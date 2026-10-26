<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The filter of the messages as the request carries it: the condition the list reads, a lamp turned on matching neither an expired row nor an off one, and the query that keeps it
function getMessagesFilter(string $src = 'get'): array {
    global $conf;
    $status = getVar($src, 'status', 'var');
    if (!in_array($status, ['on', 'off', 'gone'], true)) $status = '';
    $view = getVar($src, 'view', 'num', 0);
    if ($view > 4) $view = 0;
    $lang = ($conf['multilingual'] == 1) ? getVar($src, 'lang', 'var') : '';
    $chng = trim(getVar($src, 'chng'));
    $live = '(expire IS NULL OR expire >= NOW())';
    $cond = ($status !== '') ? [match ($status) {'on' => 'status = 1 AND '.$live, 'off' => 'status = 0 AND '.$live, default => 'expire < NOW()'}] : [];
    $pars = [];
    if ($view) {
        $cond[] = 'view = :view';
        $pars['view'] = $view;
    }
    if ($lang !== '') {
        $cond[] = '(lang = :lang OR lang = \'\')';
        $pars['lang'] = $lang;
    }
    if ($chng !== '') {
        $cond[] = 'title LIKE :find';
        $pars['find'] = '%'.$chng.'%';
    }
    $keys = array_filter(['status' => $status, 'view' => $view, 'lang' => $lang, 'chng' => $chng], static fn($v) => $v !== '' && $v !== 0);
    return ['status' => $status, 'view' => $view, 'lang' => $lang, 'chng' => $chng, 'where' => $cond ? ' WHERE '.implode(' AND ', $cond) : '', 'pars' => $pars,
        'base' => 'name=messages'.($keys ? '&'.http_build_query($keys, '', '&', PHP_QUERY_RFC3986) : '')];
}

# The filter bar of the messages: status, audience, the languages the rows name when the site has several, and the title
function getMessagesSearch(array $flt): string {
    global $afile, $db, $conf, $tpl;
    $opts = static function (array $list, string|int $cur): array {
        $out = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $cur === '' || $cur === 0]];
        foreach ($list as $key => $label) $out[] = ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => (string)$cur === (string)$key];
        return $out;
    };
    $fields = [
        ['field_id' => 'sl-msg-stat', 'label' => _STATUS, 'name_attr' => 'status', 'options' => $opts(['on' => _ON, 'off' => _OFF, 'gone' => _EXPIRED], $flt['status']),
            'is_now' => true],
        ['field_id' => 'sl-msg-view', 'label' => _VIEW, 'name_attr' => 'view', 'options' => $opts([1 => _MVALL, 2 => _MVANON, 3 => _MVUSERS, 4 => _MVADMIN], $flt['view']),
            'is_now' => true],
    ];
    if ($conf['multilingual'] == 1) {
        $langs = [];
        foreach ($db->getSqlRows($db->getSqlQuery('SELECT DISTINCT lang FROM '.PREFIX_DB.'_message WHERE lang != \'\' ORDER BY lang')) ?: [] as $row) {
            $langs[$row['lang']] = getLangName((string)$row['lang']);
        }
        $fields[] = ['field_id' => 'sl-msg-lang', 'label' => _LANGUAGE, 'name_attr' => 'lang', 'options' => $opts($langs, $flt['lang']), 'is_now' => true];
    }
    $fields[] = ['field_id' => 'sl-msg-q', 'label' => _TITLE, 'name_attr' => 'chng', 'itype' => 'search', 'value' => $flt['chng']];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'messages']],
        'fields' => $fields,
    ]);
}

function messages(): void {
    global $db, $afile, $tpl;
    $flt = getMessagesFilter();
    parse_str($flt['base'], $keys);
    $views = [1 => _MVALL, 2 => _MVANON, 3 => _MVUSERS];
    $result = $db->getSqlQuery('SELECT id, title, expire, status, view, lang FROM '.PREFIX_DB.'_message'.$flt['where'].' ORDER BY id', $flt['pars']);
    $rows = [];
    while ([$mid, $title, $expire, $active, $view, $lang] = $db->getSqlRow($result)) {
        $exp = $expire ? strtotime($expire) - time() : 0;
        $gone = $expire && $exp < 0;
        $keep = $keys + ['id[]' => $mid];
        $text = filterTextHighlight(htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $flt['chng']);
        $rows[] = ['name' => $title, 'drop' => getTplPostAction($keep + ['op' => 'delete'], 'trash', _ONDELETE), 'cells' => [
            ['is_col_id' => true, 'has_content_text' => true, 'content_text' => $mid],
            ['is_truncate' => true, 'title_text' => $title,
                'content_html' => $tpl->getHtmlFrag('inline-badge', ['is_note' => true, 'label_html' => $text, 'title_text' => $title])],
            ['has_content_text' => true, 'content_text' => $gone ? _EXPIRED : (($exp > 0) ? getDuration($exp) : _UNLIMITED)],
            ['has_content_text' => true, 'content_text' => $views[$view] ?? _MVADMIN],
            ['has_content_text' => true, 'content_text' => getLangName($lang ?: _ALL)],
            ['is_col_status' => true, 'stat' => getTplPostAction($keep + ['op' => 'status', 'act' => ($active && !$gone) ? '0' : '1'], 'power',
                ($active && !$gone) ? _DEACTIVATE : _ACTIVATE) + ['is_on' => $active && !$gone, 'label' => _STATUS.': '.$title, 'later' => 'lamp act']],
            ['is_col_actions' => true, 'dial' => [['href' => $afile.'.php?name=messages&op=add&id='.$mid, 'icon_name' => 'pencil', 'title' => _FULLEDIT, 'is_edit' => true]]],
        ]];
    }
    setHead();
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'], 'tabs' => [_HOME, _ADD, _MANUAL], 'part' => [0],
            'subtitle_html' => getMessagesSearch($flt)]),
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
        'rows' => $rows,
        'count' => count($rows),
        'none_text' => ($flt['where'] !== '') ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $stop, $tpl;
    $mid = getVar('req', 'id', 'num');
    if ($mid) {
        [
            $title,
            $body,
            $expire,
            $active,
            $view,
            $lang,
        ] = $db->getSqlRow($db->getSqlQuery('SELECT title, body, expire, status, view, lang FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $mid]));
        $days = 0;
    } else {
        $mid = getVar('post', 'mid', 'num');
        $title = getVar('post', 'title', 'title');
        $body = filterSourceText(getVar('post', 'body', 'raw', ''), isAdmin(true));
        $days = getVar('post', 'expire', 'num', 0);
        [$expire] = ($mid ? $db->getSqlRow($db->getSqlQuery('SELECT expire FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => $mid])) : false) ?: [null];
        $active = getVar('post', 'status', 'num');
        $view = getVar('post', 'view', 'num');
        $lang = getVar('post', 'lang', 'var');
    }
    $stoptext = is_array($stop) ? implode("\n", $stop) : (string)$stop;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'], 'tabs' => [_HOME, _ADD, _MANUAL], 'tab' => 1]);
    if ($stoptext !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stoptext]);
    if ($body) $cont .= getTplPreviewContent(['title' => $title, 'texta' => $body, 'mod' => 'all']);
    $langsel = '';
    if ($conf['multilingual'] == 1) {
        $langsel = $tpl->getHtmlFrag('select', ['name_attr' => 'lang', 'options_html' => getTplLanguageOptions($lang)]);
    }
    if ($expire) {
        $newexpire = 0;
        $expire = strtotime($expire) - time();
        $exp_day = $expire / 86400;
        $expire_text = _PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'expire',
            'input_id' => 'f-expire',
            'describedby' => 'f-expire-hint',
            'value_attr' => (string)$days,
            'placeholder_text' => _EXPIRATION,
            'is_required' => true,
        ]);
    }
    $rows = [
        [
            'label_for' => 'f-title',
            'label_html' => _TITLE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'name_attr' => 'title',
                'input_id' => 'f-title',
                'value_attr' => (string)$title,
                'maxlength_num' => 100,
                'placeholder_text' => _TITLE,
                'is_required' => true,
            ]),
        ],
        ['label_html' => _TEXT, 'label_id' => $labid = getFieldIds('', 'body')['label'], 'field_html' => getTplTextarea([
            'labelledby' => $labid,
            'label' => _TEXT,
            'id' => '1',
            'name' => 'body',
            'value' => (string)$body,
            'mod' => 'all',
            'store' => 'message.body',
            'rows' => '10',
            'placeholder' => _TEXT,
            'required' => '1',
        ]), 'is_full' => true],
    ];
    if ($langsel) {
        $rows[] = ['label_html' => _LANGUAGE, 'field_html' => $langsel];
    }
    $rows[] = [
        'label_for' => $newexpire ? 'f-expire' : '',
        'label_html' => _EXPIRATION,
        'hint_html' => _CONFINES,
        'hint_id' => $newexpire ? getFieldIds('f-expire')['hint'] : '',
        'field_html' => $expire_text,
    ];
    $rows[] = ['label_for' => 'f-view', 'label_html' => _VIEWPRIV, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'view',
        'selectid' => 'f-view',
        'options_html' =>
            $tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVALL, 'is_selected' => (string)$view === '1']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVANON, 'is_selected' => (string)$view === '2']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVUSERS, 'is_selected' => (string)$view === '3']).
            $tpl->getHtmlFrag('select-option', ['value_attr' => '4', 'label_text' => _MVADMIN, 'is_selected' => (string)$view === '4']),
    ])];
    $rows[] = ['label_html' => _ACTIVATE2, 'label_id' => $labid = getFieldIds('', 'status')['label'], 'field_html' => getTplRadioGroup(['labelledby' => $labid,
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('messages')],
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
    $title = filterCutText(getVar('post', 'title', 'title'), 100);
    $body = filterSourceText(getVar('post', 'body', 'raw', ''), isAdmin(true));
    $days = getVar('post', 'expire', 'num', 0);
    $active = getVar('post', 'status', 'num');
    $view = getVar('post', 'view', 'num');
    $lang = getVar('post', 'lang', 'var');
    if (!isset(getLanguageList()[$lang])) $lang = '';
    $posttype = getVar('post', 'posttype', 'var');
    $warn = !checkAdminPost('messages');
    if (!$title) $stop[] = _CERROR;
    if (!$body) $stop[] = _CERROR1;
    if ($room = checkEditorTextRoom($body, 'message.body')) $stop[] = $room;
    if (!$warn && !$stop && $posttype == 'save') {
        if ($mid) {
            $exp = ($days > 0) ? ' expire = COALESCE(expire, NOW() + INTERVAL :days DAY),' : '';
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET title = :title, body = :body,'.$exp.' status = :active, view = :view, lang = :lang WHERE id = :mid', [
                'title' => $title,
                'body' => $body,
                'active' => $active,
                'view' => $view,
                'lang' => $lang,
                'mid' => $mid,
            ] + (($days > 0) ? ['days' => $days] : []));
        } else {
            $exp = ($days > 0) ? 'NOW() + INTERVAL :days DAY' : 'NULL';
            $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_message (id, title, body, expire, status, view, lang) VALUES (NULL, :title, :body, '.$exp.', :active, :view, :lang)', [
                'title' => $title,
                'body' => $body,
                'active' => $active,
                'view' => $view,
                'lang' => $lang,
            ] + (($days > 0) ? ['days' => $days] : []));
        }
        setRedirect($afile.'.php?name=messages', false, 302, _SUCCSAVE);
    } elseif ($warn) {
        setRedirect($afile.'.php?name=messages&op=add'.($mid ? '&id='.$mid : ''), false, 302, _TOKENMISS, true);
    } elseif ($posttype == 'delete') {
        delete([$mid]);
    } else {
        add();
    }
}

function status(): void {
    global $db, $afile;
    $ids = getVar('post', 'id[]', 'num');
    $act = getVar('post', 'act', 'num') ? 1 : 0;
    $warn = !checkAdminPost('messages');
    $wipe = $act ? ', expire = CASE WHEN expire < NOW() THEN NULL ELSE expire END' : '';
    $done = !$warn && $ids;
    foreach ($done ? $ids : [] as $id) {
        if (!$db->getSqlQuery('UPDATE '.PREFIX_DB.'_message SET status = :active'.$wipe.' WHERE id = :mid', ['active' => $act, 'mid' => intval($id)])) $done = false;
    }
    setRedirect($afile.'.php?'.getMessagesFilter('post')['base'], false, 302, $warn ? _TOKENMISS : ($done ? _SUCCSTATUS : _ERROR), !$done);
}

function delete(array $ids = []): void {
    global $db, $afile;
    $ids = $ids ?: getVar('post', 'id[]', 'num');
    $warn = !checkAdminPost('messages');
    $done = !$warn && $ids;
    foreach ($done ? $ids : [] as $id) {
        $res = $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_message WHERE id = :mid', ['mid' => intval($id)]);
        if (!$res || $res->rowCount() < 1) $done = false;
    }
    setRedirect($afile.'.php?'.getMessagesFilter('post')['base'], false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=messages', 'name=messages&op=add', 'name=messages&op=info'],
        'tabs' => [_HOME, _ADD, _MANUAL],
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
