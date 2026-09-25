<?php

declare(strict_types=1);

namespace Tests\Unit;

use Closure;
use Feed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

require_once dirname(__DIR__, 2).'/core/classes/feed.php';

/**
 * Stage S07 of docs/node: the shared Feed class. RSS 2.0, RSS 1.0 and Atom become the canonical Markdown byte for byte;
 * the transport is scripted through the two operations of the contract, so DNS and HTTP - redirects, a private target,
 * a rebound name, the byte and time bounds, conditional requests - are reproduced without any network.
 */
final class FeedTest extends TestCase
{
    private const CONF = ['bytes' => '2097152', 'timeout' => '10', 'redirects' => '3', 'max' => '50'];
    private const PUBLIC = '93.184.216.34';

    private array $gets = [];
    private array $asks = [];

    # A feed over a scripted transport: replies answer the get calls in order, the zone answers resolve by host - a list of lists answers successive lookups,
    # a closure answers a lookup that takes its own time
    private function getFeed(array $replies, array $zone = [], array $conf = []): Feed
    {
        $this->gets = [];
        $this->asks = [];
        $send = function (string $op, array $req) use ($replies, $zone): array {
            if ($op === 'resolve') {
                $this->asks[] = $req['host'];
                $one = $zone[$req['host']] ?? [self::PUBLIC];
                if ($one instanceof Closure) $one = $one();
                if ($one instanceof RuntimeException) throw $one;
                if (isset($one[0]) && is_array($one[0])) $one = $one[min(count($this->asks) - 1, count($one) - 1)];
                return ['addresses' => $one];
            }
            if ($op !== 'get') throw new RuntimeException('operation');
            $this->gets[] = $req;
            $one = $replies[count($this->gets) - 1] ?? ['code' => 599, 'headers' => [], 'body' => ''];
            if ($one instanceof Closure) return $one($req);
            if ($one instanceof RuntimeException) throw $one;
            return $one;
        };
        return new Feed($conf + self::CONF, $send);
    }

    # One 200 answer carrying an XML body under a feed type
    private static function getOk(string $xml, array $head = []): array
    {
        return ['code' => 200, 'headers' => $head + ['Content-Type' => ['application/rss+xml; charset=utf-8']], 'body' => $xml];
    }

    # One redirect answer
    private static function getMove(string $to, int $code = 302): array
    {
        return ['code' => $code, 'headers' => ['Location' => [$to]], 'body' => ''];
    }

    # A minimal RSS 2.0 document around the given items
    private static function getRss(string $items): string
    {
        return '<?xml version="1.0" encoding="utf-8"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>T</title>'.$items.'</channel></rss>';
    }

    #[Test]
    public function theClassKeepsTheContractShape(): void
    {
        $ref = new ReflectionClass(Feed::class);
        $this->assertTrue($ref->isFinal(), 'Feed must be final');
        $pub = array_map(fn(ReflectionMethod $m): string => $m->getName(), $ref->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($pub);
        $this->assertSame(['__construct', 'getFeedContent', 'getFeedUrl'], $pub);
        $this->assertTrue($ref->getMethod('getFeedUrl')->isStatic(), 'The address normalizer NodeSync shares must be static');
        $this->assertSame(['url' => 'https://example.com/a%7Cb?x=1', 'host' => 'example.com'], Feed::getFeedUrl(' https://EXAMPLE.com:443/a|b?x=1#top '));
        $this->assertSame(Feed::getFeedUrl('https://example.com/a%20b?x=1'), Feed::getFeedUrl(Feed::getFeedUrl('https://example.com/a%20b?x=1')['url']));
        $this->assertSame([], Feed::getFeedUrl('https://user:pw@example.com/feed'));
        $ctor = $ref->getConstructor();
        $this->assertSame('array $conf, ?Closure $send = null', implode(', ', array_map(
            fn($p): string => ($p->allowsNull() && $p->getType()->getName() !== 'array' ? '?' : '').$p->getType()->getName().' $'.$p->getName().($p->isOptional() ? ' = null' : ''),
            $ctor->getParameters()
        )));
        $res = $this->getFeed([self::getOk(self::getRss(''))])->getFeedContent('https://example.com/feed');
        $this->assertSame(['ok', 'changed', 'code', 'body', 'etag', 'modified', 'error'], array_keys($res));
    }

    #[Test]
    public function anRssDocumentBecomesTheCanonicalMarkdown(): void
    {
        $xml = '<?xml version="1.0" encoding="windows-1251"?><rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><title>Channel</title>'
            .'<item><title>Первая  новость</title><link>/news/1?a=1&amp;b=2</link><pubDate>Tue, 03 Jun 2025 09:39:21 +0200</pubDate>'
            .'<description><![CDATA[<p>First <b>para</b></p><script>alert(1)</script><style>p{}</style><p>Second<br>third</p>]]></description></item>'
            .'<item><title></title><link>https://ex.org/a</link><dc:date>2025-06-03T10:00:00Z</dc:date><description>plain</description></item>'
            .'<item><description>neither title nor link</description></item>'
            .'<item><title>Guid</title><guid>https://ex.org/g</guid><pubDate>yesterday</pubDate></item>'
            .'<item><title>Fake guid</title><guid isPermaLink="false">https://ex.org/n</guid></item>'
            .'</channel></rss>';
        $xml = iconv('UTF-8', 'windows-1251', $xml);
        $res = $this->getFeed([self::getOk($xml)])->getFeedContent('https://example.com/dir/feed.xml');
        $want = "## Первая новость\n\n2025\\-06\\-03 07\\:39 UTC\n\n[example\\.com](https://example.com/news/1?a=1&b=2)\n\nFirst para\n\nSecond\n\nthird\n\n"
            ."## ex\\.org\n\n2025\\-06\\-03 10\\:00 UTC\n\n[ex\\.org](https://ex.org/a)\n\nplain\n\n"
            ."## Guid\n\n[ex\\.org](https://ex.org/g)\n\n"
            ."## Fake guid\n";
        $this->assertTrue($res['ok']);
        $this->assertTrue($res['changed']);
        $this->assertSame(200, $res['code']);
        $this->assertSame($want, $res['body']);
        $this->assertSame('', $res['error']);
    }

    #[Test]
    public function atomAndRssOneBecomeTheSameShape(): void
    {
        $atom = '<?xml version="1.0"?><feed xmlns="http://www.w3.org/2005/Atom"><title>A</title>'
            .'<entry><title type="html">&lt;i&gt;Atom&lt;/i&gt; one</title><link rel="self" href="https://a.example/self"/><link href="/p/1"/>'
            .'<published>2025-01-01T00:00:00Z</published><updated>2025-01-02T03:04:05+03:00</updated>'
            .'<summary type="xhtml"><div xmlns="http://www.w3.org/1999/xhtml"><p>x</p><p>y <b>z</b></p></div></summary></entry>'
            .'<entry><title>Two</title><link rel="alternate" href="https://b.example/2"/><published>2025-02-03T04:05:06.123+00:00</published>'
            .'<content type="text">line one'."\n\n".'line   two</content></entry>'
            .'</feed>';
        $res = $this->getFeed([self::getOk($atom, ['Content-Type' => ['application/atom+xml']])])->getFeedContent('https://a.example/atom');
        $this->assertSame("## Atom one\n\n2025\\-01\\-02 00\\:04 UTC\n\n[a\\.example](https://a.example/p/1)\n\nx\n\ny z\n\n"
            ."## Two\n\n2025\\-02\\-03 04\\:05 UTC\n\n[b\\.example](https://b.example/2)\n\nline one\n\nline two\n", $res['body']);
        $rdf = '<?xml version="1.0"?><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns="http://purl.org/rss/1.0/" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<channel rdf:about="https://r.example/"><title>R</title></channel>'
            .'<item rdf:about="https://r.example/1"><title>One</title><link>https://r.example/1</link><dc:date>2025-03-04</dc:date><description>D</description></item></rdf:RDF>';
        $res = $this->getFeed([self::getOk($rdf, ['Content-Type' => ['application/rdf+xml']])])->getFeedContent('https://r.example/rdf');
        $this->assertSame("## One\n\n2025\\-03\\-04 00\\:00 UTC\n\n[r\\.example](https://r.example/1)\n\nD\n", $res['body']);
    }

    #[Test]
    public function theEntryCountFollowsRssMaxAndAnEmptyFeedIsAnEmptyBody(): void
    {
        $items = '';
        for ($i = 1; $i <= 5; $i++) $items .= '<item><title>N'.$i.'</title></item>';
        $res = $this->getFeed([self::getOk(self::getRss($items))], [], ['max' => '2'])->getFeedContent('https://example.com/f');
        $this->assertSame("## N1\n\n## N2\n", $res['body']);
        $res = $this->getFeed([self::getOk(self::getRss(''))])->getFeedContent('https://example.com/f');
        $this->assertTrue($res['ok']);
        $this->assertTrue($res['changed']);
        $this->assertSame('', $res['body']);
    }

    #[Test]
    public function theSameDocumentGivesTheSameBodyWhateverTheOldTemplateSays(): void
    {
        $xml = self::getRss('<item><title>A</title><link>https://example.com/a</link><pubDate>Tue, 03 Jun 2025 09:39:21 GMT</pubDate>'
            .'<description>&lt;p&gt;B&lt;/p&gt;</description></item>');
        $one = $this->getFeed([self::getOk($xml)])->getFeedContent('https://example.com/f')['body'];
        $two = $this->getFeed([self::getOk($xml)], [], ['temp' => '<table>[title]</table>'])->getFeedContent('https://example.com/f')['body'];
        $this->assertSame($one, $two);
        $this->assertSame("## A\n\n2025\\-06\\-03 09\\:39 UTC\n\n[example\\.com](https://example.com/a)\n\nB\n", $one);
    }

    #[Test]
    public function receivedTextIsEscapedAndNeverBecomesACommand(): void
    {
        $title = '[hide]x[/hide] [attach=a.png align=left title=t] **b** `c` <i>d</i> \\e #f *01 [block=1]';
        $desc = '&lt;p&gt;&amp;lt;script&amp;gt;x&amp;lt;/script&amp;gt; [url=javascript:alert(1)]y[/url]&lt;/p&gt;&amp;#2;k&amp;#3;';
        $xml = self::getRss('<item><title>'.htmlspecialchars($title, ENT_XML1).'</title><link>https://ex.org/p(1)]*01`\\x</link><description>'.$desc.'</description></item>'
            .'<item><title>Bad link</title><link>javascript:alert(1)</link></item>'
            .'<item><title>Credentials</title><link>https://user:pass@ex.org/</link></item>'
            .'<item><title>Six</title><link>https://[2001:4860::1]/</link></item>');
        $res = $this->getFeed([self::getOk($xml)])->getFeedContent('https://example.com/f');
        $want = '## \\[hide\\]x\\[\\/hide\\] \\[attach\\=a\\.png align\\=left title\\=t\\] \\*\\*b\\*\\* \\`c\\` \\<i\\>d\\<\\/i\\> \\\\e \\#f \\*01 \\[block\\=1\\]'."\n\n"
            .'[ex\\.org](https://ex.org/p%281%29%5D%2A01%60%5Cx)'."\n\n"
            .'\\<script\\>x\\<\\/script\\> \\[url\\=javascript\\:alert\\(1\\)\\]y\\[\\/url\\]'."\n\nk\n\n"
            ."## Bad link\n\n## Credentials\n\n## Six\n";
        $this->assertSame($want, $res['body']);
    }

    #[Test]
    public function conditionalRequestsBelongToTheRequestedAddress(): void
    {
        $etag = '"v1"';
        $mod = 'Mon, 21 Sep 2026 17:00:23 GMT';
        $res = $this->getFeed([['code' => 304, 'headers' => [], 'body' => '']])->getFeedContent('https://example.com/f', $etag, $mod);
        $this->assertSame(['ok' => true, 'changed' => false, 'code' => 304, 'body' => '', 'etag' => $etag, 'modified' => $mod, 'error' => ''], $res);
        $this->assertSame($etag, $this->gets[0]['headers']['If-None-Match']);
        $this->assertSame($mod, $this->gets[0]['headers']['If-Modified-Since']);
        $new = self::getOk(self::getRss(''), ['ETag' => ['W/"v2"'], 'Last-Modified' => ['Tue, 22 Sep 2026 10:00:00 GMT']]);
        $res = $this->getFeed([$new])->getFeedContent('https://example.com/f', $etag);
        $this->assertSame('W/"v2"', $res['etag']);
        $this->assertSame('Tue, 22 Sep 2026 10:00:00 GMT', $res['modified']);
        $res = $this->getFeed([self::getMove('https://example.com/g'), ['code' => 304, 'headers' => [], 'body' => '']])->getFeedContent('https://example.com/f', $etag);
        $this->assertFalse($res['ok'], 'A 304 behind a redirect answers validators that were never sent');
        $this->assertSame('status', $res['error']);
        $this->assertArrayNotHasKey('If-None-Match', $this->gets[1]['headers']);
        $res = $this->getFeed([self::getMove('https://example.com/g'), self::getOk(self::getRss(''), ['ETag' => ['"x"']])])->getFeedContent('https://example.com/f');
        $this->assertSame('', $res['etag'], 'A validator of another address is not returned for this one');
        $res = $this->getFeed([['code' => 304, 'headers' => [], 'body' => '']])->getFeedContent('https://example.com/f');
        $this->assertSame('status', $res['error'], 'A 304 on an unconditional request is not an answer');
        $bad = self::getOk(self::getRss(''), ['ETag' => ["\"a\"\r\nX: y"], 'Last-Modified' => ['yesterday']]);
        $this->getFeed([$bad])->getFeedContent('https://example.com/f', "\"a\"\r\nX-Inject: 1", 'yesterday');
        $this->assertArrayNotHasKey('If-None-Match', $this->gets[0]['headers']);
        $this->assertArrayNotHasKey('If-Modified-Since', $this->gets[0]['headers']);
        $this->assertSame(['User-Agent', 'Accept'], array_keys($this->gets[0]['headers']), 'No cookie, authorization or other header is ever sent');
    }

    #[Test]
    public function threeRedirectsAreFollowedAndTheFourthIsRefused(): void
    {
        $xml = self::getRss('<item><title>R</title><link>p</link></item>');
        $hops = [self::getMove('/b', 301), self::getMove('c/d', 307), self::getMove('//other.example/e', 308), self::getOk($xml)];
        $res = $this->getFeed($hops)->getFeedContent('https://example.com/a/f');
        $this->assertTrue($res['ok']);
        $this->assertSame(['https://example.com/a/f', 'https://example.com/b', 'https://example.com/c/d', 'https://other.example/e'], array_column($this->gets, 'url'));
        $this->assertSame(['example.com', 'example.com', 'example.com', 'other.example'], $this->asks, 'Every hop resolves its host again');
        $this->assertSame("## R\n\n[other\\.example](https://other.example/p)\n", $res['body'], 'Relative links resolve against the final address');
        $res = $this->getFeed([self::getMove('/1'), self::getMove('/2'), self::getMove('/3'), self::getMove('/4'), self::getOk($xml)])->getFeedContent('https://example.com/f');
        $this->assertSame(['ok' => false, 'code' => 302, 'error' => 'redirect'], ['ok' => $res['ok'], 'code' => $res['code'], 'error' => $res['error']],
            'Too many redirects report the code of the last one that arrived');
        $this->assertCount(4, $this->gets);
        $res = $this->getFeed([self::getMove('/1', 301), self::getMove('/2', 308)], [], ['redirects' => '1'])->getFeedContent('https://example.com/f');
        $this->assertSame(['redirect', 308], [$res['error'], $res['code']]);
        $res = $this->getFeed([self::getMove('/1')], [], ['redirects' => '0'])->getFeedContent('https://example.com/f');
        $this->assertSame('redirect', $res['error']);
        $res = $this->getFeed([['code' => 302, 'headers' => ['Location' => ['/a', '/b']], 'body' => '']])->getFeedContent('https://example.com/f');
        $this->assertSame(['redirect', 302], [$res['error'], $res['code']]);
        $res = $this->getFeed([self::getMove('ftp://example.com/f')])->getFeedContent('https://example.com/f');
        $this->assertSame('redirect', $res['error'], 'A redirect out of http and https is not followed');
    }

    #[Test]
    public function aPrivateTargetIsRefusedAtEveryHop(): void
    {
        $res = $this->getFeed([self::getMove('http://169.254.169.254/latest/meta-data/')])->getFeedContent('https://example.com/f');
        $this->assertSame(['address', 302], [$res['error'], $res['code']], 'A refusal after a redirect keeps the code of the answer that arrived');
        $this->assertCount(1, $this->gets);
        $res = $this->getFeed([self::getMove('https://inside.example/f')], ['inside.example' => ['10.0.0.5']])->getFeedContent('https://example.com/f');
        $this->assertSame('address', $res['error']);
        $this->assertCount(1, $this->gets);
        $res = $this->getFeed([], ['mixed.example' => [self::PUBLIC, '127.0.0.1']])->getFeedContent('https://mixed.example/f');
        $this->assertSame('address', $res['error'], 'One non-public address in the answer refuses the host');
        $this->assertSame([], $this->gets);
        $res = $this->getFeed([], ['six.example' => ['::ffff:127.0.0.1']])->getFeedContent('https://six.example/f');
        $this->assertSame('address', $res['error']);
        $res = $this->getFeed([], ['none.example' => []])->getFeedContent('https://none.example/f');
        $this->assertSame('address', $res['error']);
        $res = $this->getFeed([], ['dead.example' => new RuntimeException('dns')])->getFeedContent('https://dead.example/f');
        $this->assertSame('transport', $res['error']);
    }

    #[Test]
    public function aReboundNameIsRefusedAndEveryRequestIsPinnedToTheCheckedAddress(): void
    {
        $zone = ['feed.example' => [['93.184.216.34', '2606:4700::1111'], ['192.168.1.10']]];
        $res = $this->getFeed([self::getMove('/next'), self::getOk(self::getRss(''))], $zone)->getFeedContent('https://feed.example/f');
        $this->assertSame('address', $res['error'], 'The second lookup of the same name answered a private address');
        $this->assertCount(1, $this->gets);
        $this->assertSame('93.184.216.34', $this->gets[0]['ip'], 'The IPv4 address is preferred and the request is pinned to it');
        $this->getFeed([self::getOk(self::getRss(''))], ['v6.example' => ['2a00:1450:4001::2', '2606:4700::1111']])->getFeedContent('https://v6.example/f');
        $this->assertSame('2606:4700::1111', $this->gets[0]['ip']);
        $this->getFeed([self::getOk(self::getRss(''))])->getFeedContent('http://93.184.216.34/f');
        $this->assertSame([], $this->asks, 'An address literal needs no lookup');
        $this->assertSame('93.184.216.34', $this->gets[0]['ip']);
    }

    #[Test]
    public function theByteAndTimeBoundsComeFromTheConfiguration(): void
    {
        $res = $this->getFeed([self::getOk(str_repeat('x', 101))], [], ['bytes' => '100'])->getFeedContent('https://example.com/f');
        $this->assertSame(['bytes', 200], [$res['error'], $res['code']]);
        $this->assertSame(100, $this->gets[0]['bytes']);
        $this->getFeed([self::getOk(self::getRss(''))], [], ['timeout' => '7'])->getFeedContent('https://example.com/f');
        $this->assertGreaterThan(6.5, $this->gets[0]['timeout']);
        $this->assertLessThanOrEqual(7.0, $this->gets[0]['timeout']);
        $slow = function (array $req): array {
            usleep(1100000);
            return self::getOk(self::getRss(''));
        };
        $res = $this->getFeed([$slow], [], ['timeout' => '1'])->getFeedContent('https://example.com/f');
        $this->assertSame('timeout', $res['error'], 'The bound covers the whole operation, not one request');
    }

    #[Test]
    public function theNameLookupCountsAgainstTheTimeBound(): void
    {
        $late = function (): array {
            usleep(1100000);
            return ['10.0.0.5'];
        };
        $res = $this->getFeed([self::getOk(self::getRss(''))], ['slow.example' => $late], ['timeout' => '1'])->getFeedContent('https://slow.example/f');
        $this->assertSame(['timeout', 0], [$res['error'], $res['code']], 'A lookup that used up the bound is a timeout whatever it answered');
        $this->assertSame([], $this->gets);
        $res = $this->getFeed([self::getMove('https://slow.example/g', 301)], ['slow.example' => $late], ['timeout' => '1'])->getFeedContent('https://example.com/f');
        $this->assertSame(['timeout', 301], [$res['error'], $res['code']], 'The lookup of a later hop spends the same bound');
        $this->assertCount(1, $this->gets);
    }

    #[Test]
    public function theFeedPageKeepsTheOutcomeOfAnAddressForFifteenMinutes(): void
    {
        $script = dirname(__DIR__).'/Support/contract_probe.php';
        $raw = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' rssview 2>&1');
        $data = json_decode($raw, true);
        $this->assertIsArray($data, 'Probe rssview did not return JSON: '.$raw);
        $this->assertTrue($data['stored'], 'A stored outcome of the address is shown without a fetch');
        $this->assertFalse($data['aged'], 'An outcome older than 900 seconds is fetched again');
        $this->assertTrue($data['refusal']);
        $this->assertSame(['body' => ''], $data['kept'], 'The refusal of the new fetch is stored in place of the aged outcome');
        $this->assertTrue($data['fresh']);
        $this->assertTrue($data['badpage']);
        $this->assertSame(0, $data['badfiles'], 'An address outside the form of Feed is neither fetched nor stored');
    }

    #[Test]
    public function theCaseOfThePathReachesTheTransport(): void
    {
        $this->getFeed([self::getOk(self::getRss(''))])->getFeedContent('https://Feeds.Example.COM/News/RSS.xml?Id=A');
        $this->assertSame('https://feeds.example.com/News/RSS.xml?Id=A', $this->gets[0]['url'], 'Only the host loses its case; the path and query are another address');
        $this->assertSame(['feeds.example.com'], $this->asks);
    }

    public static function badLimits(): array
    {
        return [
            'missing' => [['bytes' => null]],
            'fraction' => [['timeout' => '1.5']],
            'negative' => [['redirects' => '-1']],
            'zero bytes' => [['bytes' => '0']],
            'zero timeout' => [['timeout' => '0']],
            'zero max' => [['max' => '0']],
            'padded' => [['bytes' => ' 100']],
            'leading zero' => [['timeout' => '010']],
            'array' => [['redirects' => ['3']]],
        ];
    }

    #[Test]
    #[DataProvider('badLimits')]
    public function aLimitOutOfShapeRefusesBeforeAnyRequest(array $over): void
    {
        $conf = array_filter($over + self::CONF, fn($v) => $v !== null);
        $res = (new Feed($conf, function (): array {
            $this->fail('No transport call may happen with a bad limit');
        }))->getFeedContent('https://example.com/f');
        $this->assertSame('config', $res['error']);
    }

    #[Test]
    public function integerLimitsFromTheAdminFormAreAccepted(): void
    {
        $res = $this->getFeed([self::getOk(self::getRss(''))], [], ['max' => 20, 'redirects' => 0])->getFeedContent('https://example.com/f');
        $this->assertTrue($res['ok']);
    }

    public static function refusedAddresses(): array
    {
        return [
            'ftp' => ['ftp://example.com/f', 'url'],
            'file' => ['file:///etc/passwd', 'url'],
            'javascript' => ['javascript:alert(1)', 'url'],
            'no scheme' => ['example.com/f', 'url'],
            'credentials' => ['https://user:pw@example.com/f', 'url'],
            'port' => ['https://example.com:8443/f', 'url'],
            'single label' => ['http://localhost/f', 'url'],
            'space' => ['https://example.com/a b', 'url'],
            'loopback' => ['http://127.0.0.1/f', 'address'],
            'metadata' => ['http://169.254.169.254/f', 'address'],
            'private' => ['http://192.168.0.1/f', 'address'],
            'v6 loopback' => ['http://[::1]/f', 'address'],
            'v6 unique local' => ['http://[fc00::1]/f', 'address'],
            'multicast' => ['http://224.0.0.1/f', 'address'],
        ];
    }

    #[Test]
    #[DataProvider('refusedAddresses')]
    public function anAddressOutsideThePolicyNeverReachesTheTransport(string $url, string $code): void
    {
        $res = $this->getFeed([])->getFeedContent($url);
        $this->assertSame($code, $res['error']);
        $this->assertSame([], $this->gets);
        $this->assertSame([], $this->asks);
    }

    #[Test]
    public function aBrokenDocumentOrAnswerIsARefusalWithoutPartialBody(): void
    {
        $cases = [
            'xml' => [self::getOk('<rss><channel><item><title>x</title></item>')],
            'doctype' => [self::getOk('<?xml version="1.0"?><!DOCTYPE rss [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
                .'<rss version="2.0"><channel><item><title>&x;</title></item></channel></rss>')],
            'entity' => [self::getOk('<?xml version="1.0"?><!ENTITY a "b"><rss/>')],
            'html root' => [self::getOk('<html><body>x</body></html>')],
            'no channel' => [self::getOk('<rss version="2.0"/>')],
            'empty' => [self::getOk('')],
        ];
        foreach ($cases as $name => $replies) {
            $res = $this->getFeed($replies)->getFeedContent('https://example.com/f');
            $got = ['ok' => $res['ok'], 'changed' => $res['changed'], 'body' => $res['body'], 'error' => $res['error']];
            $this->assertSame(['ok' => false, 'changed' => false, 'body' => '', 'error' => 'xml'], $got, $name);
        }
        $wide = '<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE rss [<!ENTITY e "EXPANDED">]><rss version="2.0"><channel><item><title>&e;</title></item></channel></rss>';
        $res = $this->getFeed([self::getOk("\xFF\xFE".mb_convert_encoding($wide, 'UTF-16LE', 'UTF-8'))])->getFeedContent('https://example.com/f');
        $this->assertSame(['xml', ''], [$res['error'], $res['body']], 'A document type the byte scan cannot see is refused after parsing');
        $wide = '<?xml version="1.0" encoding="UTF-16"?><rss version="2.0"><channel><item><title>Ю</title></item></channel></rss>';
        $res = $this->getFeed([self::getOk("\xFF\xFE".mb_convert_encoding($wide, 'UTF-16LE', 'UTF-8'))])->getFeedContent('https://example.com/f');
        $this->assertSame("## Ю\n", $res['body']);
        $res = $this->getFeed([self::getOk(self::getRss(''), ['Content-Type' => ['text/html; charset=utf-8']])])->getFeedContent('https://example.com/f');
        $this->assertSame('type', $res['error']);
        $res = $this->getFeed([['code' => 200, 'headers' => [], 'body' => self::getRss('<item><title>ok</title></item>')]])->getFeedContent('https://example.com/f');
        $this->assertSame("## ok\n", $res['body'], 'A response without a declared type is left to the XML parser');
        $res = $this->getFeed([['code' => 500, 'headers' => [], 'body' => 'secret error page']])->getFeedContent('https://example.com/f?token=secret');
        $this->assertSame(['status', 500, ''], [$res['error'], $res['code'], $res['body']]);
        $res = $this->getFeed([new RuntimeException('connection reset with secret')])->getFeedContent('https://example.com/f?token=secret');
        $this->assertSame('transport', $res['error']);
        $shapes = [
            ['code' => '200', 'headers' => [], 'body' => ''],
            ['code' => 200, 'headers' => ['a' => 'b'], 'body' => ''],
            ['code' => 200, 'headers' => [], 'body' => '', 'x' => 1],
            ['code' => 200, 'headers' => []],
        ];
        foreach ($shapes as $bad) {
            $this->assertSame('transport', $this->getFeed([$bad])->getFeedContent('https://example.com/f')['error']);
        }
        $res = $this->getFeed([self::getMove('/next', 307), $shapes[0]])->getFeedContent('https://example.com/f');
        $this->assertSame(['transport', 307], [$res['error'], $res['code']], 'A malformed answer after a redirect keeps the code that arrived');
    }

    # The source of one project file
    private static function getCode(string $path): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$path);
    }

    #[Test]
    public function theFiveOwnersRunOnFeedAndTheOldReaderIsGone(): void
    {
        $root = dirname(__DIR__, 2);
        $left = [];
        foreach (['index.php', 'admin', 'core', 'modules', 'blocks', 'plugins', 'setup', 'templates'] as $part) {
            $list = is_file($root.'/'.$part) ? [$root.'/'.$part] : [];
            if (is_dir($root.'/'.$part)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$part, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if (str_ends_with($file->getFilename(), '.php')) $list[] = $file->getPathname();
                }
            }
            foreach ($list as $file) {
                if (preg_match('/\brss_(read|load)\s*\(/', (string)file_get_contents($file))) $left[] = substr($file, strlen($root) + 1);
            }
        }
        $this->assertSame([], $left, 'The old reader is still called');
        $this->assertSame(2, substr_count(self::getCode('admin/modules/blocks.php'), 'getRssBody($url)'), 'Both block forms fetch through Feed');
        $this->assertStringContainsString('echo getRssView(getVar(', self::getCode('modules/account/index.php'));
        $this->assertStringContainsString('$cont .= getRssView($url);', self::getCode('modules/rss/index.php'));
        $core = self::getCode('core/system.php');
        $this->assertStringContainsString('$content = getRssBody($url) ?? $content;', $core, 'A failed refresh keeps the stored body');
        $this->assertStringContainsString("\$prs->filterContent(\$content, true, '', 2)", $core, 'A stored feed is rendered in safe mode');
        $this->assertStringContainsString("\$prs->filterDoc(\$body, true, '', 1)", $core, 'A feed a visitor chose is rendered safe and never enters the parser cache');
        $pre = "\$content = (\$url == '') ? \$prs->filterContent(\$content, false, 'all', 2) : '';";
        $this->assertStringContainsString($pre, $core, 'A feed block is never rendered in trusted mode');
        $this->assertStringNotContainsString('core/classes/feed.php', substr($core, 0, (int)strpos($core, 'function ')), 'Feed is loaded by its consumers only');
    }

    #[Test]
    public function theTransportBoundsLiveInTheStandardConfigAndTheTemplateIsGone(): void
    {
        $conf = (require dirname(__DIR__, 2).'/config/rss.php')['rss'];
        $this->assertSame(['2097152', '10', '3'], [$conf['bytes'] ?? null, $conf['timeout'] ?? null, $conf['redirects'] ?? null]);
        $this->assertArrayNotHasKey('temp', $conf);
        $admin = self::getCode('modules/rss/admin/index.php');
        $this->assertStringNotContainsString('temp', $admin, 'The template field and its save are removed');
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lng) {
            $this->assertStringNotContainsString('_RSSTEMP', self::getCode('admin/lang/'.$lng.'.php'), $lng);
        }
    }

    #[Test]
    public function theUpdateClearsTheStoredHtmlOfFeedBlocksAfterTheFieldBlock(): void
    {
        $code = self::getCode('setup/index.php');
        $at = strpos($code, '$bodytext .= setUpdateFields($db, $xprefix);');
        $this->assertNotFalse($at);
        $tail = substr($code, $at, 1200);
        $this->assertStringContainsString("SET content = \\'\\', time = \\'0\\' WHERE url != \\'\\'", $tail, 'The body is emptied and the next view refreshes at once');
        $this->assertStringContainsString("unset(\$rdata['temp']);", $tail);
        $this->assertStringContainsString("['bytes' => '2097152', 'redirects' => '3', 'timeout' => '10']", $tail, 'A site keeping its own rss.php gets the three bounds');
    }
}
