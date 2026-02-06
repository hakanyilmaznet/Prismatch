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

  $userId = trim((string)($data['user_id'] ?? ''));
  if ($userId === '' || !preg_match('/^[a-f0-9]{8,64}$/i', $userId)) {
    $userId = bin2hex(random_bytes(8));
  }

  $_SESSION['user_email'] = $username;
  $_SESSION['user_id'] = $username;
  $_SESSION['local_user_id'] = $userId;

  upsert_user_login($username);

  echo json_encode([
    'ok' => true,
    'user_id' => $userId,
    'username' => $username,
  ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  json_fail(500, 'internal');
}



