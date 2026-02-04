<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../pusher.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
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

$display = $_SESSION['user_name'] ?? user_display_name($userId);
echo pusher_auth_response($socketId, $channel, $userId, ['name' => $display]);
