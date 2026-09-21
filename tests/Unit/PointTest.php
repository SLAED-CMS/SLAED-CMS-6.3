<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S01 of docs/node: the Point class is the one writer of the points journal and of the fast balance, and
 * docs/node/points.md is its contract. tests/Support/point_probe.php boots the real core in an isolated CLI process
 * and drives the class against a disposable schema carrying the shipped account table and the shipped journal, so
 * the DDL of setup/sql/table.sql is executed by the same run. Every persistent result is read by a connection of
 * its own. The site database is never touched.
 */
final class PointTest extends TestCase
{
    private const ACTIONS = [
        'publish' => ['10', '86400', '10'], 'comment' => ['5', '86400', '30'], 'view' => ['0', '86400', '50'],
        'download' => ['3', '86400', '20'], 'visit' => ['0', '86400', '20'], 'poll' => ['3', '86400', '10'],
        'order' => ['10', '0', '0'], 'favorite' => ['0', '86400', '20'], 'message' => ['0', '86400', '20'],
        'recommend' => ['0', '86400', '5'], 'register' => ['0', '0', '0'], 'login' => ['0', '86400', '1'],
        'report' => ['3', '86400', '5'], 'moderate' => ['0', '86400', '100'], 'adjust' => ['0', '0', '0'],
    ];

    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/point_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_point_probe';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
        $this->assertNotEmpty($data['runs'], 'The probe ran no scenario');
        return self::$probe = $data;
    }

    # One scenario group of the report
    private function getRun(string $name): array
    {
        return $this->getProbe()['runs'][$name];
    }

    # The shipped scope is the starter table of the contract, value for value and as canonical strings, and the probe drops its schema again
    #[Test]
    public function theShippedScopeIsTheStarterTable(): void
    {
        $conf = require dirname(__DIR__, 2).'/config/points.php';
        $this->assertSame(['points'], array_keys($conf), 'config/points.php does not return exactly one scope');
        $this->assertSame(['active', 'actions'], array_keys($conf['points']), 'The points scope does not carry exactly active and actions');
        $this->assertSame('1', $conf['points']['active']);
        $want = array_map(static fn(array $one): array => ['points' => $one[0], 'period' => $one[1], 'limit' => $one[2]], self::ACTIONS);
        $this->assertSame($want, $conf['points']['actions'], 'The shipped rules are not the fifteen starter rules of points.md');
        $this->assertArrayNotHasKey('rate', $conf['points']['actions'], 'A rating reward came back into the points scope');
        $this->assertTrue($this->getProbe()['clean'], 'The probe left its schema on the server');
    }

    # The class stands alone: no rating, no node, no request, no template and no configuration writer are reachable from it
    #[Test]
    public function theClassImportsNothingItDoesNotOwn(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/classes/point.php');
        foreach (['Rating', 'Node', 'getVar', '$_', 'Template', 'setConfigFile', 'global ', 'time()', 'date('] as $one) {
            $this->assertStringNotContainsString($one, $code, 'core/classes/point.php reaches for '.$one);
        }
        $ref = new \ReflectionClass($this->getPointClass());
        $this->assertTrue($ref->isFinal(), 'The class is not final');
        $open = array_map(static fn(\ReflectionMethod $one): string => $one->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        sort($open);
        $this->assertSame(['__construct', 'addEvent', 'getEventId'], $open, 'The public API is not exactly the constructor, addEvent and getEventId');
        $this->assertSame('bool', (string)$ref->getMethod('addEvent')->getReturnType());
        $this->assertSame('int|false', (string)$ref->getMethod('getEventId')->getReturnType());
    }

    # Load the class file in this process for reflection only; it needs nothing but the guard constant the bootstrap already defines
    private function getPointClass(): string
    {
        if (!class_exists('Point', false)) require_once dirname(__DIR__, 2).'/core/classes/point.php';
        return 'Point';
    }

    # The configuration is accepted whole or not at all: every bound passes, every neighbour of a bound, every native type, sign, space and foreign or missing key fails
    #[Test]
    public function theConfigurationIsAcceptedWholeOrNotAtAll(): void
    {
        $run = $this->getRun('config');
        $good = ['shipped', 'off', 'sumtop', 'perlow', 'pertop', 'limtop', 'free'];
        $this->assertCount(35, $run['valid'], 'A configuration case went missing');
        foreach ($run['valid'] as $name => $done) {
            $this->assertSame(in_array($name, $good, true), $done, 'The configuration case '.$name.' was judged wrongly');
        }
    }

    # A broken scope switches the whole class off, compensation and correction included, writes nothing and reports itself to the site log
    #[Test]
    public function aBrokenScopeSwitchesTheClassOff(): void
    {
        $run = $this->getRun('config');
        $this->assertSame([false, false, false], $run['dead'], 'A class with a broken scope still answered an event, an origin or a correction');
        $this->assertSame([], $run['rows'], 'A class with a broken scope wrote a journal row');
        $this->assertTrue($run['log'], 'The broken scope was not reported to the site log');
        $this->assertSame([false, false, false], $run['void'], 'An empty scope is a subsystem closed on purpose: it answers nothing and writes no log line');
    }

    # Every refused key and data value answers false, and the whole list of them costs no statement
    #[Test]
    public function everyRefusedInputAnswersFalseWithoutSql(): void
    {
        $run = $this->getRun('input');
        $this->assertCount(40, $run['deny'], 'An input case went missing');
        $this->assertSame([], array_keys(array_filter($run['deny'])), 'A refused input was accepted');
        $this->assertSame(0, $run['cost'], 'Refusing an input cost a statement');
    }

    # A zero reward and a switched off system succeed without a statement, even for an account nobody checked, and still refuse a malformed key
    #[Test]
    public function anEmptyRewardCostsNoStatement(): void
    {
        $run = $this->getRun('input');
        $this->assertSame(['zero' => true, 'off' => true, 'ghost' => true, 'badzero' => false, 'badoff' => false], $run['free']);
        $this->assertSame(0, $run['freecost'], 'An empty reward reached the database');
        $this->assertSame([], $run['rows'], 'An empty reward or a refused input wrote a journal row');
    }

    # An award writes one row with the configured amount and moves the balance by it; its key is unique for good across repeats and free across scopes, sources and recipients
    #[Test]
    public function anAwardIsWrittenOnceForGood(): void
    {
        $run = $this->getRun('award');
        $this->assertTrue($run['first']);
        $this->assertFalse($run['open'], 'An award without an owner left its transaction open');
        $this->assertTrue($run['again'], 'A repeated event is an empty success, not a refusal');
        $this->assertSame([[[2, 0, 'publish', 'node.news', 1050, 'node:1050', 10, null, '']], 10], $run['once'], 'The repeat wrote a second row or the row is not the event');
        $this->assertSame([true, true, true, false], [$run['next'], $run['scope'], $run['mate'], $run['ghost']], 'A missing account was rewarded, or a distinct key was refused');
        $this->assertSame([10, 10, 10, 10], $run['sums']);
        $this->assertSame([30, 10], $run['bals'], 'The balances are not the sum of the journal');
        $this->assertSame([true, [3, 4, 'comment', 'forum.topic', 7, 'post:9', 5, null], 255], $run['full'], 'The data of an event did not reach its row');
    }

    # The limit is per recipient and action over a sliding period of the database clock, shared by every scope, and a compensated origin still counts against it
    #[Test]
    public function theLimitSlidesOnTheDatabaseClock(): void
    {
        $run = $this->getRun('limit');
        $this->assertSame([true, true, true, true], $run['runs'], 'A reached limit is a success that skips the reward, not an error');
        $this->assertSame([[5, 5], 10], $run['held'], 'The limit of two let a third award through, or scopes were counted apart');
        $this->assertSame([true, 5], $run['mate'], 'The limit of one recipient held back another');
        $this->assertSame([true, 20], $run['other'], 'The limit of one action held back another');
        $this->assertTrue($run['undo']);
        $this->assertSame([true, 15], $run['still'], 'A compensation freed a place under the limit');
        $this->assertSame([true, 20], $run['slid'], 'An award that left the period still counted');
        $this->assertSame([true, true, 20], $run['late'], 'An award passed a limit that was full again');
        $this->assertSame([true, true, 30], $run['loose'], 'A rule without a limit was bounded');
    }

    # What would overflow the balance column is refused, logged, and leaves both the journal and the aggregate as they were
    #[Test]
    public function anOverflowIsRefusedAndLogged(): void
    {
        $run = $this->getRun('over');
        $this->assertSame([false, false, false], [$run['award'], $run['open'], $run['adjust']]);
        $this->assertSame([[], 4294967290], $run['held'], 'A refused overflow changed the journal or the balance');
        $this->assertSame([true, true], $run['fits'], 'An amount that exactly fills the column was refused');
        $this->assertTrue($run['log'], 'The overflow was not reported to the site log');
    }

    # A correction works with rewards switched off, records the amount that was really applied, never goes below zero and writes nothing when nothing could be taken
    #[Test]
    public function aCorrectionRecordsWhatWasApplied(): void
    {
        $run = $this->getRun('adjust');
        $this->assertSame([true, true], [$run['gift'], $run['again']]);
        $this->assertSame([[[2, 1, 'adjust', 'account', 0, 'adm:1', 50, null, 'a gift']], 50], $run['row'], 'The correction is not attributed, or its repeat wrote a second row');
        $this->assertSame([true, [50, -50], 0], $run['take'], 'The journal does not carry the amount that was really taken');
        $this->assertSame([true, [50, -50], 0], $run['empty'], 'A correction that could take nothing wrote a row');
        $this->assertSame([[true, 1000000], [true, 0]], [$run['top'], $run['bottom']], 'A correction at its bound was refused');
        $this->assertFalse($run['ghost'], 'An account that does not exist was corrected');
    }

    # The origin lookup tells an id, a confirmed nothing and a refusal apart, needs the transaction of its owner, and works with rewards switched off
    #[Test]
    public function theOriginLookupTellsItsThreeAnswersApart(): void
    {
        $run = $this->getRun('origin');
        $this->assertFalse($run['bare'], 'The lookup answered without the transaction of an owner');
        $this->assertIsInt($run['found']);
        $this->assertGreaterThan(0, $run['found']);
        $this->assertSame([0, 0, 0, 0], [$run['none'], $run['scope'], $run['mate'], $run['ghost']], 'A key without an origin did not answer a confirmed nothing');
        $deny = [$run['adjust'], $run['rate'], $run['badscope'], $run['badsource'], $run['baduid']];
        $this->assertSame(array_fill(0, 5, false), $deny, 'A wrong key did not answer false');
        $this->assertTrue($run['open'], 'The lookup closed the transaction of its owner');
        $this->assertTrue($run['undo'], 'The compensation of the found origin was refused with rewards switched off');
        $this->assertFalse($run['spent'], 'The reserved source of a compensation was accepted as the key of an origin');
    }

    # A compensation exists once per origin, only for a positive award of the same recipient, action and scope, takes what the balance still holds, and is recorded even at zero
    #[Test]
    public function aCompensationIsSingleAndBounded(): void
    {
        $run = $this->getRun('reverse');
        $this->assertSame(17, $run['start']);
        $deny = [$run['action'], $run['scope'], $run['mate'], $run['adjust'], $run['fine'], $run['none'], $run['chain']];
        $this->assertSame(array_fill(0, 7, false), $deny, 'A foreign origin, a correction, a compensation or a missing row was compensated');
        $this->assertSame([4, 17], $run['held'], 'A refused compensation changed the journal or the balance');
        $this->assertSame([[true, 7], [true, 7]], [$run['whole'], $run['twice']], 'A compensation ran twice or not at all');
        $this->assertSame([true, 0], $run['part'], 'A compensation took more than the balance holds');
        $this->assertSame([true, 0], $run['zero']);
        $this->assertSame([[1, -10, 'node:1'], [1, -7, 'node:2'], [1, 0, 'node:3']], $run['rows'], 'The applied amounts or the zero compensation are missing');
        $this->assertSame([true, 50], $run['refill'], 'A zero compensation was repeated once the balance was refilled');
        $this->assertSame([true, 50, 8], $run['reaward'], 'A compensated event was awarded a second time');
        $this->assertFalse($run['open']);
    }

    # Inside the transaction of an owner the award is a savepoint: it follows the rollback and the commit of that owner and ends neither
    #[Test]
    public function aJoinedAwardFollowsItsOwner(): void
    {
        $run = $this->getRun('join');
        $this->assertSame([true, true], [$run['done'], $run['open']], 'The class ended a transaction it did not open');
        $this->assertSame([[], 0], $run['unseen'], 'The award was committed behind the back of its owner');
        $this->assertSame([[], 0, ''], $run['dropped'], 'The rollback of the owner did not take the award with it');
        $this->assertSame([true, true, true], $run['redo'], 'A repeat inside one transaction was not an empty success');
        $this->assertSame([[10], 10, 'kept'], $run['kept'], 'The commit of the owner did not keep the award and the main action together');
    }

    # A recoverable failure after the journal row is written takes that row back, answers false, is logged with its source, and leaves the main action of the owner to commit
    #[Test]
    public function aRecoverableFailureKeepsTheMainAction(): void
    {
        $run = $this->getRun('fail');
        $this->assertSame([false, true, true], $run['joined'], 'A local failure was not a false that left the owner its transaction');
        $this->assertSame([[], 0, 'main'], $run['after'], 'The main action was lost, or half of the points unit survived');
        $this->assertSame([false, false, [], 0], $run['own'], 'A failure without an owner left a transaction open or a row behind');
        $this->assertSame([true, [10], 10], $run['healed'], 'The refused event could not be awarded afterwards');
        $this->assertTrue($run['log'], 'The failure was not logged with the source of its event');
    }

    # The account row is the first lock: while another session holds it nothing is written and the origin row is never reached, and the unit works again once it is free
    #[Test]
    public function theAccountRowIsTheFirstLock(): void
    {
        $run = $this->getRun('lock');
        $this->assertSame([false, false], $run['own']);
        $this->assertSame([false, true], $run['joined'], 'A lock wait that timed out cost the owner its transaction');
        $this->assertSame([false, true], $run['origin'], 'The origin was answered although the account row was held elsewhere');
        $this->assertTrue($run['order'], 'The origin row was locked before the account row');
        $this->assertSame([true, true, true], $run['freed']);
        $this->assertSame([[10, 10], 20, 'main'], $run['after']);
    }

    # What cannot be proven throws: a deadlock, an unknown commit, and a connection that died under an owner whose main action was already written
    #[Test]
    public function anUnprovenOutcomeThrows(): void
    {
        $run = $this->getRun('lost');
        $this->assertSame(['RuntimeException', false], $run['hardown'], 'A deadlock answered false, or left the own transaction open');
        $this->assertSame(['RuntimeException', true], $run['hardjoin'], 'A deadlock under an owner answered false, or the class touched the outer transaction');
        $this->assertSame(['RuntimeException', false], $run['commit'], 'An unknown commit was reported as an outcome');
        $this->assertSame([[], 0], $run['held']);
        $this->assertSame('RuntimeException', $run['killed'], 'A lost outer transaction answered false, so its owner would report success');
        $this->assertSame('RuntimeException', $run['origin'], 'The origin lookup answered false on a lost outer transaction');
        $this->assertSame([[], 0, ''], $run['after'], 'The main action of the lost transaction persisted, or points were written without it');
        $this->assertSame([true, [10], 10], $run['alive'], 'The same event could not be repeated whole on a new connection');
    }

    # An owner whose snapshot is older than the award of a rival: with snapshot isolation the server drops the whole transaction, the class throws and the owner repeats everything
    # Without it the unique key stops the repeat the snapshot hid, the repeat is the empty success it always is, and the owner keeps and commits its main action
    # The limit is read from the snapshot of the owner by decision of 2026-09-19: parallel requests of one account may pass it by their number, and no neighbour ever deadlocks
    #[Test]
    public function aStaleSnapshotNeverAwardsTwice(): void
    {
        $run = $this->getRun('stale');
        foreach ($run as $mode => $one) {
            if ($one['skip']) continue;
            $this->assertTrue($one['rival']);
            $this->assertSame([true, true, 5, 'again'], $one['again'], 'The whole repeat of the owner awarded the rival event again under mode '.$mode);
        }
        $lost = $run['on'];
        if (!$lost['skip']) {
            $this->assertSame(['RuntimeException', false, null, false], [$lost['same'], $lost['alive'], $lost['other'], $lost['end']], 'A dropped transaction answered a result');
            $this->assertSame([[5], 5, ''], $lost['held'], 'The main action of the dropped transaction persisted, or the rival event was awarded twice');
        }
        $kept = $run['off'];
        $this->assertFalse($kept['skip']);
        $this->assertSame([true, true, true, true], [$kept['same'], $kept['alive'], $kept['other'], $kept['end']], 'A repeat the snapshot hid was not an empty success');
        $this->assertTrue($kept['bound'], 'The limit is counted from the snapshot of the owner by decision; a locking count would deadlock neighbouring accounts');
        $this->assertSame([[5, 5, 5], 15, 'main'], $kept['held'], 'The hidden repeat wrote a second row, or cost the owner its main action');
    }

    # Real processes at one moment: one source leaves one row, and four sources cannot pass a limit of two between them
    #[Test]
    public function concurrentWritersAreSerializedByTheAccount(): void
    {
        $run = $this->getRun('race');
        $this->assertSame([true, true, true, true], $run['same']);
        $this->assertSame([[5], 5], $run['once'], 'Concurrent writers of one source wrote more than one row');
        $this->assertSame([true, true, true, true], $run['many']);
        $this->assertSame([[5, 5], 10], $run['bound'], 'Concurrent writers passed the limit between them');
    }

    # The shared reset of the account admin screen runs in batches of 500, splits a balance above a million into parts, and a repeat after a failed commit debits nothing twice
    # The function is lifted out of modules/account/admin/index.php by name and driven with the core globals pointed at the disposable schema
    #[Test]
    public function theSharedResetResumesWithoutASecondDebit(): void
    {
        $run = $this->getRun('reset');
        $this->assertFalse($run['refused'][0], 'A refused commit was reported as a finished reset');
        $this->assertSame(16, strlen($run['refused'][1]['id']), 'The operation id did not stay in the admin session');
        $this->assertGreaterThan(1500, $run['refused'][1]['cur'], 'The first run never left its first batch of 500 accounts');
        $this->assertSame(0, $run['first']['odd'], 'A reset row is not an attributed negative correction');
        $this->assertSame($run['total'], $run['first']['held'] - $run['first']['sum'], 'The journal and the balances disagree after the refused commit');
        $this->assertSame([3, []], $run['intact'], 'The account of the refused commit lost points or gained a row');
        $this->assertSame([false, $run['refused'][1]['id']], [$run['muted'][0], $run['muted'][1]['id']], 'The repeat did not keep the operation id');
        $this->assertSame([true, 0, [['1', -3]]], $run['kept'], 'The commit without an answer moved the cursor or was not kept');
        $this->assertSame([true, null], $run['last'], 'The finished reset left its state in the session');
        $this->assertSame([0, [['1', -3], ['2', -4]]], $run['again'], 'The repeat debited the first part twice or missed the new points');
        $this->assertSame([['1', -1000000], ['2', -1000000], ['3', -500000]], $run['large'], 'A balance above a million was not debited in parts');
        $this->assertSame([], $run['none'], 'An empty account got a reset row');
        $this->assertSame([[$run['refused'][1]['id']], 0, 0], [$run['final']['ids'], $run['final']['odd'], $run['final']['held']]);
        $this->assertSame(-$run['total'] - 4, $run['final']['sum'], 'The journal does not carry exactly what the accounts held');
        $this->assertSame([[true, null], $run['final']['rows']], [$run['idle'], $run['after']], 'A reset with nothing to debit wrote rows or kept a state');
    }
}
