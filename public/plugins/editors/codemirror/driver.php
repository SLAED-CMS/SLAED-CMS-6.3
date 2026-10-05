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
        return Editor::getAssetTags(['plugins/editors/codemirror/assets/cm6.css'], ['plugins/editors/codemirror/assets/cm6.bundle.js']);
    }

    public function getWidget(string $id, string $name, string $value, string $lang, string $profile, string $label): string {
        global $tpl;
        $fn = self::LANGS[$lang] ?? '';
        $ext = $fn ? 'CM6.'.$fn.'(),' : '';
        $dark = ($profile === 'full') ? ',CM6.oneDark' : '';
        $jlab = json_encode(['aria-label' => $label], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $exts = '[CM6.basicSetup,'.$ext.'CM6.keymap.of([CM6.indentWithTab]),CM6.EditorView.contentAttributes.of('.$jlab.')'.$dark.']';
        $jid = json_encode($id);
        $jcm = json_encode($id.'_cm');
        $eid = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        $ta = $tpl->getHtmlFrag('textarea', [
            'name_attr' => $name,
            'value_text' => $value,
            'input_class' => defined('ADMIN_FILE') ? 'sl-form-control' : '',
            'input_attr' => 'id="'.$eid.'" hidden',
        ]);
        $ta .= $tpl->getHtmlFrag('editor-mount', ['id' => $id.'_cm', 'is_code' => true]);
        $js = 'var ta=el;';
        $js .= 'var view=new CM6.EditorView({state:CM6.EditorState.create({doc:ta.value,extensions:'.$exts.'}),';
        $js .= 'parent:document.getElementById('.$jcm.')});';
        $js .= 'CM6.editors['.$jid.']=view;';
        $js .= 'ta.form&&ta.form.addEventListener("submit",function(){ta.value=view.state.doc.toString();},true);';
        return $ta.Editor::getInitScript($id, $js, 'view.destroy();delete CM6.editors['.$jid.'];');
    }
}
