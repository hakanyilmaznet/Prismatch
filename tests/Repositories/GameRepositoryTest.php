<?php
declare(strict_types=1);

namespace Tests\Repositories;

use Prismatch\Repositories\GameRepository;
use Tests\Support\BaseTestCase;

class GameRepositoryTest extends BaseTestCase {
    private array $sampleUser = [
        'id' => 'u-user-uuid',
        'email' => 'gamer@example.com',
        'created_at' => '2026-10-01 10:00:00.000',
        'last_login' => '2026-10-01 10:00:00.000',
        'total_plays' => 5,
        'total_wins' => 1,
        'best_level' => 10,
        'total_correct' => 30,
    ];

    public function testRecordFullGameSuccessWithExplicitFields(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
            'INSERT INTO games' => [],
            'INSERT INTO rounds' => [],
            'UPDATE users' => [],
        ]);

        $repo = new GameRepository($pdo);
        $payload = [
            'created_at' => '2026-10-01T12:00:00Z',
            'finished_at' => '2026-10-01T12:02:30Z',
            'duration_ms' => 150000,
            'reached_level' => 15,
            'total_correct' => 14,
            'score' => 2850,
            'won' => true,
            'language' => 'tr',
            'country' => 'tr',
            'game_mode' => 'points',
            'rounds' => [
                [
                    'level' => 1,
                    'target_color' => '#FF0000',
                    'grid_colors' => ['#FF0000', '#00FF00', '#0000FF'],
                    'picked_color' => '#FF0000',
                    'response_ms' => 1200,
                    'is_correct' => 1,
                    'created_at' => '2026-10-01T12:00:05Z',
                ],
                [
                    'level' => 2,
                    'target_color' => '#FFFF00',
                    'grid_colors_json' => json_encode(['#FFFF00', '#000000']),
                    'picked_color' => '#FFFF00',
                    'response_ms' => 1500,
                    'is_correct' => true,
                ]
            ],
        ];

        $gameId = $repo->recordFullGame('u-user-uuid', 'gamer@example.com', $payload);

        $this->assertNotNull($gameId);
        $this->assertNotEmpty($gameId);
    }

    public function testRecordFullGameInfersFieldsFromRounds(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
            'INSERT INTO games' => [],
            'INSERT INTO rounds' => [],
            'UPDATE users' => [],
        ]);

        $repo = new GameRepository($pdo);
        $payload = [
            'startedAt' => '2026-10-01T12:00:00Z',
            'endedAt' => '2026-10-01T12:01:00Z',
            'reached_level' => 0,
            'total_correct' => 0,
            'duration_ms' => 0,
            'gameMode' => 'flags',
            'rounds' => [
                [
                    'level' => 3,
                    'targetColor' => '#123456',
                    'gridColors' => ['#123456', '#654321'],
                    'pickedColor' => '#123456',
                    'responseMs' => 2500,
                    'isCorrect' => true,
                ],
                [
                    'level' => 7,
                    'targetColor' => '#ABCDEF',
                    'gridColors' => ['#ABCDEF', '#FEDCBA'],
                    'pickedColor' => null,
                    'responseMs' => 3000,
                    'isCorrect' => false,
                ]
            ],
        ];

        $gameId = $repo->recordFullGame('u-user-uuid', 'gamer@example.com', $payload);

        $this->assertNotNull($gameId);
    }

    public function testRecordFullGameRollsBackOnException(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
            'INSERT INTO games' => function () {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willThrowException(new \RuntimeException('DB write lock failure'));
                return $stmt;
            },
        ]);
        $pdo->expects($this->once())->method('rollBack');

        $repo = new GameRepository($pdo);
        $result = $repo->recordFullGame('u-user-uuid', 'gamer@example.com', [
            'score' => 100,
            'rounds' => []
        ]);

        $this->assertNull($result);
    }

    public function testListGamesByUser(): void {
        $sampleGames = [
            [
                'id' => 'g-1',
                'user_id' => 'u-user-uuid',
                'email' => 'gamer@example.com',
                'created_at' => '2026-10-01 12:00:00.000',
                'duration_ms' => 60000,
                'reached_level' => 10,
                'total_correct' => 9,
                'score' => 1500,
                'won' => 0,
                'game_mode' => 'elimination',
            ],
            [
                'id' => 'g-2',
                'user_id' => 'u-user-uuid',
                'email' => 'gamer@example.com',
                'created_at' => '2026-10-01 13:00:00.000',
                'duration_ms' => 120000,
                'reached_level' => 25,
                'total_correct' => 25,
                'score' => 5000,
                'won' => 1,
                'game_mode' => 'points',
            ]
        ];

        $pdo = $this->createMockPdo([
            'FROM games' => $sampleGames,
        ]);

        $repo = new GameRepository($pdo);
        $games = $repo->listGamesByUser('u-user-uuid', 20);

        $this->assertCount(2, $games);
        $this->assertSame('g-1', $games[0]['id']);
        $this->assertSame('g-2', $games[1]['id']);
    }

    public function testGetGameDetailsFound(): void {
        $gameRow = [
            'id' => 'g-100',
            'user_id' => 'u-user-uuid',
            'score' => 1200,
            'game_mode' => 'elimination',
        ];
        $roundRows = [
            [
                'id' => 'r-1',
                'game_id' => 'g-100',
                'level' => 1,
                'grid_colors_json' => '["#FF0000","#00FF00"]',
                'is_correct' => 1,
            ],
            [
                'id' => 'r-2',
                'game_id' => 'g-100',
                'level' => 2,
                'grid_colors_json' => 'invalid-json',
                'is_correct' => 0,
            ]
        ];

        $pdo = $this->createMockPdo([
            'WHERE id = :gid' => [$gameRow],
            'FROM rounds' => $roundRows,
        ]);

        $repo = new GameRepository($pdo);
        $details = $repo->getGameDetails('g-100', 'u-user-uuid');

        $this->assertNotNull($details);
        $this->assertSame('g-100', $details['id']);
        $this->assertArrayHasKey('rounds', $details);
        $this->assertCount(2, $details['rounds']);
        $this->assertSame('["#FF0000","#00FF00"]', $details['rounds'][0]['grid_colors_json']);
        $this->assertSame('invalid-json', $details['rounds'][1]['grid_colors_json']);
    }

    public function testGetGameDetailsReturnsNullWhenNotFound(): void {
        $pdo = $this->createMockPdo([
            'WHERE id = :gid' => [],
        ]);

        $repo = new GameRepository($pdo);
        $this->assertNull($repo->getGameDetails('ghost-game', 'u-user-uuid'));
    }
}
