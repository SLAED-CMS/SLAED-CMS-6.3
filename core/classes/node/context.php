<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The request snapshot every Node read and write is decided against: the site user with the effective groups, the separate administrator identity,
# the types that administrator moderates, the right to manage Node, the main administrator flag, the address, the language, the trusted background flag
# and the right to delete shared polls
# The one shared assembly at the request boundary builds it; the object reads no session, cookie, global or database, and it refuses a snapshot that contradicts itself
final readonly class NodeContext {

    # The grammar of a public type name, which is what an entry of the moderated types carries without its storage prefix
    private const NAME = '/^[a-z][a-z0-9]{0,19}$/D';

    # Refuse a snapshot whose rights have no administrator behind them, whose lists repeat or carry foreign values, or whose background flag carries any identity
    public function __construct(
        public int $uid,
        public array $groups,
        public int $aid,
        public array $mods,
        public bool $manage,
        public bool $super,
        public string $ip,
        public string $lang,
        public bool $task = false,
        public bool $polls = false
    ) {
        if ($uid < 0 || $aid < 0) throw new NodeException('A context identity is negative', NodeException::INVALID);
        if (!array_is_list($groups) || count(array_unique($groups)) !== count($groups)) throw new NodeException('Context groups are no unique id list', NodeException::INVALID);
        foreach ($groups as $one) if (!is_int($one) || $one < 1) throw new NodeException('A context group is no positive id', NodeException::INVALID);
        if (!array_is_list($mods) || count(array_unique($mods)) !== count($mods)) throw new NodeException('Context types are not a list of unique names', NodeException::INVALID);
        foreach ($mods as $one) if (!is_string($one) || !preg_match(self::NAME, $one)) throw new NodeException('A context type is no public type name', NodeException::INVALID);
        if (($manage || $super || $polls || $mods !== []) && $aid < 1) throw new NodeException('An administrative right without an administrator', NodeException::INVALID);
        if ($task && ($uid || $aid || $groups || $mods || $manage || $super || $polls)) throw new NodeException('A background context carries an identity', NodeException::INVALID);
    }
}
