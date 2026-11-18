<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Decode once the titles a site stored HTML-escaped; the titles are plain text now and escaped on output alone
# A second decode would spoil a title where a person typed an entity, so fix marks each table it decoded in storage/backup/titles.json and never decodes it again
# A row escaped again by every edit loses all its layers; both modes list such rows before and after for a check by eye
# The fix of _categories drops the stored category maps, as they keep a copy of the titles for up to a day
# Usage: php tools/titles.php report - read-only, counts the rows of each table that would change
# Usage: php tools/titles.php fix - decodes the tables not yet marked and marks them
# A one-off for the stand and the production site outside the release archive; production runs fix right after the files of the step are uploaded

if (PHP_SAPI !== 'cli') die('CLI only');

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));

# The plain text of a stored title and the number of escaping layers it loses; every decode shortens the text, so the loop ends
function getTitleText(string $text): array {
    $num = 0;
    while (($next = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== $text) {
        $text = $next;
        $num++;
    }
    return [$text, $num];
}

# The rows of one table whose title a decode changes; a title without an ampersand holds no entity and is never read
function getTitleRows(Database $db, string $tab, string $col): array {
    $res = $db->getSqlQuery('SELECT id, '.$col.' FROM '.PREFIX_DB.$tab.' WHERE '.$col.' LIKE \'%&%\' ORDER BY id');
    if ($res === false) throw new RuntimeException($tab.': the titles cannot be read');
    $out = [];
    while ($row = $db->getSqlRow($res)) {
        [$text, $num] = getTitleText((string)$row[1]);
        if ($num) $out[] = ['id' => intval($row[0]), 'old' => (string)$row[1], 'new' => $text, 'num' => $num];
    }
    return $out;
}

# The tables already decoded on this site; a mark file that cannot be read stops the tool, as an empty list would decode twice
function getTitleMarks(): array {
    $file = BACKUP_DIR.'/titles.json';
    if (!is_file($file)) return [];
    $data = json_decode((string)file_get_contents($file), true);
    if (!is_array($data)) throw new RuntimeException('storage/backup/titles.json cannot be read');
    return $data;
}

# Write the marks whole through a temporary file, so a broken write never leaves half a list
function setTitleMarks(array $data): bool {
    $file = BACKUP_DIR.'/titles.json';
    $temp = $file.'.'.bin2hex(random_bytes(6));
    if (file_put_contents($temp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n", LOCK_EX) === false) return false;
    if (rename($temp, $file)) return true;
    unlink($temp);
    return false;
}

# Decode one table in one transaction and mark it before the commit; a row edited since the read keeps its new title
function setTitleRows(Database $db, string $tab, string $col, array $rows, array $marks): int {
    if (!$db->setSqlBegin()) throw new RuntimeException($tab.': the transaction cannot be started');
    $done = 0;
    foreach ($rows as $one) {
        $sql = 'UPDATE '.PREFIX_DB.$tab.' SET '.$col.' = :new WHERE id = :id AND '.$col.' = :old';
        $res = $db->getSqlQuery($sql, ['new' => $one['new'], 'id' => $one['id'], 'old' => $one['old']]);
        if ($res === false) {
            $db->setSqlRollback();
            throw new RuntimeException($tab.': row '.$one['id'].' cannot be written, nothing was changed');
        }
        $done += $res->rowCount();
    }
    if (!setTitleMarks($marks + [$tab => ['time' => date('Y-m-d H:i:s'), 'rows' => $done]])) {
        $db->setSqlRollback();
        throw new RuntimeException($tab.': storage/backup/titles.json cannot be written, nothing was changed');
    }
    if ($db->setSqlCommit()) return $done;
    setTitleMarks($marks);
    throw new RuntimeException($tab.': the commit failed, nothing was changed');
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['report', 'fix'], true)) {
    fwrite(STDERR, "Usage: php tools/titles.php report|fix\n");
    exit(1);
}

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ob_start();
require BASE_DIR.'/core/system.php';
ob_end_clean();

$list = ['_forum' => 'title', '_categories' => 'title', '_blocks' => 'title', '_admins' => 'title', '_groups' => 'name', '_newsletter' => 'title', '_mail' => 'title',
    '_nodes' => 'title'];
$todo = 0;
try {
    $marks = getTitleMarks();
    foreach ($list as $tab => $col) {
        $rows = getTitleRows($db, $tab, $col);
        if (isset($marks[$tab])) {
            echo $tab, ': decoded ', $marks[$tab]['time'], ', ', count($rows), ' rows hold an entity now, fix leaves the table alone', PHP_EOL;
            continue;
        }
        $deep = array_filter($rows, fn($v) => $v['num'] > 1);
        echo $tab, ': ', count($rows), ' rows to decode, ', count($deep), ' of them escaped more than once', PHP_EOL;
        $todo += count($rows);
        foreach ($deep as $one) echo '  ', $one['id'], ' (', $one['num'], ' layers): ', $one['old'], ' => ', $one['new'], PHP_EOL;
        if ($mode === 'report') continue;
        $done = setTitleRows($db, $tab, $col, $rows, $marks);
        $marks = getTitleMarks();
        $res = ($tab === '_categories') ? $db->getSqlQuery('SELECT DISTINCT modul FROM '.PREFIX_DB.'_categories') : false;
        foreach ($res ? $res->fetchAll(PDO::FETCH_COLUMN) : [] as $mod) deleteCategoryMap((string)$mod);
        echo $tab, ': ', $done, ' rows decoded and the table marked', PHP_EOL;
    }
} catch (RuntimeException $err) {
    fwrite(STDERR, $err->getMessage().PHP_EOL);
    exit(1);
}
if ($mode === 'report' && $todo) echo 'report: nothing was written, run fix to decode', PHP_EOL;
exit(0);
