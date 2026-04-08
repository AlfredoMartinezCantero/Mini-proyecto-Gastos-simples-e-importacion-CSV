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

$flashes = flash_consume_all();
$import  = $_SESSION['import_ctx'] ?? null;

// Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Si no existe app_url(), usamos rutas relativas (MVP)
function url(string $path): string {
    if (function_exists('app_url')) {
        return (string) app_url($path);
    }
    // fallback: convierte "/front/index.php" -> "../front/index.php"
    $path = ltrim($path, '/');
    return '../' . $path;
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Importar CSV · Gastos Simples</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">

  <!-- CSS -->
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
        <div class="card" role="status">
          <strong><?= h(ucfirst($type)) ?>:</strong>
          <?= h((string)($f['message'] ?? '')) ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$import): ?>
    <!-- Estado 1: Subida -->
    <section class="card" aria-labelledby="subida-titulo">
      <h2 id="subida-titulo">Importar archivo CSV</h2>
      <p class="muted">
        Cabecera obligatoria: <code>date,concept,category,amount</code>. Separador: coma. Decimales: punto.
      </p>

      <form action="<?= h(url('back/controllers/import_preview.php')) ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="field">
          <label for="csv">Archivo CSV</label>
          <input
            type="file"
            id="csv"
            name="csv"
            accept=".csv,text/csv"
            required
            aria-describedby="csv-help"
          >
          <small id="csv-help" class="help">Tamaño máximo: 2 MB · UTF‑8 recomendado</small>
        </div>

        <div class="field actions">
          <button class="btn primary" type="submit" aria-label="Previsualizar CSV">Previsualizar</button>
          <a class="btn" href="<?= h(url('front/index.php')) ?>" aria-label="Cancelar importación">Cancelar</a>
        </div>
      </form>
    </section>

  <?php else: ?>
    <!-- Estado 2: Preview + Confirmación -->
    <section class="card" aria-labelledby="preview-titulo">
      <h2 id="preview-titulo">Vista previa</h2>

      <p class="muted">
        Archivo: <strong><?= h((string)($import['original_name'] ?? 'CSV')) ?></strong> ·
        Filas leídas: <strong><?= (int)($import['total_lines'] ?? 0) ?></strong> ·
        Válidas: <strong><?= (int)($import['valid_rows'] ?? 0) ?></strong> ·
        Errores: <strong><?= (int)($import['error_rows'] ?? 0) ?></strong>
      </p>

      <?php if (!empty($import['preview_rows'])): ?>
        <div class="table-wrapper">
          <table class="table" aria-label="Vista previa CSV">
            <thead>
              <tr>
                <th scope="col">Línea</th>
                <th scope="col">Fecha</th>
                <th scope="col">Concepto</th>
                <th scope="col">Categoría</th>
                <th scope="col" class="num">Importe</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($import['preview_rows'] as $r): ?>
                <tr>
                  <td><?= (int)($r['line'] ?? 0) ?></td>
                  <td><?= h((string)($r['date'] ?? '')) ?></td>
                  <td><?= h((string)($r['concept'] ?? '')) ?></td>
                  <td><?= h((string)($r['category'] ?? '')) ?></td>
                  <td class="num"><?= h((string)($r['amount'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <?php if (!empty($import['errors'])): ?>
        <div class="card" role="alert" aria-live="assertive">
          <strong>Errores detectados:</strong>
          <ul>
            <?php foreach ($import['errors'] as $e): ?>
              <li>
                <strong>Línea <?= (int)($e['line'] ?? 0) ?>:</strong>
                <?= h((string)($e['reason'] ?? 'Error')) ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="help">Las filas con error se omiten automáticamente.</p>
        </div>
      <?php endif; ?>

      <div class="field actions">
        <form action="<?= h(url('back/controllers/import_commit.php')) ?>" method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="import_token" value="<?= h((string)($import['token'] ?? '')) ?>">
          <button class="btn primary" type="submit" aria-label="Confirmar importación CSV">Confirmar importación</button>
        </form>

        <form action="<?= h(url('back/controllers/import_preview.php')) ?>" method="post" class="inline-form" aria-label="Cancelar importación">
          <?= csrf_field() ?>
          <input type="hidden" name="cancel" value="1">
          <button class="btn" type="submit">Cancelar</button>
        </form>
      </div>
    </section>
  <?php endif; ?>

</main>

<footer class="app-footer">
  <div class="container">
    <small>© <?= date('Y') ?> Gastos Simples</small>
  </div>
</footer>

</body>
</html>