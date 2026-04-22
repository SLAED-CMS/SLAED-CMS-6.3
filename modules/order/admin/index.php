<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('order')) die('Illegal file access');

function order(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $ops = ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'];
    $tabs = [_HOME, _ADD, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs]);
    if (getVar('get', 'send', 'num', 0)) $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _OR_8])]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['order']['anum'] ?? 25;
    $anump = $conf['order']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $result = $db->getSqlQuery('SELECT id, email, info, note, ip, agent, time, status FROM '.PREFIX_DB.'_order ORDER BY time DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $email, $info, $note, $ip, $agent, $date, $status] = $db->getSqlRow($result)) {
            $act = $status ? 0 : 1;
            $infos = getTplViewFieldRows(['field' => $info, 'mod' => 'order']);
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $email, 'content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => [
                            ['label' => _COMMENT, 'value' => (string)$note],
                            ['label' => _BROWSER, 'value' => (string)$agent, 'is_last' => true],
                        ],
                        'label_text' => anti_spam($email),
                        'title_html' => $infos,
                    ])],
                    ['content_html' => user_geo_ip($ip, 4)],
                    ['is_col_date' => true, 'content_html' => format_time($date, _TIMESTRING)],
                    ['is_col_status' => true, 'content_html' => ad_status('', $status)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => [
                        ['href' => $afile.'.php?name=order&amp;op=activate&amp;id='.$id.'&amp;act='.$act.'&amp;token='.getSiteToken(), 'label' => $status ? _DEACTIVATE : _ACTIVATE, 'title' => $status ? _DEACTIVATE : _ACTIVATE],
                        ['href' => $afile.'.php?name=order&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
                        [
                            'href' => $afile.'.php?name=order&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                            'label' => _ONDELETE,
                            'title' => _ONDELETE,
                            'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'._ID.': '.$id.'&quot;?\')"',
                        ],
                    ]])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _EMAIL, 'is_truncate' => true],
                ['content' => _IP],
                ['content' => _DATE, 'is_col_date' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => 'name=order&amp;', 'table' => '_order', 'field' => 'id']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    $mid = $id;
    if ($mid) {
        $result = $db->getSqlQuery('SELECT email, info, note, time FROM '.PREFIX_DB.'_order WHERE id = :mid', ['mid' => $mid]);
        [$email, $field, $note, $date] = $db->getSqlRow($result);
    } else {
        $mid = getVar('post', 'mid', 'num', 0);
        $email = getVar('post', 'email', 'text', '');
        $fieldp = getVar('post', 'field[]', 'raw', []);
        $field = is_array($fieldp) ? filterFields($fieldp) : getVar('post', 'field', 'field');
        $note = getVar('post', 'note', 'text', '');
        $date = getVar('req', 'date', 'time');
    }
    setHead();
    $ops = ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'];
    $tabs = [_HOME, _ADD, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($field) $cont .= getTplPreviewContent(['title' => $email, 'texta' => $field, 'textb' => _COMMENT.': '.$note, 'mod' => 'all']);
    $rows = [
        ['label_html' => _OR_9.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'email', 'name_attr' => 'email', 'value_attr' => $email, 'is_required' => true])],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'date', 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => _OR_10.':', 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'note', 'value_text' => $note, 'placeholder_text' => _OR_10]), 'is_full' => true],
    ];
    $rows = array_merge($rows, getTplAddFieldRows(['field' => $field, 'mod' => 'order']));
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=order&amp;op=save',
        'hidden' => [
            ['nameattr' => 'mid', 'valueattr' => (string)$mid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW]).$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND]).($mid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : ''), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $mid = getVar('post', 'mid', 'num', 0);
    $email = getVar('post', 'email', 'text', '');
    $fieldp = getVar('post', 'field[]', 'raw', []);
    $field = is_array($fieldp) ? filterFields($fieldp) : getVar('post', 'field', 'field');
    $note = getVar('post', 'note', 'text', '');
    $date = getVar('req', 'date', 'time');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        checkemail($email);
        if (!$stop && $posttype === 'save') {
            if ($mid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET email = :email, info = :info, note = :note, time = :time WHERE id = :mid', ['email' => $email, 'info' => $field, 'note' => $note, 'time' => $date, 'mid' => $mid]);
            } else {
                $ip = getip();
                $agent = getagent();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_order VALUES (NULL, :email, :info, :note, :ip, :agent, :time, \'1\')', ['email' => $email, 'info' => $field, 'note' => $note, 'ip' => $ip, 'agent' => $agent, 'time' => $date]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($mid);
        return;
    }
    setRedirect($afile.'.php?name=order', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$did && !checkSiteToken();
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=order', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function activate(): void {
    global $db, $afile, $conf, $prs;
    $act = getVar('get', 'act', 'num', 0);
    $id = getVar('get', 'id', 'num', 0);
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_order SET status = :act WHERE id = :id', ['act' => $act, 'id' => $id]);
        if ($act) {
            [$email] = $db->getSqlRow($db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_order WHERE id = :id', ['id' => $id]));
            $amail = ($conf['order']['mail'] ?? '') ? $conf['order']['mail'] : ($conf['adminmail'] ?? '');
            $subject = ($conf['sitename'] ?? '').' - '._ORDER;
            $msg = ($conf['sitename'] ?? '').' - '._ORDER.'<br><br>';
            $msg .= $prs->filterContent($conf['order']['sendinfo'] ?? '', false, 'all');
            addMail($email, $amail, $subject, $msg, 0, 3);
        }
    }
    $succ = $act ? _OR_8 : _SUCCSTATUS;
    $url = $act ? $afile.'.php?name=order&send=1' : $afile.'.php?name=order';
    setRedirect($url, false, 302, $iswarn ? _TOKENMISS : $succ, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ops = ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'];
    $tabs = [_HOME, _ADD, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/order.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _OR_1.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'mail', 'value_attr' => $conf['order']['mail'] ?? ''])],
        ['label_html' => _C_34.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => $conf['order']['anum'] ?? 25])],
        ['label_html' => _C_36.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => $conf['order']['anump'] ?? 10])],
        ['label_html' => _OR_2, 'field_html' => getTplRadioGroup(['name' => 'an', 'value' => (string)($conf['order']['an'] ?? 0), 'options' => $yesno])],
        ['label_html' => _OR_3, 'field_html' => getTplRadioGroup(['name' => 'pr', 'value' => (string)($conf['order']['pr'] ?? 0), 'options' => $yesno])],
        ['label_html' => _OR_4, 'field_html' => getTplRadioGroup(['name' => 'ad', 'value' => (string)($conf['order']['ad'] ?? 0), 'options' => $yesno])],
        ['label_html' => _OR_5.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'text', 'value' => $conf['order']['text'] ?? '', 'mod' => 'all', 'rows' => 5, 'placeholder' => _OR_5, 'required' => '1']), 'is_full' => true],
        ['label_html' => _OR_6.':', 'field_html' => getTplTextarea(['id' => '2', 'name' => 'info', 'value' => $conf['order']['info'] ?? '', 'mod' => 'all', 'rows' => 5, 'placeholder' => _OR_6, 'required' => '1']), 'is_full' => true],
        ['label_html' => _OR_7.':', 'field_html' => getTplTextarea(['id' => '3', 'name' => 'sendinfo', 'value' => $conf['order']['sendinfo'] ?? '', 'mod' => 'all', 'rows' => 5, 'placeholder' => _OR_7, 'required' => '1']), 'is_full' => true],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=order&amp;op=configsave',
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
        $cont = [
            'mail' => getVar('post', 'mail', 'text', ''),
            'anum' => getVar('post', 'anum', 'num', 25),
            'anump' => getVar('post', 'anump', 'num', 10),
            'an' => getVar('post', 'an', 'num', 0),
            'pr' => getVar('post', 'pr', 'num', 0),
            'ad' => getVar('post', 'ad', 'num', 0),
            'text' => getVar('post', 'text', 'text', ''),
            'info' => getVar('post', 'info', 'text', ''),
            'sendinfo' => getVar('post', 'sendinfo', 'text', ''),
        ];
        setConfigFile('order.php', $cont);
    }
    setRedirect($afile.'.php?name=order&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=order', 'name=order&amp;op=add', 'name=order&amp;op=config', 'name=order&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: order(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'activate': activate(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
