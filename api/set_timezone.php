<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);

if (!is_array($data) || empty($data['timezone'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_input']);
    exit;
}

$tz = (string)$data['timezone'];

// Whitelist valid timezone
if (!in_array($tz, timezone_identifiers_list(), true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_timezone']);
    exit;
}

$_SESSION['browser_timezone'] = $tz;

if (PHP_VERSION_ID >= 70300) {
    setcookie('tz', $tz, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => COOKIE_SECURE,
        'httponly' => false,
        'samesite' => 'Lax'
    ]);
} else {
    setcookie('tz', $tz, time() + 60 * 60 * 24 * 30, '/', '', COOKIE_SECURE, false);
}

echo json_encode(['ok' => true]);
