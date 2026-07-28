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
# This batch adds the reads only; the write path, the target resolver and the counter update follow in the batches after it
class Comment {

    private Database $db;
    private Parser $prs;
    private array $conf;

    # Build the subsystem from the services the request already carries; the parser is kept because the read helpers that render a body move here in a later batch
    public function __construct(Database $db, Parser $prs, array $conf) {
        $this->db = $db;
        $this->prs = $prs;
        $this->conf = is_array($conf['comments'] ?? null) ? $conf['comments'] : [];
    }

    # Count the comments of one target that the current viewer may see
    public function getCount(string $mod, int $id): int {
        [$where, $pars] = $this->getScope($mod, $id);
        return $this->getTotal(PREFIX_DB.'_comment '.$where, $pars);
    }

    # Return one page of the comment list of a target together with the author record of every registered commenter on it
    # The scope is resolved once and both queries run against it, so the count and the page can never answer to different permissions
    public function getList(string $mod, int $id, int $page): array {
        [$where, $pars] = $this->getScope($mod, $id);
        $out = $this->getPager($this->getTotal(PREFIX_DB.'_comment '.$where, $pars), $page, intval($this->conf['num'] ?? 15));
        $out['rows'] = [];
        if ($out['total'] < 1) return $out;
        $sql = 'SELECT id, cid, modul, time, uid, name, ip, body, status FROM '.PREFIX_DB.'_comment '.$where
            .' ORDER BY time '.($out['isasc'] ? 'ASC' : 'DESC').' LIMIT '.$out['offset'].', '.$out['limit'];
        $uids = [];
        $list = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $list[] = $this->getRowData($row) + ['user' => []];
            if (intval($row['uid']) > 0) $uids[] = intval($row['uid']);
        }
        $auth = $this->getAuthors($uids);
        foreach ($list as $key => $row) {
            if (isset($auth[$row['uid']])) $list[$key]['user'] = $auth[$row['uid']];
        }
        $out['rows'] = $list;
        return $out;
    }

    # Return one page of the moderation list, narrowed by state, by module and by one of the five search fields
    public function getAdminList(CommentStatus $stat, string $mod, int $find, string $term, int $page): array {
        [$where, $pars] = $this->getAdminScope($stat, $mod, $find, $term);
        $join = PREFIX_DB.'_comment AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) '.$where;
        $out = $this->getPager($this->getTotal($join, $pars), $page, intval($this->conf['anum'] ?? 25));
        $out['rows'] = [];
        if ($out['total'] < 1) return $out;
        $sql = 'SELECT s.id, s.cid, s.modul, s.time, s.uid, s.name, s.ip, s.body, s.status, u.name AS unam FROM '.$join
            .' ORDER BY s.time '.($out['isasc'] ? 'ASC' : 'DESC').' LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, $pars)) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + ['nick' => (string)($row['unam'] ?? '')];
        }
        return $out;
    }

    # Return one comment by its id, with the account name of a registered author, or an empty array when the row is gone
    public function getComment(int $id): array {
        $sql = 'SELECT s.id, s.cid, s.modul, s.time, s.uid, s.name, s.ip, s.body, s.status, u.name AS unam FROM '.PREFIX_DB.'_comment AS s'
            .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :id';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id]));
        return $row ? $this->getRowData($row) + ['nick' => (string)($row['unam'] ?? '')] : [];
    }

    # Return the published comments of one account, newest first, for the activity feed of a profile
    public function getUserList(int $uid, int $limit): array {
        if ($uid < 1 || $limit < 1) return [];
        $sql = 'SELECT id, cid, modul, time, uid, name, ip, body, status FROM '.PREFIX_DB.'_comment WHERE uid = :uid AND status = :stat ORDER BY id DESC LIMIT 0, '.intval($limit);
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, ['uid' => $uid, 'stat' => CommentStatus::Published->value])) ?: [] as $row) {
            $out[] = $this->getRowData($row);
        }
        return $out;
    }

    # Return the module names the stored comments actually use, for the module selector of the moderation list
    public function getModuleList(): array {
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_comment ORDER BY modul ASC')) ?: [] as $row) {
            if ($row['modul'] !== '') $out[] = (string)$row['modul'];
        }
        return $out;
    }

    # Build the visibility scope of one target once, so a count and the page it belongs to can never disagree about what is visible
    # A moderator of the module sees the pending comments as well, which is why the scope depends on the viewer and not on the caller
    private function getScope(string $mod, int $id): array {
        $pars = ['cid' => $id, 'mod' => $mod];
        if (is_moder($mod)) return ['WHERE cid = :cid AND modul = :mod', $pars];
        $pars['stat'] = CommentStatus::Published->value;
        return ['WHERE cid = :cid AND modul = :mod AND status = :stat', $pars];
    }

    # Build the moderation scope against the comment and account join, so the count query carries exactly the predicate the result query carries
    # The author search needs two placeholders for one term because a native prepared statement rejects a named placeholder used twice
    private function getAdminScope(CommentStatus $stat, string $mod, int $find, string $term): array {
        $where = 'WHERE s.status = :stat';
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
            'uid' => intval($row['uid']),
            'name' => (string)$row['name'],
            'ip' => (string)$row['ip'],
            'body' => (string)$row['body'],
            'status' => intval($row['status']),
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
