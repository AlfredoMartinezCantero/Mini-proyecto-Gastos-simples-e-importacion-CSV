<?php
declare(strict_types=1);
require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_login();

// Recuperar valores previos (si hubo validación fallida)
$old    = $_SESSION['form_old'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);

// Defaults
$today = (new DateTime())->format('Y-m-d');

$date     = (string)($old['date'] ?? $today);
$concept  = (string)($old['concept'] ?? '');
$category = (string)($old['category'] ?? '');
$amount   = (string)($old['amount'] ?? '');
$typeVal  = (string)($old['type'] ?? 'expense');

if (!in_array($typeVal, ['expense', 'income'], true)) {
    $typeVal = 'expense';
}

$flashes = flash_consume_all();

// Helpers
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Añadir movimiento · Gastos Simples</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Gastos Simples</h1>
    <nav aria-label="Acciones">
      <a class="btn" href="index.php" aria-label="Volver al listado">← Volver</a>
    </nav>
  </div>
</header>

<main class="container">

  <?php if (!empty($flashes)): ?>
    <div class="flash-stack" aria-live="polite" aria-atomic="true">
      <?php foreach ($flashes as $f): ?>
        <?php $t = h((string)($f['type'] ?? 'info')); ?>
        <div class="card" role="status">
          <strong><?= h(ucfirst($t)) ?>:</strong>
          <?= h((string)($f['message'] ?? '')) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="card" role="alert" aria-live="assertive">
      <strong>Hay errores en el formulario:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= h((string)$e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card" aria-labelledby="new-titulo">
    <h2 id="new-titulo">Añadir movimiento</h2>

    <form action="../back/controllers/expenses_insert.php" method="post" novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label for="date">Fecha</label>
        <input type="date" id="date" name="date" required value="<?= h($date) ?>">
      </div>

      <div class="field">
        <label for="concept">Concepto</label>
        <input type="text" id="concept" name="concept" required maxlength="180" value="<?= h($concept) ?>">
      </div>

      <div class="field">
        <label for="category">Categoría</label>
        <input type="text" id="category" name="category" required maxlength="60"
               value="<?= h($category) ?>" placeholder="Ej.: Alimentación">
      </div>

      <div class="field">
        <label for="type">Tipo</label>
        <select id="type" name="type" required>
          <option value="expense" <?= $typeVal === 'expense' ? 'selected' : '' ?>>Gasto</option>
          <option value="income"  <?= $typeVal === 'income'  ? 'selected' : '' ?>>Ingreso</option>
        </select>
        <small class="help">El sistema guardará los ingresos como importe negativo automáticamente.</small>
      </div>

      <div class="field">
        <label for="amount">Importe (€)</label>
        <input type="number" id="amount" name="amount" required step="0.01" inputmode="decimal"
               value="<?= h($amount) ?>">
        <small class="help">Introduce el importe en positivo (ej.: 12.50). El tipo decide si es gasto o ingreso.</small>
      </div>

      <div class="field actions">
        <button class="btn primary" type="submit" aria-label="Guardar">Guardar</button>
        <a class="btn ghost" href="index.php" aria-label="Cancelar y volver al listado">Cancelar</a>
      </div>
    </form>
  </section>

</main>

<footer class="app-footer">
  <div class="container">
    <small>© <?= date('Y'); ?> Gastos Simples</small>
  </div>
</footer>

</body>
</html>