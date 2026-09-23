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
    # How long a generated static document may sit in a browser cache; the OpenSearch and XSL descriptions change with a release, not with a setting, so no field governs them
    public const STATICDAYS = 7;
    # The name of one write-guard marker: 32 lowercase hex characters made by this class, never a value of the request
    private const GUARD = '/^[a-f0-9]{32}\.lock$/D';
    private static bool $bumped = false;
    private static $hold = null;
    private static array $guards = [];
    private static ?int $until = null;

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
    # A stored file that cannot be read is the one cache failure nothing else reports: the caller renders the page again and the guard prevents the warning that would name it
    # It is recorded once per process, because a site whose cache directory lost its permissions would otherwise pay a locked append for every read of every page
    public static function getBody(string $file): string {
        static $told = false;
        if (!is_file($file)) return '';
        if (!is_readable($file)) {
            if (!$told) Logger::addFile('error', 'Cache file is not readable', ['path' => $file]);
            $told = true;
            return '';
        }
        $body = file_get_contents($file);
        return ($body !== false) ? $body : '';
    }

    # Write a cache file atomically through a temp file, exclusive lock, and rename; a short write is a failure and never reaches the target path
    public static function setBody(string $file, string $body): bool {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
        $tmp = tempnam($dir, basename($file).'.');
        if ($tmp === false) return false;
        if (file_put_contents($tmp, $body, LOCK_EX) !== strlen($body)) {
            if (is_file($tmp)) unlink($tmp);
            return false;
        }
        if (!rename($tmp, $file)) {
            if (is_file($tmp)) unlink($tmp);
            return false;
        }
        return true;
    }

    # Remove every cached file under storage/cache, keeping protected markers, the write-guard journal and the directory tree
    # The generation is bumped afterwards because unlink reports failure silently: a page the sweep could not remove stays unreachable instead of being served again
    public static function deleteAll(): int {
        $num = 0;
        if (is_dir(CACHE_DIR)) {
            $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS));
            foreach ($iter as $file) {
                if (!$file->isFile()) continue;
                $name = $file->getFilename();
                if ($name === '.htaccess' || $name === 'index.html' || self::checkGuardPath($file->getPathname())) continue;
                if (unlink($file->getPathname())) $num++;
            }
        }
        self::addEpoch();
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

    # Bind the page of this request to the moment its data changes on its own; null binds it with no known moment, and several calls keep the earliest one
    # A bound page is stored with that moment, is never served from the cache once it has come, and is never handed to a browser cache that would outlive it
    public static function setPageUntil(?int $until): void {
        self::$until = min(self::$until ?? PHP_INT_MAX, $until ?? PHP_INT_MAX);
    }

    # Write the page sidecar describing one stored body: body hash, dynamic flag and the moment the page is bound to, published after the body so a mismatched pair fails closed
    public static function setMeta(string $file, string $body, bool $dyn): bool {
        return self::setBody($file.'.json', json_encode(['sha1' => sha1($body), 'dyn' => $dyn ? 1 : 0, 'until' => self::$until ?? 0]));
    }

    # Read and validate the page sidecar against the actual body; a missing, corrupt, or mismatched sidecar reports dynamic so serving fails closed to no-store
    # The moment a page is bound to comes back as until, zero for an unbound one; a sidecar that cannot be trusted reports the past, so a bound page is never served from it
    public static function getMeta(string $file, string $body): array {
        $data = json_decode(self::getBody($file.'.json'), true);
        if (!is_array($data) || !isset($data['sha1'], $data['dyn']) || !hash_equals((string)$data['sha1'], sha1($body))) return ['dyn' => true, 'valid' => false, 'until' => 1];
        $until = $data['until'] ?? 0;
        return ['dyn' => (bool)$data['dyn'], 'valid' => true, 'until' => (is_int($until) && $until >= 0) ? $until : 1];
    }

    # Recursively remove cached files under one directory older than the retention window, keeping protected markers, the write-guard journal and the directory tree
    # Evicted live entries only trigger a cheap rebuild
    public static function deleteStaleTree(string $dir, int $ttl): int {
        if ($ttl < 1 || !is_dir($dir)) return 0;
        $num = 0;
        $edge = time() - $ttl;
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $name = $file->getFilename();
            if ($name === '.htaccess' || $name === 'index.html' || self::checkGuardPath($file->getPathname())) continue;
            if ($file->getMTime() < $edge && unlink($file->getPathname())) $num++;
        }
        return $num;
    }

    # Read the generation counter under a shared lock, so a bump in progress is never seen as an empty file
    # A missing counter is generation zero; a counter that cannot be read or is no plain number answers false, which switches the page cache off instead of serving generation zero
    private static function getEpochValue(): int|false {
        $file = COUNTER_DIR.'/cache.log';
        if (!is_file($file)) return 0;
        if (!is_readable($file)) return false;
        $hand = fopen($file, 'r');
        if ($hand === false) return false;
        $val = flock($hand, LOCK_SH) ? stream_get_contents($hand) : false;
        fclose($hand);
        return is_string($val) && preg_match('/^[0-9]{1,18}$/D', $val) ? intval($val) : false;
    }

    # Read the current page-cache generation counter, returning zero when it is missing or unreadable; the page cache itself asks checkWriteGuard() first and never trusts that zero
    # The counter lives with the other persistent counters in storage/counter and not inside the tree it governs, so clearing the cache cannot reset it
    public static function getEpoch(): int {
        $val = self::getEpochValue();
        return $val === false ? 0 : $val;
    }

    # Bump the page-cache generation counter through an exclusive lock to invalidate every cached page, and answer whether the new generation is proven to be on disk
    # One bump per request is enough for ordinary writers, so a repeat answers true without work; the owner of a write guard forces the final bump that follows its commit
    # A growing number never gets shorter, so it is written over the old one in place and the file is never empty between two states; only a malformed counter is truncated first
    public static function addEpoch(bool $force = false): bool {
        if (self::$bumped && !$force) return true;
        $dir = COUNTER_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
        $hand = fopen($dir.'/cache.log', 'c+');
        if ($hand === false) return false;
        $done = false;
        if (flock($hand, LOCK_EX)) {
            $raw = (string)stream_get_contents($hand);
            $text = (string)(intval($raw) + 1);
            if (strlen($text) < strlen($raw)) ftruncate($hand, 0);
            rewind($hand);
            $done = fwrite($hand, $text) === strlen($text) && fflush($hand) && rewind($hand) && stream_get_contents($hand) === $text;
            flock($hand, LOCK_UN);
        }
        fclose($hand);
        if ($done) self::$bumped = true;
        return $done;
    }

    # Report whether a path belongs to the write-guard journal, which no sweep may touch: only the completion and the recovery of a guard remove a marker, after a proven bump
    private static function checkGuardPath(string $path): bool {
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', CACHE_DIR);
        return $path === $root.'/guards.lock' || str_starts_with($path, $root.'/guards/');
    }

    # Take the short shared lock of the guard journal, which serializes the creation, the removal and the recovery of every marker; closing the handle releases it
    # It is what keeps a marker from being taken for abandoned between the creation of its file and the grab of its own lock
    private static function getGuardGate(): mixed {
        $dir = CACHE_DIR.'/guards';
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return false;
        $gate = fopen(CACHE_DIR.'/guards.lock', 'c');
        if ($gate === false) return false;
        if (flock($gate, LOCK_EX)) return $gate;
        fclose($gate);
        return false;
    }

    # Open the journal entry of one unfinished invalidation: a unique marker in storage/cache/guards whose file lock this process holds, or false when it cannot be made
    # The owner of a content write takes it before BEGIN and may not start its SQL without it; while any marker exists the page cache is neither read nor filled
    # The class keeps the handle itself, so a marker whose owner forgot it stays locked until the process ends and is recovered by the next request after that
    public static function getWriteGuard(): mixed {
        $gate = self::getGuardGate();
        if ($gate === false) return false;
        $path = CACHE_DIR.'/guards/'.bin2hex(random_bytes(16)).'.lock';
        $hand = fopen($path, 'x');
        $done = $hand !== false && flock($hand, LOCK_EX | LOCK_NB);
        if ($hand !== false && !$done) {
            fclose($hand);
            unlink($path);
        }
        fclose($gate);
        if (!$done) return false;
        self::$guards[get_resource_id($hand)] = [$hand, $path];
        return $hand;
    }

    # Close the journal entry of a finished write: only a handle this class registered is accepted, and its marker is removed under the lock of the journal
    # The owner calls it after the forced bump that follows its commit, or after a proven rollback; a marker that could not be removed is left to the recovery, which bumps again
    public static function deleteWriteGuard(mixed $guard): bool {
        if (!is_resource($guard)) return false;
        $id = get_resource_id($guard);
        $path = self::$guards[$id][1] ?? '';
        if ($path === '' || !self::checkGuardPath($path) || !preg_match(self::GUARD, basename($path))) return false;
        $gate = self::getGuardGate();
        if ($gate === false) return false;
        flock($guard, LOCK_UN);
        fclose($guard);
        unset(self::$guards[$id]);
        $done = !is_file($path) || unlink($path);
        fclose($gate);
        return $done;
    }

    # Report whether the page cache may be read and filled right now: the generation is readable and no marker of an unfinished write is left
    # A marker whose lock is free belongs to a process that died; bumping the generation and removing it is all the recovery needs, whatever became of the SQL behind it
    # A marker whose lock is held belongs to a live writer and is never touched or waited for; an unreadable journal switches the cache off like an unreadable generation does
    public static function checkWriteGuard(): bool {
        if (self::getEpochValue() === false) return false;
        $dir = CACHE_DIR.'/guards';
        if (!is_dir($dir)) return true;
        $list = scandir($dir);
        if ($list === false) return false;
        if (!preg_grep(self::GUARD, $list)) return true;
        $gate = self::getGuardGate();
        if ($gate === false) return false;
        $left = 0;
        foreach (preg_grep(self::GUARD, scandir($dir) ?: []) as $name) {
            $path = $dir.'/'.$name;
            $hand = fopen($path, 'r+');
            $free = $hand !== false && flock($hand, LOCK_EX | LOCK_NB) && self::addEpoch(true);
            if ($hand !== false) fclose($hand);
            if (!$free || !unlink($path)) $left++;
        }
        fclose($gate);
        return $left === 0;
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

    # Emit browser cache, content type, and security headers; a public response drops every pending cookie so a shared proxy can never hand one visitor state to another
    # It also drops the Pragma that PHP emits for every session, because a response cannot both invite caching and forbid it, whatever an old intermediary decides to honour
    public static function setHeaders(bool $public, int $days = 0, string $type = 'text/html', int $mtime = 0, bool $immutable = false): void {
        $ctype = ($type === 'text/html') ? $type.'; charset='._CHARSET : $type;
        header('Content-Type: '.$ctype);
        if ($public) {
            header_remove('Set-Cookie');
            header_remove('Pragma');
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

    # Emit the headers of a private response the browser may keep but has to revalidate on every use: type, validators and the security headers of setHeaders()
    # Pending cookies stay, because the answer belongs to one visitor; the Pragma and Expires a session emits go, as they would contradict the revalidation
    public static function setPrivateHeaders(string $type, int $mtime, string $etag): void {
        self::setHeaders(false, 0, $type, $mtime);
        header_remove('Pragma');
        header_remove('Expires');
        header('Cache-Control: private, no-cache, must-revalidate, no-transform');
        if ($etag !== '') header('ETag: '.$etag);
    }

    # Send a 304 status and report a match when the client cached copy is still current; a given entity tag decides an If-None-Match on its own
    # Only a request without If-None-Match falls back to the If-Modified-Since date, and a call without a tag ignores If-None-Match as before
    public static function checkNotModified(int $mtime, string $etag = ''): bool {
        $none = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($etag !== '' && $none !== '') {
            $tags = array_map(fn($v) => preg_replace('#^W/#', '', trim($v)), explode(',', $none));
            if ($none !== '*' && !in_array(preg_replace('#^W/#', '', $etag), $tags, true)) return false;
            http_response_code(304);
            return true;
        }
        if ($mtime <= 0) return false;
        $since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($since === '') return false;
        $stamp = strtotime($since);
        if ($stamp === false || $stamp < $mtime) return false;
        http_response_code(304);
        return true;
    }
}
