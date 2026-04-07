<?php
// back/inc/paths.php
declare(strict_types=1);

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

function app_url(string $path): string {
    $base = rtrim(app_base_url(), '/');
    $path = '/' . ltrim($path, '/');
    return $base . $path;
}

function app_redirect(string $path): void {
    header('Location: ' . app_url($path), true, 302);
    exit;
}