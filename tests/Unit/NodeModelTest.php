<?php

declare(strict_types=1);

namespace Tests\Unit;

use Database;
use Error;
use NodeContext;
use NodeException;
use NodeExtension;
use NodeSupport;
use NodeSync;
use NodeStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionEnum;
use ReflectionFunctionAbstract;
use RuntimeException;

require_once dirname(__DIR__, 2).'/core/classes/pdo.php';
require_once dirname(__DIR__, 2).'/core/classes/comment.php';
require_once dirname(__DIR__, 2).'/core/classes/node/load.php';
require_once dirname(__DIR__, 2).'/core/classes/node/ext/load.php';

/**
 * Stage S08 of docs/node: the Node schema, the read-only models and the loading without Composer. The models keep the exact
 * constructors of 05-core-api.md and nothing else, the state enum owns the move matrix of 03-database.md, the context refuses
 * a snapshot that contradicts itself, and the closed class and extension maps load only files that exist. The schema half runs
 * in tests/Support/node_probe.php: a fresh install and an update of the shipped SQL files on disposable MariaDB databases.
 */
final class NodeModelTest extends TestCase
{
    private static array $probe = [];

    # The directory of the Node classes
    private static function getDir(): string
    {
        return dirname(__DIR__, 2).'/core/classes/node';
    }

    # One shipped SQL file
    private static function getSql(string $name): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/setup/sql/'.$name);
    }

    # A function or method as its parameters, each written as type, name and default
    private static function getShape(ReflectionFunctionAbstract $fn): array
    {
        $out = [];
        foreach ($fn->getParameters() as $one) {
            $out[] = $one->getType().' '.$one->getName().($one->isDefaultValueAvailable() ? ' = '.var_export($one->getDefaultValue(), true) : '');
        }
        return $out;
    }

    # One valid public context with the overrides replacing whole arguments
    private static function getContext(array $over = []): NodeContext
    {
        $arg = $over + ['uid' => 0, 'groups' => [], 'aid' => 0, 'mods' => [], 'manage' => false, 'super' => false, 'ip' => '127.0.0.1', 'lang' => 'en', 'task' => false];
        return new NodeContext(...$arg);
    }

    # Run the probe once and memoize its report; a probe that cannot create its databases is a failure, not a skip
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/node_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_probe';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The probe failed');
        $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
        return self::$probe = $data;
    }

    # The exact constructors of the data classes of 05-core-api.md
    public static function getModels(): array
    {
        return [
            'Node' => ['Node', ['int id', 'int tid', 'int cid', 'int uid', 'string aname', '?string ip', 'string title', 'string intro', '?string body',
                '?array fields', 'int poll', 'bool home', 'CommentMode comon', 'bool pinned', 'int comnum', 'int views', 'int score', 'int ratings',
                'NodeStatus status', 'int version', 'string created', 'string updated', '?string pubdate', '?string expires', '?array cids', '?array rels',
                '?array assets', '?string uname', '?string ctitle']],
            'NodeType' => ['NodeType', ['int id', 'string name', 'string title', 'string intro', 'string ext', 'bool active', 'int sort', 'int version',
                'string created', 'string updated', 'array settings', 'array fields', 'array uploads', 'array rating']],
            'NodeTypeInput' => ['NodeTypeInput', ['string title', 'string intro', 'string ext', 'int sort', 'array settings', 'array fields', 'array uploads', 'array rating']],
            'NodeInput' => ['NodeInput', ['int cid', 'array cids', 'string aname', 'string title', 'string intro', 'string body', 'array fields', 'int poll',
                'bool home', 'CommentMode comon', 'bool pinned', '?string pubdate', '?string expires', 'array rels', 'array assets', 'array ext']],
            'NodeRelation' => ['NodeRelation', ['int id', 'int nid', 'int rid', 'string type', 'int sort', 'string created']],
            'NodeAsset' => ['NodeAsset', ['int id', 'int nid', 'string kind', 'string role', 'string src', 'string name', 'string title', 'string intro',
                '?string mime', '?int size', '?int width', '?int height', '?int duration', 'int hits', '?string reported', 'int ruid', 'int sort',
                'string created', 'string updated']],
            'NodeTarget' => ['NodeTarget', ['NodeType type', 'int id', 'int uid', 'string title', 'CommentMode comon', 'int comnum', 'int score', 'int ratings']],
            'NodeContext' => ['NodeContext', ['int uid', 'array groups', 'int aid', 'array mods', 'bool manage', 'bool super', 'string ip', 'string lang', 'bool task = false',
                'bool polls = false']],
        ];
    }

    # Each model is a final readonly class whose only member is the promoted constructor of the contract: no getter, setter, factory or extra state
    #[Test]
    #[DataProvider('getModels')]
    public function eachModelHasTheContractConstructorAndNothingElse(string $name, array $want): void
    {
        $ref = new ReflectionClass($name);
        $this->assertTrue($ref->isFinal(), $name.' is not final');
        $this->assertTrue($ref->isReadOnly(), $name.' is not readonly');
        $this->assertFalse($ref->getParentClass(), $name.' inherits');
        $this->assertSame([], $ref->getInterfaceNames(), $name.' implements an interface');
        $this->assertSame($want, self::getShape($ref->getConstructor()), $name.' has another constructor');
        $this->assertSame(['__construct'], array_map(fn($m) => $m->getName(), $ref->getMethods()), $name.' has methods besides the constructor');
        $props = [];
        foreach ($ref->getProperties() as $one) {
            $this->assertTrue($one->isPublic() && $one->isReadOnly() && $one->isPromoted(), $name.'::$'.$one->getName().' is not a promoted public readonly property');
            $props[] = $one->getName();
        }
        $this->assertSame(array_map(fn($p) => explode(' ', $p)[1], $want), $props, $name.' holds other properties');
    }

    # A built model cannot be changed afterwards, not even a property of the same type
    #[Test]
    public function aBuiltModelCannotBeChanged(): void
    {
        $rel = new \NodeRelation(1, 2, 3, 'related', 0, '2026-09-23 10:00:00');
        $this->expectException(Error::class);
        $rel->sort = 5;
    }

    # The materials are read with the existing comment mode and the stored numbers turn into state cases; the loaded sets stay what they were built with
    #[Test]
    public function aMaterialCarriesTheStateAndCommentModeAsEnums(): void
    {
        $node = new \Node(7, 1, 0, 0, 'guest', null, 'T', '', null, null, 0, false, \CommentMode::from(2), false, 0, 0, 0, 0, NodeStatus::from(2), 1,
            '2026-09-23 10:00:00', '2026-09-23 10:00:00', null, null, null, [], [], null, null);
        $this->assertSame(\CommentMode::Open, $node->comon);
        $this->assertSame(NodeStatus::Published, $node->status);
        $this->assertNull($node->body, 'An unloaded body is not null');
        $this->assertSame([], $node->rels, 'A loaded empty set is not an empty array');
        $this->assertNull($node->ip, 'A hidden address is not null');
    }

    # The five states carry the stored numbers of 03-database.md, and the enum has no public member besides the move check
    #[Test]
    public function theStatesCarryTheStoredNumbers(): void
    {
        $ref = new ReflectionEnum(NodeStatus::class);
        $this->assertSame('int', (string)$ref->getBackingType());
        $have = array_column(array_map(fn($c) => [$c->name, $c->value], NodeStatus::cases()), 1, 0);
        $this->assertSame(['Draft' => 0, 'Pending' => 1, 'Published' => 2, 'Disabled' => 3, 'Deleted' => 4], $have);
        $own = array_values(array_filter(array_map(fn($m) => $m->getName(), $ref->getMethods()), fn($m) => !in_array($m, ['cases', 'from', 'tryFrom'], true)));
        $this->assertSame(['checkStatusMove'], $own);
        $this->assertSame(['self to'], self::getShape($ref->getMethod('checkStatusMove')));
        $this->assertSame('bool', (string)$ref->getMethod('checkStatusMove')->getReturnType());
    }

    # The full five by five matrix: exactly the moves of 03-database.md are allowed, the current state is no move, and the trash leads only to disabled
    #[Test]
    public function theMoveMatrixIsExactlyTheContract(): void
    {
        $want = [
            'Draft' => ['Pending', 'Published', 'Deleted'],
            'Pending' => ['Draft', 'Published', 'Deleted'],
            'Published' => ['Disabled', 'Deleted'],
            'Disabled' => ['Pending', 'Published', 'Deleted'],
            'Deleted' => ['Disabled'],
        ];
        foreach (NodeStatus::cases() as $from) {
            foreach (NodeStatus::cases() as $to) {
                $this->assertSame(in_array($to->name, $want[$from->name], true), $from->checkStatusMove($to), $from->name.' -> '.$to->name);
            }
        }
    }

    # The only error of the API: a final runtime exception with five stable codes read through getCode, keeping the storage cause as previous
    #[Test]
    public function theErrorHasFiveStableCodes(): void
    {
        $ref = new ReflectionClass(NodeException::class);
        $this->assertTrue($ref->isFinal());
        $this->assertSame(RuntimeException::class, $ref->getParentClass()->getName());
        $this->assertSame(['NOTFOUND' => 1, 'DENIED' => 2, 'INVALID' => 3, 'CONFLICT' => 4, 'STORAGE' => 5], $ref->getConstants());
        $own = array_values(array_filter($ref->getMethods(), fn($m) => $m->getDeclaringClass()->getName() === NodeException::class));
        $this->assertSame([], $own, 'NodeException declares methods');
        $cause = new \PDOException('lost');
        $err = new NodeException('store', NodeException::STORAGE, $cause);
        $this->assertSame(NodeException::STORAGE, $err->getCode());
        $this->assertSame($cause, $err->getPrevious());
    }

    # The extension contract is one interface with exactly the nine methods and signatures of 05-core-api.md
    #[Test]
    public function theExtensionContractHasNineMethods(): void
    {
        $ref = new ReflectionClass(NodeExtension::class);
        $this->assertTrue($ref->isInterface());
        $this->assertSame([], $ref->getInterfaceNames());
        $want = [
            'filterNodeConfig' => 'array(array config, array settings, array fields)',
            'filterNodeData' => 'array(NodeType type, array data, ?Node node = NULL)',
            'getNodeScope' => 'array(NodeType type)',
            'checkNodeAction' => 'bool(NodeType type, Node|NodeTarget node, string action)',
            'updateNodeAction' => 'void(NodeType type, NodeTarget node, string action, int uid)',
            'addNodeData' => 'void(Node node, array data)',
            'updateNodeData' => 'void(Node before, Node after, ?array data)',
            'deleteNodeData' => 'void(Node node)',
            'getNodeData' => 'array(NodeType type, array nodes, string mode)',
        ];
        $have = [];
        foreach ($ref->getMethods() as $one) $have[$one->getName()] = $one->getReturnType().'('.implode(', ', self::getShape($one)).')';
        $this->assertSame($want, $have);
    }

    # A public, an administrative, a main administrator and a background snapshot are accepted as they are, and the background flag is off by default
    #[Test]
    public function aConsistentContextIsAccepted(): void
    {
        $this->assertFalse(self::getContext()->task);
        $this->assertFalse((new NodeContext(3, [1, 4], 0, [], false, false, '::1', 'ru'))->task);
        $adm = self::getContext(['uid' => 3, 'groups' => [2], 'aid' => 5, 'mods' => ['news', 'docs'], 'manage' => true]);
        $this->assertSame([3, [2], 5, ['news', 'docs'], true], [$adm->uid, $adm->groups, $adm->aid, $adm->mods, $adm->manage]);
        $this->assertTrue(self::getContext(['aid' => 1, 'super' => true])->super);
        $this->assertTrue(self::getContext(['task' => true])->task);
        $this->assertSame([false, true], [self::getContext(['aid' => 1])->polls, self::getContext(['aid' => 1, 'polls' => true])->polls]);
    }

    # Snapshots that contradict themselves: a right without an administrator, a background flag with an identity, repeated or foreign list entries
    public static function getBadContexts(): array
    {
        return [
            'manage without aid' => [['manage' => true]],
            'super without aid' => [['super' => true]],
            'mods without aid' => [['mods' => ['news']]],
            'polls without aid' => [['polls' => true]],
            'negative uid' => [['uid' => -1]],
            'negative aid' => [['aid' => -1]],
            'repeated group' => [['groups' => [2, 2]]],
            'group as string' => [['groups' => ['2']]],
            'zero group' => [['groups' => [0]]],
            'keyed groups' => [['groups' => [3 => 2]]],
            'repeated type' => [['aid' => 1, 'mods' => ['news', 'news']]],
            'prefixed type' => [['aid' => 1, 'mods' => ['node-news']]],
            'upper case type' => [['aid' => 1, 'mods' => ['News']]],
            'type of 21 chars' => [['aid' => 1, 'mods' => [str_repeat('a', 21)]]],
            'type as number' => [['aid' => 1, 'mods' => [5]]],
            'task with user' => [['task' => true, 'uid' => 3]],
            'task with groups' => [['task' => true, 'groups' => [1]]],
            'task with admin' => [['task' => true, 'aid' => 1]],
            'task with manage' => [['task' => true, 'aid' => 1, 'manage' => true]],
            'task with super' => [['task' => true, 'aid' => 1, 'super' => true]],
            'task with mods' => [['task' => true, 'aid' => 1, 'mods' => ['news']]],
            'task with polls' => [['task' => true, 'aid' => 1, 'polls' => true]],
        ];
    }

    # Every contradictory snapshot is refused as invalid input before any Node class can use it
    #[Test]
    #[DataProvider('getBadContexts')]
    public function aContradictoryContextIsRefused(array $over): void
    {
        try {
            self::getContext($over);
        } catch (NodeException $err) {
            $this->assertSame(NodeException::INVALID, $err->getCode());
            return;
        }
        $this->fail('The context was accepted');
    }

    # The class map lists every class file of the directory and only those, each mapped name is declared by its file, and no path is built from the requested name
    #[Test]
    public function theClassMapListsExactlyTheExistingFiles(): void
    {
        $code = (string)file_get_contents(self::getDir().'/load.php');
        $this->assertSame(1, preg_match('/\$map = \[(.*?)\];/s', $code, $hit));
        preg_match_all("/'([A-Za-z]+)' => '([a-z]+\\.php)'/", $hit[1], $rows, PREG_SET_ORDER);
        $map = array_column($rows, 2, 1);
        $files = array_values(array_diff(array_map('basename', glob(self::getDir().'/*.php')), ['load.php']));
        sort($files);
        $mapped = array_values($map);
        sort($mapped);
        $this->assertSame($files, $mapped, 'The map and the class files differ');
        foreach ($map as $class => $file) {
            $text = (string)file_get_contents(self::getDir().'/'.$file);
            $this->assertMatchesRegularExpression('/^(?:final (?:readonly )?class|interface|enum) '.$class.'\b/m', $text, $file.' does not declare '.$class);
        }
        $this->assertStringContainsString("require_once __DIR__.'/'.\$map[\$name]", $code);
        $this->assertSame(0, preg_match('/\$name\s*\.|\.\s*\$name\b/', $code), 'A path is built from the requested name');
        $this->assertStringNotContainsString('NodeLoader', $code);
    }

    # The extension factory keeps a closed map of existing files: an empty key is a standard type, support and sync make their classes, every key outside the map is refused
    #[Test]
    public function theExtensionFactoryIsClosed(): void
    {
        $db = (new ReflectionClass(Database::class))->newInstanceWithoutConstructor();
        $ctx = self::getContext();
        $this->assertNull(getNodeExtension('', $db, $ctx));
        $this->assertInstanceOf(NodeSupport::class, getNodeExtension('support', $db, $ctx));
        $this->assertInstanceOf(NodeSync::class, getNodeExtension('sync', $db, $ctx));
        foreach (['Sync', 'sync.php', 'NodeSync', 'Support', 'support.php', 'NodeSupport', '../ext/load', 'load', 'node', '0', ' '] as $key) {
            try {
                getNodeExtension($key, $db, $ctx);
                $this->fail('The key '.$key.' was accepted');
            } catch (NodeException $err) {
                $this->assertSame(NodeException::INVALID, $err->getCode(), $key);
            }
        }
        $code = (string)file_get_contents(self::getDir().'/ext/load.php');
        $this->assertSame(1, preg_match('/\$map = \[(.*?)\];/s', $code, $hit));
        preg_match_all("/'([a-z]+)' => \\['([a-z]+\\.php)', '([A-Za-z]+)'\\]/", $hit[1], $rows, PREG_SET_ORDER);
        $files = array_values(array_diff(array_map('basename', glob(self::getDir().'/ext/*.php')), ['load.php']));
        $this->assertSame($files, array_column($rows, 2), 'An extension file exists outside the map, or the map names a missing file');
        $this->assertStringNotContainsString('$key.', $code, 'A path or class is built from the stored key');
    }

    # A request has only the map after the bootstrap; the first use of a class loads its file alone, and future or crafted names load nothing
    #[Test]
    public function classesLoadOnlyWhenARequestUsesThem(): void
    {
        $run = $this->getProbe()['runs']['load'];
        $this->assertSame(['load.php'], $run['boot']['files'], 'The bootstrap loaded more than the map');
        $this->assertSame([], $run['boot']['declared'], 'The bootstrap declared a Node class');
        $this->assertFalse($run['boot']['ext'], 'The bootstrap loaded the extension factory');
        $this->assertSame(['context.php', 'load.php'], $run['context']);
        $this->assertTrue($run['status']);
        $this->assertSame(['context.php', 'load.php', 'status.php'], $run['enum']);
        $this->assertSame(array_fill_keys(array_keys($run['fake']), false), $run['fake'], 'A future or crafted name was resolved');
        $this->assertSame($run['enum'], $run['after'], 'A future or crafted name loaded a file');
        $this->assertSame(array_fill_keys(array_keys($run['all']), true), $run['all'], 'A mapped class does not load');
        $this->assertCount(15, $run['files']);
    }

    # The schema files create every table after the tables it references and never switch the foreign key checks off
    #[Test]
    public function tablesAreCreatedAfterTheTablesTheyReference(): void
    {
        foreach (['table.sql', 'table_update6_3.sql'] as $file) {
            $sql = self::getSql($file);
            $this->assertSame(0, preg_match('/SET\s+(?:@@|SESSION\s+|GLOBAL\s+)?FOREIGN_KEY_CHECKS/i', $sql), $file.' switches the foreign key checks');
            preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`\{prefix\}_([a-z_]+)`(.*?)\n\)\s*ENGINE/s', $sql, $rows, PREG_SET_ORDER);
            $seen = [];
            foreach ($rows as $row) {
                preg_match_all('/REFERENCES `\{prefix\}_([a-z_]+)`/', $row[2], $refs);
                foreach ($refs[1] as $ref) $this->assertTrue($ref === $row[1] || isset($seen[$ref]), $file.': '.$row[1].' references '.$ref.' before it exists');
                $seen[$row[1]] = true;
            }
        }
    }

    # The update carries the eight Node tables verbatim, widens the administrator rights by the one existing MODIFY, and no second copy of the DDL exists
    #[Test]
    public function theUpdateDeclaresTheSameNodeSchema(): void
    {
        $fresh = self::getSql('table.sql');
        $update = self::getSql('table_update6_3.sql');
        preg_match_all('/CREATE TABLE `\{prefix\}_(node[a-z_]*)` (\(.*?\n\)\s*ENGINE[^;]*;)/s', $fresh, $new, PREG_SET_ORDER);
        preg_match_all('/CREATE TABLE IF NOT EXISTS `\{prefix\}_(node[a-z_]*)` (\(.*?\n\)\s*ENGINE[^;]*;)/s', $update, $old, PREG_SET_ORDER);
        $want = ['node_types', 'nodes', 'node_assets', 'node_categories', 'node_publish', 'node_relations', 'node_support', 'node_sync'];
        $this->assertSame($want, array_column($new, 1), 'table.sql does not create the Node tables in dependency order');
        $this->assertSame(array_column($new, 2, 1), array_column($old, 2, 1), 'The update creates other Node tables than a fresh install');
        $this->assertSame(1, preg_match_all('/MODIFY `modules`\s+(.+?),?\n/', $update, $mods), 'The administrator rights are declared more than once');
        $this->assertSame('TEXT NOT NULL', $mods[1][0]);
        $this->assertMatchesRegularExpression('/\n  `modules` TEXT NOT NULL,\n/', $fresh);
        $this->assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/modules/node/sql');
    }

    # A fresh install with foreign key checks on creates every table without one failed statement, all Node tables on InnoDB with every named constraint
    #[Test]
    public function aFreshInstallCreatesTheNodeSchema(): void
    {
        $run = $this->getProbe()['runs']['fresh'];
        $this->assertSame(1, $run['checks'], 'The install did not run with foreign key checks on');
        $this->assertSame([], $run['failed']);
        $this->assertSame(array_fill_keys(['probe_node_assets', 'probe_node_categories', 'probe_node_publish', 'probe_node_relations', 'probe_node_support', 'probe_node_sync',
            'probe_node_types', 'probe_nodes'], 'InnoDB'), $run['engines']);
        $want = ['probe_chk_node_assets_kind' => 'CHECK', 'probe_chk_node_assets_role' => 'CHECK', 'probe_chk_node_assets_src' => 'CHECK',
            'probe_chk_node_relations_self' => 'CHECK', 'probe_chk_node_support_prio' => 'CHECK', 'probe_chk_node_support_state' => 'CHECK',
            'probe_chk_node_sync_refresh' => 'CHECK', 'probe_fk_nodes_type' => 'FOREIGN KEY', 'probe_fk_node_assets_node' => 'FOREIGN KEY',
            'probe_fk_node_categories_node' => 'FOREIGN KEY', 'probe_fk_node_publish_node' => 'FOREIGN KEY', 'probe_fk_node_relations_node' => 'FOREIGN KEY',
            'probe_fk_node_relations_related' => 'FOREIGN KEY', 'probe_fk_node_support_node' => 'FOREIGN KEY', 'probe_fk_node_sync_node' => 'FOREIGN KEY'];
        $this->assertSame($want, array_filter($run['constraints'], fn($k) => str_contains($k, '_node'), ARRAY_FILTER_USE_KEY));
    }

    # Every index of the installed tables is one of 03-database.md with its exact columns, and the foreign keys added none of their own
    #[Test]
    public function theIndexesAreExactlyTheContract(): void
    {
        $want = [
            'node_types' => ['active' => 'active,sort,id', 'unique PRIMARY' => 'id', 'unique name' => 'name'],
            'nodes' => ['author' => 'uid,status,published,id', 'cat' => 'tid,cid,status,pinned,published,id', 'expires' => 'tid,status,expires',
                'home' => 'tid,home,status,pinned,published,id', 'ip' => 'ip,created,id', 'poll' => 'poll', 'pub' => 'tid,status,pinned,published,id',
                'queue' => 'status,created,id', 'title' => 'tid,status,pinned,title,id', 'unique PRIMARY' => 'id', 'updated' => 'tid,status,pinned,updated,id',
                'views' => 'tid,status,pinned,views,published,id'],
            'node_assets' => ['node' => 'nid,role,sort,id', 'report' => 'reported,id', 'src' => 'src(191)', 'unique PRIMARY' => 'id'],
            'node_categories' => ['cat' => 'cid,nid', 'unique PRIMARY' => 'id', 'unique node' => 'nid,cid'],
            'node_publish' => ['queue' => 'due,nid', 'unique PRIMARY' => 'nid'],
            'node_relations' => ['source' => 'nid,type,sort,rid', 'target' => 'rid,type,sort,nid', 'unique PRIMARY' => 'id', 'unique edge' => 'nid,type,rid'],
            'node_support' => ['admin' => 'aid,state,activity,id', 'queue' => 'state,prio,activity,id', 'unique PRIMARY' => 'id', 'unique node' => 'nid'],
            'node_sync' => ['due' => 'due,id', 'unique PRIMARY' => 'id', 'unique node' => 'nid'],
        ];
        $have = array_map(fn($t) => array_map(fn($c) => implode(',', $c), $t), $this->getProbe()['runs']['fresh']['indexes']);
        $this->assertSame($want, $have);
    }

    # The constraints hold against real rows: unique names and pairs, foreign keys both ways, the checks of kind, role, source, self relation, support state and refresh
    #[Test]
    public function theConstraintsHoldAgainstRealRows(): void
    {
        $run = $this->getProbe()['runs']['fresh']['rules'];
        foreach (['type', 'nodes', 'category', 'relation', 'asset', 'support', 'sync', 'manual', 'publish', 'drop', 'empty', 'typegone'] as $key) {
            $this->assertSame('accepted', $run[$key], $key);
        }
        foreach (['twin', 'catwin', 'reltwin', 'suptwin', 'synctwin', 'pubtwin'] as $key) $this->assertSame('refused 1062', $run[$key], $key);
        foreach (['orphan', 'catorphan', 'relorphan', 'retype'] as $key) $this->assertSame('refused 1452', $run[$key], $key);
        foreach (['relself', 'kind', 'role', 'src', 'state', 'prio', 'refresh', 'longest'] as $key) {
            $this->assertMatchesRegularExpression('/^refused (4025|3819)$/', $run[$key], $key);
        }
        $this->assertSame('refused 1451', $run['typedrop'], 'A type with materials was deleted');
    }

    # The column defaults are the ones the contract names: a new type is disabled at version 1, a new material is a draft at version 1 with comments off
    #[Test]
    public function theDefaultsAreTheContract(): void
    {
        $run = $this->getProbe()['runs']['fresh']['rules'];
        $this->assertEquals(['status' => 0, 'version' => 1, 'comon' => 0, 'home' => 0, 'pinned' => 0, 'cid' => 0, 'uid' => 0, 'aname' => '', 'ip' => '', 'poll' => 0,
            'published' => null, 'expires' => null], $run['defaults']);
        $this->assertEquals(['ext' => '', 'active' => 0, 'sort' => 0, 'version' => 1], $run['typedef']);
        $this->assertEquals(['aid' => 0, 'state' => 0, 'prio' => 1, 'version' => 1], $run['supdef']);
        $this->assertEquals(['refresh' => 3600, 'due' => null, 'etag' => '', 'modified' => '', 'fails' => 0, 'error' => ''], $run['syncdef']);
    }

    # The physical delete of a material removes its categories, assets, delivery, support and sync rows and its relations from both ends, and nothing else
    #[Test]
    public function deletingAMaterialCascadesToItsOwnRowsOnly(): void
    {
        $run = $this->getProbe()['runs']['fresh']['rules'];
        $this->assertSame(['node_assets' => 2, 'node_categories' => 1, 'node_publish' => 1, 'node_support' => 1, 'node_sync' => 1, 'node_relations' => 2], $run['before']);
        $this->assertSame(array_fill_keys(array_keys($run['before']), 0), $run['after']);
        $this->assertSame(1, $run['others'], 'The relation between two other materials was removed');
    }

    # An installation without Node reaches the fresh schema through the update with foreign key checks on, and a repeated update changes nothing
    #[Test]
    public function theUpdateReachesTheFreshSchemaAndRepeats(): void
    {
        $probe = $this->getProbe()['runs'];
        $run = $probe['update'];
        $this->assertSame([], $run['base']);
        $this->assertSame(1, $run['checks']);
        $this->assertSame(0, $run['tables'], 'The installation already had Node tables before the update');
        $this->assertSame([], $run['first'], 'The update has failing statements');
        $this->assertSame([], $run['second'], 'The repeated update has failing statements');
        $this->assertSame($probe['fresh']['create'], $run['create'], 'The updated tables differ from a fresh install');
        $this->assertSame($probe['fresh']['create'], $run['again'], 'The repeated update changed the tables');
        $this->assertSame('forum,shop,node,node-news', $run['modules'], 'Widening the administrator rights changed a stored value');
        $this->assertStringContainsString('`modules` text NOT NULL', $run['create']['admins']);
    }
}
