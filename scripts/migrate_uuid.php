<?php
// Usage: php scripts/migrate_uuid.php
// Migrates existing numeric id schema to UUID CHAR(36) primary keys.

require_once __DIR__ . '/../config.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

function debug_output(string $msg): void {
  $logPath = __DIR__ . '/migrate_uuid.log';
  @file_put_contents($logPath, $msg . PHP_EOL, FILE_APPEND);
  if (php_sapi_name() === 'cli') {
    fwrite(STDERR, $msg . PHP_EOL);
  } else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg . "\n";
  }
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  debug_output("PHP ERROR [$errno] $errstr in $errfile:$errline");
  return false;
});

register_shutdown_function(function () {
  $err = error_get_last();
  if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
    debug_output("PHP FATAL [{$err['type']}] {$err['message']} in {$err['file']}:{$err['line']}");
  }
});

function pdo_conn(): PDO {
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
  return new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
}

function uuid_v4(): string {
  $data = random_bytes(16);
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function exec_sql(PDO $pdo, string $sql): void {
  echo $sql . PHP_EOL;
  $pdo->exec($sql);
}

function table_exists(PDO $pdo, string $table): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
  ");
  $st->execute([':t' => $table]);
  return (int)$st->fetchColumn() > 0;
}

function column_info(PDO $pdo, string $table, string $col): ?array {
  $st = $pdo->prepare("
    SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c
    LIMIT 1
  ");
  $st->execute([':t' => $table, ':c' => $col]);
  $row = $st->fetch();
  return $row ?: null;
}

function column_exists(PDO $pdo, string $table, string $col): bool {
  return column_info($pdo, $table, $col) !== null;
}

function index_exists(PDO $pdo, string $table, string $indexName): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i
  ");
  $st->execute([':t' => $table, ':i' => $indexName]);
  return (int)$st->fetchColumn() > 0;
}

function drop_index_if_exists(PDO $pdo, string $table, string $indexName): void {
  if (index_exists($pdo, $table, $indexName)) {
    exec_sql($pdo, "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`");
  }
}

function constraint_exists(PDO $pdo, string $table, string $constraintName): bool {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c
  ");
  $st->execute([':t' => $table, ':c' => $constraintName]);
  return (int)$st->fetchColumn() > 0;
}

function drop_fk_if_exists(PDO $pdo, string $table, string $constraintName): void {
  if (constraint_exists($pdo, $table, $constraintName)) {
    exec_sql($pdo, "ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
  }
}

function drop_fks_referencing(PDO $pdo, string $referencedTable): void {
  $st = $pdo->prepare("
    SELECT CONSTRAINT_NAME, TABLE_NAME
    FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = :rt
  ");
  $st->execute([':rt' => $referencedTable]);
  $rows = $st->fetchAll() ?: [];
  foreach ($rows as $r) {
    $c = (string)$r['CONSTRAINT_NAME'];
    $t = (string)$r['TABLE_NAME'];
    if ($c !== '' && $t !== '') {
      exec_sql($pdo, "ALTER TABLE `{$t}` DROP FOREIGN KEY `{$c}`");
    }
  }
}

function add_column_if_missing(PDO $pdo, string $table, string $col, string $ddl): void {
  if (!column_exists($pdo, $table, $col)) {
    exec_sql($pdo, "ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$ddl}");
  }
}

function ensure_primary_key(PDO $pdo, string $table, string $col): void {
  $st = $pdo->prepare("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = 'PRIMARY'
  ");
  $st->execute([':t' => $table]);
  $hasPk = (int)$st->fetchColumn() > 0;
  if ($hasPk) {
    exec_sql($pdo, "ALTER TABLE `{$table}` DROP PRIMARY KEY");
  }
  exec_sql($pdo, "ALTER TABLE `{$table}` ADD PRIMARY KEY (`{$col}`)");
}

function rename_id_to_legacy_if_needed(PDO $pdo, string $table): void {
  $info = column_info($pdo, $table, 'id');
  if (!$info) return;
  $dataType = strtolower((string)$info['DATA_TYPE']);
  $len = (int)($info['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
  if ($dataType !== 'char' || $len !== 36) {
    if (!column_exists($pdo, $table, 'legacy_id')) {
      exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `id` `legacy_id` BIGINT UNSIGNED NOT NULL");
    }
  }
}

function rename_fk_to_legacy_if_needed(PDO $pdo, string $table, string $col): void {
  $info = column_info($pdo, $table, $col);
  if (!$info) return;
  $dataType = strtolower((string)$info['DATA_TYPE']);
  $len = (int)($info['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
  if ($dataType !== 'char' || $len !== 36) {
    $legacy = "legacy_{$col}";
    if (!column_exists($pdo, $table, $legacy)) {
      exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `{$col}` `{$legacy}` BIGINT UNSIGNED NOT NULL");
    }
  }
}

function add_fk_if_missing(PDO $pdo, string $table, string $constraintName, string $ddl): void {
  if (!constraint_exists($pdo, $table, $constraintName)) {
    exec_sql($pdo, "ALTER TABLE `{$table}` ADD {$ddl}");
  }
}

function warn_if_nulls(PDO $pdo, string $table, string $col): void {
  $st = $pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` IS NULL OR `{$col}` = ''");
  $count = (int)$st->fetchColumn();
  if ($count > 0) {
    echo "WARNING: {$table}.{$col} has {$count} empty values" . PHP_EOL;
  }
}

$pdo = pdo_conn();
echo "Starting UUID migration..." . PHP_EOL;
function join_id_column(PDO $pdo, string $table): string {
  return column_exists($pdo, $table, 'legacy_id') ? 'legacy_id' : 'id';
}

function drop_column_if_exists(PDO $pdo, string $table, string $col): void {
  if (column_exists($pdo, $table, $col)) {
    exec_sql($pdo, "ALTER TABLE `{$table}` DROP COLUMN `{$col}`");
  }
}

function ensure_uuid_id_from_temp(PDO $pdo, string $table, string $tmpCol = 'id_uuid'): void {
  if (!column_exists($pdo, $table, $tmpCol)) return;
  $idInfo = column_info($pdo, $table, 'id');
  if (!$idInfo) {
    exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `{$tmpCol}` `id` CHAR(36) NOT NULL");
    return;
  }
  $dataType = strtolower((string)$idInfo['DATA_TYPE']);
  $len = (int)($idInfo['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
  if ($dataType === 'char' && $len === 36) {
    drop_column_if_exists($pdo, $table, $tmpCol);
    return;
  }
  if (!column_exists($pdo, $table, 'legacy_id')) {
    exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `id` `legacy_id` BIGINT UNSIGNED NOT NULL");
  } else {
    drop_column_if_exists($pdo, $table, 'id');
  }
  exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `{$tmpCol}` `id` CHAR(36) NOT NULL");
}

function ensure_uuid_fk_from_temp(PDO $pdo, string $table, string $col, string $tmpCol): void {
  if (!column_exists($pdo, $table, $tmpCol)) return;
  $info = column_info($pdo, $table, $col);
  if ($info) {
    $dataType = strtolower((string)$info['DATA_TYPE']);
    $len = (int)($info['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
    if ($dataType === 'char' && $len === 36) {
      drop_column_if_exists($pdo, $table, $tmpCol);
      return;
    }
    $legacy = "legacy_{$col}";
    if (!column_exists($pdo, $table, $legacy)) {
      exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `{$col}` `{$legacy}` BIGINT UNSIGNED NOT NULL");
    } else {
      drop_column_if_exists($pdo, $table, $col);
    }
  }
  exec_sql($pdo, "ALTER TABLE `{$table}` CHANGE COLUMN `{$tmpCol}` `{$col}` CHAR(36) NOT NULL");
}

try {
  // users
  if (table_exists($pdo, 'users')) {
    add_column_if_missing($pdo, 'users', 'id', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `users` SET `id` = UUID() WHERE `id` IS NULL OR `id` = ''");
    exec_sql($pdo, "ALTER TABLE `users` MODIFY `id` CHAR(36) NOT NULL");
    if (!index_exists($pdo, 'users', 'uq_users_email')) {
      exec_sql($pdo, "ALTER TABLE `users` ADD UNIQUE KEY `uq_users_email` (`email`)");
    }
    ensure_primary_key($pdo, 'users', 'id');
  }

  // games
  if (table_exists($pdo, 'games')) {
    drop_fk_if_exists($pdo, 'games', 'fk_games_email');
    // Drop dependent FKs before altering games PK (names may vary)
    drop_fks_referencing($pdo, 'games');
    add_column_if_missing($pdo, 'games', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'games', 'user_id', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `games` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    exec_sql($pdo, "UPDATE `games` g JOIN `users` u ON u.email = g.email SET g.user_id = u.id WHERE g.user_id IS NULL OR g.user_id = ''");
    warn_if_nulls($pdo, 'games', 'user_id');
    rename_id_to_legacy_if_needed($pdo, 'games');
    ensure_uuid_id_from_temp($pdo, 'games', 'id_uuid');
    ensure_primary_key($pdo, 'games', 'id');
    if (!index_exists($pdo, 'games', 'idx_games_user_created')) {
      exec_sql($pdo, "ALTER TABLE `games` ADD INDEX `idx_games_user_created` (`user_id`, `created_at`)");
    }
    add_fk_if_missing(
      $pdo,
      'games',
      'fk_games_user',
      "CONSTRAINT `fk_games_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
  }

  // rounds
  if (table_exists($pdo, 'rounds')) {
    drop_fk_if_exists($pdo, 'rounds', 'fk_rounds_game');
    add_column_if_missing($pdo, 'rounds', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'rounds', 'game_id_uuid', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `rounds` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    $gamesJoinCol = join_id_column($pdo, 'games');
    exec_sql($pdo, "UPDATE `rounds` r JOIN `games` g ON g.`{$gamesJoinCol}` = r.game_id SET r.game_id_uuid = g.id WHERE r.game_id_uuid IS NULL OR r.game_id_uuid = ''");
    warn_if_nulls($pdo, 'rounds', 'game_id_uuid');
    rename_id_to_legacy_if_needed($pdo, 'rounds');
    rename_fk_to_legacy_if_needed($pdo, 'rounds', 'game_id');
    ensure_uuid_fk_from_temp($pdo, 'rounds', 'game_id', 'game_id_uuid');
    ensure_uuid_id_from_temp($pdo, 'rounds', 'id_uuid');
    ensure_primary_key($pdo, 'rounds', 'id');
    add_fk_if_missing(
      $pdo,
      'rounds',
      'fk_rounds_game',
      "CONSTRAINT `fk_rounds_game` FOREIGN KEY (`game_id`) REFERENCES `games`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
  }

  // rooms
if (table_exists($pdo, 'rooms')) {
  drop_fk_if_exists($pdo, 'rooms', 'fk_rooms_owner');
  drop_fks_referencing($pdo, 'rooms');
  add_column_if_missing($pdo, 'rooms', 'id_uuid', 'CHAR(36) NULL');
  add_column_if_missing($pdo, 'rooms', 'owner_id', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `rooms` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    exec_sql($pdo, "UPDATE `rooms` r JOIN `users` u ON u.email = r.owner_email SET r.owner_id = u.id WHERE r.owner_id IS NULL OR r.owner_id = ''");
    warn_if_nulls($pdo, 'rooms', 'owner_id');
    rename_id_to_legacy_if_needed($pdo, 'rooms');
    ensure_uuid_id_from_temp($pdo, 'rooms', 'id_uuid');
    ensure_primary_key($pdo, 'rooms', 'id');
    add_fk_if_missing(
      $pdo,
      'rooms',
      'fk_rooms_owner',
      "CONSTRAINT `fk_rooms_owner` FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
  }

  // room_players
  if (table_exists($pdo, 'room_players')) {
    drop_fk_if_exists($pdo, 'room_players', 'fk_room_players_room');
    drop_fk_if_exists($pdo, 'room_players', 'fk_room_players_email');
    add_column_if_missing($pdo, 'room_players', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'room_players', 'user_id', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'room_players', 'room_id_uuid', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `room_players` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    exec_sql($pdo, "UPDATE `room_players` p JOIN `users` u ON u.email = p.email SET p.user_id = u.id WHERE p.user_id IS NULL OR p.user_id = ''");
    $roomsJoinCol = join_id_column($pdo, 'rooms');
    exec_sql($pdo, "UPDATE `room_players` p JOIN `rooms` r ON r.`{$roomsJoinCol}` = p.room_id SET p.room_id_uuid = r.id WHERE p.room_id_uuid IS NULL OR p.room_id_uuid = ''");
    warn_if_nulls($pdo, 'room_players', 'user_id');
    warn_if_nulls($pdo, 'room_players', 'room_id_uuid');
    rename_id_to_legacy_if_needed($pdo, 'room_players');
    rename_fk_to_legacy_if_needed($pdo, 'room_players', 'room_id');
    ensure_uuid_fk_from_temp($pdo, 'room_players', 'room_id', 'room_id_uuid');
    ensure_uuid_id_from_temp($pdo, 'room_players', 'id_uuid');
    ensure_primary_key($pdo, 'room_players', 'id');
    drop_index_if_exists($pdo, 'room_players', 'uq_room_player');
    exec_sql($pdo, "ALTER TABLE `room_players` ADD UNIQUE KEY `uq_room_player` (`room_id`, `user_id`)");
    add_fk_if_missing(
      $pdo,
      'room_players',
      'fk_room_players_room',
      "CONSTRAINT `fk_room_players_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
    add_fk_if_missing(
      $pdo,
      'room_players',
      'fk_room_players_user',
      "CONSTRAINT `fk_room_players_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
  }

  // room_rounds
  if (table_exists($pdo, 'room_rounds')) {
    drop_fk_if_exists($pdo, 'room_rounds', 'fk_room_rounds_room');
    add_column_if_missing($pdo, 'room_rounds', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'room_rounds', 'room_id_uuid', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `room_rounds` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    $roomsJoinCol = join_id_column($pdo, 'rooms');
    exec_sql($pdo, "UPDATE `room_rounds` rr JOIN `rooms` r ON r.`{$roomsJoinCol}` = rr.room_id SET rr.room_id_uuid = r.id WHERE rr.room_id_uuid IS NULL OR rr.room_id_uuid = ''");
    warn_if_nulls($pdo, 'room_rounds', 'room_id_uuid');
    rename_id_to_legacy_if_needed($pdo, 'room_rounds');
    rename_fk_to_legacy_if_needed($pdo, 'room_rounds', 'room_id');
    ensure_uuid_fk_from_temp($pdo, 'room_rounds', 'room_id', 'room_id_uuid');
    ensure_uuid_id_from_temp($pdo, 'room_rounds', 'id_uuid');
    ensure_primary_key($pdo, 'room_rounds', 'id');
    add_fk_if_missing(
      $pdo,
      'room_rounds',
      'fk_room_rounds_room',
      "CONSTRAINT `fk_room_rounds_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
  }

  // room_events
  if (table_exists($pdo, 'room_events')) {
    drop_fk_if_exists($pdo, 'room_events', 'fk_room_events_room');
    add_column_if_missing($pdo, 'room_events', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'room_events', 'room_id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'room_events', 'user_id', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `room_events` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    $roomsJoinCol = join_id_column($pdo, 'rooms');
    exec_sql($pdo, "UPDATE `room_events` e JOIN `rooms` r ON r.`{$roomsJoinCol}` = e.room_id SET e.room_id_uuid = r.id WHERE e.room_id_uuid IS NULL OR e.room_id_uuid = ''");
    exec_sql($pdo, "UPDATE `room_events` e JOIN `users` u ON u.email = e.email SET e.user_id = u.id WHERE e.user_id IS NULL OR e.user_id = ''");
    warn_if_nulls($pdo, 'room_events', 'room_id_uuid');
    rename_id_to_legacy_if_needed($pdo, 'room_events');
    rename_fk_to_legacy_if_needed($pdo, 'room_events', 'room_id');
    ensure_uuid_fk_from_temp($pdo, 'room_events', 'room_id', 'room_id_uuid');
    ensure_uuid_id_from_temp($pdo, 'room_events', 'id_uuid');
    ensure_primary_key($pdo, 'room_events', 'id');
    add_fk_if_missing(
      $pdo,
      'room_events',
      'fk_room_events_room',
      "CONSTRAINT `fk_room_events_room` FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
    );
    add_fk_if_missing(
      $pdo,
      'room_events',
      'fk_room_events_user',
      "CONSTRAINT `fk_room_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE"
    );
  }

  // daily_scores (main)
  if (table_exists($pdo, 'daily_scores')) {
    $hasChallengeDate = column_exists($pdo, 'daily_scores', 'challenge_date');
    $hasDayUtc = column_exists($pdo, 'daily_scores', 'day_utc');
    if ($hasChallengeDate) {
      add_column_if_missing($pdo, 'daily_scores', 'id_uuid', 'CHAR(36) NULL');
      add_column_if_missing($pdo, 'daily_scores', 'user_id', 'CHAR(36) NULL');
      exec_sql($pdo, "UPDATE `daily_scores` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
      exec_sql($pdo, "UPDATE `daily_scores` d JOIN `users` u ON u.email = d.email SET d.user_id = u.id WHERE d.user_id IS NULL OR d.user_id = ''");
      warn_if_nulls($pdo, 'daily_scores', 'user_id');
      rename_id_to_legacy_if_needed($pdo, 'daily_scores');
      ensure_uuid_id_from_temp($pdo, 'daily_scores', 'id_uuid');
      ensure_primary_key($pdo, 'daily_scores', 'id');
      drop_index_if_exists($pdo, 'daily_scores', 'uq_daily_user');
      exec_sql($pdo, "ALTER TABLE `daily_scores` ADD UNIQUE KEY `uq_daily_user` (`challenge_date`, `user_id`)");
      add_fk_if_missing(
        $pdo,
        'daily_scores',
        'fk_daily_scores_user',
        "CONSTRAINT `fk_daily_scores_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE ON UPDATE CASCADE"
      );
    } elseif ($hasDayUtc) {
      add_column_if_missing($pdo, 'daily_scores', 'id_uuid', 'CHAR(36) NULL');
      add_column_if_missing($pdo, 'daily_scores', 'user_id', 'CHAR(36) NULL');
      exec_sql($pdo, "UPDATE `daily_scores` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
      exec_sql($pdo, "UPDATE `daily_scores` d JOIN `users` u ON u.email = d.user_email SET d.user_id = u.id WHERE d.user_id IS NULL OR d.user_id = ''");
      rename_id_to_legacy_if_needed($pdo, 'daily_scores');
      ensure_uuid_id_from_temp($pdo, 'daily_scores', 'id_uuid');
      ensure_primary_key($pdo, 'daily_scores', 'id');
      drop_index_if_exists($pdo, 'daily_scores', 'uq_daily_user');
      exec_sql($pdo, "ALTER TABLE `daily_scores` ADD UNIQUE KEY `uq_daily_user` (`day_utc`, `user_id`)");
    }
  }

  // daily_rounds (recordx)
  if (table_exists($pdo, 'daily_rounds')) {
    add_column_if_missing($pdo, 'daily_rounds', 'id_uuid', 'CHAR(36) NULL');
    add_column_if_missing($pdo, 'daily_rounds', 'daily_score_id_uuid', 'CHAR(36) NULL');
    exec_sql($pdo, "UPDATE `daily_rounds` SET `id_uuid` = UUID() WHERE `id_uuid` IS NULL OR `id_uuid` = ''");
    $dailyJoinCol = join_id_column($pdo, 'daily_scores');
    exec_sql($pdo, "UPDATE `daily_rounds` r JOIN `daily_scores` s ON s.`{$dailyJoinCol}` = r.daily_score_id SET r.daily_score_id_uuid = s.id WHERE r.daily_score_id_uuid IS NULL OR r.daily_score_id_uuid = ''");
    rename_id_to_legacy_if_needed($pdo, 'daily_rounds');
    rename_fk_to_legacy_if_needed($pdo, 'daily_rounds', 'daily_score_id');
    ensure_uuid_fk_from_temp($pdo, 'daily_rounds', 'daily_score_id', 'daily_score_id_uuid');
    ensure_uuid_id_from_temp($pdo, 'daily_rounds', 'id_uuid');
    ensure_primary_key($pdo, 'daily_rounds', 'id');
    add_fk_if_missing(
      $pdo,
      'daily_rounds',
      'fk_daily_rounds_score',
      "CONSTRAINT `fk_daily_rounds_score` FOREIGN KEY (`daily_score_id`) REFERENCES `daily_scores`(`id`) ON DELETE CASCADE"
    );
  }

  echo "Migration complete." . PHP_EOL;
} catch (Throwable $e) {
  debug_output("ERROR: " . $e->getMessage());
  debug_output("TYPE: " . get_class($e));
  debug_output("FILE: " . $e->getFile() . ":" . $e->getLine());
  debug_output("TRACE:\n" . $e->getTraceAsString());
  exit(1);
}
