<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The only error of the public Node API; the cause is the standard code, the message is for the log and never reaches a visitor
# The module maps each code to its own HTTP status and localized text, and a storage failure carries the original exception as previous
final class NodeException extends RuntimeException {

    # The material or a related entity that an operation has to change does not exist
    public const NOTFOUND = 1;

    # The context does not allow the action
    public const DENIED = 2;

    # The input, the state or the requested move is not acceptable
    public const INVALID = 3;

    # The expected version is older than the stored one
    public const CONFLICT = 4;

    # The storage operation did not complete
    public const STORAGE = 5;

    # The public write window limits.send of the same address has not passed yet
    public const LIMITED = 6;
}
