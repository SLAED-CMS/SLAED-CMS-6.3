<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;

# The addresses and feeds of Node over route_probe.php seo: the canonical address, translated categories, the feeds, the retired addresses and the support card
final class NodeRouteSeoTest extends NodeRouteCase
{
    # The canonical address of a view and a list names no foreign parameter of the query, the start page keeps the address of the site and its slogan
    #[Test]
    public function theCanonicalAddressLeavesForeignParametersOut(): void
    {
        $run = $this->getMode('seo');
        $site = $run['site'];
        $this->assertSame([$site.'/news/view/102', $site.'/news/view/102', $site.'/news?cat=1',
            $site.'/news?page=2', $site.'/', $site.'/', true], $run['canon'], 'A canonical address carries a foreign parameter or a view is not indexed');
        $this->assertSame(['Probe slogan of the site', true, ''], $run['desc'], 'The start page on a Node type lost the slogan as its description');
    }

    # A category named by a language constant shows its translation in the head of the view, the list and the feed
    #[Test]
    public function aConstantCategoryTitleIsTranslated(): void
    {
        $this->assertSame([false, true, false, false, true], $this->getMode('seo')['const'], 'A category title stays a language constant');
    }

    # The block and the feed of a type without the publication sort still run by date while its own list keeps the title order
    #[Test]
    public function feedsRunByDateWhateverTheListSorts(): void
    {
        $this->assertSame([true, true, ['Doc one', 'Zebra doc']], $this->getMode('seo')['order'], 'A feed follows the list sort of its type');
    }

    # A page past the end with an explicit default sort is not found at once, a page inside still goes to the clean address
    #[Test]
    public function aPagePastTheEndIsNotFoundBeforeAnyRedirect(): void
    {
        $this->assertSame([404, '', 301, '/news?page=2'], $this->getMode('seo')['bound'], 'The redirect of the default sort leads to a page that does not exist');
    }

    # A path that is no address finds the error page, a path after index.php goes 301 to its page in one hop, after another script to that script; an address answers
    #[Test]
    public function aRetiredAddressIsNotFoundOrMoved(): void
    {
        $run = $this->getMode('seo');
        $site = $run['site'];
        $this->assertSame([404, true, 404, 404, [301, $site.'/sitemap'], [301, $site.'/'], [301, $site.'/'], [301, '/admin.php'], [404, ''], [404, ''], 403, 410], $run['retired'],
            'A retired address renders a page, redirects elsewhere or a refusal of the light path is not gone');
        $this->assertSame([200, 200, 200, 200, true], $run['kept'], 'An address of the site or a file of the document root no longer answers');
    }

    # A name without a feed is not found, no name takes the start feed, the alternate link names the feed of the page and a description links absolutely
    #[Test]
    public function aFeedExistsOnlyWhereATypeHasOne(): void
    {
        $run = $this->getMode('seo');
        $this->assertSame([404, 404, 404, 200, true, $run['site'].'/index.php?go=rss&name=docs', $run['site'].'/index.php?go=rss&name=news', true, false], $run['rss'],
            'A feed answers without rss, the alternate link names a wrong feed or a description links relatively');
        $this->assertSame(['news', 'docs'], $run['data']['feeds'], 'The list of feeds differs from the types with rss');
    }

    # A channel names the site, the type and a readable category, links its list, dates itself by its newest item, answers 304 to its own tag and 404 for a closed category
    #[Test]
    public function aFeedNamesItsListAndRevalidates(): void
    {
        $run = $this->getMode('seo');
        $feed = $run['feed'];
        $site = $feed['site'];
        $this->assertSame([$site.' — News', $site.' — News — Open', $site.' — News — Members'], $feed['title'], 'The channel title misses the site, the type or the category');
        $this->assertSame([$run['site'].'/news', $run['site'].'/news?cat=1'], $feed['link'], 'The channel links other than its list');
        $this->assertSame([true, true, true, 200, 0], $feed['built'], 'lastBuildDate is not the newest item of the channel, or an empty channel carries one');
        $this->assertSame('Copyright (c) '.$site, $feed['copy'], 'The copyright names other than the site');
        $this->assertSame([true, true, 304, '', 200, true], $feed['cache'], 'The channel is not revalidated by its tag, or two read sets share a tag');
        $this->assertSame([404, 200, 404, 404], $feed['closed'], 'A closed, unknown or foreign category gave a channel');
    }

    # The support card of the administration shows every root message beyond the first page and every reply beyond the cap of a branch
    #[Test]
    public function theSupportCardShowsTheWholeCorrespondence(): void
    {
        $this->assertSame([303, true, 200, 20, 6], $this->getMode('seo')['support'], 'The support card hides a part of the correspondence');
    }

    # A stale block stays off without a log line per render; the related field of a moderator binds its hint, and a plain writer is not offered it
    #[Test]
    public function aStaleBlockIsQuietAndTheRelatedHintIsBound(): void
    {
        $run = $this->getMode('seo');
        $this->assertSame([false, false, true], $run['stale'], 'A stale block shows to a guest or writes the site log on every render');
        $this->assertSame([true, true, false, false], $run['hint'],
            'The hint of the related field has no id or is not described by its input, or a writer who is no moderator is offered the field');
    }
}
