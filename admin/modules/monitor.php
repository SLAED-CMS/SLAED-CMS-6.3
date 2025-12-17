<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $extra = ''): string {
    $ops = ['name=monitor', 'name=monitor&op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs('System Monitor', 'stat.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $extra);
}

function get_server_load_data() {
    $load = [0, 0, 0];
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
    }
    return $load;
}

function get_memory_info() {
    $free = 0;
    $total = 0;

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $cmd = 'wmic ComputerSystem get TotalPhysicalMemory /Value';
        @exec($cmd, $outtot);
        foreach ($outtot as $line) {
            if (strpos($line, 'TotalPhysicalMemory') !== false) {
                $parts = explode('=', $line);
                $total = intval($parts[1]);
                break;
            }
        }

        $cmd = 'wmic OS get FreePhysicalMemory /Value';
        @exec($cmd, $outfree);
        foreach ($outfree as $line) {
            if (strpos($line, 'FreePhysicalMemory') !== false) {
                $parts = explode('=', $line);
                $free = intval($parts[1]) * 1024;
                break;
            }
        }
    } else {
        $data = @file_get_contents('/proc/meminfo');
        if ($data) {
            $data = explode("\n", $data);
            $meminfo = [];
            foreach ($data as $line) {
                list($key, $val) = explode(':', $line);
                $meminfo[trim($key)] = trim($val);
            }
            $total = intval($meminfo['MemTotal'] ?? 0) * 1024;
            $free = intval($meminfo['MemAvailable'] ?? 0) * 1024;
        }
    }

    if ($total <= 0) {
        $total = memory_get_safe_limit();
        $free = $total - memory_get_usage(true);
    }

    $used = $total - $free;
    return [
        'total' => $total,
        'free' => $free,
        'used' => $used,
        'percent' => ($total > 0) ? round(($used / $total) * 100, 1) : 0
    ];
}

function memory_get_safe_limit() {
    $memlim = ini_get('memory_limit');
    if (preg_match('/^(\d+)(.)$/', $memlim, $matches)) {
        if ($matches[2] == 'M') {
            $memlim = $matches[1] * 1024 * 1024;
        } else if ($matches[2] == 'K') {
            $memlim = $matches[1] * 1024;
        } else if ($matches[2] == 'G') {
            $memlim = $matches[1] * 1024 * 1024 * 1024;
        }
    }
    return $memlim;
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision).' '.$units[$pow];
}

function get_network_stats() {
    $rx = 0;
    $tx = 0;
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        @exec('netstat -e', $output);
        foreach ($output as $line) {
            if (stripos($line, 'Bytes') !== false) {
                $parts = preg_split('/\s+/', trim($line));
                $rx = $parts[1] ?? 0;
                $tx = $parts[2] ?? 0;
                break;
            }
        }
    } else {
        $data = @file_get_contents('/proc/net/dev');
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

function is_mod_active($mod) {
    global $prefix, $db;
    if (function_exists('is_active')) return is_active($mod);
    $row = $db->sql_fetchrow($db->sql_query('SELECT active FROM '.$prefix.'_modules WHERE title = :title', ['title' => $mod]));
    return ($row && $row[0] == 1);
}

function monitor(): void {
    global $prefix, $db, $conf, $confdb;
    head();
    $cont = navi(0, 0, 0, 0);
    $cont .= setTemplateBasic('open');
    

    // Stats Gathering
    $load = get_server_load_data();
    $mem = get_memory_info();
    $disk_total = disk_total_space('.');
    $disk_free = disk_free_space('.');
    $disk_used = $disk_total - $disk_free;
    $diskpct = ($disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0;
    $net = get_network_stats();

    $uptime = 'N/A';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $upt = @file_get_contents('/proc/uptime');
        if ($upt) {
            $upt = explode(' ', $upt)[0];
            $days = floor($upt / 86400);
            $hours = floor(($upt % 86400) / 3600);
            $mins = floor(($upt % 3600) / 60);
            $uptime = "$days d, $hours:$mins";
        }
    } else {
        $uptime = 'Windows';
    }

    $userson = $db->sql_numrows($db->sql_query('SELECT id FROM '.$prefix.'_session'));

    // DB Stats
    $dbsize = 0;
    $dbtabs = 0;
    $dbresult = $db->sql_query('SHOW TABLE STATUS FROM '.$confdb['name']);
    while ($row = $db->sql_fetchrow($dbresult)) {
        $dbsize += $row['Data_length'] + $row['Index_length'];
        $dbtabs++;
    }

    $servsw = $_SERVER['SERVER_SOFTWARE'];
    $servname = 'Web Server';
    if (stripos($servsw, 'apache') !== false) $servname = 'Apache';
    elseif (stripos($servsw, 'nginx') !== false) $servname = 'Nginx';
    elseif (stripos($servsw, 'litespeed') !== false) $servname = 'LiteSpeed';

    // Detailed Info Logic
    $gd = gd_info();
    $verq = $db->sql_query('SELECT VERSION()');
    $verrow = $db->sql_fetchrow($verq);
    $mysql = $verrow[0];

    $on = '<span style="color:#21c45d">On</span>';
    $off = '<span style="color:#ef4444">Off</span>';

    // Counts for Overview Strip
    $cntfile = $db->sql_numrows($db->sql_query('SELECT lid FROM '.$prefix.'_files WHERE status != \'0\''));
    $cntnews = $db->sql_numrows($db->sql_query('SELECT sid FROM '.$prefix.'_news WHERE status != \'0\''));

    // Calculate dashboard metrics
    $loadP = min($load[0] * 10, 100);
    $dash = 2 * pi() * 45;
    $off = $dash - ($dash * $loadP / 100);
    $ramP = $mem['percent'];
    $offR = $dash - ($dash * $ramP / 100);
    $ramumb = round($mem['used'] / 1024 / 1024);
    $ramtmb = round($mem['total'] / 1024 / 1024);
    $diskP = $diskpct;
    $dashD = 2 * pi() * 45;
    $offD = $dashD - ($dashD * $diskP / 100);
    $diskused = format_bytes($disk_used);
    $disktot = format_bytes($disk_total);
    $diskfree = format_bytes($disk_free);
    $nettx = format_bytes($net['tx']);
    $netrx = format_bytes($net['rx']);
    $dbszfmt = format_bytes($dbsize);

    // Additional variables for dashboard
    $loadstr = implode(' / ', $load);
    $phpver = PHP_VERSION;
    $phpsapi = php_sapi_name();
    $osname = php_uname('s');
    $servfull = $_SERVER['SERVER_SOFTWARE'];
    $gdver = $gd['GD Version'];
    $postmax = ini_get('post_max_size');
    $fileup = ini_get('file_uploads') ? $on : $off;
    $upmax = ini_get('upload_max_filesize');
    $memlim = ini_get('memory_limit');
    $maxvars = ini_get('max_input_vars');
    $maxtime = ini_get('max_execution_time');
    $gzipld = (extension_loaded('zlib')) ? $on : $off;
    $zipld = (extension_loaded('zip')) ? $on : $off;
    $phptime = date('H:i:s');
    $opmode = (!$conf['close']) ? $on : $off;
    $statact = (is_mod_active('stat')) ? $on : $off;
    $referact = (is_mod_active('referers')) ? $on : $off;
    $newslet = ($conf['newsletter']) ? $on : $off;
    $cache = ($conf['cache']) ? $on : $off;
    $rewrite = ($conf['rewrite']) ? $on : $off;
    $cmsver = $conf['version'];

    // SVG paths for traffic chart
    $pathUp = 'M0,220 ';
    $pathDown = 'M0,220 ';
    for ($i = 0; $i <= 20; $i++) {
        $x = $i * (100 / 20).'%';
        $yU = 220 - rand(10, 80);
        $yD = 220 - rand(10, 80);
        if ($i == 20) {
            $yU = 220;
            $yD = 220;
        }
        $pathUp .= 'L'.$x.','.$yU.' ';
        $pathDown .= 'L'.$x.','.$yD.' ';
    }
    $pathUp .= 'Z';
    $pathDown .= 'Z';

    $cont .= setTemplateBasic('basic-monitor', [
        '{%dash%}' => $dash,
        '{%off%}' => $off,
        '{%load_0%}' => $load[0],
        '{%loadstr%}' => $loadstr,
        '{%offR%}' => $offR,
        '{%ramP%}' => $ramP,
        '{%ramumb%}' => $ramumb,
        '{%ramtmb%}' => $ramtmb,
        '{%dashD%}' => $dashD,
        '{%offD%}' => $offD,
        '{%diskP%}' => $diskP,
        '{%diskused%}' => $diskused,
        '{%disktot%}' => $disktot,
        '{%cntnews%}' => $cntnews,
        '{%cntfile%}' => $cntfile,
        '{%dbtabs%}' => $dbtabs,
        '{%userson%}' => $userson,
        '{%servname%}' => $servname,
        '{%mysql%}' => $mysql,
        '{%phpver%}' => $phpver,
        '{%pathUp%}' => $pathUp,
        '{%pathDown%}' => $pathDown,
        '{%nettx%}' => $nettx,
        '{%netrx%}' => $netrx,
        '{%opmode%}' => $opmode,
        '{%statact%}' => $statact,
        '{%referact%}' => $referact,
        '{%newslet%}' => $newslet,
        '{%cache%}' => $cache,
        '{%rewrite%}' => $rewrite,
        '{%cmsver%}' => $cmsver,
        '{%osname%}' => $osname,
        '{%servfull%}' => $servfull,
        '{%phpsapi%}' => $phpsapi,
        '{%gdver%}' => $gdver,
        '{%dbszfmt%}' => $dbszfmt,
        '{%postmax%}' => $postmax,
        '{%fileup%}' => $fileup,
        '{%upmax%}' => $upmax,
        '{%memlim%}' => $memlim,
        '{%maxvars%}' => $maxvars,
        '{%maxtime%}' => $maxtime,
        '{%gzipld%}' => $gzipld,
        '{%zipld%}' => $zipld,
        '{%phptime%}' => $phptime,
        '{%diskfree%}' => $diskfree
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function info(): void {
    head();
    echo navi(0, 1, 0, 0).'<div id="repadm_info">'.adm_info(1, 0, 'monitor').'</div>';
    foot();
}

switch ($op) {
    default: monitor(); break;
    case 'info': info(); break;
}
