<?php
declare(strict_types=1);

namespace Tests\Repositories;

use Prismatch\Repositories\DailyRepository;
use Tests\Support\BaseTestCase;

class DailyRepositoryTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        // Reset cachedCols static property between tests using reflection
        $ref = new \ReflectionClass(DailyRepository::class);
        $prop = $ref->getProperty('cachedCols');
        $prop->setValue(null, null);
    }

    private array $defaultColumns = [
        ['Field' => 'id'],
        ['Field' => 'day_utc'],
        ['Field' => 'user_id'],
        ['Field' => 'user_email'],
        ['Field' => 'email'],
        ['Field' => 'anon_id'],
        ['Field' => 'game_mode'],
        ['Field' => 'reached_level'],
        ['Field' => 'correct_count'],
        ['Field' => 'total_correct'],
        ['Field' => 'score'],
        ['Field' => 'duration_ms'],
        ['Field' => 'won'],
        ['Field' => 'language'],
        ['Field' => 'country'],
        ['Field' => 'created_at'],
    ];

    public function testGetStatusUserHasPlayed(): void {
        $sampleRow = [
            'id' => 'ds-1',
            'day_utc' => '2026-10-01',
            'user_id' => 'u-1',
            'game_mode' => 'elimination',
            'score' => 1500,
            'reached_level' => 12,
            'correct_count' => 11,
            'won' => 1,
        ];

        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'SELECT * FROM daily_scores' => [$sampleRow],
        ]);

        $repo = new DailyRepository($pdo);
        $status = $repo->getStatus('u-1', null, '2026-10-01', 'elimination');

        $this->assertTrue($status['has_played']);
        $this->assertSame('2026-10-01', $status['day_utc']);
        $this->assertSame('elimination', $status['game_mode']);
        $this->assertSame(1500, $status['score']);
        $this->assertSame(12, $status['reached_level']);
        $this->assertSame(11, $status['correct_count']);
        $this->assertTrue($status['won']);
    }

    public function testGetStatusUserHasNotPlayed(): void {
        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'SELECT * FROM daily_scores' => [],
        ]);

        $repo = new DailyRepository($pdo);
        $status = $repo->getStatus('u-new', null, '2026-10-01', 'points');

        $this->assertFalse($status['has_played']);
        $this->assertSame('2026-10-01', $status['day_utc']);
        $this->assertSame('points', $status['game_mode']);
        $this->assertNull($status['score']);
        $this->assertNull($status['reached_level']);
        $this->assertFalse($status['won']);
    }

    public function testGetStatusAnonymousPlayer(): void {
        $sampleRow = [
            'id' => 'ds-anon',
            'day_utc' => '2026-10-01',
            'anon_id' => 'anon-token-123',
            'game_mode' => 'flags',
            'score' => 800,
            'reached_level' => 6,
            'correct_count' => 6,
            'won' => 0,
        ];

        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'SELECT * FROM daily_scores' => [$sampleRow],
        ]);

        $repo = new DailyRepository($pdo);
        $status = $repo->getStatus(null, 'anon-token-123', '2026-10-01', 'flags');

        $this->assertTrue($status['has_played']);
        $this->assertSame('flags', $status['game_mode']);
        $this->assertSame(800, $status['score']);
    }

    public function testRecordScoreWhenAlreadyPlayedReturnsError(): void {
        $sampleRow = [
            'id' => 'ds-1',
            'day_utc' => '2026-10-01',
            'user_id' => 'u-1',
            'score' => 100,
        ];

        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'SELECT * FROM daily_scores' => [$sampleRow],
        ]);

        $repo = new DailyRepository($pdo);
        $result = $repo->recordScore('u-1', 'u1@test.com', null, [
            'score' => 200,
            'game_mode' => 'elimination',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertSame('already_played', $result['error']);
    }

    public function testRecordScoreSuccess(): void {
        $callCount = 0;
        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => function ($sql) {
                if (stripos($sql, 'daily_rounds') !== false) {
                    return $this->createMockStatement([
                        ['Field' => 'id'],
                        ['Field' => 'daily_score_id'],
                        ['Field' => 'stage'],
                    ]);
                }
                return $this->createMockStatement($this->defaultColumns);
            },
            'SHOW TABLES LIKE' => function () {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('fetchColumn')->willReturn('daily_rounds');
                $stmt->method('execute')->willReturn(true);
                return $stmt;
            },
            'SELECT * FROM daily_scores' => [],
            'INSERT INTO daily_scores' => [],
            'INSERT INTO daily_rounds' => [],
        ]);

        $repo = new DailyRepository($pdo);
        $result = $repo->recordScore('u-2', 'u2@test.com', null, [
            'score' => 2500,
            'reached_level' => 10,
            'total_correct' => 10,
            'duration_ms' => 45000,
            'game_mode' => 'points',
            'won' => true,
            'country' => 'tr',
            'language' => 'tr',
            'rounds' => [
                [
                    'level' => 1,
                    'target_color' => '#FF0000',
                    'picked_color' => '#FF0000',
                    'grid_colors' => ['#FF0000', '#00FF00'],
                    'response_ms' => 1000,
                    'is_correct' => 1,
                ]
            ]
        ]);

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame(2500, $result['score']);
        $this->assertSame('points', $result['game_mode']);
    }

    public function testRecordScoreRollbackOnDbException(): void {
        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'SELECT * FROM daily_scores' => [],
            'INSERT INTO daily_scores' => function () {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willThrowException(new \RuntimeException('Insert failed'));
                return $stmt;
            },
        ]);
        $pdo->expects($this->once())->method('rollBack');

        $repo = new DailyRepository($pdo);
        $result = $repo->recordScore('u-err', 'err@test.com', null, ['score' => 500]);

        $this->assertFalse($result['ok']);
        $this->assertSame('db_error', $result['error']);
    }

    public function testGetLeaderboardWithDefaultAndFilteredParameters(): void {
        $sampleLeaderboard = [
            [
                'id' => 'ds-1',
                'email' => 'alpha@test.com',
                'score' => 3000,
                'reached_level' => 20,
                'total_correct' => 20,
                'duration_ms' => 60000,
                'language' => 'en',
                'country' => 'US',
                'created_at' => '2026-10-01 12:00:00',
                'game_mode' => 'elimination',
            ],
            [
                'id' => 'ds-2',
                'email' => 'beta@test.com',
                'score' => 2500,
                'reached_level' => 18,
                'total_correct' => 18,
                'duration_ms' => 70000,
                'language' => 'tr',
                'country' => 'TR',
                'created_at' => '2026-10-01 12:30:00',
                'game_mode' => 'elimination',
            ]
        ];

        $pdo = $this->createMockPdo([
            'SHOW COLUMNS' => $this->defaultColumns,
            'FROM daily_scores' => $sampleLeaderboard,
        ]);

        $repo = new DailyRepository($pdo);

        // Test without country filter
        $list = $repo->getLeaderboard('2026-10-01', 50, null, 'elimination');
        $this->assertCount(2, $list);
        $this->assertSame('alpha@test.com', $list[0]['email']);

        // Test with country filter
        $trList = $repo->getLeaderboard('2026-10-01', 10, 'TR', 'points');
        $this->assertIsArray($trList);
    }
}
