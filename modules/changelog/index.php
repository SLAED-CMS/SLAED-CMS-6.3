<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

require_once __DIR__.'/common.php';

function chlogRenderPaging(string $modname, int $totcom, int $totpage, int $perpage, int $page, array $filters): string {
    $query = http_build_query(array_filter([
        'author' => $filters['author'] ?? '',
        'file' => $filters['file'] ?? '',
        'word' => $filters['search'] ?? '',
        'datefrom' => $filters['since'] ?? '',
        'dateto' => $filters['until'] ?? ''
    ]));
    $url = $query ? $query.'&' : '';

    $out = getPageNumbers($modname, $totcom, $totpage, $perpage, $url, 10, $page, '', 'page');
    return $out ?? '';
}

function changelog(): void {
    global $conf, $tpl;
    setHead(['title' => _CHANGELOG]);

    $page = max(1, getVar('get', 'page', 'num', 1));
    $filters = chlogReadFilters('get');
    chlogCanonicalRedirect('index.php', $conf['name'], $filters, $page);
    $config = chlogGetConfig($conf);
    $loaded = chlogLoadCommits($conf, $filters, defined('BASE_DIR') ? (string)BASE_DIR : getcwd());
    $commits = $loaded['commits'];
    $totcount = $loaded['total'];
    $error = (string)$loaded['error'];

    if (empty($commits)) {
        $warnText = $error !== '' ? $error : _CHLOG_ERR_NO_COMMITS;
        $warnType = $error !== '' ? 'warn' : 'info';
        echo $tpl->getHtmlFrag('alert', ['is_warn' => ($warnType !== 'info'), 'text' => $warnText]);
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

    $cont = $tpl->getHtmlPart('changelog', [
        'action_url' => 'index.php?name='.$conf['name'],
        'hidden' => ['name_attr' => 'name', 'value_attr' => $conf['name']],
        'search_field' => ['itype' => 'text', 'input_id' => 'search', 'name_attr' => 'word', 'value_attr' => chlogEsc($filters['search']), 'placeholder_text' => _CHLOG_SEARCH_PH],
        'author_field' => ['itype' => 'text', 'input_id' => 'author', 'name_attr' => 'author', 'value_attr' => chlogEsc($filters['author']), 'placeholder_text' => _CHLOG_AUTHOR_PH],
        'file_field' => ['itype' => 'text', 'input_id' => 'file', 'name_attr' => 'file', 'value_attr' => chlogEsc($filters['file']), 'placeholder_text' => _CHLOG_FILE_PH],
        'datefrom_field' => ['itype' => 'date', 'input_id' => 'datefrom', 'name_attr' => 'datefrom', 'value_attr' => chlogEsc($filters['since'])],
        'dateto_field' => ['itype' => 'date', 'input_id' => 'dateto', 'name_attr' => 'dateto', 'value_attr' => chlogEsc($filters['until'])],
        'filter_button' => ['button_type' => 'submit', 'submit_label' => _CHLOG_FILTER_BTN, 'is_changelog_primary' => true],
        'reset_button' => ['button_type' => 'button', 'submit_label' => _CHLOG_RESET_BTN, 'is_changelog_secondary' => true, 'reset_url' => 'index.php?name='.$conf['name']],
        'search' => chlogEsc($filters['search']),
        'author' => chlogEsc($filters['author']),
        'file' => chlogEsc($filters['file']),
        'datefrom' => chlogEsc($filters['since']),
        'dateto' => chlogEsc($filters['until']),
        'reset_url' => 'index.php?name='.$conf['name'],
        'show_file' => $config['source'] !== 'github',
        'totcount' => $totcount,
        'totcom' => $totcom,
        'page' => $page,
        'totpage' => $totpage,
        'commits' => chlogRenderCommits($compg, $config + ['highlight' => $filters['search']]),
        'paging' => chlogRenderPaging($conf['name'], $totcom, $totpage, $config['perpage'], $page, $filters),
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

switch ($op) {
    default: changelog(); break;
}
