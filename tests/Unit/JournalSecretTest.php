<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Batch 1 of docs/0-PRIVATE-DATA-2026.md: no secret of a request reaches a journal, whichever writer handles it
final class JournalSecretTest extends TestCase
{
    private static array $probe = [];

    private static array $dirs = [];

    # The probe tests/Support/journal_probe.php boots the real core with its configuration and LOGS_DIR in scratch and the request journal and both login reports on
    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/journal_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_journal_'.$mode.'_'.bin2hex(random_bytes(4));
        self::$dirs[] = $work;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        return self::$probe[$mode] = $data;
    }

    # Remove the scratch trees of every scenario, configuration copy included
    public static function tearDownAfterClass(): void
    {
        $drop = static function (string $dir) use (&$drop): void {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') continue;
                is_dir($dir.'/'.$name) ? $drop($dir.'/'.$name) : unlink($dir.'/'.$name);
            }
            rmdir($dir);
        };
        foreach (self::$dirs as $dir) $drop($dir);
    }

    # The request journal is a Logger channel: the password, the cookie and the session value are in no file, their names are in request.log, and log.log is gone
    #[Test]
    public function requestJournalKeepsNamesOnly(): void
    {
        $data = $this->getProbe('write');
        $this->assertArrayHasKey('request.log', $data['files']);
        $this->assertArrayNotHasKey('log.log', $data['files']);
        $this->assertArrayNotHasKey('error_php.log', $data['files'], 'a writer raised a PHP error: '.($data['files']['error_php.log'] ?? ''));
        foreach ($data['files'] as $name => $text) {
            foreach ($data['keys'] as $kind => $secret) $this->assertStringNotContainsString($secret, $text, 'the '.$kind.' value reached '.$name);
        }
        $line = json_decode(trim($data['files']['request.log']), true);
        $this->assertIsArray($line, 'request.log holds a structured line');
        $this->assertSame('request', $line['chan']);
        $this->assertSame('[masked]', $line['post']['user_password']);
        $this->assertContains('probe_cookie', $line['cookie_keys']);
        $this->assertContains('probe_session', $line['session_keys']);
    }

    # A refused login is recorded with the login that tried and without any attempted string, and the writer no longer accepts one
    #[Test]
    public function refusedLoginHasNoPassword(): void
    {
        $data = $this->getProbe('write');
        $this->assertSame(3, $data['params'], 'addLoginReport() takes no password');
        $this->assertStringContainsString('probeuser', $data['files']['user.log'] ?? '');
        $this->assertStringContainsString('probeadmin', $data['files']['admin.log'] ?? '');
        $this->assertStringNotContainsString($data['keys']['pass'], $data['files']['user.log'].$data['files']['admin.log']);
    }

    # The entry that triggers a rotation lands in the new journal, not in the file moved away, and both rotations name the archive after its journal
    #[Test]
    public function rotationEntryReachesNewFile(): void
    {
        $data = $this->getProbe('rotate');
        $this->assertStringContainsString('probeuser', $data['files']['user.log'] ?? '');
        $this->assertStringNotContainsString('old entry', $data['files']['user.log'] ?? '');
        $arch = array_filter(array_keys($data['files']), static fn($v) => preg_match('/^user_[0-9_-]+\.log\.(zip|gz|bz2|bak)$/', $v) === 1);
        $this->assertCount(1, $arch, 'the full journal was moved into one rotation archive named user_<date>.log.<ext>');
        $this->assertStringContainsString('probe rotation', $data['files']['warn.log'] ?? '');
        $this->assertStringNotContainsString('old entry', $data['files']['warn.log'] ?? '');
        $arch = array_filter(array_keys($data['files']), static fn($v) => preg_match('/^warn_[0-9_-]+\.log\.(zip|gz|bz2|bak)$/', $v) === 1);
        $this->assertCount(1, $arch, 'Logger did not name its rotation archive warn_<date>.log.<ext>');
    }

    # The security dashboard reads the structured request.log for its latest event
    #[Test]
    public function dashboardReadsRequestJournal(): void
    {
        $data = $this->getProbe('write');
        $this->assertStringContainsString('| REQUEST: ', $data['event']);
        $this->assertStringContainsString('"chan":"request"', $data['event']);
    }
}
