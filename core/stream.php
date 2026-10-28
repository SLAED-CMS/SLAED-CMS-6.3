<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

# The one upload root of every owner, at the project level and never below the document root, so no server can execute or list an uploaded file
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', BASE_DIR.'/uploads');

# The upload folders the light path serves at uploads/<folder>/<name>; getUploadUrl() asks the same list, so a printed address and its delivery agree
function getUploadPublic(): array {
    return ['all', 'avatars', 'layout', 'presentation'];
}

# The address uploads/<path> of a file in a public folder, else an empty string; a file of a private owner gets one only from its route after the rights check
function getUploadUrl(string $path): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return in_array(explode('/', $path)[0], getUploadPublic(), true) ? HOME_PATH.'uploads/'.$path : '';
}

# The path below the folder $dir of the site, decoded once as the filesystem would see it, or null; the prefix is the folder of the entry or, root on the project, its parent
function getRequestPath(string $dir): ?string {
    $path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    foreach (array_unique([$base, (string)preg_replace('#/public$#', '', $base)]) as $root) {
        if (str_starts_with($path, $root.'/'.$dir.'/')) return substr($path, strlen($root) + strlen($dir) + 2);
    }
    return null;
}

# The light path: serve one file of a public upload folder before the core boots, anything else 410; the type comes from a fixed map, never the request
function setUploadStream(string $rel): never {
    $mime = ['avif' => 'image/avif', 'flac' => 'audio/flac', 'gif' => 'image/gif', 'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'm4a' => 'audio/mp4', 'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4', 'oga' => 'audio/ogg', 'ogg' => 'audio/ogg', 'opus' => 'audio/ogg', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'wav' => 'audio/wav',
        'webm' => 'video/webm', 'webp' => 'image/webp'];
    $part = explode('/', $rel);
    $good = count($part) > 1 && in_array($part[0], getUploadPublic(), true) && !preg_match('#[\x00-\x1f\\\\]#', $rel);
    foreach ($part as $one) if ($one === '' || $one[0] === '.') $good = false;
    $root = $good ? realpath(UPLOADS_DIR.'/'.$part[0]) : false;
    $file = $good ? realpath(UPLOADS_DIR.'/'.$rel) : false;
    $root = ($root === false) ? '' : rtrim(str_replace('\\', '/', $root), '/').'/';
    $good = $root !== '' && $file !== false && is_file($file) && str_starts_with(str_replace('\\', '/', $file), $root);
    if (!$good) {
        http_response_code(410);
        exit;
    }
    if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
    require_once BASE_DIR.'/core/classes/cache.php';
    getFileStream($file, basename($file), $mime[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream', true, 'public');
}

# The one file answer of the project, sandboxed, its type from a closed list; $start runs before the headers leave, for a body from byte zero, never HEAD, 304 or 416
function getFileStream(string $path, string $name, string $mime = 'application/octet-stream', bool $inline = false, string $cache = 'none', ?callable $start = null): never {
    $safe = ['image/gif', 'image/jpeg', 'image/png', 'image/webp', 'image/avif', 'audio/mpeg', 'audio/wav', 'audio/x-wav', 'audio/vnd.wave', 'audio/flac', 'audio/x-flac',
        'audio/ogg', 'audio/mp4', 'audio/x-m4a', 'audio/webm', 'video/mp4', 'video/webm', 'video/ogg'];
    $name = rawurlencode(basename(str_replace('\\', '/', $name)));
    $hand = ($name !== '' && is_file($path) && is_readable($path)) ? fopen($path, 'rb') : false;
    $stat = $hand ? fstat($hand) : false;
    if ($stat === false) {
        if ($hand) fclose($hand);
        http_response_code(404);
        exit;
    }
    $size = $stat['size'];
    $mtime = $stat['mtime'];
    $type = in_array($mime, $safe, true) ? $mime : 'application/octet-stream';
    $show = $inline && $type !== 'application/octet-stream';
    $etag = '"'.substr(sha1((realpath($path) ?: $path).'|'.$size.'|'.$mtime), 0, 32).'"';
    $head = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';
    while (ob_get_level() > 0) ob_end_clean();
    ini_set('zlib.output_compression', '0');
    header('Content-Security-Policy: sandbox');
    if ($cache === 'private' || $cache === 'public') {
        Cache::setHeaders($cache, $type, $mtime, $etag, ($cache === 'public') ? 86400 : 0);
        if (Cache::checkNotModified($mtime, $etag)) {
            fclose($hand);
            exit;
        }
    } else {
        Cache::setHeaders('none', $type);
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0, no-transform');
    }
    $from = 0;
    $last = $size - 1;
    $want = $head ? '' : trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
    $cond = trim((string)($_SERVER['HTTP_IF_RANGE'] ?? ''));
    if ($want !== '' && $cond !== '' && $cond !== $etag && $cond !== gmdate('D, d M Y H:i:s', $mtime).' GMT') $want = '';
    if ($want !== '' && preg_match('#^bytes\s*=#i', $want)) {
        $sets = array_map('trim', explode(',', preg_replace('#^bytes\s*=#i', '', $want)));
        $bad = false;
        foreach ($sets as $one) if (!preg_match('#^(?:\d+-\d*|-\d+)$#D', $one)) $bad = true;
        if (!$bad && count($sets) === 1) {
            [$lo, $hi] = explode('-', $sets[0]);
            if ($lo === '') {
                $from = max(0, $size - intval($hi));
                $bad = intval($hi) === 0 || $size === 0;
            } else {
                $from = intval($lo);
                if ($hi !== '') $last = min($last, intval($hi));
                $bad = $from >= $size || ($hi !== '' && intval($hi) < $from);
            }
        }
        if ($bad) {
            fclose($hand);
            header('Content-Range: bytes */'.$size);
            http_response_code(416);
            exit;
        }
        if (count($sets) > 1) {
            $from = 0;
            $last = $size - 1;
        } else {
            http_response_code(206);
            header('Content-Range: bytes '.$from.'-'.$last.'/'.$size);
        }
    }
    header('Accept-Ranges: bytes');
    header('Content-Disposition: '.($show ? 'inline' : 'attachment').'; filename="'.$name.'"; filename*=UTF-8\'\''.$name);
    header('Content-Length: '.max(0, $last - $from + 1));
    if ($head) {
        fclose($hand);
        exit;
    }
    if ($start !== null && $from === 0) $start();
    set_time_limit(0);
    if ($from > 0) fseek($hand, $from);
    $left = $last - $from + 1;
    while ($left > 0 && !feof($hand) && !connection_aborted()) {
        $part = fread($hand, min(65536, $left));
        if ($part === false || $part === '') break;
        echo $part;
        flush();
        $left -= strlen($part);
    }
    fclose($hand);
    exit;
}
