<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('links')) die('Illegal file access');

function links(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['links']['anum'] ?? 25;
    $anump = $conf['links']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    $status = getVar('get', 'status', 'num', 0);
    if ($status == 1) {
        $status = '0';
        $field = 'name=links&status=1&';
        $refer = '&refer=1';
        $cont = getTplAdminTabs(['ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS], 'tab' => 2]);
    } elseif ($status == 2) {
        $status = '2';
        $field = 'name=links&status=2&';
        $refer = '&refer=1';
        $cont = getTplAdminTabs(['ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS], 'tab' => 3]);
    } else {
        $status = '1';
        $field = 'name=links&';
        $refer = '&refer=1';
        $cont = getTplAdminTabs(['ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS]]);
    }
    $result = $db->getSqlQuery('SELECT l.id, l.cid, l.name, l.title, l.url, l.time, l.ip, c.title, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_categories AS c ON (l.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.status = :status ORDER BY l.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $cid, $uname, $title, $url, $date, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $items = [];
            if ($status && time() >= strtotime($date)) {
                $items[] = ['href' => 'index.php?name=links&op=view&id='.$id, 'icon_name' => 'eye', 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            if ($status == 2) {
                $items[] = ['href' => $afile.'.php?name=links&op=approve&id='.$id.'&token='.getSiteToken(), 'icon_name' => 'check2', 'title' => _IGNORE];
            }
            $items[] = ['href' => $afile.'.php?name=links&op=add&id='.$id, 'icon_name' => 'pencil', 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=links&op=delete&id='.$id.$refer.'&token='.getSiteToken(),
                'icon_name' => 'trash',
                'title' => _ONDELETE,
                'confirm_text' => _DELETE.' "'.$title.'"?',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cid ? $ctitle : _NO],
                            ['label' => _DATE, 'value' => format_time($date, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? Geoip::getIpHtml($ip) : _NO, 'is_last' => true],
                        ],
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['is_truncate' => true, 'title_text' => domain($url), 'content_html' => domain($url)],
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
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _SITEURL, 'is_truncate' => true],
                ['content' => _POSTEDBY, 'is_col_author' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_links', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
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
        $result = $db->getSqlQuery('SELECT l.cid, l.name, l.title, l.intro, l.body, l.url, l.time, l.email, l.ihome, l.acomm, u.name FROM '.PREFIX_DB.'_links AS l LEFT JOIN '.PREFIX_DB.'_users AS u ON (l.uid = u.id) WHERE l.id = :fid', ['fid' => $fid]);
        [$cid, $uname, $title, $description, $bodytext, $site, $date, $email, $ihome, $acomm, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $fid = getVar('post', 'fid', 'num', 0);
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $description = getVar('post', 'description', 'text', '');
        $bodytext = getVar('post', 'bodytext', 'text', '');
        $site = getVar('post', 'site', 'url', 'http://');
        $date = getVar('req', 'date', 'time');
        $ihome = getVar('post', 'ihome', 'num', 0);
        $acomm = getVar('post', 'acomm', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $email = getVar('post', 'email', 'text', '');
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => array_values((array)$stop)]);
    if ($description) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $description, 'textb' => $bodytext, 'mod' => 'links']);
    $link = ($site && $site !== 'http://') ? $tpl->getHtmlFrag('link', ['href' => $site, 'title' => _DOWNLLINK, 'label' => _URL, 'is_blank' => true]) : _URL;
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cid]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'links\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cid === (int)$catid,
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
        ['label_for' => 'f-title', 'label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title', 'value_attr' => $title, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_for' => 'f-cid', 'label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cid', 'selectid' => 'f-cid', 'options_html' => $catopts])],
        ['label_html' => _TEXT, 'field_html' => getTplTextarea([
            'id' => '1', 'name' => 'description', 'value' => $description, 'mod' => 'links', 'store' => 'links.intro', 'rows' => '5', 'placeholder' => _TEXT, 'required' => '1',
        ]), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _ENDTEXT, 'field_html' => getTplTextarea([
            'id' => '2', 'name' => 'bodytext', 'value' => $bodytext, 'mod' => 'links', 'store' => 'links.body', 'rows' => '15', 'placeholder' => _ENDTEXT, 'required' => '0',
        ]), 'is_full' => true, 'field_unwrapped' => true],
        ['label_for' => 'f-site', 'label_html' => $link, 'field_html' => $tpl->getHtmlFrag('input', ['name_attr' => 'site', 'input_id' => 'f-site', 'value_attr' => $site, 'placeholder_text' => _URL])],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'date', 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => _PUBHOME, 'field_html' => getTplRadioGroup(['name' => 'ihome', 'value' => (string)$ihome, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_for' => 'f-acomm', 'label_html' => _COMMENTS, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'acomm', 'selectid' => 'f-acomm', 'options_html' => $commopts])],
        ['label_for' => 'f-email', 'label_html' => _AUEMAIL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'email', 'input_id' => 'f-email', 'value_attr' => $email])],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($fid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=links&op=save',
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
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $description = getVar('post', 'description', 'text', '');
    $bodytext = getVar('post', 'bodytext', 'text', '');
    $site = getVar('post', 'site', 'url', '');
    $date = getVar('req', 'date', 'time');
    $ihome = getVar('post', 'ihome', 'num', 0);
    $acomm = getVar('post', 'acomm', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $email = getVar('post', 'email', 'text', '');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$title) $stop[] = _CERROR;
        if (!$description) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if ($room = checkEditorTextRoom($description, 'links.intro')) $stop[] = $room;
        if ($room = checkEditorTextRoom($bodytext, 'links.body')) $stop[] = $room;
        if (!$fid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_links WHERE title = :title', ['title' => $title])) > 0) $stop[] = _LINKEXIST;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ?: 0;
            $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
            if ($fid) {
                setContentActive('_links', [$fid], 21);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_links SET cid = :cid, uid = :uid, name = :name, title = :title, intro = :intro, body = :body, url = :url, time = :time, email = :email, ihome = :ihome, acomm = :acomm WHERE id = :fid', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $site, 'time' => $date, 'email' => $email, 'ihome' => $ihome, 'acomm' => $acomm, 'fid' => $fid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_links (cid, uid, name, title, intro, body, url, time, email, ip, ihome, acomm, status) VALUES (:cid, :uid, :name, :title, :intro, :body, :url, :time, :email, :ip, :ihome, :acomm, \'1\')', ['cid' => $cid, 'uid' => $postid, 'name' => $postname, 'title' => $title, 'intro' => $description, 'body' => $bodytext, 'url' => $site, 'time' => $date, 'email' => $email, 'ip' => $ip, 'ihome' => $ihome, 'acomm' => $acomm]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($fid);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    setRedirect($afile.'.php?name=links', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function approve(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num', 0);
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        setContentActive('_links', [$id], 21);
    }
    setRedirect($afile.'.php?name=links&status=2', false, 302, $iswarn ? _TOKENMISS : _SUCCSTATUS, $iswarn);
}

function delete(int $dfid = 0): void {
    global $db, $afile, $com;
    $id = $dfid ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$dfid && !checkSiteToken();
    if (!$iswarn && $id) {
        $com->deleteTarget('links', [$id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'links\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_links WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=links', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'], 'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS], 'tab' => 4]);
    $cont .= checkPerms(CONFIG_DIR.'/links.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_for' => 'f-defis', 'label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'input_id' => 'f-defis', 'value_attr' => urldecode($conf['links']['defis'] ?? ''), 'is_config' => true])],
        ['label_for' => 'f-linknum', 'label_html' => _PAGELINKNUM, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'linknum', 'input_id' => 'f-linknum', 'value_attr' => (string)($conf['links']['linknum'] ?? 0), 'is_config' => true])],
        ['label_for' => 'f-listnum', 'label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'listnum', 'input_id' => 'f-listnum', 'value_attr' => (string)($conf['links']['listnum'] ?? 0), 'is_config' => true])],
        ['label_for' => 'f-num', 'label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'input_id' => 'f-num', 'value_attr' => (string)($conf['links']['num'] ?? 0), 'is_config' => true])],
        ['label_for' => 'f-anum', 'label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'input_id' => 'f-anum', 'value_attr' => (string)($conf['links']['anum'] ?? 0), 'is_config' => true])],
        ['label_for' => 'f-nump', 'label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'input_id' => 'f-nump', 'value_attr' => (string)($conf['links']['nump'] ?? 0), 'is_config' => true])],
        ['label_for' => 'f-anump', 'label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'input_id' => 'f-anump', 'value_attr' => (string)($conf['links']['anump'] ?? 0), 'is_config' => true])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['links']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _VIEWCAT, 'field_html' => getTplRadioGroup(['name' => 'viewcat', 'value' => (string)($conf['links']['viewcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['links']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['links']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['links']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _L_8, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['links']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _L_9, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['links']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _L_11, 'field_html' => getTplRadioGroup(['name' => 'broc', 'value' => (string)($conf['links']['broc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _L_12, 'field_html' => getTplRadioGroup(['name' => 'links', 'value' => (string)($conf['links']['links'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_37, 'field_html' => getTplRadioGroup(['name' => 'autor', 'value' => (string)($conf['links']['autor'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['links']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_18, 'field_html' => getTplRadioGroup(['name' => 'read', 'value' => (string)($conf['links']['read'] ?? 0), 'options' => $yesno])],
        ['label_html' => _L_1, 'field_html' => getTplRadioGroup(['name' => 'hits', 'value' => (string)($conf['links']['hits'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['links']['rate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_20, 'field_html' => getTplRadioGroup(['name' => 'letter', 'value' => (string)($conf['links']['letter'] ?? 0), 'options' => $yesno])],
        ['label_html' => _PAGELINK, 'field_html' => getTplRadioGroup(['name' => 'link', 'value' => (string)($conf['links']['link'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=links&op=configsave',
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
            'broc' => getVar('post', 'broc', 'num', 0),
            'links' => getVar('post', 'links', 'num', 0),
            'autor' => getVar('post', 'autor', 'num', 0),
            'date' => getVar('post', 'date', 'num', 0),
            'read' => getVar('post', 'read', 'num', 0),
            'hits' => getVar('post', 'hits', 'num', 0),
            'rate' => getVar('post', 'rate', 'num', 0),
            'letter' => getVar('post', 'letter', 'num', 0),
            'link' => getVar('post', 'link', 'num', 0),
        ];
        setConfigFile('links.php', $cont);
    }
    setRedirect($afile.'.php?name=links&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=links', 'name=links&op=add', 'name=links&status=1', 'name=links&status=2', 'name=links&op=config', 'name=links&op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _BROCLINKS, _PREFERENCES, _DOCS],
    ]);
}

switch ($op) {
    default: links(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'approve': approve(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
