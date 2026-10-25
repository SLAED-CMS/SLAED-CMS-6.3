<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The registry of public addresses: the one writer of _routes, which keeps every path an entity was ever given bound to it for ever
final class Route {

    # The kinds of entity a path names
    private const KINDS = ['type', 'node', 'cat', 'topic', 'user', 'poll'];

    # The grammar of a prefix and of the name of a type, the grammar NodeService gives a type name
    private const NAME = '/^[a-z][a-z0-9]{0,19}$/D';

    # A stored path: lowercase ASCII words joined by hyphens under at most one prefix
    private const PATH = '/^[a-z0-9-]+(?:\/[a-z0-9-]+)?$/D';

    # The states of a row: a former path answering 301, the current one, and a path of an entity that is gone answering 410
    private const FORMER = 0;
    private const CURRENT = 1;
    private const GONE = 2;

    # The operations of the switch of each module; any other prefix is a Node type with the operations of getNodeOps(), held by RouteRegistryTest
    private const OPS = [
        'node' => ['view', 'add', 'asset', 'report', 'support'],
        'account' => ['newuser', 'finnewuser', 'privat', 'rss', 'favorites', 'logins', 'view', 'login', 'logout', 'edithome', 'savehome', 'passlost',
            'passmail', 'activate', 'savepass', 'setavatar', 'oauth_init', 'oauth', 'oauth_finish', 'oauth_unlink', 'unsub'],
        'forum' => ['view', 'move', 'add', 'send', 'delete'],
        'recommend' => ['send'],
        'users' => ['rules', 'stats'],
        'voting' => ['view'],
    ];

    # The operations public/index.php answers under any name before it routes
    private const COMMON = ['mode', 'newlang'];

    # Names no type takes besides the modules: the entries of public/, the uploads the light path answers before the core, the prefix of the connector
    private const TYPES = ['admin', 'api', 'demo', 'error', 'favicon', 'index', 'phoenix', 'plugins', 'robots', 'setup', 'sitemap', 'templates',
        'update', 'uploads'];

    # How many paths a racing writer may take first before this one gives up
    private const TRIES = 20;

    # The largest value an unsigned integer column holds
    private const MAXINT = 4294967295;

    # The fixed trusted name of the savepoint that isolates a write inside the transaction of its writer
    private const SAVE = 'slroute';

    # The server codes that take the whole transaction with them: a deadlock, and a record changed since the snapshot was taken
    private const LOST = [1213, 1020];

    # How many times a write in its own transaction runs whole when the server rolls that transaction back; the connection learns of it only from the next answer
    private const ROUNDS = 3;

    private Database $db;

    # The server code of the last refused statement of a write, read before anything else replaces it
    private int $errno = 0;

    # Build the registry over the connection of the request; it holds no HTTP, template or configuration dependency
    public function __construct(Database $db) {
        $this->db = $db;
    }

    # The entity a path names, the state of the path and the current path of the entity, or an empty array for a path never given
    public function getRoute(string $path): array {
        if (strlen($path) > 255 || !preg_match(self::PATH, $path)) return [];
        $sql = 'SELECT r.kind, r.oid, r.state, c.path AS now FROM '.PREFIX_DB.'_routes AS r'
            .' LEFT JOIN '.PREFIX_DB.'_routes AS c ON c.kind = r.kind AND c.oid = r.oid AND c.cur = 1 WHERE r.path = :path';
        $row = $this->db->getSqlRow($this->db->getSqlQuery($sql, ['path' => $path]));
        return $row ? ['kind' => $row['kind'], 'oid' => intval($row['oid']), 'state' => intval($row['state']), 'path' => $row['now'] ?? ''] : [];
    }

    # The current path of each of the given entities of one kind in one query, keyed by id; an entity without a current path is absent
    public function getRouteList(string $kind, array $ids): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $v): bool => $v > 0 && $v <= self::MAXINT)));
        if (!$ids || !in_array($kind, self::KINDS, true)) return [];
        $sql = 'SELECT oid, path FROM '.PREFIX_DB.'_routes WHERE kind = ? AND cur = 1 AND oid IN ('.implode(', ', array_fill(0, count($ids), '?')).')';
        $res = $this->db->getSqlQuery($sql, array_merge([$kind], $ids));
        $out = [];
        while ($res && ($row = $this->db->getSqlRow($res))) $out[intval($row['oid'])] = $row['path'];
        return $out;
    }

    # Give an entity its first path from a title under a prefix and answer it; an entity that has one keeps it, a type takes its name or nothing, a refusal is ''
    public function addRoute(string $kind, int $oid, string $prefix, string $title): string {
        if (!$this->checkRouteOwner($kind, $oid, $prefix)) return '';
        $now = $this->getRouteList($kind, [$oid]);
        if (isset($now[$oid])) return $now[$oid];
        if ($kind === 'type') return $this->checkTypeName($title) ? $this->setRouteWrite($kind, $oid, '', $title) : '';
        return $this->setRouteWrite($kind, $oid, $prefix, filterSlug($title) ?: $oid);
    }

    # Move an entity to the path of a new name, its current path turning former and answering 301 for ever; a type never moves
    public function setRoute(string $kind, int $oid, string $prefix, string $title): string {
        if ($kind === 'type' || !$this->checkRouteOwner($kind, $oid, $prefix)) return '';
        return $this->setRouteWrite($kind, $oid, $prefix, filterSlug($title) ?: $oid);
    }

    # The path a form leaves an entity on: the name it gave, else its current name under a new prefix, else its first path from the title; answers the path or ''
    public function setRouteName(string $kind, int $oid, string $prefix, string $name, string $title): string {
        if ($kind === 'type' || !$this->checkRouteOwner($kind, $oid, $prefix)) return '';
        $now = $this->getRouteList($kind, [$oid])[$oid] ?? '';
        if ($name === '' && str_starts_with($now, $prefix.'/')) return $now;
        if ($name === '' && $now !== '') $name = substr($now, strpos($now, '/') + 1);
        return ($name === '') ? $this->addRoute($kind, $oid, $prefix, $title) : $this->setRoute($kind, $oid, $prefix, $name);
    }

    # Mark every path of a gone entity gone, the current one and all former ones, so each answers 410 and none is given again
    public function deleteRoute(string $kind, int $oid): bool {
        if (!in_array($kind, self::KINDS, true) || $oid < 1 || $oid > self::MAXINT) return false;
        $sql = 'UPDATE '.PREFIX_DB.'_routes SET state = :state WHERE kind = :kind AND oid = :oid';
        return $this->db->getSqlQuery($sql, ['state' => self::GONE, 'kind' => $kind, 'oid' => $oid]) !== false;
    }

    # Whether a name under a prefix is an operation of its module or type, which the parser reads before the registry, so a page of that name would never open
    public function isRouteReserved(string $prefix, string $name): bool {
        $ops = self::OPS[$prefix] ?? ((preg_match(self::NAME, $prefix) && is_dir(BASE_DIR.'/modules/'.$prefix)) ? [] : self::OPS['node']);
        return in_array($name, self::COMMON, true) || in_array($name, $ops, true);
    }

    # Whether a name is free for a new Node type: its grammar, no module, no entry of public/ and no path a type ever had, a deleted type included
    public function checkTypeName(string $name): bool {
        if (!preg_match(self::NAME, $name) || in_array($name, self::TYPES, true) || is_dir(BASE_DIR.'/modules/'.$name)) return false;
        return $this->getRoute($name) === [];
    }

    # Refuse an unknown kind, an id out of the column, and a prefix outside the grammar; a type is the one kind without a prefix
    private function checkRouteOwner(string $kind, int $oid, string $prefix): bool {
        if (!in_array($kind, self::KINDS, true) || $oid < 1 || $oid > self::MAXINT) return false;
        return ($kind === 'type') ? $prefix === '' : preg_match(self::NAME, $prefix) === 1;
    }

    # The rows holding the path of a name or one of its suffixes, keyed by path, so the first free suffix is found without a query per step
    private function getRouteTaken(string $base): array {
        $sql = 'SELECT path, kind, oid, state FROM '.PREFIX_DB.'_routes WHERE path = :path OR path LIKE :like';
        $res = $this->db->getSqlQuery($sql, ['path' => $base, 'like' => $base.'-%']);
        $out = [];
        while ($res && ($row = $this->db->getSqlRow($res))) $out[$row['path']] = ['kind' => $row['kind'], 'oid' => intval($row['oid']), 'state' => intval($row['state'])];
        return $out;
    }

    # One write of a path, answering it or '': a unit of its own the server rolled back whole runs again on a fresh snapshot, one behind a savepoint of its writer throws
    private function setRouteWrite(string $kind, int $oid, string $prefix, string $name): string {
        $own = !$this->db->checkSqlActive();
        for ($round = 1; ; $round++) {
            $this->errno = 0;
            $open = $own ? $this->db->setSqlBegin() : $this->db->getSqlQuery('SAVEPOINT '.self::SAVE) !== false;
            if (!$open && $own) return '';
            if (!$open) throw new RuntimeException('Route: the savepoint was refused and the outer transaction is unknown');
            $path = $this->addRouteRow($kind, $oid, $prefix, $name);
            if ($path !== '') {
                $kept = $own ? $this->db->setSqlCommit() : $this->db->getSqlQuery('RELEASE SAVEPOINT '.self::SAVE) !== false;
                if (!$kept) throw new RuntimeException('Route: the outcome of the commit is unknown');
                return $path;
            }
            $lost = in_array($this->errno, self::LOST, true);
            $back = $own ? $this->db->setSqlRollback() : !$lost && $this->db->getSqlQuery('ROLLBACK TO SAVEPOINT '.self::SAVE) !== false && $this->db->checkSqlActive();
            if ($own && $lost && $round < self::ROUNDS && $this->db->getSqlQuery('DO 1') !== false && !$this->db->checkSqlActive()) continue;
            Logger::addSite('error', 'Route: the path was not stored', ['kind' => $kind, 'oid' => $oid, 'name' => $name, 'errno' => $this->errno, 'round' => $round]);
            if ($lost || !$back) throw new RuntimeException('Route: the rollback is not proven and the transaction is lost');
            return '';
        }
    }

    # Take the first free path of a name: its own path back, else the name or its next suffix past every taken, reserved or racing path; a type has no suffix
    private function addRouteRow(string $kind, int $oid, string $prefix, string $name): string {
        $base = ($kind === 'type') ? $name : $prefix.'/'.$name;
        $taken = $this->getRouteTaken($base);
        $sql = 'INSERT INTO '.PREFIX_DB.'_routes (path, kind, oid) VALUES (:path, :kind, :oid)';
        $tries = 0;
        for ($num = 1; $tries < self::TRIES; $num++) {
            if ($kind === 'type' && $num > 1) return '';
            $path = ($num === 1) ? $base : $base.'-'.$num;
            $hold = $taken[$path] ?? null;
            if ($hold !== null && $hold['kind'] === $kind && $hold['oid'] === $oid && $hold['state'] !== self::GONE) return $this->setRouteBack($kind, $oid, $path, $hold['state']);
            if ($hold !== null || ($kind !== 'type' && $this->isRouteReserved($prefix, substr($path, strlen($prefix) + 1)))) continue;
            $tries++;
            if (!$this->setRouteFormer($kind, $oid)) return '';
            if ($this->db->getSqlQuery($sql, ['path' => $path, 'kind' => $kind, 'oid' => $oid]) !== false) return $path;
            $this->errno = intval($this->db->getSqlError()['code']);
            if ($this->db->getSqlTwin() !== 'path') return '';
            $taken[$path] = ['kind' => '', 'oid' => 0, 'state' => self::CURRENT];
        }
        return '';
    }

    # Turn the current path of an entity former, so the unique owner key leaves room for its next current path
    private function setRouteFormer(string $kind, int $oid): bool {
        $sql = 'UPDATE '.PREFIX_DB.'_routes SET state = :state WHERE kind = :kind AND oid = :oid AND cur = 1';
        if ($this->db->getSqlQuery($sql, ['state' => self::FORMER, 'kind' => $kind, 'oid' => $oid]) !== false) return true;
        $this->errno = intval($this->db->getSqlError()['code']);
        return false;
    }

    # Take a path of the entity itself back: the current one stays, a former one becomes current again and the current one former, so no chain ever forms
    private function setRouteBack(string $kind, int $oid, string $path, int $state): string {
        if ($state === self::CURRENT) return $path;
        if (!$this->setRouteFormer($kind, $oid)) return '';
        if ($this->db->getSqlQuery('UPDATE '.PREFIX_DB.'_routes SET state = :state WHERE path = :path', ['state' => self::CURRENT, 'path' => $path]) !== false) return $path;
        $this->errno = intval($this->db->getSqlError()['code']);
        return '';
    }
}
