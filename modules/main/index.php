<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

function main(): void {
    global $db, $tpl, $prs;
    setHead([
        'title' => 'Простота Функциональность Эффективность Безопасность',
        'desc' => 'Система управления содержимым сайта, простая в использовании и настройке, имеющая при этом высокий уровень безопасности, высокую скорость работы, а также практически неограниченный потенциал в решении вопроса расширения функциональности.',
    ]);
    $cont = $tpl->getHtmlPart('main-slider', []);
    $path = 'uploads/screens';
    $path2 = 'uploads/screens/thumb';
    $screens = [];
    if (is_dir($path2)) {
        $dir = opendir($path2);
        if ($dir !== false) {
            while (false !== ($file = readdir($dir))) {
                if ($file != '.' && $file != '..' && $file != 'index.html' && !is_dir($path2.'/'.$file)) $screens[] = $file;
            }
            closedir($dir);
        }
    }
    if ($screens) shuffle($screens);
    $items_html = '';
    foreach ($screens as $val) {
        $sname = ucfirst(str_ireplace(['.gif', '.jpg', '.png', '.com', '.net', '.ru', '.ua', '.biz', '.info', '.su', '.in', '.org'], '', $val));
        $srat = round(filesize($path2.'/'.$val) / 20);
        $items_html .= $tpl->getHtmlFrag('main-carousel-item', [
            'full_url'     => $path.'/'.$val,
            'link_title'   => _SITE.': '.$sname.', '._RATING.': '.$srat,
            'thumb_url'    => $path2.'/'.$val,
            'img_alt'      => _SITE.': '.$sname,
            'site_label'   => _SITE,
            'site_name'    => $sname,
            'rating_label' => _RATING,
            'rating'       => $srat,
        ]);
    }
    $cont .= $tpl->getHtmlFrag('main-carousel', ['heading' => 'Примеры внедрений системы', 'items_html' => $items_html]);
    $news_html = '';
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.time, s.intro, c.title, c.intro FROM '.PREFIX_DB.'_news AS s LEFT JOIN '.PREFIX_DB."_categories AS c ON (s.cid=c.id) WHERE s.time <= NOW() AND s.status != '0' ORDER BY s.time DESC LIMIT 3");
    while ([$id, $cid, $title, $time, $hometext, $ctitle, $cdesc] = $db->getSqlRow($result)) {
        $linkstrip = cutstr($title, 45);
        if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
            $img = 'uploads/news/thumb/'.trim($match[1]);
        } else {
            preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
            $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
        }
        $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
        $ntext = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, 'news'))), ENT_QUOTES), 60);
        $news_html .= $tpl->getHtmlFrag('main-content-item', [
            'url'       => 'index.php?name=news&amp;op=view&amp;id='.$id,
            'title'     => $title,
            'label'     => $linkstrip,
            'img'       => $img,
            'text'      => $ntext,
            'cat_url'   => 'index.php?name=news&amp;cat='.$cid,
            'cat_desc'  => $cdesc,
            'cat_title' => $ctitle,
            'date_label' => _DATE,
            'date'      => format_time($time, _TIMESTRING),
        ]);
    }
    $files_html = '';
    $result = $db->getSqlQuery('SELECT s.id, s.cid, s.title, s.intro, s.time, c.title, c.intro FROM '.PREFIX_DB.'_files AS s LEFT JOIN '.PREFIX_DB."_categories AS c ON (s.cid=c.id) WHERE s.time <= NOW() AND s.status != '0' ORDER BY s.time DESC LIMIT 3");
    while ([$id, $cid, $title, $hometext, $time, $ctitle, $cdesc] = $db->getSqlRow($result)) {
        $linkstrip = cutstr($title, 45);
        if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
            $img = 'uploads/files/thumb/'.trim($match[1]);
        } else {
            preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
            $img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : '');
        }
        $img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
        $ntext = cutstr(htmlspecialchars(trim(strip_tags($prs->filterContent($hometext, false, 'files'))), ENT_QUOTES), 60);
        $files_html .= $tpl->getHtmlFrag('main-content-item', [
            'url'       => 'index.php?name=files&amp;op=view&amp;id='.$id,
            'title'     => $title,
            'label'     => $linkstrip,
            'img'       => $img,
            'text'      => $ntext,
            'cat_url'   => 'index.php?name=files&amp;cat='.$cid,
            'cat_desc'  => $cdesc,
            'cat_title' => $ctitle,
            'date_label' => _DATE,
            'date'      => format_time($time, _TIMESTRING),
        ]);
    }
    $col1 = $tpl->getHtmlFrag('main-section', [
        'section_title' => _NEWS,
        'items_html'    => $news_html,
        'more_url'      => 'index.php?name=news',
        'more_title'    => _NEWS,
        'more_label'    => _NEWS,
    ]);
    $col2 = $tpl->getHtmlFrag('main-section', [
        'section_title' => _FILES,
        'items_html'    => $files_html,
        'more_url'      => 'index.php?name=files',
        'more_title'    => _FILES,
        'more_label'    => _FILES,
    ]);
    $cont .= $tpl->getHtmlFrag('main-grid', ['col1_html' => $col1, 'col2_html' => $col2]);
    echo $cont;
    setFoot();
}

switch ($op) {
    default: main(); break;
}
