<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Guards of the panel a clean installation depends on, run through a probe over stubs or proven off the shipped sources
final class AdminGuardTest extends TestCase
{
    private static array $files = [];

    # Run one scenario of tests/Support/guard_probe.php, which lifts the shipped functions over stubs into its own scratch root, and answer its report
    private function getProbe(string $mode, string $name = ''): array
    {
        $script = dirname(__DIR__).'/Support/guard_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_guard_'.$mode.'_'.md5($name).'_'.getmypid();
        $this->deleteTree($work);
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work.'/probe');
        $out = (string)shell_exec($cmd.' '.escapeshellarg($name).' 2>&1');
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

    # Answer every admin handler file of the tree: the panel modules and the admin part of every module
    private function getAdminFiles(): array
    {
        $root = dirname(__DIR__, 2);
        $list = array_merge(glob($root.'/admin/modules/*.php') ?: [], glob($root.'/modules/*/admin/index.php') ?: []);
        $this->assertNotEmpty($list);
        return array_map(fn(string $v): string => substr(str_replace('\\', '/', $v), strlen(str_replace('\\', '/', $root)) + 1), $list);
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Cut one top-level function out of a source file
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        return substr($code, $from, (int)strpos($code, "\n}\n", $from) - $from);
    }

    # COOKIE, FILES, SESSION and SERVER used to be printed raw, so a cookie of the visitor ran as markup in the panel
    #[Test]
    public function theDebugPanelEscapesEveryValue(): void
    {
        $html = $this->getProbe('debug')['html'];
        foreach (['post', 'get', 'cookie', 'files', 'session', 'server'] as $mark) $this->assertStringContainsString($mark, $html, $mark.' is not printed');
        foreach (['<b>', '<i>', '<script', '<img', '<svg', '<iframe'] as $tag) $this->assertStringNotContainsString($tag, $html, $tag.' reached the panel unescaped');
        $this->assertStringContainsString('&lt;script&gt;cookie&lt;/script&gt;', $html);
    }

    # A refused name leaves both files where they were, stores nothing and answers the error of the field
    public static function getRefusedNames(): array
    {
        return [
            'parent directory' => ['../evil'],
            'subdirectory' => ['sub/panel'],
            'existing file' => ['index'],
            'empty' => [''],
            'upper case' => ['Panel'],
            'dot' => ['panel.x'],
        ];
    }

    # The admin file is renamed only to a plain name, never out of the root and never over another file
    #[Test]
    #[DataProvider('getRefusedNames')]
    public function theAdminFileRefusesAnUnsafeName(string $name): void
    {
        $data = $this->getProbe('afile', $name);
        $this->assertSame(['admin.php', 'index.php'], $data['files'], 'The root changed');
        $this->assertSame([], $data['outside'], 'A file left the root');
        $this->assertArrayNotHasKey('saved', $data, 'The settings were stored');
        $this->assertSame(['admin.php?name=security&op=config', 'badname', true], [$data['url'], $data['text'], $data['warn']]);
    }

    # A plain new name moves the file and stores the name
    #[Test]
    public function theAdminFileTakesAPlainName(): void
    {
        $data = $this->getProbe('afile', 'panel_2-x');
        $this->assertSame(['index.php', 'panel_2-x.php'], $data['files']);
        $this->assertSame('panel_2-x', $data['saved']);
        $this->assertSame(['panel_2-x.php?name=security&op=config', 'saved', false], [$data['url'], $data['text'], $data['warn']]);
    }

    # The same name stores the settings without touching a file
    #[Test]
    public function theAdminFileKeepsItsName(): void
    {
        $data = $this->getProbe('afile', 'admin');
        $this->assertSame(['admin.php', 'index.php'], $data['files']);
        $this->assertSame(['admin', 'saved', false], [$data['saved'], $data['text'], $data['warn']]);
    }

    # Every state-changing handler of shop and order requires POST with a body token of its scope, like the rest of the panel
    #[Test]
    public function theShopAndOrderHandlersRequirePost(): void
    {
        $list = [
            'shop' => ['clientset', 'clientsave', 'clientdel', 'productsave', 'productops', 'partnerset', 'partnersave', 'partnerdel', 'export', 'save'],
            'order' => ['save', 'delete', 'activate', 'configsave'],
        ];
        foreach ($list as $mod => $funcs) {
            $path = 'modules/'.$mod.'/admin/index.php';
            $this->assertStringNotContainsString('checkSiteToken(', $this->getFile($path), $path.' still accepts a token from a header or a GET');
            $this->assertStringNotContainsString('getSiteToken()', $this->getFile($path), $path.' renders a token of another scope');
            foreach ($funcs as $func) $this->assertStringContainsString("checkAdminPost('".$mod."')", $this->getBody($path, $func), $mod.' '.$func.'() has no POST check');
        }
    }

    # No admin handler accepts a token from a header, a GET or another scope, and no admin address carries one
    #[Test]
    public function everyAdminHandlerRequiresPost(): void
    {
        foreach ($this->getAdminFiles() as $path) {
            $code = $this->getFile($path);
            $this->assertStringNotContainsString('checkSiteToken(', $code, $path.' still accepts a token from a header or a GET');
            $this->assertStringNotContainsString('getSiteToken()', $code, $path.' renders a token of another scope');
            $this->assertStringNotContainsString('&token=', $code, $path.' puts a token into an address');
        }
    }

    # A posted field called name overrides the module name the panel routes by, so no admin form posts one of its own
    #[Test]
    public function noAdminFormPostsTheRouteName(): void
    {
        foreach ($this->getAdminFiles() as $path) {
            $code = $this->getFile($path);
            $this->assertStringNotContainsString("getVar('post', 'name',", $code, $path.' reads a posted field called name');
            $this->assertDoesNotMatchRegularExpression("/getHtmlFrag\('(input|textarea|select)', \[[^\]]*'name_attr' => 'name'/", $code, $path.' renders a field called name');
        }
    }

    # A parent that is the category itself, one of its descendants or no category of the module is refused before any write
    public static function getRefusedParents(): array
    {
        return [
            'itself' => ['1:1'],
            'child' => ['1:2'],
            'grandchild' => ['1:3'],
            'unknown' => ['1:99'],
        ];
    }

    # The plain branch of the category save checks the parent the way the Node branch does
    #[Test]
    #[DataProvider('getRefusedParents')]
    public function aCategoryRefusesItsOwnSubtreeAsParent(string $pair): void
    {
        $data = $this->getProbe('catparent', $pair);
        $this->assertArrayNotHasKey('update', $data, 'The category was written');
        $this->assertSame(['badparent', true], [$data['text'], $data['warn']]);
    }

    # A parent outside the subtree and the top level are written as posted
    #[Test]
    public function aCategoryTakesAParentOutsideItsSubtree(): void
    {
        foreach (['3:4' => 4, '2:0' => 0] as $pair => $want) {
            $data = $this->getProbe('catparent', $pair);
            $this->assertSame($want, $data['update'][0]['parent'] ?? null, $pair.' was not written');
            $this->assertSame(['saved', false], [$data['text'], $data['warn']]);
        }
    }

    # A parent cycle already stored ends the walk up and the subtree collection, and every category repairs its own set once
    #[Test]
    public function theForumRepairEndsOnAStoredCycle(): void
    {
        $data = $this->getProbe('forumlast');
        $this->assertTrue($data['done'] ?? false);
        foreach ($data['subs'] as $sub) $this->assertSame(array_values(array_unique($sub)), $sub, 'A category entered its subtree twice');
        $this->assertSame([4, 3, 1, 2], array_column($data['update'], 'id'));
    }

    # The first write of the panel raised the generation before the other writes and the commit, and nothing raised it after them; the end of the request now forces it
    # The online tracking wrote _session on every view of the panel and so dropped the page cache on every click; it moves no generation now
    #[Test]
    public function aPanelWriteRaisesTheGenerationAgainAtTheEnd(): void
    {
        $data = $this->getProbe('epoch');
        $this->assertSame(0, $data['session'] ?? null, 'The online tracking of every panel request drops the whole page cache');
        $this->assertSame(1, $data['early'] ?? null, 'The first write does not raise the generation at once, or every write does');
        $this->assertSame(2, $data['final'] ?? null, 'The end of the request does not force the generation after the writes');
        $query = substr($this->getFile('core/classes/pdo.php'), (int)strpos($this->getFile('core/classes/pdo.php'), 'function getSqlQuery('));
        $this->assertStringNotContainsString('PREFIX_DB', substr($query, 0, (int)strpos($query, "\n    }\n")), 'The installer runs queries before PREFIX_DB exists');
    }

    # A forum topic or a product is added under the lock of the account and a recount in the same transaction, like a Node material
    #[Test]
    public function aPlainFavoriteCountsUnderTheAccountLock(): void
    {
        $body = $this->getBody('core/user.php', 'addFavorite');
        $plain = substr($body, (int)strpos($body, "in_array(\$mod, ['forum', 'shop'], true)"));
        $this->assertStringContainsString('setSqlBegin()', $plain);
        $this->assertStringContainsString('_users WHERE id = :uid FOR UPDATE', $plain);
        $this->assertStringContainsString("\$all < intval(\$conf['favorites']['favorites'])", $plain);
        $this->assertLessThan(strpos($plain, 'INSERT INTO'), strpos($plain, 'FOR UPDATE'), 'The row is written before the account is locked');
        $this->assertStringContainsString('setSqlRollback()', $plain);
    }

    # The admin menu of a profile names the ops the panel has now: the edit form of account, the ban list of security and the posted delete of account
    #[Test]
    public function theProfileMenuReachesLiveOps(): void
    {
        $body = $this->getBody('modules/account/index.php', 'view');
        foreach (['op=users_add', 'op=security_block', 'op=users_del'] as $gone) $this->assertStringNotContainsString($gone, $body, 'The profile still links to '.$gone);
        $this->assertStringContainsString("'.php?name=account&op=add&id='.\$uid", $body);
        $this->assertStringContainsString("'.php?name=security&op=banlist&new_ip='", $body);
        $this->assertStringContainsString("getTplPostAction(['name' => 'account', 'op' => 'delete', 'id' => \$uid]", $body);
    }

    # The forum list only reads; the repair of the counters is a posted op of its own behind the admin POST guard
    #[Test]
    public function theForumRepairIsAPostedOp(): void
    {
        $this->assertStringNotContainsString('updateForumSync(', $this->getBody('modules/forum/admin/index.php', 'forum'));
        $sync = $this->getBody('modules/forum/admin/index.php', 'sync');
        $this->assertStringContainsString("checkAdminPost('forum')", $sync);
        $this->assertStringContainsString('updateForumSync()', $sync);
    }
}
