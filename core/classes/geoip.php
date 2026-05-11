<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

class Geoip {
    # Return normalized empty GeoIP result
    public static function getEmpty(): array {
        return [
            'country' => '',
            'country_name' => '',
            'continent' => '',
            'asn' => 0,
            'organization' => '',
            'provider' => '',
            'status' => '',
        ];
    }

    # Return GeoIP information for one IP
    public static function getInfo(string $ip): array {
        global $conf;
        static $cache = [];
        $res = self::getEmpty();
        $cfg = $conf['geoip'] ?? [];
        $key = self::getCacheKey($ip, $cfg);
        if (isset($cache[$key])) return $cache[$key];
        if (empty($cfg['enabled']) || !self::checkIp($ip)) {
            $cache[$key] = $res;
            return $res;
        }
        $hit = false;
        $path = self::getPath((string)($cfg['country_database'] ?? ''));
        $reader = self::getReader($path);
        if ($reader !== null) {
            try {
                $data = $reader->country($ip);
                $res['country'] = (string)($data->country->isoCode ?? '');
                $res['country_name'] = (string)($data->country->name ?? '');
                $res['continent'] = (string)($data->continent->code ?? '');
                $hit = $res['country'] !== '' || $res['country_name'] !== '' || $res['continent'] !== '';
            } catch (Throwable) {
            }
        }
        $path = self::getPath((string)($cfg['asn_database'] ?? ''));
        $reader = self::getReader($path);
        if ($reader !== null) {
            try {
                $data = $reader->asn($ip);
                $res['asn'] = (int)($data->autonomousSystemNumber ?? 0);
                $res['organization'] = (string)($data->autonomousSystemOrganization ?? '');
                $hit = $hit || $res['asn'] > 0 || $res['organization'] !== '';
            } catch (Throwable) {
            }
        }
        if ($hit) {
            $res['provider'] = 'maxmind';
            $res['status'] = 'ok';
        }
        $cache[$key] = $res;
        return $res;
    }

    # Return country code for one IP
    public static function getCountry(string $ip): string {
        $res = self::getInfo($ip);
        return (string)$res['country'];
    }

    # Return database file status for admin UI
    public static function getFileInfo(string $file): array {
        $path = self::getPath($file);
        $ok = is_file($path) && is_readable($path);
        return [
            'path' => $file,
            'found' => $ok,
            'size' => $ok ? filesize($path) : 0,
            'mtime' => $ok ? filemtime($path) : 0,
        ];
    }

    # Check if IP is valid for lookup
    private static function checkIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    # Return cache key without keeping full IP unless configured
    private static function getCacheKey(string $ip, array $cfg): string {
        if (!empty($cfg['store_ip']) && empty($cfg['anonymize_ip'])) return $ip;
        return sha1($ip);
    }

    # Return absolute path for a configured database file
    private static function getPath(string $file): string {
        $file = trim($file);
        if ($file === '') return '';
        if (str_starts_with($file, '/') || preg_match('#^[a-z]:[\\\\/]#i', $file)) return $file;
        return BASE_DIR.'/'.$file;
    }

    # Return MaxMind reader when available
    private static function getReader(string $path): ?object {
        static $list = [];
        if ($path === '' || !is_file($path) || !is_readable($path)) return null;
        if (!class_exists('\GeoIp2\Database\Reader')) return null;
        if (array_key_exists($path, $list)) return $list[$path];
        try {
            $list[$path] = new \GeoIp2\Database\Reader($path);
        } catch (Throwable) {
            $list[$path] = null;
        }
        return $list[$path];
    }
}
