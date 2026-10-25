<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The catch-up of a 6.3 site of early October: archive and old folder addresses, the merge of the shipped config/ and the drop of the tables of the gone modules
final class UpdateArchiveTest extends TestCase
{
    private const DONE = [
        'nodes.intro #1' => '[attach=a.gif align=left title=A size=full]',
        'nodes.body #1' => '[attach=b.png align=none title=title] [img]./uploads/archive/news/gone.gif[/img]'
            .' [usehtml]<img src="index.php?go=file&own=node&id=1&key=a.gif">[/usehtml]',
        'nodes.body #2' => '[attach=c.jpg align=none title=C size=full] [attach=d.png align=none title=title size=full]',
        'nodes.intro #3' => '[attach=e.gif align=none title=title size=full]',
        'comment.body #1' => '[attach=a.gif align=center title=title size=full]',
        'comment.body #2' => '[img]./index.php?go=file&own=node&id=1&key=a.gif[/img]',
        'forum.body #1' => '[img=center alt=T]./index.php?go=file&own=node&id=1&key=a.gif[/img]',
        'forum.body #2' => '[img]http://other.net/uploads/archive/news/a.gif[/img] [code]uploads/archive/news/a.gif[/code]',
        'privat.body #1' => '[url=./index.php?go=file&own=node&id=2&key=c.jpg]C[/url]',
        'message.body #1' => '[img]./uploads/archive/news/e.gif[/img]',
    ];

    private const COUNT = ['nodes.intro' => 2, 'nodes.body' => 2, 'comment.body' => 2, 'forum.body' => 1, 'privat.body' => 1];

    private static array $probe = [];

    # The probe tests/Support/update_probe.php lifts the functions out of update.php by name and drives them against a disposable schema, never the stand database
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_archive';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' archive 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs'];
        }
        return self::$probe;
    }

    # The dry run counts per column what the write run then changes and writes neither a text nor a manifest
    #[Test]
    public function theDryRunCountsAndWritesNothing(): void
    {
        $run = $this->getRun()['clean'];
        $this->assertTrue($run['dry']['done'], $run['dry']['text']);
        $this->assertNull($run['dry']['info'], 'The dry run wrote a manifest');
        $this->assertStringContainsString('nodes.intro 2, nodes.body 2, comment.body 2, forum.body 1, privat.body 1), addresses that stay: 3', $run['dry']['text']);
        $this->assertStringStartsWith('[img=left alt=A]./uploads/archive/news/a.gif', $run['dry']['texts']['nodes.intro #1'], 'The dry run rewrote a text');
        $this->assertSame(self::COUNT, $run['write']['info']['count'], 'The manifest does not keep the counts of the dry run');
    }

    # A material and the comments of its type take attachments for the archive, the old folder and the folder of the type, the raw HTML the file address
    # A comment of a gone material and the other texts reach the published material that carries the name; a draft, another host, code and a missing file stay
    #[Test]
    public function everyAddressReachesItsFileOrStays(): void
    {
        $run = $this->getRun()['clean']['write'];
        $this->assertTrue($run['done'], $run['text']);
        $this->assertSame(self::DONE, $run['texts']);
        $this->assertSame('verified', $run['info']['state']);
        $this->assertSame(3, $run['info']['left']);
    }

    # A verified unit is skipped, and a run that stopped after the first material continues after its cursor and is verified again
    #[Test]
    public function aRepeatAndAResumeChangeNothingTwice(): void
    {
        $run = $this->getRun()['clean'];
        $this->assertStringContainsString('caught up by an earlier run', $run['repeat']['text']);
        $this->assertSame(self::DONE, $run['repeat']['texts']);
        $this->assertTrue($run['resume']['done'], $run['resume']['text']);
        $this->assertSame(self::DONE, $run['resume']['texts']);
        $this->assertSame(['nodes' => 3, 'comment' => 2, 'forum' => 2, 'privat' => 1, 'message' => 1], $run['resume']['info']['cursor']);
    }

    # A check that still finds texts to change starts the cursors over, so the repeat its refusal asks for rewrites them instead of finding nothing after the cursor
    #[Test]
    public function aFailedCheckStartsTheCursorsOver(): void
    {
        $run = $this->getRun()['clean'];
        $this->assertFalse($run['stuck']['done']);
        $this->assertStringContainsString('texts still change after the rewrite', $run['stuck']['text']);
        $this->assertSame([[], 'applying'], [$run['stuck']['info']['cursor'], $run['stuck']['info']['state']]);
        $this->assertTrue($run['unstuck']['done'], $run['unstuck']['text']);
        $this->assertSame(self::DONE, $run['unstuck']['texts']);
    }

    # Without the shipped folder the step refuses; with it the retired keys leave, css_f and script_f keep the values of the site and the missing options arrive
    # A type section stays the site's, a shipped type the site lacks is not added, a file the site keeps out of the merge is not written, and a repeat adds nothing
    #[Test]
    public function theSettingsStepMergesTheShippedConfig(): void
    {
        $run = $this->getRun()['merge'];
        $this->assertTrue($run['nonered']);
        $this->assertStringContainsString('storage/backup/release/config', $run['none']);
        $one = $run['first'];
        $this->assertTrue($one['done'], $one['text']);
        $this->assertSame(['css_f' => 'own.css', 'homeurl' => 'https://slaed.net', 'script_f' => 'own.js', 'webroot' => ''], $one['global']);
        $this->assertSame(['fresh' => '1', 'keep' => 'site'], $one['users']);
        $this->assertSame(['defaults' => ['list' => ['views' => ['list']]], 'limits' => ['edit' => 600], 'types' => ['news' => ['own' => 1]]], $one['node']);
        $this->assertSame(['google' => ['client' => '']], $one['oauth']);
        $this->assertFalse($one['system'], 'A reserved file of the shipped folder was written into the site');
        $this->assertStringContainsString('added (none)', $run['again']);
    }

    # The tables of gone modules drop, and a source table drops once each row is in Node: a published one by _node_legacy, a pending one by a material of its title and time
    # The pending news lies in a cp1251 table with a trailing space; a row matched by neither keeps its table with its id, until a later run finds it carried
    #[Test]
    public function theSourceTablesDropOnceTheirRowsAreInNode(): void
    {
        $run = $this->getRun()['tables'];
        $want = ['probe tables of the removed modules dropped: order, whois', 'probe source tables of Node dropped, every row is a material: news, pages, help',
            'source tables of Node kept, rows not in Node yet: probe_faq 1 (ids: 20); probe_files 1 (ids: 40)'];
        $this->assertSame($want, array_column($run['first']['rows'], 'text'));
        $this->assertSame([true, true, true], array_column($run['first']['rows'], 'done'));
        $this->assertSame(['faq', 'files'], $run['first']['left']);
        $this->assertSame([$want[2]], array_column($run['again']['rows'], 'text'), 'A repeat dropped or kept something else');
        $this->assertSame(['probe source tables of Node dropped, every row is a material: faq, files'], array_column($run['last']['rows'], 'text'));
        $this->assertSame([], $run['last']['left']);
    }
}
