<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The events of the extension event, driven by tests/Support/route_probe.php with the argument event; the static half reads the files
final class NodeEventTest extends TestCase
{
    # The raw answer of the probe run, kept before any assertion so a failed probe is not started again by the next test
    private static array $outs = [];

    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    # The probe runs the base disposable database and configuration with the type events of the extension event, open to the public form of a member
    # Real HTTP requests of the administrator, a member and a guest reach the forms, the list, its archive and the page; the child mode eventext asks NodeEvent directly
    # Run the probe once in its event mode and memoize the run; a probe that cannot create its database or start its server is a failure, not a skip
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_event';
            $out = self::$outs[__FUNCTION__] ??= (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' event 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            $this->assertIsArray($data['runs']['event']['ext'] ?? null, 'The child mode eventext did not answer');
            self::$probe = $data['runs']['event'];
        }
        return self::$probe;
    }

    # The form of the panel and the public form of a member take the start, the end and the place; an empty end is stored as none and the place is trimmed
    #[Test]
    public function theFormsStoreTheStartTheEndAndThePlace(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, true], $run['form'], 'The form of the panel does not offer the start, the end and the place');
        $this->assertSame([303, 303, 303, 303, 303], $run['add'], 'An event of the panel was not created');
        $this->assertSame($run['row'][1], $run['row'][0], 'The stored start, end or place differs from the form');
        $this->assertNull($run['row'][2], 'An empty end was not stored as none');
        [$page, $start, $place, $code, $row] = $run['edit'];
        $this->assertSame([200, true, true, 303], [$page, $start, $place, $code], 'The edit form does not show the stored event or did not save');
        $this->assertSame(['ends' => null, 'place' => 'Arena'], ['ends' => $row['ends'], 'place' => $row['place']], 'The edit did not change the end and the place');
        $this->assertSame([200, true, 303, 'Square'], $run['member'], 'The public form of a member does not write the event');
    }

    # An end not after the start, a missing or impossible start and a place wider than its column store nothing, and the refusal names the field
    #[Test]
    public function aRefusedEventStoresNothingAndNamesItsField(): void
    {
        $run = $this->getRun();
        $this->assertSame([422, 422, 422, 422, 422, 0], $run['bad'], 'A refused event was stored or answered another status');
        $this->assertSame([true, true], $run['named'], 'The refusal of an end before the start does not name its field');
        $ext = $run['ext']['input'];
        $this->assertSame(['ok' => true, 'value' => ['starts' => '2030-05-01 10:00:00', 'ends' => '2030-05-01 12:00:00', 'place' => 'Hall']], $ext['good']);
        $this->assertSame(['ok' => true, 'value' => ['starts' => '2030-05-01 10:00:00', 'ends' => null, 'place' => '']], $ext['open']);
        $this->assertTrue($ext['wide'], 'A place of 255 characters was refused');
        foreach (['same' => 'ext.ends', 'early' => 'ext.starts', 'day' => 'ext.starts', 'text' => 'ext.starts', 'ctrl' => 'ext.place', 'long' => 'ext.place',
            'extra' => 'ext'] as $key => $path) {
            $this->assertSame(['ok' => false, 'code' => 3, 'msg' => 'Invalid event input: '.$path], $ext[$key], $key);
        }
    }

    # The list shows the upcoming events, the running one first, without a sort or a direction of its own; the archive shows the past ones newest first
    #[Test]
    public function theListSplitsUpcomingAndPastEvents(): void
    {
        $run = $this->getRun();
        [$code, $spots, $leaves, $sort, $arch, $dir, $place] = $run['list'];
        $this->assertSame(200, $code);
        $this->assertSame([-1, -1], [$spots['Past'], $spots['Old']], 'A past event stands in the upcoming list');
        $this->assertTrue($spots['Running'] > 0 && $spots['Running'] < $spots['Soon'] && $spots['Soon'] < $spots['Later'], 'The upcoming list is not nearest first');
        $this->assertSame([3, false, true, false, true], [$leaves, $sort, $arch, $dir, $place], 'The list misses its leaves, its archive switch or the place, or offers a sort');
        [$code, $spots, $active, $marks] = $run['archive'];
        $this->assertSame(200, $code);
        $this->assertSame([-1, -1, -1], [$spots['Running'], $spots['Soon'], $spots['Later']], 'An upcoming event stands in the archive');
        $this->assertTrue($spots['Past'] > 0 && $spots['Past'] < $spots['Old'], 'The archive is not newest first');
        $this->assertTrue($active, 'The archive switch is not marked on the archive');
        $this->assertSame(3, $marks, 'The archive switch and the two past events do not carry the archive mark');
        $this->assertSame([400, 400, 400, 400], $run['refuse'], 'A sort, a direction or a bad archive switch was accepted, or a standard type took the switch');
        $this->assertSame([200, true, true], $run['view'], 'A past event lost its page, its place or its leaf');
    }

    # An event without an end stays upcoming through the day it starts on and goes to the archive with the next day
    #[Test]
    public function anEventWithoutAnEndLastsItsDay(): void
    {
        $this->assertSame([[true, false], [false, true]], $this->getRun()['ext']['day']);
    }

    # The extension has no settings, the display mode event belongs to its type alone, and the scope and order answer only the list arguments of the type
    #[Test]
    public function theExtensionAnswersTheListArgumentsAlone(): void
    {
        $ext = $this->getRun()['ext'];
        $bad = ['ok' => false, 'code' => 3];
        $this->assertSame(['ok' => true, 'value' => []], $ext['config'][0]);
        $this->assertSame($bad, array_slice($ext['config'][1], 0, 2), 'The extension took settings of its own');
        $this->assertSame($bad + ['msg' => 'Invalid node input: view.mode'], $ext['config'][2], 'A type of the extension took another display mode');
        $this->assertSame($bad + ['msg' => 'Invalid node input: view.mode'], $ext['config'][3], 'A standard type took the display mode event');
        $this->assertTrue($ext['config'][4], 'A type of the extension with its display mode was refused');
        [$scope, $order, $join, $next, $past, $badscope, $badorder] = $ext['scope'];
        $this->assertSame([['join' => '', 'where' => '', 'params' => []], []], [$scope, $order], 'A read outside the list is narrowed or ordered');
        $this->assertTrue($join, 'The list of the type does not join the events');
        $this->assertSame([['expr' => 'ne.starts', 'dir' => 'asc'], ['expr' => 'ne.starts', 'dir' => 'desc']], [$next, $past]);
        $this->assertSame([$bad + ['msg' => 'Invalid event input: args'], $bad + ['msg' => 'Invalid event input: args']], [$badscope, $badorder]);
        [$standard, $sorted, $plain] = $ext['misuse'];
        $this->assertSame($bad + ['msg' => 'Invalid node input: args'], $standard, 'A standard type took list arguments');
        $this->assertSame($bad + ['msg' => 'Invalid node input: order'], $sorted, 'A sort key overrode the order of the extension');
        $this->assertSame(['ok' => true, 'value' => 6], $plain, 'A read without list arguments does not see every event of the type');
    }

    # The list of an event type reads type, page and count, and the events of the page in one batch: four statements, inside the ceiling of docs/NODE.md
    #[Test]
    public function theListKeepsItsStatementBudget(): void
    {
        $this->assertSame(4, $this->getRun()['ext']['budget']);
    }

    # The table, the closed factory and the display mode are where docs/NODE.md puts them, and the update carries the same table as a fresh install
    #[Test]
    public function theStageFilesKeepTheirContract(): void
    {
        $root = self::getRoot();
        $load = (string)file_get_contents($root.'/core/classes/node/ext/load.php');
        $this->assertStringContainsString("'event' => ['event.php', 'NodeEvent']", $load);
        $code = (string)file_get_contents($root.'/core/classes/node/ext/event.php');
        $this->assertStringContainsString('final class NodeEvent implements NodeExtension', $code);
        $this->assertSame(11, preg_match_all('/^    public function /m', $code), 'The class has other public methods than its constructor and the ten of the contract');
        $table = (string)file_get_contents($root.'/storage/update/sql/table.sql');
        $this->assertMatchesRegularExpression('/CREATE TABLE `\{prefix\}_node_event` \(\n  `nid` INT UNSIGNED NOT NULL,\n  `starts` DATETIME NOT NULL,\n'
            .'  `ends` DATETIME DEFAULT NULL,\n  `place` VARCHAR\(255\) NOT NULL DEFAULT \'\',\n  PRIMARY KEY \(`nid`\),\n  KEY `starts` \(`starts`, `nid`\),/', $table);
        foreach (['fragments/node/event/card.html', 'partials/node/event/view.html'] as $one) {
            $this->assertFileExists($root.'/public/templates/lite/'.$one, 'The lite theme misses the template '.$one.' of the display mode event');
        }
    }
}
