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
$role = (string)($_POST['role'] ?? '');

if ($id <= 0 || !in_array($role, ['admin','user'], true)) {
    flash_add('error', 'Datos inválidos.');
    app_redirect('/front/admin/users.php');
}

// Protección: impedir degradar al último admin
$adminsCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$id]);
$oldRole = (string)$stmt->fetchColumn();

if ($oldRole === '') {
    flash_add('error', 'Usuario no encontrado.');
    app_redirect('/front/admin/users.php');
}

if ($oldRole === 'admin' && $role === 'user' && $adminsCount <= 1) {
    flash_add('error', 'No puedes degradar al último admin.');
    app_redirect('/front/admin/users.php');
}

if ($id === (int)current_user_id() && $oldRole === 'admin' && $role === 'user' && $adminsCount <= 1) {
    flash_add('error', 'No puedes degradarte siendo el último admin.');
    app_redirect('/front/admin/users.php');
}

$upd = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
$upd->execute([':role' => $role, ':id' => $id]);

flash_add('success', 'Rol actualizado correctamente.');
app_redirect('/front/admin/users.php');