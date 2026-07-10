<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

$base = rtrim((string)($argv[1] ?? 'https://slaed.loc'), '/');
$routes = [
    '/' => ['types' => ['WebSite', 'WebPage'], 'index' => true],
    '/index.php?name=news' => ['types' => ['CollectionPage'], 'index' => true],
    '/index.php?name=voting' => ['types' => ['CollectionPage'], 'index' => true],
    '/index.php?name=rss' => ['types' => ['WebPage'], 'index' => true],
    '/index.php?name=content' => ['types' => ['CollectionPage'], 'index' => true],
    '/index.php?name=changelog' => ['types' => ['CollectionPage'], 'index' => true],
    '/index.php?name=search' => ['types' => ['WebPage'], 'index' => false],
];
$extra = array_slice($argv, 2);
foreach ($extra as $path) $routes[$path] = ['types' => [], 'index' => true];

if (!function_exists('curl_init')) {
    fwrite(STDERR, "The cURL extension is required.\n");
    exit(2);
}

function getSeoAuditPage(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERAGENT => 'SLAED SEO audit',
    ]);
    $html = curl_exec($ch);
    $data = [
        'html' => is_string($html) ? $html : '',
        'status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
        'final' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
        'error' => curl_error($ch),
    ];
    curl_close($ch);
    return $data;
}

$details = [
    'news' => 'NewsArticle', 'pages' => 'Article', 'faq' => 'Article',
    'files' => 'WebPage', 'links' => 'WebPage', 'shop' => 'WebPage',
    'forum' => 'WebPage', 'voting' => 'WebPage',
];
foreach ($details as $mod => $type) {
    $page = getSeoAuditPage($base.'/index.php?name='.$mod);
    if ($page['status'] !== 200 || $page['html'] === '') continue;
    if (!preg_match('#<main\b[^>]*>(.*?)</main>#is', $page['html'], $main)) continue;
    $pat = '#href=["\']([^"\']*name='.$mod.'[^"\']*op=view[^"\']*)["\']#i';
    if (!preg_match($pat, $main[1], $link)) continue;
    $href = html_entity_decode($link[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $parts = parse_url($href);
    $path = (string)($parts['path'] ?? '');
    if (!empty($parts['query'])) $path .= '?'.$parts['query'];
    if ($path !== '') $routes['/'.ltrim($path, '/')] = ['types' => [$type], 'index' => true];
}

$errors = [];
$report = [];
foreach ($routes as $path => $expect) {
    $url = preg_match('#^https?://#i', $path) ? $path : $base.'/'.ltrim($path, '/');
    if ($path === '/') $url = $base.'/';
    $page = getSeoAuditPage($url);
    $html = $page['html'];
    $status = $page['status'];
    $final = $page['final'];
    $error = $page['error'];
    if ($html === '' || $status !== 200) {
        $errors[] = $path.': HTTP '.$status.($error !== '' ? ' '.$error : '');
        continue;
    }

    $doc = new DOMDocument();
    $prior = libxml_use_internal_errors(true);
    $doc->loadHTML($html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prior);
    $xp = new DOMXPath($doc);
    $h1 = $xp->query('//main//h1')->length;
    $levels = [];
    foreach ($xp->query('//main//h1|//main//h2|//main//h3|//main//h4|//main//h5|//main//h6') as $node) {
        $levels[] = (int)substr($node->nodeName, 1);
    }
    $title = trim((string)$xp->evaluate('string(//title)'));
    $robots = trim((string)$xp->evaluate('string(//meta[translate(@name,"ROBOTS","robots")="robots"]/@content)'));
    $canon = $xp->query('//link[translate(@rel,"CANONICAL","canonical")="canonical"]');
    $ogurl = trim((string)$xp->evaluate('string(//meta[@property="og:url"]/@content)'));
    $types = [];
    $jsonok = true;
    foreach ($xp->query('//script[@type="application/ld+json"]') as $node) {
        try {
            $data = json_decode($node->textContent, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data) || array_is_list($data)) throw new UnexpectedValueException('object expected');
            if (isset($data['@type'])) $types[] = (string)$data['@type'];
        } catch (Throwable) {
            $jsonok = false;
        }
    }
    if ($h1 !== 1) $errors[] = $path.': expected one H1 inside main, got '.$h1;
    $prev = 0;
    foreach ($levels as $lvl) {
        if ($prev > 0 && $lvl > $prev + 1) {
            $errors[] = $path.': heading level jumps from H'.$prev.' to H'.$lvl;
            break;
        }
        $prev = $lvl;
    }
    $parts = array_values(array_filter(array_map('trim', explode(' - ', $title)), 'strlen'));
    foreach ($parts as $key => $part) {
        if ($key > 0 && mb_strtolower($part, 'UTF-8') === mb_strtolower($parts[$key - 1], 'UTF-8')) {
            $errors[] = $path.': adjacent duplicate title segment '.$part;
        }
    }
    if (!$jsonok) $errors[] = $path.': invalid JSON-LD';
    foreach ($expect['types'] as $type) {
        if (!in_array($type, $types, true)) $errors[] = $path.': missing schema type '.$type;
    }
    if ($expect['index']) {
        if (stripos($robots, 'noindex') !== false) $errors[] = $path.': index route is noindex';
        if ($canon->length !== 1) $errors[] = $path.': expected one canonical, got '.$canon->length;
        if ($canon->length === 1) {
            $href = $canon->item(0)->getAttribute('href');
            if (!preg_match('#^https?://#i', $href)) $errors[] = $path.': canonical is not absolute';
            if ($ogurl !== $href) $errors[] = $path.': og:url differs from canonical';
        }
    } else {
        if (stripos($robots, 'noindex') === false) $errors[] = $path.': service route is indexable';
        if ($canon->length !== 0) $errors[] = $path.': noindex route has canonical';
    }
    $report[] = [
        'path' => $path, 'status' => $status, 'final' => $final, 'h1' => $h1,
        'headings' => implode(',', $levels),
        'title' => $title, 'robots' => $robots, 'types' => $types,
    ];
}

echo json_encode(['ok' => !$errors, 'routes' => $report, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
exit($errors ? 1 : 0);
