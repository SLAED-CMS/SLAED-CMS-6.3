<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorCkeditor implements ContentDriver {
    private static bool $done = false;
    private const TB_FULL = "['heading','|','bold','italic','underline','strikethrough','|','link','bulletedList','numberedList','|','blockQuote','insertTable','|','alignment','|','sourceEditing','htmlEmbed','|','undo','redo']";
    private const TB_SIMPLE = "['bold','italic','|','link','bulletedList','numberedList','|','blockQuote','|','undo','redo']";

    public function getAssets(string $profile): string {
        if (self::$done) return '';
        self::$done = true;
        return getHtmlScriptSrc('plugins/editors/ckeditor/assets/ckeditor.bundle.js');
    }

    public function getWidget(string $id, string $name, string $value, string $profile): string {
        $jid = json_encode($id);
        $jval = json_encode($value);
        $lang = substr(_LOCALE, 0, 2);
        $tb = ($profile === 'full') ? self::TB_FULL : self::TB_SIMPLE;
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $eval = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $ta = '<div id="'.$eid.'_ck"></div>';
        $ta .= '<input type="hidden" id="'.$eid.'" name="'.$enm.'" value="'.$eval.'">';
        $js = '(function(){CK5.ClassicEditor.create(document.getElementById('.$jid.'+"_ck"),{';
        $js .= 'toolbar:'.$tb.',language:"'.$lang.'"}).then(function(ed){';
        $js .= 'ed.setData('.$jval.');';
        $js .= 'var inp=document.getElementById('.$jid.');';
        $js .= 'inp.form&&inp.form.addEventListener("submit",function(){inp.value=ed.getData();},true);';
        $js .= '});})();';
        return $ta.getHtmlScriptInline($js);
    }
}
