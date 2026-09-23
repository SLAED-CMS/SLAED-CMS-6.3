<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Stage S13 of docs/node: the public and administrative routes of Node and the view preparer. The behaviour is driven by
 * tests/Support/route_probe.php: one disposable MariaDB database from the shipped table.sql, a scratch configuration that registers
 * three types, a scratch upload root with the guards of the release, and the real index.php and admin.php answering real HTTP
 * requests of the built-in server with tests/Support/route_web.php as router. The static half reads the files of the stage.
 */
final class NodeRouteTest extends TestCase
{
    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

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
        $this->assertSame([200, true, 200], $run['sort'], 'Another sort is not answered noindex');
        $this->assertSame([false, true], $run['cat'], 'The read right of a category is not applied');
        $this->assertSame([404, 200, 404], $run['off'], 'The disabled type answers a guest or a forged context, or not its main administrator');
        $this->assertSame([200, true], $run['home'], 'The start page is not the list of its type');
    }

    # A guest list is stored bound to the deadline of Node and served again without reading the database; a signed-in visitor is always served live
    #[Test]
    public function aGuestListIsServedFromThePageCache(): void
    {
        $run = $this->getRuns()['lists'];
        $this->assertSame(1, $run['cache']['pages'], 'The guest list was not stored');
        $this->assertSame([PHP_INT_MAX], $run['cache']['until'], 'The stored list is not bound to the deadline of Node');
        $this->assertTrue($run['nostore'], 'A bound Node page reaches the browser cache');
        $this->assertFalse($run['hit'], 'The second guest request read the database instead of the stored page');
        $this->assertTrue($run['user'], 'A signed-in visitor was served a stored page');
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
    }

    # The editor attachment answers its own name of an accessible material alone, through the controlled private answer, and nothing crafted
    #[Test]
    public function theAttachmentAnswersOnlyItsOwnName(): void
    {
        $run = $this->getRuns()['attach'];
        $this->assertSame([200, 'image/png', 'private, no-cache, must-revalidate, no-transform', 70], $run['file']);
        $this->assertSame([200, 0], $run['head']);
        $this->assertSame(200, $run['thumb']);
        $this->assertSame(array_fill(0, 8, 404), $run['refused'],
            'A name of no text, a crafted key, an extra, a mixed or a wrong flag, a closed material or a foreign type is served');
        $this->assertSame(403, $run['direct'], 'The upload directory of the type is open to direct access');
        $this->assertFalse($run['climb'], 'A key climbing out of the directory served a file');
    }

    # The resources: an image is shown without counting, a download is counted before a whole body or a range from zero only, an external visit before its redirect
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
        $this->assertSame([404, 404], $run['foreign']);
        $this->assertSame(403, $run['direct']);
    }

    # A report needs POST and the token, is stored once with its reporter, and a second report of the same visitor within a minute is refused
    #[Test]
    public function theReportIsGuarded(): void
    {
        $run = $this->getRuns()['reports'];
        $this->assertSame(405, $run['get']);
        $this->assertSame(403, $run['token']);
        $this->assertSame([303, 1, 3], $run['sent'], 'The report is not stored with its registered reporter');
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
        $this->assertSame([200, 'application/json; charset=UTF-8', 'attachment; filename="node-temp.json"', 'slaed.node'], $run['export']);
        $this->assertSame([303, 1], $run['clone']);
        $this->assertSame([422, 100, 422, 100, 303, 150], $run['limits'], 'A limit a stored type exceeds was saved, or a limit every type keeps was refused');
        $this->assertSame([303, 0, 403, 1], $run['delete'], 'The type was not deleted, or a moderator deleted one');
        $this->assertSame([303, 404, 200], $run['off']);
    }

    # A type an unfinished configuration operation holds answers 503 without a stored copy, and the list opens again once the marker is gone
    #[Test]
    public function aHeldTypeIsClosed(): void
    {
        $this->assertSame([503, '60', true, 200], $this->getRuns()['hold']);
    }

    # The view preparer answers the exact keys of every mode, the same keys for a target, resources without their source and refuses every foreign pair
    #[Test]
    public function theViewPreparerKeepsItsContract(): void
    {
        $run = $this->getRuns()['data'];
        $this->assertSame(array_fill_keys(['list', 'view', 'card', 'tcard', 'tblock', 'tsearch'], true), $run['keys']);
        $this->assertSame([3, 3, 3, 3, 3], $run['refused']);
        $this->assertSame('index.php?name=news&op=view&id=101', $run['full']['href']);
        $this->assertSame(['Alpha', 'intro of 101', true, 'index.php?name=news&cat=1', 'Open', 'anna', 'index.php?name=account&op=view&uname=anna'],
            [$run['full']['title'], $run['full']['intro'], $run['full']['body'], $run['full']['chref'], $run['full']['ctitle'], $run['full']['author'], $run['full']['ahref']]);
        $this->assertSame(['cover', 'files'], $run['full']['roles']);
        $this->assertSame([true, 'index.php?name=news&op=asset&id=2', 'index.php?name=news&op=report&id=2', false, true, 'index.php?name=news&op=asset&id=1'],
            array_values($run['asset']));
        $this->assertSame(['title' => 'Beta', 'intro' => '', 'body' => '', 'views' => null, 'fields' => [], 'assets' => []], $run['light']);
        $this->assertSame('', $run['list'], 'A list card carries the body');
        $this->assertSame(['4.333333', null], $run['average']);
        $this->assertSame([false], $run['trusted'], 'Markup of an untrusted text reached the page');
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
        foreach (['partials/node/list.html', 'partials/node/view.html'] as $one) $this->assertFileExists($root.'/templates/lite/'.$one);
        foreach (['card', 'block', 'search', 'image', 'gallery', 'download', 'player',
            'link'] as $one) $this->assertFileExists($root.'/templates/lite/fragments/node/'.$one.'.html');
        $this->assertFileEquals($root.'/templates/lite/fragments/repeat.html', $root.'/templates/admin/fragments/repeat.html', 'The repeatable rows differ between the themes');
        foreach (['index.php', 'lang', 'admin/index.php', 'admin/lang', 'admin/info/ru.md'] as $one) $this->assertFileExists($root.'/modules/node/'.$one);
        foreach (['controllers', 'repositories', 'src', 'sql'] as $one) $this->assertDirectoryDoesNotExist($root.'/modules/node/'.$one);
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/modules/node/index.php'), 'The public controller runs SQL');
        $this->assertStringNotContainsString('getSqlQuery', (string)file_get_contents($root.'/modules/node/admin/index.php'), 'The administrative controller runs SQL');
    }

    # Routing, cache map, navigation and rights know the types from the registry alone: no list of type names in the shared code
    #[Test]
    public function theSharedCodeKnowsTypesFromTheRegistry(): void
    {
        $index = (string)file_get_contents(self::getRoot().'/index.php');
        $this->assertStringContainsString("if (\$nname === 'node' || isset(\$conf['node']['types'][\$nname])) {", $index);
        $this->assertStringContainsString("require_once BASE_DIR.'/modules/node/index.php';", $index);
        $this->assertStringContainsString("if ((\$op ?? '') !== '' || !isset(\$conf['node']['types'][\$name ?? ''])) return false;", self::getBody('core/system.php',
            'checkPageCache'));
        $this->assertStringContainsString('getNodeTypeMap()[$con]', self::getBody('core/system.php', 'getModuleName'));
        $this->assertStringContainsString("if (isset(\$conf['node']['types'][\$modul])) \$modul = 'node-'.\$modul;", self::getBody('core/system.php', 'is_admin_modul'));
        $this->assertStringContainsString('getNodeTypeMap()', self::getBody('core/helpers.php', 'getTplModuleSelect'));
        $this->assertStringContainsString('getNodeTypeMap()', (string)file_get_contents(self::getRoot().'/blocks/modules.php'));
        $this->assertStringContainsString('if ($seo instanceof Closure) $seo = $seo();', self::getBody('core/system.php', 'setHead'));
        foreach (['core/system.php', 'core/helpers.php', 'index.php', 'admin/index.php', 'blocks/modules.php'] as $file) {
            $this->assertDoesNotMatchRegularExpression("/'(?:news|docs|files|faq|pages|jokes|links|media|help|content)'\\s*=>\\s*'node/",
                (string)file_get_contents(self::getRoot().'/'.$file));
        }
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
}
