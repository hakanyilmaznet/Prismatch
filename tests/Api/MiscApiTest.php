<?php
declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTestCase;

class MiscApiTest extends ApiTestCase {
    public function testSetLangEndpoint(): void {
        $res = $this->callApi('api/set_lang.php', [
            'method' => 'POST',
            'body' => ['lang' => 'tr'],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('tr', $res['json']['lang']);
    }

    public function testSetTimezoneValid(): void {
        $res = $this->callApi('api/set_timezone.php', [
            'method' => 'POST',
            'body' => ['timezone' => 'Europe/Istanbul'],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('Europe/Istanbul', $res['session']['browser_timezone'] ?? null);
    }

    public function testSetTimezoneInvalid(): void {
        $res = $this->callApi('api/set_timezone.php', [
            'method' => 'POST',
            'body' => ['timezone' => 'Invalid/Timezone'],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('invalid_timezone', $res['json']['error']);
    }

    public function testSetTimezoneEmpty(): void {
        $res = $this->callApi('api/set_timezone.php', [
            'method' => 'POST',
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('invalid_input', $res['json']['error']);
    }

    public function testPusherAuthUnauthorizedWhenLoggedOut(): void {
        $res = $this->callApi('api/pusher_auth.php', [
            'method' => 'POST',
            'session' => [],
            'post' => [
                'socket_id' => '123.456',
                'channel_name' => 'presence-room-test',
            ],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testPusherAuthMissingParams(): void {
        $res = $this->callApi('api/pusher_auth.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'user@test.com',
            ],
            'post' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testPusherAuthSuccess(): void {
        $res = $this->callApi('api/pusher_auth.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'user@test.com',
            ],
            'post' => [
                'socket_id' => '123.456',
                'channel_name' => 'presence-room-test',
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertArrayHasKey('auth', $res['json']);
    }

    public function testStorePendingInvalidJson(): void {
        $res = $this->callApi('api/store_pending.php', [
            'method' => 'POST',
            'body' => 'not-a-json{',
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('Invalid JSON', $res['json']['error']);
    }

    public function testStorePendingSuccess(): void {
        $res = $this->callApi('api/store_pending.php', [
            'method' => 'POST',
            'body' => ['score' => 250, 'game_mode' => 'points'],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame(250, $res['session']['pending_result']['score'] ?? null);
    }

    public function testLocalLoginValidation(): void {
        // Empty username
        $resEmpty = $this->callApi('api/local_login.php', [
            'method' => 'POST',
            'body' => ['username' => ''],
        ]);
        $this->assertSame(400, $resEmpty['status']);
        $this->assertSame('username_required', $resEmpty['json']['error']);

        // Invalid length (<2 chars)
        $resShort = $this->callApi('api/local_login.php', [
            'method' => 'POST',
            'body' => ['username' => 'a'],
        ]);
        $this->assertSame(400, $resShort['status']);
        $this->assertSame('username_invalid', $resShort['json']['error']);
    }

    public function testLocalLoginSuccess(): void {
        $res = $this->callApi('api/local_login.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'body' => ['username' => 'GuestPlayer', 'user_id' => 'client-123'],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('GuestPlayer', $res['json']['username']);
        $this->assertSame('GuestPlayer', $res['session']['user_name'] ?? null);
        $this->assertSame('local', $res['session']['login_provider'] ?? null);
    }

    public function testRecordGameUnauthorized(): void {
        $res = $this->callApi('api/record.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['score' => 100],
        ]);

        $this->assertSame(401, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('Not logged in', $res['json']['error']);
    }

    public function testRecordGameEmptyBody(): void {
        $res = $this->callApi('api/record.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => '',
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('Empty body', $res['json']['error']);
    }

    public function testRecordGameSuccess(): void {
        $res = $this->callApi('api/record.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [
                'score' => 2000,
                'reached_level' => 10,
                'total_correct' => 10,
                'duration_ms' => 45000,
                'game_mode' => 'points',
                'rounds' => [
                    [
                        'level' => 1,
                        'target_color' => '#FF0000',
                        'grid_colors' => ['#FF0000', '#00FF00'],
                        'picked_color' => '#FF0000',
                        'response_ms' => 1000,
                        'is_correct' => true,
                    ]
                ]
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertNotEmpty($res['json']['gameId']);
    }
}
