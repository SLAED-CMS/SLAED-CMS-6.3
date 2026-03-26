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

    $srcsel = '<select name="source" class="sl_conf" onchange="toggleGithubFields(this.value)">'
        .'<option value="local"'.($source === 'local' ? ' selected' : '').'>'._CHLOG_SOURCE_LOCAL.'</option>'
        .'<option value="github"'.($source === 'github' ? ' selected' : '').'>'._CHLOG_SOURCE_GITHUB.'</option>'
        .'</select>';

    $rows = getAdminFormRow(_CHLOG_SOURCE.':<div class="sl_small">'._CHLOG_SOURCE_TITLE.'</div>', $srcsel);
    $rows .= '<tr class="chlog-gh-row"'.$ghdisplay.'><td>'._CHLOG_GH_OWNER.':</td><td><input type="text" name="ghowner" value="'.chlogEsc($conf['changelog']['ghowner'] ?? '').'" class="sl_conf" placeholder="'._CHLOG_GH_OWNER.'" autocomplete="off"></td></tr>';
    $rows .= '<tr class="chlog-gh-row"'.$ghdisplay.'><td>'._CHLOG_GH_REPO.':</td><td><input type="text" name="ghrepo" value="'.chlogEsc($conf['changelog']['ghrepo'] ?? '').'" class="sl_conf" placeholder="'._CHLOG_GH_REPO.'" autocomplete="off"></td></tr>';
    $rows .= '<tr class="chlog-gh-row"'.$ghdisplay.'><td>'._CHLOG_GH_TOKEN.':</td><td><input type="password" name="ghtoken" value="'.chlogEsc($conf['changelog']['ghtoken'] ?? '').'" class="sl_conf" placeholder="'._CHLOG_GH_TOKEN.'" autocomplete="new-password"></td></tr>';
    $rows .= getAdminFormRow(_CHLOG_LIMIT.':<div class="sl_small">'._CHLOG_STATS_TITLE.'</div>', '<input type="number" name="limit" value="'.($conf['changelog']['limit'] ?? 50).'" class="sl_conf" min="10" max="500" required>');
    $rows .= getAdminFormRow(_CHLOG_PER_PAGE.':', '<input type="number" name="perpage" value="'.($conf['changelog']['perpage'] ?? 10).'" class="sl_conf" min="5" max="50" required>');
    $rows .= getAdminFormRow(_CHLOG_CACHE_TTL.':', '<input type="number" name="cachettl" value="'.($conf['changelog']['cachettl'] ?? 900).'" class="sl_conf" min="0" max="3600" required>');
    $rows .= getAdminFormRow(_CHLOG_GROUP_DATE, radio_form($conf['changelog']['grpdate'] ?? 0, 'grpdate'));
    $rows .= getAdminFormRow(_CHLOG_SHOW_FILES, radio_form($conf['changelog']['showfile'] ?? 0, 'showfile'));
    $rows .= getAdminFormRow(_CHLOG_SHOW_STATS, radio_form($conf['changelog']['showstat'] ?? 0, 'showstat'));
    $rows .= getAdminFormRow(_CHLOG_EXPORT, radio_form($conf['changelog']['exporten'] ?? 0, 'exporten'));
    $rows .= getAdminFormWide('<input type="submit" value="'._SAVECHANGES.'" class="sl_but_blue">', '', 'sl_center');

    $hide = '<input type="hidden" name="name" value="changelog">'
        .'<input type="hidden" name="op" value="configsave">'
        .'<input type="hidden" name="token" value="'.chlogEsc(getSiteToken('changelog')).'">';

    $script = '<script>
    function toggleGithubFields(source) {
        var rows = document.querySelectorAll(".chlog-gh-row");
        for (var i = 0; i < rows.length; i++) {
            rows[i].style.display = (source === "github") ? "" : "none";
        }
    }
    </script>';

    $cont .= getAdminBox(getAdminForm($afile.'.php', $rows, $hide, 'sl_table_conf').$script);
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

