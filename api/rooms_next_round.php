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

if ($room['owner_email'] !== $email) {
  http_response_code(403);
  echo json_encode(['error' => 'not_owner']);
  exit;
}

$roomId = (int)$room['id'];
$current = (int)$room['current_round'];
$total = (int)$room['rounds_total'];
$next = $current + 1;

if ($next > $total) {
  set_room_finished($roomId);
  pusher_trigger('presence-room-' . $guid, 'room:finished', ['guid' => $guid]);
  echo json_encode(['ok' => true, 'finished' => true]);
  exit;
}

if ($current === 0) {
  set_room_started($roomId);
}

$question = room_create_round($roomId, $next, $total);
$timing = room_timing_for_round($next);

$payload = [
  'guid' => $guid,
  'round' => $next,
  'rounds_total' => $total,
  'question' => $question,
  'countdown_ms' => $timing['countdown_ms'],
  'show_ms' => $timing['show_ms'],
  'answer_ms' => $timing['answer_ms'],
];

pusher_trigger('presence-room-' . $guid, 'room:round', $payload);
echo json_encode(['ok' => true, 'round' => $next]);
