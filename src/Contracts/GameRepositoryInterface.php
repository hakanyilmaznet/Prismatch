<?php
declare(strict_types=1);

namespace Prismatch\Contracts;

interface GameRepositoryInterface {
    public function recordFullGame(string $userId, string $email, array $payload): ?string;
    public function listGamesByUser(string $userId, int $limit = 50): array;
    public function getGameDetails(string $gameId, string $userId): ?array;
}
