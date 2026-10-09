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
            '_EEXPAND', '_ERESTORE', '_EDITOR_DRAFT', '_EDITOR_DRAFTBACK', '_EDITOR_DRAFTDROP', '_EUPLOAD', '_EEMOJI', '_CLOSE', '_EEMOJIRECENT',
            '_EEMOJISMILE', '_EEMOJIREACT', '_EEMOJINOTICE', '_EEMOJISYMBOL', '_EEMOJIEMPTY', '_EDITOR_RECENT', '_EDITOR_LEFT', '_ETEXTLONG',
            '_EDITOR_SEARCH', '_EDITOR_FOLDALL', '_EDITOR_UNFOLDALL', '_EDITOR_BOLD', '_EDITOR_ITALIC', '_CODE', '_URL', '_LIST', '_QUOTE', '_EDITOR_FILES',
            '_EDITOR_SELALL', '_EDITOR_COMMENT', '_EFULLSCREEN', '_EDITOR_CMDS', '_EDITOR_CMDFIND', '_EDITOR_GRPEDIT', '_EDITOR_GRPFORMAT',
            '_EDITOR_GRPINSERT', '_EDITOR_GRPVIEW', '_EDITOR_NONE', '_EDITOR_KEYS', '_EDITOR_KEYNEXT', '_EDITOR_KEYLINE', '_EDITOR_KEYMOVE',
            '_EDITOR_KEYDUP', '_EDITOR_KEYDEL', '_EDITOR_KEYINDENT', '_EDITOR_KEYPAIR', '_EDITOR_KEYCARET', '_EDITOR_KEYLIST', '_EDITOR_KEYESC',
            '_EDITOR_PALPICK', '_EDITOR_PALRUN', '_EDITOR_PALSHUT', '_EDITOR_PALANY', '_EDITOR_VARS', '_EDITOR_HINT', '_EDITOR_VARUSED',
            '_EDITOR_PRETTY', '_EDITOR_FLAT', '_EDITOR_ISSUES', '_EDITOR_KEYISSUE', '_EDITOR_LINTVAR', '_EDITOR_LINTNEAR', '_EDITOR_LINTSHUT',
            '_EDITOR_LINTOPEN', '_EDITOR_LINTALT', '_EDITOR_LINTBLANK', '_EDITOR_LINTQUOT', '_EDITOR_LINTSRC', '_EDITOR_FIXVAR', '_EDITOR_FIXDEL',
            '_EDITOR_FIXADD', '_EDITOR_FIXQUOT', '_EDITOR_FIXSHUT', '_EDITOR_DIFF', '_EDITOR_DIFFVIEW', '_EDITOR_DIFFALL', '_EDITOR_DIFFSIDE',
            '_EDITOR_DIFFWAS', '_EDITOR_DIFFNOW', '_EDITOR_DIFFSAME', '_EDITOR_DIFFBACK', '_EDITOR_DIFFKEEP', '_EDITOR_DIFFNOTE',
            '_PREVIEW', '_EDITOR_SAMPLE', '_ERROR'];
        $words = array_merge($words, array_values((new ReflectionClassConstant(Editor::class, 'PHRASES'))->getValue()));
        foreach ($words as $name) if (!defined($name)) define($name, 'word '.$name);
        if (!defined('_LOCALE')) define('_LOCALE', 'en_US');
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

            public function getHtmlPart(string $name, array $data = []): string
            {
                $this->frags[] = [$name, $data];
                return '';
            }
        };
        (new ReflectionProperty(Editor::class, 'done'))->setValue(null, false);
        (new ReflectionProperty(Editor::class, 'emoji'))->setValue(null, false);
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

    # Every text of the shell offers emoji, from a panel printed once per answer with its words and the addresses its script loads by on the first press; code never
    # A text tells the runtime the format it writes a picture in, and a text without an upload place carries no file window
    #[Test]
    public function textEditorsOfferEmojiAndTheirFormat(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/plain/driver.php';
        $frags = $this->getDriverFrags(function (): void {
            (new EditorPlain())->getWidget('1', 'one', '', 'full', []);
            (new EditorCodemirror())->getWidget('2', 'two', '', 'full', ['format' => 'markdown']);
        });
        $this->assertCount(1, array_filter($frags, fn($one) => $one[0] === 'emoji-panel'), 'The emoji panel is printed more than once or not at all');
        $part = $this->getFragData($frags, 'emoji-panel');
        $src = json_decode((string)($part['src_json'] ?? ''), true);
        $this->assertStringStartsWith('plugins/system/emoji.js', $src[0] ?? '', 'The panel names no script to load');
        $this->assertStringStartsWith('plugins/system/emoji/en.js', $src[1] ?? '', 'The panel names no words of the locale');
        $this->assertSame('word _EEMOJISMILE', json_decode((string)($part['words_json'] ?? ''), true)['smileys'] ?? null, 'A category of the panel speaks English');
        $frame = $this->getFragData($frags, 'editor-frame');
        $this->assertSame('plain', $frame['lang_key'] ?? null, 'The frame does not tell the runtime the format it writes a picture in');
        $this->assertSame('', $frame['files_json'] ?? null, 'A text without an upload place offers the file window');
        $code = $this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('c', 'x', '', 'php', 'full', 'File'));
        $this->assertNull($this->getFragData($code, 'emoji-panel'), 'Code offers emoji');
        $kit = $this->getFragData($code, 'editor-kit');
        $this->assertSame('word _EUPLOAD', $kit['files_text'] ?? null, 'The folder of the capsule is not named');
        $this->assertSame('word _EEMOJI', $kit['emoji_text'] ?? null, 'The emoji button of the capsule is not named');
    }

    # The capsule and the palette carry every command once, each marked with what its engine needs, and the runtime keeps of them what the frame serves
    # The palette is a window of the canon printed inside the kit, so a page of many editors carries one; Ctrl+K reaches it only from inside a frame
    #[Test]
    public function capsuleAndPaletteFollowTheEngine(): void
    {
        $kit = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('c', 'x', '', 'php', 'full', 'File')), 'editor-kit');
        $words = json_decode((string)($kit['words_json'] ?? ''), true);
        foreach (['recent' => '_EDITOR_RECENT', 'left' => '_EDITOR_LEFT', 'long' => '_ETEXTLONG'] as $key => $name) {
            $this->assertSame('word '.$name, $words[$key] ?? null, 'The runtime has no word for '.$key);
        }
        foreach ($kit as $key => $value) {
            if (str_ends_with($key, '_text')) $this->assertNotSame('', (string)$value, $key.' of the kit carries no word');
        }
        $tpl = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-kit.html');
        $acts = ['undo', 'redo', 'search', 'wrap', 'fold', 'unfold', 'bold', 'italic', 'code', 'link', 'list', 'quote', 'files', 'emoji', 'hint', 'copy', 'reset', 'diff', 'full'];
        foreach ($acts as $act) {
            $this->assertStringContainsString('data-sl-editor-act="'.$act.'"', $tpl, 'The capsule has no '.$act);
            $this->assertStringContainsString('data-sl-editor-cmd="'.$act.'"', $tpl, 'The palette has no '.$act);
        }
        $need = ['search' => 'cm', 'fold' => 'cm', 'unfold' => 'cm', 'comment' => 'code', 'bold' => 'markdown', 'list' => 'markdown', 'files' => 'files', 'emoji' => 'text',
            'hint' => 'vars', 'var' => 'vars'];
        foreach ($need as $act => $want) {
            $this->assertMatchesRegularExpression('#data-sl-editor-cmd="'.$act.'" data-sl-editor-need="'.$want.'"#', $tpl, 'The palette offers '.$act.' to an engine without it');
        }
        $this->assertMatchesRegularExpression('#<dialog class="sl-modal sl-editor-pal" data-sl-editor-palette#', $tpl, 'The palette is not a window of the canon');
        $this->assertStringContainsString('data-sl-focus', $tpl, 'The keyboard does not land in the search of the palette');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        foreach (["frame.addEventListener('keydown', function (ev) { setKeys(ed, ev); }, true)", "ev.code === 'KeyK'", "ev.key !== 'Escape'", "pal.addEventListener('close'",
            "area.addEventListener('keydown', function (ev) { setListEnter(ed, ev); })", 'enc.encode(text).length'] as $part) {
            $this->assertStringContainsString($part, $run, 'The runtime lost '.$part);
        }
        $this->assertStringNotContainsString("doc.addEventListener('keydown'", $run, 'Ctrl+K is heard outside the editors');
    }

    # The counter of a text reads the room of its column: a text column gives its bytes, a mediumtext column and code give none
    #[Test]
    public function textCountsTheRoomOfItsColumn(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/plain/driver.php';
        $run = fn(array $room) => $this->getFragData($this->getDriverFrags(fn() => (new EditorPlain())->getWidget('1', 'x', '', 'full', ['room' => $room])), 'editor-frame');
        $this->assertSame(65535, $run(['kind' => 'text', 'bytes' => 65535])['room_num'] ?? null, 'A text column gives the counter no room');
        $this->assertSame(0, $run(['kind' => 'mediumtext', 'bytes' => 16777215])['room_num'] ?? null, 'A mediumtext column counts sixteen megabytes down');
        $this->assertSame(0, $run([])['room_num'] ?? null);
        $code = $this->getFragData($this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('c', 'x', '', 'php', 'full', 'File')), 'editor-frame');
        $this->assertSame(0, $code['room_num'] ?? null, 'Code counts the room of a column');
        $tpl = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-frame.html');
        $this->assertStringContainsString('data-sl-editor-room="{{ room_num }}"', $tpl, 'The frame does not hand the room to the runtime');
        $this->assertStringContainsString('data-sl-editor-left', $tpl, 'The status line has no place for the counter');
    }

    # The variables of a caller reach only its own frame through both doors, and the kit, the runtime and tplconfig carry them
    #[Test]
    public function variablesReachTheFrameOfTheirCaller(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/plain/driver.php';
        $keep = $GLOBALS['conf'] ?? null;
        $GLOBALS['conf']['editor'] = ['code' => 'codemirror', 'admin' => 'plain'];
        $vars = ['[src]' => 'Link <b>', 'src' => 'No bracket', '[a b]' => 'Space', '[rel]' => 'Attribute'];
        $frags = $this->getDriverFrags(function () use ($vars): void {
            Editor::getCode(['id' => 'c', 'lang' => 'html', 'vars' => $vars]);
            Editor::getContent(['id' => 't', 'role' => 'admin', 'editor' => 'plain', 'vars' => $vars]);
            (new EditorPlain())->getWidget('p', 'x', '', 'full', []);
        });
        $GLOBALS['conf'] = $keep;
        $frames = array_values(array_filter($frags, fn($one) => $one[0] === 'editor-frame'));
        $this->assertCount(3, $frames);
        $want = json_encode([['[src]', 'Link <b>'], ['[rel]', 'Attribute']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSame($want, $frames[0][1]['vars_json'] ?? null, 'Code does not get the variables of its caller');
        $this->assertSame($want, $frames[1][1]['vars_json'] ?? null, 'A text does not get the variables of its caller');
        $this->assertSame('', $frames[2][1]['vars_json'] ?? null, 'A frame inherits the variables of the one before it');
        $kit = $this->getFragData($frags, 'editor-kit');
        $this->assertSame('word _EDITOR_VARUSED', json_decode((string)($kit['words_json'] ?? ''), true)['used'] ?? null, 'The count of the variables speaks English');
        $tpl = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-kit.html');
        foreach (['data-sl-editor-act="vars" data-sl-editor-need="vars"', 'data-sl-editor-act="hint" data-sl-editor-need="vars"', 'data-sl-editor-hint'] as $part) {
            $this->assertStringContainsString($part, $tpl, 'The kit lost '.$part);
        }
        $frame = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-frame.html');
        $this->assertStringContainsString('data-sl-editor-vars="{{ vars_json }}"', $frame, 'The frame does not hand the variables to the runtime');
        $this->assertStringContainsString('data-sl-editor-used', $frame, 'The status line has no place for the count of the variables');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        $want = ["cm.Decoration.mark({ class: 'sl-editor-var' })", 'cm.EditorState.languageData.of(getVarData(ed))',
            "frame.addEventListener('keydown', function (ev) { setHintKeys(ed, ev); }, true)"];
        foreach ($want as $part) {
            $this->assertStringContainsString($part, $run, 'The runtime lost '.$part);
        }
        $page = (string)file_get_contents(BASE_DIR.'/admin/modules/uploads.php');
        $this->assertStringContainsString("'vars' => \$vars", $page, 'The templates of the file types pass no variables');
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $loc) {
            $this->assertStringNotContainsString("'_TPINFO'", (string)file_get_contents(BASE_DIR.'/admin/lang/'.$loc.'.php'), 'The variables stand as markup in '.$loc);
        }
    }
    # The check of a template reaches only the html frame of a caller that asks for it, and the kit, the runtime and tplconfig carry it
    #[Test]
    public function templateCheckFollowsItsCaller(): void
    {
        $frags = $this->getDriverFrags(function (): void {
            Editor::getCode(['id' => 'a', 'lang' => 'html', 'lint' => true]);
            Editor::getCode(['id' => 'b', 'lang' => 'php', 'lint' => true]);
            Editor::getCode(['id' => 'c', 'lang' => 'html']);
        });
        $frames = array_values(array_filter($frags, fn($one) => $one[0] === 'editor-frame'));
        $this->assertCount(3, $frames);
        $this->assertTrue($frames[0][1]['is_lint'] ?? null, 'Html of a caller that asks is not checked');
        $this->assertSame('word _EDITOR_LINT', $frames[0][1]['lint_text'] ?? null, 'The badge of the check is not named');
        $this->assertFalse($frames[1][1]['is_lint'] ?? null, 'A language the check cannot read is checked');
        $this->assertFalse($frames[2][1]['is_lint'] ?? null, 'A frame inherits the check of the one before it');
        $words = json_decode((string)($this->getFragData($frags, 'editor-kit')['words_json'] ?? ''), true);
        $this->assertSame('word _EDITOR_ISSUES', $words['issues'] ?? null, 'The badge speaks English');
        foreach (['unknown', 'near', 'shut', 'open', 'alt', 'blank', 'quot', 'src', 'fixvar', 'fixdel', 'fixadd', 'fixquot', 'fixshut'] as $key) {
            $this->assertStringStartsWith('word _EDITOR_', (string)($words['lint'][$key] ?? ''), 'The check has no word for '.$key);
        }
        $kit = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-kit.html');
        foreach (['data-sl-editor-act="pretty" data-sl-editor-need="lint"', 'data-sl-editor-act="flat" data-sl-editor-need="lint"',
            'data-sl-editor-cmd="pretty" data-sl-editor-need="lint"', 'data-sl-editor-cmd="flat" data-sl-editor-need="lint"',
            'data-sl-editor-cmd="lint" data-sl-editor-need="lint"', 'data-sl-editor-issues'] as $part) {
            $this->assertStringContainsString($part, $kit, 'The kit lost '.$part);
        }
        $frame = (string)file_get_contents(PUBLIC_DIR.'/templates/admin/fragments/editor-frame.html');
        $this->assertStringContainsString('{% if is_lint %} data-sl-editor-lint{% endif %}', $frame, 'The frame does not tell the runtime to check');
        $this->assertStringContainsString('data-sl-editor-act="lint"', $frame, 'The status line has no badge of the check');
        $this->assertStringContainsString('<span data-sl-editor-lint-text>', $frame, 'The words of the badge share the name of the flag of the frame');
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        foreach (['cm.linter(getLintSource(ed), { delay: 250 }), cm.lintGutter()', "ev.code === 'KeyM' && ed.can.lint", 'setLint(ed);'] as $part) {
            $this->assertStringContainsString($part, $run, 'The runtime lost '.$part);
        }
        $this->assertStringContainsString("'lint' => true", (string)file_get_contents(BASE_DIR.'/admin/modules/uploads.php'), 'The templates of the file types are not checked');
    }

    # The comparison is one window of the canon in the kit, opened from the capsule, the palette and the mark of a changed text in the status line
    # It is computed only when asked for, and a long text is aligned by lines before its changed lines are aligned by units, so its table stays small
    #[Test]
    public function comparisonOpensFromEveryPlace(): void
    {
        $frags = $this->getDriverFrags(fn() => (new EditorCodemirror())->getWidget('c', 'x', '', 'php', 'full', 'File'));
        $this->assertSame('word _EDITOR_DIFF', $this->getFragData($frags, 'editor-frame')['diff_text'] ?? null, 'The mark of a changed text names no comparison');
        $kit = $this->getFragData($frags, 'editor-kit');
        $want = ['diff' => '_EDITOR_DIFF', 'joint' => '_EDITOR_DIFFALL', 'side' => '_EDITOR_DIFFSIDE', 'diffback' => '_EDITOR_DIFFBACK', 'same' => '_EDITOR_DIFFSAME'];
        foreach ($want as $key => $name) {
            $this->assertSame('word '.$name, $kit[$key.'_text'] ?? null, 'The comparison has no word for '.$key);
        }
        foreach (['lite', 'admin'] as $theme) {
            $tpl = (string)file_get_contents(PUBLIC_DIR.'/templates/'.$theme.'/fragments/editor-kit.html');
            foreach (['<dialog class="sl-modal sl-modal-lg sl-editor-diff" data-sl-editor-diff', 'data-sl-editor-diff-view="all" aria-pressed="true"',
                'data-sl-editor-diff-view="side" aria-pressed="false"', 'data-sl-editor-diff-back', 'data-sl-editor-diff-side hidden'] as $part) {
                $this->assertStringContainsString($part, $tpl, 'The comparison of '.$theme.' lost '.$part);
            }
            $frame = (string)file_get_contents(PUBLIC_DIR.'/templates/'.$theme.'/fragments/editor-frame.html');
            $this->assertStringContainsString('class="sl-editor-dirty" data-sl-editor-act="diff"', $frame, 'The mark of a changed text in '.$theme.' opens no comparison');
        }
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        foreach (["if (act === 'diff') return setDiff(ed);", '<= cells) addSubsequence(out, was, now)', 'else if (deep) addLines(out,', "dif.addEventListener('close'",
            'setText(ed, ed.orig, kit.words.restored)', 'diff = getDiff(ed.orig, now);'] as $part) {
            $this->assertStringContainsString($part, $run, 'The runtime lost '.$part);
        }
        $this->assertStringNotContainsString('innerHTML', $run, 'The comparison writes a text of the page as markup');
    }

    # The preview reaches only the frame of a caller that asks for it: a template filled with sample values, a text through the route of its door
    # It renders in a sandboxed frame that runs no script, a PDF is opened by a link, and the route takes a POST that ends the answer
    #[Test]
    public function previewFollowsItsCaller(): void
    {
        $frags = $this->getDriverFrags(function (): void {
            Editor::getCode(['id' => 'a', 'lang' => 'html', 'preview' => ['vals' => ['[src]' => 's.webp', 'src' => 'x', '[x' => 'y'], 'open' => 's.pdf']]);
            Editor::getCode(['id' => 'b', 'lang' => 'html']);
            Editor::getCode(['id' => 'c', 'lang' => 'html', 'preview' => 'nothing']);
        });
        $frames = array_values(array_filter($frags, fn($one) => $one[0] === 'editor-frame'));
        $this->assertCount(3, $frames);
        $this->assertSame(['vals' => ['[src]' => 's.webp'], 'open' => 's.pdf'], json_decode((string)($frames[0][1]['view_json'] ?? ''), true),
            'A template is previewed with other values');
        $this->assertSame('', $frames[1][1]['view_json'] ?? null, 'A frame inherits the preview of the one before it');
        $this->assertSame('', $frames[2][1]['view_json'] ?? null, 'A preview of an unknown kind reaches the frame');
        $kit = $this->getFragData($frags, 'editor-kit');
        $this->assertSame(_PREVIEW, $kit['preview_text'] ?? null, 'The preview has no name');
        $this->assertSame('word _EDITOR_SAMPLE', $kit['sample_text'] ?? null, 'The link to the sample has no name');
        $editor = (string)file_get_contents(BASE_DIR.'/core/classes/editor.php');
        $this->assertStringContainsString("'url' => 'index.php?go='.(defined('ADMIN_FILE') ? '5' : '1').'&op=getEditorPreview'", $editor,
            'A text is not previewed by the route of its door');
        $this->assertStringContainsString("'preview' => 'text',", (string)file_get_contents(BASE_DIR.'/core/helpers.php'), 'The texts of the site are not previewed');
        foreach (['lite', 'admin'] as $theme) {
            $tpl = (string)file_get_contents(PUBLIC_DIR.'/templates/'.$theme.'/fragments/editor-kit.html');
            foreach (['data-sl-editor-act="preview" data-sl-editor-need="preview" aria-pressed="false"', 'data-sl-editor-cmd="preview" data-sl-editor-need="preview"',
                'data-sl-editor-view-frame sandbox="allow-same-origin"', 'data-sl-editor-view-open href="#" target="_blank" rel="noopener" hidden'] as $part) {
                $this->assertStringContainsString($part, $tpl, 'The preview of '.$theme.' lost '.$part);
            }
            $this->assertStringNotContainsString('allow-scripts', $tpl, 'The preview of '.$theme.' runs the scripts of a text');
            $frame = (string)file_get_contents(PUBLIC_DIR.'/templates/'.$theme.'/fragments/editor-frame.html');
            $this->assertStringContainsString('{% if view_json %} data-sl-editor-preview="{{ view_json }}"{% endif %}', $frame, 'The frame of '.$theme.' hands no preview');
        }
        foreach (['picture' => 'webp', 'sound' => 'wav', 'film' => 'webm', 'document' => 'pdf'] as $kind => $ext) {
            $this->assertFileExists(PUBLIC_DIR.'/templates/admin/assets/samples/sample.'.$ext, 'The panel ships no sample '.$kind);
        }
        $run = (string)file_get_contents(PUBLIC_DIR.'/plugins/system/editor.js');
        foreach (["if (ed.can.preview && act === 'preview') return setPreview(ed);", "fetch(data.url, { method: 'POST', body: body, credentials: 'same-origin' })",
            'seq === ed.sheetSeq && eds.get(ed.frame) === ed', '.srcdoc = ', 'setSheet(ed);',
            'ed.sheetSeer.observe(page.body);'] as $part) {
            $this->assertStringContainsString($part, $run, 'The runtime lost '.$part);
        }
        $index = (string)file_get_contents(PUBLIC_DIR.'/index.php');
        $this->assertSame(2, substr_count($index, "case 'getEditorPreview': getEditorPreview(); break;"), 'The route is not answered by both doors');
        $route = (string)file_get_contents(BASE_DIR.'/core/helpers.php');
        $from = strpos($route, 'function getEditorPreview(');
        $this->assertNotFalse($from, 'The route of the preview is gone');
        $body = substr($route, $from, (int)strpos($route, "\n}\n", $from) - $from);
        foreach (["!== 'POST'", "getVar('post', 'text', 'text')", 'checkEditorTextRoom(', 'getTplPreviewContent(', 'exit;',
            '$own = getUploadOwner($mod);', "'own' => (\$own !== '') ? [\$own, 0] : []"] as $part) {
            $this->assertStringContainsString($part, $body, 'The route of the preview lost '.$part);
        }
        $this->assertStringContainsString("'preview' => getTemplateSample(", (string)file_get_contents(BASE_DIR.'/admin/modules/uploads.php'),
            'The file templates are not previewed');
    }
}
