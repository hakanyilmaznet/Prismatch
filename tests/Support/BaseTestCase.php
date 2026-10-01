<?php
declare(strict_types=1);

namespace Tests\Support;

use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

abstract class BaseTestCase extends TestCase {
    /**
     * Creates a mock PDOStatement returning rows.
     */
    protected function createMockStatement(array $rows = [], int $rowCount = 1): PDOStatement {
        $stmt = $this->createMock(PDOStatement::class);
        $fetchIndex = 0;

        $stmt->method('execute')->willReturn(true);
        $stmt->method('bindValue')->willReturn(true);
        $stmt->method('bindParam')->willReturn(true);
        $stmt->method('rowCount')->willReturn($rowCount);

        $stmt->method('fetch')->willReturnCallback(function () use (&$fetchIndex, $rows) {
            if ($fetchIndex < count($rows)) {
                return $rows[$fetchIndex++];
            }
            return false;
        });

        $stmt->method('fetchAll')->willReturn($rows);

        $stmt->method('fetchColumn')->willReturnCallback(function (int $col = 0) use ($rows) {
            if (!empty($rows)) {
                $first = reset($rows);
                if (is_array($first)) {
                    $vals = array_values($first);
                    return $vals[$col] ?? false;
                }
                return $first;
            }
            return false;
        });

        return $stmt;
    }

    /**
     * Creates a mock PDO instance that returns PDOStatements based on query substrings.
     */
    protected function createMockPdo(array $map = []): PDO {
        $pdo = $this->createMock(PDO::class);

        $pdo->method('beginTransaction')->willReturn(true);
        $pdo->method('commit')->willReturn(true);
        $pdo->method('rollBack')->willReturn(true);
        $pdo->method('lastInsertId')->willReturn('1');

        $matcher = function ($query) use ($map) {
            foreach ($map as $pattern => $result) {
                if ($pattern === '*' || stripos((string)$query, (string)$pattern) !== false) {
                    if ($result instanceof PDOStatement) {
                        return $result;
                    }
                    if (is_callable($result)) {
                        return $result($query);
                    }
                    if (is_array($result)) {
                        return $this->createMockStatement($result);
                    }
                }
            }
            return $this->createMockStatement([]);
        };

        $pdo->method('prepare')->willReturnCallback($matcher);
        $pdo->method('query')->willReturnCallback($matcher);

        return $pdo;
    }
}
