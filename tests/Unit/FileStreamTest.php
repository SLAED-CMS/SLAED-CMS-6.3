<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S12 of docs/node: the one file answer of the project, getFileStream(), and the private revalidation of Cache it
 * rides on. Every case is a real HTTP exchange with the built-in web server running tests/Support/web_probe.php, which
 * serves scratch fixtures through the shipped function: the compatible two-argument download, the closed inline registry,
 * the private cache with entity tag and date, HEAD, one byte range, If-Range, 416, the $start callback and the bounded
 * streaming of a large file that stops once the client goes away.
 */
final class FileStreamTest extends TestCase
{
    private static $proc = null;
    private static int $port = 0;
    private static string $work = '';

    # Start the built-in server over a fresh scratch root with the fixtures; a server that does not come up is a failure, not a skip
    public static function setUpBeforeClass(): void
    {
        self::$work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_file_stream';
        self::deleteTree(self::$work);
        mkdir(self::$work.'/files', 0777, true);
        $plain = '';
        for ($i = 0; $i < 1000; $i++) $plain .= chr($i % 251);
        file_put_contents(self::$work.'/files/plain.bin', $plain);
        file_put_contents(self::$work.'/files/empty.bin', '');
        $hand = fopen(self::$work.'/files/big.bin', 'wb');
        $block = str_repeat('0123456789abcdef', 65536);
        for ($i = 0; $i < 32; $i++) fwrite($hand, $block);
        fclose($hand);
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        self::$port = (int)substr(strrchr((string)stream_socket_get_name($sock, false), ':'), 1);
        fclose($sock);
        $env = getenv() + ['SLAED_WEB_ROOT' => self::$work];
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, dirname(__DIR__).'/Support/web_probe.php'];
        self::$proc = proc_open($cmd, [1 => ['file', self::$work.'/server.log', 'a'], 2 => ['file', self::$work.'/server.log', 'a']], $pipes, self::$work, $env);
        set_error_handler(static fn(): bool => true);
        for ($i = 0; $i < 50; $i++) {
            $test = stream_socket_client('tcp://127.0.0.1:'.self::$port, $no, $err, 1);
            if ($test) break;
            usleep(100000);
        }
        restore_error_handler();
        if (!$test) self::fail('The built-in server did not start');
        fclose($test);
    }

    # Stop the server and remove the scratch root
    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        self::deleteTree(self::$work);
    }

    # Remove one directory tree
    private static function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (scandir($dir) as $one) {
            if ($one === '.' || $one === '..') continue;
            is_dir($dir.'/'.$one) ? self::deleteTree($dir.'/'.$one) : unlink($dir.'/'.$one);
        }
        rmdir($dir);
    }

    # Send one raw HTTP/1.0 request to /stream and answer the status, the headers by lowercase name and the body; a positive cut closes the socket after that many body bytes
    private function getReply(string $method, array $query, array $head = [], int $cut = 0): array
    {
        if (is_file(self::$work.'/start.log')) unlink(self::$work.'/start.log');
        if (is_file(self::$work.'/last.json')) unlink(self::$work.'/last.json');
        $sock = stream_socket_client('tcp://127.0.0.1:'.self::$port, $no, $err, 5);
        $this->assertNotFalse($sock, 'The server refused the connection: '.$err);
        $lines = $method.' /stream?'.http_build_query($query)." HTTP/1.0\r\nHost: 127.0.0.1\r\n";
        foreach ($head as $name => $val) $lines .= $name.': '.$val."\r\n";
        fwrite($sock, $lines."\r\n");
        $raw = '';
        while (!feof($sock)) {
            $raw .= (string)fread($sock, 65536);
            $split = strpos($raw, "\r\n\r\n");
            if ($cut > 0 && $split !== false && strlen($raw) - $split - 4 >= $cut) break;
        }
        fclose($sock);
        [$top, $body] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
        $rows = explode("\r\n", $top);
        $status = (int)(explode(' ', (string)array_shift($rows))[1] ?? 0);
        $heads = [];
        foreach ($rows as $row) {
            [$name, $val] = array_pad(explode(':', $row, 2), 2, '');
            $heads[strtolower(trim($name))][] = trim($val);
        }
        return ['status' => $status, 'head' => $heads, 'body' => $body, 'starts' => $this->getStarts()];
    }

    # How many times the $start callback ran in the last request
    private function getStarts(): int
    {
        usleep(20000);
        return is_file(self::$work.'/start.log') ? strlen((string)file_get_contents(self::$work.'/start.log')) : 0;
    }

    # One header value of a reply, or null when it was not sent
    private function getHead(array $reply, string $name): ?string
    {
        return $reply['head'][$name][0] ?? null;
    }

    # The fixture of one thousand bytes
    private function getPlain(): string
    {
        return (string)file_get_contents(self::$work.'/files/plain.bin');
    }

    # The old two-argument call stays an opaque attachment that is never stored, with the whole file, and a missing file is a bare 404
    #[Test]
    public function theTwoArgumentCallStaysAnOpaqueStoredNothingDownload(): void
    {
        $one = $this->getReply('GET', ['f' => 'plain.bin']);
        $this->assertSame(200, $one['status']);
        $this->assertSame('application/octet-stream', $this->getHead($one, 'content-type'));
        $this->assertStringStartsWith('attachment; filename="plain.bin"', (string)$this->getHead($one, 'content-disposition'));
        $this->assertStringStartsWith('private, no-store', (string)$this->getHead($one, 'cache-control'), 'The uncached answer is not the private no-store one of the preview');
        $this->assertStringContainsString('no-transform', (string)$this->getHead($one, 'cache-control'));
        $this->assertNull($this->getHead($one, 'etag'));
        $this->assertSame('1000', $this->getHead($one, 'content-length'));
        $this->assertSame('nosniff', $this->getHead($one, 'x-content-type-options'));
        $this->assertSame($this->getPlain(), $one['body']);
        $this->assertSame(404, $this->getReply('GET', ['f' => 'none.bin'])['status']);
        $none = $this->getReply('GET', ['f' => 'none.bin', 'mime' => 'image/png', 'start' => 1]);
        $this->assertSame([404, 0], [$none['status'], $none['starts']], 'a missing file never starts the counter');
    }

    # Only the closed registry of raster images, audio and video keeps its type and may be shown inline; everything else is an opaque attachment
    #[Test]
    public function onlyTheClosedRegistryIsShownInline(): void
    {
        $png = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'image/png', 'inline' => 1]);
        $this->assertSame('image/png', $this->getHead($png, 'content-type'));
        $this->assertStringStartsWith('inline;', (string)$this->getHead($png, 'content-disposition'));
        $keep = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'video/mp4']);
        $this->assertSame('video/mp4', $this->getHead($keep, 'content-type'));
        $this->assertStringStartsWith('attachment;', (string)$this->getHead($keep, 'content-disposition'), 'inline is asked for, never assumed');
        foreach (['image/svg+xml', 'text/html', 'application/xml', 'text/xml', 'application/pdf', 'application/x-unknown', 'broken', ''] as $mime) {
            $one = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => $mime, 'inline' => 1]);
            $this->assertSame('application/octet-stream', $this->getHead($one, 'content-type'), $mime);
            $this->assertStringStartsWith('attachment;', (string)$this->getHead($one, 'content-disposition'), $mime);
        }
    }

    # The name of the attachment is its last segment, encoded, so no separator and no header line of a crafted name survive
    #[Test]
    public function theNameCarriesNoSeparatorAndNoHeaderLine(): void
    {
        $one = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'image/png', 'n' => "a/b\\..\\c\r\nX-Evil: 1.png"]);
        $this->assertSame(200, $one['status']);
        $this->assertArrayNotHasKey('x-evil', $one['head']);
        $disp = (string)$this->getHead($one, 'content-disposition');
        $this->assertStringContainsString('filename="c%0D%0AX-Evil%3A%201.png"', $disp);
        $this->assertStringNotContainsString('/', substr($disp, (int)strpos($disp, 'filename=')));
    }

    # A cached answer is private and revalidated, carries its tag and date, keeps the cookies of the visitor and drops the session Pragma
    #[Test]
    public function theCachedAnswerIsPrivateAndRevalidated(): void
    {
        $one = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'image/png', 'cached' => 1, 'cookie' => 1]);
        $this->assertSame('private, no-cache, must-revalidate, no-transform', $this->getHead($one, 'cache-control'));
        $this->assertMatchesRegularExpression('#^"[a-f0-9]{32}"$#', (string)$this->getHead($one, 'etag'));
        $this->assertSame(gmdate('D, d M Y H:i:s', (int)filemtime(self::$work.'/files/plain.bin')).' GMT', $this->getHead($one, 'last-modified'));
        $this->assertSame('probe=1; path=/', $this->getHead($one, 'set-cookie'));
        $this->assertNull($this->getHead($one, 'pragma'));
        $this->assertSame('bytes', $this->getHead($one, 'accept-ranges'));
        $again = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'image/png', 'cached' => 1]);
        $this->assertSame($this->getHead($one, 'etag'), $this->getHead($again, 'etag'), 'the tag is stable for an unchanged file');
        $this->assertFalse(is_dir(self::$work.'/cache'), 'no copy of the file is stored');
    }

    # If-None-Match wins over If-Modified-Since, the date alone decides without a tag, and a 304 carries no body and never starts the counter
    #[Test]
    public function conditionalRequestsAnswerNotModified(): void
    {
        $base = ['f' => 'plain.bin', 'mime' => 'image/png', 'cached' => 1, 'start' => 1];
        $etag = (string)$this->getHead($this->getReply('HEAD', $base), 'etag');
        $date = gmdate('D, d M Y H:i:s', time() + 3600).' GMT';
        $hit = $this->getReply('GET', $base, ['If-None-Match' => $etag]);
        $this->assertSame(304, $hit['status']);
        $this->assertSame('', $hit['body']);
        $this->assertSame(0, $hit['starts']);
        $this->assertSame(304, $this->getReply('GET', $base, ['If-None-Match' => '"x", W/'.$etag])['status'], 'a weak match of the list is enough');
        $this->assertSame(304, $this->getReply('GET', $base, ['If-None-Match' => '*'])['status']);
        $miss = $this->getReply('GET', $base, ['If-None-Match' => '"other"', 'If-Modified-Since' => $date]);
        $this->assertSame(200, $miss['status'], 'a tag that does not match is not rescued by the date');
        $this->assertSame(1, $miss['starts']);
        $this->assertSame(304, $this->getReply('GET', $base, ['If-Modified-Since' => $date])['status']);
        $this->assertSame(200, $this->getReply('GET', $base, ['If-Modified-Since' => 'Mon, 01 Jan 2001 00:00:00 GMT'])['status']);
        $head = $this->getReply('HEAD', $base, ['If-None-Match' => $etag]);
        $this->assertSame(304, $head['status']);
        $this->assertSame(200, $this->getReply('GET', ['f' => 'plain.bin'], ['If-None-Match' => $etag])['status'], 'an uncached answer is never conditional');
    }

    # HEAD sends the headers of the whole file without a body, ignores a range and never starts the counter
    #[Test]
    public function headSendsTheWholeFileHeadersWithoutBody(): void
    {
        $one = $this->getReply('HEAD', ['f' => 'plain.bin', 'mime' => 'image/png', 'cached' => 1, 'start' => 1], ['Range' => 'bytes=0-9']);
        $this->assertSame(200, $one['status']);
        $this->assertSame('1000', $this->getHead($one, 'content-length'));
        $this->assertNull($this->getHead($one, 'content-range'));
        $this->assertSame('', $one['body']);
        $this->assertSame(0, $one['starts']);
    }

    # One satisfiable range gets 206 with its exact bytes; open, closed, suffix and overlong forms are read, and only a range from byte zero starts the counter
    #[Test]
    public function oneRangeGetsPartialContent(): void
    {
        $plain = $this->getPlain();
        $base = ['f' => 'plain.bin', 'mime' => 'video/mp4', 'cached' => 1, 'start' => 1];
        $cases = ['bytes=0-99' => [0, 99, 1], 'bytes=100-199' => [100, 199, 0], 'bytes=900-' => [900, 999, 0], 'bytes=-100' => [900, 999, 0],
            'bytes=0-' => [0, 999, 1], 'bytes=990-5000' => [990, 999, 0], 'bytes=-5000' => [0, 999, 1], 'BYTES = 10-19' => [10, 19, 0]];
        foreach ($cases as $range => [$from, $last, $starts]) {
            $one = $this->getReply('GET', $base, ['Range' => $range]);
            $this->assertSame(206, $one['status'], $range);
            $this->assertSame('bytes '.$from.'-'.$last.'/1000', $this->getHead($one, 'content-range'), $range);
            $this->assertSame((string)($last - $from + 1), $this->getHead($one, 'content-length'), $range);
            $this->assertSame(substr($plain, $from, $last - $from + 1), $one['body'], $range);
            $this->assertSame($starts, $one['starts'], $range);
        }
    }

    # A malformed or unsatisfiable range gets 416 with the size and no body, several correct ranges get the whole file, and an unknown unit is ignored
    #[Test]
    public function badRangesAreRefusedAndSeveralAreIgnored(): void
    {
        $base = ['f' => 'plain.bin', 'mime' => 'video/mp4', 'cached' => 1, 'start' => 1];
        foreach (['bytes=1000-', 'bytes=5-1', 'bytes=abc', 'bytes=-0', 'bytes=', 'bytes=0-1,x', 'bytes=1-2-3'] as $range) {
            $one = $this->getReply('GET', $base, ['Range' => $range]);
            $this->assertSame(416, $one['status'], $range);
            $this->assertSame('bytes */1000', $this->getHead($one, 'content-range'), $range);
            $this->assertSame('', $one['body'], $range);
            $this->assertSame(0, $one['starts'], $range);
        }
        foreach (['bytes=0-1,5-6', 'items=0-5'] as $range) {
            $one = $this->getReply('GET', $base, ['Range' => $range]);
            $this->assertSame(200, $one['status'], $range);
            $this->assertSame($this->getPlain(), $one['body'], $range);
            $this->assertSame('video/mp4', $this->getHead($one, 'content-type'), $range.' is no multipart answer');
            $this->assertSame(1, $one['starts'], $range);
        }
        $none = $this->getReply('GET', ['f' => 'empty.bin', 'mime' => 'video/mp4'], ['Range' => 'bytes=0-']);
        $this->assertSame(416, $none['status']);
        $this->assertSame('bytes */0', $this->getHead($none, 'content-range'));
        $empty = $this->getReply('GET', ['f' => 'empty.bin', 'mime' => 'video/mp4']);
        $this->assertSame(200, $empty['status']);
        $this->assertSame('0', $this->getHead($empty, 'content-length'));
    }

    # If-Range with the current tag or the exact date keeps the range, anything else gets the whole file
    #[Test]
    public function ifRangeKeepsTheRangeOnlyForTheCurrentValidator(): void
    {
        $base = ['f' => 'plain.bin', 'mime' => 'video/mp4', 'cached' => 1];
        $head = $this->getReply('HEAD', $base);
        $this->assertSame(206, $this->getReply('GET', $base, ['Range' => 'bytes=0-9', 'If-Range' => (string)$this->getHead($head, 'etag')])['status']);
        $this->assertSame(206, $this->getReply('GET', $base, ['Range' => 'bytes=0-9', 'If-Range' => (string)$this->getHead($head, 'last-modified')])['status']);
        $this->assertSame(200, $this->getReply('GET', $base, ['Range' => 'bytes=0-9', 'If-Range' => '"stale"'])['status']);
        $this->assertSame(200, $this->getReply('GET', $base, ['Range' => 'bytes=0-9', 'If-Range' => 'W/'.$this->getHead($head, 'etag')])['status']);
    }

    # The whole body and the counter: $start runs exactly once for a whole GET
    #[Test]
    public function theCallbackRunsOnceForAWholeBody(): void
    {
        $one = $this->getReply('GET', ['f' => 'plain.bin', 'mime' => 'image/png', 'start' => 1]);
        $this->assertSame(200, $one['status']);
        $this->assertSame(1, $one['starts']);
    }

    # A large file streams in bounded blocks: the memory of PHP stays far below its size, and a client that goes away ends the loop at once
    #[Test]
    public function aLargeFileStreamsInBoundedBlocksAndStopsOnAbort(): void
    {
        $size = (int)filesize(self::$work.'/files/big.bin');
        $one = $this->getReply('GET', ['f' => 'big.bin', 'mime' => 'video/mp4']);
        $this->assertSame($size, strlen($one['body']));
        $last = $this->getLast();
        $this->assertLessThan(8 * 1048576, $last['peak'], 'the file reached the memory of PHP');
        $this->getReply('GET', ['f' => 'big.bin', 'mime' => 'video/mp4'], [], 65536);
        $last = $this->getLast();
        $this->assertSame(1, $last['status'], 'the server did not notice the client went away');
        $this->assertLessThan(10.0, $last['time']);
    }

    # The shutdown record of the last request, waited for a few seconds
    private function getLast(): array
    {
        for ($i = 0; $i < 100 && !is_file(self::$work.'/last.json'); $i++) usleep(100000);
        $data = json_decode((string)file_get_contents(self::$work.'/last.json'), true);
        $this->assertIsArray($data, 'The server wrote no shutdown record');
        return $data;
    }
}
