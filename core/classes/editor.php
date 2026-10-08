<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# Contract for all content editors
interface ContentDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string;
}

# Contract for all code/syntax editors; the label is the accessible name of the editable area and is never empty
interface CodeDriver {
    public function getAssets(string $profile): string;

    public function getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label): string;
}

class Editor {
    private static array $mdata = [];
    private static array $drvs = [];
    private static bool $done = false;
    private static bool $emoji = false;
    private const LANGS = [
        'php' => ['PHP', 'filetype-php'],
        'html' => ['HTML', 'filetype-html'],
        'css' => ['CSS', 'filetype-css'],
        'js' => ['JavaScript', 'filetype-js'],
        'json' => ['JSON', 'filetype-json'],
        'sql' => ['SQL', 'filetype-sql'],
        'xml' => ['XML', 'filetype-xml'],
        'markdown' => ['Markdown', 'markdown'],
        'ini' => ['INI', 'sliders'],
        'apache' => ['Apache', 'server'],
        'robots' => ['TXT', 'robot'],
        'plain' => ['', 'file-text'],
        'text' => ['', 'file-text'],
    ];
    private const PHRASES = [
        'Find' => '_EDITOR_FIND',
        'Replace' => '_EDITOR_REPLACE',
        'next' => '_EDITOR_NEXT',
        'previous' => '_EDITOR_PREV',
        'all' => '_ALL',
        'match case' => '_EDITOR_CASE',
        'regexp' => '_EDITOR_REGEXP',
        'by word' => '_EDITOR_WORD',
        'replace' => '_EDITOR_REPLONE',
        'replace all' => '_EDITOR_REPLALL',
        'close' => '_CLOSE',
        'current match' => '_EDITOR_CURMATCH',
        'on line' => '_EDITOR_ONLINE',
        'replaced $ matches' => '_EDITOR_REPLACED',
        'replaced match on line $' => '_EDITOR_REPLINE',
        'Folded lines' => '_EDITOR_FOLDED',
        'Unfolded lines' => '_EDITOR_UNFOLDED',
        'to' => '_EDITOR_TO',
        'folded code' => '_EDITOR_FOLDCODE',
        'unfold' => '_EDITOR_UNFOLD',
        'Fold line' => '_EDITOR_FOLDLINE',
        'Unfold line' => '_EDITOR_UNFOLDLN',
        'Go to line' => '_EDITOR_GOTOLINE',
        'go' => '_EDITOR_GO',
        'Diagnostics' => '_EDITOR_LINT',
        'No diagnostics' => '_EDITOR_NOLINT',
        'Completions' => '_EDITOR_COMPLETE',
        'Control character' => '_EDITOR_CTRLCHAR',
        'Selection deleted' => '_EDITOR_SELDEL',
    ];

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
            if ($fmts && !in_array($fmt, $fmts, true)) {
                $key = self::getEditorKey($role);
                $driver = self::getDriver($key);
            }
        }
        if (!$driver instanceof ContentDriver) {
            $key = 'plain';
            $driver = self::getDriver($key);
        }
        $id = (string)($data['id'] ?? 'editor');
        $name = (string)($data['name'] ?? 'text');
        $value = (string)($data['value'] ?? '');
        $data = self::getNameData($data);
        return $driver instanceof ContentDriver ? $driver->getAssets($profile).self::getThemeSkin($key).$driver->getWidget($id, $name, $value, $profile, $data) : '';
    }

    # Settle the accessible name of one editor once, so four drivers cannot answer it four ways and none of them has to guess
    # A caption of its own row is pointed at, because a reference follows the caption when it changes; a row with no caption at all is named by its own text instead
    # An empty target is a worse answer than no attribute, since the name computes to the empty string with the attribute visibly in place, so the text can never be empty here
    # The text is kept beside the reference, not instead of it: TinyMCE puts its editable in a second document where an IDREF resolves to nothing, so only a text copy crosses
    private static function getNameData(array $data): array {
        $data['labelledby'] = (string)($data['labelledby'] ?? '');
        $data['label'] = (string)($data['label'] ?? '');
        if ($data['label'] === '') $data['label'] = _TEXT;
        $data['arialabel'] = ($data['labelledby'] === '') ? $data['label'] : '';
        $data['describedby'] = (string)($data['describedby'] ?? '');
        return $data;
    }

    # Render code editor widget; lang required, validated against manifest
    # The label names the editable area for a screen reader, and falls back to the same generic text a content editor gets, since no code editor has a caption of its own
    public static function getCode(array $data): string {
        global $conf;
        $key = (string)($conf['editor']['code'] ?? 'codemirror');
        $profile = (string)($data['profile'] ?? 'full');
        $lang = (string)($data['lang'] ?? 'text');
        $man = self::getManifest($key);
        if (!$man || !($man['enabled'] ?? true) || !self::checkType($man, 'code')
            || !in_array('admin', (array)($man['roles'] ?? []), true)) {
            $key = 'codemirror';
            $man = self::getManifest($key);
        }
        $list = (array)($man['lang'] ?? []);
        if ($list && !in_array($lang, $list, true)) $lang = 'text';
        $driver = self::getDriver($key);
        if (!$driver instanceof CodeDriver) {
            $key = 'codemirror';
            $driver = self::getDriver($key);
        }
        $id = (string)($data['id'] ?? 'code');
        $name = (string)($data['name'] ?? '');
        $value = (string)($data['text'] ?? '');
        $label = (string)($data['label'] ?? '');
        if ($label === '') $label = _TEXT;
        return $driver instanceof CodeDriver ? $driver->getAssets($profile).self::getThemeSkin($key).$driver->getWidget($id, $name, $value, $lang, $profile, $label) : '';
    }

    # Render one editor in the shell: a text with the emoji panel, the room of a text column and, where its field names an upload place, the file window; the kit once per answer
    public static function getFrame(array $data): string {
        global $tpl;
        $lang = (string)($data['lang'] ?? 'text');
        [$name, $icon] = self::LANGS[$lang] ?? self::LANGS['text'];
        $code = !empty($data['code']);
        $kit = (!$code && ($data['mod'] ?? '') !== '') ? getEditorFileKit((string)($data['id'] ?? ''), $data) : ['opt' => [], 'html' => ''];
        $room = (array)($data['room'] ?? []);
        $head = '';
        if (!self::$done) {
            self::$done = true;
            $head = self::getAssetTags([], ['plugins/system/editor.js']).$tpl->getHtmlFrag('editor-kit', self::getKitData());
        }
        if (!$code) $head .= self::getEmojiPanel();
        return $head.$tpl->getHtmlFrag('editor-frame', [
            'tab_text' => (string)($data['label'] ?? ''),
            'icon_name' => $icon,
            'lang_text' => $name,
            'lang_key' => $lang,
            'files_json' => $kit['opt'] ? json_encode($kit['opt'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            'room_num' => (!$code && ($room['kind'] ?? '') !== 'mediumtext') ? (int)($room['bytes'] ?? 0) : 0,
            'is_code' => $code,
            'core_url' => (string)($data['core'] ?? ''),
            'grammar_url' => (string)($data['grammar'] ?? ''),
            'name_attr' => (string)($data['name'] ?? ''),
            'input_id' => (string)($data['id'] ?? ''),
            'value_text' => (string)($data['value'] ?? ''),
            'rows_num' => (int)($data['rows'] ?? 0),
            'placeholder_text' => (string)($data['placeholder'] ?? ''),
            'is_required' => !empty($data['required']),
            'labelledby' => (string)($data['labelledby'] ?? ''),
            'aria_label' => (string)($data['arialabel'] ?? ''),
            'describedby' => (string)($data['describedby'] ?? ''),
            'dirty_text' => _EDITOR_DIRTY,
        ]).$kit['html'];
    }

    # The titles of the capsule and the palette and the words of the status line and of the CodeMirror panels, which the runtime reads from the kit and never holds itself
    private static function getKitData(): array {
        $words = [
            'tools' => _EDITOR_TOOLS,
            'pos' => _EDITOR_POS,
            'sel' => _EDITOR_SEL,
            'size' => _EDITOR_SIZE,
            'copied' => _COPYDONE,
            'noclip' => _EDITOR_NOCLIP,
            'restored' => _EDITOR_RESTORED,
            'recent' => _EDITOR_RECENT,
            'left' => _EDITOR_LEFT,
            'long' => _ETEXTLONG,
            'phrases' => array_map(constant(...), self::PHRASES),
        ];
        return [
            'words_json' => json_encode($words, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'undo_text' => _EDITOR_UNDO,
            'redo_text' => _EDITOR_REDO,
            'search_text' => _EDITOR_SEARCH,
            'fold_text' => _EDITOR_FOLDALL,
            'unfold_text' => _EDITOR_UNFOLDALL,
            'bold_text' => _EDITOR_BOLD,
            'italic_text' => _EDITOR_ITALIC,
            'code_text' => _CODE,
            'link_text' => _URL,
            'list_text' => _LIST,
            'quote_text' => _QUOTE,
            'files_text' => _EUPLOAD,
            'upload_text' => _EDITOR_FILES,
            'emoji_text' => _EEMOJI,
            'wrap_text' => _EDITOR_WRAP,
            'copy_text' => _COPY,
            'reset_text' => _EDITOR_RESET,
            'all_text' => _EDITOR_SELALL,
            'comment_text' => _EDITOR_COMMENT,
            'full_text' => _EFULLSCREEN,
            'cmds_text' => _EDITOR_CMDS,
            'find_text' => _EDITOR_CMDFIND,
            'edit_text' => _EDITOR_GRPEDIT,
            'format_text' => _EDITOR_GRPFORMAT,
            'insert_text' => _EDITOR_GRPINSERT,
            'view_text' => _EDITOR_GRPVIEW,
            'none_text' => _EDITOR_NONE,
            'keys_text' => _EDITOR_KEYS,
            'keynext_text' => _EDITOR_KEYNEXT,
            'keyline_text' => _EDITOR_KEYLINE,
            'keymove_text' => _EDITOR_KEYMOVE,
            'keydup_text' => _EDITOR_KEYDUP,
            'keydel_text' => _EDITOR_KEYDEL,
            'keyindent_text' => _EDITOR_KEYINDENT,
            'keypair_text' => _EDITOR_KEYPAIR,
            'keycaret_text' => _EDITOR_KEYCARET,
            'keylist_text' => _EDITOR_KEYLIST,
            'keyesc_text' => _EDITOR_KEYESC,
            'pick_text' => _EDITOR_PALPICK,
            'run_text' => _EDITOR_PALRUN,
            'shut_text' => _EDITOR_PALSHUT,
            'any_text' => _EDITOR_PALANY,
            'close_text' => _CLOSE,
            'move_text' => _EMOVEWIN,
            'expand_text' => _EEXPAND,
            'restore_text' => _ERESTORE,
            'draft_text' => _EDITOR_DRAFT,
            'back_text' => _EDITOR_DRAFTBACK,
            'drop_text' => _EDITOR_DRAFTDROP,
        ];
    }

    # Print the templates of the emoji panel with its words and the addresses its script loads by, once per answer for every engine that offers it
    public static function getEmojiPanel(): string {
        global $tpl;
        if (self::$emoji) return '';
        self::$emoji = true;
        $words = 'plugins/system/emoji/'.substr(_LOCALE, 0, 2).'.js';
        $src = [Template::getAssetUrl('plugins/system/emoji.js')];
        if (Template::getAssetUrl($words) !== $words) $src[] = Template::getAssetUrl($words);
        $lab = [
            'emoji' => _EEMOJI,
            'recent' => _EEMOJIRECENT,
            'smileys' => _EEMOJISMILE,
            'reactions' => _EEMOJIREACT,
            'notices' => _EEMOJINOTICE,
            'symbols' => _EEMOJISYMBOL,
            'empty' => _EEMOJIEMPTY,
        ];
        return $tpl->getHtmlPart('emoji-panel', [
            'words_json' => json_encode($lab, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'src_json' => json_encode($src, JSON_UNESCAPED_SLASHES),
            'emoji_label' => _EEMOJI,
            'close_label' => _CLOSE,
            'move_label' => _EMOVEWIN,
            'expand_label' => _EEXPAND,
            'restore_label' => _ERESTORE,
        ]);
    }

    # Render editor select dropdown for settings UI
    public static function getSelect(string $name, string $selected, string $type, string $role, string $selectAttr = ''): string {
        global $tpl;
        $list = self::getEditorList($type, $role);
        $html = '';
        foreach ($list as $id => $man) {
            $html .= $tpl->getHtmlFrag('select-option', [
                'value_attr' => $id,
                'label_text' => (string)($man['label'] ?? $id),
                'is_selected' => $id === $selected,
            ]);
        }
        return $tpl->getHtmlFrag('select', [
            'name_attr' => $name,
            'select_class' => '',
            'select_attr' => $selectAttr,
            'options_html' => $html,
        ]);
    }

    # Emit the stylesheets and scripts of an editor: plain tags on a page load, where the parser runs them in order before the inline init of an instance
    # A fragment answered over htmx names the same versioned addresses to the client loader, which adds only what the page lacks, so no engine and no listener runs twice
    public static function getAssetTags(array $css, array $js): string {
        global $tpl;
        $css = array_map(Template::getAssetUrl(...), $css);
        $js = array_map(Template::getAssetUrl(...), $js);
        if (($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
            $list = json_encode([$css, $js], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
            return $tpl->getHtmlFrag('head-script-inline', ['js' => 'if(window.SlaedEditors){window.SlaedEditors.load.apply(null,'.$list.');}']);
        }
        $out = '';
        foreach ($css as $one) $out .= $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $one, 'type' => '', 'title' => '']);
        foreach ($js as $one) $out .= $tpl->getHtmlFrag('head-script-src', ['src' => $one, 'attr' => '']);
        return $out;
    }

    # Wrap the init of one editor instance so it runs once its engine is there: after the deferred head on a page load, after the assets of an htmx fragment otherwise
    # The node is captured when the script runs and checked when the init does, so a region swapped away while the engine loaded binds no instance to a removed node
    # The teardown is handed to the client, which destroys every instance inside a region before that region is swapped away or restored
    public static function getInitScript(string $id, string $run, string $kill): string {
        global $tpl;
        $js = '(function(){var el=document.getElementById('.json_encode($id).');if(!el){return;}var go=function(){if(!el.isConnected){return;}'.$run;
        $js .= 'if(window.SlaedEditors){window.SlaedEditors.own(el,function(){'.$kill.'});}};';
        $js .= 'var start=function(){if(window.SlaedEditors){window.SlaedEditors.ready(go);}else{go();}};';
        $js .= 'if(window.SlaedEditors||document.readyState!=="loading"){start();}else{document.addEventListener("DOMContentLoaded",start);}})();';
        return $tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }

    # Emit the active theme skin stylesheet for an editor that declares one in its manifest; deduplicated per theme/editor pair, a declared but missing skin file is logged
    private static function getThemeSkin(string $key): string {
        global $theme;
        static $done = [];
        $man = self::getManifest($key);
        if (empty($man['theme']['skin'])) return '';
        $mark = $theme.':'.$key;
        if (isset($done[$mark])) return '';
        $done[$mark] = true;
        $skin = 'templates/'.$theme.'/assets/editors/'.$key.'/skin.css';
        if (Template::getAssetUrl($skin) === $skin) {
            Logger::addSite('error', 'Editor theme skin missing: '.$skin, ['editor' => $key, 'theme' => (string)$theme]);
            return '';
        }
        return self::getAssetTags([$skin], []);
    }

    # Return parsed manifest for one editor; null if missing, invalid or named apart from its directory, so a key never walks out of it
    public static function getManifest(string $id): ?array {
        if (isset(self::$mdata[$id])) return self::$mdata[$id];
        $path = PUBLIC_DIR.'/plugins/editors/'.$id.'/manifest.json';
        if (!is_file($path)) return null;
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) return null;
        foreach (['id', 'type', 'driver', 'entry', 'roles', 'profiles', 'formats'] as $name) {
            if (!isset($data[$name])) return null;
        }
        if ($data['id'] !== $id) return null;
        self::$mdata[$id] = $data;
        return $data;
    }

    # Return the primary storage format declared by an editor manifest
    public static function getFormat(string $id): string {
        $man = self::getManifest($id);
        $fmts = (array)($man['formats'] ?? []);
        $mode = (string)($fmts[0] ?? 'plain');
        return in_array($mode, ['plain', 'markdown', 'html'], true) ? $mode : 'plain';
    }

    # Whether a manifest serves one editor type, its type given as one string or as a list of them
    private static function checkType(array $man, string $type): bool {
        return in_array($type, (array)($man['type'] ?? []), true);
    }

    # Validate that manifest qualifies as an enabled content editor for the given role
    private static function checkManifest(?array $man, string $role): bool {
        return $man !== null
            && ($man['enabled'] ?? true)
            && self::checkType($man, 'content')
            && in_array($role, (array)($man['roles'] ?? []), true);
    }

    # Public gateway for external callers (e.g. updateAdminEditor() in admin/index.php)
    public static function isValidEditor(string $key, string $role): bool {
        return self::checkManifest(self::getManifest($key), $role);
    }

    # Return available editors filtered by type and role, sorted by priority
    private static function getEditorList(string $type, string $role): array {
        $base = PUBLIC_DIR.'/plugins/editors';
        $list = [];
        if (!is_dir($base)) return $list;
        foreach (scandir($base) as $dir) {
            if ($dir[0] === '.') continue;
            $man = self::getManifest($dir);
            if (!$man) continue;
            if (!($man['enabled'] ?? true)) continue;
            if ($type && !self::checkType($man, $type)) continue;
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
        $path = PUBLIC_DIR.'/plugins/editors/'.$id.'/'.($man['entry'] ?? 'driver.php');
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
