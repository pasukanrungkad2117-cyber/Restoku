<?php
// order_status.php
require_once 'config.php';
require_once 'functions.php';

$token = $_GET['token'] ?? '';
$stmt = $pdo->prepare("SELECT id, payment_status FROM orders WHERE payment_token = ?");
$stmt->execute([$token]);
$order = $stmt->fetch();
if(!$order) die('Order tidak ditemukan');
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Status Order</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-5">
  <h3>Status Order #<?php echo (int)$order['id']; ?></h3>
  <div id="status" class="p-4 border rounded">
    Status: <strong><?php echo e($order['payment_status']); ?></strong>
  </div>
  <p class="mt-3">Halaman akan memeriksa status pembayaran setiap 5 detik.</p>
</div>

<script>
const token = '<?php echo e($token); ?>';
async function check(){
  try{
    let res = await fetch('order_status_ajax.php?token='+encodeURIComponent(token));
    let j = await res.json();
    document.getElementById('status').innerHTML = 'Status: <strong>' + j.payment_status + '</strong>';
    if(j.payment_status !== 'PENDING'){
      clearInterval(i);
      alert('Pembayaran terdeteksi: ' + j.payment_status);
    }
  }catch(e){ console.error(e); }
}
let i = setInterval(check, 5000);
</script>
</body>
</html>
