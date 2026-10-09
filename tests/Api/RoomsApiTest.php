<?php
declare(strict_types=1);

namespace Tests\Api;

use Tests\Support\ApiTestCase;

class RoomsApiTest extends ApiTestCase {
    private array $sampleRoomSeed = [
        'id' => 'r-api-room-1',
        'guid' => 'guid-api-room-1',
        'name' => 'API Test Arena',
        'owner_id' => 'u-owner-api',
        'owner_email' => 'owner@test.com',
        'status' => 'waiting',
        'rounds_total' => 25,
        'current_round' => 0,
        'max_players' => 25,
        'is_private' => 0,
        'game_mode' => 'elimination',
        'created_at' => '2026-10-01 12:00:00',
        'updated_at' => '2026-10-01 12:00:00',
    ];

    private array $samplePlayerSeed = [
        'id' => 'rp-api-1',
        'room_id' => 'r-api-room-1',
        'user_id' => 'u-owner-api',
        'email' => 'owner@test.com',
        'status' => 'active',
        'score' => 0,
        'correct' => 0,
        'is_online' => 1,
        'joined_at' => '2026-10-01 12:00:00',
        'last_active' => '2026-10-01 12:00:00',
    ];

    public function testRoomsCreateUnauthorized(): void {
        $res = $this->callApi('api/rooms_create.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['name' => 'Test Room'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsCreateMissingName(): void {
        $res = $this->callApi('api/rooms_create.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => ['name' => ''],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('name_required', $res['json']['error']);
    }

    public function testRoomsCreateSuccess(): void {
        $res = $this->callApi('api/rooms_create.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [
                'name' => 'Championship Arena',
                'game_mode' => 'points',
                'is_private' => false,
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertNotEmpty($res['json']['guid']);
        $this->assertSame('points', $res['json']['game_mode']);
        $this->assertFalse($res['json']['is_private']);
    }

    public function testRoomsCreateTeamsWithCustomNames(): void {
        $res = $this->callApi('api/rooms_create.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [
                'name' => 'Mega Team War',
                'game_mode' => 'teams',
                'is_private' => false,
                'teams' => [
                    ['id' => 'red', 'name' => 'Phoenix'],
                    ['id' => 'blue', 'name' => 'Hydra'],
                    ['id' => 'green', 'name' => 'Titan'],
                    ['id' => 'yellow', 'name' => 'Dragon'],
                ],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertNotEmpty($res['json']['guid']);
        $this->assertSame('teams', $res['json']['game_mode']);
        $this->assertNotNull($res['json']['settings_json']);

        $settings = json_decode($res['json']['settings_json'], true);
        $this->assertCount(4, $settings['teams']);
        $this->assertSame('Phoenix', $settings['teams'][0]['name']);
        $this->assertSame('Dragon', $settings['teams'][3]['name']);
    }

    public function testRoomsJoinUnauthorized(): void {
        $res = $this->callApi('api/rooms_join.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'guid-123'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsJoinMissingGuid(): void {
        $res = $this->callApi('api/rooms_join.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsJoinSuccess(): void {
        $res = $this->callApi('api/rooms_join.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-joiner',
                'user_email' => 'joiner@test.com',
            ],
            'body' => ['guid' => 'guid-api-room-1'],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertArrayHasKey('room', $res['json']);
        $this->assertArrayHasKey('players', $res['json']);
    }

    public function testRoomsStateUnauthorized(): void {
        $res = $this->callApi('api/rooms_state.php', [
            'method' => 'GET',
            'session' => [],
            'get' => ['guid' => 'guid-123'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsStateMissingGuid(): void {
        $res = $this->callApi('api/rooms_state.php', [
            'method' => 'GET',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'get' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsStateSuccess(): void {
        $res = $this->callApi('api/rooms_state.php', [
            'method' => 'GET',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'get' => ['guid' => 'guid-api-room-1'],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('guid-api-room-1', $res['json']['room']['guid']);
        $this->assertCount(1, $res['json']['players']);
    }

    public function testRoomsAnswerUnauthorized(): void {
        $res = $this->callApi('api/rooms_answer.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'g-1', 'round' => 1],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsAnswerMissingGuidOrRound(): void {
        $res = $this->callApi('api/rooms_answer.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => ['guid' => ''],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsNextRoundUnauthorized(): void {
        $res = $this->callApi('api/rooms_next_round.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'g-1'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
    }

    public function testRoomsNextRoundMissingGuid(): void {
        $res = $this->callApi('api/rooms_next_round.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsRoundEndUnauthorized(): void {
        $res = $this->callApi('api/rooms_round_end.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'g-1', 'round' => 1],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsRoundEndMissingGuid(): void {
        $res = $this->callApi('api/rooms_round_end.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsRestartUnauthorized(): void {
        $res = $this->callApi('api/rooms_restart.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'g-1'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
    }

    public function testRoomsRestartMissingGuid(): void {
        $res = $this->callApi('api/rooms_restart.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsLeaveUnauthorized(): void {
        $res = $this->callApi('api/rooms_leave.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'g-1'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
    }

    public function testRoomsLeaveMissingGuid(): void {
        $res = $this->callApi('api/rooms_leave.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'body' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsLeaveSuccess(): void {
        $res = $this->callApi('api/rooms_leave.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'body' => ['guid' => 'guid-api-room-1'],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertFalse($res['json']['spectator']);
    }

    public function testRoomsTickMissingGuid(): void {
        $res = $this->callApi('api/rooms_tick.php', [
            'method' => 'GET',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'get' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsTickSuccess(): void {
        $res = $this->callApi('api/rooms_tick.php', [
            'method' => 'GET',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'get' => ['guid' => 'guid-api-room-1'],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('waiting', $res['json']['status']);
    }

    public function testRoomsPlayerStatsMissingGuid(): void {
        $res = $this->callApi('api/rooms_player_stats.php', [
            'method' => 'GET',
            'session' => [
                'user_id' => 'u-1',
                'user_email' => 'u1@test.com',
            ],
            'get' => [],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsPlayerStatsSuccess(): void {
        $res = $this->callApi('api/rooms_player_stats.php', [
            'method' => 'GET',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'get' => ['guid' => 'guid-api-room-1'],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertArrayHasKey('summary', $res['json']);
        $this->assertArrayHasKey('stats', $res['json']);
    }

    public function testRoomsReactionUnauthorized(): void {
        $res = $this->callApi('api/rooms_reaction.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'guid-1', 'emoji' => '🔥'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsReactionBadRequest(): void {
        $res = $this->callApi('api/rooms_reaction.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'body' => ['guid' => ''],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsReactionSuccess(): void {
        $res = $this->callApi('api/rooms_reaction.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Captain Agile',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'emoji' => '🚀',
                'sound' => 'rocket',
                'text' => 'Let us go!',
            ],
            'seeds' => [
                'rooms' => [$this->sampleRoomSeed],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('🚀', $res['json']['payload']['emoji']);
        $this->assertSame('rocket', $res['json']['payload']['sound']);
        $this->assertSame('Captain Agile', $res['json']['payload']['user_name']);
        $this->assertSame('Let us go!', $res['json']['payload']['text']);
    }

    public function testRoomsPowerupUnauthorized(): void {
        $res = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'session' => [],
            'body' => ['guid' => 'guid-1', 'type' => 'fifty_fifty'],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsPowerupBadRequest(): void {
        $res = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'body' => ['guid' => ''],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertFalse($res['json']['ok']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsPowerupSuccessAndDuplicateBlock(): void {
        $activeRoom = array_merge($this->sampleRoomSeed, [
            'status' => 'active',
            'current_round' => 2,
        ]);
        $opponentSeed = [
            'id' => 'rp-api-2',
            'room_id' => 'r-api-room-1',
            'user_id' => 'u-player-2',
            'email' => 'p2@test.com',
            'status' => 'active',
            'score' => 150,
            'correct' => 1,
            'is_online' => 1,
            'joined_at' => '2026-10-01 12:00:00',
            'last_active' => '2026-10-01 12:00:00',
        ];

        // 1. Blocked when streak < 5
        $resBlocked = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Host User',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'type' => 'fifty_fifty',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed, $opponentSeed],
            ],
        ]);

        $this->assertSame(400, $resBlocked['status']);
        $this->assertFalse($resBlocked['json']['ok']);
        $this->assertSame('powerup_streak_required', $resBlocked['json']['error']);

        // Generate 5 consecutive correct answer events
        $eventsSeed = [];
        for ($i = 1; $i <= 5; $i++) {
            $eventsSeed[] = [
                'id' => 'ev-streak-' . $i,
                'room_id' => 'r-api-room-1',
                'round_index' => $i,
                'user_id' => 'u-owner-api',
                'email' => 'owner@test.com',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => true, 'response_ms' => 500]),
                'created_at' => '2026-10-01 12:00:0' . $i,
            ];
        }

        // 2. Successful fifty_fifty with 5-streak
        $res = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Host User',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'type' => 'fifty_fifty',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed, $opponentSeed],
                'room_events' => $eventsSeed,
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('fifty_fifty', $res['json']['payload']['type']);

        // 3. Successful ink_splat targeting rival (with 5-streak)
        $resInk = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Host User',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'type' => 'ink_splat',
                'target_user_id' => 'u-player-2',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed, $opponentSeed],
                'room_events' => $eventsSeed,
            ],
        ]);

        $this->assertSame(200, $resInk['status']);
        $this->assertTrue($resInk['json']['ok']);
        $this->assertSame('ink_splat', $resInk['json']['payload']['type']);
        $this->assertSame('u-player-2', $resInk['json']['payload']['target_user_id']);
    }

    public function testRoomsCreateTeamsMode(): void {
        $res = $this->callApi('api/rooms_create.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-teams-creator',
                'user_email' => 'creator@test.com',
            ],
            'body' => [
                'name' => 'Devs vs QAs Arena',
                'game_mode' => 'teams',
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('teams', $res['json']['game_mode']);
    }

    public function testRoomsTeamUnauthorized(): void {
        $res = $this->callApi('api/rooms_team.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'body' => [
                'guid' => 'guid-api-room-1',
                'team' => 'blue',
            ],
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertSame('login_required', $res['json']['error']);
    }

    public function testRoomsTeamBadRequest(): void {
        $res = $this->callApi('api/rooms_team.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'body' => [
                'guid' => '',
                'team' => '',
            ],
        ]);

        $this->assertSame(400, $res['status']);
        $this->assertSame('bad_request', $res['json']['error']);
    }

    public function testRoomsTeamSuccess(): void {
        $teamsRoom = $this->sampleRoomSeed;
        $teamsRoom['game_mode'] = 'teams';

        $res = $this->callApi('api/rooms_team.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'team' => 'blue',
            ],
            'seeds' => [
                'rooms' => [$teamsRoom],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['ok']);
        $this->assertSame('blue', $res['json']['team']);
    }

    public function testRoomsCreateNewGameModes(): void {
        foreach (['hot_potato', 'flash_memory', 'alchemy'] as $mode) {
            $res = $this->callApi('api/rooms_create.php', [
                'method' => 'POST',
                'use_sqlite' => true,
                'session' => [
                    'user_id' => 'u-owner-api',
                    'user_email' => 'owner@test.com',
                ],
                'body' => [
                    'name' => 'Crazy Mode ' . $mode,
                    'game_mode' => $mode,
                    'is_private' => false,
                ],
            ]);

            $this->assertSame(200, $res['status']);
            $this->assertTrue($res['json']['ok']);
            $this->assertSame($mode, $res['json']['game_mode']);
        }
    }

    public function testRoomsPowerupShieldAndSabotages(): void {
        $activeRoom = $this->sampleRoomSeed;
        $activeRoom['status'] = 'active';
        $activeRoom['current_round'] = 3;

        $opponentSeed = [
            'id' => 'rp-api-2',
            'room_id' => 'r-api-room-1',
            'user_id' => 'u-player-2',
            'email' => 'p2@test.com',
            'status' => 'active',
            'score' => 2000,
            'correct' => 2,
            'is_online' => 1,
            'has_shield' => 0,
            'joined_at' => '2026-10-01 12:00:00',
            'last_active' => '2026-10-01 12:00:00',
        ];

        $eventsSeed = [];
        for ($i = 1; $i <= 5; $i++) {
            $eventsSeed[] = [
                'id' => 'ev-streak-' . $i,
                'room_id' => 'r-api-room-1',
                'round_index' => $i,
                'user_id' => 'u-owner-api',
                'email' => 'owner@test.com',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => true, 'response_ms' => 500]),
                'created_at' => '2026-10-01 12:00:0' . $i,
            ];
        }

        // Test Shield activation
        $resShield = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Host User',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'type' => 'shield',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed, $opponentSeed],
                'room_events' => $eventsSeed,
            ],
        ]);

        $this->assertSame(200, $resShield['status']);
        $this->assertTrue($resShield['json']['ok']);
        $this->assertSame('shield', $resShield['json']['payload']['type']);

        // Test Freeze sabotage
        $resFreeze = $this->callApi('api/rooms_powerup.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-owner-api',
                'user_email' => 'owner@test.com',
                'user_name' => 'Host User',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'type' => 'freeze',
                'target_user_id' => 'u-player-2',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed, $opponentSeed],
                'room_events' => $eventsSeed,
            ],
        ]);

        $this->assertSame(200, $resFreeze['status']);
        $this->assertTrue($resFreeze['json']['ok']);
        $this->assertSame('freeze', $resFreeze['json']['payload']['type']);
    }

    public function testRoomsPredictEndpoint(): void {
        // Unauthorized
        $resUnauth = $this->callApi('api/rooms_predict.php', [
            'method' => 'POST',
            'body' => ['guid' => 'guid-api-room-1', 'predicted_user_id' => 'u-owner-api'],
        ]);
        $this->assertSame(403, $resUnauth['status']);
        $this->assertSame('login_required', $resUnauth['json']['error']);

        // Bad request
        $resBad = $this->callApi('api/rooms_predict.php', [
            'method' => 'POST',
            'session' => ['user_id' => 'u-spec-1', 'user_email' => 'spec@test.com'],
            'body' => ['guid' => '', 'predicted_user_id' => ''],
        ]);
        $this->assertSame(400, $resBad['status']);
        $this->assertSame('bad_request', $resBad['json']['error']);

        // Success
        $activeRoom = $this->sampleRoomSeed;
        $activeRoom['status'] = 'active';

        $resSuccess = $this->callApi('api/rooms_predict.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-spec-1',
                'user_email' => 'spec@test.com',
                'user_name' => 'Spectator One',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'predicted_user_id' => 'u-owner-api',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $resSuccess['status']);
        $this->assertTrue($resSuccess['json']['ok']);
        $this->assertSame('u-owner-api', $resSuccess['json']['predicted_user_id']);
    }

    public function testRoomsCheerEndpoint(): void {
        // Unauthorized
        $resUnauth = $this->callApi('api/rooms_cheer.php', [
            'method' => 'POST',
            'body' => ['guid' => 'guid-api-room-1', 'target_user_id' => 'u-owner-api'],
        ]);
        $this->assertSame(403, $resUnauth['status']);
        $this->assertSame('login_required', $resUnauth['json']['error']);

        // Bad request
        $resBad = $this->callApi('api/rooms_cheer.php', [
            'method' => 'POST',
            'session' => ['user_id' => 'u-spec-1', 'user_email' => 'spec@test.com'],
            'body' => ['guid' => '', 'target_user_id' => ''],
        ]);
        $this->assertSame(400, $resBad['status']);
        $this->assertSame('bad_request', $resBad['json']['error']);

        // Success
        $activeRoom = $this->sampleRoomSeed;
        $activeRoom['status'] = 'active';

        $resSuccess = $this->callApi('api/rooms_cheer.php', [
            'method' => 'POST',
            'use_sqlite' => true,
            'session' => [
                'user_id' => 'u-spec-1',
                'user_email' => 'spec@test.com',
                'user_name' => 'Spectator One',
            ],
            'body' => [
                'guid' => 'guid-api-room-1',
                'target_user_id' => 'u-owner-api',
                'emoji' => '🎈',
            ],
            'seeds' => [
                'rooms' => [$activeRoom],
                'room_players' => [$this->samplePlayerSeed],
            ],
        ]);

        $this->assertSame(200, $resSuccess['status']);
        $this->assertTrue($resSuccess['json']['ok']);
        $this->assertSame('u-owner-api', $resSuccess['json']['target_user_id']);
        $this->assertSame('🎈', $resSuccess['json']['emoji']);
    }
}

