<?php
declare(strict_types=1);

namespace Prismatch\Contracts;

interface DailyRepositoryInterface {
    public function getStatus(?string $userId, ?string $anonId, ?string $dayUtc = null): array;
    public function recordScore(?string $userId, ?string $userEmail, ?string $anonId, array $data): array;
    public function getLeaderboard(?string $dayUtc = null, int $limit = 100): array;
}
