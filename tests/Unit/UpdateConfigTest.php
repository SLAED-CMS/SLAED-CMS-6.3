<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The configuration step of the 6.3 update in update.php carries the settings of a 6.2 site over the sources the release ships
final class UpdateConfigTest extends TestCase
{
    private static array $probe = [];

    # A 6.2 site keeps each setting in config/config_<name>.php as a variable of its own; every old source leaves config/, because the runtime includes each file there
    # The probe tests/Support/update_probe.php lifts the functions out of update.php by name and drives them on a scratch site, never the stand configuration
    # Run the probe once in its configuration mode and memoize the report for every test in this class
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/update_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_update_config';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' config 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.$out);
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left its schema on the server');
            self::$probe = $data['runs']['clean'];
        }
        return self::$probe;
    }

    # The values of the site go over the shipped source, the release keeps its version and asset list, the site stays closed, a language name becomes its code
    # The start module of the panel is a setting the release dropped, so the one the site stored never reaches the carried source
    # A start module or a theme that left the tree falls back to the shipped value, a logo the theme holds stays, and seo wins over the global of 6.2
    # The 6.2 key forum names the area of config/forum.php, which global.php would shadow in the merged configuration, so it is not carried
    #[Test]
    public function theSiteValuesGoOverTheRelease(): void
    {
        $run = $this->getRun()['first'];
        $glob = $run['global'];
        $this->assertTrue($run['done'], $run['text']);
        $this->assertFalse($run['leak'], 'The output of an old source reached the page');
        $this->assertSame(['Old site', '1', 'ru', 'forum', '-', '_', 'kept'], [$glob['sitename'], $glob['close'], $glob['language'], $glob['module'], $glob['sep'], $glob['tsep'],
            $glob['oldkey']]);
        $this->assertSame([$run['ship']['version'], $run['ship']['css_f'], $run['ship']['amod']], [$glob['version'], $glob['css_f'], $glob['amod'] ?? null]);
        $this->assertSame([$run['ship']['theme'], 'mark.svg'], [$glob['theme'], $glob['site_logo']], 'A theme that left the tree was kept or a logo of the theme was dropped');
        $this->assertArrayNotHasKey('forum', $glob, 'A 6.2 key that names a configuration area reached global.php and shadows that area');
        $this->assertSame(['de', 'k1'], [$run['lang']['lang'], $run['lang']['key']]);
        $want = ['stat' => '0'] + $run['ship']['statistic'];
        ksort($want);
        $this->assertSame($want, $run['statistic']);
    }

    # Keys the release does not ship stay for the data units, every shipped key is there, and the extra fields of the site replace the shipped definitions whole
    #[Test]
    public function theDataUnitsFindTheOldKeys(): void
    {
        $run = $this->getRun()['first'];
        $this->assertSame(['1', '1,2,3', '77'], [$run['users']['point'], $run['users']['points'], $run['users']['anum']]);
        $this->assertSame([], array_diff($run['ship']['users'], array_keys($run['users'])), 'A shipped key of users is gone');
        $this->assertSame(['account' => 'Kind|A,B|3|1'], $run['fields']);
    }

    # Every old source leaves config/, the ones without a successor too, and the answer names what was carried and what was not
    #[Test]
    public function noOldSourceStaysInConfig(): void
    {
        $run = $this->getRun()['first'];
        $this->assertSame([], $run['old']);
        $names = ['core', 'db', 'fields', 'global', 'header', 'lang', 'news', 'security', 'seo', 'stat', 'templ', 'uploads', 'users'];
        $this->assertSame(array_map(fn($v) => 'config_'.$v.'.php', $names), $run['back']);
        $this->assertStringContainsString('carried into fields, global, lang, security, statistic, uploads, users; not carried: core, db, header, news, templ', $run['text']);
    }

    # The two positional formats that changed after 6.2: an upload rule loses its retired eighth field and gets the guest limit at the user one
    # An address ban turns ip and octet count into CIDR and one that is no 6.2 address ban is dropped and named, a rule without twelve fields and member bans stay
    #[Test]
    public function theChangedFormatsAreRewritten(): void
    {
        $run = $this->getRun()['first'];
        $this->assertSame(['gif,png|104857600|1048576|500|500|10|250|200|100|1|0|100', 'gif,png'], [$run['uploads']['forum'], $run['uploads']['typ']]);
        $this->assertSame(['blocker_ip' => '10.1.2.0/24|abc|1784035343|Hack||10.9.9.9/32|0|1784035344|Spam||', 'blocker_user' => 'bob|1784035343|Spam||'], $run['bans']);
        $this->assertStringContainsString('the ban entry ::1 is not a 6.2 address ban and was dropped', $run['text']);
    }

    # A repeat has nothing left to carry and changes no file, and a source met again after a break gives the same result
    #[Test]
    public function aRepeatChangesNothing(): void
    {
        $data = $this->getRun();
        $this->assertSame('', $data['again']['text']);
        $this->assertSame($data['first']['hash'], $data['again']['hash']);
        $this->assertTrue($data['broken']['done'], $data['broken']['text']);
        $this->assertSame($data['first']['hash'], $data['broken']['hash'], 'Carrying a source again after a break changed the result');
        $this->assertSame([], $data['broken']['old']);
    }

    # The first stage of the update reads the connection settings of a 6.2 db.php, so it runs on a 6.2 site without a form
    #[Test]
    public function theOldConnectionSettingsAreRead(): void
    {
        $this->assertSame(['host' => 'h', 'name' => 'olddb', 'prefix' => 'sport', 'mode' => '1'], $this->getRun()['base']);
    }
}
