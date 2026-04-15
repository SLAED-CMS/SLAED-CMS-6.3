<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Contract for all content editors
interface ContentDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string;
}

# Contract for all code/syntax editors
interface CodeDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $lang, string $profile): string;
}

class Editor {
    private static array $mdata = [];
    private static array $drvs = [];

    # Render content editor widget; editor, profile, role, format are optional overrides
    public static function getContent(array $data): string {
        $role = (string)($data['role'] ?? (defined('ADMIN_FILE') ? 'admin' : 'user'));
        $key = (string)($data['editor'] ?? self::getEditorKey($role));
        $profile = (string)($data['profile'] ?? ($role === 'admin' ? 'full' : 'simple'));
        $fmt = (string)($data['format'] ?? '');
        if ($fmt === 'html' && $role !== 'admin') $key = self::getEditorKey($role);
        if (!self::checkManifest(self::getManifest($key), $role)) $key = self::getEditorKey($role);
        $driver = self::getDriver($key);
        if ($fmt && $driver instanceof ContentDriver) {
            $man = self::getManifest($key);
            $fmts = (array)($man['formats'] ?? []);
            if ($fmts && !in_array($fmt, $fmts, true)) $driver = self::getDriver(self::getEditorKey($role));
        }
        if (!$driver instanceof ContentDriver) $driver = self::getDriver('plain');
        $id = (string)($data['id'] ?? 'editor');
        $name = (string)($data['name'] ?? 'text');
        $value = (string)($data['value'] ?? '');
        return $driver instanceof ContentDriver ? $driver->getAssets($profile).$driver->getWidget($id, $name, $value, $profile, $data) : '';
    }

    # Render code editor widget; lang required, validated against manifest
    public static function getCode(array $data): string {
        global $conf;
        $key = (string)($conf['editor']['code'] ?? 'codemirror');
        $profile = (string)($data['profile'] ?? 'full');
        $lang = (string)($data['lang'] ?? 'text');
        $man = self::getManifest($key);
        if (!$man || !($man['enabled'] ?? true) || ($man['type'] ?? '') !== 'code'
            || !in_array('admin', (array)($man['roles'] ?? []), true)) {
            $key = 'codemirror';
            $man = self::getManifest($key);
        }
        $list = (array)($man['lang'] ?? []);
        if ($list && !in_array($lang, $list, true)) $lang = 'text';
        $driver = self::getDriver($key);
        if (!$driver instanceof CodeDriver) $driver = self::getDriver('codemirror');
        $id = (string)($data['id'] ?? 'code');
        $name = (string)($data['name'] ?? '');
        $value = (string)($data['text'] ?? '');
        return $driver instanceof CodeDriver ? $driver->getAssets($profile).$driver->getWidget($id, $name, $value, $lang, $profile) : '';
    }

    # Render editor select dropdown for settings UI
    public static function getSelect(string $name, string $selected, string $type, string $role, string $selectAttr = ''): string {
        global $tpl;
        $list = self::getEditorList($type, $role);
        $html = '';
        foreach ($list as $id => $man) {
            $html .= $tpl->getHtmlFrag('new/select-option', [
                'value_attr' => $id,
                'label_text' => (string)($man['label'] ?? $id),
                'is_selected' => $id === $selected,
            ]);
        }
        return $tpl->getHtmlFrag('new/select', [
            'name_attr' => $name,
            'select_class' => '',
            'select_attr' => $selectAttr,
            'options_html' => $html,
        ]);
    }

    # Return parsed manifest for one editor; null if missing or invalid
    private static function getManifest(string $id): ?array {
        if (isset(self::$mdata[$id])) return self::$mdata[$id];
        $path = BASE_DIR.'/plugins/editors/'.$id.'/manifest.json';
        if (!is_file($path)) return null;
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) return null;
        foreach (['id', 'type', 'driver', 'entry', 'roles', 'profiles', 'formats'] as $name) {
            if (!isset($data[$name])) return null;
        }
        self::$mdata[$id] = $data;
        return $data;
    }

    # Validate that manifest qualifies as an enabled content editor for the given role
    private static function checkManifest(?array $man, string $role): bool {
        return $man !== null
            && ($man['enabled'] ?? true)
            && ($man['type'] ?? '') === 'content'
            && in_array($role, (array)($man['roles'] ?? []), true);
    }

    # Public gateway for external callers (e.g. changeeditor() in admin/index.php)
    public static function isValidEditor(string $key, string $role): bool {
        return self::checkManifest(self::getManifest($key), $role);
    }

    # Return available editors filtered by type and role, sorted by priority
    private static function getEditorList(string $type, string $role): array {
        $base = BASE_DIR.'/plugins/editors';
        $list = [];
        if (!is_dir($base)) return $list;
        foreach (scandir($base) as $dir) {
            if ($dir[0] === '.') continue;
            $man = self::getManifest($dir);
            if (!$man) continue;
            if (!($man['enabled'] ?? true)) continue;
            if ($type && ($man['type'] ?? '') !== $type) continue;
            if ($role && !in_array($role, (array)($man['roles'] ?? []), true)) continue;
            $list[$man['id']] = $man;
        }
        uasort($list, fn($a, $b) => ($a['priority'] ?? 100) <=> ($b['priority'] ?? 100));
        return $list;
    }

    # Load and cache driver instance — class and entry point taken from manifest
    private static function getDriver(string $id): ContentDriver|CodeDriver|null {
        if (isset(self::$drvs[$id])) return self::$drvs[$id];
        $man = self::getManifest($id);
        if (!$man) return null;
        $cls = (string)($man['driver'] ?? '');
        $path = BASE_DIR.'/plugins/editors/'.$id.'/'.($man['entry'] ?? 'driver.php');
        if ($cls === '' || !is_file($path)) return null;
        require_once $path;
        if (!class_exists($cls)) return null;
        self::$drvs[$id] = new $cls();
        return self::$drvs[$id];
    }

    # Resolve active content editor key — full manifest validation via checkManifest()
    private static function getEditorKey(string $role): string {
        global $admin, $conf;
        if ($role === 'admin') {
            $key = (string)($admin[3] ?? '');
            if ($key && self::checkManifest(self::getManifest($key), 'admin')) return $key;
            $key = (string)($conf['editor']['admin'] ?? 'plain');
            if ($key !== 'plain' && self::checkManifest(self::getManifest($key), 'admin')) return $key;
            return 'plain';
        }
        $key = (string)($conf['editor']['user'] ?? 'plain');
        if ($key !== 'plain' && self::checkManifest(self::getManifest($key), 'user')) return $key;
        return 'plain';
    }
}
