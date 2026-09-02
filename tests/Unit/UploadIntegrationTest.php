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
        'modules/account/index.php' => 'savehome',
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

    # docs/ARCHITECTURE.md, Upload Place Boundary: the two field places must answer exactly the arrays modules/files/index.php and modules/account/index.php assembled by hand before it
    # The claim is field for field and not shape only, because batches 7 and 8 change markup on the promise that the rule behind it did not move
    #[Test]
    public function thePlaceResolverAnswersWhatTheModulesAssembledByHand(): void
    {
        $data = $this->getProbe('place');
        $this->assertSame([], $data['hand']['files.dist'], 'files.dist no longer answers what modules/files/index.php built by hand');
        $this->assertSame([], $data['hand']['users.avatar'], 'users.avatar no longer answers what modules/account/index.php built by hand');
    }

    # An attachment place is getUploadRuleData() and never a second reading of the same configuration, so every key that helper answers survives the resolver unchanged
    #[Test]
    public function anAttachmentPlaceIsTheModuleRuleItself(): void
    {
        $data = $this->getProbe('place');
        $this->assertSame([], $data['attach'], 'news.attach no longer equals getUploadRuleData(news)');
        $rule = $data['rules']['news.attach'];
        $this->assertSame('news', $rule['mod'], 'An attachment place lost the module it belongs to');
        $this->assertSame('news', $rule['store'], 'An attachment place no longer stores into the directory of its own module');
        $this->assertTrue($rule['canlink'], 'An attachment place refuses an address, which the editor has always accepted');
        $this->assertSame(['editorUpload', 'editorFiles', 'editorDelete', 'editorArchive'], $rule['ops'], 'An attachment place no longer permits all four editor routes');
    }

    # The grammar lives in the resolver alone, so everything that is not one module and one slot separated by one dot is refused before a configuration is read
    #[Test]
    public function everyMalformedPlaceIsRefused(): void
    {
        $data = $this->getProbe('place');
        foreach ($data['bad'] as $place => $okay) {
            $this->assertFalse($okay, 'The place '.($place === '' ? 'an empty string' : $place).' resolved successfully');
        }
        $this->assertArrayHasKey('Files.Dist', $data['bad'], 'The uppercase probe is gone, so the grammar is no longer proven case sensitive');
        $this->assertArrayHasKey('files.dist.x', $data['bad'], 'The three segment probe is gone, so the grammar is no longer proven to hold one dot');
    }

    # The access matrix of the two field places is the load bearing half of the batch: the module they are moderated as, the caps they list under and the routes they permit
    # A field place uploads through its own form, so it permits the listing route alone; leaving the other three reachable is what would create orphaned files
    #[Test]
    public function theAccessMatrixOfTheTwoFieldPlacesHolds(): void
    {
        $data = $this->getProbe('place');
        $dist = $data['rules']['files.dist'];
        $avat = $data['rules']['users.avatar'];
        $this->assertSame('files', $dist['mod'], 'files.dist is no longer moderated as the files module');
        $this->assertSame('account', $avat['mod'], 'users.avatar is moderated as the first segment of its own name instead of as account');
        $this->assertSame(['editorFiles'], $dist['ops'], 'files.dist permits a route its window never offers');
        $this->assertSame(['editorFiles'], $avat['ops'], 'users.avatar permits a route its window never offers');
        $this->assertTrue($dist['canlink'], 'files.dist no longer accepts an address, which the catalogue has always taken');
        $this->assertFalse($avat['canlink'], 'users.avatar accepts an address, which resolves against no avatar directory');
        $this->assertSame(0, $avat['guestupload'], 'A guest may upload an avatar, and a guest owns no account to upload one into');
        $this->assertSame(0, $avat['guestfiles'], 'A guest is offered a listing of avatars, and a guest owns none');
        foreach (['files.dist' => $dist, 'users.avatar' => $avat] as $place => $rule) {
            $this->assertSame(250, $rule['moderfiles'], 'The moderator cap of '.$place.' is not the shipped one of config/uploads.php');
            $this->assertSame(100, $rule['userfiles'], 'The member cap of '.$place.' is not the shipped one of config/uploads.php');
            $this->assertSame(1, $rule['maxfiles'], 'A field place takes more than one file, and nothing outside the editor offers a batch');
            $this->assertSame(0, $rule['maxquota'], 'A field place carries a quota, so its window would draw a bar over a number nobody set');
        }
    }

    # The upload right of files.dist is two settings and not one: add opens the form and upload only the row inside it, so a member may not upload where adding is switched off
    #[Test]
    public function theCatalogueUploadRightIsBothSettings(): void
    {
        $data = $this->getProbe('place');
        $dist = $data['rules']['files.dist'];
        $more = $data['rights'];
        $user = ($more['upload'] === 1 && $more['add'] === 1) ? 1 : 0;
        $lost = ($more['upload'] === 1 && $more['addquest'] === 1) ? 1 : 0;
        $this->assertSame($user, $dist['userupload'], 'The member upload right of files.dist is not upload and add together');
        $this->assertSame($lost, $dist['guestupload'], 'The guest upload right of files.dist is not upload and addquest together');
        $this->assertSame(($more['aupload'] === 1) ? 1 : 0, $data['rules']['users.avatar']['userupload'], 'The member upload right of users.avatar is not aupload');
        $shut = $data['locked'];
        $this->assertSame(0, $shut['add'], 'A member may upload a catalogue file into a module where adding is switched off');
        $this->assertSame(0, $shut['addquest'], 'A guest may upload a catalogue file where guest adding is switched off');
        $this->assertSame([0, 0], $shut['upload'], 'The upload switch alone no longer closes both upload rights of files.dist');
        $this->assertSame(0, $shut['aupload'], 'An avatar may be uploaded where the avatar upload switch is off');
    }

    # The directory of a place is the configured one in the three forms its readers need, and which one files.dist means is a role question the resolver answers alone
    #[Test]
    public function theDirectoryOfAPlaceIsTheConfiguredOne(): void
    {
        $data = $this->getProbe('place');
        $dist = $data['rules']['files.dist'];
        $avat = $data['rules']['users.avatar'];
        $this->assertSame($data['dirs']['files.dist'], $dist['dir'], 'A visitor no longer uploads a catalogue file into the temporary directory');
        $this->assertNotSame($data['dirs']['files.public'], $dist['dir'], 'A visitor uploads a catalogue file straight into the public directory');
        $this->assertSame($data['dirs']['users.avatar'], $avat['dir'], 'An avatar no longer lands in the configured avatar directory');
        foreach (['files.dist' => $dist, 'users.avatar' => $avat] as $place => $rule) {
            $this->assertSame('uploads/'.$rule['store'], $rule['dir'], 'The two relative directory forms of '.$place.' disagree');
            $this->assertStringEndsWith('/'.$rule['store'], $rule['path'], 'The absolute directory of '.$place.' is not the relative one below the upload root');
        }
    }

    # docs/ARCHITECTURE.md, Upload Place Boundary: every editor route travels on the place and no longer on the module, because filterVar() empties a string carrying a dot
    # The entry guard of index.php is the load bearing half: a request the branch drops never reaches a case at all, so a guard written in a route below it would never run
    #[Test]
    public function everyEditorRouteTravelsOnThePlace(): void
    {
        $rule = $this->getBody('core/system.php', 'getEditorRouteRule');
        $this->assertStringContainsString("getVar('get', 'place', 'raw', '')", $rule, 'The shared guard reads the place through a filter that empties a dot');
        $this->assertStringContainsString('getUploadPlaceRule(', $rule, 'The shared guard resolves something other than the place rule');
        $this->assertStringNotContainsString("getVar('get', 'mod'", $rule, 'The shared guard still reads the module parameter, which cannot carry a place at all');
        $code = $this->getFile('index.php');
        $from = strpos($code, '$go == 4');
        $stop = strpos($code, '$go == 5');
        $this->assertNotFalse($from, 'The editor branch of the front controller is gone');
        $this->assertNotFalse($stop, 'The branch after the editor one is gone, so the slice below would run past the routes it claims about');
        $part = substr($code, $from, $stop - $from);
        $this->assertStringContainsString("getVar('get', 'place', 'raw', '')", $part, 'The entry guard reads no place, so a request carrying one dies before any route');
        $this->assertStringNotContainsString("getVar('get', 'mod'", $part, 'The entry guard of the editor branch still demands a module, which no endpoint URL carries any more');
    }

    # The ops gate is the security assertion of the batch: a field place uploads through its own form, so the three routes its window never offers are refused by the server
    # It stands in the shared guard and before the two guards that answer for the visitor, so no route restates it and none can ship with it quietly missing
    #[Test]
    public function aPlaceIsRefusedTheRouteItDoesNotPermit(): void
    {
        $rule = $this->getBody('core/system.php', 'getEditorRouteRule');
        $gate = strpos($rule, "in_array(getVar('req', 'op', 'var', ''), \$rul['ops'], true)");
        $this->assertNotFalse($gate, 'The shared guard never asks whether the place permits the route that was dispatched');
        $this->assertStringContainsString("\$rul['ops'], true)) getEditorJson(['ok' => false, 'error' => _ACCESSDENIED])", $rule, 'The ops gate refuses in words of its own');
        $this->assertLessThan(strpos($rule, 'checkEditorUploadAccess('), $gate, 'The ops gate runs after the right of the visitor and not before it');
        $this->assertLessThan(strpos($rule, 'checkSiteToken('), $gate, 'The ops gate runs after the token, so a valid token decides a route the place never permitted');
        $data = $this->getProbe('place');
        foreach (['files.dist', 'users.avatar'] as $place) {
            $ops = $data['rules'][$place]['ops'];
            $this->assertNotContains('editorUpload', $ops, $place.' permits the upload route, so the gate above would let a file in beside its own form');
            $this->assertNotContains('editorDelete', $ops, $place.' permits the deletion route, whose window offers no button for it');
            $this->assertNotContains('editorArchive', $ops, $place.' permits the packing route, whose window offers no button for it');
        }
    }

    # Both ends of the contract migrate together: the four endpoint URLs are built beside the editor and carry the attachment place of the module instead of its name
    #[Test]
    public function everyEndpointUrlCarriesThePlace(): void
    {
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringContainsString(".'.attach'", $drv, 'The editor no longer names the attachment place of its module, so its URLs carry nothing the routes can resolve');
        foreach (['editorUpload', 'editorFiles', 'editorDelete', 'editorArchive'] as $op) {
            $this->assertStringContainsString('op='.$op.'&place=', $drv, 'The endpoint URL of '.$op.' does not carry a place');
        }
        $this->assertStringNotContainsString('&mod=', $drv, 'An endpoint URL still carries the module parameter, which the front controller no longer reads');
    }

    # The file context is built from the place rule alone, which already carries the directory and the module it is moderated as, so no caller hands the same place down beside it
    #[Test]
    public function theFileContextIsBuiltFromThePlaceRule(): void
    {
        $code = $this->getFile('core/system.php');
        $this->assertSame(1, substr_count($code, 'function getUploadFileArea('), 'The file context of an upload place is built somewhere other than the one resolver');
        $this->assertStringNotContainsString('getEditorFileArea', $code, 'The former module context survives beside the place one, so two readings of the same directory exist');
        $area = $this->getBody('core/system.php', 'getUploadFileArea');
        $this->assertStringContainsString("\$rule['mod']", $area, 'The context works out the module beside the rule that already answers it');
        $this->assertStringContainsString("(string)\$rule['path']", $area, 'The context opens a root other than the directory of the place');
        foreach (['addEditorUpload', 'getEditorFileJson', 'setEditorFileRun'] as $name) {
            $this->assertStringContainsString('getUploadFileArea($rul)', $this->getBody('core/system.php', $name), $name.'() builds its context on something else');
        }
    }

    # A path handed in by a form is resolved in one place for both handlers, because the two questions it answers - does the file exist in this place, does it belong to whoever is posting -
    # were written twice in words that differed, and the copy that drifts is the one that stops asking. The wording of the refusal stays with each caller; the refusal itself does not
    #[Test]
    public function theTakenPathIsResolvedInOnePlace(): void
    {
        $code = $this->getFile('core/system.php');
        $this->assertSame(1, substr_count($code, 'function getUploadTakenFile('), 'The stored pick of a form is resolved somewhere other than the one resolver');
        $body = $this->getBody('core/system.php', 'getUploadTakenFile');
        $this->assertStringContainsString('getUploadFileArea($rule)->getFileData($take)', $body, 'The path is read past the place context, so a name reaching outside the directory would answer');
        $this->assertStringContainsString("\$one['kind'] === 'dir'", $body, 'A directory answers as a file, so a pick could name the store itself');
        $this->assertStringContainsString("'index.html', '.htaccess'", $body, 'The two names that are never content are offered as content');
        $this->assertStringContainsString('FileManager::getFileOwner(', $body, 'A stored file is taken on the word of the client, which is the one thing a path from a form may never be');
        $this->assertStringContainsString('getEditorFileOwner($mod)', $body, 'The resolver writes an owner of its own, so its answer and the listing disagree about whose file it is');
        $this->assertStringContainsString('!is_moder($mod)', $body, 'The ownership test excuses nobody, or excuses more than the module moderator the deletion route already excuses');
        $this->assertLessThan(strpos($body, 'is_moder('), strpos($body, "'gone'"), 'The role is consulted before the file is known to exist, so the moderator would be excused the existence test as well');
        foreach (['modules/files/index.php' => 'send', 'modules/account/index.php' => 'savehome'] as $path => $name) {
            $this->assertStringContainsString('getUploadTakenFile(', $this->getBody($path, $name), $path.' resolves a stored pick on its own again');
        }
    }

    # The upload route stores into the directory the place named and never into a module directory it derived from the name, which is what lets a field place point anywhere
    #[Test]
    public function theUploadRouteStoresIntoTheDirectoryOfThePlace(): void
    {
        $body = $this->getBody('core/system.php', 'addEditorUpload');
        $this->assertStringContainsString("(string)\$rul['store']", $body, 'The upload route names its destination itself instead of taking the one the place resolved');
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
        foreach (['modules/account/index.php' => 'savehome', 'modules/files/index.php' => 'send', 'modules/files/admin/index.php' => 'save'] as $path => $name) {
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

    # The catalogue asks for a file through the one door: the form keeps no file field and no address row of its own, and what the window handed back rides the ordinary submit
    # The three outcomes are read in a fixed defensive order - the upload, then the stored file, then the address - because the window cannot hand back two and a leftover of one must never answer for another
    # A path arrives from the client and is never taken on trust: the file layer answers whether it exists in the place, and the owner token answers whether it belongs to whoever is posting
    #[Test]
    public function theCatalogueAsksForAFileThroughTheOneDoor(): void
    {
        $form = $this->getBody('modules/files/index.php', 'add');
        $this->assertStringContainsString('getFileManagerField([', $form, 'The catalogue form carries no door, so the window is reachable from an editor alone again');
        $this->assertStringContainsString("'place' => 'files.dist'", $form, 'The door of the catalogue opens on something other than the place of the distributed file');
        $this->assertStringNotContainsString("getHtmlFrag('file-input'", $form, 'The catalogue still prints a bare file field beside the door, so two ways of adding a file stand in one form');
        $this->assertStringNotContainsString("'input_id' => 'f-url'", $form, 'The address row is still typed into the form, which the window now owns');
        $body = $this->getBody('modules/files/index.php', 'send');
        $up = strpos($body, 'addUploadedFile(');
        $take = strpos($body, 'getUploadTakenFile(');
        $this->assertNotFalse($up, 'The catalogue no longer publishes an upload at all');
        $this->assertNotFalse($take, 'The catalogue never resolves a stored file, so a pick from the storage reaches the database unchecked');
        $this->assertLessThan($take, $up, 'The stored file is read before the upload, so a leftover path would answer for a file the visitor just chose');
        $this->assertStringNotContainsString('FileManager::getFileOwner(', $body, 'The catalogue tests ownership in words of its own beside the one resolver, and a guard written twice is a guard that drifts');
        $this->assertStringContainsString('getEditorFileOwner(', $body, 'The catalogue writes an owner of its own again, so its names and the listing disagree about whose file it is');
        $this->assertStringNotContainsString('(int)$user[0]', substr($body, 0, $up), 'The owner is cast to an integer again, which turns every guest token into zero and matches one guest against another');
        $this->assertStringContainsString('checkEditorUploadAccess(', $body, 'The handler decides the upload right beside the one gate that answers it for every other place');
    }

    # The avatar asks for a file through the same door, and the row that used to carry a bare file field is the only thing in its tile that changed
    # A preset is picked by a click and beats anything the window produced, so the arbitration reads it first and the two window outcomes after it in a fixed order
    # The place keeps a file name resolved against the avatar directory, so the stored arm writes the name and never the address the catalogue writes out of the same shape
    #[Test]
    public function theAvatarAsksForAFileThroughTheOneDoor(): void
    {
        $form = $this->getBody('modules/account/index.php', 'edithome');
        $this->assertStringContainsString('getFileManagerField([', $form, 'The avatar tile carries no door, so the window is reachable from an editor alone again');
        $this->assertStringContainsString("'place' => 'users.avatar'", $form, 'The door of the avatar opens on something other than the place of the avatar');
        $this->assertStringNotContainsString("getHtmlFrag('file-input'", $form, 'The avatar tile still prints a bare file field beside the door');
        $this->assertStringNotContainsString("'url' => ", $form, 'The avatar door is handed an address field, which resolves against no avatar directory');
        $this->assertStringNotContainsString("\$conf['users']['aupload']", $form, 'The form decides the upload right off a configuration key again instead of off the one gate');
        $body = $this->getBody('modules/account/index.php', 'savehome');
        $pre = strpos($body, "getVar('post', 'avatar'");
        $up = strpos($body, 'addUploadedFile(');
        $take = strpos($body, 'getUploadTakenFile(');
        $this->assertNotFalse($pre, 'The preset arm is gone, so a gallery click reaches no arbitration at all');
        $this->assertNotFalse($up, 'The avatar handler no longer publishes an upload at all');
        $this->assertNotFalse($take, 'The avatar handler never resolves a stored file, so a pick from the storage reaches the profile unchecked');
        $this->assertLessThan($up, $pre, 'The upload is read before the preset, so a leftover file beats a gallery click the member just made');
        $this->assertLessThan($take, $up, 'The stored file is read before the upload, so a leftover path would answer for a file the member just chose');
        $this->assertStringNotContainsString('FileManager::getFileOwner(', $body, 'The avatar tests ownership in words of its own beside the one resolver');
        $this->assertStringContainsString('getEditorFileOwner(', $body, 'The avatar writes an owner of its own, so its names and the listing disagree');
        $this->assertStringNotContainsString("\$rule['mod'], \$uid)", $body, 'The owner is the numeric identifier again instead of the token every other place stores');
        $this->assertStringContainsString('checkEditorUploadAccess(', $body, 'The handler decides the upload right beside the one gate that answers it for every other place');
        $this->assertStringNotContainsString("\$conf['users']['aupload']", $body, 'The handler decides the upload right off a configuration key again instead of off the one gate');
    }

    # The profile write carries the avatar now, so the two guards that stood in front of the route it replaced stand in front of it: a member, and a POST
    # Both are read before the token, because a token proves a session and never an identity, and a visitor holding one would otherwise reach the write itself
    #[Test]
    public function theProfileWriteIsClosedToAVisitorAndToAGet(): void
    {
        $body = $this->getBody('modules/account/index.php', 'savehome');
        $user = strpos($body, 'is_user()');
        $post = strpos($body, 'REQUEST_METHOD');
        $tok = strpos($body, 'checkSiteToken(');
        $this->assertNotFalse($user, 'A signed out visitor reaches the profile write, where the member of the row is read off an identity that was never proven');
        $this->assertNotFalse($post, 'The profile write answers a GET, so an address alone changes an account');
        $this->assertNotFalse($tok, 'The profile write asks for no token at all');
        $this->assertLessThan($tok, $user, 'The token is checked before the visitor, so a session with no member behind it reaches further than it should');
        $this->assertLessThan($tok, $post, 'The token is checked before the method, so a GET reaches further than it should');
    }
}
