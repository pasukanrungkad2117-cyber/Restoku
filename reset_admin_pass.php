<?php
// reset_admin_pass.php — gunakan sementara lalu hapus
require_once 'config.php'; // pastikan path benar

// Ganti password di bawah sesuai keinginan:
$new_password = 'admin123'; // ganti jika mau password lain
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE username = ?");
    $stmt->execute([$new_hash, 'admin']);
    if($stmt->rowCount()){
        echo "Password admin berhasil direset menjadi: <strong>" . htmlspecialchars($new_password) . "</strong><br>";
        echo "Hapus file reset_admin_pass.php setelah login.";
    } else {
        echo "Tidak ada user 'admin' ditemukan. Periksa tabel users di phpMyAdmin.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
