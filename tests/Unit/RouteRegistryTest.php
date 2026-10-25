<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

# The registry of addresses of the Address Boundary of docs/ARCHITECTURE.md: the name filterSlug() makes, the class Route over _routes and its reserved words
final class RouteRegistryTest extends TestCase
{
    private static array $probe = [];

    private static string $work = '';

    # The probe tests/Support/registry_probe.php boots the real core and works in a disposable schema it drops again
    # Run the probe once and memoize its report for every test in this class
    private function getRun(string $name): array
    {
        if (self::$probe === []) {
            self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_route_'.bin2hex(random_bytes(4));
            $script = dirname(__DIR__).'/Support/registry_probe.php';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg(self::$work).' 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data;
        }
        return self::$probe['runs'][$name];
    }

    # A private constant of Route, read from the class file without booting the core
    private function getConst(string $name): mixed
    {
        require_once dirname(__DIR__, 2).'/core/classes/route.php';
        return (new ReflectionClass('Route'))->getConstant($name);
    }

    # Remove the scratch tree of the probe, configuration copy included
    public static function tearDownAfterClass(): void
    {
        $drop = static function (string $dir) use (&$drop): void {
            if (!is_dir($dir)) return;
            foreach (scandir($dir) ?: [] as $name) {
                if ($name === '.' || $name === '..') continue;
                is_dir($dir.'/'.$name) ? $drop($dir.'/'.$name) : unlink($dir.'/'.$name);
            }
            rmdir($dir);
        };
        if (self::$work !== '') $drop(self::$work);
    }

    # The letters of every locale have their Latin form in one table, an apostrophe joins its word, and a title of symbols only has no name
    #[Test]
    public function everyLocaleIsTransliterated(): void
    {
        $run = $this->getRun('slug');
        $this->assertSame('adaptivnyi-shablon-pure', $run['ru']);
        $this->assertSame('yizhak-i-ganok-ye-pyat', $run['uk']);
        $this->assertSame('groesse-der-aepfel-ueber-uebung', $run['de']);
        $this->assertSame('zazolc-gesla-jazn', $run['pl']);
        $this->assertSame('lete-ou-ca-a-commence-coeur-noel', $run['fr']);
        $this->assertSame('hello-world-2026', $run['en']);
        $this->assertSame('', $run['sym']);
        $this->assertSame('dorabotka-modulya-shop-5-6-lete-title', $run['html'], 'an entity of a stored title became a word of its name');
    }

    # A long name is cut to 80 at the last hyphen before the limit, one long word hard at 80, and a hyphen right at the limit is a clean cut
    #[Test]
    public function aLongNameIsCut(): void
    {
        $run = $this->getRun('slug');
        $this->assertSame(implode('-', array_fill(0, 13, 'slovo')), $run['cut']);
        $this->assertSame(str_repeat('a', 80), $run['word']);
        $this->assertSame(str_repeat('b', 80), $run['edge']);
    }

    # The first path comes from the title and stays; a path taken by a current, a former or a gone row takes the next suffix; an empty name takes the id
    #[Test]
    public function aTakenPathTakesTheNextSuffix(): void
    {
        $run = $this->getRun('registry');
        $this->assertSame('files/adaptivnyi-shablon-pure', $run['first']);
        $this->assertSame('files/adaptivnyi-shablon-pure', $run['again'], 'a new title moved a path that was already given');
        $this->assertSame('files/adaptivnyi-shablon-pure-2', $run['twin'], 'after a current path');
        $this->assertSame('files/old-name-2', $run['former'], 'after a former path');
        $this->assertSame('files/gone-2', $run['gone'], 'after a gone path');
        $this->assertSame('files/old-name-3', $run['noreuse'], 'a path of a gone entity was given again');
        $this->assertSame('files/new-name-2', $run['nomove'], 'a move took a path of a gone entity');
        $this->assertSame('files/4', $run['empty']);
        $this->assertSame(['files/moduli', 'files/moduli-2', 'forum/moduli'], [$run['cat'], $run['catnode'], $run['forum']], 'materials and categories share a prefix');
    }

    # Every former path of an entity names its current path, never the path that followed it, and a former path of its own is taken back
    #[Test]
    public function aMoveLeavesNoChain(): void
    {
        $run = $this->getRun('registry');
        $this->assertSame(['files/new-name', 'files/third-name'], [$run['moved'], $run['third']]);
        $want = [
            ['kind' => 'node', 'oid' => 5, 'state' => 0, 'path' => 'files/third-name'],
            ['kind' => 'node', 'oid' => 5, 'state' => 0, 'path' => 'files/third-name'],
            ['kind' => 'node', 'oid' => 5, 'state' => 1, 'path' => 'files/third-name'],
        ];
        $this->assertSame($want, $run['nochain']);
        $this->assertSame(['files/old-name', 'files/old-name'], [$run['back'], $run['same']]);
        $this->assertSame([['files/old-name', 1, 1], ['files/new-name', 0, null], ['files/third-name', 0, null]], $run['rows'], 'a move back added a row');
    }

    # The database refuses a second current row of an entity, a written cur and a state outside the three, and cur follows state whoever writes it
    #[Test]
    public function curAndStateNeverDisagree(): void
    {
        $run = $this->getRun('registry');
        $this->assertSame([false, 'owner'], $run['owner'], 'a second current row was accepted');
        $this->assertFalse($run['cur'][0], 'the generated column took a written value');
        $this->assertFalse($run['check'][0], 'a state outside 0, 1 and 2 was accepted');
        $this->assertSame([['files/adaptivnyi-shablon-pure', 0, null]], $run['hand'], 'a state written by hand left cur behind');
        $this->assertSame(0, intval($run['apart']));
    }

    # Every row of a gone entity is gone, the current one and the former ones, and its paths still name it
    #[Test]
    public function everyRowOfAGoneEntityIsGone(): void
    {
        $run = $this->getRun('registry');
        $this->assertTrue($run['deleted']);
        $this->assertSame([['files/old-name', 2, null], ['files/new-name', 2, null], ['files/third-name', 2, null]], $run['allgone']);
        $this->assertSame(['kind' => 'node', 'oid' => 5, 'state' => 2, 'path' => ''], $run['gonepath']);
    }

    # The current paths of a set of entities come in one query, a gone and an unknown entity absent; a path never given or outside the grammar is nothing
    #[Test]
    public function aSetIsReadInOneQuery(): void
    {
        $run = $this->getRun('registry');
        $this->assertSame(['1' => 'files/adaptivnyi-shablon-pure', '2' => 'files/adaptivnyi-shablon-pure-2'], $run['list']);
        $this->assertSame(1, $run['listq']);
        $this->assertSame([[], [], []], $run['none']);
    }

    # An unknown kind, a zero id, a prefix outside the grammar, a type with a prefix and a moved type are refused; a path taken in a rolled back transaction is undone
    #[Test]
    public function aWriteJoinsItsWriter(): void
    {
        $run = $this->getRun('registry');
        $this->assertSame(['', '', '', '', ''], $run['refuse']);
        $this->assertSame('files/inside', $run['inside']);
        $this->assertTrue($run['open'], 'a write inside the transaction of its writer closed that transaction');
        $this->assertSame([], $run['undone'], 'the rollback of the writer left the path behind');
    }

    # Two writers racing for one free path: the second waits on the unique key and takes the next suffix inside its own transaction
    #[Test]
    public function twoWritersRaceForOnePath(): void
    {
        $run = $this->getRun('race');
        $this->assertTrue($run['waited'], 'the second writer was never seen waiting on the key, so no race was run');
        $this->assertSame(['files/race', 'files/race-2'], [$run['first'], $run['second']]);
        $this->assertSame([[['files/race', 1, 1]], [['files/race-2', 1, 1]]], $run['rows']);
    }

    # Two writers moving one entity: the second waits on its current row, which snapshot isolation of MariaDB answers with 1020, and its own transaction runs again
    #[Test]
    public function twoWritersMoveOneEntity(): void
    {
        $run = $this->getRun('move');
        $this->assertTrue($run['waited'], 'the second writer was never seen waiting on the current row, so no race was run');
        $this->assertSame(['files/moved-a', 'files/moved-b'], [$run['first'], $run['second']]);
        $this->assertSame([[['files/moved', 0, null], ['files/moved-a', 0, null], ['files/moved-b', 1, 1]]], $run['rows']);
    }

    # An operation of the module or the type of a prefix takes a suffix there and only there; a type takes its name or nothing
    #[Test]
    public function reservedWordsTakeASuffix(): void
    {
        $run = $this->getRun('reserved');
        $want = ['files/add-2', 'files/view-2', 'files/mode-2', 'forum/move-2', 'forum/send-2', 'users/rules-2', 'users/newlang-2', 'voting/view-2', 'users/view'];
        $this->assertSame($want, $run['ops']);
        $this->assertSame(['files', '', 'recipes', ''], $run['type'], 'a taken or reserved type name was given');
        $want = ['recipes' => false, 'books' => true, 'files' => false, 'uploads' => false, 'api' => false, 'demo' => false, 'phoenix' => false, 'templates' => false,
            'forum' => false, 'account' => false, 'node' => false, 'admin' => false, 'Books' => false, '1abc' => false];
        $this->assertSame($want, $run['names']);
    }

    # The operations Route reserves are the cases of the switch of every module and the operations of getNodeOps(), so no module answers one the registry could give
    #[Test]
    public function theOperationsMatchTheModules(): void
    {
        $ops = $this->getConst('OPS');
        $base = dirname(__DIR__, 2).'/modules';
        $seen = [];
        foreach (scandir($base) ?: [] as $mod) {
            $file = $base.'/'.$mod.'/index.php';
            if ($mod === '.' || $mod === '..' || !is_file($file)) continue;
            if (!preg_match('/^switch \(\$op\) \{\n(.*?)^\}/ms', (string)file_get_contents($file), $hit)) continue;
            preg_match_all("/case '([a-z_]+)':/", $hit[1], $cases);
            $want = $cases[1];
            $have = $ops[$mod] ?? [];
            sort($want);
            sort($have);
            $this->assertSame($want, $have, 'Route::OPS of '.$mod.' leaves the switch of its module');
            $seen[] = $mod;
        }
        $node = (string)file_get_contents($base.'/node/index.php');
        $this->assertSame(1, preg_match('/function getNodeOps\(.*?\n\}/s', $node, $func), 'getNodeOps() is gone');
        preg_match_all("/'([a-z]+)' => \[/", $func[0], $keys);
        $want = $keys[1];
        $have = $ops['node'];
        sort($want);
        sort($have);
        $this->assertSame($want, $have, 'Route::OPS of a type leaves getNodeOps()');
        $seen[] = 'node';
        $this->assertSame([], array_diff(array_keys($ops), $seen), 'Route::OPS names a module without a switch');
    }

    # The operations public/index.php answers under any name are reserved under every prefix
    #[Test]
    public function theCommonOperationsMatchTheEntry(): void
    {
        preg_match_all("/\\\$op === '([a-z]+)'/", (string)file_get_contents(dirname(__DIR__, 2).'/public/index.php'), $hit);
        $want = array_values(array_unique($hit[1]));
        $have = $this->getConst('COMMON');
        sort($want);
        sort($have);
        $this->assertSame($want, $have);
    }

    # Every entry of public/ is a name no type takes, whether it is reserved by Route or a module; uploads and api are reserved although they are no entry
    #[Test]
    public function theTreeIsReserved(): void
    {
        $root = dirname(__DIR__, 2);
        $types = $this->getConst('TYPES');
        $bad = [];
        foreach (scandir($root.'/public') ?: [] as $item) {
            if ($item[0] === '.') continue;
            $name = strtolower(explode('.', $item)[0]);
            if (!in_array($name, $types, true) && !is_dir($root.'/modules/'.$name)) $bad[] = $item;
        }
        $this->assertSame([], $bad, 'An entry of public/ is free for a type');
        $this->assertContains('uploads', $types);
        $this->assertContains('api', $types);
    }
}
