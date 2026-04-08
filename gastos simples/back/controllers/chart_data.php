<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (!current_user_id()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo     = get_pdo();
$isAdmin = current_user_is_admin();
$userId  = (int)(current_user_id() ?? 0);

$mode  = trim((string)($_GET['mode'] ?? 'category')); // category | day
$month = trim((string)($_GET['month'] ?? ''));        // YYYY-MM
$categoryFilter = trim((string)($_GET['category'] ?? '')); // solo en mode=day
$q = trim((string)($_GET['q'] ?? ''));

if (!in_array($mode, ['category', 'day'], true)) {
    $mode = 'category';
}

if ($month === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Selecciona un mes (YYYY-MM) para ver el gráfico.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    http_response_code(400);
    echo json_encode(['error' => 'month inválido. Usa YYYY-MM'], JSON_UNESCAPED_UNICODE);
    exit;
}

$dt = DateTime::createFromFormat('Y-m-d', $month . '-01');
if (!$dt) {
    http_response_code(400);
    echo json_encode(['error' => 'month inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$from = $dt->format('Y-m-d');
$to   = $dt->modify('last day of this month')->format('Y-m-d');

$w = [];
$args = [];

// Solo gastos (no ingresos) para el gráfico
$w[] = 'amount > 0';

// Mes
$w[] = '`date` BETWEEN :from AND :to';
$args[':from'] = $from;
$args[':to']   = $to;

// El gráfico SIEMPRE es personal
$w[] = 'user_id = :uid';
$args[':uid'] = $userId;


// Búsqueda por concepto (opcional)
if ($q !== '') {
    $w[] = 'concept LIKE :q';
    $args[':q'] = '%' . $q . '%';
}

// Solo en modo día aplicamos categoría
if ($mode === 'day' && $categoryFilter !== '') {
    $w[] = 'category = :category';
    $args[':category'] = $categoryFilter;
}

$where = 'WHERE ' . implode(' AND ', $w);

if ($mode === 'category') {
    $sql = "SELECT category AS label, SUM(amount) AS value
            FROM expenses
            {$where}
            GROUP BY category
            ORDER BY value DESC";
} else {
    $sql = "SELECT `date` AS label, SUM(amount) AS value
            FROM expenses
            {$where}
            GROUP BY `date`
            ORDER BY `date` ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($args);

$labels = [];
$values = [];

while ($row = $stmt->fetch()) {
    $labels[] = (string)$row['label'];
    $values[] = (float)$row['value'];
}

echo json_encode([
    'mode' => $mode,
    'month' => $month,
    'labels' => $labels,
    'values' => $values,
    'currency' => 'EUR'
], JSON_UNESCAPED_UNICODE);