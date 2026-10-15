<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The router of the connector: the branch api/ of public/index.php asks it for the reply of a request and hands that reply to its one writer
final class ApiRouter {

    # Every code of the API with its status and its short English text; a client localizes by the code
    private const CODES = [
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

    # The reply of the current request; no service is declared, so every route is unknown
    public static function getResponse(): array {
        return ['status' => 404, 'code' => 'no_route'];
    }

    # Write the one reply of a request under api/ and end it: data on success, otherwise the code, or the code of the status, an unknown one being the 500 internal
    public static function setApiReply(array $reply): never {
        while (ob_get_level() > 0) ob_end_clean();
        if (headers_sent()) exit;
        $okay = array_key_exists('data', $reply);
        $code = (string)($reply['code'] ?? array_search((int)($reply['status'] ?? 500), array_map(static fn(array $v): int => $v[0], self::CODES), true));
        $code = isset(self::CODES[$code]) ? $code : 'internal';
        $stat = $okay ? (int)($reply['status'] ?? 200) : self::CODES[$code][0];
        $rid = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        if (!preg_match('#^[A-Za-z0-9-]{8,32}$#D', $rid)) $rid = bin2hex(random_bytes(8));
        $from = (float)($GLOBALS['sgtime'] ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $meta = ['id' => $rid, 'ver' => (string)($reply['ver'] ?? 'v1'), 'ms' => (int)round((microtime(true) - $from) * 1000), 'now' => (int)floor(microtime(true) * 1000)];
        $body = $okay ? ['ok' => true, 'data' => $reply['data'], 'meta' => $meta]
            : ['ok' => false, 'error' => ['code' => $code, 'message' => self::CODES[$code][1], 'details' => (object)($reply['details'] ?? [])], 'meta' => $meta];
        header_remove();
        http_response_code($stat);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Request-Id: '.$rid);
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
}
