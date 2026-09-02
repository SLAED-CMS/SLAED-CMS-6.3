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
        $assets .= $tpl->getHtmlPart('editor-toastui-templates', [
            'emoji_label' => _EEMOJI,
            'close_label' => _CLOSE,
            'move_label' => _EMOVEWIN,
            'expand_label' => _EEXPAND,
            'restore_label' => _ERESTORE,
        ]);
        return $assets
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-tags.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-emoji.js', 'attr' => '']);
    }

    # Render one editor instance together with the single image and file window that replaces the vendor image dialog and the former file catalogue
    # The window is rendered for every visitor because the address field inside it is, and the sections divide by three flags rather than by a reduced second editor
    # May upload and may list are one server decision, checkEditorUploadAccess(), and are handed down separately because they switch different sections of one markup
    # May embed is not a right at all: it is read off the room the store names, so a summary field offers no embed mode whoever is looking at it
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
        $ta = $tpl->getHtmlFrag('textarea', [
            'name_attr' => $name,
            'rows_num' => $rows,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'describedby' => (string)($data['describedby'] ?? ''),
            'input_attr' => 'id="'.$eid.'" hidden',
        ]);
        $ta .= $tpl->getHtmlFrag('editor-mount', ['id' => $id.'_toast', 'labelledby' => (string)($data['labelledby'] ?? ''), 'aria_label' => (string)($data['arialabel'] ?? ''), 'describedby' => (string)($data['describedby'] ?? '')]);
        $mod = strtolower((string)($data['mod'] ?? ''));
        $plc = ($mod !== '') ? $mod.'.attach' : '';
        $rul = (array)($data['rule'] ?? []);
        $plr = ($plc !== '') ? getUploadPlaceRule($plc) : [];
        $room = (array)($data['room'] ?? []);
        $upl = $mod !== '' && checkEditorUploadAccess($mod, $rul);
        $emb = !empty($room['embed']);
        $mdr = $upl && is_moder($mod);
        $tok = $upl ? getSiteToken('upload') : '';
        $atk = $upl ? getSiteToken() : '';
        $pid = $id.'_toast_upload';
        $mid = $id.'_toast_msg';
        $fid = $id.'_toast_file';
        $tid = $id.'_toast_title';
        $oid = $id.'_toast_object';
        $uid = $id.'_toast_url';
        $aid = $id.'_toast_alt';
        $bid = $id.'_toast_urlalt';
        $cid = $id.'_toast_embed';
        $sid = $id.'_toast_insert';
        $txt = getFileManagerText();
        $opt = [
            'super' => isAdmin(true),
            'labels' => $txt['labels'] + [
                'quote' => _EQUOTE,
                'hide' => _HIDE,
                'tabs' => _ETABS,
                'emoji' => _EEMOJI,
                'html' => _EUSEHTML,
                'php' => _EUSEPHP,
                'fullscreen' => _EFULLSCREEN,
                'exitfull' => _EEXITFULL,
                'image' => _IMG,
                'attach' => _EATTACH,
                'nofiles' => _NO_INFO,
                'uploaded' => _FILE_RENAMED,
                'nofile' => _ENOFILE,
                'emoji_recent' => _EEMOJIRECENT,
                'emoji_smileys' => _EEMOJISMILE,
                'emoji_reactions' => _EEMOJIREACT,
                'emoji_notices' => _EEMOJINOTICE,
                'emoji_symbols' => _EEMOJISYMBOL,
                'emoji_empty' => _EEMOJIEMPTY,
            ],
            'panes' => $txt['panes'] + [
                'emb' => [_EMODEEMBED, _EMODEEMBEDINFO],
            ],
            'embedmax' => Parser::EMBEDMAX,
            'embedimg' => Parser::EMBEDIMG,
            'canupload' => $upl,
            'canlist' => $upl,
            'canembed' => $emb,
            'canlink' => !empty($plr['canlink']),
            'canzip' => $mdr,
            'candel' => $mdr,
            'room' => (int)($room['bytes'] ?? 65535),
            'panel' => $pid,
            'opts' => $sid,
            'msg' => $mid,
            'object' => $oid,
            'url' => $uid,
            'alt' => $aid,
            'urlalt' => $bid,
            'last' => 6,
            'maxfiles' => (int)($rul['maxfiles'] ?? 0),
            'exts' => array_values(array_filter(array_map('trim', explode(',', strtolower((string)($rul['extensions'] ?? '')))))),
            'maxbytes' => (int)($rul['maxbytes'] ?? 0),
            'maxwidth' => (int)($rul['maxwidth'] ?? 0),
            'maxheight' => (int)($rul['maxheight'] ?? 0),
            'token' => $tok,
            'ajax' => $atk,
            'upload' => $upl ? 'index.php?go=4&op=editorUpload&place='.rawurlencode($plc) : '',
            'files' => $upl ? 'index.php?go=4&op=editorFiles&place='.rawurlencode($plc) : '',
            'remove' => $mdr ? 'index.php?go=4&op=editorDelete&place='.rawurlencode($plc) : '',
            'archive' => $mdr ? 'index.php?go=4&op=editorArchive&place='.rawurlencode($plc) : '',
        ];
        $panel = getFileManagerWindow([
            'place' => $plc,
            'panel' => $pid,
            'title' => $tid,
            'msg' => $mid,
            'upfile' => $fid,
            'embed' => $cid,
            'object' => $oid,
            'url' => $uid,
            'alt' => $aid,
            'urlalt' => $bid,
            'editor' => $id,
            'can_embed' => $emb,
            'embed_accept' => implode(',', array_map(static fn(string $ext): string => '.'.$ext, Parser::EMBEDIMG)),
            'embed_types' => implode(', ', Parser::EMBEDIMG).' · '.filterSize(Parser::EMBEDMAX),
            'room_text' => filterSize((int)($room['bytes'] ?? 0)),
        ]);
        $acts = [
            ['key' => 'image', 'icon' => 'image', 'name' => _INSERTIMG, 'tone' => 'info'],
            ['key' => 'attach', 'icon' => 'paperclip', 'name' => _EINSOBJ, 'tone' => 'neutral'],
            ['icon' => 'download', 'name' => _DOWNLOAD, 'tone' => 'neutral', 'is_load' => true],
        ];
        if ($mdr) $acts[] = ['key' => 'zip', 'icon' => 'file-zip', 'name' => _EDITOR_ZIP, 'tone' => 'warn'];
        if ($mdr) $acts[] = ['key' => 'delete', 'icon' => 'trash3', 'name' => _DELETE, 'tone' => 'danger'];
        if ($upl) $panel .= getWindowShot([
            'own' => 'editor',
            'editor' => $eid,
            'prev_text' => _EDITOR_PREV,
            'next_text' => _EDITOR_NEXT,
            'can_walk' => true,
            'can_props' => true,
            'acts' => $acts,
        ]);
        if ($upl) $panel .= $tpl->getHtmlFrag('window', [
            'win_id' => htmlspecialchars($sid, ENT_QUOTES, 'UTF-8'),
            'size_class' => 'sl-modal-sm',
            'win_class' => 'sl-fm-win sl-toastui-upload',
            'win_attr' => 'data-editor="'.$eid.'"',
            'icon_name' => 'sliders',
            'title_text' => _EDITOR_OPTS,
            'has_sub' => true,
            'sub_attr' => 'data-sl-opts="name"',
            'close_text' => _CLOSE,
            'body_html' => $tpl->getHtmlFrag('window-body-insert', [
                'opts_id' => htmlspecialchars($sid, ENT_QUOTES, 'UTF-8'),
                'align_label' => _EDITOR_ALIGN,
                'alignno_label' => _EDITOR_ALIGNNO,
                'alignleft_label' => _EDITOR_ALIGNLEFT,
                'alignright_label' => _EDITOR_ALIGNRIGHT,
                'caption_label' => _EDITOR_CAPTION,
            ]),
            'foot_html' => $tpl->getHtmlFrag('window-foot-insert', [
                'editor_id' => $eid,
                'close_label' => _CLOSE,
                'insert_label' => _EDITOR_INSERT,
            ]),
        ]);
        $jopt = json_encode($opt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $js = '(function(){var ta=document.getElementById('.$jid.');var root=window.toastui&&window.toastui.Editor;';
        $js .= 'if(!ta||!root){return;}';
        $js .= 'var ed=new root({el:document.getElementById('.$jid.'+"_toast"),';
        $js .= 'initialEditType:'.$mode.',initialValue:'.$jval.',placeholder:'.$jph.',height:'.$h.',language:'.$lang.',autofocus:'.$focus.',usageStatistics:false});';
        $js .= 'var mnt=document.getElementById('.$jid.'+"_toast");';
        $js .= 'var lab=mnt?mnt.getAttribute("aria-labelledby"):"";var alt=mnt?mnt.getAttribute("aria-label"):"";';
        $js .= 'var des=mnt?mnt.getAttribute("aria-describedby"):"";';
        $js .= 'if(mnt){mnt.removeAttribute("aria-labelledby");mnt.removeAttribute("aria-label");mnt.removeAttribute("aria-describedby");}';
        $js .= 'var setname=function(){if(!mnt||(!lab&&!alt&&!des)){return;}';
        $js .= 'var box=mnt.querySelectorAll("[contenteditable=true],.ProseMirror,.toastui-editor-md-container textarea");';
        $js .= 'for(var i=0;i<box.length;i++){if(lab){box[i].setAttribute("aria-labelledby",lab);}else if(alt){box[i].setAttribute("aria-label",alt);}';
        $js .= 'if(des){box[i].setAttribute("aria-describedby",des);}}};';
        $js .= 'setname();setTimeout(setname,300);ed.on("changeMode",setname);';
        $js .= 'if(window.SlaedToastUi){window.SlaedToastUi.register('.$jid.',ed,'.$jopt.');}';
        $js .= 'if('.$focus.'){setTimeout(function(){var box=document.getElementById('.$jid.'+"_toast");';
        $js .= 'var foc=box&&box.querySelector(".toastui-editor-contents[contenteditable=true],.ProseMirror.toastui-editor-contents,"+';
        $js .= '".toastui-editor textarea:not(.toastui-editor-pseudo-clipboard)");';
        $js .= 'if(foc){foc.focus();}else{try{ed.focus();}catch(e){}}},300);}';
        $js .= 'var sync=function(){ta.value=ed.getMarkdown();};';
        $js .= 'ed.on("change",sync);ed.on("blur",sync);';
        $js .= 'ta.form&&ta.form.addEventListener("submit",sync,true);';
        $js .= '})();';
        return $ta.$panel.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
