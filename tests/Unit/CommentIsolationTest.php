<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The comment table has exactly one owner, the global helpers that shared it are gone, and no table name built from a variable reaches it
final class CommentIsolationTest extends TestCase
{
    # The one file allowed to name the comment table, and the trees generated, vendored or outside the rule, and the internal migration update.php
    private const OWNER = 'core/classes/comment.php';
    private const SKIP = ['vendor', 'node_modules', 'storage', 'public/update.php', 'tools', 'tests', '.git'];

    # Every production file that builds a table name from a variable, measured on 2026-07-28; a new entry has to be reviewed before it is added here
    private const ASSEMBLED = [
        'core/system.php',
        'core/user.php',
        'modules/account/index.php',
        'modules/search/admin/index.php',
    ];

    private static array $files = [];

    # List every production PHP file of the project once, keyed by its path relative to the repository root
    # The installer schema, the parity tool and the test support stay out: a parity tool through the class proves nothing and the installer creates the table
    private function getFiles(): array
    {
        if (self::$files !== []) return self::$files;
        $root = str_replace('\\', '/', dirname(__DIR__, 2));
        $out = [];
        foreach (getTreeFiles($root) as $item) {
            $path = str_replace('\\', '/', $item->getPathname());
            $name = substr($path, strlen($root) + 1);
            if ($item->isDir()) continue;
            if (substr($name, -4) !== '.php') continue;
            if (in_array(explode('/', $name)[0], self::SKIP, true) || in_array($name, self::SKIP, true)) continue;
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

    # A table name built from a variable hid two consumers from every literal sweep, so the files that build one are a closed list and none of them can be handed the comment table
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

    # The target map holds every module that renders comments with its table and nothing else: the key is the scope of the points journal, and no positional slot may return
    #[Test]
    public function theTargetMapKeepsTheModulesAndNoSlot(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/'.self::OWNER);
        $want = ['account' => '_users', 'voting' => '_voting'];
        preg_match('#private const MODULES = \[(.+?)\];#s', $code, $hit);
        $this->assertNotEmpty($hit, 'The module map is gone from the class');
        $this->assertSame(count($want), substr_count($hit[1], '=>'), 'The module map holds a different number of modules');
        foreach ($want as $mod => $tab) {
            $this->assertStringContainsString("'".$mod."' => '".$tab."'", $hit[1], 'Module '.$mod.' lost its table');
        }
        $this->assertDoesNotMatchRegularExpression('#[0-9]#', $hit[1], 'A positional points slot is back in the module map');
    }

    # Points are awarded by named actions of the Point class alone: the positional list, its switch and both numeric helpers left the tree together with their callers
    #[Test]
    public function thePositionalPointsAreGone(): void
    {
        $root = dirname(__DIR__, 2);
        $conf = (require $root.'/config/users.php')['users'];
        $this->assertArrayNotHasKey('point', $conf, 'The old points switch is back in config/users.php');
        $this->assertArrayNotHasKey('points', $conf, 'The positional points list is back in config/users.php');
        $code = (string)file_get_contents($root.'/core/system.php');
        foreach (['function updatePoints', 'function addPointsAction', 'updatePoints(', 'addPointsAction('] as $name) {
            $this->assertStringNotContainsString($name, $code, 'The numeric points helper is back in core/system.php: '.$name);
        }
        $this->assertStringContainsString("'account' => ['_users', 'votes', 'tvotes', 'id', '', ''],", $code, 'The rating map of accounts carries a points slot again');
    }
}
