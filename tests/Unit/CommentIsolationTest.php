<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 1, batch 6 of docs/COMMENTS-REDESIGN-2026.md: the acceptance criterion of the whole stage, asserted
 * once for all six batches. The comment table has exactly one owner, the three global helpers that used to
 * share it are gone, and no consumer can reach it through a table name assembled from a variable — the shape
 * that hid two consumers from every literal sweep and made the fact list of the plan wrong twice.
 * The installer schema, the parity tool and the test support are excluded by name, because a parity tool that
 * went through the class under test would prove nothing and the installer creates the table in the first place.
 */
final class CommentIsolationTest extends TestCase
{
    # The one file allowed to name the comment table, and the trees whose content is generated, vendored or deliberately outside the rule
    private const OWNER = 'core/classes/comment.php';
    private const SKIP = ['vendor', 'node_modules', 'storage', 'setup', 'tools', 'tests', '.git'];

    # Every production file that builds a table name from a variable, measured on 2026-07-28; a new entry has to be reviewed before it is added here
    private const ASSEMBLED = [
        'core/admin.php',
        'core/system.php',
        'core/user.php',
        'modules/account/index.php',
        'modules/search/admin/index.php',
        'modules/search/index.php',
        'modules/shop/admin/index.php',
    ];

    private static array $files = [];

    # List every production PHP file of the project once, keyed by its path relative to the repository root
    private function getFiles(): array
    {
        if (self::$files !== []) return self::$files;
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $dirs = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);
        $walk = new \RecursiveIteratorIterator($dirs, \RecursiveIteratorIterator::SELF_FIRST);
        $out = [];
        foreach ($walk as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $name = substr($path, strlen($root) + 1);
            if ($item->isDir()) continue;
            if (substr($name, -4) !== '.php') continue;
            if (in_array(explode('/', $name)[0], self::SKIP, true)) continue;
            $out[$name] = (string)file_get_contents($path);
        }
        ksort($out);
        return self::$files = $out;
    }

    # Drop the whole-line comments of one source, so a statement that has been commented out does not count as a live consumer
    private function getCode(string $src): string
    {
        return (string)preg_replace('#^[ \t]*(?:\#|//).*$#m', '', $src);
    }

    # No production file except the class names the comment table
    #[Test]
    public function commentTableIsReachedThroughItsClassAlone(): void
    {
        $hits = [];
        foreach ($this->getFiles() as $name => $src) {
            if ($name === self::OWNER) continue;
            if (preg_match('#PREFIX_DB\s*\.\s*[\'"]_comment#', $this->getCode($src))) $hits[] = $name;
        }
        $this->assertSame([], $hits, 'A comment statement lives outside '.self::OWNER);
    }

    # The class itself still holds the statements, so the sweep above is proving isolation and not an empty project
    #[Test]
    public function theOwnerStillHoldsTheStatements(): void
    {
        $code = $this->getFiles()[self::OWNER] ?? '';
        $this->assertNotSame('', $code, self::OWNER.' was not read');
        $this->assertGreaterThanOrEqual(10, substr_count($code, 'PREFIX_DB.\'_comment'), 'The comment statements left the class they were moved into');
    }

    # A table name built from a variable is the shape that hid two consumers, so the files that build one are a closed list and none of them can be handed the comment table
    #[Test]
    public function assembledTableNamesCannotReachTheCommentTable(): void
    {
        $hits = [];
        foreach ($this->getFiles() as $name => $src) {
            if (preg_match('#PREFIX_DB\s*\.\s*[\'"]_[\'"]\s*\.\s*\$#', $this->getCode($src))) $hits[] = $name;
        }
        $this->assertSame(self::ASSEMBLED, $hits, 'A new file builds a table name from a variable and has to be checked against the comment table');
        $map = $this->getFiles()['core/user.php'];
        $beg = strpos($map, 'function getProfileModules(');
        $this->assertNotFalse($beg);
        $from = strpos($map, '\'comm\' =>', $beg);
        $entry = substr($map, $from, strpos($map, "\n", $from) - $from);
        $this->assertStringNotContainsString('\'table\'', $entry, 'The comment entry of the profile map carries a table name again');
        $this->assertStringNotContainsString('\'where\'', $entry, 'The comment entry of the profile map carries a where clause again');
        $rows = [];
        foreach ($this->getFiles() as $name => $src) {
            foreach (explode("\n", $this->getCode($src)) as $num => $line) {
                if (str_contains($line, 'getAdminCountRow(') && str_contains($line, '\'comment\'')) $rows[] = $name.':'.($num + 1);
            }
        }
        $this->assertSame([], $rows, 'The sidebar count helper is handed the comment table as a name again');
    }

    # The three globals the class absorbed are defined nowhere and called nowhere
    #[Test]
    public function theRetiredGlobalHelpersAreGone(): void
    {
        foreach (['ashowcom', 'numcom', 'getCommentMode'] as $name) {
            foreach ($this->getFiles() as $file => $src) {
                $this->assertStringNotContainsString('function '.$name.'(', $src, $name.'() is still defined in '.$file);
                $this->assertSame(0, preg_match('#\b'.$name.'\s*\(#', $this->getCode($src)), $name.'() is still named in '.$file);
            }
        }
    }

    # The target map holds every module that renders comments, with the points slot each of them awarded before the move
    #[Test]
    public function theTargetMapKeepsTheModulesAndTheirSlots(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.self::OWNER);
        $want = [
            'account' => ['_users', 3],
            'faq' => ['_faq', 7],
            'files' => ['_files', 10],
            'links' => ['_links', 22],
            'media' => ['_media', 26],
            'news' => ['_news', 32],
            'pages' => ['_pages', 36],
            'shop' => ['_products', 40],
            'voting' => ['_voting', 43],
        ];
        preg_match('#private const MODULES = \[(.+?)\];#s', $code, $hit);
        $this->assertNotEmpty($hit, 'The module map is gone from the class');
        $this->assertSame(count($want), substr_count($hit[1], '=>'), 'The module map holds a different number of modules');
        foreach ($want as $mod => [$tab, $slot]) {
            $this->assertStringContainsString('\''.$mod.'\' => [\''.$tab.'\', '.$slot.']', $hit[1], 'Module '.$mod.' lost its table or its points slot');
        }
        foreach ([17, 29] as $slot) {
            $this->assertStringNotContainsString(', '.$slot.']', $hit[1], 'The retired points slot '.$slot.' is back in the module map');
        }
    }

    # The retired slots were dropped from the code alone; removing their CSV positions would renumber every event above them
    #[Test]
    public function thePointsListKeepsItsRetiredPositions(): void
    {
        $conf = (string)file_get_contents(dirname(__DIR__, 2).'/config/users.php');
        preg_match('#\'points\' => \'([0-9,]+)\'#', $conf, $hit);
        $this->assertNotEmpty($hit, 'The points list is gone from config/users.php');
        $this->assertCount(45, explode(',', $hit[1]), 'The points list changed length, which renumbers every slot above the removed one');
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/core/system.php');
        $this->assertStringContainsString("'account' => ['_users', 'votes', 'tvotes', 2]", $code, 'Account ratings no longer award points slot 2');
    }
}
