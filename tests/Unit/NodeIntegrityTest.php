<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S19.3 of docs/node: the integrity fixes that only a real request reaches. The behaviour is driven by tests/Support/route_probe.php with the
 * argument intact: the disposable database and scratch configuration of the S16 integrations with the points of the data update switched on, real HTTP
 * requests of the main administrator, a user and a guest, and the rows each request leaves checked by SQL. The class side of the stage - comments of a
 * deleted material, remains of gone owners, the journal after a commit - is held by NodeServiceTest and NodeConfigTest.
 */
final class NodeIntegrityTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its intact mode and memoize the run; the only error the run may log is the refusal of the trigger that breaks one block save
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_intact';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' intact 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The routes wrote PHP errors');
            $this->assertCount(1, $data['logs']['error_sql.log'], 'The routes wrote SQL errors besides the refusal of the probe trigger');
            $this->assertStringContainsString('probe refuses the block', $data['logs']['error_sql.log'][0]);
            self::$probe = $data['runs']['intact'];
        }
        return self::$probe;
    }

    # A manual correction of the balance is keyed by the form that carries it, so sending the same form twice applies it once
    #[Test]
    public function aPointCorrectionAppliesOncePerForm(): void
    {
        $run = $this->getRun()['adjust'];
        $this->assertTrue($run['key'], 'The account form carries no key of its correction');
        $this->assertSame([303, 303], $run['codes'], 'A valid correction did not save');
        $this->assertSame([$run['was'][0] + 5, $run['was'][1] + 1], $run['now'], 'The repeated form applied the correction twice');
    }

    # A note the points journal would refuse - too long or with markup - refuses the whole form before the profile is written, and the form keeps its key
    #[Test]
    public function aBadNoteKeepsTheProfileAndTheBalance(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 200], $run['note']['codes'], 'A refused note did not show the form again');
        $this->assertTrue($run['note']['occ'], 'The profile was written although the correction was refused');
        $this->assertSame($run['adjust']['now'], $run['note']['points'], 'A refused note moved the balance');
        $this->assertTrue($run['note']['kept'], 'The form shown again lost the key of its correction');
    }

    # A block save runs in one transaction: a statement that fails after the parameter was written takes the parameter back, and a clean save writes all of it
    #[Test]
    public function aFailedBlockSaveTakesItsParameterBack(): void
    {
        $run = $this->getRun()['block'];
        $this->assertSame([true, 303, true, 'Node home block'], $run['boom'], 'The parameter of a failed save stayed');
        $this->assertSame([303, 3, 'Fine'], $run['fine'], 'A clean save was not written');
    }

    # The main administrator annuls a vote of a disabled material, of a type whose rating is switched off and of a disabled type; a repeat is an empty success
    #[Test]
    public function theMainAdministratorAnnulsEveryExistingVote(): void
    {
        $run = $this->getRun();
        $this->assertSame([[200, 200, 200], 3, [8, 2, 2, 2], [4, 1, 1, 1]], $run['votes'], 'The votes of the run were not stored');
        $this->assertSame([303, true, [4, 1, 2, 2]], $run['annul']['material'], 'The vote of a disabled material was not annulled');
        $this->assertSame([303, true, [0, 0, 2, 2]], $run['annul']['rating'], 'The vote of a type without rating was not annulled');
        $this->assertSame([303, true, [0, 0, 1, 1]], $run['annul']['type'], 'The vote of a disabled type was not annulled');
        $this->assertSame([303, true, [0, 0, 2, 2]], $run['annul']['again'], 'A repeated annulment moved the aggregate');
    }
}
