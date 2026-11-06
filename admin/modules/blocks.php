<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getBlockTabsOps(): array {
    return [
        'name=blocks',
        'name=blocks&op=add',
        'name=blocks&op=fileadd',
        'name=blocks&op=fileedit',
        'name=blocks&op=info',
    ];
}

# The where-to-show boxes of a block: the places with their kind and name in a popover, a value no page answers kept checked until the administrator drops it, then the marks
function getBlockWhereItems(array $where): array {
    global $tpl;
    $places = getBlockPlaces();
    foreach (getBlockStray($where, $places) as $val) $places[$val] = [_BLOCK_NOWHERE, $val];
    $out = [];
    foreach ($places as $key => [$kind, $label]) {
        $out[] = ['is_checked' => in_array($key, $where, true), 'name_attr' => 'blockwhere[]', 'value_attr' => $key,
            'label_html' => $tpl->getHtmlFrag('popover', ['label_text' => $label, 'title_text' => $kind.': '.$key])];
    }
    $home = in_array('home', $where, true);
    $list = ['ihome' => _HOME, 'home' => _INHOME, 'all' => _BLOCK_ALL, 'otricanie' => _DENYING, 'infly' => _INFLY, 'flyfix' => _FLY_FIX];
    foreach ($list as $key => $label) {
        $out[] = ['is_checked' => in_array($key, $where, true) && !($key === 'all' && $home), 'name_attr' => 'blockwhere[]', 'value_attr' => $key, 'label_text' => $label];
    }
    return $out;
}

function blocks(): void {
    global $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL]]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _DRAGSORT, 'acts_html' => getTplPostButton(['name' => 'blocks', 'op' => 'fix'], 'arrow-repeat', _FIX, '', 'act')]);
    echo $cont.$tpl->getHtmlPart('box', [
        'box_id' => 'repajax_block',
        'content_html' => getAdminBlockList(),
    ]);
    setFoot();
}

function add(array $sent = [], array $stop = []): void {
    global $db, $conf, $afile, $tpl;
    $spec = getBlockSpec();
    $sent += ['title' => '', 'url' => '', 'refresh' => '3600', 'content' => '', 'bpos' => 'l', 'where' => [], 'lang' => '', 'status' => '1', 'expire' => '0',
        'action' => 'd', 'view' => '0'];
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => $stop]);
    $rows = [
        [
            'label_for' => 'f-title',
            'label_html' => _TITLE, 'hint_html' => _ADDCONST, 'hint_id' => $hntid = getFieldIds('f-title')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
                'itype' => 'text',
                'is_required' => true,
                'maxlength_num' => $spec['title'],
                'name_attr' => 'title',
                'input_id' => 'f-title',
                'placeholder_text' => _TITLE,
                'value_attr' => (string)$sent['title'],
            ]),
        ],
        [
            'label_for' => 'f-url',
            'label_html' => _RSSFILE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'maxlength_num' => $spec['url'],
                'name_attr' => 'url',
                'input_id' => 'f-url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => (string)$sent['url'],
            ]),
        ],
        [
            'label_for' => 'f-headline',
            'label_html' => _RSSC, 'hint_html' => _RSSLINESINFO.' '._RSSINFO, 'hint_id' => $hntid = getFieldIds('f-headline')['hint'],
            'field_html' => $tpl->getHtmlFrag('select', ['describedby' => $hntid,
                'name_attr' => 'headline',
                'selectid' => 'f-headline',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '',
                    'label_text' => _CUSTOM,
                    'is_selected' => true,
                ]).rss_select(),
            ]),
        ],
        [
            'label_for' => 'f-refresh',
            'label_html' => _REFRESHTIME, 'hint_html' => _REFINFO, 'hint_id' => $hntid = getFieldIds('f-refresh')['hint'],
            'field_html' => $tpl->getHtmlFrag('select', ['describedby' => $hntid,
                'name_attr' => 'refresh',
                'selectid' => 'f-refresh',
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1800', 'label_text' => '30 '._MIN.'.', 'is_selected' => (string)$sent['refresh'] === '1800'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '3600', 'label_text' => '1 '._HOUR, 'is_selected' => (string)$sent['refresh'] === '3600'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '18000', 'label_text' => '5 '._HOUR.'.', 'is_selected' => (string)$sent['refresh'] === '18000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '36000', 'label_text' => '10 '._HOUR.'.', 'is_selected' => (string)$sent['refresh'] === '36000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '86400', 'label_text' => '24 '._HOUR.'.', 'is_selected' => (string)$sent['refresh'] === '86400']),
            ]),
        ],
    ];
    $bfopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NONE, 'is_selected' => true]);
    foreach ($spec['files'] as $file) {
        if ($file !== 'node.php' && $db->getSqlRowCount($db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) > 0) continue;
        $bfopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $file, 'label_text' => $file]);
    }
    $rows[] = [
        'label_for' => 'f-bfile',
        'label_html' => _FILENAME, 'hint_html' => _FILENAMEIN, 'hint_id' => $hntid = getFieldIds('f-bfile')['hint'],
        'field_html' => $tpl->getHtmlFrag('select', ['describedby' => $hntid,
            'name_attr' => 'bfile',
            'selectid' => 'f-bfile',
            'options_html' => $bfopts,
        ]),
    ];
    $rows[] = [
        'label_html' => _CONTENT,
        'label_id' => $labid = getFieldIds('', 'content')['label'],
        'field_html' => getTplTextarea([
            'labelledby' => $labid,
            'label' => _CONTENT,
            'id' => '1',
            'name' => 'content',
            'value' => (string)$sent['content'],
            'mod' => 'all',
            'store' => 'blocks.content',
            'rows' => '15',
            'placeholder' => _CONTENT,
        ]),
        'is_full' => true,
    ];
    $rows[] = [
        'label_for' => 'f-bpos',
        'label_html' => _POSITION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bpos',
            'selectid' => 'f-bpos',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'l', 'label_text' => _LEFT, 'is_selected' => $sent['bpos'] === 'l'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c', 'label_text' => _CENTERUP, 'is_selected' => $sent['bpos'] === 'c'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _CENTERDOWN, 'is_selected' => $sent['bpos'] === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _RIGHT, 'is_selected' => $sent['bpos'] === 'r']),
        ]),
    ];
    $rows[] = [
        'label_html' => _BLOCK_VIEW,
        'label_id' => $labid = getFieldIds('', 'blockwhere')['label'],
        'field_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $labid, 'items' => getBlockWhereItems($sent['where'])]),
        'is_full' => true,
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_for' => 'f-lang',
            'label_html' => _LANGUAGE,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'lang',
                'selectid' => 'f-lang',
                'options_html' => getTplLanguageOptions((string)$sent['lang']),
            ]),
        ];
    }
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'label_id' => $labid = getFieldIds('', 'status')['label'],
        'field_html' => getTplRadioGroup([
            'labelledby' => $labid,
            'name' => 'status',
            'value' => (string)$sent['status'],
            'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-expire',
        'label_html' => _EXPIRATION, 'hint_html' => _CONFINES, 'hint_id' => $hntid = getFieldIds('f-expire')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
            'itype' => 'number',
            'is_required' => true,
            'input_attr' => 'min="0" max="'.$spec['days'].'"',
            'name_attr' => 'expire',
            'input_id' => 'f-expire',
            'placeholder_text' => _EXPIRATION,
            'value_attr' => (string)$sent['expire'],
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-action',
        'label_html' => _AFTEREXPIRATION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'action',
            'selectid' => 'f-action',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DEACTIVATE, 'is_selected' => $sent['action'] === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _DELETE, 'is_selected' => $sent['action'] === 'r']),
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-view',
        'label_html' => _VIEWPRIV,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'selectid' => 'f-view',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => (string)$sent['view'] === '0'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS, 'is_selected' => (string)$sent['view'] === '1'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN, 'is_selected' => (string)$sent['view'] === '2'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVANON, 'is_selected' => (string)$sent['view'] === '3']),
        ]),
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=blocks&op=addsave',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'addsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
        ],
        'rows' => $rows,
        'submit_label' => _CREATEBLOCK,
        'icon_name' => 'plus-lg',
    ])]);
    setFoot();
}

function fileadd(): void {
    global $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 2]);
    $cont .= checkPerms(BASE_DIR.'/blocks');
    $rows = [
        [
            'label_for' => 'f-bf',
            'label_html' => _FILENAME,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'is_required' => true,
                'maxlength_num' => 200,
                'name_attr' => 'bf',
                'input_id' => 'f-bf',
                'placeholder_text' => _FILENAME,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => _TYPE,
            'label_id' => $labid = getFieldIds('', 'flag')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'flag',
                'value' => 'php',
                'options' => [['value' => 'php', 'label' => 'PHP'], ['value' => 'html', 'label' => 'HTML']],
            ]),
        ],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=blocks&op=filecode',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'filecode'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
        ],
        'rows' => $rows,
        'submit_label' => _CREATEBLOCK,
        'icon_name' => 'plus-lg',
    ])]);
    setFoot();
}

function fileedit(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
    $opts = '';
    $files = scandir(BASE_DIR.'/blocks');
    foreach ($files as $file) {
        if (preg_match('/^(.+)\.php$/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                ]);
            }
        }
    }
    $rows = [[
        'label_for' => 'f-bf',
        'label_html' => _FILENAME,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bf',
            'selectid' => 'f-bf',
            'options_html' => $opts,
        ]),
    ]];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=blocks&op=filecode',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'filecode'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
        ],
        'rows' => $rows,
        'submit_label' => _EDITBLOCK,
    ])]);
    setFoot();
}

function fix(): void {
    global $afile;
    $warn = !checkAdminPost('blocks');
    $done = !$warn;
    foreach (['l', 'r', 'c', 'd'] as $val) $done = $done && setBlockOrder($val);
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCSAVE : _ERROR), !$done);
}

function addsave(): void {
    global $afile;
    if (!checkAdminPost('blocks')) setRedirect($afile.'.php?name=blocks&op=add', false, 302, _TOKENMISS, true);
    $set = [
        'title' => getVar('post', 'title', 'title', ''),
        'content' => getVar('post', 'content', 'raw', ''),
        'url' => getVar('post', 'headline', 'url', '') ?: getVar('post', 'url', 'url', ''),
        'bfile' => getVar('post', 'bfile', 'text', ''),
        'bpos' => getVar('post', 'bpos', 'var', ''),
        'status' => getVar('post', 'status', 'num', 0),
        'refresh' => getVar('post', 'refresh', 'num', 3600),
        'lang' => getVar('post', 'lang', 'var', ''),
        'view' => getVar('post', 'view', 'num', 0),
        'days' => getVar('post', 'expire', 'num', 0),
        'action' => getVar('post', 'action', 'var', 'd'),
        'which' => getVar('post', 'blockwhere[]', 'var', []),
    ];
    $res = setBlockRow(0, $set);
    if (is_int($res)) setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    if ($res === _ERROR) setRedirect($afile.'.php?name=blocks&op=add', false, 302, _ERROR, true);
    $sent = array_map('strval', array_diff_key($set, ['which' => true, 'days' => true])) + ['where' => $set['which'], 'expire' => (string)$set['days']];
    add($sent, [$res]);
}

function getBlockCodeForm(string $bf, string $flag, string $text, string $ver, string $note = ''): string {
    global $afile, $tpl;
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
    $dir = BASE_DIR.'/blocks';
    $path = BASE_DIR.'/blocks/'.$bf;
    $cont .= checkPerms($dir).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BLOCK.': '.$path]);
    if (file_exists($path)) $cont .= checkPerms($path).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _B_FEDIT]);
    if ($note !== '') $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $note]);
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _EINFOPHP]);
    $rows = [[
        'label_html' => _FILENAME,
        'has_field_text' => true,
        'field_text' => $bf,
    ], [
        'label_html' => _CONTENT,
        'field_html' => Editor::getCode([
            'id' => 'code',
            'name' => 'blocktext',
            'label' => _CONTENT,
            'lang' => 'php',
            'text' => $text,
        ]),
        'is_full' => true,
    ]];
    return $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=blocks&op=filecodesave',
        'hidden' => [
            ['nameattr' => 'bf', 'valueattr' => $bf],
            ['nameattr' => 'flag', 'valueattr' => $flag],
            ['nameattr' => 'ver', 'valueattr' => $ver],
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'filecodesave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
}

function filecode(): void {
    global $afile;
    if (!checkAdminPost('blocks')) setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, _TOKENMISS, true);
    $bf = getVar('post', 'bf', 'text', '');
    if ($bf != '') {
        $flag = getVar('post', 'flag', 'var', '');
        if ($flag) {
            $flaged = $flag;
            $bf = preg_replace('/[^a-z0-9_\-]/i', '', str_replace('.php', '', $bf));
            $bf = $bf.'.php';
        } else {
            $bf = preg_match('/^[a-z0-9_\-]+\.php$/i', $bf) ? $bf : '';
            if ($bf === '') {
                setRedirect($afile.'.php?name=blocks&op=fileedit');
                return;
            }
            if (!is_file(BASE_DIR.'/blocks/'.$bf)) setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, _NONE_FOUND, true);
            $bfstr = file_get_contents(BASE_DIR.'/blocks/'.$bf);
            if (strpos($bfstr, 'BLOCKHTML') === false) {
                $flaged = 'php';
                $code = preg_replace('/\A<\?php.*?if\s*\(\s*!defined\(\s*[\'"]BLOCK_FILE[\'"]\s*\)\s*\)\s*\{.*?\}\s*/is', '', $bfstr);
                $code = preg_replace('/\?>\s*\z/is', '', (string)$code);
                $out = [1 => $code];
            } else {
                $flaged = 'html';
                preg_match('/<<<BLOCKHTML(.*)BLOCKHTML;/is', $bfstr, $out);
                unset($out[0]);
            }
        }
        $path = BASE_DIR.'/blocks/'.$bf;
        setHead();
        echo getBlockCodeForm($bf, $flaged, trim($out[1] ?? ''), is_file($path) ? (string)sha1_file($path) : '');
        setFoot();
    } else {
        setRedirect($afile.'.php?name=blocks&op=fileedit');
    }
}

function filecodesave(): void {
    global $afile;
    $warn = !checkAdminPost('blocks');
    $btext = getVar('post', 'blocktext', 'raw', '');
    $bf = getVar('post', 'bf', 'text', '');
    $bf = preg_match('/^[a-z0-9_\-]+\.php$/i', $bf) ? $bf : '';
    if ($warn || !$bf) setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, $warn ? _TOKENMISS : '', $warn);
    $flag = getVar('post', 'flag', 'var', '');
    $ver = getVar('post', 'ver', 'word', '');
    if (trim($btext) === '') {
        setHead();
        echo getBlockCodeForm($bf, $flag, '', $ver, _BLOCKS_NOCODE);
        setFoot();
        return;
    }
    $htmlb = ($flag == 'html') ? "\$content = <<<BLOCKHTML\n" : '';
    $htmle = ($flag == 'html') ? "\nBLOCKHTML;\n" : '';
    $body = '<?php'."\n".'# Author: Eduard Laas'."\n".'# 2005 - '.date('Y').' SLAED'."\n".'# License: MIT'."\n".'# Website: slaed.net'."\n\n"
        .'if (!defined(\'BLOCK_FILE\')) {'."\n".'header(\'Location: ../index.php\');'."\n".'exit;'."\n".'}'."\n\n".$htmlb.$btext.$htmle."\n".'?>';
    $man = getAdminFileManager('system');
    $res = ($ver === '') ? $man->addFileEntry('blocks/'.$bf) : ['ok' => true, 'error' => ''];
    if ($res['ok']) $res = $man->setFileBody('blocks/'.$bf, $body, ($ver === '') ? sha1('') : $ver);
    if ($res['ok']) setRedirect($afile.'.php?name=blocks', false, 302, _SUCCFILESAVE);
    if (!in_array($res['error'], ['conflict', 'exists'], true)) setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, _ERROR_UP, true);
    http_response_code(409);
    setHead();
    echo getBlockCodeForm($bf, $flag, $btext, (string)sha1_file(BASE_DIR.'/blocks/'.$bf), _UPLOADS_STALE);
    setFoot();
}

function edit(int $bid = 0, array $sent = [], array $stop = []): void {
    global $afile, $conf, $db, $tpl;
    $spec = getBlockSpec();
    $bid = $bid ?: getVar('get', 'id', 'num');
    $sql = 'SELECT bkey, title, content, url, bpos, weight, status, refresh, lang, bfile, view, expire, action, which, param FROM '.PREFIX_DB.'_blocks WHERE id = :bid';
    $row = $db->getSqlRow($db->getSqlQuery($sql, ['bid' => $bid])) ?: setRedirect($afile.'.php?name=blocks', false, 302, _NONE_FOUND, true);
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => $stop]);
    [
        'title' => $title, 'content' => $content, 'url' => $url, 'bpos' => $bpos, 'weight' => $weight, 'status' => $active, 'refresh' => $refresh,
        'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'expire' => $expire, 'action' => $action, 'which' => $which, 'param' => $param,
    ] = array_replace($row, $sent);
    if ($url != '') {
        $type = '('._BLOCKRSS.')';
    } elseif ($bfile != '') {
        $type = '('._BLOCKFILE.')';
    } else {
        $type = '('._BLOCKHTML.')';
    }
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BLOCK.': '.$title.' '.$type]);
    $rows = [[
        'label_for' => 'f-title',
        'label_html' => _TITLE, 'hint_html' => _ADDCONST, 'hint_id' => $hntid = getFieldIds('f-title')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
            'itype' => 'text',
            'is_required' => true,
            'maxlength_num' => $spec['title'],
            'name_attr' => 'title',
            'input_id' => 'f-title',
            'placeholder_text' => _TITLE,
            'value_attr' => (string)$title,
        ]),
    ]];
    if ($bfile != '') {
        $bfopts = '';
        $files = $spec['files'];
        if (!in_array($bfile, $files, true)) {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _BLOCK_NOFILE.': blocks/'.htmlspecialchars($bfile, ENT_QUOTES)]);
            $bfopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $bfile, 'label_text' => $bfile.' — '._BLOCK_NOFILE, 'is_selected' => true]);
        }
        foreach ($files as $file) $bfopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $file, 'label_text' => $file, 'is_selected' => $bfile === $file]);
        $rows[] = [
            'label_for' => 'f-bfile',
            'label_html' => _FILENAME,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'bfile',
                'selectid' => 'f-bfile',
                'options_html' => $bfopts,
            ]),
        ];
        if ($bfile === 'node.php') {
            $set = getNodeBlockParam((string)$param);
            if ($set === null) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _BLOCKPROBLEM]);
            $set ??= ['type' => [], 'mode' => 'last', 'limit' => 10];
            $tbox = [];
            foreach (getNodeTypeMap() as $name => $type) {
                if (!$type->settings['integrations']['blocks']) continue;
                $tbox[] = ['is_checked' => in_array($name, $set['type'], true), 'name_attr' => 'ntype[]', 'value_attr' => $name, 'label_text' => getModuleName($name)];
            }
            $tids = getFieldIds('', 'ntype');
            $rows[] = ['label_html' => _TYPE, 'label_id' => $tids['label'], 'hint_html' => _BLOCK_ALLTYPES, 'hint_id' => $tids['hint'],
                'field_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $tids['label'], 'describedby' => $tids['hint'], 'items' => $tbox])];
            $rows[] = ['label_for' => 'f-nmode', 'label_html' => _SHOW, 'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'nmode', 'selectid' => 'f-nmode',
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'last', 'label_text' => _NEW, 'is_selected' => $set['mode'] === 'last'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => 'home', 'label_text' => _HOME, 'is_selected' => $set['mode'] === 'home'])])];
            $rows[] = ['label_for' => 'f-nlimit', 'label_html' => _C_13, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'nlimit',
                'input_id' => 'f-nlimit', 'value_attr' => (string)$set['limit'], 'is_required' => true])];
        }
    } elseif ($url != '') {
        $rows[] = [
            'label_for' => 'f-url',
            'label_html' => _RSSFILE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'maxlength_num' => $spec['url'],
                'name_attr' => 'url',
                'input_id' => 'f-url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => (string)$url,
            ]),
        ];
        $rows[] = [
            'label_for' => 'f-refresh',
            'label_html' => _REFRESHTIME, 'hint_html' => _REFINFO, 'hint_id' => $hntid = getFieldIds('f-refresh')['hint'],
            'field_html' => $tpl->getHtmlFrag('select', ['describedby' => $hntid,
                'name_attr' => 'refresh',
                'selectid' => 'f-refresh',
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1800', 'label_text' => '30 '._MIN.'.', 'is_selected' => (string)$refresh === '1800'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '3600', 'label_text' => '1 '._HOUR, 'is_selected' => (string)$refresh === '3600'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '18000', 'label_text' => '5 '._HOUR.'.', 'is_selected' => (string)$refresh === '18000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '36000', 'label_text' => '10 '._HOUR.'.', 'is_selected' => (string)$refresh === '36000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '86400', 'label_text' => '24 '._HOUR.'.', 'is_selected' => (string)$refresh === '86400']),
            ]),
        ];
    } else {
        $rows[] = [
            'label_html' => _CONTENT,
            'label_id' => $labid = getFieldIds('', 'content')['label'],
            'field_html' => getTplTextarea([
                'labelledby' => $labid,
                'label' => _CONTENT,
                'id' => '1',
                'name' => 'content',
                'value' => (string)$content,
                'mod' => 'all',
                'store' => 'blocks.content',
                'rows' => '15',
                'placeholder' => _CONTENT,
            ]),
            'is_full' => true,
        ];
    }
    $rows[] = [
        'label_for' => 'f-bpos',
        'label_html' => _POSITION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bpos',
            'selectid' => 'f-bpos',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'l', 'label_text' => _LEFT, 'is_selected' => (string)$bpos === 'l'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c', 'label_text' => _CENTERUP, 'is_selected' => (string)$bpos === 'c'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _CENTERDOWN, 'is_selected' => (string)$bpos === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _RIGHT, 'is_selected' => (string)$bpos === 'r']),
        ]),
    ];
    $where = explode(',', (string)$which);
    $stray = getBlockStray($where, getBlockPlaces());
    if ($stray) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _BLOCK_NOWHERE.': '.htmlspecialchars(implode(', ', $stray), ENT_QUOTES)]);
    $rows[] = [
        'label_html' => _BLOCK_VIEW,
        'label_id' => $labid = getFieldIds('', 'blockwhere')['label'],
        'field_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $labid, 'items' => getBlockWhereItems($where)]),
        'is_full' => true,
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_for' => 'f-lang',
            'label_html' => _LANGUAGE,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'lang',
                'selectid' => 'f-lang',
                'options_html' => getTplLanguageOptions((string)$lang),
            ]),
        ];
    }
    if ($expire) {
        $newexp = 0;
        $expire = strtotime($expire) - time();
        $expday = $expire / 86400;
        $exptxt = _PURCHASED.': '.getDuration($expire).' ('.round($expday, 3).' '._DAYS.')';
    } else {
        $newexp = 1;
        $exptxt = $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'is_required' => true,
            'input_attr' => 'min="0" max="'.$spec['days'].'"',
            'name_attr' => 'expire',
            'input_id' => 'f-expire',
            'describedby' => 'f-expire-hint',
            'placeholder_text' => _EXPIRATION,
            'value_attr' => '0',
        ]);
    }
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'label_id' => $labid = getFieldIds('', 'status')['label'],
        'field_html' => getTplRadioGroup([
            'labelledby' => $labid,
            'name' => 'status',
            'value' => (string)(int)$active,
            'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ]),
    ];
    $rows[] = [
        'label_for' => $newexp ? 'f-expire' : '',
        'label_html' => _EXPIRATION, 'hint_html' => _CONFINES, 'hint_id' => $newexp ? getFieldIds('f-expire')['hint'] : '',
        'field_html' => $exptxt,
    ];
    $rows[] = [
        'label_for' => 'f-action',
        'label_html' => _AFTEREXPIRATION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'action',
            'selectid' => 'f-action',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DEACTIVATE, 'is_selected' => (string)$action === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _DELETE, 'is_selected' => (string)$action === 'r']),
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-view',
        'label_html' => _VIEWPRIV,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'selectid' => 'f-view',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => (int)$view === 0])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS, 'is_selected' => (int)$view === 1])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN, 'is_selected' => (int)$view === 2])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVANON, 'is_selected' => (int)$view === 3]),
        ]),
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=blocks&op=editsave',
        'hidden' => [
            ['nameattr' => 'bid', 'valueattr' => (string)$bid],
            ['nameattr' => 'weight', 'valueattr' => (string)$weight],
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    setFoot();
}

function editsave(): void {
    global $afile;
    $bid = getVar('post', 'bid', 'num', 0);
    if (!checkAdminPost('blocks')) setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _TOKENMISS, true);
    $set = [
        'title' => getVar('post', 'title', 'title', ''),
        'content' => getVar('post', 'content', 'raw', ''),
        'url' => getVar('post', 'url', 'url', ''),
        'bfile' => getVar('post', 'bfile', 'text', ''),
        'bpos' => getVar('post', 'bpos', 'var', ''),
        'status' => getVar('post', 'status', 'num', 0),
        'refresh' => getVar('post', 'refresh', 'num', null),
        'lang' => getVar('post', 'lang', 'var', ''),
        'view' => getVar('post', 'view', 'num', 0),
        'days' => getVar('post', 'expire', 'num', null),
        'action' => getVar('post', 'action', 'var', 'd'),
        'which' => getVar('post', 'blockwhere[]', 'var', []),
    ];
    if ($set['bfile'] === 'node.php') {
        $pick = getVar('post', 'ntype[]', 'var', []);
        $node = ['type' => array_values(array_intersect(array_keys(getNodeTypeMap()), $pick)), 'mode' => getVar('post', 'nmode', 'raw', 'last')];
        $node['limit'] = intval(getVar('post', 'nlimit', 'num', 10));
        $set['param'] = (string)json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (count($node['type']) !== count(array_unique($pick))) $set['param'] = '';
    }
    $res = setBlockRow($bid, $set, getVar('post', 'weight', 'num', 0));
    if (is_int($res)) setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    if ($res === _NONE_FOUND) setRedirect($afile.'.php?name=blocks', false, 302, _NONE_FOUND, true);
    if (in_array($res, [_ERROR, _BLOCKPROBLEM], true)) setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, $res, true);
    edit($bid, ['title' => (string)$set['title'], 'content' => (string)$set['content'], 'url' => (string)$set['url'], 'bpos' => (string)$set['bpos'],
        'status' => (string)$set['status'], 'lang' => (string)$set['lang'], 'view' => (string)$set['view'], 'action' => (string)$set['action'],
        'which' => getBlockWhich($set['which'])], [$res]);
}

function change(): void {
    global $afile;
    $id = getVar('post', 'id', 'num', 0);
    $act = getVar('post', 'act', 'num', 0);
    $warn = !checkAdminPost('blocks');
    $res = (!$warn && $id) ? setBlockRow($id, ['status' => $act ? 0 : 1]) : _ERROR;
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : (is_int($res) ? _SUCCSTATUS : $res), !is_int($res));
}

function delete(): void {
    global $afile;
    $id = getVar('post', 'id', 'num', 0);
    $warn = !checkAdminPost('blocks');
    $res = (!$warn && $id) ? setBlockRow($id, null) : _ERROR;
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : (is_int($res) ? _SUCCDELETE : $res), !is_int($res));
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => getBlockTabsOps(),
        'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL],
    ]);
}

switch ($op) {
    default: blocks(); break;
    case 'add': add(); break;
    case 'addsave': addsave(); break;
    case 'edit': edit(); break;
    case 'editsave': editsave(); break;
    case 'change': change(); break;
    case 'fileadd': fileadd(); break;
    case 'fileedit': fileedit(); break;
    case 'filecode': filecode(); break;
    case 'filecodesave': filecodesave(); break;
    case 'fix': fix(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
