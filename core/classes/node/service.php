<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The only writer of Node: the operations of a type and the import of its definition, the materials with their categories, relations and resources, the counters,
# the categories of a type and the delivery of future publications; every write is decided against the context of the instance and nothing is taken from a global
# A type operation is the closure of the shared configuration writer: the configuration lock, the root lock of the type directory, the cache guard, BEGIN, the locked type row,
# the checks against the fresh areas, the SQL, the package of the four shared areas with its proof, COMMIT
# A material write keeps the same order without the configuration: the root lock with the file checks, the poll locks, the cache guard, BEGIN, the type row, the categories
# and the materials by ascending id, the resources and the points; the final cache generation follows the commit
# The effective settings are checked by NodeQuery::filterNodeSettings() alone; this class adds what needs SQL, the language, the clock or the file tree
final class NodeService {

    # The grammar of a public type name and of an extension key
    private const NAME = '/^[a-z][a-z0-9]{0,19}$/D';

    # Names no type may take: the module itself, the system entries of the tree and the device names Windows keeps
    private const RESERVED = [
        'node', 'admin', 'index', 'setup', 'core', 'config', 'storage', 'templates', 'plugins', 'uploads', 'tools', 'tests', 'vendor', 'con', 'prn', 'aux', 'nul',
        'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9', 'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];

    # The nine standard replacements, which may be registered although a module of the same name once existed
    private const SWAPS = ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media'];

    # The rating rule a new type gets when its input brings none, as ratings.md defines a new rule
    private const RATE = ['active' => '1', 'period' => '2592000', 'detail' => '1', 'guests' => '1'];

    # The largest whole limit of an upload rule the reader accepts, eighteen digits
    private const UPMAX = 999999999999999999;

    # The largest export an import accepts, and the largest canonical JSON of the field values of one material
    private const JSONMAX = 1048576;

    # The largest value an unsigned integer column holds
    private const MAXINT = 4294967295;

    # A canonical database date and time
    private const DATE = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/D';

    # The keys of one resource and of one relation of a full input set, and the relation kinds with the feature of the type each one needs
    private const ASSETKEYS = ['id', 'kind', 'role', 'src', 'name', 'title', 'intro', 'sort'];
    private const RELKEYS = ['rid', 'type', 'sort'];
    private const RELS = ['related' => 'related', 'parent' => 'tree'];

    # How many seconds a write waits for the named lock of a poll before it gives up with a conflict
    private const POLLWAIT = 5;

    # The columns a category form writes, in the order of the category table
    private const CATKEYS = ['modul', 'title', 'intro', 'img', 'lang', 'parent', 'status', 'pview', 'pread', 'ppost', 'preply', 'pedit', 'pdelete', 'pmod'];

    # The columns of a stored material the writer reads back
    private const COLS = 'n.id, n.tid, n.cid, n.uid, n.aname, n.ip, n.title, n.intro, n.body, n.field, n.poll, n.home, n.comon, n.pinned, n.comnum, n.views, n.score,'
        .' n.ratings, n.status, n.version, n.created, n.updated, n.published, n.expires';

    # The columns of a stored resource in the order of its table
    private const ASSETS = 'id, nid, kind, role, src, name, title, intro, mime, size, width, height, duration, hits, reported, ruid, sort, created, updated';

    private Database $db;
    private NodeContext $ctx;
    private Field $fld;
    private ?Point $pnt;
    private ?NodeExtension $ext;
    private NodeQuery $query;

    # Keep the database, the request context, the shared field system, the shared points of the operations that reward and the extension of the type a material write serves
    public function __construct(Database $db, NodeContext $context, Field $field, ?Point $point = null, ?NodeExtension $ext = null) {
        $this->db = $db;
        $this->ctx = $context;
        $this->fld = $field;
        $this->pnt = $point;
        $this->ext = $ext;
        $this->query = (new NodeQuery($db, $context, $field))->setNodeExtension($ext);
    }

    # The refusal of an input, with the path of the first error for the log
    private function getInvalid(string $path): NodeException {
        return new NodeException('Invalid node input: '.$path, NodeException::INVALID);
    }

    # A storage failure with the last driver error as previous
    private function getStorage(string $text): NodeException {
        return new NodeException($text, NodeException::STORAGE, $this->db->laste);
    }

    # A refusal of the context
    private function getDenied(string $text): NodeException {
        return new NodeException($text, NodeException::DENIED);
    }

    # A material or a related row that does not exist
    private function getMissing(string $text): NodeException {
        return new NodeException($text, NodeException::NOTFOUND);
    }

    # Run one statement; a statement the server refused is a storage failure
    private function getQueryRes(string $sql, array $pars = []): PDOStatement {
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) throw $this->getStorage('A node write failed');
        return $res;
    }

    # Count rows with one statement
    private function getRowCount(string $sql, array $pars): int {
        return intval($this->getQueryRes($sql, $pars)->fetchColumn());
    }

    # Whether an array has exactly the given keys, in any order
    private function checkKeys(array $arr, array $keys): bool {
        $have = array_keys($arr);
        sort($have);
        sort($keys);
        return $have === $keys;
    }

    # Bind a list of values as named values unique to the prefix and answer the placeholders of an IN list
    private function getInList(array $ids, string $pre, array &$pars): string {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[] = ':'.$pre.$i;
            $pars[$pre.$i] = $id;
        }
        return implode(', ', $out);
    }

    # Refuse every type operation to a context that neither manages Node nor is the main administrator
    private function checkManage(): void {
        if (!$this->ctx->manage && !$this->ctx->super) throw $this->getDenied('The context does not manage node types');
    }

    # Whether a label is plain text without markup or the name of a defined language constant, within the length, and empty only where that is allowed
    private function checkLabel(string $text, int $max, bool $empty): bool {
        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $max) return false;
        if ($text === '') return $empty;
        if ($text[0] === '_') return preg_match('/^_[A-Z][A-Z0-9_]*$/D', $text) === 1 && defined($text);
        return strip_tags($text) === $text && !preg_match('/[\x00-\x1F\x7F]/', $text);
    }

    # Refuse an operation whose request loaded other global Node settings than the ones under the lock, because the settings check reads the loaded ones
    private function checkBaseNode(array $base): void {
        global $conf;
        $now = $base['node'];
        $was = $conf['node'] ?? [];
        unset($now['types'], $was['types']);
        if (!$now || $now !== $was) throw new NodeException('The global node settings changed during the request', NodeException::CONFLICT);
    }

    # Refuse a new name outside the grammar, a reserved name, a module name other than the nine replacements, a name a shared area already carries,
    # a name the upload root holds in another case, and a name whose categories are still there from an earlier owner
    private function checkNewName(string $name, array $base): void {
        global $conf;
        if (!preg_match(self::NAME, $name) || in_array($name, self::RESERVED, true)) throw $this->getInvalid('name');
        $module = isset($conf['modules'][$name]) || is_dir(BASE_DIR.'/modules/'.$name);
        if ($module && !in_array($name, self::SWAPS, true)) throw $this->getInvalid('name');
        $used = isset($base['node']['types'][$name]) || isset($base['fields']['node'][$name]) || isset($base['uploads'][$name]) || isset($base['ratings']['node.'.$name]);
        if ($used) throw $this->getInvalid('name');
        $list = scandir(UPLOADS_DIR);
        if ($list === false) throw $this->getStorage('The upload root cannot be listed');
        foreach ($list as $one) if ($one !== $name && strtolower($one) === $name) throw $this->getInvalid('name');
        if ($this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_categories WHERE modul = :name', ['name' => $name])) throw $this->getInvalid('categories');
    }

    # The guard files of an upload directory by name with their exact bytes, as the file layer knows them; a release without the index of the upload root is broken
    private function getGuardList(): array {
        require_once BASE_DIR.'/core/classes/filemanager.php';
        $list = FileManager::getGuardFiles();
        if (in_array('', $list, true)) throw $this->getStorage('The guard file of the upload root is missing');
        return $list;
    }

    # Whether a directory holds anything but guard files: any other file, a guard name with other bytes, a link or a place that cannot be listed counts as user data
    private function checkUserFiles(string $dir, array $guards): bool {
        $list = scandir($dir);
        if ($list === false) return true;
        foreach ($list as $one) {
            if ($one === '.' || $one === '..') continue;
            $path = $dir.'/'.$one;
            if (is_link($path)) return true;
            if (is_dir($path)) {
                if ($this->checkUserFiles($path, $guards)) return true;
            } elseif (!isset($guards[$one]) || file_get_contents($path) !== $guards[$one]) {
                return true;
            }
        }
        return false;
    }

    # Write every guard file a type directory lacks; a guard name holding other bytes or a link leaves the directory unconfirmed
    private function setTypeGuards(string $dir): void {
        foreach ($this->getGuardList() as $one => $text) {
            $path = $dir.'/'.$one;
            if (!file_exists($path) && file_put_contents($path, $text) !== strlen($text)) throw $this->getStorage('The guard of the type cannot be written');
            if (is_link($path) || file_get_contents($path) !== $text) throw $this->getInvalid('directory');
        }
    }

    # Prepare the file area of a new type: a directory holding any user file or in an unsafe state is refused, a missing one is created with its guards before any user file
    private function setTypeRoot(string $name): void {
        $dir = UPLOADS_DIR.'/'.$name;
        $guards = $this->getGuardList();
        if (is_link($dir) || (file_exists($dir) && !is_dir($dir))) throw $this->getInvalid('directory');
        if (is_dir($dir) && $this->checkUserFiles($dir, $guards)) throw $this->getInvalid('directory');
        if (!is_dir($dir) && !mkdir($dir, 0755) && !is_dir($dir)) throw $this->getStorage('The directory of the type cannot be created');
        $this->setTypeGuards($dir);
    }

    # Confirm that the web server itself refuses the directory of a type before it goes public: the guards are completed, then the site asks for its own guard page
    # Only a first answer of 403 or 404 confirms the refusal; a served page, a redirect or a failed request leave the type off, because NOD-206 has no direct mode at all
    private function checkTypeGuard(string $name): void {
        global $conf;
        $dir = UPLOADS_DIR.'/'.$name;
        if (is_link($dir) || !is_dir($dir)) throw $this->getInvalid('directory');
        $this->setTypeGuards($dir);
        $base = rtrim((string)($conf['homeurl'] ?? ''), '/');
        $res = preg_match('#^https?://#i', $base) ? getSchedulerFetch($base.'/uploads/'.rawurlencode($name).'/index.html') : ['code' => 0];
        if (!in_array($res['code'], [403, 404], true)) throw $this->getInvalid('directory');
    }

    # The named pieces of a stored upload rule string, whole limits as integers; a string that is not twelve pieces answers an empty array
    private function getUploadPieces(mixed $text): array {
        $part = is_string($text) ? explode('|', $text) : [];
        if (count($part) !== count(NodeQuery::UPLOAD)) return [];
        $out = [];
        foreach (NodeQuery::UPLOAD as $i => $key) $out[$key] = ($i > 0 && preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $part[$i])) ? intval($part[$i]) : $part[$i];
        return $out;
    }

    # Check the twelve named pieces of an upload rule and answer the stored string: supported extensions without repeats, whole limits and the two switches of the uploads screen
    private function filterUploadRule(array $rule): string {
        if (!$this->checkKeys($rule, NodeQuery::UPLOAD)) throw $this->getInvalid('uploads');
        $exts = $rule['extensions'];
        $list = (is_string($exts) && preg_match('/^[a-z0-9]+(?:,[a-z0-9]+)*$/D', $exts)) ? explode(',', $exts) : [];
        if (!$list || count(array_unique($list)) !== count($list) || array_diff($list, getUploadService()::getSupportedTypes())) throw $this->getInvalid('uploads.extensions');
        foreach (array_slice(NodeQuery::UPLOAD, 1) as $key) {
            $top = in_array($key, ['userupload', 'guestupload'], true) ? 1 : self::UPMAX;
            if (!is_int($rule[$key]) || $rule[$key] < 0 || $rule[$key] > $top) throw $this->getInvalid('uploads.'.$key);
        }
        return setUploadRuleData($rule);
    }

    # Check the four keys of a rating rule in the stored string form and answer them in the canonical order
    private function filterRateRule(array $rule): array {
        if (!$this->checkKeys($rule, array_keys(self::RATE))) throw $this->getInvalid('rating');
        foreach (['active', 'detail', 'guests'] as $key) if (!in_array($rule[$key], ['0', '1'], true)) throw $this->getInvalid('rating.'.$key);
        $max = (string)(intdiv(PHP_INT_MAX, 86400) * 86400);
        $per = $rule['period'];
        $good = is_string($per) && preg_match('/^(?:0|[1-9][0-9]{0,18})$/D', $per);
        if (!$good || strlen($per) > strlen($max) || (strlen($per) === strlen($max) && strcmp($per, $max) > 0)) throw $this->getInvalid('rating.period');
        return ['active' => $rule['active'], 'period' => $per, 'detail' => $rule['detail'], 'guests' => $rule['guests']];
    }

    # The stored differences of an effective map from its defaults: a map is compared key by key, a list or a value whole, and an empty section without default is left out
    private function getMapDiff(array $full, array $base): array {
        $out = [];
        foreach ($full as $key => $val) {
            $def = $base[$key] ?? null;
            if (is_array($val) && is_array($def) && $def !== [] && !array_is_list($def) && ($val === [] || !array_is_list($val))) {
                $sub = $this->getMapDiff($val, $def);
                if ($sub !== []) $out[$key] = $sub;
            } elseif ($val !== $def && !($def === null && $val === [])) {
                $out[$key] = $val;
            }
        }
        return $out;
    }

    # The stored form of effective settings: the differences from the defaults, each role with only the keys that differ from the role defaults besides its three required ones
    private function getStoredSettings(array $set, array $defs): array {
        foreach ($set['assets'] as $role => $one) {
            $set['assets'][$role] = array_filter($one, fn($v, $k) => in_array($k, ['title', 'mode', 'max'], true) || $v !== NodeQuery::ROLEDEF[$k], ARRAY_FILTER_USE_BOTH);
        }
        return $this->getMapDiff($set, $defs);
    }

    # Check the whole input of a type against the fresh areas and answer the effective settings and what the package stores: the section with the version, fields, uploads, rating
    # Empty upload and rating rules are the defaults on create only; the workflow groups must exist and every label constant of the type and its roles must be defined
    private function getTypeData(NodeTypeInput $input, array $base, bool $new, int $ver): array {
        if (!$this->checkLabel($input->title, 100, false)) throw $this->getInvalid('title');
        if (!$this->checkLabel($input->intro, 1000, true)) throw $this->getInvalid('intro');
        if ($input->ext !== '' && !preg_match(self::NAME, $input->ext)) throw $this->getInvalid('ext');
        if ($input->sort < 0 || $input->sort > self::MAXINT) throw $this->getInvalid('sort');
        try {
            $fields = $this->fld->filterFieldList($input->fields);
        } catch (InvalidArgumentException $err) {
            throw $this->getInvalid('fields.'.$err->getMessage());
        }
        $set = $this->query->filterNodeSettings($input->ext, $input->settings, $fields);
        $gids = array_values(array_unique(array_merge($set['workflow']['groups'], $set['workflow']['publish'])));
        if ($gids) {
            $pars = [];
            $sql = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_groups WHERE id IN ('.$this->getInList($gids, 'g', $pars).')';
            if ($this->getRowCount($sql, $pars) !== count($gids)) throw $this->getInvalid('workflow.groups');
        }
        foreach ($set['assets'] as $role => $one) {
            if (!$this->checkLabel($one['title'], 255, false)) throw $this->getInvalid('assets.'.$role.'.title');
            if (!$this->checkLabel($one['intro'], 1000, true)) throw $this->getInvalid('assets.'.$role.'.intro');
        }
        $stored = $this->getStoredSettings($set, $base['node']['defaults']);
        if ($this->query->filterNodeSettings($input->ext, $stored, $fields) !== $set) throw $this->getStorage('The stored settings do not reproduce the effective ones');
        $up = ($new && $input->uploads === []) ? $this->getUploadPieces($base['uploads']['all'] ?? null) : $input->uploads;
        return [
            'set' => $set,
            'node' => ['version' => $ver] + $stored,
            'fields' => $fields,
            'uploads' => $this->filterUploadRule($up),
            'rating' => ($new && $input->rating === []) ? self::RATE : $this->filterRateRule($input->rating),
        ];
    }

    # Refuse roles that would strand stored resources of the type: a removed role that still has resources, and a link role over local files or repeated addresses
    # The addresses are compared byte for byte, because the column sorts without case and the rule of the link mode is the exact result of the shared check
    private function checkTypeAssets(int $id, array $roles): void {
        $from = ' FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE n.tid = :id';
        foreach ($this->getQueryRes('SELECT DISTINCT a.role'.$from, ['id' => $id])->fetchAll(PDO::FETCH_COLUMN) as $role) {
            if (!isset($roles[$role])) throw $this->getInvalid('assets.'.$role);
        }
        foreach ($roles as $role => $def) {
            if ($def['mode'] !== 'link') continue;
            $sql = 'SELECT COUNT(*) AS num, COUNT(DISTINCT CAST(a.src AS BINARY)) AS uniq,'
                .' COALESCE(SUM(a.src NOT LIKE \'http://%\' AND a.src NOT LIKE \'https://%\'), 0) AS loc'.$from.' AND a.role = :role';
            $row = $this->getQueryRes($sql, ['id' => $id, 'role' => $role])->fetch(PDO::FETCH_ASSOC);
            if (intval($row['num']) !== intval($row['uniq']) || intval($row['loc']) > 0) throw $this->getInvalid('assets.'.$role.'.mode');
        }
    }

    # The input a stored type would be saved with, rebuilt from its row and the fresh areas, so that an activation checks exactly what is stored
    private function getStoredInput(array $row, array $base, string $name): NodeTypeInput {
        $sect = $base['node']['types'][$name];
        $defs = $base['fields']['node'][$name] ?? [];
        $rate = $base['ratings']['node.'.$name] ?? [];
        unset($sect['version']);
        if (!is_array($defs)) throw $this->getInvalid('fields');
        if (!is_array($rate)) throw $this->getInvalid('rating');
        return new NodeTypeInput((string)$row['title'], (string)$row['intro'], (string)$row['ext'], intval($row['sort']), $sect, $defs,
            $this->getUploadPieces($base['uploads'][$name] ?? null), $rate);
    }

    # The package of the four shared areas with the sections of one type replaced, or removed when no data is given; the lists keep a stable order
    private function getTypePack(array $base, string $name, ?array $data): array {
        unset($base['node']['types'][$name], $base['fields']['node'][$name], $base['uploads'][$name], $base['ratings']['node.'.$name]);
        if ($data !== null) {
            $base['node']['types'][$name] = $data['node'];
            if ($data['fields']) $base['fields']['node'][$name] = $data['fields'];
            $base['uploads'][$name] = $data['uploads'];
            $base['ratings']['node.'.$name] = $data['rating'];
        }
        $base['node']['types'] ??= [];
        ksort($base['node']['types']);
        ksort($base['uploads']);
        ksort($base['ratings']);
        if (($base['fields']['node'] ?? null) === []) unset($base['fields']['node']);
        if (isset($base['fields']['node'])) ksort($base['fields']['node']);
        return $base;
    }

    # Read the row of one type by its unique name with a locking read inside the open transaction, or null when it does not exist
    private function getTypeRow(string $name): ?array {
        $row = $this->getQueryRes('SELECT id, name, title, intro, ext, active, sort, version FROM '.PREFIX_DB.'_node_types WHERE name = :name FOR UPDATE', ['name' => $name])
            ->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    # Refuse a missing type, a stale expected version, and a type whose stored configuration does not carry the version of its row
    private function checkTypeRow(?array $row, int $version, array $base, string $name): void {
        if ($row === null) throw $this->getMissing('The node type does not exist');
        if (intval($row['version']) !== $version) throw new NodeException('The expected type version is stale', NodeException::CONFLICT);
        if (($base['node']['types'][$name]['version'] ?? null) !== $version) throw $this->getStorage('The configuration does not carry the type at its version');
    }

    # Run one type operation as the closure of the shared configuration writer in the order of the lock protocol, then finish the cache invalidation
    # The work gets the fresh areas and the locked row and answers the package and the proof; a refusal rolls back, an unknown commit or a failed bump keeps the guard
    private function setTypeWrite(string $name, Closure $work): void {
        $this->checkManage();
        if (!preg_match(self::NAME, $name)) throw $this->getInvalid('name');
        $state = ['fail' => null, 'guard' => false, 'lock' => false, 'res' => ''];
        $done = setConfigFile(function (array $base, Closure $save) use ($name, $work, &$state): string {
            try {
                $this->checkBaseNode($base);
                $state['lock'] = FileManager::getPathLock(UPLOADS_DIR.'/'.$name);
                if ($state['lock'] === false) throw $this->getStorage('The directory lock of the type cannot be taken');
                $state['guard'] = Cache::getWriteGuard();
                if ($state['guard'] === false) throw $this->getStorage('The cache guard cannot be taken');
                if (!$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
                [$pack, $proof] = $work($base, $this->getTypeRow($name));
                if (!$save($pack, $proof)) throw $this->getStorage('The configuration package cannot be saved');
            } catch (Throwable $err) {
                $this->db->setSqlRollback();
                $state['fail'] = ($err instanceof NodeException) ? $err : new NodeException('A node type operation failed', NodeException::STORAGE, $err);
                return $state['res'] = 'aborted';
            }
            if (!$this->db->setSqlCommit()) {
                $state['fail'] = $this->getStorage('The commit of a node type operation is uncertain');
                return $state['res'] = 'uncertain';
            }
            return $state['res'] = 'committed';
        });
        $bump = $done && Cache::addEpoch(true);
        if ($done && !$bump) Logger::addSite('error', 'Node: the cache generation could not be raised after a type operation', ['name' => $name]);
        if ($state['guard'] !== false && ($bump || $state['res'] === 'aborted')) Cache::deleteWriteGuard($state['guard']);
        if ($state['lock'] !== false) FileManager::deletePathLock($state['lock']);
        if (!$done) throw $state['fail'] ?? new NodeException('The configuration of a node type cannot be written', NodeException::STORAGE);
    }

    # Read the type an operation has just published through a reader of its own, which takes up the new snapshot of the configuration
    private function getTypeResult(string $name): NodeType {
        $type = (new NodeQuery($this->db, $this->ctx, $this->fld))->getNodeType($name);
        if ($type === null) throw $this->getStorage('The written type cannot be read back');
        return $type;
    }

    # Create a disabled type of version 1 under a new public name: its row, its sections in the four shared areas and its upload directory with the guard
    # An empty upload rule copies the rule all and an empty rating rule is the new rule; an existing directory is taken only when it holds nothing but guard files
    public function addNodeType(string $name, NodeTypeInput $input): NodeType {
        $this->setTypeWrite($name, function (array $base, ?array $row) use ($name, $input): array {
            if ($row !== null) throw $this->getInvalid('name');
            $this->checkNewName($name, $base);
            $data = $this->getTypeData($input, $base, true, 1);
            $this->setTypeRoot($name);
            $sql = 'INSERT INTO '.PREFIX_DB.'_node_types (name, title, intro, ext, active, sort, version, created, updated)'
                .' VALUES (:name, :title, :intro, :ext, 0, :sort, 1, NOW(), NOW())';
            $this->getQueryRes($sql, ['name' => $name, 'title' => $input->title, 'intro' => $input->intro, 'ext' => $input->ext, 'sort' => $input->sort]);
            $id = intval($this->db->getSqlLastId());
            return [$this->getTypePack($base, $name, $data), ['kind' => 'add', 'name' => $name, 'id' => $id, 'old' => 0, 'new' => 1]];
        });
        return $this->getTypeResult($name);
    }

    # Change the data of a type at the expected version: the extension key only while the type is disabled and has no material, every rule complete and checked again,
    # and no role change that would strand the stored resources of its materials
    public function updateNodeType(string $name, NodeTypeInput $input, int $version): NodeType {
        $this->setTypeWrite($name, function (array $base, ?array $row) use ($name, $input, $version): array {
            $this->checkTypeRow($row, $version, $base, $name);
            $id = intval($row['id']);
            if ($input->ext !== (string)$row['ext']) {
                if ($row['active']) throw $this->getInvalid('ext');
                if ($this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE tid = :id', ['id' => $id])) throw $this->getInvalid('ext');
            }
            $data = $this->getTypeData($input, $base, false, $version + 1);
            $this->checkTypeAssets($id, $data['set']['assets']);
            $sql = 'UPDATE '.PREFIX_DB.'_node_types SET title = :title, intro = :intro, ext = :ext, sort = :sort, version = version + 1, updated = NOW() WHERE id = :id';
            $this->getQueryRes($sql, ['title' => $input->title, 'intro' => $input->intro, 'ext' => $input->ext, 'sort' => $input->sort, 'id' => $id]);
            return [$this->getTypePack($base, $name, $data), ['kind' => 'update', 'name' => $name, 'id' => $id, 'old' => $version, 'new' => $version + 1]];
        });
        return $this->getTypeResult($name);
    }

    # Switch a type on or off at the expected version; switching on repeats the whole check of what is stored and needs the writable directory of the type
    # Switching an inactive type on first confirms the refusal of its directory by the web server, a network request that runs before every lock
    # Asking for the state the type already has is no change: it succeeds without a write and keeps the version
    public function updateNodeTypeStatus(string $name, bool $active, int $version): NodeType {
        $this->checkManage();
        if ($active && preg_match(self::NAME, $name) && !((new NodeQuery($this->db, $this->ctx, $this->fld))->getNodeType($name)?->active ?? false)) $this->checkTypeGuard($name);
        $this->setTypeWrite($name, function (array $base, ?array $row) use ($name, $active, $version): array {
            $this->checkTypeRow($row, $version, $base, $name);
            $id = intval($row['id']);
            if ((bool)$row['active'] === $active) return [$base, []];
            if ($active) {
                $this->getTypeData($this->getStoredInput($row, $base, $name), $base, false, $version + 1);
                $dir = UPLOADS_DIR.'/'.$name;
                if (is_link($dir) || !is_dir($dir) || !is_writable($dir)) throw $this->getInvalid('directory');
            }
            $sql = 'UPDATE '.PREFIX_DB.'_node_types SET active = :active, version = version + 1, updated = NOW() WHERE id = :id';
            $this->getQueryRes($sql, ['active' => $active ? 1 : 0, 'id' => $id]);
            $base['node']['types'][$name]['version'] = $version + 1;
            return [$base, ['kind' => 'status', 'name' => $name, 'id' => $id, 'old' => $version, 'new' => $version + 1]];
        });
        return $this->getTypeResult($name);
    }

    # Delete a type at the expected version when it has no material in any state, no category and no user file; its directory and guard stay
    # The sections of the type leave all four shared areas, and the moderation right node-<name> leaves every administrator, so a later type of that name inherits nothing
    public function deleteNodeType(string $name, int $version): void {
        $this->setTypeWrite($name, function (array $base, ?array $row) use ($name, $version): array {
            $this->checkTypeRow($row, $version, $base, $name);
            $id = intval($row['id']);
            if ($this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE tid = :id', ['id' => $id])) throw $this->getInvalid('nodes');
            if ($this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_categories WHERE modul = :name', ['name' => $name])) throw $this->getInvalid('categories');
            $dir = UPLOADS_DIR.'/'.$name;
            if (file_exists($dir) && (is_link($dir) || !is_dir($dir) || $this->checkUserFiles($dir, $this->getGuardList()))) throw $this->getInvalid('directory');
            $key = 'node-'.$name;
            $rows = $this->getQueryRes('SELECT id, modules FROM '.PREFIX_DB.'_admins WHERE modules LIKE :key', ['key' => '%'.$key.'%'])->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $one) {
                $mods = getAdminModuleNames((string)$one['modules']);
                if (!in_array($key, $mods, true)) continue;
                $keep = implode(',', array_values(array_diff($mods, [$key])));
                $this->getQueryRes('UPDATE '.PREFIX_DB.'_admins SET modules = :mods WHERE id = :id', ['mods' => $keep, 'id' => intval($one['id'])]);
            }
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_types WHERE id = :id', ['id' => $id]);
            return [$this->getTypePack($base, $name, null), ['kind' => 'delete', 'name' => $name, 'id' => $id, 'old' => $version, 'new' => 0]];
        });
    }

    # Create a new disabled type from an export of the slaed.node format: the whole file is decoded and its shape checked before anything is written
    # A non-empty name replaces the name of the file; cloning is an export imported under a new name and a profile is a shipped export, both through this one path
    public function addNodeTypeImport(string $json, string $name = ''): NodeType {
        $this->checkManage();
        if (strlen($json) > self::JSONMAX) throw $this->getInvalid('json');
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->getInvalid('json');
        }
        if (!is_array($data) || !$this->checkKeys($data, ['format', 'type', 'version']) || $data['format'] !== NodeQuery::FORMAT || $data['version'] !== 1) {
            throw $this->getInvalid('format');
        }
        $type = $data['type'];
        if (!is_array($type) || !$this->checkKeys($type, NodeQuery::EXPORT)) throw $this->getInvalid('type');
        foreach (['name', 'title', 'intro', 'ext'] as $key) if (!is_string($type[$key])) throw $this->getInvalid('type.'.$key);
        if (!is_int($type['sort'])) throw $this->getInvalid('type.sort');
        foreach (['settings', 'fields', 'uploads', 'rating'] as $key) {
            if (!is_array($type[$key]) || ($type[$key] !== [] && array_is_list($type[$key]))) throw $this->getInvalid('type.'.$key);
        }
        $input = new NodeTypeInput($type['title'], $type['intro'], $type['ext'], $type['sort'], $type['settings'], $type['fields'], $type['uploads'], $type['rating']);
        return $this->addNodeType($name !== '' ? $name : $type['name'], $input);
    }

    # The shared points an operation that rewards or compensates needs; refused before the first statement when the instance was built without them
    private function getPoint(): Point {
        if ($this->pnt === null) throw $this->getInvalid('point');
        return $this->pnt;
    }

    # Register one reward of an action for a registered recipient; a refusal Point already logged never blocks the action, a lost transaction does
    private function addNodePoint(string $action, NodeType $type, string $source, int $uid): void {
        if ($uid < 1) return;
        try {
            $this->getPoint()->addEvent($action, 'node.'.$type->name, $source, $uid);
        } catch (RuntimeException $err) {
            throw new NodeException('The points of a node action are lost', NodeException::STORAGE, $err);
        }
    }

    # Whether the context moderates the type: the main administrator or a moderator named by the type; managing Node gives no right on the materials of a type
    private function checkModer(NodeType $type): bool {
        return $this->ctx->super || in_array($type->name, $this->ctx->mods, true);
    }

    # Refuse a material write whose extension does not match its type: none for a standard type, exactly the class the closed factory makes for a registered key
    private function checkExtType(NodeType $type): void {
        if ($type->ext === '') {
            if ($this->ext !== null) throw $this->getInvalid('extension');
            return;
        }
        require_once __DIR__.'/ext/load.php';
        if ($this->ext === null || get_class($this->ext) !== get_class(getNodeExtension($type->ext, $this->db, $this->ctx))) throw $this->getInvalid('extension');
    }

    # The two system limits of the loaded Node configuration a material write is bound by
    private function getLimits(): array {
        global $conf;
        $out = [];
        foreach (['maxassets', 'syncbatch'] as $key) {
            $val = $conf['node']['limits'][$key] ?? null;
            if (!is_int($val) || $val < 1) throw $this->getStorage('The limits of the Node configuration are broken');
            $out[$key] = $val;
        }
        return $out;
    }

    # Whether a text is valid UTF-8 without control characters within the length, and empty only where that is allowed
    private function checkText(string $text, int $max, bool $empty): bool {
        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $max || preg_match('/[\x00-\x1F\x7F]/', $text)) return false;
        return $text !== '' || $empty;
    }

    # Whether a string is a canonical database date and time of the calendar
    private function checkDate(string $text): bool {
        if (!preg_match(self::DATE, $text, $hit)) return false;
        return checkdate(intval($hit[2]), intval($hit[3]), intval($hit[1])) && intval($hit[4]) < 24 && intval($hit[5]) < 60 && intval($hit[6]) < 60;
    }

    # Whether a category right string grants the context: an empty string never, a group list by intersection with the groups, otherwise the level 0 of a guest or 1 of a user
    private function checkCatGrant(string $perm): bool {
        [$lvl, $ids] = array_pad(explode('|', $perm, 2), 2, '');
        if ($perm === '' || !ctype_digit($lvl)) return false;
        $gids = array_values(array_filter(array_map('intval', explode(',', $ids)), fn($v) => $v > 0));
        if ($gids) return (bool)array_intersect($gids, $this->ctx->groups);
        return intval($lvl) <= ($this->ctx->uid > 0 ? 1 : 0);
    }

    # Check a list of unique positive ids of at most the given size and answer it sorted
    private function getIdSet(array $ids, int $max, string $path): array {
        if (!array_is_list($ids) || count($ids) > $max) throw $this->getInvalid($path);
        foreach ($ids as $id) if (!is_int($id) || $id < 1 || $id > self::MAXINT) throw $this->getInvalid($path);
        if (count(array_unique($ids)) !== count($ids)) throw $this->getInvalid($path);
        sort($ids);
        return $ids;
    }

    # The form of a resource source: link for an absolute http or https address without credentials, file for a safe relative path below the type root, empty for anything else
    private function getSrcForm(string $src): string {
        if ($src === '' || strlen($src) > 2048) return '';
        if (preg_match('#^https?://#', $src)) {
            $part = parse_url($src);
            $good = !preg_match('/[\x00-\x20\x7F]/', $src) && is_array($part) && ($part['host'] ?? '') !== '' && !isset($part['user']) && !isset($part['pass']);
            return $good ? 'link' : '';
        }
        $good = preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#D', $src) && !preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $src) && !str_starts_with($src, 'thumb/');
        return $good ? 'file' : '';
    }

    # Whether a visitor who does not moderate the type may use its public form at all: an active type with public submission and a workflow that admits the visitor
    private function checkFlowAccess(NodeType $type): bool {
        $flow = $type->settings['workflow'];
        $user = $this->ctx->uid > 0;
        $pass = match ($flow['access']) {
            'all' => true,
            'user' => $user,
            default => $user && array_intersect($flow['groups'], $this->ctx->groups) !== [],
        };
        return $type->active && $type->settings['features']['submit'] && $pass;
    }

    # Refuse a create the context may not do: a background context always, a state other than draft, pending and published, and for anyone who does not moderate the type
    # a disabled type, a type without public submission, a visitor the workflow does not admit, a draft, a pending material without moderation,
    # and a direct publication without the right
    private function checkNewNode(NodeType $type, NodeStatus $status): void {
        if (!in_array($status, [NodeStatus::Draft, NodeStatus::Pending, NodeStatus::Published], true)) throw $this->getInvalid('status');
        if ($this->ctx->task) throw $this->getDenied('A background context writes no material');
        $this->checkExtType($type);
        if ($this->checkModer($type)) return;
        $flow = $type->settings['workflow'];
        $feat = $type->settings['features'];
        $user = $this->ctx->uid > 0;
        if (!$this->checkFlowAccess($type) || $status === NodeStatus::Draft) throw $this->getDenied('The context may not submit this material');
        if ($status === NodeStatus::Pending && !$feat['moderation']) throw $this->getInvalid('status');
        $direct = $user && array_intersect($flow['publish'], $this->ctx->groups) !== [];
        if ($status === NodeStatus::Published && $feat['moderation'] && !$direct) throw $this->getDenied('The context may not publish directly');
    }

    # The canonical field values of a material: defaults fill the empty active fields of a new material, required fields bind a pending or published one,
    # the stored values of inactive fields survive a change, and names without a definition leave with it
    private function getFieldData(NodeType $type, array $vals, NodeStatus $status, ?Node $old): array {
        $defs = $type->fields;
        foreach ($old ? [] : $defs as $name => $def) {
            if ($def['active'] && in_array($vals[$name] ?? null, [null, '', []], true) && !in_array($def['default'], [null, '', []], true)) $vals[$name] = $def['default'];
        }
        $errs = $this->fld->checkFieldValues($defs, $vals, in_array($status, [NodeStatus::Pending, NodeStatus::Published], true));
        if ($errs) throw $this->getInvalid('fields.'.array_key_first($errs).'.'.reset($errs));
        try {
            $out = $this->fld->filterFieldValues($defs, $vals);
        } catch (InvalidArgumentException $err) {
            throw $this->getInvalid('fields.'.$err->getMessage());
        }
        foreach ($old?->fields ?? [] as $name => $val) if (isset($defs[$name]) && !$defs[$name]['active']) $out[$name] = $val;
        ksort($out);
        return $out;
    }

    # Check the full set of relations: the closed shape, a registered kind whose feature the type has, no repeat, one parent at most, and at most the sync batch of rows
    private function getRelData(NodeType $type, array $rels, int $max): array {
        if (!array_is_list($rels) || count($rels) > $max) throw $this->getInvalid('rels');
        $out = [];
        $seen = [];
        foreach ($rels as $i => $one) {
            $path = 'rels.'.$i;
            if (!is_array($one) || !$this->checkKeys($one, self::RELKEYS)) throw $this->getInvalid($path);
            $good = is_int($one['rid']) && $one['rid'] > 0 && $one['rid'] <= self::MAXINT && is_string($one['type']) && isset(self::RELS[$one['type']]);
            if (!$good || !is_int($one['sort']) || $one['sort'] < 0 || $one['sort'] > self::MAXINT) throw $this->getInvalid($path);
            if (!$type->settings['features'][self::RELS[$one['type']]]) throw $this->getInvalid($path.'.type');
            $key = ($one['type'] === 'parent') ? 'parent' : 'related:'.$one['rid'];
            if (isset($seen[$key])) throw $this->getInvalid($path);
            $seen[$key] = true;
            $out[] = ['rid' => $one['rid'], 'type' => $one['type'], 'sort' => $one['sort']];
        }
        usort($out, fn($a, $b) => [$a['type'], $a['sort'], $a['rid']] <=> [$b['type'], $b['sort'], $b['rid']]);
        return $out;
    }

    # Check the full set of resources against the roles of the type: the closed shape, own existing ids without repeats, a registered role, a kind its mode shows, a source
    # of the allowed form, the texts within their columns, the limit of each role and of the material, and the minimum of every active role for a pending or published one
    # A resource the input keeps unchanged in kind, role and source keeps its metadata and is no new binding; every other local source is resolved under the root lock
    private function getAssetData(NodeType $type, array $list, NodeStatus $status, ?Node $old, int $max): array {
        if (!array_is_list($list) || count($list) > $max) throw $this->getInvalid('assets');
        $roles = $type->settings['assets'];
        $had = [];
        foreach ($old?->assets ?? [] as $one) $had[$one->id] = $one;
        $out = [];
        $ids = [];
        $count = [];
        foreach ($list as $i => $one) {
            $path = 'assets.'.$i;
            if (!is_array($one) || !$this->checkKeys($one, self::ASSETKEYS)) throw $this->getInvalid($path);
            $id = $one['id'];
            if ($id !== null && (!is_int($id) || !isset($had[$id]) || isset($ids[$id]))) throw $this->getInvalid($path.'.id');
            if ($id !== null) $ids[$id] = true;
            foreach (['kind', 'role', 'src', 'name', 'title', 'intro'] as $key) if (!is_string($one[$key])) throw $this->getInvalid($path.'.'.$key);
            if (!is_int($one['sort']) || $one['sort'] < 0 || $one['sort'] > self::MAXINT) throw $this->getInvalid($path.'.sort');
            $def = $roles[$one['role']] ?? null;
            if ($def === null) throw $this->getInvalid($path.'.role');
            $prev = ($id === null) ? null : $had[$id];
            $same = $prev !== null && $prev->kind === $one['kind'] && $prev->role === $one['role'] && $prev->src === $one['src'];
            $form = $this->getSrcForm($one['src']);
            if ($form === '') throw $this->getInvalid($path.'.src');
            if (!$same) {
                $allow = array_intersect(NodeQuery::RMODES[$def['mode']], $def['kinds'] ?: NodeQuery::KINDS);
                if (!$def['active'] || !in_array($one['kind'], $allow, true)) throw $this->getInvalid($path.'.kind');
                if (($form === 'link') ? !$def['canlink'] : $def['mode'] === 'link') throw $this->getInvalid($path.'.src');
            }
            if (!$this->checkText($one['name'], 255, true)) throw $this->getInvalid($path.'.name');
            if (!$this->checkText($one['title'], 100, true)) throw $this->getInvalid($path.'.title');
            $intro = filterTrustedTags($one['intro'], $this->ctx->super);
            if (strlen($intro) > 65535 || !mb_check_encoding($intro, 'UTF-8')) throw $this->getInvalid($path.'.intro');
            $count[$one['role']] = ($count[$one['role']] ?? 0) + 1;
            $meta = $prev ? [$prev->mime, $prev->size, $prev->width, $prev->height, $prev->duration] : [null, null, null, null, null];
            $out[] = ['id' => $id, 'kind' => $one['kind'], 'role' => $one['role'], 'src' => $one['src'], 'name' => $one['name'], 'title' => $one['title'],
                'intro' => $intro, 'sort' => $one['sort'], 'link' => $form === 'link', 'new' => !$same, 'meta' => $meta];
        }
        foreach ($count as $role => $num) if ($num > $roles[$role]['max']) throw $this->getInvalid('assets.'.$role.'.max');
        if (in_array($status, [NodeStatus::Pending, NodeStatus::Published], true)) {
            foreach ($roles as $role => $def) if ($def['active'] && ($count[$role] ?? 0) < $def['min']) throw $this->getInvalid('assets.'.$role.'.min');
        }
        return $out;
    }

    # Check the whole input of a material against its type before any lock and answer the canonical values the write stores
    # The trusted tags leave every text but that of the main administrator, who alone holds the right to author them, before anything else reads it
    # A switch that is off allows only its empty value or a poll the material already carries, a visitor who does not moderate the type sets no poll, home, pin or date,
    # the texts fit their columns, the fields, relations and resources are full sets of the closed shapes, and the extension canonicalizes its own data;
    # rows, the clock and files follow under the locks
    private function getInputData(NodeType $type, NodeInput $in, NodeStatus $status, ?Node $old): array {
        $feat = $type->settings['features'];
        $lims = $this->getLimits();
        $moder = $this->checkModer($type);
        $uid = $old ? $old->uid : $this->ctx->uid;
        if ($in->cid < 0 || $in->cid > self::MAXINT || (!$feat['categories'] && ($in->cid || $in->cids))) throw $this->getInvalid('cid');
        $cids = array_values(array_diff($this->getIdSet($in->cids, $lims['syncbatch'], 'cids'), [$in->cid]));
        $keep = $old !== null && $in->poll === $old->poll;
        if ($in->poll < 0 || $in->poll > self::MAXINT || ($in->poll && !$keep && (!$feat['poll'] || !$moder))) throw $this->getInvalid('poll');
        if ($in->home && (!$feat['home'] || !$moder)) throw $this->getInvalid('home');
        if ($in->pinned && (!$feat['pinned'] || !$moder)) throw $this->getInvalid('pinned');
        if (!$feat['comments'] && $in->comon !== CommentMode::Disabled) throw $this->getInvalid('comon');
        if ($in->pubdate !== null && (!$moder || !$this->checkDate($in->pubdate))) throw $this->getInvalid('pubdate');
        if ($in->expires !== null && (!$moder || !$feat['schedule'] || !$this->checkDate($in->expires))) throw $this->getInvalid('expires');
        if (($uid > 0 && $in->aname !== '') || !$this->checkText($in->aname, 25, true)) throw $this->getInvalid('aname');
        $title = trim($in->title);
        if (!$this->checkText($title, 100, false)) throw $this->getInvalid('title');
        $text = ['intro' => filterTrustedTags($in->intro, $this->ctx->super), 'body' => filterTrustedTags($in->body, $this->ctx->super)];
        foreach ($text as $key => $val) {
            if (!mb_check_encoding($val, 'UTF-8') || checkEditorTextRoom($val, 'nodes.'.$key) !== '') throw $this->getInvalid($key);
        }
        if ($type->ext === '' && $in->ext !== []) throw $this->getInvalid('ext');
        $vals = $in->fields;
        array_walk_recursive($vals, fn(&$v) => $v = is_string($v) ? filterTrustedTags($v, $this->ctx->super) : $v);
        $fields = $this->getFieldData($type, $vals, $status, $old);
        $json = $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : '{}';
        if (strlen($json) > self::JSONMAX) throw $this->getInvalid('fields');
        return [
            'cid' => $in->cid, 'cids' => $cids, 'aname' => $in->aname, 'title' => $title, 'intro' => $text['intro'], 'body' => $text['body'], 'fields' => $fields, 'field' => $json,
            'poll' => $in->poll, 'home' => $in->home, 'comon' => $in->comon, 'pinned' => $in->pinned, 'pubdate' => $in->pubdate, 'expires' => $in->expires, 'pub' => null,
            'rels' => $this->getRelData($type, $in->rels, $lims['syncbatch']), 'assets' => $this->getAssetData($type, $in->assets, $status, $old, $lims['maxassets']),
            'ext' => ($type->ext === '') ? [] : $this->ext->filterNodeData($type, $in->ext, $old),
        ];
    }

    # The owner token a file of the visitor carries: the account of the context and, for a guest alone, the token of the session; a moderator needs none
    private function getFileToken(NodeType $type, bool $moder): ?string {
        return ($this->ctx->uid > 0) ? (string)$this->ctx->uid : ($moder ? null : getEditorFileOwner($type->name));
    }

    # Read one file of the type area for a new binding: a plain existing file outside thumb, no guard file, an allowed extension within the size,
    # and owned by the visitor unless the context moderates the type
    private function getFileRow(FileManager $area, string $path, array $exts, int $max, bool $moder, ?string $token, string $err): array {
        $row = $area->getFileData($path);
        if (!$row || $row['kind'] === 'dir' || isset(FileManager::getGuardFiles()[$row['name']]) || str_starts_with($row['path'], 'thumb/')) throw $this->getInvalid($err);
        if (!in_array($row['extension'], $exts, true) || ($max > 0 && $row['size'] > $max)) throw $this->getInvalid($err);
        if (!$moder && ($token === null || FileManager::getFileOwner($row['name']) !== $token)) throw $this->getInvalid($err.'.owner');
        return $row;
    }

    # The content type of a local file as the magic database names it, or null when the build cannot tell
    private function getFileMime(string $full): ?string {
        $info = class_exists('finfo') ? new finfo(FILEINFO_MIME_TYPE) : null;
        $mime = $info ? $info->file($full) : false;
        return (is_string($mime) && strlen($mime) <= 100 && preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#D', $mime)) ? $mime : null;
    }

    # Resolve every new binding of a file of the type area: an attachment name the text gains and a new local source of a resource must exist in uploads/<type>,
    # keep an allowed extension and size, and belong to the visitor unless the context moderates the type; a name the stored text already carried is checked for its form alone
    # The owner is the account of the context and, for a guest alone, the token of the session; a local source takes its canonical path and the metadata read here once
    private function checkNodeFiles(NodeType $type, array &$data, ?Node $old): void {
        $prs = new Parser();
        $list = fn(string $one, string $two): array => array_values(array_unique(array_merge($prs->getAttachList($one), $prs->getAttachList($two))));
        $names = $list($data['intro'], $data['body']);
        $fresh = array_values(array_diff($names, $old ? $list($old->intro, (string)$old->body) : []));
        $local = array_filter($data['assets'], fn($v) => $v['new'] && !$v['link']);
        if (!$names && !$local) return;
        $rule = $type->uploads;
        if (!$rule) throw $this->getInvalid('uploads');
        $exts = explode(',', $rule['extensions']);
        foreach ($names as $name) {
            $good = preg_match('/^[A-Za-z0-9_\-. ]+$/D', $name) && trim($name, '. ') !== '' && in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $exts, true);
            if (!$good) throw $this->getInvalid('attach');
        }
        if (!$fresh && !$local) return;
        require_once BASE_DIR.'/core/classes/filemanager.php';
        $area = new FileManager('editor', UPLOADS_DIR.'/'.$type->name);
        $moder = $this->checkModer($type);
        $token = $this->getFileToken($type, $moder);
        foreach ($fresh as $name) $this->getFileRow($area, $name, $exts, $rule['maxbytes'], $moder, $token, 'attach');
        foreach ($local as $i => $one) {
            $def = $type->settings['assets'][$one['role']];
            $allow = $def['extensions'] ? array_values(array_intersect($def['extensions'], $exts)) : $exts;
            $max = ($def['maxbytes'] === null) ? $rule['maxbytes'] : (($rule['maxbytes'] > 0) ? min($rule['maxbytes'], $def['maxbytes']) : $def['maxbytes']);
            $row = $this->getFileRow($area, $one['src'], $allow, $max, $moder, $token, 'assets.'.$i.'.src');
            if ($one['kind'] !== 'file' && $row['kind'] !== $one['kind']) throw $this->getInvalid('assets.'.$i.'.kind');
            $data['assets'][$i]['src'] = $row['path'];
            $data['assets'][$i]['meta'] = [$this->getFileMime(UPLOADS_DIR.'/'.$type->name.'/'.$row['path']), $row['size'], $row['width'], $row['height'], null];
        }
    }

    # Take the named lock of one poll on the connection of the write, waiting a bounded time; a timeout is a conflict and a failed statement a storage failure
    private function getPollLock(int $poll): string {
        $name = 'node.poll.'.$poll;
        $got = $this->getQueryRes('SELECT GET_LOCK(:name, :wait)', ['name' => $name, 'wait' => self::POLLWAIT])->fetchColumn();
        if ($got === null || $got === false) throw $this->getStorage('The lock of a poll cannot be taken');
        if (intval($got) !== 1) throw new NodeException('The poll is held by another write', NodeException::CONFLICT);
        return $name;
    }

    # Run one write of Node rows in the order of the lock protocol: the root lock of the type directory with the file checks, the poll locks, the cache guard, BEGIN,
    # the work, COMMIT
    # The final cache generation follows the commit and only then the guard goes; a refusal before BEGIN and a proven rollback free the guard, an unknown outcome keeps it
    private function setNodeWrite(?NodeType $root, array $polls, ?Closure $files, Closure $work): mixed {
        $lock = false;
        $held = [];
        $guard = false;
        $step = 'before';
        $res = null;
        $fail = null;
        try {
            if ($root !== null && $files !== null) {
                require_once BASE_DIR.'/core/classes/filemanager.php';
                $lock = FileManager::getPathLock(UPLOADS_DIR.'/'.$root->name);
                if ($lock === false) throw $this->getStorage('The directory lock of the type cannot be taken');
                $files();
            }
            foreach ($polls as $poll) $held[] = $this->getPollLock($poll);
            $guard = Cache::getWriteGuard();
            if ($guard === false) throw $this->getStorage('The cache guard cannot be taken');
            if (!$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
            $step = 'open';
            $res = $work();
            $step = 'unknown';
            if (!$this->db->setSqlCommit()) throw $this->getStorage('The commit of a node write is uncertain');
            $step = 'done';
        } catch (Throwable $err) {
            if ($step === 'open' && !$this->db->setSqlRollback()) $step = 'unknown';
            $fail = ($err instanceof NodeException) ? $err : new NodeException('A node write failed', NodeException::STORAGE, $err);
        }
        $bump = $step === 'done' && Cache::addEpoch(true);
        if ($step === 'done' && !$bump) Logger::addSite('error', 'Node: the cache generation could not be raised after a write', ['type' => $root?->name ?? '']);
        if ($guard !== false && ($bump || $step === 'before' || $step === 'open')) Cache::deleteWriteGuard($guard);
        foreach ($held as $name) $this->db->getSqlQuery('SELECT RELEASE_LOCK(:name)', ['name' => $name]);
        if ($lock !== false) FileManager::deletePathLock($lock);
        if ($fail !== null) throw $fail;
        return $res;
    }

    # Lock the row of the type first inside a write and answer the clock of the database; a type that is gone or, for anyone but its moderator, switched off is not found,
    # and a type changed since the request read it is a conflict, because the input was checked against the settings of the older version
    private function getTypeLock(NodeType $type): string {
        $sql = 'SELECT active, version, NOW() AS now FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE';
        $row = $this->getQueryRes($sql, ['id' => $type->id])->fetch(PDO::FETCH_ASSOC);
        if (!$row || (!$row['active'] && !$this->checkModer($type))) throw $this->getMissing('The node type does not exist');
        if (intval($row['version']) !== $type->version) throw new NodeException('The node type changed during the write', NodeException::CONFLICT);
        return (string)$row['now'];
    }

    # Walk the current parent chain upward from a new parent with locking reads: reaching the material itself is a cycle, a node seen twice a damaged tree
    private function checkTreeWalk(int $self, int $parent): void {
        $seen = [];
        for ($cur = $parent; $cur > 0;) {
            if ($cur === $self) throw $this->getInvalid('rels.parent');
            if (isset($seen[$cur])) {
                Logger::addSite('error', 'Node: the parent chain of a tree runs in a circle', ['nid' => $cur]);
                throw $this->getStorage('The tree of the type is damaged');
            }
            $seen[$cur] = true;
            $sql = 'SELECT rid FROM '.PREFIX_DB.'_node_relations WHERE nid = :id AND type = \'parent\' ORDER BY id LIMIT 1 FOR UPDATE';
            $cur = intval($this->getQueryRes($sql, ['id' => $cur])->fetchColumn());
        }
    }

    # Check what the input refers to against current rows, with locking reads inside a write, and answer the locked row of the material itself on a change:
    # the dates against the clock of the database, the categories of the type, the related materials of the type, the parent chain, the poll and the uniqueness of an external link
    # A visitor who does not moderate the type posts only into categories whose post right admits him and relates only published materials
    private function checkNodeRefs(NodeType $type, array &$data, NodeStatus $status, string $now, ?Node $old, int $self, bool $lock): ?array {
        $tail = $lock ? ' FOR UPDATE' : '';
        $moder = $this->checkModer($type);
        $pub = $data['pubdate'];
        if ($pub !== null && strcmp($pub, $now) > 0 && !$type->settings['features']['schedule']) throw $this->getInvalid('pubdate');
        $pub ??= ($status === NodeStatus::Published) ? $now : null;
        if ($data['expires'] !== null && strcmp($data['expires'], $pub ?? $now) <= 0) throw $this->getInvalid('expires');
        $data['pub'] = $pub;
        $cats = array_values(array_filter(array_merge([$data['cid']], $data['cids'])));
        sort($cats);
        if ($cats) {
            $pars = [];
            $sql = 'SELECT id, modul, ppost FROM '.PREFIX_DB.'_categories WHERE id IN ('.$this->getInList($cats, 'c', $pars).') ORDER BY id'.$tail;
            $map = array_column($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC), null, 'id');
            foreach ($cats as $cid) {
                if (($map[$cid]['modul'] ?? null) !== $type->name) throw $this->getInvalid('cid');
                if (!$moder && !$this->checkCatGrant((string)$map[$cid]['ppost'])) throw $this->getDenied('The context may not post into the category');
            }
        }
        $rids = array_column($data['rels'], 'rid');
        if (in_array($self, $rids, true)) throw $this->getInvalid('rels');
        $ids = array_values(array_unique(array_merge($self ? [$self] : [], $rids)));
        sort($ids);
        $map = [];
        if ($ids) {
            $pars = [];
            $sql = 'SELECT id, tid, status, version FROM '.PREFIX_DB.'_nodes WHERE id IN ('.$this->getInList($ids, 'n', $pars).') ORDER BY id'.$tail;
            $map = array_column($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC), null, 'id');
        }
        foreach ($rids as $rid) {
            $row = $map[$rid] ?? null;
            $shut = $row !== null && !$moder && NodeStatus::tryFrom(intval($row['status'])) !== NodeStatus::Published;
            if ($row === null || intval($row['tid']) !== $type->id || $shut) throw $this->getInvalid('rels');
        }
        $up = 0;
        $was = 0;
        foreach ($data['rels'] as $rel) if ($rel['type'] === 'parent') $up = $rel['rid'];
        foreach ($old?->rels ?? [] as $rel) if ($rel->type === 'parent') $was = $rel->rid;
        if ($self && $up && $up !== $was) $this->checkTreeWalk($self, $up);
        if ($data['poll'] && $data['poll'] !== ($old?->poll ?? 0)) {
            $sql = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_voting WHERE id = :id AND status = 1';
            if (!$this->getRowCount($sql, ['id' => $data['poll']])) throw $this->getInvalid('poll');
        }
        $roles = array_keys(array_filter($type->settings['assets'], fn($v) => $v['mode'] === 'link'));
        foreach ($data['assets'] as $i => $one) {
            if (!in_array($one['role'], $roles, true)) continue;
            $pars = ['tid' => $type->id, 'src' => $one['src'], 'bin' => $one['src'], 'self' => $self];
            $sql = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE n.tid = :tid AND a.role IN ('
                .$this->getInList($roles, 'r', $pars).') AND a.src = :src AND CAST(a.src AS BINARY) = CAST(:bin AS BINARY) AND a.nid <> :self';
            if ($this->getRowCount($sql, $pars)) throw $this->getInvalid('assets.'.$i.'.src');
        }
        return $self ? ($map[$self] ?? null) : null;
    }

    # Replace the extra categories, the relations and the resources of a material by their full new sets: rows that left are deleted, changed rows updated, new rows inserted
    # A resource whose kind, role or source changed is new content with fresh metadata and no open report; a physical file is never removed here
    private function setNodeSets(int $id, array $data, ?Node $old, string $now): void {
        $was = $old?->cids ?? [];
        $gone = array_values(array_diff($was, $data['cids']));
        $add = array_values(array_diff($data['cids'], $was));
        if ($gone) {
            $pars = ['id' => $id];
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_categories WHERE nid = :id AND cid IN ('.$this->getInList($gone, 'c', $pars).')', $pars);
        }
        if ($add) {
            $rows = [];
            $pars = [];
            foreach ($add as $i => $cid) {
                $rows[] = '(:n'.$i.', :c'.$i.')';
                $pars += ['n'.$i => $id, 'c'.$i => $cid];
            }
            $this->getQueryRes('INSERT INTO '.PREFIX_DB.'_node_categories (nid, cid) VALUES '.implode(', ', $rows), $pars);
        }
        $have = [];
        foreach ($old?->rels ?? [] as $rel) $have[$rel->type.':'.$rel->rid] = $rel;
        $rows = [];
        $pars = [];
        foreach ($data['rels'] as $i => $rel) {
            $prev = $have[$rel['type'].':'.$rel['rid']] ?? null;
            unset($have[$rel['type'].':'.$rel['rid']]);
            if ($prev !== null && $prev->sort !== $rel['sort']) {
                $this->getQueryRes('UPDATE '.PREFIX_DB.'_node_relations SET sort = :sort WHERE id = :id', ['sort' => $rel['sort'], 'id' => $prev->id]);
            } elseif ($prev === null) {
                $rows[] = '(:n'.$i.', :r'.$i.', :t'.$i.', :s'.$i.', :w'.$i.')';
                $pars += ['n'.$i => $id, 'r'.$i => $rel['rid'], 't'.$i => $rel['type'], 's'.$i => $rel['sort'], 'w'.$i => $now];
            }
        }
        if ($have) {
            $del = [];
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_relations WHERE id IN ('.$this->getInList(array_map(fn($v) => $v->id, $have), 'd', $del).')', $del);
        }
        if ($rows) $this->getQueryRes('INSERT INTO '.PREFIX_DB.'_node_relations (nid, rid, type, sort, created) VALUES '.implode(', ', $rows), $pars);
        $had = [];
        foreach ($old?->assets ?? [] as $one) $had[$one->id] = $one;
        $keep = array_filter(array_column($data['assets'], 'id'));
        $gone = array_values(array_diff(array_keys($had), $keep));
        if ($gone) {
            $del = [];
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_assets WHERE id IN ('.$this->getInList($gone, 'd', $del).')', $del);
        }
        foreach ($data['assets'] as $one) {
            [$mime, $size, $width, $height, $time] = $one['meta'];
            $pars = ['kind' => $one['kind'], 'role' => $one['role'], 'src' => $one['src'], 'name' => $one['name'], 'title' => $one['title'], 'intro' => $one['intro'],
                'mime' => $mime, 'size' => $size, 'width' => $width, 'height' => $height, 'time' => $time, 'sort' => $one['sort'], 'now' => $now];
            if ($one['id'] === null) {
                $sql = 'INSERT INTO '.PREFIX_DB.'_node_assets (nid, kind, role, src, name, title, intro, mime, size, width, height, duration, sort, created, updated)'
                    .' VALUES (:id, :kind, :role, :src, :name, :title, :intro, :mime, :size, :width, :height, :time, :sort, :now, :now2)';
                $this->getQueryRes($sql, $pars + ['id' => $id, 'now2' => $now]);
                continue;
            }
            $prev = $had[$one['id']];
            if ($one['new']) {
                $sql = 'UPDATE '.PREFIX_DB.'_node_assets SET kind = :kind, role = :role, src = :src, name = :name, title = :title, intro = :intro, mime = :mime, size = :size,'
                    .' width = :width, height = :height, duration = :time, sort = :sort, updated = :now, reported = NULL, ruid = 0 WHERE id = :id';
                $this->getQueryRes($sql, $pars + ['id' => $one['id']]);
            } elseif ([$prev->name, $prev->title, $prev->intro, $prev->sort] !== [$one['name'], $one['title'], $one['intro'], $one['sort']]) {
                $sql = 'UPDATE '.PREFIX_DB.'_node_assets SET name = :name, title = :title, intro = :intro, sort = :sort, updated = :now WHERE id = :id';
                $this->getQueryRes($sql, ['name' => $one['name'], 'title' => $one['title'], 'intro' => $one['intro'], 'sort' => $one['sort'], 'now' => $now, 'id' => $one['id']]);
            }
        }
    }

    # Keep the delivery of a future publication in line with the stored state: a published material with a future date has its job and any other state has none,
    # and a publication whose date has come, at once or because a pending job was moved into the past, is rewarded inside this transaction
    private function setPublishJob(NodeType $type, int $id, int $uid, NodeStatus $status, ?string $pub, ?string $job, bool $was, string $now): void {
        $live = $status === NodeStatus::Published;
        $wait = $live && $pub !== null && strcmp($pub, $now) > 0;
        if ($wait && $job !== $pub) {
            $sql = 'INSERT INTO '.PREFIX_DB.'_node_publish (nid, published, due) VALUES (:id, :pub, :due) ON DUPLICATE KEY UPDATE published = :pa, due = :pb';
            $this->getQueryRes($sql, ['id' => $id, 'pub' => $pub, 'due' => $pub, 'pa' => $pub, 'pb' => $pub]);
        } elseif (!$wait && $job !== null) {
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_publish WHERE nid = :id', ['id' => $id]);
        }
        if ($live && !$wait && (!$was || $job !== null)) $this->addNodePoint('publish', $type, 'node:'.$id, $uid);
    }

    # Build one resource from its row, the unknown metadata as null
    private function getAssetModel(array $row): NodeAsset {
        $num = fn($v) => ($v === null) ? null : intval($v);
        return new NodeAsset(intval($row['id']), intval($row['nid']), (string)$row['kind'], (string)$row['role'], (string)$row['src'], (string)$row['name'], (string)$row['title'],
            (string)$row['intro'], ($row['mime'] === null) ? null : (string)$row['mime'], $num($row['size']), $num($row['width']), $num($row['height']), $num($row['duration']),
            intval($row['hits']), ($row['reported'] === null) ? null : (string)$row['reported'], intval($row['ruid']), intval($row['sort']), (string)$row['created'],
            (string)$row['updated']);
    }

    # Build one material from a stored row and its sets; a state or comment mode outside the enums is a storage failure the writer does not paper over
    # The address is kept only for a moderator of the type, and the joined names only when the row carries them
    private function getNodeModel(array $row, NodeType $type, ?array $sets): Node {
        $state = NodeStatus::tryFrom(intval($row['status']));
        $mode = CommentMode::tryFrom(intval($row['comon']));
        if ($state === null || $mode === null) throw $this->getStorage('A material carries an unknown state or comment mode');
        $vals = json_decode((string)$row['field'], true);
        if (!is_array($vals) || ($vals && array_is_list($vals))) $vals = [];
        $ip = $this->checkModer($type) ? (string)$row['ip'] : null;
        return new Node(intval($row['id']), intval($row['tid']), intval($row['cid']), intval($row['uid']), (string)$row['aname'], $ip, (string)$row['title'],
            (string)$row['intro'], (string)$row['body'], $vals, intval($row['poll']), (bool)$row['home'], $mode, (bool)$row['pinned'], intval($row['comnum']),
            intval($row['views']), intval($row['score']), intval($row['ratings']), $state, intval($row['version']), (string)$row['created'], (string)$row['updated'],
            ($row['published'] === null) ? null : (string)$row['published'], ($row['expires'] === null) ? null : (string)$row['expires'], $sets['cids'] ?? null,
            $sets['rels'] ?? null, $sets['assets'] ?? null, isset($row['uname']) ? (string)$row['uname'] : null, isset($row['ctitle']) ? (string)$row['ctitle'] : null);
    }

    # Read one stored material of the type the way the writer sees it, without the read rules of a visitor, with its three sets when asked; null when it does not exist
    private function getStoredNode(int $id, NodeType $type, bool $sets): ?Node {
        $sql = 'SELECT '.self::COLS.', u.name AS uname, c.title AS ctitle FROM '.PREFIX_DB.'_nodes AS n LEFT JOIN '.PREFIX_DB.'_users AS u ON u.id = n.uid AND n.uid > 0'
            .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0 WHERE n.id = :id AND n.tid = :tid';
        $row = $this->getQueryRes($sql, ['id' => $id, 'tid' => $type->id])->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if (!$sets) return $this->getNodeModel($row, $type, null);
        $cids = array_map('intval', $this->getQueryRes('SELECT cid FROM '.PREFIX_DB.'_node_categories WHERE nid = :id ORDER BY cid', ['id' => $id])->fetchAll(PDO::FETCH_COLUMN));
        $rels = [];
        $sql = 'SELECT id, nid, rid, type, sort, created FROM '.PREFIX_DB.'_node_relations WHERE nid = :id ORDER BY type, sort, rid';
        foreach ($this->getQueryRes($sql, ['id' => $id])->fetchAll(PDO::FETCH_ASSOC) as $one) {
            $rels[] = new NodeRelation(intval($one['id']), intval($one['nid']), intval($one['rid']), (string)$one['type'], intval($one['sort']), (string)$one['created']);
        }
        $sql = 'SELECT '.self::ASSETS.' FROM '.PREFIX_DB.'_node_assets WHERE nid = :id ORDER BY role, sort, id';
        $assets = array_map(fn($v) => $this->getAssetModel($v), $this->getQueryRes($sql, ['id' => $id])->fetchAll(PDO::FETCH_ASSOC));
        return $this->getNodeModel($row, $type, ['cids' => $cids, 'rels' => $rels, 'assets' => $assets]);
    }

    # Read the type, the state and the version of one material by its id without a lock and resolve its type through the reader; only a moderator of that type goes on
    # A context that moderates no type at all is refused before any statement
    private function getNodeHead(int $id): array {
        if ($this->ctx->task || (!$this->ctx->super && !$this->ctx->mods)) throw $this->getDenied('The context moderates no material');
        $sql = 'SELECT n.tid, n.status, n.version, t.name FROM '.PREFIX_DB.'_nodes AS n INNER JOIN '.PREFIX_DB.'_node_types AS t ON t.id = n.tid WHERE n.id = :id';
        $row = ($id > 0) ? $this->getQueryRes($sql, ['id' => $id])->fetch(PDO::FETCH_ASSOC) : false;
        $type = $row ? $this->query->getNodeType((string)$row['name']) : null;
        if ($type === null || $type->id !== intval($row['tid'])) throw $this->getMissing('The material does not exist');
        if (!$this->checkModer($type)) throw $this->getDenied('The context does not moderate the type');
        $this->checkExtType($type);
        return [$row, $type];
    }

    # Refuse to send a material to moderation or publication while an active required field holds no value or an active role has fewer resources than its minimum
    private function checkNodeReady(NodeType $type, Node $node): void {
        foreach ($type->fields as $name => $def) {
            if ($def['active'] && $def['req'] && in_array($node->fields[$name] ?? null, [null, '', []], true)) throw $this->getInvalid('fields.'.$name.'.required');
        }
        $need = array_filter($type->settings['assets'], fn($v) => $v['active'] && $v['min'] > 0);
        if (!$need) return;
        $sql = 'SELECT role, COUNT(*) AS num FROM '.PREFIX_DB.'_node_assets WHERE nid = :id GROUP BY role';
        $have = array_column($this->getQueryRes($sql, ['id' => $node->id])->fetchAll(PDO::FETCH_ASSOC), 'num', 'role');
        foreach ($need as $role => $def) if (intval($have[$role] ?? 0) < $def['min']) throw $this->getInvalid('assets.'.$role.'.min');
    }

    # Check a new material exactly like a create would and answer it unsaved, id 0 and version 0, for the preview of a form: rights, workflow, every set, the files and the dates
    # Only reading statements run and no transaction is opened, so a preview leaves no material, relation, resource or counter behind
    public function getNodePreview(NodeType $type, NodeInput $input, NodeStatus $status): Node {
        $this->checkNewNode($type, $status);
        $data = $this->getInputData($type, $input, $status, null);
        $this->checkNodeFiles($type, $data, null);
        $now = (string)$this->getQueryRes('SELECT NOW()')->fetchColumn();
        $this->checkNodeRefs($type, $data, $status, $now, null, 0, false);
        $rels = array_map(fn($v) => new NodeRelation(0, 0, $v['rid'], $v['type'], $v['sort'], ''), $data['rels']);
        $assets = array_map(fn($v) => new NodeAsset(0, 0, $v['kind'], $v['role'], $v['src'], $v['name'], $v['title'], $v['intro'], $v['meta'][0], $v['meta'][1], $v['meta'][2],
            $v['meta'][3], $v['meta'][4], 0, null, 0, $v['sort'], '', ''), $data['assets']);
        return new Node(0, $type->id, $data['cid'], $this->ctx->uid, $data['aname'], null, $data['title'], $data['intro'], $data['body'], $data['fields'], $data['poll'],
            $data['home'], $data['comon'], $data['pinned'], 0, 0, 0, 0, $status, 0, '', '', $data['pub'], $data['expires'], $data['cids'], $rels, $assets, null, null);
    }

    # Create a material of the type in draft, pending or published at version 1 with its categories, fields, relations and resources in one transaction
    # A visitor who does not moderate the type submits through its workflow; a publication whose date has come is rewarded at once and a future one gets its job
    public function addNode(NodeType $type, NodeInput $input, NodeStatus $status): Node {
        $this->checkNewNode($type, $status);
        $this->getPoint();
        $data = $this->getInputData($type, $input, $status, null);
        $files = function () use ($type, &$data): void {
            $this->checkNodeFiles($type, $data, null);
        };
        return $this->setNodeWrite($type, $data['poll'] ? [$data['poll']] : [], $files, function () use ($type, $status, &$data): Node {
            $now = $this->getTypeLock($type);
            $this->checkNodeRefs($type, $data, $status, $now, null, 0, true);
            $sql = 'INSERT INTO '.PREFIX_DB.'_nodes (tid, cid, uid, aname, ip, title, intro, body, field, poll, home, comon, pinned, status, version, created, updated,'
                .' published, expires) VALUES (:tid, :cid, :uid, :aname, :ip, :title, :intro, :body, :field, :poll, :home, :comon, :pinned, :status, 1, :now, :upd, :pub, :exp)';
            $this->getQueryRes($sql, ['tid' => $type->id, 'cid' => $data['cid'], 'uid' => $this->ctx->uid, 'aname' => $data['aname'], 'ip' => $this->ctx->ip,
                'title' => $data['title'], 'intro' => $data['intro'], 'body' => $data['body'], 'field' => $data['field'], 'poll' => $data['poll'], 'home' => $data['home'] ? 1 : 0,
                'comon' => $data['comon']->value, 'pinned' => $data['pinned'] ? 1 : 0, 'status' => $status->value, 'now' => $now, 'upd' => $now, 'pub' => $data['pub'],
                'exp' => $data['expires']]);
            $id = intval($this->db->getSqlLastId());
            $this->setNodeSets($id, $data, null, $now);
            $this->setPublishJob($type, $id, $this->ctx->uid, $status, $data['pub'], null, false, $now);
            $node = $this->getStoredNode($id, $type, true) ?? throw $this->getStorage('The created material cannot be read back');
            $this->ext?->addNodeData($node, $data['ext']);
            return $node;
        });
    }

    # Change the content of a material at the expected version: its categories, texts, fields, relations and resources as full sets, the state untouched
    # A move of the parent is checked against the current chain under the lock of the type, and a pending job moved into the past is delivered as the publication that came
    public function updateNode(int $id, NodeInput $input, int $version): Node {
        [$head, $type] = $this->getNodeHead($id);
        $this->getPoint();
        if (intval($head['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
        $old = $this->getStoredNode($id, $type, true) ?? throw $this->getMissing('The material does not exist');
        $data = $this->getInputData($type, $input, $old->status, $old);
        $polls = ($data['poll'] !== $old->poll) ? array_values(array_filter([$old->poll, $data['poll']])) : [];
        sort($polls);
        $files = function () use ($type, &$data, $old): void {
            $this->checkNodeFiles($type, $data, $old);
        };
        return $this->setNodeWrite($type, $polls, $files, function () use ($type, $id, $version, $old, &$data): Node {
            $now = $this->getTypeLock($type);
            $row = $this->checkNodeRefs($type, $data, $old->status, $now, $old, $id, true);
            if ($row === null) throw $this->getMissing('The material does not exist');
            if (intval($row['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
            $before = $this->getStoredNode($id, $type, true) ?? throw $this->getMissing('The material does not exist');
            $sql = 'UPDATE '.PREFIX_DB.'_nodes SET cid = :cid, aname = :aname, title = :title, intro = :intro, body = :body, field = :field, poll = :poll, home = :home,'
                .' comon = :comon, pinned = :pinned, published = :pub, expires = :exp, version = version + 1, updated = :now WHERE id = :id AND version = :ver';
            $this->getQueryRes($sql, ['cid' => $data['cid'], 'aname' => $data['aname'], 'title' => $data['title'], 'intro' => $data['intro'], 'body' => $data['body'],
                'field' => $data['field'], 'poll' => $data['poll'], 'home' => $data['home'] ? 1 : 0, 'comon' => $data['comon']->value, 'pinned' => $data['pinned'] ? 1 : 0,
                'pub' => $data['pub'], 'exp' => $data['expires'], 'now' => $now, 'id' => $id, 'ver' => $version]);
            $this->setNodeSets($id, $data, $before, $now);
            $job = $this->getQueryRes('SELECT published FROM '.PREFIX_DB.'_node_publish WHERE nid = :id FOR UPDATE', ['id' => $id])->fetchColumn();
            $live = $before->status === NodeStatus::Published;
            $this->setPublishJob($type, $id, $before->uid, $before->status, $data['pub'], ($job === false) ? null : (string)$job, $live, $now);
            $after = $this->getStoredNode($id, $type, true) ?? throw $this->getStorage('The changed material cannot be read back');
            $this->ext?->updateNodeData($before, $after, $data['ext']);
            return $after;
        });
    }

    # Move a material to another state of the closed matrix at the expected version; asking for the current state succeeds without a write and keeps the version
    # Pending and published need the required fields and the minimum resources, a publication whose date has come is rewarded and the job follows the new state
    # The answer is the stored row after the move without its three sets, which the move does not touch
    public function updateNodeStatus(int $id, NodeStatus $status, int $version): Node {
        [$head, $type] = $this->getNodeHead($id);
        $this->getPoint();
        $from = NodeStatus::tryFrom(intval($head['status'])) ?? throw $this->getStorage('A material carries an unknown state');
        if (intval($head['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
        if ($from === $status) return $this->getStoredNode($id, $type, false) ?? throw $this->getMissing('The material does not exist');
        if (!$from->checkStatusMove($status)) throw $this->getInvalid('status');
        return $this->setNodeWrite($type, [], null, function () use ($type, $id, $version, $status): Node {
            $now = $this->getTypeLock($type);
            $sql = 'SELECT '.self::COLS.', p.published AS jpub FROM '.PREFIX_DB.'_nodes AS n LEFT JOIN '.PREFIX_DB.'_node_publish AS p ON p.nid = n.id'
                .' WHERE n.id = :id AND n.tid = :tid FOR UPDATE';
            $row = $this->getQueryRes($sql, ['id' => $id, 'tid' => $type->id])->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw $this->getMissing('The material does not exist');
            if (intval($row['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
            $before = $this->getNodeModel($row, $type, null);
            if ($before->status === $status || !$before->status->checkStatusMove($status)) throw $this->getInvalid('status');
            if (in_array($status, [NodeStatus::Pending, NodeStatus::Published], true)) $this->checkNodeReady($type, $before);
            $pub = $before->pubdate ?? (($status === NodeStatus::Published) ? $now : null);
            $sql = 'UPDATE '.PREFIX_DB.'_nodes SET status = :status, published = :pub, version = version + 1, updated = :now WHERE id = :id AND version = :ver';
            $this->getQueryRes($sql, ['status' => $status->value, 'pub' => $pub, 'now' => $now, 'id' => $id, 'ver' => $version]);
            $job = ($row['jpub'] === null) ? null : (string)$row['jpub'];
            $this->setPublishJob($type, $id, $before->uid, $status, $pub, $job, $before->status === NodeStatus::Published, $now);
            $after = $this->getNodeModel(array_replace($row, ['status' => $status->value, 'published' => $pub, 'version' => $version + 1, 'updated' => $now]), $type, null);
            $this->ext?->updateNodeData($before, $after, null);
            return $after;
        });
    }

    # Delete a material physically at the expected version with its categories, relations, resource rows and job; the files it pointed at stay
    # The publication award of the author is compensated once through Point, and the extension removes its rows inside the same transaction, as do the favorites of it
    public function deleteNode(int $id, int $version): void {
        [$head, $type] = $this->getNodeHead($id);
        $point = $this->getPoint();
        if (intval($head['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
        $this->setNodeWrite($type, [], null, function () use ($type, $id, $version, $point): bool {
            $this->getTypeLock($type);
            $sql = 'SELECT uid, version FROM '.PREFIX_DB.'_nodes WHERE id = :id AND tid = :tid FOR UPDATE';
            $row = $this->getQueryRes($sql, ['id' => $id, 'tid' => $type->id])->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw $this->getMissing('The material does not exist');
            if (intval($row['version']) !== $version) throw new NodeException('The expected material version is stale', NodeException::CONFLICT);
            $uid = intval($row['uid']);
            try {
                $rid = ($uid > 0) ? $point->getEventId('publish', 'node.'.$type->name, 'node:'.$id, $uid) : 0;
                if ($rid > 0) $point->addEvent('publish', 'node.'.$type->name, 'reverse:'.$rid, $uid, ['rid' => $rid]);
            } catch (RuntimeException $err) {
                throw new NodeException('The points of a node action are lost', NodeException::STORAGE, $err);
            }
            if ($this->ext !== null) $this->ext->deleteNodeData($this->getStoredNode($id, $type, true) ?? throw $this->getMissing('The material does not exist'));
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_favorites WHERE modul = :modul AND fid = :fid', ['modul' => $type->name, 'fid' => $id]);
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_nodes WHERE id = :id AND version = :ver', ['id' => $id, 'ver' => $version]);
            return true;
        });
    }

    # Count one successful public view of a material of the type: the public read right is checked again, the counter moves by one atomic statement without a new version,
    # and a registered viewer is rewarded once per material
    public function updateNodeViews(int $id, NodeType $type): void {
        if ($this->ctx->task) throw $this->getDenied('A background context counts no view');
        $this->getPoint();
        $tgt = $this->query->getNodeTarget($type->name, $id);
        if ($tgt === null || $tgt->type->id !== $type->id) throw $this->getMissing('The material does not exist');
        $this->getQueryRes('UPDATE '.PREFIX_DB.'_nodes SET views = views + 1 WHERE id = :id AND views < :max', ['id' => $id, 'max' => self::MAXINT]);
        $this->addNodePoint('view', $type, 'node:'.$id, $this->ctx->uid);
    }

    # Lock the type row and then the material row inside the open transaction of a global owner, both as locking reads, so no snapshot is taken before the wait
    private function setTargetLock(int $id, NodeType $type): void {
        if (!$this->db->checkSqlActive()) throw $this->getInvalid('transaction');
        $sql = 'SELECT id FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE';
        if ($this->getQueryRes($sql, ['id' => $type->id])->fetchColumn() === false) throw $this->getMissing('The node type does not exist');
        $sql = 'SELECT id FROM '.PREFIX_DB.'_nodes WHERE id = :id AND tid = :tid FOR UPDATE';
        if ($this->getQueryRes($sql, ['id' => $id, 'tid' => $type->id])->fetchColumn() === false) throw $this->getMissing('The material does not exist');
    }

    # Lock one material for a global owner inside its open transaction and read its light target only then, with the very predicate of the reader
    # A material that is missing, closed or of another type answers null; the rating subsystem calls this as the first statement of a vote
    public function getLockedTarget(int $id, NodeType $type): ?NodeTarget {
        if ($id < 1 || $id > self::MAXINT) throw $this->getInvalid('id');
        try {
            $this->setTargetLock($id, $type);
        } catch (NodeException $err) {
            if ($err->getCode() === NodeException::NOTFOUND) return null;
            throw $err;
        }
        $tgt = $this->query->getNodeTarget($type->name, $id);
        return ($tgt !== null && $tgt->type->id === $type->id) ? $tgt : null;
    }

    # Write trusted aggregates of a global owner into one material inside the open transaction of that owner: the type row, then the material row, then the columns
    private function setNodeCount(int $id, NodeType $type, array $vals): void {
        $this->setTargetLock($id, $type);
        $set = implode(', ', array_map(fn($v) => $v.' = :'.$v, array_keys($vals)));
        $this->getQueryRes('UPDATE '.PREFIX_DB.'_nodes SET '.$set.' WHERE id = :id', $vals + ['id' => $id]);
    }

    # Store the checked count of visible comments of a material inside the transaction of the comment owner; neither the version nor the date of the material moves
    public function updateNodeComments(int $id, NodeType $type, int $count): void {
        if ($count < 0 || $count > self::MAXINT) throw $this->getInvalid('comnum');
        $this->setNodeCount($id, $type, ['comnum' => $count]);
    }

    # Store the checked sum and number of ratings of a material inside the transaction of the rating owner: a number of votes of one to five stars each, or none at all
    public function updateNodeRating(int $id, NodeType $type, int $score, int $ratings): void {
        if ($ratings < 0 || $score < $ratings || $score > 5 * $ratings || $score > self::MAXINT) throw $this->getInvalid('rating');
        $this->setNodeCount($id, $type, ['score' => $score, 'ratings' => $ratings]);
    }

    # Clear the link of every material to one shared poll that is being deleted, inside the open transaction of the poll owner, who already holds the named lock of the poll
    # Which types a poll reaches is only known from the materials, and a plain read of them would open a snapshot before the wait, so every type row is locked first
    # by ascending id, then the materials of the poll by ascending id; the owner raises the cache generation after its commit
    public function deleteNodePoll(int $id): void {
        if ($this->ctx->aid < 1 || $this->ctx->task) throw $this->getDenied('Only an administrator deletes a poll');
        if ($id < 1 || $id > self::MAXINT) throw $this->getInvalid('poll');
        if (!$this->db->checkSqlActive()) throw $this->getInvalid('transaction');
        $this->getQueryRes('SELECT id FROM '.PREFIX_DB.'_node_types ORDER BY id FOR UPDATE');
        $ids = $this->getQueryRes('SELECT id FROM '.PREFIX_DB.'_nodes WHERE poll = :poll ORDER BY id FOR UPDATE', ['poll' => $id])->fetchAll(PDO::FETCH_COLUMN);
        if ($ids) $this->getQueryRes('UPDATE '.PREFIX_DB.'_nodes SET poll = 0, version = version + 1, updated = NOW() WHERE poll = :poll', ['poll' => $id]);
    }

    # Run one counted action on an accessible resource: the extension of the type may forbid it first and follows it inside the same transaction when the statement changed the row
    private function setAssetAction(NodeType $type, NodeAsset $asset, string $act, string $sql, array $pars): bool {
        if ($this->ext === null) return $this->getQueryRes($sql, $pars)->rowCount() > 0;
        $tgt = $this->query->getNodeTarget($type->name, $asset->nid);
        if ($tgt === null || !$this->ext->checkNodeAction($type, $tgt, $act)) throw $this->getDenied('The extension forbids the action');
        if (!$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
        try {
            $done = $this->getQueryRes($sql, $pars)->rowCount() > 0;
            if ($done) $this->ext->updateNodeAction($type, $tgt, $act);
            if (!$this->db->setSqlCommit()) throw $this->getStorage('The commit of a node action is uncertain');
        } catch (Throwable $err) {
            $this->db->setSqlRollback();
            throw ($err instanceof NodeException) ? $err : new NodeException('A node action failed', NodeException::STORAGE, $err);
        }
        return $done;
    }

    # Resolve one editor attachment of the type to its canonical path for the controlled file answer; every refusal answers the same empty string
    # The name must be a whole managed name of the upload service with an extension the type still allows, and it lives in the root of the type or, as thumb, in thumb/
    # A stored material (id above zero) grants a name its own intro or body carries, read with the light text projection, so knowing the name of a file opens nothing
    # The preview of NOD-199 (id zero) grants a file of the visitor alone - the account, the session token of a guest - unless the context moderates the type
    # The original is checked before its thumb in both branches, and nothing here counts, writes or trusts an owner, address or name the request brought besides the key
    public function getNodeFile(NodeType $type, int $id, string $key, bool $thumb): string {
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $exts = $type->uploads ? explode(',', $type->uploads['extensions']) : [];
        $good = $id >= 0 && !$this->ctx->task && strlen($key) <= 255 && $key === basename(str_replace('\\', '/', $key));
        require_once BASE_DIR.'/core/classes/filemanager.php';
        if (!$good || !FileManager::checkFileName($key) || !in_array($ext, $exts, true) || !in_array($ext, getUploadService()::getSupportedTypes(), true)) return '';
        $area = new FileManager('editor', UPLOADS_DIR.'/'.$type->name);
        try {
            if ($id > 0) {
                $node = $this->query->getNodeContent($id, $type);
                $prs = new Parser();
                $names = $node ? array_merge($prs->getAttachList($node->intro), $prs->getAttachList((string)$node->body)) : [];
                $row = in_array($key, $names, true) ? $area->getFileData($key) : [];
                if (!$row || $row['kind'] === 'dir' || $row['path'] !== $key) return '';
            } else {
                $moder = $this->checkModer($type);
                if (!$moder && !$this->checkFlowAccess($type)) return '';
                $row = $this->getFileRow($area, $key, $exts, $type->uploads['maxbytes'], $moder, $this->getFileToken($type, $moder), 'attach');
                if ($row['path'] !== $key) return '';
            }
        } catch (NodeException) {
            return '';
        }
        $rel = $thumb ? 'thumb/'.$key : $key;
        $one = $thumb ? $area->getFileData($rel) : $row;
        $full = ($one && $one['kind'] !== 'dir' && $one['path'] === $rel) ? realpath(UPLOADS_DIR.'/'.$type->name.'/'.$rel) : false;
        return ($full === false) ? '' : str_replace('\\', '/', $full);
    }

    # Count one allowed start of a download or one external visit of a resource right before the response: the access is checked again and a role of another mode is refused
    # The counter moves by one atomic statement without a new version or date, and a registered visitor is rewarded once per resource
    public function updateNodeAssetHits(int $id, NodeType $type): void {
        if ($this->ctx->task) throw $this->getDenied('A background context counts no download');
        $this->getPoint();
        $asset = $this->query->getNodeAsset($id, $type) ?? throw $this->getMissing('The resource does not exist');
        $mode = $type->settings['assets'][$asset->role]['mode'];
        $link = str_starts_with($asset->src, 'http://') || str_starts_with($asset->src, 'https://');
        if ($mode !== 'download' && !($mode === 'link' && $link)) throw $this->getInvalid('asset.mode');
        $sql = 'UPDATE '.PREFIX_DB.'_node_assets SET hits = hits + 1 WHERE id = :id AND hits < :max';
        $this->setAssetAction($type, $asset, 'asset', $sql, ['id' => $id, 'max' => self::MAXINT]);
        $this->addNodePoint(($mode === 'link') ? 'visit' : 'download', $type, 'asset:'.$id, $this->ctx->uid);
    }

    # Register a report that an accessible resource does not work, once: the first report keeps its date and author, a repeat changes nothing and a guest is stored as zero
    public function updateNodeAssetReport(int $id, NodeType $type): void {
        if ($this->ctx->task) throw $this->getDenied('A background context reports nothing');
        $asset = $this->query->getNodeAsset($id, $type) ?? throw $this->getMissing('The resource does not exist');
        if (!$type->settings['assets'][$asset->role]['report']) throw $this->getInvalid('asset.report');
        $sql = 'UPDATE '.PREFIX_DB.'_node_assets SET reported = NOW(), ruid = :ruid WHERE id = :id AND reported IS NULL';
        $this->setAssetAction($type, $asset, 'report', $sql, ['ruid' => $this->ctx->uid, 'id' => $id]);
    }

    # Decide an open report of a resource of the type as a moderator of it: the row is locked and read again, a report already decided makes the call an empty success,
    # a useful report of a registered author rewards him with a source of its own, and the report is cleared in the same transaction
    public function deleteNodeAssetReport(int $id, NodeType $type, bool $useful): void {
        if (!$this->checkModer($type)) throw $this->getDenied('The context does not moderate the type');
        if ($useful) $this->getPoint();
        $sql = 'SELECT n.tid FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE a.id = :id';
        if ($id < 1 || intval($this->getQueryRes($sql, ['id' => $id])->fetchColumn()) !== $type->id) throw $this->getMissing('The resource does not exist');
        if (!$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
        try {
            $row = $this->getQueryRes('SELECT reported, ruid FROM '.PREFIX_DB.'_node_assets WHERE id = :id FOR UPDATE', ['id' => $id])->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['reported'] !== null) {
                if ($useful) $this->addNodePoint('report', $type, 'report:'.$id.':'.bin2hex(random_bytes(8)), intval($row['ruid']));
                $this->getQueryRes('UPDATE '.PREFIX_DB.'_node_assets SET reported = NULL, ruid = 0 WHERE id = :id', ['id' => $id]);
            }
            if (!$this->db->setSqlCommit()) throw $this->getStorage('The commit of a report decision is uncertain');
        } catch (Throwable $err) {
            $this->db->setSqlRollback();
            throw ($err instanceof NodeException) ? $err : new NodeException('A report decision failed', NodeException::STORAGE, $err);
        }
    }

    # Deliver one due job in its own transaction: the type, the material and the job are locked and read again; a job that is stale, cancelled or not due rewards nobody,
    # an author without an account is passed over, a recoverable refusal of the points moves the job one minute on, and a lost transaction leaves it untouched
    private function setPublishDue(int $id, int $tid, string $name): string {
        if (!$this->db->setSqlBegin()) return 'failed';
        try {
            $live = $this->getQueryRes('SELECT active FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE', ['id' => $tid])->fetchColumn();
            $sql = 'SELECT uid, status, published, published <= NOW() AS came FROM '.PREFIX_DB.'_nodes WHERE id = :id AND tid = :tid FOR UPDATE';
            $row = $this->getQueryRes($sql, ['id' => $id, 'tid' => $tid])->fetch(PDO::FETCH_ASSOC);
            $sql = 'SELECT published, due <= NOW() AS ready FROM '.PREFIX_DB.'_node_publish WHERE nid = :id FOR UPDATE';
            $job = $this->getQueryRes($sql, ['id' => $id])->fetch(PDO::FETCH_ASSOC);
            $out = 'skipped';
            $del = 'DELETE FROM '.PREFIX_DB.'_node_publish WHERE nid = :id';
            if (!$live || !$row || !$job || !$job['ready']) {
                $out = 'skipped';
            } elseif (NodeStatus::tryFrom(intval($row['status'])) !== NodeStatus::Published || $row['published'] !== $job['published']) {
                $this->getQueryRes($del, ['id' => $id]);
            } elseif (!$row['came']) {
                $this->getQueryRes('UPDATE '.PREFIX_DB.'_node_publish SET due = published WHERE nid = :id', ['id' => $id]);
            } else {
                $uid = intval($row['uid']);
                $user = $uid > 0 && $this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid]) > 0;
                $done = !$user || $this->getPoint()->addEvent('publish', 'node.'.$name, 'node:'.$id, $uid);
                $this->getQueryRes($done ? $del : 'UPDATE '.PREFIX_DB.'_node_publish SET due = NOW() + INTERVAL 60 SECOND WHERE nid = :id', ['id' => $id]);
                $out = $done ? 'processed' : 'failed';
            }
            if (!$this->db->setSqlCommit()) throw $this->getStorage('The commit of a publication job is uncertain');
            return $out;
        } catch (Throwable $err) {
            $this->db->setSqlRollback();
            Logger::addSite('error', 'Node: a publication job failed', ['nid' => $id, 'error' => get_class($err)]);
            return 'failed';
        }
    }

    # Deliver the due publications of active types for the scheduler, in the order of the queue and one transaction per material, and answer the result in its own form
    # Only the trusted background context runs it; the status is success or failed as the scheduler takes it, and extra counts the processed, failed and skipped jobs
    public function updateNodePublishList(int $limit = 50): array {
        if (!$this->ctx->task) throw $this->getDenied('Only a background context delivers publications');
        if ($limit < 1 || $limit > 500) throw $this->getInvalid('limit');
        $this->getPoint();
        $sql = 'SELECT p.nid, n.tid, t.name FROM '.PREFIX_DB.'_node_publish AS p INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = p.nid'
            .' INNER JOIN '.PREFIX_DB.'_node_types AS t ON t.id = n.tid WHERE p.due <= NOW() AND t.active = 1 ORDER BY p.due, p.nid LIMIT '.$limit;
        $sum = ['processed' => 0, 'failed' => 0, 'skipped' => 0];
        foreach ($this->getQueryRes($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) $sum[$this->setPublishDue(intval($row['nid']), intval($row['tid']), (string)$row['name'])]++;
        $text = sprintf('Node publication: %d processed, %d failed, %d skipped', $sum['processed'], $sum['failed'], $sum['skipped']);
        return ['status' => $sum['failed'] ? 'failed' : 'success', 'message' => $text, 'extra' => $sum];
    }

    # The module of one category, read without a lock to learn which types a category write has to lock first
    private function getCatModul(int $id): string {
        $row = ($id > 0) ? $this->getQueryRes('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :id', ['id' => $id])->fetchColumn() : false;
        if ($row === false) throw $this->getMissing('The category does not exist');
        return (string)$row;
    }

    # The Node types a category write touches by the modules the category had and gets, each one administered by the context: the main administrator,
    # the manager of Node or a moderator of that type; a write that touches no Node type does not belong here
    private function getCatTypes(array $names): array {
        if ($this->ctx->aid < 1) throw $this->getDenied('The context administers no category');
        $out = [];
        foreach (array_unique($names) as $name) {
            $type = $this->query->getNodeType($name);
            if ($type === null) continue;
            if (!$this->ctx->manage && !$this->checkModer($type)) throw $this->getDenied('The context does not administer the type');
            $out[$type->id] = $type;
        }
        if (!$out) throw $this->getInvalid('category');
        ksort($out);
        return $out;
    }

    # Lock the rows of the types a category write touches by ascending id; a type that is gone meanwhile makes the write not found
    private function setCatTypeLock(array $types): void {
        $pars = [];
        $sql = 'SELECT id FROM '.PREFIX_DB.'_node_types WHERE id IN ('.$this->getInList(array_keys($types), 't', $pars).') ORDER BY id FOR UPDATE';
        if (count($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_COLUMN)) !== count($types)) throw $this->getMissing('The node type does not exist');
    }

    # Change one category of a Node type with the full checked row of the category form, under the lock of every type it leaves or enters and of the category itself
    # A category that still carries materials as main or extra category never leaves its type; its language and rights decide what pages show, so the page cache follows the guard
    public function updateNodeCategory(int $id, array $row): void {
        if (!$this->checkKeys($row, self::CATKEYS)) throw $this->getInvalid('category');
        foreach ($row as $key => $val) if (!is_string($val) && !is_int($val)) throw $this->getInvalid('category.'.$key);
        if (!is_string($row['modul']) || $row['modul'] === '') throw $this->getInvalid('category.modul');
        $was = $this->getCatModul($id);
        $types = $this->getCatTypes([$was, $row['modul']]);
        $this->setNodeWrite(null, [], null, function () use ($id, $row, $was, $types): bool {
            $this->setCatTypeLock($types);
            $now = $this->getQueryRes('SELECT modul FROM '.PREFIX_DB.'_categories WHERE id = :id FOR UPDATE', ['id' => $id])->fetchColumn();
            if ($now === false) throw $this->getMissing('The category does not exist');
            if ((string)$now !== $was) throw new NodeException('The category changed during the write', NodeException::CONFLICT);
            if ($row['modul'] !== $was) {
                $sql = 'SELECT (EXISTS (SELECT 1 FROM '.PREFIX_DB.'_nodes WHERE cid = :na) OR EXISTS (SELECT 1 FROM '.PREFIX_DB.'_node_categories WHERE cid = :nb)) AS used';
                if ($this->getRowCount($sql, ['na' => $id, 'nb' => $id])) throw $this->getInvalid('category.used');
            }
            $set = implode(', ', array_map(fn($v) => $v.' = :'.$v, self::CATKEYS));
            $this->getQueryRes('UPDATE '.PREFIX_DB.'_categories SET '.$set.' WHERE id = :id', $row + ['id' => $id]);
            return true;
        });
    }

    # Delete one category of a Node type with its direct subcategories, as the category screen does, under the lock of the type, the categories and the materials:
    # a main category of any material in any state blocks the whole deletion, and the extra links of the set leave with a new version of every material that had one
    public function deleteNodeCategory(int $id): void {
        $was = $this->getCatModul($id);
        $types = $this->getCatTypes([$was]);
        $this->setNodeWrite(null, [], null, function () use ($id, $was, $types): bool {
            $this->setCatTypeLock($types);
            $sql = 'SELECT id, modul FROM '.PREFIX_DB.'_categories WHERE id = :id OR parent = :pid ORDER BY id FOR UPDATE';
            $map = array_column($this->getQueryRes($sql, ['id' => $id, 'pid' => $id])->fetchAll(PDO::FETCH_ASSOC), 'modul', 'id');
            if (!isset($map[$id])) throw $this->getMissing('The category does not exist');
            if ((string)$map[$id] !== $was) throw new NodeException('The category changed during the write', NodeException::CONFLICT);
            $pars = [];
            $in = $this->getInList(array_keys($map), 'c', $pars);
            if ($this->getRowCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE cid IN ('.$in.')', $pars)) throw $this->getInvalid('category.used');
            $nids = $this->getQueryRes('SELECT DISTINCT nid FROM '.PREFIX_DB.'_node_categories WHERE cid IN ('.$in.') ORDER BY nid', $pars)->fetchAll(PDO::FETCH_COLUMN);
            if ($nids) {
                $npar = [];
                $nin = $this->getInList(array_map('intval', $nids), 'n', $npar);
                $this->getQueryRes('SELECT id FROM '.PREFIX_DB.'_nodes WHERE id IN ('.$nin.') ORDER BY id FOR UPDATE', $npar);
                $this->getQueryRes('UPDATE '.PREFIX_DB.'_nodes SET version = version + 1, updated = NOW() WHERE id IN ('.$nin.')', $npar);
                $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_categories WHERE cid IN ('.$in.')', $pars);
            }
            $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_categories WHERE id IN ('.$in.')', $pars);
            return true;
        });
    }
}
