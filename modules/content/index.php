<?php
# Author: Eduard Laas
# Copyright Â© 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function content(): void {
    global $db, $afile, $conf, $tpl;
    $limit = (int)($conf['content']['num'] ?? ($conf['content']['num'] ?? 10));
    $nump = (int)($conf['content']['nump'] ?? ($conf['content']['nump'] ?? 5));
    if ($limit < 1) $limit = 10;
    if ($nump < 1) $nump = 5;
    setHead(['title' => _CONTENT]);
    $cont = $tpl->getHtmlFrag('title', ['title' => _CONTENT]);
    $num = getVar('get', 'num', 'num', '1');
    $offset = ($num - 1) * $limit;
    $result = $db->getSqlQuery('SELECT id, title, body, time, counter FROM '.PREFIX_DB.'_content WHERE time <= NOW() ORDER BY time DESC LIMIT '.$offset.', '.$limit);
    if ($db->getSqlRowCount($result) > 0) {
        $cont .= $tpl->getHtmlFrag('content-list-open', ['id' => _ID, 'title' => _TITLE, 'functions' => _FUNCTIONS]);
        while ([$id, $title, $body, $time, $counter]= $db->getSqlRow($result)) {
            $moder = (is_moder($conf['name'])) ? '<a href="'.$afile.'.php?op=content_add&amp;id='.$id.'" title="'._FULLEDIT.'">'._FULLEDIT.'</a>||<a href="'.$afile.'.php?op=content_delete&amp;id='.$id.'&amp;refer=1" OnClick="return DelCheck(this, \''._DELETE.' &quot;'.$title.'&quot;?\');" title="'._ONDELETE.'">'._ONDELETE.'</a>||' : '';
            $href = getSeoUrl(['name' => $conf['name'], 'op' => 'view', 'id' => $id, 'title' => $title]);
            $actions = add_menu($moder.'<a href="index.php?name=content&amp;op=view&amp;id='.$id.'" title="'._SHOW.'">'._SHOW.'</a>');
            $cont .= $tpl->getHtmlFrag('content-list-basic', [
                'id' => $id,
                'tip' => title_tip(_DATE.': '.format_time($time, _TIMESTRING).'<br>'._READS.': '.$counter),
                'href' => $href,
                'title_attr' => $title,
                'title_text' => $title,
                'title_new' => new_graphic($time),
                'actions' => $actions,
            ]);
        }
        $cont .= setTemplateBasic('table-close');
        $cont .= setArticleNumbers('pagenum', $conf['name'], $limit, '', 'id', '_content', '', '', $nump);
    } else {
        $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => false, 'text' => _NO_INFO]);
    }
    echo $cont;
    setFoot();
}

function view(): void {
    global $db, $conf, $tpl;
    $id = getVar('get', 'id', 'num');
    $word = getVar('get', 'word', 'word');
    $result = $db->getSqlQuery('SELECT id, title, body, field, url, time, refresh FROM '.PREFIX_DB.'_content WHERE id = :id AND time <= NOW()', ['id' => $id]);
    if ($db->getSqlRowCount($result) == 1) {
        $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET counter = counter+1 WHERE id = :id', ['id' => $id]);
        [$id, $title, $body, $field, $url, $time, $refresh] = $db->getSqlRow($result);
        if ($url) {
            $past = time() - $refresh;
            if (strtotime($time) < $past) {
                $conf['content'] = rss_read($url, 1);
                $db->getSqlQuery('UPDATE '.PREFIX_DB.'_content SET body = :body, time = NOW() WHERE id = :id', ['body' => $conf['content'], 'id' => $id]);
            }
        }
        $fields = fields_out($field, $conf['name']);
        $fields = ($fields) ? '<br><br>'.$fields : '';
        $hometext = $body.$fields;
        $seodesc = cutstr(trim(strip_tags(filterReplaceText(filterMarkdown($hometext, $conf['name'], false), $conf['name']))), 160);
        $seoimg = getImgText($hometext, '', false);
        $seoimg = $seoimg ? $conf['homeurl'].'/'.$seoimg : '';
        setHead([
            'title' => $title,
            'desc' => $seodesc,
            'img' => $seoimg,
            'time' => $time,
            'author' => $conf['sitename'],
        ]);
        echo $tpl->getHtmlFrag('title', ['title' => $title]).filterTextHighlight(filterMarkdown($hometext, $conf['name'], false), $word);
        setFoot();
    } else {
        setRedirect('index.php?name='.$conf['name']);
    }
}

switch($op) {
    default: content(); break;
    case 'view': view(); break;
}
