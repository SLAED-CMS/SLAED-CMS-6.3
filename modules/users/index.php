<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

# The groups and the whole count of members by one query: the steps of the points ladder from the lowest up, each with the members who stand on it now, then the special ones
function getUsersLadder(): array {
    global $db;
    $out = [[], [], -1];
    $res = $db->getSqlQuery('SELECT g.id, g.name, g.intro, g.points, g.`rank`, g.color, g.extra, CASE WHEN g.extra = 1'
        .' THEN (SELECT COUNT(*) FROM '.PREFIX_DB.'_users AS u WHERE u.grp = g.id) ELSE (SELECT COUNT(*) FROM '.PREFIX_DB.'_users AS u WHERE u.points >= g.points) END AS num,'
        .' (SELECT COUNT(*) FROM '.PREFIX_DB.'_users) AS total FROM '.PREFIX_DB.'_groups AS g ORDER BY g.points ASC, g.id ASC');
    while ($row = $db->getSqlRow($res)) {
        [$id, $name, $intro, $from, $rank, $color, $extra, $num, $total] = $row;
        $out[(int)$extra === 1 ? 1 : 0][] = ['id' => (int)$id, 'name' => $name, 'intro' => $intro, 'from' => (int)$from, 'rank' => $rank, 'color' => $color, 'num' => (int)$num];
        $out[2] = (int)$total;
    }
    foreach (array_keys($out[0]) as $i) $out[0][$i]['num'] -= $out[0][$i + 1]['num'] ?? 0;
    return $out;
}

# The condition of the list and of its count: a part of the name, and the points between the threshold of the chosen step and the next one
function getUsersWhere(string $find, array $pick): array {
    $part = [];
    $bind = [];
    if ($find !== '') [$part[], $bind['find']] = ['u.name LIKE :find ESCAPE \'!\'', '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $find).'%'];
    if ($pick) [$part[], $bind['lo']] = ['u.points >= :lo', $pick['from']];
    if (isset($pick['till'])) [$part[], $bind['hi']] = ['u.points < :hi', $pick['till']];
    return [$part ? ' WHERE '.implode(' AND ', $part) : '', $bind];
}

# The mark of a visit in the last week as the data of the fresh fragment, whose tiers are those of a new material
function getUsersFresh(?string $last): array {
    $mark = $last ? strtotime($last) : false;
    $age = $mark ? time() - $mark : 604800;
    if ($age >= 604800) return [];
    $tier = ($age < 3600) ? 'now' : (($age < 86400) ? 'day' : (($age < 259200) ? 'days' : 'week'));
    return [['tier' => $tier, 'is_'.$tier => true, 'title' => _LAST_VISIT, 'date_text' => format_time($last, _TIMESTRING), 'date_iso' => date('c', $mark)]];
}

# The limit of a rule in words; a period and a limit are both zero or both set, and a period is never under a minute
function getUsersLimit(int $period, int $limit): string {
    if (!$limit) return _USERS_NOLIMIT;
    $form = [3600 => _USERS_LIMHOUR, 86400 => _USERS_LIMDAY, 604800 => _USERS_LIMWEEK][$period] ?? '';
    return ($form !== '') ? sprintf($form, $limit) : sprintf(_USERS_LIMSEC, $limit, $period);
}

# The rules of the points as the rows of their window; the correction by an administrator is no rule a member earns by
function getUsersRules(): array {
    global $conf;
    $icons = ['publish' => 'pencil-square', 'comment' => 'chat-text', 'view' => 'eye', 'download' => 'download', 'visit' => 'box-arrow-up-right', 'poll' => 'bar-chart',
        'favorite' => 'bookmark', 'message' => 'envelope', 'recommend' => 'megaphone', 'register' => 'person-plus', 'login' => 'box-arrow-in-right', 'report' => 'flag',
        'moderate' => 'shield-check'];
    $rows = [];
    foreach ($conf['points']['actions'] as $name => $rule) {
        if ($name === 'adjust') continue;
        $rows[] = ['icon' => $icons[$name] ?? 'stars', 'name' => constant('_POINTS_'.strtoupper($name)), 'limit' => getUsersLimit((int)$rule['period'], (int)$rule['limit']),
            'points' => ($rule['points'] > 0 ? '+' : '').(int)$rule['points'], 'is_plus' => $rule['points'] > 0];
    }
    return $rows;
}

# The groups as the rows of their window, the special ones first; a step leads to the list of its group
function getUsersGroups(array $steps, array $specs, string $name): array {
    $rows = [];
    foreach ($specs as $one) $rows[] = ['icon' => 'patch-check', 'name' => $one['name'], 'intro' => $one['intro'], 'is_spec' => true,
        'rank_html' => getRankView((string)$one['rank'], _GROUP.': '.$one['name']), 'count' => filterDecimal($one['num'], 0)];
    foreach ($steps as $one) $rows[] = ['icon' => 'people', 'name' => $one['name'], 'intro' => $one['intro'], 'href' => getSeoUrl(['name' => $name, 'grp' => $one['id']]),
        'rank_html' => getRankView((string)$one['rank'], _GROUP.': '.$one['name']), 'floor' => sprintf(_USERS_FLOOR, filterDecimal($one['from'], 0)),
        'count' => filterDecimal($one['num'], 0)];
    return $rows;
}

# The place of the reader and his way to the next step, empty for a guest; the place counts the members above him in the order of the list
function getUsersMine(array $steps, int $total): array {
    global $db;
    $info = getUserInfo();
    if (!$info) return [];
    $pts = (int)$info['points'];
    [$place] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) + 1 FROM '.PREFIX_DB.'_users WHERE points > :pts OR (points = :same AND id < :uid)',
        ['pts' => $pts, 'same' => $pts, 'uid' => (int)$info['id']]));
    $base = 0;
    $next = [];
    foreach ($steps as $one) {
        if ($one['from'] <= $pts) $base = $one['from'];
        if ($one['from'] > $pts && !$next) $next = $one;
    }
    $part = $next ? min(99, intdiv(($pts - $base) * 100, max(1, $next['from'] - $base))) : 100;
    $gap = $next ? $next['from'] - $pts : 0;
    return ['icon' => 'person-check', 'title' => _USERS_MINE, 'level' => 2, 'part' => $part,
        'note' => $next ? sprintf(_USERS_NEXT, $next['name'], sprintf(getPluralForm($gap, _USERS_PTS), filterDecimal($gap, 0))) : _USERS_TOPSTEP,
        'chips' => [['tone' => 'neutral', 'icon' => 'hash', 'label' => sprintf(_NUMOF, filterDecimal((int)$place, 0), filterDecimal($total, 0)), 'title' => _USERS_PLACE],
            ['tone' => 'neutral', 'icon' => 'stars', 'label' => filterDecimal($pts, 0), 'title' => _POINTS]]];
}

# The list of the members in the look the reader chose; the pedestal is for the best by points, the first three of the first page in that order
function users(string $open = ''): void {
    global $db, $conf, $tpl, $pnt, $user;
    $name = $conf['name'];
    $num = max(1, (int)getVar('get', 'page', 'num', 1));
    $head = (int)getVar('get', 'from', 'num', 0);
    $find = mb_substr(analyze_name(getVar('get', 'find', 'word', '')), 0, 25);
    $grp = (int)getVar('get', 'grp', 'num', 0);
    $sort = getVar('get', 'order', 'var', '');
    $dir = getVar('get', 'dir', 'var', '');
    $ways = ['points' => 'desc', 'reg' => 'desc', 'visit' => 'desc', 'name' => 'asc'];
    if (($sort !== '' && !isset($ways[$sort])) || !in_array($dir, ['', 'asc', 'desc'], true)) setError(404);
    $key = $sort ?: 'points';
    $way = $dir ?: $ways[$key];
    $part = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    header('Vary: HX-Request');
    [$steps, $specs, $total] = getUsersLadder();
    if ($total < 0) [$total] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_users'));
    $pick = [];
    foreach ($steps as $i => $one) if ($one['id'] === $grp) $pick = $one + (isset($steps[$i + 1]) ? ['till' => $steps[$i + 1]['from']] : []);
    if ($grp && !$pick) setError(404);
    $base = ['name' => $name] + ($find !== '' ? ['find' => rawurlencode($find)] : []) + ($pick ? ['grp' => $grp] : []);
    $turn = static fn(string $one, string $to): array => ($one === 'points' && $to === 'desc') ? [] : ['order' => $one, 'dir' => $to];
    $link = static fn(int $i): array => ['href' => getSeoUrl($base + $turn($key, $way) + ($i > 1 ? ['page' => $i] : []))];
    $views = ['podium', 'cards', 'steps'];
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $look = getVar('post', 'view', 'var', '');
        if (checkSitePost('users') && in_array($look, $views, true)) setCookies('view-'.$name, time() + 31536000, $look);
        setRedirect($link($num)['href'], false, 303);
    }
    $look = getCookies('view-'.$name);
    if (!in_array($look, $views, true)) $look = 'podium';
    $lim = getPageRows();
    [$where, $bind] = getUsersWhere($find, $pick);
    [$count] = ($find !== '') ? $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_users AS u'.$where, $bind)) : [$pick ? $pick['num'] : $total];
    $pages = max(1, (int)ceil($count / $lim));
    if ($num > $pages) setError(404);
    $head = ($head > 0 && $head < $num && $num - $head < 10) ? $head : $num;
    $off = ($head - 1) * $lim;
    $plain = $where === '' && $key === 'points' && $way === 'desc';
    $cols = ['points' => 'u.points', 'reg' => 'u.regdate', 'visit' => 'u.lastvis', 'name' => 'u.name'];
    $rank = $plain ? '0' : '(SELECT COUNT(*) + 1 FROM '.PREFIX_DB.'_users AS t WHERE t.points > u.points OR (t.points = u.points AND t.id < u.id))';
    $res = $db->getSqlQuery('SELECT u.id, u.name, u.avatar, u.website, u.regdate, u.origin, u.lastvis, u.points, u.ip, g.name, g.`rank`, g.color, r.path, '.$rank
        .' AS spot FROM '.PREFIX_DB.'_users AS u LEFT JOIN '.PREFIX_DB.'_groups AS g ON g.id = u.grp AND g.extra = 1'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'user\' AND r.oid = u.id AND r.cur = 1'.$where
        .' ORDER BY '.$cols[$key].' '.($way === 'asc' ? 'ASC' : 'DESC').', u.id ASC LIMIT '.$off.', '.(($num - $head + 1) * $lim), $bind);
    $moder = is_moder($name) > 0;
    $seen = $conf['users']['prof'] != 1 || is_user() || is_moder('account');
    $self = is_user() ? (int)($user[0] ?? 0) : 0;
    $rows = [];
    $bags = [];
    while ($row = $db->getSqlRow($res)) {
        [$uid, $nick, $ava, $site, $reg, $from, $last, $pts, $ip, $gname, $grank, $gcolor, $path, $spot] = $row;
        $step = [];
        foreach ($steps as $one) if ($one['from'] <= $pts) $step = $one;
        $place = $plain ? $off + count($rows) + 1 : (int)$spot;
        $tips = [['label' => _REG, 'value_text' => format_time($reg, _TIMESTRING), 'has_value_text' => true],
            ['label' => _LAST_VISIT, 'value_text' => format_time($last, _TIMESTRING), 'has_value_text' => true]];
        if ($site) $tips[] = ['label' => _SITE, 'value_text' => $site, 'has_value_text' => true];
        if ($moder) $tips[] = ['label' => _IP, 'value' => Geoip::getIpHtml((string)$ip)];
        $item = [
            'place' => $place, 'is_lead' => $place <= 3, 'is_first' => $place === 1, 'name' => $nick, 'is_me' => $self === (int)$uid,
            'href' => $seen ? getSeoUrl(['name' => 'account', 'op' => 'view', 'uname' => rawurlencode($nick), 'route' => $path]) : '',
            'avatar' => getUserAvatarUrl(['avatar' => $ava]), 'ring' => getRingColor($gcolor ?: ($step['color'] ?? '')),
            'step' => $step['name'] ?? '', 'step_href' => $step ? getSeoUrl(['name' => $name, 'grp' => $step['id']]) : '',
            'rank_html' => getRankView((string)($grank ?: ($step['rank'] ?? '')), _GROUP.': '.($gname ?: ($step['name'] ?? ''))),
            'reg' => format_time($reg), 'reg_iso' => date('c', strtotime((string)$reg)), 'visit' => format_time($last), 'origin' => cutstr((string)$from, 30),
            'points' => filterDecimal((int)$pts, 0), 'fresh' => getUsersFresh($last), 'tips' => [['items' => $tips]],
        ];
        $rows[] = $item;
        $bags[$step['id'] ?? 0][] = $item;
    }
    if (!$rows && $num > 1) setError(404);
    $tiers = [];
    foreach (array_reverse($steps) as $i => $one) {
        if (empty($bags[$one['id']])) continue;
        $tiers[] = ['id' => 'sl-users-step-'.$one['id'], 'icon' => 'award', 'title' => $one['name'], 'level' => 2, 'is_fold' => true, 'is_open' => true,
            'plus_text' => _OPCL.': '.$one['name'], 'tone' => $i % 6, 'tools_html' => getRankView((string)$one['rank'], _GROUP.': '.$one['name']), 'rows' => $bags[$one['id']],
            'chips' => [['tone' => 'neutral', 'icon' => 'people', 'label' => filterDecimal($one['num'], 0), 'title' => _TU_USERSCOUNT],
                ['tone' => 'neutral', 'icon' => 'stars', 'label' => sprintf(_USERS_FLOOR, filterDecimal($one['from'], 0)), 'title' => _POINTS]]];
    }
    if (!empty($bags[0])) $tiers[] = ['id' => 'sl-users-step-0', 'icon' => 'person', 'title' => _USERS_NOSTEP, 'level' => 2, 'is_fold' => true, 'is_open' => true,
        'plus_text' => _OPCL.': '._USERS_NOSTEP, 'tone' => 5, 'rows' => $bags[0]];
    $tiles = [['tone' => 0, 'href' => getSeoUrl(array_diff_key($base, ['grp' => 0]) + $turn($key, $way)), 'title' => _USERS_ALLGRP, 'name' => _USERS_ALLGRP,
        'icon_name' => 'people', 'count' => filterDecimal((int)$total, 0), 'count_title' => _TU_USERSCOUNT, 'is_current' => !$pick]];
    foreach (array_reverse($steps) as $i => $one) $tiles[] = ['tone' => ($i + 1) % 6, 'href' => getSeoUrl(['grp' => $one['id']] + $base + $turn($key, $way)),
        'title' => $one['name'], 'name' => $one['name'], 'icon_name' => 'award', 'count' => filterDecimal($one['num'], 0), 'count_title' => _TU_USERSCOUNT,
        'description' => $one['intro'], 'is_current' => $grp === $one['id']];
    $names = ['points' => [_POINTS, 'stars'], 'reg' => [_REG, 'calendar3'], 'visit' => [_LAST_VISIT, 'clock-history'], 'name' => [_NICKNAME, 'sort-alpha-down']];
    $sorts = [];
    foreach ($names as $one => [$label, $icon]) $sorts[] = ['href' => getSeoUrl($base + $turn($one, ($one === $key) ? $way : $ways[$one])), 'label' => $label, 'icon_name' => $icon,
        'is_current' => $one === $key];
    $cells = [];
    $looks = ['podium' => [_USERS_VPODIUM, 'trophy'], 'cards' => [_USERS_VCARDS, 'person-vcard'], 'steps' => [_USERS_VSTEPS, 'bar-chart-steps']];
    foreach ($looks as $one => [$label, $icon]) $cells[] = ['key' => $one, 'title' => $label, 'icon_name' => $icon, 'is_now' => $one === $look];
    $keep = [];
    foreach (($pick ? ['grp' => $grp] : []) + $turn($key, $way) as $one => $val) $keep[] = ['name' => $one, 'value' => $val];
    $rules = $pnt->active ? getUsersRules() : [];
    $wins = $rules ? [['id' => 'sl-users-rules', 'icon' => 'stars', 'name' => _TU_RULES, 'title' => _TU_RULES, 'level' => 2, 'tone' => 2, 'is_open' => $open === 'rules',
        'chips' => [['tone' => 'neutral', 'icon' => 'list-check', 'label' => count($rules)]],
        'body_html' => $tpl->getHtmlPart('users-win', ['is_rules' => true, 'rows' => $rules, 'limit_label' => _POINTS_LIMIT, 'points_label' => _POINTS])]] : [];
    $wins[] = ['id' => 'sl-users-stats', 'icon' => 'people', 'name' => _STATISTIC, 'title' => _STATISTIC, 'level' => 2, 'tone' => 0, 'is_open' => $open === 'stats',
        'chips' => [['tone' => 'neutral', 'icon' => 'collection', 'label' => count($steps) + count($specs)]],
        'body_html' => $tpl->getHtmlPart('users-win', ['is_stats' => true, 'rows' => getUsersGroups($steps, $specs, $name), 'spec_label' => _SPEC,
            'points_label' => _POINTS, 'count_label' => _TU_USERSCOUNT])];
    $mine = $pnt->active ? getUsersMine($steps, (int)$total) : [];
    $pill = 'data-sl-toggle-control="sl-users-rules" data-sl-toggle-scope="none" data-sl-toggle-effect="slide" data-sl-toggle-duration="250"';
    if ($mine) $mine['tools_html'] = $tpl->getHtmlFrag('link', ['href' => getSeoUrl(['name' => $name, 'op' => 'rules']), 'title' => _TU_RULES, 'label' => _USERS_HOWTO,
        'icon_name' => 'question-circle', 'chip_tone' => 'info', 'link_attr' => $pill]);
    $asc = $way === 'asc';
    $top = ($look === 'podium' && $head === 1 && $key === 'points' && !$asc) ? 3 : 0;
    $seo =['title' => _TOPUSERS, 'kind' => 'collection', 'robots' => ($plain && $head === $num) ? '' : 'noindex, follow',
        'canon' => getSeoUrl(['name' => $name] + ($num > 1 ? ['page' => $num] : []))];
    $page = $tpl->getHtmlPart('users', [
        'head' => [
            'title' => _TOPUSERS, 'count_title' => _TU_USERSCOUNT, 'count_text' => sprintf(getPluralForm((int)$count, _USERS_COUNT), filterDecimal((int)$count, 0)),
            'view_html' => $tpl->getHtmlFrag('mode-switch', ['action' => $link($num)['href'], 'modes' => $cells, 'field' => 'view', 'is_view' => true, 'label' => _USERS_VIEW,
                'hidden' => $tpl->getHtmlFrag('hidden', ['name_attr' => 'token', 'value_attr' => getSiteToken('users'), 'input_attr' => ''])]),
            'seek_href' => getSeoUrl(['name' => $name]), 'seek_value' => $find, 'seek_keep' => $keep,
            'find_label' => _USERS_FIND, 'search_label' => _SEARCH, 'top_label' => _PAGETOP, 'filters_label' => _USERS_SIFT,
            'cats_html' => $steps ? $tpl->getHtmlPart('users-win', ['is_tiles' => true, 'rows' => $tiles]) : '', 'cats_label' => _GROUP,
            'cat_name' => $pick ? $pick['name'] : _USERS_ALLGRP, 'is_cat' => (bool)$pick, 'cats_icon' => 'people',
            'sorts' => $sorts, 'sort_name' => $names[$key][0], 'sort_label' => _SORT, 'dir_href' => getSeoUrl($base + $turn($key, $asc ? 'desc' : 'asc')),
            'dir_label' => $asc ? _ASCENDING : _DESCENDING, 'dir_title' => $asc ? _ASC : _DESC, 'is_asc' => $asc,
            'wins' => $wins, 'reset_href' => $plain ? '' : getSeoUrl(['name' => $name]), 'reset_label' => _FRESET,
        ],
        'mine' => $mine ? [$mine] : [],
        'rows' => $rows,
        'lead' => array_slice($rows, 0, $top),
        'panes' => ($look !== 'podium') ? $tiers : (count($rows) > $top ? [['id' => 'sl-users-rest', 'icon' => 'list-ol', 'title' => _RATING, 'level' => 2, 'is_fold' => true,
            'is_open' => true, 'plus_text' => _OPCL.': '._RATING, 'tone' => 1, 'has_rank' => true, 'rows' => array_slice($rows, $top)]] : []),
        'is_podium' => $top > 0, 'is_cards' => $look === 'cards',
        'is_moder' => $moder, 'has_points' => (bool)$pnt->active,
        'lead_head' => ['icon' => 'trophy', 'title' => _USERS_LEAD, 'level' => 2,
            'chips' => [['tone' => 'neutral', 'icon' => 'people', 'label' => filterDecimal((int)$count, 0), 'title' => _TU_USERSCOUNT]]],
        'place_label' => _USERS_PLACE, 'you_label' => _USERS_YOU, 'reg_label' => _REG, 'visit_label' => _LAST_VISIT, 'from_label' => _USERS_FROM, 'points_label' => _POINTS,
        'group_label' => _GROUP, 'info_label' => _PERSONALINFO, 'profile_label' => _ACCOUNT,
        'pager_html' => getTplPagerView($num, $pages, getPageSpan(), $link, ['count' => (int)$count, 'limit' => $lim]),
        'more_href' => ($num < $pages && $num - $head < 9) ? getSeoUrl($base + $turn($key, $way) + ['page' => $num + 1, 'from' => $head]) : '', 'more_label' => _MORE,
        'empty' => ['is_found' => true, 'act_url' => getSeoUrl(['name' => $name])],
    ]);
    if ($part) {
        echo $tpl->getHtmlFrag('head-title', ['title' => getHeadTitle($seo)]).$page;
        return;
    }
    setHead($seo);
    echo $page;
    setFoot();
}

switch ($op) {
    default: users(); break;
    case 'rules': users('rules'); break;
    case 'stats': users('stats'); break;
}
