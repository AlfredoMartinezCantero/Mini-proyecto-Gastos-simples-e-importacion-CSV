<?php
declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

$flashes = flash_consume_all();

if (current_user_id()) {
    app_redirect('/front/index.php');
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Registro · Gastos Simples AMC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?= h(app_url('/assets/css/style.css')) ?>">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Gastos Simples AMC</h1>
  </div>
</header>

<main class="container">

  <?php foreach ($flashes as $f): ?>
    <div class="card <?= h($f['type'] ?? 'info') ?>" role="status" aria-live="polite" aria-atomic="true">
      <strong><?= h(ucfirst($f['type'] ?? 'info')) ?>:</strong> <?= h($f['message'] ?? '') ?>
    </div>
  <?php endforeach; ?>

  <section class="card" aria-labelledby="reg-titulo">
    <h2 id="reg-titulo">Crear cuenta</h2>

    <form action="<?= h(app_url('/back/controllers/register_post.php')) ?>" method="post" novalidate>
      <?= csrf_field() ?>
      
      <div class="field">
        <label for="email">Email</label>
        <input id="email" name="email" type="email" required autocomplete="email">
      </div>

      <div class="field">
        <label for="password">Contraseña (mín. 8)</label>
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
      </div>

      <div class="field">
        <label for="password2">Repite la contraseña</label>
        <input id="password2" name="password2" type="password" required minlength="8" autocomplete="new-password">
      </div>

      <div class="field actions">
        <button class="btn primary" type="submit">Crear cuenta</button>
        <a class="btn" href="<?= h(app_url('/front/login.php')) ?>">Volver a login</a>
      </div>
    </form>
  </section>

</main>

<footer class="app-footer">
  <div class="container">
    <small>© <?= date('Y'); ?></small>
  </div>
</footer>

</body>
</html>