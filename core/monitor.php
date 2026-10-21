<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

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
        $cmd = 'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command'
            .' "(Get-CimInstance Win32_Processor -ErrorAction SilentlyContinue | Measure-Object -Property LoadPercentage -Average).Average"';
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

# Reads total and free physical memory on Windows via PowerShell CIM and WMIC fallback paths
function getMemoryInfoWindows(): array {
    return getMonitorCache('memory_windows', 10, static function (): array {
        $free = 0;
        $total = 0;
        $ps = [];
        if (function_exists('exec')) exec(
            'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command'
            .' "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory | Format-List)"',
            $ps
        );
        foreach ($ps as $line) {
            if (str_contains($line, 'TotalVisibleMemorySize')) {
                $parts = explode(':', $line, 2);
                $total = intval(trim($parts[1] ?? '0')) * 1024;
            } elseif (str_contains($line, 'FreePhysicalMemory')) {
                $parts = explode(':', $line, 2);
                $free = intval(trim($parts[1] ?? '0')) * 1024;
            }
        }
        if ($total > 0 && $free > 0) return [$total, $free];
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
        return [$total, $free];
    });
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
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $out = [];
        if (function_exists('exec')) exec(
            'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command'
            .' "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfLogicalProcessors -Sum).Sum"',
            $out
        );
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
    Cache::setBody($file, json_encode($data, JSON_UNESCAPED_UNICODE));
}

# Appends a rounded metric value to history and trims array length to the configured maximum
function addHistory(array $history, float $value, int $max = 30): array {
    $history[] = round($value, 2);
    if (count($history) > $max) $history = array_slice($history, -$max);
    return $history;
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
        exec(
            'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command'
            ." \"(Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToString('yyyy-MM-dd HH:mm:ss')\"",
            $out
        );
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
        $res = $db->getSqlQuery('SELECT CURRENT_TIME() as db_time');
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_time'])) $tz = (string)$row['db_time'];
        $data['timezone'] = $tz;
        $res = $db->getSqlQuery('SELECT CURRENT_USER() as db_user');
        if ($res && ($row = $db->getSqlRow($res)) && isset($row['db_user'])) $data['user'] = (string)$row['db_user'];
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

# Returns latest recent security-related event with source label and timestamp context
function getSecurityEventHours(int $hours = 24): string {
    $logsdir = defined('LOGS_DIR') ? (string)LOGS_DIR : BASE_DIR.'/storage/logs';
    $files = ['warn.log', 'hack.log', 'error_site.log', 'error_php.log', 'request.log'];
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

# Collects raw CPU metadata including logical and physical cores and current base frequency
function getCpuDetailsRaw(): array {
    $logical = getCpuCores();
    $physical = 0;
    $freq = 'N/A';
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        if (function_exists('exec')) {
            $out = [];
            exec(
                'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Measure-Object -Property NumberOfCores -Sum).Sum"',
                $out
            );
            if (!empty($out) && is_numeric(trim((string)$out[0]))) $physical = (int)trim((string)$out[0]);
            $out = [];
            exec(
                'powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "(Get-CimInstance Win32_Processor | Select-Object -First 1 -ExpandProperty MaxClockSpeed)"',
                $out
            );
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

# Returns the CPU metadata, read again only every two minutes because the cores and the clock do not change between polls
function getCpuDetails(): array {
    return getMonitorCache('cpu_details', 120, 'getCpuDetailsRaw');
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

# Returns formatted uploads directory size or N/A when directory size is unavailable
function getUploadsSizeLabel(): string {
    $updir = defined('UPLOADS_DIR') ? (string)UPLOADS_DIR : BASE_DIR.'/uploads';
    $bytes = getDirectorySizeBytes($updir);
    return ($bytes === null) ? 'N/A' : filterSize((int)$bytes);
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

# Caches expensive runtime extras such as directory scans and log counters across short monitor refresh windows
function getMonitorRuntimeExtras(): array {
    return getMonitorCache('runtime_extras', 60, static fn(): array => [
        'storages' => getStorageDirectorySizes(),
        'lastbackup' => getLastBackupRunLabel(),
        'error24' => getErrorLogCountHours(24),
        'uploadsz' => getUploadsSizeLabel(),
        'failed24' => getFailedLoginCountHours(24),
        'seclast24' => getSecurityEventHours(24),
        'dblast24' => getDbIssueEventHours(24),
    ]);
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

# The figures of the home cockpit for a super-administrator: four shares with the rows of their panels, the strip of this request and the signals, toned by the worst
function getCockpitData(): array {
    global $afile;
    $snap = getMonitorCache('cockpit', 3, static function (): array {
        [$cpup] = getCpuLoad();
        $opc = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $mem = is_array($opc) ? ($opc['memory_usage'] ?? []) : [];
        return ['cpu_p' => is_numeric($cpup) ? (float)$cpup : 0.0, 'cpu' => getCpuDetails(), 'mem' => getMemoryInfo(), 'op_on' => is_array($opc) && !empty($opc['opcache_enabled']),
            'op_hit' => (float)($opc['opcache_statistics']['opcache_hit_rate'] ?? 0), 'op_used' => (int)($mem['used_memory'] ?? 0),
            'op_total' => (int)($mem['used_memory'] ?? 0) + (int)($mem['free_memory'] ?? 0) + (int)($mem['wasted_memory'] ?? 0),
            'op_files' => (int)($opc['opcache_statistics']['num_cached_scripts'] ?? 0)] + getMonitorDiskSnapshot();
    });
    $row = static fn(string $icon, string $label, string $value): array => ['icon' => $icon, 'label' => $label, 'value' => $value];
    $ring = static fn(string $label, string $title, float $part, string $tone, array $rows): array => [
        'label' => $label, 'title' => $title, 'value' => round($part, 1), 'tone' => $tone, 'rows' => $rows];
    $cpu = $snap['cpu'];
    $mem = $snap['mem'];
    $hit = $snap['op_on'] ? $snap['op_hit'] : 0.0;
    $opcrow = $snap['op_on'] ? [$row('memory', _ADM_MEMORY, filterSize($snap['op_used']).' / '.filterSize($snap['op_total'])),
        $row('file-earmark-code', _ADM_SCRIPTS, (string)$snap['op_files'])] : [];
    $sigs = getSystemSignals();
    $lvls = array_column($sigs, 'level');
    $online = getTableCount('session');
    $load = getLoadStats();
    return [
        'tone' => in_array('danger', $lvls, true) ? 'danger' : (in_array('warn', $lvls, true) ? 'warn' : 'ok'),
        'href' => HOME_PATH.$afile.'.php?name=monitor',
        'gauges' => [
            'cpu' => $ring('CPU', _ADM_CPU, $snap['cpu_p'], getPercentTone($snap['cpu_p']), [$row('cpu', _ADM_CORES, $cpu['physical'].' / '.$cpu['logical']),
                $row('lightning-charge', _ADM_FREQ, (string)$cpu['freq'])]),
            'ram' => $ring('RAM', _ADM_RAM, (float)$mem['percent'], getPercentTone((float)$mem['percent']), [$row('memory', _ADM_USED, filterSize($mem['used'])),
                $row('stack', _OVERALL, filterSize($mem['total'])), $row('check2-circle', _ADM_AVAIL, filterSize($mem['free']))]),
            'disk' => $ring(_ADM_DISK, _ADM_DISK, (float)$snap['disk_pct'], getPercentTone((float)$snap['disk_pct']), [$row('pie-chart', _ADM_USED,
                filterSize($snap['disk_used'])), $row('box', _ADM_FREE, filterSize($snap['disk_free']))]),
            'opcache' => $ring('OPcache', 'OPcache', $hit, $snap['op_on'] ? getPercentTone(100 - $hit) : 'warn', array_merge([$row('bullseye', _ADM_HITS,
                $snap['op_on'] ? round($hit, 1).'%' : _OFF)], $opcrow)),
        ],
        'strip' => [
            'gen' => ['icon' => 'stopwatch', 'label' => _GENERATION, 'value' => (int)round($load['gen'] * 1000), 'unit' => 'ms'],
            'sql' => ['icon' => 'database', 'label' => _ADM_QUERIES, 'value' => $load['qnum'], 'unit' => 'sql'],
            'mem' => ['icon' => 'memory', 'label' => _ADM_PHPMEM, 'value' => round($load['mem'] / 1048576, 1), 'unit' => 'MB'],
            'online' => ['icon' => 'people', 'label' => _ONLINE, 'value' => $online, 'unit' => ''],
        ],
        'signals' => $sigs,
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
    $past = date('Y-m-d', strtotime('-1 day'));
    $when = match (true) {
        date('Y-m-d', $runs['backup']) === date('Y-m-d') => date('H:i', $runs['backup']),
        date('Y-m-d', $runs['backup']) === $past => _YESTERDAY,
        default => date('d.m', $runs['backup']),
    };
    $bak = empty($conf['security']['log_b']) ? ['warn', _OFF] : (($runs['backup'] <= 0) ? ['danger', _ADM_SIG_NOBAK]
        : [($age > 86400 * max(1, intval($conf['security']['dump_age']))) ? 'warn' : 'ok', $when]);
    $day = static fn(int|string $num): string => sprintf(_ADM_SIG_DAY, $num);
    $count = static fn(int|string $num, int $warn, int $fail = 0): string => !is_int($num) ? 'ok'
        : (($fail > 0 && $num >= $fail) ? 'danger' : (($num >= $warn) ? 'warn' : 'ok'));
    $rows = [
        ['dirs', _ADM_SIG_DIRS, 'security', $self['open'] ? 'danger' : (($self['none'] || $self['unknown']) ? 'warn' : 'ok'),
            $self['open'] ? _ADM_SIG_OPEN : ($self['none'] ? _ADM_SIG_NONE : _ADM_SIG_UNK)],
        ['setup', _ADM_SIG_SETUP, '', is_file(PUBLIC_DIR.'/setup.php') ? 'warn' : 'ok', _ADM_SIG_ROOT],
        ['php', _ADM_SIG_PHP, 'monitor', $count($runs['php'], 1, 100), $day($runs['php'])],
        ['sql', _ADM_SIG_DB, 'database', $count($runs['sql'], 1, 10), $day($runs['sql'])],
        ['disk', _ADM_DISK, 'monitor', ($free < 10) ? 'danger' : (($disk['disk_pct'] > 75) ? 'warn' : 'ok'), $disk['disk_pct'].'%'],
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
