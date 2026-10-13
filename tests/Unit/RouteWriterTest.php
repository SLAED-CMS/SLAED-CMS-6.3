<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The writers of the address registry of docs/10-URL-2026.md, batch 2, over route_probe.php address; a row is [path, state, cur]
final class RouteWriterTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its address mode and memoize the run; the requests may write no PHP and no SQL error besides the refusals of the trigger
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_address_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' address 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The requests wrote SQL errors besides the refusals of the trigger');
            self::$probe = $data['runs']['address'];
        }
        return self::$probe;
    }

    # A material gets its path on publication, keeps it through a new title, moves only by the field and leaves every path gone, never given again
    #[Test]
    public function aMaterialGetsMovesAndLosesItsPath(): void
    {
        $run = $this->getRun()['node'];
        $this->assertSame([303, [['news/adaptivnyi-shablon-pure', 1, 1]]], $run['made'], 'A published material has no path from its title');
        $this->assertSame([303, []], $run['draft'], 'A draft got a path');
        $this->assertSame(['adaptivnyi-shablon-pure', true, false], $run['field'], 'The field Address shows the name, and only for a material with a path');
        $this->assertSame([303, [['news/adaptivnyi-shablon-pure', 1, 1]]], $run['retitle'], 'A new title moved the path');
        $this->assertSame([303, [['news/adaptivnyi-shablon-pure', 0, null], ['news/novyi-adres', 1, 1]]], $run['moved'], 'The field did not move the path');
        $this->assertSame([303, [['news/chernovik', 1, 1]]], $run['publish'], 'The move of a draft to published gave no path');
        $this->assertSame([303, 2, []], $run['private'], 'A published request of the private type got a path');
        $this->assertSame([303, 0, [['news/adaptivnyi-shablon-pure', 2, null], ['news/novyi-adres', 2, null]]], $run['delete'], 'A deleted material kept a live path');
        $this->assertSame([303, [['news/adaptivnyi-shablon-pure-2', 1, 1]]], $run['noreuse'], 'The path of a deleted material was given again');
    }

    # A type takes its name as its path, a deleted one leaves it gone, and neither its name nor a reserved word can be taken by a new type
    #[Test]
    public function aTypeHoldsItsNameForEver(): void
    {
        $run = $this->getRun()['type'];
        $this->assertSame([303, [['recipes', 1, 1]]], $run['made']);
        $this->assertSame([303, 0, [['recipes', 2, null]]], $run['delete']);
        $this->assertSame(['recipes' => 0, 'api' => 0, 'uploads' => 0], $run['refused'], 'A gone or reserved name became a type');
    }

    # A category of a type and of the forum gets its path on creation, a taken name its suffix, the field moves it, a new module carries it over, a deletion leaves it gone
    #[Test]
    public function aCategoryGetsMovesAndLosesItsPath(): void
    {
        $run = $this->getRun()['cat'];
        $this->assertSame([303, [['news/moduli', 1, 1]]], $run['made']);
        $this->assertSame([303, [['news/moduli-2', 1, 1]]], $run['twin'], 'A taken name in the field did not take a suffix');
        $this->assertSame(['moduli', true], $run['field'], 'The edit form shows no name or no whole path');
        $this->assertSame([303, [['news/moduli', 0, null], ['news/plaginy', 1, 1]], 200], $run['moved']);
        $this->assertSame([true, false], $run['cache'], 'The category cache does not carry the path or keeps it after the move');
        $this->assertSame([303, [['news/moduli', 0, null], ['news/plaginy', 0, null], ['forum/plaginy', 1, 1]]], $run['modul'], 'A new module did not carry the name over');
        $this->assertSame([303, 0, [['news/moduli', 2, null], ['news/plaginy', 2, null], ['forum/plaginy', 2, null]]], $run['delete'], 'A deleted forum category');
        $this->assertSame([303, 0, [['news/moduli-2', 2, null]]], $run['nodedelete'], 'A deleted category of a type');
        $this->assertSame([303, [['forum/obshchie-voprosy', 1, 1]]], $run['forum']);
    }

    # A topic gets its path on creation, a reply none until the recycle section makes it a topic, the recycle section keeps a path and a deletion for good leaves it gone
    #[Test]
    public function aTopicGetsAndLosesItsPath(): void
    {
        $run = $this->getRun()['topic'];
        $this->assertSame([303, [['forum/modul-poiska', 1, 1]]], $run['made']);
        $this->assertSame([303, true, []], $run['reply'], 'A reply got a path');
        $this->assertSame([303, 0, [['forum/modul-poiska-2', 1, 1]]], $run['binreply'], 'A reply the recycle section made a topic has no path');
        $this->assertSame([303, 11, [['forum/modul-poiska', 1, 1]]], $run['bin'], 'The recycle section took the path');
        $this->assertSame([303, 0, [['forum/modul-poiska', 2, null]]], $run['delete']);
    }

    # An account gets its path when its row is created, a new login moves it, the freed path is never given to the next account, a deletion leaves every path gone
    #[Test]
    public function anAccountGetsMovesAndLosesItsPath(): void
    {
        $run = $this->getRun()['user'];
        $this->assertSame([303, [['users/eduard', 1, 1]]], $run['made']);
        $this->assertSame([303, [['users/eduard', 1, 1]]], $run['same'], 'A save with the same login moved the path');
        $this->assertSame([303, [['users/eduard', 0, null], ['users/edik', 1, 1]]], $run['rename']);
        $this->assertSame([303, [['users/eduard-2', 1, 1]]], $run['taken'], 'The former path of another account was handed on');
        $this->assertSame([303, 0, [['users/eduard', 2, null], ['users/edik', 2, null]]], $run['delete']);
        $this->assertSame([200, [['users/dora', 1, 1]]], $run['activate'], 'The activation gave no path');
    }

    # A poll gets its path on creation, the field moves it and a deletion leaves every path gone
    #[Test]
    public function aPollGetsMovesAndLosesItsPath(): void
    {
        $run = $this->getRun()['poll'];
        $this->assertSame([303, [['voting/kak-vam-novyi-dizain', 1, 1]]], $run['made']);
        $this->assertSame('kak-vam-novyi-dizain', $run['field']);
        $this->assertSame([303, [['voting/kak-vam-novyi-dizain', 0, null], ['voting/dizain', 1, 1]]], $run['moved']);
        $this->assertSame([303, 0, [['voting/kak-vam-novyi-dizain', 2, null], ['voting/dizain', 2, null]]], $run['delete']);
    }

    # A refused path takes its entity with it: no material, category, topic, account or poll and no route row is left behind, and every writer met the refusal
    #[Test]
    public function aFailedPathLeavesNothingBehind(): void
    {
        $run = $this->getRun();
        $this->assertSame(['node' => 0, 'forumcat' => 0, 'nodecat' => 0, 'topic' => 0, 'user' => 0, 'poll' => 0, 'routes' => 0], $run['fail']);
        $this->assertSame(7, $run['refusals'], 'A writer did not reach the registry, so its refusal proves nothing');
        $this->assertSame(0, $run['apart'], 'state and cur disagree on a row');
    }
}
