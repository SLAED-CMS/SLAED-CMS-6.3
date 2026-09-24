<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function users(): void {
    global $db, $conf, $tpl;
    setHead(['title' => _TOPUSERS, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _TOPUSERS, 'htitle' => _TOPUSERS, 'best_href' => $conf['points']['active'] ? getSeoUrl(['name' => $conf['name'], 'op' => 'rules']) : '', 'btitle' => _TU_RULES, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'stats']), 'ptitle' => _TU_STATS, 'liste_href' => '', 'add_href' => '']);
    $lim = 50;
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $lim);
    $count = ($num) ? $offset + 1 : 1;
        $result = $db->getSqlQuery('SELECT id, name, website, regdate, origin, lastvis, points, ip, gender, votes, tvotes FROM '.PREFIX_DB.'_users ORDER BY points DESC LIMIT '.$offset.', '.$lim);
        if ($db->getSqlRowCount($result) > 0) {
            $con = explode('|', (string)($conf['rating']['account'] ?? '0|0|0'));
            $rate = !empty($con[1]);
            $head = (is_moder($conf['name'])) ? _IP : _REG;
            $sort = $rate ? _RATING : _LOCALITYLANG;
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$id, $name, $site, $reg, $from, $last, $point, $ip, $gender, $votes, $total] = $row;
	            $tipItems = [
	                ['label' => _REG, 'value' => format_time($reg, _TIMESTRING), 'is_last' => false],
	                ['label' => _LAST_VISIT, 'value' => format_time($last, _TIMESTRING), 'is_last' => !$site],
	            ];
	            if ($site) $tipItems[] = ['label' => _SITE, 'value' => $site, 'is_last' => true];
	                $info = (is_moder($conf['name'])) ? Geoip::getIpHtml($ip) : format_time($reg);
	                $rating = $rate ? $tpl->getHtmlFrag('rating-box', ['content' => getRatingAsync(1, $id, 'account', $votes, $total, '', 1)]) : cutstr((string)$from, 30);
	            $rows[] = [
                'id' => (string)$count,
                'cells' => [
                    ['text' => (string)$count, 'href' => '#'.$count, 'title' => (string)$count, 'is_num' => true],
                    ['prefix_html' => getTplTitleTip($tipItems), 'content_html' => user_info($name)],
                    ['content_html' => $info],
                    ['content_html' => getGenderText($gender)],
                    ['content_html' => $rating],
                    ...($conf['points']['active'] ? [['text' => (string)$point]] : []),
                ],
            ];
            $count++;
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => [
                'open' => true,
                'sortable' => true,
                'headers' => [
                    ['text' => _ID, 'is_num' => true],
                    ['text' => _NICKNAME],
                    ['text' => $head],
                    ['text' => _GENDER],
                    ['text' => $sort],
                    ...($conf['points']['active'] ? [['text' => _POINTS]] : []),
                ],
            ],
            'table_close' => [],
            'pager_html' => getTplPager([
                'limit'     => $lim,
                'maxpg'     => 5,
                'table'     => '_users',
                'field'     => 'id',
                'mod'       => $conf['name'],
                'where'     => '',
                'url_extra' => [],
                'prefix'    => 'new/',
            ]),
        ]);
    } else {
        if ((int)$num > 1) setError(404);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function rules(): void {
    global $conf, $tpl;
    if (!$conf['points']['active']) setRedirect('index.php?name='.$conf['name']);
    setHead(['title' => _TU_RULES, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _TOPUSERS, 'htitle' => _TOPUSERS, 'best_href' => $conf['points']['active'] ? getSeoUrl(['name' => $conf['name'], 'op' => 'rules']) : '', 'btitle' => _TU_RULES, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'stats']), 'ptitle' => _TU_STATS, 'liste_href' => '', 'add_href' => '']);
    $rows = [];
    foreach ($conf['points']['actions'] as $name => $rule) {
        if ($name === 'adjust') continue;
        $rows[] = [
            'id' => $name,
            'cells' => [
                ['text' => constant('_POINTS_'.strtoupper($name))],
                ['text' => (string)$rule['points'], 'is_num' => true],
                ['text' => (string)$rule['period'], 'is_num' => true],
                ['text' => (string)$rule['limit'], 'is_num' => true],
            ],
        ];
    }
    $cont .= $tpl->getHtmlPart('content-list', [
        'rows' => $rows,
        'table_open' => [
            'open' => true,
            'sortable' => true,
            'headers' => [
                ['text' => _TYPE],
                ['text' => _POINTS, 'is_num' => true],
                ['text' => _POINTS_PERIOD, 'is_num' => true],
                ['text' => _POINTS_LIMIT, 'is_num' => true],
            ],
        ],
        'table_close' => [],
    ]);
    $cont .= $tpl->getHtmlPart('navi-lower', [
        'back_button' => ['button_type' => 'button', 'title' => _BACK, 'label' => _BACK, 'is_back' => true, 'is_navi_lower' => true],
        'home_link' => ['href' => 'index.php?name='.$conf['name'], 'title' => _PAGEHOME, 'label' => _PAGEHOME, 'is_navi_lower' => true],
        'top_link' => ['href' => '#top', 'title' => _PAGETOP, 'label' => _PAGETOP, 'is_navi_lower' => true],
    ]);
    echo $cont;
    setFoot();
}

function stats(): void {
    global $db, $conf, $tpl;
    setHead(['title' => _TU_STATS, 'kind' => 'collection']);
    $cont = getModuleNavi(['title' => _TOPUSERS, 'htitle' => _TOPUSERS, 'best_href' => $conf['points']['active'] ? getSeoUrl(['name' => $conf['name'], 'op' => 'rules']) : '', 'btitle' => _TU_RULES, 'pop_href' => getSeoUrl(['name' => $conf['name'], 'op' => 'stats']), 'ptitle' => _TU_STATS, 'liste_href' => '', 'add_href' => '']);
    $result = $db->getSqlQuery('SELECT id, name, intro, points, extra, `rank`, color FROM '.PREFIX_DB.'_groups ORDER BY points');
    if ($result) {
        $rows = [];
        while ($row = $db->getSqlRow($result)) {
            [$grid, $grname, $description, $points, $extra, $rank, $color] = $row;
            if ((int)$extra) {
                $extra = _YES;
                [$total] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_users WHERE grp = :grid', ['grid' => $grid]));
            } else {
                $extra = _NO;
                [$total] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM '.PREFIX_DB.'_users WHERE points >= :points', ['points' => $points]));
            }
            $trank = ($grname) ? _GROUP.': '.$grname : _RANK;
            $rows[] = [
                'cells' => [
                    ['img_src' => getThemeImagePath('ranks/'.$rank), 'img_alt' => $trank, 'img_title' => $trank],
                    ['primary_text' => $grname, 'secondary_text' => $description],
                    ['text' => (string)$points],
                    ['text' => (string)$total],
                    ['text' => (string)$extra],
                ],
            ];
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => [
                'open' => true,
                'sortable' => true,
                'headers' => [
                    ['text' => _RANK],
                    ['text' => _DESCRIPTION],
                    ['text' => _POINTS],
                    ['text' => _TU_USERSCOUNT],
                    ['text' => cutstr(_SPEC, 4, 1)],
                ],
            ],
            'table_close' => [],
            'empty_alert' => ['is_warn' => false, 'text' => _NO_INFO],
        ]);
        $cont .= $tpl->getHtmlPart('navi-lower', [
            'back_button' => ['button_type' => 'button', 'title' => _BACK, 'label' => _BACK, 'is_back' => true, 'is_navi_lower' => true],
            'home_link' => ['href' => 'index.php?name='.$conf['name'], 'title' => _PAGEHOME, 'label' => _PAGEHOME, 'is_navi_lower' => true],
            'top_link' => ['href' => '#top', 'title' => _PAGETOP, 'label' => _PAGETOP, 'is_navi_lower' => true],
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: users(); break;
    case 'rules': rules(); break;
    case 'stats': stats(); break;
}
