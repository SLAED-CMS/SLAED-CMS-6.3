<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Steps 1 and 9 of docs/PRIVAT-2026.md: the private-message table trades one status column for four independent
 * states and then gains the format column of the content contract, in all three update channels at once.
 * tests/Support/privat_probe.php builds each documented pre-migration shape in a disposable schema, runs the shipped
 * statements of one channel against it through the same splitter an installation runs them with, and reports what
 * the table and the mailboxes look like afterwards. States A and B still carry the legacy status column, C is
 * already converted, D carries the renamed column with the BOOLEAN definition a bare rename leaves behind, and E is
 * the table stage 1 shipped: every state column and every key in place and only format missing, which is the
 * installation the format section is written for. The site database is never touched.
 */
final class PrivatMigrationTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/privat_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
        $this->assertNotEmpty($data['runs'], 'The probe ran no scenario');
        return self::$probe = $data;
    }

    # Every channel and every state ends on the table setup/sql/table.sql declares, column order and index set included
    #[Test]
    public function everyChannelEndsOnTheFreshSchema(): void
    {
        $data = $this->getProbe();
        $this->assertCount(10, $data['runs'], 'Two channels times five states is what the plan asks for');
        foreach ($data['runs'] as $name => $run) {
            $this->assertTrue($run['def'], $name." did not converge on the fresh schema:\n".$run['text']."\n\nfresh:\n".$data['fresh']);
        }
    }

    # A second run of the same channel is a run that changes nothing, which is what makes an interrupted upgrade restartable
    #[Test]
    public function everyChannelIsSafeToRunTwice(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            $this->assertTrue($run['stable'], $name.' changed the table or the messages on its second run');
        }
    }

    # An already converted table is left alone: no column is rewritten and no row is touched
    #[Test]
    public function aConvertedTableIsNotRewritten(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            $this->assertTrue($run['quiet'], $name.' rebuilt a table that already carried the final definition');
            $this->assertTrue($run['kept'], $name.' changed the state of a message it had nothing to convert');
        }
    }

    # Every state except the already converted one really differs from the final definition before the run, or it would prove nothing by converging
    #[Test]
    public function theWrongDefinitionIsTheOneUnderTest(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            $this->assertTrue($run['moved'], $name.' started from the definition it is supposed to produce');
        }
    }

    # No message is lost and no mailbox changes size, except the outbox, which regains the rows the old predicate hid
    #[Test]
    public function everyMailboxSurvivesTheConversion(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            if ($run['parity'] === []) continue;
            $this->assertTrue($run['parity']['rows'], $name.' lost or gained a row');
            $this->assertTrue($run['parity']['inbox'], $name.' changed an inbox');
            $this->assertTrue($run['parity']['saved'], $name.' changed a saved mailbox');
            $this->assertTrue($run['parity']['subset'], $name.' dropped a message out of an outbox');
            $this->assertTrue($run['parity']['more'], $name.' did not restore the sent messages the old outbox predicate hid');
        }
    }

    # A saved message was opened before it was saved, so it converts to read, and nothing keeps a value outside the state model
    #[Test]
    public function aSavedMessageConvertsToRead(): void
    {
        foreach ($this->getProbe()['runs'] as $name => $run) {
            if ($run['parity'] === []) continue;
            $this->assertTrue($run['parity']['read'], $name.' left a saved message unread');
            $this->assertTrue($run['parity']['flat'], $name.' left a state column outside the values the model allows');
        }
    }

    # The probe drops its disposable schema whatever the scenarios found
    #[Test]
    public function theProbeCleansUpAfterItself(): void
    {
        $this->assertTrue($this->getProbe()['clean'], 'The probe left its schema on the server');
    }
}
