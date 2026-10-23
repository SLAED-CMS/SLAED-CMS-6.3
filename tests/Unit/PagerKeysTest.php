<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The arrow keys of the pager and the tip that explains them, read off the shipped script, the two themes and the six locales without writing to the site
final class PagerKeysTest extends TestCase
{
    private const THEMES = ['admin', 'lite'];
    private const LOCALES = ['de', 'en', 'fr', 'pl', 'ru', 'uk'];
    private const SITEJS = 'public/plugins/system/slaed.js';

    private static array $files = [];

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Return a slice of a file from a landmark, so a claim is made about one handler and not about the file around it
    private function getPart(string $path, string $mark, int $len): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, $mark);
        $this->assertNotFalse($from, $mark.' is gone from '.$path);
        return substr($code, $from, $len);
    }

    # The listener leaves a key to whoever already handled it and to every place that owns the arrows: a field, an editable, a tab, an open window, the file desk
    #[Test]
    public function theKeysStandAsideWhereTheArrowsBelongToSomeoneElse(): void
    {
        $part = $this->getPart(self::SITEJS, 'function setPagerKeys()', 1400);
        $note = 'A key another handler already took pages the list as well';
        $this->assertStringContainsString('event.defaultPrevented', $part, $note);
        $note = 'A key with a modifier pages the list, so a shortcut of the browser or of an editor turns the page';
        foreach (['event.altKey', 'event.ctrlKey', 'event.metaKey', 'event.shiftKey'] as $mod) $this->assertStringContainsString($mod, $part, $note);
        $note = 'The arrows of a field, a select or an editable turn the page instead of moving the caret';
        $this->assertMatchesRegularExpression('/closest\(\'input, textarea, select[^\']*\'\)/', $part, $note);
        $this->assertStringContainsString('isContentEditable', $part, $note);
        $note = 'The arrows that walk a tab list turn the page as well';
        $this->assertStringContainsString('[role="tab"], [role="tablist"]', $part, $note);
        $note = 'A key pressed inside an open window, the file manager and the gallery among them, turns the page behind it';
        $this->assertStringContainsString('dialog', $part, $note);
        $note = 'The keys that walk the file desk of the panel turn the page as well';
        $this->assertStringContainsString('.sl-fm-split', $part, $note);
    }

    # The keys follow the first pager on show outside a window and click its link, so an htmx link swaps its part as it does for the pointer
    #[Test]
    public function theKeysClickTheLinkOfTheFirstPagerOnShow(): void
    {
        $part = $this->getPart(self::SITEJS, 'function setPagerKeys()', 1400);
        $note = 'The keys do not follow the arrow links of the pager, which carry rel="prev" and rel="next"';
        $this->assertStringContainsString('a[rel="\' + rel + \'"]', $part, $note);
        $note = 'The keys navigate by themselves, so an htmx pager reloads the page instead of swapping its part';
        $this->assertStringContainsString('link.click();', $part, $note);
        $this->assertStringNotContainsString('location', $part, $note);
        $note = 'A hidden pager or one inside a window owns the keys, so the arrows page a list the reader does not see';
        $this->assertStringContainsString('getClientRects().length', $part, $note);
        $note = 'The keys are never wired';
        $this->assertStringContainsString('setPagerKeys();', $this->getPart(self::SITEJS, 'function setSlaedUi()', 1200), $note);
    }

    # Both themes print the one fragment with the "i" and its tip, hide it on a touch screen, and the arrows name their keys
    #[Test]
    public function bothThemesExplainTheKeys(): void
    {
        $lite = $this->getFile('public/templates/lite/fragments/pager.html');
        $note = 'The pager fragments of the two themes differ, though the mechanism and the classes are one';
        $this->assertSame($lite, $this->getFile('public/templates/admin/fragments/pager.html'), $note);
        $note = 'The pager does not take its key tip from the fragment of its theme';
        $this->assertStringContainsString("{% include 'fragments/pager-keys.html' %}", $lite, $note);
        foreach (self::THEMES as $name) {
            $keys = $this->getFile('public/templates/'.$name.'/fragments/pager-keys.html');
            $note = 'The key tip of theme '.$name.' is no tip of the system';
            $this->assertStringContainsString('class="sl-tip sl-float sl-pager-keys"', $keys, $note);
            $this->assertStringContainsString('<div class="sl-float-panel sl-pager-tip" role="tooltip">', $keys, $note);
            $note = 'A key of the tip of theme '.$name.' is not drawn as an icon labelled for a screen reader';
            $this->assertStringContainsString('<kbd><i class="bi bi-arrow-left" role="img" aria-label="{{ _KEYLEFT }}"></i></kbd>', $keys, $note);
            $this->assertStringContainsString('<kbd><i class="bi bi-arrow-right" role="img" aria-label="{{ _KEYRIGHT }}"></i></kbd>', $keys, $note);
            $note = 'The keys of the tip of theme '.$name.' stand in a definition list, which each theme lays out as label and value';
            $this->assertStringNotContainsString('<dl', $keys, $note);
        }
        $link = $this->getFile('public/templates/lite/fragments/pager-link.html');
        $note = 'An arrow link does not name the key that follows it';
        $this->assertStringContainsString('aria-keyshortcuts="{{ key_name }}"', $link, $note);
        foreach (self::THEMES as $name) {
            $css = $this->getFile('public/templates/'.$name.'/assets/css/theme.css');
            $note = 'Theme '.$name.' shows the "i" on a touch screen, which has no arrow keys to explain';
            $this->assertMatchesRegularExpression('/@media \(pointer: coarse\) \{\s+\.sl-pager \.sl-pager-keys \{\s+display: none;/', $css, $note);
            $note = 'Theme '.$name.' has no face for the keys of the tip';
            $this->assertMatchesRegularExpression('/^\.sl-pager-tip kbd(,\n[^{]+)? \{/m', $css, $note);
        }
        foreach (self::LOCALES as $loc) {
            $lang = $this->getFile('lang/'.$loc.'.php');
            foreach (['_PAGEKEYS', '_PAGEPREV', '_PAGENEXT', '_PAGEKEYNOTE', '_KEYLEFT', '_KEYRIGHT'] as $name) {
                $this->assertStringContainsString("define('".$name."',", $lang, 'Locale '.$loc.' is missing '.$name);
            }
        }
    }
}
