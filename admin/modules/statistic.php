<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getStatisticSearch(): string {
    global $afile;
    $file = getVar('post', 'file', 'text');
    $files = [];
    $handle = opendir(COUNTER_DIR.'/statistic/');
    if ($handle !== false) {
        while (false !== ($f = readdir($handle))) $files[] = $f;
        closedir($handle);
    }
    rsort($files);
    $search = '<form method="post" action="'.$afile.'.php">'._STATFROM.': <select name="file"><option value="">'._NO_INFO.'</option>';
    foreach ($files as $val) {
        if ($val != '' && preg_match('/^statistic\_(.+)\.log/', $val, $matches)) {
            $sel = ($file && $file == $val) ? ' selected' : '';
            $search .= '<option value="'.$val.'"'.$sel.'>'.$matches[1].'</option>';
        }
    }
    $search .= '</select> <input type="hidden" name="name" value="statistic"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    return setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
}

function statistic(): void {
    global $afile;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&amp;file='.$file : '';
    setHead();
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=conf', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'sub' => getStatisticSearch()]);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    $cont .= setTemplateBasic('open');
    $cont .= '<img src="'.$afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day=15" alt="'._STATGR.'" title="'._STATGR.'">';
    if ($file || date('d') > 15) {
        if ($file) {
            $path = COUNTER_DIR.'/statistic/'.$file;
            $temp = (is_file($path) && is_readable($path)) ? file($path) : [];
            $out = ($temp !== false) ? count($temp) : 0;
        } else {
            $out = date('d');
        }
        $cont .= '<hr><img src="'.$afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day='.$out.'" alt="'._STATGR.'" title="'._STATGR.'">';
    }
    $cont .= '<hr><table class="sl_table_list_sort"><thead><tr><th>'._DATE.'</th><th>'._UNIQUE.'</th><th>'._HITS.'</th><th>'._HOME.'</th><th>'._REFERERS.'</th><th>'._BOTSOPT.'</th><th>'._AUDIENCE.'</th><th class="{sorter: false}">'._USERS.'</th></tr></thead><tbody>';
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
        $cont .= '<tr><td>'.$out[0].'</td><td>'.$out[1].'</td><td>'.$out[2].'</td><td>'.$out[6].'</td><td>'.$out[5].'</td><td>'.$out[4].'</td><td>'.$out_aud.'</td><td>'.rtrim($out[7]).'</td></tr>';
    }
    $cont .= '<tr><th>'._ALL.'</th><th>'.$unique.'</th><th>'.$today.'</th><th>'.$homepage.'</th><th>'.$sites.'</th><th>'.$engines.'</th><th>'.$auditory.'</th><th>'.$regusers.'</th></tr></tbody></table>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function add(): void {
    getStatistic();
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=conf', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 1, 'sub' => getStatisticSearch()]);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $cont .= setTemplateBasic('open');
    $cont .= setTemplateBasic('form-conf', [
        '{%route%}'    => $afile,
        '{%module%}'   => 'statistic',
        '{%op%}'       => 'save',
        '{%save%}'     => _SAVECHANGES,
        '{%fields%}'   => '',
        '{%_statbet%}' => _STATBET,
        '{%bet%}'      => $conf['statistic']['bet'],
        '{%_statshi%}' => _STATSHI,
        '{%shi%}'      => $conf['statistic']['shi'],
        '{%_statact%}' => _STATACT,
        '{%r_stat%}'   => radio_form($conf['statistic']['stat'], 'stat'),
        'if_flag'      => ['statistic' => true],
    ]);
    $cont .= setTemplateBasic('close');
    echo $cont;
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
    setRedirect($afile.'.php?name=statistic&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=statistic', 'name=statistic&amp;op=conf', 'name=statistic&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _INFO], 'tab' => 2, 'sub' => getStatisticSearch()]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: statistic(); break;
    case 'add': add(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
