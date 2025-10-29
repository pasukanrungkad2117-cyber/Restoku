<?php
// admin_order_view.php — Detail pesanan admin (Bootstrap 5)
// Menggunakan PDO $pdo dari config.php

require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// --- (opsional) aktifkan kalau punya halaman login admin ---
// if (empty($_SESSION['admin_logged_in'])) { header('Location: admin_login.php'); exit; }

// helper rupiah
function rupiah($n){ return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) { http_response_code(400); exit('ID order tidak valid'); }

// -------- Header Order --------
$sqlOrder = "SELECT id, table_no, total, payment_token, payment_status, customer_email,
                    customer_note, created_at, payment_expires, payment_used
             FROM orders WHERE id = ?";
$st = $pdo->prepare($sqlOrder);
$st->execute([$orderId]);
$order = $st->fetch(PDO::FETCH_ASSOC);
if (!$order) { http_response_code(404); exit('Order tidak ditemukan'); }

$isPaid = (strtoupper((string)$order['payment_status']) === 'PAID') || ((int)$order['payment_used'] === 1);

// -------- Tandai Terbayar --------
if (!$isPaid && isset($_POST['mark_paid'])) {
    $upd = $pdo->prepare("UPDATE orders SET payment_status='PAID', payment_used=1 WHERE id=?");
    $upd->execute([$orderId]);
    header("Location: admin_order_view.php?id=".$orderId);
    exit;
}

// -------- Detail Item --------
$sqlItems = "SELECT 
                oi.id AS item_id,
                oi.qty,
                oi.price,
                mi.name AS name
            FROM order_items oi
            LEFT JOIN menu_items mi ON mi.id = oi.menu_id
            WHERE oi.order_id = ?
            ORDER BY oi.id ASC";
$sti = $pdo->prepare($sqlItems);
$sti->execute([$orderId]);
$items = $sti->fetchAll(PDO::FETCH_ASSOC);

// Hitung total (pakai orders.total jika ada, jika tidak hitung dari detail)
$computedTotal = 0;
foreach ($items as $it) { $computedTotal += ((float)$it['price']) * ((int)$it['qty']); }
$grandTotal = ($order['total'] !== null && (int)$order['total'] > 0) ? (int)$order['total'] : (int)$computedTotal;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Detail Order #<?= e($order['id']); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f6f7fb; }
    .card { border:0; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
    .table td, .table th { vertical-align: middle; }
    .text-break-all { word-break: break-all; }
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Detail Order #<?= e($order['id']); ?></h4>
    <div class="d-flex gap-2">
      <?php if (!$isPaid): ?>
        <form method="post" onsubmit="return confirm('Tandai order ini sebagai TERBAYAR?');">
          <button class="btn btn-success" name="mark_paid">Tandai Terbayar</button>
        </form>
      <?php endif; ?>
      <a class="btn btn-secondary" href="admin_dashboard.php">← Kembali</a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body row g-3">
      <div class="col-sm-6 col-md-3">
        <div class="small text-muted">Meja</div>
        <div class="fw-semibold fs-5"><?= e($order['table_no']); ?></div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="small text-muted">Status</div>
        <span class="badge <?= $isPaid ? 'bg-success' : 'bg-warning text-dark'; ?>">
          <?= $isPaid ? 'Terbayar' : 'Belum Terbayar'; ?>
        </span>
        <?php if (!$isPaid && !empty($order['payment_expires'])): ?>
          <div class="small text-muted mt-1">Kadaluarsa: <?= e($order['payment_expires']); ?></div>
        <?php endif; ?>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="small text-muted">Dibuat</div>
        <div class="fw-semibold"><?= e($order['created_at']); ?></div>
      </div>
      <div class="col-sm-6 col-md-3">
        <div class="small text-muted">Total</div>
        <div class="fw-semibold fs-5"><?= rupiah($grandTotal); ?></div>
      </div>

      <?php if (!empty($order['customer_email'])): ?>
      <div class="col-md-6">
        <div class="small text-muted">Email Pelanggan</div>
        <div class="fw-semibold"><?= e($order['customer_email']); ?></div>
      </div>
      <?php endif; ?>

      <?php if (!empty($order['payment_token'])): ?>
      <div class="col-md-6">
        <div class="small text-muted">Payment Token</div>
        <code class="text-break-all d-inline-block"><?= e($order['payment_token']); ?></code>
      </div>
      <?php endif; ?>

      <?php if (!empty($order['customer_note'])): ?>
      <div class="col-12">
        <div class="small text-muted">Catatan</div>
        <div><?= nl2br(e($order['customer_note'])); ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header bg-white">
      <strong>Item Pesanan</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:70px">#</th>
              <th>Nama Menu</th>
              <th class="text-center" style="width:120px">Qty</th>
              <th class="text-end" style="width:160px">Harga</th>
              <th class="text-end" style="width:180px">Subtotal</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$items): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada item.</td></tr>
          <?php else: $no=1; foreach ($items as $it):
                  $sub = ((float)$it['price']) * ((int)$it['qty']); ?>
            <tr>
              <td><?= $no++; ?></td>
              <td><?= e($it['name'] ?? '—'); ?></td>
              <td class="text-center"><?= (int)$it['qty']; ?></td>
              <td class="text-end"><?= rupiah($it['price']); ?></td>
              <td class="text-end"><?= rupiah($sub); ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold">
              <td colspan="4" class="text-end">Total</td>
              <td class="text-end"><?= rupiah($grandTotal); ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>
</body>
</html>
