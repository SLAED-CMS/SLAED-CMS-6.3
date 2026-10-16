<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Update;

# Settings, languages and themes of an update: missing options merged into config/, the sections a site owns kept, languages and the two themes replaced whole
final class UpdateMergeTest extends TestCase
{
    private static array $probe = [];

    public static function setUpBeforeClass(): void
    {
        require_once BASE_DIR.'/core/classes/update.php';
    }

    # Run the merge of tests/Support/config_probe.php once in a fresh process; it works on a scratch copy of config/, never on the site's
    private function getProbe(): array
    {
        if (self::$probe) return self::$probe;
        $script = dirname(__DIR__).'/Support/config_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_config_merge_'.getmypid();
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' merge '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The merge probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The merge probe failed');
        $this->assertFalse($data['site'], 'The probe left a marker in the journal of the site itself');
        return self::$probe = $data['data'];
    }

    # A trial names every option it would add and writes nothing; the run adds exactly those through setConfigFile() and a second run finds none
    #[Test]
    public function aTrialNamesWhatTheRunAdds(): void
    {
        $data = $this->getProbe();
        $want = [
            'fields.php' => ['fields.probeset'],
            'global.php' => ['probenew'],
            'modules.php' => ['modules.probemod'],
            'node.php' => ['node.defaults.list.probeopt'],
            'probefile.php' => ['probefile.one'],
            'scheduler.php' => ['scheduler.jobs.cachegc.probekey', 'scheduler.jobs.probejob'],
            'uploads.php' => ['uploads.probenew'],
        ];
        $this->assertSame(['ok' => true, 'error' => '', 'name' => '', 'adds' => $want], $data['test']);
        $this->assertTrue($data['same'], 'The trial wrote a settings file');
        $this->assertSame([true, '', '', true], $data['run'], 'The run failed or added other options than the trial named');
        $this->assertSame([], $data['again'], 'A second run found options to add again');
        $this->assertSame(array_keys($want), $data['touched'], 'The run wrote a file with nothing to add, or system.php');
        $this->assertSame(['marker' => false, 'dirs' => 0], $data['trace'], 'The run left the journal of the configuration behind');
    }

    # A new option reaches the site at any depth, flat in global.php, as a scheduler job, a module, a Node default kept as an int, and a whole missing file
    #[Test]
    public function aShippedOptionReachesTheSite(): void
    {
        $data = $this->getProbe();
        $this->assertSame('shipped', $data['global'][0], 'A new flat option of global.php was not added');
        $this->assertSame([true, 'x'], $data['jobs'], 'A new scheduler job or a new key of a job was not added');
        $this->assertTrue($data['module'], 'A new module of the release got no entry');
        $this->assertSame(5, $data['node'][0], 'A new Node default was not added through the Closure form with its type');
        $this->assertSame(['probefile' => ['one' => '1']], $data['file'], 'A settings file the site lacks was not placed');
        $this->assertSame(['shipped', 5], $data['local'], 'local.php was not published with the merge');
    }

    # What the site saved stays: its own value, its list whole, a deleted Node type and field gone, no section of a type it does not have
    #[Test]
    public function whatTheSiteSavedStays(): void
    {
        $data = $this->getProbe();
        $this->assertSame('Probe site', $data['global'][1], 'A value the site saved was replaced');
        $this->assertSame([['probe'], ['https://x.example']], $data['api'], 'A list of the site was merged or replaced');
        $this->assertSame([false, false, false], array_slice($data['node'], 1), 'A Node type came back, a new type was added or a type section was merged');
        $this->assertSame([false, true], $data['fields'], 'A deleted field came back or a new field set was not added');
        $this->assertSame([false, false, '1'], $data['uploads'], 'An upload rule of a missing Node type was added');
        $this->assertSame([false, false], $data['ratings'], 'A rating rule of a missing Node type was added');
    }

    # The rule of the merge on its own: lists by value, a type section and a field set by the site, a clash of shapes by the site, missing maps by their keys
    #[Test]
    public function theMergeKeepsShapesTheSiteOwns(): void
    {
        $site = ['list' => ['a', 'b'], 'empty' => [], 'text' => 'mine', 'deep' => ['keep' => '1']];
        $pack = ['list' => ['x', 'y', 'z'], 'empty' => ['new' => '2'], 'text' => ['no' => '3'], 'deep' => ['keep' => '9', 'add' => '4'], 'fresh' => ['one' => '5']];
        $res = Update::getConfigMerge($site, $pack, []);
        $want = ['list' => ['a', 'b'], 'empty' => ['new' => '2'], 'text' => 'mine', 'deep' => ['keep' => '1', 'add' => '4'], 'fresh' => ['one' => '5']];
        $this->assertSame($want, $res['data']);
        $this->assertSame(['empty.new', 'deep.add', 'fresh'], $res['adds']);
        $this->assertSame(['data' => ['a', 'b'], 'adds' => []], Update::getConfigMerge(['a', 'b'], ['x', 'y', 'z'], []), 'A list was merged by its indexes');
        $site = ['fields' => ['account' => ['field1' => ['a' => '1']], 'node' => ['news' => ['f' => []]]]];
        $pack = ['fields' => ['account' => ['field1' => ['a' => '1', 'b' => '2'], 'field2' => []], 'forum' => ['field1' => []],
            'node' => ['faq' => ['f' => []], 'news' => ['g' => []]]]];
        $res = Update::getConfigMerge($site, $pack, ['news', 'faq']);
        $this->assertSame(['fields.forum'], $res['adds'], 'A field set was merged, or a field set of a Node type was added or merged');
        $this->assertSame(['account', 'node', 'forum'], array_keys($res['data']['fields']));
        $res = Update::getConfigMerge(['node' => []], ['node' => ['types' => ['news' => ['version' => 1]], 'limits' => ['max' => 1]]], ['news'], '');
        $this->assertSame(['node' => ['limits' => ['max' => 1]]], $res['data'], 'A missing types map brought a Node type along');
        $this->assertSame(['node.limits'], $res['adds']);
        $res = Update::getConfigMerge(['news' => 'a', 'all' => 'b'], ['news' => 'x', 'faq' => 'y', 'all' => 'z', 'typ' => 'w'], ['news', 'faq'], 'uploads');
        $this->assertSame(['data' => ['news' => 'a', 'all' => 'b', 'typ' => 'w'], 'adds' => ['uploads.typ']], $res, 'An upload rule of a missing type was added');
    }

    # A package with a Node file but no node.php names no shipped type, so a section of a deleted type could come back: the merge refuses it before it writes
    #[Test]
    public function aNodeFileNeedsNodePhpBeside(): void
    {
        $dir = UPDATE_DIR.'/merge';
        if (!is_dir($dir.'/config')) mkdir($dir.'/config', 0755, true);
        file_put_contents($dir.'/config/uploads.php', "<?php\nreturn ['uploads' => ['faq' => 'x']];\n");
        $res = Update::addConfigMerge($dir, true);
        unlink($dir.'/config/uploads.php');
        rmdir($dir.'/config');
        rmdir($dir);
        $this->assertSame(['ok' => false, 'error' => 'pack', 'name' => 'config/node.php', 'adds' => []], $res);
    }

    # Language files of every place the page Languages edits and the themes admin and lite are replaced whole; code, settings and other themes are not
    #[Test]
    public function languagesAndTheTwoThemesAreReplacedWhole(): void
    {
        foreach (['lang/ru.php', 'admin/lang/en.php', 'modules/news/lang/de.php', 'modules/forum/admin/lang/uk.php', 'public/templates/admin/fragments/alert.html',
            'public/templates/lite/assets/css/theme.css'] as $path) {
            $this->assertTrue(Update::isSwapFile($path), $path.' is not replaced whole');
        }
        foreach (['core/system.php', 'lang/index.html', 'lang/sub/ru.php', 'config/lang.php', 'public/templates/mytheme/index.html', 'public/templates/admin',
            'public/templates/lite2/x.css', 'modules/news/index.php', 'admin/modules/lang.php'] as $path) {
            $this->assertFalse(Update::isSwapFile($path), $path.' passed for a file replaced whole');
        }
    }
}
