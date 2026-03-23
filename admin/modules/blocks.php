<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function getBlockModules(): array {
    global $conf;
    static $mods = null;
    if ($mods === null) {
        $mods = [];
        foreach ($conf['modules'] as $name => $info) {
            if ((int)($info['type'] ?? 1) !== 1) continue;
            $mods[] = $name;
        }
        sort($mods);
    }
    return $mods;
}

function blocks(): void {
    global $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO]]);
    echo $cont.$tpl->getHtmlFrag('open', []).'<div id="repajax_block">'.ajax_block().'</div>'.$tpl->getHtmlFrag('close', []);
    setFoot();
}

function add(): void {
    global $db, $conf, $afile, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form action="'.$afile.'.php" method="post">'
    .'<table class="sl_table_form">'
    .'<tr><td>'._TITLE.':<div class="sl_small">'._ADDCONST.'</div></td><td><input type="text" name="title" maxlength="60" class="sl_form" placeholder="'._TITLE.'" required></td></tr>'
    .'<tr><td>'._RSSFILE.':</td><td><input type="text" name="url" class="sl_form" placeholder="'._RSSFILE.'"></td></tr>'
    .'<tr><td><div class="sl_small">'._RSSLINESINFO.' '._RSSINFO.'</div></td><td><select name="headline" class="sl_form"><option value="0" selected>'._CUSTOM.'</option>'.rss_select().'</select></td></tr>'
    .'<tr><td>'._REFRESHTIME.':<div class="sl_small">'._REFINFO.'</div></td><td><select name="refresh" class="sl_form">'
    .'<option value="1800">30 '._MIN.'.</option>'
    .'<option value="3600" selected>1 '._HOUR.'</option>'
    .'<option value="18000">5 '._HOUR.'.</option>'
    .'<option value="36000">10 '._HOUR.'.</option>'
    .'<option value="86400">24 '._HOUR.'.</option></select></td></tr>'
    .'<tr><td>'._FILENAME.':<div class="sl_small">'._FILENAMEIN.'</div></td><td>'
    .'<select name="bfile" class="sl_form">'
    .'<option value="" selected>'._NONE.'</option>';
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) $cont .= '<option value="'.$file.'">'.$matches[0].'</option>'."\n";
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._CONTENT.':</td><td>'.textarea('1', 'content', '', 'all', '15', _CONTENT, '').'</td></tr>'
    .'<tr><td>'._POSITION.':</td><td><select name="bpos" class="sl_form">'
    .'<option value="l">'._LEFT.'</option>'
    .'<option value="c">'._CENTERUP.'</option>'
    .'<option value="d">'._CENTERDOWN.'</option>'
    .'<option value="r">'._RIGHT.'</option>'
    .'<option value="b">'._BANNERUP.'</option>'
    .'<option value="f">'._BANNERDOWN.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._BLOCK_VIEW.':</td><td><table>';
    $a = 2;
    $i = 1;
    $modules = getBlockModules();
    foreach ($modules as $title) {
        $tdwidth = intval(100/$a);
        if (($i - 1) % $a == 0) $cont .= '<tr>';
        $cont .= '<td style="width: '.$tdwidth.'%;"><input type="checkbox" name="blockwhere[]" value="'.$title.'"> <span title="'._MODUL.': '.$title.'" class="sl_note">'.getModuleName($title).'</span></td>';
        if ($i % $a == 0) $cont .= '</tr>';
        $i++;
    }
    $cont .= '<tr><td><input type="checkbox" name="blockwhere[]" value="ihome"> <b>'._HOME.'</b></td><td><input type="checkbox" name="blockwhere[]" value="home"> <b>'._INHOME.'</b></td></tr>'
    .'<tr><td><input type="checkbox" name="blockwhere[]" value="all"> <b>'._BLOCK_ALL.'</b></td><td><input type="checkbox" name="blockwhere[]" value="otricanie"> <b>'._DENYING.'</b></td></tr>'
    .'<tr><td><input type="checkbox" name="blockwhere[]" value="infly"> <b>'._INFLY.'</b></td><td><input type="checkbox" name="blockwhere[]" value="flyfix"> <b>'._FLY_FIX.'</b></td></tr></table>'
    .'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language().'</select></td></tr>';
    $cont .= '<tr><td>'._ACTIVATE2.'</td><td>'.radio_form(1, 'status').'</td></tr>'
    .'<tr><td>'._EXPIRATION.':<div class="sl_small">'._CONFINES.'</div></td><td><input type="number" name="expire" value="0" class="sl_form" placeholder="'._EXPIRATION.'" required></td></tr>'
    .'<tr><td>'._AFTEREXPIRATION.':</td><td><select name="action" class="sl_form">'
    .'<option value="d">'._DEACTIVATE.'</option>'
    .'<option value="r">'._DELETE.'</option></select></td></tr>'
    .'<tr><td>'._VIEWPRIV.'</td><td><select name="view" class="sl_form">';
    $privs = [_MVALL, _MVUSERS, _MVADMIN, _MVANON];
    foreach ($privs as $key => $value) $cont .= '<option value="'.$key.'">'.$value.'</option>';
    $cont .= '</select></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="blocks"><input type="hidden" name="op" value="addsave"><input type="submit" value="'._CREATEBLOCK.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
}

function fileadd(): void {
    global $afile, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 2]);
    $cont .= checkPerms(BASE_DIR.'/blocks/');
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._FILENAME.':</td><td><input type="text" name="bf" maxlength="200" class="sl_form" placeholder="'._FILENAME.'" required></td></tr>'
    .'<tr><td>'._TYPE.':</td><td><input type="radio" name="flag" value="php" checked> PHP <input type="radio" name="flag" value="html"> HTML</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="blocks"><input type="hidden" name="op" value="filecode"><input type="submit" value="'._CREATEBLOCK.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
    setFoot();
    global $tpl;
}

function fileedit(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._FILENAME.':</td><td><select name="bf" class="sl_form">';
    $files = scandir('blocks');
    foreach ($files as $file) {
        if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
            if ($db->getSqlRowCount($db->getSqlQuery('SELECT * FROM '.PREFIX_DB.'_blocks WHERE bfile = :file', ['file' => $file])) == 0) $cont .= '<option value="'.$file.'">'.$matches[0].'</option>'."\n";
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="blocks"><input type="hidden" name="op" value="filecode"><input type="submit" value="'._EDITBLOCK.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
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
    $blockwhere = getVar('post', 'blockwhere', 'var', []);
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
        $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
        echo $cont.$tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _RSSFAIL]).$tpl->getHtmlFrag('open', []).'<table><tr><td class="sl_center">'._GOBACK.'</td></tr></table>'.$tpl->getHtmlFrag('close', []);
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
        $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 3]);
        $cont .= checkPerms(BASE_DIR.'/blocks/');
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _BLOCK.': '.$bf]);
        if (file_exists('blocks/'.$bf)) {
            $cont .= checkPerms(BASE_DIR.'/blocks/'.$bf);
            $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _B_FEDIT]);
        }
        $cont .= $tpl->getHtmlFrag('alert', ['type' => 'warn', 'text' => _EINFOPHP]);
        $cont .= $tpl->getHtmlFrag('open', []);
        $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_edit">'
        .'<tr><td>'.textarea_code('code', 'blocktext', 'sl_form', 'text/x-php', trim($out[1])).'</td></tr>'
        .'<tr><td class="sl_center"><input type="hidden" name="bf" value="'.$bf.'">'
        .'<input type="hidden" name="flag" value="'.$flaged.'">'
        .'<input type="hidden" name="name" value="blocks">'
        .'<input type="hidden" name="op" value="filecodesave">'
        .'<input type="submit" value="'._SAVE.'" class="sl_but_blue"> '._GOBACK.'</td></tr></table></form>';
        $cont .= $tpl->getHtmlFrag('close', []);
        echo $cont;
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
    global $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 1]);
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
    $cont .= $tpl->getHtmlFrag('open', []);
    $cont .= '<form action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._TITLE.':<div class="sl_small">'._ADDCONST.'</div></td><td><input type="text" name="title" maxlength="50" value="'.$title.'" class="sl_form" placeholder="'._TITLE.'" required></td></tr>';
    if ($bfile != '') {
        $cont .= '<tr><td>'._FILENAME.':</td><td><select name="bfile" class="sl_form">';
        $files = scandir('blocks');
        foreach ($files as $file) {
            if (preg_match('/^block\-(.+)\.php/', $file, $matches)) {
                $selected = ($bfile == $file) ? ' selected' : '';
                $cont .= '<option value="'.$file.'"'.$selected.'>'.$matches[0].'</option>';
            }
        }
        $cont .= '</select></td></tr>';
    } else {
        if ($url != '') {
            $cont .= '<tr><td>'._RSSFILE.':</td><td><input type="text" name="url" maxlength="200" value="'.$url.'" class="sl_form" placeholder="'._RSSFILE.'"></td></tr>'
            .'<tr><td>'._REFRESHTIME.':</td><td><select name="refresh" class="sl_form">'
            .'<option value="1800"';
            if ($refresh == '1800') $cont .= ' selected';
            $cont .= '>30 '._MIN.'.</option>'
            .'<option value="3600"';
            if ($refresh == '3600') $cont .= ' selected';
            $cont .= '>1 '._HOUR.'</option>'
            .'<option value="18000"';
            if ($refresh == '18000') $cont .= ' selected';
            $cont .= '>5 '._HOUR.'.</option>'
            .'<option value="36000"';
            if ($refresh == '36000') $cont .= ' selected';
            $cont .= '>10 '._HOUR.'.</option>'
            .'<option value="86400"';
            if ($refresh == '86400') $cont .= ' selected';
            $cont .= '>24 '._HOUR.'.</option>'
            .'</select></td></tr>';
        } else {
            $cont .= '<tr><td>'._CONTENT.':</td><td>'.textarea('1', 'content', $content, 'all', '15', _CONTENT, '').'</td></tr>';
        }
    }
    $sel1 = ($bpos == 'l') ? ' selected' : '';
    $sel2 = ($bpos == 'c') ? ' selected' : '';
    $sel3 = ($bpos == 'r') ? ' selected' : '';
    $sel4 = ($bpos == 'd') ? ' selected' : '';
    $sel5 = ($bpos == 'b') ? ' selected' : '';
    $sel6 = ($bpos == 'f') ? ' selected' : '';
    $cont .= '<tr><td>'._POSITION.':</td><td><select name="bpos" class="sl_form">'
    .'<option value="l"'.$sel1.'>'._LEFT.'</option>'
    .'<option value="c"'.$sel2.'>'._CENTERUP.'</option>'
    .'<option value="d"'.$sel4.'>'._CENTERDOWN.'</option>'
    .'<option value="r"'.$sel3.'>'._RIGHT.'</option>'
    .'<option value="b"'.$sel5.'>'._BANNERUP.'</option>'
    .'<option value="f"'.$sel6.'>'._BANNERDOWN.'</option>'
    .'</select></td></tr>';
    $cont .= '<tr><td>'._BLOCK_VIEW.':</td><td><table>';
    $where_mas = explode(',', $which ?? '');
    $a = 2;
    $i = 1;
    $modules = getBlockModules();
    foreach ($modules as $title) {
        $mel = '';
        foreach ($where_mas as $val) if ($val == $title) $mel = ' checked';
        $tdwidth = intval(100/$a);
        if (($i - 1) % $a == 0) $cont .= '<tr>';
        $cont .= '<td style="width: '.$tdwidth.'%;"><input type="checkbox" name="blockwhere[]" value="'.$title.'"'.$mel.'> <span title="'._MODUL.': '.$title.'" class="sl_note">'.getModuleName($title).'</span></td>';
        if ($i % $a == 0) $cont .= '</tr>';
        $i++;
    }
    $iel = (in_array('ihome', $where_mas)) ? ' checked' : '';
    $hel = (in_array('home', $where_mas)) ? ' checked' : '';
    $cel = (in_array('all', $where_mas) && empty($hel)) ? ' checked' : '';
    $fel = (in_array('infly', $where_mas)) ? ' checked' : '';
    $oel = (in_array('otricanie', $where_mas)) ? ' checked' : '';
    $xel = (in_array('flyfix', $where_mas)) ? ' checked' : '';
    $cont .= '<tr><td><input type="checkbox" name="blockwhere[]" value="ihome"'.$iel.'> <b>'._HOME.'</b></td><td><input type="checkbox" name="blockwhere[]" value="home"'.$hel.'> <b>'._INHOME.'</b></td></tr>'
    .'<tr><td><input type="checkbox" name="blockwhere[]" value="all"'.$cel.'> <b>'._BLOCK_ALL.'</b></td><td><input type="checkbox" name="blockwhere[]" value="otricanie"'.$oel.'> <b>'._DENYING.'</b></td></tr>'
    .'<tr><td><input type="checkbox" name="blockwhere[]" value="infly"'.$fel.'> <b>'._INFLY.'</b></td><td><input type="checkbox" name="blockwhere[]" value="flyfix"'.$xel.'> <b>'._FLY_FIX.'</b></td></tr></table>'
    .'</td></tr>';
    if ($conf['multilingual'] == 1) $cont .= '<tr><td>'._LANGUAGE.':</td><td><select name="lang" class="sl_form">'.language($lang).'</select></td></tr>';
    if ($expire != 0) {
        $newexpire = 0;
        $oldexpire = $expire;
        $expire = intval($expire - time());
        $exp_day = $expire / 86400;
        $expire_text = '<input type="hidden" name="expire" value="'.$oldexpire.'">'._PURCHASED.': '.getDuration($expire).' ('.round($exp_day, 3).' '._DAYS.')';
    } else {
        $newexpire = 1;
        $expire_text = '<input type="number" name="expire" value="0" class="sl_form" placeholder="'._EXPIRATION.'" required>';
    }
    $selact1 = ($action == 'd') ? ' selected' : '';
    $selact2 = ($action == 'r') ? ' selected' : '';
    $cont .= '<tr><td>'._ACTIVATE2.'</td><td>'.radio_form($active, 'status').'</td></tr>'
    .'<tr><td>'._EXPIRATION.':<div class="sl_small">'._CONFINES.'</div></td><td>'.$expire_text.'</td></tr>'
    .'<tr><td>'._AFTEREXPIRATION.':</td><td><select name="action" class="sl_form">'
    .'<option value="d"'.$selact1.'>'._DEACTIVATE.'</option>'
    .'<option value="r"'.$selact2.'>'._DELETE.'</option></select></td></tr>'
    .'<tr><td>'._VIEWPRIV.'</td><td><select name="view" class="sl_form">';
    $privs = [_MVALL, _MVUSERS, _MVADMIN, _MVANON];
    foreach ($privs as $key => $value) {
        $sel = ($view == $key) ? ' selected' : '';
        $cont .= '<option value="'.$key.'"'.$sel.'>'.$value.'</option>';
    }
    $cont .= '</select></td></tr>'
    .'<tr><td colspan="2" class="sl_center">'
    .'<input type="hidden" name="oldposition" value="'.$bpos.'">'
    .'<input type="hidden" name="bid" value="'.$bid.'">'
    .'<input type="hidden" name="newexpire" value="'.$newexpire.'">'
    .'<input type="hidden" name="bkey" value="'.$bkey.'">'
    .'<input type="hidden" name="weight" value="'.$weight.'">'
    .'<input type="hidden" name="name" value="blocks">'
    .'<input type="hidden" name="op" value="editsave">'
    .'<input type="submit" value="'._SAVE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= $tpl->getHtmlFrag('close', []);
    echo $cont;
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
    $blockwhere = getVar('post', 'blockwhere', 'var', []);
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
    setHead();
    $cont = setAdminNavi(['ops' => ['name=blocks', 'name=blocks&amp;op=add', 'name=blocks&amp;op=fileadd', 'name=blocks&amp;op=fileedit', 'name=blocks&amp;op=fix', 'name=blocks&amp;op=info'], 'tabs' => [_HOME, _ADDNEWBLOCK, _ADDNEWFILEBLOCK, _EDITBLOCK, _FIX, _INFO], 'tab' => 5]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
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
