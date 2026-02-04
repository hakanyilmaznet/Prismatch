<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../i18n.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $email = $_SESSION['user_email'] ?? null;
  if (!$email) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'not_logged_in']);
    exit;
  }

  $raw = file_get_contents('php://input');
  $data = json_decode($raw ?: '[]', true);
  if (!is_array($data)) $data = [];

  // Use server UTC date to prevent client manipulation
  $challengeDate = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');

  // Enforce single attempt (no overwrite):
  if (has_played_daily($email, $challengeDate)) {
    echo json_encode(['ok'=>false,'error'=>'already_played']);
    exit;
  }

  $country = request_country(); // Cloudflare preferred
  $lang    = get_request_language();

  $payload = [
    'challenge_date' => $challengeDate,
    'email'          => $email,
    'reached_level'  => (int)($data['reached_level'] ?? $data['reachedLevel'] ?? 0),
    'total_correct'  => (int)($data['total_correct'] ?? $data['correct'] ?? 0),
    'duration_ms'    => (int)($data['duration_ms'] ?? $data['durationMs'] ?? 0),
    'language'       => $lang,
    'country'        => $country,
    'rounds'         => $data['rounds'] ?? [],
  ];

  $payload['reached_level'] = max(1, min(50, (int)$payload['reached_level']));
  $payload['total_correct'] = max(0, (int)$payload['total_correct']);
  $payload['duration_ms'] = max(0, (int)$payload['duration_ms']);

  $payload['score'] = compute_daily_score($payload);

  upsert_daily_score($payload);

  echo json_encode(['ok'=>true, 'score'=>$payload['score'], 'country'=>$country, 'flag'=>country_flag_icon_url($country)]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'internal','detail'=>$e->getMessage()]);
}
