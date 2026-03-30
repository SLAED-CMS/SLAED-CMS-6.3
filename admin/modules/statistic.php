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
    foreach (scandir(COUNTER_DIR.'/statistic/') as $filev) $files[] = $filev;
    rsort($files);
    $sopts = getTplOption('', _NO_INFO, !$file);
    foreach ($files as $val) {
        if ($val != '' && preg_match('/^statistic\_(.+)\.log/', $val, $matches)) {
            $sopts .= getTplOption($val, $matches[1], $file === $val);
        }
    }
    $sel = getTplSelect('file', $sopts);
    return getTplAdminSearchBox($tpl->getHtmlFrag('admin-statistic-search-form', [
        'from_label' => _STATFROM.':',
        'ok_label' => _OK,
        'route' => $afile,
        'select_html' => $sel,
    ]));
}

function statistic(): void {
    global $afile, $tpl;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&amp;file='.$file : '';
    setHead();
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'sub' => getStatisticSearch()]);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    $statv = $tpl->getHtmlFrag('admin-statistic-image', [
        'alt_text' => _STATGR,
        'image_url' => $afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day=15',
        'title_text' => _STATGR,
    ]);
    if ($file || date('d') > 15) {
        if ($file) {
            $path = COUNTER_DIR.'/statistic/'.$file;
            $temp = (is_file($path) && is_readable($path)) ? file($path) : [];
            $out = ($temp !== false) ? count($temp) : 0;
        } else {
            $out = date('d');
        }
        $statv .= getTplHrLine();
        $statv .= $tpl->getHtmlFrag('admin-statistic-image', [
            'alt_text' => _STATGR,
            'image_url' => $afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day='.$out,
            'title_text' => _STATGR,
        ]);
    }
    $head = $tpl->getHtmlFrag('admin-statistic-table-head', [
        'audience_label' => _AUDIENCE,
        'bots_label' => _BOTSOPT,
        'date_label' => _DATE,
        'hits_label' => _HITS,
        'home_label' => _HOME,
        'referers_label' => _REFERERS,
        'unique_label' => _UNIQUE,
        'users_label' => _USERS,
    ]);
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
        $rows .= $tpl->getHtmlFrag('admin-statistic-table-row', [
            'audience_text' => (string)$out_aud,
            'bots_text' => $out[4],
            'date_text' => $out[0],
            'hits_text' => $out[2],
            'home_text' => $out[6],
            'referers_text' => $out[5],
            'unique_text' => $out[1],
            'users_text' => rtrim($out[7]),
        ]);
    }
    $rows .= $tpl->getHtmlFrag('admin-statistic-table-total', [
        'all_label' => _ALL,
        'audience_text' => (string)$auditory,
        'bots_text' => (string)$engines,
        'hits_text' => (string)$today,
        'home_text' => (string)$homepage,
        'referers_text' => (string)$sites,
        'unique_text' => (string)$unique,
        'users_text' => (string)$regusers,
    ]);
    $statv .= getTplHrLine().getTplAdminTable($head, $rows, 'sl_table_list_sort');
    echo $cont.getTplBox($statv);
    setFoot();
}

function add(): void {
    getStatistic();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1, 'sub' => getStatisticSearch()]);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $confv = $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'statistic',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_statbet' => _STATBET,
        'bet' => $conf['statistic']['bet'],
        '_statshi' => _STATSHI,
        'shi' => $conf['statistic']['shi'],
        '_statact' => _STATACT,
        'r_stat' => radio_form($conf['statistic']['stat'], 'stat'),
        'statistic' => true,
    ]);
    echo $cont.getTplBox($confv);
    setFoot();
}

function save(): void {
    global $afile;
    $cont = [
        'bet' => getVar('post', 'bet', 'num', 42),
        'shi' => getVar('post', 'shi', 'num', 22),
        'stat' => getVar('post', 'stat', 'num')
    ];
    setConfigFile('statistic.php', $cont);
    setRedirect($afile.'.php?name=statistic&op=config');
}

function info(): void {
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=config', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2, 'sub' => getStatisticSearch()]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: statistic(); break;
    case 'add': add(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
