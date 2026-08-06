<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 2 of docs/COMMENTS-REDESIGN-2026.md: one rule set for add and edit, a state change expressed as a conditional
 * update, a soft delete that never counts twice, an idempotency key on the add and a stored format that decides how a
 * body renders. The behaviour half runs through tests/Support/contract_probe.php, which signs in as the administrator
 * of this installation before the core boots — isAdmin() memoizes its verdict on the first call — and drives the class
 * against the live rows inside a transaction it always rolls back. That closes the moderator gap batches 1 to 3 left
 * open for status and delete. The format half needs no database and calls the parser directly.
 */
final class CommentStateTest extends TestCase
{
    private static array $probe = [];

    # Run one probe scenario per process and memoize its report for every test in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        return self::$probe[$mode] = $data;
    }

    # Return the write report, or skip when this installation has no administrator the probe can sign in as
    private function getState(): array
    {
        $data = $this->getProbe('commentstage');
        if (!$data['admin']) $this->markTestSkipped('No super administrator with a stored address on this installation');
        if (empty($data['target'])) $this->markTestSkipped('No writable news target on this installation');
        return $data;
    }

    # The probe drives real writes against live rows and may not leave one behind
    #[Test]
    public function theProbeRunLeavesTheTableUntouched(): void
    {
        $this->assertTrue($this->getProbe('commentstage')['clean']);
    }

    # One rule set answers both paths, and the length rule measures the longest word rather than the last one
    #[Test]
    public function theLengthRuleMeasuresTheLongestWord(): void
    {
        $data = $this->getProbe('commentstage');
        $this->assertSame($data['rules']['longest'], $data['rules']['last'], 'The rule set answers differently when the long word is not the last one');
        $this->assertNotSame([], $data['rules']['longest'], 'A word over the limit was accepted');
    }

    # The length rule counts characters, not bytes: a word of exactly the limit in two-byte characters is accepted
    #[Test]
    public function theLengthRuleCountsCharactersNotBytes(): void
    {
        $data = $this->getProbe('commentstage');
        $this->assertGreaterThan($data['rules']['limit'], $data['rules']['bytes'], 'The probe word is not longer in bytes than in characters');
        $this->assertSame([], $data['rules']['chars'], 'A word of exactly the limit was rejected, so the rule still measures bytes');
    }

    # The rules an edit never had stay bound to the add: an empty body is refused on both paths, the flood window only on the add
    #[Test]
    public function theAddOnlyRulesStayBoundToTheAdd(): void
    {
        $data = $this->getState();
        $this->assertNotSame([], $data['rules']['empty'], 'An empty body was accepted');
        [$add, $edit] = $data['rules']['flood'];
        $this->assertNotSame([], $add, 'A second submit from one address inside the window was accepted');
        $this->assertSame([], $edit, 'The flood window of the add path fired on an edit');
        $this->assertSame([], $data['rules']['freed'], 'The window never reopens once the last comment of that address is old enough');
    }

    # A stored comment carries what the final contract keeps and nothing the request could have named: the key it was written under as raw bytes, and no mark of an edit or a removal
    #[Test]
    public function theStoredRowCarriesTheColumnsOfThisStage(): void
    {
        $data = $this->getState();
        $this->assertSame('', $data['stored']['error']);
        $this->assertSame('abcdef0123456789abcdef0123456789', $data['stored']['reqkey'], 'The submitted key is not the key the row was stored under');
        $this->assertNull($data['stored']['deleted']);
        $this->assertNull($data['stored']['edited']);
    }

    # A second add carrying the key of the first one answers the first comment and stores no second row
    #[Test]
    public function aRepeatedKeyAnswersTheFirstComment(): void
    {
        $data = $this->getState();
        [$first, $again, $error, $rows] = $data['replay'];
        $this->assertSame('', $error, 'The replayed add was refused instead of answered');
        $this->assertSame($first, $again, 'The replayed add answered another comment');
        $this->assertSame(1, $rows, 'The idempotency key is stored on more than one row');
    }

    # A repeated moderation click reports the same result and moves neither the target counter nor the points a second time
    #[Test]
    public function aRepeatedStatusTransitionCountsOnce(): void
    {
        $data = $this->getState();
        foreach (['hide', 'show'] as $step) {
            $this->assertTrue($data[$step][0], 'The '.$step.' transition was refused');
            $this->assertTrue($data[$step.'again'][0], 'The repeated '.$step.' transition was refused instead of answered as done');
            $this->assertSame($data[$step][1], $data[$step.'again'][1], 'The repeated '.$step.' transition moved the counter or the points again');
        }
        $this->assertSame($data['counters'][0], $data['counters'][1], 'Four transitions did not return the counter and the points to where they started');
        $this->assertGreaterThan(0, $data['counters'][2], 'This installation awards no points, so the points half of this check proves nothing');
    }

    # A delete marks the row once: the second call answers the same result, keeps the first timestamp and moves nothing
    #[Test]
    public function theSoftDeleteIsIdempotent(): void
    {
        $data = $this->getState();
        $this->assertTrue($data['delete']);
        $this->assertTrue($data['deleteagain']);
        $this->assertNotSame('', $data['deleted'][0], 'The delete left no timestamp on the row');
        $this->assertSame($data['deleted'][0], $data['deletedagain'][0], 'The repeated delete moved the timestamp of the first one');
        $this->assertSame($data['deleted'][1], $data['deletedagain'][1], 'The repeated delete moved the counter or the points again');
        $this->assertNotSame($data['before'], $data['deleted'][1], 'The delete of a published comment did not take its counter back');
    }

    # A deleted comment is gone from every read and can neither be edited nor moderated any more
    #[Test]
    public function aDeletedCommentLeavesEveryRead(): void
    {
        $data = $this->getState();
        $this->assertSame([], $data['gone']['single']);
        $this->assertFalse($data['gone']['list']);
        $this->assertFalse($data['gone']['admin']);
        $this->assertFalse($data['gone']['edit']);
        $this->assertFalse($data['gone']['status']);
    }

    # Deleting a comment that was never published takes nothing back, because nothing was ever counted for it
    #[Test]
    public function deletingAPendingCommentMovesNoCounter(): void
    {
        $data = $this->getState();
        [$done, $was, $now] = $data['pending'];
        $this->assertTrue($done);
        $this->assertSame($was, $now, 'The delete of a pending comment moved the counter or the points');
    }

    # The write path stores source: the trusted-html tokens a visitor may not open are gone, the censor still applies, and nothing else about the text is rewritten
    #[Test]
    public function theWritePathStoresSourceAndNotEscapedHtml(): void
    {
        $data = $this->getProbe('commentguest');
        if (!isset($data['norm'])) $this->markTestSkipped('No writable news target on this installation');
        [$error, $body, $word, $cens, $repl] = $data['norm'];
        $this->assertSame('', $error, 'The crafted body was refused');
        $this->assertStringNotContainsString('[usehtml]', $body, 'A visitor stored the trusted html token');
        $this->assertStringContainsString('<b>x</b>', $body, 'The body was escaped on write instead of stored as source');
        $this->assertStringContainsString('cost $5', $body, 'The dollar sign was still escaped into an entity on write');
        $this->assertStringContainsString('back\\slash', $body, 'The backslash was still escaped into an entity on write');
        if ($cens) $this->assertStringContainsString($repl, $body, 'The censor list no longer applies to a comment');
        if ($cens) $this->assertStringNotContainsString($word, $body, 'A censored word survived the write');
    }

    # A stored body survives being edited and saved again unchanged, which is what makes the column source rather than rendered output
    #[Test]
    public function aStoredBodyRoundTripsThroughBothEditPaths(): void
    {
        $data = $this->getState();
        if (!$data['round']) $this->markTestSkipped('No stored comment with a body on this installation');
        foreach ($data['round'] as $fmt => [$moder, $saved, $author, $part]) {
            $this->assertTrue($moder, 'The moderation save changed a '.$fmt.' body it was handed unchanged: '.$part);
            $this->assertTrue($saved, 'The author edit of a '.$fmt.' body was refused');
            $this->assertTrue($author, 'The author edit changed a '.$fmt.' body it was handed unchanged: '.$part);
        }
    }

    # Rows sharing one timestamp keep their order, which is what the second sort column buys
    #[Test]
    public function equalTimestampsKeepAStableOrder(): void
    {
        $data = $this->getState();
        $this->assertNotSame([], $data['sort'][0], 'The probe target rendered no row to sort');
        $this->assertSame($data['sort'][0], $data['sort'][1], 'Two reads of one list with equal timestamps answered different orders');
    }

    # The stored format decides how a body renders: plain recognizes no Markdown construct, markdown does, and an empty column renders as markdown
    #[Test]
    public function theStoredFormatDecidesTheRendering(): void
    {
        $data = $this->getProbe('commentformat');
        $this->assertStringContainsString('<strong>bold</strong>', $data['markdown']);
        $this->assertStringContainsString('<em>it</em>', $data['markdown']);
        $this->assertStringContainsString('<li>item</li>', $data['markdown']);
        $this->assertStringContainsString('**bold**', $data['plain']);
        $this->assertStringNotContainsString('<em>', $data['plain']);
        $this->assertStringNotContainsString('<li>', $data['plain']);
        $this->assertStringContainsString('<br>', $data['plain'], 'A plain body did not turn its line ending into a break');
        $this->assertSame($data['markdown'], $data['empty'], 'A row without a format did not render as markdown');
    }

    # Both formats read the inline BB the parser keeps for old content, and neither lets the author emit HTML of their own
    #[Test]
    public function bothFormatsReadBbAndEscapeHtml(): void
    {
        $data = $this->getProbe('commentformat');
        $this->assertStringContainsString('<strong>bb</strong>', $data['markdown']);
        $this->assertStringContainsString('<strong>bb</strong>', $data['plain']);
        foreach (['html', 'htmlplain'] as $case) {
            $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $data[$case], 'A stored tag rendered as markup in case '.$case);
            $this->assertStringNotContainsString('onerror=alert(1)>', $data[$case], 'A stored event handler survived case '.$case);
        }
        $this->assertStringContainsString('href="#"', $data['url'], 'A script scheme survived a bracket link');
        $this->assertStringNotContainsString('<b>raw</b>', $data['usehtml'], 'The trusted html escape hatch fired for a comment body');
    }
}
