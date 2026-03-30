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
    $rows = '';
    $rows .= getAdminFormRow(_VERSION.':', getExternalAction('//slaed.net', _VERSION, 'SLAED CMS '.$conf['version']));
    $rows .= getAdminFormRow(_SITENAME.':', getAdminTextInput('sitename', (string)$conf['sitename'], 'sl_conf', 'maxlength="255" placeholder="'._SITENAME.'" required'));
    $rows .= getAdminFormRow(_SITEURL.':', getAdminUrlInput('homeurl', $conf['homeurl'], 'sl_conf', 'maxlength="255" placeholder="'._SITEURL.'" required'));
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $list = is_dir($path) ? scandir($path) : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $opts .= getAdminOption($path.$entry, $entry, $conf['site_logo'] == $entry);
            }
        }
    }
    $rows .= getAdminFormRow(_LOGO.':', getAdminSelect('site_logo', $opts, 'sl_conf', 'id="img_replace"'));
    $rows .= getAdminFormRow(_SITELOGO.':', getAdminImagePreview($path.$conf['site_logo'], _SITELOGO));
    $rows .= getAdminFormRow(_DESCRIPTION.':', getAdminTextarea('slogan', $conf['slogan'], 'sl_conf', 'placeholder="'._DESCRIPTION.'" required'));
    $rows .= getAdminFormRow(getAdminHintLabel(_ADMININFO, _ADMININFODES), getAdminTextarea('admininfo', $conf['admininfo'], 'sl_conf', 'placeholder="'._ADMININFO.'"'));
    $rows .= getAdminFormRow(_STARTDATE.':', datetime(1, 'startdate', $conf['startdate'], 16, 'sl_conf'));
    $rows .= getAdminFormRow(_ADMINEMAIL.':', getAdminEmailInput('adminmail', $conf['adminmail'], 'sl_conf', 'maxlength="255" placeholder="'._ADMINEMAIL.'" required'));
    $rows .= getAdminFormRow(_USER_COOKIE.':', getAdminTextInput('user_c', (string)$conf['user_c'], 'sl_conf', 'maxlength="255" placeholder="'._USER_COOKIE.'" required'));
    $rows .= getAdminFormRow(_ADMIN_SESSION.':', getAdminTextInput('admin_c', (string)$conf['admin_c'], 'sl_conf', 'maxlength="255" placeholder="'._ADMIN_SESSION.'" required'));
    $rows .= getAdminFormRow(_USER_COOKIE_T.':', getAdminNumberInput('user_c_t', (string)intval($conf['user_c_t'] / 86400), 'sl_conf', 'placeholder="'._USER_COOKIE_T.'" required'));
    $rows .= getAdminFormRow(_SESS_T.':', getAdminNumberInput('sess_t', (string)intval($conf['sess_t'] / 60), 'sl_conf', 'placeholder="'._SESS_T.'" required'));
    $rows .= getAdminFormRow(_IP_LINK.':', getAdminUrlInput('ip_link', $conf['ip_link'], 'sl_conf', 'maxlength="255" placeholder="'._IP_LINK.'" required'));
    $list = is_dir('templates') ? scandir('templates') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $opts .= getAdminOption($file, $file, $file == $conf['theme']);
            }
        }
    }
    $rows .= getAdminFormRow(_THEME.':', getAdminSelect('theme', $opts, 'sl_conf'));
    $rows .= getAdminFormRow(getAdminHintLabel(_PUTINHOME, _PUTINHOMEINFO.' '._CTRLINFO), modul('module', 'sl_conf', $conf['module'], 1));
    $mods = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'page', 'shop_clients', 'voting'];
    $mname = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'pages', 'shop', 'voting'];
    $ival = 0;
    $opts = '';
    foreach ($mods as $val) {
        if ($val != '') {
            if (file_exists('modules/'.$mname[$ival].'/admin/index.php')) {
                $opts .= getAdminOption($val, getModuleName($mname[$ival]), $conf['amod'] == $val);
            }
            $ival++;
        }
    }
    $rows .= getAdminFormRow(_PUTINAHOME.':', getAdminSelect('amod', $opts, 'sl_conf'));
    $rows .= getAdminFormWide(getAdminHrLine());
    $opts = getAdminOption('0', _CAPSEC0, $conf['gfx_chk'] == '0')
        .getAdminOption('1', _CAPSEC1, $conf['gfx_chk'] == '1')
        .getAdminOption('2', _CAPSEC2, $conf['gfx_chk'] == '2')
        .getAdminOption('3', _CAPSEC3, $conf['gfx_chk'] == '3')
        .getAdminOption('4', _CAPSEC4, $conf['gfx_chk'] == '4')
        .getAdminOption('5', _CAPSEC5, $conf['gfx_chk'] == '5')
        .getAdminOption('6', _CAPSEC6, $conf['gfx_chk'] == '6')
        .getAdminOption('7', _CAPSEC7, $conf['gfx_chk'] == '7');
    $rows .= getAdminFormRow(_CAPTCHA.':', getAdminSelect('gfx_chk', $opts, 'sl_conf'));
    $opts = '';
    $ival = 1;
    while ($ival <= 9) {
        $opts .= getAdminOption((string)$ival, '0.'.$ival, $ival == $conf['quality']);
        $ival++;
    }
    $rows .= getAdminFormRow(_CAPQUALITY.': <div class="sl_small">'._CAPQUALITYI.'</div>', getAdminSelect('quality', $opts, 'sl_conf'));
    $rows .= getAdminFormRow(_CAPKEY.': <div class="sl_small">'._CAPKEYI.'</div>', getAdminTextInput('capkey', (string)$conf['capkey'], 'sl_conf', 'maxlength="255" placeholder="'._CAPKEY.'"'));
    $rows .= getAdminFormRow(_CAPSECKEY.': <div class="sl_small">'._CAPKEYI.'</div>', getAdminTextInput('capsec', (string)$conf['capsec'], 'sl_conf', 'maxlength="255" placeholder="'._CAPSECKEY.'"'));
    $rows .= getAdminFormWide(getAdminHrLine());
    $rows .= getAdminFormRow(_EDITOR.':', redaktor('2', 'redaktor', 'sl_conf', $conf['redaktor'], 0));
    $list = timezone_identifiers_list();
    $name = $conf['gtime'] ?? '';
    $opts = '';
    foreach ($list as $val) {
        $opts .= getAdminOption($val, $val, $name === $val);
    }
    $rows .= getAdminFormRow(_GTIME.':', getAdminSelect('gtime', $opts, 'sl_conf'));
    $vars = explode(',', $conf['variables']);
    $vals = [_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.': POST', _AVARIABLES.': GET', _AVARIABLES.': COOKIE', _AVARIABLES.': FILES', _AVARIABLES.': SESSION', _AVARIABLES.': SERVER', _AQUERY_DB.': MySQL'];
    $opts = '';
    foreach ($vals as $key => $val) {
        if ($val != '') {
            $opts .= getAdminOption((string)$key, $val, !empty($vars[$key]));
        }
    }
    $rows .= getAdminFormRow(getAdminHintLabel(_VARIABLES, _CTRLINFO), getAdminSelect('variables[]', $opts, 'sl_conf', 'multiple="multiple"'));
    $rows .= getAdminFormRow(_VAR_VIEW.':', getAdminSelect('var_view',
        getAdminOption('0', _MVADMIN, $conf['var_view'] == '0')
        .getAdminOption('1', _MVALL, $conf['var_view'] == '1'),
        'sl_conf'));
    $rows .= getAdminFormRow(_SYNTAX.':', getAdminSelect('syntax',
        getAdminOption('0', _SYNTAXP, $conf['syntax'] == '0')
        .getAdminOption('1', _SYNTAXPN, $conf['syntax'] == '1')
        .getAdminOption('2', _SYNTAXSH, $conf['syntax'] == '2'),
        'sl_conf'));
    $rows .= getAdminFormRow(_ADMCOL.':', getAdminNumberInput('admcol', (string)$conf['admcol'], 'sl_conf', 'placeholder="'._ADMCOL.'" required'));
    $rows .= getAdminFormRow(_DB_SYNC, radio_form($conf['dbsync'], 'dbsync'));
    $rows .= getAdminFormRow(_SESSION, radio_form($conf['session'], 'session'));
    $rows .= getAdminFormRow(_MESSAGE_BOX, radio_form($conf['message'], 'message'));
    $rows .= getAdminFormRow(_TIME_DB, radio_form($conf['db_t'], 'db_t'));
    $rows .= getAdminFormRow(_ADMINFOEDIT, radio_form($conf['adminfo'], 'adminfo'));
    $rows .= getAdminFormRow(_SITE_CLOSE, radio_form($conf['close'], 'close'));
    $rows .= getAdminFormRow(_DEVMODE, radio_form($conf['dev_mode'] ?? 0, 'dev_mode'));
    $taba = getAdminTabContent(getAdminTabName('config', 0), getAdminRowsTable($rows));

    $rows = '';
    $rows .= getAdminFormRow(_DEFIS.':', getAdminTextInput('defis', urldecode($conf['defis']), 'sl_conf', 'maxlength="255" placeholder="'._DEFIS.'" required'));
    $rows .= getAdminFormRow(_DLETTER.':', getAdminNumberInput('dletter', (string)$conf['dletter'], 'sl_conf', 'placeholder="'._DLETTER.'" required'));
    $rows .= getAdminFormRow(_LTITLE, radio_form($conf['ltitle'], 'ltitle'));
    $rows .= getAdminFormRow(_ADESC, radio_form($conf['adesc'], 'adesc'));
    $rows .= getAdminFormWide(getAdminHrLine());
    $rows .= getAdminFormRow(_RSEP.':', getAdminTextInput('sep', urldecode($conf['sep']), 'sl_conf', 'maxlength="255" placeholder="'._RSEP.'" required'));
    $rows .= getAdminFormRow(_TSEP.':', getAdminTextInput('tsep', urldecode($conf['tsep']), 'sl_conf', 'maxlength="255" placeholder="'._TSEP.'" required'));
    $rows .= getAdminFormRow(_REWRITE_MOD, radio_form($conf['rewrite'], 'rewrite'));
    $rows .= getAdminFormRow(_SEOTITLE, radio_form($conf['title'] ?? 1, 'title'));
    $rows .= getAdminFormRow(_SEOCTITLE, radio_form($conf['ctitle'] ?? 1, 'ctitle'));
    $rows .= getAdminFormWide(getAdminHrLine());
    $rows .= getAdminFormRow(_OGRAPH, radio_form($conf['agraph'] ?? 1, 'agraph'));
    $rows .= getAdminFormRow(_OGRAPHT.'<div class="sl_small">'._TPLVARS.'</div>', getAdminTextarea('graph', $conf['graph'] ?? '', 'sl_conf', 'placeholder="'._OGRAPHT.'"', 65, 8));
    $rows .= getAdminFormRow(_SCHEMA, radio_form($conf['aschema'] ?? 1, 'aschema'));
    $rows .= getAdminFormRow(_SCHEMAT.'<div class="sl_small">'._TPLVARS.'</div>', getAdminTextarea('schema', $conf['schema'] ?? '', 'sl_conf', 'placeholder="'._SCHEMAT.'"', 65, 15));
    $tabb = getAdminTabContent(getAdminTabName('config', 1), getAdminRowsTable($rows));

    $list = is_dir('lang') ? scandir('lang') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (preg_match('/^(.+)\.php/', $file, $matches)) {
                $name = $matches[1];
                $opts .= getAdminOption($name, getLangName($name), $conf['language'] == $name);
            }
        }
    }
    $rows = '';
    $rows .= getAdminFormRow(_SELLANGUAGE.':', getAdminSelect('language', $opts, 'sl_conf'));
    $rows .= getAdminFormRow(_ACTMULTILINGUAL, radio_form($conf['multilingual'], 'multilingual'));
    $rows .= getAdminFormRow(_ACTUSEFLAGS, radio_form($conf['flags'], 'flags'));
    $rows .= getAdminFormRow(_GEO_IP, radio_form($conf['geo_ip'], 'geo_ip'));
    $rows .= getAdminFormRow(_ACTUSELANG, radio_form($conf['alang'], 'alang'));
    $tabc = getAdminTabContent(getAdminTabName('config', 2), getAdminRowsTable($rows));

    $rows = '';
    $rows .= getAdminFormRow(_CENSORMODE.':', getAdminSelect('censor',
        getAdminOption('0', _NO, $conf['censor'] == 0)
        .getAdminOption('1', _MATCHANY, $conf['censor'] == 1),
        'sl_conf'));
    $rows .= getAdminFormRow(_CENSORREPLACE.':', getAdminTextInput('censor_r', (string)$conf['censor_r'], 'sl_conf', 'maxlength="10" placeholder="'._CENSORREPLACE.'" required'));
    $rows .= getAdminFormRow(getAdminHintLabel(_CENSOR, _NOKOMA), getAdminTextarea('censor_l', $conf['censor_l'], 'sl_conf', 'placeholder="'._CENSOR.'" required'));
    $rows .= getAdminFormRow(_CLICABLE.'<div class="sl_small">'._CLICABLEINFO.'</div>', radio_form($conf['clickable'], 'clickable'));
    $tabd = getAdminTabContent(getAdminTabName('config', 3), getAdminRowsTable($rows));

    $rows = '';
    $rows .= getAdminFormRow(getAdminHintLabel(_BOTSLIST, _NOKOMA.' '._BOTSINFO), getAdminTextarea('bots', $conf['bots'], 'sl_conf', 'placeholder="'._BOTSLIST.'" required', 65, 10));
    $rows .= getAdminFormRow(getAdminHintLabel(_BOTSSITE, _NOKOMA), getAdminTextarea('fbots', $conf['fbots'], 'sl_conf', 'placeholder="'._BOTSSITE.'" required', 65, 10));
    $rows .= getAdminFormRow(_BOTSACT, radio_form($conf['botsact'], 'botsact'));
    $tabe = getAdminTabContent(getAdminTabName('config', 4), getAdminRowsTable($rows));

    $ival = 0;
    $name = 0;
    foreach (glob('config/cache/*.txt') as $file) {
        $name += filesize($file);
        $ival++;
    }
    $rows = '';
    $rows .= getAdminFormRow(_CACHE.':', getAdminSelect('cache',
        getAdminOption('0', _NO, $conf['cache'] == 0)
        .getAdminOption('1', _CACHE_1, $conf['cache'] == 1)
        .getAdminOption('2', _CACHE_2, $conf['cache'] == 2),
        'sl_conf'));
    $rows .= getAdminFormRow(_CACHETIME.':', getAdminNumberInput('cache_t', (string)$conf['cache_t'], 'sl_conf', 'placeholder="'._CACHETIME.'" required'));
    $rows .= getAdminFormRow(_CACHEDEL.':', getAdminNumberInput('cache_d', (string)$conf['cache_d'], 'sl_conf', 'placeholder="'._CACHEDEL.'" required'));
    $rows .= getAdminFormRow(_CACHECOMP, radio_form($conf['cache_c'], 'cache_c'));
    $rows .= getAdminFormRow(_CACHEBROW, radio_form($conf['cache_b'], 'cache_b'));
    $rows .= getAdminFormWide(getAdminHrLine(), '', 'sl_center');
    $rows .= getAdminFormRow(_CACHECSS, radio_form($conf['cache_css'], 'cache_css'));
    $rows .= getAdminFormRow(getAdminHintLabel(_CSSDIR, _CSSDIRINFO.' '._NOKOMA), getAdminTextarea('css_f', $conf['css_f'], 'sl_conf', 'placeholder="'._CSSDIRINFO.'" required'));
    $rows .= getAdminFormRow(_CSSHEAD, radio_form($conf['css_h'], 'css_h'));
    $rows .= getAdminFormRow(_CSSCOMP, radio_form($conf['css_c'], 'css_c'));
    $rows .= getAdminFormRow(_CSSENC, radio_form($conf['css_e'], 'css_e'));
    $rows .= getAdminFormWide(getAdminHrLine(), '', 'sl_center');
    $rows .= getAdminFormRow(_CACHESCRIPT, radio_form($conf['cache_script'], 'cache_script'));
    $rows .= getAdminFormRow(getAdminHintLabel(_SCRIPTFILE, _SCRIPTFILEINFO.' '._NOKOMA), getAdminTextarea('script_f', $conf['script_f'], 'sl_conf', 'placeholder="'._SCRIPTFILEINFO.'" required'));
    $rows .= getAdminFormRow(_SCRIPTHEAD, radio_form($conf['script_h'], 'script_h'));
    $rows .= getAdminFormRow(_SCRIPTCOMP, radio_form($conf['script_c'], 'script_c'));
    $rows .= getAdminFormRow(_SCRIPTASIN, radio_form($conf['script_a'], 'script_a'));
    $rows .= getAdminFormRow(_SCRIPTBOT, radio_form($conf['script_b'], 'script_b'));
    $html = $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _DIR.': config/cache<br>'._FILE_M.': '.$ival.'<br>'._FILE_S.': '.filterSize($name)]);
    $tabf = getAdminTabContent(getAdminTabName('config', 5), $html.getAdminRowsTable($rows));

    $rows = '';
    $rows .= getAdminFormRow(getAdminHintLabel(_MAILTEMP, _MAILTEMPINFO), getAdminTextarea('mtemp', $conf['mtemp'], 'sl_conf', 'placeholder="'._MAILTEMP.'" required', 65, 10));
    $tabg = getAdminTabContent(getAdminTabName('config', 6), getAdminRowsTable($rows));

    $content = $taba.$tabb.$tabc.$tabd.$tabe.$tabf.$tabg.getAdminTabsSetup('config');
    echo $cont.getAdminBox(getAdminConfSave($content, 'config', 'save'));
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
        'script_f' => strtr(getVar('post', 'script_f', 'text', 'plugins/system/global-func.js,plugins/system/tablesort.min.js,plugins/jquery/jquery.js,plugins/jquery/ui/jquery-ui.js,plugins/jquery/jquery.cookie.js,plugins/fancybox/jquery.mousewheel.js,plugins/fancybox/jquery.fancybox.js,plugins/jquery/jquery.slaed.js'), $kprotect),
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
    $cont = setAdminNavi(['ops' => ['name=config', 'name=config', 'name=config', 'name=config', 'name=config&amp;op=show', 'name=config', 'name=config', 'name=config&amp;op=info'], 'tabs' => [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO], 'tab' => 8]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
