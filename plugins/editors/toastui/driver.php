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
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-emoji.js', 'attr' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/toastui/assets/editor-upload.js', 'attr' => '']);
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
            'input_attr' => 'id="'.$eid.'" style="display:none"',
        ]);
        $ta .= '<div id="'.$eid.'_toast"></div>';
        $mod = strtolower((string)($data['mod'] ?? ''));
        $rul = (array)($data['rule'] ?? []);
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
        $exts = array_values(array_filter(array_map('trim', explode(',', (string)($rul['extensions'] ?? '')))));
        $quot = (int)($rul['maxquota'] ?? 0);
        $mbyt = (int)($rul['maxbytes'] ?? 0);
        $mwid = (int)($rul['maxwidth'] ?? 0);
        $mhei = (int)($rul['maxheight'] ?? 0);
        $lims = [implode(', ', $exts)];
        if ($mbyt > 0) $lims[] = filterSize($mbyt);
        if ($mwid > 0 && $mhei > 0) $lims[] = $mwid.' × '.$mhei.' px';
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
                'emoji_recent' => _EEMOJIRECENT,
                'emoji_smileys' => _EEMOJISMILE,
                'emoji_reactions' => _EEMOJIREACT,
                'emoji_notices' => _EEMOJINOTICE,
                'emoji_symbols' => _EEMOJISYMBOL,
                'emoji_empty' => _EEMOJIEMPTY,
                'toobig' => _ERROR_SIZE,
                'badtype' => _ERROR_FILE,
                'noembed' => _ENOEMBED,
                'insert' => _INSERTIMG,
                'insobj' => _EINSOBJ,
                'download' => _DOWNLOAD,
                'zip' => _EDITOR_ZIP,
                'delete' => _DELETE,
                'acts' => _FUNCTIONS,
                'mark' => _EDITOR_MARK,
                'marked' => _EDITOR_MARKED,
                'insimgs' => _EDITOR_INSIMGS,
                'insobjs' => _EDITOR_INSOBJS,
                'askdel' => _EDITOR_ASKDEL,
                'askdels' => _EDITOR_ASKDELS,
                'preview' => _PREVIEW,
                'empty' => _EDITOR_EMPTY,
                'emptywhy' => _EDITOR_EMPTYWHY,
                'none' => _EDITOR_NONE,
                'nonewhy' => _EDITOR_NONEWHY,
                'fail' => _EDITOR_FAIL,
                'failwhy' => _EDITOR_FAILWHY,
                'reset' => _FRESET,
                'retry' => _RETRY,
                'queue' => _EDITOR_QUEUE,
                'queueend' => _EDITOR_QUEUEEND,
                'quota' => _EDITOR_QUOTA,
                'more' => _EDITOR_MORE,
                'mynote' => _EDITOR_MYNOTE,
                'name' => _NAME,
                'type' => _TYPE,
                'size' => _SIZE,
                'dim' => _EDITOR_DIM,
                'date' => _DATE,
                'addr' => _EDITOR_ADDR,
            ],
            'panes' => [
                'up' => [_EMODEUPLOAD, _EMODEUPLOADINFO],
                'url' => [_EDITOR_LINK, _EDITOR_LINKINFO],
                'emb' => [_EMODEEMBED, _EMODEEMBEDINFO],
                'lib' => [_EDITOR_MY, _EDITOR_MYINFO],
            ],
            'embedmax' => Parser::EMBEDMAX,
            'embedimg' => Parser::EMBEDIMG,
            'canupload' => $upl,
            'canlist' => $upl,
            'canembed' => $emb,
            'canzip' => $mdr,
            'candel' => $mdr,
            'room' => (int)($room['bytes'] ?? 65535),
            'panel' => $pid,
            'msg' => $mid,
            'object' => $oid,
            'url' => $uid,
            'alt' => $aid,
            'urlalt' => $bid,
            'last' => 6,
            'page' => 12,
            'maxfiles' => (int)($rul['maxfiles'] ?? 0),
            'token' => $tok,
            'ajax' => $atk,
            'upload' => $upl ? 'index.php?go=4&op=editorUpload&mod='.rawurlencode($mod) : '',
            'files' => $upl ? 'index.php?go=4&op=editorFiles&mod='.rawurlencode($mod) : '',
            'remove' => $mdr ? 'index.php?go=4&op=editorDelete&mod='.rawurlencode($mod) : '',
            'archive' => $mdr ? 'index.php?go=4&op=editorArchive&mod='.rawurlencode($mod) : '',
        ];
        $panel = $tpl->getHtmlPart('editor-toastui-files', [
            'panel_id' => htmlspecialchars($pid, ENT_QUOTES, 'UTF-8'),
            'title_id' => htmlspecialchars($tid, ENT_QUOTES, 'UTF-8'),
            'msg_id' => htmlspecialchars($mid, ENT_QUOTES, 'UTF-8'),
            'file_id' => htmlspecialchars($fid, ENT_QUOTES, 'UTF-8'),
            'embed_id' => htmlspecialchars($cid, ENT_QUOTES, 'UTF-8'),
            'object_id' => htmlspecialchars($oid, ENT_QUOTES, 'UTF-8'),
            'url_id' => htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'),
            'alt_id' => htmlspecialchars($aid, ENT_QUOTES, 'UTF-8'),
            'urlalt_id' => htmlspecialchars($bid, ENT_QUOTES, 'UTF-8'),
            'editor_id' => $eid,
            'can_upload' => $upl,
            'can_list' => $upl,
            'can_embed' => $emb,
            'can_zip' => $mdr,
            'can_delete' => $mdr,
            'accept_attr' => implode(',', array_map(static fn(string $ext): string => '.'.$ext, $exts)),
            'embed_accept' => implode(',', array_map(static fn(string $ext): string => '.'.$ext, Parser::EMBEDIMG)),
            'embed_types' => implode(', ', Parser::EMBEDIMG).' · '.filterSize(Parser::EMBEDMAX),
            'title_label' => _EUPLOAD,
            'close_label' => _CLOSE,
            'move_label' => _EMOVEWIN,
            'expand_label' => _EEXPAND,
            'restore_label' => _ERESTORE,
            'add_label' => _EDITOR_ADD,
            'store_label' => _EDITOR_STORE,
            'up_label' => _EMODEUPLOAD,
            'up_note' => _EMODEUPLOADINFO,
            'up_why' => _EDITOR_NOUP,
            'link_label' => _EDITOR_LINK,
            'link_note' => _EDITOR_LINKNOTE,
            'link_lead' => _EDITOR_LINKINFO,
            'link_warn' => _EDITOR_LINKWARN,
            'embed_label' => _EMODEEMBED,
            'embed_note' => _EMODEEMBEDINFO,
            'embed_why' => _ENOEMBED,
            'files_label' => _EDITOR_MY,
            'files_note' => '',
            'files_why' => _EDITOR_NOMY,
            'quota_text' => $upl ? sprintf(_EDITOR_QUOTA, '—', filterSize($quot)) : _EDITOR_NOUP,
            'module_text' => sprintf(_EDITOR_MODULE, $mod),
            'address_label' => _EIMGURL,
            'alt_label' => _DESCRIPTION,
            'insas_label' => _EDITOR_INSAS,
            'image_label' => _IMG,
            'object_label' => _EINSOBJ,
            'nofile_label' => _ENOFILE,
            'drop_label' => _EDROPFILES,
            'limits_text' => implode(' · ', $lims),
            'stop_label' => _EDITOR_STOP,
            'used_label' => _EDITOR_USED,
            'room_label' => _EDITOR_ROOM,
            'room_text' => filterSize((int)($room['bytes'] ?? 0)),
            'full_label' => _EDITOR_FULL,
            'filter_label' => _EDITOR_FILTER,
            'all_label' => _ALL,
            'images_label' => _EDITOR_IMAGES,
            'others_label' => _EDITOR_OTHERS,
            'list_label' => _EDITOR_ASLIST,
            'tiles_label' => _EDITOR_ASGRID,
            'markall_label' => _EDITOR_MARKALL,
            'name_label' => _NAME,
            'type_label' => _TYPE,
            'size_label' => _SIZE,
            'date_label' => _DATE,
            'props_label' => _EDITOR_PROPS,
            'props_note' => _EDITOR_NOTE,
            'zip_label' => _EDITOR_ZIP,
            'delete_label' => _DELETE,
            'unmark_label' => _EDITOR_UNMARK,
            'wait_text' => _EDITOR_WAIT,
            'insert_label' => _EDITOR_INSERT,
            'refresh_label' => _UPDATE,
        ]);
        $acts = [
            ['key' => 'image', 'icon' => 'image', 'name' => _INSERTIMG, 'tone' => 'info'],
            ['key' => 'attach', 'icon' => 'paperclip', 'name' => _EINSOBJ, 'tone' => 'neutral'],
            ['icon' => 'download', 'name' => _DOWNLOAD, 'tone' => 'neutral', 'is_load' => true],
        ];
        if ($mdr) $acts[] = ['key' => 'zip', 'icon' => 'file-zip', 'name' => _EDITOR_ZIP, 'tone' => 'warn'];
        if ($mdr) $acts[] = ['key' => 'delete', 'icon' => 'trash3', 'name' => _DELETE, 'tone' => 'danger'];
        if ($upl) $panel .= $tpl->getHtmlPart('window-gallery', [
            'shot_own' => 'editor',
            'editor_id' => $eid,
            'shot_text' => _PREVIEW,
            'prev_text' => _EDITOR_PREV,
            'next_text' => _EDITOR_NEXT,
            'can_walk' => true,
            'can_props' => true,
            'acts' => $acts,
        ]);
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
        return $ta.$panel.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
