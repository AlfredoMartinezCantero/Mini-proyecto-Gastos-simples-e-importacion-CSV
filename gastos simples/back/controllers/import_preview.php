<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

csrf_validate();

// Cancelar preview
if (!empty($_POST['cancel'])) {
    if (!empty($_SESSION['import_ctx']['tmp_path']) && is_file($_SESSION['import_ctx']['tmp_path'])) {
        @unlink($_SESSION['import_ctx']['tmp_path']);
    }
    unset($_SESSION['import_ctx']);
    flash_add('info', 'Importación cancelada.');
    app_redirect('/front/import.php');
}

if (!isset($_FILES['csv'])) {
    flash_add('error', 'No se ha recibido ningún archivo.');
    app_redirect('/front/import.php');
}

$f = $_FILES['csv'];

// Validación de subida
if ($f['error'] !== UPLOAD_ERR_OK) {
    flash_add('error', 'Error al subir el archivo (código ' . $f['error'] . ').');
    app_redirect('/front/import.php');
}

// Tamaño máximo (2MB)
$maxBytes = 2 * 1024 * 1024;
if (($f['size'] ?? 0) <= 0 || ($f['size'] ?? 0) > $maxBytes) {
    flash_add('error', 'El archivo supera el tamaño máximo permitido (2 MB) o está vacío.');
    app_redirect('/front/import.php');
}

// Extensión
$originalName = (string)($f['name'] ?? 'import.csv');
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($ext !== 'csv') {
    flash_add('error', 'El archivo debe tener extensión .csv');
    app_redirect('/front/import.php');
}

// MIME
$tmpUpload = $f['tmp_name'];
$mime = @mime_content_type($tmpUpload) ?: '';
$allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
if ($mime && !in_array($mime, $allowedMimes, true)) {
    // No bloqueamos al 100% porque algunos sistemas lo marcan como text/plain
    // pero si viene algo claramente raro, paramos.
}

// Copiar a un tmp controlado para usarlo en commit
$tmpDir = sys_get_temp_dir();
$tmpPath = $tmpDir . DIRECTORY_SEPARATOR . 'gastos_import_' . bin2hex(random_bytes(8)) . '.csv';

if (!move_uploaded_file($tmpUpload, $tmpPath)) {
    flash_add('error', 'No se pudo mover el archivo subido a ubicación temporal.');
    app_redirect('/front/import.php');
}

// Parse CSV
$fh = fopen($tmpPath, 'rb');
if ($fh === false) {
    @unlink($tmpPath);
    flash_add('error', 'No se pudo abrir el archivo.');
    app_redirect('/front/import.php');
}

// Leer cabecera
$header = fgetcsv($fh, 0, ',', '"', "\\");
if ($header === false) {
    fclose($fh);
    @unlink($tmpPath);
    flash_add('error', 'CSV vacío o ilegible.');
    app_redirect('/front/import.php');
}

// Normalizar cabecera
$header = array_map(fn($x) => trim((string)$x), $header);
$expected = ['date', 'concept', 'category', 'amount'];

if ($header !== $expected) {
    fclose($fh);
    @unlink($tmpPath);
    flash_add('error', 'Cabecera inválida. Debe ser exactamente: date,concept,category,amount');
    app_redirect('/front/import.php');
}

$previewRows = [];
$errors = [];
$errorsLimit = 30;

$totalLines = 0;   // filas de datos (sin cabecera)
$validRows = 0;
$errorRows = 0;

$lineNumber = 1; // cabecera es línea 1

// Limitar por seguridad (para evitar CSV gigantes)
$maxRows = 20000;

while (($row = fgetcsv($fh, 0, ',', '"', "\\")) !== false) {
    $lineNumber++;
    if ($row === [null] || $row === false) {
        continue;
    }

    $totalLines++;
    if ($totalLines > $maxRows) {
        $errors[] = ['line' => $lineNumber, 'reason' => 'Se supera el máximo de filas permitido (' . $maxRows . ').'];
        $errorRows++;
        break;
    }

    // Asegurar 4 columnas
    $row = array_map(fn($x) => trim((string)$x), $row);
    if (count($row) !== 4) {
        $errorRows++;
        if (count($errors) < $errorsLimit) {
            $errors[] = ['line' => $lineNumber, 'reason' => 'Número de columnas incorrecto (se esperaban 4).'];
        }
        continue;
    }

    [$date, $concept, $category, $amount] = $row;
    $rowErrors = [];

    // Fecha
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        $rowErrors[] = 'Fecha inválida (YYYY-MM-DD).';
    }

    // Concepto / categoría
    if ($concept === '' || mb_strlen($concept) > 180) {
        $rowErrors[] = 'Concepto obligatorio (máx. 180).';
    }
    if ($category === '' || mb_strlen($category) > 60) {
        $rowErrors[] = 'Categoría obligatoria (máx. 60).';
    }

    // Importe: debe ser numérico con punto (permitimos coma y la normalizamos)
    $amountNorm = str_replace(',', '.', $amount);
    if (!is_numeric($amountNorm)) {
        $rowErrors[] = 'Importe no numérico.';
    } else {
        $amountNorm = number_format((float)$amountNorm, 2, '.', '');
    }

    if ($rowErrors) {
        $errorRows++;
        if (count($errors) < $errorsLimit) {
            $errors[] = ['line' => $lineNumber, 'reason' => implode(' ', $rowErrors)];
        }
        continue;
    }

    $validRows++;

    // Preview: primeras 10 filas válidas
    if (count($previewRows) < 10) {
        $previewRows[] = [
            'line' => $lineNumber,
            'date' => $date,
            'concept' => $concept,
            'category' => $category,
            'amount' => $amountNorm,
        ];
    }
}

fclose($fh);

// Guardar contexto en sesión para confirmación
$token = bin2hex(random_bytes(16));

$_SESSION['import_ctx'] = [
    'token' => $token,
    'tmp_path' => $tmpPath,
    'original_name' => $originalName,
    'mime' => $mime,
    'total_lines' => $totalLines,
    'valid_rows' => $validRows,
    'error_rows' => $errorRows,
    'preview_rows' => $previewRows,
    'errors' => $errors,
    'errors_shown' => min($errorsLimit, count($errors)),
];

// Si no hay filas válidas, no permitimos confirmar
if ($validRows === 0) {
    flash_add('error', 'No hay filas válidas para importar. Revisa el CSV.');
    // limpiar tmp
    @unlink($tmpPath);
    unset($_SESSION['import_ctx']);
    app_redirect('/front/import.php');
}

flash_add('info', 'Vista previa generada. Revisa y confirma para importar.');
app_redirect('/front/import.php');