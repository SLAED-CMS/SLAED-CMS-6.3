<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The project has no runtime autoload and this file is required directly by the service accessor and by the probes, so it brings the file layer it depends on with it
if (!class_exists('FileManager')) require_once __DIR__.'/filemanager.php';

# Publishes uploaded files below one upload root: extension, type, image and quota validation, collision-free naming and lock-guarded atomic publication (docs/UPLOAD-2026.md)
# Every result is the same eight-key shape; a failure carries only its machine-readable code and leaves neither a final file nor a partial behind
# The type is read from the content by two readers: the magic database where the build has one and a structural validator per format where it has none
# The clock, the transfer, the random name segment and the two filesystem primitives sit behind protected seams, so the tests reach every branch without an HTTP request
# Production code never subclasses this class and always reaches it through getUploadService()
class Upload {
    private const TRIES = 5;
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
    # The first entry of every row is the canonical type of that format: it is what the structural fallback answers when no magic database is available to name it
    private const TYPES = [
        'gif' => ['image/gif'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'avif' => ['image/avif'],
        'mp3' => ['audio/mpeg'],
        'wav' => ['audio/wav', 'audio/x-wav', 'audio/vnd.wave'],
        'flac' => ['audio/flac', 'audio/x-flac'],
        'ogg' => ['audio/ogg', 'application/ogg'],
        'oga' => ['audio/ogg', 'application/ogg'],
        'opus' => ['audio/ogg', 'application/ogg'],
        'm4a' => ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        'mp4' => ['video/mp4', 'application/mp4'],
        'webm' => ['video/webm', 'audio/webm'],
        'pdf' => ['application/pdf'],
        'zip' => ['application/zip'],
        'rar' => ['application/vnd.rar', 'application/x-rar'],
        'gz' => ['application/gzip', 'application/x-gzip'],
        '7z' => ['application/x-7z-compressed'],
        'tar' => ['application/x-tar', 'application/tar'],
    ];
    # The bounded windows the structural fallback works in: the search for a first audio frame, the header and tail zone of a document, one block walk and one read of a stream
    private const SCAN = 8192;
    private const ZONE = 2048;
    private const BLOCKS = 256;
    private const CHUNK = 65536;
    # The container boxes an ISO base media file declares its tracks in, which is the path the handler search descends
    private const BOXES = ['moov', 'trak', 'mdia', 'minf', 'stbl'];
    # The registered brands an ISO base media file of this project may carry, read as the major brand or as one of the compatible brands that follow it
    private const BRANDS = ['isom', 'iso2', 'iso4', 'iso5', 'iso6', 'mp41', 'mp42', 'mp71', 'avc1', 'dash', 'mmp4', 'M4A ', 'M4B ', 'M4V ', 'MSNV', 'f4v '];
    # The MPEG audio bitrates in kbit per second, by version group and by the layer bits of the frame header; index zero is the free format and is refused before the lookup
    private const MPRATE = [
        'one' => [
            1 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
            2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
            3 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
        ],
        'two' => [
            1 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
            3 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
        ],
    ];
    # The MPEG audio sample rates in hertz, by the version bits of the frame header: zero is MPEG 2.5, two is MPEG 2 and three is MPEG 1
    private const MPFREQ = [0 => [11025, 12000, 8000], 2 => [22050, 24000, 16000], 3 => [44100, 48000, 32000]];

    private string $root;
    private ?finfo $reader = null;
    private array $notes = [];

    # Builds the service over the upload root, which comes from getUploadService(), so no adapter repeats it; where the destination locks live is decided by FileManager alone
    public function __construct(string $root) {
        $this->root = rtrim(str_replace('\\', '/', $root), '/');
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
    public function addUploadedFile(array $file, array $rule, string $dir, string $base, ?string $owner = null): array {
        $code = $this->checkUploadInput($file, $rule);
        if ($code !== '') return $this->getFailResult($code);
        $tmp = (string)$file['tmp_name'];
        $ext = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $code = $this->checkTypePolicy($ext, $rule);
        if ($code !== '') return $this->getFailResult($code);
        $mime = $this->getFileMime($tmp, $ext);
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
        return $this->addPublishRun($part, $info, $rule, $rel, $base, $owner);
    }

    # Publishes a whole batch in submission order, mixed successes and failures included; a batch over maxfiles transfers nothing and reports one count failure
    public function addUploadedFiles(array $files, array $rule, string $dir, string $base, ?string $owner = null): array {
        $list = $this->getUploadList($files);
        if ($list === []) return [$this->getFailResult('missing')];
        $num = (int)($rule['maxfiles'] ?? 0);
        if ($num > 0 && count($list) > $num) return [$this->getFailResult('count')];
        $out = [];
        foreach ($list as $one) $out[] = $this->addUploadedFile($one, $rule, $dir, $base, $owner);
        return $out;
    }

    # Publishes one file fetched from a remote URL; the address policy, the redirects and the byte limits run first, the partial then passes the checks a local upload passes
    public function addRemoteFile(string $url, array $rule, string $dir, string $base, ?string $owner = null): array {
        if ($this->getRuleTypes($rule) === []) return $this->getFailResult('extension');
        if (!function_exists('curl_init')) {
            $this->addCapsNote('curl_missing');
            return $this->getFailResult('unsupported');
        }
        $rel = $this->getSafeDir($dir);
        $canon = ($rel === '') ? '' : $this->getDestPath($rel);
        if ($canon === '') return $this->getFailResult('destination');
        $part = $this->getPartPath($canon);
        $res = $this->addRemoteFetch($url, $part, (int)($rule['maxbytes'] ?? 0));
        if ($res['error'] !== '') return $this->getAbortResult(false, $part, $rel, $res['error']);
        $ext = strtolower((string)pathinfo($res['path'], PATHINFO_EXTENSION));
        $code = $this->checkTypePolicy($ext, $rule);
        if ($code !== '') return $this->getAbortResult(false, $part, $rel, $code);
        $mime = $this->getFileMime($part, $ext);
        if (!in_array($mime, self::TYPES[$ext], true)) return $this->getAbortResult(false, $part, $rel, 'mime');
        $info = $this->getImageBounds($part, $ext, $rule);
        if ($info['error'] !== '') return $this->getAbortResult(false, $part, $rel, $info['error']);
        $info['ext'] = $ext;
        $info['mime'] = $mime;
        return $this->addPublishRun($part, $info, $rule, $rel, $base, $owner);
    }

    # Deletes one file this class published, addressed by the root-relative path a result returned; anything that is not a canonical class-owned name below the root is refused
    # Which names are canonical is answered by FileManager::checkFileName(), the one place in the project that knows the format, so this class carries no pattern of its own
    public function deleteStoredFile(string $path): bool {
        $path = str_replace('\\', '/', $path);
        $file = basename($path);
        $rel = $this->getSafeDir(dirname($path));
        if ($rel === '' || !FileManager::checkFileName($file)) return false;
        $canon = $this->getDestPath($rel);
        if ($canon === '') return false;
        $full = $canon.'/'.$file;
        if (is_link($full) || !is_file($full)) return false;
        $lock = FileManager::getPathLock($canon);
        if ($lock === false) return false;
        $done = $this->deleteFilePath($full);
        FileManager::deletePathLock($lock);
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
        return getRandomString(FileManager::SALTLEN);
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

    # Checks the submitted file against the rule: a malformed shape, a PHP upload error, a source that is not an upload and an oversized file all stop here
    # Nothing about the content is decided here, so a build without a magic database still answers missing, size and transfer before the file is ever read
    private function checkUploadInput(array $file, array $rule): string {
        if ($this->getRuleTypes($rule) === []) return 'extension';
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

    # Returns the type of one file, lowercased, judged from its content and never from a name or a header a client sent; a file that cannot be named returns an empty string
    # The magic database stays the first reader: only when it is absent or cannot name the file does the structural fallback read the format the extension claims
    # A type the database did name is answered unchanged, so a file whose content contradicts its extension is refused by the caller instead of being asked a second question
    private function getFileMime(string $path, string $ext): string {
        $info = $this->getTypeReader();
        $mime = ($info instanceof finfo) ? $info->file($path) : false;
        $mime = is_string($mime) ? strtolower($mime) : '';
        if ($mime !== '' && $mime !== 'application/octet-stream') return $mime;
        return isset(self::TYPES[$ext]) ? $this->getFormatMime($path, $ext) : '';
    }

    # Returns the one type reader of this instance; a build without the extension or without a readable magic database returns null, which is what hands the file to the fallback
    # Protected rather than private so a test can drive a build without the extension while every other native capability of this machine stays available
    protected function getTypeReader(): ?finfo {
        if ($this->reader instanceof finfo) return $this->reader;
        if (!class_exists('finfo')) {
            $this->addCapsNote('fileinfo_missing');
            return null;
        }
        try {
            $this->reader = new finfo(FILEINFO_MIME_TYPE);
        } catch (Throwable) {
            $this->addCapsNote('fileinfo_init_failed');
            return null;
        }
        return $this->reader;
    }

    # Records one missing server capability in error_file.log, once per instance and reason, so an operator can tell a stripped build apart from a rejected file
    private function addCapsNote(string $why): void {
        if (isset($this->notes[$why])) return;
        $this->notes[$why] = true;
        Logger::addFile('error', 'Upload capability missing on this build', ['reason' => $why]);
    }

    # Returns the canonical type of one file whose structure really is the format its extension claims, or an empty string when the structure says otherwise
    # Every validator reads through one handle with bounded seeks and reads, so a file of any size is judged without ever being held in memory
    private function getFormatMime(string $path, string $ext): string {
        $hand = fopen($path, 'rb');
        if ($hand === false) return '';
        $stat = fstat($hand);
        $size = (int)($stat['size'] ?? 0);
        try {
            $done = $this->checkFormatBody($hand, $size, $ext, $path);
        } finally {
            fclose($hand);
        }
        return $done ? self::TYPES[$ext][0] : '';
    }

    # Dispatches one file to the validator of the format its extension claims; a signature alone never answers here, because every one of these formats has a structure to walk
    private function checkFormatBody(mixed $hand, int $size, string $ext, string $path): bool {
        return match ($ext) {
            'gif', 'jpg', 'jpeg', 'png', 'webp', 'avif' => $this->checkImageType($path, $ext),
            'mp3' => $this->checkSoundBody($hand, $size),
            'wav' => $this->checkWaveBody($hand, $size),
            'flac' => $this->checkFlacBody($hand, $size),
            'ogg', 'oga', 'opus' => $this->checkOggBody($hand, $size, $ext),
            'm4a' => $this->checkBoxBody($hand, $size, 'soun'),
            'mp4' => $this->checkBoxBody($hand, $size, 'vide'),
            'webm' => $this->checkWebmBody($hand, $size),
            'pdf' => $this->checkPdfBody($hand, $size),
            'zip' => $this->checkZipBody($hand, $size, $path),
            'rar' => $this->checkRarBody($hand, $size),
            'gz' => $this->checkGzipBody($hand, $size),
            '7z' => $this->checkSevenBody($hand, $size),
            'tar' => $this->checkTarBody($hand, $size),
            default => false,
        };
    }

    # Returns the bytes at one offset of an open file, or an empty string when the file ends before the requested length
    private function getFilePart(mixed $hand, int $from, int $len): string {
        if ($len < 1 || $from < 0 || fseek($hand, $from) !== 0) return '';
        $data = fread($hand, $len);
        return (is_string($data) && strlen($data) === $len) ? $data : '';
    }

    # Returns the CRC-32 of one range of an open file, read in bounded chunks, which is what the archive trailers are compared against
    private function getFileSum(mixed $hand, int $from, int $len): int {
        if ($len < 0 || fseek($hand, $from) !== 0) return -1;
        $sum = hash_init('crc32b');
        while ($len > 0) {
            $part = fread($hand, min(self::CHUNK, $len));
            if (!is_string($part) || $part === '') return -1;
            hash_update($sum, $part);
            $len -= strlen($part);
        }
        return (int)hexdec(hash_final($sum));
    }

    # Proves one image really carries the format its extension claims, which is the type marker the header reader answers for it
    # The configured bounds and the decode that proves the pixel data follow in getImageBounds(), so this half answers the type question and never opens a second decoder
    private function checkImageType(string $path, string $ext): bool {
        $info = $this->getImageInfo($path);
        if ($info === []) return false;
        $want = match ($ext) {
            'gif' => IMAGETYPE_GIF,
            'jpg', 'jpeg' => IMAGETYPE_JPEG,
            'png' => IMAGETYPE_PNG,
            'webp' => IMAGETYPE_WEBP,
            'avif' => IMAGETYPE_AVIF,
            default => 0,
        };
        return $want !== 0 && $info[2] === $want;
    }

    # Proves one MPEG audio file: an ID3v2 tag is skipped by its own synchsafe size, and the stream that follows has to carry two consecutive frames of one and the same format
    private function checkSoundBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, 10);
        if ($head === '') return false;
        $from = 0;
        if (str_starts_with($head, 'ID3')) {
            if (ord($head[3]) > 4 || ord($head[4]) === 0xff) return false;
            $len = 0;
            for ($i = 6; $i < 10; $i++) {
                if (ord($head[$i]) > 0x7f) return false;
                $len = ($len << 7) | ord($head[$i]);
            }
            $from = 10 + $len + ((ord($head[5]) & 0x10) ? 10 : 0);
        }
        return $this->checkSoundSync($hand, $size, $from);
    }

    # Finds the first frame header in a bounded window and requires the frame it announces to be followed by a second one of the same version, layer and sample rate
    private function checkSoundSync(mixed $hand, int $size, int $from): bool {
        $scan = $this->getFilePart($hand, $from, min(self::SCAN, max(0, $size - $from)));
        $len = strlen($scan);
        for ($i = 0; $i + 4 <= $len; $i++) {
            if ($scan[$i] !== "\xff") continue;
            $head = substr($scan, $i, 4);
            $step = $this->getSoundSize($head);
            if ($step < 1) continue;
            $next = $this->getFilePart($hand, $from + $i + $step, 4);
            if ($next === '' || $this->getSoundSize($next) < 1 || $head[1] !== $next[1]) continue;
            if ((ord($head[2]) & 0x0c) === (ord($next[2]) & 0x0c)) return true;
        }
        return false;
    }

    # Returns the byte length of one MPEG audio frame from its four header bytes, or zero when those bytes are not a frame header at all
    private function getSoundSize(string $head): int {
        if (strlen($head) < 4 || ord($head[0]) !== 0xff || (ord($head[1]) & 0xe0) !== 0xe0) return 0;
        $vers = (ord($head[1]) >> 3) & 0x03;
        $lay = (ord($head[1]) >> 1) & 0x03;
        $rate = (ord($head[2]) >> 4) & 0x0f;
        $freq = (ord($head[2]) >> 2) & 0x03;
        $pad = (ord($head[2]) >> 1) & 0x01;
        if ($vers === 1 || $lay === 0 || $rate === 0 || $rate === 15 || $freq === 3) return 0;
        $bits = self::MPRATE[($vers === 3) ? 'one' : 'two'][$lay][$rate] * 1000;
        $freq = self::MPFREQ[$vers][$freq];
        if ($lay === 3) return intdiv(12 * $bits, $freq) * 4 + $pad * 4;
        return intdiv((($lay === 1 && $vers !== 3) ? 72 : 144) * $bits, $freq) + $pad;
    }

    # Proves one RIFF wave file: the declared size stays inside the file and the chunk chain it announces really carries a format block and a data block
    private function checkWaveBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, 12);
        if ($head === '' || !str_starts_with($head, 'RIFF') || substr($head, 8, 4) !== 'WAVE') return false;
        $stop = (int)unpack('V', substr($head, 4, 4))[1] + 8;
        if ($stop < 20 || $stop > $size) return false;
        $from = 12;
        $seen = ['fmt ' => false, 'data' => false];
        while ($from + 8 <= $stop) {
            $part = $this->getFilePart($hand, $from, 8);
            if ($part === '' || preg_match('#^[\x20-\x7e]{4}$#', substr($part, 0, 4)) !== 1) return false;
            $len = (int)unpack('V', substr($part, 4, 4))[1];
            $name = substr($part, 0, 4);
            if ($len < 0 || $from + 8 + $len > $stop) return false;
            if ($name === 'fmt ' && $len >= 16) $seen['fmt '] = true;
            if ($name === 'data') $seen['data'] = true;
            $from += 8 + $len + ($len % 2);
        }
        return $seen['fmt '] && $seen['data'];
    }

    # Proves one FLAC stream: the first metadata block is the mandatory stream information of exactly 34 bytes, and the block chain ends with its last block inside the file
    private function checkFlacBody(mixed $hand, int $size): bool {
        if ($this->getFilePart($hand, 0, 4) !== 'fLaC') return false;
        $from = 4;
        for ($i = 0; $i < self::BLOCKS; $i++) {
            $head = $this->getFilePart($hand, $from, 4);
            if ($head === '') return false;
            $type = ord($head[0]) & 0x7f;
            $len = (ord($head[1]) << 16) | (ord($head[2]) << 8) | ord($head[3]);
            if ($i === 0 && ($type !== 0 || $len !== 34)) return false;
            if ($type === 127 || $from + 4 + $len > $size) return false;
            $from += 4 + $len;
            if (ord($head[0]) & 0x80) return $from <= $size;
        }
        return false;
    }

    # Proves the first page of one Ogg stream: the page fits the file, its own checksum holds, and its first packet announces one of the three codecs this project accepts
    private function checkOggBody(mixed $hand, int $size, string $ext): bool {
        $head = $this->getFilePart($hand, 0, 27);
        if ($head === '' || !str_starts_with($head, 'OggS') || ord($head[4]) !== 0) return false;
        $segs = ord($head[26]);
        $table = ($segs > 0) ? $this->getFilePart($hand, 27, $segs) : '';
        if ($table === '') return false;
        $full = 27 + $segs;
        for ($i = 0; $i < $segs; $i++) $full += ord($table[$i]);
        $page = ($full <= $size) ? $this->getFilePart($hand, 0, $full) : '';
        if ($page === '') return false;
        $want = (int)unpack('V', substr($page, 22, 4))[1];
        if ($this->getOggSum(substr_replace($page, "\x00\x00\x00\x00", 22, 4)) !== $want) return false;
        $data = substr($page, 27 + $segs, ord($table[0]));
        if ($ext === 'opus') return str_starts_with($data, 'OpusHead');
        return str_starts_with($data, "\x01vorbis") || str_starts_with($data, 'OpusHead') || str_starts_with($data, "\x7fFLAC");
    }

    # Returns the page checksum of one Ogg page: a CRC-32 over the Ogg polynomial, without the input and output reflection the common one uses
    private function getOggSum(string $page): int {
        static $tab = [];
        if ($tab === []) {
            for ($i = 0; $i < 256; $i++) {
                $val = $i << 24;
                for ($j = 0; $j < 8; $j++) $val = (($val & 0x80000000) ? (($val << 1) ^ 0x04c11db7) : ($val << 1)) & 0xffffffff;
                $tab[$i] = $val;
            }
        }
        $sum = 0;
        $len = strlen($page);
        for ($i = 0; $i < $len; $i++) $sum = ((($sum << 8) & 0xffffffff) ^ $tab[(($sum >> 24) & 0xff) ^ ord($page[$i])]);
        return $sum;
    }

    # Proves one ISO base media file: the first box is a file type box of an accepted brand, every top level box ends where the next begins, and the wanted track is declared
    # Which handler is demanded separates the two extensions of this one container: a sound track is an m4a and a picture track is an mp4, which is what their canonical types say
    private function checkBoxBody(mixed $hand, int $size, string $want): bool {
        $head = $this->getFilePart($hand, 0, 8);
        if ($head === '' || substr($head, 4, 4) !== 'ftyp') return false;
        $len = (int)unpack('N', substr($head, 0, 4))[1];
        if ($len < 16 || $len > $size || $len % 4 !== 0) return false;
        if (!$this->checkBoxBrand($this->getFilePart($hand, 8, min($len - 8, 256)))) return false;
        $from = 0;
        $seen = false;
        for ($i = 0; $i < self::BLOCKS; $i++) {
            $box = $this->getBoxHead($hand, $from, $size);
            if ($box === []) return false;
            if ($box['type'] === 'moov') $seen = $seen || $this->checkBoxTrack($hand, $from + $box['head'], $from + $box['size'], $want, 0);
            $from += $box['size'];
            if ($from === $size) return $seen;
        }
        return false;
    }

    # Returns whether the major brand of one file type box or one of the compatible brands behind it is a brand this project accepts
    private function checkBoxBrand(string $data): bool {
        foreach (str_split(substr($data, 0, intdiv(strlen($data), 4) * 4), 4) as $key => $one) {
            if ($key !== 1 && in_array($one, self::BRANDS, true)) return true;
        }
        return false;
    }

    # Returns the size, the type and the header length of the box at one offset, or an empty array when the box does not fit the range it sits in
    private function getBoxHead(mixed $hand, int $from, int $stop): array {
        $head = $this->getFilePart($hand, $from, 8);
        if ($head === '' || preg_match('#^[\x20-\x7e]{4}$#', substr($head, 4, 4)) !== 1) return [];
        $len = (int)unpack('N', substr($head, 0, 4))[1];
        $wide = 8;
        if ($len === 1) {
            $more = $this->getFilePart($hand, $from + 8, 8);
            if ($more === '') return [];
            $len = (int)unpack('J', $more)[1];
            $wide = 16;
        }
        if ($len === 0) $len = $stop - $from;
        if ($len < $wide || $from + $len > $stop) return [];
        return ['size' => $len, 'type' => substr($head, 4, 4), 'head' => $wide];
    }

    # Walks the container boxes of one movie box and reports whether a track of the wanted handler type is declared inside it
    private function checkBoxTrack(mixed $hand, int $from, int $stop, string $want, int $deep): bool {
        if ($deep > 4) return false;
        while ($from + 8 <= $stop) {
            $box = $this->getBoxHead($hand, $from, $stop);
            if ($box === []) return false;
            if ($box['type'] === 'hdlr' && $this->getFilePart($hand, $from + $box['head'] + 8, 4) === $want) return true;
            if (in_array($box['type'], self::BOXES, true) && $this->checkBoxTrack($hand, $from + $box['head'], $from + $box['size'], $want, $deep + 1)) return true;
            $from += $box['size'];
        }
        return false;
    }

    # Proves one WebM file: the EBML header parses element by element, ends exactly where it said it would, declares the webm document type and is followed by a segment
    private function checkWebmBody(mixed $hand, int $size): bool {
        if ($this->getFilePart($hand, 0, 4) !== "\x1a\x45\xdf\xa3") return false;
        $head = $this->getEbmlSize($hand, 4);
        if ($head === [] || $head['all'] || $head['size'] < 1) return false;
        $from = $head['next'];
        $stop = $from + $head['size'];
        if ($stop > $size) return false;
        $type = '';
        while ($from < $stop) {
            $one = $this->getEbmlHead($hand, $from, $stop);
            if ($one === []) return false;
            if ($one['id'] === '4282') $type = trim($this->getFilePart($hand, $one['next'], min($one['size'], 16)), "\x00");
            $from = $one['next'] + $one['size'];
        }
        if ($from !== $stop || $type !== 'webm') return false;
        $seg = $this->getEbmlHead($hand, $stop, $size);
        return $seg !== [] && $seg['id'] === '18538067';
    }

    # Returns the identifier, the payload length and the payload offset of the EBML element at one offset, or an empty array when it does not fit the range it sits in
    private function getEbmlHead(mixed $hand, int $from, int $stop): array {
        $lead = $this->getFilePart($hand, $from, 1);
        if ($lead === '') return [];
        $wide = $this->getEbmlWide(ord($lead));
        $id = ($wide > 0 && $wide < 5) ? $this->getFilePart($hand, $from, $wide) : '';
        if ($id === '') return [];
        $len = $this->getEbmlSize($hand, $from + $wide);
        if ($len === []) return [];
        $size = $len['all'] ? $stop - $len['next'] : $len['size'];
        if ($size < 0 || $len['next'] + $size > $stop) return [];
        return ['id' => strtolower(bin2hex($id)), 'size' => $size, 'next' => $len['next']];
    }

    # Returns the value, the end offset and the all-ones marker of the variable length integer at one offset, with its own length bit removed
    private function getEbmlSize(mixed $hand, int $from): array {
        $lead = $this->getFilePart($hand, $from, 1);
        if ($lead === '') return [];
        $wide = $this->getEbmlWide(ord($lead));
        $data = ($wide > 0) ? $this->getFilePart($hand, $from, $wide) : '';
        if ($data === '') return [];
        $val = ord($data[0]) & (0xff >> $wide);
        $ones = $val === (0xff >> $wide);
        for ($i = 1; $i < $wide; $i++) {
            $ones = $ones && ord($data[$i]) === 0xff;
            $val = ($val << 8) | ord($data[$i]);
        }
        return ['size' => $val, 'next' => $from + $wide, 'all' => $ones];
    }

    # Returns how many bytes one variable length integer occupies, which its leading byte announces as the position of its highest set bit
    private function getEbmlWide(int $byte): int {
        for ($i = 0; $i < 8; $i++) {
            if ($byte >= (0x80 >> $i)) return $i + 1;
        }
        return 0;
    }

    # Proves one PDF document: a version header of a released revision in the leading zone and the end of file marker in the trailing zone
    private function checkPdfBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, min($size, self::ZONE));
        $pos = ($head === '') ? false : strpos($head, '%PDF-');
        if ($pos === false || preg_match('#^%PDF-(1\.[0-7]|2\.0)#', substr($head, $pos, 10)) !== 1) return false;
        $tail = $this->getFilePart($hand, max(0, $size - self::ZONE), min($size, self::ZONE));
        return $tail !== '' && str_contains($tail, '%%EOF');
    }

    # Proves one zip archive: the end of the central directory sits where the file ends, the directory holds the announced records, and every record addresses a local header
    # Where the extension owning this format is available it has to open the archive as well, because it reads the fields this walk deliberately does not
    private function checkZipBody(mixed $hand, int $size, string $path): bool {
        $tail = $this->getFilePart($hand, max(0, $size - 65557), min($size, 65557));
        $pos = ($tail === '') ? false : strrpos($tail, "PK\x05\x06");
        if ($pos === false) return false;
        $from = $size - strlen($tail) + $pos;
        $end = $this->getFilePart($hand, $from, 22);
        if ($end === '' || $from + 22 + (int)unpack('v', substr($end, 20, 2))[1] !== $size) return false;
        $disk = (int)unpack('v', substr($end, 4, 2))[1] + (int)unpack('v', substr($end, 6, 2))[1];
        $here = (int)unpack('v', substr($end, 8, 2))[1];
        $num = (int)unpack('v', substr($end, 10, 2))[1];
        $len = (int)unpack('V', substr($end, 12, 4))[1];
        $off = (int)unpack('V', substr($end, 16, 4))[1];
        if ($disk !== 0 || $here !== $num || $off + $len > $from) return false;
        if ($num === 0xffff || $len === 0xffffffff || $off === 0xffffffff) return $this->checkZipReader($path, true);
        return $this->checkZipParts($hand, $off, $num, $off + $len) && $this->checkZipReader($path, false);
    }

    # Walks the central directory records of one archive and proves each of them addresses a local header of the same archive
    # Only the first records of a very large directory are walked, because the archive is proven whole by the extension that owns the format wherever it is available
    private function checkZipParts(mixed $hand, int $from, int $num, int $stop): bool {
        $seen = min($num, self::BLOCKS);
        for ($i = 0; $i < $seen; $i++) {
            $head = $this->getFilePart($hand, $from, 46);
            if ($head === '' || !str_starts_with($head, "PK\x01\x02")) return false;
            $name = (int)unpack('v', substr($head, 28, 2))[1];
            $more = (int)unpack('v', substr($head, 30, 2))[1] + (int)unpack('v', substr($head, 32, 2))[1];
            $off = (int)unpack('V', substr($head, 42, 4))[1];
            if ($this->getFilePart($hand, $off, 4) !== "PK\x03\x04") return false;
            $from += 46 + $name + $more;
            if ($from > $stop) return false;
        }
        return $seen < $num || $from === $stop;
    }

    # Returns whether the archive extension accepts this file, or whether its absence is allowed to answer for it; a form only that extension can read is refused without it
    private function checkZipReader(string $path, bool $must): bool {
        if (!class_exists('ZipArchive')) return !$must;
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return false;
        $zip->close();
        return true;
    }

    # Proves one RAR archive: the marker names the generation, and the block chain of that generation is walked with every announced size kept inside the file
    private function checkRarBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, 8);
        if ($head === '') return false;
        if (str_starts_with($head, "Rar!\x1a\x07\x00")) return $this->checkRarFour($hand, $size, 7);
        return str_starts_with($head, "Rar!\x1a\x07\x01\x00") && $this->checkRarFive($hand, $size, 8);
    }

    # Walks the fixed block headers of a RAR 4 archive, whose first block has to be the archive header itself
    private function checkRarFour(mixed $hand, int $size, int $from): bool {
        for ($i = 0; $i < self::BLOCKS; $i++) {
            $head = $this->getFilePart($hand, $from, 7);
            if ($head === '' || ($i === 0 && ord($head[2]) !== 0x73)) return false;
            $flag = (int)unpack('v', substr($head, 3, 2))[1];
            $step = (int)unpack('v', substr($head, 5, 2))[1];
            if ($step < 7) return false;
            if ($flag & 0x8000) {
                $more = $this->getFilePart($hand, $from + 7, 4);
                if ($more === '') return false;
                $step += (int)unpack('V', $more)[1];
            }
            $from += $step;
            if ($from > $size) return false;
            if ($from === $size) return true;
        }
        return true;
    }

    # Walks the variable length block headers of a RAR 5 archive, whose first block has to be the main archive header
    private function checkRarFive(mixed $hand, int $size, int $from): bool {
        for ($i = 0; $i < self::BLOCKS; $i++) {
            $head = $this->getFilePart($hand, $from + 4, min(self::ZONE, max(0, $size - $from - 4)));
            $len = ($head === '') ? [] : $this->getRarNum($head, 0);
            if ($len === [] || $len['size'] < 1) return false;
            $step = $this->getRarStep($head, $len, $i);
            if ($step < 1 || $from + 4 + $len['wide'] + $step > $size) return false;
            $from += 4 + $len['wide'] + $step;
            if ($from === $size) return true;
        }
        return true;
    }

    # Returns how many bytes of one RAR 5 block follow its header length field: the header itself and the data area it announces, or zero when the block is not readable
    private function getRarStep(string $head, array $len, int $key): int {
        $from = $len['wide'];
        $type = $this->getRarNum($head, $from);
        $flag = ($type === []) ? [] : $this->getRarNum($head, $from + $type['wide']);
        if ($flag === [] || ($key === 0 && $type['size'] !== 1)) return 0;
        $from += $type['wide'] + $flag['wide'];
        if ($flag['size'] & 0x01) {
            $more = $this->getRarNum($head, $from);
            if ($more === []) return 0;
            $from += $more['wide'];
        }
        $data = ($flag['size'] & 0x02) ? $this->getRarNum($head, $from) : ['size' => 0];
        return ($data === []) ? 0 : $len['size'] + $data['size'];
    }

    # Returns the value and the width of the RAR 5 variable length integer at one offset of a byte string, which carries seven bits per byte, lowest first
    private function getRarNum(string $data, int $from): array {
        $len = strlen($data);
        $val = 0;
        for ($i = 0; $i < 10 && $from + $i < $len; $i++) {
            $one = ord($data[$from + $i]);
            $val |= ($one & 0x7f) << ($i * 7);
            if (($one & 0x80) === 0) return ['size' => $val, 'wide' => $i + 1];
        }
        return [];
    }

    # Proves one gzip member: the header with every optional field it announces, and where the compression library is present the stream itself against its own checksum and length
    private function checkGzipBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, 10);
        if ($head === '' || !str_starts_with($head, "\x1f\x8b\x08")) return false;
        $flag = ord($head[3]);
        if ($flag & 0xe0) return false;
        $from = 10;
        if ($flag & 0x04) {
            $more = $this->getFilePart($hand, $from, 2);
            if ($more === '') return false;
            $from += 2 + (int)unpack('v', $more)[1];
        }
        if ($flag & 0x08) $from = $this->getTextStop($hand, $from, $size);
        if ($flag & 0x10) $from = $this->getTextStop($hand, $from, $size);
        if ($flag & 0x02) $from += 2;
        if ($from < 10 || $from + 8 > $size) return false;
        return !function_exists('inflate_init') || $this->checkGzipFlow($hand, $size, $from);
    }

    # Returns the offset behind the zero terminated string at one offset, or minus one when the file ends before its terminator
    private function getTextStop(mixed $hand, int $from, int $size): int {
        while ($from > -1 && $from < $size) {
            $part = $this->getFilePart($hand, $from, min(self::CHUNK, $size - $from));
            $stop = ($part === '') ? false : strpos($part, "\x00");
            if ($stop !== false) return $from + $stop + 1;
            if ($part === '') return -1;
            $from += strlen($part);
        }
        return -1;
    }

    # Decompresses one gzip member in bounded chunks and holds it to the checksum and the uncompressed length its own trailer announces
    private function checkGzipFlow(mixed $hand, int $size, int $from): bool {
        $tail = $this->getFilePart($hand, $size - 8, 8);
        $flow = ($tail === '') ? false : inflate_init(ZLIB_ENCODING_RAW);
        if ($flow === false || fseek($hand, $from) !== 0) return false;
        $sum = hash_init('crc32b');
        $stop = $size - 8;
        $len = 0;
        set_error_handler(static fn(): bool => true);
        try {
            while ($from < $stop) {
                $part = fread($hand, min(self::CHUNK, $stop - $from));
                if (!is_string($part) || $part === '') return false;
                $from += strlen($part);
                $out = inflate_add($flow, $part, ($from >= $stop) ? ZLIB_FINISH : ZLIB_NO_FLUSH);
                if ($out === false) return false;
                hash_update($sum, $out);
                $len += strlen($out);
            }
        } finally {
            restore_error_handler();
        }
        if (inflate_get_status($flow) !== ZLIB_STREAM_END) return false;
        $want = (int)unpack('V', substr($tail, 0, 4))[1];
        return (int)hexdec(hash_final($sum)) === $want && ($len & 0xffffffff) === (int)unpack('V', substr($tail, 4, 4))[1];
    }

    # Proves one 7z archive: the start header holds against its own checksum, and the next header it addresses lies inside the file and holds against its checksum as well
    private function checkSevenBody(mixed $hand, int $size): bool {
        $head = $this->getFilePart($hand, 0, 32);
        if ($head === '' || !str_starts_with($head, "7z\xbc\xaf\x27\x1c") || ord($head[6]) !== 0) return false;
        if (crc32(substr($head, 12, 20)) !== (int)unpack('V', substr($head, 8, 4))[1]) return false;
        $off = (int)unpack('P', substr($head, 12, 8))[1];
        $len = (int)unpack('P', substr($head, 20, 8))[1];
        $sum = (int)unpack('V', substr($head, 28, 4))[1];
        if ($off < 0 || $len < 0 || 32 + $off + $len > $size) return false;
        if ($len === 0) return $off === 0 && $sum === 0;
        return $this->getFileSum($hand, 32 + $off, $len) === $sum;
    }

    # Proves one tar archive: every header block holds against its own checksum, its data is padded to the block size, and the chain ends with the two zero blocks the format needs
    private function checkTarBody(mixed $hand, int $size): bool {
        if ($size < 1536 || $size % 512 !== 0) return false;
        $from = 0;
        $seen = 0;
        while ($seen < self::BLOCKS && $from + 512 <= $size) {
            $head = $this->getFilePart($hand, $from, 512);
            if ($head === '') return false;
            if (trim($head, "\x00") === '') break;
            $len = $this->checkTarHead($head) ? $this->getTarNum(substr($head, 124, 12)) : -1;
            if ($len < 0) return false;
            $from += 512 + intdiv($len + 511, 512) * 512;
            $seen++;
        }
        if ($seen < 1 || $from + 1024 > $size) return false;
        return trim($this->getFilePart($hand, $from, 1024), "\x00") === '';
    }

    # Returns whether one tar header block holds against the checksum it carries, which is the sum of its own bytes with the checksum field read as spaces
    private function checkTarHead(string $head): bool {
        $want = $this->getTarNum(substr($head, 148, 8));
        if ($want < 1 || $head[0] === "\x00") return false;
        $sum = 0;
        for ($i = 0; $i < 512; $i++) $sum += ($i > 147 && $i < 156) ? 32 : ord($head[$i]);
        return $sum === $want;
    }

    # Returns the value of one octal tar header field, or minus one when the field is not a plain octal number
    private function getTarNum(string $data): int {
        $data = trim($data, " \x00");
        if ($data === '') return 0;
        return (preg_match('#^[0-7]+$#', $data) === 1) ? (int)octdec($data) : -1;
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
        if ($call === '' || !function_exists($call)) {
            $this->addCapsNote('decoder_missing');
            return 'unsupported';
        }
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
    # The owner segment carries the token the caller owns the file by, reduced to the characters the name grammar allows; null and a token reducing to nothing write no segment
    private function addPublishRun(string $part, array $info, array $rule, string $rel, string $base, ?string $owner): array {
        $canon = dirname($part);
        $name = preg_replace('#[^a-zA-Z0-9_]#', '', $base);
        $own = ($owner === null) ? '' : preg_replace('#[^a-zA-Z0-9]#', '', $owner);
        $size = (int)filesize($part);
        if ($name === '') return $this->getAbortResult(false, $part, $rel, 'destination');
        $lock = FileManager::getPathLock($canon);
        if ($lock === false) return $this->getAbortResult(false, $part, $rel, 'destination');
        $this->deleteOldParts($canon, $part, $rel);
        $max = (int)($rule['maxquota'] ?? 0);
        if ($max > 0 && $this->getUsedBytes($canon) + $size > $max) return $this->getAbortResult($lock, $part, $rel, 'quota');
        $code = 'exists';
        for ($i = 0; $i < self::TRIES; $i++) {
            $file = $name.'-'.$this->getNameSalt().($own === '' ? '' : '-'.$own).'.'.$info['ext'];
            if (file_exists($canon.'/'.$file)) continue;
            if (!$this->addPublishFile($part, $canon.'/'.$file)) {
                $code = 'write';
                break;
            }
            FileManager::deletePathLock($lock);
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
        if ($lock !== false) FileManager::deletePathLock($lock);
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

    # Returns the failure shape of one result code; a failure never carries a file, a path or dimensions
    private function getFailResult(string $code): array {
        return ['ok' => false, 'file' => null, 'path' => null, 'size' => 0, 'mime' => null, 'width' => null, 'height' => null, 'error' => $code];
    }
}
