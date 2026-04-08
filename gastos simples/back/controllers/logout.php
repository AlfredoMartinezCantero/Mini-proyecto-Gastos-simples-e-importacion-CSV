<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/flash.php';

auth_logout();
flash_add('success', 'Sesión cerrada.');
app_redirect('/front/login.php');
