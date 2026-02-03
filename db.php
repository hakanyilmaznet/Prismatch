<?php
// db.php (MySQL/MariaDB uyumlu - JSON alanı LONGTEXT)

require_once __DIR__ . '/config.php';


if (!function_exists('mb_chr')) {
  function mb_chr($code, $encoding = 'UTF-8') {
    return iconv('UCS-4LE', $encoding, pack('V', (int)$code));
  }
}

function db() {
  static $pdo = null;
  if ($pdo) return $pdo;

  $dsn = sprintf(
    'mysql:host=%s;dbname=%s;charset=%s',
    DB_HOST,
    DB_NAME,
    DB_CHARSET
  );

  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  // Schema init (idempotent)
  init_schema($pdo);

  return $pdo;
}

function ensure_column(PDO $pdo, $table, $col, $ddl) {
  // idempotent column add for MySQL/MariaDB
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND COLUMN_NAME = :c
  ");
  $stmt->execute([':t' => $table, ':c' => $col]);
  $row = $stmt->fetch();
  $exists = $row && (int)$row['c'] > 0;

  if (!$exists) {
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$ddl}");
  }
}

function init_schema(PDO $pdo) {
  // users: kişisel veri sadece email
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      email VARCHAR(320) NOT NULL,
      created_at DATETIME(3) NOT NULL,
      last_login DATETIME(3) NOT NULL,
      total_plays INT NOT NULL DEFAULT 0,
      total_wins INT NOT NULL DEFAULT 0,
      best_level INT NOT NULL DEFAULT 0,
      total_correct INT NOT NULL DEFAULT 0,
      PRIMARY KEY (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS games (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      email VARCHAR(320) NOT NULL,
      created_at DATETIME(3) NOT NULL,
      finished_at DATETIME(3) NOT NULL,
      duration_ms INT NOT NULL,
      reached_level INT NOT NULL,
      total_correct INT NOT NULL,
      score INT NOT NULL DEFAULT 0,
      won TINYINT(1) NOT NULL,
      language VARCHAR(16) NULL,
      country VARCHAR(8) NULL,
      PRIMARY KEY (id),
      INDEX idx_games_email_created (email, created_at),
      CONSTRAINT fk_games_email FOREIGN KEY (email) REFERENCES users(email)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");


  // Migrations / backward compatible columns
  ensure_column($pdo, 'games', 'language', 'VARCHAR(16) NULL');
  ensure_column($pdo, 'games', 'country', 'VARCHAR(8) NULL');
  ensure_column($pdo, 'games', 'score', 'INT NOT NULL DEFAULT 0');

  // ✅ MariaDB uyumluluğu: grid_colors_json LONGTEXT
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rounds (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      game_id BIGINT UNSIGNED NOT NULL,
      level INT NOT NULL,
      target_color VARCHAR(16) NOT NULL,
      grid_colors_json LONGTEXT NOT NULL,
      picked_color VARCHAR(16) NULL,
      response_ms INT NOT NULL,
      is_correct TINYINT(1) NOT NULL,
      created_at DATETIME(3) NOT NULL,
      PRIMARY KEY (id),
      INDEX idx_rounds_game_level (game_id, level),
      CONSTRAINT fk_rounds_game FOREIGN KEY (game_id) REFERENCES games(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Daily tables (idempotent)
  ensure_daily_schema($pdo);
}

function now_utc_mysql() {
  $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
  return $dt->format('Y-m-d H:i:s.v'); // DATETIME(3)
}

function iso_to_mysql_datetime($iso) {
  try {
    $dt = new DateTimeImmutable($iso);
  } catch (Exception $e) {
    return now_utc_mysql();
  }
  $dt = $dt->setTimezone(new DateTimeZone('UTC'));
  return $dt->format('Y-m-d H:i:s.v');
}

function upsert_user_login($email) {
  $pdo = db();
  $now = now_utc_mysql();

  $stmt = $pdo->prepare("
    INSERT INTO users (email, created_at, last_login)
    VALUES (:email, :created_at, :last_login)
    ON DUPLICATE KEY UPDATE last_login = VALUES(last_login)
  ");
  $stmt->execute([
    ':email' => $email,
    ':created_at' => $now,
    ':last_login' => $now,
  ]);
}

function get_user_stats($email) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
  $stmt->execute([':email' => $email]);
  return $stmt->fetch() ?: [];
}

/**
 * Full game payload kaydı:
 * payload = {
 *   reachedLevel, correct, won, startedAt, endedAt, durationMs,
 *   rounds: [{ level, targetColor, gridColors[9], pickedColor|null, responseMs, isCorrect }]
 * }
 */
 
 function record_full_game($email, $gamePayload) {
  $pdo = db();

  // FK kırılmasın
  upsert_user_login($email);

  $startedIso = (string)(isset($gamePayload['startedAt']) ? $gamePayload['startedAt'] : '');
  $endedIso   = (string)(isset($gamePayload['endedAt']) ? $gamePayload['endedAt'] : '');

  $createdAt  = $startedIso ? iso_to_mysql_datetime($startedIso) : now_utc_mysql();
  $finishedAt = $endedIso   ? iso_to_mysql_datetime($endedIso)   : now_utc_mysql();

  $durationMs = (int)(isset($gamePayload['durationMs']) ? $gamePayload['durationMs'] : 0);
  $reached    = (int)(isset($gamePayload['reachedLevel']) ? $gamePayload['reachedLevel'] : 1);
  $totalCorrect = (int)(isset($gamePayload['correct']) ? $gamePayload['correct'] : 0);
  $won = !empty($gamePayload['won']) ? 1 : 0;
  $score = compute_daily_score($gamePayload);

  if ($reached < 1) $reached = 1;
  if ($reached > 25) $reached = 25;
  if ($totalCorrect < 0) $totalCorrect = 0;
  if ($durationMs < 0) $durationMs = 0;

  $pdo->beginTransaction();
  try {
    // -------------------------
    // 1) Insert game
    // -------------------------
    $sqlGame = "
      INSERT INTO games
        (email, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country)
      VALUES
        (:email, :created_at, :finished_at, :duration_ms, :reached_level, :total_correct, :score, :won, :language, :country)
    ";
    if (trim($sqlGame) === '') throw new RuntimeException('sqlGame is empty');

    $stmtGame = $pdo->prepare($sqlGame);
    $stmtGame->execute([
      ':email' => $email,
      ':created_at' => $createdAt,
      ':finished_at' => $finishedAt,
      ':duration_ms' => $durationMs,
      ':reached_level' => $reached,
      ':total_correct' => $totalCorrect,
      ':score' => $score,
      ':won' => $won,
      ':language' => get_request_language(),
      ':country' => get_request_country(),
    ]);

    $gameId = (int)$pdo->lastInsertId();

    // -------------------------
    // 2) Insert rounds
    // -------------------------
    $sqlRound = "
      INSERT INTO rounds
        (game_id, level, target_color, grid_colors_json, picked_color, response_ms, is_correct, created_at)
      VALUES
        (:game_id, :level, :target_color, :grid_json, :picked_color, :response_ms, :is_correct, :created_at)
    ";
    if (trim($sqlRound) === '') throw new RuntimeException('sqlRound is empty');

    $stmtRound = $pdo->prepare($sqlRound);

    $rounds = isset($gamePayload['rounds']) ? $gamePayload['rounds'] : [];
    if (is_array($rounds)) {
      foreach ($rounds as $r) {
        if (!is_array($r)) continue;

        $level = (int)(isset($r['level']) ? $r['level'] : 0);
        $target = (string)(isset($r['targetColor']) ? $r['targetColor'] : '');
        $grid = isset($r['gridColors']) ? $r['gridColors'] : [];
        $picked = array_key_exists('pickedColor', $r) ? $r['pickedColor'] : null;
        $respMs = (int)(isset($r['responseMs']) ? $r['responseMs'] : 0);
        $isCorrect = !empty($r['isCorrect']) ? 1 : 0;

        if ($level < 1 || $level > 25) continue;
        if ($target === '') continue;
        if (!is_array($grid) || count($grid) !== 9) continue;

        $gridVals = array_values($grid);
        if (count(array_unique($gridVals)) !== 9) continue;

        $gridJson = json_encode($gridVals, JSON_UNESCAPED_SLASHES);

        // HY093 riskini minimize etmek için bindValue kullanımı
        $stmtRound->bindValue(':game_id', $gameId, PDO::PARAM_INT);
        $stmtRound->bindValue(':level', $level, PDO::PARAM_INT);
        $stmtRound->bindValue(':target_color', $target, PDO::PARAM_STR);
        $stmtRound->bindValue(':grid_json', $gridJson, PDO::PARAM_STR);

        if ($picked === null) {
          $stmtRound->bindValue(':picked_color', null, PDO::PARAM_NULL);
        } else {
          $stmtRound->bindValue(':picked_color', (string)$picked, PDO::PARAM_STR);
        }

        $stmtRound->bindValue(':response_ms', max(0, $respMs), PDO::PARAM_INT);
        $stmtRound->bindValue(':is_correct', $isCorrect ? 1 : 0, PDO::PARAM_INT);
        $stmtRound->bindValue(':created_at', now_utc_mysql(), PDO::PARAM_STR);

        $stmtRound->execute();
      }
    }

    // -------------------------
    // 3) Aggregate user stats (param repeat yok)
    // -------------------------
    $sqlAgg = "
      UPDATE users
      SET
        total_plays = total_plays + 1,
        total_wins = total_wins + :win_inc,
        best_level = CASE WHEN :lvl1 > best_level THEN :lvl2 ELSE best_level END,
        total_correct = total_correct + :correct
      WHERE email = :email
    ";
    if (trim($sqlAgg) === '') throw new RuntimeException('sqlAgg is empty');

    $stmtAgg = $pdo->prepare($sqlAgg);
    $stmtAgg->execute([
      ':win_inc' => $won ? 1 : 0,
      ':lvl1' => $reached,
      ':lvl2' => $reached,
      ':correct' => $totalCorrect,
      ':email' => $email,
    ]);

    $pdo->commit();
    return $gameId;

  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}


function list_games($email, $limit = 100) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT id, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
    FROM games
    WHERE email = :email
    ORDER BY id DESC
    LIMIT :lim
  ");
  $stmt->bindValue(':email', $email, PDO::PARAM_STR);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll() ?: [];
}

function get_game($email, $gameId) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT id, email, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
    FROM games
    WHERE id = :id AND email = :email
    LIMIT 1
  ");
  $stmt->execute([':id' => $gameId, ':email' => $email]);
  return $stmt->fetch() ?: [];
}

function list_rounds($gameId) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT level, target_color, grid_colors_json, picked_color, response_ms, is_correct, created_at
    FROM rounds
    WHERE game_id = :gid
    ORDER BY level ASC
  ");
  $stmt->execute([':gid' => $gameId]);
  return $stmt->fetchAll() ?: [];
}

function get_request_country() {
  // Cloudflare country (ISO-3166-1 alpha-2)
  if (!empty($_SERVER['HTTP_CF_IPCOUNTRY']) && $_SERVER['HTTP_CF_IPCOUNTRY'] !== 'XX') {
    $cc = strtoupper(trim((string)$_SERVER['HTTP_CF_IPCOUNTRY']));
    if (preg_match('/^[A-Z]{2}$/', $cc)) return $cc;
  }
  return null;
}

function get_request_language() {
  $supported = array_keys(supported_languages());

  $cfLang = (isset($_SERVER['HTTP_CF_LANGUAGE']) ? $_SERVER['HTTP_CF_LANGUAGE'] : (isset($_SERVER['HTTP_CF_LOCALE']) ? $_SERVER['HTTP_CF_LOCALE'] : ''));
  if ($cfLang !== '') {
    $norm = normalize_lang((string)$cfLang);
    if (in_array($norm, $supported, true)) return $norm;
  }

  $al = (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '');
  if ($al) {
    $cands = explode(',', $al);
    foreach ($cands as $c) {
      $code = trim(explode(';', $c)[0]);
      if ($code === '') continue;
      if (in_array($code, $supported, true)) return $code;
      $base = normalize_lang($code);
      if (in_array($base, $supported, true)) return $base;
    }
  }

  if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], $supported, true)) {
    return (string)$_SESSION['lang'];
  }

  return 'en';
}
/* ================================
   Cloudflare country + flag helper
   ================================ */

/**
 * Returns ISO-3166-1 alpha-2 country code based on Cloudflare header.
 * - Requires Cloudflare proxy be active AND IP Geolocation enabled.
 */
function cf_country() {
  $cc = (isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '');
  $cc = strtoupper(trim((string)$cc));
  if ($cc === '' || $cc === 'XX') return null;
  if (!preg_match('/^[A-Z]{2}$/', $cc)) return null;
  return $cc;
}

/**
 * Fallback for when CF-IPCountry missing:
 * - if client posted "country" (locale-derived), accept it ONLY if valid.
 */
function request_country_fallback() {
  $cc = (isset($_POST['country']) ? $_POST['country'] : '');
  $cc = strtoupper(trim((string)$cc));
  if ($cc === '' || $cc === 'XX') return null;
  if (!preg_match('/^[A-Z]{2}$/', $cc)) return null;
  return $cc;
}

/** Prefer Cloudflare, fallback to client locale-derived country if provided. */
function request_country() {
  return cf_country();
}

/** Country code -> flag emoji (e.g., TR -> 🇹🇷). */
function country_flag($cc) {
  if (!$cc) return '🏳️';
  $cc = strtoupper($cc);
  if (!preg_match('/^[A-Z]{2}$/', $cc)) return '🏳️';
  $a = ord($cc[0]) - 65 + 0x1F1E6;
  $b = ord($cc[1]) - 65 + 0x1F1E6;
  return mb_chr($a, 'UTF-8') . mb_chr($b, 'UTF-8');
}

/** Country code -> local flag icon URL (flags/...). */
function country_flag_icon_url($cc) {
  static $map = null;
  if ($map === null) {
    $path = __DIR__ . '/flags/iso2_to_flag.php';
    if (is_file($path)) {
      $map = require $path;
      if (!is_array($map)) $map = [];
    } else {
      $map = [];
    }
  }

  $cc = strtoupper(trim((string)$cc));
  if (!preg_match('/^[A-Z]{2}$/', $cc)) return '';
  if (isset($map[$cc])) {
    return 'flags/' . $map[$cc];
  }
  return '';
}

/* ================================
   Daily Challenge schema & queries
   ================================ */

function daily_target_show_ms_for_level($level) {
  $level = (int)$level;
  if ($level < 1) $level = 1;
  if ($level > 25) $level = 25;

  $startMs = 3000;
  $decay = 0.9;
  $minMs = 250;

  $ms = (int)floor($startMs * pow($decay, $level - 1));
  if ($ms < $minMs) $ms = $minMs;
  return $ms;
}

function compute_daily_score($payload) {
  $reached = (int)(isset($payload['reached_level']) ? $payload['reached_level'] : (isset($payload['reachedLevel']) ? $payload['reachedLevel'] : 0));
  if ($reached < 1) $reached = 1;
  if ($reached > 25) $reached = 25;

  $rounds = isset($payload['rounds']) && is_array($payload['rounds']) ? $payload['rounds'] : [];
  $sumRatio = 0.0;
  $count = 0;

  foreach ($rounds as $r) {
    if (!is_array($r)) continue;
    $level = (int)(isset($r['level']) ? $r['level'] : 0);
    if ($level < 1 || $level > 25) continue;

    $isCorrect = !empty($r['isCorrect']) ? true : false;
    if (!$isCorrect) continue;

    $responseMs = (int)(isset($r['responseMs']) ? $r['responseMs'] : 0);
    $responseMs = max(1, $responseMs);
    $showMs = daily_target_show_ms_for_level($level);

    $ratio = $showMs / $responseMs;
    if ($ratio < 0) $ratio = 0.0;
    if ($ratio > 1.5) $ratio = 1.5;
    $sumRatio += $ratio;
    $count++;
  }

  $avgRatio = $count > 0 ? ($sumRatio / $count) : 0.0;
  $timeFactor = min(1.0, $avgRatio / 1.5);
  $levelFactor = $reached / 25;

  $score = (int)round(1000 * ((0.7 * $levelFactor) + (0.3 * $timeFactor)));
  if ($score < 0) $score = 0;
  if ($score > 1000) $score = 1000;
  return $score;
}

function ensure_daily_schema($pdo = null) {
  if (!$pdo) {
    $pdo = (isset($GLOBALS['__pdo_daily_tmp']) ? $GLOBALS['__pdo_daily_tmp'] : null);
    if (!$pdo) $pdo = (isset($GLOBALS['pdo']) ? $GLOBALS['pdo'] : null);
  }
  if (!$pdo) return;

  // Create daily_scores table (one row per user per UTC date)
  $sql = "
  CREATE TABLE IF NOT EXISTS daily_scores (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    challenge_date DATE NOT NULL,               -- UTC date
    email VARCHAR(255) NOT NULL,
    score INT NOT NULL DEFAULT 0,               -- total_correct or custom scoring
    reached_level INT NOT NULL DEFAULT 0,
    total_correct INT NOT NULL DEFAULT 0,
    duration_ms INT NOT NULL DEFAULT 0,
    language VARCHAR(16) NULL,
    country VARCHAR(2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_daily_user (challenge_date, email),
    KEY idx_daily_date_score (challenge_date, score),
    KEY idx_daily_country_date (country, challenge_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ";
  // Some hosts disallow multi statements in prepare; execute as-is.
  $pdo->exec($sql);
}

/**
 * Insert or update a user's daily score (one attempt/day enforced by UNIQUE).
 * If you truly need "single attempt, no overwrite", change ON DUPLICATE to no-op.
 */
function upsert_daily_score($row) {
  $pdo = db();

  $sql = "
    INSERT INTO daily_scores
      (challenge_date, email, score, reached_level, total_correct, duration_ms, language, country)
    VALUES
      (:challenge_date, :email, :score, :reached_level, :total_correct, :duration_ms, :language, :country)
    ON DUPLICATE KEY UPDATE
      score = VALUES(score),
      reached_level = VALUES(reached_level),
      total_correct = VALUES(total_correct),
      duration_ms = VALUES(duration_ms),
      language = VALUES(language),
      country = VALUES(country)
  ";
  $st = $pdo->prepare($sql);
  $st->execute([
    ':challenge_date' => $row['challenge_date'],
    ':email'          => $row['email'],
    ':score'          => (int)$row['score'],
    ':reached_level'  => (int)$row['reached_level'],
    ':total_correct'  => (int)$row['total_correct'],
    ':duration_ms'    => (int)$row['duration_ms'],
    ':language'       => (isset($row['language']) ? $row['language'] : null),
    ':country'        => (isset($row['country']) ? $row['country'] : null),
  ]);
}

/** Has user already played today (UTC date)? */
function has_played_daily($email, $challengeDate) {
  $pdo = db();
  $st = $pdo->prepare("SELECT 1 FROM daily_scores WHERE challenge_date=:d AND email=:e LIMIT 1");
  $st->execute([':d'=>$challengeDate, ':e'=>$email]);
  return (bool)$st->fetchColumn();
}

/**
 * Leaderboard for a date, optional country filter.
 * Sort: score desc, reached_level desc, duration asc, created_at asc.
 */
function daily_leaderboard($challengeDate, $country = null, $limit = 50) {
  $pdo = db();
  $limit = max(1, min(200, $limit));

  if ($country) {
    $st = $pdo->prepare("
      SELECT email, score, reached_level, total_correct, duration_ms, language, country, created_at
      FROM daily_scores
      WHERE challenge_date=:d AND country=:c
      ORDER BY score DESC, reached_level DESC, duration_ms ASC, created_at ASC
      LIMIT {$limit}
    ");
    $st->execute([':d'=>$challengeDate, ':c'=>$country]);
  } else {
    $st = $pdo->prepare("
      SELECT email, score, reached_level, total_correct, duration_ms, language, country, created_at
      FROM daily_scores
      WHERE challenge_date=:d
      ORDER BY score DESC, reached_level DESC, duration_ms ASC, created_at ASC
      LIMIT {$limit}
    ");
    $st->execute([':d'=>$challengeDate]);
  }
  return $st->fetchAll();
}

