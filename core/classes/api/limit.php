<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The token buckets of the API in the file cache: a bucket refills $rate tokens a minute up to $burst, and a request takes one
final class ApiLimit {

    # The address an IP is counted under: IPv4 as it is, IPv4 mapped into IPv6 as its IPv4, any other IPv6 by its /64, since one host owns a whole prefix
    public static function getIpKey(string $ip): string {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return $ip;
        $pack = (string)inet_pton($ip);
        if (str_starts_with($pack, str_repeat("\0", 10)."\xff\xff")) return (string)inet_ntop(substr($pack, 12));
        return inet_ntop(substr($pack, 0, 8).str_repeat("\0", 8)).'/64';
    }

    # Take one token of the bucket $key under its lock: 0 lets the request pass, more is the seconds until a token is back; a bucket that cannot be kept lets it pass
    public static function getWait(string $key, int $rate, int $burst): int {
        if ($rate < 1 || $burst < 1) return 0;
        $file = Cache::getFile(['api', 'limit', $key], 'json');
        if (!Cache::setDirPath(dirname($file))) return 0;
        $hand = fopen($file, 'c+');
        if ($hand === false) return 0;
        flock($hand, LOCK_EX);
        $now = microtime(true);
        $data = json_decode((string)stream_get_contents($hand), true);
        $have = is_array($data) ? (float)($data['n'] ?? $burst) : (float)$burst;
        $last = is_array($data) ? (float)($data['t'] ?? $now) : $now;
        $have = min($burst, $have + max(0, $now - $last) * $rate / 60);
        $wait = ($have >= 1) ? 0 : (int)ceil((1 - $have) * 60 / $rate);
        if ($wait === 0) $have -= 1;
        ftruncate($hand, 0);
        rewind($hand);
        fwrite($hand, json_encode(['n' => $have, 't' => $now]));
        fflush($hand);
        flock($hand, LOCK_UN);
        fclose($hand);
        return $wait;
    }
}
