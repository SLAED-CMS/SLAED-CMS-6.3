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

function add(): void {
    global $db, $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 1]);
    $rows = [
        [
            'label_for' => 'f-title',
            'label_html' => _TITLE, 'hint_html' => _ADDCONST, 'hint_id' => $hntid = getFieldIds('f-title')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
                'itype' => 'text',
                'is_required' => true,
                'maxlength_num' => 60,
                'name_attr' => 'title',
                'input_id' => 'f-title',
                'placeholder_text' => _TITLE,
                'value_attr' => '',
            ]),
        ],
        [
            'label_for' => 'f-url',
            'label_html' => _RSSFILE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'url',
                'input_id' => 'f-url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => '',
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
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1800', 'label_text' => '30 '._MIN.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '3600', 'label_text' => '1 '._HOUR, 'is_selected' => true])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '18000', 'label_text' => '5 '._HOUR.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '36000', 'label_text' => '10 '._HOUR.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '86400', 'label_text' => '24 '._HOUR.'.']),
            ]),
        ],
    ];
    $bfopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NONE, 'is_selected' => true]);
    $files = scandir(BASE_DIR.'/blocks');
    foreach ($files as $file) {
        if (preg_match('/^(.+)\.php$/', $file, $matches)) {
            if ($file === 'node.php' || $db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) {
                $bfopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                ]);
            }
        }
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
        'label_for' => 'f-content',
        'label_html' => _CONTENT,
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'content',
            'input_id' => 'f-content',
            'rows_num' => 15,
            'value_text' => '',
        ]),
        'is_full' => true,
    ];
    $rows[] = [
        'label_for' => 'f-bpos',
        'label_html' => _POSITION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bpos',
            'selectid' => 'f-bpos',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'l', 'label_text' => _LEFT])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c', 'label_text' => _CENTERUP])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _CENTERDOWN])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _RIGHT]),
        ]),
    ];
    $rows[] = [
        'label_html' => _BLOCK_VIEW,
        'label_id' => $labid = getFieldIds('', 'blockwhere')['label'],
        'field_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $labid, 'items' => getBlockWhereItems([])]),
        'is_full' => true,
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_for' => 'f-lang',
            'label_html' => _LANGUAGE,
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'lang',
                'selectid' => 'f-lang',
                'options_html' => getTplLanguageOptions(),
            ]),
        ];
    }
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'label_id' => $labid = getFieldIds('', 'status')['label'],
        'field_html' => getTplRadioGroup([
            'labelledby' => $labid,
            'name' => 'status',
            'value' => '1',
            'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-expire',
        'label_html' => _EXPIRATION, 'hint_html' => _CONFINES, 'hint_id' => $hntid = getFieldIds('f-expire')['hint'],
        'field_html' => $tpl->getHtmlFrag('input', ['describedby' => $hntid,
            'itype' => 'number',
            'is_required' => true,
            'name_attr' => 'expire',
            'input_id' => 'f-expire',
            'placeholder_text' => _EXPIRATION,
            'value_attr' => '0',
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-action',
        'label_html' => _AFTEREXPIRATION,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'action',
            'selectid' => 'f-action',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DEACTIVATE])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _DELETE]),
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-view',
        'label_html' => _VIEWPRIV,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'selectid' => 'f-view',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => true])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVANON]),
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
    global $db, $afile, $tpl;
    $warn = !checkAdminPost('blocks');
    $title = filterCutText(getVar('post', 'title', 'title', ''), 60);
    $content = filterSourceText(getVar('post', 'content', 'raw', ''), isAdmin(true));
    $url = getVar('post', 'url', 'url', '');
    $bpos = getVar('post', 'bpos', 'var', '');
    $active = getVar('post', 'status', 'num', 0);
    $refresh = getVar('post', 'refresh', 'num', 0);
    $headline = getVar('post', 'headline', 'url', '');
    $lang = getVar('post', 'lang', 'var', '');
    if (!isset(getLanguageList()[$lang])) $lang = '';
    $bfile = getVar('post', 'bfile', 'text', '');
    $bfile = preg_match('/^[a-z0-9_\-]{1,251}\.php$/i', $bfile) ? $bfile : '';
    $view = getVar('post', 'view', 'num', 0);
    $days = getVar('post', 'expire', 'num', 0);
    $action = getVar('post', 'action', 'var', '');
    if (!in_array($action, ['d', 'r'], true)) $action = '';
    $url = filterCutText(($headline) ? $headline : $url, 200);
    $bwhere = getVar('post', 'blockwhere[]', 'var', []);
    $bkey = '';
    $btime = 0;
    if ($bfile != '') {
        $url = '';
        if ($title == '') $title = filterCutText(str_replace('_', ' ', str_replace('.php', '', $bfile)), 60);
    }
    if ($url) {
        $btime = time();
        $content = getRssBody($url) ?? '';
    }
    if ($warn) {
        setRedirect($afile.'.php?name=blocks&op=add', false, 302, _TOKENMISS, true);
    }
    if (($content == '') && ($bfile == '')) {
        setHead();
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _RSSFAIL]).$tpl->getHtmlPart('box', [
            'content_html' => _GOBACK,
        ]);
        setFoot();
    } else {
        $param = ($bfile === 'node.php') ? json_encode(['type' => [], 'mode' => 'last', 'limit' => 10]) : '';
        $sql = 'INSERT INTO '.PREFIX_DB.'_blocks VALUES (NULL, :bkey, :title, :content, :url, :bpos, 0, :active, :refresh, :btime, :lang, :bfile, :view,'
            .(($days > 0) ? ' NOW() + INTERVAL :days DAY,' : ' NULL,').' :action, :which, :param)';
        $done = in_array($bpos, ['l', 'r', 'c', 'd'], true) && $db->setSqlBegin();
        $done = $done && $db->getSqlQuery($sql, [
            'bkey' => $bkey, 'title' => $title, 'content' => $content, 'url' => $url, 'bpos' => $bpos, 'active' => $active, 'refresh' => $refresh,
            'btime' => $btime, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'action' => $action, 'which' => getBlockWhich($bwhere), 'param' => $param,
        ] + (($days > 0) ? ['days' => $days] : [])) !== false;
        $done = $done && setBlockOrder($bpos, [(int)$db->getSqlLastId()]) && $db->setSqlCommit();
        if (!$done) {
            $db->setSqlRollback();
            setRedirect($afile.'.php?name=blocks&op=add', false, 302, _ERROR, true);
        }
        setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    }
}

function filecode(): void {
    global $db, $afile, $tpl;
    if (!checkAdminPost('blocks')) {
        setHead();
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
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
                setRedirect($afile.'.php?name=blocks&op=logview');
                return;
            }
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
        setHead();
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
        $dir = BASE_DIR.'/blocks';
        $path = BASE_DIR.'/blocks/'.$bf;
        $cont .= checkPerms($dir).$tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BLOCK.': '.$path]);
        if (file_exists($path)) $cont .= checkPerms($path).$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _B_FEDIT]);
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
                'text' => trim($out[1] ?? ''),
            ]),
            'is_full' => true,
        ]];
        echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
            'action_url' => $afile.'.php?name=blocks&op=filecodesave',
            'hidden' => [
                ['nameattr' => 'bf', 'valueattr' => $bf],
                ['nameattr' => 'flag', 'valueattr' => $flaged],
                ['nameattr' => 'name', 'valueattr' => 'blocks'],
                ['nameattr' => 'op', 'valueattr' => 'filecodesave'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken('blocks')],
            ],
            'rows' => $rows,
            'submit_label' => _SAVE,
        ])]);
        setFoot();
    } else {
        setRedirect($afile.'.php?name=blocks&op=logview');
    }
}

function filecodesave(): void {
    global $afile;
    $warn = !checkAdminPost('blocks');
    $btext = getVar('post', 'blocktext', 'raw', '');
    $bf = getVar('post', 'bf', 'text', '');
    $bf = preg_match('/^[a-z0-9_\-]+\.php$/i', $bf) ? $bf : '';
    if (!$warn && $btext && $bf) {
        if ($handle = fopen(BASE_DIR.'/blocks/'.$bf, 'wb')) {
            $htmlb = '';
            $htmle = '';
            $flag = getVar('post', 'flag', 'var', '');
            if ($flag == 'html') {
                $htmlb = "\$content = <<<BLOCKHTML\n";
                $htmle = "\nBLOCKHTML;\n";
            }
            fwrite($handle, '<?php'."\n".'# Author: Eduard Laas'."\n".'# 2005 - '.date('Y').' SLAED'."\n".'# License: MIT'."\n".'# Website: slaed.net'."\n\n"
                .'if (!defined(\'BLOCK_FILE\')) {'."\n".'header(\'Location: ../index.php\');'."\n".'exit;'."\n".'}'."\n\n".$htmlb.$btext.$htmle."\n".'?>');
            fclose($handle);
            setRedirect($afile.'.php?name=blocks', false, 302, _SUCCFILESAVE);
        }
    }
    setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, $warn ? _TOKENMISS : '', $warn);
}

function edit(): void {
    global $afile, $conf, $db, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _MANUAL], 'tab' => 3]);
    $bid = getVar('get', 'id', 'num');
    $sql = 'SELECT bkey, title, content, url, bpos, weight, status, refresh, lang, bfile, view, expire, action, which, param FROM '.PREFIX_DB.'_blocks WHERE id = :bid';
    $row = $db->getSqlRow($db->getSqlQuery($sql, ['bid' => $bid]));
    [$bkey, $title, $content, $url, $bpos, $weight, $active, $refresh, $lang, $bfile, $view, $expire, $action, $which, $param] = $row;
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
            'maxlength_num' => 50,
            'name_attr' => 'title',
            'input_id' => 'f-title',
            'placeholder_text' => _TITLE,
            'value_attr' => (string)$title,
        ]),
    ]];
    if ($bfile != '') {
        $bfopts = '';
        $files = scandir(BASE_DIR.'/blocks');
        if (!in_array($bfile, $files, true)) {
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _BLOCK_NOFILE.': blocks/'.htmlspecialchars($bfile, ENT_QUOTES)]);
            $bfopts .= $tpl->getHtmlFrag('select-option', ['value_attr' => $bfile, 'label_text' => $bfile.' — '._BLOCK_NOFILE, 'is_selected' => true]);
        }
        foreach ($files as $file) {
            if (preg_match('/^(.+)\.php$/', $file, $matches)) {
                $bfopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                    'is_selected' => $bfile == $file,
                ]);
            }
        }
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
            'label_for' => 'f-content',
            'label_html' => _CONTENT,
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'content',
                'input_id' => 'f-content',
                'rows_num' => 15,
                'value_text' => (string)$content,
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
            ['nameattr' => 'oldposition', 'valueattr' => (string)$bpos],
            ['nameattr' => 'bid', 'valueattr' => (string)$bid],
            ['nameattr' => 'bkey', 'valueattr' => (string)$bkey],
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
    global $db, $afile;
    $warn = !checkAdminPost('blocks');
    $bid = getVar('post', 'bid', 'num');
    $bkey = filterCutText(getVar('post', 'bkey', 'var', ''), 15);
    $title = filterCutText(getVar('post', 'title', 'title', ''), 60);
    $content = filterSourceText(getVar('post', 'content', 'raw', ''), isAdmin(true));
    $url = filterCutText(getVar('post', 'url', 'url', ''), 200);
    $oldpos = getVar('post', 'oldposition', 'var', '');
    $bpos = getVar('post', 'bpos', 'var', '');
    $active = getVar('post', 'status', 'num', 0);
    $refresh = getVar('post', 'refresh', 'num', 0);
    $weight = getVar('post', 'weight', 'num', 0);
    $lang = getVar('post', 'lang', 'var', '');
    if (!isset(getLanguageList()[$lang])) $lang = '';
    $bfile = getVar('post', 'bfile', 'text', '');
    $bfile = preg_match('/^[a-z0-9_\-]{1,251}\.php$/i', $bfile) ? $bfile : '';
    $view = getVar('post', 'view', 'num', 0);
    $days = getVar('post', 'expire', 'num', 0);
    $action = getVar('post', 'action', 'var', '');
    if (!in_array($action, ['d', 'r'], true)) $action = '';
    $bwhere = getVar('post', 'blockwhere[]', 'var', []);
    if ($warn) {
        setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _TOKENMISS, true);
    }
    if (!in_array($bpos, ['l', 'r', 'c', 'd'], true)) setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _ERROR, true);
    $param = '';
    if ($bfile === 'node.php') {
        $pick = getVar('post', 'ntype[]', 'var', []);
        $set = ['type' => array_values(array_intersect(array_keys(getNodeTypeMap()), $pick)), 'mode' => getVar('post', 'nmode', 'raw', 'last')];
        $set['limit'] = intval(getVar('post', 'nlimit', 'num', 10));
        $param = (string)json_encode($set, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (count($set['type']) !== count(array_unique($pick)) || getNodeBlockParam($param) === null) {
            setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _BLOCKPROBLEM, true);
        }
    }
    $which = getBlockWhich($bwhere);
    $fresh = false;
    if ($url) {
        $bkey = '';
        $content = getRssBody($url);
        $fresh = $content !== null;
        if (!$fresh) [$content] = $db->getSqlRow($db->getSqlQuery('SELECT content FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]));
    }
    $fail = !$db->setSqlBegin();
    $put = function (string $sql, array $pars) use ($db, &$fail): PDOStatement|false {
        $res = $fail ? false : $db->getSqlQuery($sql, $pars);
        if ($res === false) $fail = true;
        return $res;
    };
    $put('UPDATE '.PREFIX_DB.'_blocks SET param = :param, which = :which WHERE id = :bid', ['param' => $param, 'which' => $which, 'bid' => $bid]);
    $pars = ['bkey' => $bkey, 'title' => $title, 'content' => $content, 'url' => $url, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang,
        'bfile' => $bfile, 'view' => $view, 'bid' => $bid];
    $sql = 'UPDATE '.PREFIX_DB.'_blocks SET bkey = :bkey, title = :title, content = :content, url = :url, status = :active, refresh = :refresh,'
        .' lang = :lang, bfile = :bfile, view = :view';
    if ($fresh) {
        $sql .= ', time = :time';
        $pars['time'] = time();
    }
    if ($url) {
        $put($sql.' WHERE id = :bid', $pars);
    } else {
        $exp = ($days > 0) ? ', expire = COALESCE(expire, NOW() + INTERVAL :days DAY)' : '';
        $put($sql.$exp.', action = :action WHERE id = :bid', ['action' => $action] + (($days > 0) ? ['days' => $days] : []) + $pars);
    }
    if (!$fail && $oldpos !== $bpos && !setBlockOrder($bpos, [$bid], $weight)) $fail = true;
    if (!$fail && !$db->setSqlCommit()) $fail = true;
    if ($fail) {
        $db->setSqlRollback();
        setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _ERROR, true);
    }
    setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
}

function change(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $act = getVar('post', 'act', 'num', 0);
    $warn = !checkAdminPost('blocks');
    $wipe = $act ? '' : ', expire = CASE WHEN expire < NOW() THEN NULL ELSE expire END';
    $res = (!$warn && $id) ? $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = :active'.$wipe.' WHERE id = :id', ['active' => $act ? 0 : 1, 'id' => $id]) : false;
    $done = $res && $res->rowCount() > 0;
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCSTATUS : _ERROR), !$done);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('post', 'id', 'num');
    $warn = !checkAdminPost('blocks');
    $row = (!$warn && $id) ? $db->getSqlRow($db->getSqlQuery('SELECT bpos FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id])) : false;
    $done = $row && $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id]) !== false;
    if ($done && in_array($row[0], ['l', 'r', 'c', 'd'], true)) setBlockOrder((string)$row[0]);
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
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
