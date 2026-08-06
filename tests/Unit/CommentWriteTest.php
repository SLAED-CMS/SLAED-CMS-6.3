<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 1, batch 3 of docs/COMMENTS-REDESIGN-2026.md: the frontend write path moved into the Comment class, and the
 * stage promises that each of the eight modules still stores the same row, increments its own target counter and
 * awards its own points slot. Both author kinds run through tests/Support/contract_probe.php, which boots the real
 * core in an isolated CLI process and drives the class against the live rows of this installation inside a
 * transaction it always rolls back. filter_input() cannot be driven from CLI, so the request half of the submit —
 * the token, the HTMX response and the moderator paths of edit and status — belongs to the browser checks.
 */
final class CommentWriteTest extends TestCase
{
    private static array $probe = [];

    # Run one write probe per author kind and memoize its report for every scenario in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        return self::$probe[$mode] = $data;
    }

    # Return the report of the registered run, or skip when this installation has no account the login check accepts
    private function getUserProbe(): array
    {
        $data = $this->getProbe('commentwrite');
        if (!$data['user']) $this->markTestSkipped('No account with a stored password on this installation');
        $this->assertSame($data['uid'][0], $data['uid'][1], 'The probe could not sign in as the account it picked');
        return $data;
    }

    # Neither run may leave a row behind: both drive real writes and both roll them back
    #[Test]
    public function everyProbeRunLeavesTheTableUntouched(): void
    {
        $this->assertTrue($this->getProbe('commentwrite')['clean']);
        $this->assertTrue($this->getProbe('commentguest')['clean']);
    }

    # A registered comment is stored against the resolved target, under the account, and published when the target is open
    #[Test]
    public function everyModuleStoresTheRowItResolved(): void
    {
        $data = $this->getUserProbe();
        $this->assertCount(8, $data['rows']);
        $seen = 0;
        foreach ($data['rows'] as $mod => $one) {
            if (intval($one['target']) === 0) continue;
            $this->assertSame('', $one['error'], 'Module "'.$mod.'" refused the write');
            $this->assertSame($one['want'], $one['stored'], 'Module "'.$mod.'" stored another row');
            $seen++;
        }
        if ($seen === 0) $this->markTestSkipped('No module of this installation carries a writable target');
        $this->assertGreaterThan(6, $seen, 'Only '.$seen.' of the eight modules carry a writable target here');
    }

    # Each module increments its own target counter by one and awards its own points slot
    #[Test]
    public function everyModuleMovesItsOwnCounterAndSlot(): void
    {
        $data = $this->getUserProbe();
        foreach ($data['rows'] as $mod => $one) {
            $this->assertSame($one['wantdelta'], $one['delta'], 'Module "'.$mod.'" moved another counter or another points slot');
        }
    }

    # An anonymous comment lands pending on a stand that premoderates guests, and a pending row moves neither counter nor points
    #[Test]
    public function anonymousAddLandsPendingAndMovesNothing(): void
    {
        $data = $this->getProbe('commentguest');
        $this->assertCount(8, $data['rows']);
        $seen = 0;
        foreach ($data['rows'] as $mod => $one) {
            if (($one['error'] ?? null) === null) continue;
            $this->assertSame('', $one['error'], 'Module "'.$mod.'" refused the anonymous write');
            $this->assertSame($one['want'], $one['stored'], 'Module "'.$mod.'" stored another row');
            $this->assertSame([0, 0], $one['delta'], 'A pending comment moved the counter or the points of "'.$mod.'"');
            $seen++;
        }
        if ($seen === 0) $this->markTestSkipped('No module of this installation carries a target an anonymous visitor may write to');
    }

    # Every refusal of the submit path is still the refusal the class answers, for both author kinds
    #[Test]
    public function writeRulesRefuseWhatTheSubmitPathRefused(): void
    {
        foreach (['commentwrite', 'commentguest'] as $mode) {
            $data = $this->getProbe($mode);
            if (!$data['refuse']) $this->markTestSkipped('No writable news target on this installation');
            foreach ($data['refuse'] as $case => $pair) {
                if ($case === 'flood') continue;
                $this->assertSame($pair[1], $pair[0], 'Case "'.$case.'" of '.$mode.' answered another refusal');
            }
        }
    }

    # A second submit from one address inside the send window is refused; the marker is written from the clock the rule measures with, so a stand whose database clock drifts still observes it
    #[Test]
    public function secondSubmitInsideTheWindowIsRefused(): void
    {
        $data = $this->getUserProbe();
        $this->assertSame($data['refuse']['flood'][1], $data['refuse']['flood'][0]);
    }

    # The author of a fresh comment edits it, and the row carries exactly the body the class answered
    #[Test]
    public function theAuthorEditsInsideTheWindow(): void
    {
        $data = $this->getUserProbe();
        if (!$data['edit']) $this->markTestSkipped('No writable news target on this installation');
        [$allow, $saved, $mod, $body, $row] = $data['edit']['fresh'];
        $this->assertTrue($allow);
        $this->assertTrue($saved);
        $this->assertSame('news', $mod);
        $this->assertSame($body, $row);
        $this->assertSame('probe body edited', $row);
    }

    # The edit window closes: the same author is refused once it has passed, and so is a comment written while the two clocks disagree
    #[Test]
    public function theEditWindowClosesForTheAuthor(): void
    {
        $data = $this->getUserProbe();
        if (!$data['edit']) $this->markTestSkipped('No writable news target on this installation');
        $this->assertFalse($data['edit']['stale']);
        $this->assertSame(abs($data['skew']) <= 5, $data['edit']['skewed'], 'The edit window and the clock skew of this installation disagree');
    }

    # Nobody else may edit a comment or move its state: a visitor is refused on both, and the stored body stays untouched
    #[Test]
    public function aVisitorEditsAndModeratesNothing(): void
    {
        $data = $this->getProbe('commentguest');
        if (!$data['edit']) $this->markTestSkipped('No writable news target on this installation');
        [$allow, $saved, , $body, $row] = $data['edit']['fresh'];
        $this->assertFalse($allow);
        $this->assertFalse($saved);
        $this->assertSame($body, $row);
        $this->assertFalse($data['edit']['skewed']);
        $this->assertFalse($data['edit']['stale']);
        $this->assertFalse($data['edit']['status']);
        $this->assertFalse($this->getUserProbe()['edit']['status'], 'A signed-in visitor who moderates nothing changed a comment state');
    }
}
