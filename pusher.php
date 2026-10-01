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

function pusher_clean_data(array $data): array {
  if (isset($data['players']) && is_array($data['players'])) {
    $cleanPlayers = [];
    foreach ($data['players'] as $p) {
      if (!is_array($p)) continue;
      $cleanPlayers[] = [
        'user_id' => (string)($p['user_id'] ?? ''),
        'email' => (string)($p['email'] ?? ''),
        'status' => (string)($p['status'] ?? 'active'),
        'score' => (int)($p['score'] ?? 0),
        'correct' => (int)($p['correct'] ?? 0),
        'is_online' => (int)($p['is_online'] ?? 1),
      ];
    }
    $data['players'] = $cleanPlayers;
  }
  return $data;
}

function pusher_trigger(string $channel, string $event, array $data, ?string $socketId = null): bool {
  if (defined('PUSHER_DISABLE') && PUSHER_DISABLE) {
    return true;
  }
  if (!defined('PUSHER_KEY') || !defined('PUSHER_APP_ID') || !defined('PUSHER_SECRET') || !PUSHER_KEY || !PUSHER_APP_ID || !PUSHER_SECRET) {
    return false;
  }

  $startTime = microtime(true);
  $cleanedData = pusher_clean_data($data);
  $jsonData = json_encode($cleanedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  $eventPayload = [
    'name' => $event,
    'channel' => $channel,
    'data' => $jsonData,
  ];

  if ($socketId !== null && $socketId !== '') {
    $eventPayload['socket_id'] = $socketId;
  }

  $payload = json_encode($eventPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

  // Safety check: Pusher Channels imposes a 10KB (10240 bytes) limit on event payloads
  $bytes = strlen($payload);
  if ($bytes > 9800 && class_exists('Prismatch\Core\Logger')) {
    \Prismatch\Core\Logger::log('WARN', 'Pusher', "Large payload ({$bytes} bytes) on '{$event}' for '{$channel}'", [
      'bytes' => $bytes,
      'keys' => array_keys($cleanedData),
    ]);
  }

  $query = pusher_sign_query($payload);
  $url = pusher_base_url() . '?' . $query;

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Expect:', // Disable 100-continue delay
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_CONNECTTIMEOUT_MS => 1500, // 1.5s connect timeout to prevent blocking HTTP workers
    CURLOPT_TIMEOUT_MS => 3000,        // 3.0s total execution timeout (down from 8s)
    CURLOPT_NOSIGNAL => 1,
    CURLOPT_TCP_NODELAY => 1,          // Disable Nagle's algorithm for low latency
    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4, // Fast IPv4 DNS resolution
  ]);

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
      'payload_bytes' => $bytes,
      'error' => $curlErr ?: null,
      'resp' => $resp !== false ? substr((string)$resp, 0, 120) : null,
      'data_keys' => array_keys($cleanedData),
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



