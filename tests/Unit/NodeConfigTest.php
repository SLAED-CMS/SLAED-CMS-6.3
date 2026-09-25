<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Stage S10 of docs/node: NodeService writes types. The four operations and the import run as the Closure of the shared
 * configuration writer on the four areas node, fields, uploads and ratings, with the type row locked in the same cycle and
 * the version proven on both sides. Every behaviour is driven by tests/Support/node_probe.php in its service mode: scratch
 * sources, backup, cache and upload root, one disposable MariaDB database with the shipped schema, and child processes for
 * a writer that dies at its commit, for the restore that follows the database, and for two creates racing for one name.
 */
final class NodeConfigTest extends TestCase
{
    private static array $probe = [];

    # The file of a Node class
    private static function getFile(string $name): string
    {
        return dirname(__DIR__, 2).'/core/classes/node/'.$name;
    }

    # Run the probe once in its service mode and memoize the runs; a probe that cannot create its database is a failure, not a skip
    private function getRuns(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/node_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_config';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' service 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['runs']['schema'], 'The shipped schema did not install');
            self::$probe = $data;
        }
        return self::$probe['runs'];
    }

    # One refused call of the probe must carry the expected code and message
    private function assertRefused(array $call, int $code, string $msg, string $name = ''): void
    {
        $this->assertFalse($call['ok'], $name.' was accepted');
        $this->assertSame('NodeException', $call['class'], $name);
        $this->assertSame($code, $call['code'], $name);
        $this->assertSame($msg, $call['msg'], $name);
    }

    # One call refused as invalid input with the path of its first error
    private function assertInvalid(array $call, string $path, string $name = ''): void
    {
        $this->assertRefused($call, 3, 'Invalid node input: '.$path, $name);
    }

    # The type operations of the writer keep the approved signatures of 05-core-api.md, and the class is loaded through the closed map;
    # the whole public surface of the writer, materials included, is held by NodeServiceTest
    #[Test]
    public function theWriterHasTheTypeOperations(): void
    {
        require_once dirname(__DIR__, 2).'/core/classes/pdo.php';
        require_once dirname(__DIR__, 2).'/core/classes/comment.php';
        require_once dirname(__DIR__, 2).'/core/classes/field.php';
        require_once dirname(__DIR__, 2).'/core/classes/point.php';
        require_once dirname(__DIR__, 2).'/core/classes/node/load.php';
        $ref = new ReflectionClass('NodeService');
        $this->assertTrue($ref->isFinal());
        $want = [
            '__construct' => ['Database db', 'NodeContext context', 'Field field', '?Point point = NULL', '?NodeExtension ext = NULL', ''],
            'addNodeType' => ['string name', 'NodeTypeInput input', 'NodeType'],
            'addNodeTypeImport' => ['string json', "string name = ''", 'NodeType'],
            'deleteNodeType' => ['string name', 'int version', 'void'],
            'updateNodeType' => ['string name', 'NodeTypeInput input', 'int version', 'NodeType'],
            'updateNodeTypeStatus' => ['string name', 'bool active', 'int version', 'NodeType'],
        ];
        $have = [];
        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $one) {
            $shape = [];
            foreach ($one->getParameters() as $arg) {
                $shape[] = $arg->getType().' '.$arg->getName().($arg->isDefaultValueAvailable() ? ' = '.var_export($arg->getDefaultValue(), true) : '');
            }
            $shape[] = (string)$one->getReturnType();
            $have[$one->getName()] = $shape;
        }
        $have = array_intersect_key($have, $want);
        ksort($have);
        ksort($want);
        $this->assertSame($want, $have);
        $this->assertSame([], array_map(fn($v) => $v->getName(), $ref->getMethods(ReflectionMethod::IS_STATIC)));
        $this->assertStringContainsString("'NodeService' => 'service.php',", (string)file_get_contents(self::getFile('load.php')));
        $export = new ReflectionMethod('NodeQuery', 'getNodeTypeExport');
        $this->assertSame('string', (string)$export->getReturnType());
    }

    # The writer decides by its context alone, writes the configuration only through the shared writer and never touches the runtime snapshot directly
    #[Test]
    public function theWriterReadsNoRequestAndWritesThroughTheSharedWriter(): void
    {
        $code = (string)file_get_contents(self::getFile('service.php'));
        foreach (['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION', '$_SERVER', 'is_acess(', 'is_moder(', 'is_user(', 'isAdmin(', 'getIp(', 'local.php',
            'serialize(', 'setConfigSource(', 'static $', 'eval('] as $bad) {
            $this->assertStringNotContainsString($bad, $code, $bad);
        }
        $this->assertSame(0, preg_match('/(?<![A-Za-z])Node(?:Config|Registry|Provider|TypeService|TypeManager)/', $code), 'A configuration or registry class appears');
        preg_match_all('/^\s*global ([^;]+);/m', $code, $hit);
        $this->assertSame(['$conf'], array_values(array_unique($hit[1])), 'The writer reads another global than the configuration');
        $this->assertSame(1, substr_count($code, 'setConfigFile('), 'The configuration is written by more than one path');
        $this->assertStringContainsString('->filterNodeSettings(', $code, 'The settings are not checked by the one validator of the reader');
        $this->assertSame(0, preg_match('/function (?:filter|check)(?:List|View|Flow|Feature|Link|Asset)Rule/', $code), 'The writer has a second validator of the settings');
        foreach (['Cache::getWriteGuard()', 'Cache::addEpoch(true)', 'Cache::deleteWriteGuard(', 'FileManager::getPathLock(', 'FOR UPDATE'] as $step) {
            $this->assertStringContainsString($step, $code, $step);
        }
    }

    # A guest, a user and a moderator of a type run no type operation and no export, and nothing is read or written for them
    #[Test]
    public function onlyTheManagerAndTheMainAdministratorWriteTypes(): void
    {
        $run = $this->getRuns()['rights'];
        foreach (['guest', 'anna', 'moder'] as $who) {
            foreach (['add', 'update', 'status', 'delete', 'import', 'export'] as $op) {
                $this->assertRefused($run[$who][$op], 2, 'The context does not manage node types', $who.'.'.$op);
            }
        }
        $this->assertTrue($run['same'], 'A denied operation left a trace');
    }

    # Every name outside the grammar, reserved, taken by a module or a shared area, held by the file tree in another case, or left with data of an earlier owner is refused
    #[Test]
    public function aNewNameIsCheckedBeforeAnythingIsWritten(): void
    {
        $run = $this->getRuns()['names'];
        foreach ($run['bad'] as $name => $call) {
            $path = ['media' => 'categories', 'pages' => 'directory'][$name] ?? 'name';
            $this->assertInvalid($call, $path, (string)$name);
        }
        $this->assertCount(19, $run['bad']);
        $this->assertTrue($run['same'], 'A refused name left a row, a source or a directory');
        $this->assertSame(['index.html', 'thumb/old.txt'], $run['pages'], 'The old file of an earlier owner was touched');
        foreach (['x', 'a'.str_repeat('b', 19)] as $name) {
            $this->assertSame(['ok' => true, 'value' => 1], $run['good'][$name], $name.' of length '.strlen($name).' was refused');
            $this->assertSame(['ok' => true, 'value' => null], $run['gone'][$name]);
            $trace = $run['trace'][$name];
            foreach (['row', 'node', 'fields', 'uploads', 'rating'] as $key) $this->assertNull($trace[$key], $name.': '.$key.' is left after the delete');
            $this->assertTrue($trace['dir'] && $trace['guard'], $name.': the directory and its guard were not kept');
        }
    }

    # A new type is disabled at version 1; its sections are the stored differences, its rules the copy of all and the new rating rule, its directory carries the guard
    #[Test]
    public function aTypeIsCreatedDisabledWithEveryPart(): void
    {
        $run = $this->getRuns()['add'];
        $this->assertTrue($run['news']['ok'], $run['news']['msg'] ?? '');
        $news = $run['news']['value'];
        $this->assertSame([1, false, 'News', 10], [$news['version'], $news['active'], $news['title'], $news['sort']]);
        $trace = $run['trace'];
        $this->assertSame(['id' => $news['id'], 'title' => 'News', 'intro' => '', 'ext' => '', 'active' => 0, 'sort' => 10, 'version' => 1], $trace['row']);
        $this->assertSame(['version', 'list', 'view', 'features', 'assets', 'integrations'], array_keys($trace['node']), 'The stored section is not the differences');
        $this->assertSame(['orders' => ['published', 'updated', 'title', 'views', 'rating']], $trace['node']['list']);
        $this->assertSame(['title' => '_COVER', 'kinds' => ['image'], 'max' => 1, 'mode' => 'image', 'sort' => 10], $trace['node']['assets']['cover']);
        $this->assertTrue($run['merged'], 'The stored differences do not give the effective settings back');
        $this->assertSame($trace['all'], $trace['uploads'], 'The upload rule is not the copy of the rule all');
        $this->assertSame(['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'], $trace['rating']);
        $this->assertNull($trace['fields']);
        $this->assertTrue($trace['dir'] && $trace['guard'], 'The directory or its guard is missing');
        $this->assertTrue($run['epoch'], 'The cache generation was not raised');
        $this->assertFalse($run['state']['marker']);
        $this->assertSame(0, $run['state']['guards'], 'A cache guard was left');
        $this->assertSame(12, count($run['read']['uploads']));
        $this->assertTrue($run['public'], 'A disabled type is public');
        $this->assertTrue($run['files']['ok'] && $run['docs']['ok']);
        $this->assertSame(['release', 'site'], array_keys($run['filestrace']['fields']));
        $this->assertTrue($run['filestrace']['guard'], 'The directory of guards alone was not taken');
        $this->assertInvalid($run['again'], 'name', 'a second create');
    }

    # An update needs the expected version and complete rules; a refusal leaves no trace, and a full update writes every part at the next version
    #[Test]
    public function anUpdateChangesEveryPartAtTheNextVersion(): void
    {
        $run = $this->getRuns()['update'];
        $this->assertRefused($run['stale'], 4, 'The expected type version is stale', 'stale');
        $this->assertRefused($run['older'], 4, 'The expected type version is stale', 'older');
        $this->assertRefused($run['missing'], 1, 'The node type does not exist', 'missing');
        $paths = ['noup' => 'uploads', 'norate' => 'rating', 'badext' => 'uploads.extensions', 'badflag' => 'uploads.userupload', 'badnum' => 'uploads.maxbytes',
            'badrate' => 'rating.period', 'badkey' => 'rating'];
        foreach ($paths as $key => $path) $this->assertInvalid($run[$key], $path, $key);
        $this->assertRefused($run['unknown'], 3, 'Unknown node extension key', 'unknown');
        $this->assertTrue($run['same'], 'A refused update left a trace');
        $this->assertTrue($run['done']['ok'], $run['done']['msg'] ?? '');
        $trace = $run['trace'];
        $this->assertSame([2, 'News two', 'Plain intro', 5], [$trace['row']['version'], $trace['row']['title'], $trace['row']['intro'], $trace['row']['sort']]);
        $this->assertSame(2, $trace['node']['version']);
        $this->assertSame(20, $trace['node']['list']['limit']);
        $this->assertSame(['release', 'site'], array_keys($trace['fields']));
        $this->assertSame('3', explode('|', $trace['uploads'])[5]);
        $this->assertSame(['active' => '1', 'period' => '86400', 'detail' => '0', 'guests' => '0'], $trace['rating']);
        $this->assertRefused($run['repeat'], 4, 'The expected type version is stale', 'repeat');
    }

    # A role with stored resources cannot be removed, and a role becomes a link only over unique external addresses; free roles change as before
    #[Test]
    public function rolesDoNotStrandStoredResources(): void
    {
        $run = $this->getRuns()['assets'];
        $this->assertInvalid($run['drop'], 'assets.download', 'a removed role with resources');
        $this->assertInvalid($run['local'], 'assets.download.mode', 'a link over a local file');
        $this->assertInvalid($run['twice'], 'assets.download.mode', 'a link over a repeated address');
        $this->assertSame(['ok' => true, 'value' => 2], $run['case'], 'Addresses that differ in case were taken for one');
        $this->assertSame(['ok' => true, 'value' => 3], $run['empty']);
        $this->assertSame(['ok' => true, 'value' => 4], $run['free'], 'A role without resources could not be removed');
        $this->assertSame(['ok' => true, 'value' => null], $run['gone']);
    }

    # Switching on repeats the whole check and needs the directory; a repeat is no change; the extension stays on an active type and on a type with a material
    #[Test]
    public function theStateSwitchChecksWhatIsStored(): void
    {
        $run = $this->getRuns()['status'];
        $this->assertSame([3, true], [$run['on']['value']['version'], $run['on']['value']['active']]);
        $this->assertSame([3, true], [$run['again']['value']['version'], $run['again']['value']['active']], 'A repeat of the state changed the version');
        $this->assertSame('news', $run['public']['name'], 'An active type is not public');
        $this->assertInvalid($run['extactive'], 'ext', 'the extension of an active type');
        $this->assertSame([4, false], [$run['off']['value']['version'], $run['off']['value']['active']]);
        $this->assertInvalid($run['extnode'], 'ext', 'the extension of a type with a material');
        $this->assertInvalid($run['nodir'], 'directory');
        $this->assertInvalid($run['brokup'], 'uploads');
        $this->assertInvalid($run['brokrate'], 'rating');
        $this->assertSame([['ok' => true, 'value' => null], ['ok' => true, 'value' => null]], $run['cleanup']);
        $rows = [['name' => 'news', 'active' => 0, 'version' => 4], ['name' => 'files', 'active' => 0, 'version' => 1], ['name' => 'docs', 'active' => 0, 'version' => 1]];
        $this->assertSame($rows, $run['rows']);
    }

    # Labels, sort, sections, nested values, fields and groups the stored type could not hold are refused before anything is written; existing groups pass
    #[Test]
    public function theInputIsCheckedWhole(): void
    {
        $run = $this->getRuns()['input'];
        $paths = [
            'roleconst' => 'assets.cover.title', 'rolehtml' => 'assets.cover.title', 'titleconst' => 'title', 'titlehtml' => 'title', 'titlelong' => 'title',
            'titleempty' => 'title', 'sort' => 'sort', 'section' => 'color', 'version' => 'version', 'object' => 'list.show', 'float' => 'list.limit', 'form' => 'form',
            'column' => 'fields.version', 'groups' => 'workflow.groups', 'extkey' => 'ext',
        ];
        foreach ($paths as $key => $path) $this->assertInvalid($run[$key], $path, $key);
        $this->assertStringStartsWith('Invalid node input: fields.bad', $run['field']['msg']);
        $this->assertTrue($run['same'], 'A refused input left a trace');
        $this->assertTrue($run['groupok']['ok'], $run['groupok']['msg'] ?? '');
        $this->assertSame(['access' => 'group', 'groups' => [1, 2], 'publish' => [2]], $run['grptrace']['node']['workflow']);
    }

    # The export is the exact slaed.node format; its import under a new name is the same type, the name of the file is used without one, and a broken file leaves no trace
    #[Test]
    public function anExportImportsAsTheSameType(): void
    {
        $run = $this->getRuns()['port'];
        $this->assertSame(['format', 'version', 'type'], $run['keys']);
        $this->assertSame(['name', 'title', 'intro', 'ext', 'sort', 'settings', 'fields', 'uploads', 'rating'], $run['type']);
        $this->assertSame(['slaed.node', 1], [$run['format'], $run['version']]);
        $this->assertSame('files', $run['data']['name']);
        $this->assertSame(['release', 'site'], array_keys($run['data']['fields']));
        $this->assertCount(12, $run['data']['uploads']);
        $this->assertRefused($run['missing'], 1, 'The node type does not exist', 'missing');
        $this->assertSame(['clone', 1, false], [$run['clone']['value']['name'], $run['clone']['value']['version'], $run['clone']['value']['active']]);
        $this->assertTrue($run['same'], 'The clone does not export as its source');
        $this->assertSame('copy', $run['file']['value']['name']);
        $paths = [
            'big' => 'json', 'syntax' => 'json', 'scalar' => 'format', 'format' => 'format', 'version' => 'format', 'extra' => 'format', 'typekey' => 'type',
            'typemiss' => 'type', 'taken' => 'name', 'settings' => 'list.limit', 'float' => 'uploads.maxbytes', 'list' => 'type.settings', 'sort' => 'type.sort',
            'role' => 'assets.cover.mode', 'field' => 'fields.site.type',
        ];
        foreach ($paths as $key => $path) $this->assertInvalid($run['bad'][$key], $path, $key);
        $this->assertRefused($run['bad']['ext'], 3, 'Unknown node extension key', 'ext');
        $this->assertTrue($run['unchanged'], 'A refused import left a trace');
    }

    # A material in the trash, a category and a user file, hidden or in thumb, keep the type; a clean type leaves every area and the rights, its directory stays
    #[Test]
    public function aTypeIsDeletedOnlyWhenNothingOfItIsLeft(): void
    {
        $run = $this->getRuns()['delete'];
        $this->assertRefused($run['stale'], 4, 'The expected type version is stale', 'stale');
        $this->assertInvalid($run['node'], 'nodes');
        $this->assertInvalid($run['category'], 'categories');
        foreach ($run['file'] as $file => $call) $this->assertInvalid($call, 'directory', $file);
        $this->assertNotNull($run['kept']['row']);
        $this->assertNotNull($run['kept']['node']);
        $this->assertSame(['ok' => true, 'value' => null], $run['done']);
        foreach (['row', 'node', 'fields', 'uploads', 'rating'] as $key) $this->assertNull($run['trace'][$key], $key.' is left after the delete');
        $this->assertSame(['.htaccess', 'index.html', 'thumb/index.html'], $run['walk'], 'The directory was not kept with its guards');
        $this->assertSame(['1' => '', '2' => 'forum,node-news,node-docs', '3' => 'node,shop'], $run['admins'], 'The right of the deleted type was left');
        $this->assertSame(['ok' => true, 'value' => 1], $run['reuse'], 'The name of a deleted type cannot be registered again');
        $this->assertSame(['ok' => true, 'value' => null], $run['again']);
    }

    # NOD-206: a type goes public only when the web server itself refuses its directory - the probe server carries the shared nginx rule of docs/node/09
    # A new directory gets both guards, a missing one is written back, a served guard page or a guard with other bytes keeps the type off, and a type already on stays on
    #[Test]
    public function aTypeGoesPublicOnlyBehindARefusingWebServer(): void
    {
        $run = $this->getRuns()['gate'];
        $this->assertSame(['ok' => true, 'value' => 1], $run['add']);
        $this->assertSame([true, 'deny from all'], $run['made'], 'The new directory did not get both guards');
        $this->assertInvalid($run['open'], 'directory', 'a served guard page');
        $this->assertSame('deny from all', $run['written'], 'The missing guard was not written back');
        $this->assertInvalid($run['tampered'], 'directory', 'a guard with other bytes');
        $this->assertInvalid($run['index'], 'directory', 'an index with other bytes');
        $this->assertSame(['ok' => true, 'value' => 2], $run['on']);
        $this->assertSame(['ok' => true, 'value' => 2], $run['again'], 'A repeat of the state asks the server again');
        $this->assertTrue($run['trace']);
        $this->assertSame(['ok' => true, 'value' => 3], $run['off']);
        $this->assertSame(['ok' => true, 'value' => null], $run['delete'], 'A directory of guards alone keeps the type from being deleted');
        $this->assertSame(['.htaccess', 'index.html'], $run['kept'], 'The guards of the deleted type were removed');
    }

    # A writer dies before and after its commit: the type is held, other type writes wait, and the restore follows the database or refuses a row that fits neither side
    #[Test]
    public function anInterruptedOperationIsRestoredByTheDatabase(): void
    {
        foreach (['before' => [4, 'News two', true], 'after' => [5, 'Crash after', false]] as $when => [$row, $title, $old]) {
            $run = $this->getRuns()['crash'][$when];
            $this->assertTrue($run['marker'], $when.': no marker');
            $this->assertSame(['why' => 'proof', 'types' => ['news'], 'phase' => 'prepared'], array_diff_key($run['jour'], ['proof' => 0]), $when);
            $this->assertSame(['kind' => 'update', 'name' => 'news', 'old' => $run['was'], 'new' => $run['was'] + 1], array_diff_key($run['jour']['proof'], ['id' => 0]), $when);
            $this->assertSame($row, $run['row'], $when.': the commit did not end as the crash dictates');
            $this->assertTrue($run['source'], $when.': the sources were not replaced before the commit');
            $this->assertTrue($run['held'], $when.': a held type is readable');
            $this->assertTrue($run['other'], $when.': another type is held too');
            $this->assertRefused($run['write'], 5, 'The configuration of a node type cannot be written', $when.': a write during the marker');
            $this->assertSame(['done' => true, 'why' => '', 'marker' => false], $run['restore'], $when);
            $end = $run['end'];
            $this->assertFalse($end['marker'], $when.': the marker is left');
            $this->assertSame([$row, $old, $title], [$end['row'], $end['old'], $end['read']], $when.': the restore took the wrong side');
            $this->assertTrue($end['recover'], $when.': the guard of the dead writer was not recovered');
            $this->assertSame(0, $end['left']);
        }
        $this->assertSame(['done' => false, 'why' => 'proof', 'marker' => true], $this->getRuns()['crash']['before']['blocked'], 'A row of neither side was decided');
    }

    # A committed type operation whose journal cannot record the committed phase is finished by the proof in the database, never sent back to the old sources
    #[Test]
    public function aLostJournalAfterTheCommitKeepsTheNewSide(): void
    {
        $run = $this->getRuns()['crash']['journal'];
        $this->assertSame(['ok' => true, 'value' => $run['db']], $run['child'], 'The operation did not finish');
        $this->assertSame(1, $run['row'], 'The commit of the operation is missing');
        $this->assertSame($run['db'], $run['conf'], 'The sources went back to the old side while the database kept the new one');
        $this->assertFalse($run['marker'], 'The marker is left');
        $this->assertSame(0, $run['ops'], 'The working snapshot of the operation is left');
    }

    # A name whose comments or favorites an earlier owner left behind is refused until the manager deletes them; a live module or type is never listed or deleted,
    # a key is told apart by its exact bytes, and after the cleanup the name registers
    #[Test]
    public function remainsOfAGoneOwnerHoldTheNameUntilDeleted(): void
    {
        $run = $this->getRuns()['remains'];
        $live = $run['live'];
        $this->assertNotSame('', $live, 'The run has no registered type');
        $this->assertRefused($run['refused'], 3, 'Invalid node input: remains', 'oldsec');
        $this->assertSame(['ok' => true, 'value' => ['OldSec' => ['comments' => 1, 'favorites' => 0], 'gonefav' => ['comments' => 0, 'favorites' => 1],
            'oldsec' => ['comments' => 2, 'favorites' => 1]]], $run['list'], 'The remains are not exactly the gone owners');
        foreach ($run['denied'] as $call) $this->assertRefused($call, 2, 'The context does not manage node types', 'moder');
        foreach ($run['kept'] as $mod => $call) $this->assertRefused($call, 3, 'Invalid node input: name', $mod);
        $this->assertSame(['ok' => true, 'value' => null], $run['one']);
        $this->assertRefused($run['case'], 3, 'Invalid node input: remains', 'OldSec still holds the name');
        $this->assertSame(['ok' => true, 'value' => null], $run['two']);
        $this->assertSame(['oldsec' => 0, 'OldSec' => 0, 'shop' => 1, 'forum' => 1, $live => 1, 'gonefav' => 1], $run['rows'], 'The cleanup touched a live owner');
        $this->assertSame(['ok' => true, 'value' => 1], $run['added'], 'The cleaned name does not register');
        $this->assertSame(['ok' => true, 'value' => ['gonefav']], $run['after']);
        $this->assertSame(['ok' => true, 'value' => null], $run['dropped']);
    }

    # Two processes create one new name at once: the shared lock lets exactly one through
    #[Test]
    public function twoCreatesOfOneNameGiveOneType(): void
    {
        $run = $this->getRuns()['race'];
        $codes = array_map(fn($v) => $v['ok'] ? 'ok' : $v['msg'], $run['runs']);
        sort($codes);
        $this->assertSame(['Invalid node input: name', 'ok'], $codes);
        $this->assertSame(1, $run['trace']['row']['version']);
    }

    # Besides the journal of published type changes, the only lines the run leaves in the log are the types held while an operation was unfinished;
    # the journal names every kind of change with the administrator of the context, and a status that did not change leaves no line
    #[Test]
    public function heldTypesAreLogged(): void
    {
        $seen = [];
        $jour = [];
        foreach ($this->getRuns()['log'] as $line) {
            $one = json_decode($line, true);
            if ($one['msg'] === 'Node: a type operation was published') $jour[$one['kind']][] = [$one['level'], $one['aid'], $one['new'] === 0 || $one['new'] === $one['old'] + 1];
            else $seen[] = $one['msg'].' '.($one['name'] ?? '');
        }
        $this->assertSame(['Node: a type is invalid or held by an unfinished configuration operation news'], array_values(array_unique($seen)));
        ksort($jour);
        $this->assertSame(['add', 'delete', 'status', 'update'], array_keys($jour));
        foreach ($jour as $kind => $rows) $this->assertSame([['info', 3, true]], array_values(array_unique($rows, SORT_REGULAR)), $kind);
    }
}
