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

# The parts of the series in the theme admin that the statistic and monitor screens share: their keys and their escaping
final class SeriesPartsTest extends TestCase
{
    private static Template $view;

    public static function setUpBeforeClass(): void
    {
        if (!class_exists('Template', false)) require_once dirname(__DIR__, 2).'/core/classes/template.php';
        if (!defined('_DETAILS')) define('_DETAILS', 'Details');
        self::$view = new Template('admin');
    }

    # Render one fragment and answer it as a document
    private static function getPart(string $name, array $data): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.self::$view->getHtmlFrag($name, $data).'</body></html>', LIBXML_NOERROR);
    }

    #[Test]
    public function windowTakesLampsOrIconMetaAndTwoHalves(): void
    {
        $lamps = self::getPart('pane', ['title' => 'A<b>', 'content_html' => '<p id="in"></p>']);
        $this->assertCount(3, $lamps->querySelectorAll('.sl-pane-lamps > i'));
        $this->assertSame('A<b>', $lamps->querySelector('h2.sl-pane-title')->textContent);
        $this->assertNotNull($lamps->querySelector('.sl-pane-body:not(.sl-pane-two) > #in'));
        $this->assertNull($lamps->querySelector('[data-sl-live-box]'));
        $full = self::getPart('pane', [
            'title' => 'B', 'icon' => 'database', 'meta' => 'M<', 'is_fill' => true, 'live_html' => '<div class="sl-live-chip"></div>',
            'content_html' => '<p id="one"></p>', 'right_html' => '<p id="two"></p>',
        ]);
        $this->assertNull($full->querySelector('.sl-pane-lamps'));
        $this->assertNotNull($full->querySelector('.sl-pane.sl-pane-fill[data-sl-live-box] .sl-pane-cap > i.bi-database'));
        $this->assertSame('M<', $full->querySelector('.sl-pane-meta')->textContent);
        $this->assertNotNull($full->querySelector('.sl-pane-two > div > #one'));
        $this->assertNotNull($full->querySelector('.sl-pane-two > div > #two'));
    }

    #[Test]
    public function aTileCarriesItsChangeBesideItsNote(): void
    {
        $tile = self::getPart('metric-list', ['items' => [[
            'label' => 'A', 'value' => '1 200', 'num' => 1200, 'note' => 'N<', 'delta' => '+12%', 'dir' => 'up', 'arrow' => 'arrow-up-right', 'delta_tip' => 'T<',
        ]]]);
        $this->assertSame('1200', $tile->querySelector('.sl-metric-value > [data-sl-figure]')->getAttribute('data-sl-figure'));
        $this->assertSame('N<', $tile->querySelector('[data-sl-metric-note]')->textContent);
        $delta = $tile->querySelector('small > .sl-metric-delta-up[data-sl-metric-delta]');
        $this->assertSame('T<', $delta->getAttribute('title'));
        $this->assertNotNull($delta->querySelector('i.bi-arrow-up-right'));
        $this->assertSame('+12%', $delta->lastElementChild->textContent);
        $this->assertNull(self::getPart('metric-list', ['items' => [['label' => 'B', 'value' => '2', 'note' => 'N']]])->querySelector('[data-sl-metric-delta]'));
    }

    # The cockpit takes its rings, its strip and its parts as data: the home and the statistic share this one markup
    #[Test]
    public function theCockpitTakesItsPartsAsData(): void
    {
        $view = \Dom\HTMLDocument::createFromString('<!doctype html><html><body>'.self::$view->getHtmlPart('cockpit', [
            'tone' => 'ok',
            'cap_icon' => 'graph-up',
            'cap_text' => 'C<',
            'rings' => [['key' => 'human', 'label' => 'L<', 'title' => 'T<']],
            'strip' => [['key' => 'usr', 'icon' => 'person', 'label' => 'U<', 'unit' => ''], ['key' => 'gen', 'icon' => 'stopwatch', 'label' => 'G', 'unit' => 'ms']],
            'state_html' => '<span id="state"></span>',
            'lamp_html' => '<div id="lamp"></div>',
            'nav_html' => '<div id="nav"></div>',
        ]).'</body></html>', LIBXML_NOERROR);
        $this->assertNull($view->querySelector('[data-sl-cockpit]'), 'A page with its own poll carries no address of the home poll');
        $this->assertSame('C<', $view->querySelector('#sl-cockpit-cap')->textContent);
        $this->assertNotNull($view->querySelector('#sl-cockpit-cap > i.bi-graph-up'));
        $this->assertNotNull($view->querySelector('.sl-cockpit-top > #state'));
        $this->assertNotNull($view->querySelector('.sl-cockpit-top > #nav'));
        $this->assertNotNull($view->querySelector('.sl-cockpit-gauges > #lamp'));
        $this->assertNull($view->querySelector('[data-sl-cockpit-pass]'), 'A lamp of its own replaces the lamp of the checks');
        $ring = $view->querySelector('[data-sl-gauge="human"]');
        $this->assertSame('L<', $ring->getAttribute('aria-label'));
        $this->assertSame('T<', $view->querySelector('#sl-cockpit-tip-human')->textContent);
        $this->assertCount(2, $view->querySelectorAll('[data-sl-strip] [data-sl-strip-val]'));
        $this->assertSame('U<', $view->querySelector('[data-sl-strip="usr"] > span')->textContent);
        $this->assertStringContainsString(' ms ', $view->querySelector('[data-sl-strip="gen"]')->textContent);
        $this->assertStringNotContainsString('href', $view->querySelector('template[data-sl-cockpit-row]')->innerHTML);
    }

    #[Test]
    public function groundHoldsItsLinesAndRowsTheirWindows(): void
    {
        $ground = self::getPart('pane-ground', ['content_html' => '<p id="in"></p>']);
        $this->assertNotNull($ground->querySelector('.sl-ground > .sl-ground-lines[aria-hidden="true"] + #in'));
        $split = self::getPart('pane-row', ['content_html' => '<p id="big"></p>', 'side_html' => '<p id="small"></p>']);
        $this->assertNotNull($split->querySelector('.sl-pane-split > #big + .sl-pane-side > #small'));
        $this->assertNotNull(self::getPart('pane-row', ['content_html' => '<p id="in"></p>'])->querySelector('.sl-pane-trio > #in'));
    }

    #[Test]
    public function tileDrawsDotAndIconOnlyWithTheirKeys(): void
    {
        $doc = self::getPart('metric-list', ['items' => [
            ['label' => 'CPU', 'value' => '12', 'unit' => '%', 'note' => 'n<', 'tone' => 'cpu', 'icon' => 'cpu-fill'],
            ['label' => 'Plain', 'value' => '<7', 'is_good' => true],
        ]]);
        $tiles = $doc->querySelectorAll('.sl-metrics > .sl-metric');
        $this->assertCount(2, $tiles);
        $this->assertNotNull($tiles[0]->querySelector('.sl-metric-dot'));
        $this->assertNotNull($tiles[0]->querySelector('i.bi-cpu-fill.sl-metric-icon'));
        $this->assertStringContainsString('sl-metric-cpu', $tiles[0]->getAttribute('class'));
        $this->assertSame('n<', $doc->querySelector('.sl-metric > small')->textContent);
        $this->assertNull($tiles[1]->querySelector('.sl-metric-dot, .sl-metric-icon'));
        $this->assertStringContainsString('sl-metric-good', $tiles[1]->getAttribute('class'));
        $this->assertSame('<7', $tiles[1]->querySelector('.sl-metric-value')->textContent);
    }

    #[Test]
    public function figureOfATileKeepsItsValueApartFromItsText(): void
    {
        $doc = self::getPart('metric-list', ['items' => [['label' => 'Hits', 'value' => '1 234,5', 'num' => 1234.5, 'unit' => 'KB/s'], ['label' => 'Plain', 'value' => 'none']]]);
        $tiles = $doc->querySelectorAll('.sl-metric-value');
        $this->assertSame('1234.5', $tiles[0]->querySelector('[data-sl-figure]')->getAttribute('data-sl-figure'));
        $this->assertSame('1 234,5', $tiles[0]->querySelector('[data-sl-figure]')->textContent);
        $this->assertNull($tiles[1]->querySelector('[data-sl-figure]'));
    }

    #[Test]
    public function partOfTheEngineCarriesItsDataWhole(): void
    {
        $json = json_encode(['label' => 'A"<b>', 'days' => [['value' => 3, 'cap' => "1 'x'"]]], JSON_UNESCAPED_UNICODE);
        $host = self::getPart('series', ['id' => 'sl-days', 'kind' => 'days', 'json' => $json])->querySelector('#sl-days');
        $this->assertSame('days', $host->getAttribute('data-sl-series'));
        $this->assertSame($json, $host->getAttribute('data-sl-series-data'));
        $this->assertSame('', $host->innerHTML);
        $this->assertFalse(self::getPart('series', ['kind' => 'week', 'json' => '{}'])->querySelector('[data-sl-series]')->hasAttribute('id'));
        $wait = self::getPart('series', ['kind' => 'live', 'json' => '{}', 'wait_text' => 'W<'])->querySelector('[data-sl-series] > .sl-loading[role="status"]');
        $this->assertSame('W<', $wait->getAttribute('aria-label'));
    }

    #[Test]
    public function listEscapesEveryKindOfValue(): void
    {
        $doc = self::getPart('kv-list', ['items' => [
            ['label' => 'L<', 'text' => 'plain<'],
            ['label' => 'Soft', 'text' => '1.2', 'icon' => 'database'],
            ['label' => 'On', 'text' => 'On', 'is_flag' => true, 'is_on' => true],
            ['label' => 'Off', 'text' => 'Off', 'is_flag' => true],
            ['label' => 'Mute', 'text' => 'none', 'is_mute' => true],
            ['label' => 'Good', 'text' => 'fine', 'is_good' => true],
            ['label' => 'Long', 'text' => '<script>x</script>', 'is_long' => true, 'tip_label' => 'Open<'],
        ]]);
        $rows = $doc->querySelectorAll('dl.sl-kv-list > .sl-kv');
        $this->assertCount(7, $rows);
        $this->assertSame('L<', $rows[0]->querySelector('dt')->textContent);
        $this->assertSame('plain<', $rows[0]->querySelector('dd')->textContent);
        $this->assertNotNull($rows[1]->querySelector('.bi-database'));
        $this->assertStringContainsString('sl-kv-soft', $rows[1]->getAttribute('class'));
        $this->assertNotNull($rows[2]->querySelector('.sl-kv-flag.sl-kv-on'));
        $this->assertNull($rows[3]->querySelector('.sl-kv-on'));
        $this->assertNotNull($rows[3]->querySelector('.sl-kv-flag'));
        $this->assertNotNull($rows[4]->querySelector('.sl-kv-mute'));
        $this->assertNotNull($rows[5]->querySelector('.sl-kv-good'));
        $this->assertNull($doc->querySelector('script'));
        $this->assertSame('<script>x</script>', $rows[6]->querySelector('.sl-long-text')->textContent);
        $this->assertSame('<script>x</script>', $rows[6]->querySelector('.sl-tip > .sl-float-panel')->textContent);
        $this->assertSame('Open<', $rows[6]->querySelector('.sl-tip > button')->getAttribute('aria-label'));
    }

    #[Test]
    public function markOfCutValueNamesItselfWithoutALabel(): void
    {
        $doc = self::getPart('long', ['text' => 'whole value']);
        $this->assertSame(_DETAILS, $doc->querySelector('.sl-long > .sl-tip > button')->getAttribute('aria-label'));
        $this->assertSame('tooltip', $doc->querySelector('.sl-tip > .sl-float-panel')->getAttribute('role'));
    }

    #[Test]
    public function meterIsAMeterWithItsShareAndTone(): void
    {
        $doc = self::getPart('meter', ['label' => 'Disk', 'value' => '75%', 'pct' => 75, 'tone' => 'warn', 'note' => '3 of 4']);
        $bar = $doc->querySelector('.sl-meter > .sl-meter-bar.sl-knob-warn');
        $this->assertSame('meter', $bar->getAttribute('role'));
        $this->assertSame('Disk', $bar->getAttribute('aria-label'));
        $this->assertSame('75', $bar->getAttribute('aria-valuenow'));
        $this->assertSame('--sl-d-part: 75%', $bar->querySelector('i')->getAttribute('style'));
        $this->assertSame('3 of 4', $doc->querySelector('.sl-meter-note')->textContent);
        $this->assertNull(self::getPart('meter', ['label' => 'Disk', 'value' => '1%', 'pct' => 1, 'tone' => 'ok'])->querySelector('.sl-meter-note'));
    }

    #[Test]
    public function aListWithoutItsKeysRendersEmpty(): void
    {
        $this->assertNull(self::getPart('metric-list', [])->querySelector('.sl-metric'));
        $this->assertNull(self::getPart('kv-list', [])->querySelector('.sl-kv'));
        $this->assertNull(self::getPart('ext', [])->querySelector('.sl-ext-chip'));
        $this->assertNull(self::getPart('ext', ['groups' => [['label' => 'Bare']]])->querySelector('.sl-ext-chip, .sl-ext-note'));
    }

    #[Test]
    public function extensionsMarkEveryChipAndSayWhatIsLost(): void
    {
        $doc = self::getPart('ext', ['groups' => [
            ['label' => 'Must', 'have_text' => '1 of 2', 'is_short' => true, 'chips' => [
                ['name' => 'gd', 'is_ok' => true, 'state_text' => 'yes'],
                ['name' => 'curl', 'is_lost' => true, 'state_text' => 'no'],
            ], 'notes' => [['name' => 'No curl', 'text' => 'no updates<']]],
            ['label' => 'Off', 'chips' => [['name' => 'xsl', 'is_off' => true]], 'notes' => []],
        ]]);
        $groups = $doc->querySelectorAll('.sl-ext > div');
        $this->assertCount(2, $groups);
        $this->assertSame('1 of 2', $groups[0]->querySelector('h3.sl-ext-cap > .sl-ext-short')->textContent);
        $this->assertNotNull($groups[0]->querySelector('.sl-ext-chip.sl-ext-ok > .bi-check-circle-fill'));
        $this->assertNotNull($groups[0]->querySelector('.sl-ext-chip.sl-ext-lost > .bi-x-circle-fill'));
        $this->assertStringContainsString('no', $groups[0]->querySelector('.sl-ext-lost > .sl-visually-hidden')->textContent);
        $this->assertStringContainsString('no updates<', $groups[0]->querySelector('.sl-ext-note')->textContent);
        $this->assertNull($groups[1]->querySelector('.sl-ext-short, .sl-ext-good, .sl-ext-note'));
        $this->assertNotNull($groups[1]->querySelector('.sl-ext-chip.sl-ext-off'));
    }
}
