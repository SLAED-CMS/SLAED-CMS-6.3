<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The updates of a site: the signatures of manifests, key statements and notes, the keys it trusts, the grammar of versions and paths, its check and its database steps
final class Update {

    # The only host a site asks, over HTTPS without a redirect; a fork changes it in the code, never in a setting
    public const HOST = 'https://slaed.net';

    # The page of a release for people, completed by its number; the panel builds the link from the version alone
    public const PAGE = 'https://github.com/SLAED-CMS/SLAED/releases/tag/v';

    # The largest answer of the master a site reads; a longer one is refused whole
    public const LIMIT = 1048576;

    # The fields of client/state.json: id, hash of the address, last check and its refusal, newest time per channel, release found, journal sizes, failure to report
    private const STATE = ['sid' => '', 'home' => '', 'last' => 0, 'error' => '', 'seen' => [], 'found' => [], 'logs' => [], 'fail' => []];

    # The type tags every signed message starts with, so a signature of one kind never passes for another
    public const MANIFEST = "SLAED-UPDATE-MANIFEST-v1\n";
    public const KEYCHANGE = "SLAED-KEYCHANGE-v1\n";
    public const NOTE = "SLAED-NOTE-v1\n";

    # The factory keys in base64: the primary signs releases and notes, the recovery key only key statements; test keys until the key ceremony
    public const PRIMARY = '59ubydnghcn58YwJIchp9O9rmPvkRhBfX1CssLJzopg=';
    public const RECOVERY = 'OpOGkJH+KEQehR5ZadvvlmTBtVb+WXOyLWM7yBndTA0=';

    # The folders a package may hold at its top; a file of the top level passes by its name alone
    public const DIRS = ['admin', 'blocks', 'config', 'core', 'docs', 'lang', 'modules', 'public', 'storage', 'tests', 'tools', 'uploads'];

    # A range of versions in a note: bounds such as ">=8.0.0 <8.0.2" separated by one space, or empty for every version
    public const RANGE = '#^((<=|>=|<|>|=)?\d{1,5}\.\d{1,5}\.\d{1,5}( (<=|>=|<|>|=)?\d{1,5}\.\d{1,5}\.\d{1,5})*)?$#D';

    # Whether a value is a version of the one grammar X.Y.Z that a manifest, a path and a note use
    public static function isVersion(mixed $ver): bool {
        return is_string($ver) && preg_match('#^\d{1,5}\.\d{1,5}\.\d{1,5}$#D', $ver) === 1;
    }

    # Whether a version lies in a range of the grammar RANGE; every bound must hold, and a malformed range holds no version
    public static function isVersionIn(string $ver, string $range): bool {
        if (!self::isVersion($ver) || preg_match(self::RANGE, $range) !== 1) return false;
        foreach (preg_split('# #', $range, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            preg_match('#^(<=|>=|<|>|=)?(.+)$#D', $part, $hit);
            if (!version_compare($ver, $hit[2], ($hit[1] === '') ? '=' : $hit[1])) return false;
        }
        return true;
    }

    # The raw bytes of a strict base64 text of exactly $len bytes, of any length for 0, or an empty string; only the canonical text of those bytes passes
    public static function getRawBytes(mixed $text, int $len = 0): string {
        if (!is_string($text)) return '';
        $raw = base64_decode($text, true);
        return (is_string($raw) && ($len === 0 || strlen($raw) === $len) && base64_encode($raw) === $text) ? $raw : '';
    }

    # The fingerprint of a raw public key, its SHA-256 in hex; the first 16 characters are the key id a manifest names
    public static function getKeyPrint(string $key): string {
        return hash('sha256', $key);
    }

    # Whether $sig signs $msg under the raw public $key; without the extension sodium the class Signature verifies, and only then is it loaded
    public static function isSignValid(string $msg, string $sig, string $key): bool {
        if (strlen($sig) !== 64 || strlen($key) !== 32) return false;
        if (extension_loaded('sodium')) return sodium_crypto_sign_verify_detached($sig, $msg, $key);
        require_once BASE_DIR.'/core/classes/signature.php';
        return Signature::isSignValid($msg, $sig, $key);
    }

    # The manifest from the bytes and base64 signature as served, verified under the primary key before decoding, then held against the site's version, channel and seen times
    public static function getManifest(string $body, string $sig, array $keys, array $site): array {
        $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'data' => []];
        $key = self::getRawBytes($keys['primary'] ?? '', 32);
        if ($key === '' || !self::isSignValid(self::MANIFEST.$body, self::getRawBytes($sig, 64), $key)) return $fail('sign');
        $data = self::getJsonData($body);
        if (!self::isManifest($data) || $data['kid'] !== substr(self::getKeyPrint($key), 0, 16)) return $fail('format');
        $chan = (($site['channel'] ?? '') === 'beta') ? 'beta' : 'stable';
        if ($data['channel'] !== 'stable' && $data['channel'] !== $chan) return $fail('channel');
        if (!version_compare($data['version'], (string)($site['version'] ?? ''), '>')) return $fail('version');
        if ($data['time'] < intval($site['seen'][$data['channel']] ?? 0)) return $fail('stale');
        if (intval($site['now'] ?? time()) >= $data['expires']) return $fail('expired');
        return ['ok' => true, 'error' => '', 'data' => $data];
    }

    # A note of the master from its bytes and base64 signature, verified under the primary key before decoding, then held against the site's sid, version and time
    public static function getNote(string $body, string $sig, array $keys, array $site): array {
        $fail = static fn(string $why): array => ['ok' => false, 'error' => $why, 'data' => []];
        $key = self::getRawBytes($keys['primary'] ?? '', 32);
        if ($key === '' || !self::isSignValid(self::NOTE.$body, self::getRawBytes($sig, 64), $key)) return $fail('sign');
        $data = self::getJsonData($body);
        if (!self::isNote($data)) return $fail('format');
        if ($data['sid'] !== '' && $data['sid'] !== ($site['sid'] ?? '')) return $fail('sid');
        if (!self::isVersionIn((string)($site['version'] ?? ''), $data['ver'])) return $fail('version');
        if ($data['until'] > 0 && intval($site['now'] ?? time()) > $data['until']) return $fail('expired');
        return ['ok' => true, 'error' => '', 'data' => $data];
    }

    # The keys after the statements of $list in their order: each signed by the recovery key in force with a higher kseq, a new recovery key also by the primary
    public static function getKeyChain(array $keys, array $list): array {
        foreach ($list as $one) {
            $body = (string)($one['body'] ?? '');
            $msg = self::KEYCHANGE.$body;
            $rkey = self::getRawBytes($keys['recovery'] ?? '', 32);
            if (!self::isSignValid($msg, self::getRawBytes($one['sig'] ?? '', 64), $rkey)) return ['ok' => false, 'error' => 'sign', 'keys' => $keys];
            $data = self::getJsonData($body);
            $want = ['kseq', 'primary', 'recovery', 'time', 'type'];
            $good = is_array($data) && self::getKeyNames($data) === $want && $data['type'] === 'keychange' && is_int($data['kseq']) && is_int($data['time'])
                && self::getRawBytes($data['primary'], 32) !== '' && self::getRawBytes($data['recovery'], 32) !== '' && $data['primary'] !== $data['recovery'];
            if (!$good) return ['ok' => false, 'error' => 'format', 'keys' => $keys];
            if ($data['kseq'] <= intval($keys['kseq'] ?? 0)) return ['ok' => false, 'error' => 'kseq', 'keys' => $keys];
            $both = $data['recovery'] === $keys['recovery'] || self::isSignValid($msg, self::getRawBytes($one['psig'] ?? '', 64), self::getRawBytes($keys['primary'] ?? '', 32));
            if (!$both) return ['ok' => false, 'error' => 'sign', 'keys' => $keys];
            $gone = (array)($keys['revoked'] ?? []);
            if ($data['primary'] !== $keys['primary']) $gone[] = $keys['primary'];
            if ($data['recovery'] !== $keys['recovery']) $gone[] = $keys['recovery'];
            if (in_array($data['primary'], $gone, true) || in_array($data['recovery'], $gone, true)) return ['ok' => false, 'error' => 'revoked', 'keys' => $keys];
            $keys = ['kseq' => $data['kseq'], 'primary' => $data['primary'], 'recovery' => $data['recovery'], 'revoked' => array_values(array_unique($gone))];
        }
        return ['ok' => true, 'error' => '', 'keys' => $keys];
    }

    # The key statements kept in a folder as <kseq>.json with .sig and, when the recovery key changes, .psig, in the order of kseq
    public static function getKeyList(string $dir): array {
        $list = [];
        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $num = basename($file, '.json');
            if (!ctype_digit($num)) continue;
            $base = $dir.'/'.$num;
            $list[intval($num)] = ['body' => (string)file_get_contents($file), 'sig' => is_file($base.'.sig') ? trim((string)file_get_contents($base.'.sig')) : '',
                'psig' => is_file($base.'.psig') ? trim((string)file_get_contents($base.'.psig')) : ''];
        }
        ksort($list);
        return array_values($list);
    }

    # The keys the site trusts: client/keys.json once a statement was applied, the factory keys before; a damaged file trusts no key at all
    public static function getKeyFile(): array {
        $file = UPDATE_DIR.'/client/keys.json';
        if (!is_file($file)) return ['kseq' => 0, 'primary' => self::PRIMARY, 'recovery' => self::RECOVERY, 'revoked' => []];
        $data = self::getJsonData((string)file_get_contents($file));
        $good = is_array($data) && is_int($data['kseq'] ?? null) && self::getRawBytes($data['primary'] ?? '', 32) !== '' && self::getRawBytes($data['recovery'] ?? '', 32) !== ''
            && is_array($data['revoked'] ?? null);
        return $good ? ['kseq' => $data['kseq'], 'primary' => $data['primary'], 'recovery' => $data['recovery'], 'revoked' => $data['revoked']]
            : ['kseq' => 0, 'primary' => '', 'recovery' => '', 'revoked' => []];
    }

    # Write the keys the site trusts; only the result of getKeyChain() is written here
    public static function setKeyFile(array $keys): bool {
        return self::setJsonFile('keys.json', $keys);
    }

    # Whether a path may stand in a package: relative with forward slashes, no empty or dot segment, below a shipped folder or a plain top file, never below client/ or master/
    public static function isPackagePath(string $path): bool {
        $path = str_ends_with($path, '/') ? substr($path, 0, -1) : $path;
        if (preg_match('#^[A-Za-z0-9._ +@()-]+(/[A-Za-z0-9._ +@()-]+)*$#D', $path) !== 1) return false;
        foreach (explode('/', $path) as $part) if ($part === '.' || $part === '..' || trim($part) !== $part) return false;
        if (preg_match('#^storage/update/(client|master)(/|$)#', $path) === 1) return false;
        return !str_contains($path, '/') || in_array(strstr($path, '/', true), self::DIRS, true);
    }

    # Whether a shipped path is a file of the site that a package only places when it is missing: a settings file of config/ and public/robots.txt
    public static function isSiteFile(string $path): bool {
        if ($path === 'public/robots.txt') return true;
        return preg_match('#^config/[a-z0-9_]+\.php$#D', $path) === 1 && !in_array(basename($path), ['system.php', 'header.php', 'chmod.php', 'local.php'], true);
    }

    # The state of this installation in client/state.json, every field of STATE present; a missing or damaged file is an empty state
    public static function getState(): array {
        $file = UPDATE_DIR.'/client/state.json';
        $data = is_file($file) ? self::getJsonData((string)file_get_contents($file)) : null;
        return is_array($data) ? array_intersect_key(array_replace(self::STATE, $data), self::STATE) : self::STATE;
    }

    # Write the state of this installation; the flag new of getSite() is no part of it
    public static function setState(array $state): bool {
        return self::setJsonFile('state.json', array_intersect_key($state, self::STATE));
    }

    # The state with an id of this address: $fresh, no state or one of another address, a copied site, starts over with a new id, marked new for the caller
    public static function getSite(string $home, bool $fresh = false): array {
        $state = self::getState();
        $hash = hash('sha256', rtrim($home, '/'));
        if (!$fresh && preg_match('#^[0-9a-f]{32}$#D', (string)$state['sid']) === 1 && hash_equals($hash, (string)$state['home'])) return $state;
        $state = array_replace(self::STATE, ['sid' => bin2hex(random_bytes(16)), 'home' => $hash]);
        return self::setState($state) ? $state + ['new' => true] : $state;
    }

    # The daily minute of a site from its id as the cron line of updatecheck, so the sites of one host never reach the master at once
    public static function getCheckTime(string $sid): string {
        $min = crc32($sid) % 1440;
        return ($min % 60).' '.intdiv($min, 60).' * * *';
    }

    # The domain a site reports: the lowercase host of its address in punycode, never localhost, an IP or a zone of stands such as .local, .test and .localhost
    public static function getHostName(string $url): string {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (preg_match('#[^\x00-\x7f]#', $host) === 1) $host = function_exists('idn_to_ascii') ? (string)idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) : '';
        if ($host === '' || strlen($host) > 253 || filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false) return '';
        if (preg_match('#^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]([a-z0-9-]{0,61}[a-z0-9])?$#D', $host) !== 1) return '';
        return (preg_match('#\.(local|test|localhost|example|invalid)$#D', $host) === 1) ? '' : $host;
    }

    # The extensions an update relies on, as the bits of the check: 1 sodium, 2 zip, 4 phar with zlib
    public static function getExtBits(): int {
        return (extension_loaded('sodium') ? 1 : 0) | (class_exists('ZipArchive') ? 2 : 0) | ((extension_loaded('phar') && extension_loaded('zlib')) ? 4 : 0);
    }

    # The sizes of the PHP and SQL error journals, the marks from which the next check counts the new errors of either
    public static function getLogSizes(): array {
        clearstatcache();
        $out = [];
        foreach (['php' => 'error_php.log', 'sql' => 'error_sql.log'] as $key => $name) $out[$key] = is_file(LOGS_DIR.'/'.$name) ? (int)filesize(LOGS_DIR.'/'.$name) : 0;
        return $out;
    }

    # The entries a journal gained after byte $from, one per line, read in chunks; a journal shorter than its mark was rotated and counts from its start
    public static function getLogCount(string $name, int $from): int {
        $file = LOGS_DIR.'/'.$name;
        $link = is_file($file) ? fopen($file, 'rb') : false;
        if ($link === false) return 0;
        fseek($link, ($from > (int)filesize($file)) ? 0 : $from);
        $num = 0;
        while (!feof($link) && $num < 1000000) $num += substr_count((string)fread($link, 65536), "\n");
        fclose($link);
        return min($num, 1000000);
    }

    # One GET to the master: HTTPS only, no redirect followed, the peer verified, the answer cut at LIMIT; its status, its body and why it failed
    public static function getRemote(string $path, array $query): array {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 15, 'follow_location' => 0, 'ignore_errors' => true,
            'header' => 'User-Agent: SLAED/'.SL_VERSION."\r\nAccept: application/json\r\n"], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        set_error_handler(static fn(): bool => true);
        $link = fopen(self::HOST.$path.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986), 'rb', false, $ctx);
        restore_error_handler();
        if ($link === false) return ['code' => 0, 'body' => '', 'error' => 'master'];
        $body = (string)stream_get_contents($link, self::LIMIT + 1);
        $head = stream_get_meta_data($link)['wrapper_data'] ?? [];
        fclose($link);
        $code = (is_array($head) && preg_match('#^HTTP/\S+\s+(\d{3})#', (string)($head[0] ?? ''), $hit) === 1) ? (int)$hit[1] : 0;
        if (strlen($body) > self::LIMIT) return ['code' => $code, 'body' => '', 'error' => 'size'];
        return ['code' => $code, 'body' => $body, 'error' => ($code === 200) ? '' : 'master'];
    }

    # Ask the master once with what master/check lists, apply its answer and keep the result in the state; $fetch is the transport seam for tests
    public static function addCheck(Database $db, array $conf, array $state, ?callable $fetch = null): array {
        $sets = (array)($conf['update']['check'] ?? []);
        $chan = (($sets['channel'] ?? '') === 'beta') ? 'beta' : 'stable';
        $keys = self::getKeyFile();
        $sizes = self::getLogSizes();
        $logs = (array)$state['logs'];
        $res = $db->getSqlQuery('SELECT VERSION()');
        $dbs = (string)(($res ? $db->getSqlRow($res) : [])[0] ?? '');
        $query = ['sid' => $state['sid'], 'v' => SL_VERSION, 'ch' => $chan, 'kseq' => $keys['kseq'], 'php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION,
            'db' => (stripos($dbs, 'mariadb') !== false) ? 'mariadb' : 'mysql', 'ext' => self::getExtBits(), 'ep' => self::getLogCount('error_php.log', intval($logs['php'] ?? 0)),
            'es' => self::getLogCount('error_sql.log', intval($logs['sql'] ?? 0))];
        if (preg_match('#\d{1,5}\.\d{1,5}\.\d{1,5}#', $dbs, $hit) === 1) $query['dbv'] = $hit[0];
        $host = (intval($sets['upsite'] ?? 1) === 1) ? self::getHostName((string)($conf['homeurl'] ?? '')) : '';
        if ($host !== '') $query['host'] = $host;
        if (self::isVersion($state['fail']['ver'] ?? null)) $query += ['fv' => $state['fail']['ver'], 'fs' => intval($state['fail']['step'] ?? 0)];
        $got = ($fetch ?? [self::class, 'getRemote'])('/api/v1/master/check', $query);
        $data = ($got['error'] === '') ? self::getJsonData((string)$got['body']) : null;
        $state['last'] = time();
        if (!is_array($data) || ($data['ok'] ?? false) !== true || !is_array($data['data'] ?? null)) {
            $state['error'] = ($got['error'] !== '') ? $got['error'] : 'master';
            self::setState($state);
            return ['ok' => false, 'error' => $state['error'], 'found' => (string)($state['found']['version'] ?? '')];
        }
        $state = self::setCheckReply($data['data'], array_replace($state, ['error' => '', 'logs' => $sizes, 'fail' => []]), $keys, $chan);
        $done = self::setState($state);
        return ['ok' => $done && $state['error'] === '', 'error' => $done ? $state['error'] : 'write', 'found' => (string)($state['found']['version'] ?? '')];
    }

    # Apply an answer of master/check: the key statements in order while they verify, the manifest offered or none, which drops the release found, and the notes for this site
    public static function setCheckReply(array $data, array $state, array $keys, string $chan): array {
        $now = time();
        $made = false;
        foreach (is_array($data['keys'] ?? null) ? $data['keys'] : [] as $one) {
            $one = is_array($one) ? $one : [];
            $res = self::getKeyChain($keys, [['body' => self::getRawBytes($one['body'] ?? ''), 'sig' => $one['sig'] ?? '', 'psig' => $one['psig'] ?? '']]);
            if (!$res['ok']) break;
            [$keys, $made] = [$res['keys'], true];
        }
        if ($made && !self::setKeyFile($keys)) $state['error'] = 'write';
        $man = is_array($data['manifest'] ?? null) ? $data['manifest'] : null;
        $state['found'] = [];
        if ($man !== null) {
            $site = ['channel' => $chan, 'version' => SL_VERSION, 'seen' => (array)$state['seen'], 'now' => $now];
            $res = self::getManifest(self::getRawBytes($man['body'] ?? ''), is_string($man['sig'] ?? null) ? $man['sig'] : '', $keys, $site);
            $one = $res['data'];
            if ($res['ok']) {
                $state['found'] = ['version' => $one['version'], 'channel' => $one['channel'], 'security' => $one['security'], 'php' => $one['php'], 'min' => $one['min'],
                    'expires' => $one['expires'], 'body' => $man['body'], 'sig' => $man['sig']];
                $state['seen'][$one['channel']] = max(intval($state['seen'][$one['channel']] ?? 0), $one['time']);
            } elseif ($res['error'] !== 'version') {
                $state['error'] = $res['error'];
            }
        }
        $notes = [];
        $site = ['sid' => $state['sid'], 'version' => SL_VERSION, 'now' => $now];
        foreach (is_array($data['notes'] ?? null) ? $data['notes'] : [] as $one) {
            $one = is_array($one) ? $one : [];
            $res = self::getNote(self::getRawBytes($one['body'] ?? ''), is_string($one['sig'] ?? null) ? $one['sig'] : '', $keys, $site);
            if ($res['ok']) $notes[$res['data']['id']] = $res['data'];
        }
        $hide = array_values(array_intersect(self::getNoteFile()['hide'], array_keys($notes)));
        if (!self::setJsonFile('notes.json', ['list' => array_values($notes), 'hide' => $hide])) $state['error'] = 'write';
        return $state;
    }

    # The notes this site accepted at its last check and the ids its administrators hid
    public static function getNoteFile(): array {
        $file = UPDATE_DIR.'/client/notes.json';
        $data = is_file($file) ? self::getJsonData((string)file_get_contents($file)) : null;
        $list = is_array($data['list'] ?? null) ? array_values(array_filter($data['list'], [self::class, 'isNote'])) : [];
        return ['list' => $list, 'hide' => is_array($data['hide'] ?? null) ? array_values(array_filter($data['hide'], 'is_int')) : []];
    }

    # The notes the panel shows: accepted, neither hidden nor expired, the newest first
    public static function getNoteList(): array {
        $data = self::getNoteFile();
        $list = array_filter($data['list'], static fn(array $one): bool => !in_array($one['id'], $data['hide'], true) && ($one['until'] === 0 || $one['until'] >= time()));
        usort($list, static fn(array $one, array $two): int => $two['id'] <=> $one['id']);
        return $list;
    }

    # Hide one accepted note on this site; the master never learns of it
    public static function setNoteHide(int $id): bool {
        $data = self::getNoteFile();
        if (!in_array($id, array_column($data['list'], 'id'), true)) return false;
        $data['hide'] = array_values(array_unique(array_merge($data['hide'], [$id])));
        return self::setJsonFile('notes.json', $data);
    }

    # The versions whose database steps wait after the mark: each step file of step/ up to the code, then the code itself, marked even without a file
    public static function getStepList(string $mark): array {
        $vers = [SL_VERSION];
        foreach (array_merge(glob(UPDATE_DIR.'/step/*.sql') ?: [], glob(UPDATE_DIR.'/step/*.php') ?: []) as $file) $vers[] = pathinfo($file, PATHINFO_FILENAME);
        $vers = array_unique(array_filter($vers, static fn(string $v): bool => self::isVersion($v) && ($mark === '' || version_compare($v, $mark, '>'))
            && version_compare($v, SL_VERSION, '<=')));
        usort($vers, 'version_compare');
        $out = [];
        foreach ($vers as $ver) $out[$ver] = ['sql' => is_file(UPDATE_DIR.'/step/'.$ver.'.sql'), 'php' => is_file(UPDATE_DIR.'/step/'.$ver.'.php')];
        return $out;
    }

    # Run the waiting steps in the order of versions, the SQL file and then the PHP file that answers true, and mark each version in config/update.php as it is on disk
    public static function addSteps(Database $db, array $conf): array {
        require_once BASE_DIR.'/core/gate.php';
        require_once BASE_DIR.'/core/admin.php';
        $file = (static fn(string $path): mixed => is_file($path) ? include $path : [])(CONFIG_DIR.'/update.php');
        $data = is_array($file['update'] ?? null) ? $file['update'] : [];
        $base = (array)($conf['db'] ?? []);
        $from = ['{prefix}', '{engine}', '{charset}', '{collate}'];
        $to = [(string)($base['prefix'] ?? ''), (string)($base['engine'] ?? 'InnoDB'), (string)($base['charset'] ?? 'utf8mb4'), (string)($base['collate'] ?? '')];
        foreach (self::getStepList((string)getStepMark(CONFIG_DIR)) as $ver => $has) {
            $list = $has['sql'] ? getSqlbatch((string)file_get_contents(UPDATE_DIR.'/step/'.$ver.'.sql')) : ['error' => '', 'statements' => []];
            if ($list['error'] !== '') return ['ok' => false, 'ver' => $ver, 'error' => $ver.'.sql: '.$list['error']];
            foreach ($list['statements'] as $sql) {
                if ($db->getSqlQuery(str_replace($from, $to, $sql)) === false) return ['ok' => false, 'ver' => $ver, 'error' => $db->getSqlError()['message']];
            }
            if ($has['php'] && (static fn(string $path, Database $db): mixed => include $path)(UPDATE_DIR.'/step/'.$ver.'.php', $db) !== true) {
                return ['ok' => false, 'ver' => $ver, 'error' => $ver.'.php'];
            }
            $data['steps'][$ver] = date('Y-m-d H:i:s');
            if (!setConfigFile('update.php', $data)) return ['ok' => false, 'ver' => $ver, 'error' => 'config/update.php'];
        }
        return ['ok' => true, 'ver' => '', 'error' => ''];
    }

    # Write one JSON file of client/ by one rename, so a reader never sees half a file
    private static function setJsonFile(string $name, array $data): bool {
        $dir = UPDATE_DIR.'/client';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
        $temp = $dir.'/'.$name.'.'.bin2hex(random_bytes(6));
        if (file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) return false;
        if (rename($temp, $dir.'/'.$name)) return true;
        unlink($temp);
        return false;
    }

    # The decoded JSON of signed bytes, the same bytes that were verified and never re-encoded, or null when they are no JSON
    private static function getJsonData(string $body): mixed {
        try {
            return json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    # The sorted keys of an array, so a format is held to exactly its fields whatever their order
    private static function getKeyNames(array $data): array {
        $keys = array_keys($data);
        sort($keys);
        return $keys;
    }

    # Whether a decoded manifest has exactly the fields of its format, each of its type; delta is null for a release without one
    private static function isManifest(mixed $data): bool {
        $want = ['channel', 'commit', 'delta', 'expires', 'full', 'kid', 'min', 'php', 'security', 'time', 'type', 'version'];
        if (!is_array($data) || self::getKeyNames($data) !== $want || $data['type'] !== 'release' || !self::isVersion($data['version'])) return false;
        $pack = static fn(mixed $one, array $keys): bool => is_array($one) && self::getKeyNames($one) === $keys && is_int($one['size']) && $one['size'] > 0
            && is_int($one['usize']) && $one['usize'] > 0 && is_string($one['sha256']) && preg_match('#^[0-9a-f]{64}$#D', $one['sha256']) === 1;
        if (!$pack($data['full'], ['sha256', 'size', 'usize'])) return false;
        if ($data['delta'] !== null && (!$pack($data['delta'], ['from', 'sha256', 'size', 'usize']) || !self::isVersion($data['delta']['from'])
            || !version_compare($data['delta']['from'], $data['version'], '<'))) return false;
        return in_array($data['channel'], ['stable', 'beta'], true) && self::isVersion($data['min']) && version_compare($data['min'], $data['version'], '<=')
            && is_string($data['php']) && preg_match('#^\d{1,2}\.\d{1,2}(\.\d{1,3})?$#D', $data['php']) === 1 && is_bool($data['security'])
            && is_string($data['commit']) && preg_match('#^([0-9a-f]{40}|[0-9a-f]{64})$#D', $data['commit']) === 1 && is_int($data['time']) && is_int($data['expires'])
            && $data['expires'] > $data['time'] && is_string($data['kid']) && preg_match('#^[0-9a-f]{16}$#D', $data['kid']) === 1;
    }

    # Whether a decoded note has exactly the fields of its format: plain text of at most 1000 characters per language, a range, a sid or none, a deadline or 0
    public static function isNote(mixed $data): bool {
        $want = ['en', 'id', 'level', 'ru', 'sid', 'type', 'until', 'ver'];
        if (!is_array($data) || self::getKeyNames($data) !== $want || $data['type'] !== 'note') return false;
        foreach (['ru', 'en'] as $lang) {
            if (!is_string($data[$lang]) || $data[$lang] === '' || !mb_check_encoding($data[$lang], 'UTF-8') || mb_strlen($data[$lang]) > 1000) return false;
        }
        return is_int($data['id']) && $data['id'] > 0 && in_array($data['level'], ['info', 'warn', 'danger'], true) && is_string($data['ver'])
            && preg_match(self::RANGE, $data['ver']) === 1 && is_string($data['sid']) && preg_match('#^([A-Za-z0-9]{32})?$#D', $data['sid']) === 1
            && is_int($data['until']) && $data['until'] >= 0;
    }
}
