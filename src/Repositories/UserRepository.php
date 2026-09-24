<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Contracts\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function upsertLogin(string $email): void {
        $now = Database::nowUtc();
        $email = strtolower(trim($email));
        if ($email === '') return;

        $stmt = $this->pdo->prepare("
            INSERT INTO users (id, email, created_at, last_login)
            VALUES (:id, :email, :created_at, :last_login)
            ON DUPLICATE KEY UPDATE last_login = VALUES(last_login)
        ");
        $stmt->execute([
            ':id' => Database::generateUuid(),
            ':email' => $email,
            ':created_at' => $now,
            ':last_login' => $now,
        ]);
    }

    public function findByEmail(string $email): ?array {
        $email = strtolower(trim($email));
        if ($email === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, last_login, total_plays, total_wins, best_level, total_correct
            FROM users
            WHERE email = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(string $id): ?array {
        $id = trim($id);
        if ($id === '') return null;

        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, last_login, total_plays, total_wins, best_level, total_correct
            FROM users
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function ensureByEmail(string $email): array {
        $user = $this->findByEmail($email);
        if ($user) return $user;

        $this->upsertLogin($email);
        $user = $this->findByEmail($email);
        if ($user) return $user;

        return [
            'id' => Database::generateUuid(),
            'email' => $email,
            'created_at' => Database::nowUtc(),
            'last_login' => Database::nowUtc(),
            'total_plays' => 0,
            'total_wins' => 0,
            'best_level' => 0,
            'total_correct' => 0,
        ];
    }

    public function getStats(string $email): array {
        $user = $this->findByEmail($email);
        if (!$user) {
            return [
                'total_plays' => 0,
                'total_wins' => 0,
                'best_level' => 0,
                'total_correct' => 0,
            ];
        }

        return [
            'total_plays' => (int)$user['total_plays'],
            'total_wins' => (int)$user['total_wins'],
            'best_level' => (int)$user['best_level'],
            'total_correct' => (int)$user['total_correct'],
        ];
    }

    public function getDisplayName(string $userId): string {
        $user = $this->findById($userId);
        return $user ? $this->formatDisplayName($user) : 'Player';
    }

    public function formatDisplayName(array $userRow): string {
        $email = (string)($userRow['email'] ?? '');
        if ($email === '') return 'Player';
        $parts = explode('@', $email);
        return $parts[0] !== '' ? $parts[0] : 'Player';
    }
}
