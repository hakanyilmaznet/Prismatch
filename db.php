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

function ensure_column($pdo, $table, $col, $ddl) {
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

function ensure_index($pdo, $table, $indexName, $ddl) {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND INDEX_NAME = :i
  ");
  $stmt->execute([':t' => $table, ':i' => $indexName]);
  $row = $stmt->fetch();
  $exists = $row && (int)$row['c'] > 0;
  if (!$exists) {
    $pdo->exec("ALTER TABLE `{$table}` ADD {$ddl}");
  }
}

function ensure_foreign_key($pdo, $table, $constraintName, $ddl) {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND CONSTRAINT_NAME = :c
  ");
  $stmt->execute([':t' => $table, ':c' => $constraintName]);
  $row = $stmt->fetch();
  $exists = $row && (int)$row['c'] > 0;
  if (!$exists) {
    $pdo->exec("ALTER TABLE `{$table}` ADD {$ddl}");
  }
}

function drop_foreign_key_if_exists($pdo, $table, $constraintName) {
  $stmt = $pdo->prepare("
    SELECT COUNT(*) AS c
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = :t
      AND CONSTRAINT_NAME = :c
  ");
  $stmt->execute([':t' => $table, ':c' => $constraintName]);
  $row = $stmt->fetch();
  $exists = $row && (int)$row['c'] > 0;
  if ($exists) {
    $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
  }
}

function init_schema($pdo) {
  // users: kişisel veri sadece email
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
      id CHAR(36) NOT NULL,
      username VARCHAR(30) NULL,
      email VARCHAR(320) NULL,
      created_at DATETIME(3) NOT NULL,
      last_login DATETIME(3) NOT NULL,
      total_plays INT NOT NULL DEFAULT 0,
      total_wins INT NOT NULL DEFAULT 0,
      best_level INT NOT NULL DEFAULT 0,
      total_correct INT NOT NULL DEFAULT 0,
      PRIMARY KEY (id),
      UNIQUE KEY uq_users_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS games (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id CHAR(36) NOT NULL,
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
      INDEX idx_games_user_created (user_id, created_at),
      CONSTRAINT fk_games_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");


  // Migrations / backward compatible columns
  ensure_column($pdo, 'users', 'id', 'CHAR(36) NULL');
  ensure_column($pdo, 'users', 'username', 'VARCHAR(30) NULL');
  ensure_column($pdo, 'users', 'email', 'VARCHAR(320) NULL');

  ensure_column($pdo, 'games', 'user_id', 'CHAR(36) NULL');
  ensure_column($pdo, 'games', 'language', 'VARCHAR(16) NULL');
  ensure_column($pdo, 'games', 'country', 'VARCHAR(8) NULL');
  ensure_column($pdo, 'games', 'score', 'INT NOT NULL DEFAULT 0');

  // Backfill user ids if needed
  try {
    $pdo->exec("UPDATE users SET id = UUID() WHERE id IS NULL OR id = ''");
  } catch (Exception $e) {}

  // Drop old email-based FKs if present
  drop_foreign_key_if_exists($pdo, 'games', 'fk_games_email');
  drop_foreign_key_if_exists($pdo, 'rooms', 'fk_rooms_owner');
  drop_foreign_key_if_exists($pdo, 'room_players', 'fk_room_players_email');

  // Ensure users.id is primary key
  try {
    $pdo->exec("ALTER TABLE users MODIFY id CHAR(36) NOT NULL");
  } catch (Exception $e) {}
  try {
    $stmt = $pdo->query("
      SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND CONSTRAINT_NAME = 'PRIMARY'
      LIMIT 1
    ");
    $pkCol = $stmt ? $stmt->fetchColumn() : null;
    if ($pkCol && $pkCol !== 'id') {
      $pdo->exec("ALTER TABLE users DROP PRIMARY KEY, ADD PRIMARY KEY (id)");
    }
  } catch (Exception $e) {}

  ensure_index($pdo, 'users', 'uq_users_email', 'UNIQUE KEY uq_users_email (email)');

  // Backfill new user_id columns from legacy email fields
  try {
    $pdo->exec("
      UPDATE games g
      JOIN users u ON g.email = u.email
      SET g.user_id = u.id
      WHERE (g.user_id IS NULL OR g.user_id = '')
        AND g.email IS NOT NULL AND g.email <> ''
    ");
  } catch (Exception $e) {}
  try {
    $pdo->exec("
      UPDATE rooms r
      JOIN users u ON r.owner_email = u.email
      SET r.owner_id = u.id
      WHERE (r.owner_id IS NULL OR r.owner_id = '')
        AND r.owner_email IS NOT NULL AND r.owner_email <> ''
    ");
  } catch (Exception $e) {}
  try {
    $pdo->exec("
      UPDATE room_players rp
      JOIN users u ON rp.email = u.email
      SET rp.user_id = u.id
      WHERE (rp.user_id IS NULL OR rp.user_id = '')
        AND rp.email IS NOT NULL AND rp.email <> ''
    ");
  } catch (Exception $e) {}
  try {
    $pdo->exec("
      UPDATE room_events re
      JOIN users u ON re.email = u.email
      SET re.user_id = u.id
      WHERE (re.user_id IS NULL OR re.user_id = '')
        AND re.email IS NOT NULL AND re.email <> ''
    ");
  } catch (Exception $e) {}
  try {
    $pdo->exec("
      UPDATE daily_scores ds
      JOIN users u ON ds.email = u.email
      SET ds.user_id = u.id
      WHERE (ds.user_id IS NULL OR ds.user_id = '')
        AND ds.email IS NOT NULL AND ds.email <> ''
    ");
  } catch (Exception $e) {}

  // Ensure daily unique key is on (challenge_date, user_id)
  try { $pdo->exec("ALTER TABLE daily_scores DROP INDEX uq_daily_user"); } catch (Exception $e) {}
  ensure_index($pdo, 'daily_scores', 'uq_daily_user', 'UNIQUE KEY uq_daily_user (challenge_date, user_id)');
  ensure_foreign_key($pdo, 'daily_scores', 'fk_daily_scores_user', 'CONSTRAINT fk_daily_scores_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');

  // Ensure new FKs and indexes
  ensure_index($pdo, 'games', 'idx_games_user_created', 'INDEX idx_games_user_created (user_id, created_at)');
  ensure_foreign_key($pdo, 'games', 'fk_games_user', 'CONSTRAINT fk_games_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');

  ensure_index($pdo, 'rooms', 'idx_rooms_owner', 'KEY idx_rooms_owner (owner_id)');
  ensure_foreign_key($pdo, 'rooms', 'fk_rooms_owner', 'CONSTRAINT fk_rooms_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');

  ensure_index($pdo, 'room_players', 'idx_room_players_user', 'KEY idx_room_players_user (user_id)');
  ensure_foreign_key($pdo, 'room_players', 'fk_room_players_user', 'CONSTRAINT fk_room_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE');

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
  ensure_column($pdo, 'daily_scores', 'user_id', 'CHAR(36) NULL');
  try {
    $pdo->exec("
      UPDATE daily_scores ds
      JOIN users u ON ds.email = u.email
      SET ds.user_id = u.id
      WHERE (ds.user_id IS NULL OR ds.user_id = '')
        AND ds.email IS NOT NULL AND ds.email <> ''
    ");
  } catch (Exception $e) {}

  // Room mode tables (idempotent)
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS rooms (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      guid CHAR(36) NOT NULL,
      name VARCHAR(80) NULL,
      owner_id CHAR(36) NOT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'waiting',
      rounds_total INT NOT NULL,
      current_round INT NOT NULL DEFAULT 0,
      max_players INT NOT NULL DEFAULT 25,
      created_at DATETIME(3) NOT NULL,
      started_at DATETIME(3) NULL,
      finished_at DATETIME(3) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_rooms_guid (guid),
      KEY idx_rooms_owner (owner_id),
      KEY idx_rooms_status (status),
      CONSTRAINT fk_rooms_owner FOREIGN KEY (owner_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  ensure_column($pdo, 'rooms', 'owner_id', 'CHAR(36) NULL');
  ensure_column($pdo, 'rooms', 'name', 'VARCHAR(80) NULL');

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS room_players (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      room_id BIGINT UNSIGNED NOT NULL,
      user_id CHAR(36) NOT NULL,
      joined_at DATETIME(3) NOT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'active',
      eliminated_round INT NULL,
      score INT NOT NULL DEFAULT 0,
      correct INT NOT NULL DEFAULT 0,
      last_active DATETIME(3) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_room_player (room_id, user_id),
      KEY idx_room_players_room (room_id),
      KEY idx_room_players_user (user_id),
      CONSTRAINT fk_room_players_room FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
      CONSTRAINT fk_room_players_user FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  ensure_column($pdo, 'room_players', 'user_id', 'CHAR(36) NULL');

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS room_rounds (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      room_id BIGINT UNSIGNED NOT NULL,
      round_index INT NOT NULL,
      question_json LONGTEXT NOT NULL,
      started_at DATETIME(3) NOT NULL,
      ended_at DATETIME(3) NULL,
      PRIMARY KEY (id),
      UNIQUE KEY uq_room_round (room_id, round_index),
      KEY idx_room_rounds_room (room_id),
      CONSTRAINT fk_room_rounds_room FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  $pdo->exec("
    CREATE TABLE IF NOT EXISTS room_events (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      room_id BIGINT UNSIGNED NOT NULL,
      round_index INT NOT NULL,
      user_id CHAR(36) NOT NULL,
      event_type VARCHAR(32) NOT NULL,
      payload_json LONGTEXT NOT NULL,
      created_at DATETIME(3) NOT NULL,
      PRIMARY KEY (id),
      KEY idx_room_events_room (room_id),
      KEY idx_room_events_type (event_type),
      CONSTRAINT fk_room_events_room FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
  ensure_column($pdo, 'room_events', 'user_id', 'CHAR(36) NULL');
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

function user_guid(){
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function get_user_by_id($userId){
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $userId]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function get_user_by_email($email){
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id LIMIT 1");
  $stmt->execute([':email' => $email]);
  $row = $stmt->fetch();
  return $row ?: null;
}

function user_display_name_from_row($row){
  $username = trim((string)((isset($row['username']) ? $row['username'] : '')));
  if ($username !== '') return $username;
  $email = trim((string)((isset($row['email']) ? $row['email'] : '')));
  return $email !== '' ? $email : 'Guest';
}

function user_display_name($userId){
  $row = get_user_by_id($userId);
  return $row ? user_display_name_from_row($row) : 'Guest';
}

function touch_user_login($userId){
  $pdo = db();
  $now = now_utc_mysql();
  $pdo->prepare("UPDATE users SET last_login = :t WHERE id = :id")
      ->execute([':t' => $now, ':id' => $userId]);
}

function ensure_user_by_email($email){
  $pdo = db();
  $now = now_utc_mysql();
  $email = strtolower(trim($email));

  $existing = get_user_by_email($email);
  if ($existing) {
    $pdo->prepare("UPDATE users SET last_login = :t WHERE id = :id")
        ->execute([':t' => $now, ':id' => $existing['id']]);
    return $existing;
  }

  $id = user_guid();
  $stmt = $pdo->prepare("
    INSERT INTO users (id, email, created_at, last_login)
    VALUES (:id, :email, :created_at, :last_login)
  ");
  $stmt->execute([
    ':id' => $id,
    ':email' => $email,
    ':created_at' => $now,
    ':last_login' => $now,
  ]);
  return get_user_by_id($id) ?: ['id' => $id, 'email' => $email];
}

function ensure_local_user($username, ?$userId = null){
  $pdo = db();
  $now = now_utc_mysql();
  $username = trim($username);

  if ($userId) {
    $existing = get_user_by_id($userId);
    if ($existing) {
      $pdo->prepare("
        UPDATE users
        SET username = :username, last_login = :t
        WHERE id = :id
      ")->execute([':username' => $username, ':t' => $now, ':id' => $userId]);
      $existing['username'] = $username;
      return $existing;
    }
  }

  $id = user_guid();
  $stmt = $pdo->prepare("
    INSERT INTO users (id, username, created_at, last_login)
    VALUES (:id, :username, :created_at, :last_login)
  ");
  $stmt->execute([
    ':id' => $id,
    ':username' => $username,
    ':created_at' => $now,
    ':last_login' => $now,
  ]);
  return get_user_by_id($id) ?: ['id' => $id, 'username' => $username];
}

function merge_user_accounts($fromUserId, $toUserId){
  if ($fromUserId === $toUserId) return;
  $pdo = db();
  try {
    $pdo->prepare("
      UPDATE users u
      JOIN users f ON f.id = :fromId
      SET
        u.total_plays = u.total_plays + f.total_plays,
        u.total_wins = u.total_wins + f.total_wins,
        u.total_correct = u.total_correct + f.total_correct,
        u.best_level = CASE WHEN f.best_level > u.best_level THEN f.best_level ELSE u.best_level END
      WHERE u.id = :toId
    ")->execute([':fromId' => $fromUserId, ':toId' => $toUserId]);
  } catch (Exception $e) {}
  $tables = [
    ['games', 'user_id'],
    ['rooms', 'owner_id'],
    ['room_players', 'user_id'],
    ['room_events', 'user_id'],
    ['daily_scores', 'user_id'],
  ];
  foreach ($tables as $t) {
    try {
      $pdo->prepare("UPDATE {$t[0]} SET {$t[1]} = :toId WHERE {$t[1]} = :fromId")
          ->execute([':toId' => $toUserId, ':fromId' => $fromUserId]);
    } catch (Exception $e) {}
  }
  try {
    $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $fromUserId]);
  } catch (Exception $e) {}
}

function link_user_email($userId, $email){
  $pdo = db();
  $now = now_utc_mysql();
  $email = strtolower(trim($email));

  $existingByEmail = get_user_by_email($email);
  if ($existingByEmail && $existingByEmail['id'] !== $userId) {
    merge_user_accounts($userId, $existingByEmail['id']);
    $pdo->prepare("UPDATE users SET last_login = :t WHERE id = :id")
        ->execute([':t' => $now, ':id' => $existingByEmail['id']]);
    return get_user_by_id($existingByEmail['id']) ?: $existingByEmail;
  }

  $pdo->prepare("UPDATE users SET email = :email, last_login = :t WHERE id = :id")
      ->execute([':email' => $email, ':t' => $now, ':id' => $userId]);
  return get_user_by_id($userId) ?: ['id' => $userId, 'email' => $email];
}

function get_user_stats($userId) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
  $stmt->execute([':id' => $userId]);
  return $stmt->fetch() ?: [];
}

/**
 * Full game payload kaydı:
 * payload = {
 *   reachedLevel, correct, won, startedAt, endedAt, durationMs,
 *   rounds: [{ level, targetColor, gridColors[9|16|25], pickedColor|null, responseMs, isCorrect }]
 * }
 */
 
 function record_full_game($userId, $gamePayload) {
  $pdo = db();

  // FK kırılmasın
  touch_user_login($userId);

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
  if ($reached > 50) $reached = 50;
  if ($totalCorrect < 0) $totalCorrect = 0;
  if ($durationMs < 0) $durationMs = 0;

  $pdo->beginTransaction();
  try {
    // -------------------------
    // 1) Insert game
    // -------------------------
    $sqlGame = "
      INSERT INTO games
        (user_id, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country)
      VALUES
        (:user_id, :created_at, :finished_at, :duration_ms, :reached_level, :total_correct, :score, :won, :language, :country)
    ";
    if (trim($sqlGame) === '') throw new RuntimeException('sqlGame is empty');

    $stmtGame = $pdo->prepare($sqlGame);
    $stmtGame->execute([
      ':user_id' => $userId,
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

        if ($level < 1 || $level > 50) continue;
        if ($target === '') continue;
        if (!is_array($grid) || !in_array(count($grid), [9,16,25], true)) continue;

        $gridVals = array_values($grid);
        if (count(array_unique($gridVals)) !== count($gridVals)) continue;

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
      WHERE id = :user_id
    ";
    if (trim($sqlAgg) === '') throw new RuntimeException('sqlAgg is empty');

    $stmtAgg = $pdo->prepare($sqlAgg);
    $stmtAgg->execute([
      ':win_inc' => $won ? 1 : 0,
      ':lvl1' => $reached,
      ':lvl2' => $reached,
      ':correct' => $totalCorrect,
      ':user_id' => $userId,
    ]);

    $pdo->commit();
    return $gameId;

  } catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
  }
}


function list_games($userId, $limit = 100) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT id, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
    FROM games
    WHERE user_id = :user_id
    ORDER BY id DESC
    LIMIT :lim
  ");
  $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll() ?: [];
}

function get_game($userId, $gameId) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT id, user_id, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
    FROM games
    WHERE id = :id AND user_id = :user_id
    LIMIT 1
  ");
  $stmt->execute([':id' => $gameId, ':user_id' => $userId]);
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
  if ($level > 50) $level = 50;

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
  if ($reached > 50) $reached = 50;

  $rounds = isset($payload['rounds']) && is_array($payload['rounds']) ? $payload['rounds'] : [];
  $sumRatio = 0.0;
  $count = 0;

  foreach ($rounds as $r) {
    if (!is_array($r)) continue;
    $level = (int)(isset($r['level']) ? $r['level'] : 0);
    if ($level < 1 || $level > 50) continue;

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
  $levelFactor = $reached / 50;

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
    user_id CHAR(36) NOT NULL,
    score INT NOT NULL DEFAULT 0,               -- total_correct or custom scoring
    reached_level INT NOT NULL DEFAULT 0,
    total_correct INT NOT NULL DEFAULT 0,
    duration_ms INT NOT NULL DEFAULT 0,
    language VARCHAR(16) NULL,
    country VARCHAR(2) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_daily_user (challenge_date, user_id),
    KEY idx_daily_date_score (challenge_date, score),
    KEY idx_daily_country_date (country, challenge_date),
    CONSTRAINT fk_daily_scores_user FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE ON UPDATE CASCADE
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
      (challenge_date, user_id, score, reached_level, total_correct, duration_ms, language, country)
    VALUES
      (:challenge_date, :user_id, :score, :reached_level, :total_correct, :duration_ms, :language, :country)
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
    ':user_id'        => $row['user_id'],
    ':score'          => (int)$row['score'],
    ':reached_level'  => (int)$row['reached_level'],
    ':total_correct'  => (int)$row['total_correct'],
    ':duration_ms'    => (int)$row['duration_ms'],
    ':language'       => (isset($row['language']) ? $row['language'] : null),
    ':country'        => (isset($row['country']) ? $row['country'] : null),
  ]);
}

/** Has user already played today (UTC date)? */
function has_played_daily($userId, $challengeDate) {
  $pdo = db();
  $st = $pdo->prepare("SELECT 1 FROM daily_scores WHERE challenge_date=:d AND user_id=:u LIMIT 1");
  $st->execute([':d'=>$challengeDate, ':u'=>$userId]);
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
      SELECT ds.user_id, u.username, u.email, ds.score, ds.reached_level, ds.total_correct, ds.duration_ms, ds.language, ds.country, ds.created_at
      FROM daily_scores ds
      JOIN users u ON u.id = ds.user_id
      WHERE ds.challenge_date=:d AND ds.country=:c
      ORDER BY ds.score DESC, ds.reached_level DESC, ds.duration_ms ASC, ds.created_at ASC
      LIMIT {$limit}
    ");
    $st->execute([':d'=>$challengeDate, ':c'=>$country]);
  } else {
    $st = $pdo->prepare("
      SELECT ds.user_id, u.username, u.email, ds.score, ds.reached_level, ds.total_correct, ds.duration_ms, ds.language, ds.country, ds.created_at
      FROM daily_scores ds
      JOIN users u ON u.id = ds.user_id
      WHERE ds.challenge_date=:d
      ORDER BY ds.score DESC, ds.reached_level DESC, ds.duration_ms ASC, ds.created_at ASC
      LIMIT {$limit}
    ");
    $st->execute([':d'=>$challengeDate]);
  }
  return $st->fetchAll();
}

/* ================================
   Room Mode (Realtime) helpers
   ================================ */

function cleanup_old_rooms($pdo = null) {
  $pdo = $pdo ?: db();
  $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->modify('-30 days')
    ->format('Y-m-d H:i:s.v');
  $pdo->prepare("DELETE FROM rooms WHERE created_at < :cutoff")->execute([':cutoff' => $cutoff]);
}

function room_guid(){
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function create_room($ownerId, $roundsTotal, $name = '') {
  $pdo = db();
  cleanup_old_rooms($pdo);
  touch_user_login($ownerId);

  $rounds = max(1, min(50, (int)$roundsTotal));
  $name = trim((string)$name);
  if ($name !== '') {
    $name = mb_substr($name, 0, 80, 'UTF-8');
  }
  $guid = room_guid();

  $stmt = $pdo->prepare("
    INSERT INTO rooms (guid, name, owner_id, rounds_total, created_at)
    VALUES (:guid, :name, :owner, :rounds, :created_at)
  ");
  $stmt->execute([
    ':guid' => $guid,
    ':name' => ($name !== '' ? $name : null),
    ':owner' => $ownerId,
    ':rounds' => $rounds,
    ':created_at' => now_utc_mysql(),
  ]);
  $roomId = (int)$pdo->lastInsertId();

  $stmtP = $pdo->prepare("
    INSERT INTO room_players (room_id, user_id, joined_at, status, last_active)
    VALUES (:room_id, :user_id, :joined_at, 'active', :last_active)
  ");
  $stmtP->execute([
    ':room_id' => $roomId,
    ':user_id' => $ownerId,
    ':joined_at' => now_utc_mysql(),
    ':last_active' => now_utc_mysql(),
  ]);

  return ['id' => $roomId, 'guid' => $guid];
}

function get_room_by_guid($guid) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT * FROM rooms WHERE guid = :g LIMIT 1");
  $stmt->execute([':g' => $guid]);
  return $stmt->fetch() ?: null;
}

function list_user_rooms($userId, $limit = 100) {
  $pdo = db();
  cleanup_old_rooms($pdo);
  $stmt = $pdo->prepare("
    SELECT r.*
    FROM rooms r
    JOIN room_players p ON p.room_id = r.id
    WHERE p.user_id = :user_id
    ORDER BY r.id DESC
    LIMIT :lim
  ");
  $stmt->bindValue(':user_id', $userId, PDO::PARAM_STR);
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll() ?: [];
}

function list_room_players($roomId) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT rp.user_id, rp.status, rp.eliminated_round, rp.score, rp.correct, rp.joined_at, u.username, u.email
    FROM room_players rp
    JOIN users u ON u.id = rp.user_id
    WHERE rp.room_id = :rid
    ORDER BY rp.score DESC, rp.correct DESC, rp.joined_at ASC
  ");
  $stmt->execute([':rid' => $roomId]);
  $rows = $stmt->fetchAll() ?: [];
  foreach ($rows as &$r) {
    $r['display_name'] = user_display_name_from_row($r);
  }
  return $rows;
}

function room_winner_name($roomId) {
  $pdo = db();
  $stmt = $pdo->prepare("
    SELECT u.username, u.email
    FROM room_players rp
    JOIN users u ON u.id = rp.user_id
    WHERE rp.room_id = :rid
    ORDER BY rp.score DESC, rp.correct DESC, rp.joined_at ASC
    LIMIT 1
  ");
  $stmt->execute([':rid' => $roomId]);
  $row = $stmt->fetch();
  return $row ? user_display_name_from_row($row) : null;
}

function add_room_player($roomId, $userId) {
  $pdo = db();
  touch_user_login($userId);

  $room = $pdo->prepare("SELECT max_players FROM rooms WHERE id = :id");
  $room->execute([':id' => $roomId]);
  $maxPlayers = (int)($room->fetchColumn() ?: 25);

  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM room_players WHERE room_id = :rid");
  $countStmt->execute([':rid' => $roomId]);
  $count = (int)$countStmt->fetchColumn();
  if ($count >= $maxPlayers) return false;

  $stmt = $pdo->prepare("
    INSERT INTO room_players (room_id, user_id, joined_at, status, last_active)
    VALUES (:room_id, :user_id, :joined_at, 'active', :last_active)
    ON DUPLICATE KEY UPDATE last_active = VALUES(last_active)
  ");
  $stmt->execute([
    ':room_id' => $roomId,
    ':user_id' => $userId,
    ':joined_at' => now_utc_mysql(),
    ':last_active' => now_utc_mysql(),
  ]);
  return true;
}

function set_room_started($roomId) {
  $pdo = db();
  $stmt = $pdo->prepare("UPDATE rooms SET status='active', started_at=:t WHERE id=:id AND status='waiting'");
  $stmt->execute([':t' => now_utc_mysql(), ':id' => $roomId]);
  return $stmt->rowCount() > 0;
}

function set_room_finished($roomId) {
  $pdo = db();
  $stmt = $pdo->prepare("UPDATE rooms SET status='finished', finished_at=:t WHERE id=:id");
  $stmt->execute([':t' => now_utc_mysql(), ':id' => $roomId]);
}

function grid_count_for_round($roundIndex, $roundsTotal) {
  $lvl = max(1, (int)$roundIndex);
  if ($lvl <= 20) return 9;
  if ($lvl <= 40) return 16;
  return 25;
}

function room_timing_for_round($roundIndex) {
  $lvl = max(1, (int)$roundIndex);
  $countdownMs = 3000;
  $answerMs = 5000;
  if ($lvl === 21 || $lvl === 41) {
    $showMs = 5000;
  } else {
    $showMs = (int)floor(3000 * pow(0.9, $lvl - 1));
    if ($showMs < 250) $showMs = 250;
  }
  return ['countdown_ms' => $countdownMs, 'show_ms' => $showMs, 'answer_ms' => $answerMs];
}

function room_generate_question($roundIndex, $roundsTotal) {
  $palette = [
    "#000000","#FFFFFF","#FF0000","#00FF00","#0000FF","#FFFF00","#00FFFF","#FF00FF",
    "#FFA500","#800080","#00FF7F","#1E90FF","#DC143C","#FFD700","#8A2BE2","#00CED1",
    "#FF1493","#7FFF00","#FF8C00","#20B2AA","#ADFF2F","#FF69B4","#40E0D0","#B22222","#6A5ACD"
  ];
  $count = grid_count_for_round($roundIndex, $roundsTotal);
  shuffle($palette);
  $grid = array_slice($palette, 0, $count);
  $target = $grid[array_rand($grid)];
  return ['target' => $target, 'grid' => $grid, 'gridCount' => $count];
}

function room_create_round($roomId, $roundIndex, $roundsTotal) {
  $pdo = db();
  $question = room_generate_question($roundIndex, $roundsTotal);
  $stmt = $pdo->prepare("
    INSERT INTO room_rounds (room_id, round_index, question_json, started_at)
    VALUES (:room_id, :round_index, :question_json, :started_at)
  ");
  $stmt->execute([
    ':room_id' => $roomId,
    ':round_index' => $roundIndex,
    ':question_json' => json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':started_at' => now_utc_mysql(),
  ]);
  $pdo->prepare("UPDATE rooms SET current_round = :r WHERE id = :id")->execute([':r'=>$roundIndex, ':id'=>$roomId]);
  return $question;
}

function room_round_question($roomId, $roundIndex) {
  $pdo = db();
  $stmt = $pdo->prepare("SELECT question_json FROM room_rounds WHERE room_id=:rid AND round_index=:r LIMIT 1");
  $stmt->execute([':rid'=>$roomId, ':r'=>$roundIndex]);
  $row = $stmt->fetchColumn();
  return $row ? json_decode($row, true) : null;
}

function room_end_round($roomId, $roundIndex) {
  $pdo = db();
  $stmt = $pdo->prepare("UPDATE room_rounds SET ended_at=:t WHERE room_id=:rid AND round_index=:r");
  $stmt->execute([':t'=>now_utc_mysql(), ':rid'=>$roomId, ':r'=>$roundIndex]);
}

function room_log_event($roomId, $roundIndex, $userId, $type, $payload) {
  $pdo = db();
  $stmt = $pdo->prepare("
    INSERT INTO room_events (room_id, round_index, user_id, event_type, payload_json, created_at)
    VALUES (:room_id, :round_index, :user_id, :event_type, :payload_json, :created_at)
  ");
  $stmt->execute([
    ':room_id'=>$roomId,
    ':round_index'=>$roundIndex,
    ':user_id'=>$userId,
    ':event_type'=>$type,
    ':payload_json'=>json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ':created_at'=>now_utc_mysql(),
  ]);
}

function room_mark_eliminated($roomId, $userId, $roundIndex) {
  $pdo = db();
  $stmt = $pdo->prepare("
    UPDATE room_players
    SET status='eliminated', eliminated_round=:r
    WHERE room_id=:rid AND user_id=:user_id
  ");
  $stmt->execute([':r'=>$roundIndex, ':rid'=>$roomId, ':user_id'=>$userId]);
}

function room_add_score($roomId, $userId, $scoreDelta, $correctDelta) {
  $pdo = db();
  $stmt = $pdo->prepare("
    UPDATE room_players
    SET score = score + :score, correct = correct + :correct, last_active=:t
    WHERE room_id=:rid AND user_id=:user_id
  ");
  $stmt->execute([
    ':score'=> (int)$scoreDelta,
    ':correct'=> (int)$correctDelta,
    ':t'=> now_utc_mysql(),
    ':rid'=> $roomId,
    ':user_id'=> $userId,
  ]);
}










