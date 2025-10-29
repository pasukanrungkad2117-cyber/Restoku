<?php
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'emailkamu@gmail.com'; // ganti
    $mail->Password = 'APP_PASSWORD_GMAIL_KAMU'; // ganti
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom('emailkamu@gmail.com', 'Resto Online');
    $mail->addAddress('emailtujuan@gmail.com', 'Test User');
    $mail->isHTML(true);
    $mail->Subject = 'Test Email dari Resto';
    $mail->Body = 'Halo, ini adalah test email dari aplikasi Resto!';

    $mail->send();
    echo "✅ Email berhasil dikirim!";
} catch (Exception $e) {
    echo "❌ Gagal kirim email. Error: {$mail->ErrorInfo}";
}
