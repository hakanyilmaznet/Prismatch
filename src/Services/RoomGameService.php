<?php
declare(strict_types=1);

namespace Prismatch\Services;

use PDO;
use Prismatch\Core\Database;
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
        $countdownMs = 3000;
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

    public function getGridCountForRound(int $roundIndex, int $roundsTotal = 50): int {
        $lvl = max(1, $roundIndex);
        if ($lvl <= 20) return 9;
        if ($lvl <= 40) return 16;
        return 25;
    }

    public function generateQuestion(int $roundIndex, int $roundsTotal = 50): array {
        $palette = array_values(array_unique(self::COLOR_PALETTE));
        $count = $this->getGridCountForRound($roundIndex, $roundsTotal);
        shuffle($palette);
        $grid = array_slice($palette, 0, $count);
        $target = $grid[array_rand($grid)];
        return ['target' => $target, 'grid' => $grid, 'gridCount' => $count];
    }

    public function joinRoom(string $guid, string $userId, string $email): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        $added = $this->roomRepo->addPlayer($roomId, $userId, $email);
        if (!$added) {
            return ['ok' => false, 'error' => 'room_full', 'code' => 403];
        }

        $players = $this->roomRepo->listPlayers($roomId);

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
            ],
            'players' => $players,
        ];
    }

    public function startOrNextRound(string $guid, string $userId, bool $requireHost = true): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        if ($requireHost && ($room['owner_id'] ?? null) !== $userId) {
            return ['ok' => false, 'error' => 'not_owner', 'code' => 403];
        }

        $roomId = (string)$room['id'];
        $current = (int)$room['current_round'];
        $total = (int)$room['rounds_total'];

        if ($room['status'] === 'finished') {
            return ['ok' => true, 'finished' => true];
        }

        // Multiplayer rooms require at least 2 players to start
        if ($current === 0 || $room['status'] === 'waiting') {
            $players = $this->roomRepo->listPlayers($roomId);
            if (count($players) < 2) {
                return ['ok' => false, 'error' => 'min_players_required', 'code' => 400];
            }
        }

        return $this->advanceToRound($room, $current + 1);
    }

    public function restartRoom(string $guid, string $userId, bool $startImmediately = false): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        if (($room['owner_id'] ?? null) !== $userId) {
            return ['ok' => false, 'error' => 'not_owner', 'code' => 403];
        }

        $roomId = (string)$room['id'];
        $now = Database::nowUtc();

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

            // Reset all players in the room to active, 0 score, 0 correct, clear elimination
            $stmtPlayers = $this->pdo->prepare("
                UPDATE room_players
                SET status = 'active',
                    eliminated_round = NULL,
                    score = 0,
                    correct = 0,
                    last_active = :now
                WHERE room_id = :rid
            ");
            $stmtPlayers->execute([':now' => $now, ':rid' => $roomId]);

            // Clean up previous rounds and events for clean state
            $stmtDelRounds = $this->pdo->prepare("DELETE FROM room_rounds WHERE room_id = :rid");
            $stmtDelRounds->execute([':rid' => $roomId]);

            $stmtDelEvents = $this->pdo->prepare("DELETE FROM room_events WHERE room_id = :rid");
            $stmtDelEvents->execute([':rid' => $roomId]);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['ok' => false, 'error' => 'db_error', 'code' => 500, 'detail' => $e->getMessage()];
        }

        $freshPlayers = $this->roomRepo->listPlayers($roomId);

        if ($startImmediately) {
            $freshRoom = $this->roomRepo->getRoomById($roomId);
            if ($freshRoom) {
                if (count($freshPlayers) < 2) {
                    return ['ok' => false, 'error' => 'min_players_required', 'code' => 400];
                }
                return $this->advanceToRound($freshRoom, 1);
            }
        }

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:reset', [
                'guid' => $guid,
                'status' => 'waiting',
                'round' => 0,
                'players' => $freshPlayers,
            ]);
        }

        return [
            'ok' => true,
            'status' => 'waiting',
            'round' => 0,
            'players' => $freshPlayers,
        ];
    }

    public function advanceToRound(array $room, int $nextRound): array {
        $roomId = (string)$room['id'];
        $guid = (string)$room['guid'];
        $total = (int)$room['rounds_total'];
        $current = (int)$room['current_round'];

        // Atomic lock check: only advance if the round matches current
        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare("SELECT status, current_round, rounds_total FROM rooms WHERE id = :id FOR UPDATE");
            $lockStmt->execute([':id' => $roomId]);
            $locked = $lockStmt->fetch();

            if (!$locked || $locked['status'] === 'finished') {
                $this->pdo->rollBack();
                return ['ok' => true, 'finished' => true];
            }

            $currentInDb = (int)$locked['current_round'];
            if ($currentInDb >= $nextRound) {
                // Already advanced by another thread/request
                $this->pdo->rollBack();
                return ['ok' => true, 'already_advanced' => true, 'round' => $currentInDb];
            }

            // Check alive players
            $players = $this->roomRepo->listPlayers($roomId);
            $activeCount = 0;
            foreach ($players as $p) {
                if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
            }

            if (($activeCount <= 1 && count($players) >= 2 && $currentInDb > 0) || ($activeCount === 0 && $currentInDb > 0) || $nextRound > $total) {
                $this->pdo->commit();
                $finishResult = $this->finishGame($roomId, $currentInDb, $guid);
                return ['ok' => true, 'finished' => true, 'players' => $finishResult['players']];
            }

            // Update room status to active if waiting, requiring at least 2 players
            if ($currentInDb === 0) {
                if (count($players) < 2) {
                    $this->pdo->rollBack();
                    return ['ok' => false, 'error' => 'min_players_required', 'code' => 400];
                }
                $this->roomRepo->setStatus($roomId, 'active');
            }

            // Generate question
            $question = $this->generateQuestion($nextRound, $total);
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
            $payload = [
                'guid' => $guid,
                'round' => $nextRound,
                'rounds_total' => $total,
                'question' => $question,
                'countdown_ms' => $timing['countdown_ms'],
                'show_ms' => $timing['show_ms'],
                'answer_ms' => $timing['answer_ms'],
            ];

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:round', $payload);
            }

            return ['ok' => true, 'round' => $nextRound];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Failed advanceToRound: " . $e->getMessage());
            return ['ok' => false, 'error' => 'advance_failed', 'msg' => $e->getMessage()];
        }
    }

    public function processAnswer(string $guid, string $userId, string $email, int $round, string $picked, bool $isTimeout, int $responseMs): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['ok' => false, 'error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        if ($room['status'] !== 'active') {
            return ['ok' => false, 'error' => 'room_not_active', 'code' => 409];
        }

        // Verify player is currently active
        $players = $this->roomRepo->listPlayers($roomId);
        $me = null;
        foreach ($players as $p) {
            if ($p['user_id'] === $userId) {
                $me = $p;
                break;
            }
        }
        if (!$me || $me['status'] !== 'active') {
            return ['ok' => false, 'error' => 'not_active', 'code' => 403];
        }

        // Fetch round question
        $qStmt = $this->pdo->prepare("SELECT question_json FROM room_rounds WHERE room_id = :rid AND round_index = :r LIMIT 1");
        $qStmt->execute([':rid' => $roomId, ':r' => $round]);
        $qRow = $qStmt->fetchColumn();
        if (!$qRow) {
            return ['ok' => false, 'error' => 'round_not_found', 'code' => 404];
        }

        $question = json_decode((string)$qRow, true);
        $target = $question['target'] ?? '';
        if ($target === '') {
            return ['ok' => false, 'error' => 'invalid_round', 'code' => 500];
        }

        // Prevent duplicate answers for the same round
        $dupStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM room_events
            WHERE room_id = :rid AND round_index = :r AND user_id = :uid AND event_type IN ('answer', 'eliminate', 'timeout')
        ");
        $dupStmt->execute([':rid' => $roomId, ':r' => $round, ':uid' => $userId]);
        if ((int)$dupStmt->fetchColumn() > 0) {
            return ['ok' => true, 'already_answered' => true];
        }

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
        } else {
            $this->roomRepo->markEliminated($roomId, $userId, $round);
            $this->roomRepo->logEvent($roomId, $round, $userId, $email, $isTimeout ? 'timeout' : 'eliminate', [
                'picked' => $picked !== '' ? $picked : null,
                'target' => $target,
                'response_ms' => $responseMs,
                'correct' => false,
            ]);
        }

        $freshPlayers = $this->roomRepo->listPlayers($roomId);
        $activeCount = 0;
        foreach ($freshPlayers as $p) {
            if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
        }

        if (function_exists('pusher_trigger')) {
            pusher_trigger('presence-room-' . $guid, 'room:update', [
                'guid' => $guid,
                'round' => $round,
                'players' => $freshPlayers,
                'eliminated' => $isCorrect ? null : $email,
            ]);
        }

        // Winner determined: when only 1 non-eliminated player remains (or all players eliminated)
        if (($activeCount <= 1 && count($freshPlayers) >= 2) || $activeCount === 0) {
            $finishResult = $this->finishGame($roomId, $round, $guid);
            return [
                'ok' => true,
                'correct' => $isCorrect,
                'score_delta' => $scoreDelta,
                'finished' => true,
                'players' => $finishResult['players'],
            ];
        }

        // IMPROVED MECHANIC: Check if ALL active players have answered this round!
        // If everyone answered, immediately end the round early instead of waiting 5s!
        $ansCountStmt = $this->pdo->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM room_events
            WHERE room_id = :rid AND round_index = :r AND event_type IN ('answer', 'eliminate', 'timeout')
        ");
        $ansCountStmt->execute([':rid' => $roomId, ':r' => $round]);
        $answeredCount = (int)$ansCountStmt->fetchColumn();

        // Total remaining players before this round
        $totalRoundParticipants = count($freshPlayers);
        // Active participants in this round
        if ($answeredCount >= $activeCount) {
            // All active players have answered! End round immediately!
            $this->endRound($roomId, $round);
            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $round,
                    'players' => $freshPlayers,
                ]);
            }
        }

        return ['ok' => true, 'correct' => $isCorrect, 'score_delta' => $scoreDelta];
    }

    public function endRound(string $roomId, int $roundIndex): void {
        $stmt = $this->pdo->prepare("
            UPDATE room_rounds
            SET ended_at = COALESCE(ended_at, :now)
            WHERE room_id = :rid AND round_index = :r
        ");
        $stmt->execute([':now' => Database::nowUtc(), ':rid' => $roomId, ':r' => $roundIndex]);
    }

    public function finishGame(string $roomId, int $round, string $guid): array {
        $this->endRound($roomId, $round);

        // Fetch players sorted by: (status = 'active') DESC, score DESC, correct DESC, joined_at ASC
        $players = $this->roomRepo->listPlayers($roomId);
        $winner = !empty($players) ? $players[0] : null;

        // Award 5000 win bonus points to the winner if not already awarded
        if ($winner && !empty($winner['user_id'])) {
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

                try {
                    $updUser = $this->pdo->prepare("UPDATE users SET total_wins = total_wins + 1 WHERE id = :uid");
                    $updUser->execute([':uid' => $winnerUserId]);
                } catch (\Throwable $e) {}
            }
        }

        $this->roomRepo->setStatus($roomId, 'finished', $round);
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

        return [
            'ok' => true,
            'finished' => true,
            'round' => $round,
            'players' => $finalPlayers,
        ];
    }

    public function tick(string $guid): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        if ($room['status'] === 'finished' || (int)$room['rounds_total'] <= 0) {
            $players = $this->roomRepo->listPlayers($roomId);
            return ['ok' => true, 'status' => 'finished', 'finished' => true, 'players' => $players];
        }

        $current = (int)$room['current_round'];
        if ($room['status'] === 'waiting' && $current === 0) {
            return ['ok' => true, 'status' => 'waiting'];
        }

        $st = $this->pdo->prepare("SELECT started_at, ended_at, question_json FROM room_rounds WHERE room_id = :rid AND round_index = :r LIMIT 1");
        $st->execute([':rid' => $roomId, ':r' => $current]);
        $round = $st->fetch();
        if (!$round) {
            return ['ok' => true];
        }

        $timing = $this->getTimingForRound($current);
        $countdownMs = $timing['countdown_ms'];
        $showMs = $timing['show_ms'];
        $answerMs = $timing['answer_ms'];
        $intermissionMs = 4000; // slightly snappier 4 seconds

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $startedAt = new \DateTimeImmutable($round['started_at'], new \DateTimeZone('UTC'));
        $endedAt = $round['ended_at'] ? new \DateTimeImmutable($round['ended_at'], new \DateTimeZone('UTC')) : null;

        $elapsed = ($now->getTimestamp() - $startedAt->getTimestamp()) * 1000;

        // Auto-end round when time runs out
        if ($endedAt === null && $elapsed >= ($countdownMs + $showMs + $answerMs)) {
            $this->endRound($roomId, $current);

            // Eliminate players who didn't answer in time
            $stc = $this->pdo->prepare("
                SELECT email
                FROM room_events
                WHERE room_id = :rid AND round_index = :r AND event_type = 'answer' AND payload_json LIKE '%\"correct\":true%'
            ");
            $stc->execute([':rid' => $roomId, ':r' => $current]);
            $correctEmails = $stc->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
            $correctSet = array_flip($correctEmails);

            $question = json_decode((string)$round['question_json'], true);
            $target = $question['target'] ?? null;

            $players = $this->roomRepo->listPlayers($roomId);
            foreach ($players as $p) {
                if (($p['status'] ?? '') === 'eliminated') continue;
                $pEmail = (string)($p['email'] ?? '');
                $pUserId = (string)($p['user_id'] ?? '');
                if ($pEmail === '' || isset($correctSet[$pEmail])) continue;

                $this->roomRepo->markEliminated($roomId, $pUserId, $current);
                $this->roomRepo->logEvent($roomId, $current, $pUserId, $pEmail, 'timeout', [
                    'picked' => null,
                    'target' => $target,
                    'response_ms' => $answerMs,
                    'correct' => false,
                ]);
            }

            $freshPlayers = $this->roomRepo->listPlayers($roomId);
            $activeCount = 0;
            foreach ($freshPlayers as $p) {
                if (($p['status'] ?? '') !== 'eliminated') $activeCount++;
            }

            // Winner determined: when only 1 non-eliminated player remains (or all players eliminated)
            if (($activeCount <= 1 && count($freshPlayers) >= 2) || $activeCount === 0) {
                $finishResult = $this->finishGame($roomId, $current, $guid);
                return ['ok' => true, 'finished' => true, 'players' => $finishResult['players']];
            }

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $current,
                    'players' => $freshPlayers,
                ]);
            }

            return ['ok' => true, 'ended' => true];
        }

        // Intermission check: automatically advance to next round when intermission is over
        if ($endedAt !== null) {
            $elapsedEnd = ($now->getTimestamp() - $endedAt->getTimestamp()) * 1000;
            if ($elapsedEnd >= $intermissionMs) {
                return $this->advanceToRound($room, $current + 1);
            }
        }

        // If currently in active question phase, return current question info
        if ($endedAt === null) {
            $question = json_decode((string)$round['question_json'], true);
            if ($question) {
                return [
                    'ok' => true,
                    'round' => $current,
                    'rounds_total' => (int)$room['rounds_total'],
                    'question' => $question,
                    'countdown_ms' => $countdownMs,
                    'show_ms' => $showMs,
                    'answer_ms' => $answerMs,
                    'started_at' => $round['started_at'],
                    'elapsed_ms' => $elapsed,
                ];
            }
        }

        return ['ok' => true];
    }
}
