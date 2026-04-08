<?php
declare(strict_types=1);

require_once __DIR__ . '/../inc/conexion_bd.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

$pdo     = get_pdo();
$isAdmin = current_user_is_admin();
$userId  = (int) (current_user_id() ?? 0);

// Filtros recibidos por GET (read-only -> no CSRF)
$month    = trim((string)($_GET['month'] ?? ''));     // YYYY-MM
$category = trim((string)($_GET['category'] ?? ''));
$q        = trim((string)($_GET['q'] ?? ''));

// NO forzar mes si está vacío: exporta lo visible
$filename = ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month))
  ? ('gastos_' . preg_replace('/[^0-9\\-]/', '', $month) . '.csv')
  : 'gastos_export.csv';

$w = [];
$args = [];

// Export SIEMPRE personal, incluso para admin
$w[] = 'user_id = :uid';
$args[':uid'] = $userId;


// Mes -> rango [from, to]
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $dt = DateTime::createFromFormat('Y-m-d', $month . '-01');
    if ($dt) {
        $from = $dt->format('Y-m-d');
        $to   = $dt->modify('last day of this month')->format('Y-m-d');
        $w[] = '`date` BETWEEN :from AND :to';
        $args[':from'] = $from;
        $args[':to']   = $to;
    }
}

// Categoría exacta
if ($category !== '') {
    $w[] = 'category = :category';
    $args[':category'] = $category;
}

// Búsqueda por concepto (LIKE)
if ($q !== '') {
    $w[] = 'concept LIKE :q';
    $args[':q'] = '%' . $q . '%';
}

$where = $w ? ('WHERE ' . implode(' AND ', $w)) : '';

$sql = "SELECT `date`, concept, category, amount
        FROM expenses
        {$where}
        ORDER BY `date` ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($args);

// Nombre de archivo amigable
$filename = 'gastos_' . preg_replace('/[^0-9\-]/', '', $month) . '.csv';

// Headers de descarga
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Opcional: BOM UTF-8 para Excel (descomentar la línea siguiente)
// echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
if ($out === false) {
    http_response_code(500);
    exit('No se pudo generar el CSV.');
}

// Cabecera obligatoria
fputcsv($out, ['date', 'concept', 'category', 'amount'], ',', '"', "\\");

// Filas
while ($row = $stmt->fetch()) {
    // amount con punto decimal, 2 decimales (CSV exige .)
    $amount = number_format((float)$row['amount'], 2, '.', '');

    fputcsv($out, [
        $row['date'],
        $row['concept'],
        $row['category'],
        $amount,
    ], ',', '"', "\\");
}

fclose($out);
exit;
