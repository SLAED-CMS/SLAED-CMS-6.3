<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The migration of update.php rewrites direct addresses of the own folder into attachments and moves no file (docs/1-FILES-2026.md, batch 3)
final class UpdateAttachTest extends TestCase
{
    private static string $code = '';

    # The source of update.php, read once
    private static function getCode(): string
    {
        return self::$code !== '' ? self::$code : self::$code = (string)file_get_contents(dirname(__DIR__, 2).'/public/update.php');
    }

    # Load getMigrateAttach() alone from update.php, whose top level runs the update, by cutting the function out of its tokens
    private static function getAttach(): void
    {
        if (function_exists('getMigrateAttach')) return;
        $code = self::getCode();
        $from = strpos($code, 'function getMigrateAttach(');
        $tokens = token_get_all('<?php '.substr($code, (int)$from));
        $text = '';
        $depth = 0;
        foreach ($tokens as $one) {
            $part = is_array($one) ? $one[1] : $one;
            $text .= $part;
            if (in_array($part, ['{', '${'], true) || (is_array($one) && $one[0] === T_CURLY_OPEN)) $depth++;
            if ($part === '}' && --$depth === 0) break;
        }
        $file = sys_get_temp_dir().'/slaed_update_attach_'.getmypid().'.php';
        file_put_contents($file, $text."\n");
        require $file;
        unlink($file);
    }

    # Convert one text of the 6.2 module news, whose folder holds every name but missing.png
    private function getText(string $text, int $nid = 0, string $dir = 'news'): string
    {
        self::getAttach();
        $had = array_key_exists('conf', $GLOBALS) && is_array($GLOBALS['conf']) && array_key_exists('homeurl', $GLOBALS['conf']);
        $was = $had ? $GLOBALS['conf']['homeurl'] : null;
        $GLOBALS['conf']['homeurl'] = 'https://slaed.net';
        try {
            return \getMigrateAttach($text, $dir, $nid, fn(string $rel): bool => $rel !== 'missing.png');
        } finally {
            if ($had) $GLOBALS['conf']['homeurl'] = $was;
            else unset($GLOBALS['conf']['homeurl']);
        }
    }

    # An image becomes the full-size form with its alignment and its alternative text as the title; a link around its thumb becomes the thumbnail form
    # A link around another image keeps the image alone in full size, a link becomes an attachment titled by its label, a bare link or one labelled by its address titled title
    # A Markdown image takes the same forms as [img], and the forum folder converts like the folder of a module
    #[Test]
    public function everyFormOfTheOwnFolderBecomesAnAttachment(): void
    {
        $cases = [
            '[img=left alt=SLAED CMS 2]./uploads/news/a-1.gif[/img]' => '[attach=a-1.gif align=left title=SLAED CMS 2 size=full]',
            '[img]uploads/news/b.png[/img]' => '[attach=b.png align=none title=title size=full]',
            '[img alt=Was: (1)]http://www.slaed.net/uploads/news/c.png[/img]' => '[attach=c.png align=none title=Was 1 size=full]',
            '[url=./uploads/news/d.png][img]./uploads/news/thumb/d.png[/img][/url]' => '[attach=d.png align=none title=title]',
            '[url=./uploads/news/e.png][img=center alt=E]./uploads/news/e.jpg[/img][/url]' => '[attach=e.jpg align=center title=E size=full]',
            '[url=./uploads/news/f.png]Скачать[/url]' => '[attach=f.png align=none title=Скачать]',
            '[url]/uploads/news/g.zip[/url]' => '[attach=g.zip align=none title=title]',
            '[img]./uploads/news/a%20b.png[/img]' => '[attach=a b.png align=none title=title size=full]',
            '![Shot one](uploads/news/h.png)' => '[attach=h.png align=none title=Shot one size=full]',
            '![](/uploads/news/thumb/i.png)' => '[attach=i.png align=none title=title]',
            '[url=http://www.slaed.net/uploads/news/j.zip]http://www.slaed.net/uploads/news/j.zip[/url]' => '[attach=j.zip align=none title=title]',
        ];
        foreach ($cases as $from => $want) $this->assertSame($want, $this->getText($from), 'The form '.$from.' was not converted');
        $this->assertSame('[attach=a-1.gif align=left title=A size=full]', $this->getText('[img=left alt=A]./uploads/archive/news/a-1.gif[/img]', 0, 'archive/news'));
        $this->assertSame('[attach=k.gif align=center title=K size=full]', $this->getText('[img=center alt=K]uploads/forum/k.gif[/img]', 0, 'forum'));
        require_once BASE_DIR.'/core/classes/parser.php';
        $tag = (new \ReflectionClassConstant(\Parser::class, 'ATTTAG'))->getValue();
        preg_match_all($tag, implode(' ', $cases), $mm);
        $this->assertSame(['a-1.gif', 'b.png', 'c.png', 'd.png', 'e.jpg', 'f.png', 'g.zip', 'a b.png', 'h.png', 'i.png', 'j.zip'], $mm[1],
            'The parser does not read every converted attachment');
    }

    # A missing file, a name outside the attachment grammar, a sub folder, another folder, another host and a link around markup stay exactly as they were
    # An image inside a link to elsewhere stays, because an attachment is a link of its own, and code and raw blocks keep the examples they quote
    #[Test]
    public function anAddressThatCannotBeAnAttachmentStays(): void
    {
        $keep = [
            '[url=https://example.com][img]./uploads/news/a-1.gif[/img][/url]',
            '[url=https://example.com]Banner [img=left alt=B]./uploads/news/b.png[/img][/url]',
            '[code][img]./uploads/news/a-1.gif[/img][/code]',
            '[php]$a = \'[url]/uploads/news/g.zip[/url]\';[/php]',
            '[img]./uploads/news/missing.png[/img]',
            '[img]./uploads/news/x%28y%29.png[/img]',
            '[img]./uploads/news/sub/z.png[/img]',
            '[img]./uploads/forum/a-1.gif[/img]',
            '[img]http://other.net/uploads/news/c.png[/img]',
            '[url=./uploads/news/f.png][b]Bold[/b][/url]',
            'See uploads/news/a-1.gif here',
        ];
        foreach ($keep as $text) $this->assertSame($text, $this->getText($text), 'The address '.$text.' was rewritten');
        $mix = '[code][img]./uploads/news/b.png[/img][/code] [img]./uploads/news/b.png[/img] [url=https://example.com][img]./uploads/news/b.png[/img][/url]';
        $want = '[code][img]./uploads/news/b.png[/img][/code] [attach=b.png align=none title=title size=full] [url=https://example.com][img]./uploads/news/b.png[/img][/url]';
        $this->assertSame($want, $this->getText($mix), 'A protected region was rewritten or not restored around a converted image');
        $odd = $this->getText("a\x01b\x017\x01 [img]./uploads/news/b.png[/img]");
        $this->assertSame("a\x01b\x017\x01 [attach=b.png align=none title=title size=full]", $odd, 'A stray marker byte broke the text');
    }

    # Inside [usehtml] the raw HTML stays and a source of the own folder becomes the file address of the material; outside it and without a material nothing changes
    #[Test]
    public function aSourceInsideRawHtmlBecomesTheFileAddress(): void
    {
        $raw = '[usehtml]<td><img src=&#034;./uploads/news/check.gif&#034; alt=&#034;OK&#034;><img src="uploads/news/thumb/d.png"></td>[/usehtml]';
        $want = '[usehtml]<td><img src=&#034;index.php?go=file&own=node&id=7&key=check.gif&#034; alt=&#034;OK&#034;>'
            .'<img src="index.php?go=file&own=node&id=7&key=d.png&thumb=1"></td>[/usehtml]';
        $this->assertSame($want, $this->getText($raw, 7));
        $this->assertSame($raw, $this->getText($raw), 'Without a material the raw HTML was rewritten');
        $this->assertSame('<img src="./uploads/news/check.gif">', $this->getText('<img src="./uploads/news/check.gif">', 7), 'A source outside [usehtml] was rewritten');
    }

    # update.php moves, copies and renames no upload: the working copy of the files, the archive, the outer addresses and the renamed attachment names are gone
    #[Test]
    public function theMigrationMovesNoFile(): void
    {
        $code = self::getCode();
        foreach (['setMigrateFiles', 'setMigrateOuter', 'setMigrateMove', 'getMigrateNames', 'getMigrateKeep', 'getMigrateText', 'uploads/archive', "/files/'"] as $gone) {
            $this->assertStringNotContainsString($gone, $code, 'update.php still carries '.$gone);
        }
        $this->assertStringContainsString("'#'.\$node['id'].': rename the file '", $code, 'A resource of a refused spelling no longer tells the operator to rename its file');
    }
}
