<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorToastUi implements ContentDriver {
    private static bool $done = false;

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
        return $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/toastui/assets/toastui-editor.min.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/toastui/assets/slaed-icons.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/toastui-editor.all.min.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/i18n/ru-ru.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-tags.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-emoji.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/slaed-upload.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $jid = json_encode($id);
        $jval = json_encode($value);
        $mode = '"markdown"';
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $high = (int)($data['height'] ?? 0);
        if ($high <= 0) {
            $high = ($rows >= 15) ? 500 : (($rows >= 10) ? 300 : 250);
        }
        $height = max(250, $high);
        $h = '"'.$height.'px"';
        $focus = !empty($data['autofocus']) ? 'true' : 'false';
        $lang = (substr(_LOCALE, 0, 2) === 'ru') ? '"ru-RU"' : '"en-US"';
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
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
                'emoji' => 'Emoji',
                'html' => _EUSEHTML,
                'php' => _EUSEPHP,
                'files' => _FILES,
                'image' => _IMG,
                'attach' => _INSERT,
                'nofiles' => _NO.' '._FILES,
                'upload' => _ERROR_UP,
                'load' => _ERROR,
                'uploaded' => _FILE_RENAMED,
                'emoji_recent' => $emoji['recent'],
                'emoji_smileys' => $emoji['smileys'],
                'emoji_reactions' => $emoji['reactions'],
                'emoji_notices' => $emoji['notices'],
                'emoji_symbols' => $emoji['symbols'],
                'emoji_empty' => $emoji['empty'],
            ],
        ];
        if ($upl) {
            $tok = getSiteToken('upload');
            $atk = getSiteToken();
            $pid = $id.'_toast_upload';
            $mid = $id.'_toast_msg';
            $lid = $id.'_toast_files';
            $fid = $id.'_toast_file';
            $opt += [
                'token' => $tok,
                'panel' => $pid,
                'msg' => $mid,
                'list' => $lid,
                'upload' => 'index.php?go=4&op=editorUpload&mod='.rawurlencode($mod),
                'files' => 'index.php?go=4&op=editorFiles&mod='.rawurlencode($mod).'&token='.rawurlencode($atk),
            ];
            $panel = $tpl->getHtmlPart('toastui-upload-panel', [
                'panel_id' => htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'),
                'msg_id' => htmlspecialchars($mid, ENT_QUOTES, 'UTF-8'),
                'list_id' => htmlspecialchars($lid, ENT_QUOTES, 'UTF-8'),
                'file_id' => htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'),
                'editor_id' => $eid,
                'refresh_label' => _UPDATE,
            ]);
        }
        $jopt = json_encode($opt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $js = '(function(){var ta=document.getElementById('.$jid.');var root=window.toastui&&window.toastui.Editor;';
        $js .= 'if(!ta||!root){return;}';
        $js .= 'var ed=new root({el:document.getElementById('.$jid.'+"_toast"),';
        $js .= 'initialEditType:'.$mode.',initialValue:'.$jval.',height:'.$h.',language:'.$lang.',autofocus:'.$focus.',usageStatistics:false});';
        $js .= 'if(window.SlaedToastUi){window.SlaedToastUi.register('.$jid.',ed,'.$jopt.');}';
        $js .= 'if('.$focus.'){setTimeout(function(){var box=document.getElementById('.$jid.'+"_toast");var foc=box&&box.querySelector(".toastui-editor-contents[contenteditable=true],.ProseMirror.toastui-editor-contents,.toastui-editor textarea:not(.toastui-editor-pseudo-clipboard)");if(foc){foc.focus();}else{try{ed.focus();}catch(e){}}},300);}';
        $js .= 'ta.form&&ta.form.addEventListener("submit",function(){ta.value=ed.getMarkdown();},true);';
        $js .= '})();';
        return $ta.$panel.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
