<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('MODULE_FILE') && !defined('ADMIN_FILE')) die('Illegal file access');

# The one upload root of every owner, at the project level and never below the document root, so no server can execute or list an uploaded file
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', BASE_DIR.'/uploads');

# The upload folders the light path serves at their direct address uploads/<folder>/<name>; any other folder below UPLOADS_DIR is read only through the route of its owner
# Closing an owner takes its folder off this list and moves no file, and getUploadUrl() asks the same list, so an address the site prints and its delivery never disagree
function getUploadPublic(): array {
    return ['all', 'avatars', 'presentation'];
}

# Return the address uploads/<path> of a path relative to UPLOADS_DIR when its first folder is on the public list of the light path, and an empty string for any other
# A file of a private owner has no address of its own: only the route of that owner builds one, after its rights check, so no caller spells uploads/ into an address
function getUploadUrl(string $path): string {
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return in_array(explode('/', $path)[0], getUploadPublic(), true) ? 'uploads/'.$path : '';
}

# The path below uploads/ one request asks for, or null for a request of anything else; the prefix is the folder of the entry, and in the root-on-the-project mode its parent
# The path is decoded once here, so a separator or a dot segment written as an escape is judged by setUploadStream() in the form the filesystem would see it
function getUploadRequest(): ?string {
    $path = rawurldecode((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    $base = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    foreach (array_unique([$base, (string)preg_replace('#/public$#', '', $base)]) as $root) {
        if (str_starts_with($path, $root.'/uploads/')) return substr($path, strlen($root) + 9);
    }
    return null;
}

# The light path: answer one request below uploads/ before the core boots, without configuration, database or session, and end the request there
# Only a file of a public folder is served, a thumb/ copy included; a private folder, a dot segment, a control character, a link out of its folder or a missing file is gone
# The type comes from a fixed map of the formats the upload service accepts; getFileStream() sends a type outside its inline list as an attachment, an SVG included
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

# Hand one stored file to the client and end the request there, which is the single file answer of the project and the one place its headers are decided
# The caller names the type from server metadata or a fixed map, never from the request, and a type outside the closed inline registry is sent as the opaque one and saved
# Every answer carries nosniff and a sandbox policy, so a stored SVG or HTML file opened by its address runs no script on the site
# The name is reduced to its own last segment and encoded, so a name assembled out of a request carries no separator and can append no header line
# The cache mode none forbids a copy, private lets one browser keep it revalidated through its entity tag and date, public lets any cache keep it a day
# The file itself is never copied into the cache directory
# A GET honours one byte range, a malformed or unsatisfiable one gets 416 and several are ignored for the whole file; a HEAD gets the headers of the whole file
# $start runs once, after the conditions are decided and before the first header leaves, for a whole body or a range from byte zero alone - never for HEAD, 304 or 416
# The body is read in bounded blocks from the one open handle and the loop stops when the client goes away, so the size of the file never reaches the memory of PHP
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
