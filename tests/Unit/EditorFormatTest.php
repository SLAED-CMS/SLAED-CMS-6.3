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
}
