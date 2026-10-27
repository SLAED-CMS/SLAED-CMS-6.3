<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# A page of the layout at its address, over route_probe.php page: the kind page of the registry, its head, its audience, the page 404 of the layout and the link of the draft
final class RoutePageTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its page mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_page_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' page 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The pages wrote PHP or SQL errors');
            self::$probe = $data['runs']['page'];
        }
        return self::$probe;
    }

    # The answer of one request as status, location and title
    private function getLine(array $one): array
    {
        return [$one['code'], $one['move'], $one['title']];
    }

    # A page answers at its path with its own title, description, picture and canonical address; its former path and its name answer 301 to it, the query kept
    #[Test]
    public function aPageAnswersAtItsPath(): void
    {
        $page = $this->getRun()['pages'];
        $want = ['code' => 200, 'move' => '', 'title' => 'About', 'canon' => '~/about-us', 'robots' => 'index, follow', 'og' => '~/uploads/all/og.png',
            'desc' => 'All about us', 'draft' => false, 'dead' => false];
        $this->assertSame($want, $page['about-us']);
        $this->assertSame([301, '~/about-us', ''], $this->getLine($page['about']), 'a former path did not move');
        $this->assertSame([301, '~/about-us', ''], $this->getLine($page['index.php?name=page-10']), 'the name of a page did not move to its path');
        $this->assertSame([301, '~/about-us?utm=x', ''], $this->getLine($page['index.php?name=page-10&utm=x']));
        $this->assertSame([410, ''], array_slice($this->getLine($page['gone-page']), 0, 2));
    }

    # A hidden page carries noindex and no canonical address; a page for users denies a guest and opens for an account
    #[Test]
    public function aPageKeepsItsRobotsAndItsAudience(): void
    {
        $page = $this->getRun()['pages'];
        $this->assertSame([200, '', 'noindex, follow'], [$page['hidden']['code'], $page['hidden']['canon'], $page['hidden']['robots']]);
        $this->assertSame([403, 'Members'], [$page['members']['code'], $page['members']['title']]);
        $this->assertSame([200, '~/members'], [$page['members:anna']['code'], $page['members:anna']['canon']]);
    }

    # The name page-<key> and a path answer 404 for the home page, the page 404, a page of a slot, a page without a row and a page that is none, as a module past its head does
    #[Test]
    public function aPageWithoutAnAddressAnswers404(): void
    {
        $page = $this->getRun()['pages'];
        foreach (['page-10', 'docs-page', 'index.php?name=page-1', 'index.php?name=page-2', 'index.php?name=page-13', 'index.php?name=page-14', 'index.php?name=page-99',
            'nosuch', 'users?page=50'] as $path) {
            $this->assertSame([404, '', 'Lost here', 'noindex, follow', ''], [$page[$path]['code'], $page[$path]['move'], $page[$path]['title'], $page[$path]['robots'],
                $page[$path]['canon']], $path);
        }
    }

    # The page 404 of the layout without a section the visitor sees gives way to the standard page of the error, its code kept
    #[Test]
    public function anEmptyPage404GivesWayToTheStandardOne(): void
    {
        $this->assertSame([404, 'Ошибка 404 — Название сайта'], [$this->getRun()['empty']['code'], $this->getRun()['empty']['title']]);
    }

    # A valid link shows the draft of its page, a new page, the home page and a page of a slot unindexed and marked; a page of the slot of home elsewhere answers 404
    #[Test]
    public function theLinkOfTheDraftShowsTheDraft(): void
    {
        $draft = $this->getRun()['draft'];
        $want = ['about' => 'About draft', 'path' => 'About draft', 'fresh' => 'Fresh', 'home' => 'Название сайта', 'docs' => 'Docs'];
        foreach ($want as $key => $title) {
            $this->assertSame([200, '', $title, '', 'noindex, follow', true], [$draft[$key]['code'], $draft[$key]['move'], $draft[$key]['title'], $draft[$key]['canon'],
                $draft[$key]['robots'], $draft[$key]['draft']], $key);
        }
        $this->assertSame([404, 'Lost here', false], [$draft['blank']['code'], $draft['blank']['title'], $draft['blank']['draft']], 'a page of the home slot opened as home');
    }

    # A link of another page, an expired one, one of an earlier counter and a broken one answer 403 with the words of a dead link and show no draft
    #[Test]
    public function aDeadLinkOfTheDraftIsRefused(): void
    {
        $run = $this->getRun();
        foreach (['swap', 'expired', 'revoked', 'junk'] as $key) {
            $this->assertSame([403, true, false], [$run['draft'][$key]['code'], $run['draft'][$key]['dead'], $run['draft'][$key]['draft']], $key);
        }
        $this->assertSame([true, false, false, false, false], $run['data']['checks'], 'only the link of its page, counter and term may pass');
    }

    # The sitemap lists a page with an address of its own, for everyone and indexed, in the XML and in the text map; a hidden, closed or slot page stays out
    #[Test]
    public function theSitemapListsThePagesWithAnAddress(): void
    {
        $data = $this->getRun()['data'];
        $this->assertSame('success', $data['sitemap']);
        $this->assertSame(['about-us'], $data['locs']);
        $this->assertSame([true, false, false], $data['txt']);
    }
}
