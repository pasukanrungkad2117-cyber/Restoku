<?php
// admin_dashboard.php (rapi + aksi sejajar + tombol Kelola Slider + ekspor PDF)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// fallback flash
if(!function_exists('flash_set')){
    function flash_set($k,$m){ $_SESSION['__flash__'][$k]=$m; }
    function flash_get($k){ $v = $_SESSION['__flash__'][$k] ?? null; if(isset($_SESSION['__flash__'][$k])) unset($_SESSION['__flash__'][$k]); return $v; }
}

if (empty($_SESSION['admin_logged_in'])) { header('Location: admin_login.php'); exit; }
$csrf = function_exists('generate_csrf_token') ? generate_csrf_token() : '';

// Handle mark_paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && (!function_exists('verify_csrf_token') || verify_csrf_token($_POST['csrf'] ?? ''))) {
    if ($_POST['action'] === 'mark_paid' && !empty($_POST['order_id'])) {
        $oid = (int)$_POST['order_id'];
        // tidak ada kolom paid_at → hapus set paid_at
        $upd = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_used = 1 WHERE id = ? AND payment_status != 'PAID'");
        $upd->execute([$oid]);
        flash_set('success', $upd->rowCount() ? "Order #{$oid} ditandai TERBAYAR." : "Order #{$oid} gagal ditandai (mungkin sudah dibayar).");
        header('Location: admin_dashboard.php'); exit;
    }
}

// SUMMARY
$summary = $pdo->query("
    SELECT 
      COUNT(*) AS total_orders,
      SUM(CASE WHEN payment_status='PAID' THEN 1 ELSE 0 END) AS total_paid_count,
      SUM(CASE WHEN payment_status!='PAID' THEN 1 ELSE 0 END) AS total_unpaid_count,
      SUM(CASE WHEN payment_status='PAID' THEN total ELSE 0 END) AS revenue_total,
      SUM(CASE WHEN payment_status='PAID' AND DATE(created_at)=CURDATE() THEN total ELSE 0 END) AS revenue_today
    FROM orders
")->fetch();

// ORDERS — taruh yang belum terbayar di atas
$orders = $pdo->query("
  SELECT id, table_no, total, payment_status, created_at
  FROM orders
  ORDER BY (payment_status='PAID') ASC, created_at DESC
  LIMIT 150
")->fetchAll();

// REPORT RANGE
$start = !empty($_GET['start']) ? $_GET['start'] : date('Y-01-01');
$end   = !empty($_GET['end']) ? $_GET['end'] : date('Y-m-d');
try { $start_dt = date('Y-m-d', strtotime($start)); } catch(Throwable $e){ $start_dt = date('Y-01-01'); }
try { $end_dt = date('Y-m-d', strtotime($end)); } catch(Throwable $e){ $end_dt = date('Y-m-d'); }
if(strtotime($start_dt) > strtotime($end_dt)) { $tmp=$start_dt; $start_dt=$end_dt; $end_dt=$tmp; }

$stmt = $pdo->prepare("SELECT COUNT(*) AS orders_count, SUM(CASE WHEN payment_status='PAID' THEN total ELSE 0 END) AS revenue_paid, SUM(CASE WHEN payment_status!='PAID' THEN total ELSE 0 END) AS revenue_unpaid FROM orders WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_dt, $end_dt]);
$period_summary = $stmt->fetch();

$stmt2 = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(CASE WHEN payment_status='PAID' THEN total ELSE 0 END) AS revenue FROM orders WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY ym ORDER BY ym ASC");
$stmt2->execute([$start_dt, $end_dt]);
$monthly = $stmt2->fetchAll();

$labels = []; $data = [];
try {
    $current = new DateTime((new DateTime($start_dt))->format('Y-m-01'));
    $end_month = new DateTime((new DateTime($end_dt))->format('Y-m-01'));
    $map = []; foreach($monthly as $m) $map[$m['ym']] = (float)$m['revenue'];
    while($current <= $end_month){
        $k = $current->format('Y-m');
        $labels[] = $k;
        $data[] = $map[$k] ?? 0;
        $current->modify('+1 month');
    }
} catch(Throwable $e){ $labels = [date('Y-m')]; $data = [0]; }

function money($v){ return 'Rp ' . number_format($v ?? 0,0,',','.'); }
$flash_success = flash_get('success'); $flash_error = flash_get('error');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Resto</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{--bg:#f4f6fa;--muted:#6b7280;--card:#fff}
    body{background:var(--bg);color:#111827;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial}
    .topbar{background:#0f1724;color:#fff;padding:14px 0;box-shadow:0 2px 6px rgba(15,23,36,0.08)}
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:1.25rem}
    .stat-card{background:var(--card);border-radius:12px;padding:18px;box-shadow:0 8px 24px rgba(18,40,80,0.04);min-height:110px;display:flex;flex-direction:column;justify-content:space-between}
    .stat-meta{color:var(--muted);font-size:0.95rem}
    .stat-value{font-size:1.9rem;font-weight:700;margin-top:6px}
    .stat-sub{color:var(--muted);font-size:0.9rem;margin-top:6px}
    .card-round{border-radius:12px}
    .badge-paid{background:#16a34a;color:#fff;padding:6px 10px;border-radius:999px;font-size:0.85rem}
    .badge-unpaid{background:#f59e0b;color:#111;padding:6px 10px;border-radius:999px;font-size:0.85rem}
    .badge-expired{background:#ef4444;color:#fff;padding:6px 10px;border-radius:999px;font-size:0.85rem}
    table.table td, table.table th{vertical-align:middle}
    /* tombol aksi */
    .action-buttons { display:flex; gap:8px; align-items:center; justify-content:center; flex-wrap:wrap; }
    .action-buttons .btn { min-width:100px; height:34px; display:inline-flex; align-items:center; justify-content:center; }
  </style>
</head>
<body>
  <nav class="topbar">
    <div class="container d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center">
        <div class="me-3" style="font-weight:700;font-size:1.1rem">Admin - Resto</div>
        <div class="text-white-50 small">Dashboard & Laporan Keuangan</div>
      </div>
      <div>
        <a href="admin_menu.php" class="btn btn-outline-light btn-sm me-2">Kelola Menu</a>
        <a href="admin_slider.php" class="btn btn-outline-light btn-sm me-2">Kelola Slider</a>
        <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container my-4">
    <?php if($flash_success): ?><div class="alert alert-success"><?php echo htmlspecialchars($flash_success); ?></div><?php endif;?>
    <?php if($flash_error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flash_error); ?></div><?php endif;?>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-meta">Total Order</div><div class="stat-value"><?php echo (int)$summary['total_orders']; ?></div><div class="stat-sub">&nbsp;</div></div>
      <div class="stat-card"><div class="stat-meta">Belum terbayar</div><div class="stat-value"><?php echo (int)$summary['total_unpaid_count']; ?></div><div class="stat-sub"><?php echo money($period_summary['revenue_unpaid'] ?? 0); ?> (periode)</div></div>
      <div class="stat-card"><div class="stat-meta">Terbayar</div><div class="stat-value"><?php echo (int)$summary['total_paid_count']; ?></div><div class="stat-sub"><?php echo money($summary['revenue_today']); ?> hari ini</div></div>
      <div class="stat-card"><div class="stat-meta">Pendapatan Total</div><div class="stat-value"><?php echo money($summary['revenue_total']); ?></div><div class="stat-sub">Periode: <?php echo htmlspecialchars($start_dt); ?> → <?php echo htmlspecialchars($end_dt); ?></div></div>
    </div>

    <div class="card card-round mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Laporan Keuangan</h5>
          <form class="d-flex align-items-center" method="get">
            <label class="me-2 text-muted small">Dari</label>
            <input type="date" name="start" class="form-control form-control-sm me-2" value="<?php echo htmlspecialchars($start_dt); ?>">
            <label class="me-2 text-muted small">Sampai</label>
            <input type="date" name="end" class="form-control form-control-sm me-2" value="<?php echo htmlspecialchars($end_dt); ?>">
            <button class="btn btn-primary btn-sm me-2">Filter</button>
            <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm">Reset</a>
          </form>
        </div>

        <div class="row g-3">
          <div class="col-lg-4">
            <div class="p-3 bg-white rounded">
              <div class="text-muted small">Pesanan (periode)</div>
              <div class="h3 fw-bold"><?php echo (int)$period_summary['orders_count']; ?></div>
              <div class="text-muted mt-2">Pendapatan terbayar: <strong><?php echo money($period_summary['revenue_paid']); ?></strong></div>
              <div class="text-muted mt-1">Belum terbayar: <strong><?php echo money($period_summary['revenue_unpaid']); ?></strong></div>
              <div class="mt-3 d-flex gap-2">
                <a id="exportPdf" class="btn btn-outline-primary btn-sm" href="admin_export_finance.php?start=<?php echo rawurlencode($start_dt); ?>&end=<?php echo rawurlencode($end_dt); ?>"><i class="bi bi-file-earmark-pdf"></i> Ekspor PDF</a>
                <a id="exportCsv" class="btn btn-outline-secondary btn-sm" href="admin_export_finance.php?start=<?php echo rawurlencode($start_dt); ?>&end=<?php echo rawurlencode($end_dt); ?>&format=csv"><i class="bi bi-download"></i> CSV</a>
              </div>
            </div>
          </div>

          <div class="col-lg-8">
            <canvas id="revenueChart" height="120"></canvas>
          </div>
        </div>
      </div>
    </div>

    <div class="card card-round">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0">Daftar Order Terbaru</h5>
          <small class="text-muted">Menampilkan hingga 150 entri terbaru</small>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:70px">#</th>
                <th>Meja</th>
                <th style="width:140px">Jumlah Item</th>
                <th style="width:140px">Total</th>
                <th style="width:140px">Status</th>
                <th style="width:200px">Dibuat</th>
                <th style="width:240px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($orders as $o):
                $itemsCount = 0;
                try {
                    $s = $pdo->prepare("SELECT SUM(qty) AS items FROM order_items WHERE order_id = ? LIMIT 1");
                    $s->execute([$o['id']]);
                    $sr = $s->fetch();
                    $itemsCount = $sr ? (int)$sr['items'] : 0;
                } catch(Throwable $e){ $itemsCount = 0; }
                $status = $o['payment_status'];
              ?>
                <tr>
                  <td><?php echo (int)$o['id']; ?></td>
                  <td><?php echo htmlspecialchars($o['table_no']); ?></td>
                  <td><?php echo $itemsCount; ?></td>
                  <td><?php echo money($o['total']); ?></td>
                  <td>
                    <?php if($status === 'PAID'): ?><span class="badge-paid">Terbayar</span>
                    <?php elseif($status === 'EXPIRED'): ?><span class="badge-expired">Kadaluarsa</span>
                    <?php else: ?><span class="badge-unpaid">Belum terbayar</span><?php endif; ?>
                  </td>
                  <td class="mono"><?php echo htmlspecialchars($o['created_at']); ?></td>
                  <td class="text-center">
                    <div class="action-buttons">
                      <a href="admin_order_view.php?id=<?php echo (int)$o['id']; ?>" class="btn btn-outline-secondary btn-sm">Lihat</a>
                      <?php if($status !== 'PAID'): ?>
                        <form method="post" class="m-0" onsubmit="return confirm('Tandai order #<?php echo (int)$o['id']; ?> sebagai Terbayar?')">
                          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
                          <input type="hidden" name="action" value="mark_paid">
                          <input type="hidden" name="order_id" value="<?php echo (int)$o['id']; ?>">
                          <button class="btn btn-success btn-sm">Terbayar</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>

  </div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
  const labels = <?php echo json_encode($labels); ?>;
  const dataSeries = <?php echo json_encode($data); ?>;
  const ctx = document.getElementById('revenueChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: { labels: labels, datasets: [{ label: 'Pendapatan (Rp)', data: dataSeries, fill:true, tension:0.25, borderColor:'#0d6efd', backgroundColor:'rgba(13,110,253,0.08)' }]},
    options: { scales: { y: { ticks: { callback: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } } }, plugins: { tooltip:{ callbacks:{ label: ctx => 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.parsed.y) } } } }
  });
</script>
</body>
</html>
