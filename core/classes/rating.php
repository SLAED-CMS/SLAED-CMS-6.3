<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Rating subsystem: the one writer of the vote history, of the last participation of every actor and, through its trusted adapter, of the aggregate every rated target shows
# A vote is a value from 1 to 5 of one server-made actor, an account or a guest address; nobody changes or removes a vote, only the main administrator annuls one with a reason
# The class owns its transaction and the write guard of the page cache around it, takes its locks in one fixed order, and reads the clock of the database only
# The target is reached through two trusted callbacks given once by the bootstrap, so no scope ever becomes a table or a path and the class knows neither nodes nor points
final class Rating {

    # The closed grammar of a scope, which is also the key of its rule: the three fixed subsystems and one public name of a registered Node type
    private const SCOPE = '/^(?:account|forum|shop|node\.[a-z][a-z0-9]{0,19})$/D';

    # The scopes whose rule has to exist whatever Node types are registered
    private const FIXED = ['account', 'forum', 'shop'];

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
    private array $actor;
    private string $akey;
    private Closure $read;
    private Closure $write;

    # Build the subsystem over the connection of the request, the ratings scope of the configuration, the trusted actor of the session and the two trusted target callbacks
    # Every rule is checked and converted exactly once; a wrong or missing rule blocks the rating of its own scope and is reported, and a wrong actor blocks every operation
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

    # Check one rule and answer it converted, or an empty array: exactly four keys, three switches stored as the strings 0 or 1, and a canonical period of seconds
    # The period is compared as a string against the largest whole number of days an integer holds, so a value that would overflow is refused before it is ever converted
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

    # Check the whole ratings scope and answer the usable rules by scope; a rule that fails, a key outside the grammar and a missing fixed scope are each reported once here
    private function filterConfig(array $conf): array {
        $out = [];
        foreach ($conf as $name => $rule) {
            $good = is_string($name) && preg_match(self::SCOPE, $name) ? $this->filterRule($rule) : [];
            if ($good) $out[$name] = $good;
            else $this->addRatingLog('the rule of a scope is invalid and its rating is blocked', ['scope' => $name]);
        }
        foreach (array_diff(self::FIXED, array_keys($conf)) as $name) $this->addRatingLog('the rule of a scope is missing and its rating is blocked', ['scope' => $name]);
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

    # Build the stored identity of the actor: the account when there is one, otherwise the normalized address of the guest, otherwise nothing
    # An account is the same actor from every address and device, and a missing or unspecified address identifies no guest at all
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

    # The average of an aggregate as a decimal string with up to six fraction digits, rounded half up and without trailing zeros, or null while nobody voted
    # It is derived by long division on integers, so no float takes part; the whole sum and the whole count stay the only source of truth
    private function getAverage(int $score, int $num): ?string {
        if ($num < 1) return null;
        $rest = $score % $num;
        $micro = intdiv($score, $num) * 1000000;
        for ($i = 0, $unit = 100000; $i < 6; $i++, $unit = intdiv($unit, 10)) {
            $rest *= 10;
            $micro += intdiv($rest, $num) * $unit;
            $rest %= $num;
        }
        if ($rest * 2 >= $num) $micro++;
        return rtrim(rtrim(intdiv($micro, 1000000).'.'.str_pad($micro % 1000000, 6, '0', STR_PAD_LEFT), '0'), '.');
    }

    # Build the closed result of getRating, addRating and deleteRating; the counters are filled only from a target the actor may see, so a refused target reveals nothing
    private function getResult(string $code, ?array $target = null, array $more = []): array {
        $out = ['ok' => $code === 'ok', 'code' => $code, 'vote' => 0, 'score' => 0, 'ratings' => 0, 'average' => null, 'wait' => 0, 'duplicate' => false, 'canvote' => false];
        if ($target) $out = array_replace($out, array_intersect_key($target, $out), ['average' => $this->getAverage($target['score'], $target['ratings'])]);
        return array_replace($out, $more);
    }

    # Ask the trusted adapter for one target: null is a target the actor may not reach, false is an answer outside the contract, which is reported and never used
    # With the lock the adapter repeats the current rights and locks the row of the owner, which is the first lock of every write and what serializes one target
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

    # Read the last participation of one actor in one target together with the clock of the server: last is null while the actor never took part, and false means a failed read
    # With the lock the row is held for the rest of the transaction; it is always taken after the row of the target and before any vote
    private function getActorLast(string $scope, int $id, string $key, bool $lock): array|false {
        $sql = 'SELECT last, UNIX_TIMESTAMP() AS now FROM '.PREFIX_DB.'_rating_actors WHERE scope = :scope AND mid = :mid AND actor = :actor'.($lock ? ' FOR UPDATE' : '');
        $res = $this->db->getSqlQuery($sql, ['scope' => $scope, 'mid' => $id, 'actor' => $key]);
        if ($res === false) return false;
        $row = $this->db->getSqlRow($res);
        return $row ? ['last' => intval($row['last']), 'now' => intval($row['now'])] : ['last' => null, 'now' => 0];
    }

    # Answer whether the actor could place a new vote in the given state, and the seconds its interval still runs: the wait speaks of the interval alone, never of another refusal
    # The interval is measured as now minus last against the period, which cannot overflow; a last that lies in the future is broken data and never an open vote
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

    # Make sure the target is known before its first vote: a new empty target gets a row with a zero starting balance
    # An aggregate that is not zero without such a row was never carried over by the update, so it is no new target and the vote is refused and reported
    private function addTargetRow(string $scope, int $id, array $target, int $now): bool {
        $seen = $this->getTargetRow($scope, $id);
        if ($seen !== null) return $seen;
        if ($target['score'] || $target['ratings']) {
            $this->addRatingLog('the target carries an aggregate that was never carried over and takes no vote', ['scope' => $scope, 'mid' => $id]);
            return false;
        }
        $sql = 'INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (:scope, :mid, 0, 0, :now)';
        return $this->db->getSqlQuery($sql, ['scope' => $scope, 'mid' => $id, 'now' => $now]) !== false;
    }

    # Run one vote under the locks of the fixed order - owner, target, actor, request - and answer its result with the word whether anything was written
    # A delivery key the actor already used answers the stored vote again, or a conflict when it carries another value; only a new key meets the own-vote rule and the interval
    # The interval never extends on a refusal, because last moves only together with an accepted vote, and the aggregate is checked before it is handed to the adapter
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
        $sql = 'INSERT INTO '.PREFIX_DB.'_rating_votes (scope, mid, actor, uid, value, request, created) VALUES (:scope, :mid, :actor, :uid, :value, :request, :now)';
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

    # Annul one vote under the same order of locks, which is why the vote is read twice: first without a lock to learn its target, then again under the locks of that target
    # Exactly the stored value leaves the aggregate, the administrator, the reason and the moment are recorded, and the last participation of the voter stays as it was
    # A vote that is already annulled is an empty success, and an aggregate that would turn negative is broken data that is reported and left alone
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
        if (intval($cur['annulled']) > 0) {
            [$can, $wait] = $this->getVoteState($rule, $target, $mine['last'], $now);
            return [$this->getResult('ok', $target, ['vote' => $vote, 'duplicate' => true, 'wait' => $wait, 'canvote' => $can]), false];
        }
        $next = ['score' => $target['score'] - intval($cur['value']), 'ratings' => $target['ratings'] - 1] + $target;
        if ($next['score'] < 0 || $next['ratings'] < 0) {
            $this->addRatingLog('the annulment would turn the aggregate negative and is refused', ['scope' => $scope, 'mid' => $id, 'vote' => $vote]);
            return $fail;
        }
        $sql = 'UPDATE '.PREFIX_DB.'_rating_votes SET annulled = :now, aid = :aid, reason = :reason WHERE id = :id';
        if ($this->db->getSqlQuery($sql, ['now' => $now, 'aid' => $this->actor['aid'], 'reason' => $reason, 'id' => $vote]) === false) return $fail;
        if (!($this->write)($scope, $id, $next['score'], $next['ratings'])) return $fail;
        [$can, $wait] = $this->getVoteState($rule, $next, $mine['last'], $now);
        return [$this->getResult('ok', $next, ['vote' => $vote, 'wait' => $wait, 'canvote' => $can]), true];
    }

    # Run one writing unit inside the transaction of the subsystem and the write guard of the page cache: guard, BEGIN, the unit, COMMIT, the forced bump, and then the guard goes
    # A unit that wrote nothing, failed or threw is rolled back, and the guard goes with a rollback that is proven, which includes a transaction the server already dropped
    # An unknown commit answers storage and keeps the guard; a bump that fails after a proven commit is reported and keeps the guard too, while the stored vote answers as stored
    private function setUnitRun(Closure $unit, array $ctx): array {
        if ($this->db->checkSqlActive()) {
            $this->addRatingLog('a write was called inside a foreign open transaction and is refused', $ctx);
            return $this->getResult('storage');
        }
        $guard = Cache::getWriteGuard();
        if ($guard === false) {
            $this->addRatingLog('the write guard of the page cache could not be opened and the write is refused', $ctx);
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
            if ($this->db->setSqlRollback() || !$this->db->checkSqlActive()) Cache::deleteWriteGuard($guard);
            return $out;
        }
        if (!$this->db->setSqlCommit()) {
            $this->addRatingLog('the outcome of the commit is unknown and the write guard is kept', $ctx);
            return $this->getResult('storage');
        }
        $done = Cache::addEpoch(true) && Cache::deleteWriteGuard($guard);
        if (!$done) $this->addRatingLog('the write is stored but the page cache was not invalidated, the write guard is kept', $ctx);
        return $out;
    }

    # Read the state of one target for the actor without writing anything: the aggregate of every reachable target, the rest of its interval and whether a new vote would be taken
    # A target whose rating is switched off, closed to guests, owned by the actor or still waiting answers ok with canvote false; only an unreachable target hides its counters
    public function getRating(string $scope, int $id): array {
        if (!$this->actor) return $this->getResult('denied');
        if (!$this->checkTargetKey($scope, $id)) return $this->getResult('invalid');
        $rule = $this->rules[$scope] ?? null;
        $target = $rule ? $this->getTarget($scope, $id, false) : null;
        if (!$target) return $this->getResult($target === null ? 'unavailable' : 'storage');
        $seen = $this->akey !== '' ? $this->getActorLast($scope, $id, $this->akey, false) : ['last' => null, 'now' => 0];
        if ($seen === false) return $this->getResult('storage');
        [$can, $wait] = $this->getVoteState($rule, $target, $seen['last'], $seen['now']);
        return $this->getResult('ok', $target, ['wait' => $wait, 'canvote' => $can]);
    }

    # Place one vote of the actor on one target; the request is the key of one delivery, so a network repeat answers the stored vote and never a second one
    # Everything that can be refused without the database is refused first: the form, a blocked scope, a rating that is switched off, a guest who may not vote or has no address
    public function addRating(string $scope, int $id, int $value, string $request): array {
        if (!$this->actor) return $this->getResult('denied');
        if (!$this->checkTargetKey($scope, $id) || $value < 1 || $value > 5 || !preg_match(self::REQUEST, $request)) return $this->getResult('invalid');
        $rule = $this->rules[$scope] ?? null;
        if (!$rule) return $this->getResult('unavailable');
        if (!$rule['active'] || $this->akey === '' || (!$this->actor['uid'] && !$rule['guests'])) return $this->getResult('denied');
        return $this->setUnitRun(fn(): array => $this->addVoteRow($scope, $id, $value, $request, $rule), ['scope' => $scope, 'mid' => $id, 'actor' => $this->akey]);
    }

    # Annul one vote as the main administrator, with a reason; it needs neither an active rating nor an open interval, and a repeat is an empty success
    # A vote that does not exist and a target that is physically gone both answer unavailable, and the history stays whatever happens to the target
    public function deleteRating(int $vote, string $reason): array {
        if (!$this->actor || !$this->actor['super']) return $this->getResult('denied');
        if ($vote < 1 || !$this->checkReason($reason)) return $this->getResult('invalid');
        $res = $this->db->getSqlQuery('SELECT scope, mid, actor FROM '.PREFIX_DB.'_rating_votes WHERE id = :id', ['id' => $vote]);
        if ($res === false) return $this->getResult('storage');
        $row = $this->db->getSqlRow($res);
        if (!$row) return $this->getResult('unavailable');
        return $this->setUnitRun(fn(): array => $this->deleteVoteRow($vote, $reason, $row), ['vote' => $vote, 'aid' => $this->actor['aid']]);
    }

    # Page through the stored votes for the main administrator: of everything, of one scope, or of one target, by a cursor of the vote id and without a count
    # The rows are the stored columns as they are, with the reason and the state of every vote; next is the last id of the page and zero when the page is empty
    public function getRatingList(string $scope = '', int $id = 0, int $after = 0, int $limit = 50): array {
        $out = ['ok' => false, 'code' => 'denied', 'rows' => [], 'next' => 0];
        if (!$this->actor || !$this->actor['super']) return $out;
        $good = $after >= 0 && $limit >= 1 && $limit <= self::MAXLIST && $id >= 0 && $id <= self::MAXID && ($scope === '' ? $id === 0 : preg_match(self::SCOPE, $scope));
        if (!$good) return array_replace($out, ['code' => 'invalid']);
        $sql = 'SELECT id, scope, mid, actor, uid, value, request, created, annulled, aid, reason FROM '.PREFIX_DB.'_rating_votes WHERE id > :after';
        $pars = ['after' => $after];
        if ($scope !== '') $pars['scope'] = $scope;
        if ($id) $pars['mid'] = $id;
        $sql .= ($scope !== '' ? ' AND scope = :scope' : '').($id ? ' AND mid = :mid' : '').' ORDER BY id LIMIT '.$limit;
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) return array_replace($out, ['code' => 'storage']);
        foreach ($this->db->getSqlRows($res) ?: [] as $row) {
            $one = ['id' => 0, 'scope' => '', 'mid' => 0, 'actor' => '', 'uid' => 0, 'value' => 0, 'request' => '', 'created' => 0, 'annulled' => 0, 'aid' => 0, 'reason' => ''];
            foreach ($one as $key => $void) $one[$key] = is_int($void) ? intval($row[$key]) : $row[$key];
            $out['rows'][] = $one;
            $out['next'] = $one['id'];
        }
        return array_replace($out, ['ok' => true, 'code' => 'ok']);
    }
}
