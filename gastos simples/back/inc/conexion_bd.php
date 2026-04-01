<?php
declare(strict_types=1);

session_start();

/**
 * Configuración: ajustar aqui las credenciales o via variables de entorno.
 * - Variables de entorno admitidas: DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
 */

const APP_DEBUG = true; // en producción: false

function env(string $key, ?string $default = null): ?string{
    $v = getenv($key);
    return ($v === false) ? $default : $v;
}

function get_pdo(): PDO{
    static $pdo = null;
    if ($pdo instanceof PDO){
        return $pdo;
    }

    $host = env('DB_HOST', '127.0.0.1');
    $name = env('DB_NAME', 'gastos_simples');
    $user = env('DB_USER', 'root'); // Cambiar el entorno
    $pass = env('DB_PASS', '');     // Cambiar el entorno
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    
    try{
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_spanish_ci");
        $pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES, ERROR_FOR_DIVISION_BY_ZERO, NO_ENGINE_SUBSTITUTION'");
        return $pdo;
} catch (Throwable $e){
    if (APP_DEBUG){
        $msg = htmlspecialchars($e->getMessage());
        die("Error de conexión MySQL: {$msg}");
    }
    http_response_code(500);
    exit('Error interno.');
    }
}