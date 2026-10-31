<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# A rate of the sample in KB/s: the sample holds bytes, and the chart keeps one unit for its whole window
function getMonitorRate(array $hist): array {
    return array_map(static fn(int|float $val): float => round($val / 1024, 1), $hist);
}

# A tile of a series over the chart: the value now, the peak and the mean of the window, the dot and the icon of the series
function getMonitorTile(string $label, string $tone, string $icon, array $vals, string $unit, int $digits): array {
    $now = $vals ? (float)$vals[count($vals) - 1] : 0.0;
    $text = static fn(int|float $num): string => filterDecimal($num, $digits);
    return [
        'label' => $label,
        'tone' => $tone,
        'icon' => $icon,
        'unit' => $unit,
        'num' => round($now, $digits),
        'value' => $text($now),
        'note' => sprintf(_MONITOR_PEAKAVG, $text($vals ? max($vals) : 0), $text($vals ? array_sum($vals) / count($vals) : 0)),
    ];
}

# All the page redraws of one reading: the lamp and its panel, the rings and the strip of the cockpit, the tiles and the live chart; the first draw and the poll take this one shape
function getMonitorView(array $snap, array $facts): array {
    $sigs = getServerSignalList($facts);
    $rate = getPageHourRate();
    $lamp = getServerLamp($sigs, $rate, (float)$snap['cpu_p']);
    $gauges = getCockpitRings($snap);
    foreach ($sigs as $sig) if (isset($gauges[$sig['key']]) && $sig['level'] !== 'ok') $gauges[$sig['key']]['tone'] = $sig['level'];
    $mood = ['ok' => _ADM_CALM, 'info' => _ADM_LULL, 'warn' => _ADM_HEED, 'danger' => _ADM_FAULT][$lamp['tone']];
    $beat = ($rate === null) ? '—' : filterDecimal($rate / 60, 1);
    $mins = getUptimeMinutes((string)$snap['uptime']);
    $text = ($mins === null) ? '—' : getUptimeWords($mins);
    $up = getMonitorRate($snap['net_hist_up']);
    $down = getMonitorRate($snap['net_hist_down']);
    $at = $snap['sys_hist_time'];
    return [
        'tone' => $lamp['tone'],
        'loud' => $lamp['loud'],
        'cap' => $lamp['loud'] ? _ADM_WAIT : sprintf(_ADM_PASSED, $lamp['total']),
        'rows' => array_values(array_filter($sigs, static fn(array $sig): bool => $sig['level'] !== 'ok')),
        'pulse' => [
            'mode' => $lamp['mode'],
            'base' => $lamp['base'],
            'bpm' => $lamp['bpm'],
            'num' => ($rate === null) ? '—' : round($rate / 60, 1),
            'text' => $beat,
            'mood' => $mood,
            'label' => sprintf(_MONITOR_PULSE, $beat, $mood, $lamp['pass'], $lamp['total']),
        ],
        'gauges' => $gauges,
        'strip' => ['uptime' => ['value' => $text, 'text' => $text]] + getCockpitStrip(),
        'age' => ($snap['sampled_at'] > 0) ? max(time() - $snap['sampled_at'], 0) : 0,
        'meta' => sprintf(_MONITOR_WINDOW, $at ? max(1, (int)round(($at[count($at) - 1] - $at[0]) / 60)) : 0),
        'tiles' => [
            getMonitorTile(_MONITOR_UP, 'up', 'cloud-upload-fill', $up, 'KB/s', 1),
            getMonitorTile(_MONITOR_DOWN, 'down', 'cloud-download-fill', $down, 'KB/s', 1),
            getMonitorTile('CPU', 'cpu', 'cpu-fill', $snap['sys_hist_cpu'], '%', 0),
            getMonitorTile('RAM', 'ram', 'motherboard-fill', $snap['sys_hist_ram'], '%', 0),
        ],
        'live' => [
            'label' => _MONITOR_TRAFFIC,
            'at' => $at,
            'now' => time(),
            'grid' => ['100%', '75%', '50%', '25%', '0%'],
            'words' => ['now' => _MONITOR_TIME_NOW, 'sec' => _MONITOR_TIME_AGO, 'min' => _MONITOR_TIME_MIN],
            'series' => [
                ['key' => 'up', 'name' => _MONITOR_UP, 'unit' => 'KB/s', 'digits' => 1, 'tone' => 'up', 'values' => $up],
                ['key' => 'down', 'name' => _MONITOR_DOWN, 'unit' => 'KB/s', 'digits' => 1, 'tone' => 'down', 'values' => $down],
                ['key' => 'cpu', 'name' => 'CPU', 'unit' => '%', 'digits' => 0, 'tone' => 'cpu', 'max' => 100, 'values' => $snap['sys_hist_cpu']],
                ['key' => 'ram', 'name' => 'RAM', 'unit' => '%', 'digits' => 0, 'tone' => 'ram', 'max' => 100, 'values' => $snap['sys_hist_ram']],
            ],
        ],
    ];
}

# A row of a list: a value cut short opens whole beside the mark of the system, an empty one says so in the quiet tone
function getMonitorRow(string $label, string $text, bool $long = false): array {
    if ($text === '') return ['label' => $label, 'text' => _NO_INFO, 'is_mute' => true];
    return ['label' => $label, 'text' => $text, 'is_long' => $long, 'tip_label' => _DETAILS.': '.$label];
}

# A row of a state: its lamp with the word, in the system's words for on and off
function getMonitorFlag(string $label, bool $state): array {
    return ['label' => $label, 'text' => $state ? _ON : _OFF, 'is_flag' => true, 'is_on' => $state];
}

# A size of a folder, or the quiet word where it could not be read
function getMonitorSize(string $label, int|float|null $bytes): array {
    return ($bytes === null) ? getMonitorRow($label, '') : getMonitorRow($label, filterSize($bytes));
}

# The latest security event as a line of its time, level and message, the raw line of the journal never shown
function getMonitorEvent(?array $evt): array {
    if ($evt === null) return getMonitorRow(_MONITOR_LASTEV, '');
    if ($evt === []) return ['label' => _MONITOR_LASTEV, 'text' => _NO, 'is_good' => true];
    $word = ['critical' => _MONITOR_LVCRIT, 'error' => _MONITOR_LVERROR, 'warning' => _MONITOR_LVWARN][$evt['level']] ?? _MONITOR_LVINFO;
    return getMonitorRow(_MONITOR_LASTEV, implode(' · ', array_filter([getShortDate($evt['time']), $word, $evt['text'] ?: $evt['file']])), true);
}

# The names of the cookies of the request and their number; their values, the session among them, stay off the screen
function getMonitorCookies(): array {
    $list = array_keys(getCookieValues());
    return getMonitorRow(_MONITOR_COOKIES, $list ? sprintf(_MONITOR_HIDDEN, count($list), implode(', ', $list)) : '', true);
}

# The modules PHP has on disk and has not loaded, read from its directory; a directory out of reach gives its settings instead of a list
function getMonitorExtOff(array $seen): array {
    $dir = (string)ini_get('extension_dir');
    $base = (string)ini_get('open_basedir');
    if ($dir === '' || !isPathAllowed($dir) || !is_dir($dir)) return ['chips' => [], 'notes' => [['name' => 'extension_dir', 'text' => sprintf(_MONITOR_EXTDIR, $dir, $base)]]];
    $off = [];
    foreach (array_merge(glob($dir.'/*.so') ?: [], glob($dir.'/*.dll') ?: []) as $file) {
        $name = strtolower(str_replace(['php_', '.dll', '.so'], '', basename($file)));
        if ($name !== '' && !in_array($name, $seen, true)) $off[] = ['name' => $name, 'is_off' => true];
    }
    return ['chips' => $off, 'notes' => []];
}

# The extensions by their weight: a wanted one framed with its state, what a missing one costs under its group, then the others loaded and those on disk left out
function getMonitorExts(): string {
    global $tpl;
    $exts = getExtensionWeight(get_loaded_extensions());
    $cost = ['gd' => _MONITOR_NOGD, 'curl' => _MONITOR_NOCURL, 'dom' => _MONITOR_NODOM, 'intl' => _MONITOR_NOINTL, 'OPcache' => _MONITOR_NOOPC, 'openssl' => _MONITOR_NOSSL,
        'Phar' => _MONITOR_NOPHAR, 'fileinfo' => _MONITOR_NOFINFO, 'sodium' => _MONITOR_NOSODIUM, 'zip' => _MONITOR_NOZIP, 'zlib' => _MONITOR_NOZLIB];
    $group = static function (string $label, array $list, array $lost) use ($cost): array {
        $have = count($list) - count($lost);
        return [
            'label' => $label,
            'have_text' => sprintf(_MONITOR_HAVE, $have, count($list)),
            'is_short' => $lost !== [],
            'chips' => array_map(static fn(string $one): array => in_array($one, $lost, true)
                ? ['name' => $one, 'is_lost' => true, 'state_text' => _NO] : ['name' => $one, 'is_ok' => true, 'state_text' => _MONITOR_HAS], $list),
            'notes' => array_map(static fn(string $one): array => ['name' => sprintf(_MONITOR_EXTNO, $one), 'text' => $cost[$one] ?? _MONITOR_NOEXT], $lost),
        ];
    };
    $off = getMonitorExtOff($exts['seen']);
    return $tpl->getHtmlFrag('ext', ['groups' => [
        $group(_MONITOR_MUST, $exts['must'], $exts['must_lost']),
        $group(_MONITOR_TIP, $exts['tip'], $exts['tip_lost']),
        ['label' => _MONITOR_OTHER, 'chips' => array_map(static fn(string $one): array => ['name' => $one], $exts['other'])],
        ['label' => _MONITOR_OFF] + $off,
    ]]);
}

# The whole ground of the page from one reading: the windows are drawn once, and the poll moves only the cockpit and the traffic
function getMonitorGround(array $snap): string {
    global $afile, $conf, $db, $sgtime, $tpl;
    $every = 4;
    $facts = getServerFacts($snap);
    $view = getMonitorView($snap, $facts);
    $extra = getMonitorRuntimeExtras();
    $stats = getMonitorDbStats();
    $health = $facts['health'];
    $web = getServerSoftware();
    $webver = ($web['version'] !== 'N/A') ? $web['version'] : '';
    $dbase = getDbLabel((string)($snap['soft']['db'] ?? getDbVersion()));
    $store = $extra['storages'];
    $disk = $snap['diskio'];
    $opc = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
    $opon = is_array($opc) && !empty($opc['opcache_enabled']);
    $opmem = $opon ? $opc['memory_usage'] : [];
    $optot = (int)($opmem['used_memory'] ?? 0) + (int)($opmem['free_memory'] ?? 0) + (int)($opmem['wasted_memory'] ?? 0);
    $https = in_array(strtolower(getServerValue('HTTPS', '')), ['on', '1'], true) || (int)getServerValue('SERVER_PORT', '0') === 443;
    $peak = memory_get_peak_usage(true);
    $limit = getMemoryLimitBytes();
    $upmax = ini_parse_quantity((string)ini_get('upload_max_filesize'));
    $postmax = ini_parse_quantity((string)ini_get('post_max_size'));
    $upnote = sprintf(_MONITOR_UPNOTE, ini_get('post_max_size'), ini_get('upload_max_filesize'));
    $free = $snap['disk_total'] > 0 ? $snap['disk_free'] * 100 / $snap['disk_total'] : 100;
    $rate = static fn(?float $num): string => ($num === null) ? '' : filterSize($num).'/s';
    $mins = getUptimeMinutes((string)$snap['uptime']);
    $backup = strtotime((string)$extra['lastbackup']);
    $opbar = $opon ? getTplMeter(_MONITOR_OPMEM, (int)$opmem['used_memory'], $optot, sprintf(_MONITOR_OF, filterSize($opmem['used_memory']), filterSize($optot))) : '';
    $list = static fn(array $items): string => $tpl->getHtmlFrag('kv-list', ['items' => $items]);
    $pane = static fn(array $keys): string => $tpl->getHtmlFrag('pane', $keys);
    $tiles = $tpl->getHtmlFrag('metric-list', ['items' => $view['tiles']]);
    $part = $tpl->getHtmlFrag('series', [
        'id' => 'sl-monitor-live',
        'kind' => 'live',
        'wait_text' => _MONITOR_WAIT,
        'json' => (string)json_encode($view['live'], JSON_UNESCAPED_UNICODE),
    ]);
    $cockpit = $tpl->getHtmlPart('cockpit', [
        'cap_icon' => 'display',
        'cap_text' => _MONITOR_STATE,
        'tone' => $view['tone'],
        'is_ok' => $view['tone'] === 'ok',
        'is_info' => $view['tone'] === 'info',
        'is_warn' => $view['tone'] === 'warn',
        'is_danger' => $view['tone'] === 'danger',
        'loud' => $view['loud'],
        'live_html' => $tpl->getHtmlFrag('live-chip', ['live_every' => $every]),
        'lamp_html' => $tpl->getHtmlPart('monitor-cockpit', ['is_lamp' => true, 'state_text' => _MONITOR_STATE, 'rate_text' => _MONITOR_RATE]),
        'rings' => getCockpitParts()['rings'],
        'strip' => array_merge([['key' => 'uptime', 'icon' => 'clock-history', 'label' => _MONITOR_UPTIME, 'unit' => '']], getCockpitParts()['strip']),
    ]);
    $traffic = $pane([
        'title' => _MONITOR_TRAFFIC,
        'icon' => 'graph-up-arrow',
        'meta' => $view['meta'],
        'live_html' => $tpl->getHtmlFrag('live-chip', ['live_every' => $every]),
        'content_html' => $tiles.$part,
    ]);
    $server = $pane([
        'title' => _MONITOR_SRVPHP,
        'icon' => 'hdd-network',
        'meta' => trim($web['name'].' '.$webver).' · PHP '.PHP_VERSION,
        'content_html' => $list([
            getMonitorRow(_MONITOR_UPTIME, ($mins === null) ? '' : getUptimeWords($mins, true)),
            getMonitorRow(_MONITOR_OS, php_uname('s')),
            getMonitorRow(_MONITOR_WEB, $web['raw'], true),
            getMonitorFlag('HTTPS', $https),
            getMonitorRow(_MONITOR_PROTO, getServerValue('SERVER_PROTOCOL', '')),
            getMonitorRow(_MONITOR_HOST, getServerValue('SERVER_NAME', '')),
            getMonitorRow(_MONITOR_IP, getServerValue('SERVER_ADDR', '')),
            getMonitorRow(_MONITOR_PORT, getServerValue('SERVER_PORT', '')),
            getMonitorRow(_MONITOR_ROOT, getServerValue('DOCUMENT_ROOT', ''), true),
        ]),
        'right_html' => $opbar.$list([
            getMonitorRow('SAPI', php_sapi_name()),
            getMonitorRow('Zend Engine', function_exists('zend_version') ? zend_version() : ''),
            getMonitorRow(_MONITOR_CHARSET, (string)ini_get('default_charset')),
            getMonitorRow(_MONITOR_ZONE, date_default_timezone_get()),
            getMonitorRow('GD', function_exists('gd_info') ? (string)(gd_info()['GD Version'] ?? '') : '', true),
            getMonitorFlag('GZip', extension_loaded('zlib')),
            getMonitorFlag('Zip', extension_loaded('zip')),
            getMonitorFlag('BZip2', extension_loaded('bz2')),
        ]),
    ]);
    $soft = $pane(['title' => _MONITOR_SOFT, 'content_html' => $list([
        ['label' => 'SLAED CMS', 'text' => (string)($conf['version'] ?? ''), 'icon' => 'window-stack'],
        ['label' => 'PHP', 'text' => PHP_VERSION, 'icon' => 'filetype-php'],
        ['label' => $dbase['name'], 'text' => $dbase['version'], 'icon' => 'database'],
        ['label' => $web['name'], 'text' => $webver ?: _NO_INFO, 'icon' => 'hdd-rack'],
    ])]);
    $opcache = $pane(['title' => 'OPcache', 'content_html' => $list([
        getMonitorFlag('OPcache', $opon),
        getMonitorRow(_ADM_SCRIPTS, $opon ? filterDecimal((int)$opc['opcache_statistics']['num_cached_scripts'], 0) : ''),
        getMonitorRow(_ADM_HITS, $opon ? filterDecimal(round((float)$opc['opcache_statistics']['opcache_hit_rate'], 1)).'%' : ''),
    ])]);
    $base = $pane([
        'title' => _MONITOR_DBREQ,
        'icon' => 'database',
        'meta' => $dbase['name'].' '.$dbase['version'].' · '.filterSize($stats['dbsize']),
        'content_html' => (($health['buffer_pool'] !== null) ? getTplMeter(_MONITOR_POOL, $stats['dbsize'], $health['buffer_pool'],
            sprintf(_MONITOR_POOLFOR, filterSize($stats['dbsize']), filterSize($health['buffer_pool'])), ($stats['dbsize'] > $health['buffer_pool']) ? 'warn' : 'ok') : '').$list([
            getMonitorRow(_MONITOR_SERVER, $dbase['name'].' '.$dbase['version']),
            getMonitorRow(_MONITOR_CHARSET, $health['charset']),
            getMonitorRow(_MONITOR_ZONE, $health['timezone']),
            getMonitorRow(_MONITOR_PACKET, ($health['max_packet'] !== null) ? filterSize($health['max_packet']) : ''),
            getMonitorRow(_MONITOR_CONNS, ($health['connections'] !== null) ? sprintf(_MONITOR_OF, $health['connections'], (int)$health['max_conn']) : ''),
            getMonitorRow(_MONITOR_DBSIZE, filterSize($stats['dbsize'])),
            getMonitorRow(_MONITOR_TABLES, (string)$stats['dbtabs']),
            getMonitorRow(_MONITOR_DBNAME, (string)($conf['db']['name'] ?? '')),
            getMonitorRow(_USER, $health['user']),
            getMonitorRow('SQL mode', str_replace(',', ', ', $health['sql_mode']), true),
        ]),
        'right_html' => getTplMeter(_ADM_PHPMEM, $peak, $limit, ($limit > 0) ? sprintf(_MONITOR_PEAK, filterSize($peak), filterSize($limit)) : _MONITOR_NOLIMIT).$list([
            getMonitorRow(_MONITOR_CLIENT, getIp()),
            getMonitorRow(_ADDRESS, getServerValue('REQUEST_URI', ''), true),
            getMonitorRow(_MONITOR_QUERY, getServerValue('QUERY_STRING', ''), true),
            getMonitorRow(_MONITOR_TIME, filterDecimal((microtime(true) - $sgtime) * 1000, 0).' ms'),
            getMonitorRow(_MONITOR_DBQUERY, (string)$db->qnum),
            getMonitorRow(_MONITOR_DBTIME, filterDecimal($db->sqltime * 1000, 1).' ms'),
            getMonitorRow(_MONITOR_ACCEPT, getServerValue('HTTP_ACCEPT_LANGUAGE', ''), true),
            getMonitorCookies(),
            getMonitorRow(_BROWSER, getServerValue('HTTP_USER_AGENT', ''), true),
        ]),
    ]);
    $guard = $pane(['title' => _SECURITY, 'content_html' => $list([
        getMonitorRow(_MONITOR_FAILED, filterDecimal((int)$extra['failed24'], 0)),
        getMonitorEvent($extra['seclast24']),
        is_int($extra['sql24']) && $extra['sql24'] > 0 ? getMonitorRow(_MONITOR_DBERR, filterDecimal($extra['sql24'], 0))
            : (is_int($extra['sql24']) ? ['label' => _MONITOR_DBERR, 'text' => _NO, 'is_good' => true] : getMonitorRow(_MONITOR_DBERR, '')),
        getMonitorRow(_MONITOR_ERRLOG, is_int($extra['error24']) ? sprintf(_MONITOR_RECORDS, filterDecimal($extra['error24'], 0)) : ''),
    ])]);
    $folders = $pane([
        'title' => _MONITOR_STORE,
        'meta' => ($backup > 0) ? sprintf(_MONITOR_BACKUP, getShortDate($backup)) : '',
        'content_html' => $list([
            getMonitorSize(_UPLOADS, $snap['uploads']),
            getMonitorSize(_MONITOR_BACKUPS, $store['backup']),
            getMonitorSize(_MONITOR_LOGS, $store['logs']),
            getMonitorSize(_MONITOR_CACHE, $store['cache']),
        ]),
    ]);
    $drive = $pane(['title' => _ADM_DISK, 'content_html' => getTplMeter(_ADM_DISK, $snap['disk_used'], $snap['disk_total'],
        sprintf(_MONITOR_OF, filterSize($snap['disk_used']), filterSize($snap['disk_total']))).$list([
        getMonitorSize(_ADM_FREE, $snap['disk_free']),
        getMonitorRow(_MONITOR_READ, $rate($disk['read_rate'])),
        getMonitorRow(_MONITOR_WRITE, $rate($disk['write_rate'])),
        ($free < 10) ? ['label' => _MONITOR_HEALTH, 'text' => _MONITOR_LOWDISK] : ['label' => _MONITOR_HEALTH, 'text' => _MONITOR_NORM, 'is_good' => true],
    ])]);
    $limits = $pane(['title' => _MONITOR_LIMITS, 'content_html' => $list([
        getMonitorRow(_MONITOR_MEMLIM, (string)ini_get('memory_limit')),
        getMonitorRow(_MONITOR_SCRIPT, filterSize(memory_get_usage(true))),
        getMonitorRow(_MONITOR_MAXTIME, ini_get('max_execution_time').' '._SEC),
        getMonitorRow('POST', (string)ini_get('post_max_size')),
        getMonitorRow(_MONITOR_FILE, (string)ini_get('upload_max_filesize')),
        getMonitorFlag(_MONITOR_UPLOADS, (bool)ini_get('file_uploads')),
        getMonitorRow(_MONITOR_MAXFILES, (string)ini_get('max_file_uploads')),
        getMonitorRow(_MONITOR_MAXVARS, (string)ini_get('max_input_vars')),
    ])]);
    $upbar = getTplMeter(_MONITOR_UPLOAD, $upmax, $postmax, $upnote, ($upmax > $postmax) ? 'warn' : 'ok');
    $site = $pane(['title' => _SITE, 'content_html' => $upbar.$list(array_merge([
        getMonitorFlag(_SITE, !($conf['close'] ?? 0)),
        getMonitorFlag(_STATISTIC, (bool)($conf['statistic']['stat'] ?? 0)),
        getMonitorFlag(_NEWSLETTER, intval($conf['scheduler']['jobs']['newsletter']['active'] ?? 0) === 1),
        getMonitorFlag(_MONITOR_CACHE, (bool)($conf['cache'] ?? 0)),
    ], ($stats['cntnews'] !== null) ? [getMonitorRow(_NEWS, filterDecimal($stats['cntnews'], 0))] : [],
        ($stats['cntfile'] !== null) ? [getMonitorRow(_FILES, filterDecimal($stats['cntfile'], 0))] : []))]);
    $ground = $cockpit.$traffic
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $server, 'side_html' => $soft.$opcache])
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $base, 'side_html' => $guard.$folders])
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $drive.$limits.$site])
        .$pane(['title' => _MONITOR_EXTS, 'icon' => 'puzzle', 'content_html' => getMonitorExts()]);
    return $tpl->getHtmlPart('monitor-cockpit', [
        'is_page' => true,
        'url' => $afile.'.php?name=monitor&op=live',
        'every' => $every,
        'view' => (string)json_encode($view, JSON_UNESCAPED_UNICODE),
        'content_html' => $tpl->getHtmlFrag('pane-ground', ['content_html' => $ground]),
    ]);
}

function monitor(): void {
    setHead();
    echo getTplAdminTabs(['ops' => ['name=monitor', 'name=monitor&op=info'], 'tabs' => [_HOME, _MANUAL]]).getMonitorGround(getMonitorSample());
    setFoot();
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=monitor', 'name=monitor&op=info'],
        'tabs' => [_HOME, _MANUAL],
    ]);
}

# The one poll of the page answers one reading as JSON; it carries X-SL-Poll, so the session is already released when the sample is retaken
function live(): void {
    header('Content-Type: application/json; charset=UTF-8');
    $snap = getMonitorSample(true);
    echo json_encode(getMonitorView($snap, getServerFacts($snap)), JSON_UNESCAPED_UNICODE);
    exit;
}

switch ($op) {
    default: monitor(); break;
    case 'info': info(); break;
    case 'live': live(); break;
}
