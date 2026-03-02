<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/referers.php';

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0): string {
    global $afile;
    $ops = ['name=referers', 'name=referers&amp;op=conf', 'name=referers&amp;op=del', 'name=referers&amp;op=info'];
    $lang = [_HOME, _PREFERENCES, _DELETE, _INFO];
    $search = '<form method="post" action="'.$afile.'.php">'._SORTE.': <select name="sort">';
    $priv = [_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF];
    $sort = getVar('post', 'sort', 'num', 0);
    $order = getVar('post', 'order', 'num', 0);
    foreach ($priv as $key => $value) {
        $idx = $key + 1;
        $sel = ($sort == $idx) ? ' selected' : '';
        $search .= '<option value="'.$idx.'"'.$sel.'>'.$value.'</option>';
    }
    $search .= '</select> <select name="order">';
    $privs = [_ASC, _DESC];
    foreach ($privs as $key => $value) {
        $idx = $key + 1;
        $sel = ($order == $idx) ? ' selected' : '';
        $search .= '<option value="'.$idx.'"'.$sel.'>'.$value.'</option>';
    }
    $search .= '</select> <input type="hidden" name="name" value="referers"><input type="hidden" name="op" value="referers"><input type="submit" value="'._OK.'" class="sl_but_blue"></form>';
    $search = setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
    return getAdminTabs(_REFERERS, 'referers.png', $search, $ops, $lang, [], [], $tab, $subtab);
}

function referers(): void {
    global $db, $conf;
    $sort = getVar('req', 'sort', 'num', 10);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['referers']['anum'];
    $tnum = ($offset) ? $conf['referers']['anum'] + $offset : $conf['referers']['anum'];
    $sortmap = [
        1 => ['referer', 'hits'], 2 => ['referer', 'referer'],
        3 => ['link', 'hits'], 4 => ['link', 'link'],
        5 => ['name', 'hits'], 6 => ['name', 'name'],
        7 => ['ip', 'hits'], 8 => ['ip', 'ip'],
        9 => ['date', 'hits'], 10 => ['date', 'date']
    ];
    $count = $sortmap[$sort][0] ?? 'date';
    $ordby = $sortmap[$sort][1] ?? 'date';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $result = $db->getSqlQuery('SELECT Count('.$count.') AS hits, uid, name, ip, referer, link, date FROM '.PREFIX_DB.'_referer GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc);
    head();
    $cont = navi(0, 0, 0, 0);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= setTemplateBasic('open');
        $a = 0;
        $massiv = [];
        while ([$hits, $uid, $name, $ip, $referer, $link, $date] = $db->getSqlRow($result)) {
            $massiv[] = [$hits, $uid, $name, $ip, $referer, $link, $date];
            $a++;
        }
        $cont .= '<table class="sl_table_list_sort"><thead><tr><th>'._IP.'</th><th>'._HITS.'</th><th>'._REFERERS.'</th><th>'._SWORD.'</th><th class="{sorter: false}">'._ID.'</th></tr></thead><tbody>';
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($massiv[$i]) && $massiv[$i] != '') {
                $name = ($massiv[$i][1]) ? user_info($massiv[$i][2]) : $massiv[$i][2];
                $words = engines_word($massiv[$i][4]) ?: _NO;
                $cont .= '<tr>'
                   .'<td>'.title_tip(_NICKNAME.': '.$name.'<br>'._DATE.': '.format_time($massiv[$i][6], _TIMESTRING)).$massiv[$i][3].'</td>'
                   .'<td>'.domain($massiv[$i][5], 30).'</td>'
                   .'<td>'.domain($massiv[$i][4], 30).'</td>'
                   .'<td><span title="'.$words.'" class="sl_note">'.cutstr($words, 25).'</span></td>'
                   .'<td>'.$massiv[$i][0].'</td></tr>';
            }
        }
        $cont .= '</tbody></table>';
        $numpages = ceil($a / $conf['referers']['anum']);
        $cont .= setPageNumbers('pagenum', '', $a, $numpages, $conf['referers']['anum'], 'name=referers&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['referers']['anump']);
        $cont .= setTemplateBasic('close');
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    foot();
}

function conf(): void {
    global $afile, $conf;
    head();
    $cont = navi(0, 1, 0, 0);
    $cont .= checkPerms(CONFIG_DIR.'/referers.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_conf">'
       .'<tr><td>'._C_34.':</td><td><input type="number" name="anum" value="'.$conf['referers']['anum'].'" class="sl_conf" placeholder="'._C_34.'" required></td></tr>'
       .'<tr><td>'._C_36.':</td><td><input type="number" name="anump" value="'.$conf['referers']['anump'].'" class="sl_conf" placeholder="'._C_36.'" required></td></tr>'
       .'<tr><td>'._REFER_T.':</td><td><input type="number" name="refer_t" value="'.intval($conf['referers']['refer_t'] / 86400).'" class="sl_conf" placeholder="'._REFER_T.'" required></td></tr>'
       .'<tr><td>'._REFER.'</td><td>'.radio_form($conf['referers']['refer'], 'refer').'</td></tr>'
       .'<tr><td>'._REFERB.'</td><td>'.radio_form($conf['referers']['referb'], 'referb').'</td></tr>'
       .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="referers"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $afile;
    $content = [
        'anum' => getVar('post', 'anum', 'num', 50),
        'anump' => getVar('post', 'anump', 'num', 10),
        'refer_t' => getVar('post', 'refer_t', 'num', 30) * 86400,
        'refer' => getVar('post', 'refer', 'num', 0),
        'referb' => getVar('post', 'referb', 'num', 0),
    ];
    setConfigFile('referers.php', $content);
    setRedirect($afile.'.php?name=referers&op=conf');
}

function del(): void {
    global $db, $afile;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = 0');
    setRedirect($afile.'.php?name=referers');
}

function info(): void {
    head();
    echo navi(0, 3, 0, 0).'<div id="repadm_info">'.getAdminInfo().'</div>';
    foot();
}

switch ($op) {
    default: referers(); break;
    case 'conf': conf(); break;
    case 'save': save(); break;
    case 'del': del(); break;
    case 'info': info(); break;
}
