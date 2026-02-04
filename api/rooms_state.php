<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

session_name(SESSION_NAME);
session_set_cookie_params([
  'httponly' => true,
  'secure' => COOKIE_SECURE,
  'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
  http_response_code(403);
  echo json_encode(['error' => 'login_required']);
  exit;
}

$guid = trim((string)($_GET['guid'] ?? ''));
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

  echo json_encode([
    'ok' => true,
    'room' => [
      'guid' => $room['guid'],
      'name' => $room['name'],
      'status' => $room['status'],
    'rounds_total' => (int)$room['rounds_total'],
    'current_round' => (int)$room['current_round'],
    'owner_id' => $room['owner_id'],
    'owner_name' => user_display_name((string)$room['owner_id']),
  ],
  'players' => list_room_players((int)$room['id']),
]);
