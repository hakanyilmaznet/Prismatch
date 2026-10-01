<?php
declare(strict_types=1);

namespace Tests\Repositories;

use Prismatch\Repositories\UserRepository;
use Tests\Support\BaseTestCase;

class UserRepositoryTest extends BaseTestCase {
    private array $sampleUser = [
        'id' => 'u-1234-uuid',
        'email' => 'player@example.com',
        'created_at' => '2026-10-01 10:00:00.000',
        'last_login' => '2026-10-01 12:00:00.000',
        'total_plays' => '15',
        'total_wins' => '4',
        'best_level' => '12',
        'total_correct' => '85',
    ];

    public function testUpsertLoginWithValidEmail(): void {
        $pdo = $this->createMockPdo([
            'INSERT INTO users' => [],
        ]);

        $repo = new UserRepository($pdo);
        $repo->upsertLogin('Test.User@example.com');
        $this->assertTrue(true);
    }

    public function testUpsertLoginWithEmptyEmailDoesNothing(): void {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $repo = new UserRepository($pdo);
        $repo->upsertLogin('');
        $repo->upsertLogin('   ');
    }

    public function testFindByEmailReturnsUserWhenFound(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
        ]);

        $repo = new UserRepository($pdo);
        $user = $repo->findByEmail('player@example.com');

        $this->assertNotNull($user);
        $this->assertSame('u-1234-uuid', $user['id']);
        $this->assertSame('player@example.com', $user['email']);
    }

    public function testFindByEmailReturnsNullWhenNotFoundOrEmpty(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [],
        ]);

        $repo = new UserRepository($pdo);
        $this->assertNull($repo->findByEmail(''));
        $this->assertNull($repo->findByEmail('   '));
        $this->assertNull($repo->findByEmail('unknown@example.com'));
    }

    public function testFindByIdReturnsUserWhenFound(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
        ]);

        $repo = new UserRepository($pdo);
        $user = $repo->findById('u-1234-uuid');

        $this->assertNotNull($user);
        $this->assertSame('u-1234-uuid', $user['id']);
    }

    public function testFindByIdReturnsNullWhenNotFoundOrEmpty(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [],
        ]);

        $repo = new UserRepository($pdo);
        $this->assertNull($repo->findById(''));
        $this->assertNull($repo->findById('   '));
        $this->assertNull($repo->findById('non-existent-id'));
    }

    public function testEnsureByEmailReturnsExistingUser(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
        ]);

        $repo = new UserRepository($pdo);
        $user = $repo->ensureByEmail('player@example.com');

        $this->assertSame('u-1234-uuid', $user['id']);
        $this->assertSame('player@example.com', $user['email']);
    }

    public function testEnsureByEmailUpsertsAndReturnsDefaultIfInitialNotFound(): void {
        $callCount = 0;
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturnCallback(function () use (&$callCount) {
            $callCount++;
            if ($callCount === 1) {
                return false;
            }
            return [
                'id' => 'new-uuid',
                'email' => 'newplayer@example.com',
                'created_at' => '2026-10-01 12:00:00.000',
                'last_login' => '2026-10-01 12:00:00.000',
                'total_plays' => 0,
                'total_wins' => 0,
                'best_level' => 0,
                'total_correct' => 0,
            ];
        });

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $repo = new UserRepository($pdo);
        $user = $repo->ensureByEmail('newplayer@example.com');

        $this->assertSame('new-uuid', $user['id']);
        $this->assertSame('newplayer@example.com', $user['email']);
    }

    public function testEnsureByEmailFallbackWhenDoubleFetchReturnsEmpty(): void {
        $pdo = $this->createMockPdo([
            'SELECT' => [],
            'INSERT' => [],
        ]);

        $repo = new UserRepository($pdo);
        $user = $repo->ensureByEmail('fallback@example.com');

        $this->assertNotEmpty($user['id']);
        $this->assertSame('fallback@example.com', $user['email']);
        $this->assertSame(0, $user['total_plays']);
        $this->assertSame(0, $user['total_wins']);
    }

    public function testGetStatsForExistingUser(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
        ]);

        $repo = new UserRepository($pdo);
        $stats = $repo->getStats('player@example.com');

        $this->assertSame(15, $stats['total_plays']);
        $this->assertSame(4, $stats['total_wins']);
        $this->assertSame(12, $stats['best_level']);
        $this->assertSame(85, $stats['total_correct']);
    }

    public function testGetStatsForNonExistentUser(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [],
        ]);

        $repo = new UserRepository($pdo);
        $stats = $repo->getStats('ghost@example.com');

        $this->assertSame(0, $stats['total_plays']);
        $this->assertSame(0, $stats['total_wins']);
        $this->assertSame(0, $stats['best_level']);
        $this->assertSame(0, $stats['total_correct']);
    }

    public function testFormatDisplayName(): void {
        $repo = new UserRepository($this->createMockPdo());

        $this->assertSame('alice', $repo->formatDisplayName(['email' => 'alice@gmail.com']));
        $this->assertSame('bob', $repo->formatDisplayName(['email' => 'bob@prismatch.online']));
        $this->assertSame('Player', $repo->formatDisplayName(['email' => '']));
        $this->assertSame('Player', $repo->formatDisplayName([]));
        $this->assertSame('Player', $repo->formatDisplayName(['email' => '@domain.com']));
    }

    public function testGetDisplayName(): void {
        $pdo = $this->createMockPdo([
            'SELECT id, email' => [$this->sampleUser],
        ]);

        $repo = new UserRepository($pdo);
        $this->assertSame('player', $repo->getDisplayName('u-1234-uuid'));

        $emptyPdo = $this->createMockPdo(['SELECT' => []]);
        $emptyRepo = new UserRepository($emptyPdo);
        $this->assertSame('Player', $emptyRepo->getDisplayName('ghost-uuid'));
    }
}
