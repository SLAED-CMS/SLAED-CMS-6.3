<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

require_once dirname(__DIR__, 2).'/core/classes/template.php';

# The rank of a group as the stored string icon:lit:total:#rrggbb: what getRankData() accepts and the row of marks getRankView() hands the theme
final class RankDataTest extends TestCase
{
    private static mixed $was = null;

    # A template double that answers the fragment name and its data as JSON, so the test reads what the view hands to the theme
    private static function getTemplate(): Template
    {
        return new class () extends Template {
            public function __construct()
            {
            }

            public function getHtmlFrag(string $name, array $data = []): string
            {
                return json_encode(['frag' => $name, 'data' => $data], JSON_UNESCAPED_UNICODE);
            }
        };
    }

    # Lift the two helpers out of core/system.php by name, since the file needs a request to load and a copied body would drift from the shipped one
    public static function setUpBeforeClass(): void
    {
        if (!function_exists('getRankData')) {
            $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/system.php');
            foreach (['getRankData', 'getRankView'] as $name) {
                $from = strpos($code, 'function '.$name.'(');
                $to = ($from === false) ? false : strpos($code, "\n}\n", $from);
                if ($from === false || $to === false) throw new \RuntimeException($name.'() is gone from core/system.php');
                eval(substr($code, $from, $to - $from + 3));
            }
        }
        self::$was = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = self::getTemplate();
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['tpl'] = self::$was;
    }

    # A valid string splits into its icon, its counts as numbers and its colour
    #[Test]
    public function aValidStringSplitsIntoItsParts(): void
    {
        $this->assertSame(['icon' => 'star-fill', 'lit' => 3, 'total' => 5, 'color' => '#8297a2'], getRankData('star-fill:3:5:#8297a2'));
    }

    # The counts hold 0 <= lit <= total and 1 <= total <= 10 at both ends
    #[Test]
    public function theCountsHoldTheirBounds(): void
    {
        $this->assertSame(0, getRankData('star-fill:0:5:#8297a2')['lit'] ?? null, 'No lit mark is a rank');
        $this->assertSame(10, getRankData('star-fill:10:10:#8297a2')['lit'] ?? null, 'Ten of ten is a rank');
        $this->assertSame(1, getRankData('gear-fill:1:1:#8297a2')['total'] ?? null, 'One mark is a rank');
        $this->assertSame([], getRankData('star-fill:0:0:#8297a2'), 'A rank of no marks is drawn');
        $this->assertSame([], getRankData('star-fill:3:11:#8297a2'), 'A rank of eleven marks is drawn');
        $this->assertSame([], getRankData('star-fill:6:5:#8297a2'), 'More lit marks than marks are drawn');
        $this->assertSame([], getRankData('star-fill:-1:5:#8297a2'), 'A negative count is drawn');
    }

    # Every value that is not the rank string, an old picture name first, gives no rank
    #[Test]
    #[DataProvider('getBadRanks')]
    public function aValueOutsideTheStringGivesNoRank(string $rank): void
    {
        $this->assertSame([], getRankData($rank));
    }

    public static function getBadRanks(): array
    {
        return [
            'empty' => [''],
            'old picture' => ['rank_3.png'],
            'old special picture' => ['partner_bronze.png'],
            'icon with capitals' => ['Star-Fill:3:5:#8297a2'],
            'icon with an underscore' => ['star_fill:3:5:#8297a2'],
            'icon with a path' => ['../star:3:5:#8297a2'],
            'icon with a quote' => ['star"onclick:3:5:#8297a2'],
            'no icon' => [':3:5:#8297a2'],
            'short colour' => ['star-fill:3:5:#fff'],
            'colour with capitals' => ['star-fill:3:5:#8297A2'],
            'colour not hex' => ['star-fill:3:5:#82g7a2'],
            'colour by name' => ['star-fill:3:5:red'],
            'colour with a rule' => ['star-fill:3:5:#8297a2;background:red'],
            'no colour' => ['star-fill:3:5:'],
            'counts not numbers' => ['star-fill:three:five:#8297a2'],
            'part missing' => ['star-fill:3:#8297a2'],
            'part too many' => ['star-fill:3:5:5:#8297a2'],
        ];
    }

    # The view hands the theme the title, the colour and one mark per count, the first ones lit
    #[Test]
    public function theViewHandsOneMarkPerCountTheFirstLit(): void
    {
        $view = json_decode(getRankView('sun-fill:2:4:#c68649', 'Group: Partners'), true);
        $this->assertSame('rank', $view['frag']);
        $this->assertSame(['Group: Partners', '#c68649'], [$view['data']['title'], $view['data']['color']]);
        $marks = $view['data']['marks'];
        $this->assertSame([true, true, false, false], array_column($marks, 'is_lit'));
        $this->assertSame(['sun-fill'], array_values(array_unique(array_column($marks, 'icon_name'))));
    }

    # A value that is no rank, an old picture name included, draws nothing
    #[Test]
    public function aValueThatIsNoRankDrawsNothing(): void
    {
        $this->assertSame('', getRankView('rank_3.png', 'Group: Locals'));
        $this->assertSame('', getRankView('', 'Rank'));
    }
}
