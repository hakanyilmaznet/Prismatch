<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json; charset=utf-8');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'login_required']);
  exit;
}

$_SESSION['link_user_id'] = $userId;
echo json_encode(['ok' => true]);
