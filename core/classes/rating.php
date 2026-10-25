<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Rating subsystem: the one writer of the vote history, of the last participation of every actor and, through its trusted adapter, of the aggregate every rated target shows
final class Rating {

    # The closed grammar of a scope, which is also the key of its rule: the three fixed subsystems and one public name of a registered Node type
    private const SCOPE = '/^(?:account|forum|node\.[a-z][a-z0-9]{0,19})$/D';

    # The scopes whose rule has to exist whatever Node types are registered
    private const FIXED = ['account', 'forum'];

    # The canonical decimal string the period is stored as, and the 32 lowercase hex characters of one delivery key
    private const NUMBER = '/^(?:0|[1-9][0-9]{0,18})$/D';
    private const REQUEST = '/^[a-f0-9]{32}$/D';

    # The seconds of one day, the top of the unsigned columns that hold an id, the longest reason and the widest page of the journal
    private const DAY = 86400;
    private const MAXID = 4294967295;
    private const MAXNOTE = 255;
    private const MAXLIST = 100;

    private Database $db;
    private array $rules;
    private array $blocked = [];
    private array $actor;
    private string $akey;
    private Closure $read;
    private Closure $write;

    # Build the subsystem; an exactly empty scope is closed on purpose, as getRatingService() passes it before the update mark, so nothing is blocked or logged
    public function __construct(Database $db, array $conf, array $actor, Closure $read, Closure $write) {
        $this->db = $db;
        $this->rules = $this->filterConfig($conf);
        $this->actor = $this->filterActor($actor);
        $this->akey = $this->getActorKey();
        $this->read = $read;
        $this->write = $write;
    }

    # Report one failure of the subsystem to the site log, with the context that lets an administrator find the scope, the target or the vote behind it
    private function addRatingLog(string $text, array $ctx = []): void {
        Logger::addSite('error', 'Rating: '.$text, $ctx);
    }

    # Convert one rule or answer []; the period is compared as a string against the largest whole day count an integer holds, so an overflow is refused before conversion
    private function filterRule(mixed $rule): array {
        if (!is_array($rule) || count($rule) !== 4) return [];
        foreach (['active', 'detail', 'guests'] as $key) {
            if (!in_array($rule[$key] ?? null, ['0', '1'], true)) return [];
        }
        $per = $rule['period'] ?? null;
        if (!is_string($per) || !preg_match(self::NUMBER, $per)) return [];
        $max = intdiv(PHP_INT_MAX, self::DAY) * self::DAY;
        if (strlen($per) > strlen($max) || (strlen($per) === strlen($max) && strcmp($per, $max) > 0)) return [];
        return ['active' => $rule['active'] === '1', 'period' => intval($per), 'detail' => $rule['detail'] === '1', 'guests' => $rule['guests'] === '1'];
    }

    # Check the ratings scope; a failing rule and a missing fixed scope are remembered as blocked, so a vote there answers blocked and never a missing target
    private function filterConfig(array $conf): array {
        $out = [];
        if ($conf === []) return $out;
        foreach ($conf as $name => $rule) {
            $scope = is_string($name) && preg_match(self::SCOPE, $name);
            $good = $scope ? $this->filterRule($rule) : [];
            if ($good) {
                $out[$name] = $good;
                continue;
            }
            if ($scope) $this->blocked[$name] = true;
            $this->addRatingLog('the rule of a scope is invalid and its rating is blocked', ['scope' => $name]);
        }
        foreach (array_diff(self::FIXED, array_keys($conf)) as $name) {
            $this->blocked[$name] = true;
            $this->addRatingLog('the rule of a scope is missing and its rating is blocked', ['scope' => $name]);
        }
        return $out;
    }

    # Check the trusted actor and answer it unchanged, or an empty array: exactly uid, ip, aid and super with their types, and the main administrator always carries a positive aid
    private function filterActor(array $actor): array {
        $good = count($actor) === 4 && is_int($actor['uid'] ?? null) && is_string($actor['ip'] ?? null) && is_int($actor['aid'] ?? null) && is_bool($actor['super'] ?? null);
        if ($good) $good = $actor['uid'] >= 0 && $actor['uid'] <= self::MAXID && $actor['aid'] >= 0 && $actor['aid'] <= self::MAXID && (!$actor['super'] || $actor['aid'] > 0);
        if ($good) return $actor;
        $this->addRatingLog('the actor is invalid and every operation is refused');
        return [];
    }

    # The stored identity of the actor: the account when there is one, else the normalized guest address; an unspecified address identifies no guest
    private function getActorKey(): string {
        if (!$this->actor) return '';
        if ($this->actor['uid']) return 'u:'.$this->actor['uid'];
        $norm = getIpNorm($this->actor['ip']);
        return ($norm === false || $norm === '0.0.0.0' || $norm === '::') ? '' : 'g:'.$norm;
    }

    # Report whether a scope and an id name a possible target: the closed grammar and a positive id inside its column, nothing of which is ever used as an SQL identifier
    private function checkTargetKey(string $scope, int $id): bool {
        return preg_match(self::SCOPE, $scope) && $id >= 1 && $id <= self::MAXID;
    }

    # Report whether a reason is plain text: valid UTF-8 within the column, not blank, without markup and without control characters
    private function checkReason(string $text): bool {
        if (!mb_check_encoding($text, 'UTF-8') || trim($text) === '' || mb_strlen($text) > self::MAXNOTE) return false;
        return $text === strip_tags($text) && !preg_match('/[\x00-\x1F\x7F]/', $text);
    }

    # The average as a decimal string by long division on integers, so no float takes part; public, because every display of an average uses this one rule
    public static function getAverage(int $score, int $num, int $digits = 6): ?string {
        if ($num < 1) return null;
        $digits = max(1, min(6, $digits));
        $scale = 10 ** $digits;
        $rest = $score % $num;
        $micro = intdiv($score, $num) * $scale;
        for ($unit = intdiv($scale, 10); $unit > 0; $unit = intdiv($unit, 10)) {
            $rest *= 10;
            $micro += intdiv($rest, $num) * $unit;
            $rest %= $num;
        }
        if ($rest * 2 >= $num) $micro++;
        return rtrim(rtrim(intdiv($micro, $scale).'.'.str_pad($micro % $scale, $digits, '0', STR_PAD_LEFT), '0'), '.');
    }

    # Build the closed result of getRating, addRating and deleteRating; the counters are filled only from a target the actor may see, so a refused target reveals nothing
    private function getResult(string $code, ?array $target = null, array $more = []): array {
        $out = ['ok' => $code === 'ok', 'code' => $code, 'vote' => 0, 'score' => 0, 'ratings' => 0, 'average' => null, 'wait' => 0, 'duplicate' => false, 'canvote' => false];
        if ($target) $out = array_replace($out, array_intersect_key($target, $out), ['average' => self::getAverage($target['score'], $target['ratings'])]);
        return array_replace($out, $more);
    }

    # Ask the trusted adapter for one target; an answer outside the contract is reported and never used, and with the lock it is the first lock of every write
    private function getTarget(string $scope, int $id, bool $lock): array|false|null {
        $row = ($this->read)($scope, $id, $this->actor, $lock);
        if ($row === null) return null;
        $good = is_array($row) && count($row) === 4 && is_int($row['owner'] ?? null) && is_int($row['score'] ?? null) && is_int($row['ratings'] ?? null);
        $good = $good && is_bool($row['enabled'] ?? null);
        if ($good && $row['owner'] >= 0 && $row['score'] >= 0 && $row['ratings'] >= 0) return $row;
        $this->addRatingLog('the target adapter answered outside its contract', ['scope' => $scope, 'mid' => $id]);
        return false;
    }

    # The current Unix second of the database server, the only clock the subsystem knows, or false when it could not be read
    private function getClock(): int|false {
        $res = $this->db->getSqlQuery('SELECT UNIX_TIMESTAMP() AS now');
        $row = $res === false ? false : $this->db->getSqlRow($res);
        return $row ? intval($row['now']) : false;
    }

    # Read the last participation of the actor with the server clock; its lock is always taken after the target row and before any vote
    private function getActorLast(string $scope, int $id, string $key, bool $lock): array|false {
        $sql = 'SELECT last, UNIX_TIMESTAMP() AS now FROM '.PREFIX_DB.'_rating_actors WHERE scope = :scope AND mid = :mid AND actor = :actor'.($lock ? ' FOR UPDATE' : '');
        $res = $this->db->getSqlQuery($sql, ['scope' => $scope, 'mid' => $id, 'actor' => $key]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? ['last' => intval($row['last']), 'now' => intval($row['now'])] : ['last' => null, 'now' => 0];
    }

    # Whether a new vote is open and the seconds left; measured as now minus last, which cannot overflow, and a last in the future is broken data, never an open vote
    private function getVoteState(array $rule, array $target, ?int $last, int $now): array {
        $sane = $last === null || $last <= $now;
        $wait = ($last !== null && $sane && $rule['period'] > 0 && $now - $last < $rule['period']) ? $rule['period'] - ($now - $last) : 0;
        $open = $rule['active'] && $target['enabled'] && $this->akey !== '' && ($this->actor['uid'] > 0 || $rule['guests']);
        $own = $this->actor['uid'] > 0 && $target['owner'] === $this->actor['uid'];
        return [$open && !$own && $sane && !$wait, $wait];
    }

    # Lock the row that marks a target as known to the subsystem: true when it exists, null when it does not, false when the statement failed
    private function getTargetRow(string $scope, int $id): ?bool {
        $res = $this->db->getSqlQuery('SELECT mid FROM '.PREFIX_DB.'_rating_targets WHERE scope = :scope AND mid = :mid FOR UPDATE', ['scope' => $scope, 'mid' => $id]);
        if ($res === false) return false;
        return $this->db->getSqlRow($res) ? true : null;
    }

    # Give a new target its row before the first vote; a non-zero aggregate without a row was never carried over by the update, so the vote is refused and reported
    private function addTargetRow(string $scope, int $id, array $target, int $now): bool {
        $seen = $this->getTargetRow($scope, $id);
        if ($seen !== null) return $seen;
        if ($target['score'] || $target['ratings']) {
            $this->addRatingLog('the target carries an aggregate that was never carried over and takes no vote', ['scope' => $scope, 'mid' => $id]);
            return false;
        }
        $sql = 'INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (:scope, :mid, 0, 0, FROM_UNIXTIME(:now))';
        return $this->db->getSqlQuery($sql, ['scope' => $scope, 'mid' => $id, 'now' => $now]) !== false;
    }

    # Run one vote under the fixed lock order owner, target, actor, request; last moves only with an accepted vote, so a refusal never extends the interval
    private function addVoteRow(string $scope, int $id, int $value, string $request, array $rule): array {
        $fail = [$this->getResult('storage'), false];
        $target = $this->getTarget($scope, $id, true);
        if (!$target) return $target === null ? [$this->getResult('unavailable'), false] : $fail;
        $now = $this->getClock();
        if ($now === false || !$this->addTargetRow($scope, $id, $target, $now)) return $fail;
        $seen = $this->getActorLast($scope, $id, $this->akey, true);
        if ($seen === false) return $fail;
        $pars = ['scope' => $scope, 'mid' => $id, 'actor' => $this->akey];
        $sql = 'SELECT id, value FROM '.PREFIX_DB.'_rating_votes WHERE scope = :scope AND mid = :mid AND actor = :actor AND request = :request FOR UPDATE';
        $res = $this->db->getSqlQuery($sql, $pars + ['request' => $request]);
        if ($res === false) return $fail;
        $old = $this->db->getSqlRow($res);
        [$can, $wait] = $this->getVoteState($rule, $target, $seen['last'], $now);
        $state = ['wait' => $wait, 'canvote' => $can];
        if ($old && intval($old['value']) === $value) return [$this->getResult('ok', $target, ['vote' => intval($old['id']), 'duplicate' => true] + $state), false];
        if ($old) return [$this->getResult('conflict', $target, $state), false];
        if (!$target['enabled'] || ($this->actor['uid'] > 0 && $target['owner'] === $this->actor['uid'])) return [$this->getResult('denied', $target, $state), false];
        if ($seen['last'] !== null && $seen['last'] > $now) {
            $this->addRatingLog('the last participation of an actor lies in the future and the vote is refused', $pars);
            return $fail;
        }
        if ($wait) return [$this->getResult('interval', $target, $state), false];
        $sql = 'INSERT INTO '.PREFIX_DB.'_rating_votes (scope, mid, actor, uid, value, request, created)'
            .' VALUES (:scope, :mid, :actor, :uid, :value, :request, FROM_UNIXTIME(:now))';
        if ($this->db->getSqlQuery($sql, $pars + ['uid' => $this->actor['uid'], 'value' => $value, 'request' => $request, 'now' => $now]) === false) return $fail;
        $vote = intval($this->db->getSqlLastId());
        $sql = $seen['last'] === null
            ? 'INSERT INTO '.PREFIX_DB.'_rating_actors (scope, mid, actor, last) VALUES (:scope, :mid, :actor, :now)'
            : 'UPDATE '.PREFIX_DB.'_rating_actors SET last = :now WHERE scope = :scope AND mid = :mid AND actor = :actor';
        if ($vote < 1 || $this->db->getSqlQuery($sql, $pars + ['now' => $now]) === false) return $fail;
        $next = ['score' => $target['score'] + $value, 'ratings' => $target['ratings'] + 1] + $target;
        if (!($this->write)($scope, $id, $next['score'], $next['ratings'])) return $fail;
        [$can, $wait] = $this->getVoteState($rule, $next, $now, $now);
        return [$this->getResult('ok', $next, ['vote' => $vote, 'wait' => $wait, 'canvote' => $can]), true];
    }

    # Annul one vote, read twice: unlocked to learn its target, then again under the locks of that target, so the fixed lock order holds
    private function deleteVoteRow(int $vote, string $reason, array $row): array {
        $fail = [$this->getResult('storage'), false];
        $scope = $row['scope'];
        $id = intval($row['mid']);
        $target = $this->getTarget($scope, $id, true);
        if (!$target) return $target === null ? [$this->getResult('unavailable'), false] : $fail;
        $now = $this->getClock();
        if ($now === false || !$this->getTargetRow($scope, $id) || $this->getActorLast($scope, $id, $row['actor'], true) === false) return $fail;
        $res = $this->db->getSqlQuery('SELECT scope, mid, value, annulled FROM '.PREFIX_DB.'_rating_votes WHERE id = :id FOR UPDATE', ['id' => $vote]);
        $cur = $res === false ? false : $this->db->getSqlRow($res);
        if (!$cur || $cur['scope'] !== $scope || intval($cur['mid']) !== $id) return $fail;
        $mine = $this->akey === '' ? ['last' => null, 'now' => 0] : $this->getActorLast($scope, $id, $this->akey, false);
        if ($mine === false) return $fail;
        $rule = $this->rules[$scope] ?? ['active' => false, 'period' => 0, 'detail' => false, 'guests' => false];
        if ($cur['annulled'] !== null) {
            [$can, $wait] = $this->getVoteState($rule, $target, $mine['last'], $now);
            return [$this->getResult('ok', $target, ['vote' => $vote, 'duplicate' => true, 'wait' => $wait, 'canvote' => $can]), false];
        }
        $next = ['score' => $target['score'] - intval($cur['value']), 'ratings' => $target['ratings'] - 1] + $target;
        if ($next['score'] < 0 || $next['ratings'] < 0) {
            $this->addRatingLog('the annulment would turn the aggregate negative and is refused', ['scope' => $scope, 'mid' => $id, 'vote' => $vote]);
            return $fail;
        }
        $sql = 'UPDATE '.PREFIX_DB.'_rating_votes SET annulled = FROM_UNIXTIME(:now), aid = :aid, reason = :reason WHERE id = :id';
        if ($this->db->getSqlQuery($sql, ['now' => $now, 'aid' => $this->actor['aid'], 'reason' => $reason, 'id' => $vote]) === false) return $fail;
        if (!($this->write)($scope, $id, $next['score'], $next['ratings'])) return $fail;
        [$can, $wait] = $this->getVoteState($rule, $next, $mine['last'], $now);
        return [$this->getResult('ok', $next, ['vote' => $vote, 'wait' => $wait, 'canvote' => $can]), true];
    }

    # Run one writing unit in a transaction of its own; a unit that wrote nothing, failed or threw is rolled back, and an unknown commit answers storage
    private function setUnitRun(Closure $unit, array $ctx): array {
        if ($this->db->checkSqlActive()) {
            $this->addRatingLog('a write was called inside a foreign open transaction and is refused', $ctx);
            return $this->getResult('storage');
        }
        try {
            [$out, $keep] = $this->db->setSqlBegin() ? $unit() : [$this->getResult('storage'), false];
        } catch (Throwable $err) {
            $this->addRatingLog('the write threw and is rolled back: '.$err->getMessage(), $ctx);
            [$out, $keep] = [$this->getResult('storage'), false];
        }
        if (!$keep) {
            if ($out['code'] === 'storage') $this->addRatingLog('the write was not stored', $ctx);
            $this->db->setSqlRollback();
            return $out;
        }
        if (!$this->db->setSqlCommit()) {
            $this->addRatingLog('the outcome of the commit is unknown', $ctx);
            return $this->getResult('storage');
        }
        return $out;
    }

    # Answer the checked rule of one scope - active, period, detail and guests - or an empty array for a scope without a usable rule, which shows no live widget
    public function getRule(string $scope): array {
        return $this->rules[$scope] ?? [];
    }

    # Read the state of one target for the actor without writing; only an unreachable target hides its counters, any other refusal answers ok with canvote false
    public function getRating(string $scope, int $id): array {
        if (!$this->actor) return $this->getResult('denied');
        if (!$this->checkTargetKey($scope, $id)) return $this->getResult('invalid');
        $rule = $this->rules[$scope] ?? null;
        if (!$rule) return $this->getResult(isset($this->blocked[$scope]) ? 'blocked' : 'unavailable');
        $target = $this->getTarget($scope, $id, false);
        if (!$target) return $this->getResult($target === null ? 'unavailable' : 'storage');
        $seen = $this->akey !== '' ? $this->getActorLast($scope, $id, $this->akey, false) : ['last' => null, 'now' => 0];
        if ($seen === false) return $this->getResult('storage');
        [$can, $wait] = $this->getVoteState($rule, $target, $seen['last'], $seen['now']);
        return $this->getResult('ok', $target, ['wait' => $wait, 'canvote' => $can]);
    }

    # Place one vote; the request is the key of one delivery, so a network repeat answers the stored vote and never a second one
    public function addRating(string $scope, int $id, int $value, string $request): array {
        if (!$this->actor) return $this->getResult('denied');
        if (!$this->checkTargetKey($scope, $id) || $value < 1 || $value > 5 || !preg_match(self::REQUEST, $request)) return $this->getResult('invalid');
        $rule = $this->rules[$scope] ?? null;
        if (!$rule) return $this->getResult(isset($this->blocked[$scope]) ? 'blocked' : 'unavailable');
        if (!$rule['active'] || $this->akey === '' || (!$this->actor['uid'] && !$rule['guests'])) return $this->getResult('denied');
        return $this->setUnitRun(fn(): array => $this->addVoteRow($scope, $id, $value, $request, $rule), ['scope' => $scope, 'mid' => $id, 'actor' => $this->akey]);
    }

    # Annul one vote as the main administrator with a reason; a repeat is an empty success and the history stays whatever happens to the target
    public function deleteRating(int $vote, string $reason): array {
        if (!$this->actor || !$this->actor['super']) return $this->getResult('denied');
        if ($vote < 1 || !$this->checkReason($reason)) return $this->getResult('invalid');
        $res = $this->db->getSqlQuery('SELECT scope, mid, actor FROM '.PREFIX_DB.'_rating_votes WHERE id = :id', ['id' => $vote]);
        if ($res === false) return $this->getResult('storage');
        $row = $this->db->getSqlRow($res);
        if (!$row) return $this->getResult('unavailable');
        return $this->setUnitRun(fn(): array => $this->deleteVoteRow($vote, $reason, $row), ['vote' => $vote, 'aid' => $this->actor['aid']]);
    }

    # Page the stored votes newest first for the main administrator, a page of the panel up to LIST_MAX; a page past the last answers the last one
    public function getRatingList(string $scope = '', int $id = 0, int $page = 1, int $limit = 50): array {
        $out = ['ok' => false, 'code' => 'denied', 'rows' => [], 'total' => 0, 'page' => 1];
        if (!$this->actor || !$this->actor['super']) return $out;
        $good = $page >= 1 && $limit >= 1 && $limit <= (defined('ADMIN_FILE') ? LIST_MAX : self::MAXLIST) && $id >= 0 && $id <= self::MAXID
            && ($scope === '' ? $id === 0 : preg_match(self::SCOPE, $scope));
        if (!$good) return array_replace($out, ['code' => 'invalid']);
        $pars = [];
        if ($scope !== '') $pars['scope'] = $scope;
        if ($id) $pars['mid'] = $id;
        $where = $pars ? ' WHERE '.implode(' AND ', array_map(static fn(string $key): string => $key.' = :'.$key, array_keys($pars))) : '';
        $num = $this->db->getSqlQuery('SELECT COUNT(id) FROM '.PREFIX_DB.'_rating_votes'.$where, $pars);
        if ($num === false) return array_replace($out, ['code' => 'storage']);
        $total = intval($num->fetchColumn());
        $page = min($page, max(1, (int)ceil($total / $limit)));
        $sql = 'SELECT id, scope, mid, actor, uid, value, request, created, annulled, aid, reason FROM '.PREFIX_DB.'_rating_votes'.$where;
        $res = $this->db->getSqlQuery($sql.' ORDER BY id DESC LIMIT '.(($page - 1) * $limit).', '.$limit, $pars);
        if ($res === false) return array_replace($out, ['code' => 'storage']);
        foreach ($this->db->getSqlRows($res) ?: [] as $row) {
            $one = ['id' => 0, 'scope' => '', 'mid' => 0, 'actor' => '', 'uid' => 0, 'value' => 0, 'request' => '', 'created' => '', 'annulled' => null, 'aid' => 0,
                'reason' => ''];
            foreach ($one as $key => $void) $one[$key] = is_int($void) ? intval($row[$key]) : $row[$key];
            $out['rows'][] = $one;
        }
        return array_replace($out, ['ok' => true, 'code' => 'ok', 'total' => $total, 'page' => $page]);
    }
}
