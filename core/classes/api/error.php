<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The one exception a guard or a handler of the API throws: a code of the closed list, its details for the client and the headers its reply carries
final class ApiError extends RuntimeException {

    # Every code of the API with its status and its short English text; a client localizes by the code, and a new code changes .rules/api.md first
    public const CODES = [
        'bad_request' => [400, 'Bad request'],
        'no_auth' => [401, 'Unauthorized'],
        'no_scope' => [403, 'Forbidden'],
        'no_route' => [404, 'Not found'],
        'no_method' => [405, 'Method not allowed'],
        'conflict' => [409, 'Conflict'],
        'too_large' => [413, 'Payload too large'],
        'invalid' => [422, 'Invalid input'],
        'too_many' => [429, 'Too many requests'],
        'maintenance' => [503, 'Maintenance'],
        'disabled' => [503, 'Disabled'],
        'internal' => [500, 'Internal error'],
    ];

    # A code outside the list becomes the 500 internal, so no hand-made status ever reaches a client
    public function __construct(public readonly string $name, public readonly array $details = [], public readonly array $head = []) {
        $code = isset(self::CODES[$name]) ? $name : 'internal';
        parent::__construct(self::CODES[$code][1], self::CODES[$code][0]);
    }

    # The reply array of this refusal for ApiRouter::setApiReply()
    public function getReply(): array {
        return ['code' => isset(self::CODES[$this->name]) ? $this->name : 'internal', 'details' => $this->details, 'head' => $this->head];
    }
}
