<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');

# The filter of the favorites as the request carries it: the table and condition the list, its count and a sweep of the foot read, and the query that keeps it
function getFavoritesFilter(string $src = 'get'): array {
    $modul = getVar($src, 'modul', 'var');
    $search = (getVar($src, 'search', 'num', 2) === 1) ? 1 : 2;
    $chng = trim(getVar($src, 'chng'));
    $cond = [];
    $pars = [];
    if ($modul !== '') {
        $cond[] = 'f.modul = :modul';
        $pars['modul'] = $modul;
    }
    if ($chng !== '') {
        $cond[] = (($search === 1) ? 'f.id' : 'u.name').' LIKE :find';
        $pars['find'] = '%'.$chng.'%';
    }
    $keys = array_filter(['modul' => $modul, 'search' => $search, 'chng' => $chng], static fn($v) => $v !== '');
    return ['modul' => $modul, 'search' => $search, 'chng' => $chng, 'from' => PREFIX_DB.'_favorites AS f LEFT JOIN '.PREFIX_DB.'_users AS u ON (f.uid = u.id)',
        'where' => $cond ? ' WHERE '.implode(' AND ', $cond) : '', 'pars' => $pars, 'base' => 'name=favorites&'.http_build_query($keys, '', '&', PHP_QUERY_RFC3986)];
}

# The filter bar of the favorites: the modules their rows hold, then the column and the text of the search
function getFavoritesSearch(array $flt): string {
    global $afile, $db, $tpl;
    $mods = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $flt['modul'] === '']];
    foreach ($db->getSqlRows($db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_favorites ORDER BY modul')) ?: [] as $row) {
        $mods[] = ['value_attr' => (string)$row['modul'], 'label_text' => getModuleName((string)$row['modul']), 'is_selected' => $flt['modul'] === $row['modul']];
    }
    $finds = [];
    foreach ([1 => _ID, 2 => _POSTEDBY] as $key => $label) $finds[] = ['value_attr' => (string)$key, 'label_text' => $label, 'is_selected' => $flt['search'] === $key];
    return $tpl->getHtmlFrag('list-filter', [
        'action_url' => $afile.'.php',
        'label' => _SEARCH,
        'is_part' => true,
        'hidden' => [['nameattr' => 'name', 'valueattr' => 'favorites']],
        'fields' => [
            ['field_id' => 'sl-fav-mod', 'label' => _MODUL, 'name_attr' => 'modul', 'options' => $mods, 'is_now' => true],
            ['field_id' => 'sl-fav-in', 'label' => _SEARCH, 'name_attr' => 'search', 'options' => $finds, 'find_id' => 'sl-fav-q', 'find_name' => 'chng',
                'find_value' => $flt['chng']],
        ],
    ]);
}

# The favorites of every user, newest first, each with the title of the material it marks, read per module in one query
function favorites(): void {
    global $afile, $db, $conf;
    setHead();
    $flt = getFavoritesFilter();
    $chng = $flt['chng'];
    $res = $db->getSqlQuery('SELECT COUNT(f.id), MAX(f.id) FROM '.$flt['from'].$flt['where'], $flt['pars']);
    [$total, $upto] = array_map('intval', $res->fetch(PDO::FETCH_NUM));
    $limit = getListLimit(intval($conf['favorites']['anum']), $total);
    $pages = max(1, (int)ceil($total / $limit));
    $num = min(max(1, getVar('get', 'num', 'num', 1)), $pages);
    $sql = 'SELECT f.id, f.fid, f.modul, u.name, f.time FROM '.$flt['from'].$flt['where'].' ORDER BY f.id DESC';
    $list = $db->getSqlQuery($sql.' LIMIT '.(($num - 1) * $limit).', '.$limit, $flt['pars'])->fetchAll(PDO::FETCH_NUM);
    parse_str($flt['base'].'&num='.$num, $keep);
    $want = [];
    foreach ($list as [, $fid, $mod]) $want[$mod][intval($fid)] = $mod;
    $names = [];
    foreach ($want as $mod => $refs) {
        if ($mod === 'forum') {
            $pars = [];
            foreach (array_keys($refs) as $i => $one) $pars['f'.$i] = $one;
            $res = $db->getSqlQuery('SELECT id, title FROM '.PREFIX_DB.'_forum WHERE id IN (:'.implode(', :', array_keys($pars)).')', $pars);
            $names[$mod] = $res ? $res->fetchAll(PDO::FETCH_KEY_PAIR) : [];
        } else {
            $names[$mod] = getNodeTitleMap($refs);
        }
    }
    $rows = [];
    foreach ($list as [$id, $fid, $mod, $uname, $time]) {
        $who = $uname ? user_info($uname, null) : '';
        $title = getDecodedText((string)($names[$mod][intval($fid)] ?? ''));
        $rows[] = ['name' => ($title !== '') ? cutstr($title, 60) : _ID.' '.$id,
            'drop' => getTplPostAction($keep + ['op' => 'delete', 'id[]' => $id], 'trash', _ONDELETE), 'cells' => [
            ['is_col_id' => true, 'content_html' => ($flt['search'] === 1) ? filterTextHighlight((string)$id, $chng) : (string)$id],
            ['is_col_title' => true, 'is_truncate' => true, 'title_text' => $title, 'has_content_text' => true, 'content_text' => $title],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => getModuleName($mod)],
            ['is_col_author' => true, 'content_html' => $uname ? (($flt['search'] === 2) ? filterTextHighlight($who, $chng) : $who) : _ANONYM],
            ['is_col_date' => true, 'moment' => $time],
            ['is_col_actions' => true, 'dial' => [['href' => 'index.php?name='.$mod.'&op=view&id='.$fid.'#'.$fid, 'icon_name' => 'eye', 'title' => _MVIEW]]],
        ]];
    }
    echo getTplAdminList([
        'head_html' => getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL],
            'part' => [0], 'subtitle_html' => getFavoritesSearch($flt)]),
        'is_fixed' => true,
        'head' => [
            ['content' => _ID, 'is_col_id' => true],
            ['content' => _TITLE, 'is_col_title' => true, 'is_truncate' => true],
            ['content' => _MODUL, 'is_truncate' => true],
            ['content' => _POSTEDBY, 'is_col_author' => true],
            ['content' => _DATE, 'is_col_date' => true],
            ['content' => _FUNCTIONS, 'is_col_actions' => true, 'nosort' => true],
        ],
        'rows' => $rows,
        'href' => $afile.'.php?'.$flt['base'].'&',
        'page' => $num,
        'pages' => $pages,
        'maxpg' => intval($conf['favorites']['anump']),
        'count' => $total,
        'limit' => $limit,
        'base' => intval($conf['favorites']['anum']),
        'upto' => $upto,
        'none_text' => ($flt['where'] !== '') ? _NONE_FOUND : _NO_INFO,
    ]);
    setFoot();
}

function config(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'], 'tabs' => [_HOME, _PREFERENCES, _MANUAL], 'tab' => 1]);
    $cont .= checkPerms(CONFIG_DIR.'/favorites.php');
    $rows = [
        [
            'label_for' => 'f-num',
            'label_html' => _C_33,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'num',
                'input_id' => 'f-num',
                'value_attr' => (string)$conf['favorites']['num'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anum',
            'label_html' => _C_34,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anum',
                'input_id' => 'f-anum',
                'value_attr' => (string)$conf['favorites']['anum'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-nump',
            'label_html' => _C_35,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'nump',
                'input_id' => 'f-nump',
                'value_attr' => (string)$conf['favorites']['nump'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-anump',
            'label_html' => _C_36,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'anump',
                'input_id' => 'f-anump',
                'value_attr' => (string)$conf['favorites']['anump'],
                'is_config' => true,
            ]),
        ],
        [
            'label_for' => 'f-favorites',
            'label_html' => _FAVOR_UMAX,
            'field_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'favorites',
                'input_id' => 'f-favorites',
                'value_attr' => (string)$conf['favorites']['favorites'],
                'is_config' => true,
            ]),
        ],
        ['label_html' => _FAVOR_ACT, 'label_id' => $labid = getFieldIds('', 'favact')['label'], 'field_html' => getTplRadioGroup([
            'labelledby' => $labid,
            'name' => 'favact',
            'value' => (string)$conf['favorites']['favact'],
            'options' => [
                ['value' => '1', 'label' => _YES],
                ['value' => '0', 'label' => _NO],
            ],
        ])],
    ];
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'favorites'],
            ['nameattr' => 'op', 'valueattr' => 'save'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('favorites')],
        ],
        'rows' => $rows,
        'submit_label' => _SAVECHANGES,
    ])]);
    setFoot();
}

function save(): void {
    global $afile;
    $warn = !checkAdminPost('favorites');
    if (!$warn) {
        $cont = [
            'num' => getVar('post', 'num', 'num', 15),
            'anum' => getVar('post', 'anum', 'num', 15),
            'nump' => getVar('post', 'nump', 'num', 5),
            'anump' => getVar('post', 'anump', 'num', 5),
            'favorites' => getVar('post', 'favorites', 'num'),
            'favact' => getVar('post', 'favact', 'num')
        ];
        setConfigFile('favorites.php', $cont);
    }
    setRedirect($afile.'.php?name=favorites&op=config', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
}

function delete(): void {
    global $afile, $db;
    $ids = getVar('post', 'id[]', 'num');
    $num = getVar('post', 'num', 'num', 1);
    $warn = !checkAdminPost('favorites');
    $flt = getFavoritesFilter('post');
    $act = static function (int $id) use ($db): bool {
        $res = $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_favorites WHERE id = :id', ['id' => $id]);
        return $res && $res->rowCount() > 0;
    };
    if (!$warn && setListPortion('f.id', $flt['from'], $flt['where'], $flt['pars'], $act)) exit;
    $done = !$warn && $ids;
    foreach ($done ? $ids : [] as $id) {
        if (!$act(intval($id))) $done = false;
    }
    setRedirect($afile.'.php?'.$flt['base'].'&num='.$num, false, 302, $warn ? _TOKENMISS : ($done ? _SUCCDELETE : _ERROR), !$done);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=favorites', 'name=favorites&op=config', 'name=favorites&op=info'],
        'tabs' => [_HOME, _PREFERENCES, _MANUAL],
    ]);
}

switch ($op) {
    default: favorites(); break;
    case 'config': config(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'info': info(); break;
}
