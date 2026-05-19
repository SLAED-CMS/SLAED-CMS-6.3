<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
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
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $form]);
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
    $has = false;
    foreach ($rows as $line) {
        $pts = explode('|', trim((string)$line));
        if (isset($pts[8])) $has = true;
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
        return $tpl->getHtmlPart('box', [
            'content_html' => '<p class="sl-statx-unavail">Extended statistics not available for this archive.</p>',
        ]);
    }
    $pct = static function(int $num, int $den): int {
        return $den > 0 ? (int)round($num / $den * 100) : 0;
    };
    $tot = static function(array $map): int {
        $sum = 0;
        foreach ($map as $val) $sum += (int)$val;
        return $sum;
    };
    $ring = static function(string $lab, string $sub, int $pct, string $clr): string {
        $pct = max(0, min(100, $pct));
        $off = number_format(round(238.76 * (1 - $pct / 100), 1), 1, '.', '');
        return '<div class="sl-statx-ring" style="--accent:'.$clr.'">'
            .'<svg viewBox="0 0 100 100"><circle class="sl-statx-rbg" cx="50" cy="50" r="38"></circle>'
            .'<circle class="sl-statx-rval" cx="50" cy="50" r="38" stroke-dasharray="238.76" stroke-dashoffset="'.$off.'"></circle></svg>'
            .'<strong>'.$pct.'%</strong><b>'.htmlspecialchars($lab, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</b>'
            .'<span>'.htmlspecialchars($sub, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span></div>';
    };
    $bars = static function(string $title, array $map, string $clr, ?array $ord = null) use ($tot, $pct): string {
        $oth = (int)($map['Other'] ?? 0);
        unset($map['Other']);
        $sum = $tot($map) + $oth;
        if ($sum <= 0) return '';
        $html = '<div class="sl-statx-dim" style="--accent:'.$clr.'"><h4>'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</h4>';
        $seen = [];
        $make = static function(string $name, int $cnt, int $sum, int $pct): string {
            return '<div class="sl-statx-bar"><span class="sl-statx-bl">'.htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</span>'
                .'<span class="sl-statx-bg"><span class="sl-statx-bv" style="width:'.$pct.'%"></span></span>'
                .'<span class="sl-statx-bc">'.$cnt.'</span><span class="sl-statx-bp">'.$pct.'%</span></div>';
        };
        if ($ord !== null) {
            foreach ($ord as $key) {
                if ($key === 'Other' || !array_key_exists($key, $map)) continue;
                $cnt = (int)$map[$key];
                if ($cnt <= 0) {
                    $seen[$key] = true;
                    continue;
                }
                $seen[$key] = true;
                $html .= $make($key, $cnt, $sum, $pct($cnt, $sum));
            }
            $rest = [];
            foreach ($map as $key => $val) {
                if (!isset($seen[$key])) $rest[$key] = $val;
            }
            arsort($rest, SORT_NUMERIC);
            foreach ($rest as $key => $val) {
                $val = (int)$val;
                if ($val <= 0) continue;
                $html .= $make($key, $val, $sum, $pct($val, $sum));
            }
        } else {
            arsort($map, SORT_NUMERIC);
            foreach ($map as $key => $val) {
                $val = (int)$val;
                if ($val <= 0) continue;
                $html .= $make($key, $val, $sum, $pct($val, $sum));
            }
        }
        if ($oth > 0) {
            $html .= $make('Other', $oth, $sum, $pct($oth, $sum));
        }
        return $html.'</div>';
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
    $hrm = 0;
    foreach ($hrs as $val) $hrm = max($hrm, (int)$val);
    $out = [];
    $out[] = '<style>
.sl-statx-rings{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.sl-statx-ring{padding:12px;border:1px solid var(--line);border-radius:5px;background:var(--bg);box-shadow:var(--shadow);text-align:center}
.sl-statx-ring svg{transform:rotate(-90deg);width:100%;max-width:120px;height:auto;display:block;margin:0 auto 8px}
.sl-statx-rbg{fill:none;stroke:var(--soft);stroke-width:12}
.sl-statx-ring .val,.sl-statx-rval{fill:none;stroke:var(--accent);stroke-width:12;stroke-linecap:round}
.sl-statx-ring strong{display:block;font-size:22px;line-height:1.1}
.sl-statx-ring b{display:block;margin-top:4px;font-size:12px}
.sl-statx-ring span{display:block;color:var(--muted);font-size:11px}
.sl-statx-dims{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:14px}
.sl-statx-dim{padding:12px;border:1px solid var(--line);border-radius:5px;background:var(--bg);box-shadow:var(--shadow)}
.sl-statx-dim h4,.sl-statx-hours h4{margin:0 0 8px;color:var(--blue2);font-size:13px}
.sl-statx-bar{display:flex;align-items:center;gap:8px;margin:4px 0}
.sl-statx-bl{width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sl-statx-bg{flex:1;background:var(--soft);border-radius:3px;height:8px}
.sl-statx-bv{display:block;height:100%;background:var(--accent,var(--blue2));border-radius:3px}
.sl-statx-bc,.sl-statx-bp{min-width:40px;text-align:right}
.sl-statx-hours{padding:12px;border:1px solid var(--line);border-radius:5px;background:var(--bg);box-shadow:var(--shadow);margin-top:14px}
.sl-statx-hours svg{display:block;width:100%;height:auto}
.sl-statx-hours rect{fill:var(--blue2)}
.sl-statx-hours text{fill:var(--muted);font-size:8px}
.sl-statx-unavail{margin:0}
@media (max-width: 900px){
.sl-statx-rings{grid-template-columns:repeat(2,minmax(0,1fr))}
.sl-statx-dims{grid-template-columns:1fr}
}
</style>';
    $out[] = '<div class="sl-statx-rings">'
        .$ring('Human Traffic %', 'Humans / all devices', $pct($hum, $devt), 'var(--green)')
        .$ring('Mobile Share %', 'Mobile / human traffic', $pct($mob, $hum), 'var(--purple)')
        .$ring('Return Depth %', 'Avg depth: '.number_format($davg, 1, '.', ''), $pct($ret, $hit), 'var(--blue2)')
        .$ring('Search Share %', 'Search / all refcat', $pct($search, $reft), 'var(--orange)')
        .'</div>';
    $dims = [];
    $dim = $bars('Browsers', $cnt[8], 'var(--blue2)');
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('OS', $cnt[9], 'var(--green)');
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('Devices', $cnt[10], 'var(--purple)', ['desktop', 'mobile', 'tablet', 'bot']);
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('Countries', $cnt[11], 'var(--orange)');
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('Refcat', $cnt[12], 'var(--blue2)', ['direct', 'search', 'social', 'referrer']);
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('New / Returning', $cnt[14], 'var(--green)', ['new', 'returning']);
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('Depth', $cnt[15], 'var(--blue2)', ['1', '2-3', '4-7', '8+']);
    if ($dim !== '') $dims[] = $dim;
    $dim = $bars('Duration', $cnt[16], 'var(--orange)', ['<30s', '30s-3m', '3m-15m', '15m+']);
    if ($dim !== '') $dims[] = $dim;
    if ($dims !== []) $out[] = '<div class="sl-statx-dims">'.implode('', $dims).'</div>';
    if ($hrm > 0) {
        $svg = '<section class="sl-statx-hours"><h4>Hits by hour (total)</h4><svg viewBox="0 0 264 64" aria-hidden="true">';
        for ($h = 0; $h < 24; $h++) {
            $bh = (int)round(48 * $hrs[$h] / $hrm);
            $barx = $h * 11 + 1;
            $bary = 48 - $bh;
            $svg .= '<rect x="'.$barx.'" y="'.$bary.'" width="9" height="'.$bh.'" rx="1"></rect>';
            if ($h % 3 === 0) {
                $svg .= '<text x="'.($h * 11 + 5).'" y="62" text-anchor="middle">'.$h.'</text>';
            }
        }
        $svg .= '</svg></section>';
        $out[] = $svg;
    }
    $output = implode('', $out);
    return $tpl->getHtmlPart('box', ['content_html' => $output]);
}

function statistic(): void {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&amp;file='.$file : '';
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
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
    ];
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
            ],
        ])]);
    }
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
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'tab' => 1,
        'subtitle_html' => getStatisticSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $rows = [
        ['label_html' => _STATBET, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'bet', 'value_attr' => (string)$conf['statistic']['bet'], 'is_config' => true])],
        ['label_html' => _STATSHI, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'shi', 'value_attr' => (string)$conf['statistic']['shi'], 'is_config' => true])],
        ['label_html' => _STATACT, 'field_html' => getTplRadioGroup(['name' => 'stat', 'value' => (string)(int)$conf['statistic']['stat'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'statistic'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkSiteToken();
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
        'ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
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
