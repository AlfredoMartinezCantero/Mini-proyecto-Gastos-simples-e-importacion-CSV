<?php
// back/inc/auth.php
declare(strict_types=1);

require_once __DIR__ . '/conexion_bd.php'; // incluye paths.php + session_start()

function auth_register(string $email, string $password, string $role = 'user'): int {
    $email = trim(mb_strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email no válido');
    }
    if (!in_array($role, ['admin', 'user'], true)) {
        throw new InvalidArgumentException('Rol no válido');
    }
    if (mb_strlen($password) < 8) {
        throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres');
    }

    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn()) {
        throw new RuntimeException('El email ya está registrado');
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $ins  = $pdo->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
    $ins->execute([$email, $hash, $role]);
    return (int)$pdo->lastInsertId();
}

function auth_login(string $email, string $password): bool {
    $email = trim(mb_strtolower($email));
    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT id, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    if (!$row) return false;

    if (!password_verify($password, $row['password_hash'])) return false;

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        $new = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$new, (int)$row['id']]);
    }

    $_SESSION['user_id'] = (int)$row['id'];
    $_SESSION['user_email'] = $email;
    $_SESSION['user_role'] = $row['role'];
    return true;
}

function auth_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user_role(): string {
    $r = $_SESSION['user_role'] ?? 'guest';
    return is_string($r) && $r !== '' ? $r : 'guest';
}

function current_user_is_admin(): bool {
    return current_user_role() === 'admin';
}

function require_login(): void {
    if (!current_user_id()) {
        app_redirect('/front/login.php');
    }
}

function require_admin(): void {
    if (!current_user_id() || !current_user_is_admin()) {
        http_response_code(403);
        exit('Acceso denegado. Solo administradores.');
    }
}