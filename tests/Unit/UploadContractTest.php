<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batches 3 and 4 of docs/UPLOAD-2026.md: the local and the remote half of the Upload class.
 * tests/Support/upload_probe.php boots the real core with its writable directories in scratch and drives
 * the class against a disposable upload root, one scenario per process. Everything that needs an HTTP
 * upload, a controlled clock or a refusing filesystem goes through the protected seams of the test-only
 * subclass declared in that probe; the type map is verified against the libmagic build of this machine.
 * The remote scenarios answer the DNS and cURL seams from a scripted zone and a scripted reply chain,
 * so every rejected address range and every redirect is reachable without a single socket being opened.
 */
final class UploadContractTest extends TestCase
{
    private static array $probe = [];
    private const CANON = ['7z', 'avif', 'flac', 'gif', 'gz', 'jpeg', 'jpg', 'm4a', 'mp3', 'mp4', 'oga', 'ogg', 'opus', 'pdf', 'png', 'rar', 'tar', 'wav', 'webm', 'webp', 'zip'];

    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $file = dirname(__DIR__).'/Support/upload_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_upload_probe_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # Read one source file of the project
    private function getFile(string $name): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$name);
    }

    # Assert the failure shape: a refused upload carries its code and nothing else, and leaves no partial behind
    private function checkFailShape(array $run, string $code, string $note): void
    {
        $this->assertFalse($run['ok'], $note.' reported success');
        $this->assertSame($code, $run['error'], $note.' returned the wrong code');
        $this->assertNull($run['file'], $note.' returned a file name');
        $this->assertNull($run['path'], $note.' returned a path');
        $this->assertSame(0, $run['size'], $note.' returned a size');
        $this->assertNull($run['mime'], $note.' returned a MIME type');
        if (isset($run['parts'])) $this->assertSame([], $run['parts'], $note.' left a partial behind');
    }

    # The type policy is the canonical 21-format set and nothing else, because it is what the admin settings validate against
    #[Test]
    public function theSupportedTypeListIsTheCanonicalSet(): void
    {
        $list = $this->getProbe('types')['list'];
        sort($list);
        $this->assertSame(self::CANON, $list, 'Upload::getSupportedTypes() is no longer the canonical set');
    }

    # One fixture per mapped extension: a real file of that format has to be detected as one of its mapped MIME types on this libmagic build, or it would be unusable in practice
    #[Test]
    public function everyMappedExtensionUploadsWithADetectedMime(): void
    {
        $data = $this->getProbe('types');
        $this->assertSame(self::CANON, array_values(array_unique(self::CANON)), 'The canonical list of this test holds a duplicate');
        foreach ($data['runs'] as $ext => $run) {
            if (!$run['made']) {
                $this->assertArrayHasKey($ext, $data['caps'], 'No '.$ext.' fixture could be built and the format is not an optional image format');
                $this->assertFalse($data['caps'][$ext], 'This build can encode '.$ext.' but no fixture was produced');
                continue;
            }
            $this->assertTrue($run['ok'], 'A valid '.$ext.' upload was refused with '.var_export($run['error'], true));
            $this->assertTrue($run['stored'], 'The '.$ext.' upload did not leave exactly one file in the destination');
            $note = 'This libmagic build reports '.$run['mime'].' for '.$ext;
            $this->assertContains($run['mime'], $data['map'][$ext], $note.', which the type map does not accept');
        }
    }

    # Every local result code is reachable, and every one of them refuses the upload without leaving anything behind
    #[Test]
    public function everyLocalResultCodeIsReachable(): void
    {
        $data = $this->getProbe('codes');
        $want = [
            'missing' => 'missing', 'empty' => 'missing', 'nofile' => 'transfer', 'inisize' => 'size', 'notupload' => 'transfer',
            'size' => 'size', 'extension' => 'extension', 'blocked' => 'extension', 'unsupported' => 'unsupported', 'mime' => 'mime',
            'image' => 'image', 'dimensions' => 'dimensions', 'quota' => 'quota', 'destination' => 'destination', 'exists' => 'exists', 'write' => 'write',
        ];
        foreach ($want as $key => $code) {
            $this->checkFailShape($data[$key], $code, 'The '.$key.' case');
        }
        $this->assertTrue($data['brokenhdr']['header'], 'The broken image fixture no longer passes a header read, so it cannot prove the decode runs');
        $this->assertFalse($data['brokenhdr']['decode'], 'The broken image fixture still decodes, so it is not broken at all');
        $this->assertSame([], $data['extension']['left'], 'A refused extension published a file');
        $this->assertSame(['stored.png'], $data['quota']['left'], 'The quota rejection changed the destination');
        $this->assertSame(['files-aaaaaaaaaa.png'], $data['exists']['left'], 'The retry exhaustion published a file');
        $this->assertSame([], $data['write']['left'], 'A failed rename published a file');
    }

    # A configured but web-active extension is refused whatever the configuration says, and an extension without a type policy fails closed
    #[Test]
    public function configurationCannotEnableAnUnsafeOrUnknownFormat(): void
    {
        $data = $this->getProbe('codes');
        $this->assertSame('extension', $data['blocked']['error'], 'A configured php extension was not refused by the class');
        $this->assertSame('unsupported', $data['unsupported']['error'], 'A configured extension without a type policy did not fail closed');
        $this->assertNotContains('bmp', $this->getProbe('types')['list'], 'bmp is not part of the canonical set and must have no type policy');
    }

    # A successful publication returns the metadata the adapters use instead of probing the filesystem themselves
    #[Test]
    public function aSuccessfulResultCarriesItsOwnMetadata(): void
    {
        $run = $this->getProbe('codes')['done'];
        $this->assertTrue($run['ok'], 'A valid upload was refused with '.var_export($run['error'], true));
        $this->assertNull($run['error'], 'A successful result carries an error');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-7\.png$#', (string)$run['file'], 'The stored name does not follow the canonical grammar');
        $this->assertSame('files/'.$run['file'], $run['path'], 'The returned path is not the root-relative path of the stored file');
        $this->assertSame('image/png', $run['mime']);
        $this->assertSame(40, $run['width']);
        $this->assertSame(30, $run['height']);
        $this->assertGreaterThan(0, $run['size']);
        $this->assertSame([$run['file']], $run['left'], 'The destination holds something other than the published file');
    }

    # The stored name grammar: the owner suffix, the sanitized base and a base that sanitizes to nothing
    # The owner is a token and not a number: a guest owns their uploads by a session token, and a digit string stays valid so a file stored before the widening keeps resolving
    #[Test]
    public function theStoredNameFollowsTheOwnerGrammar(): void
    {
        $data = $this->getProbe('naming');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}\.png$#', (string)$data['none']['file'], 'A privileged upload must carry no owner suffix');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-0\.png$#', (string)$data['guest']['file'], 'A zero owner must carry the zero owner suffix');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-42\.png$#', (string)$data['user']['file'], 'An owned upload must carry its user suffix');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-a1b2c3d4e5f6a7b8\.png$#', (string)$data['token']['file'], 'A session token must survive whole');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-abc9\.png$#', (string)$data['dirty']['file'], 'The owner was not reduced to the allowed character set');
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}\.png$#', (string)$data['empty']['file'], 'An owner that reduces to nothing must carry no owner suffix');
        $this->assertMatchesRegularExpression('#^myfiles2-[a-zA-Z0-9]{10}-0\.png$#', (string)$data['clean']['file'], 'The base was not reduced to the allowed character set');
        $this->checkFailShape($data['nobase'], 'destination', 'A base that sanitizes to nothing');
    }

    # A name collision redraws the random segment under the lock, and five collisions in a row end as exists rather than as an overwrite
    #[Test]
    public function aCollisionRedrawsTheNameAndExhaustionReportsExists(): void
    {
        $retry = $this->getProbe('naming')['retry'];
        $this->assertTrue($retry['ok'], 'A single collision must be retried, not reported');
        $this->assertSame('files-bbbbbbbbbb-0.png', $retry['file'], 'The retry did not draw the next name');
        $this->assertSame(['files-aaaaaaaaaa-0.png', 'files-bbbbbbbbbb-0.png'], $retry['left'], 'The colliding file was replaced instead of kept');
        $exists = $this->getProbe('codes')['exists'];
        $this->checkFailShape($exists, 'exists', 'Five collisions in a row');
    }

    # A publication either completes or leaves nothing: a rename that fails publishes no file and removes the partial
    #[Test]
    public function aFailedRenamePublishesNothingAndRemovesThePartial(): void
    {
        $data = $this->getProbe('publish');
        $this->assertTrue($data['done']['ok'], 'The reference publication failed with '.var_export($data['done']['error'], true));
        $this->assertSame($data['done']['size'], $data['done']['bytes'], 'The reported size is not the size of the published file');
        $this->checkFailShape($data['norename'], 'write', 'A failed rename');
        $this->assertSame([], $data['norename']['left'], 'A failed rename left a file behind');
    }

    # When the partial cannot be deleted either, the caller still gets write and the stranded path becomes visible in error_file.log
    #[Test]
    public function aStrandedPartialIsLoggedInsteadOfAssumedAway(): void
    {
        $data = $this->getProbe('publish');
        $run = $data['stranded'];
        $this->assertFalse($run['ok']);
        $this->assertSame('write', $run['error'], 'A stranded partial must not change the outcome of the call');
        $this->assertSame([], $run['left'], 'A stranded partial must never be published');
        $this->assertCount(1, $run['parts'], 'This case is only meaningful while the partial really survives');
        $found = array_filter($data['log'], static fn(array $row): bool => str_contains($row['msg'], 'partial') && $row['path'] === 'files/'.$run['parts'][0]);
        $this->assertCount(1, $found, 'The stranded partial was not logged with its root-relative path: '.json_encode($data['log']));
    }

    # Partials of earlier runs are collected by the one-hour sweep, which is the only thing that removes them
    #[Test]
    public function abandonedPartialsAreSweptAfterOneHour(): void
    {
        $data = $this->getProbe('sweep');
        $this->assertTrue($data['fresh']['ok'], 'The publication next to the abandoned partials failed');
        $this->assertSame([false, true], $data['fresh']['kept'], 'The sweep must remove the two-hour-old partial and keep the fresh one');
        $this->assertTrue($data['aged']['ok']);
        $this->assertSame([false, false], $data['aged']['kept'], 'An hour later the second partial must be swept as well');
        $this->assertSame([], $data['aged']['parts'], 'The publication left a partial of its own behind');
    }

    # Quota counts the incoming file exactly once and never counts partials or the directory sentinels
    #[Test]
    public function quotaCountsTheIncomingFileOnceAndSkipsSentinels(): void
    {
        $data = $this->getProbe('quota');
        $this->assertTrue($data['edge']['ok'], 'A file that exactly fills the quota was refused');
        $this->checkFailShape($data['over'], 'quota', 'One byte over the quota');
        $this->assertSame(['stored.bin'], $data['over']['left'], 'The rejected file was published anyway');
        $this->assertTrue($data['skipped']['ok'], 'index.html, .htaccess and a partial were counted against the quota');
    }

    # Only a plain relative directory that resolves below the upload root is a destination
    #[Test]
    public function onlyARelativeDirectoryBelowTheRootIsADestination(): void
    {
        $data = $this->getProbe('paths');
        foreach (['plain', 'trail', 'back'] as $key) {
            $this->assertTrue($data['runs'][$key]['ok'], 'The '.$key.' destination was refused');
            $this->assertStringStartsWith('files/', (string)$data['runs'][$key]['path']);
        }
        foreach (['empty', 'dot', 'up', 'inner', 'abs', 'drive', 'nul', 'gone'] as $key) {
            $this->assertFalse($data['runs'][$key]['ok'], 'The '.$key.' destination was accepted');
            $this->assertSame('destination', $data['runs'][$key]['error'], 'The '.$key.' destination failed with the wrong code');
        }
        $this->assertSame([], $data['outside'], 'A refused destination wrote outside the upload root');
    }

    # A symlink that leaves the upload root is refused; creating one needs privileges Windows does not grant by default
    #[Test]
    public function aSymlinkOutOfTheRootIsRefused(): void
    {
        $data = $this->getProbe('paths')['escape'];
        if (empty($data['made'])) $this->markTestSkipped('This machine does not allow the probe to create a directory symlink');
        $this->assertFalse($data['ok'], 'A destination that resolves outside the upload root was accepted');
        $this->assertSame('destination', $data['error']);
        $this->assertFalse($data['wrote'], 'The upload was written outside the upload root');
    }

    # Compensation deletes only what this class published, addressed by the exact path a result returned
    #[Test]
    public function deleteStoredFileOnlyRemovesCanonicalClassFiles(): void
    {
        $data = $this->getProbe('delete');
        $this->assertTrue($data['done'], 'The published file could not be deleted through its returned path');
        $this->assertFalse($data['twice'], 'A second delete of the same path reported success');
        $this->assertFalse($data['legacy']['done'], 'A file outside the canonical naming grammar was deleted');
        $this->assertFalse($data['away'], 'A path leaving the upload root was accepted');
        $this->assertFalse($data['absolute'], 'An absolute path was accepted');
        $this->assertFalse($data['gone'], 'A missing file reported a successful deletion');
        $this->assertSame(['legacy.png'], $data['left'], 'The compensation removed something other than its own file');
    }

    # The batch entry point: both submitted shapes, the order of the results, and a limit that transfers nothing
    #[Test]
    public function aBatchReportsOneResultPerFileAndRefusesToOverrunMaxfiles(): void
    {
        $data = $this->getProbe('batch');
        $this->assertCount(1, $data['single'], 'The single-file shape must produce exactly one result');
        $this->assertTrue($data['single'][0]['ok']);
        $this->assertCount(1, $data['count'], 'A batch over maxfiles must report one count failure, not an empty list');
        $this->checkFailShape($data['count'][0], 'count', 'A batch over maxfiles');
        $this->assertSame([], $data['countleft'], 'A batch over maxfiles transferred a file');
        $this->assertCount(2, $data['mixed'], 'A mixed batch must report one result per submitted file');
        $this->assertTrue($data['mixed'][0]['ok'], 'The accepted file of a mixed batch was refused');
        $this->checkFailShape($data['mixed'][1], 'extension', 'The refused file of a mixed batch');
        $this->assertCount(1, $data['mixedleft'], 'A mixed batch published the wrong number of files');
        $this->checkFailShape($data['none'][0], 'missing', 'An empty submission');
        $this->checkFailShape($data['nested'][0], 'missing', 'A nested file entry');
        $this->assertCount(1, $data['flat'], 'A batch whose name is a list while the other keys are scalars must still report one result per name');
        $this->checkFailShape($data['flat'][0], 'missing', 'A batch with a scalar tmp_name next to a list of names');
        $this->assertSame([], $data['flatleft'], 'A malformed batch shape published a file');
    }

    # Every writer takes the same destination lock, so concurrent uploads can neither oversubscribe the quota nor overwrite each other
    #[Test]
    public function concurrentWritersCannotOversubscribeTheQuota(): void
    {
        $data = $this->getProbe('race');
        $done = array_values(array_filter($data['runs'], static fn(?array $row): bool => !empty($row['ok'])));
        $this->assertCount(4, $data['runs'], 'Not every concurrent writer reported back');
        $this->assertCount(2, $done, 'The quota admitted the wrong number of concurrent writers');
        $this->assertSame(2, count($data['left']), 'The destination holds a different number of files than were published');
        $this->assertLessThanOrEqual($data['max'], $data['bytes'], 'Concurrent writers exceeded the quota');
        $this->assertSame([], $data['parts'], 'A concurrent run left a partial behind');
        foreach ($data['runs'] as $row) {
            if (empty($row['ok'])) $this->assertSame('quota', $row['error'], 'A refused concurrent writer failed for the wrong reason');
        }
    }

    # Source level: the two publication strategies the plan rejected must stay rejected, so the final name is never opened before the rename and link() is never relied on
    #[Test]
    public function thePublicationNeitherReservesTheFinalNameNorLinks(): void
    {
        $code = $this->getFile('core/classes/upload.php');
        $this->assertSame(1, substr_count($code, 'fopen('), 'The class opens a file somewhere other than the remote partial');
        $this->assertStringContainsString("fopen(\$part, 'ab')", $code, 'The one fopen() of the class must be the remote partial, never a final name');
        $this->assertStringContainsString('FileManager::getPathLock($canon)', $code, 'The publication no longer runs under the shared destination lock');
        $this->assertStringNotContainsString('function getLockHandle', $code, 'The class kept a lock protocol of its own, so its writers stand in two queues');
        $this->assertDoesNotMatchRegularExpression('#(?<![a-zA-Z_])link\(#', $code, 'The class publishes through link(), which the plan rejects');
        $this->assertStringContainsString('return rename($from, $dest);', $code, 'The publication primitive is no longer a single rename()');
    }

    # Source level: the network reaches the class through the two protected seams only, which is what keeps every remote test deterministic and offline
    #[Test]
    public function theNetworkIsReachedThroughTheTwoSeamsOnly(): void
    {
        $code = $this->getFile('core/classes/upload.php');
        $this->assertSame(1, substr_count($code, 'curl_exec('), 'cURL is executed somewhere other than the request seam');
        $this->assertSame(1, substr_count($code, 'dns_get_record('), 'The resolver is called somewhere other than the DNS seam');
        $this->assertStringContainsString('protected function getRemoteReply(array $opts): array {', $code, 'The cURL seam is missing');
        $this->assertStringContainsString('protected function getHostRecords(string $host): array {', $code, 'The DNS seam is missing');
        $note = 'The class fetches through something other than cURL';
        $this->assertDoesNotMatchRegularExpression('#(?<![a-zA-Z_])(file_get_contents|fsockopen|stream_socket_client)\(#', $code, $note);
    }

    # Only a plain http or https URL with a default port, no credentials and no fragment reaches a lookup at all
    #[Test]
    public function theUrlPolicyRefusesEverythingItCannotNormalize(): void
    {
        $data = $this->getProbe('remoteurl');
        foreach (['plain', 'port', 'query', 'clear'] as $key) {
            $this->assertTrue($data[$key]['ok'], 'The '.$key.' URL was refused with '.var_export($data[$key]['error'], true));
            $this->assertSame(1, $data[$key]['hits'], 'The '.$key.' URL took more than one request');
        }
        $keys = ['badport', 'zeroport', 'crossport', 'ftp', 'local', 'script', 'creds', 'frag', 'single', 'under', 'space', 'nul', 'none', 'noscheme'];
        foreach ($keys as $key) {
            $this->checkFailShape($data[$key], 'remote', 'The '.$key.' URL');
            $this->assertSame(0, $data[$key]['hits'], 'The '.$key.' URL was requested instead of being refused before any lookup');
            $this->assertSame([], $data[$key]['left'], 'The '.$key.' URL published a file');
        }
    }

    # Every resolved address is validated before a connection, one non-public address anywhere in the answer refuses the host, and the alias chain is bounded
    #[Test]
    public function onlyPubliclyRoutableAddressesAreConnectedTo(): void
    {
        $data = $this->getProbe('remotedns');
        foreach (['public', 'sixpub', 'alias', 'literal', 'sixlit', 'pair'] as $key) {
            $this->assertTrue($data[$key]['ok'], 'The '.$key.' host was refused with '.var_export($data[$key]['error'], true));
        }
        $this->assertSame('104.18.32.7', $data['pair']['pin'], 'An answer with several validated addresses is not resolved to one deterministic address');
        $this->assertSame('files.example.net:443:93.184.216.34', $data['public']['bind'], 'A resolved host is not pinned to the address that was validated');
        $note = 'A host that is already an address must carry no resolve entry: libcurl fails the whole transfer over an IPv6 literal in one';
        $this->assertSame('', $data['literal']['bind'], $note);
        $this->assertSame('', $data['sixlit']['bind'], $note);
        # A host the policy refused answers its own code, so an operator reading the log can tell a private target apart from a prefix this build does not know yet
        $keys = ['private', 'loop', 'link', 'cgnat', 'zero', 'cast', 'docnet', 'mixed', 'ula', 'sixloop', 'mapped', 'aliasbad'];
        $keys = array_merge($keys, ['literalbad', 'sixliteral']);
        # Every IPv6 range the IANA registry marks as not globally reachable, each one a prefix an earlier revision of the block list let through
        $keys = array_merge($keys, ['sixbench', 'sixdoc', 'sixsid', 'sixorchid', 'sixdummy', 'sixdiscard']);
        # Reserved space refused by absence from the delegated prefixes: site-local, outside 2000::/3, the returned 6bone block and a gap between two registry entries
        $keys = array_merge($keys, ['sixsite', 'sixfree', 'sixbone', 'sixgap']);
        # A lookup that found nothing is not a refusal and keeps the generic code: an unknown host, an alias loop and a chain over the limit
        $miss = ['gone', 'aliasloop', 'aliasdeep'];
        foreach ([...$keys, ...$miss] as $key) {
            $this->checkFailShape($data[$key], in_array($key, $miss, true) ? 'remote' : 'address', 'The '.$key.' host');
            $this->assertSame(0, $data[$key]['hits'], 'The '.$key.' host was connected to');
            $this->assertSame([], $data[$key]['left'], 'The '.$key.' host published a file');
        }
    }

    # The prefix list is maintained by hand between releases, so every member is held to the shape the policy can match at all
    # A prefix that is not aligned to its own length silently covers a different block than the operator meant, and one outside 2000::/3 would widen the policy past global unicast
    #[Test]
    public function everyShippedPrefixIsAlignedGlobalUnicast(): void
    {
        if (!class_exists('Upload', false)) {
            if (!defined('FUNC_FILE')) define('FUNC_FILE', true);
            require_once dirname(__DIR__, 2).'/core/classes/upload.php';
        }
        $nets = \Upload::getPublicNets();
        $this->assertNotEmpty($nets, 'The address policy holds no prefix at all, so no remote fetch can succeed');
        $seen = [];
        foreach ($nets as $net) {
            $part = explode('/', $net);
            $this->assertCount(2, $part, $net.' is not written as a prefix with a length');
            $bin = inet_pton($part[0]);
            $bits = (int)$part[1];
            $this->assertNotFalse($bin, $net.' does not carry a readable address');
            $this->assertSame(16, strlen((string)$bin), $net.' is not an IPv6 prefix');
            $this->assertGreaterThan(0, $bits, $net.' has no usable prefix length');
            $this->assertLessThanOrEqual(128, $bits, $net.' has a prefix length past the address size');
            $this->assertSame(0x20, ord((string)$bin[0]) & 0xe0, $net.' sits outside the 2000::/3 global unicast block');
            $this->assertSame($bin, $this->getMaskedAddress((string)$bin, $bits), $net.' is not aligned to its own prefix length');
            $this->assertArrayNotHasKey($net, $seen, $net.' is listed twice');
            $seen[$net] = true;
        }
    }

    # Zero every bit of one packed address below the given prefix length, which is what an aligned prefix already looks like
    private function getMaskedAddress(string $bin, int $bits): string
    {
        for ($i = 0; $i < 16; $i++) {
            $keep = min(8, max(0, $bits - $i * 8));
            $bin[$i] = chr(ord($bin[$i]) & (0xff << (8 - $keep)) & 0xff);
        }
        return $bin;
    }

    # Every refusal by the address policy names the address in error_file.log, which is the only signal an operator has that the prefix list has fallen behind the registry
    #[Test]
    public function everyRefusedAddressIsRecordedWithItsAddress(): void
    {
        $rows = $this->getProbe('remotedns')['policy']['log'];
        $this->assertNotEmpty($rows, 'The address policy recorded nothing at all');
        $seen = [];
        foreach ($rows as $one) {
            if ($one['msg'] === 'Remote address refused by the address policy') $seen[] = $one['address'];
        }
        foreach (['10.0.0.5', '127.0.0.1', '169.254.169.254', '::1', 'fc00::1', 'fec0::1', '3ffe::1', '2004::1'] as $addr) {
            $this->assertContains($addr, $seen, 'The refusal of '.$addr.' was not recorded with the address it refused');
        }
    }

    # Redirects: every hop is validated again, at most three are followed, an intermediate body is discarded and only a final 2xx publishes
    #[Test]
    public function redirectsAreRevalidatedAndBoundedToThree(): void
    {
        $data = $this->getProbe('remotehops');
        foreach (['one' => 2, 'relative' => 2, 'three' => 4, 'discard' => 2] as $key => $hits) {
            $this->assertTrue($data[$key]['ok'], 'The '.$key.' redirect was refused with '.var_export($data[$key]['error'], true));
            $this->assertSame($hits, $data[$key]['hits'], 'The '.$key.' redirect took the wrong number of requests');
        }
        $this->assertSame($data['bytes'], $data['discard']['size'], 'The body of the redirect response was published together with the final one');
        foreach (['four' => 'remote', 'private' => 'address', 'scheme' => 'remote', 'noloc' => 'remote', 'missing' => 'remote'] as $key => $code) {
            $this->checkFailShape($data[$key], $code, 'The '.$key.' redirect');
            $this->assertSame([], $data[$key]['left'], 'The '.$key.' redirect published a file');
        }
        $this->assertSame(4, $data['four']['hits'], 'A fourth redirect must not be requested');
        $this->assertSame(1, $data['private']['hits'], 'A redirect to a private address must not be requested');
    }

    # Both byte limits stop the transfer, a transport that never answers is a refusal, and a connection that reached another address is refused after the fact
    #[Test]
    public function theByteLimitsAndTheConnectedAddressAreEnforced(): void
    {
        $data = $this->getProbe('remotebody');
        $this->assertTrue($data['edge']['ok'], 'A body that exactly fills the limit was refused');
        $this->assertSame($data['bytes'], $data['edge']['size'], 'The published size is not the size of the transferred body');
        $this->checkFailShape($data['claim'], 'size', 'A Content-Length over the limit');
        $this->checkFailShape($data['stream'], 'size', 'A body that grows over the limit without a Content-Length');
        $this->checkFailShape($data['fail'], 'remote', 'A transport that never answered');
        $this->checkFailShape($data['rebind'], 'remote', 'A connection that reached another address than the validated one');
        foreach (['claim', 'stream', 'fail', 'rebind'] as $key) {
            $this->assertSame([], $data[$key]['left'], 'The '.$key.' case published a file');
        }
    }

    # One request: pinned to the validated address, without a proxy, restricted to http and https, with TLS verification kept and both timeouts set
    #[Test]
    public function theRequestIsPinnedHardenedAndTimeBounded(): void
    {
        $data = $this->getProbe('remoteopts');
        $this->assertTrue($data['ok'], 'The reference fetch failed with '.var_export($data['error'], true));
        $this->assertCount(1, $data['hits'], 'The reference fetch took more than one request');
        $hit = $data['hits'][0];
        $this->assertSame('93.184.216.34', $hit['pin'], 'The request was not pinned to the validated address');
        $this->assertSame('', $hit['proxy'], 'The request did not disable the environment proxy');
        $this->assertSame('http,https', $hit['protos'], 'The request did not restrict the allowed protocols');
        $this->assertFalse($hit['follow'], 'cURL follows redirects itself, so a hop would skip the address policy');
        $this->assertSame(0, $hit['redirs'], 'cURL was allowed to follow redirects');
        $this->assertSame([true, 2], $hit['verify'], 'TLS peer or host verification is no longer enabled');
        $this->assertSame([5, 30], $hit['wait'], 'The connect and total timeouts are not the ones the plan fixes');
    }

    # The final URL decides the extension, and the transferred body then passes the same type, image, quota and naming checks a local upload passes
    #[Test]
    public function theFinalUrlDecidesTheExtensionAndTheBodyIsCheckedLikeALocalOne(): void
    {
        $data = $this->getProbe('remotetype');
        $this->assertTrue($data['query']['ok'], 'A URL with a query string was refused with '.var_export($data['query']['error'], true));
        $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}-9\.png$#', (string)$data['owner']['file'], 'A remote file does not follow the canonical owner grammar');
        $this->assertTrue($data['archive']['ok'], 'A remote archive was refused with '.var_export($data['archive']['error'], true));
        $this->assertNull($data['archive']['width'], 'A non-image result carries a width');
        $this->assertNull($data['archive']['height'], 'A non-image result carries a height');
        $want = [
            'noext' => 'extension', 'blocked' => 'extension', 'notlisted' => 'extension', 'unsupported' => 'unsupported',
            'mime' => 'mime', 'broken' => 'image', 'dimensions' => 'dimensions', 'destination' => 'destination', 'quota' => 'quota',
        ];
        foreach ($want as $key => $code) {
            $this->checkFailShape($data[$key], $code, 'The remote '.$key.' case');
        }
        $this->assertSame(0, $data['destination']['hits'], 'A destination that cannot be resolved must be refused before the fetch');
        $this->assertSame(['stored.png'], $data['quota']['left'], 'The quota rejection changed the destination');
    }

    # Source level: the class is reached through one accessor that names the upload root and the lock directory, and is loaded lazily rather than on every request
    #[Test]
    public function theServiceAccessorIsTheOnlyPlaceThatBuildsTheClass(): void
    {
        $code = $this->getFile('core/system.php');
        $this->assertStringContainsString('function getUploadService(): Upload {', $code, 'core/system.php has no upload service accessor');
        $this->assertStringContainsString('new Upload(UPLOADS_DIR)', $code, 'The accessor does not build the class over the upload root');
        $this->assertSame(1, substr_count($code, "require_once BASE_DIR.'/core/classes/upload.php'"), 'The class file must be required exactly once, inside the accessor');
        $this->assertStringNotContainsString('new Upload(', $this->getFile('core/classes/parser.php'), 'A consumer builds the class itself instead of using the accessor');
    }
}
