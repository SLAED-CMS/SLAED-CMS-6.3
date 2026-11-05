<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The request reading of core/security.php, the trusted tags, the info page and the login limit, asserted on the shipped functions lifted by tests/Support/watch_probe.php
final class RequestWatchTest extends TestCase
{
    # The raw answer of each probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static array $probe = [];

    # Run the probe once and memoize its report for every scenario
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_watch_'.getmypid();
        $cmd = escapeshellarg(PHP_BINARY).' -d opcache.enable_cli=0 '.escapeshellarg(dirname(__DIR__).'/Support/watch_probe.php').' '.escapeshellarg($work);
        $out = self::$outs[__FUNCTION__] ??= (string)shell_exec($cmd.' 2>&1');
        $this->deleteWork($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 800));
        $this->assertSame('', $data['error'], 'The probe failed');
        return self::$probe = $data['data'];
    }

    # Remove the work folder of the probe
    private function deleteWork(string $dir): void
    {
        foreach (glob($dir.'/*') ?: [] as $one) {
            if (is_dir($one)) $this->deleteWork($one);
            else unlink($one);
        }
        if (is_dir($dir)) rmdir($dir);
    }

    # Only what no ordinary value carries stops a GET: a climb out of a folder, a script tag, a stream wrapper, in any of their encoded forms
    #[Test]
    public function narrowSignsStopGetRequest(): void
    {
        foreach ($this->getProbe()['stops'] as $seen) $this->assertSame(['stop: Hack in GET - q'], array_slice($seen, 0, 1));
    }

    # A word of a search or a bracket is a signal: it reaches the journal and the request goes on
    #[Test]
    public function wordsOfTextOnlyReachJournal(): void
    {
        foreach ($this->getProbe()['words'] as $seen) {
            $this->assertCount(1, $seen);
            $this->assertStringStartsWith('watch: ', $seen[0]);
        }
    }

    # A word inside a longer one and a plain file name say nothing at all
    #[Test]
    public function plainValuesLeaveNoTrace(): void
    {
        foreach ($this->getProbe()['clean'] as $seen) $this->assertSame([], $seen);
    }

    # A POST never stops: a free text may discuss anything, and the signal stays in the journal
    #[Test]
    public function postNeverStopsTheRequest(): void
    {
        $posts = $this->getProbe()['posts'];
        foreach ($posts as $seen) foreach ($seen as $line) $this->assertStringStartsNotWith('stop: ', $line);
        $this->assertSame(['watch: HTML in POST - q'], $posts[1]);
        $this->assertSame(['watch: SQL in POST - q'], $posts[4]);
        $this->assertSame([], $posts[2]);
    }

    # A pattern that breaks on a long value counts as found, so a filler no longer hides what follows it
    #[Test]
    public function brokenPatternCountsAsFound(): void
    {
        $this->assertSame(['watch: HTML in POST - q'], $this->getProbe()['long']);
    }

    # A member named after a keyword stays quiet: the base64 of a cookie is not read, and a cookie never stops the request
    #[Test]
    public function cookieIsNotDecodedAndNeverStops(): void
    {
        $cookie = $this->getProbe()['cookie'];
        $this->assertSame([], $cookie[0]);
        $this->assertSame([], $cookie[1]);
        $this->assertSame(['watch: HTML in COOKIE - q'], $cookie[2]);
        foreach ($cookie as $seen) foreach ($seen as $line) $this->assertStringStartsNotWith('stop: ', $line);
    }

    # A report names the field and never quotes the value, which may be a password
    #[Test]
    public function reportNeverQuotesTheValue(): void
    {
        $this->assertSame(['watch: HTML in POST - pass'], $this->getProbe()['named']);
    }

    # A nested parameter is read to its leaf
    #[Test]
    public function nestedParameterIsReadToTheLeaf(): void
    {
        $this->assertSame(['stop: Hack in GET - c'], array_slice($this->getProbe()['nest'], 0, 1));
    }

    # Any scheme but http and https empties a web address, and a colon before digits stays a port
    #[Test]
    public function webUrlTakesOnlyHttpSchemes(): void
    {
        $urls = $this->getProbe()['urls'];
        $this->assertSame(['', '', '', ''], array_slice($urls, 0, 4));
        $this->assertSame(['https://ok.example/', 'http://ok.example', 'http://ok.example:8080/x', 'http://localhost:8080'], array_slice($urls, 4));
    }

    # No step of a filter may leave a trusted tag behind it, neither stripslashes nor urldecode nor strip_tags
    #[Test]
    public function noFilterStepRebuildsTrustedTag(): void
    {
        foreach ($this->getProbe()['tags'] as $text) {
            $this->assertStringNotContainsStringIgnoringCase('[usephp]', $text);
            $this->assertStringNotContainsStringIgnoringCase('[/usephp]', $text);
        }
    }

    # Expired bans go, a manual one stays, a new one takes no browser hash
    #[Test]
    public function banListDropsExpiredRows(): void
    {
        $ban = array_values(array_filter($this->getProbe()['ban']));
        $this->assertCount(3, $ban);
        $this->assertStringStartsWith('192.0.2.1/32|0|', $ban[0]);
        $this->assertStringStartsWith('198.51.100.90/32', $ban[1]);
        $this->assertSame('203.0.113.1/32|0|9|hack', $ban[2]);
    }

    # The cap evicts the oldest automatic bans and never a manual one
    #[Test]
    public function banCapKeepsManualRows(): void
    {
        $ban = array_values(array_filter($this->getProbe()['cap']));
        $this->assertCount(1001, $ban);
        $this->assertStringStartsWith('192.0.2.1/32', $ban[0]);
    }

    # A ban of the panel goes through the same list: past the cap of automatic bans the manual one is added after the other manual ones, and nothing expired stays
    #[Test]
    public function manualBanJoinsTheCappedList(): void
    {
        $ban = array_values(array_filter($this->getProbe()['hand']));
        $this->assertCount(1002, $ban);
        $this->assertStringStartsWith('192.0.2.1/32', $ban[0]);
        $this->assertStringStartsWith('203.0.113.2/32|0|', $ban[1]);
        $this->assertStringEndsWith('|manual', $ban[1]);
        $this->assertSame(1000, count(array_filter($ban, static fn(string $v): bool => str_ends_with($v, '|hack'))));
    }

    # One rule of a new password for the site and the panel: both fields, the same text, minpass counted in bytes
    #[Test]
    public function aNewPasswordFollowsOneRule(): void
    {
        $this->assertSame(['nopass', 'nopass', 'differ', 'short: 8', '', ''], $this->getProbe()['rule']);
    }

    # One security mail an hour, the next one saying how many it follows
    #[Test]
    public function securityMailIsHeldWithinTheHour(): void
    {
        $this->assertSame([0, -1, -1, 2], $this->getProbe()['skip']);
    }

    # A post that failed the token check renders nothing: the stored doc runs its tags, the post stays text
    #[Test]
    public function infoPageRunsNoPostWithoutToken(): void
    {
        $info = $this->getProbe()['info'];
        $this->assertSame(['ran' => false, 'file' => false], $info['super_no_token']);
        $this->assertSame(['ran' => false, 'file' => false], $info['admin_no_token']);
    }

    # An administrator of one module reads the doc and writes nothing, as the doc runs PHP
    #[Test]
    public function infoPageIsWrittenBySuperOnly(): void
    {
        $info = $this->getProbe()['info'];
        $this->assertSame(['ran' => false, 'file' => false], $info['admin_token']);
        $this->assertSame(['ran' => true, 'file' => true], $info['super_token']);
    }

    # A write of the site takes the token of a POST body alone, in the scope of its module: a token in the address or of a GET opens nothing
    #[Test]
    public function siteWriteTakesOnlyBodyToken(): void
    {
        $this->assertSame([[false, ''], [false, ''], [true, 'forum']], $this->getProbe()['site']);
    }

    # Every token write of a site module goes through checkSitePost() with its own scope, and its forms render the token of that scope
    #[Test]
    public function siteWritesShareOneTokenCheck(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (glob($root.'/modules/*/index.php') ?: [] as $file) {
            $code = (string)file_get_contents($file);
            $name = basename(dirname($file));
            $this->assertStringNotContainsString("checkSiteToken(getVar('post', 'token'", $code, $name.' checks a body token by hand');
            $this->assertStringNotContainsString('checkForumPost', $code, $name.' keeps a token check of its own');
        }
        $writes = ['forum' => 3, 'contact' => 1, 'recommend' => 1, 'account' => 9, 'node' => 3];
        foreach ($writes as $name => $num) {
            $code = (string)file_get_contents($root.'/modules/'.$name.'/index.php');
            $this->assertSame($num, substr_count($code, "checkSitePost('".$name."')"), $name.' does not check each write in its own scope');
            $this->assertStringNotContainsString("'value_attr' => getSiteToken(), 'input_attr' => ''])", $code, $name.' renders a form token of another scope');
        }
    }

    # A password stored from any of the old escaped forms still opens, and nothing opens with a wrong or an empty one
    #[Test]
    public function everyStoredPasswordFormOpens(): void
    {
        $this->assertSame(['raw' => true, 'login' => true, 'register' => true, 'profile' => true, 'wrong' => '', 'empty' => ''], $this->getProbe()['forms']);
    }

    # A header counts only from a listed proxy, and then only the rightmost address no proxy added: what a client wrote to the left of it is never read
    #[Test]
    public function aForwardedAddressCountsOnlyFromAListedProxy(): void
    {
        $this->assertSame(['no_list' => '203.0.113.5', 'stranger' => '203.0.113.5', 'proxy' => '198.51.100.9', 'forged' => '198.51.100.9', 'chain' => '198.51.100.9',
            'garbage' => '10.0.0.2', 'real' => '198.51.100.9', 'none' => '10.0.0.2', 'ipv6' => '198.51.100.9', 'bad_peer' => '0.0.0.0'], $this->getProbe()['ip']);
    }

    # Five misses lock this address out of one name; another name and another address go on, a login to the own account resets nothing but itself, and 30 misses lock the address
    #[Test]
    public function loginLimitIsPerAddressAndName(): void
    {
        $this->assertSame([false, true, false, false, true, false, true], $this->getProbe()['limit']);
    }
}
