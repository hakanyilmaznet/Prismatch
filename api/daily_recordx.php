<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../i18n.php';

header('Content-Type: application/json; charset=utf-8');

function pdo_conn(): PDO {
  if (function_exists('get_pdo')) return get_pdo();
  if (function_exists('pdo')) return pdo();
  if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) return $GLOBALS['pdo'];
  throw new RuntimeException('PDO connection not found. Please expose get_pdo() in db.php');
}

function ensure_daily_tables(PDO $pdo): void {
  // minimal schema; safe to run repeatedly
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS daily_scores (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      day_utc DATE NOT NULL,
      user_email VARCHAR(255) NULL,
      anon_id CHAR(36) NULL,
      reached_level INT NOT NULL,
      correct_count INT NOT NULL,
      duration_ms INT NOT NULL,
      won TINYINT(1) NOT NULL DEFAULT 0,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uq_daily_user (day_utc, user_email),
      UNIQUE KEY uq_daily_anon (day_utc, anon_id),
      KEY idx_day (day_utc),
      KEY idx_score (reached_level, correct_count, duration_ms)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS daily_rounds (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
      daily_score_id BIGINT UNSIGNED NOT NULL,
      stage INT NOT NULL,
      target_color CHAR(7) NOT NULL,
      picked_color CHAR(7) NOT NULL,
      response_ms INT NOT NULL,
      is_correct TINYINT(1) NOT NULL,
      grid_json JSON NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_score_stage (daily_score_id, stage),
      CONSTRAINT fk_daily_rounds_score FOREIGN KEY (daily_score_id)
        REFERENCES daily_scores(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

function get_anon_id(): string {
  if (!empty($_COOKIE['anon_id'])) return $_COOKIE['anon_id'];
  $id = function_exists('uuid_create') ? uuid_create(UUID_TYPE_RANDOM) : bin2hex(random_bytes(16));
  // simple UUID-ish fallback if uuid ext missing:
  if (strlen($id) === 32) {
    $id = substr($id,0,8)."-".substr($id,8,4)."-".substr($id,12,4)."-".substr($id,16,4)."-".substr($id,20,12);
  }
  setcookie('anon_id', $id, ['expires'=>time()+60*60*24*365, 'path'=>'/', 'samesite'=>'Lax', 'httponly'=>false]);
  return $id;
}

try {
  $raw = file_get_contents('php://input') ?: '';
  $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

  $mode = $payload['mode'] ?? null;
  if ($mode !== 'daily') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'mode must be daily']);
    exit;
  }

  $day = $payload['dailyDayUtc'] ?? '';
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'dailyDayUtc invalid']);
    exit;
  }

  $email = $_SESSION['user_email'] ?? null;
  $anon = $email ? null : get_anon_id();

  $reached = (int)($payload['reachedLevel'] ?? 0);
  $correct = (int)($payload['correct'] ?? 0);
  $won = !empty($payload['won']) ? 1 : 0;
  $duration = (int)($payload['durationMs'] ?? 0);
  $rounds = $payload['rounds'] ?? [];

  if ($reached < 1 || $reached > 25 || $duration < 0 || !is_array($rounds)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'payload invalid']);
    exit;
  }

  $pdo = pdo_conn();
  ensure_daily_tables($pdo);

  $pdo->beginTransaction();

  // One attempt per day per identity (email or anon)
  $stmt = $pdo->prepare("
    INSERT INTO daily_scores (day_utc, user_email, anon_id, reached_level, correct_count, duration_ms, won)
    VALUES (:day, :email, :anon, :reached, :correct, :duration, :won)
    ON DUPLICATE KEY UPDATE
      reached_level = VALUES(reached_level),
      correct_count = VALUES(correct_count),
      duration_ms = VALUES(duration_ms),
      won = VALUES(won)
  ");
  $stmt->execute([
    ':day' => $day,
    ':email' => $email,
    ':anon' => $anon,
    ':reached' => $reached,
    ':correct' => $correct,
    ':duration' => $duration,
    ':won' => $won,
  ]);

  // fetch score id
  $scoreId = (int)$pdo->lastInsertId();
  if ($scoreId === 0) {
    // ON DUPLICATE KEY path: fetch existing row id
    $sel = $pdo->prepare("SELECT id FROM daily_scores WHERE day_utc=:day AND ".($email ? "user_email=:email" : "anon_id=:anon")." LIMIT 1");
    $sel->execute($email ? [':day'=>$day,':email'=>$email] : [':day'=>$day,':anon'=>$anon]);
    $scoreId = (int)($sel->fetchColumn() ?: 0);
  }

  // Replace rounds
  $pdo->prepare("DELETE FROM daily_rounds WHERE daily_score_id=?")->execute([$scoreId]);

  $ins = $pdo->prepare("
    INSERT INTO daily_rounds (daily_score_id, stage, target_color, picked_color, response_ms, is_correct, grid_json)
    VALUES (?, ?, ?, ?, ?, ?, CAST(? AS JSON))
  ");

  foreach ($rounds as $r) {
    $stage = (int)($r['level'] ?? 0);
    $target = (string)($r['targetColor'] ?? '');
    $picked = (string)($r['pickedColor'] ?? '');
    $rt = (int)($r['responseMs'] ?? 0);
    $ok = !empty($r['isCorrect']) ? 1 : 0;
    $grid = $r['gridColors'] ?? [];

    if ($stage < 1 || $stage > 25) continue;
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $target)) continue;
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $picked)) continue;

    $gridJson = json_encode(array_values($grid), JSON_UNESCAPED_SLASHES);
    $ins->execute([$scoreId, $stage, $target, $picked, $rt, $ok, $gridJson]);
  }

  $pdo->commit();

  echo json_encode(['ok'=>true,'id'=>$scoreId,'day'=>$day,'email'=>$email]);
} catch (Throwable $e) {
  if (!empty($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Internal error','detail'=>$e->getMessage()]);
}
