<?php

declare(strict_types=1);

/**
 * Mensajes flash sencillos en $_SESSION.
 * Tipos sugeridos: success, error, warning, info.
 */

function flash_add(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_consume_all(): array {
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}