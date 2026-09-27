<?php
/**
 * Enforces UTF-8 (no BOM), LF line endings, and trailing newline for PHP files.
 */

use PHPUnit\Framework\TestCase;

class PhpFileFormatTest extends TestCase
{
    private static string $basePath;
    private static array $phpFiles = [];
    private const MOJIBAKE = [
        "\xC3\x83\xC2\x90", "\xC3\x83\xE2\x80\x98", "\xC3\x82\xC2\xA9", "\xC3\x82\xC2\xA7",
        "\xC3\x82\xC2\xAE", "\xC3\x82\xC2\xB7", "\xC3\x82\xC2\xB6", "\xC3\xA2\xE2\x82\xAC",
        "\xC3\xA2\xE2\x80\x9E\xE2\x80\x93", "\xC3\x90", "\xC3\x91",
    ];

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanPhpFiles();
    }

    private static function scanPhpFiles(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$basePath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            // Skip third-party and generated content.
            if (preg_match('#[/\\\\](vendor|storage|uploads|plugins)[/\\\\]#', $path)) {
                continue;
            }

            self::$phpFiles[] = $path;
        }
    }

    public function testPhpFilesEncoding(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            if (!mb_check_encoding($content, 'UTF-8')) {
                $errors[] = "$relative - некорректная кодировка (не UTF-8)";
            }

            if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
                $errors[] = "$relative - содержит BOM";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с кодировкой:\n".implode("\n", $errors)
        );
    }

    public function testPhpFilesMojibake(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            foreach (self::MOJIBAKE as $frag) {
                if (!str_contains($content, $frag)) continue;
                $errors[] = "$relative - содержит крякозябры: ".$frag;
                break;
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с крякозябрами:\n".implode("\n", $errors)
        );
    }

    public function testPhpFilesLineEndings(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);

            if (str_contains($content, "\r\n")) {
                $errors[] = "$relative - содержит CRLF, нужен LF";
            }

            if ($content !== '' && !str_ends_with($content, "\n")) {
                $errors[] = "$relative - отсутствует финальный LF";
            }
        }

        $this->assertEmpty(
            $errors,
            "Проблемы с окончаниями строк:\n".implode("\n", $errors)
        );
    }

    public function testPhpCommentsSingleLine(): void
    {
        $errors = [];

        foreach (self::$phpFiles as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen(self::$basePath) + 1));

            if (!preg_match('#^((core|modules|blocks|admin|setup)/|[^/]+$)#', $relative)) {
                continue;
            }

            foreach (self::getWrappedComments(file_get_contents($file)) as $line) {
                $errors[] = "$relative:$line - комментарий продолжает предыдущую строку #";
            }
        }

        $this->assertEmpty(
            $errors,
            "Перенесённые комментарии (.rules/global.md, Comments):\n".implode("\n", $errors)
        );
    }

    public function testWrappedCommentIsDetected(): void
    {
        $code = "<?php\n# Reads the list of materials of one type,\n# ordered by date\n# Returns an empty array on failure\nfunction getList(): array { return []; }\n";

        $this->assertSame([3], self::getWrappedComments($code));
    }

    private static function getWrappedComments(string $code): array
    {
        $lines = [];
        $prev = 0;

        foreach (token_get_all($code) as $token) {
            if (!is_array($token) || $token[0] !== T_COMMENT || !str_starts_with($token[1], '#')) {
                continue;
            }

            if ($prev === $token[2] - 1 && preg_match('/^#\s*[a-z]/', $token[1])) {
                $lines[] = $token[2];
            }

            $prev = $token[2];
        }

        return $lines;
    }
}
