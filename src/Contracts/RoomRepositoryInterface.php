<?php
declare(strict_types=1);

namespace Prismatch\Contracts;

interface RoomRepositoryInterface {
    public function createRoom(string $ownerId, string $ownerEmail, int $roundsTotal = 50, ?string $name = null): array;
    public function getRoomByGuid(string $guid): ?array;
    public function getRoomById(string $id): ?array;
    public function listUserRooms(string $userId, int $limit = 50): array;
    public function addPlayer(string $roomId, string $userId, string $email): bool;
    public function listPlayers(string $roomId): array;
    public function markEliminated(string $roomId, string $userId, int $roundIndex): void;
    public function addScore(string $roomId, string $userId, int $scoreDelta, int $correctDelta): void;
    public function setStatus(string $roomId, string $status, ?int $finishedRound = null): void;
    public function logEvent(string $roomId, int $roundIndex, ?string $userId, string $email, string $type, array $payload): void;
}
