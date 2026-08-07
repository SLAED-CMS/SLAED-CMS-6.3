<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function content(): void {
    global $db, $afile, $conf, $tpl;
    $limit = (int)($conf['content']['num'] ?? 10);
    $nump = (int)($conf['content']['nump'] ?? 5);
    if ($limit < 1) $limit = 10;
    if ($nump < 1) $nump = 5;
    setHead(['title' => _CONTENT, 'kind' => 'collection']);
    $cont = $tpl->getHtmlFrag('title', ['title' => _CONTENT, 'is_level_one' => true]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $limit);
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content WHERE time <= NOW() ORDER BY time DESC LIMIT '.$offset.', '.$limit);
    if ($db->getSqlRowCount($result) > 0) {
        $rows = [];
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        while ($row = $db->getSqlRow($result)) {
            [$id, $title, $time, $counter] = $row;
            $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title]);
            $tip = $tpl->getHtmlFrag('block-content', [
                'content' => $tpl->getHtmlFrag('date-badge', ['iso' => date('c', strtotime($time)), 'title' => _DATE, 'text' => format_time($time)])
                    .(($counter) ? ' '.$tpl->getHtmlFrag('span', ['title' => _READS, 'is_views' => true, 'text' => (string)$counter]) : ''),
            ]);
            $menu = '';
            if ($ismoder) {
                $edit = $afile.'.php?name=content&op=add&id='.$id;
                $del = $afile.'.php?name=content&op=delete&id='.$id.'&refer=1&token='.$token;
                $menu = $tpl->getHtmlFrag('dial', getTplEditMenu($edit, $del, $title));
            }
            $rows[] = [
                'id' => (string)$id,
                'cells' => [
                    ['content_html' => getTplNewGraphic($time).' '.$tpl->getHtmlFrag('link', ['href' => $href, 'title' => $title, 'label' => $title, 'suffix_html' => $tip])],
                    ['is_num' => true, 'content_html' => ($menu !== '' ? $menu.' ' : '').$tpl->getHtmlFrag('link', ['href' => '#'.$id, 'title' => (string)$id, 'label' => (string)$id, 'is_num_anchor' => true])],
                ],
            ];
        }
        $cont .= $tpl->getHtmlPart('content-list', [
            'rows' => $rows,
            'table_open' => ['open' => true, 'sortable' => true, 'col_id' => _ID, 'col_title' => _TITLE],
            'table_close' => [],
            'pager_html' => getTplPager([
            'limit'  => $limit,
            'maxpg'  => $nump,
            'table'  => '_content',
            'field'  => 'id',
            'mod'    => $conf['name'],
            'where'  => 'time <= NOW()',
            'prefix' => 'new/',
            ]),
        ]);
    } else {
        if ((int)$num > 1) setError(404);
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $afile, $conf, $tpl, $prs;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $result = $db->getSqlQuery('SELECT id, title, body, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$id, $title, $body, $field, $url, $time, $refresh] = $db->getSqlRow($result);
        if ($url) {
            $past = time() - $refresh;
            if (strtotime($time) < $past) {
                $rss = rss_read($url, 1);
                $room = checkEditorTextRoom($rss, 'content.body');
                if ($room === '') {
                    $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET body = :body, time = NOW() WHERE id = :id', ['body' => $rss, 'id' => $id]);
                } else {
                    Logger::addSite('warning', 'Feed refused for content.body: '.$room, ['id' => $id, 'url' => $url]);
                }
            }
        }
        $fields = getTplViewFieldRows(['field' => $field, 'mod' => $conf['name']]);
        $hometext = $body.$fields;
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $title,
            'kind' => 'article',
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $time,
            'author' => $conf['sitename'],
        ]);
        $ismoder = is_moder($conf['name']);
        $edit = $afile.'.php?name=content&op=add&id='.$id;
        $del = $afile.'.php?name=content&op=delete&id='.$id.'&token='.getSiteToken();
        $cont = $tpl->getHtmlPart('view', [
            'is_moder' => $ismoder,
            'share_url' => getPublicUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title]),
            'share_title' => $title,
            'title_text' => filterTextHighlight($title, $word),
            'title_new' => getTplNewGraphic($time),
            'text' => filterTextHighlight($prs->filterDoc($body, false, $conf['name'], 1), $word),
            'fields' => $fields,
            ...($ismoder ? getTplEditMenu($edit, $del, $title) : []),
            'back_title' => _BACK,
            'back_text' => _BACK,
        ]);
        echo $cont;
        setFoot();
    } else {
        setError(404);
    }
}

switch ($op) {
    default: content(); break;
    case 'view': view(); break;
}
