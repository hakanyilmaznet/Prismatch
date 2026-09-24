<?php
declare(strict_types=1);

namespace Prismatch\Contracts;

interface UserRepositoryInterface {
    public function upsertLogin(string $email): void;
    public function findByEmail(string $email): ?array;
    public function findById(string $id): ?array;
    public function ensureByEmail(string $email): array;
    public function getStats(string $email): array;
    public function getDisplayName(string $userId): string;
    public function formatDisplayName(array $userRow): string;
}
