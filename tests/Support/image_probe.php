<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the image pipeline tests of batch 2 in docs/UPLOAD-2026.md
# boots the real core like index.php, one scenario per process, and drives getImageThumb() against fixtures GD writes itself
# Nothing under the site upload tree is read or written - every fixture and every thumbnail lives below the scratch root the caller passed
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

# The scratch directory every fixture and every produced thumbnail of this run lives in
$GLOBALS['proot'] = $probework.'/img';

# Start every scenario from an empty scratch directory, so a previous run cannot be counted as this run's output
function addProbeRoot(): void {
    if (!is_dir($GLOBALS['proot'])) {
        mkdir($GLOBALS['proot'], 0777, true);
        return;
    }
    foreach (scandir($GLOBALS['proot']) ?: [] as $file) {
        $path = $GLOBALS['proot'].'/'.$file;
        if ($file === '.' || $file === '..') continue;
        if (is_file($path)) unlink($path);
        if (is_dir($path)) deleteDir($path);
    }
}

# Report which of the two new formats the local GD build can both decode and encode, so a scenario is skipped rather than failed on a build without them
function getProbeCaps(): array {
    return [
        'gif' => function_exists('imagecreatefromgif') && function_exists('imagegif'),
        'jpg' => function_exists('imagecreatefromjpeg') && function_exists('imagejpeg'),
        'png' => function_exists('imagecreatefrompng') && function_exists('imagepng'),
        'webp' => function_exists('imagecreatefromwebp') && function_exists('imagewebp'),
        'avif' => function_exists('imagecreatefromavif') && function_exists('imageavif'),
        'bmp' => function_exists('imagebmp'),
    ];
}

# Write one solid 64x48 fixture in the requested format and return its path, or an empty string when the GD build cannot encode it
function addProbeImage(string $ext): string {
    $img = imagecreatetruecolor(64, 48);
    $col = imagecolorallocate($img, 20, 120, 200);
    imagefilledrectangle($img, 0, 0, 63, 47, $col);
    $path = $GLOBALS['proot'].'/source.'.$ext;
    $done = match ($ext) {
        'gif' => function_exists('imagegif') && imagegif($img, $path),
        'jpg' => function_exists('imagejpeg') && imagejpeg($img, $path),
        'png' => function_exists('imagepng') && imagepng($img, $path),
        'webp' => function_exists('imagewebp') && imagewebp($img, $path),
        'avif' => function_exists('imageavif') && imageavif($img, $path),
        'bmp' => function_exists('imagebmp') && imagebmp($img, $path),
        default => false,
    };
    return $done && is_file($path) ? $path : '';
}

# Describe one produced file by existence, detected image type and pixel size
function getProbeInfo(string $path): array {
    if (!is_file($path)) return ['exists' => false, 'type' => 0, 'width' => 0, 'height' => 0];
    $info = getimagesize($path);
    if (!is_array($info)) return ['exists' => true, 'type' => 0, 'width' => 0, 'height' => 0];
    return ['exists' => true, 'type' => (int)$info[2], 'width' => (int)$info[0], 'height' => (int)$info[1]];
}

# Every raster format the helper has a branch for: the thumbnail must be written, keep its own format and be scaled to the requested width
function getProbeThumb(): array {
    $out = [];
    foreach (['gif', 'jpg', 'png', 'webp', 'avif'] as $ext) {
        addProbeRoot();
        $src = addProbeImage($ext);
        if ($src === '') {
            $out[$ext] = ['made' => false];
            continue;
        }
        $dest = $GLOBALS['proot'].'/thumb.'.$ext;
        $back = getImageThumb($src, $dest, 32);
        $out[$ext] = ['made' => true, 'same' => $back === $dest, 'source' => getProbeInfo($src), 'thumb' => getProbeInfo($dest)];
    }
    return $out;
}

# Everything the helper has no branch for must hand the source path back and leave the destination unwritten, which is what the caller reads as a failure
function getProbeSkip(): array {
    addProbeRoot();
    $out = [];
    $src = addProbeImage('bmp');
    $dest = $GLOBALS['proot'].'/thumb-bmp.png';
    $out['bmp'] = ['made' => $src !== '', 'same' => $src !== '' && getImageThumb($src, $dest, 32) === $src, 'written' => is_file($dest)];
    $file = $GLOBALS['proot'].'/plain.txt';
    file_put_contents($file, 'not an image at all');
    $dest = $GLOBALS['proot'].'/thumb-plain.png';
    $out['plain'] = ['same' => getImageThumb($file, $dest, 32) === $file, 'written' => is_file($dest)];
    $dest = $GLOBALS['proot'].'/thumb-missing.png';
    $file = $GLOBALS['proot'].'/gone.png';
    $out['missing'] = ['same' => getImageThumb($file, $dest, 32) === $file, 'written' => is_file($dest)];
    $src = addProbeImage('png');
    $dest = $GLOBALS['proot'].'/thumb-zero.png';
    $out['zero'] = ['made' => $src !== '', 'same' => $src !== '' && getImageThumb($src, $dest, 0) === $src, 'written' => is_file($dest)];
    return $out;
}

# The renderer half of the fallback: a source whose thumbnail cannot be produced must be rendered against the full size file and never against a thumbnail that was never written
# This is the one scenario that writes below the site upload tree, because filterAttach() resolves every path from BASE_DIR and cannot be pointed at the scratch root
# The fixture is a png cut to 33 bytes: the header still parses, so the markup is reached with real dimensions, while the decoder refuses it and no thumbnail is produced
function getProbeAttach(): array {
    if (!function_exists('imagepng') || !function_exists('imagecreatefrompng')) return ['ran' => false, 'why' => 'this build has no png decoder'];
    $dir = UPLOADS_DIR.'/news';
    if (!is_dir($dir) || !is_writable($dir)) return ['ran' => false, 'why' => 'uploads/news is not a writable directory on this installation'];
    $name = 'probeattach'.bin2hex(random_bytes(4)).'.png';
    $file = $dir.'/'.$name;
    $img = imagecreatetruecolor(64, 48);
    imagepng($img, $file);
    file_put_contents($file, substr((string)file_get_contents($file), 0, 33));
    $thumb = $dir.'/thumb/'.$name;
    $pars = new Parser();
    $html = $pars->filterDoc('[attach='.$name.' align=left title=probe]', false, 'news');
    $left = is_file($thumb);
    if (is_file($file)) unlink($file);
    if ($left) unlink($thumb);
    return ['ran' => true, 'html' => $html, 'src' => 'uploads/news/'.$name, 'thumb' => 'uploads/news/thumb/'.$name, 'left' => $left];
}

$mode = (string)($argv[1] ?? '');
$out = [];
try {
    addProbeRoot();
    $out = match ($mode) {
        'thumb' => ['caps' => getProbeCaps(), 'runs' => getProbeThumb()],
        'skip' => ['caps' => getProbeCaps(), 'runs' => getProbeSkip()],
        'attach' => ['caps' => getProbeCaps(), 'runs' => getProbeAttach()],
        default => ['error' => 'unknown scenario '.$mode],
    };
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
