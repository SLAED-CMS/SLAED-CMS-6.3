<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getRefererSearch(): string {
    global $afile, $tpl;
    $priv = [_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF];
    $sort = getVar('req', 'sort', 'num', 0);
    $order = getVar('req', 'order', 'num', 0);
    $sortopts = '';
    foreach ($priv as $key => $value) {
        $idx = $key + 1;
        $sortopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$idx,
            'label_text' => $value,
            'is_selected' => $sort == $idx,
        ]);
    }
    $orderopts = '';
    $privs = [_ASC, _DESC];
    foreach ($privs as $key => $value) {
        $idx = $key + 1;
        $orderopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$idx,
            'label_text' => $value,
            'is_selected' => $order == $idx,
        ]);
    }
    return $tpl->getHtmlPart('searchbox', [
        'searchbox' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php',
            'hidden' => [
                ['nameattr' => 'name', 'valueattr' => 'referers'],
            ],
            'content_html' =>
                _SORTE.': '.
                $tpl->getHtmlFrag('select', ['name_attr' => 'sort', 'options_html' => $sortopts]).
                ' '.
                _SORTORDER.': '.
                $tpl->getHtmlFrag('select', ['name_attr' => 'order', 'options_html' => $orderopts]).
                ' '.
                $tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        ]),
    ]);
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
    $cont = getTplAdminTabs([
        'ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete&amp;token='.getSiteToken(), 'name=referers&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO],
        'subtitle_html' => getRefererSearch(),
    ]);
    if ($db->getSqlRowCount($result) > 0) {
        $a = 0;
        $massiv = [];
        while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
            $massiv[] = [$hits, $uid, $name, $ip, $referer, $url, $date];
            $a++;
        }
        $rows = [];
        for ($i = $offset; $i < $tnum; $i++) {
            if (isset($massiv[$i]) && $massiv[$i] != '') {
                $name = ($massiv[$i][1]) ? user_info($massiv[$i][2]) : $massiv[$i][2];
                $words = engines_word($massiv[$i][4]) ?: _NO;
                $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['content_html' => $tpl->getHtmlFrag('info-tooltip', [
                            'label_text' => $massiv[$i][3],
                            'title_text' => _NICKNAME.': '.$name.' | '._DATE.': '.format_time($massiv[$i][6], _TIMESTRING),
                        ])],
                        ['is_truncate' => true, 'title_text' => domain($massiv[$i][5]), 'content_html' => domain($massiv[$i][5], 30)],
                        ['is_truncate' => true, 'title_text' => domain($massiv[$i][4]), 'content_html' => domain($massiv[$i][4], 30)],
                        ['is_truncate' => true, 'title_text' => $words, 'content_html' => $tpl->getHtmlFrag('info-tooltip', [
                            'label_text' => $words,
                            'title_text' => $words,
                        ])],
                        ['content_html' => (string)$massiv[$i][0]],
                    ],
                ])]);
            }
        }
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('table', [
            'head' => [
                ['content' => _IP],
                ['content' => _IN_URL, 'is_truncate' => true],
                ['content' => _REF_URL, 'is_truncate' => true],
                ['content' => _SWORD, 'is_truncate' => true],
                ['content' => _HITS, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
            'is_wrapless' => true,
        ]).getPageNumbers('', $a, ceil($a / $conf['referers']['anum']), $conf['referers']['anum'], 'name=referers&amp;sort='.$sort.'&amp;order='.$order.'&amp;', $conf['referers']['anump'])]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete&amp;token='.getSiteToken(), 'name=referers&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO],
        'tab' => 1,
        'subtitle_html' => getRefererSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/referers.php');
    $rows = [
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)$conf['referers']['anum'], 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)$conf['referers']['anump'], 'is_config' => true])],
        ['label_html' => _REFER_T, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'refer_t', 'value_attr' => (string)intval($conf['referers']['refer_t'] / 86400), 'is_config' => true])],
        ['label_html' => _REFER, 'field_html' => getTplRadioGroup(['name' => 'refer', 'value' => (string)$conf['referers']['refer'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _REFERB, 'field_html' => getTplRadioGroup(['name' => 'referb', 'value' => (string)$conf['referers']['referb'], 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'referers'],
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
        $content = [
            'anum' => getVar('post', 'anum', 'num', 50),
            'anump' => getVar('post', 'anump', 'num', 10),
            'refer_t' => getVar('post', 'refer_t', 'num', 30) * 86400,
            'refer' => getVar('post', 'refer', 'num', 0),
            'referb' => getVar('post', 'referb', 'num', 0),
        ];
        setConfigFile('referers.php', $content);
    }
    setRedirect($afile.'.php?name=referers&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer WHERE lid = 0');
    }
    setRedirect($afile.'.php?name=referers', false, 302, $warn ? _TOKENMISS : _SUCCCLEAR, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=referers', 'name=referers&amp;op=config', 'name=referers&amp;op=delete&amp;token='.getSiteToken(), 'name=referers&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DELETE, _INFO],
    ]);
}

switch ($op) {
    default: referers(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
