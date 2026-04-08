<?php
declare(strict_types=1);

require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

// Aseguramos sesión para flashes / old input
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
function pdo(): PDO {
    if (function_exists('db')) {
        return db();
    }
    if (function_exists('get_pdo')) {
        return get_pdo();
    }
    throw new RuntimeException('No se encontró función de conexión PDO (db() o get_pdo()).');
}

/**
 * Si no existe app_url(), usa rutas relativas (MVP).
 */
function url(string $path): string {
    if (function_exists('app_url')) {
        return (string) app_url($path);
    }
    $path = ltrim($path, '/');
    return '../' . $path;
}

// Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

require_login();

$pdo = pdo();

// Validación de id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$isAdmin = current_user_is_admin();
$userId  = (int)(current_user_id() ?? 0);

// Cargamos gasto
$stmt = $pdo->prepare("SELECT id, user_id, `date`, concept, category, amount FROM expenses WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    exit('Gasto no encontrado.');
}
if (!$isAdmin && (int)$row['user_id'] !== $userId) {
    http_response_code(403);
    exit('No tienes permiso.');
}

// Old input + errores de validación (si vienes de un submit fallido)
$old    = $_SESSION['form_old'] ?? [];
$errors = $_SESSION['form_errors'] ?? [];
unset($_SESSION['form_old'], $_SESSION['form_errors']);

$date     = (string)($old['date']     ?? $row['date']);
$concept  = (string)($old['concept']  ?? $row['concept']);
$category = (string)($old['category'] ?? $row['category']);

// Tipo: si viene de old lo respetamos; si no, lo inferimos por el signo guardado
$typeVal = (string)($old['type'] ?? (((float)$row['amount'] < 0) ? 'income' : 'expense'));
if (!in_array($typeVal, ['expense', 'income'], true)) {
    $typeVal = 'expense';
}

// Importe: mostrar SIEMPRE en positivo (abs), salvo que venga de old (lo que escribió el usuario)
if (isset($old['amount'])) {
    $amountDisplay = (string)$old['amount'];
} else {
    $amountDisplay = number_format(abs((float)$row['amount']), 2, '.', '');
}

$flashes = flash_consume_all();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Editar gasto · Gastos Simples</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Gastos Simples</h1>
    <nav aria-label="Acciones">
      <a class="btn" href="<?= h(url('front/index.php')) ?>" aria-label="Volver al listado">← Volver</a>
    </nav>
  </div>
</header>

<main class="container">

  <?php if (!empty($flashes)): ?>
    <div class="flash-stack" aria-live="polite" aria-atomic="true">
      <?php foreach ($flashes as $f): ?>
        <?php $type = h((string)($f['type'] ?? 'info')); ?>
        <div class="alert <?= $type ?>" role="status">
          <strong><?= h(ucfirst($type)) ?>:</strong>
          <?= h((string)($f['message'] ?? '')) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($errors)): ?>
    <div class="alert error" role="alert" aria-live="assertive">
      <strong>Hay errores en el formulario:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= h((string)$e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <section class="card" aria-labelledby="edit-titulo">
    <h2 id="edit-titulo">Editar movimiento</h2>

    <form action="<?= h(url('back/controllers/expenses_update.php')) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">

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
        <input type="text" id="category" name="category" required maxlength="60" value="<?= h($category) ?>">
      </div>

      <div class="field">
        <label for="type">Tipo</label>
        <select id="type" name="type" required>
          <option value="expense" <?= $typeVal === 'expense' ? 'selected' : '' ?>>Gasto</option>
          <option value="income"  <?= $typeVal === 'income'  ? 'selected' : '' ?>>Ingreso</option>
        </select>
        <small class="help">El sistema guarda los ingresos como importe negativo automáticamente.</small>
      </div>

      <!-- Importe siempre en positivo: el tipo decide el signo -->
      <div class="field">
        <label for="amount">Importe (€)</label>
        <input
          type="number"
          id="amount"
          name="amount"
          required
          step="0.01"
          inputmode="decimal"
          value="<?= h($amountDisplay) ?>"
        >
        <small class="help">Introduce el importe en positivo (ej.: 1200.00). El tipo define si es gasto o ingreso.</small>
      </div>

      <div class="field actions">
        <button class="btn primary" type="submit" aria-label="Guardar cambios">Guardar cambios</button>
        <a class="btn ghost" href="<?= h(url('front/index.php')) ?>" aria-label="Cancelar y volver">Cancelar</a>
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