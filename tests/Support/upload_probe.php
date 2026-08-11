<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the Upload class of batches 3 and 4 in docs/UPLOAD-2026.md
# boots the real core like index.php, one scenario per process, and drives the class against a disposable upload root it fills itself
# Nothing under the site upload tree is read or written - the root, the lock directory, the source files and the logs of every run live below the scratch root the caller passed
$probework = (string)($argv[2] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/upload.php';

# The disposable upload root and the directory the source files of one run are built in; the lock directory belongs to FileManager and follows the redirected LOGS_DIR
$GLOBALS['pwork'] = $probework;
$GLOBALS['proot'] = $probework.'/root';
$GLOBALS['ptmp'] = $probework.'/src';

# The test-only subclass named in the plan: it replaces the clock, the two upload primitives no unit test can forge and the three seams a failure branch needs
class ProbeUpload extends Upload {
    public int $tick = 0;
    public array $salts = [];
    public bool $ispub = true;
    public bool $isdel = true;
    public array $dns = [];
    public array $reply = [];
    public array $hits = [];
    public string $peer = '';

    # Drive the one-hour partial sweep from a controlled clock
    protected function getTime(): int {
        return $this->tick > 0 ? $this->tick : time();
    }

    # A scratch file stands in for the request-scoped upload the SAPI would have received
    protected function checkSourceFile(string $path): bool {
        return is_file($path);
    }

    # move_uploaded_file() refuses anything the SAPI did not receive, so the transfer is a move with the same consuming semantics
    protected function addSourceFile(string $from, string $dest): bool {
        return rename($from, $dest);
    }

    # Draw the queued names in order and repeat the last one, which is what makes a collision retry and a retry exhaustion reproducible
    protected function getNameSalt(): string {
        if ($this->salts === []) return parent::getNameSalt();
        return count($this->salts) > 1 ? (string)array_shift($this->salts) : (string)$this->salts[0];
    }

    # Refuse the publishing rename on demand, which no real filesystem does reliably enough to test against
    protected function addPublishFile(string $from, string $dest): bool {
        return $this->ispub && parent::addPublishFile($from, $dest);
    }

    # Refuse to delete on demand, which is the stranded-partial case the plan requires proof of
    protected function deleteFilePath(string $path): bool {
        return $this->isdel && parent::deleteFilePath($path);
    }

    # Answer the address policy from a scripted zone, so every rejected range is reachable without asking a real resolver for an address it would never return
    protected function getHostRecords(string $host): array {
        return $this->dns[strtolower(rtrim($host, '.'))] ?? [];
    }

    # Serve one scripted reply through the real header and body callbacks of the class and record what the request was configured with; no unit test of this probe opens a socket
    protected function getRemoteReply(array $opts): array {
        $one = $this->reply[count($this->hits)] ?? ['code' => 599];
        $bind = (string)(($opts[CURLOPT_RESOLVE] ?? [])[0] ?? '');
        $host = trim((string)parse_url((string)($opts[CURLOPT_URL] ?? ''), PHP_URL_HOST), '[]');
        $pin = ($bind === '') ? $host : (string)(explode(':', $bind, 3)[2] ?? '');
        $this->hits[] = [
            'url' => (string)($opts[CURLOPT_URL] ?? ''),
            'bind' => $bind,
            'pin' => $pin,
            'proxy' => $opts[CURLOPT_PROXY] ?? 'unset',
            'protos' => $opts[CURLOPT_PROTOCOLS_STR] ?? 'unset',
            'follow' => $opts[CURLOPT_FOLLOWLOCATION] ?? 'unset',
            'redirs' => $opts[CURLOPT_MAXREDIRS] ?? 'unset',
            'verify' => [$opts[CURLOPT_SSL_VERIFYPEER] ?? 'unset', $opts[CURLOPT_SSL_VERIFYHOST] ?? 'unset'],
            'wait' => [$opts[CURLOPT_CONNECTTIMEOUT] ?? 'unset', $opts[CURLOPT_TIMEOUT] ?? 'unset'],
        ];
        $peer = ($this->peer !== '') ? $this->peer : $pin;
        if (!empty($one['fail'])) return ['ok' => false, 'peer' => ''];
        $body = (string)($one['body'] ?? '');
        $rows = ['HTTP/1.1 '.(int)($one['code'] ?? 200).' probe'];
        if (isset($one['next'])) $rows[] = 'Location: '.$one['next'];
        if (empty($one['nolen']) && $body !== '') $rows[] = 'Content-Length: '.(int)($one['claim'] ?? strlen($body));
        $rows[] = '';
        foreach ($rows as $line) {
            if ($opts[CURLOPT_HEADERFUNCTION]($opts, $line."\r\n") !== strlen($line) + 2) return ['ok' => false, 'peer' => $peer];
        }
        foreach (str_split($body, 16) as $chunk) {
            if ($opts[CURLOPT_WRITEFUNCTION]($opts, $chunk) !== strlen($chunk)) return ['ok' => false, 'peer' => $peer];
        }
        return ['ok' => true, 'peer' => $peer];
    }
}

# Start every scenario from an empty scratch tree with one prepared destination directory below the disposable upload root
function addProbeRoot(): void {
    foreach ([$GLOBALS['proot'], $GLOBALS['ptmp']] as $dir) {
        if (is_dir($dir)) deleteDir($dir);
        mkdir($dir, 0777, true);
    }
    mkdir($GLOBALS['proot'].'/files', 0777, true);
}

# Build one service over the disposable root
function getProbeUpload(): ProbeUpload {
    return new ProbeUpload($GLOBALS['proot']);
}

# The default rule of a scenario: everything the type policy knows is allowed and no limit is set, so a scenario only states the limit it is about
function getProbeRule(array $over = []): array {
    return $over + [
        'extensions' => implode(',', Upload::getSupportedTypes()),
        'maxquota' => 0,
        'maxbytes' => 0,
        'maxwidth' => 0,
        'maxheight' => 0,
        'maxfiles' => 0,
    ];
}

# Report which image formats the local GD build can encode, so an image scenario is skipped rather than failed on a build without them
function getProbeCaps(): array {
    return [
        'gif' => function_exists('imagegif'),
        'jpg' => function_exists('imagejpeg'),
        'jpeg' => function_exists('imagejpeg'),
        'png' => function_exists('imagepng'),
        'webp' => function_exists('imagewebp'),
        'avif' => function_exists('imageavif'),
        'bmp' => function_exists('imagebmp'),
    ];
}

# Write one image fixture of the requested format and pixel size, or return an empty string when this GD build cannot encode it
function addProbeImage(string $path, string $ext, int $wid, int $hei): string {
    $img = imagecreatetruecolor($wid, $hei);
    imagefilledrectangle($img, 0, 0, $wid - 1, $hei - 1, imagecolorallocate($img, 20, 120, 200));
    $done = match ($ext) {
        'gif' => function_exists('imagegif') && imagegif($img, $path),
        'jpg', 'jpeg' => function_exists('imagejpeg') && imagejpeg($img, $path),
        'png' => function_exists('imagepng') && imagepng($img, $path),
        'webp' => function_exists('imagewebp') && imagewebp($img, $path),
        'avif' => function_exists('imageavif') && imageavif($img, $path),
        'bmp' => function_exists('imagebmp') && imagebmp($img, $path),
        default => false,
    };
    return $done && is_file($path) ? $path : '';
}

# Return the smallest body that carries the container magic of one non-image format, so libmagic reports the type a real file of that format would
function getProbeBody(string $ext): string {
    return match ($ext) {
        'pdf' => "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n",
        'gz' => (string)gzencode('slaed upload probe'),
        'rar' => "Rar!\x1a\x07\x00".str_repeat("\x00", 32),
        '7z' => "7z\xbc\xaf\x27\x1c\x00\x04".str_repeat("\x00", 24),
        'mp3' => "ID3\x03\x00\x00\x00\x00\x00\x21".str_repeat("\x00", 33)."\xff\xfb\x90\x00".str_repeat("\x00", 64),
        'wav' => getProbeWave(),
        'flac' => "fLaC\x00\x00\x00\x22".str_repeat("\x00", 34),
        'ogg', 'oga', 'opus' => "OggS\x00\x02".str_repeat("\x00", 20)."\x01\x1e\x01vorbis".str_repeat("\x00", 24),
        'm4a' => "\x00\x00\x00\x20ftypM4A \x00\x00\x00\x00M4A mp42isom".str_repeat("\x00", 16),
        'mp4' => "\x00\x00\x00\x20ftypisom\x00\x00\x02\x00isomiso2avc1mp41".str_repeat("\x00", 16),
        'webm' => "\x1a\x45\xdf\xa3\x01\x00\x00\x00\x00\x00\x00\x1f\x42\x86\x81\x01\x42\xf7\x81\x01\x42\xf2\x81\x04\x42\xf3\x81\x08\x42\x82\x84webm",
        default => '',
    };
}

# A complete 8-bit mono RIFF/WAVE file with one sample block, which is what libmagic recognizes as wave audio
function getProbeWave(): string {
    $data = str_repeat("\x80", 64);
    $fmt = pack('vvVVvv', 1, 1, 8000, 8000, 1, 8);
    $body = 'WAVE'.'fmt '.pack('V', strlen($fmt)).$fmt.'data'.pack('V', strlen($data)).$data;
    return 'RIFF'.pack('V', strlen($body)).$body;
}

# A real archive of the requested container, built by the extension that owns the format instead of by hand
function addProbeArchive(string $path, string $ext): string {
    if ($ext === 'zip') {
        if (!class_exists('ZipArchive')) return '';
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return '';
        $zip->addFromString('probe.txt', 'slaed upload probe');
        $zip->close();
        return is_file($path) ? $path : '';
    }
    if (!class_exists('PharData')) return '';
    try {
        $tar = new PharData($path);
        $tar->addFromString('probe.txt', 'slaed upload probe');
    } catch (Throwable) {
        return '';
    }
    return is_file($path) ? $path : '';
}

# Build one fixture of the requested extension and return it in the shape one $_FILES entry has, or an empty array when this build cannot produce that format
function addProbeFile(string $ext, string $name = '', int $wid = 40, int $hei = 30): array {
    $file = ($name !== '') ? $name : 'src-'.bin2hex(random_bytes(4)).'.'.$ext;
    $path = $GLOBALS['ptmp'].'/'.$file;
    $type = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($type, ['gif', 'jpg', 'jpeg', 'png', 'webp', 'avif', 'bmp'], true)) {
        if (addProbeImage($path, $type, $wid, $hei) === '') return [];
    } elseif ($type === 'zip' || $type === 'tar') {
        if (addProbeArchive($path, $type) === '') return [];
    } else {
        $body = getProbeBody($type);
        if ($body === '') return [];
        file_put_contents($path, $body);
    }
    return ['name' => $file, 'tmp_name' => $path, 'size' => (int)filesize($path), 'error' => UPLOAD_ERR_OK];
}

# A real PNG whose IHDR is complete and whose pixel data is gone: the cut sits at byte 33, the first byte after the header chunk, so it holds for any image size
# libmagic still reports image/png and getimagesize() still answers the full size, so only a real decode refuses it; a shorter cut is caught by the header read and proves nothing
function addProbeBroken(): array {
    $file = addProbeFile('png');
    if ($file === []) return [];
    return addProbeRaw('broken.png', substr((string)file_get_contents($file['tmp_name']), 0, 33));
}

# Reports whether the broken fixture still passes a header-only read, which is the property that makes it a regression guard rather than a duplicate of the missing-file case
function getProbeBrokenHeader(): array {
    $file = addProbeBroken();
    if ($file === []) return ['header' => false, 'decode' => false];
    set_error_handler(static fn(): bool => true);
    try {
        $info = getimagesize($file['tmp_name']);
        $img = imagecreatefrompng($file['tmp_name']);
    } finally {
        restore_error_handler();
    }
    return ['header' => is_array($info) && (int)($info[0] ?? 0) > 0, 'decode' => $img !== false];
}

# Write one source file with the given raw body, for the cases where the content must not match the name
function addProbeRaw(string $file, string $body): array {
    $path = $GLOBALS['ptmp'].'/'.$file;
    file_put_contents($path, $body);
    return ['name' => $file, 'tmp_name' => $path, 'size' => strlen($body), 'error' => UPLOAD_ERR_OK];
}

# List the published files of one destination, partials excluded
function getProbeList(string $dir): array {
    $out = [];
    foreach (scandir($GLOBALS['proot'].'/'.$dir) ?: [] as $file) {
        if ($file === '.' || $file === '..' || str_starts_with($file, '.upload-')) continue;
        $out[] = $file;
    }
    sort($out);
    return $out;
}

# List the partials left in one destination, which must be empty after every finished operation
function getProbeParts(string $dir): array {
    $out = [];
    foreach (scandir($GLOBALS['proot'].'/'.$dir) ?: [] as $file) {
        if (str_starts_with($file, '.upload-') && str_ends_with($file, '.part')) $out[] = $file;
    }
    sort($out);
    return $out;
}

# Read back what the file log recorded during a scenario, which is how a stranded partial is proven visible
function getProbeLog(): array {
    $path = LOGS_DIR.'/error_file.log';
    if (!is_file($path)) return [];
    $out = [];
    foreach (explode("\n", (string)file_get_contents($path)) as $line) {
        $row = json_decode(trim($line), true);
        if (!is_array($row)) continue;
        $out[] = [
            'msg' => (string)($row['msg'] ?? ''),
            'path' => (string)($row['path'] ?? ''),
            'host' => (string)($row['host'] ?? ''),
            'address' => (string)($row['address'] ?? ''),
        ];
    }
    return $out;
}

# Publish one file with the default rule and report the result together with what the destination holds afterwards
function addProbeRun(Upload $upl, array $file, array $rule, string $dir = 'files', string $base = 'files', ?string $owner = null): array {
    $res = $upl->addUploadedFile($file, $rule, $dir, $base, $owner);
    $res['left'] = getProbeList('files');
    $res['parts'] = getProbeParts('files');
    return $res;
}

# One fixture per mapped extension: what the local libmagic build detects has to be a member of the mapped set, or a real upload of that format would be refused
function getProbeTypes(): array {
    $map = (array)(new ReflectionClass(Upload::class))->getConstant('TYPES');
    $out = ['list' => Upload::getSupportedTypes(), 'map' => $map, 'caps' => getProbeCaps(), 'runs' => []];
    foreach (array_keys($map) as $ext) {
        addProbeRoot();
        $file = addProbeFile($ext);
        if ($file === []) {
            $out['runs'][$ext] = ['made' => false, 'mime' => '', 'ok' => false, 'stored' => false];
            continue;
        }
        $res = addProbeRun(getProbeUpload(), $file, getProbeRule());
        $out['runs'][$ext] = [
            'made' => true,
            'mime' => (string)(new finfo(FILEINFO_MIME_TYPE))->file($GLOBALS['proot'].'/files/'.(string)$res['file']),
            'ok' => (bool)$res['ok'],
            'error' => $res['error'],
            'stored' => count($res['left']) === 1,
        ];
    }
    return $out;
}

# Every local result code, each produced by the smallest input that reaches it
function getProbeCodes(): array {
    $out = [];
    addProbeRoot();
    $upl = getProbeUpload();
    $out['missing'] = addProbeRun($upl, [], getProbeRule());
    $out['empty'] = addProbeRun($upl, ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE], getProbeRule());
    $file = addProbeFile('png');
    $out['nofile'] = addProbeRun($upl, ['name' => 'a.png', 'tmp_name' => $file['tmp_name'], 'size' => 10, 'error' => UPLOAD_ERR_PARTIAL], getProbeRule());
    $out['inisize'] = addProbeRun($upl, ['name' => 'a.png', 'tmp_name' => $file['tmp_name'], 'size' => 10, 'error' => UPLOAD_ERR_INI_SIZE], getProbeRule());
    $out['notupload'] = addProbeRun(getProbeReal(), $file, getProbeRule());
    $out['size'] = addProbeRun($upl, addProbeFile('png'), getProbeRule(['maxbytes' => 20]));
    $out['extension'] = addProbeRun($upl, addProbeFile('png'), getProbeRule(['extensions' => 'gif,jpg']));
    $out['blocked'] = addProbeRun($upl, addProbeRaw('evil.php', '<?php echo 1;'), getProbeRule(['extensions' => 'php,png']));
    $out['unsupported'] = addProbeRun($upl, addProbeFile('bmp'), getProbeRule(['extensions' => 'bmp,png']));
    $out['mime'] = addProbeRun($upl, addProbeRaw('claim.png', (string)file_get_contents(addProbeFile('gz')['tmp_name'])), getProbeRule());
    $out['image'] = addProbeRun($upl, addProbeBroken(), getProbeRule());
    $out['brokenhdr'] = getProbeBrokenHeader();
    $out['dimensions'] = addProbeRun($upl, addProbeFile('png', '', 200, 100), getProbeRule(['maxwidth' => 100, 'maxheight' => 100]));
    $out['quota'] = getProbeQuotaRun();
    $out['destination'] = addProbeRun($upl, addProbeFile('png'), getProbeRule(), 'nosuchdir');
    $out['exists'] = getProbeExistsRun();
    $out['write'] = getProbeWriteRun();
    $out['done'] = addProbeRun($upl, addProbeFile('png'), getProbeRule(), 'files', 'files', 7);
    return $out;
}

# The unmodified class, which is what proves that a source the SAPI never received is refused
function getProbeReal(): Upload {
    return new Upload($GLOBALS['proot']);
}

# One quota rejection: the stored file plus the incoming one exceed the limit
function getProbeQuotaRun(): array {
    file_put_contents($GLOBALS['proot'].'/files/stored.png', str_repeat('x', 400));
    $res = addProbeRun(getProbeUpload(), addProbeFile('png'), getProbeRule(['maxquota' => 500]));
    unlink($GLOBALS['proot'].'/files/stored.png');
    return $res;
}

# Retry exhaustion: the queue repeats one name, so every one of the five attempts finds the destination taken
function getProbeExistsRun(): array {
    $upl = getProbeUpload();
    $upl->salts = ['aaaaaaaaaa'];
    file_put_contents($GLOBALS['proot'].'/files/files-aaaaaaaaaa.png', 'taken');
    $res = addProbeRun($upl, addProbeFile('png'), getProbeRule());
    unlink($GLOBALS['proot'].'/files/files-aaaaaaaaaa.png');
    return $res;
}

# A publishing rename that fails: nothing is published, the partial goes and the caller is told the write failed
function getProbeWriteRun(): array {
    $upl = getProbeUpload();
    $upl->ispub = false;
    return addProbeRun($upl, addProbeFile('png'), getProbeRule());
}

# The stored name grammar: the owner suffix, the sanitized base and the fixed random segment
# The owner is a token rather than a number: a digit string is what a member and a file stored before the widening carry, and a session token is what a guest carries
function getProbeNaming(): array {
    $out = [];
    foreach (['none' => null, 'guest' => '0', 'user' => '42', 'token' => 'a1b2c3d4e5f6a7b8', 'dirty' => 'a b/c-9', 'empty' => ''] as $key => $own) {
        addProbeRoot();
        $out[$key] = addProbeRun(getProbeUpload(), addProbeFile('png'), getProbeRule(), 'files', 'files', $own);
    }
    addProbeRoot();
    $out['clean'] = addProbeRun(getProbeUpload(), addProbeFile('png'), getProbeRule(), 'files', 'my files/2!', 0);
    addProbeRoot();
    $out['nobase'] = addProbeRun(getProbeUpload(), addProbeFile('png'), getProbeRule(), 'files', '!!!', 0);
    addProbeRoot();
    $upl = getProbeUpload();
    $upl->salts = ['aaaaaaaaaa', 'bbbbbbbbbb'];
    file_put_contents($GLOBALS['proot'].'/files/files-aaaaaaaaaa-0.png', 'taken');
    $out['retry'] = addProbeRun($upl, addProbeFile('png'), getProbeRule(), 'files', 'files', 0);
    return $out;
}

# Publication: a successful run, a failed rename, and a failed rename whose partial cannot be deleted either
function getProbePublish(): array {
    addProbeRoot();
    $out = ['done' => addProbeRun(getProbeUpload(), addProbeFile('png', '', 64, 48), getProbeRule())];
    $out['done']['bytes'] = is_file($GLOBALS['proot'].'/files/'.(string)$out['done']['file']) ? (int)filesize($GLOBALS['proot'].'/files/'.(string)$out['done']['file']) : 0;
    addProbeRoot();
    $upl = getProbeUpload();
    $upl->ispub = false;
    $out['norename'] = addProbeRun($upl, addProbeFile('png'), getProbeRule());
    addProbeRoot();
    $upl = getProbeUpload();
    $upl->ispub = false;
    $upl->isdel = false;
    $out['stranded'] = addProbeRun($upl, addProbeFile('png'), getProbeRule());
    $out['log'] = getProbeLog();
    return $out;
}

# The one-hour sweep of abandoned partials, driven from the controlled clock
function getProbeSweep(): array {
    addProbeRoot();
    $old = $GLOBALS['proot'].'/files/.upload-'.str_repeat('a', 16).'.part';
    $new = $GLOBALS['proot'].'/files/.upload-'.str_repeat('b', 16).'.part';
    file_put_contents($old, 'abandoned');
    file_put_contents($new, 'fresh');
    touch($old, time() - 7200);
    $out = ['fresh' => addProbeRun(getProbeUpload(), addProbeFile('png'), getProbeRule())];
    $out['fresh']['kept'] = [is_file($old), is_file($new)];
    $upl = getProbeUpload();
    $upl->tick = time() + 3600;
    $out['aged'] = addProbeRun($upl, addProbeFile('png'), getProbeRule());
    $out['aged']['kept'] = [is_file($old), is_file($new)];
    return $out;
}

# Quota: the boundary itself, one byte over it, and the entries that never count
function getProbeQuota(): array {
    $out = [];
    addProbeRoot();
    $file = addProbeFile('png');
    $size = (int)$file['size'];
    file_put_contents($GLOBALS['proot'].'/files/stored.bin', str_repeat('x', 100));
    $out['edge'] = addProbeRun(getProbeUpload(), $file, getProbeRule(['maxquota' => $size + 100]));
    addProbeRoot();
    $file = addProbeFile('png');
    file_put_contents($GLOBALS['proot'].'/files/stored.bin', str_repeat('x', 100));
    $out['over'] = addProbeRun(getProbeUpload(), $file, getProbeRule(['maxquota' => $size + 99]));
    addProbeRoot();
    $file = addProbeFile('png');
    file_put_contents($GLOBALS['proot'].'/files/index.html', str_repeat('x', 4000));
    file_put_contents($GLOBALS['proot'].'/files/.htaccess', str_repeat('x', 4000));
    file_put_contents($GLOBALS['proot'].'/files/.upload-'.str_repeat('c', 16).'.part', str_repeat('x', 4000));
    $out['skipped'] = addProbeRun(getProbeUpload(), $file, getProbeRule(['maxquota' => $size + 10]));
    return $out;
}

# Destination policy: only a plain relative directory that resolves below the upload root is a target
function getProbePaths(): array {
    addProbeRoot();
    $out = ['runs' => []];
    $list = [
        'plain' => 'files',
        'trail' => 'files/',
        'back' => 'files\\',
        'empty' => '',
        'dot' => '.',
        'up' => '../outside',
        'inner' => 'files/../../outside',
        'abs' => '/etc',
        'drive' => 'C:/Windows',
        'nul' => "files\x00/x",
        'gone' => 'nosuchdir',
    ];
    foreach ($list as $key => $dir) {
        $file = addProbeFile('png');
        $res = getProbeUpload()->addUploadedFile($file, getProbeRule(), $dir, 'files');
        $out['runs'][$key] = ['ok' => (bool)$res['ok'], 'error' => $res['error'], 'path' => $res['path']];
    }
    $out['escape'] = getProbeEscape();
    $out['outside'] = is_dir(dirname($GLOBALS['proot']).'/outside') ? scandir(dirname($GLOBALS['proot']).'/outside') : [];
    return $out;
}

# A symlink that leaves the upload root must be refused even though its own name is harmless
function getProbeEscape(): array {
    $away = dirname($GLOBALS['proot']).'/away';
    if (!is_dir($away)) mkdir($away, 0777, true);
    $link = $GLOBALS['proot'].'/escape';
    if (is_dir($link)) return ['made' => false];
    set_error_handler(static fn(): bool => true);
    $made = symlink($away, $link);
    restore_error_handler();
    if (!$made) return ['made' => false];
    $res = getProbeUpload()->addUploadedFile(addProbeFile('png'), getProbeRule(), 'escape', 'files');
    return ['made' => true, 'ok' => (bool)$res['ok'], 'error' => $res['error'], 'wrote' => count(scandir($away) ?: []) > 2];
}

# Compensation: only a canonical file this class published below the root may be deleted through it
function getProbeDelete(): array {
    addProbeRoot();
    $upl = getProbeUpload();
    $res = addProbeRun($upl, addProbeFile('png'), getProbeRule(), 'files', 'files', 5);
    $out = ['published' => (string)$res['path']];
    $out['legacy'] = ['made' => (bool)file_put_contents($GLOBALS['proot'].'/files/legacy.png', 'old'), 'done' => $upl->deleteStoredFile('files/legacy.png')];
    $out['away'] = $upl->deleteStoredFile('../away/files-aaaaaaaaaa-5.png');
    $out['absolute'] = $upl->deleteStoredFile($GLOBALS['proot'].'/files/'.basename((string)$res['path']));
    $out['gone'] = $upl->deleteStoredFile('files/files-zzzzzzzzzz-5.png');
    $out['done'] = $upl->deleteStoredFile((string)$res['path']);
    $out['twice'] = $upl->deleteStoredFile((string)$res['path']);
    $out['left'] = getProbeList('files');
    return $out;
}

# The batch entry point: both submitted shapes, the order of the results and the batch limit that transfers nothing
function getProbeBatch(): array {
    addProbeRoot();
    $out = [];
    $one = addProbeFile('png');
    $out['single'] = getProbeUpload()->addUploadedFiles($one, getProbeRule(), 'files', 'files', 0);
    $out['none'] = getProbeUpload()->addUploadedFiles([], getProbeRule(), 'files', 'files', 0);
    addProbeRoot();
    $out['count'] = getProbeUpload()->addUploadedFiles(getProbeMulti(['png', 'png', 'png']), getProbeRule(['maxfiles' => 2]), 'files', 'files', 0);
    $out['countleft'] = getProbeList('files');
    addProbeRoot();
    $mixed = getProbeMulti(['png', 'png']);
    $mixed['name'][1] = 'second.gz';
    $out['mixed'] = getProbeUpload()->addUploadedFiles($mixed, getProbeRule(['extensions' => 'png']), 'files', 'files', 0);
    $out['mixedleft'] = getProbeList('files');
    addProbeRoot();
    $bad = getProbeMulti(['png']);
    $bad['name'][0] = ['nested'];
    $out['nested'] = getProbeUpload()->addUploadedFiles($bad, getProbeRule(), 'files', 'files', 0);
    addProbeRoot();
    $flat = getProbeMulti(['png']);
    $flat['tmp_name'] = 'plain';
    $flat['size'] = 1;
    $flat['error'] = UPLOAD_ERR_OK;
    $out['flat'] = getProbeUpload()->addUploadedFiles($flat, getProbeRule(), 'files', 'files', 0);
    $out['flatleft'] = getProbeList('files');
    return $out;
}

# Sum the published bytes of one destination the way the quota does, which is what the race scenario compares against the limit
function getProbeBytes(string $dir): int {
    $sum = 0;
    foreach (getProbeList($dir) as $file) $sum += (int)filesize($GLOBALS['proot'].'/'.$dir.'/'.$file);
    return $sum;
}

# One child publication of the race: this process publishes a single file into the shared destination and reports only what it got
function getProbeChild(int $max): array {
    $file = addProbeFile('png');
    if ($file === []) return ['ok' => false, 'file' => null, 'error' => 'nofixture'];
    $res = getProbeUpload()->addUploadedFile($file, getProbeRule(['maxquota' => $max]), 'files', 'files', 0);
    return ['ok' => (bool)$res['ok'], 'file' => $res['file'], 'error' => $res['error']];
}

# Concurrent writers against one destination: every writer takes the same lock, so a race can neither oversubscribe the quota nor publish two identical names
function getProbeRace(): array {
    addProbeRoot();
    $file = addProbeFile('png');
    $size = (int)$file['size'];
    unlink($file['tmp_name']);
    $max = $size * 2;
    $proc = [];
    $pipe = [];
    for ($i = 0; $i < 4; $i++) {
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' child '.escapeshellarg($GLOBALS['pwork']).' '.escapeshellarg((string)$max);
        $proc[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe[$i]);
    }
    $runs = [];
    foreach ($proc as $key => $one) {
        if (!is_resource($one)) continue;
        $runs[] = json_decode(trim((string)stream_get_contents($pipe[$key][1])), true);
        foreach ($pipe[$key] as $hand) fclose($hand);
        proc_close($one);
    }
    return ['size' => $size, 'max' => $max, 'runs' => $runs, 'left' => getProbeList('files'), 'parts' => getProbeParts('files'), 'bytes' => getProbeBytes('files')];
}

# The lock files that exist at this moment, read out of the one directory FileManager names, which is how two writers are proven to open one file rather than two
function getProbeLockList(): array {
    $dir = LOGS_DIR.'/uploads';
    $out = is_dir($dir) ? array_values(array_diff(scandir($dir) ?: [], ['.', '..'])) : [];
    sort($out);
    return $out;
}

# One child of the queue scenario: it publishes into the destination whose lock the parent holds, and reports when it entered the publication and when it finally got through
function getProbeHold(): array {
    $file = addProbeFile('png');
    if ($file === []) return ['ok' => false, 'from' => 0.0, 'done' => 0.0, 'error' => 'nofixture'];
    touch($GLOBALS['pwork'].'/hold.flag');
    $from = microtime(true);
    $res = getProbeUpload()->addUploadedFile($file, getProbeRule(), 'files', 'files', 0);
    return ['ok' => (bool)$res['ok'], 'from' => $from, 'done' => microtime(true), 'error' => $res['error']];
}

# One queue for the whole project: the file layer holds the lock of a destination, and an upload of another process into the same directory waits instead of publishing beside it
# The upload service takes its lock through FileManager as well, so the two open one lock file; a protocol of its own would leave a second name and serialize nothing
function getProbeQueue(): array {
    addProbeRoot();
    $canon = str_replace('\\', '/', (string)realpath($GLOBALS['proot'].'/files'));
    $flag = $GLOBALS['pwork'].'/hold.flag';
    if (is_file($flag)) unlink($flag);
    $lock = FileManager::getPathLock($canon);
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' hold '.escapeshellarg($GLOBALS['pwork']);
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipe);
    for ($i = 0; $i < 100; $i++) {
        clearstatcache(true, $flag);
        if (is_file($flag)) break;
        usleep(50000);
    }
    usleep(300000);
    $during = getProbeList('files');
    $free = microtime(true);
    FileManager::deletePathLock($lock);
    $child = [];
    if (is_resource($proc)) {
        $child = json_decode(trim((string)stream_get_contents($pipe[1])), true);
        foreach ($pipe as $hand) fclose($hand);
        proc_close($proc);
    }
    $out = ['held' => $lock !== false, 'child' => is_array($child) ? $child : [], 'during' => $during, 'after' => getProbeList('files')];
    return $out + ['free' => $free, 'dir' => LOGS_DIR.'/uploads', 'want' => substr(sha1($canon), 0, 16).'.lock', 'locks' => getProbeLockList()];
}

# Assemble the multi-file shape a browser sends for one file input that accepts several files
function getProbeMulti(array $list): array {
    $out = ['name' => [], 'tmp_name' => [], 'size' => [], 'error' => []];
    foreach ($list as $ext) {
        $file = addProbeFile($ext);
        $out['name'][] = $file['name'] ?? '';
        $out['tmp_name'][] = $file['tmp_name'] ?? '';
        $out['size'][] = $file['size'] ?? 0;
        $out['error'][] = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    }
    return $out;
}

# The bytes one scripted reply serves as its body: a real fixture of the requested format, removed from the source directory once it has been read
function getProbeSource(string $ext, int $wid = 40, int $hei = 30): string {
    $file = addProbeFile($ext, '', $wid, $hei);
    if ($file === []) return '';
    $body = (string)file_get_contents($file['tmp_name']);
    unlink($file['tmp_name']);
    return $body;
}

# The scripted zone a remote scenario resolves against unless it brings its own: one public address for the host every fixture URL points at
function getProbeZone(array $rows = []): array {
    return $rows ?: ['files.example.net' => [['type' => 'A', 'ip' => '93.184.216.34']]];
}

# An alias chain longer than the class follows, which is what proves the chain limit rather than the loop check
function getProbeChain(): array {
    $out = ['files.example.net' => [['type' => 'CNAME', 'target' => 'hopa.example.org']]];
    $list = ['hopa', 'hopb', 'hopc', 'hopd', 'hope', 'hopf'];
    foreach ($list as $key => $name) {
        $next = $list[$key + 1] ?? '';
        $out[$name.'.example.org'] = ($next === '') ? [['type' => 'A', 'ip' => '93.184.216.36']] : [['type' => 'CNAME', 'target' => $next.'.example.org']];
    }
    return $out;
}

# Fetch one URL against a scripted zone and a scripted reply chain, and report the result with what the destination holds and how every request was configured
function addProbeFetch(array $over, string $url = 'https://files.example.net/photo.png', array $rule = []): array {
    $upl = getProbeUpload();
    $upl->dns = getProbeZone($over['dns'] ?? []);
    $upl->reply = $over['reply'] ?? [];
    $upl->peer = (string)($over['peer'] ?? '');
    $res = $upl->addRemoteFile($url, $rule ?: getProbeRule(), (string)($over['dir'] ?? 'files'), 'files', $over['uid'] ?? null);
    $res['left'] = getProbeList('files');
    $res['parts'] = getProbeParts('files');
    $res['hits'] = $upl->hits;
    return $res;
}

# Reduce one fetch to the full result shape plus what the destination and the request log looked like afterwards
function getProbeShape(array $res): array {
    $out = $res;
    $out['ok'] = (bool)$res['ok'];
    $out['pin'] = (string)($res['hits'][0]['pin'] ?? '');
    $out['bind'] = (string)($res['hits'][0]['bind'] ?? '');
    $out['hits'] = count($res['hits']);
    return $out;
}

# URL policy: only a plain http or https URL with a default port, no credentials and no fragment reaches a lookup at all
function getProbeRemoteUrl(): array {
    addProbeRoot();
    $body = getProbeSource('png');
    $list = [
        'plain' => 'https://files.example.net/photo.png',
        'port' => 'https://files.example.net:443/photo.png',
        'query' => 'https://files.example.net/photo.png?v=2',
        'clear' => 'http://files.example.net/photo.png',
        'badport' => 'https://files.example.net:8443/photo.png',
        'zeroport' => 'https://files.example.net:0/photo.png',
        'crossport' => 'https://files.example.net:80/photo.png',
        'ftp' => 'ftp://files.example.net/photo.png',
        'local' => 'file:///etc/passwd',
        'script' => 'javascript:alert(1)',
        'creds' => 'https://user:pass@files.example.net/photo.png',
        'frag' => 'https://files.example.net/photo.png#part',
        'single' => 'https://localhost/photo.png',
        'under' => 'https://files_example.net/photo.png',
        'space' => 'https://files.example.net/pho to.png',
        'nul' => "https://files.example.net/pho\x00to.png",
        'none' => '',
        'noscheme' => '//files.example.net/photo.png',
    ];
    $out = [];
    foreach ($list as $key => $url) {
        addProbeRoot();
        $out[$key] = getProbeShape(addProbeFetch(['reply' => [['code' => 200, 'body' => $body]]], $url));
    }
    return $out;
}

# Address policy: every resolved address is validated before a connection, and one non-public address anywhere in the answer refuses the host
function getProbeRemoteDns(): array {
    addProbeRoot();
    $body = getProbeSource('png');
    $site = 'https://files.example.net/photo.png';
    $reply = [['code' => 200, 'body' => $body]];
    $alias = ['files.example.net' => [['type' => 'CNAME', 'target' => 'edge.example.org']]];
    $list = [
        'public' => [getProbeZone(), $site],
        'private' => [['files.example.net' => [['type' => 'A', 'ip' => '10.0.0.5']]], $site],
        'loop' => [['files.example.net' => [['type' => 'A', 'ip' => '127.0.0.1']]], $site],
        'link' => [['files.example.net' => [['type' => 'A', 'ip' => '169.254.169.254']]], $site],
        'cgnat' => [['files.example.net' => [['type' => 'A', 'ip' => '100.64.1.1']]], $site],
        'zero' => [['files.example.net' => [['type' => 'A', 'ip' => '0.0.0.0']]], $site],
        'cast' => [['files.example.net' => [['type' => 'A', 'ip' => '224.0.0.1']]], $site],
        'docnet' => [['files.example.net' => [['type' => 'A', 'ip' => '203.0.113.10']]], $site],
        'mixed' => [['files.example.net' => [['type' => 'A', 'ip' => '93.184.216.34'], ['type' => 'A', 'ip' => '192.168.0.5']]], $site],
        'ula' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => 'fc00::1']]], $site],
        'sixloop' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '::1']]], $site],
        'mapped' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '::ffff:127.0.0.1']]], $site],
        'sixpub' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '2606:4700::1111']]], $site],
        'sixbench' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '2001:2::1']]], $site],
        'sixdoc' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '3fff::1']]], $site],
        'sixsid' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '5f00::1']]], $site],
        'sixorchid' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '2001:20::1']]], $site],
        'sixdummy' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '100:0:0:1::1']]], $site],
        'sixdiscard' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '100::1']]], $site],
        'sixsite' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => 'fec0::1']]], $site],
        'sixfree' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '4000::1']]], $site],
        'sixbone' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '3ffe::1']]], $site],
        'sixgap' => [['files.example.net' => [['type' => 'AAAA', 'ipv6' => '2004::1']]], $site],
        'gone' => [['other.example.net' => [['type' => 'A', 'ip' => '93.184.216.34']]], $site],
        'pair' => [['files.example.net' => [['type' => 'A', 'ip' => '93.184.216.34'], ['type' => 'A', 'ip' => '104.18.32.7']]], $site],
        'alias' => [$alias + ['edge.example.org' => [['type' => 'A', 'ip' => '104.18.32.7']]], $site],
        'aliasbad' => [$alias + ['edge.example.org' => [['type' => 'A', 'ip' => '192.168.1.7']]], $site],
        'aliasloop' => [$alias + ['edge.example.org' => [['type' => 'CNAME', 'target' => 'files.example.net']]], $site],
        'aliasdeep' => [getProbeChain(), $site],
        'literal' => [[], 'https://93.184.216.35/photo.png'],
        'sixlit' => [[], 'https://[2606:4700::1111]/photo.png'],
        'literalbad' => [[], 'https://127.0.0.1/photo.png'],
        'sixliteral' => [[], 'https://[::1]/photo.png'],
    ];
    $out = [];
    foreach ($list as $key => $one) {
        addProbeRoot();
        $out[$key] = getProbeShape(addProbeFetch(['dns' => $one[0], 'reply' => $reply], $one[1]));
    }
    # The scenarios above share one log for the whole process, so what the address policy recorded is read back once at the end
    $out['policy'] = ['log' => getProbeLog()];
    return $out;
}

# Redirects: every hop is validated again, at most three are followed, an intermediate body is discarded and only a final 2xx publishes
function getProbeRemoteHops(): array {
    addProbeRoot();
    $body = getProbeSource('png');
    $done = ['code' => 200, 'body' => $body];
    $hop = static fn(string $to): array => ['code' => 302, 'next' => $to];
    $zone = [
        'files.example.net' => [['type' => 'A', 'ip' => '93.184.216.34']],
        'edge.example.org' => [['type' => 'A', 'ip' => '104.18.32.7']],
        'inner.example.org' => [['type' => 'A', 'ip' => '10.1.1.1']],
    ];
    $away = 'https://edge.example.org/other.png';
    $list = [
        'one' => [$hop($away), $done],
        'relative' => [$hop('/deep/other.png'), $done],
        'three' => [$hop('https://files.example.net/a.png'), $hop('https://files.example.net/b.png'), $hop($away), $done],
        'four' => [$hop('https://files.example.net/a.png'), $hop('https://files.example.net/b.png'), $hop('https://files.example.net/c.png'), $hop($away), $done],
        'private' => [$hop('https://inner.example.org/other.png'), $done],
        'scheme' => [$hop('file:///etc/passwd'), $done],
        'noloc' => [['code' => 302]],
        'missing' => [['code' => 404, 'body' => 'not found']],
        'discard' => [['code' => 302, 'next' => $away, 'body' => 'DISCARDED BODY'], $done],
    ];
    $out = ['bytes' => strlen($body)];
    foreach ($list as $key => $reply) {
        addProbeRoot();
        $out[$key] = getProbeShape(addProbeFetch(['dns' => $zone, 'reply' => $reply]));
    }
    return $out;
}

# Byte limits, a transport that never answers, and the address the connection really reached
function getProbeRemoteBody(): array {
    addProbeRoot();
    $body = getProbeSource('png');
    $len = strlen($body);
    $list = [
        'edge' => [['reply' => [['code' => 200, 'body' => $body]]], getProbeRule(['maxbytes' => $len])],
        'claim' => [['reply' => [['code' => 200, 'body' => $body, 'claim' => $len + 1]]], getProbeRule(['maxbytes' => $len])],
        'stream' => [['reply' => [['code' => 200, 'body' => $body, 'nolen' => true]]], getProbeRule(['maxbytes' => $len - 1])],
        'fail' => [['reply' => [['fail' => true]]], getProbeRule()],
        'rebind' => [['reply' => [['code' => 200, 'body' => $body]], 'peer' => '8.8.4.4'], getProbeRule()],
    ];
    $out = ['bytes' => $len];
    foreach ($list as $key => $one) {
        addProbeRoot();
        $out[$key] = getProbeShape(addProbeFetch($one[0], 'https://files.example.net/photo.png', $one[1]));
    }
    return $out;
}

# The final URL decides the extension, and the transferred body then passes the same type, image, quota and naming checks a local upload passes
function getProbeRemoteType(): array {
    addProbeRoot();
    $png = getProbeSource('png');
    $site = 'https://files.example.net/photo.png';
    $good = [['code' => 200, 'body' => $png]];
    $arch = [['code' => 200, 'body' => getProbeSource('gz')]];
    $wide = [['code' => 200, 'body' => getProbeSource('png', 200, 100)]];
    $list = [
        'query' => [['reply' => $good], $site.'?v=2', getProbeRule()],
        'owner' => [['reply' => $good, 'uid' => 9], $site, getProbeRule()],
        'noext' => [['reply' => $good], 'https://files.example.net/photo', getProbeRule()],
        'blocked' => [['reply' => $good], 'https://files.example.net/shell.php', getProbeRule(['extensions' => 'php,png'])],
        'unsupported' => [['reply' => $good], 'https://files.example.net/photo.bmp', getProbeRule(['extensions' => 'bmp,png'])],
        'notlisted' => [['reply' => $good], $site, getProbeRule(['extensions' => 'gif'])],
        'mime' => [['reply' => $arch], $site, getProbeRule()],
        'broken' => [['reply' => [['code' => 200, 'body' => substr($png, 0, 33)]]], $site, getProbeRule()],
        'dimensions' => [['reply' => $wide], $site, getProbeRule(['maxwidth' => 100, 'maxheight' => 100])],
        'archive' => [['reply' => $arch], 'https://files.example.net/backup.gz', getProbeRule()],
        'destination' => [['reply' => $good, 'dir' => 'nosuchdir'], $site, getProbeRule()],
    ];
    $out = [];
    foreach ($list as $key => $one) {
        addProbeRoot();
        $out[$key] = getProbeShape(addProbeFetch($one[0], $one[1], $one[2]));
    }
    addProbeRoot();
    file_put_contents($GLOBALS['proot'].'/files/stored.png', str_repeat('x', 400));
    $out['quota'] = getProbeShape(addProbeFetch(['reply' => $good], $site, getProbeRule(['maxquota' => 500])));
    return $out;
}

# How one request is configured: pinned to the validated address, no proxy, no protocol beyond http and https, TLS verification kept and both timeouts set
function getProbeRemoteOpts(): array {
    addProbeRoot();
    $res = addProbeFetch(['reply' => [['code' => 200, 'body' => getProbeSource('png')]]]);
    return ['ok' => (bool)$res['ok'], 'error' => $res['error'], 'hits' => $res['hits']];
}

# The named fields of one upload configuration string, read off the shipped resolver instead of repeated here, so an appended key cannot drift out of this probe
function getProbeRuleKeys(): array {
    return array_values(array_diff(array_keys(getUploadRuleData('nosuchmodule')), ['ok', 'error', 'dir', 'path']));
}

# The resolver and the serializer against the shipped configuration: no double can answer this, because the whole point is that the real records survive the round trip
# A rule one field short normalises on the first write instead of round tripping: the key order is completed, no stored value changes, and every later write reproduces the string
# config/uploads.php also holds the scalars typ, dir, width and height, which are not module records and are excluded by the pipe that every record carries
function getProbeResolver(): array {
    global $conf;
    $out = ['records' => [], 'keys' => getProbeRuleKeys()];
    $mods = [];
    foreach ($conf['uploads'] as $mod => $val) {
        if (is_string($val) && str_contains($val, '|')) $mods[(string)$mod] = $val;
    }
    foreach ($mods as $mod => $val) {
        $rule = getUploadRuleData($mod);
        $norm = setUploadRuleData($rule);
        $conf['uploads']['probenorm'] = $norm;
        $out['records'][$mod] = [
            'ok' => (bool)$rule['ok'],
            'kept' => array_slice(explode('|', $norm), 0, count(explode('|', $val))) === explode('|', $val),
            'stable' => setUploadRuleData(getUploadRuleData('probenorm')) === $norm,
            'fields' => count(explode('|', $norm)),
        ];
    }
    $none = getUploadRuleData('nosuchmodule');
    $out['unknown'] = ['ok' => (bool)$none['ok'], 'ext' => (string)$none['extensions'], 'keys' => count(array_intersect($out['keys'], array_keys($none)))];
    $out['allext'] = (string)getUploadRuleData('all')['extensions'];
    $conf['uploads']['probeshort'] = 'gif';
    $short = getUploadRuleData('probeshort');
    $out['short'] = ['ok' => (bool)$short['ok'], 'ext' => (string)$short['extensions'], 'quota' => (int)$short['maxquota'], 'guest' => (int)$short['guestupload']];
    $conf['uploads']['probeold'] = 'gif|1|2|3|4|5|6|7|8|9|1';
    $old = getUploadRuleData('probeold');
    $out['old'] = ['userfiles' => (int)$old['userfiles'], 'guestfiles' => (int)$old['guestfiles']];
    $conf['uploads']['probeempty'] = '';
    $out['empty'] = ['ok' => (bool)getUploadRuleData('probeempty')['ok']];
    return $out;
}

# The accessor is the only place that names the upload root, so it is read back off the instance it builds; the lock directory is no property of this class any more
# The decoder list travels with it, because the fail closed branch of getDecodeError() is only unreachable while this build has every one of them
function getProbeService(): array {
    $one = getUploadService();
    $two = getUploadService();
    $ref = new ReflectionClass(Upload::class);
    $root = $ref->getProperty('root');
    $lock = FileManager::getPathLock($GLOBALS['pwork']);
    $held = getProbeLockList();
    FileManager::deletePathLock($lock);
    $gone = [];
    foreach (['imagecreatefromgif', 'imagecreatefromjpeg', 'imagecreatefrompng', 'imagecreatefromwebp', 'imagecreatefromavif'] as $call) {
        if (!function_exists($call)) $gone[] = $call;
    }
    return [
        'nodecoder' => $gone,
        'same' => $one === $two,
        'root' => (string)$root->getValue($one),
        'fields' => array_map(static fn(ReflectionProperty $one): string => $one->getName(), $ref->getProperties()),
        'held' => $held,
        'wantroot' => rtrim(str_replace('\\', '/', UPLOADS_DIR), '/'),
        'wantlock' => [substr(sha1($GLOBALS['pwork']), 0, 16).'.lock'],
        'types' => Upload::getSupportedTypes(),
    ];
}

$mode = (string)($argv[1] ?? '');
$out = [];
try {
    $out = match ($mode) {
        'resolver' => getProbeResolver(),
        'service' => getProbeService(),
        'child' => getProbeChild((int)($argv[3] ?? 0)),
        'race' => getProbeRace(),
        'hold' => getProbeHold(),
        'queue' => getProbeQueue(),
        'types' => getProbeTypes(),
        'codes' => getProbeCodes(),
        'naming' => getProbeNaming(),
        'publish' => getProbePublish(),
        'sweep' => getProbeSweep(),
        'quota' => getProbeQuota(),
        'paths' => getProbePaths(),
        'delete' => getProbeDelete(),
        'batch' => getProbeBatch(),
        'remoteurl' => getProbeRemoteUrl(),
        'remotedns' => getProbeRemoteDns(),
        'remotehops' => getProbeRemoteHops(),
        'remotebody' => getProbeRemoteBody(),
        'remotetype' => getProbeRemoteType(),
        'remoteopts' => getProbeRemoteOpts(),
        default => ['error' => 'unknown scenario '.$mode],
    };
} catch (Throwable $error) {
    $out = ['error' => $error->getMessage().' @ '.$error->getFile().':'.$error->getLine()];
}
while (ob_get_level() > 0) ob_end_clean();
echo json_encode($out, JSON_UNESCAPED_SLASHES);
