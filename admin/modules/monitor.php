<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

// Builds admin navigation tabs for monitor module screens and returns rendered tab header HTML.
function navi(int $tab = 0): string {
    $ops = ['name=monitor', 'name=monitor&op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs('', $ops, $lang, act: $tab);
}

// Checks whether a filesystem path is permitted by open_basedir restrictions before any file operations.
function isPathAllowed(string $path): bool {
    $obase = ini_get('open_basedir');
    if ($obase === false || $obase === '') {
        return true;
    }
    $npath = str_replace('\\', '/', $path);
    foreach (explode(PATH_SEPARATOR, $obase) as $base) {
        $base = trim((string)$base);
        if ($base === '' || $base === '.') {
            continue;
        }
        $cbase = rtrim(str_replace('\\', '/', $base), '/');
        if ($cbase === '') {
            continue;
        }
        if ($npath === $cbase || str_starts_with($npath, $cbase.'/')) {
            return true;
        }
    }
    return false;
}

// Reads file contents only when the path is allowed, file exists, and read permission is available.
function getFileSafe(string $path): string|false {
    if (!isPathAllowed($path)) {
        return false;
    }
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }
    $content = file_get_contents($path);
    return ($content === false) ? false : $content;
}

// Reads a server variable via input filtering and getenv fallback without direct superglobal access.
function getServerValue(string $key, string $default = ''): string {
    $value = filter_input(INPUT_SERVER, $key, FILTER_UNSAFE_RAW, FILTER_NULL_ON_FAILURE);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    $value = getenv($key);
    if (is_string($value) && $value !== '') {
        return $value;
    }
    return $default;
}

// Returns normalized cookie key/value pairs via input filtering for request diagnostics.
function getCookieValues(): array {
    $items = filter_input_array(INPUT_COOKIE, FILTER_UNSAFE_RAW);
    if (!is_array($items) || $items === []) {
        return [];
    }
    $result = [];
    foreach ($items as $key => $value) {
        if (!is_string($key) || is_array($value)) {
            continue;
        }
        $result[$key] = (string)$value;
    }
    return $result;
}

// Collects total, free, used memory and usage percent using OS-specific providers with safe fallbacks.
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

// Reads total and free physical memory on Windows via PowerShell CIM and WMIC fallback paths.
function getMemoryInfoWindows(): array {
    $free = 0;
    $total = 0;
    $ps = [];
    if (function_exists('exec')) {
        exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory | Format-List)"', $ps);
    }
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
        return [$total, $free];
    }
    $outtot = [];
    if (function_exists('exec')) {
        exec('wmic ComputerSystem get TotalPhysicalMemory /Value', $outtot);
    }
    foreach ($outtot as $line) {
        if (str_contains($line, 'TotalPhysicalMemory')) {
            $parts = explode('=', $line);
            $total = intval($parts[1] ?? 0);
            break;
        }
    }
    $outfree = [];
    if (function_exists('exec')) {
        exec('wmic OS get FreePhysicalMemory /Value', $outfree);
    }
    foreach ($outfree as $line) {
        if (str_contains($line, 'FreePhysicalMemory')) {
            $parts = explode('=', $line);
            $free = intval($parts[1] ?? 0) * 1024;
            break;
        }
    }
    return [$total, $free];
}

// Parses /proc/meminfo on Linux and computes total and available memory with compatibility fallback.
function getMemoryInfoLinux(): array {
    $total = 0;
    $free = 0;
    $data = getFileSafe('/proc/meminfo');
    if (!$data) {
        return [$total, $free];
    }
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

// Converts PHP memory_limit to bytes and returns a conservative fallback when limit is unlimited.
function getMemorySafeLimit(): int {
    $memlim = ini_get('memory_limit');
    if ($memlim === false || $memlim === '' || $memlim === '-1') {
        return max(memory_get_usage(true) * 2, 134217728);
    }
    if (preg_match('/^(\d+)(.)$/', $memlim, $matches)) {
        if ($matches[2] === 'M') return $matches[1] * 1024 * 1024;
        if ($matches[2] === 'K') return $matches[1] * 1024;
        if ($matches[2] === 'G') return $matches[1] * 1024 * 1024 * 1024;
    }
    return intval($memlim);
}

// Detects logical CPU core count across Windows and Linux with environment and command fallbacks.
function getCpuCores(): int {
    $cores = 0;
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $out = [];
        if (function_exists('exec')) {
            exec('powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum"', $out);
        }
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
        if ($info !== false) {
            preg_match_all('/^processor\s*:/m', $info, $matches);
            if (!empty($matches[0])) $cores = count($matches[0]);
        }
        if ($cores <= 0 && function_exists('exec')) {
            $out = [];
            exec('nproc 2>/dev/null', $out);
            if (!empty($out) && is_numeric(trim((string)$out[0]))) $cores = (int)trim((string)$out[0]);
        }
    }
    return ($cores > 0) ? $cores : 1;
}

// Returns cumulative RX and TX bytes using the active platform-specific network statistics source.
function getNetworkStats(): array {
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        return getNetworkStatsWindows();
    }
    return getNetworkStatsLinux();
}

// Parses netstat output on Windows to extract cumulative received and transmitted byte counters.
function getNetworkStatsWindows(): array {
    $rx = 0.0;
    $tx = 0.0;
    $out = [];
    if (function_exists('exec')) {
        exec('netstat -e', $out);
    }
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

// Aggregates Linux network byte counters from procfs and sysfs with progressive fallbacks.
function getNetworkStatsLinux(): array {
    $rx = 0.0;
    $tx = 0.0;
    $parsed = false;
    $data = getFileSafe('/proc/net/dev');
    if ($data !== false && $data !== '') {
        [$rx, $tx, $parsed] = getNetDevStats($data);
    }
    if (!$parsed && function_exists('exec')) {
        $out = [];
        exec('cat /proc/net/dev 2>/dev/null', $out);
        if (!empty($out)) {
            [$rx, $tx, $parsed] = getNetDevStats(implode("\n", $out));
        }
    }
    if (!$parsed && function_exists('exec')) {
        $rxout = [];
        $txout = [];
        exec('sh -c "for f in /sys/class/net/*/statistics/rx_bytes; do case \"$f\" in */lo/*) continue;; esac; cat \"$f\" 2>/dev/null; done"', $rxout);
        exec('sh -c "for f in /sys/class/net/*/statistics/tx_bytes; do case \"$f\" in */lo/*) continue;; esac; cat \"$f\" 2>/dev/null; done"', $txout);
        if (!empty($rxout) || !empty($txout)) {
            foreach ($rxout as $line) {
                $val = trim((string)$line);
                if (is_numeric($val)) $rx += (float)$val;
            }
            foreach ($txout as $line) {
                $val = trim((string)$line);
                if (is_numeric($val)) $tx += (float)$val;
            }
        }
    }
    return ['rx' => $rx, 'tx' => $tx];
}

// Parses /proc/net/dev payload and sums non-loopback interface RX and TX byte counters safely.
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

// Returns the absolute metrics storage file path used for persisting monitor history snapshots.
function getMetricStorePath(): string {
    return LOGS_DIR.'/monitor_metrics.json';
}

// Loads persisted monitor metrics from JSON store and returns an array with safe empty fallback.
function getMetricStore(): array {
    $file = getMetricStorePath();
    if (!is_file($file) || !is_readable($file)) return [];
    $json = file_get_contents($file);
    if ($json === false || $json === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// Writes monitor metrics to JSON storage when logs directory is writable and available.
function setMetricStore(array $data): void {
    $file = getMetricStorePath();
    if (!is_dir(LOGS_DIR) || !is_writable(LOGS_DIR)) return;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// Appends a rounded metric value to history and trims array length to the configured maximum.
function addHistory(array $history, float $value, int $max = 30): array {
    $history[] = round($value, 2);
    if (count($history) > $max) $history = array_slice($history, -$max);
    return $history;
}

// Builds a smooth SVG cubic-bezier line path from metric history for chart rendering.
function getSmoothLinePath(array $history, float $maxvalue, int $height = 220): string {
    $history = array_values(array_map('floatval', $history));
    if (!$history) return 'M0,'.$height.' L100,'.$height;
    $count = count($history);
    $maxvalue = max($maxvalue, 1.0);
    $points = [];
    foreach ($history as $i => $val) {
        $x = ($count > 1) ? ($i * (100 / ($count - 1))) : 0.0;
        $y = $height - (($val / $maxvalue) * ($height - 10));
        if ($y < 0) $y = 0;
        if ($y > $height) $y = $height;
        $points[] = ['x' => round($x, 2), 'y' => round($y, 2)];
    }
    if (count($points) < 2) return 'M'.$points[0]['x'].','.$points[0]['y'].' L'.$points[0]['x'].','.$points[0]['y'];
    $path = 'M'.$points[0]['x'].','.$points[0]['y'].' ';
    $n = count($points);
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = ($i > 0) ? $points[$i - 1] : $points[$i];
        $p1 = $points[$i];
        $p2 = $points[$i + 1];
        $p3 = ($i + 2 < $n) ? $points[$i + 2] : $p2;
        $cp1x = $p1['x'] + (($p2['x'] - $p0['x']) / 6);
        $cp1y = $p1['y'] + (($p2['y'] - $p0['y']) / 6);
        $cp2x = $p2['x'] - (($p3['x'] - $p1['x']) / 6);
        $cp2y = $p2['y'] - (($p3['y'] - $p1['y']) / 6);
        $path .= 'C'.round($cp1x, 2).','.round($cp1y, 2).' '
            .round($cp2x, 2).','.round($cp2y, 2).' '
            .$p2['x'].','.$p2['y'].' ';
    }
    return trim($path);
}

// Builds a smooth closed SVG area path from metric history for filled chart rendering.
function getSmoothAreaPath(array $history, float $maxvalue, int $height = 220): string {
    $history = array_values(array_map('floatval', $history));
    if (!$history) return 'M0,'.$height.' L100,'.$height.' Z';
    $count = count($history);
    $maxvalue = max($maxvalue, 1.0);
    $points = [];
    foreach ($history as $i => $val) {
        $x = ($count > 1) ? ($i * (100 / ($count - 1))) : 0.0;
        $y = $height - (($val / $maxvalue) * ($height - 10));
        if ($y < 0) $y = 0;
        if ($y > $height) $y = $height;
        $points[] = ['x' => round($x, 2), 'y' => round($y, 2)];
    }
    $first = $points[0];
    $path = 'M0,'.$height.' L'.$first['x'].','.$first['y'].' ';
    $n = count($points);
    if ($n === 1) {
        $path .= 'L100,'.$height.' Z';
        return $path;
    }
    for ($i = 0; $i < $n - 1; $i++) {
        $p0 = ($i > 0) ? $points[$i - 1] : $points[$i];
        $p1 = $points[$i];
        $p2 = $points[$i + 1];
        $p3 = ($i + 2 < $n) ? $points[$i + 2] : $p2;
        $cp1x = $p1['x'] + (($p2['x'] - $p0['x']) / 6);
        $cp1y = $p1['y'] + (($p2['y'] - $p0['y']) / 6);
        $cp2x = $p2['x'] - (($p3['x'] - $p1['x']) / 6);
        $cp2y = $p2['y'] - (($p3['y'] - $p1['y']) / 6);
        $path .= 'C'.round($cp1x, 2).','.round($cp1y, 2).' '
            .round($cp2x, 2).','.round($cp2y, 2).' '
            .$p2['x'].','.$p2['y'].' ';
    }
    $path .= 'L100,'.$height.' Z';
    return trim($path);
}

// Calculates realtime network rates, updates history buffers, and persists metric snapshots.
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

// Reads cumulative disk read and write bytes from Linux diskstats for whole block devices only.
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
        // Keep only whole block devices, skip partitions to avoid double counting.
        if (!preg_match('/^(sd[a-z]+|hd[a-z]+|vd[a-z]+|xvd[a-z]+|nvme\d+n\d+|mmcblk\d+|md\d+|dm-\d+)$/', (string)$name)) continue;
        $readsectors = (float)($parts[5] ?? 0);
        $writesectors = (float)($parts[9] ?? 0);
        $read += $readsectors * 512;
        $write += $writesectors * 512;
    }
    return ['read' => $read, 'write' => $write, 'ok' => true];
}

// Calculates disk read and write rates from cumulative counters and updates history buffers.
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
    $prevwrite = (float)($store['disk_prev_write'] ?? 0);
    $dt = max($now - $prevts, 1);
    $readrate = ($prevts > 0) ? max(($totals['read'] - $prevread) / $dt, 0) : 0.0;
    $writerate = ($prevts > 0) ? max(($totals['write'] - $prevwrite) / $dt, 0) : 0.0;
    $histread = is_array($store['disk_hist_read'] ?? null) ? $store['disk_hist_read'] : [];
    $histwrite = is_array($store['disk_hist_write'] ?? null) ? $store['disk_hist_write'] : [];
    $histread = addHistory($histread, $readrate);
    $histwrite = addHistory($histwrite, $writerate);
    $store['disk_prev_ts'] = $now;
    $store['disk_prev_read'] = $totals['read'];
    $store['disk_prev_write'] = $totals['write'];
    $store['disk_hist_read'] = $histread;
    $store['disk_hist_write'] = $histwrite;
    setMetricStore($store);
    return [
        'read_rate' => $readrate,
        'write_rate' => $writerate,
        'hist_read' => $histread,
        'hist_write' => $histwrite,
    ];
}

// Returns human-readable system uptime from platform sources with graceful fallback behavior.
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

// Formats uptime seconds into a compact days, hours, minutes, and seconds text representation.
function getUptimeText(int $sec): string {
    $days = intdiv($sec, 86400);
    $hours = intdiv($sec % 86400, 3600);
    $mins = intdiv($sec % 3600, 60);
    return $days.'d '.$hours.'h '.$mins.'m';
}

// Collects raw CPU metadata including logical and physical cores and current base frequency.
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
        if ($cpuinfo !== false) {
            preg_match_all('/^physical id\s*:\s*(\d+)/m', $cpuinfo, $physids);
            preg_match_all('/^core id\s*:\s*(\d+)/m', $cpuinfo, $coreids);
            if (!empty($physids[1]) && !empty($coreids[1]) && count($physids[1]) === count($coreids[1])) {
                $pairs = [];
                foreach ($physids[1] as $k => $pid) {
                    $pairs[] = $pid.'-'.$coreids[1][$k];
                }
                $physical = count(array_unique($pairs));
            }
            if ($physical <= 0) {
                preg_match('/^cpu cores\s*:\s*(\d+)/m', $cpuinfo, $m);
                if (!empty($m[1])) $physical = (int)$m[1];
            }
            preg_match('/^cpu MHz\s*:\s*([0-9.]+)/m', $cpuinfo, $mhz);
            if (!empty($mhz[1])) $freq = round(((float)$mhz[1]) / 1000, 2).' GHz';
        }
    }
    return [
        'logical' => max($logical, 1),
        'physical' => ($physical > 0) ? (string)$physical : 'N/A',
        'freq' => $freq,
    ];
}

// Returns cached CPU metadata when fresh, otherwise refreshes and persists CPU details cache.
function getCpuDetails(): array {
    static $reqcache = null;
    if (is_array($reqcache)) return $reqcache;
    $ttl = 120;
    $cachekey = 'slaed_monitor_cpu_details_v1';
    if (is_callable('apcu_fetch') && is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        $ok = false;
        $cached = call_user_func('apcu_fetch', $cachekey, $ok);
        if ($ok && is_array($cached) && isset($cached['data'], $cached['ts']) && (time() - (int)$cached['ts']) <= $ttl) {
            $reqcache = $cached['data'];
            return $reqcache;
        }
    }
    $fresh = getCpuDetailsRaw();
    $reqcache = $fresh;
    if (is_callable('apcu_store') && (bool)ini_get('apc.enabled')) {
        call_user_func('apcu_store', $cachekey, ['ts' => time(), 'data' => $fresh], $ttl);
    }
    return $fresh;
}

// Formats boolean status into colored HTML badges used by monitor status indicators.
function getStatusHtml(?bool $state): string {
    if ($state === null) return '<span style="color:#9ca3af">N/A</span>';
    return $state ? '<span style="color:#21c45d">On</span>' : '<span style="color:#ef4444">Off</span>';
}

// Queries database runtime health metrics and returns normalized diagnostics for monitor output.
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
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_time'])) {
            $tz = (string)$row['db_time'];
        }
        $data['timezone'] = $tz;
        $res = $db->getSqlQuery("SELECT CURRENT_USER() as db_user");
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_user'])) {
            $data['user'] = (string)$row['db_user'];
        }
    } catch (Throwable) {
    }
    return $data;
}

// Shortens long text and adds escaped tooltip markup for safe compact UI presentation.
function getTooltipText(string $text, int $limit = 50): string {
    if ($text === '' || $text === 'N/A' || mb_strlen($text, 'UTF-8') <= $limit) return $text;
    $short = htmlspecialchars(mb_substr($text, 0, $limit, 'UTF-8'), ENT_QUOTES, 'UTF-8').'...';
    $full = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    return $short.' <i class="bi bi-info-circle" title="'.$full.'" style="cursor:help; color:#3b82f6;"></i>';
}

// Calculates the cumulative size of regular files in a readable directory tree; returns null when unavailable.
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

// Returns size metrics for key storage folders and null when folders are unavailable.
function getStorageDirectorySizes(): array {
    $backupbytes = defined('BACKUP_DIR') ? getDirectorySizeBytes((string)BACKUP_DIR) : null;
    $cachebytes = defined('CACHE_DIR') ? getDirectorySizeBytes((string)CACHE_DIR) : null;
    $logsbytes = defined('LOGS_DIR') ? getDirectorySizeBytes((string)LOGS_DIR) : null;
    return [
        'backup' => $backupbytes,
        'cache' => $cachebytes,
        'logs' => $logsbytes,
    ];
}

// Finds newest file modification timestamp inside a directory tree excluding symlinks.
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

// Returns formatted timestamp of latest backup file or N/A when no backups are found.
function getLastBackupRunLabel(): string {
    $backupcounterfile = (defined('COUNTER_DIR') ? COUNTER_DIR : BASE_DIR.'/storage/counter').'/backup.log';
    if (is_file($backupcounterfile) && is_readable($backupcounterfile)) {
        $raw = trim((string)file_get_contents($backupcounterfile));
        if ($raw !== '' && ctype_digit($raw)) {
            $ts = (int)$raw;
            if ($ts > 0) return date('Y-m-d H:i:s', $ts);
        }
    }
    $backupdir = defined('BACKUP_DIR') ? (string)BACKUP_DIR : BASE_DIR.'/storage/backup';
    $mtime = getLatestFileMTime($backupdir);
    if ($mtime !== null) return date('Y-m-d H:i:s', $mtime);
    return 'N/A';
}

// Counts recent error log entries within a time window using bounded tail parsing.
function getErrorLogCountHours(int $hours = 24): int|string {
    $logfile = (defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs').'/error_file.log';
    if (!is_file($logfile) || !is_readable($logfile)) return 'N/A';
    $lines = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || !is_array($lines)) return 'N/A';
    if (!$lines) return 0;
    $threshold = time() - ($hours * 3600);
    $count = 0;
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = (string)$lines[$i];
        if (!preg_match('/^\[([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2})\]/', $line, $m)) {
            continue;
        }
        $ts = strtotime($m[1]);
        if ($ts === false) continue;
        if ($ts < $threshold) break;
        $count++;
    }
    return $count;
}

// Reads the last bytes of a log file safely for efficient tail-based analysis.
function getFileTailChunk(string $filepath, int $maxbytes = 262144): string {
    if (!is_file($filepath) || !is_readable($filepath)) return '';
    $size = filesize($filepath);
    if ($size === false || $size <= 0) return '';
    $readbytes = min(max($maxbytes, 4096), (int)$size);
    $handle = fopen($filepath, 'rb');
    if ($handle === false) return '';
    if ($size > $readbytes) fseek($handle, -$readbytes, SEEK_END);
    $data = stream_get_contents($handle);
    fclose($handle);
    return is_string($data) ? $data : '';
}

// Splits tail text into normalized lines and limits output to the requested maximum count.
function getTailLines(string $taildata, int $maxlines = 2000): array {
    if ($taildata === '') return [];
    $lines = preg_split('/\R/u', $taildata) ?: [];
    $lines = array_values(array_filter(array_map('trim', $lines), static fn($v) => $v !== ''));
    if (count($lines) > $maxlines) $lines = array_slice($lines, -$maxlines);
    return $lines;
}

// Extracts unix timestamp from a log line using multiple supported datetime patterns.
function getLogLineTimestamp(string $line): int|null {
    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $m)) {
        $ts = strtotime($m[1]);
        if ($ts !== false) return $ts;
    }
    if (preg_match('/"ts"\s*:\s*"([^"]+)"/', $line, $m)) {
        $ts = strtotime($m[1]);
        if ($ts !== false) return $ts;
    }
    if (preg_match('/(\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}:\d{2})/', $line, $m)) {
        $dt = DateTime::createFromFormat('d.m.Y H:i:s', $m[1]);
        if ($dt instanceof DateTime) return $dt->getTimestamp();
    }
    return null;
}

// Normalizes whitespace and truncates log line text for compact and safe status display.
function getLogSnippet(string $line, int $limit = 80): string {
    $line = preg_replace('/\s+/', ' ', trim($line)) ?? '';
    return mb_strlen($line, 'UTF-8') > $limit ? mb_substr($line, 0, $limit, 'UTF-8').'...' : $line;
}

// Returns formatted uploads directory size or N/A when directory size is unavailable.
function getUploadsSizeLabel(): string {
    $uploadsdir = defined('UPLOADS_DIR') ? (string)UPLOADS_DIR : BASE_DIR.'/uploads';
    $bytes = getDirectorySizeBytes($uploadsdir);
    return ($bytes === null) ? 'N/A' : filterSize((int)$bytes);
}

// Counts failed login events in recent logs within the specified hour interval.
function getFailedLoginCountHours(int $hours = 24): int|string {
    $file = (defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs').'/log_admin.log';
    if (!is_file($file) || !is_readable($file)) return 'N/A';
    $tail = getFileTailChunk($file, 262144);
    $lines = getTailLines($tail, 2500);
    if (!$lines) return 0;
    $threshold = time() - ($hours * 3600);
    $count = 0;
    $block = [];
    $isboundary = static fn(string $line): bool => preg_match('/^-{3,}$/', $line) === 1;
    $hasloginmarker = static fn(string $text): bool =>
        preg_match('/(login|auth|Ð²Ñ…Ð¾Ð´|Ãâ€™Ã‘â€¦ÃÂ¾ÃÂ´)/iu', $text) === 1;
    $isfailed = static fn(string $text): bool =>
        preg_match('/(\bno\b|Ð½ÐµÑ‚|ÃÂÃÂµÃ‘Â‚|fail|failed|denied|invalid|unauthori[sz]ed|blocked)/iu', $text) === 1;
    $flushblock = static function(array $rows) use (&$count, $threshold, $hasloginmarker, $isfailed): void {
        if (!$rows) return;
        $text = implode("\n", $rows);
        if (!$hasloginmarker($text)) return;
        if (!$isfailed($text)) return;
        $ts = null;
        foreach ($rows as $row) {
            $ts = getLogLineTimestamp($row);
            if ($ts !== null) break;
        }
        if ($ts !== null && $ts >= $threshold) $count++;
    };
    foreach ($lines as $line) {
        if ($isboundary($line)) {
            $flushblock($block);
            $block = [];
            continue;
        }
        $block[] = $line;
    }
    $flushblock($block);
    return $count;
}

// Returns latest recent security-related event with source label and timestamp context.
function getSecurityEventHours(int $hours = 24): string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['warn.log', 'hack.log', 'error_site.log', 'error_php.log', 'log.log'];
    $threshold = time() - ($hours * 3600);
    $bestts = 0;
    $bestlabel = '';
    $seen = false;
    foreach ($files as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $seen = true;
        $tail = getFileTailChunk($path, 262144);
        $lines = getTailLines($tail, 2500);
        if (!$lines) continue;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            $ts = getLogLineTimestamp($line);
            if ($ts === null || $ts < $threshold) continue;
            if ($ts <= $bestts) continue;
            $bestts = $ts;
            $bestlabel = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.getLogSnippet($line, 70);
            break;
        }
    }
    if (!$seen) return 'N/A';
    if ($bestts <= 0) return '0';
    return date('Y-m-d H:i:s', $bestts).' | '.$bestlabel;
}

// Returns latest recent database-related issue event matched by error patterns.
function getDbIssueEventHours(int $hours = 24): string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['error_sql.log', 'error_file.log', 'error_php.log'];
    $threshold = time() - ($hours * 3600);
    $bestts = 0;
    $bestlabel = '';
    $seen = false;
    $dbpattern = '/(sqlstate|mysql|mysqli|mariadb|database|pdo|deadlock|lock wait|too many connections|connection refused|access denied)/iu';
    foreach ($files as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $seen = true;
        $tail = getFileTailChunk($path, 262144);
        $lines = getTailLines($tail, 2500);
        if (!$lines) continue;
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if (preg_match($dbpattern, $line) !== 1) continue;
            $ts = getLogLineTimestamp($line);
            if ($ts === null || $ts < $threshold) continue;
            if ($ts <= $bestts) continue;
            $bestts = $ts;
            $bestlabel = strtoupper(pathinfo($name, PATHINFO_FILENAME)).': '.getLogSnippet($line, 70);
            break;
        }
    }
    if (!$seen) return 'N/A';
    if ($bestts <= 0) return '0';
    return date('Y-m-d H:i:s', $bestts).' | '.$bestlabel;
}


// Maps percentage load value to semantic color code for dashboard usage indicators.
function getUsageColor(float $pct): string {
    if ($pct > 95) return '#ef4444';
    if ($pct > 75) return '#f97316';
    if ($pct > 50) return '#3b82f6';
    return '#22c55e';
}

// Builds complete monitor snapshot with system, network, and chart path values.
function getMonitorPanelSnapshot(): array {
    [$cpup] = getCpuLoad();
    $cpu = getCpuDetails();
    $mem = getMemoryInfo();
    $disktotal = disk_total_space('.');
    $diskfree = disk_free_space('.');
    $diskused = max($disktotal - $diskfree, 0);
    $diskpct = ($disktotal > 0) ? round(($diskused / $disktotal) * 100, 1) : 0;
    $live = getRealtimePanelMetrics((float)$cpup, (float)$mem['percent']);
    $nethistmax = max(array_merge([1.0], $live['hist_up'], $live['hist_down']));
    $pathup = getSmoothAreaPath($live['hist_up'], $nethistmax);
    $pathdown = getSmoothAreaPath($live['hist_down'], $nethistmax);
    $pathupline = getSmoothLinePath($live['hist_up'], $nethistmax);
    $pathdownline = getSmoothLinePath($live['hist_down'], $nethistmax);
    $pathcpu = getSmoothAreaPath($live['hist_cpu'], 100.0);
    $pathram = getSmoothAreaPath($live['hist_ram'], 100.0);
    $pathcpuline = getSmoothLinePath($live['hist_cpu'], 100.0);
    $pathramline = getSmoothLinePath($live['hist_ram'], 100.0);
    return [
        'cpu_p' => (float)$cpup,
        'cpu' => $cpu,
        'mem' => $mem,
        'disk_total' => (float)$disktotal,
        'disk_free' => (float)$diskfree,
        'disk_used' => (float)$diskused,
        'disk_pct' => (float)$diskpct,
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
        'path_up_line' => $pathupline,
        'path_down_line' => $pathdownline,
        'path_cpu_line' => $pathcpuline,
        'path_ram_line' => $pathramline,
    ];
}

// Builds template variables for realtime traffic panel including paths and tooltips.
function getTrafficPanelVars(?array $snapshot = null): array {
    $snapshot = $snapshot ?? getMonitorPanelSnapshot();
    $cpup = (float)$snapshot['cpu_p'];
    $cpu = $snapshot['cpu'];
    $mem = $snapshot['mem'];
    $net = $snapshot['net'];
    return [
        '{%path_up%}'     => $snapshot['path_up'],
        '{%path_down%}'   => $snapshot['path_down'],
        '{%path_cpu%}'    => $snapshot['path_cpu'],
        '{%path_ram%}'    => $snapshot['path_ram'],
        '{%path_up_line%}'   => $snapshot['path_up_line'],
        '{%path_down_line%}' => $snapshot['path_down_line'],
        '{%path_cpu_line%}'  => $snapshot['path_cpu_line'],
        '{%path_ram_line%}'  => $snapshot['path_ram_line'],
        '{%tip_up%}'      => htmlspecialchars('Upstream: '.filterSize($net['tx_total']).' ('.filterSize($net['tx_rate']).'/s)', ENT_QUOTES, 'UTF-8'),
        '{%tip_down%}'    => htmlspecialchars('Downstream: '.filterSize($net['rx_total']).' ('.filterSize($net['rx_rate']).'/s)', ENT_QUOTES, 'UTF-8'),
        '{%tip_cpu%}'     => htmlspecialchars('CPU Usage: '.round((float)$cpup, 1).'%', ENT_QUOTES, 'UTF-8'),
        '{%tip_ram%}'     => htmlspecialchars('RAM Usage: '.round((float)$mem['percent'], 1).'% ('.filterSize($mem['used']).' / '.filterSize($mem['total']).')', ENT_QUOTES, 'UTF-8'),
        '{%nettx%}'       => filterSize($net['tx_total']),
        '{%netrx%}'       => filterSize($net['rx_total']),
        '{%nettxrate%}'   => filterSize($net['tx_rate']).'/s',
        '{%netrxrate%}'   => filterSize($net['rx_rate']).'/s',
        '{%cpuuse%}'      => round((float)$cpup, 1),
        '{%cpufreq%}'     => $cpu['freq'],
        '{%ram_p%}'       => round((float)$mem['percent'], 1),
        '{%ramtmb%}'      => filterSize($mem['total']),
    ];
}

// Builds template variables for server status gauges, colors, and usage metrics.
function getServerStatusVars(?array $snapshot = null): array {
    $snapshot = $snapshot ?? getMonitorPanelSnapshot();
    $cpup = (float)$snapshot['cpu_p'];
    $cpu = $snapshot['cpu'];
    $mem = $snapshot['mem'];
    $disktotal = (float)$snapshot['disk_total'];
    $diskused = (float)$snapshot['disk_used'];
    $diskpct = (float)$snapshot['disk_pct'];
    $dash = 2 * M_PI * 45;
    $offload = $dash - ($dash * $cpup / 100);
    $offr = $dash - ($dash * $mem['percent'] / 100);
    $offd = $dash - ($dash * $diskpct / 100);
    return [
        '{%dash%}'      => $dash,
        '{%off%}'       => $offload,
        '{%load_0%}'    => round((float)$cpup, 1),
        '{%cpucolor%}'  => getUsageColor((float)$cpup),
        '{%cpucores%}'  => $cpu['logical'],
        '{%cpuphys%}'   => $cpu['physical'],
        '{%cpufreq%}'   => $cpu['freq'],
        '{%dash_r%}'    => $dash,
        '{%off_r%}'     => $offr,
        '{%ramcolor%}'  => getUsageColor((float)$mem['percent']),
        '{%ram_p%}'     => round((float)$mem['percent'], 1),
        '{%ramumb%}'    => filterSize($mem['used']),
        '{%ramtmb%}'    => filterSize($mem['total']),
        '{%ramavailmb%}' => filterSize($mem['free']),
        '{%dash_d%}'    => $dash,
        '{%off_d%}'     => $offd,
        '{%diskcolor%}' => getUsageColor((float)$diskpct),
        '{%disk_p%}'    => $diskpct,
        '{%diskused%}'  => filterSize($diskused),
        '{%disktot%}'   => filterSize($disktotal),
    ];
}

// Renders monitor partial HTML for selected panels using merged template variables.
function getMonitorPartial(array $snapshot, bool $showstatus, bool $showtraffic, bool $useoob = false): string {
    $vars = array_merge(
        getServerStatusVars($snapshot),
        getTrafficPanelVars($snapshot),
        [
            '{%status_oob%}' => ($showstatus && $useoob) ? ' hx-swap-oob="outerHTML"' : '',
            '{%traffic_oob%}' => ($showtraffic && $useoob) ? ' hx-swap-oob="outerHTML"' : '',
            'if_flag' => [
                'show_layout' => false,
                'show_status' => $showstatus,
                'show_traffic' => $showtraffic,
            ],
        ]
    );
    return setTemplateBasic('basic-monitor', $vars);
}

// Collects monitor counts and database size statistics needed for dashboard summary.
function getMonitorDbStats(object $db, array $conf): array {
    $userson = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session'));
    $cntfile = $db->getSqlRowCount($db->getSqlQuery('SELECT lid FROM '.PREFIX_DB."_files WHERE status != '0'"));
    $cntnews = $db->getSqlRowCount($db->getSqlQuery('SELECT sid FROM '.PREFIX_DB."_news WHERE status != '0'"));
    $dbsize = 0;
    $dbtabs = 0;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname !== '') {
        $dbres = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
        while ($row = $db->getSqlRow($dbres)) {
            $dbsize += $row['Data_length'] + $row['Index_length'];
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

// Collects web server, firewall, extension, and protocol metadata for dashboard.
function getMonitorServerStats(): array {
    $servsw = getServerValue('SERVER_SOFTWARE', '');
    $servname = 'Web Server';
    if (stripos($servsw, 'apache') !== false) $servname = 'Apache';
    elseif (stripos($servsw, 'nginx') !== false) $servname = 'Nginx';
    elseif (stripos($servsw, 'litespeed') !== false) $servname = 'LiteSpeed';
    $servver = 'N/A';
    if (preg_match('#/(\\d+(?:\\.\\d+)+)#', (string)$servsw, $vm)) {
        $servver = $vm[1];
    }
    $srvport = getServerValue('SERVER_PORT', 'N/A');
    $https = strtolower(getServerValue('HTTPS', ''));
    $ishttps = ($https === 'on' || $https === '1') || ((int)$srvport === 443);
    $srvhttps = $ishttps ? '<span style="color:#21c45d">enabled</span>' : '<span style="color:#ef4444">disabled</span>';
    return [
        'servsw' => $servsw,
        'servname' => $servname,
        'servver' => $servver,
        'extlist' => getTooltipText(implode(', ', get_loaded_extensions()), 30),
        'srvprot' => getServerValue('SERVER_PROTOCOL', 'N/A'),
        'srvname' => getServerValue('SERVER_NAME', 'N/A'),
        'srvport' => $srvport,
        'srvroot' => getServerValue('DOCUMENT_ROOT', 'N/A'),
        'srvhttps' => $srvhttps,
        'serverip' => getServerValue('SERVER_ADDR', 'N/A'),
    ];
}

// Collects runtime diagnostics, opcache, storage, and timing metrics for monitor.
function getMonitorRuntimeStats(object $db, ?array $snapshot, string $afile): array {
    $disktot = ($snapshot !== null) ? (float)$snapshot['disk_total'] : (float)disk_total_space('.');
    $diskfree = ($snapshot !== null) ? (float)$snapshot['disk_free'] : (float)disk_free_space('.');
    $diskused = max($disktot - $diskfree, 0);
    $opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $opcacheon = $opcache && !empty($opcache['opcache_enabled']);
    $opmemused = $opcache ? $opcache['memory_usage']['used_memory'] : 0;
    $opmemfree = $opcache ? $opcache['memory_usage']['free_memory'] : 0;
    $opmemwaste = $opcache ? $opcache['memory_usage']['wasted_memory'] : 0;
    $opmemtot = $opmemused + $opmemfree + $opmemwaste;
    $reqtime = (float)getServerValue('REQUEST_TIME_FLOAT', '0');
    if ($reqtime <= 0) $reqtime = microtime(true);
    $storages = getStorageDirectorySizes();
    $quick = '<a href="'.$afile.'.php?name=security" class="sl_but">Security Logs</a> '
        .'<a href="'.$afile.'.php?name=security&amp;op=conf" class="sl_but">Security Settings</a> '
        .'<a href="'.$afile.'.php?name=database" class="sl_but">Database</a>';
    $diskwarn = ($disktot > 0 && (($diskfree / $disktot) * 100) < 10)
        ? '<span style="color:#ef4444">Low free space</span>'
        : '<span style="color:#21c45d">Normal</span>';
    return [
        'diskio' => getDiskIoMetrics(),
        'disktotal' => $disktot,
        'diskfree' => $diskfree,
        'diskused' => $diskused,
        'gdver' => (function_exists('gd_info') ? (gd_info()['GD Version'] ?? 'N/A') : 'N/A'),
        'opcacheon' => $opcacheon,
        'opmem' => $opcacheon ? filterSize((int)$opmemused).' / '.filterSize((int)$opmemtot) : 'N/A',
        'opscripts' => $opcacheon ? $opcache['opcache_statistics']['num_cached_scripts'] : 'N/A',
        'ophit' => $opcacheon ? round($opcache['opcache_statistics']['opcache_hit_rate'], 1).'%' : 'N/A',
        'uptime' => getUptimeInfo(),
        'dbhealth' => getDbHealth($db),
        'diskwarn' => $diskwarn,
        'storages' => $storages,
        'lastbackup' => getLastBackupRunLabel(),
        'error24' => getErrorLogCountHours(24),
        'uploadsz' => getUploadsSizeLabel(),
        'failed24' => getFailedLoginCountHours(24),
        'seclast24' => getSecurityEventHours(24),
        'dblast24' => getDbIssueEventHours(24),
        'quick' => $quick,
        'exectime' => round(microtime(true) - $reqtime, 3),
        'dbtime' => round($db->sqltime * 1000, 1),
    ];
}

// Collects current request metadata including cookie dump, URI, IP, and locale.
function getMonitorRequestStats(): array {
    $reqcookie = 'N/A';
    $cookies = getCookieValues();
    if (!empty($cookies)) {
        $list = [];
        foreach ($cookies as $k => $v) $list[] = $k.'='.$v;
        $reqcookie = implode('; ', $list);
    }
    $reqip = getServerValue('REMOTE_ADDR', 'N/A');
    if (function_exists('getip')) {
        $reqip = getip();
    }
    return [
        'reqmethod' => getServerValue('REQUEST_METHOD', 'N/A'),
        'reqcookie' => $reqcookie,
        'requri' => getServerValue('REQUEST_URI', 'N/A'),
        'reqquery' => getServerValue('QUERY_STRING', 'N/A'),
        'reqip' => $reqip,
        'requa' => getServerValue('HTTP_USER_AGENT', 'N/A'),
        'reqlang' => getServerValue('HTTP_ACCEPT_LANGUAGE', 'N/A'),
    ];
}

// Builds final render-ready template variables for the main monitor dashboard.
function getMonitorTemplateVars(?array $snapshot, array $ctx, array $conf, object $db, string $afile): array {
    $status = static fn(?bool $v): string => getStatusHtml($v);
    $dbhealth = $ctx['dbhealth'];
    $diskio = $ctx['diskio'];
    $storages = $ctx['storages'];
    $vars = [
            '{%statusurl%}'  => $afile.'.php?name=monitor&amp;op=status',
            '{%trafficurl%}' => $afile.'.php?name=monitor&amp;op=traffic',
            '{%syncurl%}'    => $afile.'.php?name=monitor&amp;op=sync',
            '{%status_oob%}' => '',
            '{%traffic_oob%}' => '',
            '{%cntnews%}'   => $ctx['cntnews'],
            '{%cntfile%}'   => $ctx['cntfile'],
            '{%dbtabs%}'    => $ctx['dbtabs'],
            '{%userson%}'   => $ctx['userson'],
            '{%servsoftname%}' => $ctx['servname'],
            '{%servver%}'   => $ctx['servver'],
            '{%mysql%}'     => db_version(),
            '{%phpver%}'    => PHP_VERSION,
            '{%opmode%}'    => $status(!($conf['close'] ?? 0)),
            '{%statact%}'   => $status(is_active('stat')),
            '{%referact%}'  => $status(is_active('referers')),
            '{%newslet%}'   => $status((bool)($conf['newsletter'] ?? 0)),
            '{%cache%}'     => $status((bool)($conf['cache'] ?? 0)),
            '{%rewrite%}'   => $status((bool)($conf['rewrite'] ?? 0)),
            '{%cmsver%}'    => $conf['version'] ?? '',
            '{%osname%}'    => php_uname('s'),
            '{%servfull%}'  => $ctx['servsw'],
            '{%serverip%}'  => $ctx['serverip'],
            '{%servprot%}'  => $ctx['srvprot'],
            '{%servname%}'  => $ctx['srvname'],
            '{%servport%}'  => $ctx['srvport'],
            '{%servroot%}'  => getTooltipText($ctx['srvroot'], 30),
            '{%servhttps%}' => $ctx['srvhttps'],
            '{%phpsapi%}'   => php_sapi_name(),
            '{%zend_eng%}'  => function_exists('zend_version') ? zend_version() : 'N/A',
            '{%php_char%}'  => ini_get('default_charset') ?: 'N/A',
            '{%php_exts%}'  => $ctx['extlist'],
            '{%gdver%}'     => $ctx['gdver'],
            '{%opcache_on%}'=> $status($ctx['opcacheon']),
            '{%opcache_mem%}'=> $ctx['opmem'],
            '{%opcache_scripts%}'=> $ctx['opscripts'],
            '{%opcache_hit_rate%}'=> $ctx['ophit'],
            '{%postmax%}'   => ini_get('post_max_size'),
            '{%fileup%}'    => $status((bool)ini_get('file_uploads')),
            '{%maxfileup%}' => ini_get('max_file_uploads'),
            '{%upmax%}'     => ini_get('upload_max_filesize'),
            '{%memlim%}'    => ini_get('memory_limit'),
            '{%scriptmem%}' => filterSize(memory_get_usage(true)),
            '{%mempeak%}'   => filterSize(memory_get_peak_usage(true)),
            '{%maxvars%}'   => ini_get('max_input_vars'),
            '{%maxtime%}'   => ini_get('max_execution_time'),
            '{%gzipld%}'    => $status(extension_loaded('zlib')),
            '{%zipld%}'     => $status(extension_loaded('zip')),
            '{%bz2ld%}'     => $status(extension_loaded('bz2')),
            '{%phptime%}'   => date('H:i:s'),
            '{%uptime%}'    => $ctx['uptime'],
            '{%dbszfmt%}'   => filterSize($ctx['dbsize']),
            '{%diskwarn%}'  => $ctx['diskwarn'],
            '{%quicklinks%}' => $ctx['quick'],
            '{%dbconn%}'    => $dbhealth['connections'],
            '{%dbslow%}'    => $dbhealth['slow'],
            '{%dbchar%}'    => $dbhealth['charset'],
            '{%dbsqlmode%}' => getTooltipText($dbhealth['sql_mode'], 30),
            '{%dbmaxpack%}' => $dbhealth['max_packet'],
            '{%dbbuffpool%}'=> $dbhealth['buffer_pool'],
            '{%dbtz%}'      => $dbhealth['timezone'],
            '{%dbcurname%}' => $conf['db']['name'] ?? 'N/A',
            '{%dbuser%}'    => $dbhealth['user'],
            '{%dbqnum%}'    => $db->qnum,
            '{%dbqtime%}'   => $ctx['dbtime'],
            '{%exectime%}'  => $ctx['exectime'],
            '{%diskfree%}'  => filterSize((float)$ctx['diskfree']),
            '{%disktot%}'   => filterSize((float)$ctx['disktotal']),
            '{%diskused%}'  => filterSize((float)$ctx['diskused']),
            '{%reqmeth%}'   => $ctx['reqmethod'],
            '{%reqcookie%}' => getTooltipText($ctx['reqcookie'], 30),
            '{%requri%}'    => getTooltipText($ctx['requri'], 30),
            '{%reqquery%}'  => getTooltipText($ctx['reqquery'], 30),
            '{%reqip%}'     => $ctx['reqip'],
            '{%requa%}'     => getTooltipText($ctx['requa'], 30),
            '{%reqlang%}'   => getTooltipText($ctx['reqlang'], 30),
            '{%dskread%}'   => ($diskio['read_rate'] === null) ? 'N/A' : filterSize((int)$diskio['read_rate']).'/s',
            '{%dskwrite%}'  => ($diskio['write_rate'] === null) ? 'N/A' : filterSize((int)$diskio['write_rate']).'/s',
            '{%backupdirsz%}' => ($storages['backup'] === null) ? 'N/A' : filterSize((int)$storages['backup']),
            '{%cachedirsz%}'  => ($storages['cache'] === null) ? 'N/A' : filterSize((int)$storages['cache']),
            '{%logsdirsz%}'   => ($storages['logs'] === null) ? 'N/A' : filterSize((int)$storages['logs']),
            '{%lastbackuprun%}' => $ctx['lastbackup'],
            '{%errorlog24h%}' => (string)$ctx['error24'],
            '{%uploadssz%}' => $ctx['uploadsz'],
            '{%failedlogins24h%}' => (string)$ctx['failed24'],
            '{%lastsecurityevent24h%}' => getTooltipText($ctx['seclast24'], 30),
            '{%dbissueevent24h%}' => getTooltipText($ctx['dblast24'], 30),
            'if_flag' => [
                'show_layout' => true,
                'show_status' => ($snapshot !== null),
                'show_traffic' => ($snapshot !== null),
            ],
        ];
    if ($snapshot !== null) {
        $vars = array_merge($vars, getServerStatusVars($snapshot), getTrafficPanelVars($snapshot));
    }
    return $vars;
}

// Renders the main monitor page including navigation, panels, and full dashboard layout.
function monitor(): void {
    global $db, $conf, $afile;
    setHead();
    $cont = navi().setTemplateBasic('open');
    $ctx = array_merge(
        getMonitorDbStats($db, $conf),
        getMonitorServerStats(),
        getMonitorRuntimeStats($db, null, $afile),
        getMonitorRequestStats()
    );
    $vars = getMonitorTemplateVars(null, $ctx, $conf, $db, $afile);
    $cont .= setTemplateBasic('basic-monitor', $vars).setTemplateBasic('close');
    echo $cont;
    setFoot();
}

// Renders monitor information page with standard admin info block and navigation tabs.
function info(): void {
    setHead();
    echo navi(1).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

// Renders only traffic partial panel for asynchronous dashboard refresh requests.
function traffic(): void {
    echo getMonitorPartial(getMonitorPanelSnapshot(), false, true, false);
}

// Renders only status partial panel for asynchronous dashboard refresh requests.
function status(): void {
    echo getMonitorPartial(getMonitorPanelSnapshot(), true, false, false);
}

// Renders status and traffic partials together for synchronized asynchronous updates.
function sync(): void {
    echo getMonitorPartial(getMonitorPanelSnapshot(), true, true, true);
}

switch ($op) {
    default: monitor(); break;
    case 'info': info(); break;
    case 'traffic': traffic(); break;
    case 'status': status(); break;
    case 'sync': sync(); break;
}
