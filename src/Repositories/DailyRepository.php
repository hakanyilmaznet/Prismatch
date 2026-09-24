<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Contracts\DailyRepositoryInterface;

class DailyRepository implements DailyRepositoryInterface {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function getStatus(?string $userId, ?string $anonId, ?string $dayUtc = null): array {
        $dayUtc = $dayUtc ?: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $hasPlayed = false;
        $row = null;

        if ($userId) {
            $stmt = $this->pdo->prepare("SELECT * FROM daily_scores WHERE day_utc = :d AND (user_id = :u OR user_email = :u) LIMIT 1");
            $stmt->execute([':d' => $dayUtc, ':u' => $userId]);
            $row = $stmt->fetch();
            if ($row) $hasPlayed = true;
        }

        if (!$hasPlayed && $anonId) {
            $stmt = $this->pdo->prepare("SELECT * FROM daily_scores WHERE day_utc = :d AND anon_id = :a LIMIT 1");
            $stmt->execute([':d' => $dayUtc, ':a' => $anonId]);
            $row = $stmt->fetch();
            if ($row) $hasPlayed = true;
        }

        return [
            'has_played' => $hasPlayed,
            'day_utc' => $dayUtc,
            'score' => $row ? (int)$row['score'] : null,
            'reached_level' => $row ? (int)$row['reached_level'] : null,
            'correct_count' => $row ? (int)$row['correct_count'] : null,
            'won' => $row ? !empty($row['won']) : false,
        ];
    }

    public function recordScore(?string $userId, ?string $userEmail, ?string $anonId, array $data): array {
        $dayUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $status = $this->getStatus($userId ?: $userEmail, $anonId, $dayUtc);
        if ($status['has_played']) {
            return ['ok' => false, 'error' => 'already_played'];
        }

        $id = Database::generateUuid();
        $reachedLevel = (int)($data['reached_level'] ?? $data['reachedLevel'] ?? 0);
        $correctCount = (int)($data['total_correct'] ?? $data['correct_count'] ?? $data['correct'] ?? 0);
        $score = (int)($data['score'] ?? 0);
        $durationMs = (int)($data['duration_ms'] ?? $data['durationMs'] ?? 0);
        $won = !empty($data['won']) ? 1 : 0;
        $language = isset($data['language']) ? substr((string)$data['language'], 0, 16) : null;
        $country = isset($data['country']) ? strtoupper(substr((string)$data['country'], 0, 8)) : null;
        $rounds = is_array($data['rounds'] ?? null) ? $data['rounds'] : [];

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO daily_scores (
                    id, day_utc, user_id, user_email, anon_id,
                    reached_level, correct_count, score, duration_ms, won, language, country, created_at
                ) VALUES (
                    :id, :day_utc, :user_id, :user_email, :anon_id,
                    :reached_level, :correct_count, :score, :duration_ms, :won, :language, :country, :created_at
                )
                ON DUPLICATE KEY UPDATE
                    score = VALUES(score),
                    reached_level = VALUES(reached_level),
                    correct_count = VALUES(correct_count),
                    duration_ms = VALUES(duration_ms),
                    won = VALUES(won)
            ");
            $now = Database::nowUtc();
            $stmt->execute([
                ':id' => $id,
                ':day_utc' => $dayUtc,
                ':user_id' => $userId,
                ':user_email' => $userEmail,
                ':anon_id' => $anonId,
                ':reached_level' => $reachedLevel,
                ':correct_count' => $correctCount,
                ':score' => $score,
                ':duration_ms' => $durationMs,
                ':won' => $won,
                ':language' => $language,
                ':country' => $country,
                ':created_at' => $now,
            ]);

            if (!empty($rounds)) {
                $rStmt = $this->pdo->prepare("
                    INSERT INTO daily_rounds (
                        id, daily_score_id, stage, target_color, picked_color,
                        response_ms, is_correct, grid_json, created_at
                    ) VALUES (
                        :id, :sid, :stage, :target, :picked, :response_ms, :is_correct, :grid, :created_at
                    )
                ");
                foreach ($rounds as $r) {
                    $gridJson = is_array($r['grid_colors'] ?? null) ? json_encode($r['grid_colors']) : null;
                    $rStmt->execute([
                        ':id' => Database::generateUuid(),
                        ':sid' => $id,
                        ':stage' => (int)($r['level'] ?? $r['stage'] ?? 1),
                        ':target' => (string)($r['target_color'] ?? ''),
                        ':picked' => (string)($r['picked_color'] ?? ''),
                        ':response_ms' => (int)($r['response_ms'] ?? 0),
                        ':is_correct' => !empty($r['is_correct']) ? 1 : 0,
                        ':grid' => $gridJson,
                        ':created_at' => $now,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true, 'id' => $id, 'score' => $score];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Failed to record daily score: " . $e->getMessage());
            return ['ok' => false, 'error' => 'db_error', 'msg' => $e->getMessage()];
        }
    }

    public function getLeaderboard(?string $dayUtc = null, int $limit = 100, ?string $country = null): array {
        $dayUtc = $dayUtc ?: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $limit = max(1, min(200, $limit));

        if ($country) {
            $stmt = $this->pdo->prepare("
                SELECT id, user_email as email, score, reached_level, correct_count as total_correct, duration_ms, language, country, created_at
                FROM daily_scores
                WHERE day_utc = :d AND country = :c
                ORDER BY score DESC, reached_level DESC, correct_count DESC, duration_ms ASC, created_at ASC
                LIMIT {$limit}
            ");
            $stmt->execute([':d' => $dayUtc, ':c' => $country]);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT id, user_email as email, score, reached_level, correct_count as total_correct, duration_ms, language, country, created_at
                FROM daily_scores
                WHERE day_utc = :d
                ORDER BY score DESC, reached_level DESC, correct_count DESC, duration_ms ASC, created_at ASC
                LIMIT {$limit}
            ");
            $stmt->execute([':d' => $dayUtc]);
        }

        return $stmt->fetchAll() ?: [];
    }
}
