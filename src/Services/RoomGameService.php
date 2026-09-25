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

    public function getGridCountForRound(int $roundIndex, int $roundsTotal = 50): int {
        $lvl = max(1, $roundIndex);
        if ($lvl <= 10) return 4;
        if ($lvl <= 20) return 6;
        if ($lvl <= 30) return 9;
        if ($lvl <= 40) return 12;
        return 16;
    }

    public function generateQuestion(int $roundIndex, int $roundsTotal = 50): array {
        $palette = [
            "#000000","#FFFFFF","#FF0000","#00FF00","#0000FF","#FFFF00","#00FFFF","#FF00FF",
            "#FFA500","#800080","#00FF7F","#1E90FF","#DC143C","#FFD700","#8A2BE2","#00CED1",
            "#FF1493","#7FFF00","#FF8C00","#20B2AA","#ADFF2F","#FF69B4","#40E0D0","#B22222","#6A5ACD"
        ];
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

        return $this->advanceToRound($room, $current + 1);
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

            if ($activeCount === 0 && $currentInDb > 0) {
                $this->roomRepo->setStatus($roomId, 'finished', $currentInDb);
                $this->pdo->commit();
                if (function_exists('pusher_trigger')) {
                    pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                        'guid' => $guid,
                        'round' => $currentInDb,
                        'players' => $players,
                    ]);
                    pusher_trigger('presence-room-' . $guid, 'room:finished', [
                        'guid' => $guid,
                        'players' => $players,
                    ]);
                }
                return ['ok' => true, 'finished' => true, 'players' => $players];
            }

            if ($nextRound > $total) {
                $this->roomRepo->setStatus($roomId, 'finished', $currentInDb);
                $this->pdo->commit();
                $finalPlayers = $this->roomRepo->listPlayers($roomId);
                if (function_exists('pusher_trigger')) {
                    pusher_trigger('presence-room-' . $guid, 'room:finished', [
                        'guid' => $guid,
                        'players' => $finalPlayers,
                    ]);
                }
                return ['ok' => true, 'finished' => true, 'players' => $finalPlayers];
            }

            // Update room status to active if waiting
            if ($currentInDb === 0) {
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

        // Check if all players are eliminated
        if ($activeCount === 0) {
            $this->endRound($roomId, $round);
            $this->roomRepo->setStatus($roomId, 'finished', $round);
            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $round,
                    'players' => $freshPlayers,
                ]);
                pusher_trigger('presence-room-' . $guid, 'room:finished', [
                    'guid' => $guid,
                    'players' => $freshPlayers,
                ]);
            }
            return ['ok' => true, 'correct' => $isCorrect, 'score_delta' => $scoreDelta, 'finished' => true, 'players' => $freshPlayers];
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

    public function tick(string $guid): array {
        $room = $this->roomRepo->getRoomByGuid($guid);
        if (!$room) {
            return ['error' => 'not_found', 'code' => 404];
        }

        $roomId = (string)$room['id'];
        if ($room['status'] === 'finished' || (int)$room['rounds_total'] <= 0) {
            return ['ok' => true, 'status' => 'finished'];
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

            if (function_exists('pusher_trigger')) {
                pusher_trigger('presence-room-' . $guid, 'room:leaderboard', [
                    'guid' => $guid,
                    'round' => $current,
                    'players' => $freshPlayers,
                ]);
            }

            if ($activeCount === 0) {
                $this->roomRepo->setStatus($roomId, 'finished', $current);
                if (function_exists('pusher_trigger')) {
                    pusher_trigger('presence-room-' . $guid, 'room:finished', [
                        'guid' => $guid,
                        'players' => $freshPlayers,
                    ]);
                }
                return ['ok' => true, 'finished' => true, 'players' => $freshPlayers];
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
