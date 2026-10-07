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
    private const PHRASES = [
        'Find' => '_EDITOR_FIND',
        'Replace' => '_EDITOR_REPLACE',
        'next' => '_EDITOR_NEXT',
        'previous' => '_EDITOR_PREV',
        'all' => '_ALL',
        'match case' => '_EDITOR_CASE',
        'regexp' => '_EDITOR_REGEXP',
        'by word' => '_EDITOR_WORD',
        'replace' => '_EDITOR_REPLONE',
        'replace all' => '_EDITOR_REPLALL',
        'close' => '_CLOSE',
        'current match' => '_EDITOR_CURMATCH',
        'on line' => '_EDITOR_ONLINE',
        'replaced $ matches' => '_EDITOR_REPLACED',
        'replaced match on line $' => '_EDITOR_REPLINE',
        'Folded lines' => '_EDITOR_FOLDED',
        'Unfolded lines' => '_EDITOR_UNFOLDED',
        'to' => '_EDITOR_TO',
        'folded code' => '_EDITOR_FOLDCODE',
        'unfold' => '_EDITOR_UNFOLD',
        'Fold line' => '_EDITOR_FOLDLINE',
        'Unfold line' => '_EDITOR_UNFOLDLN',
        'Go to line' => '_EDITOR_GOTOLINE',
        'go' => '_EDITOR_GO',
        'Diagnostics' => '_EDITOR_LINT',
        'No diagnostics' => '_EDITOR_NOLINT',
        'Completions' => '_EDITOR_COMPLETE',
        'Control character' => '_EDITOR_CTRLCHAR',
        'Selection deleted' => '_EDITOR_SELDEL',
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
        $flag = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $jlab = json_encode(['aria-label' => $label], $flag);
        $jphr = json_encode(array_map(constant(...), self::PHRASES), $flag);
        $exts = '[CM6.basicSetup,'.$ext.'CM6.syntaxHighlighting(CM6.classHighlighter),CM6.keymap.of([CM6.indentWithTab]),';
        $exts .= 'CM6.EditorState.phrases.of('.$jphr.'),CM6.EditorView.contentAttributes.of('.$jlab.')]';
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
