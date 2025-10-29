<?php
// pay_confirm.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// load composer if available (for PHPMailer optional)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$token = trim((string)($_REQUEST['token'] ?? ''));
$sig   = trim((string)($_REQUEST['sig'] ?? ''));

if ($token === '' || $sig === '') {
    http_response_code(400);
    echo "token/sig missing";
    exit;
}
if (!defined('PAYMENT_SECRET') || PAYMENT_SECRET === '') {
    http_response_code(500);
    echo "server misconfigured (no PAYMENT_SECRET)";
    exit;
}

// verify signature
$calc = hash_hmac('sha256', $token, PAYMENT_SECRET);
if (!hash_equals($calc, $sig)) {
    http_response_code(403);
    echo "invalid signature";
    exit;
}

// fetch order
$stmt = $pdo->prepare("SELECT id, payment_status, payment_expires, payment_used, customer_email, total FROM orders WHERE payment_token = ? LIMIT 1");
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo "order not found";
    exit;
}

$orderId = (int)$order['id'];
$now = time();
$expires_ts = $order['payment_expires'] ? strtotime($order['payment_expires']) : 0;

// check expiry
if ($expires_ts && $now > $expires_ts) {
    // mark expired if not paid
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'EXPIRED' WHERE id = ? AND payment_status != 'PAID'");
    $upd->execute([$orderId]);
    echo "expired";
    exit;
}

// already paid or used?
if ((int)$order['payment_used'] === 1 || $order['payment_status'] === 'PAID') {
    echo "already paid";
    exit;
}

// Check if 'paid_at' column exists
$hasPaidAt = (bool)$pdo->query("SHOW COLUMNS FROM `orders` LIKE 'paid_at'")->fetch();

// Atomic update: mark PAID & used only if payment_used = 0
if ($hasPaidAt) {
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_used = 1, paid_at = NOW() WHERE id = ? AND payment_used = 0");
} else {
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'PAID', payment_used = 1 WHERE id = ? AND payment_used = 0");
}
$upd->execute([$orderId]);

if ($upd->rowCount() === 0) {
    echo "failed to update (maybe processed concurrently)";
    exit;
}

// Optional: send notification email to customer
$customer_email = filter_var($order['customer_email'] ?? '', FILTER_VALIDATE_EMAIL);
if ($customer_email) {
    // try PHPMailer if available (otherwise fallback to mail())
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            // configure SMTP here if you want, else PHPMailer may use mail()
            $mail->setFrom('no-reply@example.com', 'Resto');
            $mail->addAddress($customer_email);
            $mail->isHTML(true);
            $mail->Subject = "Pembayaran diterima — Order #{$orderId}";
            $mail->Body = "<p>Pembayaran untuk Order <strong>#{$orderId}</strong> telah diterima. Total: Rp " . number_format($order['total'],0,',','.') . ".</p>";
            $mail->send();
        } catch (Exception $e) {
            error_log('notify email error: '.$e->getMessage());
        }
    } else {
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        @mail($customer_email, "Pembayaran diterima — Order #{$orderId}", "<p>Pembayaran diterima. Order #{$orderId}</p>", $headers);
    }
}

// Return success — if called via AJAX expect text/json; if via browser show simple page
if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status'=>'OK','order_id'=>$orderId]);
    exit;
}

// fallback: simple HTML confirmation
?>
<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pembayaran Berhasil</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light">
<div class="container py-5">
  <div class="card p-4">
    <h3>Pembayaran Berhasil</h3>
    <p>Order #<?php echo (int)$orderId; ?> telah ditandai TERBAYAR.</p>
    <a href="admin_dashboard.php" class="btn btn-outline-primary">Dashboard Admin</a>
    <a href="index.php" class="btn btn-success">Kembali ke Menu</a>
  </div>
</div>
</body></html>
