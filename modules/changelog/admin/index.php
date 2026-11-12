<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !is_admin_modul('changelog')) die('Illegal file access');

require_once __DIR__.'/../common.php';

function changelog(): void {
    global $afile, $conf, $tpl, $prs;
    setHead();
    $exporten = $conf['changelog']['exporten'] ?? true;
    $flt = chlogReadFilters('get');
    $config = chlogGetConfig($conf);
    $loaded = chlogLoadCommits($conf, $flt, __DIR__.'/../../../');
    $error = (string)$loaded['error'];
    $word = $flt['search'];
    $keys = array_filter(['word' => $word, 'author' => $flt['author'], 'file' => $flt['file'], 'datefrom' => $flt['since'], 'dateto' => $flt['until']], fn($v) => $v !== '');
    $stat = $config['showstat'] && $config['source'] !== 'github';
    $total = count($loaded['commits']);
    $limit = getListLimit($total);
    $pages = max(1, (int)ceil($total / $limit));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $rows = [];
    foreach (array_slice($loaded['commits'], ($num - 1) * $limit, $limit) as $one) {
        $title = (string)($one['subject'] ?? '');
        $body = (string)($one['body'] ?? '');
        $files = is_array($one['files'] ?? null) ? $one['files'] : [];
        $html = ($body !== '' && $body !== CHLOG_COMMIT_END) ? $prs->filterDoc($body, true, 'changelog') : '';
        $tip = $html !== '' ? [['label' => _DESCRIPTION, 'value' => ($word !== '') ? filterTextHighlight($html, $word) : $html]] : [];
        if (($one['email'] ?? '') !== '') $tip[] = ['label' => _EMAIL, 'has_value_text' => true, 'value_text' => (string)$one['email']];
        foreach (($stat && $config['showfile']) ? $files : [] as $i => $file) {
            $tip[] = ['label' => $i ? '' : _CHLOG_FILES, 'has_value_text' => true,
                'value_text' => '+'.intval($file['added'] ?? 0).' -'.intval($file['deleted'] ?? 0).' '.($file['file'] ?? '')];
        }
        $add = array_sum(array_map('intval', array_column($files, 'added')));
        $del = array_sum(array_map('intval', array_column($files, 'deleted')));
        $subj = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $rows[] = ['name' => $title, 'details' => $tip, 'cells' => [
            ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)($one['hash'] ?? '')],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $title, 'content_html' => ($word !== '') ? filterTextHighlight($subj, $word) : $subj],
            ['is_col_author' => true, 'title_text' => (string)($one['email'] ?? ''), 'has_content_text' => true, 'content_text' => (string)($one['author'] ?? '')],
            ...($stat ? [
                ['attr' => 'data-sort="'.($add + $del).'"', 'has_content_text' => true, 'content_text' => '+'.$add.' / -'.$del],
                ['is_col_count' => true, 'has_content_text' => true, 'content_text' => (string)count($files)],
            ] : []),
            ['is_col_date' => true, 'moment' => (string)($one['date'] ?? '')],
        ]];
    }
    $fields = [
        ['field_id' => 'sl-chlog-word', 'label' => _CHLOG_SEARCH, 'itype' => 'search', 'name_attr' => 'word', 'value' => $word],
        ['field_id' => 'sl-chlog-author', 'label' => _CHLOG_AUTHOR, 'itype' => 'text', 'name_attr' => 'author', 'value' => $flt['author']],
        ...(($config['source'] !== 'github') ? [['field_id' => 'sl-chlog-file', 'label' => _CHLOG_FILE, 'itype' => 'text', 'name_attr' => 'file', 'value' => $flt['file']]] : []),
        ['field_id' => 'sl-chlog-from', 'label' => _CHLOG_DATE_FROM, 'itype' => 'date', 'name_attr' => 'datefrom', 'value' => $flt['since']],
        ['field_id' => 'sl-chlog-to', 'label' => _CHLOG_DATE_TO, 'itype' => 'date', 'name_attr' => 'dateto', 'value' => $flt['until']],
    ];
    $search = $tpl->getHtmlFrag('list-filter', ['action_url' => $afile.'.php', 'label' => _SEARCH, 'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'changelog']], 'fields' => $fields]);
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(($exporten ? [
            'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=export&id=txt', 'name=changelog&op=export&id=md', 'name=changelog&op=info'],
            'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _MANUAL],
        ] : [
            'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
            'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        ]) + ['part' => [0], 'subtitle_html' => $search]),
        'note_html' => checkPerms(CONFIG_DIR.'/changelog.php'),
        'is_fixed' => true,
        'head' => [
            ['content' => _CHLOG_COMMIT, 'is_col_count' => true],
            ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true],
            ['content' => _CHLOG_AUTHOR, 'is_col_author' => true],
            ...($stat ? [['content' => _CHLOG_CHANGES], ['content' => _CHLOG_FILES, 'is_col_count' => true]] : []),
            ['content' => _CHLOG_DATE, 'is_col_date' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?'.http_build_query(['name' => 'changelog'] + $keys, '', '&', PHP_QUERY_RFC3986).'&',
        'page' => $num,
        'pages' => $pages,
        'count' => $total,
        'limit' => $limit,
        'none_text' => $error,
        'reset_url' => ($error === '' && $keys) ? $afile.'.php?name=changelog' : '',
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $exporten = $conf['changelog']['exporten'] ?? true;
    $cont = getTplAdminTabs($exporten ? [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=export&id=txt', 'name=changelog&op=export&id=md', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _MANUAL],
        'tab'  => 1,
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
        'tab'  => 1,
    ]);
    $source = chlogSource((string) ($conf['changelog']['source'] ?? 'local'));
    $sourceopts =
        [['value_attr' => 'local', 'label_text' => 'Local', 'is_selected' => $source === 'local'],
        ['value_attr' => 'github', 'label_text' => 'GitHub', 'is_selected' => $source === 'github']];
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $rows = [
        ...(isAdmin(true) ? [
            [
                'label_for' => 'f-source',
                'label_html' => _CHLOG_SOURCE, 'hint_html' => _CHLOG_SOURCE_TITLE, 'hint_id' => $hntid = getFieldIds('f-source')['hint'],
                'field_html' => $tpl->getHtmlFrag('select', [
                    'describedby' => $hntid, 'name_attr' => 'source', 'selectid' => 'f-source', 'options' => $sourceopts, 'is_config' => true,
                ]),
            ],
            [
                'label_for' => 'f-ghowner',
                'label_html' => _CHLOG_GH_OWNER,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text', 'name_attr' => 'ghowner', 'input_id' => 'f-ghowner', 'value_attr' => chlogEsc($conf['changelog']['ghowner'] ?? ''), 'is_config' => true,
                ]),
                'is_hidden' => $source !== 'github',
            ],
            [
                'label_for' => 'f-ghrepo',
                'label_html' => _CHLOG_GH_REPO,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'text', 'name_attr' => 'ghrepo', 'input_id' => 'f-ghrepo', 'value_attr' => chlogEsc($conf['changelog']['ghrepo'] ?? ''), 'is_config' => true,
                ]),
                'is_hidden' => $source !== 'github',
            ],
            [
                'label_for' => 'f-ghtoken',
                'label_html' => _CHLOG_GH_TOKEN,
                'field_html' => $tpl->getHtmlFrag('input', [
                    'itype' => 'password', 'name_attr' => 'ghtoken', 'input_id' => 'f-ghtoken', 'value_attr' => chlogEsc($conf['changelog']['ghtoken'] ?? ''), 'is_config' => true,
                ]),
                'is_hidden' => $source !== 'github',
            ],
        ] : []),
        [
            'label_for' => 'f-limit',
            'label_html' => _CHLOG_LIMIT, 'hint_html' => _CHLOG_STATS_TITLE, 'hint_id' => $hntid = getFieldIds('f-limit')['hint'],
            'field_html' => $tpl->getHtmlFrag('input', [
                'describedby' => $hntid, 'itype' => 'number', 'name_attr' => 'limit', 'input_id' => 'f-limit', 'value_attr' => (string)($conf['changelog']['limit'] ?? 50),
                'is_config' => true, 'input_attr' => ' min="10" max="'.CHLOG_MAX_LIMIT.'"',
            ]),
        ],
        ...getTplPagerFields($conf['changelog'] ?? []),
        [
            'label_for' => 'f-cachettl', 'label_html' => _CHLOG_CACHE_TTL,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number', 'name_attr' => 'cachettl', 'input_id' => 'f-cachettl', 'value_attr' => (string)($conf['changelog']['cachettl'] ?? 900), 'is_config' => true,
                'input_attr' => ' min="0" max="3600"',
            ]),
        ],
        [
            'label_html' => _CHLOG_GROUP_DATE, 'label_id' => $labid = getFieldIds('', 'grpdate')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'grpdate', 'value' => (string)($conf['changelog']['grpdate'] ?? 0), 'options' => $yesno]),
        ],
        [
            'label_html' => _CHLOG_SHOW_FILES, 'label_id' => $labid = getFieldIds('', 'showfile')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'showfile', 'value' => (string)($conf['changelog']['showfile'] ?? 0), 'options' => $yesno]),
        ],
        [
            'label_html' => _CHLOG_SHOW_STATS, 'label_id' => $labid = getFieldIds('', 'showstat')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'showstat', 'value' => (string)($conf['changelog']['showstat'] ?? 0), 'options' => $yesno]),
        ],
        [
            'label_html' => _CHLOG_EXPORT, 'label_id' => $labid = getFieldIds('', 'exporten')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'exporten', 'value' => (string)($conf['changelog']['exporten'] ?? 0), 'options' => $yesno]),
        ],
    ];
    $cont .= checkPerms(CONFIG_DIR.'/changelog.php');
    $body = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php?name=changelog&op=configsave',
        'hidden' => [
            ['nameattr' => 'token', 'valueattr' => getSiteToken('changelog')],
        ],
        'content_html' => $tpl->getHtmlFrag('pane', ['icon' => 'sliders', 'title' => _GENPREF, 'content_html' => $tpl->getHtmlPart('div', ['rows' => $rows])]),
        'submit_label' => _SAVECHANGES,
    ]);
    $cont .= $tpl->getHtmlPart('box', ['content_html' => $body]);
    echo $cont;
    setFoot();
}

function configsave(): void {
    global $afile, $conf;
    $iswarn = !checkAdminPost('changelog');
    $text = $iswarn ? _TOKENMISS : _SUCCSAVE;
    $part = array_filter(array_map(fn(string $v): string => getVar('post', $v, 'raw', ''), ['source', 'ghowner', 'ghrepo', 'ghtoken']), fn(string $v): bool => $v !== '');
    if (!$iswarn && !isAdmin(true) && $part) [$iswarn, $text] = [true, _ACCESSDENIED];
    if (!$iswarn) {
        $confdata = (isAdmin(true) ? [
            'source' => chlogSource(getVar('post', 'source', 'var', 'local')),
            'ghowner' => trim(getVar('post', 'ghowner', 'text', '')),
            'ghrepo' => trim(getVar('post', 'ghrepo', 'text', '')),
            'ghtoken' => trim(getVar('post', 'ghtoken', 'text', '')),
        ] : array_intersect_key($conf['changelog'] ?? [], array_flip(['source', 'ghowner', 'ghrepo', 'ghtoken']))) + [
            'limit' => chlogClamp(getVar('post', 'limit', 'num', 50), 10, CHLOG_MAX_LIMIT),
            ...getPagerPost(),
            'cachettl' => chlogClamp(getVar('post', 'cachettl', 'num', 900), 0, 3600),
            'grpdate' => getVar('post', 'grpdate', 'num', 0),
            'showfile' => getVar('post', 'showfile', 'num', 0),
            'showstat' => getVar('post', 'showstat', 'num', 0),
            'exporten' => getVar('post', 'exporten', 'num', 0)
        ];
        $iswarn = !setConfigFile('changelog.php', $confdata);
        if ($iswarn) $text = getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP;
    }
    setRedirect($afile.'.php?name=changelog&op=config', false, 302, $text, $iswarn);
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
        'tabs' => [_HOME, _PREFERENCES, _CHLOG_EXPORT_TXT, _CHLOG_EXPORT_MD, _MANUAL],
    ] : [
        'ops'  => ['name=changelog', 'name=changelog&op=config', 'name=changelog&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: changelog(); break;
    case 'config': config(); break;
    case 'configsave': configsave(); break;
    case 'export': export(); break;
    case 'info': info(); break;
}
