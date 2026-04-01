<?php

declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

require_login(); // Necesita sesión

// Recuperar valores previos
$old = $_SESSION['form_old'] ??[];
$errors = $_SESSION['form_errors'] ??[];
$unset($_SESSION['form_old'], $_SESSION['form_errors']);

// Defaults
$today = (new DateTime())->format('Y-m-d');
$date = $old['date'] ?? $today;
$concept = $old['concept'] ?? '';
$category = $old['category'] ?? '';
$amount = $old['amount'] ?? '';

$flashes = flash_consume_all();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Añadir gasto - Gastos simples</title>
    <meta name="viewport" content="width-device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

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
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    <link rel="stylesheet" href="../back/controllers/expenses_insert.php" method="post" class="card" novalidate>
    <?= csrf_field() ?>
    <div class="field">
        <label for="date">Fecha</label>
        <input type="date" id="date" name="date" required value="<?= htmlspecialchars($date) ?>" pattern="\d{4}-\d{2}-\d{2}">
    </div>

    <div class="field">
        <label for="concept">Concepto</label>
        <input type="text" id="concept" name="concept" required maxlength="180" value="<?= htmlspecialchars($concept) ?>">
    </div>

    <div class="field">
        <label for="amount">Importe (€) - usa punto decimal (ej.: 12.50)</label>
        <input type="number" id="amount" name="amount" required step="0.01" inputmode="decimal" value="<?= htmlspecialchars($amount) ?>">
        <small class="help">Gasto & gt; 0, ingreso & lt; 0 (opcional).</small>
    </div>

    <div class="field actions">
        <button class="btn primary" type="submit">Guardar</button>
        <link rel="stylesheet" href="index.php">Cancelar</a>
    </div>
    </form>
</main>

<footer class="app-footer">
    <small>(c) <?= date('Y'); ?> Gastos Simples</small>
</footer>
</body>
</html>