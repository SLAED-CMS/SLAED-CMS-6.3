<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('changelog')) die('Illegal file access');

require_once __DIR__.'/../common.php';

// ============================================================================
// MAIN FUNCTIONS
// ============================================================================

function changelog(): void {
    global $afile, $conf, $tpl;

    setHead();

    $_exporten = $conf['changelog']['exporten'] ?? true;
    $cont = setAdminNavi($_exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=export&amp;id=txt', 'name=changelog&amp;op=export&amp;id=md', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _INFO],
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/changelog.php');

    $page = max(1, getVar('get', 'page', 'num', 1));
    $filters = chlogReadFilters('get');
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

    $cont .= $tpl->getHtmlPart('changelog', [
        'aroute' => $afile,
        'search' => chlogEsc($filters['search']),
        'author' => chlogEsc($filters['author']),
        'file' => chlogEsc($filters['file']),
        'datefrom' => chlogEsc($filters['since']),
        'dateto' => chlogEsc($filters['until']),
        'totcount' => $totcount,
        'totcom' => $totcom,
        'page' => $page,
        'totpage' => $totpage,
        'commits' => chlogRenderCommits($compg, $config),
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
    ]);

    echo $cont;
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $_exporten = $conf['changelog']['exporten'] ?? true;
    $cont = setAdminNavi($_exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=export&amp;id=txt', 'name=changelog&amp;op=export&amp;id=md', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _INFO],
        'tab'  => 1,
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'tab'  => 1,
    ]);
    $cont .= checkPerms(CONFIG_DIR.'/changelog.php');

    $source = chlogSource((string) ($conf['changelog']['source'] ?? 'local'));
    $ghdisplay = $source === 'github' ? '' : ' style="display: none;"';
    $hide = '<input type="hidden" name="name" value="changelog">'
        .'<input type="hidden" name="op" value="configsave">'
        .'<input type="hidden" name="token" value="'.chlogEsc(getSiteToken('changelog')).'">';
    $cont .= getAdminBox(getAdminForm($afile.'.php', $tpl->getHtmlFrag('admin-chlog-config-rows', [
        'cachettl_label' => _CHLOG_CACHE_TTL.':',
        'cachettl_value' => (string)($conf['changelog']['cachettl'] ?? 900),
        'exporten_html' => radio_form($conf['changelog']['exporten'] ?? 0, 'exporten'),
        'exporten_label' => _CHLOG_EXPORT,
        'gh_owner_label' => _CHLOG_GH_OWNER.':',
        'gh_owner_value' => chlogEsc($conf['changelog']['ghowner'] ?? ''),
        'gh_repo_label' => _CHLOG_GH_REPO.':',
        'gh_repo_value' => chlogEsc($conf['changelog']['ghrepo'] ?? ''),
        'gh_token_label' => _CHLOG_GH_TOKEN.':',
        'gh_token_value' => chlogEsc($conf['changelog']['ghtoken'] ?? ''),
        'ghdisplay_attr' => $ghdisplay,
        'grpdate_html' => radio_form($conf['changelog']['grpdate'] ?? 0, 'grpdate'),
        'grpdate_label' => _CHLOG_GROUP_DATE,
        'limit_hint' => _CHLOG_STATS_TITLE,
        'limit_label' => _CHLOG_LIMIT.':',
        'limit_value' => (string)($conf['changelog']['limit'] ?? 50),
        'perpage_label' => _CHLOG_PER_PAGE.':',
        'perpage_value' => (string)($conf['changelog']['perpage'] ?? 10),
        'save_label' => _SAVECHANGES,
        'showfile_html' => radio_form($conf['changelog']['showfile'] ?? 0, 'showfile'),
        'showfile_label' => _CHLOG_SHOW_FILES,
        'showstat_html' => radio_form($conf['changelog']['showstat'] ?? 0, 'showstat'),
        'showstat_label' => _CHLOG_SHOW_STATS,
        'source_hint' => _CHLOG_SOURCE_TITLE,
        'source_html' => $tpl->getHtmlFrag('admin-chlog-source-select', [
            'is_github' => $source === 'github',
            'is_local' => $source === 'local',
        ]),
        'source_label' => _CHLOG_SOURCE.':',
    ]), $hide, 'sl_table_conf').$tpl->getHtmlFrag('admin-chlog-config-script'));
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile, $conf, $tpl;

    if (!checkSiteToken(getVar('post', 'token', 'raw', ''), 'changelog')) {
        setHead();
        $_exporten = $conf['changelog']['exporten'] ?? true;
        $cont = setAdminNavi($_exporten ? [
            'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=export&amp;id=txt', 'name=changelog&amp;op=export&amp;id=md', 'name=changelog&amp;op=info'],
            'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _INFO],
            'tab'  => 1,
        ] : [
            'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=info'],
            'tabs' => [_HOME, _PREFERENCES, _INFO],
            'tab'  => 1,
        ]);
        echo $cont.$tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => _CHLOG_ERR_TOKEN]);
        setFoot();
        return;
    }

    $confdata = [
        'source' => chlogSource(getVar('post', 'source', 'var', 'local')),
        'ghowner' => trim(getVar('post', 'ghowner', 'text', '')),
        'ghrepo' => trim(getVar('post', 'ghrepo', 'text', '')),
        'ghtoken' => trim(getVar('post', 'ghtoken', 'text', '')),
        'limit' => chlogClamp(getVar('post', 'limit', 'num', 50), 10, 500),
        'perpage' => chlogClamp(getVar('post', 'perpage', 'num', 10), 5, 50),
        'cachettl' => chlogClamp(getVar('post', 'cachettl', 'num', 900), 0, 3600),
        'grpdate' => getVar('post', 'grpdate', 'num', 0),
        'showfile' => getVar('post', 'showfile', 'num', 0),
        'showstat' => getVar('post', 'showstat', 'num', 0),
        'exporten' => getVar('post', 'exporten', 'num', 0)
    ];

    setConfigFile('changelog.php', $confdata);
    setRedirect($afile.'.php?name=changelog&op=config');
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
    setHead();
    $_exporten = $conf['changelog']['exporten'] ?? true;
    $cont = setAdminNavi($_exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=export&amp;id=txt', 'name=changelog&amp;op=export&amp;id=md', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _INFO],
        'tab'  => 4,
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&amp;op=config', 'name=changelog&amp;op=info'],
        'tabs' => [_HOME, _PREFERENCES, _INFO],
        'tab'  => 4,
    ]);
    echo $cont.getAdminInfoBox(getAdminInfo());
    setFoot();
}


function rendpage(int $totcom, int $totpage, int $perpage, int $page, array $filters): string {
    $query = http_build_query(array_filter([
        'name' => 'changelog',
        'author' => $filters['author'],
        'file' => $filters['file'],
        'search' => $filters['search'],
        'datefrom' => $filters['since'],
        'dateto' => $filters['until']
    ]));
    $url = $query ? $query.'&' : 'name=changelog&';

    return setPageNumbers(
        'pagenum', 'changelog', $totcom, $totpage, $perpage,
        $url, 10, $page, '', 'page'
    );
}

// ============================================================================
// ROUTING
// ============================================================================

switch ($op) {
    default: changelog(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}
