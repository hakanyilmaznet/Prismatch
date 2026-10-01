<?php
declare(strict_types=1);

// Disable Pusher during tests
define('PUSHER_DISABLE', true);

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../src/autoload.php';
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../i18n.php';

use Prismatch\Core\Database;

$rawInput = file_get_contents('php://stdin');
$config = json_decode($rawInput ?: '{}', true) ?: [];

$script = (string)($config['script'] ?? '');
if (!$script || !file_exists($script)) {
    echo json_encode(['error' => 'Script not found: ' . $script]);
    exit(1);
}

// Setup environment
$_SERVER['REQUEST_METHOD'] = strtoupper((string)($config['method'] ?? 'GET'));
$_GET = (array)($config['get'] ?? []);
$_POST = (array)($config['post'] ?? []);
$_SESSION = (array)($config['session'] ?? []);
if (isset($config['server']) && is_array($config['server'])) {
    foreach ($config['server'] as $k => $v) {
        $_SERVER[$k] = $v;
    }
}

// Override php://input simulation if body is provided
$body = $config['body'] ?? '';
if (is_array($body)) {
    $body = json_encode($body);
}
if ($body !== '') {
    stream_wrapper_unregister('php');
    class MockPhpStream {
        public $context;
        private static string $data = '';
        private int $position = 0;

        public static function setData(string $data): void {
            self::$data = $data;
        }

        public function stream_open($path, $mode, $options, &$opened_path): bool {
            $this->position = 0;
            return true;
        }

        public function stream_read($count): string {
            $ret = substr(self::$data, $this->position, $count);
            $this->position += strlen($ret);
            return $ret;
        }

        public function stream_eof(): bool {
            return $this->position >= strlen(self::$data);
        }

        public function stream_stat(): array {
            return ['size' => strlen(self::$data)];
        }
    }
    MockPhpStream::setData((string)$body);
    stream_wrapper_register('php', MockPhpStream::class);
}

// SQLite test in-memory PDO if requested
class TestSqlitePdo extends PDO {
    private function rewriteQuery(string $q): string {
        // Rewrite MySQL ON DUPLICATE KEY UPDATE to SQLite ON CONFLICT
        $q = preg_replace(
            '/ON DUPLICATE KEY UPDATE\s+last_login\s*=\s*VALUES\(last_login\)/i',
            'ON CONFLICT(email) DO UPDATE SET last_login = excluded.last_login',
            $q
        );
        $q = preg_replace(
            '/ON DUPLICATE KEY UPDATE\s+status\s*=\s*VALUES\(status\)[^;]*/i',
            'ON CONFLICT(room_id, user_id) DO UPDATE SET last_active = excluded.last_active',
            $q
        );
        $q = preg_replace(
            '/AND NOT\s*\(\s*\(.*?DATE_SUB.*?\)\s*\)/is',
            '',
            $q
        );
        return $q;
    }

    #[\ReturnTypeWillChange]
    public function prepare(string $query, array $options = []): \PDOStatement|false {
        if (stripos($query, 'SHOW TABLES LIKE') !== false) {
            return parent::prepare("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE :tbl", $options);
        }
        $rw = $this->rewriteQuery($query);
        return parent::prepare($rw, $options);
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false {
        if (preg_match('/SHOW COLUMNS FROM\s+(\w+)/i', $query, $m)) {
            $table = $m[1];
            $stmt = parent::query("PRAGMA table_info({$table})");
            $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            parent::exec("DROP TABLE IF EXISTS _temp_cols; CREATE TEMP TABLE _temp_cols (Field TEXT);");
            $ins = parent::prepare("INSERT INTO _temp_cols (Field) VALUES (:f)");
            foreach ($cols as $c) {
                $ins->execute([':f' => $c['name']]);
            }
            return parent::query("SELECT Field FROM _temp_cols");
        }

        $rw = $this->rewriteQuery($query);
        if ($fetchMode !== null) {
            return parent::query($rw, $fetchMode, ...$fetchModeArgs);
        }
        return parent::query($rw);
    }
}

if (!empty($config['use_sqlite'])) {
    $pdo = new TestSqlitePdo('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->sqliteCreateFunction('GREATEST', fn(...$args) => max($args));
    $pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'));

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            email TEXT UNIQUE,
            created_at TEXT,
            last_login TEXT,
            total_plays INTEGER DEFAULT 0,
            total_wins INTEGER DEFAULT 0,
            best_level INTEGER DEFAULT 0,
            total_correct INTEGER DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS rooms (
            id TEXT PRIMARY KEY,
            guid TEXT UNIQUE,
            name TEXT,
            owner_id TEXT,
            owner_email TEXT,
            status TEXT,
            rounds_total INTEGER,
            current_round INTEGER,
            max_players INTEGER DEFAULT 25,
            is_private INTEGER,
            game_mode TEXT,
            created_at TEXT,
            updated_at TEXT,
            finished_at TEXT
        );
        CREATE TABLE IF NOT EXISTS room_players (
            id TEXT PRIMARY KEY,
            room_id TEXT,
            user_id TEXT,
            email TEXT,
            status TEXT,
            score INTEGER DEFAULT 0,
            correct INTEGER DEFAULT 0,
            is_online INTEGER DEFAULT 1,
            joined_at TEXT,
            last_active TEXT,
            eliminated_round INTEGER,
            UNIQUE(room_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS room_rounds (
            id TEXT PRIMARY KEY,
            room_id TEXT,
            round_index INTEGER,
            target_color TEXT,
            grid_colors_json TEXT,
            status TEXT,
            started_at TEXT,
            ended_at TEXT,
            question_json TEXT,
            created_at TEXT
        );
        CREATE TABLE IF NOT EXISTS room_events (
            id TEXT PRIMARY KEY,
            room_id TEXT,
            round_index INTEGER,
            user_id TEXT,
            email TEXT,
            event_type TEXT,
            payload_json TEXT,
            created_at TEXT
        );
        CREATE TABLE IF NOT EXISTS daily_scores (
            id TEXT PRIMARY KEY,
            day_utc TEXT,
            challenge_date TEXT,
            user_id TEXT,
            user_email TEXT,
            email TEXT,
            anon_id TEXT,
            game_mode TEXT,
            score INTEGER DEFAULT 0,
            reached_level INTEGER DEFAULT 0,
            correct_count INTEGER DEFAULT 0,
            total_correct INTEGER DEFAULT 0,
            duration_ms INTEGER DEFAULT 0,
            won INTEGER DEFAULT 0,
            language TEXT,
            country TEXT,
            created_at TEXT
        );
        CREATE TABLE IF NOT EXISTS daily_rounds (
            id TEXT PRIMARY KEY,
            daily_score_id TEXT,
            score_id TEXT,
            stage INTEGER,
            level INTEGER,
            target_color TEXT,
            picked_color TEXT,
            response_ms INTEGER,
            is_correct INTEGER,
            grid_json TEXT,
            created_at TEXT
        );
        CREATE TABLE IF NOT EXISTS games (
            id TEXT PRIMARY KEY,
            user_id TEXT,
            email TEXT,
            created_at TEXT,
            finished_at TEXT,
            duration_ms INTEGER,
            reached_level INTEGER,
            total_correct INTEGER,
            score INTEGER,
            won INTEGER,
            language TEXT,
            country TEXT,
            game_mode TEXT
        );
        CREATE TABLE IF NOT EXISTS rounds (
            id TEXT PRIMARY KEY,
            game_id TEXT,
            level INTEGER,
            target_color TEXT,
            grid_colors_json TEXT,
            picked_color TEXT,
            response_ms INTEGER,
            is_correct INTEGER,
            created_at TEXT
        );
    ");

    // Insert seeds if provided
    if (!empty($config['seeds']) && is_array($config['seeds'])) {
        foreach ($config['seeds'] as $table => $rows) {
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $ph = array_map(fn($c) => ':' . $c, $cols);
                $stmt = $pdo->prepare(sprintf("INSERT INTO %s (%s) VALUES (%s)", $table, implode(', ', $cols), implode(', ', $ph)));
                $params = [];
                foreach ($row as $k => $v) {
                    $params[':' . $k] = $v;
                }
                $stmt->execute($params);
            }
        }
    }

    Database::setConnection($pdo);
}

ob_start();

register_shutdown_function(function () {
    $output = ob_get_contents();
    @ob_end_clean();
    $statusCode = http_response_code() ?: 200;
    $parsedJson = json_decode($output, true);

    $result = [
        'status' => $statusCode,
        'body' => $output,
        'json' => $parsedJson,
        'session' => $_SESSION,
    ];

    echo "\n---API_RESULT_START---\n" . json_encode($result) . "\n---API_RESULT_END---\n";
});

// Run script
require $script;
