<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

require_once __DIR__.'/error.php';
require_once __DIR__.'/client.php';
require_once __DIR__.'/input.php';
require_once __DIR__.'/limit.php';

# The router of the connector: the branch api/ asks it for the reply of a request, read from the one service its path names, and hands that reply to its one writer
final class ApiRouter {

    # The keys an action may declare, every other one refuses the whole declaration
    private const KEYS = ['method', 'call', 'auth', 'scope', 'params', 'size', 'cache', 'tool', 'ver', 'reply'];

    # The headers the guards of the current request collected for its reply, a refusal included
    private static array $head = [];

    # The checked declarations read in this request by service name, false for a refused one
    private static array $list = [];

    # The reply of the current request: the guards of .rules/api.md in their fixed order, then the handler; every refusal is an ApiError turned into its reply
    # The guards of the clients - auth by key or secret, scope, origin, limit - come with the clients; until then an action that is not public is 401
    public static function getResponse(): array {
        self::$head = self::getCorsHead();
        $ver = 'v1';
        try {
            $act = self::checkApiRoute();
            $ver = 'v'.$act['vnum'];
            self::checkApiMaint($act);
            if (self::checkApiCors($act)) return ['status' => 204, 'data' => [], 'head' => self::$head, 'ver' => $ver];
            self::checkApiFlood();
            $cli = self::checkApiAuth($act);
            self::checkApiSize($act);
            $in = self::filterApiInput($act);
            return ['head' => self::$head, 'ver' => $ver] + self::getActionReply($act, $in, $cli);
        } catch (ApiError $err) {
            return ['head' => array_replace(self::$head, $err->head), 'ver' => $ver] + $err->getReply();
        }
    }

    # The live reference of system/schema from the declarations of every service the site runs: what a guest may call, never the handler behind it
    # A service or action switched off and a service whose declaration is refused are left out
    public static function getSchemaData(): array {
        global $conf;
        $names = array_map(fn(string $v): string => basename($v, '.php'), glob(BASE_DIR.'/core/classes/api/services/*.php') ?: []);
        foreach ($conf['modules'] ?? [] as $name => $mod) if (intval($mod['active'] ?? 0) === 1 && is_file(BASE_DIR.'/modules/'.$name.'/api.php')) $names[] = (string)$name;
        $off = (array)($conf['api']['off'] ?? []);
        $out = [];
        foreach (array_unique($names) as $name) {
            if (in_array($name, $off, true) || !preg_match('#^[a-z][a-z0-9]{1,23}$#D', $name)) continue;
            try {
                $decl = self::getApiService($name);
            } catch (ApiError) {
                continue;
            }
            $acts = [];
            foreach ($decl['actions'] ?? [] as $key => $one) {
                if ($one['auth'] !== 'public' || in_array($name.'/'.$key, $off, true)) continue;
                $acts[$key] = ['method' => $one['method'], 'auth' => $one['auth'], 'ver' => $one['ver'], 'reply' => $one['reply'],
                    'params' => (object)array_map(self::getParamView(...), $one['params'])] + (isset($one['tool']) ? ['tool' => $one['tool']] : []);
            }
            if ($acts !== []) $out[$name] = ['title' => $decl['title'], 'actions' => $acts];
        }
        ksort($out);
        return ['services' => (object)$out];
    }

    # Write the one reply of a request under api/ and end it: a file through getFileStream(), otherwise the envelope with the data or the code of the refusal
    # A reply without a known code takes the code of its status, an unknown one being the 500 internal; a 204 carries no body, and a HEAD loses its body at the server
    public static function setApiReply(array $reply): never {
        while (ob_get_level() > 0) ob_end_clean();
        if (headers_sent()) exit;
        $rid = self::getRequestId();
        header_remove();
        foreach ((array)($reply['head'] ?? []) as $key => $val) header($key.': '.$val);
        header('X-Request-Id: '.$rid);
        if (($reply['mode'] ?? '') === 'file') getFileStream($reply['path'], $reply['name'], $reply['mime'], false, $reply['cache']);
        $okay = array_key_exists('data', $reply);
        $code = (string)($reply['code'] ?? array_search((int)($reply['status'] ?? 500), array_map(static fn(array $v): int => $v[0], ApiError::CODES), true));
        $code = isset(ApiError::CODES[$code]) ? $code : 'internal';
        $stat = $okay ? (int)($reply['status'] ?? 200) : ApiError::CODES[$code][0];
        http_response_code($stat);
        if ($stat === 204) {
            ini_set('default_mimetype', '');
            exit;
        }
        $from = (float)($GLOBALS['sgtime'] ?? $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
        $meta = ['id' => $rid, 'ver' => (string)($reply['ver'] ?? 'v1'), 'ms' => (int)round((microtime(true) - $from) * 1000), 'now' => (int)floor(microtime(true) * 1000)];
        $body = $okay ? ['ok' => true, 'data' => ($reply['data'] === []) ? new stdClass() : $reply['data'], 'meta' => $meta]
            : ['ok' => false, 'error' => ['code' => $code, 'message' => ApiError::CODES[$code][1], 'details' => (object)($reply['details'] ?? [])], 'meta' => $meta];
        $text = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($text === false) {
            Logger::addPhp('error', 'API reply not encoded: '.json_last_error_msg(), ['rid' => $rid]);
            http_response_code(500);
            $okay = false;
            $text = json_encode(['ok' => false, 'error' => ['code' => 'internal', 'message' => ApiError::CODES['internal'][1], 'details' => new stdClass()], 'meta' => $meta]);
        }
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: '.(($okay && !empty($reply['cache'])) ? $reply['cache'] : (self::isAuthSent() ? 'private, no-store' : 'no-store')));
        header('X-Content-Type-Options: nosniff');
        echo $text;
        exit;
    }

    # Guard 1: the path v<n>/<service>/<action> to an action declared for that version: 404 no_route for anything else, 405 no_method with Allow for another method
    # An OPTIONS passes to the guard of CORS, and a HEAD is the GET of the same action
    private static function checkApiRoute(): array {
        if (!preg_match('#^v([1-9]\d?)/([a-z][a-z0-9]{1,23})/([a-z][a-z0-9]{0,31})$#D', (string)getRequestPath('api'), $hit)) throw new ApiError('no_route');
        $one = self::getApiService($hit[2])['actions'][$hit[3]] ?? null;
        if ($one === null || !in_array((int)$hit[1], $one['ver'], true)) throw new ApiError('no_route');
        $meth = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $allow = ($one['method'] === 'GET') ? 'GET, HEAD' : $one['method'];
        if ($meth !== 'OPTIONS' && $meth !== $one['method'] && !($meth === 'HEAD' && $one['method'] === 'GET')) {
            throw new ApiError('no_method', [], ['Allow' => $allow.', OPTIONS']);
        }
        return ['service' => $hit[2], 'action' => $hit[3], 'vnum' => (int)$hit[1], 'meth' => $meth, 'allow' => $allow] + $one;
    }

    # Guard 2: the API switched off answers 503 disabled everywhere, maintenance 503 maintenance to all but the service system, a switched-off service or action 503 disabled
    # A site without config/api.php runs the API on, as a missing option always reads its shipped value
    private static function checkApiMaint(array $act): void {
        global $conf;
        $api = $conf['api'] ?? [];
        $off = (array)($api['off'] ?? []);
        if (intval($api['active'] ?? 1) !== 1) throw new ApiError('disabled');
        if (intval($api['maint'] ?? 0) === 1 && $act['service'] !== 'system') throw new ApiError('maintenance');
        if (in_array($act['service'], $off, true) || in_array($act['service'].'/'.$act['action'], $off, true)) throw new ApiError('disabled');
    }

    # Guard 3: an Origin that is neither the site's own nor on the global list is 403; an OPTIONS preflight is answered 204 here, without auth
    private static function checkApiCors(array $act): bool {
        global $conf;
        $from = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $home = parse_url((string)($conf['homeurl'] ?? '')) ?: [];
        $own = ($home['scheme'] ?? '').'://'.($home['host'] ?? '').(isset($home['port']) ? ':'.$home['port'] : '');
        if ($from !== '' && $from !== $own && !isset(self::$head['Access-Control-Allow-Origin'])) throw new ApiError('no_scope');
        if ($act['meth'] !== 'OPTIONS') return false;
        self::$head['Allow'] = $act['allow'].', OPTIONS';
        if (isset(self::$head['Access-Control-Allow-Origin'])) {
            self::$head['Access-Control-Allow-Methods'] = $act['allow'];
            self::$head['Access-Control-Allow-Headers'] = 'Authorization, Content-Type, Idempotency-Key, X-Request-Id';
            self::$head['Access-Control-Max-Age'] = '600';
        }
        return true;
    }

    # Guard 4: the bucket of the caller's address before any database lookup, the address kept only as a keyed hash; an empty bucket is 429 with Retry-After
    private static function checkApiFlood(): void {
        global $conf;
        $lim = $conf['api']['limits'] ?? [];
        $key = hash_hmac('sha256', ApiLimit::getIpKey(getIp()), getSecret('api-flood'));
        $wait = ApiLimit::getWait('ip|'.$key, intval($lim['flood'] ?? 60), intval($lim['burst'] ?? 30));
        if ($wait > 0) throw new ApiError('too_many', [], ['Retry-After' => (string)$wait]);
    }

    # Guard 5: a public action is called as the guest; a key or a declared secret needs the clients of the connector, so until they exist such an action is 401
    private static function checkApiAuth(array $act): ApiClient {
        if ($act['auth'] !== 'public') throw new ApiError('no_auth');
        return new ApiClient();
    }

    # Guard 9: the declared length of the body against the size of the action or the default, 413 before a byte is read
    private static function checkApiSize(array $act): void {
        if (intval($_SERVER['CONTENT_LENGTH'] ?? 0) > self::getBodySize($act)) throw new ApiError('too_large');
    }

    # Guard 10: the declared params, each read by its type, become the input of the handler
    private static function filterApiInput(array $act): array {
        return ApiInput::getInput($act['params'], $act['meth'], self::getBodySize($act));
    }

    # The largest body an action takes: its own size, otherwise the default of config/api.php
    private static function getBodySize(array $act): int {
        global $conf;
        return $act['size'] ?: max(1, intval($conf['api']['limits']['size'] ?? 65536));
    }

    # Call the handler once every guard passed, the common.php of a module service loaded only now; a fault of it is logged under the request id and answers 500 internal
    private static function getActionReply(array $act, array $in, ApiClient $cli): array {
        $lib = BASE_DIR.'/modules/'.$act['service'].'/common.php';
        if (!is_file(BASE_DIR.'/core/classes/api/services/'.$act['service'].'.php') && is_file($lib)) require_once $lib;
        try {
            if (!function_exists($act['call'])) throw new LogicException('API handler '.$act['call'].' is not defined');
            $data = ($act['call'])($in, $cli);
            if (!is_array($data)) throw new LogicException('API handler '.$act['call'].' returned no array');
            if ($act['reply'] === 'file') return self::getFileReply($data, $act);
            $pub = $act['auth'] === 'public' && $act['cache'] > 0 && !self::isAuthSent();
            return ['data' => $data, 'cache' => $pub ? 'public, max-age='.$act['cache'] : ''];
        } catch (ApiError $err) {
            throw $err;
        } catch (Throwable $err) {
            Logger::addPhp('error', get_class($err).': '.$err->getMessage(), ['rid' => self::getRequestId(), 'file' => $err->getFile(), 'line' => $err->getLine()]);
            throw new ApiError('internal');
        }
    }

    # The reply of the mode file: a missing file is 404, one outside storage/update/ and the upload root a fault, so a slip never sends code, configuration, a dump or a journal
    # Any cache of the action is the private mode of getFileStream(): one browser keeps the file and revalidates it by its entity tag
    private static function getFileReply(array $data, array $act): array {
        $path = realpath((string)($data['path'] ?? ''));
        if ($path === false || !is_file($path) || !is_readable($path)) throw new ApiError('no_route');
        $path = str_replace('\\', '/', $path);
        $roots = array_filter([realpath(UPDATE_DIR), realpath(UPLOADS_DIR)]);
        $keep = array_filter($roots, fn(string $v): bool => str_starts_with($path, str_replace('\\', '/', $v).'/'));
        if ($keep === []) throw new LogicException('API handler '.$act['call'].' named a file outside storage/update and uploads');
        return ['mode' => 'file', 'path' => $path, 'name' => (string)($data['name'] ?? basename($path)), 'mime' => (string)($data['mime'] ?? 'application/octet-stream'),
            'cache' => ($act['cache'] > 0) ? 'private' : 'none'];
    }

    # The checked declaration of one service, a core service before a module of the same name; [] when there is none or its module is not active
    # A declaration the router refuses answers 500 internal, so a broken service never passes for a missing one
    private static function getApiService(string $name): array {
        global $conf;
        if (!array_key_exists($name, self::$list)) {
            $file = BASE_DIR.'/core/classes/api/services/'.$name.'.php';
            if (!is_file($file)) $file = (intval($conf['modules'][$name]['active'] ?? 0) === 1) ? BASE_DIR.'/modules/'.$name.'/api.php' : '';
            self::$list[$name] = ($file !== '' && is_file($file)) ? self::checkApiDeclare($name, require $file) : [];
        }
        if (self::$list[$name] === false) throw new ApiError('internal');
        return self::$list[$name];
    }

    # A declaration checked against .rules/api.md with the defaults of its actions filled in; a refused one is logged with the reason and kept as false
    private static function checkApiDeclare(string $name, mixed $decl): array|false {
        $acts = is_array($decl) ? ($decl['actions'] ?? null) : null;
        $scope = is_array($decl) ? ($decl['scopes'] ?? []) : [];
        $why = (!is_array($acts) || $acts === [] || !is_string($decl['title'] ?? null) || !is_array($scope)) ? 'shape' : '';
        foreach (($why === '') ? $scope : [] as $key => $text) if (!preg_match('#^'.$name.':[a-z]{2,24}$#D', (string)$key) || !is_string($text)) $why = 'scope '.$key;
        foreach (($why === '') ? $acts : [] as $key => $one) if ($why === '') $why = self::getActionFault((string)$key, $one, $scope);
        if ($why !== '') {
            Logger::addPhp('error', 'API declaration refused', ['service' => $name, 'why' => $why]);
            return false;
        }
        foreach ($acts as $key => $one) $acts[$key] = $one + ['auth' => 'key', 'params' => [], 'ver' => [1], 'reply' => 'envelope', 'size' => 0, 'cache' => 0];
        return ['title' => $decl['title'], 'scopes' => $scope, 'events' => (array)($decl['events'] ?? []), 'actions' => $acts];
    }

    # The first fault of one declared action, or an empty string: a public action only reads, a key action needs a scope of its service, every param a type of ApiInput
    private static function getActionFault(string $key, mixed $one, array $scope): string {
        if (!preg_match('#^[a-z][a-z0-9]{0,31}$#D', $key) || !is_array($one)) return 'action '.$key;
        $auth = $one['auth'] ?? 'key';
        $args = $one['params'] ?? [];
        $vers = $one['ver'] ?? [1];
        $bad = match (true) {
            array_diff(array_keys($one), self::KEYS) !== [] => 'keys',
            !in_array($one['method'] ?? '', ['GET', 'POST'], true) => 'method',
            !is_string($one['call'] ?? null) || !preg_match('#^[A-Za-z_][A-Za-z0-9_]*$#D', $one['call']) => 'call',
            !in_array($auth, ['key', 'secret', 'public'], true) => 'auth',
            ($auth === 'key' || isset($one['scope'])) && (!is_string($one['scope'] ?? null) || !isset($scope[$one['scope']])) => 'scope',
            $auth === 'public' && $one['method'] !== 'GET' => 'public',
            !is_array($args) || array_filter(array_keys($args), fn($v): bool => !preg_match('#^[a-z][a-z0-9_]{0,31}$#D', (string)$v)) !== [] => 'params',
            array_filter($args, fn($v): bool => !ApiInput::checkSpec($v)) !== [] => 'params',
            isset($one['size']) && (!is_int($one['size']) || $one['size'] < 1) => 'size',
            isset($one['cache']) && (!is_int($one['cache']) || $one['cache'] < 0) => 'cache',
            !in_array($one['reply'] ?? 'envelope', ['envelope', 'file'], true) => 'reply',
            !is_array($vers) || $vers === [] || array_filter($vers, fn($v): bool => !is_int($v) || $v < 1 || $v > 99) !== [] => 'ver',
            isset($one['tool']) && !is_array($one['tool']) => 'tool',
            default => '',
        };
        return ($bad === '') ? '' : $key.': '.$bad;
    }

    # One declared param as system/schema shows it: its type, whether it is required, its argument by name and its default
    private static function getParamView(mixed $spec): array {
        $spec = is_array($spec) ? $spec : [$spec];
        $view = ['type' => $spec[0], 'req' => !array_key_exists('def', $spec)];
        if (isset($spec[1])) $view[match ($spec[0]) { 'num' => 'max', 'word' => 'values', default => 'len' }] = $spec[1];
        if (!$view['req']) $view['def'] = $spec['def'];
        return $view;
    }

    # The headers every reply of this request carries, whatever guard refuses it: Vary on Origin, and for an origin on the global list its echo
    private static function getCorsHead(): array {
        global $conf;
        $from = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
        $head = ['Vary' => 'Origin'];
        if ($from !== '' && in_array($from, (array)($conf['api']['cors'] ?? []), true)) {
            $head += ['Access-Control-Allow-Origin' => $from, 'Access-Control-Expose-Headers' => 'X-Request-Id, Retry-After'];
        }
        return $head;
    }

    # Whether the request carries an Authorization header; such a reply is never kept by a shared cache
    private static function isAuthSent(): bool {
        return (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '') !== '';
    }

    # The id of this request for its reply and its log: a valid X-Request-Id of the caller, otherwise a generated one
    private static function getRequestId(): string {
        static $rid = '';
        if ($rid === '') $rid = (string)($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
        if (!preg_match('#^[A-Za-z0-9-]{8,32}$#D', $rid)) $rid = bin2hex(random_bytes(8));
        return $rid;
    }
}
