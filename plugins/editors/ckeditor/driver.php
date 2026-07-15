<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorCkeditor implements ContentDriver {
    private static bool $done = false;
    private const PL_FULL = '[CK5.Essentials,CK5.Paragraph,CK5.Heading,CK5.Bold,CK5.Italic,CK5.Underline,CK5.Strikethrough,CK5.Link,CK5.List,CK5.BlockQuote,CK5.Table,CK5.TableToolbar,CK5.Alignment,CK5.SourceEditing,CK5.HtmlEmbed,CK5.Indent,CK5.IndentBlock]';
    private const PL_SIMPLE = '[CK5.Essentials,CK5.Paragraph,CK5.Bold,CK5.Italic,CK5.Link,CK5.List,CK5.BlockQuote]';
    private const TB_FULL = "['heading','|','bold','italic','underline','strikethrough','|','link','bulletedList','numberedList','|','blockQuote','insertTable','|','alignment','|','outdent','indent','|','sourceEditing','htmlEmbed','|','undo','redo']";
    private const TB_SIMPLE = "['bold','italic','|','link','bulletedList','numberedList','|','blockQuote','|','undo','redo']";

    public function getAssets(string $profile): string {
        global $tpl;
        if (self::$done) return '';
        self::$done = true;
        return $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => 'plugins/editors/ckeditor/assets/ckeditor.bundle.css', 'type' => '', 'title' => ''])
            .$tpl->getHtmlFrag('head-script-src', ['src' => 'plugins/editors/ckeditor/assets/ckeditor.bundle.js', 'attr' => '']);
    }

    public function getWidget(string $id, string $name, string $value, string $profile, array $data = []): string {
        global $tpl;
        $jid = json_encode($id);
        $jval = json_encode($value);
        $jph = json_encode((string)($data['placeholder'] ?? ''));
        $lang = substr(_LOCALE, 0, 2);
        $pl = ($profile === 'full') ? self::PL_FULL : self::PL_SIMPLE;
        $tb = ($profile === 'full') ? self::TB_FULL : self::TB_SIMPLE;
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $ta = '<div id="'.$eid.'_ck"></div>';
        $ta .= $tpl->getHtmlFrag('hidden', [
            'name_attr' => $name,
            'value_attr' => $value,
            'input_attr' => 'id="'.$eid.'"',
        ]);
        $js = '(function(){CK5.ClassicEditor.create(document.getElementById('.$jid.'+"_ck"),{';
        $js .= 'plugins:'.$pl.',toolbar:'.$tb.',placeholder:'.$jph.',language:"'.$lang.'",licenseKey:"GPL",';
        $js .= 'table:{contentToolbar:["tableColumn","tableRow","mergeTableCells"]}}).then(function(ed){';
        $js .= 'ed.setData('.$jval.');';
        $js .= 'var inp=document.getElementById('.$jid.');';
        $js .= 'inp.form&&inp.form.addEventListener("submit",function(){inp.value=ed.getData();},true);';
        $js .= '});})();';
        return $ta.$tpl->getHtmlFrag('head-script-inline', ['js' => $js]);
    }
}
