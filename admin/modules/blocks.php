<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getBlockTabsOps(): array {
    return [
        'name=blocks',
        'name=blocks&amp;op=add',
        'name=blocks&amp;op=fileadd',
        'name=blocks&amp;op=fileedit',
        'name=blocks&amp;op=fix&amp;token='.getSiteToken(),
        'name=blocks&amp;op=info',
    ];
}

function blocks(): void {
    global $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO]]);
    echo $cont.$tpl->getHtmlPart('box', [
        'box_id' => 'repajax_block',
        'content_html' => getAdminBlockList(getSiteToken()),
    ]);
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
    $rows = [
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _TITLE.':', 'hint' => _ADDCONST]),
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'is_required' => true,
                'maxlength_num' => 60,
                'name_attr' => 'title',
                'placeholder_text' => _TITLE,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => _RSSFILE.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _RSSC.':', 'hint' => _RSSLINESINFO.' '._RSSINFO]),
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'headline',
                'options_html' => $tpl->getHtmlFrag('select-option', [
                    'value_attr' => '0',
                    'label_text' => _CUSTOM,
                    'is_selected' => true,
                ]).rss_select(),
            ]),
        ],
        [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _REFRESHTIME.':', 'hint' => _REFINFO]),
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'refresh',
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1800', 'label_text' => '30 '._MIN.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '3600', 'label_text' => '1 '._HOUR, 'is_selected' => true])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '18000', 'label_text' => '5 '._HOUR.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '36000', 'label_text' => '10 '._HOUR.'.'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '86400', 'label_text' => '24 '._HOUR.'.']),
            ]),
        ],
    ];
    $bfopts = $tpl->getHtmlFrag('select-option', ['value_attr' => '', 'label_text' => _NONE, 'is_selected' => true]);
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) {
                $bfopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                ]);
            }
        }
    }
    $viewItems = '';
    foreach (getBlockModules() as $mod) {
        $viewItems .= $tpl->getHtmlFrag('label-item', [
            'input_html' => $tpl->getHtmlFrag('checkbox', [
                'name_attr' => 'blockwhere[]',
                'value_attr' => $mod,
            ]),
            'label_html' => $tpl->getHtmlFrag('title-tip', [
                'label_text' => getModuleName($mod),
                'title_text' => _MODUL.': '.$mod,
            ]),
        ]);
    }
    foreach ([
        ['value' => 'ihome', 'label' => _HOME],
        ['value' => 'home', 'label' => _INHOME],
        ['value' => 'all', 'label' => _BLOCK_ALL],
        ['value' => 'otricanie', 'label' => _DENYING],
        ['value' => 'infly', 'label' => _INFLY],
        ['value' => 'flyfix', 'label' => _FLY_FIX],
    ] as $item) {
        $viewItems .= $tpl->getHtmlFrag('label-item', [
            'input_html' => $tpl->getHtmlFrag('checkbox', [
                'name_attr' => 'blockwhere[]',
                'value_attr' => $item['value'],
            ]),
            'label_html' => $item['label'],
        ]);
    }
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _FILENAME.':', 'hint' => _FILENAMEIN]),
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bfile',
            'options_html' => $bfopts,
        ]),
    ];
    $rows[] = [
        'label_html' => _CONTENT.':',
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'name_attr' => 'content',
            'rows_num' => 15,
            'value_text' => '',
        ]),
        'is_full' => true,
    ];
    $rows[] = [
        'label_html' => _POSITION.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bpos',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'l', 'label_text' => _LEFT])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c', 'label_text' => _CENTERUP])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _CENTERDOWN])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _RIGHT])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'b', 'label_text' => _BANNERUP])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'f', 'label_text' => _BANNERDOWN]),
        ]),
    ];
    $rows[] = [
        'label_html' => _BLOCK_VIEW.':',
        'field_html' => $tpl->getHtmlFrag('radio-group', ['items_html' => $viewItems]),
        'is_full' => true,
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_html' => _LANGUAGE.':',
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'lang',
                'options_html' => getTplLanguageOptions(),
            ]),
        ];
    }
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'field_html' => getTplRadioGroup(['name' => 'status', 'value' => '1', 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EXPIRATION.':', 'hint' => _CONFINES]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'is_required' => true,
            'name_attr' => 'expire',
            'placeholder_text' => _EXPIRATION,
            'value_attr' => '0',
        ]),
    ];
    $rows[] = [
        'label_html' => _AFTEREXPIRATION.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'action',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DEACTIVATE])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _DELETE]),
        ]),
    ];
    $rows[] = [
        'label_html' => _VIEWPRIV,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => true])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVANON]),
        ]),
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=blocks&amp;op=addsave',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'addsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _CREATEBLOCK,
    ])]);
    setFoot();
}

function fileadd(): void {
    global $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 2]);
    $cont .= checkPerms(BASE_DIR.'/blocks/');
    $rows = [
        [
            'label_html' => _FILENAME.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'text',
                'is_required' => true,
                'maxlength_num' => 200,
                'name_attr' => 'bf',
                'placeholder_text' => _FILENAME,
                'value_attr' => '',
            ]),
        ],
        [
            'label_html' => _TYPE.':',
            'field_html' => getTplRadioGroup(['name' => 'flag', 'value' => 'php', 'options' => [['value' => 'php', 'label' => 'PHP'], ['value' => 'html', 'label' => 'HTML']]]),
        ],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=blocks&amp;op=filecode',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'filecode'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _CREATEBLOCK,
    ])]);
    setFoot();
}

function fileedit(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
    $opts = '';
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                ]);
            }
        }
    }
    $rows = [[
        'label_html' => _FILENAME.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bf',
            'options_html' => $opts,
        ]),
    ]];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=blocks&amp;op=filecode',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'filecode'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _EDITBLOCK,
    ])]);
    setFoot();
}

function fix(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    if (!$warn) {
    $pos = ['b', 'c', 'd', 'f', 'l', 'r'];
    foreach ($pos as $val) {
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE bpos = :val ORDER BY weight ASC', ['val' => $val]);
        $weight = 0;
        while ([$bid] = $db->getSqlRow($result)) {
            $weight++;
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $bid]);
        }
    }
    }
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function addsave(): void {
    global $db, $afile, $tpl;
    $warn = !checkSiteToken();
    $title = getVar('post', 'title', 'title', '');
    $content = getVar('post', 'content', 'text', '');
    $url = getVar('post', 'url', 'url', '');
    $bpos = getVar('post', 'bpos', 'var', '');
    $active = getVar('post', 'status', 'num', 0);
    $refresh = getVar('post', 'refresh', 'num', 0);
    $headline = getVar('post', 'headline', 'url', '');
    $lang = getVar('post', 'lang', 'var', '');
    $bfile = getVar('post', 'bfile', 'text', '');
    $bfile = preg_match('/^block\-[a-z0-9_\-]+\.php$/i', $bfile) ? $bfile : '';
    $view = getVar('post', 'view', 'num', 0);
    $expire = getVar('post', 'expire', 'num', 0);
    $action = getVar('post', 'action', 'var', '');
    $url = ($headline) ? $headline : $url;
    $blockwhere = getVar('post', 'blockwhere[]', 'var', []);
    [$weight] = $db->getSqlRow($db->getSqlQuery('SELECT weight FROM '.PREFIX_DB.'_blocks WHERE bpos = :bpos ORDER BY weight DESC', ['bpos' => $bpos]));
    $weight++;
    $bkey = '';
    $btime = '';
    if ($bfile != '') {
        $url = '';
        if ($title == '') $title = str_replace('_', ' ', str_replace(['block-', '.php'], '', $bfile));
    }
    if ($url) {
        $btime = time();
        $content = rss_read($url, 1);
    }
    if ($warn) {
        setRedirect($afile.'.php?name=blocks&op=add', false, 302, _TOKENMISS, true);
    }
    if (($content == '') && ($bfile == '')) {
        setHead();
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _RSSFAIL]).$tpl->getHtmlPart('box', [
            'content_html' => _GOBACK,
        ]);
        setFoot();
    } else {
        if ($expire == '' || $expire == 0) {
            $expire = 0;
        } else {
            $expire = time() + ($expire * 86400);
        }
        if (isset($blockwhere)) {
            $which = '';
            $which = (in_array('all', $blockwhere)) ? 'all' : $which;
            $which = (in_array('home', $blockwhere)) ? 'home' : $which;
            if ($which == '') $which = implode(',', $blockwhere);
        }
        $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_blocks VALUES (NULL, :bkey, :title, :content, :url, :bpos, :weight, :active, :refresh, :btime, :lang, :bfile, :view, :expire, :action, :which)', [
            'bkey' => $bkey, 'title' => $title, 'content' => $content, 'url' => $url, 'bpos' => $bpos, 'weight' => $weight, 'active' => $active, 'refresh' => $refresh, 'btime' => $btime, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'expire' => $expire, 'action' => $action, 'which' => $which
        ]);
        setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    }
}

function filecode(): void {
    global $db, $afile, $tpl;
    if (!checkSiteToken()) {
        setHead();
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _TOKENMISS]);
        setFoot();
        return;
    }
    $bf = getVar('post', 'bf', 'text', '');
    if ($bf != '') {
        $flag = getVar('post', 'flag', 'var', '');
        if ($flag) {
            $flaged = $flag;
            $bf = preg_replace('/[^a-z0-9_\-]/i', '', str_replace(['block-', '.php'], '', $bf));
            $bf = 'block-'.$bf.'.php';
        } else {
            $bf = preg_match('/^block\-[a-z0-9_\-]+\.php$/i', $bf) ? $bf : '';
            if ($bf === '') {
                setRedirect($afile.'.php?name=blocks&op=logview');
                return;
            }
            $bfstr = file_get_contents('blocks/'.$bf);
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
        $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
        $cont .= checkPerms(BASE_DIR.'/blocks/');
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BLOCK.': '.$bf]);
        if (file_exists('blocks/'.$bf)) {
            $cont .= checkPerms(BASE_DIR.'/blocks/'.$bf);
            $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _B_FEDIT]);
        }
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _EINFOPHP]);
        $rows = [[
            'label_html' => _FILENAME.':',
            'field_html' => htmlspecialchars($bf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        ], [
            'label_html' => _CONTENT.':',
            'field_html' => Editor::getCode([
                'id' => 'code',
                'name' => 'blocktext',
                'lang' => 'php',
                'text' => trim($out[1] ?? ''),
            ]),
            'is_full' => true,
        ]];
        echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
            'action_url' => $afile.'.php?name=blocks&amp;op=filecodesave',
            'hidden' => [
                ['nameattr' => 'bf', 'valueattr' => $bf],
                ['nameattr' => 'flag', 'valueattr' => $flaged],
                ['nameattr' => 'name', 'valueattr' => 'blocks'],
                ['nameattr' => 'op', 'valueattr' => 'filecodesave'],
                ['nameattr' => 'token', 'valueattr' => getSiteToken()],
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
    $warn = !checkSiteToken();
    $blocktext = (string)getVar('post', 'blocktext', 'raw', '');
    $bf = getVar('post', 'bf', 'text', '');
    $bf = preg_match('/^block\-[a-z0-9_\-]+\.php$/i', $bf) ? $bf : '';
    if (!$warn && $blocktext && $bf) {
        if ($handle = fopen('blocks/'.$bf, 'wb')) {
            $html_b = '';
            $html_e = '';
            $flag = getVar('post', 'flag', 'var', '');
            if ($flag == 'html') {
                $html_b = "\$content = <<<BLOCKHTML\r\n";
                $html_e = "\r\nBLOCKHTML;\r\n";
            }
            fwrite($handle, '<?php'.PHP_EOL.'# Author: Eduard Laas'.PHP_EOL.'# Copyright (c) 2005 - '.date('Y').' SLAED'.PHP_EOL.'# License: GNU GPL 3'.PHP_EOL.'# Website: slaed.net'.PHP_EOL.PHP_EOL.'if (!defined(\'BLOCK_FILE\')) {'.PHP_EOL.'header(\'Location: ../index.php\');'.PHP_EOL.'exit;'.PHP_EOL.'}'.PHP_EOL.PHP_EOL.$html_b.$blocktext.$html_e.PHP_EOL.'?>');
            fclose($handle);
            setRedirect($afile.'.php?name=blocks', false, 302, _SUCCFILESAVE);
        }
    }
    setRedirect($afile.'.php?name=blocks&op=fileedit', false, 302, $warn ? _TOKENMISS : '', $warn);
}

function edit(): void {
    global $afile, $conf, $db, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => getBlockTabsOps(), 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
    $bid = getVar('get', 'id', 'num');
    [$bkey, $title, $content, $url, $bpos, $weight, $active, $refresh, $lang, $bfile, $view, $expire, $action, $which] = $db->getSqlRow($db->getSqlQuery('SELECT bkey, title, content, url, bpos, weight, status, refresh, lang, bfile, view, expire, action, which FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]));
    if ($url != '') {
        $type = '('._BLOCKRSS.')';
    } elseif ($bfile != '') {
        $type = '('._BLOCKFILE.')';
    } else {
        $type = '('._BLOCKHTML.')';
    }
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _BLOCK.': '.$title.' '.$type]);
    $rows = [[
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _TITLE.':', 'hint' => _ADDCONST]),
        'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'text',
            'is_required' => true,
            'maxlength_num' => 50,
            'name_attr' => 'title',
            'placeholder_text' => _TITLE,
            'value_attr' => (string)$title,
        ]),
    ]];
    if ($bfile != '') {
        $bfopts = '';
        $files = scandir('blocks');
        foreach ($files as $file) {
            if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
                $bfopts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $matches[0],
                    'is_selected' => $bfile == $file,
                ]);
            }
        }
        $rows[] = [
            'label_html' => _FILENAME.':',
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'bfile',
                'options_html' => $bfopts,
            ]),
        ];
    } elseif ($url != '') {
        $rows[] = [
            'label_html' => _RSSFILE.':',
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'url',
                'name_attr' => 'url',
                'placeholder_text' => _RSSFILE,
                'value_attr' => (string)$url,
            ]),
        ];
        $rows[] = [
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _REFRESHTIME.':', 'hint' => _REFINFO]),
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'refresh',
                'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '1800', 'label_text' => '30 '._MIN.'.', 'is_selected' => (string)$refresh === '1800'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '3600', 'label_text' => '1 '._HOUR, 'is_selected' => (string)$refresh === '3600'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '18000', 'label_text' => '5 '._HOUR.'.', 'is_selected' => (string)$refresh === '18000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '36000', 'label_text' => '10 '._HOUR.'.', 'is_selected' => (string)$refresh === '36000'])
                    .$tpl->getHtmlFrag('select-option', ['value_attr' => '86400', 'label_text' => '24 '._HOUR.'.', 'is_selected' => (string)$refresh === '86400']),
            ]),
        ];
    } else {
        $rows[] = [
            'label_html' => _CONTENT.':',
            'field_html' => $tpl->getHtmlFrag('textarea', [
                'name_attr' => 'content',
                'rows_num' => 15,
                'value_text' => (string)$content,
            ]),
            'is_full' => true,
        ];
    }
    $rows[] = [
        'label_html' => _POSITION.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'bpos',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'l', 'label_text' => _LEFT, 'is_selected' => (string)$bpos === 'l'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'c', 'label_text' => _CENTERUP, 'is_selected' => (string)$bpos === 'c'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _CENTERDOWN, 'is_selected' => (string)$bpos === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _RIGHT, 'is_selected' => (string)$bpos === 'r'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'b', 'label_text' => _BANNERUP, 'is_selected' => (string)$bpos === 'b'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'f', 'label_text' => _BANNERDOWN, 'is_selected' => (string)$bpos === 'f']),
        ]),
    ];
    $where = explode(',', (string)($which ?? ''));
    $viewItems = '';
    foreach (getBlockModules() as $mod) {
        $viewItems .= $tpl->getHtmlFrag('label-item', [
            'input_html' => $tpl->getHtmlFrag('checkbox', [
                'is_checked' => in_array($mod, $where),
                'name_attr' => 'blockwhere[]',
                'value_attr' => $mod,
            ]),
            'label_html' => $tpl->getHtmlFrag('title-tip', [
                'label_text' => getModuleName($mod),
                'title_text' => _MODUL.': '.$mod,
            ]),
        ]);
    }
    $homeOn = in_array('home', $where);
    foreach ([
        ['value' => 'ihome', 'label' => _HOME, 'checked' => in_array('ihome', $where)],
        ['value' => 'home', 'label' => _INHOME, 'checked' => $homeOn],
        ['value' => 'all', 'label' => _BLOCK_ALL, 'checked' => in_array('all', $where) && !$homeOn],
        ['value' => 'otricanie', 'label' => _DENYING, 'checked' => in_array('otricanie', $where)],
        ['value' => 'infly', 'label' => _INFLY, 'checked' => in_array('infly', $where)],
        ['value' => 'flyfix', 'label' => _FLY_FIX, 'checked' => in_array('flyfix', $where)],
    ] as $item) {
        $viewItems .= $tpl->getHtmlFrag('label-item', [
            'input_html' => $tpl->getHtmlFrag('checkbox', [
                'is_checked' => $item['checked'],
                'name_attr' => 'blockwhere[]',
                'value_attr' => $item['value'],
            ]),
            'label_html' => $item['label'],
        ]);
    }
    $rows[] = [
        'label_html' => _BLOCK_VIEW.':',
        'field_html' => $tpl->getHtmlFrag('radio-group', ['items_html' => $viewItems]),
        'is_full' => true,
    ];
    if ($conf['multilingual'] == 1) {
        $rows[] = [
            'label_html' => _LANGUAGE.':',
            'field_html' => $tpl->getHtmlFrag('select', [
                'name_attr' => 'lang',
                'options_html' => getTplLanguageOptions((string)$lang),
            ]),
        ];
    }
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expireText = $tpl->getHtmlFrag('hidden', ['nameattr' => 'expire', 'valueattr' => (string)$oldexpire])
            ._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expireText = $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'is_required' => true,
            'name_attr' => 'expire',
            'placeholder_text' => _EXPIRATION,
            'value_attr' => '0',
        ]);
    }
    $rows[] = [
        'label_html' => _ACTIVATE2,
        'field_html' => getTplRadioGroup(['name' => 'status', 'value' => (string)(int)$active, 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]]),
    ];
    $rows[] = [
        'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _EXPIRATION.':', 'hint' => _CONFINES]),
        'field_html' => $expireText,
    ];
    $rows[] = [
        'label_html' => _AFTEREXPIRATION.':',
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'action',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => 'd', 'label_text' => _DEACTIVATE, 'is_selected' => (string)$action === 'd'])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => 'r', 'label_text' => _DELETE, 'is_selected' => (string)$action === 'r']),
        ]),
    ];
    $rows[] = [
        'label_html' => _VIEWPRIV,
        'field_html' => $tpl->getHtmlFrag('select', [
            'name_attr' => 'view',
            'options_html' => $tpl->getHtmlFrag('select-option', ['value_attr' => '0', 'label_text' => _MVALL, 'is_selected' => (int)$view === 0])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '1', 'label_text' => _MVUSERS, 'is_selected' => (int)$view === 1])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '2', 'label_text' => _MVADMIN, 'is_selected' => (int)$view === 2])
                .$tpl->getHtmlFrag('select-option', ['value_attr' => '3', 'label_text' => _MVANON, 'is_selected' => (int)$view === 3]),
        ]),
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlFrag('form', [
        'action_url' => $afile.'.php?name=blocks&amp;op=editsave',
        'hidden' => [
            ['nameattr' => 'oldposition', 'valueattr' => (string)$bpos],
            ['nameattr' => 'bid', 'valueattr' => (string)$bid],
            ['nameattr' => 'newexpire', 'valueattr' => (string)$newexpire],
            ['nameattr' => 'bkey', 'valueattr' => (string)$bkey],
            ['nameattr' => 'weight', 'valueattr' => (string)$weight],
            ['nameattr' => 'name', 'valueattr' => 'blocks'],
            ['nameattr' => 'op', 'valueattr' => 'editsave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'rows' => $rows,
        'submit_label' => _SAVE,
    ])]);
    setFoot();
}

function editsave(): void {
    global $db, $afile;
    $warn = !checkSiteToken();
    $newexpire = getVar('post', 'newexpire', 'num', 0);
    $bid = getVar('post', 'bid', 'num');
    $bkey = getVar('post', 'bkey', 'var', '');
    $title = getVar('post', 'title', 'title', '');
    $content = getVar('post', 'content', 'text', '');
    $url = getVar('post', 'url', 'url', '');
    $oldposition = getVar('post', 'oldposition', 'var', '');
    $bpos = getVar('post', 'bpos', 'var', '');
    $active = getVar('post', 'status', 'num', 0);
    $refresh = getVar('post', 'refresh', 'num', 0);
    $weight = getVar('post', 'weight', 'num', 0);
    $lang = getVar('post', 'lang', 'var', '');
    $bfile = getVar('post', 'bfile', 'text', '');
    $bfile = preg_match('/^block\-[a-z0-9_\-]+\.php$/i', $bfile) ? $bfile : '';
    $view = getVar('post', 'view', 'num', 0);
    $expire = getVar('post', 'expire', 'num', 0);
    $action = getVar('post', 'action', 'var', '');
    $blockwhere = getVar('post', 'blockwhere[]', 'var', []);
    if ($warn) {
        setRedirect($afile.'.php?name=blocks&op=edit&id='.$bid, false, 302, _TOKENMISS, true);
    }
    if (isset($blockwhere)) {
        $which = '';
        if (in_array('all', $blockwhere)) $which = 'all';
        if (in_array('home', $blockwhere)) $which = 'home';
        if ($which == '') {
            $which = implode(',', $blockwhere);
        } else {
            if (in_array('otricanie', $blockwhere)) $which .= ',otricanie';
            if (in_array('flyfix', $blockwhere)) $which .= ',flyfix';
        }
        if (in_array('infly', $blockwhere)) {
            if (in_array('flyfix', $blockwhere)) {
                $which = 'infly,'.str_replace('infly,', '', $which);
            } else {
                $which = 'infly,';
            }
        }
        if (in_array('ihome', $blockwhere) && $which != 'home') {
            $which = 'ihome,'.str_replace(',ihome', '', $which);
        }
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET which = :which WHERE id = :bid', ['which' => $which, 'bid' => $bid]);
    } else {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET which = \'\' WHERE id = :bid', ['bid' => $bid]);
    }
    if ($url) {
        $bkey = '';
        $content = rss_read($url, 1);
        if ($oldposition != $bpos) {
            $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight >= :weight AND bpos = :bpos', ['weight' => $weight, 'bpos' => $bpos]);
            $fweight = $weight;
            $oweight = $weight;
            while ([$nbid] = $db->getSqlRow($result)) {
                $weight++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $nbid]);
            }
            $result2 = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight > :oweight AND bpos = :oldposition', ['oweight' => $oweight, 'oldposition' => $oldposition]);
            while ([$obid] = $db->getSqlRow($result2)) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :oweight WHERE id = :bid', ['oweight' => $oweight, 'bid' => $obid]);
                $oweight++;
            }
            [$lastw] = $db->getSqlRow($db->getSqlQuery('SELECT weight FROM '.PREFIX_DB.'_blocks WHERE bpos = :bpos ORDER BY weight DESC LIMIT 0,1', ['bpos' => $bpos]));
            if ($lastw <= $fweight) {
                $lastw++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET title = :title, content = :content, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view WHERE id = :bid', [
                    'title' => $title, 'content' => $content, 'bpos' => $bpos, 'weight' => $lastw, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'bid' => $bid
                ]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET title = :title, content = :content, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view WHERE id = :bid', [
                    'title' => $title, 'content' => $content, 'bpos' => $bpos, 'weight' => $fweight, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'bid' => $bid
                ]);
            }
        } else {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET bkey = :bkey, title = :title, content = :content, url = :url, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view WHERE id = :bid', [
                'bkey' => $bkey, 'title' => $title, 'content' => $content, 'url' => $url, 'bpos' => $bpos, 'weight' => $weight, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'bid' => $bid
            ]);
        }
        setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    } else {
        if ($oldposition != $bpos) {
            $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight >= :weight AND bpos = :bpos', ['weight' => $weight, 'bpos' => $bpos]);
            $fweight = $weight;
            $oweight = $weight;
            while ([$nbid] = $db->getSqlRow($result)) {
                $weight++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $nbid]);
            }
            $result2 = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight > :oweight AND bpos = :oldposition', ['oweight' => $oweight, 'oldposition' => $oldposition]);
            while ([$obid] = $db->getSqlRow($result2)) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :oweight WHERE id = :bid', ['oweight' => $oweight, 'bid' => $obid]);
                $oweight++;
            }
            [$lastw] = $db->getSqlRow($db->getSqlQuery('SELECT weight FROM '.PREFIX_DB.'_blocks WHERE bpos = :bpos ORDER BY weight DESC LIMIT 0,1', ['bpos' => $bpos]));
            if ($lastw <= $fweight) {
                $lastw++;
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET title = :title, content = :content, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view WHERE id = :bid', [
                    'title' => $title, 'content' => $content, 'bpos' => $bpos, 'weight' => $lastw, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'bid' => $bid
                ]);
            } else {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET title = :title, content = :content, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view WHERE id = :bid', [
                    'title' => $title, 'content' => $content, 'bpos' => $bpos, 'weight' => $fweight, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'bid' => $bid
                ]);
            }
        } else {
            if ($expire == '') $expire = 0;
            if ($newexpire == 1 && $expire != 0) $expire = time() + ($expire * 86400);
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET bkey = :bkey, title = :title, content = :content, url = :url, bpos = :bpos, weight = :weight, status = :active, refresh = :refresh, lang = :lang, bfile = :bfile, view = :view, expire = :expire, action = :action WHERE id = :bid', [
                'bkey' => $bkey, 'title' => $title, 'content' => $content, 'url' => $url, 'bpos' => $bpos, 'weight' => $weight, 'active' => $active, 'refresh' => $refresh, 'lang' => $lang, 'bfile' => $bfile, 'view' => $view, 'expire' => $expire, 'action' => $action, 'bid' => $bid
            ]);
        }
        setRedirect($afile.'.php?name=blocks', false, 302, _SUCCSAVE);
    }
}

function change(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num', 0);
    $warn = !checkSiteToken();
    if (!$warn && $id) {
        $active = ($act) ? 0 : 1;
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    }
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : _SUCCSTATUS, $warn);
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $warn = !checkSiteToken();
    if (!$warn && $id) {
        [$bpos, $weight] = $db->getSqlRow($db->getSqlQuery('SELECT bpos, weight FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id]));
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight > :weight AND bpos = :bpos', ['weight' => $weight, 'bpos' => $bpos]);
        while ([$nbid] = $db->getSqlRow($result)) {
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $nbid]);
            $weight++;
        }
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id]);
    }
    setRedirect($afile.'.php?name=blocks', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => getBlockTabsOps(),
        'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO],
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
