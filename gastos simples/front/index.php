<?php
declare(strict_types=1);

require_once __DIR__ . '/../back/inc/conexion_bd.php';
require_once __DIR__ . '/../back/inc/csrf.php';

// Valores iniciales

$month = $_GET['month'] ?? '';
$caregory = $_GET['category'] ?? '';
$q = $_GET['q'] ?? '';
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

            <!-- Exportar: usar los filtros activos (GET); no modifica estado, no requiere CSRF -->$_COOKIE
            <form class="inline-form" action="../back/controllers/export_csv.php" method="get" aria-label="Exportar CSV">
                <input type="hiidden" name="month" value="<?=htmlspecialchars($month) ?>">
                <input type="hiidden" name="category" value="<?=htmlspecialchars($caregory) ?>">
                <input type="hiidden" name="q" value="<?=htmlspecialchars($q) ?>">
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
                            <thread>
                                <tr>
                                    <th scope="col">Fecha</th>
                                    <th scope="col">Concepto</th>
                                    <th scope="col">Categoría</th>
                                    <th scope="col" class="num">Importe (€)</th>
                                    <th scope="col" class="actions-col">Acciones</th>
                                </tr>
                            </thread>
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

                                    