<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorToastUi implements ContentDriver {
    private static bool $done = false;

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

    public function getAssets(string $profile): string {
        global $tpl;
        if (self::$done) return '';
        self::$done = true;
        $locale = $this->getLocale();
        $assets = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/toastui/assets/toastui-editor.min.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/toastui-editor.all.min.js', 'attr' => '']);
        if ($locale[1] !== '') $assets .= $tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/i18n/'.$locale[1], 'attr' => '']);
        $ewords = 'plugins/editors/toastui/assets/i18n/emoji-'.substr(_LOCALE, 0, 2).'.js';
        if (is_file($ewords)) $assets .= $tpl->getHtmlFrag('head-script-src', ['src' => $ewords, 'attr' => '']);
        $assets .= $tpl->getHtmlPart('editor-toastui-templates', []);
        return $assets
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-tags.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-emoji.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-upload.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
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
        $rul = (array)($data['rule'] ?? []);
        $upl = $mod !== '' && checkEditorUploadAccess($mod, $rul);
        $panel = '';
        $opt = [
            'super' => isAdmin(true),
            'tpl' => 'js-slaed-editor-tpl',
            'labels' => [
                'quote' => _EQUOTE,
                'hide' => _HIDE,
                'tabs' => _ETABS,
                'emoji' => _EEMOJI,
                'html' => _EUSEHTML,
                'php' => _EUSEPHP,
                'files' => _FILES,
                'fullscreen' => _EFULLSCREEN,
                'exitfull' => _EEXITFULL,
                'image' => _IMG,
                'attach' => _EATTACH,
                'nofiles' => _NO_INFO,
                'upload' => _ERROR_UP,
                'load' => _ERROR,
                'uploaded' => _FILE_RENAMED,
                'fileup' => _FILEUP,
                'nofile' => _ENOFILE,
                'dropfiles' => _EDROPFILES,
                'emoji_recent' => _EEMOJIRECENT,
                'emoji_smileys' => _EEMOJISMILE,
                'emoji_reactions' => _EEMOJIREACT,
                'emoji_notices' => _EEMOJINOTICE,
                'emoji_symbols' => _EEMOJISYMBOL,
                'emoji_empty' => _EEMOJIEMPTY,
            ],
            'imagehead' => $ihid,
            'linkhead' => $lhid,
            'emojihead' => $ehid,
        ];
        $wins = $tpl->getHtmlPart('editor-toastui-dialogs', [
            'editor_id' => $eid,
            'windows' => [
                ['name' => 'image', 'head_id' => $ihid, 'label' => _INSERTIMG],
                ['name' => 'link', 'head_id' => $lhid, 'label' => _EINSLINK],
                ['name' => 'emoji', 'head_id' => $ehid, 'label' => _EEMOJI],
            ],
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
            $opt['labels']['toobig'] = _ERROR_SIZE;
            $opt += [
                'embedmax' => Parser::EMBEDMAX,
                'token' => $tok,
                'panel' => $pid,
                'msg' => $mid,
                'list' => $lid,
                'object' => $oid,
                'maxfiles' => (int)($rul['maxfiles'] ?? 0),
                'upload' => 'index.php?go=4&op=editorUpload&mod='.rawurlencode($mod),
                'files' => 'index.php?go=4&op=editorFiles&mod='.rawurlencode($mod).'&token='.rawurlencode($atk),
            ];
            $panel = $tpl->getHtmlPart('editor-toastui-files', [
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
                'upload_label' => _EMODEUPLOAD,
                'upload_info' => _EMODEUPLOADINFO,
                'embed_label' => _EMODEEMBED,
                'embed_info' => _EMODEEMBEDINFO,
                'select_label' => _ESELFILE,
                'nofile_label' => _ENOFILE,
                'drop_label' => _EDROPFILES,
                'type_label' => _FTYPE,
                'types_text' => str_replace(',', ', ', (string)($rul['extensions'] ?? '')),
                'allsize_label' => _FSIZEALL,
                'allsize_text' => filterSize((int)($rul['maxquota'] ?? 0)),
                'filesize_label' => _FSIZE,
                'filesize_text' => filterSize((int)($rul['maxbytes'] ?? 0)),
                'width_label' => _AWIDTH,
                'width_text' => (int)($rul['maxwidth'] ?? 0).' px',
                'height_label' => _AHEIGHT,
                'height_text' => (int)($rul['maxheight'] ?? 0).' px',
                'count_label' => _FILEUP,
                'count_text' => (int)($rul['maxfiles'] ?? 0),
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
        $js .= 'var sync=function(){ta.value=ed.getMarkdown();};';
        $js .= 'ed.on("change",sync);ed.on("blur",sync);';
        $js .= 'ta.form&&ta.form.addEventListener("submit",sync,true);';
        $js .= '})();';
        return $ta.$panel.$wins.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
