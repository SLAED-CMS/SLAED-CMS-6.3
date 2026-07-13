<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function messages(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'], 'tabs' => [_HOME, _ADD, _DOCS]]);
    $result = $db->getSqlQuery('SELECT id, title, body, expire, status, view, lang FROM '.PREFIX_DB.'_message ORDER BY id');
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        while ([$mid, $title, $body, $expire, $active, $view, $lang] = $db->getSqlRow($result)) {
            if (($expire && $expire < time()) || (!$active && $expire)) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = :active, expire = :expire WHERE id = :mid', ['active' => 0, 'expire' => 0, 'mid' => $mid]);
            }
            if ($view == 1) {
                $mview = _MVALL;
            } elseif ($view == 2) {
                $mview = _MVANON;
            } elseif ($view == 3) {
                $mview = _MVUSERS;
            } else {
                $mview = _MVADMIN;
            }
            $lang = (!$lang) ? _ALL : $lang;
            $exp = intval($expire - time());
            $exp = ($exp > 0) ? getDuration($exp) : _UNLIMITED;
            $rows[] = $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
                'cells' => [
                    ['is_col_id' => true, 'content_html' => (string)$mid],
                    ['is_truncate' => true, 'title_text' => $title, 'content_html' => $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label' => $title, 'title_text' => $title])],
                    ['content_html' => $exp],
                    ['content_html' => $mview],
                    ['content_html' => getLangName($lang)],
                    ['is_col_status' => true, 'content_html' => ad_status('', $active)],
                    ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', [
                        'dial_title' => _FUNCTIONS,
                        'dial' => [[
                            'href' => $afile.'.php?name=messages&op=status&id='.$mid.'&act='.($active ? '0' : '1').'&token='.getSiteToken(),
                            'icon_name' => 'power',
                            'title' => $active ? _DEACTIVATE : _ACTIVATE,
                        ], [
                            'href' => $afile.'.php?name=messages&op=add&id='.$mid,
                            'icon_name' => 'pencil',
                            'title' => _FULLEDIT,
                        ], [
                            'href' => $afile.'.php?name=messages&op=delete&id='.$mid.'&token='.getSiteToken(),
                            'icon_name' => 'trash',
                            'title' => _ONDELETE,
                            'confirm_text' => _DELETE.' "'.$title.'"?',
                        ]],
                    ])],
                ],
            ])]);
        }
        $cont .= $tpl->getHtmlFrag('table', [
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _TITLE, 'is_truncate' => true],
                ['content' => _PURCHASED],
                ['content' => _VIEW],
                ['content' => _LANGUAGE],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => implode('', $rows),
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $stop, $tpl;
    $mid = getVar('req', 'id', 'num');
    if ($mid) {
        [$title, $body, $expire, $active, $view, $lang] = $db->getSqlRow($db->getSqlQuery('SELECT title, body, expire, status, view, lang FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $mid]));
    } else {
        $mid = getVar('post', 'mid', 'num');
        $title = getVar('post', 'title', 'title');
        $body = getVar('post', 'body', 'text');
        $newexpire = getVar('post', 'newexpire', 'num');
        $expire_input = getVar('post', 'expire', 'num');
        $expire = ($newexpire == 1 && $expire_input) ? time() + ($expire_input * 86400) : $expire_input;
        $active = getVar('post', 'status', 'num');
        $view = getVar('post', 'view', 'num');
        $lang = getVar('post', 'lang', 'var');
    }
    $stoptext = is_array($stop) ? implode(PHP_EOL, $stop) : (string)$stop;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'], 'tabs' => [_HOME, _ADD, _DOCS], 'tab' => 1]);
    if ($stoptext !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stoptext]);
    if ($body) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $body, 'mod' => 'all']);
    $langsel = '';
    if ($conf['multilingual'] == 1) {
        $langsel = $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions($lang, 1)]);
    }
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = $tpl->getHtmlFrag('hidden', ['nameattr' => 'expire', 'valueattr' => (string)$oldexpire])._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'expire', 'value_attr' => '0', 'placeholder_text' => _EXPIRATION, 'is_required' => true]);
    }
    $rows = [
        ['label_html' => _TITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'value_attr' => (string)$title, 'maxlength_num' => 100, 'placeholder_text' => _TITLE, 'is_required' => true])],
        ['label_html' => _TEXT, 'field_html' => getTplTextarea(['id' => '1', 'name' => 'body', 'value' => (string)$body, 'mod' => 'all', 'rows' => '10', 'placeholder' => _TEXT, 'required' => '1']), 'is_full' => true],
    ];
    if ($langsel) {
        $rows[] = ['label_html' => _LANGUAGE, 'field_html' => $langsel];
    }
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EXPIRATION, 'hint' => _CONFINES]), 'field_html' => $expire_text];
    $rows[] = ['label_html' => _VIEWPRIV, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'view',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVALL, 'is_selected' => (string)$view === '1']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVANON, 'is_selected' => (string)$view === '2']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVUSERS, 'is_selected' => (string)$view === '3']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _MVADMIN, 'is_selected' => (string)$view === '4']),
    ])];
    $rows[] = ['label_html' => _ACTIVATE2, 'field_html' => getTplRadioGroup([
        'name' => 'status',
        'value' => (string)(int)$active,
        'options' => [
            ['value' => '1', 'label' => _YES],
            ['value' => '0', 'label' => _NO],
        ],
    ])];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'mid', 'valueattr' => (string)$mid],
            ['nameattr' => 'name', 'valueattr' => 'messages'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'posttype', 'valueattr' => 'save'],
            ['nameattr' => 'newexpire', 'valueattr' => (string)$newexpire],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $stop = [];
    $mid = getVar('post', 'mid', 'num');
    $title = getVar('post', 'title', 'title');
    $body = getVar('post', 'body', 'text');
    $newexpire = getVar('post', 'newexpire', 'num');
    $expire = getVar('post', 'expire', 'num');
    $active = getVar('post', 'status', 'num');
    $view = getVar('post', 'view', 'num');
    $lang = getVar('post', 'lang', 'var');
    $posttype = getVar('post', 'posttype', 'var');
    $warn = !checkSiteToken();
    $expire = ($newexpire == 1 && $expire) ? time() + ($expire * 86400) : $expire;
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if (!$warn && !$stop && $posttype == 'save') {
        if ($mid) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET title = :title, body = :body, expire = :expire, status = :active, view = :view, lang = :lang WHERE id = :mid', ['title' => $title, 'body' => $body, 'expire' => $expire, 'active' => $active, 'view' => $view, 'lang' => $lang, 'mid' => $mid]);
        } else {
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_message (id, title, body, expire, status, view, lang) VALUES (NULL, :title, :body, :expire, :active, :view, :lang)', ['title' => $title, 'body' => $body, 'expire' => $expire, 'active' => $active, 'view' => $view, 'lang' => $lang]);
        }
        setRedirect($afile.'.php?name=messages', false, 302, _SUCCSAVE);
    } elseif ($warn) {
        setRedirect($afile.'.php?name=messages&op=add'.($mid ? '&id='.$mid : ''), false, 302, _TOKENMISS, true);
    } elseif ($posttype == 'delete') {
        delete($mid);
    } else {
        add();
    }
}

function status(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    $warn = !checkSiteToken();
    if (!$warn && $id) $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = :active WHERE id = :mid', ['active' => $act, 'mid' => $id]);
    setRedirect($afile.'.php?name=messages', false, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function delete(int $mid = 0): void {
    global $db, $afile;
    $id = $mid ?: getVar('get', 'id', 'num');
    $warn = !checkSiteToken();
    if (!$warn && $id) $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $id]);
    setRedirect($afile.'.php?name=messages', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'],
        'tabs' => [_HOME, _ADD, _DOCS],
    ]);
}

switch ($op) {
    default: messages(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
