<?php
// back/controllers/expenses_delete_all.php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/csrf.php';
require_once __DIR__ . '/../inc/flash.php';

require_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

csrf_validate();

$confirm = trim((string)($_POST['confirm_text'] ?? ''));

// Confirmación obligatoria
if ($confirm !== 'BORRAR TODO') {
    flash_add('error', 'Confirmación incorrecta. Debes escribir exactamente: BORRAR TODO');
    app_redirect('/front/index.php');
}

$userId = (int)(current_user_id() ?? 0);
if ($userId <= 0) {
    flash_add('error', 'Sesión inválida. Vuelve a iniciar sesión.');
    app_redirect('/front/index.php');
}

$pdo = get_pdo();

try {
    $pdo->beginTransaction();

    // SIEMPRE borrar SOLO los gastos del usuario actual
    $stmt = $pdo->prepare("DELETE FROM expenses WHERE user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    $deleted = $stmt->rowCount();

    $pdo->commit();

    flash_add('success', "Borrado completado. Registros eliminados de tu cuenta: {$deleted}");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    if (defined('APP_DEBUG') && APP_DEBUG) {
        flash_add('error', 'Error al borrar: ' . $e->getMessage());
    } else {
        flash_add('error', 'No se pudo completar el borrado.');
    }
}

app_redirect('/front/index.php');