<?php
// back/controllers/expenses_insert.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();
csrf_validate();

function pdo(): PDO {
    if (function_exists('db')) {
        return db();
    }
    if (function_exists('get_pdo')) {
        return get_pdo();
    }
    throw new RuntimeException('No se encontró función de conexión PDO (db() o get_pdo()).');
}

$pdo = pdo();

// Usuario actual
$userId = (int)(current_user_id() ?? 0);
if ($userId <= 0) {
    flash_add('error', 'Sesión inválida. Vuelve a iniciar sesión.');
    header('Location: ../../front/index.php', true, 302);
    exit;
}

// Inputs
$date     = trim((string)($_POST['date'] ?? ''));
$concept  = trim((string)($_POST['concept'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$amountIn = trim((string)($_POST['amount'] ?? ''));

// ✅ Tipo (gasto/ingreso)
$type = (string)($_POST['type'] ?? 'expense');
if (!in_array($type, ['expense', 'income'], true)) {
    $type = 'expense';
}

// Validación
$errors = [];

// Fecha YYYY-MM-DD válida
$dt = DateTime::createFromFormat('Y-m-d', $date);
$okDate = ($dt instanceof DateTime) && $dt->format('Y-m-d') === $date;
if (!$okDate) {
    $errors[] = 'La fecha debe tener formato YYYY-MM-DD.';
}

// Concepto/categoría obligatorios + longitudes
if ($concept === '') {
    $errors[] = 'El concepto es obligatorio.';
} elseif (mb_strlen($concept) > 180) {
    $errors[] = 'El concepto no puede superar 180 caracteres.';
}

if ($category === '') {
    $errors[] = 'La categoría es obligatoria.';
} elseif (mb_strlen($category) > 60) {
    $errors[] = 'La categoría no puede superar 60 caracteres.';
}

// Importe: permitimos coma por usabilidad, pero normalizamos a punto.
$amountNorm = str_replace(',', '.', $amountIn);

// Acepta números decimales "normales": 12, 12.3, 12.34
// Rechaza notación científica 1e3 y otros formatos raros.
if ($amountNorm === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $amountNorm)) {
    $errors[] = 'El importe debe ser un número válido (usa punto decimal, ej.: 12.50).';
    $amount = 0.0; // placeholder
} else {
    $amount = (float)$amountNorm;
    if ($amount <= 0) {
        $errors[] = 'El importe debe ser mayor que 0.';
    } else {
        // ✅ Normalización del signo según tipo
        $amount = ($type === 'income') ? -abs($amount) : abs($amount);
    }
}

// Si hay errores: guardamos old + errors y volvemos
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = [
        'date'     => $date,
        'concept'  => $concept,
        'category' => $category,
        'amount'   => $amountIn,
        'type'     => $type,
    ];
    header('Location: ../../front/new.php', true, 302);
    exit;
}

// Insert
try {
    $stmt = $pdo->prepare(
        "INSERT INTO expenses (user_id, `date`, concept, category, amount)
         VALUES (:uid, :d, :c, :cat, :a)"
    );
    $stmt->execute([
        ':uid' => $userId,
        ':d'   => $date,
        ':c'   => $concept,
        ':cat' => $category,
        ':a'   => $amount,
    ]);

    flash_add('success', $type === 'income'
        ? 'Ingreso añadido correctamente.'
        : 'Gasto añadido correctamente.'
    );

    header('Location: ../../front/index.php', true, 302);
    exit;

} catch (Throwable $e) {
    // Mantener old input si falla la BD
    $_SESSION['form_old'] = [
        'date'     => $date,
        'concept'  => $concept,
        'category' => $category,
        'amount'   => $amountIn,
        'type'     => $type,
    ];

    if (defined('APP_DEBUG') && APP_DEBUG) {
        flash_add('error', 'Error al insertar: ' . $e->getMessage());
    } else {
        flash_add('error', 'No se ha podido guardar el registro.');
    }

    header('Location: ../../front/new.php', true, 302);
    exit;
}