<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

if (!defined('PREFIX_DB')) define('PREFIX_DB', 'test');

final class OauthLinkDatabase
{
    public string $password = '!';
    public bool $hasmore = false;
    public bool $commit = true;
    public bool $failmore = false;
    public array $queries = [];
    public int $rolls = 0;
    private string $last = '';

    # Starts the simulated unlink transaction
    public function setSqlBegin(): bool
    {
        return true;
    }

    # Records unlink SQL and can simulate failure of the other-link lookup
    public function getSqlQuery(string $query = '', array $params = []): object|false
    {
        $this->last = $query;
        $this->queries[] = $query;
        if ($this->failmore && str_starts_with($query, 'SELECT 1')) return false;
        return new stdClass();
    }

    # Returns the simulated user lock row or other-link existence row
    public function getSqlRow(object $query): array|false
    {
        if (str_contains($this->last, '_users WHERE')) return ['id' => 7, 'password' => $this->password];
        if (str_starts_with($this->last, 'SELECT 1')) return $this->hasmore ? [1] : false;
        return false;
    }

    # Returns the configured commit outcome
    public function setSqlCommit(): bool
    {
        return $this->commit;
    }

    # Records a rollback of the simulated unlink transaction
    public function setSqlRollback(): bool
    {
        $this->rolls++;
        return true;
    }
}

final class OauthLinkTest extends TestCase
{
    private mixed $olddb = null;
    private bool $haddb = false;

    protected function setUp(): void
    {
        require_once BASE_DIR.'/core/classes/oauth.php';
        $this->haddb = array_key_exists('db', $GLOBALS);
        $this->olddb = $GLOBALS['db'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->haddb) {
            $GLOBALS['db'] = $this->olddb;
        } else {
            unset($GLOBALS['db']);
        }
    }

    #[Test]
    public function getPasswordLinkDeletionWithoutLookup(): void
    {
        $db = new OauthLinkDatabase();
        $db->password = '$2y$usable';
        $GLOBALS['db'] = $db;
        $this->assertSame('', \Oauth::deleteLink(7, 'google'));
        $this->assertFalse(str_contains(implode("\n", $db->queries), 'SELECT 1'));
        $this->assertSame(0, $db->rolls);
    }

    #[Test]
    public function getPasswordlessDeletionWithAnotherLink(): void
    {
        $db = new OauthLinkDatabase();
        $db->hasmore = true;
        $GLOBALS['db'] = $db;
        $this->assertSame('', \Oauth::deleteLink(7, 'google'));
        $this->assertStringContainsString('provider <> :prov LIMIT 1', implode("\n", $db->queries));
        $this->assertSame(0, $db->rolls);
    }

    #[Test]
    public function getLastLoginMethodProtected(): void
    {
        $db = new OauthLinkDatabase();
        $GLOBALS['db'] = $db;
        $this->assertSame('unlink_last_method', \Oauth::deleteLink(7, 'google'));
        $this->assertStringNotContainsString('DELETE FROM', implode("\n", $db->queries));
        $this->assertSame(1, $db->rolls);
    }

    #[Test]
    public function getLookupAndCommitFailuresRolledBack(): void
    {
        $db = new OauthLinkDatabase();
        $db->failmore = true;
        $GLOBALS['db'] = $db;
        $this->assertSame('link_failed', \Oauth::deleteLink(7, 'google'));
        $this->assertSame(1, $db->rolls);
        $db = new OauthLinkDatabase();
        $db->password = '$2y$usable';
        $db->commit = false;
        $GLOBALS['db'] = $db;
        $this->assertSame('link_failed', \Oauth::deleteLink(7, 'google'));
        $this->assertSame(1, $db->rolls);
    }
}
