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
        $cfg = $conf;
        $key = self::getCacheKey($ip, $cfg);
        if (isset($cache[$key])) return $cache[$key];
        if (empty($cfg['geoip_enabled']) || !self::checkIp($ip)) {
            $cache[$key] = $res;
            return $res;
        }
        $hit = false;
        $coun = self::getCountryData($ip, $cfg);
        if ($coun !== []) {
            $res['country'] = $coun['country'];
            $res['country_name'] = $coun['country_name'];
            $res['continent'] = $coun['continent'];
            $hit = true;
        }
        $asnd = self::getAsnData($ip, $cfg);
        if ($asnd !== []) {
            $res['asn'] = $asnd['asn'];
            $res['organization'] = $asnd['organization'];
            $hit = true;
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
        global $conf;
        $res = self::getCountryData($ip, $conf);
        return (string)($res['country'] ?? '');
    }

    # Return flag HTML for one IP
    public static function getFlagHtml(string $ip): string {
        global $conf, $tpl;
        static $flags = [];
        $res = self::getCountryData($ip, $conf);
        $code = strtolower((string)($res['country'] ?? ''));
        if ($code === '') return '';
        $name = (string)($res['country_name'] ?: strtoupper($code));
        $key = $code.'|'.$name;
        if (isset($flags[$key])) return $flags[$key];
        $flags[$key] = $tpl->getHtmlFrag('span', [
            'title' => $name,
            'img_src' => self::getFlagSrc($code),
            'img_alt' => $name,
            'is_geo_flag' => true,
        ]);
        return $flags[$key];
    }

    # Return IP link with optional flag for one IP
    public static function getIpHtml(string $ip): string {
        global $conf, $tpl;
        $link = $tpl->getHtmlFrag('link', [
            'href' => $conf['ip_link'].$ip,
            'title' => (string)_IP.': '.$ip,
            'label' => $ip,
            'is_blank' => true,
        ]);
        return self::getFlagHtml($ip).$link;
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
        if (!empty($cfg['geoip_store']) && empty($cfg['geoip_anon'])) return $ip;
        return sha1($ip);
    }

    # Return absolute path for a configured database file
    private static function getPath(string $file): string {
        $file = trim($file);
        if ($file === '') return '';
        if (str_starts_with($file, '/') || preg_match('#^[a-z]:[\\\\/]#i', $file)) return $file;
        return BASE_DIR.'/'.$file;
    }

    # Return cached country data for one IP
    private static function getCountryData(string $ip, array $cfg): array {
        static $cache = [];
        $key = self::getCacheKey($ip, $cfg);
        if (isset($cache[$key])) return $cache[$key];
        $res = [];
        if (!empty($cfg['geoip_enabled']) && self::checkIp($ip)) {
            $data = self::getRecord(self::getPath((string)($cfg['geoip_country'] ?? '')), $ip);
            if ($data !== []) {
                $cont = $data['continent'] ?? [];
                $coun = $data['country'] ?? [];
                $names = is_array($coun) ? ($coun['names'] ?? []) : [];
                $code = is_array($coun) ? (string)($coun['iso_code'] ?? '') : '';
                $name = is_array($names) ? (string)($names['en'] ?? reset($names) ?: '') : '';
                $zone = is_array($cont) ? (string)($cont['code'] ?? '') : '';
                if ($code !== '' || $name !== '' || $zone !== '') {
                    $res = ['country' => $code, 'country_name' => $name, 'continent' => $zone];
                }
            }
        }
        $cache[$key] = $res;
        return $res;
    }

    # Return cached ASN data for one IP
    private static function getAsnData(string $ip, array $cfg): array {
        static $cache = [];
        $key = self::getCacheKey($ip, $cfg);
        if (isset($cache[$key])) return $cache[$key];
        $res = [];
        if (!empty($cfg['geoip_enabled']) && self::checkIp($ip)) {
            $data = self::getRecord(self::getPath((string)($cfg['geoip_asn'] ?? '')), $ip);
            if ($data !== []) {
                $asn = (int)($data['autonomous_system_number'] ?? 0);
                $org = (string)($data['autonomous_system_organization'] ?? '');
                if ($asn > 0 || $org !== '') $res = ['asn' => $asn, 'organization' => $org];
            }
        }
        $cache[$key] = $res;
        return $res;
    }

    # Return existing country flag path
    private static function getFlagSrc(string $code): string {
        static $cache = [];
        if (isset($cache[$code])) return $cache[$code];
        $path = 'flags/'.$code.'.svg';
        $file = img_find($path);
        $cache[$code] = file_exists($file) ? $file : img_find('flags/unknown.svg');
        return $cache[$code];
    }

    # Return MaxMind DB record for one IP
    private static function getRecord(string $path, string $ip): array {
        try {
            $db = self::getMmdb($path);
            if ($db === null) return [];
            $addr = inet_pton($ip);
            if ($addr === false) return [];
            $raw = unpack('C*', $addr);
            if ($raw === false) return [];
            $meta = $db['meta'];
            $bits = count($raw) * 8;
            if ((int)$meta['ip_version'] === 4 && $bits === 128) return [];
            $node = 0;
            if ((int)$meta['ip_version'] === 6 && $bits === 32) {
                for ($i = 0; $i < 96 && $node < (int)$meta['node_count']; $i++) {
                    $node = self::getMmdbNode($db, $node, 0);
                }
            }
            for ($i = 0; $i < $bits && $node < (int)$meta['node_count']; $i++) {
                $byte = $raw[intdiv($i, 8) + 1];
                $bit = ($byte >> (7 - ($i % 8))) & 1;
                $node = self::getMmdbNode($db, $node, $bit);
            }
            if ($node <= (int)$meta['node_count']) return [];
            $offs = $node - (int)$meta['node_count'] + (int)$meta['datab'] - 16;
            [$data] = self::getMmdbDecode($db['bin'], $offs, (int)$meta['datab']);
            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }

    # Return cached MMDB payload and metadata
    private static function getMmdb(string $path): ?array {
        static $list = [];
        if ($path === '' || !is_file($path) || !is_readable($path)) return null;
        if (array_key_exists($path, $list)) return $list[$path];
        $bin = file_get_contents($path);
        if ($bin === false) {
            $list[$path] = null;
            return null;
        }
        $mark = "\xAB\xCD\xEFMaxMind.com";
        $pos = strrpos($bin, $mark);
        if ($pos === false) {
            $list[$path] = null;
            return null;
        }
        [$meta] = self::getMmdbDecode($bin, $pos + strlen($mark), 0);
        if (!is_array($meta) || empty($meta['node_count']) || empty($meta['record_size'])) {
            $list[$path] = null;
            return null;
        }
        $meta['nodes'] = (int)$meta['node_count'];
        $meta['record'] = (int)$meta['record_size'];
        $meta['nsize'] = (int)($meta['record'] / 4);
        $meta['datab'] = $meta['nodes'] * $meta['nsize'] + 16;
        $list[$path] = ['bin' => $bin, 'meta' => $meta];
        return $list[$path];
    }

    # Return one search tree node pointer
    private static function getMmdbNode(array $db, int $node, int $side): int {
        $meta = $db['meta'];
        $base = $node * (int)$meta['nsize'];
        $bin = $db['bin'];
        if ((int)$meta['record'] === 24) {
            $offs = $base + $side * 3;
            return self::getMmdbUint(substr($bin, $offs, 3), 3);
        }
        if ((int)$meta['record'] === 28) {
            $offs = $base + 3 * $side;
            $buf = substr($bin, $offs, 4);
            $mid = $side === 0 ? ((ord($buf[3]) & 0xF0) >> 4) : (ord($buf[0]) & 0x0F);
            return self::getMmdbUint(chr($mid).substr($buf, $side, 3), 4);
        }
        if ((int)$meta['record'] === 32) {
            $offs = $base + $side * 4;
            return self::getMmdbUint(substr($bin, $offs, 4), 4);
        }
        return 0;
    }

    # Decode one MMDB value
    private static function getMmdbDecode(string $bin, int $offs, int $base): array {
        $ctrl = ord($bin[$offs]);
        $offs++;
        $type = $ctrl >> 5;
        if ($type === 1) {
            [$ptr, $offs] = self::getMmdbPointer($bin, $ctrl, $offs, $base);
            [$data] = self::getMmdbDecode($bin, $ptr, $base);
            return [$data, $offs];
        }
        if ($type === 0) {
            $type = ord($bin[$offs]) + 7;
            $offs++;
        }
        [$size, $offs] = self::getMmdbSize($bin, $ctrl, $offs);
        if ($type === 11) {
            $arr = [];
            for ($i = 0; $i < $size; $i++) {
                [$arr[], $offs] = self::getMmdbDecode($bin, $offs, $base);
            }
            return [$arr, $offs];
        }
        if ($type === 7) {
            $map = [];
            for ($i = 0; $i < $size; $i++) {
                [$key, $offs] = self::getMmdbDecode($bin, $offs, $base);
                [$val, $offs] = self::getMmdbDecode($bin, $offs, $base);
                $map[(string)$key] = $val;
            }
            return [$map, $offs];
        }
        if ($type === 14) return [$size !== 0, $offs];
        $data = substr($bin, $offs, $size);
        $offs += $size;
        return [self::getMmdbValue($type, $data, $size), $offs];
    }

    # Return decoded scalar MMDB value
    private static function getMmdbValue(int $type, string $data, int $size): mixed {
        if ($type === 2 || $type === 4) return $data;
        if ($type === 3) {
            $val = unpack('E', $data);
            return $val !== false ? $val[1] : 0.0;
        }
        if ($type === 8) {
            $data = str_pad($data, 4, "\x00", STR_PAD_LEFT);
            $val = unpack('l', pack('V', self::getMmdbUint($data, 4)));
            return $val !== false ? $val[1] : 0;
        }
        if ($type === 5 || $type === 6 || $type === 9 || $type === 10) return self::getMmdbUint($data, $size);
        if ($type === 15) {
            $val = unpack('G', $data);
            return $val !== false ? $val[1] : 0.0;
        }
        return null;
    }

    # Return variable MMDB value size
    private static function getMmdbSize(string $bin, int $ctrl, int $offs): array {
        $size = $ctrl & 0x1F;
        if ($size < 29) return [$size, $offs];
        $read = $size - 28;
        $data = substr($bin, $offs, $read);
        $offs += $read;
        if ($size === 29) return [29 + ord($data[0]), $offs];
        if ($size === 30) return [285 + self::getMmdbUint($data, 2), $offs];
        return [65821 + self::getMmdbUint($data, 3), $offs];
    }

    # Return decoded MMDB data pointer
    private static function getMmdbPointer(string $bin, int $ctrl, int $offs, int $base): array {
        $size = (($ctrl >> 3) & 0x03) + 1;
        $data = substr($bin, $offs, $size);
        $offs += $size;
        $part = $ctrl & 0x07;
        if ($size === 1) return [$base + self::getMmdbUint(chr($part).$data, 2), $offs];
        if ($size === 2) return [$base + 2048 + self::getMmdbUint(chr($part).$data, 3), $offs];
        if ($size === 3) return [$base + 526336 + self::getMmdbUint(chr($part).$data, 4), $offs];
        return [$base + self::getMmdbUint($data, 4), $offs];
    }

    # Return big-endian unsigned integer
    private static function getMmdbUint(string $data, int $size): int {
        $num = 0;
        for ($i = 0; $i < $size; $i++) {
            $num = ($num << 8) + ord($data[$i]);
        }
        return $num;
    }
}
