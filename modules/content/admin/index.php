<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('content')) die('Illegal file access');

function content(): void {
    global $db, $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
    $num = getVar('get', 'num', 'num', 1);
    $anum = $conf['content']['anum'] ?? 10;
    $anump = $conf['content']['anump'] ?? 10;
    $offset = ($num - 1) * $anum;
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content ORDER BY id DESC LIMIT '.$offset.', '.$anum);
    if ($db->getSqlRowCount($result) > 0) {
        $head = [
            ['content' => _ID],
            ['content' => _TITLE],
            ['content' => _DATE],
            ['content' => cutstr(_READS, 4, 1)],
            ['content' => _STATUS, 'nosort' => true],
            ['content' => _FUNCTIONS, 'nosort' => true],
        ];
        $rows = '';
        while ([$id, $title, $time, $counter] = $db->getSqlRow($result)) {
            $view = (time() >= strtotime($time)) ? 'index.php?name=content&amp;op=view&amp;id='.$id : '';
            $active = $view ? '1' : '0';
            $acts = $tpl->getHtmlFrag('edit-tip', [
                'delete_confirm' => _DELETE.' "'.$title.'"?',
                'delete_href' => $afile.'.php?name=content&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
                'delete_label' => _ONDELETE,
                'delete_title' => _ONDELETE,
                'editor_label' => _EDITOR,
                'edit_href' => $afile.'.php?name=content&amp;op=add&amp;id='.$id,
                'edit_label' => _FULLEDIT,
                'edit_title' => _FULLEDIT,
                'view_href' => $view,
                'view_label' => _MVIEW,
                'view_title' => _MVIEW,
            ]);
            $rows .= $tpl->getHtmlFrag('table-row', [
                'cells_html' => $tpl->getHtmlFrag('table-row-content', [
                    'actions_html' => $acts,
                    'date_text' => format_time($time, _TIMESTRING),
                    'id_text' => (string)$id,
                    'reads_text' => (string)$counter,
                    'status_html' => ad_status('', $active),
                    'title_html' => $tpl->getHtmlFrag('title-tip', [
                        'items' => [
                            ['label' => _URL, 'value' => $conf['homeurl'].'/index.php?name=content&amp;op=view&amp;id='.$id, 'is_last' => false],
                            ['label' => _ORTYPEURL, 'value' => $conf['homeurl'].'/index.php?go=rss&amp;name=content&amp;id='.$id, 'is_last' => true],
                        ],
                    ]).cutstr($title, 50),
                ]),
                'row_attr' => '',
                'row_class' => '',
            ]);
        }
        $cont .= $tpl->getHtmlFrag('table', [
            'head' => $head,
            'rows_html' => $rows,
            'table_class' => 'sl_table_list_sort',
        ]);
        $cont .= setArticleNumbers('pagenum', '', $anum, 'name=content&amp;', 'id', '_content', '', '', $anump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
    $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
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
                'input_class' => 'sl_form',
                'is_required' => true,
                'itype' => 'text',
                'maxlength_num' => 100,
                'name_attr' => 'title',
                'placeholder_text' => _TITLE,
                'value_attr' => $title,
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', [
                'label' => _RSSFILE,
                'hint' => _RSSINFO,
            ]),
            'field_html' => $tpl->getHtmlFrag('input', [
                'input_class' => 'sl_form',
                'itype' => 'text',
                'maxlength_num' => 200,
                'name_attr' => 'url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => $url,
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', [
                'label' => _REFRESHTIME,
                'hint' => _REFINFO,
            ]),
            'field_html' => getTplRefreshTimeSelect(['valu' => $refresh]),
        ],
        ['label_html' => _TEXT.':', 'field_html' => textarea('1', 'body', $body, 'content', '25', _TEXT, '0'), 'row_class' => 'sl-add-item-full'],
        ['label_html' => _CHNGSTORY.':', 'field_html' => getTplAddDateTime(['name' => 'time', 'time' => $time, 'with' => true, 'max' => 16])],
    ];
    $rows = array_merge($rows, getTplAddFieldRows(['field' => $field, 'mod' => 'content']));
    $acts = [
        'hasname' => (bool)$cid,
        'nameattr' => 'cid',
        'opattr' => 'save',
        'options' => [
            ['valueattr' => 'preview', 'labeltext' => _PREVIEW],
            ['valueattr' => 'save', 'labeltext' => _SEND],
        ],
        'submit_label' => _OK,
        'valueattr' => (string)$cid,
    ];
    if ($cid) $acts['options'][] = ['valueattr' => 'delete', 'labeltext' => _DELETE];
    $hide = [
        ['nameattr' => 'name', 'valueattr' => 'content'],
        ['nameattr' => 'token', 'valueattr' => getSiteToken()],
    ];
    $cont .= getTplBox($tpl->getHtmlFrag('add-div', [
        'action_url' => $afile.'.php?name=content&amp;op=add',
        'actions' => $acts,
        'hidden' => $hide,
        'rows' => $rows,
    ]));
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $url = getVar('post', 'url', 'text', '');
    $body = getVar('post', 'body', 'text', '');
    $body = ($url) ? rss_read($url, 1) : $body;
    $fieldp = getVar('post', 'field[]', 'raw', []);
    $field = is_array($fieldp) ? filterFields($fieldp) : '';
    $time = getVar('req', 'time', 'time');
    $refresh = getVar('post', 'refresh', 'num', 0);
    if (!$title) $stop[] = _CERROR;
    if (!$body && !$url) $stop[] = _CERROR1;
    if (!$body && $url) $stop[] = _RSSFAIL;
    $posttype = getVar('post', 'posttype', 'text', '');
    if (!$stop && $posttype == 'save') {
        if ($cid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET title = :title, body = :body, field = :field, url = :url, time = :time, refresh = :refresh WHERE id = :cid', ['title' => $title, 'body' => $body, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh, 'cid' => $cid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_content (title, body, field, url, time, refresh, counter) VALUES (:title, :body, :field, :url, :time, :refresh, \'0\')', ['title' => $title, 'body' => $body, 'field' => $field, 'url' => $url, 'time' => $time, 'refresh' => $refresh]);
        }
        setRedirect($afile.'.php?name=content');
    } elseif ($posttype == 'delete') {
        delete($cid);
    } else {
        add();
    }
}

function delete(int $cid = 0): void {
    global $db, $afile, $tpl;
    if (!$cid && !checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO]]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $id = $cid ? $cid : getVar('req', 'id', 'num', 0);
    if ($id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_content WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=content');
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/content.php');
    $rows = [
        ['label_html' => _C_33.':', 'field_html' => getTplNumberInput($conf['content']['num'], 'num', 'sl_conf')],
        ['label_html' => _C_34.':', 'field_html' => getTplNumberInput($conf['content']['anum'], 'anum', 'sl_conf')],
        ['label_html' => _C_35.':', 'field_html' => getTplNumberInput($conf['content']['nump'], 'nump', 'sl_conf')],
        ['label_html' => _C_36.':', 'field_html' => getTplNumberInput($conf['content']['anump'], 'anump', 'sl_conf')],
    ];
    $cont .= getTplBox($tpl->getHtmlFrag('config-div', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'content'],
            ['nameattr' => 'op', 'valueattr' => 'configsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 2]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $cont = [
        'num' => getVar('post', 'num', 'num', 25),
        'anum' => getVar('post', 'anum', 'num', 25),
        'nump' => getVar('post', 'nump', 'num', 10),
        'anump' => getVar('post', 'anump', 'num', 10),
    ];
    setConfigFile('content.php', $cont);
    setRedirect($afile.'.php?name=content&op=config');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=content', 'name=content&amp;op=add', 'name=content&amp;op=config', 'name=content&amp;op=info'], 'tabs' => [_HOME, _ADD, _PREFERENCES, _INFO], 'tab' => 3]);
    setAdminInfoPage($cont);
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
