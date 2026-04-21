<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('help')) die('Illegal file access');

function help(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['help']['anum'] ?? 25;
    $anump = $conf['help']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '1';
        $field = 'name=help&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = getTplAdminTabs(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO], 'tab' => 1]);
    } else {
        $status = '0';
        $field = 'name=help&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.comments, s.ip, s.status, c.title, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.pid = \'0\' AND s.status = :status ORDER BY s.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $title, $time, $comments, $ip, $stat, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : _ANONYM;
            $items = [
                ['href' => $afile.'.php?name=help&amp;op=view&amp;id='.$id, 'label' => _MVIEW, 'title' => _MVIEW],
                [
                    'href' => $afile.'.php?name=help&amp;op=delete&amp;id='.$id.$refer.'&amp;token='.getSiteToken(),
                    'label' => _ONDELETE,
                    'title' => _ONDELETE,
                    'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
                ],
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['content_html' => (string)$id],
                    ['content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cid ? $ctitle : _NO],
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? user_geo_ip($ip, 4) : _NO, 'is_last' => true],
                        ],
                        'label_text' => cutstr($title, 60),
                        'title_text' => $title,
                    ])],
                    ['content_html' => $post],
                    ['content_html' => (string)$comments],
                    ['content_html' => ad_status('', $stat ? 0 : 1)],
                    ['content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'head' => [
                ['content' => _ID],
                ['content' => _TITLE],
                ['content' => _POSTEDBY],
                ['content' => cutstr(_MESSAGES, 4, 1)],
                ['content' => _STATUS, 'nosort' => true],
                ['content' => _FUNCTIONS, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_help', 'field' => 'id', 'where' => 'pid = \'0\' AND status = \''.$status.'\'']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $tpl, $prs;
    $vid = (int)(getVar('get', 'id', 'num', 0) ?? 0);
    $result = $db->getSqlQuery('SELECT s.id, s.pid, s.uid, s.aid, s.title, s.time, s.body, s.field, s.counter, s.score, s.ratings, c.title, c.intro, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_categories AS c ON (s.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :id1 OR s.pid = :id2 AND s.time <= now() ORDER BY s.time ASC', ['id1' => $vid, 'id2' => $vid]);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    $body = '';
    $a = 0;
    while ([$id, $pid, $huid, $haid, $title, $time, $hometext, $field, $counter, $score, $ratings, $ctitle, $cdesc, $nick] = $db->getSqlRow($result)) {
        $title = $title ?: _MESSAGE.': '.$a;
        $fields = getTplViewFieldRows(['field' => $field, 'mod' => 'help']);
        $text = $prs->filterContent($hometext.(($fields) ? PHP_EOL.PHP_EOL.$fields : ''), false, 'help');
        $meta = [];
        if (!$pid) {
            $meta[] = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-cat', 'label' => ($ctitle ?: _NO)]);
            $meta[] = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-views', 'label' => (string)$counter]);
        }
        $meta[] = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-post-icon', 'label_html' => ($nick ? user_info($nick) : _ANONYM)]);
        $meta[] = $tpl->getHtmlFrag('inline-badge', ['class' => 'sl-date', 'label' => format_time($time, _TIMESTRING)]);
        if ($a) {
            $meta[] = $tpl->getHtmlFrag('link', ['href' => '#'.$id, 'class' => 'sl-pnum', 'title' => _MESSAGE.': '.$a, 'label' => (string)$a]);
        }
        $actions = $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => [
            ['href' => $afile.'.php?name=help&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT],
            [
                'href' => $afile.'.php?name=help&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ],
        ]]);
        $rating = ($haid && $huid != $haid) ? $tpl->getHtmlPart('div', ['class' => 'rate-box pull-right', 'content_html' => getRatingAsync(0, $id, 'help', $ratings, $score, '')]) : '';
        $body .= $tpl->getHtmlPart('preview', [
            'id' => (string)$id,
            'title' => $title,
            'body_a' => $text,
            'body_b' => implode(' ', $meta),
            'body_c' => $rating.$actions,
        ]);
        $a++;
    }
    $cont .= $body;
    $cont .= addview($vid);
    echo $cont;
    setFoot();
}

function addview(int $id): string {
    global $db, $afile, $admin, $conf, $tpl;
    $result = $db->getSqlQuery('SELECT cid, uid, status FROM '.PREFIX_DB.'_help WHERE id = :id', ['id' => $id]);
    [$cid, $uid, $status] = $db->getSqlRow($result);
    return $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=help&amp;op=save',
        'hidden' => [
            ['nameattr' => 'pid', 'valueattr' => (string)$id],
            ['nameattr' => 'cat', 'valueattr' => (string)$cid],
            ['nameattr' => 'uid', 'valueattr' => (string)$uid],
            ['nameattr' => 'refer', 'valueattr' => '1'],
            ['nameattr' => 'posttype', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => [
            ['label_html' => _POSTEDBY.':', 'field_html' => getTplUserSearchInput([
                'input_id' => 'postname',
                'list_id' => 'postname_list',
                'maxlength' => 25,
                'minlength' => (int)$conf['search']['slet'],
                'name' => 'postname',
                'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
                'value' => (string)($admin[1] ?? ''),
            ])],
            ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => '', 'mod' => 'help', 'rows' => '10', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
            ['label_html' => _HELPGLOS, 'field_html' => getTplRadioGroup(['name' => 'status', 'value' => (string)$status, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
            ['label_html' => _MAIL_SENDE.':', 'field_html' => getTplRadioGroup(['name' => 'umail', 'value' => '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ],
        'submit_label' => _SEND,
    ])]);
}

function add(): void {
    global $db, $afile, $stop, $tpl, $conf;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT s.pid, s.cid, s.title, s.time, s.body, s.field, s.status, u.name FROM '.PREFIX_DB.'_help AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.aid = u.id) WHERE s.id = :id', ['id' => $id]);
        [$pid, $cat, $subject, $time, $hometext, $field, $status, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: _ANONYM;
    } else {
        $id = getVar('post', 'id', 'num', 0);
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $field = getVar('post', 'field', 'field');
    }
    $status = getVar('post', 'status', 'num', 0) ? getVar('post', 'status', 'num', 0) : ($status ?? 0);
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO]]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($hometext) $cont .= getTplPreviewContent(['title' => $subject, 'texta' => $hometext, 'field' => $field, 'mod' => 'help']);
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cat]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'help\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cat === (int)$catid,
        ]);
    }
    $rows = [
        ['label_html' => _POSTEDBY.':', 'field_html' => getTplUserSearchInput([
            'input_id' => 'postname',
            'list_id' => 'postname_list',
            'maxlength' => 25,
            'minlength' => (int)$conf['search']['slet'],
            'name' => 'postname',
            'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
            'value' => $postname,
        ])],
        ['label_html' => _TITLE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'subject', 'value_attr' => $subject, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cat', 'options_html' => $catopts])],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
        ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => 'help', 'rows' => '10', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => '', 'field_html' => getTplFieldsIn(['field' => $field, 'mod' => 'help']), 'is_full' => true],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($id ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=help&amp;op=save',
        'hidden' => [
            ['nameattr' => 'id', 'valueattr' => (string)$id],
            ['nameattr' => 'pid', 'valueattr' => (string)$pid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'select_class' => 'sl-inline-gap'])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $admin, $conf, $stop, $tpl;
    $id = getVar('post', 'id', 'num', 0);
    $pid = getVar('post', 'pid', 'num', 0);
    $uid = getVar('post', 'uid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $field = getVar('post', 'field', 'field');
    $time = getVar('req', 'time', 'time');
    $status = getVar('post', 'status', 'num', 0);
    $umail = getVar('post', 'umail', 'text', '');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$subject && !$pid) $stop[] = _CERROR;
        if (!$hometext && !$pid) $stop[] = _CERROR1;
        if (!$postname && !$pid) $stop[] = _CERROR3;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ? is_user_id($postname) : 0;
            if ($id) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET cid = :cat, aid = :postid, title = :subject, time = :time, body = :body, field = :field WHERE id = :id', ['cat' => $cat, 'postid' => $postid, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'field' => $field, 'id' => $id]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_help (pid, cid, uid, aid, title, time, body, field, ip, status) VALUES (:pid, :cat, :uid, :postid, :subject, now(), :body, \'\', :ip, \'0\')', ['pid' => $pid, 'cat' => $cat, 'uid' => $uid, 'postid' => $postid, 'subject' => $subject, 'body' => $hometext, 'ip' => $ip]);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_help SET comments = comments+1, status = :status WHERE id = :pid', ['status' => $status, 'pid' => $pid]);
                if ($umail) {
                    $result = $db->getSqlQuery('SELECT email FROM '.PREFIX_DB.'_users WHERE id = :uid', ['uid' => $uid]);
                    if ($db->getSqlRowCount($result) == 1) {
                        [$mail] = $db->getSqlRow($result);
                        $finishlink = ($conf['homeurl'] ?? '').'/index.php?name=help&amp;op=view&amp;id='.$pid;
                        $subject = ($conf['sitename'] ?? '').' - '._HELP;
                        $message = str_replace('[text]', sprintf(_ADDMAILU, substr($admin[1] ?? '', 0, 25), _HELP, $finishlink), $conf['mtemp'] ?? '');
                        addMail($mail, $conf['adminmail'] ?? '', $subject, $message, 0, 3);
                    }
                }
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($id);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    $url = ($id || !$pid) ? $afile.'.php?name=help&op=view&id='.($pid ? $pid : $id) : $afile.'.php?name=help';
    if (!$id && $pid) $url = $afile.'.php?name=help';
    setRedirect($url, false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$fid && !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'help\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_help WHERE id = :id1 OR pid = :id2', ['id1' => $id, 'id2' => $id]);
    }
    setRedirect($afile.'.php?name=help', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'], 'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/help.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['help']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['help']['listnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['help']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['help']['anum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)($conf['help']['nump'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['help']['anump'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['help']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['help']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['help']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _HELPADD, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['help']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['help']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['help']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['help']['letter'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=help&amp;op=configsave',
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
            'defis' => getVar('post', 'defis', 'defis', '%3E'),
            'listnum' => getVar('post', 'listnum', 'num', 10),
            'num' => getVar('post', 'num', 'num', 25),
            'anum' => getVar('post', 'anum', 'num', 25),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'catdesc' => getVar('post', 'catdesc', 'num', 0),
            'subcat' => getVar('post', 'subcat', 'num', 0),
            'addmail' => getVar('post', 'addmail', 'num', 0),
            'add' => getVar('post', 'add', 'num', 0),
            'date' => getVar('post', 'date', 'num', 0),
            'read' => getVar('post', 'read', 'num', 0),
            'letter' => getVar('post', 'letter', 'num', 0),
        ];
        setConfigFile('help.php', $cont);
    }
    setRedirect($afile.'.php?name=help&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=help', 'name=help&amp;status=1', 'name=help&amp;op=config', 'name=help&amp;op=info'],
        'tabs' => [_HOME, _CLOSED, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: help(); break;
    case 'view': view(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
