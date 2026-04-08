<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

csrf_validate();

$email = trim((string)($_POST['email'] ?? ''));
$p1    = (string)($_POST['password'] ?? '');
$p2    = (string)($_POST['password2'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash_add('error', 'Email no válido.');
    app_redirect('/front/register.php');
}
if (strlen($p1) < 8) {
    flash_add('error', 'La contraseña debe tener al menos 8 caracteres.');
    app_redirect('/front/register.php');
}
if ($p1 !== $p2) {
    flash_add('error', 'Las contraseñas no coinciden.');
    app_redirect('/front/register.php');
}

try {
    auth_register($email, $p1, 'user');
    flash_add('success', 'Cuenta creada. Ya puedes iniciar sesión.');
    app_redirect('/front/login.php');
} catch (Throwable $e) {
    flash_add('error', 'No se pudo crear la cuenta (¿email ya registrado?).');
    app_redirect('/front/register.php');
}