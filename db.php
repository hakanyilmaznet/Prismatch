<?php
declare(strict_types=1);

/**
 * db.php - Database Facade
 * Provides backward-compatible procedural API wrapping the SOLID OOP architecture in src/
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/autoload.php';

use Prismatch\Core\Database;
use Prismatch\Core\SchemaManager;
use Prismatch\Repositories\UserRepository;
use Prismatch\Repositories\GameRepository;
use Prismatch\Repositories\DailyRepository;
use Prismatch\Repositories\RoomRepository;
use Prismatch\Services\RoomGameService;

if (!function_exists('mb_chr')) {
    function mb_chr($code, $encoding = 'UTF-8') {
        return iconv('UCS-4LE', $encoding, pack('V', (int)$code));
    }
}

function db(): PDO {
    return Database::getConnection();
}

function uuid_v4(): string {
    return Database::generateUuid();
}

function now_utc_mysql(): string {
    return Database::nowUtc();
}

function iso_to_mysql_datetime(string $iso): string {
    return Database::isoToUtc($iso);
}

// --- User Operations ---

function upsert_user_login(string $email): void {
    (new UserRepository())->upsertLogin($email);
}

function find_user_by_email(string $email): ?array {
    return (new UserRepository())->findByEmail($email);
}

function find_user_by_id(string $id): ?array {
    return (new UserRepository())->findById($id);
}

function ensure_user_by_email(string $email): array {
    return (new UserRepository())->ensureByEmail($email);
}

function ensure_local_user(string $username): array {
    $email = str_contains($username, '@') ? $username : $username . '@local.player';
    return (new UserRepository())->ensureByEmail($email);
}

function get_user_stats(string $email): array {
    return (new UserRepository())->getStats($email);
}

function user_display_name(string $userId): string {
    return (new UserRepository())->getDisplayName($userId);
}

function user_display_name_from_row(array $userRow): string {
    return (new UserRepository())->formatDisplayName($userRow);
}

// --- Single Player Games ---

function record_full_game(string $userId, string $email, array $payload): ?string {
    return (new GameRepository())->recordFullGame($userId, $email, $payload);
}

function list_user_games(string $userId, int $limit = 50): array {
    return (new GameRepository())->listGamesByUser($userId, $limit);
}

function list_games(string $userId, int $limit = 50): array {
    return list_user_games($userId, $limit);
}

function get_game_details(string $gameId, string $userId): ?array {
    return (new GameRepository())->getGameDetails($gameId, $userId);
}

function get_game(string $userId, string $gameId): ?array {
    return get_game_details($gameId, $userId);
}

function list_rounds(string $gameId): array {
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

// --- Daily Challenge & Geo Helpers ---

function cf_country(): ?string {
    $cc = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? $_SERVER['HTTP_CF_IPCOUNTRY'] : '';
    $cc = strtoupper(trim((string)$cc));
    if ($cc === '' || $cc === 'XX' || !preg_match('/^[A-Z]{2}$/', $cc)) return null;
    return $cc;
}

function request_country(): ?string {
    return cf_country() ?: (isset($_POST['country']) && preg_match('/^[A-Z]{2}$/i', (string)$_POST['country']) ? strtoupper((string)$_POST['country']) : null);
}

function get_request_country(): ?string {
    return request_country();
}

function get_request_language(): string {
    if (!empty($_SESSION['lang'])) {
        return (string)$_SESSION['lang'];
    }
    if (function_exists('get_lang')) {
        return get_lang();
    }
    return 'en';
}

function country_flag(?string $cc): string {
    if (!$cc) return '🏳️';
    $cc = strtoupper(trim($cc));
    if (!preg_match('/^[A-Z]{2}$/', $cc)) return '🏳️';
    $a = ord($cc[0]) - 65 + 0x1F1E6;
    $b = ord($cc[1]) - 65 + 0x1F1E6;
    return mb_chr($a, 'UTF-8') . mb_chr($b, 'UTF-8');
}

function country_flag_icon_url(?string $cc): string {
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
    return isset($map[$cc]) ? 'flags/' . $map[$cc] : '';
}

function daily_target_show_ms_for_level(int $level): int {
    $level = max(1, min(25, $level));
    $ms = (int)floor(3000 * pow(0.9, $level - 1));
    return max(250, $ms);
}

function compute_daily_score(array $payload): int {
    $reached = (int)($payload['reached_level'] ?? $payload['reachedLevel'] ?? 0);
    $reached = max(1, min(25, $reached));
    $rounds = is_array($payload['rounds'] ?? null) ? $payload['rounds'] : [];
    $sumRatio = 0.0;
    $count = 0;

    foreach ($rounds as $r) {
        if (!is_array($r)) continue;
        $level = (int)($r['level'] ?? 0);
        if ($level < 1 || $level > 25) continue;
        if (empty($r['isCorrect']) && empty($r['is_correct'])) continue;

        $responseMs = (int)($r['responseMs'] ?? $r['response_ms'] ?? 0);
        $responseMs = max(1, $responseMs);
        $showMs = daily_target_show_ms_for_level($level);
        $ratio = min(1.5, max(0.0, $showMs / $responseMs));
        $sumRatio += $ratio;
        $count++;
    }

    $avgRatio = $count > 0 ? ($sumRatio / $count) : 0.0;
    $timeFactor = min(1.0, $avgRatio / 1.5);
    $levelFactor = $reached / 25;
    $score = (int)round(1000 * ((0.7 * $levelFactor) + (0.3 * $timeFactor)));
    return max(0, min(1000, $score));
}

function upsert_daily_score(array $row): void {
    (new DailyRepository())->recordScore(
        $row['user_id'] ?? null,
        $row['email'] ?? null,
        $row['anon_id'] ?? null,
        $row
    );
}

function daily_leaderboard(string $challengeDate, ?string $country = null, int $limit = 50): array {
    return (new DailyRepository())->getLeaderboard($challengeDate, $limit, $country);
}

function get_daily_challenge_status(?string $userId, ?string $anonId, ?string $dayUtc = null): array {
    return (new DailyRepository())->getStatus($userId, $anonId, $dayUtc);
}

function has_played_daily(string $userId, string $dayUtc): bool {
    $status = (new DailyRepository())->getStatus($userId, null, $dayUtc);
    return !empty($status['has_played']);
}

function record_daily_challenge_score(?string $userId, ?string $userEmail, ?string $anonId, array $data): array {
    return (new DailyRepository())->recordScore($userId, $userEmail, $anonId, $data);
}

function get_daily_leaderboard(?string $dayUtc = null, int $limit = 100): array {
    return (new DailyRepository())->getLeaderboard($dayUtc, $limit);
}

// --- Multiplayer Rooms ---

function create_room(string $ownerId, string $ownerEmail, int $roundsTotal = 50, ?string $name = null): array {
    return (new RoomRepository())->createRoom($ownerId, $ownerEmail, $roundsTotal, $name);
}

function get_room_by_guid(string $guid): ?array {
    return (new RoomRepository())->getRoomByGuid($guid);
}

function get_room_by_id(string $id): ?array {
    return (new RoomRepository())->getRoomById($id);
}

function list_user_rooms(string $userId, int $limit = 50): array {
    return (new RoomRepository())->listUserRooms($userId, $limit);
}

function add_room_player(string $roomId, string $userId, string $email): bool {
    return (new RoomRepository())->addPlayer($roomId, $userId, $email);
}

function list_room_players(string $roomId): array {
    return (new RoomRepository())->listPlayers($roomId);
}

function set_room_started(string $roomId): void {
    (new RoomRepository())->setStatus($roomId, 'active');
}

function set_room_finished(string $roomId, ?int $finishedRound = null): void {
    (new RoomRepository())->setStatus($roomId, 'finished', $finishedRound);
}

function room_mark_eliminated(string $roomId, string $userId, int $roundIndex): void {
    (new RoomRepository())->markEliminated($roomId, $userId, $roundIndex);
}

function room_add_score(string $roomId, string $userId, int $scoreDelta, int $correctDelta): void {
    (new RoomRepository())->addScore($roomId, $userId, $scoreDelta, $correctDelta);
}

function room_log_event(string $roomId, int $roundIndex, ?string $userId, string $email, string $type, array $payload): void {
    (new RoomRepository())->logEvent($roomId, $roundIndex, $userId, $email, $type, $payload);
}

function room_timing_for_round(int $roundIndex): array {
    return (new RoomGameService())->getTimingForRound($roundIndex);
}

function grid_count_for_round(int $roundIndex, int $roundsTotal = 50): int {
    return (new RoomGameService())->getGridCountForRound($roundIndex, $roundsTotal);
}

function room_generate_question(int $roundIndex, int $roundsTotal = 50): array {
    return (new RoomGameService())->generateQuestion($roundIndex, $roundsTotal);
}

function room_create_round(string $roomId, int $roundIndex, int $roundsTotal = 50): array {
    $service = new RoomGameService();
    $question = $service->generateQuestion($roundIndex, $roundsTotal);
    $pdo = db();
    $stmt = $pdo->prepare("
        INSERT INTO room_rounds (id, room_id, round_index, question_json, started_at)
        VALUES (:id, :room_id, :round_index, :question_json, :started_at)
        ON DUPLICATE KEY UPDATE question_json = VALUES(question_json)
    ");
    $stmt->execute([
        ':id' => uuid_v4(),
        ':room_id' => $roomId,
        ':round_index' => $roundIndex,
        ':question_json' => json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':started_at' => now_utc_mysql(),
    ]);
    $pdo->prepare("UPDATE rooms SET current_round = :r WHERE id = :id")->execute([':r' => $roundIndex, ':id' => $roomId]);
    return $question;
}

function room_round_question(string $roomId, int $roundIndex): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT question_json FROM room_rounds WHERE room_id = :rid AND round_index = :r LIMIT 1");
    $stmt->execute([':rid' => $roomId, ':r' => $roundIndex]);
    $row = $stmt->fetchColumn();
    return $row ? json_decode((string)$row, true) : null;
}

function room_end_round(string $roomId, int $roundIndex): void {
    (new RoomGameService())->endRound($roomId, $roundIndex);
}

function room_winner_email(string $roomId): ?string {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT email
        FROM room_players
        WHERE room_id = :rid
        ORDER BY (status = 'active') DESC, score DESC, correct DESC
        LIMIT 1
    ");
    $stmt->execute([':rid' => $roomId]);
    $res = $stmt->fetchColumn();
    return $res ? (string)$res : null;
}

function country_name(?string $code, string $lang = 'en'): string {
    if (!$code) return '-';
    $code = strtoupper(trim($code));
    if ($code === '' || $code === '-') return '-';

    // Simple country dictionary or fallback
    return $code;
}
