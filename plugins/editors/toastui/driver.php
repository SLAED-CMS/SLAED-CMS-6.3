<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorToastUi implements ContentDriver {
    private static bool $done = false;

    public function getAssets(string $profile): string {
        if (self::$done) return '';
        self::$done = true;
        return getHtmlCssLink('plugins/editors/toastui/assets/toastui-editor.min.css')
            .getHtmlScriptSrc('plugins/editors/toastui/assets/toastui-editor.all.min.js')
            .getHtmlScriptSrc('plugins/editors/toastui/assets/i18n/ru-ru.js');
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        $jid = json_encode($id);
        $jval = json_encode($value);
        $mode = ($profile === 'full') ? '"wysiwyg"' : '"markdown"';
        $rows = (int)($data['rows'] ?? (($profile === 'full') ? 20 : 10));
        $height = max(180, $rows * 24);
        $h = '"'.$height.'px"';
        $lang = (substr(_LOCALE, 0, 2) === 'ru') ? '"ru-RU"' : '"en-US"';
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $ta = '<textarea id="'.$eid.'" name="'.$enm.'" style="display:none">';
        $ta .= htmlspecialchars($value, ENT_QUOTES, 'UTF-8').'</textarea>';
        $ta .= '<div id="'.$eid.'_toast"></div>';
        $js = '(function(){var ta=document.getElementById('.$jid.');var root=window.toastui&&window.toastui.Editor;';
        $js .= 'if(!ta||!root){return;}';
        $js .= 'var ed=new root({el:document.getElementById('.$jid.'+"_toast"),';
        $js .= 'initialEditType:'.$mode.',initialValue:'.$jval.',height:'.$h.',language:'.$lang.',usageStatistics:false});';
        $js .= 'ta.form&&ta.form.addEventListener("submit",function(){ta.value=ed.getMarkdown();},true);';
        $js .= '})();';
        return $ta.getHtmlScriptInline($js);
    }
}
