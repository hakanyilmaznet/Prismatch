<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

function json_fail(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  if (!is_array($data)) {
    json_fail(400, 'invalid_json');
  }

  $username = trim((string)($data['username'] ?? ''));
  if ($username === '') {
    json_fail(400, 'username_required');
  }

  if (!preg_match('/^[\p{L}\p{N} _-]{2,30}$/u', $username)) {
    json_fail(400, 'username_invalid');
  }

  $clientId = trim((string)($data['user_id'] ?? ''));

  $user = ensure_local_user($username);
  $_SESSION['user_email'] = (string)($user['email'] ?? $username);
  $_SESSION['user_id'] = (string)($user['id'] ?? '');
  $_SESSION['user_name'] = $username;
  $_SESSION['local_user_id'] = $clientId !== '' ? $clientId : null;
  $_SESSION['login_provider'] = 'local';
  $_SESSION['is_guest'] = true;

  echo json_encode([
    'ok' => true,
    'user_id' => (string)($user['id'] ?? ''),
    'username' => $username,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  json_fail(500, 'internal');
}



