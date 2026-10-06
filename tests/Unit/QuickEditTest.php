<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The quick edit of a stored text over its two routes, driven by tests/Support/route_probe.php quick with real requests of an author, a stranger and a moderator
final class QuickEditTest extends TestCase
{
    private static array $probe = [];

    # Return the source of one function, from its signature to its closing brace at the given indentation
    private function getSource(string $file, string $name, string $pad = ''): string
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
        $beg = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($beg, $name.'() not found in '.$file);
        $end = strpos($code, "\n".$pad."}\n", $beg);
        $this->assertNotFalse($end, $name.'() has no closing brace in '.$file);
        return substr($code, $beg, $end - $beg);
    }

    # Run the probe once in its quick mode and memoize the run; the routes may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_quick_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' quick 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The routes wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The routes wrote SQL errors');
            self::$probe = $data['runs']['quick'];
            $want = ['anna' => true, 'boris' => true, 'root' => true, 'moder' => true];
            $this->assertSame($want, array_slice(self::$probe['tokens'], 0, 4), 'A visitor of the probe found no page token');
        }
        return self::$probe;
    }

    # Both routes check their own token, so the shared gate of the router never answers them with an alert the editor would be swapped for
    #[Test]
    public function theRoutesCheckTheirOwnTokenFromTheHeader(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/public/index.php');
        $this->assertMatchesRegularExpression("/\\\$public = \(\\\$go == 1 && in_array\(\\\$op, \[[^\]]*'getQuickEdit', 'updateQuickEdit'[^\]]*\], true\)\)/", $code);
        $this->assertStringContainsString("case 'getQuickEdit': getQuickEdit(); break;", $code);
        $this->assertStringContainsString("case 'updateQuickEdit': updateQuickEdit(); break;", $code);
        $check = $this->getSource('core/system.php', 'checkQuickRequest');
        $this->assertStringContainsString("\$_SERVER['HTTP_X_CSRF_TOKEN']", $check);
        $this->assertStringNotContainsString('getVar(', $check, 'The token is read from a parameter');
        $this->assertStringNotContainsString('getRequestToken', $check, 'The token is read through the helper that falls back to a parameter');
        $this->assertLessThan(strpos($check, 'checkSiteToken'), strpos($check, 'REQUEST_METHOD'), 'The method is not checked before the token');
    }

    # Every editor call site names its storage literally, so the quick edit renders its editor inside the adapter of each kind and never in the generic class
    #[Test]
    public function theGenericClassRendersNoEditor(): void
    {
        $this->assertStringNotContainsString('getTplTextarea', (string)file_get_contents(dirname(__DIR__, 2).'/core/classes/quick.php'));
        $code = $this->getSource('core/system.php', 'getQuickService');
        foreach (['comment.body', 'forum.body', 'nodes.intro', 'nodes.body'] as $store) $this->assertStringContainsString("'store' => '".$store."'", $code);
    }

    # The editor opens with the stamp of the stored row and declares the page token in its own header; the routes refuse the other method with an Allow header
    #[Test]
    public function theEditorOpensWithTheStampAndOneMethodEach(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, true, true, true], $run['open'], 'The editor did not open with the stored text, its stamp, its own id and the page token');
        $this->assertSame([405, 'GET', 405, 'POST'], $run['methods'], 'A route answered the other method');
    }

    # A token missing from the header is refused even when a parameter carries it, and a stale one writes nothing
    #[Test]
    public function aTokenOutsideTheHeaderIsIgnored(): void
    {
        $run = $this->getRun()['tokens'];
        $this->assertSame([403, 403, 403, 403, true], [$run['none'], $run['param'], $run['postparam'], $run['stale'], $run['kept']]);
    }

    # A field outside its closed form is refused before anything is read: a kind without an adapter, a zero id, a field the kind does not carry and a short stamp
    #[Test]
    public function aFieldOutsideItsFormIsRefused(): void
    {
        $this->assertSame([422, 422, 422, 422, 422, true], $this->getRun()['forms']);
    }

    # A save stores the canonical text and answers the rendered body with the edited mark out of band
    #[Test]
    public function aSaveAnswersTheRegionAndItsMark(): void
    {
        $this->assertSame([200, true, true, 'Anna second text', true, false], $this->getRun()['save']);
    }

    # The repetition of a save with its old stamp and the same text is saved again and does not change the edit time
    #[Test]
    public function aRepeatedSaveIsSavedWithoutAWrite(): void
    {
        $this->assertSame([200, true], $this->getRun()['repeat'], 'The repeat of a stored save was a conflict or wrote again');
    }

    # Another text at a stale stamp is a conflict that answers the current text with its fresh stamp and stores nothing
    #[Test]
    public function aStaleStampIsAConflict(): void
    {
        $this->assertSame([409, true, true, true], $this->getRun()['conflict']);
    }

    # The rules of the kind still apply: a word past the limit and an empty text are refused with 422 and the row stays
    #[Test]
    public function theRulesOfTheKindStillApply(): void
    {
        $this->assertSame([422, 422, true], $this->getRun()['rules']);
    }

    # A stranger may neither open nor save the text of an author, and a moderator may do both
    #[Test]
    public function theRightComesFromTheStoredRow(): void
    {
        $run = $this->getRun();
        $this->assertSame([403, 403, true], $run['foreign']);
        $this->assertSame([200, 200, 'Root fixed it'], $run['moder']);
    }

    # A right lost between opening and saving refuses the save: the window closed after the editor opened is decided again under the lock
    #[Test]
    public function aRightLostAfterOpeningRefusesTheSave(): void
    {
        $this->assertSame([200, 403, 403, true], $this->getRun()['lost']);
    }

    # A removed item is gone for both routes and keeps its row
    #[Test]
    public function aRemovedItemIsGone(): void
    {
        $this->assertSame([404, 404, true], $this->getRun()['gone']);
    }

    # A forum post opens and saves through the same protocol: the stored form, its mark out of band, a repeat without a write and a stale stamp as a conflict
    #[Test]
    public function aForumPostSavesThroughTheSameProtocol(): void
    {
        $run = $this->getRun()['forum'];
        $this->assertSame([200, true, true], $run['open'], 'The forum editor did not open with its own id and the stamp of the stored row');
        $this->assertSame([200, true, true, 'Anna reply edited', true], $run['save']);
        $this->assertSame([200, true], $run['repeat'], 'The repeat of a stored forum save was a conflict or wrote again');
        $this->assertSame([409, true], $run['conflict']);
        $this->assertSame([403, 403, true], $run['foreign']);
        $this->assertSame([422, true, true], $run['names'], 'A quick edit bound a file of the forum folder its author does not own');
    }

    # A Node material offers the quick edit on its public page alone: its author sees an own entry, a stranger none, a card of the list none, its moderator before the editor
    #[Test]
    public function aNodeMaterialOffersTheQuickEditOnItsPageAlone(): void
    {
        $this->assertSame([true, true, false, false, true], $this->getRun()['node']['page']);
    }

    # A Node save stores at the version; an author edit of a published material goes back to moderation and says so, the repeat writes nothing and a stale version conflicts
    #[Test]
    public function aNodeSaveTakesTheVersionAndTheWorkflow(): void
    {
        $run = $this->getRun()['node'];
        $this->assertSame([200, true, true], $run['open'], 'The node editor did not open with the version as its stamp and its own id');
        $this->assertSame([200, true, true, 1, 1], $run['save'], 'The author edit did not go back to moderation in one version step with its note');
        $this->assertSame([200, true], $run['repeat']);
        $this->assertSame([409, true], $run['conflict']);
        $this->assertSame([404, 404, 422], $run['foreign'], 'A stranger reached the material sent to moderation or a field outside intro and body was taken');
        $this->assertSame([404, 404, 403], $run['draft'], 'A material the stranger may not read answered otherwise than a missing one, or a readable one not as denied');
        $this->assertSame([200, false, 'Intro by the moderator', 1], $run['moder'], 'A moderator edit moved the state or carried a note');
    }

    # The place of a forum post is read again under the lock: a topic closed or a post moved after the editor opened refuses the author, never the moderator
    #[Test]
    public function aForumPlaceChangedAfterOpeningRefusesTheAuthor(): void
    {
        $run = $this->getRun()['forum'];
        $this->assertSame([403, 403, true], $run['closed'], 'A save into a topic closed after the editor opened was stored');
        $this->assertSame([403, true], $run['moved'], 'A save of a post moved into a category without the edit right was stored');
        $this->assertSame([200, 'Root fixed the reply'], $run['moder']);
    }
}
