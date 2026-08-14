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
        'admin/modules/uploads.php' => 'fmupload',
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

    # Every module record of the shipped configuration survives resolve and serialize with every named field present
    # A rule one field short normalises on the first write: the key order is completed, every value the rule carried is kept, and every write after that reproduces the string
    #[Test]
    public function everyShippedRecordRoundTripsThroughTheResolver(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertNotEmpty($data['records'], 'The shipped configuration holds no module record at all');
        foreach ($data['records'] as $mod => $row) {
            $this->assertSame(count($data['keys']), $row['fields'], 'The normalised record '.$mod.' does not carry one field per named key');
            $this->assertTrue($row['kept'], 'Normalising the record '.$mod.' changed a value it already carried');
            $this->assertTrue($row['stable'], 'setUploadRuleData(getUploadRuleData('.$mod.')) does not reproduce a normalised string');
            $this->assertTrue($row['ok'], 'The record '.$mod.' resolves to a directory that does not exist');
        }
    }

    # The guest limit falls back to the user one when its position is absent, because zero means no limit and would hand an unbounded list to the one role that never had one
    #[Test]
    public function aRuleWithoutTheAppendedKeyFallsBackToTheUserLimit(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertSame(8, $data['old']['userfiles'], 'A rule written short lost its user limit');
        $this->assertSame($data['old']['userfiles'], $data['old']['guestfiles'], 'A rule without the guest limit did not fall back to the user limit');
    }

    # An unknown key fails closed and never quietly hands back the general record, which is what would turn any directory into an editor upload target
    #[Test]
    public function anUnknownKeyNeverResolvesTheGeneralRecord(): void
    {
        $data = $this->getProbe('resolver');
        $this->assertFalse($data['unknown']['ok'], 'An unknown module resolved successfully');
        $this->assertSame('', $data['unknown']['ext'], 'An unknown module inherited an extension list');
        $this->assertNotSame($data['allext'], $data['unknown']['ext'], 'An unknown module was answered with the all record');
        $this->assertSame(count($data['keys']), $data['unknown']['keys'], 'A failed resolution drops named fields, so a caller reading one limit would fatal');
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

    # The accessor builds one instance over the upload root and is the only place that names it; the lock directory belongs to the protocol and is named by FileManager alone
    #[Test]
    public function theServiceIsBuiltOnceOverTheDocumentedPaths(): void
    {
        $data = $this->getProbe('service');
        $this->assertTrue($data['same'], 'getUploadService() built a second instance');
        $this->assertSame($data['wantroot'], $data['root'], 'The service was not built on UPLOADS_DIR');
        $this->assertNotContains('lockdir', $data['fields'], 'The upload service holds a lock directory of its own again');
        $this->assertSame($data['wantlock'], $data['held'], 'The lock of the file layer is not one file named after the directory it guards');
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
            'core/system.php' => 'getEditorRouteRule(',
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
        # The editor routes read their rule through one guard, so the token check is asserted where it now stands and not where every route used to repeat it
        $rule = $this->getBody('core/system.php', 'getEditorRouteRule');
        $this->assertStringContainsString('checkSiteToken(', $rule, 'The shared guard of the editor routes carries no token check at all');
        $this->assertStringContainsString('checkEditorUploadAccess(', $rule, 'The shared guard of the editor routes decides nothing about access');
    }

    # Both editor routes take the owner from the one resolver, and the listing compares it as a string
    # An integer cast is the trap the widening carries: it turns every token into zero, the value a guest carried before, so every guest would match every other guest
    # The resolver hands a guest a token derived from the session and never the session id itself, because the segment ends up in a public file name
    #[Test]
    public function theOwnerTokenIsResolvedInOnePlaceAndComparedAsAString(): void
    {
        $code = $this->getFile('core/system.php');
        $body = $this->getBody('core/system.php', 'getEditorFileOwner');
        $this->assertStringContainsString('hash_hmac(', $body, 'The guest owner is not derived, so the stored name would carry the session itself');
        $this->assertStringNotContainsString('return session_id()', $body, 'The guest owner is the session id, which a public file name must never carry');
        $this->assertStringNotContainsString("'upload|'.session_id()", $body, 'An empty session id is derived instead of refused, so every guest would share one token');
        $this->assertSame(1, substr_count($code, 'function getEditorFileOwner('), 'The owner resolver exists more than once');
        foreach (['addEditorUpload', 'getEditorFileJson'] as $name) {
            $this->assertStringContainsString('getEditorFileOwner(', $this->getBody('core/system.php', $name), $name.'() decides ownership on its own');
        }
        $list = $this->getBody('core/system.php', 'getEditorFileJson');
        $read = 'The route no longer reads the owner off the file layer and takes the stored name apart again';
        $this->assertStringContainsString("FileManager::getFileOwner(\$one['name']) !== \$tok", $list, $read);
        $this->assertStringNotContainsString('preg_match(', $list, 'The route carries a pattern of its own beside the one of the file layer');
        $cast = 'The owner is compared as an integer, so every guest token collapses to zero and matches every other guest';
        $this->assertDoesNotMatchRegularExpression('#\(int\) *(?:\$tok|FileManager::getFileOwner)#', $list, $cast);
        $note = 'The ownership pattern matches digits only again, so a guest token never resolves';
        $this->assertMatchesRegularExpression('#\(\[a-zA-Z0-9\]\+\)#', $this->getFile('core/classes/filemanager.php'), $note);
    }

    # The listing route carries no access rule of its own: a guest is answered by checkEditorUploadAccess() like every other visitor, and the owner token is what narrows the list
    # One role question is left on it, which of the three limits applies, and each of the three is a setting rather than a number the route decides for a role
    # The membership test is held to one appearance and to that one line, because a guard reintroduced in any other wording is the same defect this batch removed
    #[Test]
    public function theListingRouteLeavesAccessToTheOneGate(): void
    {
        $body = $this->getBody('core/system.php', 'getEditorFileJson');
        $this->assertStringContainsString('getEditorRouteRule(', $body, 'The listing route no longer passes the one guard that decides access');
        $this->assertSame(1, substr_count($body, 'is_user('), 'The route tests membership beside the limit choice, which is a role rule beside the settings');
        $this->assertMatchesRegularExpression('#\$lim = .*is_user\(\).*guestfiles#', $body, 'Membership no longer chooses which of the three limits applies');
        $this->assertSame(1, substr_count($body, "'ok' => true"), 'The route answers a list twice, so one of them is an early answer beside the settings');
        foreach (['moderfiles', 'userfiles', 'guestfiles'] as $key) {
            $this->assertStringContainsString("\$rul['".$key."']", $body, 'The listing limit never reads '.$key.', so one role is bounded by the limit of another');
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
        $this->assertStringContainsString('!function_exists($call))', $body, 'The decode check no longer asks whether the decoder of this type exists');
        $this->assertStringContainsString("addCapsNote('decoder_missing')", $body, 'A missing decoder is no longer recorded as the server capability it is');
        $this->assertStringContainsString("return 'unsupported';", $body, 'A missing decoder no longer fails closed');
        $this->assertStringNotContainsString('return true', $body, 'The decode check can still answer true without decoding anything');
        $this->assertStringContainsString('"ext-gd"', $this->getFile('composer.json'), 'ext-gd is not a require, so a build without it would refuse every image');
        $data = $this->getProbe('service');
        $this->assertSame([], $data['nodecoder'], 'This build has no decoder for '.implode(', ', $data['nodecoder']).', so the fail closed branch is live here');
    }

    # One stored value is one line however long it is: a configuration file is data, and the line limit of .rules/global.md governs the code a person writes
    # The byte-for-byte round trip of a real save belongs to tools/upload-route-check.php: a unit test may not write into the configuration directory of the site
    #[Test]
    public function theConfigurationWritersKeepOneValueOnOneLine(): void
    {
        $body = $this->getBody('core/system.php', 'setConfigFile');
        $this->assertStringNotContainsString('$wrap', $body, 'The writer splits a long value across lines again');
        $this->assertStringNotContainsString("\$ind.'    .'", $body, 'The writer emits a concatenation again');
        $this->assertStringNotContainsString('$wrap', $this->getFile('setup/index.php'), 'The installer writer would produce files the panel writer would not');
        foreach (['config/filetype.php', 'config/uploads.php', 'config/files.php', 'config/users.php'] as $path) {
            $this->assertDoesNotMatchRegularExpression("#\n\s+\.'#", $this->getFile($path), $path.' still carries a value split across lines');
        }
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
