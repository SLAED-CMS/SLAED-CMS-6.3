<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Cache {
    private const EXTS = ['html', 'json'];
    # How long a generated static document may sit in a browser cache; the OpenSearch and XSL descriptions change with a release, not with a setting, so no field governs them
    public const STATICDAYS = 7;
    # How long a stored file of the data and template caches outlives its last write before the cachegc job removes it
    public const KEEP = 86400;

    # The file of one stored entry in storage/cache/data, named by the hash of its ordered identity parts; an extension outside the whitelist yields no file
    public static function getFile(array $parts, string $ext): string {
        if (!in_array($ext, self::EXTS, true)) return '';
        return CACHE_DIR.'/data/'.sha1(implode('|', $parts)).'.'.$ext;
    }

    # Report whether a cache file exists, is not empty, and is still within the TTL window
    public static function isFresh(string $file, int $ttl): bool {
        if (!is_file($file)) return false;
        if (filesize($file) === 0) return false;
        return (time() - $ttl) < filemtime($file);
    }

    # Read a cache file or answer ''; an unreadable file is logged once per process, as nothing else reports it and a lost permission would otherwise log every read
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

    # Create a missing cache directory; losing the race to a parallel request is the expected success, and only a directory still missing afterwards is logged
    public static function setDirPath(string $dir): bool {
        if (is_dir($dir)) return true;
        set_error_handler(static fn(): bool => true);
        $made = mkdir($dir, 0777, true);
        restore_error_handler();
        if ($made || is_dir($dir)) return true;
        Logger::addFile('error', 'Cache directory cannot be created', ['path' => $dir]);
        return false;
    }

    # Write a cache file atomically through a temp file, exclusive lock, and rename; a short write is a failure and never reaches the target path
    public static function setBody(string $file, string $body): bool {
        $dir = dirname($file);
        if (!self::setDirPath($dir)) return false;
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

    # Remove every cached file under storage/cache, keeping the lock files and the directory tree
    public static function deleteAll(): int {
        return self::deleteStale(CACHE_DIR, 0);
    }

    # Remove cached files not rewritten for ttl seconds, every file for zero, keeping lock files: unlinking a held lock lets the next process lock a new one beside it
    public static function deleteStale(string $dir, int $ttl): int {
        if ($ttl < 0 || !is_dir($dir)) return 0;
        $num = 0;
        $edge = time() - $ttl;
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            if (str_ends_with($file->getFilename(), '.lock')) continue;
            if (($ttl === 0 || $file->getMTime() < $edge) && unlink($file->getPathname())) $num++;
        }
        return $num;
    }

    # Emit the content type, cache policy and security headers; a public answer drops every pending cookie, so a shared proxy never hands one visitor state to another
    public static function setHeaders(string $mode = 'none', string $type = 'text/html', int $mtime = 0, string $etag = '', int $age = 0): void {
        header('Content-Type: '.(($type === 'text/html') ? $type.'; charset='._CHARSET : $type));
        header_remove('Pragma');
        header_remove('Expires');
        if ($mode === 'public') {
            $age = ($age > 0) ? $age : self::STATICDAYS * 86400;
            header_remove('Set-Cookie');
            header('Cache-Control: public, max-age='.$age);
            header('Expires: '.gmdate('D, d M Y H:i:s', time() + $age).' GMT');
            if ($etag !== '') header('ETag: '.$etag);
        } elseif ($mode === 'private') {
            header('Cache-Control: private, no-cache, must-revalidate, no-transform');
            if ($etag !== '') header('ETag: '.$etag);
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
        }
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', ($mtime > 0) ? $mtime : time()).' GMT');
        header('X-Powered-By: SLAED CMS');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    # Send 304 when the cached copy is current; a given entity tag decides an If-None-Match alone, and If-Modified-Since counts only without that header
    public static function checkNotModified(int $mtime, string $etag = ''): bool {
        $none = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        if ($etag !== '' && $none !== '') {
            $tags = array_map(fn(string $v): ?string => preg_replace('#^W/#', '', trim($v)), explode(',', $none));
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
