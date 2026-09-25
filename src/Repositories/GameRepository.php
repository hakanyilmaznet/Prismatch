<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Contracts\GameRepositoryInterface;

class GameRepository implements GameRepositoryInterface {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function recordFullGame(string $userId, string $email, array $payload): ?string {
        $userRepo = new UserRepository($this->pdo);
        $user = $userRepo->ensureByEmail($email);
        $userId = $user['id'];

        $gameId = Database::generateUuid();
        $createdAt = isset($payload['created_at'])
            ? Database::isoToUtc((string)$payload['created_at'])
            : (isset($payload['startedAt']) ? Database::isoToUtc((string)$payload['startedAt']) : Database::nowUtc());
        $finishedAt = isset($payload['finished_at'])
            ? Database::isoToUtc((string)$payload['finished_at'])
            : (isset($payload['endedAt']) ? Database::isoToUtc((string)$payload['endedAt']) : Database::nowUtc());
        $durationMs = (int)($payload['duration_ms'] ?? $payload['durationMs'] ?? 0);
        $reachedLevel = (int)($payload['reached_level'] ?? $payload['reachedLevel'] ?? 0);
        $totalCorrect = (int)($payload['total_correct'] ?? $payload['correct'] ?? 0);
        $score = (int)($payload['score'] ?? 0);
        $won = !empty($payload['won']) ? 1 : 0;
        $language = isset($payload['language']) ? substr((string)$payload['language'], 0, 16) : null;
        $country = isset($payload['country']) ? strtoupper(substr((string)$payload['country'], 0, 8)) : null;

        // Auto-infer from rounds if not explicitly provided at top-level
        if ($reachedLevel === 0 && !empty($payload['rounds']) && is_array($payload['rounds'])) {
            foreach ($payload['rounds'] as $r) {
                $reachedLevel = max($reachedLevel, (int)($r['level'] ?? 0));
            }
        }
        if ($totalCorrect === 0 && !empty($payload['rounds']) && is_array($payload['rounds'])) {
            foreach ($payload['rounds'] as $r) {
                if (!empty($r['is_correct']) || !empty($r['isCorrect'])) {
                    $totalCorrect++;
                }
            }
        }
        if ($durationMs === 0 && !empty($payload['rounds']) && is_array($payload['rounds'])) {
            $sumRoundMs = 0;
            foreach ($payload['rounds'] as $r) {
                $sumRoundMs += (int)($r['response_ms'] ?? $r['responseMs'] ?? 0);
            }
            if ($sumRoundMs > 0) {
                $durationMs = $sumRoundMs;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO games (
                    id, user_id, email, created_at, finished_at,
                    duration_ms, reached_level, total_correct, score, won, language, country
                ) VALUES (
                    :id, :user_id, :email, :created_at, :finished_at,
                    :duration_ms, :reached_level, :total_correct, :score, :won, :language, :country
                )
            ");
            $stmt->execute([
                ':id' => $gameId,
                ':user_id' => $userId,
                ':email' => $email,
                ':created_at' => $createdAt,
                ':finished_at' => $finishedAt,
                ':duration_ms' => $durationMs,
                ':reached_level' => $reachedLevel,
                ':total_correct' => $totalCorrect,
                ':score' => $score,
                ':won' => $won,
                ':language' => $language,
                ':country' => $country,
            ]);

            // Save rounds
            if (!empty($payload['rounds']) && is_array($payload['rounds'])) {
                $roundStmt = $this->pdo->prepare("
                    INSERT INTO rounds (
                        id, game_id, level, target_color, grid_colors_json,
                        picked_color, response_ms, is_correct, created_at
                    ) VALUES (
                        :id, :game_id, :level, :target_color, :grid_colors_json,
                        :picked_color, :response_ms, :is_correct, :created_at
                    )
                ");

                foreach ($payload['rounds'] as $r) {
                    $gridColors = $r['grid_colors'] ?? $r['gridColors'] ?? null;
                    if (is_array($gridColors)) {
                        $gridJson = json_encode(array_values($gridColors), JSON_UNESCAPED_SLASHES);
                    } elseif (!empty($r['grid_colors_json'])) {
                        $gridJson = (string)$r['grid_colors_json'];
                    } else {
                        $gridJson = '[]';
                    }

                    $targetColor = (string)($r['target_color'] ?? $r['targetColor'] ?? '');
                    $pickedColor = isset($r['picked_color'])
                        ? (string)$r['picked_color']
                        : (isset($r['pickedColor']) && $r['pickedColor'] !== null ? (string)$r['pickedColor'] : null);
                    $responseMs = (int)($r['response_ms'] ?? $r['responseMs'] ?? 0);
                    $isCorrect = (!empty($r['is_correct']) || !empty($r['isCorrect'])) ? 1 : 0;
                    $rCreatedAt = isset($r['created_at'])
                        ? Database::isoToUtc((string)$r['created_at'])
                        : (isset($r['createdAt']) ? Database::isoToUtc((string)$r['createdAt']) : Database::nowUtc());

                    $roundStmt->execute([
                        ':id' => Database::generateUuid(),
                        ':game_id' => $gameId,
                        ':level' => (int)($r['level'] ?? 0),
                        ':target_color' => $targetColor,
                        ':grid_colors_json' => $gridJson,
                        ':picked_color' => $pickedColor,
                        ':response_ms' => $responseMs,
                        ':is_correct' => $isCorrect,
                        ':created_at' => $rCreatedAt,
                    ]);
                }
            }

            // Update user aggregates
            $updateUser = $this->pdo->prepare("
                UPDATE users
                SET total_plays = total_plays + 1,
                    total_wins = total_wins + :won,
                    best_level = GREATEST(best_level, :reached_level),
                    total_correct = total_correct + :total_correct,
                    last_login = :now
                WHERE id = :user_id
            ");
            $updateUser->execute([
                ':won' => $won,
                ':reached_level' => $reachedLevel,
                ':total_correct' => $totalCorrect,
                ':now' => Database::nowUtc(),
                ':user_id' => $userId,
            ]);

            $this->pdo->commit();
            return $gameId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Failed to record game: " . $e->getMessage());
            return null;
        }
    }

    public function listGamesByUser(string $userId, int $limit = 50): array {
        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
            FROM games
            WHERE user_id = :uid
              AND NOT (
                (email LIKE '%@prismatch' OR email LIKE '%@local.player')
                AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
              )
            ORDER BY created_at DESC
            LIMIT :lim
        ");
        $stmt->bindValue(':uid', $userId);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function getGameDetails(string $gameId, string $userId): ?array {
        $stmt = $this->pdo->prepare("
            SELECT id, email, created_at, finished_at, duration_ms, reached_level, total_correct, score, won, language, country
            FROM games
            WHERE id = :gid AND user_id = :uid
            LIMIT 1
        ");
        $stmt->execute([':gid' => $gameId, ':uid' => $userId]);
        $game = $stmt->fetch();
        if (!$game) return null;

        $rStmt = $this->pdo->prepare("
            SELECT level, target_color, grid_colors_json, picked_color, response_ms, is_correct, created_at
            FROM rounds
            WHERE game_id = :gid
            ORDER BY level ASC
        ");
        $rStmt->execute([':gid' => $gameId]);
        $game['rounds'] = $rStmt->fetchAll() ?: [];

        return $game;
    }
}
