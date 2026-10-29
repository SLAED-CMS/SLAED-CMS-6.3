<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The draft of the site builder: written whole for the draft link, values checked, acts on sections, menus and blocks, every write a POST of its scope
final class LayoutDraftTest extends TestCase
{
    private const MODULE = 'admin/modules/layout.php';

    # Run one mode of the probe in a scratch root of its own and answer its data
    private function getProbe(string $mode): array
    {
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_layout_'.$mode.'_'.getmypid();
        $this->deleteTree($work);
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Support/layout_probe.php').' '.escapeshellarg($mode).' '.escapeshellarg($work);
        $out = (string)shell_exec($cmd.' 2>&1');
        $this->deleteTree($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'Probe '.$mode.' failed');
        return $data['data'];
    }

    # Remove a scratch tree of this test
    private function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $item) {
            $path = $dir.'/'.$item;
            is_dir($path) ? $this->deleteTree($path) : unlink($path);
        }
        rmdir($dir);
    }

    # Cut one top-level function out of the module
    private function getBody(string $name): string
    {
        $code = (string)file_get_contents(BASE_DIR.'/'.self::MODULE);
        $from = strpos($code, "\nfunction ".$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.self::MODULE);
        return substr($code, $from, (int)strpos($code, "\n}\n", $from) - $from);
    }

    # No draft starts as the site; a draft written whole leaves no temporary file and is read by a draft link; a module page waits in memory for the first change
    #[Test]
    public function theDraftIsWrittenWholeAndReadByTheDraftLink(): void
    {
        $data = $this->getProbe('draft');
        $keys = ['layout', 'link', 'bnext', 'blocks', 'bwas', 'conf', 'undo', 'redo', 'rev', 'ed', 'by', 'at'];
        $this->assertSame(['next' => 20, 'keys' => $keys, 'dirty' => false, 'file' => false], $data['fresh']);
        $this->assertFalse($data['counter'], 'The counter of ids made a draft');
        $this->assertTrue($data['written']);
        $this->assertTrue($data['dirty']);
        $this->assertSame(['draft.json'], $data['left'], 'A temporary file was left beside the draft');
        $this->assertSame(['link' => 3, 'phone' => '+49 1', 'pages' => [1, 2, 9]], $data['link']);
        $this->assertSame(['undo' => 1, 'phone' => '+49 1', 'next' => 25], $data['back']);
        $this->assertSame('', $data['broken'], 'A draft that does not read did not start as the site');
        $this->assertFalse($data['panel'], 'A section of the panel got a page of the site');
        $made = ['made' => ['key' => 20, 'mod' => 'news'], 'page' => ['Mod news', '2', '0', ''], 'slot' => ['id' => 21, 'type' => 'module', 'on' => true, 'module' => 'news'],
            'dirty' => false, 'stored' => [1, 2, 9], 'next' => 20];
        $this->assertSame($made, $data['made'], 'The page of a module is not made in memory with the columns of its module');
        $this->assertSame(['made' => false, 'title' => 'Changed', 'next' => 22, 'file' => []], $data['kept'], 'The first change did not write the page of a module');
    }

    # A type of Field is checked by Field, a kind of the builder by its own grammar; a refused value keeps the old one and names its field
    #[Test]
    public function valuesAreCheckedByFieldAndByTheKindsOfTheBuilder(): void
    {
        $data = $this->getProbe('value');
        $good = ['text' => 'Hello', 'url' => '/about', 'select' => 'three', 'bool' => false, 'int' => 7, 'choice' => '3', 'empty' => '', 'focus' => 'left top', 'tone' => 'accent',
            'slug' => 'o-kompanii', 'markdown' => "A\nB"];
        foreach ($good as $key => $val) $this->assertSame(['', $val], $data[$key], $key.' was not taken');
        $bad = ['textmax' => 'max', 'badurl' => 'format', 'badselect' => 'choice', 'bigint' => 'max', 'badchoice' => 'format', 'badfocus' => 'format', 'icon' => 'format',
            'anchor' => 'format', 'file' => 'format', 'kind' => 'format', 'badtype' => 'format', 'badref' => 'format'];
        foreach ($bad as $key => $code) $this->assertSame([$code, null], $data[$key], $key.' was not refused');
        $refs = ['nodetype' => ['news', 'docs'], 'noderef' => 3944, 'noref' => 0, 'intref' => 15];
        foreach ($refs as $key => $val) $this->assertSame(['', $val], $data[$key], $key.' was not taken');
        $this->assertSame(['vals' => ['title' => 'New', 'href' => '/old'], 'bad' => ['Link']], $data['set']);
    }

    # A new section takes the next id of the counter and its words resolved, the slot of a module stands once, an order must name every section
    #[Test]
    public function sectionActsKeepTheirIdsAndTheSlotOnce(): void
    {
        $data = $this->getProbe('sec');
        $this->assertSame(['sec:20', 'added Hero', false, ''], $data['add']);
        $this->assertSame(['', '', false, 'taken Slot'], $data['slot']);
        $this->assertSame(['sec:21', 'copied Hero', false, ''], $data['dup']);
        $this->assertSame(['sec:3', '', false, ''], $data['dupslot'], 'The slot of a module got a twin');
        $this->assertSame([3, 21, 20], $data['order']);
        $this->assertSame([21, 20, 3], $data['moved'], 'A section dragged after another did not land there');
        $this->assertSame([3, 21, 20], $data['before'], 'A section dragged before another did not land there');
        $this->assertSame([3, 21, 20], $data['nowhere'], 'A section dragged to no section moved');
        $this->assertSame([3, 21, 20], $data['short'], 'An order missing a section was taken');
        $this->assertSame([20, 3, 21], $data['ordered']);
        $this->assertSame(['sec:21', 'hidden Hero', true, ''], $data['hide']);
        $this->assertSame(['', 'deleted Hero', true, ''], $data['del']);
        $this->assertSame(['id' => 20, 'title' => 'word of a constant', 'item' => 'word of a constant', 'who' => '0', 'phone' => true], $data['hero']);
        $this->assertSame(22, $data['next'], 'The counter of ids went back');
        $this->assertSame([20, 3], $data['ids']);
        $this->assertSame(['about-12', 'about', 'page'], $data['addr']);
    }

    # An item nests into the item above only in a menu of two levels and never as the first, comes back a level up after its parent, an order names its whole level
    #[Test]
    public function menuItemsNestAndOrderByTheirLevel(): void
    {
        $data = $this->getProbe('menu');
        $this->assertSame(['4(5)', '6(7)'], $data['in']);
        $this->assertSame(['4(5)', '6(7)'], $data['first'], 'The first item was nested');
        $this->assertSame(['4(5)', '6', '7'], $data['out']);
        $this->assertSame(['6', '7', '4(5)'], $data['order']);
        $this->assertSame(['6', '7', '4(5)'], $data['short'], 'An order missing an item was taken');
        $this->assertSame(['menu:main', 'item deleted', true], $data['del']);
        $this->assertSame(['6', '7'], $data['after'], 'An item left its kids behind');
        $this->assertSame(['30', '31'], $data['flat'], 'An item was nested in a menu of one level');
        $this->assertSame(['menu:none', '', false], $data['unknown']);
    }

    # The draft keeps only the fields of a block that differ from the row it began from, and that row for the check of the publication
    #[Test]
    public function blockChangesLeaveTheDraftWhenTheyMatchTheRow(): void
    {
        $data = $this->getProbe('block');
        $this->assertSame(['13' => ['status' => false]], $data['off']);
        $this->assertSame(['13' => ['id' => '13', 'title' => 'Poll', 'status' => true, 'bpos' => 'r', 'weight' => 6, 'which' => ['all']]], $data['was']);
        $this->assertSame(['13' => ['status' => false, 'title' => 'Vote']], $data['two']);
        $this->assertSame([], $data['back'], 'A block set back to its row stayed in the draft');
        $this->assertSame([13], $data['kept'], 'The row the draft began from was dropped');
        $this->assertSame([], $data['places'], 'The same places in another order made a change');
        $this->assertSame(['id' => 'n1', 'title' => 'New', 'status' => true, 'new' => true], $data['new']);
    }

    # The window before a publication lists the changes by the ids of the draft and the remarks a visitor would trip over, a picture behind its switch left out
    #[Test]
    public function theChecksListTheChangesAndTheRemarks(): void
    {
        $data = $this->getProbe('tools');
        $changes = ['secnew Hero > sec:20', 'secnew Hero «Off» > sec:22', 'page Twin > page:12', 'part Contacts > part:contacts', 'itemset A2 > menu:main:4',
            'itemnew E > menu:main:8', 'itemdel B > menu:main', 'blkset Poll > blk:13', 'blknew New > blk:n1', 'blkdel Old > '];
        $this->assertSame($changes, $data['changes']);
        $issues = ['notitle > sec:20', 'addr /nowhere > sec:20', 'alt big.png > sec:20', 'heavy big.png 586 > sec:20', 'mdalt big.png > sec:20', 'heavy big.png 586 > sec:20',
            'intext anchor #gone > sec:20', 'hidden > sec:22', 'nodesc > page:9', 'twinurl about About > page:12', 'addr https:// > part:contacts', 'nowhere A2 > menu:main:4',
            'nowhere D > menu:main:7', 'nowhere E > menu:main:8', 'blknone New > blk:n1'];
        $this->assertSame($issues, $data['issues']);
        $this->assertSame([true, true, true, true, false, false, false], $data['same']);
    }

    # The tools of a section: its look goes to a section of its type alone, a copy and a ready page take new ids, a section of a publication comes back to its place
    #[Test]
    public function theToolsOfASectionKeepTheirIdsAndTheirContent(): void
    {
        $data = $this->getProbe('tools');
        $this->assertSame(['pic', 'tone', 'cols'], $data['look'], 'A picture, an icon or a text counted as the look');
        $this->assertSame(['sec:31', 'look into Hero', true, ''], $data['paste']);
        $this->assertSame(['tone' => 'accent', 'cols' => 'two', 'pic' => false, 'title' => 'Two', 'photo' => 'p/2.png'], $data['pasted']);
        $this->assertSame(['sec:31', '', false, 'look gone'], $data['self'], 'A section took its own look');
        $this->assertSame(['sec:30', 'copied Hero to Home', true, ''], $data['copy']);
        $this->assertSame(['3:module', '20:hero'], $data['copied']);
        $this->assertSame(['', '', false, ''], $data['copyslot'], 'The slot of a module was copied');
        $this->assertSame(21, $data['next']);
        $this->assertSame(['sec:3', ''], $data['back']);
        $this->assertSame([3], $data['backids'], 'A section brought back twice stands twice');
        $this->assertSame(['', 'story gone'], $data['nosec']);
        $this->assertSame(['', 'page About gone'], $data['nopage']);
        $preset = ['key' => 20, 'addr' => 'about-20', 'band' => false, 'next' => 23, 'secs' => ['21:hero', '22:module'], 'title' => 'Mine', 'anchor' => 'top', 'who' => '0'];
        $this->assertSame($preset, $data['preset'], 'A ready page kept a missing type, a second slot or a module the slot refuses');
        $this->assertTrue($data['empty']);
    }

    # A copy of a page takes a number, ids and an address of its own and leaves a slot behind; a text makes a cover and sections "Text", its words never read as constants
    #[Test]
    public function aPageIsCopiedOrMadeFromAText(): void
    {
        $data = $this->getProbe('pages');
        $this->assertSame([20, 'copied About. Slot kept.'], $data['copy']);
        $this->assertSame(['title' => 'Copy About', 'addr' => 'copy-about', 'desc' => 'Said', 'secs' => ['21:hero']], $data['copied'], 'A copy shares an id or took the slot');
        $this->assertSame([0, ''], $data['lost'], 'The page 404 was copied');
        $this->assertSame([22, '_HOME', 2], $data['text']);
        $this->assertSame(['addr' => 'home', 'desc' => 'The lead.', 'band' => false], $data['textpage']);
        $secs = [['hero', '_HOME', 'The lead.', ''], ['text', 'One', 'Text **one**.', 'file-text'], ['text', 'Two', "- a\n# Not a title", 'file-text']];
        $this->assertSame($secs, $data['textsecs'], 'A text page lost a part, kept a default button or read a word as a constant');
        $this->assertSame([0, '', 0], $data['notitle'], 'A text without "# " made a page');
        $this->assertTrue($data['bare'], 'A page without a cover lost its title band');
        $addrs = ['Pages' => ['/' => 'Home', '/copy-about' => 'Copy About', '/home' => '_HOME', '/bare' => 'Bare']];
        $addrs['Modules'] = ['/contact' => 'Mod contact', '/news' => 'Mod news'];
        $this->assertSame($addrs, $data['addrs'], 'The choice of an address offers a page holding a slot, an inactive module or misses a page');
    }

    # The texts of the builder are written in the editors of the panel: Markdown with the file window of layout.attach, its folder public and its uploads for its editors alone
    #[Test]
    public function theEditorWindowCarriesTheFilesOfTheBuilder(): void
    {
        $body = $this->getBody('edit');
        $this->assertStringContainsString("getTplTextarea(['id' => 'f-lay-edit'", $body, 'A Markdown text is not written in the content editor of the panel');
        $this->assertStringContainsString("'mod' => 'layout'", $body, 'The frame of the builder carries no data-sl-editor-files');
        $this->assertStringContainsString("Editor::getCode(['id' => 'f-lay-edit'", $body, 'The own HTML is not written in the code editor');
        $this->assertStringContainsString("'readonly' => true", $this->getBody('source'), 'The file of the layout opens for writing');
        $this->assertContains('layout', getUploadPublic(), 'A picture of a section has no address');
        $rule = explode('|', (string)((require BASE_DIR.'/config/uploads.php')['uploads']['layout'] ?? ''));
        $this->assertCount(12, $rule, 'The shipped upload rule of layout is missing');
        $this->assertSame(['0', '0'], [$rule[9], $rule[10]], 'A user or a guest may upload into the folder of the builder');
        $rows = $this->getBody('getLayoutRows');
        $this->assertStringContainsString("getFileManagerField(['id' => \$fid, 'place' => 'layout.attach'", $rows, 'A picture is not picked in the file window');
    }

    # A layout file is read by the descriptions: undescribed values leave, an unknown type, a refused value or a twin id refuse it; empty records and blocks are named
    #[Test]
    public function aLayoutFileIsReadByTheDescriptions(): void
    {
        $data = $this->getProbe('import');
        $this->assertSame([], $data['bad']);
        $this->assertSame([40, false, [1, 2, 9]], [$data['next'], $data['shipped'], $data['keys']], 'The counter or the pages of an imported file are wrong');
        $this->assertSame(['phone' => '+49', 'social' => [['url' => 'https://x.com']]], $data['theme'], 'A value no part describes was kept');
        $menu = ['main' => [['id' => 4, 'title' => 'A', 'href' => '/about', 'who' => '0', 'kids' => []]]];
        $this->assertSame($menu, $data['menus'], 'A menu kept a level or a menu the theme lacks');
        $sec = ['id' => 30, 'type' => 'card', 'on' => true, 'title' => 'One', 'tone' => 'accent', 'item' => 7, 'block' => 15, 'items' => [['text' => 'a'], ['text' => 'b']]];
        $this->assertSame($sec, $data['sec'], 'A section was not read by its description');
        $this->assertSame(['title' => 'About', 'addr' => 'about', 'noindex' => true], $data['page']);
        $this->assertSame([null, ['About: no gone']], $data['type'], 'A type the theme lacks was taken');
        $this->assertSame([null, ['About · Card · Tone']], $data['value'], 'A refused value was taken');
        $this->assertSame([null, ['About · Card · id']], $data['twin'], 'An id given twice was taken');
        $this->assertSame([[null, []], [null, []]], [$data['version'], $data['nohome']], 'A file of another version or without its home page was taken');
        $this->assertSame(['nonode > sec:31', 'blkoff Poll > sec:31', 'nonode > sec:32', 'noblock > sec:32', 'nodesc > page:9'], $data['issues']);
        $import = $this->getBody('setLayoutImport');
        $this->assertStringContainsString("!== 'json' => _ERROR_FILE", $import, 'A file other than JSON is read');
        $this->assertStringNotContainsString('include', $import, 'An imported file is run');
    }

    # Every write of the screen is a POST of the scope layout checked before anything is read from the post, and the reads write nothing
    #[Test]
    public function everyWriteOfTheScreenIsAPostOfItsScope(): void
    {
        foreach (['save', 'act'] as $name) {
            $body = $this->getBody($name);
            $guard = strpos($body, "if (!checkAdminPost('layout'))");
            $this->assertNotFalse($guard, $name.'() does not check its POST');
            $this->assertLessThan(strpos($body, "getVar('post'"), $guard, $name.'() reads the post before its check');
        }
        foreach (['layout', 'tree', 'pick', 'story', 'checks', 'dest', 'edit', 'source', 'find', 'export', 'info'] as $name) {
            $body = $this->getBody($name);
            $this->assertStringNotContainsString('setLayoutKeep(', $body, $name.'() writes a step of the draft');
            $this->assertStringNotContainsString('checkAdminPost(', $body);
        }
        $this->assertStringContainsString("if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');", (string)file_get_contents(BASE_DIR.'/'.self::MODULE));
    }
}
