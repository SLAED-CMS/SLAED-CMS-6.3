<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 4 of docs/BACKUP-2026.md: the lock protocol and the request boundary of the scheduler.
 * tests/Support/scheduler_probe.php boots the real core with LOGS_DIR in scratch, declares its own
 * jobs in memory so no site configuration is written, and starts a real second process wherever the
 * protocol needs a contended lock - an operating system lock cannot be contested inside one process.
 * The route scenario drives the live site over HTTP; the admin handlers are asserted at source level,
 * which is stated in each test that does so rather than presented as a request test.
 */
final class SchedulerLockTest extends TestCase
{
    private static array $probe = [];

    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/scheduler_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_sched_probe_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # Read one admin handler as source, which is how the POST contract of the four admin entry points is asserted
    private function getSource(string $name): string
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/admin/modules/scheduler.php');
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, 'admin handler '.$name.'() is gone');
        $to = strpos($code, "\n}\n", $from);
        return substr($code, $from, $to === false ? null : $to - $from);
    }

    # An idle job holds no lock and is simply subject to its schedule
    #[Test]
    public function anIdleJobIsNeitherLockedNorRepaired(): void
    {
        $data = $this->getProbe('table')['idle'];
        $this->assertFalse($data['locked']);
        $this->assertFalse($data['due'], 'A job that just had its state reset must wait for its next slot');
    }

    # While another process owns the lock the job is live: not due, not startable, and its state is left alone
    #[Test]
    public function aLiveJobIsNotDueAndNotStartable(): void
    {
        $data = $this->getProbe('table')['live'];
        $this->assertTrue($data['held'], 'The second process never took the lock, so this scenario proves nothing');
        $this->assertTrue($data['locked']);
        $this->assertFalse($data['due']);
        $this->assertSame('locked', $data['run'], 'A second run started while the first still held the lock');
        $this->assertSame(1, $data['state']['running'], 'The refused run rewrote the state of the live one');
    }

    # A crashed run leaves no lock behind, so the job becomes due again on its own instead of needing manual file surgery
    #[Test]
    public function aCrashedRunBecomesDueAgainWithoutIntervention(): void
    {
        $data = $this->getProbe('table')['crashed'];
        $this->assertFalse($data['locked'], 'A crashed process still holds its lock');
        $this->assertTrue($data['due'], 'A crashed job stayed permanently not-due');
        $this->assertSame(1, $data['read']['running'], 'Reading the state repaired it, which a read path must never do');
    }

    # Reconciliation defines the state exactly, keeps last_run so the job resumes at its next slot, and changes nothing on a second pass
    #[Test]
    public function reconciliationIsExactAndIdempotent(): void
    {
        $data = $this->getProbe('table')['crashed'];
        $this->assertSame(0, $data['after']['running']);
        $this->assertSame(0, $data['after']['started']);
        $this->assertSame('crashed', $data['after']['status']);
        $this->assertSame(3, $data['after']['fails'], 'The failure counter did not advance');
        $this->assertSame(1700000000, $data['after']['lastrun'], 'last_run was rewritten, which would make a crashed job retry in a loop');
        $this->assertNotSame('', $data['after']['error']);
        $this->assertSame($data['after'], $data['twice'], 'A second protocol pass changed the reconciled state');
    }

    # No read path may write: looking at a crashed job from every read entry point leaves the state byte for byte as it was
    #[Test]
    public function readPathsNeverRepairAnything(): void
    {
        $data = $this->getProbe('readonly');
        $this->assertSame($data['before'], $data['after'], 'A read path mutated job state');
        $this->assertSame(1, $data['after']['running']);
    }

    # Two instances of one job cannot overlap, while an unrelated job stays startable at the same moment
    #[Test]
    public function oneJobIsSerializedAndOthersAreNot(): void
    {
        $data = $this->getProbe('concurrent');
        $this->assertTrue($data['held'], 'The second process never took the lock, so this scenario proves nothing');
        $this->assertSame('locked', $data['same']);
        $this->assertTrue($data['samehandle'], 'The lock was handed out twice for the same job');
        $this->assertTrue($data['other'], 'An unrelated job was blocked, so the scheduler is globally serialized');
        $this->assertTrue($data['free'], 'The lock was not released when the owning process ended');
    }

    # The next run that takes the lock reconciles the crash and then runs normally
    #[Test]
    public function theNextRunReconcilesAndProceeds(): void
    {
        $data = $this->getProbe('recover');
        $this->assertTrue($data['due']);
        $this->assertNotSame('locked', $data['status'], 'A crashed job could not be started again');
        $this->assertSame(0, $data['state']['running'], 'The run left the job marked as running');
        $this->assertFalse($data['locked'], 'The run did not release its lock');
    }

    # A scheduled run asks the schedule again while holding the lock
    # The verdict that selected it was formed before the lock existed, and another process may have acted on it since
    #[Test]
    public function aCoveredSlotIsNotRunTwice(): void
    {
        $data = $this->getProbe('slot');
        $this->assertTrue($data['duebefore'], 'The job was not due to begin with, so this scenario proves nothing');
        $this->assertSame('success', $data['first']);
        $this->assertTrue($data['ranonce']);
        $this->assertNotSame('success', $data['second'], 'The same slot was executed twice');
        $this->assertFalse($data['slotafter'], 'The slot still reads as due after it was covered, so the barrier would let a second process through');
        $this->assertTrue($data['slotstale'], 'The barrier answers no regardless of state, which would block every scheduled run');
    }

    # A run whose state cannot be stored is refused rather than executed: otherwise the work would happen, report success and still look due to the next pass
    #[Test]
    public function aRunThatCannotRecordItsStateDoesNotStart(): void
    {
        $data = $this->getProbe('unwritable');
        $this->assertTrue($data['due'], 'The job was not due, so this scenario proves nothing');
        $this->assertFalse($data['writable'], 'The state file stayed writable, so nothing was actually tested');
        $this->assertSame('failed', $data['status'], 'A run started even though its state could not be recorded');
        $this->assertStringContainsString('state cannot be written', $data['message']);
        $this->assertSame('idle', $data['state']['status'], 'The refused run still rewrote the stored status');
    }

    # A manual start is an operator decision and is deliberately not subject to the schedule
    #[Test]
    public function aManualStartIgnoresTheSchedule(): void
    {
        $data = $this->getProbe('slot');
        $this->assertSame('success', $data['manual']);
        $this->assertSame('success', $data['manualagain'], 'A manual run was refused because the slot was already covered');
    }

    # Unlock follows the same protocol: it refuses while a process owns the job and never touches its state
    #[Test]
    public function unlockRefusesWhileTheJobIsLive(): void
    {
        $data = $this->getProbe('unlock');
        $this->assertTrue($data['held'], 'The second process never took the lock, so this scenario proves nothing');
        $this->assertTrue($data['refused'], 'Unlock was granted while another process owned the job');
        $this->assertSame(1, $data['kept']['running'], 'The refused unlock still rewrote the state');
    }

    # Once the job is free, unlock reconciles it and leaves the lock file in place, because deleting it would break the lock for anyone holding it
    #[Test]
    public function unlockReconcilesAndKeepsTheLockFile(): void
    {
        $data = $this->getProbe('unlock');
        $this->assertTrue($data['granted']);
        $this->assertSame('crashed', $data['after']['status']);
        $this->assertSame(1700000000, $data['after']['lastrun']);
        $this->assertTrue($data['lockfile'], 'The lock file was deleted');
    }

    # A repair that could not be stored must be reported as such: the job stays running, and unlock has to say so instead of showing its success message
    #[Test]
    public function unlockReportsARepairItCouldNotStore(): void
    {
        $data = $this->getProbe('unlock')['readonly'];
        $this->assertTrue($data['granted'], 'The lock was not free, so this scenario proves nothing');
        $this->assertFalse($data['repaired'], 'A reconciliation nobody could write reported success');
        $this->assertSame(1, $data['state']['running'], 'The job was reported repaired while its state still says running');
    }

    # The admin action has to act on that answer rather than announce success unconditionally
    #[Test]
    public function theUnlockHandlerActsOnTheRepairResult(): void
    {
        $code = $this->getSource('unlock');
        $this->assertStringContainsString('updateSchedulerCrash($name, $state) !== null', $code, 'unlock() ignores whether the repair was stored');
        $this->assertStringContainsString('$warn = !$done', $code, 'unlock() reports success regardless of the repair result');
    }

    # Each trigger carries its own credential: they are not interchangeable and nothing else opens the endpoint
    #[Test]
    public function eachTriggerAcceptsOnlyItsOwnCredential(): void
    {
        $data = $this->getProbe('access');
        $this->assertTrue($data['cronok']);
        $this->assertTrue($data['pseudook']);
        $this->assertFalse($data['cronbad']);
        $this->assertFalse($data['pseudobad']);
        $this->assertFalse($data['pseudocron'], 'The static cron token opened the pseudo path');
        $this->assertFalse($data['cronsite'], 'A session token opened the cron path');
        $this->assertFalse($data['cronempty']);
        $this->assertFalse($data['empty']);
    }

    # manual is not a credential and is no longer a trigger of the direct endpoint
    #[Test]
    public function manualAndUnknownTriggersAreRefused(): void
    {
        $data = $this->getProbe('access');
        $this->assertFalse($data['manual'], 'manual still opens the direct endpoint');
        $this->assertFalse($data['unknown']);
    }

    # The pseudo trigger hands its credential to a body, so it never travels in an address
    #[Test]
    public function theTriggerCredentialIsNotInTheUrl(): void
    {
        $data = $this->getProbe('trigger');
        $this->assertSame('index.php?go=3&op=scheduler', $data['url']);
        $this->assertNotSame('', $data['token'], 'The trigger produced no credential');
        $this->assertFalse($data['query'], 'The credential is still part of the address');
    }

    # With a live cron there is simply nothing to trigger, and asking must return the same shape rather than fail on every page render
    #[Test]
    public function aLiveCronYieldsNoTriggerInsteadOfAnError(): void
    {
        $data = $this->getProbe('trigger');
        $this->assertIsArray($data['withcron'], 'Asking for a trigger while cron is alive did not return the contract shape: '.json_encode($data['withcron']));
        $this->assertSame([], $data['withcron'], 'A site with a live cron was still handed a pseudo trigger');
    }

    # Against the running site: no method, trigger or credential other than the documented pair gets in
    #[Test]
    public function theLiveEndpointRefusesEveryWrongRequest(): void
    {
        $data = $this->getProbe('route');
        foreach (['getquery', 'postnotoken', 'postmanual', 'postnotrigger', 'postwrongcron', 'postwrongpseudo'] as $case) {
            $this->assertStringContainsString('"denied"', $data[$case], $case.' was not refused by the live endpoint');
        }
    }

    # A valid pseudo credential works only as a POST from the session it was issued to
    #[Test]
    public function aValidCredentialWorksOnlyAsAPostOfItsOwnSession(): void
    {
        $data = $this->getProbe('route');
        if (empty($data['token'])) $this->markTestSkipped('The site had no due job, so it issued no pseudo trigger to replay');
        $this->assertStringNotContainsString('"denied"', $data['session'], 'A valid POST from the issuing session was refused');
        $this->assertStringContainsString('"denied"', $data['nosession'], 'The credential worked without its session');
        $this->assertStringContainsString('"denied"', $data['getvalid'], 'A valid credential still worked over GET');
    }

    # All four admin mutations require POST and validate a scheduler-scoped token read from the body (asserted at source level)
    #[Test]
    public function everyAdminMutationRequiresAScopedPostToken(): void
    {
        foreach (['save', 'run', 'unlock', 'delete'] as $name) {
            $this->assertStringContainsString("checkAdminPost('scheduler')", $this->getSource($name), $name.'() does not go through the shared POST guard');
        }
        $core = (string)file_get_contents(dirname(__DIR__, 2).'/core/security.php');
        $from = strpos($core, 'function checkAdminPost(');
        $this->assertNotFalse($from, 'The shared admin POST guard is gone');
        $guard = substr($core, $from, (int)strpos($core, "\n}\n", $from) - $from);
        $this->assertStringContainsString("!== 'POST'", $guard, 'The helper does not reject other request methods');
        $this->assertStringContainsString("getVar('post', 'token', 'text'), \$scope", $guard, 'The helper does not read a scoped token from the body');
    }

    # None of the four handlers may read its payload from anywhere but the body, or a GET could still carry it
    #[Test]
    public function noAdminMutationReadsItsPayloadFromTheQueryString(): void
    {
        foreach (['save', 'run', 'unlock', 'delete'] as $name) {
            $this->assertStringNotContainsString("getVar('req'", $this->getSource($name), $name.'() still accepts request-scope input');
            $this->assertStringNotContainsString("getVar('get'", $this->getSource($name), $name.'() still accepts query input');
        }
    }

    # The crashed state needs a label of its own, in every locale, or the state D10 defines stays invisible in the list
    #[Test]
    public function theCrashedLabelExistsInEveryLocale(): void
    {
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
            $file = dirname(__DIR__, 2).'/admin/lang/'.$lang.'.php';
            $this->assertStringContainsString("define('_SCHEDULER_CRASH'", (string)file_get_contents($file), $lang.'.php has no crashed label');
        }
    }

    # The list derives the displayed state from the lock and the JSON together, so a crashed run cannot read as idle and a live one cannot read as crashed
    #[Test]
    public function theListDerivesItsStateFromBothInputs(): void
    {
        $code = $this->getSource('scheduler');
        $this->assertStringContainsString('checkSchedulerLock($name)', $code, 'The list does not consult the OS lock');
        $this->assertStringContainsString('_SCHEDULER_CRASH', $code, 'The list has no crashed branch');
        $this->assertMatchesRegularExpression('#\$held => _SCHEDULER_RUNNING#', $code, 'A held lock must read as running whatever the JSON says');
        $this->assertMatchesRegularExpression('#!empty\(\$state\[.running.\]\) => _SCHEDULER_CRASH#', $code, 'A free lock with running state must read as crashed');
        $this->assertStringNotContainsString('setSchedulerState', $code, 'The list writes state, which a read path must never do');
    }

    # An over-budget run is recognizable from live elapsed time rather than from the duration of the previous finished run
    #[Test]
    public function theTipShowsLiveElapsedTimeForALockedJob(): void
    {
        $code = $this->getSource('scheduler');
        $this->assertMatchesRegularExpression('#\$held && \$start > 0\) \? \(time\(\) - \$start\)#', $code, 'A running job still shows the duration of the previous run');
        $this->assertStringContainsString('_SCHEDULER_RUNTIME', $code, 'The elapsed value lost its label');
    }

    # The row actions are rendered through the existing dial POST contract rather than as links carrying a token
    #[Test]
    public function theRowActionsUseTheDialPostContract(): void
    {
        $core = (string)file_get_contents(dirname(__DIR__, 2).'/core/helpers.php');
        $from = strpos($core, 'function getTplPostAction(');
        $this->assertNotFalse($from, 'The shared POST action builder is gone');
        $code = substr($core, $from, (int)strpos($core, "\n}\n", $from) - $from);
        $this->assertStringContainsString('form_id', $code);
        $this->assertStringContainsString('hidden', $code);
        $this->assertStringContainsString('getSiteToken(', $code, 'The action builder does not scope its token');
        $list = $this->getSource('scheduler');
        $this->assertStringContainsString('getTplPostAction(', $list, 'The scheduler no longer uses the shared builder');
        $this->assertStringNotContainsString('op=run&job=', $list, 'The run action is still a link');
        $this->assertStringNotContainsString('op=unlock&job=', $list, 'The unlock action is still a link');
        $this->assertStringNotContainsString('op=delete&job=', $list, 'The delete action is still a link');
    }
}
