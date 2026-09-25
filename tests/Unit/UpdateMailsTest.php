<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S19.2 of docs/node: the newsletter step of the 6.3 update in setup/index.php keeps the pending recipients before the schema file
 * drops the mails column and queues them after it, once per campaign and address. tests/Support/update_probe.php lifts the shipped functions
 * out of the installer by name and drives them against a disposable schema, so the database of the stand is never touched.
 */
final class UpdateMailsTest extends TestCase
{
    private const ROWS = [
        [1, 'a@probe.test', 'admin@probe.test', 'First', 'newsletter'],
        [1, 'b@probe.test', 'admin@probe.test', 'First', 'newsletter'],
        [2, 'c@probe.test', 'admin@probe.test', 'Second', 'newsletter'],
    ];

    private const CAMPS = [[1, 5, 2, 2], [2, 5, 1, 1], [3, 0, 0, 0]];

    private static array $probe = [];

    # Run the probe once in its newsletter mode and memoize the report for every test in this class
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_mails';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' mails 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs']['clean'];
        }
        return self::$probe;
    }

    # Before the schema file the valid addresses are kept once each, nothing is queued yet, and a second pass keeps the first snapshot
    #[Test]
    public function theRecipientsAreKeptBeforeTheSchema(): void
    {
        $run = $this->getRun();
        foreach (['kept', 'again'] as $name) {
            $this->assertSame([true, 'prepared', ['campaigns' => 2, 'recipients' => 3], true, []], [$run[$name]['done'], $run[$name]['state'], $run[$name]['count'],
                $run[$name]['snap'], $run[$name]['rows']], $name);
        }
        $this->assertSame('', $run['again']['text'], 'A second pass took the snapshot again');
    }

    # After the column is gone the queue gets every address once with its campaign, and the campaigns wait with their count
    #[Test]
    public function aBreakAfterTheSchemaLosesNoRecipient(): void
    {
        $run = $this->getRun()['moved'];
        $this->assertTrue($run['done'], $run['text']);
        $this->assertSame(['verified', self::ROWS, self::CAMPS], [$run['state'], $run['rows'], $run['camps']]);
    }

    # A repeat of a verified step and a break in the middle of the queue neither double nor lose an address
    #[Test]
    public function aRepeatDoublesNothing(): void
    {
        $run = $this->getRun();
        foreach (['repeat', 'half'] as $name) $this->assertSame([true, self::ROWS, self::CAMPS], [$run[$name]['done'], $run[$name]['rows'], $run[$name]['camps']], $name);
        $this->assertStringContainsString('rows written: 2', $run['half']['text']);
    }

    # A snapshot that left its manifest stops the step, and a site with nothing pending gets no snapshot and no line
    #[Test]
    public function theStopsHold(): void
    {
        $run = $this->getRun();
        $this->assertFalse($run['forged']['done']);
        $this->assertStringContainsString('does not match its manifest', $run['forged']['text']);
        foreach ($run['none'] as $one) $this->assertSame(['', null, false, []], [$one['text'], $one['state'], $one['snap'], $one['rows']]);
    }
}
