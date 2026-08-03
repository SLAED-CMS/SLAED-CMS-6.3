<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 3 of docs/BACKUP-2026.md: the half no double can answer. tests/Support/backup_probe.php boots
 * the real core in an isolated CLI process, creates a disposable schema with the shapes that matter -
 * a keyed table, one without a primary key holding duplicate rows, a stored generated column, a
 * composite key, binary and quoted values - and drives Backup against it through a scratch backup
 * root. Every scenario drops its schema again and reports whether it left staging behind. The site
 * database and storage/backup are never touched.
 */
final class BackupIntegrationTest extends TestCase
{
    private static array $probe = [];

    # Run one probe scenario in a fresh process and memoize its report for every test in this class
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $script = dirname(__DIR__).'/Support/backup_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_backup_probe_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # No scenario may leave its disposable schema or a staging directory behind
    #[Test]
    public function everyScenarioCleansUpAfterItself(): void
    {
        foreach (['export', 'compress', 'publish', 'fail', 'scope', 'restore'] as $mode) {
            $this->assertTrue($this->getProbe($mode)['clean'], $mode.' left its schema or a staging directory behind');
        }
    }

    # A run against a live server produces one artifact whose checksum is the checksum of the file on disk
    #[Test]
    public function aRunPublishesOneVerifiedArtifact(): void
    {
        $data = $this->getProbe('export');
        $this->assertSame('success', $data['status'], $data['message']);
        $this->assertTrue($data['exists'], 'The result named an artifact that does not exist');
        $this->assertTrue($data['same'], 'The published artifact does not match the checksum the result reports');
        $this->assertSame([], $data['stage'], 'Staging survived a successful run');
        $this->assertCount(1, $data['files']);
        $this->assertSame(4, $data['extra']['table_count']);
        $this->assertSame(11, $data['extra']['row_count']);
    }

    # The shared connection is handed back exactly as it was found, including the buffering mode and an open transaction
    #[Test]
    public function theSharedConnectionIsRestored(): void
    {
        $data = $this->getProbe('export');
        $this->assertSame($data['before'], $data['after'], 'The export changed session state it did not restore');
        $this->assertTrue($data['after']['buffered'], 'Unbuffered mode survived the export');
        $this->assertSame(0, $data['after']['intrx'], 'The export left a transaction open');
    }

    # The dump carries its own prologue and epilogue, exports in UTC, and replaces the restoring SQL mode so backslash escapes read as written
    #[Test]
    public function theDumpBracketsItselfWithSessionState(): void
    {
        $sql = $this->getProbe('export')['sql'];
        $this->assertTrue($sql['prolog']);
        $this->assertTrue($sql['epilog']);
        $this->assertTrue($sql['utc']);
        $this->assertTrue($sql['modeset'], 'Without a fixed SQL mode a server with NO_BACKSLASH_ESCAPES would read the values differently');
    }

    # Values survive serialization: quotes, a semicolon inside data, negative numbers, NULL, binary with null bytes and an empty binary value
    #[Test]
    public function everyValueShapeIsSerializedCorrectly(): void
    {
        $sql = $this->getProbe('export')['sql'];
        $this->assertTrue($sql['quoted'], 'A quote inside a value was not escaped');
        $this->assertTrue($sql['semicolon'], 'A semicolon inside a value did not survive');
        $this->assertTrue($sql['binary'], 'Binary data was not written as hex');
        $this->assertTrue($sql['emptybin'], 'An empty binary value must stay an empty string, because 0x alone is not valid SQL');
        $this->assertTrue($sql['negative']);
        $this->assertTrue($sql['null']);
    }

    # A stored generated column is computed on restore and must not be inserted, and rows are read in full primary key order
    #[Test]
    public function generatedColumnsAreOmittedAndKeysOrderTheRows(): void
    {
        $sql = $this->getProbe('export')['sql'];
        $this->assertSame('`id`,`price`', $sql['gencol'], 'The generated column reached the INSERT column list');
        $this->assertSame('INSERTINTO`p_multi`(`one`,`two`,`txt`)VALUES(1,1,\'c\'),(1,2,\'a\'),(2,1,\'b\');', $sql['keyorder'],
            'Rows were not ordered by the full composite primary key');
    }

    # A table without a primary key is exported rather than rejected, and it is named in the result
    #[Test]
    public function aTableWithoutAPrimaryKeyIsReportedAsUnordered(): void
    {
        $this->assertSame(['p_nokey'], $this->getProbe('export')['extra']['unordered']);
    }

    # Every compressor produces an artifact that decompresses to exactly the SQL the result reports, verified outside the class as well
    #[Test]
    public function everyCompressorRoundTripsToTheSameSql(): void
    {
        $data = $this->getProbe('compress');
        $this->assertNotEmpty($data['runs'], 'No compressor was available to test');
        foreach ($data['runs'] as $algo => $run) {
            $this->assertSame('success', $run['status'], $algo.': '.$run['message']);
            $this->assertSame($algo, $run['format']);
            $this->assertStringEndsWith('.sql.'.$algo, $run['name'], 'The artifact name must always carry .sql');
            $this->assertTrue($run['plain'], $algo.' does not decompress to the dump it was made from');
            $this->assertSame([], $run['stage']);
        }
    }

    # Two runs never produce the same name and an existing artifact is never replaced
    #[Test]
    public function publicationNeverReplacesAnExistingArtifact(): void
    {
        $data = $this->getProbe('publish');
        $this->assertTrue($data['distinct'], 'Two runs produced the same artifact name');
        $this->assertTrue($data['kept'], 'The second run changed the artifact of the first');
        $this->assertSame(2, $data['files']);
    }

    # Publication rests on link() refusing an existing destination, which is the guarantee the 8 hex characters do not give
    #[Test]
    public function hardLinkingRefusesAnExistingDestination(): void
    {
        $data = $this->getProbe('publish');
        $this->assertTrue($data['linkrefused'], 'link() replaced an existing file on this filesystem');
        $this->assertTrue($data['targetkept'], 'The destination content changed although link() failed');
    }

    # A publication that cannot link gives up after a bounded number of attempts instead of looping or overwriting
    #[Test]
    public function aPublicationThatCannotLinkFailsAfterItsAttempts(): void
    {
        $data = $this->getProbe('publish');
        $this->assertSame('Backup could not publish the artifact after 5 attempts', $data['retry']);
        $this->assertTrue($data['candkept'], 'A failed publication removed the candidate it could not publish');
    }

    # The artifact is restricted before it gets a public name, so a candidate that cannot even be restricted never reaches link()
    #[Test]
    public function anUnrestrictableCandidateIsNeverPublished(): void
    {
        $this->assertSame('Backup could not restrict the artifact before publishing it', $this->getProbe('publish')['missing']);
    }

    # The fingerprint reacts to a schema change and ignores concurrent writes, so ordinary traffic never looks like drift
    #[Test]
    public function theFingerprintSeesDdlAndIgnoresDml(): void
    {
        $data = $this->getProbe('drift');
        $this->assertTrue($data['dml'], 'Concurrent inserts and deletes changed the fingerprint');
        $this->assertTrue($data['ddl'], 'An added column did not change the fingerprint');
    }

    # An ALTER issued by another process during a real export cannot corrupt the artifact: the snapshot holds and the dump carries the schema as it was
    #[Test]
    public function aConcurrentAlterCannotReachTheArtifact(): void
    {
        $race = $this->getProbe('drift')['race'];
        $this->assertTrue($race['altered'], 'The competing process never altered the table, so this scenario proved nothing');
        $this->assertGreaterThan(65000, $race['rows'], 'The export was too small to overlap the competing DDL');
        $this->assertTrue($race['snapshot'], 'The artifact carries a column that was added after the export began');
        $this->assertSame([], $race['stage']);
    }

    # A failure inside the export publishes nothing, removes staging, closes the transaction and restores the session
    #[Test]
    public function aFailedExportLeavesNothingBehind(): void
    {
        $data = $this->getProbe('fail');
        $this->assertSame('failed', $data['status']);
        $this->assertStringContainsString('MEMORY', $data['message']);
        $this->assertSame([], $data['files'], 'A failed run published an artifact');
        $this->assertSame([], $data['stage'], 'A failed run left staging behind');
        $this->assertTrue($data['session'], 'A failed run did not restore the session');
        $this->assertSame(0, $data['intrx'], 'A failed run left a transaction open');
    }

    # An engine the snapshot cannot cover is exported as structure only once the operator says so, and then carries no rows
    #[Test]
    public function aSchemaonlyEngineExportsItsStructureWithoutRows(): void
    {
        $data = $this->getProbe('fail')['schemaonly'];
        $this->assertSame('success', $data['status']);
        $this->assertTrue($data['created'], 'The structure of the schemaonly table is missing');
        $this->assertFalse($data['inserted'], 'A schemaonly table carried rows');
    }

    # Against a live catalog holding a view, a trigger and a routine, the strict default refuses to produce anything
    #[Test]
    public function anUnsupportedObjectStopsAStrictRunBeforeOutput(): void
    {
        $data = $this->getProbe('scope')['strict'];
        $this->assertSame('failed', $data['status']);
        $this->assertStringContainsString('views (p_view)', $data['message']);
        $this->assertStringContainsString('triggers (p_trg)', $data['message']);
        $this->assertStringContainsString('routines (p_helper)', $data['message']);
        $this->assertSame([], $data['files'], 'A strict run that failed still published an artifact');
    }

    # With the opt-in the same schema succeeds, records exactly what was skipped, and the artifact holds no view
    #[Test]
    public function anAcceptedIncompleteRunRecordsWhatItSkipped(): void
    {
        $data = $this->getProbe('scope');
        $this->assertSame('success', $data['open']['status']);
        $this->assertFalse($data['open']['complete']);
        $this->assertSame(['views' => ['p_view'], 'triggers' => ['p_trg'], 'routines' => ['p_helper']], $data['open']['unsupported']);
        $this->assertStringContainsString('incomplete by operator choice', $data['open']['message']);
        $this->assertFalse($data['viewout'], 'A view reached the artifact, which this class does not serialize');
    }

    # The spike case, now executable: an account with only schema SELECT reads an empty trigger and routine catalog although both exist, and must fail rather than certify absence
    #[Test]
    public function anEmptyCatalogIsNeverReadAsProofOfAbsence(): void
    {
        $data = $this->getProbe('grant');
        $gone = isset($data['select']['status']) && $data['select']['status'] === 'no connection';
        if ($gone) $this->markTestSkipped('The probe account could not connect: '.($data['select']['message'] ?? ''));
        $this->assertSame('failed', $data['select']['status']);
        $this->assertSame(0, $data['select']['catalog']['triggers'], 'The account saw a trigger, so this scenario proves nothing');
        $this->assertSame(0, $data['select']['catalog']['routines'], 'The account saw a routine, so this scenario proves nothing');
        $this->assertStringContainsString('triggers need TRIGGER', $data['select']['message']);
        $this->assertStringContainsString('events need EVENT', $data['select']['message']);
        $this->assertStringContainsString('routines need', $data['select']['message']);
    }

    # A privilege that exists only through a role the session has not activated is not a privilege the session has
    #[Test]
    public function anInactiveRoleDoesNotSatisfyTheContract(): void
    {
        $data = $this->getProbe('grant');
        if (!isset($data['inactive']['catalog'])) $this->markTestSkipped('The role fixtures were not created: '.($data['error'] ?? ''));
        $this->assertSame('failed', $data['inactive']['status']);
        $this->assertStringContainsString('triggers need TRIGGER', $data['inactive']['message']);
        $this->assertSame(0, $data['inactive']['catalog']['triggers']);
    }

    # Activating the role resolves the classes it grants - through a nested chain the server expands itself - and leaves exactly the class it does not grant
    #[Test]
    public function anActiveNestedRoleResolvesTheClassesItGrants(): void
    {
        $data = $this->getProbe('grant');
        if (!isset($data['active']['catalog'])) $this->markTestSkipped('The role fixtures were not created: '.($data['error'] ?? ''));
        $this->assertSame(1, $data['active']['catalog']['triggers'], 'The activated role did not make triggers visible');
        $this->assertStringNotContainsString('triggers need TRIGGER', $data['active']['message']);
        $this->assertStringNotContainsString('events need EVENT', $data['active']['message']);
        $this->assertStringContainsString('routines need', $data['active']['message'], 'Schema SELECT alone must not satisfy routines');
    }

    # With every class provable the run gets past preflight and stops on the strict completeness rule instead
    #[Test]
    public function aProvableAccountReachesTheCompletenessRule(): void
    {
        $data = $this->getProbe('grant');
        if (!isset($data['direct']['catalog'])) $this->markTestSkipped('The role fixtures were not created: '.($data['error'] ?? ''));
        $this->assertSame(1, $data['direct']['catalog']['routines']);
        $this->assertStringNotContainsString('cannot prove object visibility', $data['direct']['message']);
        $this->assertStringContainsString('does not serialize', $data['direct']['message']);
    }

    # The artifact restores into an empty database and gives back exactly what was exported, duplicates included
    #[Test]
    public function theArtifactRestoresIntoAnEmptyDatabase(): void
    {
        $data = $this->getProbe('restore');
        $this->assertTrue($data['restored'], 'The artifact did not apply: '.$data['error']);
        $this->assertTrue($data['identical'], 'The restored database differs from the exported one');
        $this->assertSame(3, $data['target']['p_nokey']['rows']);
        $this->assertContains(2, $data['target']['p_nokey']['mset'], 'The duplicate rows of the unordered table were collapsed');
    }

    # The restoring session is handed back with its own SQL mode, so applying a dump does not reconfigure the client
    #[Test]
    public function restoringDoesNotLeaveTheClientReconfigured(): void
    {
        $data = $this->getProbe('restore');
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $data['mode']);
        $this->assertStringNotContainsString('NO_AUTO_VALUE_ON_ZERO', $data['mode'], 'The dump epilogue did not restore the SQL mode of the restoring session');
    }

    # The release gate: a backup of the live site database, taken by the class and restored into an empty database, gives back the schema it was made from
    #[Test]
    public function theSiteArtifactRestoresIntoAWorkingDatabase(): void
    {
        $data = $this->getProbe('gate');
        $this->assertTrue($data['restored'], 'The site artifact did not apply: '.$data['error']);
        $this->assertSame($data['tables']['source'], $data['tables']['target'], 'The restored database has a different number of base tables');
        $this->assertTrue($data['names'], 'The restored database holds different table names');
        $this->assertSame([], $data['ddl'], 'Table definitions differ between the source and the restored database');
        $this->assertGreaterThan(1000, $data['tally'], 'The backup carried too few rows for this gate to mean anything');
    }

    # The rows themselves are compared, not just counted; a table may only differ because the running site wrote it after the snapshot was taken
    #[Test]
    public function theRestoredRowsAreIdenticalExceptWhereTheSiteKeptWriting(): void
    {
        $data = $this->getProbe('gate');
        $this->assertGreaterThan(30, $data['data']['compared'], 'Too few tables were compared for this to mean anything');
        foreach (array_merge($data['data']['differing'], $data['rows']) as $name) {
            $this->assertTrue($this->checkVolatile($name), $name.' differs from its backup and is not a table a live site rewrites on its own');
        }
    }

    # Tables a running site writes without anyone asking: sessions on every request, the mail queue on every notification, counters and statistics on every hit
    private function checkVolatile(string $name): bool
    {
        foreach (['_session', '_mail', '_stats', '_counter', '_online', '_visitors'] as $mark) {
            if (str_ends_with($name, $mark)) return true;
        }
        return false;
    }

    # The queries the core issues on a page view work against the restored database, which is what "restorable" has to mean
    #[Test]
    public function theCoreQueriesWorkAgainstTheRestoredDatabase(): void
    {
        foreach ($this->getProbe('gate')['smoke'] as $name => $rows) {
            $this->assertIsInt($rows, $name.' failed against the restored database: '.$rows);
        }
    }

    # The dispatcher runs the class and the procedural implementation is gone, with no alias or wrapper left behind
    #[Test]
    public function theDispatcherRunsTheClassAndNothingElse(): void
    {
        $core = (string)file_get_contents(dirname(__DIR__, 2).'/core/system.php');
        $this->assertStringNotContainsString('function addBackupTask', $core, 'The procedural backup implementation is still there');
        $this->assertStringContainsString("'backup' => addBackupJob()", $core, 'The dispatcher does not run the class path');
        $this->assertStringContainsString("require_once BASE_DIR.'/core/classes/backup.php'", $core, 'The class is not required lazily');
        $this->assertStringNotContainsString('addBackupTask(', $core, 'A call to the removed implementation survived');
    }

    # Durable persistence works against a real file and says so when the file cannot be opened
    #[Test]
    public function theCandidateIsSyncedOrTheRunFails(): void
    {
        $data = $this->getProbe('sync');
        $this->assertSame('done', $data['ok'], 'fsync failed on a real file');
        $this->assertSame('Backup could not reopen the compressed candidate for fsync', $data['missing']);
    }
}
