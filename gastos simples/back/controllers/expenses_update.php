<?php
// back/controllers/expenses_update.php
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

$pdo = get_pdo();

$id       = (int)($_POST['id'] ?? 0);
$date     = trim((string)($_POST['date'] ?? ''));
$concept  = trim((string)($_POST['concept'] ?? ''));
$category = trim((string)($_POST['category'] ?? ''));
$amountIn = trim((string)($_POST['amount'] ?? ''));

if ($id <= 0) {
    flash_add('error', 'Solicitud inválida.');
    app_redirect('/front/index.php');
}

// Verifica que exista y permisos
$stmt = $pdo->prepare('SELECT user_id FROM expenses WHERE id = :id');
$stmt->execute([':id' => $id]);
$current = $stmt->fetch();

if (!$current) {
    flash_add('error', 'Gasto no encontrado.');
    app_redirect('/front/index.php');
}

$isAdmin = current_user_is_admin();
$userId  = (int)current_user_id();
if (!$isAdmin && (int)$current['user_id'] !== $userId) {
    http_response_code(403);
    exit('No tienes permiso para actualizar este gasto.');
}

// Validaciones
$errors = [];

$dt = DateTime::createFromFormat('Y-m-d', $date);
$okDate = $dt && $dt->format('Y-m-d') === $date;
if (!$okDate) $errors[] = 'La fecha debe tener formato YYYY-MM-DD.';

if ($concept === '' || mb_strlen($concept) > 180) {
    $errors[] = 'El concepto es obligatorio y no puede superar 180 caracteres.';
}
if ($category === '' || mb_strlen($category) > 60) {
    $errors[] = 'La categoría es obligatoria y no puede superar 60 caracteres.';
}

$amountNorm = str_replace(',', '.', $amountIn);
if (!is_numeric($amountNorm)) {
    $errors[] = 'El importe debe ser un número válido (usa punto decimal).';
}
$amount = (float)$amountNorm;

if ($errors) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_old'] = [
        'date' => $date,
        'concept' => $concept,
        'category' => $category,
        'amount' => $amountIn,
    ];
    app_redirect('/front/edit.php?id=' . $id);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE expenses
         SET `date` = :d, concept = :c, category = :cat, amount = :a
         WHERE id = :id"
    );
    $stmt->execute([
        ':d'   => $date,
        ':c'   => $concept,
        ':cat' => $category,
        ':a'   => $amount,
        ':id'  => $id,
    ]);

    flash_add('success', 'Gasto actualizado correctamente.');
} catch (Throwable $e) {
    if (defined('APP_DEBUG') && APP_DEBUG) {
        flash_add('error', 'Error al actualizar: ' . $e->getMessage());
    } else {
        flash_add('error', 'No se ha podido actualizar el gasto.');
    }
}

app_redirect('/front/index.php');