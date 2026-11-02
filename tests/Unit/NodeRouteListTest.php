<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;

# The lists and views of Node over route_probe.php tree, modes and head: the document tree, the display modes, the htmx part, the hints, the notices and the header of a list
final class NodeRouteListTest extends NodeRouteCase
{
    # A document of a type with the tree shows its trail, its level with its own children and the neighbours of the reading order
    # A parent the reader may not see stays hidden and its child stands among the roots, a document outside the tree of the reader and a type without the tree show no block
    #[Test]
    public function theDocumentShowsItsBranchOfTheTree(): void
    {
        $run = $this->getMode('tree');
        $roots = ['Doc one', 'Guide', 'Orphan'];
        $flat = ['more' => 0, 'first' => ''];
        $this->assertSame([200, ['path' => [], 'items' => $roots, 'kids' => [], 'cur' => ['Doc one'], 'prev' => [], 'next' => ['Guide']] + $flat], $run['nav'][201]);
        $this->assertSame([200, ['path' => ['Guide', 'Config'], 'items' => ['Config', 'Install & run'], 'kids' => ['Advanced'], 'cur' => ['Config'], 'prev' => ['Guide'],
            'next' => ['Advanced']] + $flat], $run['nav'][204], 'The branch of Config lacks its trail, its level, its child or its neighbours');
        $this->assertSame([200, ['path' => ['Guide', 'Config', 'Advanced'], 'items' => ['Advanced'], 'kids' => [], 'cur' => ['Advanced'], 'prev' => ['Config'],
            'next' => ['Install & run']] + $flat], $run['nav'][205], 'The reading order does not climb back to the next sibling of the parent');
        $this->assertSame([200, ['path' => [], 'items' => $roots, 'kids' => [], 'cur' => ['Orphan'], 'prev' => ['Install & run'], 'next' => []] + $flat], $run['nav'][207],
            'The child of a hidden parent does not stand among the roots');
        $this->assertSame([false, 404], $run['hidden'], 'The pending parent is linked or readable for a guest');
        $this->assertSame([200, []], $run['moder'], 'A document outside the tree of the reader shows a branch');
        $this->assertSame([200, false], $run['plain'], 'A type without the tree shows the block');
        $this->assertSame([true, false], $run['escape'], 'A title in the tree is not escaped exactly once');
    }

    # The tree is read in batches of getNodeTree(): one statement up to 500 documents and one more for each started 500, never one per row; the view keeps its budget
    #[Test]
    public function theTreeKeepsTheBudgetOfTheView(): void
    {
        [$one, $two] = $this->getMode('tree')['sql'];
        $this->assertSame([1, ['Config', 'Install & run'], 'Advanced'], [$one['tree'], $one['items'], $one['next']], 'Seven documents took more than one batch');
        $this->assertSame([2, ['Config', 'Install & run'], 'Advanced'], [$two['tree'], $two['items'], $two['next']], 'Six hundred and seven documents did not take two batches');
        $this->assertLessThanOrEqual(6 + 1, $one['view'] + $one['tree'], 'The view with related cards and one batch of the tree is over the budget of docs/NODE.md');
        $this->assertLessThanOrEqual(6 + 2, $two['view'] + $two['tree'], 'The view with related cards and two batches of the tree is over the budget of docs/NODE.md');
    }

    # A wide level shows at most ten siblings on each side of the current document with its real numbers, a wide parent its first twenty children, each cut edge flagged
    #[Test]
    public function aWideLevelShowsAWindowAroundTheDocument(): void
    {
        $run = $this->getMode('tree')['wide'];
        $this->assertSame(['path' => 0, 'items' => 13, 'kids' => 0, 'cur' => 1, 'prev' => 1, 'next' => 1, 'more' => 1, 'first' => ''], $run['edge'],
            'The root Orphan near the start of 578 roots does not show itself, two before and ten after with one cut edge');
        $this->assertSame([21, 2, 'Zulu 300'], [$run['middle']['items'], $run['middle']['more'], $run['cur']], 'A document in the middle is not the centre of 21 entries');
        $this->assertMatchesRegularExpression('/^counter-reset: li [1-9][0-9]*$/', $run['middle']['first'], 'The window does not keep the real numbers of its entries');
        $this->assertSame(['path' => 3, 'items' => 1, 'kids' => 20, 'cur' => 1, 'prev' => 1, 'next' => 1, 'more' => 1, 'first' => ''], $run['kids'],
            'Advanced with 25 children does not show the first twenty and flag the rest');
    }

    # Every type shows its display mode: article is the base set, docs a contents row, faq an accordion, files a card with its download, media tiles with a poster
    #[Test]
    public function everyTypeShowsItsDisplayMode(): void
    {
        $run = $this->getMode('modes');
        $this->assertSame(['news' => [200, false, 2], 'docs' => [200, true, 0], 'faq' => [200, true, 0], 'files' => [200, true, 0], 'media' => [200, true, 0]], $run['list'],
            'A list does not answer or does not carry the classes of its mode');
        $this->assertSame([200, false], $run['news'], 'The article mode left the base set');
        $this->assertSame([1, ['article'], ['Doc one'], 0], [$run['docs']['count'], $run['docs']['tags'], $run['docs']['titles'], $run['docs']['buttons']],
            'The contents row of docs carries a button or misses its title');
        $this->assertSame([false, false, true], $run['docsview'], 'The docs view shows its views or author, or lost its date');
        $this->assertSame([2, ['details'], ['Why no mail arrives?', 'How to reset a password?']], [$run['faq']['count'], $run['faq']['tags'], $run['faq']['titles']],
            'The faq list is no accordion of its questions');
        $this->assertSame([true, false, false], $run['faqview'], 'The faq view does not ask its question or shows its views or author');
        $this->assertSame([['/files/asset/12'], ['/files/asset/11'], 3], [$run['files']['links'], $run['files']['images'],
            $run['files']['buttons']], 'The file card does not offer the download of the one material that has it beside both reading buttons');
        $this->assertNotEmpty(preg_grep('/^1[.,]95 KB$/', $run['files']['chips']), 'The file card hides the size of its download');
        $this->assertContains('318', $run['files']['chips'], 'The file card hides the count of its downloads');
        $this->assertSame(['bi-download', 'sl-entry-content'], $run['filesview'], 'The files view does not put its resources above the text');
        $this->assertSame([['Gallery only', 'Overview video'], ['/media/asset/16', '/media/asset/13'], 1],
            [$run['media']['titles'], $run['media']['images'], $run['grid']], 'The media tiles are not one grid, or the cover does not prefer the poster over the gallery');
        $this->assertContains('2026', $run['media']['chips'], 'The media tile hides the year of its field');
        $this->assertSame([['<video', 'sl-entry-content'], 1, ['Gallery only']], $run['mediaview'], 'The media view does not play above its text or loses the grid of its related');
    }

    # A list answers only the views of its type: the switch offers them, its POST keeps the choice in a cookie and returns to the list, anything else falls back
    #[Test]
    public function aListAnswersOnlyTheViewsOfItsType(): void
    {
        $run = $this->getMode('modes')['looks'];
        $news = ['cards', 'compact', 'tiles'];
        $this->assertSame([[$news, 'cards', ''], 303, '/news?page=2', [$news, 'compact', 'node-compact']], $run['news'],
            'The switch does not offer the views of news, or its POST does not set the view and return to the page of the list');
        $this->assertSame([$news, 'compact', 'node-compact'], $run['keep'], 'A view news does not offer or the token of another form changed the view');
        $this->assertSame([$news, 'cards', ''], $run['stale'], 'A cookie outside the views of the type did not fall back to its first view');
        $this->assertSame([[['list', 'rail'], 'list', 'node-row'], [['list', 'rail'], 'rail', 'duo sl-node-rail'], 1, 1], $run['docs'],
            'The docs list does not start in its first view, or the rail is not one tab and one pane per document');
        $this->assertSame([[], '', ''], $run['faq'], 'A type of one view draws the switch');
    }

    # An htmx request of a list gets the part alone with the title a reload shows, a plain one the whole page; "Show more" links the next page only
    #[Test]
    public function anHtmxRequestGetsTheListPartAlone(): void
    {
        $run = $this->getMode('modes')['live'];
        $this->assertSame([200, true, true, false, false], $run['part'], 'The htmx answer is not the part headed by its title, or it carries the page frame');
        $this->assertSame([200, true, true], $run['full'], 'A plain request does not get the whole page around the part');
        $this->assertSame([true, true], $run['vary'], 'The two answers of one address do not vary by HX-Request');
        $this->assertNotSame('', $run['title'][0], 'The part carries no title');
        $this->assertSame($run['title'][1], $run['title'][0], 'The title of the part is not the title of the page');
        $this->assertSame(['/news?page=2', ''], $run['more'], '"Show more" does not link the next page, or the last page offers it');
        $this->assertSame([true, 5], $run['marks'], 'The part is not the history element, or the toolbar, the three windows and the foot are not boosted');
        $this->assertSame(1, $this->getMode('head')['bar']['form'], 'The link of the public form is boosted into the list');
    }

    # The search hints of a type answer at most five links to what its search finds, nothing under the minimum length, and no title the visitor may not read
    #[Test]
    public function theSearchHintsFollowTheSearchOfTheType(): void
    {
        $run = $this->getMode('head')['hint'];
        $this->assertSame([200, 5, 5, false], $run['many'], 'The hints are not five links of the word, or they carry the page frame');
        $this->assertSame(['', '', 5], $run['short'], 'A word under the minimum of the search module gets hints, or one at the minimum gets none');
        $this->assertSame([['Beta', 'Gamma'], ['Beta', 'Gamma', 'Members']], $run['rights'],
            'A guest sees a closed category, a future or pending material, or the club reader misses the closed category');
        $this->assertSame('/news/view/105', $run['href'], 'A hint does not link the material');
        $this->assertSame(['', ''], $run['other'], 'A type outside the search or an unknown name answers hints');
    }

    # A page past the bound of the reader and a material past it are not found, and the run writes no PHP error
    #[Test]
    public function aPagePastTheReaderBoundIsNotFound(): void
    {
        $this->assertSame([404, 404, 404], $this->getMode('head')['bound'], 'A page or a material past the reader bound answered other than not found');
        [$count, $miss] = $this->getMode('head')['assets'];
        $this->assertGreaterThan(5, $count, 'The rendered page links no assets of the theme or the plugins');
        $this->assertSame([], $miss, 'An asset of the rendered page is not served from the document root public/');
    }

    # The notice of a submission shows once, the notice of a report shows once, and the header marquee shows the latest faq material
    #[Test]
    public function theNoticeShowsOnceAndTheMarqueeShowsTheLatest(): void
    {
        $run = $this->getMode('head');
        $this->assertSame([303, '/news', true, false], $run['notice'], 'The notice of a submission did not show exactly once');
        $this->assertSame([303, '/news/view/101', true, false], $run['report'], 'The notice of a report did not show exactly once');
        $this->assertStringContainsString('Doc one', $run['faq'], 'The header marquee does not show the latest faq material');
    }

    # A closed category stays out of the banner and the breadcrumb, a member who reads it sees it, and a view shows its own category
    #[Test]
    public function aClosedCategoryStaysOutOfTheHeader(): void
    {
        $this->assertSame([false, false, true, false, true], $this->getMode('head')['banner'], 'The banner or the breadcrumb shows a category the visitor may not read');
    }

    # The start page names the type once a category or a page is asked, letters keep the category, and an empty Node block is left out
    #[Test]
    public function theStartPageLettersAndEmptyBlocksFollowTheList(): void
    {
        $run = $this->getMode('head');
        $site = $run['site'];
        $this->assertSame([$site.'/news?cat=1', $site.'/news?page=2', $site.'/'], $run['canon'],
            'The canonical address of the start page names no type');
        $this->assertSame([true, true], $run['letters'], 'A letter drops the category of the list');
        $this->assertSame([false, false], $run['block'], 'An empty Node block shows its title or the problem notice');
    }

    # The header of a list links its sorts, the other direction, the reset, the feed with the category and the form; every link keeps the filters and the default sort stays clean
    #[Test]
    public function theListHeaderLinksItsSortsAndKeepsItsFilters(): void
    {
        $bar = $this->getMode('head')['bar'];
        $list = '/news';
        $tail = ['/index.php?go=rss&name=news', $list.'/add'];
        $this->assertSame([$list, $list.'?dir=asc&order=title', $list.'?dir=desc&order=views', $list.'?dir=asc&order=published', ...$tail], $bar['guest'][0],
            'The guest header misses a sort, the direction or the feed, or offers a personal filter');
        $this->assertSame([$list, $list.'?dir=asc&order=title', $list.'?dir=desc&order=views', $list.'?dir=asc&order=published', $list.'?new=1', $list.'?fav=1', ...$tail],
            $bar['anna'][0], 'The header of a signed-in visitor misses a personal filter');
        $this->assertSame([$list.'?new=1', $list.'?dir=asc&new=1&order=title', $list.'?dir=desc&new=1&order=views', $list.'?dir=asc&new=1&order=published', $list,
            $list.'?fav=1&new=1', $list, ...$tail], $bar['new'][0], 'A link of the header drops the filter of the list, or the filter cannot be switched off');
        $this->assertSame([301, $list.'?new=1', [$list.'?fav=1', $list.'?dir=asc&fav=1&order=title', $list.'?dir=desc&fav=1&order=views', $list.'?dir=desc&fav=1&order=title',
            $list.'?dir=asc&fav=1&new=1&order=title', $list.'?dir=asc&order=title', $list, ...$tail]], $bar['keep'],
            'The default sort does not return to the clean address with its filter, or a link loses the chosen sort');
        $this->assertSame(['/index.php?go=rss&name=news&cat=1'], $bar['feed'], 'The feed of the header is not the channel of the category');
        $this->assertSame([400, 400, 400], $bar['bad'], 'fav on a type without favorites or a personal filter other than 1 passed');
    }

    # The header counts what its reader reads, kept per readable set until a publication or a write, and the previous visit holds for the session
    #[Test]
    public function theHeaderCountsFollowTheReaderAndTheirCache(): void
    {
        $bar = $this->getMode('head')['bar'];
        $this->assertSame(['3'], $bar['guest'][1], 'The guest count is not the readable total');
        $this->assertSame(['4', '2', '1'], $bar['anna'][1], 'The total, the news since the last visit or the favorites of anna are miscounted');
        $this->assertSame([['2 4', '2', '1'], [103, 105]], array_slice($bar['new'], 1), 'The list since the last visit is not the news after it');
        $this->assertSame([['1 4', '2', '1'], [101], 200, []], $bar['fav'], 'The favorites list is not the favorite of anna, or both filters do not intersect');
        $this->assertSame([true, ['4', '2', '1']], $bar['visit'], 'The previous visit moved with the column within the session');
        $this->assertSame([200, true, ['3'], false], $bar['loose'], 'A personal filter of a guest narrowed the list, was indexed or stayed in a link');
        $this->assertSame([[true, false, false], [true, false], ['1', '1', '1']], $bar['counts'],
            'A letter without readable materials is linked, or a category tile does not carry its count');
        $this->assertSame([['c1', 'c1-2'], true, 3, 4], $bar['cache'], 'The counts are not kept per readable set until the next publication');
        $this->assertSame([303, true, true], $bar['drop'], 'A written material did not drop the counts of its type');
        $this->assertSame([1, 1], [preg_match('/^3 \S+$/u', $bar['words'][0]), preg_match('/^2 \S+ 4 \S+$/u', $bar['words'][1])],
            'The count chip does not name its materials in the form of its number');
        $this->assertSame([1, 1, []], [count($bar['months'][0]), preg_match('/^\S+ 2026 2$/u', $bar['months'][0][0] ?? ''), $bar['months'][1]],
            'A list by publication is not headed by its month with the count of its cards on the page, or a list by title is');
        $this->assertSame([true, false, true], $bar['catlets'], 'A letter of a category is counted over the whole type');
    }

    # The favorites shelf reads without a token in its address, answers the member and nothing to a guest
    #[Test]
    public function theFavoritesShelfReadsWithoutAToken(): void
    {
        $this->assertSame([true, false, 200, true, ''], $this->getMode('head')['favorites'], 'The favorites shelf carries the token in its address or refuses the read');
    }
}
