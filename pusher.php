<?php
require_once __DIR__ . '/config.php';
if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
  error_reporting(E_ALL);
  @ini_set('display_errors', '1');
  @ini_set('display_startup_errors', '1');
}


function pusher_base_url(): string {
  return 'https://api-' . PUSHER_CLUSTER . '.pusher.com/apps/' . PUSHER_APP_ID . '/events';
}

function pusher_sign_query(string $body): string {
  $timestamp = (string)time();
  $bodyMd5 = md5($body);
  $query = [
    'auth_key' => PUSHER_KEY,
    'auth_timestamp' => $timestamp,
    'auth_version' => '1.0',
    'body_md5' => $bodyMd5,
  ];
  $queryString = http_build_query($query);
  $stringToSign = "POST\n/apps/" . PUSHER_APP_ID . "/events\n" . $queryString;
  $signature = hash_hmac('sha256', $stringToSign, PUSHER_SECRET);
  return $queryString . '&auth_signature=' . $signature;
}

function pusher_trigger(string $channel, string $event, array $data): bool {
  $startTime = microtime(true);
  $payload = json_encode([
    'name' => $event,
    'channel' => $channel,
    'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  $query = pusher_sign_query($payload);
  $url = pusher_base_url() . '?' . $query;

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
  curl_setopt($ch, CURLOPT_TIMEOUT, 8);
  $resp = curl_exec($ch);
  $curlErr = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $durationMs = round((microtime(true) - $startTime) * 1000, 2);
  $ok = ($resp !== false && $code >= 200 && $code < 300);

  if (class_exists('Prismatch\Core\Logger')) {
    \Prismatch\Core\Logger::log($ok ? 'INFO' : 'ERROR', 'Pusher', "trigger '{$event}' on '{$channel}'", [
      'ok' => $ok,
      'http_code' => $code,
      'duration_ms' => $durationMs,
      'error' => $curlErr ?: null,
      'resp' => $resp !== false ? substr((string)$resp, 0, 120) : null,
      'data_keys' => array_keys($data),
    ]);
  }

  return $ok;
}

function pusher_auth_response(string $socketId, string $channelName, ?string $userId = null, ?array $userInfo = null): string {
  // For private channels
  if ($userId === null) {
    $stringToSign = $socketId . ':' . $channelName;
    $signature = hash_hmac('sha256', $stringToSign, PUSHER_SECRET);
    return json_encode(['auth' => PUSHER_KEY . ':' . $signature]);
  }

  // Presence channels
  $userData = ['user_id' => $userId];
  if ($userInfo !== null) $userData['user_info'] = $userInfo;
  $userJson = json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $stringToSign = $socketId . ':' . $channelName . ':' . $userJson;
  $signature = hash_hmac('sha256', $stringToSign, PUSHER_SECRET);
  return json_encode(['auth' => PUSHER_KEY . ':' . $signature, 'channel_data' => $userJson]);
}



