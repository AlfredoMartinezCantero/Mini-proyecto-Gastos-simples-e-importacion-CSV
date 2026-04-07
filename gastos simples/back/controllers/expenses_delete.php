<?php
// back/controllers/expenses_delete.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

require_login();
csrf_validate();

$pdo = get_pdo();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_add('error', 'Solicitud inválida.');
    header('Location: /front/index.php', true, 302);
    exit;
}

// Cargar para validar permisos
$stmt = $pdo->prepare('SELECT user_id FROM expenses WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row) {
    flash_add('error', 'Gasto no encontrado.');
    header('Location: /front/index.php', true, 302);
    exit;
}

$isAdmin = current_user_is_admin();
$userId  = (int)current_user_id();

if (!$isAdmin && (int)$row['user_id'] !== $userId) {
    http_response_code(403);
    exit('No tienes permiso para borrar este gasto.');
}

try {
    $del = $pdo->prepare('DELETE FROM expenses WHERE id = :id');
    $del->execute([':id' => $id]);
    flash_add('success', 'Gasto borrado correctamente.');
} catch (Throwable $e) {
    if (APP_DEBUG) {
        flash_add('error', 'Error al borrar: ' . $e->getMessage());
    } else {
        flash_add('error', 'No se ha podido borrar el gasto.');
    }
}


app_redirect('/front/index.php');
exit;