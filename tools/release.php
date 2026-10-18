<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Builds a release from a git tag, publishes what tools/sign.php signed, and writes the notes of the master; it never touches a private key
# Usage: php tools/release.php build v8.0.1 [--from=v8.0.0] [--min=8.0.0] [--expires=45] [--max=<MB>] [--security]
# Usage: php tools/release.php publish v8.0.1 | publish --note=<id> | publish --keys=<kseq>
# Usage: php tools/release.php note --level info|warn|danger [--ver "<8.0.2"] [--until 2026-12-31] [--sid <sid>]
# The output lies in storage/release/<version>/: slaed-<version>.zip, slaed-<version>-from-<from>.zip, files.json and the two manifests
# Publishing needs the green rehearsal of tools/rehearse.php for the same archives, then pushes the local tag, creates the GitHub release and fills inbox/ of the master
# Settings of publishing in tools/.env: SLAED_SFTP=user@slaed.net:22 and SLAED_SFTP_PATH=<the folder storage/update/master of slaed.net>

if (PHP_SAPI !== 'cli') die('CLI only');

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('FUNC_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
define('UPDATE_DIR', BASE_DIR.'/storage/update');
define('RELEASE_DIR', BASE_DIR.'/storage/release');
require_once BASE_DIR.'/core/classes/update.php';

# Print the reason to the error stream and leave with a failure code
function setFailExit(string $text): never {
    fwrite(STDERR, 'Refused: '.$text."\n");
    exit(1);
}

# Split the command line into positional words and options; --name=value and --name value both work, a name of $flags takes no value
function getArgs(array $argv, array $flags): array {
    $out = ['pos' => [], 'opt' => []];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = (string)$argv[$i];
        if (!str_starts_with($arg, '--')) {
            $out['pos'][] = $arg;
            continue;
        }
        [$name, $val] = array_pad(explode('=', substr($arg, 2), 2), 2, null);
        if ($val === null) $val = in_array($name, $flags, true) ? '1' : (string)($argv[++$i] ?? '');
        $out['opt'][$name] = $val;
    }
    return $out;
}

# One value of tools/.env, the gitignored settings of the owner's machine, or an empty string
function getEnvValue(string $name): string {
    $text = is_file(__DIR__.'/.env') ? (string)file_get_contents(__DIR__.'/.env') : '';
    return preg_match('#^'.preg_quote($name, '#').'=(.*)$#m', $text, $hit) ? trim($hit[1], " \t\r\"'") : '';
}

# Run a program without a shell in the project folder: its exit code, its output and its error text, the latter through a file so neither pipe can stall
function getCommandRun(array $cmd): array {
    $err = tempnam(sys_get_temp_dir(), 'slr');
    $proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $err, 'w']], $pipes, BASE_DIR);
    if (!is_resource($proc)) setFailExit('cannot start '.$cmd[0]);
    fclose($pipes[0]);
    $out = (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($proc);
    $text = trim((string)file_get_contents($err));
    unlink($err);
    return ['code' => $code, 'out' => $out, 'err' => $text];
}

# The output of a git command, or a stop with its error text
function getGitText(array $args): string {
    $res = getCommandRun(array_merge(['git'], $args));
    if ($res['code'] !== 0) setFailExit('git '.implode(' ', $args).': '.preg_replace('#(https?://)[^/@\s]+@#', '$1***@', $res['err']));
    return $res['out'];
}

# The version core/version.php of a tag names, or an empty string when the tag has no such file
function getTagVersion(string $tag): string {
    $res = getCommandRun(['git', 'show', $tag.':core/version.php']);
    return ($res['code'] === 0 && preg_match("#define\('SL_VERSION', '([^']*)'\)#", $res['out'], $hit)) ? $hit[1] : '';
}

# The release tags vX.Y.Z whose tree carries core/version.php, the first one with an updater and every later one, by version
function getReleaseTags(): array {
    $list = [];
    foreach (preg_split('#\R#', trim(getGitText(['tag', '--list', 'v*']))) ?: [] as $tag) {
        $ver = substr($tag, 1);
        if (Update::isVersion($ver) && getTagVersion($tag) === $ver) $list[$ver] = $tag;
    }
    uksort($list, 'version_compare');
    return $list;
}

# Delete the files of one folder and the folder itself, so a rebuild never leaves an old signature beside a new archive
function deleteDirFiles(string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $file) if (is_file($file)) unlink($file);
    if (is_dir($dir)) rmdir($dir);
}

# The array a settings file of the archive returns, read through a scratch copy; the file is the tag's own code
function getConfigArray(string $text): array {
    $tmp = tempnam(sys_get_temp_dir(), 'slaed-conf');
    file_put_contents($tmp, $text);
    $data = include $tmp;
    unlink($tmp);
    return is_array($data) ? $data : [];
}

# A module the tag holds whose folder export-ignore keeps out of the archive loses its row in config/modules.php of the archive, so no site lists a module it lacks
# The row is cut out of the generated text, and the result must return the same array without those keys, or the build stops
function setArchiveModules(string $tag, string $file): array {
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) setFailExit('git archive wrote no zip for '.$tag);
    $have = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (preg_match('#^modules/([^/]+)/#', (string)$zip->getNameIndex($i), $hit) === 1) $have[$hit[1]] = true;
    }
    $gone = [];
    foreach (preg_split('#\R#', trim(getGitText(['ls-tree', '--name-only', $tag, 'modules/'])), -1, PREG_SPLIT_NO_EMPTY) as $path) {
        if (!isset($have[basename($path)])) $gone[] = basename($path);
    }
    $text = (string)$zip->getFromName('config/modules.php');
    $data = getConfigArray($text);
    $cut = array_values(array_filter($gone, static fn(string $mod): bool => isset($data['modules'][$mod])));
    if ($cut !== []) {
        $new = $text;
        foreach ($cut as $mod) $new = (string)preg_replace("#^        '".preg_quote($mod, '#')."' => \[\n(?:            .*\n)*?        \],\n#m", '', $new, 1);
        $want = $data;
        foreach ($cut as $mod) unset($want['modules'][$mod]);
        if (getConfigArray($new) !== $want) setFailExit('the rows of '.implode(', ', $cut).' do not leave config/modules.php of '.$tag.' cleanly');
        $zip->addFromString('config/modules.php', $new);
    }
    if (!$zip->close()) setFailExit('cannot write '.$file);
    return $cut;
}

# The archive of a tag: git archive honours export-ignore, every entry passes the path rules of a package, and files.json with SHA-256 and the site marks goes in
function addArchive(string $tag, string $file, string $ver): array {
    getGitText(['archive', '--format=zip', '-o', $file, $tag]);
    setArchiveModules($tag, $file);
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) setFailExit('git archive wrote no zip for '.$tag);
    $files = [];
    $usize = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        $name = (string)$stat['name'];
        $zip->getExternalAttributesIndex($i, $sys, $attr);
        if ((($attr >> 16) & 0xF000) === 0xA000) setFailExit($tag.' holds the symlink '.$name);
        if (!Update::isPackagePath($name)) setFailExit($tag.' holds the path '.$name.', which no package may hold');
        if (str_ends_with($name, '/')) continue;
        if ($name === 'storage/update/files.json') setFailExit($tag.' carries its own storage/update/files.json');
        $hand = hash_init('sha256');
        $read = $zip->getStreamIndex($i);
        hash_update_stream($hand, $read);
        fclose($read);
        $files[$name] = ['sha256' => hash_final($hand), 'size' => intval($stat['size'])] + (Update::isSiteFile($name) ? ['site' => true] : []);
        $usize += intval($stat['size']);
    }
    ksort($files, SORT_STRING);
    $list = json_encode(['version' => $ver, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    $zip->addFromString('storage/update/files.json', $list);
    if (!$zip->close()) setFailExit('cannot write '.$file);
    return ['files' => $files, 'list' => $list, 'usize' => $usize + strlen($list)];
}

# The delta from a previous release: every file that list lacks or holds with another hash, every settings file of config/ for the merge, and the full files.json
function addDelta(string $full, string $file, array $old, array $pack): int {
    $src = new ZipArchive();
    $zip = new ZipArchive();
    if ($src->open($full) !== true || $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) setFailExit('cannot write '.$file);
    $usize = 0;
    foreach ($pack['files'] as $name => $one) {
        $keep = !empty($one['site']) && str_starts_with($name, 'config/');
        if (!$keep && ($old[$name]['sha256'] ?? '') === $one['sha256']) continue;
        $zip->addFromString($name, (string)$src->getFromName($name));
        $usize += $one['size'];
    }
    $zip->addFromString('storage/update/files.json', $pack['list']);
    $src->close();
    if (!$zip->close()) setFailExit('cannot write '.$file);
    return $usize + strlen($pack['list']);
}

# Build a release from its local tag: a clean tree, the version of the code, a full archive and a delta, a size check against the last release, two manifests
function setBuild(string $tag, array $opt): void {
    $ver = substr($tag, 1);
    if (!str_starts_with($tag, 'v') || !Update::isVersion($ver)) setFailExit('the tag is vX.Y.Z');
    if (getCommandRun(['git', 'rev-parse', '-q', '--verify', 'refs/tags/'.$tag])['code'] !== 0) setFailExit('no local tag '.$tag.'; git tag '.$tag.' first');
    if (trim(getGitText(['status', '--porcelain', '--untracked-files=no'])) !== '') setFailExit('the tree is not clean');
    $code = getTagVersion($tag);
    if ($code !== $ver) setFailExit('core/version.php of '.$tag.' names '.($code === '' ? 'no version' : $code));
    $tags = getReleaseTags();
    unset($tags[$ver]);
    $lower = array_filter(array_keys($tags), static fn(string $one): bool => version_compare($one, $ver, '<'));
    $from = isset($opt['from']) ? substr((string)$opt['from'], 1) : (string)(end($lower) ?: '');
    if ($from !== '' && !isset($tags[$from])) setFailExit('--from names no release tag with an updater');
    $higher = array_filter(array_keys($tags), static fn(string $one): bool => !version_compare($one, $ver, '<'));
    if ($higher) setFailExit($ver.' is not above the release '.max($higher));
    $min = (string)($opt['min'] ?? (array_key_first($tags) ?? $ver));
    if (!Update::isVersion($min) || version_compare($min, $ver, '>')) setFailExit('--min is a version not above '.$ver);
    $comp = json_decode(getGitText(['show', $tag.':composer.json']), true);
    if (!preg_match('#(\d{1,2}\.\d{1,2}(\.\d{1,3})?)#', (string)($comp['require']['php'] ?? ''), $php)) setFailExit('composer.json of '.$tag.' names no PHP version');
    $days = intval($opt['expires'] ?? 45);
    if ($days < 1 || $days > 365) setFailExit('--expires is 1 to 365 days');
    $max = isset($opt['max']) ? (float)$opt['max'] : null;
    $dir = RELEASE_DIR.'/'.$ver;
    deleteDirFiles($dir);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) setFailExit('cannot create '.$dir);
    $full = $dir.'/slaed-'.$ver.'.zip';
    $pack = addArchive($tag, $full, $ver);
    $size = intval(filesize($full));
    $old = [];
    if ($from !== '') {
        $prev = $dir.'/previous.zip';
        $old = addArchive($tags[$from], $prev, $from);
        $osize = intval(filesize($prev));
        unlink($prev);
        $grow = sprintf('the full archive grew from %.1f to %.1f MB; confirm the size with --max=<MB>', $osize / 1048576, $size / 1048576);
        if ($size > $osize * 1.2 && $max === null) setFailExit($grow);
    } elseif ($max === null) {
        setFailExit(sprintf('the first release confirms its size: the full archive is %.1f MB, pass --max=<MB>', $size / 1048576));
    }
    if ($max !== null && $size > $max * 1048576) setFailExit(sprintf('the full archive is %.1f MB, above --max=%s', $size / 1048576, $opt['max']));
    $delta = null;
    if ($from !== '') {
        $dfile = $dir.'/slaed-'.$ver.'-from-'.$from.'.zip';
        $dsize = addDelta($full, $dfile, $old['files'], $pack);
        $delta = ['size' => intval(filesize($dfile)), 'usize' => $dsize, 'sha256' => hash_file('sha256', $dfile), 'from' => $from];
    }
    file_put_contents($dir.'/files.json', $pack['list']);
    $now = time();
    $data = ['type' => 'release', 'version' => $ver, 'channel' => '', 'min' => $min, 'php' => $php[1], 'security' => isset($opt['security']),
        'full' => ['size' => $size, 'usize' => $pack['usize'], 'sha256' => hash_file('sha256', $full)], 'delta' => $delta,
        'commit' => trim(getGitText(['rev-list', '-n', '1', $tag])), 'time' => $now, 'expires' => $now + $days * 86400, 'kid' => ''];
    foreach (['beta', 'stable'] as $chan) {
        $data['channel'] = $chan;
        file_put_contents($dir.'/manifest-'.$chan.'.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    fwrite(STDOUT, sprintf("Built %s: %d files, full %.1f MB%s\n", $ver, count($pack['files']), $size / 1048576,
        $delta ? sprintf(', delta from %s %.1f MB', $from, $delta['size'] / 1048576) : '').'Next: php tools/sign.php '.$ver."\n");
}

# The factory keys a tree of the core names, read from its source by the two constants of Update
function getCodeKeys(string $code): array {
    preg_match("#const PRIMARY = '([^']*)'#", $code, $pri);
    preg_match("#const RECOVERY = '([^']*)'#", $code, $rec);
    return ['kseq' => 0, 'primary' => $pri[1] ?? '', 'recovery' => $rec[1] ?? '', 'revoked' => []];
}

# Stop when factory keys are the test keys of tests/Fixtures/update/ or are missing from the README: a release with them could never be corrected
function checkFactoryKeys(array $keys, string $readme): void {
    $test = [];
    foreach (['primary', 'recovery'] as $role) {
        $data = json_decode((string)file_get_contents(BASE_DIR.'/tests/Fixtures/update/'.$role.'.key'), true);
        if (!is_array($data) || !is_string($data['pub'] ?? null)) setFailExit('the test key tests/Fixtures/update/'.$role.'.key is missing, so test keys cannot be told apart');
        $test[] = $data['pub'];
    }
    $flat = strtolower(preg_replace('#\s+#', '', $readme));
    foreach (['primary', 'recovery'] as $role) {
        $raw = Update::getRawBytes($keys[$role], 32);
        if ($raw === '') setFailExit('the core names no '.$role.' factory key');
        if (in_array($keys[$role], $test, true)) setFailExit('the '.$role.' factory key is the test key; the key ceremony replaces it');
        if (!str_contains($flat, Update::getKeyPrint($raw))) setFailExit('README.md does not publish the fingerprint of the '.$role.' factory key');
    }
}

# The keys in force: the given factory keys after every key statement of storage/release/keys/
function getKeyState(array $keys): array {
    $res = Update::getKeyChain($keys, Update::getKeyList(RELEASE_DIR.'/keys'));
    if (!$res['ok']) setFailExit('a key statement of storage/release/keys/ fails: '.$res['error']);
    return $res['keys'];
}

# The path of gh: on PATH, or where its installer puts it on Windows
function getGhPath(): string {
    $win = 'C:/Program Files/GitHub CLI/gh.exe';
    return (PHP_OS_FAMILY === 'Windows' && is_file($win)) ? $win : 'gh';
}

# Put files into one folder of inbox/ of the master by SFTP, each as .part and then renamed, and fetch each back to compare its SHA-256
function setInboxUpload(string $sub, array $files): void {
    $host = getEnvValue('SLAED_SFTP');
    $root = rtrim(getEnvValue('SLAED_SFTP_PATH'), '/');
    if (!preg_match('#^([^@\s]+@[^:\s]+)(?::(\d+))?$#', $host, $hit) || $root === '') setFailExit('tools/.env names no SLAED_SFTP=user@host:port and SLAED_SFTP_PATH');
    $dest = $root.'/inbox/'.$sub;
    $back = sys_get_temp_dir().'/slaed-back-'.bin2hex(random_bytes(4));
    mkdir($back);
    $lines = ['-mkdir "'.$root.'/inbox"'];
    $path = '';
    foreach (explode('/', $sub) as $part) $lines[] = '-mkdir "'.$root.'/inbox'.($path .= '/'.$part).'"';
    foreach ($files as $file) {
        $name = basename($file);
        array_push($lines, 'put "'.$file.'" "'.$dest.'/'.$name.'.part"', '-rm "'.$dest.'/'.$name.'"', 'rename "'.$dest.'/'.$name.'.part" "'.$dest.'/'.$name.'"',
            'get "'.$dest.'/'.$name.'" "'.$back.'/'.$name.'"');
    }
    $batch = $back.'/batch.txt';
    file_put_contents($batch, implode("\n", $lines)."\n");
    $res = getCommandRun(['sftp', '-b', $batch, '-P', $hit[2] ?? '22', $hit[1]]);
    unlink($batch);
    if ($res['code'] !== 0) setFailExit('sftp: '.$res['err']);
    foreach ($files as $file) {
        $copy = $back.'/'.basename($file);
        $same = is_file($copy) && hash_file('sha256', $copy) === hash_file('sha256', $file);
        if (is_file($copy)) unlink($copy);
        if (!$same) setFailExit(basename($file).' came back from the master with another SHA-256');
    }
    rmdir($back);
    fwrite(STDOUT, 'In inbox/'.$sub.' of the master: '.count($files)." files, each fetched back and compared\n");
}

# The repository of the releases, read from the fixed address of a release page in the core, so the GitHub release and the links of the sites never part
function getReleaseRepo(): string {
    return (preg_match('#^https://github\.com/([^/]+/[^/]+)/releases/tag/v$#D', Update::PAGE, $hit) === 1) ? $hit[1] : '';
}

# Stop unless origin is the repository of the releases, where the tag goes and gh publishes; the address of origin may carry a token and is never printed
function checkReleaseRepo(string $repo): void {
    $res = getCommandRun(['git', 'remote', 'get-url', 'origin']);
    $url = trim($res['out']);
    $same = $repo !== '' && $res['code'] === 0 && preg_match('#github\.com[:/]([^/\s]+/[^/\s]+?)(\.git)?/?$#i', $url, $hit) === 1 && strcasecmp($hit[1], $repo) === 0;
    if (!$same) setFailExit('origin is not github.com/'.$repo.', the repository the release page of the sites names (Update::PAGE)');
}

# The text of the GitHub release for people: which archive installs and which updates, their SHA-256, and that the archives GitHub adds are sources only
function getReleaseNotes(string $repo, string $tag, array $packs): string {
    $out = [];
    foreach ($packs as $name => $one) {
        $out[] = isset($one['from']) ? '- A site exactly on '.$one['from'].' may take the smaller delta `'.$name.'`, SHA-256 `'.$one['sha256'].'`'
            : '- A new site, or an upload by hand on the page "Updates" of the panel: `'.$name.'`, SHA-256 `'.$one['sha256'].'`';
    }
    $out[] = '';
    $out[] = 'The "Source code" archives GitHub adds are the sources for developers, not a release: they lack `storage/update/files.json`.';
    $out[] = '';
    $out[] = 'Changes: https://github.com/'.$repo.'/blob/'.$tag.'/docs/VERSIONS.md';
    return implode("\n", $out);
}

# Stop unless tools/rehearse.php left a green record of exactly the archives the signed manifest names
function checkRehearsal(string $dir, array $man): void {
    $rec = is_file($dir.'/rehearsal.json') ? json_decode((string)file_get_contents($dir.'/rehearsal.json'), true) : null;
    $green = is_array($rec) && ($rec['version'] ?? '') === $man['version'] && ($rec['full'] ?? '') === $man['full']['sha256']
        && ($rec['delta'] ?? null) === ($man['delta']['sha256'] ?? null);
    if (!$green) setFailExit('no green rehearsal of these archives; php tools/rehearse.php v'.$man['version']);
}

# Publish a signed release: real factory keys published in the README, valid signatures, a green rehearsal, then the tag, the GitHub release and the inbox of the master
function setPublish(string $tag): void {
    $ver = substr($tag, 1);
    if (!str_starts_with($tag, 'v') || !Update::isVersion($ver)) setFailExit('the tag is vX.Y.Z');
    if (getCommandRun(['git', 'rev-parse', '-q', '--verify', 'refs/tags/'.$tag])['code'] !== 0) setFailExit('no local tag '.$tag);
    $dir = RELEASE_DIR.'/'.$ver;
    $keys = getCodeKeys(getGitText(['show', $tag.':core/classes/update.php']));
    checkFactoryKeys($keys, getGitText(['show', $tag.':README.md']));
    $keys = getKeyState($keys);
    $files = [];
    foreach (['beta', 'stable'] as $chan) {
        $name = $dir.'/manifest-'.$chan.'.json';
        if (!is_file($name) || !is_file($name.'.sig')) setFailExit('manifest-'.$chan.'.json of '.$ver.' is not signed; php tools/sign.php '.$ver);
        $res = Update::getManifest((string)file_get_contents($name), trim((string)file_get_contents($name.'.sig')), $keys, ['version' => '0.0.0', 'channel' => 'beta']);
        if (!$res['ok'] || $res['data']['channel'] !== $chan || $res['data']['version'] !== $ver) setFailExit('manifest-'.$chan.'.json does not verify: '.$res['error']);
        $data = $res['data'];
        array_push($files, $name, $name.'.sig');
    }
    $packs = ['slaed-'.$ver.'.zip' => $data['full']] + ($data['delta'] ? ['slaed-'.$ver.'-from-'.$data['delta']['from'].'.zip' => $data['delta']] : []);
    foreach ($packs as $name => $one) {
        if (!is_file($dir.'/'.$name) || filesize($dir.'/'.$name) !== $one['size'] || hash_file('sha256', $dir.'/'.$name) !== $one['sha256']) {
            setFailExit($name.' is not the archive the signed manifest names');
        }
        $files[] = $dir.'/'.$name;
    }
    checkRehearsal($dir, $data);
    if (getEnvValue('SLAED_SFTP') === '' || getEnvValue('SLAED_SFTP_PATH') === '') setFailExit('tools/.env names no SLAED_SFTP and SLAED_SFTP_PATH');
    $repo = getReleaseRepo();
    checkReleaseRepo($repo);
    if (trim(getGitText(['ls-remote', '--tags', 'origin', 'refs/tags/'.$tag])) === '') getGitText(['push', 'origin', 'refs/tags/'.$tag]);
    $gh = getGhPath();
    $zips = array_map(static fn(string $name): string => $dir.'/'.$name, array_keys($packs));
    $view = getCommandRun([$gh, 'release', 'view', $tag, '--repo', $repo]);
    $res = ($view['code'] === 0) ? getCommandRun(array_merge([$gh, 'release', 'upload', $tag, '--clobber', '--repo', $repo], $zips))
        : getCommandRun(array_merge([$gh, 'release', 'create', $tag, '--verify-tag', '--repo', $repo, '--title', 'SLAED CMS '.$ver,
            '--notes', getReleaseNotes($repo, $tag, $packs)], $zips));
    if ($res['code'] !== 0) setFailExit('gh release: '.$res['err']);
    $back = sys_get_temp_dir().'/slaed-gh-'.bin2hex(random_bytes(4));
    foreach (array_keys($packs) as $name) {
        $res = getCommandRun([$gh, 'release', 'download', $tag, '--pattern', $name, '--dir', $back, '--clobber', '--repo', $repo]);
        $same = $res['code'] === 0 && hash_file('sha256', $back.'/'.$name) === $packs[$name]['sha256'];
        if (is_file($back.'/'.$name)) unlink($back.'/'.$name);
        if (!$same) setFailExit($name.' came back from GitHub with another SHA-256');
    }
    if (is_dir($back)) rmdir($back);
    setInboxUpload($ver, $files);
    fwrite(STDOUT, 'Published '.$ver.'; next: Master → Releases → Accept on slaed.net'."\n");
}

# Publish a signed note or key statement into inbox/notes or inbox/keys, after its signature verified under the keys in force
function setPublishItem(string $kind, string $id): void {
    $keys = getKeyState(['kseq' => 0, 'primary' => Update::PRIMARY, 'recovery' => Update::RECOVERY, 'revoked' => []]);
    checkFactoryKeys(['primary' => Update::PRIMARY, 'recovery' => Update::RECOVERY], (string)file_get_contents(BASE_DIR.'/README.md'));
    $base = RELEASE_DIR.'/'.$kind.'/'.$id;
    if (!ctype_digit($id) || !is_file($base.'.json') || !is_file($base.'.sig')) setFailExit('no signed '.$kind.' item '.$id.' in '.RELEASE_DIR.'/'.$kind);
    $body = (string)file_get_contents($base.'.json');
    $sig = Update::getRawBytes(trim((string)file_get_contents($base.'.sig')), 64);
    $good = ($kind === 'notes') ? Update::isSignValid(Update::NOTE.$body, $sig, Update::getRawBytes($keys['primary'], 32))
        : in_array($id, array_map(static fn(array $one): string => (string)(json_decode($one['body'], true)['kseq'] ?? ''), Update::getKeyList(RELEASE_DIR.'/keys')), true);
    if (!$good) setFailExit($kind.' item '.$id.' does not verify under the keys in force');
    setInboxUpload($kind, array_values(array_filter([$base.'.json', $base.'.sig', $base.'.psig'], 'is_file')));
}

# Read a text of several lines from the console until a line that holds a single dot
function getInputText(string $label): string {
    fwrite(STDOUT, $label."\n");
    $lines = [];
    while (($line = fgets(STDIN)) !== false && rtrim($line, "\r\n") !== '.') $lines[] = rtrim($line, "\r\n");
    return trim(implode("\n", $lines));
}

# Write a note of the master for sign.php: level, range of versions, an optional sid and deadline, plain text in Russian and English
function addNote(array $opt): void {
    $until = (string)($opt['until'] ?? '');
    $stop = ($until === '') ? 0 : intval(strtotime($until.' 23:59:59 UTC'));
    if ($until !== '' && (!preg_match('#^\d{4}-\d{2}-\d{2}$#D', $until) || $stop <= time())) setFailExit('--until is a future date YYYY-MM-DD');
    $data = ['type' => 'note', 'id' => time(), 'level' => (string)($opt['level'] ?? ''), 'ver' => trim((string)($opt['ver'] ?? '')), 'sid' => (string)($opt['sid'] ?? ''),
        'until' => $stop, 'ru' => '', 'en' => ''];
    if (!in_array($data['level'], ['info', 'warn', 'danger'], true)) setFailExit('--level is info, warn or danger');
    if (preg_match(Update::RANGE, $data['ver']) !== 1) setFailExit('--ver is a range such as "<8.0.2" or ">=8.0.0 <8.0.2"');
    if (preg_match('#^([A-Za-z0-9]{32})?$#D', $data['sid']) !== 1) setFailExit('--sid is the 32 characters a site shows');
    $data['ru'] = getInputText('Text in Russian, plain, up to 1000 characters; end with a line holding a single dot:');
    $data['en'] = getInputText('Text in English, the same way:');
    if (!Update::isNote($data)) setFailExit('each text is plain, not empty and at most 1000 characters');
    $file = RELEASE_DIR.'/notes/'.$data['id'].'.json';
    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true) && !is_dir(dirname($file))) setFailExit('cannot create '.dirname($file));
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    fwrite(STDOUT, 'Note '.$data['id'].' written; next: php tools/sign.php --note '.$data['id']."\n");
}

$args = getArgs($argv, ['security']);
$mode = (string)($args['pos'][0] ?? '');
$opt = $args['opt'];
if ($mode === 'build' && isset($args['pos'][1])) setBuild((string)$args['pos'][1], $opt);
elseif ($mode === 'publish' && isset($opt['note'])) setPublishItem('notes', (string)$opt['note']);
elseif ($mode === 'publish' && isset($opt['keys'])) setPublishItem('keys', (string)$opt['keys']);
elseif ($mode === 'publish' && isset($args['pos'][1])) setPublish((string)$args['pos'][1]);
elseif ($mode === 'note') addNote($opt);
else {
    fwrite(STDERR, 'Usage: php tools/release.php build vX.Y.Z [--from=..] [--min=..] [--expires=45] [--max=<MB>] [--security] | publish vX.Y.Z | publish --note=<id>'
        ." | publish --keys=<kseq> | note --level ..\n");
    exit(1);
}
