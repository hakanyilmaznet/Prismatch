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
if ($guid === '' || $round < 1) {
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

room_end_round((int)$room['id'], $round);
$players = list_room_players((int)$room['id']);
$activeCount = 0;
foreach ($players as $p) {
  if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
}

pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
  'guid' => $guid,
  'round' => $round,
  'players' => $players,
]);

if ($activeCount === 0) {
  set_room_finished((int)$room['id']);
  pusher_trigger('presence-room-' . $guid, 'room:finished', ['guid' => $guid]);
}

echo json_encode(['ok' => true]);
