<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Journal names of docs/ARCHITECTURE.md, "Private Data Boundary": a journal is named after what it holds, a failure goes to error_*.log, a record of what happened to <meaning>.log
final class JournalNameTest extends TestCase
{
    # Every journal of storage/logs/ and what it holds: the rule table of the plan, which the label map of the security section has to cover exactly
    private const JOURNALS = ['admin', 'database', 'error_file', 'error_php', 'error_site', 'error_sql', 'file', 'filescan', 'filescan_tree', 'hack', 'oauth', 'request',
        'user', 'warn'];

    # The state files storage/logs/ holds beside the journals
    private const STATES = ['filescan.json', 'monitor.json', 'scheduler/heartbeat.json', 'scheduler/trigger.json'];

    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root.'/core/classes/logger.php';
        require_once $root.'/core/classes/cache.php';
        require_once $root.'/core/classes/filemanager.php';
        require_once $root.'/core/classes/upload.php';
        require_once $root.'/core/monitor.php';
    }

    # Every channel of Logger lands in a journal of the table at every level, and the security section labels every journal and nothing else
    #[Test]
    public function everyChannelHasAJournalAndEveryJournalALabel(): void
    {
        $file = new \ReflectionMethod(\Logger::class, 'getFile');
        foreach (['php', 'sql', 'file', 'site', 'warn', 'hack', 'request'] as $chan) {
            foreach (['notice', 'error'] as $levl) {
                $name = basename((string)$file->invoke(null, $chan, $levl), '.log');
                $this->assertContains($name, self::JOURNALS, 'The channel '.$chan.' at '.$levl.' writes a journal outside the rule');
            }
        }
        preg_match('/\$labels = \[(.*?)\];/s', $this->getSource('admin/modules/security.php'), $map);
        preg_match_all("/'([a-z_]+)' => _SEC_STAT_/", $map[1] ?? '', $keys);
        $labels = $keys[1];
        sort($labels);
        $this->assertSame(self::JOURNALS, $labels, 'The label map of the security section does not cover exactly the journals of the rule');
    }

    # Every name the code writes into LOGS_DIR is a journal, a state file or a directory of state, and every rotation archive is named after its journal
    #[Test]
    public function everyNameInTheLogFolderFollowsTheRule(): void
    {
        $seen = 0;
        foreach (['core', 'admin', 'modules', 'public'] as $top) {
            foreach (getTreeFiles(dirname(__DIR__, 2).'/'.$top) as $item) {
                if ($item->getExtension() !== 'php') continue;
                $path = $item->getPathname();
                $text = (string)file_get_contents($path);
                preg_match_all("/LOGS_DIR\s*\.\s*'([^']*)'/", $text, $refs);
                foreach ($refs[1] as $ref) {
                    $seen++;
                    $this->assertTrue($this->isLogName($ref), $path.' writes '.$ref.' into LOGS_DIR, which is no journal, state file or state directory');
                }
                preg_match_all("/addCompress\(LOGS_DIR,\s*\\$\w+,\s*([^,]+),/", $text, $calls);
                foreach ($calls[1] as $call) {
                    $this->assertMatchesRegularExpression("/^(\\$\w+\.'_'|'[a-z_]+_')\.date\([^)]*\)\.'\.log'$/", $call, $path.' names an archive '.$call);
                    $this->assertDoesNotMatchRegularExpression('/log_|dump/', $call, $path.' names an archive with an old prefix');
                }
            }
        }
        $this->assertGreaterThan(10, $seen, 'The scan found too few names to prove anything');
        $this->assertStringContainsString("pathinfo(\$file, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s').'.log'", $this->getSource('core/classes/logger.php'));
    }

    # A refused file operation is a record of what happened and goes to file.log only; a capability the build lacks is a failure and goes to error_file.log only
    #[Test]
    public function aFileOperationIsRoutedByLevelAndWrittenOnce(): void
    {
        $mark = 'refused-'.bin2hex(random_bytes(4));
        \Logger::addFile('warning', 'Upload file operation', ['path' => $mark]);
        $why = 'probe-'.bin2hex(random_bytes(4));
        (new \ReflectionMethod(\Upload::class, 'addCapsNote'))->invoke(new \Upload(sys_get_temp_dir()), $why);
        $file = $this->getJournal('file.log');
        $fail = $this->getJournal('error_file.log');
        $this->assertStringContainsString($mark, $file, 'The refused operation is missing from file.log');
        $this->assertStringNotContainsString($mark, $fail, 'The refused operation also reached error_file.log');
        $this->assertStringContainsString($why, $fail, 'The missing capability is missing from error_file.log');
        $this->assertStringNotContainsString($why, $file, 'The missing capability also reached file.log');
    }

    # A cache clear while a lock of storage/cache/locks/ is held leaves the lock file in place and the lock held, while the cached entries go
    #[Test]
    public function aCacheClearLeavesAHeldLock(): void
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()).'/slaed-journal-'.bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $entry = CACHE_DIR.'/data/probe-'.bin2hex(random_bytes(4)).'.json';
        if (!is_dir(dirname($entry))) mkdir(dirname($entry), 0777, true);
        file_put_contents($entry, '{}');
        $lock = \FileManager::getPathLock($dir);
        try {
            $this->assertNotFalse($lock, 'The file layer could not take the lock');
            $key = (string)(new \ReflectionMethod(\FileManager::class, 'getLockKey'))->invoke(null, $dir);
            $path = CACHE_DIR.'/locks/uploads/'.substr(sha1($key), 0, 16).'.lock';
            $this->assertFileExists($path, 'The lock is not under storage/cache/locks/');
            \Cache::deleteAll();
            $this->assertFileExists($path, 'Clearing the cache removed a held lock');
            $this->assertFileDoesNotExist($entry, 'Clearing the cache kept a cached entry');
            $other = fopen($path, 'cb');
            $this->assertFalse(flock($other, LOCK_EX | LOCK_NB), 'The lock was released by the cache clear');
            fclose($other);
        } finally {
            \FileManager::deletePathLock($lock);
            rmdir($dir);
        }
    }

    # The dashboard counter reads the structured line Logger writes and counts warning, error and critical within the window, nothing else
    #[Test]
    public function theErrorCounterCountsTheProblemLevelsOnly(): void
    {
        $base = getErrorLogCountHours(24);
        $now = date('c');
        $rows = [
            ['ts' => $now, 'level' => 'error'],
            ['ts' => $now, 'level' => 'critical'],
            ['ts' => $now, 'level' => 'warning'],
            ['ts' => $now, 'level' => 'notice'],
            ['ts' => $now, 'level' => 'info'],
            ['ts' => $now, 'level' => 'debug'],
            ['ts' => date('c', time() - 2 * 86400), 'level' => 'error'],
        ];
        $text = implode("\n", array_map(static fn(array $row): string => (string)json_encode($row + ['chan' => 'site', 'msg' => 'probe']), $rows));
        file_put_contents(LOGS_DIR.'/error_site.log', $text."\n[2026-01-01 00:00:00] old text line\n", FILE_APPEND);
        $this->assertSame((is_int($base) ? $base : 0) + 3, getErrorLogCountHours(24));
    }

    # Whether one literal joined to LOGS_DIR names a journal, a state file or a directory below which only state is kept
    private function isLogName(string $ref): bool
    {
        if (in_array($ref, ['/', '/scheduler', '/scheduler/'], true)) return true;
        if (preg_match('#^/([a-z_]+)\.log$#', $ref, $mx)) return in_array($mx[1], self::JOURNALS, true);
        return in_array(ltrim($ref, '/'), self::STATES, true);
    }

    # Read one journal of this run, empty when it was never written
    private function getJournal(string $name): string
    {
        return is_file(LOGS_DIR.'/'.$name) ? (string)file_get_contents(LOGS_DIR.'/'.$name) : '';
    }

    # Read one repository file
    private function getSource(string $path): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }
}
