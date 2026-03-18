<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

function getUploadsSearch(): string {
    global $afile, $conf;
    $dir = getVar('post', 'dir', 'var', $conf['uploads']['dir']);
    $search = '<form method="post" action="'.$afile.'.php">'._DIR.': <select name="dir" OnChange="submit()">';
    $handle = opendir('uploads');
    if ($handle !== false) {
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/\./', $file)) {
                $sel = ($dir == $file) ? ' selected' : '';
                $search .= '<option value="'.$file.'"'.$sel.'>uploads/'.$file.'</option>';
            }
        }
        closedir($handle);
    }
    $search .= '</select><input type="hidden" name="name" value="uploads"><input type="hidden" name="op" value="uploads"></form>';
    return setTemplateBasic('searchbox', ['{%searchbox%}' => $search]);
}

function uploads(): void {
    global $afile, $conf, $stop;
    $dir = getVar('post', 'dir', 'var', '');
    if ($dir === '') $dir = getVar('get', 'dir', 'var', $conf['uploads']['dir']);
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=templconf', 'name=uploads&amp;op=conf', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [_EUPLOAD, '<span OnClick="AjaxLoad(\'GET\', \'1\', \'f1\', \'go=5&amp;op=ashow_files&amp;id=1&amp;dir='.$dir.'\', \'\'); return false;">'._DGEN.'</span>', '<span OnClick="AjaxLoad(\'GET\', \'1\', \'f2\', \'go=5&amp;op=ashow_files&amp;id=2&amp;dir='.$dir.'\', \'\'); return false;">'._DTHUMB.'</span>'], 'subtab' => 1, 'sub' => getUploadsSearch(), 'id' => 'uploads']);
    if ($stop) $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'warn', 'text' => $stop]);
    $cont .= checkPerms(BASE_DIR.'/uploads/');
    $cont .= '<div id="tabcs0" class="tabcont">';
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': uploads/'.$dir]);
    $cont .= setTemplateBasic('open');
    $cont .= '<form enctype="multipart/form-data" action="'.$afile.'.php" method="post"><table class="sl_table_form">'
    .'<tr><td>'._FILE_USER.':</td><td><input type="file" name="userfile" class="sl_form"></td></tr>'
    .'<tr><td>'._FILE_SITE.':</td><td><input type="text" name="sitefile" class="sl_form" placeholder="'._FILE_SITE.'"></td></tr>'
    .'<tr><td colspan="2" class="sl_center"><input type="hidden" name="name" value="uploads"><input type="hidden" name="op" value="uploadsave"><input type="hidden" name="dir" value="'.$dir.'"><input type="submit" value="'._EXECUTE.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    $cont .= '</div>'
    .'<div id="tabcs1" class="tabcont">';
    $fdir = 'uploads/'.$dir;
    $cont .= checkPerms(BASE_DIR.'/'.$fdir);
    if (is_dir($fdir)) {
        $f = 0;
        $affilesize = 0;
        $handle = opendir($fdir);
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($fdir.'/'.$file)) {
                    $filesize = filesize($fdir.'/'.$file);
                    $f++;
                    $affilesize += $filesize;
                }
            }
            closedir($handle);
        }
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': '.$fdir.'<br>'._FILE_M.': '.$f.'<br>'._FILE_S.': '.filterSize($affilesize)]);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    $cont .= setTemplateBasic('open').'<div id="repf1"></div>'.setTemplateBasic('close');
    $cont .= '</div>'
    .'<div id="tabcs2" class="tabcont">';
    $tdir = 'uploads/'.$dir.'/thumb';
    $cont .= checkPerms(BASE_DIR.'/'.$tdir);
    if (is_dir($tdir)) {
        $t = 0;
        $atfilesize = 0;
        $handle = opendir($tdir);
        if ($handle !== false) {
            while (($file = readdir($handle)) !== false) {
                if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($tdir.'/'.$file)) {
                    $filesize = filesize($tdir.'/'.$file);
                    $t++;
                    $atfilesize += $filesize;
                }
            }
            closedir($handle);
        }
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _MODUL.': '.getModuleName($dir).'<br>'._DIR.': '.$tdir.'<br>'._FILE_M.': '.$t.'<br>'._FILE_S.': '.filterSize($atfilesize)]);
    } else {
        $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _NO_INFO]);
    }
    $cont .= setTemplateBasic('open').'<div id="repf2"></div>'.setTemplateBasic('close');
    $cont .= '</div>'
    .'<script>
        var countries=new ddtabcontent("uploadss")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>';
    echo $cont;
    setFoot();
}

function uploadsave(): void {
    global $afile, $stop;
    $dir = getVar('post', 'dir', 'var');
    upload(3, 'uploads/'.$dir, 'gif,jpg,jpeg,png,zip,rar', '104857600', $dir, '1600', '1600', '1');
    if ($stop) {
        uploads();
    } else {
        setRedirect($afile.'.php?name=uploads&dir='.$dir);
    }
}

function templconf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=templconf', 'name=uploads&amp;op=conf', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [_EUPLOAD, _DGEN, _DTHUMB], 'tab' => 1, 'sub' => getUploadsSearch(), 'id' => 'templconf']);
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _TPINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/filetype.php');
    $typm = explode(',', $conf['uploads']['typ']);
    $conts = '';
    for ($i = 0; $i < count($typm); $i++) {
        $hr = ($i == 0) ? '' : '<hr>';
        $conts .= $hr.'<table class="sl_table_edit"><tr><td><h5>'._TPFOR.': '.$typm[$i].'</h5></td></tr><tr><td>'.textarea_code('code_'.$i.'', 'tmp[]', 'sl_form', 'text/html', $conf['filetype'][$typm[$i]] ?? '').'</td></tr></table>';
    }
    $cont .= setTemplateBasic('open').'<form action="'.$afile.'.php" method="post">'.$conts.'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="name" value="uploads"><input type="hidden" name="op" value="templsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>'.setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function templsave(): void {
    global $afile, $conf;
    $cont = [];
    $typm = explode(',', $conf['uploads']['typ']);
    $tmp = getVar('post', 'tmp', 'raw');
    for ($i = 0; $i < count($typm); $i++) $cont[$typm[$i]] = $tmp[$i];
    setConfigFile('filetype.php', $cont);
    setRedirect($afile.'.php?name=uploads&op=templconf');
}

function conf(): void {
    global $afile, $conf;
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=templconf', 'name=uploads&amp;op=conf', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', ''], 'stabs' => [_GENPREF, _MODULES], 'tab' => 2, 'subtab' => 1, 'sub' => getUploadsSearch(), 'id' => 'conf']);
    $cont .= checkPerms(CONFIG_DIR.'/uploads.php');
    $handle = opendir('uploads');
    $directory = '';
    if ($handle !== false) {
        while (($file = readdir($handle)) !== false) {
            if (!preg_match('/\./', $file)) {
                $sel = ($conf['uploads']['dir'] == $file) ? ' selected' : '';
                $directory .= '<option value="'.$file.'"'.$sel.'>uploads/'.$file.'</option>';
            }
        }
        closedir($handle);
    }
    $conts = '<form action="'.$afile.'.php" method="post">'
    .'<div id="tabcs0" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._DIRDEF.':</td><td><select name="dir" class="sl_conf">'.$directory.'</select></td></tr>'
    .'<tr><td>'._TPFORM.':<div class="sl_small">'._TPFORMIN.'</div></td><td><textarea name="ttyp" cols="65" rows="5" class="sl_conf" placeholder="'._TPFORM.'" required>'.$conf['uploads']['typ'].'</textarea></td></tr>'
    .'<tr><td>'._TPWIDTH.':</td><td><input type="number" name="twidth" value="'.$conf['uploads']['width'].'" class="sl_conf" placeholder="'._TPWIDTH.'" required></td></tr>'
    .'<tr><td>'._TPHEIGHT.':</td><td><input type="number" name="theight" value="'.$conf['uploads']['height'].'" class="sl_conf" placeholder="'._TPHEIGHT.'" required></td></tr></table>'
    .'</div>'
    .'<div id="tabcs1" class="tabcont">';
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $con = explode('|', $conf['uploads'][$val]);
            $hr = ($i == 0) ? '' : '<hr>';
            $conts .= $hr.'<table class="sl_table_conf">'
            .'<tr><td>'._MODUL.':</td><td>'.getModuleName($val).'</td></tr>'
            .'<tr><td>'._FTYPE.':</td><td><input type="text" name="type[]" value="'.$con[0].'" class="sl_conf" placeholder="'._FTYPE.'" required></td></tr>'
            .'<tr><td>'._FSIZEALL._FIN.':</td><td><input type="number" name="allsize[]" value="'.$con[1].'" class="sl_conf" placeholder="'._FSIZEALL._FIN.'" required></td></tr>'
            .'<tr><td>'._FSIZE._FIN.':</td><td><input type="number" name="size[]" value="'.$con[2].'" class="sl_conf" placeholder="'._FSIZE._FIN.'" required></td></tr>'
            .'<tr><td>'._AWIDTH._AIN.':</td><td><input type="number" name="width[]" value="'.$con[3].'" class="sl_conf" placeholder="'._AWIDTH._AIN.'" required></td></tr>'
            .'<tr><td>'._AHEIGHT._AIN.':</td><td><input type="number" name="height[]" value="'.$con[4].'" class="sl_conf" placeholder="'._AHEIGHT._AIN.'" required></td></tr>'
            .'<tr><td>'._FILEUP.':</td><td><input type="number" name="up[]" value="'.$con[5].'" class="sl_conf" placeholder="'._FILEUP.'" required></td></tr>'
            .'<tr><td>'._GDWIDTH.':</td><td><input type="number" name="gdwidth[]" value="'.$con[6].'" class="sl_conf" placeholder="'._GDWIDTH.'" required></td></tr>'
            .'<tr><td>'._F_5.':</td><td><input type="number" name="num[]" value="'.$con[7].'" class="sl_conf" placeholder="'._F_5.'" required></td></tr>'
            .'<tr><td>'._EDFILEA.':<div class="sl_small">'._CONFINES.'</div></td><td><input type="number" name="asum[]" value="'.$con[8].'" class="sl_conf" placeholder="'._EDFILEA.'" required></td></tr>'
            .'<tr><td>'._EDFILEU.':<div class="sl_small">'._CONFINES.'</div></td><td><input type="number" name="usum[]" value="'.$con[9].'" class="sl_conf" placeholder="'._EDFILEU.'" required></td></tr>'
            .'<tr><td>'._F_8.'</td><td>'.radio_form($con[10], $i.'upload').'</td></tr>'
            .'<tr><td>'._F_9.'</td><td>'.radio_form($con[11], $i.'upguest').'</td></tr></table>';
            $i++;
        }
    }
    $conts .= '</div>';
    $cont .= setTemplateBasic('open');
    $cont .= $conts
    .'<script>
        var countries=new ddtabcontent("confs")
        countries.setpersist(true)
        countries.setselectedClassTarget("link")
        countries.init()
    </script>'
    .'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="name" value="uploads"><input type="hidden" name="op" value="confsave"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    setFoot();
}

function confsave(): void {
    global $afile;
    $protect = ["\n" => '', "\t" => '', "\r" => '', ' ' => ''];
    $ttyp = getVar('post', 'ttyp', 'text');
    $xttyp = (!$ttyp) ? 'gif,jpg,jpeg,png,bmp' : strtolower(strtr($ttyp, $protect));
    $twidth = getVar('post', 'twidth', 'num', 500);
    $xtwidth = (!$twidth) ? 500 : $twidth;
    $theight = getVar('post', 'theight', 'num', 500);
    $xtheight = (!$theight) ? 500 : $theight;
    $dir = getVar('post', 'dir', 'var');
    $cont = [];
    $cont['dir'] = $dir;
    $cont['typ'] = $xttyp;
    $cont['width'] = $xtwidth;
    $cont['height'] = $xtheight;
    $mods = ['all', 'account', 'album', 'auto_links', 'content', 'faq', 'files', 'forum', 'help', 'info', 'links', 'media', 'news', 'pages', 'shop', 'voting'];
    $type = getVar('post', 'type', 'raw');
    $allsize = getVar('post', 'allsize', 'raw');
    $size = getVar('post', 'size', 'raw');
    $width = getVar('post', 'width', 'raw');
    $height = getVar('post', 'height', 'raw');
    $up = getVar('post', 'up', 'raw');
    $gdwidth = getVar('post', 'gdwidth', 'raw');
    $num = getVar('post', 'num', 'raw');
    $asum = getVar('post', 'asum', 'raw');
    $usum = getVar('post', 'usum', 'raw');
    $i = 0;
    foreach ($mods as $val) {
        if ($val != '') {
            $xtype = (!$type[$i]) ? 'gif,jpg,jpeg,png,zip,rar' : strtolower(strtr($type[$i], $protect));
            $xallsize = (!intval($allsize[$i])) ? 104857600 : $allsize[$i];
            $xsize = (!intval($size[$i])) ? 1048576 : $size[$i];
            $xwidth = (!intval($width[$i])) ? 500 : $width[$i];
            $xheight = (!intval($height[$i])) ? 500 : $height[$i];
            $xup = (!intval($up[$i])) ? 10 : $up[$i];
            $xgdwidth = (!intval($gdwidth[$i])) ? 150 : $gdwidth[$i];
            $xnum = (!intval($num[$i])) ? 10 : $num[$i];
            $xasum = (!intval($asum[$i])) ? 250 : $asum[$i];
            $xusum = (!intval($usum[$i])) ? 100 : $usum[$i];
            $upload = getVar('post', $i.'upload', 'num');
            $upguest = getVar('post', $i.'upguest', 'num');
            $cont[$val] = $xtype.'|'.$xallsize.'|'.$xsize.'|'.$xwidth.'|'.$xheight.'|'.$xup.'|'.$xgdwidth.'|'.$xnum.'|'.$xasum.'|'.$xusum.'|'.$upload.'|'.$upguest;
            $i++;
        }
    }
    setConfigFile('uploads.php', $cont);
    setRedirect($afile.'.php?name=uploads&op=conf');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=uploads', 'name=uploads&amp;op=templconf', 'name=uploads&amp;op=conf', 'name=uploads&amp;op=info'], 'tabs' => [_FILES, _TEMPLATES, _PREFERENCES, _INFO], 'sops' => ['', '', ''], 'stabs' => [_EUPLOAD, _DGEN, _DTHUMB], 'tab' => 3, 'sub' => getUploadsSearch()]);
    echo $cont.'<div id="repadm_info">'.getAdminInfo().'</div>';
    setFoot();
}

switch ($op) {
    default: uploads(); break;
    case 'uploadsave': uploadsave(); break;
    case 'templconf': templconf(); break;
    case 'templsave': templsave(); break;
    case 'conf': conf(); break;
    case 'confsave': confsave(); break;
    case 'info': info(); break;
}
