<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * Debug flag:
 * - true  => hatayı detail + trace ile döndürür (geliştirme)
 * - false => kullanıcıya minimal hata döndürür (prod)
 */
$DEBUG = true;

$country = get_request_country();

function fail(int $code, string $stage, string $msg, ?Throwable $e = null): void {
  global $DEBUG;
  http_response_code($code);

  $out = [
    'ok' => false,
    'error' => $msg,
    'stage' => $stage,
  ];

  if ($DEBUG && $e) {
    $out['detail'] = $e->getMessage();
    $out['type'] = get_class($e);
    $out['file'] = $e->getFile();
    $out['line'] = $e->getLine();

    // trace'i kısalt (ilk 12 frame)
    $trace = $e->getTrace();
    $out['trace'] = array_slice(array_map(function($t){
      return [
        'file' => $t['file'] ?? null,
        'line' => $t['line'] ?? null,
        'func' => ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''),
      ];
    }, $trace), 0, 12);
  }

  echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  // 1) auth
  if (empty($_SESSION['user_email']) || empty($_SESSION['user_id'])) {
    fail(401, 'auth', 'Not logged in');
  }
  $email = (string)$_SESSION['user_email'];
  $userId = (string)$_SESSION['user_id'];

  // 2) input
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') {
    fail(400, 'input', 'Empty body');
  }

  $data = json_decode($raw, true);
  if (!is_array($data)) {
    fail(400, 'parse', 'Invalid JSON');
  }

  // Cloudflare-derived locale metadata (server-trust)
  $data['country'] = get_request_country();
  $data['language'] = get_request_language();

  // 3) minimal payload sanity (debug amaçlı)
  if ($DEBUG) {
    if (!array_key_exists('reachedLevel', $data) || !array_key_exists('won', $data)) {
      // game payload değilse: yine de record_full_game içi kontrol eder ama burada uyaralım
      // hata yerine "warn" döndürmeyelim; sadece devam edeceğiz
    }
  }

  // 4) write
  try {
    $gameId = record_full_game($userId, $email, $data);
  } catch (Throwable $e) {
    fail(500, 'record_full_game', 'Internal error', $e);
  }

  echo json_encode(['ok' => true, 'gameId' => $gameId], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  // beklenmeyen
  fail(500, 'unexpected', 'Internal error', $e);
}



