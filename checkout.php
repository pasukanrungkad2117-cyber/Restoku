<?php
// checkout.php (lengkap, siap copy-paste)
// Requirements: composer autoload, Dompdf, PHPMailer (optional), config.php, functions.php
// Behavior: create order + items, generate PDF struk, send email, redirect to pay.php?token=...

declare(strict_types=1);
session_start();

require_once __DIR__ . '/vendor/autoload.php'; // composer autoload (harus ada)
require_once __DIR__ . '/config.php';          // harus menyediakan $pdo
require_once __DIR__ . '/functions.php';       // harus menyediakan CSRF, flash helpers

use Dompdf\Dompdf;
use Dompdf\Options;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Basic request validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: cart.php');
    exit;
}
if (!isset($_POST['csrf']) || !verify_csrf_token($_POST['csrf'])) {
    die('CSRF token invalid');
}
if (($_POST['action'] ?? '') !== 'checkout') {
    header('Location: cart.php');
    exit;
}

// Cart from session
$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    flash_set('success', 'Keranjang kosong');
    header('Location: cart.php');
    exit;
}

// Input validation
$table_no = trim((string)($_POST['table_no'] ?? ''));
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$customer_note = trim((string)($_POST['customer_note'] ?? ''));

if (!$table_no || !$email) {
    flash_set('success', 'Nomor meja dan email wajib diisi');
    header('Location: cart.php');
    exit;
}

// compute total (server authoritative)
$total = 0;
foreach ($cart as $c) {
    $total += (float)$c['price'] * (int)$c['qty'];
}

// DB transaction: insert order + order_items
try {
    $pdo->beginTransaction();

    $token = bin2hex(random_bytes(8)); // unique payment token
    $expires_dt = (new DateTime('+2 minutes'))->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("INSERT INTO orders (table_no, total, payment_status, customer_email, customer_note, payment_token, payment_expires, created_at)
                           VALUES (?, ?, 'PENDING', ?, ?, ?, ?, NOW())");
    $stmt->execute([$table_no, $total, $email, $customer_note, $token, $expires_dt]);

    $order_id = (int)$pdo->lastInsertId();
    if ($order_id <= 0) {
        throw new RuntimeException('Failed to create order');
    }

    $ins = $pdo->prepare("INSERT INTO order_items (order_id, menu_id, qty, price) VALUES (?, ?, ?, ?)");
    foreach ($cart as $c) {
        $ins->execute([$order_id, (int)$c['id'], (int)$c['qty'], (float)$c['price']]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Checkout DB error: ' . $e->getMessage());
    flash_set('success', 'Terjadi kesalahan saat membuat order');
    header('Location: cart.php');
    exit;
}

// Build receipt HTML
$items_html = '';
foreach ($cart as $c) {
    $items_html .= "<tr>
        <td style='padding:8px;border-bottom:1px solid #eee;'>" . htmlspecialchars($c['name']) . "</td>
        <td style='padding:8px;border-bottom:1px solid #eee;text-align:center;'>" . (int)$c['qty'] . "</td>
        <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>Rp " . number_format((float)$c['price'], 0, ',', '.') . "</td>
        <td style='padding:8px;border-bottom:1px solid #eee;text-align:right;'>Rp " . number_format((float)$c['price'] * (int)$c['qty'], 0, ',', '.') . "</td>
    </tr>";
}

$receipt_html = "<!doctype html><html lang='id'><head><meta charset='utf-8'><title>Struk #{$order_id}</title>
<style>
body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:16px}
.header{margin-bottom:12px}
.table{width:100%;border-collapse:collapse;margin-top:8px}
.table th, .table td{padding:8px;border-bottom:1px solid #eee}
.tfoot td{font-weight:700}
.note-box{background:#f7f7f8;padding:8px;border-radius:6px;margin-top:8px}
</style>
</head>
<body>
  <div class='header'>
    <h2>Resto</h2>
    <div>Order ID: <strong>#{$order_id}</strong></div>
    <div>Meja: <strong>" . htmlspecialchars($table_no) . "</strong></div>
    <div>Tanggal: " . date('Y-m-d H:i') . "</div>
  </div>

  <table class='table' role='presentation'>
    <thead>
      <tr style='background:#f6f6f6'><th style='text-align:left'>Menu</th><th>Qty</th><th style='text-align:right'>Harga</th><th style='text-align:right'>Subtotal</th></tr>
    </thead>
    <tbody>
      {$items_html}
    </tbody>
    <tfoot>
      <tr class='tfoot'><td colspan='3' style='text-align:right'>Total</td><td style='text-align:right'>Rp " . number_format($total, 0, ',', '.') . "</td></tr>
    </tfoot>
  </table>

  " . (!empty($customer_note) ? "<div class='note-box'><strong>Catatan pelanggan:</strong><div>" . htmlspecialchars($customer_note) . "</div></div>" : "") . "

  <p style='margin-top:16px;color:#555'>Terima kasih telah memesan. Simpan halaman ini atau cek email untuk struk.</p>
</body>
</html>";

// Create PDF (Dompdf) if available
$pdf_path = null;
$pdf_created = false;
if (class_exists('\Dompdf\Dompdf')) {
    try {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($receipt_html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $tmp = sys_get_temp_dir();
        $pdf_path = $tmp . DIRECTORY_SEPARATOR . "receipt_{$order_id}_" . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($pdf_path, $dompdf->output());
        $pdf_created = file_exists($pdf_path);
    } catch (Throwable $e) {
        error_log('Dompdf error: ' . $e->getMessage());
        $pdf_created = false;
    }
}

// --- Email sending ---
// Configure your SMTP here. **GANTI** dengan data asli.
// If using Gmail: use App Password (16 chars) and no spaces.
$smtp = [
    'host' => 'smtp.gmail.com',
    'username' => 'pasukanrungkad2117@gmail.com',
    'password' => 'nunjdcpnoepmwfxm', // <-- PENTING: hapus spasi jika ada; gunakan 16-char App Password tanpa spasi
    'port' => 587,
    'secure' => 'tls',
    'from_email' => 'pasukanrungkad2117@gmail.com',
    'from_name' => 'Resto'
];

if(class_exists('PHPMailer\\PHPMailer\\PHPMailer')){
    try {
        $mail = new PHPMailer(true);
        // debug level 0 (non verbose). Ubah ke SMTP::DEBUG_SERVER untuk debugging
        // $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = $smtp['host'];
        $mail->SMTPAuth = true;
        $mail->Username = $smtp['username'];
        $mail->Password = $smtp['password'];
        $mail->SMTPSecure = $smtp['secure'] === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int)$smtp['port'];

        // From harus sama dengan akun yang autentikasi (disarankan)
        $mail->setFrom($smtp['from_email'], $smtp['from_name']);
        $mail->addReplyTo($smtp['from_email'], $smtp['from_name']);

        $mail->addAddress($email);
        $mail->Subject = "Struk Pembelian #{$order_id} — Resto";
        $mail->isHTML(true);
        $mail->Body = $receipt_html;
        $mail->AltBody = "Order ID: {$order_id} — Total: Rp ".number_format($total,0,',','.');
        if($pdf_created && $pdf_path){
            $mail->addAttachment($pdf_path, "Struk-Order-{$order_id}.pdf");
        }

        $mail->send();
        $sent = true;
    } catch(Exception $ex){
        error_log("PHPMailer error: " . $ex->getMessage());
        error_log("PHPMailer debug: " . ($mail->ErrorInfo ?? 'no debug info'));
        $sent = false;
    }
} else {
    // Fallback to mail() without attachment (may not work on XAMPP without proper sendmail)
    $to = $email;
    $subject = "Struk Pembelian #{$order_id} — Resto";
    $boundary = md5(time());
    $headers = "From: {$smtp['from_name']} <{$smtp['from_email']}>\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $receipt_html . "\r\n";
    $message .= "--{$boundary}--";
    $sent = @mail($to, $subject, $message, $headers);
}

// remove temporary pdf
if ($pdf_created && $pdf_path && file_exists($pdf_path)) {
    @unlink($pdf_path);
}

// clear cart
unset($_SESSION['cart']);

// redirect to pay page (QR + countdown). The payment.php will show QR and poll status.
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$redirect = $base . '/payment.php?token=' . rawurlencode($token);

// set flash message about email send status
if ($sent) {
    flash_set('success', 'Order dibuat dan struk telah dikirim ke email Anda.');
} else {
    flash_set('success', 'Order dibuat. Namun pengiriman email gagal — periksa konfigurasi SMTP.');
}

header('Location: ' . $redirect);
exit;
