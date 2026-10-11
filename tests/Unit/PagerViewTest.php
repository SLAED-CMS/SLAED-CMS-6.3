<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;

require_once dirname(__DIR__, 2).'/core/classes/template.php';

# The window of numbers getTplPagerView() hands the theme: the anchors, the dots, the marks the pager narrows by and the range of the page line
final class PagerViewTest extends TestCase
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
                return json_encode(['frag' => $name, 'data' => $data], JSON_UNESCAPED_UNICODE)."\n";
            }
        };
    }

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('getTplPagerView')) require_once dirname(__DIR__, 2).'/core/helpers.php';
        $text = ['_BACK' => 'Back', '_NEXT' => 'Next', '_OVERALL' => 'In total', '_PAGE' => 'Page', '_NUMOF' => '%1$s of %2$s', '_PAGERANGE' => 'Showing %1$s–%2$s'];
        foreach ($text as $name => $val) {
            if (!defined($name)) define($name, $val);
        }
        self::$was = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = self::getTemplate();
    }

    public static function tearDownAfterClass(): void
    {
        $GLOBALS['tpl'] = self::$was;
    }

    # Render one pager through the double and return its pager data and the links it was built from, in order
    private static function getView(int $num, int $pages, int $maxpg, array $meta = []): array
    {
        $out = getTplPagerView($num, $pages, $maxpg, static fn(int $i): array => ['href' => 'index.php?name=news&num='.$i], $meta);
        $view = json_decode(trim($out), true);
        $items = [];
        foreach (array_filter(explode("\n", (string)$view['data']['items'])) as $line) $items[] = json_decode($line, true)['data'];
        return ['view' => $view, 'items' => $items];
    }

    # Spell the row of numbers as the reader sees it: the current page in brackets, a far number with a caret, a neighbour with a dot, the dots as an ellipsis
    private static function getRow(array $items): string
    {
        $out = [];
        foreach ($items as $item) {
            if (!empty($item['is_dots'])) {
                $out[] = '…';
                continue;
            }
            $mark = !empty($item['is_far']) ? '^' : (!empty($item['is_near']) ? '.' : '');
            $out[] = !empty($item['is_cur']) ? '['.$item['label'].']' : $item['label'].$mark;
        }
        return implode(' ', $out);
    }

    # Sixteen pages in a window of five, read at the first, a middle and the last page
    public static function getWindows(): array
    {
        return [
            'the first page' => [1, '[1] 2. 3^ 4^ 5^ … 16'],
            'a middle page' => [8, '1 … 6^ 7. [8] 9. 10^ … 16'],
            'the last page' => [16, '1 … 12^ 13^ 14^ 15. [16]'],
        ];
    }

    # The first and the last stay with no mark, the dots stand where numbers are skipped, the neighbours are near and the rest of the window far
    #[Test]
    #[DataProvider('getWindows')]
    public function theWindowCarriesItsMarks(int $num, string $want): void
    {
        $data = self::getView($num, 16, 5);
        $this->assertSame('pager', $data['view']['frag']);
        $this->assertSame($want, self::getRow($data['items']));
        foreach ($data['items'] as $item) {
            if (!empty($item['is_dots'])) continue;
            $edge = in_array($item['label'], ['1', '16'], true);
            if ($edge) $this->assertFalse($item['is_far'] || $item['is_near'], 'The first and the last page carry a mark and would go on a narrow pager');
            if (!empty($item['is_cur'])) $this->assertArrayNotHasKey('href', $item, 'The current page is a link to itself');
            if (empty($item['is_cur'])) $this->assertSame('index.php?name=news&num='.$item['label'], $item['href']);
        }
    }

    # No dots when the window touches both anchors, and no pager at all for one page
    #[Test]
    public function aShortRunHasNoDots(): void
    {
        $this->assertSame('[1] 2. 3^ 4', self::getRow(self::getView(1, 4, 8)['items']));
        $this->assertSame('', getTplPagerView(1, 1, 8, static fn(int $i): array => []));
    }

    # The arrows lead one page back and forward with their own rel and key, and an arrow at the end of the run is no link
    #[Test]
    public function theArrowsStepOnePage(): void
    {
        $prev = json_decode(trim(self::getView(1, 16, 5)['view']['data']['prev']), true)['data'];
        $next = json_decode(trim(self::getView(1, 16, 5)['view']['data']['next']), true)['data'];
        $this->assertTrue($prev['is_nav'] && $prev['is_cur']);
        $this->assertArrayNotHasKey('href', $prev);
        $this->assertSame(['index.php?name=news&num=2', 'next', 'chevron-right', 'ArrowRight'], [$next['href'], $next['rel'], $next['icon_name'], $next['key_name']]);
        $back = json_decode(trim(self::getView(16, 16, 5)['view']['data']['prev']), true)['data'];
        $this->assertSame(['index.php?name=news&num=15', 'prev', 'chevron-left', 'ArrowLeft'], [$back['href'], $back['rel'], $back['icon_name'], $back['key_name']]);
    }

    # The page line gets the range of the shown records when the caller names the page size and the total, the last page cut at the total
    #[Test]
    public function theLineCarriesTheRange(): void
    {
        $data = self::getView(3, 16, 5, ['count' => 157, 'limit' => 10])['view']['data'];
        $this->assertSame([21, 30, 'Showing 21–30', '3 of 16', 157], [$data['range_from'], $data['range_to'], $data['range_text'], $data['page_of'], $data['count']]);
        $last = self::getView(16, 16, 5, ['count' => 157, 'limit' => 10])['view']['data'];
        $this->assertSame([151, 157], [$last['range_from'], $last['range_to']]);
        $bare = self::getView(3, 16, 5)['view']['data'];
        $this->assertArrayNotHasKey('range_text', $bare, 'A caller that names no page size gets a range it cannot know');
    }
}
