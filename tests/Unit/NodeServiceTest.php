<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Stage S11 of docs/node: NodeService writes materials. Create, preview, change, the moves of the state matrix and the
 * physical delete with their categories, fields, relations and resources; the tree and the external link under the lock of
 * the type; counters, reports and the delivery of future publications with Point; the categories of a type and the
 * moderator right node-<name> of the upload helpers. Every behaviour is driven by tests/Support/node_probe.php in its
 * material mode: scratch sources, cache and upload root, one disposable MariaDB database with the shipped schema, copies of
 * the reader and the writer whose closed factory knows a recording extension, and child processes for the races and crashes.
 */
final class NodeServiceTest extends TestCase
{
    private static array $probe = [];

    # The root of the repository
    private static function getRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    # The body of one top-level function of a PHP file, from its signature to the closing brace at column 0
    private static function getBody(string $file, string $name): string
    {
        $code = (string)file_get_contents(self::getRoot().'/'.$file);
        $from = strpos($code, "\nfunction ".$name.'(');
        if ($from === false) return '';
        $end = strpos($code, "\n}\n", $from);
        return substr($code, $from, $end === false ? null : $end - $from + 3);
    }

    # Run the probe once in its material mode and memoize the runs; a probe that cannot create its database is a failure, not a skip
    private function getRuns(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/node_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_service';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' material 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['runs']['schema'], 'The shipped schema did not install');
            $this->assertSame(['query' => true, 'service' => true], $data['runs']['copies'], 'The run does not write with the shipped reader and writer');
            foreach ($data['runs']['types'] as $name => $call) $this->assertSame(['ok' => true, 'value' => 2], $call, 'The type '.$name.' was not created and switched on');
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

    # The writer carries exactly the approved public operations: the four of a type and the import, the four of a material, the preview, the counters,
    # the three of a resource, the file of an attachment, the delivery of publications and the two of a category, with the nullable points and extension of 05-core-api.md
    #[Test]
    public function theWriterHasTheApprovedOperationsAndNothingElse(): void
    {
        require_once self::getRoot().'/core/classes/pdo.php';
        require_once self::getRoot().'/core/classes/comment.php';
        require_once self::getRoot().'/core/classes/field.php';
        require_once self::getRoot().'/core/classes/point.php';
        require_once self::getRoot().'/core/classes/node/load.php';
        $ref = new ReflectionClass('NodeService');
        $this->assertTrue($ref->isFinal());
        $want = [
            '__construct' => ['Database db', 'NodeContext context', 'Field field', '?Point point = NULL', '?NodeExtension ext = NULL', ''],
            'addNode' => ['NodeType type', 'NodeInput input', 'NodeStatus status', 'Node'],
            'addNodeType' => ['string name', 'NodeTypeInput input', 'NodeType'],
            'addNodeTypeImport' => ['string json', "string name = ''", 'NodeType'],
            'deleteNode' => ['int id', 'int version', 'void'],
            'deleteNodeAssetReport' => ['int id', 'NodeType type', 'bool useful', 'void'],
            'deleteNodeCategory' => ['int id', 'void'],
            'deleteNodeType' => ['string name', 'int version', 'void'],
            'getNodeFile' => ['NodeType type', 'int id', 'string key', 'bool thumb', 'string'],
            'getNodePreview' => ['NodeType type', 'NodeInput input', 'NodeStatus status', 'Node'],
            'updateNode' => ['int id', 'NodeInput input', 'int version', 'Node'],
            'updateNodeAssetHits' => ['int id', 'NodeType type', 'void'],
            'updateNodeAssetReport' => ['int id', 'NodeType type', 'void'],
            'updateNodeCategory' => ['int id', 'array row', 'void'],
            'updateNodeComments' => ['int id', 'NodeType type', 'int count', 'void'],
            'updateNodePublishList' => ['int limit = 50', 'array'],
            'updateNodeRating' => ['int id', 'NodeType type', 'int score', 'int ratings', 'void'],
            'updateNodeStatus' => ['int id', 'NodeStatus status', 'int version', 'Node'],
            'updateNodeType' => ['string name', 'NodeTypeInput input', 'int version', 'NodeType'],
            'updateNodeTypeStatus' => ['string name', 'bool active', 'int version', 'NodeType'],
            'updateNodeViews' => ['int id', 'NodeType type', 'void'],
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
        ksort($have);
        ksort($want);
        $this->assertSame($want, $have);
        $this->assertSame([], array_map(fn($v) => $v->getName(), $ref->getMethods(ReflectionMethod::IS_STATIC)));
    }

    # The writer of materials decides by its context alone: no request, session or role helper, no global but the configuration, and SQL values only as bound parameters
    #[Test]
    public function theMaterialWriterReadsNoRequestAndBindsEveryValue(): void
    {
        $code = (string)file_get_contents(self::getRoot().'/core/classes/node/service.php');
        $list = ['$_GET', '$_POST', '$_REQUEST', '$_COOKIE', '$_SESSION', 'is_acess(', 'is_moder(', 'is_user(', 'isAdmin(', 'getIp(', 'getUploadTakenFile(', 'addslashes('];
        foreach ($list as $bad) {
            $this->assertStringNotContainsString($bad, $code, $bad);
        }
        preg_match_all('/^\s*global ([^;]+);/m', $code, $hit);
        $this->assertSame(['$conf'], array_values(array_unique($hit[1])), 'The writer reads another global than the configuration');
        $this->assertSame(1, substr_count($code, 'getEditorFileOwner('), 'The session is asked for more than the token of a guest');
        $glue = '/(?:WHERE|SET|VALUES)[^\'";]*\'\s*\.\s*\$(?!this->getInList|set\b|tail\b|in\b|nin\b|rows\b|limit\b)/';
        $this->assertSame(0, preg_match($glue, $code), 'A value is concatenated into SQL');
        $this->assertStringContainsString("'SELECT GET_LOCK(:name, :wait)'", $code, 'The poll lock is not a bound named lock');
        $this->assertStringContainsString("'SELECT RELEASE_LOCK(:name)'", $code, 'The poll lock is never released');
    }

    # Create: every refused create writes nothing, and the draft, the submission, the direct publication and the guest submission store what the contract says
    #[Test]
    public function aCreateStoresTheMaterialOrNothing(): void
    {
        $run = $this->getRuns()['create'];
        $bad = [
            'disabled' => [3, 'status'], 'deleted' => [3, 'status'], 'guest' => [2, 'The context may not submit this material'],
            'direct' => [2, 'The context may not publish directly'], 'draft' => [2, 'The context may not submit this material'], 'home' => [3, 'home'],
            'pinned' => [3, 'pinned'], 'poll' => [3, 'poll'], 'date' => [3, 'pubdate'], 'comon' => [3, 'comon'], 'nopoll' => [3, 'poll'], 'nohome' => [3, 'home'],
            'expires' => [3, 'expires'], 'offpoll' => [3, 'poll'], 'forum' => [3, 'cid'], 'othercat' => [3, 'cid'],
            'closed' => [2, 'The context may not post into the category'], 'missing' => [3, 'cid'], 'twice' => [3, 'cids'],
            'task' => [2, 'A background context writes no material'], 'blank' => [3, 'title'], 'control' => [3, 'title'], 'long' => [3, 'title'],
            'aname' => [3, 'aname'], 'ext' => [3, 'ext'], 'moder' => [2, 'The context may not submit this material'],
            'nosubmit' => [2, 'The context may not submit this material'], 'required' => [3, 'fields.release.required'], 'intro' => [3, 'intro'],
            'field' => [3, 'fields.release.max'], 'nopoint' => [3, 'point'],
        ];
        $this->assertSame(array_keys($bad), array_keys($run['bad']));
        foreach ($bad as $key => [$code, $text]) {
            if ($code === 3) $this->assertInvalid($run['bad'][$key], $text, $key);
            else $this->assertRefused($run['bad'][$key], $code, $text, $key);
        }
        $this->assertTrue($run['same'], 'A refused create left a row, a job, points or a guard behind');
        $draft = $run['draft']['value'];
        $this->assertSame(['Draft', 1, 0, 'Editor', '127.0.0.1', null, [15, 16], 1], [$draft['status'], $draft['version'], $draft['uid'], $draft['aname'], $draft['ip'],
            $draft['pubdate'], $draft['cids'], $draft['poll']], 'The draft of a moderator');
        $this->assertTrue($run['epoch'], 'A create does not reach the page cache');
        $pend = $run['pending']['value'];
        $this->assertSame(['Pending', 2, '', null, 12], [$pend['status'], $pend['uid'], $pend['aname'], $pend['ip'], $pend['cid']], 'The submission of a user');
        $direct = $run['direct']['value'];
        $this->assertSame(['Published', 3, $run['clock']], [$direct['status'], $direct['uid'], $direct['pubdate']], 'The direct publication of the group publish');
        $this->assertSame([['uid' => 3, 'points' => 1, 'rid' => null]], $run['award'], 'A direct publication is not rewarded once in its own transaction');
        $this->assertSame(['site' => 'https://example.com/'], $run['filedraft']['value']['fields'], 'The default of an empty active field is not applied on create');
        $guest = $run['guestfile']['value'];
        $this->assertSame(['Pending', 0, ['release' => '1.0', 'site' => 'https://example.com/']], [$guest['status'], $guest['uid'], $guest['fields']], 'A guest submission');
        $this->assertSame('https://example.com/g.zip', $guest['assets'][0]['src']);
        $this->assertInvalid($run['nomin'], 'assets.download.min', 'A pending material below the minimum of a role');
        $this->assertSame(0, $run['guards'], 'A write left a cache guard behind');
    }

    # Update: the stale, the foreign and the forged are refused without a trace; a moderator replaces every full set, keeps author, address and inactive field
    #[Test]
    public function anUpdateReplacesTheFullSetsAtTheExpectedVersion(): void
    {
        $run = $this->getRuns()['update'];
        $this->assertRefused($run['bad']['stale'], 4, 'The expected material version is stale');
        $this->assertRefused($run['bad']['anna'], 2, 'The context moderates no material');
        $this->assertRefused($run['bad']['boss'], 2, 'The context moderates no material', 'Managing Node is no right on a material');
        $this->assertRefused($run['bad']['missing'], 1, 'The material does not exist');
        $this->assertInvalid($run['bad']['self'], 'rels', 'A relation to the material itself');
        $this->assertInvalid($run['bad']['foreign'], 'assets.0.id', 'A resource id of another material');
        $this->assertTrue($run['same'], 'A refused update changed something');
        $full = $run['full']['value'];
        $this->assertSame([2, 'Changed', [16]], [$full['version'], $full['title'], $full['cids']]);
        $this->assertSame([['related', 5]], array_map(fn($v) => [$v['type'], $v['sort']], $full['rels']));
        $this->assertSame([['cover', 3, 'photo-abcdefghij-2.png'], ['gallery', 1, 'photo-bcdefghijk-3.png']], array_map(fn($v) => [$v['role'], $v['sort'], $v['src']],
            $full['assets']));
        $cleared = $run['cleared']['value'];
        $this->assertSame([3, [], [], []], [$cleared['version'], $cleared['cids'], $cleared['rels'], $cleared['assets']], 'Empty full sets do not clear');
        $keep = $run['keep']['value'];
        $this->assertSame([2, '127.0.0.1', 'Edited', 'Pending'], [$keep['uid'], $keep['ip'], $keep['title'], $keep['status']], 'A moderator took over author or address');
        $this->assertSame(['old' => 'keep', 'release' => '2.0'], $run['fields']['value'], 'Inactive values, hidden input and unknown names');
        $this->assertSame(['ok' => true, 'value' => 2], $run['first']);
        $this->assertRefused($run['second'], 4, 'The expected material version is stale', 'The second editor');
        $this->assertSame(['ok' => true, 'value' => 3], $run['again'], 'The second editor after taking the current version');
        $this->assertSame('Second', $run['title']);
    }

    # States: exactly the pairs of the closed matrix pass with one new version, the repeat writes nothing, readiness binds pending and published,
    # and a move costs at most six Node statements, seven with a job of _node_publish
    #[Test]
    public function theStateMachineFollowsTheMatrix(): void
    {
        $run = $this->getRuns()['status'];
        $allow = ['Draft' => ['Pending', 'Published', 'Deleted'], 'Pending' => ['Draft', 'Published', 'Deleted'], 'Published' => ['Disabled', 'Deleted'],
            'Disabled' => ['Pending', 'Published', 'Deleted'], 'Deleted' => ['Disabled']];
        foreach ($allow as $from => $list) {
            foreach (array_keys($allow) as $to) {
                $want = ($from === $to) ? 0 : (in_array($to, $list, true) ? 1 : 3);
                $this->assertSame($want, $run['pairs'][$from.'-'.$to], $from.' to '.$to);
            }
        }
        $this->assertSame(0, $run['repeat'], 'The repeat of the current state moved the version');
        $this->assertLessThanOrEqual(2, $run['repeatsql'], 'The repeat of the current state ran more than its reads');
        $this->assertRefused($run['stale'], 4, 'The expected material version is stale');
        $this->assertRefused($run['anna'], 2, 'The context moderates no material');
        $this->assertInvalid($run['required'], 'fields.release.required');
        $this->assertInvalid($run['minimum'], 'assets.download.min');
        $this->assertSame(['ok' => true, 'value' => 'Published'], $run['ready']);
        $this->assertLessThanOrEqual(6, $run['budget'], 'A move without a job');
        $this->assertLessThanOrEqual(7, $run['budgetjob'], 'A move with a job of _node_publish');
        $this->assertTrue($run['published'], 'A publication without a date does not take the clock of the database');
        $this->assertTrue($run['job'], 'A publication with a future date gets no job');
        $this->assertSame('Disabled', $run['restore'], 'A restore does not end disabled');
        $this->assertSame(0, $run['guards']);
    }

    # Delete: the stale and the foreign are refused; the physical delete takes every row of the material, compensates the publication once,
    # leaves the file and a child as a root, and costs five Node statements besides Point
    #[Test]
    public function aPhysicalDeleteTakesEveryRowAndCompensatesThePublication(): void
    {
        $run = $this->getRuns()['delete'];
        $this->assertRefused($run['bad']['stale'], 4, 'The expected material version is stale');
        $this->assertRefused($run['bad']['anna'], 2, 'The context moderates no material');
        $this->assertInvalid($run['bad']['nopoint'], 'point', 'A compensation without Point');
        $this->assertSame(['ok' => true, 'value' => null], $run['done']);
        $this->assertSame(-1, $run['balance'], 'The publication award was not taken back');
        $this->assertSame([['uid' => 3, 'points' => 1, 'rid' => null]], $run['origin']);
        $this->assertSame([['uid' => 3, 'points' => -1, 'rid' => $run['oid']]], $run['reverse'], 'The compensation does not point at its origin');
        $this->assertSame(['nodes' => 0, 'node_assets' => 0, 'node_categories' => 0], $run['rows']);
        $this->assertTrue($run['file'], 'The delete removed the file a resource pointed at');
        $this->assertSame([], $run['child'], 'The parent link of a child survived the delete of its parent');
        $this->assertSame(5, $run['sqlnode'], 'The Node statements of a physical delete');
    }

    # Full sets: the limits of categories and relations by the sync batch and of resources by maxassets, the roles, the shapes and the kinds
    #[Test]
    public function theFullSetsAreClosedAndBounded(): void
    {
        $run = $this->getRuns()['sets'];
        $want = ['cids' => 'cids', 'rels' => 'rels', 'assets' => 'assets', 'max' => 'assets.cover.max', 'keys' => 'assets.0', 'zero' => 'assets.0.id',
            'role' => 'assets.0.role', 'kind' => 'assets.0.kind', 'relkeys' => 'rels.0', 'reltype' => 'rels.0.type', 'relkind' => 'rels.0', 'reltwice' => 'rels.1',
            'relother' => 'rels', 'parents' => 'rels.1'];
        foreach ($want as $key => $path) $this->assertInvalid($run[$key], $path, $key);
        $this->assertSame(['ok' => true, 'value' => [15]], $run['main'], 'The main category stays in the extra set');
    }

    # Files: a new attachment and a new local source belong to the visitor, a moderator binds any file, metadata is read once,
    # and an external link is unique inside its type by the whole address, also against a concurrent writer
    #[Test]
    public function newFileBindingsBelongToTheVisitorAndLinksAreUnique(): void
    {
        $run = $this->getRuns()['files'];
        $this->assertTrue($run['own']['ok'], 'An own attachment');
        $this->assertTrue($run['moder']['ok'], 'A moderator binding foreign files');
        foreach (['foreign', 'legacy', 'guest'] as $key) $this->assertInvalid($run[$key], 'attach.owner', $key);
        foreach (['text', 'absent'] as $key) $this->assertInvalid($run[$key], 'attach', $key);
        $cover = $run['cover']['value'][0];
        $this->assertSame(['image', 'photo-abcdefghij-2.png', 'image/png', 1, 1, null], [$cover['kind'], $cover['src'], $cover['mime'], $cover['width'], $cover['height'],
            $cover['duration']]);
        $this->assertGreaterThan(0, $cover['size']);
        $this->assertInvalid($run['coverforeign'], 'assets.0.src.owner');
        $this->assertInvalid($run['pdfimage'], 'assets.0.kind');
        foreach (['thumb', 'escape', 'nolink'] as $key) $this->assertInvalid($run[$key], 'assets.0.src', $key);
        $this->assertSame(['application/pdf', null, null], [$run['pdffile']['value']['mime'], $run['pdffile']['value']['width'], $run['pdffile']['value']['height']]);
        $link = $run['link'];
        foreach (['first', 'case', 'longa', 'longb', 'other'] as $key) $this->assertTrue($link[$key]['ok'], $key);
        foreach (['twin', 'local', 'script', 'creds'] as $key) $this->assertInvalid($link[$key], 'assets.0.src', $key);
        $this->assertSame(['ok' => true, 'value' => 2], $link['keep'], 'The own unchanged address of a material is its duplicate');
        $oks = array_values(array_filter($run['race'], fn($v) => ($v['ok'] ?? false) === true));
        $bad = array_values(array_filter($run['race'], fn($v) => ($v['ok'] ?? true) === false));
        $this->assertCount(1, $oks, 'Two concurrent writers of one address');
        $this->assertCount(1, $bad);
        $this->assertInvalid($bad[0], 'assets.0.src');
    }

    # Tree: a cycle through a descendant is refused, two concurrent moves that close a cycle let exactly one through, and a move that dies before its commit
    # leaves no edge and no held lock behind
    #[Test]
    public function theTreeIsChangedUnderTheLockOfTheType(): void
    {
        $run = $this->getRuns()['tree'];
        $this->assertInvalid($run['cycle'], 'rels.parent');
        $this->assertSame(['ok' => true, 'value' => 2], $run['move']);
        $this->assertTrue($run['moved']);
        $oks = array_values(array_filter($run['race'], fn($v) => ($v['ok'] ?? false) === true));
        $bad = array_values(array_filter($run['race'], fn($v) => ($v['ok'] ?? true) === false));
        $this->assertCount(1, $oks, 'Both concurrent moves or none went through: '.json_encode($run['race']));
        $this->assertInvalid($bad[0], 'rels.parent');
        $this->assertSame(1, count(array_filter($run['edges'])), 'The stored edges do not match the one move that passed');
        $this->assertSame(0, $run['after'], 'A move that died before its commit left its edge');
        $this->assertSame(['ok' => true, 'value' => 2], $run['next'], 'The move after the crash found a held lock or a partial state');
        $this->assertTrue($run['nextedge']);
        $this->assertTrue($run['cleared'], 'The guard of the crashed writer was not recovered');
    }

    # Publication: a future date gets its job and no points, the job is delivered exactly once by the background context after its date,
    # and every move, cancel, absorbed reward, missing author, inactive type, recoverable failure, parallel run and crash follows 03-database.md
    #[Test]
    public function aFuturePublicationIsDeliveredOnceByTheScheduler(): void
    {
        $run = $this->getRuns()['publish'];
        $this->assertSame($run['future']['pub'], $run['future']['job']['published']);
        $this->assertSame($run['future']['pub'], $run['future']['job']['due']);
        $this->assertSame(0, $run['future']['points'], 'A future publication was rewarded at once');
        $this->assertSame(['processed' => 0, 'failed' => 0, 'skipped' => 0], $run['early']['extra']);
        $this->assertTrue($run['wait']);
        $this->assertRefused($run['views'], 1, 'The material does not exist', 'A visitor reached a publication before its date');
        $this->assertSame(['status' => 'success', 'message' => 'Node publication: 1 processed, 0 failed, 0 skipped',
            'extra' => ['processed' => 1, 'failed' => 0, 'skipped' => 0]], $run['due']);
        $this->assertSame(['job' => null, 'points' => 1], $run['once']);
        $this->assertSame(0, $run['again']['extra']['processed']);
        $this->assertSame(1, $run['still']);
        $this->assertSame($run['moved']['node'], $run['moved']['job']['published'], 'A moved date left the job behind');
        $this->assertSame($run['moved']['node'], $run['moved']['job']['due']);
        $this->assertSame(0, $run['moved']['points']);
        $this->assertSame(['job' => null, 'points' => 1], $run['past'], 'A pending job moved into the past is not delivered as the publication that came');
        $this->assertSame(['job' => null, 'points' => 0], $run['cancel'], 'Leaving published does not cancel the job');
        $this->assertSame(['Published', 1], $run['republish'], 'A later publication is not rewarded for the first time');
        $this->assertNull($run['deleted']);
        $this->assertSame(['processed' => 1, 'failed' => 0, 'skipped' => 0], $run['zero']['run']);
        $this->assertSame([null, 0], [$run['zero']['job'], $run['zero']['points']], 'A zero reward did not absorb the job');
        $this->assertSame(['Published', 1], $run['zero']['later'], 'An absorbed job blocks the first reward of a later publication');
        $edge = $run['edge'];
        $this->assertSame(['processed' => 3, 'failed' => 0, 'skipped' => 0], $edge['run']);
        $this->assertSame([null, null, 0, null, 1], [$edge['anon'], $edge['lost'], $edge['lostpts'], $edge['gap'], $edge['gappts']],
            'An author without an account, a missing account and a passed expiry');
        $this->assertSame(['run' => ['processed' => 0, 'failed' => 0, 'skipped' => 0], 'job' => true], $run['inactive'], 'A job of an inactive type was processed');
        $this->assertSame(['run' => ['processed' => 1, 'failed' => 0, 'skipped' => 0], 'job' => null, 'points' => 1], $run['active']);
        $this->assertSame(['status' => 'failed', 'run' => ['processed' => 0, 'failed' => 1, 'skipped' => 0], 'job' => true, 'later' => true], $run['broken'],
            'A recoverable failure of the points does not keep the job one minute on');
        $this->assertSame(['run' => ['processed' => 1, 'failed' => 0, 'skipped' => 0], 'points' => 1], $run['healed']);
        $par = $run['parallel'];
        $this->assertCount(1, $par['due'], 'The parallel case started with more than its own job');
        $done = array_sum(array_map(fn($v) => $v['value']['extra']['processed'] ?? 0, $par['runs']));
        $this->assertSame(1, $done, 'Two parallel runs delivered one job twice or never');
        $this->assertSame([1, null], [$par['points'], $par['job']]);
        $this->assertSame(['job' => true, 'points' => 0], $run['crash']['before']['mid'], 'A run that died before its commit kept something');
        $this->assertSame([['processed' => 1, 'failed' => 0, 'skipped' => 0], 1, null], [$run['crash']['before']['next'], $run['crash']['before']['points'],
            $run['crash']['before']['job']]);
        $this->assertSame(['job' => false, 'points' => 1], $run['crash']['after']['mid'], 'A run that died after its commit lost its result');
        $this->assertSame([['processed' => 0, 'failed' => 0, 'skipped' => 0], 1, null], [$run['crash']['after']['next'], $run['crash']['after']['points'],
            $run['crash']['after']['job']]);
        $this->assertRefused($run['denied'], 2, 'Only a background context delivers publications');
        $this->assertInvalid($run['limit'], 'limit');
        $sched = [$run['sched']['status'] ?? null, $run['sched']['job'] ?? null];
        $this->assertSame(['success', 'nodepublish'], $sched, 'The scheduler does not run the job: '.json_encode($run['sched']));
        $this->assertSame(['processed', 'failed', 'skipped'], array_keys($run['sched']['extra']));
    }

    # The job is registered in all four places of the scheduler and returns its statuses: the map and the dispatch of core/system.php, the shipped configuration
    # and the addition of the update branch in setup/index.php; the adapter builds the one trusted background context
    #[Test]
    public function theJobIsRegisteredInTheFourPlacesOfTheScheduler(): void
    {
        $this->assertStringContainsString("'nodepublish' => 'nodepublish'", self::getBody('core/system.php', 'getSchedulerJob'));
        $this->assertStringContainsString("'nodepublish' => addNodePublishTask(),", self::getBody('core/system.php', 'addSchedulerSystemJob'));
        $task = self::getBody('core/system.php', 'addNodePublishTask');
        $this->assertStringContainsString("new NodeContext(0, [], 0, [], false, false, '', '', true)", $task);
        $this->assertStringContainsString('->updateNodePublishList(', $task);
        $this->assertStringContainsString("'status' => 'failed'", $task);
        $conf = require self::getRoot().'/config/scheduler.php';
        $job = $conf['scheduler']['jobs']['nodepublish'] ?? [];
        $this->assertSame(['system', '1', 'nodepublish', '* * * * *', '180', '1', ['limit' => '50']], [$job['type'] ?? '', $job['active'] ?? '', $job['system'] ?? '',
            $job['schedule'] ?? '', $job['lock_timeout'] ?? '', $job['manual'] ?? '', $job['settings'] ?? []]);
        $setup = (string)file_get_contents(self::getRoot().'/setup/index.php');
        $this->assertStringContainsString("if (is_array(\$sched) && !isset(\$sched['jobs']['nodepublish'])) {", $setup);
        $this->assertStringContainsString("'schedule' => '* * * * *',\n                    'priority' => '6',\n                    'lock_timeout' => '180',", $setup);
    }

    # Counters: a view counts a readable publication only and is rewarded once, and comments and ratings are written only inside the transaction of their owner,
    # checked and without a new version, date or cache generation
    #[Test]
    public function theCountersMoveWithoutAVersion(): void
    {
        $run = $this->getRuns()['counters'];
        $this->assertSame([['ok' => true, 'value' => null], ['ok' => true, 'value' => null]], $run['view']);
        $this->assertRefused($run['draft'], 1, 'The material does not exist');
        $this->assertSame([['uid' => 2, 'points' => 1, 'rid' => null]], $run['points'], 'A view is not rewarded exactly once');
        $this->assertTrue($run['epoch'], 'A view raised the cache generation');
        $this->assertInvalid($run['notx'], 'transaction');
        $this->assertSame(['ok' => true, 'value' => null], $run['comments']);
        $this->assertInvalid($run['negative'], 'comnum');
        $this->assertSame(['ok' => true, 'value' => null], $run['rating']);
        foreach (['over', 'under', 'none'] as $key) $this->assertInvalid($run[$key], 'rating', $key);
        $this->assertRefused($run['other'], 1, 'The material does not exist', 'A counter of another type');
        $this->assertSame(['views' => 2, 'comnum' => 3, 'score' => 7, 'ratings' => 2, 'version' => 1, 'same' => true], $run['after']);
    }

    # Resources: a download and a visit are counted and rewarded by the mode of their role, a report keeps its first author,
    # and only the main administrator or a moderator of the type decides it, rewarding a useful report of a registered author once
    #[Test]
    public function resourceCountersAndReportsFollowTheirMode(): void
    {
        $run = $this->getRuns()['assetops'];
        $this->assertSame([['ok' => true, 'value' => null], ['ok' => true, 'value' => null]], $run['hits']);
        $this->assertSame([['uid' => 2, 'points' => 1, 'rid' => null]], $run['download']);
        $this->assertSame([['ok' => true, 'value' => null], [['uid' => 2, 'points' => 1, 'rid' => null]]], $run['visit']);
        $this->assertInvalid($run['cover'], 'asset.mode');
        $this->assertRefused($run['wrongtype'], 1, 'The resource does not exist');
        $this->assertInvalid($run['noreport'], 'asset.report');
        $this->assertSame(['hits' => 2, 'open' => 1, 'ruid' => 2], array_diff_key($run['rows']['download'], ['updated' => 0]), 'The first report lost its author');
        $this->assertSame(['hits' => 1, 'open' => 1, 'ruid' => 0], array_diff_key($run['rows']['link'], ['updated' => 0]), 'A guest report');
        $this->assertTrue($run['sameupd'], 'A counter or a report changed the date of the resource');
        $this->assertTrue($run['epoch'], 'A counter or a report raised the cache generation');
        $this->assertSame(1, $run['version']);
        foreach (['anna', 'boss', 'moder'] as $key) $this->assertRefused($run['decide'][$key], 2, 'The context does not moderate the type', $key);
        foreach (['useful', 'again', 'guest'] as $key) $this->assertSame(['ok' => true, 'value' => null], $run['decide'][$key], $key);
        $this->assertSame(['hits' => 2, 'open' => 0, 'ruid' => 0], array_diff_key($run['cleared']['download'], ['updated' => 0]));
        $this->assertSame(['hits' => 1, 'open' => 0, 'ruid' => 0], array_diff_key($run['cleared']['link'], ['updated' => 0]));
        $this->assertCount(1, $run['rewards'], 'The decisions rewarded more or less than the one useful registered report');
        $this->assertSame(2, $run['rewards'][0]['uid']);
        $this->assertMatchesRegularExpression('/^report:\d+:[0-9a-f]{16}$/D', $run['rewards'][0]['source']);
    }

    # Categories: a used category never leaves its type and is never deleted, its language changes through the guard, an extra link leaves with a new version,
    # and only the main administrator, the manager of Node or a moderator of the type writes it
    #[Test]
    public function theCategoriesOfATypeAreChangedUnderItsLock(): void
    {
        $run = $this->getRuns()['categories'];
        foreach (['move', 'moveextra', 'deleteused'] as $key) $this->assertInvalid($run[$key], 'category.used', $key);
        $this->assertSame(['ok' => true, 'value' => null], $run['lang']);
        foreach (['keys', 'forum'] as $key) $this->assertInvalid($run[$key], 'category', $key);
        $this->assertRefused($run['anna'], 2, 'The context administers no category');
        $this->assertRefused($run['moder'], 2, 'The context does not administer the type');
        $this->assertRefused($run['deletemissing'], 1, 'The category does not exist');
        $this->assertTrue($run['epoch'], 'A category write does not reach the page cache');
        $this->assertSame(['news', 'german'], $run['stored']);
        $this->assertSame(['ok' => true, 'value' => null], $run['delete']);
        $this->assertSame([null, null], $run['gone'], 'The category or its subcategory stayed');
        $this->assertSame(['version' => 2, 'cids' => []], $run['extnode'], 'The extra link left without a new version of its material');
        $this->assertSame(['ok' => true, 'value' => null], $run['free']);
        $this->assertSame('forum', $run['moved']);
        $this->assertSame([0, 16], [$run['guards'], $run['used']]);
    }

    # Preview: the same checks as a create, an unsaved material with id 0 and version 0 and its metadata, and nothing written anywhere
    #[Test]
    public function aPreviewChecksLikeACreateAndWritesNothing(): void
    {
        $run = $this->getRuns()['preview'];
        $node = $run['ok']['value'];
        $this->assertSame([0, 0, 'Pending', 2, [15], ''], [$node['id'], $node['version'], $node['status'], $node['uid'], $node['cids'], $node['created']]);
        $this->assertSame([0, 'image/png', 1], [$node['assets'][0]['id'], $node['assets'][0]['mime'], $node['assets'][0]['width']]);
        $this->assertInvalid($run['foreign'], 'attach.owner');
        $this->assertRefused($run['direct'], 2, 'The context may not publish directly');
        $this->assertRefused($run['closed'], 2, 'The context may not post into the category');
        $this->assertRefused($run['task'], 2, 'A background context writes no material');
        $this->assertSame(['ok' => true, 'value' => 0], $run['nopoint'], 'A preview needs the points of a write');
        $this->assertTrue($run['same'], 'A preview wrote a row, a job, points or a guard');
        $this->assertTrue($run['epoch'], 'A preview raised the cache generation');
    }

    # The extension of a type: exactly the registered instance writes it, its data is checked before and written inside the transaction of every write,
    # and its failure takes the main write back with it
    #[Test]
    public function theExtensionJoinsEveryWriteInsideItsTransaction(): void
    {
        $run = $this->getRuns()['hook'];
        $this->assertInvalid($run['none'], 'extension', 'A type with an extension written without it');
        $this->assertInvalid($run['extra'], 'extension', 'A standard type written with an extension');
        $this->assertRefused($run['baddata'], 3, 'hook data');
        $this->assertSame([['filter', 0], ['add', true, ['note' => 'a']], ['filter', $run['log'][2][1]], ['update', 1, ['note' => 'b']], ['update', 1, null],
            ['delete', true]], $run['log']);
        $this->assertGreaterThan(0, $run['log'][2][1]);
        $this->assertRefused($run['fail'], 5, 'A node write failed');
        $this->assertTrue($run['same'], 'A failed extension left the main write behind');
    }

    # The upload helpers: the moderator right of a place is node-<name> for a Node type and the module key for any other module, through one helper asked in all six places;
    # a booted moderator of the type passes, a site user owns by his id, and an administrator who still carries the key of a removed module of the same name inherits nothing
    #[Test]
    public function theUploadHelpersKnowTheModeratorOfANodeType(): void
    {
        $run = $this->getRuns()['upload'];
        $this->assertSame(['moder' => true, 'forum' => true, 'shop' => false, 'none' => false, 'owner' => null, 'flag' => true], $run['moder']);
        $this->assertSame(['moder' => false, 'forum' => false, 'shop' => false, 'none' => false, 'owner' => '2', 'flag' => false], $run['anna']);
        $this->assertSame(['moder' => false, 'forum' => true, 'shop' => false, 'none' => false, 'flag' => false], array_diff_key($run['legacy'], ['owner' => 0]),
            'The stored key of a removed module made its administrator a moderator of the Node type of that name');
        $this->assertStringContainsString("return \$mod !== '' && is_moder(\$mod) === 1;", self::getBody('core/system.php', 'checkUploadModer'));
        $this->assertStringContainsString("if (isset(\$conf['node']['types'][\$modul])) \$modul = 'node-'.\$modul;", self::getBody('core/system.php', 'is_admin_modul'));
        foreach (['checkEditorUploadAccess', 'getEditorFileOwner', 'getUploadFileArea', 'getUploadTakenFile', 'addEditorUpload', 'getEditorFileJson'] as $name) {
            $body = self::getBody('core/system.php', $name);
            $this->assertStringContainsString('checkUploadModer($mod)', $body, $name);
            $this->assertStringNotContainsString('is_moder(', $body, $name);
        }
        $this->assertStringContainsString("'moder' => \$upl && checkUploadModer(\$mod),", self::getBody('core/helpers.php', 'getUploadPlaceView'));
        $this->assertStringContainsString('$mdr = $upl && checkUploadModer($mod);', (string)file_get_contents(self::getRoot().'/plugins/editors/toastui/driver.php'));
        $room = self::getBody('core/helpers.php', 'getEditorRoomData');
        $this->assertStringContainsString("'nodes.body' => 'mediumtext'", $room);
        $this->assertStringContainsString("'nodes.intro' => 'text'", $room);
    }

    # Trusted tags: the right to author [usephp] and [usehtml] belongs to the main administrator alone, so every other author loses them at the write,
    # also rebuilt from a nested pair, in the texts, the preview, the captions of resources and the values of fields
    #[Test]
    public function theTrustedTagsLeaveEveryTextButThatOfTheMainAdministrator(): void
    {
        $run = $this->getRuns()['trust'];
        $clean = 'A echo 1; <b>x</b> B';
        foreach (['anna', 'moder'] as $who) $this->assertSame(['intro' => $clean, 'body' => $clean, 'cap' => 'cap'], $run[$who], $who);
        $raw = 'A [usephp]echo 1;[/usephp] [UseHtml]<b>x</b>[/UseHtml] [us[usephp]ephp]B';
        $this->assertSame(['intro' => $raw, 'body' => $raw, 'cap' => '[usehtml]cap[/usehtml]'], $run['root'], 'The main administrator lost his trusted tags');
        $this->assertSame($clean, $run['preview']);
        $this->assertSame('1.0', $run['field']['release'], 'A field value kept a trusted tag');
    }

    # A switch turned off keeps what a material already carries: an unchanged poll saves again, another poll is refused, and clearing it is allowed
    #[Test]
    public function aSwitchedOffPollKeepsTheStoredLink(): void
    {
        $run = $this->getRuns()['keep'];
        $this->assertTrue($run['type']['ok']);
        $this->assertSame(['ok' => true, 'value' => 1], $run['same'], 'The stored poll of a material blocks its edit once the feature is off');
        $this->assertInvalid($run['other'], 'poll');
        $this->assertSame(['ok' => true, 'value' => 0], $run['clear']);
    }

    # The file of an attachment (S12): a stored material grants the names of its own intro and body to a reader, in two statements with the type; a thumb only after
    # its original and only when it exists; the preview of NOD-199 grants the files of the visitor and every file to a moderator without SQL; each refusal is the same ''
    #[Test]
    public function anAttachmentFileIsGrantedByItsMaterialOrItsOwner(): void
    {
        $run = $this->getRuns()['attach'];
        $this->assertSame('news/photo-abcdefghij-2.png', $run['saved']);
        $this->assertSame('news/photo-bcdefghijk-3.png', $run['intro'], 'A name of the intro is not granted');
        $this->assertSame('news/thumb/photo-abcdefghij-2.png', $run['thumb']);
        foreach (['nothumb', 'absent', 'pending', 'othertype', 'task', 'negative'] as $key) $this->assertSame('', $run[$key], $key.' was granted');
        $this->assertSame('news/photo-abcdefghij-2.png', $run['pendroot'], 'The moderator cannot read the file of a pending material');
        foreach ($run['attack'] as $i => $pair) $this->assertSame('|', $pair, 'Crafted key '.$i.' reached a file');
        $this->assertContains($run['symlink'], ['none', ''], 'A link out of the type directory was followed');
        $pre = $run['preview'];
        $this->assertSame(['news/photo-abcdefghij-2.png', 'news/thumb/photo-abcdefghij-2.png', 'news/photo-bcdefghijk-3.png'], [$pre['anna'], $pre['annathumb'], $pre['boris']]);
        foreach (['foreign', 'clara', 'guest', 'guestall', 'task', 'text'] as $key) $this->assertSame('', $pre[$key], 'The preview granted '.$key);
        foreach (['moder', 'root', 'far'] as $key) $this->assertSame('news/photo-bcdefghijk-3.png', $pre[$key], 'The moderator '.$key.' is refused a file of the type');
        $this->assertSame([2, 0], [$run['sql'], $run['previewsql']], 'The attachment costs the type and the text row, the preview no statement');
    }

    # The category screen hands every write of a category of a Node type to the writer and keeps its own SQL for the other modules
    #[Test]
    public function theCategoryScreenWritesNodeCategoriesThroughTheWriter(): void
    {
        $code = (string)file_get_contents(self::getRoot().'/admin/modules/categories.php');
        $this->assertSame(2, substr_count($code, '->updateNodeCategory($id, '), 'The save and the switch of a Node category');
        $this->assertSame(1, substr_count($code, '->deleteNodeCategory($id)'), 'The delete of a Node category');
        $this->assertSame(3, substr_count($code, 'getNodeTypeMap()'), 'The screen decides a Node category by something else than the type map');
        $this->assertStringNotContainsString('_nodes', $code, 'The category screen touches a Node table itself');
        $mods = self::getBody('core/helpers.php', 'getCategoryModules');
        $this->assertStringContainsString("array_filter(getNodeTypeMap(), fn(\$v) => \$v->settings['features']['categories'])", $mods, 'The category modules miss the Node types');
        $this->assertStringContainsString("array_merge(['forum', 'shop'], array_keys(\$types))", $mods);
    }
}
