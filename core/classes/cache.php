<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

class Cache {
    private const TYPES = ['html', 'assets', 'data'];
    private const EXTS = ['html', 'css', 'js', 'json'];

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

    # Emit browser cache, content type, and security headers for public or no-store responses
    public static function setHeaders(bool $public, int $days = 0, string $type = 'text/html'): void {
        $ctype = ($type === 'text/html') ? $type.'; charset='._CHARSET : $type;
        header('Content-Type: '.$ctype);
        if ($public) {
            $max = $days * 86400;
            header('Cache-Control: public, max-age='.$max);
            header('Expires: '.gmdate('D, d M Y H:i:s', time() + $max).' GMT');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
        }
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').' GMT');
        header('X-Powered-By: SLAED CMS');
        header('X-Powered-CMS: SLAED CMS');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}
