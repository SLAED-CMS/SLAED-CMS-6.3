<?php

namespace Tests\Unit;

use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once BASE_DIR.'/core/classes/pdo.php';

/**
 * A statement double, because Database returns PDOStatement|false and PDOStatement itself cannot be
 * instantiated. Nothing is ever read from it — the class under test reads rows through the database
 * wrapper, which this double answers directly.
 */
final class MailQueueStatement extends PDOStatement
{
    public function __construct()
    {
    }
}

/**
 * A recording database: every statement the Mail class issues is captured with its parameters, and
 * the rows a read returns are handed in by the test. No connection is opened, so the queue contract
 * is asserted on the SQL that would run rather than on a database that has to exist.
 */
final class MailQueueDatabase extends \Database
{
    public array $sql = [];
    public array $rows = [];
    public bool $answer = true;
    public int $moved = 1;

    public function __construct()
    {
    }

    public function getSqlQuery(string $query = '', array $params = []): PDOStatement|false
    {
        $this->sql[] = ['query' => $query, 'pars' => $params];
        return $this->answer ? new MailQueueStatement() : false;
    }

    public function getSqlRow(PDOStatement|int $query_id = 0): array|false
    {
        $row = array_shift($this->rows);
        return is_array($row) ? $row : false;
    }

    public function getSqlRows(PDOStatement|int $query_id = 0): array|false
    {
        $rows = array_shift($this->rows);
        return is_array($rows) ? $rows : false;
    }

    public function getSqlAffected(): int|false
    {
        return $this->moved;
    }

    public function getSqlLastId(): string|false
    {
        return '7';
    }

    # Return the statements issued so far whose text contains the given fragment
    public function getMatch(string $part): array
    {
        return array_values(array_filter($this->sql, static fn(array $one): bool => str_contains($one['query'], $part)));
    }
}

/**
 * Stage 2 of docs/MAIL-2026.md: addQueue() stores instead of sending, and the drain claims, records
 * and prunes. What is asserted here is the contract that does not need a database — the statement
 * each step issues, the bounds every stored value is held to, and that no public method delivers.
 * The behaviour that only a real engine can answer — an atomic claim, a backoff that actually
 * expires, a prune that removes exactly the rows past the window — is driven against the live
 * database by tests/Support/mail_probe.php and asserted in MailDrainTest.
 */
final class MailQueueTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/logger.php';
        require_once BASE_DIR.'/core/classes/mail.php';
        if (!defined('PREFIX_DB')) define('PREFIX_DB', 'test');
        if (!defined('_IP')) define('_IP', 'IP');
        if (!defined('_BROWSER')) define('_BROWSER', 'Browser');
        if (!defined('_HASH')) define('_HASH', 'Hash');
    }

    # Build a Mail service over a recording database and a given mail config section
    private function getMail(MailQueueDatabase $db, array $conf = []): \Mail
    {
        return new \Mail($db, ['sitename' => 'SLAED CMS', 'mail' => $conf]);
    }

    # Call one of the private methods, which are private by design so nothing outside the class can compose or deliver a message
    private function getCall(\Mail $mailer, string $meth, array $args): mixed
    {
        return (new ReflectionMethod(\Mail::class, $meth))->invokeArgs($mailer, $args);
    }

    # One accepted message is one stored row and nothing else: no transport is entered, so the request that queued it never waits for a delivery
    #[Test]
    public function anAcceptedMessageIsStoredAndNotDelivered(): void
    {
        $db = new MailQueueDatabase();
        $mailer = $this->getMail($db);
        $this->assertTrue($mailer->addQueue([
            'kind' => 'account',
            'email' => 'user@slaed.net',
            'title' => 'Password reset',
            'body' => '<p>Hello</p>',
            'sender' => 'info@slaed.net',
            'prio' => 1,
        ]));
        $this->assertCount(1, $db->sql);
        $one = $db->sql[0];
        $this->assertStringStartsWith('INSERT INTO test_mail', $one['query']);
        $this->assertSame('account', $one['pars']['kind']);
        $this->assertSame('info@slaed.net', $one['pars']['from']);
        $this->assertSame('user@slaed.net', $one['pars']['mail']);
        $this->assertSame('Password reset', $one['pars']['subj']);
        $this->assertSame('<p>Hello</p>', $one['pars']['body']);
        $this->assertSame(0, $one['pars']['ref']);
        $this->assertSame(1, $one['pars']['prio']);
    }

    # The raw subject is stored, never a MIME-encoded one, because an encoded subject inflates by a third and would be truncated by the column that holds it
    #[Test]
    public function theStoredSubjectIsTheRawOne(): void
    {
        $db = new MailQueueDatabase();
        $this->assertTrue($this->getMail($db)->addQueue(['kind' => 'news', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net', 'title' => 'Привет']));
        $this->assertSame('Привет', $db->sql[0]['pars']['subj']);
    }

    # A caller that asks for the client block gets it inside the stored body, because only the originating request knows the visitor and the drain would supply the scheduler
    #[Test]
    public function theClientBlockIsStoredWithTheBody(): void
    {
        $db = new MailQueueDatabase();
        $this->assertTrue($this->getMail($db)->addQueue(['kind' => 'security', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net', 'body' => 'text', 'client' => true]));
        $this->assertStringStartsWith('text', $db->sql[0]['pars']['body']);
        $this->assertStringContainsString('IP: 127.0.0.1', $db->sql[0]['pars']['body']);
    }

    # A message pointing at a shared body stores none of its own, which is what keeps one mailing one body rather than one body per recipient
    #[Test]
    public function aSharedBodyIsNotStoredPerRow(): void
    {
        $db = new MailQueueDatabase();
        $mesg = ['kind' => 'newsletter', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net', 'body' => 'ignored', 'ref' => 12, 'client' => true];
        $this->assertTrue($this->getMail($db)->addQueue($mesg));
        $this->assertSame(12, $db->sql[0]['pars']['ref']);
        $this->assertSame('', $db->sql[0]['pars']['body']);
    }

    # A subject longer than the column is refused where the caller can still be told, instead of failing the write under strict mode and taking the message with it
    #[Test]
    public function aSubjectLongerThanTheColumnIsRefused(): void
    {
        $db = new MailQueueDatabase();
        $mailer = $this->getMail($db);
        $mesg = ['kind' => 'order', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net', 'body' => 'text'];
        $this->assertTrue($mailer->addQueue($mesg + ['title' => str_repeat('a', 255)]));
        $this->assertFalse($mailer->addQueue($mesg + ['title' => str_repeat('a', 256)]));
        $this->assertSame('rejected subject, longer than 255 characters', $mailer->getError());
        $this->assertCount(1, $db->sql);
    }

    # The bound is counted in characters, because the column stores characters and a multibyte subject would otherwise be refused at a third of its real length
    #[Test]
    public function theSubjectBoundIsCountedInCharacters(): void
    {
        $db = new MailQueueDatabase();
        $this->assertTrue($this->getMail($db)->addQueue([
            'kind' => 'order',
            'email' => 'user@slaed.net',
            'sender' => 'info@slaed.net',
            'title' => str_repeat("\u{041F}", 255),
        ]));
        $this->assertCount(1, $db->sql);
    }

    # An address longer than the column that stores it is refused for the same reason, on both ends of the message
    #[Test]
    public function anAddressLongerThanItsColumnIsRefused(): void
    {
        $db = new MailQueueDatabase();
        $mailer = $this->getMail($db);
        $long = str_repeat('a', 90).'@slaed.net';
        $this->assertFalse($mailer->addQueue(['kind' => 'order', 'email' => 'user@slaed.net', 'sender' => $long]));
        $this->assertSame('rejected sender address', $mailer->getError());
        $this->assertFalse($mailer->addQueue(['kind' => 'order', 'email' => str_repeat('a', 250).'@slaed.net', 'sender' => 'info@slaed.net']));
        $this->assertSame('rejected recipient address', $mailer->getError());
        $this->assertSame([], $db->sql);
    }

    # A queue that cannot be reached refuses the message with a stated reason rather than pretending it was accepted
    #[Test]
    public function aMessageIsRefusedWhenTheQueueCannotBeReached(): void
    {
        $mailer = new \Mail(null, ['sitename' => 'SLAED CMS', 'mail' => []]);
        $this->assertFalse($mailer->addQueue(['kind' => 'account', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net']));
        $this->assertSame('the queue is unreachable, there is no database connection', $mailer->getError());
    }

    # A write that fails is reported as a refusal, so a caller is never told a message was accepted into a queue it never reached
    #[Test]
    public function aFailedWriteIsReportedAsARefusal(): void
    {
        $db = new MailQueueDatabase();
        $db->answer = false;
        $mailer = $this->getMail($db);
        $this->assertFalse($mailer->addQueue(['kind' => 'account', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net']));
        $this->assertSame('the message could not be stored in the queue', $mailer->getError());
    }

    # Nothing outside the class may deliver: the queue is the only way out, so every transport and the composition around it stays private
    #[Test]
    public function noPublicMethodDelivers(): void
    {
        $want = ['__construct', '__destruct', 'addQueue', 'getError', 'getBatch', 'setResult', 'updateQueue', 'deleteQueue'];
        $have = [];
        foreach ((new \ReflectionClass(\Mail::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $meth) {
            $have[] = $meth->getName();
        }
        sort($want);
        sort($have);
        $this->assertSame($want, $have);
    }

    # The claim is one conditional UPDATE stamping a fresh identifier, and only the rows carrying it are read back, which is what stops two runs from taking the same row
    # Its predicate is exactly the leading columns of the claim index and nothing else, because a marker column in it costs the ordered range read the index exists for
    #[Test]
    public function theClaimIsOneConditionalUpdateFollowedByItsOwnRead(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [[['id' => 5, 'kind' => 'account', 'sender' => '', 'email' => 'user@slaed.net', 'title' => '', 'body' => '', 'ref' => 0, 'prio' => 1, 'tries' => 0]]];
        $rows = $this->getMail($db)->getBatch(25);
        $this->assertCount(1, $rows);
        $claim = $db->getMatch('SET locked = NOW()');
        $this->assertCount(1, $claim);
        $this->assertStringContainsString('WHERE hold = 0 AND status = 0 AND ntime <= NOW() ORDER BY prio ASC, ntime ASC, id ASC LIMIT 25', $claim[0]['query']);
        $this->assertStringNotContainsString('locked IS NULL', $claim[0]['query']);
        $this->assertStringNotContainsString('lockid =', explode('WHERE', $claim[0]['query'])[1]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $claim[0]['pars']['lock']);
        $read = $db->getMatch('SELECT id, kind, sender');
        $this->assertCount(1, $read);
        $this->assertStringContainsString('WHERE lockid = :lock', $read[0]['query']);
        $this->assertSame($claim[0]['pars']['lock'], $read[0]['pars']['lock']);
    }

    # A claimed row is moved behind the lock window, which is what keeps it out of every other claim and hands it back once a run that died stops working on it
    #[Test]
    public function aClaimMovesTheRowBehindTheLockWindow(): void
    {
        $db = new MailQueueDatabase();
        $mailer = new \Mail($db, ['sitename' => 'SLAED CMS', 'mail' => [], 'scheduler' => ['jobs' => ['maildrain' => ['lock_timeout' => '900']]]]);
        $mailer->getBatch(25);
        $claim = $db->getMatch('SET locked = NOW()')[0];
        $this->assertStringContainsString('ntime = DATE_ADD(NOW(), INTERVAL :wait SECOND)', $claim['query']);
        $this->assertSame(900, $claim['pars']['wait']);
    }

    # What a run did not send goes back to the queue due immediately, rather than sitting out the window its claim moved it behind
    #[Test]
    public function aReleasedRowIsDueAgainAtOnce(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [[['id' => 5, 'kind' => 'account', 'sender' => '', 'email' => 'u@slaed.net', 'title' => '', 'body' => '', 'ref' => 0, 'prio' => 1, 'tries' => 0]]];
        $mailer = $this->getMail($db, ['rate' => '0', 'batch' => '1']);
        $mailer->getBatch(1);
        (new \ReflectionMethod(\Mail::class, 'deleteLock'))->invoke($mailer);
        $free = $db->getMatch('SET locked = NULL, lockid = \'\', ntime = NOW() WHERE lockid = :lock');
        $this->assertCount(1, $free);
        $this->assertStringContainsString('AND status = 0', $free[0]['query']);
    }

    # Every claim carries an identifier of its own, so a second run reads its own rows and never the ones another run is holding
    #[Test]
    public function everyClaimUsesAFreshIdentifier(): void
    {
        $db = new MailQueueDatabase();
        $mailer = $this->getMail($db);
        $mailer->getBatch(5);
        $mailer->getBatch(5);
        $lock = array_column($db->getMatch('SET locked = NOW()'), 'pars');
        $this->assertNotSame($lock[0]['lock'], $lock[1]['lock']);
    }

    # A claim releases what a dead run left behind first, bounded by the lock window of the drain job rather than by a guess
    #[Test]
    public function aStaleClaimIsReleasedBeforeANewOneIsTaken(): void
    {
        $db = new MailQueueDatabase();
        $mailer = new \Mail($db, ['sitename' => 'SLAED CMS', 'mail' => [], 'scheduler' => ['jobs' => ['maildrain' => ['lock_timeout' => '900']]]]);
        $mailer->getBatch(5);
        $free = $db->getMatch('SET locked = NULL, lockid = \'\' WHERE status = 0');
        $this->assertCount(1, $free);
        $this->assertStringContainsString('locked < DATE_SUB(NOW(), INTERVAL :wait SECOND)', $free[0]['query']);
        $this->assertSame(900, $free[0]['pars']['wait']);
    }

    # An accepted row is finished: it is marked, unlocked and carries no failure text from an earlier attempt
    #[Test]
    public function anAcceptedRowIsMarkedAndUnlocked(): void
    {
        $db = new MailQueueDatabase();
        $this->assertTrue($this->getMail($db)->setResult(5, true));
        $this->assertCount(1, $db->sql);
        $this->assertStringContainsString('SET status = 1, tries = tries + 1, locked = NULL, lockid = \'\', phase = \'\', code = \'\', error = \'\'', $db->sql[0]['query']);
        $this->assertSame(5, $db->sql[0]['pars']['id']);
    }

    # A refused row waits behind a backoff that grows with the attempt, and stays claimable rather than being held by the run that failed it
    #[Test]
    public function aRefusedRowWaitsBehindAGrowingBackoff(): void
    {
        $wait = [];
        foreach ([0, 1, 2] as $done) {
            $db = new MailQueueDatabase();
            $db->rows = [['tries' => $done]];
            $mailer = $this->getMail($db, ['tries' => '5', 'backoff' => '300']);
            $this->assertTrue($mailer->setResult(5, false, 'the relay refused at rcpt'));
            $one = $db->getMatch('SET status = :stat')[0];
            $this->assertSame(0, $one['pars']['stat']);
            $this->assertSame('the relay refused at rcpt', $one['pars']['err']);
            $this->assertStringContainsString('locked = NULL, lockid = \'\'', $one['query']);
            $wait[] = $one['pars']['wait'];
        }
        $this->assertSame([300, 600, 1200], $wait);
    }

    # The attempt cap turns the row into a failure the admin view keeps, instead of retrying a permanently broken address forever
    #[Test]
    public function theAttemptCapFailsTheRow(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [['tries' => 4]];
        $this->assertTrue($this->getMail($db, ['tries' => '5'])->setResult(5, false, 'refused'));
        $this->assertSame(2, $db->getMatch('SET status = :stat')[0]['pars']['stat']);
    }

    # A hard failure skips the retries: a message whose shared body is gone would be refused the same way on every attempt
    #[Test]
    public function aHardFailureIsNotRetried(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [['tries' => 0]];
        $this->assertTrue($this->getMail($db, ['tries' => '5'])->setResult(5, false, 'the shared body is gone', true));
        $this->assertSame(2, $db->getMatch('SET status = :stat')[0]['pars']['stat']);
    }

    # Recording a failure must never fail itself, so every value is cut to its column before the write rather than by it
    #[Test]
    public function aRecordedFailureCannotOverflowItsColumns(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [['tries' => 0]];
        $mailer = $this->getMail($db);
        (new \ReflectionProperty(\Mail::class, 'phase'))->setValue($mailer, str_repeat('p', 40));
        (new \ReflectionProperty(\Mail::class, 'code'))->setValue($mailer, str_repeat('5', 40));
        $mailer->setResult(5, false, str_repeat("\u{00FC}", 400));
        $pars = $db->getMatch('SET status = :stat')[0]['pars'];
        $this->assertSame(10, mb_strlen($pars['phase']));
        $this->assertSame(20, mb_strlen($pars['code']));
        $this->assertSame(str_repeat("\u{00FC}", 255), $pars['err']);
    }

    # A multi-line relay answer is stored on one line, because a stored response carrying line breaks would reshape every log and list that reads it back
    #[Test]
    public function aMultiLineResponseIsStoredOnOneLine(): void
    {
        $db = new MailQueueDatabase();
        $db->rows = [['tries' => 0]];
        $this->getMail($db)->setResult(5, false, "550 5.7.1 refused\r\n550 policy");
        $this->assertSame('550 5.7.1 refused 550 policy', $db->getMatch('SET status = :stat')[0]['pars']['err']);
    }

    # Retention is applied per kind: a mailing is one row per account and keeps far less history than the transactional mail that answers what was sent
    #[Test]
    public function retentionAppliesTheShorterWindowToBulkRowsOnly(): void
    {
        $db = new MailQueueDatabase();
        $db->moved = 3;
        $this->assertSame(6, $this->getMail($db, ['keep' => '30', 'keepbulk' => '3'])->deleteQueue());
        $this->assertCount(2, $db->sql);
        $this->assertStringContainsString('WHERE status = 1 AND kind = \'newsletter\'', $db->sql[0]['query']);
        $this->assertSame(3, $db->sql[0]['pars']['days']);
        $this->assertStringContainsString('WHERE status = 1 AND kind <> \'newsletter\'', $db->sql[1]['query']);
        $this->assertSame(30, $db->sql[1]['pars']['days']);
    }

    # A failed row is never pruned, because it is exactly what an administrator opens the queue view to read
    #[Test]
    public function pruningNeverRemovesAFailedRow(): void
    {
        $db = new MailQueueDatabase();
        $this->getMail($db)->deleteQueue();
        foreach ($db->sql as $one) {
            $this->assertStringContainsString('status = 1', $one['query']);
        }
    }

    # The rate window caps what may leave inside one minute and opens a fresh window once the last one has expired
    #[Test]
    public function theRateWindowCapsOneMinuteAndThenReopens(): void
    {
        $mailer = $this->getMail(new MailQueueDatabase(), ['rate' => '2']);
        $rsec = new \ReflectionProperty(\Mail::class, 'rsec');
        $rnum = new \ReflectionProperty(\Mail::class, 'rnum');
        $rsec->setValue($mailer, time());
        $rnum->setValue($mailer, 0);
        $this->assertTrue($this->getCall($mailer, 'checkRate', []));
        $rnum->setValue($mailer, 2);
        $this->assertFalse($this->getCall($mailer, 'checkRate', []));
        $rsec->setValue($mailer, time() - 61);
        $this->assertTrue($this->getCall($mailer, 'checkRate', []));
        $this->assertSame(0, $rnum->getValue($mailer));
    }

    # A rate of zero is the documented way to send without a cap, and must not read as a cap of nothing
    #[Test]
    public function aRateOfZeroIsNoLimit(): void
    {
        $mailer = $this->getMail(new MailQueueDatabase(), ['rate' => '0']);
        (new \ReflectionProperty(\Mail::class, 'rsec'))->setValue($mailer, time());
        (new \ReflectionProperty(\Mail::class, 'rnum'))->setValue($mailer, 1000);
        $this->assertTrue($this->getCall($mailer, 'checkRate', []));
    }
}
