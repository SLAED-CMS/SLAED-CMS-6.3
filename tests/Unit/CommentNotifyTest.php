<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 3 of docs/COMMENTS-REDESIGN-2026.md: the notification of a new comment is a queue row written inside the
 * transaction that stores the comment, one per stored comment, and nothing is delivered while the visitor waits.
 * The behaviour half runs through tests/Support/contract_probe.php, which drives the two writes of the request
 * handler in its order inside a transaction it always rolls back, so a job written without a comment and a comment
 * written without its job are both visible as a number. The handler itself cannot be called from CLI — getVar()
 * reads a scalar through filter_input() — so its wiring is read from the source instead, the way the stage 0 guard
 * already reads the write path.
 */
final class CommentNotifyTest extends TestCase
{
    private static array $probe = [];
    private static array $src = [];

    # Run the notification probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' commentnotify 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe commentnotify did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # Return the report, or skip when this installation cannot produce a notification at all
    private function getNotify(): array
    {
        $data = $this->getProbe();
        if (empty($data['target'])) $this->markTestSkipped('No writable news target on this installation');
        if ($data['addmail'] !== '1') $this->markTestSkipped('Comment notifications are switched off on this installation');
        if (!$data['subs']) $this->markTestSkipped('No administrator on this installation is subscribed to news notifications');
        return $data;
    }

    # Return the source of one function or method, from its signature to its closing brace at the given indentation
    private function getSource(string $file, string $name, string $pad = ''): string
    {
        $key = $file.'::'.$name;
        if (isset(self::$src[$key])) return self::$src[$key];
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
        $beg = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($beg, $name.'() not found in '.$file);
        $end = strpos($code, "\n".$pad."}\n", $beg);
        $this->assertNotFalse($end, $name.'() has no closing brace in '.$file);
        return self::$src[$key] = substr($code, $beg, $end - $beg);
    }

    # The probe writes real comments and real queue rows against live tables and may not leave one of either behind
    #[Test]
    public function theProbeRunLeavesBothTablesUntouched(): void
    {
        $data = $this->getProbe();
        if (empty($data['target'])) $this->markTestSkipped('No writable news target on this installation');
        $this->assertSame([0, 0], $data['gone'], 'The probe left a comment or a queue row behind');
        $this->assertTrue($data['clean'], 'The probe run did not restore both tables');
    }

    # One stored comment writes one queue row, and the rollback takes both away together, which is what "inside the same transaction" means
    #[Test]
    public function theNotificationIsWrittenInsideTheCommentTransaction(): void
    {
        $data = $this->getNotify();
        [$error, $isnew, $rows, $jobs, $keyed] = $data['add'];
        $this->assertSame('', $error, 'The probe add was refused');
        $this->assertTrue($isnew, 'The class did not report the comment as stored by this call');
        $this->assertSame(1, $rows, 'The add stored something other than exactly one comment');
        $this->assertSame(1, $keyed, 'The idempotency key is stored on more than one row');
        $this->assertSame($data['subs'], $jobs, 'The add did not queue one job per administrator subscribed to this module');
        $this->assertSame([0, 0], $data['gone'], 'The rollback did not take the comment and its job away together');
    }

    # The row the notification produced is a stored job: nothing was attempted, nothing was delivered, and it carries the anchor of the comment it announces
    #[Test]
    public function theQueuedJobIsStoredAndNotDelivered(): void
    {
        $data = $this->getNotify();
        $this->assertNotSame([], $data['queued'], 'The add queued no job at all');
        foreach ($data['queued'] as [$kind, $status, $tries, $prio, $ref, $link]) {
            $this->assertSame('comment', $kind, 'The job was queued under another kind');
            $this->assertSame(0, $status, 'The job left the request already marked as accepted, so it was delivered inside it');
            $this->assertSame(0, $tries, 'The job was attempted inside the request');
            $this->assertSame(1, $prio, 'The comment notification lost its priority');
            $this->assertSame(0, $ref, 'A comment notification stores its own body rather than a reference');
            $this->assertTrue($link, 'The queued body does not link to the comment that was just stored');
        }
    }

    # A replayed submit stores no second comment, so it must write no second job either
    #[Test]
    public function aReplayedSubmitWritesNoSecondJob(): void
    {
        $data = $this->getNotify();
        [$error, $isnew, $same, $rows, $jobs] = $data['replay'];
        $this->assertSame('', $error, 'The replayed submit was refused instead of answered');
        $this->assertFalse($isnew, 'The replayed submit claimed to have stored a comment');
        $this->assertTrue($same, 'The replayed submit answered another comment');
        $this->assertSame(0, $rows, 'The replayed submit stored a second comment');
        $this->assertSame(0, $jobs, 'The replayed submit queued a second notification');
    }

    # A refused comment writes nothing at all, notification included
    #[Test]
    public function aRefusedCommentQueuesNothing(): void
    {
        $data = $this->getNotify();
        [$stopped, $isnew, $rows, $jobs] = $data['refuse'];
        $this->assertTrue($stopped, 'The empty body was accepted');
        $this->assertFalse($isnew, 'A refused add reported a stored comment');
        $this->assertSame(0, $rows, 'A refused add stored a comment');
        $this->assertSame(0, $jobs, 'A refused add queued a notification');
    }

    # A queue write that fails takes nothing with it: the transaction is still open and the comment already stored in it is still there
    #[Test]
    public function aRefusedQueueWriteKeepsTheStoredComment(): void
    {
        $data = $this->getNotify();
        [$queued, $active, $stored, $error] = $data['fail'];
        $this->assertFalse($queued, 'The queue accepted a rejected address');
        $this->assertNotSame('', $error, 'The refusal was not reported through getError()');
        $this->assertTrue($active, 'A refused queue write closed the transaction the comment is stored in');
        $this->assertSame(1, $stored, 'A refused queue write took the stored comment with it');
    }

    # The submit path costs the two writes and nothing else: the transport is never opened, so the 26.6 s of the old synchronous send cannot recur
    #[Test]
    public function theSubmitPathCostsOnlyItsWrites(): void
    {
        $data = $this->getNotify();
        $this->assertLessThan(1000, $data['msec'], 'The comment write and its notification took longer than a second');
        $this->assertLessThan(500, $data['notify'], 'The notification alone took longer than half a second');
    }

    # The handler owns one transaction and both writes happen inside it, the notification only for a comment this request stored
    #[Test]
    public function theHandlerOwnsTheTransactionSpanningBothWrites(): void
    {
        $code = $this->getSource('core/user.php', 'addComment');
        $this->assertStringContainsString('$own = $db->setSqlBegin();', $code);
        $this->assertStringContainsString('if ($new[\'error\'] === \'\' && $new[\'new\']) {', $code);
        $this->assertStringContainsString('addAdminMail($conf[\'comments\'][\'addmail\']', $code);
        $this->assertStringContainsString('if ($own) $db->setSqlRollback();', $code);
        $begin = strpos($code, 'setSqlBegin');
        $write = strpos($code, '$com->addComment(');
        $mail = strpos($code, 'addAdminMail(');
        $commit = strpos($code, 'setSqlCommit');
        $this->assertLessThan($write, $begin, 'The transaction opens after the comment is written');
        $this->assertLessThan($mail, $write, 'The notification is written before the comment');
        $this->assertLessThan($commit, $mail, 'The notification is written after the commit, so it is outside the transaction');
    }

    # The class answers whether this call is what stored the comment, because a replay must not repeat the side effects of the first one
    #[Test]
    public function theClassReportsWhetherThisCallStoredTheComment(): void
    {
        $code = $this->getSource('core/classes/comment.php', 'addComment', '    ');
        $this->assertStringContainsString('\'new\' => true, \'error\' => \'\'', $code);
        $this->assertSame(1, substr_count($code, '\'new\' => true'), 'More than one return of the add path claims to have stored a comment');
        $reply = $this->getSource('core/classes/comment.php', 'getKeyResult', '    ');
        $this->assertStringNotContainsString('\'new\' => true', $reply, 'A replayed submit is answered as a stored comment');
    }

    # The audience expander of the comment path ends in the queue and holds no delivery of its own
    #[Test]
    public function theNotificationPathOnlyQueues(): void
    {
        $code = $this->getSource('core/system.php', 'addAdminMail');
        $this->assertStringContainsString('$mailer->addQueue(', $code);
        $this->assertDoesNotMatchRegularExpression('#(?<![>$\w])mail\s*\(#', $code, 'The notification path still delivers a message itself');
    }
}
