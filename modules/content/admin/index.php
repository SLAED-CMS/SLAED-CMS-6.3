<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');

function content(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $ops = ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['content']['anum'] ?? 10;
    $anump = $conf['content']['anump'] ?? 10;
    $offset = ($num - 1) * $anum;
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $title, $time, $counter] = $db->getSqlRow($result)) {
            $view = (time() >= strtotime($time)) ? 'index.php?name=content&amp;op=view&amp;id='.$id : '';
            $active = $view ? '1' : '0';
            $acts = $tpl->getHtmlFrag('edit-actions', [
                'editor_label' => _EDITOR,
                'view_link' => $view ? ['href' => $view, 'title' => _MVIEW, 'label' => _MVIEW] : [],
                'edit_link' => ['href' => $afile.'.php?name=content&amp;op=add&amp;id='.$id, 'title' => _FULLEDIT, 'label' => _FULLEDIT],
                'delete_link' => [
                    'href' => $afile.'.php?name=content&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                    'confirm_text' => _DELETE.' "'.$title.'"?',
                    'title' => _ONDELETE,
                    'label' => _ONDELETE,
                    'is_delete' => true,
                ],
            ]);
            $tip = $tpl->getHtmlFrag('info-tooltip', [
                'items' => [
                    ['label' => _URL, 'value' => $conf['homeurl'].'/index.php?name=content&amp;op=view&amp;id='.$id, 'is_last' => false],
                    ['label' => _ORTYPEURL, 'value' => $conf['homeurl'].'/index.php?go=rss&amp;name=content&amp;id='.$id, 'is_last' => true],
                ],
            ]);
            $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$id],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tip.htmlspecialchars((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
                    ['is_col_date' => true, 'content_html' => format_time($time, _TIMESTRING)],
                    ['is_col_count' => true, 'content_html' => (string)$counter],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $acts],
                ],
            ])]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _DATE, 'is_col_date' => true],
                ['content' => cutstr(_READS, 4, 1), 'is_col_count' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $body .= getTplPager(['limit' => $anum, 'maxpg' => $anump, 'url' => 'name=content&amp;', 'table' => '_content', 'field' => 'id']);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', [
            'content_html' => $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]),
        ]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num', 0);
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, body, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
        [$cid, $title, $body, $field, $url, $time, $refresh] = $db->getSqlRow($result);
    } else {
        $cid = getVar('post', 'cid', 'num', 0);
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', '');
        $fieldp = getVar('post', 'field[]', 'raw', []);
        $field = is_array($fieldp) ? filterFields($fieldp) : '';
        $url = getVar('post', 'url', 'text', '');
        $time = getVar('req', 'time', 'time');
        $refresh = getVar('post', 'refresh', 'num', 0);
    }
    setHead();
    $ops = ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'lines' => array_values((array)$stop)]);
    $prev = [
        'title' => $title,
        'texta' => $body,
        'field' => $field,
        'mod' => 'content',
    ];
    $cont .= getTplPreviewContent($prev);
    $rows = [
        [
            'label_html' => _TITLE.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'is_required' => true,
                'itype' => 'text',
                'maxlength_num' => 100,
                'name_attr' => 'title',
                'placeholder_text' => _TITLE,
                'value_attr' => $title,
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _RSSFILE, 'hint' => _RSSINFO]),
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'maxlength_num' => 200,
                'name_attr' => 'url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => $url,
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _REFRESHTIME, 'hint' => _REFINFO]),
            'field_html' => getTplRefreshTimeSelect(['valu' => $refresh]),
        ],
        ['label_html' => _TEXT.':', 'field_html' => getTplTextarea(['id' => '1', 'name' => 'body', 'value' => $body, 'mod' => 'content', 'rows' => '25', 'placeholder' => _TEXT, 'required' => '0']), 'is_full' => true, 'field_unwrapped' => true],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
    ];
    $rows = array_merge($rows, getTplAddFieldRows(['field' => $field, 'mod' => 'content']));
    $actions = [
        'hasname' => (bool)$cid,
        'cid_hidden' => ['name_attr' => 'cid', 'value_attr' => (string)$cid],
        'op_hidden' => ['name_attr' => 'op', 'value_attr' => 'save'],
        'select' => [
            'name_attr' => 'posttype',
            'is_save_action' => true,
            'options' => [
                ['value_attr' => 'preview', 'label_text' => _PREVIEW],
                ['value_attr' => 'save', 'label_text' => _SEND],
            ],
        ],
        'submit' => ['button_type' => 'submit', 'submit_label' => _OK],
    ];
    if ($cid) $actions['select']['options'][] = ['value_attr' => 'delete', 'label_text' => _DELETE];
    $hide = [
        ['nameattr' => 'name', 'valueattr' => 'content'],
        ['nameattr' => 'token', 'valueattr' => getSiteToken()],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=content&amp;op=add',
        'actions' => $actions,
        'hidden' => $hide,
        'rows' => $rows,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'text', '');
    $body = getVar('post', 'body', 'text', '');
    $fieldp = getVar('post', 'field[]', 'raw', []);
    $field = is_array($fieldp) ? filterFields($fieldp) : '';
    $time = getVar('req', 'time', 'time');
    $refresh = getVar('post', 'refresh', 'num', 0);
    $posttype = getVar('post', 'posttype', 'text', '');
    $iswarn = !checkSiteToken();
    if (!$iswarn) {
        $body = ($url) ? rss_read($url, 1) : $body;
        if (!$title) $stop[] = _CERROR;
        if (!$body && !$url) $stop[] = _CERROR1;
        if (!$body && $url) $stop[] = _RSSFAIL;
        if (!$stop && $posttype == 'save') {
            $data = ['title' => $title, 'body' => $body, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh];
            if ($cid) {
                $sql = 'UPDATE '.PREFIX_DB.'_content SET title = :title, body = :body, field = :field, url = :url, time = :time, refresh = :refresh WHERE id = :cid';
                $db->getSqlQuery($sql, $data + ['cid' => $cid]);
            } else {
                $sql = 'INSERT INTO '.PREFIX_DB.'_content (title, body, field, url, time, refresh, counter) VALUES (:title, :body, :field, :url, :time, :refresh, \'0\')';
                $db->getSqlQuery($sql, $data);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    if ($posttype == 'delete') {
        delete($cid);
        return;
    }
    setRedirect($afile.'.php?name=content', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $cid = 0): void {
    global $db, $afile;
    $id = $cid ?: getVar('req', 'id', 'num', 0);
    $refer = !$cid && (bool)getVar('get', 'refer', 'num', 0);
    $iswarn = !$cid && !checkSiteToken();
    if (!$iswarn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
    $redirect = $refer ? 'index.php?name=content' : $afile.'.php?name=content';
    setRedirect($redirect, false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ops = ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'];
    $cont = getTplAdminTabs(['ops' => $ops, 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/content.php');
    $rows = [
        ['label_html' => _C_33.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'num', 'value_attr' => $conf['content']['num']])],
        ['label_html' => _C_34.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anum', 'value_attr' => $conf['content']['anum']])],
        ['label_html' => _C_35.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nump', 'value_attr' => $conf['content']['nump']])],
        ['label_html' => _C_36.':', 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'anump', 'value_attr' => $conf['content']['anump']])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=content&amp;op=configsave',
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
            'num' => getVar('post', 'num', 'num', 25),
            'anum' => getVar('post', 'anum', 'num', 25),
            'nump' => getVar('post', 'nump', 'num', 10),
            'anump' => getVar('post', 'anump', 'num', 10),
        ];
        setConfigFile('content.php', $cont);
    }
    setRedirect($afile.'.php?name=content&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'],
        'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO],
    ]);
}

switch ($op) {
    default: content(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'info': info(); break;
}
