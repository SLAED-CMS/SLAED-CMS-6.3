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

    # The private rows of a profile and its admin menu are for an administrator of the accounts: one with another section reads the profile as a user does
    #[Test]
    public function theProfileOpensItsPrivateRowsToTheAccountRightAlone(): void
    {
        $body = $this->getBody('modules/account/index.php', 'view');
        $this->assertStringContainsString("\$adm = is_moder(\$conf['name']) > 0;", $body, 'An administrator of any section reads the private rows of every user');
        $this->assertStringNotContainsString('$adm = isAdmin();', $body, 'Any administrator is taken for the administrator of the accounts');
    }

    # A closed profile opens to an administrator of the accounts alone, on the page, in the link, the files, the activity and the comments of a profile
    #[Test]
    public function aClosedProfileOpensToTheAccountRightAlone(): void
    {
        $open = "|| is_moder('account')) {";
        $this->assertStringContainsString($open, $this->getBody('modules/account/index.php', 'view'));
        $this->assertStringContainsString($open, $this->getBody('core/system.php', 'user_info'));
        $this->assertStringContainsString("!is_user() && !is_moder('account')) return false;", $this->getBody('core/user.php', 'checkProfileFile'));
        $this->assertStringContainsString("!is_user() && !is_moder('account'))) return '';", $this->getBody('core/user.php', 'getProfileLastView'));
        $this->assertStringContainsString("\$this->site['prof'] === 1 && !is_user() && !is_moder('account')", $this->getFile('core/classes/comment.php'));
        $home = $this->getBody('modules/account/index.php', 'edithome');
        $this->assertStringContainsString("(isAdmin() ? ['mod' => _ACCOUNT_SNDQUEUE", $home, 'The queue sounds are for any administrator');
    }

    # The recommendation is a mail form like the contact form and asks the captcha under its switch
    #[Test]
    public function theRecommendationAsksTheCaptchaOfTheContactForm(): void
    {
        $code = $this->getFile('modules/recommend/index.php');
        $this->assertStringContainsString("'captcha' => getCaptcha('contact'),", $code);
        $this->assertStringContainsString("if (checkCaptcha('contact')) \$stop[] = _SECCODEINCOR;", $code);
        $this->assertStringNotContainsString("Captcha('comment')", $code);
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

    # The browser runs the sitemap stylesheet in the origin of the site, so only a super administrator reaches its editor and save; no other sees the tab, the op flashes a refusal
    #[Test]
    public function theSitemapTemplateIsForTheSuperAdmin(): void
    {
        $file = 'modules/sitemap/admin/index.php';
        foreach (['xsledit', 'xslsave'] as $name) {
            $body = $this->getBody($file, $name);
            $guard = strpos($body, "if (!isAdmin(true)) setRedirect(\$afile.'.php?name=sitemap', false, 302, _ACCESSDENIED, true);");
            $this->assertNotFalse($guard, $name.'() opens to an administrator of the sitemap alone');
            $this->assertLessThan(strpos($body, 'SITEMAP_DIR'), $guard, $name.'() touches the stylesheet before it asks the right');
        }
        $this->assertStringContainsString('if (!isAdmin(true)) unset($ops[1], $tabs[1]);', $this->getBody($file, 'getSitemapTabs'));
        $this->assertSame(0, substr_count($this->getFile($file), "'tabs' => [_HOME, _TEMPLATE"), 'A tab strip of the module is built past getSitemapTabs()');
    }

    # The pins of the floating menu come from a cookie: the option stores only eight plain keys, and the menu draws only a section its administrator may open
    #[Test]
    public function thePinsOfTheMenuAreHeldAtBothEnds(): void
    {
        $option = $this->getBody('admin/index.php', 'setAdminOption');
        $check = "count(\$keys) > ((\$key === 'pins') ? 8 : count(\$conf['modules']) + count(\$conf['node']['types'] ?? [])) || preg_grep('#[^a-z0-9_]#', \$keys)";
        $this->assertStringContainsString($check, $option, 'The option stores a list of pins it never checked');
        $store = strpos($option, "setCookies(\$key, time() + 31536000, implode('-', \$keys))");
        $this->assertLessThan($store, strpos($option, 'http_response_code(400)'), 'The pins are stored before they are checked');
        $dock = $this->getBody('core/system.php', 'getAdminDock');
        $this->assertStringContainsString('if (!isset($list[$key]) || count($pins) === 8) continue;', $dock, 'The menu draws a pin of a closed section or a ninth one');
        $open = "((\$typ === 0) ? !isAdmin(true) : (!is_admin_modul(\$key) || !is_file(BASE_DIR.'/modules/'.\$key.'/admin/index.php')))";
        $this->assertStringContainsString($open, $this->getBody('core/system.php', 'getAdminSectionList'), 'A section its administrator may not open is listed');
    }

    # The editor of the options is refused when it is no editor of the panel, so a forged value writes nothing to the row of the administrator
    #[Test]
    public function theOptionRefusesAnUnknownEditor(): void
    {
        $option = $this->getBody('admin/index.php', 'setAdminOption');
        $check = "(\$key === 'editor' && (count(\$part) !== 4 || !Editor::isValidEditor(\$val, 'admin')))";
        $this->assertStringContainsString($check, $option, 'An unknown editor is stored instead of refused');
        $this->assertStringNotContainsString('$val = Editor::isValidEditor', $option, 'An unknown editor is swapped for the default and stored');
    }

    # The form of an administrator shows the stored name and an empty address: no loop of the form reuses the name, and no bare scheme blocks the URL field
    #[Test]
    public function theAdministratorFormKeepsItsNameAndAddress(): void
    {
        $add = $this->getBody('admin/modules/admins.php', 'add');
        $this->assertStringNotContainsString('as $name =>', $add, 'A loop of the form overwrites the name of the administrator');
        $this->assertStringContainsString("getVar('post', 'url', 'url', '')", $add, 'The add form fills the URL field with a bare scheme');
        $this->assertStringContainsString("getVar('post', 'url', 'url', '')", $this->getBody('admin/modules/admins.php', 'save'), 'An empty address is stored as a bare scheme');
    }

    # The edit of a category that is gone returns to the list with a warning: its empty row used to reach a typed helper and answer 500
    #[Test]
    public function theEditOfAGoneCategoryReturnsToTheList(): void
    {
        $edit = $this->getBody('admin/modules/categories.php', 'edit');
        $this->assertStringContainsString("\$row = \$db->getSqlRow(\$result) ?: setRedirect(\$afile.'.php?name=categories', false, 302, _NONE_FOUND, true);", $edit,
            'A gone category is read as a row of nulls');
    }

    # A missing token answers with the flash back to the form or the list: the alert in place of a page, the silent redirects and the empty page of the user save are gone
    #[Test]
    public function aMissingTokenAnswersWithAFlash(): void
    {
        foreach (array_merge($this->getAdminFiles(), ['modules/forum/index.php']) as $path) {
            $this->assertStringNotContainsString("'text' => _TOKENMISS]", $this->getFile($path), $path.' prints a missing token as an alert of its page');
        }
        $user = "if (!checkAdminPost('account')) setRedirect(\$afile.'.php?name=account&op=add'.(\$uid ? '&id='.\$uid : ''), false, 302, _TOKENMISS, true);";
        $this->assertStringContainsString($user, $this->getBody('modules/account/admin/index.php', 'addsave'), 'A user save without the token answers an empty page');
        $this->assertStringContainsString("if (!checkAdminPost('blocks')) setRedirect(\$afile.'.php?name=blocks&op=fileedit', false, 302, _TOKENMISS, true);",
            $this->getBody('admin/modules/blocks.php', 'filecode'), 'The code of a file block answers a missing token with a page of its own');
        $dump = $this->getBody('admin/modules/database.php', 'dump');
        $this->assertStringContainsString("if (\$run && !checkAdminPost('database')) setRedirect(\$afile.'.php?name=database&op=dump', false, 302, _TOKENMISS, true);", $dump,
            'A query without the token answers with a page of its own');
        $this->assertStringContainsString('_DB_RUNBUSY', $dump, 'A run that is already active is named by a literal');
        $this->assertSame(3, substr_count($this->getFile('modules/forum/index.php'), "setRedirect(getSeoUrl(['name' => \$conf['name']]), false, 302, _TOKENMISS, true);"),
            'A write of the forum without the token is not named');
        $this->assertStringContainsString('_TOKENMISS, true);', $this->getBody('admin/index.php', 'deleteAdminSession'), 'A logout without the token goes back silently');
    }

    # The edit of a record that is not there goes back to its list with _NONE_FOUND, never to an empty form, an alert in place of the form or a redirect without a word
    #[Test]
    public function aGoneRecordReturnsToItsList(): void
    {
        $sites = [
            ['admin/modules/admins.php', 'add'], ['admin/modules/admins.php', 'delete'], ['modules/account/admin/index.php', 'add'], ['admin/modules/groups.php', 'add'],
            ['modules/voting/admin/index.php', 'add'], ['admin/modules/blocks.php', 'edit'], ['admin/modules/blocks.php', 'filecode'], ['admin/modules/comments.php', 'edit'],
            ['admin/modules/newsletter.php', 'add'], ['admin/modules/lang.php', 'fileedit'], ['modules/search/admin/index.php', 'edit'],
            ['admin/modules/modules.php', 'edit'], ['modules/master/admin/index.php', 'share'],
        ];
        foreach ($sites as [$path, $name]) {
            $this->assertStringContainsString('_NONE_FOUND, true);', $this->getBody($path, $name), $name.'() of '.$path.' does not go back to its list with _NONE_FOUND');
        }
        foreach ($this->getAdminFiles() as $path) $this->assertStringNotContainsString('_NO_INFO, true);', $this->getFile($path), $path.' names a gone record _NO_INFO');
    }

    # A refused form shows its list of reasons in messages over the form again: no joined text, no bare page with a back link
    #[Test]
    public function aRefusedFormShowsItsListOverTheForm(): void
    {
        foreach (array_merge($this->getAdminFiles(), ['admin/index.php']) as $path) {
            $code = $this->getFile($path);
            $this->assertStringNotContainsString('implode("\n", $stop)', $code, $path.' joins the refusals into one text');
            $this->assertDoesNotMatchRegularExpression("/'text' => \\\$stop(text)?\]/", $code, $path.' prints the refusals as a text');
        }
        $add = $this->getBody('admin/modules/blocks.php', 'addsave');
        $edit = $this->getBody('admin/modules/blocks.php', 'editsave');
        $this->assertStringNotContainsString('back_text', $add.$edit, 'A refused block answers a bare page with a back link');
        $this->assertStringContainsString('add($sent, [', $add, 'A refused new block loses what was typed');
        $keep = "edit(\$bid, ['title' => (string)\$set['title'], 'content' => (string)\$set['content']";
        $this->assertStringContainsString($keep, $edit, 'A refused block edit loses what was typed');
    }

    # A count after a POST travels in the flash by sprintf(), never as a flag of the address read into a second alert
    #[Test]
    public function aCountTravelsInTheFlash(): void
    {
        $search = $this->getFile('modules/search/admin/index.php');
        foreach (['&reindex=', '&pick=', "getVar('get', 'reindex'", "getVar('get', 'pick'"] as $flag) {
            $this->assertStringNotContainsString($flag, $search, 'The search settings carry a count in the address: '.$flag);
        }
        $this->assertStringContainsString('sprintf(_SEARCHAUTODONE, ', $this->getBody('modules/search/admin/index.php', 'reindex'), 'The reindex count left the flash');
        $this->assertStringContainsString('sprintf(_SEARCH_ADDED, ', $this->getBody('modules/search/admin/index.php', 'modadd'), 'The count of added modules left the flash');
        $security = $this->getFile('admin/modules/security.php');
        $this->assertStringNotContainsString('&send=1', $security, 'The ban mail is told by a flag of the address');
        $this->assertStringNotContainsString("getVar('get', 'send'", $security, 'The ban list reads a flag of the address into an alert');
        $save = $this->getBody('admin/modules/security.php', 'bansave');
        $this->assertStringContainsString('$text = $done ? _SUCCSAVE : ', $save, 'A saved ban list flashes an empty text');
        $this->assertStringContainsString('$text = _MAIL_SEND;', $save, 'A queued ban mail is not named in the flash');
    }

    # The right node is one box of the Node group: listed among the modules too, its second box sent the right again when the first was cleared
    #[Test]
    public function theRightNodeHasOneBox(): void
    {
        $add = $this->getBody('admin/modules/admins.php', 'add');
        $this->assertStringContainsString("if ((int)(\$info['type'] ?? 1) !== 1 || \$mod === 'node') continue;", $add, 'The module node is listed beside the Node group');
        $this->assertStringContainsString("\$nodes = ['node' => [_NODE_MANAGE, 'node']];", $add, 'The right to manage Node is gone from the form');
    }

    # A ban term of 0 or none lasts the 30 days its code meant: the 0 the form started with used to write a ban that ended the second it was saved
    #[Test]
    public function aBanWithoutTermLastsThirtyDays(): void
    {
        $save = $this->getBody('admin/modules/security.php', 'bansave');
        $this->assertSame(2, substr_count($save, '$time = time() + (($time > 0) ? $time : 30) * 86400;'), 'A ban of the address or of the name ends at once without a term');
        $this->assertStringNotContainsString('is_numeric($time)', $save, 'The fallback of the term hangs on a number that is always given');
        $this->assertStringContainsString("\$time = getVar('req', 'time', 'num') ?: 30;", $this->getBody('admin/modules/security.php', 'banlist'), 'The form starts the term at 0');
        $this->assertSame(2, substr_count($this->getFile('core/security.php'), "_BANN_INFO.' '._BANN_TERM"), 'The ban text puts a full stop after its exclamation mark');
    }

    # The url field of a user site starts empty and shows a stored bare scheme as empty; any other stored value stays as stored, never escaped twice
    #[Test]
    public function theUserFormShowsABareSchemeAsEmpty(): void
    {
        $add = $this->getBody('modules/account/admin/index.php', 'add');
        $this->assertStringContainsString("getVar('post', 'site', 'url', '')", $add, 'A new user starts with a bare scheme in the site field');
        $this->assertStringContainsString("\$site = preg_match('#^https?://\$#i', (string)\$site) ? '' : (string)\$site;", $add, 'The panel shows a stored bare scheme');
        $home = $this->getBody('modules/account/index.php', 'edithome');
        $this->assertStringContainsString("'value_attr' => preg_match('#^https?://\$#i', (string)\$info['website']) ? '' : \$info['website'],", $home,
            'The cabinet shows a stored bare scheme');
        $this->assertStringNotContainsString('filterWebUrl', $add.$home, 'A stored address passes filterText() a second time on its way to the form');
    }

    # The menu on the site serves an administrator alone: its script, its sections behind the token, its link into the panel only where its moderator may go
    #[Test]
    public function theMenuOnTheSiteIsForTheAdministrator(): void
    {
        $this->assertStringContainsString("if (isAdmin() && (defined('ADMIN_FILE') || !isThemeFlag('dock-site-off')))", $this->getBody('core/system.php', 'doScript'));
        $index = $this->getFile('public/index.php');
        $open = substr($index, (int)strpos($index, '$public = '), 400);
        $this->assertStringNotContainsString('getAdminSectionData', substr($open, 0, (int)strpos($open, ';')), 'All the sections answer without the token');
        $gate = "isAdmin() && in_array(\$op, ['getAdminQueueData', 'getAdminSectionData', 'getUserSessionAdminInfo'], true)";
        $this->assertStringContainsString($gate, $index, 'The block of a delegated administrator cannot refresh its summary');
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

    # Orders of the panel menu a page may post, each with the sections as the menu then lists them and the cookie the option keeps, null for a refusal
    public static function getMenuValues(): array
    {
        $base = ['admins', 'blocks', 'config', 'account', 'forum'];
        return [
            'none' => ['', $base, ''], 'dragged' => ['forum-blocks-account-config', ['blocks', 'config', 'admins', 'forum', 'account'], 'forum-blocks-account-config'],
            'twice' => ['forum-forum', ['admins', 'blocks', 'config', 'forum', 'account'], 'forum'],
            'gone' => ['ghost-forum', ['admins', 'blocks', 'config', 'forum', 'account'], 'ghost-forum'],
            'word' => ['Forum-blocks', ['blocks', 'admins', 'config', 'account', 'forum'], null], 'long' => ['a-b-c-d-e-f', $base, null],
        ];
    }

    # The order of the panel menu is its administrator's cookie: the panel sections stay before the site ones, an unnamed section keeps its place after the named
    #[Test]
    #[DataProvider('getMenuValues')]
    public function theMenuKeepsTheOrderOfItsAdministrator(string $value, array $list, ?string $kept): void
    {
        $data = $this->getProbe('menu', $value);
        $this->assertSame($list, $data['list'] ?? [], 'The sections are not listed in the order of the cookie '.$value);
        $this->assertSame(($kept === null) ? 'refused' : 'done', $data['log'] ?? '', 'The option took the order '.$value.' wrongly');
        if ($kept === '') $this->assertSame('menu', $data['drop'] ?? '', 'An empty order left the cookie in place');
        elseif ($kept !== null) $this->assertSame(['menu', $kept], $data['set'] ?? [], $value.' was not stored as '.$kept);
        else $this->assertArrayNotHasKey('set', $data, $value.' was stored');
    }

    # People and safety on one standard: a ban joins the capped list and is told by its kind, a letter of a form has one writer, a password one rule, a date one type
    #[Test]
    public function peopleAndSafetyFollowOneStandard(): void
    {
        $save = $this->getBody('admin/modules/security.php', 'bansave');
        $this->assertStringContainsString("\$cont['blocker_ip'] = getBanList(\$cidr.'|'.\$hash.'|'.\$time.'|'.\$info);", $save, 'A ban of the panel skips the drop and the cap');
        $this->assertStringNotContainsString("getVar('post', 'id', 'num')", $save, 'A ban is told by a hidden number');
        $list = $this->getBody('admin/modules/security.php', 'banlist');
        foreach (['ip', 'user'] as $kind) {
            $this->assertStringContainsString("['nameattr' => 'kind', 'valueattr' => '".$kind."']", $list, 'The form of the '.$kind.' ban sends no kind');
        }
        $mails = [['admin/modules/security.php', 'bansave'], ['admin/modules/admins.php', 'save'], ['modules/account/admin/index.php', 'addsave']];
        foreach ($mails as [$path, $func]) {
            $body = $this->getBody($path, $func);
            $this->assertStringContainsString('addFormMail(', $body, $path.' '.$func.' queues its letter by hand');
            $this->assertStringNotContainsString('->addQueue(', $body, $path.' '.$func.' queues its letter by hand');
        }
        $this->assertStringContainsString("if (\$mail && \$chg && addFormMail('account'", $this->getBody('modules/account/admin/index.php', 'addsave'),
            'A user save sends the login mail without a new password');
        $rules = [['admin/modules/admins.php', 'save'], ['modules/account/admin/index.php', 'addsave'], ['modules/account/index.php', 'finnewuser'],
            ['modules/account/index.php', 'savepass']];
        foreach ($rules as [$path, $func]) {
            $this->assertStringContainsString('checkPassRule(', $this->getBody($path, $func), $path.' '.$func.' checks a password by hand');
        }
        $this->assertStringContainsString('checkPassRule($apass, $atwo)', $this->getFile('admin/index.php'), 'The first administrator skips the rule of a password');
        foreach (['admin/index.php', 'admin/modules/admins.php', 'modules/account/admin/index.php', 'modules/account/index.php'] as $path) {
            $this->assertStringNotContainsString('_CHARMIN', $this->getFile($path), $path.' checks minpass by hand');
            $this->assertStringNotContainsString('$stop[] = _NOPASS', $this->getFile($path), $path.' checks a password by hand');
        }
        $form = $this->getBody('modules/account/admin/index.php', 'add');
        foreach (["'itype' => 'email', 'name_attr' => 'email'", "'itype' => 'password', 'name_attr' => 'pass',", "'itype' => 'password', 'name_attr' => 'pass2',"] as $field) {
            $this->assertStringContainsString($field, (string)preg_replace('/,\s+/', ', ', $form), 'The user form shows a credential as plain text: '.$field);
        }
        $this->assertStringNotContainsString("[login]\\n'", $form, 'The login mail of a user joins the nick and the password by a literal backslash and n');
        $site = (string)preg_replace('/,\s+/', ', ', $this->getFile('modules/account/index.php'));
        foreach (["'itype' => 'password', 'name_attr' => 'user_password',", "'itype' => 'password', 'name_attr' => 'user_password2',", "'itype' => 'email', 'name_attr' => 'mail',",
            "'itype' => 'email', 'name_attr' => 'email',"] as $field) {
            $this->assertStringContainsString($field, $site, 'A form of the site shows a credential as plain text: '.$field);
        }
        $this->assertDoesNotMatchRegularExpression("/\['name_attr' => '(user_password2?|pass2?|apwd2?|mail|email|aemail)',/", $site.$form.$this->getFile('admin/index.php'),
            'A credential field has no type');
        foreach ([['admin/modules/logs.php', 'logview'], ['admin/modules/logs.php', 'logins'], ['admin/modules/groups.php', 'getPointsJournal']] as [$path, $func]) {
            $body = $this->getBody($path, $func);
            $this->assertStringNotContainsString('\d{4}-\d{2}-\d{2}', $body, $path.' '.$func.' reads a date by its own pattern');
            $this->assertMatchesRegularExpression("/getVar\('get', '(from|day)', 'date', ''\)/", $body, $path.' '.$func.' reads no date by its type');
        }
    }
}
