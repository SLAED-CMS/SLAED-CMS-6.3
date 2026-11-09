<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# Render one GeoIP database status row
function getGeoipFileRow(string $label, string $file): string {
    global $tpl;
    $info = Geoip::getFileInfo($file);
    $size = $info['found'] ? filterSize($info['size']) : _NO;
    $date = $info['found'] ? date(_TIMESTRING, (int)$info['mtime']) : _NO;
    return $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
        'cells' => [
            ['content_html' => $label],
            ['content_html' => $size],
            ['content_html' => $date],
            ['content_html' => (string)$info['path']],
            ['is_col_status' => true, 'stat' => ['text' => $info['found'] ? _FOUND : _NOTFOUND, 'is_on' => (bool)$info['found'], 'is_fail' => !$info['found']]],
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
        [
            'label_html' => _GEO_IP,
            'label_id' => $labid = getFieldIds('', 'geoipenabled')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'geoipenabled',
                'value' => (string)(int)($conf['geoip_enabled'] ?? 0),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-geoipcache',
            'label_html' => _GEOIP_CACHE,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'geoipcache',
                'input_id' => 'f-geoipcache',
                'value_attr' => (string)($conf['geoip_cache'] ?? 86400),
                'is_config' => true,
            ]),
        ],
        [
            'label_html' => _GEOIP_ANON,
            'label_id' => $labid = getFieldIds('', 'geoipanon')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'geoipanon',
                'value' => (string)(int)($conf['geoip_anon'] ?? 1),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_html' => _GEOIP_STORE,
            'label_id' => $labid = getFieldIds('', 'geoipstore')['label'],
            'field_html' => getTplRadioGroup([
                'labelledby' => $labid,
                'name' => 'geoipstore',
                'value' => (string)(int)($conf['geoip_store'] ?? 0),
                'options' => [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]],
            ]),
        ],
        [
            'label_for' => 'f-testip',
            'label_html' => _GEOIP_TESTIP,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'testip', 'input_id' => 'f-testip', 'value_attr' => $test, 'is_config' => true]),
        ],
    ];
    $head = [
        ['content' => _GEOIP_BASE],
        ['content' => _SIZE],
        ['content' => _DATE],
        ['content' => _GEOIP_PATH],
        ['content' => _STATUS, 'is_col_status' => true, 'is_head_status' => true],
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

# Render the settings tabs in one form; the cache button stands in its action row but posts a form of its own by the attribute form, because the browser drops a nested form
function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $ctab = getVar('get', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 4) $ctab = 0;
    $links = [];
    foreach ([_GENPREF, _SEO, _LOCALIZATION, _CENSORS, _OPTIMIZE] as $idx => $label) {
        $links[] = [
            'href' => '#',
            'is_active' => $ctab === $idx,
            'label' => $label,
            'rel' => 'config-panel-'.$idx,
            'title' => $label,
        ];
        if ($idx === 3) $links[] = ['href' => $afile.'.php?name=config&op=replace', 'label' => _REPLACE, 'title' => _REPLACE];
    }
    $links[] = [
        'href' => $afile.'.php?name=config&op=info&tab='.$ctab,
        'label' => _MANUAL,
        'link_attr' => 'data-sl-tab-info-link="config-main"',
        'title' => _MANUAL,
    ];
    $cont = getTplAdminTabs([
        'find' => _ADM_SETFIND,
        'is_runtime' => true,
        'links' => $links,
        'tabs_id' => 'config-main',
        'tabs_index' => (getVar('get', 'tab', 'raw', '') !== '') ? $ctab : '',
        'tabs_sync_selector' => 'input[name="tab"]',
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/global.php');
    if (getConfigJournal()) {
        $link = $tpl->getHtmlFrag('link', ['href' => $afile.'.php?name=config&op=restore', 'title' => _CONFIG_RESTORE, 'label' => _CONFIG_RESTORE]);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CONFIG_PENDING.' '.$link]);
    }
    $rows = [];
    $yesno = [
        ['value' => '1', 'label' => _YES],
        ['value' => '0', 'label' => _NO],
    ];
    $rows[] = ['label_for' => 'f-sitename', 'label_html' => _SITENAME, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'sitename',
        'input_id' => 'f-sitename',
        'value_attr' => (string)$conf['sitename'],
        'maxlength_num' => 255,
        'placeholder_text' => _SITENAME,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-homeurl', 'label_html' => _SITEURL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'url',
        'name_attr' => 'homeurl',
        'input_id' => 'f-homeurl',
        'value_attr' => (string)$conf['homeurl'],
        'maxlength_num' => 255,
        'placeholder_text' => _SITEURL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $path = 'templates/'.$conf['theme'].'/images/logos/';
    $dir = PUBLIC_DIR.'/'.$path;
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
    $rows[] = ['label_for' => 'img_replace', 'label_html' => _LOGO, 'field_html' => $tpl->getHtmlFrag('select', [
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
    $dir = PUBLIC_DIR.'/'.$path;
    $adlogo = $conf['admin_logo'] ?? 'slaed-logo-wordmark-gradient-blue.svg';
    if (!is_file(PUBLIC_DIR.'/'.$path.$adlogo)) $adlogo = 'slaed-logo-wordmark-gradient-blue.svg';
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
    $rows[] = ['label_for' => 'admin_img_replace', 'label_html' => _ADMINLOGO, 'field_html' => $tpl->getHtmlFrag('select', [
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
    $rows[] = ['label_for' => 'f-slogan', 'label_html' => _DESCRIPTION, 'field_html' => $tpl->getHtmlFrag('textarea', [
        'name_attr' => 'slogan',
        'input_id' => 'f-slogan',
        'value_text' => (string)$conf['slogan'],
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = [
        'label_for' => 'f-admininfo',
        'label_html' => _ADMININFO,
        'hint_html' => _ADMININFODES,
        'hint_id' => $hntid = getFieldIds('f-admininfo')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'admininfo',
            'input_id' => 'f-admininfo',
            'value_text' => (string)$conf['admininfo'],
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_html' => _STARTDATE,
        'field_html' => getTplAddDateTime(['name' => 'startdate', 'label' => _STARTDATE, 'time' => (string)$conf['startdate'], 'with' => true, 'max' => 16, 'is_config' => true]),
    ];
    $rows[] = ['label_for' => 'f-adminmail', 'label_html' => _ADMINEMAIL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'email',
        'name_attr' => 'adminmail',
        'input_id' => 'f-adminmail',
        'value_attr' => (string)$conf['adminmail'],
        'maxlength_num' => 255,
        'placeholder_text' => _ADMINEMAIL,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-user-c', 'label_html' => _USER_COOKIE, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'user_c',
        'input_id' => 'f-user-c',
        'value_attr' => (string)$conf['user_c'],
        'maxlength_num' => 255,
        'placeholder_text' => _USER_COOKIE,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-admin-c', 'label_html' => _ADMIN_SESSION, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'admin_c',
        'input_id' => 'f-admin-c',
        'value_attr' => (string)$conf['admin_c'],
        'maxlength_num' => 255,
        'placeholder_text' => _ADMIN_SESSION,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-user-c-t', 'label_html' => _USER_COOKIE_T, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'user_c_t',
        'input_id' => 'f-user-c-t',
        'value_attr' => (string)intval($conf['user_c_t'] / 86400),
        'placeholder_text' => _USER_COOKIE_T,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-sess-t', 'label_html' => _SESS_T, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'sess_t',
        'input_id' => 'f-sess-t',
        'value_attr' => (string)intval($conf['sess_t'] / 60),
        'placeholder_text' => _SESS_T,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-ip-link', 'label_html' => _IP_LINK, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'url',
        'name_attr' => 'ip_link',
        'input_id' => 'f-ip-link',
        'value_attr' => (string)$conf['ip_link'],
        'maxlength_num' => 255,
        'placeholder_text' => _IP_LINK,
        'is_required' => true,
        'is_config' => true,
    ])];
    $list = is_dir(PUBLIC_DIR.'/templates') ? scandir(PUBLIC_DIR.'/templates') : [];
    $opts = '';
    if (is_array($list)) {
        foreach ($list as $file) {
            if (!preg_match('/\./', $file) && $file != 'admin' && checkThemeAssets($file)) {
                $opts .= $tpl->getHtmlFrag('select-option', [
                    'value_attr' => $file,
                    'label_text' => $file,
                    'is_selected' => $file == $conf['theme'],
                ]);
            }
        }
    }
    $rows[] = ['label_for' => 'f-theme', 'label_html' => _THEME, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'theme',
        'selectid' => 'f-theme',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $fids = getFieldIds('f-module');
    $rows[] = [
        'label_html' => _PUTINHOME,
        'label_id' => $fids['label'],
        'hint_html' => _PUTINHOMEINFO,
        'hint_id' => $fids['hint'],
        'field_html' => getTplModuleChecks('module', $conf['module'], [], $fids['label'], $fids['hint']),
    ];
    $rows[] = [
        'label_for' => 'f-editor-user',
        'label_html' => _EDITORUSER,
        'field_html' => Editor::getSelect('editor_user', (string)($conf['editor']['user'] ?? 'plain'), 'content', 'user', 'id="f-editor-user"'),
    ];
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
    $rows[] = ['label_for' => 'f-gtime', 'label_html' => _GTIME, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'gtime',
        'selectid' => 'f-gtime',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $vars = explode(',', $conf['variables']);
    $vals = [
        _DEACTIVATE,
        _SYSTEM_INFO,
        _AVARIABLES.': POST',
        _AVARIABLES.': GET',
        _AVARIABLES.': COOKIE',
        _AVARIABLES.': FILES',
        _AVARIABLES.': SESSION',
        _AVARIABLES.': SERVER',
        _AQUERY_DB.': MySQL',
    ];
    $boxes = [];
    foreach ($vals as $key => $val) $boxes[] = ['name_attr' => 'variables[]', 'value_attr' => (string)$key, 'label_text' => $val, 'is_checked' => !empty($vars[$key])];
    $rows[] = ['label_html' => _VARIABLES, 'label_id' => $labid = getFieldIds('', 'variables')['label'],
        'field_html' => $tpl->getHtmlFrag('check-list', ['labelledby' => $labid, 'items' => $boxes])];
    $opts = $tpl->getHtmlFrag('select-option', [
        'value_attr' => '0',
        'label_text' => _MVADMIN,
        'is_selected' => $conf['var_view'] == '0',
    ]).$tpl->getHtmlFrag('select-option', [
        'value_attr' => '1',
        'label_text' => _MVALL,
        'is_selected' => $conf['var_view'] == '1',
    ]);
    $rows[] = ['label_for' => 'f-var-view', 'label_html' => _VAR_VIEW, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'var_view',
        'selectid' => 'f-var-view',
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
    $rows[] = ['label_for' => 'f-syntax', 'label_html' => _SYNTAX, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'syntax',
        'selectid' => 'f-syntax',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-admcol', 'label_html' => _ADMCOL, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'admcol',
        'input_id' => 'f-admcol',
        'value_attr' => (string)$conf['admcol'],
        'placeholder_text' => _ADMCOL,
        'is_required' => true,
        'is_config' => true,
    ])];
    foreach (['rows' => [_C_33, 1, 100], 'span' => [_C_35, 1, 20], 'arows' => [_C_34, 5, LIST_MAX], 'aspan' => [_C_36, 1, 20]] as $key => [$label, $min, $max]) {
        $rows[] = ['label_for' => 'f-pager-'.$key, 'label_html' => $label, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'pager_'.$key,
            'input_id' => 'f-pager-'.$key,
            'value_attr' => (string)($conf['pager'][$key] ?? ''),
            'input_attr' => 'min="'.$min.'" max="'.$max.'"',
            'is_required' => true,
            'is_config' => true,
        ])];
    }
    $rows[] = [
        'label_html' => _SESSION,
        'label_id' => $labid = getFieldIds('', 'session')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'session', 'value' => $conf['session'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _BOTSACT,
        'label_id' => $labid = getFieldIds('', 'botsact')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'botsact', 'value' => $conf['botsact'], 'options' => $yesno]),
    ];
    foreach (['site' => _LIVE_SITE, 'panel' => _LIVE_PANEL, 'note' => _LIVE_NOTE] as $kind => $label) {
        $rows[] = ['label_for' => 'f-live-'.$kind, 'label_html' => $label, 'field_html' => $tpl->getHtmlFrag('input', [
            'itype' => 'number',
            'name_attr' => 'live_'.$kind,
            'input_id' => 'f-live-'.$kind,
            'value_attr' => (string)getLiveEvery($kind),
            'placeholder_text' => $label,
            'is_config' => true,
        ])];
    }
    $rows[] = [
        'label_html' => _TIME_DB,
        'label_id' => $labid = getFieldIds('', 'db_t')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'db_t', 'value' => $conf['db_t'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _ADMINFOEDIT,
        'label_id' => $labid = getFieldIds('', 'adminfo')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'adminfo', 'value' => $conf['adminfo'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _SITE_CLOSE,
        'label_id' => $labid = getFieldIds('', 'close')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'close', 'value' => $conf['close'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _DEVMODE,
        'label_id' => $labid = getFieldIds('', 'dev_mode')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'dev_mode', 'value' => $conf['dev_mode'] ?? 0, 'options' => $yesno]),
    ];
    $taba = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $rows = [];
    $rows[] = ['label_for' => 'f-defis', 'label_html' => _DEFIS, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'defis',
        'input_id' => 'f-defis',
        'value_attr' => urldecode($conf['defis']),
        'maxlength_num' => 255,
        'placeholder_text' => _DEFIS,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-dletter', 'label_html' => _DLETTER, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'number',
        'name_attr' => 'dletter',
        'input_id' => 'f-dletter',
        'value_attr' => (string)$conf['dletter'],
        'placeholder_text' => _DLETTER,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = [
        'label_html' => _LTITLE,
        'label_id' => $labid = getFieldIds('', 'ltitle')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'ltitle', 'value' => $conf['ltitle'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _ADESC,
        'label_id' => $labid = getFieldIds('', 'adesc')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'adesc', 'value' => $conf['adesc'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _FORCESSL,
        'label_id' => $labid = getFieldIds('', 'forcessl')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'forcessl', 'value' => $conf['forcessl'] ?? 0, 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _FORCEHOST,
        'label_id' => $labid = getFieldIds('', 'forcehost')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'forcehost', 'value' => $conf['forcehost'] ?? 0, 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _OGRAPH,
        'label_id' => $labid = getFieldIds('', 'agraph')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'agraph', 'value' => $conf['agraph'] ?? 1, 'options' => $yesno]),
    ];
    $rows[] = [
        'label_for' => 'f-graph',
        'label_html' => _OGRAPHT,
        'hint_html' => _TPLVARS,
        'hint_id' => $hntid = getFieldIds('f-graph')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'graph',
            'input_id' => 'f-graph',
            'value_text' => (string)($conf['graph'] ?? ''),
            'cols_num' => 65,
            'rows_num' => 8,
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_html' => _SCHEMA,
        'label_id' => $labid = getFieldIds('', 'aschema')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'aschema', 'value' => $conf['aschema'] ?? 1, 'options' => $yesno]),
    ];
    $rows[] = [
        'label_for' => 'f-schema',
        'label_html' => _SCHEMAT,
        'hint_html' => _TPLVARS,
        'hint_id' => $hntid = getFieldIds('f-schema')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'schema',
            'input_id' => 'f-schema',
            'value_text' => (string)($conf['schema'] ?? ''),
            'cols_num' => 65,
            'rows_num' => 15,
            'is_config' => true,
        ]),
    ];
    $tabb = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $list = is_dir(BASE_DIR.'/lang') ? scandir(BASE_DIR.'/lang') : [];
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
    $rows[] = ['label_for' => 'f-language', 'label_html' => _SELLANGUAGE, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'language',
        'selectid' => 'f-language',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = [
        'label_html' => _ACTMULTILINGUAL,
        'label_id' => $labid = getFieldIds('', 'multilingual')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'multilingual', 'value' => $conf['multilingual'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _ACTUSEFLAGS,
        'label_id' => $labid = getFieldIds('', 'flags')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'flags', 'value' => $conf['flags'], 'options' => $yesno]),
    ];
    $rows[] = [
        'label_html' => _ACTUSELANG,
        'label_id' => $labid = getFieldIds('', 'alang')['label'],
        'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'alang', 'value' => $conf['alang'], 'options' => $yesno]),
    ];
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
    $rows[] = ['label_for' => 'f-censor', 'label_html' => _CENSORMODE, 'field_html' => $tpl->getHtmlFrag('select', [
        'name_attr' => 'censor',
        'selectid' => 'f-censor',
        'options_html' => $opts,
        'is_config' => true,
    ])];
    $rows[] = ['label_for' => 'f-censor-r', 'label_html' => _CENSORREPLACE, 'field_html' => $tpl->getHtmlFrag('input', [
        'itype' => 'text',
        'name_attr' => 'censor_r',
        'input_id' => 'f-censor-r',
        'value_attr' => (string)$conf['censor_r'],
        'maxlength_num' => 10,
        'placeholder_text' => _CENSORREPLACE,
        'is_required' => true,
        'is_config' => true,
    ])];
    $rows[] = [
        'label_for' => 'f-censor-l',
        'label_html' => _CENSOR,
        'hint_html' => _NOKOMA,
        'hint_id' => $hntid = getFieldIds('f-censor-l')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'censor_l',
            'input_id' => 'f-censor-l',
            'value_text' => (string)$conf['censor_l'],
            'is_required' => true,
            'is_config' => true,
        ]),
    ];
    $tabd = $tpl->getHtmlPart('div', ['rows' => $rows]);
    $cnt = 0;
    $size = 0;
    $dirs = [];
    if (is_dir(CACHE_DIR)) {
        $base = str_replace('\\', '/', CACHE_DIR);
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(CACHE_DIR, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $size += (int)$file->getSize();
            $cnt++;
            $rel = substr(str_replace('\\', '/', $file->getPath()), strlen($base) + 1);
            if ($rel === '' || $rel === false) $rel = '.';
            $dirs[$rel] = ($dirs[$rel] ?? 0) + 1;
        }
        ksort($dirs);
    }
    $rows = [];
    $rows[] = [
        'label_html' => _CACHE,
        'hint_html' => _CACHEINFO,
        'label_id' => ($fids = getFieldIds('', 'cache'))['label'],
        'hint_id' => $fids['hint'],
        'field_html' => getTplRadioGroup([
            'describedby' => $fids['hint'],
            'labelledby' => $fids['label'],
            'name' => 'cache',
            'value' => $conf['cache'],
            'options' => $yesno,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-css-f',
        'label_html' => _CSSDIR,
        'hint_html' => _CSSDIRINFO.' '._NOKOMA,
        'hint_id' => $hntid = getFieldIds('f-css-f')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'css_f',
            'input_id' => 'f-css-f',
            'value_text' => (string)$conf['css_f'],
            'is_config' => true,
        ]),
    ];
    $rows[] = [
        'label_for' => 'f-script-f',
        'label_html' => _SCRIPTFILE,
        'hint_html' => _SCRIPTFILEINFO.' '._NOKOMA,
        'hint_id' => $hntid = getFieldIds('f-script-f')['hint'],
        'field_html' => $tpl->getHtmlFrag('textarea', [
            'describedby' => $hntid,
            'name_attr' => 'script_f',
            'input_id' => 'f-script-f',
            'value_text' => (string)$conf['script_f'],
            'is_required' => true,
            'is_config' => true,
        ]),
    ];
    $items = [];
    foreach ($dirs as $dk => $dv) $items[] = ['label' => ($dk === '.') ? 'storage/cache' : 'storage/cache/'.$dk, 'text' => filterDecimal($dv, 0)];
    if (empty($conf['scheduler']['jobs']['cachegc']['active'])) $items[] = ['label' => _SCHEDULER.' cachegc', 'text' => _SCHEDULER_OFF, 'is_flag' => true];
    $html = $tpl->getHtmlFrag('metric-list', ['items' => [
        ['label' => _FILE_M, 'value' => filterDecimal($cnt, 0), 'icon' => 'files'],
        ['label' => _FILE_S, 'value' => filterSize($size), 'icon' => 'hdd'],
    ]]).$tpl->getHtmlFrag('kv-list', ['items' => $items]);
    $tabf = $html.$tpl->getHtmlPart('div', ['rows' => $rows]);
    $content = '';
    foreach ([$taba, $tabb, $tabc, $tabd, $tabf] as $idx => $panel) {
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
            ['nameattr' => 'token', 'valueattr' => getSiteToken('config')],
        ],
        'is_valid' => true,
        'actions_html' => $tpl->getHtmlFrag('button', [
            'button_type' => 'submit',
            'submit_label' => _CACHECLEAR,
            'icon_name' => 'trash',
            'is_plain' => true,
            'button_attr' => 'form="config-clear" data-sl-tab-show="4" data-sl-tab-group="config-main" hidden',
        ]),
        'submit_label' => _SAVECHANGES,
    ]);
    $clear = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'post',
        'form_attr' => 'id="config-clear" hidden',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'config'],
            ['nameattr' => 'op', 'valueattr' => 'clearcache'],
            ['nameattr' => 'tab', 'valueattr' => (string)$ctab],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('config')],
        ],
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form.$clear]);
    setFoot();
}

function save(): void {
    global $afile, $conf;
    $ctab = getVar('post', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 4) $ctab = 0;
    $warn = !checkAdminPost('config');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $protect = ['\n' => '', '\t' => '', '\r' => '', ' ' => ''];
        $kprotect = [', ' => ',', ' ,' => ',', ' , ' => ',', ',,' => ',', '\n' => ',', '\t' => ',', '\r' => ','];

        $homeurl = getVar('post', 'homeurl', 'url');
        $xhomeurl = ($homeurl !== '' && substr($homeurl, -1) == '/') ? substr($homeurl, 0, -1) : $homeurl;
        $keys = [];
        $brand = filterSiteBrand(['sitename' => getVar('post', 'sitename', 'raw', ''), 'site_logo' => getVar('post', 'site_logo', 'raw', '')], $keys);
        $names = array_map(static fn(string $v): string => ($v === 'sitename') ? _SITENAME : _LOGO, $keys);
        if ($keys) setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, sprintf(_ADM_BADVAL, implode(', ', $names)), true);
        $xadlogo = basename(str_replace('templates/admin/images/logos/', '', getVar('post', 'admin_logo', 'text')));
        if (!is_file(PUBLIC_DIR.'/templates/admin/images/logos/'.$xadlogo)) $xadlogo = 'slaed-logo-wordmark-gradient-blue.svg';

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
        $graph = getVar('post', 'graph', 'raw');
        $schema = getVar('post', 'schema', 'raw');
        $known = ['[homeurl]', '[site]', '[logo]', '[loc]', '[time]', '[mtime]', '[title]', '[desc]', '[img]', '[ctitle]', '[type]', '[url]', '[headline]', '[author]'];
        preg_match_all('#\[[a-z]+\]#', $graph.$schema, $found);
        $unknown = array_values(array_diff(array_unique($found[0]), $known));
        if ($unknown) setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, _SEO_TPL_INVALID.': '.implode(', ', $unknown), true);
        if ($graph !== '') {
            preg_match_all('#<meta\b[^>]*>#is', $graph, $metas);
            $rest = trim((string)preg_replace('#<meta\b[^>]*>#is', '', $graph));
            $valid = $rest === '' && !empty($metas[0]);
            foreach ($metas[0] ?? [] as $meta) {
                $hasprop = preg_match('#\bproperty\s*=\s*(["\'])og:[^"\']+\1#i', $meta);
                $hascont = preg_match('#\bcontent\s*=\s*(["\']).*?\1#is', $meta);
                if (!$hasprop || !$hascont) $valid = false;
            }
            if (!$valid) setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, _SEO_TPL_INVALID.': '._OGRAPHT, true);
        }
        if ($schema !== '') {
            try {
                getSeoJsonItems($schema);
            } catch (Throwable $e) {
                setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, _SEO_TPL_INVALID.': '.$e->getMessage(), true);
            }
        }

        $cont = [
            'sitename' => $brand['sitename'],
            'homeurl' => $xhomeurl,
            'admin_logo' => $xadlogo,
            'site_logo' => $brand['site_logo'],
            'slogan' => getVar('post', 'slogan', 'text'),
            'admininfo' => getVar('post', 'admininfo', 'text'),
            'startdate' => getVar('post', 'startdate', 'time'),
            'adminmail' => getVar('post', 'adminmail', 'text'),
            'user_c' => $xuser_c,
            'admin_c' => $xadmin_c,
            'user_c_t' => getVar('post', 'user_c_t', 'num', 30) * 86400,
            'sess_t' => getVar('post', 'sess_t', 'num', 10) * 60,
            'ip_link' => getVar('post', 'ip_link', 'url', 'http://whois.domaintools.com/'),
            'theme' => ($xtheme = getVar('post', 'theme', 'var')) && checkThemeAssets($xtheme) ? $xtheme : (string)$conf['theme'],
            'module' => $xmodule,
            'editor' => ['user' => $eduser],
            'gtime' => getVar('post', 'gtime', 'text'),
            'var_view' => getVar('post', 'var_view', 'num'),
            'syntax' => getVar('post', 'syntax', 'num'),
            'variables' => $xvariables,
            'admcol' => getVar('post', 'admcol', 'num', 6),
            'pager' => [
                'rows' => (string)max(1, min(100, getVar('post', 'pager_rows', 'num', 15))),
                'span' => (string)max(1, min(20, getVar('post', 'pager_span', 'num', 8))),
                'arows' => (string)max(5, min(LIST_MAX, getVar('post', 'pager_arows', 'num', 50))),
                'aspan' => (string)max(1, min(20, getVar('post', 'pager_aspan', 'num', 10))),
            ],
            'session' => getVar('post', 'session', 'num'),
            'live' => array_map(static fn(string $kind): string => (string)getLiveEvery($kind, intval(getVar('post', 'live_'.$kind, 'num'))),
                ['site' => 'site', 'panel' => 'panel', 'note' => 'note']),
            'db_t' => getVar('post', 'db_t', 'num'),
            'adminfo' => getVar('post', 'adminfo', 'num'),
            'close' => getVar('post', 'close', 'num'),
            'defis' => urlencode(getVar('post', 'defis', 'let', '|')),
            'dletter' => getVar('post', 'dletter', 'num', 160),
            'ltitle' => getVar('post', 'ltitle', 'num'),
            'adesc' => getVar('post', 'adesc', 'num'),
            'forcessl' => getVar('post', 'forcessl', 'num'),
            'forcehost' => getVar('post', 'forcehost', 'num'),
            'agraph' => getVar('post', 'agraph', 'num'),
            'graph' => $graph,
            'aschema' => getVar('post', 'aschema', 'num'),
            'schema' => $schema,
            'language' => getVar('post', 'language', 'var'),
            'multilingual' => getVar('post', 'multilingual', 'num'),
            'flags' => getVar('post', 'flags', 'num'),
            'geoip_anon' => getVar('post', 'geoipanon', 'num', 1),
            'geoip_cache' => getVar('post', 'geoipcache', 'num', 86400),
            'geoip_enabled' => getVar('post', 'geoipenabled', 'num', 1),
            'geoip_store' => getVar('post', 'geoipstore', 'num', 0),
            'geoip_test' => $test,
            'alang' => getVar('post', 'alang', 'num'),
            'censor' => $xcensor,
            'censor_r' => $xcensor_r,
            'censor_l' => $xcensor_l,
            'botsact' => getVar('post', 'botsact', 'num'),
            'cache' => getVar('post', 'cache', 'num'),
            'css_f' => strtr(getVar('post', 'css_f', 'text', ''), $kprotect),
            'script_f' => strtr(getVar('post', 'script_f', 'text',
                'plugins/htmx/htmx.min.js,plugins/system/global-func.js,plugins/system/slaed.js,plugins/tablesort/tablesort.min.js'), $kprotect),
            'dev_mode' => getVar('post', 'dev_mode', 'num'),
        ];
        $file = CONFIG_DIR.'/global.php';
        if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
        $glob = is_file($file) ? require $file : [];
        $warn = !is_array($glob) || !$glob || !setConfigFile('global.php', $cont, $glob);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, $text, $warn);
}

function clearcache(): void {
    global $afile;
    $ctab = getVar('post', 'tab', 'num', 0);
    if ($ctab < 0 || $ctab > 4) $ctab = 0;
    $warn = !checkAdminPost('config');
    if (!$warn) Cache::deleteAll();
    setRedirect($afile.'.php?name=config&tab='.$ctab, false, 302, $warn ? _TOKENMISS : _SUCCCLEAR, $warn);
}

# One form per type, because a form of all types can grow past max_input_vars, and PHP would then cut off the rules of the last ones without a word
function replace(): void {
    global $afile, $conf, $tpl;
    $types = getNodeTypeMap();
    $pick = getVar('get', 'type', 'var');
    if (!isset($types[$pick])) $pick = (string)array_key_first($types);
    setHead();
    $cont = getTplAdminTabs([
        'ops' => ['name=config&tab=0', 'name=config&tab=1', 'name=config&tab=2', 'name=config&tab=3', 'name=config&op=replace', 'name=config&tab=4', 'name=config&op=info'],
        'tabs' => [_GENPREF, _SEO, _LOCALIZATION, _CENSORS, _REPLACE, _OPTIMIZE, _MANUAL],
        'tab' => 4,
    ]);
    if ($pick === '') {
        echo $cont.$tpl->getHtmlFrag('empty', []);
        setFoot();
        return;
    }
    $subs = '';
    foreach ($types as $name => $type) {
        $label = (str_starts_with($type->title, '_') && defined($type->title)) ? constant($type->title) : $type->title;
        $subs .= $tpl->getHtmlFrag('tabs-link', ['href' => $afile.'.php?name=config&op=replace&type='.$name, 'is_active' => $name === $pick, 'label' => $label, 'title' => $label]);
    }
    $cont .= $tpl->getHtmlPart('tabs', ['id' => 'replace-type', 'is_subtabs' => true, 'tabs_html' => $subs]);
    $cont .= $tpl->getHtmlFrag('alert', ['text' => _REPLACEINFO]);
    $cont .= checkPerms(CONFIG_DIR.'/replace.php');
    $fieldc = explode('||', $conf['replace'][$pick] ?? '');
    $blok = '';
    for ($c = 0; $c < 50; $c++) {
        $out = array_pad(explode('|', $fieldc[$c] ?? '', 2), 2, '');
        if ($out[0] === '0') $out[0] = '';
        if ($out[1] === '0') $out[1] = '';
        $next = $c + 1;
        $wid = 'f-word-'.$c;
        $tid = 'f-repl-'.$c;
        $rows = [
            [
                'label_for' => $wid,
                'label_html' => _WORD,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text',
                    'name_attr' => 'word[]',
                    'input_id' => $wid,
                    'value_attr' => $out[0],
                    'placeholder_text' => _WORD,
                    'is_config' => true,
                ]),
            ],
            [
                'label_for' => $tid,
                'label_html' => _CONTENT,
                'hint_html' => _REPLACEIN,
                'hint_id' => $hntid = getFieldIds($tid)['hint'],
                'field_html' => $tpl->getHtmlFrag('textarea', [
                    'describedby' => $hntid,
                    'name_attr' => 'repl[]',
                    'input_id' => $tid,
                    'value_text' => $out[1],
                    'rows_num' => '5',
                    'is_config' => true,
                ]),
            ],
        ];
        $blok .= $tpl->getHtmlPart('toggle-form-block', [
            'block_id' => 'fi'.$c,
            'is_toggle_block' => true,
            'is_hidden' => $out[0] === '' && $out[1] === '' && $c !== 0,
            'toggle_target_id' => 'fi'.$next,
            'title' => _ADD,
            'label_html' => _REPLACE_FIELD.': '.$next,
            'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows]),
        ]);
    }
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'config'],
            ['nameattr' => 'op', 'valueattr' => 'replacesave'],
            ['nameattr' => 'type', 'valueattr' => $pick],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('config')],
        ],
        'content_html' => $blok,
        'submit_label' => _SAVECHANGES,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]);
    setFoot();
}

# A type without rules stays in the file with an empty value: the parser then skips it, and the merge of an update would bring a missing key back with the shipped rules
function replacesave(): void {
    global $afile, $conf;
    $type = getVar('post', 'type', 'var');
    $warn = !checkAdminPost('config');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn && !isset(getNodeTypeMap()[$type])) {
        $warn = true;
        $text = _ERROR;
    }
    if (!$warn) {
        $rules = [];
        $bars = [];
        for ($i = 0; $i < 50; $i++) {
            $word = getVar('post', 'word['.$i.']', 'word', '0');
            $body = getVar('post', 'repl['.$i.']', '', '0');
            if (str_contains($word.$body, '|')) $bars[] = $i + 1;
            if ($word !== '0' || $body !== '0') $rules[] = $word.'|'.$body;
        }
        if ($bars) setRedirect($afile.'.php?name=config&op=replace&type='.$type, false, 302, sprintf(_REPLACE_BAR, implode(', ', $bars)), true);
        $warn = !setConfigFile('replace.php', [$type => implode('||', $rules)], $conf['replace'] ?? []);
        if ($warn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=config&op=replace'.($type !== '' ? '&type='.$type : ''), false, 302, $text, $warn);
}

# The restore entry reads nothing but the configuration journal, so it opens when the configuration it repairs cannot render the settings screen
function restore(): void {
    global $afile, $tpl;
    if (getVar('post', 'op', 'var') === 'restore') {
        $warn = !checkAdminPost('config');
        $text = _TOKENMISS;
        if (!$warn) {
            $warn = !setConfigRestore();
            $why = $warn ? (getConfigJournal()['why'] ?? '') : '';
            $text = $warn ? (($why !== '') ? sprintf(_CONFIG_BLOCKED, $why) : _ERROR_UP) : _CONFIG_RESTORED;
        }
        setRedirect($afile.'.php?name=config&op=restore', false, 302, $text, $warn);
    }
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=config', 'name=config&op=restore', 'name=config&op=info'], 'tabs' => [_PREFERENCES, _CONFIG_RECOVER, _MANUAL], 'tab' => 1]);
    $jour = getConfigJournal();
    if (!$jour) {
        echo $cont.$tpl->getHtmlFrag('alert', ['text' => _CONFIG_CLEAN]);
        setFoot();
        return;
    }
    $rows = [[_CONFIG_OP, $jour['op']], [_DATE, date(_TIMESTRING, $jour['time'])], [_CONFIG_PHASE, $jour['phase']]];
    if ($jour['types']) $rows[] = [_TYPE, implode(', ', array_map('strval', $jour['types']))];
    foreach ($jour['files'] as $name => $one) $rows[] = [_FILE.': '.$name, $one['state']];
    $proof = $jour['why'] === 'proof';
    $pname = (string)($jour['proof']['name'] ?? '');
    $pword = (($jour['proof']['kind'] ?? '') === 'layout') ? _LAYOUT_PROOF : sprintf(_NODE_PROOF, $pname);
    $rows[] = [_CONFIG_VERDICT, ($jour['verdict'] !== '') ? $jour['verdict'] : ($proof ? $pword : sprintf(_CONFIG_BLOCKED, $jour['why']))];
    $body = '';
    foreach ($rows as $row) {
        $body .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', [
            'cells' => [
                ['content_html' => $row[0]],
                ['content_html' => htmlspecialchars($row[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')],
            ],
        ])]);
    }
    $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CONFIG_PENDING]);
    $info = $tpl->getHtmlFrag('table', ['head' => [['content' => _PARAMETERS], ['content' => _VALUE]], 'rows_html' => $body]);
    $send = ($jour['verdict'] !== '' || $proof) ? getTplPostButton(['name' => 'config', 'op' => 'restore'], 'arrow-repeat', _CONFIG_RESTORE) : '';
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $info.$send]);
    setFoot();
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => [
            'name=config&tab=0',
            'name=config&tab=1',
            'name=config&tab=2',
            'name=config&tab=3',
            'name=config&op=replace',
            'name=config&tab=4',
            'name=config&op=info',
        ],
        'tabs' => [_GENPREF, _SEO, _LOCALIZATION, _CENSORS, _REPLACE, _OPTIMIZE, _MANUAL],
    ]);
}

switch ($op) {
    default: config(); break;
    case 'save': save(); break;
    case 'clearcache': clearcache(); break;
    case 'replace': replace(); break;
    case 'replacesave': replacesave(); break;
    case 'restore': restore(); break;
    case 'info': info(); break;
}
