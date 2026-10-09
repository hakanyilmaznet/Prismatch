<?php
declare(strict_types=1);

namespace Prismatch\Services;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Core\Logger;
use Prismatch\Repositories\RoomRepository;
use Prismatch\Repositories\UserRepository;

class RoomGameService {
    private PDO $pdo;
    private RoomRepository $roomRepo;
    private UserRepository $userRepo;
    public const POWERUP_STREAK_REQUIREMENT = 5;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->roomRepo = new RoomRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
    }

    public function getTimingForRound(int $roundIndex, string $gameMode = 'elimination'): array {
        $lvl = max(1, $roundIndex);
        // Sonraki tura geçişte 3-2-1 saymasın, 1 saniye sonra başlasın (1000ms)
        $countdownMs = ($lvl > 1) ? 1000 : 3000;
        $answerMs = 5000;

        if ($lvl === 21 || $lvl === 41) {
            $showMs = 5000;
        } else {
            // Gelişmiş hedef renk/bayrak görüntüleme süresi algoritması:
            // 250ms gibi çok kısa süreler insan gözü, mobil DOM render ve ağ gecikmesi nedeniyle hedefin görünmeden geçilmesine neden oluyordu.
            // Yeni dengeli süre eğrisi:
            // - Bayrak modunda armaları/desenleri net algılamak için taban 1600ms, tavan 3200ms
            // - Renk modunda gözün net odaklanabilmesi için taban 1200ms, tavan 2800ms
            if ($gameMode === 'flags') {
                $showMs = max(1600, (int)round(3200 - ($lvl - 1) * 70));
            } else {
                $showMs = max(1200, (int)round(2800 - ($lvl - 1) * 65));
            }
        }

        return [
            'countdown_ms' => $countdownMs,
            'show_ms' => $showMs,
            'answer_ms' => $answerMs,
        ];
    }

    private const COLOR_PALETTE = [
        "#000000","#FFFFFF","#FF0000","#00FF00","#0000FF","#FFFF00","#00FFFF","#FF00FF",
        "#800000","#008000","#000080","#808000","#008080","#800080","#C0C0C0","#808080",
        "#9999FF","#993366","#FFFFCC","#CCFFFF","#660066","#FF8080","#0066CC","#CCCCFF",
        "#00CCFF","#CCFFCC","#FFFF99","#99CCFF","#FF99CC","#CC99FF","#FFCC99",
        "#3366FF","#33CCCC","#99CC00","#FFCC00","#FF9900","#FF6600","#666699","#969696",
        "#003366","#339966","#003300","#333300","#993300","#333399","#333333",
        "#1ABC9C","#2ECC71","#3498DB","#9B59B6","#E67E22","#E74C3C","#F1C40F","#95A5A6",
        "#16A085","#27AE60","#2980B9","#8E44AD","#D35400","#C0392B","#F39C12","#7F8C8D",
        "#2C3E50","#ECF0F1","#BDC3C7","#34495E",
        "#FFADAD","#FFD6A5","#FDFFB6","#CAFFBF","#9BF6FF","#A0C4FF","#BDB2FF","#FFC6FF",
        "#E0E0E0","#F5F5F5","#FAFAFA","#D1D5DB","#9CA3AF","#6B7280","#4B5563","#374151",
        "#0F172A","#1E293B","#334155","#475569","#1E1B4B","#312E81","#3730A3","#4338CA",
        "#064E3B","#065F46","#047857","#059669","#7C2D12","#9A3412","#B45309","#D97706",
        "#57E32C","#10B981","#3B82F6","#6366F1","#8B5CF6","#D946EF","#F43F5E"
    ];

    public function getGridCountForRound(int $roundIndex, int $roundsTotal = 25): int {
        $lvl = max(1, $roundIndex);
        if ($lvl <= 10) return 9;   // İlk 10 tur: 9 kare (3x3)
        if ($lvl <= 20) return 16;  // Sonraki 10 tur: 16 kare (4x4)
        return 25;                  // Sonraki 5 tur: 25 kare (5x5)
    }

    public static function getFlagPalette(): array {
        static $flags = null;
        if ($flags === null) {
            $flagDir = dirname(__DIR__, 2) . '/flags';
            $files = glob($flagDir . '/*.png');
            if ($files) {
                $flags = [];
                foreach ($files as $file) {
                    $base = basename($file);
                    if (str_ends_with(strtolower($base), '.png')) {
                        $flags[] = 'flags/' . $base;
                    }
                }
            } else {
                $flags = [];
            }
        }
        return $flags;
    }

    public function generateQuestion(int $roundIndex, int $roundsTotal = 25, string $gameMode = 'elimination'): array {
        if ($gameMode === 'flags') {
            $palette = self::getFlagPalette();
        } else {
            $palette = array_values(array_unique(self::COLOR_PALETTE));
        }
        $count = $this->getGridCountForRound($roundIndex, $roundsTotal);
        shuffle($palette);
        $grid = array_slice($palette, 0, min($count, count($palette)));
        $target = $grid[array_rand($grid)];
        return ['target' => $target, 'grid' => $grid, 'gridCount' => count($grid)];
    }

    public function joinRoom(string $guid, string $userId, string $email): array {
        Logger::room('joinRoom:attempt', $guid, ['user_id' => $userId, 'email' => $email]);
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            Logger::room('joinRoom:not_found', $guid, ['user_id' => $userId], 'WARN');
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        $added = $this->roomRepo->addPlayer($roomId, $userId, $email);
        if (!$added) {
            Logger::room('joinRoom:room_full', $guid, ['user_id' => $userId], 'WARN');
            return ['ok' => false, 'error' => 'room_full', 'code' => 403];
        }

        $players = $this->roomRepo->listPlayers($roomId);
        Logger::room('joinRoom:success', $guid, ['players_count' => count($players), 'joined' => $email]);

        // Oyuncuya henüz bir avatar atanmamışsa, alınmamış ilk avatarı otomatik ata
        $takenAvatars = [];
        $mePlayer = null;
        foreach ($players as $p) {
            if (!empty($p['avatar'])) {
                $takenAvatars[] = (string)$p['avatar'];
            }
            if ((string)$p['user_id'] === $userId || (string)($p['email'] ?? '') === $email) {
                $mePlayer = $p;
            }
        }
        if ($mePlayer && empty($mePlayer['avatar'])) {
            $conceptAvatars = self::getConceptAvatars();
            foreach ($conceptAvatars as $av) {
                if (!in_array((string)$av['id'], $takenAvatars, true)) {
                    $this->roomRepo->setPlayerAvatar($roomId, $userId, (string)$av['id']);
                    break;
                }
            }
            $players = $this->roomRepo->listPlayers($roomId);
        }

        // Realtime notification: Notify waiting room that a player joined
        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'round' => (int)$room['current_round'],
                'players' => $players,
                'joined' => $email,
            ]);
        }

        return [
            'ok' => true,
            'room' => [
                'guid' => $room['guid'],
                'name' => $room['name'],
                'status' => $room['status'],
                'rounds_total' => (int)$room['rounds_total'],
                'current_round' => (int)$room['current_round'],
                'owner_email' => $room['owner_email'],
                'owner_id' => $room['owner_id'] ?? null,
                'is_private' => !empty($room['is_private']),
                'game_mode' => $room['game_mode'] ?? 'elimination',
            ],
            'players' => $players,
            'avatars' => self::getConceptAvatars(),
        ];
    }

    public function startOrNextRound(string $guid, string $userId, bool $requireHost = true): array {
        Logger::room('startOrNextRound:attempt', $guid, ['user_id' => $userId, 'require_host' => $requireHost]);
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            Logger::room('startOrNextRound:not_found', $guid, [], 'WARN');
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        if ($requireHost && ($room['owner_id'] ?? null) !== $userId) {
            Logger::room('startOrNextRound:not_owner', $guid, ['owner_id' => $room['owner_id'] ?? null, 'user_id' => $userId], 'WARN');
            return ['ok' => false, 'error' => 'not_owner', 'code' => 403];
        }

        $roomId = (string)$room['id'];
        $current = (int)$room['current_round'];
        $total = (int)$room['rounds_total'];

        if ($room['status'] === 'finished') {
            Logger::room('startOrNextRound:already_finished', $guid);
            return ['ok' => true, 'finished' => true];
        }

        // Multiplayer rooms require at least 2 online players to start
        if ($current === 0 || $room['status'] === 'waiting') {
            $ownerId = (string)($room['owner_id'] ?? $userId);
            $this->roomRepo->cleanupStalePlayers($roomId, $ownerId, 8);
            $players = $this->roomRepo->listPlayers($roomId);
            $onlinePlayers = array_filter($players, fn($p) => !empty($p['is_online']));
            if (count($onlinePlayers) < 2) {
                Logger::room('startOrNextRound:min_players_required', $guid, ['online_count' => count($onlinePlayers), 'total_players' => count($players)], 'WARN');
                return ['ok' => false, 'error' => 'min_players_required', 'code' => 400];
            }
        }

        return $this->advanceToRound($room, $current + 1);
    }

    public function restartRoom(string $guid, string $userId, bool $startImmediately = false): array {
        Logger::room('restartRoom:attempt', $guid, ['user_id' => $userId, 'start_immediately' => $startImmediately]);
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            Logger::room('restartRoom:not_found', $guid, [], 'WARN');
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        if (($room['owner_id'] ?? null) !== $userId) {
            Logger::room('restartRoom:not_owner', $guid, ['owner_id' => $room['owner_id'] ?? null, 'user_id' => $userId], 'WARN');
            return ['ok' => false, 'error' => 'not_owner', 'code' => 403];
        }

        $roomId = (string)$room['id'];
        $now = Database::nowUtc();

        // 1. Purge stale players who left or went offline before restarting room
        $this->roomRepo->cleanupStalePlayers($roomId, $userId, 8);

        $this->pdo->beginTransaction();
        try {
            // Reset room status and round counter
            $stmtRoom = $this->pdo->prepare("
                UPDATE rooms
                SET status = 'waiting',
                    current_round = 0,
                    finished_round = NULL,
                    started_at = NULL,
                    finished_at = NULL
                WHERE id = :rid
            ");
            $stmtRoom->execute([':rid' => $roomId]);

            // Reset only active/remaining players in the room (do not revive or update last_active of offline players)
            $stmtPlayers = $this->pdo->prepare("
                UPDATE room_players
                SET status = 'active',
                    eliminated_round = NULL,
                    score = 0,
                    correct = 0
                WHERE room_id = :rid
            ");
            $stmtPlayers->execute([':rid' => $roomId]);

            // Touch host's active timestamp
            $this->roomRepo->touchPlayer($roomId, $userId, true);

            // Clean up previous rounds and events for clean state
            $stmtDelRounds = $this->pdo->prepare("DELETE FROM room_rounds WHERE room_id = :rid");
            $stmtDelRounds->execute([':rid' => $roomId]);

            $stmtDelEvents = $this->pdo->prepare("DELETE FROM room_events WHERE room_id = :rid");
            $stmtDelEvents->execute([':rid' => $roomId]);

            $this->pdo->commit();
            Logger::room('restartRoom:db_reset_done', $guid, ['room_id' => $roomId]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('RoomGame', "restartRoom DB error on guid={$guid}: " . $e->getMessage(), [], $e);
            return ['ok' => false, 'error' => 'db_error', 'code' => 500, 'detail' => $e->getMessage()];
        }

        $freshPlayers = $this->roomRepo->listPlayers($roomId);
        $onlinePlayers = array_filter($freshPlayers, fn($p) => !empty($p['is_online']));

        // Broadcast room:reset to all connected players
        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:reset', [
                'guid' => $guid,
                'status' => 'waiting',
                'round' => 0,
                'game_mode' => (string)($room['game_mode'] ?? 'elimination'),
                'players' => $freshPlayers,
                'restarting' => $startImmediately,
            ]);
            Logger::room('restartRoom:pusher_reset_triggered', $guid, ['players_count' => count($freshPlayers), 'online_count' => count($onlinePlayers)]);
        }

        if ($startImmediately) {
            $freshRoom = $this->roomRepo->getRoomById($roomId);
            if ($freshRoom) {
                if (count($onlinePlayers) < 2) {
                    Logger::room('restartRoom:min_players_required', $guid, ['online_count' => count($onlinePlayers), 'total' => count($freshPlayers)], 'WARN');
                    return ['ok' => false, 'error' => 'min_players_required', 'code' => 400, 'players' => $freshPlayers];
                }
                Logger::room('restartRoom:advancing_to_round_1', $guid);
                $advResult = $this->advanceToRound($freshRoom, 1);
                $advResult['is_restart'] = true;
                $advResult['players'] = $freshPlayers;
                return $advResult;
            }
        }

        return [
            'ok' => true,
            'status' => 'waiting',
            'round' => 0,
            'game_mode' => (string)($room['game_mode'] ?? 'elimination'),
            'players' => $freshPlayers,
        ];
    }

    public function advanceToRound(array $room, int $nextRound): array {
        $roomId = (string)$room['id'];
        $guid = (string)$room['guid'];
        $total = (int)$room['rounds_total'];
        $current = (int)$room['current_round'];

        Logger::room('advanceToRound:attempt', $guid, ['from_round' => $current, 'next_round' => $nextRound]);

        // Atomic lock check: only advance if the round matches current
        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare("SELECT status, current_round, rounds_total, game_mode FROM rooms WHERE id = :id FOR UPDATE");
            $lockStmt->execute([':id' => $roomId]);
            $locked = $lockStmt->fetch();

            if (!$locked || $locked['status'] === 'finished') {
                $this->pdo->rollBack();
                Logger::room('advanceToRound:locked_finished', $guid);
                return ['ok' => true, 'finished' => true];
            }

            $currentInDb = (int)$locked['current_round'];
            if ($currentInDb >= $nextRound) {
                // Already advanced by another thread/request
                $this->pdo->rollBack();
                Logger::room('advanceToRound:already_advanced', $guid, ['current_in_db' => $currentInDb, 'requested' => $nextRound]);
                return ['ok' => true, 'already_advanced' => true, 'round' => $currentInDb];
            }

            $gameMode = (string)($locked['game_mode'] ?? ($room['game_mode'] ?? 'elimination'));
            $isElimination = ($gameMode === 'elimination');

            // Check alive players
            $players = $this->roomRepo->listPlayers($roomId);
            $activeCount = 0;
            foreach ($players as $p) {
                if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
            }

            $shouldFinish = ($nextRound > $total);
            if ($isElimination && $currentInDb > 0) {
                if (($activeCount <= 1 && count($players) >= 2) || $activeCount === 0) {
                    $shouldFinish = true;
                }
            }

            if ($shouldFinish) {
                $this->pdo->commit();
                Logger::room('advanceToRound:match_finished_condition', $guid, ['active_count' => $activeCount, 'next_round' => $nextRound, 'game_mode' => $gameMode]);
                $finishResult = $this->finishGame($roomId, $currentInDb, $guid);
                return ['ok' => true, 'finished' => true, 'players' => $finishResult['players']];
            }

            // Update room status to active if waiting, requiring at least 2 players
            if ($currentInDb === 0) {
                if (count($players) < 2) {
                    $this->pdo->rollBack();
                    Logger::room('advanceToRound:min_players_failed', $guid, ['players_count' => count($players)], 'WARN');
                    return ['ok' => false, 'error' => 'min_players_required', 'code' => 400];
                }
                $this->roomRepo->setStatus($roomId, 'active');
            }

            // Generate question
            $question = $this->generateQuestion($nextRound, $total, $gameMode);
            $now = Database::nowUtc();

            // Insert round
            $insRound = $this->pdo->prepare("
                INSERT INTO room_rounds (id, room_id, round_index, question_json, started_at)
                VALUES (:id, :room_id, :round_index, :question_json, :started_at)
            ");
            $insRound->execute([
                ':id' => Database::generateUuid(),
                ':room_id' => $roomId,
                ':round_index' => $nextRound,
                ':question_json' => json_encode($question, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':started_at' => $now,
            ]);

            // Update current round in rooms
            $updRoom = $this->pdo->prepare("UPDATE rooms SET current_round = :r WHERE id = :id");
            $updRoom->execute([':r' => $nextRound, ':id' => $roomId]);

            $this->pdo->commit();

            $timing = $this->getTimingForRound($nextRound, $gameMode);
            $freshPlayers = $this->roomRepo->listPlayers($roomId);

            $nowMs = (int)round((float)(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('U.u') * 1000);

            $payload = [
                'guid' => $guid,
                'round' => $nextRound,
                'rounds_total' => $total,
                'game_mode' => $gameMode,
                'question' => $question,
                'countdown_ms' => $timing['countdown_ms'],
                'show_ms' => $timing['show_ms'],
                'answer_ms' => $timing['answer_ms'],
                'players' => $freshPlayers,
                'started_at_ms' => $nowMs,
                'server_now_ms' => $nowMs,
            ];

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:round', $payload);
            }

            Logger::room('advanceToRound:success', $guid, ['round' => $nextRound, 'active_players' => $activeCount, 'game_mode' => $gameMode]);

            return [
                'ok' => true,
                'round' => $nextRound,
                'rounds_total' => $total,
                'game_mode' => $gameMode,
                'question' => $question,
                'countdown_ms' => $timing['countdown_ms'],
                'show_ms' => $timing['show_ms'],
                'answer_ms' => $timing['answer_ms'],
                'players' => $freshPlayers,
                'started_at_ms' => $nowMs,
                'server_now_ms' => $nowMs,
            ];
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('RoomGame', "advanceToRound exception on guid={$guid}: " . $e->getMessage(), [], $e);
            return ['ok' => false, 'error' => 'advance_failed', 'msg' => $e->getMessage()];
        }
    }

    public function processAnswer(string $guid, string $userId, string $email, int $round, string $picked, bool $isTimeout, int $responseMs): array {
        Logger::room('processAnswer:attempt', $guid, [
            'user_id' => $userId,
            'email' => $email,
            'round' => $round,
            'picked' => $picked,
            'timeout' => $isTimeout,
            'response_ms' => $responseMs,
        ]);

        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            Logger::room('processAnswer:room_not_found', $guid, ['round' => $round], 'WARN');
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        if ($room['status'] !== 'active') {
            Logger::room('processAnswer:room_not_active', $guid, ['status' => $room['status']], 'WARN');
            return ['ok' => false, 'error' => 'room_not_active', 'code' => 409];
        }

        // Verify player is currently active in the room
        $players = $this->roomRepo->listPlayers($roomId);
        $me = null;
        foreach ($players as $p) {
            if ($p['user_id'] === $userId) {
                $me = $p;
                break;
            }
        }
        if (!$me || $me['status'] !== 'active') {
            Logger::room('processAnswer:player_not_active', $guid, ['user_id' => $userId, 'player_status' => $me['status'] ?? 'null'], 'WARN');
            return ['ok' => false, 'error' => 'not_active', 'code' => 403];
        }

        // Fetch round question
        $qStmt = $this->pdo->prepare("SELECT question_json FROM room_rounds WHERE room_id = :rid AND round_index = :r LIMIT 1");
        $qStmt->execute([':rid' => $roomId, ':r' => $round]);
        $qRow = $qStmt->fetchColumn();
        if (!$qRow) {
            Logger::room('processAnswer:round_not_found', $guid, ['round' => $round], 'WARN');
            return ['ok' => false, 'error' => 'round_not_found', 'code' => 404];
        }

        $question = json_decode((string)$qRow, true);
        $target = $question['target'] ?? '';
        if ($target === '') {
            Logger::room('processAnswer:invalid_round_target', $guid, ['round' => $round], 'ERROR');
            return ['ok' => false, 'error' => 'invalid_round', 'code' => 500];
        }

        // Prevent duplicate answers for the same round
        $dupStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM room_events
            WHERE room_id = :rid AND round_index = :r AND user_id = :uid AND event_type IN ('answer', 'eliminate', 'timeout')
        ");
        $dupStmt->execute([':rid' => $roomId, ':r' => $round, ':uid' => $userId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            Logger::room('processAnswer:already_answered', $guid, ['user_id' => $userId, 'round' => $round]);
            return ['ok' => true, 'already_answered' => true];
        }

        $gameMode = (string)($room['game_mode'] ?? 'elimination');
        $isElimination = ($gameMode === 'elimination');

        $isCorrect = (!$isTimeout && $picked !== '' && $picked === $target);
        $scoreDelta = 0;
        $streak = 0;
        $streakMultiplier = 1.0;
        $streakBonus = 0;

        if ($isCorrect) {
            // Calculate consecutive correct streak up to this round
            $streakStmt = $this->pdo->prepare("
                SELECT payload_json
                FROM room_events
                WHERE room_id = :rid AND user_id = :uid AND event_type IN ('answer', 'eliminate', 'timeout')
                ORDER BY round_index DESC
                LIMIT 25
            ");
            $streakStmt->execute([':rid' => $roomId, ':uid' => $userId]);
            $prevEvents = $streakStmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $streak = 1;
            foreach ($prevEvents as $pe) {
                $pj = json_decode((string)$pe['payload_json'], true) ?: [];
                if (!empty($pj['correct'])) {
                    $streak++;
                } else {
                    break;
                }
            }

            if ($streak >= 5) {
                $streakMultiplier = 1.5;
            } elseif ($streak === 4) {
                $streakMultiplier = 1.4;
            } elseif ($streak === 3) {
                $streakMultiplier = 1.25;
            } elseif ($streak === 2) {
                $streakMultiplier = 1.1;
            } else {
                $streakMultiplier = 1.0;
            }

            $baseScore = max(50, 1000 - (int)floor(max(0, $responseMs) / 10));
            $scoreDelta = (int)round($baseScore * $streakMultiplier);
            $streakBonus = max(0, $scoreDelta - $baseScore);

            $this->roomRepo->addScore($roomId, $userId, $scoreDelta, 1);
            $this->roomRepo->logEvent($roomId, $round, $userId, $email, 'answer', [
                'picked' => $picked,
                'target' => $target,
                'response_ms' => $responseMs,
                'correct' => true,
                'score_delta' => $scoreDelta,
                'base_score' => $baseScore,
                'streak' => $streak,
                'streak_multiplier' => $streakMultiplier,
                'streak_bonus' => $streakBonus,
            ]);
            Logger::room('processAnswer:correct', $guid, [
                'user_id' => $userId,
                'score_delta' => $scoreDelta,
                'streak' => $streak,
                'streak_multiplier' => $streakMultiplier,
                'round' => $round
            ]);
        } elseif ($isElimination) {
            // Oda oyunlarında yarışmacılar çıkış butonuna basmadıkça izleyici moduna düşmesinler
            $this->roomRepo->logEvent($roomId, $round, $userId, $email, $isTimeout ? 'timeout' : 'answer', [
                'picked' => $picked !== '' ? $picked : null,
                'target' => $target,
                'response_ms' => $responseMs,
                'correct' => false,
                'score_delta' => 0,
            ]);
            Logger::room('processAnswer:wrong_or_timeout', $guid, ['user_id' => $userId, 'timeout' => $isTimeout, 'round' => $round]);
        } else {
            // Points Mode (Elenmesiz mod):
            // - Yanlış seçenekte: kazanması gereken puan toplam puanından düşülsün (-pointsPenalty)
            // - Süre aşımında / pas: 0 puan (kesinti veya elenme yok)
            if ($isTimeout || $picked === '') {
                $scoreDelta = 0;
                $this->roomRepo->logEvent($roomId, $round, $userId, $email, 'timeout', [
                    'picked' => null,
                    'target' => $target,
                    'response_ms' => $responseMs,
                    'correct' => false,
                    'score_delta' => 0,
                ]);
                Logger::room('processAnswer:points_timeout', $guid, ['user_id' => $userId, 'round' => $round]);
            } else {
                $pointsPenalty = max(50, 1000 - (int)floor(max(0, $responseMs) / 10));
                $scoreDelta = -$pointsPenalty;
                $this->roomRepo->addScore($roomId, $userId, $scoreDelta, 0);
                $this->roomRepo->logEvent($roomId, $round, $userId, $email, 'answer', [
                    'picked' => $picked,
                    'target' => $target,
                    'response_ms' => $responseMs,
                    'correct' => false,
                    'score_delta' => $scoreDelta,
                ]);
                Logger::room('processAnswer:points_wrong', $guid, ['user_id' => $userId, 'score_delta' => $scoreDelta, 'round' => $round]);
            }
        }

        $freshPlayers = $this->roomRepo->listPlayers($roomId);
        $activeCount = 0;
        foreach ($freshPlayers as $p) {
            if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
        }

        // Participating players in this round are players who were NOT eliminated before this round
        // (i.e. eliminated_round IS NULL or eliminated_round >= $round).
        $partStmt = $this->pdo->prepare("
            SELECT user_id
            FROM room_players
            WHERE room_id = :rid
              AND (eliminated_round IS NULL OR eliminated_round >= :r)
        ");
        $partStmt->execute([':rid' => $roomId, ':r' => $round]);
        $participatingUserIds = $partStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        $totalParticipants = count($participatingUserIds);

        // Fetch distinct user IDs that have recorded an answer/timeout/eliminate event in this round
        $ansStmt = $this->pdo->prepare("
            SELECT DISTINCT user_id
            FROM room_events
            WHERE room_id = :rid
              AND round_index = :r
              AND event_type IN ('answer', 'eliminate', 'timeout')
        ");
        $ansStmt->execute([':rid' => $roomId, ':r' => $round]);
        $answeredUserIds = $ansStmt->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        $answeredCount = count($answeredUserIds);

        $allAnswered = ($answeredCount >= $totalParticipants && $totalParticipants > 0);

        Logger::room('processAnswer:participation_check', $guid, [
            'round' => $round,
            'total_participants' => $totalParticipants,
            'answered_count' => $answeredCount,
            'all_answered' => $allAnswered,
            'active_survivors' => $activeCount,
            'game_mode' => $gameMode,
        ]);

        $isGameOver = ($round >= (int)$room['rounds_total']);
        if ($isElimination) {
            $isGameOver = $isGameOver || (($activeCount <= 1 && count($freshPlayers) >= 2) || $activeCount === 0);
        }

        if ($allAnswered) {
            // All participating players have made their choice! End round immediately!
            Logger::room('processAnswer:all_players_answered_ending_round', $guid, ['round' => $round]);
            $this->endRound($roomId, $round);

            if ($isGameOver) {
                Logger::room('processAnswer:game_over', $guid, ['active_count' => $activeCount, 'round' => $round, 'game_mode' => $gameMode]);
                $finishResult = $this->finishGame($roomId, $round, $guid);
                return [
                    'ok' => true,
                    'correct' => $isCorrect,
                    'score_delta' => $scoreDelta,
                    'streak' => $streak,
                    'streak_multiplier' => $streakMultiplier,
                    'streak_bonus' => $streakBonus,
                    'finished' => true,
                    'round_ended' => true,
                    'all_answered' => true,
                    'players' => $finishResult['players'],
                ];
            }

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $round,
                    'players' => $freshPlayers,
                    'all_answered' => true,
                ]);
            }

            return [
                'ok' => true,
                'correct' => $isCorrect,
                'score_delta' => $scoreDelta,
                'streak' => $streak,
                'streak_multiplier' => $streakMultiplier,
                'streak_bonus' => $streakBonus,
                'round_ended' => true,
                'all_answered' => true,
                'players' => $freshPlayers,
            ];
        }

        // Only broadcast intermediate progress if round is still in progress and waiting for others
        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'round' => $round,
                'players' => $freshPlayers,
                'eliminated' => ($isElimination && !$isCorrect) ? $email : null,
                'answered_count' => $answeredCount,
                'total_participants' => $totalParticipants,
            ]);
        }

        return [
            'ok' => true,
            'correct' => $isCorrect,
            'score_delta' => $scoreDelta,
            'streak' => $streak,
            'streak_multiplier' => $streakMultiplier,
            'streak_bonus' => $streakBonus,
            'round_ended' => false,
            'all_answered' => false,
            'answered_count' => $answeredCount,
            'total_participants' => $totalParticipants,
            'players' => $freshPlayers,
        ];
    }

    public function endRound(string $roomId, int $roundIndex): void {
        Logger::room('endRound', $roomId, ['round' => $roundIndex]);
        $stmt = $this->pdo->prepare("
            UPDATE room_rounds
            SET ended_at = COALESCE(ended_at, :now)
            WHERE room_id = :rid AND round_index = :r
        ");
        $stmt->execute([':now' => Database::nowUtc(), ':rid' => $roomId, ':r' => $roundIndex]);
    }

    public function finishGame(string $roomId, int $round, string $guid): array {
        Logger::room('finishGame:attempt', $guid, ['round' => $round]);
        $this->endRound($roomId, $round);

        // Fetch players sorted by: (status = 'active') DESC, score DESC, correct DESC, joined_at ASC
        $players = $this->roomRepo->listPlayers($roomId);
        $winner = !empty($players) ? $players[0] : null;

        // Award 5000 win bonus points to the winner if eligible and not already awarded:
        // A player is eligible for the win bonus if they are the active survivor OR they scored points (> 0).
        // If everyone has 0 points and everyone was eliminated, no win bonus is awarded.
        if ($winner && !empty($winner['user_id'])) {
            $isEligible = ($winner['status'] === 'active' || (int)($winner['score'] ?? 0) > 0);
            if ($isEligible) {
                $winnerUserId = (string)$winner['user_id'];
                $winnerEmail = (string)$winner['email'];

                $checkBonus = $this->pdo->prepare("
                    SELECT COUNT(*) FROM room_events
                    WHERE room_id = :rid AND event_type = 'win_bonus'
                ");
                $checkBonus->execute([':rid' => $roomId]);
                $alreadyAwarded = (int)$checkBonus->fetchColumn() > 0;

                if (!$alreadyAwarded) {
                    $bonusPoints = 5000;
                    $this->roomRepo->addScore($roomId, $winnerUserId, $bonusPoints, 0);
                    $this->roomRepo->logEvent($roomId, $round, $winnerUserId, $winnerEmail, 'win_bonus', [
                        'bonus' => $bonusPoints,
                        'winner_id' => $winnerUserId,
                        'winner_email' => $winnerEmail,
                    ]);
                    Logger::room('finishGame:win_bonus_awarded', $guid, ['winner_id' => $winnerUserId, 'winner_email' => $winnerEmail, 'bonus' => $bonusPoints]);
                }
            }
        }

        // Mark room as finished in database
        $now = Database::nowUtc();
        $updRoom = $this->pdo->prepare("
            UPDATE rooms
            SET status = 'finished',
                finished_round = :r,
                finished_at = COALESCE(finished_at, :now)
            WHERE id = :rid
        ");
        $updRoom->execute([':r' => $round, ':now' => $now, ':rid' => $roomId]);

        $finalPlayers = $this->roomRepo->listPlayers($roomId);
        $awards = $this->getRoomAwards($roomId);

        $roomRow = $this->roomRepo->getRoomById($roomId);
        $gameMode = (string)($roomRow['game_mode'] ?? 'elimination');
        $teamSummary = null;
        if ($gameMode === 'teams') {
            $configuredTeams = $this->getRoomTeams($roomRow ?: []);
            $teamScores = [];
            $teamMembers = [];
            foreach ($configuredTeams as $ct) {
                $tId = (string)($ct['id'] ?? 'red');
                $teamScores[$tId] = 0;
                $teamMembers[$tId] = 0;
            }
            if (empty($teamScores)) {
                $teamScores = ['red' => 0, 'blue' => 0];
                $teamMembers = ['red' => 0, 'blue' => 0];
            }

            $allowedIds = array_keys($teamScores);
            $fallbackTeam = $allowedIds[0];

            foreach ($finalPlayers as $p) {
                $t = (string)($p['team'] ?? '');
                if (!in_array($t, $allowedIds, true)) {
                    $t = $fallbackTeam;
                }
                $teamScores[$t] += (int)($p['score'] ?? 0);
                $teamMembers[$t]++;
            }

            $maxScore = -1;
            $winningTeam = 'tie';
            $isTie = false;
            foreach ($teamScores as $tId => $score) {
                if ($score > $maxScore) {
                    $maxScore = $score;
                    $winningTeam = $tId;
                    $isTie = false;
                } elseif ($score === $maxScore && $maxScore >= 0) {
                    $isTie = true;
                }
            }
            if ($isTie && count($teamScores) > 1) {
                $winningTeam = 'tie';
            }

            $teamSummary = [
                'red_score' => $teamScores['red'] ?? 0,
                'blue_score' => $teamScores['blue'] ?? 0,
                'red_members' => $teamMembers['red'] ?? 0,
                'blue_members' => $teamMembers['blue'] ?? 0,
                'winning_team' => $winningTeam,
                'teams' => $configuredTeams,
                'scores' => $teamScores,
                'members' => $teamMembers,
            ];
        }

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:finished', [
                'guid' => $guid,
                'round' => $round,
                'players' => $finalPlayers,
                'awards' => $awards,
                'game_mode' => $gameMode,
                'team_summary' => $teamSummary,
                'winner' => $winner ? [
                    'user_id' => $winner['user_id'],
                    'email' => $winner['email'],
                    'bonus' => 5000,
                ] : null,
            ]);
        }

        Logger::room('finishGame:success', $guid, ['round' => $round, 'players_count' => count($finalPlayers), 'awards_count' => count($awards)]);

        return [
            'ok' => true,
            'finished' => true,
            'round' => $round,
            'players' => $finalPlayers,
            'awards' => $awards,
            'game_mode' => $gameMode,
            'team_summary' => $teamSummary,
        ];
    }

    public function tick(string $guid, ?string $userId = null): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];

        if ($userId !== null && $userId !== '') {
            $this->roomRepo->touchPlayer($roomId, $userId);
        }

        if ($room['status'] === 'finished' || (int)$room['rounds_total'] <= 0) {
            $players = $this->roomRepo->listPlayers($roomId);
            $awards = $this->getRoomAwards($roomId);
            return [
                'ok' => true,
                'status' => 'finished',
                'finished' => true,
                'players' => $players,
                'awards' => $awards,
                'game_mode' => (string)($room['game_mode'] ?? 'elimination'),
            ];
        }

        $current = (int)$room['current_round'];
        $gameMode = (string)($room['game_mode'] ?? 'elimination');
        if ($room['status'] === 'waiting' && $current === 0) {
            $ownerId = (string)($room['owner_id'] ?? '');
            if ($ownerId !== '') {
                $this->roomRepo->cleanupStalePlayers($roomId, $ownerId, 10);
            }
            $this->roomRepo->cleanupInactiveRooms(60);
            $players = $this->roomRepo->listPlayers($roomId);
            return ['ok' => true, 'status' => 'waiting', 'game_mode' => $gameMode, 'players' => $players];
        }

        $st = $this->pdo->prepare("SELECT started_at, ended_at, question_json FROM room_rounds WHERE room_id = :rid AND round_index = :r LIMIT 1");
        $st->execute([':rid' => $roomId, ':r' => $current]);
        $round = $st->fetch();
        if (!$round) {
            return ['ok' => true, 'status' => $room['status'], 'round' => $current, 'game_mode' => $gameMode];
        }

        $timing = $this->getTimingForRound($current, $gameMode);
        $countdownMs = $timing['countdown_ms'];
        $showMs = $timing['show_ms'];
        $answerMs = $timing['answer_ms'];
        $intermissionMs = 2500; // 2.5s smooth intermission

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startedAt = new \DateTimeImmutable($round['started_at'], new \DateTimeZone('UTC'));
        $endedAt = $round['ended_at'] ? new \DateTimeImmutable($round['ended_at'], new \DateTimeZone('UTC')) : null;

        $nowMs = (int)round((float)$now->format('U.u') * 1000);
        $startedAtMs = (int)round((float)$startedAt->format('U.u') * 1000);
        $elapsed = max(0, $nowMs - $startedAtMs);

        // Auto-end round when time runs out (+ 1000ms buffer for network latency)
        if ($endedAt === null && $elapsed >= ($countdownMs + $showMs + $answerMs + 1000)) {
            Logger::room('tick:round_timeout_reached', $guid, ['round' => $current, 'elapsed_ms' => $elapsed]);
            $this->endRound($roomId, $current);

            // Eliminate players who didn't submit any answer/timeout in time
            $unansweredStmt = $this->pdo->prepare("
                SELECT rp.user_id, rp.email
                FROM room_players rp
                WHERE rp.room_id = :rid
                  AND rp.status = 'active'
                  AND (rp.eliminated_round IS NULL OR rp.eliminated_round >= :r)
                  AND rp.user_id NOT IN (
                      SELECT re.user_id
                      FROM room_events re
                      WHERE re.room_id = :rid
                        AND re.round_index = :r
                        AND re.event_type IN ('answer', 'eliminate', 'timeout')
                  )
            ");
            $unansweredStmt->execute([':rid' => $roomId, ':r' => $current]);
            $unansweredPlayers = $unansweredStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $question = json_decode((string)$round['question_json'], true);
            $target = $question['target'] ?? null;
            $gameMode = (string)($room['game_mode'] ?? 'elimination');
            $isElimination = ($gameMode === 'elimination');

            foreach ($unansweredPlayers as $up) {
                $pUserId = (string)$up['user_id'];
                $pEmail = (string)$up['email'];
                // Oda oyunlarında yarışmacılar çıkış butonuna basmadıkça izleyici moduna düşmesinler
                $this->roomRepo->logEvent($roomId, $current, $pUserId, $pEmail, 'timeout', [
                    'picked' => null,
                    'target' => $target,
                    'response_ms' => $answerMs,
                    'correct' => false,
                    'score_delta' => 0,
                ]);
                Logger::room('tick:player_auto_timed_out', $guid, ['user_id' => $pUserId, 'email' => $pEmail, 'round' => $current, 'game_mode' => $gameMode]);
            }

            $freshPlayers = $this->roomRepo->listPlayers($roomId);
            $activeCount = 0;
            foreach ($freshPlayers as $p) {
                if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
            }

            // Game over check:
            $isGameOver = ($current >= (int)$room['rounds_total']);
            if ($isElimination) {
                $isGameOver = $isGameOver || (($activeCount <= 1 && count($freshPlayers) >= 2) || $activeCount === 0);
            }

            if ($isGameOver) {
                Logger::room('tick:timeout_triggered_game_finish', $guid, ['round' => $current, 'active_count' => $activeCount, 'game_mode' => $gameMode]);
                $finishResult = $this->finishGame($roomId, $current, $guid);
                return ['ok' => true, 'finished' => true, 'game_mode' => $gameMode, 'players' => $finishResult['players']];
            }

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $current,
                    'game_mode' => $gameMode,
                    'players' => $freshPlayers,
                    'timeout_ended' => true,
                ]);
            }

            return ['ok' => true, 'status' => 'intermission', 'round' => $current, 'game_mode' => $gameMode, 'ended' => true, 'players' => $freshPlayers];
        }

        // Intermission check: automatically advance to next round when intermission is over
        if ($endedAt !== null) {
            $elapsedEnd = (int)round(((float)$now->format('U.u') - (float)$endedAt->format('U.u')) * 1000);
            if ($elapsedEnd >= $intermissionMs) {
                Logger::room('tick:intermission_complete_advancing', $guid, ['current' => $current, 'next' => $current + 1, 'elapsed_intermission_ms' => $elapsedEnd]);
                $freshRoom = $this->roomRepo->getRoomById($roomId);
                if ($freshRoom) {
                    return $this->advanceToRound($freshRoom, $current + 1);
                }
            }

            return [
                'ok' => true,
                'status' => 'intermission',
                'round' => $current,
                'game_mode' => $gameMode,
                'ended_at' => $round['ended_at'],
                'elapsed_intermission_ms' => $elapsedEnd,
                'intermission_ms' => $intermissionMs,
                'players' => $this->roomRepo->listPlayers($roomId),
            ];
        }

        // If currently in active question phase, return current question info
        $question = json_decode((string)$round['question_json'], true);
        return [
            'ok' => true,
            'status' => 'active',
            'round' => $current,
            'rounds_total' => (int)$room['rounds_total'],
            'game_mode' => $gameMode,
            'question' => $question,
            'countdown_ms' => $countdownMs,
            'show_ms' => $showMs,
            'answer_ms' => $answerMs,
            'started_at' => $round['started_at'],
            'started_at_ms' => $startedAtMs,
            'server_now_ms' => $nowMs,
            'elapsed_ms' => $elapsed,
            'players' => $this->roomRepo->listPlayers($roomId),
        ];
    }

    public function leaveRoom(string $guid, string $userId): array {
        Logger::room('leaveRoom:attempt', $guid, ['user_id' => $userId]);
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        $status = (string)$room['status'];

        if ($status === 'waiting' || $status === 'finished') {
            $this->roomRepo->removePlayer($roomId, $userId);
            $this->roomRepo->touchRoom($roomId);
            $players = $this->roomRepo->listPlayers($roomId);
            Logger::room('leaveRoom:removed_from_lobby', $guid, ['user_id' => $userId, 'remaining' => count($players)]);

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:update', [
                    'guid' => $guid,
                    'round' => (int)$room['current_round'],
                    'players' => $players,
                    'left_user_id' => $userId,
                ]);
            }
            return ['ok' => true, 'players' => $players, 'spectator' => false];
        }

        // Active game: mark eliminated and set to spectator status
        $current = (int)$room['current_round'];
        $this->roomRepo->markEliminated($roomId, $userId, $current);
        $this->roomRepo->touchRoom($roomId);

        // Fetch leaving player details for logging
        $allPlayers = $this->roomRepo->listPlayers($roomId);
        $leavingPlayer = null;
        foreach ($allPlayers as $p) {
            if ($p['user_id'] === $userId) {
                $leavingPlayer = $p;
                break;
            }
        }
        $userEmail = (string)($leavingPlayer['email'] ?? '');

        // Log elimination event so round progression knows this player has concluded
        $this->roomRepo->logEvent($roomId, $current, $userId, $userEmail, 'eliminate', [
            'reason' => 'left_room',
            'status' => 'spectator',
            'round' => $current,
        ]);

        $players = $this->roomRepo->listPlayers($roomId);
        Logger::room('leaveRoom:marked_eliminated_spectator', $guid, ['user_id' => $userId, 'round' => $current]);

        $activeCount = 0;
        foreach ($players as $p) {
            if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
        }
        $gameMode = (string)($room['game_mode'] ?? 'elimination');
        $isElimination = ($gameMode === 'elimination');

        // Check if elimination game should finish (only 1 or 0 survivor left)
        if ($isElimination && (($activeCount <= 1 && count($players) >= 2) || $activeCount === 0)) {
            Logger::room('leaveRoom:triggered_game_finish', $guid, ['round' => $current, 'active_count' => $activeCount]);
            $finishResult = $this->finishGame($roomId, $current, $guid);
            $players = $finishResult['players'] ?? $this->roomRepo->listPlayers($roomId);
        }

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'round' => $current,
                'players' => $players,
                'left_user_id' => $userId,
                'spectator' => true,
            ]);
        }

        return ['ok' => true, 'players' => $players, 'spectator' => true];
    }

    /**
     * Broadcast live reaction (emoji, sound, banter text) across presence channel.
     *
     * @param string $guid Room GUID
     * @param string $userId Player user ID
     * @param string $userEmail Player email
     * @param string $userName Player display name
     * @param string $emoji Reaction emoji (e.g. 🔥, 😂, 🚀)
     * @param string $sound Reaction audio identifier
     * @param string|null $text Optional quick shout
     * @return array{ok: bool, payload?: array<string, mixed>, code?: int, error?: string}
     */
    public function broadcastReaction(
        string $guid,
        string $userId,
        string $userEmail,
        string $userName,
        string $emoji,
        string $sound = 'pop',
        ?string $text = null
    ): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'code' => 404, 'error' => 'room_not_found'];
        }

        $emoji = mb_substr(trim($emoji), 0, 8);
        $sound = preg_replace('/[^a-zA-Z0-9_-]/', '', substr(trim($sound), 0, 20)) ?: 'pop';
        $text = $text !== null ? mb_substr(trim($text), 0, 40) : null;
        if ($text === '') {
            $text = null;
        }

        $now = microtime(true);
        $payload = [
            'guid' => $guid,
            'user_id' => $userId,
            'email' => $userEmail,
            'user_name' => $userName,
            'emoji' => $emoji,
            'sound' => $sound,
            'text' => $text,
            'ts' => (int)($now * 1000),
        ];

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:reaction', $payload);
        }

        return ['ok' => true, 'payload' => $payload];
    }

    /**
     * Apply a powerup or sabotage action in a room.
     *
     * @param string $guid Room GUID
     * @param string $userId Attacker user ID
     * @param string $userEmail Attacker user email
     * @param string $userName Attacker user display name
     * @param string $type Powerup type ('fifty_fifty' or 'ink_splat')
     * @param string|null $targetUserId Optional target user ID for sabotage
     * @param string|null $color Optional custom splat color hex
     * @return array{ok: bool, error?: string, code?: int, payload?: array<string, mixed>}
     */
    public function usePowerup(
        string $guid,
        string $userId,
        string $userEmail,
        string $userName,
        string $type,
        ?string $targetUserId = null,
        ?string $color = null
    ): array {
        $allowed = ['fifty_fifty', 'ink_splat'];
        if (!in_array($type, $allowed, true)) {
            return ['ok' => false, 'code' => 400, 'error' => 'invalid_powerup'];
        }

        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'code' => 404, 'error' => 'room_not_found'];
        }

        if (($room['status'] ?? '') !== 'active') {
            return ['ok' => false, 'code' => 400, 'error' => 'room_not_active'];
        }

        $roomId = (string)$room['id'];
        $players = $this->roomRepo->listPlayers($roomId);

        // Verify attacker is an active player
        $attacker = null;
        foreach ($players as $p) {
            if ((string)$p['user_id'] === $userId || (string)($p['email'] ?? '') === $userEmail) {
                $attacker = $p;
                break;
            }
        }

        if (!$attacker || ($attacker['status'] ?? '') === 'eliminated') {
            return ['ok' => false, 'code' => 403, 'error' => 'player_not_active'];
        }

        // Verify user has unlocked/renewed this powerup via 5 consecutive correct answers
        $avail = $this->checkPowerupAvailability($roomId, $userId, $type);
        if (!$avail['available']) {
            return [
                'ok' => false,
                'code' => 400,
                'error' => 'powerup_streak_required',
                'current_streak' => $avail['current_streak'],
                'required_streak' => $avail['required_streak'],
                'msg' => 'Bu jokeri kullanmak için 5 tur üst üste doğru cevap vermelisiniz.'
            ];
        }

        $targetPlayer = null;
        $targetName = null;
        $splatColor = null;
        if ($type === 'ink_splat') {
            // Mürekkep sıçratmada oyuncu seçimi iptal: SADECE lider rakip oyuncuya gönderilir
            $rivals = array_values(array_filter($players, function ($p) use ($userId, $userEmail) {
                return (string)$p['user_id'] !== $userId
                    && (string)($p['email'] ?? '') !== $userEmail
                    && ($p['status'] ?? '') !== 'eliminated';
            }));
            if (empty($rivals)) {
                return ['ok' => false, 'code' => 400, 'error' => 'no_target_available'];
            }
            usort($rivals, fn($a, $b) => ((int)($b['score'] ?? 0)) <=> ((int)($a['score'] ?? 0)));
            $targetPlayer = $rivals[0];
            $targetUserId = (string)$targetPlayer['user_id'];

            if ($targetPlayer) {
                $targetName = (string)($targetPlayer['user_name'] ?? '');
                if ($targetName === '' && !empty($targetPlayer['email'])) {
                    $parts = explode('@', (string)$targetPlayer['email']);
                    $targetName = $parts[0] !== '' ? $parts[0] : (string)$targetPlayer['email'];
                }
                if ($targetName === '') {
                    $targetName = 'Lider';
                }
            }

            // Rastgele canlı mürekkep rengi
            $inkPalette = ['#ff007f', '#00e5ff', '#39ff14', '#ffe600', '#a855f7', '#ff3d00', '#00ff88', '#ec4899', '#3b82f6', '#ff5722', '#8a2be2', '#00f5d4'];
            $splatColor = ($color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $color)) ? $color : $inkPalette[array_rand($inkPalette)];
        }

        // Record in room_events
        $eventId = Database::generateUuid();
        $payloadData = [
            'type' => $type,
            'from_user_id' => $userId,
            'from_name' => $userName,
            'from_email' => $userEmail,
            'target_user_id' => $targetPlayer ? (string)$targetPlayer['user_id'] : null,
            'target_name' => $targetName,
            'target_email' => $targetPlayer ? (string)($targetPlayer['email'] ?? '') : null,
            'color' => $splatColor,
            'round' => (int)($room['current_round'] ?? 1),
            'ts' => (int)(microtime(true) * 1000),
        ];

        $ins = $this->pdo->prepare("
            INSERT INTO room_events (id, room_id, round_index, user_id, email, event_type, payload_json, created_at)
            VALUES (:id, :room_id, :round_index, :user_id, :email, 'powerup', :payload_json, :created_at)
        ");
        $ins->execute([
            ':id' => $eventId,
            ':room_id' => $roomId,
            ':round_index' => (int)($room['current_round'] ?? 1),
            ':user_id' => $userId,
            ':email' => $userEmail,
            ':payload_json' => json_encode($payloadData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => Database::nowUtc(),
        ]);

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:powerup', $payloadData);
        }

        return ['ok' => true, 'payload' => $payloadData];
    }

    /**
     * Get teams configuration for a room (2-4 teams).
     *
     * @param array $roomRow Room database record
     * @return array<int, array{id: string, name: string, color: string}>
     */
    public function getRoomTeams(array $roomRow): array {
        if (!empty($roomRow['settings_json'])) {
            $decoded = json_decode((string)$roomRow['settings_json'], true);
            if (!empty($decoded['teams']) && is_array($decoded['teams'])) {
                return $decoded['teams'];
            }
        }
        return [
            ['id' => 'red', 'name' => 'Red', 'color' => '#ef4444'],
            ['id' => 'blue', 'name' => 'Blue', 'color' => '#3b82f6'],
        ];
    }

    /**
     * Set a player's team in the room lobby.
     *
     * @param string $guid Room GUID
     * @param string $userId Player user ID
     * @param string $team Team name ('red', 'blue', 'green', 'yellow')
     * @return array{ok: bool, error?: string, code?: int, team?: string, players?: array<int, array<string, mixed>>}
     */
    public function chooseTeam(string $guid, string $userId, string $team): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'code' => 404, 'error' => 'room_not_found'];
        }

        $roomTeams = $this->getRoomTeams($room);
        $allowed = array_column($roomTeams, 'id');
        if (empty($allowed)) {
            $allowed = ['red', 'blue', 'green', 'yellow'];
        }

        if (!in_array($team, $allowed, true)) {
            return ['ok' => false, 'code' => 400, 'error' => 'invalid_team'];
        }

        if (($room['status'] ?? '') !== 'waiting') {
            return ['ok' => false, 'code' => 400, 'error' => 'match_already_started'];
        }

        $roomId = (string)$room['id'];
        $ok = $this->roomRepo->setPlayerTeam($roomId, $userId, $team);
        if (!$ok) {
            return ['ok' => false, 'code' => 400, 'error' => 'update_failed'];
        }

        $players = $this->roomRepo->listPlayers($roomId);

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'players' => $players,
            ]);
        }

        return ['ok' => true, 'team' => $team, 'players' => $players];
    }

    /**
     * Konsepte uygun tasarlanmış 50 özel Prismatch avatarı.
     * Renkler, prizmalar, kristaller ve enerjik kromatik temalar içerir.
     *
     * @return array<int, array{id: string, icon: string, name: string, color: string, bg: string}>
     */
    public static function getConceptAvatars(): array {
        return [
            ['id' => '1', 'icon' => '💎', 'name' => 'Elmas Prizma', 'color' => '#00e5ff', 'bg' => 'linear-gradient(135deg, #00e5ff, #0077b6)'],
            ['id' => '2', 'icon' => '🔮', 'name' => 'Ametist Küre', 'color' => '#a855f7', 'bg' => 'linear-gradient(135deg, #a855f7, #6b21a8)'],
            ['id' => '3', 'icon' => '🌈', 'name' => 'Gökkuşağı', 'color' => '#ff007f', 'bg' => 'linear-gradient(135deg, #ff007f, #ffe600)'],
            ['id' => '4', 'icon' => '⚡', 'name' => 'Neon Şimşek', 'color' => '#ffe600', 'bg' => 'linear-gradient(135deg, #ffe600, #ff6b00)'],
            ['id' => '5', 'icon' => '🔥', 'name' => 'Kromik Alev', 'color' => '#ff3d00', 'bg' => 'linear-gradient(135deg, #ff3d00, #d50000)'],
            ['id' => '6', 'icon' => '💧', 'name' => 'Akvamarin', 'color' => '#00f5d4', 'bg' => 'linear-gradient(135deg, #00f5d4, #00bbf9)'],
            ['id' => '7', 'icon' => '🌟', 'name' => 'Süpernova', 'color' => '#ffd166', 'bg' => 'linear-gradient(135deg, #ffd166, #f72585)'],
            ['id' => '8', 'icon' => '🦄', 'name' => 'Tekboynuz', 'color' => '#f72585', 'bg' => 'linear-gradient(135deg, #f72585, #7209b7)'],
            ['id' => '9', 'icon' => '🐉', 'name' => 'Zümrüt Ejder', 'color' => '#10b981', 'bg' => 'linear-gradient(135deg, #10b981, #065f46)'],
            ['id' => '10', 'icon' => '🦊', 'name' => 'Kızıl Tilki', 'color' => '#fb923c', 'bg' => 'linear-gradient(135deg, #fb923c, #c2410c)'],
            ['id' => '11', 'icon' => '🦁', 'name' => 'Altın Aslan', 'color' => '#f59e0b', 'bg' => 'linear-gradient(135deg, #f59e0b, #b45309)'],
            ['id' => '12', 'icon' => '🐯', 'name' => 'Amber Kaplan', 'color' => '#ea580c', 'bg' => 'linear-gradient(135deg, #ea580c, #9a3412)'],
            ['id' => '13', 'icon' => '🐼', 'name' => 'Biyo Panda', 'color' => '#38bdf8', 'bg' => 'linear-gradient(135deg, #38bdf8, #1e293b)'],
            ['id' => '14', 'icon' => '🐨', 'name' => 'Pastel Koala', 'color' => '#94a3b8', 'bg' => 'linear-gradient(135deg, #94a3b8, #475569)'],
            ['id' => '15', 'icon' => '🦜', 'name' => 'Renk Papağanı', 'color' => '#22c55e', 'bg' => 'linear-gradient(135deg, #22c55e, #eab308)'],
            ['id' => '16', 'icon' => '🦚', 'name' => 'Safir Tavuskuşu', 'color' => '#3b82f6', 'bg' => 'linear-gradient(135deg, #3b82f6, #1d4ed8)'],
            ['id' => '17', 'icon' => '🦋', 'name' => 'Lila Kelebek', 'color' => '#c084fc', 'bg' => 'linear-gradient(135deg, #c084fc, #7e22ce)'],
            ['id' => '18', 'icon' => '🐝', 'name' => 'Güneş Arısı', 'color' => '#eab308', 'bg' => 'linear-gradient(135deg, #eab308, #ca8a04)'],
            ['id' => '19', 'icon' => '🐞', 'name' => 'Yakut Böcek', 'color' => '#ef4444', 'bg' => 'linear-gradient(135deg, #ef4444, #991b1b)'],
            ['id' => '20', 'icon' => '🐙', 'name' => 'Mor Kraken', 'color' => '#8b5cf6', 'bg' => 'linear-gradient(135deg, #8b5cf6, #5b21b6)'],
            ['id' => '21', 'icon' => '🐬', 'name' => 'Turkuaz Yunus', 'color' => '#06b6d4', 'bg' => 'linear-gradient(135deg, #06b6d4, #0e7490)'],
            ['id' => '22', 'icon' => '🦈', 'name' => 'Okyanus Köpekbalığı', 'color' => '#0284c7', 'bg' => 'linear-gradient(135deg, #0284c7, #075985)'],
            ['id' => '23', 'icon' => '🪐', 'name' => 'Kozmik Satürn', 'color' => '#f43f5e', 'bg' => 'linear-gradient(135deg, #f43f5e, #881337)'],
            ['id' => '24', 'icon' => '🚀', 'name' => 'Prizma Roket', 'color' => '#ec4899', 'bg' => 'linear-gradient(135deg, #ec4899, #be185d)'],
            ['id' => '25', 'icon' => '🛸', 'name' => 'Galaksi Gemisi', 'color' => '#14b8a6', 'bg' => 'linear-gradient(135deg, #14b8a6, #0f766e)'],
            ['id' => '26', 'icon' => '👾', 'name' => 'Piksel İstilacı', 'color' => '#84cc16', 'bg' => 'linear-gradient(135deg, #84cc16, #4d7c0f)'],
            ['id' => '27', 'icon' => '🎭', 'name' => 'Renk Maskesi', 'color' => '#f472b6', 'bg' => 'linear-gradient(135deg, #f472b6, #db2777)'],
            ['id' => '28', 'icon' => '👑', 'name' => 'Prizma Tacı', 'color' => '#eab308', 'bg' => 'linear-gradient(135deg, #eab308, #b45309)'],
            ['id' => '29', 'icon' => '🏆', 'name' => 'Şampiyon Kupa', 'color' => '#f59e0b', 'bg' => 'linear-gradient(135deg, #f59e0b, #d97706)'],
            ['id' => '30', 'icon' => '🎨', 'name' => 'Sanat Paleti', 'color' => '#ec4899', 'bg' => 'linear-gradient(135deg, #ec4899, #8b5cf6)'],
            ['id' => '31', 'icon' => '🪄', 'name' => 'Sihir Değneği', 'color' => '#d946ef', 'bg' => 'linear-gradient(135deg, #d946ef, #a21caf)'],
            ['id' => '32', 'icon' => '🧪', 'name' => 'Simya İksiri', 'color' => '#22c55e', 'bg' => 'linear-gradient(135deg, #22c55e, #15803d)'],
            ['id' => '33', 'icon' => '🧬', 'name' => 'Kromozom', 'color' => '#06b6d4', 'bg' => 'linear-gradient(135deg, #06b6d4, #4338ca)'],
            ['id' => '34', 'icon' => '🌺', 'name' => 'Neon Nilüfer', 'color' => '#f43f5e', 'bg' => 'linear-gradient(135deg, #f43f5e, #e11d48)'],
            ['id' => '35', 'icon' => '🍀', 'name' => 'Şans Yoncası', 'color' => '#10b981', 'bg' => 'linear-gradient(135deg, #10b981, #047857)'],
            ['id' => '36', 'icon' => '🍄', 'name' => 'Siber Mantar', 'color' => '#ef4444', 'bg' => 'linear-gradient(135deg, #ef4444, #b91c1c)'],
            ['id' => '37', 'icon' => '🍒', 'name' => 'Yakut Kiraz', 'color' => '#e11d48', 'bg' => 'linear-gradient(135deg, #e11d48, #9f1239)'],
            ['id' => '38', 'icon' => '🍉', 'name' => 'Karpuz Dilimi', 'color' => '#f43f5e', 'bg' => 'linear-gradient(135deg, #f43f5e, #10b981)'],
            ['id' => '39', 'icon' => '🥑', 'name' => 'Lime Avokado', 'color' => '#84cc16', 'bg' => 'linear-gradient(135deg, #84cc16, #365314)'],
            ['id' => '40', 'icon' => '🍋', 'name' => 'Sitrin Limon', 'color' => '#facc15', 'bg' => 'linear-gradient(135deg, #facc15, #ca8a04)'],
            ['id' => '41', 'icon' => '🍇', 'name' => 'Ametist Salkım', 'color' => '#9333ea', 'bg' => 'linear-gradient(135deg, #9333ea, #6b21a8)'],
            ['id' => '42', 'icon' => '🫐', 'name' => 'İndigo Yabanmersini', 'color' => '#4f46e5', 'bg' => 'linear-gradient(135deg, #4f46e5, #312e81)'],
            ['id' => '43', 'icon' => '🍭', 'name' => 'Gökkuşağı Şeker', 'color' => '#fb7185', 'bg' => 'linear-gradient(135deg, #fb7185, #38bdf8)'],
            ['id' => '44', 'icon' => '🍩', 'name' => 'Galaksi Donut', 'color' => '#a855f7', 'bg' => 'linear-gradient(135deg, #a855f7, #ec4899)'],
            ['id' => '45', 'icon' => '🧁', 'name' => 'Pastel Kapkek', 'color' => '#f472b6', 'bg' => 'linear-gradient(135deg, #f472b6, #fb923c)'],
            ['id' => '46', 'icon' => '🍦', 'name' => 'Neon Dondurma', 'color' => '#38bdf8', 'bg' => 'linear-gradient(135deg, #38bdf8, #f43f5e)'],
            ['id' => '47', 'icon' => '🧊', 'name' => 'Kristal Buz', 'color' => '#67e8f9', 'bg' => 'linear-gradient(135deg, #67e8f9, #0284c7)'],
            ['id' => '48', 'icon' => '☀️', 'name' => 'Güneş Halesi', 'color' => '#f59e0b', 'bg' => 'linear-gradient(135deg, #f59e0b, #ea580c)'],
            ['id' => '49', 'icon' => '🌙', 'name' => 'Ay Işığı', 'color' => '#818cf8', 'bg' => 'linear-gradient(135deg, #818cf8, #3730a3)'],
            ['id' => '50', 'icon' => '💫', 'name' => 'Kozmik Kıvılcım', 'color' => '#fbbf24', 'bg' => 'linear-gradient(135deg, #fbbf24, #d946ef)'],
        ];
    }

    /**
     * ID'ye göre avatar detayını döndürür.
     */
    public static function getAvatarById(string $avatarId): ?array {
        foreach (self::getConceptAvatars() as $av) {
            if ((string)$av['id'] === (string)$avatarId) {
                return $av;
            }
        }
        return null;
    }

    /**
     * Oyuncu avatar seçimi. Seçilmiş bir avatarı başka bir oyuncu seçemez.
     *
     * @param string $guid Room GUID
     * @param string $userId Player user ID
     * @param string $avatarId Seçilen avatar ID'si (1 - 50)
     * @return array{ok: bool, error?: string, code?: int, avatar?: string, players?: array<int, array<string, mixed>>}
     */
    public function chooseAvatar(string $guid, string $userId, string $avatarId): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'code' => 404, 'error' => 'room_not_found'];
        }

        $allAvatars = self::getConceptAvatars();
        $validIds = array_column($allAvatars, 'id');
        if (!in_array($avatarId, $validIds, true)) {
            return ['ok' => false, 'code' => 400, 'error' => 'invalid_avatar'];
        }

        $roomId = (string)$room['id'];
        $ok = $this->roomRepo->setPlayerAvatar($roomId, $userId, $avatarId);
        if (!$ok) {
            return [
                'ok' => false,
                'code' => 409,
                'error' => 'avatar_already_taken',
                'msg' => 'Bu avatar başka bir oyuncu tarafından seçildi! Lütfen başka bir avatar seçin.'
            ];
        }

        $players = $this->roomRepo->listPlayers($roomId);

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'players' => $players,
            ]);
        }

        return ['ok' => true, 'avatar' => $avatarId, 'players' => $players];
    }

    /**
     * Compute funny and prestigious end-of-match awards for players in the room.
     *
     * @param string $roomId Room database ID
     * @return array<int, array<string, mixed>> List of awarded badges
     */
    public function getRoomAwards(string $roomId): array {
        $players = $this->roomRepo->listPlayers($roomId);
        if (empty($players)) {
            return [];
        }

        $stmt = $this->pdo->prepare("
            SELECT user_id, event_type, payload_json, round_index
            FROM room_events
            WHERE room_id = :rid AND event_type IN ('answer', 'timeout', 'eliminate', 'powerup')
            ORDER BY round_index ASC
        ");
        $stmt->execute([':rid' => $roomId]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $userMetrics = [];
        foreach ($players as $p) {
            $uid = (string)$p['user_id'];
            $userMetrics[$uid] = [
                'user_id' => $uid,
                'email' => (string)($p['email'] ?? ''),
                'score' => (int)($p['score'] ?? 0),
                'correct' => (int)($p['correct'] ?? 0),
                'total_rounds' => 0,
                'total_ms' => 0,
                'fastest_ms' => null,
                'slowest_ms' => null,
                'current_streak' => 0,
                'max_streak' => 0,
                'wrong_fast_ms' => null,
                'used_sabotage' => false,
            ];
        }

        foreach ($events as $ev) {
            $uid = (string)$ev['user_id'];
            if (!isset($userMetrics[$uid])) continue;
            $payload = json_decode((string)$ev['payload_json'], true) ?: [];

            if (($ev['event_type'] ?? '') === 'powerup') {
                if (($payload['type'] ?? '') === 'ink_splat') {
                    $userMetrics[$uid]['used_sabotage'] = true;
                }
                continue;
            }

            $isCorrect = !empty($payload['correct']);
            $ms = (int)($payload['response_ms'] ?? 0);

            $userMetrics[$uid]['total_rounds']++;
            if ($ms > 0) {
                $userMetrics[$uid]['total_ms'] += $ms;
                if ($userMetrics[$uid]['fastest_ms'] === null || $ms < $userMetrics[$uid]['fastest_ms']) {
                    $userMetrics[$uid]['fastest_ms'] = $ms;
                }
                if ($userMetrics[$uid]['slowest_ms'] === null || $ms > $userMetrics[$uid]['slowest_ms']) {
                    $userMetrics[$uid]['slowest_ms'] = $ms;
                }
            }

            if ($isCorrect) {
                $userMetrics[$uid]['current_streak']++;
                if ($userMetrics[$uid]['current_streak'] > $userMetrics[$uid]['max_streak']) {
                    $userMetrics[$uid]['max_streak'] = $userMetrics[$uid]['current_streak'];
                }
            } else {
                $userMetrics[$uid]['current_streak'] = 0;
                if ($ms > 0 && ($userMetrics[$uid]['wrong_fast_ms'] === null || $ms < $userMetrics[$uid]['wrong_fast_ms'])) {
                    $userMetrics[$uid]['wrong_fast_ms'] = $ms;
                }
            }
        }

        $awards = [];

        // 1. 🚀 Işık Hızı / Speed Demon (Lowest average ms)
        $fastestPlayer = null;
        $bestAvg = 999999;
        foreach ($userMetrics as $uid => $m) {
            if ($m['total_rounds'] > 0 && $m['total_ms'] > 0) {
                $avg = $m['total_ms'] / $m['total_rounds'];
                if ($avg < $bestAvg) {
                    $bestAvg = $avg;
                    $fastestPlayer = $m;
                }
            }
        }
        if ($fastestPlayer && $bestAvg < 999999) {
            $awards[] = [
                'id' => 'speed_demon',
                'icon' => '⚡',
                'title' => 'Işık Hızı / Speed Demon',
                'user_id' => $fastestPlayer['user_id'],
                'email' => $fastestPlayer['email'],
                'stat' => (int)round($bestAvg) . 'ms ortalama',
                'desc' => 'Düşünmeden tıkladı, fareyi alevlendirdi!',
            ];
        }

        // 2. 🧐 Aşırı Düşünen / The Overthinker (Slowest average ms)
        $slowestPlayer = null;
        $worstAvg = 0;
        foreach ($userMetrics as $uid => $m) {
            if ($m['total_rounds'] > 0 && $m['total_ms'] > 0) {
                $avg = $m['total_ms'] / $m['total_rounds'];
                if ($avg > $worstAvg && ($fastestPlayer === null || $fastestPlayer['user_id'] !== $uid || count($userMetrics) === 1)) {
                    $worstAvg = $avg;
                    $slowestPlayer = $m;
                }
            }
        }
        if ($slowestPlayer && $worstAvg > 1000) {
            $awards[] = [
                'id' => 'overthinker',
                'icon' => '🧐',
                'title' => 'Aşırı Düşünen / Overthinker',
                'user_id' => $slowestPlayer['user_id'],
                'email' => $slowestPlayer['email'],
                'stat' => (int)round($worstAvg) . 'ms ortalama',
                'desc' => 'Son milisaniyeye kadar pikselleri inceledi!',
            ];
        }

        // 3. 🔥 Alev Topu / Streak Master (Highest streak >= 2)
        $streakPlayer = null;
        $highestStreak = 1;
        foreach ($userMetrics as $uid => $m) {
            if ($m['max_streak'] > $highestStreak) {
                $highestStreak = $m['max_streak'];
                $streakPlayer = $m;
            }
        }
        if ($streakPlayer && $highestStreak >= 2) {
            $awards[] = [
                'id' => 'streak_master',
                'icon' => '🔥',
                'title' => 'Alev Topu / Streak Master',
                'user_id' => $streakPlayer['user_id'],
                'email' => $streakPlayer['email'],
                'stat' => $highestStreak . 'x Seri Kombo',
                'desc' => 'Dur durak bilmedi, üst üste bildi!',
            ];
        }

        // 4. 🎯 Keskin Nişancı / Sniper (Highest accuracy % with >= 2 rounds)
        $sniperPlayer = null;
        $bestAcc = 0;
        foreach ($userMetrics as $uid => $m) {
            if ($m['total_rounds'] >= 2) {
                $acc = ($m['correct'] / $m['total_rounds']) * 100;
                if ($acc > $bestAcc) {
                    $bestAcc = $acc;
                    $sniperPlayer = $m;
                }
            }
        }
        if ($sniperPlayer && $bestAcc >= 50) {
            $awards[] = [
                'id' => 'sniper',
                'icon' => '🎯',
                'title' => 'Keskin Nişancı / Sniper',
                'user_id' => $sniperPlayer['user_id'],
                'email' => $sniperPlayer['email'],
                'stat' => '%' . (int)round($bestAcc) . ' İsabet',
                'desc' => 'Gözünü hedeften hiç ayırmadı!',
            ];
        }

        // 5. 🥔 Cesur Yürek / YOLO (Fastest wrong pick)
        $yoloPlayer = null;
        $fastestWrong = 999999;
        foreach ($userMetrics as $uid => $m) {
            if ($m['wrong_fast_ms'] !== null && $m['wrong_fast_ms'] < $fastestWrong) {
                $fastestWrong = $m['wrong_fast_ms'];
                $yoloPlayer = $m;
            }
        }
        if ($yoloPlayer && $fastestWrong < 2000) {
            $awards[] = [
                'id' => 'yolo',
                'icon' => '🥔',
                'title' => 'Cesur Yürek / YOLO',
                'user_id' => $yoloPlayer['user_id'],
                'email' => $yoloPlayer['email'],
                'stat' => $fastestWrong . 'ms (Hatalı)',
                'desc' => 'Çok hızlıydı ama yanlış renge uçtu!',
            ];
        }

        // 6. 🦑 Kaos Ajanı / Chaos Agent (Used ink splat sabotage)
        $chaosPlayer = null;
        foreach ($userMetrics as $uid => $m) {
            if (!empty($m['used_sabotage'])) {
                $chaosPlayer = $m;
                break;
            }
        }
        if ($chaosPlayer) {
            $awards[] = [
                'id' => 'chaos_agent',
                'icon' => '🦑',
                'title' => 'Kaos Ajanı / Chaos Agent',
                'user_id' => $chaosPlayer['user_id'],
                'email' => $chaosPlayer['email'],
                'stat' => 'Mürekkep Sıçrattı',
                'desc' => 'Ortalığı karıştırdı, dostluğu test etti!',
            ];
        }

        // 7. 👑 Maçın MVP'si / Match MVP (Highest score in teams mode)
        $room = $this->roomRepo->getRoomById($roomId);
        if (($room['game_mode'] ?? '') === 'teams') {
            $topPlayer = null;
            $topScore = -999999;
            foreach ($userMetrics as $uid => $m) {
                if ($m['score'] > $topScore) {
                    $topScore = $m['score'];
                    $topPlayer = $m;
                }
            }
            if ($topPlayer && $topScore > 0) {
                $awards[] = [
                    'id' => 'mvp',
                    'icon' => '👑',
                    'title' => 'Maçın MVP\'si / Match MVP',
                    'user_id' => $topPlayer['user_id'],
                    'email' => $topPlayer['email'],
                    'stat' => $topScore . ' Puan',
                    'desc' => 'Tüm oyuncular arasında zirveye oturarak takımının yıldızı oldu!',
                ];
            }
        }

        return $awards;
    }

    /**
     * Check if a player currently has access to a powerup based on streak rules.
     * Rule: Not given up-front. Earned after 5 consecutive correct answers,
     * and after each use, renewed after another 5 consecutive correct answers.
     *
     * @param string $roomId Room ID
     * @param string $userId User ID
     * @param string $type Powerup type ('fifty_fifty' or 'ink_splat')
     * @return array{available: bool, current_streak: int, required_streak: int}
     */
    public function checkPowerupAvailability(string $roomId, string $userId, string $type): array {
        $stmt = $this->pdo->prepare("
            SELECT round_index, event_type, payload_json
            FROM room_events
            WHERE room_id = :rid AND user_id = :uid AND event_type IN ('answer', 'timeout', 'eliminate', 'powerup')
            ORDER BY id ASC
        ");
        $stmt->execute([':rid' => $roomId, ':uid' => $userId]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $hasItem = false;
        $currentStreak = 0;

        foreach ($events as $ev) {
            $eType = (string)($ev['event_type'] ?? '');
            $payload = json_decode((string)($ev['payload_json'] ?? ''), true) ?: [];

            if ($eType === 'answer') {
                $isCorrect = !empty($payload['correct']);
                if ($isCorrect) {
                    $currentStreak++;
                    if (!$hasItem && $currentStreak >= self::POWERUP_STREAK_REQUIREMENT) {
                        $hasItem = true;
                    }
                } else {
                    $currentStreak = 0;
                }
            } elseif ($eType === 'timeout' || $eType === 'eliminate') {
                $currentStreak = 0;
            } elseif ($eType === 'powerup') {
                $pType = (string)($payload['type'] ?? '');
                if ($pType === $type) {
                    // This specific powerup was consumed; streak counter resets for next renewal
                    $hasItem = false;
                    $currentStreak = 0;
                }
            }
        }

        return [
            'available' => $hasItem,
            'current_streak' => $currentStreak,
            'required_streak' => self::POWERUP_STREAK_REQUIREMENT,
        ];
    }
}


