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

    # The name a page carries in $conf['name'], in $name and in the which of a block: page- and its permanent number, a name no module or type can take
    public const PAGE = '/^page-([1-9][0-9]{0,9})$/D';

    # The name of a section type, which is the file name of its description, its fragment and its provider
    private const TYPE = '/^[a-z][a-z0-9-]{0,39}$/D';

    # The draft in LAYOUT_DIR is JSON and never PHP: it is written from the edits, so no include may ever run it
    private const DRAFT = 'draft.json';

    # The key of a field in a description: no underscore, so the names derived with one (text_html, side_right) never meet a field; items and parts are the lists it adds
    private const FIELD = '/^[a-z][a-z0-9]{0,31}$/D';

    # The nine points of a picture its crop keeps in view, as object-position takes them
    private const FOCUS = ['left top', 'center top', 'right top', 'left center', 'center', 'right center', 'left bottom', 'center bottom', 'right bottom'];

    # The tones a section gives its icons or figures; the tone text is the colour of the words and prints none
    private const TONES = ['primary', 'success', 'warning', 'accent', 'danger'];

    # The name of a Bootstrap icon, the icon contract every theme ships
    private const ICON = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D';

    # The anchor of a section, the id a link to its place on the page names
    private const ANCHOR = '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D';

    # An address of a YouTube film in each form its site gives, the id of the film caught
    private const TUBE = '#^https?://(?:www\.|m\.)?(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^\#]*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#';

    # A point of a map as one field writes it, the latitude then the longitude: 52.3507, 8.2271
    private const POINT = '/^(-?[0-9]{1,2}(?:\.[0-9]{1,8})?)\s*,\s*(-?[0-9]{1,3}(?:\.[0-9]{1,8})?)$/D';

    private array $data;
    private string $base;
    private string $mods;
    private string $lang;
    private bool $user;
    private bool $moder;
    private bool $draft = false;
    private ?array $types = null;
    private ?array $places = null;
    private ?array $parts = null;
    private array $provs = [];
    private ?Field $fld = null;

    # Keep the layout of $conf['layout'], the folders of the theme and the modules, the viewer of the sections and menu items, and the language of a multilingual site
    public function __construct(array $data, string $theme, bool $user, bool $moder, string $lang = '', string $mods = '') {
        $this->data = self::getLayoutData($data);
        $this->base = PUBLIC_DIR.'/templates/'.$theme;
        $this->mods = ($mods !== '') ? $mods : BASE_DIR.'/modules';
        $this->lang = $lang;
        $this->user = $user;
        $this->moder = $moder;
    }

    # The link to look at one page of the draft until a moment: the term, then an HMAC over the page, the term and the counter of links a new link raises
    public static function getDraftToken(int $key, int $till, int $link): string {
        return $till.'.'.hash_hmac('sha256', $key.'|'.$till.'|'.$link, getSecret('layout-draft'));
    }

    # Whether a link to look at the draft is the one signed for this page and the current counter of links, and its term has not passed
    public static function checkDraftToken(int $key, string $token, int $link): bool {
        if (preg_match('/^([1-9][0-9]{0,10})\.[0-9a-f]{64}$/D', $token, $hit) !== 1 || (int)$hit[1] < time()) return false;
        return hash_equals(self::getDraftToken($key, (int)$hit[1], $link), $token);
    }

    # The draft as its layout and the counter of its links, or an empty array while there is none or it does not read
    public static function getDraftFile(): array {
        $file = LAYOUT_DIR.'/'.self::DRAFT;
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (!is_array($data) || !is_array($data['layout'] ?? null)) return [];
        return ['layout' => $data['layout'], 'link' => (int)($data['link'] ?? 0)];
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

    # Read the draft in place of the published layout, as a valid link to look at the draft asks
    public function setDraftData(array $data): void {
        $this->data = self::getLayoutData($data);
        $this->draft = true;
        $this->parts = null;
    }

    # Whether the layout read is the draft, which no search engine indexes and no address redirects
    public function isDraft(): bool {
        return $this->draft;
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

    # The pages at an address of their own: not the home page, not the page 404, and no slot switched on, whose page answers to its module for every viewer
    public function getAddressKeys(): array {
        $skip = [(int)$this->data['home'], (int)$this->data['notfound']];
        $out = [];
        foreach ($this->data['pages'] as $key => $page) {
            if (in_array($key, $skip, true)) continue;
            foreach ($page['sections'] as $sec) if (is_array($sec) && ($sec['type'] ?? '') === self::SLOT && !empty($sec['on'])) continue 2;
            $out[] = $key;
        }
        return $out;
    }

    # Whether the audience of a page, all or users only on the scale of _blocks.view, holds the viewer
    public function isPageShown(int $key): bool {
        return self::isViewShown((int)($this->getPage($key)['who'] ?? 0), $this->user, $this->moder);
    }

    # Whether a page, a missing one included, has no section its viewer sees
    public function isPageEmpty(int $key): bool {
        foreach ($this->getPage($key)['sections'] ?? [] as $sec) if (is_array($sec) && $this->isSectionShown($sec)) return false;
        return true;
    }

    # The columns of a page as a module carries them in config/modules.php: side for the side zones, top for the centre, 3 by default
    public function getPageColumns(int $key): array {
        $page = $this->getPage($key);
        return [(string)($page['side'] ?? '3'), (string)($page['top'] ?? '3')];
    }

    # The sections of a page in their order, each in the frame of fragments/section.html, the module's output in the slot; a section printing nothing loses its frame
    public function getPageHtml(int $key, string $body, Template $tpl, Parser $prs): string {
        $html = '';
        $slot = false;
        $num = 0;
        $types = $this->getSectionTypes();
        $wrap = $tpl->checkTemplateFile('fragments', 'section');
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
            $frag = (string)($one['frag'] ?? '');
            if ($frag === '') continue;
            $desc = $one['desc'];
            $data = $this->getFieldData(is_array($desc['fields'] ?? null) ? $desc['fields'] : [], $sec, $prs);
            if (is_array($desc['items'] ?? null)) $data['items'] = $this->getItemData($desc['items'], $sec['items'] ?? null, $prs);
            $data['parts'] = $this->getThemeData($prs);
            $prov = $this->getProvider($type, $one['prov']);
            if ($prov) $data = array_replace($data, $prov($sec));
            $inner = $tpl->getHtmlSection($type, $data, ($frag === 'theme') ? '' : $this->mods.'/'.$frag.'/sections');
            if (trim($inner) === '') continue;
            if (!$wrap) {
                $html .= $inner;
                continue;
            }
            $frame = !empty($desc['frame']);
            $icon = (string)(($data['icon'] ?? '') ?: ($desc['icon'] ?? ''));
            $html .= $tpl->getHtmlFrag('section', [
                'is_frame' => $frame,
                'head' => $frame ? ['num' => sprintf('%02d', ++$num), 'icon' => $icon, 'title' => $data['title'] ?? '', 'lead' => $data['lead'] ?? ''] : [],
                'anchor' => (preg_match(self::ANCHOR, (string)($sec['anchor'] ?? '')) === 1) ? $sec['anchor'] : '',
                'no_phone' => array_key_exists('phone', $sec) && empty($sec['phone']),
                'no_desk' => array_key_exists('desk', $sec) && empty($sec['desk']),
                'body_html' => $inner,
            ]);
        }
        return $slot ? $html : $html.$body;
    }

    # The items of a menu the theme declares, as deep as it declares, that the viewer may see; the title, the address and the icon checked as fields of a section
    public function getMenu(string $key): array {
        $walk = function (array $list, int $lev) use (&$walk): array {
            $out = [];
            foreach ($list as $item) {
                if (!is_array($item) || !self::isViewShown((int)($item['who'] ?? 0), $this->user, $this->moder)) continue;
                $icon = (string)($item['icon'] ?? '');
                $out[] = [
                    'id' => (int)($item['id'] ?? 0),
                    'title' => $this->getTypeValue('title', 'text', [], $item['title'] ?? ''),
                    'href' => $this->getTypeValue('href', 'url', [], $item['href'] ?? ''),
                    'icon' => (preg_match(self::ICON, $icon) === 1) ? $icon : '',
                    'blank' => !empty($item['blank']),
                    'kids' => ($lev > 1) ? $walk(is_array($item['kids'] ?? null) ? $item['kids'] : [], $lev - 1) : [],
                ];
            }
            return $out;
        };
        $depth = (int)($this->getThemePlaces()['menus'][$key]['depth'] ?? 0);
        $list = $this->data['menus'][$key] ?? [];
        return ($depth > 0 && is_array($list)) ? $walk($list, $depth) : [];
    }

    # The values of the parts the theme declares, by their fields and lists checked as those of a section; a value no part of the theme names stays in the layout unprinted
    public function getThemeData(Parser $prs): array {
        if ($this->parts !== null) return $this->parts;
        $vals = $this->data['theme'];
        $out = [];
        foreach ($this->getThemePlaces()['parts'] as $part) {
            if (!is_array($part)) continue;
            $out += $this->getFieldData(is_array($part['fields'] ?? null) ? $part['fields'] : [], $vals, $prs);
            $list = is_array($part['items'] ?? null) ? $part['items'] : [];
            $key = (string)($list['key'] ?? '');
            if (preg_match(self::FIELD, $key) === 1) $out[$key] = $this->getItemData($list, $vals[$key] ?? null, $prs);
        }
        return $this->parts = $out;
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
        if ($this->places !== null) return $this->places;
        $file = $this->base.'/places.json';
        $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $out = [];
        foreach (['parts', 'menus', 'zones'] as $key) $out[$key] = is_array($data[$key] ?? null) ? $data[$key] : [];
        return $this->places = $out;
    }

    # Whether a section is switched on, today lies within its dates, its audience on the scale of _blocks.view holds the viewer, and its language is the visitor's
    private function isSectionShown(array $sec): bool {
        $lang = (string)($sec['lang'] ?? '');
        if ($lang !== '' && $this->lang !== '' && $lang !== $this->lang) return false;
        $day = date('Y-m-d');
        $from = (string)($sec['from'] ?? '');
        $till = (string)($sec['till'] ?? '');
        if (($from !== '' && strcmp($from, $day) > 0) || ($till !== '' && strcmp($till, $day) < 0)) return false;
        return !empty($sec['on']) && self::isViewShown((int)($sec['who'] ?? 0), $this->user, $this->moder);
    }

    # The values of a section or an item by the fields of its description: a type of Field checked by Field, a kind of the builder here; a value that fails prints as absent
    private function getFieldData(array $list, array $sec, Parser $prs): array {
        $out = [];
        foreach ($list as $one) {
            $key = is_array($one) ? (string)($one['key'] ?? '') : '';
            if (preg_match(self::FIELD, $key) !== 1) continue;
            $val = $sec[$key] ?? null;
            $type = (string)($one['type'] ?? '');
            if ($type === 'markdown' || $type === 'code') {
                $out[$key.'_html'] = (!is_string($val) || $val === '') ? '' : (($type === 'code') ? $val : $prs->filterDoc($val, true, 'layout'));
                continue;
            }
            if (in_array($type, ['video', 'page', 'point'], true)) {
                $out += $this->getFrameData($key, $type, is_string($val) ? trim($val) : '');
                continue;
            }
            $out[$key] = match ($type) {
                'file' => is_string($val) ? $this->getFileUrl($val) : '',
                'focus' => in_array($val, self::FOCUS, true) ? $val : 'center',
                'icon' => (is_string($val) && preg_match(self::ICON, $val) === 1) ? $val : '',
                'tone' => in_array($val, self::TONES, true) ? $val : '',
                default => $this->getTypeValue($key, $type, $one, $val),
            };
            if ($type === 'select' && $out[$key] !== '') $out[$key.'_'.$out[$key]] = true;
            if ($type === 'url' && preg_match('#^https?://#i', $out[$key]) === 1) $out[$key.'_ext'] = true;
        }
        return $out;
    }

    # The items of a section by the description of its list, no more than its max, each with its place in the list as two digits and the mark of the first
    private function getItemData(array $desc, mixed $list, Parser $prs): array {
        $out = [];
        $max = max(0, (int)($desc['max'] ?? 0));
        $fields = is_array($desc['fields'] ?? null) ? $desc['fields'] : [];
        foreach (is_array($list) ? $list : [] as $item) {
            if (count($out) >= $max) break;
            if (is_array($item)) $out[] = $this->getFieldData($fields, $item, $prs) + ['order_num' => sprintf('%02d', count($out) + 1), 'is_first' => $out === []];
        }
        return $out;
    }

    # The addresses of an outside frame a field of the kinds video, page and point gives; the frame of a film is the privacy mode of YouTube, which plays on the click that loads it
    private function getFrameData(string $key, string $type, string $val): array {
        $out = [$key.'_frame' => '', $key.'_file' => '', $key.'_host' => ''];
        if ($type === 'video') {
            if (preg_match(self::TUBE, $val, $hit) === 1) $out[$key.'_frame'] = 'https://www.youtube-nocookie.com/embed/'.$hit[1].'?autoplay=1';
            elseif (preg_match('/\.(?:mp4|webm)$/iD', $val) === 1) $out[$key.'_file'] = $this->getFileUrl($val);
            return $out;
        }
        if ($type === 'point') {
            if (preg_match(self::POINT, $val, $hit) !== 1 || abs((float)$hit[1]) > 90 || abs((float)$hit[2]) > 180) return $out;
            [$lat, $lon] = [(float)$hit[1], (float)$hit[2]];
            $box = implode(',', array_map(static fn(float $v): string => number_format($v, 4, '.', ''), [$lon - 0.016, $lat - 0.01, $lon + 0.016, $lat + 0.01]));
            $out[$key.'_frame'] = 'https://www.openstreetmap.org/export/embed.html?bbox='.$box.'&layer=mapnik&marker='.$hit[1].','.$hit[2];
            return $out;
        }
        $part = parse_url($val);
        if (!is_array($part) || strtolower($part['scheme'] ?? '') !== 'https' || ($part['host'] ?? '') === '' || isset($part['user'])) return $out;
        if (filter_var($val, FILTER_VALIDATE_URL) === false) return $out;
        return [$key.'_frame' => $val, $key.'_host' => strtolower($part['host'])] + $out;
    }

    # One value of a type of Field, made canonical by Field itself; the key stands for the label of an option, whose constant of the panel the site never loads
    private function getTypeValue(string $key, string $type, array $one, mixed $val): mixed {
        $none = in_array($type, ['bool', 'int'], true) ? null : '';
        $opts = [];
        if ($type === 'select') {
            $items = [];
            $list = is_array($one['options'] ?? null) ? array_keys($one['options']) : [];
            foreach ($list as $opt) $items[$opt] = ['title' => (string)$opt, 'active' => true, 'sort' => count($items)];
            $opts['items'] = $items;
        }
        foreach (['min', 'max'] as $lim) if (isset($one[$lim])) $opts[$lim] = $one[$lim];
        $rule = ['title' => $key, 'intro' => '', 'type' => $type, 'default' => $none, 'options' => $opts, 'req' => false, 'multi' => false, 'active' => true, 'sort' => 0];
        $this->fld ??= new Field();
        try {
            $out = $this->fld->filterFieldValues([$key => $rule], [$key => $val])[$key] ?? $none;
        } catch (InvalidArgumentException) {
            $out = $none;
        }
        return ($type === 'bool') ? (bool)$out : $out;
    }

    # The address of a picture by its path below uploads/, only in a public folder of the uploads and never a step out of it
    private function getFileUrl(string $path): string {
        if (preg_match('#^[a-z0-9][a-z0-9_-]*(?:/[A-Za-z0-9][A-Za-z0-9_.-]*)+$#D', $path) !== 1 || str_contains($path, '..')) return '';
        return getUploadUrl($path);
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
