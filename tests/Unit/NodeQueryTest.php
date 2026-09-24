<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Stage S09 of docs/node: NodeQuery reads types, materials, targets, resources, trees and sitemap rows against the request
 * context, and getNodeContext() builds that context from the trusted state of the request. Every behaviour is driven by
 * tests/Support/node_probe.php in its query mode: a disposable MariaDB database filled with the shipped schema, a scratch
 * configuration that carries the probe types, the shipped reader copied byte for byte next to a factory whose closed map
 * names a test extension, and one child process per visitor for the context.
 */
final class NodeQueryTest extends TestCase
{
    private static array $probe = [];

    # The file of the reader
    private static function getFile(): string
    {
        return dirname(__DIR__, 2).'/core/classes/node/query.php';
    }

    # Run the probe once in its query mode and memoize the runs; a probe that cannot create its database is a failure, not a skip
    private function getRuns(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/node_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_query';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' query 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'];
    }

    # One refused call of the probe must carry the invalid code and the path of its first error
    private function assertRefused(array $call, string $path, string $name = ''): void
    {
        $this->assertFalse($call['ok'], $name.' was accepted');
        $this->assertSame('NodeException', $call['class'], $name);
        $this->assertSame(3, $call['code'], $name);
        $this->assertSame('Invalid node input: '.$path, $call['msg'], $name);
    }

    # The public methods of the reader are exactly the approved API of 05-core-api.md with their exact parameters and results
    #[Test]
    public function theReaderHasTheContractMethodsAndNothingElse(): void
    {
        $want = [
            '__construct' => ['Database db', 'NodeContext context', 'Field field', ''],
            'filterNodeSettings' => ['string ext', 'array settings', 'array fields', 'array'],
            'getNode' => ['int id', 'NodeType type', '?Node'],
            'getNodeAsset' => ['int id', 'NodeType type', '?NodeAsset'],
            'getNodeContent' => ['int id', 'NodeType type', '?Node'],
            'getNodeCount' => ['int'],
            'getNodeDeadline' => ['?int'],
            'getNodeList' => ['array'],
            'getNodeSitemap' => ['int after = 0', 'int limit = 500', 'array'],
            'getNodeTarget' => ['string type', 'int id', '?NodeTarget'],
            'getNodeTargetList' => ['array refs', 'array'],
            'getNodeTree' => ['int after = 0', 'int limit = 500', 'array'],
            'getNodeType' => ['string name', '?NodeType'],
            'getNodeTypeExport' => ['string name', 'string'],
            'getNodeTypeList' => ['array'],
            'setNodeAuthor' => ['int uid', 'self'],
            'setNodeCategory' => ['int cid', 'self'],
            'setNodeExtension' => ['?NodeExtension ext', 'self'],
            'setNodeHome' => ['bool home = true', 'self'],
            'setNodeLetter' => ['string letter', 'self'],
            'setNodeOrder' => ['string order', "string dir = 'desc'", 'self'],
            'setNodePage' => ['int page', 'int limit', 'self'],
            'setNodePublished' => ['?string from', '?string until', 'self'],
            'setNodeSearch' => ['string text', 'self'],
            'setNodeSets' => ['bool load', 'self'],
            'setNodeStatus' => ['NodeStatus status', 'self'],
            'setNodeType' => ['NodeType type', 'self'],
            'setNodeTypes' => ['array types', 'self'],
        ];
        require_once dirname(__DIR__, 2).'/core/classes/pdo.php';
        require_once dirname(__DIR__, 2).'/core/classes/comment.php';
        require_once dirname(__DIR__, 2).'/core/classes/field.php';
        require_once dirname(__DIR__, 2).'/core/classes/node/load.php';
        $ref = new ReflectionClass('NodeQuery');
        $this->assertTrue($ref->isFinal());
        $have = [];
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $one) {
            $shape = [];
            foreach ($one->getParameters() as $arg) {
                $shape[] = $arg->getType().' '.$arg->getName().($arg->isDefaultValueAvailable() ? ' = '.var_export($arg->getDefaultValue(), true) : '');
            }
            $shape[] = (string)$one->getReturnType();
            $have[$one->getName()] = $shape;
        }
        ksort($have);
        ksort($want);
        $this->assertSame($want, $have);
        $this->assertSame([], array_map(fn($v) => $v->getName(), $ref->getMethods(ReflectionMethod::IS_STATIC)));
    }

    # The reader decides by its context alone: no request, session, cookie or user global, no legacy access helper, and no cache of its own
    #[Test]
    public function theReaderReadsNoRequestAndNoUserGlobal(): void
    {
        $code = (string)file_get_contents(self::getFile());
        foreach (['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION', '$_SERVER', 'is_acess(', 'is_moder(', 'is_user(', 'isAdmin(', 'getIp(', 'catmids(',
            'file_put_contents', 'serialize(', 'Cache::', 'static $', 'OFFSET'] as $bad) {
            $this->assertStringNotContainsString($bad, $code, $bad);
        }
        $this->assertSame(0, preg_match('/(?<![A-Za-z])Node(?:Config|Registry|Provider)/', $code), 'A configuration or registry class appears in the reader');
        preg_match_all('/^\s*global ([^;]+);/m', $code, $hit);
        $this->assertSame(['$conf'], array_values(array_unique($hit[1])), 'The reader reads another global than the configuration');
        $this->assertStringContainsString("require_once __DIR__.'/ext/load.php'", $code);
        $this->assertSame(1, substr_count($code, 'getConfig()'), 'The configuration is read again in more than the documented place');
    }

    # The probe reads with the shipped reader: its copy is byte for byte, and the copied factory differs in the line of its closed map alone
    #[Test]
    public function theProbeReadsWithTheShippedCode(): void
    {
        $run = $this->getRuns();
        $this->assertSame([], $run['schema'], 'The shipped schema did not install');
        $this->assertSame(['query' => true, 'factory' => true, 'lines' => 1], $run['reader']);
    }

    # A type joins its row with the effective settings, the checked fields, the upload rule as named pieces and the stored rating rule, once per instance
    #[Test]
    public function aTypeIsAssembledFromItsRowAndTheConfiguration(): void
    {
        $run = $this->getRuns()['types'];
        $news = $run['news'];
        $this->assertSame(['id', 'name', 'title', 'intro', 'ext', 'active', 'sort', 'version', 'created', 'updated', 'settings', 'fields', 'uploads', 'rating'], array_keys($news));
        $head = array_slice($news, 0, 8);
        $this->assertSame(['id' => 1, 'name' => 'news', 'title' => 'News', 'intro' => '', 'ext' => '', 'active' => true, 'sort' => 10, 'version' => 1], $head);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $news['created']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $news['updated']);
        $set = $news['settings'];
        $this->assertSame(['list', 'view', 'form', 'workflow', 'admin', 'features', 'assets', 'integrations', 'ext'], array_keys($set));
        $this->assertSame(['orders' => ['published', 'updated', 'title', 'views', 'rating'], 'order' => 'published', 'dir' => 'desc', 'limit' => 10, 'alpha' => false,
            'show' => ['category', 'author', 'date', 'views']], $set['list']);
        $flow = ['access' => 'user', 'groups' => [], 'publish' => [], 'notify' => ['pending' => true, 'result' => true]];
        $this->assertSame($flow, $set['workflow'], 'The defaults were not merged');
        $this->assertSame(['search' => true, 'rss' => true, 'sitemap' => true, 'blocks' => true, 'seo' => 'news'], $set['integrations']);
        $this->assertSame(['cover', 'gallery'], array_keys($set['assets']));
        $this->assertSame(['title', 'intro', 'kinds', 'extensions', 'maxbytes', 'min', 'max', 'canlink', 'report', 'mode', 'active', 'sort'], array_keys($set['assets']['cover']));
        $this->assertSame([], $set['ext']);
        $this->assertArrayNotHasKey('version', $set);
        $this->assertSame([], $news['fields']);
        $this->assertSame(['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'], $news['rating']);
        $files = $run['files'];
        $this->assertSame(['release', 'site'], array_keys($files['fields']));
        $keys = ['extensions', 'maxquota', 'maxbytes', 'maxwidth', 'maxheight', 'maxfiles', 'thumbwidth', 'moderfiles', 'userfiles', 'userupload', 'guestupload', 'guestfiles'];
        $this->assertSame($keys, array_keys($files['uploads']));
        $this->assertIsInt($files['uploads']['maxbytes']);
        $this->assertSame(['own' => true], $run['probe']['settings']['ext'], 'The extension settings did not pass through the registered extension');
        $this->assertSame([[], []], [$run['bare']['uploads'], $run['bare']['rating']], 'A missing upload or rating rule must block only its subsystem');
        $this->assertTrue($run['same'], 'A second read built a second type object');
        $this->assertSame(0, $run['againsql'], 'A second read ran SQL');
        $this->assertTrue($run['conf'], 'The reader changed the global configuration');
    }

    # A public context reads only active valid types; the main administrator and the Node manager also read the disabled ones; the list is one statement
    #[Test]
    public function eachContextSeesTheTypesItMay(): void
    {
        $run = $this->getRuns()['types'];
        $found = array_map(fn($v) => $v['found'], $run['guest']);
        $this->assertSame(['news' => true, 'docs' => true, 'files' => true, 'off' => false, 'bad' => false, 'ghost' => false, 'probe' => true, 'plain' => true, 'bare' => true,
            'Bad-Name' => false, 'nosuch' => false], $found);
        $this->assertSame(0, $run['guest']['Bad-Name']['sql'], 'A name outside the grammar reached SQL');
        $public = ['news', 'docs', 'files', 'probe', 'plain', 'bare'];
        $admin = ['news', 'docs', 'files', 'off', 'probe', 'plain', 'bare'];
        $this->assertSame(['guest' => $public, 'moder' => $public, 'boss' => $admin, 'root' => $admin], array_map(fn($v) => $v['names'], $run['lists']));
        foreach ($run['lists'] as $who => $one) {
            $this->assertSame(1, $one['sql'], $who.': the list took more than one statement');
            $this->assertSame(0, $one['after'], $who.': a read after the list ran SQL');
            $this->assertTrue($one['same'], $who);
            $this->assertSame(in_array($who, ['boss', 'root'], true), $one['off'], $who);
        }
    }

    # A version that differs makes the shared configuration be read once more; the fresh one resolves the type with the row already read,
    # a difference that lasts reads the row exactly once more and fails the type, and the global stays untouched
    #[Test]
    public function aChangedVersionIsReadOnceMoreAndALastingOneFails(): void
    {
        $run = $this->getRuns()['stale'];
        $this->assertSame(2, $run['stale'], 'The type was not rebuilt from the fresh configuration');
        $this->assertSame(1, $run['stalesql'], 'The fresh configuration did not resolve the row already read');
        $this->assertFalse($run['drift']);
        $this->assertSame(2, $run['driftsql']);
        $this->assertTrue($run['conf']);
        $this->assertSame(1, $run['global'], 'The re-read replaced the global configuration');
    }

    # The broken variants of a stored type, each with the path of its first error
    public static function getBrokenSettings(): array
    {
        return [
            'unknown section' => ['section', 'colour'], 'page size 0' => ['listlimit', 'list.limit'], 'page size over maxlist' => ['listmax', 'list.limit'],
            'default sort not allowed' => ['order', 'list.order'], 'repeated sort' => ['orders', 'list.orders'], 'sort outside the registry' => ['sortkey', 'list.orders'],
            'direction' => ['dir', 'list.dir'], 'letter switch as string' => ['alpha', 'list.alpha'], 'unknown metadata' => ['show', 'list.show'],
            'unknown list key' => ['listkey', 'list'], 'view path' => ['view', 'view'], 'form key' => ['form', 'form'], 'missing feature' => ['features', 'features'],
            'feature as string' => ['feature', 'features.comments'], 'unknown integration' => ['integr', 'integrations'], 'page kind' => ['seo', 'integrations.seo'],
            'submit access' => ['access', 'workflow.access'], 'group access without groups' => ['groups', 'workflow.groups'],
            'publish outside groups' => ['publish', 'workflow.publish'], 'notice as string' => ['notify', 'workflow.notify'],
            'display mode' => ['assetmode', 'assets.cover.mode'], 'kinds outside the mode' => ['assetkinds', 'assets.cover.kinds'],
            'role max over maxassets' => ['assetmax', 'assets.cover.max'], 'role min over max' => ['assetmin', 'assets.cover.max'],
            'unknown role key' => ['assetkey', 'assets.cover'], 'role switch as string' => ['assetstr', 'assets.cover.active'],
            'role title markup' => ['assettitle', 'assets.cover.title'], 'role name case' => ['rolename', 'assets.Cover'], 'link of two' => ['link', 'assets.link.mode'],
            'second link role' => ['twolinks', 'assets.site.mode'], 'link with local limits' => ['linklocal', 'assets.link.mode'],
            'settings of a standard type' => ['extstd', 'ext'],
            'field named after a column' => ['reserved', 'fields.version'], 'no Node configuration' => ['nonode', 'node'],
        ];
    }

    # The one validator of the settings refuses every broken part with its path and a shared NodeException
    #[Test]
    #[DataProvider('getBrokenSettings')]
    public function theSettingsValidatorRefusesEachBrokenPart(string $name, string $path): void
    {
        $this->assertRefused($this->getRuns()['settings']['bad'][$name], $path, $name);
    }

    # The stored sections of every probe type pass, the external link role passes, the extension checks its own settings and an unknown key is refused by the factory
    #[Test]
    public function theSettingsValidatorAcceptsTheProfiles(): void
    {
        $run = $this->getRuns()['settings'];
        foreach ($run['good'] as $name => $one) $this->assertTrue($one['ok'], $name.' was refused: '.($one['msg'] ?? ''));
        $this->assertSame(['ext' => []], array_intersect_key($run['good']['docs']['value'], ['ext' => 1]));
        $this->assertSame(['own' => true], $run['good']['probe']['value']['ext']);
        $this->assertTrue($run['link']['ok']);
        $this->assertSame(['ok' => false, 'code' => 3, 'class' => 'NodeException', 'msg' => 'probe config'], $run['bad']['extconf']);
        $this->assertSame(['ok' => false, 'code' => 3, 'class' => 'NodeException', 'msg' => 'Unknown node extension key'], $run['bad']['extunknown']);
    }

    # The read right of the main category: guest, user, group members, moderator of the type, main administrator and the empty read string, the same in list and count
    #[Test]
    public function categoryRightsFollowTheContext(): void
    {
        $run = $this->getRuns()['rights'];
        $guest = [101, 102, 112, 113, 114, 116, 119, 120, 121];
        $every = [101, 102, 103, 104, 105, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121];
        $want = [
            'guest' => $guest,
            'clara' => [101, 102, 103, 112, 113, 114, 115, 116, 119, 120, 121],
            'anna' => [101, 102, 103, 104, 112, 113, 114, 115, 116, 119, 120, 121],
            'boris' => [101, 102, 103, 112, 113, 114, 115, 116, 118, 119, 120, 121],
            'moder' => $every,
            'boss' => $guest,
            'root' => $every,
        ];
        foreach ($want as $who => $ids) {
            $this->assertSame($ids, $run[$who]['ids'], $who);
            $this->assertSame(count($ids), $run[$who]['count'], $who.': the count differs from the list');
            $this->assertSame(1, $run[$who]['second'], $who.': the categories were read again by the same instance');
        }
        foreach (['guest', 'clara', 'anna', 'boris', 'boss'] as $who) $this->assertSame(2, $run[$who]['first'], $who.': the count did not take one prefetch and one count');
        foreach (['moder', 'root'] as $who) $this->assertSame(1, $run[$who]['first'], $who.': a moderator without a language needs no prefetch');
    }

    # A category lists its materials from the main and the extra category once each, only with the right of the category and of the main category of each material
    #[Test]
    public function theCategoryFilterReturnsEachMaterialOnce(): void
    {
        $run = $this->getRuns()['category'];
        $want = [
            'guest-1' => [116, 102], 'clara-1' => [116, 115, 102], 'guest-2' => [], 'anna-2' => [115, 103], 'guest-5' => [116, 112], 'guest-8' => [], 'moder-4' => [105],
            'guest-999' => [],
        ];
        foreach ($want as $key => $ids) {
            $this->assertSame($ids, $run[$key]['ids'], $key);
            $this->assertSame(count($ids), $run[$key]['count'], $key);
        }
        $this->assertRefused($run['plain'], 'category', 'a category of a type without categories');
        $this->assertRefused($run['zero'], 'category', 'category 0');
    }

    # The language of the categories narrows lists and their deadline, never a direct read, a target or a sitemap row
    #[Test]
    public function theLanguageNarrowsListsOnly(): void
    {
        $run = $this->getRuns()['language'];
        $this->assertSame([114, 121, 120, 119, 116, 113, 102, 101], $run['ru']['ids']);
        $this->assertSame([114, 121, 120, 119, 116, 112, 102, 101], $run['en']['ids']);
        $this->assertSame(8, $run['ru']['count']);
        $this->assertSame([], $run['rucat']['ids'], 'A category of another language was listed');
        $this->assertSame([116, 112], $run['encat']['ids']);
        foreach (['ru', 'en'] as $lang) {
            $this->assertSame([true, true], $run[$lang.'item'], $lang);
            $this->assertSame([112, 113], $run[$lang.'target'], $lang);
            $this->assertContains(112, $run[$lang.'site'], $lang);
            $this->assertContains(113, $run[$lang.'site'], $lang);
            $this->assertNotNull($run[$lang.'deadline'], $lang);
        }
        $this->assertNotContains(112, $run['moder']['ids'], 'The language did not narrow the list of a moderator');
        $this->assertContains(105, $run['moder']['ids'], 'The moderator lost the right of the categories');
    }

    # A missing, foreign and closed material are the same null; a moderator reads every state and date and a disabled type, the manager does not
    #[Test]
    public function aClosedMaterialIsTheSameNullAsAMissingOne(): void
    {
        $run = $this->getRuns()['items'];
        $rights = $this->getRuns()['rights'];
        foreach (['guest', 'clara'] as $who) {
            $this->assertSame($rights[$who]['ids'], $run['found'][$who], $who.': the single read and the list disagree');
            $this->assertSame($run['found'][$who], $run['content'][$who], $who);
        }
        foreach (['moder', 'root'] as $who) {
            $this->assertSame(range(101, 121), $run['found'][$who], $who);
            $this->assertSame(range(101, 121), $run['content'][$who], $who);
        }
        $this->assertSame([false, false, false], $run['foreign'], 'A material was read through another type or by a missing id');
        $this->assertSame([false, 0], $run['zero'], 'Id 0 reached SQL');
        $this->assertSame([false, true, false], $run['off'], 'Guest, main administrator and manager on a disabled type');
    }

    # The full read carries the body, the fields and typed sets; the short read carries the body alone; the address is visible to a moderator only
    #[Test]
    public function theFullReadCarriesTypedSetsAndTheShortReadNone(): void
    {
        $run = $this->getRuns()['items'];
        $node = $run['guest101'];
        $keys = ['id', 'tid', 'cid', 'uid', 'aname', 'ip', 'title', 'intro', 'body', 'fields', 'poll', 'home', 'comon', 'pinned', 'comnum', 'views', 'score', 'ratings',
            'status', 'version', 'created', 'updated', 'pubdate', 'expires', 'cids', 'rels', 'assets', 'uname', 'ctitle'];
        $this->assertSame($keys, array_keys($node));
        $pick = [$node['status'], $node['comon'], $node['body'], $node['fields'], $node['cids'], $node['pubdate'], $node['ip']];
        $this->assertSame(['Published', 'Open', 'body of 101', [], [], '2026-01-01 10:00:00', null], $pick);
        $this->assertSame(['NodeRelation', 6, 101, 102, 'related', 5], array_slice(array_values($node['rels'][0]), 0, 6));
        $keys = ['class', 'id', 'nid', 'kind', 'role', 'src', 'name', 'title', 'intro', 'mime', 'size', 'width', 'height', 'duration', 'hits', 'reported', 'ruid', 'sort',
            'created', 'updated'];
        $this->assertSame($keys, array_keys($node['assets'][0]));
        $this->assertSame('NodeAsset', $node['assets'][0]['class']);
        $this->assertSame(['anna', null], [$node['uname'], $node['ctitle']]);
        $this->assertSame('192.0.2.1', $run['moder101']['ip']);
        $this->assertSame(['Guest writer', 0, null, null], [$run['guest121']['aname'], $run['guest121']['uid'], $run['guest121']['uname'], $run['guest121']['ip']]);
        $this->assertSame(['boris', 'Open', 'Disabled'], [$run['guest102']['uname'], $run['guest102']['ctitle'], $run['guest102']['comon']]);
        $short = $run['content101'];
        $this->assertSame('body of 101', $short['body']);
        foreach (['fields', 'cids', 'rels', 'assets'] as $key) $this->assertNull($short[$key], $key.' was loaded by the short read');
        $files = $run['files301'];
        $this->assertSame(['release' => '1.2', 'site' => 'https://example.com'], $files['fields']);
        $this->assertSame([1, 2, 3, 4], array_column($files['assets'], 'id'), 'The full read did not load every resource row of the material');
        $this->assertSame([101], array_column($files['rels'], 'rid'));
        $this->assertSame([], $run['files303'], 'Stored values that are no JSON object were not read as empty');
        $this->assertSame(5, $run['fullsql'], 'A full material costs the type, the row, categories, relations and resources');
        $this->assertSame(2, $run['contentsql'], 'The short read costs the type and the row');
    }

    # A resource needs its material readable through the route type and an active role of that type, in one statement after the type
    #[Test]
    public function aResourceNeedsItsMaterialTypeAndActiveRole(): void
    {
        $run = $this->getRuns()['assets'];
        $this->assertSame(['id' => 1, 'role' => 'cover', 'mime' => 'image/png', 'size' => 1000, 'width' => 10, 'height' => 20, 'duration' => null, 'reported' => null],
            array_intersect_key($run['guest-1-files'], array_flip(['id', 'role', 'mime', 'size', 'width', 'height', 'duration', 'reported'])));
        $this->assertSame(['NodeAsset', 7], [$run['guest-2-files']['class'], $run['guest-2-files']['hits']]);
        $this->assertSame([null, null], [$run['guest-3-files']['mime'], $run['guest-3-files']['size']]);
        foreach (['guest-4-files', 'root-4-files', 'guest-5-files', 'guest-6-files', 'guest-99-files', 'guest-0-files'] as $key) $this->assertNull($run[$key], $key);
        $this->assertSame(5, $run['root-5-files']['id'], 'A moderator did not read the resource of a draft');
        $this->assertSame(6, $run['guest-6-news']['id']);
        $this->assertSame(2, $run['sql']);
    }

    # Pinned first where the type pins, every allowed key with its ties, the default sort of the type, refused keys and sizes, and pages past the end
    #[Test]
    public function listsSortAndPage(): void
    {
        $run = $this->getRuns()['order'];
        $ids = fn(string $key): array => array_column($run[$key], 'id');
        $this->assertSame([114, 121, 120, 119, 118, 117, 116, 115, 113, 112, 105, 104, 103, 102, 101], $ids('news'));
        $this->assertSame([114, 101, 102, 103, 104, 105, 112, 113, 115, 116, 117, 118, 119, 120, 121], $ids('news-published-asc'));
        $this->assertSame([114, 120, 118, 121, 104, 112, 119, 116, 115, 117, 103, 101, 102, 113, 105], $ids('news-title-asc'));
        $this->assertSame([114, 102, 101, 120, 121, 119, 118, 117, 116, 115, 113, 112, 105, 104, 103], $ids('news-views-desc'));
        $this->assertSame([114, 102, 101, 121, 120, 119, 118, 117, 116, 115, 113, 112, 105, 104, 103], $ids('news-rating-desc'));
        $this->assertSame([114, 101, 102, 121, 120, 119, 118, 117, 116, 115, 113, 112, 105, 104, 103], $ids('news-rating-asc'));
        $this->assertSame([208, 207, 201, 202, 206, 203, 205], $ids('docs'), 'The default sort of the type is not title ascending');
        $this->assertRefused($run['docsrating'], 'order', 'a sort the type does not allow');
        $this->assertRefused($run['newkey'], 'order', 'an alias outside the registry');
        $this->assertRefused($run['updir'], 'order', 'a direction outside asc and desc');
        $this->assertSame(range(1020, 1011), $run['plain2']);
        $this->assertSame(range(1030, 1021), $run['plaindefault'], 'The default page size is not the page size of the type');
        $this->assertSame([], $run['plain4']);
        $this->assertSame(30, $run['plaincount'], 'The count follows the paging');
        $this->assertRefused($run['plainsize'], 'limit', 'a page over the size of the type');
        $this->assertRefused($run['page0'], 'page', 'page 0');
        $this->assertRefused($run['size0'], 'page', 'size 0');
        $this->assertRefused($run['notype'], 'type', 'a list without a type');
    }

    # Every filter narrows the list and the count alike and refuses what the type or the grammar does not allow
    #[Test]
    public function filtersNarrowListAndCountAlike(): void
    {
        $run = $this->getRuns()['filters'];
        $want = [
            'letter-a' => [201, 202, 206], 'letter-A' => [201, 202, 206], 'letter-9' => [207],
            'search-100%' => [120], 'search-_sale' => [120], 'search-%' => [120], 'search-Open' => [102, 101], 'search-OPEN' => [102, 101], 'search-50%_off' => [120],
            'search-nothing here' => [],
            'author-3' => [114, 116, 102],
            'status-guest-Draft' => [], 'status-guest-Pending' => [], 'status-guest-Disabled' => [], 'status-guest-Deleted' => [],
            'status-guest-Published' => [114, 121, 120, 119, 116, 113, 112, 102, 101],
            'status-moder-Draft' => [108], 'status-moder-Pending' => [109], 'status-moder-Disabled' => [110], 'status-moder-Deleted' => [111],
            'status-moder-Published' => [114, 106, 121, 120, 119, 118, 117, 116, 115, 113, 112, 107, 105, 104, 103, 102, 101],
            'range-0' => [103, 102], 'range-1' => [121, 120, 119], 'range-2' => [101],
            'home' => [114], 'homeoff' => [114, 121, 120, 119, 116, 113, 112, 102, 101],
        ];
        foreach ($want as $key => $ids) {
            $this->assertTrue($run[$key]['ok'], $key.': '.($run[$key]['msg'] ?? ''));
            $this->assertSame($ids, $run[$key]['value']['ids'], $key);
            $this->assertSame(count($ids), $run[$key]['value']['count'], $key.': the count differs from the list');
        }
        foreach (['letter-news' => 'letter', 'letterbad-%' => 'letter', 'letterbad-ab' => 'letter', 'letterbad- ' => 'letter', 'searchlong' => 'search', 'author0' => 'author',
            'home-docs' => 'home'] as $key => $path) {
            $this->assertRefused($run[$key], $path, $key);
        }
        for ($i = 0; $i < 6; $i++) $this->assertRefused($run['rangebad-'.$i], 'published', 'range '.$i);
        $this->assertTrue($run['searchmax']['value'], 'A search of 255 characters was refused');
    }

    # A mixed selection is one union of type branches, each read through its own factory extension; an assigned extension must match the key of a single type
    #[Test]
    public function mixedSelectionsAndExtensions(): void
    {
        $run = $this->getRuns()['mixed'];
        $this->assertSame([114, 303, 302, 301, 121, 120, 119, 116, 113, 112, 102, 101], $run['mixed']['ids']);
        $this->assertSame(12, $run['mixed']['count']);
        $this->assertSame([114, 303, 302, 301, 121, 120, 119, 116, 113, 112], array_column($run['mixedrows'], 0));
        $this->assertSame([114, 120, 121, 303, 112, 119, 116, 101, 102, 113], $run['mixedtitle']);
        $this->assertRefused($run['mixeddocs'], 'order', 'a sort one type of the mix does not allow');
        foreach (['empty', 'twice', 'string', 'offguest'] as $key) $this->assertRefused($run[$key], 'types', $key);
        $this->assertRefused($run['offtype'], 'type', 'a disabled type for a guest');
        $this->assertSame([401], $run['offroot']['value']);
        $this->assertSame(['ids' => [703, 701], 'count' => 2], $run['probe-anna']['value']);
        $this->assertSame(['ids' => [702], 'count' => 1], $run['probe-boris']['value']);
        $this->assertSame(['ids' => [], 'count' => 0], $run['probe-guest']['value']);
        foreach (['probenone', 'probeother', 'newsext', 'probemixext', 'probeitemnone'] as $key) $this->assertRefused($run[$key], 'extension', $key);
        $this->assertSame([114, 703, 701, 121, 120, 119, 116, 115, 113, 112, 104, 103, 102, 101], $run['probemix']['value']['ids']);
        $this->assertSame(14, $run['probemix']['value']['count']);
        $this->assertSame([true, false], $run['probeitem'], 'The scope of the extension did not narrow the single read');
        $this->assertSame([701, 101], $run['probetarget']);
    }

    # Targets keep the input order, leave out whatever the context may not read, share one type object and cost at most two statements
    #[Test]
    public function targetsKeepOrderAndCostTwoStatements(): void
    {
        $run = $this->getRuns()['targets'];
        $want = ['guest' => [120, 301, 101, 202], 'clara' => [120, 301, 103, 101, 202], 'moder' => [120, 301, 103, 101, 202, 105], 'root' => [120, 301, 103, 101, 202, 105]];
        foreach ($want as $who => $ids) {
            $this->assertSame($ids, $run[$who]['ids'], $who);
            $this->assertSame(2, $run[$who]['sql'], $who);
            $this->assertSame(1, $run[$who]['again'], $who.': known types were read again');
        }
        $this->assertSame(['class' => 'NodeTarget', 'type' => true, 'id' => 101, 'uid' => 2, 'title' => 'Open air', 'comon' => 'Open', 'comnum' => 0, 'score' => 8, 'ratings' => 2,
            'props' => ['type', 'id', 'uid', 'title', 'comon', 'comnum', 'score', 'ratings']], $run['one']);
        $this->assertTrue($run['shared']);
        $this->assertFalse($run['wrongtype']);
        $this->assertSame([[], 0], $run['empty']);
        $this->assertSame(['count' => 30, 'sql' => 2], $run['big']);
        foreach (['zero', 'negative', 'key', 'upper', 'value', 'many', 'single'] as $key) $this->assertRefused($run['bad-'.$key], 'refs', $key);
    }

    # The tree gives id, title, parent and sort after a cursor; a parent the context may not read is null, and the batch is one statement
    #[Test]
    public function theTreeHidesAnUnreadableParent(): void
    {
        $run = $this->getRuns()['tree'];
        $rows = [[201, null, 0], [202, 201, 1], [203, 202, 2], [205, null, 3], [206, null, 0], [207, null, 0], [208, null, 0]];
        $this->assertSame($rows, array_map(fn($v) => [$v['id'], $v['parent'], $v['sort']], $run['all']));
        $this->assertSame(['id', 'title', 'parent', 'sort'], array_keys($run['all'][0]));
        $this->assertSame([203, 205], array_column($run['batch'], 'id'));
        $this->assertSame($run['all'], $run['root'], 'A moderator list shows a parent the published tree does not have');
        foreach (['limit', 'after', 'notree', 'notype'] as $key) $this->assertRefused($run[$key], 'tree', $key);
        $this->assertSame(2, $run['sql'], 'A tree batch costs the category prefetch and one statement');
    }

    # The sitemap gives the closed row of every public type with the integration, and a cursor walk returns the same ids as one batch
    #[Test]
    public function theSitemapWalksByCursor(): void
    {
        $run = $this->getRuns()['sitemap'];
        $ids = [101, 102, 112, 113, 114, 116, 119, 120, 121, 201, 202, 203, 205, 206, 207, 208, 301, 302, 303];
        $this->assertSame($ids, array_column($run['all'], 'id'));
        $this->assertSame($ids, $run['walk']);
        $this->assertSame(['id' => 102, 'name' => 'news', 'title' => 'Open cat', 'cid' => 1, 'ctitle' => 'Open', 'published' => '2026-01-02 10:00:00'],
            array_diff_key($run['all'][1], ['updated' => 1]));
        $this->assertSame(['id', 'name', 'title', 'cid', 'ctitle', 'published', 'updated'], array_keys($run['all'][0]));
        $this->assertRefused($run['limit'], 'sitemap', 'limit 501');
        $this->assertRefused($run['after'], 'sitemap', 'cursor -1');
    }

    # The deadline is the nearest future publication or expiry the list would show, one aggregate on top of the prefetch, null when nothing is timed
    #[Test]
    public function theDeadlineIsTheNextTimedChange(): void
    {
        $run = $this->getRuns()['deadline'];
        $this->assertSame($run['want'], $run['guest']);
        $this->assertSame($run['want'], $run['root']);
        $this->assertSame($run['want'], $run['mixed']);
        $this->assertSame(2, $run['guestsql']);
        $this->assertSame(1, $run['guestagain']);
        $this->assertSame(1, $run['rootsql']);
        $this->assertNull($run['plain']);
        $this->assertSame(1, $run['plainsql']);
        $this->assertNull($run['category'], 'A time of another category leaked into the deadline');
    }

    # The budgets of 11-security-performance.md from a fresh reader including the type: three without categories, the batches of the type on top, never more than seven
    # Building the HTML cache of a list adds the one deadline statement and reuses the category prefetch: four for a plain type, never more than eight
    # The administrative list switches the sets off and costs type, count and page alone, and its models hold null for every set it did not load
    #[Test]
    public function statementBudgetsHold(): void
    {
        $run = $this->getRuns()['budget'];
        $want = ['plain-3' => 3, 'plain-10' => 3, 'news-3' => 7, 'news-10' => 7, 'docs-3' => 6, 'docs-10' => 6, 'files-3' => 7, 'files-10' => 7,
            'build-plain' => 4, 'build-news' => 8, 'build-docs' => 7, 'build-files' => 8, 'targets' => 2, 'admin-news' => 3, 'admin-files' => 3,
            'bare' => [0, null, null, null]];
        $this->assertSame($want, $run);
    }

    # The request context comes from the trusted state alone: groups by account and points, administrators by their stored rights, the background flag never from the request
    #[Test]
    public function theRequestContextIsBuiltFromTrustedStateOnly(): void
    {
        $run = $this->getRuns()['context'];
        $want = [
            'guest' => [0, [], 0, [], false, false],
            'anna' => [2, [1], 0, [], false, false],
            'boris' => [3, [2], 0, [], false, false],
            'dmitri' => [5, [1, 2], 0, [], false, false],
            'moder' => [0, [], 2, ['docs', 'news'], false, false],
            'boss' => [0, [], 3, [], true, false],
            'root' => [0, [], 1, [], true, true],
            'pair' => [2, [1], 2, ['docs', 'news'], false, false],
            'mono' => [0, [], 0, [], false, false],
        ];
        foreach ($want as $who => $ctx) {
            $one = $run[$who];
            $this->assertSame('', $one['error'], $who);
            $this->assertSame($ctx, [$one['uid'], $one['groups'], $one['aid'], $one['mods'], $one['manage'], $one['super']], $who);
            $this->assertFalse($one['task'], $who.': the background flag came from the request');
            $this->assertSame('127.0.0.1', $one['ip'], $who);
            $this->assertTrue($one['same'], $who.': the context was built twice');
            $this->assertSame(0, $one['again'], $who.': the second call ran SQL');
            $this->assertSame($who === 'mono' ? '' : $one['locale'], $one['lang'], $who);
        }
        $this->assertNotSame('', $run['guest']['lang']);
    }

    # A broken stored type, a type without configuration, a lasting version difference and unreadable field values are reported with the name or id behind them
    #[Test]
    public function storedProblemsAreLogged(): void
    {
        $seen = [];
        foreach ($this->getRuns()['log'] as $line) {
            $one = json_decode($line, true);
            $seen[] = trim($one['msg'].' '.($one['name'] ?? $one['nid'] ?? '').' '.($one['path'] ?? ''));
        }
        $seen = array_values(array_unique($seen));
        sort($seen);
        $this->assertSame([
            'Node: a type has no configuration ghost',
            'Node: the field values of a material are no JSON object 303',
            'Node: the rating rule of a type is missing or invalid, its rating is blocked bare',
            'Node: the stored configuration of a type is invalid bad Invalid node input: list.limit',
            'Node: the upload rule of a type is missing or invalid, its uploads are blocked bare',
            'Node: the version of a type differs between the database and the configuration drift',
        ], $seen);
    }
}
