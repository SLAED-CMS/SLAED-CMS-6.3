<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getStatisticSearch(): string {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $files = [];
    $sdir = COUNTER_DIR.'/statistic';
    if (is_dir($sdir)) foreach (scandir($sdir) as $filev) $files[] = $filev;
    rsort($files);
    $sopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _NO_INFO,
        'is_selected' => !$file,
    ]);
    foreach ($files as $val) {
        if ($val != '' && preg_match('/^statistic\_(.+)\.log/', $val, $matches)) {
            $sopts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => $val,
                'label_text' => $matches[1],
                'is_selected' => $file === $val,
            ]);
        }
    }
    $sel = $tpl->getHtmlFrag('select', [
        'name_attr' => 'file',
        'options_html' => $sopts,
    ]);
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'statistic'],
        ],
        'content_html' => _STATFROM.': '.$sel.' '.$tpl->getHtmlFrag('button', [
            'submit_label' => _OK,
            'button_type' => 'submit',
        ]),
    ]);
    return $tpl->getHtmlPart('div', ['is_searchbox' => true, 'content_html' => $form]);
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

# Render extended statistics for the selected archive or current period
function getStatExtPanel(string $file): string {
    global $tpl;
    $days = COUNTER_DIR.'/days.log';
    $stat = COUNTER_DIR.'/statistic.log';
    $rows = [];
    if ($file !== '') {
        $path = COUNTER_DIR.'/statistic/'.$file;
        if (is_file($path) && is_readable($path)) {
            $rows = file($path) ?: [];
        }
    } else {
        if (is_file($days) && is_readable($days)) {
            $rows = file($days) ?: [];
            if (is_file($stat) && is_readable($stat)) {
                $tmp = file($stat);
                if ($tmp !== false) $rows = array_merge($rows, $tmp);
            }
        } elseif (is_file($stat) && is_readable($stat)) {
            $rows = file($stat) ?: [];
        }
    }
    $rows = ($rows !== false) ? $rows : [];
    $cnt = [
        8 => [],
        9 => [],
        10 => [],
        11 => [],
        12 => [],
        14 => [],
        15 => [],
        16 => [],
    ];
    $hrs = array_fill(0, 24, 0);
    $lgen = array_fill(0, 24, 0);
    $lnum = array_fill(0, 24, 0);
    $has = false;
    foreach ($rows as $line) {
        $pts = explode('|', trim((string)$line));
        if (isset($pts[8])) $has = true;
        $load = getStatLoadHours($pts);
        foreach ($load['gen'] as $h => $val) {
            $lgen[$h] += $val;
            $lnum[$h] += $load['num'][$h];
        }
        foreach ([8, 9, 10, 11, 12, 14, 15, 16] as $idx) {
            if (isset($pts[$idx]) && $pts[$idx] !== '') {
                $cur = getCounterField($pts[$idx]);
                foreach ($cur as $key => $val) {
                    $cnt[$idx][$key] = ($cnt[$idx][$key] ?? 0) + (int)$val;
                }
            }
        }
        if (isset($pts[13]) && $pts[13] !== '') {
            $tmp = array_pad(array_slice(explode(',', $pts[13]), 0, 24), 24, 0);
            foreach ($tmp as $h => $val) $hrs[$h] += (int)$val;
        }
    }
    if (!$has) {
        return $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('statistic-extended', ['empty_text' => 'Extended statistics not available for this archive.'])]);
    }
    $pct = static function(int $num, int $den): int {
        return $den > 0 ? (int)round($num / $den * 100) : 0;
    };
    $tot = static function(array $map): int {
        $sum = 0;
        foreach ($map as $val) $sum += (int)$val;
        return $sum;
    };
    $ring = static function(string $lab, string $sub, int $part, string $tone): array {
        $part = max(0, min(100, $part));
        return [
            'label' => $lab,
            'sub' => $sub,
            'pct' => $part,
            'dash' => number_format(round(238.76 * (1 - $part / 100), 1), 1, '.', ''),
            'tone' => $tone,
        ];
    };
    $bars = static function(string $title, array $map, string $tone, ?array $ord = null) use ($tot, $pct): array {
        $oth = (int)($map['Other'] ?? 0);
        unset($map['Other']);
        $sum = $tot($map) + $oth;
        if ($sum <= 0) return [];
        $rows = [];
        $seen = [];
        $make = static function(string $name, int $num, int $part): array {
            return ['name' => $name, 'count' => $num, 'pct' => $part];
        };
        if ($ord !== null) {
            foreach ($ord as $key) {
                if ($key === 'Other' || !array_key_exists($key, $map)) continue;
                $num = (int)$map[$key];
                $seen[$key] = true;
                if ($num <= 0) continue;
                $rows[] = $make($key, $num, $pct($num, $sum));
            }
            $rest = [];
            foreach ($map as $key => $val) {
                if (!isset($seen[$key])) $rest[$key] = $val;
            }
            arsort($rest, SORT_NUMERIC);
            foreach ($rest as $key => $val) {
                $val = (int)$val;
                if ($val <= 0) continue;
                $rows[] = $make($key, $val, $pct($val, $sum));
            }
        } else {
            arsort($map, SORT_NUMERIC);
            foreach ($map as $key => $val) {
                $val = (int)$val;
                if ($val <= 0) continue;
                $rows[] = $make($key, $val, $pct($val, $sum));
            }
        }
        if ($oth > 0) $rows[] = $make('Other', $oth, $pct($oth, $sum));
        return ['title' => $title, 'tone' => $tone, 'bars' => $rows];
    };
    $vals = $cnt[14]['new'] ?? 0;
    $ret = $cnt[14]['returning'] ?? 0;
    $hit = $vals + $ret;
    $davg = 0.0;
    if (($cnt[15]['1'] ?? 0) || ($cnt[15]['2-3'] ?? 0) || ($cnt[15]['4-7'] ?? 0) || ($cnt[15]['8+'] ?? 0)) {
        $sum = 0;
        $num = 0;
        $mid = ['1' => 1.0, '2-3' => 2.5, '4-7' => 5.5, '8+' => 10.0];
        foreach ($mid as $key => $val) {
            $cur = (int)($cnt[15][$key] ?? 0);
            $sum += $cur;
            $num += $cur * $val;
        }
        if ($sum > 0) $davg = $num / $sum;
    }
    $search = (int)($cnt[12]['search'] ?? 0);
    $reft = $tot($cnt[12]);
    $devt = $tot($cnt[10]);
    $bot = (int)($cnt[10]['bot'] ?? 0);
    $hum = max(0, $devt - $bot);
    $mob = (int)($cnt[10]['mobile'] ?? 0);
    $rings = [
        $ring('Human Traffic %', 'Humans / all devices', $pct($hum, $devt), 'success'),
        $ring('Mobile Share %', 'Mobile / human traffic', $pct($mob, $hum), 'accent'),
        $ring('Return Depth %', 'Avg depth: '.number_format($davg, 1, '.', ''), $pct($ret, $hit), 'primary'),
        $ring('Search Share %', 'Search / all refcat', $pct($search, $reft), 'warning'),
    ];
    $dims = [];
    foreach ([
        ['Browsers', $cnt[8], 'primary', null],
        ['OS', $cnt[9], 'success', null],
        ['Devices', $cnt[10], 'accent', ['desktop', 'mobile', 'tablet', 'bot']],
        ['Countries', $cnt[11], 'warning', null],
        ['Refcat', $cnt[12], 'primary', ['direct', 'search', 'social', 'referrer']],
        ['New / Returning', $cnt[14], 'success', ['new', 'returning']],
        ['Depth', $cnt[15], 'primary', ['1', '2-3', '4-7', '8+']],
        ['Duration', $cnt[16], 'warning', ['<30s', '30s-3m', '3m-15m', '15m+']],
    ] as [$name, $map, $tone, $ord]) {
        $dim = $bars($name, $map, $tone, $ord);
        if ($dim !== []) $dims[] = $dim;
    }
    $hbars = static function(array $vals): array {
        $top = max($vals);
        if ($top <= 0) return [];
        $out = [];
        for ($i = 0; $i < 24; $i++) {
            $high = (int)round(48 * $vals[$i] / $top);
            $out[] = [
                'x' => $i * 11 + 1,
                'y' => 48 - $high,
                'height' => $high,
                'is_tick' => $i % 3 === 0,
                'tickx' => $i * 11 + 5,
                'label' => (string)$i,
            ];
        }
        return $out;
    };
    $gavg = array_map(static fn(int $gen, int $num): float => $num ? $gen / $num : 0.0, $lgen, $lnum);
    $charts = array_values(array_filter([
        ['title' => 'Hits by hour (total)', 'hours' => $hbars($hrs)],
        ['title' => 'Average generation by hour (ms)', 'hours' => $hbars($gavg)],
    ], static fn(array $one): bool => $one['hours'] !== []));
    return $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('statistic-extended', [
        'rings' => $rings,
        'dims' => $dims,
        'charts' => $charts,
    ])]);
}

function statistic(): void {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&file='.$file : '';
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&op=config', 'name=statistic&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    $statv = $tpl->getHtmlFrag('image-preview', [
        'alt_text' => _STATGR,
        'image_id' => 'statistic-chart-main',
        'src_attr' => $afile.'.php?name=statistic'.($file ? '&file='.$file : '').'&op=add&day=15',
    ]);
    if ($file || date('d') > 15) {
        if ($file) {
            $path = COUNTER_DIR.'/statistic/'.$file;
            $temp = (is_file($path) && is_readable($path)) ? file($path) : [];
            $out = ($temp !== false) ? count($temp) : 0;
        } else {
            $out = date('d');
        }
        $statv .= $tpl->getHtmlFrag('image-preview', [
            'alt_text' => _STATGR,
            'image_id' => 'statistic-chart-extra',
            'src_attr' => $afile.'.php?name=statistic'.($file ? '&file='.$file : '').'&op=add&day='.$out,
        ]);
    }
    $head = [
        ['content' => _DATE],
        ['content' => _UNIQUE],
        ['content' => _HITS],
        ['content' => _HOME],
        ['content' => _REFERERS],
        ['content' => _BOTSOPT],
        ['content' => _AUDIENCE],
        ['content' => _USERS],
        ['content' => _GENERATION],
        ['content' => _ADM_QUERIES],
        ['content' => _ADM_PHPMEM],
    ];
    $avg = static fn(int $num, int $gen, int $sql, int $mem): array => $num ? [round($gen / $num).' ms', (string)round($sql / $num, 1), round($mem / 1024 / $num, 1).' MB']
        : ['—', '—', '—'];
    $tnum = $tgen = $tsql = $tmem = 0;
    $rows = '';
    $daysLog = COUNTER_DIR.'/days.log';
    $statLog = COUNTER_DIR.'/statistic.log';
    if ($file) {
        $path = COUNTER_DIR.'/statistic/'.$file;
        $f = (is_file($path) && is_readable($path)) ? file($path) : [];
    } else {
        if (file_exists($daysLog) && is_readable($daysLog)) {
            $f = file($daysLog);
            $stat = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : false;
            if ($stat !== false) $f = array_merge($f, $stat);
        } else {
            $f = (file_exists($statLog) && is_readable($statLog)) ? file($statLog) : [];
        }
    }
    $f = ($f !== false) ? $f : [];
    $to = count($f);
    $unique = $today = $engines = $sites = $homepage = $auditory = $regusers = 0;
    for ($i = 0; $i < $to; $i++) {
        $out = explode('|', $f[$i]);
        $unique += $out[1];
        $today += $out[2];
        $engines += $out[4];
        $sites += $out[5];
        $homepage += $out[6];
        $out_aud = $out[1] - ($out[4] + $out[5]);
        $auditory += $out_aud;
        if ($auditory < 0) $auditory = 0;
        $regusers += rtrim($out[7]);
        $load = getStatLoadHours($out);
        $day = [array_sum($load['num']), array_sum($load['gen']), array_sum($load['sql']), array_sum($load['mem'])];
        $tnum += $day[0];
        $tgen += $day[1];
        $tsql += $day[2];
        $tmem += $day[3];
        [$dgen, $dsql, $dmem] = $avg(...$day);
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $out[0]],
                ['content_html' => $out[1]],
                ['content_html' => $out[2]],
                ['content_html' => $out[6]],
                ['content_html' => $out[5]],
                ['content_html' => $out[4]],
                ['content_html' => (string)$out_aud],
                ['content_html' => rtrim($out[7])],
                ['content_html' => $dgen],
                ['content_html' => $dsql],
                ['content_html' => $dmem],
            ],
        ])]);
    }
    [$agen, $asql, $amem] = $avg($tnum, $tgen, $tsql, $tmem);
    $rows .= $tpl->getHtmlFrag('table-row', [
        'row_attr' => 'data-sort-method="none"',
        'cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => _ALL])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$unique])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$today])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$homepage])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$sites])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$engines])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$auditory])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => (string)$regusers])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => $agen])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => $asql])],
                ['content_html' => $tpl->getHtmlFrag('span', ['is_bold' => true, 'text' => $amem])],
            ],
        ]),
    ]);
    $statv .= $tpl->getHtmlFrag('table', [
        'is_fixed' => true,
        'is_wrapless' => true,
        'head' => $head,
        'rows_html' => $rows,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $statv]);
    echo getStatExtPanel($file);
    setFoot();
}

function add(): void {
    getStatistic();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&op=config', 'name=statistic&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'tab' => 1,
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $rows = [
        [
            'label_for' => 'f-bet',
            'label_html' => _STATBET,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'bet',
                'input_id' => 'f-bet',
                'value_attr' => (string)$conf['statistic']['bet'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-shi',
            'label_html' => _STATSHI,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'shi',
                'input_id' => 'f-shi',
                'value_attr' => (string)$conf['statistic']['shi'],
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _STATACT,
            'label_id' => $labid = getFieldIds('', 'stat')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'stat',
                'value' => (string)(int)$conf['statistic']['stat'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'statistic'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('statistic')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('statistic');
    if (!$warn) {
        $cont = [
            'bet' => getVar('post', 'bet', 'num', 42),
            'shi' => getVar('post', 'shi', 'num', 22),
            'stat' => getVar('post', 'stat', 'num')
        ];
        setConfigFile('statistic.php', $cont);
    }
    setRedirect($afile.'.php?name=statistic&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=statistic', 'name=statistic&op=config', 'name=statistic&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'subtitle_html' => getStatisticSearch(),
    ]);
}

switch ($op) {
    default: statistic(); break;
    case 'add': add(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
