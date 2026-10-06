<?php
declare(strict_types=1);

namespace Tests\Services;

use Prismatch\Services\RoomGameService;
use Tests\Support\BaseTestCase;

class RoomGameServiceTest extends BaseTestCase {
    private array $sampleRoom = [
        'id' => 'r-srv-1',
        'guid' => 'g-srv-1',
        'name' => 'Service Test Room',
        'owner_id' => 'u-owner-1',
        'owner_email' => 'owner@test.com',
        'status' => 'waiting',
        'rounds_total' => 25,
        'current_round' => 0,
        'is_private' => 0,
        'game_mode' => 'elimination',
        'created_at' => '2026-10-01 12:00:00.000',
        'updated_at' => '2026-10-01 12:00:00.000',
    ];

    private array $samplePlayer1 = [
        'id' => 'rp-1',
        'room_id' => 'r-srv-1',
        'user_id' => 'u-owner-1',
        'email' => 'owner@test.com',
        'status' => 'active',
        'score' => 0,
        'correct' => 0,
        'is_online' => 1,
        'joined_at' => '2026-10-01 12:00:00.000',
        'last_active' => '2026-10-01 12:00:00.000',
    ];

    private array $samplePlayer2 = [
        'id' => 'rp-2',
        'room_id' => 'r-srv-1',
        'user_id' => 'u-player-2',
        'email' => 'p2@test.com',
        'status' => 'active',
        'score' => 0,
        'correct' => 0,
        'is_online' => 1,
        'joined_at' => '2026-10-01 12:00:05.000',
        'last_active' => '2026-10-01 12:00:05.000',
    ];

    public function testGetTimingForRound(): void {
        $service = new RoomGameService($this->createMockPdo());

        // Round 1
        $t1 = $service->getTimingForRound(1);
        $this->assertSame(3000, $t1['countdown_ms']);
        $this->assertSame(5000, $t1['answer_ms']);
        $this->assertGreaterThanOrEqual(1200, $t1['show_ms']);

        // Round 2+
        $t2 = $service->getTimingForRound(2);
        $this->assertSame(1000, $t2['countdown_ms']);

        // High round minimum guarantee (never below 1200ms in colors mode)
        $t25 = $service->getTimingForRound(25, 'elimination');
        $this->assertGreaterThanOrEqual(1200, $t25['show_ms']);

        // Flags mode guarantee (never below 1600ms)
        $tFlags = $service->getTimingForRound(25, 'flags');
        $this->assertGreaterThanOrEqual(1600, $tFlags['show_ms']);

        // Special milestone rounds 21 and 41
        $t21 = $service->getTimingForRound(21);
        $this->assertSame(5000, $t21['show_ms']);

        $t41 = $service->getTimingForRound(41);
        $this->assertSame(5000, $t41['show_ms']);
    }

    public function testGetGridCountForRound(): void {
        $service = new RoomGameService($this->createMockPdo());

        // Low rounds total = 4
        $cLow = $service->getGridCountForRound(1, 4);
        $this->assertGreaterThanOrEqual(2, $cLow);

        // Standard 25 rounds
        $c1 = $service->getGridCountForRound(1, 25);
        $c15 = $service->getGridCountForRound(15, 25);
        $c25 = $service->getGridCountForRound(25, 25);

        $this->assertGreaterThanOrEqual(2, $c1);
        $this->assertGreaterThanOrEqual($c1, $c15);
        $this->assertGreaterThanOrEqual($c15, $c25);
    }

    public function testGenerateQuestion(): void {
        $service = new RoomGameService($this->createMockPdo());

        // Colors mode
        $qColor = $service->generateQuestion(1, 25, 'elimination');
        $this->assertArrayHasKey('target', $qColor);
        $this->assertArrayHasKey('grid', $qColor);
        $this->assertContains($qColor['target'], $qColor['grid']);

        // Flags mode
        $qFlags = $service->generateQuestion(1, 25, 'flags');
        $this->assertArrayHasKey('target', $qFlags);
        $this->assertArrayHasKey('grid', $qFlags);
        $this->assertContains($qFlags['target'], $qFlags['grid']);
    }

    public function testJoinRoomNotFound(): void {
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [],
        ]);

        $service = new RoomGameService($pdo);
        $res = $service->joinRoom('ghost-guid', 'u-user', 'user@test.com');

        $this->assertFalse($res['ok']);
        $this->assertSame('not_found', $res['error']);
    }

    public function testJoinRoomWhenFull(): void {
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'SELECT COUNT(*) FROM room_players' => [['COUNT(*)' => 10]],
            'SELECT status, current_round' => [['status' => 'waiting', 'current_round' => 0]],
            'INSERT INTO room_players' => [],
        ]);

        // When addPlayer returns false because room is full
        $repoMock = $this->createMock(\Prismatch\Repositories\RoomRepository::class);
        $repoMock->method('getRoomByGuid')->willReturn($this->sampleRoom);
        $repoMock->method('addPlayer')->willReturn(false);

        $ref = new \ReflectionClass(RoomGameService::class);
        $service = new RoomGameService($this->createMockPdo());
        $prop = $ref->getProperty('roomRepo');
        $prop->setValue($service, $repoMock);

        $res = $service->joinRoom('g-srv-1', 'u-11', 'p11@test.com');
        $this->assertFalse($res['ok']);
        $this->assertSame('room_full', $res['error']);
    }

    public function testJoinRoomSuccess(): void {
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'FROM room_players' => [$this->samplePlayer1],
            'SELECT status, current_round' => [['status' => 'waiting', 'current_round' => 0]],
            'INSERT INTO room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $service = new RoomGameService($pdo);
        $res = $service->joinRoom('g-srv-1', 'u-player-2', 'p2@test.com');

        $this->assertTrue($res['ok']);
        $this->assertArrayHasKey('players', $res);
        $this->assertSame('g-srv-1', $res['room']['guid']);
    }

    public function testStartOrNextRoundValidation(): void {
        // 1. Not found
        $service = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => []]));
        $res1 = $service->startOrNextRound('ghost', 'u-owner-1');
        $this->assertFalse($res1['ok']);
        $this->assertSame('not_found', $res1['error']);

        // 2. Not owner
        $service2 = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$this->sampleRoom]]));
        $res2 = $service2->startOrNextRound('g-srv-1', 'u-imposter');
        $this->assertFalse($res2['ok']);
        $this->assertSame('not_owner', $res2['error']);

        // 3. Already finished
        $finishedRoom = $this->sampleRoom;
        $finishedRoom['status'] = 'finished';
        $service3 = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$finishedRoom]]));
        $res3 = $service3->startOrNextRound('g-srv-1', 'u-owner-1');
        $this->assertTrue($res3['ok']);
        $this->assertTrue($res3['finished']);

        // 4. Less than 2 players in elimination mode
        $service4 = new RoomGameService($this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'FROM room_players' => [$this->samplePlayer1], // Only 1 player
            'DELETE FROM room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]));
        $res4 = $service4->startOrNextRound('g-srv-1', 'u-owner-1');
        $this->assertFalse($res4['ok']);
        $this->assertSame('min_players_required', $res4['error']);
    }

    public function testRestartRoomSecurityAndReset(): void {
        // Non-owner cannot restart
        $service = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$this->sampleRoom]]));
        $res = $service->restartRoom('g-srv-1', 'u-not-owner');
        $this->assertFalse($res['ok']);
        $this->assertSame('not_owner', $res['error']);

        // Owner restarts successfully
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'DELETE FROM room_rounds' => [],
            'DELETE FROM room_events' => [],
            'UPDATE room_players' => [],
            'UPDATE rooms' => [],
            'FROM room_players' => [$this->samplePlayer1, $this->samplePlayer2],
        ]);

        $serviceOwner = new RoomGameService($pdo);
        $resOwner = $serviceOwner->restartRoom('g-srv-1', 'u-owner-1', false);
        $this->assertTrue($resOwner['ok']);
    }

    public function testProcessAnswerValidations(): void {
        // Room not found
        $service = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => []]));
        $res = $service->processAnswer('ghost', 'u-1', 'u1@test.com', 1, '#FF0000', false, 1000);
        $this->assertFalse($res['ok']);
        $this->assertSame('not_found', $res['error']);

        // Room not active
        $serviceWaiting = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$this->sampleRoom]]));
        $resWaiting = $serviceWaiting->processAnswer('g-srv-1', 'u-owner-1', 'owner@test.com', 1, '#FF0000', false, 1000);
        $this->assertFalse($resWaiting['ok']);
        $this->assertSame('room_not_active', $resWaiting['error']);
    }

    public function testLeaveRoomInLobbyVersusActive(): void {
        // 1. Lobby leave: removes player
        $pdoLobby = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'DELETE FROM room_players' => [],
            'UPDATE rooms SET updated_at' => [],
            'FROM room_players' => [$this->samplePlayer1],
        ]);
        $serviceLobby = new RoomGameService($pdoLobby);
        $resLobby = $serviceLobby->leaveRoom('g-srv-1', 'u-player-2');
        $this->assertTrue($resLobby['ok']);
        $this->assertFalse($resLobby['spectator']);

        // 2. Active match leave: marks player eliminated (spectator)
        $activeRoom = $this->sampleRoom;
        $activeRoom['status'] = 'active';
        $activeRoom['current_round'] = 2;

        $pdoActive = $this->createMockPdo([
            'WHERE guid = :guid' => [$activeRoom],
            'UPDATE room_players SET status = \'eliminated\'' => [],
            'UPDATE rooms SET updated_at' => [],
            'INSERT INTO room_events' => [],
            'FROM room_players' => [$this->samplePlayer1],
            'UPDATE rooms SET status = :status' => [],
            'SELECT id, email' => [['id' => 'u-owner-1', 'total_plays' => 1, 'total_wins' => 0]],
            'UPDATE users' => [],
        ]);
        $serviceActive = new RoomGameService($pdoActive);
        $resActive = $serviceActive->leaveRoom('g-srv-1', 'u-player-2');
        $this->assertTrue($resActive['ok']);
        $this->assertTrue($resActive['spectator']);
    }

    public function testGetRoomAwards(): void {
        $players = [
            [
                'id' => 'rp-1',
                'room_id' => 'r-srv-1',
                'user_id' => 'u-fast',
                'email' => 'fast@test.com',
                'status' => 'active',
                'score' => 200,
                'correct' => 2,
            ],
            [
                'id' => 'rp-2',
                'room_id' => 'r-srv-1',
                'user_id' => 'u-slow',
                'email' => 'slow@test.com',
                'status' => 'active',
                'score' => 50,
                'correct' => 1,
            ],
        ];

        $events = [
            [
                'user_id' => 'u-fast',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => true, 'response_ms' => 400]),
                'round_index' => 1,
            ],
            [
                'user_id' => 'u-fast',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => true, 'response_ms' => 450]),
                'round_index' => 2,
            ],
            [
                'user_id' => 'u-slow',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => false, 'response_ms' => 1800]),
                'round_index' => 1,
            ],
            [
                'user_id' => 'u-slow',
                'event_type' => 'answer',
                'payload_json' => json_encode(['correct' => true, 'response_ms' => 2200]),
                'round_index' => 2,
            ],
        ];

        $pdo = $this->createMockPdo([
            'FROM room_players' => $players,
            'FROM room_events' => $events,
        ]);

        $service = new RoomGameService($pdo);
        $awards = $service->getRoomAwards('r-srv-1');

        $this->assertNotEmpty($awards);
        $awardIds = array_column($awards, 'id');
        $this->assertContains('speed_demon', $awardIds);
        $this->assertContains('overthinker', $awardIds);
        $this->assertContains('streak_master', $awardIds);
        $this->assertContains('sniper', $awardIds);
    }

    public function testUsePowerupValidations(): void {
        // Invalid powerup type
        $service = new RoomGameService($this->createMockPdo());
        $resInvalid = $service->usePowerup('g-srv-1', 'u-owner-1', 'owner@test.com', 'Owner', 'nuke');
        $this->assertFalse($resInvalid['ok']);
        $this->assertSame('invalid_powerup', $resInvalid['error']);

        // Room not found
        $serviceNotFound = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => []]));
        $resNotFound = $serviceNotFound->usePowerup('ghost', 'u-owner-1', 'owner@test.com', 'Owner', 'fifty_fifty');
        $this->assertFalse($resNotFound['ok']);
        $this->assertSame('room_not_found', $resNotFound['error']);

        // Room not active
        $serviceNotActive = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$this->sampleRoom]]));
        $resNotActive = $serviceNotActive->usePowerup('g-srv-1', 'u-owner-1', 'owner@test.com', 'Owner', 'fifty_fifty');
        $this->assertFalse($resNotActive['ok']);
        $this->assertSame('room_not_active', $resNotActive['error']);

        // Active room with success
        $activeRoom = $this->sampleRoom;
        $activeRoom['status'] = 'active';
        $activeRoom['current_round'] = 2;

        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$activeRoom],
            'FROM room_players' => [$this->samplePlayer1, $this->samplePlayer2],
            'SELECT COUNT(*) FROM room_events' => [['COUNT(*)' => 0]],
            'INSERT INTO room_events' => [],
        ]);
        $serviceActive = new RoomGameService($pdo);
        $resSuccess = $serviceActive->usePowerup('g-srv-1', 'u-owner-1', 'owner@test.com', 'Owner', 'ink_splat');
        $this->assertTrue($resSuccess['ok']);
        $this->assertSame('ink_splat', $resSuccess['payload']['type']);
        $this->assertSame('u-player-2', $resSuccess['payload']['target_user_id']);
    }

    public function testChooseTeamValidationsAndSuccess(): void {
        $service = new RoomGameService($this->createMockPdo());

        // Invalid team
        $resInvalid = $service->chooseTeam('g-srv-1', 'u-1', 'green');
        $this->assertFalse($resInvalid['ok']);
        $this->assertSame('invalid_team', $resInvalid['error']);

        // Room not found
        $serviceNotFound = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => []]));
        $resNotFound = $serviceNotFound->chooseTeam('ghost', 'u-1', 'blue');
        $this->assertFalse($resNotFound['ok']);
        $this->assertSame('room_not_found', $resNotFound['error']);

        // Room already active
        $activeRoom = $this->sampleRoom;
        $activeRoom['status'] = 'active';
        $serviceActive = new RoomGameService($this->createMockPdo(['WHERE guid = :guid' => [$activeRoom]]));
        $resActive = $serviceActive->chooseTeam('g-srv-1', 'u-1', 'blue');
        $this->assertFalse($resActive['ok']);
        $this->assertSame('match_already_started', $resActive['error']);

        // Success
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
            'UPDATE room_players SET team = :team' => [],
            'FROM room_players' => [$this->samplePlayer1, $this->samplePlayer2],
        ]);
        $serviceSuccess = new RoomGameService($pdo);
        $resSuccess = $serviceSuccess->chooseTeam('g-srv-1', 'u-owner-1', 'blue');
        $this->assertTrue($resSuccess['ok']);
        $this->assertSame('blue', $resSuccess['team']);
    }

    public function testFinishGameTeamsSummary(): void {
        $teamsRoom = $this->sampleRoom;
        $teamsRoom['game_mode'] = 'teams';

        $p1 = $this->samplePlayer1;
        $p1['team'] = 'red';
        $p1['score'] = 2500;

        $p2 = $this->samplePlayer2;
        $p2['team'] = 'blue';
        $p2['score'] = 1800;

        $pdo = $this->createMockPdo([
            'UPDATE rooms SET status = :status' => [],
            'FROM room_players' => [$p1, $p2],
            'WHERE id = :id' => [$teamsRoom],
            'FROM room_events' => [],
        ]);

        $service = new RoomGameService($pdo);
        $result = $service->finishGame('r-srv-1', 5, 'g-srv-1');

        $this->assertTrue($result['finished']);
        $this->assertSame('teams', $result['game_mode']);
        $this->assertNotNull($result['team_summary']);
        $this->assertSame(2500, $result['team_summary']['red_score']);
        $this->assertSame(1800, $result['team_summary']['blue_score']);
        $this->assertSame('red', $result['team_summary']['winning_team']);
    }
}


