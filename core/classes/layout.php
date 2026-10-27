<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The layout of the site, config/layout.php read, rendered and given as data; it knows no section, part or menu by name, each comes from its description
final class Layout {

    # The shape of config/layout.php this code reads; a file of another version is moved by a step of the update, never read as it is
    public const VERSION = 1;

    # The one type of section the core owns: it holds the output of a module, every other type is described by the theme or a module
    public const SLOT = 'module';

    # The name of a section type, which is the file name of its description, its fragment and its provider
    private const TYPE = '/^[a-z][a-z0-9-]{0,39}$/D';

    private array $data;
    private string $base;
    private string $mods;
    private bool $user;
    private bool $moder;
    private ?array $types = null;
    private array $provs = [];

    # Keep the layout of $conf['layout'], the folder of the theme and of the modules, and the viewer the sections and menu items are shown to
    public function __construct(array $data, string $theme, bool $user, bool $moder, string $mods = '') {
        $this->data = self::getLayoutData($data);
        $this->base = PUBLIC_DIR.'/templates/'.$theme;
        $this->mods = ($mods !== '') ? $mods : BASE_DIR.'/modules';
        $this->user = $user;
        $this->moder = $moder;
    }

    # The scale 0-3 of _blocks.view that a block, a section and a menu item share: all, users, moderators, guests; a moderator sees every value
    public static function isViewShown(int $view, bool $user, bool $moder): bool {
        if ($moder) return true;
        return match ($view) {
            0 => true,
            1 => $user,
            3 => !$user,
            default => false,
        };
    }

    # The layout in its one shape, numbers and the mark read back from the text the string form of setConfigFile() writes, as the merge of an update does
    public static function getLayoutData(array $data): array {
        $none = ['v' => self::VERSION, 'shipped' => false, 'next' => 1, 'home' => 0, 'notfound' => 0, 'theme' => [], 'menus' => [], 'pages' => []];
        if (!is_scalar($data['v'] ?? null) || (string)$data['v'] !== (string)self::VERSION) return $none;
        foreach (['theme', 'menus', 'pages'] as $key) if (!is_array($data[$key] ?? null)) return $none;
        $data = array_replace($none, array_intersect_key($data, $none));
        foreach (['v', 'next', 'home', 'notfound'] as $key) $data[$key] = (int)$data[$key];
        $data['shipped'] = !empty($data['shipped']);
        $data['pages'] = array_filter($data['pages'], static fn($v) => is_array($v) && is_array($v['sections'] ?? null));
        return $data;
    }

    # The whole layout as data, the form a later answer of the API gives
    public function getData(): array {
        return $this->data;
    }

    # One page by its permanent number, or an empty array when the layout has none
    public function getPage(int $key): array {
        return $this->data['pages'][$key] ?? [];
    }

    # The number of the home page, 0 when the layout names none that exists
    public function getHomeKey(): int {
        $key = (int)$this->data['home'];
        return ($this->getPage($key) !== []) ? $key : 0;
    }

    # The number of the page whose slot holds a module, 0 when none does; the home page and the page 404 never answer for a module
    public function getModuleKey(string $mod): int {
        if ($mod === '') return 0;
        $skip = [(int)$this->data['home'], (int)$this->data['notfound']];
        foreach (array_keys($this->data['pages']) as $key) {
            if (!in_array($key, $skip, true) && $this->getSlotName($key) === $mod) return $key;
        }
        return 0;
    }

    # The module the slot of a page names, an empty name standing for the home module of the settings, or null when the page shows no slot
    public function getSlotName(int $key): ?string {
        foreach ($this->getPage($key)['sections'] ?? [] as $sec) {
            if (is_array($sec) && ($sec['type'] ?? '') === self::SLOT && $this->isSectionShown($sec)) return (string)($sec['module'] ?? '');
        }
        return null;
    }

    # The columns of a page as a module carries them in config/modules.php: side for the side zones, top for the centre, 3 by default
    public function getPageColumns(int $key): array {
        $page = $this->getPage($key);
        return [(string)($page['side'] ?? '3'), (string)($page['top'] ?? '3')];
    }

    # The sections of a page in their order, the output of its module in the place of the slot; output no slot took stays after them, never dropped
    public function getPageHtml(int $key, string $body, Template $tpl): string {
        $html = '';
        $slot = false;
        $types = $this->getSectionTypes();
        foreach ($this->getPage($key)['sections'] ?? [] as $sec) {
            if (!is_array($sec) || !$this->isSectionShown($sec)) continue;
            $type = (string)($sec['type'] ?? '');
            if ($type === self::SLOT) {
                if ($slot) continue;
                $slot = true;
                $html .= $body;
                continue;
            }
            $one = $types[$type] ?? [];
            if (($one['frag'] ?? '') !== 'theme') continue;
            $prov = $this->getProvider($type, $one['prov']);
            $html .= $tpl->getHtmlSection($type, $prov ? array_replace($sec, $prov($sec)) : $sec);
        }
        return $slot ? $html : $html.$body;
    }

    # The items of one menu the viewer may see, at every depth, each with its children filtered the same way
    public function getMenu(string $key): array {
        $walk = function (array $list) use (&$walk): array {
            $out = [];
            foreach ($list as $item) {
                if (!is_array($item) || !self::isViewShown((int)($item['who'] ?? 0), $this->user, $this->moder)) continue;
                $item['kids'] = $walk(is_array($item['kids'] ?? null) ? $item['kids'] : []);
                $out[] = $item;
            }
            return $out;
        };
        $list = $this->data['menus'][$key] ?? [];
        return $walk(is_array($list) ? $list : []);
    }

    # The section types of the roots, the theme first, then each module by name: the first root to describe a type owns it, the theme's fragment wins
    public function getSectionTypes(): array {
        if ($this->types !== null) return $this->types;
        $roots = ['theme' => $this->base.'/sections'];
        foreach (glob($this->mods.'/*/sections', GLOB_ONLYDIR) ?: [] as $dir) $roots[basename(dirname($dir))] = $dir;
        $types = [];
        foreach ($roots as $root => $dir) {
            foreach (glob($dir.'/*.json') ?: [] as $file) {
                $type = basename($file, '.json');
                if (isset($types[$type]) || $type === self::SLOT || preg_match(self::TYPE, $type) !== 1) continue;
                $desc = json_decode((string)file_get_contents($file), true);
                if (!is_array($desc) || ($desc !== [] && array_is_list($desc))) continue;
                $frag = is_file($roots['theme'].'/'.$type.'.html') ? 'theme' : (is_file($dir.'/'.$type.'.html') ? $root : '');
                $prov = ($root !== 'theme' && is_file($dir.'/'.$type.'.php')) ? $dir.'/'.$type.'.php' : '';
                $types[$type] = ['root' => $root, 'desc' => $desc, 'frag' => $frag, 'prov' => $prov];
            }
        }
        return $this->types = $types;
    }

    # The places a theme declares in its places.json: its parts with their fields, its menus with their depth and its zones of blocks
    public function getThemePlaces(): array {
        $file = $this->base.'/places.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $out = [];
        foreach (['parts', 'menus', 'zones'] as $key) $out[$key] = is_array($data[$key] ?? null) ? $data[$key] : [];
        return $out;
    }

    # Whether a section is switched on and its audience, the scale of _blocks.view, holds the viewer
    private function isSectionShown(array $sec): bool {
        return !empty($sec['on']) && self::isViewShown((int)($sec['who'] ?? 0), $this->user, $this->moder);
    }

    # The data provider of a type a module brings, a file of its sections/ returning a Closure that takes the section and gives the values of its fragment
    private function getProvider(string $type, string $file): ?Closure {
        if ($file === '') return null;
        if (!array_key_exists($type, $this->provs)) {
            $prov = include $file;
            $this->provs[$type] = ($prov instanceof Closure) ? $prov : null;
        }
        return $this->provs[$type];
    }
}
