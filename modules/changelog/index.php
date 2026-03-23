<?php
# Author: Eduard Laas
# Copyright 2005 - 2026 SLAED
# License: GNU GPL 3
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
        'search' => $filters['search'] ?? '',
        'datefrom' => $filters['since'] ?? '',
        'dateto' => $filters['until'] ?? ''
    ]));
    $url = $query ? $query.'&' : '';

    $out = setPageNumbers('pagenum', $modname, $totcom, $totpage, $perpage, $url, 10, $page, '', 'page');
    return $out ?? '';
}

function changelog(): void {
    global $conf, $tpl;
    setHead(['title' => _CHANGELOG]);

    $page = max(1, getVar('get', 'page', 'num', 1));
    $filters = chlogReadFilters('get');
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

    $cont = setTemplateBasic('basic-changelog', [
        '{%action%}' => 'index',
        '{%name%}' => $conf['name'],
        '{%search%}' => chlogEsc($filters['search']),
        '{%author%}' => chlogEsc($filters['author']),
        '{%file%}' => chlogEsc($filters['file']),
        '{%datefrom%}' => chlogEsc($filters['since']),
        '{%dateto%}' => chlogEsc($filters['until']),
        '{%totcount%}' => $totcount,
        '{%totcom%}' => $totcom,
        '{%page%}' => $page,
        '{%totpage%}' => $totpage,
        '{%commits%}' => chlogRenderCommits($compg, $config),
        '{%paging%}' => chlogRenderPaging($conf['name'], $totcom, $totpage, $config['perpage'], $page, $filters),
        '{%txt_filter_heading%}' => _CHLOG_FILTER,
        '{%txt_search_label%}' => _CHLOG_SEARCH,
        '{%txt_search_placeholder%}' => _CHLOG_SEARCH_PH,
        '{%txt_author_label%}' => _CHLOG_AUTHOR,
        '{%txt_author_placeholder%}' => _CHLOG_AUTHOR_PH,
        '{%txt_file_label%}' => _CHLOG_FILE,
        '{%txt_file_placeholder%}' => _CHLOG_FILE_PH,
        '{%txt_datefrom_label%}' => _CHLOG_DATE_FROM,
        '{%txt_dateto_label%}' => _CHLOG_DATE_TO,
        '{%txt_filter_btn%}' => _CHLOG_FILTER_BTN,
        '{%txt_reset_btn%}' => _CHLOG_RESET_BTN,
        '{%txt_total%}' => _CHLOG_TOTAL,
        '{%txt_filtered%}' => _CHLOG_FILTERED,
        '{%txt_page%}' => _CHLOG_PAGE,
        '{%txt_commits%}' => _CHLOG_COMMITS,
        '{%txt_commits_repo%}' => _CHLOG_COMMITS_REPO
    ]);

    echo $cont;
    setFoot();
}

switch ($op) {
    default: changelog(); break;
}
