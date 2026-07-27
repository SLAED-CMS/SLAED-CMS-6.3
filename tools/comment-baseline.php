<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Capture and verify byte-level baselines of the rendered comment list.
# Stage 1 of docs/COMMENTS-REDESIGN-2026.md requires the markup to survive the move
# into the Comments class unchanged; without a baseline taken beforehand that claim
# cannot be checked. Run it before the refactor, then again after.
#
#   php tools/comment-baseline.php capture
#   php tools/comment-baseline.php verify
#
# Options: --base=https://slaed.loc --out=storage/baseline/comments
# Guest view only. Logged-in and moderator views carry session state and belong to
# the browser path in .agents/skills/slaed/testing/browser-debugging.

# Read one command line option, falling back to the given default
function getOption(array $args, string $name, string $def): string {
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name) + 3);
    }
    return $def;
}

# Open the database using the project configuration without booting the CMS
function getDatabase(string $root): PDO {
    $cfg = require $root.'/config/db.php';
    $con = $cfg['db'];
    $dsn = 'mysql:host='.$con['host'].';dbname='.$con['name'].';charset=utf8mb4';
    return new PDO($dsn, $con['uname'], $con['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Rank commented targets per module; several are kept because a target may have comments stored while its acomm mode is now off
function getFixtures(PDO $pdo, string $pref): array {
    $sql = 'SELECT modul, cid, COUNT(*) num FROM '.$pref.'_comment WHERE status = 1 GROUP BY modul, cid ORDER BY modul, num DESC';
    $out = [];
    foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
        $mod = $row['modul'];
        if (count($out[$mod] ?? []) >= 8) continue;
        $out[$mod][] = ['cid' => (int)$row['cid'], 'num' => (int)$row['num']];
    }
    return $out;
}

# Fetch one page over HTTP, accepting the local development certificate
function getPage(string $url): string {
    $ctx = stream_context_create([
        'http' => ['timeout' => 20, 'ignore_errors' => true, 'header' => "User-Agent: SLAED-baseline\r\n"],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $out = file_get_contents($url, false, $ctx);
    return $out === false ? '' : $out;
}

# Extract the element carrying the given id together with its balanced closing tag
function getRegion(string $html, string $name): string {
    $pos = strpos($html, 'id="'.$name.'"');
    if ($pos === false) return '';
    $beg = strrpos(substr($html, 0, $pos), '<div');
    if ($beg === false) return '';
    $dep = 0;
    $len = strlen($html);
    for ($i = $beg; $i < $len; $i++) {
        if (substr($html, $i, 4) === '<div') $dep++;
        if (substr($html, $i, 6) === '</div>') {
            $dep--;
            if ($dep === 0) return substr($html, $beg, $i + 6 - $beg);
        }
    }
    return '';
}

# Replace request-scoped values so two captures of identical markup compare equal.
# The X-CSRF-TOKEN header is emitted once per comment by getRatingAsync(), so without
# this the list never compares equal to itself across two sessions.
function filterVolatile(string $html): string {
    $html = preg_replace('#token=[0-9a-f]{16,}#i', 'token=TOKEN', $html);
    $html = preg_replace('#("X-CSRF-TOKEN":\s*")[0-9a-f]{16,}(")#i', '\1TOKEN\2', $html);
    $html = preg_replace('#\[\[sldyn:([a-z]+):([^:]*):[0-9a-f]+\]\]#i', '[[sldyn:\1:\2:MARK]]', $html);
    return preg_replace('#name="captcha[^"]*" value="[^"]*"#i', 'name="captcha" value="CAPTCHA"', $html);
}

$args = array_slice($argv, 1);
$mode = $args[0] ?? '';
if ($mode !== 'capture' && $mode !== 'verify') {
    fwrite(STDERR, "Usage: php tools/comment-baseline.php capture|verify [--base=URL] [--out=DIR]\n");
    exit(2);
}
$root = dirname(__DIR__);
$base = rtrim(getOption($args, 'base', 'https://slaed.loc'), '/');
$out = getOption($args, 'out', $root.'/storage/baseline/comments');
$cfg = require $root.'/config/db.php';
$pref = $cfg['db']['prefix'];
$pdo = getDatabase($root);
$fixt = getFixtures($pdo, $pref);
if (!$fixt) {
    fwrite(STDERR, "No commented targets found\n");
    exit(1);
}
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, 'Cannot create '.$out."\n");
    exit(1);
}
$mani = [];
$fail = 0;
$prev = is_file($out.'/manifest.json') ? json_decode((string)file_get_contents($out.'/manifest.json'), true) : [];
foreach ($fixt as $mod => $list) {
    $part = '';
    $url = '';
    $cid = 0;
    $num = 0;
    foreach ($list as $item) {
        $try = $base.'/index.php?name='.$mod.'&op=view&id='.$item['cid'];
        $html = getPage($try);
        if ($html === '') continue;
        $found = filterVolatile(getRegion($html, 'repcsave'));
        if ($found === '') continue;
        $part = $found;
        $url = $try;
        $cid = $item['cid'];
        $num = $item['num'];
        break;
    }
    if ($part === '') {
        printf("%-8s SKIP - no rendered comment region on any of %d candidates\n", $mod, count($list));
        if ($mode === 'capture') $fail++;
        continue;
    }
    $hash = hash('sha256', $part);
    $mani[$mod] = ['cid' => $cid, 'comments' => $num, 'bytes' => strlen($part), 'sha256' => $hash, 'url' => $url];
    if ($mode === 'capture') {
        file_put_contents($out.'/'.$mod.'.html', $part);
        printf("%-8s cid=%-6d comments=%-4d %7d bytes  %s\n", $mod, $cid, $num, strlen($part), substr($hash, 0, 12));
        continue;
    }
    $old = $prev[$mod]['sha256'] ?? '';
    if ($old === '') {
        printf("%-8s NEW - no baseline recorded\n", $mod);
        $fail++;
    } elseif ($old === $hash) {
        if (is_file($out.'/'.$mod.'.actual.html')) unlink($out.'/'.$mod.'.actual.html');
        printf("%-8s OK\n", $mod);
    } else {
        printf("%-8s CHANGED %d -> %d bytes\n", $mod, (int)($prev[$mod]['bytes'] ?? 0), strlen($part));
        file_put_contents($out.'/'.$mod.'.actual.html', $part);
        $fail++;
    }
}
if ($mode === 'capture') {
    file_put_contents($out.'/manifest.json', json_encode($mani, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    echo 'Captured '.count($mani).' modules to '.$out."\n";
}
exit($fail ? 1 : 0);
