<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('changelog')) die('Illegal file access');

require_once __DIR__.'/../common.php';

function changelog(): void {
    global $afile, $conf, $tpl;
    setHead();
    $exporten = $conf['changelog']['exporten'] ?? true;
    $cont = getTplAdminTabs($exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=export&id=txt', 'name=changelog&op=export&id=md', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _DOCS],
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DOCS],
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/changelog.php');

    $page = max(1, getVar('get', 'page', 'num', 1));
    $filters = chlogReadFilters('get');
    chlogCanonicalRedirect($afile.'.php', 'changelog', $filters, $page);
    $config = chlogGetConfig($conf);
    $loaded = chlogLoadCommits($conf, $filters, __DIR__.'/../../../');
    $commits = $loaded['commits'];
    $totcount = $loaded['total'];
    $error = (string)$loaded['error'];

    if (empty($commits)) {
        $warnText = $error !== '' ? $error : _CHLOG_ERR_NO_COMMITS;
        $warnType = $error !== '' ? 'warn' : 'info';
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => ($warnType !== 'info'), 'text' => $warnText]);
        echo $cont;
        setFoot();
        return;
    }

    $paged = chlogPaginate($commits, $page, $config['perpage']);
    $totcom = $paged['total'];
    $totpage = $paged['pages'];
    $page = $paged['page'];
    $compg = $paged['items'];

    if ($config['grpdate']) {
        $compg = chlogGroupCommitsByDate($compg);
    }

    $cont .= $tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('changelog', [
        'action_url' => $afile.'.php',
        'reset_url' => $afile.'.php?name=changelog',
        'hidden' => ['name_attr' => 'name', 'value_attr' => 'changelog'],
        'search_field' => ['itype' => 'text', 'input_id' => 'search', 'name_attr' => 'word', 'value_attr' => chlogEsc($filters['search']), 'placeholder_text' => _CHLOG_SEARCH_PH],
        'author_field' => ['itype' => 'text', 'input_id' => 'author', 'name_attr' => 'author', 'value_attr' => chlogEsc($filters['author']), 'placeholder_text' => _CHLOG_AUTHOR_PH],
        'file_field' => ['itype' => 'text', 'input_id' => 'file', 'name_attr' => 'file', 'value_attr' => chlogEsc($filters['file']), 'placeholder_text' => _CHLOG_FILE_PH],
        'datefrom_field' => ['itype' => 'date', 'input_id' => 'datefrom', 'name_attr' => 'datefrom', 'value_attr' => chlogEsc($filters['since'])],
        'dateto_field' => ['itype' => 'date', 'input_id' => 'dateto', 'name_attr' => 'dateto', 'value_attr' => chlogEsc($filters['until'])],
        'filter_button' => ['button_type' => 'submit', 'submit_label' => _CHLOG_FILTER_BTN, 'is_chlog_primary' => true],
        'reset_button' => ['button_type' => 'button', 'submit_label' => _CHLOG_RESET_BTN, 'is_chlog_secondary' => true, 'reset_url' => $afile.'.php?name=changelog'],
        'show_file' => $config['source'] !== 'github',
        'totcount' => $totcount,
        'totcom' => $totcom,
        'page' => $page,
        'totpage' => $totpage,
        'commits' => chlogRenderCommits($compg, $config + ['highlight' => $filters['search']]),
        'paging' => rendpage($totcom, $totpage, $config['perpage'], $page, $filters),
        'txt_filter_heading' => _CHLOG_FILTER,
        'txt_search_label' => _CHLOG_SEARCH,
        'txt_search_placeholder' => _CHLOG_SEARCH_PH,
        'txt_author_label' => _CHLOG_AUTHOR,
        'txt_author_placeholder' => _CHLOG_AUTHOR_PH,
        'txt_file_label' => _CHLOG_FILE,
        'txt_file_placeholder' => _CHLOG_FILE_PH,
        'txt_datefrom_label' => _CHLOG_DATE_FROM,
        'txt_dateto_label' => _CHLOG_DATE_TO,
        'txt_filter_btn' => _CHLOG_FILTER_BTN,
        'txt_reset_btn' => _CHLOG_RESET_BTN,
        'txt_total' => _CHLOG_TOTAL,
        'txt_filtered' => _CHLOG_FILTERED,
        'txt_page' => _CHLOG_PAGE,
        'txt_commits' => _CHLOG_COMMITS,
        'txt_commits_repo' => _CHLOG_COMMITS_REPO,
    ])]);

    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $exporten = $conf['changelog']['exporten'] ?? true;
    $cont = getTplAdminTabs($exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=export&id=txt', 'name=changelog&op=export&id=md', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _DOCS],
        'tab'  => 1,
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DOCS],
        'tab'  => 1,
    ]);
    $source = chlogSource((string) ($conf['changelog']['source'] ?? 'local'));
    $sourceopts =
        $tpl->getHtmlFrag('select-option', ['value_attr' => 'local', 'label_text' => 'Local', 'is_selected' => $source === 'local']).
        $tpl->getHtmlFrag('select-option', ['value_attr' => 'github', 'label_text' => 'GitHub', 'is_selected' => $source === 'github']);
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        [
            'label_for' => 'f-source',
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CHLOG_SOURCE, 'hint' => _CHLOG_SOURCE_TITLE]),
            'field_html' => $tpl->getHtmlFrag('select', ['name_attr' => 'source', 'selectid' => 'f-source', 'options_html' => $sourceopts, 'is_config' => true]),
        ],
        [
            'label_for' => 'f-ghowner',
            'label_html' => _CHLOG_GH_OWNER,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'ghowner', 'input_id' => 'f-ghowner', 'value_attr' => chlogEsc($conf['changelog']['ghowner'] ?? ''), 'is_config' => true]),
            'is_hidden' => $source !== 'github',
        ],
        [
            'label_for' => 'f-ghrepo',
            'label_html' => _CHLOG_GH_REPO,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'ghrepo', 'input_id' => 'f-ghrepo', 'value_attr' => chlogEsc($conf['changelog']['ghrepo'] ?? ''), 'is_config' => true]),
            'is_hidden' => $source !== 'github',
        ],
        [
            'label_for' => 'f-ghtoken',
            'label_html' => _CHLOG_GH_TOKEN,
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'password', 'name_attr' => 'ghtoken', 'input_id' => 'f-ghtoken', 'value_attr' => chlogEsc($conf['changelog']['ghtoken'] ?? ''), 'is_config' => true]),
            'is_hidden' => $source !== 'github',
        ],
        [
            'label_for' => 'f-limit',
            'label_html' => $tpl->getHtmlFrag('label-hint', ['label' => _CHLOG_LIMIT, 'hint' => _CHLOG_STATS_TITLE]),
            'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'limit', 'input_id' => 'f-limit', 'value_attr' => (string)($conf['changelog']['limit'] ?? 50), 'is_config' => true, 'input_attr' => ' min="10" max="'.CHLOG_MAX_LIMIT.'"']),
        ],
        ['label_for' => 'f-perpage', 'label_html' => _CHLOG_PER_PAGE, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'perpage', 'input_id' => 'f-perpage', 'value_attr' => (string)($conf['changelog']['perpage'] ?? 10), 'is_config' => true, 'input_attr' => ' min="5" max="50"'])],
        ['label_for' => 'f-cachettl', 'label_html' => _CHLOG_CACHE_TTL, 'field_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'cachettl', 'input_id' => 'f-cachettl', 'value_attr' => (string)($conf['changelog']['cachettl'] ?? 900), 'is_config' => true, 'input_attr' => ' min="0" max="3600"'])],
        ['label_html' => _CHLOG_GROUP_DATE, 'field_html' => getTplRadioGroup(['name' => 'grpdate', 'value' => (string)($conf['changelog']['grpdate'] ?? 0), 'options' => $yesno])],
        ['label_html' => _CHLOG_SHOW_FILES, 'field_html' => getTplRadioGroup(['name' => 'showfile', 'value' => (string)($conf['changelog']['showfile'] ?? 0), 'options' => $yesno])],
        ['label_html' => _CHLOG_SHOW_STATS, 'field_html' => getTplRadioGroup(['name' => 'showstat', 'value' => (string)($conf['changelog']['showstat'] ?? 0), 'options' => $yesno])],
        ['label_html' => _CHLOG_EXPORT, 'field_html' => getTplRadioGroup(['name' => 'exporten', 'value' => (string)($conf['changelog']['exporten'] ?? 0), 'options' => $yesno])],
    ];
    $cont .= checkPerms(CONFIG_DIR.'/changelog.php');
    $body = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=changelog&op=configsave',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken('changelog')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile;
    $iswarn = !checkSiteToken(getVar('post', 'token', 'raw', ''), 'changelog');
    if (!$iswarn) {
        $confdata = [
            'source' => chlogSource(getVar('post', 'source', 'var', 'local')),
            'ghowner' => trim(getVar('post', 'ghowner', 'text', '')),
            'ghrepo' => trim(getVar('post', 'ghrepo', 'text', '')),
            'ghtoken' => trim(getVar('post', 'ghtoken', 'text', '')),
            'limit' => chlogClamp(getVar('post', 'limit', 'num', 50), 10, CHLOG_MAX_LIMIT),
            'perpage' => chlogClamp(getVar('post', 'perpage', 'num', 10), 5, 50),
            'cachettl' => chlogClamp(getVar('post', 'cachettl', 'num', 900), 0, 3600),
            'grpdate' => getVar('post', 'grpdate', 'num', 0),
            'showfile' => getVar('post', 'showfile', 'num', 0),
            'showstat' => getVar('post', 'showstat', 'num', 0),
            'exporten' => getVar('post', 'exporten', 'num', 0)
        ];
        setConfigFile('changelog.php', $confdata);
    }
    setRedirect($afile.'.php?name=changelog&op=config', false, 302, $iswarn ? _TOKENMISS : _SUCCSAVE, $iswarn);
}

function export(): void {
    global $conf;
    $format = getVar('get', 'id', 'var', 'txt');
    $loaded = chlogLoadCommits($conf, [], __DIR__.'/../../../');
    $commits = $loaded['commits'];
    $filename = 'changelog_'.date('Y-m-d').'.'.($format === 'md' ? 'md' : 'txt');

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    echo chlogBuildExport($commits, $format);
    exit;
}

function info(): void {
    global $conf;
    $exporten = $conf['changelog']['exporten'] ?? true;
    setTplAdminInfoPage($exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=export&id=txt', 'name=changelog&op=export&id=md', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _DOCS],
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _DOCS],
    ]);
}


function rendpage(int $totcom, int $totpage, int $perpage, int $page, array $filters): string {
    $query = http_build_query(array_filter([
        'name' => 'changelog',
        'author' => $filters['author'],
        'file' => $filters['file'],
        'word' => $filters['search'],
        'datefrom' => $filters['since'],
        'dateto' => $filters['until']
    ]));
    return getPageNumbers('', $totcom, $totpage, $perpage, ($query ? $query.'&' : 'name=changelog&'), 10, $page, '', 'page');
}

switch ($op) {
    default: changelog(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}
