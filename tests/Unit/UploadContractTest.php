<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Batches 3 and 4 of docs/UPLOAD-2026.md: the local and the remote half of the Upload class
final class UploadContractTest extends TestCase
{
    # The raw answer of each probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static array $probe = [];
    private const CANON = ['7z', 'avif', 'flac', 'gif', 'gz', 'jpeg', 'jpg', 'm4a', 'mp3', 'mp4', 'oga', 'ogg', 'opus', 'pdf', 'png', 'rar', 'tar', 'wav', 'webm', 'webp', 'zip'];

    # The probe tests/Support/upload_probe.php boots the real core with writable directories in scratch, one scenario per process on a disposable upload root
    # An HTTP upload, a controlled clock or a refusing filesystem go through the protected seams of the probe subclass; the type map meets the local libmagic build
    # The remote scenarios answer the DNS and cURL seams from a scripted zone and reply chain, so every rejected range and redirect is reached without a socket
    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $file = dirname(__DIR__).'/Support/upload_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_upload_probe_'.$mode;
        $out = self::$outs[__FUNCTION__.$mode] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' '.escapeshellarg($mode).' '
            .escapeshellarg($work).' 2>&1');
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

    # Answer whether the probe built the fixture of one case; a missing one is accepted only for a format this GD build cannot encode
    private function checkFitMade(array $run, string $ext): bool
    {
        if (!empty($run['made'])) return true;
        $this->assertFalse($this->getProbe('fit')['caps'][$ext] ?? true, 'This build can encode '.$ext.' but the probe produced no fixture of it');
        return false;
    }

    # Assert one scaled publication: the result and the stored file agree on the fitted size, the container is the one that was sent, and nothing but the file is left
    private function checkFitShape(array $run, array $size, string $type, string $note): void
    {
        $this->assertTrue($run['ok'], $note.' was refused with '.var_export($run['error'], true));
        $this->assertSame($size, [$run['width'], $run['height']], $note.' reports a size other than the fitted one');
        $this->assertSame($size, $run['real'], $note.' is stored at a size other than the one its result reports');
        $this->assertSame($type, $run['type'], $note.' changed its container on the way');
        $this->assertSame($type, $run['mime'], $note.' reports a type other than the one it was sent as');
        $this->assertSame($run['bytes'], $run['size'], $note.' reports the weight of the transferred file instead of the stored one');
        $this->assertFalse($run['same'], $note.' was stored as it was sent');
        $this->assertCount(1, $run['left'], $note.' left something other than its one file');
        $this->assertSame([], $run['parts'], $note.' left a partial behind');
    }

    # An image over the bounds of its rule is scaled into them in proportion and stays in its own container, locally and by link; one bound alone fits as well
    #[Test]
    public function anImageOverTheBoundsIsScaledInItsOwnFormat(): void
    {
        $data = $this->getProbe('fit');
        $type = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif'];
        foreach ($type as $ext => $mime) {
            $run = $data['scale'][$ext];
            if (!$this->checkFitMade($run, $ext)) continue;
            $this->checkFitShape($run, [100, 50], $mime, 'A '.$ext.' of 200x100 under bounds of 100x100');
            $this->assertSame('RGBW', $run['word'], 'A scaled '.$ext.' without an orientation was turned or mirrored');
        }
        $this->checkFitShape($data['link'], [100, 50], 'image/png', 'A png of 200x100 fetched by link under bounds of 100x100');
        $this->checkFitShape($data['wide'], [50, 25], 'image/png', 'A png of 200x100 under a width bound of 50 alone');
        $this->checkFitShape($data['high'], [40, 20], 'image/png', 'A png of 200x100 under a height bound of 20 alone');
    }

    # The words are the corners Chromium and Firefox drew for the same fixtures: they turn a JPEG, an early eXIf of a PNG and irot with imir of an AVIF, never a WebP
    #[Test]
    public function aScaledImageIsTurnedTheWayABrowserShowsIt(): void
    {
        $data = $this->getProbe('fit')['turn'];
        $want = ['jpg1' => 'RGBW', 'jpg2' => 'GRWB', 'jpg3' => 'WBGR', 'jpg4' => 'BWRG', 'jpg5' => 'RBGW', 'jpg6' => 'BRWG', 'jpg7' => 'WGBR', 'jpg8' => 'GWRB',
            'jpgbig' => 'BRWG', 'pngbig' => 'GWRB', 'png6' => 'BRWG', 'pnglate' => 'RGBW', 'webp6' => 'RGBW', 'avifrot' => 'GWRB', 'avifboth' => 'RBGW', 'avifswap' => 'WGBR'];
        $side = ['jpg5', 'jpg6', 'jpg7', 'jpg8', 'jpgbig', 'pngbig', 'png6', 'avifrot', 'avifboth', 'avifswap'];
        $type = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif'];
        foreach ($want as $key => $word) {
            $run = $data[$key];
            $ext = (string)preg_replace('#^(jpg|png|webp|avif).*$#', '$1', $key);
            if (!$this->checkFitMade($run, $ext)) continue;
            $size = in_array($key, $side, true) ? [20, 40] : [40, 20];
            $this->checkFitShape($run, $size, $type[$ext], 'The turned fixture '.$key.' of 64x32 under bounds of 40x40');
            $this->assertSame($word, $run['word'], 'The fixture '.$key.' is not stored the way a browser shows it');
            $this->assertFalse($run['exif'], 'The scaled fixture '.$key.' still carries the orientation its pixels already follow');
        }
    }

    # The bounds are compared with the size an image is shown at: a turned one inside them is stored byte for byte under the size of its header, one over them is scaled upright
    #[Test]
    public function theBoundsAreComparedWithTheShownSize(): void
    {
        $data = $this->getProbe('fit');
        $run = $data['inside'];
        $this->assertTrue($run['ok'], 'A turned jpg shown at 32x64 was refused under bounds of 40x100 with '.var_export($run['error'], true));
        $this->assertTrue($run['same'], 'An image inside the bounds was rewritten instead of stored byte for byte');
        $this->assertTrue($run['exif'], 'An image inside the bounds lost its metadata');
        $this->assertSame([64, 32], [$run['width'], $run['height']], 'An image inside the bounds no longer reports the size of its header');
        $this->checkFitShape($data['shown'], [20, 40], 'image/jpeg', 'A turned jpg whose header fits 64x40 while its shown size of 32x64 does not');
        $this->assertSame('BRWG', $data['shown']['word'], 'The image scaled for its shown size is not stored upright');
    }

    # A scaled JPEG keeps the ICC segments of its source and no other metadata; a profile of another colour model, an incomplete one and one past 64 KB stay behind
    #[Test]
    public function aScaledJpegKeepsTheProfileOfItsSource(): void
    {
        $data = $this->getProbe('fit')['icc'];
        foreach (['whole' => 'in one segment', 'split' => 'in two segments', 'link' => 'fetched by link'] as $key => $note) {
            $run = $data[$key];
            if (!$this->checkFitMade($run, 'jpg')) continue;
            $this->checkFitShape($run, [20, 40], 'image/jpeg', 'A turned jpg with an RGB profile '.$note);
            $this->assertTrue($run['kept'], 'The scaled jpg with an RGB profile '.$note.' does not carry the segments of its source, whole and in order');
            $this->assertSame('BRWG', $run['word'], 'The scaled jpg with a profile '.$note.' is not stored upright, or the profile broke its decode');
            $this->assertFalse($run['exif'], 'The scaled jpg with a profile '.$note.' carries its Exif segment as well');
        }
        foreach (['cmyk' => 'of a colour model the written pixels are not in', 'part' => 'that declares a segment it does not hold', 'heavy' => 'past 64 KB'] as $key => $note) {
            $run = $data[$key];
            if (!$this->checkFitMade($run, 'jpg')) continue;
            $this->checkFitShape($run, [20, 40], 'image/jpeg', 'A turned jpg with a profile '.$note);
            $this->assertSame(0, $run['icc'], 'A profile '.$note.' was carried into the scaled jpg');
        }
        if (!$this->checkFitMade($data['inside'], 'jpg')) return;
        $this->assertTrue($data['inside']['same'], 'A jpg with a profile inside the bounds was rewritten instead of stored byte for byte');
    }

    # An animation over the bounds is refused, because GD would keep one frame of it; inside the bounds it is stored as it was sent
    #[Test]
    public function aMovingImageOverTheBoundsIsRefused(): void
    {
        $data = $this->getProbe('fit');
        foreach (['gif', 'png', 'webp', 'avif'] as $ext) {
            $run = $data['moving'][$ext];
            if (!$this->checkFitMade($run, $ext)) continue;
            $this->checkFailShape($run, 'dimensions', 'A moving '.$ext.' over the bounds');
            $this->assertSame([], $run['left'], 'A moving '.$ext.' over the bounds published a file');
        }
        $this->assertTrue($data['still']['ok'], 'A moving gif inside the bounds was refused with '.var_export($data['still']['error'], true));
        $this->assertTrue($data['still']['same'], 'A moving gif inside the bounds was rewritten, which costs it every frame but the first');
        $this->assertGreaterThan(65536, $data['span'], 'The noise fixture fits one read of the class, so it no longer proves the walk across a chunk');
        $this->checkFitShape($data['noise'], [100, 100], 'image/gif', 'A still gif whose one frame is longer than one read');
        $this->checkFailShape($data['noisy'], 'dimensions', 'A gif whose second frame begins past the first read');
    }

    # A clear pixel stays clear through the scale: the alpha of a PNG, and the one transparent index of a GIF beside a black the scale must not open
    #[Test]
    public function transparencySurvivesTheScale(): void
    {
        $data = $this->getProbe('fit');
        $this->checkFitShape($data['clearpng'], [100, 50], 'image/png', 'A png with a clear corner');
        $this->assertSame('TGBW', $data['clearpng']['word'], 'The clear corner of a scaled png was filled, or an opaque one was cleared');
        $this->checkFitShape($data['cleargif'], [100, 50], 'image/gif', 'A gif with a transparent index');
        $this->assertSame('TKRT', $data['cleargif']['word'], 'The transparent corners of a scaled gif were filled, or its black was cleared with them');
    }

    # A decode is budgeted from the header to the byte, the rows GD allocates plus four megabytes; the weight limit is answered before the scale, a broken body once it decodes
    #[Test]
    public function aDecodeStaysInsideTheMemoryBudget(): void
    {
        $data = $this->getProbe('fit');
        $this->checkFitShape($data['room'], [100, 50], 'image/png', 'A fit of 200x100 into 100x50 with room for exactly its canvas, its rows and the spare');
        $this->checkFailShape($data['tight'], 'dimensions', 'The same fit with one byte less of room');
        $this->assertTrue($data['plain']['ok'], 'An image of 40x30 inside the bounds was refused with room for exactly its decode: '.var_export($data['plain']['error'], true));
        $this->checkFailShape($data['short'], 'dimensions', 'The same image with one byte less of room');
        $this->checkFailShape($data['weight'], 'size', 'An image over the bounds and one byte over the limit of weight');
        $this->checkFailShape($data['broken'], 'image', 'A header of 200x100 without pixel data, over the bounds');
        $this->checkFailShape($data['brokenlink'], 'image', 'The same header fetched by link');
        foreach (['tight', 'short', 'weight', 'broken', 'brokenlink'] as $key) {
            $this->assertSame([], $data[$key]['left'], 'The refused case '.$key.' published a file');
        }
    }

    # The budget is held against the allocator of this PHP: a width of 1025 and a PNG with alpha take eight bytes a pixel, which a count of four or five would let through
    #[Test]
    public function theBudgetCoversWhatTheAllocatorReallyTakes(): void
    {
        $data = $this->getProbe('fit')['real'];
        foreach (['jpg' => 'A jpg 1025 pixels wide', 'png' => 'A png of 1000x1000 with alpha'] as $key => $note) {
            $run = $data[$key];
            if (!$this->checkFitMade($run, $key)) continue;
            $this->assertGreaterThan(0, $run['peak'], $note.' reported no peak, so the comparison proves nothing');
            $need = $run['budget'] - 4194304;
            $this->assertGreaterThanOrEqual($run['peak'] - 2097152, $need, $note.' is budgeted below what its decode takes, less the chunk the peak is rounded to');
            $this->assertLessThanOrEqual($run['peak'] + 4194304, $need, $note.' is budgeted far above what its decode takes, so images that fit are refused');
        }
    }

    # Source level: a build that decodes a format and cannot write it refuses the fit and records the capability, and a decoder is never opened before the budget is answered
    #[Test]
    public function theFitIsPlannedBeforeAnythingIsDecoded(): void
    {
        $code = $this->getFile('core/classes/upload.php');
        $from = (int)strpos($code, 'private function getImageBounds(');
        $body = substr($code, $from, (int)strpos($code, 'private function getFitSize(') - $from);
        $this->assertStringContainsString("'encoder_missing'", $body, 'A format this build cannot write is no longer recorded as the server capability it is');
        $this->assertStringContainsString('$this->addCapsNote($gone);', $body, 'A missing reader or writer of the scale is refused without a note for the operator');
        $this->assertLessThan(strpos($body, 'getDecodeError('), strpos($body, 'getMemoryRoom()'), 'The decode is reached before the memory budget is answered');
        $this->assertStringNotContainsString('getImageCanvas(', $body, 'The bounds check decodes an image it is about to scale, so the pixels are held twice');
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
        $this->assertSame(['error_file.log'], array_values(array_unique(array_column($found, 'file'))), 'A partial that would not delete is a failure, error_file.log only');
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

    # Quota counts the incoming file exactly once and never counts a partial; no upload folder carries a guard file, so every other name is a stored file
    #[Test]
    public function quotaCountsTheIncomingFileOnceAndSkipsSentinels(): void
    {
        $data = $this->getProbe('quota');
        $this->assertTrue($data['edge']['ok'], 'A file that exactly fills the quota was refused');
        $this->checkFailShape($data['over'], 'quota', 'One byte over the quota');
        $this->assertSame(['stored.bin'], $data['over']['left'], 'The rejected file was published anyway');
        $this->assertTrue($data['skipped']['ok'], 'A partial was counted against the quota');
    }

    # Only a plain relative directory that resolves below the upload root is a destination; a missing folder of an owner is created by the first write
    #[Test]
    public function onlyARelativeDirectoryBelowTheRootIsADestination(): void
    {
        $data = $this->getProbe('paths');
        foreach (['plain', 'trail', 'back'] as $key) {
            $this->assertTrue($data['runs'][$key]['ok'], 'The '.$key.' destination was refused');
            $this->assertStringStartsWith('files/', (string)$data['runs'][$key]['path']);
        }
        $this->assertTrue($data['runs']['made']['ok'] && $data['made'], 'A missing folder of an owner was not created by the first write');
        $this->assertStringStartsWith('owner/sub/', (string)$data['runs']['made']['path']);
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
        $this->assertSame(3, substr_count($code, 'fopen('), 'The class opens a file somewhere other than the remote partial and the two reads of the content validators');
        $this->assertSame(2, substr_count($code, "fopen(\$path, 'rb')"), 'The format validator or the reader of the image marks opens its file for more than reading');
        $this->assertStringContainsString("fopen(\$part, 'ab')", $code, 'The writing fopen() of the class must be the remote partial, never a final name');
        $this->assertStringContainsString("fopen(\$path, 'rb')", $code, 'The reading fopen() of the class must be the read-only handle of the content validator');
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
    # A host the policy refused answers its own code, so an operator reading the log can tell a private target apart from a prefix this build does not know yet
    # Refused are every IPv6 range the IANA registry marks as not globally reachable, each one a prefix an earlier revision of the block list let through
    # Reserved space is refused by absence from the delegated prefixes: site-local, outside 2000::/3, the returned 6bone block and a gap between two registry entries
    # A lookup that found nothing is not a refusal and keeps the generic code: an unknown host, an alias loop and a chain over the limit
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
        $keys = ['private', 'loop', 'link', 'cgnat', 'zero', 'cast', 'docnet', 'mixed', 'ula', 'sixloop', 'mapped', 'aliasbad'];
        $keys = array_merge($keys, ['literalbad', 'sixliteral']);
        $keys = array_merge($keys, ['sixbench', 'sixdoc', 'sixsid', 'sixorchid', 'sixdummy', 'sixdiscard']);
        $keys = array_merge($keys, ['sixsite', 'sixfree', 'sixbone', 'sixgap']);
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

    # Every refusal by the address policy names the address in file.log, never in error_file.log: the policy worked, and the line tells an operator the prefix list is behind
    #[Test]
    public function everyRefusedAddressIsRecordedWithItsAddress(): void
    {
        $rows = $this->getProbe('remotedns')['policy']['log'];
        $this->assertNotEmpty($rows, 'The address policy recorded nothing at all');
        $seen = [];
        foreach ($rows as $one) {
            if ($one['msg'] !== 'Remote address refused by the address policy') continue;
            $this->assertSame('file.log', $one['file'], 'A refusal is no failure of the system and must not reach error_file.log');
            $seen[] = $one['address'];
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
