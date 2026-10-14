<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The building of an address of docs/10-URL-2026.md, batch 5, over route_probe.php build: the links the pages print, the statements of the lists, the builder itself
final class RouteBuildTest extends TestCase
{
    # The statements of each page before batch 5, measured by the same run on the tree of batch 4; a list may run one more and no other page any more
    private const BASE = ['news' => 10, 'news/open' => 12, 'news?page=2' => 10, 'news/alpha' => 8, 'forum' => 4, 'forum/talk' => 8, 'forum/topic-one' => 8, 'users' => 5,
        'voting' => 5, 'voting/poll-one' => 9, 'users/anna' => 14];

    private static array $probe = [];

    # Run the probe once in its build mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_build_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' build 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The pages wrote PHP or SQL errors');
            self::$probe = $data['runs']['build'];
        }
        return self::$probe;
    }

    # Every row of the address table built from its entity: the stored path absorbs the keys of its page, an operation and its id form the path, the rest is the sorted query
    #[Test]
    public function everyRowOfTheTableIsBuiltFromItsEntity(): void
    {
        $want = ['home' => '/news', 'node' => '/news/alpha', 'cat' => '/news/open', 'page' => '/news/open?page=2', 'first' => '/news/open', 'sort' => '/news?let=a&order=title',
            'topic' => '/forum/topic-one', 'section' => '/forum/talk', 'profile' => '/users/anna', 'poll' => '/voting/poll-one', 'add' => '/news/add', 'asset' => '/news/asset/2',
            'login' => '/account/login', 'privat' => '/account/privat?uname=anna'];
        $table = $this->getRun()['child']['table'];
        $this->assertSame($want, array_diff_key($table, ['public' => 0]));
        $this->assertMatchesRegularExpression('#^http://127\.0\.0\.1:\d+/news/alpha\?com=2$#', $table['public'], 'The absolute address is not the path on the configured host');
    }

    # A material that has no path by design, pending or of a private type, is passed with the route null and keeps its view by id
    #[Test]
    public function aMaterialWithoutAPathKeepsItsViewById(): void
    {
        $this->assertSame(['pending' => [null, '/news/view/104'], 'help' => '/help/view/7'], $this->getRun()['child']['byid']);
    }

    # A material published by the public form is answered on the path its publication gave it, and an approval through the writer answers the path it gave
    #[Test]
    public function aPublicationAnswersThePathItGave(): void
    {
        $this->assertSame([303, '/news/probe-built'], $this->getRun()['added'], 'The submission does not redirect to the path of the published material');
        $this->assertSame(['news/pending-one', 'news/pending-one'], $this->getRun()['child']['approved'], 'The approved material is answered without its new path');
    }

    # A link of an entity page asked without the key route still answers, and the site log names the call that forgot it
    #[Test]
    public function aForgottenRouteIsNamedWithItsCaller(): void
    {
        $forgot = $this->getRun()['child']['forgot'];
        $this->assertSame('/news/view/101', $forgot['url']);
        $this->assertCount(1, $forgot['log'], 'The forgotten route left no single warning');
        $this->assertSame(['warning', 'getSeoUrl: an entity link without its route', 'news'], [$forgot['log'][0]['level'], $forgot['log'][0]['msg'], $forgot['log'][0]['name']]);
        $this->assertStringStartsWith('route_probe.php:'.$forgot['line'].' ', $forgot['log'][0]['caller'], 'The warning does not name the caller');
    }

    # The pages print the links of their entities on the stored paths their readers joined, the pager on page and the forms of the operations on their paths
    #[Test]
    public function thePagesLinkTheirEntitiesOnTheirPaths(): void
    {
        $want = [
            'news' => ['/news/beta', '/news/view/105', '/news/open', '/users/anna', '/news?page=2', '/news?dir=asc&order=title', '/news?let=A'],
            'news/alpha' => ['/news', '/news/open', '/users/anna', '/news/asset/2', '/news/report/2'],
            'news/open' => ['/news/alpha', '/news/open?let=A'],
            'forum/talk' => ['/forum/topic-one', '/forum/topic-one?last=1'],
            'voting' => ['/voting/poll-one'],
            'account' => ['/account/passlost', '/account/newuser'],
            'news/add' => ['/news/add'],
        ];
        $links = $this->getRun()['links'];
        foreach ($want as $page => $list) $this->assertSame($list, array_values(array_intersect($list, $links[$page])), 'The page '.$page.' misses a link of its entities');
        $this->assertSame([], $this->getRun()['warn'], 'A page built an entity link without its route');
    }

    # A material and a forum topic of one id kept as favorites each link their own path, in the list of favorites and on the cabinet home
    #[Test]
    public function favoritesOfOneIdLinkTheirOwnPaths(): void
    {
        $links = $this->getRun()['links'];
        foreach (['account/favorites', 'cabinet'] as $page) {
            $both = ['/news/alpha', '/forum/collide'];
            $this->assertSame($both, array_values(array_intersect($both, $links[$page])), 'The page '.$page.' mixes the paths');
            $this->assertNotContains('/forum/alpha', $links[$page]);
            $this->assertNotContains('/news/collide', $links[$page]);
        }
    }

    # A page opened at a path of two segments and more resolves no address against that path: every link, form, htmx call, picture and fetch starts at the root
    #[Test]
    public function aPageAtAPathCarriesNoRelativeAddress(): void
    {
        foreach ($this->getRun()['relative'] as $page => [$code, $list]) {
            $this->assertSame(200, $code, 'The page '.$page.' does not answer');
            $this->assertSame([], $list, 'The page '.$page.' carries relative addresses');
        }
        $this->assertCount(23, $this->getRun()['relative'], 'The run did not visit every page');
    }

    # The paths come from joins of the readers and from the category map, so no list runs more than one statement above the run before batch 5
    #[Test]
    public function theListsCountNoMoreThanTheBaselinePlusOne(): void
    {
        $counts = $this->getRun()['counts'];
        $this->assertSame(array_keys(self::BASE), array_keys($counts));
        foreach (self::BASE as $page => $num) $this->assertLessThanOrEqual($num + 1, $counts[$page], 'The page '.$page.' runs more statements than the baseline allows');
        foreach ($counts as $page => $num) $this->assertGreaterThan(0, $num, 'The page '.$page.' shows no count of statements');
    }
}
