<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# CLI probe for the configuration protocol of docs/node/06-types.md and the request-owned directory lock of the file layer
# It boots the real core the way index.php does, with the configuration, the journal, the cache and the logs redirected into scratch
# The scratch configuration is a copy of the sources of the stand without local.php, so no scenario ever writes below config/ or storage/ of the site
# A process that dies in the middle of an operation is a real child that exits inside its closure, and concurrency is made of real processes
$pmode = (string)($argv[1] ?? '');
$probework = (string)($argv[2] ?? '');
$pargs = array_slice($argv, 3);
require_once __DIR__.'/probe_boot.php';
$preal = BASE_DIR.'/config';
if (!is_dir($probework.'/config')) {
    mkdir($probework.'/config', 0777, true);
    foreach (glob($preal.'/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') copy($file, $probework.'/config/'.basename($file));
    }
}
define('CONFIG_DIR', $probework.'/config');
define('BACKUP_DIR', $probework.'/backup');
define('CACHE_DIR', $probework.'/cache');
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/filemanager.php';

# The closure every shared writer of the panel is: replace one area of the fresh base and answer what the save answered
function getProbeWriter(string $area, array $data, string $res = ''): Closure {
    return static function (array $base, Closure $save) use ($area, $data, $res): string {
        $base[$area] = array_replace($base[$area], $data);
        $done = $save($base);
        return ($res !== '') ? $res : ($done ? 'committed' : 'aborted');
    };
}

# Hash every source of the scratch configuration, so a scenario proves what it left alone as exactly as what it changed
function getProbeHashes(): array {
    $out = [];
    foreach (glob(CONFIG_DIR.'/*.php') ?: [] as $file) {
        if (basename($file) !== 'local.php') $out[basename($file)] = sha1_file($file);
    }
    ksort($out);
    return $out;
}

# What the journal root holds right now: whether the marker exists and how many operation directories are left
function getProbeTrace(): array {
    return ['marker' => is_file(BACKUP_DIR.'/config/marker.json'), 'dirs' => count(glob(BACKUP_DIR.'/config/*', GLOB_ONLYDIR) ?: [])];
}

# Read the published snapshot the way the next request would, past OPcache
function getProbeLocal(): array {
    $file = CONFIG_DIR.'/local.php';
    if (!is_file($file)) return [];
    if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    $data = require $file;
    return is_array($data['_config'] ?? null) ? $data['_config'] : [];
}

# Read one source the way the next request would, past OPcache
function getProbeSource(string $name): mixed {
    $file = CONFIG_DIR.'/'.$name;
    if (!is_file($file)) return null;
    if (function_exists('opcache_invalidate')) opcache_invalidate($file, true);
    return require $file;
}

# Run one scenario of this probe in a child process and answer its decoded report
function getProbeChild(string $mode, array $args = []): mixed {
    global $probework;
    $line = escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($mode).' '.escapeshellarg($probework);
    foreach ($args as $one) $line .= ' '.escapeshellarg((string)$one);
    return json_decode((string)shell_exec($line.' 2>&1'), true);
}

# Start one scenario of this probe in a child process without waiting for it
function getProbeSpawn(string $mode, array $args = []): mixed {
    global $probework;
    $line = [PHP_BINARY, __FILE__, $mode, $probework];
    foreach ($args as $one) $line[] = (string)$one;
    $pipes = [];
    $proc = proc_open($line, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    return ['proc' => $proc, 'out' => $pipes[1], 'err' => $pipes[2]];
}

# Wait for one spawned child and answer its decoded report
function getProbeReap(array $child): mixed {
    $out = stream_get_contents($child['out']);
    fclose($child['out']);
    fclose($child['err']);
    proc_close($child['proc']);
    return json_decode((string)$out, true);
}

# The string form: the stored shape of an independent source, the published snapshot, the unchanged save and every refused name
function getProbeString(): array {
    $before = getProbeHashes();
    $done = setConfigFile('whois.php', ['zeta' => 2, 'alpha' => true, 'text' => "one\r\ntwo\rthree", 'deep' => ['num' => 7, 'flag' => false]]);
    $code = (string)file_get_contents(CONFIG_DIR.'/whois.php');
    $after = getProbeHashes();
    $data = getProbeSource('whois.php');
    $local = getProbeLocal()['whois'] ?? null;
    $same = setConfigFile('whois.php', ['zeta' => 2, 'alpha' => true, 'text' => "one\r\ntwo\rthree", 'deep' => ['num' => 7, 'flag' => false]]);
    $bytes = (string)file_get_contents(CONFIG_DIR.'/whois.php') === $code;
    $merge = setConfigFile('whois.php', ['zeta' => 3], ['zeta' => 1, 'kept' => 'yes']);
    $deny = [];
    foreach (['local.php', 'system.php', 'fields.php', 'uploads.php', 'ratings.php', 'node.php', '../whois.php', 'Whois.php', 'whois', 'sub/whois.php'] as $name) {
        $deny[$name] = setConfigFile($name, ['probe' => '1']);
    }
    return [
        'done' => $done,
        'data' => $data,
        'crlf' => str_contains($code, "\r"),
        'others' => array_keys(array_diff_assoc($after, $before)),
        'local' => $local,
        'same' => $same,
        'bytes' => $bytes,
        'merge' => $merge ? getProbeSource('whois.php') : null,
        'deny' => $deny,
        'trace' => getProbeTrace(),
        'temps' => glob(CONFIG_DIR.'/*.tmp') ?: [],
    ];
}

# The Closure form: the base it hands over, native types kept, the untouched neighbours, node.php born and taken back, and every refused package
function getProbeClosure(): array {
    $seen = [];
    $before = getProbeHashes();
    $pack = ['text' => 'x', 'num' => 5, 'flag' => true, 'none' => null, 'list' => [1, 'two', ['deep' => false]]];
    $done = setConfigFile(static function (array $base, Closure $save) use (&$seen, $pack): string {
        $seen = ['keys' => array_keys($base), 'node' => $base['node'], 'wrapped' => isset($base['fields']['fields']), 'rules' => count($base['uploads'])];
        $base['fields']['probe'] = $pack;
        return $save($base) ? 'committed' : 'aborted';
    });
    $after = getProbeHashes();
    $deny = [];
    $bad = ['float' => 1.5, 'object' => new stdClass(), 'closure' => static fn(): int => 1, 'resource' => fopen('php://memory', 'rb')];
    foreach ($bad as $name => $val) {
        $deny[$name] = setConfigFile(static function (array $base, Closure $save) use ($val): string {
            $base['fields']['bad'] = ['val' => $val];
            return $save($base) ? 'committed' : 'aborted';
        });
    }
    fclose($bad['resource']);
    $deny['missing'] = setConfigFile(static function (array $base, Closure $save): string {
        unset($base['ratings']);
        return $save($base) ? 'committed' : 'aborted';
    });
    $deny['extra'] = setConfigFile(static function (array $base, Closure $save): string {
        unset($base['node']);
        $base['global'] = ['sitename' => 'probe'];
        return $save($base) ? 'committed' : 'aborted';
    });
    $deny['mixed'] = setConfigFile(getProbeWriter('fields', ['mixed' => '1']), ['fields' => []]);
    $twice = [];
    $second = setConfigFile(static function (array $base, Closure $save) use (&$twice): string {
        $base['fields']['twice'] = 'first';
        $twice[] = $save($base);
        $base['fields']['twice'] = 'second';
        $twice[] = $save($base);
        return 'committed';
    });
    $nested = null;
    setConfigFile(static function (array $base, Closure $save) use (&$nested): string {
        $nested = setConfigFile('whois.php', ['nested' => '1']);
        return 'aborted';
    });
    $proof = ['name' => 'probe', 'id' => 7, 'old' => 1, 'new' => 2, 'kind' => 'update'];
    $born = setConfigFile(getProbeWriter('node', ['types' => ['probe' => ['version' => 1]]]));
    $node = getProbeSource('node.php');
    unlink(CONFIG_DIR.'/node.php');
    $back = setConfigFile(getProbeWriter('node', ['types' => ['probe' => ['version' => 1]]], 'aborted'));
    $gone = !is_file(CONFIG_DIR.'/node.php');
    $throw = setConfigFile(static function (array $base, Closure $save): string {
        $base['fields']['thrown'] = 'x';
        $save($base);
        throw new RuntimeException('probe');
    });
    $abort = setConfigFile(static function (array $base, Closure $save) use ($proof): string {
        $base['fields']['proved'] = 'x';
        $save($base, $proof);
        return 'aborted';
    });
    return [
        'done' => $done,
        'seen' => $seen,
        'kept' => (getProbeSource('fields.php')['fields']['probe'] ?? null) === $pack,
        'changed' => array_keys(array_diff_assoc($after, $before)),
        'local' => (getProbeLocal()['fields']['probe'] ?? null) === $pack,
        'deny' => $deny,
        'twice' => [$second, $twice, getProbeSource('fields.php')['fields']['twice'] ?? null],
        'nested' => $nested,
        'born' => [$born, $node],
        'back' => [$back, $gone],
        'throw' => [$throw, isset(getProbeSource('fields.php')['fields']['thrown'])],
        'abort' => [$abort, isset(getProbeSource('fields.php')['fields']['proved'])],
        'trace' => getProbeTrace(),
    ];
}

# Child: replace the fields area and die inside the closure, which is the process killed after the sources moved and before the committed mark
function getProbeDie(): void {
    setConfigFile(static function (array $base, Closure $save): string {
        $base['fields']['crash'] = 'new';
        $base['node'] = ['types' => ['crash' => ['version' => 1]]];
        $save($base);
        exit(0);
    });
}

# Bring the scratch back to a state without journal and without the crash areas, so the next case starts from a known base
function setProbeReset(): void {
    foreach (glob(BACKUP_DIR.'/config/*/*/*') ?: [] as $file) unlink($file);
    foreach (glob(BACKUP_DIR.'/config/*/*') ?: [] as $one) is_dir($one) ? rmdir($one) : unlink($one);
    foreach (glob(BACKUP_DIR.'/config/*') ?: [] as $one) is_dir($one) ? rmdir($one) : unlink($one);
    if (is_file(CONFIG_DIR.'/node.php')) unlink(CONFIG_DIR.'/node.php');
    global $preal;
    copy($preal.'/fields.php', CONFIG_DIR.'/fields.php');
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    getConfig();
}

# One crash case: a dead writer, an optional tamper of what it left, then what the journal says, what a save answers, what getConfig() serves and what the restore does
function getProbeCase(string $tamper): array {
    setProbeReset();
    $local = sha1_file(CONFIG_DIR.'/local.php');
    getProbeChild('die');
    $mark = json_decode((string)file_get_contents(BACKUP_DIR.'/config/marker.json'), true);
    $root = BACKUP_DIR.'/config/'.($mark['op'] ?? 'none');
    if ($tamper === 'committed') {
        $jour = json_decode((string)file_get_contents($root.'/journal.json'), true);
        file_put_contents($root.'/journal.json', json_encode(['phase' => 'committed'] + $jour, JSON_UNESCAPED_SLASHES));
    }
    if ($tamper === 'partial') copy($root.'/old/fields.php', CONFIG_DIR.'/fields.php');
    if ($tamper === 'backup') file_put_contents($root.'/old/fields.php', "<?php\nreturn ['fields' => ['forged' => '1']];\n");
    if ($tamper === 'source') file_put_contents(CONFIG_DIR.'/fields.php', "<?php\nreturn ['fields' => ['manual' => '1']];\n");
    if ($tamper === 'journal') unlink($root.'/journal.json');
    $jour = getConfigJournal();
    $out = [
        'marker' => is_array($mark),
        'types' => $jour['types'] ?? null,
        'phase' => $jour['phase'] ?? null,
        'verdict' => $jour['verdict'] ?? null,
        'why' => $jour['why'] ?? null,
        'states' => array_map(static fn(array $one): string => $one['state'], $jour['files'] ?? []),
        'served' => sha1_file(CONFIG_DIR.'/local.php') === $local,
        'refused' => [setConfigFile('whois.php', ['late' => '1']), setConfigFile(getProbeWriter('ratings', ['late' => '1|1|1']))],
    ];
    unlink(CONFIG_DIR.'/local.php');
    $conf = getConfig();
    $out['memory'] = [$conf['fields']['crash'] ?? null, isset($conf['node']['types']['crash']), isset($conf['sitename']), is_file(CONFIG_DIR.'/local.php')];
    if (!is_dir(CACHE_DIR.'/pages')) mkdir(CACHE_DIR.'/pages', 0777, true);
    file_put_contents(CACHE_DIR.'/pages/probe.html', 'stale');
    $out['restore'] = setConfigRestore();
    $out['again'] = setConfigRestore();
    $out['after'] = [
        'crash' => getProbeSource('fields.php')['fields']['crash'] ?? null,
        'node' => is_file(CONFIG_DIR.'/node.php'),
        'local' => getProbeLocal()['fields']['crash'] ?? null,
        'cache' => is_file(CACHE_DIR.'/pages/probe.html'),
        'trace' => getProbeTrace(),
        'save' => $out['restore'] ? setConfigFile('whois.php', ['late' => '2']) : null,
    ];
    return $out;
}

# Every crash case, and the plain rebuild a missing local.php gets when no marker forbids it
function getProbeCrash(): array {
    $out = [];
    foreach (['none', 'partial', 'committed', 'backup', 'source', 'journal'] as $case) $out[$case] = getProbeCase($case);
    setProbeReset();
    $proof = ['name' => 'probe', 'id' => 7, 'old' => 1, 'new' => 2, 'kind' => 'update'];
    $done = setConfigFile(static function (array $base, Closure $save) use ($proof): string {
        $base['fields']['proved'] = 'x';
        $save($base, $proof);
        return 'uncertain';
    });
    $jour = getConfigJournal();
    $out['proof'] = [$done, $jour['why'] ?? null, $jour['types'] ?? null, $jour['proof'] ?? null, setConfigRestore(), getProbeTrace()['marker']];
    setProbeReset();
    unlink(CONFIG_DIR.'/local.php');
    $conf = getConfig();
    $out['rebuild'] = [isset($conf['sitename']), is_file(CONFIG_DIR.'/local.php'), getProbeLocal() === $conf];
    return $out;
}

# Child: save a run of keys into one area, one operation per key
function getProbeRun(string $area, int $count): array {
    $out = [];
    for ($i = 0; $i < $count; $i++) $out[] = setConfigFile(getProbeWriter($area, [$area.'key'.$i => 'v'.$i]));
    return $out;
}

# Two writers of two different areas at once: neither may lose a key of its own or wipe the area of the other
function getProbeRace(): array {
    setProbeReset();
    $one = getProbeSpawn('run', ['fields', 6]);
    $two = getProbeSpawn('run', ['ratings', 6]);
    $runs = [getProbeReap($one), getProbeReap($two)];
    $fields = getProbeSource('fields.php')['fields'] ?? [];
    $rates = getProbeSource('ratings.php')['ratings'] ?? [];
    $local = getProbeLocal();
    $count = static fn(array $arr, string $pre): int => count(array_filter(array_keys($arr), static fn($v): bool => str_starts_with((string)$v, $pre)));
    return [
        'runs' => $runs,
        'fields' => $count($fields, 'fieldskey'),
        'ratings' => $count($rates, 'ratingskey'),
        'local' => [$count($local['fields'] ?? [], 'fieldskey'), $count($local['ratings'] ?? [], 'ratingskey')],
        'kept' => isset($fields['account'], $rates['account']),
        'trace' => getProbeTrace(),
    ];
}

# Child: try the lock file of one key without waiting and say whether it could be taken
function getProbeTry(string $dir): array {
    $file = LOGS_DIR.'/uploads/'.substr(sha1(rtrim(str_replace('\\', '/', $dir), '/')), 0, 16).'.lock';
    $fh = fopen($file, 'cb');
    $free = ($fh !== false) && flock($fh, LOCK_EX | LOCK_NB);
    if ($free) flock($fh, LOCK_UN);
    if ($fh !== false) fclose($fh);
    return ['free' => $free];
}

# Child: take the lock of one key through the file layer, waiting for it, and say when it got through
function getProbeWait(string $dir): array {
    $from = microtime(true);
    file_put_contents($dir.'.ready', '1');
    $lock = FileManager::getPathLock($dir);
    $done = microtime(true);
    FileManager::deletePathLock($lock);
    return ['from' => $from, 'done' => $done, 'held' => $lock !== false];
}

# The request-owned lock: a second entry of one key neither waits nor opens a second handle, the flock goes with the last release, and another process stands in the queue
function getProbeLock(): array {
    global $probework;
    $dir = $probework.'/locked';
    $one = FileManager::getPathLock($dir);
    $two = FileManager::getPathLock($dir.'/');
    $sub = FileManager::getPathLock($dir.'/sub');
    $out = ['held' => $one !== false, 'same' => $one === $two, 'sub' => $sub !== false && $sub !== $one];
    FileManager::deletePathLock($sub);
    $out['both'] = getProbeChild('try', [$dir])['free'] ?? null;
    FileManager::deletePathLock($two);
    $out['inner'] = getProbeChild('try', [$dir])['free'] ?? null;
    $child = getProbeSpawn('wait', [$dir]);
    for ($i = 0; $i < 200 && !is_file($dir.'.ready'); $i++) usleep(50000);
    usleep(300000);
    $free = microtime(true);
    FileManager::deletePathLock($one);
    $wait = getProbeReap($child);
    $out['queue'] = [($wait['held'] ?? false), ($wait['from'] ?? $free) < $free, ($wait['done'] ?? 0) >= $free];
    $out['outer'] = getProbeChild('try', [$dir])['free'] ?? null;
    $again = FileManager::getPathLock($dir);
    $out['again'] = $again !== false;
    FileManager::deletePathLock($again);
    return $out;
}

# Remove the scratch tree of this probe
function deleteProbeTree(string $dir): void {
    if (!is_dir($dir)) return;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iter as $one) $one->isDir() ? rmdir($one->getPathname()) : unlink($one->getPathname());
    rmdir($dir);
}

$report = ['error' => ''];

# The scratch carries a copy of the real sources, the database password among them, so it goes even when a scenario ends in a fatal error
if (!in_array($pmode, ['run', 'try', 'wait', 'die'], true)) register_shutdown_function('deleteProbeTree', $probework);

try {
    $report['data'] = match ($pmode) {
        'string' => getProbeString(),
        'closure' => getProbeClosure(),
        'crash' => getProbeCrash(),
        'race' => getProbeRace(),
        'lock' => getProbeLock(),
        'run' => getProbeRun((string)($pargs[0] ?? ''), (int)($pargs[1] ?? 0)),
        'try' => getProbeTry((string)($pargs[0] ?? '')),
        'wait' => getProbeWait((string)($pargs[0] ?? '')),
        'die' => getProbeDie(),
        default => throw new RuntimeException('unknown mode'),
    };
} catch (Throwable $err) {
    $report['error'] = $err->getMessage().' @ '.basename($err->getFile()).':'.$err->getLine();
}

if (in_array($pmode, ['run', 'try', 'wait', 'die'], true)) {
    echo json_encode($report['data'] ?? $report, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$report['site'] = is_file(BASE_DIR.'/storage/backup/config/marker.json');
deleteProbeTree($probework);
echo json_encode($report, JSON_INVALID_UTF8_SUBSTITUTE);
