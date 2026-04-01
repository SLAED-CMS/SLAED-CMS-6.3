<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminNavi(['ops' => ['', '', '', '', '', '', '', 'name=config&amp;op=info'], 'tabs' => [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO], 'id' => 'config']);
    $cont .= checkPerms(CONFIG_DIR.'/global.php');
    $rows = [];
    $rows[] = ['label_html' => _VERSION.':', 'field_html' => getTplExternalAction('//slaed.net', _VERSION, 'SLAED CMS '.$conf['version'])];
    $rows[] = ['label_html' => _SITENAME.':', 'field_html' => getTplTextInput('sitename', (string)$conf['sitename'], 'sl_conf', 'maxlength="255" placeholder="'._SITENAME.'" required')];
    $rows[] = ['label_html' => _SITEURL.':', 'field_html' => getTplUrlInput('homeurl', $conf['homeurl'], 'sl_conf', 'maxlength="255" placeholder="'._SITEURL.'" required')];
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $list = is_dir($path) ? scandir($path) : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $opts .= getTplOption($path.$entry, $entry, $conf['site_logo'] == $entry);
            }
        }
    }
    $rows[] = ['label_html' => _LOGO.':', 'field_html' => getTplSelect('site_logo', $opts, 'sl_conf', 'id="img_replace"')];
    $rows[] = ['label_html' => _SITELOGO.':', 'field_html' => getTplImagePreview($path.$conf['site_logo'], _SITELOGO)];
    $rows[] = ['label_html' => _DESCRIPTION.':', 'field_html' => getTplTextarea('slogan', $conf['slogan'], 'sl_conf', 'placeholder="'._DESCRIPTION.'" required')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_ADMININFO, _ADMININFODES), 'field_html' => getTplTextarea('admininfo', $conf['admininfo'], 'sl_conf', 'placeholder="'._ADMININFO.'"')];
    $rows[] = ['label_html' => _STARTDATE.':', 'field_html' => datetime(1, 'startdate', $conf['startdate'], 16, 'sl_conf')];
    $rows[] = ['label_html' => _ADMINEMAIL.':', 'field_html' => getTplEmailInput('adminmail', $conf['adminmail'], 'sl_conf', 'maxlength="255" placeholder="'._ADMINEMAIL.'" required')];
    $rows[] = ['label_html' => _USER_COOKIE.':', 'field_html' => getTplTextInput('user_c', (string)$conf['user_c'], 'sl_conf', 'maxlength="255" placeholder="'._USER_COOKIE.'" required')];
    $rows[] = ['label_html' => _ADMIN_SESSION.':', 'field_html' => getTplTextInput('admin_c', (string)$conf['admin_c'], 'sl_conf', 'maxlength="255" placeholder="'._ADMIN_SESSION.'" required')];
    $rows[] = ['label_html' => _USER_COOKIE_T.':', 'field_html' => getTplNumberInput('user_c_t', (string)intval($conf['user_c_t'] / 86400), 'sl_conf', 'placeholder="'._USER_COOKIE_T.'" required')];
    $rows[] = ['label_html' => _SESS_T.':', 'field_html' => getTplNumberInput('sess_t', (string)intval($conf['sess_t'] / 60), 'sl_conf', 'placeholder="'._SESS_T.'" required')];
    $rows[] = ['label_html' => _IP_LINK.':', 'field_html' => getTplUrlInput('ip_link', $conf['ip_link'], 'sl_conf', 'maxlength="255" placeholder="'._IP_LINK.'" required')];
    $list = is_dir('templates') ? scandir('templates') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $opts .= getTplOption($file, $file, $file == $conf['theme']);
            }
        }
    }
    $rows[] = ['label_html' => _THEME.':', 'field_html' => getTplSelect('theme', $opts, 'sl_conf')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_PUTINHOME, _PUTINHOMEINFO.' '._CTRLINFO), 'field_html' => modul('module', 'sl_conf', $conf['module'], 1)];
    $mods = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'page', 'shop_clients', 'voting'];
    $mname = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'pages', 'shop', 'voting'];
    $ival = 0;
    $opts = '';
    foreach ($mods as $val) {
        if ($val != '') {
            if (file_exists('modules/'.$mname[$ival].'/admin/index.php')) {
                $opts .= getTplOption($val, getModuleName($mname[$ival]), $conf['amod'] == $val);
            }
            $ival++;
        }
    }
    $rows[] = ['label_html' => _PUTINAHOME.':', 'field_html' => getTplSelect('amod', $opts, 'sl_conf')];
    $rows[] = ['content_html' => getTplHrLine()];
    $opts = getTplOption('0', _CAPSEC0, $conf['gfx_chk'] == '0')
        .getTplOption('1', _CAPSEC1, $conf['gfx_chk'] == '1')
        .getTplOption('2', _CAPSEC2, $conf['gfx_chk'] == '2')
        .getTplOption('3', _CAPSEC3, $conf['gfx_chk'] == '3')
        .getTplOption('4', _CAPSEC4, $conf['gfx_chk'] == '4')
        .getTplOption('5', _CAPSEC5, $conf['gfx_chk'] == '5')
        .getTplOption('6', _CAPSEC6, $conf['gfx_chk'] == '6')
        .getTplOption('7', _CAPSEC7, $conf['gfx_chk'] == '7');
    $rows[] = ['label_html' => _CAPTCHA.':', 'field_html' => getTplSelect('gfx_chk', $opts, 'sl_conf')];
    $opts = '';
    $ival = 1;
    while ($ival <= 9) {
        $opts .= getTplOption((string)$ival, '0.'.$ival, $ival == $conf['quality']);
        $ival++;
    }
    $rows[] = ['label_html' => getTplAdminHintLabel(_CAPQUALITY, _CAPQUALITYI), 'field_html' => getTplSelect('quality', $opts, 'sl_conf')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_CAPKEY, _CAPKEYI), 'field_html' => getTplTextInput('capkey', (string)$conf['capkey'], 'sl_conf', 'maxlength="255" placeholder="'._CAPKEY.'"')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_CAPSECKEY, _CAPKEYI), 'field_html' => getTplTextInput('capsec', (string)$conf['capsec'], 'sl_conf', 'maxlength="255" placeholder="'._CAPSECKEY.'"')];
    $rows[] = ['content_html' => getTplHrLine()];
    $rows[] = ['label_html' => _EDITOR.':', 'field_html' => redaktor('2', 'redaktor', 'sl_conf', $conf['redaktor'], 0)];
    $list = timezone_identifiers_list();
    $name = $conf['gtime'] ?? '';
    $opts = '';
    foreach ($list as $val) {
        $opts .= getTplOption($val, $val, $name === $val);
    }
    $rows[] = ['label_html' => _GTIME.':', 'field_html' => getTplSelect('gtime', $opts, 'sl_conf')];
    $vars = explode(',', $conf['variables']);
    $vals = [_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.': POST', _AVARIABLES.': GET', _AVARIABLES.': COOKIE', _AVARIABLES.': FILES', _AVARIABLES.': SESSION', _AVARIABLES.': SERVER', _AQUERY_DB.': MySQL'];
    $opts = '';
    foreach ($vals as $key => $val) {
        if ($val != '') {
            $opts .= getTplOption((string)$key, $val, !empty($vars[$key]));
        }
    }
    $rows[] = ['label_html' => getTplAdminHintLabel(_VARIABLES, _CTRLINFO), 'field_html' => getTplSelect('variables[]', $opts, 'sl_conf', 'multiple="multiple"')];
    $rows[] = ['label_html' => _VAR_VIEW.':', 'field_html' => getTplSelect('var_view',
        getTplOption('0', _MVADMIN, $conf['var_view'] == '0')
        .getTplOption('1', _MVALL, $conf['var_view'] == '1'),
        'sl_conf')];
    $rows[] = ['label_html' => _SYNTAX.':', 'field_html' => getTplSelect('syntax',
        getTplOption('0', _SYNTAXP, $conf['syntax'] == '0')
        .getTplOption('1', _SYNTAXPN, $conf['syntax'] == '1')
        .getTplOption('2', _SYNTAXSH, $conf['syntax'] == '2'),
        'sl_conf')];
    $rows[] = ['label_html' => _ADMCOL.':', 'field_html' => getTplNumberInput('admcol', (string)$conf['admcol'], 'sl_conf', 'placeholder="'._ADMCOL.'" required')];
    $rows[] = ['label_html' => _DB_SYNC, 'field_html' => radio_form($conf['dbsync'], 'dbsync')];
    $rows[] = ['label_html' => _SESSION, 'field_html' => radio_form($conf['session'], 'session')];
    $rows[] = ['label_html' => _MESSAGE_BOX, 'field_html' => radio_form($conf['message'], 'message')];
    $rows[] = ['label_html' => _TIME_DB, 'field_html' => radio_form($conf['db_t'], 'db_t')];
    $rows[] = ['label_html' => _ADMINFOEDIT, 'field_html' => radio_form($conf['adminfo'], 'adminfo')];
    $rows[] = ['label_html' => _SITE_CLOSE, 'field_html' => radio_form($conf['close'], 'close')];
    $rows[] = ['label_html' => _DEVMODE, 'field_html' => radio_form($conf['dev_mode'] ?? 0, 'dev_mode')];
    $taba = getTplAdminTabContent(getTplAdminTabName('config', 0), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $rows = [];
    $rows[] = ['label_html' => _DEFIS.':', 'field_html' => getTplTextInput('defis', urldecode($conf['defis']), 'sl_conf', 'maxlength="255" placeholder="'._DEFIS.'" required')];
    $rows[] = ['label_html' => _DLETTER.':', 'field_html' => getTplNumberInput('dletter', (string)$conf['dletter'], 'sl_conf', 'placeholder="'._DLETTER.'" required')];
    $rows[] = ['label_html' => _LTITLE, 'field_html' => radio_form($conf['ltitle'], 'ltitle')];
    $rows[] = ['label_html' => _ADESC, 'field_html' => radio_form($conf['adesc'], 'adesc')];
    $rows[] = ['content_html' => getTplHrLine()];
    $rows[] = ['label_html' => _RSEP.':', 'field_html' => getTplTextInput('sep', urldecode($conf['sep']), 'sl_conf', 'maxlength="255" placeholder="'._RSEP.'" required')];
    $rows[] = ['label_html' => _TSEP.':', 'field_html' => getTplTextInput('tsep', urldecode($conf['tsep']), 'sl_conf', 'maxlength="255" placeholder="'._TSEP.'" required')];
    $rows[] = ['label_html' => _REWRITE_MOD, 'field_html' => radio_form($conf['rewrite'], 'rewrite')];
    $rows[] = ['label_html' => _SEOTITLE, 'field_html' => radio_form($conf['title'] ?? 1, 'title')];
    $rows[] = ['label_html' => _SEOCTITLE, 'field_html' => radio_form($conf['ctitle'] ?? 1, 'ctitle')];
    $rows[] = ['content_html' => getTplHrLine()];
    $rows[] = ['label_html' => _OGRAPH, 'field_html' => radio_form($conf['agraph'] ?? 1, 'agraph')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_OGRAPHT, _TPLVARS), 'field_html' => getTplTextarea('graph', $conf['graph'] ?? '', 'sl_conf', 'placeholder="'._OGRAPHT.'"', 65, 8)];
    $rows[] = ['label_html' => _SCHEMA, 'field_html' => radio_form($conf['aschema'] ?? 1, 'aschema')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_SCHEMAT, _TPLVARS), 'field_html' => getTplTextarea('schema', $conf['schema'] ?? '', 'sl_conf', 'placeholder="'._SCHEMAT.'"', 65, 15)];
    $tabb = getTplAdminTabContent(getTplAdminTabName('config', 1), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $list = is_dir('lang') ? scandir('lang') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (preg_match('/^(.+)\.php/', $file, $matches)) {
                $name = $matches[1];
                $opts .= getTplOption($name, getLangName($name), $conf['language'] == $name);
            }
        }
    }
    $rows = [];
    $rows[] = ['label_html' => _SELLANGUAGE.':', 'field_html' => getTplSelect('language', $opts, 'sl_conf')];
    $rows[] = ['label_html' => _ACTMULTILINGUAL, 'field_html' => radio_form($conf['multilingual'], 'multilingual')];
    $rows[] = ['label_html' => _ACTUSEFLAGS, 'field_html' => radio_form($conf['flags'], 'flags')];
    $rows[] = ['label_html' => _GEO_IP, 'field_html' => radio_form($conf['geo_ip'], 'geo_ip')];
    $rows[] = ['label_html' => _ACTUSELANG, 'field_html' => radio_form($conf['alang'], 'alang')];
    $tabc = getTplAdminTabContent(getTplAdminTabName('config', 2), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $rows = [];
    $rows[] = ['label_html' => _CENSORMODE.':', 'field_html' => getTplSelect('censor',
        getTplOption('0', _NO, $conf['censor'] == 0)
        .getTplOption('1', _MATCHANY, $conf['censor'] == 1),
        'sl_conf')];
    $rows[] = ['label_html' => _CENSORREPLACE.':', 'field_html' => getTplTextInput('censor_r', (string)$conf['censor_r'], 'sl_conf', 'maxlength="10" placeholder="'._CENSORREPLACE.'" required')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_CENSOR, _NOKOMA), 'field_html' => getTplTextarea('censor_l', $conf['censor_l'], 'sl_conf', 'placeholder="'._CENSOR.'" required')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_CLICABLE, _CLICABLEINFO), 'field_html' => radio_form($conf['clickable'], 'clickable')];
    $tabd = getTplAdminTabContent(getTplAdminTabName('config', 3), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $rows = [];
    $rows[] = ['label_html' => getTplAdminHintLabel(_BOTSLIST, _NOKOMA.' '._BOTSINFO), 'field_html' => getTplTextarea('bots', $conf['bots'], 'sl_conf', 'placeholder="'._BOTSLIST.'" required', 65, 10)];
    $rows[] = ['label_html' => getTplAdminHintLabel(_BOTSSITE, _NOKOMA), 'field_html' => getTplTextarea('fbots', $conf['fbots'], 'sl_conf', 'placeholder="'._BOTSSITE.'" required', 65, 10)];
    $rows[] = ['label_html' => _BOTSACT, 'field_html' => radio_form($conf['botsact'], 'botsact')];
    $tabe = getTplAdminTabContent(getTplAdminTabName('config', 4), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $ival = 0;
    $name = 0;
    foreach (glob('config/cache/*.txt') as $file) {
        $name += filesize($file);
        $ival++;
    }
    $rows = [];
    $rows[] = ['label_html' => _CACHE.':', 'field_html' => getTplSelect('cache',
        getTplOption('0', _NO, $conf['cache'] == 0)
        .getTplOption('1', _CACHE_1, $conf['cache'] == 1)
        .getTplOption('2', _CACHE_2, $conf['cache'] == 2),
        'sl_conf')];
    $rows[] = ['label_html' => _CACHETIME.':', 'field_html' => getTplNumberInput('cache_t', (string)$conf['cache_t'], 'sl_conf', 'placeholder="'._CACHETIME.'" required')];
    $rows[] = ['label_html' => _CACHEDEL.':', 'field_html' => getTplNumberInput('cache_d', (string)$conf['cache_d'], 'sl_conf', 'placeholder="'._CACHEDEL.'" required')];
    $rows[] = ['label_html' => _CACHECOMP, 'field_html' => radio_form($conf['cache_c'], 'cache_c')];
    $rows[] = ['label_html' => _CACHEBROW, 'field_html' => radio_form($conf['cache_b'], 'cache_b')];
    $rows[] = ['content_html' => getTplHrLine(), 'cell_class' => 'sl_center'];
    $rows[] = ['label_html' => _CACHECSS, 'field_html' => radio_form($conf['cache_css'], 'cache_css')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_CSSDIR, _CSSDIRINFO.' '._NOKOMA), 'field_html' => getTplTextarea('css_f', $conf['css_f'], 'sl_conf', 'placeholder="'._CSSDIRINFO.'" required')];
    $rows[] = ['label_html' => _CSSHEAD, 'field_html' => radio_form($conf['css_h'], 'css_h')];
    $rows[] = ['label_html' => _CSSCOMP, 'field_html' => radio_form($conf['css_c'], 'css_c')];
    $rows[] = ['label_html' => _CSSENC, 'field_html' => radio_form($conf['css_e'], 'css_e')];
    $rows[] = ['content_html' => getTplHrLine(), 'cell_class' => 'sl_center'];
    $rows[] = ['label_html' => _CACHESCRIPT, 'field_html' => radio_form($conf['cache_script'], 'cache_script')];
    $rows[] = ['label_html' => getTplAdminHintLabel(_SCRIPTFILE, _SCRIPTFILEINFO.' '._NOKOMA), 'field_html' => getTplTextarea('script_f', $conf['script_f'], 'sl_conf', 'placeholder="'._SCRIPTFILEINFO.'" required')];
    $rows[] = ['label_html' => _SCRIPTHEAD, 'field_html' => radio_form($conf['script_h'], 'script_h')];
    $rows[] = ['label_html' => _SCRIPTCOMP, 'field_html' => radio_form($conf['script_c'], 'script_c')];
    $rows[] = ['label_html' => _SCRIPTASIN, 'field_html' => radio_form($conf['script_a'], 'script_a')];
    $rows[] = ['label_html' => _SCRIPTBOT, 'field_html' => radio_form($conf['script_b'], 'script_b')];
    $html = $tpl->getHtmlFrag('alert', ['type' => 'info', 'text' => _DIR.': config/cache'.getTplAdminTipLine(_FILE_M, (string)$ival).getTplAdminTipLine(_FILE_S, filterSize($name))]);
    $tabf = getTplAdminTabContent(getTplAdminTabName('config', 5), $html.$tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $rows = [];
    $rows[] = ['label_html' => getTplAdminHintLabel(_MAILTEMP, _MAILTEMPINFO), 'field_html' => getTplTextarea('mtemp', $conf['mtemp'], 'sl_conf', 'placeholder="'._MAILTEMP.'" required', 65, 10)];
    $tabg = getTplAdminTabContent(getTplAdminTabName('config', 6), $tpl->getHtmlFrag('config-div-content', ['rows' => $rows]));

    $content = $taba.$tabb.$tabc.$tabd.$tabe.$tabf.$tabg.getTplAdminTabsSetup('config');
    echo $cont.getTplBox(getTplAdminConfSave($content, 'config', 'save'));
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
        'css_f' => strtr(getVar('post', 'css_f', 'text', 'templates/[theme]/,plugins/syntaxhighlighter/styles/'), $kprotect),
        'css_h' => getVar('post', 'css_h', 'num'),
        'css_c' => getVar('post', 'css_c', 'num'),
        'css_e' => getVar('post', 'css_e', 'num'),
        'cache_script' => getVar('post', 'cache_script', 'num'),
        'script_f' => strtr(getVar('post', 'script_f', 'text', 'plugins/system/global-func.js,plugins/system/slaed.js,plugins/tablesort/tablesort.min.js'), $kprotect),
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
    $cont = getTplAdminNavi(['ops' => ['name=config', 'name=config', 'name=config', 'name=config', 'name=config&amp;op=show', 'name=config', 'name=config', 'name=config&amp;op=info'], 'tabs' => [_GENPREF, _SEO, _MULTILINGUAL, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _INFO], 'tab' => 8]);
    setAdminInfoPage($cont);
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'info': info(); break;
}
