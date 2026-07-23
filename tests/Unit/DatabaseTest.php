<?php

namespace Tests\Unit;

use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once BASE_DIR.'/core/classes/pdo.php';

final class DatabaseTransactionPdo extends PDO
{
    public bool $active = false;
    public string $failure = '';
    public int $begins = 0;
    public int $commits = 0;
    public int $rolls = 0;

    # Creates a controllable PDO double without opening a database connection
    public function __construct()
    {
    }

    # Returns the simulated transaction state or raises the configured status error
    public function inTransaction(): bool
    {
        if ($this->failure === 'status') throw new PDOException('status failed');
        return $this->active;
    }

    # Starts the simulated transaction or raises the configured begin error
    public function beginTransaction(): bool
    {
        $this->begins++;
        if ($this->failure === 'begin') throw new PDOException('begin failed');
        $this->active = true;
        return true;
    }

    # Commits the simulated transaction or raises the configured commit error
    public function commit(): bool
    {
        $this->commits++;
        if ($this->failure === 'commit') throw new PDOException('commit failed');
        $this->active = false;
        return true;
    }

    # Rolls back the simulated transaction or raises the configured rollback error
    public function rollBack(): bool
    {
        $this->rolls++;
        if ($this->failure === 'rollback') throw new PDOException('rollback failed');
        $this->active = false;
        return true;
    }
}

final class DatabaseTest extends TestCase
{
    # Builds the database wrapper around a controllable PDO double
    private function getDatabase(DatabaseTransactionPdo $pdo): \Database
    {
        $ref = new ReflectionClass(\Database::class);
        $db = $ref->newInstanceWithoutConstructor();
        $db->sqlconnid = $pdo;
        return $db;
    }

    #[Test]
    public function getTransactionLifecycle(): void
    {
        $pdo = new DatabaseTransactionPdo();
        $db = $this->getDatabase($pdo);
        $this->assertTrue($db->setSqlBegin());
        $this->assertTrue($pdo->active);
        $this->assertFalse($db->setSqlBegin());
        $this->assertSame(1, $pdo->begins);
        $this->assertTrue($db->setSqlCommit());
        $this->assertFalse($pdo->active);
        $this->assertFalse($db->setSqlCommit());
        $this->assertTrue($db->setSqlBegin());
        $this->assertTrue($db->setSqlRollback());
        $this->assertFalse($pdo->active);
        $this->assertFalse($db->setSqlRollback());
    }

    #[Test]
    public function getTransactionErrorsAsFalse(): void
    {
        foreach (['begin', 'commit', 'rollback', 'status'] as $failure) {
            $pdo = new DatabaseTransactionPdo();
            $db = $this->getDatabase($pdo);
            $pdo->failure = $failure;
            if ($failure === 'commit' || $failure === 'rollback') $pdo->active = true;
            $ok = match ($failure) {
                'commit' => $db->setSqlCommit(),
                'rollback' => $db->setSqlRollback(),
                default => $db->setSqlBegin(),
            };
            $this->assertFalse($ok, $failure);
            $this->assertInstanceOf(PDOException::class, $db->laste);
            $this->assertSame($failure.' failed', $db->laste->getMessage());
        }
    }

    #[Test]
    public function getTransactionsWithoutPdoAsFalse(): void
    {
        $ref = new ReflectionClass(\Database::class);
        $db = $ref->newInstanceWithoutConstructor();
        $this->assertFalse($db->setSqlBegin());
        $this->assertFalse($db->setSqlCommit());
        $this->assertFalse($db->setSqlRollback());
    }
}
