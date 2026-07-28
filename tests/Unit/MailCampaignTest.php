<?php

namespace Tests\Unit;

use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once BASE_DIR.'/core/classes/pdo.php';

/**
 * A statement double, because Database returns PDOStatement|false and PDOStatement cannot be built.
 */
final class MailCampStatement extends PDOStatement
{
    public function __construct()
    {
    }
}

/**
 * A recording database: the statements the campaign half issues are captured with their parameters
 * and the rows a read returns are handed in by the test, so the contract is asserted on the SQL that
 * would run rather than on a database that has to exist.
 */
final class MailCampDatabase extends \Database
{
    public array $sql = [];
    public array $rows = [];

    public function __construct()
    {
    }

    public function getSqlQuery(string $query = '', array $params = []): PDOStatement|false
    {
        $this->sql[] = ['query' => $query, 'pars' => $params];
        return new MailCampStatement();
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
        return 1;
    }

    public function setSqlBegin(): bool
    {
        return false;
    }

    # Return the statements issued so far whose text contains the given fragment
    public function getMatch(string $part): array
    {
        return array_values(array_filter($this->sql, static fn(array $one): bool => str_contains($one['query'], $part)));
    }
}

/**
 * Stage 3 of docs/MAIL-2026.md, the half that needs no engine: the failure taxonomy, the address
 * normaliser the suppression registry is keyed by, the verification ladder and its refusal to fail
 * closed, and the statements the campaign state machine issues. What only a real database can answer
 * — an expansion that resumes, a sample that parks its campaign, a release that frees the rest — is
 * driven against the live one by tests/Support/mail_probe.php and asserted in MailDrainTest.
 */
final class MailCampaignTest extends TestCase
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

    # Build a Mail service over a recording database, a mail section and a campaign policy section
    private function getMail(MailCampDatabase $db, array $conf = [], array $rule = []): \Mail
    {
        return new \Mail($db, ['sitename' => 'SLAED CMS', 'mail' => $conf, 'newsletter' => $rule]);
    }

    # Reach one private method, because composition, classification and the registry are all internal by design
    private function getCall(\Mail $mailer, string $meth, array $args = []): mixed
    {
        return (new ReflectionMethod(\Mail::class, $meth))->invokeArgs($mailer, $args);
    }

    # Set the phase and the code of the last attempt, which is the state the classification reads
    private function setStep(\Mail $mailer, string $phase, string $code): void
    {
        foreach (['phase' => $phase, 'code' => $code] as $name => $val) {
            $prop = new \ReflectionProperty(\Mail::class, $name);
            $prop->setValue($mailer, $val);
        }
    }

    # A permanent refusal means something different at every step, and only one of the steps says anything about the recipient
    #[Test]
    public function everyPhaseIsClassifiedByWhatItCanPossiblyMean(): void
    {
        $mailer = $this->getMail(new MailCampDatabase());
        $want = ['connect' => 'transport', 'ehlo' => 'transport', 'tls' => 'transport', 'auth' => 'transport'];
        $want += ['from' => 'campaign', 'rcpt' => 'rcpt', 'data' => 'message', '' => 'message'];
        foreach ($want as $phase => $scope) {
            $this->setStep($mailer, $phase, '550');
            $this->assertSame($scope, $this->getCall($mailer, 'getFailScope'), 'A permanent refusal at '.($phase ?: 'no phase').' was misread');
        }
    }

    # A refusal that is not permanent is a retry at every phase, and a refusal carrying no code decided nothing at all
    #[Test]
    public function anythingButAPermanentCodeIsTemporary(): void
    {
        $mailer = $this->getMail(new MailCampDatabase());
        foreach ([['rcpt', '450'], ['auth', '421'], ['rcpt', ''], ['connect', ''], ['data', '354']] as $step) {
            $this->setStep($mailer, $step[0], $step[1]);
            $this->assertSame('temp', $this->getCall($mailer, 'getFailScope'), 'A '.($step[1] ?: 'silent').' answer at '.$step[0].' was read as permanent');
        }
    }

    # The stored form of an address is produced by one function, because a lookup that disagrees with the unique index misses suppressions or collides them
    #[Test]
    public function theRegistryKeyLowercasesTheDomainAndKeepsTheLocalPart(): void
    {
        $mailer = $this->getMail(new MailCampDatabase());
        $this->assertSame('User.Name@slaed.net', $this->getCall($mailer, 'filterDeadMail', [' User.Name@SLAED.Net ']));
        $this->assertSame('a@b.c', $this->getCall($mailer, 'filterDeadMail', ['a@B.C']));
    }

    # An address carrying a control character is refused outright rather than cleaned, so nothing a trim produces can become a key
    #[Test]
    public function aControlCharacterIsRefusedByTheNormaliser(): void
    {
        $mailer = $this->getMail(new MailCampDatabase());
        foreach (["user@slaed.net\r\nBcc: x@y.z", "user\x00@slaed.net", 'user', '@slaed.net', 'user@'] as $mail) {
            $this->assertSame('', $this->getCall($mailer, 'filterDeadMail', [$mail]), 'The normaliser accepted '.rawurlencode($mail));
        }
    }

    # A non-ASCII domain is stored in its canonical form, or refused where the host cannot produce one, because two spellings of one mailbox are two keys
    #[Test]
    public function aNonAsciiDomainIsCanonicalOrRefused(): void
    {
        $mailer = $this->getMail(new MailCampDatabase());
        $done = $this->getCall($mailer, 'filterDeadMail', ['user@почта.рф']);
        if (!function_exists('idn_to_ascii')) {
            $this->assertSame('', $done, 'A host without the conversion stored a spelling that would not match itself');
            return;
        }
        $this->assertSame('user@xn--80a1acny.xn--p1ai', $done);
    }

    # A syntactically impossible address is a fact about the address and the one refusal the ladder is allowed to make on its own
    #[Test]
    public function theLadderRefusesOnlyWhatIsCertain(): void
    {
        $mailer = $this->getMail(new MailCampDatabase(), ['verify' => '0']);
        $this->assertFalse($mailer->checkAddress('not an address'));
        $this->assertFalse($mailer->checkAddress(''));
    }

    # A recorded history of recipient refusals keeps an address out of bulk audiences, and one below the cap does not
    #[Test]
    public function theRegistryStopsAnAddressOnlyAtTheConfiguredCount(): void
    {
        $db = new MailCampDatabase();
        $db->rows = [['fails' => 2]];
        $mailer = $this->getMail($db, ['verify' => '0'], ['bouncemax' => '2']);
        $this->assertFalse($mailer->checkAddress('user@slaed.net'));
        $db->rows = [['fails' => 1]];
        $this->assertTrue($mailer->checkAddress('user@slaed.net'));
    }

    # Every step of the ladder degrades to sending: a check that cannot run states nothing, and a check that states nothing must never stop a mailing
    #[Test]
    public function everyCheckThatCannotAnswerSendsAnyway(): void
    {
        $db = new MailCampDatabase();
        $db->rows = [[], []];
        $mailer = $this->getMail($db, ['verify' => '0'], ['bouncemax' => '2']);
        $this->assertTrue($mailer->checkAddress('user@slaed.net'), 'An address with no recorded history was refused');
        $this->assertTrue($this->getCall($mailer, 'checkMailDomain', ['user@почта.рф']), 'A domain the resolver cannot be asked about was refused');
    }

    # Only a permanent verdict about a recipient is counted, and it is counted as a running total rather than as a flag
    #[Test]
    public function aRecipientVerdictIsCountedAgainstTheAddressAlone(): void
    {
        $db = new MailCampDatabase();
        $mailer = $this->getMail($db);
        $this->setStep($mailer, 'rcpt', '550 5.1.1');
        $this->getCall($mailer, 'addDeadMail', ['User@SLAED.net']);
        $sql = $db->getMatch('_maildead');
        $this->assertCount(1, $sql);
        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE fails = LEAST(255, fails + 1)', $sql[0]['query']);
        $this->assertSame('User@slaed.net', $sql[0]['pars']['mail']);
        $this->assertSame('rcpt', $sql[0]['pars']['phase']);
    }

    # A campaign row is written held and marked, so an audience exists in the queue without a single message of it being claimable
    #[Test]
    public function anExpandedAudienceIsStoredHeld(): void
    {
        $db = new MailCampDatabase();
        $mesg = ['kind' => 'newsletter', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net', 'ref' => 4, 'camp' => true, 'hold' => true];
        $this->assertTrue($this->getMail($db)->addQueue($mesg));
        $sql = $db->getMatch('INSERT INTO test_mail');
        $this->assertSame(2, $sql[0]['pars']['camp']);
        $this->assertSame(1, $sql[0]['pars']['hold']);
        $this->assertSame(4, $sql[0]['pars']['ref']);
    }

    # A transactional message is never part of a campaign, whatever a call site passes, because it has no audience to be held with
    #[Test]
    public function aTransactionalRowIsNeverHeld(): void
    {
        $db = new MailCampDatabase();
        $this->assertTrue($this->getMail($db)->addQueue(['kind' => 'account', 'email' => 'user@slaed.net', 'sender' => 'info@slaed.net']));
        $sql = $db->getMatch('INSERT INTO test_mail');
        $this->assertSame(0, $sql[0]['pars']['camp']);
        $this->assertSame(0, $sql[0]['pars']['hold']);
    }

    # Three tests decide whether a mailing is sampled, and each of them alone is enough to send the audience whole
    #[Test]
    public function theSampleIsSkippedWheneverSamplingWouldMeanNothing(): void
    {
        foreach ([[0, 5000], [100, 0], [100, 100]] as $case) {
            $db = new MailCampDatabase();
            $rule = ['canary' => (string)$case[0], 'canarymin' => '500'];
            $this->assertSame(5, $this->getMail($db, [], $rule)->setCampReady(4, $case[1]));
            $free = $db->getMatch('SET hold = 0');
            $this->assertCount(1, $free, 'An unsampled audience was not released whole');
            $this->assertStringContainsString('kind = \'newsletter\' AND ref = :id AND status = 0', $free[0]['query']);
        }
    }

    # A sampled mailing releases one address per recipient domain and holds everything else, because a hundred addresses behind one provider measure that provider
    #[Test]
    public function theSampleIsDrawnAcrossDomainsAndReleasesOnlyItself(): void
    {
        $db = new MailCampDatabase();
        $db->rows = [[['id' => 11], ['id' => 12]], [['id' => 20]]];
        $this->assertSame(3, $this->getMail($db, [], ['canary' => '3', 'canarymin' => '2'])->setCampReady(4, 500));
        $pick = $db->getMatch('GROUP BY SUBSTRING_INDEX(email');
        $this->assertCount(1, $pick);
        $this->assertStringContainsString('ORDER BY RAND() LIMIT 3', $pick[0]['query']);
        $mark = $db->getMatch('SET camp = 1, hold = 0');
        $this->assertCount(1, $mark);
        $this->assertStringContainsString('WHERE id IN (11, 12, 20)', $mark[0]['query']);
        $this->assertSame([], $db->getMatch('SET hold = 0 WHERE kind'), 'A sampled mailing released its whole audience');
    }

    # A release frees the audience and never the sample, which was released once already and has its results
    #[Test]
    public function areleaseTouchesTheAudienceAndNotTheSample(): void
    {
        $db = new MailCampDatabase();
        $db->rows = [['num' => 40]];
        $this->assertTrue($this->getMail($db)->setCampFree(4));
        $free = $db->getMatch('_mail SET hold = 0');
        $this->assertCount(1, $free);
        $this->assertStringContainsString('AND camp = 2 AND status = 0', $free[0]['query']);
        $this->assertCount(1, $db->getMatch('_newsletter SET status = 5'));
    }

    # A release that frees nothing finishes the mailing, because an audience suppression left no larger than its sample has already been sent
    #[Test]
    public function areleaseWithNothingLeftFinishesTheMailing(): void
    {
        $db = new MailCampDatabase();
        $db->rows = [['num' => 0]];
        $this->assertTrue($this->getMail($db)->setCampFree(4));
        $this->assertSame([], $db->getMatch('_newsletter SET status = 5'), 'A mailing with nothing left to send was started again');
        $this->assertCount(1, $db->getMatch('_newsletter SET status = 6'));
    }

    # An abort holds both slices, because stopping a mailing during its sample has to stop the sample as well
    #[Test]
    public function anAbortHoldsEverythingThatHasNotBeenSent(): void
    {
        $db = new MailCampDatabase();
        $this->assertTrue($this->getMail($db)->setCampAbort(4, 'stopped by the test'));
        $hold = $db->getMatch('_mail SET hold = 1');
        $this->assertCount(1, $hold);
        $this->assertStringContainsString('AND camp > 0 AND status = 0', $hold[0]['query']);
        $note = $db->getMatch('_newsletter SET status = 7');
        $this->assertSame('stopped by the test', $note[0]['pars']['note']);
    }

    # A row refused before any transport was entered carries no verdict of its own, and must never be recorded under the answer the previous row happened to get
    #[Test]
    public function aRowThatNeverReachedATransportInheritsNoVerdict(): void
    {
        $db = new MailCampDatabase();
        $mailer = $this->getMail($db, [], ['breakwin' => '1', 'abort' => '1']);
        $this->setStep($mailer, 'rcpt', '550');
        $row = ['id' => 5, 'kind' => 'newsletter', 'sender' => 'info@slaed.net', 'email' => 'user@slaed.net', 'title' => 'x'];
        $row += ['body' => '', 'ref' => 9, 'prio' => 3, 'tries' => 0, 'camp' => 2];
        $this->assertSame('fail', $this->getCall($mailer, 'setQueueSend', [$row]));
        $mark = $db->getMatch('SET status = :stat');
        $this->assertCount(1, $mark);
        $this->assertSame('', $mark[0]['pars']['phase'], 'A missing shared body was recorded under the phase of an earlier message');
        $this->assertSame('', $mark[0]['pars']['code']);
        $this->assertSame([], $db->getMatch('_maildead'), 'A missing shared body was blamed on the recipient');
        $this->assertSame([], $db->getMatch('_newsletter SET status = 7'), 'A missing shared body was counted by the breaker');
    }

    # The breaker reads a window of results and fires only when enough of them exist and enough of them were about a recipient
    #[Test]
    public function theBreakerFiresOnRecipientFailuresAloneAndOnlyWithAFullWindow(): void
    {
        $rows = [];
        for ($i = 0; $i < 4; $i++) $rows[] = ['status' => 2, 'phase' => 'rcpt'];
        for ($i = 0; $i < 6; $i++) $rows[] = ['status' => 1, 'phase' => ''];
        $db = new MailCampDatabase();
        $db->rows = [$rows];
        $this->getCall($this->getMail($db, [], ['breakwin' => '10', 'abort' => '40']), 'checkCampBreak', [4]);
        $this->assertCount(1, $db->getMatch('_newsletter SET status = 7'), 'A mailing over its limit was not aborted');
        $db = new MailCampDatabase();
        $db->rows = [array_slice($rows, 0, 9)];
        $this->getCall($this->getMail($db, [], ['breakwin' => '10', 'abort' => '40']), 'checkCampBreak', [4]);
        $this->assertSame([], $db->getMatch('_newsletter SET status = 7'), 'A rate was read before the window held enough results');
    }

    # A window full of refusals that were not about recipients is not evidence about the list and must not stop the mailing
    #[Test]
    public function refusalsThatBlameTheMessageNeverTripTheBreaker(): void
    {
        $rows = [];
        for ($i = 0; $i < 10; $i++) $rows[] = ['status' => 2, 'phase' => 'data'];
        $db = new MailCampDatabase();
        $db->rows = [$rows];
        $this->getCall($this->getMail($db, [], ['breakwin' => '10', 'abort' => '10']), 'checkCampBreak', [4]);
        $this->assertSame([], $db->getMatch('_newsletter SET status = 7'));
    }
}
