<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Reads one filesystem area below one server-chosen root: path canonicalization, the path policy, the descriptor and the capabilities (docs/FILE-MANAGER-CONCEPT-2026.md)
# The client never names a physical root and never names an absolute path; it passes a path relative to the context it already received, and everything else is decided here
# This stage holds the reading half only: no method of this class writes, renames, deletes or locks anything, so a wrong answer here costs a refusal and never a lost file
# The dependency runs one way, Upload -> FileManager, so nothing in this file knows the upload service exists
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
        'sql' => 'sql', 'xml' => 'xml', 'txt' => 'text', 'md' => 'text', 'ini' => 'text', 'log' => 'text',
    ];
    private const IMAGES = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg'];
    private const AUDIOS = ['mp3', 'wav', 'flac', 'ogg', 'oga', 'opus', 'm4a'];
    private const VIDEOS = ['mp4', 'webm'];
    private const PACKS = ['zip', 'rar', 'gz', '7z', 'tar'];
    private const CRIT = ['index.php', 'admin.php', 'setup.php', '.htaccess'];
    private const CRITDIR = ['config', 'core'];

    private string $mode = '';
    private string $root = '';
    private array $flags = [];

    # Builds one context over the root the server chose; an unknown mode or an unresolvable root leaves it closed, which is what makes a miswired route refuse instead of browse
    # The flags carry the decisions the route already made with the module rule and is_moder(), because the upload rule stays the only place a permission is computed
    public function __construct(string $mode, string $root, array $flags = []) {
        $path = in_array($mode, self::MODES, true) ? realpath($root) : false;
        if ($path === false) return;
        $this->mode = $mode;
        $this->root = rtrim(str_replace('\\', '/', $path), '/');
        $this->flags = $flags;
    }

    # Lists one directory of the context as descriptors, directories first and each group by name; a refused, missing or unlistable directory answers an empty list
    # Entries the policy hides are dropped here, and the same policy refuses them again on a direct request, because hiding a path is convenience and refusing it is the protection
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

    # Returns one object as the normalized descriptor of the plan, or an empty array when the path leaves the root, does not exist or is closed by the policy
    # The absolute path travels only in an administrative context; the editor never receives it, and neither context receives a path the client did not already hold relatively
    # Whether an object is a managed file is answered by checkFileName() and by nothing else, so no reader of a listing ever takes a stored name apart on its own
    public function getFileData(string $path): array {
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '') return [];
        $rule = $this->getPathRules($pair['rel']);
        if (!$rule['list']) return [];
        $isdir = is_dir($pair['full']);
        $ext = $isdir ? '' : strtolower(pathinfo($pair['full'], PATHINFO_EXTENSION));
        $kind = $this->getFileKind($isdir, $ext);
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
            'managed' => !$isdir && self::checkFileName($pair['rel']),
            'editable' => $edit,
            'previewable' => $prev,
            'capabilities' => $this->getFileGrants($rule, $kind, $prev, $edit),
        ];
        if ($this->mode !== 'editor') $data['realpath'] = $pair['full'];
        if ($this->mode === 'system') $data['critical'] = $this->checkCritPath($pair['rel']);
        return $data;
    }

    # Returns what this context allows at all, which is what the interface prints instead of deriving a permission from a role of its own
    # A closed instance answers every capability false, so a context that failed to build cannot be mistaken for one that simply forbids a single action
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
                'mkdir' => true, 'rename' => true, 'copy' => true, 'move' => true, 'delete' => true]),
            default => $none,
        };
    }

    # Answers whether one operation is allowed on one path of this context, which every route asks before it acts and not after the path is already open
    # An operation over two paths asks twice, once per side: a move needs write and delete on the source and write on the destination, or a closed file copies itself into the open
    public function checkFileAccess(string $path, string $op): bool {
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '' || !in_array($op, self::OPS, true)) return false;
        return $this->getPathRules($pair['rel'])[$op];
    }

    # Answers whether one file opens in the source editor: the context has to edit at all, the extension has to carry a mode and the file has to be readable and small enough
    # Write is not asked here on purpose, because a readable file of a write-closed path still opens for reading; whether it may be saved is the edit capability of its descriptor
    public function isFileEditable(string $path): bool {
        if (empty($this->getCapabilities()['edit']) || $this->getCodeLanguage($path) === '') return false;
        $pair = $this->getPathPair($path);
        if ($pair['full'] === '' || !is_file($pair['full']) || !is_readable($pair['full']) || !$this->checkFileAccess($path, 'read')) return false;
        return (int)filesize($pair['full']) <= self::MAXEDIT;
    }

    # Returns the highlighting mode of one path for the source editor, or an empty string for everything that is not text, which is what keeps a binary out of the editor
    public function getCodeLanguage(string $path): string {
        $ext = strtolower(pathinfo(str_replace('\\', '/', $path), PATHINFO_EXTENSION));
        return self::LANGS[$ext] ?? '';
    }

    # Answers whether one name follows the managed format the upload service publishes under, which tells a file this project stored apart from one that arrived otherwise
    public static function checkFileName(string $file): bool {
        return preg_match(self::NAMEPAT, basename(str_replace('\\', '/', $file))) === 1;
    }

    # Returns the owner token one managed name carries, or null when the name carries no owner segment or does not follow the format at all
    # The answer is a string and is compared as one: the segment of a guest is an opaque token, and a numeric cast turns every one of them into zero and joins two guests
    public static function getFileOwner(string $file): ?string {
        if (!preg_match(self::NAMEPAT, basename(str_replace('\\', '/', $file)), $hit)) return null;
        return (($hit[1] ?? '') === '') ? null : $hit[1];
    }

    # Takes the exclusive lock of one directory and answers the open handle, or false when it cannot be taken; every writer of the project serializes on this one protocol
    # The key is the directory and not the file, because the upload service draws a free name below it and the file it is about to write does not exist yet to be locked
    # Two writers holding two protocols of their own would not wait for each other at all, which is why the pair is public here instead of private in whoever writes first
    public static function getPathLock(string $dir): mixed {
        $locks = self::getLockDir();
        if ($locks === '' || (!is_dir($locks) && !mkdir($locks, 0750, true) && !is_dir($locks))) return false;
        $lock = fopen($locks.'/'.substr(sha1(rtrim(str_replace('\\', '/', $dir), '/')), 0, 16).'.lock', 'cb');
        if ($lock === false) return false;
        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            return false;
        }
        return $lock;
    }

    # Releases one lock taken by getPathLock(); the lock file itself is never deleted, because deleting it would break the lock for a process that still holds it
    public static function deletePathLock(mixed $lock): void {
        if (!is_resource($lock)) return;
        flock($lock, LOCK_UN);
        fclose($lock);
    }

    # Returns the one directory the lock files of the project live in, outside every tree they guard, so a lock is never listed, downloaded or removed with what it serializes
    # A build without a log root answers an empty string and every lock then fails closed, because a writer that believes it holds a lock it never took is the worse half
    private static function getLockDir(): string {
        return defined('LOGS_DIR') ? rtrim(str_replace('\\', '/', LOGS_DIR), '/').'/uploads' : '';
    }

    # Canonicalizes one client path inside the root and answers its relative and absolute form, or two empty strings when it is not a plain relative path below the root
    # The root itself is the empty relative path; a drive letter, a stream wrapper and an alternate data stream all carry a colon, and a traversal survives no separator spelling
    # realpath() resolves the last symlink too, so the prefix test below is what refuses a link that leaves the root while its own name looks harmless
    # The relative path is read back off the resolved one and never off the request, so a link below the root and a second spelling of a name reach the policy as what they are
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

    # Answers the policy row of one relative path: what may be listed, read, downloaded, written and deleted there, decided in this one place for every route of the context
    # The root of a context is written into but never taken away: it is listed and written, and it is never downloaded, renamed or removed through the browser that shows it
    # Partials and lock files belong to the writers and are closed in every context; the system rows are the table of the plan, and everything it does not name stays open
    # A row is matched against the lowered path, because a filesystem that ignores case answers the same file for both spellings and a closed path must not open under a second name
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

    # Answers whether one system path is one of the files whose loss stops the site, which is what makes the interface ask again and the journal record the answer
    private function checkCritPath(string $rel): bool {
        if ($rel === '') return false;
        $test = strtolower($rel);
        return in_array($test, self::CRIT, true) || in_array(explode('/', $test)[0], self::CRITDIR, true);
    }

    # Narrows the capabilities of the context down to one object: what a directory cannot be, what the path policy closes and what the kind of the file does not support
    # Copying and packing are file operations here, because walking a directory into a second one is recursion and the first version of the plan does not carry it
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

    # Returns the kind one object is shown and treated as; html, htm and js resolve to code and are therefore never previewable, which is the rule the preview boundary needs
    private function getFileKind(bool $isdir, string $ext): string {
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

    # Returns the site-relative address of one object, which exists only below the document root; the system area answers none, because its files are served by a route
    private function getFileLink(string $full): string {
        if ($this->mode === 'system' || !defined('BASE_DIR')) return '';
        $base = realpath(BASE_DIR);
        if ($base === false) return '';
        $base = rtrim(str_replace('\\', '/', $base), '/');
        return str_starts_with($full, $base.'/') ? substr($full, strlen($base) + 1) : '';
    }

    # Returns the address of the thumbnail one image carries in the additional directory of its own directory, or an empty string when none was stored
    private function getThumbLink(string $full): string {
        $thumb = dirname($full).'/thumb/'.basename($full);
        return is_file($thumb) ? $this->getFileLink($thumb) : '';
    }
}
