<?php
// back/inc/paths.php
declare(strict_types=1);

/**
 * Base URL del proyecto (sin /front ni /back).
 * Funciona aunque el proyecto esté en subcarpetas y con espacios (%20).
 */
function app_base_url(): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    foreach (['/front/', '/back/'] as $marker) {
        $pos = strpos($script, $marker);
        if ($pos !== false) {
            return substr($script, 0, $pos);
        }
    }
    return rtrim(dirname($script), '/');
}

/** Construye una URL interna tipo /tuapp/front/index.php */
function app_url(string $path): string {
    $base = rtrim(app_base_url(), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

/** Redirección interna segura */
function app_redirect(string $path): void {
    header('Location: ' . app_url($path), true, 302);
    exit;
}