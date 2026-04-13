<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

class EditorCodemirror implements CodeDriver {
    private static bool $done = false;
    private const LANGS = [
        'php' => 'php',
        'html' => 'html',
        'css' => 'css',
        'js' => 'javascript',
        'sql' => 'sql',
        'xml' => 'xml',
        'json' => 'json',
        'text' => '',
    ];

    public function getAssets(string $profile): string {
        if (self::$done) return '';
        self::$done = true;
        return getHtmlCssLink('plugins/editors/codemirror/assets/cm6.css')
            .getHtmlScriptSrc('plugins/editors/codemirror/assets/cm6.bundle.js');
    }

    public function getWidget(string $id, string $name, string $value, string $lang, string $profile): string {
        $fn = self::LANGS[$lang] ?? '';
        $ext = $fn ? 'CM6.'.$fn.'(),' : '';
        $dark = ($profile === 'full') ? ',CM6.oneDark' : '';
        $exts = '[CM6.basicSetup,'.$ext.'CM6.keymap.of([CM6.indentWithTab])'.$dark.']';
        $jid = json_encode($id);
        $jcm = json_encode($id.'_cm');
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $enm = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $eval = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $ta = '<textarea id="'.$eid.'" name="'.$enm.'" style="display:none">'.$eval.'</textarea>';
        $ta .= '<div id="'.$eid.'_cm" class="sl_code_editor"></div>';
        $js = '(function(){var ta=document.getElementById('.$jid.');';
        $js .= 'var view=new CM6.EditorView({state:CM6.EditorState.create({doc:ta.value,extensions:'.$exts.'}),';
        $js .= 'parent:document.getElementById('.$jcm.')});';
        $js .= 'CM6.editors['.$jid.']=view;';
        $js .= 'ta.form&&ta.form.addEventListener("submit",function(){ta.value=view.state.doc.toString();},true);';
        $js .= '})();';
        return $ta.getHtmlScriptInline($js);
    }
}
