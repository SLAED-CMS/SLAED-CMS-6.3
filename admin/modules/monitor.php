<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

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

# Builds the monitor traffic chart from its four series; the SVG around them lives in the theme partial
function getMonitorChartSvg(array $snap): string {
    global $tpl;
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
    $upb = getHistoryBucketPoints($uh, $mx, $pb, $ww, $px, $bc);
    $dpb = getHistoryBucketPoints($dh, $mx, $pb, $ww, $px, $bc);
    $cpb = getHistoryBucketPoints($ch, 100.0, $pb, $ww, $px, $bc);
    $rpb = getHistoryBucketPoints($rh, 100.0, $pb, $ww, $px, $bc);
    $series = [
        ['key' => 'up', 'grad' => 'slMonitorAreaUp', 'area' => getSmoothAreaPath($uh, $mx, $pb, $ww, $px), 'line' => getSmoothLinePath($uh, $mx, $pb, $ww, $px)],
        ['key' => 'down', 'grad' => 'slMonitorAreaDown', 'area' => getSmoothAreaPath($dh, $mx, $pb, $ww, $px), 'line' => getSmoothLinePath($dh, $mx, $pb, $ww, $px)],
        ['key' => 'cpu', 'grad' => 'slMonitorAreaCpu', 'area' => getSmoothAreaPath($ch, 100.0, $pb, $ww, $px), 'line' => getSmoothLinePath($ch, 100.0, $pb, $ww, $px)],
        ['key' => 'ram', 'grad' => 'slMonitorAreaRam', 'area' => getSmoothAreaPath($rh, 100.0, $pb, $ww, $px), 'line' => getSmoothLinePath($rh, 100.0, $pb, $ww, $px)],
    ];
    $gy = [42, 82, 122, 162, 202];
    $al = ['100', '75', '50', '25', '0'];
    $grid = '';
    $ticks = [];
    foreach ($gy as $ix => $yy) {
        $grid .= 'M'.$px.' '.$yy.'H'.($ww - $px);
        $ticks[] = ['y' => $yy + 3, 'label' => $al[$ix]];
    }
    $vgrid = '';
    $axes = [];
    foreach ($xs as $ix => $xx) {
        $vgrid .= 'M'.$xx.' 42V'.$pb;
        $sec = ($bc - 1 - $ix) * $st;
        $axes[] = ['x' => round($xx, 2), 'label' => getMonitorChartTimeLabel($sec, $sec === 0)];
    }
    $slots = [];
    for ($ix = 0; $ix < $bc; $ix++) {
        $up = $upb[$ix] ?? null;
        $dp = $dpb[$ix] ?? null;
        $cp = $cpb[$ix] ?? null;
        $rp = $rpb[$ix] ?? null;
        $fp = getHistoryMarkerPoint($cpb, $ix, $bc);
        $xx = (float)$xs[$ix];
        $stt = ($ix === 0) ? $px : round((($xs[$ix - 1]) + $xx) / 2, 2);
        $end = ($ix === $bc - 1) ? ($ww - $px) : round(($xx + ($xs[$ix + 1])) / 2, 2);
        $tx = $xx + 14;
        if ($tx + $tw > $ww - $px) $tx = $xx - $tw - 14;
        if ($tx < $px) $tx = $px;
        $fy = (float)($fp['y'] ?? ($cp['y'] ?? $pb));
        $ty = (($fy + $th + 18) > ($hh - 8)) ? max($fy - $th - 16, 8) : 56;
        $tm = time() - (($bc - 1 - $ix) * $st);
        $lb = getMonitorChartTimeLabel((int)(($bc - 1 - $ix) * $st), $ix === $bc - 1);
        $slots[] = [
            'x' => $xx,
            'y' => $fy,
            'tipx' => round($tx, 2),
            'tipy' => $ty,
            'title' => date('H:i:s', $tm).' · '.$lb,
            'zonex' => $stt,
            'zonewidth' => max($end - $stt, 1),
            'rows' => [
                ['y' => 39, 'label' => 'CPU', 'value' => round((float)($cp['value'] ?? 0), 1).'%'],
                ['y' => 57, 'label' => 'RAM', 'value' => round((float)($rp['value'] ?? 0), 1).'%'],
                ['y' => 75, 'label' => 'Upstream', 'value' => filterSize((float)($up['value'] ?? 0)).'/s'],
                ['y' => 93, 'label' => 'Downstream', 'value' => filterSize((float)($dp['value'] ?? 0)).'/s'],
            ],
        ];
    }
    return $tpl->getHtmlPart('monitor-chart', [
        'width' => $ww,
        'height' => $hh,
        'label' => 'Traffic monitoring chart',
        'gridline' => $grid,
        'vgrid' => $vgrid,
        'ticks' => $ticks,
        'series' => $series,
        'slots' => $slots,
        'axes' => $axes,
        'tipwidth' => $tw,
        'tipheight' => $th,
    ]);
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
    $cpuval = (float)$cpup;
    $ramval = (float)$mem['percent'];
    $diskval = (float)$diskpct;
    return [
        'load_0' => round($cpuval, 1),
        'cpu_tone' => getPercentTone($cpuval),
        'cpu_full' => $cpuval >= 100,
        'cpucores' => $cpu['logical'],
        'cpuphys' => $cpu['physical'],
        'cpufreq' => $cpu['freq'],
        'ram_tone' => getPercentTone($ramval),
        'ram_full' => $ramval >= 100,
        'ram_p' => round($ramval, 1),
        'ramumb' => filterSize($mem['used']),
        'ramtmb' => filterSize($mem['total']),
        'ramavailmb' => filterSize($mem['free']),
        'disk_tone' => getPercentTone($diskval),
        'disk_full' => $diskval >= 100,
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
    $soft = getServerSoftware();
    $servsw = $soft['raw'];
    $servname = $soft['name'];
    $servver = $soft['version'];
    $srvport = getServerValue('SERVER_PORT', 'N/A');
    $https = strtolower(getServerValue('HTTPS', ''));
    $ishttps = ($https === 'on' || $https === '1') || ((int)$srvport === 443);
    $srvhttps = $tpl->getHtmlFrag('inline-badge', [
        'is_success' => $ishttps,
        'is_danger' => !$ishttps,
        'label' => $ishttps ? 'enabled' : 'disabled',
    ]);
    $exts = get_loaded_extensions();
    $dir = (string)ini_get('extension_dir');
    $base = (string)ini_get('open_basedir');
    $able = $base === '' || array_reduce(
        explode(PATH_SEPARATOR, $base),
        static fn(bool $keep, string $one): bool => $keep || str_starts_with($dir, rtrim($one, '/\\')),
        false
    );
    $why = '';
    if ($dir === '') $why = 'extension_dir is not set, so the module directory was not read';
    elseif (!$able) $why = 'extension_dir '.$dir.' lies outside open_basedir '.$base.', so the module directory was not read';
    elseif (!is_dir($dir)) $why = 'extension_dir '.$dir.' is not a readable directory, so the module directory was not read';
    $off = [];
    if ($why === '') {
        $seen = array_map('strtolower', $exts);
        foreach (array_merge(glob($dir.'/*.so') ?: [], glob($dir.'/*.dll') ?: []) as $file) {
            $name = strtolower(str_replace(['php_', '.dll', '.so'], '', basename($file)));
            if ($name !== '' && !in_array($name, $seen, true)) $off[] = $name;
        }
    }
    $onlist = implode(', ', $exts);
    $offlist = implode(', ', $off);
    $onhtml = $tpl->getHtmlFrag('popover', ['content_html' => $onlist, 'label_text' => $onlist]);
    if ($why !== '') $offhtml = $tpl->getHtmlFrag('popover', ['content_html' => $why, 'label_text' => 'N/A']);
    elseif ($off !== []) $offhtml = $tpl->getHtmlFrag('popover', ['content_html' => $offlist, 'label_text' => $offlist]);
    else $offhtml = 'None';

    return [
        'servsw' => $servsw,
        'servname' => $servname,
        'servver' => $servver,
        'extlist_on' => $onhtml,
        'extlist_off' => $offhtml,
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
            'statusurl' => $afile.'.php?name=monitor&op=status',
            'trafficurl' => $afile.'.php?name=monitor&op=traffic',
            'syncurl' => $afile.'.php?name=monitor&op=sync',
            'status_oob' => '',
            'traffic_oob' => '',
            'cntnews' => $ctx['cntnews'],
            'cntfile' => $ctx['cntfile'],
            'dbtabs' => $ctx['dbtabs'],
            'userson' => $ctx['userson'],
            'servsoftname' => $ctx['servname'],
            'servver' => $ctx['servver'],
            'mysql' => (string)getDbVersion(),
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
            'servroot' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['srvroot'], 'label_text' => $ctx['srvroot']]),
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
            'dbsqlmode' => $tpl->getHtmlFrag('popover', ['content_html' => $dbhealth['sql_mode'], 'label_text' => $dbhealth['sql_mode']]),
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
            'reqcookie' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['reqcookie'], 'label_text' => $ctx['reqcookie']]),
            'requri' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['requri'], 'label_text' => $ctx['requri']]),
            'reqquery' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['reqquery'], 'label_text' => $ctx['reqquery']]),
            'reqip' => (string)$ctx['reqip'],
            'requa' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['requa'], 'label_text' => $ctx['requa']]),
            'reqlang' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['reqlang'], 'label_text' => $ctx['reqlang']]),
            'dskread' => ($diskio['read_rate'] === null) ? 'N/A' : filterSize((int)$diskio['read_rate']).'/s',
            'dskwrite' => ($diskio['write_rate'] === null) ? 'N/A' : filterSize((int)$diskio['write_rate']).'/s',
            'backupdirsz' => ($storages['backup'] === null) ? 'N/A' : filterSize((int)$storages['backup']),
            'cachedirsz' => ($storages['cache'] === null) ? 'N/A' : filterSize((int)$storages['cache']),
            'logsdirsz' => ($storages['logs'] === null) ? 'N/A' : filterSize((int)$storages['logs']),
            'lastbackuprun' => (string)$ctx['lastbackup'],
            'errorlog24h' => (string)$ctx['error24'],
            'uploadssz' => (string)$ctx['uploadsz'],
            'failedlogins24h' => (string)$ctx['failed24'],
            'lastsecurityevent24h' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['seclast24'], 'label_text' => $ctx['seclast24']]),
            'dbissueevent24h' => $tpl->getHtmlFrag('popover', ['content_html' => $ctx['dblast24'], 'label_text' => $ctx['dblast24']]),
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
    $navi = getTplAdminTabs(['ops' => ['name=monitor', 'name=monitor&op=info'], 'tabs' => [_HOME, _DOCS]]);
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
        'ops' => ['name=monitor', 'name=monitor&op=info'],
        'tabs' => [_HOME, _DOCS],
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
