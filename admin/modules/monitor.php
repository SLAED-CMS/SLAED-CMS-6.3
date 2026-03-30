<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


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
    $memlim = ini_get('memory_limit');
    if ($memlim === false || $memlim === '' || $memlim === '-1') return max(memory_get_usage(true) * 2, 134217728);
    if (preg_match('/^(\d+)(.)$/', $memlim, $matches)) {
        if ($matches[2] === 'M') return $matches[1] * 1024 * 1024;
        if ($matches[2] === 'K') return $matches[1] * 1024;
        if ($matches[2] === 'G') return $matches[1] * 1024 * 1024 * 1024;
    }
    return intval($memlim);
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
function getSmoothLinePath(array $history, float $maxvalue, int $height = 220): string {
    $history = array_values(array_map('floatval', $history));
    if (!$history) return 'M0,'.$height.' L100,'.$height;
    $count = count($history);
    $maxvalue = max($maxvalue, 1.0);
    $points = [];
    foreach ($history as $ix => $val) {
        $xx = ($count > 1) ? ($ix * (100 / ($count - 1))) : 0.0;
        $yy = $height - (($val / $maxvalue) * ($height - 10));
        if ($yy < 0) $yy = 0;
        if ($yy > $height) $yy = $height;
        $points[] = ['x' => round($xx, 2), 'y' => round($yy, 2)];
    }
    if (count($points) < 2) return 'M'.$points[0]['x'].','.$points[0]['y'].' L'.$points[0]['x'].','.$points[0]['y'];
    $path = 'M'.$points[0]['x'].','.$points[0]['y'].' ';
    $nx = count($points);
    for ($ix = 0; $ix < $nx - 1; $ix++) {
        $pta = ($ix > 0) ? $points[$ix - 1] : $points[$ix];
        $ptb = $points[$ix];
        $ptc = $points[$ix + 1];
        $ptd = ($ix + 2 < $nx) ? $points[$ix + 2] : $ptc;
        $conax = $ptb['x'] + (($ptc['x'] - $pta['x']) / 6);
        $conay = $ptb['y'] + (($ptc['y'] - $pta['y']) / 6);
        $conbx = $ptc['x'] - (($ptd['x'] - $ptb['x']) / 6);
        $conby = $ptc['y'] - (($ptd['y'] - $ptb['y']) / 6);
        $path .= 'C'.round($conax, 2).','.round($conay, 2).' '
            .round($conbx, 2).','.round($conby, 2).' '
            .$ptc['x'].','.$ptc['y'].' ';
    }
    return trim($path);
}

# Builds a smooth closed SVG area path from metric history for filled chart rendering
function getSmoothAreaPath(array $history, float $maxvalue, int $height = 220): string {
    $history = array_values(array_map('floatval', $history));
    if (!$history) return 'M0,'.$height.' L100,'.$height.' Z';
    $count = count($history);
    $maxvalue = max($maxvalue, 1.0);
    $points = [];
    foreach ($history as $ix => $val) {
        $xx = ($count > 1) ? ($ix * (100 / ($count - 1))) : 0.0;
        $yy = $height - (($val / $maxvalue) * ($height - 10));
        if ($yy < 0) $yy = 0;
        if ($yy > $height) $yy = $height;
        $points[] = ['x' => round($xx, 2), 'y' => round($yy, 2)];
    }
    $first = $points[0];
    $path = 'M0,'.$height.' L'.$first['x'].','.$first['y'].' ';
    $nx = count($points);
    if ($nx === 1) {
        $path .= 'L100,'.$height.' Z';
        return $path;
    }
    for ($ix = 0; $ix < $nx - 1; $ix++) {
        $pta = ($ix > 0) ? $points[$ix - 1] : $points[$ix];
        $ptb = $points[$ix];
        $ptc = $points[$ix + 1];
        $ptd = ($ix + 2 < $nx) ? $points[$ix + 2] : $ptc;
        $conax = $ptb['x'] + (($ptc['x'] - $pta['x']) / 6);
        $conay = $ptb['y'] + (($ptc['y'] - $pta['y']) / 6);
        $conbx = $ptc['x'] - (($ptd['x'] - $ptb['x']) / 6);
        $conby = $ptc['y'] - (($ptd['y'] - $ptb['y']) / 6);
        $path .= 'C'.round($conax, 2).','.round($conay, 2).' '.round($conbx, 2).','.round($conby, 2).' '.$ptc['x'].','.$ptc['y'].' ';
    }
    $path .= 'L100,'.$height.' Z';
    return trim($path);
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
    if ($state === null) return '<span class="sl_muted">N/A</span>';
    return adminFlagBox($state, 'On', 'Off');
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
        error_log('Monitor DB health read failed: '.$error->getMessage());
    }
    return $data;
}

# Shortens long text and adds escaped tooltip markup for safe compact UI presentation
function getTooltipText(string $text, int $limit = 50): string {
    if ($text === '' || $text === 'N/A' || mb_strlen($text, 'UTF-8') <= $limit) return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $short = htmlspecialchars(mb_substr($text, 0, $limit, 'UTF-8'), ENT_QUOTES, 'UTF-8').'...';
    $full = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return $short.' <i class="bi bi-info-circle" title="'.$full.'" style="cursor:help; color:#3b82f6;"></i>';
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

# Normalizes whitespace and truncates log line text for compact and safe status display
function getLogSnippet(string $line, int $limit = 80): string {
    $line = preg_replace('/\s+/', ' ', trim($line)) ?? '';
    return mb_strlen($line, 'UTF-8') > $limit ? mb_substr($line, 0, $limit, 'UTF-8').'...' : $line;
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
            $besttxt = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.getLogSnippet($line, 70);
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
            $besttxt = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.getLogSnippet($line, 70);
            break;
        }
    }
    if (!$seen) return 'N/A';
    if ($bestts <= 0) return '0';
    return date('Y-m-d H:i:s', $bestts).' | '.$besttxt;
}


# Maps percentage load value to semantic color code for dashboard usage indicators
function getUsageColor(float $pct): string {
    if ($pct > 95) return '#ef4444';
    if ($pct > 75) return '#f97316';
    if ($pct > 50) return '#3b82f6';
    return '#22c55e';
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
        'tip_up' => htmlspecialchars('Upstream: '.filterSize($net['tx_total']).' ('.filterSize($net['tx_rate']).'/s)', ENT_QUOTES, 'UTF-8'),
        'tip_down' => htmlspecialchars('Downstream: '.filterSize($net['rx_total']).' ('.filterSize($net['rx_rate']).'/s)', ENT_QUOTES, 'UTF-8'),
        'tip_cpu' => htmlspecialchars('CPU Usage: '.round((float)$cpup, 1).'%', ENT_QUOTES, 'UTF-8'),
        'tip_ram' => htmlspecialchars('RAM Usage: '.round((float)$mem['percent'], 1).'% ('.filterSize($mem['used']).' / '.filterSize($mem['total']).')', ENT_QUOTES, 'UTF-8'),
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
    return [
        'dash' => $dash,
        'off' => $offload,
        'load_0' => round((float)$cpup, 1),
        'cpucolor' => getUsageColor((float)$cpup),
        'cpucores' => $cpu['logical'],
        'cpuphys' => $cpu['physical'],
        'cpufreq' => $cpu['freq'],
        'dash_r' => $dash,
        'off_r' => $offr,
        'ramcolor' => getUsageColor((float)$mem['percent']),
        'ram_p' => round((float)$mem['percent'], 1),
        'ramumb' => filterSize($mem['used']),
        'ramtmb' => filterSize($mem['total']),
        'ramavailmb' => filterSize($mem['free']),
        'dash_d' => $dash,
        'off_d' => $offd,
        'diskcolor' => getUsageColor((float)$diskpct),
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
    return $tpl->getHtmlFrag('basic-monitor', $vars);
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
    $srvhttps = adminFlagBox($ishttps, 'enabled', 'disabled');
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
    
    $extlist_on = getTooltipText(implode(', ', $loaded), 25);
    $extlist_off = !empty($off) ? getTooltipText(implode(', ', $off), 25) : 'None / N/A';

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
    $diskwarn = adminFlagBox(!$islowdisk, 'Normal', 'Low free space');
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
            'mysql' => htmlspecialchars((string)db_version(), ENT_QUOTES, 'UTF-8'),
            'phpver' => PHP_VERSION,
            'opmode' => $status(!($conf['close'] ?? 0)),
            'statact' => $status(is_active('stat')),
            'referact' => $status(is_active('referers')),
            'newslet' => $status((bool)($conf['newsletter']['active'] ?? 0)),
            'cache' => $status((bool)($conf['cache'] ?? 0)),
            'rewrite' => $status((bool)($conf['rewrite'] ?? 0)),
            'cmsver' => htmlspecialchars((string)($conf['version'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'osname' => htmlspecialchars(php_uname('s'), ENT_QUOTES, 'UTF-8'),
            'servfull' => htmlspecialchars((string)$ctx['servsw'], ENT_QUOTES, 'UTF-8'),
            'serverip' => htmlspecialchars((string)$ctx['serverip'], ENT_QUOTES, 'UTF-8'),
            'servprot' => htmlspecialchars((string)$ctx['srvprot'], ENT_QUOTES, 'UTF-8'),
            'servname' => htmlspecialchars((string)$ctx['srvname'], ENT_QUOTES, 'UTF-8'),
            'servport' => htmlspecialchars((string)$ctx['srvport'], ENT_QUOTES, 'UTF-8'),
            'servroot' => getTooltipText($ctx['srvroot'], 25),
            'servhttps' => $ctx['srvhttps'],
            'phpsapi' => htmlspecialchars(php_sapi_name(), ENT_QUOTES, 'UTF-8'),
            'zend_eng' => htmlspecialchars((string)(function_exists('zend_version') ? zend_version() : 'N/A'), ENT_QUOTES, 'UTF-8'),
            'php_char' => htmlspecialchars((string)(ini_get('default_charset') ?: 'N/A'), ENT_QUOTES, 'UTF-8'),
            'extlist_on' => $ctx['extlist_on'],
            'extlist_off' => $ctx['extlist_off'],
            'gdver' => htmlspecialchars((string)$ctx['gdver'], ENT_QUOTES, 'UTF-8'),
            'opcache_on' => $status($ctx['opcacheon']),
            'opcache_mem' => htmlspecialchars((string)$ctx['opmem'], ENT_QUOTES, 'UTF-8'),
            'opcache_scripts' => htmlspecialchars((string)$ctx['opscripts'], ENT_QUOTES, 'UTF-8'),
            'opcache_hit_rate' => htmlspecialchars((string)$ctx['ophit'], ENT_QUOTES, 'UTF-8'),
            'postmax' => htmlspecialchars((string)ini_get('post_max_size'), ENT_QUOTES, 'UTF-8'),
            'fileup' => $status((bool)ini_get('file_uploads')),
            'maxfileup' => htmlspecialchars((string)ini_get('max_file_uploads'), ENT_QUOTES, 'UTF-8'),
            'upmax' => htmlspecialchars((string)ini_get('upload_max_filesize'), ENT_QUOTES, 'UTF-8'),
            'memlim' => htmlspecialchars((string)ini_get('memory_limit'), ENT_QUOTES, 'UTF-8'),
            'scriptmem' => filterSize(memory_get_usage(true)),
            'mempeak' => filterSize(memory_get_peak_usage(true)),
            'maxvars' => htmlspecialchars((string)ini_get('max_input_vars'), ENT_QUOTES, 'UTF-8'),
            'maxtime' => htmlspecialchars((string)ini_get('max_execution_time'), ENT_QUOTES, 'UTF-8'),
            'gzipld' => $status(extension_loaded('zlib')),
            'zipld' => $status(extension_loaded('zip')),
            'bz2ld' => $status(extension_loaded('bz2')),
            'phptime' => date('H:i:s'),
            'uptime' => htmlspecialchars((string)$ctx['uptime'], ENT_QUOTES, 'UTF-8'),
            'dbszfmt' => filterSize($ctx['dbsize']),
            'diskwarn' => $ctx['diskwarn'],
            'dbconn' => htmlspecialchars((string)$dbhealth['connections'], ENT_QUOTES, 'UTF-8'),
            'dbslow' => htmlspecialchars((string)$dbhealth['slow'], ENT_QUOTES, 'UTF-8'),
            'dbchar' => htmlspecialchars((string)$dbhealth['charset'], ENT_QUOTES, 'UTF-8'),
            'dbsqlmode' => getTooltipText($dbhealth['sql_mode'], 25),
            'dbmaxpack' => htmlspecialchars((string)$dbhealth['max_packet'], ENT_QUOTES, 'UTF-8'),
            'dbbuffpool' => htmlspecialchars((string)$dbhealth['buffer_pool'], ENT_QUOTES, 'UTF-8'),
            'dbtz' => htmlspecialchars((string)$dbhealth['timezone'], ENT_QUOTES, 'UTF-8'),
            'dbcurname' => htmlspecialchars((string)($conf['db']['name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
            'dbuser' => htmlspecialchars((string)$dbhealth['user'], ENT_QUOTES, 'UTF-8'),
            'dbqnum' => $db->qnum,
            'dbqtime' => $ctx['dbtime'],
            'exectime' => $ctx['exectime'],
            'diskfree' => filterSize((float)$ctx['diskfree']),
            'disktot' => filterSize((float)$ctx['disktotal']),
            'diskused' => filterSize((float)$ctx['diskused']),
            'reqmeth' => htmlspecialchars((string)$ctx['reqmethod'], ENT_QUOTES, 'UTF-8'),
            'reqcookie' => getTooltipText($ctx['reqcookie'], 25),
            'requri' => getTooltipText($ctx['requri'], 25),
            'reqquery' => getTooltipText($ctx['reqquery'], 25),
            'reqip' => htmlspecialchars((string)$ctx['reqip'], ENT_QUOTES, 'UTF-8'),
            'requa' => getTooltipText($ctx['requa'], 25),
            'reqlang' => getTooltipText($ctx['reqlang'], 25),
            'dskread' => ($diskio['read_rate'] === null) ? 'N/A' : filterSize((int)$diskio['read_rate']).'/s',
            'dskwrite' => ($diskio['write_rate'] === null) ? 'N/A' : filterSize((int)$diskio['write_rate']).'/s',
            'backupdirsz' => ($storages['backup'] === null) ? 'N/A' : filterSize((int)$storages['backup']),
            'cachedirsz' => ($storages['cache'] === null) ? 'N/A' : filterSize((int)$storages['cache']),
            'logsdirsz' => ($storages['logs'] === null) ? 'N/A' : filterSize((int)$storages['logs']),
            'lastbackuprun' => htmlspecialchars((string)$ctx['lastbackup'], ENT_QUOTES, 'UTF-8'),
            'errorlog24h' => (string)$ctx['error24'],
            'uploadssz' => htmlspecialchars((string)$ctx['uploadsz'], ENT_QUOTES, 'UTF-8'),
            'failedlogins24h' => (string)$ctx['failed24'],
            'lastsecurityevent24h' => getTooltipText($ctx['seclast24'], 25),
            'dbissueevent24h' => getTooltipText($ctx['dblast24'], 25),
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
    $navi = setAdminNavi(['ops' => ['name=monitor', 'name=monitor&op=info'], 'tabs' => [_HOME, _INFO]]);
    echo $navi.getAdminBox($tpl->getHtmlFrag('basic-monitor', $vars));
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
    $cont = setAdminNavi(['ops' => ['name=monitor', 'name=monitor&op=info'], 'tabs' => [_HOME, _INFO], 'tab' => 1]);
    setAdminInfoPage($cont);
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
