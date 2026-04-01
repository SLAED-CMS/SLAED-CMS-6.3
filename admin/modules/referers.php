<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getRefererSearch(): string {
    global $afile, $tpl;
    $priv = [_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF];
    $sort = getVar('post', 'sort', 'num', 0);
    $order = getVar('post', 'order', 'num', 0);
    $sortOpts = '';
    foreach ($priv as $key => $value) {
        $idx = $key + 1;
        $sortOpts .= getTplOption((string) $idx, $value, $sort == $idx);
    }
    $orderOpts = '';
    $privs = [_ASC, _DESC];
    foreach ($privs as $key => $value) {
        $idx = $key + 1;
        $orderOpts .= getTplOption((string) $idx, $value, $order == $idx);
    }
    return getTplAdminSearchBox($tpl->getHtmlFrag('admin-referers-search-form', [
        'ok_label' => _OK,
        'route' => $afile,
        'sort_html' => getTplSelect('sort', $sortOpts, 'sl_form'),
        'sort_label' => _SORTE.':',
        'order_html' => getTplSelect('order', $orderOpts, 'sl_form'),
    ]));
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
    $cont = getTplAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete'.$token, 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'sub' => getRefererSearch()]);
    if ($db->getSqlRowCount($result) > 0) {
        $a = 0;
        $massiv = [];
        while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
            $massiv[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
            $a++;
        }
        $head = getTplAdminTableHead([_IP, _HITS, _REFERERS, _SWORD, [_ID, 'nosort']]);
        $rows = '';
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($massiv[$i]) && $massiv[$i] != '') {
                $name = ($massiv[$i][1]) ? user_info($massiv[$i][2]) : $massiv[$i][2];
                $words = engines_word($massiv[$i][4]) ?: _NO;
                $rows .= getTplAdminTableRow(getTplAdminTableCells([
                    getTplAdminTitleTip(_NICKNAME.': '.$name.getTplAdminTipLine(_DATE, format_time($massiv[$i][6], _TIMESTRING))).$massiv[$i][3],
                    domain($massiv[$i][5], 30),
                    domain($massiv[$i][4], 30),
                    getTplSpan('sl_note', cutstr($words, 25), $words),
                    (string)$massiv[$i][0],
                ]));
            }
        }
        $cont .= getTplAdminTable($head, $rows);
        $numpages = ceil($a / $conf['referers']['anum']);
        $cont .= setPageNumbers('pagenum', '', $a, $numpages, $conf['referers']['anum'], 'name=referers&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['referers']['anump']);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete'.$token, 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'tab' => 1, 'sub' => getRefererSearch()]);
    $cont .= checkPerms(CONFIG_DIR.'/referers.php');
    $rows = [
        ['label_html' => _C_34, 'field_html' => getTplNumberInput((string)$conf['referers']['anum'], 'anum', 'sl_conf')],
        ['label_html' => _C_36, 'field_html' => getTplNumberInput((string)$conf['referers']['anump'], 'anump', 'sl_conf')],
        ['label_html' => _REFER_T, 'field_html' => getTplNumberInput((string)intval($conf['referers']['refer_t'] / 86400), 'refer_t', 'sl_conf')],
        ['label_html' => _REFER, 'field_html' => radio_form($conf['referers']['refer'], 'refer')],
        ['label_html' => _REFERB, 'field_html' => radio_form($conf['referers']['referb'], 'referb')],
    ];
    $confv = $tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden_html' => getTplHiddenInput('name', 'referers').getTplHiddenInput('op', 'save').getTplHiddenInput('token', getSiteToken()),
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.getTplBox($confv);
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
    $cont = getTplAdminNavi(['ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete'.$token, 'name=referers&amp;op=info'], 'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO], 'tab' => 3, 'sub' => getRefererSearch()]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: referers(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
