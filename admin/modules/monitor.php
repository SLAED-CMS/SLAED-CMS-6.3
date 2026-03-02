<?php
# Author: Eduard Laas
# Copyright (c) 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function getMonitorTabs(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    $ops = ['name=monitor', 'name=monitor&op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs('System Monitor', 'statistic.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

function isWindows(): bool {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function getServerSoftware(): string {
    return (string)($_SERVER['SERVER_SOFTWARE'] ?? getenv('SERVER_SOFTWARE') ?: '');
}

function getServerLoadData(): array {
    $load = [0, 0, 0];
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
    }
    return $load;
}

function isProcReadable(string $path): bool {
    if (strpos($path, '/proc/') !== 0) return false;
    $base = (string)ini_get('open_basedir');
    if ($base !== '') {
        $allow = false;
        foreach (explode(PATH_SEPARATOR, $base) as $root) {
            $root = rtrim(trim($root), '/');
            if ($root !== '' && ($path === $root || strpos($path, $root.'/') === 0)) {
                $allow = true;
                break;
            }
        }
        if (!$allow) return false;
    }
    return is_readable($path);
}

function getCommandOutput(string $command): array {
    if (!function_exists('exec')) {
        return [];
    }
    // Defense-in-depth: this helper must execute only static internal commands.
    if (preg_match('/[;&|`><\r\n]/', $command)) {
        return [];
    }
    $output = [];
    exec($command, $output);
    return $output;
}

function getMemoryInfo(): array {
    $free = 0;
    $total = 0;

    if (isWindows()) {
        $cmd = 'wmic ComputerSystem get TotalPhysicalMemory /Value';
        $outtot = getCommandOutput($cmd);
        foreach ($outtot as $line) {
            if (strpos($line, 'TotalPhysicalMemory') !== false) {
                $parts = explode('=', $line);
                $total = intval($parts[1]);
                break;
            }
        }

        $cmd = 'wmic OS get FreePhysicalMemory /Value';
        $outfree = getCommandOutput($cmd);
        foreach ($outfree as $line) {
            if (strpos($line, 'FreePhysicalMemory') !== false) {
                $parts = explode('=', $line);
                $free = intval($parts[1]) * 1024;
                break;
            }
        }
    } else {
        $data = isProcReadable('/proc/meminfo') ? file_get_contents('/proc/meminfo') : false;
        if ($data) {
            $data = explode("\n", $data);
            $meminfo = [];
            foreach ($data as $line) {
                if (strpos($line, ':') === false) continue;
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

    $used = $total - $free;
    return [
        'total' => $total,
        'free' => $free,
        'used' => $used,
        'percent' => ($total > 0) ? round(($used / $total) * 100, 1) : 0,
    ];
}

function getMemorySafeLimit(): int {
    $memlim = ini_get('memory_limit');
    if ($memlim === false || $memlim === '' || $memlim === '-1') {
        return max(memory_get_usage(true) * 2, 134217728);
    }
    if (preg_match('/^(\d+)(.)$/', $memlim, $matches)) {
        if ($matches[2] == 'M') {
            $memlim = $matches[1] * 1024 * 1024;
        } else if ($matches[2] == 'K') {
            $memlim = $matches[1] * 1024;
        } else if ($matches[2] == 'G') {
            $memlim = $matches[1] * 1024 * 1024 * 1024;
        }
    }
    return intval($memlim);
}

function getFormattedBytes(float|int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision).' '.$units[$pow];
}

function getNetworkStats(): array {
    $rx = 0;
    $tx = 0;
    if (isWindows()) {
        $output = getCommandOutput('netstat -e');
        foreach ($output as $line) {
            if (stripos($line, 'Bytes') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                $rx = $parts[1] ?? 0;
                $tx = $parts[2] ?? 0;
                break;
            }
        }
    } else {
        $data = isProcReadable('/proc/net/dev') ? file_get_contents('/proc/net/dev') : false;
        if ($data) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (strpos($line, ':') !== false) {
                    $parts = preg_split('/\s+/', trim(substr($line, strpos($line, ':') + 1)));
                    $rx += $parts[0] ?? 0;
                    $tx += $parts[8] ?? 0;
                }
            }
        }
    }
    return ['rx' => $rx, 'tx' => $tx];
}

function isModuleActive(string $mod): bool {
    global $conf;
    if (function_exists('is_active')) return is_active($mod);
    $info = $conf['modules'][$mod] ?? [];
    return !empty($info['active']);
}

function getMonitor(): void {
    global $db, $conf;
    head();
    $cont = getMonitorTabs(0, 0, 0, 0);
    $cont .= setTemplateBasic('open');
    

    // Stats Gathering
    $load = getServerLoadData();
    $mem = getMemoryInfo();
    $disk_total = disk_total_space('.');
    $disk_free = disk_free_space('.');
    $disk_used = $disk_total - $disk_free;
    $diskpct = ($disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0;
    $net = getNetworkStats();

    $userson = $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_session'));

    // DB Stats
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

    $servsw = getServerSoftware();
    $servname = 'Web Server';
    if (stripos($servsw, 'apache') !== false) $servname = 'Apache';
    elseif (stripos($servsw, 'nginx') !== false) $servname = 'Nginx';
    elseif (stripos($servsw, 'litespeed') !== false) $servname = 'LiteSpeed';

    // Detailed Info Logic
    $gd = function_exists('gd_info') ? gd_info() : ['GD Version' => 'N/A'];
    $verq = $db->getSqlQuery('SELECT VERSION()');
    $verrow = $db->getSqlRow($verq);
    $mysql = $verrow[0];

    $status_on = '<span style="color:#21c45d">On</span>';
    $status_off = '<span style="color:#ef4444">Off</span>';

    // Counts for Overview Strip
    $cntfile = $db->getSqlRowCount($db->getSqlQuery('SELECT lid FROM '.PREFIX_DB.'_files WHERE status != \'0\''));
    $cntnews = $db->getSqlRowCount($db->getSqlQuery('SELECT sid FROM '.PREFIX_DB.'_news WHERE status != \'0\''));

    // Calculate dashboard metrics
    $load_p = min($load[0] * 10, 100);
    $dash = 2 * pi() * 45;
    $off_load = $dash - ($dash * $load_p / 100);
    $ram_p = $mem['percent'];
    $off_r = $dash - ($dash * $ram_p / 100);
    $ram_used_mb = round($mem['used'] / 1024 / 1024);
    $ram_total_mb = round($mem['total'] / 1024 / 1024);
    $disk_p = $diskpct;
    $dash_d = 2 * pi() * 45;
    $off_d = $dash_d - ($dash_d * $disk_p / 100);
    $disk_used_fmt = getFormattedBytes($disk_used);
    $disk_total_fmt = getFormattedBytes($disk_total);
    $disk_free_fmt = getFormattedBytes($disk_free);
    $net_tx = getFormattedBytes($net['tx']);
    $net_rx = getFormattedBytes($net['rx']);
    $db_size_fmt = getFormattedBytes($dbsize);

    // Additional variables for dashboard
    $loadstr = implode(' / ', $load);
    $phpver = PHP_VERSION;
    $phpsapi = php_sapi_name();
    $osname = php_uname('s');
    $servfull = getServerSoftware();
    $gdver = (string)($gd['GD Version'] ?? 'N/A');
    $post_max = ini_get('post_max_size');
    $file_up = ini_get('file_uploads') ? $status_on : $status_off;
    $up_max = ini_get('upload_max_filesize');
    $mem_lim = ini_get('memory_limit');
    $max_vars = ini_get('max_input_vars');
    $max_time = ini_get('max_execution_time');
    $gzip_ld = extension_loaded('zlib') ? $status_on : $status_off;
    $zip_ld = extension_loaded('zip') ? $status_on : $status_off;
    $php_time = date('H:i:s');
    $op_mode = !($conf['close'] ?? 0) ? $status_on : $status_off;
    $stat_act = isModuleActive('stat') ? $status_on : $status_off;
    $refer_act = isModuleActive('referers') ? $status_on : $status_off;
    $newslet = ($conf['newsletter'] ?? 0) ? $status_on : $status_off;
    $cache = ($conf['cache'] ?? 0) ? $status_on : $status_off;
    $rewrite = ($conf['rewrite'] ?? 0) ? $status_on : $status_off;
    $cms_ver = (string)($conf['version'] ?? '');

    // SVG paths for traffic chart
    $path_up = 'M0,220 ';
    $path_down = 'M0,220 ';
    for ($i = 0; $i <= 20; $i++) {
        $x = $i * (100 / 20).'%';
        $y_u = 220 - rand(10, 80);
        $y_d = 220 - rand(10, 80);
        if ($i == 20) {
            $y_u = 220;
            $y_d = 220;
        }
        $path_up .= 'L'.$x.','.$y_u.' ';
        $path_down .= 'L'.$x.','.$y_d.' ';
    }
    $path_up .= 'Z';
    $path_down .= 'Z';

    $cont .= setTemplateBasic('basic-monitor', [
        '{%dash%}' => $dash,
        '{%off%}' => $off_load,
        '{%load_0%}' => $load[0],
        '{%loadstr%}' => $loadstr,
        '{%off_r%}' => $off_r,
        '{%ram_p%}' => $ram_p,
        '{%ramumb%}' => $ram_used_mb,
        '{%ramtmb%}' => $ram_total_mb,
        '{%dash_d%}' => $dash_d,
        '{%off_d%}' => $off_d,
        '{%disk_p%}' => $disk_p,
        '{%diskused%}' => $disk_used_fmt,
        '{%disktot%}' => $disk_total_fmt,
        '{%cntnews%}' => $cntnews,
        '{%cntfile%}' => $cntfile,
        '{%dbtabs%}' => $dbtabs,
        '{%userson%}' => $userson,
        '{%servname%}' => $servname,
        '{%mysql%}' => $mysql,
        '{%phpver%}' => $phpver,
        '{%path_up%}' => $path_up,
        '{%path_down%}' => $path_down,
        '{%nettx%}' => $net_tx,
        '{%netrx%}' => $net_rx,
        '{%opmode%}' => $op_mode,
        '{%statact%}' => $stat_act,
        '{%referact%}' => $refer_act,
        '{%newslet%}' => $newslet,
        '{%cache%}' => $cache,
        '{%rewrite%}' => $rewrite,
        '{%cmsver%}' => $cms_ver,
        '{%osname%}' => $osname,
        '{%servfull%}' => $servfull,
        '{%phpsapi%}' => $phpsapi,
        '{%gdver%}' => $gdver,
        '{%dbszfmt%}' => $db_size_fmt,
        '{%postmax%}' => $post_max,
        '{%fileup%}' => $file_up,
        '{%upmax%}' => $up_max,
        '{%memlim%}' => $mem_lim,
        '{%maxvars%}' => $max_vars,
        '{%maxtime%}' => $max_time,
        '{%gzipld%}' => $gzip_ld,
        '{%zipld%}' => $zip_ld,
        '{%phptime%}' => $php_time,
        '{%diskfree%}' => $disk_free_fmt,
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function getInfo(): void {
    head();
    echo getMonitorTabs(0, 1, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    foot();
}

switch ($op) {
    default: getMonitor(); break;
    case 'info': getInfo(); break;
}


