<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The one redirect of the Address Boundary of docs/ARCHITECTURE.md, over route_probe.php move; an answer is [status, Location], the probe site written as ~
final class RouteMoveTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its move mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_move_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' move 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The redirects wrote PHP or SQL errors');
            self::$probe = $data['runs']['move'];
        }
        return self::$probe;
    }

    # Each reason alone lands on the current address in one absolute hop: a former path, case, a slash, the script, a query address of 8.0 or 6.2, num and page 1
    #[Test]
    public function eachReasonLandsInOneHop(): void
    {
        $alpha = [301, '~/news/alpha'];
        $want = ['news/old-alpha' => $alpha, 'News/Alpha' => $alpha, 'news/alpha/' => $alpha, 'index.php/news/alpha' => $alpha,
            'index.php?name=news&op=view&id=101' => $alpha, 'news/view/101' => $alpha, 'news?num=2' => [301, '~/news?page=2'], 'news?page=1' => [301, '~/news'],
            'index.php' => [301, '~/'], 'index.php/' => [301, '~/'], 'index.php/index.php?name=voting' => [301, '~/voting'],
            'index.php?name=forum&op=view&id=601' => [301, '~/forum/topic-one'], 'index.php?name=forum&cat=10' => [301, '~/forum/talk'],
            'index.php?name=voting&op=view&id=7' => [301, '~/voting/poll-one'], 'index.php?name=news&cat=1&num=2' => [301, '~/news/open?page=2'],
            'index.php?name=pages&op=view&id=9' => $alpha, 'news/alpha' => [200, ''], 'news?page=2' => [200, ''],
            'index.php?name=account&op=view&uname=anna' => [301, '~/users/anna']];
        $this->assertSame($want, $this->getRun()['alone'], 'A reason is not answered by one 301 to the current address');
    }

    # The keys a path absorbs leave the query, every other key stays in its order; num yields to page, and the activation code keeps its num
    #[Test]
    public function theFunctionalQueryIsKept(): void
    {
        $want = ['index.php?name=news&op=view&id=101&com=2&utm=x' => [301, '~/news/alpha?com=2&utm=x'],
            'index.php?name=news&order=title&let=B' => [301, '~/news?order=title&let=B'], 'news?num=3&page=2' => [301, '~/news?page=2'],
            'index.php?name=account&op=activate&user=x&num=abc' => [301, '~/account/activate?user=x&num=abc'], 'index.php?name=news&op=add&cat=1' => [301, '~/news/add?cat=1']];
        $this->assertSame($want, $this->getRun()['kept']);
    }

    # A refused entity never leaks its path, POST and htmx stay, HEAD moves like GET, sorting stays, a gone path is gone, an operation without a name is not lost
    #[Test]
    public function refusalsAndMethodsAreNotMoved(): void
    {
        $want = ['closed' => [404, ''], 'closedq' => [404, ''], 'pending' => [404, ''], 'post' => [200, ''], 'htmx' => [200, ''], 'head' => [301, '~/news/alpha'],
            'sort' => [200, ''], 'gone' => [410, ''], 'bare' => [200, '']];
        $this->assertSame($want, $this->getRun()['left']);
        $this->assertSame('~/news?page=2', $this->getRun()['canon'], 'Page 2 of a list is not its own canonical address');
    }

    # A redirected material or topic counts no view and writes nothing after its read; the first real view after the redirects counts exactly one
    #[Test]
    public function aRedirectCountsNoView(): void
    {
        $this->assertSame([0, 1, 1], $this->getRun()['views'], 'A redirect counted a view: [topic after the redirects, material after one view, topic after one view]');
    }

    # The forced host and scheme join the other reasons in the same hop, a 6.2 address included; an endpoint keeps the early block and a refusal stays on its host
    #[Test]
    public function theHostAndSchemeJoinTheHop(): void
    {
        $run = $this->getRun();
        $this->assertSame([[301, '~/news/alpha'], [301, '~/news/alpha'], [301, '~/news/alpha?utm=x'], [200, ''], [301, '~/index.php?go=rss&name=news'], [404, '']], $run['host']);
        $this->assertSame([301, 'https://'], [$run['ssl'][0][0], substr($run['ssl'][0][1], 0, 8)], 'The forced scheme is not in the target');
        $this->assertStringEndsWith('/news/alpha', $run['ssl'][0][1]);
        $this->assertSame([200, ''], $run['ssl'][1], 'A request that came over https behind a proxy was moved');
    }
}
