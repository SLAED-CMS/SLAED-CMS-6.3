<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Every forum handler that names a post takes its category, topic and author from the stored row, never from the category the request names
final class ForumRightTest extends TestCase
{
    private static ?array $probe = null;
    private static array $src = [];

    # Run the probe once: it boots the real core against live rows, reads the place of a stored post and asks the right as a guest and as an account
    private function getProbe(): array
    {
        if (self::$probe !== null) return self::$probe;
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' forumright 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe forumright did not return JSON: '.$out);
        return self::$probe = $data;
    }

    # Return the source of one function, from its signature to its closing brace at the start of a line
    private function getSource(string $file, string $name): string
    {
        $key = $file.'::'.$name;
        if (isset(self::$src[$key])) return self::$src[$key];
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.$file);
        $beg = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($beg, $name.'() not found in '.$file);
        $end = strpos($code, "\n}\n", $beg);
        $this->assertNotFalse($end, $name.'() has no closing brace in '.$file);
        return self::$src[$key] = substr($code, $beg, $end - $beg);
    }

    # The place of a reply and of a topic is what their rows hold, a reply carrying the status of its topic, and a missing post has no category
    #[Test]
    public function thePlaceOfAPostIsItsStoredRow(): void
    {
        $data = $this->getProbe();
        if ($data['reply'] === [] && $data['topic'] === []) $this->markTestSkipped('This installation holds no forum post');
        foreach (['reply', 'topic'] as $kind) {
            if ($data[$kind] === []) continue;
            $this->assertSame($data[$kind][1], $data[$kind][0], 'The place of the '.$kind.' is not the one its rows hold');
        }
        $this->assertSame(['cid' => 0, 'topic' => 0, 'uid' => 0, 'status' => 0], $data['missing']);
    }

    # A guest holds the right only as a moderator: neither a post without an account nor any author matches a visitor who is not signed in
    #[Test]
    public function aGuestNeverMatchesAnAuthor(): void
    {
        $this->assertSame(['moder' => true, 'anon' => false, 'author' => false], $this->getProbe()['guest']);
    }

    # A signed-in author holds the right over their own post with the category right while the topic is open, and over nothing else
    #[Test]
    public function anAuthorHoldsTheRightOnlyOverTheirOwnOpenPost(): void
    {
        $data = $this->getProbe();
        if ($data['user'] === []) $this->markTestSkipped('No account with a stored password on this installation');
        $this->assertTrue($data['signed'], 'The probe could not sign in as the account it picked');
        $want = ['own' => true, 'closed' => false, 'other' => false, 'noright' => false, 'nobody' => false, 'delete' => true, 'moder' => true];
        $this->assertSame($want, $data['user']);
    }

    # Each handler that names a post reads its place, and none compares the author with the reader or takes the category of the request on its own
    #[Test]
    public function everyHandlerTakesTheCategoryFromThePost(): void
    {
        $file = 'modules/forum/index.php';
        foreach (['add', 'send', 'delete', 'move'] as $name) {
            $code = $this->getSource($file, $name);
            $this->assertStringContainsString('getForumPlace(', $code, $name.'() does not read the stored place of the post it names');
            $this->assertDoesNotMatchRegularExpression('/==\s*\(int\)\$user\[0\]/', $code, $name.'() compares the author outside checkForumRight()');
        }
        foreach (['add', 'send'] as $name) {
            $code = $this->getSource($file, $name);
            $this->assertStringContainsString("? \$place['cid'] : getVar('req', 'cat', 'num')", $code, $name.'() takes the category of the request for a named post');
            $this->assertStringContainsString("\$place['topic']", $code, $name.'() lets a reply answer another post than its topic');
        }
        $this->assertStringContainsString("\$catid = getForumPlace(intval(\$id))['cid'];", $this->getSource($file, 'delete'));
        $this->assertStringContainsString('$insert = $pid ? ($isreply', $this->getSource($file, 'send'), 'A reply is inserted without the reply right');
        foreach (['getForumSource', 'updateForumBody'] as $name) {
            $post = $this->getSource('core/user.php', $name);
            $this->assertStringContainsString('getForumPlace($id)', $post, $name.'() does not read the stored place of the post');
            $this->assertStringContainsString('checkForumRight(', $post, $name.'() asks another right than the buttons');
            $this->assertStringNotContainsString('getVar(', $post, $name.'() reads the request');
        }
    }

    # The buttons of the view ask the same right as the handlers, so the view never offers an action the handler refuses
    #[Test]
    public function theViewAsksTheSameRight(): void
    {
        $code = $this->getSource('modules/forum/index.php', 'view');
        $this->assertStringContainsString('checkForumRight($ismod, $isedit, (int)$val[3], (int)$tstatus)', $code);
        $this->assertStringContainsString('checkForumRight($ismod, $isdelete, (int)$val[3])', $code);
        $this->assertStringNotContainsString('$user[0]', $code);
    }
}
