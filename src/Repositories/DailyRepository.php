<?php
declare(strict_types=1);

namespace Prismatch\Repositories;

use PDO;
use Prismatch\Core\Database;
use Prismatch\Contracts\DailyRepositoryInterface;

class DailyRepository implements DailyRepositoryInterface {
    private PDO $pdo;
    private static ?array $cachedCols = null;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    private function getTableColumns(): array {
        if (self::$cachedCols !== null) {
            return self::$cachedCols;
        }
        $cols = [];
        try {
            $stmt = $this->pdo->query("SHOW COLUMNS FROM daily_scores");
            if ($stmt) {
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $cols[strtolower((string)$row['Field'])] = true;
                }
            }
        } catch (\Throwable $e) {
            // fallback defaults
        }
        self::$cachedCols = $cols;
        return $cols;
    }

    private function hasTable(string $table): bool {
        try {
            $stmt = $this->pdo->prepare("SHOW TABLES LIKE :tbl");
            $stmt->execute([':tbl' => $table]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getStatus(?string $userId, ?string $anonId, ?string $dayUtc = null, string $gameMode = 'elimination'): array {
        $dayUtc = $dayUtc ?: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $cleanMode = in_array($gameMode, ['elimination', 'points', 'flags'], true) ? $gameMode : 'elimination';
        $hasPlayed = false;
        $row = null;
        $cols = $this->getTableColumns();
        $dateCol = isset($cols['day_utc']) ? 'day_utc' : 'challenge_date';
        $hasModeCol = isset($cols['game_mode']);
        $modeSql = $hasModeCol ? "AND (game_mode = :gm OR (game_mode IS NULL AND :gm = 'elimination'))" : "";

        if ($userId) {
            $userWhere = [];
            $params = [':d' => $dayUtc];
            if ($hasModeCol) $params[':gm'] = $cleanMode;
            if (isset($cols['user_id'])) {
                $userWhere[] = 'user_id = :u';
                $params[':u'] = $userId;
            }
            if (isset($cols['user_email'])) {
                $userWhere[] = 'user_email = :ue';
                $params[':ue'] = $userId;
            }
            if (isset($cols['email'])) {
                $userWhere[] = 'email = :e';
                $params[':e'] = $userId;
            }
            if (!empty($userWhere)) {
                $sqlWhere = implode(' OR ', $userWhere);
                $stmt = $this->pdo->prepare("SELECT * FROM daily_scores WHERE {$dateCol} = :d AND ({$sqlWhere}) {$modeSql} LIMIT 1");
                $stmt->execute($params);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $hasPlayed = true;
            }
        }

        if (!$hasPlayed && $anonId && isset($cols['anon_id'])) {
            $params = [':d' => $dayUtc, ':a' => $anonId];
            if ($hasModeCol) $params[':gm'] = $cleanMode;
            $stmt = $this->pdo->prepare("SELECT * FROM daily_scores WHERE {$dateCol} = :d AND anon_id = :a {$modeSql} LIMIT 1");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $hasPlayed = true;
        }

        return [
            'has_played' => $hasPlayed,
            'day_utc' => $dayUtc,
            'game_mode' => $row ? ($row['game_mode'] ?? $cleanMode) : $cleanMode,
            'score' => $row ? (int)($row['score'] ?? 0) : null,
            'reached_level' => $row ? (int)($row['reached_level'] ?? 0) : null,
            'correct_count' => $row ? (int)($row['correct_count'] ?? $row['total_correct'] ?? 0) : null,
            'won' => $row ? !empty($row['won']) : false,
        ];
    }

    public function recordScore(?string $userId, ?string $userEmail, ?string $anonId, array $data): array {
        $dayUtc = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $gameMode = (string)($data['game_mode'] ?? $data['gameMode'] ?? 'elimination');
        $cleanMode = in_array($gameMode, ['elimination', 'points', 'flags'], true) ? $gameMode : 'elimination';

        $status = $this->getStatus($userId ?: $userEmail, $anonId, $dayUtc, $cleanMode);
        if ($status['has_played']) {
            return ['ok' => false, 'error' => 'already_played'];
        }

        $cols = $this->getTableColumns();
        $dateCol = isset($cols['day_utc']) ? 'day_utc' : 'challenge_date';
        $reachedLevel = (int)($data['reached_level'] ?? $data['reachedLevel'] ?? 0);
        $correctCount = (int)($data['total_correct'] ?? $data['correct_count'] ?? $data['correct'] ?? 0);
        $score = (int)($data['score'] ?? 0);
        $durationMs = (int)($data['duration_ms'] ?? $data['durationMs'] ?? 0);
        $language = isset($data['language']) ? substr((string)$data['language'], 0, 16) : null;
        $country = isset($data['country']) ? strtoupper(substr((string)$data['country'], 0, 8)) : null;
        $rounds = is_array($data['rounds'] ?? null) ? $data['rounds'] : [];

        $insertData = [];
        if (isset($cols['id'])) {
            $insertData['id'] = Database::generateUuid();
        }
        $insertData[$dateCol] = $dayUtc;
        if (isset($cols['user_id'])) $insertData['user_id'] = $userId;
        if (isset($cols['user_email'])) $insertData['user_email'] = $userEmail;
        if (isset($cols['email'])) $insertData['email'] = $userEmail ?: $userId;
        if (isset($cols['anon_id'])) $insertData['anon_id'] = $anonId;
        if (isset($cols['game_mode'])) $insertData['game_mode'] = $cleanMode;
        if (isset($cols['reached_level'])) $insertData['reached_level'] = $reachedLevel;
        if (isset($cols['correct_count'])) $insertData['correct_count'] = $correctCount;
        if (isset($cols['total_correct'])) $insertData['total_correct'] = $correctCount;
        if (isset($cols['score'])) $insertData['score'] = $score;
        if (isset($cols['duration_ms'])) $insertData['duration_ms'] = $durationMs;
        if (isset($cols['won'])) $insertData['won'] = !empty($data['won']) ? 1 : 0;
        if (isset($cols['language'])) $insertData['language'] = $language;
        if (isset($cols['country'])) $insertData['country'] = $country;
        if (isset($cols['created_at'])) $insertData['created_at'] = Database::nowUtc();

        $this->pdo->beginTransaction();
        try {
            $fields = array_keys($insertData);
            $fieldSql = implode(', ', $fields);
            $paramPlaceholders = [];
            $params = [];
            foreach ($insertData as $k => $v) {
                $placeholder = ':' . $k;
                $paramPlaceholders[] = $placeholder;
                $params[$placeholder] = $v;
            }
            $paramSql = implode(', ', $paramPlaceholders);

            $updateParts = [];
            if (isset($cols['score'])) $updateParts[] = 'score = VALUES(score)';
            if (isset($cols['reached_level'])) $updateParts[] = 'reached_level = VALUES(reached_level)';
            if (isset($cols['correct_count'])) $updateParts[] = 'correct_count = VALUES(correct_count)';
            if (isset($cols['total_correct'])) $updateParts[] = 'total_correct = VALUES(total_correct)';
            if (isset($cols['duration_ms'])) $updateParts[] = 'duration_ms = VALUES(duration_ms)';
            if (isset($cols['won'])) $updateParts[] = 'won = VALUES(won)';
            if (isset($cols['game_mode'])) $updateParts[] = 'game_mode = VALUES(game_mode)';
            $updateSql = !empty($updateParts) ? ('ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts)) : '';

            $stmt = $this->pdo->prepare("
                INSERT INTO daily_scores ({$fieldSql})
                VALUES ({$paramSql})
                {$updateSql}
            ");
            $stmt->execute($params);
            $scoreId = $insertData['id'] ?? (string)$this->pdo->lastInsertId();

            if (!empty($rounds) && $this->hasTable('daily_rounds')) {
                $rCols = [];
                try {
                    $rStmtCols = $this->pdo->query("SHOW COLUMNS FROM daily_rounds");
                    if ($rStmtCols) {
                        while ($rc = $rStmtCols->fetch(PDO::FETCH_ASSOC)) {
                            $rCols[strtolower((string)$rc['Field'])] = true;
                        }
                    }
                } catch (\Throwable $e) {}

                $sidCol = isset($rCols['daily_score_id']) ? 'daily_score_id' : 'score_id';
                $stageCol = isset($rCols['stage']) ? 'stage' : 'level';
                $now = Database::nowUtc();

                $rStmt = $this->pdo->prepare("
                    INSERT INTO daily_rounds (
                        id, {$sidCol}, {$stageCol}, target_color, picked_color,
                        response_ms, is_correct, grid_json, created_at
                    ) VALUES (
                        :id, :sid, :stage, :target, :picked, :response_ms, :is_correct, :grid, :created_at
                    )
                ");
                foreach ($rounds as $r) {
                    $gridColors = $r['grid_colors'] ?? $r['gridColors'] ?? null;
                    $gridJson = is_array($gridColors) ? json_encode($gridColors) : null;
                    $rStmt->execute([
                        ':id' => Database::generateUuid(),
                        ':sid' => $scoreId,
                        ':stage' => (int)($r['level'] ?? $r['stage'] ?? 1),
                        ':target' => (string)($r['target_color'] ?? $r['targetColor'] ?? ''),
                        ':picked' => (string)($r['picked_color'] ?? $r['pickedColor'] ?? ''),
                        ':response_ms' => (int)($r['response_ms'] ?? $r['responseMs'] ?? 0),
                        ':is_correct' => !empty($r['is_correct']) || !empty($r['isCorrect']) ? 1 : 0,
                        ':grid' => $gridJson,
                        ':created_at' => $now,
                    ]);
                }
            }

            $this->pdo->commit();
            return ['ok' => true, 'id' => $scoreId, 'score' => $score, 'game_mode' => $cleanMode];
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            error_log("Failed to record daily score: " . $e->getMessage());
            return ['ok' => false, 'error' => 'db_error', 'msg' => $e->getMessage()];
        }
    }

    public function getLeaderboard(?string $dayUtc = null, int $limit = 100, ?string $country = null, ?string $gameMode = null): array {
        $dayUtc = $dayUtc ?: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d');
        $limit = max(1, min(200, $limit));
        $cols = $this->getTableColumns();

        $dateCol = isset($cols['day_utc']) ? 'day_utc' : 'challenge_date';
        $emailExpr = isset($cols['email']) ? 'email' : (isset($cols['user_email']) ? 'user_email as email' : "'' as email");
        $correctExpr = isset($cols['total_correct']) ? 'total_correct' : (isset($cols['correct_count']) ? 'correct_count as total_correct' : '0 as total_correct');
        $idExpr = isset($cols['id']) ? 'id' : "'' as id";
        $langExpr = isset($cols['language']) ? 'language' : "'' as language";
        $countryExpr = isset($cols['country']) ? 'country' : "'' as country";
        $createdExpr = isset($cols['created_at']) ? 'created_at' : "NOW() as created_at";
        $reachedExpr = isset($cols['reached_level']) ? 'reached_level' : '0 as reached_level';
        $scoreExpr = isset($cols['score']) ? 'score' : '0 as score';
        $durationExpr = isset($cols['duration_ms']) ? 'duration_ms' : '0 as duration_ms';
        $modeExpr = isset($cols['game_mode']) ? 'game_mode' : "'elimination' as game_mode";

        $selectFields = "{$idExpr}, {$emailExpr}, {$scoreExpr}, {$reachedExpr}, {$correctExpr}, {$durationExpr}, {$langExpr}, {$countryExpr}, {$createdExpr}, {$modeExpr}";
        $orderCorrect = isset($cols['total_correct']) ? 'total_correct DESC,' : (isset($cols['correct_count']) ? 'correct_count DESC,' : '');

        $rawEmailCol = isset($cols['email']) ? 'email' : (isset($cols['user_email']) ? 'user_email' : "''");
        $tempFilter = "AND NOT (({$rawEmailCol} LIKE '%@prismatch' OR {$rawEmailCol} LIKE '%@local.player') AND created_at < DATE_SUB(NOW(), INTERVAL 1 DAY))";

        $modeFilter = "";
        $params = [':d' => $dayUtc];

        if ($gameMode && $gameMode !== 'all' && isset($cols['game_mode'])) {
            $cleanMode = in_array($gameMode, ['elimination', 'points', 'flags'], true) ? $gameMode : 'elimination';
            $modeFilter = "AND (game_mode = :gm OR (game_mode IS NULL AND :gm = 'elimination'))";
            $params[':gm'] = $cleanMode;
        }

        if ($country && isset($cols['country'])) {
            $params[':c'] = $country;
            $stmt = $this->pdo->prepare("
                SELECT {$selectFields}
                FROM daily_scores
                WHERE {$dateCol} = :d AND country = :c {$modeFilter} {$tempFilter}
                ORDER BY score DESC, reached_level DESC, {$orderCorrect} duration_ms ASC, created_at ASC
                LIMIT {$limit}
            ");
            $stmt->execute($params);
        } else {
            $stmt = $this->pdo->prepare("
                SELECT {$selectFields}
                FROM daily_scores
                WHERE {$dateCol} = :d {$modeFilter} {$tempFilter}
                ORDER BY score DESC, reached_level DESC, {$orderCorrect} duration_ms ASC, created_at ASC
                LIMIT {$limit}
            ");
            $stmt->execute($params);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
