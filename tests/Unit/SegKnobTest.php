<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The segmented switch and the travelling plate of its chosen cell, read off the shipped script, the two themes and the mode fragment without writing to the site
final class SegKnobTest extends TestCase
{
    private const THEMES = ['admin', 'lite'];
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

    # A rail handed a place stands on it still once and travels from the next frame; a resize observer, which answers at once, leaves it standing there
    #[Test]
    public function aHandedPlaceStandsStillOnce(): void
    {
        $part = $this->getPart(self::SITEJS, 'function setKnobs(node)', 1900);
        $note = 'The plate is not held still on the place it was handed, so it jumps instead of travelling from it';
        $this->assertStringContainsString("rail.setAttribute('data-sl-knob-still', '');", $part, $note);
        $note = 'The still plate is never released, so it never travels to the chosen cell';
        $this->assertMatchesRegularExpression("/requestAnimationFrame\\(function \\(\\) \\{\\s+rail\\.removeAttribute\\('data-sl-knob-still'\\);/", $part, $note);
        $note = 'The observers move a plate that still stands on the place of the page before, so the travel is lost';
        $this->assertStringContainsString("if (!rail.hasAttribute('data-sl-knob-still')) setKnobPlace(rail);", $part, $note);
        $note = 'A rail already set up is set up again, and its plate jumps back to a handed place';
        $this->assertStringContainsString("!rail.hasAttribute('data-sl-knob-ready')", $part, $note);
        $from = $this->getPart(self::SITEJS, 'function getKnobFrom()', 600);
        $note = 'A place is taken back on another path or after the five seconds, so it wanders into an unrelated page';
        $this->assertStringContainsString('from.path !== window.location.pathname', $from, $note);
        $this->assertStringContainsString('> 5000', $from, $note);
        $note = 'A hand-over is read more than once, so a later page starts its plate on a stale place';
        $this->assertStringContainsString('window.sessionStorage.removeItem(knobkey);', $from, $note);
    }

    # The place of every rail is kept when the page is left and when an htmx swap replaces a part, and the swapped part sets its rails up again
    #[Test]
    public function thePlaceIsKeptOnLeavingAndTakenAfterTheSwap(): void
    {
        $js = $this->getFile(self::SITEJS);
        $note = 'A page that is left keeps no place, so the plate of the next page appears instead of travelling';
        $this->assertMatchesRegularExpression("/addEventListener\\('pagehide', function \\(\\) \\{\\s+setKnobKeep\\(document\\);/", $js, $note);
        $note = 'An htmx swap keeps no place of the rail it replaces';
        $keep = 'if (event.detail && event.detail.target) setKnobKeep(event.detail.target);';
        $this->assertMatchesRegularExpression("/addEventListener\\('htmx:beforeSwap', function \\(event\\) \\{\\s+".preg_quote($keep, '/').'/', $js, $note);
        $after = $this->getPart(self::SITEJS, "document.addEventListener('htmx:afterSwap', function (event) {\n        setTableSort(event.target);", 1200);
        $note = 'A rail an htmx swap brings in is never set up, so its plate stays away';
        $this->assertStringContainsString('setKnobs(', $after, $note);
        $start = $this->getPart(self::SITEJS, 'function setSlaedUi()', 900);
        $note = 'The rails of the page are never set up at start';
        $this->assertStringContainsString('setKnobs(document);', $start, $note);
        $note = 'An outer swap replaces the rail itself, and the place of the rail is not kept because only its children are looked at';
        $this->assertStringContainsString("root.matches('[data-sl-knob]')", $this->getPart(self::SITEJS, 'function getKnobRails(node)', 400), $note);
    }

    # Both themes carry the one component and the one mechanism; only the site header carries the mode modifier, on the fragment that prints the rail
    #[Test]
    public function bothThemesCarryTheSwitch(): void
    {
        foreach (self::THEMES as $name) {
            $css = $this->getFile('public/templates/'.$name.'/assets/css/theme.css');
            foreach (['.sl-seg {', '.sl-seg-cell {', '.sl-seg-knob {', '.sl-seg-run {', '.sl-seg-view {', '.sl-seg-pager {'] as $rule) {
                $note = 'Theme '.$name.' is missing '.$rule;
                $this->assertStringContainsString($rule, $css, $note);
            }
            $note = 'The plate of theme '.$name.' does not stand on the place the script measured';
            $this->assertStringContainsString('[data-sl-knob][data-sl-knob-ready] > [data-sl-knob-mark] {', $css, $note);
            $note = 'The plate of theme '.$name.' moves for a reader who asked for less motion';
            $move = '[data-sl-knob][data-sl-knob-ready]:not([data-sl-knob-still])';
            $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s+'.preg_quote($move, '/').'/', $css, $note);
            $base = $this->getFile('public/templates/'.$name.'/assets/css/base.css');
            $note = 'Theme '.$name.' does not declare the tokens of the plain switch in its API block';
            $this->assertStringContainsString('--sl-seg-knob-bg:', $base, $note);
        }
        $lite = $this->getFile('public/templates/lite/assets/css/theme.css');
        $note = 'The site header switch has no face of its own';
        $this->assertStringContainsString('.sl-seg-mode {', $lite, $note);
        $note = 'The step ladder of the old knob is back beside the measured one';
        $this->assertStringNotContainsString('sl-mode-knob', $lite, $note);
        $tpl = $this->getFile('public/templates/lite/fragments/mode-switch.html');
        $note = 'The mode switch is not a rail the script knows, so its plate never travels';
        $this->assertStringContainsString('<span class="sl-seg sl-seg-mode" data-sl-knob="mode">', $tpl, $note);
        $note = 'The plate of the mode switch is not marked, so the sheet has nothing to move';
        $this->assertStringContainsString('<span class="sl-seg-knob" data-sl-knob-mark aria-hidden="true"></span>', $tpl, $note);
    }

    # Every link to the top is a plain anchor scrolled by the sheet, so no script and no inline handler stand behind it
    #[Test]
    public function theLinkToTheTopIsAPlainAnchor(): void
    {
        $note = 'The script still carries a scroll of its own for the link to the top';
        $this->assertStringNotContainsString('window.Upper', $this->getFile(self::SITEJS), $note);
        $note = 'The panel link still prints an inline handler for the link to the top';
        $this->assertStringNotContainsString('is_upper', $this->getFile('public/templates/admin/fragments/link.html'), $note);
        $note = 'The footer link to the top does not point at the top of the page';
        $this->assertStringContainsString("'top_link' => ['href' => '#top'", $this->getFile('core/system.php'), $note);
        foreach (self::THEMES as $name) {
            $base = $this->getFile('public/templates/'.$name.'/assets/css/base.css');
            $note = 'Theme '.$name.' does not scroll smoothly, or scrolls so for a reader who asked for less motion';
            $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: no-preference\) \{\s+html \{\s+scroll-behavior: smooth;/', $base, $note);
        }
    }
}
