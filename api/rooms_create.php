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

header('Content-Type: application/json');

$email = $_SESSION['user_email'] ?? null;
if (!$email) {
  http_response_code(403);
  echo json_encode(['error' => 'login_required']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$rounds = 50;
$name = (string)($input['name'] ?? ($_POST['name'] ?? ''));
if (trim($name) === '') {
  http_response_code(400);
  echo json_encode(['error' => 'name_required']);
  exit;
}
if ($rounds < 1) $rounds = 50;

try {
  $room = create_room($email, $rounds, $name);
  echo json_encode(['ok' => true, 'guid' => $room['guid']]);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'create_failed']);
}



