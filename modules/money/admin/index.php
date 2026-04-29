<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('money')) die('Illegal file access');

function money(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
    ]);
    if (getVar('get', 'send', 'num', 0)) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _MA_15]);
    }
    $num = getVar('get', 'num', 'num', 1);
    $anum = (int)($conf['money']['anum'] ?? 25);
    $anump = (int)($conf['money']['anump'] ?? 10);
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, sum, email, intro, note, ip, agent, time, status FROM '.PREFIX_DB.'_money ORDER BY time DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        [$numstories] = $db->getSqlRow($db->getSqlQuery('SELECT Count(id) FROM '.PREFIX_DB.'_money'));
        $rnum = $numstories;
        if ($numstories > $offset) $rnum -= $offset;
        $rows = '';
        $form = explode(',', $conf['money']['form'] ?? '');
        while ([$id, $sum, $email, $intro, $note, $ip, $agent, $time, $status] = $db->getSqlRow($result)) {
            $act = $status ? 0 : 1;
            $ival = explode('|', $intro);
            $tips = [];
            $i = 0;
            foreach ($form as $val) {
                if ($val === '') continue;
                $tips[] = [
                    'label' => $val,
                    'has_value_text' => true,
                    'value_text' => (string)($ival[$i] ?? ''),
                    'is_last' => false,
                ];
                $i++;
            }
            $tips[] = [
                'label' => _COMMENT,
                'has_value_text' => true,
                'value_text' => (string)$note,
                'is_last' => false,
            ];
            $tips[] = [
                'label' => _BROWSER,
                'has_value_text' => true,
                'value_text' => (string)$agent,
                'is_last' => true,
            ];
            $items = [
                [
                    'href' => $afile.'.php?name=money&amp;op=activate&amp;id='.$id.'&amp;act='.$act.'&amp;token='.getSiteToken(),
                    'label' => $status ? _DEACTIVATE : _ACTIVATE,
                    'title' => $status ? _DEACTIVATE : _ACTIVATE,
                ],
                [
                    'href' => $afile.'.php?name=money&amp;op=invoice&amp;id='.$id.'&amp;rnum='.$rnum,
                    'label' => _RECHN_B,
                    'title' => _RECHN_B,
                ],
                [
                    'href' => $afile.'.php?name=money&amp;op=add&amp;id='.$id,
                    'label' => _FULLEDIT,
                    'title' => _FULLEDIT,
                ],
                [
                    'href' => $afile.'.php?name=money&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'._ID.': '.$id.'&quot;?\')"',
                ],
            ];
            $rows .= $tpl->getHtmlFrag('table-row', [
                'cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['is_col_id' => true, 'content_html' => (string)$id],
                        ['is_col_count' => true, 'content_html' => $sum.' EUR'],
                        ['is_truncate' => true, 'title_text' => $email, 'content_html' => $tpl->getHtmlFrag('info-tooltip', ['items' => $tips]).anti_spam($email)],
                        ['content_html' => user_geo_ip($ip, 4)],
                        ['is_col_date' => true, 'content_html' => format_time($time, _TIMESTRING)],
                        ['is_col_status' => true, 'content_html' => ad_status('', $status)],
                        ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                    ],
                ]),
            ]);
            $rnum--;
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _SUM, 'is_col_count' => true],
                ['content' => _EMAIL, 'is_truncate' => true],
                ['content' => _IP],
                ['content' => _DATE, 'is_col_date' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager([
            'limit' => $anum,
            'maxpg' => $anump,
            'url' => 'name=money&amp;',
            'table' => '_money',
            'field' => 'id',
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $conf, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT sum, email, intro, note, time FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $mid]);
        [$sum, $email, $intro, $note, $time] = $db->getSqlRow($result);
        $intro = explode('|', $intro);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $sum = getVar('post', 'sum', 'num', 0);
        $email = getVar('post', 'email', 'text', '');
        $intro = getVar('post', 'intro', 'array', []);
        $intro = is_array($intro) ? $intro : [];
        $note = getVar('post', 'note', 'text', '');
        $time = getVar('req', 'time', 'time');
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
        'tab' => 1,
    ]);
    if ($stop) {
        $cont .= is_array($stop)
            ? $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values($stop)])
            : $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => (string)$stop]);
    }
    if ($intro) {
        $form = explode(',', $conf['money']['form'] ?? '');
        $lines = [];
        $i = 0;
        foreach ($form as $val) {
            if ($val === '') continue;
            $lines[] = $val.': '.htmlspecialchars((string)($intro[$i] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $i++;
        }
        $cont .= getTplPreviewContent(['title' => $email, 'texta' => implode('<br>', $lines), 'textb' => _COMMENT.': '.$note, 'mod' => 'all']);
    }
    $rows = [
        [
            'label_html' => _MA_17,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'sum',
                'value_attr' => (string)$sum,
                'is_required' => true,
            ]),
        ],
        [
            'label_html' => _MA_18,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'email',
                'name_attr' => 'email',
                'value_attr' => $email,
                'is_required' => true,
                'maxlength_num' => 255,
            ]),
        ],
    ];
    $form = explode(',', $conf['money']['form'] ?? '');
    $i = 0;
    foreach ($form as $val) {
        if ($val === '') continue;
        $rows[] = [
            'label_html' => $val,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'intro[]',
                'value_attr' => (string)($intro[$i] ?? ''),
                'maxlength_num' => 255,
                'placeholder_text' => $val,
            ]),
        ];
        $i++;
    }
    $rows[] = [
        'label_html' => _MA_19,
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'note',
            'value_text' => $note,
            'rows_num' => 5,
            'input_attr' => ' placeholder="'._MA_19.'"',
        ]),
        'is_full' => true,
    ];
    $rows[] = [
        'label_html' => _CHNGSTORY,
        'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16]),
    ];
    $posttypeopts = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($mid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=money&amp;op=save',
        'hidden' => [
            ['nameattr' => 'mid', 'valueattr' => (string)$mid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $sum = getVar('post', 'sum', 'num', 0);
    $email = getVar('post', 'email', 'text', '');
    $intro = getVar('post', 'intro', 'array', []);
    $intro = is_array($intro) ? $intro : [];
    $list = $intro ? filterText(implode('|', $intro)) : '';
    $note = getVar('post', 'note', 'text', '');
    $time = getVar('req', 'time', 'time');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    checkemail($email);
    if (!$iswarn) {
        if (!$stop && $posttype === 'save') {
            if ($mid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET sum = :sum, email = :email, intro = :intro, note = :note, time = :time WHERE id = :mid', ['sum' => $sum, 'email' => $email, 'intro' => $list, 'note' => $note, 'time' => $time, 'mid' => $mid]);
            } else {
                $ip = getip();
                $agent = getagent();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_money (`sum`, `email`, `intro`, `note`, `ip`, `agent`, `time`, `status`) VALUES (:sum, :email, :intro, :note, :ip, :agent, :time, \'1\')', ['sum' => $sum, 'email' => $email, 'intro' => $list, 'note' => $note, 'ip' => $ip, 'agent' => $agent, 'time' => $time]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($mid);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    setRedirect($afile.'.php?name=money', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$did && !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=money', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function billing(string $title, string $autor, string $infos, string $num, string $date, string $menge, string $kurs, string $sum): void {
    global $theme, $conf;
    $template = file_get_contents('modules/money/templates/billing.html');
    if ($template === false) return;
    $replacements = [
        '$charset' => _CHARSET,
        '$theme' => $theme,
        '$title' => $title,
        '\$logo' => $conf['site_logo'] ?? '',
        '$sitename' => $conf['sitename'] ?? '',
        '$autor' => $autor,
        '$infos' => $infos,
        '$num' => $num,
        '$date' => $date,
        '$menge' => $menge,
        '$kurs' => $kurs,
        '$sum' => $sum,
    ];
    echo str_replace(array_keys($replacements), array_values($replacements), $template);
}

function invoice(): void {
    global $db, $conf, $prs;
    $id = getVar('get', 'id', 'num', 0);
    [$sum, $email, $intro, $note, $ip, $agent, $time] = $db->getSqlRow($db->getSqlQuery('SELECT sum, email, intro, note, ip, agent, time FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
    $defis = urldecode($conf['defis'] ?? '%3E');
    $title = _RECHN.' '.$defis.' '._MONEY.' '.$defis.' '.($conf['sitename'] ?? '');
    $form = explode(',', $conf['money']['form'] ?? '');
    $intro = explode('|', $intro);
    $i = 0;
    $lines = [];
    foreach ($form as $val) {
        if ($val === '') continue;
        $lines[] = $val.': '.htmlspecialchars((string)($intro[$i] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $i++;
    }
    $infos = implode('<br>', $lines);
    $rnum = getVar('get', 'rnum', 'text', '');
    $kurs = (float)($conf['money']['kurs'] ?? 0);
    $proz = (float)($conf['money']['proz'] ?? 0);
    $menge = ($sum / 100) * $kurs * (100 - $proz);
    $kurs = ($menge > 0) ? round($sum / $menge, 2) : 0;
    billing($title, $prs->filterContent($conf['money']['autor'] ?? '', false, 'money'), $prs->filterContent($infos, false, 'money'), $rnum, format_time($time), (string)round($menge, 2), $kurs.' EUR', $sum.' EUR');
}

function activate(): void {
    global $db, $afile, $conf, $prs;
    $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_money SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
        if ($act) {
            [$email] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_money WHERE id = :id', ['id' => $id]));
            $amail = ($conf['money']['mail'] ?? '') ? $conf['money']['mail'] : ($conf['adminmail'] ?? '');
            $subject = ($conf['sitename'] ?? '').' - '._MONEY;
            $msg = ($conf['sitename'] ?? '').' - '._MONEY.'<br><br>';
            $msg .= $prs->filterContent($conf['money']['sendinfo'] ?? '', false, 'all');
            addMail($email, $amail, $subject, $msg, 0, 3);
        }
    }
    $tail = (!$iswarn && $act) ? '&send=1' : '';
    setRedirect($afile.'.php?name=money'.$tail, false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
        'tab' => 2,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/money.php');
    $rows = [
        ['label_html' => _MA_3, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'proz', 'value_attr' => (string)($conf['money']['proz'] ?? '0')])],
        ['label_html' => _MA_4.': EUR > USD', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'kurs', 'value_attr' => (string)($conf['money']['kurs'] ?? '')])],
        ['label_html' => _MA_4.': EUR > RUB', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'kurs2', 'value_attr' => (string)($conf['money']['kurs2'] ?? '')])],
        ['label_html' => _MA_5, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'bal', 'value_attr' => (string)($conf['money']['bal'] ?? '')])],
        ['label_html' => _MA_6, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => (string)($conf['money']['mail'] ?? '')])],
        ['label_html' => _MA_7, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'form', 'value_text' => (string)($conf['money']['form'] ?? ''), 'rows_num' => 3]), 'is_full' => true],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['money']['anum'] ?? 25)])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['money']['anump'] ?? 10)])],
        ['label_html' => _MA_8, 'field_html' => getTplRadioGroup(['name' => 'an', 'value' => (string)($conf['money']['an'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _MA_9, 'field_html' => getTplRadioGroup(['name' => 'pr', 'value' => (string)($conf['money']['pr'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _MA_10, 'field_html' => getTplRadioGroup(['name' => 'ad', 'value' => (string)($conf['money']['ad'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _MA_11, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'text', 'value' => (string)($conf['money']['text'] ?? ''), 'mod' => 'all', 'rows' => 5, 'placeholder' => _MA_11, 'required' => '1']), 'is_full' => true],
        ['label_html' => _MA_12, 'field_html' => getTplTextarea(['id' => '2', 'name' => 'info', 'value' => (string)($conf['money']['info'] ?? ''), 'mod' => 'all', 'rows' => 5, 'placeholder' => _MA_12, 'required' => '1']), 'is_full' => true],
        ['label_html' => _MA_13, 'field_html' => getTplTextarea(['id' => '3', 'name' => 'sendinfo', 'value' => (string)($conf['money']['sendinfo'] ?? ''), 'mod' => 'all', 'rows' => 5, 'placeholder' => _MA_13, 'required' => '1']), 'is_full' => true],
        ['label_html' => _MA_14, 'field_html' => getTplTextarea(['id' => '4', 'name' => 'autor', 'value' => (string)($conf['money']['autor'] ?? ''), 'mod' => 'all', 'rows' => 5, 'placeholder' => _MA_14, 'required' => '1']), 'is_full' => true],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=money&amp;op=configsave',
        'hidden' => [['nameattr' => 'token', 'valueattr' => getSiteToken()]],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $xkurs = str_replace(',', '.', getVar('post', 'kurs', 'text', '0'));
        $xkurs2 = str_replace(',', '.', getVar('post', 'kurs2', 'text', '0'));
        $xform = strtr(getVar('post', 'form', 'raw', ''), ["\n" => '', "\t" => '', "\r" => '']);
        $cont = [
            'proz' => getVar('post', 'proz', 'text', '0'),
            'kurs' => $xkurs,
            'kurs2' => $xkurs2,
            'bal' => getVar('post', 'bal', 'text', ''),
            'mail' => getVar('post', 'mail', 'text', ''),
            'form' => $xform,
            'anum' => getVar('post', 'anum', 'num', 25),
            'anump' => getVar('post', 'anump', 'num', 10),
            'an' => getVar('post', 'an', 'num', 0),
            'pr' => getVar('post', 'pr', 'num', 0),
            'ad' => getVar('post', 'ad', 'num', 0),
            'text' => getVar('post', 'text', 'text', ''),
            'info' => getVar('post', 'info', 'text', ''),
            'sendinfo' => getVar('post', 'sendinfo', 'text', ''),
            'autor' => getVar('post', 'autor', 'text', ''),
        ];
        setConfigFile('money.php', $cont);
    }
    setRedirect($afile.'.php?name=money&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=money', 'name=money&amp;op=add', 'name=money&amp;op=config', 'name=money&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: money(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'activate': activate(); break;
    case 'delete': delete(); break;
    case 'invoice': invoice(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
