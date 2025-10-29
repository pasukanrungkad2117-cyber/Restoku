
<?php
require_once __DIR__ . '/vendor/autoload.php';

// config.php (tambahkan)
define('PAYMENT_SECRET', 'ganti_dengan_rahasia_panjang_acak_32_atau_lebih');



// config.php
session_start();

$DB_HOST = '127.0.0.1';
$DB_NAME = 'resto_app';
$DB_USER = 'root';
$DB_PASS = ''; // jika pakai password XAMPP, isi di sini

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// helper: base url
$BASE_URL = '/resto'; // sesuaikan jika folder beda, contoh: '/resto' or '/'

function e($v){ return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
