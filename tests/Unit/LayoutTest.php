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
        require_once BASE_DIR.'/core/classes/field.php';
        if (!class_exists('Parser', false)) require_once BASE_DIR.'/core/classes/parser.php';
        self::$theme = 'scratch-'.substr(sha1('layout'.getmypid().microtime(true)), 0, 8);
        self::$tdir = PUBLIC_DIR.'/templates/'.self::$theme;
        self::$mods = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_layout_mods_'.getmypid();
        $files = [
            self::$tdir.'/sections/hero.json' => '{"title": "_HERO", "fields": [{"key": "title", "type": "text"}]}',
            self::$tdir.'/sections/hero.html' => '<p class="hero">{{ title }}</p>',
            self::$tdir.'/sections/text.json' => '{"title": "_TEXT", "fields": [{"key": "body", "type": "text"}]}',
            self::$tdir.'/sections/text.html' => '<p class="text">{{ body }}</p>',
            self::$tdir.'/sections/bare.json' => '{"title": "_BARE"}',
            self::$tdir.'/sections/module.json' => '{"title": "_SLOT"}',
            self::$tdir.'/sections/Bad_Name.json' => '{"title": "_BAD"}',
            self::$tdir.'/sections/list.json' => '["a", "b"]',
            self::$tdir.'/sections/feed.html' => '<p class="feed">{{ title }} {{ count }}</p>',
            self::$tdir.'/places.json' => '{"parts": {"brand": {"fields": []}}, "menus": {"main": {"depth": 2}}, "zones": "broken"}',
            self::$mods.'/probe/sections/feed.json' => '{"title": "_FEED", "fields": [{"key": "title", "type": "text"}]}',
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

    # An item of a menu as the builder writes it, for everyone and without children
    private function getMenuItem(string $title, string $href, array $over = []): array
    {
        return array_replace(['id' => 1, 'title' => $title, 'href' => $href, 'who' => '0', 'kids' => []], $over);
    }

    # The layout as one visitor sees it in the language of a multilingual site, the types read from the scratch theme and the scratch modules
    private function getReader(array $data, bool $user = false, bool $moder = false, string $lang = ''): Layout
    {
        return new Layout($data, self::$theme, $user, $moder, $lang, self::$mods);
    }

    # A parser that marks the text it was given and the module it renders for, so a test sees which values went through it
    private function getParser(): \Parser
    {
        return new class () extends \Parser {
            public function filterDoc(string $src, bool $safe = true, string $mod = '', int $hoff = 0, string $fmt = '', array $own = [], bool $trust = false): string
            {
                return '<md data-mod="'.$mod.'" data-safe="'.(int)$safe.'">'.htmlspecialchars($src).'</md>';
            }
        };
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

    # The delivered config/layout.php as the index of git holds it: the working file of the stand is a layout of its own (.rules/git.md), never the delivery
    private static function getShippedFile(): array
    {
        $proc = proc_open(['git', '-C', BASE_DIR, 'show', ':config/layout.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $code = (string)stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proc) !== 0) $code = (string)file_get_contents(BASE_DIR.'/config/layout.php');
        $file = (string)tempnam(sys_get_temp_dir(), 'slaed_layout_');
        file_put_contents($file, $code);
        $data = require $file;
        unlink($file);
        return $data;
    }

    # The delivered file is of this version and marked shipped; its home page holds the slot of the home module of the settings and has no columns
    #[Test]
    public function theShippedLayoutIsNeutral(): void
    {
        $file = self::getShippedFile();
        $this->assertSame(['layout'], array_keys($file));
        $data = $file['layout'];
        $this->assertSame($data, Layout::getLayoutData($data), 'The delivered file is not in the one shape');
        $this->assertTrue($data['shipped']);
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
        foreach ($data['menus'] as $list) foreach ($list as $item) $ids[] = $item['id'];
        $this->assertGreaterThan(max($ids), $data['next'], 'The counter of the file would give a number again');
    }

    # The delivery carries a menu of the site's own sections in every menu of lite, every key of its parts and no value of one site: no contact, link, mark or tab
    #[Test]
    public function theShippedChromeIsNeutral(): void
    {
        $data = self::getShippedFile()['layout'];
        $places = (new Layout([], 'lite', false, false))->getThemePlaces();
        $this->assertSame(array_keys($places['menus']), array_keys($data['menus']));
        foreach ($data['menus'] as $list) {
            $this->assertNotSame([], $list);
            foreach ($list as $item) $this->assertMatchesRegularExpression('#^/[a-z]*$#D', $item['href'], 'A shipped item leads outside the site');
        }
        $keys = [];
        foreach ($places['parts'] as $part) {
            foreach ($part['fields'] as $one) $keys[] = $one['key'];
            if (isset($part['items'])) $keys[] = $part['items']['key'];
        }
        sort($keys);
        $have = array_keys($data['theme']);
        sort($have);
        $this->assertSame($keys, $have, 'An update brings a site the keys of the delivery, so it carries every key lite declares');
        $this->assertSame([], array_filter($data['theme'], static fn(mixed $v): bool => !in_array($v, ['', false, []], true)), 'The delivery carries a value of one site');
    }

    # The merge of an update writes the delivered file through the string form of setConfigFile(), every scalar as text; the site still has its home page and its slot
    #[Test]
    public function theTextAnUpdateWritesReadsAsTheLayout(): void
    {
        $norm = static function (mixed $val) use (&$norm): array|string {
            if (is_array($val)) return array_map($norm, $val);
            return is_bool($val) ? (string)(int)$val : (string)$val;
        };
        $file = self::getShippedFile();
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

    # The sections render in their order, the module's output in the slot, a module's own fragment where the theme has none; what is hidden, off or has no fragment prints nothing
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
        $html = (string)preg_replace('#\s+#', ' ', $this->getReader($data)->getPageHtml(1, '<main>BODY</main>', $tpl, $this->getParser()));
        $this->assertSame('<p class="hero">A &amp; B</p><main>BODY</main><p class="feed">Feed 7</p><p class="card"></p>', str_replace('> <', '><', trim($html)));
        $user = $this->getReader($data, true)->getPageHtml(1, '', $tpl, $this->getParser());
        $this->assertStringContainsString('<p class="text">Users</p>', $user);
        $this->assertStringNotContainsString('Off', $user);
        $this->assertStringEndsWith('<main>BODY</main>', $this->getReader($data)->getPageHtml(2, '<main>BODY</main>', $tpl, $this->getParser()), 'Output no slot took was dropped');
    }

    # A section of lite prints each value as its kind asks: Field refuses a bad address, a bad picture, icon or tone prints nothing, a list stops at its max
    #[Test]
    public function aSectionOfLitePrintsItsValuesByTheirKinds(): void
    {
        $page = ['sections' => [
            $this->getSection(1, 'hero', ['over' => 'Over', 'title' => 'A < B', 'accent' => 'Acc', 'text' => "One\nTwo", 'btn' => 'Go', 'href' => 'javascript:alert(1)',
                'btn2' => 'Docs', 'href2' => '/docs', 'pic' => '1', 'photo' => 'presentation/brand/background-1.png', 'alt' => 'Mark', 'focus' => 'left top', 'anchor' => 'top']),
            $this->getSection(2, 'media', ['title' => 'Media', 'icon' => 'bad icon', 'text' => '**Bold**', 'btn' => 'More', 'href' => 'https://example.org/',
                'photo' => 'presentation/../../config/db.php', 'side' => 'right', 'phone' => false]),
            $this->getSection(3, 'features', ['title' => 'Feat', 'icon' => 'star', 'look' => 'line', 'cols' => 'four', 'tone' => 'nosuch',
                'items' => array_fill(0, 10, ['icon' => 'star', 'title' => 'Card', 'text' => 'Why'])]),
            $this->getSection(4, 'steps', ['title' => 'Steps', 'items' => [['title' => 'First', 'text' => ''], 'broken', ['title' => 'Second', 'text' => 'Then']]]),
            $this->getSection(5, 'text', ['title' => 'Later', 'body' => 'x', 'from' => '2999-01-01']),
            $this->getSection(6, 'text', ['title' => 'Gone', 'body' => 'x', 'till' => '2000-01-01']),
            $this->getSection(7, 'media', ['title' => 'Flip', 'text' => '', 'photo' => 'presentation/brand/background-4.png', 'side' => 'right']),
            $this->getSection(8, 'cta', ['title' => 'Call', 'btn' => 'Write', 'href' => '/contact', 'desk' => '']),
        ]];
        $read = new Layout($this->getLayout(['pages' => [1 => $page]]), 'lite', false, false, '', self::$mods);
        $html = $read->getPageHtml(1, '', new Template('lite'), $this->getParser());
        $this->assertStringContainsString('id="top"', $html);
        $this->assertStringContainsString('<b>A &lt; B</b> <span>Acc</span>', $html);
        $this->assertStringContainsString("<p class=\"sl-pres-lead sl-sec-lines\">One\nTwo</p>", $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('>Go<', $html, 'A button without a valid address printed');
        $this->assertStringContainsString('<a class="sl-but" href="/docs">Docs</a>', $html, 'An address of the site opened a new tab');
        $this->assertStringContainsString('<a class="sl-but" href="https://example.org/" target="_blank" rel="noopener">More</a>', $html);
        $this->assertStringContainsString('src="/uploads/presentation/brand/background-1.png" alt="Mark" style="--sl-d-focus: left top"', $html);
        $this->assertStringContainsString('<md data-mod="layout" data-safe="1">**Bold**</md>', $html);
        $this->assertStringNotContainsString('db.php', $html);
        $this->assertStringContainsString('sl-sec-media sl-sec-solo', $html);
        $this->assertStringContainsString('sl-sec-media sl-sec-flip', $html);
        $this->assertStringContainsString('bi-layout-text-sidebar sl-pres-icon', $html, 'A bad icon did not fall back to the icon of the type');
        $this->assertStringContainsString('sl-sec-no-phone', $html);
        $this->assertStringContainsString('sl-sec-no-desk', $html);
        $this->assertStringContainsString('sl-pres-tech sl-sec-cols-four', $html);
        $this->assertSame(8, substr_count($html, 'sl-pres-tech-card'));
        $this->assertStringNotContainsString('data-sl-tone', $html);
        $this->assertMatchesRegularExpression('#>01</span><div><b>First</b></div>.*>02</span><div><b>Second</b><span class="sl-pres-tags"><span>Then</span>#s', $html);
        $this->assertStringNotContainsString('Later', $html);
        $this->assertStringNotContainsString('Gone', $html);
        $this->assertStringContainsString('<a class="sl-but sl-sec-cta-but" href="/contact">Write</a>', $html);
        preg_match_all('#<span class="sl-pres-num">(\d+)</span>#', $html, $hit);
        $this->assertSame(['01', '02', '03', '04'], $hit[1], 'Only the framed sections are numbered, in their order');
    }

    # The edit mode of the builder marks each section, the slot and the texts of the fields for builder.js; a visitor's page carries none of them
    #[Test]
    public function theEditModeMarksThePartsOfThePage(): void
    {
        $page = ['sections' => [
            $this->getSection(1, 'hero', ['title' => 'Head', 'text' => 'Lead']),
            $this->getSection(2, 'module'),
            $this->getSection(3, 'steps', ['title' => 'Steps', 'items' => [['title' => 'First', 'text' => 'Then']]]),
            $this->getSection(4, 'text', ['title' => 'Body', 'body' => 'x']),
        ]];
        $data = $this->getLayout(['pages' => [1 => $page]]);
        $read = new Layout($data, 'lite', false, false, '', self::$mods);
        $plain = $read->getPageHtml(1, '<main>BODY</main>', new Template('lite'), $this->getParser());
        $this->assertStringNotContainsString('data-sl-pick', $plain, 'A visitor got the marks of the builder');
        $this->assertStringNotContainsString('data-sl-text', $plain);
        $this->assertStringNotContainsString('data-sl-md', $plain);
        $this->assertFalse($read->isBuildMode());
        $read->setBuildMode();
        $html = $read->getPageHtml(1, '<main>BODY</main>', new Template('lite'), $this->getParser());
        $this->assertTrue($read->isBuildMode());
        $this->assertStringContainsString('data-sl-pick="sec:1"', $html);
        $this->assertStringContainsString('<div data-sl-pick="sec:2" data-sl-build-one><main>BODY</main></div>', $html, 'The slot is not picked around the module');
        $this->assertStringContainsString('<b data-sl-text="title">Head</b>', $html);
        $this->assertStringContainsString('data-sl-text="text" data-sl-lines>Lead</p>', $html);
        $this->assertStringContainsString('<b data-sl-text="items.01.title">First</b>', $html, 'An item is not marked by its place');
        $this->assertStringContainsString('<h2 data-sl-text="title">Steps</h2>', $html, 'The head of a framed section is not marked');
        $this->assertStringContainsString('data-sl-md="body"', $html);
    }

    # Every text lite marks names a field: of its type in a section (an item of its list, Markdown or own HTML by kind), of a part or a menu item in the chrome
    #[Test]
    public function theMarksOfLiteNameTheFieldsOfTheirTypes(): void
    {
        foreach (glob(PUBLIC_DIR.'/templates/lite/sections/*.html') ?: [] as $file) {
            $type = basename($file, '.html');
            $json = is_file(dirname($file).'/'.$type.'.json') ? dirname($file).'/'.$type.'.json' : BASE_DIR.'/modules/presentation/sections/'.$type.'.json';
            $desc = is_file($json) ? json_decode((string)file_get_contents($json), true) : [];
            $kinds = array_column(is_array($desc['fields'] ?? null) ? $desc['fields'] : [], 'type', 'key');
            $kinds += ['title' => 'text', 'lead' => 'text'];
            $items = array_column(is_array($desc['items']['fields'] ?? null) ? $desc['items']['fields'] : [], 'type', 'key');
            preg_match_all('#data-sl-(text|md)="([^"]+)"#', (string)file_get_contents($file), $hit, PREG_SET_ORDER);
            foreach ($hit as [, $mark, $path]) {
                $item = preg_match('#^items\.\{\{ item\.order_num \}\}\.([a-z0-9]+)$#', $path, $one) === 1;
                $kind = $item ? ($items[$one[1]] ?? '') : ($kinds[$path] ?? '');
                $this->assertNotSame('', $kind, $type.'.html marks '.$path.', which its type does not describe');
                $want = ($mark === 'md') ? ['markdown', 'code'] : ['text', 'textarea'];
                $this->assertContains($kind, $want, $type.'.html marks '.$path.' of the kind '.$kind.' as '.$mark);
            }
        }
        $head = (string)file_get_contents(PUBLIC_DIR.'/templates/lite/fragments/presentation-head.html');
        $this->assertStringContainsString('data-sl-text="title"', $head, 'The head of a framed section is not marked');
        $places = json_decode((string)file_get_contents(PUBLIC_DIR.'/templates/lite/places.json'), true);
        [$fields, $lists] = [['title' => true], []];
        foreach ($places['parts'] as $part) {
            foreach ($part['fields'] ?? [] as $one) $fields[$one['key']] = true;
            if (isset($part['items']['key'])) $lists[$part['items']['key']] = array_column($part['items']['fields'], 'type', 'key');
        }
        foreach (['site-header', 'site-footer', 'menu'] as $name) {
            $code = (string)file_get_contents(PUBLIC_DIR.'/templates/lite/partials/'.$name.'.html');
            preg_match_all('#data-sl-pick="(part|menu):([a-z]+)#', $code, $hit, PREG_SET_ORDER);
            foreach ($hit as [, $kind, $key]) $this->assertArrayHasKey($key, $places[$kind.'s'], $name.'.html picks the '.$kind.' '.$key.', which lite does not declare');
            preg_match_all('#data-sl-text="([^"]+)"#', $code, $hit);
            foreach ($hit[1] as $path) {
                $item = preg_match('#^([a-z]+)\.\{\{ [a-z]+\.order_num \}\}\.([a-z0-9]+)$#', $path, $one) === 1;
                $this->assertTrue($item ? isset($lists[$one[1]][$one[2]]) : isset($fields[$path]), $name.'.html marks '.$path.', which no part of lite describes');
            }
        }
    }

    # Every section of lite and of the modules has its fragment, fields of the closed kinds under names a fragment receives, labels and defaults naming defined constants
    #[Test]
    public function theSectionsOfLiteAreDescribedInFull(): void
    {
        $dir = PUBLIC_DIR.'/templates/lite/sections';
        $lang = file_get_contents(BASE_DIR.'/admin/lang/ru.php').file_get_contents(BASE_DIR.'/lang/ru.php');
        foreach (glob(BASE_DIR.'/modules/*/sections', GLOB_ONLYDIR) ?: [] as $one) $lang .= (string)file_get_contents(dirname($one).'/lang/ru.php');
        preg_match_all("/^define\('(_[A-Z0-9_]+)'/m", $lang, $hit);
        $defs = array_flip($hit[1]);
        $lost = ['path', 'file', 'data', 'real', 'lev', 'iscode', 'scope', 'items', 'parts'];
        $kinds = ['text', 'textarea', 'select', 'bool', 'int', 'url', 'markdown', 'code', 'file', 'focus', 'icon', 'tone', 'video', 'page', 'point'];
        $groups = [];
        $files = array_merge(glob($dir.'/*.json') ?: [], glob(BASE_DIR.'/modules/*/sections/*.json') ?: []);
        foreach ($files as $file) {
            $type = basename($file, '.json');
            $desc = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFileExists($dir.'/'.$type.'.html', $type.' has no fragment in lite');
            foreach ([$desc['title'], $desc['note']] as $name) $this->assertArrayHasKey($name, $defs, $type.' names an undefined '.$name);
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $desc['icon']);
            $this->assertIsBool($desc['frame']);
            $groups[$desc['group']][] = $type;
            $sets = [[$desc['fields'], $desc['make']]];
            if (isset($desc['items'])) {
                $this->assertArrayHasKey($desc['items']['label'], $defs);
                $this->assertGreaterThan(0, $desc['items']['max']);
                $this->assertContains($desc['items']['name'], array_column($desc['items']['fields'], 'key'));
                $sets[] = [$desc['items']['fields'], $desc['items']['make']];
                foreach ($desc['make']['items'] as $item) $sets[] = [$desc['items']['fields'], $item];
            }
            foreach ($sets as [$fields, $make]) {
                $keys = array_column($fields, 'key');
                $this->assertEqualsCanonicalizing($keys, array_values(array_diff(array_keys($make), ['items'])), $type.': the defaults and the fields differ');
                foreach ($fields as $one) {
                    $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]{0,31}$/D', $one['key']);
                    $this->assertNotContains($one['key'], $lost, $type.'.'.$one['key'].' is a name Template::getView() never hands to the fragment');
                    $this->assertContains($one['type'], $kinds, $type.'.'.$one['key']);
                    $this->assertArrayHasKey($one['label'], $defs, $type.'.'.$one['key'].' names an undefined '.$one['label']);
                    foreach ($one['options'] ?? [] as $opt => $label) {
                        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]{0,31}$/D', (string)$opt);
                        if (!ctype_digit($label)) $this->assertArrayHasKey($label, $defs);
                    }
                    $val = $make[$one['key']];
                    if ($one['type'] === 'select') $this->assertArrayHasKey($val, $one['options'], $type.'.'.$one['key']);
                    if ($one['type'] === 'file' && $val !== '') $this->assertFileExists(UPLOADS_DIR.'/'.$val);
                    if (is_string($val) && str_starts_with($val, '_')) $this->assertArrayHasKey($val, $defs, $type.'.'.$one['key'].' defaults to an undefined '.$val);
                }
            }
        }
        $groups = array_map(static function (array $list): array {
            sort($list);
            return $list;
        }, $groups);
        ksort($groups);
        $this->assertSame([
            'base' => ['cta', 'features', 'hero', 'media', 'steps', 'text'],
            'contact' => ['faq', 'map', 'prices'],
            'master' => ['embed', 'html'],
            'media' => ['gallery', 'sites', 'video'],
            'trust' => ['stats', 'team', 'voices'],
        ], $groups);
        $bare = array_diff(array_map(static fn(string $v): string => basename($v, '.html'), glob($dir.'/*.html') ?: []), array_merge(...array_values($groups)));
        sort($bare);
        $this->assertSame(['block', 'feed', 'item'], $bare, 'A fragment of lite waits for a type no root describes yet');
    }

    # The kinds of an outside frame give their addresses and nothing else: a film of YouTube in its privacy mode or of the uploads, a page of https alone, a point of the map
    #[Test]
    public function anOutsideFrameWaitsForTheClick(): void
    {
        $page = ['sections' => [
            $this->getSection(1, 'video', ['title' => 'Tube', 'url' => 'https://youtu.be/dQw4w9WgXcQ?t=3', 'photo' => 'presentation/brand/background-2.png',
                'focus' => 'left top']),
            $this->getSection(2, 'video', ['title' => 'Film', 'url' => 'presentation/clip.webm', 'caption' => 'Clip']),
            $this->getSection(3, 'video', ['title' => 'Lost', 'url' => 'https://vimeo.com/1', 'caption' => 'Never']),
            $this->getSection(4, 'embed', ['title' => 'Form', 'url' => 'https://forms.example.org/f?a=1&b=2', 'height' => 500]),
            $this->getSection(5, 'embed', ['title' => 'Plain', 'url' => 'http://example.org/']),
            $this->getSection(6, 'embed', ['title' => 'Script', 'url' => 'javascript:alert(1)//https://x']),
            $this->getSection(7, 'map', ['title' => 'Map', 'point' => '52.3507, 8.2271', 'side' => 'right']),
            $this->getSection(8, 'map', ['title' => 'Off map', 'point' => '95, 8']),
            $this->getSection(9, 'html', ['code' => '<b data-x="1">Own</b>']),
        ]];
        $read = new Layout($this->getLayout(['pages' => [1 => $page], 'theme' => ['phone' => '+49 1', 'email' => 'a@example.org']]), 'lite', false, false, '', self::$mods);
        $html = $read->getPageHtml(1, '', new Template('lite'), $this->getParser());
        $this->assertStringContainsString('<template><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1"', $html);
        $this->assertStringContainsString('src="/uploads/presentation/brand/background-2.png" alt="" style="--sl-d-focus: left top"', $html);
        $this->assertStringContainsString('<video src="/uploads/presentation/clip.webm" controls preload="metadata"></video>', $html);
        $this->assertStringContainsString('<p class="sl-pres-lead">Clip</p>', $html);
        $this->assertStringNotContainsString('Lost', $html, 'A section printing nothing kept its frame');
        $this->assertStringNotContainsString('Never', $html);
        $this->assertStringContainsString('style="--sl-d-frame: 500px"', $html);
        $this->assertStringContainsString('forms.example.org</button>', $html);
        $this->assertStringContainsString('src="https://forms.example.org/f?a=1&amp;b=2"', $html);
        $this->assertStringNotContainsString('Plain', $html);
        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringContainsString('https://www.openstreetmap.org/export/embed.html?bbox=8.2111,52.3407,8.2431,52.3607&amp;layer=mapnik&amp;marker=52.3507,8.2271', $html);
        $this->assertStringContainsString('sl-sec-media sl-sec-flip', $html);
        $this->assertStringContainsString('<a href="tel:+49 1">+49 1</a>', $html, 'The values of the theme did not reach the section');
        $this->assertStringContainsString('<a href="mailto:a@example.org">', $html);
        $this->assertStringContainsString('<h2>Off map</h2>', $html, 'The contacts of the theme stand without the map');
        $this->assertSame(1, substr_count($html, 'sl-sec-media sl-sec-solo'));
        $this->assertStringContainsString('<b data-x="1">Own</b>', $html);
        $this->assertSame(3, substr_count($html, 'data-sl-sec-load>'), 'Every outside frame but the film of the uploads waits behind its placeholder');
    }

    # The first question of a list opens when the section asks; an answer is Markdown through the parser
    #[Test]
    public function theFirstQuestionOpens(): void
    {
        $sec = $this->getSection(1, 'faq', ['title' => 'Faq', 'open' => true, 'items' => [['q' => 'One?', 'a' => 'Yes'], ['q' => 'Two?', 'a' => 'No']]]);
        $read = new Layout($this->getLayout(['pages' => [1 => ['sections' => [$sec]]]]), 'lite', false, false, '', self::$mods);
        $html = $read->getPageHtml(1, '', new Template('lite'), $this->getParser());
        $this->assertSame(1, substr_count($html, ' open>'));
        $this->assertMatchesRegularExpression('#<details class="sl-node-faq" open>\s*<summary>.*One\?#s', $html);
        $this->assertStringContainsString('<md data-mod="layout" data-safe="1">No</md>', $html);
    }

    # The showcase of sites is a type of the module presentation with its provider, drawn by the fragment of lite
    #[Test]
    public function theShowcaseIsATypeOfItsModule(): void
    {
        $types = (new Layout([], 'lite', false, false))->getSectionTypes();
        $this->assertSame(['presentation', 'theme'], [$types['sites']['root'], $types['sites']['frag']]);
        $this->assertSame(str_replace('\\', '/', BASE_DIR.'/modules/presentation/sections/sites.php'), str_replace('\\', '/', $types['sites']['prov']));
        $this->assertFileExists(BASE_DIR.'/modules/presentation/sections/sites.html', 'A theme without its own fragment has none to fall back on');
        $code = (string)file_get_contents(BASE_DIR.'/modules/presentation/index.php');
        $this->assertStringNotContainsString('function getPresentationSites', $code, 'The provider would run the module');
    }

    # The theme describes first and its fragment wins, a module adds its own types with a provider; the slot, a bad name and a list are no type
    #[Test]
    public function theTypesComeFromTheThemeAndTheModules(): void
    {
        $types = $this->getReader($this->getLayout())->getSectionTypes();
        $this->assertSame(['bare', 'hero', 'text', 'card', 'feed'], array_keys($types));
        $this->assertSame(['root' => 'theme', 'desc' => ['title' => '_HERO', 'fields' => [['key' => 'title', 'type' => 'text']]], 'frag' => 'theme', 'prov' => ''], $types['hero']);
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
        $this->assertSame(['parts' => [], 'menus' => [], 'zones' => []], (new Layout([], 'scratch-00000000', false, false, '', self::$mods))->getThemePlaces());
    }

    # A section of one language shows only to a visitor of that language, and to everyone while the site is not multilingual
    #[Test]
    public function aSectionOfALanguageShowsInItsLanguage(): void
    {
        $page = ['sections' => [$this->getSection(1, 'hero', ['title' => 'All']), $this->getSection(2, 'hero', ['title' => 'Ru', 'lang' => 'ru']),
            $this->getSection(3, 'hero', ['title' => 'De', 'lang' => 'de'])]];
        $data = $this->getLayout(['pages' => [1 => $page, 2 => ['sections' => [$this->getSection(4, 'hero', ['lang' => 'de'])]]]]);
        $tpl = new Template(self::$theme);
        $html = fn(string $lang): string => (string)preg_replace('#\s+#', '', $this->getReader($data, false, false, $lang)->getPageHtml(1, '', $tpl, $this->getParser()));
        $this->assertSame('<pclass="hero">All</p><pclass="hero">Ru</p>', $html('ru'));
        $this->assertSame('<pclass="hero">All</p><pclass="hero">Ru</p><pclass="hero">De</p>', $html(''));
        $this->assertTrue($this->getReader($data, false, false, 'ru')->isPageEmpty(2));
        $this->assertFalse($this->getReader($data, false, false, 'de')->isPageEmpty(2));
    }

    # A page answers at an address of its own unless it is the home page, the page 404 or holds a slot switched on; its audience is the scale of a block
    #[Test]
    public function aPageOfSectionsHasAnAddressOfItsOwn(): void
    {
        $pages = $this->getLayout()['pages'] + [
            6 => ['who' => '1', 'sections' => [$this->getSection(16, 'hero')]],
            7 => ['sections' => []],
            8 => ['sections' => [$this->getSection(17, 'module', ['module' => 'faq', 'on' => false]), $this->getSection(18, 'hero')]],
        ];
        $guest = $this->getReader($this->getLayout(['pages' => $pages]));
        $this->assertSame([5, 6, 7, 8], $guest->getAddressKeys());
        $this->assertSame([6, 7, 8], $this->getReader($this->getLayout(['pages' => $pages, 'home' => 5]))->getAddressKeys());
        $this->assertFalse($guest->isPageShown(6));
        $this->assertTrue($this->getReader($this->getLayout(['pages' => $pages]), true)->isPageShown(6));
        $this->assertTrue($guest->isPageShown(7));
        $this->assertTrue($guest->isPageEmpty(7));
        $this->assertTrue($guest->isPageEmpty(99));
        $this->assertFalse($guest->isPageEmpty(8));
        $this->assertSame(1, preg_match(Layout::PAGE, 'page-'.max(array_keys($pages))));
        foreach (['page-0', 'page-01', 'page-', 'page-1a', 'Page-1', 'page-12345678901', 'news'] as $bad) $this->assertSame(0, preg_match(Layout::PAGE, $bad), $bad);
    }

    # The draft read in place of the published layout keeps the viewer and marks itself, so the page answers as a draft
    #[Test]
    public function theDraftReadsInPlaceOfTheLayout(): void
    {
        $read = $this->getReader($this->getLayout());
        $this->assertFalse($read->isDraft());
        $read->setDraftData($this->getLayout(['pages' => [9 => ['title' => 'New', 'sections' => [$this->getSection(19, 'hero')]]], 'home' => 9]));
        $this->assertTrue($read->isDraft());
        $this->assertSame([9], array_keys($read->getData()['pages']));
        $this->assertSame(9, $read->getHomeKey());
        $read->setDraftData(['v' => 0]);
        $this->assertSame([], $read->getData()['pages']);
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

    # A menu is as deep as the theme declares it, a menu it does not declare prints nothing; an address, an icon and a title are checked as fields of a section
    #[Test]
    public function aMenuKeepsItsDepthAndItsChecks(): void
    {
        $item = $this->getMenuItem(...);
        $deep = $item('Two', '/two', ['kids' => [$item('Three', '/three')], 'icon' => 'bad icon', 'blank' => '1']);
        $list = [$item('One', 'https://example.org/', ['kids' => [$deep], 'icon' => 'star']), $item('Bad', 'javascript:alert(1)', ['title' => ['x']]), $item('Rel', 'news')];
        $read = $this->getReader($this->getLayout(['menus' => ['main' => $list, 'foot' => $list]]));
        $main = $read->getMenu('main');
        $this->assertSame(['id' => 1, 'title' => 'One', 'href' => 'https://example.org/', 'icon' => 'star', 'blank' => false], array_diff_key($main[0], ['kids' => 1]));
        $want = ['id' => 1, 'title' => 'Two', 'href' => '/two', 'icon' => '', 'blank' => true, 'kids' => []];
        $this->assertSame($want, $main[0]['kids'][0], 'A level below the depth of the theme printed');
        $this->assertSame(['', ''], [$main[1]['title'], $main[1]['href']]);
        $this->assertSame('', $main[2]['href'], 'An address that is neither of the site nor of the web passed');
        $this->assertSame([], $read->getMenu('foot'), 'A menu the theme does not declare printed');
    }

    # The parts of the theme reach the page by the fields of places.json, each value checked as a field of a section is; a key no part declares stays unprinted
    #[Test]
    public function theThemePartsAreCheckedByTheirPlaces(): void
    {
        $theme = [
            'tagline' => "One\nTwo", 'paneon' => '1', 'panehref' => 'javascript:alert(1)', 'email' => 'not mail', 'web' => 'https://example.org/', 'other' => 'Lost',
            'social' => [['icon' => 'github', 'title' => 'Hub', 'url' => 'https://github.com/x'], ['icon' => 'bad icon', 'title' => 'Own', 'url' => '/recommend'], 'broken'],
            'marks' => array_fill(0, 8, ['img' => 'presentation/brand/tech/php.png', 'title' => 'PHP']),
            'tabs' => [['title' => 'Ideas', 'href' => '/forum'], ['title' => 'Mail', 'href' => '/contact'], ['title' => 'Third', 'href' => '/x']],
        ];
        $parts = (new Layout($this->getLayout(['theme' => $theme]), 'lite', false, false, '', self::$mods))->getThemeData($this->getParser());
        $this->assertSame("One\nTwo", $parts['tagline']);
        $this->assertTrue($parts['paneon']);
        $this->assertSame(['', '', 'https://example.org/', true], [$parts['panehref'], $parts['email'], $parts['web'], $parts['web_ext']]);
        $this->assertArrayNotHasKey('other', $parts);
        $social = array_map(static fn(array $v): array => [$v['icon'], $v['url'], !empty($v['url_ext'])], $parts['social']);
        $this->assertSame([['github', 'https://github.com/x', true], ['', '/recommend', false]], $social);
        $this->assertCount(6, $parts['marks'], 'The footer holds more marks than its part allows');
        $this->assertSame('/uploads/presentation/brand/tech/php.png', $parts['marks'][0]['img']);
        $this->assertSame([true, false], array_column($parts['tabs'], 'is_first'), 'The theme draws two tabs');
        $this->assertSame(['social', 'marks', 'tabs'], array_values(array_filter(array_keys($parts), static fn(string $k): bool => is_array($parts[$k]))));
    }

    # The chrome of lite prints the menus and parts of the layout and carries no value of slaed.net of its own
    #[Test]
    public function theChromeOfLiteComesFromTheLayout(): void
    {
        $files = ['partials/site-header.html', 'partials/site-footer.html', 'partials/menu.html', 'fragments/block-menu.html'];
        foreach ($files as $one) {
            $code = (string)file_get_contents(PUBLIC_DIR.'/templates/lite/'.$one);
            foreach (['slaed.de', 'slaed.info', 'Ostercappeln', 'support@slaed.net', 'images/tmp/', 'Все великое', 'Aктуальная'] as $word) {
                $this->assertStringNotContainsString($word, $code, $one.' still carries '.$word);
            }
        }
        $lang = file_get_contents(BASE_DIR.'/admin/lang/ru.php').file_get_contents(BASE_DIR.'/lang/ru.php');
        preg_match_all("/^define\('(_[A-Z0-9_]+)'/m", $lang, $hit);
        $places = json_decode((string)file_get_contents(PUBLIC_DIR.'/templates/lite/places.json'), true, 512, JSON_THROW_ON_ERROR);
        array_walk_recursive($places, function (mixed $val, string|int $key) use ($hit): void {
            if (in_array($key, ['title', 'note', 'where', 'label'], true) && $val !== '') $this->assertContains($val, $hit[1], 'places.json of lite names an undefined '.$val);
        });
        $item = $this->getMenuItem(...);
        $kids = [$item('Docs <b>', 'https://example.org/', ['blank' => true]), $item('Mail', '/contact', ['icon' => 'star'])];
        $list = [$item('Group', '', ['kids' => $kids]), $item('News', '/news')];
        $read = new Layout($this->getLayout(['menus' => ['main' => $list]]), 'lite', false, false, '', self::$mods);
        $tpl = new Template('lite');
        $menu = $tpl->getHtmlPart('menu', ['menus' => ['main' => $read->getMenu('main')], 'home' => 'Home']);
        $this->assertStringContainsString('<a href="#" title="Group">Group</a>', $menu, 'A group without its address lost its place in the menu');
        $this->assertStringContainsString('<a href="https://example.org/" title="Docs &lt;b&gt;" target="_blank" rel="noopener">Docs &lt;b&gt;</a>', $menu);
        $this->assertStringContainsString('<i class="bi bi-star" aria-hidden="true"></i>&nbsp;Mail</a>', $menu);
        $this->assertStringContainsString('<a href="/news" title="News">News</a>', $menu);
        $block = $tpl->getHtmlFrag('block-menu', ['items' => $read->getMenu('main')]);
        $this->assertStringContainsString('<li><a href="/contact" title="Mail"><i class="bi bi-star" aria-hidden="true"></i>&nbsp;Mail</a></li></ul></li>', $block);
        $this->assertStringStartsWith('<nav class="sl-block-menu"><ul><li><a href="#" title="Group">Group</a><ul>', $block);
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
