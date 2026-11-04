<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Reads the site gallery: every original with a WebP thumbnail of 640 px made on first sight or after a newer original, named by config/presentation.php, shuffled
function getPresentationSites(): array {
    global $conf;
    $dir = UPLOADS_DIR.'/presentation/sites';
    $meta = $conf['presentation']['sites'] ?? [];
    $rows = [];
    if (!is_dir($dir.'/thumb')) mkdir($dir.'/thumb', 0755);
    foreach (scandir($dir) ?: [] as $file) {
        $orig = $dir.'/'.$file;
        if (str_starts_with($file, '.') || !is_file($orig) || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $file)) continue;
        $name = 'thumb/'.$file.'.webp';
        $path = $dir.'/'.$name;
        if (!is_file($path) || filemtime($path) < filemtime($orig)) {
            [$full] = getImageBox($orig);
            $temp = $path.'.'.getmypid().'.tmp';
            if (getImageThumb($orig, $temp, min(640, max(1, $full)), IMAGETYPE_WEBP, 82) !== $temp || !rename($temp, $path)) {
                if (is_file($temp)) unlink($temp);
                $name = $file;
                $path = $orig;
            }
        }
        [$wid, $hei] = getImageBox($path);
        if ($wid < 1) continue;
        $item = $meta[$file] ?? [];
        $cat = (string)($item['cat'] ?? '');
        $rows[] = [
            'src' => getUploadUrl('presentation/sites/'.$name),
            'w' => $wid,
            'h' => $hei,
            'name' => (string)($item['name'] ?? pathinfo($file, PATHINFO_FILENAME)),
            'cat' => defined($cat) ? constant($cat) : $cat,
            'has_cat' => $cat !== '',
            'rate' => (int)round(filesize($orig) / 1000),
        ];
    }
    shuffle($rows);
    foreach (array_keys($rows) as $i) $rows[$i]['num'] = $i + 1;
    return $rows;
}

# Counts the files and bytes under one cache directory, the stored renderings or the compiled templates, for the request path to show as they stand
function getPresentationCacheDir(string $dir): array {
    $num = 0;
    $size = 0;
    if (!is_dir($dir)) return ['num' => 0, 'size' => 0];
    $walk = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($walk as $file) {
        if (!$file->isFile() || str_starts_with($file->getFilename(), '.')) continue;
        $num++;
        $size += $file->getSize();
    }
    return ['num' => $num, 'size' => $size];
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
            'src' => getUploadUrl('presentation/brand/'.$thumb),
            'href' => getUploadUrl('presentation/brand/'.$file),
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
            'src' => getUploadUrl('presentation/dna/'.$file), 'key' => pathinfo($file, PATHINFO_FILENAME),
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
