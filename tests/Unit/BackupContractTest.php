<?php

namespace Tests\Unit;

use Backup;
use Database;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

require_once BASE_DIR.'/core/classes/pdo.php';
require_once BASE_DIR.'/core/classes/backup.php';

/**
 * Batch 2 of docs/BACKUP-2026.md: everything the Backup class decides before it touches a database or
 * a real filesystem. The collaborator is a plain mock, so no connection is opened; the compressor
 * capability map is injected through the constructor seam, and the only files written are the scratch
 * artifacts the result contract has to measure. Snapshot behaviour, unbuffered cursors, fsync and
 * link() need real semantics and belong to batch 3, so nothing here asserts on them.
 */
final class BackupContractTest extends TestCase
{
    private const SHIPPED = ['include' => '*', 'exclude' => 'ipb_*', 'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY', 'compress' => 'auto', 'keep' => '0',
        'allow_incomplete' => '0'];
    private array $temps = [];

    protected function tearDown(): void
    {
        foreach ($this->temps as $file) {
            if (is_file($file)) unlink($file);
        }
        $this->temps = [];
    }

    # Build the class under test with a stubbed database, an injected capability map and a scratch backup root
    private function getBackup(array $sett = [], ?array $caps = null, string $name = 'slaed'): Backup
    {
        $db = $this->createStub(Database::class);
        $db->method('getSqlValue')->willReturnCallback(static fn(mixed $val): string => '\''.str_replace('\'', '\'\'', (string)$val).'\'');
        return new Backup($db, ['name' => $name], $sett, sys_get_temp_dir().'/slaed_backup_unit', $caps ?? ['zip' => true, 'gz' => true, 'bz2' => true]);
    }

    # Call one private method of the class under test
    private function getCall(Backup $obj, string $name, array $args = []): mixed
    {
        return (new ReflectionMethod($obj, $name))->invokeArgs($obj, $args);
    }

    # Read one private property of the class under test
    private function getProp(Backup $obj, string $name): mixed
    {
        return (new ReflectionProperty($obj, $name))->getValue($obj);
    }

    # Write one private property of the class under test
    private function setProp(Backup $obj, string $name, mixed $val): void
    {
        (new ReflectionProperty($obj, $name))->setValue($obj, $val);
    }

    # Validate a settings array and return the accepted result
    private function getSettings(array $sett): array
    {
        $back = $this->getBackup($sett);
        return $this->getCall($back, 'checkSettingsInput', [$sett]);
    }

    # Build a privilege verdict from fixture grant lines for the given vendor and version
    private function getVerdict(array $lines, string $vend = 'mysql', int $srvn = 80400, string $schema = 'slaed'): array
    {
        $back = $this->getBackup(self::SHIPPED);
        $this->setProp($back, 'vend', $vend);
        $this->setProp($back, 'srvn', $srvn);
        return $this->getCall($back, 'checkGrantAccess', [$this->getCall($back, 'getGrantMatrix', [$lines]), $schema]);
    }

    # Reserve one scratch file path and register it for cleanup
    private function getScratch(string $ext): string
    {
        $file = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_backup_'.bin2hex(random_bytes(6)).'.'.$ext;
        $this->temps[] = $file;
        return $file;
    }

    # The settings this change ships in config/scheduler.php are exactly what the class accepts
    #[Test]
    public function theShippedDefaultsPassValidation(): void
    {
        $this->assertSame(self::SHIPPED, $this->getSettings(self::SHIPPED));
    }

    # The fixture above is worth nothing unless it is the configuration the product actually ships, and the upgrade must agree with it
    # Retention in particular has to stay off by default: a fresh install and an upgraded site must both keep every archive until an operator says otherwise
    #[Test]
    public function theRealConfigMatchesTheShippedDefaults(): void
    {
        $root = dirname(__DIR__, 2);
        $conf = require $root.'/config/scheduler.php';
        $live = $conf['scheduler']['jobs']['dbbackup']['settings'] ?? [];
        $this->assertSame(self::SHIPPED, $live, 'config/scheduler.php drifted away from the settings this test asserts');
        $this->assertSame('0', $live['keep'], 'Retention is on by default, so a fresh install would delete archives on its own');
        $setup = (string)file_get_contents($root.'/setup/index.php');
        $from = strpos($setup, '$bset = [');
        $this->assertNotFalse($from, 'The upgrade block no longer fills the backup settings');
        $block = substr($setup, $from, (int)strpos($setup, '];', $from) - $from);
        foreach (self::SHIPPED as $key => $val) {
            $this->assertStringContainsString("'".$key."' => '".$val."'", $block, 'The upgrade default for '.$key.' differs from the shipped one');
        }
    }

    # A missing key falls back to its default instead of failing a nightly run over an absent value
    #[Test]
    public function aMissingKeyFallsBackToItsDefault(): void
    {
        $sett = $this->getSettings([]);
        $this->assertSame('*', $sett['include']);
        $this->assertSame('auto', $sett['compress']);
        $this->assertSame('0', $sett['keep']);
        $this->assertSame('0', $sett['allow_incomplete']);
    }

    # A key nobody reads would be silently ignored, which is the failure mode this contract exists to prevent
    #[Test]
    public function anUnknownSettingKeyIsRejectedByName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#unknown keys: charset#');
        $this->getSettings(self::SHIPPED + ['charset' => 'auto']);
    }

    # allow_incomplete decides whether an incomplete artifact may exist at all, so its domain is exactly two values
    #[Test]
    #[DataProvider('getBadFlags')]
    public function allowIncompleteRejectsAnythingButZeroOrOne(string $flag): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#allow_incomplete must be exactly 0 or 1#');
        $this->getSettings(['allow_incomplete' => $flag]);
    }

    # Values that look like a boolean but are not the accepted domain
    public static function getBadFlags(): array
    {
        return [['2'], [''], ['yes'], ['true'], ['-1'], ['00']];
    }

    # Both accepted values are accepted
    #[Test]
    public function allowIncompleteAcceptsBothValidValues(): void
    {
        $this->assertSame('0', $this->getSettings(['allow_incomplete' => '0'])['allow_incomplete']);
        $this->assertSame('1', $this->getSettings(['allow_incomplete' => '1'])['allow_incomplete']);
    }

    # Retention counts artifacts, so anything that is not a non-negative integer is a configuration error
    #[Test]
    public function keepMustBeANonNegativeInteger(): void
    {
        $this->assertSame('7', $this->getSettings(['keep' => '7'])['keep']);
        $this->assertSame('0', $this->getSettings(['keep' => '0'])['keep']);
        $this->expectException(RuntimeException::class);
        $this->getSettings(['keep' => '-1']);
    }

    # An unknown compressor name must fail rather than resolve to something else
    #[Test]
    public function theCompressDomainIsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#compress must be one of#');
        $this->getSettings(['compress' => 'rar']);
    }

    # The legacy caret form did three jobs at once and must be rejected rather than reinterpreted as a plain pattern
    #[Test]
    public function theLegacyCaretPatternIsRejectedWithAPointerToExclude(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#legacy caret pattern \^ipb_\*; exclusions belong in the exclude setting#');
        $this->getSettings(['include' => '^ipb_*']);
    }

    # The same rejection applies to the exclude side, where the legacy syntax used to live
    #[Test]
    public function theLegacyCaretPatternIsAlsoRejectedInExclude(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#exclude rejects the legacy caret pattern#');
        $this->getSettings(['exclude' => '^ipb_*']);
    }

    # The shipped scope keeps every table and drops the forum tables the legacy string used to exclude
    #[Test]
    public function theShippedScopeReplacesTheLegacyCaretString(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $this->getCall($back, 'checkSettingsInput', [self::SHIPPED]);
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['slaed_news']));
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['slaed_users']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['ipb_topics']));
    }

    # Patterns match whole names, so a prefix or a suffix around the pattern is not in scope
    #[Test]
    public function patternsMatchWholeTableNamesOnly(): void
    {
        $back = $this->getBackup([]);
        $this->getCall($back, 'checkSettingsInput', [['include' => 'slaed_*']]);
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['slaed_news']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['xslaed_news']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['other_news']));
    }

    # A question mark stands for exactly one character, as the legacy documentation promised
    #[Test]
    public function aQuestionMarkMatchesExactlyOneCharacter(): void
    {
        $back = $this->getBackup([]);
        $this->getCall($back, 'checkSettingsInput', [['include' => 'slaed_????']]);
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['slaed_news']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['slaed_new']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['slaed_newsy']));
    }

    # Exclude wins over include, whatever order the operator wrote them in
    #[Test]
    public function excludeWinsOverInclude(): void
    {
        $back = $this->getBackup([]);
        $this->getCall($back, 'checkSettingsInput', [['include' => 'slaed_*', 'exclude' => 'slaed_session']]);
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['slaed_news']));
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['slaed_session']));
    }

    # Case folding follows the server rule rather than a guess, so the same pattern answers differently per server
    #[Test]
    public function caseFoldingFollowsTheServerRule(): void
    {
        $back = $this->getBackup([]);
        $this->getCall($back, 'checkSettingsInput', [['include' => 'slaed_*']]);
        $this->assertFalse($this->getCall($back, 'checkTableScope', ['SLAED_News']));
        $this->setProp($back, 'fold', true);
        $this->assertTrue($this->getCall($back, 'checkTableScope', ['SLAED_News']));
    }

    # auto must fall back on its own, because addCompress() treats auto without any compressor as a hard error
    #[Test]
    public function autoFallsBackToNoneWhenNothingIsAvailable(): void
    {
        $back = $this->getBackup(self::SHIPPED, ['zip' => false, 'gz' => false, 'bz2' => false]);
        $this->getCall($back, 'checkSettingsInput', [self::SHIPPED]);
        $this->getCall($back, 'setCompressor');
        $this->assertSame('none', $this->getProp($back, 'algo'));
    }

    # auto resolves one concrete algorithm and never passes auto down
    #[Test]
    public function autoResolvesZipThenGzThenBz2(): void
    {
        $back = $this->getBackup(self::SHIPPED, ['zip' => false, 'gz' => true, 'bz2' => true]);
        $this->getCall($back, 'checkSettingsInput', [self::SHIPPED]);
        $this->getCall($back, 'setCompressor');
        $this->assertSame('gz', $this->getProp($back, 'algo'));
        $back = $this->getBackup(self::SHIPPED, ['zip' => false, 'gz' => false, 'bz2' => true]);
        $this->getCall($back, 'checkSettingsInput', [self::SHIPPED]);
        $this->getCall($back, 'setCompressor');
        $this->assertSame('bz2', $this->getProp($back, 'algo'));
    }

    # An explicitly requested compressor that does not exist fails in preflight, before any output exists
    #[Test]
    public function anExplicitUnavailableCompressorFailsBeforeOutput(): void
    {
        $sett = ['compress' => 'bz2'];
        $back = $this->getBackup($sett, ['zip' => true, 'gz' => true, 'bz2' => false]);
        $this->getCall($back, 'checkSettingsInput', [$sett]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#compressor bz2 is not available#');
        $this->getCall($back, 'setCompressor');
    }

    # An operator who asked for no compression gets none, even when nothing is installed
    #[Test]
    public function noneNeedsNoCompressorAtAll(): void
    {
        $sett = ['compress' => 'none'];
        $back = $this->getBackup($sett, ['zip' => false, 'gz' => false, 'bz2' => false]);
        $this->getCall($back, 'checkSettingsInput', [$sett]);
        $this->getCall($back, 'setCompressor');
        $this->assertSame('none', $this->getProp($back, 'algo'));
    }

    # With the default settings an object this class cannot serialize fails the run and the message names it
    #[Test]
    public function anUnsupportedObjectFailsTheRunByDefault(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $this->setProp($back, 'sett', self::SHIPPED);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('#routines \(getcount, movenet\).*allow_incomplete#');
        $this->getCall($back, 'checkScopeLimit', [['routines' => ['getcount', 'movenet']]]);
    }

    # With the explicit opt-in the same scope succeeds, and the incompleteness is recorded rather than silent
    #[Test]
    public function anAcceptedIncompleteScopeIsRecorded(): void
    {
        $sett = ['allow_incomplete' => '1'] + self::SHIPPED;
        $back = $this->getBackup($sett);
        $this->setProp($back, 'sett', $sett);
        $this->getCall($back, 'checkScopeLimit', [['triggers' => ['trg_news'], 'views' => []]]);
        $this->assertFalse($this->getProp($back, 'full'));
        $this->assertSame(['triggers' => ['trg_news']], $this->getProp($back, 'unsup'));
    }

    # A scope holding none of the unsupported classes stays complete under both settings
    #[Test]
    public function anEmptyObjectSetKeepsTheRunComplete(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $this->setProp($back, 'sett', self::SHIPPED);
        $this->getCall($back, 'checkScopeLimit', [['views' => [], 'triggers' => [], 'events' => [], 'routines' => []]]);
        $this->assertTrue($this->getProp($back, 'full'));
        $this->assertSame([], $this->getProp($back, 'unsup'));
    }

    # Schema SELECT alone listed the table and the view but reported zero triggers, events and routines while all three existed
    #[Test]
    public function schemaSelectAloneCannotProveTheOtherClasses(): void
    {
        $miss = $this->getVerdict(['GRANT USAGE ON *.* TO `probe`@`localhost`', 'GRANT SELECT ON `slaed`.* TO `probe`@`localhost`']);
        $this->assertArrayNotHasKey('tables', $miss);
        $this->assertArrayHasKey('triggers', $miss);
        $this->assertArrayHasKey('events', $miss);
        $this->assertArrayHasKey('routines', $miss);
    }

    # Global SELECT behaved identically for triggers and events on both vendors, and is the alternative that does satisfy routines
    #[Test]
    public function globalSelectStillCannotProveTriggersOrEvents(): void
    {
        $miss = $this->getVerdict(['GRANT SELECT ON *.* TO `probe`@`localhost`']);
        $this->assertArrayNotHasKey('tables', $miss);
        $this->assertArrayNotHasKey('routines', $miss);
        $this->assertArrayHasKey('triggers', $miss);
        $this->assertArrayHasKey('events', $miss);
    }

    # A grant that covers every class leaves nothing to prove
    #[Test]
    public function aCompleteGrantSatisfiesEveryClass(): void
    {
        $this->assertSame([], $this->getVerdict(['GRANT SELECT, TRIGGER, EVENT ON *.* TO `probe`@`localhost`']));
        $this->assertSame([], $this->getVerdict(['GRANT ALL PRIVILEGES ON *.* TO `probe`@`localhost` WITH GRANT OPTION']));
    }

    # The spike account held SELECT and TRIGGER globally and could still see nothing in the schema, because a partial revoke narrowed the global grant
    #[Test]
    public function aPartialRevokeIsSubtractedFromTheGlobalGrant(): void
    {
        $lines = ['GRANT SELECT, TRIGGER, EVENT ON *.* TO `probe`@`localhost`', 'REVOKE SELECT, TRIGGER, EVENT ON `slaed`.* FROM `probe`@`localhost`'];
        $miss = $this->getVerdict($lines);
        $this->assertArrayHasKey('tables', $miss);
        $this->assertArrayHasKey('triggers', $miss);
        $this->assertArrayHasKey('events', $miss);
        $this->assertSame([], $this->getVerdict($lines, 'mysql', 80400, 'other'), 'A revoke on one schema must not narrow another schema');
    }

    # MariaDB expands a nested role chain itself, so the output addresses several grantees and must not be filtered by the requested name
    #[Test]
    public function grantLinesAddressedToAnotherGranteeAreAccepted(): void
    {
        $lines = [
            'GRANT SELECT ON *.* TO `probe`@`localhost` IDENTIFIED BY PASSWORD \'*11223344556677889900AABBCCDDEEFF00112233\'',
            'GRANT `parent` TO `probe`@`localhost`',
            'GRANT TRIGGER, EVENT ON *.* TO `child`',
            'GRANT `child` TO `parent`',
        ];
        $this->assertSame([], $this->getVerdict($lines, 'mariadb', 110702));
    }

    # MariaDB embeds password hashes in its grant output, so nothing derived from a grant line may carry one
    #[Test]
    public function aPasswordHashNeverEntersTheEvaluatedSet(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $mtrx = $this->getCall($back, 'getGrantMatrix', [['GRANT SELECT ON *.* TO `probe`@`localhost` IDENTIFIED BY PASSWORD \'*SECRETHASH\'']]);
        $this->assertStringNotContainsString('SECRETHASH', json_encode($mtrx));
        $this->assertStringNotContainsString('IDENTIFIED', json_encode($mtrx));
    }

    # A column grant proves visibility of two columns, never of a class, so it must not satisfy a scope-wide claim
    #[Test]
    public function anObjectLevelGrantNeverSatisfiesAClassWideClaim(): void
    {
        $miss = $this->getVerdict(['GRANT SELECT (id, title) ON `slaed`.`slaed_news` TO `probe`@`localhost`']);
        $this->assertArrayHasKey('tables', $miss);
        $this->assertArrayHasKey('triggers', $miss);
    }

    # SHOW_ROUTINE is a MySQL 8.0.20 global dynamic privilege and has no meaning on MariaDB or on an older server
    #[Test]
    public function showRoutineCountsOnlyWhereItExists(): void
    {
        $lines = ['GRANT TRIGGER, EVENT ON *.* TO `probe`@`localhost`', 'GRANT SELECT ON `slaed`.* TO `probe`@`localhost`', 'GRANT SHOW_ROUTINE ON *.* TO `probe`@`localhost`'];
        $this->assertSame([], $this->getVerdict($lines, 'mysql', 80400));
        $this->assertArrayHasKey('routines', $this->getVerdict($lines, 'mysql', 80019));
        $this->assertArrayHasKey('routines', $this->getVerdict($lines, 'mariadb', 110702));
    }

    # Being a routine definer yields a non-empty but incomplete catalog, so the remaining alternatives are the only ones that count
    #[Test]
    public function routineVisibilityAcceptsItsDocumentedAlternatives(): void
    {
        $base = 'GRANT SELECT, TRIGGER, EVENT ON `slaed`.* TO `probe`@`localhost`';
        $this->assertArrayHasKey('routines', $this->getVerdict([$base]));
        $this->assertSame([], $this->getVerdict([$base, 'GRANT EXECUTE ON `slaed`.* TO `probe`@`localhost`']));
        $this->assertSame([], $this->getVerdict([$base, 'GRANT CREATE ROUTINE ON `slaed`.* TO `probe`@`localhost`']));
        $this->assertSame([], $this->getVerdict([$base, 'GRANT ALTER ROUTINE ON `slaed`.* TO `probe`@`localhost`']));
    }

    # A value is written as a numeric literal only when the column metadata and the value both prove it is numeric
    #[Test]
    public function numericLiteralsNeedBothMetadataAndValue(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $num = ['name' => 'id', 'type' => 'int', 'extr' => '', 'num' => true, 'bin' => false, 'gen' => false];
        $txt = ['name' => 'title', 'type' => 'varchar', 'extr' => '', 'num' => false, 'bin' => false, 'gen' => false];
        $this->assertSame('42', $this->getCall($back, 'getRowLiteral', [42, $num]));
        $this->assertSame('42', $this->getCall($back, 'getRowLiteral', ['42', $num]));
        $this->assertSame('\'42\'', $this->getCall($back, 'getRowLiteral', ['42', $txt]));
        $this->assertSame('\'0x1A\'', $this->getCall($back, 'getRowLiteral', ['0x1A', $num]), 'A non-numeric value in a numeric column must be quoted, not emitted raw');
    }

    # A NULL stays a NULL and never becomes an empty string
    #[Test]
    public function nullIsSerializedAsNull(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $col = ['name' => 'body', 'type' => 'text', 'extr' => '', 'num' => false, 'bin' => false, 'gen' => false];
        $this->assertSame('NULL', $this->getCall($back, 'getRowLiteral', [null, $col]));
    }

    # Binary and bit values are written as hex, and an empty binary value stays an empty string because 0x alone is not valid SQL
    #[Test]
    public function binaryValuesAreSerializedAsHex(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $col = ['name' => 'data', 'type' => 'blob', 'extr' => '', 'num' => false, 'bin' => true, 'gen' => false];
        $this->assertSame('0x00ff41', $this->getCall($back, 'getRowLiteral', ["\x00\xff\x41", $col]));
        $this->assertSame('\'\'', $this->getCall($back, 'getRowLiteral', ['', $col]));
        $this->assertSame('NULL', $this->getCall($back, 'getRowLiteral', [null, $col]));
    }

    # A quoted value goes through the driver rather than through a hand-written escape
    #[Test]
    public function textValuesAreQuotedByTheDriver(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $col = ['name' => 'title', 'type' => 'varchar', 'extr' => '', 'num' => false, 'bin' => false, 'gen' => false];
        $this->assertSame('\'O\'\'Neil\'', $this->getCall($back, 'getRowLiteral', ['O\'Neil', $col]));
    }

    # A generated column is computed on restore, so it must not appear in the INSERT column list
    #[Test]
    public function generatedColumnsAreExcludedFromTheInsert(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $cols = [
            ['name' => 'id', 'type' => 'int', 'extr' => 'AUTO_INCREMENT', 'num' => true, 'bin' => false, 'gen' => false],
            ['name' => 'slug', 'type' => 'varchar', 'extr' => 'STORED GENERATED', 'num' => false, 'bin' => false, 'gen' => true],
            ['name' => 'title', 'type' => 'varchar', 'extr' => '', 'num' => false, 'bin' => false, 'gen' => false],
        ];
        $kept = $this->getCall($back, 'filterDumpCols', [$cols]);
        $this->assertSame(['id', 'title'], array_column($kept, 'name'));
    }

    # The canonical name always carries .sql, including for zip, so one suffix rule covers every format
    #[Test]
    public function theArtifactNameFollowsTheCanonicalPattern(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $this->setProp($back, 'tick', 1767225600);
        $stamp = date('Y-m-d_H-i-s', 1767225600);
        $this->assertMatchesRegularExpression('#^slaed_'.$stamp.'_[0-9a-f]{8}\.sql$#', $this->getCall($back, 'getArtifactName', ['']));
        $this->assertMatchesRegularExpression('#^slaed_'.$stamp.'_[0-9a-f]{8}\.sql\.zip$#', $this->getCall($back, 'getArtifactName', ['zip']));
        $this->assertMatchesRegularExpression('#^slaed_'.$stamp.'_[0-9a-f]{8}\.sql\.gz$#', $this->getCall($back, 'getArtifactName', ['gz']));
    }

    # A collision is answered by a new suffix, so the retry cannot reproduce the name that just failed
    #[Test]
    public function aCollisionRetryProducesADifferentName(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $names = [];
        for ($i = 0; $i < 8; $i++) $names[] = $this->getCall($back, 'getArtifactName', ['gz']);
        $this->assertCount(8, array_unique($names));
    }

    # The stem is the sanitized database name, so a name with separators cannot escape the backup root
    #[Test]
    public function theStemSanitizesTheDatabaseName(): void
    {
        $back = $this->getBackup(self::SHIPPED, null, '../my db.1');
        $this->assertSame('___my_db_1', $this->getProp($back, 'stem'));
        $this->assertStringStartsWith('___my_db_1_', $this->getCall($back, 'getArtifactName', ['']));
    }

    # Retention is off by default, so the first run after the upgrade cannot silently delete an operator history
    #[Test]
    public function keepZeroDeletesNothing(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $files = ['slaed_2026-06-03_03-30-01.zip' => 100, 'slaed_2026-08-01_03-30-01_aabbccdd.sql.gz' => 200];
        $this->assertSame([], $this->getCall($back, 'filterRetentionList', [$files, 0]));
    }

    # Legacy and current names age out together, ordered by the timestamp in the name rather than by mtime
    #[Test]
    public function bothNamingSchemesAgeTogether(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $files = [
            'slaed_2026-06-03_03-30-01.zip' => 900,
            'slaed_2026-07-01_03-30-02.sql' => 900,
            'slaed_2026-08-01_03-30-03_aabbccdd.sql.gz' => 100,
            'slaed_2026-08-02_03-30-04_11223344.sql' => 100,
        ];
        $this->assertSame(['slaed_2026-07-01_03-30-02.sql', 'slaed_2026-06-03_03-30-01.zip'], $this->getCall($back, 'filterRetentionList', [$files, 2]));
        $this->assertSame(['slaed_2026-06-03_03-30-01.zip'], $this->getCall($back, 'filterRetentionList', [$files, 3]));
        $this->assertSame([], $this->getCall($back, 'filterRetentionList', [$files, 4]));
    }

    # Anything that is not a canonical artifact of this database is invisible to retention
    #[Test]
    public function foreignFilesAreInvisibleToRetention(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $files = [
            'index.html' => 100,
            '.htaccess' => 100,
            'other_2026-06-03_03-30-01.zip' => 100,
            'slaed_2026-06-03_03-30-01.sql.part' => 100,
            'slaed_backup.sql' => 100,
            'slaed_2026-06-03_03-30-01_zzzzzzzz.sql' => 100,
            'slaed_2026-06-03_03-30-01.zip' => 100,
            'slaed_2026-08-01_03-30-01_aabbccdd.sql.gz' => 100,
        ];
        $this->assertSame(['slaed_2026-06-03_03-30-01.zip'], $this->getCall($back, 'filterRetentionList', [$files, 1]));
    }

    # mtime breaks a tie between two names that carry the same timestamp and is never the primary key
    #[Test]
    public function mtimeOnlyBreaksTies(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $files = [
            'slaed_2026-08-01_03-30-01_aaaaaaaa.sql' => 100,
            'slaed_2026-08-01_03-30-01_bbbbbbbb.sql' => 300,
            'slaed_2026-08-02_03-30-01_cccccccc.sql' => 50,
        ];
        $this->assertSame(['slaed_2026-08-01_03-30-01_aaaaaaaa.sql'], $this->getCall($back, 'filterRetentionList', [$files, 2]));
    }

    # A failure after a verified publication is a warning on a successful run, never a reason to discard the artifact
    #[Test]
    public function aPostPublicationFailureKeepsTheRunSuccessful(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $file = $this->getScratch('sql');
        file_put_contents($file, 'SELECT 1;');
        $this->getCall($back, 'addRunWarning', ['Backup could not delete the expired artifact: old.sql']);
        $res = $this->getCall($back, 'getRunResult', [$file, str_repeat('a', 64), '', 0]);
        $this->assertSame('success', $res['status']);
        $this->assertStringContainsString('could not delete the expired artifact', $res['message']);
        $this->assertSame(['Backup could not delete the expired artifact: old.sql'], $res['extra']['warnings']);
    }

    # The result identifies exactly one artifact with its checksums, so no consumer ever derives a filename
    #[Test]
    public function theResultCarriesTheWholeContract(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $file = $this->getScratch('sql');
        file_put_contents($file, 'SELECT 1;');
        $this->setProp($back, 'tnum', 37);
        $this->setProp($back, 'rnum', 4711);
        $this->setProp($back, 'unord', ['slaed_session']);
        $res = $this->getCall($back, 'getRunResult', [$file, 'deadbeef', 'gz', 3]);
        $keys = ['backup_file', 'backup_path', 'backup_size', 'backup_format', 'backup_hash', 'dump_hash', 'table_count', 'row_count', 'complete', 'unsupported', 'unordered',
            'warnings', 'removed'];
        $this->assertSame($keys, array_keys($res['extra']));
        $this->assertSame(basename($file), $res['extra']['backup_file']);
        $this->assertSame(9, $res['extra']['backup_size']);
        $this->assertSame('gz', $res['extra']['backup_format']);
        $this->assertSame(hash_file('sha256', $file), $res['extra']['backup_hash']);
        $this->assertSame('deadbeef', $res['extra']['dump_hash']);
        $this->assertSame(37, $res['extra']['table_count']);
        $this->assertSame(4711, $res['extra']['row_count']);
        $this->assertSame(['slaed_session'], $res['extra']['unordered']);
        $this->assertSame(3, $res['extra']['removed']);
        $this->assertTrue($res['extra']['complete']);
    }

    # An accepted incomplete run says so in both the flag and the message, so the scheduler cannot report it as a full backup
    #[Test]
    public function anIncompleteRunIsVisibleInTheResult(): void
    {
        $sett = ['allow_incomplete' => '1'] + self::SHIPPED;
        $back = $this->getBackup($sett);
        $this->setProp($back, 'sett', $sett);
        $file = $this->getScratch('sql');
        file_put_contents($file, 'SELECT 1;');
        $this->getCall($back, 'checkScopeLimit', [['routines' => ['getcount']]]);
        $res = $this->getCall($back, 'getRunResult', [$file, 'deadbeef', '', 0]);
        $this->assertFalse($res['extra']['complete']);
        $this->assertSame(['routines' => ['getcount']], $res['extra']['unsupported']);
        $this->assertStringContainsString('routines (getcount)', $res['message']);
    }

    # Every session variable the dump changes is saved first and handed back by the epilogue, so a restore leaves the client session as it found it
    #[Test]
    public function theDumpEpilogueRestoresEverythingThePrologueChanged(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $head = $this->getCall($back, 'getDumpProlog');
        $foot = $this->getCall($back, 'getDumpEpilog');
        preg_match_all('/SET (@SLAED_[A-Z]+) = @@SESSION\.(\w+);/', $head, $kept);
        preg_match_all('/SET SESSION (\w+) = /', $head, $used);
        $this->assertNotEmpty($kept[2]);
        $this->assertSame(['foreign_key_checks', 'unique_checks', 'sql_mode', 'time_zone'], $used[1], 'The prologue changed a variable the test does not know about');
        foreach (array_combine($kept[1], $kept[2]) as $mark => $name) {
            $this->assertStringContainsString('SET SESSION '.$name.' = '.$mark.';', $foot, $name.' is saved but never restored');
        }
        $this->assertStringContainsString('SET NAMES utf8mb4;', $head);
        foreach (['character_set_client', 'character_set_results', 'collation_connection'] as $name) {
            $this->assertStringContainsString('SET SESSION '.$name.' = @SLAED_', $foot, 'SET NAMES is not reversible without '.$name);
        }
    }

    # One recognizer answers what an artifact of this database is, so retention and the monitor cannot drift apart
    #[Test]
    public function theArtifactRecognizerAnswersBothNamingSchemes(): void
    {
        $this->assertSame('2026-08-01_03-30-01', Backup::getArtifactMark('slaed_2026-08-01_03-30-01_aabbccdd.sql', 'slaed'));
        $this->assertSame('2026-08-01_03-30-01', Backup::getArtifactMark('slaed_2026-08-01_03-30-01_aabbccdd.sql.gz', 'slaed'));
        $this->assertSame('2026-06-03_07-29-26', Backup::getArtifactMark('slaed_2026-06-03_07-29-26.zip', 'slaed'), 'The legacy scheme is no longer recognized');
    }

    # Everything that is not a published artifact of this database is invisible, which is what keeps staging out of the monitor figure
    #[Test]
    public function theArtifactRecognizerRejectsEverythingElse(): void
    {
        $junk = ['index.html', '.htaccess', '.stage-aabbccdd', 'dump.sql', 'slaed_2026-08-01_03-30-01_aabbccdd.sql.part',
            'slaed_2026-08-01_03-30-01_zzzzzzzz.sql', 'other_2026-08-01_03-30-01.zip'];
        foreach ($junk as $file) {
            $this->assertSame('', Backup::getArtifactMark($file, 'slaed'), $file.' was taken for an artifact');
        }
    }

    # The stem is derived in one place too, so a database name is sanitized the same way wherever it is needed
    #[Test]
    public function theStemIsDerivedInOnePlace(): void
    {
        $this->assertSame('slaed', Backup::getArtifactStem('slaed'));
        $this->assertSame('my_db_x', Backup::getArtifactStem('my db.x'));
        $this->assertSame($this->getProp($this->getBackup(self::SHIPPED), 'stem'), Backup::getArtifactStem('slaed'));
    }

    # The monitor must use that recognizer rather than keep a second copy of the naming rules
    #[Test]
    public function theMonitorUsesTheSharedRecognizer(): void
    {
        $code = (string)file_get_contents(dirname(__DIR__, 2).'/admin/modules/monitor.php');
        $this->assertStringContainsString('Backup::getArtifactMark(', $code, 'The monitor does not use the shared artifact recognizer');
        $this->assertStringContainsString('Backup::getArtifactStem(', $code);
        $this->assertStringNotContainsString('getLatestFileMTime', $code, 'The monitor still derives the last backup from directory mtime');
    }

    # An uncompressed artifact reports its format as sql rather than as an empty string
    #[Test]
    public function anUncompressedArtifactReportsTheSqlFormat(): void
    {
        $back = $this->getBackup(self::SHIPPED);
        $file = $this->getScratch('sql');
        file_put_contents($file, 'SELECT 1;');
        $res = $this->getCall($back, 'getRunResult', [$file, 'deadbeef', '', 0]);
        $this->assertSame('sql', $res['extra']['backup_format']);
    }
}
