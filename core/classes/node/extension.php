<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The contract of the special behaviour of one type: nine methods, reached only through the closed factory of core/classes/node/ext/load.php
# An extension adds checks, scope and its own rows to what the core does; it never replaces rights, transactions, the base write or the base reads,
# and a write method runs inside the transaction of its owner, so it performs no network request and nothing that cannot be rolled back
interface NodeExtension {

    # Check the settings of the extension against the effective standard section of the type and its checked fields, and return them canonical
    public function filterNodeConfig(array $config, array $settings, array $fields): array;

    # Check and canonicalize the extension input of a material before the writing transaction starts; the stored material is passed on change and null on create
    public function filterNodeData(NodeType $type, array $data, ?Node $node = null): array;

    # Return the trusted join, where and params that narrow every public read of the type before counting and paging
    public function getNodeScope(NodeType $type): array;

    # Forbid one of the closed actions on an already read target in addition to the core rules; it can never allow what the core refused
    public function checkNodeAction(NodeType $type, Node|NodeTarget $node, string $action): bool;

    # Follow an action that has really happened, changing only rows of the extension inside the transaction of the action owner
    public function updateNodeAction(NodeType $type, NodeTarget $node, string $action): void;

    # Create the rows of the extension after the main row and the standard sets, before the shared commit
    public function addNodeData(Node $node, array $data): void;

    # Change the rows of the extension after the standard update, before the shared commit; data is null when only the state changed
    public function updateNodeData(Node $before, Node $after, ?array $data): void;

    # Remove the rows of the extension before the main row is physically deleted inside the same transaction
    public function deleteNodeData(Node $node): void;

    # Read the data of the extension for one page of accessible materials in one batch and return it as a map of material id to array
    public function getNodeData(NodeType $type, array $nodes, string $mode): array;
}
