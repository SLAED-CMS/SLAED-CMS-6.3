<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S16 of docs/node: the remaining integrations of Node. The behaviour is driven by tests/Support/route_probe.php with the argument integ: the
 * disposable database and scratch configuration of the S13 probe, news with rating, favorites, poll, home and all four integrations, docs with search,
 * sitemap and blocks, real HTTP requests of guests, users and administrators, and the child mode integext that runs the sitemap generator and puts the
 * map of the stand back byte for byte. The static half reads the files.
 */
final class NodeIntegTest extends TestCase
{
    private static array $probe = [];

    # The root of the tree
    private static function getRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 2));
    }

    # Run the probe once in its integ mode and memoize the run; the one SQL error allowed is the refusal of the probe trigger that proves the rollback of a vote
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_integ';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' integ 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The routes wrote PHP errors');
            $this->assertCount(1, $data['logs']['error_sql.log'], 'The routes wrote SQL errors beyond the refusal of the probe trigger');
            $this->assertStringContainsString('probe refuses the aggregate', $data['logs']['error_sql.log'][0]);
            $this->assertIsArray($data['runs']['integ']['sitemap'] ?? null, 'The child mode integext did not answer');
            self::$probe = $data['runs']['integ'];
        }
        return self::$probe;
    }

    # One file of the tree
    private static function getFile(string $path): string
    {
        return (string)file_get_contents(self::getRoot().'/'.$path);
    }

    # The page of a material carries the live rating, the poll and, for a user, the favorite switch only where the type has the feature
    #[Test]
    public function theMaterialShowsTheLivePartsOfItsFeatures(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, true, true, false, true], $run['widgets'], 'The rating, the poll or the live token is missing, or a guest got the favorite switch');
        $this->assertSame([false, false], $run['docsview'], 'A type without rating or poll shows them');
    }

    # A vote of node.<name> goes through Node: one delivery is stored once, a repeat answers the stored vote, the interval holds, and the version stays
    #[Test]
    public function aVoteIsStoredOnceWithoutAnewVersion(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, [4, 1, 1, 1], 200, [4, 1, 1, 1], 429, [4, 1, 1, 1], 1], $run['vote']);
        $this->assertSame([200, ''], array_slice($run['average'], 0, 2), 'The vote did not answer the refreshed block');
        $this->assertStringEndsWith(': 5', $run['average'][2], 'The refreshed block does not show the average the rating class answers');
        $js = (string)file_get_contents(dirname(__DIR__, 2).'/plugins/system/slaed.js');
        $this->assertStringContainsString("new DOMParser().parseFromString(xhr.responseText || '', 'text/html').body", $js, 'A refused vote is not read in an inert document');
        $this->assertStringNotContainsString('box.innerHTML = xhr.responseText', $js, 'A refused vote is parsed by a live element where an image handler runs');
    }

    # A closed, pending or disabled material and an unknown type are unavailable, a type without the rating feature refuses, and so do a wrong token and GET
    #[Test]
    public function aVoteOutsideTheRightsIsRefused(): void
    {
        $run = $this->getRun();
        $this->assertSame([404, 404, 404, 403, 404, 403, 405], $run['refuse']);
        $this->assertSame([403, [0, 0, 0, 0], 200, [4, 1, 1, 1]], $run['own'], 'The author rated the own material, or a member could not rate a readable one');
    }

    # A failure of the Node write inside the transaction of the vote takes the vote, the actor and the target row back as a whole
    #[Test]
    public function aFailedNodeWriteRollsTheVoteBack(): void
    {
        $run = $this->getRun();
        $this->assertSame([500, [0, 0, 0, 0]], $run['rollback'], 'A vote survived the failure of the Node write');
        $this->assertSame([200, [4, 1, 1, 1]], $run['after'], 'The target stayed blocked after the failure');
    }

    # A favorite needs a readable material of a type with the feature; the lists show only what their viewer may read, and the deletion of a material removes it
    #[Test]
    public function favoritesFollowTheRightsAndTheLifeOfTheMaterial(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, 200, true, 1, 0, 1, 0], $run['fav'], 'A favorite was refused, or one was stored for a type without the feature or an unreadable material');
        $this->assertSame([true, false, true], $run['favlist'], 'The list shows a material its viewer may not read, or the lists miss a Node title');
        $this->assertSame([303, null, 0], $run['delete'], 'The deletion of a material left its favorites');
    }

    # Deleting a shared poll clears the link of its materials with a new version, deletes the poll and leaves the material
    #[Test]
    public function deletingAPollClearsTheLinkOfItsMaterials(): void
    {
        $this->assertSame([303, 0, 1, 0, false], $this->getRun()['poll']);
    }

    # Search reads the types with the search integration as the visitor may, escapes the literal word, and offers exactly those types
    #[Test]
    public function searchReadsTheTypesOfItsIntegration(): void
    {
        $this->assertSame([true, true, false, false, false, true, false, false, true, true, false, false], $this->getRun()['search']);
    }

    # The RSS channel of a type with the integration lists what a guest may read; a type without it has no items and is not offered
    #[Test]
    public function rssServesTheTypesOfItsIntegration(): void
    {
        $this->assertSame([200, true, false, false, true, false, true, false], $this->getRun()['rss']);
    }

    # Two instances of blocks/node.php show their own type and mode; an invalid parameter switches its instance off for visitors and shows the problem to an administrator
    #[Test]
    public function theNodeBlockIsDrivenByTheParametersOfItsInstance(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, true, false, true, true, false, true], $run['blocks']);
        $this->assertSame([200, true, true, 303, '{"type":"","mode":"last","limit":5}', 303, '{"type":"docs","mode":"last","limit":3}'], $run['editor'],
            'The editor misses the fields of node.php, stored an invalid parameter or did not store a valid one');
    }

    # The search screen toggles the integration inside the type itself, as a new version of that type
    #[Test]
    public function aSharedScreenTogglesTheIntegrationOfTheType(): void
    {
        $this->assertSame([true, 303, null, 2, false], $this->getRun()['toggle']);
    }

    # A page of the results reads the Node rows up to its own end and counts the rest: one result a page, the newer material first and a link to the next page
    #[Test]
    public function searchPagesTheRowsOfNodeByTheirCount(): void
    {
        $this->assertSame([true, false, true, false, true], $this->getRun()['paged']);
    }

    # The sitemap reads the materials of its types as a guest in cursor batches, escapes its addresses, and the HTML map shows a type with its open categories only
    #[Test]
    public function theSitemapWalksTheTypesOfItsIntegration(): void
    {
        $map = $this->getRun()['sitemap'];
        $this->assertSame(['success', 607, true, false, 0], [$map['status'], $map['count'], $map['valid'], $map['raw'], $map['parts']]);
        $this->assertSame([true, true, false], $map['list']);
        $this->assertSame([true, false], $map['cats'], 'A closed category reached the map');
        $this->assertSame([true, true, false, false, true, false], $map['items'], 'A closed, pending or disabled material reached the map');
        $this->assertSame(601, $map['bulk'], 'The cursor lost or repeated materials across batches');
        $this->assertSame([true, false, false], $map['txt'], 'The HTML map misses the open category or lists materials of Node');
    }

    # The shared code names no type: the adapters, the block and the generators read the registry and the settings of each type
    #[Test]
    public function theSharedCodeNamesNoType(): void
    {
        foreach (['core/system.php', 'core/user.php', 'blocks/node.php', 'modules/search/index.php', 'modules/rss/index.php'] as $path) {
            $code = self::getFile($path);
            foreach (["'news'", "'docs'", "'faq'", "'pages'"] as $name) $this->assertStringNotContainsString('=== '.$name, $code, $path.' compares with the type '.$name);
        }
        $pres = self::getFile('modules/presentation/index.php');
        foreach (['getNodeModeType($mode)', "\$read('article')", "\$read('docs')", "\$read('files')"] as $one) $this->assertStringContainsString($one, $pres);
        $this->assertStringContainsString("getNodeModeType('faq')", self::getFile('templates/lite/index.php'));
        $this->assertStringContainsString('`param` VARCHAR(255) NOT NULL DEFAULT \'\'', self::getFile('setup/sql/table.sql'));
        $this->assertStringContainsString("CALL addcol('{prefix}_blocks', 'param'", self::getFile('setup/sql/table_update6_3.sql'));
    }
}
