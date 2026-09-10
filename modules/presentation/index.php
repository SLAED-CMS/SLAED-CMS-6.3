<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE')) {
    header('Location: ../../index.php');
    exit;
}

# Reads the site gallery: every thumbnail with an original, named and categorised by config/presentation.php, rated by thumbnail size and shuffled once per request
function getPresentationSites(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/sites';
    $meta = $conf['presentation']['sites'] ?? [];
    $rows = [];
    foreach (scandir($dir.'/thumb') ?: [] as $file) {
        $path = $dir.'/thumb/'.$file;
        if (str_starts_with($file, '.') || !is_file($path) || !is_file($dir.'/'.$file) || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $file)) continue;
        [$wid, $hei] = getImageBox($path);
        if ($wid < 1) continue;
        $item = $meta[$file] ?? [];
        $cat = (string)($item['cat'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/sites/thumb/'.$file,
            'w' => $wid,
            'h' => $hei,
            'name' => (string)($item['name'] ?? pathinfo($file, PATHINFO_FILENAME)),
            'cat' => defined($cat) ? constant($cat) : $cat,
            'has_cat' => $cat !== '',
            'rate' => (int)round(filesize($path) / 20),
        ];
    }
    shuffle($rows);
    foreach (array_keys($rows) as $i) $rows[$i]['num'] = $i + 1;
    return $rows;
}

# Reads the brand archive in config/presentation.php order: thumbnail, original and box per file, title and group from constants, logotypes and partner badges shown whole
function getPresentationBrand(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/brand';
    $rows = [];
    foreach ($conf['presentation']['brand'] ?? [] as $file => $item) {
        if (!is_file($dir.'/'.$file)) continue;
        $thumb = is_file($dir.'/thumb/'.$file) ? 'thumb/'.$file : $file;
        [$wid, $hei] = getImageBox($dir.'/'.$thumb);
        $title = (string)($item['title'] ?? '');
        $group = (string)($item['group'] ?? '');
        $mark = (string)($item['mark'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/brand/'.$thumb,
            'href' => 'uploads/presentation/brand/'.$file,
            'w' => $wid,
            'h' => $hei,
            'title' => (defined($title) ? constant($title) : $title).($mark !== '' ? ' · '.$mark : ''),
            'group' => defined($group) ? constant($group) : $group,
            'is_contain' => str_starts_with($file, 'logotype-') || str_starts_with($file, 'partner_'),
            'num' => count($rows) + 1,
        ];
    }
    return $rows;
}

# Reads the four principles in the order of config/presentation.php: the band image with its box, the title, the two paragraphs and the icon resolved from constants
function getPresentationDna(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/dna';
    $rows = [];
    foreach ($conf['presentation']['dna'] ?? [] as $file => $item) {
        if (!is_file($dir.'/'.$file)) continue;
        [$wid, $hei] = getImageBox($dir.'/'.$file);
        $title = (string)($item['title'] ?? '');
        $text = (string)($item['text'] ?? '');
        $more = (string)($item['more'] ?? '');
        $href = (string)($item['href'] ?? '');
        $rows[] = [
            'src' => 'uploads/presentation/dna/'.$file,
            'w' => $wid,
            'h' => $hei,
            'title' => defined($title) ? constant($title) : $title,
            'text' => defined($text) ? constant($text) : $text,
            'more' => defined($more) ? constant($more) : $more,
            'icon' => (string)($item['icon'] ?? ''),
            'href' => $href,
            'has_href' => $href !== '',
        ];
    }
    return $rows;
}

# Reads the owner voices from config/presentation.php: quote, author and site as raw text, role and marks resolved from constants, initials built from the author name
function getPresentationVoices(): array {
    global $conf;
    $rows = [];
    foreach ($conf['presentation']['voices'] ?? [] as $item) {
        $name = trim((string)($item['name'] ?? ''));
        $init = '';
        foreach (preg_split('/\s+/u', $name) ?: [] as $word) $init .= mb_substr($word, 0, 1, 'utf-8');
        $role = (string)($item['role'] ?? '');
        $marks = [];
        foreach ($item['marks'] ?? [] as $mark) {
            $label = (string)($mark['label'] ?? '');
            $marks[] = ['icon' => (string)($mark['icon'] ?? ''), 'label' => defined($label) ? constant($label) : $label];
        }
        $rows[] = [
            'text' => (string)($item['text'] ?? ''),
            'name' => $name,
            'initials' => mb_strtoupper($init, 'utf-8'),
            'site' => (string)($item['site'] ?? ''),
            'role' => defined($role) ? constant($role) : $role,
            'since' => (string)($item['since'] ?? ''),
            'tone' => (string)($item['tone'] ?? ''),
            'marks' => $marks,
        ];
    }
    return $rows;
}

function presentation(): void {
    setHead([
        'title' => _PRES_TITLE,
        'desc' => _PRES_DESC,
    ]);
    setFoot();
}

switch ($op) {
    default: presentation(); break;
}
