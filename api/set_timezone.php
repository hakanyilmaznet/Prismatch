<?php
require_once __DIR__ . '/../config.php';

session_name(SESSION_NAME);
session_start();

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data) || empty($data['timezone'])) {
  http_response_code(400);
  exit;
}

$tz = $data['timezone'];

// Güvenlik: whitelist + valid timezone
if (!in_array($tz, timezone_identifiers_list(), true)) {
  http_response_code(400);
  exit;
}

// Session + cookie
$_SESSION['browser_timezone'] = $tz;
setcookie(
  'tz',
  $tz,
  [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => COOKIE_SECURE,
    'httponly' => false,
    'samesite' => 'Lax'
  ]
);

echo json_encode(['ok' => true]);



