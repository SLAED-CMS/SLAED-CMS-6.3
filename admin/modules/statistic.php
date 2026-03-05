<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    global $afile;
    $file = getVar('post', 'file', 'text');
    $ops = ['name=statistic', 'name=statistic&amp;op=conf', 'name=statistic&amp;op=info'];
    $lang = [_HOME, _PREFERENCES, _INFO];
    $files = [];
    $handle = opendir(COUNTER_DIR.'/statistic/');
    if ($handle !== false) {
        while (false !== ($file = readdir($handle))) $files[] = $file;
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
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
    return getAdminTabs($search, $ops, $lang, [], [], $tab, $subtab, $legacy);
}

function statistic(): void {
    global $afile;
    $file = getVar('post', 'file', 'text');
    $pfile = $file ? '&amp;file='.$file : '';
    setHead();
    $cont = navi(0, 0, 0, 0);
    $cont .= checkPerms(COUNTER_DIR);
    $cont .= checkPerms(COUNTER_DIR.'/statistic');
    $cont .= setTemplateBasic('open');
    $cont .= '<img src="'.$afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day=15" alt="'._STATGR.'" title="'._STATGR.'">';
    if ($file || date('d') > 15) {
        if ($file) {
            $temp = file(COUNTER_DIR.'/statistic/'.$file);
            $out = ($temp !== false) ? count($temp) : 0;
        } else {
            $out = date('d');
        }
        $cont .= '<hr><img src="'.$afile.'.php?name=statistic&amp;op=add'.$pfile.'&amp;day='.$out.'" alt="'._STATGR.'" title="'._STATGR.'">';
    }
    $cont .= '<hr><table class="sl_table_list_sort"><thead><tr><th>'._DATE.'</th><th>'._UNIQUE.'</th><th>'._HITS.'</th><th>'._HOME.'</th><th>'._REFERERS.'</th><th>'._BOTSOPT.'</th><th>'._AUDIENCE.'</th><th class="{sorter: false}">'._USERS.'</th></tr></thead><tbody>';
    if ($file) {
        $f = file(COUNTER_DIR.'/statistic/'.$file);
    } else {
        if (file_exists(COUNTER_DIR.'/days.log')) {
            $f = file(COUNTER_DIR.'/days.log');
            $stat = file(COUNTER_DIR.'/statistic.log');
            if ($stat !== false) $f = array_merge($f, $stat);
        } else {
            $f = file(COUNTER_DIR.'/statistic.log');
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
    $cont = navi(0, 1, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/statistic.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
    .'<tr><td>'._STATBET.':</td><td><input type="number" name="bet" value="'.$conf['statistic']['bet'].'" class="sl_conf" placeholder="'._STATBET.'" required></td></tr>'
    .'<tr><td>'._STATSHI.':</td><td><input type="number" name="shi" value="'.$conf['statistic']['shi'].'" class="sl_conf" placeholder="'._STATSHI.'" required></td></tr>'
    .'<tr><td>'._STATACT.'</td><td>'.radio_form($conf['statistic']['stat'], 'stat').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="statistic"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
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
    echo navi(0, 2, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: statistic(); break;
    case 'add': add(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
