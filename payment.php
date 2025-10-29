<?php
// payment.php - show QR + countdown + polling
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$token = trim((string)($_GET['token'] ?? ''));
if (!$token) { echo "token missing"; exit; }
if (!defined('PAYMENT_SECRET') || PAYMENT_SECRET === '') {
    echo "server misconfigured (no PAYMENT_SECRET)"; exit;
}
$sig = hash_hmac('sha256', $token, PAYMENT_SECRET);

// fetch order (to show amount, expiry)
$stmt = $pdo->prepare("SELECT id, total, payment_status, payment_expires FROM orders WHERE payment_token = ? LIMIT 1");
$stmt->execute([$token]);
$order = $stmt->fetch();
if (!$order) { echo "order not found"; exit; }

// decide QR image path
$qr_token_png = __DIR__ . "/assets/qr/{$token}.png";
$qr_token_jpg = __DIR__ . "/assets/qr/{$token}.jpg";
$qr_default_jpg = __DIR__ . "/assets/qr/default_qr.jpg";
$qr_default_png = __DIR__ . "/assets/qr/default_qr.png";

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://")
    . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

$payment_url = $base . "/pay_confirm.php?token=" . rawurlencode($token) . "&sig=" . rawurlencode($sig);

if (file_exists($qr_token_png)) {
    $qr_url = 'assets/qr/' . rawurlencode($token) . '.png';
} elseif (file_exists($qr_token_jpg)) {
    $qr_url = 'assets/qr/' . rawurlencode($token) . '.jpg';
} elseif (file_exists($qr_default_jpg)) {
    $qr_url = 'assets/qr/default_qr.jpg';
} elseif (file_exists($qr_default_png)) {
    $qr_url = 'assets/qr/default_qr.png';
} else {
    // fallback Google Chart
    $qr_url = "https://chart.googleapis.com/chart?cht=qr&chs=400x400&chl=" . rawurlencode($payment_url) . "&chld=M|0";
}

$expires_ts = $order['payment_expires'] ? strtotime($order['payment_expires']) : 0;
$remaining = max(0, $expires_ts - time());
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pembayaran Order #<?php echo (int)$order['id']; ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f4f6fa;color:#111827} .panel{max-width:980px;margin:36px auto;padding:22px;background:#fff;border-radius:12px;box-shadow:0 10px 30px rgba(18,40,80,0.06)}
.qr-img{width:320px;height:320px;object-fit:contain;border:1px solid #eee;border-radius:8px;background:#fff}
.paid{color:#16a34a;font-weight:700} .expired{color:#c02627;font-weight:700}
</style>
</head>
<body>
<div class="container">
  <div class="panel">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4>Pembayaran Order #<?php echo (int)$order['id']; ?></h4>
      <a href="index.php" class="btn btn-outline-secondary">Kembali ke Menu</a>
    </div>

    <div class="row g-4">
      <div class="col-md-5 text-center">
        <img src="<?php echo htmlspecialchars($qr_url); ?>" class="qr-img" alt="QR Pembayaran">
        <div class="mt-3 small text-muted">Bayar: <strong>Rp <?php echo number_format($order['total'],0,',','.'); ?></strong></div>
      </div>

      <div class="col-md-7">
        <p>Scan QR dengan aplikasi bank / e-wallet. Halaman ini akan mendeteksi otomatis bila sudah dibayar.</p>
        <div class="mb-3">Status: <span id="statusLabel"><?php echo $order['payment_status']==='PAID' ? '<span class="paid">Terbayar</span>' : '<span class="text-muted">Menunggu</span>'; ?></span></div>
        <div class="mb-3">Waktu tersisa: <span id="countdown"><?php echo gmdate('i:s', $remaining); ?></span></div>
        <div id="notif"></div>
        <div class="mt-3">
          <!-- optional test button -->
          <form method="post" action="pay_confirm.php" onsubmit="return confirm('Tandai terbayar (test)?');">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="hidden" name="sig" value="<?php echo htmlspecialchars($sig); ?>">
            <button class="btn btn-success">Saya sudah bayar (Test)</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let remaining = <?php echo (int)$remaining; ?>;
const token = '<?php echo addslashes($token); ?>';
const sig   = '<?php echo addslashes($sig); ?>';
const statusLabel = document.getElementById('statusLabel');
const countdownEl = document.getElementById('countdown');
const notif = document.getElementById('notif');

function formatTime(s){ if(s<0) s=0; const mm = String(Math.floor(s/60)).padStart(2,'0'); const ss = String(s%60).padStart(2,'0'); return mm+':'+ss; }

async function poll(){
  try {
    const res = await fetch('order_status_ajax.php?token='+encodeURIComponent(token)+'&sig='+encodeURIComponent(sig));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const data = await res.json();
    if(data.error){ console.error('status error', data.error); return; }
    if(data.status === 'PAID'){
      statusLabel.innerHTML = '<span class="paid">Terbayar</span>';
      notif.innerHTML = '<div class="alert alert-success">Pembayaran diterima. Terima kasih!</div>';
      clearInterval(poller); clearInterval(countdownTimer);
    } else if(data.status === 'EXPIRED'){
      statusLabel.innerHTML = '<span class="expired">Kadaluarsa</span>';
      notif.innerHTML = '<div class="alert alert-danger">Waktu pembayaran habis.</div>';
      clearInterval(poller); clearInterval(countdownTimer);
      countdownEl.textContent = '00:00';
    } else {
      remaining = data.remaining;
      countdownEl.textContent = formatTime(remaining);
    }
  } catch (err) {
    console.error('poll error', err);
  }
}

const poller = setInterval(poll, 3000);
const countdownTimer = setInterval(()=>{ if(remaining>0){ remaining--; countdownEl.textContent = formatTime(remaining); } else { countdownEl.textContent = '00:00'; } }, 1000);

// kick first run
poll();
</script>
</body>
</html>
