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

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
        $this->roomRepo = new RoomRepository($this->pdo);
        $this->userRepo = new UserRepository($this->pdo);
    }

    public function getTimingForRound(int $roundIndex): array {
        $lvl = max(1, $roundIndex);
        // Sonraki tura geçişte 3-2-1 saymasın, 1 saniye sonra başlasın (1000ms)
        $countdownMs = ($lvl > 1) ? 1000 : 3000;
        $answerMs = 5000;

        if ($lvl === 21 || $lvl === 41) {
            $showMs = 5000;
        } else {
            $showMs = (int)floor(3000 * pow(0.9, $lvl - 1));
            if ($showMs < 250) $showMs = 250;
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

            $timing = $this->getTimingForRound($nextRound);
            $freshPlayers = $this->roomRepo->listPlayers($roomId);

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

        if ($isCorrect) {
            $scoreDelta = max(50, 1000 - (int)floor(max(0, $responseMs) / 10));
            $this->roomRepo->addScore($roomId, $userId, $scoreDelta, 1);
            $this->roomRepo->logEvent($roomId, $round, $userId, $email, 'answer', [
                'picked' => $picked,
                'target' => $target,
                'response_ms' => $responseMs,
                'correct' => true,
                'score_delta' => $scoreDelta,
            ]);
            Logger::room('processAnswer:correct', $guid, ['user_id' => $userId, 'score_delta' => $scoreDelta, 'round' => $round]);
        } elseif ($isElimination) {
            $this->roomRepo->markEliminated($roomId, $userId, $round);
            $this->roomRepo->logEvent($roomId, $round, $userId, $email, $isTimeout ? 'timeout' : 'eliminate', [
                'picked' => $picked !== '' ? $picked : null,
                'target' => $target,
                'response_ms' => $responseMs,
                'correct' => false,
            ]);
            Logger::room('processAnswer:eliminated', $guid, ['user_id' => $userId, 'timeout' => $isTimeout, 'round' => $round]);
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

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:finished', [
                'guid' => $guid,
                'round' => $round,
                'players' => $finalPlayers,
                'winner' => $winner ? [
                    'user_id' => $winner['user_id'],
                    'email' => $winner['email'],
                    'bonus' => 5000,
                ] : null,
            ]);
        }

        Logger::room('finishGame:success', $guid, ['round' => $round, 'players_count' => count($finalPlayers)]);

        return [
            'ok' => true,
            'finished' => true,
            'round' => $round,
            'players' => $finalPlayers,
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
            return ['ok' => true, 'status' => 'finished', 'finished' => true, 'players' => $players];
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

        $timing = $this->getTimingForRound($current);
        $countdownMs = $timing['countdown_ms'];
        $showMs = $timing['show_ms'];
        $answerMs = $timing['answer_ms'];
        $intermissionMs = 2500; // 2.5s smooth intermission

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startedAt = new \DateTimeImmutable($round['started_at'], new \DateTimeZone('UTC'));
        $endedAt = $round['ended_at'] ? new \DateTimeImmutable($round['ended_at'], new \DateTimeZone('UTC')) : null;

        // Accurate millisecond elapsed calculation
        $elapsed = (int)round(((float)$now->format('U.u') - (float)$startedAt->format('U.u')) * 1000);

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
                if ($isElimination) {
                    $this->roomRepo->markEliminated($roomId, $pUserId, $current);
                }
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
}

