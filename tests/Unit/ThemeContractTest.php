<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The theme ratchet. There are no git hooks and no CI here, and .claude/ is not tracked, so the edit
 * hook is per-machine and this test is the whole enforcement that survives a clone: it runs the audit
 * over both themes and fails when any ratcheted count grew against tools/ui-audit-baseline.json.
 * It catches what an edit hook cannot - a manual edit, a merge, and a file the hook never saw.
 */
final class ThemeContractTest extends TestCase
{
    private static array $cont = [];
    private static array $base = [];

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/tools/ui-audit.php';
        self::$cont = getContract();
        self::$base = getBaselineData();
    }

    #[Test]
    public function testTheBaselineIsCommittedAndCoversEveryTheme(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/tools/ui-audit-baseline.json', 'the ratchet cannot live beside the gitignored rules and survive a clone');
        foreach (array_keys(self::$cont['themes']) as $name) {
            $this->assertArrayHasKey($name, self::$base['themes'] ?? [], 'theme '.$name.' has no baseline entry; store one with --store');
        }
    }

    # getContract() folds the generated pair registry in only when the file is there, so a missing or empty one does not fail the
    # contrast check - it silently leaves nothing to check and every count downstream reads zero and passes. A gate whose input can
    # vanish without a word is the failure this plan keeps finding, so the registry is asserted the same way the ratchet is
    #[Test]
    public function testTheContrastRegistryIsCommittedAndCarriesPairs(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2).'/tools/ui-contrast.json', 'the contrast gate has no pairs without it and would read zero while checking nothing; regenerate with node tools/ui-shots.mjs --contrast');
        $pairs = self::$cont['contrast']['pairs'] ?? [];
        $this->assertNotEmpty($pairs, 'the contrast registry holds no pairs; regenerate it with node tools/ui-shots.mjs --contrast');
        foreach (array_keys(self::$cont['themes']) as $name) {
            $mine = array_filter($pairs, static fn(array $one): bool => ($one['theme'] ?? '') === $name);
            $this->assertNotEmpty($mine, 'theme '.$name.' has no contrast pair recorded, so nothing about its colours is being checked');
        }
    }

    #[Test]
    public function testNoRatchetedCountGrewAgainstTheBaseline(): void
    {
        foreach (array_keys(self::$cont['themes']) as $name) {
            $now = getThemeAudit($name)['totals'];
            foreach (self::$cont['ratchet'] as $key) {
                $was = self::$base['themes'][$name][$key] ?? null;
                if ($was === null) continue;
                $this->assertLessThanOrEqual($was, $now[$key], $name.'.'.$key.' grew from '.$was.' to '.$now[$key].'; run php tools/ui-audit.php --theme='.$name.' to see where');
            }
        }
    }

    # The freeze, asked of the tree the way the ratchet asks of the counts. An API that gains a role wrongs no copy;
    # one that loses or renames one leaves every theme copied from it reading a name this repository cannot reach,
    # and no count can see that - the token total falls by one and reads like a tidy-up
    #[Test]
    public function testAFrozenApiHasNotLostAName(): void
    {
        if (!(self::$cont['frozen'] ?? false)) $this->markTestSkipped('the API is not frozen yet, so no roster is binding');
        foreach (array_keys(self::$cont['themes']) as $name) {
            $was = self::$base['api'][$name] ?? null;
            $this->assertIsArray($was, $name.' has no name roster in the baseline, so the freeze holds nothing; store one with --store');
            $gone = array_diff($was, array_keys(getThemeModel($name)['api']));
            $this->assertSame([], array_values($gone), $name.' no longer declares '.implode(', ', $gone).', which a frozen API may not do');
        }
    }

    #[Test]
    public function testEveryThemeClosesItsApiBlockWithTheMarker(): void
    {
        foreach (array_keys(self::$cont['themes']) as $name) {
            $why = $name.'/assets/css/base.css has no '.self::$cont['marker'];
            $this->assertTrue(getThemeModel($name)['marker'], $why.', so its API block has no end and a theme author cannot tell API from element styles');
        }
    }

    #[Test]
    public function testEveryDeclaredThemeFileIsPresent(): void
    {
        foreach (self::$cont['themes'] as $name => $conf) {
            foreach ($conf['css'] as $path) {
                $this->assertFileExists(dirname(__DIR__, 2).'/'.$path, $name.' declares '.$path.' in the contract but the package does not carry it');
            }
        }
    }

    #[Test]
    public function testTheMarkupScanDidNotGrow(): void
    {
        $sum = 0;
        foreach (checkPhpMarkup() as $hits) $sum += $hits['class'] + $hits['style'] + $hits['tag'];
        $was = self::$base['global']['markup'] ?? null;
        if ($was === null) $this->markTestSkipped('no stored markup baseline');
        $this->assertLessThanOrEqual($was, $sum, 'PHP hardcodes more markup than the baseline records; a theme cannot restyle what PHP assembles');
    }

    #[Test]
    public function testNoNewNameHoldsTwoKindsAcrossThemes(): void
    {
        $now = checkNameKinds();
        $was = self::$base['global']['kinds'] ?? null;
        if ($was === null) $this->markTestSkipped('no stored kind baseline');
        $this->assertLessThanOrEqual($was, count($now), 'a rule written against one theme is wrong in the other, and no reader can tell which without opening both');
    }

    #[Test]
    public function testEveryLadderStepHasATokenName(): void
    {
        foreach (self::$cont['ladders'] as $axis => $conf) {
            $this->assertSameSize($conf['steps'], $conf['tokens'], 'ladder '.$axis.' has a step with no token name, so a snap has nowhere to land');
        }
    }

    #[Test]
    public function testEveryAllowlistEntryCarriesItsReason(): void
    {
        foreach (['properties', 'values', 'shapes'] as $part) {
            foreach (self::$cont['allowlist'][$part] as $key => $why) {
                $this->assertNotSame('', trim((string)$why), 'allowlist entry '.$part.'.'.$key.' has no written reason, and an allowlist without reasons is how a zoo grows back');
            }
        }
        foreach (self::$cont['markup']['exclude'] as $key => $why) {
            $this->assertNotSame('', trim((string)$why), 'markup exclusion '.$key.' has no written reason');
        }
    }

    #[Test]
    public function testTheModeLivesOnlyInTheApiBlock(): void
    {
        foreach (self::$cont['themes'] as $name => $conf) {
            $model = getThemeModel($name);
            foreach ($model['files'] as $path => $file) {
                $body = $file['text'];
                if ($path === $conf['api']) {
                    $mark = strpos($body, self::$cont['marker']);
                    $this->assertNotFalse($mark, $path.' has no marker, so nothing says where the API block ends');
                    $body = substr($body, $mark);
                }
                $body = filterComments($body);
                $why = $path.' names the colour mode outside the API block';
                $this->assertStringNotContainsString('light-dark(', $body, $why.'; a component that cannot follow the tokens is missing a role');
                $this->assertStringNotContainsString('prefers-color-scheme', $body, $why.'; a media query drifts from the tokens the moment one of them moves');
            }
        }
    }

    #[Test]
    public function testEveryDocumentTemplateCarriesTheModeAttribute(): void
    {
        $root = dirname(__DIR__, 2);
        $list = [
            'templates/admin/layouts/admin.html',
            'templates/admin/layouts/bare.html',
            'templates/lite/layouts/admin.html',
            'templates/lite/layouts/bare.html',
            'templates/lite/partials/site-header.html',
            'templates/lite/fragments/shop-invoice.html',
        ];
        foreach ($list as $path) {
            $html = (string)file_get_contents($root.'/'.$path);
            $why = $path.' opens a document without the mode attribute, so the page renders in the wrong mode';
            $this->assertStringContainsString('data-theme="{{ mode }}"', $html, $why);
        }
    }

    #[Test]
    public function testEveryTokenReadByJavascriptIsStillDeclared(): void
    {
        $seen = [];
        foreach (array_keys(self::$cont['themes']) as $name) $seen += getThemeModel($name)['api'];
        foreach (array_keys(self::$cont['js']) as $tok) {
            $this->assertArrayHasKey($tok, $seen, $tok.' is read through getComputedStyle and falls through to a script default when it disappears, which no CSS check can see');
        }
    }

    #[Test]
    public function testCategoricalMembersStayDistinguishable(): void
    {
        foreach (array_keys(self::$cont['themes']) as $name) {
            $api = getThemeModel($name)['api'];
            foreach (self::$cont['categorical'] as $set => $conf) {
                $rgb = [];
                foreach ($conf['members'] as $item) {
                    $val = $api['--sl-'.$set.'-'.$item] ?? null;
                    if ($val === null) continue;
                    $hit = getRgbValues($val);
                    if ($hit !== null) $rgb[$item] = $hit;
                }
                foreach ($rgb as $one => $left) {
                    foreach ($rgb as $two => $right) {
                        if ($one >= $two) continue;
                        $gap = abs($left[0] - $right[0]) + abs($left[1] - $right[1]) + abs($left[2] - $right[2]);
                        $why = $name.': '.$set.' members '.$one.' and '.$two.' are too close to tell apart';
                        $this->assertGreaterThanOrEqual($conf['mindiff'], $gap, $why.', and a set with no order has no ladder to lean on');
                    }
                }
            }
        }
    }
}
