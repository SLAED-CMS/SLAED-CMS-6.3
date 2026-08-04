<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 10 of docs/UPLOAD-2026.md: the half no double can answer. Two of its scenarios run
 * tests/Support/upload_probe.php against the real core in an isolated CLI process, so the resolver
 * and the service accessor are exercised against the configuration the site actually ships rather
 * than against a fixture. The rest reads the adapters themselves and holds them to the ordering
 * rule of Adapter write ordering and compensation - authorization, token and business validation
 * before the class, checked writes and compensation after it. Nothing here writes to the site.
 */
final class UploadIntegrationTest extends TestCase
{
    private const ADAPTERS = [
        'core/system.php' => 'addEditorUpload',
        'modules/account/index.php' => 'saveavatar',
        'modules/files/index.php' => 'send',
        'modules/files/admin/index.php' => 'save',
        'admin/modules/uploads.php' => 'uploadsave',
    ];

    private static array $probe = [];
    private static array $files = [];

    # Run one probe scenario in a fresh process and memoize its report for every test in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/upload_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_upload_live_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Return the body of one function or method, so an ordering claim is made about the handler and not about the file around it
    # The closing brace is matched at the indentation of the declaration, because a method of a class does not close at column zero
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        $head = strrpos(substr($code, 0, $from), "\n");
        $line = substr($code, $head + 1, $from - $head - 1);
        $pad = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
        $stop = strpos($code, "\n".$pad."}\n", $from);
        $this->assertNotFalse($stop, $name.'() in '.$path.' has no closing brace at its own indentation');
        return substr($code, $from, $stop - $from);
    }

    # Every module record of the shipped configuration survives resolve and serialize byte for byte, with all twelve named fields present
    #[Test]
    public function everyShippedRecordRoundTripsThroughTheResolver(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertNotEmpty($data['records'], 'The shipped configuration holds no module record at all');
        foreach ($data['records'] as $mod => $row) {
            $this->assertSame([], $row['missing'], 'The resolved record '.$mod.' is missing named fields');
            $this->assertSame(12, $row['fields'], 'The stored record '.$mod.' does not carry twelve fields');
            $this->assertTrue($row['same'], 'setUploadRuleData(getUploadRuleData('.$mod.')) does not reproduce the stored string');
            $this->assertTrue($row['ok'], 'The record '.$mod.' resolves to a directory that does not exist');
        }
    }

    # An unknown key fails closed and never quietly hands back the general record, which is what would turn any directory into an editor upload target
    #[Test]
    public function anUnknownKeyNeverResolvesTheGeneralRecord(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertFalse($data['unknown']['ok'], 'An unknown module resolved successfully');
        $this->assertSame('', $data['unknown']['ext'], 'An unknown module inherited an extension list');
        $this->assertNotSame($data['allext'], $data['unknown']['ext'], 'An unknown module was answered with the all record');
        $this->assertSame(12, $data['unknown']['keys'], 'A failed resolution drops named fields, so a caller reading one limit would fatal');
    }

    # A short or empty string is a configuration mistake, not a crash: the resolver answers the full key set with zeros
    #[Test]
    public function aMalformedRecordDoesNotFatal(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertSame('gif', $data['short']['ext'], 'A one field record lost its only value');
        $this->assertSame(0, $data['short']['quota'], 'A missing field did not default to the disabled value');
        $this->assertSame(0, $data['short']['guest'], 'A missing guest flag did not default to closed');
        $this->assertFalse($data['empty']['ok'], 'An empty record resolved successfully');
    }

    # The accessor builds one instance over the upload root and the lock directory under LOGS_DIR, and it is the only place that names either
    #[Test]
    public function theServiceIsBuiltOnceOverTheDocumentedPaths(): void
    {
        $data = $this->getProbe('service');
        $this->assertTrue($data['same'], 'getUploadService() built a second instance');
        $this->assertSame($data['wantroot'], $data['root'], 'The service was not built on UPLOADS_DIR');
        $this->assertSame($data['wantlock'], $data['lockdir'], 'The lock directory is not the uploads directory below LOGS_DIR');
        $this->assertCount(21, $data['types'], 'The type policy no longer holds the canonical 21 formats');
    }

    # Every adapter reaches the class through the accessor; a direct construction would put the root and the lock directory in a second place
    # core/system.php is where the accessor itself lives, so it is held to exactly one construction rather than to none, and that one has to sit inside getUploadService()
    #[Test]
    public function noAdapterBuildsTheClassItself(): void
    {
        foreach (self::ADAPTERS as $path => $name) {
            $this->assertStringNotContainsString('new Upload(', $this->getBody($path, $name), $name.'() constructs the class instead of using the accessor');
        }
        $code = $this->getFile('core/system.php');
        $this->assertSame(1, substr_count($code, '= new Upload('), 'core/system.php builds the class somewhere other than the accessor');
        $this->assertStringContainsString('new Upload(', $this->getBody('core/system.php', 'getUploadService'), 'The accessor no longer builds the class');
    }

    # The publish call sits behind the guard of its own flow, so an invalid token or a failed business check can never reach the class
    #[Test]
    public function everyAdapterGuardsThePublishCall(): void
    {
        $guards = [
            'core/system.php' => 'checkSiteToken(',
            'modules/account/index.php' => 'checkSiteToken(',
            'modules/files/index.php' => 'checkSiteToken(',
            'modules/files/admin/index.php' => 'checkAdminPost(',
            'admin/modules/uploads.php' => 'checkAdminPost(',
        ];
        foreach (self::ADAPTERS as $path => $name) {
            $body = $this->getBody($path, $name);
            $gate = strpos($body, $guards[$path]);
            $call = strpos($body, 'getUploadService()->add');
            $this->assertNotFalse($gate, $name.'() has no token check at all');
            $this->assertNotFalse($call, $name.'() no longer publishes through the service');
            $this->assertLessThan($call, $gate, $name.'() reaches the upload service before it checks its token');
        }
    }

    # A preview or a delete is not a save: both file handlers publish only inside the save branch, which is the ordering that stops an orphan
    #[Test]
    public function onlyASaveReachesTheUploadService(): void
    {
        foreach (['modules/files/index.php' => 'send', 'modules/files/admin/index.php' => 'save'] as $path => $name) {
            $body = $this->getBody($path, $name);
            $call = strpos($body, 'getUploadService()->addUploadedFile(');
            $this->assertNotFalse($call, $name.'() no longer publishes through the service');
            $head = substr($body, 0, $call);
            $this->assertMatchesRegularExpression("#posttype\s*==?=?\s*'save'#", $head, $name.'() publishes without first proving the request is a save');
        }
    }

    # A refused token stops the whole handler and not only its save branch, and the delete it dispatches validates the request on its own rather than trusting its caller
    # Every state-changing route of the module authorizes through checkAdminPost(), so the method is proven together with the token and no action of it travels as an address
    #[Test]
    public function aRefusedTokenReachesNoDeleteBranch(): void
    {
        $body = $this->getBody('modules/files/admin/index.php', 'save');
        $note = 'save() dispatches a delete without proving its token first, so a request with no token deletes the file and its rows';
        $this->assertStringContainsString("!\$iswarn && \$posttype === 'delete'", $body, $note);
        $code = $this->getFile('modules/files/admin/index.php');
        $this->assertStringNotContainsString('checkSiteToken(', $code, 'A handler of this module still authorizes without proving the request method');
        foreach (['save', 'delete', 'approve', 'configsave'] as $name) {
            $body = $this->getBody('modules/files/admin/index.php', $name);
            $this->assertStringContainsString("!checkAdminPost('files')", $body, $name.'() does not authorize through the scoped POST check');
        }
    }

    # Every adapter that writes a row checks the result and compensates the exact path it just published, and logs a compensation that itself failed
    #[Test]
    public function everyDatabaseWriteIsCheckedAndCompensated(): void
    {
        foreach (['modules/account/index.php' => 'saveavatar', 'modules/files/index.php' => 'send', 'modules/files/admin/index.php' => 'save'] as $path => $name) {
            $body = $this->getBody($path, $name);
            $this->assertStringContainsString('deleteStoredFile(', $body, $name.'() does not compensate a failed row write');
            $this->assertStringContainsString('Logger::addFile(', $body, $name.'() does not log a compensation that failed');
            $this->assertMatchesRegularExpression('#!\$?[a-z]*(->getSqlQuery|done)#', $body, $name.'() does not test the result of its own write');
        }
        $body = $this->getBody('modules/files/admin/index.php', 'save');
        $note = 'The update branch does not prove its target row exists: a successful statement over no row would pass as a written row and leave the file behind';
        $this->assertStringContainsString("SELECT id FROM '.PREFIX_DB.'_files WHERE id = :id", $body, $note);
        $note = 'The update that follows a publication does not count its rows, so a target removed between the check and the write would still strand the file';
        $this->assertStringContainsString("\$rpath !== '' && \$db->getSqlRowCount(\$done) < 1", $body, $note);
    }

    # The admin files handler must not publish and then move: the relocation branch may only run when no file was submitted
    #[Test]
    public function theAdminFilesHandlerNeverPublishesAndThenMoves(): void
    {
        $body = $this->getBody('modules/files/admin/index.php', 'save');
        $move = strpos($body, 'rename(');
        $this->assertNotFalse($move, 'The relocation branch is gone, so a stored file can no longer be moved');
        $head = substr($body, 0, $move);
        $this->assertStringContainsString('!$sent', $head, 'The relocation branch is not guarded against a request that just published a file');
    }

    # Without a decoder the decode invariant cannot be satisfied, so the class must answer unsupported rather than pass the file through on its header alone
    # The branch is unreachable on a build that has every decoder, which is why it is held at the source: a silent return to true is exactly the defect this guards
    #[Test]
    public function aMissingImageDecoderFailsClosed(): void
    {
        $body = $this->getBody('core/classes/upload.php', 'getDecodeError');
        $this->assertStringContainsString("!function_exists(\$call)) return 'unsupported'", $body, 'A missing decoder no longer fails closed');
        $this->assertStringNotContainsString('return true', $body, 'The decode check can still answer true without decoding anything');
        $this->assertStringContainsString('"ext-gd"', $this->getFile('composer.json'), 'ext-gd is not a require, so a build without it would refuse every image');
        $data = $this->getProbe('service');
        $this->assertSame([], $data['nodecoder'], 'This build has no decoder for '.implode(', ', $data['nodecoder']).', so the fail closed branch is live here');
    }

    # A value whose line would pass the limit is written as a concatenation; the shipped artifact proves it and the writer source keeps the branch that produced it
    # The byte-for-byte round trip of a real save belongs to tools/upload-route-check.php: a unit test may not write into the configuration directory of the site
    #[Test]
    public function theConfigurationFilesKeepEveryLineInsideTheLimit(): void
    {
        foreach (['config/filetype.php', 'config/uploads.php', 'config/files.php', 'config/users.php'] as $path) {
            foreach (explode("\n", $this->getFile($path)) as $num => $line) {
                $this->assertLessThanOrEqual(180, strlen(rtrim($line, "\r")), $path.' line '.($num + 1).' passes the 180 character limit');
            }
        }
        $body = $this->getBody('core/system.php', 'setConfigFile');
        $this->assertStringContainsString('$wrap = function', $body, 'The writer no longer wraps a value that would pass the line limit');
        $this->assertStringContainsString("\$ind.'    .'", $body, 'The wrap no longer emits a concatenation the next save can reproduce');
        $this->assertStringContainsString('$wrap = function', $this->getFile('setup/index.php'), 'The installer writer would produce files the panel writer would not');
        $fty = $this->getFile('config/filetype.php');
        $this->assertStringContainsString("'\n            .'", $fty, 'No shipped template is wrapped, so the limit is met by accident rather than by the writer');
    }

    # The generic upload fallback of the editor entry is closed: an unknown operation answers 400 instead of publishing anything
    #[Test]
    public function theEditorEntryHasNoGenericUploadFallback(): void
    {
        $code = $this->getFile('index.php');
        $from = strpos($code, 'elseif ($go == 4)');
        $this->assertNotFalse($from, 'The editor entry is gone');
        $body = substr($code, $from, (int)strpos($code, 'elseif ($go == 5)', $from) - $from);
        $this->assertStringContainsString('http_response_code(400)', $body, 'An unknown editor operation no longer answers 400');
        $this->assertStringNotContainsString('upload(', $body, 'The editor entry still reaches a generic upload');
    }
}
