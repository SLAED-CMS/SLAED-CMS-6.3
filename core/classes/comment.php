<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Moderation state of one stored comment, as the status column of the comment table holds it
enum CommentStatus: int {
    case Pending = 0;
    case Published = 1;
}

# Moderation mode of a comment target, as the acomm column of the target row holds it
enum CommentMode: int {
    case Disabled = 0;
    case Moderated = 1;
    case Open = 2;
}

# Comment subsystem: every read and write of the comment table with its permissions, pagination and target bookkeeping in one place
# Nothing outside this class reaches the comment table any more; the resolver that authorizes a target and the counter that follows a write live here as well
# Add, edit, status and delete wrap their own writes; when a transaction is already open they join it and leave begin, commit and rollback to whoever owns it
class Comment {

    # The eight modules that render comments, each with the table its target rows live in and the points slot its author is credited from
    # One map answers both questions, because the modules a comment may be written against and the modules whose counter moves are the same eight by construction
    private const MODULES = [
        'faq' => ['_faq', 7],
        'files' => ['_files', 10],
        'links' => ['_links', 22],
        'media' => ['_media', 26],
        'news' => ['_news', 32],
        'pages' => ['_pages', 36],
        'shop' => ['_products', 40],
        'voting' => ['_voting', 43],
    ];

    # The two syntaxes a comment body may be written in; html is a trust grant rather than a format and is never stored against a comment
    private const FORMATS = ['plain', 'markdown'];

    # How many segments one reply path may carry, so a thread cannot be driven past the width the column and the rendering were sized for
    private const MAXDEPTH = 20;

    # The columns every read of one comment row answers with, so a consumer never has to know which of them a given query needed
    private const FIELDS = 'id, cid, modul, time, edited, deleted, uid, name, ip, body, format, status, pid, path';

    private Database $db;
    private Parser $prs;
    private array $conf;
    private array $site;

    # Build the subsystem from the services the request already carries; the settings the write normalization needs are snapshotted so no method reaches for a global
    public function __construct(Database $db, Parser $prs, array $conf) {
        $this->db = $db;
        $this->prs = $prs;
        $this->conf = is_array($conf['comments'] ?? null) ? $conf['comments'] : [];
        $this->site = [
            'click' => !empty($conf['clickable']),
            'cens' => !empty($conf['censor']),
            'from' => (string)($conf['censor_l'] ?? ''),
            'to' => (string)($conf['censor_r'] ?? ''),
        ];
    }

    # Report the target rows whose stored comment counter disagrees with the comments actually published under them
    # That counter is denormalised on purpose - eight modules read it without ever touching this table - so nothing notices when it drifts, and this is what notices
    # The count is the public one and never the viewer's: it is bookkeeping about a column every visitor reads, so a moderator running the sweep must not count what only they can see
    public function getCountDrift(string $mod = ''): array {
        $out = [];
        foreach (self::MODULES as $name => $one) {
            if ($mod !== '' && $mod !== $name) continue;
            $tab = PREFIX_DB.$one[0];
            $live = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_comment AS c WHERE c.modul = :mod AND c.cid = x.id AND c.status = :stat AND c.deleted IS NULL';
            $held = 'SELECT 1 FROM '.PREFIX_DB.'_comment AS d WHERE d.modul = :dmod AND d.cid = x.id';
            $sql = 'SELECT t.cid, t.col, t.live FROM (SELECT x.id AS cid, x.comments AS col, ('.$live.') AS live FROM '.$tab.' AS x'
                .' WHERE x.comments <> 0 OR EXISTS ('.$held.')) AS t WHERE t.col <> t.live ORDER BY t.cid ASC';
            $pars = ['mod' => $name, 'dmod' => $name, 'stat' => CommentStatus::Published->value];
            foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
                $out[] = ['modul' => $name, 'cid' => intval($row['cid']), 'col' => intval($row['col']), 'live' => intval($row['live'])];
            }
        }
        return $out;
    }

    # Write the live comment count into one target row, which is the one statement every counter path of this class goes through
    # The number is counted inside the statement rather than moved by a delta, so a row that had drifted comes back correct instead of drifting further
    # This also means a comment written between a drift report and its repair cannot be overwritten by the stale figure that report carried
    private function setTargetCount(int $id, string $mod): bool {
        if ($id < 1 || !isset(self::MODULES[$mod])) return false;
        $live = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_comment AS c WHERE c.modul = :mod AND c.cid = :cid AND c.status = :stat AND c.deleted IS NULL';
        $sql = 'UPDATE '.PREFIX_DB.self::MODULES[$mod][0].' SET comments = ('.$live.') WHERE id = :tid';
        $pars = ['mod' => $mod, 'cid' => $id, 'stat' => CommentStatus::Published->value, 'tid' => $id];
        return $this->db->getSqlQuery($sql, $pars) !== false;
    }

    # Write the live comment count back into the target rows a drift report named, and answer how many rows were actually corrected
    public function updateCountDrift(array $rows): int {
        $done = 0;
        foreach ($rows as $one) {
            if (!$this->setTargetCount(intval($one['cid'] ?? 0), (string)($one['modul'] ?? ''))) continue;
            if (intval($this->db->getSqlAffected()) > 0) $done++;
        }
        if ($done > 0) Cache::addEpoch();
        return $done;
    }

    # Return one page of the discussion of a target: the page counts and paginates root comments, and every root on it arrives with its whole branch
    # The scope is resolved once and every query runs against it, so the count, the roots and their replies can never answer to different permissions
    # Replies follow their root in path order, which is the order they were written in, so the rows come back ready to render top to bottom
    # One root may be named through full, and then its branch is answered whole rather than capped, which is what a reader without HTMX follows the reply control to
    public function getList(string $mod, int $id, int $page, int $full = 0): array {
        [$where, $pars] = $this->getScope($mod, $id, true);
        $root = $where.' AND pid = 0';
        $out = $this->getPager($this->getTotal(PREFIX_DB.'_comment '.$root, $pars), $page, intval($this->conf['num'] ?? 15));
        $out['rows'] = [];
        if ($out['total'] < 1) return $out;
        $dir = $out['isasc'] ? 'ASC' : 'DESC';
        $sql = 'SELECT '.self::FIELDS.' FROM '.PREFIX_DB.'_comment '.$root
            .' ORDER BY time '.$dir.', id '.$dir.' LIMIT '.$out['offset'].', '.$out['limit'];
        $tree = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $tree[] = $this->getRowData($row);
        }
        $out['rows'] = $this->getAuthorRows($this->getTreeRows($tree, $where, $pars, $full));
        return $out;
    }

    # Return the replies stored under one comment, oldest first, skipping what the caller already has and never more than it asked for
    # The scope of the comment's own target decides what is visible here as well, which is what keeps a branch request from answering more than its page would
    # The answer carries the branch total beside the rows, so the caller knows whether a further request has anything left to fetch
    public function getBranch(int $id, int $limit, int $skip = 0): array {
        $out = ['rows' => [], 'total' => 0, 'skip' => max(0, $skip), 'left' => 0];
        if ($id < 1 || $limit < 1) return $out;
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT modul, cid, path FROM '.PREFIX_DB.'_comment WHERE id = :id', ['id' => $id]));
        if (!$row || (string)$row['path'] === '') return $out;
        [$where, $pars] = $this->getScope((string)$row['modul'], intval($row['cid']), true);
        $pars['base'] = (string)$row['path'].'/%';
        $out['total'] = $this->getTotal(PREFIX_DB.'_comment '.$where.' AND path LIKE :base', $pars);
        if ($out['total'] < 1) return $out;
        $sql = 'SELECT '.self::FIELDS.' FROM '.PREFIX_DB.'_comment '.$where.' AND path LIKE :base ORDER BY path ASC LIMIT '.$out['skip'].', '.intval($limit);
        $rows = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $one) {
            $rows[] = $this->getRowData($one);
        }
        $out['rows'] = $this->getAuthorRows($rows);
        $out['left'] = max(0, $out['total'] - $out['skip'] - count($out['rows']));
        return $out;
    }

    # Return one page of the moderation list, narrowed by state, by module and by one of the five search fields
    public function getAdminList(CommentStatus $stat, string $mod, int $find, string $term, int $page): array {
        [$where, $pars] = $this->getAdminScope($stat, $mod, $find, $term);
        $join = PREFIX_DB.'_comment AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) '.$where;
        $out = $this->getPager($this->getTotal($join, $pars), $page, intval($this->conf['anum'] ?? 25));
        $out['rows'] = [];
        if ($out['total'] < 1) return $out;
        $dir = $out['isasc'] ? 'ASC' : 'DESC';
        $sql = 'SELECT s.'.str_replace(', ', ', s.', self::FIELDS).', u.name AS unam FROM '.$join
            .' ORDER BY s.time '.$dir.', s.id '.$dir.' LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + ['nick' => (string)($row['unam'] ?? '')];
        }
        return $out;
    }

    # Return one comment by its id, with the account name and the full author record of a registered author, or an empty array when the row is gone
    # The author record is what a single-comment fragment response needs, and it costs no query for a guest comment because the author lookup is skipped for uid 0
    public function getComment(int $id): array {
        $sql = 'SELECT s.'.str_replace(', ', ', s.', self::FIELDS).', u.name AS unam FROM '.PREFIX_DB.'_comment AS s'
            .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :id AND s.deleted IS NULL';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        if (!$row) return [];
        $out = $this->getRowData($row) + ['nick' => (string)($row['unam'] ?? '')];
        return $out + ['user' => $this->getAuthors([$out['uid']])[$out['uid']] ?? []];
    }

    # Return the published comments of one account, newest first, for the activity feed of a profile
    public function getUserList(int $uid, int $limit): array {
        if ($uid < 1 || $limit < 1) return [];
        $sql = 'SELECT '.self::FIELDS.' FROM '.PREFIX_DB.'_comment WHERE uid = :uid AND status = :stat AND deleted IS NULL ORDER BY id DESC LIMIT 0, '.intval($limit);
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, ['uid' => $uid, 'stat' => CommentStatus::Published->value])) ?: [] as $row) {
            $out[] = $this->getRowData($row);
        }
        return $out;
    }

    # Count the published comments of one account, the number the profile hub shows beside the counters of the other modules
    public function getUserCount(int $uid): int {
        if ($uid < 1) return 0;
        $from = PREFIX_DB.'_comment WHERE uid = :uid AND status = :stat AND deleted IS NULL';
        return $this->getTotal($from, ['uid' => $uid, 'stat' => CommentStatus::Published->value]);
    }

    # Count the comments in one moderation state across every module, the number the waiting-content chip of the admin sidebar shows
    public function getStatusCount(CommentStatus $stat): int {
        return $this->getTotal(PREFIX_DB.'_comment WHERE status = :stat AND deleted IS NULL', ['stat' => $stat->value]);
    }

    # Resolve a comment target through the fixed module map and answer its moderation mode; Disabled when the module is unknown, the row is gone, hidden or closed
    # Visibility is the module's own view predicate, so an unpublished, hidden or out-of-category target refuses a write exactly as its own page refuses a read
    public function getTargetMode(string $mod, int $id): CommentMode {
        if (!$id || !isset(self::MODULES[$mod])) return CommentMode::Disabled;
        $tab = PREFIX_DB.self::MODULES[$mod][0];
        if ($mod == 'voting') {
            $sql = 'SELECT acomm FROM '.$tab.' WHERE id = :id AND modul = \'\' AND time <= NOW() AND (enddate >= NOW() AND status = \'0\' OR status = \'1\')';
        } else {
            $sql = 'SELECT acomm FROM '.$tab.' AS t WHERE t.id = :id AND t.time <= NOW() AND t.status != \'0\' '.catmids($mod, 't.cid');
        }
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        return $row ? (CommentMode::tryFrom(intval($row['acomm'])) ?? CommentMode::Disabled) : CommentMode::Disabled;
    }

    # Answer the page one comment is rendered on, so a link to it can name a page that really shows it instead of an anchor that resolves to nothing
    # The rank is counted over roots alone, because the list paginates roots and a reply is always rendered behind the root it belongs to
    # The comparison is a row value against the same index the list reads, so the count is a range on modul_cid_pid_time rather than a scan
    public function getRootPage(int $id): int {
        if ($id < 1) return 1;
        $sql = 'SELECT modul, cid, path FROM '.PREFIX_DB.'_comment WHERE id = :id AND deleted IS NULL';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        if (!$row || (string)$row['path'] === '') return 1;
        $top = $this->db->getSqlRow($this->db->getSqlQuery(
            'SELECT time, id FROM '.PREFIX_DB.'_comment WHERE id = :id',
            ['id' => intval(substr((string)$row['path'], 0, 10))]
        ));
        if (!$top) return 1;
        [$where, $pars] = $this->getScope((string)$row['modul'], intval($row['cid']), true);
        $pars['rtim'] = (string)$top['time'];
        $pars['rrid'] = intval($top['id']);
        $seen = $this->getTotal(
            PREFIX_DB.'_comment '.$where.' AND pid = 0 AND (time, id) '.(!empty($this->conf['sort']) ? '<=' : '>=').' (:rtim, :rrid)',
            $pars
        );
        $size = intval($this->conf['num'] ?? 15);
        return ($seen > 0 && $size > 0) ? (int)ceil($seen / $size) : 1;
    }

    # Return the module names the stored comments actually use, for the module selector of the moderation list
    public function getModuleList(): array {
        $out = [];
        $sql = 'SELECT DISTINCT modul FROM '.PREFIX_DB.'_comment WHERE deleted IS NULL ORDER BY modul ASC';
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql)) ?: [] as $row) {
            if ($row['modul'] !== '') $out[] = (string)$row['modul'];
        }
        return $out;
    }

    # Resolve the syntax a new comment body is stored under; an html editor grants trust rather than naming a format, so it is refused here and the body is kept as markdown source
    private function getBodyFormat(): string {
        $fmt = getEditorMode();
        return in_array($fmt, self::FORMATS, true) ? $fmt : 'markdown';
    }

    # Store one new comment against a target the resolver accepts and answer its id, the stored name, whether this call stored it and the refusal that stopped it
    # Mode, author, address and moderation state come from the server context; the request supplies the module key, the target id, the body, the guest name and its key
    # The new flag is what a caller with side effects of its own asks: a replay answers the first comment without storing a second, so no notification may be written for it either
    # A reply names its parent by id and nothing else: the parent decides the branch it joins, and a parent of another target, a removed one or one already at the depth limit is refused
    public function addComment(string $mod, int $id, string $body, string $name, string $key = '', int $pid = 0): array {
        global $user;
        $mode = $this->getTargetMode($mod, $id);
        $ip = getIp();
        $hash = $this->getIpHash($ip);
        $stop = $this->checkRules($mod, $body, $name, $hash, true);
        $last = $stop ? (string)end($stop) : '';
        if ($last !== '' || $mode === CommentMode::Disabled) return ['id' => 0, 'name' => $name, 'new' => false, 'error' => $last ?: _ERROR];
        $base = $this->getParentPath($mod, $id, $pid);
        if ($base === null) return ['id' => 0, 'name' => $name, 'new' => false, 'error' => _COMMENTS_PARENT];
        $body = $this->filterCommentBody($body, $this->getLinkFlag($mod));
        if (is_user()) {
            $uid = intval($user[0]);
            $info = getUserInfo();
            $name = $info['name'];
            $stat = (!is_moder($mod) && ($mode === CommentMode::Moderated || $info['access'])) ? CommentStatus::Pending : CommentStatus::Published;
        } else {
            $uid = 0;
            $stat = (!is_moder($mod) && ($mode === CommentMode::Moderated || $this->conf['anonpost'] == 1)) ? CommentStatus::Pending : CommentStatus::Published;
        }
        $key = $this->getRequestKey($key);
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return ['id' => 0, 'name' => $name, 'new' => false, 'error' => _ERROR];
        $sql = 'INSERT INTO '.PREFIX_DB.'_comment (cid, modul, time, uid, name, ip, iphash, body, format, reqkey, status, pid)'
            .' VALUES (:cid, :modul, NOW(), :uid, :name, :ip, :hash, :body, :format, :reqkey, :status, :pid)';
        $done = $this->db->getSqlQuery($sql, [
            'cid' => $id, 'modul' => $mod, 'uid' => $uid, 'name' => $name, 'ip' => $ip, 'hash' => $hash,
            'body' => $body, 'format' => $this->getBodyFormat(), 'reqkey' => $key, 'status' => $stat->value, 'pid' => $base === '' ? 0 : $pid,
        ]);
        if (!$done) {
            $fail = intval($this->db->getSqlError()['code']) === 1062;
            if ($own) $this->db->setSqlRollback();
            return $fail ? $this->getKeyResult($key, $name) : ['id' => 0, 'name' => $name, 'new' => false, 'error' => _ERROR];
        }
        $new = intval($this->db->getSqlLastId());
        $path = ($base !== '' ? $base.'/' : '').str_pad((string)$new, 10, '0', STR_PAD_LEFT);
        if (!$this->db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET path = :path WHERE id = :id', ['path' => $path, 'id' => $new])) {
            if ($own) $this->db->setSqlRollback();
            return ['id' => 0, 'name' => $name, 'new' => false, 'error' => _ERROR];
        }
        if ($stat === CommentStatus::Published) $this->updateTargetPoints($mod, false, $uid);
        if ($own && !$this->db->setSqlCommit()) {
            $this->db->setSqlRollback();
            return ['id' => 0, 'name' => $name, 'new' => false, 'error' => _ERROR];
        }
        if ($stat === CommentStatus::Published) $this->addTargetCount($id, $mod);
        Cache::addEpoch();
        return ['id' => $new, 'name' => $name, 'new' => true, 'error' => ''];
    }

    # Load one comment for editing and, when a body is given, store the edited text once the edit rules accept it
    # The permission, the module and the edit window all come from the stored row, so a request can neither name the module nor extend its own window
    public function updateComment(int $id, string $body): array {
        global $user;
        $sql = 'SELECT uid, time, body, format, modul FROM '.PREFIX_DB.'_comment WHERE id = :id AND deleted IS NULL';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        $mod = (string)($row['modul'] ?? '');
        $uid = $row ? intval($row['uid']) : 0;
        $out = ['allow' => false, 'mod' => $mod, 'body' => (string)($row['body'] ?? ''), 'format' => (string)($row['format'] ?? ''), 'saved' => false, 'error' => []];
        $wait = strtotime((string)($row['time'] ?? '')) + intval($this->conf['edit']);
        if (!is_moder($mod) && !(is_user() && $uid == intval($user[0]) && time() < $wait)) return $out;
        $out['allow'] = true;
        if ($id < 1 || $mod === '' || !$body) return $out;
        $out['error'] = $this->checkRules($mod, $body, '', '', false);
        if ($out['error']) return $out;
        $text = $this->filterCommentBody($body, $this->getLinkFlag($mod));
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return $out;
        $done = $this->db->getSqlQuery(
            'UPDATE '.PREFIX_DB.'_comment SET body = :body, edited = NOW() WHERE id = :id AND deleted IS NULL',
            ['body' => $text, 'id' => $id]
        );
        if (!$done || ($own && !$this->db->setSqlCommit())) {
            if ($own) $this->db->setSqlRollback();
            return $out;
        }
        Cache::addEpoch();
        $out['body'] = $text;
        $out['saved'] = true;
        return $out;
    }

    # Publish or hide one comment as a moderator of the module the stored row names, and move the counter and the points of its target with it
    # The state is changed by a conditional update rather than by a read followed by a write, so two parallel requests cannot both count the same transition
    # The wanted state is bound twice under two names because a native prepared statement rejects one named placeholder used in two positions
    public function setStatus(int $id, bool $open): bool {
        $own = !$this->db->checkSqlActive();
        if ($id < 1 || ($own && !$this->db->setSqlBegin())) return false;
        $sql = 'SELECT cid, uid, status, modul FROM '.PREFIX_DB.'_comment WHERE id = :id AND deleted IS NULL FOR UPDATE';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        $mod = (string)($row['modul'] ?? '');
        $cid = $row ? intval($row['cid']) : 0;
        if (!$cid || $mod === '' || !is_moder($mod)) {
            if ($own) $this->db->setSqlRollback();
            return false;
        }
        $stat = $open ? CommentStatus::Published : CommentStatus::Pending;
        $done = $this->db->getSqlQuery(
            'UPDATE '.PREFIX_DB.'_comment SET status = :next WHERE id = :id AND status != :curr AND deleted IS NULL',
            ['next' => $stat->value, 'curr' => $stat->value, 'id' => $id]
        );
        if ($done === false) {
            if ($own) $this->db->setSqlRollback();
            return false;
        }
        $moved = intval($this->db->getSqlAffected()) > 0;
        if ($moved) $this->updateTargetPoints($mod, !$open, intval($row['uid']));
        if ($own && !$this->db->setSqlCommit()) {
            $this->db->setSqlRollback();
            return false;
        }
        if ($moved) {
            $this->addTargetCount($cid, $mod);
            Cache::addEpoch();
        }
        return true;
    }

    # Remove one comment as a moderator of the module the stored row names, and take the counter and the points of its target back when the removed row was published
    # The row is marked rather than erased and the mark is set by a conditional update, so a repeated delete answers the same result without moving a counter twice
    public function deleteComment(int $id): bool {
        $own = !$this->db->checkSqlActive();
        if ($id < 1 || ($own && !$this->db->setSqlBegin())) return false;
        $sql = 'SELECT cid, uid, status, modul FROM '.PREFIX_DB.'_comment WHERE id = :id FOR UPDATE';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        $mod = (string)($row['modul'] ?? '');
        $cid = $row ? intval($row['cid']) : 0;
        if (!$cid || $mod === '' || !is_moder($mod)) {
            if ($own) $this->db->setSqlRollback();
            return false;
        }
        $done = $this->db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET deleted = NOW() WHERE id = :id AND deleted IS NULL', ['id' => $id]);
        if ($done === false) {
            if ($own) $this->db->setSqlRollback();
            return false;
        }
        $hid = intval($this->db->getSqlAffected()) > 0;
        $gone = $hid && intval($row['status']) === CommentStatus::Published->value;
        if ($gone) $this->updateTargetPoints($mod, true, intval($row['uid']));
        if ($own && !$this->db->setSqlCommit()) {
            $this->db->setSqlRollback();
            return false;
        }
        if ($gone) $this->addTargetCount($cid, $mod);
        if ($hid) Cache::addEpoch();
        return true;
    }

    # Remove the comments of target rows the module admin has just deleted, because a target that is gone leaves nothing to reference and no counter to move
    # The id list is bound one placeholder per value, so a caller can hand over a bulk selection without any of it reaching the statement as text
    public function deleteTarget(string $mod, array $ids): bool {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($v) => $v > 0)));
        if ($mod === '' || !$ids) return false;
        $keys = [];
        $pars = ['mod' => $mod];
        foreach ($ids as $key => $val) {
            $keys[] = ':c'.$key;
            $pars['c'.$key] = $val;
        }
        $done = $this->db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid IN ('.implode(', ', $keys).') AND modul = :mod', $pars);
        if ($done === false) return false;
        if (intval($this->db->getSqlAffected()) > 0) Cache::addEpoch();
        return true;
    }

    # Anonymise the comments of an account that is being removed: the rows stay, so discussions stay readable and every reply keeps the parent it was written under
    # Removing the rows instead would shrink the target counters of eight modules and break every branch below them, which is why the reference goes and the comment does not
    # Counters and points are deliberately left alone: the comments are still published, and there is no account left to recalculate a point score for
    public function deleteUser(int $uid): bool {
        if ($uid < 1) return false;
        $done = $this->db->getSqlQuery('UPDATE '.PREFIX_DB.'_comment SET uid = 0, name = \'\' WHERE uid = :uid', ['uid' => $uid]);
        if ($done === false) return false;
        if (intval($this->db->getSqlAffected()) > 0) Cache::addEpoch();
        return true;
    }

    # Store the body a moderator typed in the moderation form, exactly as it arrived, because that form is not the author edit path and applies neither its rules nor its window
    public function updateBody(int $id, string $body): bool {
        if ($id < 1) return false;
        $sql = 'UPDATE '.PREFIX_DB.'_comment SET body = :body, edited = NOW() WHERE id = :id AND deleted IS NULL';
        return (bool)$this->db->getSqlQuery($sql, ['body' => $body, 'id' => $id]);
    }

    # Apply the write rules of one comment and answer every refusal it collects, in the order the submit path applies them, so its caller can show the last one or the whole list
    # The rules a new comment adds are the ones an edit never had: a guest name, the flood window and the captcha; the length rule measures the longest word in characters for both
    public function checkRules(string $mod, string $body, string $name, string $hash, bool $isnew): array {
        $words = array_map(static fn(string $one): int => mb_strlen($one, 'UTF-8'), explode(' ', str_replace(["\n", "\r", "\t"], ' ', $body)));
        $long = $words ? max($words) : 0;
        $last = $isnew ? $this->getLastTime($hash) : '';
        $wait = ($last !== '' ? strtotime($last) : 0) + intval($this->conf['send']);
        $stop = [];
        if ($body === '') $stop[] = _CERROR1;
        if ($long > $this->conf['letter']) $stop[] = _CERROR2;
        if ($isnew && !is_user() && ($name === '' || $this->conf['anonpost'] == 0)) $stop[] = _CERROR3;
        if ($isnew && $wait > time()) $stop[] = sprintf(_CERROR5, $this->conf['send']);
        if (!is_moder($mod) && (($this->conf['link'] == 1 && !is_user()) || $this->conf['link'] == 2) && stripos($body, 'http://') !== false) $stop[] = _CERROR9;
        if ($isnew && checkCaptcha('comment')) $stop[] = _SECCODEINCOR;
        return $stop;
    }

    # Award or take back the points of one comment author in the direction the write that calls this took
    # This half belongs to the transaction of that write and rolls back with it, which is why it stayed where the counter left
    private function updateTargetPoints(string $mod, bool $del, int $uid): void {
        if (!isset(self::MODULES[$mod])) return;
        updatePoints(self::MODULES[$mod][1], $uid, $del ? 1 : 0);
    }

    # Queue the counter of one target to be rewritten once the request is over
    # The count runs after the response rather than inside the write, because the subquery it needs is a locking read of the comment table
    # Two visitors commenting on one target at the same moment would otherwise wait on each other, and the loser of that wait would lose the comment
    # A deferred task rather than a call after the commit, because the class may run inside a transaction it did not open and whose commit it never sees
    private function addTargetCount(int $id, string $mod): void {
        if ($id < 1 || !isset(self::MODULES[$mod])) return;
        addDeferredTask(function() use ($id, $mod): void {
            $this->setTargetCount($id, $mod);
        });
    }

    # Build the visibility scope of one target once, so a count and the page it belongs to can never disagree about what is visible
    # A moderator of the module sees the pending comments as well, which is why the scope depends on the viewer and not on the caller
    # A tree scope keeps a removed comment that still carries a visible reply, because dropping it would take the whole branch below it out of the page
    # The state is bound twice under two names because a native prepared statement rejects one named placeholder used in two positions
    private function getScope(string $mod, int $id, bool $tomb): array {
        $pars = ['cid' => $id, 'mod' => $mod];
        $where = 'WHERE cid = :cid AND modul = :mod';
        $seen = '';
        if (!is_moder($mod)) {
            $pars['stat'] = CommentStatus::Published->value;
            $where .= ' AND status = :stat';
            if ($tomb) {
                $pars['rsta'] = CommentStatus::Published->value;
                $seen = ' AND r.status = :rsta';
            }
        }
        if (!$tomb) return [$where.' AND deleted IS NULL', $pars];
        $tab = PREFIX_DB.'_comment';
        $live = 'SELECT 1 FROM '.$tab.' AS r WHERE r.modul = '.$tab.'.modul AND r.cid = '.$tab.'.cid AND r.deleted IS NULL'
            .$seen.' AND r.path LIKE CONCAT('.$tab.'.path, \'/%\')';
        return [$where.' AND (deleted IS NULL OR EXISTS ('.$live.'))', $pars];
    }

    # Load the replies of the root comments of one page and put every branch behind the root it belongs to, in the order it was written
    # A root shows at most the configured number of replies and reports how many it has, so one long discussion cannot put an unbounded page in front of a reader
    # Two round trips answer the whole page rather than one per root: the counts decide what is left to fetch, and the rows are capped before they are read
    # The cap is a plain LIMIT rather than a window function, because no window function is used anywhere in this project and the distribution targets servers without them
    private function getTreeRows(array $roots, string $where, array $pars, int $full = 0): array {
        if (!$roots) return [];
        $reps = max(1, intval($this->conf['reps'] ?? 5));
        $keys = [];
        foreach ($roots as $key => $one) {
            $keys[] = 'path LIKE :b'.$key;
            $pars['b'.$key] = $one['path'].'/%';
        }
        $from = PREFIX_DB.'_comment '.$where.' AND pid != 0 AND ('.implode(' OR ', $keys).')';
        $seen = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery('SELECT SUBSTRING(path, 1, 10) AS base, COUNT(*) AS num FROM '.$from.' GROUP BY base', $pars)) ?: [] as $row) {
            $seen[(string)$row['base']] = intval($row['num']);
        }
        $kids = [];
        if ($seen) {
            $sql = 'SELECT '.self::FIELDS.' FROM '.$from.' ORDER BY path ASC LIMIT 0, '.(count($roots) * $reps);
            foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
                $one = $this->getRowData($row);
                $base = substr($one['path'], 0, 10);
                if (count($kids[$base] ?? []) >= $reps) continue;
                $kids[$base][] = $one;
            }
        }
        $wide = ($full > 0) ? str_pad((string)$full, 10, '0', STR_PAD_LEFT) : '';
        if ($wide !== '' && ($seen[$wide] ?? 0) > $reps) $kids[$wide] = $this->getBranch($full, $seen[$wide])['rows'];
        $out = [];
        foreach ($roots as $one) {
            $branch = $kids[$one['path']] ?? [];
            $one['kids'] = $seen[$one['path']] ?? 0;
            $one['shown'] = count($branch);
            $out[] = $one;
            foreach ($branch as $kid) $out[] = $kid;
        }
        return $out;
    }

    # Attach the author record of every registered commenter of one row set, so a page of rows needs one account round trip whatever its shape
    private function getAuthorRows(array $rows): array {
        $uids = [];
        foreach ($rows as $one) {
            if ($one['uid'] > 0) $uids[] = $one['uid'];
        }
        $auth = $this->getAuthors($uids);
        foreach ($rows as $key => $one) {
            $rows[$key]['user'] = $auth[$one['uid']] ?? [];
        }
        return $rows;
    }

    # Build the moderation scope against the comment and account join, so the count query carries exactly the predicate the result query carries
    # The author search needs two placeholders for one term because a native prepared statement rejects a named placeholder used twice
    private function getAdminScope(CommentStatus $stat, string $mod, int $find, string $term): array {
        $where = 'WHERE s.status = :stat AND s.deleted IS NULL';
        $pars = ['stat' => $stat->value];
        if ($mod !== '') {
            $where .= ' AND s.modul = :mod';
            $pars['mod'] = $mod;
        }
        if ($term === '') return [$where, $pars];
        if ($find === 3) {
            $pars['fnam'] = '%'.$term.'%';
            $pars['fusr'] = '%'.$term.'%';
            return [$where.' AND (s.name LIKE :fnam OR u.name LIKE :fusr)', $pars];
        }
        $pars['find'] = '%'.$term.'%';
        $where .= match ($find) {
            1 => ' AND s.id LIKE :find',
            4 => ' AND s.modul LIKE :find',
            5 => ' AND s.ip LIKE :find',
            default => ' AND s.body LIKE :find',
        };
        return [$where, $pars];
    }

    # Normalize a submitted comment into the source the row stores: the shared writer escapes, breaks lines and hides quotes for a render model comments no longer use
    # What stays is what changes the text itself rather than its markup: the trusted-html tokens a visitor may not open, the clickable-link rewrite and the censor list
    private function filterCommentBody(string $body, int $flag): string {
        if ($body === '') return '';
        if (!isAdmin()) $body = (string)preg_replace('#\[/?(?:usehtml|usephp)\]#si', '', $body);
        if ($this->site['click'] && $flag != 1) $body = filterClickable($body);
        if (!isAdmin() && $this->site['cens']) {
            foreach (explode(',', $this->site['from']) as $one) {
                $one = trim($one);
                if ($one !== '') $body = (string)preg_replace('#'.preg_quote($one, '#').'#i', $this->site['to'], $body);
            }
        }
        return trim($body);
    }

    # Resolve the path a reply attaches to; an empty string means a root comment and null means the named parent may not be replied to at all
    # A parent of another target, a removed one, one the writer cannot see and one already at the depth limit are refused alike, so a crafted pid can only fail
    private function getParentPath(string $mod, int $cid, int $pid): ?string {
        if ($pid < 1) return '';
        $sql = 'SELECT path, status FROM '.PREFIX_DB.'_comment WHERE id = :id AND modul = :mod AND cid = :cid AND deleted IS NULL';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $pid, 'mod' => $mod, 'cid' => $cid]));
        if (!$row || (string)$row['path'] === '') return null;
        if (intval($row['status']) !== CommentStatus::Published->value && !is_moder($mod)) return null;
        return (substr_count((string)$row['path'], '/') + 2 <= self::MAXDEPTH) ? (string)$row['path'] : null;
    }

    # Build the link flag the body normalization takes: 1 keeps the bare links of this author as text, 0 lets the rewrite make them clickable
    private function getLinkFlag(string $mod): int {
        return (!is_moder($mod) && (($this->conf['alink'] == 1 && !is_user()) || $this->conf['alink'] == 2)) ? 1 : 0;
    }

    # Read the time of the most recent comment of one address, the value the flood window of a new submit is measured against
    # The address is matched through its keyed hash rather than through the stored text, so the flood window needs no index on a value that identifies a visitor
    private function getLastTime(string $hash): string {
        if ($hash === '') return '';
        $sql = 'SELECT time FROM '.PREFIX_DB.'_comment WHERE iphash = :hash ORDER BY time DESC, id DESC LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['hash' => $hash]));
        return (string)($row['time'] ?? '');
    }

    # Derive the flood-control fingerprint of one address, keyed by a purpose secret so a stolen table cannot be walked back to the addresses it was built from
    private function getIpHash(string $ip): string {
        $ip = strtolower(trim($ip));
        return $ip !== '' ? hash_hmac('sha256', $ip, getSecret('commentip')) : '';
    }

    # Accept the idempotency key of a submit only in the shape the unique index expects, and mint one when the request carries none, so no add can ever store an empty key
    private function getRequestKey(string $key): string {
        $key = strtolower(trim($key));
        return preg_match('/^[0-9a-f]{32}$/', $key) ? $key : bin2hex(random_bytes(16));
    }

    # Answer a replayed submit with the result of the row the first one stored, which is what makes a repeated POST return the same comment instead of a second one
    # The answer is never new: this request stored nothing, so whatever the first one wrote beside the comment must not be written again
    private function getKeyResult(string $key, string $name): array {
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT id, name FROM '.PREFIX_DB.'_comment WHERE reqkey = :key', ['key' => $key]));
        if (!$row) return ['id' => 0, 'name' => $name, 'new' => false, 'error' => _ERROR];
        return ['id' => intval($row['id']), 'name' => (string)$row['name'], 'new' => false, 'error' => ''];
    }

    # Count the rows of one already-built source, so the count of a list is always written against the very source the list itself reads
    private function getTotal(string $from, array $pars): int {
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT COUNT(*) AS num FROM '.$from, $pars));
        return $row ? intval($row['num']) : 0;
    }

    # Normalize one stored comment row into typed values, so no consumer has to know that the driver hands out numeric and named keys at once
    private function getRowData(array $row): array {
        return [
            'id' => intval($row['id']),
            'cid' => intval($row['cid']),
            'modul' => (string)$row['modul'],
            'time' => (string)$row['time'],
            'edited' => (string)($row['edited'] ?? ''),
            'deleted' => (string)($row['deleted'] ?? ''),
            'uid' => intval($row['uid']),
            'name' => (string)$row['name'],
            'ip' => (string)$row['ip'],
            'body' => (string)$row['body'],
            'format' => (string)($row['format'] ?? ''),
            'status' => intval($row['status']),
            'pid' => intval($row['pid'] ?? 0),
            'path' => (string)($row['path'] ?? ''),
            'depth' => substr_count((string)($row['path'] ?? ''), '/'),
        ];
    }

    # Resolve the page bounds and the running number the first row of the page carries, from the site-wide sort direction
    # An empty list keeps page one and no offset, because the list region is not rendered at all in that case
    private function getPager(int $total, int $page, int $limit): array {
        $limit = ($limit > 0) ? $limit : 15;
        $isasc = !empty($this->conf['sort']);
        if ($total < 1) return ['total' => 0, 'pages' => 0, 'page' => 1, 'offset' => 0, 'limit' => $limit, 'isasc' => $isasc, 'first' => 0];
        $pages = (int)ceil($total / $limit);
        if ($page < 1) $page = 1;
        if ($page > $pages) $page = $pages;
        $offset = ($page - 1) * $limit;
        $first = $isasc ? $offset + 1 : (($total > $offset) ? $total - $offset : $total);
        return ['total' => $total, 'pages' => $pages, 'page' => $page, 'offset' => $offset, 'limit' => $limit, 'isasc' => $isasc, 'first' => $first];
    }

    # Load the author record of every registered commenter of one page in a single round trip, keyed by account id
    # The group join can answer several rows for one account and the last of them wins, which is the record the current list rendering already picks
    private function getAuthors(array $uids): array {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn($v) => $v > 0)));
        if (!$uids) return [];
        $keys = [];
        $pars = [];
        foreach ($uids as $key => $val) {
            $keys[] = ':u'.$key;
            $pars['u'.$key] = $val;
        }
        $sql = 'SELECT u.id, u.name, u.rank, u.email, u.website, u.avatar, u.regdate, u.origin, u.sig, u.viewmail, u.points, u.warnings, u.gender, u.votes, u.tvotes,'
            .' g.name AS gnam, g.rank AS grnk, g.color AS gclr FROM '.PREFIX_DB.'_users AS u'
            .' LEFT JOIN '.PREFIX_DB.'_groups AS g ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points))'
            .' WHERE u.id IN ('.implode(', ', $keys).') ORDER BY g.extra ASC, g.points ASC';
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $out[intval($row['id'])] = [
                'id' => intval($row['id']),
                'name' => (string)$row['name'],
                'rank' => (string)$row['rank'],
                'email' => (string)$row['email'],
                'website' => (string)$row['website'],
                'avatar' => (string)$row['avatar'],
                'regdate' => (string)$row['regdate'],
                'origin' => (string)$row['origin'],
                'sig' => (string)$row['sig'],
                'viewmail' => (string)$row['viewmail'],
                'points' => intval($row['points']),
                'warnings' => (string)$row['warnings'],
                'gender' => intval($row['gender']),
                'votes' => intval($row['votes']),
                'tvotes' => intval($row['tvotes']),
                'gname' => (string)($row['gnam'] ?? ''),
                'grank' => (string)($row['grnk'] ?? ''),
                'gcolor' => (string)($row['gclr'] ?? ''),
            ];
        }
        return $out;
    }
}
