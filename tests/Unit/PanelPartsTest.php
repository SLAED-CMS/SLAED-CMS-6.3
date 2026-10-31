<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

# The series on the whole panel: the meter of a real limit, the mark of a cell cut short, the list inside its window and the hint of a note
final class PanelPartsTest extends TestCase
{
    private static array $data = [];

    private static Template $view;

    public static function setUpBeforeClass(): void
    {
        $script = dirname(__DIR__).'/Support/panel_probe.php';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' 2>&1');
        self::$data = (array)json_decode($out, true);
        self::assertNotSame([], self::$data, 'The probe did not return JSON: '.$out);
        if (!class_exists('Template', false)) require_once dirname(__DIR__, 2).'/core/classes/template.php';
        if (!defined('_DETAILS')) define('_DETAILS', 'Details');
        self::$view = new Template('admin');
    }

    # One answer of the probe, or one fragment, as a document
    private static function getDoc(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.$html.'</body></html>', LIBXML_NOERROR);
    }

    # The list of a section in RSS stays: the categories of its form are built on the server for the section it names
    #[Test]
    public function noListSubmitsItself(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge([$root.'/core/helpers.php', $root.'/core/admin.php'], glob($root.'/admin/modules/*.php') ?: [],
            glob($root.'/modules/*/admin/index.php') ?: [], array_diff(glob($root.'/modules/*/index.php') ?: [], [$root.'/modules/rss/index.php']));
        $this->assertGreaterThan(20, count($files));
        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression('/on-?change\s*=\s*\\\\?["\'][^"\']*submit/i', (string)file_get_contents($file),
                $file.': a list applies by its button, or in the panel by the field is_now of list-filter');
        }
    }

    #[Test]
    public function aStripOfTabsIsATablistOfTabs(): void
    {
        $script = (string)file_get_contents(dirname(__DIR__, 2).'/public/plugins/system/slaed.js');
        $from = (int)strpos($script, 'function initTabGroup(');
        $part = substr($script, $from, (int)strpos($script, 'function applyFieldShow(') - $from);
        $this->assertStringContainsString("list[0].closest('.sl-tabs-nav')", $part, 'On a module head the strip is the group itself, which querySelector never finds');
        $this->assertStringContainsString("cells[c].setAttribute('role', 'presentation')", $part, 'A tablist owns tabs, so its li gives up the role of a list item');
        $this->assertStringContainsString('list[move].focus()', $part, 'An arrow moves the focus with the choice, or the next arrow starts from the same tab');
    }

    #[Test]
    public function anEmptyOrShortValueCarriesNoMark(): void
    {
        $this->assertSame([], self::$data['cell_none']);
        $this->assertSame(['title_text' => str_repeat('x', 15)], self::$data['cell_short']);
        $this->assertSame(['title_text'], self::$data['cell_wide'], 'The bound counts characters, not bytes');
    }

    #[Test]
    public function aLongValueOpensWholeBehindItsMarkInsteadOfATitle(): void
    {
        $this->assertArrayNotHasKey('title_text', self::$data['cell_long'], 'A mark and a title on one cell would show twice');
        $doc = self::getDoc(self::$data['cell_long']['prefix_html']);
        $this->assertSame('<b>'.str_repeat('x', 15), $doc->querySelector('.sl-tip > .sl-float-panel[role="tooltip"]')->textContent);
        $this->assertNull($doc->querySelector('b'));
        $this->assertStringEndsWith(': L<', $doc->querySelector('.sl-tip > button')->getAttribute('aria-label'));
    }

    #[Test]
    public function aMeterTakesItsToneFromItsShareUnlessItNamesOne(): void
    {
        $low = self::getDoc(self::$data['meter_low']);
        $bar = $low->querySelector('.sl-meter-bar.sl-knob-ok[role="meter"]');
        $this->assertSame('25', $bar->getAttribute('aria-valuenow'));
        $this->assertSame('L<', $low->querySelector('.sl-meter-head > span')->textContent);
        $this->assertStringContainsString('25', $low->querySelector('.sl-meter-head > b')->textContent);
        $this->assertSame('N<', $low->querySelector('.sl-meter-note')->textContent);
        $over = self::getDoc(self::$data['meter_over'])->querySelector('.sl-meter-bar.sl-knob-danger');
        $this->assertSame('100', $over->getAttribute('aria-valuenow'), 'A share over its limit stops at the end of the bar');
        $this->assertSame('0', self::getDoc(self::$data['meter_none'])->querySelector('.sl-meter-bar')->getAttribute('aria-valuenow'), 'Nothing used is no sliver for a reader');
        $this->assertSame('1', self::getDoc(self::$data['meter_tiny'])->querySelector('.sl-meter-bar')->getAttribute('aria-valuenow'), 'A trace of use stays visible');
        $this->assertNotNull(self::getDoc(self::$data['meter_tone'])->querySelector('.sl-meter-bar.sl-knob-ok'), 'A progress at its end is no alarm');
    }

    #[Test]
    public function aMeterInARowShowsItsFigureAndLeavesTheNameToItsColumn(): void
    {
        $doc = self::getDoc(self::$data['meter_row']);
        $this->assertNotNull($doc->querySelector('.sl-meter.sl-meter-row'));
        $this->assertNull($doc->querySelector('.sl-meter-head > span'));
        $this->assertSame('1 / 4', $doc->querySelector('.sl-meter-head > b')->textContent);
        $this->assertSame('L', $doc->querySelector('.sl-meter-bar')->getAttribute('aria-label'));
    }

    #[Test]
    public function aListStandsInsideItsWindowUnderItsTiles(): void
    {
        $doc = self::getDoc(self::$data['list_pane']);
        $this->assertSame('T<', $doc->querySelector('#sl-list > section.sl-pane h2.sl-pane-title')->textContent);
        $this->assertNotNull($doc->querySelector('#head + #note + section.sl-pane > .sl-pane-cap > i.bi-database'));
        $this->assertSame('M', $doc->querySelector('.sl-pane-meta')->textContent);
        $this->assertNotNull($doc->querySelector('.sl-pane > .sl-pane-body > #tiles + .sl-table-shell table'));
        $bare = self::getDoc(self::$data['list_bare']);
        $this->assertNull($bare->querySelector('.sl-pane'));
        $this->assertNotNull($bare->querySelector('#sl-list > .sl-table-shell table'));
    }

    #[Test]
    public function aNoteKeepsTheHintItIsGiven(): void
    {
        $note = self::getDoc(self::$view->getHtmlFrag('popover', ['label_text' => 'L', 'title_text' => 'T<']))->querySelector('span.sl-note');
        $this->assertSame('T<', $note->getAttribute('title'));
        $this->assertFalse(self::getDoc(self::$view->getHtmlFrag('popover', ['label_text' => 'L']))->querySelector('span.sl-note')->hasAttribute('title'));
    }
}
