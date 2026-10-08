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
        if (self::$done) return '';
        self::$done = true;
        $locale = $this->getLocale();
        $js = ['plugins/editors/toastui/assets/toastui-editor.all.min.js'];
        if ($locale[1] !== '') $js[] = 'plugins/editors/toastui/assets/i18n/'.$locale[1];
        $ewords = 'plugins/system/emoji/'.substr(_LOCALE, 0, 2).'.js';
        if (Template::getAssetUrl($ewords) !== $ewords) $js[] = $ewords;
        $js[] = 'plugins/editors/toastui/assets/editor-tags.js';
        $js[] = 'plugins/system/emoji.js';
        return Editor::getEmojiPanel().Editor::getAssetTags(['plugins/editors/toastui/assets/toastui-editor.min.css'], $js);
    }

    # Render one editor instance with the file window every visitor gets, since its address field serves everyone; the options are the ones the shell takes too
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
        $kit = getEditorFileKit($id, $data);
        $panel = $kit['html'];
        $opt = ['super' => isAdmin(true)] + $kit['opt'];
        $opt['labels'] += [
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
        ];
        $jopt = json_encode($opt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $js = 'var ta=el;var root=window.toastui&&window.toastui.Editor;';
        $js .= 'if(!root){return;}';
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
        $kill = 'if(window.SlaedFileManager&&window.SlaedFileManager.deleteUpload){window.SlaedFileManager.deleteUpload('.$jid.');}'
            .'if(window.SlaedToastUi&&window.SlaedToastUi.unregister){window.SlaedToastUi.unregister('.$jid.');}else{ed.destroy();}';
        return $ta.$panel.Editor::getInitScript($id, $js, $kill);
    }
}
