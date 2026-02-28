<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_god()) die('Illegal file access');
require_once CONFIG_DIR.'/global.php';

function navi(int $opt = 0, int $tab = 0, int $subtab = 0, int $legacy = 0, string $id = ''): string {
    $ops = ($opt == 1) ? ['name=config', 'name=config', 'name=config', 'name=config', 'name=config', 'name=config&amp;op=show', 'name=config', 'name=config', 'name=config&amp;op=info'] : ['', '', '', '', '', '', '', '', 'name=config&amp;op=info'];
    $lang = [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _SEARCH, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO];
    return getAdminTabs(_PREFERENCES, 'config.png', '', $ops, $lang, [], [], $tab, $subtab, $legacy, $id);
}

function config(): void {
    global $afile, $conf;
    head();
    $cont = navi(0, 0, 0, 0, 'config');
    $cont .= checkPerms(CONFIG_DIR.'/global.php');
    $cont .= setTemplateBasic('open');
    $cont .= '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabc0" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._VERSION.':</td><td><a href="//slaed.net" target="_blank" title="'._VERSION.'">SLAED CMS '.$conf['version'].'</a></td></tr>'
    .'<tr><td>'._SITENAME.':</td><td><input type="text" name="sitename" value="'.$conf['sitename'].'" maxlength="255" class="sl_conf" placeholder="'._SITENAME.'" required></td></tr>'
    .'<tr><td>'._SITEURL.':</td><td><input type="url" name="homeurl" value="'.$conf['homeurl'].'" maxlength="255" class="sl_conf" placeholder="'._SITEURL.'" required></td></tr>'
    .'<tr><td>'._LOGO.':</td><td><select name="site_logo" id="img_replace" class="sl_conf">';
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $entries = is_dir($path) ? scandir($path) : [];
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $sel = ($conf['site_logo'] == $entry) ? ' selected' : '';
                $cont .= '<option value="'.$path.$entry.'"'.$sel.'>'.$entry.'</option>';
            }
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._SITELOGO.':</td><td><img src="'.$path.$conf['site_logo'].'" id="picture" alt="'._SITELOGO.'"></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="slogan" cols="65" rows="5" class="sl_conf" placeholder="'._DESCRIPTION.'" required>'.$conf['slogan'].'</textarea></td></tr>'
    .'<tr><td>'._ADMININFO.':<div class="sl_small">'._ADMININFODES.'</div></td><td><textarea name="admininfo" cols="65" rows="5" class="sl_conf" placeholder="'._ADMININFO.'">'.$conf['admininfo'].'</textarea></td></tr>'
    .'<tr><td>'._STARTDATE.':</td><td>'.datetime(1, 'startdate', $conf['startdate'], 16, 'sl_conf').'</td></tr>'
    .'<tr><td>'._ADMINEMAIL.':</td><td><input type="email" name="adminmail" value="'.$conf['adminmail'].'" maxlength="255" class="sl_conf" placeholder="'._ADMINEMAIL.'" required></td></tr>'
    .'<tr><td>'._USER_COOKIE.':</td><td><input type="text" name="user_c" value="'.$conf['user_c'].'" maxlength="255" class="sl_conf" placeholder="'._USER_COOKIE.'" required></td></tr>'
    .'<tr><td>'._ADMIN_SESSION.':</td><td><input type="text" name="admin_c" value="'.$conf['admin_c'].'" maxlength="255" class="sl_conf" placeholder="'._ADMIN_SESSION.'" required></td></tr>'
    .'<tr><td>'._USER_COOKIE_T.':</td><td><input type="number" name="user_c_t" value="'.intval($conf['user_c_t'] / 86400).'" class="sl_conf" placeholder="'._USER_COOKIE_T.'" required></td></tr>'
    .'<tr><td>'._SESS_T.':</td><td><input type="number" name="sess_t" value="'.intval($conf['sess_t'] / 60).'" class="sl_conf" placeholder="'._SESS_T.'" required></td></tr>'
    .'<tr><td>'._IP_LINK.':</td><td><input type="url" name="ip_link" value="'.$conf['ip_link'].'" maxlength="255" class="sl_conf" placeholder="'._IP_LINK.'" required></td></tr>'
    .'<tr><td>'._THEME.':</td><td><select name="theme" class="sl_conf">';
    $templates = is_dir('templates') ? scandir('templates') : [];
    if (is_array($templates)) {
        foreach ($templates as $tfile) {
            if (!preg_match('/\./', $tfile) && $tfile != 'admin') {
                $selected = ($tfile == $conf['theme']) ? 'selected' : '';
                $cont .= '<option value="'.$tfile.'" '.$selected.'>'.$tfile.'</option>';
            }
        }
    }
    $cont .= '</select></td></tr><tr><td>'._PUTINHOME.':<div class="sl_small">'._PUTINHOMEINFO.' '._CTRLINFO.'</div></td><td>'.modul('module', 'sl_conf', $conf['module'], 1).'</td></tr>';
    $mods = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'page', 'shop_clients', 'voting'];
    $mname = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'pages', 'shop', 'voting'];
    $i = 0;
    $ocont = '';
    foreach ($mods as $val) {
        if ($val != '') {
            if (file_exists('modules/'.$mname[$i].'/admin/index.php')) {
                $selected = ($conf['amod'] == $val) ? 'selected' : '';
                $ocont .= '<option value="'.$val.'" '.$selected.'>'.deflmconst($mname[$i]).'</option>';
            }
            $i++;
        }
    }
    $cont .= '<tr><td>'._PUTINAHOME.':</td><td><select name="amod" class="sl_conf">'.$ocont.'</select></td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._CAPTCHA.':</td><td><select name="gfx_chk" class="sl_conf">'
    .'<option value="0"';
    if ($conf['gfx_chk'] == '0') $cont .= ' selected';
    $cont .= '>'._CAPSEC0.'</option>'
    .'<option value="1"';
    if ($conf['gfx_chk'] == '1') $cont .= ' selected';
    $cont .= '>'._CAPSEC1.'</option>'
    .'<option value="2"';
    if ($conf['gfx_chk'] == '2') $cont .= ' selected';
    $cont .= '>'._CAPSEC2.'</option>'
    .'<option value="3"';
    if ($conf['gfx_chk'] == '3') $cont .= ' selected';
    $cont .= '>'._CAPSEC3.'</option>'
    .'<option value="4"';
    if ($conf['gfx_chk'] == '4') $cont .= ' selected';
    $cont .= '>'._CAPSEC4.'</option>'
    .'<option value="5"';
    if ($conf['gfx_chk'] == '5') $cont .= ' selected';
    $cont .= '>'._CAPSEC5.'</option>'
    .'<option value="6"';
    if ($conf['gfx_chk'] == '6') $cont .= ' selected';
    $cont .= '>'._CAPSEC6.'</option>'
    .'<option value="7"';
    if ($conf['gfx_chk'] == '7') $cont .= ' selected';
    $cont .= '>'._CAPSEC7.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._CAPQUALITY.': <div class="sl_small">'._CAPQUALITYI.'</div></td><td><select name="quality" class="sl_conf">';
    $xquality = 1;
    while ($xquality <= 9) {
        $sel = ($xquality == $conf['quality']) ? ' selected' : '';
        $cont .= '<option value="'.$xquality.'"'.$sel.'>0.'.$xquality.'</option>';
        $xquality++;
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._CAPKEY.': <div class="sl_small">'._CAPKEYI.'</div></td><td><input type="text" name="capkey" value="'.$conf['capkey'].'" maxlength="255" class="sl_conf" placeholder="'._CAPKEY.'"></td></tr>'
    .'<tr><td>'._CAPSECKEY.': <div class="sl_small">'._CAPKEYI.'</div></td><td><input type="text" name="capsec" value="'.$conf['capsec'].'" maxlength="255" class="sl_conf" placeholder="'._CAPSECKEY.'"></td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._EDITOR.':</td><td>'.redaktor('2', 'redaktor', 'sl_conf', $conf['redaktor'], 0).'</td></tr>';
    $gtime = timezone_identifiers_list();
    $sel = $conf['gtime'] ?? '';
    $gcont = '';
    foreach ($gtime as $gval) {
        $selected = ($sel === $gval) ? ' selected' : '';
        $gcont .= '<option value="'.$gval.'"'.$selected.'>'.$gval.'</option>';
    }
    $cont .= '<tr><td>'._GTIME.':</td><td><select name="gtime" class="sl_conf">'.$gcont.'</select></td></tr>';
    $cont .= '<tr><td>'._VARIABLES.':<div class="sl_small">'._CTRLINFO.'</div></td><td><select name="variables[]" multiple="multiple" class="sl_conf">';
    $variables = explode(',', $conf['variables']);
    $varconst = [_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.': POST', _AVARIABLES.': GET', _AVARIABLES.': COOKIE', _AVARIABLES.': FILES', _AVARIABLES.': SESSION', _AVARIABLES.': SERVER', _AQUERY_DB.': MySQL'];
    foreach ($varconst as $key => $val) {
        if ($val != '') {
            $selected = (!empty($variables[$key])) ? ' selected' : '';
            $cont .= '<option value="'.$key.'"'.$selected.'>'.$val.'</option>';
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._VAR_VIEW.':</td><td><select name="var_view" class="sl_conf">'
    .'<option value="0"';
    if ($conf['var_view'] == '0') $cont .= ' selected';
    $cont .= '>'._MVADMIN.'</option>'
    .'<option value="1"';
    if ($conf['var_view'] == '1') $cont .= ' selected';
    $cont .= '>'._MVALL.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._SYNTAX.':</td><td><select name="syntax" class="sl_conf">'
    .'<option value="0"';
    if ($conf['syntax'] == '0') $cont .= ' selected';
    $cont .= '>'._SYNTAXP.'</option>'
    .'<option value="1"';
    if ($conf['syntax'] == '1') $cont .= ' selected';
    $cont .= '>'._SYNTAXPN.'</option>'
    .'<option value="2"';
    if ($conf['syntax'] == '2') $cont .= ' selected';
    $cont .= '>'._SYNTAXSH.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._ADMCOL.':</td><td><input type="number" name="admcol" value="'.$conf['admcol'].'" class="sl_conf" placeholder="'._ADMCOL.'" required></td></tr>'
    .'<tr><td>'._DB_SYNC.'</td><td>'.radio_form($conf['dbsync'], 'dbsync').'</td></tr>'
    .'<tr><td>'._SESSION.'</td><td>'.radio_form($conf['session'], 'session').'</td></tr>'
    .'<tr><td>'._MESSAGE_BOX.'</td><td>'.radio_form($conf['message'], 'message').'</td></tr>'
    .'<tr><td>'._TIME_DB.'</td><td>'.radio_form($conf['db_t'], 'db_t').'</td></tr>'
    .'<tr><td>'._ADMIN_SBLOCK.'</td><td>'.radio_form($conf['sblock'], 'sblock').'</td></tr>'
    .'<tr><td>'._ADMINFOEDIT.'</td><td>'.radio_form($conf['adminfo'], 'adminfo').'</td></tr>'
    .'<tr><td>'._SITE_CLOSE.'</td><td>'.radio_form($conf['close'], 'close').'</td></tr>'
    .'<tr><td>'._DEVMODE.'</td><td>'.radio_form($conf['dev_mode'] ?? 0, 'dev_mode').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc1" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._DEFIS.':</td><td><input type="text" name="defis" value="'.urldecode($conf['defis']).'" maxlength="255" class="sl_conf" placeholder="'._DEFIS.'" required></td></tr>'
    .'<tr><td>'._DLETTER.':</td><td><input type="number" name="dletter" value="'.$conf['dletter'].'" class="sl_conf" placeholder="'._DLETTER.'" required></td></tr>'
    .'<tr><td>'._LTITLE.'</td><td>'.radio_form($conf['ltitle'], 'ltitle').'</td></tr>'
    .'<tr><td>'._ADESC.'</td><td>'.radio_form($conf['adesc'], 'adesc').'</td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._RSEP.':</td><td><input type="text" name="sep" value="'.urldecode($conf['sep']).'" maxlength="255" class="sl_conf" placeholder="'._RSEP.'" required></td></tr>'
    .'<tr><td>'._TSEP.':</td><td><input type="text" name="tsep" value="'.urldecode($conf['tsep']).'" maxlength="255" class="sl_conf" placeholder="'._TSEP.'" required></td></tr>'
    .'<tr><td>'._REWRITE_MOD.'</td><td>'.radio_form($conf['rewrite'], 'rewrite').'</td></tr>'
    .'<tr><td>'._SEOTITLE.'</td><td>'.radio_form($conf['title'] ?? 1, 'title').'</td></tr>'
    .'<tr><td>'._SEOCTITLE.'</td><td>'.radio_form($conf['ctitle'] ?? 1, 'ctitle').'</td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._OGRAPH.'</td><td>'.radio_form($conf['agraph'] ?? 1, 'agraph').'</td></tr>'
    .'<tr><td>'._OGRAPHT.'<div class="sl_small">'._TPLVARS.'</div></td><td><textarea name="graph" cols="65" rows="8" class="sl_conf" placeholder="'._OGRAPHT.'">'.htmlspecialchars($conf['graph'] ?? '', ENT_QUOTES, 'UTF-8').'</textarea></td></tr>'
    .'<tr><td>'._SCHEMA.'</td><td>'.radio_form($conf['aschema'] ?? 1, 'aschema').'</td></tr>'
    .'<tr><td>'._SCHEMAT.'<div class="sl_small">'._TPLVARS.'</div></td><td><textarea name="schema" cols="65" rows="15" class="sl_conf" placeholder="'._SCHEMAT.'">'.htmlspecialchars($conf['schema'] ?? '', ENT_QUOTES, 'UTF-8').'</textarea></td></tr>'
    .'</table>'
    .'</div>'
    .'<div id="tabc2" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._SELLANGUAGE.':</td><td><select name="language" class="sl_conf">';
    $entries = is_dir('language') ? scandir('language') : [];
    if (is_array($entries)) {
        foreach ($entries as $file) {
            if (preg_match('/^(.+)\.php/', $file, $matches)) {
                $langfound = $matches[1];
                $selected = ($conf['language'] == $langfound) ? 'selected' : '';
                $cont .= '<option value="'.$langfound.'" '.$selected.'>'.deflang($langfound).'</option>';
            }
        }
    }
    $cont .= '</select></td></tr>'
    .'<tr><td>'._ACTMULTILINGUAL.'</td><td>'.radio_form($conf['multilingual'], 'multilingual').'</td></tr>'
    .'<tr><td>'._ACTUSEFLAGS.'</td><td>'.radio_form($conf['flags'], 'flags').'</td></tr>'
    .'<tr><td>'._GEO_IP.'</td><td>'.radio_form($conf['geo_ip'], 'geo_ip').'</td></tr>'
    .'<tr><td>'._ACTUSELANG.'</td><td>'.radio_form($conf['alang'], 'alang').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc3" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._CENSORMODE.':</td><td>'
    .'<select name="censor" class="sl_conf">'
    .'<option value="0"';
    if ($conf['censor'] == 0) $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($conf['censor'] == 1) $cont .= ' selected';
    $cont .= '>'._MATCHANY.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._CENSORREPLACE.':</td><td><input type="text" name="censor_r" value="'.$conf['censor_r'].'" maxlength="10" class="sl_conf" placeholder="'._CENSORREPLACE.'" required></td></tr>'
    .'<tr><td>'._CENSOR.':<div class="sl_small">'._NOKOMA.'</div></td><td><textarea name="censor_l" cols="65" rows="5" class="sl_conf" placeholder="'._CENSOR.'" required>'.$conf['censor_l'].'</textarea></td></tr>'
    .'<tr><td>'._CLICABLE.'<div class="sl_small">'._CLICABLEINFO.'</div></td><td>'.radio_form($conf['clickable'], 'clickable').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc4" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._SMODULE.':<div class="sl_small">'._CTRLINFO.'</div></td><td>'.modul('search', 'sl_conf', $conf['search'], 1).'</td></tr>'
    .'<tr><td>'._SEARCHLETMIN.':<div class="sl_small">'._SEARCHLETINFO.'</div></td><td><input type="number" name="slet" value="'.$conf['slet'].'" class="sl_conf" placeholder="'._SEARCHLETMIN.'" required></td></tr>'
    .'<tr><td>'._SEARCHNUM.':</td><td><input type="number" name="snum" value="'.$conf['snum'].'" class="sl_conf" placeholder="'._SEARCHNUM.'" required></td></tr>'
    .'<tr><td>'._C_35.':</td><td><input type="number" name="snump" value="'.$conf['snump'].'" class="sl_conf" placeholder="'._C_35.'" required></td></tr>'
    .'<tr><td>'._ASEARCH.'</td><td>'.radio_form($conf['asearch'], 'asearch').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc5" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._BOTSLIST.':<div class="sl_small">'._NOKOMA.' '._BOTSINFO.'</div></td><td><textarea name="bots" cols="65" rows="10" class="sl_conf" placeholder="'._BOTSLIST.'" required>'.$conf['bots'].'</textarea></td></tr>'
    .'<tr><td>'._BOTSSITE.':<div class="sl_small">'._NOKOMA.'</div></td><td><textarea name="fbots" cols="65" rows="10" class="sl_conf" placeholder="'._BOTSSITE.'" required>'.$conf['fbots'].'</textarea></td></tr>'
    .'<tr><td>'._BOTSACT.'</td><td>'.radio_form($conf['botsact'], 'botsact').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc6" class="tabcont">';
    $f = $asize = 0;
    foreach (glob('config/cache/*.txt') as $file) {
        $size = filesize($file);
        $f++;
        $asize += $size;
    }
    $cont .= setTemplateWarning('warn', ['time' => '', 'url' => '', 'id' => 'info', 'text' => _DIR.': config/cache<br>'._FILE_M.': '.$f.'<br>'._FILE_S.': '.files_size($asize)]);
    $cont .= '<table class="sl_table_conf">'
    .'<tr><td>'._CACHE.':</td><td>'
    .'<select name="cache" class="sl_conf">'
    .'<option value="0"';
    if ($conf['cache'] == 0) $cont .= ' selected';
    $cont .= '>'._NO.'</option>'
    .'<option value="1"';
    if ($conf['cache'] == 1) $cont .= ' selected';
    $cont .= '>'._CACHE_1.'</option>'
    .'<option value="2"';
    if ($conf['cache'] == 2) $cont .= ' selected';
    $cont .= '>'._CACHE_2.'</option>'
    .'</select></td></tr>'
    .'<tr><td>'._CACHETIME.':</td><td><input type="number" name="cache_t" value="'.$conf['cache_t'].'" class="sl_conf" placeholder="'._CACHETIME.'" required></td></tr>'
    .'<tr><td>'._CACHEDEL.':</td><td><input type="number" name="cache_d" value="'.$conf['cache_d'].'" class="sl_conf" placeholder="'._CACHEDEL.'" required></td></tr>'
    .'<tr><td>'._CACHECOMP.'</td><td>'.radio_form($conf['cache_c'], 'cache_c').'</td></tr>'
    .'<tr><td>'._CACHEBROW.'</td><td>'.radio_form($conf['cache_b'], 'cache_b').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><hr></td></tr>'
    .'<tr><td>'._CACHECSS.'</td><td>'.radio_form($conf['cache_css'], 'cache_css').'</td></tr>'
    .'<tr><td>'._CSSDIR.':<div class="sl_small">'._CSSDIRINFO.' '._NOKOMA.'</div></td><td><textarea name="css_f" cols="65" rows="5" class="sl_conf" placeholder="'._CSSDIRINFO.'" required>'.$conf['css_f'].'</textarea></td></tr>'
    .'<tr><td>'._CSSHEAD.'</td><td>'.radio_form($conf['css_h'], 'css_h').'</td></tr>'
    .'<tr><td>'._CSSCOMP.'</td><td>'.radio_form($conf['css_c'], 'css_c').'</td></tr>'
    .'<tr><td>'._CSSENC.'</td><td>'.radio_form($conf['css_e'], 'css_e').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><hr></td></tr>'
    .'<tr><td>'._CACHESCRIPT.'</td><td>'.radio_form($conf['cache_script'], 'cache_script').'</td></tr>'
    .'<tr><td>'._SCRIPTFILE.':<div class="sl_small">'._SCRIPTFILEINFO.' '._NOKOMA.'</div></td><td><textarea name="script_f" cols="65" rows="5" class="sl_conf" placeholder="'._SCRIPTFILEINFO.'" required>'.$conf['script_f'].'</textarea></td></tr>'
    .'<tr><td>'._SCRIPTHEAD.'</td><td>'.radio_form($conf['script_h'], 'script_h').'</td></tr>'
    .'<tr><td>'._SCRIPTCOMP.'</td><td>'.radio_form($conf['script_c'], 'script_c').'</td></tr>'
    .'<tr><td>'._SCRIPTASIN.'</td><td>'.radio_form($conf['script_a'], 'script_a').'</td></tr>'
    .'<tr><td>'._SCRIPTBOT.'</td><td>'.radio_form($conf['script_b'], 'script_b').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc7" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._MAILTEMP.':<div class="sl_small">'._MAILTEMPINFO.'</div></td><td><textarea name="mtemp" cols="65" rows="10" class="sl_conf" placeholder="'._MAILTEMP.'" required>'.$conf['mtemp'].'</textarea></td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent(\'config\')
        countries.setpersist(true)
        countries.setselectedClassTarget(\'link\')
        countries.init()
    </script>'
    .'<table class="sl_table_conf"><tr><td class="sl_center"><input type="hidden" name="name" value="config"><input type="hidden" name="op" value="save"><input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    $cont .= setTemplateBasic('close');
    echo $cont;
    foot();
}

function save(): void {
    global $afile, $conf;
    $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
    $kprotect = [', ' => ',', ' ,' => ',', ' , ' => ',', ',,' => ',', '\n' => ',', '\t' => ',', '\r' => ','];

    $homeurl = getVar('post', 'homeurl', 'url');
    $xhomeurl = ($homeurl[strlen($homeurl) - 1] == '/') ? substr($homeurl, 0, -1) : $homeurl;
    $xsite_logo = str_replace('templates/'.$conf['theme'].'/images/logos/', '', getVar('post', 'site_logo', 'text'));

    $xuser_c = getVar('post', 'user_c', 'text');
    $xadmin_c = getVar('post', 'admin_c', 'text');
    if ($xuser_c === $xadmin_c) {
        $xuser_c = 'user-'.$xuser_c;
        $xadmin_c = 'admin-'.$xadmin_c;
    }

    $module = getVar('post', 'module', 'var');
    $xmodule = $module ? implode(',', $module) : '0';

    $variables = getVar('post', 'variables', 'var');
    $variables = $variables ? array_map('strval', (array)$variables) : [];
    $xvariables = [];
    for ($i = 0; $i < 9; $i++) $xvariables[] = in_array((string)$i, $variables, true) ? '1' : '0';
    $xvariables = implode(',', $xvariables);

    $xcensor_r = strtolower(strtr(getVar('post', 'censor_r', 'text', ''), $protect));
    $xcensor_l = strtolower(strtr(getVar('post', 'censor_l', 'text', ''), $protect));
    $xcensor = (!$xcensor_r || !$xcensor_l) ? 0 : getVar('post', 'censor', 'num');
    $search = getVar('post', 'search', 'var');
    $xsearch = $search ? implode(',', $search) : '0';

    $cont = [
        'version' => '6.3.0 Phoenix',
        'sitename' => getVar('post', 'sitename', 'text'),
        'homeurl' => $xhomeurl,
        'site_logo' => $xsite_logo,
        'slogan' => getVar('post', 'slogan', 'text'),
        'admininfo' => getVar('post', 'admininfo', 'text'),
        'startdate' => save_datetime(1, 'startdate'),
        'adminmail' => getVar('post', 'adminmail', 'text'),
        'user_c' => $xuser_c,
        'admin_c' => $xadmin_c,
        'user_c_t' => getVar('post', 'user_c_t', 'num', 30) * 86400,
        'sess_t' => getVar('post', 'sess_t', 'num', 10) * 60,
        'ip_link' => getVar('post', 'ip_link', 'url', 'http://whois.domaintools.com/'),
        'theme' => getVar('post', 'theme', 'var'),
        'module' => $xmodule,
        'amod' => getVar('post', 'amod', 'var'),
        'gfx_chk' => getVar('post', 'gfx_chk', 'num'),
        'quality' => getVar('post', 'quality', 'num'),
        'capkey' => getVar('post', 'capkey', 'text'),
        'capsec' => getVar('post', 'capsec', 'text'),
        'redaktor' => getVar('post', 'redaktor', 'num'),
        'gtime' => getVar('post', 'gtime', 'text'),
        'var_view' => getVar('post', 'var_view', 'num'),
        'syntax' => getVar('post', 'syntax', 'num'),
        'variables' => $xvariables,
        'admcol' => getVar('post', 'admcol', 'num', 5),
        'dbsync' => getVar('post', 'dbsync', 'num'),
        'session' => getVar('post', 'session', 'num'),
        'message' => getVar('post', 'message', 'num'),
        'db_t' => getVar('post', 'db_t', 'num'),
        'sblock' => getVar('post', 'sblock', 'num'),
        'adminfo' => getVar('post', 'adminfo', 'num'),
        'close' => getVar('post', 'close', 'num'),
        'defis' => urlencode(getVar('post', 'defis', 'let', '|')),
        'dletter' => getVar('post', 'dletter', 'num', 160),
        'ltitle' => getVar('post', 'ltitle', 'num'),
        'adesc' => getVar('post', 'adesc', 'num'),
        'sep' => urlencode(getVar('post', 'sep', 'let', '-')),
        'tsep' => urlencode(getVar('post', 'tsep', 'let', '-')),
        'rewrite' => getVar('post', 'rewrite', 'num'),
        'title' => getVar('post', 'title', 'num'),
        'ctitle' => getVar('post', 'ctitle', 'num'),
        'agraph' => getVar('post', 'agraph', 'num'),
        'graph' => getVar('post', 'graph', 'raw'),
        'aschema' => getVar('post', 'aschema', 'num'),
        'schema' => getVar('post', 'schema', 'raw'),
        'language' => getVar('post', 'language', 'var'),
        'multilingual' => getVar('post', 'multilingual', 'num'),
        'flags' => getVar('post', 'flags', 'num'),
        'geo_ip' => getVar('post', 'geo_ip', 'num'),
        'alang' => getVar('post', 'alang', 'num'),
        'censor' => $xcensor,
        'censor_r' => $xcensor_r,
        'censor_l' => $xcensor_l,
        'clickable' => getVar('post', 'clickable', 'num'),
        'search' => $xsearch,
        'slet' => getVar('post', 'slet', 'num'),
        'snum' => getVar('post', 'snum', 'num'),
        'snump' => getVar('post', 'snump', 'num'),
        'asearch' => getVar('post', 'asearch', 'num'),
        'bots' => strtr(getVar('post', 'bots', 'text', ''), $kprotect),
        'fbots' => strtr(getVar('post', 'fbots', 'text', ''), $kprotect),
        'botsact' => getVar('post', 'botsact', 'num'),
        'cache' => getVar('post', 'cache', 'num'),
        'cache_t' => getVar('post', 'cache_t', 'num', 60),
        'cache_d' => getVar('post', 'cache_d', 'num', 30),
        'cache_c' => getVar('post', 'cache_c', 'num'),
        'cache_b' => getVar('post', 'cache_b', 'num'),
        'cache_css' => getVar('post', 'cache_css', 'num'),
        'css_f' => strtr(getVar('post', 'css_f', 'text', 'templates/[theme]/,plugins/jquery/ui/,plugins/fancybox/,plugins/uploadify/,plugins/syntaxhighlighter/styles/'), $kprotect),
        'css_h' => getVar('post', 'css_h', 'num'),
        'css_c' => getVar('post', 'css_c', 'num'),
        'css_e' => getVar('post', 'css_e', 'num'),
        'cache_script' => getVar('post', 'cache_script', 'num'),
        'script_f' => strtr(getVar('post', 'script_f', 'text', 'plugins/system/global-func.js,plugins/jquery/jquery.js,plugins/jquery/ui/jquery-ui.js,plugins/jquery/jquery.tablesorter.js,plugins/jquery/jquery.cookie.js,plugins/fancybox/jquery.mousewheel.js,plugins/fancybox/jquery.fancybox.js,plugins/jquery/jquery.slaed.js'), $kprotect),
        'script_h' => getVar('post', 'script_h', 'num'),
        'script_c' => getVar('post', 'script_c', 'num'),
        'script_a' => getVar('post', 'script_a', 'num'),
        'script_b' => getVar('post', 'script_b', 'num'),
        'mtemp' => getVar('post', 'mtemp', 'raw'),
        'dev_mode' => getVar('post', 'dev_mode', 'num'),
        'newsletter' => $conf['newsletter'],
        'newslettercount' => $conf['newslettercount'],
        'sitekey' => getPass(25),
        'lic_h' => 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt',
        'lic_f' => 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg=='
    ];
    setConfigFile('global.php', $cont);
    setRedirect($afile.'.php?name=config');
}

function info(): void {
    head();
    echo navi(1, 8, 0, 0, '').'<div id="repadm_info">'.adm_info(1, 0, 'config').'</div>';
    foot();
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
