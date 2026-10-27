<?php

declare(strict_types=1);

namespace Tests\Unit;

use Layout;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Template;
use Update;

# The layout of config/layout.php: its shape, the pages and their slot, the one visibility scale, the types of the roots and the merge of an update
final class LayoutTest extends TestCase
{
    private static string $theme = '';
    private static string $tdir = '';
    private static string $mods = '';

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/template.php';
        require_once BASE_DIR.'/core/classes/layout.php';
        require_once BASE_DIR.'/core/classes/update.php';
        self::$theme = 'scratch-'.substr(sha1('layout'.getmypid().microtime(true)), 0, 8);
        self::$tdir = PUBLIC_DIR.'/templates/'.self::$theme;
        self::$mods = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_layout_mods_'.getmypid();
        $files = [
            self::$tdir.'/sections/hero.json' => '{"title": "_HERO"}',
            self::$tdir.'/sections/hero.html' => '<p class="hero">{{ title }}</p>',
            self::$tdir.'/sections/text.json' => '{"title": "_TEXT"}',
            self::$tdir.'/sections/text.html' => '<p class="text">{{ body }}</p>',
            self::$tdir.'/sections/bare.json' => '{"title": "_BARE"}',
            self::$tdir.'/sections/module.json' => '{"title": "_SLOT"}',
            self::$tdir.'/sections/Bad_Name.json' => '{"title": "_BAD"}',
            self::$tdir.'/sections/list.json' => '["a", "b"]',
            self::$tdir.'/sections/feed.html' => '<p class="feed">{{ title }} {{ count }}</p>',
            self::$tdir.'/places.json' => '{"parts": {"brand": {"fields": []}}, "menus": {"main": {"depth": 2}}, "zones": "broken"}',
            self::$mods.'/probe/sections/feed.json' => '{"title": "_FEED"}',
            self::$mods.'/probe/sections/feed.php' => '<?php return static fn(array $sec): array => ["count" => 7];',
            self::$mods.'/probe/sections/hero.json' => '{"title": "_MODHERO"}',
            self::$mods.'/probe/sections/card.json' => '{"title": "_CARD"}',
            self::$mods.'/probe/sections/card.html' => '<p class="card"></p>',
        ];
        foreach ($files as $file => $body) {
            if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
            file_put_contents($file, $body);
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$tdir, self::$mods, BASE_DIR.'/storage/cache/templates/'.self::$theme] as $root) {
            if ($root === '' || !is_dir($root)) continue;
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($walk as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            rmdir($root);
        }
    }

    # A section as the builder writes it: switched on, for everyone, with the fields of its type
    private function getSection(int $id, string $type, array $over = []): array
    {
        return array_replace(['id' => $id, 'type' => $type, 'on' => true, 'who' => '0'], $over);
    }

    # A layout of the current version with a home page, a page 404, a page of a module and a page with a hidden slot
    private function getLayout(array $over = []): array
    {
        return array_replace([
            'v' => Layout::VERSION,
            'shipped' => false,
            'next' => 30,
            'home' => 1,
            'notfound' => 2,
            'theme' => ['tagline' => 'Line'],
            'menus' => [],
            'pages' => [
                1 => ['side' => '3', 'top' => '3', 'sections' => [$this->getSection(10, 'hero', ['title' => 'Home']), $this->getSection(11, 'module', ['module' => ''])]],
                2 => ['sections' => [$this->getSection(12, 'module', ['module' => 'news'])]],
                3 => ['side' => '2', 'top' => '0', 'sections' => [$this->getSection(13, 'module', ['module' => 'news'])]],
                4 => ['sections' => [$this->getSection(14, 'module', ['module' => 'forum', 'who' => '1'])]],
                5 => ['sections' => [$this->getSection(15, 'module', ['module' => 'faq', 'on' => false])]],
            ],
        ], $over);
    }

    # The layout as one visitor sees it, the types read from the scratch theme and the scratch modules
    private function getReader(array $data, bool $user = false, bool $moder = false): Layout
    {
        return new Layout($data, self::$theme, $user, $moder, self::$mods);
    }

    # The scale of _blocks.view: all, users, moderators, guests; a moderator sees every value, an unknown value nobody else
    #[Test]
    public function theViewScaleOfBlocksHoldsForEveryViewer(): void
    {
        $want = [
            [0, false, false, true], [0, true, false, true],
            [1, false, false, false], [1, true, false, true],
            [2, false, false, false], [2, true, false, false], [2, false, true, true],
            [3, false, false, true], [3, true, false, false], [3, true, true, true],
            [7, true, false, false], [7, false, true, true],
        ];
        foreach ($want as [$view, $user, $moder, $seen]) {
            $this->assertSame($seen, Layout::isViewShown($view, $user, $moder), 'view '.$view.', user '.var_export($user, true).', moderator '.var_export($moder, true));
        }
    }

    # getBlocks() asks the one function for both of its paths, the free block and the column, and keeps no copy of the scale
    #[Test]
    public function theBlocksReadTheOneScale(): void
    {
        $code = (string)file_get_contents(BASE_DIR.'/core/system.php');
        $this->assertSame(2, substr_count($code, 'Layout::isViewShown($view, is_user() > 0, is_moder() > 0)'));
        $this->assertSame(0, preg_match('#\$view == [0-3] &&#', $code), 'core/system.php checks a value of the scale inline again');
    }

    # A file of another version or without its lists reads as a layout of no page; a page without its list of sections is dropped
    #[Test]
    public function aForeignShapeReadsAsNoPage(): void
    {
        $none = ['v' => Layout::VERSION, 'shipped' => false, 'next' => 1, 'home' => 0, 'notfound' => 0, 'theme' => [], 'menus' => [], 'pages' => []];
        $this->assertSame($none, Layout::getLayoutData([]));
        $this->assertSame($none, Layout::getLayoutData($this->getLayout(['v' => Layout::VERSION + 1])));
        $this->assertSame($none, Layout::getLayoutData($this->getLayout(['v' => (string)(Layout::VERSION + 1)])));
        $this->assertSame($none, Layout::getLayoutData($this->getLayout(['v' => [Layout::VERSION]])));
        $this->assertSame($none, Layout::getLayoutData($this->getLayout(['pages' => 'none'])));
        $data = Layout::getLayoutData($this->getLayout(['pages' => [1 => ['sections' => []], 6 => ['title' => 'No list'], 7 => 'text'], 'extra' => 1]));
        $this->assertSame([1], array_keys($data['pages']));
        $this->assertArrayNotHasKey('extra', $data);
        $this->assertSame(0, $this->getReader(['v' => 0, 'home' => 1])->getHomeKey());
    }

    # The delivered file is of this version and marked shipped; its home page holds the slot of the home module of the settings and has no columns
    #[Test]
    public function theShippedLayoutIsNeutral(): void
    {
        $file = require BASE_DIR.'/config/layout.php';
        $this->assertSame(['layout'], array_keys($file));
        $data = $file['layout'];
        $this->assertSame($data, Layout::getLayoutData($data), 'The delivered file is not in the one shape');
        $this->assertTrue($data['shipped']);
        $this->assertSame([], $data['menus']);
        $read = $this->getReader($data);
        $home = $read->getHomeKey();
        $this->assertSame(1, $home);
        $this->assertSame('', $read->getSlotName($home));
        $this->assertSame(['3', '3'], $read->getPageColumns($home));
        $this->assertTrue($read->getPage($data['notfound'])['noindex']);
        $ids = [];
        foreach ($data['pages'] as $key => $page) {
            $ids[] = $key;
            foreach ($page['sections'] as $sec) $ids[] = $sec['id'];
        }
        $this->assertGreaterThan(max($ids), $data['next'], 'The counter of the file would give a number again');
    }

    # The merge of an update writes the delivered file through the string form of setConfigFile(), every scalar as text; the site still has its home page and its slot
    #[Test]
    public function theTextAnUpdateWritesReadsAsTheLayout(): void
    {
        $norm = static function (mixed $val) use (&$norm): array|string {
            if (is_array($val)) return array_map($norm, $val);
            return is_bool($val) ? (string)(int)$val : (string)$val;
        };
        $file = require BASE_DIR.'/config/layout.php';
        $read = $this->getReader($norm($file['layout']));
        $data = $read->getData();
        $this->assertSame([Layout::VERSION, true, 1, 2], [$data['v'], $data['shipped'], $data['home'], $data['notfound']]);
        $this->assertSame(1, $read->getHomeKey());
        $this->assertSame('', $read->getSlotName(1));
        $this->assertSame(['3', '3'], $read->getPageColumns(1));
        $this->assertFalse(Layout::getLayoutData($norm(array_replace($file['layout'], ['shipped' => false])))['shipped']);
    }

    # The home page and the page 404 never answer for a module; the first page whose shown slot holds it does, a hidden or switched off slot none
    #[Test]
    public function aPageAnswersForTheModuleOfItsSlot(): void
    {
        $guest = $this->getReader($this->getLayout());
        $this->assertSame(1, $guest->getHomeKey());
        $this->assertSame('', $guest->getSlotName(1));
        $this->assertSame(3, $guest->getModuleKey('news'));
        $this->assertSame(0, $guest->getModuleKey('forum'));
        $this->assertSame(0, $guest->getModuleKey('faq'));
        $this->assertSame(0, $guest->getModuleKey(''));
        $this->assertNull($guest->getSlotName(4));
        $this->assertNull($guest->getSlotName(99));
        $this->assertSame(4, $this->getReader($this->getLayout(), true)->getModuleKey('forum'));
        $this->assertSame(['2', '0'], $guest->getPageColumns(3));
        $this->assertSame(['3', '3'], $guest->getPageColumns(2));
        $this->assertSame(0, $this->getReader($this->getLayout(['home' => 9]))->getHomeKey());
    }

    # The sections render through the theme in their order, the module's output in the slot; what is hidden, off or has no fragment of the theme prints nothing
    #[Test]
    public function aPageRendersItsSectionsAroundTheModule(): void
    {
        $page = ['sections' => [
            $this->getSection(1, 'hero', ['title' => 'A & B']),
            $this->getSection(2, 'module'),
            $this->getSection(3, 'text', ['body' => 'Users', 'who' => '1']),
            $this->getSection(4, 'text', ['body' => 'Off', 'on' => false]),
            $this->getSection(5, 'bare'),
            $this->getSection(6, 'nosuch'),
            $this->getSection(7, 'feed', ['title' => 'Feed']),
            $this->getSection(8, 'card'),
            $this->getSection(9, 'module'),
        ]];
        $data = $this->getLayout(['pages' => [1 => $page, 2 => ['sections' => [$this->getSection(1, 'hero', ['title' => 'Top'])]]]]);
        $tpl = new Template(self::$theme);
        $html = (string)preg_replace('#\s+#', ' ', $this->getReader($data)->getPageHtml(1, '<main>BODY</main>', $tpl));
        $this->assertSame('<p class="hero">A &amp; B</p><main>BODY</main><p class="feed">Feed 7</p>', str_replace('> <', '><', trim($html)));
        $user = $this->getReader($data, true)->getPageHtml(1, '', $tpl);
        $this->assertStringContainsString('<p class="text">Users</p>', $user);
        $this->assertStringNotContainsString('Off', $user);
        $this->assertStringEndsWith('<main>BODY</main>', $this->getReader($data)->getPageHtml(2, '<main>BODY</main>', $tpl), 'Output no slot took was dropped');
    }

    # The theme describes first and its fragment wins, a module adds its own types with a provider; the slot, a bad name and a list are no type
    #[Test]
    public function theTypesComeFromTheThemeAndTheModules(): void
    {
        $types = $this->getReader($this->getLayout())->getSectionTypes();
        $this->assertSame(['bare', 'hero', 'text', 'card', 'feed'], array_keys($types));
        $this->assertSame(['root' => 'theme', 'desc' => ['title' => '_HERO'], 'frag' => 'theme', 'prov' => ''], $types['hero']);
        $this->assertSame('', $types['bare']['frag']);
        $this->assertSame(['probe', 'theme'], [$types['feed']['root'], $types['feed']['frag']]);
        $this->assertStringEndsWith('/probe/sections/feed.php', $types['feed']['prov']);
        $this->assertSame(['probe', 'probe', ''], [$types['card']['root'], $types['card']['frag'], $types['card']['prov']]);
    }

    # The places of the theme are its places.json: three lists, a missing or broken one empty
    #[Test]
    public function theThemeDescribesItsPlaces(): void
    {
        $places = $this->getReader($this->getLayout())->getThemePlaces();
        $this->assertSame(['parts' => ['brand' => ['fields' => []]], 'menus' => ['main' => ['depth' => 2]], 'zones' => []], $places);
        $this->assertSame(['parts' => [], 'menus' => [], 'zones' => []], (new Layout([], 'scratch-00000000', false, false, self::$mods))->getThemePlaces());
    }

    # A menu gives the items its viewer may see, at every depth
    #[Test]
    public function aMenuKeepsTheItemsOfItsViewer(): void
    {
        $item = static fn(string $title, string $who, array $kids = []): array => ['id' => 1, 'title' => $title, 'href' => '', 'who' => $who, 'kids' => $kids];
        $data = $this->getLayout(['menus' => ['main' => [$item('All', '0', [$item('Users', '1'), $item('Guests', '3')]), $item('Admins', '2'), 'broken']]]);
        $titles = static function (array $list) use (&$titles): array {
            return array_map(static fn(array $one): array => [$one['title'], $titles($one['kids'])], $list);
        };
        $this->assertSame([['All', [['Guests', []]]]], $titles($this->getReader($data)->getMenu('main')));
        $this->assertSame([['All', [['Users', []]]]], $titles($this->getReader($data, true)->getMenu('main')));
        $this->assertSame([['All', [['Users', []], ['Guests', []]]], ['Admins', []]], $titles($this->getReader($data, true, true)->getMenu('main')));
        $this->assertSame([], $this->getReader($data)->getMenu('foot'));
    }

    # An update adds the layout to a site that lacks it and new keys of the theme, but never a page or a menu item the site deleted
    #[Test]
    public function anUpdateKeepsThePagesAndMenusOfTheSite(): void
    {
        $pack = $this->getLayout(['theme' => ['tagline' => '', 'phone' => ''], 'menus' => ['main' => [['id' => 20, 'title' => 'New']]]]);
        $this->assertSame($pack, Update::getConfigMerge([], $pack, [], 'layout')['data']);
        $site = $this->getLayout(['pages' => [1 => $pack['pages'][1]], 'menus' => ['main' => []]]);
        $res = Update::getConfigMerge($site, $pack, [], 'layout');
        $this->assertSame([1], array_keys($res['data']['pages']));
        $this->assertSame([], $res['data']['menus']['main']);
        $this->assertSame(['tagline' => 'Line', 'phone' => ''], $res['data']['theme']);
        $this->assertSame(['layout.theme.phone'], $res['adds']);
    }
}
