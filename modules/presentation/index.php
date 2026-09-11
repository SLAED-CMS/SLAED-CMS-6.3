<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

# Reads the site gallery: every thumbnail with an original, named and categorised by config/presentation.php, rated by thumbnail size and shuffled once per request
function getPresentationSites(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/sites';
    $meta = $conf['presentation']['sites'] ?? [];
    $rows = [];
    foreach (scandir($dir.'/thumb') ?: [] as $file) {
        $path = $dir.'/thumb/'.$file;
        if (str_starts_with($file, '.') || !is_file($path) || !is_file($dir.'/'.$file) || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $file)) continue;
        [$wid, $hei] = getImageBox($path);
        if ($wid < 1) continue;
        $item = $meta[$file] ?? [];
        $cat = (string)($item['cat'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/sites/thumb/'.$file,
            'w' => $wid,
            'h' => $hei,
            'name' => (string)($item['name'] ?? pathinfo($file, PATHINFO_FILENAME)),
            'cat' => defined($cat) ? constant($cat) : $cat,
            'has_cat' => $cat !== '',
            'rate' => (int)round(filesize($path) / 20),
        ];
    }
    shuffle($rows);
    foreach (array_keys($rows) as $i) $rows[$i]['num'] = $i + 1;
    return $rows;
}

# Reads the brand archive in config/presentation.php order: thumbnail, original and box per file, title and group from constants, logotypes and partner badges shown whole
function getPresentationBrand(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/brand';
    $rows = [];
    foreach ($conf['presentation']['brand'] ?? [] as $file => $item) {
        if (!is_file($dir.'/'.$file)) continue;
        $thumb = is_file($dir.'/thumb/'.$file) ? 'thumb/'.$file : $file;
        [$wid, $hei] = getImageBox($dir.'/'.$thumb);
        $title = (string)($item['title'] ?? '');
        $group = (string)($item['group'] ?? '');
        $mark = (string)($item['mark'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/brand/'.$thumb,
            'href' => 'uploads/presentation/brand/'.$file,
            'w' => $wid,
            'h' => $hei,
            'title' => (defined($title) ? constant($title) : $title).($mark !== '' ? ' · '.$mark : ''),
            'group' => defined($group) ? constant($group) : $group,
            'is_contain' => str_starts_with($file, 'logotype-') || str_starts_with($file, 'partner_'),
            'num' => count($rows) + 1,
        ];
    }
    return $rows;
}

# Reads the four principles in the order of config/presentation.php: the band image with its box, the title, the two paragraphs and the icon resolved from constants
function getPresentationDna(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/dna';
    $rows = [];
    foreach ($conf['presentation']['dna'] ?? [] as $file => $item) {
        if (!is_file($dir.'/'.$file)) continue;
        [$wid, $hei] = getImageBox($dir.'/'.$file);
        $title = (string)($item['title'] ?? '');
        $text = (string)($item['text'] ?? '');
        $more = (string)($item['more'] ?? '');
        $href = (string)($item['href'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/dna/'.$file,
            'w' => $wid,
            'h' => $hei,
            'title' => defined($title) ? constant($title) : $title,
            'text' => defined($text) ? constant($text) : $text,
            'more' => defined($more) ? constant($more) : $more,
            'icon' => (string)($item['icon'] ?? ''),
            'href' => $href,
            'has_href' => $href !== '',
        ];
    }
    return $rows;
}

# Reads the owner voices from config/presentation.php: quote, author and site as raw text, role and marks resolved from constants, initials built from the author name
function getPresentationVoices(): array {
    global $conf;
    $rows = [];
    foreach ($conf['presentation']['voices'] ?? [] as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $init = '';
        foreach (preg_split('/\s+/u', $name) ?: [] as $word) $init .= mb_substr($word, 0, 1, 'utf-8');
        $role = (string)($item['role'] ?? '');
        $marks = [];
        foreach ($item['marks'] ?? [] as $mark) {
            $label = (string)($mark['label'] ?? '');
            $marks[] = ['icon' => (string)($mark['icon'] ?? ''), 'label' => defined($label) ? constant($label) : $label];
        }
        $rows[] = [
            'text' => (string)($item['text'] ?? ''),
            'name' => $name,
            'initials' => mb_strtoupper($init, 'utf-8'),
            'site' => (string)($item['site'] ?? ''),
            'role' => defined($role) ? constant($role) : $role,
            'since' => (string)($item['since'] ?? ''),
            'tone' => (string)($item['tone'] ?? ''),
            'marks' => $marks,
        ];
    }
    return $rows;
}


# Collects every figure and caption of the page for partials/presentation.html: tables and counters first, the live request figures last, so the SQL of the sections is counted
# Every text is a constant, every number has a source named in the plan; the template receives words, urls, attribute values and flags and owns every tag and class
function getPresentationData(): array {
    global $conf, $db, $theme;
    $cnt = getSessionCounts();
    $today = getStatsToday();
    $month = getStatsDays(30);
    $week = array_slice($month, -7);
    $store = getMetricStore();
    [$dbver] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    $driver = (stripos((string)$dbver, 'mariadb') !== false) ? 'MariaDB' : 'MySQL';
    $dbnum = preg_match('/^\d+(?:\.\d+)+/', (string)$dbver, $vm) ? $vm[0] : (string)$dbver;
    $ver = explode(' ', trim((string)$conf['version']), 2);
    $cache = (int)$conf['cache'];
    $bots = ($conf['botsact']) ? $cnt['bots'] : 0;
    $guests = $cnt['all'] - $cnt['users'] - $bots;
    $human = ($cnt['all'] > 0) ? (int)round(($cnt['all'] - $bots) * 100 / $cnt['all']) : 0;
    $news = getTableCount('news', "time <= NOW() AND status != '0'");
    $pages = getTableCount('pages', "status != '0'");
    $files = getTableCount('files', "status != '0'");
    $users = getTableCount('users');
    $cats = getTableCount('categories');
    $pcats = getTableCount('categories', 'modul = :mod', ['mod' => 'pages']);
    $fcats = getTableCount('categories', 'modul = :mod', ['mod' => 'files']);
    $bcount = getTableCount('blocks', "status = '1'");
    $events = getSecurityEventCount(24);
    $failed = getFailedLoginCountHours(24);
    $failed = is_int($failed) ? $failed : 0;
    $langs = count(glob(BASE_DIR.'/lang/*.php') ?: []);
    $nsql = 'SELECT id, title, time FROM '.PREFIX_DB."_news WHERE time <= NOW() AND status != '0' ORDER BY time DESC LIMIT 1";
    [$nid, $ntitle, $ntime] = $db->getSqlRow($db->getSqlQuery($nsql));
    [$tid, $ttitle, $ttime] = $db->getSqlRow(getForumTopics('id, title, ltime', '', 1));
    $slots = ['b' => _PRES_BL_BANNER, 'l' => _PRES_BL_LEFT, 'c' => _PRES_BL_TOP, 'd' => _PRES_BL_BOTTOM, 'r' => _PRES_BL_RIGHT, 'f' => _PRES_BL_FOOTER];
    $nodes = [];
    $filled = [];
    $res = $db->getSqlQuery('SELECT title, bpos, bfile, status FROM '.PREFIX_DB.'_blocks ORDER BY status DESC, weight ASC LIMIT 8');
    while ([$title, $bpos, $bfile, $status] = $db->getSqlRow($res)) {
        if ($status) $filled[$bpos] = true;
        $nodes[] = ['icon' => getIconName('blocks'), 'pos' => $slots[$bpos] ?? $bpos, 'title' => $title, 'is_file' => $bfile !== '', 'is_on' => (bool)$status];
    }
    $cur = array_find($nodes, static fn(array $node): bool => $node['is_on'])['pos'] ?? _PRES_BL_CONTENT;
    $mods = [];
    $mon = 0;
    $mtot = 0;
    foreach ($conf['modules'] as $key => $item) {
        if ((int)($item['type'] ?? 0) !== 1) continue;
        $mtot++;
        if (!empty($item['active'])) $mon++;
        if (count($mods) >= 8 || empty($item['menu'])) continue;
        $mods[] = ['icon' => getIconName($key), 'label' => getModuleName($key), 'note' => $key, 'href' => 'index.php?name='.$key, 'is_on' => !empty($item['active'])];
    }
    $lit = [];
    foreach (['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight'] as $i => $word) $lit[$word] = !empty($mods[$i]['is_on']);
    $commits = [];
    $chlog = $conf['changelog'] ?? [];
    if (($chlog['source'] ?? '') !== 'github' || (($chlog['ghowner'] ?? '') !== '' && ($chlog['ghrepo'] ?? '') !== '')) {
        require_once BASE_DIR.'/modules/changelog/common.php';
        $log = chlogLoadCommits($conf, [], '');
        $base = ($log['source'] === 'github') ? 'https://github.com/'.$chlog['ghowner'].'/'.$chlog['ghrepo'].'/commit/' : '';
        foreach (array_slice($log['commits'], 0, 5) as $i => $row) {
            $commits[] = [
                'short' => $row['hash'], 'title' => $row['subject'], 'iso' => date('c', strtotime($row['date'])), 'date' => format_time($row['date'], _DATESTRING),
                'note' => $row['author'], 'href' => ($base !== '') ? $base.$row['fullhash'] : 'index.php?name=changelog', 'is_on' => $i === 0, 'is_ext' => $base !== '',
            ];
        }
    }
    $load = getLoadStats();
    $max = getLoadLimits();
    $share = static fn(float $val, float $top): int => ($top > 0) ? (int)min(100, max(0, round($val * 100 / $top))) : 0;
    $ring = static function (string $val, string $unit, string $label, int $part, string $size = '', bool $good = false, string $icon = ''): array {
        $tone = getPercentTone($good ? 100 - $part : $part);
        return [
            'value' => $val, 'unit' => $unit, 'label' => $label, 'part' => $part, 'icon' => $icon, 'is_sm' => $size === 'sm', 'is_xs' => $size === 'xs',
            'is_ok' => $tone === 'ok', 'is_info' => $tone === 'info', 'is_warn' => $tone === 'warn', 'is_danger' => $tone === 'danger',
        ];
    };
    $stat = static fn(string $label, string $val, string $unit = '', string $note = '', int $part = -1, string $tone = ''): array => [
        'label' => $label, 'value' => $val, 'unit' => $unit, 'note' => $note, 'part' => $part, 'has_part' => $part >= 0, 'tone' => $tone,
    ];
    $num = static fn(int|float $val): string => number_format($val, 0, '.', ' ');
    $gb = static fn(float $val): string => number_format($val / 1073741824, 1, '.', '');
    $head = static fn(int $num, string $icon, string $title, string $lead, string $state = '', string $tone = ''): array => [
        'num' => sprintf('%02d', $num), 'icon' => $icon, 'title' => $title, 'lead' => $lead, 'state' => $state, 'tone' => $tone,
    ];
    $gen = (int)round($load['gen'] * 1000);
    $gsec = number_format($load['gen'], 3, '.', '');
    $sql = number_format($load['sql'], 4, '.', '');
    $avg = number_format($load['avg'], 4, '.', '');
    $gpart = $share($load['gen'], $max['gen']);
    $qpart = $share($load['qnum'], $max['qnum']);
    $mpart = $share($load['mem'], $max['mem']);
    $spart = $share($load['avg'], $max['qtime']);
    $memmb = number_format($load['mem'] / 1048576, 2, '.', '');
    $memmax = ($max['mem'] > 0) ? '/ '.$num($max['mem'] / 1048576).' MB' : '';
    $mins = max(1, (int)((time() - strtotime('today')) / 60));
    $rate = (int)round($today['visits'] / $mins);
    $sess = $today['session'];
    $back = (int)($sess['returning'] ?? 0);
    $fresh = (int)($sess['new'] ?? 0);
    $rpart = $share($back, $back + $fresh);
    $npart = $share($fresh, $back + $fresh);
    $deep = $today['depth'];
    $dpart = $share((float)($deep['8+'] ?? 0), (float)array_sum($deep));
    $peak = max(1, max($today['hours']));
    $hours = [];
    foreach ($today['hours'] as $hits) $hours[] = ['part' => $share($hits, $peak)];
    $prev = $week[count($week) - 2]['visits'] ?? 0;
    $diff = ($prev > 0) ? (int)round(($today['visits'] - $prev) * 100 / $prev) : 0;
    $series = json_encode([
        'day' => ['labels' => range(0, 23), 'visits' => $today['hours']],
        'week' => ['labels' => array_column($week, 'date'), 'visits' => array_column($week, 'visits'), 'hosts' => array_column($week, 'hosts')],
        'month' => ['labels' => array_column($month, 'date'), 'visits' => array_column($month, 'visits'), 'hosts' => array_column($month, 'hosts')],
    ], JSON_UNESCAPED_UNICODE);
    $samp = (int)($store['sampled_at'] ?? 0);
    $hasmon = $samp > 0 && (time() - $samp) < 300;
    $hcpu = $store['sys_hist_cpu'] ?? [];
    $hram = $store['sys_hist_ram'] ?? [];
    $cpu = (int)round((float)(end($hcpu) ?: 0));
    $ram = (int)round((float)(end($hram) ?: 0));
    $disk = (int)round((float)($store['disk_pct'] ?? 0));
    $soft = $store['soft'] ?? [];
    $dsize = $gb((float)($store['disk_used'] ?? 0)).' / '.$gb((float)($store['disk_total'] ?? 0)).' GB';
    $branch = (string)($conf['presentation']['branch'] ?? '');
    $logo = 'slaed-logo-mark-gradient-blue.svg';
    $state = $cache ? _PRES_STK_ACTIVE : _PRES_STK_OFF;
    $rail = [];
    $names = [
        'workbench' => [_PRES_NAV_RHYTHM, _PRES_SUB_RHYTHM], 'system' => [_PRES_NAV_MODULES, _PRES_SUB_MODULES], 'security' => [_PRES_NAV_GUARD, _PRES_SUB_GUARD],
        'runtime' => [_PRES_NAV_SPEED, _PRES_SUB_SPEED], 'development' => [_PRES_NAV_DEV, _PRES_SUB_DEV], 'live-project' => [_PRES_NAV_STATS, _PRES_SUB_STATS],
        'pipeline' => [_PRES_NAV_ARCH, _PRES_SUB_ARCH], 'dna' => [_PRES_NAV_DNA, _PRES_SUB_DNA], 'archive' => [_PRES_NAV_HISTORY, _PRES_SUB_HISTORY],
        'showcase' => [_PRES_NAV_SITES, _PRES_SUB_SITES], 'voices' => [_PRES_NAV_VOICES, _PRES_SUB_VOICES], 'pulse' => [_PRES_NAV_PULSE, _PRES_SUB_PULSE],
    ];
    $i = 0;
    foreach ($names as $id => $pair) $rail[] = ['id' => $id, 'label' => $pair[0], 'sub' => $pair[1], 'num' => sprintf('%02d', ++$i), 'is_current' => $i === 1];
    $sites = getPresentationSites();
    $brand = getPresentationBrand();
    foreach (array_keys($brand) as $i) $brand[$i] += ['is_first' => $i === 0, 'is_hidden' => $i > 4, 'open' => _PRES_GL_OPEN, 'down' => _DOWNLOAD];
    foreach (array_keys($sites) as $i) $sites[$i]['rating'] = _RATING;
    $voices = getPresentationVoices();
    foreach (array_keys($voices) as $i) $voices[$i]['since_label'] = _PRES_VO_SINCE;
    $hero = [
        'cockpit' => _PRES_COCKPIT, 'nominal' => _PRES_NOMINAL, 'version' => $conf['version'],
        'gen_ring' => $ring((string)$gen, 'ms', _PRES_GEN, $gpart),
        'cache_ring' => $ring($state, '', _PRES_CACHE, $cache ? 100 : 0, '', true),
        'qnum_ring' => $ring((string)$load['qnum'], '/'.$max['qnum'], _PRES_QUERIES, $qpart),
        'online_ring' => $ring((string)$cnt['all'], '', _ONLINE, $human, '', true),
        'plate' => ['theme' => $theme, 'logo' => $logo, 'name' => $ver[1] ?? $ver[0], 'text' => _PRES_CONTROL],
        'eyebrow' => _PRES_EYEBROW, 'head_a' => _PRES_HEAD_A, 'head_b' => _PRES_HEAD_B, 'head_c' => _PRES_HEAD_C, 'lead' => _PRES_LEAD,
        'core' => [
            'title' => _PRES_CONTROL, 'route' => 'index.php?name='.$conf['name'], 'menu' => $mods, 'view' => _PRES_RH_VITALS, 'online' => _ONLINE,
            'metrics' => [
                ['value' => $gsec.' s', 'label' => _PRES_GEN], ['value' => (string)$load['qnum'], 'label' => _PRES_QUERIES], ['value' => (string)$cnt['all'], 'label' => _ONLINE],
            ],
            'series' => $series, 'chart' => _PRES_RH_CHART,
            'stack' => [
                ['icon' => 'filetype-php', 'name' => 'PHP '.PHP_VERSION, 'note' => _PRES_STK_RUNTIME, 'is_ok' => false],
                ['icon' => 'database', 'name' => $driver, 'note' => _PRES_STK_STORAGE, 'is_ok' => false],
                ['icon' => 'lightning-charge', 'name' => 'HTMX', 'note' => _PRES_STK_INTERACT, 'is_ok' => false],
                ['icon' => 'device-ssd', 'name' => _PRES_STK_CACHE, 'note' => $state, 'is_ok' => $cache > 0],
            ],
            'flow' => [_PRES_FLOW_REQ, _PRES_FLOW_GUARD, _PRES_FLOW_MODULE, _PRES_FLOW_TPL],
        ],
        'facts' => [
            $stat(_PRES_FACT_VER, $ver[0], '', $ver[1] ?? ''),
            $stat(_PRES_FACT_FILES, $num($files)),
            $stat(sprintf(_PRES_FACT_SINCE, '2005'), (string)((int)date('Y') - 2005), _PRES_UNIT_YEAR),
            $stat(_PRES_FACT_MIT, 'MIT'),
            $stat(_PRES_FACT_GEN, $gsec, 's'),
            $stat(_PRES_FACT_DBQ, (string)$load['qnum']),
        ],
        'ticks' => [
            ['name' => _NEWS, 'text' => _PRES_TICK_NEWS], ['name' => _FILES, 'text' => _PRES_TICK_FILES], ['name' => _USERS, 'text' => _PRES_TICK_USERS],
            ['name' => 'SEO', 'text' => _PRES_TICK_SEO], ['name' => _SECURITY, 'text' => _PRES_TICK_SEC], ['name' => _THEME, 'text' => _PRES_TICK_TPL],
        ],
        'tech_label' => _PRES_TECH,
        'tech' => [
            ['icon' => 'filetype-php', 'name' => 'PHP', 'note' => _PRES_TECH_PHP, 'tone' => 'primary'],
            ['icon' => 'database', 'name' => 'MySQL', 'note' => _PRES_TECH_DB, 'tone' => 'info'],
            ['icon' => 'database-gear', 'name' => 'MariaDB', 'note' => _PRES_TECH_DB, 'tone' => 'accent'],
            ['icon' => 'filetype-html', 'name' => 'HTML', 'note' => _PRES_TECH_HTML, 'tone' => 'warning'],
            ['icon' => 'filetype-css', 'name' => 'CSS', 'note' => _PRES_TECH_CSS, 'tone' => 'primary'],
            ['icon' => 'lightning-charge', 'name' => 'HTMX', 'note' => _PRES_TECH_HTMX, 'tone' => 'success'],
        ],
    ];
    $rhythm = [
        'head' => $head(1, getIconName('activity'), _PRES_H_RHYTHM, _PRES_L_RHYTHM),
        'volume' => $num($today['visits']), 'volume_label' => _PRES_RH_VOLUME, 'change' => ($diff >= 0 ? '+' : '').$diff.'%', 'has_change' => $prev > 0,
        'series' => $series, 'period_label' => _PRES_RH_PERIOD, 'view_label' => _PRES_RH_VIEW, 'chart_label' => _PRES_RH_CHART,
        'periods' => [
            ['key' => 'day', 'label' => _PRES_RH_DAY, 'is_on' => true], ['key' => 'week', 'label' => _PRES_RH_WEEK, 'is_on' => false],
            ['key' => 'month', 'label' => _PRES_RH_MONTH, 'is_on' => false],
        ],
        'views' => [
            ['key' => 'area', 'label' => _PRES_RH_AREA, 'icon' => 'graph-up', 'is_on' => true], ['key' => 'bar', 'label' => _PRES_RH_BARS, 'icon' => 'bar-chart', 'is_on' => false],
        ],
        'legend' => [_PRES_RH_HITS, _PRES_ST_HOSTS], 'vring' => $ring((string)$gen, 'ms', _PRES_GEN, $gpart),
        'vitals' => [
            'over' => _PRES_RH_VITALS, 'title' => _PRES_RH_FAST, 'text' => _PRES_RH_FAST_T,
            'rows' => [
                ['label' => _PRES_RH_RESP, 'value' => $gen.' ms', 'icon' => ''], ['label' => _PRES_QUERIES, 'value' => (string)$load['qnum'], 'icon' => ''],
                ['label' => _PRES_RH_GUARD, 'value' => _PRES_GD_STATE, 'icon' => 'shield-check'],
            ],
            'note' => _PRES_RH_NOTE,
        ],
    ];
    $modules = [
        'head' => $head(2, getIconName('modules'), _PRES_H_MODULES, sprintf(_PRES_L_MODULES, $mon, $mtot)),
        'theme' => $theme, 'logo' => $logo, 'mods' => $mods, 'lit' => $lit,
        'groups' => [
            ['title' => _PRES_MD_CORE, 'state' => _PRES_MD_READY, 'tags' => [
                _PRES_NAV_MODULES.' '.$mon.' / '.$mtot, _LANGUAGE.' '.$langs, _PRES_CACHE.' '.$state, _VERSION.' '.$ver[0],
            ]],
            ['title' => _PRES_MD_CONTENT, 'state' => _PRES_MD_LIVE, 'tags' => [
                _NEWS.' '.$num($news), _PAGES.' '.$num($pages), _FILES.' '.$num($files), _CATEGORIES.' '.$num($cats),
            ]],
            ['title' => _PRES_MD_CONTROL, 'state' => _PRES_MD_SECURED, 'tags' => [
                _USERS.' '.$num($users), _ONLINE.' '.$cnt['all'], _BOTS.' '.$bots, _PRES_GD_EVENTS.' '.$events,
            ]],
            ['title' => _PRESENTATION, 'state' => _PRES_MD_SEPARATE, 'tags' => [
                _THEME.' '.$theme, sprintf(_PRES_BL_ACTIVE, $bcount), _LANGUAGE.' '.$conf['language'],
            ]],
        ],
        'blocks' => [
            'over' => _PRES_BL_OVER, 'title' => _PRES_BL_TITLE, 'active' => sprintf(_PRES_BL_ACTIVE, $bcount),
            'fill' => [
                'banner' => isset($filled['b']), 'left' => isset($filled['l']), 'top' => isset($filled['c']), 'bottom' => isset($filled['d']),
                'right' => isset($filled['r']), 'footer' => isset($filled['f']),
            ],
            'slots' => [
                'banner' => _PRES_BL_BANNER, 'left' => _PRES_BL_LEFT, 'top' => _PRES_BL_TOP, 'content' => _PRES_BL_CONTENT, 'output' => _PRES_BL_OUTPUT,
                'bottom' => _PRES_BL_BOTTOM, 'right' => _PRES_BL_RIGHT, 'footer' => _PRES_BL_FOOTER,
            ],
            'nodes' => $nodes, 'router' => _PRES_BL_POS, 'router_note' => _PRES_BL_ROUTER, 'layout' => _PRES_BL_LAYOUT, 'current' => $cur,
            'rules' => [
                ['icon' => 'eye', 'title' => _PRES_BL_VIS, 'note' => _PRES_BL_VIS_T], ['icon' => 'sort-down', 'title' => _PRES_BL_ORDER, 'note' => _PRES_BL_ORDER_T],
                ['icon' => 'toggle-on', 'title' => _STATUS, 'note' => _PRES_BL_STATUS_T], ['icon' => 'file-earmark-code', 'title' => _PRES_BL_SOURCE, 'note' => _PRES_BL_SOURCE_T],
            ],
        ],
    ];
    $guard = [
        'head' => $head(3, getIconName('security'), _PRES_H_GUARD, _PRES_L_GUARD, _PRES_GD_ON, 'success'),
        'zones' => [['num' => '01', 'label' => _PRES_GD_INGRESS], ['num' => '02', 'label' => _PRES_GD_FILTER], ['num' => '03', 'label' => _PRES_GD_TRUST]],
        'rate' => (string)$rate, 'rate_label' => _PRES_GD_RATE, 'policy' => _PRES_GD_POLICY, 'policy_text' => _PRES_GD_POLICY_T,
        'trusted' => _PRES_GD_TRUSTED, 'trusted_text' => _PRES_GD_RUNTIME,
        'legend' => [
            ['icon' => 'person-fill', 'label' => _PRES_GD_HUMAN, 'tone' => 'primary'], ['icon' => 'search', 'label' => _PRES_GD_SEARCH, 'tone' => 'info'],
            ['icon' => 'stars', 'label' => 'AI', 'tone' => 'accent'], ['icon' => 'robot', 'label' => _PRES_GD_BOT, 'tone' => 'muted'],
            ['icon' => 'bug-fill', 'label' => _PRES_GD_ATTACK, 'tone' => 'danger'],
        ],
        'badge' => _PRES_GD_MODE, 'net' => _PRES_GD_NET, 'allow' => _PRES_GD_ALLOWBUS, 'deny' => _PRES_GD_DENY, 'track' => _PRES_GD_TRACK, 'scan' => _PRES_GD_SCAN,
        'quarantine' => _PRES_GD_QUARANT, 'caught' => (string)$events, 'entry' => _PRES_GD_ENTRY,
        'panes' => [
            ['icon' => 'file-earmark-text', 'title' => _PRES_MD_CONTENT], ['icon' => 'search', 'title' => _SEARCH],
            ['icon' => 'stars', 'title' => _PRES_GD_PUBLIC], ['icon' => 'gear', 'title' => _PRES_GD_SYSTEM],
        ],
        'classifier' => [
            'title' => _PRES_GD_CLASS, 'text' => _PRES_GD_CLASS_T, 'state' => _PRES_GD_STATE,
            'stats' => [
                $stat(_PRES_GD_EVENTS, (string)$events, '', '', -1, $events > 0 ? 'danger' : 'success'),
                $stat(_PRES_GD_FAILED, (string)$failed, '', '', -1, $failed > 0 ? 'warning' : 'success'),
                $stat(_PRES_GD_VISITS, $num($today['visits'])),
            ],
        ],
        'types_title' => _PRES_GD_TYPES,
        'types' => [
            ['icon' => 'person-fill', 'label' => _PRES_GD_T_HUMAN, 'verdict' => _PRES_GD_ALLOW, 'tone' => 'primary'],
            ['icon' => 'search', 'label' => _PRES_GD_T_SEARCH, 'verdict' => _PRES_GD_INDEX, 'tone' => 'info'],
            ['icon' => 'stars', 'label' => _PRES_GD_T_AI, 'verdict' => _PRES_GD_CLASSIFY, 'tone' => 'accent'],
            ['icon' => 'robot', 'label' => _PRES_GD_T_BOT, 'verdict' => _PRES_GD_ALLOW, 'tone' => 'muted'],
            ['icon' => 'bug-fill', 'label' => _PRES_GD_T_EVIL, 'verdict' => _PRES_GD_BLOCK, 'tone' => 'danger'],
        ],
        'log_title' => _PRES_GD_LAST,
        'log' => [
            ['label' => _PRES_GD_LOG_A, 'result' => _PRES_GD_ALLOW, 'tone' => 'success'], ['label' => _PRES_GD_LOG_B, 'result' => _PRES_GD_INDEX, 'tone' => 'success'],
            ['label' => _PRES_GD_LOG_C, 'result' => _PRES_GD_CLASSIFY, 'tone' => 'info'], ['label' => _PRES_GD_LOG_D, 'result' => _PRES_GD_ALLOW, 'tone' => 'success'],
            ['label' => _PRES_GD_LOG_E, 'result' => _PRES_GD_BLOCK, 'tone' => 'danger'], ['label' => _PRES_GD_LOG_F, 'result' => _PRES_GD_BLOCK, 'tone' => 'danger'],
        ],
        'note' => _PRES_GD_NOTE,
    ];
    $runtime = [
        'head' => $head(4, getIconName('focus'), _PRES_H_SPEED, _PRES_L_SPEED),
        'resp' => ['title' => _PRES_SP_RESP, 'value' => $gsec, 'unit' => 's', 'series' => $series, 'chart' => _PRES_RH_CHART],
        'db' => [
            'title' => _PRES_DB,
            'rows' => [
                ['label' => _PRES_QUERIES, 'value' => (string)$load['qnum'], 'part' => $qpart, 'has_part' => true],
                ['label' => _PRES_SQL_TIME, 'value' => $sql.' s', 'part' => $spart, 'has_part' => true],
                ['label' => _PRES_SP_DRIVER, 'value' => 'PDO / '.$driver, 'part' => 0, 'has_part' => false],
                ['label' => _PRES_CACHE, 'value' => $state, 'part' => $cache ? 100 : 0, 'has_part' => true],
            ],
        ],
        'online' => [
            'title' => _ONLINE, 'value' => (string)$cnt['all'], 'unit' => _ONLINE,
            'rows' => [['label' => _BVIS, 'value' => (string)$guests], ['label' => _BOTS, 'value' => (string)$bots], ['label' => _USERS, 'value' => (string)$cnt['users']]],
        ],
        'pdo' => [
            'title' => _PRES_SP_PDO, 'state' => _PRES_SP_READY, 'driver' => $driver,
            'nodes' => [
                ['icon' => 'boxes', 'num' => '01', 'title' => _PRES_SP_N_MODULE, 'note' => ''], ['icon' => 'braces', 'num' => '02', 'title' => _PRES_DB, 'note' => _PRES_SP_N_DB],
                ['icon' => 'plug', 'num' => '03', 'title' => 'PDO', 'note' => _PRES_SP_N_PDO], ['icon' => 'server', 'num' => '04', 'title' => $driver, 'note' => _PRES_SP_N_SQL],
                ['icon' => 'table', 'num' => '05', 'title' => 'PDOStatement', 'note' => ''],
            ],
            'result' => _PRES_SP_RESULT, 'rows' => _PRES_SP_ROWS, 'epoch' => _PRES_SP_EPOCH, 'mode' => _PRES_SP_PREPARED, 'comment' => _PRES_SP_COMMENT,
            'stats' => [
                $stat(_PRES_QUERIES, (string)$load['qnum']), $stat(_PRES_SQL_TIME, $sql, 's'),
                $stat(_PRES_SP_PREPARES, _PRES_SP_NATIVE, '', '', -1, 'success'), $stat(_PRES_SP_ERRMODE, 'EXCEPTION', '', '', -1, 'success'),
            ],
            'trace' => [['state' => _PRES_SP_PREPARED], ['state' => _PRES_EV_COMPLETE], ['state' => _PRES_SP_ROWS]],
        ],
        'devtools' => [
            'over' => _PRES_DT_OVER, 'title' => _PRES_DT_TITLE,
            'tabs' => [
                ['label' => _PRES_DT_PERF, 'is_on' => true], ['label' => _PRES_DT_ERRORS, 'is_on' => false],
                ['label' => _PRES_DT_REQUEST, 'is_on' => false], ['label' => _PRES_DT_SQL, 'is_on' => false],
            ],
            'perf' => [
                $stat(_PRES_DT_MEMORY, $memmb, 'MB', $memmax, $mpart), $stat(_PRES_FACT_GEN, $gsec, 's', _PRES_STK_RUNTIME, $gpart),
                $stat(_PRES_FACT_DBQ, (string)$load['qnum'], '', 'PDO', $qpart), $stat(_PRES_SQL_TIME, $sql, 's', _PRES_DT_AVG.' '.$avg, $spart),
            ],
            'note' => _PRES_DT_NOTE, 'profile' => _PRES_DT_PROFILE, 'errhead' => _PRES_DT_ERRHEAD, 'captured' => _PRES_DT_CAPTURED, 'phperr' => _PRES_DT_PHPERR,
            'demo' => _PRES_DT_DEMO, 'masked' => _PRES_DT_MASKED, 'route' => $conf['name'],
        ],
        'events' => [
            ['name' => 'request.filter', 'verdict' => _PRES_EV_ALLOWED, 'tone' => 'success'], ['name' => 'session.verify', 'verdict' => _PRES_EV_VERIFIED, 'tone' => 'success'],
            ['name' => 'query.analyze', 'verdict' => _PRES_EV_REVIEW, 'tone' => 'warning'], ['name' => 'cache.refresh', 'verdict' => _PRES_EV_COMPLETE, 'tone' => 'success'],
            ['name' => 'template.render', 'verdict' => _PRES_EV_COMPLETE, 'tone' => 'success'],
        ],
    ];
    $dev = [
        'head' => $head(5, getIconName('changelog'), _PRES_H_DEV, _PRES_L_DEV),
        'version' => 'SLAED '.$conf['version'].($branch !== '' ? ' · '.$branch : ''), 'live' => _PRES_DV_ACTIVE,
        'commits' => $commits, 'has_commits' => $commits !== [], 'none' => _PRES_DV_NONE,
        'focus' => _PRES_DV_FOCUS, 'focus_state' => 'HEAD', 'first' => $commits[0] ?? [],
        'focus_tags' => ($commits !== []) ? [$commits[0]['note'], $commits[0]['date']] : [],
        'pipe' => _PRES_DV_PIPE, 'pipe_note' => _PRES_DV_PIPE_T,
        'steps' => [
            ['icon' => 'code-slash', 'label' => _PRES_DV_CODE], ['icon' => 'check2-square', 'label' => _PRES_DV_TEST], ['icon' => 'git', 'label' => _PRES_DV_COMMIT],
            ['icon' => 'journal-code', 'label' => _CHANGELOG], ['icon' => 'box-seam', 'label' => _PRES_DV_RELEASE],
        ],
        'foot' => [
            $stat($branch !== '' ? _PRES_DV_BRANCH : _PRES_DV_COMMIT, $branch !== '' ? $branch : ($commits[0]['short'] ?? _NO)),
            $stat(_PRES_DV_LAST, $commits[0]['date'] ?? _NO, '', '', -1, 'success'),
        ],
    ];
    $stats = [
        'head' => $head(6, getIconName('statistic'), _PRES_H_STATS, _PRES_L_STATS),
        'over' => _PRES_ST_OVER, 'today' => sprintf(_PRES_ST_TODAY, $today['date']), 'has_today' => $today['date'] !== '',
        'board' => [
            $stat(_PRES_ST_VISITS, $num($today['visits']), '', $today['hosts'].' '._PRES_ST_HOSTS), $stat(_PRES_ST_HOME, $num($today['home']), '', _PRES_ST_ENTRIES),
            $stat(_PRES_ST_RETURN, (string)$rpart, '%', _PRES_ST_RETURN_T, -1, 'success'), $stat(_PRES_ST_HUMAN, (string)$human, '%', _ONLINE_NOW, -1, 'success'),
            $stat(_PRES_ST_TOTAL, $num($today['total']), '', _PRES_ST_TOTAL_T),
        ],
        'hours_title' => _PRES_ST_HOURS, 'hours_note' => _PRES_ST_MODULE, 'hours' => $hours,
        'claim' => _PRES_ST_CLAIM, 'claim_title' => _PRES_ST_CLAIM_T, 'claim_text' => _PRES_ST_CLAIM_P, 'claim_tags' => [_PRES_NAV_STATS, _PRES_STK_RUNTIME, _BOTS, 'HTMX'],
        'rings' => [
            $ring((string)$rpart, '%', _PRES_ST_RETURN, $rpart, 'sm', true), $ring((string)$npart, '%', _PRES_ST_NEW, $npart, 'sm', true),
            $ring((string)$dpart, '%', _PRES_ST_DEPTH, $dpart, 'sm', true),
        ],
    ];
    $pipeline = [
        'head' => $head(7, getIconName('system'), _PRES_H_ARCH, _PRES_L_ARCH, $cache ? _PRES_AR_ON : _PRES_AR_OFF, $cache ? 'success' : 'warning'),
        'mode' => $cache ? 'hit' : 'off', 'route' => 'GET /'.($cache === 1 ? 'index.php?name=news' : '').' · '._PRES_AR_S_GUEST,
        'decision' => $cache ? 'HIT' : 'BYPASS', 'is_on' => $cache > 0, 'theme' => $theme, 'logo' => $logo, 'cache' => _PRES_AR_CACHE,
        'cache_note' => $cache ? _PRES_AR_DYNAMIC : _PRES_AR_REBUILD, 'cache_state' => $cache ? _PRES_AR_WARM : _PRES_AR_MODE_OFF, 'chip' => _PRES_AR_DYNAMIC,
        'nodes' => [
            ['icon' => 'box-arrow-in-down', 'num' => '01', 'title' => _PRES_AR_N_REQ, 'a' => 'HTTP GET', 'b' => _PRES_AR_S_GUEST],
            ['icon' => 'shield-check', 'num' => '02', 'title' => _PRES_AR_N_GUARD, 'a' => _PRES_AR_S_SEC, 'b' => _PRES_AR_S_ALLOW],
            ['icon' => 'device-ssd', 'num' => '03', 'title' => _PRES_AR_N_GATE, 'a' => _PRES_AR_S_LOOKUP, 'b' => $cache ? 'HIT' : 'BYPASS'],
            ['icon' => 'cpu', 'num' => '04', 'title' => _PRES_AR_N_KERNEL, 'a' => _PRES_AR_S_ROUTE, 'b' => _PRES_AR_S_LIVE],
            ['icon' => 'boxes', 'num' => '05', 'title' => _PRES_AR_N_MODULE, 'a' => _PRES_AR_S_CONTENT, 'b' => _PRES_AR_S_WARM],
            ['icon' => 'layout-text-window-reverse', 'num' => '06', 'title' => _PRES_AR_N_TPL, 'a' => _PRES_AR_S_RENDER, 'b' => 'HTML'],
            ['icon' => 'box-arrow-up-right', 'num' => '07', 'title' => _PRES_AR_N_RESP, 'a' => _PRES_AR_S_DYNAMIC, 'b' => _PRES_AR_S_SERVE],
        ],
        'tree' => _PRES_AR_TREE, 'tree_mode' => 'cache = '.$cache,
        'cases' => [
            ['name' => 'HIT', 'text' => _PRES_AR_HIT_T, 'is_on' => $cache > 0, 'tone' => 'success'],
            ['name' => 'MISS', 'text' => _PRES_AR_MISS_T, 'is_on' => false, 'tone' => 'warning'],
            ['name' => 'BYPASS', 'text' => _PRES_AR_BYPASS_T, 'is_on' => $cache === 0, 'tone' => 'info'],
        ],
        'states' => [
            $stat(_PRES_AR_CACHE, [_PRES_AR_MODE_OFF, _PRES_AR_MODE_ALL, _PRES_AR_MODE_HOME][$cache] ?? _PRES_AR_MODE_OFF, '', '', -1, $cache ? 'success' : 'warning'),
            $stat(_PRES_AR_PARSER, _PRES_AR_WARM, '', '', -1, 'success'), $stat(_PRES_AR_REGIONS, _PRES_AR_LIVE, '', '', -1, 'success'), $stat(_PRES_AR_LOCK, _PRES_AR_ACQUIRED),
        ],
        'invalid' => _PRES_AR_INVALID, 'epoch' => sprintf(_PRES_AR_EPOCH, Cache::getEpoch()), 'epoch_note' => _PRES_AR_EPOCH_T,
        'ttl' => sprintf(_PRES_AR_TTL, (int)round((int)$conf['cache_t'] / 60), (int)$conf['cache_b']), 'note' => _PRES_AR_NOTE,
    ];
    $gallery = ['prev' => _PRES_GL_PREV, 'next' => _PRES_GL_NEXT, 'hint' => _PRES_GL_HINT, 'role' => _PRES_GL_ROLE, 'of' => _PRES_GL_OF];
    $pulse = [
        'head' => $head(12, getIconName('rss'), _PRES_H_PULSE, _PRES_L_PULSE),
        'cards' => [
            [
                'icon' => getIconName('news'), 'title' => _PRES_PU_NEWS, 'over' => _PRES_PU_NEWS_S, 'when' => $ntime ? format_time($ntime, _DATESTRING) : '',
                'heading' => $ntitle ?: _PRES_PU_NONE, 'text' => _PRES_PU_NEWS_P, 'link' => _PRES_PU_NEWS_A, 'tone' => 'primary', 'has_date' => (bool)$ntime,
                'href' => $nid ? getSeoUrl(['name' => 'news', 'op' => 'view', 'id' => $nid, 'title' => $ntitle]) : 'index.php?name=news',
            ],
            [
                'icon' => getIconName('forum'), 'title' => _PRES_PU_FORUM, 'over' => _PRES_PU_FORUM_S, 'when' => $ttime ? format_time($ttime, _DATESTRING) : '',
                'heading' => $ttitle ?: _PRES_PU_NONE, 'text' => _PRES_PU_FORUM_P, 'link' => _PRES_PU_FORUM_A, 'tone' => 'accent', 'has_date' => (bool)$ttime,
                'href' => $tid ? 'index.php?name=forum&op=view&id='.$tid : 'index.php?name=forum',
            ],
            [
                'icon' => getIconName('pages'), 'title' => _PRES_PU_PAGES, 'over' => _PRES_PU_PAGES_S, 'when' => _PRES_PU_PAGES_T,
                'heading' => sprintf(_PRES_PU_PAGES_N, $pages, $pcats), 'text' => _PRES_PU_PAGES_P, 'link' => _PRES_PU_PAGES_A,
                'tone' => 'success', 'href' => 'index.php?name=pages', 'has_date' => false,
            ],
            [
                'icon' => getIconName('files'), 'title' => _PRES_PU_FILES, 'over' => _PRES_PU_FILES_S, 'when' => _PRES_PU_FILES_T,
                'heading' => sprintf(_PRES_PU_FILES_N, $files, $fcats), 'text' => _PRES_PU_FILES_P, 'link' => _PRES_PU_FILES_A,
                'tone' => 'warning', 'href' => 'index.php?name=files', 'has_date' => false,
            ],
        ],
        'monitor' => [
            'over' => _PRES_PU_MON_S, 'title' => _PRES_PU_MON, 'text' => _PRES_PU_MON_P, 'has_monitor' => $hasmon, 'off' => _PRES_PU_MON_OFF,
            'minis' => [
                $ring('', '', 'CPU', $cpu, 'xs', false, 'cpu') + ['text' => $cpu.'% · '.sprintf(_PRES_PU_CORES, (int)($store['cores'] ?? 0))],
                $ring('', '', 'RAM', $ram, 'xs', false, 'memory') + ['text' => $ram.'% · '._PRES_PU_UPTIME.' '.($store['uptime'] ?? '')],
                $ring('', '', 'DISK', $disk, 'xs', false, 'device-hdd') + ['text' => $dsize],
            ],
            'soft' => [
                ['icon' => 'window', 'text' => 'SLAED '.$conf['version']], ['icon' => 'filetype-php', 'text' => 'PHP '.($soft['php'] ?? PHP_VERSION)],
                ['icon' => 'database', 'text' => $driver.' '.$dbnum], ['icon' => 'server', 'text' => trim(($soft['name'] ?? '').' '.($soft['version'] ?? ''))],
            ],
        ],
    ];
    return [
        'theme' => $theme,
        'hero' => $hero,
        'rail' => ['items' => $rail, 'total' => sprintf('%02d', count($rail)), 'first' => '01', 'label' => _PRES_TITLE],
        'rhythm' => $rhythm,
        'modules' => $modules,
        'guard' => $guard,
        'runtime' => $runtime,
        'dev' => $dev,
        'stats' => $stats,
        'pipeline' => $pipeline,
        'dna' => ['head' => $head(8, 'fingerprint', _PRES_H_DNA, _PRES_L_DNA), 'items' => getPresentationDna()],
        'archive' => $gallery + [
            'head' => $head(9, getIconName('save'), _PRES_H_HISTORY, _PRES_L_HISTORY), 'title' => _PRES_H_HISTORY,
            'over' => _PRES_BR_COLLECT, 'count' => sprintf(_PRES_BR_COUNT, count($brand)), 'items' => $brand, 'last' => (string)max(count($brand) - 1, 0),
        ],
        'sites' => $gallery + [
            'head' => $head(10, getIconName('site'), _PRES_H_SITES, _PRES_L_SITES), 'title' => _PRES_H_SITES,
            'over' => _PRES_SI_REAL, 'count' => sprintf(_PRES_SI_COUNT, count($sites)), 'items' => $sites, 'last' => (string)max(count($sites) - 1, 0),
        ],
        'voices' => [
            'head' => $head(11, getIconName('comm'), _PRES_H_VOICES, _PRES_L_VOICES),
            'over' => _PRES_VO_OVER, 'pill' => _PRES_NAV_VOICES.' · '.count($voices), 'items' => $voices, 'note' => _PRES_L_VOICES, 'back' => _PRES_VO_BACK,
        ],
        'pulse' => $pulse,
    ];
}

function presentation(): void {
    global $tpl;
    setHead([
        'title' => _PRES_TITLE,
        'desc' => _PRES_DESC,
    ]);
    echo $tpl->getHtmlPart('presentation', getPresentationData());
    setFoot();
}

switch ($op) {
    default: presentation(); break;
}
