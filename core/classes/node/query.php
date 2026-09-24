<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The only reader of Node: types assembled from the type table and the loaded configuration, materials, light targets, resources, trees and sitemap rows
# Every read is decided against the context the instance was built with: the type, the state and the dates, the read right of the main category and the extension scope
# A list gets its category rights from one prefetch of the categories of each type per instance, as a predicate on cid; a single material checks its joined category in PHP
# Nothing outlives the instance: the types and category maps it assembled live until the request ends, and no SQL result is written anywhere
final class NodeQuery {

    # The grammar of a public type name
    private const NAME = '/^[a-z][a-z0-9]{0,19}$/D';

    # The grammar of a resource role name
    private const ROLE = '/^[a-z][a-z0-9_]{0,49}$/D';

    # The grammar of a value name an extension scope binds
    private const PARAM = '/^[a-z][a-z0-9_]{0,31}$/D';

    # A canonical database date and time
    private const DATE = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/D';

    # The sections a stored type may carry, in the order of the effective settings
    private const SECTIONS = ['list', 'view', 'form', 'workflow', 'admin', 'features', 'assets', 'integrations', 'ext'];

    # The closed keys of the list section, its sort keys and the metadata it may show
    private const LISTKEYS = ['orders', 'order', 'dir', 'limit', 'alpha', 'show'];
    private const ORDERS = ['published', 'updated', 'title', 'views', 'rating'];
    private const SHOW = ['category', 'author', 'date', 'views'];

    # The closed switches of the features section
    private const FEATURES = ['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related', 'tree'];

    # The closed keys of the integrations section and the page kinds its seo key names
    private const INTEGRATE = ['search', 'rss', 'sitemap', 'blocks', 'seo'];
    private const SEO = ['website', 'article', 'news'];

    # Who may submit publicly
    private const ACCESS = ['all', 'user', 'group'];

    # The resource kinds, and the kinds each display mode of a role accepts; public, because the writer checks every stored resource against the same registry
    public const KINDS = ['file', 'image', 'audio', 'video'];
    public const RMODES = [
        'image' => ['image'],
        'gallery' => ['image'],
        'download' => ['file', 'image', 'audio', 'video'],
        'player' => ['audio', 'video'],
        'none' => ['file', 'image', 'audio', 'video'],
        'link' => ['file'],
    ];

    # Every key of a role with its default in the canonical order; a null default marks a key the definition must carry
    # Public, because the writer stores a role with only the keys that differ from these defaults
    public const ROLEDEF = [
        'title' => null, 'intro' => '', 'kinds' => [], 'extensions' => [], 'maxbytes' => null, 'min' => 0,
        'max' => null, 'canlink' => false, 'report' => false, 'mode' => null, 'active' => true, 'sort' => 0,
    ];

    # The columns of the material table, which no extra field of a type may be named after
    private const COLUMNS = [
        'id', 'tid', 'cid', 'uid', 'aname', 'ip', 'title', 'intro', 'body', 'field', 'poll', 'home', 'comon', 'pinned',
        'comnum', 'views', 'score', 'ratings', 'status', 'version', 'created', 'updated', 'published', 'expires',
    ];

    # The twelve pieces of a stored upload rule, in the order the rule string holds them; public, because the writer checks the same pieces
    public const UPLOAD = [
        'extensions', 'maxquota', 'maxbytes', 'maxwidth', 'maxheight', 'maxfiles', 'thumbwidth', 'moderfiles', 'userfiles', 'userupload', 'guestupload', 'guestfiles',
    ];

    # A canonical rating period, and the largest whole number of days it may hold
    private const PERIOD = '/^(?:0|[1-9][0-9]{0,18})$/D';

    # The format of an exported type definition and the exact keys of its type, which the export writes and the import of the writer accepts
    public const FORMAT = 'slaed.node';
    public const EXPORT = ['name', 'title', 'intro', 'ext', 'sort', 'settings', 'fields', 'uploads', 'rating'];

    # The columns of the type table
    private const TYPECOLS = 'id, name, title, intro, ext, active, sort, version, created, updated';

    # The main columns of a material read, without the text, the fields and the joined names
    private const COLS = 'n.id, n.tid, n.cid, n.uid, n.aname, n.ip, n.title, n.intro, n.poll, n.home, n.comon, n.pinned, n.comnum, n.views, n.score, n.ratings,'
        .' n.status, n.version, n.created, n.updated, n.published, n.expires';

    # The columns of a resource row in the order of its table
    private const ASSETS = [
        'id', 'nid', 'kind', 'role', 'src', 'name', 'title', 'intro', 'mime', 'size', 'width', 'height', 'duration', 'hits', 'reported', 'ruid', 'sort', 'created', 'updated',
    ];

    # How each kind of read applies the rules: the state, the dates, the category predicate with or without the language, and the list filters
    # A state of list follows setNodeStatus(), any lets a moderator read every state, pub reads only the published; no category predicate means the joined row is checked in PHP
    private const READS = [
        'item' => ['state' => 'any', 'time' => true, 'cats' => '', 'filter' => false],
        'target' => ['state' => 'pub', 'time' => true, 'cats' => '', 'filter' => false],
        'site' => ['state' => 'pub', 'time' => true, 'cats' => 'all', 'filter' => false],
        'parent' => ['state' => 'pub', 'time' => true, 'cats' => 'lang', 'filter' => false],
        'list' => ['state' => 'list', 'time' => true, 'cats' => 'lang', 'filter' => true],
        'dead' => ['state' => 'pub', 'time' => false, 'cats' => 'lang', 'filter' => true],
    ];

    private Database $db;
    private NodeContext $ctx;
    private Field $fld;
    private ?NodeExtension $ext = null;
    private array $types = [];
    private ?array $held = null;
    private bool $whole = false;
    private array $exts = [];
    private array $cats = [];
    private array $list = [];
    private int $cid = 0;
    private string $letter = '';
    private int $page = 1;
    private int $limit = 0;
    private int $author = 0;
    private ?NodeStatus $status = null;
    private array $order = [];
    private ?string $from = null;
    private ?string $until = null;
    private string $search = '';
    private bool $home = false;
    private bool $sets = true;

    # Keep the database, the request context and the shared field system the reads are decided with
    public function __construct(Database $db, NodeContext $context, Field $field) {
        $this->db = $db;
        $this->ctx = $context;
        $this->fld = $field;
    }

    # Report a stored configuration or row that cannot be read, so an administrator finds the type or material behind a missing result
    private function addNodeLog(string $text, array $ctx = []): void {
        Logger::addSite('error', 'Node: '.$text, $ctx);
    }

    # The refusal of a setting or an argument, with the path of the first error for the log
    private function getInvalid(string $path): NodeException {
        return new NodeException('Invalid node input: '.$path, NodeException::INVALID);
    }

    # Run one read and answer its rows by column name; a statement the server refused is a storage failure with the driver error as previous
    private function getQueryRows(string $sql, array $pars = []): array {
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) throw new NodeException('A node read failed', NodeException::STORAGE, $this->db->laste);
        return $res->fetchAll(PDO::FETCH_ASSOC);
    }

    # Bind a list of values as named values unique to the prefix and answer the placeholders for an IN list
    private function getInList(array $ids, string $pre, array &$pars): string {
        $out = [];
        foreach (array_values($ids) as $i => $id) {
            $out[] = ':'.$pre.$i;
            $pars[$pre.$i] = $id;
        }
        return implode(', ', $out);
    }

    # Escape the wildcards of a LIKE pattern with the escape character every pattern of this class declares
    private function getLikeText(string $text): string {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $text);
    }

    # Whether an array has exactly the given keys, in any order
    private function checkExactKeys(array $arr, array $keys): bool {
        $have = array_keys($arr);
        sort($have);
        sort($keys);
        return $have === $keys;
    }

    # Whether a value is a list of unique names out of the allowed set
    private function checkNameList(mixed $list, array $allow): bool {
        if (!is_array($list) || !array_is_list($list) || count(array_unique($list)) !== count($list)) return false;
        foreach ($list as $one) if (!is_string($one) || !in_array($one, $allow, true)) return false;
        return true;
    }

    # Whether a value is a list of unique positive integer ids
    private function checkIdList(mixed $list): bool {
        if (!is_array($list) || !array_is_list($list) || count(array_unique($list)) !== count($list)) return false;
        foreach ($list as $one) if (!is_int($one) || $one < 1) return false;
        return true;
    }

    # Whether a label is plain text without markup or the name of a language constant, within the length, and empty only where that is allowed
    private function checkLabelText(mixed $text, int $max, bool $empty): bool {
        if (!is_string($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > $max) return false;
        if ($text === '') return $empty;
        if ($text[0] === '_') return preg_match('/^_[A-Z][A-Z0-9_]*$/D', $text) === 1;
        return strip_tags($text) === $text && !preg_match('/[\x00-\x1F\x7F]/', $text);
    }

    # The system limits of a Node configuration, or an empty array when it is missing or broken
    private function getNodeLimits(mixed $node): array {
        if (!is_array($node) || ($node['version'] ?? null) !== '1' || !is_array($node['limits'] ?? null)) return [];
        $out = [];
        foreach (['maxassets', 'maxlist', 'syncbatch'] as $key) {
            $val = $node['limits'][$key] ?? null;
            if (!is_int($val) || $val < 1) return [];
            $out[$key] = $val;
        }
        return $out;
    }

    # Merge the stored differences of a type into the defaults: associative maps are merged key by key, a list replaces the default whole
    # An empty array over a map is no difference, an empty array over a list clears it
    private function getMergedArray(array $base, array $over): array {
        foreach ($over as $key => $val) {
            $deep = is_array($base[$key] ?? null) && $base[$key] !== [] && !array_is_list($base[$key]) && is_array($val) && ($val === [] || !array_is_list($val));
            $base[$key] = $deep ? $this->getMergedArray($base[$key], $val) : $val;
        }
        return $base;
    }

    # Check the list section: the allowed sorts, the default sort and direction, the page size within the system limit, the letter index and the shown metadata
    private function filterListRule(mixed $rule, array $lims): array {
        if (!is_array($rule) || !$this->checkExactKeys($rule, self::LISTKEYS)) throw $this->getInvalid('list');
        if (!$this->checkNameList($rule['orders'], self::ORDERS) || $rule['orders'] === []) throw $this->getInvalid('list.orders');
        if (!in_array($rule['order'], $rule['orders'], true)) throw $this->getInvalid('list.order');
        if (!in_array($rule['dir'], ['asc', 'desc'], true)) throw $this->getInvalid('list.dir');
        if (!is_int($rule['limit']) || $rule['limit'] < 1 || $rule['limit'] > $lims['maxlist']) throw $this->getInvalid('list.limit');
        if (!is_bool($rule['alpha'])) throw $this->getInvalid('list.alpha');
        if (!$this->checkNameList($rule['show'], self::SHOW)) throw $this->getInvalid('list.show');
        return ['orders' => $rule['orders'], 'order' => $rule['order'], 'dir' => $rule['dir'], 'limit' => $rule['limit'], 'alpha' => $rule['alpha'], 'show' => $rule['show']];
    }

    # Check the view section: one safe short name of the display set, never a path
    private function filterViewRule(mixed $rule): array {
        if (!is_array($rule) || !$this->checkExactKeys($rule, ['mode']) || !is_string($rule['mode']) || !preg_match(self::NAME, $rule['mode'])) throw $this->getInvalid('view');
        return ['mode' => $rule['mode']];
    }

    # Check the workflow section: who submits, the groups that submit and publish directly, and the two notices
    private function filterFlowRule(mixed $rule): array {
        if (!is_array($rule) || !$this->checkExactKeys($rule, ['access', 'groups', 'publish', 'notify'])) throw $this->getInvalid('workflow');
        if (!in_array($rule['access'], self::ACCESS, true)) throw $this->getInvalid('workflow.access');
        if (!$this->checkIdList($rule['groups'])) throw $this->getInvalid('workflow.groups');
        if (!$this->checkIdList($rule['publish'])) throw $this->getInvalid('workflow.publish');
        if ($rule['access'] === 'group' && $rule['groups'] === []) throw $this->getInvalid('workflow.groups');
        if ($rule['access'] === 'group' && array_diff($rule['publish'], $rule['groups'])) throw $this->getInvalid('workflow.publish');
        $note = $rule['notify'];
        $good = is_array($note) && $this->checkExactKeys($note, ['pending', 'result']) && is_bool($note['pending']) && is_bool($note['result']);
        if (!$good) throw $this->getInvalid('workflow.notify');
        return ['access' => $rule['access'], 'groups' => $rule['groups'], 'publish' => $rule['publish'], 'notify' => ['pending' => $note['pending'], 'result' => $note['result']]];
    }

    # Check the features section: exactly the twelve switches, each a native boolean
    private function filterFeatureRule(mixed $rule): array {
        if (!is_array($rule) || !$this->checkExactKeys($rule, self::FEATURES)) throw $this->getInvalid('features');
        $out = [];
        foreach (self::FEATURES as $key) {
            if (!is_bool($rule[$key])) throw $this->getInvalid('features.'.$key);
            $out[$key] = $rule[$key];
        }
        return $out;
    }

    # Check the integrations section: four native switches and the page kind of the existing head builder
    private function filterLinkRule(mixed $rule): array {
        if (!is_array($rule) || !$this->checkExactKeys($rule, self::INTEGRATE)) throw $this->getInvalid('integrations');
        $out = [];
        foreach (self::INTEGRATE as $key) {
            $good = ($key === 'seo') ? in_array($rule[$key], self::SEO, true) : is_bool($rule[$key]);
            if (!$good) throw $this->getInvalid('integrations.'.$key);
            $out[$key] = $rule[$key];
        }
        return $out;
    }

    # Check the roles of the assets section and answer them expanded to every key, ordered by sort and then by name
    # A mode takes the kinds it can show, the kinds of the role only narrow them, and the external link mode is one local-free role of exactly one resource
    private function filterAssetRule(mixed $rule, array $lims): array {
        if (!is_array($rule) || ($rule !== [] && array_is_list($rule))) throw $this->getInvalid('assets');
        $out = [];
        $links = 0;
        foreach ($rule as $role => $def) {
            $path = 'assets.'.$role;
            if (!is_string($role) || !preg_match(self::ROLE, $role) || !is_array($def)) throw $this->getInvalid($path);
            if (array_diff(array_keys($def), array_keys(self::ROLEDEF)) || !isset($def['title'], $def['mode'], $def['max'])) throw $this->getInvalid($path);
            $one = array_replace(self::ROLEDEF, $def);
            if (!$this->checkLabelText($one['title'], 255, false)) throw $this->getInvalid($path.'.title');
            if (!$this->checkLabelText($one['intro'], 1000, true)) throw $this->getInvalid($path.'.intro');
            if (!$this->checkNameList($one['kinds'], self::KINDS)) throw $this->getInvalid($path.'.kinds');
            $exts = $one['extensions'];
            if (!is_array($exts) || !array_is_list($exts) || count(array_unique($exts)) !== count($exts)) throw $this->getInvalid($path.'.extensions');
            foreach ($exts as $ext) if (!is_string($ext) || !preg_match('/^[a-z0-9]{1,10}$/D', $ext)) throw $this->getInvalid($path.'.extensions');
            if ($one['maxbytes'] !== null && (!is_int($one['maxbytes']) || $one['maxbytes'] < 1)) throw $this->getInvalid($path.'.maxbytes');
            if (!is_int($one['min']) || $one['min'] < 0) throw $this->getInvalid($path.'.min');
            if (!is_int($one['max']) || $one['max'] < 1 || $one['max'] < $one['min'] || $one['max'] > $lims['maxassets']) throw $this->getInvalid($path.'.max');
            foreach (['canlink', 'report', 'active'] as $key) if (!is_bool($one[$key])) throw $this->getInvalid($path.'.'.$key);
            if (!is_int($one['sort']) || $one['sort'] < 0) throw $this->getInvalid($path.'.sort');
            if (!is_string($one['mode']) || !isset(self::RMODES[$one['mode']])) throw $this->getInvalid($path.'.mode');
            if (!array_intersect(self::RMODES[$one['mode']], $one['kinds'] ?: self::KINDS)) throw $this->getInvalid($path.'.kinds');
            $link = $one['mode'] === 'link';
            if ($link && (!$one['canlink'] || $one['max'] !== 1 || $exts !== [] || $one['maxbytes'] !== null || ++$links > 1)) throw $this->getInvalid($path.'.mode');
            $out[$role] = $one;
        }
        uksort($out, fn($a, $b) => [$out[$a]['sort'], $a] <=> [$out[$b]['sort'], $b]);
        return $out;
    }

    # Create the extension of a key once per instance through the closed factory; an unknown key is refused by the factory itself
    private function getFactoryExt(string $key): NodeExtension {
        if (!isset($this->exts[$key])) {
            require_once __DIR__.'/ext/load.php';
            $this->exts[$key] = getNodeExtension($key, $this->db, $this->ctx);
        }
        return $this->exts[$key];
    }

    # Check the stored settings of one type against a Node configuration and answer the canonical effective settings
    private function filterTypeSettings(mixed $node, string $ext, array $settings, array $fields): array {
        $lims = $this->getNodeLimits($node);
        $base = $node['defaults'] ?? null;
        if (!$lims || !is_array($base)) throw $this->getInvalid('node');
        foreach ($base as $key => $val) if (!in_array($key, self::SECTIONS, true) || $key === 'ext' || !is_array($val)) throw $this->getInvalid('defaults.'.$key);
        foreach ($settings as $key => $val) if (!in_array($key, self::SECTIONS, true) || !is_array($val)) throw $this->getInvalid($key);
        foreach (array_keys($fields) as $name) if (in_array($name, self::COLUMNS, true)) throw $this->getInvalid('fields.'.$name);
        $full = $this->getMergedArray($base, $settings);
        foreach (['form', 'admin'] as $key) if (($full[$key] ?? []) !== []) throw $this->getInvalid($key);
        $out = [
            'list' => $this->filterListRule($full['list'] ?? null, $lims),
            'view' => $this->filterViewRule($full['view'] ?? null),
            'form' => [],
            'workflow' => $this->filterFlowRule($full['workflow'] ?? null),
            'admin' => [],
            'features' => $this->filterFeatureRule($full['features'] ?? null),
            'assets' => $this->filterAssetRule($full['assets'] ?? [], $lims),
            'integrations' => $this->filterLinkRule($full['integrations'] ?? null),
        ];
        $own = $full['ext'] ?? [];
        if ($ext === '') {
            if ($own !== []) throw $this->getInvalid('ext');
            $out['ext'] = [];
        } else {
            $out['ext'] = $this->getFactoryExt($ext)->filterNodeConfig($own, $out, $fields);
        }
        return $out;
    }

    # Check the stored settings of one type, its differences from the defaults without the version, and answer the canonical effective settings
    # The same check guards the reading of a stored type and every write of one; the extension settings go through the filter of the registered extension
    public function filterNodeSettings(string $ext, array $settings, array $fields): array {
        global $conf;
        return $this->filterTypeSettings($conf['node'] ?? null, $ext, $settings, $fields);
    }

    # The stored upload rule of a type as its twelve named pieces, or an empty array for a rule that is missing or broken
    private function getUploadRule(mixed $rule): array {
        if (!is_string($rule)) return [];
        $part = explode('|', $rule);
        if (count($part) !== count(self::UPLOAD) || !preg_match('/^(?:[a-z0-9]+(?:,[a-z0-9]+)*)?$/D', $part[0])) return [];
        $out = ['extensions' => $part[0]];
        foreach (array_slice(self::UPLOAD, 1) as $i => $key) {
            if (!preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $part[$i + 1])) return [];
            $out[$key] = intval($part[$i + 1]);
        }
        return $out;
    }

    # Whether the stored rating rule of a type has exactly the four keys in the stored string form the rating subsystem reads
    private function checkRateRule(mixed $rule): bool {
        if (!is_array($rule) || !$this->checkExactKeys($rule, ['active', 'period', 'detail', 'guests'])) return false;
        foreach (['active', 'detail', 'guests'] as $key) if (!in_array($rule[$key], ['0', '1'], true)) return false;
        $max = (string)(intdiv(PHP_INT_MAX, 86400) * 86400);
        $per = $rule['period'];
        return is_string($per) && preg_match(self::PERIOD, $per) && (strlen($per) < strlen($max) || (strlen($per) === strlen($max) && strcmp($per, $max) <= 0));
    }

    # The types an unfinished configuration operation has touched, read from its marker once per instance; an unreadable marker holds every type
    # The file status is asked afresh, because the marker is written and removed by other processes than a long-running reader
    private function getHeldTypes(): array {
        if ($this->held === null) {
            $file = BACKUP_DIR.'/config/marker.json';
            clearstatcache(true, $file);
            $mark = is_file($file) ? json_decode((string)file_get_contents($file), true) : ['types' => []];
            $this->held = is_array($mark['types'] ?? null) ? array_values(array_filter($mark['types'], 'is_string')) : ['*'];
        }
        return $this->held;
    }

    # Assemble one type from its row and a configuration, answer null for a type that fails and false for a section the configuration does not carry at the version of the row
    # A type an unfinished configuration operation holds serves nobody until the operation is restored, because its database row and its sources may belong to different sides
    private function getTypeModel(array $row, array $cfg): NodeType|false|null {
        $name = (string)$row['name'];
        $sect = $cfg['node']['types'][$name] ?? null;
        $held = $this->getHeldTypes();
        if (!preg_match(self::NAME, $name) || in_array($name, $held, true) || $held === ['*']) {
            $this->addNodeLog('a type is invalid or held by an unfinished configuration operation', ['name' => $name]);
            return null;
        }
        if (!is_array($sect) || ($sect['version'] ?? null) !== intval($row['version'])) return false;
        unset($sect['version']);
        $defs = $cfg['fields']['node'][$name] ?? [];
        $uploads = $this->getUploadRule($cfg['uploads'][$name] ?? null);
        $rate = $cfg['ratings']['node.'.$name] ?? null;
        if (!$uploads) $this->addNodeLog('the upload rule of a type is missing or invalid, its uploads are blocked', ['name' => $name]);
        if (!$this->checkRateRule($rate)) {
            $this->addNodeLog('the rating rule of a type is missing or invalid, its rating is blocked', ['name' => $name]);
            $rate = [];
        }
        try {
            if (!is_array($defs)) throw $this->getInvalid('fields');
            $fields = $this->fld->filterFieldList($defs);
            $set = $this->filterTypeSettings($cfg['node'], (string)$row['ext'], $sect, $fields);
        } catch (NodeException|InvalidArgumentException $err) {
            $this->addNodeLog('the stored configuration of a type is invalid', ['name' => $name, 'path' => $err->getMessage()]);
            return null;
        }
        return new NodeType(intval($row['id']), $name, (string)$row['title'], (string)$row['intro'], (string)$row['ext'], (bool)$row['active'], intval($row['sort']),
            intval($row['version']), (string)$row['created'], (string)$row['updated'], $set, $fields, $uploads, $rate);
    }

    # Read the rows of the named types, or of every type when no name is given, ordered as the administration lists them
    private function getTypeRows(array $names): array {
        $pars = [];
        $where = $names ? ' WHERE name IN ('.$this->getInList($names, 't', $pars).')' : '';
        return $this->getQueryRows('SELECT '.self::TYPECOLS.' FROM '.PREFIX_DB.'_node_types'.$where.' ORDER BY sort, id', $pars);
    }

    # Assemble rows into the memory of the instance; a missing section or a version that differs makes the shared configuration be read once more,
    # which is how a type the request itself has just written is read back; only a version that still differs reads those rows once more, and then fails the type
    private function setTypeRows(array $rows, array $names): void {
        global $conf;
        $stale = [];
        foreach ($rows as $row) {
            $one = $this->getTypeModel($row, $conf);
            if ($one === false) $stale[] = $row;
            else $this->types[(string)$row['name']] = $one ?? false;
        }
        $again = [];
        $fresh = $stale ? getConfig() : [];
        foreach ($stale as $row) {
            $name = (string)$row['name'];
            $one = $this->getTypeModel($row, $fresh);
            if ($one === false && is_array($fresh['node']['types'][$name] ?? null)) $again[] = $name;
            elseif ($one === false) $this->addNodeLog('a type has no configuration', ['name' => $name]);
            $this->types[$name] = $one ?: false;
        }
        foreach ($again ? $this->getTypeRows($again) : [] as $row) {
            $one = $this->getTypeModel($row, $fresh);
            if ($one === false) $this->addNodeLog('the version of a type differs between the database and the configuration', ['name' => $row['name']]);
            $this->types[(string)$row['name']] = $one ?: false;
        }
        foreach (array_merge($names, $again) as $name) $this->types[$name] ??= false;
    }

    # Whether the context may receive a type: an active one always, a disabled one only by the main administrator, the Node manager and a moderator of the type
    private function checkTypeView(NodeType $type): bool {
        return $type->active || $this->ctx->super || $this->ctx->manage || in_array($type->name, $this->ctx->mods, true);
    }

    # Whether the context moderates the type: the main administrator or a moderator named by the type
    private function checkModer(NodeType $type): bool {
        return $this->ctx->super || in_array($type->name, $this->ctx->mods, true);
    }

    # Read one type by its public name; a missing, broken or, for a public context, disabled type answers null, and a second call uses the memory of the instance
    public function getNodeType(string $name): ?NodeType {
        if (!preg_match(self::NAME, $name)) return null;
        if (!array_key_exists($name, $this->types) && !$this->whole) $this->setTypeRows($this->getTypeRows([$name]), [$name]);
        $type = $this->types[$name] ?? false;
        return ($type && $this->checkTypeView($type)) ? $type : null;
    }

    # Read every type the context may receive with one statement, ordered by sort and id
    public function getNodeTypeList(): array {
        if (!$this->whole) {
            $this->setTypeRows($this->getTypeRows([]), []);
            $this->whole = true;
        }
        $out = array_values(array_filter($this->types, fn($v) => $v && $this->checkTypeView($v)));
        usort($out, fn($a, $b) => [$a->sort, $a->id] <=> [$b->sort, $b->id]);
        return $out;
    }

    # Export one type the context manages as the canonical JSON of the slaed.node format: the nine keys of the definition, no id, state, version, dates, materials or paths
    # A type whose upload or rating rule is broken is refused, because the file would not bring the same type back
    public function getNodeTypeExport(string $name): string {
        if (!$this->ctx->manage && !$this->ctx->super) throw new NodeException('The context does not manage node types', NodeException::DENIED);
        $type = $this->getNodeType($name);
        if ($type === null) throw new NodeException('The node type does not exist', NodeException::NOTFOUND);
        if ($type->uploads === [] || $type->rating === []) throw $this->getInvalid('export');
        $vals = [$type->name, $type->title, $type->intro, $type->ext, $type->sort, $type->settings, $type->fields, $type->uploads, $type->rating];
        $data = ['format' => self::FORMAT, 'version' => 1, 'type' => array_combine(self::EXPORT, $vals)];
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
    }

    # Whether a category read string grants the context: an empty string never, a group list by intersection with the groups, otherwise the level 0 of a guest or 1 of a user
    private function checkCatRead(string $pread): bool {
        [$lvl, $ids] = array_pad(explode('|', $pread, 2), 2, '');
        if ($pread === '' || !ctype_digit($lvl)) return false;
        $gids = array_values(array_filter(array_map('intval', explode(',', $ids)), fn($v) => $v > 0));
        if ($gids) return (bool)array_intersect($gids, $this->ctx->groups);
        return intval($lvl) <= ($this->ctx->uid > 0 ? 1 : 0);
    }

    # Whether the language of a category passes the language of the context: always when the filter is off, the context has no language or the category is shared
    private function checkCatLang(string $lang, bool $apply): bool {
        return !$apply || $this->ctx->lang === '' || $lang === '' || $lang === $this->ctx->lang;
    }

    # The categories of a type as id => read right and language, read with one statement per type and instance
    private function getCatMap(NodeType $type): array {
        if (!isset($this->cats[$type->id])) {
            $map = [];
            foreach ($this->getQueryRows('SELECT id, pread, lang FROM '.PREFIX_DB.'_categories WHERE modul = :modul', ['modul' => $type->name]) as $row) {
                $map[intval($row['id'])] = ['read' => $this->checkCatRead((string)$row['pread']), 'lang' => (string)$row['lang']];
            }
            $this->cats[$type->id] = $map;
        }
        return $this->cats[$type->id];
    }

    # The categories of a type the context may read, without the read right for a moderator and within the language of the context when asked
    private function getCatAllow(NodeType $type, bool $lang): array {
        $moder = $this->checkModer($type);
        $out = [];
        foreach ($this->getCatMap($type) as $cid => $cat) if (($moder || $cat['read']) && $this->checkCatLang($cat['lang'], $lang)) $out[] = $cid;
        return $out;
    }

    # Whether the joined main category of one read row grants the context: a moderator passes, a material without category is open, the rest needs a readable category of the type
    private function checkRowCat(array $row, NodeType $type): bool {
        if ($this->checkModer($type) || intval($row['cid']) === 0) return true;
        if (!$type->settings['features']['categories'] || $row['cread'] === null || $row['cmod'] !== $type->name) return false;
        return $this->checkCatRead((string)$row['cread']);
    }

    # The extension a single type is read through: none for a standard type, and for a type with an extension the assigned instance of exactly the registered class
    private function getOwnExt(NodeType $type): ?NodeExtension {
        if ($type->ext === '') {
            if ($this->ext !== null) throw $this->getInvalid('extension');
            return null;
        }
        if ($this->ext === null || get_class($this->ext) !== get_class($this->getFactoryExt($type->ext))) throw $this->getInvalid('extension');
        return $this->ext;
    }

    # Ask the extension for its trusted join and condition and give every occurrence of each bound value a name unique to the branch; a malformed answer is refused
    private function getScopeSql(NodeType $type, ?NodeExtension $ext, string $key): array {
        if ($ext === null) return ['', '', []];
        $scope = $ext->getNodeScope($type);
        if (!$this->checkExactKeys($scope, ['join', 'where', 'params']) || !is_string($scope['join']) || !is_string($scope['where']) || !is_array($scope['params'])) {
            throw $this->getInvalid('scope');
        }
        $join = $scope['join'];
        $cond = $scope['where'];
        $pars = [];
        foreach ($scope['params'] as $name => $val) {
            $good = is_string($name) && preg_match(self::PARAM, $name) && (is_int($val) || is_string($val) || is_bool($val) || $val === null);
            if (!$good) throw $this->getInvalid('scope.params');
            $idx = 0;
            $swap = function () use ($key, $name, $val, &$idx, &$pars): string {
                $idx++;
                $pars[$key.'x'.$name.'o'.$idx] = $val;
                return ':'.$key.'x'.$name.'o'.$idx;
            };
            $join = preg_replace_callback('/:'.$name.'(?![A-Za-z0-9_])/', $swap, $join);
            $cond = preg_replace_callback('/:'.$name.'(?![A-Za-z0-9_])/', $swap, $cond);
            if ($idx === 0) throw $this->getInvalid('scope.params.'.$name);
        }
        return [$join === '' ? '' : ' '.$join, $cond, $pars];
    }

    # Add the list filters of the instance to the conditions of one type branch; a filter the settings of the type do not allow is refused
    private function addFilterSql(NodeType $type, string $key, array &$where, array &$pars): void {
        $set = $type->settings;
        if ($this->cid > 0) {
            if (!$set['features']['categories']) throw $this->getInvalid('category');
            $good = in_array($this->cid, $this->getCatAllow($type, true), true);
            $where[] = $good ? '(n.cid = :'.$key.'q OR EXISTS (SELECT 1 FROM '.PREFIX_DB.'_node_categories AS nc WHERE nc.nid = n.id AND nc.cid = :'.$key.'r))' : '1 = 0';
            if ($good) $pars += [$key.'q' => $this->cid, $key.'r' => $this->cid];
        }
        if ($this->letter !== '') {
            if (!$set['list']['alpha']) throw $this->getInvalid('letter');
            $where[] = 'n.title LIKE :'.$key.'l ESCAPE \'!\'';
            $pars[$key.'l'] = $this->getLikeText($this->letter).'%';
        }
        if ($this->author > 0) {
            $where[] = 'n.uid = :'.$key.'a';
            $pars[$key.'a'] = $this->author;
        }
        if ($this->from !== null) {
            $where[] = 'n.published >= :'.$key.'f';
            $pars[$key.'f'] = $this->from;
        }
        if ($this->until !== null) {
            $where[] = 'n.published < :'.$key.'u';
            $pars[$key.'u'] = $this->until;
        }
        if ($this->search !== '') {
            $where[] = '(n.title LIKE :'.$key.'w ESCAPE \'!\' OR n.intro LIKE :'.$key.'v ESCAPE \'!\' OR n.body LIKE :'.$key.'y ESCAPE \'!\')';
            $text = '%'.$this->getLikeText($this->search).'%';
            $pars += [$key.'w' => $text, $key.'v' => $text, $key.'y' => $text];
        }
        if ($this->home) {
            if (!$set['features']['home']) throw $this->getInvalid('home');
            $where[] = 'n.home = 1';
        }
    }

    # Compile the conditions one type contributes to a read, with value names unique to the branch key: type, state and dates, category rights, filters and extension scope
    private function getBranchSql(NodeType $type, string $key, ?NodeExtension $ext, string $read): array {
        $rule = self::READS[$read];
        $moder = $this->checkModer($type);
        $where = ['n.tid = :'.$key.'t'];
        $pars = [$key.'t' => $type->id];
        if (!$type->active && !$moder) $where[] = '1 = 0';
        $state = ($rule['state'] === 'list') ? $this->status : null;
        if (($rule['state'] === 'any' && $moder) || ($state !== null && $moder)) {
            if ($state !== null) {
                $where[] = 'n.status = :'.$key.'s';
                $pars[$key.'s'] = $state->value;
            }
        } else {
            if ($state !== null && $state !== NodeStatus::Published) $where[] = '1 = 0';
            $where[] = 'n.status = :'.$key.'s';
            $pars[$key.'s'] = NodeStatus::Published->value;
            if ($rule['time']) $where[] = 'n.published <= NOW() AND (n.expires IS NULL OR n.expires > NOW())';
        }
        if ($rule['cats'] !== '') {
            $lang = $rule['cats'] === 'lang';
            if (!$type->settings['features']['categories']) {
                if (!$moder) $where[] = 'n.cid = 0';
            } elseif (!$moder || ($lang && $this->ctx->lang !== '')) {
                $ids = $this->getCatAllow($type, $lang);
                $where[] = $ids ? '(n.cid = 0 OR n.cid IN ('.$this->getInList($ids, $key.'c', $pars).'))' : 'n.cid = 0';
            }
        }
        if ($rule['filter']) $this->addFilterSql($type, $key, $where, $pars);
        [$join, $cond, $more] = $this->getScopeSql($type, $ext, $key);
        if ($cond !== '') $where[] = '('.$cond.')';
        return ['type' => $type, 'join' => $join, 'where' => implode(' AND ', $where), 'pars' => $pars + $more];
    }

    # The selected types with the extension each one is read through: the assigned instance for a single type, the shared factory for a mixed selection
    private function getTypeSet(): array {
        if (!$this->list) throw $this->getInvalid('type');
        if (count($this->list) === 1) return [[$this->list[0], $this->getOwnExt($this->list[0])]];
        if ($this->ext !== null) throw $this->getInvalid('extension');
        return array_map(fn($v) => [$v, $v->ext === '' ? null : $this->getFactoryExt($v->ext)], $this->list);
    }

    # Compile every branch of the current selection for one kind of read
    private function getListParts(string $read): array {
        $out = [];
        foreach ($this->getTypeSet() as $i => [$type, $ext]) $out[] = $this->getBranchSql($type, 'b'.$i, $ext, $read);
        return $out;
    }

    # The order of the current selection over the columns of a read row: pinned first where the feature is on, then the chosen or default sort with the id breaking ties
    private function getOrderSql(string $pre): string {
        $one = count($this->list) === 1 ? $this->list[0]->settings['list'] : null;
        [$key, $dir] = $this->order ?: ($one ? [$one['order'], $one['dir']] : ['published', 'desc']);
        $pin = false;
        foreach ($this->list as $type) {
            if ($this->order && !in_array($key, $type->settings['list']['orders'], true)) throw $this->getInvalid('order');
            $pin = $pin || $type->settings['features']['pinned'];
        }
        $dir = strtoupper($dir);
        $cols = match ($key) {
            'views' => [['views', $dir], ['published', 'DESC'], ['id', 'DESC']],
            'rating' => [['rnone', 'ASC'], ['ravg', $dir], ['ratings', $dir], ['published', 'DESC'], ['id', 'DESC']],
            default => [[$key, $dir], ['id', $dir]],
        };
        if ($pin) array_unshift($cols, ['pin', 'DESC']);
        return implode(', ', array_map(fn($v) => $pre.$v[0].' '.$v[1], $cols));
    }

    # The page size of the current selection: the requested size within the system limit and the page size of every selected type, or that smallest size by default
    private function getPageSize(): int {
        global $conf;
        $lims = $this->getNodeLimits($conf['node'] ?? null);
        $max = $lims['maxlist'] ?? 0;
        foreach ($this->list as $type) $max = min($max, $type->settings['list']['limit']);
        if ($this->limit > $max) throw $this->getInvalid('limit');
        return $this->limit ?: $max;
    }

    # Whether the type has an active extra field, which is when a list reads and decodes the field column
    private function checkFieldUse(NodeType $type): bool {
        foreach ($type->fields as $def) if ($def['active']) return true;
        return false;
    }

    # Whether the type has an active resource role, which is when a list loads the resources of its page
    private function checkAssetUse(NodeType $type): bool {
        foreach ($type->settings['assets'] as $def) if ($def['active']) return true;
        return false;
    }

    # Decode the stored field values of one material; a value that is no JSON object is reported and read as empty
    private function getFieldValues(string $json, int $id): array {
        if ($json === '') return [];
        $vals = json_decode($json, true);
        if (!is_array($vals) || ($vals !== [] && array_is_list($vals))) {
            $this->addNodeLog('the field values of a material are no JSON object', ['nid' => $id]);
            return [];
        }
        return $vals;
    }

    # Build one material from a read row and the sets loaded for it; a stored state or comment mode outside the enums is reported and the row is skipped
    private function getNodeModel(array $row, NodeType $type, bool $body, bool $fields, ?array $cids, ?array $rels, ?array $assets): ?Node {
        $id = intval($row['id']);
        $state = NodeStatus::tryFrom(intval($row['status']));
        $mode = CommentMode::tryFrom(intval($row['comon']));
        if ($state === null || $mode === null) {
            $this->addNodeLog('a material carries an unknown state or comment mode', ['nid' => $id]);
            return null;
        }
        return new Node($id, intval($row['tid']), intval($row['cid']), intval($row['uid']), (string)$row['aname'], $this->checkModer($type) ? (string)$row['ip'] : null,
            (string)$row['title'], (string)$row['intro'], $body ? (string)$row['body'] : null, $fields ? $this->getFieldValues((string)$row['field'], $id) : null,
            intval($row['poll']), (bool)$row['home'], $mode, (bool)$row['pinned'], intval($row['comnum']), intval($row['views']), intval($row['score']), intval($row['ratings']),
            $state, intval($row['version']), (string)$row['created'], (string)$row['updated'], $row['published'] === null ? null : (string)$row['published'],
            $row['expires'] === null ? null : (string)$row['expires'], $cids, $rels, $assets, $row['uname'] === null ? null : (string)$row['uname'],
            $row['ctitle'] === null ? null : (string)$row['ctitle']);
    }

    # Build one resource from its row, the unknown metadata as null
    private function getAssetModel(array $row): NodeAsset {
        $num = fn($v) => $v === null ? null : intval($v);
        return new NodeAsset(intval($row['id']), intval($row['nid']), (string)$row['kind'], (string)$row['role'], (string)$row['src'], (string)$row['name'], (string)$row['title'],
            (string)$row['intro'], $row['mime'] === null ? null : (string)$row['mime'], $num($row['size']), $num($row['width']), $num($row['height']), $num($row['duration']),
            intval($row['hits']), $row['reported'] === null ? null : (string)$row['reported'], intval($row['ruid']), intval($row['sort']), (string)$row['created'],
            (string)$row['updated']);
    }

    # The extra categories of a set of materials with one statement, every asked id present with a list that may be empty
    private function getCatSets(array $ids): array {
        if (!$ids) return [];
        $pars = [];
        $out = array_fill_keys($ids, []);
        $sql = 'SELECT nid, cid FROM '.PREFIX_DB.'_node_categories WHERE nid IN ('.$this->getInList($ids, 'i', $pars).') ORDER BY nid, cid';
        foreach ($this->getQueryRows($sql, $pars) as $row) $out[intval($row['nid'])][] = intval($row['cid']);
        return $out;
    }

    # The relations of a set of materials with one statement, every asked id present with a list that may be empty
    private function getRelSets(array $ids): array {
        if (!$ids) return [];
        $pars = [];
        $out = array_fill_keys($ids, []);
        $sql = 'SELECT id, nid, rid, type, sort, created FROM '.PREFIX_DB.'_node_relations WHERE nid IN ('.$this->getInList($ids, 'i', $pars).') ORDER BY nid, type, sort, rid';
        foreach ($this->getQueryRows($sql, $pars) as $row) {
            $rel = new NodeRelation(intval($row['id']), intval($row['nid']), intval($row['rid']), (string)$row['type'], intval($row['sort']), (string)$row['created']);
            $out[$rel->nid][] = $rel;
        }
        return $out;
    }

    # The resources of a set of materials with one statement, every asked id present with a list that may be empty
    private function getAssetSets(array $ids): array {
        if (!$ids) return [];
        $pars = [];
        $out = array_fill_keys($ids, []);
        $sql = 'SELECT '.implode(', ', self::ASSETS).' FROM '.PREFIX_DB.'_node_assets WHERE nid IN ('.$this->getInList($ids, 'i', $pars).') ORDER BY nid, role, sort, id';
        foreach ($this->getQueryRows($sql, $pars) as $row) $out[intval($row['nid'])][] = $this->getAssetModel($row);
        return $out;
    }

    # Build the materials of one list page and load, one statement each, the sets the settings of their types ask for
    private function getListNodes(array $rows): array {
        $tmap = [];
        foreach ($this->list as $type) $tmap[$type->id] = $type;
        $want = ['cids' => [], 'rels' => [], 'assets' => []];
        foreach ($this->sets ? $rows : [] as $row) {
            $type = $tmap[intval($row['tid'])];
            $id = intval($row['id']);
            if ($type->settings['features']['categories']) $want['cids'][] = $id;
            if ($type->settings['features']['related'] || $type->settings['features']['tree']) $want['rels'][] = $id;
            if ($this->checkAssetUse($type)) $want['assets'][] = $id;
        }
        $cids = $this->getCatSets($want['cids']);
        $rels = $this->getRelSets($want['rels']);
        $assets = $this->getAssetSets($want['assets']);
        $out = [];
        foreach ($rows as $row) {
            $id = intval($row['id']);
            $type = $tmap[intval($row['tid'])];
            $node = $this->getNodeModel($row, $type, false, $this->sets && $this->checkFieldUse($type), $cids[$id] ?? null, $rels[$id] ?? null, $assets[$id] ?? null);
            if ($node) $out[] = $node;
        }
        return $out;
    }

    # Assign the registered extension of the next single-type read; a standard type reads with null, and a mismatch is refused when the read runs
    public function setNodeExtension(?NodeExtension $ext): self {
        $this->ext = $ext;
        return $this;
    }

    # Limit the selection to one type the context may read: an active type, or a disabled one for its moderator
    public function setNodeType(NodeType $type): self {
        if (!$type->active && !$this->checkModer($type)) throw $this->getInvalid('type');
        $this->list = [$type];
        return $this;
    }

    # Limit the selection to a mixed set of types the context may read, each read through the extension the shared factory makes for it
    public function setNodeTypes(array $types): self {
        $ids = [];
        foreach ($types as $type) {
            if (!$type instanceof NodeType || isset($ids[$type->id]) || (!$type->active && !$this->checkModer($type))) throw $this->getInvalid('types');
            $ids[$type->id] = true;
        }
        if (!$types) throw $this->getInvalid('types');
        $this->list = array_values($types);
        return $this;
    }

    # Limit the selection to the materials whose main or extra category is the given one, the read right of the main category still required
    public function setNodeCategory(int $cid): self {
        if ($cid < 1) throw $this->getInvalid('category');
        $this->cid = $cid;
        return $this;
    }

    # Limit the selection to titles starting with one letter or digit regardless of case, or lift the limit with an empty string
    public function setNodeLetter(string $letter): self {
        if ($letter !== '' && !preg_match('/^[\p{L}\p{N}]$/Du', $letter)) throw $this->getInvalid('letter');
        $this->letter = $letter;
        return $this;
    }

    # Choose the page from 1 and its size; the size is checked against the system limit and the page size of the selected types when the read runs
    public function setNodePage(int $page, int $limit): self {
        if ($page < 1 || $limit < 1 || $page > intdiv(4294967295, $limit) + 1) throw $this->getInvalid('page');
        $this->page = $page;
        $this->limit = $limit;
        return $this;
    }

    # Choose whether the lists of the reader carry the extra fields and the categories, relations and resources of their page; a table that shows none of them
    # switches them off and keeps the three statements of type, count and page, and the models then hold null for every set that was not loaded
    public function setNodeSets(bool $load): self {
        $this->sets = $load;
        return $this;
    }

    # Limit the selection to one registered author
    public function setNodeAuthor(int $uid): self {
        if ($uid < 1) throw $this->getInvalid('author');
        $this->author = $uid;
        return $this;
    }

    # Limit the selection to one state; only a moderator of a type reads a state other than published, everyone else gets the intersection with published
    public function setNodeStatus(NodeStatus $status): self {
        $this->status = $status;
        return $this;
    }

    # Choose one of the closed sort keys and a direction; the key must also be allowed by every selected type when the read runs
    public function setNodeOrder(string $order, string $dir = 'desc'): self {
        if (!in_array($order, self::ORDERS, true) || !in_array($dir, ['asc', 'desc'], true)) throw $this->getInvalid('order');
        $this->order = [$order, $dir];
        return $this;
    }

    # Whether a string is a canonical database date and time of the calendar
    private function checkDateText(string $text): bool {
        if (!preg_match(self::DATE, $text, $hit)) return false;
        return checkdate(intval($hit[2]), intval($hit[3]), intval($hit[1])) && intval($hit[4]) < 24 && intval($hit[5]) < 60 && intval($hit[6]) < 60;
    }

    # Limit the publication date to the half-open interval from, until; one bound may be null, never both, and a non-empty interval is required
    public function setNodePublished(?string $from, ?string $until): self {
        if ($from === null && $until === null) throw $this->getInvalid('published');
        foreach ([$from, $until] as $one) if ($one !== null && !$this->checkDateText($one)) throw $this->getInvalid('published');
        if ($from !== null && $until !== null && strcmp($from, $until) >= 0) throw $this->getInvalid('published');
        $this->from = $from;
        $this->until = $until;
        return $this;
    }

    # Limit the selection to a literal substring of the title, the intro or the body, up to 255 characters, or lift the limit with an empty string
    public function setNodeSearch(string $text): self {
        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 255) throw $this->getInvalid('search');
        $this->search = $text;
        return $this;
    }

    # Limit the selection to the materials marked for the home page of types with the home feature, or lift the limit
    public function setNodeHome(bool $home = true): self {
        $this->home = $home;
        return $this;
    }

    # The select of one list branch: the main columns, the fields where the type uses them, the author and category names and the order helpers
    private function getListSelect(array $part): string {
        $type = $part['type'];
        return 'SELECT '.self::COLS.', '.(($this->sets && $this->checkFieldUse($type)) ? 'n.field' : '\'\'').' AS field, u.name AS uname, c.title AS ctitle, '
            .($type->settings['features']['pinned'] ? 'n.pinned' : '0').' AS pin, (n.ratings = 0) AS rnone, n.score / NULLIF(n.ratings, 0) AS ravg'
            .' FROM '.PREFIX_DB.'_nodes AS n LEFT JOIN '.PREFIX_DB.'_users AS u ON u.id = n.uid AND n.uid > 0'
            .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0'.$part['join'].' WHERE '.$part['where'];
    }

    # Read one page of the selection; a mixed selection unions the branches, each cut to the page end, and orders and pages the union once more
    public function getNodeList(): array {
        $parts = $this->getListParts('list');
        $size = $this->getPageSize();
        $skip = ($this->page - 1) * $size;
        $ord = $this->getOrderSql('');
        $pars = [];
        foreach ($parts as $part) $pars += $part['pars'];
        if (count($parts) === 1) {
            $sql = $this->getListSelect($parts[0]).' ORDER BY '.$ord.' LIMIT '.$skip.', '.$size;
        } else {
            $sql = 'SELECT q.* FROM ('.implode(' UNION ALL ', array_map(fn($v) => '('.$this->getListSelect($v).' ORDER BY '.$ord.' LIMIT '.($skip + $size).')', $parts))
                .') AS q ORDER BY '.$this->getOrderSql('q.').' LIMIT '.$skip.', '.$size;
        }
        return $this->getListNodes($this->getQueryRows($sql, $pars));
    }

    # Count the selection with exactly the conditions of the list, without paging and order
    public function getNodeCount(): int {
        $parts = $this->getListParts('list');
        $pars = [];
        $sql = [];
        foreach ($parts as $part) {
            $sql[] = 'SELECT COUNT(*) AS num FROM '.PREFIX_DB.'_nodes AS n'.$part['join'].' WHERE '.$part['where'];
            $pars += $part['pars'];
        }
        $query = count($sql) === 1 ? $sql[0] : 'SELECT SUM(q.num) AS num FROM ('.implode(' UNION ALL ', $sql).') AS q';
        return intval($this->getQueryRows($query, $pars)[0]['num'] ?? 0);
    }

    # The next moment the published selection changes on its own: the nearest future publication or expiry among the materials the list would show once their time comes
    # One aggregate statement answers a Unix time or null; the rights, filters and scope stay, only the time condition is lifted
    public function getNodeDeadline(): ?int {
        $parts = $this->getListParts('dead');
        $pars = [];
        $sql = [];
        foreach ($parts as $part) {
            $sql[] = 'SELECT UNIX_TIMESTAMP(MIN(CASE WHEN n.published > NOW() THEN n.published END)) AS pa,'
                .' UNIX_TIMESTAMP(MIN(CASE WHEN n.expires > NOW() THEN n.expires END)) AS pb'
                .' FROM '.PREFIX_DB.'_nodes AS n'.$part['join'].' WHERE '.$part['where'];
            $pars += $part['pars'];
        }
        $query = count($sql) === 1 ? $sql[0] : 'SELECT MIN(q.pa) AS pa, MIN(q.pb) AS pb FROM ('.implode(' UNION ALL ', $sql).') AS q';
        $row = $this->getQueryRows($query, $pars)[0] ?? [];
        $when = array_map('intval', array_filter([$row['pa'] ?? null, $row['pb'] ?? null], fn($v) => $v !== null));
        return $when ? min($when) : null;
    }

    # Read the main row of one material of the type with its author and category, checked like every read; the field column only when asked
    private function getNodeRow(int $id, NodeType $type, bool $field): ?array {
        if ($id < 1 || (!$type->active && !$this->checkModer($type))) return null;
        $part = $this->getBranchSql($type, 'm', $this->getOwnExt($type), 'item');
        $sql = 'SELECT '.self::COLS.', n.body'.($field ? ', n.field' : '').', u.name AS uname, c.title AS ctitle, c.pread AS cread, c.modul AS cmod'
            .' FROM '.PREFIX_DB.'_nodes AS n LEFT JOIN '.PREFIX_DB.'_users AS u ON u.id = n.uid AND n.uid > 0'
            .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0'.$part['join'].' WHERE n.id = :mi AND '.$part['where'];
        $row = $this->getQueryRows($sql, $part['pars'] + ['mi' => $id])[0] ?? null;
        return ($row && $this->checkRowCat($row, $type)) ? $row : null;
    }

    # Read one full material of the route type: the main row, then its extra categories, relations and resources; a missing and a closed material answer the same null
    public function getNode(int $id, NodeType $type): ?Node {
        $row = $this->getNodeRow($id, $type, true);
        if ($row === null) return null;
        $cids = $this->getCatSets([$id]);
        $rels = $this->getRelSets([$id]);
        $assets = $this->getAssetSets([$id]);
        return $this->getNodeModel($row, $type, true, true, $cids[$id], $rels[$id], $assets[$id]);
    }

    # Read the text of one material with the main statement alone, the same checks as the full read and no related set
    public function getNodeContent(int $id, NodeType $type): ?Node {
        $row = $this->getNodeRow($id, $type, false);
        return $row === null ? null : $this->getNodeModel($row, $type, true, false, null, null, null);
    }

    # Read one resource with its material, the route type and the main category in one statement; an active role of the type is required as well
    public function getNodeAsset(int $id, NodeType $type): ?NodeAsset {
        if ($id < 1 || (!$type->active && !$this->checkModer($type))) return null;
        $part = $this->getBranchSql($type, 'm', $this->getOwnExt($type), 'item');
        $sql = 'SELECT '.implode(', ', array_map(fn($v) => 'a.'.$v, self::ASSETS)).', n.cid, c.pread AS cread, c.modul AS cmod FROM '.PREFIX_DB.'_node_assets AS a'
            .' INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0'.$part['join']
            .' WHERE a.id = :ma AND '.$part['where'];
        $row = $this->getQueryRows($sql, $part['pars'] + ['ma' => $id])[0] ?? null;
        if (!$row || !$this->checkRowCat($row, $type) || ($type->settings['assets'][$row['role']]['active'] ?? false) !== true) return null;
        return $this->getAssetModel($row);
    }

    # Read the light targets of a map of global id => type name in at most two statements: the types still unknown to the instance, then one union of every type branch
    # The answer keeps the order of the input and leaves out every target that is missing, closed, not published, of a disabled type or of another type than expected
    public function getNodeTargetList(array $refs): array {
        global $conf;
        $lims = $this->getNodeLimits($conf['node'] ?? null);
        if (count($refs) > min(500, $lims['syncbatch'] ?? 500)) throw $this->getInvalid('refs');
        foreach ($refs as $id => $name) if (!is_int($id) || $id < 1 || !is_string($name) || !preg_match(self::NAME, $name)) throw $this->getInvalid('refs');
        if (!$refs) return [];
        $need = array_values(array_unique(array_filter($refs, fn($v) => !array_key_exists($v, $this->types))));
        if ($need && !$this->whole) $this->setTypeRows($this->getTypeRows($need), $need);
        $sql = [];
        $pars = [];
        $tmap = [];
        foreach (array_values(array_unique($refs)) as $i => $name) {
            $type = $this->types[$name] ?? false;
            if (!$type || !$type->active) continue;
            $part = $this->getBranchSql($type, 'b'.$i, $type->ext === '' ? null : $this->getFactoryExt($type->ext), 'target');
            $ids = $this->getInList(array_keys($refs, $name, true), 'b'.$i.'i', $pars);
            $sql[] = 'SELECT n.id, n.tid, n.cid, n.uid, n.title, n.comon, n.comnum, n.score, n.ratings, c.pread AS cread, c.modul AS cmod FROM '.PREFIX_DB.'_nodes AS n'
                .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0'.$part['join'].' WHERE n.id IN ('.$ids.') AND '.$part['where'];
            $pars += $part['pars'];
            $tmap[$type->id] = $type;
        }
        if (!$sql) return [];
        $found = [];
        foreach ($this->getQueryRows(implode(' UNION ALL ', $sql), $pars) as $row) {
            $type = $tmap[intval($row['tid'])];
            $mode = CommentMode::tryFrom(intval($row['comon']));
            if ($mode === null || !$this->checkRowCat($row, $type)) continue;
            $found[intval($row['id'])] = new NodeTarget($type, intval($row['id']), intval($row['uid']), (string)$row['title'], $mode, intval($row['comnum']),
                intval($row['score']), intval($row['ratings']));
        }
        $out = [];
        foreach (array_keys($refs) as $id) if (isset($found[$id])) $out[$id] = $found[$id];
        return $out;
    }

    # Read the light target of one global id of the expected type with the very check of the batch read
    public function getNodeTarget(string $type, int $id): ?NodeTarget {
        return $this->getNodeTargetList([$id => $type])[$id] ?? null;
    }

    # Read one batch of the tree of the selected type after a cursor: id, title, the parent only when the context may read it, and the sort of the parent link
    public function getNodeTree(int $after = 0, int $limit = 500): array {
        if ($after < 0 || $limit < 1 || $limit > 500 || count($this->list) !== 1 || !$this->list[0]->settings['features']['tree']) throw $this->getInvalid('tree');
        $type = $this->list[0];
        $ext = $this->getOwnExt($type);
        $part = $this->getBranchSql($type, 'b', $ext, 'list');
        $up = $this->getBranchSql($type, 'p', $ext, 'parent');
        $sql = 'SELECT n.id, n.title, r.rid AS parent, r.sort AS rsort,'
            .' EXISTS (SELECT 1 FROM '.PREFIX_DB.'_nodes AS n'.$up['join'].' WHERE n.id = r.rid AND '.$up['where'].') AS rok'
            .' FROM '.PREFIX_DB.'_nodes AS n'.$part['join'].' LEFT JOIN '.PREFIX_DB.'_node_relations AS r ON r.nid = n.id AND r.type = \'parent\''
            .' WHERE '.$part['where'].' AND n.id > :after ORDER BY n.id LIMIT '.$limit;
        $out = [];
        foreach ($this->getQueryRows($sql, $part['pars'] + $up['pars'] + ['after' => $after]) as $row) {
            $out[] = ['id' => intval($row['id']), 'title' => (string)$row['title'], 'parent' => ($row['rok'] && $row['parent'] !== null) ? intval($row['parent']) : null,
                'sort' => intval($row['rsort'] ?? 0)];
        }
        return $out;
    }

    # Read one batch of sitemap rows of every active type with the sitemap integration after a global id cursor; an empty batch ends the walk
    public function getNodeSitemap(int $after = 0, int $limit = 500): array {
        if ($after < 0 || $limit < 1 || $limit > 500) throw $this->getInvalid('sitemap');
        $sql = [];
        $pars = [];
        $tmap = [];
        foreach ($this->getNodeTypeList() as $i => $type) {
            if (!$type->active || !$type->settings['integrations']['sitemap']) continue;
            $part = $this->getBranchSql($type, 'b'.$i, $type->ext === '' ? null : $this->getFactoryExt($type->ext), 'site');
            $sql[] = 'SELECT n.id, n.tid, n.title, n.cid, c.title AS ctitle, n.published, n.updated FROM '.PREFIX_DB.'_nodes AS n'
                .' LEFT JOIN '.PREFIX_DB.'_categories AS c ON c.id = n.cid AND n.cid > 0'.$part['join']
                .' WHERE '.$part['where'].' AND n.id > :b'.$i.'z ORDER BY n.id LIMIT '.$limit;
            $pars += $part['pars'] + ['b'.$i.'z' => $after];
            $tmap[$type->id] = $type->name;
        }
        if (!$sql) return [];
        $query = count($sql) === 1 ? $sql[0] : 'SELECT q.* FROM ('.implode(' UNION ALL ', array_map(fn($v) => '('.$v.')', $sql)).') AS q ORDER BY q.id LIMIT '.$limit;
        $out = [];
        foreach ($this->getQueryRows($query, $pars) as $row) {
            $out[] = ['id' => intval($row['id']), 'name' => $tmap[intval($row['tid'])], 'title' => (string)$row['title'], 'cid' => intval($row['cid']),
                'ctitle' => $row['ctitle'] === null ? null : (string)$row['ctitle'], 'published' => (string)$row['published'], 'updated' => (string)$row['updated']];
        }
        return $out;
    }
}
