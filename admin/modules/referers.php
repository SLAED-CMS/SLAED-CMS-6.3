<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getRefererSearch(): string {
    global $afile, $tpl;
    $priv = [_REF_ID, _REF_URL, _IN_ID, _IN_URL, _NAME_ID, _NAME_REF, _IP_ID, _IP_REF, _TIME_ID, _TIME_REF];
    $sort = getVar('get', 'sort', 'num', 10);
    $order = getVar('get', 'order', 'num', 2);
    $sorts = [];
    foreach ($priv as $key => $value) $sorts[] = ['value_attr' => (string)($key + 1), 'label_text' => $value, 'is_selected' => $sort === $key + 1];
    $orders = [];
    foreach ([_ASC, _DESC] as $key => $value) $orders[] = ['value_attr' => (string)($key + 1), 'label_text' => $value, 'is_selected' => $order === $key + 1];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'referers']],
        'fields' => [
            ['field_id' => 'sl-ref-sort', 'label' => _SORTE, 'name_attr' => 'sort', 'options' => $sorts],
            ['field_id' => 'sl-ref-order', 'label' => _SORTORDER, 'name_attr' => 'order', 'options' => $orders],
        ],
    ]);
}

function referers(): void {
    global $db, $afile, $conf, $tpl;
    $sort = getVar('get', 'sort', 'num', 10);
    $order = getVar('get', 'order', 'num', 2);
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
    $limit = max(1, intval($conf['referers']['anum']));
    $group = 'SELECT COUNT('.$count.') AS hits, MAX(id) AS id FROM '.PREFIX_DB.'_referer GROUP BY '.$count;
    $total = intval($db->getSqlQuery('SELECT COUNT(*) FROM ('.$group.') t')->fetchColumn());
    $pages = max(1, (int)ceil($total / $limit));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $result = $db->getSqlQuery('SELECT t.hits, r.uid, r.name, r.ip, r.referer, r.url, r.time FROM ('.$group.') t JOIN '.PREFIX_DB.'_referer r ON r.id = t.id'
        .' ORDER BY '.$ordby.' '.$ordsc.' LIMIT '.(($num - 1) * $limit).', '.$limit);
    $rows = [];
    while ([$hits, $uid, $name, $ip, $referer, $url, $date] = $db->getSqlRow($result)) {
        $who = $uid ? user_info($name, null) : $name;
        $words = engines_word($referer) ?: _NO;
        $rows[] = ['cells' => [
            ['content_html' => $tpl->getHtmlFrag('popover', ['label_text' => $ip, 'title_text' => _NICKNAME.': '.$who.' | '._DATE.': '.format_time($date, _TIMESTRING)])],
            ['is_truncate' => true, 'title_text' => domain($url), 'content_html' => domain($url, 30)],
            ['is_truncate' => true, 'title_text' => domain($referer), 'content_html' => domain($referer, 30)],
            ['is_truncate' => true, 'title_text' => $words, 'content_html' => $tpl->getHtmlFrag('popover', ['label_text' => $words, 'title_text' => $words])],
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => $hits],
        ]];
    }
    $all = $total ? intval($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_referer')->fetchColumn()) : 0;
    $clear = $all ? $tpl->getHtmlFrag('alert', ['text' => sprintf(_REF_TOTAL, $all),
        'acts_html' => getTplPostButton(['name' => 'referers', 'op' => 'delete'], 'trash', _CLEAN,
            'data-sl-confirm="'.htmlspecialchars(_CLEAN.' "'._REFERERS.'"?', ENT_QUOTES, 'UTF-8').'"', 'danger')]) : '';
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs([
            'ops' => ['name=referers', 'name=referers&op=config', 'name=referers&op=info'],
            'tabs' => [_HOME, _PREFERENCES, _MANUAL],
            'subtitle_html' => getRefererSearch(),
            'part' => [0],
        ]),
        'note_html' => $clear,
        'is_fixed' => true,
        'head' => [
            ['content' => _IP],
            ['content' => _IN_URL, 'is_truncate' => true],
            ['content' => _REF_URL, 'is_truncate' => true],
            ['content' => _SWORD, 'is_truncate' => true],
            ['content' => _HITS, 'is_col_count' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?name=referers&sort='.$sort.'&order='.$order.'&',
        'page' => $num,
        'pages' => $pages,
        'maxpg' => intval($conf['referers']['anump']),
        'count' => $total,
        'limit' => $limit,
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=referers', 'name=referers&op=config', 'name=referers&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'tab' => 1,
        'subtitle_html' => getRefererSearch(),
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/referers.php');
    $rows = [
        [
            'label_for' => 'f-anum',
            'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anum',
                'input_id' => 'f-anum',
                'value_attr' => (string)$conf['referers']['anum'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anump',
            'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anump',
                'input_id' => 'f-anump',
                'value_attr' => (string)$conf['referers']['anump'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-refer-t',
            'label_html' => _REFER_T,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'refer_t',
                'input_id' => 'f-refer-t',
                'value_attr' => (string)intval($conf['referers']['refer_t'] / 86400),
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _REFER,
            'label_id' => $labid = getFieldIds('', 'refer')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'refer',
                'value' => (string)$conf['referers']['refer'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _REFERB,
            'label_id' => $labid = getFieldIds('', 'referb')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'referb',
                'value' => (string)$conf['referers']['referb'],
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
    ];
    $confv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'referers'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('referers')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $confv]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('referers');
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
    $warn = !checkAdminPost('referers');
    if (!$warn) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_referer');
    }
    setRedirect($afile.'.php?name=referers', false, 302, $warn ? _TOKENMISS : _SUCCCLEAR, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=referers', 'name=referers&op=config', 'name=referers&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: referers(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
