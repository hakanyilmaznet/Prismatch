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

$email = $_SESSION['user_email'] ?? null;
$userId = $_SESSION['user_id'] ?? null;
if (!$email || !$userId) {
  http_response_code(403);
  echo json_encode(['error' => 'login_required']);
  exit;
}

$guid = trim((string)($_GET['guid'] ?? ($_POST['guid'] ?? '')));
if ($guid === '') {
  http_response_code(400);
  echo json_encode(['error' => 'bad_request']);
  exit;
}

$room = get_room_by_guid($guid);
if (!$room) {
  http_response_code(404);
  echo json_encode(['error' => 'not_found']);
  exit;
}

$roomId = (string)$room['id'];
if ($room['status'] === 'finished' || (int)$room['rounds_total'] <= 0) {
  echo json_encode(['ok' => true, 'status' => 'finished']);
  exit;
}

$current = (int)$room['current_round'];
if ($room['status'] === 'waiting' && $current === 0) {
  echo json_encode(['ok' => true, 'status' => 'waiting']);
  exit;
}

$pdo = db();
$st = $pdo->prepare("SELECT started_at, ended_at FROM room_rounds WHERE room_id=:rid AND round_index=:r LIMIT 1");
$st->execute([':rid' => $roomId, ':r' => $current]);
$round = $st->fetch();
if (!$round) {
  echo json_encode(['ok' => true]);
  exit;
}

$timing = room_timing_for_round($current);
$countdownMs = $timing['countdown_ms'];
$showMs = $timing['show_ms'];
$answerMs = $timing['answer_ms'];
$intermissionMs = 5000;

$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$startedAt = new DateTimeImmutable($round['started_at'], new DateTimeZone('UTC'));
$endedAt = $round['ended_at'] ? new DateTimeImmutable($round['ended_at'], new DateTimeZone('UTC')) : null;
$elapsed = ($now->getTimestamp() - $startedAt->getTimestamp()) * 1000;

if ($endedAt === null && $elapsed >= ($countdownMs + $showMs + $answerMs)) {
  room_end_round($roomId, $current);
  // Auto-eliminate players who did not answer correctly before timeout
  $correctEmails = [];
  $stc = $pdo->prepare("
    SELECT email
    FROM room_events
    WHERE room_id = :rid
      AND round_index = :r
      AND event_type = 'answer'
      AND payload_json LIKE '%\"correct\":true%'
  ");
  $stc->execute([':rid' => $roomId, ':r' => $current]);
  $correctEmails = $stc->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
  $correctSet = array_flip($correctEmails);
  $question = room_round_question($roomId, $current);
  $target = $question['target'] ?? null;

  $players = list_room_players($roomId);
  foreach ($players as $p) {
    if (($p['status'] ?? '') === 'eliminated') continue;
    $emailP = (string)($p['email'] ?? '');
    $userIdP = (string)($p['user_id'] ?? '');
    if ($emailP === '' || isset($correctSet[$emailP])) continue;
    room_mark_eliminated($roomId, $userIdP, $current);
    room_log_event($roomId, $current, $userIdP, $emailP, 'timeout', [
      'picked' => null,
      'target' => $target,
      'response_ms' => $answerMs,
      'correct' => false,
    ]);
  }

  $players = list_room_players($roomId);
  $activeCount = 0;
  foreach ($players as $p) {
    if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
  }
  pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
    'guid' => $guid,
    'round' => $current,
    'players' => $players,
  ]);
  if ($activeCount === 0) {
    set_room_finished($roomId, $current);
    pusher_trigger('presence-room-' . $guid, 'room:finished', ['guid' => $guid]);
    echo json_encode(['ok' => true, 'finished' => true]);
    exit;
  }
  echo json_encode(['ok' => true, 'ended' => true]);
  exit;
}

if ($endedAt !== null) {
  $elapsedEnd = ($now->getTimestamp() - $endedAt->getTimestamp()) * 1000;
  if ($elapsedEnd >= $intermissionMs) {
    // Re-read room state to avoid advancing after it was finished by another request.
    $roomFresh = get_room_by_guid($guid);
    if (!$roomFresh) {
      echo json_encode(['ok' => true, 'status' => 'not_found']);
      exit;
    }
    if ($roomFresh['status'] === 'finished' || (int)$roomFresh['rounds_total'] <= 0) {
      echo json_encode(['ok' => true, 'status' => 'finished']);
      exit;
    }
    $total = (int)$roomFresh['rounds_total'];
    $next = $current + 1;
    $players = list_room_players($roomId);
    $activeCount = 0;
    foreach ($players as $p) {
      if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
    }
    if ($activeCount === 0) {
      set_room_finished($roomId, $current);
      pusher_trigger('presence-room-' . $guid, 'room:finished', ['guid' => $guid]);
      echo json_encode(['ok' => true, 'finished' => true]);
      exit;
    }
    if ($next > $total) {
      set_room_finished($roomId, $current);
      pusher_trigger('presence-room-' . $guid, 'room:finished', ['guid' => $guid]);
      echo json_encode(['ok' => true, 'finished' => true]);
      exit;
    }
    $question = room_create_round($roomId, $next, $total);
    $timing = room_timing_for_round($next);
    pusher_trigger('presence-room-' . $guid, 'room:round', [
      'guid' => $guid,
      'round' => $next,
      'rounds_total' => $total,
      'question' => $question,
      'countdown_ms' => $timing['countdown_ms'],
      'show_ms' => $timing['show_ms'],
      'answer_ms' => $timing['answer_ms'],
    ]);
    echo json_encode(['ok' => true, 'next' => $next]);
    exit;
  }
}

echo json_encode(['ok' => true]);



