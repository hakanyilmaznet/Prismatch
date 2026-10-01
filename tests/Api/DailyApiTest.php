<?php
declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTestCase;

class DailyApiTest extends ApiTestCase {
    public function testDailyStatusUnauthorized(): void {
        $res = $this->callApi('api/daily_status.php', [
            'method' => 'GET',
            'session' => [],
        ]);

        $this->assertSame(401, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('not_logged_in', $res['json']['error']);
    }

    public function testDailyStatusSuccess(): void {
        $res = $this->callApi('api/daily_status.php', [
            'method' => 'GET',
            'session' => [
                'user_id' => 'u-daily-1',
                'user_email' => 'daily@test.com',
            ],
            'get' => [
                'day' => '2026-10-01',
                'mode' => 'points',
            ],
            'use_sqlite' => true,
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('2026-10-01', $res['json']['day']);
        $this->assertSame('points', $res['json']['mode']);
        $this->assertArrayHasKey('modes', $res['json']);
        $this->assertFalse($res['json']['played']);
    }

    public function testDailyLeaderboardEndpoint(): void {
        $res = $this->callApi('api/daily_leaderboard.php', [
            'method' => 'GET',
            'get' => [
                'day' => '2026-10-01',
                'country' => 'TR',
                'mode' => 'elimination',
            ],
            'use_sqlite' => true,
            'seeds' => [
                'daily_scores' => [
                    [
                        'id' => 'ds-seed-1',
                        'day_utc' => '2026-10-01',
                        'challenge_date' => '2026-10-01',
                        'email' => 'champ@test.com',
                        'score' => 5000,
                        'reached_level' => 25,
                        'total_correct' => 25,
                        'duration_ms' => 60000,
                        'country' => 'TR',
                        'language' => 'tr',
                        'game_mode' => 'elimination',
                        'created_at' => '2026-10-01 12:00:00',
                    ]
                ]
            ]
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertCount(1, $res['json']['rows']);
        $this->assertSame('champ@test.com', $res['json']['rows'][0]['user']);
    }

    public function testDailyRecordEndpointSuccess(): void {
        $res = $this->callApi('api/daily_record.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-daily-record',
                'user_email' => 'record@test.com',
            ],
            'body' => [
                'reached_level' => 15,
                'total_correct' => 14,
                'duration_ms' => 75000,
                'game_mode' => 'points',
                'won' => true,
                'country' => 'US',
                'rounds' => [
                    [
                        'level' => 1,
                        'is_correct' => 1,
                        'response_ms' => 500,
                    ]
                ],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertGreaterThan(0, $res['json']['score']);
        $this->assertSame('points', $res['json']['game_mode']);
    }
}
