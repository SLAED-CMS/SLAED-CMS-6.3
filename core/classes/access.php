<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

final class FileAccess {
    public const OWNERS = ['node', 'forum', 'privat', 'comment', 'public'];
    public const PREVIEW = ['node', 'forum'];

    # One adapter per owner as closures: folder(string $mod, int $id): string always, grant(string $mod, int $id, string $key): bool for an owner with a route
    public function __construct(private readonly array $owns) {}

    # The folder of an owner relative to UPLOADS_DIR, or an empty string for a value outside the closed set or without an adapter
    public function getFileFolder(string $own, string $mod, int $id = 0): string {
        $call = in_array($own, self::OWNERS, true) ? ($this->owns[$own]['folder'] ?? null) : null;
        return ($call instanceof Closure) ? $call($mod, $id) : '';
    }

    # The address a text prints for one name: the file route of a closed owner and its target, the preview of an owner of PREVIEW without one, the direct link of a public folder
    public function getFileUrl(string $own, string $mod, int $id, string $key, bool $thumb = false): string {
        $tail = $thumb ? '&thumb=1' : '';
        if ($own === 'public') return getUploadUrl($this->getFileFolder($own, $mod, $id).'/'.($thumb ? 'thumb/' : '').$key);
        if (!in_array($own, self::OWNERS, true) || !(($this->owns[$own]['grant'] ?? null) instanceof Closure)) return '';
        if ($id < 1 && (!in_array($own, self::PREVIEW, true) || $mod === '')) return '';
        if ($id < 1) return 'index.php?go=file&own='.$own.'&name='.$mod.'&key='.rawurlencode($key).'&preview=1'.$tail;
        return 'index.php?go=file&own='.$own.'&id='.$id.'&key='.rawurlencode($key).$tail;
    }

    # The path one reader may receive, or an empty string for every refusal: a bare name of a supported type that the adapter grants, whatever its form, in its folder
    public function getFilePath(string $own, string $mod, int $id, string $key, bool $thumb = false): string {
        require_once BASE_DIR.'/core/classes/upload.php';
        $call = in_array($own, self::OWNERS, true) ? ($this->owns[$own]['grant'] ?? null) : null;
        $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $bare = strlen($key) <= 255 && preg_match('/^[A-Za-z0-9_\-][A-Za-z0-9_\-. ]*$/D', $key) && in_array($ext, Upload::getSupportedTypes(), true);
        if (!($call instanceof Closure) || $id < 0 || !$bare || !$call($mod, $id, $key)) return '';
        $room = $this->getFileFolder($own, $mod, $id);
        $root = ($room === '') ? false : realpath(UPLOADS_DIR.'/'.$room);
        $path = ($root === false) ? false : realpath($root.'/'.($thumb ? 'thumb/' : '').$key);
        if ($path === false || !is_file($path)) return '';
        $path = str_replace('\\', '/', $path);
        return str_starts_with($path, rtrim(str_replace('\\', '/', $root), '/').'/') ? $path : '';
    }
}
