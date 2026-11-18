<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function voting(): void {
    global $db, $afile, $locale, $conf, $tpl;
    $onum = ($conf['multilingual'] == 1)
        ? "(lang = '".$locale."' OR lang = '') AND modul = '' AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')"
        : "modul = '' AND time <= NOW() AND (enddate >= NOW() AND status = '0' OR status = '1')";
    $num = getVar('get', 'page', 'num', 1);
    $lim = getPageRows($conf['voting']);
    $offset = (int)(($num - 1) * $lim);
    setHead(['title' => _VOTING, 'kind' => 'collection']);
    $result = $db->getSqlQuery('SELECT v.id, v.title, v.answer, v.time, v.enddate, v.comments, v.acomm, v.typ, r.path FROM '.PREFIX_DB.'_voting AS v'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'poll\' AND r.oid = v.id AND r.cur = 1 WHERE '.$onum.' ORDER BY v.id DESC LIMIT '.$offset.', '.$lim);
    $rows = [];
    $ismoder = is_moder($conf['name']);
    while ([$id, $stitle, $answer, $date, $enddate, $comm, $acomm, $typ, $path] = $db->getSqlRow($result)) {
        $tips = [['label' => _CHNGSTORY, 'value_text' => format_time($date, _TIMESTRING), 'has_value_text' => true],
            ['label' => _ENDDATE, 'value_text' => format_time($enddate, _TIMESTRING), 'has_value_text' => true],
            ['label' => _TYPE, 'value_text' => ($typ == '1') ? _VOPEN : _VCLOSE, 'has_value_text' => true]];
        $row = [
            'id' => $id, 'href' => getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'route' => $path]), 'title' => $stitle,
            'fresh_html' => getTplNewGraphic($date), 'is_closed' => strtotime($enddate) <= time(), 'comm' => ($acomm && $comm) ? $comm : _NO,
            'vote' => array_sum(explode('|', $answer)), 'tips' => [['items' => $tips]],
        ];
        if ($ismoder) {
            $edit = HOME_PATH.$afile.'.php?name=voting&op=add&id='.$id;
            $del = HOME_PATH.$afile.'.php?name=voting&op=delete&id[]='.$id.'&refer=1';
            $row += getTplEditMenu($edit, $del, $stitle, 'voting');
        }
        $rows[] = $row;
    }
    if ($rows) {
        $cont = $tpl->getHtmlPart('voting-home', [
            'head' => ['icon' => 'bar-chart', 'title' => _VOTING, 'level' => 1],
            'rows' => $rows, 'act_label' => _VOTING_ACT, 'end_label' => _VOTING_END, 'comm_label' => _COMMENTS, 'votes_label' => _VOTING_VOTES,
            'pager_html' => getTplPager([
                'limit'     => $lim,
                'maxpg'     => getPageSpan($conf['voting']),
                'table'     => '_voting',
                'field'     => 'id',
                'mod'       => $conf['name'],
                'where'     => $onum,
                'url_extra' => [],
                'prefix'    => 'new/',
            ]),
        ]);
    } else {
        if ((int)$num > 1) setError(404);
        $cont = $tpl->getHtmlFrag('title', ['title' => _VOTING, 'is_level_one' => true]).$tpl->getHtmlFrag('empty', []);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $conf, $tpl;
    $id = getVar('get', 'id', 'num');
    $result = $db->getSqlQuery('SELECT v.title, v.time, v.acomm, r.path FROM '.PREFIX_DB.'_voting AS v'
        .' LEFT JOIN '.PREFIX_DB.'_routes AS r ON r.kind = \'poll\' AND r.oid = v.id AND r.cur = 1'
        .' WHERE v.id = :id AND v.modul = \'\' AND v.time <= NOW() AND (v.enddate >= NOW() AND v.status = \'0\' OR v.status = \'1\')', ['id' => $id]);
    if ($db->getSqlRowCount($result) > 0) {
        [$title, $date, $acomm, $route] = $db->getSqlRow($result);
        setSeoMove(['route' => $route]);
        setHead([
            'title' => $title,
            'route' => $route,
            'kind' => 'website',
            'ctitle' => _VOTING,
            'desc' => cutstr(trim(strip_tags($title)), 160),
            'time' => $date,
            'author' => $conf['sitename'],
        ]);
        $vote = $tpl->getHtmlFrag('block-content', [
            'id' => 'rep'.$conf['name'],
            'content' => getVotingView($id, $conf['name']),
        ]);
        $cont = $tpl->getHtmlFrag('block-content', ['is_post_vote' => true, 'content' => $tpl->getHtmlFrag('pane', ['icon' => 'bar-chart', 'title' => _VOTING,
            'href' => getSeoUrl(['name' => $conf['name']]), 'is_plain' => true, 'content_html' => $vote])]);
        if ($acomm) $cont .= setComShow($id, $acomm, $route);
    } else {
        setError(404);
    }
    echo $cont;
    setFoot();
}

switch ($op) {
    default: voting(); break;
    case 'view': view(); break;
}
