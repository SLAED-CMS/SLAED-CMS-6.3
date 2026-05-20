<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad(int $tcache = 2): array {
    static $cache = ['time' => 0, 'cpu' => _NO_INFO, 'info' => _NO_INFO];
    if (time() - $cache['time'] < $tcache) return [$cache['cpu'], $cache['info']];
    $percent = null;
    $allow = static function (string $path): bool {
        $obase = ini_get('open_basedir');
        if ($obase === false || $obase === '') return true;

        $npath = str_replace('\\', '/', $path);
        foreach (explode(PATH_SEPARATOR, $obase) as $base) {
            $base = trim((string)$base);
            if ($base === '' || $base === '.') continue;

            $cbase = rtrim(str_replace('\\', '/', $base), '/');
            if ($cbase === '') continue;

            if ($npath === $cbase || str_starts_with($npath, $cbase.'/')) {
                return true;
            }
        }
        return false;
    };
    $rfile = static function (string $path) use ($allow): string|false {
        if (!$allow($path)) return false;
        if (!is_file($path) || !is_readable($path)) return false;

        $content = file_get_contents($path);
        return ($content === false) ? false : $content;
    };
    if (stristr(PHP_OS, 'WIN')) {
        $out = [];
        $cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
        if (function_exists('exec')) exec($cmd, $out);
        if (!empty($out)) {
            $val = str_replace(',', '.', trim($out[0]));
            if (is_numeric($val)) $percent = (float)$val;
        }
        if ($percent === null) {
            $out = [];
            $cmd = 'wmic cpu get loadpercentage /all';
            if (function_exists('exec')) exec($cmd, $out);
            if ($out) {
                foreach ($out as $line) {
                    if ($line && preg_match('#^[0-9]+$#', $line)) {
                        $percent = (float)$line;
                        break;
                    }
                }
            }
        }
    } else {
        if (function_exists('sys_getloadavg')) {
            $tmp = sys_getloadavg();
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $loadavg = $rfile('/proc/loadavg');
        if (!isset($raw) && $loadavg !== false) {
            $tmp = explode(' ', $loadavg);
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $nproc = 0;
        $info = $rfile('/proc/cpuinfo');
        if ($info !== false) {
            preg_match_all('/^processor\s*:/m', $info, $matches);
            if (!empty($matches[0])) $nproc = count($matches[0]);
        }
        if ($nproc <= 0) $nproc = 1;
        if (isset($raw) && is_numeric($raw)) $percent = ($raw / $nproc) * 10.0;
    }
    if (is_numeric($percent)) {
        $cpu = round((float)$percent, 2);
        if ($cpu < 0) $cpu = 0.0;
        if ($cpu > 100) $cpu = 100.0;
        $info = _PLOAD1;
    } else {
        $cpu = $info = _NO_INFO;
    }
    $cache = ['time' => time(), 'cpu' => $cpu, 'info' => $info];
    return [$cpu, $info];
}

# Checks whether a filesystem path is permitted by open_basedir restrictions before any file operations
function isPathAllowed(string $path): bool {
    $obase = ini_get('open_basedir');
    if ($obase === false || $obase === '') return true;
    $npath = str_replace('\\', '/', $path);
    foreach (explode(PATH_SEPARATOR, $obase) as $base) {
        $base = trim((string)$base);
        if ($base === '' || $base === '.') continue;
        $cbase = rtrim(str_replace('\\', '/', $base), '/');
        if ($cbase === '') continue;
        if ($npath === $cbase || str_starts_with($npath, $cbase.'/')) return true;
    }
    return false;
}

# Reads file contents only when the path is allowed, file exists, and read permission is available
function getFileSafe(string $path): string|false {
    if (!isPathAllowed($path)) return false;
    if (!is_file($path) || !is_readable($path)) return false;
    $content = file_get_contents($path);
    return ($content === false) ? false : $content;
}

# Reads a server variable via input filtering and getenv fallback without direct superglobal access
function getServerValue(string $key, string $default = ''): string {
    $value = filter_input(INPUT_SERVER, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
    if (is_string($value) && $value !== '') return $value;
    $value = getenv($key);
    if (is_string($value) && $value !== '') return $value;
    return $default;
}

# Returns normalized cookie key/value pairs via input filtering for request diagnostics
function getCookieValues(): array {
    $items = filter_input_array(INPUT_COOKIE, FILTER_UNSAFE_RAW);
    if (!is_array($items) || $items === []) return [];
    $result = [];
    foreach ($items as $key => $value) {
        if (!is_string($key) || is_array($value)) continue;
        $result[$key] = (string)$value;
    }
    return $result;
}

# Collects total, free, used memory and usage percent using OS-specific providers with safe fallbacks
function getMemoryInfo(): array {
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        [$total, $free] = getMemoryInfoWindows();
    } else {
        [$total, $free] = getMemoryInfoLinux();
    }
    if ($total <= 0) {
        $total = getMemorySafeLimit();
        $free = $total - memory_get_usage(true);
    }
    $used = max($total - $free, 0);
    return [
        'total'   => $total,
        'free'    => $free,
        'used'    => $used,
        'percent' => ($total > 0) ? round(($used / $total) * 100, 1) : 0,
    ];
}

# Reads total and free physical memory on Windows via PowerShell CIM and WMIC fallback paths
function getMemoryInfoWindows(): array {
    static $reqcache = null;
    if (is_array($reqcache)) return $reqcache;
    $ttl = 10;
    $cachekey = 'slaed_monitor_memory_windows_v1';
    if (is_callable('apcu_fetch') && is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        $ok = false;
        $cached = apcu_fetch($cachekey, $ok);
        if ($ok && is_array($cached) && isset($cached['data'], $cached['ts']) && (time() - (int)$cached['ts']) <= $ttl) {
            $reqcache = $cached['data'];
            return $reqcache;
        }
    }
    $free = 0;
    $total = 0;
    $ps = [];
    if (function_exists('exec')) exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory | Format-List)"', $ps);
    foreach ($ps as $line) {
        if (str_contains($line, 'TotalVisibleMemorySize')) {
            $parts = explode(':', $line, 2);
            $total = intval(trim($parts[1] ?? '0')) * 1024;
        } elseif (str_contains($line, 'FreePhysicalMemory')) {
            $parts = explode(':', $line, 2);
            $free = intval(trim($parts[1] ?? '0')) * 1024;
        }
    }
    if ($total > 0 && $free > 0) {
        $reqcache = [$total, $free];
        if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) apcu_store($cachekey, ['ts' => time(), 'data' => $reqcache], $ttl);
        return $reqcache;
    }
    $outtot = [];
    if (function_exists('exec')) exec('wmic ComputerSystem get TotalPhysicalMemory /Value', $outtot);
    foreach ($outtot as $line) {
        if (str_contains($line, 'TotalPhysicalMemory')) {
            $parts = explode('=', $line);
            $total = intval($parts[1] ?? 0);
            break;
        }
    }
    $outfree = [];
    if (function_exists('exec')) exec('wmic OS get FreePhysicalMemory /Value', $outfree);
    foreach ($outfree as $line) {
        if (str_contains($line, 'FreePhysicalMemory')) {
            $parts = explode('=', $line);
            $free = intval($parts[1] ?? 0) * 1024;
            break;
        }
    }
    $reqcache = [$total, $free];
    if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) apcu_store($cachekey, ['ts' => time(), 'data' => $reqcache], $ttl);
    return $reqcache;
}

# Parses /proc/meminfo on Linux and computes total and available memory with compatibility fallback
function getMemoryInfoLinux(): array {
    $total = 0;
    $free = 0;
    $data = getFileSafe('/proc/meminfo');
    if (($data === false || $data === '') && function_exists('exec')) {
        $out = [];
        exec('cat /proc/meminfo 2>/dev/null', $out);
        if (!empty($out)) $data = implode("\n", $out);
    }
    if (($data === false || $data === '') && function_exists('exec')) {
        $out = [];
        exec('LC_ALL=C free -b 2>/dev/null', $out);
        if (!empty($out)) {
            foreach ($out as $line) {
                $line = trim((string)$line);
                if (!preg_match('/^Mem:\s+(\d+)\s+\d+\s+\d+\s+\d+\s+\d+\s+(\d+)/', $line, $mx)) continue;
                $total = (int)$mx[1];
                $free = (int)$mx[2];
                break;
            }
        }
    }
    if ($total > 0) return [$total, max($free, 0)];
    if (!$data) return [$total, $free];
    $mem = [];
    foreach (explode("\n", $data) as $line) {
        if (!str_contains($line, ':')) continue;
        [$key, $val] = explode(':', $line);
        $mem[trim($key)] = trim($val);
    }
    $total = intval($mem['MemTotal'] ?? 0) * 1024;
    $free = intval($mem['MemAvailable'] ?? 0) * 1024;
    if ($free <= 0) {
        $memfree = intval($mem['MemFree'] ?? 0);
        $buffers = intval($mem['Buffers'] ?? 0);
        $cached = intval($mem['Cached'] ?? 0);
        $free = ($memfree + $buffers + $cached) * 1024;
    }
    return [$total, $free];
}

# Converts PHP memory_limit to bytes and returns a conservative fallback when limit is unlimited
function getMemorySafeLimit(): int {
    return getMemoryLimitBytes(true);
}

# Detects logical CPU core count across Windows and Linux with environment and command fallbacks
function getCpuCores(): int {
    $cores = 0;
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $out = [];
        if (function_exists('exec')) exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum"', $out);
        if (!empty($out)) {
            $val = trim((string)$out[0]);
            if (is_numeric($val)) $cores = (int)$val;
        }
        if ($cores <= 0) {
            $envcores = getenv('NUMBER_OF_PROCESSORS');
            if ($envcores !== false && is_numeric($envcores)) $cores = (int)$envcores;
        }
    } else {
        $info = getFileSafe('/proc/cpuinfo');
        if (($info === false || $info === '') && function_exists('exec')) {
            $out = [];
            exec('cat /proc/cpuinfo 2>/dev/null', $out);
            if (!empty($out)) $info = implode("\n", $out);
        }
        if ($info !== false) {
            preg_match_all('/^processor\s*:/m', $info, $matches);
            if (!empty($matches[0])) $cores = count($matches[0]);
        }
        if ($cores <= 0 && function_exists('exec')) {
            $out = [];
            exec('nproc 2>/dev/null', $out);
            $val = trim((string)($out[0] ?? ''));
            if (is_numeric($val)) $cores = (int)$val;
        }
        if ($cores <= 0 && function_exists('exec')) {
            $out = [];
            exec('getconf _NPROCESSORS_ONLN 2>/dev/null', $out);
            $val = trim((string)($out[0] ?? ''));
            if (is_numeric($val)) $cores = (int)$val;
        }
    }
    return ($cores > 0) ? $cores : 1;
}

# Returns cumulative RX and TX bytes using the active platform-specific network statistics source
function getNetworkStats(): array {
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        return getNetworkStatsWindows();
    }
    return getNetworkStatsLinux();
}

# Parses netstat output on Windows to extract cumulative received and transmitted byte counters
function getNetworkStatsWindows(): array {
    $rx = 0.0;
    $tx = 0.0;
    $out = [];
    if (function_exists('exec')) exec('netstat -e', $out);
    foreach ($out as $line) {
        if (stripos($line, 'Bytes') === false) continue;
        $parts = preg_split('/\s+/', trim($line));
        $rxraw = (string)($parts[1] ?? '0');
        $txraw = (string)($parts[2] ?? '0');
        $rx = (float)preg_replace('/[^\d]/', '', $rxraw);
        $tx = (float)preg_replace('/[^\d]/', '', $txraw);
        break;
    }
    return ['rx' => $rx, 'tx' => $tx];
}

# Aggregates Linux network byte counters from procfs and sysfs with progressive fallbacks
function getNetworkStatsLinux(): array {
    $rx = 0.0;
    $tx = 0.0;
    $parsed = false;
    $data = getFileSafe('/proc/net/dev');
    if (($data === false || $data === '') && function_exists('exec')) {
        $out = [];
        exec('cat /proc/net/dev 2>/dev/null', $out);
        if (!empty($out)) $data = implode("\n", $out);
    }
    if ($data !== false && $data !== '') [$rx, $tx, $parsed] = getNetDevStats($data);
    if (!$parsed && isPathAllowed('/sys/class/net')) {
        $rxfiles = glob('/sys/class/net/*/statistics/rx_bytes') ?: [];
        $txfiles = glob('/sys/class/net/*/statistics/tx_bytes') ?: [];
        foreach ($rxfiles as $file) {
            if (!is_string($file) || str_contains($file, '/lo/')) continue;
            $val = getFileSafe($file);
            if ($val !== false && is_numeric(trim($val))) $rx += (float)trim($val);
        }
        foreach ($txfiles as $file) {
            if (!is_string($file) || str_contains($file, '/lo/')) continue;
            $val = getFileSafe($file);
            if ($val !== false && is_numeric(trim($val))) $tx += (float)trim($val);
        }
    }
    return ['rx' => $rx, 'tx' => $tx];
}

# Parses /proc/net/dev payload and sums non-loopback interface RX and TX byte counters safely
function getNetDevStats(string $data): array {
    $rx = 0.0;
    $tx = 0.0;
    $seen = 0;
    $lines = explode("\n", $data);
    foreach ($lines as $line) {
        if (!str_contains($line, ':')) continue;
        $iface = trim(substr($line, 0, strpos($line, ':')));
        if ($iface === '' || $iface === 'lo') continue;
        $payload = trim(substr($line, strpos($line, ':') + 1));
        $parts = preg_split('/\s+/', $payload);
        if (!is_array($parts) || count($parts) < 9) continue;
        $rxval = preg_replace('/[^\d]/', '', (string)$parts[0]);
        $txval = preg_replace('/[^\d]/', '', (string)$parts[8]);
        $rx += is_numeric($rxval) ? (float)$rxval : 0.0;
        $tx += is_numeric($txval) ? (float)$txval : 0.0;
        $seen++;
    }
    return [$rx, $tx, $seen > 0];
}

# Returns the absolute metrics storage file path used for persisting monitor history snapshots
function getMetricStorePath(): string {
    $dirs = [];
    if (defined('LOGS_DIR')) $dirs[] = LOGS_DIR;
    if (defined('CACHE_DIR')) $dirs[] = CACHE_DIR;
    $tmp = sys_get_temp_dir();
    if (is_string($tmp) && $tmp !== '') $dirs[] = rtrim($tmp, '/\\');
    foreach ($dirs as $dir) {
        if ($dir === '') continue;
        if (is_dir($dir) && is_writable($dir)) return $dir.'/monitor.json';
        $base = dirname($dir);
        if (!is_dir($dir) && $base !== '' && is_dir($base) && is_writable($base)) return $dir.'/monitor.json';
    }
    return LOGS_DIR.'/monitor.json';
}

# Loads persisted monitor metrics from JSON store and returns an array with safe empty fallback
function getMetricStore(): array {
    $file = getMetricStorePath();
    if (!is_file($file) || !is_readable($file)) return [];
    $json = file_get_contents($file);
    if ($json === false || $json === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

# Writes monitor metrics to JSON storage when logs directory is writable and available
function setMetricStore(array $data): void {
    $file = getMetricStorePath();
    $dir = dirname($file);
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) return;
    if (!is_writable($dir)) return;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

# Appends a rounded metric value to history and trims array length to the configured maximum
function addHistory(array $history, float $value, int $max = 30): array {
    $history[] = round($value, 2);
    if (count($history) > $max) $history = array_slice($history, -$max);
    return $history;
}

# Builds a smooth SVG cubic-bezier line path from metric history for chart rendering
function getSmoothLinePath(array $hist, float $max, int $ht = 220, int $wt = 100, int $px = 0): string {
    $pts = getHistoryPoints($hist, $max, $ht, $wt, $px);
    if (!$pts) return 'M'.$px.','.$ht.' L'.($wt - $px).','.$ht;
    if (count($pts) < 2) return 'M'.$pts[0]['x'].','.$pts[0]['y'].' L'.$pts[0]['x'].','.$pts[0]['y'];
    $pat = 'M'.$pts[0]['x'].','.$pts[0]['y'].' ';
    $nn = count($pts);
    for ($ix = 0; $ix < $nn - 1; $ix++) {
        $pta = ($ix > 0) ? $pts[$ix - 1] : $pts[$ix];
        $ptb = $pts[$ix];
        $ptc = $pts[$ix + 1];
        $ptd = ($ix + 2 < $nn) ? $pts[$ix + 2] : $ptc;
        $conax = $ptb['x'] + (($ptc['x'] - $pta['x']) / 6);
        $conay = $ptb['y'] + (($ptc['y'] - $pta['y']) / 6);
        $conbx = $ptc['x'] - (($ptd['x'] - $ptb['x']) / 6);
        $conby = $ptc['y'] - (($ptd['y'] - $ptb['y']) / 6);
        $pat .= 'C'.round($conax, 2).','.round($conay, 2).' '
            .round($conbx, 2).','.round($conby, 2).' '
            .$ptc['x'].','.$ptc['y'].' ';
    }
    return trim($pat);
}

# Builds a smooth closed SVG area path from metric history for filled chart rendering
function getSmoothAreaPath(array $hist, float $max, int $ht = 220, int $wt = 100, int $px = 0): string {
    $pts = getHistoryPoints($hist, $max, $ht, $wt, $px);
    if (!$pts) return 'M'.$px.','.$ht.' L'.($wt - $px).','.$ht.' Z';
    $fir = $pts[0];
    $pat = 'M'.$px.','.$ht.' L'.$fir['x'].','.$fir['y'].' ';
    $nn = count($pts);
    if ($nn === 1) {
        $pat .= 'L'.($wt - $px).','.$ht.' Z';
        return $pat;
    }
    for ($ix = 0; $ix < $nn - 1; $ix++) {
        $pta = ($ix > 0) ? $pts[$ix - 1] : $pts[$ix];
        $ptb = $pts[$ix];
        $ptc = $pts[$ix + 1];
        $ptd = ($ix + 2 < $nn) ? $pts[$ix + 2] : $ptc;
        $conax = $ptb['x'] + (($ptc['x'] - $pta['x']) / 6);
        $conay = $ptb['y'] + (($ptc['y'] - $pta['y']) / 6);
        $conbx = $ptc['x'] - (($ptd['x'] - $ptb['x']) / 6);
        $conby = $ptc['y'] - (($ptd['y'] - $ptb['y']) / 6);
        $pat .= 'C'.round($conax, 2).','.round($conay, 2).' '.round($conbx, 2).','.round($conby, 2).' '.$ptc['x'].','.$ptc['y'].' ';
    }
    $pat .= 'L'.($wt - $px).','.$ht.' Z';
    return trim($pat);
}

# Builds normalized SVG points from metric history for chart lines and hover markers
function getHistoryPoints(array $hist, float $max, int $ht = 220, int $wt = 100, int $px = 0): array {
    $hist = array_values(array_map('floatval', $hist));
    if (!$hist) return [];
    $cnt = count($hist);
    $max = max($max, 1.0);
    $iw = max($wt - ($px * 2), 1);
    $pts = [];
    foreach ($hist as $ix => $val) {
        $xx = $px + (($cnt > 1) ? ($ix * ($iw / ($cnt - 1))) : 0.0);
        $yy = $ht - (($val / $max) * ($ht - 10));
        if ($yy < 0) $yy = 0;
        if ($yy > $ht) $yy = $ht;
        $pts[] = ['x' => round($xx, 2), 'y' => round($yy, 2), 'value' => round($val, 2)];
    }
    return $pts;
}

# Samples evenly distributed points from history for aligned hover markers and tooltips
function getHistoryBucketPoints(array $hist, float $max, int $ht = 220, int $wt = 100, int $px = 0, int $cnt = 6): array {
    $pts = getHistoryPoints($hist, $max, $ht, $wt, $px);
    if (!$pts || $cnt <= 0) return [];
    if (count($pts) === 1) return array_fill(0, $cnt, $pts[0]);
    $last = count($pts) - 1;
    $out = [];
    for ($ix = 0; $ix < $cnt; $ix++) {
        $pos = ($cnt > 1) ? ($ix * ($last / ($cnt - 1))) : 0.0;
        $out[] = $pts[(int)round($pos)];
    }
    return $out;
}

# Returns the nearest point from a series for a given hover bucket index
function getHistoryMarkerPoint(array $pts, int $ix, int $cnt = 6): array {
    $nn = count($pts);
    if ($nn <= 0) return [];
    if ($nn === 1) return $pts[0];
    $pos = ($cnt > 1) ? ($ix * (($nn - 1) / ($cnt - 1))) : 0.0;
    return $pts[(int)round($pos)] ?? $pts[$nn - 1];
}

# Returns the localized monitor time label
function getMonitorChartTimeLabel(int $sec, bool $now = false): string {
    if ($now) return _MONITOR_TIME_NOW;
    return sprintf(_MONITOR_TIME_AGO, $sec);
}

# Builds the monitor traffic chart SVG with grid, hover zones, cursor lines, and live tips
function getMonitorChartSvg(array $snap): string {
    $uh = is_array($snap['hist_up'] ?? null) ? $snap['hist_up'] : [];
    $dh = is_array($snap['hist_down'] ?? null) ? $snap['hist_down'] : [];
    $ch = is_array($snap['hist_cpu'] ?? null) ? $snap['hist_cpu'] : [];
    $rh = is_array($snap['hist_ram'] ?? null) ? $snap['hist_ram'] : [];
    $ww = 900;
    $hh = 285;
    $pb = 222;
    $px = 8;
    $bc = 6;
    $tw = 148;
    $th = 104;
    $st = 4;
    $xs = [];
    for ($ix = 0; $ix < $bc; $ix++) {
        $xs[] = round($px + ($ix * (($ww - ($px * 2)) / ($bc - 1))), 2);
    }
    $vals = array_map('floatval', array_merge($uh, $dh));
    $mx = $vals ? max(1.0, max($vals)) : 1.0;
    $up = getHistoryPoints($uh, $mx, $pb, $ww, $px);
    $ul = getSmoothLinePath($uh, $mx, $pb, $ww, $px);
    $dl = getSmoothLinePath($dh, $mx, $pb, $ww, $px);
    $cl = getSmoothLinePath($ch, 100.0, $pb, $ww, $px);
    $rl = getSmoothLinePath($rh, 100.0, $pb, $ww, $px);
    $ua = getSmoothAreaPath($uh, $mx, $pb, $ww, $px);
    $da = getSmoothAreaPath($dh, $mx, $pb, $ww, $px);
    $ca = getSmoothAreaPath($ch, 100.0, $pb, $ww, $px);
    $ra = getSmoothAreaPath($rh, 100.0, $pb, $ww, $px);
    $upb = getHistoryBucketPoints($uh, $mx, $pb, $ww, $px, $bc);
    $dpb = getHistoryBucketPoints($dh, $mx, $pb, $ww, $px, $bc);
    $cpb = getHistoryBucketPoints($ch, 100.0, $pb, $ww, $px, $bc);
    $rpb = getHistoryBucketPoints($rh, 100.0, $pb, $ww, $px, $bc);
    $gy = [42, 82, 122, 162, 202];
    $al = ['100', '75', '50', '25', '0'];
    $xl = [];
    for ($ix = 0; $ix < $bc; $ix++) {
        $sec = (($bc - 1 - $ix) * $st);
        $xl[] = getMonitorChartTimeLabel($sec, $sec === 0);
    }
    $svg = [];
    $svg[] = '<svg class="sl-chart-svg" viewBox="0 0 '.$ww.' '.$hh.'" preserveAspectRatio="none" role="img" aria-label="Traffic monitoring chart">';
    $svg[] = '  <defs>';
    $svg[] = '    <linearGradient id="slMonitorAreaUp" x1="0" x2="0" y1="0" y2="1">';
    $svg[] = '      <stop offset="0" stop-color="var(--sl-monitor-orange)" stop-opacity="0.55" />';
    $svg[] = '      <stop offset="1" stop-color="var(--sl-monitor-orange)" stop-opacity="0.06" />';
    $svg[] = '    </linearGradient>';
    $svg[] = '    <linearGradient id="slMonitorAreaDown" x1="0" x2="0" y1="0" y2="1">';
    $svg[] = '      <stop offset="0" stop-color="var(--sl-monitor-green)" stop-opacity="0.5" />';
    $svg[] = '      <stop offset="1" stop-color="var(--sl-monitor-green)" stop-opacity="0.05" />';
    $svg[] = '    </linearGradient>';
    $svg[] = '    <linearGradient id="slMonitorAreaCpu" x1="0" x2="0" y1="0" y2="1">';
    $svg[] = '      <stop offset="0" stop-color="var(--sl-monitor-red)" stop-opacity="0.34" />';
    $svg[] = '      <stop offset="1" stop-color="var(--sl-monitor-red)" stop-opacity="0.04" />';
    $svg[] = '    </linearGradient>';
    $svg[] = '    <linearGradient id="slMonitorAreaRam" x1="0" x2="0" y1="0" y2="1">';
    $svg[] = '      <stop offset="0" stop-color="var(--sl-monitor-blue)" stop-opacity="0.34" />';
    $svg[] = '      <stop offset="1" stop-color="var(--sl-monitor-blue)" stop-opacity="0.04" />';
    $svg[] = '    </linearGradient>';
    $svg[] = '    <filter id="slMonitorGlow" x="-10%" y="-10%" width="120%" height="120%">';
    $svg[] = '      <feDropShadow dx="0" dy="1" stdDeviation="0.8" flood-color="var(--sl-color-text)" flood-opacity="0.1" />';
    $svg[] = '    </filter>';
    $svg[] = '  </defs>';
    $vgrid = [];
    foreach ($xs as $xx) {
        $vgrid[] = 'M'.$xx.' 42V222';
    }
    $svg[] = '  <path class="sl-chart-gridline" d="M8 42H892M8 82H892M8 122H892M8 162H892M8 202H892"/>';
    $svg[] = '  <path class="sl-chart-vgrid" d="'.implode('', $vgrid).'"/>';
    foreach ($gy as $ix => $y) {
        $svg[] = '  <text class="sl-chart-value-label" x="28" y="'.((int)$y + 3).'">'.$al[$ix].'</text>';
    }
    $svg[] = '  <path class="sl-chart-area sl-chart-area-up" d="'.$ua.'" fill="url(#slMonitorAreaUp)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-area sl-chart-area-down" d="'.$da.'" fill="url(#slMonitorAreaDown)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-area sl-chart-area-cpu" d="'.$ca.'" fill="url(#slMonitorAreaCpu)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-area sl-chart-area-ram" d="'.$ra.'" fill="url(#slMonitorAreaRam)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-line sl-chart-line-up" d="'.$ul.'" stroke="var(--sl-monitor-orange)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-line sl-chart-line-down" d="'.$dl.'" stroke="var(--sl-monitor-green)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-line sl-chart-line-cpu" d="'.$cl.'" stroke="var(--sl-monitor-red)" filter="url(#slMonitorGlow)" />';
    $svg[] = '  <path class="sl-chart-line sl-chart-line-ram" d="'.$rl.'" stroke="var(--sl-monitor-blue)" filter="url(#slMonitorGlow)" />';
    for ($ix = 0; $ix < $bc; $ix++) {
        $up = $upb[$ix] ?? null;
        $dp = $dpb[$ix] ?? null;
        $cp = $cpb[$ix] ?? null;
        $rp = $rpb[$ix] ?? null;
        $fp = getHistoryMarkerPoint($cpb, $ix, $bc);
        $xx = (float)$xs[$ix];
        $stt = ($ix === 0) ? $px : round((($xs[$ix - 1]) + $xx) / 2, 2);
        $end = ($ix === $bc - 1) ? ($ww - $px) : round(($xx + ($xs[$ix + 1])) / 2, 2);
        $wid = max($end - $stt, 1);
        $tx = $xx + 14;
        if ($tx + $tw > $ww - $px) $tx = $xx - $tw - 14;
        if ($tx < $px) $tx = $px;
        $fy = (float)($fp['y'] ?? ($cp['y'] ?? $pb));
        $ty = (($fy + $th + 18) > ($hh - 8)) ? max($fy - $th - 16, 8) : 56;
        $tm = time() - (($bc - 1 - $ix) * $st);
        $lb = getMonitorChartTimeLabel((int)(($bc - 1 - $ix) * $st), $ix === $bc - 1);
        $hd = date('H:i:s', $tm).' · '.$lb;
        $svg[] = '  <g class="sl-chart-day">';
        $svg[] = '    <line class="sl-chart-dayline" x1="'.$xx.'" y1="42" x2="'.$xx.'" y2="222"/>';
        $svg[] = '    <circle class="sl-chart-focus" cx="'.$xx.'" cy="'.$fy.'" r="5"/>';
        $svg[] = '    <g class="sl-chart-tip" transform="translate('.round($tx, 2).' '.$ty.')">';
        $svg[] = '      <rect width="'.$tw.'" height="'.$th.'" rx="5"/>';
        $svg[] = '      <text class="sl-chart-tip-title" x="9" y="18">'.htmlspecialchars($hd, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</text>';
        $svg[] = '      <text class="muted" x="9" y="39">CPU: '.htmlspecialchars((string)round((float)($cp['value'] ?? 0), 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'%</text>';
        $svg[] = '      <text class="muted" x="9" y="57">RAM: '.htmlspecialchars((string)round((float)($rp['value'] ?? 0), 1), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'%</text>';
        $svg[] = '      <text class="muted" x="9" y="75">Upstream: '.htmlspecialchars(filterSize((float)($up['value'] ?? 0)).'/s', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</text>';
        $svg[] = '      <text class="muted" x="9" y="93">Downstream: '.htmlspecialchars(filterSize((float)($dp['value'] ?? 0)).'/s', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</text>';
        $svg[] = '    </g>';
        $svg[] = '    <rect class="sl-chart-hover-zone" x="'.$stt.'" y="35" width="'.$wid.'" height="218"/>';
        $svg[] = '  </g>';
    }
    foreach ($xs as $ix => $xx) {
        $svg[] = '  <text class="sl-chart-axis-label" x="'.round($xx, 2).'" y="252">'.$xl[$ix].'</text>';
    }
    $svg[] = '</svg>';
    return implode("\n", $svg);
}

# Calculates realtime network rates, updates history buffers, and persists metric snapshots
function getRealtimePanelMetrics(float $cpupct, float $rampct): array {
    $now = time();
    $net = getNetworkStats();
    $store = getMetricStore();
    $prevts = (int)($store['net_prev_ts'] ?? 0);
    $prevrx = (float)($store['net_prev_rx'] ?? 0);
    $prevtx = (float)($store['net_prev_tx'] ?? 0);
    $dt = max($now - $prevts, 1);
    $rxrate = ($prevts > 0) ? max(($net['rx'] - $prevrx) / $dt, 0) : 0.0;
    $txrate = ($prevts > 0) ? max(($net['tx'] - $prevtx) / $dt, 0) : 0.0;
    $hstdn = is_array($store['net_hist_down'] ?? null) ? $store['net_hist_down'] : [];
    $hstup = is_array($store['net_hist_up'] ?? null) ? $store['net_hist_up'] : [];
    $hstcpu = is_array($store['sys_hist_cpu'] ?? null) ? $store['sys_hist_cpu'] : [];
    $hstram = is_array($store['sys_hist_ram'] ?? null) ? $store['sys_hist_ram'] : [];
    $hstdn = addHistory($hstdn, $rxrate);
    $hstup = addHistory($hstup, $txrate);
    $hstcpu = addHistory($hstcpu, max(min($cpupct, 100), 0));
    $hstram = addHistory($hstram, max(min($rampct, 100), 0));
    $store['net_prev_ts'] = $now;
    $store['net_prev_rx'] = $net['rx'];
    $store['net_prev_tx'] = $net['tx'];
    $store['net_hist_down'] = $hstdn;
    $store['net_hist_up'] = $hstup;
    $store['sys_hist_cpu'] = $hstcpu;
    $store['sys_hist_ram'] = $hstram;
    setMetricStore($store);
    return [
        'rx_total' => (float)$net['rx'],
        'tx_total' => (float)$net['tx'],
        'rx_rate' => $rxrate,
        'tx_rate' => $txrate,
        'hist_down' => $hstdn,
        'hist_up' => $hstup,
        'hist_cpu' => $hstcpu,
        'hist_ram' => $hstram,
    ];
}

# Reads cumulative disk read and write bytes from Linux diskstats for whole block devices only
function getDiskIoTotals(): array {
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) return ['read' => 0.0, 'write' => 0.0, 'ok' => false];
    $file = '/proc/diskstats';
    if (!isPathAllowed($file) || !is_file($file) || !is_readable($file)) return ['read' => 0.0, 'write' => 0.0, 'ok' => false];
    $read = 0.0;
    $write = 0.0;
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return ['read' => 0.0, 'write' => 0.0, 'ok' => false];
    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (!is_array($parts) || count($parts) < 14) continue;
        $name = $parts[2] ?? '';
        if (!preg_match('/^(sd[a-z]+|hd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme\d+n\d+|mmcblk\d+|md\d+|dm-\d+)$/', (string)$name)) continue;
        $readsec = (float)($parts[5] ?? 0);
        $writesec = (float)($parts[9] ?? 0);
        $read += $readsec * 512;
        $write += $writesec * 512;
    }
    return ['read' => $read, 'write' => $write, 'ok' => true];
}

# Calculates disk read and write rates from cumulative counters and updates history buffers
function getDiskIoMetrics(): array {
    $now = time();
    $totals = getDiskIoTotals();
    if (!$totals['ok']) {
        return [
            'read_rate' => null,
            'write_rate' => null,
            'hist_read' => [],
            'hist_write' => [],
        ];
    }
    $store = getMetricStore();
    $prevts = (int)($store['disk_prev_ts'] ?? 0);
    $prevread = (float)($store['disk_prev_read'] ?? 0);
    $writold = (float)($store['disk_prev_write'] ?? 0);
    $dt = max($now - $prevts, 1);
    $readrate = ($prevts > 0) ? max(($totals['read'] - $prevread) / $dt, 0) : 0.0;
    $wrtrate = ($prevts > 0) ? max(($totals['write'] - $writold) / $dt, 0) : 0.0;
    $histread = is_array($store['disk_hist_read'] ?? null) ? $store['disk_hist_read'] : [];
    $histwrt = is_array($store['disk_hist_write'] ?? null) ? $store['disk_hist_write'] : [];
    $histread = addHistory($histread, $readrate);
    $histwrt = addHistory($histwrt, $wrtrate);
    $store['disk_prev_ts'] = $now;
    $store['disk_prev_read'] = $totals['read'];
    $store['disk_prev_write'] = $totals['write'];
    $store['disk_hist_read'] = $histread;
    $store['disk_hist_write'] = $histwrt;
    setMetricStore($store);
    return [
        'read_rate' => $readrate,
        'write_rate' => $wrtrate,
        'hist_read' => $histread,
        'hist_write' => $histwrt,
    ];
}

# Returns human-readable system uptime from platform sources with graceful fallback behavior
function getUptimeInfo(): string {
    if (!str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $data = getFileSafe('/proc/uptime');
        if ($data !== false) {
            $sec = (int)floatval(explode(' ', trim($data))[0] ?? 0);
            if ($sec > 0) return getUptimeText($sec);
        }
    } elseif (function_exists('exec')) {
        $out = [];
        exec("powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command \"(Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToString('yyyy-MM-dd HH:mm:ss')\"", $out);
        $boot = trim((string)($out[0] ?? ''));
        $bootts = $boot !== '' ? strtotime($boot) : false;
        if ($bootts !== false) {
            $sec = max(time() - $bootts, 0);
            return getUptimeText($sec);
        }
    }
    return 'N/A';
}

# Formats uptime seconds into a compact days, hours, minutes, and seconds text representation
function getUptimeText(int $sec): string {
    $days = intdiv($sec, 86400);
    $hours = intdiv($sec % 86400, 3600);
    $mins = intdiv($sec % 3600, 60);
    return $days.'d '.$hours.'h '.$mins.'m';
}

# Collects raw CPU metadata including logical and physical cores and current base frequency
function getCpuDetailsRaw(): array {
    $logical = getCpuCores();
    $physical = 0;
    $freq = 'N/A';
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        if (function_exists('exec')) {
            $out = [];
            exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfCores -Sum).Sum"', $out);
            if (!empty($out) && is_numeric(trim((string)$out[0]))) $physical = (int)trim((string)$out[0]);
            $out = [];
            exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Select-Object -First 1 -ExpandProperty MaxClockSpeed)"', $out);
            if (!empty($out) && is_numeric(trim((string)$out[0]))) {
                $mhz = (int)trim((string)$out[0]);
                if ($mhz > 0) $freq = round($mhz / 1000, 2).' GHz';
            }
        }
    } else {
        $cpuinfo = getFileSafe('/proc/cpuinfo');
        $corespersocket = 0;
        $sockets = 0;
        if (($cpuinfo === false || $cpuinfo === '') && function_exists('exec')) {
            $out = [];
            exec('cat /proc/cpuinfo 2>/dev/null', $out);
            if (!empty($out)) $cpuinfo = implode("\n", $out);
        }
        if ($cpuinfo !== false) {
            preg_match_all('/^physical id\s*:\s*(\d+)/m', $cpuinfo, $physids);
            preg_match_all('/^core id\s*:\s*(\d+)/m', $cpuinfo, $coreids);
            if (!empty($physids[1]) && !empty($coreids[1]) && count($physids[1]) === count($coreids[1])) {
                $pairs = [];
                foreach ($physids[1] as $kx => $pid) {
                    $pairs[] = $pid.'-'.$coreids[1][$kx];
                }
                $physical = count(array_unique($pairs));
            }
            if ($physical <= 0) {
                preg_match('/^cpu cores\s*:\s*(\d+)/m', $cpuinfo, $mx);
                if (!empty($mx[1])) $physical = (int)$mx[1];
            }
            preg_match('/^cpu MHz\s*:\s*([0-9.]+)/m', $cpuinfo, $mhz);
            if (!empty($mhz[1])) $freq = round(((float)$mhz[1]) / 1000, 2).' GHz';
        }
        if (($physical <= 0 || $freq === 'N/A') && function_exists('exec')) {
            $out = [];
            exec('LC_ALL=C lscpu 2>/dev/null', $out);
            if (!empty($out)) {
                foreach ($out as $line) {
                    if ($physical <= 0 && preg_match('/^Core\\(s\\) per socket:\\s*(\\d+)/i', $line, $mx)) {
                        $corespersocket = (int)$mx[1];
                    }
                    if ($physical <= 0 && preg_match('/^Socket\\(s\\):\\s*(\\d+)/i', $line, $mx)) {
                        $sockets = (int)$mx[1];
                    }
                    if ($freq === 'N/A' && preg_match('/^(CPU max MHz|CPU MHz):\\s*([0-9.]+)/i', $line, $mx)) {
                        $freq = round(((float)$mx[2]) / 1000, 2).' GHz';
                    }
                }
                if ($physical <= 0 && !empty($corespersocket) && !empty($sockets)) $physical = $corespersocket * $sockets;
            }
        }
    }
    return [
        'logical' => max($logical, 1),
        'physical' => ($physical > 0) ? (string)$physical : 'N/A',
        'freq' => $freq,
    ];
}

# Returns cached CPU metadata when fresh, otherwise refreshes and persists CPU details cache
function getCpuDetails(): array {
    static $reqcache = null;
    if (is_array($reqcache)) return $reqcache;
    $ttl = 120;
    $cachekey = 'slaed_monitor_cpu_details_v1';
    if (is_callable('apcu_fetch') && is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        $ok = false;
        $cached = apcu_fetch($cachekey, $ok);
        if ($ok && is_array($cached) && isset($cached['data'], $cached['ts']) && (time() - (int)$cached['ts']) <= $ttl) {
            $reqcache = $cached['data'];
            return $reqcache;
        }
    }
    $fresh = getCpuDetailsRaw();
    $reqcache = $fresh;
    if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        apcu_store($cachekey, ['ts' => time(), 'data' => $fresh], $ttl);
    }
    return $fresh;
}

# Formats boolean status into colored HTML badges used by monitor status indicators
function getStatusHtml(?bool $state): string {
    global $tpl;
    if ($state === null) return $tpl->getHtmlFrag('inline-badge', ['is_muted' => true, 'label' => 'N/A']);
    return $tpl->getHtmlFrag('inline-badge', [
        'is_success' => $state,
        'is_danger' => !$state,
        'label' => $state ? 'On' : 'Off',
    ]);
}

# Queries database runtime health metrics and returns normalized diagnostics for monitor output
function getDbHealth(object $db): array {
    $data = [
        'connections' => 'N/A',
        'slow' => 'N/A',
        'charset' => 'N/A',
        'sql_mode' => 'N/A',
        'max_packet' => 'N/A',
        'buffer_pool' => 'N/A',
        'timezone' => 'N/A',
        'user' => 'N/A'
    ];
    try {
        $res = $db->getSqlQuery("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['connections'] = (string)$row['Value'];
        $res = $db->getSqlQuery("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['slow'] = (string)$row['Value'];
        $res = $db->getSqlQuery("SHOW VARIABLES LIKE 'character_set_connection'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['charset'] = (string)$row['Value'];
        $res = $db->getSqlQuery("SHOW VARIABLES LIKE 'sql_mode'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['sql_mode'] = (string)$row['Value'];
        $res = $db->getSqlQuery("SHOW VARIABLES LIKE 'max_allowed_packet'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['max_packet'] = filterSize((int)$row['Value']);
        $res = $db->getSqlQuery("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['Value'])) $data['buffer_pool'] = filterSize((int)$row['Value']);
        $tz = 'N/A';
        $res = $db->getSqlQuery("SELECT CURRENT_TIME() as db_time");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_time'])) $tz = (string)$row['db_time'];
        $data['timezone'] = $tz;
        $res = $db->getSqlQuery("SELECT CURRENT_USER() as db_user");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_user'])) $data['user'] = (string)$row['db_user'];
    } catch (Throwable $error) {
        if (class_exists('Logger')) Logger::addSql('error', 'Monitor DB health read failed', ['error' => $error->getMessage()]);
    }
    return $data;
}


# Calculates the cumulative size of regular files in a readable directory tree; returns null when unavailable
function getDirectorySizeBytes(string $path): int|null {
    if (!is_dir($path) || !is_readable($path)) return null;
    $size = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) continue;
            if ($item->isLink() || !$item->isFile()) continue;
            $size += $item->getSize();
        }
    } catch (Throwable) {
        return null;
    }
    return max($size, 0);
}

# Returns size metrics for key storage folders and null when folders are unavailable
function getStorageDirectorySizes(): array {
    $baksize = defined('BACKUP_DIR') ? getDirectorySizeBytes((string)BACKUP_DIR) : null;
    $cachesz = defined('CACHE_DIR') ? getDirectorySizeBytes((string)CACHE_DIR) : null;
    $logsize = defined('LOGS_DIR') ? getDirectorySizeBytes((string)LOGS_DIR) : null;
    return [
        'backup' => $baksize,
        'cache' => $cachesz,
        'logs' => $logsize,
    ];
}

# Finds newest file modification timestamp inside a directory tree excluding symlinks
function getLatestFileMTime(string $dir): int|null {
    if (!is_dir($dir) || !is_readable($dir)) return null;
    $latest = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item instanceof SplFileInfo) continue;
            if (!$item->isFile() || $item->isLink()) continue;
            $mtime = $item->getMTime();
            if ($mtime > $latest) $latest = $mtime;
        }
    } catch (Throwable) {
        return null;
    }
    return ($latest > 0) ? $latest : null;
}

# Returns formatted timestamp of latest backup file or N/A when no backups are found
function getLastBackupRunLabel(): string {
    $state = getSchedulerState('dbbackup');
    $ts = (int)($state['last_success'] ?? 0);
    if ($ts > 0) return date('Y-m-d H:i:s', $ts);
    $bakdir = defined('BACKUP_DIR') ? (string)BACKUP_DIR : BASE_DIR.'/storage/backup';
    $mtime = getLatestFileMTime($bakdir);
    if ($mtime !== null) return date('Y-m-d H:i:s', $mtime);
    return 'N/A';
}

# Counts recent error log entries within a time window using bounded tail parsing
function getErrorLogCountHours(int $hours = 24): int|string {
    $logfile = (defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs').'/error_file.log';
    if (!is_file($logfile) || !is_readable($logfile)) return 'N/A';
    $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || !is_array($lines)) return 'N/A';
    if (!$lines) return 0;
    $thresh = time() - ($hours * 3600);
    $count = 0;
    for ($ix = count($lines) - 1; $ix >= 0; $ix--) {
        $line = (string)$lines[$ix];
        if (!preg_match('/^\[([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})\]/', $line, $mx)) continue;
        $ts = strtotime($mx[1]);
        if ($ts === false) continue;
        if ($ts < $thresh) break;
        $count++;
    }
    return $count;
}

# Reads the last bytes of a log file safely for efficient tail-based analysis
function getFileTailChunk(string $filepath, int $maxbytes = 262144): string {
    if (!is_file($filepath) || !is_readable($filepath)) return '';
    $size = filesize($filepath);
    if ($size === false || $size <= 0) return '';
    $readlen = min(max($maxbytes, 4096), (int)$size);
    $handle = fopen($filepath, 'rb');
    if ($handle === false) return '';
    if ($size > $readlen) fseek($handle, -$readlen, SEEK_END);
    $data = stream_get_contents($handle);
    fclose($handle);
    return is_string($data) ? $data : '';
}

# Splits tail text into normalized lines and limits output to the requested maximum count
function getTailLines(string $taildata, int $maxlines = 2000): array {
    if ($taildata === '') return [];
    $lines = preg_split('/\R/u', $taildata) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn($vx) => $vx !== ''));
    if (count($lines) > $maxlines) $lines = array_slice($lines, -$maxlines);
    return $lines;
}

# Extracts unix timestamp from a log line using multiple supported datetime patterns
function getLogLineTimestamp(string $line): int|null {
    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $mx)) {
        $ts = strtotime($mx[1]);
        if ($ts !== false) return $ts;
    }
    if (preg_match('/"ts"\s*:\s*"([^"]+)"/', $line, $mx)) {
        $ts = strtotime($mx[1]);
        if ($ts !== false) return $ts;
    }
    if (preg_match('/(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2})/', $line, $mx)) {
        $dt = DateTime::createFromFormat('d.m.Y H:i:s', $mx[1]);
        if ($dt instanceof DateTime) return $dt->getTimestamp();
    }
    return null;
}

# Returns formatted uploads directory size or N/A when directory size is unavailable
function getUploadsSizeLabel(): string {
    $updir = defined('UPLOADS_DIR') ? (string)UPLOADS_DIR : BASE_DIR.'/uploads';
    $bytes = getDirectorySizeBytes($updir);
    return ($bytes === null) ? 'N/A' : filterSize((int)$bytes);
}

# Counts failed login events in recent logs within the specified hour interval
function getFailedLoginCountHours(int $hours = 24): int|string {
    $file = (defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs').'/log_admin.log';
    if (!is_file($file) || !is_readable($file)) return 'N/A';
    $tail = getFileTailChunk($file, 262144);
    $lines = getTailLines($tail, 2500);
    if (!$lines) return 0;
    $thresh = time() - ($hours * 3600);
    $count = 0;
    $block = [];
    $isbound = static fn(string $line): bool => preg_match('/^-{3,}$/', $line) === 1;
    $hasauth = static fn(string $text): bool =>
        preg_match('/(login|auth|вход)/iu', $text) === 1;
    $isfailed = static fn(string $text): bool =>
        preg_match('/(\bno\b|нет|fail|failed|denied|invalid|unauthori[sz]ed|blocked)/iu', $text) === 1;
    $flushfn = static function(array $rows) use (&$count, $thresh, $hasauth, $isfailed): void {
        if (!$rows) return;
        $text = implode("\n", $rows);
        if (!$hasauth($text)) return;
        if (!$isfailed($text)) return;
        $ts = null;
        foreach ($rows as $row) {
            $ts = getLogLineTimestamp($row);
            if ($ts !== null) break;
        }
        if ($ts !== null && $ts >= $thresh) $count++;
    };
    foreach ($lines as $line) {
        if ($isbound($line)) {
            $flushfn($block);
            $block = [];
            continue;
        }
        $block[] = $line;
    }
    $flushfn($block);
    return $count;
}

# Returns latest recent security-related event with source label and timestamp context
function getSecurityEventHours(int $hours = 24): string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['warn.log', 'hack.log', 'error_site.log', 'error_php.log', 'log.log'];
    $thresh = time() - ($hours * 3600);
    $bestts = 0;
    $besttxt = '';
    $seen = false;
    foreach ($files as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $seen = true;
        $tail = getFileTailChunk($path, 262144);
        $lines = getTailLines($tail, 2500);
        if (!$lines) continue;
        for ($ix = count($lines) - 1; $ix >= 0; $ix--) {
            $line = $lines[$ix];
            $ts = getLogLineTimestamp($line);
            if ($ts === null || $ts < $thresh) continue;
            if ($ts <= $bestts) continue;
            $bestts = $ts;
            $besttxt = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.(string)preg_replace('/\s+/', ' ', trim($line));
            break;
        }
    }
    if (!$seen) return 'N/A';
    if ($bestts <= 0) return '0';
    return date('Y-m-d H:i:s', $bestts).' | '.$besttxt;
}

# Returns latest recent database-related issue event matched by error patterns
function getDbIssueEventHours(int $hours = 24): string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['error_sql.log', 'error_file.log', 'error_php.log'];
    $thresh = time() - ($hours * 3600);
    $bestts = 0;
    $besttxt = '';
    $seen = false;
    $dbmatch = '/(sqlstate|mysql|mysqli|mariadb|database|pdo|deadlock|lock wait|too many connections|connection refused|access denied)/iu';
    foreach ($files as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $seen = true;
        $tail = getFileTailChunk($path, 262144);
        $lines = getTailLines($tail, 2500);
        if (!$lines) continue;
        for ($ix = count($lines) - 1; $ix >= 0; $ix--) {
            $line = $lines[$ix];
            if (preg_match($dbmatch, $line) !== 1) continue;
            $ts = getLogLineTimestamp($line);
            if ($ts === null || $ts < $thresh) continue;
            if ($ts <= $bestts) continue;
            $bestts = $ts;
            $besttxt = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.(string)preg_replace('/\s+/', ' ', trim($line));
            break;
        }
    }
    if (!$seen) return 'N/A';
    if ($bestts <= 0) return '0';
    return date('Y-m-d H:i:s', $bestts).' | '.$besttxt;
}


# Collects disk capacity and usage values used by both realtime and full dashboard render paths
function getMonitorDiskSnapshot(): array {
    $disksum = (float)disk_total_space('.');
    $diskfree = (float)disk_free_space('.');
    $diskused = max($disksum - $diskfree, 0);
    $diskpct = ($disksum > 0) ? round(($diskused / $disksum) * 100, 1) : 0;
    return [
        'disk_total' => $disksum,
        'disk_free' => $diskfree,
        'disk_used' => $diskused,
        'disk_pct' => (float)$diskpct,
    ];
}

# Builds complete monitor snapshot with system, network, and chart path values
function getMonitorPanelSnapshot(): array {
    static $reqcache = null;
    if (is_array($reqcache)) return $reqcache;
    $ttl = 3;
    $cachekey = 'slaed_monitor_panel_snapshot_v1';
    if (is_callable('apcu_fetch') && is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        $ok = false;
        $cached = apcu_fetch($cachekey, $ok);
        if ($ok && is_array($cached) && isset($cached['data'], $cached['ts']) && (time() - (int)$cached['ts']) <= $ttl) {
            $reqcache = $cached['data'];
            return $reqcache;
        }
    }
    [$cpup] = getCpuLoad();
    $cpu = getCpuDetails();
    $mem = getMemoryInfo();
    $disk = getMonitorDiskSnapshot();
    $live = getRealtimePanelMetrics((float)$cpup, (float)$mem['percent']);
    $netmax = max(array_merge([1.0], $live['hist_up'], $live['hist_down']));
    $pathup = getSmoothAreaPath($live['hist_up'], $netmax);
    $pathdown = getSmoothAreaPath($live['hist_down'], $netmax);
    $upline = getSmoothLinePath($live['hist_up'], $netmax);
    $downlin = getSmoothLinePath($live['hist_down'], $netmax);
    $pathcpu = getSmoothAreaPath($live['hist_cpu'], 100.0);
    $pathram = getSmoothAreaPath($live['hist_ram'], 100.0);
    $cpuline = getSmoothLinePath($live['hist_cpu'], 100.0);
    $ramline = getSmoothLinePath($live['hist_ram'], 100.0);
    $reqcache = [
        'cpu_p' => (float)$cpup,
        'cpu' => $cpu,
        'mem' => $mem,
        'disk_total' => $disk['disk_total'],
        'disk_free' => $disk['disk_free'],
        'disk_used' => $disk['disk_used'],
        'disk_pct' => $disk['disk_pct'],
        'net' => [
            'rx_total' => $live['rx_total'],
            'tx_total' => $live['tx_total'],
            'rx_rate' => $live['rx_rate'],
            'tx_rate' => $live['tx_rate'],
        ],
        'hist_down' => $live['hist_down'],
        'hist_up' => $live['hist_up'],
        'hist_cpu' => $live['hist_cpu'],
        'hist_ram' => $live['hist_ram'],
        'path_up' => $pathup,
        'path_down' => $pathdown,
        'path_cpu' => $pathcpu,
        'path_ram' => $pathram,
        'path_up_line' => $upline,
        'path_down_line' => $downlin,
        'path_cpu_line' => $cpuline,
        'path_ram_line' => $ramline,
    ];
    if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        apcu_store($cachekey, ['ts' => time(), 'data' => $reqcache], $ttl);
    }
    return $reqcache;
}

# Builds template variables for realtime traffic panel including paths and tooltips
function getTrafficPanelVars(?array $snapshot = null): array {
    $snapshot = $snapshot ?? getMonitorPanelSnapshot();
    $cpup = (float)$snapshot['cpu_p'];
    $cpu = $snapshot['cpu'];
    $mem = $snapshot['mem'];
    $net = $snapshot['net'];
    return [
        'path_up' => $snapshot['path_up'],
        'path_down' => $snapshot['path_down'],
        'path_cpu' => $snapshot['path_cpu'],
        'path_ram' => $snapshot['path_ram'],
        'path_up_line' => $snapshot['path_up_line'],
        'path_down_line' => $snapshot['path_down_line'],
        'path_cpu_line' => $snapshot['path_cpu_line'],
        'path_ram_line' => $snapshot['path_ram_line'],
        'chartsvg' => getMonitorChartSvg($snapshot),
        'tip_up' => 'Upstream: '.filterSize($net['tx_total']).' ('.filterSize($net['tx_rate']).'/s)',
        'tip_down' => 'Downstream: '.filterSize($net['rx_total']).' ('.filterSize($net['rx_rate']).'/s)',
        'tip_cpu' => 'CPU Usage: '.round((float)$cpup, 1).'%',
        'tip_ram' => 'RAM Usage: '.round((float)$mem['percent'], 1).'% ('.filterSize($mem['used']).' / '.filterSize($mem['total']).')',
        'nettx' => filterSize($net['tx_total']),
        'netrx' => filterSize($net['rx_total']),
        'nettxrate' => filterSize($net['tx_rate']).'/s',
        'netrxrate' => filterSize($net['rx_rate']).'/s',
        'cpuuse' => round((float)$cpup, 1),
        'cpufreq' => $cpu['freq'],
        'ram_p' => round((float)$mem['percent'], 1),
        'ramtmb' => filterSize($mem['total']),
    ];
}

# Builds template variables for server status gauges, colors, and usage metrics
function getServerStatusVars(?array $snapshot = null): array {
    $snapshot = $snapshot ?? getMonitorPanelSnapshot();
    $cpup = (float)$snapshot['cpu_p'];
    $cpu = $snapshot['cpu'];
    $mem = $snapshot['mem'];
    $disksum = (float)$snapshot['disk_total'];
    $diskused = (float)$snapshot['disk_used'];
    $diskpct = (float)$snapshot['disk_pct'];
    $dash = 2 * M_PI * 45;
    $offload = $dash - ($dash * $cpup / 100);
    $offr = $dash - ($dash * $mem['percent'] / 100);
    $offd = $dash - ($dash * $diskpct / 100);
    $cpuval = (float)$cpup;
    $ramval = (float)$mem['percent'];
    $diskval = (float)$diskpct;
    return [
        'dash' => $dash,
        'off' => $offload,
        'load_0' => round($cpuval, 1),
        'cpu_is_danger' => $cpuval > 95,
        'cpu_is_warning' => $cpuval > 75 && $cpuval <= 95,
        'cpu_is_info' => $cpuval > 50 && $cpuval <= 75,
        'cpucores' => $cpu['logical'],
        'cpuphys' => $cpu['physical'],
        'cpufreq' => $cpu['freq'],
        'dash_r' => $dash,
        'off_r' => $offr,
        'ram_is_danger' => $ramval > 95,
        'ram_is_warning' => $ramval > 75 && $ramval <= 95,
        'ram_is_info' => $ramval > 50 && $ramval <= 75,
        'ram_p' => round($ramval, 1),
        'ramumb' => filterSize($mem['used']),
        'ramtmb' => filterSize($mem['total']),
        'ramavailmb' => filterSize($mem['free']),
        'dash_d' => $dash,
        'off_d' => $offd,
        'disk_is_danger' => $diskval > 95,
        'disk_is_warning' => $diskval > 75 && $diskval <= 95,
        'disk_is_info' => $diskval > 50 && $diskval <= 75,
        'disk_p' => $diskpct,
        'diskused' => filterSize($diskused),
        'disktot' => filterSize($disksum),
    ];
}

# Renders monitor partial HTML for selected panels using merged template variables
function getMonitorPartial(array $snapshot, bool $showstat, bool $showtraf, bool $useoob = false): string {
    global $tpl;
    $vars = array_merge(
        getServerStatusVars($snapshot),
        getTrafficPanelVars($snapshot),
        [
            'status_oob' => ($showstat && $useoob) ? ' hx-swap-oob="outerHTML"' : '',
            'traffic_oob' => ($showtraf && $useoob) ? ' hx-swap-oob="outerHTML"' : '',
            'show_layout' => false,
            'show_status' => $showstat,
            'show_traffic' => $showtraf,
        ]
    );
    return $tpl->getHtmlPart('basic-monitor', $vars);
}

# Collects monitor counts and database size statistics needed for dashboard summary
function getMonitorDbStats(object $db, array $conf): array {
    $userson = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session'));
    $cntfile = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_files WHERE status != '0'"));
    $cntnews = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB."_news WHERE status != '0'"));
    $dbsize = 0;
    $dbtabs = 0;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname !== '') {
        $dbres = $db->getSqlQuery(
            'SELECT DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length FROM information_schema.TABLES WHERE TABLE_SCHEMA = :name',
            ['name' => $dbname]
        );
        while ($row = $db->getSqlRow($dbres)) {
            $dbsize += (int)($row['data_length'] ?? 0) + (int)($row['index_length'] ?? 0);
            $dbtabs++;
        }
    }
    return [
        'userson' => $userson,
        'cntfile' => $cntfile,
        'cntnews' => $cntnews,
        'dbsize' => $dbsize,
        'dbtabs' => $dbtabs,
    ];
}

# Collects web server, firewall, extension, and protocol metadata for dashboard
function getMonitorServerStats(): array {
    global $tpl;
    $servsw = getServerValue('SERVER_SOFTWARE', '');
    $servname = 'Web Server';
    if (stripos($servsw, 'apache') !== false) $servname = 'Apache';
    elseif (stripos($servsw, 'nginx') !== false) $servname = 'Nginx';
    elseif (stripos($servsw, 'litespeed') !== false) $servname = 'LiteSpeed';
    $servver = 'N/A';
    if (preg_match('#/(\\d+(?:\\.\\d+)+)#', (string)$servsw, $vm)) $servver = $vm[1];
    $srvport = getServerValue('SERVER_PORT', 'N/A');
    $https = strtolower(getServerValue('HTTPS', ''));
    $ishttps = ($https === 'on' || $https === '1') || ((int)$srvport === 443);
    $srvhttps = $tpl->getHtmlFrag('inline-badge', [
        'is_success' => $ishttps,
        'is_danger' => !$ishttps,
        'label' => $ishttps ? 'enabled' : 'disabled',
    ]);
    $loaded = get_loaded_extensions();
    $ext_dir = ini_get('extension_dir');
    $off = [];
    $ob = ini_get('open_basedir');
    $ext_accessible = !$ob || array_reduce(
        explode(PATH_SEPARATOR, $ob),
        static fn(bool $c, string $d) => $c || str_starts_with($ext_dir, rtrim($d, '/\\')),
        false
    );
    if ($ext_dir && $ext_accessible && is_dir($ext_dir)) {
        $files = array_merge(glob($ext_dir.'/*.so') ?: [], glob($ext_dir.'/*.dll') ?: []);
        $loaded_lower = array_map('strtolower', $loaded);
        foreach ($files as $f) {
            $name = strtolower(str_replace(['php_', '.dll', '.so'], '', basename($f)));
            if ($name !== '' && !in_array($name, $loaded_lower, true)) {
                $off[] = $name;
            }
        }
    }
    
    $exton = implode(', ', $loaded);
    $extlist_on = $tpl->getHtmlFrag('info-tooltip', ['content' => $exton, 'label_text' => $exton]);
    if (!empty($off)) { $extoff = implode(', ', $off); $extlist_off = $tpl->getHtmlFrag('info-tooltip', ['content' => $extoff, 'label_text' => $extoff]); } else { $extlist_off = 'None / N/A'; }

    return [
        'servsw' => $servsw,
        'servname' => $servname,
        'servver' => $servver,
        'extlist_on' => $extlist_on,
        'extlist_off' => $extlist_off,
        'srvprot' => getServerValue('SERVER_PROTOCOL', 'N/A'),
        'srvname' => getServerValue('SERVER_NAME', 'N/A'),
        'srvport' => $srvport,
        'srvroot' => getServerValue('DOCUMENT_ROOT', 'N/A'),
        'srvhttps' => $srvhttps,
        'serverip' => getServerValue('SERVER_ADDR', 'N/A'),
    ];
}

# Caches expensive runtime extras such as directory scans and log counters across short monitor refresh windows
function getMonitorRuntimeExtras(): array {
    static $reqcache = null;
    if (is_array($reqcache)) return $reqcache;
    $ttl = 60;
    $cachekey = 'slaed_monitor_runtime_extras_v1';
    if (is_callable('apcu_fetch') && is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        $ok = false;
        $cached = apcu_fetch($cachekey, $ok);
        if ($ok && is_array($cached) && isset($cached['data'], $cached['ts']) && (time() - (int)$cached['ts']) <= $ttl) {
            $reqcache = $cached['data'];
            return $reqcache;
        }
    }
    $fresh = [
        'storages' => getStorageDirectorySizes(),
        'lastbackup' => getLastBackupRunLabel(),
        'error24' => getErrorLogCountHours(24),
        'uploadsz' => getUploadsSizeLabel(),
        'failed24' => getFailedLoginCountHours(24),
        'seclast24' => getSecurityEventHours(24),
        'dblast24' => getDbIssueEventHours(24),
    ];
    $reqcache = $fresh;
    if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        apcu_store($cachekey, ['ts' => time(), 'data' => $fresh], $ttl);
    }
    return $fresh;
}

# Collects runtime diagnostics, opcache, storage, and timing metrics for monitor
function getMonitorRuntimeStats(object $db, ?array $snapshot): array {
    global $tpl;
    $disk = ($snapshot !== null)
        ? [
            'disk_total' => (float)$snapshot['disk_total'],
            'disk_free' => (float)$snapshot['disk_free'],
            'disk_used' => (float)$snapshot['disk_used'],
            'disk_pct' => (float)$snapshot['disk_pct'],
        ]
        : getMonitorDiskSnapshot();
    $disktot = (float)$disk['disk_total'];
    $diskfree = (float)$disk['disk_free'];
    $diskused = (float)$disk['disk_used'];
    $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $cacheon = $opcache && !empty($opcache['opcache_enabled']);
    $opmused = $opcache ? $opcache['memory_usage']['used_memory'] : 0;
    $opmfree = $opcache ? $opcache['memory_usage']['free_memory'] : 0;
    $opmwast = $opcache ? $opcache['memory_usage']['wasted_memory'] : 0;
    $opmemtot = $opmused + $opmfree + $opmwast;
    $reqtime = (float)getServerValue('REQUEST_TIME_FLOAT', '0');
    if ($reqtime <= 0) $reqtime = microtime(true);
    $extras = getMonitorRuntimeExtras();
    $islowdisk = ($disktot > 0 && (($diskfree / $disktot) * 100) < 10);
    $diskwarn = $tpl->getHtmlFrag('inline-badge', [
        'is_danger' => $islowdisk,
        'is_success' => !$islowdisk,
        'label' => $islowdisk ? 'Low free space' : 'Normal',
    ]);
    return [
        'diskio' => getDiskIoMetrics(),
        'disktotal' => $disktot,
        'diskfree' => $diskfree,
        'diskused' => $diskused,
        'gdver' => (function_exists('gd_info') ? (gd_info()['GD Version'] ?? 'N/A') : 'N/A'),
        'opcacheon' => $cacheon,
        'opmem' => $cacheon ? filterSize((int)$opmused).' / '.filterSize((int)$opmemtot) : 'N/A',
        'opscripts' => $cacheon ? $opcache['opcache_statistics']['num_cached_scripts'] : 'N/A',
        'ophit' => $cacheon ? round($opcache['opcache_statistics']['opcache_hit_rate'], 1).'%' : 'N/A',
        'uptime' => getUptimeInfo(),
        'dbhealth' => getDbHealth($db),
        'diskwarn' => $diskwarn,
        'storages' => $extras['storages'],
        'lastbackup' => $extras['lastbackup'],
        'error24' => $extras['error24'],
        'uploadsz' => $extras['uploadsz'],
        'failed24' => $extras['failed24'],
        'seclast24' => $extras['seclast24'],
        'dblast24' => $extras['dblast24'],
        'exectime' => round(microtime(true) - $reqtime, 3),
        'dbtime' => round($db->sqltime * 1000, 1),
    ];
}

# Collects current request metadata including cookie dump, URI, IP, and locale
function getMonitorRequestStats(): array {
    $reqcook = 'N/A';
    $cookies = getCookieValues();
    if (!empty($cookies)) {
        $list = [];
        foreach ($cookies as $kx => $vx) $list[] = $kx.'='.$vx;
        $reqcook = implode('; ', $list);
    }
    $reqip = getServerValue('REMOTE_ADDR', 'N/A');
    if (function_exists('getip')) $reqip = getip();
    return [
        'reqmethod' => getServerValue('REQUEST_METHOD', 'N/A'),
        'reqcookie' => $reqcook,
        'requri' => getServerValue('REQUEST_URI', 'N/A'),
        'reqquery' => getServerValue('QUERY_STRING', 'N/A'),
        'reqip' => $reqip,
        'requa' => getServerValue('HTTP_USER_AGENT', 'N/A'),
        'reqlang' => getServerValue('HTTP_ACCEPT_LANGUAGE', 'N/A'),
    ];
}

# Builds final render-ready template variables for the main monitor dashboard
function getMonitorTemplateVars(?array $snapshot, array $ctx, array $conf, object $db, string $afile): array {
    global $tpl;
    $status = static fn(?bool $vx): string => getStatusHtml($vx);
    $dbhealth = $ctx['dbhealth'];
    $diskio = $ctx['diskio'];
    $storages = $ctx['storages'];
    $vars = [
            'statusurl' => $afile.'.php?name=monitor&amp;op=status',
            'trafficurl' => $afile.'.php?name=monitor&amp;op=traffic',
            'syncurl' => $afile.'.php?name=monitor&amp;op=sync',
            'status_oob' => '',
            'traffic_oob' => '',
            'cntnews' => $ctx['cntnews'],
            'cntfile' => $ctx['cntfile'],
            'dbtabs' => $ctx['dbtabs'],
            'userson' => $ctx['userson'],
            'servsoftname' => $ctx['servname'],
            'servver' => $ctx['servver'],
            'mysql' => (string)db_version(),
            'phpver' => PHP_VERSION,
            'opmode' => $status(!($conf['close'] ?? 0)),
            'statact' => $status(is_active('stat')),
            'referact' => $status(is_active('referers')),
            'newslet' => $status((bool)($conf['newsletter']['active'] ?? 0)),
            'cache' => $status((bool)($conf['cache'] ?? 0)),
            'rewrite' => $status((bool)($conf['rewrite'] ?? 0)),
            'cmsver' => (string)($conf['version'] ?? ''),
            'osname' => php_uname('s'),
            'servfull' => (string)$ctx['servsw'],
            'serverip' => (string)$ctx['serverip'],
            'servprot' => (string)$ctx['srvprot'],
            'servname' => (string)$ctx['srvname'],
            'servport' => (string)$ctx['srvport'],
            'servroot' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['srvroot'], 'label_text' => $ctx['srvroot']]),
            'servhttps' => $ctx['srvhttps'],
            'phpsapi' => php_sapi_name(),
            'zend_eng' => (string)(function_exists('zend_version') ? zend_version() : 'N/A'),
            'php_char' => (string)(ini_get('default_charset') ?: 'N/A'),
            'extlist_on' => $ctx['extlist_on'],
            'extlist_off' => $ctx['extlist_off'],
            'gdver' => (string)$ctx['gdver'],
            'opcache_on' => $status($ctx['opcacheon']),
            'opcache_mem' => (string)$ctx['opmem'],
            'opcache_scripts' => (string)$ctx['opscripts'],
            'opcache_hit_rate' => (string)$ctx['ophit'],
            'postmax' => (string)ini_get('post_max_size'),
            'fileup' => $status((bool)ini_get('file_uploads')),
            'maxfileup' => (string)ini_get('max_file_uploads'),
            'upmax' => (string)ini_get('upload_max_filesize'),
            'memlim' => (string)ini_get('memory_limit'),
            'scriptmem' => filterSize(memory_get_usage(true)),
            'mempeak' => filterSize(memory_get_peak_usage(true)),
            'maxvars' => (string)ini_get('max_input_vars'),
            'maxtime' => (string)ini_get('max_execution_time'),
            'gzipld' => $status(extension_loaded('zlib')),
            'zipld' => $status(extension_loaded('zip')),
            'bz2ld' => $status(extension_loaded('bz2')),
            'phptime' => date('H:i:s'),
            'uptime' => (string)$ctx['uptime'],
            'dbszfmt' => filterSize($ctx['dbsize']),
            'diskwarn' => $ctx['diskwarn'],
            'dbconn' => (string)$dbhealth['connections'],
            'dbslow' => (string)$dbhealth['slow'],
            'dbchar' => (string)$dbhealth['charset'],
            'dbsqlmode' => $tpl->getHtmlFrag('info-tooltip', ['content' => $dbhealth['sql_mode'], 'label_text' => $dbhealth['sql_mode']]),
            'dbmaxpack' => (string)$dbhealth['max_packet'],
            'dbbuffpool' => (string)$dbhealth['buffer_pool'],
            'dbtz' => (string)$dbhealth['timezone'],
            'dbcurname' => (string)($conf['db']['name'] ?? 'N/A'),
            'dbuser' => (string)$dbhealth['user'],
            'dbqnum' => $db->qnum,
            'dbqtime' => $ctx['dbtime'],
            'exectime' => $ctx['exectime'],
            'diskfree' => filterSize((float)$ctx['diskfree']),
            'disktot' => filterSize((float)$ctx['disktotal']),
            'diskused' => filterSize((float)$ctx['diskused']),
            'reqmeth' => (string)$ctx['reqmethod'],
            'reqcookie' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['reqcookie'], 'label_text' => $ctx['reqcookie']]),
            'requri' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['requri'], 'label_text' => $ctx['requri']]),
            'reqquery' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['reqquery'], 'label_text' => $ctx['reqquery']]),
            'reqip' => (string)$ctx['reqip'],
            'requa' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['requa'], 'label_text' => $ctx['requa']]),
            'reqlang' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['reqlang'], 'label_text' => $ctx['reqlang']]),
            'dskread' => ($diskio['read_rate'] === null) ? 'N/A' : filterSize((int)$diskio['read_rate']).'/s',
            'dskwrite' => ($diskio['write_rate'] === null) ? 'N/A' : filterSize((int)$diskio['write_rate']).'/s',
            'backupdirsz' => ($storages['backup'] === null) ? 'N/A' : filterSize((int)$storages['backup']),
            'cachedirsz' => ($storages['cache'] === null) ? 'N/A' : filterSize((int)$storages['cache']),
            'logsdirsz' => ($storages['logs'] === null) ? 'N/A' : filterSize((int)$storages['logs']),
            'lastbackuprun' => (string)$ctx['lastbackup'],
            'errorlog24h' => (string)$ctx['error24'],
            'uploadssz' => (string)$ctx['uploadsz'],
            'failedlogins24h' => (string)$ctx['failed24'],
            'lastsecurityevent24h' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['seclast24'], 'label_text' => $ctx['seclast24']]),
            'dbissueevent24h' => $tpl->getHtmlFrag('info-tooltip', ['content' => $ctx['dblast24'], 'label_text' => $ctx['dblast24']]),
            'show_layout' => true,
            'show_status' => ($snapshot !== null),
            'show_traffic' => ($snapshot !== null),
        ];
    if ($snapshot !== null) $vars = array_merge($vars, getServerStatusVars($snapshot), getTrafficPanelVars($snapshot));
    return $vars;
}

# Aggregates all monitor context providers for full dashboard rendering
function getMonitorDashboardContext(object $db, array $conf, ?array $snapshot = null): array {
    return array_merge(
        getMonitorDbStats($db, $conf),
        getMonitorServerStats(),
        getMonitorRuntimeStats($db, $snapshot),
        getMonitorRequestStats()
    );
}

# Sets the monitor dashboard output with standard admin wrapper and optional snapshot-backed panels
function setMonitorPage(object $db, array $conf, string $afile, ?array $snapshot = null): void {
    global $tpl;
    $ctx = getMonitorDashboardContext($db, $conf, $snapshot);
    $vars = getMonitorTemplateVars($snapshot, $ctx, $conf, $db, $afile);
    $navi = getTplAdminTabs(['ops' => ['name=monitor', 'name=monitor&amp;op=info'], 'tabs' => [_HOME, _INFO]]);
    echo $navi.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('basic-monitor', $vars)]);
}

# Renders the main monitor page including navigation, panels, and full dashboard layout
function monitor(): void {
    global $db, $conf, $afile;
    setHead();
    setMonitorPage($db, $conf, $afile);
    setFoot();
}

# Renders monitor information page with standard admin info block and navigation tabs
function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=monitor', 'name=monitor&amp;op=info'],
        'tabs' => [_HOME, _INFO],
    ]);
}

# Sets snapshot-backed monitor partial output for HTMX refresh endpoints
function setMonitorPart(bool $showstat, bool $showtraf, bool $useoob = false): void {
    echo getMonitorPartial(getMonitorPanelSnapshot(), $showstat, $showtraf, $useoob);
}

# Renders only traffic partial panel for asynchronous dashboard refresh requests
function traffic(): void {
    setMonitorPart(false, true, false);
}

# Renders only status partial panel for asynchronous dashboard refresh requests
function status(): void {
    setMonitorPart(true, false, false);
}

# Renders status and traffic partials together for synchronized asynchronous updates
function sync(): void {
    setMonitorPart(true, true, true);
}

switch ($op) {
    default: monitor(); break;
    case 'info': info(); break;
    case 'traffic': traffic(); break;
    case 'status': status(); break;
    case 'sync': sync(); break;
}
