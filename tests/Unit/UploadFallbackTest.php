<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docs/UPLOAD-FILEINFO-PLAN-2026.md: the upload half of a PHP build without the Fileinfo extension.
 * tests/Support/upload_probe.php drives the class through a subclass whose type reader is gone while GD,
 * cURL, zlib and the archive extension of this machine stay available, so the structural fallback is the
 * only thing left to judge a file by. Every fixture is a real file of its format, built by the probe with
 * the blocks, sizes and checksums its container defines; a body that only carries the opening signature
 * of a format is one of the rejection cases here rather than a fixture.
 */
final class UploadFallbackTest extends TestCase
{
    private static array $probe = [];
    private const CANON = ['7z', 'avif', 'flac', 'gif', 'gz', 'jpeg', 'jpg', 'm4a', 'mp3', 'mp4', 'oga', 'ogg', 'opus', 'pdf', 'png', 'rar', 'tar', 'wav', 'webm', 'webp', 'zip'];
    # The canonical type of every format, which is what the fallback answers once the structure of a file really is the format its extension claims
    private const MIME = [
        'gif' => 'image/gif', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'avif' => 'image/avif',
        'mp3' => 'audio/mpeg', 'wav' => 'audio/wav', 'flac' => 'audio/flac', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'opus' => 'audio/ogg',
        'm4a' => 'audio/mp4', 'mp4' => 'video/mp4', 'webm' => 'video/webm', 'pdf' => 'application/pdf', 'zip' => 'application/zip',
        'rar' => 'application/vnd.rar', 'gz' => 'application/gzip', '7z' => 'application/x-7z-compressed', 'tar' => 'application/x-tar',
    ];
    # The only pairs whose bodies really are one and the same format, so a body of the one is a valid file of the other and may not be counted as a substitution
    private const ALIAS = ['jpg' => ['jpeg'], 'jpeg' => ['jpg'], 'ogg' => ['oga'], 'oga' => ['ogg'], 'opus' => ['ogg', 'oga']];

    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $file = dirname(__DIR__).'/Support/upload_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_upload_blind_'.$mode;
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

    # Assert the failure shape: a refused upload carries its code and nothing else, and leaves neither a file nor a partial behind
    private function checkFailShape(array $run, string $code, string $note): void
    {
        $this->assertFalse($run['ok'], $note.' reported success');
        $this->assertSame($code, $run['error'], $note.' returned the wrong code');
        $this->assertNull($run['file'], $note.' returned a file name');
        $this->assertNull($run['path'], $note.' returned a path');
        $this->assertSame(0, $run['size'], $note.' returned a size');
        $this->assertNull($run['mime'], $note.' returned a MIME type');
        if (isset($run['left'])) $this->assertSame([], $run['left'], $note.' published a file');
        if (isset($run['parts'])) $this->assertSame([], $run['parts'], $note.' left a partial behind');
    }

    # A real file of each of the 21 formats is published by a build without a magic database, under its own extension and with the canonical type of its format
    #[Test]
    public function everyFormatIsPublishedWithoutAMagicDatabase(): void
    {
        $data = $this->getProbe('nomagic');
        $keys = array_keys($data);
        sort($keys);
        $this->assertSame(self::CANON, $keys, 'The probe did not report one run per canonical format');
        foreach ($data as $ext => $run) {
            $this->assertTrue($run['made'], 'No '.$ext.' fixture could be built, so the format was never tried at all');
            $this->assertTrue($run['ok'], 'A valid '.$ext.' file was refused without a magic database with '.var_export($run['error'], true));
            $this->assertSame(self::MIME[$ext], $run['mime'], 'The fallback named a '.$ext.' file something other than its canonical type');
            $this->assertMatchesRegularExpression('#^files-[a-zA-Z0-9]{10}\.'.preg_quote($ext, '#').'$#', (string)$run['file'], 'The stored '.$ext.' name is not canonical');
            $this->assertSame([$run['file']], $run['left'], 'The '.$ext.' run left something other than its published file behind');
            $this->assertSame([], $run['parts'], 'The '.$ext.' run left a partial behind');
        }
    }

    # The same bodies against the magic database: it stays the first reader and keeps naming every one of them itself
    #[Test]
    public function theMagicDatabaseStaysTheFirstReader(): void
    {
        $body = $this->getFile('core/classes/upload.php');
        $this->assertStringContainsString('$mime = ($info instanceof finfo) ? $info->file($path) : false;', $body, 'The class no longer asks the magic database first');
        $this->assertStringContainsString("if (\$mime !== '' && \$mime !== 'application/octet-stream') return \$mime;", $body, 'A named type is no longer answered unchanged');
        foreach ($this->getProbe('magic') as $ext => $run) {
            $this->assertTrue($run['made'], 'No '.$ext.' fixture could be built for the magic database');
            $this->assertTrue($run['ok'], 'A valid '.$ext.' file was refused with a magic database present with '.var_export($run['error'], true));
            $this->assertStringEndsWith('.'.$ext, (string)$run['file'], 'The '.$ext.' file was not published under its own extension');
        }
    }

    # One body of each format under every other extension: without a magic database the content still has to answer for the name it arrived under
    #[Test]
    public function aBodyUnderAForeignExtensionIsRefused(): void
    {
        $data = $this->getProbe('cross');
        $keys = array_keys($data);
        sort($keys);
        $this->assertSame(self::CANON, $keys, 'The probe did not build one body per canonical format');
        foreach ($data as $ext => $rows) {
            $this->assertCount(20, $rows, 'The '.$ext.' body was not tried under every other extension');
            foreach ($rows as $want => $run) {
                if (in_array($want, self::ALIAS[$ext] ?? [], true)) {
                    $this->assertTrue($run['ok'], 'A '.$ext.' body was refused under '.$want.', which is the same format under another name');
                    continue;
                }
                $this->assertFalse($run['ok'], 'A '.$ext.' body was published as a '.$want.' file');
                $this->assertSame('mime', $run['error'], 'A '.$ext.' body under '.$want.' failed with the wrong code');
                $this->assertSame([], $run['left'], 'A '.$ext.' body under '.$want.' published a file');
            }
        }
    }

    # A truncated header, a wrong size, offset or checksum, a missing mandatory block and a body that only opens like its format are all refused
    #[Test]
    public function everyBrokenOrDisguisedBodyIsRefused(): void
    {
        $data = $this->getProbe('faulty');
        foreach ($data['made'] as $ext => $len) {
            $this->assertGreaterThan(0, $len, 'The '.$ext.' body these cases are built from is empty, so they would prove nothing');
        }
        unset($data['made']);
        $keys = [
            'gzcut', 'gzsum', 'gzjunk', 'zipcut', 'zipoff', 'ziptail', 'tarsum', 'tarend', 'sevensum', 'sevenoff', 'rarzero', 'rarcut', 'mpone', 'mpnone',
            'wavlen', 'wavchunk', 'flaclen', 'flaccut', 'oggsum', 'oggcodec', 'boxcut', 'boxbrand', 'boxbare', 'webmdoc', 'webmlen', 'pdfver', 'pdfend',
            'pdfaway', 'pdfzip',
        ];
        $this->assertSame($keys, array_keys($data), 'The set of broken bodies changed, so one of the structural checks is no longer proven');
        foreach ($data as $key => $run) {
            $this->checkFailShape($run, 'mime', 'The '.$key.' body');
        }
    }

    # A missing file, a failed transfer, an oversized file and a refused extension are all answered before the content is ever read
    #[Test]
    public function theCheapRefusalsComeBeforeTheContent(): void
    {
        $data = $this->getProbe('first');
        $want = [
            'missing' => 'missing', 'transfer' => 'transfer', 'inisize' => 'size', 'size' => 'size',
            'extension' => 'extension', 'blocked' => 'extension', 'unsupported' => 'unsupported', 'gone' => 'mime',
        ];
        foreach ($want as $key => $code) {
            $this->checkFailShape($data[$key], $code, 'The '.$key.' case without a magic database');
        }
        $this->assertTrue($data['done']['ok'], 'The reference upload of this scenario failed with '.var_export($data['done']['error'], true));
        $this->assertSame('image/png', $data['done']['mime'], 'The fallback did not name the reference upload');
        $body = $this->getFile('core/classes/upload.php');
        $note = 'The input check still refuses a whole build before it reads its own arguments';
        $this->assertStringNotContainsString("if (\$this->getTypeReader() === null) return 'unsupported';", $body, $note);
    }

    # The remote half runs through the same validator, and every refusal leaves the destination as it found it
    #[Test]
    public function theRemoteHalfUsesTheSameValidator(): void
    {
        $data = $this->getProbe('blindnet');
        foreach (['image' => 'image/png', 'archive' => 'application/gzip', 'sound' => 'audio/flac'] as $key => $mime) {
            $this->assertTrue($data[$key]['ok'], 'The remote '.$key.' was refused without a magic database with '.var_export($data[$key]['error'], true));
            $this->assertSame($mime, $data[$key]['mime'], 'The remote '.$key.' was named something other than its canonical type');
            $this->assertSame([], $data[$key]['parts'], 'The remote '.$key.' left a partial behind');
        }
        $this->checkFailShape($data['mime'], 'mime', 'A remote body that is not what its URL claims');
        $this->checkFailShape($data['broken'], 'image', 'A remote image whose pixel data is gone');
        $body = $this->getFile('core/classes/upload.php');
        $this->assertSame(2, substr_count($body, '$this->getFileMime($'), 'The two flows no longer read the content through one and the same validator');
        $note = 'The remote flow still refuses a build the transfer alone decides about';
        $this->assertStringNotContainsString("if (\$this->getTypeReader() === null || !function_exists('curl_init'))", $body, $note);
    }

    # Source level: the fallback is a structural reader of its own, reached through a seam a test can close, and it never asks the client what it sent
    #[Test]
    public function theFallbackIsStructuralAndNeedsNothingInstalled(): void
    {
        $body = $this->getFile('core/classes/upload.php');
        $this->assertStringContainsString('protected function getTypeReader(): ?finfo {', $body, 'The type reader is no longer the seam a test can close');
        $this->assertStringNotContainsString('$_FILES', $body, 'The class reads the submitted type or name out of the request itself');
        $this->assertStringNotContainsString('Content-Type', $body, 'The class judges a remote file by the header its server sent');
        $note = 'The class reaches for a shell utility, which no shipped SLAED may depend on';
        $this->assertDoesNotMatchRegularExpression('#(?<![a-zA-Z_])(shell_exec|exec|passthru|proc_open|popen|system)\(#', $body, $note);
        $json = json_decode($this->getFile('composer.json'), true);
        $this->assertIsArray($json, 'composer.json is no longer readable');
        foreach (['ext-fileinfo', 'ext-zip', 'ext-zlib'] as $one) {
            $this->assertArrayNotHasKey($one, $json['require'], $one.' is a hard requirement again, which is exactly the build the fallback exists for');
            $this->assertArrayHasKey($one, $json['suggest'], $one.' is no longer named as the optional reader it is');
        }
        foreach (['ZipArchive', 'inflate_init'] as $call) {
            $this->assertStringContainsString($call, $body, 'The fallback no longer uses '.$call.' where it is available');
            $this->assertMatchesRegularExpression('#(class_exists|function_exists)\(\''.$call.'\'\)#', $body, $call.' is used without asking whether this build has it');
        }
    }

    # Source level: every format of the policy has a validator of its own, and none of them is answered by a first byte comparison alone
    #[Test]
    public function everyFormatHasAStructuralValidator(): void
    {
        $body = $this->getFile('core/classes/upload.php');
        $from = strpos($body, 'private function checkFormatBody(');
        $this->assertNotFalse($from, 'The one dispatch of the fallback is gone');
        $part = substr($body, $from, (int)strpos($body, "\n    }\n", $from) - $from);
        foreach (self::CANON as $ext) {
            $this->assertMatchesRegularExpression("#'".preg_quote($ext, '#')."'#", $part, 'The format '.$ext.' has no branch in the fallback dispatch');
        }
        $names = ['checkSoundBody', 'checkWaveBody', 'checkFlacBody', 'checkOggBody', 'checkBoxBody', 'checkWebmBody', 'checkPdfBody'];
        $names = array_merge($names, ['checkZipBody', 'checkRarBody', 'checkGzipBody', 'checkSevenBody', 'checkTarBody']);
        foreach ($names as $name) {
            $this->assertStringContainsString('private function '.$name.'(', $body, 'The validator '.$name.'() is gone');
        }
        $this->assertStringContainsString('default => false,', $part, 'The fallback dispatch no longer fails closed on a format it does not know');
    }

    # The refusal of a whole capability is told apart from the refusal of one file, in the log and on the screen alike
    #[Test]
    public function aMissingCapabilityIsNamedAndNotBlamedOnTheFile(): void
    {
        $body = $this->getFile('core/classes/upload.php');
        foreach (['fileinfo_missing', 'fileinfo_init_failed', 'decoder_missing', 'curl_missing'] as $why) {
            $this->assertStringContainsString("addCapsNote('".$why."')", $body, 'The log no longer tells '.$why.' apart from the other capabilities');
        }
        $code = $this->getFile('core/system.php');
        $this->assertStringContainsString("'unsupported' => _ERROR_SERV,", $code, 'A capability this build lacks is still explained as an inadmissible file');
        foreach (['modules/files/index.php', 'modules/files/admin/index.php', 'modules/account/index.php'] as $path) {
            $one = $this->getFile($path);
            $this->assertStringContainsString('getUploadFailText(', $one, $path.' does not explain a refused upload through the one resolver');
            $this->assertStringNotContainsString("'extension', 'mime', 'image', 'unsupported' => _ERROR_FILE", $one, $path.' still carries a mapping of its own');
        }
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
            $this->assertStringContainsString("define('_ERROR_SERV',", $this->getFile('lang/'.$lang.'.php'), 'The locale '.$lang.' is missing the neutral capability text');
        }
    }
}
