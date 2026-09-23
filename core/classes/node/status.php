<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Life cycle state of one material, as the status column of the node table holds it; application code compares cases, never the stored numbers
# The matrix of possible moves belongs here alone; what a given context may do on top of it is decided by the service and the workflow of the type
enum NodeStatus: int {
    case Draft = 0;
    case Pending = 1;
    case Published = 2;
    case Disabled = 3;
    case Deleted = 4;

    # Every state a material may move into from each state; a restored material always comes back disabled, so the trash leads nowhere else
    private const MOVES = [
        self::Draft->value => [self::Pending, self::Published, self::Deleted],
        self::Pending->value => [self::Draft, self::Published, self::Deleted],
        self::Published->value => [self::Disabled, self::Deleted],
        self::Disabled->value => [self::Pending, self::Published, self::Deleted],
        self::Deleted->value => [self::Disabled],
    ];

    # Whether the matrix allows the move from this state into the given one; repeating the current state is no move and answers false
    public function checkStatusMove(self $to): bool {
        return in_array($to, self::MOVES[$this->value], true);
    }
}
