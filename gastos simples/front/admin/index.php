<?php
declare(strict_types=1);

require_once __DIR__ . '/../../back/inc/conexion_bd.php';
require_once __DIR__ . '/../../back/inc/auth.php';
require_once __DIR__ . '/../../back/inc/flash.php';

require_login();
require_admin();

$pdo = get_pdo();

$stats = [
  'users' => (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'expenses' => (int)$pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn(),
  'sum_expenses' => (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN amount>0 THEN amount ELSE 0 END),0) FROM expenses")->fetchColumn(),
  'sum_incomes'  => (float)$pdo->query("SELECT COALESCE(SUM(CASE WHEN amount<0 THEN -amount ELSE 0 END),0) FROM expenses")->fetchColumn(),
];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function eur(float $n): string { return '€ ' . number_format($n, 2, ',', '.'); }

$flashes = flash_consume_all();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Admin · Gastos Simples AMC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css')) ?>">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Panel de administración</h1>
    <nav aria-label="Acciones">
      <a class="btn" href="<?= h(app_url('/front/index.php')) ?>">Volver</a>
    </nav>
  </div>
</header>

<main class="container">

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= h($f['type'] ?? 'info') ?>" role="status" aria-live="polite" aria-atomic="true">
      <strong><?= h(ucfirst($f['type'] ?? 'info')) ?>:</strong> <?= h($f['message'] ?? '') ?>
    </div>
  <?php endforeach; ?>

  <section class="card">
    <h2>Resumen</h2>
    <div class="balance-row">
      <div class="metric">
        <span class="value"><?= (int)$stats['users'] ?></span>
        <span class="label">Usuarios</span>
      </div>
      <div class="metric">
        <span class="value"><?= (int)$stats['expenses'] ?></span>
        <span class="label">Movimientos</span>
      </div>
      <div class="metric">
        <span class="value"><?= eur($stats['sum_expenses']) ?></span>
        <span class="label">Gastos totales</span>
      </div>
      <div class="metric">
        <span class="value"><?= eur($stats['sum_incomes']) ?></span>
        <span class="label">Ingresos totales</span>
      </div>
    </div>
  </section>

  <section class="card">
    <h2>Gestión</h2>
    <a class="btn primary" href="<?= h(app_url('/front/admin/users.php')) ?>">Gestionar usuarios</a>
  </section>

</main>

<footer class="app-footer">
  <div class="container"><small>© <?= date('Y') ?> Gastos Simples AMC</small></div>
</footer>

</body>
</html>