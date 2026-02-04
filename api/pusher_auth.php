<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../pusher.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json');

$email = $_SESSION['user_email'] ?? null;
if (!$email) {
  http_response_code(403);
  echo json_encode(['error' => 'login_required']);
  exit;
}

$socketId = $_POST['socket_id'] ?? '';
$channel = $_POST['channel_name'] ?? '';
if ($socketId === '' || $channel === '') {
  http_response_code(400);
  echo json_encode(['error' => 'bad_request']);
  exit;
}

echo pusher_auth_response($socketId, $channel, $email, ['email' => $email]);
