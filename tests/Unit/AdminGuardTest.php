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
        $this->assertArrayNotHasKey('reset', $data, 'A refused write dropped the category map');
        $this->assertArrayNotHasKey('route', $data, 'A refused write touched the path of the category');
        $this->assertSame(['badparent', true], [$data['text'], $data['warn']]);
    }

    # A parent outside the subtree and the top level are written as posted, and the stored category map of the module is dropped once
    #[Test]
    public function aCategoryTakesAParentOutsideItsSubtree(): void
    {
        foreach (['3:4' => 4, '2:0' => 0] as $pair => $want) {
            $data = $this->getProbe('catparent', $pair);
            $this->assertSame($want, $data['update'][0]['parent'] ?? null, $pair.' was not written');
            $this->assertSame(['forum'], $data['reset'] ?? [], $pair.' did not drop the category map of its module once');
            $this->assertSame([['cat', (int)$pair, 'forum', '', 'probe']], $data['route'] ?? [], $pair.' did not keep the category on its path in the same save');
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

    # The query method of the shipped database class names no table prefix, because the installer runs queries before PREFIX_DB exists
    #[Test]
    public function theQueryNeedsNoTablePrefix(): void
    {
        $query = substr($this->getFile('core/classes/pdo.php'), (int)strpos($this->getFile('core/classes/pdo.php'), 'function getSqlQuery('));
        $this->assertStringNotContainsString('PREFIX_DB', substr($query, 0, (int)strpos($query, "\n    }\n")), 'The installer runs queries before PREFIX_DB exists');
    }

    # A forum topic is added under the lock of the account and a recount in the same transaction, like a Node material
    #[Test]
    public function aPlainFavoriteCountsUnderTheAccountLock(): void
    {
        $body = $this->getBody('core/user.php', 'addFavorite');
        $plain = substr($body, (int)strpos($body, "\$mod === 'forum'"));
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
        $this->assertStringContainsString("getTplPostAction(['name' => 'account', 'op' => 'delete', 'id[]' => \$uid]", $body);
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

    # The watch window lists the address and the page of every visitor: a super administrator alone gets the window and its answer, never without the token
    #[Test]
    public function theWatchWindowIsForTheSuperAdmin(): void
    {
        $this->assertStringContainsString("if (!\$conf['session'] || !isAdmin(true)) return;", $this->getBody('core/system.php', 'getSessionStats'));
        $set = $this->getBody('core/helpers.php', 'getWindowSet');
        $this->assertLessThan(strpos($set, "'sl-stats'"), strpos($set, "if (\$conf['session'] && isAdmin(true))"), 'The watch window is printed for any visitor');
        $index = $this->getFile('public/index.php');
        $open = substr($index, (int)strpos($index, '$public = '), 400);
        $this->assertStringNotContainsString('getSessionStats', substr($open, 0, (int)strpos($open, ';')), 'The watch window answers without the token');
        $this->assertSame(2, substr_count($index, "case 'getSessionStats': getSessionStats(); exit;"));
    }

    # The pins of the floating menu come from a cookie: the option stores only eight plain keys, and the menu draws only a section its administrator may open
    #[Test]
    public function thePinsOfTheMenuAreHeldAtBothEnds(): void
    {
        $option = $this->getBody('admin/index.php', 'setAdminOption');
        $this->assertStringContainsString("count(\$pins) > 8 || preg_grep('#[^a-z0-9_]#', \$pins)", $option, 'The option stores a list of pins it never checked');
        $store = strpos($option, "setCookies(\$key, time() + 31536000, implode('-', \$pins))");
        $this->assertLessThan($store, strpos($option, 'http_response_code(400)'), 'The pins are stored before they are checked');
        $dock = $this->getBody('core/system.php', 'getAdminDock');
        $this->assertStringContainsString('if (!isset($list[$key]) || count($pins) === 8) continue;', $dock, 'The menu draws a pin of a closed section or a ninth one');
        $open = "((\$typ === 0) ? !isAdmin(true) : (!is_admin_modul(\$key) || !is_file(BASE_DIR.'/modules/'.\$key.'/admin/index.php')))";
        $this->assertStringContainsString($open, $this->getBody('core/system.php', 'getAdminSectionList'), 'A section its administrator may not open is listed');
    }

    # The menu on the site serves an administrator alone: its script, its sections behind the token, its link into the panel only where its moderator may go
    #[Test]
    public function theMenuOnTheSiteIsForTheAdministrator(): void
    {
        $this->assertStringContainsString("if (isAdmin() && (defined('ADMIN_FILE') || !isThemeFlag('dock-site-off')))", $this->getBody('core/system.php', 'doScript'));
        $index = $this->getFile('public/index.php');
        $open = substr($index, (int)strpos($index, '$public = '), 400);
        $this->assertStringNotContainsString('getAdminSectionData', substr($open, 0, (int)strpos($open, ';')), 'All the sections answer without the token');
        $this->assertStringContainsString("isAdmin() && in_array(\$op, ['getAdminQueueData', 'getAdminSectionData'], true)", $index);
        $page = $this->getBody('core/system.php', 'getAdminPageLink');
        $this->assertStringContainsString("if (defined('ADMIN_FILE') || !isAdmin() || \$key === '') return [];", $page);
        $rule = 'if (!$ctx->super && !in_array($key, $ctx->mods, true)) return [];';
        $this->assertStringContainsString($rule, $page, 'The link edits a material of a type its administrator does not moderate');
        $this->assertStringContainsString("'dock-site-off'", $this->getBody('admin/index.php', 'setAdminOption'));
    }

    # The rows of a list page: an own number from 5 to the cap, "all" while the filter fits under it, the list's own number for anything else
    #[Test]
    public function theRowsOfAListHoldTheirBounds(): void
    {
        $data = $this->getProbe('rows');
        $base = [25, 25, 25, 1];
        $want = [
            '' => $base, '10' => [10, 10, 10, 10], '25' => [25, 25, 25, 25], '250' => [250, 250, 250, 250], '5' => [5, 5, 5, 5],
            '1000' => [1000, 1000, 1000, 1000], '4' => $base, '1001' => $base, '07' => $base, '-5' => $base, '1e3' => $base, 'abc' => $base,
            'all' => [1000, 1000, 25, 1000],
        ];
        $this->assertSame($want, $data['limit'], 'A stored choice outside the rules changed the rows, or "all" held over a filter above the cap');
        $note = 'The filter is counted although no "all" asks for it, or "all" did not count it';
        $this->assertSame(['' => [25, 0], '50' => [50, 0], 'all' => [1000, 1]], $data['lazy'], $note);
        $limit = $this->getBody('core/helpers.php', 'getListLimit');
        $this->assertStringContainsString("if (!defined('ADMIN_FILE')) return \$base;", $limit, 'The site reads the choice of the panel');
        $this->assertStringContainsString("define('LIST_MAX', 1000);", $this->getFile('core/system.php'), 'The cap of a list page moved without its probe');
    }

    # Values of the rows a page may post, each with whether the option keeps it
    public static function getRowsValues(): array
    {
        return [
            'all' => ['all', true], 'ladder' => ['25', true], 'floor' => ['5', true], 'cap' => ['1000', true], 'own' => ['37', true], 'under' => ['4', false],
            'over' => ['1001', false], 'zero' => ['07', false], 'word' => ['abc', false], 'sign' => ['+25', false],
        ];
    }

    # The option stores the rows only as "all" or a plain number from 5 to the cap and drops the cookie for anything else
    #[Test]
    #[DataProvider('getRowsValues')]
    public function theOptionStoresOnlyRowsItMayRead(string $value, bool $kept): void
    {
        $data = $this->getProbe('rows', $value);
        $this->assertSame('done', $data['log'] ?? '', 'The option refused the key rows');
        if ($kept) $this->assertSame(['rows', $value], $data['set'] ?? [], $value.' was not stored');
        else $this->assertSame('rows', $data['drop'] ?? '', $value.' was stored or left in place');
    }
}
