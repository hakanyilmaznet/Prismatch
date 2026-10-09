<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Core\Logger;
use Prismatch\Contracts\RoomRepositoryInterface;

class RoomRepository implements RoomRepositoryInterface {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function createRoom(string $ownerId, string $ownerEmail, int $roundsTotal = 25, ?string $name = null, bool $isPrivate = false, string $gameMode = 'elimination', ?string $settingsJson = null): array {
        $roomId = Database::generateUuid();
        $guid = Database::generateUuid();
        $ownerEmail = strtolower(trim($ownerEmail));
        $now = Database::nowUtc();

        $cleanName = $name !== null ? trim($name) : null;
        if ($cleanName === '') $cleanName = null;

        $cleanMode = in_array($gameMode, ['elimination', 'points', 'flags', 'teams'], true) ? $gameMode : 'elimination';

        $stmt = $this->pdo->prepare("
            INSERT INTO rooms (
                id, guid, name, owner_id, owner_email,
                status, rounds_total, current_round, is_private, game_mode, settings_json, created_at, updated_at
            ) VALUES (
                :id, :guid, :name, :owner_id, :owner_email,
                'waiting', :rounds_total, 0, :is_private, :game_mode, :settings_json, :created_at, :updated_at
            )
        ");
        $stmt->execute([
            ':id' => $roomId,
            ':guid' => $guid,
            ':name' => $cleanName,
            ':owner_id' => $ownerId,
            ':owner_email' => $ownerEmail,
            ':rounds_total' => $roundsTotal,
            ':is_private' => $isPrivate ? 1 : 0,
            ':game_mode' => $cleanMode,
            ':settings_json' => $settingsJson,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $this->addPlayer($roomId, $ownerId, $ownerEmail);

        return [
            'id' => $roomId,
            'guid' => $guid,
            'name' => $cleanName,
            'owner_id' => $ownerId,
            'owner_email' => $ownerEmail,
            'status' => 'waiting',
            'rounds_total' => $roundsTotal,
            'current_round' => 0,
            'is_private' => $isPrivate ? 1 : 0,
            'game_mode' => $cleanMode,
            'settings_json' => $settingsJson,
            'created_at' => $now,
        ];
    }

    public function getRoomByGuid(string $guid): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM rooms WHERE guid = :guid LIMIT 1");
        $stmt->execute([':guid' => $guid]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getRoomById(string $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM rooms WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listUserRooms(string $userId, int $limit = 50): array {
        $this->cleanupInactiveRooms(60);
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT r.*
            FROM rooms r
            LEFT JOIN room_players rp ON rp.room_id = r.id
            WHERE (r.owner_id = :owner_id OR rp.user_id = :player_id)
            ORDER BY r.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':owner_id', $userId);
        $stmt->bindValue(':player_id', $userId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function listPublicRooms(int $limit = 20): array {
        $this->cleanupInactiveRooms(60);
        $stmt = $this->pdo->prepare("
            SELECT r.*,
                   COUNT(DISTINCT rp.id) AS player_count
            FROM rooms r
            LEFT JOIN room_players rp ON rp.room_id = r.id
            WHERE (r.is_private = 0 OR r.is_private IS NULL)
              AND r.status IN ('waiting', 'active')
            GROUP BY r.id
            ORDER BY (r.status = 'waiting') DESC, r.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function addPlayer(string $roomId, string $userId, string $email): bool {
        $email = strtolower(trim($email));

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM room_players WHERE room_id = :rid");
        $countStmt->execute([':rid' => $roomId]);
        $count = (int)$countStmt->fetchColumn();

        $roomStmt = $this->pdo->prepare("SELECT status, current_round, max_players FROM rooms WHERE id = :rid LIMIT 1");
        $roomStmt->execute([':rid' => $roomId]);
        $roomRow = $roomStmt->fetch(PDO::FETCH_ASSOC);
        $maxPlayers = (int)($roomRow['max_players'] ?? 25);
        if ($maxPlayers <= 0) $maxPlayers = 25;

        // Check if already in room
        $existsStmt = $this->pdo->prepare("SELECT id, status FROM room_players WHERE room_id = :rid AND user_id = :uid LIMIT 1");
        $existsStmt->execute([':rid' => $roomId, ':uid' => $userId]);
        $existing = $existsStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->touchPlayer($roomId, $userId);
            $this->touchRoom($roomId);
            return true;
        }

        if ($count >= $maxPlayers) {
            return false;
        }

        $isMatchInProgress = (($roomRow['status'] ?? 'waiting') === 'active' || (int)($roomRow['current_round'] ?? 0) > 0);
        $initialStatus = $isMatchInProgress ? 'eliminated' : 'active';
        $eliminatedRound = $isMatchInProgress ? (int)($roomRow['current_round'] ?? 1) : null;

        $team = null;
        if (($roomRow['game_mode'] ?? '') === 'teams') {
            try {
                $tStmt = $this->pdo->prepare("SELECT team, COUNT(*) as cnt FROM room_players WHERE room_id = :rid GROUP BY team");
                $tStmt->execute([':rid' => $roomId]);
                $counts = ['red' => 0, 'blue' => 0];
                foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $tName = (string)($row['team'] ?? '');
                    if (isset($counts[$tName])) {
                        $counts[$tName] = (int)$row['cnt'];
                    }
                }
                $team = ($counts['blue'] < $counts['red']) ? 'blue' : 'red';
            } catch (\Throwable $e) {
                $team = 'red';
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO room_players (
                id, room_id, user_id, email, joined_at, status, eliminated_round, score, correct, team, last_active
            ) VALUES (
                :id, :room_id, :user_id, :email, :joined_at, :status, :eliminated_round, 0, 0, :team, :last_active
            )
        ");
        $now = Database::nowUtc();
        $ok = $stmt->execute([
            ':id' => Database::generateUuid(),
            ':room_id' => $roomId,
            ':user_id' => $userId,
            ':email' => $email,
            ':joined_at' => $now,
            ':status' => $initialStatus,
            ':eliminated_round' => $eliminatedRound,
            ':team' => $team,
            ':last_active' => $now,
        ]);
        if ($ok) {
            $this->touchRoom($roomId);
        }
        return $ok;
    }

    public function setPlayerTeam(string $roomId, string $userId, string $team): bool {
        if (!in_array($team, ['red', 'blue', 'green', 'yellow'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare("UPDATE room_players SET team = :team WHERE room_id = :rid AND user_id = :uid");
        $ok = $stmt->execute([':team' => $team, ':rid' => $roomId, ':uid' => $userId]);
        if ($ok) {
            $this->touchRoom($roomId);
        }
        return $ok;
    }

    public function listPlayers(string $roomId): array {
        $cutoff = (new \DateTimeImmutable('-8 seconds', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare("
            SELECT user_id, email, status, eliminated_round, score, correct, joined_at, last_active, team,
                   CASE WHEN last_active IS NOT NULL AND last_active >= :cutoff THEN 1 ELSE 0 END AS is_online
            FROM room_players
            WHERE room_id = :rid
            ORDER BY (status = 'active') DESC, score DESC, correct DESC, joined_at ASC
        ");
        $stmt->execute([':rid' => $roomId, ':cutoff' => $cutoff]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            $r['is_online'] = (int)($r['is_online'] ?? 0);
            $email = trim((string)($r['email'] ?? ''));
            $displayName = ($email !== '' && str_contains($email, '@')) ? explode('@', $email)[0] : ($email !== '' ? $email : 'Player');
            $r['name'] = $displayName;
            $r['nickname'] = $displayName;
            $r['user_name'] = $displayName;
        }
        return $rows;
    }

    public function touchPlayer(string $roomId, string $userId, bool $reactivate = false): void {
        $now = Database::nowUtc();
        if ($reactivate) {
            $stmt = $this->pdo->prepare("
                UPDATE room_players
                SET last_active = :now, status = 'active'
                WHERE room_id = :rid AND user_id = :uid
            ");
        } else {
            $stmt = $this->pdo->prepare("
                UPDATE room_players
                SET last_active = :now
                WHERE room_id = :rid AND user_id = :uid
            ");
        }
        $stmt->execute([':now' => $now, ':rid' => $roomId, ':uid' => $userId]);
        $this->touchRoom($roomId);
    }

    public function removePlayer(string $roomId, string $userId): bool {
        $stmt = $this->pdo->prepare("
            DELETE FROM room_players
            WHERE room_id = :rid AND user_id = :uid
        ");
        $res = $stmt->execute([':rid' => $roomId, ':uid' => $userId]);
        $this->touchRoom($roomId);
        return $res;
    }

    public function cleanupStalePlayers(string $roomId, string $ownerId, int $staleSeconds = 8): int {
        $cutoff = (new \DateTimeImmutable("-{$staleSeconds} seconds", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');
        $stmt = $this->pdo->prepare("
            DELETE FROM room_players
            WHERE room_id = :rid 
              AND user_id != :owner_id
              AND (last_active IS NULL OR last_active < :cutoff)
        ");
        $stmt->execute([':rid' => $roomId, ':owner_id' => $ownerId, ':cutoff' => $cutoff]);
        $this->touchRoom($roomId);
        return $stmt->rowCount();
    }

    public function markEliminated(string $roomId, string $userId, int $roundIndex): void {
        $stmt = $this->pdo->prepare("
            UPDATE room_players
            SET status = 'eliminated', eliminated_round = :r
            WHERE room_id = :rid AND user_id = :uid AND status = 'active'
        ");
        $stmt->execute([':r' => $roundIndex, ':rid' => $roomId, ':uid' => $userId]);
        $this->touchRoom($roomId);
    }

    public function addScore(string $roomId, string $userId, int $scoreDelta, int $correctDelta): void {
        $stmt = $this->pdo->prepare("
            UPDATE room_players
            SET score = score + :score,
                correct = correct + :correct,
                last_active = :now
            WHERE room_id = :rid AND user_id = :uid
        ");
        $stmt->execute([
            ':score' => $scoreDelta,
            ':correct' => $correctDelta,
            ':now' => Database::nowUtc(),
            ':rid' => $roomId,
            ':uid' => $userId,
        ]);
        $this->touchRoom($roomId);
    }

    public function setStatus(string $roomId, string $status, ?int $finishedRound = null): void {
        $now = Database::nowUtc();
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status = 'active',
                    started_at = COALESCE(started_at, :started_at),
                    updated_at = :updated_at
                WHERE id = :rid
            ");
            $stmt->execute([
                ':started_at' => $now,
                ':updated_at' => $now,
                ':rid' => $roomId,
            ]);
        } elseif ($status === 'finished') {
            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status = 'finished',
                    finished_at = COALESCE(finished_at, :finished_at),
                    finished_round = COALESCE(:fr, current_round),
                    updated_at = :updated_at
                WHERE id = :rid
            ");
            $stmt->execute([
                ':finished_at' => $now,
                ':fr' => $finishedRound,
                ':updated_at' => $now,
                ':rid' => $roomId,
            ]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE rooms SET status = :s, updated_at = :now WHERE id = :rid");
            $stmt->execute([':s' => $status, ':now' => $now, ':rid' => $roomId]);
        }
    }

    public function touchRoom(string $roomId): void {
        try {
            $stmt = $this->pdo->prepare("UPDATE rooms SET updated_at = :now WHERE id = :rid");
            $stmt->execute([':now' => Database::nowUtc(), ':rid' => $roomId]);
        } catch (\Throwable $e) {}
    }

    public function cleanupInactiveRooms(int $inactiveMinutes = 60): int {
        try {
            $cutoff = (new \DateTimeImmutable("-{$inactiveMinutes} minutes", new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.v');

            // Find rooms with no active players for 60 minutes:
            // 1. Rooms with 0 players in room_players whose last activity (updated_at or created_at) is older than cutoff.
            // 2. Rooms where players exist, but MAX(COALESCE(last_active, joined_at)) across all players is older than cutoff.
            $findStmt = $this->pdo->prepare("
                SELECT r.id, r.guid, r.name
                FROM rooms r
                LEFT JOIN room_players rp ON rp.room_id = r.id
                GROUP BY r.id, r.guid, r.name, r.updated_at, r.created_at
                HAVING (
                    (COUNT(rp.id) = 0 AND COALESCE(r.updated_at, r.created_at) < :cutoff1)
                    OR
                    (COUNT(rp.id) > 0 AND MAX(COALESCE(rp.last_active, rp.joined_at)) < :cutoff2)
                )
            ");
            $findStmt->execute([
                ':cutoff1' => $cutoff,
                ':cutoff2' => $cutoff,
            ]);
            $staleRooms = $findStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if (empty($staleRooms)) {
                return 0;
            }

            $deletedCount = 0;
            $delStmt = $this->pdo->prepare("DELETE FROM rooms WHERE id = :rid");

            foreach ($staleRooms as $room) {
                $rid = (string)$room['id'];
                $guid = (string)$room['guid'];
                $rName = (string)($room['name'] ?? '');

                $delStmt->execute([':rid' => $rid]);
                $deletedCount++;
                Logger::room('cleanupInactiveRooms:deleted', $guid, [
                    'id' => $rid,
                    'name' => $rName,
                    'inactive_minutes' => $inactiveMinutes,
                ]);
            }

            return $deletedCount;
        } catch (\Throwable $e) {
            Logger::error('RoomRepository', 'cleanupInactiveRooms failed: ' . $e->getMessage(), [], $e);
            return 0;
        }
    }

    public function logEvent(string $roomId, int $roundIndex, ?string $userId, string $email, string $type, array $payload): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO room_events (
                id, room_id, round_index, user_id, email, event_type, payload_json, created_at
            ) VALUES (
                :id, :room_id, :round_index, :user_id, :email, :event_type, :payload_json, :created_at
            )
        ");
        $stmt->execute([
            ':id' => Database::generateUuid(),
            ':room_id' => $roomId,
            ':round_index' => $roundIndex,
            ':user_id' => $userId,
            ':email' => $email,
            ':event_type' => $type,
            ':payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':created_at' => Database::nowUtc(),
        ]);
        $this->touchRoom($roomId);
    }
}
