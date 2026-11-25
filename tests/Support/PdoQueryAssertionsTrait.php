<?php

declare(strict_types=1);

namespace OceanViewFlats\Tests\Support;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Assert;

/**
 * Shared PHPUnit assertions for PDO::query() calls under strict static analysis.
 */
trait PdoQueryAssertionsTrait
{
    private function assertQuery(PDO $pdo, string $sql): PDOStatement
    {
        $stmt = $pdo->query($sql);
        Assert::assertNotFalse($stmt);
        return $stmt;
    }

    private function assertQueryColumn(PDO $pdo, string $sql): mixed
    {
        $stmt = $this->assertQuery($pdo, $sql);
        $value = $stmt->fetchColumn();
        $stmt->closeCursor();
        return $value;
    }
}
