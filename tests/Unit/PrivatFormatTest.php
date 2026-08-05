<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Step 10 of docs/PRIVAT-2026.md: tools/privat-migrate.php classifies the stored private messages, writes the format
 * column and a ledger, rewrites every body into the source its class is source of, and decodes every title into the
 * plain source it becomes at stage 2. tests/Support/privat_format_probe.php boots the real core, so the fixture
 * messages that name an editor are encoded by the writer an installation really runs, seeds the legacy shapes and the
 * html branch as stored bytes, and then drives the shipped tool over them through --db and --prefix, which is the
 * rehearsal contract of the plan. The site database is never touched.
 */
final class PrivatFormatTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/privat_format_probe.php';
        $work = sys_get_temp_dir().'/slaed_privat_format';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
        $this->assertNotEmpty($data['cases'], 'The probe ran no case');
        return self::$probe = $data;
    }

    # Every fixture that names an editor was really written under that editor, or its stored bytes would prove nothing about the branch under test
    #[Test]
    public function everyWrittenCaseUsedTheEditorItNames(): void
    {
        $data = $this->getProbe();
        foreach ($data['cases'] as $key => $case) {
            if ($case['edit'] === '') continue;
            $this->assertSame($case['edit'], $data['modes'][$key + 1] ?? '', 'Row '.($key + 1).' was written under the wrong editor format');
        }
    }

    # report is read-only: it writes no format, rewrites no message and leaves no ledger behind
    #[Test]
    public function theReportModeChangesNothing(): void
    {
        $this->assertTrue($this->getProbe()['quiet'], 'report wrote to the table or left a ledger behind');
    }

    # Every row carries the format its class maps to, which is what makes the rendering contract of stage 2 decidable per row
    #[Test]
    public function everyRowCarriesItsDocumentedFormat(): void
    {
        $data = $this->getProbe();
        foreach ($data['cases'] as $key => $case) {
            $this->assertSame($case['fmt'], $data['rows'][$key + 1]['format'] ?? '', 'Row '.($key + 1).' received the wrong format');
        }
    }

    # Every body comes back as the source it was written from, with the writer entities reversed and the machine breaks turned back into the line endings they stood for
    #[Test]
    public function everyBodyBecomesItsDocumentedSource(): void
    {
        $data = $this->getProbe();
        foreach ($data['cases'] as $key => $case) {
            $this->assertSame($case['text'], $data['rows'][$key + 1]['body'] ?? '', 'Row '.($key + 1).' body, stored as '.json_encode($data['before'][$key + 1]['body'] ?? ''));
        }
    }

    # Every title comes back as plain source, decoded and carrying no markup contract of its own
    #[Test]
    public function everyTitleBecomesPlainSource(): void
    {
        $data = $this->getProbe();
        foreach ($data['cases'] as $key => $case) {
            $this->assertSame($case['want'], $data['rows'][$key + 1]['title'] ?? '', 'Row '.($key + 1).' title, stored as '.json_encode($data['before'][$key + 1]['title'] ?? ''));
        }
    }

    # Every mode of the tool answers success against a table it can finish, so the deployment sequence is not read out of an error code
    #[Test]
    public function everyModeRunsWithoutAFailure(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            $this->assertSame(0, $run['code'], $name.' failed: '.$run['out']);
        }
    }

    # A second run of either pass rewrites nothing: the ledger marks every row it finished, which is what makes an interrupted maintenance window resumable
    #[Test]
    public function bothPassesAreSafeToRunTwice(): void
    {
        $data = $this->getProbe();
        foreach ($data['twice'] as $name => $run) {
            $this->assertSame(0, $run['code'], $name.' failed on its second run: '.$run['out']);
            $this->assertStringContainsString('0 rows rewritten', $run['out'], $name.' rewrote a row it had already finished');
        }
        $this->assertTrue($data['same'], 'A second run changed a stored title or body');
    }

    # The deployment gate of the plan reads clean once both passes have run: no row waits, no row is outside the two formats, and the row count never moved
    #[Test]
    public function theGateReadsCleanAfterBothPasses(): void
    {
        $data = $this->getProbe();
        $this->assertSame(0, $data['gate']['left'], 'A row is left outside plain and markdown');
        $this->assertSame($data['gate']['want'], $data['gate']['rows'], 'The row count moved during the run');
        $this->assertTrue($data['gate']['ledger'], 'The tool left no ledger behind');
        $this->assertStringContainsString('- clean', $data['runs']['title']['out'], 'The title pass did not report a clean gate');
        $this->assertStringContainsString('not clean yet', $data['runs']['convert']['out'], 'The convert pass claimed a gate the title pass had not passed yet');
    }

    # Reclassifying a converted table is refused, because a field whose entities are reversed twice cannot be reconstructed
    #[Test]
    public function classifyRefusesToRunOverAFinishedLedger(): void
    {
        $data = $this->getProbe();
        $this->assertSame(1, $data['guard']['code'], 'classify accepted a ledger whose rows a pass had already rewritten');
        $this->assertStringContainsString('already rewritten', $data['guard']['out'], 'classify refused for another reason');
        $this->assertStringNotContainsString('rule', $data['guard']['out'], 'classify scanned the table before it refused');
    }

    # An interrupted pass leaves finished rows behind and stamps nothing, so the refusal counts the rows and never reads a completion stamp
    #[Test]
    public function classifyRefusesTheLedgerOfAnInterruptedPass(): void
    {
        $data = $this->getProbe();
        $this->assertSame(1, $data['crash']['code'], 'classify accepted a ledger an interrupted pass had left behind');
        $this->assertStringContainsString('records 1 rows', $data['crash']['out'], 'classify did not count the rows the interrupted pass had finished');
        $this->assertTrue($data['left'], 'The refused run still changed a stored title or body');
    }

    # The probe drops its disposable schema whatever the cases found
    #[Test]
    public function theProbeCleansUpAfterItself(): void
    {
        $this->assertTrue($this->getProbe()['clean'], 'The probe left its schema on the server');
    }
}
