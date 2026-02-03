<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json; charset=utf-8');

try {
  $day = $_GET['day'] ?? '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
    $day = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d');
  }

  $email = $_SESSION['user_email'] ?? null;
  if (!$email) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'not_logged_in']);
    exit;
  }

  $played = has_played_daily($email, $day);

  echo json_encode(['ok'=>true,'played'=>$played,'day'=>$day]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Internal error','detail'=>$e->getMessage()]);
}
