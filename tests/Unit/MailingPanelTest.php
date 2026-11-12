<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The form of a mailing and its launch over real requests, driven by tests/Support/route_probe.php mailing: what a save stores and what a launch takes
final class MailingPanelTest extends TestCase
{
    # The raw answer of the probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static array $probe = [];

    # Run the probe once in its mailing mode and memoize the run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_mailing_route';
            $out = self::$outs[__FUNCTION__] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' mailing 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The requests wrote SQL errors');
            self::$probe = $data['runs']['mailing'];
        }
        return self::$probe;
    }

    # A save stores a draft and queues nothing, and a second save of the draft rewrites the same row
    #[Test]
    public function aSaveStoresADraftAndQueuesNothing(): void
    {
        $run = $this->getRun();
        $this->assertSame(303, $run['save']);
        $this->assertSame([['Draft one', 0, 0, 0], 0], $run['draft'], 'A save started the mailing or wrote into the queue');
        $this->assertSame([['Draft two', 0, 0, 0], 1], $run['edited'], 'The edit of a draft wrote a second campaign');
    }

    # A draft offers its launch, a launch without the token of the section starts nothing, and one with it hands the draft over
    #[Test]
    public function aLaunchHandsTheDraftOverUnderTheToken(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['fan'], 'The row of a draft carries no launch');
        $this->assertSame(0, $run['badtoken'], 'A launch without the token started the mailing');
        $this->assertSame(1, $run['launched']);
    }

    # A campaign in work, and a finished one with mail still in its queue, is neither rewritten nor launched
    #[Test]
    public function aCampaignWithWorkLeftTakesNeitherAnEditNorALaunch(): void
    {
        $run = $this->getRun();
        $this->assertSame(['Draft two', 1, 0, 0], $run['inwork'], 'A campaign in work was rewritten');
        $this->assertSame(['Draft two', 6, 5, 5], $run['busy'], 'A campaign with mail in its queue was rewritten or launched');
    }

    # A finished campaign with an empty queue returns to a draft with its counters at zero on a save, starts at once on the save that launches, and is launched again from its row
    #[Test]
    public function aFinishedCampaignIsReopenedAndLaunchedAgain(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['again'], 'The row of a finished campaign carries no launch');
        $this->assertSame(['Third', 0, 0, 0], $run['reopened']);
        $this->assertSame(['Fourth', 1, 0, 0], $run['savelaunch']);
        $this->assertSame(1, $run['relaunch'][1], 'A finished campaign was not launched again');
    }

    # The form of a finished campaign edits that row, and the form of its copy writes a new one
    #[Test]
    public function theCopyOfACampaignIsANewRow(): void
    {
        $run = $this->getRun();
        $this->assertSame(1, preg_match('/^[1-9]\d*$/', $run['forms'][0]), 'The form of a finished campaign does not edit its row');
        $this->assertSame('0', $run['forms'][1], 'The copy of a campaign would rewrite its source');
    }
}
