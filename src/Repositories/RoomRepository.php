<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Contracts\RoomRepositoryInterface;

class RoomRepository implements RoomRepositoryInterface {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function createRoom(string $ownerId, string $ownerEmail, int $roundsTotal = 50, ?string $name = null): array {
        $roomId = Database::generateUuid();
        $guid = Database::generateUuid();
        $ownerEmail = strtolower(trim($ownerEmail));
        $now = Database::nowUtc();

        $cleanName = $name !== null ? trim($name) : null;
        if ($cleanName === '') $cleanName = null;

        $stmt = $this->pdo->prepare("
            INSERT INTO rooms (
                id, guid, name, owner_id, owner_email,
                status, rounds_total, current_round, created_at
            ) VALUES (
                :id, :guid, :name, :owner_id, :owner_email,
                'waiting', :rounds_total, 0, :created_at
            )
        ");
        $stmt->execute([
            ':id' => $roomId,
            ':guid' => $guid,
            ':name' => $cleanName,
            ':owner_id' => $ownerId,
            ':owner_email' => $ownerEmail,
            ':rounds_total' => $roundsTotal,
            ':created_at' => $now,
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
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT r.*
            FROM rooms r
            LEFT JOIN room_players rp ON rp.room_id = r.id
            WHERE r.owner_id = :uid OR rp.user_id = :uid
            ORDER BY r.created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function addPlayer(string $roomId, string $userId, string $email): bool {
        $email = strtolower(trim($email));

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM room_players WHERE room_id = :rid");
        $countStmt->execute([':rid' => $roomId]);
        $count = (int)$countStmt->fetchColumn();

        $maxStmt = $this->pdo->prepare("SELECT max_players FROM rooms WHERE id = :rid LIMIT 1");
        $maxStmt->execute([':rid' => $roomId]);
        $maxPlayers = (int)$maxStmt->fetchColumn();
        if ($maxPlayers <= 0) $maxPlayers = 25;

        // Check if already in room
        $existsStmt = $this->pdo->prepare("SELECT id FROM room_players WHERE room_id = :rid AND user_id = :uid LIMIT 1");
        $existsStmt->execute([':rid' => $roomId, ':uid' => $userId]);
        if ($existsStmt->fetch()) {
            return true;
        }

        if ($count >= $maxPlayers) {
            return false;
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO room_players (
                id, room_id, user_id, email, joined_at, status, score, correct, last_active
            ) VALUES (
                :id, :room_id, :user_id, :email, :joined_at, 'active', 0, 0, :last_active
            )
        ");
        $now = Database::nowUtc();
        return $stmt->execute([
            ':id' => Database::generateUuid(),
            ':room_id' => $roomId,
            ':user_id' => $userId,
            ':email' => $email,
            ':joined_at' => $now,
            ':last_active' => $now,
        ]);
    }

    public function listPlayers(string $roomId): array {
        $stmt = $this->pdo->prepare("
            SELECT user_id, email, status, eliminated_round, score, correct, joined_at, last_active
            FROM room_players
            WHERE room_id = :rid
            ORDER BY (status = 'active') DESC, score DESC, correct DESC, joined_at ASC
        ");
        $stmt->execute([':rid' => $roomId]);
        return $stmt->fetchAll() ?: [];
    }

    public function markEliminated(string $roomId, string $userId, int $roundIndex): void {
        $stmt = $this->pdo->prepare("
            UPDATE room_players
            SET status = 'eliminated', eliminated_round = :r
            WHERE room_id = :rid AND user_id = :uid AND status = 'active'
        ");
        $stmt->execute([':r' => $roundIndex, ':rid' => $roomId, ':uid' => $userId]);
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
    }

    public function setStatus(string $roomId, string $status, ?int $finishedRound = null): void {
        $now = Database::nowUtc();
        if ($status === 'active') {
            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status = 'active', started_at = COALESCE(started_at, :now)
                WHERE id = :rid
            ");
            $stmt->execute([':now' => $now, ':rid' => $roomId]);
        } elseif ($status === 'finished') {
            $stmt = $this->pdo->prepare("
                UPDATE rooms
                SET status = 'finished',
                    finished_at = COALESCE(finished_at, :now),
                    finished_round = COALESCE(:fr, current_round)
                WHERE id = :rid
            ");
            $stmt->execute([':now' => $now, ':fr' => $finishedRound, ':rid' => $roomId]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE rooms SET status = :s WHERE id = :rid");
            $stmt->execute([':s' => $status, ':rid' => $roomId]);
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
    }
}
