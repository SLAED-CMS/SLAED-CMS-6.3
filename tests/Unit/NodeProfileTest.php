<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The sixteen shipped profiles of modules/node/profiles: slaed.node exports the clean installation of setup.php imports as active types, while an update creates no type
final class NodeProfileTest extends TestCase
{
    # The raw answer of each probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private const NAMES = ['album', 'board', 'content', 'docs', 'events', 'faq', 'files', 'help', 'jobs', 'jokes', 'links', 'media', 'news', 'pages', 'portfolio', 'reviews'];

    # Every shipped profile in the order of its sort, the ten that stand for the modules of 6.2 first
    private const SORTED = ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'docs', 'album', 'portfolio', 'board', 'reviews', 'events', 'jobs'];

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

    # The installation is driven by tests/Support/install_probe.php over real HTTP on a copy of the release with a disposable MariaDB database
    # The probe covers the installer, the panel, the public pages, private support and the external source of content
    # Run the installation probe in one mode and answer its report; a probe that cannot create its database is a failure, not a skip
    private function getProbe(string $mode): array
    {
        $script = dirname(__DIR__).'/Support/install_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_install';
        $out = self::$outs[__FUNCTION__.$mode] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' '
            .escapeshellarg($mode).' 2>&1');
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

    # The shipped files are in the canonical export form: the exact envelope and keys, the name of the file, the canonical JSON of the export and empty rules
    # The settings follow docs/NODE.md (Types), and a create fills the empty upload and rating rules with the rules of the site
    #[Test]
    public function theProfilesShipInTheExportFormat(): void
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
        $sorts = array_map(fn($v) => self::getProfile($v)['type']['sort'], self::SORTED);
        $this->assertSame(range(10, 160, 10), $sorts);
    }

    # Each profile carries the settings docs/NODE.md (Shipped profiles) approves: display mode, extension, SEO, the switches that differ, the roles and the fields
    #[Test]
    public function eachProfileCarriesItsApprovedSettings(): void
    {
        $want = [
            'news' => ['article', '', 'news'], 'pages' => ['article', '', 'article'], 'faq' => ['faq', '', 'article'], 'help' => ['support', 'support', 'website'],
            'jokes' => ['default', '', 'article'], 'content' => ['article', 'sync', 'article'], 'links' => ['default', '', 'website'], 'files' => ['files', '', 'article'],
            'media' => ['media', '', 'article'], 'docs' => ['docs', '', 'article'], 'album' => ['default', '', 'article'], 'portfolio' => ['article', '', 'article'],
            'board' => ['default', '', 'article'], 'reviews' => ['default', '', 'article'], 'events' => ['event', 'event', 'article'], 'jobs' => ['default', '', 'article'],
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
            'album' => ['cover' => $cover, 'gallery' => ['gallery', ['image'], 0, 100, false, false, 20]],
            'portfolio' => ['cover' => $cover, 'gallery' => ['gallery', ['image'], 0, 30, false, false, 20]],
            'board' => ['cover' => $cover, 'gallery' => ['gallery', ['image'], 0, 10, false, false, 20]],
            'events' => ['cover' => $cover, 'gallery' => ['gallery', ['image'], 0, 20, false, false, 20]], 'jobs' => ['cover' => $cover],
        ];
        $on = [
            'news' => ['categories', 'comments', 'rating', 'favorites', 'poll', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'pages' => ['categories', 'comments', 'rating', 'favorites', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'content' => ['categories', 'comments', 'rating', 'favorites', 'pinned', 'schedule', 'related'],
            'files' => ['categories', 'comments', 'rating', 'favorites', 'home', 'pinned', 'submit', 'moderation', 'schedule', 'related'],
            'docs' => ['categories', 'favorites', 'related', 'tree'],
            'help' => ['categories', 'comments', 'submit'],
            'portfolio' => ['categories', 'favorites', 'home', 'pinned', 'schedule', 'related'],
            'board' => ['categories', 'comments', 'favorites', 'pinned', 'submit', 'moderation', 'schedule'],
            'reviews' => ['home', 'pinned', 'submit', 'moderation'],
            'events' => ['categories', 'comments', 'favorites', 'home', 'pinned', 'submit', 'moderation'],
        ];
        $on += ['faq' => $on['pages'], 'jokes' => $on['pages'], 'links' => $on['files'], 'media' => $on['files'], 'album' => $on['pages'], 'jobs' => $on['board']];
        foreach ($want as $name => [$mode, $ext, $seo]) {
            $type = self::getProfile($name)['type'];
            $set = $type['settings'];
            $this->assertSame([$mode, $ext, $seo], [$set['view']['mode'], $type['ext'], $set['integrations']['seo']], $name);
            $this->assertSame($on[$name], array_keys(array_filter($set['features'])), $name);
            $this->assertSame($roles[$name] ?? [], self::getRoles(['type' => $type]), $name);
        }
        $this->assertSame(['mail' => true], self::getProfile('help')['type']['settings']['ext']);
        $this->assertSame(['pending' => false, 'result' => false], self::getProfile('help')['type']['settings']['workflow']['notify']);
        $this->assertSame(['orders' => ['title', 'updated', 'views'], 'order' => 'title', 'dir' => 'asc', 'limit' => 50, 'alpha' => true, 'show' => ['category', 'date', 'views'],
            'views' => ['list', 'rail']], self::getProfile('docs')['type']['settings']['list']);
        $this->assertSame([25, true], [self::getProfile('files')['type']['settings']['list']['limit'], self::getProfile('files')['type']['settings']['list']['alpha']]);
        $this->assertSame(['release' => ['text', 100], 'site' => ['url', null]], array_map(fn($v) => [$v['type'], $v['options']['max'] ?? null],
            self::getProfile('files')['type']['fields']));
        $media = self::getProfile('media')['type']['fields'];
        $this->assertSame(['subtitle', 'year', 'director', 'cast', 'creator', 'runtime', 'language', 'notes', 'format', 'quality', 'filesize', 'release'], array_keys($media));
        $this->assertSame(['int', 1, 9999, null], [$media['year']['type'], $media['year']['options']['min'], $media['year']['options']['max'], $media['year']['default']]);
        $this->assertSame(['textarea', 262144], [$media['notes']['type'], $media['notes']['options']['max']]);
        foreach ($media as $one) $this->assertSame([false, false, true], [$one['req'], $one['multi'], $one['active']]);
        $kinds = fn(string $name): array => array_map(fn($v) => [$v['type'], $v['options'], $v['req']], self::getProfile($name)['type']['fields']);
        $this->assertSame(['client' => ['text', ['max' => 255], false], 'year' => ['int', ['min' => 1, 'max' => 9999], false], 'site' => ['url', [], false],
            'services' => ['text', ['max' => 255], false]], $kinds('portfolio'));
        $this->assertSame(['price' => ['decimal', ['min' => '0.00', 'scale' => 2], false], 'city' => ['text', ['max' => 100], false], 'phone' => ['text', ['max' => 50], false],
            'email' => ['email', [], false]], $kinds('board'));
        $this->assertSame(['stars' => ['int', ['min' => 1, 'max' => 5], true]], $kinds('reviews'));
        $items = ['full' => ['_NODE_FULLTIME', true, 10], 'part' => ['_NODE_PARTTIME', true, 20], 'remote' => ['_NODE_REMOTE', true, 30]];
        $jobs = $kinds('jobs');
        $this->assertSame($items, array_map(fn($v) => array_values($v), $jobs['employment'][1]['items']));
        $jobs['employment'][1] = array_keys($jobs['employment'][1]['items']);
        $this->assertSame(['salary' => ['text', ['max' => 100], false], 'city' => ['text', ['max' => 100], false], 'employment' => ['select', ['full', 'part', 'remote'], false],
            'email' => ['email', [], false], 'site' => ['url', [], false]], $jobs);
        $this->assertSame(['orders' => ['published'], 'order' => 'published', 'dir' => 'desc', 'limit' => 20, 'alpha' => false, 'show' => ['category', 'author', 'views'],
            'views' => []], self::getProfile('events')['type']['settings']['list'], 'The extension event orders the list of events');
        $life = array_map(fn($v) => self::getProfile($v)['type']['settings']['workflow']['lifetime'], array_combine(self::NAMES, self::NAMES));
        $this->assertSame(['board' => 30, 'jobs' => 30], array_filter($life), 'Only an ad and a vacancy leave the site after a lifetime');
        $this->assertSame('all', self::getProfile('reviews')['type']['settings']['workflow']['access'], 'A guest cannot leave a review');
        $bare = array_diff(self::NAMES, ['files', 'media', 'portfolio', 'board', 'reviews', 'jobs']);
        foreach ($bare as $name) $this->assertSame([], self::getProfile($name)['type']['fields'], $name);
    }

    # Every label constant of the profiles - type titles, role titles and field titles - is defined in the site language of all six locales
    # The panel checks every such label while it creates a type, and the site shows it
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

    # The clean installation through the seven stops of setup.php: the release carries no config/db.php, the first page opens and writes the one-time token
    # The run goes part by part, and the marks of config/update.php appear only after the last statement of insert.sql, before the administrator part creates any type
    # The closing stop names the panel, deletes setup.php and the token, and the panel opens in the language of the first stop
    # The first part drops the types the shipped configuration and an earlier installation left in the tree, with their four areas
    # The administrator part creates the administrator with a site account of the same password and turns every profile into an active type through the one import path
    # The sixteen types carry every shared area and directory and the starter news, the mark is gone, and the recovery form of the panel creates nothing more
    # Every type, the starter news, the site account and the seed of node.sql and insert.sql carry their first path, the seed in the order of the registry
    #[Test]
    #[Group('release')]
    public function aCleanInstallationCreatesTheShippedTypes(): void
    {
        $run = $this->getRuns()['setup'];
        $home = $run['guard'];
        $this->assertSame([true, 200, 0, true], $run['first'], 'The release carries no config/db.php: the first stop opens without it and hands out the token');
        $this->assertSame([4, 5, 200, 6, $home, 'ru', 'probe'], $run['setup']);
        $parts = $run['parts'];
        $num = count($parts);
        $this->assertGreaterThan(3, $num);
        $this->assertSame(array_merge(array_fill(0, $num - 1, true), [false]), array_column($parts, 1), 'Every part but the last announces another');
        $this->assertSame(100, $parts[$num - 1][0]);
        $this->assertSame(array_merge(array_fill(0, $num - 2, false), [true, false]), array_column($parts, 2), 'The marks came before both SQL files ran');
        $this->assertSame([0, 16], [$parts[$num - 2][3], $parts[$num - 1][3]], 'A type was created before the administrator part');
        $this->assertSame([true, false, true], $run['done'], 'The closing stop did not name the panel, delete the installer, or set the language');
        $this->assertSame([['Probe', 'probe@probe.test', 'ru', $home, 'Probe', 1], true], $run['account']);
        $want = [];
        foreach (self::SORTED as $i => $name) {
            $want[] = [$name, '_'.strtoupper($name), ['help' => 'support', 'content' => 'sync', 'events' => 'event'][$name] ?? '', 1, ($i + 1) * 10, 2];
        }
        $this->assertSame($want, $run['types']);
        $this->assertSame(array_fill_keys(self::NAMES, 2), $run['node']);
        $order = array_column($want, 0);
        $this->assertSame(['uploads' => $order, 'ratings' => $order, 'fields' => ['board', 'files', 'jobs', 'media', 'portfolio', 'reviews']], $run['areas']);
        $this->assertSame(['content', 'docs', 'faq', 'files', 'help', 'links', 'news', 'stale'], $run['ghost']['names'], 'The shipped types changed');
        $this->assertSame([], $run['ghost']['left'], 'Types of the tree survived the first part of the run');
        $this->assertSame([false, false, false, false], $run['stale'], 'A type of an earlier installation came back with the profiles');
        $this->assertSame($order, $run['dirs'], 'A type does not carry its folder in uploads/node/<type>, or the folder carries a guard');
        $this->assertFalse($run['mark']);
        require_once BASE_DIR.'/core/classes/update.php';
        require_once BASE_DIR.'/core/version.php';
        $this->assertSame(['upcheck' => '1', 'channel' => 'stable', 'keepbak' => '1', 'upsite' => '1'], $run['updater']['check'], 'The installer left no settings of the check');
        $this->assertSame([SL_VERSION], $run['updater']['steps'], 'The installer did not mark the steps of its version');
        $this->assertMatchesRegularExpression('#^[0-9a-f]{32}$#D', $run['updater']['sid'], 'The installer left no id of the installation');
        $this->assertTrue($run['updater']['home'], 'The id does not belong to the address of the installation');
        $this->assertSame(\Update::getCheckTime($run['updater']['sid']), $run['updater']['line'], 'The daily minute of updatecheck does not follow the id');
        $news = [1 => 'Безопасность и обновления: что проверить в первый день', 3 => 'Разделы сайта: новости, статьи, файлы и другие',
            2 => 'Первые шаги в панели администратора'];
        $want = [];
        foreach ($news + [0 => 'Добро пожаловать в SLAED CMS'] as $cid => $title) {
            $want[] = ['name' => 'news', 'cid' => $cid ?: 2, 'uid' => 0, 'aname' => 'SLAED', 'title' => $title, 'status' => 2, 'home' => 1, 'comon' => 2];
        }
        $this->assertSame($want, $run['starter'], 'The four starter news are not in the rubrics node.sql seeds');
        $want = [['user', 'users/probe']];
        foreach (self::NAMES as $name) $want[] = ['type', $name];
        $seed = ['node' => ['news' => ['bezopasnost-i-obnovleniya-chto-proverit-v-pervyi-den', 'razdely-saita-novosti-stati-faily-i-drugie',
                'pervye-shagi-v-paneli-administratora', 'dobro-pozhalovat-v-slaed-cms']],
            'cat' => ['docs' => ['ustroistvo', 'informatsiya', 'panel-administratora', 'temy', 'bloki', 'moduli'],
                'faq' => ['nash-proekt', 'obshchie-svedeniya', 'chastye-oshibki', 'vnedreniya-i-izmeneniya'],
                'files' => ['moduli', 'bloki', 'temy-i-grafika', 'programmy', 'skripty', 'yazyki', 'slaed-cms'], 'news' => ['internet', 'nashi-novosti', 'programmy'],
                'forum' => ['sistema-postroeniya-saitov', 'vebmaster-i-vebdizain', 'koordinatsiya-proekta', 'ustanovka-nastroika-i-ispolzovanie', 'moduli', 'temy-oformleniya',
                    'soobshcheniya-o-naidennykh-oshibkakh', 'seo-indeksatsiya-i-prodvizhenie', 'idei-i-predlozheniya-dlya-razrabotki', 'svobodnyi-otdel',
                    'ustarevshie-soobshcheniya']],
            'topic' => ['forum' => ['s-chego-nachat-posle-ustanovki', 'kak-smenit-logotip-menyu-i-podval', 'predlagaite-idei-dlya-razvitiya-saita']]];
        foreach ($seed as $kind => $areas) foreach ($areas as $area => $list) foreach ($list as $name) $want[] = [$kind, $area.'/'.$name];
        $this->assertSame($want, array_map(fn(array $v): array => [$v[0], $v[1]], $run['routes']), 'The installation did not give its seed, types and accounts their paths');
        $this->assertSame(array_fill(0, count($want), 1), array_map('intval', array_column($run['routes'], 2)));
        $this->assertSame([303, 1, 16], $run['again']);
        $this->assertSame([2, false], $run['notice']);
    }

    # The installed site keeps setup.php shut: a copy put back answers a visit and a part of the run with the refusal, shows no form and no token and stays in place
    # A config/db.php whose database does not answer counts as installed too; nothing is written, and only that refused connection is logged
    #[Test]
    #[Group('release')]
    public function theInstalledSiteKeepsTheInstallerShut(): void
    {
        $run = $this->getRuns()['setup']['lock'];
        foreach (['visit', 'part', 'down'] as $kind) $this->assertSame([true, -1, false, true], $run[$kind], $kind);
        $this->assertTrue($run['same'], 'The shut installer changed config/ or the panel');
        $this->assertSame(['error_php' => 0, 'error_sql' => 1, 'error_site' => 0], $run['logs']);
    }

    # The installer over real HTTP refuses on the stop the answer came from and writes nothing: a POST without the token of its browser, a part without a run
    # The database stop refuses a wrong password with its reason and never prints a password, refuses a taken prefix and one outside its grammar, and names a free one
    # The site stop refuses an empty name, an address that is no http or https home, and a panel named outside its grammar or after another file of the root
    # The administrator stop refuses what the panel refuses; Install refuses a pending journal and a config/security.php PHP cannot write, before the database is asked
    # A run whose data file fails hands the form back with the reason and writes no mark; the permissions of the files it rewrote stay, and only the wrong password is logged
    #[Test]
    #[Group('release')]
    public function theInstallerRefusesBeforeItWrites(): void
    {
        $run = $this->getRuns()['setup']['refuse'];
        $this->assertSame([true, true, '{"more":false}', 1, true], $run['token'],
            'A POST without its own token or with the token of another browser passed, or a stop past the reach was taken');
        $this->assertSame(2, $run['server'], 'The server checks of the probe failed');
        $this->assertSame([2, true, false, false], $run['wrong'], 'A wrong password ends in a fatal error, without the reason, or prints the password');
        $this->assertSame([2, true], $run['taken']);
        $this->assertSame([2, true], $run['probe'], 'The probe of a free prefix did not name it free');
        $this->assertSame([2, true], $run['prefix']);
        $this->assertSame([3, 3, 3, 3], $run['panel'], 'The panel may be named outside its grammar or after another file of the document root');
        $this->assertSame([3, 3, 3, 3, 3], $run['site'], 'An empty site name or an address that is no home of the site passed');
        $this->assertSame([[4, 4, 4, 4, 4, 4], true], $run['admin'], 'The administrator stop took what the panel refuses, or printed the password back');
        $this->assertSame([4, true], $run['jour'], 'The installer started while a configuration operation of the site was unfinished');
        $this->assertSame([4, true], $run['write'], 'The installer started while config/security.php was not writable');
        $this->assertTrue($run['same'], 'A refused stop changed config/ or the panel');
        $this->assertSame([5, false, 4, true, false, true], $run['ddl'], 'A failed run wrote its marks or did not name the failed table');
        $this->assertTrue($run['perm'][0], 'The installer changed the permissions of config/global.php or config/security.php');
        $this->assertSame(['error_php' => 0, 'error_sql' => 1, 'error_site' => 0], $run['logs'], 'Only the refused connection is logged, in the journal of the database');
    }

    # A profile the installation cannot finish - here a user file in uploads/node/jokes it refuses to take over - is named on the closing stop and in the site log with its step
    # The other fifteen types and the four starter news are created all the same, and the mark is gone
    #[Test]
    #[Group('release')]
    public function aFailedProfileIsNamedAndTheOthersAreCreated(): void
    {
        if (self::$fail === []) self::$fail = $this->getProbe('fail');
        $run = self::$fail['setup'];
        $this->assertSame([4, 5, 200, 6], array_slice($run['setup'], 0, 4));
        $this->assertSame(array_values(array_diff(self::SORTED, ['jokes'])), array_column($run['types'], 0));
        $this->assertSame([3, true], $run['notice']);
        $this->assertFalse($run['mark']);
        $this->assertCount(4, $run['starter']);
        $logs = self::$fail['logs'];
        $this->assertSame([[], []], [$logs['error_php'], $logs['error_sql']]);
        $site = array_map(fn($v) => json_decode($v, true), $logs['error_site']);
        $jour = array_filter($site, fn($v) => $v['msg'] === 'Node: a type operation was published');
        $kinds = array_count_values(array_column($jour, 'kind'));
        ksort($kinds);
        $this->assertSame(['add' => 15, 'status' => 15], $kinds, 'Every created type is journaled by its import and its activation');
        $site = array_values(array_diff_key($site, $jour));
        $this->assertCount(1, $site);
        $one = $site[0];
        $this->assertSame(['Node: the installation could not finish a profile', 'jokes', 'import', 'Invalid node input: directory'],
            [$one['msg'], $one['name'], $one['step'], $one['path']]);
    }

    # Every created type exports exactly its profile, with the rules of the site in place of the empty ones
    #[Test]
    #[Group('release')]
    public function everyTypeExportsItsProfile(): void
    {
        $run = $this->getRuns()['exports'];
        ksort($run);
        $this->assertSame(array_fill_keys(self::NAMES, [200, true, true, true]), $run);
    }

    # Every type but help: the public list, a published material written through the panel with its roles and fields, its public page, and a draft only the panel reaches
    #[Test]
    #[Group('release')]
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
        $fields = ['files' => '{"release":"1.0.2","site":"https://example.com/"}', 'media' => '{"director":"Probe Director","year":2024}',
            'portfolio' => '{"client":"Probe Client","year":2024}', 'board' => '{"city":"Berlin","price":"1500.00"}', 'reviews' => '{"stars":4}',
            'jobs' => '{"city":"Berlin","employment":"full","salary":"3000 EUR"}'];
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
    #[Group('release')]
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
    #[Group('release')]
    public function contentSyncsByHandAndBySchedule(): void
    {
        $run = $this->getRuns()['sync'];
        $src = ['url' => 'http://127.0.0.1/feed.xml', 'refresh' => '300'];
        $this->assertSame($src + ['fails' => '0', 'error' => ''], $run['stored']);
        $this->assertSame([200, true, 502, $src + ['fails' => '1', 'error' => 'address']], $run['manual']);
        $this->assertSame([200, 303, $src + ['fails' => '2', 'error' => 'address']], $run['planned']);
    }

    # The constructor of the panel offers every shipped profile, and a type made from a profile under a new name keeps its extension settings, roles and fields
    #[Test]
    #[Group('release')]
    public function theConstructorOffersEveryProfile(): void
    {
        $run = $this->getRuns()['builder'];
        $this->assertSame([200, self::NAMES], $run['offer']);
        $this->assertSame([200, 303, 'support', 0, ['mail' => true], [], []], $run['desk']);
        $this->assertSame([200, 303, '', 0, null, ['cover', 'download'], ['release', 'site']], $run['soft']);
    }

    # The showcase of news for the guest: a category of the panel with eleven materials, two pages and a refused third, the category list with one page of cards
    # The list, its second page, the category and a material each carry a canonical address
    #[Test]
    #[Group('release')]
    public function theGuestPagesThroughCategoriesAndPages(): void
    {
        $run = $this->getRuns()['showcase'];
        [$cid, $nid] = $run['ids'];
        $this->assertSame([303, true, 11], $run['made']);
        $this->assertSame([200, true, 200, 404], $run['pages']);
        $this->assertSame([200, true, 10], $run['cat']);
        $home = (string)preg_replace('#/news\?page=2$#', '', $run['canon'][1]);
        $this->assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+$#', $home);
        $want = ['/', '/news?page=2', '/news/probe-category', '/news/showcase-1'];
        $this->assertSame(array_map(fn($v) => $home.$v, $want), $run['canon']);
    }

    # The installation writes no PHP or SQL error; the site log holds only the refusals the checks provoke and the failed checks of the local feed address
    # The site log also holds the journal of the published type changes of the run, thirty-two of them the import and the activation of the sixteen profiles
    #[Test]
    #[Group('release')]
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
        $this->assertSame(['403' => 2, '404' => 17, 'feed' => 3, 'journal' => 34], $seen);
    }
}
