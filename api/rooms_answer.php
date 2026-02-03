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
if (!$email) {
  http_response_code(403);
  echo json_encode(['error' => 'login_required']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$guid = trim((string)($input['guid'] ?? ($_POST['guid'] ?? '')));
$round = (int)($input['round'] ?? ($_POST['round'] ?? 0));
$picked = (string)($input['picked'] ?? ($_POST['picked'] ?? ''));
$isTimeout = !empty($input['timeout']) || !empty($_POST['timeout']);
$responseMs = (int)($input['response_ms'] ?? ($_POST['response_ms'] ?? 0));
if ($guid === '' || $round < 1 || ($picked === '' && !$isTimeout)) {
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

$roomId = (int)$room['id'];
if ($room['status'] !== 'active') {
  http_response_code(409);
  echo json_encode(['error' => 'room_not_active']);
  exit;
}

$pdo = db();
$st = $pdo->prepare("SELECT status FROM room_players WHERE room_id=:rid AND email=:email LIMIT 1");
$st->execute([':rid'=>$roomId, ':email'=>$email]);
$status = $st->fetchColumn();
if ($status !== 'active') {
  http_response_code(403);
  echo json_encode(['error' => 'not_active']);
  exit;
}

$question = room_round_question($roomId, $round);
if (!$question) {
  http_response_code(404);
  echo json_encode(['error' => 'round_not_found']);
  exit;
}

$target = $question['target'] ?? '';
if ($target === '') {
  http_response_code(500);
  echo json_encode(['error' => 'invalid_round']);
  exit;
}

$isCorrect = ($picked !== '' && $picked === $target);
$scoreDelta = 0;
if ($isCorrect) {
  $scoreDelta = max(50, 1000 - (int)floor(max(0, $responseMs) / 10));
  room_add_score($roomId, $email, $scoreDelta, 1);
  room_log_event($roomId, $round, $email, 'answer', [
    'picked' => $picked,
    'target' => $target,
    'response_ms' => $responseMs,
    'correct' => true,
    'score_delta' => $scoreDelta,
  ]);
} else {
  room_mark_eliminated($roomId, $email, $round);
  room_log_event($roomId, $round, $email, $isTimeout ? 'timeout' : 'eliminate', [
    'picked' => $picked !== '' ? $picked : null,
    'target' => $target,
    'response_ms' => $responseMs,
    'correct' => false,
  ]);
}

$players = list_room_players($roomId);
pusher_trigger('presence-room-' . $guid, 'room:update', [
  'guid' => $guid,
  'round' => $round,
  'players' => $players,
  'eliminated' => $isCorrect ? null : $email,
]);

echo json_encode(['ok' => true, 'correct' => $isCorrect, 'score_delta' => $scoreDelta]);
