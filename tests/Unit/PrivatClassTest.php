<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Step 2 of docs/PRIVAT-2026.md: the Privat class owns every read and write of the private message table, and every
 * one of its reads is one row of the predicate table of that plan. tests/Support/privat_class_probe.php boots the
 * real core in an isolated CLI process and drives the class against a disposable schema carrying the two shipped
 * tables it talks to. The fixture mailbox is written state by state without the class, so what each predicate has to
 * answer is known before it is asked: unread, read, saved, saved-but-unread, deleted by the recipient and deleted by
 * the sender, in both directions. The site database is never touched.
 */
final class PrivatClassTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once and memoize its report for every test in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/privat_class_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_privat_probe';
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

    # The class is loaded by the bootstrap and reachable as the request-scoped service, and the probe drops its schema again
    #[Test]
    public function theServiceIsWiredIntoTheRequest(): void
    {
        $this->assertTrue($this->getProbe()['wired'], 'The bootstrap does not build the class');
        $this->assertTrue($this->getProbe()['clean'], 'The probe left its schema on the server');
    }

    # Each of the three mailboxes answers its own predicate: saved messages leave the inbox, a deleted copy leaves both
    #[Test]
    public function everyMailboxAnswersItsOwnPredicate(): void
    {
        $run = $this->getRun('boxes');
        $this->assertSame([2, 1], $run['inbox'], 'The inbox is not the unsaved, undeleted received messages, newest first');
        $this->assertSame([4, 3], $run['saved'], 'The saved folder is not the saved, undeleted received messages');
        $this->assertSame([5], $run['outbox'], 'The outbox is not the sent messages the sender still holds');
        $this->assertSame([8, 7], $run['clara'], 'A second account does not see its own inbox');
        $this->assertSame([2, 2, 1], $run['count'], 'A count disagrees with the list it belongs to');
        $this->assertSame([2, 1], $run['recent'], 'The dashboard preview is not the head of the inbox');
    }

    # The unread badge counts a saved message that was never opened, and stops counting a deleted one
    #[Test]
    public function theBadgeCountsWhatWasNeverOpened(): void
    {
        $this->assertSame(2, $this->getRun('boxes')['badge'], 'The badge is not the unread received messages, saved ones included');
    }

    # The outgoing badge counts what the recipients have not opened, and stops counting a message the sender threw away
    #[Test]
    public function theOutgoingBadgeCountsWhatWasNotOpenedYet(): void
    {
        $run = $this->getRun('boxes');
        $this->assertSame([1, 3, 0], $run['badgeout'], 'The outgoing badge is not the sent, undeleted, unopened messages');
    }

    # Every list resolves the counterpart of the message rather than the reader, and a mailbox of nobody is empty
    #[Test]
    public function everyListNamesTheCounterpart(): void
    {
        $run = $this->getRun('boxes');
        $this->assertSame(['boris', 'boris'], $run['mate'], 'The inbox does not name the sender');
        $this->assertSame([0, 0, 0, 0], $run['guest'], 'A mailbox was answered without an account');
        $this->assertSame([2, [2], [1]], $run['pages'], 'The pager does not split the mailbox it counted');
    }

    # A detail view answers each participant their own copy, the other account, and nothing at all once their side is deleted
    #[Test]
    public function aDetailAnswersOnlyTheSideThatAsks(): void
    {
        $run = $this->getRun('views');
        $this->assertSame([3, 'boris', 'probe body 1'], $run['own'], 'The recipient does not read the message of the sender');
        $this->assertSame([3, 'boris'], $run['sent'], 'The sender is shown their own profile instead of the recipient');
        $this->assertSame([], $run['gone'], 'A recipient reads a copy they deleted');
        $this->assertSame([], $run['sentgone'], 'A sender reads a copy they deleted');
        $this->assertSame([], $run['wrongside'], 'A message answers a side it does not belong to');
        $this->assertSame([], $run['foreign'], 'A message of two other accounts was answered');
        $this->assertSame([], $run['guest'], 'A message was answered without an account');
    }

    # Every refusal of the send path answers its own machine code from the closed set the plan fixes
    #[Test]
    public function everyRefusalAnswersItsOwnCode(): void
    {
        $run = $this->getRun('send');
        $this->assertSame('not_logged', $run['nolog']);
        $this->assertSame('no_recipient', $run['noname']);
        $this->assertSame('unknown_recipient', $run['unknown']);
        $this->assertSame('no_title', $run['notitle']);
        $this->assertSame('no_body', $run['nobody']);
        $this->assertSame('word_long', $run['long'], 'A word over the limit was accepted');
        $this->assertSame('self', $run['self'], 'A message to yourself passed while the setting forbids it');
        $this->assertSame('ok', $run['selfok'], 'A message to yourself was refused while the setting allows it');
    }

    # The word limit counts characters: sixty Cyrillic letters are sixty letters and a hundred and twenty bytes
    #[Test]
    public function theWordLimitCountsCharacters(): void
    {
        $this->assertSame('ok', $this->getRun('send')['utf'], 'A word inside the limit was measured in bytes');
    }

    # An accepted send stores exactly what it was handed, unread, and answers the id of that row and of no other
    #[Test]
    public function anAcceptedSendAnswersItsOwnId(): void
    {
        $run = $this->getRun('send');
        $this->assertSame(['ok', true], $run['sent'], 'The send did not answer a new id');
        $this->assertSame([2, 3, 'probe subject', 'probe text', '10.0.0.7', 0], $run['stored'], 'The stored row is not the message that was sent');
    }

    # The send interval is enforced against the sent messages, and switched off when the installation sets no interval
    #[Test]
    public function theSendIntervalHolds(): void
    {
        $run = $this->getRun('send');
        $this->assertSame('flood', $run['flood'], 'A second send inside the interval was accepted');
        $this->assertSame('ok', $run['nowait'], 'A send was refused although the installation sets no interval');
    }

    # A full mailbox refuses the send, over the inbox quota and over the saved quota the plan has the write path recheck
    #[Test]
    public function aFullMailboxRefusesTheSend(): void
    {
        $run = $this->getRun('send');
        $this->assertSame('quota', $run['quota'], 'The inbox quota of the recipient was not rechecked');
        $this->assertSame('quota', $run['keptquota'], 'The saved quota of the recipient was not rechecked');
    }

    # Read, unread and saved are three states of their own: saving keeps what was read, unsaving keeps it too
    #[Test]
    public function readAndSavedAreIndependentStates(): void
    {
        $run = $this->getRun('flags');
        $this->assertSame([true, [1, 0, 0, 0]], $run['read'], 'A message was not marked read');
        $this->assertSame([true, [0, 0, 0, 0]], $run['unread'], 'A read message cannot be marked unread again');
        $this->assertSame([true, [1, 1, 0, 0]], $run['keep'], 'Saving a message discarded that it was read');
        $this->assertSame([true, [1, 0, 0, 0]], $run['drop'], 'Unsaving a message discarded that it was read');
        $this->assertSame([true, [1, 0, 0, 0], [1, 0, 0, 0]], $run['bulk'], 'A batch of two did not move both messages');
    }

    # One foreign, unknown, deleted or malformed id refuses the whole batch and writes nothing at all
    #[Test]
    public function oneBadIdRefusesTheWholeBatch(): void
    {
        $run = $this->getRun('flags');
        $this->assertSame([false, [0, 0, 0, 0]], $run['sender'], 'The sender of a message marked it read as its recipient');
        $this->assertSame([false, [0, 0, 0, 0]], $run['mixed'], 'A batch holding a foreign id moved the owned one anyway');
        $this->assertSame([false, [0, 0, 0, 0]], $run['unknown'], 'A batch holding an unknown id moved the owned one anyway');
        $this->assertSame([false, [0, 0, 1, 0]], $run['deleted'], 'A deleted copy was still written to');
        $this->assertFalse($run['empty'], 'An empty batch was accepted');
        $this->assertFalse($run['zero'], 'A batch holding no id was accepted');
        $this->assertFalse($run['huge'], 'A batch over the bound was accepted');
        $this->assertFalse($run['guest'], 'A batch without an account was accepted');
    }

    # The saved quota bounds what a batch really adds, so re-saving a saved message costs nothing
    #[Test]
    public function theSavedQuotaBoundsWhatIsAdded(): void
    {
        $run = $this->getRun('quota');
        $this->assertSame([false, [0, 0, 0, 0]], $run['full'], 'A full saved folder took one more message');
        $this->assertSame([true, [1, 1, 0, 0]], $run['again'], 'Saving an already saved message was counted against the quota');
        $this->assertSame([true, [0, 1, 0, 0]], $run['room'], 'A saved folder with room refused a message');
    }

    # A delete removes the copy of the side that asked and leaves the other side untouched until it deletes too
    #[Test]
    public function aDeleteTouchesOneSideOnly(): void
    {
        $run = $this->getRun('delete');
        $this->assertSame([true, [0, 0, 1, 0], [2], [8, 4, 3, 2, 1]], $run['recipient'], 'A recipient deleting a message changed what the sender sees');
        $this->assertSame([true, []], $run['both'], 'The row survived both sides deleting it');
        $this->assertSame([true, [1, 1, 1, 0], [4]], $run['saved'], 'A message deleted out of the saved folder did not leave it');
    }

    # A sender may delete a message the recipient has read, and still sees one the recipient has saved
    #[Test]
    public function aSenderKeepsTheirOwnOutbox(): void
    {
        $run = $this->getRun('delete');
        $this->assertSame([true, [1, 0, 0, 1]], $run['wasread'], 'A sender cannot delete a message that was read');
        $this->assertSame([8, 4, 3, 1], $run['wassaved'], 'A message the recipient saved vanished from the outbox of the sender');
    }

    # A message can only be deleted by a participant, and only through the side that participant is on
    #[Test]
    public function onlyAParticipantDeletes(): void
    {
        $run = $this->getRun('delete');
        $this->assertSame([false, [0, 0, 0, 0]], $run['foreign'], 'A third account deleted a message of two others');
        $this->assertSame([false, [0, 0, 0, 0]], $run['wrongside'], 'A recipient deleted their message through the outbox');
    }

    # Deleting an account takes both of its own sides out and leaves the counterpart a copy it can still read
    #[Test]
    public function aDeletedAccountLeavesTheCounterpartIntact(): void
    {
        $run = $this->getRun('user');
        $this->assertTrue($run['done'], 'The cleanup was refused');
        $this->assertSame([[], []], $run['erased'], 'A row neither side holds any more survived');
        $this->assertSame([0, 0, 1, 0], $run['kept'], 'The copy of the counterpart was erased with the account');
        $this->assertSame([8, 4, 3, 2, 1], $run['mate'], 'The counterpart lost the message from the deleted account');
        $this->assertSame([[2, 1], [4, 3]], $run['other'], 'The mailbox of an unrelated account was changed');
        $this->assertFalse($run['guest'], 'A cleanup ran without an account');
    }

    # The administrator list filters no state and names the state every row adds up to
    #[Test]
    public function theAdminListFiltersNoState(): void
    {
        $run = $this->getRun('admin');
        $this->assertSame(8, $run['total'], 'The administrator list dropped a row a participant had deleted');
        $this->assertSame([8, 7, 6, 5, 4, 3, 2, 1], $run['ids'], 'The administrator list is not ordered newest first');
        $this->assertSame(['clara', 'boris'], $run['names'], 'The administrator list does not name both accounts');
        $want = [1 => 'new', 2 => 'read', 3 => 'saved', 4 => 'saved', 5 => 'new', 6 => 'delin', 7 => 'delout', 8 => 'new'];
        $have = $run['state'];
        ksort($have);
        $this->assertSame($want, $have, 'A row is not read as the state its four columns add up to');
    }

    # An administrator deletion erases the row itself, and answers to no mailbox state
    #[Test]
    public function anAdminDeleteErasesTheRow(): void
    {
        $run = $this->getRun('admin');
        $this->assertSame([true, []], $run['erased'], 'The row survived the administrator deleting it');
        $this->assertFalse($run['zero'], 'A deletion without an id was accepted');
    }

    # A statement the server refuses rolls its own transaction back, writes nothing, and leaves the connection usable
    #[Test]
    public function aRefusedStatementRollsItselfBack(): void
    {
        $run = $this->getRun('fail');
        foreach (['read', 'keep', 'drop', 'user'] as $one) {
            $this->assertFalse($run[$one][0], 'The '.$one.' write reported success although the server refused it');
            $this->assertSame([0, 0, 0, 0], $run[$one][1], 'The '.$one.' write changed a row although it failed');
            $this->assertFalse($run[$one][2], 'The '.$one.' write left its transaction open');
        }
        $this->assertSame(['sql', false], $run['send'], 'A refused insert did not answer sql, or left its transaction open');
        $this->assertSame([true, [1, 0, 0, 0]], $run['after'], 'The connection was not usable after a refused statement');
    }

    # A send and a save that reads a quota both wait for the account row a second session holds, and a write that reads no quota does not
    #[Test]
    public function everyQuotaWriteWaitsForTheAccountRow(): void
    {
        $run = $this->getRun('lock');
        $this->assertSame(['sql', false], $run['send'], 'A send passed an account row a second session had locked, or left its transaction open');
        $this->assertSame([false, [0, 0, 0, 0], false], $run['keep'], 'A save passed the locked account row, changed a row anyway, or left its transaction open');
        $this->assertSame([true, [0, 0, 0, 0]], $run['free'], 'A write that reads no quota was made to wait for a lock it does not need');
        $this->assertSame(2, $run['held'], 'A message was stored while the account of the recipient was locked');
        $this->assertSame('ok', $run['after'], 'The send was still refused after the second session let the account row go');
        $this->assertSame(3, $run['count'], 'The send that was let through did not store exactly one message');
    }

    # A send the server broke off is attempted once more, and exactly once, and only when it owns the transaction it would roll back
    #[Test]
    public function aBrokenSendIsAttemptedOnceMore(): void
    {
        $run = $this->getRun('retry');
        $this->assertSame(['ok', true, 2, false], $run['won'], 'A send the server broke off was not repeated, or was repeated more than once');
        $this->assertSame(['sql', 2, false], $run['lost'], 'A send broken off twice did not answer sql, ran a third attempt, or left its transaction open');
        $this->assertSame(['sql', 1, true], $run['joined'], 'A send inside a transaction of its caller was retried, or rolled that transaction back');
        $this->assertSame(['sql', 1], $run['once'], 'A refusal that is not a deadlock was attempted a second time');
        $this->assertSame(3, $run['count'], 'The repeated send stored more than the one message it was given');
    }

    # Two accounts sending into the last free place of one mailbox at the same moment: one is accepted, the other is refused
    # The probe runs both sends as processes of their own, held back until one shared moment, because one connection cannot race itself
    #[Test]
    public function twoConcurrentSendsCannotTakeOnePlace(): void
    {
        $run = $this->getRun('race');
        $this->assertSame(['ok', 'quota'], $run['runs'], 'Two sends racing for the last place of a mailbox did not end as one accepted and one refused');
        $this->assertSame($run['room'], $run['held'], 'The mailbox was left holding more messages than its quota allows');
    }

    # Step 11: a send stores the source its author wrote, under the format the editor of that request names, and escapes nothing on the way in
    #[Test]
    public function aSendStoresTheSourceItWasGiven(): void
    {
        $run = $this->getRun('content');
        foreach (['plain', 'markdown'] as $mode) {
            $one = $run[$mode];
            if ($one['mode'] !== $mode) $this->markTestSkipped('The '.$mode.' editor is not enabled on this installation');
            $this->assertSame('ok', $one['error'], 'The '.$mode.' send was refused');
            $this->assertSame($mode, $one['format'], 'The stored format is not the one the editor of the request names');
            $this->assertSame([true, true], $one['kept'], 'The stored title or body is not byte for byte what the author submitted');
        }
    }

    # Step 11: the renderer both adapters call turns that source into markup that carries no tag, no handler and no script the author wrote
    #[Test]
    public function storedSourceRendersSafely(): void
    {
        $run = $this->getRun('content');
        foreach (['plain', 'markdown'] as $mode) {
            $html = $run[$mode]['render'];
            $this->assertStringNotContainsString('<script', $html, 'A script tag of the author survived the '.$mode.' rendering');
            $this->assertStringNotContainsString('href="javascript:', $html, 'A javascript URL of the author reached an attribute of the '.$mode.' rendering');
            $this->assertStringNotContainsString('<a href', $html, 'A link tag of the author survived the '.$mode.' rendering');
            $this->assertStringContainsString('&lt;script&gt;', $html, 'The script tag of the author was not rendered as its own text');
            $this->assertStringContainsString('<strong>bb</strong>', $html, 'The parser stopped rendering its own inline markup');
        }
        $this->assertStringContainsString('<br>', $run['plain']['render'], 'A plain body did not turn its line ending into a break');
        $this->assertStringNotContainsString('<br>', $run['markdown']['render'], 'A markdown body was rendered with the breaks of a plain one');
    }

    # Step 11: the title is plain source and the template it is printed through is what escapes it, in the text and in the attribute alike
    #[Test]
    public function theTitleIsEscapedAtTheTemplateBoundary(): void
    {
        $run = $this->getRun('content');
        foreach (['plain', 'markdown'] as $mode) {
            $html = $run[$mode]['title'];
            $this->assertStringNotContainsString('<img', $html, 'A tag of the title reached the page as markup');
            $this->assertSame(2, substr_count($html, '&lt;img src=x onerror=alert(1)&gt;'), 'The title was not escaped in both the attribute and the text');
            $this->assertStringNotContainsString('&amp;lt;', $html, 'The title was escaped twice, so it is still being encoded on the way in');
        }
    }

    # Step 11: what the writer still does once the escape is gone - the tokens a visitor may not open, the autolinker on the body alone, and the censor list on both fields
    #[Test]
    public function theWriterStillFiltersWhatChangesTheText(): void
    {
        $run = $this->getRun('writer');
        $this->assertSame('ok', $run['error'], 'The send of the normalization scenario was refused');
        $this->assertSame('*** t http://slaed.net', $run['title'], 'The title was not censored, kept a trusted-html token, or was run through the autolinker');
        $this->assertSame("<b>x</b> ***\nsee [url=http://slaed.net]http://slaed.net[/url] now", $run['body'], 'The body was not censored, kept a token, or was not autolinked');
    }

    # A write that joins a transaction it did not open leaves the commit to whoever did open it
    #[Test]
    public function aJoinedTransactionIsNotCommitted(): void
    {
        $run = $this->getRun('trx');
        $this->assertTrue($run['done'], 'The write was refused inside an open transaction');
        $this->assertSame([1, 0, 0, 0], $run['inside'], 'The write did not happen inside the transaction');
        $this->assertTrue($run['open'], 'The class committed a transaction it did not open');
        $this->assertSame([0, 0, 0, 0], $run['after'], 'The rollback of the caller did not take the write with it');
    }
}
