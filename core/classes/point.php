<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# User points subsystem: the one writer of the points journal and of the fast balance every profile reads, for every owner of a rewarded event
final class Point {

    # The closed vocabulary of rewarded actions; the configuration has to carry exactly these and the journal stores nothing else
    private const ACTIONS = [
        'publish', 'comment', 'view', 'download', 'visit', 'poll', 'favorite',
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
    private array $rules;

    # Whether the configuration passed its check; an owner reads it to tell a subsystem closed on purpose or broken apart from a failed write
    public private(set) bool $valid;

    # Whether rewards are given: a checked configuration that is switched on; the interface of points follows it, never the stored switch alone
    public private(set) bool $active;

    # Build over the points scope, checked whole once; an exactly empty scope, which getConfig() passes before the update mark, switches it off without a log line
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

    # Check the whole points scope and answer the fourteen rules as numbers, or an empty array when any key, any value or any pair of period and limit is wrong
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

    # Check one event into a flat event or false; an explicit amount belongs to adjust alone, and the source prefix reverse: to a compensation alone
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

    # Lock the account row and answer its balance; the account is always the first lock of the unit, which serializes every event of one recipient
    private function getUserLock(int $uid): int|false|null {
        $res = $this->db->getSqlQuery('SELECT points FROM '.PREFIX_DB.'_users WHERE id = :uid FOR UPDATE', ['uid' => $uid]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? intval($row['points']) : null;
    }

    # The id of the journal row carrying the key of an event, 0 for none; asked for an origin, it locks a positive non-compensation row with a current read
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

    # Write the journal row with the amount really applied and move the balance by it; a zero compensation still writes its row, so an origin is never reversed twice
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

    # Run one event under the account lock; a known key and a reached limit succeed empty, and a negative correction takes no more than the balance holds
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

    # Open the unit as its own transaction or a savepoint in that of the owner; a failed savepoint leaves the owner transaction unknown and therefore throws
    private function setEventBegin(bool $own): bool {
        if ($own) return $this->db->setSqlBegin();
        if ($this->db->getSqlQuery('SAVEPOINT '.self::SAVE) === false) throw new RuntimeException('Point: the savepoint was refused and the outer transaction is unknown');
        return true;
    }

    # Close the unit; the error of a failed statement is read first, because the connection keeps its last error until another one replaces it
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

    # Register one event of an approved action; the amount of an ordinary action comes from the configuration alone, and a zero or disabled reward costs no statement
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

    # Lock every recipient of one operation by ascending id before its first event, so two operations over the same accounts never lock them crosswise
    public function setUserLocks(array $uids): bool {
        $ids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn(int $v): bool => $v > 0 && $v <= self::MAXID)));
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

    # The id of the positive origin award behind a trusted event key; it needs the open transaction of the owner, locks account then origin, and works with rewards off
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
