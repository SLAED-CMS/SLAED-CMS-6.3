<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('pages')) die('Illegal file access');

function pages(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['pages']['anum'] ?? 25;
    $anump = $conf['pages']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=pages&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=pages&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs]);
    }
    $result = $db->getSqlQuery('SELECT p.id, p.cid, p.name, p.title, p.time, p.ip, t.title, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_categories AS t ON (p.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE p.status = :status ORDER BY p.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = $cid ? $ctitle : _NO;
            $ip = $ip ? user_geo_ip($ip, 4) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $items = [];
            if ($status && time() >= strtotime($time)) {
                $items[] = ['href' => 'index.php?name=pages&amp;op=view&amp;id='.$id, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            $items[] = ['href' => $afile.'.php?name=pages&amp;op=add&amp;id='.$id, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=pages&amp;op=delete&amp;id='.$id.$refer.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('info-tooltip', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $ctitle],
                            ['label' => _DATE, 'value' => format_time($time, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip, 'is_last' => true],
                        ],
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['is_col_author' => true, 'content_html' => $post],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('row-actions', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _POSTEDBY, 'is_col_author' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_pages', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
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
    $pid = $id;
    if ($pid) {
        $result = $db->getSqlQuery('SELECT p.cid, p.name, p.title, p.time, p.intro, p.body, p.ihome, p.acomm, u.name FROM '.PREFIX_DB.'_pages AS p LEFT JOIN '.PREFIX_DB.'_users AS u ON (p.uid = u.id) WHERE id = :pid', ['pid' => $pid]);
        [$cat, $uname, $subject, $time, $hometext, $bodytext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $pid = getVar('post', 'pid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $time = getVar('req', 'time', 'time');
        $acomm = getVar('post', 'acomm', 'num', 0);
        $ihome = getVar('post', 'ihome', 'num', 0);
    }
    setHead();
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($hometext) $cont .= getTplPreviewContent(['title' => $subject, 'texta' => $hometext, 'textb' => $bodytext, 'mod' => 'pages']);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cat]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'pages\' ORDER BY ordern ASC');
    while ([$cid, $ctitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$cid,
            'label_text' => $ctitle,
            'is_selected' => (int)$cid === (int)$cat,
        ]);
    }
    $commopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows = [
        [
            'label_html' => _POSTEDBY.':',
            'field_html' => getTplUserSearchInput([
                'input_id' => 'postname',
                'list_id' => 'postname_list',
                'maxlength' => 25,
                'minlength' => (int)$conf['search']['slet'],
                'name' => 'postname',
                'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
                'value' => $postname,
            ]),
        ],
        ['label_html' => _TITLE.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'subject', 'value_attr' => $subject, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cat', 'options_html' => $catopts])],
        ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => 'pages', 'rows' => 5, 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _ENDTEXT.':', 'field_html' => getTplTextarea(['id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => 'pages', 'rows' => 15, 'placeholder' => _ENDTEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS.':', 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=pages&amp;op=save',
        'hidden' => [
            ['nameattr' => 'pid', 'valueattr' => (string)$pid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW]).$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND]).($pid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : ''), 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $pid = getVar('post', 'pid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $time = getVar('req', 'time', 'time');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$subject) $stop[] = _CERROR;
        if (!$hometext) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ?: 0;
            $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
            if ($pid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_pages SET cid = :cat, uid = :uid, name = :name, title = :title, time = :time, intro = :intro, body = :body, ihome = :ihome, acomm = :acomm, status = \'1\' WHERE id = :pid', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'pid' => $pid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_pages (id, cid, uid, name, title, time, intro, body, comments, counter, ihome, acomm, score, ratings, ip, status) VALUES (NULL, :cat, :uid, :name, :title, :time, :intro, :body, \'0\', \'0\', :ihome, :acomm, \'0\', \'0\', :ip, \'1\')', ['cat' => $cat, 'uid' => $postid, 'name' => $postname, 'title' => $subject, 'time' => $time, 'intro' => $hometext, 'body' => $bodytext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
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
        delete($pid);
        return;
    }
    setRedirect($afile.'.php?name=pages', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $did = 0): void {
    global $db, $afile;
    $id = $did ?: getVar('req', 'id', 'num', 0);
    $refer = getVar('req', 'refer', 'num', 0) ? '&status=1' : '';
    $iswarn = !$did && !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_comment WHERE cid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'pages\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_pages WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=pages'.$refer, false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ops = ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'];
    $tabs = [_HOME, _ADD, _NEW, _PREFERENCES, _INFO];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => $tabs, 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/pages.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['pages']['defis'] ?? '')])],
        ['label_html' => _PAGELINKNUM.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'value_attr' => $conf['pages']['linknum'] ?? 10])],
        ['label_html' => _C_13.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => $conf['pages']['listnum'] ?? 10])],
        ['label_html' => _C_33.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => $conf['pages']['num'] ?? 25])],
        ['label_html' => _C_34.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => $conf['pages']['anum'] ?? 25])],
        ['label_html' => _C_35.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => $conf['pages']['nump'] ?? 10])],
        ['label_html' => _C_36.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => $conf['pages']['anump'] ?? 10])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['pages']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['pages']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['pages']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['pages']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['pages']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_39, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['pages']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_40, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['pages']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['pages']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['pages']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['pages']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['pages']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['pages']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['pages']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=pages&amp;op=configsave',
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
            'linknum' => getVar('post', 'linknum', 'num', 10),
            'listnum' => getVar('post', 'listnum', 'num', 10),
            'num' => getVar('post', 'num', 'num', 25),
            'anum' => getVar('post', 'anum', 'num', 25),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'homcat' => getVar('post', 'homcat', 'num', 0),
            'viewcat' => getVar('post', 'viewcat', 'num', 0),
            'catdesc' => getVar('post', 'catdesc', 'num', 0),
            'subcat' => getVar('post', 'subcat', 'num', 0),
            'addmail' => getVar('post', 'addmail', 'num', 0),
            'add' => getVar('post', 'add', 'num', 0),
            'addquest' => getVar('post', 'addquest', 'num', 0),
            'autor' => getVar('post', 'autor', 'num', 0),
            'date' => getVar('post', 'date', 'num', 0),
            'read' => getVar('post', 'read', 'num', 0),
            'rate' => getVar('post', 'rate', 'num', 0),
            'letter' => getVar('post', 'letter', 'num', 0),
            'link' => getVar('post', 'link', 'num', 0),
        ];
        setConfigFile('pages.php', $cont);
    }
    setRedirect($afile.'.php?name=pages&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=pages', 'name=pages&amp;op=add', 'name=pages&amp;status=1', 'name=pages&amp;op=config', 'name=pages&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: pages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
