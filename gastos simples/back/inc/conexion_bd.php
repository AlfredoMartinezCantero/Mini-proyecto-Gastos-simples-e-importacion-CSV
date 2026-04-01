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
    $user = env('DB_USER', 'gastos_user'); // Cambiar el entorno
    $pass = env('DB_PASS', 'TuClaveSuperSegura_2026');     // Cambiar el entorno
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
    
    try{
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $pdo->exec("SET NAMES {$charset} COLLATE utf8mb4_spanish_ci");

// ... dentro de back/inc/conexion_bd.php, tras crear $pdo:

// (mantén también tu línea de NAMES/colación, está bien)
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_spanish_ci");

/**
 * Intenta varios sql_mode válidos (sin espacios en los tokens) hasta que uno funcione.
 * Evita errores 1231 por tokens no soportados o con espacios accidentales.
 */
function setStrictSqlMode(PDO $pdo): void {
    $candidates = [
        // MySQL 8.x / MariaDB modernos (si soportan el token explícito de división por cero)
        "STRICT_ALL_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION",
        // Apto para la mayoría de instalaciones
        "STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION",
        // Alternativa “transaccional” si STR_ALL_TABLES no se admite
        "STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION",
        "STRICT_TRANS_TABLES",
    ];

    foreach ($candidates as $modes) {
        try {
            $pdo->exec("SET SESSION sql_mode = '{$modes}'");
            return; // el primero que funcione nos vale
        } catch (Throwable $e) {
            // probar el siguiente
        }
    }
    // Si todos fallan, no hacemos nada (usará el sql_mode por defecto del servidor)
}

// Llamada
setStrictSqlMode($pdo);

// Opcional (solo en desarrollo): ver qué modo quedó activo
if (defined('APP_DEBUG') && APP_DEBUG) {
    $mode = $pdo->query("SELECT @@SESSION.sql_mode AS m")->fetchColumn();
    // error_log("SQL_MODE activo: " . $mode);
}
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