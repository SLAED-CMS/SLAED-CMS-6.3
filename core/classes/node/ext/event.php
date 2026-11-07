<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The event of a type with the extension key event: the start, the end and the place of a material live in _node_event, which orders and splits the list of the type
final class NodeEvent implements NodeExtension {

    # The closed actions of the extension contract
    private const ACTIONS = ['comment', 'rate', 'favorite', 'asset', 'report'];

    # A canonical database date and time
    private const DATE = '/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})$/D';

    # The width of the stored place in characters
    private const PLACEMAX = 255;

    # The moment an event leaves the upcoming list: its end, or without one the end of the day it starts on
    private const UNTIL = 'COALESCE(ne.ends, DATE(ne.starts) + INTERVAL 1 DAY)';

    private Database $db;

    # Keep the database; the context the factory hands every extension decides nothing here, as an event belongs to whoever may write its material
    public function __construct(Database $db, NodeContext $context) {
        $this->db = $db;
    }

    # The refusal of an input, with the path of the first error for the log
    private function getInvalid(string $path): NodeException {
        return new NodeException('Invalid event input: '.$path, NodeException::INVALID);
    }

    # Run one statement; a statement the server refused is a storage failure
    private function getQueryRes(string $sql, array $pars = []): PDOStatement {
        $res = $this->db->getSqlQuery($sql, $pars);
        if ($res === false) throw new NodeException('An event statement failed', NodeException::STORAGE, $this->db->laste);
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

    # Whether a value is a canonical database date and time of the calendar from the year 1000, the first one DATETIME stores
    private function checkDate(mixed $text): bool {
        if (!is_string($text) || !preg_match(self::DATE, $text, $hit)) return false;
        return intval($hit[1]) >= 1000 && checkdate(intval($hit[2]), intval($hit[3]), intval($hit[1])) && intval($hit[4]) < 24 && intval($hit[5]) < 60 && intval($hit[6]) < 60;
    }

    # The three keys of an input already made canonical by filterNodeData(), refused in any other shape
    private function checkEventData(array $data): void {
        $keys = array_keys($data);
        sort($keys);
        $good = $keys === ['ends', 'place', 'starts'] && $this->checkDate($data['starts']) && ($data['ends'] === null || $this->checkDate($data['ends']))
            && is_string($data['place']);
        if (!$good) throw $this->getInvalid('ext');
    }

    # The list arguments of the request: null for every read that is not the list of the type, else exactly the archive switch
    private function getArchive(?array $args): ?bool {
        if ($args === null) return null;
        if (array_keys($args) !== ['archive'] || !is_bool($args['archive'])) throw $this->getInvalid('args');
        return $args['archive'];
    }

    # The extension has no settings of its own; the display mode event, which the reader ties to this key, shows the date of the event
    public function filterNodeConfig(array $config, array $settings, array $fields): array {
        if ($config !== []) throw $this->getInvalid('ext');
        return [];
    }

    # Accept the start, the optional end after it and the place of an event from whoever may write the material; an empty end is stored as none
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array {
        $keys = array_keys($data);
        sort($keys);
        if ($keys !== ['ends', 'place', 'starts']) throw $this->getInvalid('ext');
        $from = $data['starts'];
        $till = ($data['ends'] === '') ? null : $data['ends'];
        if (!$this->checkDate($from)) throw $this->getInvalid('ext.starts');
        if ($till !== null && (!$this->checkDate($till) || strcmp($till, $from) <= 0)) throw $this->getInvalid('ext.ends');
        $place = is_string($data['place']) ? trim($data['place']) : null;
        if ($place === null || !mb_check_encoding($place, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/', $place) || mb_strlen($place) > self::PLACEMAX) {
            throw $this->getInvalid('ext.place');
        }
        return ['starts' => $from, 'ends' => $till, 'place' => $place];
    }

    # The list of the type reads the upcoming events or, with the archive switch, the past ones; every other read sees every event of the type
    public function getNodeScope(NodeType $type, ?array $args): array {
        $past = $this->getArchive($args);
        if ($past === null) return ['join' => '', 'where' => '', 'params' => []];
        return ['join' => 'INNER JOIN '.PREFIX_DB.'_node_event AS ne ON ne.nid = n.id', 'where' => self::UNTIL.($past ? ' <= NOW()' : ' > NOW()'), 'params' => []];
    }

    # The list of the type orders by the start over the join of its scope: the nearest upcoming event first, the newest past one first in the archive
    public function getNodeOrder(NodeType $type, ?array $args): array {
        $past = $this->getArchive($args);
        return ($past === null) ? [] : ['expr' => 'ne.starts', 'dir' => $past ? 'desc' : 'asc'];
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

    # Create the event row of a new material in the transaction of the material
    public function addNodeData(Node $node, array $data): void {
        $this->checkEventData($data);
        $sql = 'INSERT INTO '.PREFIX_DB.'_node_event (nid, starts, ends, place) VALUES (:nid, :starts, :ends, :place)';
        $this->getQueryRes($sql, ['nid' => $node->id, 'starts' => $data['starts'], 'ends' => $data['ends'], 'place' => $data['place']]);
    }

    # Write the event of a changed material, creating the row a material of the type should already have; a change of the state alone leaves it
    public function updateNodeData(Node $before, Node $after, ?array $data): void {
        if ($data === null) return;
        $this->checkEventData($data);
        $sql = 'INSERT INTO '.PREFIX_DB.'_node_event (nid, starts, ends, place) VALUES (:nid, :starts, :ends, :place)'
            .' ON DUPLICATE KEY UPDATE starts = :nstarts, ends = :nends, place = :nplace';
        $this->getQueryRes($sql, ['nid' => $after->id, 'starts' => $data['starts'], 'ends' => $data['ends'], 'place' => $data['place'], 'nstarts' => $data['starts'],
            'nends' => $data['ends'], 'nplace' => $data['place']]);
    }

    # Remove the event row before the material itself is deleted inside the same transaction
    public function deleteNodeData(Node $node): void {
        $this->getQueryRes('DELETE FROM '.PREFIX_DB.'_node_event WHERE nid = :nid', ['nid' => $node->id]);
    }

    # Read the events of one page in one statement for every view; the start, the end and the place are public parts of the material
    public function getNodeData(NodeType $type, array $nodes, string $mode): array {
        $ids = [];
        foreach ($nodes as $one) if (($one instanceof Node || $one instanceof NodeTarget) && $one->id > 0) $ids[] = $one->id;
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $pars = [];
        $sql = 'SELECT nid, starts, ends, place FROM '.PREFIX_DB.'_node_event WHERE nid IN ('.$this->getInList($ids, 'i', $pars).')';
        $out = [];
        foreach ($this->getQueryRes($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[intval($row['nid'])] = ['starts' => $row['starts'], 'ends' => $row['ends'], 'place' => $row['place']];
        }
        return $out;
    }
}
