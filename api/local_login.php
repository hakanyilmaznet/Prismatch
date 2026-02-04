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

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) $data = [];

$username = trim((string)($data['username'] ?? ($_POST['username'] ?? '')));
$userId = trim((string)($data['user_id'] ?? ($_POST['user_id'] ?? '')));

if ($username === '') {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Kullanıcı adı gerekli.']);
  exit;
}

if (mb_strlen($username, 'UTF-8') > 30) {
  $username = mb_substr($username, 0, 30, 'UTF-8');
}

$user = ensure_local_user($username, $userId !== '' ? $userId : null);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'] ?? null;
$_SESSION['login_provider'] = 'local';
$_SESSION['user_name'] = user_display_name_from_row($user);

echo json_encode([
  'ok' => true,
  'user_id' => $user['id'],
  'username' => $user['username'] ?? $username,
]);
