<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

# Read the data of the page as addSitemapTask() wrote it; a missing file or one of another shape reads as no map
function getSitemapData(): array {
    $path = SITEMAP_DIR.'/sitemap.json';
    $data = is_readable($path) ? json_decode((string)file_get_contents($path), true) : [];
    return (is_array($data) && isset($data['stamp'], $data['secs'])) ? $data + ['time' => filemtime($path)] : [];
}

# Shape a part of the rows of one category that starts at a place of its list: the rows, the count of the rest and the address that appends the next part
function getSitemapRows(array $sec, array $cat, array $rows, int $from): array {
    $view = [];
    foreach ($rows as [, $title, $href, $date]) $view[] = ['title' => $title, 'href' => $href, 'fresh_html' => ($date !== '') ? getTplNewGraphic($date) : ''];
    $next = $from + count($rows);
    return ['rows' => $view, 'rest' => max(0, $cat['count'] - $next), 'href' => $cat['href'] ?: $sec['href'],
        'more' => HOME_PATH.'index.php?name=sitemap&op=more&mod='.$sec['name'].'&cat='.$cat['id'].'&from='.$next];
}

function sitemap(): void {
    global $tpl;
    setHead(['title' => _SITEMAP, 'kind' => 'collection']);
    $data = getSitemapData();
    $secs = [];
    $named = $total = 0;
    foreach ($data['secs'] ?? [] as $num => $sec) {
        $cats = [];
        $sum = $own = 0;
        foreach ($sec['cats'] as $cat) {
            $cats[] = ['title' => $cat['title'], 'count' => $cat['count']] + getSitemapRows($sec, $cat, $cat['rows'], 0);
            $sum += $cat['count'];
            $own += intval($cat['title'] !== '');
        }
        $chips = $own ? [['tone' => 'neutral', 'icon' => 'folder', 'label' => $own, 'title' => _CATEGORIES]] : [];
        $secs[] = ['id' => 'map-'.$sec['name'], 'tone' => $num % 6, 'icon' => $sec['icon'] ?? getIconName($sec['name']), 'title' => $sec['title'], 'href' => $sec['href'],
            'chips' => [...$chips, ['tone' => 'neutral', 'icon' => 'file-earmark-text', 'label' => $sum, 'title' => _PAGENAV]],
            'level' => 2, 'is_fold' => true, 'is_open' => true, 'plus_text' => _OPCL.': '.$sec['title'], 'cats' => $cats];
        $named += $own;
        $total += $sum;
    }
    if (!$secs) {
        echo $tpl->getHtmlFrag('title', ['title' => _SITEMAP, 'is_level_one' => true]).$tpl->getHtmlFrag('empty', []);
        setFoot();
        return;
    }
    $all = $tpl->getHtmlFrag('button', ['icon_name' => 'arrows-expand', 'label' => _EXPAND,
        'button_attr' => 'data-sl-map-all data-sl-open="'._EXPAND.'" data-sl-shut="'._COLLAPSE.'" aria-pressed="false"']);
    echo $tpl->getHtmlPart('sitemap', [
        'head' => ['icon' => getIconName('sitemap'), 'title' => _SITEMAP, 'level' => 1, 'tools_html' => $all, 'chips' => [
            ['tone' => 'neutral', 'icon' => 'grid', 'label' => count($secs), 'title' => _SECTIONS],
            ['tone' => 'neutral', 'icon' => 'folder', 'label' => $named, 'title' => _CATEGORIES],
            ['tone' => 'neutral', 'icon' => 'file-earmark-text', 'label' => $total, 'title' => _PAGENAV],
            ['tone' => 'info', 'icon' => 'clock-history', 'label' => format_time(date('Y-m-d H:i:s', $data['time'])), 'title' => _UPDATED],
        ]],
        'secs' => $secs,
        'none' => ['is_found' => true, 'is_small' => true],
    ]);
    setFoot();
}

function more(): void {
    global $tpl;
    if (empty($_SERVER['HTTP_HX_REQUEST'])) setRedirect(getSeoUrl(['name' => 'sitemap']));
    $mod = getVar('get', 'mod', 'var', '');
    $cid = getVar('get', 'cat', 'num', 0);
    $from = getVar('get', 'from', 'num', 0);
    $data = getSitemapData();
    foreach ($data['secs'] ?? [] as $sec) {
        foreach (($sec['name'] === $mod) ? $sec['cats'] : [] as $cat) {
            $path = SITEMAP_DIR.'/'.$data['stamp'].'-'.$sec['name'].'-'.$cat['id'].'.rows';
            if (intval($cat['id']) !== $cid || !is_file($path)) continue;
            $file = new SplFileObject($path);
            $file->seek($from);
            $rows = [];
            for ($i = 0; $i < 50 && !$file->eof(); $i++, $file->next()) if (is_array($row = json_decode((string)$file->current(), true))) $rows[] = $row;
            echo $tpl->getHtmlFrag('sitemap-rows', getSitemapRows($sec, $cat, $rows, $from));
            return;
        }
    }
    header('HX-Refresh: true');
}

switch ($op) {
    default: sitemap(); break;
    case 'more': more(); break;
}
