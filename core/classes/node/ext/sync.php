<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The external material of a type with the extension key sync: one RSS or Atom source per material in _node_sync, whose canonical Markdown becomes the body of _nodes
# The source is fetched by the shared Feed outside every transaction, by the explicit administrative check or by the queue of the system job, never by a page view;
# the result is written afterwards only when the source and the version of the material are still the ones the fetch started from
# The hooks keep the source row inside the transaction of NodeService and never reach the network; the body belongs to the source and is never taken from a form
final class NodeSync implements NodeExtension {

    # The closed actions of the extension contract
    private const ACTIONS = ['comment', 'rate', 'favorite', 'asset', 'report'];

    # The bounds of the refresh period in seconds; zero leaves the manual check alone
    private const REFMIN = 300;
    private const REFMAX = 31536000;

    # The first retry after a failure and the ceiling of the growing retry delay, in seconds
    private const RETRY = 300;
    private const RETRYMAX = 86400;

    # The widths of the stored address and of the two validators
    private const URLMAX = 2048;
    private const ETAGMAX = 255;
    private const MODMAX = 100;

    private Database $db;
    private NodeContext $ctx;
    private Feed $feed;

    # Keep the database, the request context and the shared feed reader every check of a source goes through
    public function __construct(Database $db, NodeContext $context, Feed $feed) {
        $this->db = $db;
        $this->ctx = $context;
        $this->feed = $feed;
    }

    # The refusal of an input, with the path of the first error for the log
    private function getInvalid(string $path): NodeException {
        return new NodeException('Invalid sync input: '.$path, NodeException::INVALID);
    }

    # A storage failure with the last driver error as previous
    private function getStorage(string $text): NodeException {
        return new NodeException($text, NodeException::STORAGE, $this->db->laste);
    }

    # Run one statement; a statement the server refused is a storage failure
    private function getQueryRes(string $sql, array $pars = []): PDOStatement {
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) throw $this->getStorage('A sync statement failed');
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

    # Whether the context moderates the type by its name: the main administrator or a moderator named by the type
    private function checkModer(string $name): bool {
        return $this->ctx->super || in_array($name, $this->ctx->mods, true);
    }

    # The retry delay after the given number of failures in a row: five minutes doubled on every further failure, never more than a day
    private function getRetry(int $fails): int {
        return min(self::RETRYMAX, self::RETRY * 2 ** min(max($fails, 1) - 1, 9));
    }

    # A validator the source sent, kept only when its column can hold it; a longer one is dropped and the next check asks without it
    private function getValidator(string $val, int $max): string {
        return (strlen($val) <= $max) ? $val : '';
    }

    # The source row of one material, locked inside the open transaction, or null when the material has none
    private function getSourceRow(int $nid): ?array {
        $sql = 'SELECT url, refresh, checked, fails FROM '.PREFIX_DB.'_node_sync WHERE nid = :nid FOR UPDATE';
        $row = $this->getQueryRes($sql, ['nid' => $nid])->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? ['url' => $row['url'], 'refresh' => intval($row['refresh']), 'checked' => $row['checked'], 'fails' => intval($row['fails'])] : null;
    }

    # Insert the source row of a material with no result yet: a source with a period is due at once, a manual one never
    private function addSourceRow(int $nid, array $data): void {
        $sql = 'INSERT INTO '.PREFIX_DB.'_node_sync (nid, url, refresh, due) VALUES (:nid, :url, :ref, '.($data['refresh'] > 0 ? 'NOW()' : 'NULL').')';
        $this->getQueryRes($sql, ['nid' => $nid, 'url' => $data['url'], 'ref' => $data['refresh']]);
    }

    # The two keys of an input already made canonical by filterNodeData(), refused in any other shape
    private function checkSourceData(array $data): void {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['refresh', 'url'] || !is_string($data['url']) || !is_int($data['refresh'])) throw $this->getInvalid('ext');
    }

    # The extension has no settings of its own: the transport belongs to config/rss.php and the period of a source to its row
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        if ($config !== []) throw $this->getInvalid('ext');
        return [];
    }

    # Accept exactly the address and the refresh period of the source from a moderator of the type: an external http or https address without credentials in the canonical form
    # Feed will request, and a period of zero or 300 seconds up to a year; nothing here resolves a name or opens a connection
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        if (!$this->checkModer($type->name)) throw new NodeException('Only a moderator of the type sets a source', NodeException::DENIED);
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['refresh', 'url']) throw $this->getInvalid('ext');
        $url = is_string($data['url']) ? (Feed::getFeedUrl($data['url'])['url'] ?? '') : '';
        if ($url === '' || strlen($url) > self::URLMAX) throw $this->getInvalid('ext.url');
        $ref = $data['refresh'];
        if (!is_int($ref) || ($ref !== 0 && ($ref < self::REFMIN || $ref > self::REFMAX))) throw $this->getInvalid('ext.refresh');
        return ['url' => $url, 'refresh' => $ref];
    }

    # An external material is read by the rights of its type alone
    public function getNodeScope(NodeType $type): array {
        return ['join' => '', 'where' => '', 'params' => []];
    }

    # The extension forbids none of the closed actions
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool {
        if (!in_array($action, self::ACTIONS, true)) throw $this->getInvalid('action');
        return true;
    }

    # The extension keeps no data an action changes
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action, int $uid): void {
        if (!in_array($action, self::ACTIONS, true)) throw $this->getInvalid('action');
    }

    # Create the source row of a new material; its body stays empty until the first successful check brings the text of the source
    public function addNodeData(Node $node, array $data): void {
        $this->checkSourceData($data);
        if ((string)$node->body !== '') throw $this->getInvalid('body');
        $this->addSourceRow($node->id, $data);
    }

    # Follow a changed material: its body is never changed by a form; a new address forgets the validators and the history of results and is due at once,
    # a new period alone keeps them and moves the next check from the last one; a change of state leaves the row alone
    public function updateNodeData(Node $before, Node $after, ?array $data): void {
        if ($data === null) return;
        $this->checkSourceData($data);
        if ((string)$after->body !== (string)$before->body) throw $this->getInvalid('body');
        $row = $this->getSourceRow($after->id);
        if ($row === null) {
            $this->addSourceRow($after->id, $data);
            return;
        }
        $ref = $data['refresh'];
        if ($data['url'] !== $row['url']) {
            $sql = 'UPDATE '.PREFIX_DB.'_node_sync SET url = :url, refresh = :ref, due = '.($ref > 0 ? 'NOW()' : 'NULL').', checked = NULL, synced = NULL, etag = \'\','
                .' modified = \'\', fails = 0, error = \'\' WHERE nid = :nid';
            $this->getQueryRes($sql, ['url' => $data['url'], 'ref' => $ref, 'nid' => $after->id]);
            return;
        }
        if ($ref === $row['refresh']) return;
        $pars = ['ref' => $ref, 'nid' => $after->id];
        $due = 'NULL';
        if ($ref > 0 && $row['checked'] === null) {
            $due = 'NOW()';
        } elseif ($ref > 0) {
            $due = 'DATE_ADD(checked, INTERVAL :wait SECOND)';
            $pars['wait'] = ($row['fails'] > 0) ? $this->getRetry($row['fails']) : $ref;
        }
        $this->getQueryRes('UPDATE '.PREFIX_DB.'_node_sync SET refresh = :ref, due = '.$due.' WHERE nid = :nid', $pars);
    }

    # Remove the source row before the material itself is deleted inside the same transaction
    public function deleteNodeData(Node $node): void {
        $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_sync WHERE nid = :nid', ['nid' => $node->id]);
    }

    # Read the source rows of one page of materials with one statement for the administrative view of a moderator of the type; a public view gets nothing,
    # because the address of the source and the technical error are not part of the material
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        if ($mode !== 'admin' || !$this->checkModer($type->name)) return [];
        $ids = [];
        foreach ($nodes as $one) if (($one instanceof Node || $one instanceof NodeTarget) && $one->id > 0) $ids[] = $one->id;
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $pars = [];
        $sql = 'SELECT nid, url, refresh, due, checked, synced, fails, error FROM '.PREFIX_DB.'_node_sync WHERE nid IN ('.$this->getInList($ids, 'i', $pars).')';
        $out = [];
        foreach ($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[intval($row['nid'])] = ['url' => $row['url'], 'refresh' => intval($row['refresh']), 'due' => $row['due'], 'checked' => $row['checked'],
                'synced' => $row['synced'], 'fails' => intval($row['fails']), 'error' => $row['error']];
        }
        return $out;
    }

    # Store the result of one fetch in its own transaction when the source and the version of the material are still the ones the fetch started from
    # A new text passes the bound of nodes.body and changes only the body, the update time and the version of the material with the source row; an answer 304 or the same text
    # changes only the source row; a failure keeps the text and the validators, counts the failure and waits longer; a concurrent change of either side writes nothing
    # The cache guard of a new text goes only after a proven rollback or a raised generation: a commit or a rollback whose outcome is unknown keeps it
    private function setSourceResult(array $snap, array $res): array {
        $nid = $snap['nid'];
        $new = $res['ok'] && $res['changed'] && $res['body'] !== $snap['body'];
        $err = $res['ok'] ? '' : (($res['error'] !== '') ? $res['error'] : 'transport');
        if ($new && checkEditorTextRoom($res['body'], 'nodes.body') !== '') [$new, $err] = [false, 'body'];
        $guard = false;
        $step = 'before';
        $wrote = false;
        $out = ['id' => $nid, 'status' => 'skipped', 'error' => ''];
        try {
            if ($new) {
                $guard = Cache::getWriteGuard();
                if ($guard === false) throw $this->getStorage('The cache guard cannot be taken');
            }
            if (!$this->db->setSqlBegin()) throw $this->getStorage('The transaction cannot be started');
            $step = 'open';
            $sql = 'SELECT version, status, NOW() AS now FROM '.PREFIX_DB.'_nodes WHERE id = :id FOR UPDATE';
            $head = $this->getQueryRes($sql, ['id' => $nid])->fetch(PDO::FETCH_ASSOC);
            $row = is_array($head) ? $this->getSourceRow($nid) : null;
            $live = is_array($head) && intval($head['version']) === $snap['version'] && intval($head['status']) !== NodeStatus::Deleted->value;
            if ($row !== null && $live && $row['url'] === $snap['url']) {
                $now = $head['now'];
                $due = ($row['refresh'] > 0) ? 'DATE_ADD(:dnow, INTERVAL :wait SECOND)' : 'NULL';
                $pars = ['now' => $now, 'nid' => $nid] + (($row['refresh'] > 0) ? ['dnow' => $now, 'wait' => $row['refresh']] : []);
                if ($err !== '') {
                    $fails = min($row['fails'] + 1, 65535);
                    if ($row['refresh'] > 0) $pars['wait'] = $this->getRetry($fails);
                    $sql = 'UPDATE '.PREFIX_DB.'_node_sync SET checked = :now, fails = :fails, error = :err, due = '.$due.' WHERE nid = :nid';
                    $this->getQueryRes($sql, $pars + ['fails' => $fails, 'err' => $err]);
                    $out = ['id' => $nid, 'status' => 'failed', 'error' => $err];
                } else {
                    if ($new) {
                        $sql = 'UPDATE '.PREFIX_DB.'_nodes SET body = :body, updated = :now, version = version + 1 WHERE id = :id AND version = :ver';
                        $done = $this->getQueryRes($sql, ['body' => $res['body'], 'now' => $now, 'id' => $nid, 'ver' => $snap['version']])->rowCount();
                        if ($done !== 1) throw $this->getStorage('The body of a synchronised material was not written');
                        $wrote = true;
                    }
                    $sql = 'UPDATE '.PREFIX_DB.'_node_sync SET checked = :now'.($new ? ', synced = :snow' : '').', etag = :etag, modified = :mod, fails = 0, error = \'\','
                        .' due = '.$due.' WHERE nid = :nid';
                    if ($new) $pars['snow'] = $now;
                    $this->getQueryRes($sql, $pars + ['etag' => $this->getValidator($res['etag'], self::ETAGMAX), 'mod' => $this->getValidator($res['modified'], self::MODMAX)]);
                    $out = ['id' => $nid, 'status' => $new ? 'updated' : 'unchanged', 'error' => ''];
                }
            }
            $step = 'unknown';
            if (!$this->db->setSqlCommit()) throw $this->getStorage('The commit of a sync result is uncertain');
            $step = 'done';
        } catch (Throwable $fail) {
            if ($step === 'open' && !$this->db->setSqlRollback()) $step = 'unknown';
            Logger::addSite('error', 'Node: the result of a feed source could not be stored', ['nid' => $nid, 'error' => get_class($fail)]);
            $out = ['id' => $nid, 'status' => 'failed', 'error' => 'storage'];
        }
        $bump = $wrote && $step === 'done' && Cache::addEpoch(true);
        if ($wrote && $step === 'done' && !$bump) Logger::addSite('error', 'Node: the cache generation could not be raised after a sync', ['nid' => $nid]);
        $keep = $step === 'unknown' || ($wrote && $step === 'done' && !$bump);
        if ($guard !== false && !$keep) Cache::deleteWriteGuard($guard);
        if ($out['status'] === 'failed' && $out['error'] !== 'storage') Logger::addSite('warning', 'Node: a feed source failed', ['nid' => $nid, 'error' => $out['error']]);
        return $out;
    }

    # Check one source now: the snapshot of the material and its source is read without a lock, the feed is fetched with the stored validators outside any transaction,
    # and the result is stored by setSourceResult(); a material in the trash is skipped
    private function setSourceCheck(array $snap): array {
        if ($snap['status'] === NodeStatus::Deleted->value) return ['id' => $snap['nid'], 'status' => 'skipped', 'error' => ''];
        return $this->setSourceResult($snap, $this->feed->getFeedContent($snap['url'], $snap['etag'], $snap['modified']));
    }

    # The snapshots of the materials and their sources the trusted condition selects, with the name, state and extension of each type, in the given order and bound
    private function getSourceSnaps(string $where, array $pars, string $tail = ''): array {
        $sql = 'SELECT s.nid, s.url, s.etag, s.modified, n.body, n.version, n.status, t.name, t.ext FROM '.PREFIX_DB.'_node_sync AS s'
            .' INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = s.nid INNER JOIN '.PREFIX_DB.'_node_types AS t ON t.id = n.tid WHERE '.$where.$tail;
        $out = [];
        foreach ($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = ['nid' => intval($row['nid']), 'url' => $row['url'], 'etag' => $row['etag'], 'modified' => $row['modified'],
                'body' => $row['body'], 'version' => intval($row['version']), 'status' => intval($row['status']), 'name' => $row['name'],
                'ext' => $row['ext']];
        }
        return $out;
    }

    # Check the source of one material now, whatever its due time, for a moderator of its type; the answer names the material, the outcome
    # updated, unchanged, failed or skipped, and the short safe error of a failure; a background context uses the queue instead
    public function updateNodeSync(int $id): array {
        if ($this->ctx->task) throw new NodeException('A background context checks the queue only', NodeException::DENIED);
        $snap = ($id > 0) ? ($this->getSourceSnaps('s.nid = :id', ['id' => $id])[0] ?? null) : null;
        if ($snap === null || $snap['ext'] !== 'sync') throw new NodeException('The external material does not exist', NodeException::NOTFOUND);
        if (!$this->checkModer($snap['name'])) throw new NodeException('The context does not moderate the type', NodeException::DENIED);
        return $this->setSourceCheck($snap);
    }

    # Check the due sources of active types for the scheduler in the order of the queue, one fetch and one transaction per material, and answer in the form of the scheduler
    # Only the trusted background context runs it; the limit counts network sources and is not the batch of the global subsystems; one statement selects the queue
    public function updateNodeSyncList(int $limit): array {
        if (!$this->ctx->task) throw new NodeException('Only a background context checks the queue', NodeException::DENIED);
        if ($limit < 1 || $limit > 50) throw $this->getInvalid('limit');
        $sum = ['checked' => 0, 'updated' => 0, 'unchanged' => 0, 'failed' => 0, 'skipped' => 0];
        $where = 's.due <= NOW() AND t.active = 1 AND t.ext = \'sync\' AND n.status != :del';
        foreach ($this->getSourceSnaps($where, ['del' => NodeStatus::Deleted->value], ' ORDER BY s.due, s.id LIMIT '.$limit) as $snap) {
            $sum['checked']++;
            $sum[$this->setSourceCheck($snap)['status']]++;
        }
        $text = sprintf('Node sync: %d checked, %d updated, %d unchanged, %d failed, %d skipped', $sum['checked'], $sum['updated'], $sum['unchanged'], $sum['failed'],
            $sum['skipped']);
        return ['status' => $sum['failed'] ? 'failed' : 'success', 'message' => $text, 'extra' => $sum];
    }
}
