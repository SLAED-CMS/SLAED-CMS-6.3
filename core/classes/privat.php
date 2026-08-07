<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The three mailboxes one stored private message can be read through, each one a row of the predicate table of docs/PRIVAT-2026.md
enum PrivatBox: string {
    case Inbox = 'inbox';
    case Saved = 'saved';
    case Outbox = 'outbox';
}

# Private message subsystem: every read and write of the private message table, with the mailbox rules, the limits and the counters in one place
# Nothing outside this class reaches the table any more, and no caller restates a mailbox predicate: the four state columns are read here or nowhere
# The recipient owns viewed, saved and delin, the sender owns delout, so what one participant does to their copy never rewrites what the other one sees
# Every write wraps its own transaction; when a transaction is already open it joins it and leaves begin, commit and rollback to whoever owns it
# The class answers data and never markup, holds no template or mail dependency, and leaves points and notifications to the adapter that called it
final class Privat {

    # The columns every read of one message row answers with, so a consumer never has to know which of them a given query needed
    private const FIELDS = 'id, uidin, uidout, title, time, viewed, saved, delin, delout';

    # How many messages one bulk action may carry, so a submitted selection cannot grow into an unbounded statement
    private const MAXBULK = 100;

    private Database $db;
    private array $conf;
    private array $site;

    # Build the subsystem from the connection the request already carries and snapshot its own settings, so no method reaches for a global
    # The site settings the write normalization reads are snapshotted beside them, because a stored message is source and only these three still change its text
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

    # The predicate of one mailbox, as the predicate table of the plan states it, optionally through a table alias
    # This is the only place the four state columns are turned into a filter, which is what keeps a list, its count and its quota from ever disagreeing
    private function getBoxWhere(PrivatBox $box, string $pre = ''): string {
        if ($box === PrivatBox::Outbox) return $pre.'uidout = :uid AND '.$pre.'delout = 0';
        if ($box === PrivatBox::Saved) return $pre.'uidin = :uid AND '.$pre.'delin = 0 AND '.$pre.'saved = 1';
        return $pre.'uidin = :uid AND '.$pre.'delin = 0 AND '.$pre.'saved = 0';
    }

    # The predicate one participant reaches their own copy of a single message through, which is what every mutation authorizes itself with
    private function getSideWhere(PrivatBox $box, string $pre = ''): string {
        if ($box === PrivatBox::Outbox) return $pre.'uidout = :uid AND '.$pre.'delout = 0';
        return $pre.'uidin = :uid AND '.$pre.'delin = 0';
    }

    # Count the rows one predicate answers, or the whole table when the administrator list asks without one
    private function getTotal(string $where, array $pars): int {
        $sql = 'SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_privat'.(($where === '') ? '' : ' WHERE '.$where);
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, $pars));
        return $row ? intval($row['num']) : 0;
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

    # The counterpart of one row in one mailbox: the account a message came from, or the one it went to
    # An account that no longer exists answers an empty name, which is what the adapter renders as the anonymous reader
    private function getMateData(array $row, PrivatBox $box): array {
        $uid = ($box === PrivatBox::Outbox) ? intval($row['uidin']) : intval($row['uidout']);
        return ['partner' => $uid, 'name' => (string)($row['name'] ?? ''), 'avatar' => (string)($row['avatar'] ?? '')];
    }

    # The state an administrator reads one row as, derived from the four columns in the order the plan fixes, first match winning
    # The recipient side outranks saved and read because a deleted copy is what an administrator has to see first
    private function getRowState(array $row): string {
        if (intval($row['delin'])) return 'delin';
        if (intval($row['delout'])) return 'delout';
        if (intval($row['saved'])) return 'saved';
        if (intval($row['viewed'])) return 'read';
        return 'new';
    }

    # Normalize a submitted selection into a bounded, deduplicated, ascending list of ids, and reject the whole batch when one of them is not an id
    # The order is what makes two parallel bulk actions take the row locks in the same sequence
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

    # Lock the rows of one batch and report whether every one of them is the caller's own copy in that mailbox side
    # One foreign or already deleted id fails the whole batch, so a submitted selection can never mutate a row it does not own
    private function checkOwner(int $uid, array $ids, PrivatBox $box): bool {
        [$keys, $pars] = $this->getIdBind($ids);
        $pars['uid'] = $uid;
        $sql = 'SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_privat WHERE id IN ('.$keys.') AND '.$this->getSideWhere($box).' FOR UPDATE';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, $pars));
        return $row && intval($row['num']) === count($ids);
    }

    # Roll back a write that cannot be completed and answer the false every mutating method reports a refusal with
    private function getFailed(bool $own): bool {
        if ($own) $this->db->setSqlRollback();
        return false;
    }

    # Roll back a send that cannot be completed and answer the machine code the adapter maps to a message of its own
    # A statement the server itself broke off also carries whether one more attempt could still win, which the send strips off again before it answers its caller
    # Only the two call sites where a statement answered false ask that, and before the rollback, because the wrapper holds one last error and a rollback would overwrite it
    private function getFailure(bool $own, string $code, bool $hot = false): array {
        $hot = $hot && $this->checkDeadlock();
        if ($own) $this->db->setSqlRollback();
        return ['id' => 0, 'error' => $code, 'retry' => $hot];
    }

    # Report whether the statement that has just failed is one a second attempt can still win: a deadlock the server broke up, or a lock it waited too long for
    # It is asked only right after a statement answered false, because the wrapper holds its last error until another statement replaces it
    private function checkDeadlock(): bool {
        return in_array(intval($this->db->getSqlError()['code']), [1205, 1213], true);
    }

    # Normalize one submitted field into the source the row stores: the old writer escaped it for a render model this module no longer uses, and that escape is what stage 2 drops
    # What stays is what changes the text itself rather than its markup: the trusted tags no message may carry whoever wrote it, the clickable-link rewrite and the censor list
    # Only the body is asked for the rewrite, because the title never carried it under the old writer either, and a title is plain text that names no link
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

    # Lock the accounts a write answers to in one statement, ascending by id, and report which of them the server really holds
    # One statement in one fixed order is what keeps two sends in opposite directions from deadlocking against each other, and a message to yourself collapses into a single row
    # The accounts are the lock target and not the messages: both quotas key off an account, and no row lock can serialize the first message an account ever receives
    private function getUserLock(int $uid, int $to): array|false {
        $sql = 'SELECT id FROM '.PREFIX_DB.'_users WHERE id IN (:usra, :usrb) ORDER BY id FOR UPDATE';
        $done = $this->db->getSqlQuery($sql, ['usra' => $uid, 'usrb' => $to]);
        if ($done === false) return false;
        $rows = $this->db->getSqlRows($done);
        return is_array($rows) ? array_map('intval', array_column($rows, 'id')) : false;
    }

    # Report whether the sender is still inside their own send interval, over the sent messages themselves and not over what is left of them
    # A sender deleting their own copy must not reset the interval, so this is the one read of the table that filters no state at all
    # The stored moment is compared by the server that wrote it: an installation whose database runs on another time zone than PHP would otherwise never see a window at all
    private function checkFlood(int $uid): bool {
        $wait = intval($this->conf['send'] ?? 0);
        if ($wait < 1) return false;
        $sql = 'SELECT (time > NOW() - INTERVAL '.$wait.' SECOND) AS hot FROM '.PREFIX_DB.'_privat WHERE uidout = :uid ORDER BY time DESC LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['uid' => $uid]));
        return $row && !empty($row['hot']);
    }

    # Report whether the mailbox of the recipient has no room left, over both quotas the module carries
    private function checkQuota(int $uid): bool {
        return $this->getMessageCount($uid, PrivatBox::Inbox) >= intval($this->conf['messin'] ?? 0)
            || $this->getMessageCount($uid, PrivatBox::Saved) >= intval($this->conf['messsav'] ?? 0);
    }

    # Count one mailbox of one account, which is the number its list pages, its counter and its quota are all read from
    public function getMessageCount(int $uid, PrivatBox $box): int {
        if ($uid < 1) return 0;
        return $this->getTotal($this->getBoxWhere($box), ['uid' => $uid]);
    }

    # Count the messages one account has not opened yet, saved ones included: a message saved without being read is still unread
    public function getUnreadCount(int $uid): int {
        if ($uid < 1) return 0;
        return $this->getTotal('uidin = :uid AND delin = 0 AND viewed = 0', ['uid' => $uid]);
    }

    # Count the messages one account has sent that their recipient has not opened yet, which is the outgoing counter of the sidebar block
    # It filters delout because a sender who threw their own copy away is no longer waiting for it to be read
    public function getUnreadOutCount(int $uid): int {
        if ($uid < 1) return 0;
        return $this->getTotal('uidout = :uid AND delout = 0 AND viewed = 0', ['uid' => $uid]);
    }

    # Return one page of one mailbox, newest first, with the counterpart of every message resolved in the same round trip
    public function getMessageList(int $uid, PrivatBox $box, int $page = 1): array {
        $out = $this->getPager($this->getMessageCount($uid, $box), $page, intval($this->conf['num'] ?? 25));
        if ($uid < 1 || $out['total'] < 1) return $out;
        $mate = ($box === PrivatBox::Outbox) ? 'uidin' : 'uidout';
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', u.name, u.avatar FROM '.PREFIX_DB.'_privat AS p'
            .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.'.$mate.' = u.id) WHERE '.$this->getBoxWhere($box, 'p.')
            .' ORDER BY p.time DESC, p.id DESC LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, ['uid' => $uid])) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + $this->getMateData($row, $box);
        }
        return $out;
    }

    # Return the newest messages of one inbox for the dashboard preview, which is the inbox list without its pager
    public function getRecentList(int $uid, int $num = 6): array {
        if ($uid < 1 || $num < 1) return [];
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', u.name, u.avatar FROM '.PREFIX_DB.'_privat AS p'
            .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uidout = u.id) WHERE '.$this->getBoxWhere(PrivatBox::Inbox, 'p.')
            .' ORDER BY p.time DESC, p.id DESC LIMIT 0, '.intval($num);
        $out = [];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql, ['uid' => $uid])) ?: [] as $row) {
            $out[] = $this->getRowData($row) + $this->getMateData($row, PrivatBox::Inbox);
        }
        return $out;
    }

    # Return one message as one of its two participants reads it, or nothing at all when that side has deleted its copy
    # The counterpart is answered with the row, so the profile a detail view renders is the other account and never the reader's own
    public function getMessageView(int $uid, int $id, PrivatBox $box): array {
        if ($uid < 1 || $id < 1) return [];
        $mate = ($box === PrivatBox::Outbox) ? 'uidin' : 'uidout';
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', p.body, p.ip, u.name, u.avatar FROM '.PREFIX_DB.'_privat AS p'
            .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.'.$mate.' = u.id) WHERE p.id = :id AND '.$this->getSideWhere($box, 'p.').' LIMIT 1';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['id' => $id, 'uid' => $uid]));
        if (!$row) return [];
        return $this->getRowData($row) + $this->getMateData($row, $box) + ['body' => (string)$row['body'], 'ip' => (string)$row['ip']];
    }

    # Return one page of the administrator list, newest first, with both account names and the state the four columns add up to
    # It filters no state on purpose: what an administrator has to see includes the copies the participants have already deleted
    public function getAdminList(int $page = 1): array {
        $out = $this->getPager($this->getTotal('', []), $page, intval($this->conf['anum'] ?? 50));
        if ($out['total'] < 1) return $out;
        $sql = 'SELECT p.'.str_replace(', ', ', p.', self::FIELDS).', p.body, i.name AS namein, o.name AS nameout FROM '.PREFIX_DB.'_privat AS p'
            .' LEFT JOIN '.PREFIX_DB.'_users AS i ON (p.uidin = i.id) LEFT JOIN '.PREFIX_DB.'_users AS o ON (p.uidout = o.id)'
            .' ORDER BY p.time DESC, p.id DESC LIMIT '.$out['offset'].', '.$out['limit'];
        foreach ($this->db->getSqlRows($this->db->getSqlQuery($sql)) ?: [] as $row) {
            $out['rows'][] = $this->getRowData($row) + [
                'body' => (string)$row['body'],
                'state' => $this->getRowState($row),
                'namein' => (string)($row['namein'] ?? ''),
                'nameout' => (string)($row['nameout'] ?? ''),
            ];
        }
        return $out;
    }

    # Store one private message and answer its id, or the machine code that says why it was refused
    # A send the server broke off is attempted once more, because a deadlock and a lock that timed out say nothing about the message itself
    # Only a send that owns its transaction is retried: a caller that opened one owns everything a rollback would take with it
    # A transaction still open after the failure is never attempted again either, because a second attempt would join it and leave the message to a commit that never comes
    # Both fields are normalized once, before the first attempt: a second attempt writes the bytes the first one meant to write and rewrites nothing
    # The room of the column is measured on the normalized body and nowhere else, because the rewrite of a link is what decides the last bytes, and no_room is the one code that carries a ready note with it
    public function addMessage(int $uid, string $name, string $title, string $body, string $ip): array {
        $title = $this->filterMessageText($title, false);
        $body = $this->filterMessageText($body, true);
        $room = checkEditorTextRoom($body, 'privat.body');
        if ($room !== '') return ['id' => 0, 'error' => 'no_room', 'note' => $room];
        $own = !$this->db->checkSqlActive();
        $out = $this->addMessageRow($uid, $name, $title, $body, $ip);
        if ($own && !empty($out['retry']) && !$this->db->checkSqlActive()) $out = $this->addMessageRow($uid, $name, $title, $body, $ip);
        return ['id' => $out['id'], 'error' => $out['error']];
    }

    # One attempt at storing a private message, which is the whole protocol from its transaction to its commit
    # Title and body are stored as the source their author wrote, so a reader renders what was written instead of what a writer had escaped; one contract reads every body and no column names a syntax
    # The send interval and both quotas are read behind the lock of both accounts, so what was true when the form was rendered decides nothing here
    # The name is resolved before the transaction opens on purpose: the first plain read of a transaction fixes the snapshot every later plain read answers from
    # With the lock as the first statement, the interval and the quotas behind it see the send that has just committed, so two of them cannot take one last place
    # The resolved id is not trusted either: the lock reports whether that account is still there, and a sender it no longer finds is gone
    private function addMessageRow(int $uid, string $name, string $title, string $body, string $ip): array {
        $title = trim($title);
        $body = trim($body);
        if ($uid < 1) return ['id' => 0, 'error' => 'not_logged'];
        if (trim($name) === '') return ['id' => 0, 'error' => 'no_recipient'];
        if ($title === '') return ['id' => 0, 'error' => 'no_title'];
        if ($body === '') return ['id' => 0, 'error' => 'no_body'];
        if ($this->getWordLength($body) > intval($this->conf['letter'] ?? 0)) return ['id' => 0, 'error' => 'word_long'];
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

    # Set one state column of a batch of received messages, after the batch has proved it belongs to the reader
    # The column is chosen from a fixed pair and never from a caller, because everything else in the statement is bound
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

    # Move a batch of received messages into the saved folder or back out of it, without touching what the reader has already read
    # The quota counts what the batch would really add, so re-saving a saved message costs nothing and a full folder refuses the whole batch
    # A batch locks only the rows it names, so two of them would both read a folder with room: the account is locked before the folder is counted, which is the row a send locks too
    # Taking that lock before the rows of the batch is what puts this write in the same order as a send, and only the branch that reads the quota needs it at all
    public function setMessageSaved(int $uid, array $ids, bool $keep): bool {
        $ids = $this->getIdList($ids);
        if ($uid < 1 || !$ids) return false;
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        if ($keep && $this->getUserLock($uid, $uid) === false) return $this->getFailed($own);
        if (!$this->checkOwner($uid, $ids, PrivatBox::Inbox)) return $this->getFailed($own);
        if ($keep) {
            [$keys, $pars] = $this->getIdBind($ids);
            $pars['uid'] = $uid;
            $add = $this->getTotal('id IN ('.$keys.') AND '.$this->getSideWhere(PrivatBox::Inbox).' AND saved = 0', $pars);
            if ($this->getMessageCount($uid, PrivatBox::Saved) + $add > intval($this->conf['messsav'] ?? 0)) return $this->getFailed($own);
        }
        if (!$this->setMessageFlag($uid, $ids, 'saved', $keep ? 1 : 0)) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }

    # Remove a batch of messages from one side of the mailbox and erase what both sides have now deleted
    # The counterpart keeps its own copy: only the flag of the side that asked is set, and the row itself goes only when neither side holds it any more
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

    # Take one account out of every mailbox it appears in, which is what a deleted user row leaves behind
    # Both of its own sides are marked deleted and what neither side holds any more is erased; the counterpart keeps a readable copy of a message from an account that is gone
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

    # Erase a batch of messages as an administrator, which is the one deletion that answers to no mailbox state
    public function deleteAdminMessage(array $ids): bool {
        $ids = $this->getIdList($ids);
        if (!$ids) return false;
        [$keys, $pars] = $this->getIdBind($ids);
        $own = !$this->db->checkSqlActive();
        if ($own && !$this->db->setSqlBegin()) return false;
        if ($this->db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_privat WHERE id IN ('.$keys.')', $pars) === false) return $this->getFailed($own);
        if ($own && !$this->db->setSqlCommit()) return $this->getFailed($own);
        return true;
    }
}
