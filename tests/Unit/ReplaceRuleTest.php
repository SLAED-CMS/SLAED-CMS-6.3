<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The rules of word replacement are one form per Node type, so a save writes its own type and leaves the rules of the others as they are
final class ReplaceRuleTest extends TestCase
{
    private const CONF = ['replace' => ['content' => 'old|keep', 'news' => 'a|b||0|0']];
    private const TYPES = ['content' => 1, 'news' => 1, 'faq' => 1];

    # Run replacesave() over tests/Support/pick_probe.php, which lifts the shipped handler over stubs and records what it writes
    private function getSave(array $post, bool $token = true): array
    {
        $case = ['file' => 'admin/modules/config.php', 'func' => 'replacesave', 'conf' => self::CONF, 'types' => self::TYPES, 'token' => $token, 'post' => $post];
        $script = dirname(__DIR__).'/Support/pick_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_repl_'.md5((string)json_encode($case)).'_'.getmypid();
        $cmd = escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 '.escapeshellarg($script).' '.escapeshellarg($work)
            .' '.escapeshellarg(base64_encode((string)json_encode($case)));
        $out = (string)shell_exec($cmd.' 2>&1');
        foreach (['logs', 'config', ''] as $dir) {
            foreach (glob($work.'/'.$dir.'/*.*') ?: [] as $file) unlink($file);
            if ($dir !== '' && is_dir($work.'/'.$dir)) rmdir($work.'/'.$dir);
        }
        if (is_dir($work)) rmdir($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The probe failed');
        return $data['data'];
    }

    # A save of one type changes that type alone, and the rules of a type that had none before reach the file
    #[Test]
    public function aSaveWritesItsOwnTypeAndKeepsTheOthers(): void
    {
        $done = $this->getSave(['type' => 'faq', 'word[0]' => 'one', 'repl[0]' => '<b>1</b>', 'word[1]' => 'two', 'repl[1]' => '2']);
        $this->assertSame(['content' => 'old|keep', 'faq' => 'one|<b>1</b>||two|2', 'news' => 'a|b||0|0'], $this->getSorted($done['cfg']['replace.php']));
        $this->assertFalse($done['warn']);
        $this->assertSame('admin.php?name=config&op=replace&type=faq', $done['url']);
    }

    # An empty pair leaves no record, so a type without rules holds an empty value, the parser skips it and the merge of an update does not bring the shipped rules back
    #[Test]
    public function emptyPairsLeaveNoRecord(): void
    {
        $done = $this->getSave(['type' => 'news', 'word[0]' => '', 'repl[0]' => '', 'word[1]' => '0', 'repl[1]' => '0']);
        $this->assertSame('', $done['cfg']['replace.php']['news']);
        $this->assertArrayHasKey('news', $done['cfg']['replace.php']);
        $kept = $this->getSave(['type' => 'news', 'word[0]' => '', 'repl[0]' => 'text']);
        $this->assertSame('0|text', $kept['cfg']['replace.php']['news'], 'A written text of a disabled rule is lost');
    }

    # A post without its token or for a type that does not exist writes nothing, and the address of a refused type falls back on the page
    #[Test]
    public function aRefusedPostWritesNothing(): void
    {
        $gone = $this->getSave(['type' => 'ghost', 'word[0]' => 'one', 'repl[0]' => '1']);
        $this->assertSame([], $gone['cfg']);
        $this->assertTrue($gone['warn']);
        $this->assertSame('_error', $gone['text']);
        $bare = $this->getSave(['type' => 'news', 'word[0]' => 'one', 'repl[0]' => '1'], false);
        $this->assertSame([], $bare['cfg']);
        $this->assertTrue($bare['warn']);
        $this->assertSame('_tokenmiss', $bare['text']);
    }

    # The sign | joins the word to its replacement and the rules to each other, so a rule that holds one is refused whole by the number of its field and nothing is written
    #[Test]
    public function aBarInARuleIsRefused(): void
    {
        foreach ([['word[1]' => 'two', 'repl[1]' => 'a | b'], ['word[1]' => 'tw|o', 'repl[1]' => 'b']] as $bad) {
            $done = $this->getSave(['type' => 'faq', 'word[0]' => 'one', 'repl[0]' => '1'] + $bad);
            $this->assertSame([], $done['cfg'], 'A rule with | reached the file');
            $this->assertTrue($done['warn']);
            $this->assertSame('_replace_bar', $done['text']);
            $this->assertSame('admin.php?name=config&op=replace&type=faq', $done['url']);
        }
    }

    # The parser splits a rule once, at its first |, as the form of the panel shows it, so a stored replacement that holds | reaches the page whole
    #[Test]
    public function theParserSplitsARuleOnce(): void
    {
        require_once BASE_DIR.'/core/classes/parser.php';
        $keep = $GLOBALS['conf'] ?? null;
        $GLOBALS['conf'] = ['replace' => ['faq' => 'SLAED|<b>A | B</b>||cms|CMS']];
        try {
            $call = new \ReflectionMethod(\Parser::class, 'replaceText');
            $out = $call->invoke((new \ReflectionClass(\Parser::class))->newInstanceWithoutConstructor(), '<p>SLAED cms</p>', 'faq');
        } finally {
            if ($keep === null) unset($GLOBALS['conf']);
            else $GLOBALS['conf'] = $keep;
        }
        $this->assertSame('<p><b>A | B</b> CMS</p>', $out);
        $code = (string)file_get_contents(BASE_DIR.'/admin/modules/config.php');
        $this->assertStringContainsString("explode('|', \$fieldc[\$c] ?? '', 2)", $code, 'The form of the panel splits a rule otherwise than the parser');
    }

    # The rules are a tab of the settings of the main administrator, and their file stays where the parser reads it
    #[Test]
    public function theSectionIsATabOfTheSettings(): void
    {
        $this->assertArrayHasKey('replace', require BASE_DIR.'/config/replace.php');
        $code = (string)file_get_contents(BASE_DIR.'/admin/modules/config.php');
        $this->assertStringContainsString("case 'replace': replace(); break;", $code);
        $this->assertStringContainsString("case 'replacesave': replacesave(); break;", $code);
        $this->assertStringContainsString("|| !isAdmin(true)) die('Illegal file access');", $code, 'The rules insert HTML, so they stay the main administrator\'s');
    }

    # The rules in the order of their keys, as the written file holds them after setConfigFile()
    private function getSorted(array $rules): array
    {
        ksort($rules);
        return $rules;
    }
}
