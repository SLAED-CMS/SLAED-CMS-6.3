<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# User points subsystem: the one writer of the points journal and of the fast balance every profile reads, for every owner of a rewarded event
# The owner of an event names an approved action, a scope, a server-made unique source and the recipient; the amount of an ordinary action comes from the configuration alone
# A non-zero event locks the account row, then writes the journal row and the balance in one unit, so the starting balance plus the journal always adds up to the aggregate
# Inside a transaction of its owner the unit is a savepoint: a refusal it can prove rolled back answers false, and anything it cannot prove throws and stops the owner
# The class holds no HTTP, template, rating or node dependency, and reads the clock of the database only, because the journal is stamped by that server
final class Point {

    # The closed vocabulary of rewarded actions; the configuration has to carry exactly these and the journal stores nothing else
    private const ACTIONS = [
        'publish', 'comment', 'view', 'download', 'visit', 'poll', 'order', 'favorite',
        'message', 'recommend', 'register', 'login', 'report', 'moderate', 'adjust',
    ];

    # The only keys the optional data of one event may carry
    private const DATAKEYS = ['mid', 'aid', 'rid', 'note', 'points'];

    # The grammar of a scope and of a source, both plain ASCII values of the journal and never an SQL identifier
    private const SCOPE = '/^[a-z][a-z0-9.:-]{0,49}$/D';
    private const SOURCE = '/^[A-Za-z0-9][A-Za-z0-9:._-]{0,63}$/D';

    # The canonical decimal string every configured number is stored as: a lone zero, or digits without a sign and without a leading zero
    private const NUMBER = '/^(?:0|[1-9][0-9]{0,8})$/D';

    # The upper bounds of one configured reward, of its period in seconds and of its limit of events per period
    private const MAXSUM = 1000;
    private const MAXPERIOD = 31536000;
    private const MINPERIOD = 60;
    private const MAXLIMIT = 10000;

    # The widest manual correction in either direction, the longest note, and the top of the unsigned columns that hold an account id and a balance
    private const MAXADJUST = 1000000;
    private const MAXNOTE = 255;
    private const MAXID = 4294967295;

    # The fixed trusted name of the savepoint that isolates the journal and the balance inside a transaction of the owner
    private const SAVE = 'slpoint';

    # The server codes that take the whole transaction with them and so can never be answered as a local refusal: a deadlock, and a record that changed since the snapshot was taken
    private const LOST = [1213, 1020];

    # The server code of a violated unique key, which under the account lock can only mean that the snapshot of the owner hid a row the journal already carries
    private const TWIN = 1062;

    private Database $db;
    private bool $active;
    private array $rules;

    # Whether the configuration passed its check; an owner reads it to tell a subsystem closed on purpose or broken apart from a failed write
    public private(set) bool $valid;

    # Build the subsystem over the connection of the request and the points scope of the configuration, which is checked whole and exactly once
    # A scope that fails the check switches the class off and is reported to the site log; no partly usable rule is ever applied
    # An exactly empty scope is a subsystem closed on purpose, which is how the core builds it before the data update left its mark: switched off the same way, without a log line
    public function __construct(Database $db, array $conf) {
        $this->db = $db;
        $this->rules = $this->filterConfig($conf);
        $this->valid = $this->rules !== [];
        $this->active = $this->valid && $conf['active'] === '1';
        if (!$this->valid && $conf !== []) Logger::addSite('error', 'Point: the points configuration is invalid and the subsystem is switched off');
    }

    # Turn one canonical decimal string into its number, or answer false for anything else: a native number, a sign, a space, a leading zero or a value above the bound
    private function filterNumber(mixed $val, int $max): int|false {
        return is_string($val) && preg_match(self::NUMBER, $val) && intval($val) <= $max ? intval($val) : false;
    }

    # Check the whole points scope and answer the fifteen rules as numbers, or an empty array when any key, any value or any pair of period and limit is wrong
    private function filterConfig(array $conf): array {
        if (count($conf) !== 2 || !isset($conf['active'], $conf['actions']) || !in_array($conf['active'], ['0', '1'], true)) return [];
        if (!is_array($conf['actions']) || count($conf['actions']) !== count(self::ACTIONS)) return [];
        $out = [];
        foreach (self::ACTIONS as $name) {
            $rule = $conf['actions'][$name] ?? null;
            if (!is_array($rule) || count($rule) !== 3) return [];
            $sum = $this->filterNumber($rule['points'] ?? null, self::MAXSUM);
            $per = $this->filterNumber($rule['period'] ?? null, self::MAXPERIOD);
            $lim = $this->filterNumber($rule['limit'] ?? null, self::MAXLIMIT);
            if ($sum === false || $per === false || $lim === false) return [];
            if (($per === 0) !== ($lim === 0) || ($per > 0 && $per < self::MINPERIOD)) return [];
            if ($name === 'adjust' && ($sum || $per || $lim)) return [];
            $out[$name] = ['points' => $sum, 'period' => $per, 'limit' => $lim];
        }
        return $out;
    }

    # Report whether a note is plain text: valid UTF-8 within the column, without markup and without control characters; a form checks its note here before it writes
    public function checkNote(string $note): bool {
        return mb_check_encoding($note, 'UTF-8') && mb_strlen($note) <= self::MAXNOTE && $note === strip_tags($note) && !preg_match('/[\x00-\x1F\x7F]/', $note);
    }

    # Check the key and the data of one event and answer them as one flat event, or false for an unknown action, a wrong recipient, a bad scope or source, or a foreign data key
    # An explicit amount belongs to adjust alone, with an administrator and a reason; a compensation names its origin and the source reverse:<rid>, a prefix no other event may use
    private function filterEvent(string $action, string $scope, string $source, int $uid, array $data): array|false {
        if (!in_array($action, self::ACTIONS, true) || !preg_match(self::SCOPE, $scope) || !preg_match(self::SOURCE, $source)) return false;
        if ($uid < 1 || $uid > self::MAXID || array_diff(array_keys($data), self::DATAKEYS)) return false;
        $out = ['action' => $action, 'scope' => $scope, 'source' => $source, 'uid' => $uid, 'mid' => 0, 'aid' => 0, 'rid' => 0, 'note' => '', 'points' => 0];
        foreach (['mid', 'aid', 'rid'] as $key) {
            $val = $data[$key] ?? 0;
            if (!is_int($val) || $val < 0 || ($key !== 'rid' && $val > self::MAXID)) return false;
            $out[$key] = $val;
        }
        $note = $data['note'] ?? '';
        if (!is_string($note) || !$this->checkNote($note)) return false;
        $out['note'] = $note;
        if (str_starts_with($source, 'reverse:') !== ($out['rid'] > 0) || ($out['rid'] && $source !== 'reverse:'.$out['rid'])) return false;
        if ($action !== 'adjust') return array_key_exists('points', $data) ? false : $out;
        $sum = $data['points'] ?? 0;
        if (!is_int($sum) || !$sum || abs($sum) > self::MAXADJUST || !$out['aid'] || $out['rid'] || trim($note) === '') return false;
        $out['points'] = $sum;
        return $out;
    }

    # Report a failure of the points unit to the site log with the key of the event, so the owner of the action can find what was not rewarded
    private function addEventLog(string $text, array $event, int $errno = 0): void {
        $ctx = ['action' => $event['action'], 'scope' => $event['scope'], 'source' => $event['source'], 'uid' => $event['uid'], 'errno' => $errno];
        Logger::addSite('error', 'Point: '.$text, $ctx);
    }

    # Lock the account row and answer its balance, null when the account does not exist, or false when the statement failed
    # The account is always the first lock of the unit, which is what serializes every event of one recipient and keeps the lock order fixed
    private function getUserLock(int $uid): int|false|null {
        $res = $this->db->getSqlQuery('SELECT points FROM '.PREFIX_DB.'_users WHERE id = :uid FOR UPDATE', ['uid' => $uid]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? intval($row['points']) : null;
    }

    # Answer the id of the journal row that carries the key of an event, 0 when there is none, or false when the statement failed
    # Asked for an origin, it answers only a positive row that is no compensation and locks it with a current read for the rest of the transaction
    private function getEventRow(array $event, bool $origin = false): int|false {
        $sql = 'SELECT id FROM '.PREFIX_DB.'_points WHERE uid = :uid AND action = :action AND scope = :scope AND source = :source';
        if ($origin) $sql .= ' AND rid IS NULL AND points > 0 FOR UPDATE';
        $res = $this->db->getSqlQuery($sql, ['uid' => $event['uid'], 'action' => $event['action'], 'scope' => $event['scope'], 'source' => $event['source']]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? intval($row['id']) : 0;
    }

    # Count the rewarded origin events of one action the recipient collected inside the sliding period, by the clock of the server that stamped them
    private function getEventCount(array $event, int $per): int|false {
        $sql = 'SELECT COUNT(id) AS num FROM '.PREFIX_DB.'_points'
            .' WHERE uid = :uid AND action = :action AND rid IS NULL AND points > 0 AND created > NOW() - INTERVAL :per SECOND';
        $res = $this->db->getSqlQuery($sql, ['uid' => $event['uid'], 'action' => $event['action'], 'per' => $per]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? intval($row['num']) : false;
    }

    # Write the journal row with the amount that was really applied and move the balance by the same amount, as the two halves of one unit
    # A zero amount writes nothing, except for a compensation, whose row has to exist so the same origin can never be reversed again
    private function addEventSum(array $event, int $sum, int $bal): ?bool {
        if (!$sum && !$event['rid']) return true;
        if ($bal + $sum > self::MAXID) {
            $this->addEventLog('the event would overflow the balance and was refused', $event);
            return false;
        }
        $sql = 'INSERT INTO '.PREFIX_DB.'_points (uid, aid, action, scope, mid, source, points, rid, note)'
            .' VALUES (:uid, :aid, :action, :scope, :mid, :source, :points, :rid, :note)';
        $pars = [
            'uid' => $event['uid'], 'aid' => $event['aid'], 'action' => $event['action'], 'scope' => $event['scope'], 'mid' => $event['mid'],
            'source' => $event['source'], 'points' => $sum, 'rid' => $event['rid'] ?: null, 'note' => $event['note'],
        ];
        if ($this->db->getSqlQuery($sql, $pars) === false) return null;
        if (!$sum) return true;
        $done = $this->db->getSqlQuery('UPDATE '.PREFIX_DB.'_users SET points = :points WHERE id = :uid', ['points' => $bal + $sum, 'uid' => $event['uid']]);
        return $done === false ? null : true;
    }

    # Lock the origin of a compensation, check that it is a positive award of the same recipient, action and scope, and take back what the balance still holds of it
    private function addReverseRow(array $event, int $bal): ?bool {
        $res = $this->db->getSqlQuery('SELECT uid, action, scope, points, rid FROM '.PREFIX_DB.'_points WHERE id = :id FOR UPDATE', ['id' => $event['rid']]);
        if ($res === false) return null;
        $row = $this->db->getSqlRow($res);
        if (!$row) return false;
        $same = intval($row['uid']) === $event['uid'] && $row['action'] === $event['action'] && $row['scope'] === $event['scope'];
        if (!$same || $row['rid'] !== null || intval($row['points']) < 1) return false;
        return $this->addEventSum($event, -min(intval($row['points']), $bal), $bal);
    }

    # Run one event under the account lock and answer true for a stored or an empty success, false for a refusal, and null for a statement that failed
    # A key the journal already carries and a reached limit are both a success that writes nothing; a negative correction takes no more than the balance holds
    private function addEventRow(array $event, array $rule): ?bool {
        $bal = $this->getUserLock($event['uid']);
        if (!is_int($bal)) return $bal === false ? null : false;
        $old = $this->getEventRow($event);
        if ($old === false) return null;
        if ($old) return true;
        if ($event['rid']) return $this->addReverseRow($event, $bal);
        if ($event['action'] === 'adjust') return $this->addEventSum($event, max($event['points'], -$bal), $bal);
        if (!$rule['limit']) return $this->addEventSum($event, $rule['points'], $bal);
        $num = $this->getEventCount($event, $rule['period']);
        if ($num === false) return null;
        return $num < $rule['limit'] ? $this->addEventSum($event, $rule['points'], $bal) : true;
    }

    # Report whether the transaction of the owner still stands, asked with a round trip because only an answer of the server refreshes what the connection knows about it
    private function checkOwnerAlive(): bool {
        return $this->db->getSqlQuery('DO 1') !== false && $this->db->checkSqlActive();
    }

    # Open the unit: a transaction of its own, or a savepoint inside the transaction of the owner, whose failure leaves that transaction unknown and therefore throws
    private function setEventBegin(bool $own): bool {
        if ($own) return $this->db->setSqlBegin();
        if ($this->db->getSqlQuery('SAVEPOINT '.self::SAVE) === false) throw new RuntimeException('Point: the savepoint was refused and the outer transaction is unknown');
        return true;
    }

    # Close the unit: keep it by commit or release, or take it back by rollback, and answer false only when that rollback is proven and the outer transaction still stands
    # A violated unique key is a repeat the snapshot of the owner could not see: the unit is taken back the same proven way and the repeat answers the empty success it is
    # The error of a failed statement is read before anything else runs, because the connection keeps its last error until another one replaces it
    private function setEventEnd(bool $own, ?bool $done, array $event): bool {
        if ($done) {
            $kept = $own ? $this->db->setSqlCommit() : $this->db->getSqlQuery('RELEASE SAVEPOINT '.self::SAVE) !== false;
            if (!$kept) throw new RuntimeException('Point: the outcome of the commit is unknown');
            return true;
        }
        $errno = $done === null ? intval($this->db->getSqlError()['code']) : 0;
        $twin = $errno === self::TWIN;
        if ($done === null && !$twin) $this->addEventLog('the event was not stored', $event, $errno);
        $lost = in_array($errno, self::LOST, true);
        $back = $own ? $this->db->setSqlRollback() : !$lost && $this->db->getSqlQuery('ROLLBACK TO SAVEPOINT '.self::SAVE) !== false && $this->db->checkSqlActive();
        if ($lost || !$back) throw new RuntimeException('Point: the rollback is not proven and the transaction is lost');
        return $twin;
    }

    # Register one event of an approved action for its recipient and answer whether it succeeded; a repeat, a reached limit and a zero or disabled reward all succeed empty
    # An ordinary reward that is zero or switched off costs no statement at all, while a compensation and a correction work on the existing balance whatever the settings say
    public function addEvent(string $action, string $scope, string $source, int $uid, array $data = []): bool {
        if (!$this->valid) return false;
        $event = $this->filterEvent($action, $scope, $source, $uid, $data);
        if ($event === false) return false;
        $rule = $this->rules[$action];
        if ($action !== 'adjust' && !$event['rid'] && (!$this->active || !$rule['points'])) return true;
        $own = !$this->db->checkSqlActive();
        if (!$this->setEventBegin($own)) {
            $this->addEventLog('the transaction of the event could not be opened', $event);
            return false;
        }
        return $this->setEventEnd($own, $this->addEventRow($event, $rule), $event);
    }

    # Lock the account rows of every recipient one operation will move, by ascending id inside the open transaction of its owner, before the first event of that operation
    # An event locks its own recipient first; an owner reaching several recipients calls this beforehand, so two operations over the same accounts never lock them crosswise
    # A set of one account costs no statement, because the event of that account takes the same lock as its first step
    public function setUserLocks(array $uids): bool {
        $ids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn($v) => $v > 0 && $v <= self::MAXID)));
        if (count($ids) < 2) return true;
        if (!$this->db->checkSqlActive()) return false;
        sort($ids);
        $keys = [];
        $pars = [];
        foreach ($ids as $i => $id) {
            $keys[] = ':u'.$i;
            $pars['u'.$i] = $id;
        }
        return $this->db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_users WHERE id IN ('.implode(', ', $keys).') ORDER BY id FOR UPDATE', $pars) !== false;
    }

    # Answer the id of the positive origin award behind a trusted event key, 0 when there is confirmed none, or false for a wrong key, a missing transaction or a failed read
    # It needs the open transaction of the owner, locks the account first and the origin row second, holds both until that transaction ends, and works with rewards switched off
    public function getEventId(string $action, string $scope, string $source, int $uid): int|false {
        $event = $this->valid && $action !== 'adjust' ? $this->filterEvent($action, $scope, $source, $uid, []) : false;
        if ($event === false || !$this->db->checkSqlActive()) return false;
        $bal = $this->getUserLock($uid);
        $id = is_int($bal) ? $this->getEventRow($event, true) : ($bal === null ? 0 : false);
        if ($id !== false) return $id;
        $errno = intval($this->db->getSqlError()['code']);
        $this->addEventLog('the origin of the event could not be read', $event, $errno);
        if (in_array($errno, self::LOST, true) || !$this->checkOwnerAlive()) throw new RuntimeException('Point: the transaction of the owner is lost');
        return false;
    }
}
