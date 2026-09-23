<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

# The address policy lives in Upload alone, so this reader loads that class instead of carrying a second copy of the networks
if (!class_exists('Upload')) require_once __DIR__.'/upload.php';

# Fetches one RSS or Atom document and writes the canonical Markdown of docs/node/05-core-api.md: headings, UTC dates, checked links and plain-text descriptions
# Every hop resolves its host again, refuses a non-public address and is pinned to the checked one; size, total time and redirects are bounded by config/rss.php
# Received text is data and never markup: each ASCII punctuation mark it carries is escaped, so Parser shows it literally and no BB, Markdown or HTML command survives
# The transport is one closure with the two operations resolve and get; null selects the cURL implementation below, and a test hands in its own so no test reaches the network
final class Feed {
    private const ATOM = 'http://www.w3.org/2005/Atom';
    private const RSSONE = 'http://purl.org/rss/1.0/';
    private const DUBLIN = 'http://purl.org/dc/elements/1.1/';
    private const TYPES = ['application/rss+xml', 'application/atom+xml', 'application/rdf+xml', 'application/xml', 'text/xml'];
    private const MOVES = [301, 302, 303, 307, 308];
    # The date shapes of RFC 822 and RFC 3339 feeds use; the leading ! zeroes every field the text does not carry, so no part of a date is ever taken from the clock
    private const DATES = ['!D, d M Y H:i:s T', '!D, d M Y H:i T', '!d M Y H:i:s T', '!d M Y H:i T', '!Y-m-d\TH:i:sP', '!Y-m-d\TH:i:s.uP', '!Y-m-d\TH:i:s', '!Y-m-d'];
    # Elements whose content is executable, embedded or interface rather than text; the whole subtree is dropped
    private const SKIP = ['button', 'embed', 'head', 'iframe', 'math', 'noscript', 'object', 'script', 'select', 'style', 'svg', 'template', 'textarea', 'title'];
    # Elements that end a paragraph before and after themselves
    private const BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'dd', 'details', 'div', 'dl', 'dt', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'header', 'hr', 'li', 'main', 'nav', 'ol', 'p', 'pre', 'section', 'summary', 'table', 'td', 'th', 'tr', 'ul',
    ];
    private const DEPTH = 256;
    private const URLMAX = 2048;

    private array $conf;
    private ?Closure $send;

    # Keep the loaded rss scope and the optional trusted transport; nothing here reads the request or the configuration for a callback, so only code can hand a transport in
    public function __construct(array $conf, ?Closure $send = null) {
        $this->conf = $conf;
        $this->send = $send;
    }

    # Fetch one feed and answer the seven keys of the contract; a 304 on the validators of the requested address is ok without a body, every refusal is ok = false with a short code
    # The validators belong to the address they came from: they are sent on the first hop only and returned only when no redirect intervened
    public function getFeedContent(string $url, string $etag = '', string $modified = ''): array {
        $lim = $this->getFeedLimits();
        if ($lim === []) return $this->getFeedFail('config');
        if (!class_exists('DOMDocument') || !class_exists('Dom\HTMLDocument')) return $this->getFeedFail('support');
        $stop = hrtime(true) + $lim['timeout'] * 1000000000;
        $etag = $this->checkFeedEtag($etag) ? $etag : '';
        $modified = $this->checkFeedStamp($modified) ? $modified : '';
        $next = $url;
        for ($hop = 0; $hop <= $lim['redirects']; $hop++) {
            $norm = $this->getFeedUrl($next);
            if ($norm === []) return $this->getFeedFail('url');
            try {
                $addr = $this->getFeedAddress($norm['host']);
                if ($addr === '') return $this->getFeedFail('address');
                $left = ($stop - hrtime(true)) / 1000000000;
                if ($left <= 0) return $this->getFeedFail('timeout');
                $head = ['User-Agent' => 'SLAED Feed', 'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, text/xml;q=0.9'];
                if ($hop === 0 && $etag !== '') $head['If-None-Match'] = $etag;
                if ($hop === 0 && $modified !== '') $head['If-Modified-Since'] = $modified;
                $res = $this->getFeedReply('get', ['url' => $norm['url'], 'headers' => $head, 'timeout' => $left, 'bytes' => $lim['bytes'], 'ip' => $addr]);
            } catch (RuntimeException) {
                return $this->getFeedFail('transport');
            }
            if (hrtime(true) > $stop) return $this->getFeedFail('timeout');
            if (!$this->checkFeedReply($res)) return $this->getFeedFail('transport');
            $code = $res['code'];
            if (strlen($res['body']) > $lim['bytes']) return $this->getFeedFail('bytes', $code);
            $head = array_change_key_case($res['headers'], CASE_LOWER);
            if (in_array($code, self::MOVES, true)) {
                $loc = $head['location'] ?? [];
                $next = (count($loc) === 1) ? $this->getFeedLink($loc[0], $norm['url']) : '';
                if ($next === '') return $this->getFeedFail('redirect', $code);
                continue;
            }
            if ($code === 304) {
                if ($hop > 0 || ($etag === '' && $modified === '')) return $this->getFeedFail('status', $code);
                $tag = $this->getFeedValue($head, 'etag');
                $mod = $this->getFeedValue($head, 'last-modified');
                return ['ok' => true, 'changed' => false, 'code' => 304, 'body' => '', 'etag' => $tag ?: $etag, 'modified' => $mod ?: $modified, 'error' => ''];
            }
            if ($code !== 200) return $this->getFeedFail('status', $code);
            if (!$this->checkFeedType($head['content-type'] ?? [])) return $this->getFeedFail('type', $code);
            $body = $this->getFeedBody($res['body'], $norm['url'], $lim['max']);
            if ($body === null) return $this->getFeedFail('xml', $code);
            $tag = ($hop === 0) ? $this->getFeedValue($head, 'etag') : '';
            $mod = ($hop === 0) ? $this->getFeedValue($head, 'last-modified') : '';
            return ['ok' => true, 'changed' => true, 'code' => 200, 'body' => $body, 'etag' => $tag, 'modified' => $mod, 'error' => ''];
        }
        return $this->getFeedFail('redirect');
    }

    # The one refusal shape: nothing changed, no body, no validators, the final HTTP code when a response arrived and a short code carrying nothing of the response or address
    private function getFeedFail(string $err, int $code = 0): array {
        return ['ok' => false, 'changed' => false, 'code' => $code, 'body' => '', 'etag' => '', 'modified' => '', 'error' => $err];
    }

    # Read the four bounds of config/rss.php as whole decimal numbers before any request; one value out of shape refuses the fetch instead of guessing a default
    private function getFeedLimits(): array {
        $out = [];
        foreach (['bytes' => 1, 'timeout' => 1, 'redirects' => 0, 'max' => 1] as $key => $min) {
            $val = $this->conf[$key] ?? null;
            if (!is_int($val) && !is_string($val)) return [];
            if (!preg_match('/^(0|[1-9][0-9]{0,8})$/', (string)$val) || (int)$val < $min) return [];
            $out[$key] = (int)$val;
        }
        return $out;
    }

    # Return whether an entity tag has the quoted shape of RFC 9110, which is also what keeps a line break or a second header out of the request
    private function checkFeedEtag(string $val): bool {
        return strlen($val) <= 256 && preg_match('/^(W\/)?"[\x21\x23-\x7e\x80-\xff]*"$/', $val) === 1;
    }

    # Return whether a date has the one HTTP-date shape a server sends and a conditional request repeats
    private function checkFeedStamp(string $val): bool {
        return preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun), [0-9]{2} (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2} GMT$/', $val) === 1;
    }

    # Return the one checked value of a validator header, or '' when it is absent, repeated or out of shape
    private function getFeedValue(array $head, string $name): string {
        $list = $head[$name] ?? [];
        if (count($list) !== 1) return '';
        $val = trim($list[0]);
        $done = ($name === 'etag') ? $this->checkFeedEtag($val) : $this->checkFeedStamp($val);
        return $done ? $val : '';
    }

    # Normalize the address of one hop before any lookup: http or https, no credentials, the default port only and a host by the DNS grammar or an address literal
    # The fragment is never sent, and a byte a request line cannot carry is percent-encoded rather than handed to the transport
    private function getFeedUrl(string $url): array {
        $url = trim($url);
        if ($url === '' || strlen($url) > self::URLMAX || preg_match('/[\x00-\x20\x7f]/', $url)) return [];
        $data = parse_url($url);
        if (!is_array($data) || isset($data['user']) || isset($data['pass'])) return [];
        $sch = strtolower($data['scheme'] ?? '');
        if ($sch !== 'http' && $sch !== 'https') return [];
        if (isset($data['port']) && $data['port'] !== ($sch === 'https' ? 443 : 80)) return [];
        $host = $this->getFeedHost($data['host'] ?? '');
        if ($host === '') return [];
        $auth = str_contains($host, ':') ? '['.$host.']' : $host;
        $tail = ($data['path'] ?? '') ?: '/';
        if (isset($data['query'])) $tail .= '?'.$data['query'];
        return ['url' => $sch.'://'.$auth.$this->getFeedCode($tail, false), 'host' => $host];
    }

    # Normalize one host: an address literal in canonical text, or a lower-case DNS name of two labels or more, converted from Unicode where intl is present; else ''
    private function getFeedHost(string $host): string {
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? inet_ntop(inet_pton($host)) : '';
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) return $host;
        $host = rtrim(mb_strtolower($host, 'UTF-8'), '.');
        if (preg_match('/[^\x00-\x7f]/', $host)) $host = function_exists('idn_to_ascii') ? (idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: '') : '';
        if ($host === '' || strlen($host) > 253) return '';
        return preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) ? $host : '';
    }

    # Percent-encode every byte an address may not carry as it stands, and a percent sign that starts no escape; a Markdown link also encodes the characters Parser reads
    # Those are the parentheses and brackets that would close the link or open a tag, the asterisk of a smilie, the backslash of a literal and the backtick of a code span
    private function getFeedCode(string $val, bool $md): string {
        $pat = $md ? '/[^A-Za-z0-9\-._~:\/?#@!$&\'+,;=%]|%(?![0-9A-Fa-f]{2})/' : '/[^A-Za-z0-9\-._~:\/?#@!$&\'()*+,;=%\[\]]|%(?![0-9A-Fa-f]{2})/';
        return preg_replace_callback($pat, fn(array $m): string => rawurlencode($m[0]), $val) ?? '';
    }

    # Resolve one host to the address the request is pinned to: a literal is judged as itself, a name by every address of its answer, and one non-public address refuses the host
    # An IPv4 address is preferred when the answer holds both families, and the choice is stable because each family is sorted
    private function getFeedAddress(string $host): string {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) return Upload::checkPublicAddress($host) ? $host : '';
        $res = $this->getFeedReply('resolve', ['host' => $host]);
        $list = $res['addresses'] ?? null;
        if (count($res) !== 1 || !is_array($list) || $list === [] || !array_is_list($list)) return '';
        foreach ($list as $addr) {
            if (!is_string($addr) || filter_var($addr, FILTER_VALIDATE_IP) === false || !Upload::checkPublicAddress($addr)) return '';
        }
        $four = array_values(array_filter($list, fn($v) => !str_contains($v, ':')));
        $pick = ($four !== []) ? $four : $list;
        sort($pick);
        return $pick[0];
    }

    # Hand one operation to the transport: the closure given to the constructor, or the system implementation of this class when none was given
    private function getFeedReply(string $op, array $req): array {
        return ($this->send !== null) ? ($this->send)($op, $req) : $this->getSystemReply($op, $req);
    }

    # The standard transport: resolve asks the system resolver, get runs one cURL request pinned to the checked address, without redirects or proxies and with TLS verification kept
    # The stream stops one chunk past the byte bound so the caller sees the overflow; a connection that reached any other address than the pinned one is refused
    # A host that is already an address carries no pin: nothing was resolved, and libcurl refuses a resolve entry whose host part is an IPv6 literal
    private function getSystemReply(string $op, array $req): array {
        if ($op === 'resolve') return ['addresses' => $this->getHostList($req['host'])];
        if ($op !== 'get') throw new RuntimeException('operation');
        $url = $req['url'];
        $addr = $req['ip'];
        $max = $req['bytes'];
        $host = trim((string)parse_url($url, PHP_URL_HOST), '[]');
        $port = str_starts_with($url, 'https:') ? 443 : 80;
        $lines = [];
        foreach ($req['headers'] as $key => $val) $lines[] = $key.': '.$val;
        $body = '';
        $head = [];
        $time = max(1, (int)ceil($req['timeout'] * 1000));
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPGET => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_PROTOCOLS_STR => 'http,https',
            CURLOPT_PROXY => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT_MS => $time,
            CURLOPT_TIMEOUT_MS => $time,
            CURLOPT_RESOLVE => ($host === $addr) ? [] : [$host.':'.$port.':'.$addr],
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_HEADERFUNCTION => function (mixed $curl, string $line) use (&$head): int {
                $one = trim($line);
                $pos = strpos($one, ':');
                if (preg_match('#^HTTP/[0-9.]+ +[0-9]{3}#', $one)) $head = [];
                elseif ($pos !== false) $head[strtolower(trim(substr($one, 0, $pos)))][] = trim(substr($one, $pos + 1));
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => function (mixed $curl, string $chunk) use (&$body, $max): int {
                $body .= $chunk;
                return strlen($body) > $max ? 0 : strlen($chunk);
            },
        ]);
        $done = curl_exec($curl);
        $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $peer = (string)curl_getinfo($curl, CURLINFO_PRIMARY_IP);
        curl_close($curl);
        if (strlen($body) > $max) return ['code' => $code, 'headers' => $head, 'body' => $body];
        if ($done === false) throw new RuntimeException('transfer');
        if ($peer === '' || inet_pton($peer) !== inet_pton($addr)) throw new RuntimeException('peer');
        return ['code' => $code, 'headers' => $head, 'body' => $body];
    }

    # Ask the system resolver for the A and AAAA records of one name, falling back to the IPv4 lookup of the host database; a failed lookup answers an empty list
    private function getHostList(string $host): array {
        set_error_handler(static fn(): bool => true);
        try {
            $rows = dns_get_record($host, DNS_A | DNS_AAAA);
            $four = gethostbynamel($host);
        } finally {
            restore_error_handler();
        }
        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $addr = $row['ip'] ?? $row['ipv6'] ?? '';
            if (is_string($addr) && $addr !== '') $out[] = $addr;
        }
        return ($out === [] && is_array($four)) ? $four : $out;
    }

    # Hold a transport answer to the exact shape of the contract: an integer code, headers as lists of strings under string names and a string body, and nothing else
    private function checkFeedReply(array $res): bool {
        if (count($res) !== 3 || !is_int($res['code'] ?? null) || !is_string($res['body'] ?? null) || !is_array($res['headers'] ?? null)) return false;
        foreach ($res['headers'] as $key => $list) {
            if (!is_string($key) || !is_array($list) || !array_is_list($list)) return false;
            foreach ($list as $one) {
                if (!is_string($one)) return false;
            }
        }
        return true;
    }

    # Return whether the declared type is one an XML feed travels under; a response without the header is left to the XML parser, two headers are refused
    private function checkFeedType(array $list): bool {
        if ($list === []) return true;
        if (count($list) !== 1) return false;
        $type = strtolower(trim(explode(';', $list[0])[0]));
        return in_array($type, self::TYPES, true) || str_ends_with($type, '+xml');
    }

    # Parse the document without network, DTD or entities and write its first entries as Markdown; a document that is not RSS 2.0, RSS 1.0 or Atom is refused, an empty feed is ''
    # The byte scan only spares the parser an obvious DTD; a UTF-16 document hides one from it, so a parsed document type refuses the feed as well
    private function getFeedBody(string $xml, string $base, int $max): ?string {
        if (trim($xml) === '' || preg_match('/<!(DOCTYPE|ENTITY)/i', $xml)) return null;
        $doc = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        $done = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($old);
        $root = $doc->documentElement;
        if (!$done || $root === null || $doc->doctype !== null) return null;
        $feed = $this->getFeedItems($root);
        if ($feed === null) return null;
        $out = [];
        foreach ($feed['list'] as $item) {
            $one = ($feed['ns'] === self::ATOM) ? $this->getAtomEntry($item, $base) : $this->getRssEntry($item, $feed['ns'], $base);
            if ($one === '') continue;
            $out[] = $one;
            if (count($out) >= $max) break;
        }
        return ($out === []) ? '' : implode("\n\n", $out)."\n";
    }

    # Name the format by its root element and return its entries with the namespace their children live in: RSS 2.0 and 0.9x have none, RSS 1.0 and Atom have their own
    private function getFeedItems(DOMElement $root): ?array {
        if ($root->localName === 'rss' && $root->namespaceURI === null) {
            $chan = $this->getFeedChild($root, 'channel', null);
            return ($chan === null) ? null : ['ns' => null, 'list' => $this->getFeedChildren($chan, 'item', null)];
        }
        if ($root->localName === 'RDF') return ['ns' => self::RSSONE, 'list' => $this->getFeedChildren($root, 'item', self::RSSONE)];
        if ($root->localName === 'feed' && $root->namespaceURI === self::ATOM) return ['ns' => self::ATOM, 'list' => $this->getFeedChildren($root, 'entry', self::ATOM)];
        return null;
    }

    # Return the first child element of one name in one namespace, so an extension element such as media:title is never read for the element of the format
    private function getFeedChild(DOMElement $node, string $name, ?string $ns): ?DOMElement {
        return $this->getFeedChildren($node, $name, $ns)[0] ?? null;
    }

    # Return every child element of one name in one namespace, in document order
    private function getFeedChildren(DOMElement $node, string $name, ?string $ns): array {
        $out = [];
        foreach ($node->childNodes as $one) {
            if ($one instanceof DOMElement && $one->localName === $name && $one->namespaceURI === $ns) $out[] = $one;
        }
        return $out;
    }

    # Return the text of the first child element of one name, trimmed, or '' when there is none
    private function getChildText(DOMElement $node, string $name, ?string $ns): string {
        return trim($this->getFeedChild($node, $name, $ns)?->textContent ?? '');
    }

    # Build one RSS entry: the title as plain text, the link or a permanent guid, pubDate or dc:date, and the description as HTML reduced to text
    private function getRssEntry(DOMElement $item, ?string $ns, string $base): string {
        $link = $this->getChildText($item, 'link', $ns);
        $guid = $this->getFeedChild($item, 'guid', $ns);
        if ($link === '' && $guid !== null && strtolower($guid->getAttribute('isPermaLink')) !== 'false') $link = trim($guid->textContent);
        $date = $this->getChildText($item, 'pubDate', $ns) ?: $this->getChildText($item, 'date', self::DUBLIN);
        $title = implode(' ', $this->getFeedParas($this->getChildText($item, 'title', $ns)));
        return $this->getFeedEntry($title, $date, $link, $base, $this->getHtmlText($this->getChildText($item, 'description', $ns)));
    }

    # Build one Atom entry: the title and the summary or content as text constructs, the first alternate link, and updated or published
    private function getAtomEntry(DOMElement $item, string $base): string {
        $link = '';
        foreach ($this->getFeedChildren($item, 'link', self::ATOM) as $one) {
            $rel = $one->getAttribute('rel');
            if ($rel !== '' && $rel !== 'alternate') continue;
            $link = $one->getAttribute('href');
            break;
        }
        $date = $this->getChildText($item, 'updated', self::ATOM) ?: $this->getChildText($item, 'published', self::ATOM);
        $node = $this->getFeedChild($item, 'title', self::ATOM);
        $title = ($node === null) ? '' : implode(' ', $this->getAtomText($node));
        $node = $this->getFeedChild($item, 'summary', self::ATOM) ?? $this->getFeedChild($item, 'content', self::ATOM);
        return $this->getFeedEntry($title, $date, $link, $base, ($node === null) ? [] : $this->getAtomText($node));
    }

    # Read one Atom text construct: text is plain, html is escaped markup and xhtml is inline markup under a div; the latter two are reduced to text by the same HTML walk
    private function getAtomText(DOMElement $node): array {
        $type = strtolower($node->getAttribute('type'));
        if ($type === 'html') return $this->getHtmlText($node->textContent);
        if ($type !== 'xhtml') return $this->getFeedParas($node->textContent);
        $html = '';
        foreach ($node->childNodes as $one) $html .= $node->ownerDocument->saveXML($one);
        return $this->getHtmlText($html);
    }

    # Reduce received HTML to plain paragraphs through the HTML5 parser of PHP: executable and embedded content is dropped whole, a block element or a break ends a paragraph
    private function getHtmlText(string $html): array {
        if (trim($html) === '') return [];
        $doc = Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        $buf = '';
        if ($doc->body !== null) $this->addHtmlText($doc->body, $buf, 0);
        return $this->getFeedParas($buf);
    }

    # Append the text below one node, whitespace collapsed, with a blank line around every block element; the walk stops at a fixed depth, so no nesting can exhaust the stack
    private function addHtmlText(Dom\Node $node, string &$buf, int $depth): void {
        if ($depth > self::DEPTH) return;
        foreach ($node->childNodes as $one) {
            if ($one instanceof Dom\Text) {
                $buf .= preg_replace('/\s+/u', ' ', $one->data) ?? '';
                continue;
            }
            if (!$one instanceof Dom\Element) continue;
            $tag = strtolower($one->localName);
            if (in_array($tag, self::SKIP, true)) continue;
            $blk = in_array($tag, self::BLOCKS, true);
            if ($blk) $buf .= "\n\n";
            $this->addHtmlText($one, $buf, $depth + 1);
            if ($blk) $buf .= "\n\n";
        }
    }

    # Split text into paragraphs at blank lines and collapse every other run of whitespace, no-break spaces included, into one space
    private function getFeedParas(string $text): array {
        $out = [];
        foreach (preg_split('/\n[ \t\f\v]*\n/', str_replace(["\r\n", "\r"], "\n", $text)) ?: [] as $one) {
            $one = trim(preg_replace('/[\s\x{00A0}\x{2028}\x{2029}]+/u', ' ', $one) ?? '');
            if ($one !== '') $out[] = $one;
        }
        return $out;
    }

    # Write one entry: the heading, the optional UTC date, the checked link under its host and the description paragraphs, received text escaped and blank lines between
    # An entry without a title is headed by the host of its link, and an entry with neither says nothing a reader could find again, so it is left out
    private function getFeedEntry(string $title, string $date, string $link, string $base, array $desc): string {
        $href = ($link === '') ? '' : $this->getFeedLink($link, $base, true);
        $host = ($href === '') ? '' : (string)parse_url($href, PHP_URL_HOST);
        if ($title === '') $title = $host;
        if ($title === '') return '';
        $out = ['## '.$this->getFeedText($title)];
        $when = $this->getFeedDate($date);
        if ($when !== '') $out[] = $this->getFeedText($when);
        if ($href !== '') $out[] = '['.$this->getFeedText($host).']('.$href.')';
        foreach ($desc as $one) $out[] = $this->getFeedText($one);
        return implode("\n\n", $out);
    }

    # Resolve one reference against the address it arrived on into an absolute http or https address with a checked host, or '' when it is not one
    # A Markdown link keeps no address literal of IPv6, whose brackets it cannot carry, and encodes the characters Parser reads; a redirect target is normalized by the next hop
    private function getFeedLink(string $ref, string $base, bool $md = false): string {
        $ref = trim(preg_replace('/[\x00-\x1f\x7f]/', '', $ref) ?? '');
        if ($ref === '') return '';
        if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $ref)) {
            $bp = parse_url($base);
            $root = $bp['scheme'].'://'.$bp['host'].(isset($bp['port']) ? ':'.$bp['port'] : '');
            $path = $bp['path'] ?? '/';
            if (str_starts_with($ref, '//')) $ref = $bp['scheme'].':'.$ref;
            elseif (str_starts_with($ref, '/')) $ref = $root.$ref;
            elseif (str_starts_with($ref, '?')) $ref = $root.$path.$ref;
            elseif (str_starts_with($ref, '#')) $ref = $root.$path.(isset($bp['query']) ? '?'.$bp['query'] : '').$ref;
            else $ref = $root.substr($path, 0, (int)strrpos($path, '/') + 1).$ref;
        }
        $data = parse_url($ref);
        if (!is_array($data) || isset($data['user']) || isset($data['pass'])) return '';
        $sch = strtolower($data['scheme'] ?? '');
        if ($sch !== 'http' && $sch !== 'https') return '';
        $host = $this->getFeedHost($data['host'] ?? '');
        if ($host === '' || ($md && str_contains($host, ':'))) return '';
        $auth = str_contains($host, ':') ? '['.$host.']' : $host;
        $tail = ($data['path'] ?? '') ?: '/';
        if (isset($data['query'])) $tail .= '?'.$data['query'];
        if (isset($data['fragment'])) $tail .= '#'.$data['fragment'];
        $out = $sch.'://'.$auth.(isset($data['port']) ? ':'.$data['port'] : '').$this->getFeedCode($tail, $md);
        return (strlen($out) > self::URLMAX) ? '' : $out;
    }

    # Normalize one received date to "Y-m-d H:i UTC" by the RFC 822 and RFC 3339 shapes; a relative phrase, an impossible date or anything else is dropped rather than guessed
    private function getFeedDate(string $raw): string {
        $raw = trim(preg_replace('/\s+/', ' ', $raw) ?? '');
        if ($raw === '') return '';
        $utc = new DateTimeZone('UTC');
        foreach (self::DATES as $fmt) {
            $when = DateTimeImmutable::createFromFormat($fmt, $raw, $utc);
            if ($when !== false && DateTimeImmutable::getLastErrors() === false) return $when->setTimezone($utc)->format('Y-m-d H:i').' UTC';
        }
        return '';
    }

    # Escape one received text for the canonical document: line endings become LF, control characters other than LF and TAB go, and every ASCII punctuation mark gets a backslash
    private function getFeedText(string $text): string {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/(?![\n\t])\p{Cc}/u', '', $text) ?? '';
        return preg_replace('/[!-\/:-@\[-`{-~]/', '\\\\$0', $text) ?? '';
    }
}
