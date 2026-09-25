<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S19.1 of docs/node: the input and output guards the implementation audit asked for. The behaviour is driven by tests/Support/route_probe.php with
 * the argument guard: the disposable database and scratch configuration of the S16 integrations plus a select field of accounts, a favorite worth points,
 * a hostile title and intro, a shop and an order, real HTTP requests of the main administrator, a poll administrator and a user, and the child mode guardext
 * that asks the service directly where no screen reaches it. The installer lock is proven by NodeProfileTest on a real installation.
 */
final class NodeGuardTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its guard mode and memoize the run; every admin write of it has to leave the PHP and SQL logs empty
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_node_guard';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' guard 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            $this->assertIsArray($data['runs']['guard']['child'] ?? null, 'The child mode guardext did not answer');
            self::$probe = $data['runs']['guard'];
        }
        return self::$probe;
    }

    # Search prints the title of a Node material as text and renders an intro without trusted tags in the safe mode, so neither markup reaches the page
    #[Test]
    public function searchPrintsNodeTextAsText(): void
    {
        $this->assertSame([true, false, true, false], $this->getRun()['search']);
    }

    # The fields screen writes only from a POST whose token is in the body and whose every area arrived whole; nothing of a refused form reaches a file
    #[Test]
    public function theFieldsScreenWritesOnlyAWholePostedForm(): void
    {
        $run = $this->getRun()['fields'];
        $this->assertSame([true, 302, true], $run['get'], 'A GET with the token in the address saved the fields');
        $this->assertSame([200, true, true], $run['cut'], 'A form cut by max_input_vars was written');
    }

    # A stored field keeps its name and the keys of its options on the server, a caption constant has to live in the site dictionary, and a whole int64 is a number
    #[Test]
    public function theFieldsScreenKeepsStoredNamesAndSiteWords(): void
    {
        $run = $this->getRun()['fields'];
        $this->assertSame([true, true], $run['name'], 'A renamed field was accepted');
        $this->assertSame([true, true], $run['key'], 'A removed option key was accepted');
        $this->assertSame([true, true], $run['word'], 'A constant of the admin dictionary was accepted as a caption');
        $this->assertSame([303, '_ACCOUNT'], $run['site'], 'A constant of the site dictionary was refused');
        $this->assertSame([true, 303, PHP_INT_MAX], array_slice($run['int'], 0, 3), 'A 19 digit int64 default was refused');
    }

    # Every published operation on a type leaves its proof and its administrator in the site journal
    #[Test]
    public function aTypeOperationIsJournaled(): void
    {
        $this->assertSame([['info', 'update', 'docs', 1, 2, 1]], $this->getRun()['fields']['int'][3]);
    }

    # The panel deletes a material only through the type it belongs to: a material of docs sent with the type news is not found and stays
    #[Test]
    public function aDeletionNamesTheTypeOfItsMaterial(): void
    {
        $this->assertSame([404, 1], $this->getRun()['delete']);
    }

    # Favorites take only a closed list of fixed modules with a visible target, and the limit of the site; points follow the stored rows alone
    #[Test]
    public function favoritesTakeRealTargetsWithinTheLimit(): void
    {
        $run = $this->getRun()['fav'];
        $this->assertTrue($run[0], 'The material offers no favorite switch');
        $this->assertSame([0, 0], [$run[2], $run[4]], 'An invented module or a missing forum topic was stored');
        $this->assertSame([1, 1], [$run[9], $run[10]], 'A hidden product or one beyond the limit was stored, or a visible target was not');
        $this->assertSame([2, ['news:102', 'shop:7']], [$run[11], $run[12]], 'Points were granted for a row that was not stored');
    }

    # A Node category is created by the writer: the right of its type, a parent of the same module, and every access rule with its group list stored
    #[Test]
    public function aNodeCategoryIsWrittenByTheWriter(): void
    {
        $run = $this->getRun();
        $child = $run['child'];
        $this->assertSame([false, 2, 'The context does not administer the type'], [$child['other']['ok'], $child['other']['code'], $child['other']['msg']]);
        $this->assertSame([true, true, 1], [$child['own']['ok'], $child['own']['value'], $child['rows']], 'Only the category of the own type is created');
        $this->assertSame([303, 0], $run['cats']['parent'], 'A parent of another module was accepted');
        $this->assertSame([1, [1, '2|1', '0|0', '2|1,0', '1|0']], $run['cats']['rights'], 'The access rules of a new category were lost');
        $this->assertSame(['2|1', '0|0'], $run['cats']['save'], 'The access rules of a saved category were lost');
    }

    # The state changing actions of shop and order refuse a GET with the token in the address and work as POST forms
    #[Test]
    public function shopAndOrderChangeStateOnlyByPost(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, [302, 302, 302, 302, 302, 302], true], $run['shop']['get'], 'A GET changed a client, a partner or a product');
        $this->assertSame([[2, 1, 1, 1, 1, 3], [0, 1, 1, 1, 0, 3]], $run['shop']['post'], 'A POST did not switch the client or the product');
        $this->assertSame([true, [1, 0, 0]], $run['order']['get'], 'A GET activated or deleted an order');
    }

    # An order is confirmed only by the switch 0 to 1: another target state is refused without a reward, the confirmation rewards once
    #[Test]
    public function anOrderIsRewardedOnlyForItsConfirmation(): void
    {
        $run = $this->getRun()['order'];
        $this->assertSame([1, 0, 0], $run['two']);
        $this->assertSame([1, 1, 1], $run['one']);
    }

    # The right of polls: an administrator of voting unlinks a poll through the screen, and a context without that right is refused by the service itself
    #[Test]
    public function aPollIsUnlinkedOnlyWithTheRightOfPolls(): void
    {
        $run = $this->getRun();
        $this->assertSame([303, 0, 0], $run['poll']);
        $this->assertSame([false, 2], [$run['child']['poll']['ok'], $run['child']['poll']['code']]);
    }
}
