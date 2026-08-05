<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Classify and convert the stored private messages of stage 2 of docs/PRIVAT-2026.md.
# Private messages render at safe = true from that stage on, so a body has to be the source its author
# wrote instead of the pre-escaped HTML the old writer stored, and every row needs the format that says
# which syntax it is source of. The title carries no format of its own: it is plain source from that
# stage on, stored decoded and escaped at the template boundary, so it gets its own pass and no column.
#
#   php tools/privat-migrate.php report                 # read-only, classifies and prints the table
#   php tools/privat-migrate.php classify               # writes format and the ledger, converts nothing
#   php tools/privat-migrate.php convert                # rewrites the bodies the ledger names, in batches
#   php tools/privat-migrate.php title                  # decodes the titles the ledger names, in batches
#   php tools/privat-migrate.php sample                 # prints stored source before and after, per class
#
# Options: --default=markdown|plain --size=500 --num=3 --out=storage/migrate --db=NAME --prefix=NAME --force=1
#
# Close the site, take a dump of the database and rehearse the restore before running classify, convert
# or title. Run classify first and read its report: the verdict is written in its own pass so it can be
# reviewed before any message is rewritten. convert and title are driven by the ledger classify wrote and
# mark every row they finish, so a second run rewrites nothing twice. Both print the deployment gate the
# plan requires, and the stage 2 runtime code must not go live before that gate reads clean.

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

# Decide the one class a stored body belongs to; the rules are ordered because their signatures overlap and every row gets exactly one verdict
# A body carrying &#034; is legacy even without a tag: only the html branch of the writer produced that entity, the other branch escapes a quote as &quot;
function getMessageRule(string $body): int {
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

# Decide whether one message was written through the html branch of the writer, the only branch that leaves a quote as &#034; and never escapes a tag
# The class of the body decides for the title too: both fields went through one editor mode in one request, and a quote entity in the title alone settles it as well
function checkLegacyRow(string $title, int $rule): bool {
    return $rule === 1 || str_contains($title, '&#034;');
}

# Reverse the entities the writer inserted, and only those: the ampersand goes last because it was escaped first, so an authored &amp;lt; cannot decay into a live &lt;
function filterEntityText(string $text, bool $html): string {
    $map = $html
        ? ['&#034;' => '"', '&#036;' => '$', '&#039;' => '\'', '&#092;' => '\\']
        : ['&lt;' => '<', '&gt;' => '>', '&quot;' => '"', '&#039;' => '\'', '&#036;' => '$', '&#092;' => '\\'];
    $text = strtr($text, $map);
    return $html ? $text : str_replace('&amp;', '&', $text);
}

# Restore the line endings the writer replaced: a machine-written break carries the ending behind it, and a break without one becomes the ending it stood for
function filterBreakText(string $text): string {
    $text = (string)preg_replace('#<br\s*/?>(\r?\n)#i', '$1', $text);
    return (string)preg_replace('#<br\s*/?>#i', "\n", $text);
}

# Turn the formatting tags that appear in legacy rows into their Markdown or BB equivalent; what the map does not cover stays text and the render path escapes it
function filterLegacyTags(string $text): string {
    $text = (string)preg_replace_callback(
        '#<a\s[^>]*href\s*=\s*["\']?([^"\'\s>]+)["\']?[^>]*>(.*?)</a>#is',
        static fn(array $one): string => '['.(trim($one[2]) !== '' ? $one[2] : $one[1]).']('.$one[1].')',
        $text
    );
    $text = (string)preg_replace('#</?(?:b|strong)\s*>#i', '**', $text);
    $text = (string)preg_replace('#</?(?:i|em)\s*>#i', '*', $text);
    $text = (string)preg_replace('#<(/?)u\s*>#i', '[$1u]', $text);
    $text = (string)preg_replace('#</?(?:tt|code)\s*>#i', '`', $text);
    $text = (string)preg_replace('#<li\s*>#i', "\n- ", $text);
    return (string)preg_replace('#</li\s*>|</?[uo]l\s*>#i', '', $text);
}

# Rewrite one stored body into the source its class is source of, which is the operation of this tool that changes what a reader of a mailbox sees
# The row verdict decides the entity map wherever the body carries no evidence of its own, so a class 4 or 5 body in a message whose title says html is read with the html map too
# Class 2 is the one exception: only nl2br writes a break with the line ending behind it, and nl2br runs in no branch but the plain one, which outranks a quote entity in the title
# The tag map runs on the legacy branch alone, and that is load-bearing: the plain map turns an authored &lt;a&gt; back into a real tag the tag map would promote to a live link
function getConvertBody(string $body, int $rule, bool $html): string {
    if ($rule === 1 || ($html && $rule !== 2)) return filterBreakText(filterLegacyTags(filterEntityText($body, true)));
    if ($rule === 2 || $rule === 3) return filterBreakText(filterEntityText($body, false));
    return filterEntityText($body, false);
}

# Rewrite one stored title into the plain source it is from stage 2 on: the writer entities go back to their characters and a break becomes the line ending it stood for
# A title carries no markup contract, so a tag the map does not name stays text and renders as its own characters instead of as markup
function getConvertTitle(string $title, bool $html): string {
    return filterBreakText(filterEntityText($title, $html));
}

# Read the ledger classify wrote, so convert and title know which rows they own and which of them they have already finished
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

# Write the ledger back after every batch, so a run that is interrupted resumes instead of rewriting a field twice
function addLedgerFile(string $file, array $data): void {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
}

# Run one conversion pass over the rows the ledger names, in batches, storing the value it replaced and marking every row it finishes so an interrupted run resumes where it stopped
# The column and the mark are literals of the two call sites and never come from the command line, so the statement carries no name a caller could choose
function setPassRows(PDO $pdo, string $tab, array $data, string $file, string $col, string $mark, int $size, callable $conv): array {
    $read = $pdo->prepare('SELECT '.$col.' FROM '.$tab.' WHERE id = :id');
    $stmt = $pdo->prepare('UPDATE '.$tab.' SET '.$col.' = :val WHERE id = :id');
    $done = 0;
    $skip = 0;
    $open = false;
    foreach ($data['rows'] as $id => $one) {
        if (!empty($one[$mark])) {
            $skip++;
            continue;
        }
        if (!$open) {
            $pdo->beginTransaction();
            $open = true;
        }
        $read->execute(['id' => (int)$id]);
        $was = (string)$read->fetchColumn();
        $new = $conv($was, $one);
        if ($new !== $was) $stmt->execute(['val' => $new, 'id' => (int)$id]);
        $data['rows'][$id][$mark] = true;
        $data['rows'][$id][$col] = $was;
        $done++;
        if ($done % $size === 0) {
            $pdo->commit();
            $open = false;
            addLedgerFile($file, $data);
        }
    }
    if ($open) $pdo->commit();
    return ['data' => $data, 'done' => $done, 'skip' => $skip];
}

# Print the deployment gate of the plan after a pass and answer how many findings block the pass itself
# A row waiting for the other pass is reported and keeps the gate unclean, but it does not fail this run: the two passes are independent and convert runs before title
# A row without a final format or a row count that moved during the maintenance window is a defect of this run and does fail it
function getGateReport(PDO $pdo, string $tab, array $data, int $total): int {
    $rest = 0;
    foreach ($data['rows'] as $one) {
        if (empty($one['done']) || empty($one['tdone'])) $rest++;
    }
    $left = (int)$pdo->query('SELECT COUNT(*) FROM '.$tab.' WHERE format NOT IN (\'plain\', \'markdown\')')->fetchColumn();
    $now = (int)$pdo->query('SELECT COUNT(*) FROM '.$tab)->fetchColumn();
    $stop = $left + ($now === $total ? 0 : 1);
    printf("gate: %d rows still waiting for a pass, %d rows outside plain and markdown, %d rows now against %d before - %s\n",
        $rest, $left, $now, $total, ($stop || $rest) ? 'not clean yet' : 'clean');
    return $stop;
}

$args = array_slice($argv, 1);
$mode = $args[0] ?? '';
if (!in_array($mode, ['report', 'classify', 'convert', 'title', 'sample'], true)) {
    fwrite(STDERR, "Usage: php tools/privat-migrate.php report|classify|convert|title|sample\n"
        ."Options: [--default=markdown] [--size=500] [--num=3] [--out=DIR] [--db=NAME] [--prefix=NAME] [--force=1]\n");
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
$tab = $pref.'_privat';
$file = $out.'/privat-format.json';
$total = (int)$pdo->query('SELECT COUNT(*) FROM '.$tab)->fetchColumn();

# A second classify over rows a pass has already rewritten would hand convert and title those rows again, and a field whose entities are reversed twice cannot be reconstructed
# The count is taken per row rather than per pass: a pass stamps the ledger only when it finishes, while an interrupted one leaves finished rows behind and no stamp at all
# The refusal comes before the classification pass so a converted table is never scanned and never described by a table of verdicts that no longer mean anything
$hold = ($mode === 'classify' && is_file($file)) ? (json_decode((string)file_get_contents($file), true) ?: []) : [];
$made = 0;
foreach ((array)($hold['rows'] ?? []) as $one) {
    if (!empty($one['done']) || !empty($one['tdone'])) $made++;
}
if ($made && getOption($args, 'force', '') !== '1') {
    fwrite(STDERR, 'The ledger at '.$file.' records '.$made." rows a pass has already rewritten - reclassifying would let convert or title run over them twice.\n"
        ."Restore the table from the dump first, or pass --force=1 if you know the messages are unconverted.\n");
    exit(1);
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
            $row = $pdo->query('SELECT title, body, format FROM '.$tab.' WHERE id = '.(int)$id)->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) continue;
            $body = (string)($data['rows'][$id]['body'] ?? $row['body']);
            $head = (string)($data['rows'][$id]['title'] ?? $row['title']);
            printf("--- rule %d, id %d, format %s, html editor %s\n", $rule, $id, (string)$row['format'], !empty($data['rows'][$id]['html']) ? 'yes' : 'no');
            printf("    title before : %s\n", str_replace("\n", '\n', $head));
            printf("    title after  : %s\n", str_replace("\n", '\n', (string)$row['title']));
            printf("    body before  : %s\n", str_replace("\n", '\n', mb_substr($body, 0, 300)));
            printf("    body after   : %s\n", str_replace("\n", '\n', mb_substr((string)$row['body'], 0, 300)));
        }
    }
    exit(0);
}

if ($mode === 'convert' || $mode === 'title') {
    $data = getLedgerFile($file);
    if ((int)$data['total'] !== $total) {
        fwrite(STDERR, 'The table changed since classify ran: '.$data['total'].' rows then, '.$total." now\n");
        exit(1);
    }
    $pass = ($mode === 'convert')
        ? setPassRows($pdo, $tab, $data, $file, 'body', 'done', $size, static fn(string $was, array $one): string => getConvertBody($was, (int)$one['rule'], !empty($one['html'])))
        : setPassRows($pdo, $tab, $data, $file, 'title', 'tdone', $size, static fn(string $was, array $one): string => getConvertTitle($was, !empty($one['html'])));
    $data = $pass['data'];
    $data[$mode === 'convert' ? 'converted' : 'titled'] = date('Y-m-d H:i:s');
    addLedgerFile($file, $data);
    printf("%s: %d rows rewritten, %d already done, %d rows in the ledger\n", $mode, $pass['done'], $pass['skip'], count($data['rows']));
    exit(getGateReport($pdo, $tab, $data, $total) ? 1 : 0);
}

$rows = $pdo->query('SELECT id, title, body FROM '.$tab.' ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);
$seen = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$list = [];
$mark = [];
$rest = [];
$ents = 0;
$html = 0;
$tits = 0;
$tags = 0;
foreach ($rows as $row) {
    $body = (string)$row['body'];
    $head = (string)$row['title'];
    $rule = getMessageRule($body);
    $seen[$rule]++;
    if ($rule === 1 || $rule === 3) $mark[$rule][] = (int)$row['id'];
    if (preg_match('/&(?:lt|gt|quot|amp);|&#0(?:34|36|39|92);/', $body)) $ents++;
    if (preg_match('/&(?:lt|gt|quot|amp);|&#0(?:34|36|39|92);/', $head)) $tits++;
    if (preg_match('#</?[a-zA-Z][^>]*>#', $head)) $tags++;
    if (checkLegacyRow($head, $rule)) $html++;
    if (preg_match_all('/&(?:[a-zA-Z][a-zA-Z0-9]{1,9}|#[0-9]{1,6}|#x[0-9a-fA-F]{1,5});/', $body, $hits)
        && array_diff($hits[0], ['&amp;', '&lt;', '&gt;', '&quot;', '&#039;', '&#034;', '&#036;', '&#092;'])) {
        $rest[] = (int)$row['id'];
    }
    $list[(string)$row['id']] = ['rule' => $rule, 'format' => getRuleFormat($rule, $def), 'html' => checkLegacyRow($head, $rule), 'done' => false, 'tdone' => false];
}
$sum = array_sum($seen);
$name = [1 => 'legacy markup', 2 => 'machine break', 3 => 'break, review', 4 => 'multi line', 5 => 'single line'];
printf("%-6s %-16s %-10s %7s\n", 'rule', 'signature', 'format', 'rows');
foreach ($seen as $rule => $cnt) {
    printf("%-6d %-16s %-10s %7d\n", $rule, $name[$rule], getRuleFormat($rule, $def), $cnt);
}
printf("%-6s %-16s %-10s %7d of %d\n", '', 'sum', '', $sum, $total);
printf("rows carrying at least one writer entity: %d\n", $ents);
printf("rows written by the html editor, whose entity set is the smaller one: %d\n", $html);
printf("titles carrying at least one writer entity, which the title pass decodes: %d\n", $tits);
printf("titles carrying markup, which a plain title keeps as its own characters: %d\n", $tags);
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
if (!is_dir($out) && !mkdir($out, 0775, true)) {
    fwrite(STDERR, 'Cannot create '.$out."\n");
    exit(1);
}
$stmt = $pdo->prepare('UPDATE '.$tab.' SET format = :format WHERE id = :id');
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
$now = (int)$pdo->query('SELECT COUNT(*) FROM '.$tab)->fetchColumn();
$left = (int)$pdo->query('SELECT COUNT(*) FROM '.$tab.' WHERE format = \'\'')->fetchColumn();
addLedgerFile($file, ['time' => date('Y-m-d H:i:s'), 'default' => $def, 'total' => $total, 'counts' => $seen, 'rows' => $list]);
printf("classify: %d rows written, %d rows left without a format, ledger %s\n", $done, $left, $file);
if ($left || $now !== $total) {
    fwrite(STDERR, "A row is left without a format or the row count changed during the run\n");
    exit(1);
}
exit(0);
