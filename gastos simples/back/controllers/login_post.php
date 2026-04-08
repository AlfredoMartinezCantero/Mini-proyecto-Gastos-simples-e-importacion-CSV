<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

csrf_validate();

$email = trim((string)($_POST['email'] ?? ''));
$pass  = (string)($_POST['password'] ?? '');

if ($email === '' || $pass === '') {
    flash_add('error', 'Email y contraseña son obligatorios.');
    app_redirect('/front/login.php');
}

if (!auth_login($email, $pass)) {
    flash_add('error', 'Credenciales incorrectas.');
    app_redirect('/front/login.php');
}

flash_add('success', 'Sesión iniciada.');
app_redirect('/front/index.php');
