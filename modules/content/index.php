<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
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
    setHead(['title' => _CONTENT]);
    $cont = '';
    $num = getVar('get', 'num', 'num', '1');
    $offset = (int)(($num - 1) * $limit);
    $result = $db->getSqlQuery('SELECT id, title, time, counter FROM '.PREFIX_DB.'_content WHERE time <= NOW() ORDER BY time DESC LIMIT '.$offset.', '.$limit);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('new/table', ['open' => true, 'sortable' => true, 'col_id' => _ID, 'col_title' => _TITLE, 'col_func' => _FUNCTIONS]);
        $ismoder = is_moder($conf['name']);
        $token   = getSiteToken();
        while ([$id, $title, $time, $counter] = $db->getSqlRow($result)) {
            $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title]);
            $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
            $tip = $tpl->getHtmlFrag('new/tip', [
                'tip_date' => format_time($time),
                'tip_date_iso' => date('c', strtotime($time)),
                'tip_date_label' => _DATE,
                'tip_reads' => (string)$counter,
                'tip_reads_label' => _READS,
            ]);
            $cont .= $tpl->getHtmlFrag('new/table-row-content', [
                'id' => (string)$id,
                'href' => $href,
                'title_attr' => $title,
                'title_text' => $title,
                'title_new' => getTplNewGraphic($time),
                'tip' => $tip,
                'is_moder' => $ismoder,
                'editor' => _EDITOR,
                'edit_href' => $afile.'.php?name=content&amp;op=add&amp;id='.$id,
                'edit_text' => _FULLEDIT,
                'delete_href' => $afile.'.php?name=content&amp;op=delete&amp;id='.$id.'&amp;refer=1&amp;token='.$token,
                'delete_text' => _ONDELETE,
                'delete_ask' => $ask,
            ]);
        }
        $cont .= $tpl->getHtmlFrag('new/table', []);
        $cont .= getTplPager([
            'limit'  => $limit,
            'maxpg'  => $nump,
            'table'  => '_content',
            'field'  => 'id',
            'mod'    => $conf['name'],
            'where'  => 'time <= NOW()',
            'prefix' => 'new/',
        ]);
    } else {
        $cont .= $tpl->getHtmlFrag('new/alert', ['is_warn' => false, 'text' => _NO_INFO]);
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
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET body = :body, time = NOW() WHERE id = :id', ['body' => $rss, 'id' => $id]);
            }
        }
        $fields = getTplFieldsOut($field, $conf['name']);
        $hometext = $body . $fields;
        $seodesc = cutstr(trim(strip_tags($prs->filterContent($hometext, false, $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $title,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $time,
            'author' => $conf['sitename'],
        ]);
        $ask = str_replace(["\\", "'"], ["\\\\", "\\'"], _DELETE.' &quot;'.$title.'&quot;?');
        $cont = $tpl->getHtmlFrag('new/view', [
            'is_moder' => is_moder($conf['name']),
            'title_text' => filterTextHighlight($title, $word),
            'text' => filterTextHighlight($prs->filterDoc($body, false, $conf['name']), $word),
            'fields' => $fields,
            'editor' => _EDITOR,
            'edit_href' => $afile.'.php?name=content&amp;op=add&amp;id='.$id,
            'edit_text' => _FULLEDIT,
            'delete_href' => $afile.'.php?name=content&amp;op=delete&amp;id='.$id.'&amp;token='.getSiteToken(),
            'delete_text' => _ONDELETE,
            'delete_ask' => $ask,
            'back_title' => _BACK,
            'back_text' => _BACK,
        ]);
        echo $cont;
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: content(); break;
    case 'view': view(); break;
}
