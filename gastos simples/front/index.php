<?php
declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';
require_once __DIR__ . '/../back/inc/flash.php';

function pdo(): PDO {
    if (function_exists('db')) {
        return db();
    }
    if (function_exists('get_pdo')) {
        return get_pdo();
    }
    throw new RuntimeException('No se encontró función de conexión PDO (db() o get_pdo()).');
}

$pdo = pdo();

// Login obligatorio
require_login();

// Helpers
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function eur(float $n): string { return '€ ' . number_format($n, 2, ',', '.'); }

// Auth helpers esperados (si no existen, fallback suave)
$userId  = function_exists('current_user_id') ? (int)(current_user_id() ?? 0) : (int)($_SESSION['user_id'] ?? 0);
$isAdmin = function_exists('current_user_is_admin') ? (bool)current_user_is_admin() : (($_SESSION['user_role'] ?? '') === 'admin');

// Lectura de filtros
$month = trim((string)($_GET['month'] ?? ''));

// Mes del gráfico (independiente del filtro del listado)
$chart_month = trim((string)($_GET['chart_month'] ?? ''));
if ($chart_month === '') {
    $chart_month = (new DateTime())->format('Y-m');
}
$category = trim((string)($_GET['category'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 25;

// Construcción dinámica del WHERE
$w    = [];
$args = [];

// SIEMPRE filtrar por usuario (incluido admin)
$w[] = 'user_id = :uid';
$args[':uid'] = $userId;

// Mes (YYYY-MM) -> rango de fechas
$from = $to = null;
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $dt = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if ($dt instanceof DateTime) {
        $dt->setTime(0, 0, 0);
        $from = $dt->format('Y-m-d');
        $to   = (clone $dt)->modify('last day of this month')->format('Y-m-d');
        $w[] = '`date` BETWEEN :from AND :to';
        $args[':from'] = $from;
        $args[':to']   = $to;
    }
}

// Categoría (igualdad)
if ($category !== '') {
    $w[] = 'category = :category';
    $args[':category'] = $category;
}

// Búsqueda por texto en concepto (LIKE)
if ($q !== '') {
    $w[] = 'concept LIKE :q';
    $args[':q'] = '%' . $q . '%';
}

$where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

// Conteo total para paginación
$sqlCount = "SELECT COUNT(*) FROM expenses {$where}";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($args);
$totalRows  = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// Query de datos
$sql = "SELECT id, `date`, concept, category, amount
        FROM expenses
        {$where}
        ORDER BY `date` DESC, id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($args as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Balance (totales)
$sqlBal = "SELECT
  COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS total_gastos,
  COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS total_ingresos,
  COALESCE(SUM(amount), 0) AS neto_contable,
  COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0)
    - COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS saldo
FROM expenses {$where}";

$stmt = $pdo->prepare($sqlBal);
$stmt->execute($args);
$bal = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
  'total_gastos' => 0,
  'total_ingresos' => 0,
  'neto_contable' => 0,
  'saldo' => 0
];
// Flashes
$flashes = flash_consume_all();

// Helpers para paginación manteniendo querystring
function build_query(array $extra = []): string {
    $qs = array_merge($_GET, $extra);
    unset($qs['p']); // lo controlamos con $extra
    return http_build_query($qs);
}

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gastos Simples AMC</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light dark">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="app-header">
  <div class="container">
    <h1>Gastos Simples AMC</h1>
    <nav aria-label="Acciones principales">
      <a class="btn" href="new.php" aria-label="Añadir gasto">Añadir gasto</a>
      <a class="btn" href="import.php" aria-label="Importar CSV">Importar CSV</a>

      <form class="inline-form" action="../back/controllers/export_csv.php" method="get" aria-label="Exportar CSV">
        <input type="hidden" name="month" value="<?= h($month) ?>">
        <input type="hidden" name="category" value="<?= h($category) ?>">
        <input type="hidden" name="q" value="<?= h($q) ?>">
        <button class="btn" type="submit" aria-label="Exportar CSV del filtro actual">Exportar CSV</button>
      </form>

      <span class="muted"><?= h($_SESSION['user_email'] ?? '') ?></span>
      <a class="btn" href="<?= h(app_url('/back/controllers/logout.php')) ?>" aria-label="Cerrar sesión">Salir</a>
    </nav>
  </div>
</header>

<?php if (!empty($flashes)): ?>
  <div class="container" aria-live="polite" aria-atomic="true">
    <?php foreach ($flashes as $f): ?>
      <div class="card" role="status">
        <strong><?= h(ucfirst((string)($f['type'] ?? 'info'))) ?>:</strong>
        <?= h((string)($f['message'] ?? '')) ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<main class="container">

  <section class="card" aria-labelledby="filtros-titulo">
    <h2 id="filtros-titulo">Filtros</h2>

    <form class="filters-grid" action="index.php" method="get" aria-describedby="filtros-ayuda">
      <div class="field">
        <label for="month">Mes</label>
        <input type="month" id="month" name="month" value="<?= h($month) ?>" placeholder="Ej.: 2026-03">
        <small class="help">&nbsp;</small>
      </div>

      <div class="field">
        <label for="category">Categoría</label>
        <input type="text" id="category" name="category" value="<?= h($category) ?>" placeholder="Ej.: Alimentación">
        <small class="help">&nbsp;</small>
      </div>

      <div class="field">
        <label for="q">Buscar en concepto</label>
        <input type="search" id="q" name="q" value="<?= h($q) ?>" placeholder="p. ej. café, metro...">
        <small class="help">&nbsp;</small>
      </div>

      <div class="field actions">
        <button class="btn primary" type="submit" aria-label="Aplicar filtros">Aplicar</button>
        <a class="btn" href="index.php" aria-label="Limpiar filtros">Limpiar</a>
        <small class="help">&nbsp;</small>
      </div>
    </form>

    <p id="filtros-ayuda" class="visually-hidden">
      Usa los filtros para acotar el listado por mes, categoría y texto.
    </p>
  </section>

<section class="dashboard-top">
  <div class="card" aria-labelledby="balance-titulo">
    <h2 id="balance-titulo">Balance mensual</h2>

    <div class="balance-row">
      <div class="metric">
        <span class="value" id="total-gastado"><?= eur((float)$bal['total_gastos']) ?></span>
        <span class="label">Total gastado</span>
      </div>

      <div class="metric">
        <span class="value" id="total-ingresos"><?= eur((float)$bal['total_ingresos']) ?></span>
        <span class="label">Total ingresos</span>
      </div>

        <div class="metric">
        <?php $saldo = (float)$bal['saldo']; ?>
        <span class="value <?= $saldo >= 0 ? 'neto-pos' : 'neto-neg' ?>">
            <?= eur($saldo) ?>
        </span>
        <span class="label">Saldo (ingresos - gastos)</span>
        <small class="help">
            Balance contable (gastos + ingresos): <?= eur((float)$bal['neto_contable']) ?>
        </small>
        </div>
    </div>

    <small class="muted">
      Filtro aplicado:
      <?= h($month !== '' ? $month : 'todos los meses') ?>
      <?= $category !== '' ? (' · categoría: ' . h($category)) : '' ?>
      <?= $q !== '' ? (' · búsqueda: ' . h($q)) : '' ?>
    </small>
  </div>

  <?php if ($isAdmin): ?>
    <div class="card admin-card" aria-labelledby="admin-titulo">
      <div class="admin-card__header">
        <h2 id="admin-titulo">Administración</h2>
        <span class="admin-badge" aria-label="Rol administrador">ADMIN</span>
      </div>

      <p class="muted">
        Gestiona usuarios, roles y revisa métricas globales del sistema.
      </p>

      <div class="admin-card__actions" role="group" aria-label="Acciones de administración">
        <a class="btn primary" href="<?= h(app_url('/front/admin/index.php')) ?>">Abrir panel</a>
        <a class="btn" href="<?= h(app_url('/front/admin/users.php')) ?>">Usuarios y roles</a>
      </div>

      <div class="admin-card__tips">
        <small class="help">
          Consejo: exporta CSV antes de acciones masivas o cambios de roles.
        </small>
      </div>
    </div>
  <?php endif; ?>
</section>

<section class="card chart-card" aria-labelledby="grafico-titulo">
  <div class="chart-card__header">
    <h2 id="grafico-titulo">Gastos del mes</h2>

    <div class="chart-header-right">
      <div class="chart-controls">
        <label for="chart_month" class="visually-hidden">Mes del gráfico</label>
        <input
          type="month"
          id="chart_month"
          name="chart_month"
          value="<?= h($chart_month) ?>"
          aria-label="Mes del gráfico"
        >
      </div>

      <div class="toggle-group" role="group" aria-label="Modo de gráfico">
        <button id="toggle-category" class="btn" type="button" aria-pressed="true">
          Por categoría
        </button>
        <button id="toggle-day" class="btn" type="button" aria-pressed="false">
          Por día
        </button>
      </div>
    </div>
  </div>

  <div id="chart-wrap" class="chart-wrap">
    <canvas id="chart"></canvas>
  </div>

  <p id="chart-error" class="muted" style="display:none;"></p>
</section>

  <section class="card" aria-labelledby="listado-titulo">
    <div class="list-header">
      <h2 id="listado-titulo">Listado de gastos</h2>

      <div class="pagination" aria-label="Paginación">
        <?php if ($totalPages > 1): ?>
          <?php
            $baseQuery = build_query();
            $prev = max(1, $page - 1);
            $next = min($totalPages, $page + 1);
          ?>
          <a class="btn" href="index.php?<?= h($baseQuery) ?>&p=<?= $prev ?>"
             aria-label="Página anterior" <?= $page <= 1 ? 'aria-disabled="true"' : '' ?>>←</a>
          <span class="muted">Página <?= $page ?> de <?= $totalPages ?></span>
          <a class="btn" href="index.php?<?= h($baseQuery) ?>&p=<?= $next ?>"
             aria-label="Página siguiente" <?= $page >= $totalPages ? 'aria-disabled="true"' : '' ?>>→</a>
        <?php else: ?>
          <span class="muted">Página 1 de 1</span>
        <?php endif; ?>
      </div>
    </div>

    <div class="table-wrapper">
      <table class="table" aria-describedby="tabla-ayuda">
        <thead>
          <tr>
            <th scope="col">Fecha</th>
            <th scope="col">Concepto</th>
            <th scope="col">Categoría</th>
            <th scope="col" class="num">Importe (€)</th>
            <th scope="col" class="actions-col">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="5" class="muted">No hay resultados para los filtros actuales.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= h((string)$r['date']) ?></td>
              <td><?= h((string)$r['concept']) ?></td>
              <td><?= h((string)$r['category']) ?></td>
              <?php
                $raw = (float)$r['amount'];
                $isIncome = $raw < 0;
                $display = eur(abs($raw)); // ✅ mostramos siempre positivo
                ?>
                <td class="num">
                <span class="<?= $isIncome ? 'amount income' : 'amount expense' ?>">
                    <?= $display ?>
                </span>
                <?php if ($isIncome): ?>
                    <span class="badge income" aria-label="Movimiento de tipo ingreso">Ingreso</span>
                <?php endif; ?>
                </td>
              <td>
                <a class="btn" href="edit.php?id=<?= (int)$r['id'] ?>" aria-label="Editar gasto">Editar</a>

                <form class="inline-form" action="../back/controllers/expenses_delete.php" method="post" aria-label="Borrar gasto">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn danger" type="submit" aria-label="Borrar gasto">Borrar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
      <p id="tabla-ayuda" class="visually-hidden">Tabla de gastos con acciones de editar y borrar.</p>
        <div class="field actions" style="justify-content: flex-end;">
        <button id="open-delete-all" class="btn danger" type="button">
        Borrar todos mis gastos
        </button>
        </div>

        <div id="delete-all-modal" class="modal" aria-hidden="true" role="dialog" aria-labelledby="delete-all-title" aria-modal="true">
        <div class="modal__backdrop" data-close-modal></div>

        <div class="modal__panel">
            <h3 id="delete-all-title" class="modal__title">Confirmar borrado total</h3>

            <p class="muted">
            Esta acción eliminará <strong>todos tus gastos</strong> asociados a tu cuenta.
            <br>Es <strong>irreversible</strong>. Te recomendamos exportar CSV antes.
            </p>

            <div class="alert warning" role="alert">
            <strong>Escribe exactamente: BORRAR TODO</strong>
            <p class="help">Solo así se habilitará el botón de confirmación.</p>
            </div>

            <form action="<?= h(app_url('/back/controllers/expenses_delete_all.php')) ?>" method="post" class="card" style="box-shadow:none; margin:0;">
            <?= csrf_field() ?>
            <div class="field">
                <label for="confirm_text">Confirmación</label>
                <input
                type="text"
                id="confirm_text"
                name="confirm_text"
                autocomplete="off"
                placeholder="BORRAR TODO"
                aria-describedby="confirm-help"
                required
                >
                <small id="confirm-help" class="help">Debe coincidir exactamente (mayúsculas y espacio).</small>
            </div>

            <div class="field actions" style="justify-content: flex-end;">
                <button type="button" class="btn" data-close-modal>Cancelar</button>
                <button id="confirm-delete-all" type="submit" class="btn danger" disabled aria-disabled="true">
                Sí, borrar definitivamente
                </button>
            </div>
            </form>
        </div>
        </div>
    </div>
  </section>

</main>

<footer class="app-footer">
  <div class="container">
    <small>© <?= date('Y'); ?> Gastos Simples AMC</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script src="../assets/js/main.js"></script>

</body>
</html>