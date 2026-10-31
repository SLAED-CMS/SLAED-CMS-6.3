<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The name of a month in the words of the language
function getStatMonthName(string $key): string {
    return (explode(',', _MONTHS)[(int)substr($key, 5, 2) - 1] ?? '').' '.substr($key, 0, 4);
}

# The types of the robots with their words; their order is the order of the fields and of the search for a mask
function getStatBotTypes(): array {
    return ['ai' => _STATISTIC_AI, 'search' => _SEARCH, 'seo' => _SEO, 'social' => _STATISTIC_SOCIAL, 'other' => _STATISTIC_OTHER];
}

# The address of a month; the running one has none but the page itself
function getStatUrl(string $month): string {
    global $afile;
    return $afile.'.php?name=statistic'.(($month !== date('Y-m')) ? '&month='.$month : '');
}

function getStatisticSearch(): string {
    global $afile, $tpl;
    $month = getVar('get', 'month', 'text');
    $keys = array_reverse(array_keys(getStatArchive()));
    $opts = [['value_attr' => '', 'label_text' => _STATISTIC_CURRENT, 'is_selected' => !in_array($month, $keys, true)]];
    foreach ($keys as $key) $opts[] = ['value_attr' => $key, 'label_text' => getStatMonthName($key), 'is_selected' => $month === $key];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _STATFROM,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'statistic']],
        'fields' => [['field_id' => 'f-month', 'label' => _STATFROM, 'name_attr' => 'month', 'is_now' => true, 'options' => $opts]],
    ]);
}

# The rows of a dimension: by its fixed order, then by count, the overflow of the counter last; past $top the tail joins the overflow
function getStatRowList(array $map, array $ord, array $names, int $top = 0): array {
    $oth = (int)($map['Other'] ?? 0);
    unset($map['Other']);
    $sum = array_sum($map) + $oth;
    if ($sum <= 0) return [];
    arsort($map, SORT_NUMERIC);
    $keys = array_map('strval', array_keys($map));
    $rows = [];
    foreach (array_merge(array_intersect($ord, $keys), array_diff($keys, $ord)) as $key) {
        if ($map[$key] > 0) $rows[] = ['key' => $key, 'name' => $names[$key] ?? $key, 'num' => (int)$map[$key]];
    }
    if ($top && count($rows) + (($oth > 0) ? 1 : 0) > $top) {
        $oth += array_sum(array_column(array_slice($rows, $top - 1), 'num'));
        $rows = array_slice($rows, 0, $top - 1);
    }
    if ($oth > 0) $rows[] = ['key' => 'Other', 'name' => _STATISTIC_OTHER, 'num' => $oth];
    return array_map(static fn(array $row): array => $row + ['text' => filterDecimal($row['num'], 0), 'pct' => (int)round($row['num'] / $sum * 100)], $rows);
}

# The part of bar rows of one dimension; the flag of a country comes from the theme and its name from intl, which is only recommended
function getStatBars(array $map, array $ord = [], array $names = [], int $top = 0, bool $land = false): array {
    $rows = getStatRowList($map, $ord, $names, $top);
    if ($land) $rows = array_map(static function (array $row): array {
        if ($row['key'] === 'Other') return $row;
        $path = getThemeFilePath('images/flags/'.strtolower($row['key']).'.svg');
        $name = class_exists('Locale') ? (string)Locale::getDisplayRegion('-'.$row['key'], _LOCALE) : '';
        return ['name' => ($name !== '') ? $name : strtoupper($row['key']), 'flag' => is_file(PUBLIC_DIR.'/'.$path) ? HOME_PATH.$path : ''] + $row;
    }, $rows);
    return ['rows' => $rows, 'empty' => _NO_INFO];
}

function getStatStack(string $label, array $map, array $ord, array $names): array {
    return ['label' => $label, 'count' => _STATISTIC_COUNT, 'parts' => getStatRowList($map, $ord, $names), 'empty' => _NO_INFO];
}

# The change against the same days of the month before; a move inside one per cent reads as flat
function getStatDelta(?array $then, array $sum, string $key): array {
    if ($then === null || !$then[$key]) return ['delta' => _NO_INFO, 'dir' => 'none', 'arrow' => 'dash', 'delta_tip' => _STATISTIC_VS];
    $num = (int)round(($sum[$key] - $then[$key]) / $then[$key] * 100);
    $dir = ($num > 1) ? 'up' : (($num < -1) ? 'down' : 'flat');
    $arrow = ['up' => 'arrow-up-right', 'down' => 'arrow-down-right', 'flat' => 'arrow-right'][$dir];
    return ['delta' => (($num > 0) ? '+' : (($num < 0) ? '−' : '')).abs($num).'%', 'dir' => $dir, 'arrow' => $arrow, 'delta_tip' => _STATISTIC_VS];
}

# The day of a row as the tips name it: its date of the month and the short word of its weekday
function getStatDayCap(array $row): string {
    return substr($row['date'], 0, 5).', '.(explode(',', _STATISTIC_WDAYS)[$row['week']] ?? '');
}

# The average of a load field by hour, for the pages that carried a load
function getStatHourAvg(?array $day, string $key): array {
    $none = array_fill(0, 24, 0);
    return array_map(static fn(int $val, int $num): int => $num ? (int)round($val / $num) : 0, $day[$key] ?? $none, $day['num'] ?? $none);
}

# A period of 7 or 30 days of the rhythm: the hits, hosts and crawlers of the days up to the day of the scope
function getStatRhythmSpan(string $key, string $name, array $list, string $delta): array {
    $num = count($list);
    return [
        'key' => $key,
        'name' => $name,
        'figure' => filterDecimal(array_sum(array_column($list, 'h')), 0),
        'note' => _STATISTIC_INSPAN,
        'delta' => $delta,
        'labels' => array_map('getStatDayCap', $list),
        'ticks' => array_map(static fn(array $row, int $i): string => ($num <= 7 || $i % 5 === 0) ? substr($row['date'], 0, 5) : '', $list, array_keys($list)),
        'series' => [
            ['key' => 'hits', 'name' => _HITS, 'tone' => 'primary', 'values' => array_column($list, 'h')],
            ['key' => 'u', 'name' => _UNIQUE, 'tone' => 'accent', 'values' => array_column($list, 'u')],
            ['key' => 'bot', 'name' => _BOTSOPT, 'tone' => 'success', 'values' => array_column($list, 'bot')],
        ],
    ];
}

# The rhythm: 24 hours are the hits and the average page of the day of the scope, the other periods its days
function getStatRhythm(array $data): array {
    $day = $data['day'];
    $hits = $day['hours'] ?? array_fill(0, 24, 0);
    $rows = [['key' => 'hits', 'name' => _HITS, 'tone' => 'primary', 'values' => $hits]];
    if (array_sum($day['num'] ?? [])) {
        $rows[] = ['key' => 'gen', 'name' => _GENERATION.', ms', 'tone' => 'accent', 'values' => getStatHourAvg($day, 'gen')];
        $rows[] = ['key' => 'sql', 'name' => _STATISTIC_SQLPAGE, 'tone' => 'success', 'values' => getStatHourAvg($day, 'sql')];
    }
    $change = static fn(int $was, int $now): string => $was ? (($now >= $was) ? '+' : '−').abs((int)round(($now - $was) / $was * 100)).'%' : '';
    $week = array_slice($data['run'], -7);
    $older = (count($data['run']) >= 14) ? array_slice($data['run'], -14, 7) : [];
    $hour = range(0, 23);
    return [
        'label' => _STATISTIC_HOURS,
        'period_label' => _STATISTIC_SPAN,
        'view_label' => _STATISTIC_VIEW,
        'views' => [['key' => 'area', 'name' => _STATISTIC_AREA, 'icon' => 'graph-up'], ['key' => 'bar', 'name' => _STATISTIC_BARS, 'icon' => 'bar-chart']],
        'periods' => [
            [
                'key' => 'day',
                'name' => _STATISTIC_H24,
                'figure' => filterDecimal($day['h'] ?? 0, 0),
                'note' => _STATISTIC_INSPAN,
                'delta' => $change($data['past']['h'] ?? 0, $day['h'] ?? 0),
                'labels' => array_map(static fn(int $one): string => sprintf('%02d:00–%02d:00', $one, ($one + 1) % 24), $hour),
                'ticks' => array_map(static fn(int $one): string => ($one % 3 === 0) ? sprintf('%02d', $one) : '', $hour),
                'series' => $rows,
            ],
            getStatRhythmSpan('week', _STATISTIC_D7, $week, (count($older) === 7) ? $change(array_sum(array_column($older, 'h')), array_sum(array_column($week, 'h'))) : ''),
            getStatRhythmSpan('month', _STATISTIC_D30, $data['run'], ''),
        ],
    ];
}

# The dial of the day: its hits and its average generation by hour, the peak in the middle, on the running day the hour that runs
function getStatClock(array $data): array {
    $day = $data['day'];
    $hits = $day['hours'] ?? array_fill(0, 24, 0);
    $peak = (int)array_search(max($hits), $hits, true);
    return [
        'label' => _STATISTIC_DIAL,
        'hours' => $hits,
        'gen' => getStatHourAvg($day, 'gen'),
        'peak' => array_sum($hits) ? $peak : -1,
        'peak_label' => _STATISTIC_PEAK,
        'peak_text' => array_sum($hits) ? sprintf('%02d:00', $peak) : '—',
        'total' => filterDecimal($day['h'] ?? 0, 0),
        'hits_word' => _STATISTIC_ENTRY,
        'now' => ($data['is_live'] && $data['at'] === count($data['rows']) - 1) ? (int)date('G') : -1,
    ];
}

# The log of days: the columns of the old table, the total first, the largest of a column marked
function getStatTable(array $data): array {
    $load = static fn(?float $gen, ?float $sql, ?float $mem): array => [
        ($gen === null) ? '—' : filterDecimal($gen, 0).' ms',
        ($sql === null) ? '—' : filterDecimal($sql, 1),
        ($mem === null) ? '—' : filterDecimal($mem, 1).' MB',
    ];
    $whole = static fn(array $nums): array => array_map(static fn(int $val): string => filterDecimal($val, 0), $nums);
    $list = array_map(static function (array $row): array {
        $pages = array_sum($row['num']);
        return [$row['u'], $row['h'], $row['home'], $row['ref'], $row['bot'], $row['aud'], $row['usr'],
            $pages ? array_sum($row['gen']) / $pages : null, $pages ? array_sum($row['sql']) / $pages : null, $pages ? array_sum($row['mem']) / 1024 / $pages : null];
    }, $data['rows']);
    $tops = [];
    foreach (range(0, 9) as $col) $tops[$col] = $list ? max(array_column($list, $col)) : 0;
    $num = count($list);
    $sum = $data['sum'];
    return [
        'can_pick' => true,
        'pick' => $data['is_pick'] ? $data['at'] : -1,
        'cols' => [_DATE, _UNIQUE, _HITS, _HOME, _REFERERS, _BOTSOPT, _AUDIENCE, _USERS, _GENERATION, _ADM_QUERIES, _ADM_PHPMEM],
        'total' => [
            'label' => _STATISTIC_TOTAL,
            'cells' => [
                ...$whole([$sum['u'], $sum['h'], $sum['home'], $sum['ref'], $sum['bot'], $sum['aud'], $sum['usr']]),
                ...$load($sum['avg_gen'], $sum['avg_sql'], $sum['avg_mem']),
            ],
        ],
        'rows' => array_map(static fn(array $row, array $nums, int $i): array => [
            'label' => $row['date'],
            'week' => explode(',', _STATISTIC_WDAYS)[$row['week']] ?? '',
            'is_end' => $row['week'] > 4,
            'run_text' => ($data['is_live'] && $i === $num - 1) ? _STATISTIC_RUNS : '',
            'cells' => [...$whole(array_slice($nums, 0, 7)), ...$load($nums[7], $nums[8], $nums[9])],
            'tops' => array_map(static fn(int|float|null $val, int $col): bool => $num > 1 && $val !== null && $val > 0 && $val === $tops[$col], $nums, array_keys($nums)),
        ], $data['rows'], $list, array_keys($list)),
    ];
}

# The line of state and the words beside the months: the pace while the dial shows the running hour, else the run of the month, and which day the dial reads
function getStatState(array $data): array {
    $num = count($data['rows']);
    $isnow = $data['pace'] !== null && !$data['is_pick'] && $data['at'] === $num - 1;
    $out = [
        'is_now' => $isnow,
        'run' => $data['is_run'] ? sprintf(_STATISTIC_RUN, $data['rows'][$num - 1]['day'] ?? (int)date('j')).' · '.date('H:i') : sprintf(_STATISTIC_ARC, $num),
        'mode' => $data['is_pick'] ? 'day' : ($data['is_live'] ? 'run' : 'arc'),
        'can_set' => $num > 1,
        'day_text' => $data['day'] ? $data['day']['date'].', '.(explode(',', _STATISTIC_WFULL)[$data['day']['week']] ?? '') : '',
    ];
    if (!$isnow) return $out;
    $pace = $data['pace'];
    [$word, $icon] = [
        'lull' => [_ADM_LULL, 'dash-circle-fill'],
        'calm' => [_STATISTIC_CALM, 'arrow-down-right-circle-fill'],
        'day' => [_ADM_CALM, 'check-circle-fill'],
        'rise' => [_STATISTIC_RISE, 'arrow-up-right-circle-fill'],
        'rush' => [_STATISTIC_RUSH, 'lightning-charge-fill'],
    ][$pace['pace']];
    $norm = sprintf(_STATISTIC_NORM, explode(',', _STATISTIC_WDAYS)[$pace['week']] ?? '', sprintf('%02d:00', $pace['hour']));
    return $out + [
        'pace' => $pace['pace'],
        'icon' => $icon,
        'text' => $word.(in_array($pace['pace'], ['lull', 'day'], true) ? '' : ' · ×'.filterDecimal($pace['share'], 1)),
        'rows' => [
            ['icon' => 'activity', 'label' => _STATISTIC_NOW, 'value' => sprintf(_STATISTIC_PERHOUR, filterDecimal($pace['rate'], 0))],
            ['icon' => 'calendar-week', 'label' => $norm, 'value' => sprintf(_STATISTIC_PERHOUR, filterDecimal($pace['norm'], 0))],
            ['icon' => $icon, 'label' => _STATISTIC_TEMPO, 'value' => '×'.filterDecimal($pace['share'], 1)],
        ],
    ];
}

# The four rings with the rows of their panels, in the shape the cockpit of the home draws its own
function getStatGauges(array $sum): array {
    $share = $sum['shares'];
    $dev = $sum['dims']['devices'] ?? [];
    $old = $sum['dims']['newret'] ?? [];
    $ref = $sum['dims']['refcat'] ?? [];
    $row = static fn(string $icon, string $label, int $num): array => ['icon' => $icon, 'label' => $label, 'value' => filterDecimal($num, 0)];
    return [
        'human' => ['value' => $share['human'], 'tone' => 'ok', 'rows' => [
            $row('person', _STATISTIC_HUMAN, array_sum($dev) - ($dev['bot'] ?? 0)),
            $row('robot', _STATISTIC_ROBOTS, $dev['bot'] ?? 0),
            $row('eye', _STATISTIC_ALLHITS, array_sum($dev)),
        ]],
        'mobile' => ['value' => $share['mobile'], 'tone' => 'info', 'rows' => [
            $row('phone', _STATISTIC_PHONE, $dev['mobile'] ?? 0), $row('laptop', _STATISTIC_DESK, $dev['desktop'] ?? 0), $row('tablet', _STATISTIC_TABLET, $dev['tablet'] ?? 0),
        ]],
        'ret' => ['value' => $share['ret'], 'tone' => 'warn', 'rows' => [
            $row('arrow-repeat', _STATISTIC_BACK, $old['returning'] ?? 0), $row('person-plus', _NEW, $old['new'] ?? 0), $row('layers', _STATISTIC_ALLSESS, array_sum($old)),
        ]],
        'search' => ['value' => $share['search'], 'tone' => 'info', 'rows' => [
            $row('search', _SEARCH, $ref['search'] ?? 0), $row('cursor', _STATISTIC_DIRECT, $ref['direct'] ?? 0),
            $row('link-45deg', _STATISTIC_SITES, $ref['referrer'] ?? 0), $row('people', _STATISTIC_SOCIAL, $ref['social'] ?? 0), $row('stars', _STATISTIC_AI, $ref['ai'] ?? 0),
        ]],
    ];
}

# All the page shows of one reading but the archive strip, the one shape of its first draw and of its poll
function getStatView(array $data): array {
    $sum = $data['sum'];
    $dim = $sum['dims'];
    $hosts = $sum['u'];
    $last = count($data['rows']) - 1;
    $item = static fn(int|float|null $num, int $digits = 0): array => ($num === null) ? ['value' => '—', 'text' => '—']
        : ['value' => round($num, $digits), 'text' => filterDecimal($num, $digits)];
    $tile = static fn(string $label, int $num, string $note, array $more = []): array => ['label' => $label, 'value' => filterDecimal($num, 0), 'num' => $num, 'note' => $note]
        + $more;
    $devs = ['desktop' => _STATISTIC_DESK, 'mobile' => _STATISTIC_PHONE, 'tablet' => _STATISTIC_TABLET, 'bot' => _STATISTIC_BOT];
    $refs = ['direct' => _STATISTIC_DIRECT, 'search' => _SEARCH, 'social' => _STATISTIC_SOCIAL, 'ai' => _STATISTIC_AI, 'referrer' => _STATISTIC_SITES];
    $kinds = getStatBotTypes();
    $pages = ['1' => _STATISTIC_P1, '2-3' => _STATISTIC_P2, '4-7' => _STATISTIC_P4, '8+' => _STATISTIC_P8];
    $times = ['<30s' => _STATISTIC_T1, '30s-3m' => _STATISTIC_T2, '3m-15m' => _STATISTIC_T3, '15m+' => _STATISTIC_T4];
    return [
        'month' => $data['key'],
        'gauges' => getStatGauges($sum),
        'strip' => [
            'gen' => $item($sum['avg_gen']),
            'sql' => $item($sum['avg_sql'], 1),
            'mem' => $item($sum['avg_mem'], 1),
            'usr' => $item($sum['usr']),
            'all' => $item($data['all']),
        ],
        'state' => getStatState($data),
        'tiles' => [
            $tile(_UNIQUE, $hosts, sprintf(_STATISTIC_PERDAY, filterDecimal($hosts / max(1, $sum['days']), 0)), getStatDelta($data['then'], $sum, 'u')),
            $tile(_HITS, $sum['h'], sprintf(_STATISTIC_PERHOST, filterDecimal($hosts ? $sum['h'] / $hosts : 0, 1))),
            $tile(_HOME, $sum['home'], _STATISTIC_HOMEIN),
            $tile(_AUDIENCE, $sum['aud'], _STATISTIC_SELF, ['is_good' => true] + getStatDelta($data['then'], $sum, 'aud')),
        ],
        'meta' => ['sl-stat-refcat' => sprintf(_STATISTIC_INSEEK, $sum['shares']['search']), 'sl-stat-table' => sprintf(_STATISTIC_DAYN, $sum['days'])],
        'parts' => [
            'sl-stat-clock' => getStatClock($data),
            'sl-stat-days' => [
                'label' => _UNIQUE,
                'tone' => 'primary',
                'can_pick' => true,
                'pick' => $data['is_pick'] ? $data['at'] : -1,
                'days' => array_map(static fn(array $one, int $i): array => [
                    'label' => (string)$one['day'],
                    'cap' => getStatDayCap($one).(($data['is_live'] && $i === $last) ? ' · '._STATISTIC_RUNS : ''),
                    'value' => $one['u'],
                    'is_end' => $one['week'] > 4,
                    'is_run' => $data['is_live'] && $i === $last,
                    'rows' => [[_UNIQUE, filterDecimal($one['u'], 0), 'primary']],
                ], $data['rows'], array_keys($data['rows'])),
            ],
            'sl-stat-refcat' => getStatBars($dim['refcat'] ?? [], array_keys($refs), $refs),
            'sl-stat-countries' => getStatBars($dim['countries'] ?? [], [], [], 6, true),
            'sl-stat-rhythm' => getStatRhythm($data),
            'sl-stat-browsers' => getStatBars($dim['browsers'] ?? [], [], ['Bot' => _STATISTIC_BOT], 6),
            'sl-stat-os' => getStatBars($dim['os'] ?? [], [], ['Bot' => _STATISTIC_BOT]),
            'sl-stat-week' => [
                'name' => _STATISTIC_INMEAN,
                'tone' => 'primary',
                'low' => _STATISTIC_LOW,
                'high' => _STATISTIC_HIGH,
                'days' => array_map(
                    static fn(string $name, string $cap, int $i): array => ['name' => $name, 'cap' => $cap, 'is_end' => $i > 4],
                    explode(',', _STATISTIC_WDAYS),
                    explode(',', _STATISTIC_WFULL),
                    range(0, 6),
                ),
                'hours' => array_map(static fn(int $one): string => ($one % 3) ? '' : sprintf('%02d', $one), range(0, 23)),
                'spans' => array_map(static fn(int $one): string => sprintf('%02d:00–%02d:00', $one, ($one + 1) % 24), range(0, 23)),
                'cells' => $data['week'],
            ],
            'sl-stat-newret' => getStatStack(_STATISTIC_NEWRET, $dim['newret'] ?? [], ['new', 'returning'], ['new' => _NEW, 'returning' => _STATISTIC_BACK]),
            'sl-stat-depth' => getStatStack(_STATISTIC_DEPTH, $dim['depth'] ?? [], array_keys($pages), $pages),
            'sl-stat-duration' => getStatStack(_STATISTIC_TIME, $dim['duration'] ?? [], array_keys($times), $times),
            'sl-stat-devices' => getStatBars($dim['devices'] ?? [], array_keys($devs), $devs),
            'sl-stat-hosts' => getStatBars($dim['hosts'] ?? [], [], [], 10),
            'sl-stat-pages' => getStatBars($dim['pages'] ?? [], [], [], 10),
            'sl-stat-bots' => getStatBars($dim['bots'] ?? [], [], [], 10),
            'sl-stat-bottype' => getStatStack(_STATISTIC_BOTS, $dim['bottype'] ?? [], array_keys($kinds), $kinds),
            'sl-stat-table' => getStatTable($data),
        ],
    ];
}

# Every month from the first file of the archive to the running one on one strip: the height is the unique hosts of an average day, so a running month and a February read level
function getStatStrip(array $data): array {
    $arc = getStatArchive();
    $cur = date('Y-m');
    $files = count(array_column($arc, 'stamp'));
    $run = getStatSum(getStatRows($cur));
    $arc[$cur] = ['days' => $run['days'], 'u' => $run['u'], 'h' => $run['h']];
    ksort($arc);
    $first = (string)array_key_first($arc);
    $months = [];
    $seen = '';
    for ($at = mktime(0, 0, 0, (int)substr($first, 5, 2), 1, (int)substr($first, 0, 4)); date('Y-m', $at) <= $cur; $at = strtotime('+1 month', $at)) {
        $key = date('Y-m', $at);
        $year = substr($key, 0, 4);
        $name = getStatMonthName($key);
        $mark = ($year !== $seen && (int)substr($key, 5, 2) <= 6) ? $year : '';
        if ($mark !== '') $seen = $year;
        if (!isset($arc[$key])) {
            $months[] = ['name' => $name, 'year' => $mark, 'value' => 0, 'href' => '', 'is_gap' => true, 'rows' => [['statistic_'.$key.'.log', _STATISTIC_NOFILE, '']]];
            continue;
        }
        $avg = $arc[$key]['days'] ? $arc[$key]['u'] / $arc[$key]['days'] : 0;
        $months[] = [
            'name' => $name.(($key === $cur) ? ' · '._STATISTIC_RUNS : ''),
            'year' => $mark,
            'value' => round($avg, 1),
            'href' => getStatUrl($key),
            'is_run' => $key === $cur,
            'is_pick' => $key === $data['key'],
            'rows' => [
                [_STATISTIC_UDAY, filterDecimal($avg, 0), 'primary'],
                [_UNIQUE, filterDecimal($arc[$key]['u'], 0), 'primary'],
                [_HITS, filterDecimal($arc[$key]['h'], 0), 'accent'],
            ],
        ];
    }
    return [
        'meta' => getStatMonthName($first).' — '.getStatMonthName($cur).' · '.sprintf(_STATISTIC_FILES, $files),
        'part' => [
            'months' => $months,
            'keys' => [
                ['mark' => 'pick', 'text' => _STATISTIC_PICKED],
                ['mark' => 'run', 'text' => _STATISTIC_RUNS],
                ['mark' => 'gap', 'text' => _STATISTIC_NOFILE],
                ['text' => _STATISTIC_HINT, 'is_hint' => true],
            ],
        ],
    ];
}

# The cockpit of the page: the rings and the strip of the theme, the dial of the day in its lamp, the months and the live chip at its head
function getStatCockpit(array $data, array $view, string $clock, int $every): string {
    global $tpl;
    return $tpl->getHtmlPart('cockpit', [
        'cap_icon' => 'graph-up',
        'cap_text' => _STATISTIC.' · '.getStatMonthName($data['key']),
        'tone' => 'ok',
        'rings' => [
            ['key' => 'human', 'label' => _STATISTIC_HUMAN, 'title' => _STATISTIC_HUMAN],
            ['key' => 'mobile', 'label' => _STATISTIC_MOBILE, 'title' => _STATISTIC_MOBILE],
            ['key' => 'ret', 'label' => _STATISTIC_RETURN, 'title' => _STATISTIC_RETURN],
            ['key' => 'search', 'label' => _STATISTIC_SOUGHT, 'title' => _STATISTIC_SOUGHT],
        ],
        'strip' => [
            ['key' => 'gen', 'icon' => 'stopwatch', 'label' => _GENERATION, 'unit' => 'ms'],
            ['key' => 'sql', 'icon' => 'database', 'label' => _ADM_QUERIES, 'unit' => 'sql'],
            ['key' => 'mem', 'icon' => 'memory', 'label' => _ADM_PHPMEM, 'unit' => 'MB'],
            ['key' => 'usr', 'icon' => 'person-check', 'label' => _USERS, 'unit' => ''],
            ['key' => 'all', 'icon' => 'infinity', 'label' => _STATISTIC_EVER, 'unit' => ''],
        ],
        'state_html' => $tpl->getHtmlPart('statistic-cockpit', ['is_state' => true, 'is_now' => $view['state']['is_now'], 'pace_cap' => _STATISTIC_PACE]),
        'lamp_html' => $tpl->getHtmlPart('statistic-cockpit', ['is_lamp' => true, 'clock_html' => $clock]),
        'nav_html' => $tpl->getHtmlPart('statistic-cockpit', [
            'is_nav' => true,
            'set_text' => _STATISTIC_DIAL,
            'now_text' => _STATISTIC_NOW,
            'past_text' => _YESTERDAY,
            'last_text' => _STATISTIC_LAST,
            'clear_text' => _STATISTIC_UNPICK,
            'prev_text' => _STATISTIC_EARLIER,
            'next_text' => _STATISTIC_LATER,
            'prev_url' => ($data['prev'] !== '') ? getStatUrl($data['prev']) : '',
            'next_url' => ($data['next'] !== '') ? getStatUrl($data['next']) : '',
            'live_html' => $data['is_run'] ? $tpl->getHtmlFrag('live-chip', ['live_every' => $every]) : '',
        ]),
    ]);
}

# The whole ground of the page; the windows hold empty hosts with their data, which the engine of the series draws
function getStatGround(array $data): string {
    global $afile, $tpl;
    $view = getStatView($data);
    $strip = getStatStrip($data);
    $every = 10;
    $series = static fn(string $id, string $kind, array $one): string => $tpl->getHtmlFrag('series', [
        'id' => $id,
        'kind' => $kind,
        'json' => (string)json_encode($one, JSON_UNESCAPED_UNICODE),
    ]);
    $part = static fn(string $id, string $kind): string => $series($id, $kind, $view['parts'][$id]);
    $pane = static fn(array $keys): string => $tpl->getHtmlFrag('pane', $keys);
    $bars = static fn(string $title, string $id): string => $pane(['title' => $title, 'content_html' => $part($id, 'rows')]);
    $month = getStatMonthName($data['key']);
    $traffic = $pane([
        'title' => _STATISTIC_TRAFFIC,
        'icon' => 'bar-chart-line',
        'meta' => $month,
        'is_fill' => true,
        'content_html' => $tpl->getHtmlFrag('metric-list', ['items' => $view['tiles']]).$part('sl-stat-days', 'days'),
    ]);
    $sources = $pane(['title' => _STATISTIC_SOURCE, 'meta' => $view['meta']['sl-stat-refcat'], 'content_html' => $part('sl-stat-refcat', 'rows')]);
    $rhythm = $pane(['title' => _STATISTIC_HOURS, 'icon' => 'activity', 'meta' => _STATISTIC_RHYTHM, 'is_fill' => true, 'content_html' => $part('sl-stat-rhythm', 'rhythm')]);
    $week = $pane(['title' => _STATISTIC_WEEK, 'icon' => 'calendar-week', 'meta' => _STATISTIC_MEAN, 'is_fill' => true, 'content_html' => $part('sl-stat-week', 'week')]);
    $stacks = $pane(['title' => _STATISTIC_SESSION, 'content_html' => $part('sl-stat-newret', 'stack').$part('sl-stat-depth', 'stack').$part('sl-stat-duration', 'stack')]);
    $ground = getStatCockpit($data, $view, $part('sl-stat-clock', 'clock'), $every)
        .$pane(['title' => _STATISTIC_ARCHIVE, 'icon' => 'archive', 'meta' => $strip['meta'], 'content_html' => $series('sl-stat-archive', 'archive', $strip['part'])])
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $traffic, 'side_html' => $sources.$bars(_STATISTIC_LANDS, 'sl-stat-countries')])
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $rhythm, 'side_html' => $bars(_STATISTIC_BROWSE, 'sl-stat-browsers').$bars(_STATISTIC_OSES, 'sl-stat-os')])
        .$tpl->getHtmlFrag('pane-row', ['content_html' => $week, 'side_html' => $stacks.$bars(_STATISTIC_DEVICE, 'sl-stat-devices')])
        .$tpl->getHtmlFrag('pane-row', [
            'content_html' => $pane(['title' => _STATISTIC_PAGES, 'is_fill' => true, 'content_html' => $part('sl-stat-pages', 'rows')]),
            'side_html' => $bars(_STATISTIC_HOSTS, 'sl-stat-hosts'),
        ])
        .$pane(['title' => _STATISTIC_BOTS, 'content_html' => $part('sl-stat-bottype', 'stack').$part('sl-stat-bots', 'rows')])
        .$pane(['title' => _STATISTIC_LOG, 'icon' => 'journal-text', 'meta' => $view['meta']['sl-stat-table'], 'content_html' => $part('sl-stat-table', 'table')]);
    unset($view['parts']);
    return $tpl->getHtmlPart('statistic-cockpit', [
        'is_page' => true,
        'url' => $afile.'.php?name=statistic&month='.$data['key'].'&op=live',
        'month' => $data['key'],
        'every' => $every,
        'is_run' => $data['is_run'],
        'view' => (string)json_encode($view, JSON_UNESCAPED_UNICODE),
        'content_html' => $tpl->getHtmlFrag('pane-ground', ['content_html' => $ground]),
    ]);
}

function statistic(): void {
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&op=config', 'name=statistic&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    echo $cont.getStatGround(getStatData(getVar('get', 'month', 'text')));
    setFoot();
}

# The poll of the page and a pick of its day answer one reading as JSON; the day comes by its place plus one, as zero is no pick
function live(): void {
    $data = getStatData(getVar('get', 'month', 'text'), getVar('get', 'day', 'num', 0) - 1, getVar('get', 'now', 'num', 1) === 1);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(getStatView($data), JSON_UNESCAPED_UNICODE);
    exit;
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
    $list = static fn(string $key, string $label, string $hint, array $lines): array => [
        'label_for' => 'f-'.$key,
        'label_html' => $label,
        'hint_html' => $hint,
        'hint_id' => $hntid = getFieldIds('f-'.$key)['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => $key,
            'input_id' => 'f-'.$key,
            'value_text' => implode("\n", $lines),
            'cols_num' => 65,
            'rows_num' => 8,
            'is_config' => true,
        ]),
    ];
    $bots = [];
    foreach (getStatBotTypes() as $type => $word) {
        $map = $conf['statistic']['bots'][$type] ?? [];
        $lines = array_map(static fn(int|string $mask, string $name): string => $mask.(($name !== '') ? '='.$name : ''), array_keys($map), $map);
        $bots[] = $list('bot_'.$type, _STATISTIC_BOTS.': '.$word, _STATISTIC_BOTLINE, $lines);
    }
    $rows = [
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
        ...$bots,
        $list('search', _BOTSSITE, _STATISTIC_LISTS, $conf['statistic']['search'] ?? []),
        $list('social', _STATISTIC_SOCIALS, _STATISTIC_LISTS, $conf['statistic']['social'] ?? []),
        $list('ai', _STATISTIC_AIHOST, _STATISTIC_LISTS, $conf['statistic']['ai'] ?? []),
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
        $lines = static fn(string $key): array => array_values(array_unique(array_filter(array_map('trim', preg_split('#[,\r\n]+#', getVar('post', $key, 'text', ''))))));
        $bots = [];
        foreach (array_keys(getStatBotTypes()) as $type) {
            $bots[$type] = [];
            foreach ($lines('bot_'.$type) as $line) {
                [$mask, $name] = array_pad(array_map('trim', explode('=', $line, 2)), 2, '');
                if ($mask !== '') $bots[$type][$mask] = $name;
            }
        }
        $hosts = static fn(string $key): array => array_map('strtolower', $lines($key));
        $cont = [
            'stat' => getVar('post', 'stat', 'num'),
            'bots' => $bots,
            'search' => $hosts('search'),
            'social' => $hosts('social'),
            'ai' => $hosts('ai'),
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
    case 'live': live(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
