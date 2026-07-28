<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('faq')) die('Illegal file access');

function faq(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['faq']['anum'] ?? 25;
    $anump = $conf['faq']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=faq&status=1&';
        $refer = '&op=faq&status=1';
        $cont = getTplAdminTabs(['ops' => ['name=faq', 'name=faq&op=add', 'name=faq&status=1', 'name=faq&op=config', 'name=faq&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=faq&';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=faq', 'name=faq&op=add', 'name=faq&status=1', 'name=faq&op=config', 'name=faq&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS]]);
    }
    $result = $db->getSqlQuery('SELECT f.id, f.cid, f.name, f.title, f.time, f.ip, t.title, u.name FROM '.PREFIX_DB.'_faq AS f LEFT JOIN '.PREFIX_DB.'_categories AS t ON (f.cid = t.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id) WHERE f.status = :status ORDER BY f.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $time, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $ctitle = ($cid) ? $ctitle : _NO;
            $ip = ($ip) ? Geoip::getIpHtml($ip) : _NO;
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            if ($status == '1' && time() >= strtotime($time)) {
                $view = 'index.php?name=faq&op=view&id='.$id;
                $active = '1';
            } else {
                $view = '';
                $active = '0';
            }
            $items = [];
            if ($view) {
                $items[] = ['href' => $view, 'icon_name' => 'eye', 'title' => _MVIEW];
            }
            $items[] = ['href' => $afile.'.php?name=faq&op=add&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=faq&op=delete&id='.$id.($refer ? '&refer=1' : '').'&token='.getSiteToken(),
                'icon_name' => 'trash',
                'title' => _ONDELETE,
                'confirm_text' => _DELETE.' "'.$title.'"?',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
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
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', ['dial_title' => _FUNCTIONS, 'dial' => $items])],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _QUESTION, 'is_truncate' => true],
                ['content' => _POSTEDBY, 'is_col_author' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_faq', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO])]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl, $conf;
    $id = getVar('req', 'id', 'num', 0);
    $fid = $id;
    if ($fid) {
        $result = $db->getSqlQuery('SELECT s.cid, s.name, s.title, s.time, s.body, s.ihome, s.acomm, u.name FROM '.PREFIX_DB.'_faq AS s LEFT JOIN '.PREFIX_DB.'_users AS u ON (s.uid = u.id) WHERE s.id = :fid', ['fid' => $fid]);
        [$cat, $uname, $subject, $time, $hometext, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $subject = getVar('post', 'subject', 'title', '');
        $time = getVar('req', 'time', 'time');
        $cat = getVar('post', 'cat', 'num', 0);
        $hometext = getVar('post', 'hometext', 'text', '');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=faq', 'name=faq&op=add', 'name=faq&status=1', 'name=faq&op=config', 'name=faq&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _PAGENOTE]);
    if ($hometext) $cont .= getTplPreviewContent(['title' => $subject, 'texta' => $hometext, 'mod' => 'faq']);
    $catopts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '',
        'label_text' => _HOMECAT,
        'is_selected' => !$cat,
    ]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'faq\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cat === (int)$catid,
        ]);
    }
    $commopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _DEACTIVATE, 'is_selected' => $acomm == 0])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _APOSTMOD, 'is_selected' => $acomm == 1])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _APOSTNOMOD, 'is_selected' => $acomm == 2]);
    $rows = [
        ['label_html' => _POSTEDBY, 'field_html' => getTplUserSearchInput([
            'input_id' => 'postname',
            'list_id' => 'postname_list',
            'maxlength' => 25,
            'minlength' => (int)$conf['search']['slet'],
            'name' => 'postname',
            'tip' => sprintf(_USERSEARCHTIP, (int)$conf['search']['slet']),
            'value' => $postname,
        ])],
        ['label_html' => _TITLE.' / '._QUESTION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'subject', 'value_attr' => $subject, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cat', 'options_html' => $catopts])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'options_html' => $commopts])],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
        ['label_html' => _ANSWER, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'hometext', 'value' => $hometext, 'mod' => 'faq', 'rows' => '10', 'placeholder' => _ANSWER, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($fid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=faq&op=save',
        'hidden' => [
            ['nameattr' => 'fid', 'valueattr' => (string)$fid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'posttype', 'options_html' => $posttypeopts, 'is_inline_gap' => true])
            .$tpl->getHtmlFrag('button', ['submit_label' => _OK, 'button_type' => 'submit']),
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $fid = getVar('post', 'fid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $subject = getVar('post', 'subject', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $hometext = getVar('post', 'hometext', 'text', '');
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
        if (!$stop && $posttype == 'save') {
            $postid = (is_user_id($postname)) ? is_user_id($postname) : 0;
            $postname = (!is_user_id($postname)) ? filterText(substr($postname, 0, 25)) : '';
            if ($fid) {
                setContentActive('_faq', [$fid], 6);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_faq SET cid = :cat, uid = :postid, name = :postname, title = :subject, time = :time, body = :body, ihome = :ihome, acomm = :acomm WHERE id = :fid', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_faq (cid, uid, name, title, time, body, ihome, acomm, ip, status) VALUES (:cat, :postid, :postname, :subject, :time, :body, :ihome, :acomm, :ip, \'1\')', ['cat' => $cat, 'postid' => $postid, 'postname' => $postname, 'subject' => $subject, 'time' => $time, 'body' => $hometext, 'ihome' => $ihome, 'acomm' => $acomm, 'ip' => $ip]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype == 'delete') {
        delete($fid);
        return;
    }
    setRedirect($afile.'.php?name=faq', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $fid = 0): void {
    global $db, $afile, $com;
    $id = $fid ? $fid : getVar('req', 'id', 'num', 0);
    $iswarn = !$fid && !checkSiteToken();
    if (!$iswarn && $id) {
        $com->deleteTarget('faq', [$id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'faq\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_faq WHERE id = :id', ['id' => $id]);
    }
    $refer = getVar('get', 'refer', 'num', 0) ? '&status=1' : '';
    setRedirect($afile.'.php?name=faq'.$refer, false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=faq', 'name=faq&op=add', 'name=faq&status=1', 'name=faq&op=config', 'name=faq&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/faq.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['faq']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _PAGELINKNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'value_attr' => (string)($conf['faq']['linknum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'value_attr' => (string)($conf['faq']['listnum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['faq']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['faq']['anum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)($conf['faq']['nump'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['faq']['anump'] ?? 0), 'is_config' => true])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['faq']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['faq']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['faq']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['faq']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['faq']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_39, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['faq']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_40, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['faq']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['faq']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['faq']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['faq']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['faq']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['faq']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['faq']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=faq&op=configsave',
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
        setConfigFile('faq.php', $cont);
    }
    setRedirect($afile.'.php?name=faq&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=faq', 'name=faq&op=add', 'name=faq&status=1', 'name=faq&op=config', 'name=faq&op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: faq(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
