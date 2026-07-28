<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 2 of docs/MAIL-2026.md, the half no double can answer: an atomic claim, a backoff that a
 * real clock has to expire, a prune that has to remove exactly the rows past its window, and a whole
 * drain run. tests/Support/mail_probe.php boots the real core in an isolated CLI process and drives
 * the class against the live database, cleaning up every row it wrote; the delivery half runs
 * against tests/Support/mail_relay.php, a loopback SMTP sink, so a message is verified as the relay
 * received it rather than as the sender believed it sent it. Nothing leaves this host.
 */
final class MailDrainTest extends TestCase
{
    private static array $probe = [];

    # Run one probe scenario and memoize its report for every test in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/mail_probe.php';
        $work = sys_get_temp_dir().'/slaed_mail_probe_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (isset($data['error'])) $this->markTestSkipped((string)$data['error']);
        return self::$probe[$mode] = $data;
    }

    # No scenario may leave a row behind: each writes into live tables and each removes exactly what it wrote
    #[Test]
    public function everyProbeRunLeavesTheTableAsItFoundIt(): void
    {
        $this->assertTrue($this->getProbe('mailqueue')['clean']);
        $this->assertTrue($this->getProbe('maildrain')['clean']);
        $this->assertTrue($this->getProbe('mailcamp')['clean']);
    }

    # A criterion is expanded by the scheduler into one row per recipient, resumably, and the request that pressed save writes none of them
    #[Test]
    public function theProducerExpandsACriterionIntoQueueRows(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame('success', $data['expand']['status']);
        $this->assertSame(3, $data['state']['total']);
        $this->assertTrue($data['state']['cursor'], 'The producer finished without recording where it got to');
    }

    # An address the registry holds at its cap never enters a bulk audience and never costs a delivery attempt
    #[Test]
    public function aSuppressedAddressIsSkippedAtQueueTime(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame(1, $data['expand']['extra']['last_newsletter_skipped']);
        $this->assertSame(0, $data['dead'], 'A suppressed address was written into the queue');
    }

    # The expansion releases only its sample and holds the audience, and the campaign says which of the two branches it took
    #[Test]
    public function theSampleIsReleasedAndTheAudienceIsHeld(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame(3, $data['state']['status'], 'The campaign did not enter the canary state');
        $this->assertSame(['1-0' => 2, '2-1' => 1], $data['slice']);
    }

    # The drain sends the sample, counts it against the mailing and parks the campaign, and it releases nothing on its own however good the result was
    #[Test]
    public function theSampleParksTheCampaignInsteadOfReleasingIt(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame(2, $data['canary']['sent']);
        $this->assertSame(4, $data['held']['status'], 'A finished sample did not park the campaign');
        $this->assertSame(2, $data['held']['send']);
        $this->assertSame(0, $data['blocked']['sent'], 'A held campaign was drained without an operator releasing it');
    }

    # A release is what makes the rest of the audience claimable, and the drain then finishes the campaign
    #[Test]
    public function areleaseIsWhatFinishesTheMailing(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame(['1-0' => 2, '2-0' => 1], $data['freed']);
        $this->assertSame(1, $data['rest']['sent']);
        $this->assertSame(6, $data['done']['status']);
        $this->assertSame(3, $data['done']['send']);
        $this->assertTrue($data['done']['ended'], 'A finished mailing carries no end time');
        $this->assertSame(0, $data['left']);
    }

    # One body is stored once and read at send time, so a mailing of any size carries one copy of its text
    #[Test]
    public function everyRecipientOfAMailingGetsTheOneStoredBody(): void
    {
        $data = $this->getProbe('mailcamp');
        $this->assertSame(3, $data['relay']['mails']);
        $this->assertSame(3, $data['shared'], 'A recipient received something other than the stored mailing body');
        $this->assertSame(1, $data['relay']['links']);
    }

    # What a call site queues is a pending row carrying the raw subject, the caller's priority and the client block of the request that queued it
    #[Test]
    public function anEnqueuedMessageIsStoredAsAPendingRow(): void
    {
        $data = $this->getProbe('mailqueue')['stored'];
        $this->assertTrue($data['done']);
        $this->assertSame('probe', $data['kind']);
        $this->assertSame(1, $data['prio']);
        $this->assertSame(0, $data['status']);
        $this->assertSame(0, $data['tries']);
        $this->assertTrue($data['subject'], 'A subject of exactly the column length came back changed');
        $this->assertTrue($data['raw'], 'The stored subject is MIME encoded, and the column would truncate the encoded form');
        $this->assertTrue($data['client'], 'The client block of the queueing request is missing from the stored body');
    }

    # A claimed row belongs to the run that took it: a second claim sees nothing, which is what stops two concurrent runs from sending one message twice
    #[Test]
    public function aClaimedRowCannotBeClaimedTwice(): void
    {
        $data = $this->getProbe('mailqueue')['claim'];
        $this->assertSame(1, $data['mine']);
        $this->assertSame(0, $data['them']);
        $this->assertTrue($data['marked'], 'The claimed row carries no lock identifier');
    }

    # A run that died before recording a result does not hold its rows forever: past the lock window they are claimable again
    #[Test]
    public function aStaleClaimIsPickedUpAgain(): void
    {
        $this->assertSame(1, $this->getProbe('mailqueue')['claim']['stale']);
    }

    # A refused row is unlocked, counted, given the reason on one line and moved behind the configured backoff, where the next claim leaves it alone
    #[Test]
    public function aRefusedRowWaitsBehindItsBackoff(): void
    {
        $data = $this->getProbe('mailqueue');
        $this->assertSame(0, $data['retry']['status']);
        $this->assertSame(1, $data['retry']['tries']);
        $this->assertSame('550 5.7.1 refused by policy', $data['retry']['error']);
        $this->assertSame('', $data['retry']['lock']);
        $this->assertGreaterThan(280, $data['retry']['wait']);
        $this->assertSame(0, $data['claim']['waiting'], 'A row behind its backoff was claimed before its time');
    }

    # After the attempt cap the row is failed and stops being retried, and a failure that cannot improve skips the retries altogether
    #[Test]
    public function theAttemptCapAndAHardFailureBothEndTheRow(): void
    {
        $data = $this->getProbe('mailqueue');
        $this->assertSame([2, 3], [$data['cap']['status'], $data['cap']['tries']]);
        $this->assertSame([2, 1], [$data['hard']['status'], $data['hard']['tries']]);
    }

    # Pruning removes accepted rows past their window and applies the shorter one to bulk rows only, leaving failures for the admin view
    #[Test]
    public function pruningRemovesOnlyWhatIsPastItsWindow(): void
    {
        $data = $this->getProbe('mailqueue')['prune'];
        $this->assertSame(2, $data['removed']);
        $this->assertFalse($data['oldbulk'], 'A bulk row past the short window survived');
        $this->assertTrue($data['newbulk'], 'A bulk row inside the short window was pruned');
        $this->assertFalse($data['oldmail'], 'A transactional row past its window survived');
        $this->assertTrue($data['failold'], 'A failed row was pruned, and it is what the admin view exists to show');
    }

    # A drain run delivers what it claimed and marks every row with what the relay answered
    #[Test]
    public function theDrainDeliversWhatWasQueued(): void
    {
        $data = $this->getProbe('maildrain');
        $this->assertSame(3, $data['run']['sent']);
        $this->assertSame(0, $data['run']['left']);
        $this->assertSame([1, 1, ''], $data['state']['one@slaed.net']);
        $this->assertSame([1, 1, ''], $data['state']['two@slaed.net']);
        $this->assertSame(3, $data['relay']['mails'], 'The relay did not receive every delivered message');
    }

    # One connection carries the whole run, which is what makes a drain one handshake instead of one per message
    #[Test]
    public function oneConnectionCarriesTheWholeRun(): void
    {
        $this->assertSame(1, $this->getProbe('maildrain')['relay']['links']);
    }

    # The stored raw subject reaches the relay correctly encoded and folded, and decodes back to what the call site queued
    #[Test]
    public function aStoredSubjectRoundTripsThroughTheDrain(): void
    {
        $data = $this->getProbe('maildrain')['sent'];
        $this->assertStringStartsWith('=?UTF-8?B?', $data[0]['subject']);
        $this->assertSame('Пароль '.str_repeat('и', 60), $data[0]['plain']);
        foreach (explode("\r\n", $data[0]['head']) as $line) {
            $this->assertLessThanOrEqual(78, strlen($line), 'A folded header line is longer than the encoded-word limit allows');
        }
    }

    # The body of a delivered message is the one the request composed, client block included, and never the drain's own request data
    #[Test]
    public function theDeliveredBodyIsTheOneTheRequestComposed(): void
    {
        $data = $this->getProbe('maildrain')['sent'];
        $this->assertStringContainsString('<p>first</p>', $data[0]['body']);
        $this->assertStringContainsString('127.0.0.1', $data[0]['body']);
        $this->assertStringNotContainsString('127.0.0.1', $data[1]['body'], 'A row that never asked for the client block was given one');
    }

    # A shared body is read from its source row at send time, so one mailing stores one body however many recipients it has
    #[Test]
    public function aSharedBodyIsResolvedFromItsSourceRow(): void
    {
        $data = $this->getProbe('maildrain');
        $this->assertTrue($data['refbody'], 'The message referring to a shared body was delivered without it');
        $this->assertSame([1, 1, ''], $data['state']['three@slaed.net']);
    }

    # A reference to a body that is gone fails the row with a stated reason instead of delivering an empty message
    #[Test]
    public function aMissingSharedBodyFailsTheRowInsteadOfSendingNothing(): void
    {
        $data = $this->getProbe('maildrain')['state'];
        $this->assertTrue($data['queued'], 'The row pointing at a missing body was refused at enqueue rather than at send time');
        $this->assertSame([2, 1, 'the shared body this message refers to no longer exists'], $data['dead@slaed.net']);
    }

    # The rate cap stops the run rather than slowing it, and what it did not send is left claimable for the next run
    #[Test]
    public function theRateCapStopsTheRunAndReleasesTheRest(): void
    {
        $data = $this->getProbe('maildrain')['rate'];
        $this->assertSame(1, $data['sent']);
        $this->assertSame(2, $data['left']);
        $this->assertSame('rate', $data['stop']);
        $this->assertSame(2, $data['free'], 'A row the run did not send was left claimed');
    }

    # The window outlives the run: a second run inside the same minute stays under the cap instead of opening a window of its own
    #[Test]
    public function theRateWindowHoldsAcrossTwoRuns(): void
    {
        $data = $this->getProbe('maildrain')['again'];
        $this->assertSame(0, $data['sent']);
        $this->assertSame('rate', $data['stop']);
        $this->assertSame(2, $data['left']);
    }
}
