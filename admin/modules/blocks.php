<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function blocks(): void {
    global $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO]]);
    echo $cont.getTplAdminPlaceholder('repajax_block', getAdminBlockList());
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
    $rows = getTplAdminFormRow(getTplAdminHintLabel(_TITLE, _ADDCONST), getTplTextInput('title', '', 'sl_form', 'maxlength="60" placeholder="'._TITLE.'" required'));
    $rows .= getTplAdminFormRow(_RSSFILE.':', getTplTextInput('url', '', 'sl_form', 'placeholder="'._RSSFILE.'"'));
    $rows .= getTplAdminFormRow(getTplAdminSmallNote(_RSSLINESINFO.' '._RSSINFO), getTplSelect('headline', getTplOption('0', _CUSTOM, true).rss_select(), 'sl_form'));
    $rows .= getTplAdminFormRow(getTplAdminHintLabel(_REFRESHTIME, _REFINFO), getTplBlockRefresh());
    $bfopts = getTplOption('', _NONE, true);
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) {
                $bfopts .= getTplOption($file, $matches[0]);
            }
        }
    }
    $rows .= $tpl->getHtmlFrag('admin-blocks-add-rows', [
        'action_html' => getTplBlockAction(),
        'afterexpiration_label' => _AFTEREXPIRATION.':',
        'activate_html' => radio_form(1, 'status'),
        'activate_label' => _ACTIVATE2,
        'bfile_html' => getTplSelect('bfile', $bfopts, 'sl_form'),
        'bfile_label_html' => getTplAdminHintLabel(_FILENAME, _FILENAMEIN),
        'blockview_html' => getTplAdminBlockGrid(),
        'blockview_label' => _BLOCK_VIEW.':',
        'content_html' => textarea('1', 'content', '', 'all', '15', _CONTENT, ''),
        'content_label' => _CONTENT.':',
        'expiration_label_html' => getTplAdminHintLabel(_EXPIRATION, _CONFINES),
        'expiration_placeholder' => _EXPIRATION,
        'language_html' => $conf['multilingual'] == 1 ? getTplAdminFormRow(_LANGUAGE.':', getTplSelect('lang', language(), 'sl_form')) : '',
        'position_html' => getTplBlockPosition(),
        'position_label' => _POSITION.':',
        'submit_label' => _CREATEBLOCK,
        'viewpriv_html' => getTplBlockView(),
        'viewpriv_label' => _VIEWPRIV,
    ]);
    $hide = getTplHiddenInput('name', 'blocks').getTplHiddenInput('op', 'addsave');
    echo $cont.getTplAdminForm($afile.'.php', $rows, $hide);
    setFoot();
}

function fileadd(): void {
    global $afile, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 2]);
    $cont .= checkPerms(BASE_DIR.'/blocks/');
    $hide = getTplHiddenInput('name', 'blocks').getTplHiddenInput('op', 'filecode');
    $rows = $tpl->getHtmlFrag('admin-blocks-fileadd-rows', [
        'createblock_label' => _CREATEBLOCK,
        'filename_label' => _FILENAME.':',
        'filename_placeholder' => _FILENAME,
        'type_label' => _TYPE.':',
    ]);
    echo $cont.getTplBox(getTplAdminForm($afile.'.php', $rows, $hide));
    setFoot();
}

function fileedit(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
    $opts = '';
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) $opts .= getTplOption($file, $matches[0]);
        }
    }
    $hide = getTplHiddenInput('name', 'blocks').getTplHiddenInput('op', 'filecode');
    $rows = $tpl->getHtmlFrag('admin-blocks-fileedit-rows', [
        'bf_html' => getTplSelect('bf', $opts, 'sl_form'),
        'editblock_label' => _EDITBLOCK,
        'filename_label' => _FILENAME.':',
    ]);
    echo $cont.getTplBox(getTplAdminForm($afile.'.php', $rows, $hide));
    setFoot();
}

function fix(): void {
    global $db, $afile;
    $pos = ['b', 'c', 'd', 'f', 'l', 'r'];
    foreach ($pos as $val) {
        $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE bpos = :val ORDER BY weight ASC', ['val' => $val]);
        $weight = 0;
        while ([$bid] = $db->getSqlRow($result)) {
            $weight++;
            $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $bid]);
        }
    }
    setRedirect($afile.'.php?name=blocks');
}

function addsave(): void {
    global $db, $afile, $tpl;
    $title = getVar('post', 'title', 'title', '');
    $content = getVar('post', 'content', 'text', '');
    $url = getVar('post', 'url', 'url', '');
    $bpos = getVar('post', 'bpos', 'var', '');
    $active = getVar('post', 'status', 'num', 0);
    $refresh = getVar('post', 'refresh', 'num', 0);
    $headline = getVar('post', 'headline', 'url', '');
    $lang = getVar('post', 'lang', 'var', '');
    $bfile = getVar('post', 'bfile', 'var', '');
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
    if (($content == '') && ($bfile == '')) {
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _RSSFAIL]).getTplBox($tpl->getHtmlFrag('admin-blocks-back-box', [
            'goback_label' => _GOBACK,
        ]));
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
        setRedirect($afile.'.php?name=blocks');
    }
}

function filecode(): void {
    global $db, $afile, $tpl;
    $bf = getVar('post', 'bf', 'var', '');
    if ($bf != '') {
        $flag = getVar('post', 'flag', 'var', '');
        if ($flag) {
            $flaged = $flag;
            $bf = str_replace(['block-', '.php'], '', $bf);
            $bf = 'block-'.$bf.'.php';
        } else {
            $bfstr = file_get_contents('blocks/'.$bf);
            if (strpos($bfstr, 'BLOCKHTML') === false) {
                $flaged = 'php';
                preg_match('/<\?php.*if.*\(\!defined\(\"BLOCK_FILE\"\)\).*exit;.*?}(.*)\?>/is', $bfstr, $out);
                unset($out[0]);
            } else {
                $flaged = 'html';
                preg_match('/<<<BLOCKHTML(.*)BLOCKHTML;/is', $bfstr, $out);
                unset($out[0]);
            }
        }
        setHead();
        $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
        $cont .= checkPerms(BASE_DIR.'/blocks/');
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _BLOCK.': '.$bf]);
        if (file_exists('blocks/'.$bf)) {
            $cont .= checkPerms(BASE_DIR.'/blocks/'.$bf);
            $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _B_FEDIT]);
        }
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _EINFOPHP]);
        $hide = getTplHiddenInput('bf', $bf)
        .getTplHiddenInput('flag', $flaged)
        .getTplHiddenInput('name', 'blocks')
        .getTplHiddenInput('op', 'filecodesave');
        $rows = $tpl->getHtmlFrag('admin-blocks-filecode-rows', [
            'code_html' => textarea_code('code', 'blocktext', 'sl_form', 'text/x-php', trim($out[1])),
            'goback_label' => _GOBACK,
            'save_label' => _SAVE,
        ]);
        echo $cont.getTplBox(getTplAdminForm($afile.'.php', $rows, $hide, 'sl_table_edit'));
        setFoot();
    } else {
        setRedirect($afile.'.php?name=blocks&op=logview');
    }
}

function filecodesave(): void {
    global $afile;
    $blocktext = filter_input(INPUT_POST, 'blocktext', FILTER_UNSAFE_RAW);
    $bf = getVar('post', 'bf', 'var', '');
    if ($blocktext && $bf) {
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
            setRedirect($afile.'.php?name=blocks');
        }
    }
}

function edit(): void {
    global $afile, $conf, $db, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
    $bid = getVar('get', 'id', 'num');
    [$bkey, $title, $content, $url, $bpos, $weight, $active, $refresh, $lang, $bfile, $view, $expire, $action, $which] = $db->getSqlRow($db->getSqlQuery('SELECT bkey, title, content, url, bpos, weight, status, refresh, lang, bfile, view, expire, action, which FROM '.PREFIX_DB.'_blocks WHERE id = :bid', ['bid' => $bid]));
    if ($url != '') {
        $type = '('._BLOCKRSS.')';
    } elseif ($bfile != '') {
        $type = '('._BLOCKFILE.')';
    } else {
        $type = '('._BLOCKHTML.')';
    }
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _BLOCK.': '.$title.' '.$type]);
    $rows = getTplAdminFormRow(getTplAdminHintLabel(_TITLE, _ADDCONST), getTplTextInput('title', $title, 'sl_form', 'maxlength="50" placeholder="'._TITLE.'" required'));
    if ($bfile != '') {
        $bfopts = '';
        $files = scandir('blocks');
        foreach ($files as $file) {
            if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
                $bfopts .= getTplOption($file, $matches[0], $bfile == $file);
            }
        }
        $rows .= getTplAdminFormRow(_FILENAME.':', getTplSelect('bfile', $bfopts, 'sl_form'));
    } elseif ($url != '') {
        $rows .= getTplAdminFormRow(_RSSFILE.':', getTplTextInput('url', $url, 'sl_form', 'maxlength="200" placeholder="'._RSSFILE.'"'));
        $rows .= getTplAdminFormRow(_REFRESHTIME.':', getTplBlockRefresh((string)$refresh));
    } else {
        $rows .= getTplAdminFormRow(_CONTENT.':', textarea('1', 'content', $content, 'all', '15', _CONTENT, ''));
    }
    $rows .= getTplAdminFormRow(_POSITION.':', getTplBlockPosition((string)$bpos));
    $rows .= getTplAdminFormRow(_BLOCK_VIEW.':', getTplAdminBlockGrid(explode(',', $which ?? '')));
    if ($conf['multilingual'] == 1) $rows .= getTplAdminFormRow(_LANGUAGE.':', getTplSelect('lang', language($lang), 'sl_form'));
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = getTplHiddenInput('expire', (string)$oldexpire)._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = getTplNumberInput(0, 'expire', 'sl_form', 'placeholder="'._EXPIRATION.'" required');
    }
    $rows .= $tpl->getHtmlFrag('admin-blocks-edit-rows', [
        'action_html' => getTplBlockAction((string)$action),
        'afterexpiration_label' => _AFTEREXPIRATION.':',
        'activate_html' => radio_form($active, 'status'),
        'activate_label' => _ACTIVATE2,
        'expiration_html' => $expire_text,
        'expiration_label_html' => getTplAdminHintLabel(_EXPIRATION, _CONFINES),
        'viewpriv_html' => getTplBlockView((int)$view),
        'viewpriv_label' => _VIEWPRIV,
    ]);
    $hide = getTplHiddenInput('oldposition', $bpos)
        .getTplHiddenInput('bid', (string)$bid)
        .getTplHiddenInput('newexpire', (string)$newexpire)
        .getTplHiddenInput('bkey', $bkey)
        .getTplHiddenInput('weight', (string)$weight)
        .getTplHiddenInput('name', 'blocks')
        .getTplHiddenInput('op', 'editsave');
    $rows .= getTplAdminFormWide(getTplAdminSubmitButton(_SAVE), '', 'sl_center');
    echo $cont.getTplAdminForm($afile.'.php', $rows, $hide);
    setFoot();
}

function editsave(): void {
    global $db, $afile;
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
    $bfile = getVar('post', 'bfile', 'var', '');
    $view = getVar('post', 'view', 'num', 0);
    $expire = getVar('post', 'expire', 'num', 0);
    $action = getVar('post', 'action', 'var', '');
    $blockwhere = getVar('post', 'blockwhere[]', 'var', []);
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
        setRedirect($afile.'.php?name=blocks');
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
        setRedirect($afile.'.php?name=blocks');
    }
}

function change(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    $act = getVar('get', 'act', 'num', 0);
    $active = ($act) ? 0 : 1;
    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET status = :active WHERE id = :id', ['active' => $active, 'id' => $id]);
    setRedirect($afile.'.php?name=blocks');
}

function delete(): void {
    global $db, $afile;
    $id = getVar('get', 'id', 'num');
    [$bpos, $weight] = $db->getSqlRow($db->getSqlQuery('SELECT bpos, weight FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id]));
    $result = $db->getSqlQuery('SELECT id FROM '.PREFIX_DB.'_blocks WHERE weight > :weight AND bpos = :bpos', ['weight' => $weight, 'bpos' => $bpos]);
    while ([$nbid] = $db->getSqlRow($result)) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_blocks SET weight = :weight WHERE id = :bid', ['weight' => $weight, 'bid' => $nbid]);
        $weight++;
    }
    $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_blocks WHERE id = :id', ['id' => $id]);
    setRedirect($afile.'.php?name=blocks');
}

function info(): void {
    $cont = getTplAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 5]);
    setAdminInfoPage($cont);
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
