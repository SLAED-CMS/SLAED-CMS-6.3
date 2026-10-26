<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The text unit of update.php turns stored BB into Markdown once, by docs/PARSER.md, "Texts of the BB era", and getMigrateHtml() writes Markdown for a 6.2 text
final class UpdateTextTest extends TestCase
{
    private static string $code = '';

    private static array $probe = [];

    # The source of update.php, read once
    private static function getCode(): string
    {
        return self::$code !== '' ? self::$code : self::$code = (string)file_get_contents(dirname(__DIR__, 2).'/public/update.php');
    }

    # Load one function alone from update.php, whose top level runs the update, by cutting it out of its tokens
    private static function getFunc(string $name): void
    {
        if (function_exists($name)) return;
        $code = self::getCode();
        $from = strpos($code, 'function '.$name.'(');
        $tokens = token_get_all('<?php '.substr($code, (int)$from));
        $text = '';
        $depth = 0;
        foreach ($tokens as $one) {
            $part = is_array($one) ? $one[1] : $one;
            $text .= $part;
            if (in_array($part, ['{', '${'], true) || (is_array($one) && $one[0] === T_CURLY_OPEN)) $depth++;
            if ($part === '}' && --$depth === 0) break;
        }
        $file = sys_get_temp_dir().'/slaed_update_text_'.getmypid().'.php';
        file_put_contents($file, $text."\n");
        require $file;
        unlink($file);
    }

    # Convert one stored text, of the BB era or Markdown by origin
    private function getText(string $text, bool $esc = true): string
    {
        self::getFunc('getMigrateMarkdown');
        return \getMigrateMarkdown($text, $esc);
    }

    # The probe tests/Support/update_probe.php drives the unit against a disposable schema, never the stand database
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_text';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' texts 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs']['clean'];
        }
        return self::$probe;
    }

    # A text of the BB era: the converted tags become Markdown, breaks hard breaks, smilies emoji, and every text run is escaped to show what the author typed
    # The forms the old parser rendered convert too: [code=] as [code], whitespace around a value or an alternative text
    #[Test]
    public function aBbTextBecomesMarkdownWithItsTextEscaped(): void
    {
        $cases = [
            '[b]Nark[/b], hi *13 *18' => "**Nark**, hi \u{1F609} \u{1F631}",
            "One<br />\r\nTwo<br />\r\n<br />\r\nThree" => "One\\\nTwo\n\nThree",
            "[quote]Q one<br />\nQ two[/quote]<br />\nafter [quote][quote]deep[/quote]x[/quote]" => "> Q one\\\n> Q two\n\nafter\n\n> > deep\n>\n> x",
            '[b]Answer:[/b]text and [b] spaced [/b]x and [i]a[/i][s]b[/s]' => '**Answer**:text and  **spaced** x and *a*~~b~~',
            "[b]one\n\ntwo[/b]" => "**one**\n\n**two**",
            'snake_case _emph_ 2*3 [x] <b> &lt;font&gt; &amp;copy; a\\b ==mark== ~~del~~ `tick`'
                => 'snake_case \\_emph\\_ 2\\*3 \\[x\\] \\<b> \\<font> \\&copy; a\\\\b \\=\\=mark\\=\\= \\~\\~del\\~\\~ \\`tick\\`',
            "# head\n- item\n1. one\n+ plus\n    four\n> quote\n===\n| a |\n|---|" => "\\# head\n\\- item\n1\\. one\n\\+ plus\nfour\n\\> quote\n\\=\\=\\=\n| a |\n\\|---\\|",
            'Mail me@site.test and see http://site.test/a_b*c and www.site.test/x_y' => 'Mail me@site.test and see http://site.test/a_b*c and www.site.test/x_y',
            "Find:<br />\n[code]User-agent: *<br />\nDisallow: /a/[/code]<br />\nand [code]a ` b[/code] inline, [code=js]x()[/code] and [php]echo 1;[/php]"
                => "Find:\n\n```\nUser-agent: *\nDisallow: /a/\n```\n\nand ``a ` b`` inline, `x()` and `echo 1;`",
            '[url=http://site.test/a b(1)]label[/url] [url]www.site.test[/url] [url]./uploads/a_b.zip[/url] [url=http://x.test]http://x.test[/url]'
                => '[label](http://site.test/a%20b%281%29) <https://www.site.test> [./uploads/a_b.zip](./uploads/a_b.zip) <http://x.test>',
            '[mail]me@site.test[/mail] [mail=me@site.test]write[/mail] [mail]broken[/mail]' => '<me@site.test> [write](mailto:me@site.test) broken',
            '[img=left alt=Shot 1]./uploads/a.png[/img] [img]http://site.test/b c.png[/img] [url=http://site.test][img]http://site.test/t.png[/img][/url]'
                => '![Shot 1](./uploads/a.png) ![](http://site.test/b%20c.png) [![](http://site.test/t.png)](http://site.test)',
            '[code=php]<?php echo "&lt;"; ?>[/code]' => "```php\n<?php echo \"<\"; ?>\n```",
            "Gap<br />\n\n\n  \n<br />\nafter" => "Gap\n\nafter",
            "Code:\n[code=]a()[/code]" => "Code:\n\n```\na()\n```",
            '[img=right alt= ]http://site.test/y.png[/img] [url=http://site.test ]x[/url] [mail = me@site.test]w[/mail] [color= red ]c[/color]'
                => '![](http://site.test/y.png) [x](http://site.test) [w](mailto:me@site.test) c',
        ];
        foreach ($cases as $from => $want) $this->assertSame($want, $this->getText($from), 'The BB text '.$from.' did not convert by the grammar');
    }

    # Tags without a Markdown equivalent keep their text, the special tags keep their tokens around a converted content, [usehtml] stays as written
    # Only balanced pairs of the grammar convert: an unpaired, crossed or misspelled tag and an empty or unsafe address are text
    #[Test]
    public function onlyTheTagsOfTheGrammarConvert(): void
    {
        $cases = [
            '[color=red]red[/color] [size=5]five[/size] [family=Arial]font[/family] [u]u[/u] [center]c[/center] [justify]j[/justify] [size=big]x[/size]'
                => 'red five font u c j \\[size=big\\]x\\[/size\\]',
            '[hide][b]h[/b][/hide] [tabs=0][tab=One][i]t[/i][/tab][/tabs] [attach=a.png align=left title=A] [block=3] [usehtml]<b>[b]raw[/b]</b>[/usehtml]'
                => '[hide]**h**[/hide] [tabs=0][tab=One]*t*[/tab][/tabs] [attach=a.png align=left title=A] [block=3] [usehtml]<b>[b]raw[/b]</b>[/usehtml]',
            '[b][i]crossed[/b][/i] [quote][b]open[/quote] [ img=left]x[/img] [li]one [li]two [hr] after'
                => "**\\[i\\]crossed**\\[/i\\]\n\n> \\[b\\]open\n\n\\[ img=left\\]x\\[/img\\] \u{2022} one \u{2022} two\n\n***\n\nafter",
            '[url=ed2k://|file|x|1|A|/]name[/url] [url]javascript:alert(1)[/url] [url=]empty[/url]' => 'name javascript:alert(1) \\[url=\\]empty\\[/url\\]',
        ];
        foreach ($cases as $from => $want) $this->assertSame($want, $this->getText($from), 'The BB text '.$from.' did not convert by the grammar');
        $seen = [];
        \getMigrateMarkdown('[b]a[/b] [b]b[/b] *05 [u]x[/u] [i]open', true, $seen);
        $this->assertSame(['b' => 2, 'smiley' => 1, 'u' => 1, 'unpaired' => 1], $seen, 'The forms a text met are not counted for the dry run');
    }

    # A text that is Markdown by origin converts its BB tags, breaks and smilies and keeps every Markdown construct, its code included, as written
    #[Test]
    public function aMarkdownTextConvertsItsTagsAlone(): void
    {
        $cases = [
            '**Kept** [Link](https://x.test) [b]bb[/b], `[b]code[/b]` *05' => "**Kept** [Link](https://x.test) **bb**, `[b]code[/b]` \u{1F603}",
            "- list\n  [b]x[/b]<br />\n\n```\n[b]fence[/b]\n```" => "- list\n  **x**\n\n```\n[b]fence[/b]\n```",
            "Rows<br />\nmore" => "Rows\\\nmore",
        ];
        foreach ($cases as $from => $want) $this->assertSame($want, $this->getText($from, false), 'The Markdown text '.$from.' was not converted alone');
        $this->assertSame("plain\r\ntext", $this->getText("plain\r\ntext", false), 'A text with nothing to convert changed');
        $this->assertSame("**a**\n\n\n\nb", $this->getText("[b]a[/b]\n\n\n\nb", false), 'The blank lines of a Markdown text were collapsed');
    }

    # The raw HTML of a 6.2 text becomes Markdown: emphasis, breaks, lists, headings, code, quotes and links, with the text between the tags escaped
    #[Test]
    public function aLegacyHtmlTextBecomesMarkdown(): void
    {
        foreach (['getMigrateBlocks', 'getMigrateLocal', 'getMigrateMarkdown', 'getMigrateHtml'] as $name) self::getFunc($name);
        $cases = [
            "<p>Text <b>bold</b>, <u>under</u>, <font color=\"red\">red</font>.<br>\nNext<br /><br />Para *05</p>" => "Text **bold**, under, red.\\\nNext\n\nPara \u{1F603}",
            '<ul><li>first *star*</li><li># no heading<ul><li>nested</li></ul></li></ul><ol><li>one</li></ol>'
                => "- first \\*star\\*\n- \\# no heading\n  \n  - nested\n\n1. one",
            '<h2>Title #1</h2><p>Use <code>a_b()</code> &amp; &lt;b&gt;</p><hr><blockquote>quote <b>x</b></blockquote><p align="center">centre</p>'
                => "## Title #1\n\nUse `a_b()` & \\<b>\n\n***\n\n> quote **x**\n\ncentre",
            '<a href="uploads/news/x.zip">Get</a> <img src="uploads/news/a.png"> <a href="http://site.test">http://site.test</a> <a href="javascript:x">bad</a>'
                => '[Get](./uploads/news/x.zip) ![](./uploads/news/a.png) <http://site.test> bad',
            '<table><tr><td>cell</td></tr></table>after [code]<b>x</b>[/code]' => "[usehtml]\n<table><tr><td>cell</td></tr></table>\n[/usehtml]\n\nafter `<b>x</b>`",
        ];
        foreach ($cases as $from => $want) $this->assertSame($want, \getMigrateHtml($from, true), 'The 6.2 text '.$from.' did not become Markdown');
    }

    # The dry run counts per column and writes nothing; the write run converts by origin, a trusted block keeps its raw HTML as Markdown and [usehtml]
    # The feed block and the field of the type text stay, a text too long for its column once escaped stays BB
    #[Test]
    public function theUnitConvertsEveryTextOnce(): void
    {
        $run = $this->getRun();
        $this->assertTrue($run['dry']['done'], $run['dry']['text']);
        $this->assertNull($run['dry']['info'], 'The dry run wrote a manifest');
        $this->assertStringContainsString('(nodes.body 1, comment.body 2, forum.body 2, forum.field 1, privat.body 1, message.body 1, newsletter.body 1,'
            .' blocks.content 1, users.sig 1, users.block 1), config/contact.php info changes', $run['dry']['text']);
        $this->assertSame('[b]Bold[/b] text', strstr($run['dry']['texts']['nodes.body #1'], "\n", true), 'The dry run converted a text');
        $want = [
            'nodes.body #1' => "**Bold** text\n- item \u{1F603}",
            'comment.body #1' => "> Q\n\n\\# not a heading \u{1F603}",
            'comment.body #2' => '**md** **x**',
            'forum.body #1' => "[a_b](http://a.test/c_d)\\\nnext & 2\\*3",
            'forum.field #1' => '{"note":"**n**","size":"[b]s[/b]"}',
            'forum.body #2' => '**Markdown** of 2026 **x**',
            'privat.body #1' => "```\n<b><x></b>\n```",
            'message.body #1' => 'C 2\\*3',
            'newsletter.body #1' => '<a@b.test>',
            'blocks.content #1' => "[Page](./page.html) **B**\n\n[usehtml]\n<table><tr><td>t</td></tr></table>\n[/usehtml]",
            'users.sig #2' => '*sig*',
        ];
        $write = $run['write'];
        $this->assertTrue($write['done'], $write['text']);
        $this->assertSame($want, $write['texts']);
        $this->assertSame(['[b]feed[/b]', 40000, '**Наши координаты**'], [$write['feed'], $write['long'], $write['contact']]);
        $this->assertSame(['verified', ['users.block #3']], [$write['info']['state'], $write['info']['long']]);
        $this->assertStringContainsString('kept as BB because too long for their column: users.block #3', $write['text']);
    }

    # A verified unit is skipped and a later dry run counts nothing; a stopped run keeps a committed batch and writes one that rolled back, so no text converts twice
    #[Test]
    public function aStoppedRunNeverConvertsATextTwice(): void
    {
        $run = $this->getRun();
        $this->assertStringContainsString('turned into Markdown by an earlier run', $run['repeat']['text']);
        $this->assertSame($run['write']['texts'], $run['repeat']['texts']);
        $this->assertStringContainsString('the unit already ran (state verified)', $run['again']['text'], 'A dry run counted the converted texts again');
        foreach (['committed', 'rolled'] as $name) {
            $this->assertTrue($run[$name]['done'], $run[$name]['text']);
            $this->assertSame($run['write']['texts'], $run[$name]['texts'], 'The batch that '.$name.' was converted again or lost');
            $this->assertSame($run['write']['contact'], $run[$name]['contact'], 'The contact text was converted twice');
            $this->assertSame($run['write']['info']['done'], $run[$name]['info']['done'], 'The batch that '.$name.' left the counts of the manifest wrong');
            $this->assertArrayNotHasKey('batch', $run[$name]['info']);
        }
    }
}
