<?php
declare(strict_types=1);

namespace Prismatch\Contracts;

interface RoomRepositoryInterface {
    public function createRoom(string $ownerId, string $ownerEmail, int $roundsTotal = 25, ?string $name = null, bool $isPrivate = false, string $gameMode = 'elimination', ?string $settingsJson = null): array;
    public function getRoomByGuid(string $guid): ?array;
    public function getRoomById(string $id): ?array;
    public function listUserRooms(string $userId, int $limit = 50): array;
    public function listPublicRooms(int $limit = 20): array;
    public function addPlayer(string $roomId, string $userId, string $email): bool;
    public function listPlayers(string $roomId): array;
    public function setPlayerTeam(string $roomId, string $userId, string $team): bool;
    public function setPlayerAvatar(string $roomId, string $userId, string $avatar): bool;
    public function setPlayerShield(string $roomId, string $userId, bool $hasShield): bool;
    public function savePrediction(string $roomId, int $round, string $spectatorId, string $spectatorEmail, string $predictedUserId): bool;
    public function settlePredictions(string $roomId, int $round, string $winnerUserId): array;
    public function awardSpectatorPoints(string $roomId, string $spectatorId, int $points): bool;
    public function touchPlayer(string $roomId, string $userId, bool $reactivate = false): void;
    public function removePlayer(string $roomId, string $userId): bool;
    public function cleanupStalePlayers(string $roomId, string $ownerId, int $staleSeconds = 8): int;
    public function markEliminated(string $roomId, string $userId, int $roundIndex): void;
    public function addScore(string $roomId, string $userId, int $scoreDelta, int $correctDelta): void;
    public function setStatus(string $roomId, string $status, ?int $finishedRound = null): void;
    public function logEvent(string $roomId, int $roundIndex, ?string $userId, string $email, string $type, array $payload): void;
    public function touchRoom(string $roomId): void;
    public function cleanupInactiveRooms(int $inactiveMinutes = 60): int;
}
