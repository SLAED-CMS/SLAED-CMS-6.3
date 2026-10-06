<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

# The public and administrative routes of Node and the view preparer, driven by tests/Support/route_probe.php; the static half reads the files of the stage
final class NodeRouteTest extends TestCase
{
    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    # The probe builds one disposable MariaDB database from the shipped table.sql, a scratch configuration of three types and a scratch upload root with the release guards
    # The real index.php and admin.php answer real HTTP requests of the built-in server with tests/Support/route_web.php as router
    # Run the probe once and memoize the runs; a probe that cannot create its database or start its server is a failure, not a skip
    private function getRuns(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            self::$probe = $data;
        }
        return self::$probe['runs'];
    }

    # Run one mode of the probe once and memoize it: secure - the public form, tree - the document tree, modes - the display modes
    # The mode seo covers the canonical addresses, the head and the feeds
    # Each mode builds its own disposable database and server
    private function getMode(string $mode): array
    {
        static $runs = [];
        if (!isset($runs[$mode])) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_'.$mode;
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' '.$mode.' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            $runs[$mode] = $data['runs'][$mode];
        }
        return $runs[$mode];
    }

    # The body of one function of a file
    private static function getBody(string $file, string $name): string
    {
        $code = (string)file_get_contents(self::getRoot().'/'.$file);
        $from = strpos($code, 'function '.$name.'(');
        $to = ($from === false) ? false : strpos($code, "\n}\n", $from);
        return ($from === false || $to === false) ? '' : substr($code, $from, $to - $from);
    }

    # The list of a type: the clean parameters, the paging, the rights of a category, the start page, a forged context and the disabled type
    #[Test]
    public function theListAnswersItsParametersAndRights(): void
    {
        $run = $this->getRuns()['lists'];
        $this->assertSame([200, true, true, false, false], $run['list'], 'Page one of news shows Gamma and Beta; Alpha is on page two and Members is closed to a guest');
        $this->assertTrue($run['canon']);
        $this->assertSame([200, true, 404], $run['page']);
        $this->assertSame([405, 'GET, HEAD'], $run['post']);
        $this->assertSame(404, $run['node'], 'The technical name node has a public route');
        $this->assertSame([400, 400, 400, 400, 404, 404, 404], $run['bad'], 'cat, let, order and dir are checked, a foreign category and unknown operations are not found');
        $this->assertSame([301, 'index.php?name=news'], $run['clean'], 'The explicit default sort is not sent back to the clean address');
        $this->assertSame([200, 400], $run['published'], 'The sort published the reader accepts for every type is refused by the route, or a sort outside the list passes');
        $this->assertSame([200, true, 200], $run['sort'], 'Another sort is not answered noindex');
        $this->assertSame([404, false, 200, true, 200], $run['cat'], 'A category the guest may not read is not missing, or the readable ones do not answer');
        $this->assertSame([404, 200, 404], $run['off'], 'The disabled type answers a guest or a forged context, or not its main administrator');
        $this->assertSame([200, true], $run['home'], 'The start page is not the list of its type');
    }

    # A list is rendered from the database on every request, for a guest as for a signed-in visitor, and never reaches a browser cache
    #[Test]
    public function aListIsRenderedLiveForEveryVisitor(): void
    {
        $run = $this->getRuns()['lists'];
        $this->assertTrue($run['nostore'], 'A Node page reaches the browser cache');
        $this->assertTrue($run['hit'], 'The second guest request did not read the changed title');
        $this->assertTrue($run['user'], 'A signed-in visitor did not read the changed title');
    }

    # One material: the type of the route, the rights, the controlled addresses, and the view counted after the answer for GET alone
    #[Test]
    public function theMaterialAnswersItsTypeAndRights(): void
    {
        $run = $this->getRuns()['view'];
        $this->assertSame([200, true, true, true, false], $run['view'],
            'The material lacks its title, its controlled attachment or asset address, or shows a direct upload address');
        $this->assertSame(1, $run['views']);
        $this->assertSame(1, $run['head'], 'HEAD counted a view');
        $this->assertSame([404, 200, 404, 200, 404, 404, 404, 404], $run['rights'],
            'A closed category, a pending material, the moderator of another type, a foreign type or a malformed id answers the wrong status');
        $this->assertStringEndsWith('/index.php?name=news&op=asset&id=1', $run['cover'][0], 'The local cover is not an absolute address of its counting route');
        $this->assertSame('https://cdn.example.com/p.png', $run['cover'][1], 'An external cover is not its own address in og:image');
    }

    # One template resolver for the module and the file block: a block takes the file of the display mode of its type, and the poster of the player is the role poster
    #[Test]
    public function theBlockAndThePlayerFollowTheType(): void
    {
        $this->assertStringContainsString('function getNodeTplName(', self::getBody('core/system.php', 'getNodeTplName'), 'The resolver is not shared by the core');
        $this->assertSame('', self::getBody('modules/node/index.php', 'getNodeTplName'), 'The module keeps its own resolver');
        $block = (string)file_get_contents(self::getRoot().'/blocks/node.php');
        $this->assertStringContainsString("getNodeTplName('fragments', 'block', \$types[\$node->tid])", $block, 'The block ignores the display mode of its type');
        $this->assertStringNotContainsString("getHtmlFrag('node/block'", $block);
        $this->assertStringContainsString("\$poster = (string)(\$view['assets']['poster'][0]['href'] ?? '');", self::getBody('modules/node/index.php', 'getNodeAssetView'));
    }

    # The file route answers the attachment of an accessible material by its own name alone, through the controlled private answer, and nothing crafted
    #[Test]
    public function theAttachmentAnswersOnlyItsOwnName(): void
    {
        $run = $this->getRuns()['attach'];
        $this->assertSame([200, 'image/png', 'private, no-cache, must-revalidate, no-transform', 70], $run['file']);
        $this->assertSame([200, 0], $run['head']);
        $this->assertSame(200, $run['thumb']);
        $this->assertSame(array_fill(0, 20, 404), $run['refused'],
            'A name of no text, a crafted key, an extra, a mixed or a wrong flag, a closed material, a foreign owner, a repeated or an unknown key is served');
        $this->assertSame(200, $run['coded'], 'A percent-encoded key is not the same key as its plain form');
        $this->assertSame(410, $run['direct'], 'The upload directory of the type is open to direct access, the light path served it');
        $this->assertFalse($run['climb'], 'A key climbing out of the directory served a file');
        $this->assertSame(405, $run['post'], 'The file route answered a write method');
        $this->assertSame(404, $run['retired'], 'The retired attach operation still serves a file');
    }

    # The resources: an image is shown without counting, a download is counted before a whole body or a range from zero only, an external visit before its redirect
    # An external address of an image role is no redirect
    #[Test]
    public function theResourcesCountOnlyAnAllowedStart(): void
    {
        $run = $this->getRuns()['assets'];
        $this->assertSame([200, 'image/png', 0], $run['image']);
        $this->assertSame([200, 'application/octet-stream', "attachment; filename=\"manual.pdf\"; filename*=UTF-8''manual.pdf", 2000, 1], $run['download']);
        $this->assertSame(1, $run['head'], 'HEAD counted a download');
        $this->assertSame([206, 206, 10, 2], $run['range'], 'A range from zero is not counted once, or a later range is counted');
        $this->assertSame([416, 2], $run['unmet']);
        $this->assertSame([302, 'https://example.com/tool', 1], $run['link']);
        $this->assertSame([404, '', 0], $run['shown'], 'An external address of an image role became a redirect of the site');
        $this->assertSame([404, 404], $run['foreign']);
        $this->assertSame(410, $run['direct'], 'The light path served a file of a private folder');
    }

    # A report needs POST and the token, is stored once with its reporter, and a second report of the same visitor within a minute is refused
    #[Test]
    public function theReportIsGuarded(): void
    {
        $run = $this->getRuns()['reports'];
        $this->assertSame(405, $run['get']);
        $this->assertSame(403, $run['token']);
        $this->assertSame([303, 1, 3], $run['sent'], 'The report is not stored with its registered reporter');
        $this->assertStringEndsWith('index.php?name=news&op=view&id=101', $run['back'], 'The report does not return to the material of the resource');
        $this->assertSame([429, true], $run['again']);
        $this->assertSame(404, $run['image'], 'A role without reports takes a report');
    }

    # The public form: the workflow opens it, a preview writes nothing, a submission needs its token and lands pending; a new upload is previewed to its owner alone
    #[Test]
    public function thePublicFormFollowsTheWorkflow(): void
    {
        $run = $this->getRuns()['form'];
        $this->assertSame(403, $run['guest']);
        $this->assertSame(404, $run['docs'], 'A type without public submission has a form');
        $this->assertSame([200, true, true, true], $run['form']);
        $this->assertSame([true, false, true], $run['link'], 'The link input of a resource is offered to a writer whose material is published directly,'
            .' which the service refuses, or missing where it goes to moderation');
        $this->assertSame([200, true, 0], $run['preview'], 'The preview wrote a material or does not show it');
        $this->assertSame([403, 0], $run['notoken']);
        $this->assertSame(400, $run['badact']);
        $this->assertSame([303, 'index.php?name=news', 1, ['status' => 1, 'uid' => 2, 'cid' => 1]], $run['submit']);
        $this->assertSame([422, 1], $run['invalid']);
        [$code, $key, $own, $file, $mine, $cache, $other, $guest, $moder, $none] = $run['upload'];
        $this->assertSame([200, true, true, true, 200], [$code, $key, $own, $file, $mine], 'The upload is not stored with its owner or not previewed to that owner');
        $this->assertStringContainsString('no-store', $cache);
        $this->assertSame([404, 404, 200, 1], [$other, $guest, $moder, $none], 'The preview of an upload reaches another visitor, or the preview wrote a material');
        $this->assertSame(303, $run['bound'][0]);
        $this->assertSame('image', $run['bound'][1][1]);
        $this->assertSame([422, 0], $run['steal'], 'The file of another visitor was bound');
    }

    # The administrative entry: the gate of the right node and of each node-<type>, the screens each right opens, the move with its notice and the deletion
    #[Test]
    public function theAdministrationFollowsTheRights(): void
    {
        $run = $this->getRuns()['admin'];
        $this->assertSame([200, true, true], $run['queue']);
        $this->assertSame([200, 403, 200, 404], $run['gate']['moder'], 'The moderator of news opens the types or a material of docs');
        $this->assertSame([200, 200, 404], $run['gate']['boss'], 'The manager of Node edits a material');
        $this->assertSame([200, 200, 404], $run['gate']['docsman']);
        $this->assertTrue($run['gate']['user'], 'A site user passed the gate of the panel');
        $this->assertSame(404, $run['gate']['bogus']);
        $this->assertSame(405, $run['gate']['getmove']);
        $this->assertSame([200, 200], $run['gate']['info'], 'The help of the module does not open for the manager or the moderator');
        $this->assertSame([404, false, true], $run['gate']['queue'], 'The queue of the moderator of news offers or lists the materials of docs');
        $this->assertSame([303, 2, 2, 1, 'anna@probe.test', 'node'], $run['move'], 'The publication did not move once or did not notify the author');
        $this->assertSame([409, 2], $run['stale']);
        $this->assertSame([303, null], $run['delete']);
    }

    # Two editors of one version: the second gets 409 without a write, keeps the own input and the old version, continues without a write and then saves normally
    #[Test]
    public function aConflictKeepsTheInputAndWritesNothing(): void
    {
        $run = $this->getRuns()['admin'];
        $this->assertSame([303, 409, 'Beta first', true, true, '1', 404], $run['clash']);
        $this->assertSame([200, '2', 'Beta first', true], $run['keep'], 'The continuation wrote, lost the input or kept the stale version');
        $this->assertSame([303, 'Beta second', 3], $run['save']);
    }

    # The type screens of the manager: create disabled, a repeated name refused, export, clone, the limits refused when a type would not fit, deletion and switching off
    #[Test]
    public function theTypeScreensGoThroughTheService(): void
    {
        $run = $this->getRuns()['types'];
        $this->assertSame([303, 1, 0, true], $run['create']);
        $this->assertSame([422, 1], $run['twice']);
        $this->assertSame([303, false, true, true, true], $run['roles'], 'The report switch of a role outside download and link was stored, or the form offers no'
            .' report row or no closed list of display modes');
        $this->assertSame([422, 0, 422, 0], $run['mode'], 'A display mode outside the list or the support mode of a standard type was stored');
        $this->assertSame([200, 'application/json; charset=UTF-8', 'attachment; filename="node-temp.json"', 'slaed.node'], $run['export']);
        $this->assertSame([303, 1], $run['clone']);
        $this->assertSame([422, 100, 422, 100, 303, 150, 500, 409, 100], $run['limits'], 'A limit a stored type exceeds was saved, a limit every type keeps was refused,'
            .' or a failed store and a pending journal did not answer 500 and 409');
        $this->assertSame([422, 422, 0, 0], $run['send'], 'A negative or missing write window was saved, or the window zero was refused');
        $this->assertSame([303, 0, 403, 1], $run['delete'], 'The type was not deleted, or a moderator deleted one');
        $this->assertSame([303, 404, 200], $run['off']);
    }

    # A type an unfinished configuration operation holds answers 503 before its op and method are checked, and opens again once the marker is gone
    #[Test]
    public function aHeldTypeIsClosed(): void
    {
        $this->assertSame([503, '60', true, 200, [503, 503]], $this->getRuns()['hold'], 'A held type answered before its marker was checked');
    }

    # The view preparer answers the exact keys of every mode, the same keys for a target, resources without their source and refuses every foreign pair
    #[Test]
    public function theViewPreparerKeepsItsContract(): void
    {
        $run = $this->getRuns()['data'];
        $this->assertSame(array_fill_keys(['list', 'view', 'card', 'tcard'], true), $run['keys']);
        $this->assertSame([3, 3, 3, 3, 3, 3, 3], $run['refused'], 'A light target was prepared for a mode other than card');
        $this->assertSame('index.php?name=news&op=view&id=101', $run['full']['href']);
        $this->assertSame(['Alpha', 'intro of 101', true, 'index.php?name=news&cat=1', 'Open', 'anna', 'index.php?name=account&op=view&uname=anna'],
            [$run['full']['title'], $run['full']['intro'], $run['full']['body'], $run['full']['chref'], $run['full']['ctitle'], $run['full']['author'], $run['full']['ahref']]);
        $this->assertSame(['cover', 'files'], $run['full']['roles']);
        $this->assertSame([true, 'index.php?name=news&op=asset&id=2', 'index.php?name=news&op=report&id=2', false, true, 'index.php?name=news&op=asset&id=1'],
            array_values($run['asset']));
        $this->assertSame(['title' => 'Beta', 'intro' => '', 'body' => '', 'views' => null, 'fields' => [], 'assets' => []], $run['light']);
        $this->assertSame('', $run['list'], 'A list card carries the body');
        $this->assertSame(['4.333333', null], $run['average']);
        $this->assertSame([false, false, true, false, true], $run['trusted'],
            'Markup of an untrusted text or around a trusted tag reached the page, the tag lost its content, or a script reached the plain intro');
    }

    # The class files of the stage: the preparer with its one public method, the map line, the template check, the shipped templates and the module tree
    #[Test]
    public function theStageFilesKeepTheirContract(): void
    {
        $root = self::getRoot();
        require_once $root.'/core/classes/node/load.php';
        $ref = new ReflectionClass('NodeView');
        $this->assertTrue($ref->isFinal());
        $this->assertSame(['__construct', 'getNodeView'], array_map(fn(ReflectionMethod $m) => $m->getName(), $ref->getMethods(ReflectionMethod::IS_PUBLIC)));
        $this->assertStringContainsString("'NodeView' => 'view.php',", (string)file_get_contents($root.'/core/classes/node/load.php'));
        $this->assertStringNotContainsString('getHtml', (string)file_get_contents($root.'/core/classes/node/view.php'), 'The preparer calls the template engine');
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/core/classes/node/view.php'), 'The preparer runs SQL');
        $this->assertTrue(method_exists('Template', 'checkTemplateFile') || str_contains((string)file_get_contents($root.'/core/classes/template.php'),
            'public function checkTemplateFile(string $kind, string $name): bool'));
        $lite = $root.'/public/templates/lite';
        foreach (['partials/node/list.html', 'partials/node/view.html'] as $one) $this->assertFileExists($lite.'/'.$one);
        foreach (['card', 'block', 'image', 'gallery', 'download', 'player', 'link', 'tree'] as $one) $this->assertFileExists($lite.'/fragments/node/'.$one.'.html');
        $this->assertFileDoesNotExist($lite.'/fragments/node/search.html', 'The search module renders the rows of Node itself');
        $this->assertFileEquals($lite.'/fragments/repeat.html', $root.'/public/templates/admin/fragments/repeat.html', 'The repeatable rows differ between the themes');
        foreach (['index.php', 'lang', 'admin/index.php', 'admin/lang', 'admin/info/ru.md'] as $one) $this->assertFileExists($root.'/modules/node/'.$one);
        foreach (['controllers', 'repositories', 'src', 'sql'] as $one) $this->assertDirectoryDoesNotExist($root.'/modules/node/'.$one);
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/modules/node/index.php'), 'The public controller runs SQL');
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/modules/node/admin/index.php'), 'The administrative controller runs SQL');
    }

    # Routing, navigation and rights know the types from the registry alone: no list of type names in the shared code
    #[Test]
    public function theSharedCodeKnowsTypesFromTheRegistry(): void
    {
        $index = (string)file_get_contents(self::getRoot().'/public/index.php');
        $this->assertStringContainsString("if (\$nname === 'node' || isset(\$conf['node']['types'][\$nname])) {", $index);
        $this->assertStringContainsString("require_once BASE_DIR.'/modules/node/index.php';", $index);
        $this->assertStringContainsString('getNodeTypeMap()[$con]', self::getBody('core/system.php', 'getModuleName'));
        $this->assertStringContainsString("if (isset(\$conf['node']['types'][\$modul])) \$modul = 'node-'.\$modul;", self::getBody('core/system.php', 'is_admin_modul'));
        $this->assertStringContainsString('getNodeTypeMap()', self::getBody('core/helpers.php', 'getTplModuleSelect'));
        $this->assertStringContainsString('getNodeTypeMap()', (string)file_get_contents(self::getRoot().'/blocks/modules.php'));
        foreach (['core/system.php', 'core/helpers.php', 'public/index.php', 'admin/index.php', 'blocks/modules.php'] as $file) {
            $this->assertDoesNotMatchRegularExpression("/'(?:news|docs|files|faq|pages|jokes|links|media|help|content)'\\s*=>\\s*'node/",
                (string)file_get_contents(self::getRoot().'/'.$file));
        }
    }

    # A file of the public form is stored only under the upload right of the type, for an active role that is no link, within the max of its role and maxfiles of the rule
    #[Test]
    public function theFormStoresAFileOnlyWithinItsRightAndLimits(): void
    {
        $run = $this->getMode('secure')['upload'];
        $this->assertSame([422, 0], $run['guest'], 'A guest stored a file although the rule of the type has no guest upload');
        $this->assertSame([200, 1], $run['user'], 'A member with the upload right could not store a cover');
        $this->assertSame([422, 1], $run['role'], 'A second file of a role with max 1 was stored');
        $this->assertSame([422, 2], $run['request'], 'A third file of a request under maxfiles 2 was stored');
        $this->assertSame([422, 0], $run['inactive'], 'An inactive role took a file');
        $this->assertSame([422, 0], $run['link'], 'A link role took a file');
    }

    # A visitor who does not moderate the type waits limits.send after the last material of the address; a moderator of the type does not
    #[Test]
    public function thePublicWriteWaitsForTheWindowOfTheAddress(): void
    {
        $this->assertSame([303, 429, 303, 2], $this->getMode('secure')['window'], 'The second submit inside the window was stored, or the window held the moderator');
    }

    # A guest passes the captcha of comments on every post: none or a forged one stores no material and sends no mail, a solved one submits; a member sees none
    #[Test]
    public function aGuestSubmitsOnlyThroughTheCaptcha(): void
    {
        $this->assertSame([true, false, 422, 422, 0, 0, 303, 1, true], $this->getMode('secure')['captcha'],
            'The captcha is missing for a guest or shown to a member, a post without a solved captcha stored a material or a mail, or a solved one was refused');
    }

    # The title of a material reaches the search result escaped once, in the link and in its tooltip
    #[Test]
    public function theSearchEscapesANodeTitleOnce(): void
    {
        $this->assertSame([true, false], $this->getMode('secure')['search'], 'The tooltip of a Node title in search is escaped twice');
    }

    # The form offers a category only when its view right shows it and its post right admits the writer: a guest sees Lobby alone, a member Secret and Open as well
    #[Test]
    public function theFormOffersOnlyCategoriesTheWriterSeesAndPostsTo(): void
    {
        $this->assertSame([true, false, false, true, true], $this->getMode('secure')['cats'],
            'The form offered a hidden category or one closed to the writer, or missed an open one');
        $this->assertSame([403, 200], $this->getMode('secure')['hidden'], 'A guest posted into a category hidden from guests, or a member could not');
    }

    # A related or parent link to a material the writer may not read - a closed category or a scheduled one - answers exactly as a link to a missing material
    #[Test]
    public function aLinkToAnUnreadableMaterialAnswersAsAMissingOne(): void
    {
        $run = $this->getMode('secure')['refs'];
        $this->assertSame([[422, 422], [422, 422], [422, 422], [200, 200]], $run['guest'],
            'A hidden or scheduled material answered unlike a missing one, or an open one was refused');
        $this->assertSame([200, 422], $run['user'], 'A member could not link a material of a members category, or linked a scheduled one');
    }

    # The pending notice to the moderators carries the raw title of a guest escaped in its HTML body
    #[Test]
    public function thePendingNoticeEscapesTheTitle(): void
    {
        $this->assertSame([303, true, true, false], $this->getMode('secure')['mail'],
            'The pending material or its notice is missing, or the notice carries live markup of the title');
    }

    # A profile is imported only from a .json file: the same export under another extension is refused and creates no type
    #[Test]
    public function theImportTakesOnlyAJsonFile(): void
    {
        $run = $this->getMode('secure')['import'];
        $this->assertTrue($run[0], 'The export of the probe type is no JSON');
        $this->assertSame([422, 0, 303, 1], array_slice($run, 1), 'A file of another extension was imported, or the .json file was refused');
    }

    # A refused import names its reason: a wrong extension, a file over 1 MiB and a definition the writer refuses each answer 422 with a text of their own
    #[Test]
    public function theImportNamesWhyAFileIsRefused(): void
    {
        [$ext, $big, $bad, $made] = $this->getMode('secure')['importtext'];
        $this->assertSame([422, 422, 422, 0], [$ext[0], $big[0], $bad[0], $made], 'A refused file was imported or answered another status');
        $texts = [$ext[1], $big[1], $bad[1]];
        $this->assertNotContains('', $texts, 'A refused import shows no alert');
        $this->assertSame($texts, array_values(array_unique($texts)), 'Two reasons of a refused import answer the same text');
    }

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
        $this->assertSame([['index.php?name=files&op=asset&id=12'], ['index.php?name=files&op=asset&id=11'], 3], [$run['files']['links'], $run['files']['images'],
            $run['files']['buttons']], 'The file card does not offer the download of the one material that has it beside both reading buttons');
        $this->assertContains('1.95 KB', $run['files']['chips'], 'The file card hides the size of its download');
        $this->assertContains('318', $run['files']['chips'], 'The file card hides the count of its downloads');
        $this->assertSame(['bi-download', 'sl-entry-content'], $run['filesview'], 'The files view does not put its resources above the text');
        $this->assertSame([['Gallery only', 'Overview video'], ['index.php?name=media&op=asset&id=16', 'index.php?name=media&op=asset&id=13'], 1],
            [$run['media']['titles'], $run['media']['images'], $run['grid']], 'The media tiles are not one grid, or the cover does not prefer the poster over the gallery');
        $this->assertContains('2026', $run['media']['chips'], 'The media tile hides the year of its field');
        $this->assertSame([['<video', 'sl-entry-content'], 1, ['Gallery only']], $run['mediaview'], 'The media view does not play above its text or loses the grid of its related');
    }

    # Every Node constant of the module exists in all six locales of its own scope, the two scopes do not repeat a name, and the module label lives in the panel language
    #[Test]
    public function theConstantsExistInEveryLocale(): void
    {
        $root = self::getRoot();
        $scopes = ['modules/node/lang', 'modules/node/admin/lang'];
        $names = [];
        foreach ($scopes as $dir) {
            $sets = [];
            foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $loc) {
                preg_match_all("/define\\('(_NODE_[A-Z0-9_]+)'/", (string)file_get_contents($root.'/'.$dir.'/'.$loc.'.php'), $hit);
                $sets[$loc] = $hit[1];
            }
            foreach ($sets as $loc => $list) $this->assertSame($sets['en'], $list, $dir.'/'.$loc.' differs from en');
            $names[$dir] = $sets['en'];
        }
        $this->assertSame([], array_values(array_intersect($names[$scopes[0]], $names[$scopes[1]])), 'A constant is defined in both scopes');
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $loc) $this->assertStringContainsString("define('_NODE','", (string)file_get_contents($root.'/admin/lang/'.$loc.'.php'));
        $used = [];
        foreach (['modules/node/index.php', 'modules/node/admin/index.php'] as $file) {
            preg_match_all('/\b(_NODE_[A-Z0-9_]+)\b/', (string)file_get_contents($root.'/'.$file), $hit);
            $used = array_merge($used, $hit[1]);
        }
        preg_match_all("/define\\('(_NODE_[A-Z0-9_]+)'/", (string)file_get_contents($root.'/admin/lang/en.php'), $hit);
        $this->assertSame([], array_values(array_diff(array_unique($used), $names[$scopes[0]], $names[$scopes[1]], $hit[1])), 'A constant the module uses is defined nowhere');
    }

    # The canonical address of a view and a list names no foreign parameter of the query, the start page keeps the address of the site and its slogan
    #[Test]
    public function theCanonicalAddressLeavesForeignParametersOut(): void
    {
        $run = $this->getMode('seo');
        $site = $run['site'];
        $this->assertSame([$site.'/index.php?name=news&op=view&id=102', $site.'/index.php?name=news&op=view&id=102', $site.'/index.php?name=news&cat=1',
            $site.'/index.php?name=news&num=2', $site.'/', $site.'/', true], $run['canon'], 'A canonical address carries a foreign parameter or a view is not indexed');
        $this->assertSame(['Probe slogan of the site', true, ''], $run['desc'], 'The start page on a Node type lost the slogan as its description');
    }

    # A category named by a language constant shows its translation in the head of the view, the list and the feed
    #[Test]
    public function aConstantCategoryTitleIsTranslated(): void
    {
        $this->assertSame([false, true, false, false, true], $this->getMode('seo')['const'], 'A category title stays a language constant');
    }

    # The block and the feed of a type without the publication sort still run by date while its own list keeps the title order
    #[Test]
    public function feedsRunByDateWhateverTheListSorts(): void
    {
        $this->assertSame([true, true, ['Doc one', 'Zebra doc']], $this->getMode('seo')['order'], 'A feed follows the list sort of its type');
    }

    # A page past the end with an explicit default sort is not found at once, a page inside still goes to the clean address
    #[Test]
    public function aPagePastTheEndIsNotFoundBeforeAnyRedirect(): void
    {
        $this->assertSame([404, '', 301, 'index.php?name=news&num=2'], $this->getMode('seo')['bound'], 'The redirect of the default sort leads to a page that does not exist');
    }

    # A path that is no address of the site finds the error page, a path after a script goes 301 to that script, and an address of the site answers as before
    #[Test]
    public function aRetiredAddressIsNotFoundOrMoved(): void
    {
        $run = $this->getMode('seo');
        $this->assertSame([404, true, 404, 404, [301, '/index.php?name=sitemap'], [301, '/'], [301, '/'], [301, '/admin.php'], [404, ''], [404, ''], 403, 410], $run['retired'],
            'A retired address renders a page, redirects elsewhere or a refusal of the light path is not gone');
        $this->assertSame([200, 200, 200, 200, true], $run['kept'], 'An address of the site or a file of the document root no longer answers');
    }

    # A name without a feed is not found, no name takes the start feed, the alternate link names the feed of the page and a description links absolutely
    #[Test]
    public function aFeedExistsOnlyWhereATypeHasOne(): void
    {
        $run = $this->getMode('seo');
        $this->assertSame([404, 404, 404, 200, true, $run['site'].'/index.php?go=rss&name=docs', $run['site'].'/index.php?go=rss&name=news', true, false], $run['rss'],
            'A feed answers without rss, the alternate link names a wrong feed or a description links relatively');
        $this->assertSame(['news', 'docs'], $run['data']['feeds'], 'The list of feeds differs from the types with rss');
    }

    # The support card of the administration shows every root message beyond the first page and every reply beyond the cap of a branch
    #[Test]
    public function theSupportCardShowsTheWholeCorrespondence(): void
    {
        $this->assertSame([303, true, 200, 20, 6], $this->getMode('seo')['support'], 'The support card hides a part of the correspondence');
    }

    # A stale block instance stays off without a log line per render, and the hint of the related field is bound to its input
    #[Test]
    public function aStaleBlockIsQuietAndTheRelatedHintIsBound(): void
    {
        $run = $this->getMode('seo');
        $this->assertSame([false, false, true], $run['stale'], 'A stale block shows to a guest or writes the site log on every render');
        $this->assertSame([true, true, false], $run['hint'], 'The hint of the related field has no id or is not described by its input');
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
        $this->assertSame([303, 'index.php?name=news', true, false], $run['notice'], 'The notice of a submission did not show exactly once');
        $this->assertSame([303, 'index.php?name=news&op=view&id=101', true, false], $run['report'], 'The notice of a report did not show exactly once');
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
        $this->assertSame([$site.'/index.php?name=news&cat=1', $site.'/index.php?name=news&num=2', $site.'/'], $run['canon'],
            'The canonical address of the start page names no type');
        $this->assertSame([true, true], $run['letters'], 'A letter drops the category of the list');
        $this->assertSame([false, false], $run['block'], 'An empty Node block shows its title or the problem notice');
    }

    # The favorites shelf reads without a token in its address, answers the member and nothing to a guest
    #[Test]
    public function theFavoritesShelfReadsWithoutAToken(): void
    {
        $this->assertSame([true, false, 200, true, ''], $this->getMode('head')['favorites'], 'The favorites shelf carries the token in its address or refuses the read');
    }
}
