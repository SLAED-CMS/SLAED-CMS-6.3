<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Classify and convert the stored comment bodies of stage 2 of docs/COMMENTS-REDESIGN-2026.md.
# Comments render at safe = true from that stage on, so a body has to be the source the author
# wrote instead of the pre-escaped HTML the old writer stored, and every row needs the format
# that says which syntax it is source of.
#
#   php tools/comment-migrate.php report                 # read-only, classifies and prints the table
#   php tools/comment-migrate.php classify               # writes format and the ledger, converts nothing
#   php tools/comment-migrate.php convert                # rewrites the bodies the ledger names, in batches
#   php tools/comment-migrate.php iphash                 # backfills the flood-control fingerprint from ip
#   php tools/comment-migrate.php sample                 # prints stored source before and after, per class
#
# Options: --default=markdown|plain --size=500 --num=3 --out=storage/migrate --db=NAME --prefix=NAME
#
# Take a dump of the comment table and rehearse the restore before running classify or convert.
# Run classify first and read its report: the verdict is written in its own pass so it can be
# reviewed before any body is rewritten. convert is driven by the ledger classify wrote and marks
# every row it finishes, so a second run converts nothing twice.

# Read one command line option, falling back to the given default
function getOption(array $args, string $name, string $def): string {
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name) + 3);
    }
    return $def;
}

# Open the database using the project configuration without booting the CMS; a name may be given so the whole run can be rehearsed against a restored copy first
function getDatabase(string $root, string $name = ''): PDO {
    $cfg = require $root.'/config/db.php';
    $con = $cfg['db'];
    $dsn = 'mysql:host='.$con['host'].';dbname='.($name !== '' ? $name : $con['name']).';charset=utf8mb4';
    return new PDO($dsn, $con['uname'], $con['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# Derive one purpose-scoped key exactly as getSecret() derives it, without booting the CMS that would create a missing master
function getSecretKey(string $root, string $purpose): string {
    $cfg = require $root.'/config/security.php';
    $master = (string)($cfg['security']['secret'] ?? '');
    if ($master === '') {
        fwrite(STDERR, "No master secret in config/security.php - open the site once so it is created\n");
        exit(1);
    }
    return hash_hmac('sha256', $purpose, $master);
}

# Decide the one class a stored body belongs to; the rules are ordered because their signatures overlap and every row gets exactly one verdict
# A body carrying &#034; is legacy even without a tag: only the html branch of the writer produced that entity, the other branch escapes a quote as &quot;
function getCommentRule(string $body): int {
    $tags = preg_match_all('#</?[a-zA-Z][^>]*>#', $body, $hits) ? $hits[0] : [];
    foreach ($tags as $one) {
        if (!preg_match('#^</?\s*br\s*/?\s*>$#i', $one)) return 1;
    }
    if (str_contains($body, '&#034;')) return 1;
    if (preg_match('#<br\s*/?>\r?\n#i', $body)) return 2;
    if (preg_match('#<br\s*/?>#i', $body)) return 3;
    return str_contains($body, "\n") ? 4 : 5;
}

# Map one class to the format the row is stored under; legacy markup becomes markdown source like any other row, and the single-line default is an input rather than an assumption
function getRuleFormat(int $rule, string $def): string {
    return match ($rule) {
        2, 3 => 'plain',
        default => $def,
    };
}

# Reverse the entities the writer inserted, and only those: the ampersand goes last because it was escaped first, so an authored &amp;lt; cannot decay into a live &lt;
function filterEntityText(string $body, bool $legacy): string {
    $map = $legacy
        ? ['&#034;' => '"', '&#036;' => '$', '&#039;' => '\'', '&#092;' => '\\']
        : ['&lt;' => '<', '&gt;' => '>', '&quot;' => '"', '&#039;' => '\'', '&#036;' => '$', '&#092;' => '\\'];
    $body = strtr($body, $map);
    return $legacy ? $body : str_replace('&amp;', '&', $body);
}

# Restore the line endings the writer replaced: a machine-written break carries the ending behind it, and a break without one becomes the ending it stood for
function filterBreakText(string $body): string {
    $body = (string)preg_replace('#<br\s*/?>(\r?\n)#i', '$1', $body);
    return (string)preg_replace('#<br\s*/?>#i', "\n", $body);
}

# Turn the formatting tags that appear in legacy rows into their Markdown or BB equivalent; what the map does not cover stays text and the render path escapes it
function filterLegacyTags(string $body): string {
    $body = (string)preg_replace_callback(
        '#<a\s[^>]*href\s*=\s*["\']?([^"\'\s>]+)["\']?[^>]*>(.*?)</a>#is',
        static fn(array $m): string => '['.(trim($m[2]) !== '' ? $m[2] : $m[1]).']('.$m[1].')',
        $body
    );
    $body = (string)preg_replace('#</?(?:b|strong)\s*>#i', '**', $body);
    $body = (string)preg_replace('#</?(?:i|em)\s*>#i', '*', $body);
    $body = (string)preg_replace('#<(/?)u\s*>#i', '[$1u]', $body);
    $body = (string)preg_replace('#</?(?:tt|code)\s*>#i', '`', $body);
    $body = (string)preg_replace('#<li\s*>#i', "\n- ", $body);
    return (string)preg_replace('#</li\s*>|</?[uo]l\s*>#i', '', $body);
}

# Rewrite one stored body into the source its class is source of, which is the only operation of this tool that changes what a visitor reads
function getConvertBody(string $body, int $rule): string {
    if ($rule === 1) return filterBreakText(filterLegacyTags(filterEntityText($body, true)));
    if ($rule === 2 || $rule === 3) return filterBreakText(filterEntityText($body, false));
    return filterEntityText($body, false);
}

# Read the ledger classify wrote, so convert knows which rows it owns and which of them it has already finished
function getLedgerFile(string $file): array {
    if (!is_file($file)) {
        fwrite(STDERR, 'No ledger at '.$file." - run classify first\n");
        exit(1);
    }
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data) || !isset($data['rows'])) {
        fwrite(STDERR, 'Unreadable ledger at '.$file."\n");
        exit(1);
    }
    return $data;
}

# Write the ledger back after every batch, so a run that is interrupted resumes instead of converting a body twice
function addLedgerFile(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
}

$args = array_slice($argv, 1);
$mode = $args[0] ?? '';
if (!in_array($mode, ['report', 'classify', 'convert', 'iphash', 'sample'], true)) {
    fwrite(STDERR, "Usage: php tools/comment-migrate.php report|classify|convert|iphash|sample [--default=markdown] [--size=500] [--num=3] [--out=DIR] [--db=NAME]\n");
    exit(2);
}
$root = dirname(__DIR__);
$def = getOption($args, 'default', 'markdown');
if ($def !== 'markdown' && $def !== 'plain') {
    fwrite(STDERR, "--default accepts markdown or plain only\n");
    exit(2);
}
$size = max(1, (int)getOption($args, 'size', '500'));
$num = max(1, (int)getOption($args, 'num', '3'));
$out = getOption($args, 'out', $root.'/storage/migrate');
$cfg = require $root.'/config/db.php';
$pref = getOption($args, 'prefix', (string)$cfg['db']['prefix']);
$pdo = getDatabase($root, getOption($args, 'db', ''));
$file = $out.'/comment-format.json';
$total = (int)$pdo->query('SELECT COUNT(*) FROM '.$pref.'_comment')->fetchColumn();

if ($mode === 'iphash') {
    $key = getSecretKey($root, 'commentip');
    $rows = $pdo->query('SELECT id, ip FROM '.$pref.'_comment WHERE iphash = \'\'')->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('UPDATE '.$pref.'_comment SET iphash = :hash WHERE id = :id');
    $done = 0;
    $pdo->beginTransaction();
    foreach ($rows as $row) {
        $ip = strtolower(trim((string)$row['ip']));
        $stmt->execute(['hash' => $ip !== '' ? hash_hmac('sha256', $ip, $key) : '', 'id' => (int)$row['id']]);
        $done++;
        if ($done % $size === 0) {
            $pdo->commit();
            $pdo->beginTransaction();
        }
    }
    $pdo->commit();
    $left = (int)$pdo->query('SELECT COUNT(*) FROM '.$pref.'_comment WHERE iphash = \'\' AND ip != \'\'')->fetchColumn();
    printf("iphash: %d rows written, %d rows still empty with an address, %d rows total\n", $done, $left, $total);
    exit($left ? 1 : 0);
}

if ($mode === 'sample') {
    $data = getLedgerFile($file);
    $seen = [];
    foreach ($data['rows'] as $id => $one) {
        $rule = (int)$one['rule'];
        if (count($seen[$rule] ?? []) >= $num) continue;
        $seen[$rule][] = (int)$id;
    }
    ksort($seen);
    foreach ($seen as $rule => $ids) {
        foreach ($ids as $id) {
            $row = $pdo->query('SELECT body, format FROM '.$pref.'_comment WHERE id = '.(int)$id)->fetch(PDO::FETCH_ASSOC);
            $was = (string)($data['rows'][(string)$id]['body'] ?? $row['body']);
            printf("--- rule %d, id %d, format %s\n", $rule, $id, (string)$row['format']);
            printf("    stored before: %s\n", str_replace("\n", '\n', mb_substr($was, 0, 300)));
            printf("    stored after : %s\n", str_replace("\n", '\n', mb_substr((string)$row['body'], 0, 300)));
        }
    }
    exit(0);
}

if ($mode === 'convert') {
    $data = getLedgerFile($file);
    if ((int)$data['total'] !== $total) {
        fwrite(STDERR, 'The table changed since classify ran: '.$data['total'].' rows then, '.$total." now\n");
        exit(1);
    }
    $read = $pdo->prepare('SELECT body FROM '.$pref.'_comment WHERE id = :id');
    $stmt = $pdo->prepare('UPDATE '.$pref.'_comment SET body = :body WHERE id = :id');
    $done = 0;
    $skip = 0;
    $open = false;
    foreach ($data['rows'] as $id => $one) {
        if (!empty($one['done'])) {
            $skip++;
            continue;
        }
        if (!$open) {
            $pdo->beginTransaction();
            $open = true;
        }
        $read->execute(['id' => (int)$id]);
        $body = (string)$read->fetchColumn();
        $new = getConvertBody($body, (int)$one['rule']);
        if ($new !== $body) $stmt->execute(['body' => $new, 'id' => (int)$id]);
        $data['rows'][$id]['done'] = true;
        $data['rows'][$id]['body'] = $body;
        $done++;
        if ($done % $size === 0) {
            $pdo->commit();
            $open = false;
            addLedgerFile($file, $data);
        }
    }
    if ($open) $pdo->commit();
    $data['converted'] = date('Y-m-d H:i:s');
    addLedgerFile($file, $data);
    $now = (int)$pdo->query('SELECT COUNT(*) FROM '.$pref.'_comment')->fetchColumn();
    printf("convert: %d rows rewritten, %d already done, %d rows total\n", $done, $skip, $now);
    if ($now !== $total) {
        fwrite(STDERR, "The row count changed during the run\n");
        exit(1);
    }
    exit(0);
}

$rows = $pdo->query('SELECT id, body FROM '.$pref.'_comment ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$seen = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$list = [];
$mark = [];
$rest = [];
$ents = 0;
foreach ($rows as $row) {
    $body = (string)$row['body'];
    $rule = getCommentRule($body);
    $seen[$rule]++;
    if ($rule === 1 || $rule === 3) $mark[$rule][] = (int)$row['id'];
    if (preg_match('/&(?:lt|gt|quot|amp);|&#0(?:34|36|39|92);/', $body)) $ents++;
    if (preg_match_all('/&(?:[a-zA-Z][a-zA-Z0-9]{1,9}|#[0-9]{1,6}|#x[0-9a-fA-F]{1,5});/', $body, $hits)
        && array_diff($hits[0], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;', '&#034;', '&#036;', '&#092;'])) {
        $rest[] = (int)$row['id'];
    }
    $list[(string)$row['id']] = ['rule' => $rule, 'format' => getRuleFormat($rule, $def), 'done' => false];
}
$sum = array_sum($seen);
$names = [1 => 'legacy markup', 2 => 'machine break', 3 => 'break, review', 4 => 'multi line', 5 => 'single line'];
printf("%-6s %-16s %-10s %7s\n", 'rule', 'signature', 'format', 'rows');
foreach ($seen as $rule => $cnt) {
    printf("%-6d %-16s %-10s %7d\n", $rule, $names[$rule], getRuleFormat($rule, $def), $cnt);
}
printf("%-6s %-16s %-10s %7d of %d\n", '', 'sum', '', $sum, $total);
printf("rows carrying at least one writer entity: %d\n", $ents);
printf("rows carrying an entity the migration does not reverse, which renders as its own text afterwards: %d%s\n",
    count($rest), $rest ? ' (ids '.implode(', ', array_slice($rest, 0, 40)).(count($rest) > 40 ? ', ...' : '').')' : '');
foreach ([1, 3] as $rule) {
    if (!isset($mark[$rule])) continue;
    $ids = $mark[$rule];
    printf("rule %d ids (%d): %s%s\n", $rule, count($ids), implode(', ', array_slice($ids, 0, 40)), count($ids) > 40 ? ', ...' : '');
}
if ($sum !== $total) {
    fwrite(STDERR, 'The class counts do not sum to the row count: '.$sum.' against '.$total."\n");
    exit(1);
}
foreach ($list as $id => $one) {
    if ($one['format'] === '') {
        fwrite(STDERR, 'Row '.$id." received no format\n");
        exit(1);
    }
    if ($one['rule'] < 1 || $one['rule'] > 5) {
        fwrite(STDERR, 'Row '.$id." received more than one verdict\n");
        exit(1);
    }
}
if ($mode === 'report') exit(0);
# A second classify over a finished run would hand convert the same rows again, and a body whose entities are reversed twice cannot be reconstructed
if (is_file($file) && !empty((json_decode((string)file_get_contents($file), true) ?: [])['converted']) && getOption($args, 'force', '') !== '1') {
    fwrite(STDERR, 'The ledger at '.$file." records a finished conversion - reclassifying would let convert run over the same rows twice.\n"
        ."Restore the table from the dump first, or pass --force=1 if you know the bodies are unconverted.\n");
    exit(1);
}
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, 'Cannot create '.$out."\n");
    exit(1);
}
$stmt = $pdo->prepare('UPDATE '.$pref.'_comment SET format = :format WHERE id = :id');
$done = 0;
$pdo->beginTransaction();
foreach ($list as $id => $one) {
    $stmt->execute(['format' => $one['format'], 'id' => (int)$id]);
    $done++;
    if ($done % $size === 0) {
        $pdo->commit();
        $pdo->beginTransaction();
    }
}
$pdo->commit();
$now = (int)$pdo->query('SELECT COUNT(*) FROM '.$pref.'_comment')->fetchColumn();
$left = (int)$pdo->query('SELECT COUNT(*) FROM '.$pref.'_comment WHERE format = \'\'')->fetchColumn();
addLedgerFile($file, ['time' => date('Y-m-d H:i:s'), 'default' => $def, 'total' => $total, 'counts' => $seen, 'rows' => $list]);
printf("classify: %d rows written, %d rows left without a format, ledger %s\n", $done, $left, $file);
if ($left || $now !== $total) {
    fwrite(STDERR, "A row is left without a format or the row count changed during the run\n");
    exit(1);
}
exit(0);
