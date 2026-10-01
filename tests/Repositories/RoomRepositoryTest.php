<?php
declare(strict_types=1);

namespace Tests\Repositories;

use Prismatch\Repositories\RoomRepository;
use Tests\Support\BaseTestCase;

class RoomRepositoryTest extends BaseTestCase {
    private array $sampleRoom = [
        'id' => 'r-uuid-1',
        'guid' => 'g-guid-1',
        'name' => 'Test Room Alpha',
        'owner_id' => 'u-owner-1',
        'owner_email' => 'owner@example.com',
        'status' => 'waiting',
        'rounds_total' => 25,
        'current_round' => 0,
        'is_private' => 0,
        'game_mode' => 'elimination',
        'created_at' => '2026-10-01 12:00:00.000',
        'updated_at' => '2026-10-01 12:00:00.000',
    ];

    private array $samplePlayer = [
        'id' => 'rp-uuid-1',
        'room_id' => 'r-uuid-1',
        'user_id' => 'u-owner-1',
        'email' => 'owner@example.com',
        'status' => 'active',
        'score' => 0,
        'correct' => 0,
        'eliminated_round' => null,
        'is_online' => 1,
        'joined_at' => '2026-10-01 12:00:00.000',
        'last_active' => '2026-10-01 12:00:00.000',
    ];

    public function testCreateRoom(): void {
        $pdo = $this->createMockPdo([
            'INSERT INTO rooms' => [],
            'SELECT status, current_round' => [['status' => 'waiting', 'current_round' => 0]],
            'INSERT INTO room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $room = $repo->createRoom('u-owner-1', 'owner@example.com', 20, 'My Arena', true, 'points');

        $this->assertNotEmpty($room['id']);
        $this->assertNotEmpty($room['guid']);
        $this->assertSame('My Arena', $room['name']);
        $this->assertSame('u-owner-1', $room['owner_id']);
        $this->assertSame('owner@example.com', $room['owner_email']);
        $this->assertSame('waiting', $room['status']);
        $this->assertSame(20, $room['rounds_total']);
        $this->assertSame(1, $room['is_private']);
        $this->assertSame('points', $room['game_mode']);
    }

    public function testGetRoomByGuid(): void {
        $pdo = $this->createMockPdo([
            'WHERE guid = :guid' => [$this->sampleRoom],
        ]);

        $repo = new RoomRepository($pdo);
        $room = $repo->getRoomByGuid('g-guid-1');

        $this->assertNotNull($room);
        $this->assertSame('r-uuid-1', $room['id']);
        $this->assertSame('g-guid-1', $room['guid']);

        $emptyRepo = new RoomRepository($this->createMockPdo(['WHERE guid = :guid' => []]));
        $this->assertNull($emptyRepo->getRoomByGuid('unknown-guid'));
    }

    public function testGetRoomById(): void {
        $pdo = $this->createMockPdo([
            'WHERE id = :id' => [$this->sampleRoom],
        ]);

        $repo = new RoomRepository($pdo);
        $room = $repo->getRoomById('r-uuid-1');

        $this->assertNotNull($room);
        $this->assertSame('r-uuid-1', $room['id']);

        $emptyRepo = new RoomRepository($this->createMockPdo(['WHERE id = :id' => []]));
        $this->assertNull($emptyRepo->getRoomById('unknown-id'));
    }

    public function testListUserRooms(): void {
        $pdo = $this->createMockPdo([
            'SELECT r.id, r.guid' => [], // cleanupInactiveRooms check
            'FROM rooms r' => [$this->sampleRoom],
        ]);

        $repo = new RoomRepository($pdo);
        $rooms = $repo->listUserRooms('u-owner-1', 25);

        $this->assertCount(1, $rooms);
        $this->assertSame('r-uuid-1', $rooms[0]['id']);
    }

    public function testListPublicRooms(): void {
        $publicRoom = $this->sampleRoom;
        $publicRoom['players_count'] = 3;

        $pdo = $this->createMockPdo([
            'SELECT r.id, r.guid' => [],
            'FROM rooms r' => [$publicRoom],
        ]);

        $repo = new RoomRepository($pdo);
        $rooms = $repo->listPublicRooms(10);

        $this->assertCount(1, $rooms);
        $this->assertSame(3, $rooms[0]['players_count']);
    }

    public function testAddPlayerInLobby(): void {
        $pdo = $this->createMockPdo([
            'SELECT status, current_round' => [['status' => 'waiting', 'current_round' => 0]],
            'INSERT INTO room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $res = $repo->addPlayer('r-uuid-1', 'u-player-2', 'p2@example.com');

        $this->assertTrue($res);
    }

    public function testAddPlayerInActiveGameJoinsAsSpectator(): void {
        $executedStatus = null;
        $pdo = $this->createMockPdo([
            'SELECT status, current_round' => [['status' => 'active', 'current_round' => 3]],
            'INSERT INTO room_players' => function () use (&$executedStatus) {
                $stmt = $this->createMock(\PDOStatement::class);
                $stmt->method('execute')->willReturnCallback(function ($params) use (&$executedStatus) {
                    $executedStatus = $params[':status'] ?? null;
                    return true;
                });
                return $stmt;
            },
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $res = $repo->addPlayer('r-uuid-1', 'u-late-spectator', 'spectator@example.com');

        $this->assertTrue($res);
        $this->assertSame('eliminated', $executedStatus);
    }

    public function testListPlayers(): void {
        $p2 = $this->samplePlayer;
        $p2['id'] = 'rp-uuid-2';
        $p2['user_id'] = 'u-player-2';

        $pdo = $this->createMockPdo([
            'FROM room_players' => [$this->samplePlayer, $p2],
        ]);

        $repo = new RoomRepository($pdo);
        $players = $repo->listPlayers('r-uuid-1');

        $this->assertCount(2, $players);
        $this->assertSame('u-owner-1', $players[0]['user_id']);
        $this->assertSame('u-player-2', $players[1]['user_id']);
    }

    public function testTouchPlayer(): void {
        $pdo = $this->createMockPdo([
            'UPDATE room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $repo->touchPlayer('r-uuid-1', 'u-owner-1', true);
        $this->assertTrue(true);
    }

    public function testRemovePlayer(): void {
        $pdo = $this->createMockPdo([
            'DELETE FROM room_players' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $res = $repo->removePlayer('r-uuid-1', 'u-owner-1');
        $this->assertTrue($res);
    }

    public function testCleanupStalePlayers(): void {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('rowCount')->willReturn(2);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($stmt) {
            if (stripos($sql, 'DELETE FROM room_players') !== false) {
                return $stmt;
            }
            return $this->createMockStatement([]);
        });

        $repo = new RoomRepository($pdo);
        $count = $repo->cleanupStalePlayers('r-uuid-1', 'u-owner-1', 10);

        $this->assertSame(2, $count);
    }

    public function testMarkEliminated(): void {
        $pdo = $this->createMockPdo([
            'UPDATE room_players SET status = \'eliminated\'' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $repo->markEliminated('r-uuid-1', 'u-player-2', 4);
        $this->assertTrue(true);
    }

    public function testAddScore(): void {
        $pdo = $this->createMockPdo([
            'UPDATE room_players SET score = score +' => [],
            'UPDATE rooms SET updated_at' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $repo->addScore('r-uuid-1', 'u-player-2', 500, 1);
        $this->assertTrue(true);
    }

    public function testSetStatusActive(): void {
        $capturedParams = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function ($params) use (&$capturedParams) {
            $capturedParams = $params;
            return true;
        });

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($stmt) {
            if (stripos($sql, "status = 'active'") !== false) {
                return $stmt;
            }
            return $this->createMockStatement([]);
        });

        $repo = new RoomRepository($pdo);
        $repo->setStatus('r-uuid-1', 'active');

        $this->assertNotNull($capturedParams);
        $this->assertArrayHasKey(':started_at', $capturedParams);
        $this->assertArrayHasKey(':updated_at', $capturedParams);
        $this->assertArrayHasKey(':rid', $capturedParams);
        $this->assertSame('r-uuid-1', $capturedParams[':rid']);
    }

    public function testSetStatusFinished(): void {
        $capturedParams = null;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturnCallback(function ($params) use (&$capturedParams) {
            $capturedParams = $params;
            return true;
        });

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($stmt) {
            if (stripos($sql, "status = 'finished'") !== false) {
                return $stmt;
            }
            return $this->createMockStatement([]);
        });

        $repo = new RoomRepository($pdo);
        $repo->setStatus('r-uuid-1', 'finished', 15);

        $this->assertNotNull($capturedParams);
        $this->assertArrayHasKey(':finished_at', $capturedParams);
        $this->assertArrayHasKey(':fr', $capturedParams);
        $this->assertArrayHasKey(':updated_at', $capturedParams);
        $this->assertArrayHasKey(':rid', $capturedParams);
        $this->assertSame(15, $capturedParams[':fr']);
    }

    public function testLogEvent(): void {
        $pdo = $this->createMockPdo([
            'INSERT INTO room_events' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $repo->logEvent('r-uuid-1', 1, 'u-player-1', 'p1@test.com', 'test_event', ['info' => 'sample']);
        $this->assertTrue(true);
    }

    public function testTouchRoom(): void {
        $pdo = $this->createMockPdo([
            'UPDATE rooms SET updated_at = NOW()' => [],
        ]);

        $repo = new RoomRepository($pdo);
        $repo->touchRoom('r-uuid-1');
        $this->assertTrue(true);
    }

    public function testCleanupInactiveRoomsDeletesStaleRooms(): void {
        $staleRooms = [
            ['id' => 'stale-1', 'guid' => 'g-stale-1', 'name' => 'Old Room 1'],
            ['id' => 'stale-2', 'guid' => 'g-stale-2', 'name' => 'Old Room 2'],
        ];

        $deletedRids = [];
        $delStmt = $this->createMock(\PDOStatement::class);
        $delStmt->method('execute')->willReturnCallback(function ($params) use (&$deletedRids) {
            $deletedRids[] = $params[':rid'] ?? null;
            return true;
        });

        $selectStmt = $this->createMockStatement($staleRooms);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturnCallback(function ($sql) use ($selectStmt, $delStmt) {
            if (stripos($sql, 'SELECT r.id, r.guid') !== false) {
                return $selectStmt;
            }
            if (stripos($sql, 'DELETE FROM rooms WHERE id = :rid') !== false) {
                return $delStmt;
            }
            return $this->createMockStatement([]);
        });

        $repo = new RoomRepository($pdo);
        $deletedCount = $repo->cleanupInactiveRooms(60);

        $this->assertSame(2, $deletedCount);
        $this->assertSame(['stale-1', 'stale-2'], $deletedRids);
    }

    public function testCleanupInactiveRoomsHandlesExceptionGracefully(): void {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \RuntimeException('Connection failed'));

        $repo = new RoomRepository($pdo);
        $deletedCount = $repo->cleanupInactiveRooms(60);

        $this->assertSame(0, $deletedCount);
    }
}
