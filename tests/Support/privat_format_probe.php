<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for tools/privat-migrate.php, step 10 of docs/PRIVAT-2026.md
# It boots the real core the way index.php does, so the fixture messages are encoded by the writer an installation really runs
# rather than by a copy of it, and then drives the shipped tool over them exactly the way the deployment sequence drives it
# A case that names an editor is written through filterHtml() under that editor; a case without one is seeded as stored bytes,
# because the html branch of the writer is unreachable from a user request on this installation: every content editor a user
# may pick answers plain or markdown, and only an admin editor answers html
# The whole run goes through --db and --prefix, which is the rehearsal contract of the plan, so the site database is never
# read or written: the probe creates its own schema, works only in it, and drops it again
$probework = (string)($argv[1] ?? '');
require_once __DIR__.'/probe_boot.php';
require_once BASE_DIR.'/core/system.php';

$GLOBALS['pname'] = 'slaed_pf_'.bin2hex(random_bytes(4));

# Open one connection to the server without selecting a schema, which is what creates and drops the disposable database
function getProbeRoot(): PDO {
    global $conf;
    return new PDO('mysql:host='.$conf['db']['host'].';charset=utf8mb4', $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

# The shipped CREATE TABLE of the message table, filled for the disposable database, so the tool is driven against the table an installation really carries
function getProbeTable(): string {
    $text = (string)file_get_contents(BASE_DIR.'/setup/sql/table.sql');
    if (!preg_match('/CREATE TABLE `\{prefix\}_privat`.*?\n\)\s*ENGINE=[^;]*;/s', $text, $hit)) throw new RuntimeException('table.sql carries no privat table');
    return str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [PREFIX_DB, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $hit[0]);
}

# Create the disposable schema with the one table the tool talks to and return a connection bound to it
function addProbeSchema(): PDO {
    global $conf;
    $root = getProbeRoot();
    $root->exec('CREATE DATABASE `'.$GLOBALS['pname'].'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $dsn = 'mysql:host='.$conf['db']['host'].';dbname='.$GLOBALS['pname'].';charset=utf8mb4';
    $pdo = new PDO($dsn, $conf['db']['uname'], $conf['db']['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec(getProbeTable());
    return $pdo;
}

# Drop the disposable schema again and report whether the server is left without it
function deleteProbeSchema(): bool {
    try {
        getProbeRoot()->exec('DROP DATABASE IF EXISTS `'.$GLOBALS['pname'].'`');
    } catch (Throwable) {
        return false;
    }
    return true;
}

# The fixture mailbox: what an author wrote, which editor wrote it, and the verdict and the source the tool has to answer for it
# An empty editor means the two fields are seeded as stored bytes, which is how the legacy shapes of a real table and the unreachable html branch enter the fixture
function getProbeCases(): array {
    return [
        ['edit' => 'plain', 'head' => 'Обмен ссылками', 'body' => "Line one\nLine two",
            'fmt' => 'plain', 'want' => 'Обмен ссылками', 'text' => "Line one\nLine two"],
        ['edit' => 'plain', 'head' => 'Модуль "Вопросы и ответы"', 'body' => 'Say "hi" & 5$ for O\'Neil',
            'fmt' => 'markdown', 'want' => 'Модуль "Вопросы и ответы"', 'text' => 'Say "hi" & 5$ for O\'Neil'],
        ['edit' => 'markdown', 'head' => 'Release notes', 'body' => "**bold** and a link\nsecond line",
            'fmt' => 'markdown', 'want' => 'Release notes', 'text' => "**bold** and a link\nsecond line"],
        ['edit' => 'markdown', 'head' => 'Entities & ampersands', 'body' => 'Type &lt; to open a tag',
            'fmt' => 'markdown', 'want' => 'Entities & ampersands', 'text' => 'Type &lt; to open a tag'],
        ['edit' => '', 'head' => 'Ответ: Хаки для Slaed&gt;&gt;&gt;index.php?name=news&amp;op=view',
            'body' => "Привет.<br />\n[code]&lt;a href=&#039;http://games&#039;&gt;Игры&lt;/a&gt;[/code]<br />\nКидай свою!",
            'fmt' => 'plain', 'want' => 'Ответ: Хаки для Slaed>>>index.php?name=news&op=view',
            'text' => "Привет.\n[code]<a href='http://games'>Игры</a>[/code]\nКидай свою!"],
        ['edit' => '', 'head' => 'Bare break', 'body' => 'one<br />two',
            'fmt' => 'plain', 'want' => 'Bare break', 'text' => "one\ntwo"],
        ['edit' => '', 'head' => 'Say &#034;hi&#034;', 'body' => '<p>Hello <b>world</b> for 5&#036; &amp; O&#039;Neil</p>',
            'fmt' => 'markdown', 'want' => 'Say "hi"', 'text' => '<p>Hello **world** for 5$ &amp; O\'Neil</p>'],
        ['edit' => '', 'head' => 'Tom &amp; Jerry', 'body' => '<i>italic</i> only',
            'fmt' => 'markdown', 'want' => 'Tom &amp; Jerry', 'text' => '*italic* only'],
        ['edit' => '', 'head' => 'First<br />Second', 'body' => 'a plain single line',
            'fmt' => 'markdown', 'want' => "First\nSecond", 'text' => 'a plain single line'],
        ['edit' => '', 'head' => 'Say &#034;hi&#034; again', 'body' => 'plain line with &amp;lt; typed by hand',
            'fmt' => 'markdown', 'want' => 'Say "hi" again', 'text' => 'plain line with &amp;lt; typed by hand'],
    ];
}

# Rewrite the ledger the way an interrupted pass leaves it behind - rows it had already finished and no completion stamp at all - and ask classify to run over it
# A guard that reads the stamp alone lets that ledger through and hands the finished rows to a second pass, which is the one reversal no restore short of the dump can undo
function getProbeCrash(string $dir): array {
    $file = $dir.'/privat-format.json';
    $data = json_decode((string)file_get_contents($file), true);
    unset($data['converted'], $data['titled']);
    foreach (array_keys($data['rows']) as $id) {
        $data['rows'][$id]['done'] = ((int)$id === 1);
        $data['rows'][$id]['tdone'] = false;
    }
    file_put_contents($file, json_encode($data));
    return getProbeTool('classify', $dir);
}

# Point the writer at the editor whose first format is the wanted one and answer the format it really resolved to
function setProbeMode(string $mode): string {
    global $conf;
    $map = ['plain' => 'plain', 'markdown' => 'toastui', 'html' => 'ckeditor'];
    $conf['editor']['user'] = $map[$mode] ?? 'plain';
    return getEditorMode();
}

# Fill the fixture mailbox and answer the editor format every written case really resolved to, so a disabled editor is visible instead of silently testing another branch
# The censor and the autolinker are switched off: both are site settings that would decide what the stored bytes look like, and neither is what the round trip is about
function addProbeRows(PDO $pdo): array {
    global $conf;
    $conf['censor'] = 0;
    $conf['clickable'] = 0;
    $stmt = $pdo->prepare('INSERT INTO `'.PREFIX_DB.'_privat` (id, uidin, uidout, title, body, time, ip) VALUES (:id, 2, 3, :tit, :txt, NOW(), \'127.0.0.1\')');
    $seen = [];
    foreach (getProbeCases() as $key => $case) {
        $head = $case['head'];
        $body = $case['body'];
        if ($case['edit'] !== '') {
            $seen[$key + 1] = setProbeMode($case['edit']);
            $head = filterHtml($head, 1);
            $body = filterHtml($body);
        }
        $stmt->execute(['id' => $key + 1, 'tit' => $head, 'txt' => $body]);
    }
    return $seen;
}

# Read back what the table holds for every fixture row, which is what the expectations of the cases are compared against
function getProbeRows(PDO $pdo): array {
    $out = [];
    foreach ($pdo->query('SELECT id, title, body, format FROM `'.PREFIX_DB.'_privat` ORDER BY id') as $row) {
        $out[(int)$row['id']] = ['title' => (string)$row['title'], 'body' => (string)$row['body'], 'format' => (string)$row['format']];
    }
    return $out;
}

# Run one mode of the shipped tool against the disposable schema through the rehearsal options, and answer its exit code and its whole output
function getProbeTool(string $mode, string $dir): array {
    $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(BASE_DIR.'/tools/privat-migrate.php').' '.$mode
        .' --db='.escapeshellarg($GLOBALS['pname']).' --prefix='.escapeshellarg(PREFIX_DB).' --out='.escapeshellarg($dir).' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    return ['code' => $code, 'out' => implode("\n", $out)];
}

$report = ['error' => '', 'clean' => false, 'quiet' => false, 'left' => false, 'modes' => [], 'runs' => [], 'before' => [],
    'rows' => [], 'cases' => getProbeCases(), 'twice' => [], 'same' => false, 'guard' => [], 'crash' => [], 'gate' => []];

try {
    $pdo = addProbeSchema();
    $dir = $probework.'/migrate';
    $report['modes'] = addProbeRows($pdo);
    $seed = getProbeRows($pdo);
    $report['runs']['report'] = getProbeTool('report', $dir);
    $report['before'] = getProbeRows($pdo);
    $report['quiet'] = ($seed === $report['before'] && !is_file($dir.'/privat-format.json'));
    $report['runs']['classify'] = getProbeTool('classify', $dir);
    $report['runs']['convert'] = getProbeTool('convert', $dir);
    $report['runs']['title'] = getProbeTool('title', $dir);
    $report['runs']['sample'] = getProbeTool('sample', $dir);
    $rows = getProbeRows($pdo);
    $report['rows'] = $rows;
    $report['twice']['convert'] = getProbeTool('convert', $dir);
    $report['twice']['title'] = getProbeTool('title', $dir);
    $report['same'] = (getProbeRows($pdo) === $rows);
    $report['guard'] = getProbeTool('classify', $dir);
    $report['gate'] = [
        'left' => (int)$pdo->query('SELECT COUNT(*) FROM `'.PREFIX_DB.'_privat` WHERE format NOT IN (\'plain\', \'markdown\')')->fetchColumn(),
        'rows' => (int)$pdo->query('SELECT COUNT(*) FROM `'.PREFIX_DB.'_privat`')->fetchColumn(),
        'want' => count(getProbeCases()),
        'ledger' => is_file($dir.'/privat-format.json'),
    ];
    $report['crash'] = getProbeCrash($dir);
    $report['left'] = (getProbeRows($pdo) === $rows);
    if (is_file($dir.'/privat-format.json')) unlink($dir.'/privat-format.json');
} catch (Throwable $err) {
    $report['error'] = $err->getMessage();
}

$report['clean'] = deleteProbeSchema();

echo json_encode($report);
