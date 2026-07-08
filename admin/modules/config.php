<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# Render GeoIP status label
function getGeoipBadge(bool $found): string {
    global $tpl;
    return $tpl->getHtmlFrag('inline-badge', [
        'is_success' => $found,
        'is_danger' => !$found,
        'label' => $found ? _FOUND : _NOTFOUND,
    ]);
}

# Render one GeoIP database status row
function getGeoipFileRow(string $label, string $file): string {
    global $tpl;
    $info = Geoip::getFileInfo($file);
    $size = $info['found'] ? filterSize($info['size']) : _NO;
    $date = $info['found'] ? date(_TIMESTRING, (int)$info['mtime']) : _NO;
    return $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
        'cells' => [
            ['content_html' => $label],
            ['is_col_status' => true, 'content_html' => getGeoipBadge((bool)$info['found'])],
            ['content_html' => $size],
            ['content_html' => $date],
            ['content_html' => (string)$info['path']],
        ],
    ])]);
}

# Render GeoIP result rows for test IP
function getGeoipRows(string $test): string {
    global $tpl;
    $res = filter_var($test, FILTER_VALIDATE_IP) ? Geoip::getInfo($test) : Geoip::getEmpty();
    $rows = [
        [_COUNTRY, (string)$res['country']],
        [_GEOIP_COUNTRYNAME, (string)$res['country_name']],
        [_GEOIP_CONTINENT, (string)$res['continent']],
        [_GEOIP_ASN, (string)$res['asn']],
        [_GEOIP_ORG, (string)$res['organization']],
        [_GEOIP_PROVIDER, (string)$res['provider']],
        [_STATUS, (string)$res['status']],
    ];
    $html = '';
    foreach ($rows as $row) {
        $html .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $row[0]],
                ['content_html' => $row[1] !== '' && $row[1] !== '0' ? $row[1] : _NO],
            ],
        ])]);
    }
    return $html;
}

# Render GeoIP settings and database status
function getGeoipPanel(): string {
    global $conf, $tpl;
    $test = getVar('req', 'testip', 'text', (string)($conf['geoip_test'] ?? ''));
    $rows = [
        ['label_html' => _GEO_IP, 'field_html' => getTplRadioGroup(['name' => 'geoipenabled', 'value' => (string)(int)($conf['geoip_enabled'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _GEOIP_CACHE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'geoipcache', 'value_attr' => (string)($conf['geoip_cache'] ?? 86400), 'is_config' => true])],
        ['label_html' => _GEOIP_ANON, 'field_html' => getTplRadioGroup(['name' => 'geoipanon', 'value' => (string)(int)($conf['geoip_anon'] ?? 1), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _GEOIP_STORE, 'field_html' => getTplRadioGroup(['name' => 'geoipstore', 'value' => (string)(int)($conf['geoip_store'] ?? 0), 'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]]])],
        ['label_html' => _GEOIP_TESTIP, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'testip', 'value_attr' => $test, 'is_config' => true])],
    ];
    $head = [
        ['content' => _DATABASE],
        ['content' => _STATUS],
        ['content' => _SIZE],
        ['content' => _DATE],
        ['content' => _ADIR],
    ];
    $body = getGeoipFileRow(_GEOIP_COUNTRYDB, (string)($conf['geoip_country'] ?? 'storage/geoip/country.mmdb'));
    $body .= getGeoipFileRow(_GEOIP_ASNDB, (string)($conf['geoip_asn'] ?? 'storage/geoip/asn.mmdb'));
    $stat = $tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $body]);
    $head = [
        ['content' => _PARAMETERS],
        ['content' => _VALUE],
    ];
    $info = $tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => getGeoipRows($test)]);
    return $tpl->getHtmlPart('div', ['rows' => $rows]).$stat.$info;
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ctab = getVar('get', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 6) $ctab = 0;
    $links = [];
    foreach ([_GENPREF, _SEO, _MULTILINGUAL.' / '._GEOLOCATION, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT] as $idx => $label) {
        $links[] = [
            'href' => '#',
            'is_active' => $ctab === $idx,
            'label' => $label,
            'rel' => 'config-panel-'.$idx,
            'title' => $label,
        ];
    }
    $links[] = [
        'href' => $afile.'.php?name=config&op=info&tab='.$ctab,
        'label' => _DOCS,
        'link_attr' => 'data-sl-tab-info-link="config-main"',
        'title' => _DOCS,
    ];
    $cont = getTplAdminTabs([
        'is_runtime' => true,
        'links' => $links,
        'tabs_id' => 'config-main',
        'tabs_index' => $ctab,
        'tabs_sync_selector' => 'input[name="tab"]',
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/global.php');
    $rows = [];
    $yesno = [
        ['value' => '1', 'label' => _YES],
        ['value' => '0', 'label' => _NO],
    ];
    $rows[] = ['label_html' => _VERSION, 'field_html' => $tpl->getHtmlFrag('link', ['href' => '//slaed.net', 'title' => _VERSION, 'label' => 'SLAED CMS '.$conf['version'], 'is_blank' => true])];
    $rows[] = ['label_html' => _SITENAME, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'sitename',
        'value_attr' => (string)$conf['sitename'],
        'maxlength_num' => 255,
        'placeholder_text' => _SITENAME,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _SITEURL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'url',
        'name_attr' => 'homeurl',
        'value_attr' => (string)$conf['homeurl'],
        'maxlength_num' => 255,
        'placeholder_text' => _SITEURL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $dir = BASE_DIR.'/'.$path;
    $list = is_dir($dir) ? scandir($dir) : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $path.$entry,
                    'label_text' => $entry,
                    'is_selected' => $conf['site_logo'] == $entry,
                ]);
            }
        }
    }
    $rows[] = ['label_html' => _LOGO, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'site_logo',
        'options_html' => $opts,
        'is_config' => true,
        'selectid' => 'img_replace',
        'imgtar' => 'picture',
    ])];
    $rows[] = ['label_html' => _SITELOGO, 'field_html' => $tpl->getHtmlFrag('image-preview', [
        'src_attr' => $path.$conf['site_logo'],
        'image_id' => 'picture',
        'alt_text' => _SITELOGO,
        'title_text' => _SITELOGO,
        'is_popup' => true,
    ])];
    $path = 'templates/admin/images/logos/';
    $dir = BASE_DIR.'/'.$path;
    $adlogo = $conf['admin_logo'] ?? 'slaed_logo_256x73.png';
    if (!is_file(BASE_DIR.'/'.$path.$adlogo)) $adlogo = 'slaed_logo_256x73.png';
    $list = is_dir($dir) ? scandir($dir) : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $entry) {
            if (preg_match('/(\.gif|\.png|\.jpg|\.jpeg|\.svg)$/is', $entry) && $entry !== '.' && $entry !== '..') {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $path.$entry,
                    'label_text' => $entry,
                    'is_selected' => $adlogo == $entry,
                ]);
            }
        }
    }
    $rows[] = ['label_html' => _ADMINLOGO, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'admin_logo',
        'options_html' => $opts,
        'is_config' => true,
        'selectid' => 'admin_img_replace',
        'imgtar' => 'admin_picture',
    ])];
    $rows[] = ['label_html' => _ADMINLOGOP, 'field_html' => $tpl->getHtmlFrag('image-preview', [
        'src_attr' => $path.$adlogo,
        'image_id' => 'admin_picture',
        'alt_text' => _ADMINLOGOP,
        'title_text' => _ADMINLOGOP,
        'is_popup' => true,
    ])];
    $rows[] = ['label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'slogan',
        'value_text' => (string)$conf['slogan'],
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _ADMININFO, 'hint' => _ADMININFODES]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'admininfo',
        'value_text' => (string)$conf['admininfo'],
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _STARTDATE, 'field_html' => getTplAddDateTime(['name' => 'startdate', 'time' => (string)$conf['startdate'], 'with' => true, 'max' => 16, 'is_config' => true])];
    $rows[] = ['label_html' => _ADMINEMAIL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'email',
        'name_attr' => 'adminmail',
        'value_attr' => (string)$conf['adminmail'],
        'maxlength_num' => 255,
        'placeholder_text' => _ADMINEMAIL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _USER_COOKIE, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'user_c',
        'value_attr' => (string)$conf['user_c'],
        'maxlength_num' => 255,
        'placeholder_text' => _USER_COOKIE,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _ADMIN_SESSION, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'admin_c',
        'value_attr' => (string)$conf['admin_c'],
        'maxlength_num' => 255,
        'placeholder_text' => _ADMIN_SESSION,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _USER_COOKIE_T, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'user_c_t',
        'value_attr' => (string)intval($conf['user_c_t'] / 86400),
        'placeholder_text' => _USER_COOKIE_T,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _SESS_T, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'sess_t',
        'value_attr' => (string)intval($conf['sess_t'] / 60),
        'placeholder_text' => _SESS_T,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _IP_LINK, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'url',
        'name_attr' => 'ip_link',
        'value_attr' => (string)$conf['ip_link'],
        'maxlength_num' => 255,
        'placeholder_text' => _IP_LINK,
        'is_required' => true,
        'is_config' => true,
    ])];
    $list = is_dir(BASE_DIR.'/templates') ? scandir(BASE_DIR.'/templates') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin') {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $file,
                    'is_selected' => $file == $conf['theme'],
                ]);
            }
        }
    }
    $rows[] = ['label_html' => _THEME, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'theme',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _PUTINHOME, 'hint' => _PUTINHOMEINFO.' '._CTRLINFO]), 'field_html' => getTplModuleSelect('module', $conf['module'], 1)];
    $mods = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'page', 'shop_clients', 'voting'];
    $mname = ['auto_links', 'faq', 'files', 'links', 'media', 'news', 'order', 'pages', 'shop', 'voting'];
    $ival = 0;
    $opts = '';
    foreach ($mods as $val) {
        if ($val != '') {
            if (file_exists('modules/'.$mname[$ival].'/admin/index.php')) {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $val,
                    'label_text' => getModuleName($mname[$ival]),
                    'is_selected' => $conf['amod'] == $val,
                ]);
            }
            $ival++;
        }
    }
    $rows[] = ['label_html' => _PUTINAHOME, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'amod',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _EDITORUSER, 'field_html' => Editor::getSelect('editor_user', (string)($conf['editor']['user'] ?? 'plain'), 'content', 'user')];
    $list = timezone_identifiers_list();
    $name = $conf['gtime'] ?? '';
    $opts = '';
    foreach ($list as $val) {
        $opts .= $tpl->getHtmlFrag('select-option', [
            'value_attr' => $val,
            'label_text' => $val,
            'is_selected' => $name === $val,
        ]);
    }
    $rows[] = ['label_html' => _GTIME, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'gtime',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $vars = explode(',', $conf['variables']);
    $vals = [_DEACTIVATE, _SYSTEM_INFO, _AVARIABLES.': POST', _AVARIABLES.': GET', _AVARIABLES.': COOKIE', _AVARIABLES.': FILES', _AVARIABLES.': SESSION', _AVARIABLES.': SERVER', _AQUERY_DB.': MySQL'];
    $opts = '';
    foreach ($vals as $key => $val) {
        if ($val != '') {
            $opts .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => (string)$key,
                'label_text' => $val,
                'is_selected' => !empty($vars[$key]),
            ]);
        }
    }
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _VARIABLES, 'hint' => _CTRLINFO]), 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'variables[]',
        'options_html' => $opts,
        'is_config' => true,
        'select_attr' => 'multiple="multiple"',
    ])];
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '0',
        'label_text' => _MVADMIN,
        'is_selected' => $conf['var_view'] == '0',
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '1',
        'label_text' => _MVALL,
        'is_selected' => $conf['var_view'] == '1',
    ]);
    $rows[] = ['label_html' => _VAR_VIEW, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'var_view',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '0',
        'label_text' => _SYNTAXP,
        'is_selected' => $conf['syntax'] == '0',
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '1',
        'label_text' => _SYNTAXPN,
        'is_selected' => $conf['syntax'] == '1',
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '2',
        'label_text' => _SYNTAXSH,
        'is_selected' => $conf['syntax'] == '2',
    ]);
    $rows[] = ['label_html' => _SYNTAX, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'syntax',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _ADMCOL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'admcol',
        'value_attr' => (string)$conf['admcol'],
        'placeholder_text' => _ADMCOL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _DB_SYNC, 'field_html' => getTplRadioGroup(['name' => 'dbsync', 'value' => $conf['dbsync'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SESSION, 'field_html' => getTplRadioGroup(['name' => 'session', 'value' => $conf['session'], 'options' => $yesno])];
    $rows[] = ['label_html' => _MESSAGE_BOX, 'field_html' => getTplRadioGroup(['name' => 'message', 'value' => $conf['message'], 'options' => $yesno])];
    $rows[] = ['label_html' => _TIME_DB, 'field_html' => getTplRadioGroup(['name' => 'db_t', 'value' => $conf['db_t'], 'options' => $yesno])];
    $rows[] = ['label_html' => _ADMINFOEDIT, 'field_html' => getTplRadioGroup(['name' => 'adminfo', 'value' => $conf['adminfo'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SITE_CLOSE, 'field_html' => getTplRadioGroup(['name' => 'close', 'value' => $conf['close'], 'options' => $yesno])];
    $rows[] = ['label_html' => _DEVMODE, 'field_html' => getTplRadioGroup(['name' => 'dev_mode', 'value' => $conf['dev_mode'] ?? 0, 'options' => $yesno])];
    $taba = $tpl->getHtmlPart('div', ['rows' => $rows]);

    $rows = [];
    $rows[] = ['label_html' => _DEFIS, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'defis',
        'value_attr' => urldecode($conf['defis']),
        'maxlength_num' => 255,
        'placeholder_text' => _DEFIS,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _DLETTER, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'dletter',
        'value_attr' => (string)$conf['dletter'],
        'placeholder_text' => _DLETTER,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _LTITLE, 'field_html' => getTplRadioGroup(['name' => 'ltitle', 'value' => $conf['ltitle'], 'options' => $yesno])];
    $rows[] = ['label_html' => _ADESC, 'field_html' => getTplRadioGroup(['name' => 'adesc', 'value' => $conf['adesc'], 'options' => $yesno])];
    $rows[] = ['label_html' => _RSEP, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'sep',
        'value_attr' => urldecode($conf['sep']),
        'maxlength_num' => 255,
        'placeholder_text' => _RSEP,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _TSEP, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'tsep',
        'value_attr' => urldecode($conf['tsep']),
        'maxlength_num' => 255,
        'placeholder_text' => _TSEP,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _REWRITE_MOD, 'field_html' => getTplRadioGroup(['name' => 'rewrite', 'value' => $conf['rewrite'], 'options' => $yesno])];
    $rows[] = ['label_html' => _FORCESSL, 'field_html' => getTplRadioGroup(['name' => 'forcessl', 'value' => $conf['forcessl'] ?? 0, 'options' => $yesno])];
    $rows[] = ['label_html' => _FORCEHOST, 'field_html' => getTplRadioGroup(['name' => 'forcehost', 'value' => $conf['forcehost'] ?? 0, 'options' => $yesno])];
    $rows[] = ['label_html' => _SEOTITLE, 'field_html' => getTplRadioGroup(['name' => 'title', 'value' => $conf['title'] ?? 1, 'options' => $yesno])];
    $rows[] = ['label_html' => _SEOCTITLE, 'field_html' => getTplRadioGroup(['name' => 'ctitle', 'value' => $conf['ctitle'] ?? 1, 'options' => $yesno])];
    $rows[] = ['label_html' => _OGRAPH, 'field_html' => getTplRadioGroup(['name' => 'agraph', 'value' => $conf['agraph'] ?? 1, 'options' => $yesno])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _OGRAPHT, 'hint' => _TPLVARS]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'graph',
        'value_text' => (string)($conf['graph'] ?? ''),
        'cols_num' => 65,
        'rows_num' => 8,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _SCHEMA, 'field_html' => getTplRadioGroup(['name' => 'aschema', 'value' => $conf['aschema'] ?? 1, 'options' => $yesno])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCHEMAT, 'hint' => _TPLVARS]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'schema',
        'value_text' => (string)($conf['schema'] ?? ''),
        'cols_num' => 65,
        'rows_num' => 15,
        'is_config' => true,
    ])];
    $tabb = $tpl->getHtmlPart('div', ['rows' => $rows]);

    $list = is_dir('lang') ? scandir('lang') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (preg_match('/^(.+)\.php/', $file, $matches)) {
                $name = $matches[1];
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $name,
                    'label_text' => getLangName($name),
                    'is_selected' => $conf['language'] == $name,
                ]);
            }
        }
    }
    $rows = [];
    $rows[] = ['label_html' => _SELLANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'language',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _ACTMULTILINGUAL, 'field_html' => getTplRadioGroup(['name' => 'multilingual', 'value' => $conf['multilingual'], 'options' => $yesno])];
    $rows[] = ['label_html' => _ACTUSEFLAGS, 'field_html' => getTplRadioGroup(['name' => 'flags', 'value' => $conf['flags'], 'options' => $yesno])];
    $rows[] = ['label_html' => _ACTUSELANG, 'field_html' => getTplRadioGroup(['name' => 'alang', 'value' => $conf['alang'], 'options' => $yesno])];
    $tabc = $tpl->getHtmlPart('div', ['rows' => $rows]).getGeoipPanel();

    $rows = [];
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '0',
        'label_text' => _NO,
        'is_selected' => $conf['censor'] == 0,
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '1',
        'label_text' => _MATCHANY,
        'is_selected' => $conf['censor'] == 1,
    ]);
    $rows[] = ['label_html' => _CENSORMODE, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'censor',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _CENSORREPLACE, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'censor_r',
        'value_attr' => (string)$conf['censor_r'],
        'maxlength_num' => 10,
        'placeholder_text' => _CENSORREPLACE,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CENSOR, 'hint' => _NOKOMA]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'censor_l',
        'value_text' => (string)$conf['censor_l'],
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CLICABLE, 'hint' => _CLICABLEINFO]), 'field_html' => getTplRadioGroup(['name' => 'clickable', 'value' => $conf['clickable'], 'options' => $yesno])];
    $tabd = $tpl->getHtmlPart('div', ['rows' => $rows]);

    $rows = [];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _BOTSLIST, 'hint' => _NOKOMA.' '._BOTSINFO]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'bots',
        'value_text' => (string)$conf['bots'],
        'cols_num' => 65,
        'rows_num' => 10,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _BOTSSITE, 'hint' => _NOKOMA]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'fbots',
        'value_text' => (string)$conf['fbots'],
        'cols_num' => 65,
        'rows_num' => 10,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _BOTSACT, 'field_html' => getTplRadioGroup(['name' => 'botsact', 'value' => $conf['botsact'], 'options' => $yesno])];
    $tabe = $tpl->getHtmlPart('div', ['rows' => $rows]);

    $cnt = 0;
    $size = 0;
    $dirs = [];
    if (is_dir(CACHE_DIR)) {
        $base = str_replace('\\', '/', CACHE_DIR);
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $fname = $file->getFilename();
            if ($fname === '.htaccess' || $fname === 'index.html') continue;
            $size += (int)$file->getSize();
            $cnt++;
            $rel = substr(str_replace('\\', '/', $file->getPath()), strlen($base) + 1);
            if ($rel === '' || $rel === false) $rel = '.';
            $dirs[$rel] = ($dirs[$rel] ?? 0) + 1;
        }
        ksort($dirs);
    }
    $rows = [];
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '0',
        'label_text' => _NO,
        'is_selected' => $conf['cache'] == 0,
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '1',
        'label_text' => _CACHE_1,
        'is_selected' => $conf['cache'] == 1,
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '2',
        'label_text' => _CACHE_2,
        'is_selected' => $conf['cache'] == 2,
    ]);
    $rows[] = ['label_html' => _CACHE, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'cache',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _CACHETIME, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'cache_t',
        'value_attr' => (string)$conf['cache_t'],
        'placeholder_text' => _CACHETIME,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _CACHECOMP, 'field_html' => getTplRadioGroup(['name' => 'cache_c', 'value' => $conf['cache_c'], 'options' => $yesno])];
    $rows[] = ['label_html' => _CACHEBROW, 'field_html' => getTplRadioGroup(['name' => 'cache_b', 'value' => $conf['cache_b'], 'options' => $yesno])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CACHELOCK, 'hint' => _CACHELOCKINFO]), 'field_html' => getTplRadioGroup(['name' => 'cache_l', 'value' => $conf['cache_l'] ?? '0', 'options' => $yesno])];
    $rows[] = ['label_html' => _CACHEDEL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'cache_d',
        'value_attr' => (string)$conf['cache_d'],
        'placeholder_text' => _CACHEDEL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _CACHECSS, 'field_html' => getTplRadioGroup(['name' => 'cache_css', 'value' => $conf['cache_css'], 'options' => $yesno])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CSSDIR, 'hint' => _CSSDIRINFO.' '._NOKOMA]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'css_f',
        'value_text' => (string)$conf['css_f'],
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _CSSHEAD, 'field_html' => getTplRadioGroup(['name' => 'css_h', 'value' => $conf['css_h'], 'options' => $yesno])];
    $rows[] = ['label_html' => _CSSCOMP, 'field_html' => getTplRadioGroup(['name' => 'css_c', 'value' => $conf['css_c'], 'options' => $yesno])];
    $rows[] = ['label_html' => _CSSENC, 'field_html' => getTplRadioGroup(['name' => 'css_e', 'value' => $conf['css_e'], 'options' => $yesno])];
    $rows[] = ['label_html' => _CACHESCRIPT, 'field_html' => getTplRadioGroup(['name' => 'cache_script', 'value' => $conf['cache_script'], 'options' => $yesno])];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _SCRIPTFILE, 'hint' => _SCRIPTFILEINFO.' '._NOKOMA]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'script_f',
        'value_text' => (string)$conf['script_f'],
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_html' => _SCRIPTHEAD, 'field_html' => getTplRadioGroup(['name' => 'script_h', 'value' => $conf['script_h'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SCRIPTCOMP, 'field_html' => getTplRadioGroup(['name' => 'script_c', 'value' => $conf['script_c'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SCRIPTASIN, 'field_html' => getTplRadioGroup(['name' => 'script_a', 'value' => $conf['script_a'], 'options' => $yesno])];
    $rows[] = ['label_html' => _SCRIPTBOT, 'field_html' => getTplRadioGroup(['name' => 'script_b', 'value' => $conf['script_b'], 'options' => $yesno])];
    $lines = [_DIR.': storage/cache'];
    foreach ($dirs as $dk => $dv) $lines[] = $dk.': '.$dv;
    $lines[] = _FILE_M.': '.$cnt;
    $lines[] = _FILE_S.': '.filterSize($size);
    $html = $tpl->getHtmlFrag('alert', ['lines' => $lines]);
    $tabf = $html.$tpl->getHtmlPart('div', ['rows' => $rows]);

    $rows = [];
    $rows[] = ['label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _MAILTEMP, 'hint' => _MAILTEMPINFO]), 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'mtemp',
        'value_text' => (string)$conf['mtemp'],
        'cols_num' => 65,
        'rows_num' => 10,
        'is_required' => true,
        'is_config' => true,
    ])];
    $tabg = $tpl->getHtmlPart('div', ['rows' => $rows]);

    $content = '';
    foreach ([$taba, $tabb, $tabc, $tabd, $tabe, $tabf, $tabg] as $idx => $panel) {
        $content .= $tpl->getHtmlFrag('tabs-panel', [
            'panel_id' => 'config-panel-'.$idx,
            'content_html' => $panel,
        ]);
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'content_html' => $content,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'config'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'tab', 'valueattr' => (string)$ctab],
            ['nameattr' => 'token', 'valueattr' => getSiteToken()],
        ],
        'actions_html' => $tpl->getHtmlFrag('button', [
            'label' => _CACHECLEAR,
            'is_green' => true,
            'reset_url' => $afile.'.php?name=config&op=clearcache&tab='.$ctab.'&token='.getSiteToken(),
            'input_attr' => ' data-sl-tab-show="5" data-sl-tab-group="config-main" style="display:none"',
        ]),
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $ctab = getVar('post', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 6) $ctab = 0;
    $warn = !checkSiteToken();
    if (!$warn) {
        $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
        $kprotect = [', ' => ',', ' ,' => ',', ' , ' => ',', ',,' => ',', '\n' => ',', '\t' => ',', '\r' => ','];

        $homeurl = getVar('post', 'homeurl', 'url');
        $xhomeurl = ($homeurl !== '' && substr($homeurl, -1) == '/') ? substr($homeurl, 0, -1) : $homeurl;
        $xsite_logo = str_replace('templates/'.$conf['theme'].'/images/logos/', '', getVar('post', 'site_logo', 'text'));
        $xadlogo = basename(str_replace('templates/admin/images/logos/', '', getVar('post', 'admin_logo', 'text')));
        if (!is_file(BASE_DIR.'/templates/admin/images/logos/'.$xadlogo)) $xadlogo = 'slaed_logo_256x73.png';

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
        $eduser = getVar('post', 'editor_user', 'var', 'plain');
        $test = getVar('post', 'testip', 'text', '');
        $test = filter_var($test, FILTER_VALIDATE_IP) ? $test : '';

        $cont = [
            'version' => '6.3.0 Phoenix',
            'sitename' => getVar('post', 'sitename', 'text'),
            'homeurl' => $xhomeurl,
            'admin_logo' => $xadlogo,
            'site_logo' => $xsite_logo,
            'slogan' => getVar('post', 'slogan', 'text'),
            'admininfo' => getVar('post', 'admininfo', 'text'),
            'startdate' => getVar('post', 'startdate', 'time'),
            'adminmail' => getVar('post', 'adminmail', 'text'),
            'user_c' => $xuser_c,
            'admin_c' => $xadmin_c,
            'user_c_t' => getVar('post', 'user_c_t', 'num', 30) * 86400,
            'sess_t' => getVar('post', 'sess_t', 'num', 10) * 60,
            'ip_link' => getVar('post', 'ip_link', 'url', 'http://whois.domaintools.com/'),
            'theme' => getVar('post', 'theme', 'var'),
            'module' => $xmodule,
            'amod' => getVar('post', 'amod', 'var'),
            'editor' => [
                'user' => $eduser,
                'code' => (string)($conf['editor']['code'] ?? 'codemirror'),
            ],
            'gtime' => getVar('post', 'gtime', 'text'),
            'var_view' => getVar('post', 'var_view', 'num'),
            'syntax' => getVar('post', 'syntax', 'num'),
            'variables' => $xvariables,
            'admcol' => getVar('post', 'admcol', 'num', 6),
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
            'forcessl' => getVar('post', 'forcessl', 'num'),
            'forcehost' => getVar('post', 'forcehost', 'num'),
            'title' => getVar('post', 'title', 'num'),
            'ctitle' => getVar('post', 'ctitle', 'num'),
            'agraph' => getVar('post', 'agraph', 'num'),
            'graph' => getVar('post', 'graph', 'raw'),
            'aschema' => getVar('post', 'aschema', 'num'),
            'schema' => getVar('post', 'schema', 'raw'),
            'language' => getVar('post', 'language', 'var'),
            'multilingual' => getVar('post', 'multilingual', 'num'),
            'flags' => getVar('post', 'flags', 'num'),
            'geoip_anon' => getVar('post', 'geoipanon', 'num', 1),
            'geoip_asn' => (string)($conf['geoip_asn'] ?? 'storage/geoip/asn.mmdb'),
            'geoip_cache' => getVar('post', 'geoipcache', 'num', 86400),
            'geoip_country' => (string)($conf['geoip_country'] ?? 'storage/geoip/country.mmdb'),
            'geoip_enabled' => getVar('post', 'geoipenabled', 'num', 1),
            'geoip_store' => getVar('post', 'geoipstore', 'num', 0),
            'geoip_test' => $test,
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
            'cache_l' => getVar('post', 'cache_l', 'num'),
            'cache_css' => getVar('post', 'cache_css', 'num'),
            'css_f' => strtr(getVar('post', 'css_f', 'text', 'templates/[theme]/,plugins/highlightjs/slaed-theme.css'), $kprotect),
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
        ];
        setConfigFile('global.php', $cont);
    }
    setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function clearcache(): void {
    global $afile;
    $ctab = getVar('req', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 6) $ctab = 0;
    $warn = !checkSiteToken();
    if (!$warn) Cache::deleteAll();
    setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, $warn ? _TOKENMISS : _SUCCCLEAR, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=config&tab=0', 'name=config&tab=1', 'name=config&tab=2', 'name=config&tab=3', 'name=config&tab=4', 'name=config&tab=5', 'name=config&tab=6', 'name=config&op=info'],
        'tabs' => [_GENPREF, _SEO, _MULTILINGUAL.' / '._GEOLOCATION, _CENSORS, _BOTSOPT, _OPTIMIZE, _MAILOPT, _DOCS],
    ]);
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'clearcache': clearcache(); break;
    case 'info': info(); break;
}
