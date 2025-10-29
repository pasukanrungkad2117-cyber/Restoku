<?php
// check_status.php - return JSON { status, remaining } after verifying signature
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
$sig   = $_GET['sig'] ?? '';

if (!$token || !$sig) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_token_or_sig']);
    exit;
}

if (!defined('PAYMENT_SECRET') || PAYMENT_SECRET === '') {
    http_response_code(500);
    echo json_encode(['error' => 'server_misconfigured']);
    exit;
}

// verify signature (use hash_equals to prevent timing attacks)
$calc = hash_hmac('sha256', $token, PAYMENT_SECRET);
if (!hash_equals($calc, $sig)) {
    http_response_code(403);
    echo json_encode(['error' => 'invalid_signature']);
    exit;
}

// fetch order
$stmt = $pdo->prepare("SELECT id, payment_status, payment_expires, payment_used FROM orders WHERE payment_token = ? LIMIT 1");
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'order_not_found']);
    exit;
}

$now = time();
$expires_ts = $order['payment_expires'] ? strtotime($order['payment_expires']) : 0;

// if already paid
if ($order['payment_status'] === 'PAID' || (int)$order['payment_used'] === 1) {
    echo json_encode(['status' => 'PAID', 'remaining' => 0]);
    exit;
}

// if expired
if ($expires_ts > 0 && $now >= $expires_ts) {
    // mark expired (only if not paid)
    $upd = $pdo->prepare("UPDATE orders SET payment_status = 'EXPIRED' WHERE payment_token = ? AND payment_status != 'PAID'");
    $upd->execute([$token]);
    echo json_encode(['status' => 'EXPIRED', 'remaining' => 0]);
    exit;
}

// still pending
$remaining = max(0, $expires_ts - $now);
echo json_encode(['status' => 'PENDING', 'remaining' => (int)$remaining]);
exit;
