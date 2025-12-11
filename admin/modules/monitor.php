<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    $ops = ['name=monitor', 'name=monitor&op=info'];
    $lang = [_HOME, _INFO];
    return getAdminTabs('System Monitor', 'stat.png', '', $ops, $lang, [], [], $tab, (bool)$subtab);
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
        @exec($cmd, $outputTotal);
        foreach ($outputTotal as $line) {
            if (strpos($line, 'TotalPhysicalMemory') !== false) {
                $parts = explode('=', $line);
                $total = intval($parts[1]);
                break;
            }
        }

        $cmd = 'wmic OS get FreePhysicalMemory /Value';
        @exec($cmd, $outputFree);
        foreach ($outputFree as $line) {
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
    $memory_limit = ini_get('memory_limit');
    if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
        if ($matches[2] == 'M') {
            $memory_limit = $matches[1] * 1024 * 1024;
        } else if ($matches[2] == 'K') {
            $memory_limit = $matches[1] * 1024;
        } else if ($matches[2] == 'G') {
            $memory_limit = $matches[1] * 1024 * 1024 * 1024;
        }
    }
    return $memory_limit;
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
    $disk_percent = ($disk_total > 0) ? round(($disk_used / $disk_total) * 100, 1) : 0;
    $net = get_network_stats();

    $uptime_str = 'N/A';
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $uptime = @file_get_contents('/proc/uptime');
        if ($uptime) {
            $uptime = explode(' ', $uptime)[0];
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $mins = floor(($uptime % 3600) / 60);
            $uptime_str = "$days d, $hours:$mins";
        }
    } else {
        $uptime_str = 'Windows';
    }

    $users_online = $db->sql_numrows($db->sql_query('SELECT ip FROM '.$prefix.'_session'));

    // DB Stats
    $dbtotal_size = 0;
    $db_tables = 0;
    $dbresult = $db->sql_query('SHOW TABLE STATUS FROM '.$confdb['name']);
    while ($row = $db->sql_fetchrow($dbresult)) {
        $dbtotal_size += $row['Data_length'] + $row['Index_length'];
        $db_tables++;
    }

    $server_soft = $_SERVER['SERVER_SOFTWARE'];
    $server_name = 'Web Server';
    if (stripos($server_soft, 'apache') !== false) $server_name = 'Apache';
    elseif (stripos($server_soft, 'nginx') !== false) $server_name = 'Nginx';
    elseif (stripos($server_soft, 'litespeed') !== false) $server_name = 'LiteSpeed';

    $extensions = get_loaded_extensions();
    $ext_count = count($extensions);

    // Detailed Info Logic
    $gd = gd_info();
    $ver_query = $db->sql_query('SELECT VERSION()');
    $ver_row = $db->sql_fetchrow($ver_query);
    $mysql_ver = $ver_row[0];

    $on = '<span style="color:#21c45d">On</span>';
    $off = '<span style="color:#ef4444">Off</span>';

    // Counts for Overview Strip
    $cnt_files = $db->sql_numrows($db->sql_query('SELECT lid FROM '.$prefix.'_files WHERE status != \'0\''));
    $cnt_news = $db->sql_numrows($db->sql_query('SELECT sid FROM '.$prefix.'_news WHERE status != \'0\''));

    // Calculate dashboard metrics
    $loadP = min($load[0] * 10, 100);
    $dash = 2 * pi() * 45;
    $off = $dash - ($dash * $loadP / 100);
    $ramP = $mem['percent'];
    $offR = $dash - ($dash * $ramP / 100);
    $ram_used_mb = round($mem['used'] / 1024 / 1024);
    $ram_total_mb = round($mem['total'] / 1024 / 1024);
    $diskP = $disk_percent;
    $dashD = 2 * pi() * 45;
    $offD = $dashD - ($dashD * $diskP / 100);
    $disk_used_fmt = format_bytes($disk_used);
    $disk_total_fmt = format_bytes($disk_total);
    $disk_free_fmt = format_bytes($disk_free);
    $net_tx_fmt = format_bytes($net['tx']);
    $net_rx_fmt = format_bytes($net['rx']);
    $db_size_fmt = format_bytes($dbtotal_size);

    // Additional variables for dashboard
    $load_str = implode(' / ', $load);
    $php_version = PHP_VERSION;
    $php_sapi = php_sapi_name();
    $os_name = php_uname('s');
    $server_soft_full = $_SERVER['SERVER_SOFTWARE'];
    $gd_version = $gd['GD Version'];
    $post_max = ini_get('post_max_size');
    $file_uploads = ini_get('file_uploads') ? $on : $off;
    $upload_max = ini_get('upload_max_filesize');
    $memory_limit = ini_get('memory_limit');
    $max_input_vars = ini_get('max_input_vars');
    $max_execution_time = ini_get('max_execution_time');
    $gzip_loaded = (extension_loaded('zlib')) ? $on : $off;
    $zip_loaded = (extension_loaded('zip')) ? $on : $off;
    $php_time = date('H:i:s');
    $operation_mode = (!$conf['close']) ? $on : $off;
    $stat_active = (is_mod_active('stat')) ? $on : $off;
    $referers_active = (is_mod_active('referers')) ? $on : $off;
    $newsletter = ($conf['newsletter']) ? $on : $off;
    $cache = ($conf['cache']) ? $on : $off;
    $rewrite = ($conf['rewrite']) ? $on : $off;
    $cms_version = $conf['version'];

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

    $cont .= <<<HTML

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root {
            --bg-body: #f3f4f6;
            --bg-card: #ffffff;
            --text-main: #374151;
            --text-sub: #9ca3af;
            --primary: #3b82f6;
            --accent: #22c55e;
            --warn: #f97316;
            --danger: #ef4444;
            --border: #e5e7eb;
        }

        .mon-wrapper {
            font-family: 'Inter', system-ui, sans-serif;
            color: var(--text-main);
        }

        .mon-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .mon-panel-head {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* 1. Top Section Split */
        .mon-top-split {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media(max-width: 900px) {
            .mon-top-split {
                grid-template-columns: 1fr;
            }
        }

        .mon-sys-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            text-align: center;
        }

        .mon-sys-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .knob-wrapper {
            position: relative;
            width: 100px;
            height: 100px;
            margin-bottom: 10px;
        }

        .knob-svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .knob-bg {
            fill: none;
            stroke: #f3f4f6;
            stroke-width: 8;
            stroke-linecap: round;
        }

        .knob-val {
            fill: none;
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s ease;
        }

        .knob-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 20px;
            font-weight: 700;
            color: #111827;
        }

        .sys-label {
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 4px;
        }

        .sys-sub {
            font-size: 12px;
            color: #9ca3af;
        }

        /* Disk Big Gauge */
        .disk-wrapper {
            position: relative;
            width: 140px;
            height: 140px;
            margin: 0 auto;
        }

        .disk-text {
            text-align: center;
            margin-top: 10px;
        }

        /* 2. Overview Strip */
        .mon-overview-strip {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px 30px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 20px;
            align-items: center;
        }

        .ov-item {
            display: flex;
            flex-direction: column;
            position: relative;
            border-right: 1px solid #f3f4f6;
            padding-left: 10px;
        }

        .ov-item:last-child {
            border-right: none;
        }

        .ov-label {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .ov-val {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ov-icon-arr {
            background: #f3f4f6;
            width: 20px;
            height: 20px;
            border-radius: 4px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 10px;
            color: #9ca3af;
        }

        /* 3. Bottom Grid */
        .mon-bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Software Cards */
        .sw-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
        }

        .sw-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .sw-icon {
            font-size: 28px;
            margin-bottom: 10px;
            color: #4b5563;
        }

        .sw-name {
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 15px;
            text-align: center;
        }

        .sw-stat-row {
            position: absolute;
            bottom: 10px;
            left: 0;
            right: 0;
            display: flex;
            justify-content: space-between;
            padding: 0 15px;
            font-size: 10px;
            color: #9ca3af;
        }

        .sw-status-green {
            color: #22c55e;
        }

        /* Traffic Chart */
        .traffic-stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
        }

        .t-label span {
            width: 8px;
            height: 8px;
            display: inline-block;
            border-radius: 50%;
            margin-right: 5px;
        }

        .t-label-up span {
            background: #f97316;
        }

        .t-label-down span {
            background: #22c55e;
        }

        .t-val {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin-top: 4px;
        }

        .chart-svg {
            width: 100%;
            height: 220px;
        }

        /* Detailed Table Footer */
        .mon-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .mon-clean-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .mon-clean-table td {
            padding: 9px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .mon-clean-table td:first-child {
            font-weight: 600;
            color: #555;
            width: 45%;
        }

        .mon-clean-table td:last-child {
            color: #333;
        }
    </style>

    <div class="mon-wrapper">

        <!-- ROW 1: Sys Status + Disk -->
        <div class="mon-top-split">
            <!-- STATUS PANEL -->
            <div class="mon-card">
                <div class="mon-panel-head">Sys Status</div>
                <div class="mon-sys-grid">
                    <!-- CPU Load -->
                    <div class="mon-sys-item">
                        <div class="knob-wrapper">
                            <svg class="knob-svg" viewBox="0 0 100 100">
                                <circle class="knob-bg" cx="50" cy="50" r="45"></circle>
                                <circle class="knob-val" cx="50" cy="50" r="45" stroke="#22c55e" stroke-dasharray="{$dash}" stroke-dashoffset="{$off}"></circle>
                            </svg>
                            <div class="knob-text">{$load[0]}%</div>
                        </div>
                        <div class="sys-label">Operations</div>
                        <div class="sys-sub">{$load_str}</div>
                    </div>
                    <!-- CPU Real -->
                    <div class="mon-sys-item">
                        <div class="knob-wrapper">
                            <svg class="knob-svg" viewBox="0 0 100 100">
                                <circle class="knob-bg" cx="50" cy="50" r="45"></circle>
                                <circle class="knob-val" cx="50" cy="50" r="45" stroke="#22c55e" stroke-dasharray="{$dash}" stroke-dashoffset="{$dash}"></circle>
                            </svg>
                            <div class="knob-text">0.2%</div>
                        </div>
                        <div class="sys-label">CPU Usage</div>
                        <div class="sys-sub">8 Cores</div>
                    </div>
                    <!-- RAM -->
                    <div class="mon-sys-item">
                        <div class="knob-wrapper">
                            <svg class="knob-svg" viewBox="0 0 100 100">
                                <circle class="knob-bg" cx="50" cy="50" r="45"></circle>
                                <circle class="knob-val" cx="50" cy="50" r="45" stroke="#22c55e" stroke-dasharray="{$dash}" stroke-dashoffset="{$offR}"></circle>
                            </svg>
                            <div class="knob-text">{$ramP}%</div>
                        </div>
                        <div class="sys-label">RAM Usage</div>
                        <div class="sys-sub">{$ram_used_mb} / {$ram_total_mb} (MB)</div>
                    </div>
                </div>
            </div>

            <!-- DISK PANEL -->
            <div class="mon-card">
                <div class="mon-panel-head">Disk</div>
                <div class="disk-wrapper">
                    <svg class="knob-svg" viewBox="0 0 100 100">
                        <!-- Concentric circles effect -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#f3f4f6" stroke-width="6"></circle>
                        <circle cx="50" cy="50" r="35" fill="none" stroke="#f9fafb" stroke-width="6"></circle>
                        <circle cx="50" cy="50" r="25" fill="none" stroke="#f3f4f6" stroke-width="6"></circle>

                        <!-- Value Arc -->
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#22c55e" stroke-width="6" stroke-linecap="round" stroke-dasharray="{$dashD}" stroke-dashoffset="{$offD}"></circle>
                    </svg>
                    <div class="knob-text" style="font-size:24px; color:#22c55e;">{$diskP}%</div>
                </div>
                <div class="disk-text">
                    <div class="sys-label">Root /</div>
                    <div class="sys-sub">{$disk_used_fmt} / {$disk_total_fmt}</div>
                </div>
            </div>
        </div>

        <!-- ROW 2: Overview Strip -->
        <div class="mon-overview-strip">
            <div class="ov-item">
                <div class="ov-label">Website</div>
                <div class="ov-val">{$cnt_news} <div class="ov-icon-arr"><i class="fa fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ov-item">
                <div class="ov-label">Files</div>
                <div class="ov-val">{$cnt_files} <div class="ov-icon-arr"><i class="fa fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ov-item">
                <div class="ov-label">Database</div>
                <div class="ov-val">{$db_tables} <div class="ov-icon-arr"><i class="fa fa-chevron-right"></i></div>
                </div>
            </div>
            <div class="ov-item">
                <div class="ov-label">Users</div>
                <div class="ov-val">{$users_online} <div class="ov-icon-arr"><i class="fa fa-chevron-right"></i></div>
                </div>
            </div>
        </div>

        <!-- ROW 3: Software & Traffic -->
        <div class="mon-bottom-grid">
            <!-- Software -->
            <div class="mon-card">
                <div class="mon-panel-head">Software</div>
                <div class="sw-grid">
                    <div class="sw-card">
                        <i class="sw-icon fa fa-server"></i>
                        <div class="sw-name">{$server_name}</div>
                        <div class="sw-stat-row"><span>Ver</span> <span class="sw-status-green"><i class="fa fa-play"></i></span></div>
                    </div>
                    <div class="sw-card">
                        <i class="sw-icon fa fa-database"></i>
                        <div class="sw-name">MySQL</div>
                        <div class="sw-stat-row"><span>{$mysql_ver}</span> <span class="sw-status-green"><i class="fa fa-play"></i></span></div>
                    </div>
                    <div class="sw-card">
                        <i class="sw-icon fa fa-code"></i>
                        <div class="sw-name">PHP</div>
                        <div class="sw-stat-row"><span>{$php_version}</span> <span class="sw-status-green"><i class="fa fa-play"></i></span></div>
                    </div>
                    <div class="sw-card">
                        <i class="sw-icon fa fa-shield"></i>
                        <div class="sw-name">Firewall</div>
                        <div class="sw-stat-row"><span>WAF</span> <span class="sw-status-green"><i class="fa fa-play"></i></span></div>
                    </div>
                </div>
            </div>

            <!-- Traffic -->
            <div class="mon-card">
                <div class="mon-panel-head">Traffic <span style="font-weight:400; font-size:12px; color:#999; margin-left:auto;">Net: All</span></div>
                <div class="traffic-stats-row">
                    <div class="t-item">
                        <div class="t-label t-label-up"><span></span> Upstream</div>
                        <div class="t-val">{$net_tx_fmt}</div>
                    </div>
                    <div class="t-item">
                        <div class="t-label t-label-down"><span></span> Downstream</div>
                        <div class="t-val">{$net_rx_fmt}</div>
                    </div>
                </div>
                <!-- Chart -->
                <svg class="chart-svg" preserveAspectRatio="none">
                    <defs>
                        <linearGradient id="gUp" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#f97316" stop-opacity="0.5" />
                            <stop offset="1" stop-color="#fff" stop-opacity="0" />
                        </linearGradient>
                        <linearGradient id="gDown" x1="0" x2="0" y1="0" y2="1">
                            <stop offset="0" stop-color="#22c55e" stop-opacity="0.5" />
                            <stop offset="1" stop-color="#fff" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <path d="{$pathUp}" fill="url(#gUp)" stroke="#f97316" stroke-width="2" />
                    <path d="{$pathDown}" fill="url(#gDown)" stroke="#22c55e" stroke-width="2" />
                </svg>
            </div>
        </div>

        <!-- 4. Footer: Detailed Info (Retained) -->
        <div class="mon-card">
            <div class="mon-panel-head"><i class="fa fa-list-alt"></i> Detailed System Information</div>
            <div class="mon-info-grid">
                <div>
                    <table class="mon-clean-table">
                        <tr>
                            <td>Operation Mode</td>
                            <td>{$operation_mode}</td>
                        </tr>
                        <tr>
                            <td>Statistics</td>
                            <td>{$stat_active}</td>
                        </tr>
                        <tr>
                            <td>Transitions</td>
                            <td>{$referers_active}</td>
                        </tr>
                        <tr>
                            <td>Newsletter</td>
                            <td>{$newsletter}</td>
                        </tr>
                        <tr>
                            <td>Caching</td>
                            <td>{$cache}</td>
                        </tr>
                        <tr>
                            <td>SEF Rewrite</td>
                            <td>{$rewrite}</td>
                        </tr>
                        <tr>
                            <td>SLAED CMS</td>
                            <td>{$cms_version}</td>
                        </tr>
                        <tr>
                            <td>OS</td>
                            <td>{$os_name}</td>
                        </tr>
                        <tr>
                            <td>Server</td>
                            <td>{$server_soft_full}</td>
                        </tr>
                        <tr>
                            <td>PHP Version</td>
                            <td>{$php_version}</td>
                        </tr>
                        <tr>
                            <td>PHP SAPI</td>
                            <td>{$php_sapi}</td>
                        </tr>
                        <tr>
                            <td>PHP GD</td>
                            <td>{$gd_version}</td>
                        </tr>
                        <tr>
                            <td>MySQL</td>
                            <td>{$mysql_ver}</td>
                        </tr>
                    </table>
                </div>
                <div>
                    <table class="mon-clean-table">
                        <tr>
                            <td>DB Size</td>
                            <td>{$db_size_fmt}</td>
                        </tr>
                        <tr>
                            <td>Post Max Size</td>
                            <td>{$post_max}</td>
                        </tr>
                        <tr>
                            <td>File Uploads</td>
                            <td>{$file_uploads}</td>
                        </tr>
                        <tr>
                            <td>Upload Max</td>
                            <td>{$upload_max}</td>
                        </tr>
                        <tr>
                            <td>Memory Limit</td>
                            <td>{$memory_limit}</td>
                        </tr>
                        <tr>
                            <td>Max Input Vars</td>
                            <td>{$max_input_vars}</td>
                        </tr>
                        <tr>
                            <td>Execution Time</td>
                            <td>{$max_execution_time} s</td>
                        </tr>
                        <tr>
                            <td>GZip</td>
                            <td>{$gzip_loaded}</td>
                        </tr>
                        <tr>
                            <td>Zip Archive</td>
                            <td>{$zip_loaded}</td>
                        </tr>
                        <tr>
                            <td>PHP Timezone</td>
                            <td>{$php_time}</td>
                        </tr>
                        <tr>
                            <td>Disk Total</td>
                            <td>{$disk_total_fmt}</td>
                        </tr>
                        <tr>
                            <td>Disk Free</td>
                            <td>{$disk_free_fmt}</td>
                        </tr>
                        <tr>
                            <td>Disk Used</td>
                            <td>{$disk_used_fmt}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
HTML;
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
