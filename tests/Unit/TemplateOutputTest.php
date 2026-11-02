<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

require_once dirname(__DIR__, 2).'/core/classes/template.php';

# The shape of the markup the engine writes: a control line leaves nothing, a line end survives a tag, a lone insert takes the indent of its line
final class TemplateOutputTest extends TestCase
{
    private static ?object $tpl = null;

    # A template double that compiles inline source into a private cache directory, so no theme file and no site cache take part
    private static function getTemplate(): object
    {
        return self::$tpl ??= new class () extends Template {
            public function __construct()
            {
                $this->cache = sys_get_temp_dir().'/slaed_tpl_output_'.getmypid();
            }

            public function getText(string $code, array $data = []): string
            {
                return $this->getView($this->filterCode($code), $data, true);
            }
        };
    }

    public static function tearDownAfterClass(): void
    {
        $dir = sys_get_temp_dir().'/slaed_tpl_output_'.getmypid();
        foreach (glob($dir.'/*') ?: [] as $file) unlink($file);
        if (is_dir($dir)) rmdir($dir);
    }

    # A line holding only control tags or a note is gone with its indent and line end
    #[Test]
    public function aControlLineLeavesNothing(): void
    {
        $code = "<ul>\n  {# list #}\n  {% for item in items %}\n  <li>{{ item }}</li>\n  {% endfor %}\n</ul>\n";
        $this->assertSame("<ul>\n  <li>a</li>\n  <li>b</li>\n</ul>\n", self::getTemplate()->getText($code, ['items' => ['a', 'b']]));
    }

    # A tag closing a line keeps the line end PHP would swallow, and the tag ending the source still ends the output
    #[Test]
    public function aTagKeepsItsLineEnd(): void
    {
        $code = "<p>{{ text }}</p>{% if more %}<b>more</b>{% endif %}\n<p>next</p>{% if more %}!{% endif %}\n";
        $this->assertSame("<p>a</p><b>more</b>\n<p>next</p>!", self::getTemplate()->getText($code, ['text' => 'a', 'more' => true]));
    }

    # A lone insert is indented as a whole, an empty one leaves no line, and pre and textarea keep their text
    #[Test]
    public function aLoneInsertTakesItsIndent(): void
    {
        $code = "<div>\n    {% if body_html %}\n    {{{ body_html }}}\n    {% endif %}\n    {% if none_html %}{{{ none_html }}}{% endif %}\n</div>\n";
        $body = "<ul>\n  <li>a</li>\n</ul>\n<pre>x\n  y</pre>\n<textarea>p\nq</textarea>\n<input value=\"r\ns\">\n";
        $want = "<div>\n    <ul>\n      <li>a</li>\n    </ul>\n    <pre>x\n  y</pre>\n    <textarea>p\nq</textarea>\n    <input value=\"r\ns\">\n</div>\n";
        $this->assertSame($want, self::getTemplate()->getText($code, ['body_html' => $body, 'none_html' => '']));
    }
}
