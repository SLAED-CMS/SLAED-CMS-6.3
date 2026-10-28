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

    # No draft yet starts as the site, clean; a draft written whole leaves no temporary file and is what the page of a draft link reads
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
            'anchor' => 'format', 'file' => 'format', 'kind' => 'format'];
        foreach ($bad as $key => $code) $this->assertSame([$code, null], $data[$key], $key.' was not refused');
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
        foreach (['layout', 'tree', 'pick', 'story', 'info'] as $name) {
            $body = $this->getBody($name);
            $this->assertStringNotContainsString('setLayoutKeep(', $body, $name.'() writes a step of the draft');
            $this->assertStringNotContainsString('checkAdminPost(', $body);
        }
        $this->assertStringContainsString("if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');", (string)file_get_contents(BASE_DIR.'/'.self::MODULE));
    }
}
