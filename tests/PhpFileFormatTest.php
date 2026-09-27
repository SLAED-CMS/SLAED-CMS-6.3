<?php
# Holds the file format of every PHP file (UTF-8 without BOM, LF, a final newline) and the comment and line rules of .rules/global.md

use PHPUnit\Framework\TestCase;

class PhpFileFormatTest extends TestCase
{
    private static string $basePath;
    private static array $phpFiles = [];
    private static array $tokens = [];
    private const MOJIBAKE = [
        "\xC3\x83\xC2\x90", "\xC3\x83\xE2\x80\x98", "\xC3\x82\xC2\xA9", "\xC3\x82\xC2\xA7",
        "\xC3\x82\xC2\xAE", "\xC3\x82\xC2\xB7", "\xC3\x82\xC2\xB6", "\xC3\xA2\xE2\x82\xAC",
        "\xC3\xA2\xE2\x80\x9E\xE2\x80\x93", "\xC3\x90", "\xC3\x91",
    ];
    private const MAXLINE = 180;

    public static function setUpBeforeClass(): void
    {
        self::$basePath = dirname(__DIR__);
        self::scanPhpFiles();
    }

    # Collect every PHP file of the tree; third-party and generated content (vendor, storage, uploads, plugins) is skipped
    private static function scanPhpFiles(): void
    {
        $iterator = getTreeFiles(self::$basePath);
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = $file->getPathname();
            if (preg_match('#[/\\\\](vendor|storage|uploads|plugins|node_modules)[/\\\\]#', $path)) continue;
            self::$phpFiles[] = $path;
        }
    }

    # Answer the files of our own code the comment rules hold, keyed by the path relative to the root; lang files carry no functions and config files are written by the panel
    private static function getCodeFiles(): array
    {
        $files = [];
        foreach (self::$phpFiles as $file) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen(self::$basePath) + 1));
            if (!preg_match('#^((core|modules|blocks|admin|setup|templates|tests|tools)/|[^/]+$)#', $rel) || str_contains($rel, '/lang/')) continue;
            $files[$rel] = $file;
        }
        return $files;
    }

    # Answer the tokens of one file, read once per run
    private static function getFileTokens(string $file): array
    {
        return self::$tokens[$file] ??= token_get_all(file_get_contents($file));
    }

    public function testPhpFilesEncoding(): void
    {
        $errors = [];
        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);
            if (!mb_check_encoding($content, 'UTF-8')) $errors[] = "$relative - некорректная кодировка (не UTF-8)";
            if (substr($content, 0, 3) === "\xEF\xBB\xBF") $errors[] = "$relative - содержит BOM";
        }
        $this->assertEmpty($errors, "Проблемы с кодировкой:\n".implode("\n", $errors));
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
        $this->assertEmpty($errors, "Проблемы с крякозябрами:\n".implode("\n", $errors));
    }

    public function testPhpFilesLineEndings(): void
    {
        $errors = [];
        foreach (self::$phpFiles as $file) {
            $content = file_get_contents($file);
            $relative = str_replace(self::$basePath.DIRECTORY_SEPARATOR, '', $file);
            if (str_contains($content, "\r\n")) $errors[] = "$relative - содержит CRLF, нужен LF";
            if ($content !== '' && !str_ends_with($content, "\n")) $errors[] = "$relative - отсутствует финальный LF";
        }
        $this->assertEmpty($errors, "Проблемы с окончаниями строк:\n".implode("\n", $errors));
    }

    public function testPhpCommentsSingleLine(): void
    {
        $errors = [];
        foreach (self::getCodeFiles() as $rel => $file) {
            foreach (self::getWrappedComments(self::getFileTokens($file)) as $line) $errors[] = "$rel:$line - комментарий продолжает предыдущую строку #";
        }
        $this->assertEmpty($errors, "Перенесённые комментарии (.rules/global.md, Comments):\n".implode("\n", $errors));
    }

    public function testPhpCommentsOutsideBodies(): void
    {
        $errors = [];
        foreach (self::getCodeFiles() as $rel => $file) {
            foreach (self::getBodyComments(self::getFileTokens($file)) as $line) $errors[] = "$rel:$line - комментарий внутри тела функции или блока";
        }
        $this->assertEmpty($errors, "Комментарии внутри тел (.rules/global.md, Comments):\n".implode("\n", $errors));
    }

    public function testPhpCommentsForm(): void
    {
        $errors = [];
        foreach (self::getCodeFiles() as $rel => $file) {
            foreach (self::getCommentFaults(self::getFileTokens($file)) as [$line, $fault]) $errors[] = "$rel:$line - $fault";
        }
        $this->assertEmpty($errors, "Форма комментариев (.rules/global.md, Comments):\n".implode("\n", $errors));
    }

    public function testClassCommentsSingleLine(): void
    {
        $errors = [];
        foreach (self::getCodeFiles() as $rel => $file) {
            foreach (self::getClassComments(self::getFileTokens($file)) as $line) $errors[] = "$rel:$line - над классом больше одной строки комментария";
        }
        $this->assertEmpty($errors, "Комментарии над классами (.rules/global.md, Comments):\n".implode("\n", $errors));
    }

    public function testPhpLineLength(): void
    {
        $errors = [];
        foreach (self::$phpFiles as $file) {
            $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen(self::$basePath) + 1));
            if (!preg_match('#^((core|modules|blocks|admin|setup|templates|tests|tools|lang)/|[^/]+$)#', $rel)) continue;
            foreach (self::getLongLines(file_get_contents($file), str_contains($rel, '/lang/') || str_starts_with($rel, 'lang/')) as $line) {
                $errors[] = "$rel:$line - строка длиннее ".self::MAXLINE.' символов';
            }
        }
        $this->assertEmpty($errors, "Длинные строки (.rules/global.md, Purpose):\n".implode("\n", $errors));
    }

    public function testSqlCommentsWithoutPeriod(): void
    {
        $errors = [];
        foreach (glob(self::$basePath.'/setup/sql/*.sql') as $file) {
            foreach (explode("\n", file_get_contents($file)) as $i => $text) {
                if (preg_match('/^\s*#.*\.\s*$/', $text)) $errors[] = 'setup/sql/'.basename($file).':'.($i + 1).' - комментарий кончается точкой';
            }
        }
        $this->assertEmpty($errors, "Точки в SQL-комментариях (.rules/global.md, Comments):\n".implode("\n", $errors));
    }

    public function testWrappedCommentIsDetected(): void
    {
        $code = "<?php\n# Reads the list of materials of one type,\n# ordered by date\n# Returns an empty array on failure\nfunction getList(): array { return []; }\n";
        $this->assertSame([3], self::getWrappedComments(token_get_all($code)));
    }

    public function testBodyCommentIsDetected(): void
    {
        $code = "<?php\n# Holds the list\nclass Box\n{\n    # The rows\n    private array \$rows = [];\n\n"
            ."    # Reads a row\n    public function getRow(): string\n    {\n        # inside\n"
            ."        \$fn = function() {\n            // closure\n        };\n        return \"{\$fn()}\";\n    }\n}\nif (true) {\n    # block\n}\n# Top level\n";
        $this->assertSame([11, 13, 19], self::getBodyComments(token_get_all($code)));
        $this->assertSame([], self::getBodyComments(token_get_all("<?php\nnamespace App {\n    # One line\n    class Box {}\n}\n")));
        $this->assertSame([4], self::getBodyComments(token_get_all("<?php\nnamespace App;\nfunction getBox(): int {\n    # inside\n    return 1;\n}\n")));
    }

    public function testCommentFaultsAreDetected(): void
    {
        $code = "<?php\n#[Attribute]\n// slashes\n/** doc */\n# Ends with a period.\n# Комментарий\n# Fine one\n";
        $this->assertSame([3, 4, 5, 6], array_column(self::getCommentFaults(token_get_all($code)), 0));
    }

    public function testClassCommentIsDetected(): void
    {
        $code = "<?php\n# One\n# Two\nfinal class First {}\n\n# Only one\nclass Second { public function getName(): string { return self::class; } }\n\$x = new class {};\n";
        $this->assertSame([4], self::getClassComments(token_get_all($code)));
    }

    public function testLongLineIsDetected(): void
    {
        $long = str_repeat('x', self::MAXLINE);
        $code = "<?php\n\$a = '$long';\ndefine('_LONG', '$long');\n\$b = '".str_repeat('ё', 10).str_repeat('x', self::MAXLINE - 20)."';\n";
        $this->assertSame([2, 3], self::getLongLines($code, false));
        $this->assertSame([2], self::getLongLines($code, true));
    }

    # Answer the lines of a # comment that continues the # comment of the line before in lowercase, the sign of one comment wrapped over two lines
    private static function getWrappedComments(array $tokens): array
    {
        $lines = [];
        $prev = 0;
        foreach ($tokens as $token) {
            if (!is_array($token) || $token[0] !== T_COMMENT || !str_starts_with($token[1], '#')) continue;
            if ($prev === $token[2] - 1 && preg_match('/^#\s*[a-z]/', $token[1])) $lines[] = $token[2];
            $prev = $token[2];
        }
        return $lines;
    }

    # Answer the lines of comments that stand inside braces other than a class or namespace body: a function, a closure, a control block or a match
    private static function getBodyComments(array $tokens): array
    {
        $lines = [];
        $stack = [];
        $kind = null;
        $prev = null;
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $type = $token[0];
                if (in_array($type, [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $prev !== T_DOUBLE_COLON) $kind = 'class';
                if ($type === T_NAMESPACE) $kind = 'class';
                if ($type === T_CURLY_OPEN || $type === T_DOLLAR_OPEN_CURLY_BRACES) $stack[] = 'string';
                if (($type === T_COMMENT || $type === T_DOC_COMMENT) && $stack && end($stack) !== 'class') $lines[] = $token[2];
                if (!in_array($type, [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $prev = $type;
                continue;
            }
            $prev = $token;
            if ($token === '{') {
                $stack[] = $kind ?? 'code';
                $kind = null;
            } elseif ($token === '}') {
                array_pop($stack);
            } elseif ($token === ';') {
                $kind = null;
            }
        }
        return $lines;
    }

    # Answer [line, fault] for every comment that is not a # line, ends with a period or is not written in English
    private static function getCommentFaults(array $tokens): array
    {
        $faults = [];
        foreach ($tokens as $token) {
            if (!is_array($token) || ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT)) continue;
            $text = rtrim($token[1]);
            if (!str_starts_with($text, '#')) $faults[] = [$token[2], 'комментарий не в форме #'];
            elseif (str_ends_with($text, '.')) $faults[] = [$token[2], 'комментарий кончается точкой'];
            elseif (preg_match('/\p{Cyrillic}/u', $text)) $faults[] = [$token[2], 'комментарий не на английском'];
        }
        return $faults;
    }

    # Answer the declaration lines of named classes, interfaces, traits and enums with more than one comment line straight above them
    private static function getClassComments(array $tokens): array
    {
        $lines = [];
        $prev = null;
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                $prev = $token;
                continue;
            }
            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true) && $prev !== T_DOUBLE_COLON && $prev !== T_NEW) {
                $count = 0;
                $top = $token[2];
                for ($j = $i - 1; $j >= 0; $j--) {
                    $item = $tokens[$j];
                    if (!is_array($item)) break;
                    if ($item[0] === T_WHITESPACE) continue;
                    if (in_array($item[0], [T_FINAL, T_ABSTRACT, T_READONLY], true)) {
                        $top = $item[2];
                        continue;
                    }
                    $span = substr_count(rtrim($item[1]), "\n");
                    if (($item[0] !== T_COMMENT && $item[0] !== T_DOC_COMMENT) || $item[2] + $span !== $top - 1) break;
                    $count += $span + 1;
                    $top = $item[2];
                }
                if ($count > 1) $lines[] = $token[2];
            }
            if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) $prev = $token[0];
        }
        return $lines;
    }

    # Answer the lines longer than the limit; a lang file may keep a translation as one define() line of any length
    private static function getLongLines(string $code, bool $lang): array
    {
        $lines = [];
        foreach (explode("\n", $code) as $i => $text) {
            if (mb_strlen($text) <= self::MAXLINE || ($lang && str_starts_with($text, 'define('))) continue;
            $lines[] = $i + 1;
        }
        return $lines;
    }
}
