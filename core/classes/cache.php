<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Cache {
    private const TYPES = ['html', 'assets', 'data'];
    private const EXTS = ['html', 'css', 'js', 'json'];
    private const SWEEP = ['html', 'assets', 'data', 'locks'];
    private const DROP = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid', 'yclid', 'fbclid', '_openstat'];
    private static bool $bumped = false;
    private static $hold = null;

    # Build a validated cache path inside storage/cache/pages and reject anything outside the whitelist
    public static function getPath(string $type, string $hash, string $ext): string {
        if (!in_array($type, self::TYPES, true)) return '';
        if (!in_array($ext, self::EXTS, true)) return '';
        if (!preg_match('/^[a-f0-9]{40}$|^[a-f0-9]{64}$/', $hash)) return '';
        return CACHE_DIR.'/pages/'.$type.'/'.$hash.'.'.$ext;
    }

    # Build a stable cache hash from ordered identity parts
    public static function getHash(array $parts): string {
        return sha1(implode('|', $parts));
    }

    # Validate the query part of one URL against a per-route key => value regex allowlist; tracking keys are dropped, any unknown, duplicate, or malformed key returns null
    public static function getQueryVars(string $url, array $allow): ?array {
        $cut = strpos($url, '?');
        if ($cut === false) return [];
        $query = substr($url, $cut + 1);
        if ($query === '') return [];
        $vars = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') continue;
            $eq = strpos($pair, '=');
            $key = urldecode(($eq === false) ? $pair : substr($pair, 0, $eq));
            if (in_array($key, self::DROP, true)) continue;
            if (!isset($allow[$key]) || isset($vars[$key])) return null;
            $val = ($eq === false) ? '' : urldecode(substr($pair, $eq + 1));
            if (!preg_match($allow[$key], $val)) return null;
            $vars[$key] = $val;
        }
        return $vars;
    }

    # Report whether a cache file exists, is not empty, and is still within the TTL window
    public static function isFresh(string $file, int $ttl): bool {
        if (!is_file($file)) return false;
        if (filesize($file) === 0) return false;
        return (time() - $ttl) < filemtime($file);
    }

    # Read a cache file and return its body or an empty string when it cannot be read
    public static function getBody(string $file): string {
        if (!is_file($file) || !is_readable($file)) return '';
        $body = file_get_contents($file);
        return ($body !== false) ? $body : '';
    }

    # Write a cache file atomically through a temp file, exclusive lock, and rename
    public static function setBody(string $file, string $body): bool {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
        $tmp = tempnam($dir, basename($file).'.');
        if ($tmp === false) return false;
        if (file_put_contents($tmp, $body, LOCK_EX) === false) {
            @unlink($tmp);
            return false;
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        return true;
    }

    # Remove every cached file under storage/cache, keeping protected markers and the directory tree
    public static function deleteAll(): int {
        if (!is_dir(CACHE_DIR)) return 0;
        $num = 0;
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $name = $file->getFilename();
            if ($name === '.htaccess' || $name === 'index.html') continue;
            if (unlink($file->getPathname())) $num++;
        }
        return $num;
    }

    # Remove cached files of one type older than the retention window, keeping protected markers
    public static function deleteStale(string $type, int $ttl): int {
        if (!in_array($type, self::SWEEP, true) || $ttl < 1) return 0;
        $dir = CACHE_DIR.'/pages/'.$type;
        if (!is_dir($dir)) return 0;
        $num = 0;
        $edge = time() - $ttl;
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..' || $name === '.htaccess' || $name === 'index.html') continue;
            $file = $dir.'/'.$name;
            if (!is_file($file)) continue;
            $when = filemtime($file);
            if ($when !== false && $when < $edge && unlink($file)) $num++;
        }
        return $num;
    }

    # Write the page sidecar describing one stored body: body hash plus dynamic flag, published after the body so a mismatched pair fails closed
    public static function setMeta(string $file, string $body, bool $dyn): bool {
        return self::setBody($file.'.json', json_encode(['sha1' => sha1($body), 'dyn' => $dyn ? 1 : 0]));
    }

    # Read and validate the page sidecar against the actual body; a missing, corrupt, or mismatched sidecar reports dynamic so serving fails closed to no-store
    public static function getMeta(string $file, string $body): array {
        $data = json_decode(self::getBody($file.'.json'), true);
        if (!is_array($data) || !isset($data['sha1'], $data['dyn']) || !hash_equals((string)$data['sha1'], sha1($body))) return ['dyn' => true, 'valid' => false];
        return ['dyn' => (bool)$data['dyn'], 'valid' => true];
    }

    # Recursively remove cached files under one directory older than the retention window, keeping protected markers and the directory tree; evicted live entries only trigger a cheap rebuild
    public static function deleteStaleTree(string $dir, int $ttl): int {
        if ($ttl < 1 || !is_dir($dir)) return 0;
        $num = 0;
        $edge = time() - $ttl;
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $name = $file->getFilename();
            if ($name === '.htaccess' || $name === 'index.html') continue;
            if ($file->getMTime() < $edge && unlink($file->getPathname())) $num++;
        }
        return $num;
    }

    # Read the current page-cache generation counter, returning zero when it is missing
    public static function getEpoch(): int {
        $file = CACHE_DIR.'/pages/epoch';
        if (!is_file($file)) return 0;
        $val = file_get_contents($file);
        return ($val !== false) ? (int)$val : 0;
    }

    # Bump the page-cache generation counter once per request through an exclusive lock to invalidate every cached page
    public static function addEpoch(): void {
        if (self::$bumped) return;
        self::$bumped = true;
        $dir = CACHE_DIR.'/pages';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return;
        $hand = fopen($dir.'/epoch', 'c+');
        if ($hand === false) return;
        if (flock($hand, LOCK_EX)) {
            $val = (int)stream_get_contents($hand);
            ftruncate($hand, 0);
            rewind($hand);
            fwrite($hand, (string)($val + 1));
            fflush($hand);
            flock($hand, LOCK_UN);
        }
        fclose($hand);
    }

    # Try to grab the single-flight rebuild lock for one page; true means rebuild here, false means another worker already rebuilds it
    public static function getRebuildLock(string $hash): bool {
        if (!preg_match('/^[a-f0-9]{40}$|^[a-f0-9]{64}$/', $hash)) return true;
        $dir = CACHE_DIR.'/pages/locks';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return true;
        $path = $dir.'/'.$hash;
        $hand = fopen($path, 'c');
        if ($hand === false) return true;
        if (!flock($hand, LOCK_EX | LOCK_NB)) {
            fclose($hand);
            return false;
        }
        touch($path);
        self::$hold = $hand;
        register_shutdown_function([self::class, 'setRebuildFree']);
        return true;
    }

    # Release the single-flight rebuild lock held by this request
    public static function setRebuildFree(): void {
        if (self::$hold === null) return;
        flock(self::$hold, LOCK_UN);
        fclose(self::$hold);
        self::$hold = null;
    }

    # Emit browser cache, content type, and security headers for public or no-store responses
    public static function setHeaders(bool $public, int $days = 0, string $type = 'text/html', int $mtime = 0, bool $immutable = false): void {
        $ctype = ($type === 'text/html') ? $type.'; charset='._CHARSET : $type;
        header('Content-Type: '.$ctype);
        if ($public) {
            $max = $immutable ? 31536000 : $days * 86400;
            header('Cache-Control: public, max-age='.$max.($immutable ? ', immutable' : ''));
            header('Expires: '.gmdate('D, d M Y H:i:s', time() + $max).' GMT');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
        }
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', ($mtime > 0) ? $mtime : time()).' GMT');
        header('X-Powered-By: SLAED CMS');
        header('X-Powered-CMS: SLAED CMS');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    # Send a 304 status and report a match when the client cached copy is not older than the stored file
    public static function checkNotModified(int $mtime): bool {
        if ($mtime <= 0) return false;
        $since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($since === '') return false;
        $stamp = strtotime($since);
        if ($stamp === false || $stamp < $mtime) return false;
        http_response_code(304);
        return true;
    }
}
