<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Walks the route and persistence matrix of docs/UPLOAD-2026.md against a running stand over real HTTP
# The unit suite proves the class; this proves the adapters, which only exist as request handlers and cannot be reached by a double
# Every scenario compares the upload tree and the database before and after itself and removes what it created, so a run leaves the stand as it found it
#
# Usage: php tools/upload-route-check.php <base-url> [read|write|comp|captcha|all]
# Credentials come from the environment and never from the command line, because an argument list is readable by every process on the host:
#   SLAED_ADMIN_NAME, SLAED_ADMIN_PASS, SLAED_USER_NAME, SLAED_USER_PASS
# Optional: SLAED_REMOTE_URL names a public file for the positive remote row; without it that row reports as not run rather than as passed
# Optional: SLAED_ROUTE_INSECURE=1 disables TLS verification, for a stand with a self signed certificate only
#
# The comp and captcha modes fail one write on purpose and edit config/security.php; run them against a stand, never against production data

if (PHP_SAPI !== 'cli') die('Illegal file access');

$argn = $argv ?? [];
if (count($argn) < 2) {
    fwrite(STDERR, "Usage: php tools/upload-route-check.php <base-url> [read|write|comp|captcha|all]\n");
    fwrite(STDERR, "Credentials are read from SLAED_ADMIN_NAME, SLAED_ADMIN_PASS, SLAED_USER_NAME and SLAED_USER_PASS\n");
    exit(1);
}

define('ROOTDIR', str_replace('\\', '/', dirname(__DIR__)));
define('SITEURL', rtrim((string)$argn[1], '/'));
define('WORKDIR', str_replace('\\', '/', sys_get_temp_dir()).'/slaed_route_check');
define('NOVERIFY', (string)getenv('SLAED_ROUTE_INSECURE') === '1');
if (!is_dir(WORKDIR) && !mkdir(WORKDIR, 0777, true)) die("cannot create the scratch directory\n");

$GLOBALS['fails'] = 0;
$GLOBALS['skips'] = 0;
$GLOBALS['made'] = [];
$GLOBALS['rows'] = [];

# Derive one scoped CSRF token exactly as core/security.php derives it, from the session the cookie jar holds
function getScopeToken(string $jar, string $scope): string {
    $cfg = require ROOTDIR.'/config/security.php';
    $key = hash_hmac('sha256', 'csrf', (string)$cfg['security']['secret']);
    $sid = getSessionId($jar);
    return hash_hmac('sha256', $sid !== '' ? $scope.'|'.$sid : $scope, $key);
}

# Read the session identifier back out of a cookie jar written by cURL
function getSessionId(string $jar): string {
    if (!is_file($jar)) return '';
    foreach (file($jar) ?: [] as $line) {
        $part = preg_split('/\t/', trim($line)) ?: [];
        if (count($part) >= 7 && $part[5] === 'PHPSESSID') return $part[6];
    }
    return '';
}

# Perform one request against the stand; an array body is form encoded, a string body is sent verbatim so repeated field names keep their order
# TLS verification stays on unless the caller asked for a self signed stand, because this tool carries an administrator session
function getHttpReply(string $jar, string $url, array|string|null $post = null, array $file = []): array {
    $curl = curl_init();
    $opts = [
        CURLOPT_URL => str_starts_with($url, 'http') ? $url : SITEURL.'/'.ltrim($url, '/'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => !NOVERIFY,
        CURLOPT_SSL_VERIFYHOST => NOVERIFY ? 0 : 2,
        CURLOPT_COOKIEJAR => $jar,
        CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'SLAED upload route check',
    ];
    if (is_string($post)) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $post;
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded'];
    } elseif ($post !== null) {
        $body = $post;
        foreach ($file as $key => $path) $body[$key] = new CURLFile($path);
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $file ? $body : http_build_query($body);
    }
    curl_setopt_array($curl, $opts);
    $out = (string)curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $err = curl_error($curl);
    return ['code' => $code, 'body' => $out, 'err' => $err];
}

# Open an administrator session and prove it landed by fetching a panel route that needs one
function setAdminSession(string $jar, string $name, string $pass): bool {
    if (is_file($jar)) unlink($jar);
    getHttpReply($jar, '/admin.php');
    getHttpReply($jar, '/admin.php', ['op' => 'check_admin', 'name' => $name, 'pwd' => $pass]);
    return str_contains(getHttpReply($jar, '/admin.php?name=uploads')['body'], 'uploads-tabs');
}

# Open a site user session and prove it landed by fetching the profile route that carries the avatar form
function setUserSession(string $jar, string $name, string $pass): bool {
    if (is_file($jar)) unlink($jar);
    getHttpReply($jar, '/index.php?name=account');
    $post = ['op' => 'login', 'user_name' => $name, 'user_password' => $pass, 'token' => getScopeToken($jar, 'account')];
    getHttpReply($jar, '/index.php?name=account&op=login', $post);
    return str_contains(getHttpReply($jar, '/index.php?name=account&op=edithome')['body'], 'saveavatar');
}

# List one directory tree as a map of project relative path to byte size, which is what every before and after comparison is made of
function getDirTree(string $dir): array {
    $out = [];
    if (!is_dir($dir)) return $out;
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') continue;
        $path = $dir.'/'.$file;
        if (is_dir($path)) $out = array_merge($out, getDirTree($path));
        else $out[str_replace(ROOTDIR.'/', '', str_replace('\\', '/', $path))] = filesize($path);
    }
    return $out;
}

# Report what one directory gained during a scenario and remember it for the cleanup pass
function getTreeDelta(string $dir, array $was): array {
    $new = array_keys(array_diff_key(getDirTree($dir), $was));
    foreach ($new as $one) $GLOBALS['made'][] = $one;
    return $new;
}

# Build one source file of the requested shape below the scratch root
function addTestFixture(string $name, string $kind, int $wid = 40, int $hei = 30): string {
    $path = WORKDIR.'/'.$name;
    if ($kind === 'png' || $kind === 'noise') {
        $img = imagecreatetruecolor($wid, $hei);
        if ($kind === 'noise') {
            $dots = $wid * $hei * 4;
            for ($i = 0; $i < $dots; $i++) imagesetpixel($img, random_int(0, $wid - 1), random_int(0, $hei - 1), random_int(0, 16777215));
        }
        imagepng($img, $path, $kind === 'noise' ? 0 : 6);
    } elseif ($kind === 'cut') {
        $src = addTestFixture('whole.png', 'png', 10, 10);
        file_put_contents($path, substr((string)file_get_contents($src), 0, 33));
    } elseif ($kind === 'zip') {
        if (is_file($path)) unlink($path);
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('readme.txt', 'route check fixture');
        $zip->close();
    } else {
        file_put_contents($path, str_repeat('slaed ', 32));
    }
    return $path;
}

# Run one statement against the site database; the tool reads the same credentials the site reads and introduces no secret of its own
function getDbRows(string $sql, array $arg = []): array {
    static $pdo = null;
    $cfg = require ROOTDIR.'/config/db.php';
    if ($pdo === null) {
        $dsn = 'mysql:host='.$cfg['db']['host'].';dbname='.$cfg['db']['name'].';charset=utf8mb4';
        $pdo = new PDO($dsn, $cfg['db']['uname'], $cfg['db']['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $stmt = $pdo->prepare(str_replace('{p}', $cfg['db']['prefix'], $sql));
    $stmt->execute($arg);
    return str_starts_with(strtoupper(ltrim($sql)), 'SELECT') ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

# Install a trigger that fails one write of the named table on purpose, so the compensation branch of an adapter can be reached
function setFailTrigger(string $table, string $when): void {
    $cfg = require ROOTDIR.'/config/db.php';
    getDbRows('DROP TRIGGER IF EXISTS slaed_route_guard');
    $body = "BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'route check'; END";
    getDbRows('CREATE TRIGGER slaed_route_guard BEFORE '.$when.' ON '.$cfg['db']['prefix'].'_'.$table.' FOR EACH ROW '.$body);
}

# Record one matrix expectation and its outcome
function checkMatrixRow(string $name, bool $done, string $note = ''): void {
    if (!$done) $GLOBALS['fails']++;
    echo ($done ? '  ok   ' : '  FAIL ').$name.($note !== '' ? ' - '.$note : '')."\n";
}

# Record one row that could not be run at all, so a missing prerequisite never reads as a pass
function setSkipRow(string $name, string $why): void {
    $GLOBALS['skips']++;
    echo '  skip '.$name.' - '.$why."\n";
}

# Read every submittable field of a rendered settings form so an unchanged save can be replayed verbatim
function getFormFields(string $html): array {
    $out = [];
    if (preg_match_all('#<input\b[^>]*>#i', $html, $hit)) {
        foreach ($hit[0] as $tag) {
            if (!preg_match('#\bname="([^"]+)"#i', $tag, $nam)) continue;
            $kind = preg_match('#\btype="([^"]+)"#i', $tag, $typ) ? strtolower($typ[1]) : 'text';
            if (in_array($kind, ['file', 'submit', 'button'], true)) continue;
            if (($kind === 'radio' || $kind === 'checkbox') && !preg_match('#\bchecked\b#i', $tag)) continue;
            $val = preg_match('#\bvalue="([^"]*)"#i', $tag, $has) ? html_entity_decode($has[1], ENT_QUOTES) : '';
            $out[] = [html_entity_decode($nam[1], ENT_QUOTES), $val];
        }
    }
    if (preg_match_all('#<textarea\b[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>#is', $html, $hit, PREG_SET_ORDER)) {
        foreach ($hit as $one) $out[] = [html_entity_decode($one[1], ENT_QUOTES), html_entity_decode($one[2], ENT_QUOTES)];
    }
    if (preg_match_all('#<select\b[^>]*name="([^"]+)"[^>]*>(.*?)</select>#is', $html, $hit, PREG_SET_ORDER)) {
        foreach ($hit as $one) {
            $val = '';
            if (preg_match('#<option[^>]*\bvalue="([^"]*)"[^>]*\bselected\b#i', $one[2], $opt)) $val = html_entity_decode($opt[1], ENT_QUOTES);
            elseif (preg_match('#<option[^>]*\bvalue="([^"]*)"#i', $one[2], $opt)) $val = html_entity_decode($opt[1], ENT_QUOTES);
            $out[] = [html_entity_decode($one[1], ENT_QUOTES), $val];
        }
    }
    return $out;
}

# Turn extracted form pairs back into a body that keeps repeated array fields in their rendered order
function setFormBody(array $pair): string {
    $out = [];
    foreach ($pair as [$name, $val]) $out[] = rawurlencode($name).'='.rawurlencode($val);
    return implode('&', $out);
}

# The frontend file submission body, which several rows reuse with a different posttype or title
function getFilePost(string $jar, string $mark, string $type): array {
    return [
        'op' => 'send', 'posttype' => $type, 'token' => getScopeToken($jar, 'files'), 'title' => $mark, 'cid' => 1,
        'description' => 'route check', 'bodytext' => 'route check', 'postname' => 'route check',
        'mail' => 'route@example.net', 'home' => '', 'url' => '', 'fversion' => '1', 'fsize' => 0,
    ];
}

# The admin file submission body, which the write, update and relocation rows reuse
function getAdminPost(string $jar, string $mark, string $path): array {
    return [
        'op' => 'save', 'posttype' => 'save', 'token' => getScopeToken($jar, 'ajax'), 'fid' => 0, 'cid' => 1,
        'postname' => 'route check', 'title' => $mark, 'description' => 'route check', 'bodytext' => 'route check',
        'url' => '', 'path' => $path, 'filesize' => 0, 'version' => '1', 'email' => '', 'website' => '', 'ihome' => 0, 'acomm' => 0,
    ];
}

# The read rows of the matrix: listings, unknown operations and every refusal that must not touch the tree
function checkReadRows(string $ajar, string $ujar, string $gjar): void {
    echo "Editor read and refusal rows\n";
    $base = ROOTDIR.'/uploads/news';
    $png = addTestFixture('ed.png', 'png', 120, 90);
    $cut = addTestFixture('cut.png', 'cut');
    $txt = addTestFixture('ed.txt', 'txt');
    foreach (['moderator' => $ajar, 'user' => $ujar] as $who => $jar) {
        $url = '/index.php?go=4&op=editorFiles&mod=news&token='.getScopeToken($jar, 'ajax');
        $out = json_decode(getHttpReply($jar, $url)['body'], true);
        $good = is_array($out['files'] ?? null);
        checkMatrixRow('editor listing answers '.$who, $good, $good ? count($out['files']).' files' : 'no file list');
    }
    # A guest reaches the listing only where the record enables guest upload; where it does not, the access check answers first
    $url = '/index.php?go=4&op=editorFiles&mod=news&token='.getScopeToken($gjar, 'ajax');
    $out = json_decode(getHttpReply($gjar, $url)['body'], true);
    $open = is_array($out['files'] ?? null);
    $done = $open ? $out['files'] === [] : ($out['error'] ?? '') === 'Access denied';
    checkMatrixRow('a guest receives no historical file', $done, $open ? 'listed and empty' : 'refused by the record');
    $res = getHttpReply($ujar, '/index.php?go=4&op=nosuchop&mod=news&token='.getScopeToken($ujar, 'ajax'));
    checkMatrixRow('unknown editor operation answers 400', $res['code'] === 400 && str_contains($res['body'], '"ok":false'));
    $res = getHttpReply($ujar, '/index.php?go=4&op=nosuchop&mod=nosuchmodule&token='.getScopeToken($ujar, 'ajax'));
    checkMatrixRow('unknown operation with unknown module answers 400', $res['code'] === 400);
    $cases = ['tampered token' => ['bogus', $png], 'inadmissible type' => ['', $txt], 'undecodable image' => ['', $cut]];
    foreach ($cases as $name => [$tok, $file]) {
        $was = getDirTree($base);
        $send = ['token' => $tok !== '' ? $tok : getScopeToken($ujar, 'upload')];
        getHttpReply($ujar, '/index.php?go=4&op=editorUpload&mod=news', $send, ['file' => $file]);
        checkMatrixRow('editor write with '.$name.' publishes nothing', getTreeDelta($base, $was) === []);
    }
    $was = getDirTree(ROOTDIR.'/uploads/screens');
    $send = ['token' => getScopeToken($ujar, 'upload')];
    $res = getHttpReply($ujar, '/index.php?go=4&op=editorUpload&mod=screens', $send, ['file' => $png]);
    $done = getTreeDelta(ROOTDIR.'/uploads/screens', $was) === [] && str_contains($res['body'], 'configuration');
    checkMatrixRow('write to an unconfigured module is refused', $done);
}

# The avatar rows: the preset branch, a real upload and every path that must change nothing
function checkAvatarRows(string $ujar, string $gjar): void {
    echo "Account avatar rows\n";
    $base = ROOTDIR.'/uploads/avatars';
    $good = addTestFixture('av.png', 'png', 80, 60);
    $txt = addTestFixture('av.txt', 'txt');
    $rows = getDbRows('SELECT id, avatar FROM {p}_users WHERE name = :n', ['n' => (string)getenv('SLAED_USER_NAME')]);
    if ($rows === []) {
        setSkipRow('avatar rows', 'the site user has no row of its own');
        return;
    }
    $befo = (string)$rows[0]['avatar'];
    $page = getHttpReply($ujar, '/index.php?name=account&op=edithome')['body'];
    checkMatrixRow('the preset grid renders no saveavatar link', preg_match('#<a[^>]+op=saveavatar#', $page) === 0);
    $pre = preg_match('#name="avatar"\s+value="([^"]+)"#', $page, $hit) ? $hit[1] : '';
    getHttpReply($ujar, '/index.php?name=account&op=saveavatar&avatar='.rawurlencode($pre));
    checkMatrixRow('a GET preset changes nothing', getAvatarValue() === $befo);
    getHttpReply($ujar, '/index.php?name=account', ['op' => 'saveavatar', 'avatar' => $pre]);
    checkMatrixRow('a preset without a token changes nothing', getAvatarValue() === $befo);
    $send = ['op' => 'saveavatar', 'avatar' => $pre, 'token' => getScopeToken($gjar, 'account')];
    getHttpReply($gjar, '/index.php?name=account', $send);
    checkMatrixRow('a guest preset changes nothing', getAvatarValue() === $befo);
    $was = getDirTree($base);
    $send = ['op' => 'saveavatar', 'token' => getScopeToken($ujar, 'account')];
    getHttpReply($ujar, '/index.php?name=account', $send, ['userfile' => $txt]);
    checkMatrixRow('an inadmissible avatar publishes nothing', getTreeDelta($base, $was) === []);
    if ($pre !== '') {
        $send = ['op' => 'saveavatar', 'avatar' => $pre, 'token' => getScopeToken($ujar, 'account')];
        getHttpReply($ujar, '/index.php?name=account', $send);
        checkMatrixRow('a preset click stores the preset path', getAvatarValue() === 'presets/'.basename($pre));
    }
    $was = getDirTree($base);
    $send = ['op' => 'saveavatar', 'token' => getScopeToken($ujar, 'account')];
    getHttpReply($ujar, '/index.php?name=account', $send, ['userfile' => $good]);
    $new = getTreeDelta($base, $was);
    $done = count($new) === 1 && getAvatarValue() === basename($new[0] ?? '');
    checkMatrixRow('an avatar upload stores the filename only', $done, implode(', ', $new).' vs '.getAvatarValue());
    setFailTrigger('users', 'UPDATE');
    $was = getDirTree($base);
    $send = ['op' => 'saveavatar', 'token' => getScopeToken($ujar, 'account')];
    getHttpReply($ujar, '/index.php?name=account', $send, ['userfile' => $good]);
    $left = getTreeDelta($base, $was);
    getDbRows('DROP TRIGGER IF EXISTS slaed_route_guard');
    checkMatrixRow('a failed profile write leaves no avatar behind', $left === [], implode(', ', $left));
    getDbRows('UPDATE {p}_users SET avatar = :a WHERE id = :i', ['a' => $befo, 'i' => (int)$rows[0]['id']]);
    checkMatrixRow('the avatar value was restored', getAvatarValue() === $befo);
}

# Read the stored avatar of the configured site user
function getAvatarValue(): string {
    $rows = getDbRows('SELECT avatar FROM {p}_users WHERE name = :n', ['n' => (string)getenv('SLAED_USER_NAME')]);
    return (string)($rows[0]['avatar'] ?? '');
}

# The write rows of the matrix: one publication per flow, each verified in the tree and in the row it belongs to
function checkWriteRows(string $ajar, string $ujar): void {
    echo "Publication rows\n";
    $png = addTestFixture('ed.png', 'png', 120, 90);
    $zip = addTestFixture('fr.zip', 'zip');
    $txt = addTestFixture('ed.txt', 'txt');
    $mark = 'routecheck-'.bin2hex(random_bytes(4));

    $base = ROOTDIR.'/uploads/news';
    $was = getDirTree($base);
    $send = ['token' => getScopeToken($ujar, 'upload')];
    $out = json_decode(getHttpReply($ujar, '/index.php?go=4&op=editorUpload&mod=news', $send, ['file' => $png])['body'], true);
    $new = getTreeDelta($base, $was);
    checkMatrixRow('editor write publishes one owned file', ($out['ok'] ?? false) && count($new) === 1, implode(', ', $new));

    $base = ROOTDIR.'/uploads/screens';
    $send = ['op' => 'uploadsave', 'dir' => 'screens', 'token' => getScopeToken($ajar, 'uploads')];
    $was = getDirTree($base);
    getHttpReply($ajar, '/admin.php?name=uploads', $send, ['userfile' => $png]);
    $new = getTreeDelta($base, $was);
    checkMatrixRow('admin local write publishes into a directory with no record', count($new) === 1, implode(', ', $new));

    $was = getDirTree($base);
    $send['sitefile'] = SITEURL.'/nothing-here.png';
    getHttpReply($ajar, '/admin.php?name=uploads', $send, ['userfile' => $txt]);
    checkMatrixRow('a rejected local file never falls back to the link', getTreeDelta($base, $was) === []);

    foreach (['http://127.0.0.1/a.png', 'http://169.254.169.254/a.png', 'http://[::1]/a.png'] as $one) {
        $was = getDirTree($base);
        $send = ['op' => 'uploadsave', 'dir' => 'screens', 'token' => getScopeToken($ajar, 'uploads'), 'sitefile' => $one];
        getHttpReply($ajar, '/admin.php?name=uploads', $send);
        checkMatrixRow('remote target '.$one.' is refused', getTreeDelta($base, $was) === []);
    }
    $link = (string)getenv('SLAED_REMOTE_URL');
    if ($link === '') {
        setSkipRow('a public remote file publishes', 'SLAED_REMOTE_URL is not set');
    } else {
        $was = getDirTree($base);
        $send = ['op' => 'uploadsave', 'dir' => 'screens', 'token' => getScopeToken($ajar, 'uploads'), 'sitefile' => $link];
        getHttpReply($ajar, '/admin.php?name=uploads', $send);
        $new = getTreeDelta($base, $was);
        checkMatrixRow('a public remote file publishes', count($new) === 1, implode(', ', $new));
    }

    $base = ROOTDIR.'/uploads/files/temp';
    $was = getDirTree($base);
    getHttpReply($ujar, '/index.php?name=files', getFilePost($ujar, $mark, 'preview'), ['userfile' => $zip]);
    $none = getDbRows('SELECT id FROM {p}_files WHERE title = :t', ['t' => $mark]) === [];
    checkMatrixRow('a frontend preview publishes nothing', getTreeDelta($base, $was) === [] && $none);
    $was = getDirTree($base);
    getHttpReply($ujar, '/index.php?name=files', getFilePost($ujar, $mark, 'save'), ['userfile' => $zip]);
    $new = getTreeDelta($base, $was);
    $row = getDbRows('SELECT id, url, filesize FROM {p}_files WHERE title = :t', ['t' => $mark]);
    foreach ($row as $one) $GLOBALS['rows'][] = (int)$one['id'];
    $done = count($new) === 1 && count($row) === 1 && ($row[0]['url'] ?? '') === ($new[0] ?? '');
    checkMatrixRow('a frontend save stores the file and the project relative url', $done, implode(', ', $new));

    checkAdminFileRows($ajar, $zip, $mark);
    checkSettingsRows($ajar);
}

# The admin file rows: a first publication into the selected directory, an update of an existing row, and a relocation with no new upload
function checkAdminFileRows(string $ajar, string $zip, string $mark): void {
    $pub = ROOTDIR.'/uploads/files/public';
    $tmp = ROOTDIR.'/uploads/files/temp';
    $was = getDirTree($pub);
    $post = getAdminPost($ajar, $mark.'-a', 'uploads/files/public');
    getHttpReply($ajar, '/admin.php?name=files&op=save', ['posttype' => 'preview'] + $post, ['filesite' => $zip]);
    checkMatrixRow('an admin preview publishes nothing', getTreeDelta($pub, $was) === []);
    $was = getDirTree($pub);
    getHttpReply($ajar, '/admin.php?name=files&op=save', $post, ['filesite' => $zip]);
    $new = getTreeDelta($pub, $was);
    $row = getDbRows('SELECT id, url FROM {p}_files WHERE title = :t', ['t' => $mark.'-a']);
    foreach ($row as $one) $GLOBALS['rows'][] = (int)$one['id'];
    checkMatrixRow('an admin save publishes into the selected directory in one step', count($new) === 1 && count($row) === 1);
    $fid = (int)($row[0]['id'] ?? 0);
    if ($fid === 0) {
        setSkipRow('admin update and relocation rows', 'the first admin save produced no row');
        return;
    }
    $was = getDirTree($pub);
    $edit = getAdminPost($ajar, $mark.'-a', 'uploads/files/public');
    $edit['fid'] = $fid;
    $edit['url'] = (string)$row[0]['url'];
    $edit['description'] = 'route check updated';
    getHttpReply($ajar, '/admin.php?name=files&op=save', $edit);
    $after = getDbRows('SELECT intro, url FROM {p}_files WHERE id = :i', ['i' => $fid]);
    $done = ($after[0]['intro'] ?? '') === 'route check updated' && ($after[0]['url'] ?? '') === $edit['url'];
    checkMatrixRow('an update of an existing row keeps its stored file', $done && getTreeDelta($pub, $was) === []);
    $was = getDirTree($tmp);
    $move = $edit;
    $move['path'] = 'uploads/files/temp';
    getHttpReply($ajar, '/admin.php?name=files&op=save', $move);
    $new = getTreeDelta($tmp, $was);
    $after = getDbRows('SELECT url FROM {p}_files WHERE id = :i', ['i' => $fid]);
    $gone = !is_file(ROOTDIR.'/'.$edit['url']);
    checkMatrixRow('a relocation without an upload moves the stored file', count($new) === 1 && $gone, implode(', ', $new));
    checkMatrixRow('the relocated row points at the new path', ($after[0]['url'] ?? '') === ($new[0] ?? ''));
    $was = getDirTree($tmp);
    getHttpReply($ajar, '/admin.php?name=files&op=save', ['posttype' => 'delete', 'fid' => $fid] + $edit, ['filesite' => $zip]);
    checkMatrixRow('a delete with a file attached publishes nothing', getTreeDelta($tmp, $was) === []);
}

# The two settings rows plus the documentation tab
function checkSettingsRows(string $ajar): void {
    echo "Settings rows\n";
    $tok = getScopeToken($ajar, 'uploads');
    $befo = (string)file_get_contents(ROOTDIR.'/config/uploads.php');
    $pair = getFormFields(getHttpReply($ajar, '/admin.php?name=uploads&op=config')['body']);
    getHttpReply($ajar, '/admin.php?name=uploads', setFormBody(array_merge([['op', 'configsave'], ['token', $tok]], $pair)));
    clearstatcache();
    checkMatrixRow('an unchanged preferences save round trips byte for byte', (string)file_get_contents(ROOTDIR.'/config/uploads.php') === $befo);
    $befo = (string)file_get_contents(ROOTDIR.'/config/filetype.php');
    $pair = getFormFields(getHttpReply($ajar, '/admin.php?name=uploads&op=tplconfig')['body']);
    getHttpReply($ajar, '/admin.php?name=uploads', setFormBody(array_merge([['op', 'tplsave'], ['token', $tok]], $pair)));
    clearstatcache();
    checkMatrixRow('an unchanged templates save round trips byte for byte', (string)file_get_contents(ROOTDIR.'/config/filetype.php') === $befo);
    $over = 0;
    foreach (file(ROOTDIR.'/config/filetype.php') ?: [] as $line) {
        if (strlen(rtrim($line, "\r\n")) > 180) $over++;
    }
    checkMatrixRow('the generated template file keeps every line inside the limit', $over === 0, $over.' line(s) over 180');
    checkMatrixRow('the docs tab renders without a warning', !preg_match('#(Warning|Fatal error)#', getHttpReply($ajar, '/admin.php?name=uploads&op=info')['body']));
}

# The compensation rows: a write that fails on purpose must leave neither a file nor a row behind
function checkCompRows(string $ajar, string $ujar): void {
    echo "Compensation rows\n";
    $zip = addTestFixture('fr.zip', 'zip');
    $base = ROOTDIR.'/uploads/files/temp';
    $mark = 'routecomp-'.bin2hex(random_bytes(4));
    setFailTrigger('files', 'INSERT');
    $was = getDirTree($base);
    getHttpReply($ujar, '/index.php?name=files', getFilePost($ujar, $mark, 'save'), ['userfile' => $zip]);
    $new = getTreeDelta($base, $was);
    checkMatrixRow('a failed frontend row write leaves no published file', $new === [], implode(', ', $new));
    checkMatrixRow('a failed frontend row write leaves no row', getDbRows('SELECT id FROM {p}_files WHERE title = :t', ['t' => $mark]) === []);
    $pub = ROOTDIR.'/uploads/files/public';
    $was = getDirTree($pub);
    getHttpReply($ajar, '/admin.php?name=files&op=save', getAdminPost($ajar, $mark.'-a', 'uploads/files/public'), ['filesite' => $zip]);
    $new = getTreeDelta($pub, $was);
    checkMatrixRow('a failed admin row write leaves no published file', $new === [], implode(', ', $new));
    getDbRows('DROP TRIGGER IF EXISTS slaed_route_guard');
    $left = getDbRows("SELECT trigger_name FROM information_schema.triggers WHERE trigger_name = 'slaed_route_guard'");
    checkMatrixRow('the guard trigger is gone', $left === []);
}

# The captcha row: it only exists for a guest, because Captcha::isActive() exempts an authenticated visitor
function checkCaptchaRow(string $gjar): void {
    echo "Captcha row\n";
    $file = ROOTDIR.'/config/security.php';
    $befo = (string)file_get_contents($file);
    $open = str_replace("'captcha' => [\n            'active' => '0',", "'captcha' => [\n            'active' => '1',", $befo);
    if ($open === $befo) {
        setSkipRow('a failed captcha publishes nothing', 'captcha is not switched off in config/security.php');
        return;
    }
    file_put_contents($file, $open);
    if (is_file(ROOTDIR.'/config/local.php')) unlink(ROOTDIR.'/config/local.php');
    $zip = addTestFixture('fr.zip', 'zip');
    $base = ROOTDIR.'/uploads/files/temp';
    $mark = 'routecheck-'.bin2hex(random_bytes(4));
    $was = getDirTree($base);
    getHttpReply($gjar, '/index.php?name=files', getFilePost($gjar, $mark, 'save'), ['userfile' => $zip]);
    $new = getTreeDelta($base, $was);
    $none = getDbRows('SELECT id FROM {p}_files WHERE title = :t', ['t' => $mark]) === [];
    checkMatrixRow('a failed captcha publishes nothing', $new === [] && $none, implode(', ', $new));
    file_put_contents($file, $befo);
    if (is_file(ROOTDIR.'/config/local.php')) unlink(ROOTDIR.'/config/local.php');
    clearstatcache();
    checkMatrixRow('config/security.php was restored byte for byte', (string)file_get_contents($file) === $befo);
    getHttpReply($gjar, '/index.php?name=account');
}

# Remove every file and row the walk created, and report anything that survived
function deleteWalkTraces(): void {
    echo "Cleanup\n";
    $left = [];
    foreach (array_unique($GLOBALS['made']) as $one) {
        $path = ROOTDIR.'/'.$one;
        if (is_file($path) && !unlink($path)) $left[] = $one;
    }
    foreach (array_unique($GLOBALS['rows']) as $id) getDbRows('DELETE FROM {p}_files WHERE id = :i', ['i' => $id]);
    checkMatrixRow('every published file was removed', $left === [], implode(', ', $left));
    $rest = getDbRows("SELECT id FROM {p}_files WHERE title LIKE 'routecheck-%' OR title LIKE 'routecomp-%'");
    checkMatrixRow('every seeded row was removed', $rest === []);
}

$mode = (string)($argn[2] ?? 'all');
$aname = (string)getenv('SLAED_ADMIN_NAME');
$apass = (string)getenv('SLAED_ADMIN_PASS');
$uname = (string)getenv('SLAED_USER_NAME');
$upass = (string)getenv('SLAED_USER_PASS');
if ($aname === '' || $apass === '' || $uname === '' || $upass === '') {
    fwrite(STDERR, "set SLAED_ADMIN_NAME, SLAED_ADMIN_PASS, SLAED_USER_NAME and SLAED_USER_PASS in the environment\n");
    exit(1);
}
$ajar = WORKDIR.'/jar-admin.txt';
$ujar = WORKDIR.'/jar-user.txt';
$gjar = WORKDIR.'/jar-guest.txt';
if (is_file($gjar)) unlink($gjar);
echo 'Sessions'.(NOVERIFY ? ' (TLS verification disabled by SLAED_ROUTE_INSECURE)' : '')."\n";
checkMatrixRow('administrator session', setAdminSession($ajar, $aname, $apass));
checkMatrixRow('site user session', setUserSession($ujar, $uname, $upass));
# The guest jar is warmed on a module route: a cached home page is answered before the session starts, so no cookie is set
getHttpReply($gjar, '/index.php?name=account');
checkMatrixRow('guest session', getSessionId($gjar) !== '');
if ($GLOBALS['fails'] > 0) {
    fwrite(STDERR, "cannot open the sessions the walk needs\n");
    exit(1);
}
if ($mode === 'read' || $mode === 'all') checkReadRows($ajar, $ujar, $gjar);
if ($mode === 'write' || $mode === 'all') checkWriteRows($ajar, $ujar);
if ($mode === 'write' || $mode === 'all') checkAvatarRows($ujar, $gjar);
if ($mode === 'comp' || $mode === 'all') checkCompRows($ajar, $ujar);
if ($mode === 'captcha' || $mode === 'all') checkCaptchaRow($gjar);
deleteWalkTraces();
echo ($GLOBALS['fails'] === 0 ? 'all rows passed' : $GLOBALS['fails'].' row(s) failed');
echo ($GLOBALS['skips'] > 0 ? ', '.$GLOBALS['skips']." not run\n" : "\n");
exit($GLOBALS['fails'] === 0 ? 0 : 1);
