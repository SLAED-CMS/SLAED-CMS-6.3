<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('ADMIN_FILE') || !isAdmin(true)) die('Illegal file access');


function groups(): void {
    global $db, $afile, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'], 'tabs' => [_HOME, _ADD, _POINTS, _MANUAL]]);
    $result = $db->getSqlQuery('SELECT g.id, g.name, g.intro, g.points, g.extra, g.`rank`, g.color, COUNT(u.id) FROM '.PREFIX_DB.'_groups AS g'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON ((g.extra = 1 AND u.grp = g.id) OR (g.extra != 1 AND u.points >= g.points))'
        .' GROUP BY g.id, g.name, g.intro, g.points, g.extra, g.`rank`, g.color ORDER BY g.points, g.id');
    $list = [];
    while ([$grid, $grname, $intro, $points, $extra, $rank, $color, $users] = $db->getSqlRow($result)) {
        $list[] = ['id' => (int)$grid, 'name' => (string)$grname, 'intro' => (string)$intro, 'points' => (int)$points, 'extra' => (bool)$extra,
            'rank' => (string)$rank, 'color' => (string)$color, 'users' => (int)$users];
    }
    $most = max([1, ...array_column($list, 'users')]);
    $steps = ['ladder' => [], 'extra' => []];
    foreach ($list as $row) {
        $steps[$row['extra'] ? 'extra' : 'ladder'][] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'intro' => $row['intro'],
            'is_extra' => $row['extra'],
            'is_empty' => $row['users'] === 0,
            'points' => $row['points'],
            'points_text' => number_format($row['points'], 0, '', ' '),
            'users' => $row['users'],
            'users_text' => _USERSCOUNT.': '.number_format($row['users'], 0, '', ' '),
            'users_url' => $afile.'.php?name=account&search='.($row['extra'] ? '6&chng='.$row['id'] : '7&chng='.$row['points']),
            'users_title' => _GROUPS_SHOWUSERS,
            'empty_text' => _GROUPS_EMPTY,
            'mark_text' => _SPEC,
            'color' => preg_match('/^#[0-9a-f]{6}$/i', $row['color']) ? $row['color'] : '',
            'share' => round($row['users'] / $most, 3),
            'rank_html' => getRankView($row['rank'], _GROUP.': '.$row['name']),
            'edit_url' => $afile.'.php?name=groups&op=add&id='.$row['id'],
            'edit_title' => _GROUPS_EDIT,
            'dial_html' => $tpl->getHtmlFrag('dial', [
                'dial_title' => _FUNCTIONS,
                'dial' => [
                    ['href' => $afile.'.php?name=groups&op=add&id='.$row['id'], 'icon_name' => 'pencil', 'title' => _FULLEDIT],
                    getTplPostAction(['name' => 'groups', 'op' => 'delete', 'id' => $row['id']], 'trash', _ONDELETE, _DELETE.' "'.$row['name'].'"?'),
                ],
            ]),
        ];
    }
    usort($steps['extra'], fn($a, $b) => ($b['users'] <=> $a['users']) ?: ($a['id'] <=> $b['id']));
    $heads = [
        'ladder' => ['list_id' => 'sl-ladder-points', 'icon_name' => 'bar-chart-steps', 'title' => _GROUPS_LADDER, 'note' => _GROUPS_LADDERI,
            'sort_key' => 'points', 'sort_title' => _GROUPS_BYPOINTS, 'is_desc' => false, 'is_extra' => false],
        'extra' => ['list_id' => 'sl-ladder-extra', 'icon_name' => 'patch-check', 'title' => _GROUPS_SPECIAL, 'note' => _GROUPS_SPECIALI,
            'sort_key' => 'users', 'sort_title' => _GROUPS_BYUSERS, 'is_desc' => true, 'is_extra' => true],
    ];
    foreach ($heads as $key => $head) {
        if ($steps[$key]) $cont .= $tpl->getHtmlPart('group-ladder', $head + ['steps' => $steps[$key]]);
    }
    if (!$list) $cont .= $tpl->getHtmlFrag('alert', ['text' => _NO_INFO]);
    echo $cont;
    setFoot();
}

function getRankPresets(): array {
    return [
        [_GROUPS_STARS, 'star-fill:3:5:#8297a2'], [_GROUPS_GOLD, 'star-fill:5:5:#b0904d'], [_GROUPS_SUNS, 'sun-fill:5:5:#c68649'],
        [_GROUPS_GEARS, 'gear-fill:5:5:#629abf'], [_GROUPS_RINGS, 'record-circle-fill:5:5:#629abf'], [_GROUPS_GEMS, 'gem:3:5:#2e8fff'],
        [_GROUPS_CUPS, 'trophy-fill:3:3:#c68649'], [_GROUPS_AWARD, 'award-fill:1:1:#c18a05'], [_GROUPS_SHIELDS, 'shield-fill:3:3:#059600'],
        [_GROUPS_HEARTS, 'heart-fill:4:5:#e5484d'], [_GROUPS_BOLTS, 'lightning-fill:3:5:#c48800'], [_GROUPS_FIRES, 'fire:3:5:#ff5500'],
        [_GROUPS_RHOMBS, 'diamond-fill:4:5:#8c08ff'], [_GROUPS_ROCKET, 'rocket-takeoff-fill:1:1:#2e8fff'], [_GROUPS_SEAL, 'patch-check-fill:1:1:#2e8fff'],
        [_GROUPS_HIVES, 'hexagon-fill:3:5:#0561ca'], [_GROUPS_DROPS, 'droplet-fill:3:5:#2e8fff'], [_GROUPS_MOONS, 'moon-stars-fill:2:3:#b0904d'],
    ];
}

function getRankTones(): array {
    return [
        ['#8297a2', _GROUPS_STEEL], ['#888888', _GROUPS_GRAY], ['#8b5a2b', _GROUPS_BROWN], ['#e5484d', _GROUPS_RED],
        ['#e83e8c', _GROUPS_PINK], ['#ff5500', _GROUPS_ORANGE], ['#c68649', _GROUPS_BRONZE], ['#cf8300', _GROUPS_AMBER],
        ['#b0904d', _GROUPS_GOLD], ['#1eab1f', _GROUPS_LIME], ['#059600', _GROUPS_GREEN], ['#1ba77d', _GROUPS_TEAL],
        ['#629abf', _GROUPS_SKY], ['#2e8fff', _GROUPS_BLUE], ['#0561ca', _GROUPS_NAVY], ['#8c08ff', _GROUPS_VIOLET],
    ];
}

function getGroupTones(): array {
    return [
        ['#888888', _GROUPS_GRAY], ['#8b5a2b', _GROUPS_BROWN], ['#e5484d', _GROUPS_RED], ['#e83e8c', _GROUPS_PINK],
        ['#ff5500', _GROUPS_ORANGE], ['#cf8300', _GROUPS_AMBER], ['#929a00', _GROUPS_OLIVE], ['#1eab1f', _GROUPS_LIME],
        ['#059600', _GROUPS_GREEN], ['#1ba77d', _GROUPS_TEAL], ['#2e8fff', _GROUPS_BLUE], ['#0561ca', _GROUPS_NAVY],
        ['#3667a0', _GROUPS_NIGHT], ['#8c08ff', _GROUPS_VIOLET],
    ];
}

function getGroupNext(): array {
    global $db;
    $base = ['points' => 0, 'rank' => 'star-fill:1:5:#2e8fff', 'color' => '#2e8fff', 'after' => ''];
    $top = $db->getSqlRow($db->getSqlQuery('SELECT name, points, `rank`, color FROM '.PREFIX_DB.'_groups WHERE extra != 1 ORDER BY points DESC, id DESC LIMIT 1'));
    if (!$top) return $base;
    [$name, $points, $rank, $color] = $top;
    $points = (int)$points;
    $data = getRankData((string)$rank) ?: getRankData($base['rank']);
    $total = ($data['lit'] >= $data['total']) ? min(10, $data['total'] + 1) : $data['total'];
    $lit = min($total, $data['lit'] + 1);
    $step = ($points >= 1000) ? 1000 : 25;
    $tones = array_column(getGroupTones(), 0);
    $at = array_search(strtolower((string)$color), $tones, true);
    return [
        'points' => max($points + $step, (int)ceil($points * 1.5 / $step) * $step),
        'rank' => $data['icon'].':'.$lit.':'.$total.':'.$data['color'],
        'color' => $tones[($at === false) ? 0 : ($at + 1) % count($tones)],
        'after' => (string)$name,
    ];
}

function add(): void {
    global $db, $afile, $stop, $tpl;
    $id = getVar('req', 'id', 'num');
    $after = '';
    if ($stop) {
        $gid = getVar('post', 'gid', 'num');
        $grname = getVar('post', 'grname', 'title');
        $intro = getVar('post', 'description', 'text');
        $extra = getVar('post', 'grextra', 'num');
        $points = getVar('post', 'points', 'num');
        $color = strtolower(trim((string)getVar('post', 'color', 'raw')));
        $tone = getVar('post', 'rank_sync', 'num') ? $color : strtolower(trim((string)getVar('post', 'rank_color', 'raw')));
        $total = min(10, max(1, intval(getVar('post', 'rank_total', 'num'))));
        $rank = trim((string)getVar('post', 'rank_icon', 'raw')).':'.min($total, intval(getVar('post', 'rank_lit', 'num'))).':'.$total.':'.$tone;
    } elseif ($id) {
        $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, `rank`, color FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $id]);
        [$gid, $grname, $intro, $points, $extra, $rank, $color] = $db->getSqlRow($result) ?: [0, '', '', 0, 0, '', ''];
    } else {
        $next = getGroupNext();
        [$gid, $grname, $intro, $extra] = [0, '', '', 0];
        [$points, $rank, $color, $after] = [$next['points'], $next['rank'], $next['color'], $next['after']];
    }
    $color = preg_match('/^#[0-9a-f]{6}$/i', (string)$color) ? strtolower($color) : '#2e8fff';
    $data = getRankData((string)$rank) ?: getRankData('star-fill:1:5:'.$color);
    $rank = $data['icon'].':'.$data['lit'].':'.$data['total'].':'.$data['color'];
    $sync = $data['color'] === $color;
    $title = ($grname !== '') ? _GROUP.': '.$grname : _RANK;
    $presets = [];
    foreach (getRankPresets() as [$label, $prank]) $presets[] = ['rank' => $prank, 'label' => $label, 'rank_html' => getRankView($prank, $label), 'is_on' => $prank === $rank];
    $icons = [];
    foreach (['star-fill', 'sun-fill', 'gear-fill', 'record-circle-fill', 'gem', 'trophy-fill', 'award-fill', 'shield-fill', 'heart-fill', 'lightning-fill',
        'fire', 'diamond-fill', 'hexagon-fill', 'circle-fill', 'rocket-takeoff-fill', 'patch-check-fill', 'droplet-fill', 'moon-stars-fill', 'flower1',
        'suit-spade-fill', 'stars', 'star-half', 'suit-heart-fill', 'suit-club-fill', 'suit-diamond-fill', 'triangle-fill', 'square-fill', 'pentagon-fill',
        'octagon-fill', 'shield-shaded', 'bookmark-star-fill', 'flag-fill', 'key-fill', 'lightbulb-fill', 'gift-fill', 'music-note-beamed', 'tree-fill', 'snow',
        'emoji-smile-fill', 'mortarboard-fill'] as $name) {
        $icons[] = ['icon_name' => $name, 'is_on' => $name === $data['icon']];
    }
    $rtones = [];
    foreach (getRankTones() as [$hex, $label]) $rtones[] = ['color' => $hex, 'label' => $label, 'is_on' => !$sync && $hex === $data['color']];
    $gtones = [];
    foreach (getGroupTones() as [$hex, $label]) $gtones[] = ['color' => $hex, 'label' => $label, 'is_on' => $hex === $color];
    $ladder = [];
    $result = $db->getSqlQuery('SELECT id, name, points FROM '.PREFIX_DB.'_groups WHERE extra != 1 ORDER BY points, id');
    while ([$sid, $sname, $spts] = $db->getSqlRow($result)) if ((int)$sid !== (int)$gid) $ladder[] = ['name' => (string)$sname, 'points' => (int)$spts];
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'],
        'tabs' => [_HOME, _ADD, _POINTS, _MANUAL], 'tab' => 1]);
    if ($stop) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'text' => $stop]);
    $form = $tpl->getHtmlPart('group-form', [
        'action_url' => $afile.'.php',
        'list_url' => $afile.'.php?name=groups',
        'token' => getSiteToken('groups'),
        'gid' => (string)$gid,
        'next_html' => ($after !== '') ? $tpl->getHtmlFrag('alert', ['text' => sprintf(_GROUPS_NEXT, htmlspecialchars($after))]) : '',
        'steps_json' => json_encode($ladder, JSON_UNESCAPED_UNICODE),
        'leave_text' => _GROUPS_LEAVE,
        'title_group' => _GROUP.': ',
        'title_rank' => _RANK,
        'presets_title' => _GROUPS_PRESET,
        'presets_note' => _GROUPS_PRESETI,
        'presets' => $presets,
        'own_title' => _GROUPS_OWNRANK,
        'icon_title' => _ICON,
        'icon_label' => _GROUPS_RANKICON,
        'icons' => $icons,
        'picker_html' => $tpl->getHtmlPart('icon-picker', ['name_attr' => 'rank_icon', 'value_attr' => $data['icon'], 'placeholder_text' => _ICON,
            'button_label' => _ICONPICK, 'label_text' => _GROUPS_RANKICON]),
        'total_title' => _GROUPS_TOTAL,
        'lit_title' => _GROUPS_LIT,
        'all_text' => _ALL,
        'total' => $data['total'],
        'lit' => $data['lit'],
        'tone_title' => _GROUPS_RANKCOLOR,
        'rank_tones' => $rtones,
        'rank_color' => $data['color'],
        'is_rank_own' => !$sync && !in_array($data['color'], array_column($rtones, 'color'), true),
        'own_text' => _GROUPS_OWNCOLOR,
        'bind_text' => _GROUPS_BIND,
        'is_sync' => $sync,
        'rank_html' => getRankView($rank, $title),
        'light_text' => _GROUPS_LIGHT,
        'dark_text' => _GROUPS_DARK,
        'rank_text' => _RANK,
        'name_text' => _NAME,
        'tile_name' => ($grname !== '') ? $grname : _GROUP,
        'contrast_text' => _GROUPS_CONTRAST,
        'group_title' => _GROUP,
        'name_html' => $tpl->getHtmlFrag('input', ['itype' => 'text', 'name_attr' => 'grname', 'input_id' => 'f-grname', 'value_attr' => $grname,
            'placeholder_text' => _NAME, 'input_attr' => 'maxlength="255"', 'is_required' => true]),
        'intro_html' => $tpl->getHtmlFrag('textarea', ['name_attr' => 'description', 'input_id' => 'f-description', 'value_text' => $intro,
            'placeholder_text' => _DESCRIPTION]),
        'grname_label' => _NAME,
        'intro_label' => _DESCRIPTION,
        'color_label' => _GROUPS_COLOR,
        'color_hint' => _GROUPS_COLORI,
        'group_tones' => $gtones,
        'color' => $color,
        'is_group_own' => !in_array($color, array_column($gtones, 'color'), true),
        'extra_label' => _SPEC_GROUP,
        'extra_hint' => _GRSINFO,
        'extra_html' => $tpl->getHtmlFrag('checkbox', ['name_attr' => 'grextra', 'input_id' => 'f-grextra', 'value_attr' => '1', 'is_checked' => (bool)$extra,
            'describedby' => 'f-grextra-hint']),
        'is_extra' => (bool)$extra,
        'points_label' => _POINTSNEEDED,
        'points_hint' => _GROUPS_POINTSI,
        'points_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'points', 'input_id' => 'f-points', 'value_attr' => (string)intval($points),
            'placeholder_text' => _POINTSNEEDED, 'input_attr' => 'min="0"', 'describedby' => 'f-points-hint f-points-place']),
        'place_first' => _GROUPS_FIRST,
        'place_between' => _GROUPS_BETWEEN,
        'place_last' => _GROUPS_LAST,
        'place_only' => _GROUPS_ONLY,
        'place_taken' => _GROUPS_TAKEN,
        'cancel_label' => _CANCEL,
        'submit_label' => $gid ? _SAVE : _ADD,
        'is_dirty' => (bool)$stop,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $form]).getAdminIconWindow();
    setFoot();
}

function save(): void {
    global $db, $afile, $stop;
    $gid = getVar('post', 'gid', 'num');
    $stop = [];
    $warn = !checkAdminPost('groups');
    if (!$warn) {
        $grname = getVar('post', 'grname', 'title');
        $intro = getVar('post', 'description', 'text');
        $points = getVar('post', 'points', 'num');
        $grextra = getVar('post', 'grextra', 'num') ? 1 : 0;
        $color = strtolower(trim((string)getVar('post', 'color', 'raw')));
        $total = min(10, max(1, intval(getVar('post', 'rank_total', 'num'))));
        $lit = min($total, max(0, intval(getVar('post', 'rank_lit', 'num'))));
        $tone = getVar('post', 'rank_sync', 'num') ? $color : strtolower(trim((string)getVar('post', 'rank_color', 'raw')));
        $rank = trim((string)getVar('post', 'rank_icon', 'raw')).':'.$lit.':'.$total.':'.$tone;
        if (!$grname) $stop[] = _CERROR;
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) $stop[] = _GROUPS_BADCOLOR;
        if (!getRankData($rank)) $stop[] = _GROUPS_BADRANK;
        if (!is_numeric($points) && !$grextra) $stop[] = _NONUMVALUE;
        if (is_numeric($points) && !$grextra) {
            $taken = $db->getSqlRow($db->getSqlQuery('SELECT name, points FROM '.PREFIX_DB.'_groups WHERE extra != 1 AND points = :points AND id != :id LIMIT 1', [
                'points' => $points, 'id' => intval($gid),
            ]));
            if ($taken) $stop[] = sprintf(_GROUPS_TAKEN, htmlspecialchars((string)$taken[0]), number_format((int)$taken[1], 0, '', ' '));
        }
    }
    if ($warn || !$stop) {
        if (!$warn) {
            $points = $grextra ? 0 : $points;
            if ($gid) {
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_groups SET name = :name, intro = :intro, points = :points, extra = :extra, `rank` = :rank, color = :color WHERE id = :id', [
                    'name' => $grname, 'intro' => $intro, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color, 'id' => $gid
                ]);
            } else {
                $db->getSqlQuery('INSERT INTO '.PREFIX_DB.'_groups (name, intro, points, extra, `rank`, color) VALUES (:name, :intro, :points, :extra, :rank, :color)', [
                    'name' => $grname, 'intro' => $intro, 'points' => $points, 'extra' => $grextra, 'rank' => $rank, 'color' => $color
                ]);
            }
        }
        setRedirect($afile.'.php?name=groups', false, 302, $warn ? _TOKENMISS : _SUCCSAVE, $warn);
    } else {
        $stop = implode("\n", $stop);
        add();
    }
}

function points(): void {
    global $afile, $conf, $tpl;
    setHead();
    $cont = getTplAdminTabs(['ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'],
        'tabs' => [_HOME, _ADD, _POINTS, _MANUAL], 'tab' => 2]);
    $cont .= checkPerms(CONFIG_DIR.'/points.php');
    $mark = ($conf['update']['points'] ?? '') === '6.3.0';
    if (!$mark) $cont .= $tpl->getHtmlFrag('alert', ['text' => _POINTS_NOMARK, 'meta' => '', 'type' => 'warn', 'is_warn' => true]);
    $phead = [
        ['content' => _NAME],
        ['content' => _POINTS, 'nosort' => 1],
        ['content' => _POINTS_PERIOD, 'nosort' => 1],
        ['content' => _POINTS_LIMIT, 'nosort' => 1],
    ];
    $heads = ['points' => _POINTS, 'period' => _POINTS_PERIOD, 'limit' => _POINTS_LIMIT];
    $prows = '';
    foreach ($conf['points']['actions'] as $name => $rule) {
        if ($name === 'adjust') continue;
        $cells = [['has_content_text' => true, 'content_text' => constant('_POINTS_'.strtoupper($name))]];
        foreach ($heads as $key => $head) {
            $cells[] = ['content_html' => $tpl->getHtmlFrag('input', [
                'itype' => 'number',
                'name_attr' => 'rule['.$name.']['.$key.']',
                'value_attr' => (string)$rule[$key],
                'placeholder_text' => $head,
                'is_required' => true,
            ])];
        }
        $prows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
    }
    $yesno = [['value' => '1', 'label' => _YES], ['value' => '0', 'label' => _NO]];
    $pointv = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'pointssave'],
            ['nameattr' => 'token', 'valueattr' => getSiteToken('groups')],
        ],
        'content_html' => $tpl->getHtmlPart('div', ['rows' => [[
            'label_html' => _UPDATE_POINTS,
            'label_id' => $labid = getFieldIds('', 'active')['label'],
            'field_html' => getTplRadioGroup(['labelledby' => $labid, 'name' => 'active', 'value' => (string)$conf['points']['active'], 'options' => $yesno]),
        ]]]).$tpl->getHtmlFrag('table', [
        'is_fixed' => true,
            'head' => $phead,
            'rows_html' => $prows,
            'is_wrapless' => true,
        ]),
        'submit_label' => _SAVE,
    ]);
    echo $cont.$tpl->getHtmlPart('box', ['content_html' => $pointv]).($mark ? getPointsJournal() : '');
    setFoot();
}

function getPointsJournal(): string {
    global $db, $afile, $conf, $tpl;
    $uid = getVar('get', 'uid', 'num', 0);
    $act = getVar('get', 'act', 'word', '');
    $day = getVar('get', 'day', 'text', '');
    $num = min(100, max(1, getVar('get', 'num', 'num', 1)));
    $names = array_keys($conf['points']['actions']);
    $act = in_array($act, $names, true) ? $act : '';
    $day = (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) && strtotime($day) !== false) ? $day : '';
    $where = [];
    $pars = [];
    if ($uid) [$where[], $pars['uid']] = ['p.uid = :uid', $uid];
    if ($act !== '') [$where[], $pars['act']] = ['p.action = :act', $act];
    if ($day !== '') [$where[], $pars['from'], $pars['to']] = ['p.created >= :from AND p.created < :to', $day.' 00:00:00', date('Y-m-d', strtotime($day.' +1 day')).' 00:00:00'];
    $cond = $where ? ' WHERE '.implode(' AND ', $where) : '';
    [$count] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM (SELECT 1 FROM '.PREFIX_DB.'_points AS p'.$cond.' LIMIT 5000) AS q', $pars));
    $sql = 'SELECT p.id, p.uid, u.name, p.aid, p.action, p.scope, p.mid, p.source, p.points, p.rid, p.note, p.created FROM '.PREFIX_DB.'_points AS p'
        .' LEFT JOIN '.PREFIX_DB.'_users AS u ON (u.id = p.uid)'.$cond.' ORDER BY p.created DESC, p.id DESC LIMIT :offset, :limit';
    $result = $db->getSqlQuery($sql, $pars + ['offset' => ($num - 1) * 50, 'limit' => 50]);
    $opts = [['value_attr' => '', 'label_text' => _ALL, 'is_selected' => $act === '']];
    foreach ($names as $name) $opts[] = ['value_attr' => $name, 'label_text' => constant('_POINTS_'.strtoupper($name)), 'is_selected' => $name === $act];
    $form = $tpl->getHtmlPart('form', [
        'action_url' => $afile.'.php',
        'method' => 'get',
        'is_inline_filter' => true,
        'hidden' => [
            ['nameattr' => 'name', 'valueattr' => 'groups'],
            ['nameattr' => 'op', 'valueattr' => 'points'],
        ],
        'content_html' => $tpl->getHtmlFrag('input', ['itype' => 'number', 'name_attr' => 'uid', 'value_attr' => $uid ? (string)$uid : '', 'placeholder_text' => _USER.' '._ID])
            .$tpl->getHtmlFrag('select', ['name_attr' => 'act', 'options' => $opts])
            .$tpl->getHtmlFrag('input', ['itype' => 'date', 'name_attr' => 'day', 'value_attr' => $day, 'placeholder_text' => _DATE])
            .$tpl->getHtmlFrag('button', ['button_type' => 'submit', 'submit_label' => _OK]),
    ]);
    $rows = '';
    while ($result && ($row = $db->getSqlRow($result))) {
        $rows .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => [
            ['is_col_id' => true, 'content_html' => (string)intval($row['id'])],
            ['is_col_date' => true, 'has_content_text' => true, 'content_text' => (string)$row['created']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => (string)($row['name'] ?? '').' #'.intval($row['uid'])],
            ['has_content_text' => true, 'content_text' => (string)$row['action']],
            ['is_truncate' => true, 'has_content_text' => true, 'content_text' => (string)$row['scope'].' / '.(string)$row['source']],
            ['is_col_count' => true, 'content_html' => (string)intval($row['points'])],
            ['is_truncate' => true, 'has_content_text' => true, 'title_text' => (string)$row['note'], 'content_text' => (string)$row['note']],
        ]])]);
    }
    $none = $tpl->getHtmlFrag('alert', ['text' => _NO_INFO, 'meta' => '', 'type' => 'info', 'is_warn' => false]);
    if ($rows === '') return $tpl->getHtmlPart('box', ['title' => _POINTS_JOURNAL, 'content_html' => $form.$none]);
    $head = [
        ['content' => _ID],
        ['content' => _DATE],
        ['content' => _USER],
        ['content' => _TYPE],
        ['content' => _POINTS_SCOPE.' / '._POINTS_SOURCE],
        ['content' => _POINTS],
        ['content' => _NOTE],
    ];
    $link = 'name=groups&op=points'.($uid ? '&uid='.$uid : '').($act !== '' ? '&act='.$act : '').($day !== '' ? '&day='.$day : '').'&';
    $body = $form.$tpl->getHtmlFrag('table', ['head' => $head, 'rows_html' => $rows]).getPageNumbers('', (int)$count, (int)ceil($count / 50), 50, $link, 10, $num, '', 'num');
    return $tpl->getHtmlPart('box', ['title' => _POINTS_JOURNAL, 'content_html' => $body]);
}

function pointssave(): void {
    global $afile, $conf;
    $warn = !checkAdminPost('groups');
    $text = $warn ? _TOKENMISS : _SUCCSAVE;
    if (!$warn) {
        $cont = ['active' => getVar('post', 'active', 'num') ? '1' : '0', 'actions' => $conf['points']['actions']];
        $tops = ['points' => 1000, 'period' => 31536000, 'limit' => 10000];
        foreach (array_keys($cont['actions']) as $name) {
            if ($name === 'adjust') continue;
            foreach ($tops as $key => $top) {
                $val = trim(getVar('post', 'rule['.$name.']['.$key.']', 'raw', ''));
                if (!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D', $val) || intval($val) > $top) $warn = true;
                $cont['actions'][$name][$key] = $val;
            }
            $per = intval($cont['actions'][$name]['period']);
            if (($per === 0) !== ($cont['actions'][$name]['limit'] === '0') || ($per > 0 && $per < 60)) $warn = true;
        }
        $text = $warn ? _NONUMVALUE : _SUCCSAVE;
        if (!$warn && !setConfigFile('points.php', $cont)) [$warn, $text] = [true, getConfigJournal() ? _CONFIG_PENDING : _ERROR_UP];
    }
    setRedirect($afile.'.php?name=groups&op=points', false, 302, $text, $warn);
}

function delete(): void {
    global $db, $afile, $conf;
    $warn = !checkAdminPost('groups');
    $id = getVar('post', 'id', 'num');
    if (!$warn && $id) {
        $db->getSqlQuery('DELETE FROM '.PREFIX_DB.'_groups WHERE id = :id', ['id' => $id]);
        $changed = false;
        foreach ($conf['modules'] as $name => $info) {
            if ((int)($info['group'] ?? 0) === $id) {
                $conf['modules'][$name]['group'] = 0;
                $changed = true;
            }
        }
        if ($changed) setConfigFile('modules.php', $conf['modules']);
    }
    setRedirect($afile.'.php?name=groups', false, 302, $warn ? _TOKENMISS : _SUCCDELETE, $warn);
}

function info(): void {
    setTplAdminInfoPage([
        'ops' => ['name=groups', 'name=groups&op=add', 'name=groups&op=points', 'name=groups&op=info'],
        'tabs' => [_HOME, _ADD, _POINTS, _MANUAL],
    ]);
}

switch ($op) {
    default: groups(); break;
    case 'add': add(); break;
    case 'save': save(); break;
    case 'delete': delete(); break;
    case 'points': points(); break;
    case 'pointssave': pointssave(); break;
    case 'info': info(); break;
}
