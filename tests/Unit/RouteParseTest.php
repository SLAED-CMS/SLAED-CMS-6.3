<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The resolution of a path of the Address Boundary of docs/ARCHITECTURE.md, over route_probe.php parse; a page is [status, title without the site name, canonical address]
final class RouteParseTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its parse mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_parse_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' parse 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The paths wrote PHP or SQL errors');
            self::$probe = $data['runs']['parse'];
        }
        return self::$probe;
    }

    # Every row of the address table opens its page as a path; the canonical address, built since batch 5 on the stored path, shows the entity the route keys named
    #[Test]
    public function everyPageOfTheTableOpensAsAPath(): void
    {
        $want = [
            'news' => [200, 'News', 'news'],
            'news/alpha' => [200, 'Alpha - Open - News', 'news/alpha'],
            'news/open' => [200, 'Open - News', 'news/open'],
            'news?let=B' => [200, 'News - B', ''],
            'news?order=title' => [200, 'News', ''],
            'forum' => [200, 'Форум', 'forum'],
            'forum/topic-one' => [200, 'Topic one - Talk - Форум', 'forum/topic-one'],
            'forum/talk' => [200, 'Форум Talk - Форум', 'forum/talk'],
            'users' => [200, 'Топ пользователи', 'users'],
            'voting/poll-one' => [200, 'Poll one - Опросы', 'voting/poll-one'],
            'news/view/101' => [200, 'Alpha - Open - News', 'news/alpha'],
            'account/login' => [200, 'Вход в панель управления - Профиль', ''],
            'news/old-alpha' => [200, 'Alpha - Open - News', 'news/alpha'],
            'news/add' => [200, 'Добавить - News', ''],
            'users/anna' => [200, 'anna - Персональная информация - Профиль', 'users/anna'],
            'account/privat' => [200, 'Личные сообщения - Профиль', ''],
        ];
        $this->assertSame($want, $this->getRun()['pages'], 'A path of the table does not open its page with the keys of its route');
    }

    # The grammar: a gone path and the paths of a deleted type answer 410, a path never given, a malformed operation and a module without operations answer 404
    #[Test]
    public function theGrammarAnswersGoneAndMissingPaths(): void
    {
        $want = ['news/gone-one' => 410, 'news/nosuch' => 404, 'recipes' => 410, 'recipes/old-dish' => 410, 'nosuch' => 404, 'nosuch/alpha' => 404, 'news/view/abc' => 404,
            'news/view/101/x' => 404, 'news/alpha/x' => 404, 'search/add' => 404, 'node' => 404];
        $this->assertSame($want, $this->getRun()['grammar']);
    }

    # The cut before the database: a 6.2 .html path, six segments, an empty segment and an encoded byte find no page; case, a trailing slash and /index.php/ still open it
    #[Test]
    public function theCutRefusesWhatNoAddressCanBe(): void
    {
        $none = [404, 'Ошибка 404 — Название сайта'];
        $want = ['news.html' => $none, 'news-view-101.html' => $none, 'a/b/c/d/e/f' => $none, 'news/a/b/c/d' => $none, 'News/Alpha' => [200, 'Alpha - Open - News'],
            'news/alpha/' => [200, 'Alpha - Open - News'], 'index.php/news/alpha' => [200, 'Alpha - Open - News'], 'news//alpha' => $none, 'news%2Falpha' => $none];
        $this->assertSame($want, $this->getRun()['cut']);
    }

    # A page two segments deep links every stylesheet and script from the root of the site, and each of them answers, so the page is not served bare
    #[Test]
    public function aDeepPathLinksItsAssetsFromTheRoot(): void
    {
        $this->assertSame(['count' => true, 'relative' => [], 'missing' => []], $this->getRun()['root'], 'A page at a path links an asset the browser resolves below the path');
    }

    # A key the path set wins over the same key of the query, a key it did not set stays, and a query address answers as before
    #[Test]
    public function theKeysOfThePathWinOverTheQuery(): void
    {
        $alpha = [200, 'Alpha - Open - News', 'news/alpha'];
        $want = [$alpha, $alpha, [200, 'News', 'news'], [200, 'Beta - News', 'news/beta']];
        $this->assertSame($want, $this->getRun()['wins']);
    }
}
