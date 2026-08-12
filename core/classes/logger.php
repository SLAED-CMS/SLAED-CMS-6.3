<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Logger {
    # The names whose value is a secret; the rule is the substring, checked case-insensitively, so every password, token, key and one-time code field in the codebase is covered
    private const MASKKEYS = '/pass|pwd|secret|token|key|code/i';
    protected static bool $lock = false;

    # Add PHP log entry
    public static function addPhp(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('php', $levl, $mesg, $ctx);
    }

    # Add SQL log entry
    public static function addSql(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('sql', $levl, $mesg, $ctx);
    }

    # Add file log entry
    public static function addFile(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('file', $levl, $mesg, $ctx);
    }

    # Add site log entry
    public static function addSite(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('site', $levl, $mesg, $ctx);
    }

    # Add warning log entry
    public static function addWarn(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('warn', $levl, $mesg, $ctx);
    }

    # Add hack log entry
    public static function addHack(string $levl, string $mesg, array $ctx = []): bool {
        return self::addEntry('hack', $levl, $mesg, $ctx);
    }

    # Add structured log entry
    public static function addEntry(string $chan, string $levl, string $mesg, array $ctx = []): bool {
        $file = self::getFile($chan);
        if ($file === '') return false;
        if (self::$lock) {
            error_log('[LOG] Recursive call prevented: '.$mesg);
            return false;
        }
        self::$lock = true;
        $chan = self::getChan($chan);
        $levl = self::getLevel($levl);
        $filev = isset($ctx['file']) ? (string)$ctx['file'] : '';
        $linev = isset($ctx['line']) ? (string)$ctx['line'] : '';
        $ctx = self::filterArray($ctx);
        foreach (self::getKeys() as $key) unset($ctx[$key]);
        $row = array_merge([
            'ts' => date('c'),
            'level' => $levl,
            'chan' => $chan,
            'type' => $chan,
            'msg' => self::filterText($mesg, 2048),
            'fingerprint' => self::getFinger($chan, $filev, $linev, $mesg),
        ], self::getRequest(), self::getContext(), self::getMemory(), $ctx);
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $ok = $line !== false && self::addLine($file, $line);
        if (!$ok) error_log('[LOG] Write failed: '.$file.' | '.$mesg);
        self::$lock = false;
        return $ok;
    }

    # Get normalized channel
    protected static function getChan(string $chan): string {
        $list = ['php', 'sql', 'file', 'site', 'warn', 'hack'];
        return in_array($chan, $list, true) ? $chan : '';
    }

    # Get log file by channel
    protected static function getFile(string $chan): string {
        if (!defined('LOGS_DIR')) return '';
        $list = [
            'php' => 'error_php.log',
            'sql' => 'error_sql.log',
            'file' => 'error_file.log',
            'site' => 'error_site.log',
            'warn' => 'warn.log',
            'hack' => 'hack.log',
        ];
        return isset($list[$chan]) ? rtrim(LOGS_DIR, '\\/').'/'.$list[$chan] : '';
    }

    # Get normalized level
    protected static function getLevel(string $levl): string {
        $list = ['debug', 'info', 'notice', 'warning', 'error', 'critical'];
        return in_array($levl, $list, true) ? $levl : 'error';
    }

    # Get reserved log keys
    protected static function getKeys(): array {
        return ['ts', 'level', 'chan', 'type', 'msg', 'fingerprint', 'req_id', 'ip', 'method', 'url', 'referer', 'ua', 'query', 'post', 'cookie_keys', 'session_keys', 'mem_mb', 'mem_peak_mb'];
    }

    # Get bounded request context
    # Request data is masked by key name before it is bounded, so a value named after a password, a secret, a token or a code never reaches a log line whatever raised the entry
    # The name decides, not the channel or the module: every posted field of every request passes here, and a false positive such as a captcha code loses nothing worth reading
    protected static function getContext(): array {
        $data = ['query' => $_GET ?? [], 'post' => $_POST ?? []];
        foreach ($data as $part => $rows) {
            foreach (array_keys($rows) as $key) {
                if (preg_match(self::MASKKEYS, (string)$key)) $rows[$key] = '[masked]';
            }
            $data[$part] = self::filterArray($rows);
        }
        $cook = array_keys($_COOKIE ?? []);
        $sess = (session_status() === PHP_SESSION_ACTIVE) ? array_keys($_SESSION ?? []) : [];
        $row = [
            'query' => $data['query'] ?: new stdClass(),
            'post' => $data['post'] ?: new stdClass(),
            'cookie_keys' => array_slice($cook, 0, 50),
            'session_keys' => array_slice($sess, 0, 50),
        ];
        if (count($cook) > 50) $row['cookie_keys_truncated'] = true;
        if (count($sess) > 50) $row['session_keys_truncated'] = true;
        return $row;
    }

    # Get common request fields
    # The address is masked like the posted data: a one-time code, an OAuth callback or a reset link carries its secret in the query, and the referer carries the previous one
    protected static function getRequest(): array {
        $ref = function_exists('getReferer') ? (string)getReferer() : (string)($_SERVER['HTTP_REFERER'] ?? '');
        $ip = function_exists('getIp') ? (string)getIp() : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return [
            'req_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? $_SERVER['UNIQUE_ID'] ?? null,
            'ip' => $ip,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'url' => self::filterText(self::filterQuery($_SERVER['REQUEST_URI'] ?? (string)getenv('REQUEST_URI')), 2048) ?: null,
            'referer' => self::filterText(self::filterQuery($ref), 2048) ?: null,
            'ua' => self::filterText($_SERVER['HTTP_USER_AGENT'] ?? '', 512) ?: null,
        ];
    }

    # Get memory metrics
    protected static function getMemory(): array {
        return [
            'mem_mb' => round(memory_get_usage(true) / 1048576, 2),
            'mem_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
    }

    # Get stable event fingerprint
    protected static function getFinger(string $chan, string $file, string $line, string $mesg): string {
        $norm = preg_replace(['/\d+/', '/\s+/'], ['#', ' '], substr($mesg, 0, 200));
        return substr(sha1($chan.'|'.$file.'|'.$line.'|'.$norm), 0, 8);
    }

    # Filter bounded text field
    protected static function filterText(mixed $text, int $max = 1024): string {
        return substr(str_replace(["\r", "\n", "\0"], ' ', trim((string)$text)), 0, $max);
    }

    # Mask the query part of one address by key name, leaving the path and every other pair in raw form; not named filterUrl, because Parser has a link policy by that name
    protected static function filterQuery(mixed $url): string {
        $url = (string)$url;
        $pos = strpos($url, '?');
        if ($pos === false) return $url;
        $mask = static fn(array $pair): string => preg_match(self::MASKKEYS, urldecode($pair[2])) ? $pair[1].$pair[2].'=[masked]' : $pair[0];
        $query = substr($url, $pos + 1);
        return substr($url, 0, $pos + 1).(preg_replace_callback('/(^|&)([^&=]+)=([^&]*)/', $mask, $query) ?? $query);
    }

    # Filter bounded array fields
    protected static function filterArray(array $data): array {
        if (count($data) > 50) {
            $data = array_slice($data, 0, 50, true);
            $data['*_truncated'] = true;
        }
        foreach ($data as $key => $val) {
            if (is_string($val)) {
                $data[$key] = ($key === 'sql') ? $val : self::filterText($val, 1024);
            } elseif (is_array($val)) {
                $data[$key] = '[array:'.count($val).']';
            } elseif (is_object($val)) {
                $data[$key] = '[object:'.get_class($val).']';
            }
        }
        return $data;
    }

    # Add line with rotation
    protected static function addLine(string $file, string $line): bool {
        global $conf;
        $max = (int)($conf['security']['log_size'] ?? 10485760);
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) return false;
        clearstatcache(true, $file);
        $size = is_file($file) ? filesize($file) : 0;
        if ($size !== false && $size >= $max) {
            $guard = $file.'.rotating';
            if (file_put_contents($guard, '1', LOCK_EX) !== false) {
                $safe = pathinfo($file, PATHINFO_FILENAME).'_'.date('Y-m-d_H-i-s');
                if (function_exists('addCompress')) addCompress(dirname($file), $file, $safe, 'auto', true, true);
                if (is_file($guard)) unlink($guard);
            }
        }
        return file_put_contents($file, $line."\n", FILE_APPEND | LOCK_EX) !== false;
    }
}
