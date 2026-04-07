<?php
// back/controllers/expenses_insert.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

require_login();
csrf_validate();

$pdo    = get_pdo();
$userId = (int)current_user_id();
$isAdmin = current_user_is_admin(); // (por si en un futuro permitimos crear para otro user)

$date     = trim((string)($_POST['date'] ?? ''));
$concept  = trim((string)($_POST['concept'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$amountIn = trim((string)($_POST['amount'] ?? ''));

// Validación
$errors = [];

// Fecha YYYY-MM-DD válida
$dt = DateTime::createFromFormat('Y-m-d', $date);
$okDate = $dt && $dt->format('Y-m-d') === $date;
if (!$okDate) { $errors[] = 'La fecha debe tener formato YYYY-MM-DD.'; }

// Concepto/categoría longitudes
if ($concept === '' || mb_strlen($concept) > 180) {
    $errors[] = 'El concepto es obligatorio y no puede superar 180 caracteres.';
}
if ($category === '' || mb_strlen($category) > 60) {
    $errors[] = 'La categoría es obligatoria y no puede superar 60 caracteres.';
}

// Importe: permitimos coma por usabilidad, pero normalizamos a punto.
$amountNorm = str_replace(',', '.', $amountIn);
if (!is_numeric($amountNorm)) {
    $errors[] = 'El importe debe ser un número válido (usa punto decimal).';
}
$amount = (float)$amountNorm;

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = [
        'date' => $date, 'concept' => $concept, 'category' => $category, 'amount' => $amountIn
    ];
    header('Location: ../../front/new.php', true, 302);
    exit;
}

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
    flash_add('success', 'Gasto añadido correctamente.');
} catch (Throwable $e) {
    if (APP_DEBUG) {
        flash_add('error', 'Error al insertar: ' . $e->getMessage());
    } else {
        flash_add('error', 'No se ha podido guardar el gasto.');
    }
}

header('Location: ../../front/index.php', true, 302);
exit;