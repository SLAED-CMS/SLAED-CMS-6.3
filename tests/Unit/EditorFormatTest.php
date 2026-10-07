<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once BASE_DIR.'/core/classes/editor.php';

final class EditorFormatTest extends TestCase
{
    # The driver reads the words of its panels from the locale, which a unit test does not load
    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/codemirror/driver.php';
        foreach ((new ReflectionClassConstant(EditorCodemirror::class, 'PHRASES'))->getValue() as $name) if (!defined($name)) define($name, 'word '.$name);
    }

    #[Test]
    public function getFormatUsesManifestPrimaryFormat(): void
    {
        $this->assertSame('plain', Editor::getFormat('plain'));
        $this->assertSame('markdown', Editor::getFormat('toastui'));
        $this->assertSame('html', Editor::getFormat('ckeditor'));
        $this->assertSame('html', Editor::getFormat('tinymce'));
        $this->assertSame('plain', Editor::getFormat('codemirror'));
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
        $this->assertSame(['code'], Editor::getManifest('codemirror')['type'] ?? null, 'The code editor does not declare its type as a list');
        $this->assertFalse(Editor::isValidEditor('codemirror', 'admin'), 'The code editor is offered as a text editor before it is one');
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
        $this->assertSame('plain toastui tinymce ckeditor', $list['admin'], 'The text editors of the panel are not listed by priority');
        $this->assertSame('plain toastui', $list['user'], 'The site offers an editor that is the panel\'s only');
        $this->assertSame('codemirror', $list['code'], 'The code editor declared by a list is missing from the code list');
    }

    # The editable area of CodeMirror had no accessible name, so a screen reader announced an unnamed field on every code screen of the panel
    # The driver writes the label into the content attributes, encoded so a file path cannot close the inline script, and every caller names its editor
    #[Test]
    public function codeEditorsCarryAnAccessibleName(): void
    {
        require_once BASE_DIR.'/public/plugins/editors/codemirror/driver.php';
        $keep = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = new class {
            public function getHtmlFrag(string $name, array $data = []): string
            {
                return ($name === 'head-script-inline') ? (string)$data['js'] : '';
            }
        };
        $js = (new EditorCodemirror())->getWidget('code', 'text', '', 'php', 'full', 'File: </script>x');
        $GLOBALS['tpl'] = $keep;
        $this->assertStringContainsString('CM6.EditorView.contentAttributes.of({"aria-label":"File: ', $js, 'The editable area is not named');
        $this->assertStringNotContainsString('</script>', $js, 'A label can close the inline script');
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

    # The code is coloured by the theme through tok-* classes in place of One Dark, and every phrase of its panels is a constant
    #[Test]
    public function codeEditorWearsTheThemeAndSpeaksTheLocale(): void
    {
        $list = (new ReflectionClassConstant(EditorCodemirror::class, 'PHRASES'))->getValue();
        $this->assertGreaterThan(15, count($list), 'The panels of the code editor carry no words of the locale');
        $this->assertArrayHasKey('current match', $list, 'What the search panel announces to a screen reader stays English');
        $keep = $GLOBALS['tpl'] ?? null;
        $GLOBALS['tpl'] = new class {
            public function getHtmlFrag(string $name, array $data = []): string
            {
                return ($name === 'head-script-inline') ? (string)$data['js'] : '';
            }
        };
        $js = (new EditorCodemirror())->getWidget('code', 'text', '', 'html', 'full', 'File');
        $GLOBALS['tpl'] = $keep;
        $this->assertStringContainsString('CM6.syntaxHighlighting(CM6.classHighlighter)', $js, 'The code is not handed to the theme as classes');
        $this->assertStringNotContainsString('oneDark', $js, 'A fixed dark scheme paints the code over the theme');
        $this->assertStringContainsString('CM6.EditorState.phrases.of({"Find":"word _EDITOR_FIND"', $js, 'The search panel speaks English');
        $entry = (string)file_get_contents(BASE_DIR.'/public/plugins/editors/codemirror/build/entry.js');
        $this->assertStringNotContainsString('theme-one-dark', $entry, 'The build still carries One Dark');
        $css = (string)file_get_contents(BASE_DIR.'/public/plugins/editors/codemirror/assets/cm6.css');
        $this->assertDoesNotMatchRegularExpression('#\bheight\s*:#', $css, 'The plugin fixes the height of the editor over the theme');
    }
}
