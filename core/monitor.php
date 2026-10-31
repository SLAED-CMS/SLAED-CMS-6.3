<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Every Windows figure from one PowerShell run per request, killed after ten seconds so a hung WMI query never holds the sampler lock; a run that gave nothing is failed
function getWindowsProbe(): array {
    static $data = null;
    if ($data !== null) return $data;
    $data = [];
    if (!function_exists('proc_open')) return $data;
    $file = tempnam(CACHE_DIR, 'slm');
    if ($file === false) return $data;
    $script = '$p = Get-CimInstance Win32_Processor -OperationTimeoutSec 4 -ErrorAction SilentlyContinue;'
        .' $o = Get-CimInstance Win32_OperatingSystem -OperationTimeoutSec 4 -ErrorAction SilentlyContinue;'
        .' \'load=\' + ($p | Measure-Object -Property LoadPercentage -Average).Average;'
        .' \'logical=\' + ($p | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum;'
        .' \'physical=\' + ($p | Measure-Object -Property NumberOfCores -Sum).Sum;'
        .' \'mhz=\' + ($p | Select-Object -First 1).MaxClockSpeed;'
        .' \'total=\' + $o.TotalVisibleMemorySize; \'free=\' + $o.FreePhysicalMemory;'
        .' \'boot=\' + $o.LastBootUpTime.ToString(\'yyyy-MM-dd HH:mm:ss\')';
    $cmd = ['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $script];
    $proc = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', $file, 'w'], 2 => ['file', 'NUL', 'w']], $pipes);
    if (is_resource($proc)) {
        $end = microtime(true) + 10;
        while (proc_get_status($proc)['running'] && microtime(true) < $end) usleep(50000);
        if (proc_get_status($proc)['running']) proc_terminate($proc);
        proc_close($proc);
    }
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        $val = str_replace(',', '.', trim($val));
        if ($key === 'boot' && ($ts = strtotime($val)) !== false) $data['boot'] = $ts;
        elseif (is_numeric($val)) $data[trim($key)] = (float)$val;
    }
    unlink($file);
    if ($data === []) $data = ['failed' => 1];
    return $data;
}

# CPU load analyzer with cache in seconds (Windows 10/11, Linux/macOS)
function getCpuLoad(int $tcache = 2): array {
    static $cache = ['time' => 0, 'cpu' => _NO_INFO, 'info' => _NO_INFO];
    if (time() - $cache['time'] < $tcache) return [$cache['cpu'], $cache['info']];
    $percent = null;
    if (PHP_OS_FAMILY === 'Windows') {
        $percent = getWindowsProbe()['load'] ?? null;
        if ($percent === null) {
            $out = [];
            $cmd = 'wmic cpu get loadpercentage /all 2>NUL';
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
        $loadavg = getFileSafe('/proc/loadavg');
        if (!isset($raw) && $loadavg !== false) {
            $tmp = explode(' ', $loadavg);
            if (isset($tmp[0]) && is_numeric($tmp[0])) $raw = (float)$tmp[0];
        }
        $nproc = 0;
        $info = getFileSafe('/proc/cpuinfo');
        if ($info !== false) {
            preg_match_all('/^processor\s*:/m', $info, $matches);
            if (!empty($matches[0])) $nproc = count($matches[0]);
        }
        if ($nproc <= 0) $nproc = 1;
        if (isset($raw) && is_numeric($raw)) $percent = ($raw / $nproc) * 100.0;
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
    if (PHP_OS_FAMILY === 'Windows') {
        [$total, $free] = getMemoryInfoWindows();
    } else {
        [$total, $free] = getMemoryInfoLinux();
    }
    if ($total <= 0) {
        $total = getMemoryLimitBytes(true);
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

# Reads total and free physical memory on Windows from the probe, with the WMIC fallback paths; only the sampler under its lock calls it, so it keeps no cache
function getMemoryInfoWindows(): array {
    $probe = getWindowsProbe();
    $total = (int)($probe['total'] ?? 0) * 1024;
    $free = (int)($probe['free'] ?? 0) * 1024;
    if ($total > 0 && $free > 0) return [$total, $free];
    $outtot = [];
    if (function_exists('exec')) exec('wmic ComputerSystem get TotalPhysicalMemory /Value 2>NUL', $outtot);
    foreach ($outtot as $line) {
        if (str_contains($line, 'TotalPhysicalMemory')) {
            $parts = explode('=', $line);
            $total = intval($parts[1] ?? 0);
            break;
        }
    }
    $outfree = [];
    if (function_exists('exec')) exec('wmic OS get FreePhysicalMemory /Value 2>NUL', $outfree);
    foreach ($outfree as $line) {
        if (str_contains($line, 'FreePhysicalMemory')) {
            $parts = explode('=', $line);
            $free = intval($parts[1] ?? 0) * 1024;
            break;
        }
    }
    return [$total, $free];
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

# Detects logical CPU core count across Windows and Linux with environment and command fallbacks
function getCpuCores(): int {
    $cores = 0;
    if (PHP_OS_FAMILY === 'Windows') {
        $cores = (int)(getWindowsProbe()['logical'] ?? 0);
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
    if (PHP_OS_FAMILY === 'Windows') {
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
    Cache::setBody($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

# Appends a rounded metric value to history and trims array length to the configured maximum
function addHistory(array $history, float $value, int $max = 30): array {
    $history[] = round($value, 2);
    if (count($history) > $max) $history = array_slice($history, -$max);
    return $history;
}

# Returns the sample with network rates and the history buffers moved on by one timed point; points without a time, left by an older store, are dropped
function getRealtimePanelMetrics(array $store, float $cpupct, float $rampct): array {
    $now = time();
    $net = getNetworkStats();
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
    $times = is_array($store['sys_hist_time'] ?? null) ? $store['sys_hist_time'] : [];
    $keep = count($times);
    $hstdn = addHistory(array_slice($hstdn, max(count($hstdn) - $keep, 0)), $rxrate);
    $hstup = addHistory(array_slice($hstup, max(count($hstup) - $keep, 0)), $txrate);
    $hstcpu = addHistory(array_slice($hstcpu, max(count($hstcpu) - $keep, 0)), max(min($cpupct, 100), 0));
    $hstram = addHistory(array_slice($hstram, max(count($hstram) - $keep, 0)), max(min($rampct, 100), 0));
    $times = addHistory($times, $now);
    $store['net_prev_ts'] = $now;
    $store['net_prev_rx'] = $net['rx'];
    $store['net_prev_tx'] = $net['tx'];
    $store['net_hist_down'] = $hstdn;
    $store['net_hist_up'] = $hstup;
    $store['sys_hist_cpu'] = $hstcpu;
    $store['sys_hist_ram'] = $hstram;
    $store['sys_hist_time'] = $times;
    $store['net'] = ['rx_total' => (float)$net['rx'], 'tx_total' => (float)$net['tx'], 'rx_rate' => $rxrate, 'tx_rate' => $txrate];
    return $store;
}

# Reads cumulative disk read and write bytes from Linux diskstats for whole block devices only
function getDiskIoTotals(): array {
    if (PHP_OS_FAMILY === 'Windows') return ['read' => 0.0, 'write' => 0.0, 'ok' => false];
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

# Returns the sample with disk read and write rates from cumulative counters under diskio and its history buffers moved on by one point
function getDiskIoMetrics(array $store): array {
    $now = time();
    $totals = getDiskIoTotals();
    if (!$totals['ok']) return ['diskio' => ['read_rate' => null, 'write_rate' => null, 'hist_read' => [], 'hist_write' => []]] + $store;
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
    $store['diskio'] = ['read_rate' => $readrate, 'write_rate' => $wrtrate, 'hist_read' => $histread, 'hist_write' => $histwrt];
    return $store;
}

# Returns human-readable system uptime from platform sources with graceful fallback behavior
function getUptimeInfo(): string {
    if (PHP_OS_FAMILY !== 'Windows') {
        $data = getFileSafe('/proc/uptime');
        if ($data !== false) {
            $sec = (int)floatval(explode(' ', trim($data))[0] ?? 0);
            if ($sec > 0) return getUptimeText($sec);
        }
    } else {
        $boot = (int)(getWindowsProbe()['boot'] ?? 0);
        if ($boot > 0) return getUptimeText(max(time() - $boot, 0));
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

# The health of the database for the monitor, which its poll reads too: the limits as numbers, the zone as a zone with its offset, null or empty where a reading failed
function getDbHealth(object $db): array {
    $data = ['connections' => null, 'max_conn' => null, 'charset' => '', 'sql_mode' => '', 'max_packet' => null, 'buffer_pool' => null, 'timezone' => '', 'user' => ''];
    try {
        $vars = [];
        $list = "'max_connections', 'character_set_connection', 'sql_mode', 'max_allowed_packet', 'innodb_buffer_pool_size'";
        $res = $db->getSqlQuery('SHOW VARIABLES WHERE Variable_name IN ('.$list.')');
        while ($row = $db->getSqlRow($res)) $vars[$row['Variable_name']] = $row['Value'];
        $data['max_conn'] = isset($vars['max_connections']) ? (int)$vars['max_connections'] : null;
        $data['charset'] = (string)($vars['character_set_connection'] ?? '');
        $data['sql_mode'] = (string)($vars['sql_mode'] ?? '');
        $data['max_packet'] = isset($vars['max_allowed_packet']) ? (int)$vars['max_allowed_packet'] : null;
        $data['buffer_pool'] = isset($vars['innodb_buffer_pool_size']) ? (int)$vars['innodb_buffer_pool_size'] : null;
        $row = $db->getSqlRow($db->getSqlQuery("SHOW GLOBAL STATUS LIKE 'Threads_connected'"));
        if ($row) $data['connections'] = (int)$row['Value'];
        $row = $db->getSqlRow($db->getSqlQuery('SELECT @@session.time_zone AS zone, TIMEDIFF(NOW(), UTC_TIMESTAMP()) AS diff, CURRENT_USER() AS who'));
        if ($row) {
            $diff = substr((string)$row['diff'], 0, 6);
            $diff = str_starts_with($diff, '-') ? $diff : '+'.substr($diff, 0, 5);
            $data['timezone'] = ((string)$row['zone'] === $diff) ? $diff : $row['zone'].' ('.$diff.')';
            $data['user'] = (string)$row['who'];
        }
    } catch (Throwable $error) {
        if (class_exists('Logger')) Logger::addSql('error', 'Monitor DB health read failed', ['error' => $error->getMessage()]);
    }
    return $data;
}

# Counts the warning, error and critical lines Logger wrote into the given error journals within a time window, reading the tail of each
function getErrorLogCountHours(int $hours = 24, array $files = ['error_php.log', 'error_sql.log', 'error_site.log', 'error_file.log']): int|string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $thresh = time() - ($hours * 3600);
    $count = 0;
    $seen = false;
    foreach ($files as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $seen = true;
        foreach (getTailLines(getFileTailChunk($path, 262144), 2500) as $line) {
            $row = json_decode($line, true);
            if (!is_array($row) || !in_array($row['level'] ?? '', ['warning', 'error', 'critical'], true)) continue;
            $ts = strtotime((string)($row['ts'] ?? ''));
            if ($ts !== false && $ts >= $thresh) $count++;
        }
    }
    return $seen ? $count : 'N/A';
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

# Count the refused logins of the panel within the last hours from _login_log
function getFailedLoginCountHours(int $hours = 24): int {
    return getLoginLog(['admin' => 1, 'result' => ['password', 'blocked'], 'from' => date('Y-m-d H:i:s', time() - $hours * 3600)], 1, 0)['total'];
}

# Splits SERVER_SOFTWARE into the raw string, the web server name and its version, so the admin monitor and the presentation page read one parser
function getServerSoftware(): array {
    $soft = getServerValue('SERVER_SOFTWARE', '');
    $name = 'Web Server';
    if (stripos($soft, 'apache') !== false) $name = 'Apache';
    elseif (stripos($soft, 'nginx') !== false) $name = 'Nginx';
    elseif (stripos($soft, 'litespeed') !== false) $name = 'LiteSpeed';
    $ver = 'N/A';
    if (preg_match('#/(\d+(?:\.\d+)+)#', $soft, $vm)) $ver = $vm[1];
    return ['raw' => $soft, 'name' => $name, 'version' => $ver];
}

# Counts the guard decisions of the last hours across warn.log and hack.log without returning a single line of them, so a public page can show the figure and never the payload
function getSecurityEventCount(int $hours = 24): int {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $thresh = time() - ($hours * 3600);
    $count = 0;
    foreach (['warn.log', 'hack.log'] as $name) {
        $path = $logsdir.'/'.$name;
        if (!is_file($path) || !is_readable($path)) continue;
        $lines = getTailLines(getFileTailChunk($path, 262144), 2500);
        for ($ix = count($lines) - 1; $ix >= 0; $ix--) {
            $ts = getLogLineTimestamp($lines[$ix]);
            if ($ts === null) continue;
            if ($ts < $thresh) break;
            $count++;
        }
    }
    return $count;
}

# The latest security event read into time, journal, level and message, never the line, which carries the address and the browser; null with no journal, [] with no event
function getSecurityEventHours(int $hours = 24): ?array {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['warn.log', 'hack.log', 'error_site.log', 'error_php.log', 'request.log'];
    $thresh = time() - ($hours * 3600);
    $best = [];
    $bestts = 0;
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
            $row = json_decode($line, true);
            $bestts = $ts;
            $best = ['time' => $ts, 'file' => pathinfo($name, PATHINFO_FILENAME), 'level' => (string)($row['level'] ?? ''), 'text' => (string)($row['msg'] ?? '')];
            break;
        }
    }
    return $seen ? $best : null;
}

# Collects disk capacity and usage values used by both realtime and full dashboard render paths; a host that disables the disk functions reads as zero
function getMonitorDiskSnapshot(): array {
    $disksum = function_exists('disk_total_space') ? (float)disk_total_space('.') : 0.0;
    $diskfree = function_exists('disk_free_space') ? (float)disk_free_space('.') : 0.0;
    $diskused = max($disksum - $diskfree, 0);
    $diskpct = ($disksum > 0) ? round(($diskused / $disksum) * 100, 1) : 0;
    return [
        'disk_total' => $disksum,
        'disk_free' => $diskfree,
        'disk_used' => $diskused,
        'disk_pct' => (float)$diskpct,
    ];
}

# Collects CPU metadata including logical and physical cores and base frequency; on Windows it comes from the probe the sample has already run, so it needs no cache
function getCpuDetails(): array {
    $logical = getCpuCores();
    $physical = 0;
    $freq = 'N/A';
    if (PHP_OS_FAMILY === 'Windows') {
        $probe = getWindowsProbe();
        $physical = (int)($probe['physical'] ?? 0);
        $mhz = (int)($probe['mhz'] ?? 0);
        if ($mhz > 0) $freq = round($mhz / 1000, 2).' GHz';
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

# Returns the published backup artifacts of the current database with their size and timestamp, ignoring staging directories, foreign files and artifacts of another database
function getBackupArtifacts(): array {
    global $conf;
    require_once BASE_DIR.'/core/classes/backup.php';
    $dir = defined('BACKUP_DIR') ? (string)BACKUP_DIR : BASE_DIR.'/storage/backup';
    if (!is_dir($dir) || !is_readable($dir)) return [];
    $stem = Backup::getArtifactStem((string)($conf['db']['name'] ?? ''));
    $out = [];
    foreach (scandir($dir) ?: [] as $file) {
        $path = $dir.'/'.$file;
        if (!is_file($path) || is_link($path)) continue;
        $mark = Backup::getArtifactMark($file, $stem);
        if ($mark === '') continue;
        $out[$file] = ['size' => (int)filesize($path), 'mark' => $mark, 'mtime' => (int)filemtime($path)];
    }
    return $out;
}

# Returns size metrics for key storage folders and null when folders are unavailable; the backup figure counts published artifacts only, so staging never inflates it
function getStorageDirectorySizes(): array {
    $baksize = null;
    if (defined('BACKUP_DIR') && is_dir((string)BACKUP_DIR) && is_readable((string)BACKUP_DIR)) {
        $baksize = 0;
        foreach (getBackupArtifacts() as $one) $baksize += $one['size'];
    }
    $cachesz = defined('CACHE_DIR') ? getDirectorySizeBytes((string)CACHE_DIR) : null;
    $logsize = defined('LOGS_DIR') ? getDirectorySizeBytes((string)LOGS_DIR) : null;
    return [
        'backup' => $baksize,
        'cache' => $cachesz,
        'logs' => $logsize,
    ];
}

# Returns the timestamp of the last backup, taken from the last successful run and otherwise from the newest published artifact rather than from any file in the directory tree
function getLastBackupRunLabel(): string {
    $state = getSchedulerState('dbbackup');
    $ts = (int)($state['last_success'] ?? 0);
    if ($ts > 0) return date('Y-m-d H:i:s', $ts);
    $marks = array_column(getBackupArtifacts(), 'mark');
    if (!$marks) return 'N/A';
    rsort($marks);
    $when = date_create_from_format('Y-m-d_H-i-s', (string)$marks[0]);
    return ($when !== false) ? $when->format('Y-m-d H:i:s') : 'N/A';
}

# Caches expensive runtime extras such as directory scans and log counters across short monitor refresh windows
function getMonitorRuntimeExtras(): array {
    return getMonitorCache('extras', 60, static fn(): array => [
        'storages' => getStorageDirectorySizes(),
        'lastbackup' => getLastBackupRunLabel(),
        'error24' => getErrorLogCountHours(24),
        'sql24' => getErrorLogCountHours(24, ['error_sql.log']),
        'failed24' => getFailedLoginCountHours(24),
        'seclast24' => getSecurityEventHours(24),
    ]);
}

# The counts of the monitor and the size of the database, kept a minute as the poll reads the size for a signal; a Node count is that of the first active type of its mode, or null
function getMonitorDbStats(): array {
    return getMonitorCache('dbstats', 60, static function (): array {
        global $db, $conf;
        $count = static function (string $mode): ?int {
            $type = getNodeModeType($mode);
            try {
                return ($type !== null) ? getNodeReader()->setNodeType($type)->getNodeCount() : null;
            } catch (NodeException) {
                return null;
            }
        };
        $size = 0;
        $tabs = 0;
        $name = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
        if ($name !== '') {
            $res = $db->getSqlQuery('SELECT DATA_LENGTH AS data_length, INDEX_LENGTH AS index_length FROM information_schema.TABLES WHERE TABLE_SCHEMA = :name', ['name' => $name]);
            while ($row = $db->getSqlRow($res)) {
                $size += (int)($row['data_length'] ?? 0) + (int)($row['index_length'] ?? 0);
                $tabs++;
            }
        }
        return ['dbsize' => $size, 'dbtabs' => $tabs, 'cntnews' => $count('article'), 'cntfile' => $count('files')];
    });
}

# One short-lived store of the monitor per site: a JSON file of Cache under the request itself, never APCu, which each php-cgi of a Windows pool keeps to itself; $ttl 0 reads fresh
function getMonitorCache(string $key, int $ttl, callable $make): array {
    static $seen = [];
    if ($ttl <= 0) return $make();
    if (isset($seen[$key])) return $seen[$key];
    $file = Cache::getFile(['monitor', $key], 'json');
    $data = Cache::isFresh($file, $ttl) ? json_decode(Cache::getBody($file), true) : null;
    if (!is_array($data)) {
        $data = $make();
        Cache::setBody($file, (string)json_encode($data));
    }
    return $seen[$key] = $data;
}

# The one writer of monitor.json: with $fresh a sample past ten seconds is retaken by whoever gets the lock without waiting; a failed Windows probe keeps the old one
function getMonitorSample(bool $fresh = false): array {
    global $db;
    $store = getMetricStore();
    $lock = ($fresh && time() - intval($store['sampled_at'] ?? 0) >= 10) ? getSchedulerLockHandle('monitor-sample') : false;
    if ($lock !== false) {
        try {
            $store = getMetricStore();
            if (time() - intval($store['sampled_at'] ?? 0) >= 10 && !(PHP_OS_FAMILY === 'Windows' && !empty(getWindowsProbe()['failed']))) {
                [$cpu] = getCpuLoad();
                $cpud = getCpuDetails();
                $mem = getMemoryInfo();
                [$dbver] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
                $store = getMonitorDiskSnapshot() + getDiskIoMetrics(getRealtimePanelMetrics($store, (float)$cpu, (float)$mem['percent']));
                $store['cpu_p'] = (float)$cpu;
                $store['cpu'] = $cpud;
                $store['mem'] = $mem;
                $store['uptime'] = getUptimeInfo();
                $store['cores'] = $cpud['logical'];
                $store['soft'] = getServerSoftware() + ['php' => PHP_VERSION, 'db' => $dbver];
                if (time() - intval($store['uploads_at'] ?? 0) >= 3600) {
                    $store['uploads'] = getDirectorySizeBytes(defined('UPLOADS_DIR') ? (string)UPLOADS_DIR : BASE_DIR.'/uploads');
                    $store['uploads_at'] = time();
                }
                $store['sampled_at'] = time();
                setMetricStore($store);
            }
        } finally {
            deleteSchedulerHandle($lock);
        }
    }
    return $store + ['cpu_p' => 0.0, 'cpu' => ['logical' => 1, 'physical' => 'N/A', 'freq' => 'N/A'], 'mem' => ['total' => 0, 'free' => 0, 'used' => 0, 'percent' => 0],
        'net' => ['rx_total' => 0.0, 'tx_total' => 0.0, 'rx_rate' => 0.0, 'tx_rate' => 0.0], 'diskio' => ['read_rate' => null, 'write_rate' => null, 'hist_read' => [],
        'hist_write' => []], 'net_hist_down' => [], 'net_hist_up' => [], 'sys_hist_cpu' => [], 'sys_hist_ram' => [], 'sys_hist_time' => [], 'uptime' => 'N/A',
        'uploads' => null, 'uploads_at' => 0, 'sampled_at' => 0] + getMonitorDiskSnapshot();
}

# The rings and the strip of the server as a cockpit names them, the same on the home and on the monitor
function getCockpitParts(): array {
    return [
        'rings' => [
            ['key' => 'cpu', 'label' => 'CPU', 'title' => _ADM_CPU],
            ['key' => 'ram', 'label' => 'RAM', 'title' => _ADM_RAM],
            ['key' => 'disk', 'label' => _ADM_DISK, 'title' => _ADM_DISK],
            ['key' => 'opcache', 'label' => 'OPcache', 'title' => 'OPcache'],
        ],
        'strip' => [
            ['key' => 'gen', 'icon' => 'stopwatch', 'label' => _GENERATION, 'unit' => 'ms'],
            ['key' => 'sql', 'icon' => 'database', 'label' => _ADM_QUERIES, 'unit' => 'sql'],
            ['key' => 'mem', 'icon' => 'memory', 'label' => _ADM_PHPMEM, 'unit' => 'MB'],
            ['key' => 'online', 'icon' => 'people', 'label' => _ONLINE, 'unit' => ''],
        ],
    ];
}

# The four shares of a server sample with the rows of their panels
function getCockpitRings(array $snap): array {
    $opc = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $opon = is_array($opc) && !empty($opc['opcache_enabled']);
    $opmem = $opon ? ($opc['memory_usage'] ?? []) : [];
    $opused = (int)($opmem['used_memory'] ?? 0);
    $optotal = $opused + (int)($opmem['free_memory'] ?? 0) + (int)($opmem['wasted_memory'] ?? 0);
    $row = static fn(string $icon, string $label, string $value): array => ['icon' => $icon, 'label' => $label, 'value' => $value];
    $ring = static fn(string $label, string $title, float $part, string $tone, array $rows): array => [
        'label' => $label, 'title' => $title, 'value' => round($part, 1), 'tone' => $tone, 'rows' => $rows];
    $cpu = $snap['cpu'];
    $mem = $snap['mem'];
    $hit = $opon ? (float)($opc['opcache_statistics']['opcache_hit_rate'] ?? 0) : 0.0;
    $opcrow = $opon ? [$row('memory', _ADM_MEMORY, filterSize($opused).' / '.filterSize($optotal)),
        $row('file-earmark-code', _ADM_SCRIPTS, (string)($opc['opcache_statistics']['num_cached_scripts'] ?? 0))] : [];
    return [
        'cpu' => $ring('CPU', _ADM_CPU, $snap['cpu_p'], getPercentTone($snap['cpu_p']), [$row('cpu', _ADM_CORES, $cpu['physical'].' / '.$cpu['logical']),
            $row('lightning-charge', _ADM_FREQ, ($cpu['freq'] === 'N/A') ? 'N/A' : filterDecimal((float)$cpu['freq']).' GHz')]),
        'ram' => $ring('RAM', _ADM_RAM, (float)$mem['percent'], getPercentTone((float)$mem['percent']), [$row('memory', _ADM_USED, filterSize($mem['used'])),
            $row('stack', _OVERALL, filterSize($mem['total'])), $row('check2-circle', _ADM_AVAIL, filterSize($mem['free']))]),
        'disk' => $ring(_ADM_DISK, _ADM_DISK, (float)$snap['disk_pct'], getPercentTone((float)$snap['disk_pct']), [$row('pie-chart', _ADM_USED,
            filterSize($snap['disk_used'])), $row('box', _ADM_FREE, filterSize($snap['disk_free']))]),
        'opcache' => $ring('OPcache', 'OPcache', $hit, $opon ? getPercentTone(100 - $hit) : 'warn', array_merge([$row('bullseye', _ADM_HITS,
            $opon ? filterDecimal(round($hit, 1)).'%' : _OFF)], $opcrow)),
    ];
}

# The strip of the average page of the running hour and the visitors online
function getCockpitStrip(): array {
    $item = static fn(string $icon, string $label, int|float|string $num, string $unit): array => ['icon' => $icon, 'label' => $label, 'value' => $num,
        'text' => is_string($num) ? $num : filterDecimal($num), 'unit' => $unit];
    $load = getLoadAverage();
    $none = $load['num'] === 0;
    return [
        'gen' => $item('stopwatch', _GENERATION, $none ? '—' : (int)round($load['gen'] * 1000), 'ms'),
        'sql' => $item('database', _ADM_QUERIES, $none ? '—' : round($load['sql'], 1), 'sql'),
        'mem' => $item('memory', _ADM_PHPMEM, $none ? '—' : round($load['mem'] / 1048576, 1), 'MB'),
        'online' => $item('people', _ONLINE, getTableCount('session'), ''),
    ];
}

# The figures of the home cockpit for a super-administrator: four shares with the rows of their panels, the strip of the average page, the signals and the sample age
function getCockpitData(): array {
    global $afile;
    $snap = getMonitorSample(true);
    $sigs = getSystemSignals();
    return [
        'tone' => getCockpitTone($sigs),
        'href' => HOME_PATH.$afile.'.php?name=monitor',
        'gauges' => getCockpitRings($snap),
        'strip' => getCockpitStrip(),
        'signals' => $sigs,
        'age' => ($snap['sampled_at'] > 0) ? max(time() - $snap['sampled_at'], 0) : null,
    ];
}

# The pages of the last sixty minutes from statistic.log, null while the file does not hold today: the statistic is off or nobody came yet, which says nothing of the hour
function getPageHourRate(): ?float {
    $stat = getStatsToday();
    return ($stat['date'] === date('d.m.Y')) ? getStatsHourRate($stat['hours'], time()) : null;
}

# The hour of a lull, under 20 pages; a counter that does not hold today makes none
function isPageLull(?float $rate): bool {
    return $rate !== null && $rate < 20;
}

# The tone of the lamp: its worst signal, and with all passed a lull (info) while statistic.log counts under 20 pages in the last sixty minutes
function getCockpitTone(array $sigs): string {
    $lvls = array_column($sigs, 'level');
    if (in_array('danger', $lvls, true)) return 'danger';
    if (in_array('warn', $lvls, true)) return 'warn';
    return isPageLull(getPageHourRate()) ? 'info' : 'ok';
}

# A moment as the facts of the panel write it: its time today, the word for yesterday, else its day and month
function getShortDate(int $time): string {
    return match (date('Y-m-d', $time)) {
        date('Y-m-d') => date('H:i', $time),
        date('Y-m-d', strtotime('-1 day')) => _YESTERDAY,
        default => date('d.m', $time),
    };
}

# The end of support of a branch as YYYY-MM, refreshed with every release (docs/UPDATE.md, "Releasing"); the web server is not here, as distributions patch it under its old number
function getSupportEnd(string $soft, string $branch): string {
    return [
        'php' => ['8.0' => '2023-11', '8.1' => '2025-12', '8.2' => '2026-12', '8.3' => '2027-12', '8.4' => '2028-12', '8.5' => '2029-12'],
        'mariadb' => ['10.4' => '2024-06', '10.5' => '2025-06', '10.6' => '2026-07', '10.11' => '2028-02', '11.4' => '2029-05', '11.8' => '2028-06', '12.3' => '2029-05'],
        'mysql' => ['5.7' => '2023-10', '8.0' => '2026-04', '8.4' => '2032-04'],
    ][$soft][$branch] ?? '';
}

# The word on a version by its branch and the level it earns; a branch outside the table is neutral, never an alarm
function getSupportFact(string $soft, string $ver, int $time): array {
    $end = getSupportEnd($soft, implode('.', array_slice(explode('.', $ver), 0, 2)));
    if ($end === '') return ['ok', $ver];
    return (date('Y-m', $time) > $end) ? ['warn', sprintf(_MONITOR_ENDED, $ver, substr($end, 0, 4))] : ['ok', sprintf(_MONITOR_UNTIL, $ver, substr($end, 0, 4))];
}

# The product, its table of support and its version from the answer of VERSION(), whose suffix a distribution writes
function getDbLabel(string $ver): array {
    $maria = stripos($ver, 'mariadb') !== false;
    $num = preg_match('#^\d+(?:\.\d+)+#', $ver, $hit) ? $hit[0] : $ver;
    return ['name' => $maria ? 'MariaDB' : 'MySQL', 'soft' => $maria ? 'mariadb' : 'mysql', 'version' => $num];
}

# The uptime of the sample in minutes: it stores the text of getUptimeText(), which the presentation prints; null where the host gives none
function getUptimeMinutes(string $text): ?int {
    return preg_match('#^(\d+)d (\d+)h (\d+)m$#', $text, $hit) ? (int)$hit[1] * 1440 + (int)$hit[2] * 60 + (int)$hit[3] : null;
}

# Minutes of uptime in the words of the language, with or without its minutes
function getUptimeWords(int $mins, bool $long = false): string {
    $days = intdiv($mins, 1440);
    $hours = intdiv($mins % 1440, 60);
    return $long ? sprintf(_MONITOR_UPLONG, $days, $hours, $mins % 60) : sprintf(_MONITOR_UPSHORT, $days, $hours);
}

# The extensions by their weight, by the code: required are composer.json and the calls with no way round, recommended what turns a part off; no code calls APCu
function getExtensionWeight(array $have): array {
    $seen = array_map(static fn(string $one): string => str_ireplace('zend ', '', strtolower($one)), $have);
    $must = ['ctype', 'curl', 'filter', 'gd', 'json', 'mbstring', 'PDO', 'pdo_mysql', 'session'];
    $tip = ['dom', 'fileinfo', 'intl', 'OPcache', 'openssl', 'Phar', 'sodium', 'zip', 'zlib'];
    $lost = static fn(array $list): array => array_values(array_filter($list, static fn(string $one): bool => !in_array(strtolower($one), $seen, true)));
    $need = array_map('strtolower', array_merge($must, $tip));
    return [
        'must' => $must,
        'tip' => $tip,
        'must_lost' => $lost($must),
        'tip_lost' => $lost($tip),
        'seen' => $seen,
        'other' => array_values(array_filter($have, static fn(string $one): bool => !in_array(str_ireplace('zend ', '', strtolower($one)), $need, true))),
    ];
}

# The one reading behind the signals of the server: what the sample holds, what the database and PHP say now, the size of the database as its store keeps it
function getServerFacts(array $snap): array {
    global $db, $conf;
    $opc = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $opon = is_array($opc) && !empty($opc['opcache_enabled']);
    $used = $opon ? (int)($opc['memory_usage']['used_memory'] ?? 0) : 0;
    $health = getDbHealth($db);
    $soft = $snap['soft'] ?? [];
    $web = getServerSoftware();
    return [
        'time' => time(),
        'health' => $health,
        'cpu' => (float)$snap['cpu_p'],
        'ram' => (float)$snap['mem']['percent'],
        'disk' => (float)$snap['disk_pct'],
        'op_on' => $opon,
        'op_hit' => $opon ? (float)($opc['opcache_statistics']['opcache_hit_rate'] ?? 0) : 0.0,
        'op_used' => $used,
        'op_total' => $opon ? $used + (int)($opc['memory_usage']['free_memory'] ?? 0) + (int)($opc['memory_usage']['wasted_memory'] ?? 0) : 0,
        'conn' => $health['connections'],
        'max_conn' => $health['max_conn'],
        'pool' => $health['buffer_pool'],
        'dbsize' => getMonitorDbStats()['dbsize'],
        'peak' => memory_get_peak_usage(true),
        'limit' => getMemoryLimitBytes(),
        'gen' => getLoadAverage()['gen'],
        'php' => PHP_VERSION,
        'db' => (string)($soft['db'] ?? getDbVersion()),
        'web' => $web['name'].(($web['version'] !== 'N/A') ? ' '.$web['version'] : ''),
        'https' => str_starts_with(strtolower((string)$conf['homeurl']), 'https://'),
        'proto' => getServerValue('SERVER_PROTOCOL', ''),
        'exts' => get_loaded_extensions(),
        'uptime' => getUptimeMinutes((string)$snap['uptime']),
    ];
}

# The thirteen signals of the server and its programs from the facts given, failures first: each with key, icon, label, level and fact; a reading that failed is neutral
function getServerSignalList(array $in): array {
    $share = static fn(float $num, int $warn, int $fail): string => ($num >= $fail) ? 'danger' : (($num >= $warn) ? 'warn' : 'ok');
    $pct = static fn(float $num): string => filterDecimal(round($num, 1)).'%';
    $exts = getExtensionWeight($in['exts']);
    $lost = array_merge($exts['must_lost'], $exts['tip_lost']);
    $db = getDbLabel($in['db']);
    [$phpok, $phpfact] = getSupportFact('php', $in['php'], $in['time']);
    [$dbok, $dbfact] = getSupportFact($db['soft'], $db['version'], $in['time']);
    $http = preg_match('#^HTTP/(\d)#', $in['proto'], $hit) && (int)$hit[1] >= 2;
    $nope = array_merge($in['https'] ? [] : ['HTTPS'], $http ? [] : ['HTTP/2']);
    $conn = ($in['conn'] !== null && $in['max_conn'] > 0);
    $mins = $in['uptime'];
    $rows = [
        ['cpu', 'cpu', _ADM_CPU, $share($in['cpu'], 75, 90), sprintf(_MONITOR_LOADED, $pct($in['cpu']))],
        ['ram', 'memory', _ADM_RAM, $share($in['ram'], 75, 90), sprintf(_MONITOR_TAKEN, $pct($in['ram']))],
        ['disk', 'hdd', _ADM_DISK, $share($in['disk'], 80, 95), sprintf(_MONITOR_TAKEN, $pct($in['disk']))],
        ['opcache', 'lightning-charge', 'OPcache', (!$in['op_on'] || $in['op_hit'] < 80) ? 'warn' : 'ok',
            $in['op_on'] ? sprintf(_MONITOR_OPFACT, $pct($in['op_hit']), filterSize($in['op_used']).' / '.filterSize($in['op_total'])) : _OFF],
        ['conn', 'diagram-3', _MONITOR_SIGCONN, $conn ? $share($in['conn'] * 100 / $in['max_conn'], 75, 90) : 'ok',
            $conn ? sprintf(_MONITOR_OF, $in['conn'], $in['max_conn']) : _NO_INFO],
        ['innodb', 'database', _MONITOR_POOL, ($in['pool'] !== null && $in['pool'] < $in['dbsize']) ? 'warn' : 'ok',
            ($in['pool'] !== null) ? sprintf(_MONITOR_POOLFOR, filterSize($in['dbsize']), filterSize($in['pool'])) : _NO_INFO],
        ['phpmem', 'speedometer2', _ADM_PHPMEM, ($in['limit'] > 0) ? $share($in['peak'] * 100 / $in['limit'], 75, 90) : 'ok',
            sprintf(_MONITOR_OF, filterSize($in['peak']), ($in['limit'] > 0) ? filterSize($in['limit']) : _MONITOR_NOLIMIT)],
        ['gen', 'stopwatch', _MONITOR_SIGGEN, ($in['gen'] === null) ? 'ok' : (($in['gen'] > 2) ? 'danger' : (($in['gen'] > 0.5) ? 'warn' : 'ok')),
            ($in['gen'] === null) ? '—' : filterDecimal($in['gen'] * 1000, 0).' ms'],
        ['php', 'filetype-php', _ADM_SIG_PHPVER, $phpok, $phpfact],
        ['soft', 'hdd-rack', _MONITOR_SIGSOFT, $dbok, $db['name'].' '.$dbfact.' · '.$in['web']],
        ['proto', 'lock', 'HTTPS / HTTP/2', $nope ? 'warn' : 'ok', $nope ? sprintf(_MONITOR_NONE, implode(', ', $nope)) : _MONITOR_PROTOOK],
        ['ext', 'puzzle', _MONITOR_EXTS, $exts['must_lost'] ? 'danger' : ($exts['tip_lost'] ? 'warn' : 'ok'),
            $lost ? sprintf(_MONITOR_NONE, implode(', ', $lost)) : _MONITOR_EXTOK],
        ['uptime', 'clock-history', _MONITOR_UPTIME, ($mins !== null && $mins < 60) ? 'warn' : 'ok',
            ($mins === null) ? _NO_INFO : (($mins < 60) ? sprintf(_MONITOR_REBOOT, $mins) : sprintf(_MONITOR_NOREBOOT, getUptimeWords($mins)))],
    ];
    $rank = ['danger' => 0, 'warn' => 1, 'ok' => 2];
    usort($rows, static fn(array $one, array $two): int => $rank[$one[3]] <=> $rank[$two[3]]);
    return array_map(static fn(array $one): array => array_combine(['key', 'icon', 'label', 'level', 'fact'], $one), $rows);
}

# The lamp of the monitor from one list of signals: the tone decides first; with all passed no page in the hour is a flat line, under 20 a lull that beats as a norm
function getServerLamp(array $sigs, ?float $rate, float $cpu): array {
    $lvls = array_column($sigs, 'level');
    $loud = count(array_filter($lvls, static fn(string $one): bool => $one !== 'ok'));
    $worst = in_array('danger', $lvls, true) ? 'danger' : ($loud ? 'warn' : 'ok');
    $mode = ($worst !== 'ok') ? $worst : (($rate !== null && round($rate) < 1) ? 'flat' : (isPageLull($rate) ? 'lull' : 'ok'));
    $base = ['ok' => 72, 'lull' => 72, 'warn' => 96, 'danger' => 168, 'flat' => 0][$mode];
    return [
        'tone' => in_array($mode, ['flat', 'lull'], true) ? 'info' : $worst,
        'mode' => $mode,
        'loud' => $loud,
        'pass' => count($lvls) - $loud,
        'total' => count($lvls),
        'base' => $base,
        'bpm' => (int)round($base * (0.8 + $cpu / 100)),
    ];
}

# The load of one statistic line by hour: pages, generation ms, queries and KB, pages counted only where the hour carries a load, as days before the load fields have none
function getStatLoadHours(array $pts): array {
    $out = [];
    foreach (['num' => 13, 'gen' => 17, 'sql' => 18, 'mem' => 19] as $key => $idx) {
        $out[$key] = array_map('intval', array_pad(array_slice(explode(',', trim((string)($pts[$idx] ?? ''))), 0, 24), 24, 0));
    }
    foreach ($out['gen'] as $hour => $val) if ($val <= 0) $out['num'][$hour] = 0;
    return $out;
}

# One line of the counter by its field names: a day of an old archive stops at the eighth field, so its hours, load and session maps are empty; null for a line that is no day
function getStatRow(string $line): ?array {
    $pts = explode('|', trim($line));
    if (count($pts) < 8 || !preg_match('#^(\d{2})\.(\d{2})\.(\d{4})$#', $pts[0], $hit)) return null;
    $dims = [];
    $maps = ['browsers' => 8, 'os' => 9, 'devices' => 10, 'countries' => 11, 'refcat' => 12, 'newret' => 14, 'depth' => 15, 'duration' => 16];
    $maps += ['hosts' => 20, 'pages' => 21, 'bots' => 22, 'bottype' => 23];
    foreach ($maps as $key => $idx) {
        $dims[$key] = getCounterField(trim($pts[$idx] ?? ''));
    }
    return getStatLoadHours($pts) + [
        'date' => $pts[0], 'month' => $hit[3].'-'.$hit[2], 'day' => (int)$hit[1],
        'week' => (int)date('N', mktime(0, 0, 0, (int)$hit[2], (int)$hit[1], (int)$hit[3])) - 1,
        'u' => (int)$pts[1], 'h' => (int)$pts[2], 'all' => (int)$pts[3], 'bot' => (int)$pts[4], 'ref' => (int)$pts[5], 'home' => (int)$pts[6], 'usr' => (int)$pts[7],
        'aud' => max(0, (int)$pts[1] - (int)$pts[4] - (int)$pts[5]),
        'hours' => array_map('intval', array_pad(array_slice(explode(',', trim($pts[13] ?? '')), 0, 24), 24, 0)),
        'dims' => $dims,
    ];
}

# The days of one month oldest first: its file of the archive, then the lines of days.log and statistic.log that fall in it, as a month stays there until the first hit of the next
function getStatRows(string $month): array {
    $rows = [];
    foreach ([COUNTER_DIR.'/statistic/statistic_'.$month.'.log', COUNTER_DIR.'/days.log', COUNTER_DIR.'/statistic.log'] as $file) {
        $list = (is_file($file) && is_readable($file)) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        foreach ($list ?: [] as $line) {
            $row = getStatRow($line);
            if ($row !== null && $row['month'] === $month) $rows[$row['day']] = $row;
        }
    }
    ksort($rows);
    return array_values($rows);
}

# The sums of a run of days as the page shows them: counts added, the load that of the average page, the four shares of the rings
function getStatSum(array $rows): array {
    $sum = ['days' => count($rows), 'u' => 0, 'h' => 0, 'home' => 0, 'ref' => 0, 'bot' => 0, 'aud' => 0, 'usr' => 0, 'hours' => array_fill(0, 24, 0), 'num' => array_fill(0, 24, 0),
        'gen' => array_fill(0, 24, 0), 'sql' => array_fill(0, 24, 0), 'mem' => array_fill(0, 24, 0), 'dims' => []];
    foreach ($rows as $row) {
        foreach (['u', 'h', 'home', 'ref', 'bot', 'aud', 'usr'] as $key) $sum[$key] += $row[$key];
        foreach (['hours', 'num', 'gen', 'sql', 'mem'] as $key) foreach ($row[$key] as $hour => $val) $sum[$key][$hour] += $val;
        foreach ($row['dims'] as $key => $map) {
            $sum['dims'][$key] ??= [];
            foreach ($map as $name => $val) $sum['dims'][$key][$name] = ($sum['dims'][$key][$name] ?? 0) + $val;
        }
    }
    $pages = array_sum($sum['num']);
    $map = static fn(string $key): array => $sum['dims'][$key] ?? [];
    $pct = static fn(int|float $num, int|float $den): int => ($den > 0) ? (int)round($num / $den * 100) : 0;
    $dev = array_sum($map('devices'));
    $man = $dev - ($map('devices')['bot'] ?? 0);
    return $sum + [
        'pages' => $pages,
        'avg_gen' => $pages ? array_sum($sum['gen']) / $pages : null,
        'avg_sql' => $pages ? array_sum($sum['sql']) / $pages : null,
        'avg_mem' => $pages ? array_sum($sum['mem']) / 1024 / $pages : null,
        'shares' => [
            'human' => $pct($man, $dev),
            'mobile' => $pct($map('devices')['mobile'] ?? 0, $man),
            'ret' => $pct($map('newret')['returning'] ?? 0, array_sum($map('newret'))),
            'search' => $pct($map('refcat')['search'] ?? 0, array_sum($map('refcat'))),
        ],
    ];
}

# The pace of the running hour against the mean of that hour on its weekday over four weeks; under 20 pages a lull as on the lamp, a norm under 20 is noise, an ordinary day
function getStatPace(array $live, array $past, int $time): array {
    $hour = (int)date('G', $time);
    $same = array_slice(array_values(array_filter($past, static fn(array $row): bool => $row['week'] === $live['week'])), -4);
    $norm = $same ? array_sum(array_map(static fn(array $row): int => $row['hours'][$hour], $same)) / count($same) : 0.0;
    $rate = getStatsHourRate($live['hours'], $time);
    $share = ($norm >= 20) ? $rate / $norm : 1.0;
    $pace = match (true) {
        $rate < 20 => 'lull',
        $share < 0.5 => 'calm',
        $share < 1.3 => 'day',
        $share < 2 => 'rise',
        default => 'rush',
    };
    return ['pace' => $pace, 'rate' => $rate, 'norm' => $norm, 'share' => $share, 'week' => $live['week'], 'hour' => $hour];
}

# Every closed month with its days, hosts and hits, kept in archive.json until the stamp of its file changes; a past month not yet rolled over has no file and is read each time
function getStatArchive(int $time = 0): array {
    $store = COUNTER_DIR.'/archive.json';
    $kept = is_file($store) ? json_decode((string)file_get_contents($store), true) : null;
    $kept = is_array($kept) ? $kept : [];
    $rows = [];
    foreach (is_dir(COUNTER_DIR.'/statistic') ? (scandir(COUNTER_DIR.'/statistic') ?: []) : [] as $name) {
        if (!preg_match('#^statistic_(\d{4}-\d{2})\.log$#', $name, $hit)) continue;
        $file = COUNTER_DIR.'/statistic/'.$name;
        $stamp = filemtime($file).'-'.filesize($file);
        if (($kept[$hit[1]]['stamp'] ?? '') === $stamp) {
            $rows[$hit[1]] = $kept[$hit[1]];
            continue;
        }
        $row = ['stamp' => $stamp, 'days' => 0, 'u' => 0, 'h' => 0];
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $pts = explode('|', $line);
            if (count($pts) < 8) continue;
            $row['days']++;
            $row['u'] += (int)$pts[1];
            $row['h'] += (int)$pts[2];
        }
        $rows[$hit[1]] = $row;
    }
    if ($rows !== $kept) addFile($store, (string)json_encode($rows));
    $date = getStatsToday()['date'];
    $late = (strlen($date) === 10) ? substr($date, 6).'-'.substr($date, 3, 2) : '';
    if ($late !== '' && $late < date('Y-m', $time ?: time()) && !isset($rows[$late])) {
        $sum = getStatSum(getStatRows($late));
        $rows[$late] = ['days' => $sum['days'], 'u' => $sum['u'], 'h' => $sum['h']];
        ksort($rows);
    }
    return $rows;
}

# The one reading of the counter behind the statistic page: a month with its sums, the day of the scope, the same days a month before, the run of the rhythm, the week and the pace
function getStatData(string $month = '', int $pick = -1, bool $now = true, int $time = 0): array {
    $time = $time ?: time();
    $cur = date('Y-m', $time);
    $keys = array_keys(getStatArchive($time));
    if (!in_array($cur, $keys, true)) $keys[] = $cur;
    sort($keys);
    $key = in_array($month, $keys, true) ? $month : $cur;
    $idx = (int)array_search($key, $keys, true);
    $rows = getStatRows($key);
    $num = count($rows);
    $back = static fn(string $one): string => date('Y-m', mktime(0, 0, 0, (int)substr($one, 5, 2) - 1, 1, (int)substr($one, 0, 4)));
    $past = getStatRows($back($key));
    $live = $num > 0 && $rows[$num - 1]['date'] === date('d.m.Y', $time);
    $at = isset($rows[$pick]) ? $pick : max(0, $num - (($live && !$now && $num > 1) ? 2 : 1));
    $run = array_slice($rows, 0, $at + 1);
    $list = $past;
    $step = $back($key);
    while ($list !== [] && count($run) < 30) {
        $run = array_merge($list, $run);
        $step = $back($step);
        $list = (count($run) < 30) ? getStatRows($step) : [];
    }
    $first = $num ? $rows[0]['day'] : 0;
    $last = $num ? $rows[$num - 1]['day'] : 0;
    $then = array_values(array_filter($past, static fn(array $row): bool => $row['day'] >= $first && $row['day'] <= $last));
    $grid = array_fill(0, 7, array_fill(0, 24, 0));
    $seen = array_fill(0, 7, 0);
    foreach ($rows as $i => $row) {
        if ($live && $i === $num - 1) continue;
        $seen[$row['week']]++;
        foreach ($row['hours'] as $hour => $val) $grid[$row['week']][$hour] += $val;
    }
    foreach ($grid as $day => $line) if ($seen[$day]) $grid[$day] = array_map(static fn(int $val): float => round($val / $seen[$day], 2), $line);
    return [
        'key' => $key,
        'prev' => $keys[$idx - 1] ?? '',
        'next' => $keys[$idx + 1] ?? '',
        'months' => $keys,
        'is_run' => $key === $cur,
        'is_live' => $live,
        'rows' => $rows,
        'sum' => getStatSum($rows),
        'then' => $then ? getStatSum($then) : null,
        'at' => $at,
        'is_pick' => isset($rows[$pick]),
        'day' => $rows[$at] ?? null,
        'past' => $rows[$at - 1] ?? ($past ? $past[count($past) - 1] : null),
        'run' => array_slice($run, -30),
        'week' => $grid,
        'pace' => $live ? getStatPace($rows[$num - 1], array_merge($past, array_slice($rows, 0, $num - 1)), $time) : null,
        'all' => getStatsToday()['total'],
    ];
}

# The fourteen signals of the lamp, failures first, each with check, level, fact of a failure and page; journals and queues kept $ttl seconds, 0 fresh
function getSystemSignals(int $ttl = 60): array {
    global $conf, $afile;
    require_once BASE_DIR.'/core/classes/update.php';
    $runs = getMonitorCache('signals', $ttl, static function (): array {
        global $mailer;
        $late = 0;
        foreach (getSchedulerJobs() as $name => $job) {
            $next = ((int)($job['active'] ?? 0) === 1) ? getSchedulerPlannedTime($job, getSchedulerState($name)) : 0;
            if ($next > 0 && $next < time() - 3600) $late++;
        }
        return ['php' => getErrorLogCountHours(24, ['error_php.log', 'error_site.log', 'error_file.log']), 'sql' => getErrorLogCountHours(24, ['error_sql.log']),
            'login' => getFailedLoginCountHours(24), 'attack' => getSecurityEventCount(24), 'late' => $late, 'backup' => (int)strtotime(getLastBackupRunLabel()),
            'mail' => $mailer->getPending(3600)];
    });
    $base = HOME_PATH.$afile.'.php?name=';
    $self = getSelfCheckState();
    $disk = getMonitorDiskSnapshot();
    $free = ($disk['disk_total'] > 0) ? $disk['disk_free'] * 100 / $disk['disk_total'] : 100;
    $opc = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $found = Update::getState()['found'];
    $ver = Update::isVersion((string)($found['version'] ?? '')) ? (string)$found['version'] : '';
    $age = time() - $runs['backup'];
    $bak = empty($conf['security']['log_b']) ? ['warn', _OFF] : (($runs['backup'] <= 0) ? ['danger', _ADM_SIG_NOBAK]
        : [($age > 86400 * max(1, intval($conf['security']['dump_age']))) ? 'warn' : 'ok', getShortDate($runs['backup'])]);
    $day = static fn(int|string $num): string => sprintf(_ADM_SIG_DAY, $num);
    $count = static fn(int|string $num, int $warn, int $fail = 0): string => !is_int($num) ? 'ok'
        : (($fail > 0 && $num >= $fail) ? 'danger' : (($num >= $warn) ? 'warn' : 'ok'));
    $rows = [
        ['dirs', _ADM_SIG_DIRS, 'security', $self['open'] ? 'danger' : (($self['none'] || $self['unknown']) ? 'warn' : 'ok'),
            $self['open'] ? _ADM_SIG_OPEN : ($self['none'] ? _ADM_SIG_NONE : _ADM_SIG_UNK)],
        ['setup', _ADM_SIG_SETUP, '', is_file(PUBLIC_DIR.'/setup.php') ? 'warn' : 'ok', _ADM_SIG_ROOT],
        ['php', _ADM_SIG_PHP, 'monitor', $count($runs['php'], 1, 100), $day($runs['php'])],
        ['sql', _ADM_SIG_DB, 'database', $count($runs['sql'], 1, 10), $day($runs['sql'])],
        ['disk', _ADM_DISK, 'monitor', ($free < 10) ? 'danger' : (($disk['disk_pct'] > 75) ? 'warn' : 'ok'), filterDecimal($disk['disk_pct']).'%'],
        ['backup', _ADM_SIG_BACKUP, 'database&op=dump', $bak[0], $bak[1]],
        ['login', _ADM_SIG_LOGIN, 'security&op=logins', $count($runs['login'], 20), $day($runs['login'])],
        ['attack', _ADM_SIG_ATTACK, 'security', $count($runs['attack'], 1), $day($runs['attack'])],
        ['cron', _SCHEDULER, 'scheduler', ($runs['late'] > 0) ? 'warn' : 'ok', sprintf(_ADM_SIG_LATE, $runs['late'])],
        ['https', 'HTTPS', 'config', str_starts_with(strtolower((string)$conf['homeurl']), 'https://') ? 'ok' : 'warn', _OFF],
        ['opcache', 'OPcache', 'monitor', (is_array($opc) && !empty($opc['opcache_enabled'])) ? 'ok' : 'warn', _OFF],
        ['phpver', _ADM_SIG_PHPVER, 'monitor', version_compare(PHP_VERSION, '8.1.0', '<') ? 'warn' : 'ok', PHP_VERSION],
        ['mail', _MAILOPT, 'newsletter&op=queue', ($runs['mail'] > 0) ? 'warn' : 'ok', sprintf(_ADM_SIG_WAIT, $runs['mail'])],
        ['update', _UPDATE_TITLE, 'update', ($ver === '') ? 'ok' : (empty($found['security']) ? 'warn' : 'danger'), $ver],
    ];
    $rank = ['danger' => 0, 'warn' => 1, 'ok' => 2];
    $out = array_map(static fn(array $one): array => ['key' => $one[0], 'label' => $one[1], 'href' => ($one[2] !== '') ? $base.$one[2] : '', 'level' => $one[3],
        'fact' => ($one[3] === 'ok') ? '' : $one[4]], $rows);
    usort($out, static fn(array $one, array $two): int => $rank[$one['level']] <=> $rank[$two['level']]);
    return $out;
}
