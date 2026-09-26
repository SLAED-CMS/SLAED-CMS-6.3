<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S17 of docs/node: the ten shipped profiles of modules/node/profiles. The files are exports of the slaed.node format with the settings
 * of docs/node/06 in canonical form and empty upload and rating rules, which a create fills with the rules of the site. A clean installation
 * turns every profile into an active type through the one import path when its first administrator is created, and writes the starter news;
 * an update creates no type. The installation is driven by tests/Support/install_probe.php over real HTTP on a copy of the release with
 * a disposable MariaDB database: the installer, the panel, the public pages, private support and the external source of content.
 */
final class NodeProfileTest extends TestCase
{
    private const NAMES = ['content', 'docs', 'faq', 'files', 'help', 'jokes', 'links', 'media', 'news', 'pages'];

    private static array $probe = [];

    private static array $fail = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    # One shipped profile, decoded
    private static function getProfile(string $name): array
    {
        return json_decode((string)file_get_contents(self::getRoot().'/modules/node/profiles/'.$name.'.json'), true, 64, JSON_THROW_ON_ERROR);
    }

    # Run the installation probe in one mode and answer its report; a probe that cannot create its database is a failure, not a skip
    private function getProbe(string $mode): array
    {
        $script = dirname(__DIR__).'/Support/install_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_install';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' '.escapeshellarg($mode).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'The probe failed');
        $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
        return $data['runs'];
    }

    # The runs of the whole installation, memoized
    private function getRuns(): array
    {
        if (self::$probe === []) self::$probe = $this->getProbe('');
        return self::$probe;
    }

    # The roles of a profile as role => [mode, kinds, min, max, canlink, report, sort]
    private static function getRoles(array $prof): array
    {
        return array_map(fn($v) => [$v['mode'], $v['kinds'], $v['min'], $v['max'], $v['canlink'], $v['report'], $v['sort']], $prof['type']['settings']['assets']);
    }

    # The ten files ship in the canonical export form: the exact envelope and keys, the name of the file, the canonical JSON of the export and empty rules
    #[Test]
    public function theTenProfilesShipInTheExportFormat(): void
    {
        $files = array_map(fn($v) => basename($v, '.json'), glob(self::getRoot().'/modules/node/profiles/*') ?: []);
        $this->assertSame(self::NAMES, $files);
        $sects = ['list', 'view', 'form', 'workflow', 'admin', 'features', 'assets', 'integrations', 'ext'];
        foreach (self::NAMES as $name) {
            $raw = (string)file_get_contents(self::getRoot().'/modules/node/profiles/'.$name.'.json');
            $data = self::getProfile($name);
            $this->assertSame($raw, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", $name);
            $this->assertSame(['format', 'version', 'type'], array_keys($data), $name);
            $this->assertSame(['slaed.node', 1], [$data['format'], $data['version']], $name);
            $this->assertSame(['name', 'title', 'intro', 'ext', 'sort', 'settings', 'fields', 'uploads', 'rating'], array_keys($data['type']), $name);
            $this->assertSame($name, $data['type']['name']);
            $this->assertSame('_'.strtoupper($name), $data['type']['title']);
            $this->assertSame([[], []], [$data['type']['uploads'], $data['type']['rating']], $name);
            $this->assertSame($sects, array_keys($data['type']['settings']), $name);
            $this->assertSame([[], []], [$data['type']['settings']['form'], $data['type']['settings']['admin']], $name);
        }
        $sorts = array_map(fn($v) => self::getProfile($v)['type']['sort'], ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'docs']);
        $this->assertSame([10, 20, 30, 40, 50, 60, 70, 80, 90, 100], $sorts);
    }

    # Each profile carries the settings docs/node/06 approves: display mode, extension, SEO, the switches that differ, the roles and the fields
    #[Test]
    public function eachProfileCarriesItsApprovedSettings(): void
    {
        $want = [
            'news' => ['article', '', 'news'], 'pages' => ['article', '', 'article'], 'faq' => ['faq', '', 'article'], 'help' => ['support', 'support', 'website'],
            'jokes' => ['default', '', 'article'], 'content' => ['article', 'sync', 'article'], 'links' => ['default', '', 'website'], 'files' => ['files', '', 'article'],
            'media' => ['media', '', 'article'], 'docs' => ['docs', '', 'article'],
        ];
        $all = ['file', 'image', 'audio', 'video'];
        $cover = ['image', ['image'], 0, 1, false, false, 10];
        $down = ['download', $all, 0, 10, true, true, 10];
        $roles = [
            'news' => ['cover' => $cover, 'gallery' => ['gallery', ['image'], 0, 20, false, false, 20]], 'pages' => ['download' => $down], 'faq' => ['download' => $down],
            'files' => ['cover' => $cover, 'download' => ['download', $all, 1, 10, true, true, 20]],
            'links' => ['cover' => $cover, 'link' => ['link', ['file'], 1, 1, true, true, 20]],
            'media' => ['poster' => ['none', ['image'], 0, 1, false, false, 10], 'source' => ['player', ['audio', 'video'], 1, 10, true, false, 20],
                'gallery' => ['gallery', ['image'], 0, 30, false, false, 30], 'download' => ['download', $all, 0, 10, true, true, 40]],
        ];
        $on = [
            'news' => ['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'pages' => ['categories', 'comments', 'rating', 'favorites', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'content' => ['categories', 'comments', 'rating', 'favorites', 'pinned', 'schedule', 'related'],
            'files' => ['categories', 'comments', 'rating', 'favorites', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'docs' => ['categories', 'favorites', 'related', 'tree'],
            'help' => ['categories', 'comments', 'submit'],
        ];
        $on += ['faq' => $on['pages'], 'jokes' => $on['pages'], 'links' => $on['files'], 'media' => $on['files']];
        foreach ($want as $name => [$mode, $ext, $seo]) {
            $type = self::getProfile($name)['type'];
            $set = $type['settings'];
            $this->assertSame([$mode, $ext, $seo], [$set['view']['mode'], $type['ext'], $set['integrations']['seo']], $name);
            $this->assertSame($on[$name], array_keys(array_filter($set['features'])), $name);
            $this->assertSame($roles[$name] ?? [], self::getRoles(['type' => $type]), $name);
        }
        $this->assertSame(['mail' => true], self::getProfile('help')['type']['settings']['ext']);
        $this->assertSame(['pending' => false, 'result' => false], self::getProfile('help')['type']['settings']['workflow']['notify']);
        $this->assertSame(['orders' => ['title', 'updated', 'views'], 'order' => 'title', 'dir' => 'asc', 'limit' => 50, 'alpha' => true, 'show' => ['category', 'date', 'views']],
            self::getProfile('docs')['type']['settings']['list']);
        $this->assertSame([25, true], [self::getProfile('files')['type']['settings']['list']['limit'], self::getProfile('files')['type']['settings']['list']['alpha']]);
        $this->assertSame(['release' => ['text', 100], 'site' => ['url', null]], array_map(fn($v) => [$v['type'], $v['options']['max'] ?? null],
            self::getProfile('files')['type']['fields']));
        $media = self::getProfile('media')['type']['fields'];
        $this->assertSame(['subtitle', 'year', 'director', 'cast', 'creator', 'runtime', 'language', 'notes', 'format', 'quality', 'filesize', 'release'], array_keys($media));
        $this->assertSame(['int', 1, 9999, null], [$media['year']['type'], $media['year']['options']['min'], $media['year']['options']['max'], $media['year']['default']]);
        $this->assertSame(['textarea', 262144], [$media['notes']['type'], $media['notes']['options']['max']]);
        foreach ($media as $one) $this->assertSame([false, false, true], [$one['req'], $one['multi'], $one['active']]);
        foreach (array_diff(self::NAMES, ['files', 'media']) as $name) $this->assertSame([], self::getProfile($name)['type']['fields'], $name);
    }

    # Every label constant of the profiles - type titles, role titles and field titles - is defined in the site language of all six locales,
    # because the panel checks it while it creates a type and the site shows it
    #[Test]
    public function everyLabelOfTheProfilesIsDefinedInSixLanguages(): void
    {
        $keys = [];
        foreach (self::NAMES as $name) {
            $type = self::getProfile($name)['type'];
            $keys[] = $type['title'];
            foreach ($type['settings']['assets'] as $one) $keys[] = $one['title'];
            foreach ($type['fields'] as $one) $keys[] = $one['title'];
        }
        $keys = array_values(array_unique(array_filter($keys, fn($v) => str_starts_with($v, '_'))));
        $this->assertNotEmpty($keys);
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
            $text = (string)file_get_contents(self::getRoot().'/lang/'.$lang.'.php');
            foreach ($keys as $key) $this->assertStringContainsString("define('".$key."',", $text, $lang.': '.$key);
        }
    }

    # Only a new installation leaves the mark that makes the first administrator create the types; the update branch of the installer creates no type
    #[Test]
    public function onlyANewInstallationLeavesTheMark(): void
    {
        $code = (string)file_get_contents(self::getRoot().'/setup/index.php');
        $from = strpos($code, "if (\$setup == 'new') {");
        $to = strpos($code, "} elseif (\$setup == 'update4_1') {");
        $this->assertNotFalse($from);
        $this->assertNotFalse($to);
        $this->assertSame(1, substr_count($code, "'node' => 'new'"));
        $this->assertStringContainsString("'node' => 'new'", substr($code, $from, $to - $from));
        $this->assertStringNotContainsString('NodeService', $code);
    }

    # The clean installation: the form of the installer opens without config/db.php and saving writes it, the installer leaves the mark and drops the types
    # an earlier installation left in the configuration of the tree with their four areas, the first administrator gets ten active types with every shared area
    # and directory, the starter news, the mark gone, and a second request creates nothing
    #[Test]
    public function aCleanInstallationCreatesTheTenTypes(): void
    {
        $run = $this->getRuns()['setup'];
        $this->assertSame([200, 'new', true], $run['setup']);
        $this->assertSame([true, 200, true, true], $run['dbfile'], 'The release carries no config/db.php: the form opens without it and the installer writes it');
        $this->assertSame([true, false, false, true, true], $run['lock'], 'The installed site keeps setup.php shut: no form, no secret, no write, no renamed panel');
        $this->assertTrue($run['unlock'], 'The owner key opens the installer again');
        $this->assertSame(0, $run['before']);
        $this->assertSame(303, $run['admin'][0]);
        $want = [];
        foreach (['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'docs'] as $i => $name) {
            $want[] = [$name, '_'.strtoupper($name), ['help' => 'support', 'content' => 'sync'][$name] ?? '', 1, ($i + 1) * 10, 2];
        }
        $this->assertSame($want, $run['types']);
        $this->assertSame(array_fill_keys(self::NAMES, 2), $run['node']);
        $order = array_column($want, 0);
        $this->assertSame(['uploads' => $order, 'ratings' => $order, 'fields' => ['files', 'media']], $run['areas']);
        $this->assertSame([true, false, false, false, false], $run['ghost'], 'Types of an earlier installation survived the clean installation');
        $this->assertSame($order, $run['dirs']);
        $this->assertFalse($run['mark']);
        $this->assertSame([['name' => 'news', 'cid' => 0, 'uid' => 0, 'aname' => 'SLAED', 'title' => 'Добро пожаловать в SLAED CMS', 'status' => 2, 'home' => 1,
            'comon' => 2]], $run['starter']);
        $this->assertSame([303, 1, 10], $run['again']);
        $this->assertSame([false, false], $run['notice']);
    }

    # The unlocked installer over real HTTP: its form carries no password and an empty one connects with the stored password; a clean installation over
    # the tables of its prefix, a prefix and a panel name outside their grammar, an update whose prefix has no tables and a wrong password are refused
    # with their reason and no fatal error, config/ and the key untouched; a clean installation whose data file fails writes no mark and keeps the key
    #[Test]
    public function theUnlockedInstallerRefusesBeforeItWrites(): void
    {
        $run = $this->getRuns()['setup']['refuse'];
        $this->assertSame([true, false], $run['form'], 'The form of the installer shows the stored password');
        $this->assertSame([true, true], $run['keep'], 'An empty password field did not keep the stored password, or the refused run wrote a file');
        $this->assertSame([true, false], $run['wrong'], 'A wrong password ends in a fatal error instead of the reason');
        $this->assertSame([true, true, true, true], [$run['fresh'], $run['prefix'], $run['afile'], $run['none']]);
        $this->assertSame([true, true], $run['same'], 'A refused run changed config/ or took the key');
        $this->assertSame([true, false, true, true], $run['ddl'], 'A failed clean installation wrote its marks or took the key');
        $this->assertSame(['error_php' => 0, 'error_sql' => 0, 'error_site' => 2], $run['logs'], 'Only the two refused connections are logged');
    }

    # A profile the installation cannot finish - here a user file in uploads/jokes, which NOD-200 refuses to take over - is named in the notice of the next page and
    # in the site log with its step, the other nine types and the starter news are created all the same, and the mark is gone
    #[Test]
    public function aFailedProfileIsNamedAndTheOthersAreCreated(): void
    {
        if (self::$fail === []) self::$fail = $this->getProbe('fail');
        $run = self::$fail['setup'];
        $this->assertSame(303, $run['admin'][0]);
        $this->assertSame(['news', 'pages', 'faq', 'help', 'content', 'links', 'files', 'media', 'docs'], array_column($run['types'], 0));
        $this->assertSame([true, true], $run['notice']);
        $this->assertFalse($run['mark']);
        $this->assertCount(1, $run['starter']);
        $logs = self::$fail['logs'];
        $this->assertSame([[], []], [$logs['error_php'], $logs['error_sql']]);
        $site = array_map(fn($v) => json_decode($v, true), $logs['error_site']);
        $jour = array_filter($site, fn($v) => $v['msg'] === 'Node: a type operation was published');
        $kinds = array_count_values(array_column($jour, 'kind'));
        ksort($kinds);
        $this->assertSame(['add' => 9, 'status' => 9], $kinds, 'Every created type is journaled by its import and its activation');
        $site = array_values(array_diff_key($site, $jour));
        $this->assertCount(1, $site);
        $one = $site[0];
        $this->assertSame(['Node: the installation could not finish a profile', 'jokes', 'import', 'Invalid node input: directory'],
            [$one['msg'], $one['name'], $one['step'], $one['path']]);
    }

    # Every created type exports exactly its profile, with the rules of the site in place of the empty ones
    #[Test]
    public function everyTypeExportsItsProfile(): void
    {
        $run = $this->getRuns()['exports'];
        ksort($run);
        $this->assertSame(array_fill_keys(self::NAMES, [200, true, true, true]), $run);
    }

    # Every type but help: the public list, a published material written through the panel with its roles and fields, its public page, and a draft only the panel reaches
    #[Test]
    public function everyProfileWritesAndServesAMaterial(): void
    {
        $runs = $this->getRuns()['types'];
        $keys = array_keys($runs);
        sort($keys);
        $this->assertSame(array_values(array_diff(self::NAMES, ['help'])), $keys);
        $want = [
            'links' => [['link', 'file', 'https://example.com/probe-links']],
            'files' => [['download', 'file', 'https://example.com/probe.zip']],
            'media' => [['source', 'video', 'https://example.com/probe.mp4']],
        ];
        $fields = ['files' => '{"release":"1.0.2","site":"https://example.com/"}', 'media' => '{"director":"Probe Director","year":2024}'];
        foreach ($runs as $name => $run) {
            $this->assertSame([200, 200, true], $run['list'], $name);
            $this->assertSame([200, 303, true, 2, $name, $fields[$name] ?? '{}', $want[$name] ?? [], ''], $run['write'], $name);
            $this->assertSame([200, true], $run['view'], $name);
            $this->assertSame([303, '', 404, 200], $run['draft'], $name);
        }
        $this->assertSame('', $runs['content']['body']);
    }

    # help stays private: the guest is refused, the owner submits through the public form and reads the request, the stranger does not, and the queue carries it
    #[Test]
    public function helpStaysPrivate(): void
    {
        $run = $this->getRuns()['support'];
        $this->assertSame([403, 200], $run['list']);
        $this->assertSame([200, true, 303], array_slice($run['write'], 0, 3));
        $this->assertSame([2, 1, 2], [$run['write'][3]['status'], $run['write'][3]['uid'], $run['write'][3]['comon']]);
        $this->assertSame(0, $run['write'][4]);
        $this->assertSame([200, 404, 403], $run['read']);
        $this->assertSame([200, true], $run['queue']);
    }

    # content keeps its source, the manual check of the panel reaches the transport of Feed and counts the refusal, and the scheduled job picks a due source up
    #[Test]
    public function contentSyncsByHandAndBySchedule(): void
    {
        $run = $this->getRuns()['sync'];
        $src = ['url' => 'http://127.0.0.1/feed.xml', 'refresh' => '300'];
        $this->assertSame($src + ['fails' => '0', 'error' => ''], $run['stored']);
        $this->assertSame([200, true, 502, $src + ['fails' => '1', 'error' => 'address']], $run['manual']);
        $this->assertSame([200, 303, $src + ['fails' => '2', 'error' => 'address']], $run['planned']);
    }

    # The constructor of the panel offers the ten profiles, and a type made from a profile under a new name keeps its extension settings, roles and fields
    #[Test]
    public function theConstructorOffersEveryProfile(): void
    {
        $run = $this->getRuns()['builder'];
        $this->assertSame([200, self::NAMES], $run['offer']);
        $this->assertSame([200, 303, 'support', 0, ['mail' => true], [], []], $run['desk']);
        $this->assertSame([200, 303, '', 0, null, ['cover', 'download'], ['release', 'site']], $run['soft']);
    }

    # The showcase of news for the guest: a category of the panel with eleven materials, two pages and a refused third, the category list with one page of cards,
    # and a canonical address for the list, its second page, the category and a material
    #[Test]
    public function theGuestPagesThroughCategoriesAndPages(): void
    {
        $run = $this->getRuns()['showcase'];
        [$cid, $nid] = $run['ids'];
        $this->assertSame([303, true, 11], $run['made']);
        $this->assertSame([200, true, 200, 404], $run['pages']);
        $this->assertSame([200, true, 10], $run['cat']);
        $home = (string)preg_replace('#/index\.php.*$#', '', $run['canon'][0]);
        $this->assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $home);
        $want = ['/index.php?name=news', '/index.php?name=news&num=2', '/index.php?name=news&cat='.$cid, '/index.php?name=news&op=view&id='.$nid];
        $this->assertSame(array_map(fn($v) => $home.$v, $want), $run['canon']);
    }

    # The installation writes no PHP or SQL error; the site log holds only the refusals the checks provoke, the failed checks of the local feed address
    # and the journal of the published type changes of the run, twenty of them the import and the activation of the ten profiles
    #[Test]
    public function theInstallationLogsOnlyWhatTheChecksProvoke(): void
    {
        $logs = $this->getRuns()['logs'];
        $this->assertSame([[], []], [$logs['error_php'], $logs['error_sql']]);
        $seen = [];
        foreach ($logs['error_site'] as $line) {
            $one = json_decode($line, true);
            $key = match ($one['msg']) {
                'Node: a feed source failed' => 'feed',
                'Node: a type operation was published' => 'journal',
                default => (string)($one['http_code'] ?? $one['msg']),
            };
            $seen[$key] = ($seen[$key] ?? 0) + 1;
        }
        ksort($seen);
        $this->assertSame(['403' => 2, '404' => 11, 'feed' => 3, 'journal' => 22], $seen);
    }
}
