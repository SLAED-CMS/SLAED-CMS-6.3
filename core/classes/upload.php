<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Publishes uploaded files below one upload root: extension, type, image and quota validation, collision-free naming and lock-guarded atomic publication (docs/UPLOAD-2026.md)
# Every result is the same eight-key shape; a failure carries only its machine-readable code and leaves neither a final file nor a partial behind
# The clock, the transfer, the random name segment and the two filesystem primitives sit behind protected seams, so the tests reach every branch without an HTTP request
# Production code never subclasses this class and always reaches it through getUploadService()
class Upload {
    private const TRIES = 5;
    private const SALTLEN = 10;
    private const PARTAGE = 3600;
    private const REDIRS = 3;
    private const CHAIN = 5;
    private const CONNSEC = 5;
    private const LOADSEC = 30;
    private const KEEP = ['index.html', '.htaccess'];
    private const BLOCK = ['phtml', 'js', 'htm', 'html', 'cgi', 'pl', 'perl', 'asp', 'swf'];
    private const IMAGES = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'];
    # Every IPv4 network the IANA special-purpose registry marks as not globally reachable, plus the ranges that are reachable but never a legitimate fetch target
    private const BLOCKNET = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
        '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4',
    ];
    # IPv6 is judged by an allowlist instead, because 2000::/3 is far from fully delegated: everything IANA still holds is reserved for future allocation and is refused by absence
    # These are the prefixes of the IANA global unicast registry held by a regional registry, so 2001::/23 (IETF), 2002::/16 (6to4) and every IANA block are simply not here
    # A newly delegated prefix must be added before this class fetches from it, which is the fail closed half of the bargain and the reason the registry is named above
    private const ALLOWSIX = [
        '2001:200::/23', '2001:400::/23', '2001:600::/23', '2001:800::/22', '2001:c00::/23', '2001:e00::/23', '2001:1200::/23', '2001:1400::/22',
        '2001:1800::/23', '2001:1a00::/23', '2001:1c00::/22', '2001:2000::/19', '2001:4000::/23', '2001:4200::/23', '2001:4400::/23', '2001:4600::/23',
        '2001:4800::/23', '2001:4a00::/23', '2001:4c00::/23', '2001:5000::/20', '2001:8000::/19', '2001:a000::/20', '2001:b000::/20', '2003::/18',
        '2400::/12', '2410::/12', '2600::/12', '2610::/23', '2620::/23', '2630::/12', '2800::/12', '2a00::/12', '2a10::/12', '2c00::/12',
    ];
    # The one special-purpose registration that leaving it out cannot exclude, because it sits inside a delegated prefix: 2001:db8::/32 lives in the APNIC block 2001:c00::/23
    private const BLOCKSIX = ['2001:db8::/32'];
    private const TYPES = [
        'gif' => ['image/gif'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/x-wav', 'audio/wav', 'audio/vnd.wave'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'oga' => ['audio/ogg', 'application/ogg'],
        'opus' => ['audio/ogg', 'application/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip'],
        'rar' => ['application/x-rar', 'application/vnd.rar'],
        'gz' => ['application/gzip', 'application/x-gzip'],
        '7z' => ['application/x-7z-compressed'],
        'tar' => ['application/x-tar', 'application/tar'],
    ];

    private string $root;
    private string $lockdir;
    private ?finfo $reader = null;

    # Builds the service over the upload root and the directory the destination locks live in; both arguments come from getUploadService(), so no adapter repeats them
    public function __construct(string $root, string $locks) {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
        $this->lockdir = rtrim(str_replace('\\', '/', $locks), '/');
    }

    # Every extension the type policy knows, which is what the admin settings validate a configured list against, so the policy exists in exactly one place
    public static function getSupportedTypes(): array {
        return array_keys(self::TYPES);
    }

    # Every IPv6 prefix the address policy will visit, which is what tools/ipv6-registry-check.php compares against the IANA registry, so the list exists in exactly one place
    public static function getPublicNets(): array {
        return self::ALLOWSIX;
    }

    # Publishes one submitted file; everything that can fail without touching the destination is checked before the transfer, so a rejected upload never reaches the upload tree
    # The source path stays as the SAPI reported it: PHP matches it literally, so a separator normalized to / makes move_uploaded_file() refuse it on Windows
    public function addUploadedFile(array $file, array $rule, string $dir, string $base, ?int $uid = null): array {
        $code = $this->checkUploadInput($file, $rule);
        if ($code !== '') return $this->getFailResult($code);
        $tmp = (string)$file['tmp_name'];
        $ext = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $code = $this->checkTypePolicy($ext, $rule);
        if ($code !== '') return $this->getFailResult($code);
        $mime = $this->getFileMime($tmp);
        if (!in_array($mime, self::TYPES[$ext], true)) return $this->getFailResult('mime');
        $info = $this->getImageBounds($tmp, $ext, $rule);
        if ($info['error'] !== '') return $this->getFailResult($info['error']);
        $rel = $this->getSafeDir($dir);
        $canon = ($rel === '') ? '' : $this->getDestPath($rel);
        if ($canon === '') return $this->getFailResult('destination');
        $part = $this->getPartPath($canon);
        if (!$this->addSourceFile($tmp, $part)) {
            $this->deleteUploadPart($part, $rel);
            return $this->getFailResult('transfer');
        }
        $info['ext'] = $ext;
        $info['mime'] = $mime;
        return $this->addPublishRun($part, $info, $rule, $rel, $base, $uid);
    }

    # Publishes a whole batch in submission order, mixed successes and failures included; a batch over maxfiles transfers nothing and reports one count failure
    public function addUploadedFiles(array $files, array $rule, string $dir, string $base, ?int $uid = null): array {
        $list = $this->getUploadList($files);
        if ($list === []) return [$this->getFailResult('missing')];
        $num = (int)($rule['maxfiles'] ?? 0);
        if ($num > 0 && count($list) > $num) return [$this->getFailResult('count')];
        $out = [];
        foreach ($list as $one) $out[] = $this->addUploadedFile($one, $rule, $dir, $base, $uid);
        return $out;
    }

    # Publishes one file fetched from a remote URL; the address policy, the redirects and the byte limits run first, the partial then passes the checks a local upload passes
    public function addRemoteFile(string $url, array $rule, string $dir, string $base, ?int $uid = null): array {
        if ($this->getRuleTypes($rule) === []) return $this->getFailResult('extension');
        if ($this->getTypeReader() === null || !function_exists('curl_init')) return $this->getFailResult('unsupported');
        $rel = $this->getSafeDir($dir);
        $canon = ($rel === '') ? '' : $this->getDestPath($rel);
        if ($canon === '') return $this->getFailResult('destination');
        $part = $this->getPartPath($canon);
        $res = $this->addRemoteFetch($url, $part, (int)($rule['maxbytes'] ?? 0));
        if ($res['error'] !== '') return $this->getAbortResult(false, $part, $rel, $res['error']);
        $ext = strtolower((string)pathinfo($res['path'], PATHINFO_EXTENSION));
        $code = $this->checkTypePolicy($ext, $rule);
        if ($code !== '') return $this->getAbortResult(false, $part, $rel, $code);
        $mime = $this->getFileMime($part);
        if (!in_array($mime, self::TYPES[$ext], true)) return $this->getAbortResult(false, $part, $rel, 'mime');
        $info = $this->getImageBounds($part, $ext, $rule);
        if ($info['error'] !== '') return $this->getAbortResult(false, $part, $rel, $info['error']);
        $info['ext'] = $ext;
        $info['mime'] = $mime;
        return $this->addPublishRun($part, $info, $rule, $rel, $base, $uid);
    }

    # Deletes one file this class published, addressed by the root-relative path a result returned; anything that is not a canonical class-owned name below the root is refused
    public function deleteStoredFile(string $path): bool {
        $path = str_replace('\\', '/', $path);
        $file = basename($path);
        $rel = $this->getSafeDir(dirname($path));
        if ($rel === '' || !preg_match('#^[a-zA-Z0-9_]+-[a-zA-Z0-9]{'.self::SALTLEN.'}(?:-[0-9]+)?\.[a-zA-Z0-9]+$#', $file)) return false;
        $canon = $this->getDestPath($rel);
        if ($canon === '') return false;
        $full = $canon.'/'.$file;
        if (is_link($full) || !is_file($full)) return false;
        $lock = $this->getLockHandle($canon);
        if ($lock === false) return false;
        $done = $this->deleteFilePath($full);
        $this->deleteLockHandle($lock);
        return $done;
    }

    # Returns the wall clock the one-hour partial sweep measures against; seam, because a sweep test cannot wait an hour
    protected function getTime(): int {
        return time();
    }

    # Returns whether the source really arrived through an HTTP upload; seam, because no unit test can forge a request-scoped upload
    protected function checkSourceFile(string $path): bool {
        return is_uploaded_file($path);
    }

    # Moves the accepted upload into its partial; seam, and the counterpart of checkSourceFile(), because move_uploaded_file() refuses anything the SAPI did not receive
    protected function addSourceFile(string $from, string $dest): bool {
        return move_uploaded_file($from, $dest);
    }

    # Returns the random segment of a stored name; seam, so the collision and publication tests can drive a known name instead of guessing one
    protected function getNameSalt(): string {
        return getRandomString(self::SALTLEN);
    }

    # Publishes the partial under its final name; seam, so a publication failure can be tested on a filesystem that would happily rename
    protected function addPublishFile(string $from, string $dest): bool {
        return rename($from, $dest);
    }

    # Removes one file of this class; seam, so the cleanup-failure branch can be tested without a filesystem that refuses to delete
    protected function deleteFilePath(string $path): bool {
        return is_file($path) && unlink($path);
    }

    # Returns the address and alias records of one host; seam, because the address policy has to be tested against records no public resolver would hand out
    protected function getHostRecords(string $host): array {
        set_error_handler(static fn(): bool => true);
        try {
            $rows = dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME);
        } finally {
            restore_error_handler();
        }
        return is_array($rows) ? $rows : [];
    }

    # Runs one prepared request and reports what came back; seam, because a unit test may not reach the network and still has to drive redirects, overflow and a rebound address
    protected function getRemoteReply(array $opts): array {
        $curl = curl_init();
        if ($curl === false) return ['ok' => false, 'peer' => ''];
        curl_setopt_array($curl, $opts);
        $done = curl_exec($curl);
        $out = ['ok' => $done !== false, 'peer' => (string)curl_getinfo($curl, CURLINFO_PRIMARY_IP)];
        curl_close($curl);
        return $out;
    }

    # Checks the submitted file against the rule and the runtime: a malformed shape, a PHP upload error, a source that is not an upload and an oversized file all stop here
    private function checkUploadInput(array $file, array $rule): string {
        if ($this->getRuleTypes($rule) === []) return 'extension';
        if ($this->getTypeReader() === null) return 'unsupported';
        $name = $file['name'] ?? '';
        $tmp = $file['tmp_name'] ?? '';
        if (!is_string($name) || !is_string($tmp) || $name === '') return 'missing';
        $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if (!is_int($err) && !is_string($err)) return 'missing';
        $err = (int)$err;
        if ($err === UPLOAD_ERR_NO_FILE) return 'missing';
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) return 'size';
        if ($err !== UPLOAD_ERR_OK) return 'transfer';
        if ($tmp === '' || !is_file($tmp) || !$this->checkSourceFile($tmp)) return 'transfer';
        $max = (int)($rule['maxbytes'] ?? 0);
        if ($max > 0 && (int)filesize($tmp) > $max) return 'size';
        return '';
    }

    # Checks the extension against the configured allowlist, then against the two policies configuration cannot override: web-active formats and formats without a type policy
    private function checkTypePolicy(string $ext, array $rule): string {
        if ($ext === '' || !preg_match('#^[a-z0-9]+$#', $ext)) return 'extension';
        if (!in_array($ext, $this->getRuleTypes($rule), true)) return 'extension';
        if (str_starts_with($ext, 'php') || in_array($ext, self::BLOCK, true)) return 'extension';
        return isset(self::TYPES[$ext]) ? '' : 'unsupported';
    }

    # Splits the configured extension list of one rule into its normalized members
    private function getRuleTypes(array $rule): array {
        $typ = $rule['extensions'] ?? '';
        if (!is_string($typ)) return [];
        return array_values(array_filter(array_map('trim', explode(',', strtolower($typ)))));
    }

    # Returns the detected MIME type of one file, lowercased; an undetectable file returns an empty string and is refused by the caller
    private function getFileMime(string $path): string {
        $info = $this->getTypeReader();
        $mime = ($info instanceof finfo) ? $info->file($path) : false;
        return is_string($mime) ? strtolower($mime) : '';
    }

    # Returns the one type reader of this instance; a build without the extension or without a readable magic database returns null, which is what makes the capability fail closed
    private function getTypeReader(): ?finfo {
        if ($this->reader instanceof finfo) return $this->reader;
        if (!class_exists('finfo')) return null;
        try {
            $this->reader = new finfo(FILEINFO_MIME_TYPE);
        } catch (Throwable) {
            return null;
        }
        return $this->reader;
    }

    # Decodes an image and checks it against the configured bounds; a non-image carries no dimensions at all, which is what keeps a successful result honest about what it published
    # The dimension check runs before the decode on purpose: it is read from the header, so the configured bounds cap what the decoder is ever asked to allocate
    private function getImageBounds(string $path, string $ext, array $rule): array {
        if (!in_array($ext, self::IMAGES, true)) return ['error' => '', 'width' => null, 'height' => null];
        $info = $this->getImageInfo($path);
        if ($info === []) return ['error' => 'image', 'width' => null, 'height' => null];
        $wid = (int)($rule['maxwidth'] ?? 0);
        $hei = (int)($rule['maxheight'] ?? 0);
        $over = ($wid > 0 && $info[0] > $wid) || ($hei > 0 && $info[1] > $hei);
        if ($over) return ['error' => 'dimensions', 'width' => $info[0], 'height' => $info[1]];
        $code = $this->getDecodeError($path, $info[2]);
        if ($code !== '') return ['error' => $code, 'width' => $info[0], 'height' => $info[1]];
        return ['error' => '', 'width' => $info[0], 'height' => $info[1]];
    }

    # Proves the pixel data really decodes, because getimagesize() reads the header only and answers a full size for a file whose image data is truncated or corrupt
    # Without a decoder the invariant cannot be satisfied, so the answer is unsupported rather than a silent pass; ext-gd is a composer require for that reason
    private function getDecodeError(string $path, int $type): string {
        $call = match ($type) {
            IMAGETYPE_GIF => 'imagecreatefromgif',
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => 'imagecreatefromwebp',
            IMAGETYPE_AVIF => 'imagecreatefromavif',
            default => '',
        };
        if ($call === '' || !function_exists($call)) return 'unsupported';
        set_error_handler(static fn(): bool => true);
        try {
            $img = $call($path);
        } finally {
            restore_error_handler();
        }
        if ($img === false) return 'image';
        unset($img);
        return '';
    }

    # Reads the pixel size and the type marker of one file; a decode failure is a routine rejection, so the warning is swallowed instead of suppressed with @
    private function getImageInfo(string $path): array {
        set_error_handler(static fn(): bool => true);
        try {
            $info = getimagesize($path);
        } finally {
            restore_error_handler();
        }
        if (!is_array($info) || (int)($info[0] ?? 0) < 1 || (int)($info[1] ?? 0) < 1) return [];
        return [(int)$info[0], (int)$info[1], (int)($info[2] ?? 0)];
    }

    # Normalizes one submitted batch into a list of single-file shapes, whatever shape the browser sent; a nested or incomplete entry stays in the list and fails on its own
    private function getUploadList(array $files): array {
        if (!isset($files['name'])) return [];
        if (!is_array($files['name'])) return [$files];
        $out = [];
        foreach ($files['name'] as $key => $one) {
            $out[] = [
                'name' => $one,
                'tmp_name' => is_array($files['tmp_name'] ?? null) ? ($files['tmp_name'][$key] ?? '') : '',
                'size' => is_array($files['size'] ?? null) ? ($files['size'][$key] ?? 0) : 0,
                'error' => is_array($files['error'] ?? null) ? ($files['error'][$key] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE,
            ];
        }
        return $out;
    }

    # Fetches one URL into the partial, resolving scheme, host and address again at every hop and following at most three redirects whose bodies are discarded
    private function addRemoteFetch(string $url, string $part, int $max): array {
        for ($i = 0; $i <= self::REDIRS; $i++) {
            $norm = $this->getRemoteUrl($url);
            if ($norm['error'] !== '') return ['error' => 'remote', 'path' => ''];
            $host = $this->getHostAddress($norm['host']);
            if ($host['error'] !== '') return ['error' => $host['error'], 'path' => ''];
            $addr = $host['addr'];
            $res = $this->addRemoteRun($norm, $addr, $part, $max);
            if ($res['error'] !== '') return ['error' => $res['error'], 'path' => ''];
            if ($res['code'] > 199 && $res['code'] < 300) return ['error' => '', 'path' => $norm['path']];
            if ($res['code'] < 300 || $res['code'] > 399 || $res['next'] === '') return ['error' => 'remote', 'path' => ''];
            $url = $this->getAbsoluteUrl($norm, $res['next']);
        }
        return ['error' => 'remote', 'path' => ''];
    }

    # Runs one hop pinned to the validated address, with TLS verification kept, proxies and other protocols refused, and a 2xx body streamed into the partial under both byte limits
    # A host that is already an address carries no pin: nothing has to be resolved, and libcurl refuses a resolve entry whose host part is an IPv6 literal by failing the transfer
    private function addRemoteRun(array $norm, string $addr, string $part, int $max): array {
        $hand = fopen($part, 'ab');
        if ($hand === false) return ['error' => 'write', 'code' => 0, 'next' => ''];
        $code = 0;
        $next = '';
        $size = 0;
        $over = false;
        $bad = false;
        $port = ($norm['scheme'] === 'https') ? 443 : 80;
        $pin = ($norm['host'] === $addr) ? [] : [$norm['host'].':'.$port.':'.$addr];
        $opts = [
            CURLOPT_URL => $norm['url'],
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS_STR => 'http,https',
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => self::CONNSEC,
            CURLOPT_TIMEOUT => self::LOADSEC,
            CURLOPT_RESOLVE => $pin,
            CURLOPT_HEADERFUNCTION => function (mixed $curl, string $line) use (&$code, &$next, &$over, $max): int {
                $len = strlen($line);
                $head = trim($line);
                if (preg_match('#^HTTP/[0-9.]+ +([0-9]{3})#', $head, $hit)) {
                    $code = (int)$hit[1];
                    $next = '';
                    return $len;
                }
                $pos = strpos($head, ':');
                if ($pos === false) return $len;
                $key = strtolower(substr($head, 0, $pos));
                $val = trim(substr($head, $pos + 1));
                if ($key === 'location' && $next === '') $next = $val;
                if ($key !== 'content-length' || $max < 1 || $code < 200 || $code > 299) return $len;
                if ((int)$val > $max) {
                    $over = true;
                    return 0;
                }
                return $len;
            },
            CURLOPT_WRITEFUNCTION => function (mixed $curl, string $chunk) use (&$code, &$size, &$over, &$bad, $hand, $max): int {
                $len = strlen($chunk);
                if ($code < 200 || $code > 299) return $len;
                if ($max > 0 && $size + $len > $max) {
                    $over = true;
                    return 0;
                }
                $put = fwrite($hand, $chunk);
                if ($put === false || $put < $len) {
                    $bad = true;
                    return 0;
                }
                $size += $len;
                return $len;
            },
        ];
        $res = $this->getRemoteReply($opts);
        fclose($hand);
        if ($over) return ['error' => 'size', 'code' => $code, 'next' => ''];
        if ($bad) return ['error' => 'write', 'code' => $code, 'next' => ''];
        if (empty($res['ok'])) return ['error' => 'remote', 'code' => $code, 'next' => ''];
        if (!$this->checkSameAddress((string)($res['peer'] ?? ''), $addr)) return ['error' => 'remote', 'code' => $code, 'next' => ''];
        return ['error' => '', 'code' => $code, 'next' => $next];
    }

    # Normalizes one URL before any lookup: only http and https survive, and credentials, fragments, non-default ports and ambiguous hosts are refused here rather than later
    private function getRemoteUrl(string $url): array {
        $fail = ['error' => 'remote', 'url' => '', 'auth' => '', 'host' => '', 'scheme' => '', 'path' => ''];
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('#[\x00-\x20\x7f]#', $url)) return $fail;
        $data = parse_url($url);
        if (!is_array($data) || isset($data['user']) || isset($data['pass']) || isset($data['fragment'])) return $fail;
        $sch = strtolower((string)($data['scheme'] ?? ''));
        if ($sch !== 'http' && $sch !== 'https') return $fail;
        if (isset($data['port']) && $data['port'] !== ($sch === 'https' ? 443 : 80)) return $fail;
        $host = strtolower((string)($data['host'] ?? ''));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) $host = substr($host, 1, -1);
        $ipv = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($host === '' || strlen($host) > 253) return $fail;
        if (!$ipv && !preg_match('#^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$#', $host)) return $fail;
        $path = (string)($data['path'] ?? '');
        $auth = str_contains($host, ':') ? '['.$host.']' : $host;
        $full = $sch.'://'.$auth.$path.(isset($data['query']) ? '?'.$data['query'] : '');
        return ['error' => '', 'url' => $full, 'auth' => $auth, 'host' => $host, 'scheme' => $sch, 'path' => $path];
    }

    # Resolves one Location header against the hop it arrived on, so a relative redirect is re-validated as a whole URL instead of being trusted as a path
    private function getAbsoluteUrl(array $norm, string $next): string {
        $next = trim($next);
        if ($next === '') return '';
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $next)) return $next;
        if (str_starts_with($next, '//')) return $norm['scheme'].':'.$next;
        $site = $norm['scheme'].'://'.$norm['auth'];
        if (str_starts_with($next, '/')) return $site.$next;
        $path = ($norm['path'] === '') ? '/' : $norm['path'];
        $base = substr($path, 0, (int)strrpos($path, '/') + 1);
        return $site.(($base === '') ? '/' : $base).$next;
    }

    # Resolves one host to a single public address, following a bounded alias chain; one non-public address anywhere in the answer refuses the whole host
    # A refusal by the address policy is reported apart from a lookup that found nothing, because the two need opposite answers from whoever reads the log
    private function getHostAddress(string $host): array {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->checkPublicAddress($host) ? ['addr' => $host, 'error' => ''] : $this->getAddressFail($host, $host);
        }
        $name = $host;
        $seen = [];
        for ($i = 0; $i < self::CHAIN; $i++) {
            $key = strtolower(rtrim($name, '.'));
            if ($key === '' || isset($seen[$key])) return ['addr' => '', 'error' => 'remote'];
            $seen[$key] = true;
            $list = [];
            $next = '';
            foreach ($this->getHostRecords($name) as $row) {
                $type = strtoupper((string)($row['type'] ?? ''));
                if ($type === 'A' || $type === 'AAAA') $list[] = (string)($row['ip'] ?? $row['ipv6'] ?? '');
                if ($type === 'CNAME' && $next === '') $next = (string)($row['target'] ?? '');
            }
            $list = array_values(array_filter($list));
            if ($list === []) {
                if ($next === '') return ['addr' => '', 'error' => 'remote'];
                $name = $next;
                continue;
            }
            foreach ($list as $addr) {
                if (!$this->checkPublicAddress($addr)) return $this->getAddressFail($name, $addr);
            }
            sort($list);
            return ['addr' => $list[0], 'error' => ''];
        }
        return ['addr' => '', 'error' => 'remote'];
    }

    # Records one address the policy refused with the host it came from, so an operator can tell a private target apart from a prefix this build does not know yet
    # Without this line the two are one failure to everyone outside the class, and a list behind the registry looks exactly like a host that is down
    private function getAddressFail(string $host, string $addr): array {
        Logger::addFile('error', 'Remote address refused by the address policy', ['host' => $host, 'address' => $addr]);
        return ['addr' => '', 'error' => 'address'];
    }

    # Returns whether one address is publicly routable: IPv4 against the blocked networks, IPv6 against the delegated prefixes and the one exception inside them
    # A mapped or compatible form is judged as the address it embeds, so a v6 spelling cannot smuggle a v4 target in
    private function checkPublicAddress(string $addr): bool {
        $bin = inet_pton($addr);
        if ($bin === false) return false;
        if (strlen($bin) === 16) {
            $head = substr($bin, 0, 12);
            $tail = substr($bin, 12);
            $flat = $head === str_repeat("\x00", 10)."\xff\xff" || ($head === str_repeat("\x00", 12) && $tail !== str_repeat("\x00", 4));
            if ($flat) return $this->checkPublicAddress((string)inet_ntop($tail));
            foreach (self::BLOCKSIX as $net) {
                if ($this->checkNetBlock($bin, $net)) return false;
            }
            foreach (self::ALLOWSIX as $net) {
                if ($this->checkNetBlock($bin, $net)) return true;
            }
            return false;
        }
        foreach (self::BLOCKNET as $net) {
            if ($this->checkNetBlock($bin, $net)) return false;
        }
        return true;
    }

    # Returns whether one packed address falls into one CIDR block of the same family
    private function checkNetBlock(string $bin, string $net): bool {
        [$base, $bits] = explode('/', $net);
        $pack = inet_pton($base);
        if ($pack === false || strlen($pack) !== strlen($bin)) return false;
        $full = intdiv((int)$bits, 8);
        $rest = (int)$bits % 8;
        if ($full > 0 && strncmp($bin, $pack, $full) !== 0) return false;
        if ($rest === 0) return true;
        $mask = chr(0xff << (8 - $rest) & 0xff);
        return ($bin[$full] & $mask) === ($pack[$full] & $mask);
    }

    # Returns whether the address the connection really reached is the address the request was pinned to, which is what closes a rebind between lookup and connect
    private function checkSameAddress(string $peer, string $addr): bool {
        $left = inet_pton($peer);
        $right = inet_pton($addr);
        return $left !== false && $left === $right;
    }

    # Normalizes one root-relative directory and refuses everything that is not a plain relative path: absolute forms, traversal, control bytes and exotic characters
    private function getSafeDir(string $dir): string {
        $dir = str_replace('\\', '/', trim($dir));
        if ($dir === '' || $dir === '.' || str_starts_with($dir, '/') || preg_match('#^[a-zA-Z]:#', $dir)) return '';
        if (preg_match('#[\x00-\x1f]#', $dir) || preg_match('#(^|/)\.\.?(/|$)#', $dir)) return '';
        if (!preg_match('#^[a-zA-Z0-9_./-]+$#', $dir)) return '';
        return rtrim($dir, '/');
    }

    # Resolves one normalized directory to its canonical path and proves it is a writable directory strictly below the upload root, which is what closes symlink escapes
    private function getDestPath(string $dir): string {
        $path = realpath($this->root.'/'.$dir);
        $root = realpath($this->root);
        if ($path === false || $root === false) return '';
        $path = str_replace('\\', '/', $path);
        $root = str_replace('\\', '/', $root);
        if (!str_starts_with($path, rtrim($root, '/').'/')) return '';
        return is_dir($path) && is_writable($path) ? $path : '';
    }

    # Returns the unique partial the transfer writes into; it lives in the final destination, so publication is a rename inside one directory and never crosses a filesystem
    private function getPartPath(string $canon): string {
        return $canon.'/.upload-'.bin2hex(random_bytes(8)).'.part';
    }

    # Publishes a transferred partial: under the destination lock it sweeps stale partials, rechecks quota, draws a free name and renames once
    private function addPublishRun(string $part, array $info, array $rule, string $rel, string $base, ?int $uid): array {
        $canon = dirname($part);
        $name = preg_replace('#[^a-zA-Z0-9_]#', '', $base);
        $size = (int)filesize($part);
        if ($name === '') return $this->getAbortResult(false, $part, $rel, 'destination');
        $lock = $this->getLockHandle($canon);
        if ($lock === false) return $this->getAbortResult(false, $part, $rel, 'destination');
        $this->deleteOldParts($canon, $part, $rel);
        $max = (int)($rule['maxquota'] ?? 0);
        if ($max > 0 && $this->getUsedBytes($canon) + $size > $max) return $this->getAbortResult($lock, $part, $rel, 'quota');
        $code = 'exists';
        for ($i = 0; $i < self::TRIES; $i++) {
            $file = $name.'-'.$this->getNameSalt().($uid === null ? '' : '-'.max(0, $uid)).'.'.$info['ext'];
            if (file_exists($canon.'/'.$file)) continue;
            if (!$this->addPublishFile($part, $canon.'/'.$file)) {
                $code = 'write';
                break;
            }
            $this->deleteLockHandle($lock);
            return [
                'ok' => true,
                'file' => $file,
                'path' => $rel.'/'.$file,
                'size' => $size,
                'mime' => (string)$info['mime'],
                'width' => $info['width'],
                'height' => $info['height'],
                'error' => null,
            ];
        }
        return $this->getAbortResult($lock, $part, $rel, $code);
    }

    # Ends a failed publication: the partial goes, the lock is released and the caller gets the code; a stranded partial is logged rather than assumed away
    private function getAbortResult(mixed $lock, string $part, string $rel, string $code): array {
        $this->deleteUploadPart($part, $rel);
        if ($lock !== false) $this->deleteLockHandle($lock);
        return $this->getFailResult($code);
    }

    # Removes one partial and reports a stranded file to error_file.log with its root-relative path, so the condition stays visible until a later sweep clears it
    private function deleteUploadPart(string $part, string $rel): void {
        if (!is_file($part) || $this->deleteFilePath($part)) return;
        Logger::addFile('error', 'Upload partial could not be deleted', ['path' => $rel.'/'.basename($part)]);
    }

    # Removes the partials of earlier runs that are older than one hour, which is the only place abandoned transfers of this class are collected
    private function deleteOldParts(string $canon, string $keep, string $rel): void {
        $edge = $this->getTime() - self::PARTAGE;
        foreach (scandir($canon) ?: [] as $file) {
            $path = $canon.'/'.$file;
            if (!$this->checkPartName($file) || $path === $keep || !is_file($path)) continue;
            if ((int)filemtime($path) > $edge) continue;
            $this->deleteUploadPart($path, $rel);
        }
    }

    # Returns whether one directory entry is a partial of this class, which is what keeps partials out of the quota and inside the sweep
    private function checkPartName(string $file): bool {
        return str_starts_with($file, '.upload-') && str_ends_with($file, '.part');
    }

    # Sums the published bytes of one destination in constant memory; partials and the directory sentinels are never counted
    private function getUsedBytes(string $canon): int {
        $sum = 0;
        foreach (scandir($canon) ?: [] as $file) {
            $path = $canon.'/'.$file;
            if ($file === '.' || $file === '..' || in_array($file, self::KEEP, true)) continue;
            if ($this->checkPartName($file) || !is_file($path)) continue;
            $sum += max(0, (int)filesize($path));
        }
        return $sum;
    }

    # Takes the exclusive lock of one destination and returns the open handle; every writer serializes on it, which is what makes the existence check and the rename one step
    private function getLockHandle(string $canon): mixed {
        if (!is_dir($this->lockdir) && !mkdir($this->lockdir, 0750, true) && !is_dir($this->lockdir)) return false;
        $lock = fopen($this->lockdir.'/'.substr(sha1($canon), 0, 16).'.lock', 'cb');
        if ($lock === false) return false;
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            return false;
        }
        return $lock;
    }

    # Releases the destination lock; the lock file itself is never deleted, because deleting it would break the lock for a process still holding it
    private function deleteLockHandle(mixed $lock): void {
        if (!is_resource($lock)) return;
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    # Returns the failure shape of one result code; a failure never carries a file, a path or dimensions
    private function getFailResult(string $code): array {
        return ['ok' => false, 'file' => null, 'path' => null, 'size' => 0, 'mime' => null, 'width' => null, 'height' => null, 'error' => $code];
    }
}
