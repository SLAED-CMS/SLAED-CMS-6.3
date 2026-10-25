<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Reads one filesystem area below one server-chosen root: path canonicalization, the path policy, the descriptor and the capabilities
class FileManager {
    public const MODES = ['uploads', 'editor', 'system'];
    # Every capability name a context answers, so both screens read one shape instead of guessing which keys their mode carries
    public const GRANTS = ['browse', 'preview', 'upload', 'download', 'insert', 'embed', 'edit', 'create', 'mkdir', 'rename', 'copy', 'move', 'delete', 'compress'];
    # The operations the path policy answers; the compound ones are asked as two of these, once per side, because rename, copy and move touch two paths
    public const OPS = ['list', 'read', 'download', 'write', 'delete'];
    # The length of the random segment of a managed name; the upload service draws its names by it, because the format of a stored name belongs to the file layer
    public const SALTLEN = 10;
    # The managed name format itself: the sanitized base, the random segment and the optional owner token, captured so one pattern answers both questions asked of a name
    private const NAMEPAT = '#^[a-zA-Z0-9_]+-[a-zA-Z0-9]{'.self::SALTLEN.'}(?:-([a-zA-Z0-9]+))?\.[a-zA-Z0-9]+$#';
    # Two megabytes of source is far past every shipped template and stylesheet, and a code editor handed more than that stops being usable in a browser
    private const MAXEDIT = 2097152;
    private const LANGS = [
        'php' => 'php', 'html' => 'html', 'htm' => 'html', 'tpl' => 'html', 'css' => 'css', 'js' => 'js', 'json' => 'json',
        'sql' => 'sql', 'xml' => 'xml', 'txt' => 'text', 'md' => 'markdown', 'ini' => 'ini', 'log' => 'text',
    ];
    private const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'];
    private const AUDIOS = ['mp3', 'wav', 'flac', 'ogg', 'oga', 'opus', 'm4a'];
    private const VIDEOS = ['mp4', 'webm'];
    private const PACKS = ['zip', 'rar', 'gz', '7z', 'tar'];
    # The file whose loss stops the site at the project level, the rewrite of the project; the document root adds its own entries, see checkCritPath()
    private const CRIT = ['.htaccess'];
    private const CRITDIR = ['config', 'core'];

    # The directory locks this request holds: canonical key => the open handle and the number of entries that took it
    private static array $held = [];
    private string $mode = '';
    private string $root = '';
    private array $flags = [];

    # Build one context over a server-chosen root, closed for an unknown mode; an empty root is refused first, as realpath() answers the working directory for it
    public function __construct(string $mode, string $root, array $flags = []) {
        $path = ($root !== '' && in_array($mode, self::MODES, true)) ? realpath($root) : false;
        if ($path === false) return;
        $this->mode = $mode;
        $this->root = rtrim(str_replace('\\', '/', $path), '/');
        $this->flags = $flags;
    }

    # List one directory as descriptors, directories first; a hidden entry is dropped here and refused again on a direct request, as hiding is no protection
    public function getFileList(string $dir): array {
        $pair = $this->getPathPair($dir);
        if ($pair['full'] === '' || !is_dir($pair['full']) || !$this->checkFileAccess($dir, 'list')) return [];
        $out = [];
        foreach (scandir($pair['full']) ?: [] as $file) {
            if ($file === '.' || $file === '..') continue;
            $rel = ($pair['rel'] === '') ? $file : $pair['rel'].'/'.$file;
            $one = $this->getFileData($rel);
            if ($one !== []) $out[] = $one;
        }
        usort($out, static fn(array $one, array $two): int => [$one['kind'] !== 'dir', $one['name']] <=> [$two['kind'] !== 'dir', $two['name']]);
        return $out;
    }

    # One object as its descriptor or empty; existence is asked again after realpath(), whose cache outlives a removed object, and the editor never gets realpath
    public function getFileData(string $path): array {
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '' || !file_exists($pair['full'])) return [];
        $rule = $this->getPathRules($pair['rel']);
        if (!$rule['list']) return [];
        $isdir = is_dir($pair['full']);
        $ext = $isdir ? '' : strtolower(pathinfo($pair['full'], PATHINFO_EXTENSION));
        $kind = self::getFileKind($isdir, $ext);
        $dims = $this->getImageBounds($pair['full'], $kind, $ext);
        $prev = in_array($kind, ['image', 'audio', 'video', 'document'], true);
        $edit = $this->isFileEditable($pair['rel']);
        $data = [
            'name' => ($pair['rel'] === '') ? basename($this->root) : basename($pair['rel']),
            'path' => $pair['rel'],
            'kind' => $kind,
            'extension' => $ext,
            'size' => $isdir ? 0 : max(0, (int)filesize($pair['full'])),
            'mtime' => max(0, (int)filemtime($pair['full'])),
            'url' => $this->getFileLink($pair['full']),
            'thumbnail' => ($kind === 'image') ? $this->getThumbLink($pair['full']) : '',
            'width' => $dims['width'],
            'height' => $dims['height'],
            'perms' => substr(sprintf('%o', fileperms($pair['full'])), -4),
            'owner' => $this->getFileUser($pair['full']),
            'managed' => !$isdir && self::checkFileName($pair['rel']),
            'editable' => $edit,
            'previewable' => $prev,
            'capabilities' => $this->getFileGrants($rule, $kind, $prev, $edit),
        ];
        if ($this->mode !== 'editor') $data['realpath'] = $pair['full'];
        if ($this->mode === 'system') $data['critical'] = $this->checkCritPath($pair['rel']);
        return $data;
    }

    # What this context allows at all, which the interface prints instead of deriving a permission of its own; a closed instance answers everything false
    public function getCapabilities(): array {
        $none = array_fill_keys(self::GRANTS, false);
        $canup = !empty($this->flags['upload']);
        $canlist = !empty($this->flags['list']);
        $moder = !empty($this->flags['moder']);
        return match ($this->mode) {
            'uploads' => array_merge($none, ['browse' => true, 'preview' => true, 'upload' => true, 'download' => true, 'mkdir' => true,
                'rename' => true, 'copy' => true, 'move' => true, 'delete' => true, 'compress' => true]),
            'editor' => array_merge($none, ['browse' => $canlist, 'preview' => true, 'upload' => $canup, 'download' => true, 'insert' => true,
                'embed' => !empty($this->flags['embed']), 'delete' => $moder, 'compress' => $moder]),
            'system' => array_merge($none, ['browse' => true, 'preview' => true, 'download' => true, 'edit' => true, 'create' => true,
                'mkdir' => true, 'rename' => true, 'copy' => true, 'move' => true, 'delete' => true, 'compress' => true]),
            default => $none,
        };
    }

    # Answer whether one operation is allowed on one path before it is opened; two paths ask once per side, or a closed file copies itself into an open one
    public function checkFileAccess(string $path, string $op): bool {
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '' || !in_array($op, self::OPS, true)) return false;
        return $this->getPathRules($pair['rel'])[$op];
    }

    # Answer whether one file opens in the source editor; write is not asked on purpose, as a write-closed file still opens to be read
    public function isFileEditable(string $path): bool {
        if (empty($this->getCapabilities()['edit']) || $this->getCodeLanguage($path) === '') return false;
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '' || !is_file($pair['full']) || !is_readable($pair['full']) || !$this->checkFileAccess($path, 'read')) return false;
        return (int)filesize($pair['full']) <= self::MAXEDIT;
    }

    # Returns the highlighting mode of one path for the source editor, or an empty string for everything that is not text, which is what keeps a binary out of the editor
    public function getCodeLanguage(string $path): string {
        $path = strtolower(str_replace('\\', '/', $path));
        if (basename($path) === 'robots.txt') return 'robots';
        return self::LANGS[pathinfo($path, PATHINFO_EXTENSION)] ?? '';
    }

    # The source of one text file with the digest of its bytes as the version the save hands back; a body with a NUL byte is a binary, not source
    public function getFileBody(string $path): array {
        if (!$this->isFileEditable($path)) return [];
        $text = file_get_contents($this->getPathPair($path)['full']);
        if ($text === false || str_contains($text, "\0")) return [];
        return ['text' => $text, 'version' => sha1($text), 'lines' => ($text === '') ? 0 : substr_count($text, "\n") + (str_ends_with($text, "\n") ? 0 : 1)];
    }

    # Write one source file back; the version is compared inside the directory lock, never beside it, and the body keeps the line separator of the file on disk
    public function setFileBody(string $path, string $text, string $ver): array {
        $pair = $this->getPathPair($path);
        $shut = ['ok' => false, 'error' => 'closed', 'version' => ''];
        if ($pair['full'] === '' || !$this->isFileEditable($pair['rel']) || !$this->checkFileAccess($pair['rel'], 'write')) return $shut;
        if (str_contains($text, "\0") || strlen($text) > self::MAXEDIT) return ['ok' => false, 'error' => 'body', 'version' => ''];
        $lock = self::getPathLock(dirname($pair['full']));
        if ($lock === false) return ['ok' => false, 'error' => 'lock', 'version' => ''];
        $now = file_get_contents($pair['full']);
        if ($now === false) {
            self::deletePathLock($lock);
            return ['ok' => false, 'error' => 'read', 'version' => ''];
        }
        if (!hash_equals(sha1($now), $ver)) {
            self::deletePathLock($lock);
            return ['ok' => false, 'error' => 'conflict', 'version' => sha1($now)];
        }
        $body = str_replace(["\r\n", "\r"], "\n", $text);
        if (str_contains($now, "\r\n")) $body = str_replace("\n", "\r\n", $body);
        $temp = $pair['full'].'.part';
        $done = file_put_contents($temp, $body, LOCK_EX) !== false;
        if ($done) chmod($temp, fileperms($pair['full']) & 0777);
        if ($done) $done = rename($temp, $pair['full']);
        if (!$done && is_file($temp)) unlink($temp);
        self::deletePathLock($lock);
        return $done ? ['ok' => true, 'error' => '', 'version' => sha1($body)] : ['ok' => false, 'error' => 'write', 'version' => ''];
    }

    # Creates one empty file below the context, which is what a new template or a new stylesheet starts as; a name already taken is refused instead of being emptied
    public function addFileEntry(string $path): array {
        return $this->addPathEntry($path, false);
    }

    # Creates one directory below the context; only the last segment is created, because a path whose parent does not exist is a mistyped name far more often than an intention
    public function addDirectory(string $path): array {
        return $this->addPathEntry($path, true);
    }

    # Renames one object inside its own directory: the new name is a name and never a path, so a rename cannot turn into a move whose destination nobody asked the policy about
    public function updateFileName(string $path, string $name): array {
        $pair = $this->getPathPair($path);
        if ($pair['rel'] === '' || !self::checkPathName($name)) return self::getPathFail('closed');
        $dir = str_contains($pair['rel'], '/') ? substr($pair['rel'], 0, (int)strrpos($pair['rel'], '/')).'/' : '';
        return $this->updatePathEntry($pair, $dir.$name, 'rename');
    }

    # Copy one file to a second path of the same context; a directory is not copied, as this layer walks no tree
    public function addFileCopy(string $path, string $dest): array {
        return $this->updatePathEntry($this->getPathPair($path), $dest, 'copy');
    }

    # Moves one object to a second path of the same context, each side asked of the policy on its own and both directories locked in one order
    public function updateFilePath(string $path, string $dest): array {
        return $this->updatePathEntry($this->getPathPair($path), $dest, 'move');
    }

    # Delete one file or one empty directory, the emptiness read inside the lock of its directory; a filled directory is refused, never partly deleted
    public function deleteFileEntry(string $path): array {
        $pair = $this->getPathPair($path);
        if ($pair['rel'] === '' || empty($this->getCapabilities()['delete']) || !$this->checkFileAccess($pair['rel'], 'delete')) return self::getPathFail('closed');
        $lock = self::getPathLock(dirname($pair['full']));
        if ($lock === false) return self::getPathFail('lock');
        $isdir = is_dir($pair['full']);
        $done = !$isdir || count(scandir($pair['full']) ?: []) < 3;
        $stop = $done ? 'write' : 'filled';
        if ($done) $done = $isdir ? rmdir($pair['full']) : unlink($pair['full']);
        self::deletePathLock($lock);
        return $done ? ['ok' => true, 'error' => '', 'path' => $pair['rel']] : self::getPathFail($stop);
    }

    # Pack one file into a ZIP beside it, asked of the policy as a write of the archive name; a failed pack removes its half-written archive
    public function addFileArchive(string $path): array {
        $pair = $this->getPathPair($path);
        if ($pair['rel'] === '' || !is_file($pair['full']) || empty($this->getCapabilities()['compress'])) return self::getPathFail('closed');
        $new = $this->getMakePath($pair['rel'].'.zip');
        if (!$this->checkFileAccess($pair['rel'], 'read') || $new['rel'] === '' || !$this->getPathRules($new['rel'])['write']) return self::getPathFail('closed');
        if (!class_exists('ZipArchive')) return self::getPathFail('archive');
        $lock = self::getPathLock(dirname($new['full']));
        if ($lock === false) return self::getPathFail('lock');
        $done = !file_exists($new['full']);
        $stop = $done ? 'write' : 'exists';
        $zip = new ZipArchive();
        if ($done) $done = $zip->open($new['full'], ZipArchive::CREATE | ZipArchive::EXCL) === true;
        if ($done) {
            $done = $zip->addFile($pair['full'], basename($pair['rel']));
            $done = $zip->close() && $done;
        }
        if (!$done && $stop === 'write' && is_file($new['full'])) unlink($new['full']);
        self::deletePathLock($lock);
        return $done ? ['ok' => true, 'error' => '', 'path' => $new['rel']] : self::getPathFail($stop);
    }

    # Pack a marked set into one archive; the name is forced to .zip, as the system area reaches public/, and one closed member refuses the whole build
    public function addFilesArchive(array $paths, string $dest): array {
        $name = str_ends_with(strtolower(trim($dest)), '.zip') ? trim($dest) : trim($dest).'.zip';
        $new = $this->getMakePath($name);
        if ($paths === [] || $new['rel'] === '' || empty($this->getCapabilities()['compress'])) return self::getPathFail('closed');
        if (!$this->getPathRules($new['rel'])['write'] || !$this->getPathRules($new['dir'])['write']) return self::getPathFail('closed');
        $list = [];
        foreach ($paths as $path) {
            $pair = $this->getPathPair(is_string($path) ? $path : '');
            if ($pair['rel'] === '' || !is_file($pair['full']) || !$this->getPathRules($pair['rel'])['read']) return self::getPathFail('closed');
            $list[$pair['full']] = in_array(basename($pair['rel']), $list, true) ? $pair['rel'] : basename($pair['rel']);
        }
        if (!class_exists('ZipArchive')) return self::getPathFail('archive');
        $lock = self::getPathLock(dirname($new['full']));
        if ($lock === false) return self::getPathFail('lock');
        $done = !file_exists($new['full']);
        $stop = $done ? 'write' : 'exists';
        $zip = new ZipArchive();
        if ($done) $done = $zip->open($new['full'], ZipArchive::CREATE | ZipArchive::EXCL) === true;
        if ($done) {
            foreach ($list as $full => $entry) $done = $zip->addFile($full, $entry) && $done;
            $done = $zip->close() && $done;
        }
        if (!$done && $stop === 'write' && is_file($new['full'])) unlink($new['full']);
        self::deletePathLock($lock);
        return $done ? ['ok' => true, 'error' => '', 'path' => $new['rel']] : self::getPathFail($stop);
    }

    # Answer whether one name follows the managed upload format; the dependency runs one way, Upload to FileManager, so this file knows no upload service
    public static function checkFileName(string $file): bool {
        return preg_match(self::NAMEPAT, basename(str_replace('\\', '/', $file))) === 1;
    }

    # The owner token of one managed name, or null; it stays a string, as a numeric cast turns every guest token into zero and joins two guests
    public static function getFileOwner(string $file): ?string {
        if (!preg_match(self::NAMEPAT, basename(str_replace('\\', '/', $file)), $hit)) return null;
        return (($hit[1] ?? '') === '') ? null : $hit[1];
    }

    # Take the reentrant lock of one directory, a subdirectory of an upload area after its root; keyed by the directory, as an upload draws a free name that has no file to lock yet
    public static function getPathLock(string $dir): mixed {
        $key = self::getLockKey($dir);
        $node = defined('NODE_DIR') ? self::getLockKey(NODE_DIR) : '';
        $base = ($node !== '' && str_starts_with($key, $node.'/')) ? $node : (defined('UPLOADS_DIR') ? self::getLockKey(UPLOADS_DIR) : '');
        $rest = ($base !== '' && str_starts_with($key, $base.'/')) ? substr($key, strlen($base) + 1) : '';
        $top = str_contains($rest, '/') ? self::getPathLock($base.'/'.strstr($rest, '/', true)) : null;
        if ($top === false) return false;
        if (isset(self::$held[$key])) {
            self::$held[$key]['count']++;
            return self::$held[$key]['lock'];
        }
        $locks = self::getLockDir();
        $lock = ($locks === '' || (!is_dir($locks) && !mkdir($locks, 0750, true) && !is_dir($locks))) ? false : fopen($locks.'/'.substr(sha1($key), 0, 16).'.lock', 'cb');
        if ($lock !== false && !flock($lock, LOCK_EX)) {
            fclose($lock);
            $lock = false;
        }
        if ($lock === false) {
            if ($top !== null) self::deletePathLock($top);
            return false;
        }
        self::$held[$key] = ['lock' => $lock, 'count' => 1, 'root' => $top];
        return $lock;
    }

    # Release one entry of a lock and of its area root; the lock file is never deleted, as that would break the lock of a process still holding it
    public static function deletePathLock(mixed $lock): void {
        if (!is_resource($lock)) return;
        $top = null;
        $last = true;
        foreach (self::$held as $key => $one) {
            if ($one['lock'] !== $lock) continue;
            $top = $one['root'];
            $last = --self::$held[$key]['count'] < 1;
            if ($last) unset(self::$held[$key]);
            break;
        }
        if ($last) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
        if ($top !== null) self::deletePathLock($top);
    }

    # The canonical key of one directory from its deepest existing part; lower case on Windows, so a junction, a second spelling or another case reach one key
    private static function getLockKey(string $dir): string {
        $path = rtrim(str_replace('\\', '/', $dir), '/');
        $tail = '';
        $real = realpath($path);
        while ($real === false && ($cut = strrpos($path, '/')) > 0) {
            $tail = substr($path, $cut).$tail;
            $path = substr($path, 0, $cut);
            $real = str_contains($path, '/') ? realpath($path) : false;
        }
        $key = (($real !== false) ? rtrim(str_replace('\\', '/', $real), '/') : $path).$tail;
        return (DIRECTORY_SEPARATOR === '\\') ? strtolower($key) : $key;
    }

    # The lock directory under the cache root, outside every tree it guards; without a cache root every lock fails closed
    private static function getLockDir(): string {
        return defined('CACHE_DIR') ? rtrim(str_replace('\\', '/', CACHE_DIR), '/').'/locks/uploads' : '';
    }

    # Take the locks of every directory one operation touches, sorted segment by segment, so two writers never wait on each other in opposite order
    private static function getPathLocks(array $dirs): array {
        $keys = array_unique(array_map(static fn(string $dir): string => self::getLockKey($dir), $dirs));
        usort($keys, static fn(string $a, string $b): int => strcmp(str_replace('/', "\0", $a), str_replace('/', "\0", $b)));
        $out = [];
        foreach ($keys as $key) {
            $lock = self::getPathLock($key);
            if ($lock === false) {
                self::deletePathLocks($out);
                return [];
            }
            $out[] = $lock;
        }
        return $out;
    }

    # Releases the locks of one operation in the reverse order they were taken in
    private static function deletePathLocks(array $locks): void {
        foreach (array_reverse($locks) as $lock) self::deletePathLock($lock);
    }

    # Answers one refused operation in the shape every writing method of this layer answers in, so a route reads the reason out of one key instead of guessing from a false
    private static function getPathFail(string $why): array {
        return ['ok' => false, 'error' => $why, 'path' => ''];
    }

    # Resolve a path that does not exist yet: the parent inside the root, the last segment a plain name, so a destination cannot climb out of its directory
    private function getMakePath(string $path): array {
        $fail = ['rel' => '', 'full' => '', 'dir' => ''];
        $rel = trim(str_replace('\\', '/', $path));
        if ($rel === '' || str_ends_with($rel, '/')) return $fail;
        $cut = strrpos($rel, '/');
        $name = ($cut === false) ? $rel : substr($rel, $cut + 1);
        $base = $this->getPathPair(($cut === false) ? '' : substr($rel, 0, $cut));
        if (!self::checkPathName($name) || $base['full'] === '' || !is_dir($base['full'])) return $fail;
        return ['rel' => ($base['rel'] === '') ? $name : $base['rel'].'/'.$name, 'full' => $base['full'].'/'.$name, 'dir' => $base['rel']];
    }

    # Answers whether one segment is a plain file name: no separator, no drive or wrapper spelling, no walk of the tree and no trailing dot or space stored under a second name
    private static function checkPathName(string $name): bool {
        if ($name === '' || $name === '.' || $name === '..' || strlen($name) > 100) return false;
        if (preg_match('#[\x00-\x1f\x7f/\\\\:*?"<>|]#', $name)) return false;
        return !str_ends_with($name, '.') && !str_ends_with($name, ' ');
    }

    # Create one file or directory under the lock of its parent; a taken name is refused inside the lock, as a check made before it is already old
    private function addPathEntry(string $path, bool $isdir): array {
        $new = $this->getMakePath($path);
        $able = $this->getCapabilities();
        if ($new['rel'] === '' || empty($able[$isdir ? 'mkdir' : 'create'])) return self::getPathFail('closed');
        if (!$this->getPathRules($new['rel'])['write'] || !$this->getPathRules($new['dir'])['write']) return self::getPathFail('closed');
        $lock = self::getPathLock(dirname($new['full']));
        if ($lock === false) return self::getPathFail('lock');
        $done = !file_exists($new['full']);
        $stop = $done ? 'write' : 'exists';
        if ($done) $done = $isdir ? mkdir($new['full'], 0755) : file_put_contents($new['full'], '') !== false;
        self::deletePathLock($lock);
        return $done ? ['ok' => true, 'error' => '', 'path' => $new['rel']] : self::getPathFail($stop);
    }

    # Move, rename or copy one object, source and destination asked of the policy apart; a copy reads the source, a move or rename writes and deletes it
    private function updatePathEntry(array $pair, string $dest, string $flag): array {
        $new = $this->getMakePath($dest);
        $copy = $flag === 'copy';
        if ($pair['rel'] === '' || $new['rel'] === '' || empty($this->getCapabilities()[$flag])) return self::getPathFail('closed');
        if ($copy && !is_file($pair['full'])) return self::getPathFail('closed');
        $rule = $this->getPathRules($pair['rel']);
        $okay = $copy ? $rule['read'] : ($rule['write'] && $rule['delete']);
        if (!$okay || !$this->getPathRules($new['rel'])['write'] || !$this->getPathRules($new['dir'])['write']) return self::getPathFail('closed');
        if (is_dir($pair['full']) && str_starts_with($new['full'], $pair['full'].'/')) return self::getPathFail('loop');
        $locks = self::getPathLocks([dirname($pair['full']), dirname($new['full'])]);
        if ($locks === []) return self::getPathFail('lock');
        $done = !file_exists($new['full']) && file_exists($pair['full']);
        $stop = $done ? 'write' : (file_exists($new['full']) ? 'exists' : 'missing');
        if ($done) $done = $copy ? copy($pair['full'], $new['full']) : rename($pair['full'], $new['full']);
        self::deletePathLocks($locks);
        return $done ? ['ok' => true, 'error' => '', 'path' => $new['rel']] : self::getPathFail($stop);
    }

    # Canonicalize one client path below the root, or empty strings; the relative form is read back off the resolved path, so a link reaches the policy as it is
    private function getPathPair(string $path): array {
        $fail = ['rel' => '', 'full' => ''];
        if ($this->root === '') return $fail;
        $rel = trim(str_replace('\\', '/', $path));
        if (preg_match('#[\x00-\x1f\x7f]#', $rel) || str_contains($rel, ':') || str_contains($rel, '//')) return $fail;
        if (str_starts_with($rel, '/') || preg_match('#(^|/)\.\.?(/|$)#', $rel)) return $fail;
        $full = realpath($this->root.'/'.rtrim($rel, '/'));
        if ($full === false) return $fail;
        $full = str_replace('\\', '/', $full);
        if ($full !== $this->root && !str_starts_with($full, $this->root.'/')) return $fail;
        return ['rel' => ($full === $this->root) ? '' : substr($full, strlen($this->root) + 1), 'full' => $full];
    }

    # The policy row of one relative path, matched lowered, as a filesystem that ignores case would open a closed path under a second spelling
    private function getPathRules(string $rel): array {
        $open = array_fill_keys(self::OPS, true);
        $shut = array_fill_keys(self::OPS, false);
        if ($rel === '') return array_merge($open, ['download' => false, 'delete' => false]);
        $test = strtolower($rel);
        $name = basename($test);
        if (str_ends_with($name, '.part') || str_ends_with($name, '.lock')) return $shut;
        if ($this->mode !== 'system') return $open;
        if (in_array('.git', explode('/', $test), true) || $name === '.env' || str_starts_with($name, '.env.')) return $shut;
        if ($test === 'storage/sessions' || str_starts_with($test, 'storage/sessions/')) return $shut;
        if (str_starts_with($test, 'storage/logs/') && str_ends_with($name, '.log')) return array_merge($open, ['write' => false]);
        return $open;
    }

    # Answer whether losing one system path stops the site; every PHP file directly in PUBLIC_DIR counts, so a panel the installer renamed stays critical
    private function checkCritPath(string $rel): bool {
        if ($rel === '') return false;
        $test = strtolower($rel);
        $pub = defined('PUBLIC_DIR') ? realpath(PUBLIC_DIR) : false;
        $pub = ($pub === false) ? '' : strtolower(rtrim(str_replace('\\', '/', $pub), '/'));
        $base = strtolower($this->root);
        $name = ($pub !== '' && str_starts_with($pub, $base.'/') && str_starts_with($test, substr($pub, strlen($base) + 1).'/'))
            ? substr($test, strlen($pub) - strlen($base)) : '';
        if ($name !== '' && !str_contains($name, '/') && ($name === '.htaccess' || str_ends_with($name, '.php'))) return true;
        return in_array($test, self::CRIT, true) || in_array(explode('/', $test)[0], self::CRITDIR, true);
    }

    # Narrow the context capabilities to one object by its kind and the path policy; copying and packing stay file operations, as this layer walks no tree
    private function getFileGrants(array $rule, string $kind, bool $prev, bool $edit): array {
        $out = $this->getCapabilities();
        $isdir = ($kind === 'dir');
        $out['browse'] = $out['browse'] && $isdir && $rule['list'];
        $out['preview'] = $out['preview'] && $prev && $rule['read'];
        $out['upload'] = $out['upload'] && $isdir && $rule['write'];
        $out['download'] = $out['download'] && !$isdir && $rule['download'];
        $out['insert'] = $out['insert'] && !$isdir;
        $out['embed'] = $out['embed'] && $kind === 'image';
        $out['edit'] = $out['edit'] && $edit && $rule['write'];
        $out['create'] = $out['create'] && $isdir && $rule['write'];
        $out['mkdir'] = $out['mkdir'] && $isdir && $rule['write'];
        $out['rename'] = $out['rename'] && $rule['write'] && $rule['delete'];
        $out['copy'] = $out['copy'] && !$isdir && $rule['read'];
        $out['move'] = $out['move'] && $rule['write'] && $rule['delete'];
        $out['delete'] = $out['delete'] && $rule['delete'];
        $out['compress'] = $out['compress'] && !$isdir && $rule['read'];
        return $out;
    }

    # The account one object belongs to, empty on Windows, which reports one synthetic owner for every file
    private function getFileUser(string $full): string {
        if (DIRECTORY_SEPARATOR !== '/') return '';
        $uid = fileowner($full);
        if ($uid === false) return '';
        $row = function_exists('posix_getpwuid') ? (posix_getpwuid($uid) ?: []) : [];
        return (string)($row['name'] ?? $uid);
    }

    # Returns the kind one object is shown and treated as; html, htm and js resolve to code and are therefore never previewable, which is the rule the preview boundary needs
    public static function getFileKind(bool $isdir, string $ext): string {
        if ($isdir) return 'dir';
        if (in_array($ext, self::IMAGES, true)) return 'image';
        if (in_array($ext, self::AUDIOS, true)) return 'audio';
        if (in_array($ext, self::VIDEOS, true)) return 'video';
        if (in_array($ext, self::PACKS, true)) return 'archive';
        if ($ext === 'pdf') return 'document';
        $lang = self::LANGS[$ext] ?? '';
        if ($lang === '') return 'file';
        return ($lang === 'text') ? 'text' : 'code';
    }

    # Reads the pixel size of one image, header only and never for a kind that carries none; a vector has no pixel size, and an undecodable file is a listing entry, not a defect
    private function getImageBounds(string $full, string $kind, string $ext): array {
        if ($kind !== 'image' || $ext === 'svg') return ['width' => null, 'height' => null];
        set_error_handler(static fn(): bool => true);
        try {
            $info = getimagesize($full);
        } finally {
            restore_error_handler();
        }
        if (!is_array($info) || (int)($info[0] ?? 0) < 1 || (int)($info[1] ?? 0) < 1) return ['width' => null, 'height' => null];
        return ['width' => (int)$info[0], 'height' => (int)$info[1]];
    }

    # Returns the site address of one object, which only a file of a public upload folder has, see getUploadUrl(); the system area answers none, its files are served by a route
    private function getFileLink(string $full): string {
        if ($this->mode === 'system' || !defined('UPLOADS_DIR')) return '';
        $base = realpath(UPLOADS_DIR);
        if ($base === false) return '';
        $base = rtrim(str_replace('\\', '/', $base), '/');
        return str_starts_with($full, $base.'/') ? getUploadUrl(substr($full, strlen($base) + 1)) : '';
    }

    # Returns the address of the thumbnail one image carries in the additional directory of its own directory, or an empty string when none was stored
    private function getThumbLink(string $full): string {
        $thumb = dirname($full).'/thumb/'.basename($full);
        return is_file($thumb) ? $this->getFileLink($thumb) : '';
    }
}
