<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('clients')) die('Illegal file access');

function clients(): void {
    global $db, $afile, $stop, $tpl;
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=clients', 'name=clients&op=add', 'name=clients&op=info'],
        'tabs' => [_HOME, _ADD, _DOCS],
    ]);
    if ($stop) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CERROR]);
    }
    $result = $db->getSqlQuery('SELECT id, title, body, url, num, hits, pid, status FROM '.PREFIX_DB.'_clients_down');
    if ($db->getSqlRowCount($result) > 0) {
        $rows = '';
        while ([$id, $title, $body, $url, $num, $hits, $prod, $status] = $db->getSqlRow($result)) {
            $act = $status ? 0 : 1;
            $time = file_exists(UPLOADS_DIR.'/clients/'.$url) ? date(_TIMESTRING, filemtime(UPLOADS_DIR.'/clients/'.$url)) : _NO_INFO;
            $rows .= $tpl->getHtmlFrag('table-row', [
                'cells_html' => $tpl->getHtmlFrag('table-cells', [
                    'cells' => [
                        ['is_col_id' => true, 'content_html' => (string)$id],
                        ['is_truncate' => true, 'title_text' => $title, 'has_content_text' => true, 'content_text' => (string)$title],
                        ['is_col_count' => true, 'content_html' => $num],
                        ['is_col_date' => true, 'content_html' => $time],
                        ['is_col_id' => true, 'content_html' => (string)$prod],
                        ['is_col_count' => true, 'content_html' => (string)$hits],
                        ['is_col_status' => true, 'content_html' => $tpl->getHtmlFrag('inline-badge', [
                            'is_green' => (bool)$status,
                            'is_red' => !$status,
                            'label' => $status ? _YES : _NO,
                            'title' => _STATUS,
                        ])],
                        ['is_col_actions' => true, 'content_html' => $tpl->getHtmlFrag('dial', [
                            'dial_title' => _FUNCTIONS,
                            'dial' => [
                                [
                                    'href' => $afile.'.php?name=clients&op=status&id='.$id.'&act='.$act.'&token='.getSiteToken(),
                                    'icon_name' => 'power',
                                    'title' => $status ? _DEACTIVATE : _ACTIVATE,
                                ],
                                [
                                    'href' => $afile.'.php?name=clients&op=add&id='.$id,
                                    'icon_name' => 'pencil',
                                    'title' => _FULLEDIT,
                                ],
                                [
                                    'href' => $afile.'.php?name=clients&op=delete&id='.$id.'&token='.getSiteToken(),
                                    'icon_name' => 'trash',
                                    'title' => _DELETE,
                                    'confirm_text' => _DELETE.' "'.$title.'"?',
                                ],
                            ],
                        ])],
                    ],
                ]),
            ]);
        }
        $body = $tpl->getHtmlFrag('table', [
            'is_wrapless' => true,
            'is_fixed' => true,
            'head' => [
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _CTITLE, 'is_truncate' => true],
                ['content' => _CVERSION, 'is_col_count' => true],
                ['content' => _CDATE, 'is_col_date' => true],
                ['content' => _ID, 'is_col_id' => true],
                ['content' => _CLOADS, 'is_col_count' => true],
                ['content' => _STATUS, 'is_col_status' => true, 'nosort' => true],
                ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
            ],
            'rows_html' => $rows,
        ]);
        $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    } else {
        $cont .= $tpl->getHtmlPart('box', [
            'content_html' => $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]),
        ]);
    }
    echo $cont;
    setFoot();
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    if ($id) {
        $result = $db->getSqlQuery('SELECT id, title, body, url, num, code, pid, status FROM '.PREFIX_DB.'_clients_down WHERE id = :id', ['id' => $id]);
        [$cid, $title, $body, $url, $num, $code, $prod, $status] = $db->getSqlRow($result);
    } else {
        $cid = getVar('post', 'cid', 'num');
        $title = getVar('post', 'title', 'title', '');
        $body = getVar('post', 'body', 'text', '');
        $url = getVar('post', 'url', 'text', '');
        $num = getVar('post', 'num', 'text', '');
        $code = getVar('post', 'code', 'text', '');
        $prod = getVar('post', 'prod', 'num', 0);
        $status = getVar('post', 'status', 'num', 0);
    }
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=clients', 'name=clients&op=add', 'name=clients&op=info'],
        'tabs' => [_HOME, _ADD, _DOCS],
        'tab' => 1,
    ]);
    if ($stop) {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => (array)$stop]);
    }
    if ($body) {
        $cont .= getTplPreviewContent(['title' => $title, 'texta' => $body, 'mod' => 'all']);
    }
    $rows = [
        ['label_for' => 'f-title', 'label_html' => _CTITLE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'title', 'input_id' => 'f-title', 'value_attr' => $title, 'maxlength_num' => 255])],
        ['label_for' => 'f-num', 'label_html' => _CVERSION, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'num', 'input_id' => 'f-num', 'value_attr' => $num, 'maxlength_num' => 255])],
        ['label_for' => 'f-url', 'label_html' => _CURL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'url', 'input_id' => 'f-url', 'value_attr' => $url, 'maxlength_num' => 255])],
        ['label_for' => 'f-code', 'label_html' => _CODE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'code', 'input_id' => 'f-code', 'value_attr' => $code, 'maxlength_num' => 255])],
        ['label_for' => 'f-prod', 'label_html' => _ID, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'prod', 'input_id' => 'f-prod', 'value_attr' => (string)$prod])],
        ['label_html' => _CADOWN, 'label_id' => $labid = getFieldIds('', 'status')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'status', 'value' => (string)$status, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_for' => 'f-body', 'label_html' => _TEXT, 'field_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'body', 'input_id' => 'f-body', 'value_text' => $body, 'rows_num' => 15])],
    ];
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=clients&op=save',
        'hidden' => [
            ['nameattr' => 'cid', 'valueattr' => (string)$cid],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    echo $cont;
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $cid = getVar('post', 'cid', 'num', 0);
    $title = getVar('post', 'title', 'title', '');
    $body = getVar('post', 'body', 'text', '');
    $url = getVar('post', 'url', 'text', '');
    $num = getVar('post', 'num', 'text', '');
    $code = getVar('post', 'code', 'text', '');
    $prod = getVar('post', 'prod', 'num', 0);
    $status = getVar('post', 'status', 'num', 0);
    $iswarn = !checkSiteToken();
    $stop = [];
    if (!$iswarn) {
        if (!$title) $stop[] = _CERROR;
        if (!$body) $stop[] = _CERROR1;
        if (!$stop) {
            if ($cid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET title = :title, body = :body, url = :url, num = :num, code = :code, pid = :pid, status = :status WHERE id = :id', ['title' => $title, 'body' => $body, 'url' => $url, 'num' => $num, 'code' => $code, 'pid' => $prod, 'status' => $status, 'id' => $cid]);
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_clients_down (title, body, url, num, code, hits, pid, status) VALUES (:title, :body, :url, :num, :code, :hits, :pid, :status)', ['title' => $title, 'body' => $body, 'url' => $url, 'num' => $num, 'code' => $code, 'hits' => 0, 'pid' => $prod, 'status' => $status]);
            }
        }
    }
    if ($stop) {
        add();
        return;
    }
    setRedirect($afile.'.php?name=clients', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function delete(int $id = 0): void {
    global $db, $afile;
    if (!$id) $id = getVar('req', 'id', 'num');
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_clients_down WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=clients', false, 302, $iswarn ? _TOKENMISS : _SUCCDELETE, $iswarn);
}

function status(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num');
    $iswarn = !checkSiteToken();
    if (!$iswarn && $id) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_clients_down SET status = :status WHERE id = :id', ['status' => $act, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=clients', false, 302, $iswarn ? _TOKENMISS : _SUCCSTATUS, $iswarn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=clients', 'name=clients&op=add', 'name=clients&op=info'],
        'tabs' => [_HOME, _ADD, _DOCS],
    ]);
}

switch ($op) {
    default: clients(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'status': status(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
