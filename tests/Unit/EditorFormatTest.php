<?php

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

require_once BASE_DIR.'/core/classes/editor.php';

final class EditorFormatTest extends TestCase
{
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
}
