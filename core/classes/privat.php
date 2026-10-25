<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The three mailboxes one stored private message can be read through
enum PrivatBox: string {
    case Inbox = 'inbox';
    case Saved = 'saved';
    case Outbox = 'outbox';
}

# Private message subsystem: the only code that reaches the message table, with mailbox rules, limits and counters, answering data and never markup
final class Privat {

    # The columns every read of one message row answers with, so a consumer never has to know which of them a given query needed
    private const FIELDS = 'id, uidin, uidout, title, time, viewed, saved, delin, delout';

    # How many messages one bulk action may carry, so a submitted selection cannot grow into an unbounded statement
    private const MAXBULK = 100;

    # How many characters of a body the server cuts for one list row, which is the only part of that column a list ever reads
    private const SNIPCUT = 300;

    # How many days back the newer period reaches and how many days back the older one begins, which are the two boundaries the facet row and the period filter share
    private const FRESHDAY = 7;
    private const STALEDAY = 30;

    # The character the search declares as its own escape, so what an author wrote as a per cent or an underscore stays a per cent or an underscore whatever the SQL mode says
    private const LIKEMARK = '!';

    # The longest search term one selection carries, because a term is a condition and not a document
    private const FINDMAX = 64;

    # How many characters of that cut are left once the source markup is stripped out of it, which is the snippet one row answers
    private const SNIPLEN = 160;

    # The source markup a snippet drops, in this order: a whole markdown link before one the cut broke off, both before the bracket tags, whitespace last
    private const SNIPFROM = [
        '#!?\[([^\]\n]*)\]\([^)\n]*\)#u',
        '#!?\[[^\]\n]*\]\([^)\n]*$#u',
        '#\[/?[a-z][a-z\d]*(?:=[^\]\n]*)?\]#iu',
        '#<[^>]*>#u',
        '#^[ \t]*(?:[>\#]+|[-+*]|\d+\.)[ \t]+#mu',
        '#[`*~]+#u',
        '#\s+#u',
    ];
    private const SNIPTO = ['$1', '', '', '', '', '', ' '];

    # The tag or bracket the cut itself broke open, which is everything from the last unclosed one to the end of the prefix
    private const SNIPOPEN = '#[<\[][^>\]\n]*$#u';

    private Database $db;
    private array $conf;
    private array $site;

    # Snapshot the privat settings and the site settings that still change a stored text, so no method reaches for a global
    public function __construct(Database $db, array $conf) {
        $this->db = $db;
        $this->conf = is_array($conf['privat'] ?? null) ? $conf['privat'] : [];
        $this->site = [
            'click' => !empty($conf['clickable']),
            'cens' => !empty($conf['censor']),
            'from' => (string)($conf['censor_l'] ?? ''),
            'to' => (string)($conf['censor_r'] ?? ''),
        ];
    }

    # The predicate of one mailbox, the only place the four state columns become a filter, so a list, its count and its quota never disagree
    private function getBoxWhere(PrivatBox $box, string $pre = ''): string {
        if ($box === PrivatBox::Outbox) return $pre.'uidout = :uid AND '.$pre.'delout = 0';
        if ($box === PrivatBox::Saved) return $pre.'uidin = :uid AND '.$pre.'delin = 0 AND '.$pre.'saved = 1';
        return $pre.'uidin = :uid AND '.$pre.'delin = 0 AND '.$pre.'saved = 0';
    }

    # The predicate of everything one reader received and has not opened, across both received mailboxes, so the unread count and the unread list agree
    private function getNewWhere(string $pre = ''): string {
        return $pre.'uidin = :uid AND '.$pre.'delin = 0 AND '.$pre.'viewed = 0';
    }

    # The predicate of one participant's own copy; the recipient owns viewed, saved and delin, the sender delout, so one side never rewrites what the other sees
    private function getSideWhere(PrivatBox $box, string $pre = ''): string {
        if ($box === PrivatBox::Outbox) return $pre.'uidout = :uid AND '.$pre.'delout = 0';
        return $pre.'uidin = :uid AND '.$pre.'delin = 0';
    }

    # Count the rows one predicate answers over the aliased table, the whole table for an empty predicate; a search brings the counterpart join along
    private function getTotal(string $where, array $pars, string $join = ''): int {
        $sql = 'SELECT COUNT(p.id) AS num FROM '.PREFIX_DB.'_privat AS p'.$join.(($where === '') ? '' : ' WHERE '.$where);
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, $pars));
        return $row ? intval($row['num']) : 0;
    }

    # The join that names the counterpart of every row with the path of its profile, which a count takes on only when the search reads that name
    private function getMateJoin(PrivatBox $box): string {
        $mate = ($box === PrivatBox::Outbox) ? 'uidin' : 'uidout';
        return ' LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.'.$mate.' = u.id) LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'user\' AND r.oid = u.id AND r.cur = 1';
    }

    # The selection of one list, its binds and whether the search needs the counterpart join; one placeholder per column, as native prepares refuse a repeated name
    private function getPickWhere(PrivatBox $box, array $pick): array {
        $where = $this->getBoxWhere($box, 'p.');
        $pars = [];
        $join = false;
        $stat = (string)($pick['stat'] ?? '');
        if ($stat === 'unread') $where .= ' AND p.viewed = 0';
        if ($stat === 'read') $where .= ' AND p.viewed = 1';
        $perd = (string)($pick['perd'] ?? '');
        if ($perd === 'new') {
            $where .= ' AND p.time >= NOW() - INTERVAL :fnew DAY';
            $pars['fnew'] = self::FRESHDAY;
        }
        if ($perd === 'old') {
            $where .= ' AND p.time < NOW() - INTERVAL :fold DAY';
            $pars['fold'] = self::STALEDAY;
        }
        $find = $this->filterFindText((string)($pick['find'] ?? ''));
        if ($find !== '') {
            $mark = self::LIKEMARK;
            $where .= ' AND (p.title LIKE :ftit ESCAPE \''.$mark.'\' OR p.body LIKE :fbody ESCAPE \''.$mark.'\''
                .' OR u.name LIKE :fusr ESCAPE \''.$mark.'\')';
            $pars['ftit'] = $find;
            $pars['fbody'] = $find;
            $pars['fusr'] = $find;
            $join = true;
        }
        return [$where, $pars, $join];
    }

    # Turn one term into the LIKE pattern; the escape character is escaped before the wildcards, or the escape of a per cent would read as a literal one
    private function filterFindText(string $text): string {
        $text = trim(mb_substr(trim($text), 0, self::FINDMAX));
        if ($text === '') return '';
        $mark = self::LIKEMARK;
        return '%'.str_replace([$mark, '%', '_'], [$mark.$mark, $mark.'%', $mark.'_'], $text).'%';
    }

    # The order of one list from a fixed set, each ending in an id tie-breaker, without which rows sharing a moment repeat or drop across a page boundary
    private function getSortOrder(string $sort): string {
        return match ($sort) {
            'old' => 'p.time ASC, p.id ASC',
            'unread' => 'p.viewed ASC, p.time DESC, p.id DESC',
            'name' => 'u.name ASC, p.time DESC, p.id DESC',
            default => 'p.time DESC, p.id DESC',
        };
    }

    # Resolve the page bounds of one list from its total, so the rows and the pager of a mailbox are computed once and from the same number
    private function getPager(int $total, int $page, int $limit): array {
        $limit = ($limit > 0) ? $limit : 25;
        if ($total < 1) return ['total' => 0, 'pages' => 0, 'page' => 1, 'offset' => 0, 'limit' => $limit, 'rows' => []];
        $pages = (int)ceil($total / $limit);
        if ($page < 1) $page = 1;
        if ($page > $pages) $page = $pages;
        return ['total' => $total, 'pages' => $pages, 'page' => $page, 'offset' => ($page - 1) * $limit, 'limit' => $limit, 'rows' => []];
    }

    # Normalize one stored row into typed values, so no consumer has to know that the driver hands out numeric and named keys at once
    private function getRowData(array $row): array {
        return [
            'id' => intval($row['id']),
            'uidin' => intval($row['uidin']),
            'uidout' => intval($row['uidout']),
            'title' => (string)$row['title'],
            'time' => (string)$row['time'],
            'viewed' => intval($row['viewed']),
            'saved' => intval($row['saved']),
            'delin' => intval($row['delin']),
            'delout' => intval($row['delout']),
        ];
    }

    # The counterpart of one row in one mailbox; an account that no longer exists answers an empty name
    private function getMateData(array $row, PrivatBox $box): array {
        $uid = ($box === PrivatBox::Outbox) ? intval($row['uidin']) : intval($row['uidout']);
        return ['partner' => $uid, 'name' => (string)($row['name'] ?? ''), 'avatar' => (string)($row['avatar'] ?? ''), 'path' => $row['path'] ?? null];
    }

    # The plain one-line snippet of a body prefix; only a prefix the server really cut loses its last open bracket, in a whole body that bracket is the author's
    private function filterSnippet(string $text): string {
        if ($text === '') return '';
        $cut = mb_strlen($text) >= self::SNIPCUT;
        $text = (string)preg_replace(self::SNIPFROM, self::SNIPTO, $text);
        if ($cut) $text = (string)preg_replace(self::SNIPOPEN, '', $text);
        return trim(mb_substr(trim($text), 0, self::SNIPLEN));
    }

    # The state an administrator reads one row as, first match winning; a deletion outranks saved and read, as a deleted copy is what the panel shows first
    private function getRowState(array $row): string {
        if (intval($row['delin'])) return 'delin';
        if (intval($row['delout'])) return 'delout';
        if (intval($row['saved'])) return 'saved';
        if (intval($row['viewed'])) return 'read';
        return 'new';
    }

    # Normalize a selection into a bounded, unique, ascending id list or reject it whole; the order makes parallel bulk actions lock rows in one sequence
    private function getIdList(array $ids): array {
        $out = array_values(array_unique(array_map('intval', $ids)));
        if (!$out || count($out) > self::MAXBULK) return [];
        foreach ($out as $one) {
            if ($one < 1) return [];
        }
        sort($out);
        return $out;
    }

    # Bind an id list one placeholder per value, because a native prepared statement takes neither an array nor one name in two positions
    private function getIdBind(array $ids): array {
        $keys = [];
        $pars = [];
        foreach ($ids as $key => $val) {
            $keys[] = ':m'.$key;
            $pars['m'.$key] = $val;
        }
        return [implode(', ', $keys), $pars];
    }

    # Lock the rows of one batch and report whether every one is the caller's own copy, so one foreign or deleted id fails the whole batch
    private function checkOwner(int $uid, array $ids, PrivatBox $box): bool {
        [$keys, $pars] = $this->getIdBind($ids);
        $pars['uid'] = $uid;
        $sql = 'SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_privat WHERE id IN ('.$keys.') AND '.$this->getSideWhere($box).' FOR UPDATE';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, $pars));
        return $row && intval($row['num']) === count($ids);
    }

    # Roll back a write the method owns and answer false; inside a transaction a caller opened, the rollback is left to that caller
    private function getFailed(bool $own): bool {
        if ($own) $this->db->setSqlRollback();
        return false;
    }

    # Roll back a failed send and answer its code; the retry test runs first, because the database wrapper keeps one last error that a failed rollback overwrites
    private function getFailure(bool $own, string $code, bool $hot = false): array {
        $hot = $hot && $this->checkDeadlock();
        if ($own) $this->db->setSqlRollback();
        return ['id' => 0, 'error' => $code, 'retry' => $hot];
    }

    # Report whether the statement that just failed is a deadlock or a lock wait timeout, which a second attempt can still win
    private function checkDeadlock(): bool {
        return in_array(intval($this->db->getSqlError()['code']), [1205, 1213], true);
    }

    # Normalize one field into the stored source, never HTML-escaped: trusted tags out, the link rewrite for the body only, the censor list
    private function filterMessageText(string $text, bool $link): string {
        if ($text === '') return '';
        $text = filterTrustedTags($text);
        if ($link && $this->site['click']) $text = filterClickable($text);
        if (!isAdmin() && $this->site['cens']) {
            foreach (explode(',', $this->site['from']) as $one) {
                $one = trim($one);
                if ($one !== '') $text = (string)preg_replace('#'.preg_quote($one, '#').'#i', $this->site['to'], $text);
            }
        }
        return trim($text);
    }

    # The length of the longest word of a text in characters, which is what the letter limit of the module bounds
    private function getWordLength(string $text): int {
        $out = 0;
        foreach (preg_split('/\s+/u', $text) ?: [] as $one) {
            $out = max($out, mb_strlen($one));
        }
        return $out;
    }

    # Resolve one account name to its id, over the whole name the column holds rather than over its first 25 bytes
    private function getUserId(string $name): int {
        $name = filterText(mb_substr($name, 0, 25));
        if ($name === '') return 0;
        $row = $this->db->getSqlRow($this->db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users WHERE name = :name LIMIT 1', ['name' => $name]));
        return $row ? intval($row['id']) : 0;
    }

    # Lock both accounts in one statement ascending by id, so opposite sends cannot deadlock; accounts and not rows, as both quotas key off an account
    private function getUserLock(int $uid, int $to): array|false {
        $sql = 'SELECT id FROM '.PREFIX_DB.'_users WHERE id IN (:usra, :usrb) ORDER BY id FOR UPDATE';
        $done = $this->db->getSqlQuery($sql, ['usra' => $uid, 'usrb' => $to]);
        if ($done === false) return false;
        $rows = $this->db->getSqlRows($done);
        return is_array($rows) ? array_map('intval', array_column($rows, 'id')) : false;
    }

    # Report whether the sender is inside their send interval over every sent row, deleted copies included, so deleting one's own copy never resets it
    private function checkFlood(int $uid): bool {
        $wait = intval($this->conf['send'] ?? 0);
        if ($wait < 1) return false;
        $sql = 'SELECT (time > NOW() - INTERVAL '.$wait.' SECOND) AS hot FROM '.PREFIX_DB.'_privat WHERE uidout = :uid ORDER BY time DESC LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['uid' => $uid]));
        return $row && !empty($row['hot']);
    }

    # Report whether the recipient's inbox is full, the only box an arriving message lands in; the saved quota is held by setMessageSaved(), a limit of 0 is none
    private function checkQuota(int $uid): bool {
        $max = $this->getBoxLimit(PrivatBox::Inbox);
        return $max > 0 && $this->getMessageCount($uid, PrivatBox::Inbox) >= $max;
    }

    # The quota of one mailbox from the settings; the outbox has none, as a sender is bounded by the mailbox of the recipient
    public function getBoxLimit(PrivatBox $box): int {
        if ($box === PrivatBox::Outbox) return 0;
        return intval($this->conf[($box === PrivatBox::Saved) ? 'messsav' : 'messin'] ?? 0);
    }

    # How full one mailbox is, the per cent clamped at 100 for a quota lowered under it and 0 for a box without a quota, as unmeasured is not full
    public function getBoxFill(int $uid, PrivatBox $box): array {
        $has = $this->getMessageCount($uid, $box);
        $max = $this->getBoxLimit($box);
        return ['has' => $has, 'max' => $max, 'part' => ($max > 0) ? min(100.0, $has * 100 / $max) : 0.0];
    }

    # Count one mailbox of one account, which is the number its list pages, its counter and its quota are all read from
    public function getMessageCount(int $uid, PrivatBox $box): int {
        if ($uid < 1) return 0;
        return $this->getTotal($this->getBoxWhere($box), ['uid' => $uid]);
    }

    # Count the messages one account has not opened yet, saved ones included: a message saved without being read is still unread
    public function getUnreadCount(int $uid): int {
        if ($uid < 1) return 0;
        return $this->getTotal($this->getNewWhere(), ['uid' => $uid]);
    }

    # Count the unopened messages of one mailbox; the inbox and saved badges add up to what getUnreadCount() answers
    public function getUnreadBoxCount(int $uid, PrivatBox $box): int {
        if ($uid < 1) return 0;
        return $this->getTotal($this->getBoxWhere($box).' AND viewed = 0', ['uid' => $uid]);
    }

    # Count the sent messages the recipient has not opened yet; a copy the sender deleted is no longer waiting
    public function getUnreadOutCount(int $uid): int {
        return $this->getUnreadBoxCount($uid, PrivatBox::Outbox);
    }

    # One page of one mailbox under one selection with its counterparts; the body is read as the server-cut prefix, so a page costs no MEDIUMTEXT per row
    public function getMessageList(int $uid, PrivatBox $box, int $page = 1, array $pick = []): array {
        [$where, $pars, $join] = $this->getPickWhere($box, $pick);
        $pars['uid'] = $uid;
        $link = $this->getMateJoin($box);
        $out = $this->getPager(($uid < 1) ? 0 : $this->getTotal($where, $pars, $join ? $link : ''), $page, intval($this->conf['num'] ?? 25));
        if ($uid < 1 || $out['total'] < 1) return $out;
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', LEFT(p.body, '.self::SNIPCUT.') AS snip, u.name, u.avatar, r.path'
            .' FROM '.PREFIX_DB.'_privat AS p'.$link.' WHERE '.$where
            .' ORDER BY '.$this->getSortOrder((string)($pick['sort'] ?? '')).' LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + $this->getMateData($row, $box) + ['snip' => $this->filterSnippet((string)($row['snip'] ?? ''))];
        }
        return $out;
    }

    # The facets of one mailbox in one aggregate row, each measured against the mailbox alone, never against the search or the other facets
    public function getBoxFacets(int $uid, PrivatBox $box): array {
        $out = ['total' => 0, 'unread' => 0, 'read' => 0, 'fresh' => 0, 'stale' => 0];
        if ($uid < 1) return $out;
        $sql = 'SELECT COUNT(id) AS total, SUM(viewed = 0) AS unseen, SUM(viewed = 1) AS seen,'
            .' SUM(time >= NOW() - INTERVAL :fnew DAY) AS fresh, SUM(time < NOW() - INTERVAL :fold DAY) AS stale'
            .' FROM '.PREFIX_DB.'_privat WHERE '.$this->getBoxWhere($box);
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['uid' => $uid, 'fnew' => self::FRESHDAY, 'fold' => self::STALEDAY]));
        if (!$row) return $out;
        return [
            'total' => intval($row['total']),
            'unread' => intval($row['unseen']),
            'read' => intval($row['seen']),
            'fresh' => intval($row['fresh']),
            'stale' => intval($row['stale']),
        ];
    }

    # The newest messages of one inbox, or with $new everything unread across both received boxes, the predicate getUnreadCount() counts
    public function getRecentList(int $uid, int $num = 6, bool $new = false): array {
        if ($uid < 1 || $num < 1) return [];
        $where = $new ? $this->getNewWhere('p.') : $this->getBoxWhere(PrivatBox::Inbox, 'p.');
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', LEFT(p.body, '.self::SNIPCUT.') AS snip, u.name, u.avatar, r.path FROM '.PREFIX_DB.'_privat AS p'
            .$this->getMateJoin(PrivatBox::Inbox).' WHERE '.$where
            .' ORDER BY p.time DESC, p.id DESC LIMIT 0, '.intval($num);
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, ['uid' => $uid])) ?: [] as $row) {
            $out[] = $this->getRowData($row) + $this->getMateData($row, PrivatBox::Inbox)
                + ['snip' => $this->filterSnippet((string)($row['snip'] ?? ''))];
        }
        return $out;
    }

    # One message as one participant reads it, empty once that side deleted it; the snippet travels along for the list row redrawn from this answer
    public function getMessageView(int $uid, int $id, PrivatBox $box): array {
        if ($uid < 1 || $id < 1) return [];
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', p.body, p.ip, u.name, u.avatar, r.path FROM '.PREFIX_DB.'_privat AS p'
            .$this->getMateJoin($box).' WHERE p.id = :id AND '.$this->getSideWhere($box, 'p.').' LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id, 'uid' => $uid]));
        if (!$row) return [];
        return $this->getRowData($row) + $this->getMateData($row, $box)
            + ['body' => (string)$row['body'], 'ip' => (string)$row['ip'], 'snip' => $this->filterSnippet((string)$row['body'])];
    }

    # The body of one message to a side that still holds it, to anyone for $all, empty otherwise; checkPrivatFile() in core/user.php gates files by it
    public function getMessageBody(int $uid, int $id, bool $all = false): string {
        if ($id < 1 || (!$all && $uid < 1)) return '';
        $sql = 'SELECT body FROM '.PREFIX_DB.'_privat WHERE id = :id'.($all ? '' : ' AND (uidin = :uin AND delin = 0 OR uidout = :uout AND delout = 0)').' LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id] + ($all ? [] : ['uin' => $uid, 'uout' => $uid])));
        return $row ? (string)$row['body'] : '';
    }

    # The bodies carrying an attachment of every message one reader still holds on either side, the names it may quote or forward, or of every stored message for $all
    public function getAttachBodies(int $uid, bool $all = false): array {
        if ($uid < 1 && !$all) return [];
        $sql = 'SELECT body FROM '.PREFIX_DB.'_privat WHERE '.($all ? '' : '(uidin = :uin AND delin = 0 OR uidout = :uout AND delout = 0) AND ').'body LIKE \'%[attach=%\'';
        $res = $this->db->getSqlQuery($sql, $all ? [] : ['uin' => $uid, 'uout' => $uid]);
        return array_map(static fn(array $row): string => (string)$row['body'], $this->db->getSqlRows($res) ?: []);
    }

    # Return one page of the administrator list newest first with both names and its state; the panel also sees copies the participants deleted
    public function getAdminList(string $state, int $find, string $term, int $page): array {
        [$from, $where, $pars] = $this->getAdminScope($state, $find, $term);
        $top = $this->db->getSqlRow($this->db->getSqlQuery('SELECT COUNT(p.id) AS num, MAX(p.id) AS top FROM '.$from.$where, $pars));
        $total = intval($top['num'] ?? 0);
        $base = intval($this->conf['anum'] ?? 50);
        $out = $this->getPager($total, $page, getListLimit($base, $total)) + ['base' => $base, 'upto' => 0];
        if ($out['total'] < 1) return $out;
        $out['upto'] = intval($top['top'] ?? 0);
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', p.body, p.ip, i.name AS namein, o.name AS nameout FROM '.$from.$where
            .' ORDER BY p.time DESC, p.id DESC LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + [
                'body' => (string)$row['body'],
                'ip' => (string)$row['ip'],
                'state' => $this->getRowState($row),
                'namein' => (string)($row['namein'] ?? ''),
                'nameout' => (string)($row['nameout'] ?? ''),
            ];
        }
        return $out;
    }

    # The table, condition and values of the administrator list by the state getRowState() names and a search, which its count and a sweep of the panel read
    public function getAdminScope(string $state, int $find, string $term): array {
        $from = PREFIX_DB.'_privat AS p LEFT JOIN '.PREFIX_DB.'_users AS i ON (p.uidin = i.id) LEFT JOIN '.PREFIX_DB.'_users AS o ON (p.uidout = o.id)';
        $live = 'p.delin = 0 AND p.delout = 0';
        $cond = match ($state) {
            'delin' => ['p.delin = 1'],
            'delout' => ['p.delin = 0 AND p.delout = 1'],
            'saved' => [$live.' AND p.saved = 1'],
            'read' => [$live.' AND p.saved = 0 AND p.viewed = 1'],
            'new' => [$live.' AND p.saved = 0 AND p.viewed = 0'],
            default => [],
        };
        $pars = [];
        $text = $this->filterFindText($term);
        if ($text !== '') {
            $cond[] = match ($find) {1 => 'p.id', 3 => 'o.name', 4 => 'i.name', 5 => 'p.ip', default => 'p.title'}.' LIKE :find ESCAPE \''.self::LIKEMARK.'\'';
            $pars['find'] = $text;
        }
        return [$from, $cond ? ' WHERE '.implode(' AND ', $cond) : '', $pars];
    }

    # Store one message and answer its id or refusal code; a deadlock or lock timeout is retried once, only when the send owns its transaction and none is left open after it
    public function addMessage(int $uid, string $name, string $title, string $body, string $ip): array {
        $title = filterCutText(trim($this->filterMessageText($title, false)), 100);
        $body = $this->filterMessageText($body, true);
        $room = checkEditorTextRoom($body, 'privat.body');
        if ($room !== '') return ['id' => 0, 'error' => 'no_room', 'note' => $room];
        $own = !$this->db->checkSqlActive();
        $out = $this->addMessageRow($uid, $name, $title, $body, $ip);
        if ($own && !empty($out['retry']) && !$this->db->checkSqlActive()) $out = $this->addMessageRow($uid, $name, $title, $body, $ip);
        return ['id' => $out['id'], 'error' => $out['error']];
    }

    # One attempt at storing a message; the name is resolved before the transaction on purpose, as its first plain read would fix the snapshot the quotas read
    private function addMessageRow(int $uid, string $name, string $title, string $body, string $ip): array {
        $title = trim($title);
        $body = trim($body);
        if ($uid < 1) return ['id' => 0, 'error' => 'not_logged'];
        if (trim($name) === '') return ['id' => 0, 'error' => 'no_recipient'];
        if ($title === '') return ['id' => 0, 'error' => 'no_title'];
        if ($body === '') return ['id' => 0, 'error' => 'no_body'];
        $long = intval($this->conf['letter'] ?? 0);
        if ($long > 0 && $this->getWordLength($body) > $long) return ['id' => 0, 'error' => 'word_long'];
        $to = $this->getUserId($name);
        if ($to < 1) return ['id' => 0, 'error' => 'unknown_recipient'];
        if (!empty($this->conf['himself']) && $to === $uid) return ['id' => 0, 'error' => 'self'];
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return ['id' => 0, 'error' => 'sql'];
        $lock = $this->getUserLock($uid, $to);
        if ($lock === false) return $this->getFailure($own, 'sql', true);
        if (!in_array($to, $lock, true)) return $this->getFailure($own, 'unknown_recipient');
        if (!in_array($uid, $lock, true)) return $this->getFailure($own, 'not_logged');
        if ($this->checkFlood($uid)) return $this->getFailure($own, 'flood');
        if ($this->checkQuota($to)) return $this->getFailure($own, 'quota');
        $done = $this->db->getSqlQuery(
            'INSERT INTO '.PREFIX_DB.'_privat (uidin, uidout, title, body, time, ip) VALUES (:uidin, :uidout, :title, :body, NOW(), :ip)',
            ['uidin' => $to, 'uidout' => $uid, 'title' => $title, 'body' => $body, 'ip' => $ip]
        );
        if ($done === false) return $this->getFailure($own, 'sql', true);
        $new = intval($this->db->getSqlLastId());
        if ($own && !$this->db->setSqlCommit()) return $this->getFailure($own, 'sql');
        return ['id' => $new, 'error' => 'ok'];
    }

    # Set one state column of a batch the reader received; the column comes from a fixed pair, as it is the one unbound part of the statement
    private function setMessageFlag(int $uid, array $ids, string $col, int $val): bool {
        $ids = $this->getIdList($ids);
        if ($uid < 1 || !$ids || !in_array($col, ['viewed', 'saved'], true)) return false;
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        if (!$this->checkOwner($uid, $ids, PrivatBox::Inbox)) return $this->getFailed($own);
        [$keys, $pars] = $this->getIdBind($ids);
        $pars['uid'] = $uid;
        $pars['val'] = $val;
        $sql = 'UPDATE '.PREFIX_DB.'_privat SET '.$col.' = :val WHERE id IN ('.$keys.') AND '.$this->getSideWhere(PrivatBox::Inbox);
        if ($this->db->getSqlQuery($sql, $pars) === false) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }

    # Mark a batch of received messages read or unread, which are two actions and not one transition
    public function setMessageRead(int $uid, array $ids, bool $read): bool {
        return $this->setMessageFlag($uid, $ids, 'viewed', $read ? 1 : 0);
    }

    # Move a batch into the saved folder or out; the account is locked before the rows, in the order a send locks, so two batches cannot both take the last room
    public function setMessageSaved(int $uid, array $ids, bool $keep): bool {
        $ids = $this->getIdList($ids);
        if ($uid < 1 || !$ids) return false;
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        if ($keep && $this->getUserLock($uid, $uid) === false) return $this->getFailed($own);
        if (!$this->checkOwner($uid, $ids, PrivatBox::Inbox)) return $this->getFailed($own);
        $max = ($keep) ? $this->getBoxLimit(PrivatBox::Saved) : 0;
        if ($max > 0) {
            [$keys, $pars] = $this->getIdBind($ids);
            $pars['uid'] = $uid;
            $add = $this->getTotal('id IN ('.$keys.') AND '.$this->getSideWhere(PrivatBox::Inbox).' AND saved = 0', $pars);
            if ($this->getMessageCount($uid, PrivatBox::Saved) + $add > $max) return $this->getFailed($own);
        }
        if (!$this->setMessageFlag($uid, $ids, 'saved', $keep ? 1 : 0)) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }

    # Remove a batch from one side of the mailbox; the row itself is erased only once neither side holds it
    public function deleteMessage(int $uid, array $ids, PrivatBox $box): bool {
        $ids = $this->getIdList($ids);
        if ($uid < 1 || !$ids) return false;
        $col = ($box === PrivatBox::Outbox) ? 'delout' : 'delin';
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        if (!$this->checkOwner($uid, $ids, $box)) return $this->getFailed($own);
        [$keys, $pars] = $this->getIdBind($ids);
        $pars['uid'] = $uid;
        $sql = 'UPDATE '.PREFIX_DB.'_privat SET '.$col.' = 1 WHERE id IN ('.$keys.') AND '.$this->getSideWhere($box);
        if ($this->db->getSqlQuery($sql, $pars) === false) return $this->getFailed($own);
        [$keys, $pars] = $this->getIdBind($ids);
        if ($this->db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id IN ('.$keys.') AND delin = 1 AND delout = 1', $pars) === false) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }

    # Take one account out of every mailbox it appears in; the counterpart keeps a readable copy of a message from an account that is gone
    public function deleteUser(int $uid): bool {
        if ($uid < 1) return false;
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        $sql = 'UPDATE '.PREFIX_DB.'_privat SET delin = 1 WHERE uidin = :uid AND delin = 0';
        if ($this->db->getSqlQuery($sql, ['uid' => $uid]) === false) return $this->getFailed($own);
        $sql = 'UPDATE '.PREFIX_DB.'_privat SET delout = 1 WHERE uidout = :uid AND delout = 0';
        if ($this->db->getSqlQuery($sql, ['uid' => $uid]) === false) return $this->getFailed($own);
        $sql = 'DELETE FROM '.PREFIX_DB.'_privat WHERE delin = 1 AND delout = 1 AND (uidin = :uin OR uidout = :uout)';
        if ($this->db->getSqlQuery($sql, ['uin' => $uid, 'uout' => $uid]) === false) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }

    # Erase a batch of messages as an administrator, which is the one deletion that answers to no mailbox state; a batch none of whose rows was there is refused
    public function deleteAdminMessage(array $ids): bool {
        $ids = $this->getIdList($ids);
        if (!$ids) return false;
        [$keys, $pars] = $this->getIdBind($ids);
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        $res = $this->db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id IN ('.$keys.')', $pars);
        if ($res === false) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return $res->rowCount() > 0;
    }
}
