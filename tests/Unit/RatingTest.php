<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S02 of docs/node: the Rating class is the one writer of the vote history and of the aggregate of every rated
 * target, docs/node/ratings.md is its contract, and the write-guard protocol of the page cache it depends on is the one of
 * docs/node/11-security-performance.md. tests/Support/rating_probe.php boots the real core in an isolated CLI process
 * and drives the class through trusted test adapters against a disposable schema built from the shipped DDL, so the
 * three tables of setup/sql/table.sql are executed by the same run. The cache directory, the generation counter and
 * the logs live in scratch, every persistent result is read by a connection of its own, and concurrency is made of
 * real processes. The site database is only read, by the renders of the page-cache scenario.
 */
final class RatingTest extends TestCase
{
    private const NONE = [
        'ok' => false, 'code' => 'unavailable', 'vote' => 0, 'score' => 0, 'ratings' => 0,
        'average' => null, 'wait' => 0, 'duplicate' => false, 'canvote' => false,
    ];

    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/rating_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_rating_probe';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
        if (!empty($data['error'])) $this->markTestSkipped('Probe: '.$data['error']);
        $this->assertNotEmpty($data['runs'], 'The probe ran no scenario');
        return self::$probe = $data;
    }

    # One scenario group of the report
    private function getRun(string $name): array
    {
        return $this->getProbe()['runs'][$name];
    }

    # Load the class file in this process for reflection only; it needs nothing but the guard constant the bootstrap already defines
    private function getRatingClass(): string
    {
        if (!class_exists('Rating', false)) require_once dirname(__DIR__, 2).'/core/classes/rating.php';
        return 'Rating';
    }

    # The class stands alone: no points, no node, no request, no template, no configuration writer and no clock of PHP are reachable from it
    #[Test]
    public function theClassImportsNothingItDoesNotOwn(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/classes/rating.php');
        foreach (['Point', 'NodeQuery', 'NodeService', 'getVar', '$_', 'Template', 'setConfigFile', 'global ', 'time()', 'date(', 'getIp()'] as $one) {
            $this->assertStringNotContainsString($one, $code, 'core/classes/rating.php reaches for '.$one);
        }
        $ref = new \ReflectionClass($this->getRatingClass());
        $this->assertTrue($ref->isFinal(), 'The class is not final');
        $open = array_map(static fn(\ReflectionMethod $one): string => $one->getName(), $ref->getMethods(\ReflectionMethod::IS_PUBLIC));
        sort($open);
        $this->assertSame(['__construct', 'addRating', 'deleteRating', 'getRating', 'getRatingList'], $open, 'The public API is not exactly the one of ratings.md');
        $pars = array_map(static fn(\ReflectionParameter $one): string => $one->getType().' '.$one->getName(), $ref->getConstructor()->getParameters());
        $this->assertSame(['Database db', 'array conf', 'array actor', 'Closure read', 'Closure write'], $pars);
        foreach (['addRating', 'deleteRating', 'getRating', 'getRatingList'] as $name) $this->assertSame('array', (string)$ref->getMethod($name)->getReturnType());
        # The core loads Point for every request since it was connected, so its presence says nothing about this class; the source scan above and an untouched balance do
        $this->assertSame(0, $this->getProbe()['point'][1], 'A run of the class moved a balance');
        $this->assertTrue($this->getProbe()['clean'], 'The probe left its schema on the server');
    }

    # The shipped schema carries the three tables of the contract, column for column, next to the untouched table of the other consumers
    #[Test]
    public function theSchemaCarriesTheThreeTables(): void
    {
        $sql = (string)file_get_contents(dirname(__DIR__, 2).'/setup/sql/table.sql');
        foreach (['rating', 'rating_actors', 'rating_targets', 'rating_votes'] as $name) $this->assertSame(1, substr_count($sql, 'CREATE TABLE `{prefix}_'.$name.'` ('), $name);
        foreach (['UNIQUE KEY `request` (`scope`, `mid`, `actor`, `request`)', 'KEY `target` (`scope`, `mid`, `id`)', 'KEY `scope` (`scope`, `id`)',
            'CONSTRAINT `{prefix}_chk_rating_votes_value` CHECK (`value` BETWEEN 1 AND 5)', 'PRIMARY KEY (`scope`, `mid`, `actor`)', 'PRIMARY KEY (`scope`, `mid`)'] as $one) {
            $this->assertStringContainsString($one, $sql);
        }
        $this->assertSame('PDOException', $this->getRun('fail')['value'], 'The server stored a vote outside the scale');
    }

    # The forced bump and the marker: one bump per request unless forced, a marker locked while its owner lives, a sweep that spares the journal, a registered handle alone closes
    #[Test]
    public function theWriteGuardIsAJournalNoSweepTouches(): void
    {
        $run = $this->getRun('cache');
        $this->assertSame([true, 1, true, 1, true, 2], $run['bump'], 'A repeat bumped again, or the forced bump did not');
        $this->assertSame([true, []], $run['idle']);
        $this->assertSame([true, 1, true, false], $run['open'], 'An open guard did not leave one locked marker that switches the cache off');
        $this->assertSame([[false], 1, 0], $run['peek'], 'Another process touched the marker of a live writer');
        $this->assertSame([2, 0, 1], $run['sweep'], 'The sweep did not remove exactly the two cached files and spare the marker');
        $this->assertSame([true, false], $run['spared'], 'The sweep did not spare exactly the lock of the journal');
        $this->assertSame([false, false, false, 1], $run['deny'], 'A value the class never registered closed a guard');
        $this->assertSame([true, 0, false, true], $run['close']);
    }

    # A generation that cannot be read switches the cache off instead of answering zero, and a marker whose writer died is recovered by one bump and then removed
    #[Test]
    public function aDeadWriterIsRecoveredByABump(): void
    {
        $run = $this->getRun('cache');
        $this->assertSame([false, 0], $run['garbled']);
        $this->assertSame([true, true], $run['healed']);
        $this->assertSame([[true], 1], $run['died'], 'The dead writer left no marker behind');
        $this->assertSame([true, 0, 1], $run['recover'], 'The recovery did not bump once and remove the marker');
    }

    # The real route decision follows the journal, and the identity of a page remembers the generation it was built from
    #[Test]
    public function thePageCacheFollowsTheJournal(): void
    {
        $this->markTestSkipped('The route map of checkPageCache() is empty until the Node routes arrive, and this scenario needs a cacheable route');
        $run = $this->getRun('page');
        foreach (['free' => true, 'held' => false, 'after' => true] as $name => $want) {
            $this->assertIsArray($run[$name], 'The route child answered nothing');
            $this->assertSame($want, $run[$name]['cache'], 'checkPageCache() is wrong while the guard is '.$name);
            $this->assertSame([true, true, true], [$run[$name]['kept'], $run[$name]['moved'], $run[$name]['memo']]);
        }
    }

    # Through the real head and foot: nothing is stored when the generation moved or a guard stands; a stored page is served as it is, not while a marker stands, not after a bump
    #[Test]
    public function thePageIsFilledAndReadByGenerationAndMarkers(): void
    {
        $this->markTestSkipped('The route map of checkPageCache() is empty until the Node routes arrive, and this scenario needs a cacheable route');
        $want = ['moved' => ['moved', 0], 'held' => ['held', 0], 'plain' => ['plain', 1], 'again' => ['plain', 1]];
        $want += ['fresh' => ['fresh', 1], 'cached' => ['plain', 1], 'later' => ['later', 2]];
        $this->assertSame($want, $this->getRun('fill'));
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/system.php');
        $this->assertStringContainsString('return $free ??= Cache::checkWriteGuard();', $code);
        $this->assertStringContainsString('getPageHash() === getPageHash(true) && Cache::checkWriteGuard() && Cache::setBody(', $code);
    }

    # Every rule is taken whole or not at all, in the stored string form only, and a broken or missing rule blocks its own scope and no other
    #[Test]
    public function aRuleIsAcceptedWholeOrNotAtAll(): void
    {
        $run = $this->getRun('config');
        $good = ['shipped', 'off', 'nodetail', 'noguests', 'zero', 'top'];
        foreach ($run['valid'] as $name => $code) $this->assertSame(in_array($name, $good, true) ? 'ok' : 'unavailable', $code, 'rule '.$name);
        $this->assertCount(26, $run['valid']);
        $this->assertSame(['ok', 'unavailable'], $run['alone'], 'A broken forum rule did not block the forum alone');
        $this->assertSame(['ok', 'unavailable'], $run['less'], 'A missing account rule did not block the account alone');
        $this->assertSame(['ok', 'ok'], $run['name'], 'A key outside the grammar damaged the valid scopes');
        $this->assertSame('unavailable', $run['ghost'], 'A Node scope without a rule was rated');
        $this->assertSame([true, true, true], $run['log']);
    }

    # active switches the vote off and keeps the aggregate readable, detail is a place of display and never an access rule
    #[Test]
    public function activeClosesTheVoteAndDetailDoesNot(): void
    {
        $run = $this->getRun('config');
        $this->assertSame([['ok', false, 0, 0, null, false, false], ['denied', false, 0, 0, null, false, false]], $run['switch']);
        $this->assertSame([['ok', false, 0, 0, null, false, true], ['ok', true, 5, 1, '5', false, false]], $run['detail']);
    }

    # The trusted actor is closed: a wrong shape refuses every operation without a statement, and a guest without a usable address reads and never votes
    #[Test]
    public function theActorIsTrustedWholeOrNotAtAll(): void
    {
        $run = $this->getRun('actor');
        $this->assertCount(9, $run['deny']);
        foreach ($run['deny'] as $name => $codes) $this->assertSame(['denied', 'denied', 'denied', 'denied'], $codes, 'actor '.$name);
        $this->assertSame(0, $run['cost']);
        $this->assertCount(5, $run['noip']);
        foreach ($run['noip'] as $name => $pair) $this->assertSame([['ok', false, 0, 0, null, false, false], 'denied'], $pair, 'address '.$name);
        $this->assertSame(['ok', 'interval', ['g:2001:db8::1']], $run['norm'], 'Two spellings of one address were two guests');
        $this->assertTrue($run['log']);
    }

    # A wrong form costs no statement, and a target that is missing or hidden answers unavailable without its counters and leaves nothing behind
    #[Test]
    public function theInputIsClosedAndAnUnreachableTargetHidesItsCounters(): void
    {
        $run = $this->getRun('input');
        $this->assertCount(20, $run['deny']);
        foreach ($run['deny'] as $name => $code) $this->assertSame('invalid', $code, 'input '.$name);
        $this->assertSame(0, $run['cost']);
        $this->assertSame([self::NONE, self::NONE], $run['gone']);
        $this->assertSame([self::NONE, self::NONE], $run['hidden'], 'A hidden target revealed its counters');
        $this->assertSame([[], [], []], $run['rows']);
    }

    # The scale: whole sums and counts, 5 and 1 make 6 over 2 and an average of 3, a derived string of six digits, and exactly the rows of the contract behind every vote
    #[Test]
    public function theScaleKeepsWholeSumsAndADerivedAverage(): void
    {
        $run = $this->getRun('scale');
        $this->assertSame(array_replace(self::NONE, ['ok' => true, 'code' => 'ok', 'canvote' => true]), $run['empty']);
        $this->assertSame(['ok', 'code', 'vote', 'score', 'ratings', 'average', 'wait', 'duplicate', 'canvote'], array_keys($run['first']));
        $this->assertSame([true, 'ok', 5, 1, '5', 2592000, false, false], array_values(array_diff_key($run['first'], ['vote' => 0])));
        $this->assertSame([6, 2, '3'], [$run['second']['score'], $run['second']['ratings'], $run['second']['average']]);
        $this->assertSame([11, 3, '3.666667'], [$run['third']['score'], $run['third']['ratings'], $run['third']['average']]);
        $this->assertSame([true, 'ok', 0, 11, 3, '3.666667', 0, false, true], array_values($run['read']));
        $calls = [['read', 'shop', false, false], ['read', 'shop', true, true], ['write', 'shop', 5, 1]];
        $this->assertSame($calls, $run['calls'], 'The adapters were not called unlocked for a read and locked inside the transaction for a vote');
        [$sum, $targets, $actors, $votes] = $run['stored'];
        $this->assertSame([11, 3], $sum);
        $this->assertSame([['shop', 1, 0, 0]], $targets, 'A new target did not start with a zero balance');
        $this->assertSame(['u:2', 'u:3', 'u:4'], array_column($actors, 2));
        $this->assertSame([[2, 5], [3, 1], [4, 5]], array_map(static fn(array $row): array => [$row[4], $row[5]], $votes));
        foreach ($votes as $key => $row) $this->assertSame([$actors[$key][3], 0, 0, ''], [$row[7], $row[8], $row[9], $row[10]], 'last is not the moment of the vote');
        $this->assertSame([[], false], [$run['marks'], $run['open']]);
    }

    # A starting balance is carried, a vote lands on top of it and its annulment takes exactly that vote back; an aggregate nobody carried over and a full column take no vote
    #[Test]
    public function theStartingBalanceIsCarriedAndNeverInvented(): void
    {
        $run = $this->getRun('carry');
        $this->assertSame(['ok', false, 37, 10, '3.7', false, true], $run['before']);
        $this->assertSame([['ok', true, 42, 11, '3.818182', false, false], [42, 11]], $run['added']);
        $this->assertSame([['ok', true, 37, 10, '3.7', false, true], [37, 10], [['account', 3, 37, 10]]], $run['undone']);
        $this->assertSame([['ok', false, 37, 10, '3.7', false, true], ['storage', false, 0, 0, null, false, false], [37, 10], 1], $run['loose']);
        $this->assertTrue($run['log']);
        $this->assertSame(['storage', true, 1], $run['full'], 'An aggregate its column cannot hold was stored');
    }

    # An account is one actor from every address, two accounts share an address freely, and a guest stays apart from every account before and after a login
    #[Test]
    public function anActorIsAnAccountOrAGuestAddress(): void
    {
        $run = $this->getRun('who');
        $want = ['home' => 'ok', 'away' => 'interval', 'mate' => 'ok', 'guest' => 'ok', 'again' => 'interval', 'login' => 'ok', 'logout' => 'interval', 'other' => 'ok'];
        $this->assertSame($want, array_intersect_key($run, $want));
        $this->assertSame([['u:2', 2, 5], ['u:3', 3, 4], ['g:10.0.0.1', 0, 3], ['u:4', 4, 2], ['g:10.0.0.2', 0, 1]], $run['rows'], 'The address of an account reached the history');
        $this->assertSame([15, 5], $run['sum']);
        $this->assertSame(['denied', 0, ['ok', false, 15, 5, '3', false, false]], $run['shut'], 'A closed guest vote cost a statement or hid the aggregate');
        $this->assertSame('ok', $run['member']);
    }

    # The interval: measured as now minus last against the period in force, never extended by a refusal, open at zero, and closed by a last participation from the future
    #[Test]
    public function theIntervalFollowsThePeriodInForce(): void
    {
        $run = $this->getRun('period');
        $this->assertSame('ok', $run['first']);
        $this->assertSame(['interval', true, false, 5], $run['fresh']);
        $this->assertSame(['interval', true, 'ok', true, false, true], $run['inside'], 'A refusal moved last, or the wait is not the rest of the period');
        $this->assertSame([0, true, 'ok', true, false, true], $run['border'], 'now - last = period did not open the vote');
        $this->assertSame(['interval', 'ok'], $run['shorter']);
        $this->assertSame('interval', $run['longer']);
        $this->assertSame(['ok', true, 0, 'ok', true], $run['free'], 'A zero period did not take two intentions in a row');
        $this->assertSame(['storage', 'storage', 'ok', 0, false], $run['future']);
        $this->assertTrue($run['log']);
        $this->assertSame([[16, 5], 5], $run['sum']);
    }

    # The delivery key: a repeat answers the stored vote, another value is a conflict, and the key is unique per actor and target only
    #[Test]
    public function aDeliveryIsStoredOnce(): void
    {
        $run = $this->getRun('request');
        $this->assertSame(['ok', true, 4, 1, '4', false, false], $run['new']);
        $this->assertSame([['ok', true, 4, 1, '4', true, false], true, true], $run['repeat']);
        $this->assertSame([['conflict', false, 4, 1, '4', false, false], [4, 1], 1], $run['clash']);
        $this->assertSame(['ok', 'ok', 'ok', true, 4], [$run['mate'], $run['next'], $run['scope'], $run['later'], $run['rows']]);
    }

    # The own profile and a switched off target are seen with their aggregate and take no vote; a guest is nobody's owner and a material without an author is nobody's own
    #[Test]
    public function theOwnVoteIsRefusedAndTheOwnRatingIsSeen(): void
    {
        $run = $this->getRun('own');
        $this->assertSame(['ok', true, 4, 1, '4', false, false], $run['mate']);
        $this->assertSame([['ok', false, 4, 1, '4', false, false], ['denied', false, 4, 1, '4', false, false], [4, 1]], $run['self']);
        $this->assertSame(['ok', 'ok'], [$run['guest'], $run['free']]);
        $this->assertSame([['ok', false, 0, 0, null, false, false], ['denied', false, 0, 0, null, false, false], [['account', 2, 0, 0], ['shop', 1, 0, 0]]], $run['closed']);
    }

    # Only the main administrator annuls, with a plain reason; exactly the stored value leaves, last stays, a repeat changes nothing, and no switch or missing rule is in the way
    #[Test]
    public function onlyTheMainAdministratorAnnuls(): void
    {
        $run = $this->getRun('annul');
        $this->assertTrue($run['ids']);
        $this->assertSame(['denied', 'denied'], $run['deny']['user']);
        $this->assertSame(['denied', 'denied'], $run['deny']['guest']);
        $this->assertSame(['denied', ['ok' => false, 'code' => 'denied', 'rows' => [], 'next' => 0]], $run['deny']['admin'], 'An ordinary administrator reached the journal');
        $form = array_fill_keys(['zero', 'sign', 'void', 'blank', 'long', 'html', 'line'], 'invalid') + ['none' => 'unavailable'];
        $this->assertSame($form, $run['form']);
        $this->assertSame([['ok', true, 5, 1, '5', false, true], true, [5, 1], [['read', 'shop', true, true], ['write', 'shop', 5, 1]], true], $run['done']);
        $this->assertSame([true, 1, 255, 2], $run['row'], 'The annulled row does not keep its value next to the administrator, the reason and the moment');
        $this->assertSame([['ok', true, 5, 1, '5', true, true], [5, 1], 255], $run['twice'], 'A repeated annulment changed something');
        $this->assertSame('interval', $run['still'], 'An annulment reopened the interval of the voter');
        $this->assertSame(['ok', [0, 0]], $run['hidden']);
        $this->assertSame(['ok', [0, 0]], $run['norule']);
        $this->assertSame([['unavailable', false, 0, 0, null, false, false], 0], $run['gone'], 'A vote of a removed target was marked without its aggregate');
        $this->assertSame(['storage', 0, true], $run['broken']);
    }

    # The journal pages by the vote id for the main administrator alone: everything, one scope, one target, a cursor, a closed set of arguments and the stored columns
    #[Test]
    public function theJournalPagesByACursor(): void
    {
        $run = $this->getRun('annul');
        $keys = ['id', 'scope', 'mid', 'actor', 'uid', 'value', 'request', 'created', 'annulled', 'aid', 'reason'];
        $this->assertSame([true, 'ok', true, true, $keys], $run['list']['all']);
        $this->assertSame(['shop', 1, 'u:2', 2, 5, 0, 0, ''], array_values(array_diff_key($run['list']['first'], ['id' => 0, 'request' => 0, 'created' => 0])));
        $this->assertSame([[1, 1, 2], [2, 3], ['account', 'node.probe'], [2, true]], [$run['list']['scope'], $run['list']['target'], $run['list']['after'], $run['list']['page']]);
        $this->assertSame(['ok' => true, 'code' => 'ok', 'rows' => [], 'next' => 0], $run['list']['past']);
        $this->assertSame('ok', $run['list']['oldtype'], 'The history of a type that is gone cannot be read');
        $bad = ['idbare' => 'invalid', 'scope' => 'invalid', 'idsign' => 'invalid', 'after' => 'invalid', 'limzero' => 'invalid', 'limover' => 'invalid', 'limtop' => 'ok'];
        $this->assertSame($bad, $run['badlist']);
    }

    # A write inside a transaction somebody else opened is refused before the adapter, a marker or a statement, and that transaction commits untouched
    #[Test]
    public function aForeignTransactionIsRefused(): void
    {
        $run = $this->getRun('foreign');
        $this->assertSame(['storage', 'storage', [], true, []], [$run['add'], $run['delete'], $run['calls'], $run['open'], $run['marks']]);
        $this->assertSame(['main', [5, 1], 1, true], $run['kept']);
    }

    # Each of the nine statements of a vote and of an annulment fails once: storage, nothing stored, no open transaction, no marker; likewise the adapter, a real error, a deadlock
    #[Test]
    public function everyFailedStatementRollsTheWholeUnitBack(): void
    {
        $run = $this->getRun('fail');
        for ($i = 1; $i <= 9; $i++) {
            $this->assertSame(['storage', 0, 0, 0, false, 0], $run['add'][$i], 'vote statement '.$i);
            $this->assertSame(['storage', false, 5, false, 0], $run['delete'][$i], 'annulment statement '.$i);
        }
        foreach ([10, 11] as $i) {
            $this->assertSame(['ok', 5, 3, 5, false, 0], $run['add'][$i], 'A vote has more than nine statements');
            $this->assertSame(['ok', true, 0, false, 0], $run['delete'][$i], 'An annulment has more than nine statements');
        }
        $this->assertSame(['storage', 0, false, 0], $run['write'], 'A refusal of the write adapter was stored');
        $this->assertSame(['storage', 0, [0, 0], false, 0], $run['real']);
        $this->assertSame(['storage', 0, false, 0], $run['hard']);
        $this->assertSame(['ok', true, 5, 1, '5', false, false], $run['healed']);
        $this->assertTrue($run['log']);
    }

    # An unknown commit answers storage and keeps its marker, the repeat of the delivery finds out what happened, and the next reader recovers the markers of the dead writer
    #[Test]
    public function anUnknownCommitKeepsItsGuard(): void
    {
        $run = $this->getRun('commit');
        $this->assertIsArray($run['child'], 'The writer process answered nothing');
        $this->assertSame(['storage', 0, 1, false], $run['child']['back']);
        $this->assertSame(['storage', 1, 2], $run['child']['kept']);
        $this->assertSame(['ok', true, 5, 1, '5', true, false], $run['child']['again'], 'The repeat of a committed delivery was not answered as the stored vote');
        $this->assertSame([[5, 1], 1, 2, false], $run['child']['end']);
        $this->assertSame([2, [true, 0, 2], true], [$run['left'], $run['recover'], $run['log']]);
    }

    # Real processes at one moment: two intentions of one actor leave one vote, one delivery sent four times leaves one vote, and five actors lose no update
    #[Test]
    public function concurrentVotesAreSerializedByTheTarget(): void
    {
        $run = $this->getRun('race');
        $codes = array_column($run['intent'], 0);
        sort($codes);
        $this->assertSame(['interval', 'interval', 'interval', 'ok'], $codes);
        $this->assertSame([[5, 1], 1, 1], $run['once']);
        $this->assertSame(['ok', 'ok', 'ok', 'ok'], array_column($run['resend'], 0));
        $this->assertCount(1, array_filter($run['resend'], static fn(array $one): bool => $one[1] === false), 'Not exactly one delivery was the first');
        $this->assertCount(1, array_unique(array_column($run['resend'], 2)), 'The repeats answered different votes');
        $this->assertSame([[4, 1], 1], $run['same']);
        $this->assertSame(['ok', 'ok', 'ok', 'ok', 'ok'], array_column($run['crowd'], 0));
        $this->assertSame([[15, 5], 5, 1], $run['all'], 'An update of the aggregate was lost');
        $this->assertSame([0, true], $run['marks']);
    }
}
