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

$ctx = $_SESSION['import_ctx'] ?? null;
$token = (string)($_POST['import_token'] ?? '');

if (!$ctx || empty($ctx['token']) || !hash_equals($ctx['token'], $token)) {
    flash_add('error', 'Sesión de importación inválida o caducada. Vuelve a previsualizar el CSV.');
    app_redirect('/front/import.php');
}

$tmpPath = $ctx['tmp_path'] ?? '';
if (!$tmpPath || !is_file($tmpPath)) {
    unset($_SESSION['import_ctx']);
    flash_add('error', 'No se encuentra el archivo temporal. Vuelve a subir el CSV.');
    app_redirect('/front/import.php');
}

$pdo = get_pdo();
$isAdmin = current_user_is_admin();
$userId  = (int)(current_user_id() ?? 0);

// Releer CSV
$fh = fopen($tmpPath, 'rb');
if ($fh === false) {
    flash_add('error', 'No se pudo abrir el archivo temporal.');
    app_redirect('/front/import.php');
}

// Leer cabecera
$header = fgetcsv($fh, 0, ',', '"', "\\");
$header = array_map(fn($x) => trim((string)$x), (array)$header);
$expected = ['date', 'concept', 'category', 'amount'];

if ($header !== $expected) {
    fclose($fh);
    @unlink($tmpPath);
    unset($_SESSION['import_ctx']);
    flash_add('error', 'Cabecera inválida al confirmar. Vuelve a subir el CSV.');
    app_redirect('/front/import.php');
}

$inserted = 0;
$skipped  = 0; // duplicados
$errors   = 0;

$lineNumber = 1; // cabecera
$totalLines = 0;

$maxRows = 20000;

try {
    $pdo->beginTransaction();

    $ins = $pdo->prepare(
        "INSERT INTO expenses (user_id, `date`, concept, category, amount)
         VALUES (:uid, :d, :c, :cat, :a)"
    );

    // si existe exactamente igual, lo omitimos
    $chk = $pdo->prepare(
        "SELECT 1 FROM expenses
         WHERE user_id = :uid AND `date` = :d AND concept = :c AND category = :cat AND amount = :a
         LIMIT 1"
    );

    while (($row = fgetcsv($fh, 0, ',', '"', "\\")) !== false) {
        $lineNumber++;
        $totalLines++;
        if ($totalLines > $maxRows) { $errors++; break; }

        $row = array_map(fn($x) => trim((string)$x), $row);
        if (count($row) !== 4) { $errors++; continue; }

        [$date, $concept, $category, $amount] = $row;

        // Validación mínima
        $dt = DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) { $errors++; continue; }

        if ($concept === '' || mb_strlen($concept) > 180) { $errors++; continue; }
        if ($category === '' || mb_strlen($category) > 60) { $errors++; continue; }

        $amountNorm = str_replace(',', '.', $amount);
        if (!is_numeric($amountNorm)) { $errors++; continue; }
        $amountNorm = number_format((float)$amountNorm, 2, '.', '');

        // Check duplicado
        $chk->execute([
            ':uid' => $userId,
            ':d'   => $date,
            ':c'   => $concept,
            ':cat' => $category,
            ':a'   => $amountNorm,
        ]);

        if ($chk->fetchColumn()) {
            $skipped++;
            continue;
        }

        // Insert
        $ins->execute([
            ':uid' => $userId,
            ':d'   => $date,
            ':c'   => $concept,
            ':cat' => $category,
            ':a'   => $amountNorm,
        ]);

        $inserted++;
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fclose($fh);
    @unlink($tmpPath);
    unset($_SESSION['import_ctx']);
    flash_add('error', (defined('APP_DEBUG') && APP_DEBUG) ? ('Error importando: ' . $e->getMessage()) : 'Error al importar.');
    app_redirect('/front/import.php');
}

fclose($fh);

// Limpiar contexto
@unlink($tmpPath);
unset($_SESSION['import_ctx']);

// Mensaje resumen
flash_add(
    'success',
    "Importación completada. Insertados: {$inserted} · Omitidos (duplicados): {$skipped} · Filas con error: {$errors}"
);

app_redirect('/front/index.php');
