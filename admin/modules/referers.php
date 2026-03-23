<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getRefererSearch(): string {
    global $afile, $tpl;
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
    return $tpl->getHtmlPart('searchbox', ['searchbox' => $search]);
}

function referers(): void {
    global $db, $conf, $tpl;
    $sort = getVar('req', 'sort', 'num', 10);
    $order = getVar('req', 'order', 'num', 2);
    $num = getVar('get', 'num', 'num', 1);
    $offset = ($num - 1) * $conf['referers']['anum'];
    $tnum = ($offset) ? $conf['referers']['anum'] + $offset : $conf['referers']['anum'];
    $sortmap = [
        1 => ['referer', 'hits'], 2 => ['referer', 'referer'],
        3 => ['url', 'hits'], 4 => ['url', 'url'],
        5 => ['name', 'hits'], 6 => ['name', 'name'],
        7 => ['ip', 'hits'], 8 => ['ip', 'ip'],
        9 => ['time', 'hits'], 10 => ['time', 'time']
    ];
    $count = $sortmap[$sort][0] ?? 'time';
    $ordby = $sortmap[$sort][1] ?? 'time';
    $ordsc = ($order == 1) ? 'ASC' : 'DESC';
    $result = $db->getSqlQuery('SELECT Count('.$count.') AS hits, uid, name, ip, referer, url, time FROM '.PREFIX_DB.'_referer GROUP BY '.$count.' ORDER BY '.$ordby.' '.$ordsc);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete', 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'sub' => getRefererSearch()]);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('open', []);
        $a = 0;
        $massiv = [];
        while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
            $massiv[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
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
        $cont .= $tpl->getHtmlFrag('close', []);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete', 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'tab' => 1, 'sub' => getRefererSearch()]);
    $cont .= checkPerms(CONFIG_DIR.'/referers.php');
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= $tpl->getHtmlFrag('form-conf', [
        'route' => $afile,
        'module' => 'referers',
        'op' => 'save',
        'save' => _SAVECHANGES,
        'fields' => '',
        '_c34' => _C_34,
        'anum' => $conf['referers']['anum'],
        '_c36' => _C_36,
        'anump' => $conf['referers']['anump'],
        '_refer_t' => _REFER_T,
        'refer_t' => intval($conf['referers']['refer_t'] / 86400),
        '_refer' => _REFER,
        'r_refer' => radio_form($conf['referers']['refer'], 'refer'),
        '_referb' => _REFERB,
        'r_referb' => radio_form($conf['referers']['referb'], 'referb'),
        'referers' => true,
    ]);
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
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
    setRedirect($afile.'.php?name=referers&op=config');
}

function delete(): void {
    global $db, $afile;
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = 0');
    setRedirect($afile.'.php?name=referers');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete', 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'tab' => 3, 'sub' => getRefererSearch()]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: referers(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
