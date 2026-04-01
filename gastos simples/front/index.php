<?php
declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/auth.php';
require_once __DIR__ . '/../back/inc/csrf.php';


// =====================================================
// Bootstrap temporal (solo desarrollo) -> eliminar con login.php
if (empty($_SESSION['user_id'])) {
    $pdo = get_pdo();
    $row = $pdo->query("SELECT id, email FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1")->fetch();
    if ($row) {
        $_SESSION['user_id']    = (int)$row['id'];
        $_SESSION['user_email'] = $row['email'];
        $_SESSION['user_role']  = 'admin';
    }
}
// =====================================================

$pdo = get_pdo();
$userId   = current_user_id();
$isAdmin  = current_user_is_admin();

// Lectura de filtros
$month    = trim((string)($_GET['month'] ?? ''));
$category = trim((string)($_GET['category'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));
$page     = max(1, (int)($_GET['p'] ?? 1));
$perPage  = 25;

// Construcción dinámica del WHERE
$w    = [];
$args = [];

// Multiusuario
if (!$isAdmin) {
    $w[] = 'user_id = :uid';
    $args[':uid'] = $userId ?? 0; // si no hay sesión, en práctica no traerá nada
}

// Mes (YYYY-MM) -> rango de fechas
$from = $to = null;
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    try {
        $dt = DateTime::createFromFormat('Y-m-d', $month . '-01');
        $dt->setTime(0, 0, 0);
        $from = $dt->format('Y-m-d');
        $to   = $dt->modify('last day of this month')->format('Y-m-d');
        $w[] = '`date` BETWEEN :from AND :to';
        $args[':from'] = $from;
        $args[':to']   = $to;
    } catch (Throwable $e) {
        // filtro inválido -> ignoramos
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
$sqlCount = "SELECT COUNT(*) AS c FROM expenses {$where}";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($args);
$totalRows = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Query de datos
$sql = "SELECT id, `date`, concept, category, amount
        FROM expenses
        {$where}
        ORDER BY `date` DESC, id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($args as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Balance (totales)
$sqlBal = "SELECT
              COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END),0) AS total_gastos,
              COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END),0) AS total_ingresos
           FROM expenses {$where}";
$stmt = $pdo->prepare($sqlBal);
$stmt->execute($args);
$bal = $stmt->fetch() ?: ['total_gastos' => 0, 'total_ingresos' => 0];

// Helper de formato
function eur(float $n): string { return '€ ' . number_format($n, 2, ',', '.'); }

?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Gastos simples</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <h1>Gastos Simples</h1>
        <nav aria-label="Acciones principales">
            <a class="btn" href="import.php" aria-label="Importar CSV">Importar CSV</a>

            <!-- Exportar: usar los filtros activos (GET); no modifica estado, no requiere CSRF -->
            <form class="inline-form" action="../back/controllers/export_csv.php" method="get" aria-label="Exportar CSV">
                <input type="hidden" name="month" value="<?=htmlspecialchars($month) ?>">
                <input type="hidden" name="category" value="<?=htmlspecialchars($category) ?>">
                <input type="hidden" name="q" value="<?=htmlspecialchars($q) ?>">
                <button class="btn" type="submit">Exportar CSV</button>
        </form>
    </nav>
</header>

            <main class="container">
                <!-- Filtros -->
                 <section class="card" aria-labelledby="filtros-titulo">
                    <h2 id="filtros-titulo">Filtros</h2>
                    <form class="filters-grid" action="index.php" method="get" aria-describedby="filtros-ayuda">
                        <div class="field">
                            <label for="month">Mes (YYYY-MM)</label>
                            <input type ="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>" aria-describedby="month-help">
                            <small id="month-help" class="help">Ej.: 2026-03</small>
                        </div>

                        <div class="field">
                            <label for="category">Categoría</label>
                            <input type="text" id="category" name="category" value="<?= htmlspecialchars($category) ?>" placeholder="Ej.: Alimentación">
                        </div>

                        <div class="field actions">
                            <button class="btn primary" type="submit" aria-label="Aplicar filtros">Aplicar</button>
                            <a class="btn" href="index.php" aria-label="Limpiar filtros">Limpiar</a>
                        </div>
                    </form>
                    <p id="filtros-ayuda" class="visually-hidden">Usa los filtros para acotar el listado por mes, categoría y texto.</p>
                </section>

                <!-- Balance -->
                <section class="grid-2">
                    <div class="card" aria-labelledby="balance-titulo">
                        <h2 id="balance-titulo">Balance mensual</h2>
                        <div class="balance-row">
                            <div class="metric">
                                <span class="value" id="total-gastado">€ 0,00</span>
                                <span class="label">Total gastado</span>
                            </div>
                            <div class="metric">
                                <span class="value" id="total-ingresos">€ 0,00</span>
                                <span class="label">Ingresos (si negativos)</span>
                            </div>
                    </div>
                    <small class="muted">Se actualizará al implementar la consulta en el siguiente paso.</small>
                    </div>
                    <!-- Gráfico -->
                    <div class="card" aria-labelledby="grafico-titulo">
                        <h2 id="grafico-titulo">Gastos del mes</h2>
                        <canvas id="chart" width="400" height="220" role="img" aria-label="Gráfico de gastos del mes seleccionado"></canvas>
                        <small class="muted">Integraré Chart.js en el paso 6.</small>
                    </div>
                </section>

                <!-- Listado -->
                <section class="card" aria-labelledby="listado-titulo">
                    <div class="list-header">
                        <h2 id="listado-titulo">Listado de gastos</h2>
                        <div class="pagination" aria-label="Paginación">
                            <span class="muted">Paginación pendiente</span>
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
                                <tr>
                                    <td colspan="5" class="muted">Sin datos todavía. Aparecerán aquí al implementar la consulta (paso 2).</td>
                                </tr>
                            </tbody>
                        </table>
                        <p id="tabla-ayuda" class="visually-hidden">Tabla de gastos con acciones de editar y borrar.</p>
                    </div>
                </section>
            </main>
            <footer class="app-footer">
                <small> (c) <?= date('Y'); ?> Gastos Simples</small>
            </footer>

<!-- Chart.js se cargará más adelante en el paso 6 -->

                                    