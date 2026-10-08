<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once BASE_DIR.'/core/classes/editor.php';

final class EditorFormatTest extends TestCase
{
    # The shell reads its words and the phrases of the CodeMirror panels from the locale, which a unit test does not load
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/codemirror/driver.php';
        if (!class_exists('Template', false)) require_once BASE_DIR.'/core/classes/template.php';
        $words = ['_TEXT', '_EDITOR_DIRTY', '_EDITOR_TOOLS', '_EDITOR_POS', '_EDITOR_SEL', '_EDITOR_SIZE', '_COPYDONE', '_EDITOR_NOCLIP', '_EDITOR_RESTORED',
            '_EDITOR_UNDO', '_EDITOR_REDO', '_EDITOR_WRAP', '_COPY', '_EDITOR_RESET', '_EMOVEWIN',
            '_EEXPAND', '_ERESTORE', '_EDITOR_DRAFT', '_EDITOR_DRAFTBACK', '_EDITOR_DRAFTDROP'];
        $words = array_merge($words, array_values((new ReflectionClassConstant(Editor::class, 'PHRASES'))->getValue()));
        foreach ($words as $name) if (!defined($name)) define($name, 'word '.$name);
    }

    #[Test]
    public function getFormatUsesManifestPrimaryFormat(): void
    {
        $this->assertSame('plain', Editor::getFormat('plain'));
        $this->assertSame('markdown', Editor::getFormat('toastui'));
        $this->assertSame('html', Editor::getFormat('ckeditor'));
        $this->assertSame('html', Editor::getFormat('tinymce'));
        $this->assertSame('markdown', Editor::getFormat('codemirror'), 'CodeMirror as text stores apart from Toast UI');
    }

    #[Test]
    public function getFormatFallsBackToPlain(): void
    {
        $this->assertSame('plain', Editor::getFormat('missing'));
    }

    # A manifest names its type as one string or as a list, and every reader of a manifest answers both through one check
    #[Test]
    public function manifestTypeIsReadAsStringOrList(): void
    {
        $check = new ReflectionMethod(Editor::class, 'checkType');
        $this->assertTrue($check->invoke(null, ['type' => 'content'], 'content'), 'A type given as one string is not read');
        $this->assertFalse($check->invoke(null, ['type' => 'content'], 'code'));
        $this->assertTrue($check->invoke(null, ['type' => ['content', 'code']], 'content'), 'A type given as a list is not read');
        $this->assertTrue($check->invoke(null, ['type' => ['content', 'code']], 'code'), 'A type given as a list is not read');
        $this->assertFalse($check->invoke(null, ['type' => ['code']], 'content'));
        $this->assertFalse($check->invoke(null, [], 'content'), 'A manifest without a type serves a type');
        $this->assertSame(['content', 'code'], Editor::getManifest('codemirror')['type'] ?? null, 'CodeMirror does not declare both of its roles as a list');
        $this->assertTrue(Editor::isValidEditor('codemirror', 'admin'), 'CodeMirror is no text editor of the panel');
        $this->assertTrue(Editor::isValidEditor('codemirror', 'user'), 'CodeMirror is no text editor of the site');
        $this->assertTrue(Editor::isValidEditor('plain', 'user'));
        $this->assertFalse(Editor::isValidEditor('x/../plain', 'admin'), 'A key walks to a manifest outside its own directory');
        $panel = (string)file_get_contents(BASE_DIR.'/admin/index.php');
        $this->assertStringNotContainsString('function isValidEditor', $panel, 'The panel reads a manifest with a check of its own');
    }

    # A page loads the core and the one grammar its editor names, so every language of the manifest needs a file of its own beside the core
    #[Test]
    public function splitBuildCarriesEveryLanguage(): void
    {
        $dir = BASE_DIR.'/public/plugins/editors/codemirror/assets/';
        $this->assertFileExists($dir.'core.js', 'The build writes no core');
        $need = array_diff((array)(Editor::getManifest('codemirror')['lang'] ?? []), ['text']);
        $need[] = 'markdown';
        foreach ($need as $lang) $this->assertFileExists($dir.'lang-'.$lang.'.js', 'The build writes no file for '.$lang);
        foreach (glob($dir.'lang-*.js') ?: [] as $file) {
            $this->assertStringNotContainsString('"./core.js"', (string)file_get_contents($file), basename($file).' imports the core entry, which a versioned address loads twice');
        }
    }

    # The lists of the settings, of the administrators and of the panel header are built by getSelect() from the manifests
    #[Test]
    public function editorListsFollowTypeAndRole(): void
    {
        $keep = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = new class {
            public function getHtmlFrag(string $name, array $data = []): string
            {
                return ($name === 'select-option') ? $data['value_attr'].' ' : (string)($data['options_html'] ?? '');
            }
        };
        $list = [
            'admin' => trim(Editor::getSelect('editor', 'plain', 'content', 'admin')),
            'user' => trim(Editor::getSelect('editor', 'plain', 'content', 'user')),
            'code' => trim(Editor::getSelect('editor', 'codemirror', 'code', 'admin')),
        ];
        $GLOBALS['tpl'] = $keep;
        $this->assertSame('plain toastui codemirror tinymce ckeditor', $list['admin'], 'The text editors of the panel are not listed by priority');
        $this->assertSame('plain toastui codemirror', $list['user'], 'The site offers an editor that is the panel\'s only');
        $this->assertSame('codemirror', $list['code'], 'The code editor declared by a list is missing from the code list');
    }


    # Render the widgets of a driver against a template that records every fragment asked for, with the kit of the shell not yet printed
    private function getDriverFrags(callable $run): array
    {
        $keep = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = new class {
            public array $frags = [];

            public function getHtmlFrag(string $name, array $data = []): string
            {
                $this->frags[] = [$name, $data];
                return '';
            }
        };
        (new ReflectionProperty(Editor::class, 'done'))->setValue(null, false);
        $run();
        $out = $GLOBALS['tpl']->frags;
        $GLOBALS['tpl'] = $keep;
        return $out;
    }

    # The data one fragment was given, or null when the widget never asked for it
    private function getFragData(array $frags, string $name): ?array
    {
        foreach ($frags as [$frag, $data]) if ($frag === $name) return $data;
        return null;
    }

    # Plain and CodeMirror render through the frame of the shell, which owns every class and the textarea, so a driver hands data and no markup
    # The runtime and the kit of its words are printed once per answer, whatever the number of editors on the page
    #[Test]
    public function bothDriversRenderTheShellAndNoClass(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/plain/driver.php';
        $runs = [
            'plain' => fn() => (new EditorPlain())->getWidget('1', 'text', 'a<b>', 'full', ['format' => 'markdown', 'label' => 'Text', 'arialabel' => 'Text']),
            'codemirror' => fn() => (new EditorCodemirror())->getWidget('code', 'text', 'a<b>', 'php', 'full', 'File'),
            'codemirror text' => fn() => (new EditorCodemirror())->getWidget('2', 'text', 'a<b>', 'full', ['format' => 'markdown', 'label' => 'Text', 'arialabel' => 'Text']),
        ];
        foreach ($runs as $key => $run) {
            $frags = $this->getDriverFrags($run);
            $data = $this->getFragData($frags, 'editor-frame');
            $this->assertNotNull($data, $key.' renders no frame of the shell');
            $this->assertNotNull($this->getFragData($frags, 'editor-kit'), $key.' prints no kit for the runtime');
            $this->assertNull($this->getFragData($frags, 'head-script-inline'), $key.' writes a script of its own');
            $this->assertNull($this->getFragData($frags, 'textarea'), $key.' draws its textarea outside the frame');
            $this->assertSame('a<b>', $data['value_text'], $key.' escapes the text before the template does');
            foreach ($data as $name => $value) {
                $this->assertStringNotContainsString('class', (string)$name, $key.' hands a class key to the frame');
                $this->assertDoesNotMatchRegularExpression('#\bsl-[a-z]#', (string)$value, $key.' hands a class name to the frame');
            }
        }
        $frags = $this->getDriverFrags(function (): void {
            (new EditorPlain())->getWidget('1', 'one', '', 'full', []);
            (new EditorPlain())->getWidget('2', 'two', '', 'full', []);
        });
        $this->assertCount(1, array_filter($frags, fn($one) => $one[0] === 'editor-kit'), 'The kit is printed once for every editor of the page');
        $this->assertCount(2, array_filter($frags, fn($one) => $one[0] === 'editor-frame'));
        foreach (['editor-frame', 'editor-kit'] as $name) {
            $dir = PUBLIC_DIR.'/templates/%s/fragments/'.$name.'.html';
            $this->assertFileEquals(sprintf($dir, 'admin'), sprintf($dir, 'lite'), $name.' differs between the themes');
        }
    }

    # The textarea of a code editor is named for a screen reader before CodeMirror arrives, and CodeMirror takes the same name; every caller names its editor
    #[Test]
    public function codeEditorsCarryAnAccessibleName(): void
    {
        $data = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('code', 'text', '', 'php', 'full', 'File: x')), 'editor-frame');
        $this->assertSame('File: x', $data['aria_label'] ?? null, 'The editable area is not named');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        $this->assertStringContainsString('cm.EditorView.contentAttributes.of(getAreaAttr(area))', $run, 'CodeMirror drops the name of its textarea');
        $miss = [];
        foreach (['admin', 'blocks', 'core', 'modules'] as $dir) {
            foreach (getTreeFiles(BASE_DIR.'/'.$dir) as $file) {
                if ($file->getExtension() !== 'php') continue;
                $code = (string)file_get_contents($file->getPathname());
                if (!preg_match_all('#Editor::getCode\(\[(.{0,600}?)\]\)#s', $code, $hits)) continue;
                foreach ($hits[1] as $args) if (!str_contains($args, "'label' =>")) $miss[] = substr(str_replace('\\', '/', $file->getPathname()), strlen(BASE_DIR) + 1);
            }
        }
        $this->assertSame([], $miss, 'A code editor is rendered without a label');
    }

    # CodeMirror as text takes the data of getContent(): the grammar of its format, the rows of the field, and a textarea that is not marked as code
    #[Test]
    public function textEditorRendersItsFormat(): void
    {
        $this->assertInstanceOf(ContentDriver::class, new EditorCodemirror());
        $run = fn(array $data) => $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('1', 'text', 'a', 'simple', $data)), 'editor-frame');
        $data = $run(['label' => 'Text', 'arialabel' => 'Text', 'rows' => 7]);
        $this->assertStringStartsWith('plugins/editors/codemirror/assets/core.js', $data['core_url'] ?? '');
        $this->assertStringStartsWith('plugins/editors/codemirror/assets/lang-markdown.js', $data['grammar_url'] ?? '', 'A text without a format is not Markdown');
        $this->assertSame('Markdown', $data['lang_text'] ?? null);
        $this->assertSame(7, $data['rows_num'] ?? null, 'The rows of the field are lost');
        $this->assertFalse($data['is_code'] ?? null, 'A text is marked as code and loses the spell check');
        $this->assertSame('Text', $data['aria_label'] ?? null);
        $this->assertSame(10, $run([])['rows_num'] ?? null);
        $this->assertSame('', $run(['format' => 'plain'])['grammar_url'] ?? null, 'Plain text asks for a grammar');
        $this->assertStringStartsWith('plugins/editors/codemirror/assets/lang-html.js', $run(['format' => 'html'])['grammar_url'] ?? '');
        $code = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('c', 'x', '', 'php', 'full', 'File')), 'editor-frame');
        $this->assertTrue($code['is_code'] ?? null, 'Code is not marked as code');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        $this->assertStringContainsString('spellcheck: String(area.spellcheck)', $run, 'CodeMirror turns the spell check of a text off');
        $this->assertStringContainsString('code ? cm.basicSetup : cm.textSetup', $run, 'A text is counted by line numbers');
        $this->assertStringContainsString('code ? cm.keymap.of([cm.indentWithTab]) : []', $run, 'Tab keeps the focus in a text instead of moving on');
        $this->assertStringContainsString('textSetup', (string)file_get_contents(PUBLIC_DIR.'/plugins/editors/codemirror/assets/core.js'), 'The core carries no setup of a text');
        $this->assertStringContainsString("frame.style.setProperty('--sl-d-editor-floor'", $run, 'CodeMirror does not keep the height of its field');
        $this->assertStringContainsString("area.addEventListener('invalid'", $run, 'An empty required text under CodeMirror blocks the form without a word');
        foreach (['admin', 'lite'] as $theme) {
            $css = (string)file_get_contents(PUBLIC_DIR.'/templates/'.$theme.'/assets/css/theme.css');
            $this->assertStringContainsString('.sl-editor .cm-editor .tok-heading', $css, 'The '.$theme.' theme does not paint a Markdown text');
            $floor = 'min-height: var(--sl-d-editor-floor, var(--sl-editor-height))';
            $this->assertStringContainsString($floor, $css, 'The '.$theme.' theme lets the form jump when CodeMirror arrives');
            $this->assertStringContainsString('.sl-editor[data-invalid] .sl-editor-card', $css, 'The '.$theme.' theme does not mark an empty required text');
        }
    }

    # CodeMirror loads through the runtime as the core and one grammar by their versioned addresses, and the IIFE bundle with its readers is gone
    #[Test]
    public function codeEditorLoadsThroughTheRuntime(): void
    {
        $data = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('code', 'text', '', 'sql', 'full', 'File')), 'editor-frame');
        $this->assertStringStartsWith('plugins/editors/codemirror/assets/core.js', $data['core_url'] ?? '');
        $this->assertStringStartsWith('plugins/editors/codemirror/assets/lang-sql.js', $data['grammar_url'] ?? '');
        $data = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('code', 'text', '', 'text', 'full', 'File')), 'editor-frame');
        $this->assertSame('', $data['grammar_url'] ?? null, 'Plain text asks for a grammar');
        $dir = PUBLIC_DIR.'/plugins/editors/codemirror/';
        $this->assertFileDoesNotExist($dir.'assets/cm6.bundle.js');
        $this->assertFileDoesNotExist($dir.'build/entry.js');
        $this->assertStringNotContainsString('iife', (string)file_get_contents($dir.'build/build.mjs'), 'The build still writes the bundle');
        $this->assertStringNotContainsString('theme-one-dark', (string)file_get_contents($dir.'build/core.js'), 'The build still carries One Dark');
        foreach (['admin/modules/editor.php', 'public/templates/admin/assets/js/admin-ui.js', 'public/templates/admin/assets/js/editor-robots.js'] as $file) {
            $this->assertStringNotContainsString('CM6', (string)file_get_contents(BASE_DIR.'/'.$file), $file.' reads the editors of the removed bundle');
        }
        $css = (string)file_get_contents($dir.'assets/cm6.css');
        $this->assertDoesNotMatchRegularExpression('#\bheight\s*:#', $css, 'The plugin fixes the height of the editor over the theme');
    }

    # The code is coloured by the theme through tok-* classes, and every phrase of the CodeMirror panels reaches the runtime as a constant of the locale
    #[Test]
    public function codeEditorWearsTheThemeAndSpeaksTheLocale(): void
    {
        $list = (new ReflectionClassConstant(Editor::class, 'PHRASES'))->getValue();
        $this->assertGreaterThan(15, count($list), 'The panels of the code editor carry no words of the locale');
        $this->assertArrayHasKey('current match', $list, 'What the search panel announces to a screen reader stays English');
        $kit = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('code', 'text', '', 'html', 'full', 'File')), 'editor-kit');
        $this->assertStringContainsString('"Find":"word _EDITOR_FIND"', $kit['words_json'] ?? '', 'The search panel speaks English');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        $this->assertStringContainsString('cm.syntaxHighlighting(cm.classHighlighter)', $run, 'The code is not handed to the theme as classes');
        $this->assertStringNotContainsString('oneDark', $run, 'A fixed dark scheme paints the code over the theme');
    }
}
