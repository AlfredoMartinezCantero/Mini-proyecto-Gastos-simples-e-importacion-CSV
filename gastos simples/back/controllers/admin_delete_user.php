<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

require_login();
require_admin();
csrf_validate();

$pdo = get_pdo();

$id = (int)($_POST['id'] ?? 0);
$confirm = trim((string)($_POST['confirm_text'] ?? ''));

if ($id <= 0) {
    flash_add('error', 'ID inválido.');
    app_redirect('/front/admin/users.php');
}
if ($confirm !== 'BORRAR USUARIO') {
    flash_add('error', 'Confirmación incorrecta. Debes escribir: BORRAR USUARIO');
    app_redirect('/front/admin/users.php');
}

// No permitir borrar al propio usuario si es el último admin
$adminsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$id]);
$role = (string)$stmt->fetchColumn();

if ($role === '') {
    flash_add('error', 'Usuario no encontrado.');
    app_redirect('/front/admin/users.php');
}
if ($role === 'admin' && $adminsCount <= 1) {
    flash_add('error', 'No puedes borrar al último admin.');
    app_redirect('/front/admin/users.php');
}
if ($id === (int)current_user_id() && $role === 'admin' && $adminsCount <= 1) {
    flash_add('error', 'No puedes borrarte siendo el último admin.');
    app_redirect('/front/admin/users.php');
}

$del = $pdo->prepare("DELETE FROM users WHERE id = ?");
$del->execute([$id]);

flash_add('success', 'Usuario eliminado. Sus gastos se han borrado por cascada.');
app_redirect('/front/admin/users.php');