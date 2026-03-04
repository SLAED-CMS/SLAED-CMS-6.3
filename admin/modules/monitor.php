<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    $ops = ['name=monitor', 'name=monitor&op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs('System Monitor', 'statistic.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

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

function getMemoryInfo(): array {
    $free = 0;
    $total = 0;

    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        // Prefer PowerShell CIM on modern Windows (WMIC is deprecated/disabled on many systems)
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
            $used = max($total - $free, 0);
            return [
                'total' => $total,
                'free' => $free,
                'used' => $used,
                'percent' => round(($used / $total) * 100, 1),
            ];
        }

        $cmd = 'wmic ComputerSystem get TotalPhysicalMemory /Value';
        $outtot = [];
        if (function_exists('exec')) exec($cmd, $outtot);
        foreach ($outtot as $line) {
            if (str_contains($line, 'TotalPhysicalMemory')) {
                $parts = explode('=', $line);
                $total = intval($parts[1]);
                break;
            }
        }

        $cmd = 'wmic OS get FreePhysicalMemory /Value';
        $outfree = [];
        if (function_exists('exec')) exec($cmd, $outfree);
        foreach ($outfree as $line) {
            if (str_contains($line, 'FreePhysicalMemory')) {
                $parts = explode('=', $line);
                $free = intval($parts[1]) * 1024;
                break;
            }
        }
    } else {
        $data = getFileSafe('/proc/meminfo');
        if ($data) {
            $data = explode("\n", $data);
            $meminfo = [];
            foreach ($data as $line) {
                if (!str_contains($line, ':')) continue;
                [$key, $val] = explode(':', $line);
                $meminfo[trim($key)] = trim($val);
            }
            $total = intval($meminfo['MemTotal'] ?? 0) * 1024;
            $free = intval($meminfo['MemAvailable'] ?? 0) * 1024;
            if ($free <= 0) {
                $mem_free = intval($meminfo['MemFree'] ?? 0);
                $buffers = intval($meminfo['Buffers'] ?? 0);
                $cached = intval($meminfo['Cached'] ?? 0);
                $free = ($mem_free + $buffers + $cached) * 1024;
            }
        }
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
            $envCores = getenv('NUMBER_OF_PROCESSORS');
            if ($envCores !== false && is_numeric($envCores)) $cores = (int)$envCores;
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

function getNginxVersion(): string {
    $servsw = $_SERVER['SERVER_SOFTWARE'] ?? getenv('SERVER_SOFTWARE') ?: '';
    if (preg_match('#nginx/([0-9.]+)#i', (string)$servsw, $m)) return $m[1];

    if (function_exists('exec')) {
        $out = [];
        exec('nginx -v 2>&1', $out);
        if (!empty($out) && preg_match('#nginx/([0-9.]+)#i', implode(' ', $out), $m)) return $m[1];
        if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
            $w = [];
            exec('where nginx 2>NUL', $w);
            foreach ($w as $bin) {
                $bin = trim($bin);
                if ($bin === '') continue;
                $cmdOut = [];
                exec('"'.$bin.'" -v 2>&1', $cmdOut);
                if (!empty($cmdOut) && preg_match('#nginx/([0-9.]+)#i', implode(' ', $cmdOut), $m)) return $m[1];
            }
        }
    }
    return 'N/A';
}

function getFirewallInfo(): array {
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $enabled = 0;
        if (function_exists('exec')) {
            $out = [];
            exec("powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command \"(Get-NetFirewallProfile -ErrorAction SilentlyContinue | Where-Object {\$_.Enabled -eq 'True'} | Measure-Object).Count\"", $out);
            if (!empty($out) && is_numeric(trim((string)$out[0]))) $enabled = (int)trim((string)$out[0]);
        }
        $isOn = $enabled > 0;
        return ['name' => 'Windows Firewall', 'state' => $isOn ? 'On' : 'Off', 'on' => $isOn];
    }

    if (function_exists('exec')) {
        $out = [];
        exec('ufw status 2>/dev/null', $out);
        $txt = strtolower(implode(' ', $out));
        if ($txt !== '') {
            $isOn = str_contains($txt, 'status: active');
            return ['name' => 'UFW', 'state' => $isOn ? 'On' : 'Off', 'on' => $isOn];
        }

        $out = [];
        exec('systemctl is-active firewalld 2>/dev/null', $out);
        $state = strtolower(trim((string)($out[0] ?? '')));
        if ($state !== '') {
            $isOn = $state === 'active';
            return ['name' => 'firewalld', 'state' => $isOn ? 'On' : 'Off', 'on' => $isOn];
        }
    }

    return ['name' => 'Firewall', 'state' => 'N/A', 'on' => null];
}

function getNetworkStats(): array {
    $rx = 0;
    $tx = 0;
    if (str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $output = [];
        if (function_exists('exec')) exec('netstat -e', $output);
        foreach ($output as $line) {
            if (stripos($line, 'Bytes') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                $rxRaw = (string)($parts[1] ?? '0');
                $txRaw = (string)($parts[2] ?? '0');
                $rx = (float)preg_replace('/[^\d]/', '', $rxRaw);
                $tx = (float)preg_replace('/[^\d]/', '', $txRaw);
                break;
            }
        }
    } else {
        $data = getFileSafe('/proc/net/dev');
        if ($data) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (str_contains($line, ':')) {
                    $parts = preg_split('/\s+/', trim(substr($line, strpos($line, ':') + 1)));
                    $rx += $parts[0] ?? 0;
                    $tx += $parts[8] ?? 0;
                }
            }
        }
    }
    return ['rx' => $rx, 'tx' => $tx];
}

function getMetricStorePath(): string {
    return LOGS_DIR.'/monitor_metrics.json';
}

function readMetricStore(): array {
    $file = getMetricStorePath();
    if (!is_file($file) || !is_readable($file)) return [];
    $json = file_get_contents($file);
    if ($json === false || $json === '') return [];
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function writeMetricStore(array $data): void {
    $file = getMetricStorePath();
    if (!is_dir(LOGS_DIR) || !is_writable(LOGS_DIR)) return;
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function pushHistory(array $history, float $value, int $max = 30): array {
    $history[] = round($value, 2);
    if (count($history) > $max) $history = array_slice($history, -$max);
    return $history;
}

function buildAreaPath(array $history, float $maxValue, int $height = 220): string {
    $history = array_values(array_map('floatval', $history));
    if (!$history) return 'M0,'.$height.' L100,'.$height.' Z';
    $count = count($history);
    $maxValue = max($maxValue, 1.0);
    $path = 'M0,'.$height.' ';
    foreach ($history as $i => $val) {
        $x = ($count > 1) ? ($i * (100 / ($count - 1))) : 0;
        $y = $height - (($val / $maxValue) * ($height - 10));
        if ($y < 0) $y = 0;
        if ($y > $height) $y = $height;
        $path .= 'L'.round($x, 2).','.round($y, 2).' ';
    }
    $path .= 'L100,'.$height.' Z';
    return $path;
}

function getTrafficMetrics(): array {
    $now = time();
    $net = getNetworkStats();
    $store = readMetricStore();

    $prevTs = (int)($store['net_prev_ts'] ?? 0);
    $prevRx = (float)($store['net_prev_rx'] ?? 0);
    $prevTx = (float)($store['net_prev_tx'] ?? 0);
    $dt = max($now - $prevTs, 1);

    $rxRate = ($prevTs > 0) ? max(($net['rx'] - $prevRx) / $dt, 0) : 0.0;
    $txRate = ($prevTs > 0) ? max(($net['tx'] - $prevTx) / $dt, 0) : 0.0;

    $histDown = is_array($store['net_hist_down'] ?? null) ? $store['net_hist_down'] : [];
    $histUp = is_array($store['net_hist_up'] ?? null) ? $store['net_hist_up'] : [];
    $histDown = pushHistory($histDown, $rxRate);
    $histUp = pushHistory($histUp, $txRate);

    $store['net_prev_ts'] = $now;
    $store['net_prev_rx'] = $net['rx'];
    $store['net_prev_tx'] = $net['tx'];
    $store['net_hist_down'] = $histDown;
    $store['net_hist_up'] = $histUp;
    writeMetricStore($store);

    return [
        'rx_total' => (float)$net['rx'],
        'tx_total' => (float)$net['tx'],
        'rx_rate' => $rxRate,
        'tx_rate' => $txRate,
        'hist_down' => $histDown,
        'hist_up' => $histUp,
    ];
}

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
        $readSectors = (float)($parts[5] ?? 0);
        $writeSectors = (float)($parts[9] ?? 0);
        $read += $readSectors * 512;
        $write += $writeSectors * 512;
    }
    return ['read' => $read, 'write' => $write, 'ok' => true];
}

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

    $store = readMetricStore();
    $prevTs = (int)($store['disk_prev_ts'] ?? 0);
    $prevRead = (float)($store['disk_prev_read'] ?? 0);
    $prevWrite = (float)($store['disk_prev_write'] ?? 0);
    $dt = max($now - $prevTs, 1);

    $readRate = ($prevTs > 0) ? max(($totals['read'] - $prevRead) / $dt, 0) : 0.0;
    $writeRate = ($prevTs > 0) ? max(($totals['write'] - $prevWrite) / $dt, 0) : 0.0;

    $histRead = is_array($store['disk_hist_read'] ?? null) ? $store['disk_hist_read'] : [];
    $histWrite = is_array($store['disk_hist_write'] ?? null) ? $store['disk_hist_write'] : [];
    $histRead = pushHistory($histRead, $readRate);
    $histWrite = pushHistory($histWrite, $writeRate);

    $store['disk_prev_ts'] = $now;
    $store['disk_prev_read'] = $totals['read'];
    $store['disk_prev_write'] = $totals['write'];
    $store['disk_hist_read'] = $histRead;
    $store['disk_hist_write'] = $histWrite;
    writeMetricStore($store);

    return [
        'read_rate' => $readRate,
        'write_rate' => $writeRate,
        'hist_read' => $histRead,
        'hist_write' => $histWrite,
    ];
}

function getUptimeInfo(): string {
    if (!str_starts_with(strtoupper(PHP_OS), 'WIN')) {
        $data = getFileSafe('/proc/uptime');
        if ($data !== false) {
            $sec = (int)floatval(explode(' ', trim($data))[0] ?? 0);
            if ($sec > 0) return formatUptime($sec);
        }
    } elseif (function_exists('exec')) {
        $out = [];
        exec("powershell -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command \"(Get-CimInstance Win32_OperatingSystem).LastBootUpTime.ToString('yyyy-MM-dd HH:mm:ss')\"", $out);
        $boot = trim((string)($out[0] ?? ''));
        $bootTs = $boot !== '' ? strtotime($boot) : false;
        if ($bootTs !== false) {
            $sec = max(time() - $bootTs, 0);
            return formatUptime($sec);
        }
    }
    return 'N/A';
}

function formatUptime(int $sec): string {
    $days = intdiv($sec, 86400);
    $hours = intdiv($sec % 86400, 3600);
    $mins = intdiv($sec % 3600, 60);
    return $days.'d '.$hours.'h '.$mins.'m';
}

function getCpuDetails(): array {
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
            preg_match_all('/^physical id\s*:\s*(\d+)/m', $cpuinfo, $physIds);
            preg_match_all('/^core id\s*:\s*(\d+)/m', $cpuinfo, $coreIds);
            if (!empty($physIds[1]) && !empty($coreIds[1]) && count($physIds[1]) === count($coreIds[1])) {
                $pairs = [];
                foreach ($physIds[1] as $k => $pid) {
                    $pairs[] = $pid.'-'.$coreIds[1][$k];
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

function getStatusHtml(?bool $state): string {
    if ($state === null) return '<span style="color:#9ca3af">N/A</span>';
    return $state ? '<span style="color:#21c45d">On</span>' : '<span style="color:#ef4444">Off</span>';
}

function getDbHealth(object $db): array {
    $connections = 'N/A';
    $slow = 'N/A';
    try {
        $res = $db->getSqlQuery("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
        if ($res) {
            $row = $db->getSqlRow($res);
            if (is_array($row) && isset($row['Value'])) $connections = (string)$row['Value'];
        }
        $res = $db->getSqlQuery("SHOW GLOBAL STATUS LIKE 'Slow_queries'");
        if ($res) {
            $row = $db->getSqlRow($res);
            if (is_array($row) && isset($row['Value'])) $slow = (string)$row['Value'];
        }
    } catch (Throwable) {
        // Keep N/A when permission for global status is restricted.
    }
    return ['connections' => $connections, 'slow' => $slow];
}

function getUsageColor(float $pct): string {
    if ($pct > 95) return '#ef4444';
    if ($pct > 75) return '#f97316';
    if ($pct > 50) return '#3b82f6';
    return '#22c55e';
}

function monitor(): void {
    global $db, $conf, $afile;
    setHead();
    
    $cont = navi();
    $cont .= setTemplateBasic('open');

    // CPU Stats
    [$cpu_p, $cpu_info] = getCpuLoad();
    $cpu = getCpuDetails();
    
    // Memory Stats
    $mem = getMemoryInfo();
    
    // Disk Stats
    $disk_total = disk_total_space('.');
    $disk_free = disk_free_space('.');
    $disk_used = max($disk_total - $disk_free, 0);
    $diskpct = ($disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0;
    
    // Network stats (totals + real-time deltas + history)
    $net = getTrafficMetrics();
    $netHistMax = max(array_merge([1.0], $net['hist_up'], $net['hist_down']));
    $path_up = buildAreaPath($net['hist_up'], $netHistMax);
    $path_down = buildAreaPath($net['hist_down'], $netHistMax);

    // Disk I/O deltas
    $diskio = getDiskIoMetrics();

    // Usage Stats
    $userson = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session'));
    $cntfile = $db->getSqlRowCount($db->getSqlQuery('SELECT lid FROM '.PREFIX_DB."_files WHERE status != '0'"));
    $cntnews = $db->getSqlRowCount($db->getSqlQuery('SELECT sid FROM '.PREFIX_DB."_news WHERE status != '0'"));

    // DB Size
    $dbsize = 0;
    $dbtabs = 0;
    $dbname = preg_replace('#[^a-zA-Z0-9_]#', '', (string)($conf['db']['name'] ?? ''));
    if ($dbname !== '') {
        $db_result = $db->getSqlQuery('SHOW TABLE STATUS FROM `'.$dbname.'`');
        while ($row = $db->getSqlRow($db_result)) {
            $dbsize += $row['Data_length'] + $row['Index_length'];
            $dbtabs++;
        }
    }

    // Server Detect
    $servsw = $_SERVER['SERVER_SOFTWARE'] ?? getenv('SERVER_SOFTWARE') ?: '';
    $servname = 'Web Server';
    if (stripos($servsw, 'apache') !== false) $servname = 'Apache';
    elseif (stripos($servsw, 'nginx') !== false) $servname = 'Nginx';
    elseif (stripos($servsw, 'litespeed') !== false) $servname = 'LiteSpeed';
    $servver = 'N/A';
    if (preg_match('#/(\\d+(?:\\.\\d+)+)#', (string)$servsw, $vm)) $servver = $vm[1];
    $nginxver = getNginxVersion();
    $firewall = getFirewallInfo();

    // Detailed Info Logic
    $gd = function_exists('gd_info') ? gd_info() : ['GD Version' => 'N/A'];
    $statusOn = static fn(bool $v): string => getStatusHtml($v);

    // UI Dashboards Math
    $dash = 2 * M_PI * 45;
    $off_load = $dash - ($dash * $cpu_p / 100);
    $off_r = $dash - ($dash * $mem['percent'] / 100);
    $off_d = $dash - ($dash * $diskpct / 100);
    $cpucolor = getUsageColor((float)$cpu_p);
    $ramcolor = getUsageColor((float)$mem['percent']);
    $diskcolor = getUsageColor((float)$diskpct);

    $uptime = getUptimeInfo();
    $dbhealth = getDbHealth($db);
    $diskwarn = ($disk_total > 0 && (($disk_free / $disk_total) * 100) < 10)
        ? '<span style="color:#ef4444">Low free space</span>'
        : '<span style="color:#21c45d">Normal</span>';
    $quick = '<a href="'.$afile.'.php?name=security" class="sl_but">Security Logs</a> '
        .'<a href="'.$afile.'.php?name=security&amp;op=conf" class="sl_but">Security Settings</a> '
        .'<a href="'.$afile.'.php?name=database" class="sl_but">Database</a>';

    $cont .= setTemplateBasic('basic-monitor', [
        '{%dash%}'      => $dash,
        '{%off%}'       => $off_load,
        '{%load_0%}'    => $cpu_p,
        '{%cpucolor%}'  => $cpucolor,
        '{%cpuuse%}'    => $cpu_p,
        '{%cpucores%}'  => $cpu['logical'],
        '{%cpuphys%}'   => $cpu['physical'],
        '{%cpufreq%}'   => $cpu['freq'],
        '{%loadstr%}'   => $cpu_info,
        '{%dash_r%}'    => $dash,
        '{%off_r%}'     => $off_r,
        '{%ramcolor%}'  => $ramcolor,
        '{%ram_p%}'     => $mem['percent'],
        '{%ramumb%}'    => filterSize($mem['used']),
        '{%ramtmb%}'    => filterSize($mem['total']),
        '{%ramavailmb%}' => filterSize($mem['free']),
        '{%dash_d%}'    => $dash,
        '{%off_d%}'     => $off_d,
        '{%diskcolor%}' => $diskcolor,
        '{%disk_p%}'    => $diskpct,
        '{%diskused%}'  => filterSize($disk_used),
        '{%disktot%}'   => filterSize($disk_total),
        '{%cntnews%}'   => $cntnews,
        '{%cntfile%}'   => $cntfile,
        '{%dbtabs%}'    => $dbtabs,
        '{%userson%}'   => $userson,
        '{%servname%}'  => $servname,
        '{%servver%}'   => $servver,
        '{%nginxver%}'  => $nginxver,
        '{%mysql%}'     => db_version(),
        '{%phpver%}'    => PHP_VERSION,
        '{%path_up%}'   => $path_up,
        '{%path_down%}' => $path_down,
        '{%nettx%}'     => filterSize($net['tx_total']),
        '{%netrx%}'     => filterSize($net['rx_total']),
        '{%nettxrate%}' => filterSize($net['tx_rate']).'/s',
        '{%netrxrate%}' => filterSize($net['rx_rate']).'/s',
        '{%opmode%}'    => $statusOn(!($conf['close'] ?? 0)),
        '{%statact%}'   => $statusOn(is_active('stat')),
        '{%referact%}'  => $statusOn(is_active('referers')),
        '{%newslet%}'   => $statusOn((bool)($conf['newsletter'] ?? 0)),
        '{%cache%}'     => $statusOn((bool)($conf['cache'] ?? 0)),
        '{%rewrite%}'   => $statusOn((bool)($conf['rewrite'] ?? 0)),
        '{%cmsver%}'    => $conf['version'] ?? '',
        '{%osname%}'    => php_uname('s'),
        '{%servfull%}'  => $servsw,
        '{%fwname%}'    => $firewall['name'],
        '{%fwstate%}'   => $firewall['state'],
        '{%fwclass%}'   => ($firewall['on'] === null) ? 'sw-status-gray' : ($firewall['on'] ? 'sw-status-green' : 'sw-status-red'),
        '{%phpsapi%}'   => php_sapi_name(),
        '{%gdver%}'     => $gd['GD Version'] ?? 'N/A',
        '{%dbszfmt%}'   => filterSize($dbsize),
        '{%postmax%}'   => ini_get('post_max_size'),
        '{%fileup%}'    => $statusOn((bool)ini_get('file_uploads')),
        '{%upmax%}'     => ini_get('upload_max_filesize'),
        '{%memlim%}'    => ini_get('memory_limit'),
        '{%maxvars%}'   => ini_get('max_input_vars'),
        '{%maxtime%}'   => ini_get('max_execution_time'),
        '{%gzipld%}'    => $statusOn(extension_loaded('zlib')),
        '{%zipld%}'     => $statusOn(extension_loaded('zip')),
        '{%phptime%}'   => date('H:i:s'),
        '{%diskfree%}'  => filterSize($disk_free),
        '{%uptime%}'    => $uptime,
        '{%dbconn%}'    => $dbhealth['connections'],
        '{%dbslow%}'    => $dbhealth['slow'],
        '{%dskread%}'   => ($diskio['read_rate'] === null) ? 'N/A' : filterSize((int)$diskio['read_rate']).'/s',
        '{%dskwrite%}'  => ($diskio['write_rate'] === null) ? 'N/A' : filterSize((int)$diskio['write_rate']).'/s',
        '{%diskwarn%}'  => $diskwarn,
        '{%quicklinks%}' => $quick,
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    
    setFoot();
}

function info(): void {
    setHead();
    echo navi(0, 1, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: monitor(); break;
    case 'info': info(); break;
}
