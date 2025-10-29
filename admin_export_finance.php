<?php
// admin_export_finance.php
// Ekspor laporan ke PDF (Dompdf) atau CSV (fallback).
// - Pastikan config.php & functions.php tersedia
// - Jika ingin PDF, install dompdf via composer (composer require dompdf/dompdf)

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: admin_login.php'); exit; }

$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-01-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-d');
$format = !empty($_GET['format']) ? strtolower($_GET['format']) : 'pdf';

// normalize dates
$start = date('Y-m-d', strtotime($start));
$end   = date('Y-m-d', strtotime($end));

// fetch orders (no GROUP BY problems) + items via subquery
$sql = "SELECT o.id, o.table_no, o.total, o.payment_status, o.created_at, o.customer_email,
       (SELECT GROUP_CONCAT(CONCAT(oi.qty,'x ',mi.name) SEPARATOR ', ')
          FROM order_items oi
          JOIN menu_items mi ON mi.id = oi.menu_id
         WHERE oi.order_id = o.id
       ) AS items
    FROM orders o
    WHERE DATE(o.created_at) BETWEEN ? AND ?
    ORDER BY o.created_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start, $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If user requested CSV explicitly -> send CSV
if ($format === 'csv') {
    $filename = "report_{$start}_to_{$end}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    $out = fopen('php://output', 'w');
    // header
    fputcsv($out, ['ID','Meja','Items','Total','Status','Dibuat','Email']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'],
            $r['table_no'],
            $r['items'] ?? '',
            number_format($r['total'],0,',','.'),
            $r['payment_status'],
            $r['created_at'],
            $r['customer_email'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Try PDF using Dompdf if available
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
    try {
        // build HTML
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            body{font-family:Arial,Helvetica,sans-serif;font-size:12px}
            table{width:100%;border-collapse:collapse}
            th,td{padding:8px;border:1px solid #ddd}
            th{background:#f6f6f6}
            h2{margin-bottom:8px}
            </style></head><body>';
        $html .= '<h2>Laporan Orders: ' . htmlspecialchars($start) . ' — ' . htmlspecialchars($end) . '</h2>';
        $html .= '<table><thead><tr><th style="width:60px">ID</th><th>Meja</th><th>Items</th><th style="width:120px">Total</th><th style="width:120px">Status</th><th style="width:160px">Dibuat</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($r['id']) . '</td>';
            $html .= '<td>' . htmlspecialchars($r['table_no']) . '</td>';
            $html .= '<td>' . htmlspecialchars($r['items'] ?? '') . '</td>';
            $html .= '<td>Rp ' . number_format($r['total'],0,',','.') . '</td>';
            $html .= '<td>' . htmlspecialchars($r['payment_status']) . '</td>';
            $html .= '<td>' . htmlspecialchars($r['created_at']) . '</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';

        // use Dompdf
        $options = new Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        // Stream PDF to browser and force download
        $filename = "report_{$start}_to_{$end}.pdf";

        // Make sure no previous output is sent
        if (ob_get_length()) ob_end_clean();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        echo $dompdf->stream($filename, ['Attachment' => 0]); // Attachment=0 returns output, but we echo it; alternative: use stream with Attachment=>true
        // Note: Dompdf::stream already sends output; some environments prefer to capture output buffer.
        exit;
    } catch (Throwable $e) {
        // fallback to CSV if PDF generation failed
        error_log("Dompdf error: " . $e->getMessage());
        // continue to CSV fallback below
    }
}

// Fallback: CSV
$filename = "report_{$start}_to_{$end}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
$out = fopen('php://output','w');
fputcsv($out,['ID','Meja','Items','Total','Status','Dibuat','Email']);
foreach($rows as $r){
    fputcsv($out, [
        $r['id'],
        $r['table_no'],
        $r['items'] ?? '',
        number_format($r['total'],0,',','.'),
        $r['payment_status'],
        $r['created_at'],
        $r['customer_email'] ?? ''
    ]);
}
fclose($out);
exit;
