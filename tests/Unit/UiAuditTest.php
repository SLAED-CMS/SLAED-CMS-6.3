<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The audit tool's own test. Every count the theme work is judged on comes out of tools/ui-audit.php,
 * so a wrong classifier is invisible: it writes a plausible number into the baseline and everything
 * downstream trusts it. Each classifier is therefore held against a fixture whose answer is known by
 * reading the fixture, not by running the tool - which is how the hue-first colour split and the
 * context-blind duplicate counter were caught while the plan was being drafted.
 */
final class UiAuditTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2).'/tools/ui-audit.php';
    }

    # Build one audit model over a single fixture file, optionally treating it as the API block holder
    private function getModel(string $name, bool $api = false): array
    {
        $path = 'tests/Fixtures/ui/'.$name;
        $text = getFileText($path);
        $this->assertNotSame('', $text, 'Fixture is missing: '.$path);
        return getTextModel([$path => $text], $api ? $path : '');
    }

    #[Test]
    public function testAPercentageGapIsAProportionAndNotARhythmStep(): void
    {
        $count = checkThemeCount($this->getModel('space-percent.css'));
        $raw = array_column($count['sites'], 'raw');
        $this->assertSame(['13px', '40%'], $raw, 'the spacing ladder is measured in pixels, so a percentage gap has no step; a percentage width is still a size decision');
    }

    #[Test]
    public function testTheMiddleOfAClampIsARateAndNotADecision(): void
    {
        $count = checkThemeCount($this->getModel('clamp.css'));
        $sel = array_column($count['sites'], 'sel');
        $raw = array_column($count['sites'], 'raw');
        $this->assertSame(['.sl-one', '.sl-one', '.sl-three', '.sl-three'], $sel, 'a clamp whose bounds are tokens is done; the rate between them never counted');
        $why = 'both bounds of the clamp are decisions and 5vw is not; min() has no rate position, so both of its arguments stay';
        $this->assertSame(['28px', '48px', '62vh', '460px'], $raw, $why);
    }

    #[Test]
    public function testADescriptorBlockHoldsNoDecision(): void
    {
        $model = $this->getModel('fontface.css');
        $count = checkThemeCount($model);
        $bare = checkBareValues($model);
        $sel = array_values(array_unique(array_column($count['sites'], 'sel')));
        $this->assertSame(['.sl-one'], $sel, 'only the ordinary rule holds decisions; the @font-face descriptors hold none');
        $this->assertCount(1, $bare['sites'], 'the weight of the face is a descriptor, and var() cannot be written there at all');
        $this->assertSame('.sl-one', $bare['sites'][0]['sel']);
    }

    #[Test]
    public function testCountsThreeLiteralsAndReadsTheRepeatedBodyAsNeed(): void
    {
        $model = $this->getModel('count-basic.css');
        $count = checkThemeCount($model);
        $dup = checkDupBlocks($model);
        $this->assertCount(3, $count['sites'], 'padding, color and the border colour are three decisions; 1px and solid are structure');
        $this->assertCount(0, $dup['groups'], 'the repeated body is one declaration, which is need and not a group to merge');
        $this->assertSame(0, $dup['blocks']);
        $this->assertSame(1, $dup['need']);
    }

    #[Test]
    public function testOnlyANameMetByNothingIsReportedAsUnmet(): void
    {
        $unmet = checkUnmetNames($this->getModel('unmet.css', true));
        $this->assertSame(['--sl-missing'], array_keys($unmet), 'a name is met by the API block, by a scoped declaration, by a fallback at the read, or by the data list');
        $this->assertCount(2, $unmet['--sl-missing'], 'both reads of the one unmet name are reported, because each drops its own declaration');
    }

    #[Test]
    public function testTwoIdenticalBodiesInDifferentMediaAreNotDuplicates(): void
    {
        $dup = checkDupBlocks($this->getModel('dup-media.css'));
        $this->assertCount(0, $dup['groups'], 'a body repeated across contexts is a rule per context, not a duplicate');
        $this->assertSame(0, $dup['blocks']);
    }

    #[Test]
    public function testABodyOfOneDeclarationIsNeedAndNotRepetition(): void
    {
        $dup = checkDupBlocks($this->getModel('dup-need.css'));
        $this->assertSame(1, $dup['need'], 'three rules whose whole body is one declaration are one property reaching one token, not a group to merge');
        $this->assertCount(1, $dup['groups'], 'the two-declaration body under two selectors of one component is the group a human still has to answer for');
        $this->assertSame(1, $dup['blocks']);
        $this->assertSame(0, $dup['split'], 'one fixture is one file, so nothing here is spread across two');
    }

    #[Test]
    public function testValuesReachedThroughATokenAreNotCounted(): void
    {
        $count = checkThemeCount($this->getModel('tokenised.css'));
        $this->assertCount(1, $count['sites'], 'only the 7px beside a token is a decision');
        $this->assertSame(1, $count['half'], 'the declaration holding both a token and a literal is half done');
        $this->assertSame(3, $count['tokenised']);
    }

    #[Test]
    public function testAColourMixIsJudgedByWhatIsInsideIt(): void
    {
        $count = checkThemeCount($this->getModel('mix.css'));
        $raw = array_map(static fn(array $one): string => $one['sel'].' '.$one['raw'], $count['sites']);
        $this->assertSame(
            ['.sl-two 13%', '.sl-three #0877b1', '.sl-four #111827', '.sl-four #e5e7eb'],
            $raw,
            'a mix of two tokens is tokenised, a literal ratio is a decision, and a literal colour inside a mix is one too'
        );
        $this->assertSame('mix', $count['sites'][0]['kind'], 'the ratio carries its own kind, so its replacement names a mix token');
    }

    #[Test]
    public function testAColourStaysAColourWhateverFunctionCarriesIt(): void
    {
        $this->assertSame('color', getValueKind('light-dark(#ffffff, #111827)'), 'a token that gained its dark half is still a colour');
        $this->assertSame('color', getValueKind('color-mix(in srgb, var(--sl-primary) 10%, transparent)'), 'a mixed colour is still a colour');
        $this->assertSame('gradient', getValueKind('linear-gradient(to top, #fff 0%, #000 100%)'));
        $this->assertSame('length', getValueKind('24px'));
    }

    #[Test]
    public function testStructureIsNeverADecision(): void
    {
        $count = checkThemeCount($this->getModel('allowlist.css'));
        $this->assertCount(0, $count['sites'], 'zero, auto, 100%, a hairline, a circle, a track list, a placement and a CSS triangle are all structure');
    }

    #[Test]
    public function testAShadowIsOneDecisionNotOnePerOffset(): void
    {
        $count = checkThemeCount($this->getModel('shadow.css'));
        $this->assertCount(1, $count['sites'], 'the whole box-shadow is tokenised at once, so its offsets are not counted apart');
    }

    #[Test]
    public function testAShadowIsStillAShadowWhenItsPartsArriveThroughTokens(): void
    {
        $api = $this->getModel('shadow.css', true)['api'];
        $why = 'a shadow reading the theme scrim or the ring geometry is a shadow; reading the colour off a literal alone '
            .'would file it as something else and the same name would hold two kinds across two themes for no visible reason';
        foreach (['--sl-shadow-literal', '--sl-shadow-tinted', '--sl-shadow-ringed', '--sl-shadow-inset'] as $name) {
            $this->assertSame('shadow', getValueKind($api[$name]), $name.': '.$why);
        }
        $this->assertNotSame('shadow', getValueKind($api['--sl-not-a-shadow']), 'one token standing alone is an alias, never a shadow');
        $this->assertNotSame('shadow', getValueKind($api['--sl-also-not']), 'one length is a length, never a shadow');
    }

    #[Test]
    public function testBareNumbersAreFoundAndNeutralOnesAreNot(): void
    {
        $model = $this->getModel('bare.css');
        $bare = checkBareValues($model);
        $this->assertCount(4, $bare['sites'], 'line-height 1 and opacity 0 stay neutral, and a value read from a token is not bare');
        $this->assertSame(['line-height' => 1, 'font-weight' => 1, 'opacity' => 1, 'z-index' => 1], $bare['byprop']);
    }

    #[Test]
    public function testEveryViolationNamesTheTokenThatReplacesIt(): void
    {
        $count = checkThemeCount($this->getModel('bare.css'));
        $fix = [];
        foreach ($count['sites'] as $site) $fix[$site['prop']] = $site['fix'];
        $this->assertSame('var(--sl-line-normal)', $fix['line-height'], 'nine spellings of normal collapse onto one step');
        $this->assertSame('var(--sl-weight-bold)', $fix['font-weight'], 'the keyword reaches the same step as 700');
        $this->assertStringContainsString('--sl-z-', $fix['z-index']);
    }

    #[Test]
    public function testPaddingSnapsToTheNearestLadderStep(): void
    {
        $count = checkThemeCount($this->getModel('count-basic.css'));
        $fix = [];
        foreach ($count['sites'] as $site) $fix[$site['prop']] = $site['fix'];
        $this->assertSame('var(--sl-space-4)', $fix['padding'], '10px sits on its own step, so nothing moves');
    }

    #[Test]
    public function testACoolNearWhiteIsANeutralAndNotABlue(): void
    {
        $ramp = getColorRamp($this->getModel('ramp.css', true));
        $gray = array_column($ramp['gray'] ?? [], 'name');
        $blue = array_column($ramp['blue'] ?? [], 'name');
        $this->assertContains('--sl-gray-fifty', $gray, 'HSL saturation reads 100 at that lightness; chroma is what says neutral');
        $this->assertContains('--sl-gray-two', $gray);
        $this->assertContains('--sl-primary', $blue);
        $this->assertContains('--sl-primary-strong', $blue);
    }

    #[Test]
    public function testEveryStepOfARampCarriesARole(): void
    {
        $ramp = getColorRamp($this->getModel('ramp.css', true));
        foreach ($ramp as $list) {
            foreach ($list as $item) {
                $this->assertNotSame('', (string)$item['role'], 'a step without a role cannot reverse for dark');
            }
        }
    }

    #[Test]
    public function testTheMarkerEndsTheApiBlock(): void
    {
        $model = $this->getModel('ramp.css', true);
        $this->assertTrue($model['marker']);
        $this->assertCount(5, $model['api'], 'the five tokens above the marker are API');
        $this->assertSame([], $model['scoped'], 'nothing below the marker declares a custom property here');
    }

    /**
     * One declaration per row with the answer read off the row, not off the tool. This table is what caught
     * `z-index: 0` being swallowed by the neutral-value allowlist before the bare-number rule could see it:
     * a stacking context is a decision, and the count was quietly four low in admin and eight in lite.
     */
    public static function getDeclCases(): array
    {
        return [
            'a border spends its decision on the colour' => ['.a{border:1px solid #ccc}', 1],
            'a border thicker than a hairline is a component decision too' => ['.a{border:2px solid #ccc}', 2],
            'a CSS triangle is geometry' => ['.a{border:6px solid transparent}', 0],
            'zero and auto are the absence of a decision' => ['.a{margin:0 auto}', 0],
            'a zero carrying a unit is still zero' => ['.a{padding:0px}', 0],
            'four sides are four decisions' => ['.a{padding:10px 20px 30px 40px}', 4],
            'a colour function is one decision' => ['.a{color:rgba(0,0,0,.5)}', 1],
            'a gradient spends one per stop' => ['.a{background:linear-gradient(90deg,#fff 0%,#000 100%)}', 2],
            'a gradient reaching its stops through tokens is clean' => ['.a{background:linear-gradient(90deg,var(--sl-a) 0%,var(--sl-b) 100%)}', 0],
            'a transition is a duration and an easing' => ['.a{transition:opacity .2s ease}', 2],
            'a transition reading both is clean' => ['.a{transition:opacity var(--sl-time-fast) var(--sl-ease-out)}', 0],
            'motion off is not a duration' => ['.a{transition:opacity 0.01ms}', 0],
            'a shadow is one decision whole' => ['.a{box-shadow:0 1px 2px rgba(0,0,0,.1)}', 1],
            'no shadow is no decision' => ['.a{box-shadow:none}', 0],
            'a content string belongs to its rule' => ['.a{content:"x"}', 0],
            'a track list is structure' => ['.a{grid-template-columns:1fr 220px}', 0],
            'placement is structure' => ['.a{top:3px}', 0],
            'filling the container is not a size' => ['.a{width:100%}', 0],
            'a figure sizing a control is' => ['.a{width:40px}', 1],
            'half a box makes a circle' => ['.a{border-radius:50%}', 0],
            'a radius off the ladder is a decision' => ['.a{border-radius:6px}', 1],
            'a stacking context is a decision, zero included' => ['.a{z-index:0}', 1],
            'so is any other layer' => ['.a{z-index:2}', 1],
            'a layer read from a token is not' => ['.a{z-index:var(--sl-z-modal)}', 0],
            'opaque is neutral' => ['.a{opacity:1}', 0],
            'invisible is neutral' => ['.a{opacity:0}', 0],
            'anything between them is a decision' => ['.a{opacity:.5}', 1],
            'a single line is neutral' => ['.a{line-height:1}', 0],
            'any other line height is not' => ['.a{line-height:1.5}', 1],
            'a weight is a decision as a number' => ['.a{font-weight:400}', 1],
            'and as a word' => ['.a{font-weight:normal}', 1],
            'a transform is a geometric operation' => ['.a{transform:translateY(-1px)}', 0],
            'the font shorthand carries size and line height' => ['.a{font:14px/16px Tahoma,Arial}', 2],
        ];
    }

    #[Test]
    #[DataProvider('getDeclCases')]
    public function testOneDeclarationCountsWhatTheRowSays(string $css, int $want): void
    {
        $this->assertCount($want, checkThemeCount(getTextModel(['case.css' => $css]))['sites'], $css);
    }

    /**
     * The walker has to survive what real stylesheets contain, because a rule it loses is a rule
     * nothing downstream ever measures and no count would look wrong.
     */
    public static function getParserCases(): array
    {
        return [
            'a brace inside a string does not end the rule' => ['.a::after{content:"}";color:#fff}.b{color:#000}', 2],
            'an at-statement is not a selector' => ['@import url(a.css);.a{color:#fff}', 1],
            'a rule inside a comment is not a rule' => ['/* .z{color:#f00} */ .a{color:#fff}', 1],
            'keyframe stops are walked' => ['@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}', 2],
        ];
    }

    #[Test]
    #[DataProvider('getParserCases')]
    public function testTheWalkerFindsExactlyTheRulesThatAreThere(string $css, int $want): void
    {
        $this->assertCount($want, getTextModel(['case.css' => $css])['rules'], $css);
    }

    #[Test]
    public function testNestedAtRulesKeepBothContexts(): void
    {
        $rule = getTextModel(['case.css' => '@supports (display:grid){@media (max-width:768px){.a{color:#fff}}}'])['rules'][0];
        $this->assertStringContainsString('supports', $rule['ctx']);
        $this->assertStringContainsString('media', $rule['ctx'], 'a body compared across contexts is not a duplicate, so losing one merges rules that never meet');
    }

    #[Test]
    public function testOneNameDeclaredTwiceIsReported(): void
    {
        $clash = $this->getModel('clash.css', true)['clash'];
        $this->assertCount(1, $clash, 'the second declaration silently wins and nothing else would ever say so');
        $this->assertSame('--sl-primary', $clash[0]['name']);
        $this->assertSame('#30a2f6', $clash[0]['was']);
        $this->assertSame('#207fb6', $clash[0]['now']);
    }

    #[Test]
    public function testTheGrammarNamesEachLawItBreaks(): void
    {
        $why = [];
        foreach (checkNameGrammar($this->getModel('names.css', true)) as $item) $why[$item['name']][] = $item['why'];
        $this->assertArrayNotHasKey('--sl-space-4', $why, 'a registered axis role passes');
        $this->assertArrayNotHasKey('--sl-btn-pad-x', $why, 'a declared component with a closed prop passes');
        $this->assertArrayNotHasKey('--sl-chart-cpu', $why, 'a categorical member passes');
        $this->assertArrayNotHasKey('--sl-d-level', $why, 'a data token is not API and is not judged as one');
        $this->assertArrayNotHasKey('--sl-shadow-focus', $why, 'focus is a declared shadow role, so the state law does not fire on it');
        $this->assertStringContainsString('inversion', implode(' ', $why['--sl-color-on-dark']));
        $this->assertStringContainsString('state', implode(' ', $why['--sl-color-primary-hover']));
        $this->assertStringContainsString('stack', implode(' ', $why['--sl-color-bg-soft-soft']));
        $this->assertStringContainsString('5 segments', implode(' ', $why['--sl-login-dropdown-form-margin-left']));
    }

    #[Test]
    public function testOneNameHoldingTwoKindsIsCaught(): void
    {
        $this->assertSame('color', getValueKind('rgba(32, 75, 102, 0.18)'));
        $this->assertSame('shadow', getValueKind('0 1px 2px rgba(42, 48, 60, 0.12)'));
        $this->assertSame('gradient', getValueKind('linear-gradient(90deg, #fff 0%, #000 100%)'));
        $this->assertSame('length', getValueKind('28px'));
    }

    #[Test]
    public function testAClassTouchingATemplateTagIsStillAUse(): void
    {
        $text = getFileText('tests/Fixtures/ui/classuse.html');
        $this->assertTrue(isClassUsed('sl-collapsible', $text), 'emitted straight after {% endif %}, with no quote or space before it');
        $this->assertTrue(isClassUsed('sl-table', $text), 'a whole name beside the longer one that only starts with it');
        $this->assertTrue(isClassUsed('sl-home', $text), 'inside a quoted class attribute');
        $this->assertFalse(isClassUsed('sl-nav', $text), 'only ever the head of a longer name');
        $this->assertFalse(isClassUsed('sl-bar', $text), 'only ever the tail of a longer name');
    }

    #[Test]
    public function testTheMarkupScanFoldsConcatenationsAndIgnoresWhatOnlyLooksLikeMarkup(): void
    {
        $hits = getFileMarkup('tests/Fixtures/ui/markup.php');
        $this->assertSame(1, $hits['class'], "'<di'.'v class=...' folds into one string and is one class attribute");
        $this->assertSame(1, $hits['style']);
        $this->assertSame(2, $hits['tag'], 'the closing div and td; a regular expression and a feed element are neither');
    }

    #[Test]
    public function testContrastIsMeasuredByTheWcagFormula(): void
    {
        $this->assertEqualsWithDelta(21.0, getContrastRatio([0, 0, 0], [255, 255, 255]), 0.01);
        $this->assertEqualsWithDelta(1.0, getContrastRatio([18, 52, 86], [18, 52, 86]), 0.01);
        $this->assertEqualsWithDelta(4.83, getContrastRatio([107, 114, 128], [255, 255, 255]), 0.02, 'the muted grey holds AA against the page, and only just');
    }

    #[Test]
    public function testTheCssWalkerKeepsMediaContextAndLineNumbers(): void
    {
        $model = $this->getModel('dup-media.css');
        $this->assertCount(2, $model['rules']);
        $this->assertNotSame($model['rules'][0]['ctx'], $model['rules'][1]['ctx']);
        $this->assertSame(3, $model['rules'][0]['line'], 'the first rule opens on the third line of the fixture');
    }
}
