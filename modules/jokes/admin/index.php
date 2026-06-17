<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('jokes')) die('Illegal file access');

function jokes(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['jokes']['anum'] ?? 25;
    $anump = $conf['jokes']['anump'] ?? 10;
    $offset = (int)(($num - 1) * $anum);
    if (getVar('get', 'status', 'num', 0) == 1) {
        $status = '0';
        $field = 'name=jokes&amp;status=1&amp;';
        $refer = '&amp;refer=1';
        $cont = getTplAdminTabs(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 2]);
    } else {
        $status = '1';
        $field = 'name=jokes&amp;';
        $refer = '';
        $cont = getTplAdminTabs(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO]]);
    }
    $result = $db->getSqlQuery('SELECT j.id, j.name, j.time, j.title, j.cid, j.ip, c.title, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_categories AS c ON (j.cid = c.id) LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.status = :status ORDER BY j.time DESC LIMIT '.$offset.', '.$anum, ['status' => $status]);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$jokeid, $uname, $date, $title, $cat, $ip, $ctitle, $nick] = $db->getSqlRow($result)) {
            $post = $nick ? user_info($nick) : ($uname ?: _ANONYM);
            $items = [];
            if ($status && time() >= strtotime($date)) {
                $items[] = ['href' => 'index.php?name=jokes&amp;cat='.$cat.'#'.$jokeid, 'label' => _MVIEW, 'title' => _MVIEW];
                $active = '1';
            } else {
                $active = '0';
            }
            $items[] = ['href' => $afile.'.php?name=jokes&amp;op=add&amp;id='.$jokeid, 'label' => _FULLEDIT, 'title' => _FULLEDIT];
            $items[] = [
                'href' => $afile.'.php?name=jokes&amp;op=delete&amp;id='.$jokeid.$refer.'&amp;token='.getSiteToken(),
                'label' => _ONDELETE,
                'title' => _ONDELETE,
                'onclick_attr' => ' OnClick="return confirm(\''._DELETE.' &quot;'.addslashes($title).'&quot;?\')"',
            ];
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$jokeid],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('popover', [
                        'items' => [
                            ['label' => _CATEGORY, 'value' => $cat ? $ctitle : _NO],
                            ['label' => _DATE, 'value' => format_time($date, _TIMESTRING)],
                            ['label' => _IP, 'value' => $ip ? Geoip::getIpHtml($ip) : _NO, 'is_last' => true],
                        ],
                        'label_text' => $title,
                        'title_text' => $title,
                    ])],
                    ['is_col_author' => true, 'content_html' => $post],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('popover', ['trigger_label' => _FUNCTIONS, 'items' => $items])],
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
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => $field, 'table' => '_jokes', 'field' => 'id', 'where' => 'status = \''.$status.'\'']);
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
    $jokeid = $id;
    if ($jokeid) {
        $result = $db->getSqlQuery('SELECT j.id, j.name, j.time, j.title, j.cid, j.body, u.name FROM '.PREFIX_DB.'_jokes AS j LEFT JOIN '.PREFIX_DB.'_users AS u ON (j.uid = u.id) WHERE j.id = :jokeid', ['jokeid' => $jokeid]);
        [$jokeid, $uname, $date, $title, $cat, $joke, $nick] = $db->getSqlRow($result);
        $postname = $nick ?: ($uname ?: _ANONYM);
    } else {
        $jokeid = getVar('post', 'jokeid', 'num', 0);
        $postname = getVar('post', 'postname', 'name', '');
        $date = getVar('req', 'date', 'time');
        $title = getVar('post', 'title', 'title', '');
        $cat = getVar('post', 'cat', 'num', 0);
        $joke = getVar('post', 'joke', 'text', '');
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    if ($joke) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $joke, 'mod' => 'all']);
    $catopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _HOMECAT, 'is_selected' => !$cat]);
    $catres = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_categories WHERE modul = \'jokes\' ORDER BY ordern ASC');
    while ([$catid, $cattitle] = $db->getSqlRow($catres)) {
        $catopts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => (string)$catid,
            'label_text' => $cattitle,
            'is_selected' => (int)$cat === (int)$catid,
        ]);
    }
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
        ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => $title, 'maxlength_num' => 255, 'is_required' => true])],
        ['label_html' => _CATEGORY, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'cat', 'options_html' => $catopts])],
        ['label_html' => _CHNGSTORY, 'field_html' => getTplAddDateTime(['name' => 'date', 'time' => $date, 'with' => true, 'max' => 16])],
        ['label_html' => _JOKE, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'joke', 'value' => $joke, 'mod' => 'jokes', 'rows' => '10', 'placeholder' => _JOKE, 'required' => '1']), 'is_full' => true, 'field_unwrapped' => true],
    ];
    $posttypeopts
        = $tpl->getHtmlFrag('select-option', ['value_attr' => 'preview', 'label_text' => _PREVIEW])
        .$tpl->getHtmlFrag('select-option', ['value_attr' => 'save', 'label_text' => _SEND])
        .($jokeid ? $tpl->getHtmlFrag('select-option', ['value_attr' => 'delete', 'label_text' => _DELETE]) : '');
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=jokes&amp;op=save',
        'hidden' => [
            ['nameattr' => 'jokeid', 'valueattr' => (string)$jokeid],
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
    $jokeid = getVar('post', 'jokeid', 'num', 0);
    $postname = getVar('post', 'postname', 'name', '');
    $date = getVar('req', 'date', 'time');
    $title = getVar('post', 'title', 'title', '');
    $cat = getVar('post', 'cat', 'num', 0);
    $joke = getVar('post', 'joke', 'text', '');
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$title) $stop[] = _CERROR;
        if (!$joke) $stop[] = _CERROR1;
        if (!$postname) $stop[] = _CERROR3;
        if (!$jokeid && $db->getSqlRowCount($db->getSqlQuery('SELECT title FROM '.PREFIX_DB.'_jokes WHERE title = :title', ['title' => $title])) > 0) $stop[] = _JOKEEXIST;
        if (!$stop && $posttype === 'save') {
            $postid = is_user_id($postname) ?: 0;
            $postname = !is_user_id($postname) ? filterText(substr($postname, 0, 25)) : '';
            if ($jokeid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_jokes SET uid = :uid, name = :name, time = :time, title = :title, cid = :cat, body = :joke, status = \'1\' WHERE id = :jokeid', ['uid' => $postid, 'name' => $postname, 'time' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'jokeid' => $jokeid]);
            } else {
                $ip = getip();
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_jokes (uid, name, time, title, cid, body, ip, status) VALUES (:uid, :name, :time, :title, :cat, :joke, :ip, \'1\')', ['uid' => $postid, 'name' => $postname, 'time' => $date, 'title' => $title, 'cat' => $cat, 'joke' => $joke, 'ip' => $ip]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype === 'delete') {
        delete($jokeid);
        return;
    }
    if ($posttype === 'preview') {
        add();
        return;
    }
    setRedirect($afile.'.php?name=jokes', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $fid = 0): void {
    global $db, $afile;
    $id = $fid ?: getVar('req', 'id', 'num', 0);
    $iswarn = !$fid && !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE fid = :id AND modul = \'jokes\'', ['id' => $id]);
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_jokes WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=jokes', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'], 'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO], 'tab' => 3]);
    $cont .= checkPerms(CONFIG_DIR.'/jokes.php');
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ['label_html' => _CDEFIS, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'defis', 'value_attr' => urldecode($conf['jokes']['defis'] ?? ''), 'is_config' => true])],
        ['label_html' => _C_33, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => (string)($conf['jokes']['num'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_34, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => (string)($conf['jokes']['anum'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_35, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => (string)($conf['jokes']['nump'] ?? 0), 'is_config' => true])],
        ['label_html' => _C_36, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => (string)($conf['jokes']['anump'] ?? 0), 'is_config' => true])],
        ['label_html' => _HOMCAT, 'field_html' => getTplRadioGroup(['name' => 'homcat', 'value' => (string)($conf['jokes']['homcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_32, 'field_html' => getTplRadioGroup(['name' => 'catdesc', 'value' => (string)($conf['jokes']['catdesc'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_15, 'field_html' => getTplRadioGroup(['name' => 'subcat', 'value' => (string)($conf['jokes']['subcat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _ADDAMAIL, 'field_html' => getTplRadioGroup(['name' => 'addmail', 'value' => (string)($conf['jokes']['addmail'] ?? 0), 'options' => $yesno])],
        ['label_html' => _J_1, 'field_html' => getTplRadioGroup(['name' => 'add', 'value' => (string)($conf['jokes']['add'] ?? 0), 'options' => $yesno])],
        ['label_html' => _J_2, 'field_html' => getTplRadioGroup(['name' => 'addquest', 'value' => (string)($conf['jokes']['addquest'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_17, 'field_html' => getTplRadioGroup(['name' => 'date', 'value' => (string)($conf['jokes']['date'] ?? 0), 'options' => $yesno])],
        ['label_html' => _C_19, 'field_html' => getTplRadioGroup(['name' => 'rate', 'value' => (string)($conf['jokes']['rate'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=jokes&amp;op=configsave',
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
            'num' => getVar('post', 'num', 'num', 25),
            'anum' => getVar('post', 'anum', 'num', 25),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
            'homcat' => getVar('post', 'homcat', 'num', 0),
            'catdesc' => getVar('post', 'catdesc', 'num', 0),
            'subcat' => getVar('post', 'subcat', 'num', 0),
            'addmail' => getVar('post', 'addmail', 'num', 0),
            'add' => getVar('post', 'add', 'num', 0),
            'addquest' => getVar('post', 'addquest', 'num', 0),
            'date' => getVar('post', 'date', 'num', 0),
            'rate' => getVar('post', 'rate', 'num', 0),
        ];
        setConfigFile('jokes.php', $cont);
    }
    setRedirect($afile.'.php?name=jokes&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=jokes', 'name=jokes&amp;op=add', 'name=jokes&amp;status=1', 'name=jokes&amp;op=config', 'name=jokes&amp;op=info'],
        'tabs' => [_HOME, _ADD, _NEW, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: jokes(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
