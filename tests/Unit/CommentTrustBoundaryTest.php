<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 0 of docs/COMMENTS-REDESIGN-2026.md: the moderation mode and the target module of a comment
 * must come from the server, never from the request. The behaviour half runs through
 * tests/Support/contract_probe.php, which boots the real core in an isolated CLI process and calls
 * Comment::getTargetMode() against live rows; the contract half reads the write path and asserts that it no longer
 * takes cid or mod from the client. Stage 1, batch 3 moved that path into the Comment class, batch 4 added
 * the moderation delete to it and batch 6 absorbed the resolver and the counter, so the contract half reads
 * the class and its request handlers together. filter_input() cannot be driven from CLI, so
 * a full addComment() round trip belongs to the browser checks in docs/TESTS.md.
 */
final class CommentTrustBoundaryTest extends TestCase
{
    private static array $probe = [];
    private static array $src = [];

    # Run the comment probe once and memoize its report for every scenario in this class
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' comment 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe comment did not return JSON: '.$out);
        return self::$probe = $data;
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

    # A visible target hands its own acomm value to the write path
    #[Test]
    public function visibleTargetReportsItsStoredMode(): void
    {
        $data = $this->getProbe();
        if (!$data['open']) $this->markTestSkipped('No published news row with comments enabled on this installation');
        $this->assertSame($data['open'][0], $data['open'][1]);
        $this->assertContains($data['open'][1], [1, 2]);
        if ($data['vote']) $this->assertSame($data['vote'][0], $data['vote'][1]);
    }

    # A target with comments disabled, a hidden target and a missing id are all refused
    #[Test]
    public function unwritableTargetsResolveToDisabled(): void
    {
        $data = $this->getProbe();
        $this->assertSame(0, $data['missing']);
        $this->assertSame(0, $data['zero']);
        if ($data['off']) $this->assertSame(0, $data['off'][1]);
        if ($data['hide']) $this->assertSame(0, $data['hide'][1]);
        if (!$data['off'] && !$data['hide']) $this->markTestSkipped('No disabled or hidden news row on this installation');
    }

    # A module name outside the fixed map never resolves a target, whatever the request sends
    #[Test]
    public function unknownModuleNeverResolvesTarget(): void
    {
        $data = $this->getProbe();
        if (!$data['open']) $this->markTestSkipped('No published news row with comments enabled on this installation');
        foreach ($data['unknown'] as $mod => $mode) {
            $this->assertSame(0, $mode, 'Module "'.$mod.'" resolved a target');
        }
    }

    # The stored write path decides the status from the resolved mode, and the request handler feeds it nothing but the module key, the target id and the idempotency key
    # Stage 2 replaced the bare acomm comparisons with the CommentMode enum, so the shape this reads changed while what it guards did not
    #[Test]
    public function addCommentTakesModeFromServer(): void
    {
        $code = $this->getSource('core/classes/comment.php', 'addComment', '    ');
        $this->assertStringContainsString('$this->getTargetMode($mod, $id)', $code);
        $this->assertStringNotContainsString('getVar(', $code);
        $this->assertStringNotContainsString('$cid', $code);
        $this->assertMatchesRegularExpression('#\$mode === CommentMode::Moderated \|\| \$info\[\'access\'\]#', $code);
        $this->assertMatchesRegularExpression('#\$mode === CommentMode::Moderated \|\| \$this->conf\[\'anonpost\'\]#', $code);
        $this->assertStringContainsString('if ($last !== \'\' || $mode === CommentMode::Disabled)', $code);
        $route = $this->getSource('core/user.php', 'addComment');
        $this->assertStringNotContainsString('cid', $route);
        $this->assertStringContainsString('$com->addComment($mod, $id, $body, $name, $key, $pid)', $route);
        $this->assertStringContainsString('$base = $this->getParentPath($mod, $id, $pid)', $code, 'The parent of a reply is not resolved against the stored tree');
    }

    # The submit URL carries no moderation mode any more
    #[Test]
    public function commentFormSendsNoModeField(): void
    {
        $code = $this->getSource('core/user.php', 'setComShow');
        $this->assertStringContainsString('op=addComment&id=', $code);
        $this->assertStringNotContainsString('&cid=', $code);
    }

    # Editing and moderating a comment take the module from the stored row, not from the request
    #[Test]
    public function updatePathsTakeModuleFromStoredRow(): void
    {
        foreach (['updateComment', 'setStatus', 'deleteComment'] as $name) {
            $code = $this->getSource('core/classes/comment.php', $name, '    ');
            $this->assertStringContainsString('modul FROM \'.PREFIX_DB.\'_comment WHERE id = :id', $code);
            $this->assertStringContainsString('$mod = (string)($row[\'modul\'] ?? \'\');', $code);
            $this->assertStringContainsString('is_moder($mod)', $code);
            $this->assertStringNotContainsString('getVar(', $code);
        }
        foreach (['setStatus', 'deleteComment'] as $name) {
            $code = $this->getSource('core/classes/comment.php', $name, '    ');
            $this->assertStringContainsString('$cid = $row ? intval($row[\'cid\']) : 0;', $code);
            $this->assertStringContainsString('$this->addTargetCount($cid, $mod);', $code);
        }
        foreach (['updateComment', 'updateCommentStatus'] as $name) {
            $route = $this->getSource('core/system.php', $name);
            $this->assertStringNotContainsString('getVar(\'post\', \'mod\'', $route);
            $this->assertStringNotContainsString('getVar(\'get\', \'mod\'', $route);
            $this->assertStringNotContainsString('PREFIX_DB', $route);
        }
    }
}
