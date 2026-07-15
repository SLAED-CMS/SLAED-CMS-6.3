<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorToastTemplate extends Template {
    # Set isolated source and cache paths for the Toast UI plugin templates
    public function __construct() {
        $root = defined('BASE_DIR') ? BASE_DIR : dirname(__DIR__, 3);
        $root = str_replace('\\', '/', rtrim($root, '\\/'));
        $this->theme = 'toastui-editor';
        $this->base = $root.'/plugins/editors/toastui/templates';
        $this->cache = $root.'/storage/cache/templates/toastui-editor';
        $base = realpath($this->base);
        $this->real = ($base !== false) ? rtrim(str_replace('\\', '/', $base), '/') : '';
    }

    # Resolve a validated partial name directly inside the plugin template directory
    protected function getFile(string $type, string $name): string {
        if ($type !== 'partials' || !$this->checkName($name)) return '';
        return $this->base.'/'.$name.'.html';
    }
}

class EditorToastUi implements ContentDriver {
    private static bool $done = false;
    private static ?EditorToastTemplate $edit = null;

    private function getLocale(): array {
        $map = [
            'de' => ['de-DE', 'de-de.js'],
            'en' => ['en-US', 'en-us.js'],
            'fr' => ['fr-FR', 'fr-fr.js'],
            'pl' => ['pl-PL', 'pl-pl.js'],
            'ru' => ['ru-RU', 'ru-ru.js'],
            'uk' => ['uk-UA', 'uk-ua.js'],
        ];
        return $map[substr(_LOCALE, 0, 2)] ?? $map['en'];
    }

    private function getEmojiLabels(): array {
        $map = [
            'de' => ['Zuletzt', 'Smileys', 'Reaktionen', 'Hinweise', 'Symbole', 'Keine Emoji'],
            'en' => ['Recent', 'Smileys', 'Reactions', 'Notices', 'Symbols', 'No emoji'],
            'fr' => ['Recents', 'Smileys', 'Reactions', 'Notes', 'Symboles', 'Aucun emoji'],
            'pl' => ['Ostatnie', 'Emotikony', 'Reakcje', 'Notatki', 'Symbole', 'Brak emoji'],
            'ru' => ['Недавние', 'Смайлы', 'Реакции', 'Заметки', 'Символы', 'Нет emoji'],
            'uk' => ['Останні', 'Смайли', 'Реакції', 'Нотатки', 'Символи', 'Немає emoji'],
        ];
        $row = $map[substr(_LOCALE, 0, 2)] ?? $map['en'];
        return [
            'recent' => $row[0],
            'smileys' => $row[1],
            'reactions' => $row[2],
            'notices' => $row[3],
            'symbols' => $row[4],
            'empty' => $row[5],
        ];
    }

    public function getAssets(string $profile): string {
        global $tpl;
        if (self::$done) return '';
        self::$done = true;
        $locale = $this->getLocale();
        $assets = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/toastui/assets/toastui-editor.min.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/toastui/assets/slaed-icons.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/toastui-editor.all.min.js', 'attr' => '']);
        if ($locale[1] !== '') $assets .= $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/i18n/'.$locale[1], 'attr' => '']);
        return $assets
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-tags.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-emoji.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-upload.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $edit = self::$edit ??= new EditorToastTemplate();
        $jid = json_encode($id);
        $jval = json_encode($value);
        $jph = json_encode((string)($data['placeholder'] ?? ''));
        $mode = '"markdown"';
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $high = (int)($data['height'] ?? 0);
        if ($high <= 0) {
            $high = ($rows >= 15) ? 500 : (($rows >= 10) ? 300 : 250);
        }
        $height = max(250, $high);
        $h = '"'.$height.'px"';
        $focus = !empty($data['autofocus']) ? 'true' : 'false';
        $locale = $this->getLocale();
        $lang = json_encode($locale[0]);
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $ihid = $id.'_toast_image_head';
        $lhid = $id.'_toast_link_head';
        $ehid = $id.'_toast_emoji_head';
        $ta = $tpl->getHtmlFrag('textarea', [
            'name_attr' => $name,
            'rows_num' => $rows,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'input_attr' => 'id="'.$eid.'" style="display:none"',
        ]);
        $ta .= '<div id="'.$eid.'_toast"></div>';
        $mod = strtolower((string)($data['mod'] ?? ''));
        $con = (array)($data['con'] ?? []);
        $upl = $mod !== '' && (is_moder($mod) || (is_user() && (int)($con[10] ?? 0) === 1) || (!is_user() && (int)($con[11] ?? 0) === 1));
        $panel = '';
        $emoji = $this->getEmojiLabels();
        $opt = [
            'admin' => isAdmin(),
            'labels' => [
                'quote' => _EQUOTE,
                'hide' => _HIDE,
                'tabs' => 'Tabs',
                'emoji' => _EEMOJI,
                'html' => _EUSEHTML,
                'php' => _EUSEPHP,
                'files' => _FILES,
                'fullscreen' => _EFULLSCREEN,
                'exitfull' => _EEXITFULL,
                'image' => _IMG,
                'attach' => _EATTACH,
                'nofiles' => _NO.' '._FILES,
                'upload' => _ERROR_UP,
                'load' => _ERROR,
                'uploaded' => _FILE_RENAMED,
                'fileup' => _FILEUP,
                'nofile' => _ENOFILE,
                'dropfiles' => _EDROPFILES,
                'emoji_recent' => $emoji['recent'],
                'emoji_smileys' => $emoji['smileys'],
                'emoji_reactions' => $emoji['reactions'],
                'emoji_notices' => $emoji['notices'],
                'emoji_symbols' => $emoji['symbols'],
                'emoji_empty' => $emoji['empty'],
            ],
            'imagehead' => $ihid,
            'linkhead' => $lhid,
            'emojihead' => $ehid,
        ];
        $wins = $edit->getHtmlPart('dialog-headers', [
            'editor_id' => $eid,
            'image_head_id' => htmlspecialchars($ihid, ENT_QUOTES, 'UTF-8'),
            'link_head_id' => htmlspecialchars($lhid, ENT_QUOTES, 'UTF-8'),
            'emoji_head_id' => htmlspecialchars($ehid, ENT_QUOTES, 'UTF-8'),
            'image_label' => _INSERTIMG,
            'link_label' => _EINSLINK,
            'emoji_label' => _EEMOJI,
            'close_label' => _CLOSE,
            'move_label' => _EMOVEWIN,
            'expand_label' => _EEXPAND,
            'restore_label' => _ERESTORE,
        ]);
        if ($upl) {
            $tok = getSiteToken('upload');
            $atk = getSiteToken();
            $pid = $id.'_toast_upload';
            $mid = $id.'_toast_msg';
            $lid = $id.'_toast_files';
            $fid = $id.'_toast_file';
            $tid = $id.'_toast_title';
            $oid = $id.'_toast_object';
            $opt += [
                'token' => $tok,
                'panel' => $pid,
                'msg' => $mid,
                'list' => $lid,
                'object' => $oid,
                'maxfiles' => (int)($con[5] ?? 0),
                'upload' => 'index.php?go=4&op=editorUpload&mod='.rawurlencode($mod),
                'files' => 'index.php?go=4&op=editorFiles&mod='.rawurlencode($mod).'&token='.rawurlencode($atk),
            ];
            $panel = $edit->getHtmlPart('file-manager', [
                'panel_id' => htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'),
                'title_id' => htmlspecialchars($tid, ENT_QUOTES, 'UTF-8'),
                'msg_id' => htmlspecialchars($mid, ENT_QUOTES, 'UTF-8'),
                'list_id' => htmlspecialchars($lid, ENT_QUOTES, 'UTF-8'),
                'file_id' => htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'),
                'object_id' => htmlspecialchars($oid, ENT_QUOTES, 'UTF-8'),
                'editor_id' => $eid,
                'title_label' => _EUPLOAD,
                'close_label' => _CLOSE,
                'move_label' => _EMOVEWIN,
                'expand_label' => _EEXPAND,
                'restore_label' => _ERESTORE,
                'object_label' => _EINSOBJ,
                'object_info' => _EINSOBJINFO,
                'select_label' => _ESELFILE,
                'nofile_label' => _ENOFILE,
                'drop_label' => _EDROPFILES,
                'type_label' => _FTYPE,
                'types_text' => str_replace(',', ', ', (string)($con[0] ?? '')),
                'allsize_label' => _FSIZEALL,
                'allsize_text' => filterSize((int)($con[1] ?? 0)),
                'filesize_label' => _FSIZE,
                'filesize_text' => filterSize((int)($con[2] ?? 0)),
                'width_label' => _AWIDTH,
                'width_text' => (int)($con[3] ?? 0).' px',
                'height_label' => _AHEIGHT,
                'height_text' => (int)($con[4] ?? 0).' px',
                'count_label' => _FILEUP,
                'count_text' => (int)($con[5] ?? 0),
                'refresh_label' => _UPDATE,
            ]);
        }
        $jopt = json_encode($opt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $js = '(function(){var ta=document.getElementById('.$jid.');var root=window.toastui&&window.toastui.Editor;';
        $js .= 'if(!ta||!root){return;}';
        $js .= 'var ed=new root({el:document.getElementById('.$jid.'+"_toast"),';
        $js .= 'initialEditType:'.$mode.',initialValue:'.$jval.',placeholder:'.$jph.',height:'.$h.',language:'.$lang.',autofocus:'.$focus.',usageStatistics:false});';
        $js .= 'if(window.SlaedToastUi){window.SlaedToastUi.register('.$jid.',ed,'.$jopt.');}';
        $js .= 'if('.$focus.'){setTimeout(function(){var box=document.getElementById('.$jid.'+"_toast");';
        $js .= 'var foc=box&&box.querySelector(".toastui-editor-contents[contenteditable=true],.ProseMirror.toastui-editor-contents,"+';
        $js .= '".toastui-editor textarea:not(.toastui-editor-pseudo-clipboard)");';
        $js .= 'if(foc){foc.focus();}else{try{ed.focus();}catch(e){}}},300);}';
        $js .= 'ta.form&&ta.form.addEventListener("submit",function(){ta.value=ed.getMarkdown();},true);';
        $js .= '})();';
        return $ta.$panel.$wins.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
