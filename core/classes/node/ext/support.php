<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The private Ticket System of a type with the extension key support: a material is a request, the global comments are its correspondence and _node_support is the queue
# The owner reads only his own requests and a moderator of the type the whole queue; the class adds a scope, one queue row per request and two commands of its own,
# never users, messages, categories, files or rights of its own
# The allowed states and priorities are the two maps of config/node.php; a map that breaks the numbers of the table blocks the extension instead of being guessed
final class NodeSupport implements NodeExtension {

    # The closed actions of the extension contract
    private const ACTIONS = ['comment', 'rate', 'favorite', 'asset', 'report'];

    private Database $db;
    private NodeContext $ctx;
    private ?NodeQuery $query = null;
    private array $types = [];

    # Keep the database and the request context every check and write of the extension is decided against
    public function __construct(Database $db, NodeContext $context) {
        $this->db = $db;
        $this->ctx = $context;
    }

    # The refusal of an input, with the path of the first error for the log
    private function getInvalid(string $path): NodeException {
        return new NodeException('Invalid support input: '.$path, NodeException::INVALID);
    }

    # A storage failure with the last driver error as previous
    private function getStorage(string $text): NodeException {
        return new NodeException($text, NodeException::STORAGE, $this->db->laste);
    }

    # Run one statement; a statement the server refused is a storage failure
    private function getQueryRes(string $sql, array $pars = []): PDOStatement {
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) throw $this->getStorage('A support statement failed');
        return $res;
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

    # Whether the context moderates the type: the main administrator or a moderator named by the type; managing Node gives no right on the requests
    private function checkModer(NodeType $type): bool {
        return $this->ctx->super || in_array($type->name, $this->ctx->mods, true);
    }

    # The state and priority maps of the loaded Node configuration, checked against the table: the named keys the code uses, distinct whole numbers inside the CHECK bounds,
    # and priorities rising from low to urgent, because the queue orders by the number; anything else blocks the extension
    private function getMaps(): array {
        global $conf;
        $maps = $conf['node']['support'] ?? null;
        $want = ['state' => ['staff', 'author', 'closed'], 'prio' => ['low', 'normal', 'high', 'urgent']];
        if (!is_array($maps) || count($maps) !== 2) throw $this->getInvalid('support');
        foreach ($want as $key => $names) {
            $map = $maps[$key] ?? null;
            if (!is_array($map) || count($map) !== count($names) || array_diff($names, array_keys($map)) !== []) throw $this->getInvalid('support.'.$key);
            $vals = array_values($map);
            foreach ($vals as $one) if (!is_int($one)) throw $this->getInvalid('support.'.$key);
            sort($vals);
            if ($vals !== range(0, count($names) - 1)) throw $this->getInvalid('support.'.$key);
        }
        $prio = $maps['prio'];
        if (!($prio['low'] < $prio['normal'] && $prio['normal'] < $prio['high'] && $prio['high'] < $prio['urgent'])) throw $this->getInvalid('support.prio');
        return $maps;
    }

    # A reader of the same context bound to this extension, built once per instance with a plain field system
    private function getQuery(): NodeQuery {
        return $this->query ??= new NodeQuery($this->db, $this->ctx, new Field());
    }

    # The type of a stored material by its type id among the types the context may receive, read once per instance; null when it is not one of them
    private function getTypeById(int $tid): ?NodeType {
        if (!array_key_exists($tid, $this->types)) {
            $this->types[$tid] = null;
            foreach ($this->getQuery()->getNodeTypeList() as $one) if ($one->id === $tid) $this->types[$tid] = $one;
        }
        return $this->types[$tid];
    }

    # The queue row of one request, locked inside the open transaction when asked, or null when the request has none
    private function getTicketRow(int $nid, bool $lock): ?array {
        $sql = 'SELECT aid, state, prio, version, activity FROM '.PREFIX_DB.'_node_support WHERE nid = :nid'.($lock ? ' FOR UPDATE' : '');
        $row = $this->getQueryRes($sql, ['nid' => $nid])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? array_map('intval', array_diff_key($row, ['activity' => 0])) + ['activity' => $row['activity']] : null;
    }

    # Whether an administrator exists and may work on the requests of the type: the main administrator or the holder of node-<name>
    private function checkAdminRight(int $aid, NodeType $type): bool {
        $row = $this->getQueryRes('SELECT super, modules FROM '.PREFIX_DB.'_admins WHERE id = :id', ['id' => $aid])->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return false;
        return !empty($row['super']) || in_array('node-'.$type->name, getAdminModuleNames($row['modules']), true);
    }

    # The addresses of the subscribed administrators who may work on the type, in the language of the request where the site is multilingual;
    # with an assigned administrator only that one, when he is still subscribed and entitled, and the acting administrator never
    private function getMailList(NodeType $type, int $aid): array {
        global $conf, $locale;
        $where = 'smail = \'1\'';
        $pars = [];
        if (!empty($conf['multilingual'])) {
            $where .= ' AND (lang = :lang OR lang = \'\')';
            $pars['lang'] = (string)$locale;
        }
        $rows = $this->getQueryRes('SELECT id, email, super, modules FROM '.PREFIX_DB.'_admins WHERE '.$where.' ORDER BY id', $pars)->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $row) {
            $good = !empty($row['super']) || in_array('node-'.$type->name, getAdminModuleNames($row['modules']), true);
            if ($good && intval($row['id']) !== $this->ctx->aid) $out[intval($row['id'])] = $row['email'];
        }
        return ($aid > 0 && isset($out[$aid])) ? [$out[$aid]] : array_values(array_unique($out));
    }

    # Queue the notice of one event of a request when the type sends mail: a new request and a reply of its owner go to the staff, a reply of the staff to the owner
    # The notice names the site, the title and the side and links the request; the private text never enters the queue, and a refused queue row is logged by Mail
    # A failed read of the recipients is logged and costs the notice alone: the request or the reply it follows is stored all the same
    private function addSupportMail(NodeType $type, int $id, int $uid, string $title, string $event, int $aid): void {
        if (empty($type->settings['ext']['mail'])) return;
        getLang('node');
        $url = getPublicUrl(['name' => $type->name, 'op' => 'view', 'id' => $id]);
        try {
            $name = (string)$this->getQueryRes('SELECT name FROM '.PREFIX_DB.'_users WHERE id = :id', ['id' => $uid])->fetchColumn();
            $list = ($event === 'staff') ? [] : $this->getMailList($type, ($event === 'owner') ? $aid : 0);
        } catch (NodeException) {
            Logger::addSite('error', 'Node: the recipients of a support notice could not be read', ['nid' => $id]);
            return;
        }
        if ($event === 'staff') {
            $mail = ($uid !== $this->ctx->uid) ? getUserMail($uid) : '';
            $list = ($mail !== '') ? [$mail] : [];
            $text = sprintf(_NODE_MSTAFF, $title, $url);
        } else {
            $text = sprintf(($event === 'new') ? _NODE_MNEW : _NODE_MUSER, $name, $title, $url);
        }
        addNodeMail($list, $title, $text);
    }

    # Accept exactly the one switch of the extension and a standard section that makes the type private: registered authors, categories, open comments and direct publication,
    # no public integration, no rating, favorites, poll, home, pin, schedule, relation, tree or resource role, and the support view; the two maps must be intact as well
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        $this->getMaps();
        if (array_keys($config) !== ['mail'] || !is_bool($config['mail'])) throw $this->getInvalid('ext.mail');
        $need = ['categories' => true, 'comments' => true, 'submit' => true, 'moderation' => false, 'rating' => false, 'favorites' => false, 'poll' => false, 'home' => false,
            'pinned' => false, 'schedule' => false, 'related' => false, 'tree' => false];
        foreach ($need as $key => $val) if (($settings['features'][$key] ?? null) !== $val) throw $this->getInvalid('features.'.$key);
        foreach (['search', 'rss', 'sitemap', 'blocks'] as $key) if (($settings['integrations'][$key] ?? null) !== false) throw $this->getInvalid('integrations.'.$key);
        if (!in_array($settings['workflow']['access'] ?? null, ['user', 'group'], true)) throw $this->getInvalid('workflow.access');
        if (array_filter($settings['assets'] ?? [], fn(array $v): bool => !empty($v['active']))) throw $this->getInvalid('assets');
        if (($settings['view']['mode'] ?? null) !== 'support') throw $this->getInvalid('view.mode');
        return ['mail' => $config['mail']];
    }

    # A request carries no extension input: its queue row is created by the extension and changed only by its own command
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        if ($data !== []) throw $this->getInvalid('ext');
        return [];
    }

    # Narrow every read of the type: a moderator reads the whole queue, a registered visitor his own requests and a guest nothing
    public function getNodeScope(NodeType $type): array {
        if ($this->checkModer($type)) return ['join' => '', 'where' => '', 'params' => []];
        if ($this->ctx->uid > 0) return ['join' => '', 'where' => 'n.uid = :owner', 'params' => ['owner' => $this->ctx->uid]];
        return ['join' => '', 'where' => '1 = 0', 'params' => []];
    }

    # Forbid a new reply to a closed request or to one without its queue row; every other closed action is left to the switches of the type
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool {
        if (!in_array($action, self::ACTIONS, true)) throw $this->getInvalid('action');
        if ($action !== 'comment') return true;
        $row = $this->getTicketRow($node->id, false);
        return $row !== null && $row['state'] !== $this->getMaps()['state']['closed'];
    }

    # Follow the first publication of a reply inside the transaction of the comment owner: a reply the owner wrote waits for the staff, any other waits for the owner,
    # the activity and the version move, and the notice of the other side is queued; the side is the author of the reply, not the moderator who approved it,
    # so the owner is never told about a reply of his own; a closed request stays as it is
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action, int $uid): void {
        if (!in_array($action, self::ACTIONS, true)) throw $this->getInvalid('action');
        if ($action !== 'comment') return;
        if (!$this->db->checkSqlActive()) throw $this->getInvalid('transaction');
        $maps = $this->getMaps();
        $row = $this->getTicketRow($node->id, true) ?? throw $this->getStorage('A request has no queue row');
        if ($row['state'] === $maps['state']['closed']) return;
        $own = $uid > 0 && $uid === $node->uid;
        $sql = 'UPDATE '.PREFIX_DB.'_node_support SET state = :state, activity = NOW(), version = version + 1 WHERE nid = :nid';
        $this->getQueryRes($sql, ['state' => $maps['state'][$own ? 'staff' : 'author'], 'nid' => $node->id]);
        $this->addSupportMail($type, $node->id, $node->uid, $node->title, $own ? 'owner' : 'staff', $row['aid']);
    }

    # Create the queue row of a new request: unassigned, normal priority and waiting for the staff; a request needs a registered owner and open comments
    public function addNodeData(Node $node, array $data): void {
        if ($data !== []) throw $this->getInvalid('ext');
        if ($node->uid < 1) throw $this->getInvalid('uid');
        if ($node->comon !== CommentMode::Open) throw $this->getInvalid('comon');
        $maps = $this->getMaps();
        $sql = 'INSERT INTO '.PREFIX_DB.'_node_support (nid, aid, state, prio, version, activity) VALUES (:nid, 0, :state, :prio, 1, NOW())';
        $this->getQueryRes($sql, ['nid' => $node->id, 'state' => $maps['state']['staff'], 'prio' => $maps['prio']['normal']]);
        $type = $this->getTypeById($node->tid) ?? throw $this->getStorage('The type of a new request cannot be read');
        $this->addSupportMail($type, $node->id, $node->uid, $node->title, 'new', 0);
    }

    # Keep a changed request inside the rules of the extension: no extension input and open comments; the queue row is not touched by a change of the material
    public function updateNodeData(Node $before, Node $after, ?array $data): void {
        if ($data !== null && $data !== []) throw $this->getInvalid('ext');
        if ($after->comon !== CommentMode::Open) throw $this->getInvalid('comon');
    }

    # Remove the queue row before the request itself is deleted inside the same transaction
    public function deleteNodeData(Node $node): void {
        $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_support WHERE nid = :nid', ['nid' => $node->id]);
    }

    # Read the queue rows of one page of accessible requests with one statement: the state, priority, assignment, version and activity for everyone the request is readable to,
    # because the owner sends the stored assignment and priority back when he closes or reopens it, and the name of the assigned administrator for a moderator alone
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        $ids = [];
        foreach ($nodes as $one) if (($one instanceof Node || $one instanceof NodeTarget) && $one->id > 0) $ids[] = $one->id;
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $pars = [];
        $sql = 'SELECT s.nid, s.aid, s.state, s.prio, s.version, s.activity, a.name AS aname FROM '.PREFIX_DB.'_node_support AS s'
            .' LEFT JOIN '.PREFIX_DB.'_admins AS a ON a.id = s.aid AND s.aid > 0 WHERE s.nid IN ('.$this->getInList($ids, 'i', $pars).')';
        $moder = $this->checkModer($type);
        $out = [];
        foreach ($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[intval($row['nid'])] = ['state' => intval($row['state']), 'version' => intval($row['version']), 'activity' => $row['activity'],
                'aid' => intval($row['aid']), 'aname' => $moder ? (string)($row['aname'] ?? '') : '', 'prio' => intval($row['prio'])];
        }
        return $out;
    }

    # Change the working card of one request at the expected version in one statement: a moderator sets the assignment, the state and the priority,
    # the owner only closes or reopens and must send the assignment and priority the row already has; a repeat of the stored values writes nothing
    # Closing and reopening move the activity, an assignment or a priority does not; the request must be readable by the context, as every read of the type decides
    public function updateNodeSupport(int $id, int $aid, int $state, int $prio, int $version): void {
        if ($this->ctx->task) throw new NodeException('A background context changes no request', NodeException::DENIED);
        $maps = $this->getMaps();
        $sql = 'SELECT t.name FROM '.PREFIX_DB.'_nodes AS n INNER JOIN '.PREFIX_DB.'_node_types AS t ON t.id = n.tid WHERE n.id = :id';
        $name = ($id > 0) ? $this->getQueryRes($sql, ['id' => $id])->fetchColumn() : false;
        $type = is_string($name) ? $this->getQuery()->getNodeType($name) : null;
        $tgt = ($type !== null && $type->ext === 'support') ? $this->getQuery()->getNodeTarget($type->name, $id) : null;
        if ($tgt === null) throw new NodeException('The request does not exist', NodeException::NOTFOUND);
        $moder = $this->checkModer($type);
        if (!in_array($state, $maps['state'], true)) throw $this->getInvalid('state');
        if (!in_array($prio, $maps['prio'], true)) throw $this->getInvalid('prio');
        if ($aid < 0) throw $this->getInvalid('aid');
        if (!$moder && ($tgt->uid !== $this->ctx->uid || !in_array($state, [$maps['state']['staff'], $maps['state']['closed']], true))) {
            throw new NodeException('The context may only close or reopen its own request', NodeException::DENIED);
        }
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
        try {
            $row = $this->getTicketRow($id, true) ?? throw new NodeException('The request has no queue row', NodeException::NOTFOUND);
            if ($row['version'] !== $version) throw new NodeException('The expected card version is stale', NodeException::CONFLICT);
            if (!$moder && ($aid !== $row['aid'] || $prio !== $row['prio'])) throw new NodeException('The owner changes no assignment or priority', NodeException::DENIED);
            if ($aid !== $row['aid'] && $aid > 0 && !$this->checkAdminRight($aid, $type)) throw $this->getInvalid('aid');
            if ([$aid, $state, $prio] !== [$row['aid'], $row['state'], $row['prio']]) {
                $move = ($state === $maps['state']['closed']) !== ($row['state'] === $maps['state']['closed']);
                $sql = 'UPDATE '.PREFIX_DB.'_node_support SET aid = :aid, state = :state, prio = :prio, version = version + 1'
                    .($move ? ', activity = NOW()' : '').' WHERE nid = :nid AND version = :ver';
                $this->getQueryRes($sql, ['aid' => $aid, 'state' => $state, 'prio' => $prio, 'nid' => $id, 'ver' => $version]);
            }
            if ($own && !$this->db->setSqlCommit()) throw $this->getStorage('The commit of a support card is uncertain');
        } catch (Throwable $err) {
            if ($own) $this->db->setSqlRollback();
            throw ($err instanceof NodeException) ? $err : new NodeException('A support card write failed', NodeException::STORAGE, $err);
        }
    }

    # Read one page of the administrative queue of the type with its total: published requests, filtered by state, assignment and priority, where null lifts a filter
    # and an assignment of 0 means the unassigned requests; ordered by priority down, then the oldest activity and the row id, for a moderator of the type alone
    public function getNodeSupportList(NodeType $type, int $page, int $limit, ?int $state = null, ?int $aid = null, ?int $prio = null): array {
        global $conf;
        if (!$this->checkModer($type)) throw new NodeException('The context does not moderate the type', NodeException::DENIED);
        if ($type->ext !== 'support') throw $this->getInvalid('type');
        $max = $conf['node']['limits']['maxlist'] ?? null;
        if (!is_int($max) || $max < 1) throw $this->getStorage('The limits of the Node configuration are broken');
        if ($page < 1 || $limit < 1 || $limit > $max || $page > intdiv(4294967295, $limit) + 1) throw $this->getInvalid('page');
        $maps = $this->getMaps();
        if ($state !== null && !in_array($state, $maps['state'], true)) throw $this->getInvalid('state');
        if ($prio !== null && !in_array($prio, $maps['prio'], true)) throw $this->getInvalid('prio');
        if ($aid !== null && $aid < 0) throw $this->getInvalid('aid');
        $where = 'n.tid = :tid AND n.status = :stat';
        $pars = ['tid' => $type->id, 'stat' => NodeStatus::Published->value];
        foreach (['state' => $state, 'aid' => $aid, 'prio' => $prio] as $key => $val) {
            if ($val === null) continue;
            $where .= ' AND s.'.$key.' = :'.$key;
            $pars[$key] = $val;
        }
        $from = ' FROM '.PREFIX_DB.'_node_support AS s INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = s.nid WHERE '.$where;
        $count = intval($this->getQueryRes('SELECT COUNT(*)'.$from, $pars)->fetchColumn());
        $out = ['nodes' => [], 'ext' => [], 'count' => $count];
        if ($count < 1) return $out;
        $sql = 'SELECT n.id, n.uid, n.title, n.comon, n.comnum, n.score, n.ratings, s.aid, s.state, s.prio, s.version, s.activity, a.name AS aname, u.name AS uname'
            .' FROM '.PREFIX_DB.'_node_support AS s INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = s.nid'
            .' LEFT JOIN '.PREFIX_DB.'_admins AS a ON a.id = s.aid AND s.aid > 0 LEFT JOIN '.PREFIX_DB.'_users AS u ON u.id = n.uid AND n.uid > 0'
            .' WHERE '.$where.' ORDER BY s.prio DESC, s.activity ASC, s.id ASC LIMIT '.(($page - 1) * $limit).', '.$limit;
        foreach ($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $nid = intval($row['id']);
            $out['nodes'][] = new NodeTarget($type, $nid, intval($row['uid']), $row['title'], CommentMode::tryFrom(intval($row['comon'])) ?? CommentMode::Disabled,
                intval($row['comnum']), intval($row['score']), intval($row['ratings']));
            $out['ext'][$nid] = ['state' => intval($row['state']), 'version' => intval($row['version']), 'activity' => $row['activity'], 'aid' => intval($row['aid']),
                'aname' => (string)($row['aname'] ?? ''), 'prio' => intval($row['prio']), 'uname' => (string)($row['uname'] ?? '')];
        }
        return $out;
    }
}
