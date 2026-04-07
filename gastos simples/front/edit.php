<?php

declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

require_login();

$pdo = get_pdo();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0){
    http_response_code(400);
    exit('Solicitud inválida.');
}

$isAdmin = current_user_is_admin();
$userId = current_user_id();

$sql = "SELECT id, user_id, `date`, concept, category, amount FROM expenses WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

if (!$row){
    http_response_code(404);
    exit('Gasto no encontrado.');
}

// Permisos, si no es admin, debe ser propietario
if (!$isAdmin && (int)$row['user_id'] !== (int)$userId){
    http_response_code(403);
    exit('No tienes permiso para editar este gasto.');
}

// Recuperar datos para el form
$old = $_SESSION['form_old'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);

$date     = $old['date']     ?? $row['date'];
$concept    = $old['concept']   ?? $row['concept'];
$category    = $old['category']   ?? $row['category'];
$amount    = $old['amount']   ?? (string)$row['amount'];

$flashes = flash_consume_all();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Editar gasto - Gastos Simples</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <form action="<?= app_url('/back/controllers/expenses_update.php') ?>" method="post" class="card">
</head>
<body>
    <header class="app-header">
        <h1>Editar gasto</h1>
    </header>

    <main class="container">
        <?php if ($flashes): ?>
            <div aria-live="polite" aria-atomic="true">
                <?php foreach ($flashes as $f): ?>
                    <div class="card" role="status">
                        <strong><?= htmlspecialchars(ucfirst($f['type'])) ?>:</strong>
                        <?= htmlspecialchars($f['message']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="card" role="alert" aria-live="assertive">
                <strong>Hay errores en el formulario:</strong>
                <ul>
                    <?php foreach ($error as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

    <form action="../back/controllers/expenses_update" method="POST" class="card" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <div class="field">
            <label for="date">Fecha</label>
            <input type="date" id="date" name="date" required value="<?= htmlspecialchars($date) ?>" pattern="\d{4}-\d{2}-\d{2}">
        </div>

        <div class="field">
            <label for="amount">Importe (€) - usa punto decimal (ej.: 12.50)</label>
            <input type="number" id="amount" name="amount" required step="0.01" inputmode="decimal" value="<?= htmlspecialchars($amount) ?>">
        </div>

        <div class="field actions">
            <button class="btn primary" type="submit">Guardar cambios</button>
            <a href="index.php">Cancelar</a>
        </div>
    </form>
</main>

<footer class="app-footer">
    <small>(c) <?= date('Y'); ?> Gastos Simples</small>
</footer>
</body>
</html>