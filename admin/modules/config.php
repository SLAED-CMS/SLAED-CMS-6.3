<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = setAdminNavi(['ops' => ['', '', '', '', '', '', '', 'name=config&amp;op=info'], 'tabs' => [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO], 'id' => 'config']);
    $cont .= checkPerms(CONFIG_DIR.'/global.php');
    $confv = '<form name="post" action="'.$afile.'.php" method="post">'
    .'<div id="tabc0" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._VERSION.':</td><td><a href="//slaed.net" target="_blank" title="'._VERSION.'">SLAED CMS '.$conf['version'].'</a></td></tr>'
    .'<tr><td>'._SITENAME.':</td><td>'.getAdminTextInput('sitename', (string)$conf['sitename'], 'sl_conf', 'maxlength="255" placeholder="'._SITENAME.'" required').'</td></tr>'
    .'<tr><td>'._SITEURL.':</td><td><input type="url" name="homeurl" value="'.$conf['homeurl'].'" maxlength="255" class="sl_conf" placeholder="'._SITEURL.'" required></td></tr>';
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $entries = is_dir($path) ? scandir($path) : [];
    $logoOpts = '';
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $logoOpts .= getAdminOption($path.$entry, $entry, $conf['site_logo'] == $entry);
            }
        }
    }
    $cont .= '<tr><td>'._LOGO.':</td><td>'.getAdminSelect('site_logo', $logoOpts, 'sl_conf', 'id="img_replace"').'</td></tr>'
    .'<tr><td>'._SITELOGO.':</td><td><img src="'.$path.$conf['site_logo'].'" id="picture" alt="'._SITELOGO.'"></td></tr>'
    .'<tr><td>'._DESCRIPTION.':</td><td><textarea name="slogan" cols="65" rows="5" class="sl_conf" placeholder="'._DESCRIPTION.'" required>'.$conf['slogan'].'</textarea></td></tr>'
    .'<tr><td>'.getAdminHintLabel(_ADMININFO, _ADMININFODES).'</td><td><textarea name="admininfo" cols="65" rows="5" class="sl_conf" placeholder="'._ADMININFO.'">'.$conf['admininfo'].'</textarea></td></tr>'
    .'<tr><td>'._STARTDATE.':</td><td>'.datetime(1, 'startdate', $conf['startdate'], 16, 'sl_conf').'</td></tr>'
    .'<tr><td>'._ADMINEMAIL.':</td><td><input type="email" name="adminmail" value="'.$conf['adminmail'].'" maxlength="255" class="sl_conf" placeholder="'._ADMINEMAIL.'" required></td></tr>'
    .'<tr><td>'._USER_COOKIE.':</td><td>'.getAdminTextInput('user_c', (string)$conf['user_c'], 'sl_conf', 'maxlength="255" placeholder="'._USER_COOKIE.'" required').'</td></tr>'
    .'<tr><td>'._ADMIN_SESSION.':</td><td>'.getAdminTextInput('admin_c', (string)$conf['admin_c'], 'sl_conf', 'maxlength="255" placeholder="'._ADMIN_SESSION.'" required').'</td></tr>'
    .'<tr><td>'._USER_COOKIE_T.':</td><td>'.getAdminNumberInput('user_c_t', (string)intval($conf['user_c_t'] / 86400), 'sl_conf', 'placeholder="'._USER_COOKIE_T.'" required').'</td></tr>'
    .'<tr><td>'._SESS_T.':</td><td>'.getAdminNumberInput('sess_t', (string)intval($conf['sess_t'] / 60), 'sl_conf', 'placeholder="'._SESS_T.'" required').'</td></tr>'
    .'<tr><td>'._IP_LINK.':</td><td><input type="url" name="ip_link" value="'.$conf['ip_link'].'" maxlength="255" class="sl_conf" placeholder="'._IP_LINK.'" required></td></tr>'
    .'<tr><td>'._THEME.':</td><td>';
    $templates = is_dir('templates') ? scandir('templates') : [];
    $themeOpts = '';
    if (is_array($templates)) {
        foreach ($templates as $tfile) {
            if (!preg_match('/\./', $tfile) && $tfile != 'admin') {
                $themeOpts .= getAdminOption($tfile, $tfile, $tfile == $conf['theme']);
            }
        }
    }
    $cont .= getAdminSelect('theme', $themeOpts, 'sl_conf').'</td></tr><tr><td>'.getAdminHintLabel(_PUTINHOME, _PUTINHOMEINFO.' '._CTRLINFO).'</td><td>'.modul('module', 'sl_conf', $conf['module'], 1).'</td></tr>';
    $mods = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'page', 'shop_clients', 'voting'];
    $mname = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'pages', 'shop', 'voting'];
    $i = 0;
    $ocont = '';
    foreach ($mods as $val) {
        if ($val != '') {
            if (file_exists('modules/'.$mname[$i].'/admin/index.php')) {
                $ocont .= getAdminOption($val, getModuleName($mname[$i]), $conf['amod'] == $val);
            }
            $i++;
        }
    }
    $cont .= '<tr><td>'._PUTINAHOME.':</td><td>'.getAdminSelect('amod', $ocont, 'sl_conf').'</td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._CAPTCHA.':</td><td>';
    $captchaOpts = getAdminOption('0', _CAPSEC0, $conf['gfx_chk'] == '0')
        .getAdminOption('1', _CAPSEC1, $conf['gfx_chk'] == '1')
        .getAdminOption('2', _CAPSEC2, $conf['gfx_chk'] == '2')
        .getAdminOption('3', _CAPSEC3, $conf['gfx_chk'] == '3')
        .getAdminOption('4', _CAPSEC4, $conf['gfx_chk'] == '4')
        .getAdminOption('5', _CAPSEC5, $conf['gfx_chk'] == '5')
        .getAdminOption('6', _CAPSEC6, $conf['gfx_chk'] == '6')
        .getAdminOption('7', _CAPSEC7, $conf['gfx_chk'] == '7');
    $cont .= getAdminSelect('gfx_chk', $captchaOpts, 'sl_conf').'</td></tr>'
    .'<tr><td>'._CAPQUALITY.': <div class="sl_small">'._CAPQUALITYI.'</div></td><td>';
    $qualityOpts = '';
    $xquality = 1;
    while ($xquality <= 9) {
        $qualityOpts .= getAdminOption((string)$xquality, '0.'.$xquality, $xquality == $conf['quality']);
        $xquality++;
    }
    $cont .= getAdminSelect('quality', $qualityOpts, 'sl_conf').'</td></tr>'
    .'<tr><td>'._CAPKEY.': <div class="sl_small">'._CAPKEYI.'</div></td><td>'.getAdminTextInput('capkey', (string)$conf['capkey'], 'sl_conf', 'maxlength="255" placeholder="'._CAPKEY.'"').'</td></tr>'
    .'<tr><td>'._CAPSECKEY.': <div class="sl_small">'._CAPKEYI.'</div></td><td>'.getAdminTextInput('capsec', (string)$conf['capsec'], 'sl_conf', 'maxlength="255" placeholder="'._CAPSECKEY.'"').'</td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._EDITOR.':</td><td>'.redaktor('2', 'redaktor', 'sl_conf', $conf['redaktor'], 0).'</td></tr>';
    $gtime = timezone_identifiers_list();
    $gtimeCurrent = $conf['gtime'] ?? '';
    $gcont = '';
    foreach ($gtime as $gval) {
        $gcont .= getAdminOption($gval, $gval, $gtimeCurrent === $gval);
    }
    $cont .= '<tr><td>'._GTIME.':</td><td>'.getAdminSelect('gtime', $gcont, 'sl_conf').'</td></tr>';
    $variables = explode(',', $conf['variables']);
    $varconst = [_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.': POST', _AVARIABLES.': GET', _AVARIABLES.': COOKIE', _AVARIABLES.': FILES', _AVARIABLES.': SESSION', _AVARIABLES.': SERVER', _AQUERY_DB.': MySQL'];
    $varOpts = '';
    foreach ($varconst as $key => $val) {
        if ($val != '') {
            $varOpts .= getAdminOption((string)$key, $val, !empty($variables[$key]));
        }
    }
    $cont .= '<tr><td>'.getAdminHintLabel(_VARIABLES, _CTRLINFO).'</td><td>'.getAdminSelect('variables[]', $varOpts, 'sl_conf', 'multiple="multiple"').'</td></tr>'
    .'<tr><td>'._VAR_VIEW.':</td><td>'.getAdminSelect('var_view',
        getAdminOption('0', _MVADMIN, $conf['var_view'] == '0')
        .getAdminOption('1', _MVALL, $conf['var_view'] == '1'),
        'sl_conf').'</td></tr>'
    .'<tr><td>'._SYNTAX.':</td><td>'.getAdminSelect('syntax',
        getAdminOption('0', _SYNTAXP, $conf['syntax'] == '0')
        .getAdminOption('1', _SYNTAXPN, $conf['syntax'] == '1')
        .getAdminOption('2', _SYNTAXSH, $conf['syntax'] == '2'),
        'sl_conf').'</td></tr>'
    .'<tr><td>'._ADMCOL.':</td><td>'.getAdminNumberInput('admcol', (string)$conf['admcol'], 'sl_conf', 'placeholder="'._ADMCOL.'" required').'</td></tr>'
    .'<tr><td>'._DB_SYNC.'</td><td>'.radio_form($conf['dbsync'], 'dbsync').'</td></tr>'
    .'<tr><td>'._SESSION.'</td><td>'.radio_form($conf['session'], 'session').'</td></tr>'
    .'<tr><td>'._MESSAGE_BOX.'</td><td>'.radio_form($conf['message'], 'message').'</td></tr>'
    .'<tr><td>'._TIME_DB.'</td><td>'.radio_form($conf['db_t'], 'db_t').'</td></tr>'
    .'<tr><td>'._ADMINFOEDIT.'</td><td>'.radio_form($conf['adminfo'], 'adminfo').'</td></tr>'
    .'<tr><td>'._SITE_CLOSE.'</td><td>'.radio_form($conf['close'], 'close').'</td></tr>'
    .'<tr><td>'._DEVMODE.'</td><td>'.radio_form($conf['dev_mode'] ?? 0, 'dev_mode').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc1" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._DEFIS.':</td><td>'.getAdminTextInput('defis', urldecode($conf['defis']), 'sl_conf', 'maxlength="255" placeholder="'._DEFIS.'" required').'</td></tr>'
    .'<tr><td>'._DLETTER.':</td><td>'.getAdminNumberInput('dletter', (string)$conf['dletter'], 'sl_conf', 'placeholder="'._DLETTER.'" required').'</td></tr>'
    .'<tr><td>'._LTITLE.'</td><td>'.radio_form($conf['ltitle'], 'ltitle').'</td></tr>'
    .'<tr><td>'._ADESC.'</td><td>'.radio_form($conf['adesc'], 'adesc').'</td></tr>'
    .'<tr><td colspan="2"><hr></td></tr>'
    .'<tr><td>'._RSEP.':</td><td>'.getAdminTextInput('sep', urldecode($conf['sep']), 'sl_conf', 'maxlength="255" placeholder="'._RSEP.'" required').'</td></tr>'
    .'<tr><td>'._TSEP.':</td><td>'.getAdminTextInput('tsep', urldecode($conf['tsep']), 'sl_conf', 'maxlength="255" placeholder="'._TSEP.'" required').'</td></tr>'
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
    .'<tr><td>'._SELLANGUAGE.':</td><td>';
    $entries = is_dir('lang') ? scandir('lang') : [];
    $langOpts = '';
    if (is_array($entries)) {
        foreach ($entries as $file) {
            if (preg_match('/^(.+)\.php/', $file, $matches)) {
                $langfound = $matches[1];
                $langOpts .= getAdminOption($langfound, getLangName($langfound), $conf['language'] == $langfound);
            }
        }
    }
    $cont .= getAdminSelect('language', $langOpts, 'sl_conf').'</td></tr>'
    .'<tr><td>'._ACTMULTILINGUAL.'</td><td>'.radio_form($conf['multilingual'], 'multilingual').'</td></tr>'
    .'<tr><td>'._ACTUSEFLAGS.'</td><td>'.radio_form($conf['flags'], 'flags').'</td></tr>'
    .'<tr><td>'._GEO_IP.'</td><td>'.radio_form($conf['geo_ip'], 'geo_ip').'</td></tr>'
    .'<tr><td>'._ACTUSELANG.'</td><td>'.radio_form($conf['alang'], 'alang').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc3" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'._CENSORMODE.':</td><td>'.getAdminSelect('censor',
        getAdminOption('0', _NO, $conf['censor'] == 0)
        .getAdminOption('1', _MATCHANY, $conf['censor'] == 1),
        'sl_conf').'</td></tr>'
    .'<tr><td>'._CENSORREPLACE.':</td><td>'.getAdminTextInput('censor_r', (string)$conf['censor_r'], 'sl_conf', 'maxlength="10" placeholder="'._CENSORREPLACE.'" required').'</td></tr>'
    .'<tr><td>'.getAdminHintLabel(_CENSOR, _NOKOMA).'</td><td><textarea name="censor_l" cols="65" rows="5" class="sl_conf" placeholder="'._CENSOR.'" required>'.$conf['censor_l'].'</textarea></td></tr>'
    .'<tr><td>'._CLICABLE.'<div class="sl_small">'._CLICABLEINFO.'</div></td><td>'.radio_form($conf['clickable'], 'clickable').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc4" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'.getAdminHintLabel(_BOTSLIST, _NOKOMA.' '._BOTSINFO).'</td><td><textarea name="bots" cols="65" rows="10" class="sl_conf" placeholder="'._BOTSLIST.'" required>'.$conf['bots'].'</textarea></td></tr>'
    .'<tr><td>'.getAdminHintLabel(_BOTSSITE, _NOKOMA).'</td><td><textarea name="fbots" cols="65" rows="10" class="sl_conf" placeholder="'._BOTSSITE.'" required>'.$conf['fbots'].'</textarea></td></tr>'
    .'<tr><td>'._BOTSACT.'</td><td>'.radio_form($conf['botsact'], 'botsact').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc5" class="tabcont">';
    $f = $asize = 0;
    foreach (glob('config/cache/*.txt') as $file) {
        $size = filesize($file);
        $f++;
        $asize += $size;
    }
    $cont .= $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _DIR.': config/cache<br>'._FILE_M.': '.$f.'<br>'._FILE_S.': '.filterSize($asize)]);
    $cont .= '<table class="sl_table_conf">'
    .'<tr><td>'._CACHE.':</td><td>'.getAdminSelect('cache',
        getAdminOption('0', _NO, $conf['cache'] == 0)
        .getAdminOption('1', _CACHE_1, $conf['cache'] == 1)
        .getAdminOption('2', _CACHE_2, $conf['cache'] == 2),
        'sl_conf').'</td></tr>'
    .'<tr><td>'._CACHETIME.':</td><td>'.getAdminNumberInput('cache_t', (string)$conf['cache_t'], 'sl_conf', 'placeholder="'._CACHETIME.'" required').'</td></tr>'
    .'<tr><td>'._CACHEDEL.':</td><td>'.getAdminNumberInput('cache_d', (string)$conf['cache_d'], 'sl_conf', 'placeholder="'._CACHEDEL.'" required').'</td></tr>'
    .'<tr><td>'._CACHECOMP.'</td><td>'.radio_form($conf['cache_c'], 'cache_c').'</td></tr>'
    .'<tr><td>'._CACHEBROW.'</td><td>'.radio_form($conf['cache_b'], 'cache_b').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><hr></td></tr>'
    .'<tr><td>'._CACHECSS.'</td><td>'.radio_form($conf['cache_css'], 'cache_css').'</td></tr>'
    .'<tr><td>'.getAdminHintLabel(_CSSDIR, _CSSDIRINFO.' '._NOKOMA).'</td><td><textarea name="css_f" cols="65" rows="5" class="sl_conf" placeholder="'._CSSDIRINFO.'" required>'.$conf['css_f'].'</textarea></td></tr>'
    .'<tr><td>'._CSSHEAD.'</td><td>'.radio_form($conf['css_h'], 'css_h').'</td></tr>'
    .'<tr><td>'._CSSCOMP.'</td><td>'.radio_form($conf['css_c'], 'css_c').'</td></tr>'
    .'<tr><td>'._CSSENC.'</td><td>'.radio_form($conf['css_e'], 'css_e').'</td></tr>'
    .'<tr><td colspan="2" class="sl_center"><hr></td></tr>'
    .'<tr><td>'._CACHESCRIPT.'</td><td>'.radio_form($conf['cache_script'], 'cache_script').'</td></tr>'
    .'<tr><td>'.getAdminHintLabel(_SCRIPTFILE, _SCRIPTFILEINFO.' '._NOKOMA).'</td><td><textarea name="script_f" cols="65" rows="5" class="sl_conf" placeholder="'._SCRIPTFILEINFO.'" required>'.$conf['script_f'].'</textarea></td></tr>'
    .'<tr><td>'._SCRIPTHEAD.'</td><td>'.radio_form($conf['script_h'], 'script_h').'</td></tr>'
    .'<tr><td>'._SCRIPTCOMP.'</td><td>'.radio_form($conf['script_c'], 'script_c').'</td></tr>'
    .'<tr><td>'._SCRIPTASIN.'</td><td>'.radio_form($conf['script_a'], 'script_a').'</td></tr>'
    .'<tr><td>'._SCRIPTBOT.'</td><td>'.radio_form($conf['script_b'], 'script_b').'</td></tr></table>'
    .'</div>'
    .'<div id="tabc6" class="tabcont">'
    .'<table class="sl_table_conf">'
    .'<tr><td>'.getAdminHintLabel(_MAILTEMP, _MAILTEMPINFO).'</td><td><textarea name="mtemp" cols="65" rows="10" class="sl_conf" placeholder="'._MAILTEMP.'" required>'.$conf['mtemp'].'</textarea></td></tr></table>'
    .'</div>'
    .'<script>
        var countries=new ddtabcontent(\'config\')
        countries.setpersist(true)
        countries.setselectedClassTarget(\'link\')
        countries.init()
    </script>'
    .'<table class="sl_table_conf"><tr><td class="sl_center">'.getAdminHidden('name', 'config').getAdminHidden('op', 'save').'<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue"></td></tr></table></form>';
    echo $cont.getAdminBox($confv);
    setFoot();
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

    $module = getVar('post', 'module[]', 'var');
    $module = is_array($module) ? array_values(array_filter(array_map('strval', $module), static fn(string $val): bool => $val !== '')) : [];
    $xmodule = $module ? implode(',', $module) : '0';

    $variables = getVar('post', 'variables[]', 'var');
    $variables = $variables ? array_map('strval', (array)$variables) : [];
    $xvariables = [];
    for ($i = 0; $i < 9; $i++) $xvariables[] = in_array((string)$i, $variables, true) ? '1' : '0';
    $xvariables = implode(',', $xvariables);

    $xcensor_r = strtolower(strtr(getVar('post', 'censor_r', 'text', ''), $protect));
    $xcensor_l = strtolower(strtr(getVar('post', 'censor_l', 'text', ''), $protect));
    $xcensor = (!$xcensor_r || !$xcensor_l) ? 0 : getVar('post', 'censor', 'num');

    $cont = [
        'version' => '6.3.0 Phoenix',
        'sitename' => getVar('post', 'sitename', 'text'),
        'homeurl' => $xhomeurl,
        'site_logo' => $xsite_logo,
        'slogan' => getVar('post', 'slogan', 'text'),
        'admininfo' => getVar('post', 'admininfo', 'text'),
        'startdate' => getVar('req', 'startdate', 'time'),
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
        'sitekey' => getPass(25),
        'lic_h' => 'UG93ZXJlZCBieSA8YSBocmVmPSJodHRwczovL3NsYWVkLm5ldCIgdGFyZ2V0PSJfYmxhbmsiIHRpdGxlPSJTTEFFRCBDTVMiPlNMQUVEIENNUzwvYT4gJmNvcHk7IDIwMDUt',
        'lic_f' => 'IFNMQUVELiBBbGwgcmlnaHRzIHJlc2VydmVkLg=='
    ];
    setConfigFile('global.php', $cont);
    setRedirect($afile.'.php?name=config');
}

function info(): void {
    setHead();
    $cont = setAdminNavi(['ops' => ['name=config', 'name=config', 'name=config', 'name=config', 'name=config&amp;op=show', 'name=config', 'name=config', 'name=config&amp;op=info'], 'tabs' => [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO], 'tab' => 8]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}

