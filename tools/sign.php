<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# The "go" for a release: signs on the owner's machine what tools/release.php built, with a key file that only a password opens
# Usage: php tools/sign.php 8.0.1 - signs manifest-beta.json and manifest-stable.json of storage/release/8.0.1/
# Usage: php tools/sign.php --refresh 8.0.1 - signs the served manifests again with a new time, so they do not expire
# Usage: php tools/sign.php --note 1760000000 - signs a note that tools/release.php note wrote
# Usage: php tools/sign.php --keys new primary|recovery <file> [--seed] - creates a key file; --seed takes the 32 bytes from paper instead
# Usage: php tools/sign.php --keys change [--primary=<file>] [--recovery=<file>] --with=<recovery key> [--key=<primary key>]
# Options: --key=<file> names the primary key file; without it SLAED_KEY of tools/.env does
# The private keys never leave the owner's machine: no repository, no server and no CI holds one, and the real sodium extension is required here

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

# Ask one line of the console; a hidden line does not echo on a terminal, and a pipe gives its next line either way
function getInputLine(string $label, bool $hide = false): string {
    fwrite(STDOUT, $label);
    if (!$hide || !stream_isatty(STDIN)) return rtrim((string)fgets(STDIN), "\r\n");
    if (PHP_OS_FAMILY === 'Windows') {
        $code = '$p = Read-Host -AsSecureString; [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p))';
        $proc = proc_open(['powershell', '-NoProfile', '-Command', $code], [0 => STDIN, 1 => ['pipe', 'w'], 2 => STDERR], $pipes);
        if (!is_resource($proc)) setFailExit('the console cannot hide a password');
        $line = (string)stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($proc);
        return rtrim($line, "\r\n");
    }
    system('stty -echo');
    $line = rtrim((string)fgets(STDIN), "\r\n");
    system('stty echo');
    fwrite(STDOUT, "\n");
    return $line;
}

# A fingerprint as the owner reads it aloud and compares with the README: the SHA-256 in groups of four
function getPrintText(string $key): string {
    return trim(chunk_split(Update::getKeyPrint($key), 4, ' '));
}

# The key pair of a key file of the given role, opened by its password; the file holds the seed in a secretbox under an Argon2id key
function getKeyPair(string $file, string $role): array {
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($data) || ($data['type'] ?? '') !== 'slaed-key') setFailExit('no key file at '.$file);
    if (($data['role'] ?? '') !== $role) setFailExit($file.' is the '.($data['role'] ?? '?').' key, not the '.$role.' key');
    $salt = Update::getRawBytes($data['salt'] ?? '', SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $nonce = Update::getRawBytes($data['nonce'] ?? '', SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $box = Update::getRawBytes($data['box'] ?? '', SODIUM_CRYPTO_SIGN_SEEDBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES);
    $ops = intval($data['ops'] ?? 0);
    $mem = intval($data['mem'] ?? 0);
    if ($salt === '' || $nonce === '' || $box === '' || $ops < SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE || $mem < SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE) {
        setFailExit($file.' is damaged');
    }
    $pass = getInputLine('Password of the '.$role.' key: ', true);
    $kek = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $pass, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    $seed = sodium_crypto_secretbox_open($box, $nonce, $kek);
    sodium_memzero($pass);
    sodium_memzero($kek);
    if (!is_string($seed) || strlen($seed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) setFailExit('wrong password for '.$file);
    $pair = sodium_crypto_sign_seed_keypair($seed);
    sodium_memzero($seed);
    $pub = sodium_crypto_sign_publickey($pair);
    if (base64_encode($pub) !== ($data['pub'] ?? '')) setFailExit($file.' does not hold the key it names');
    return ['pub' => $pub, 'sec' => sodium_crypto_sign_secretkey($pair)];
}

# The keys in force: the factory keys of the core after every key statement the owner made, verified as a site verifies them
function getKeyState(): array {
    $keys = ['kseq' => 0, 'primary' => Update::PRIMARY, 'recovery' => Update::RECOVERY, 'revoked' => []];
    $res = Update::getKeyChain($keys, Update::getKeyList(RELEASE_DIR.'/keys'));
    if (!$res['ok']) setFailExit('a key statement of storage/release/keys/ fails: '.$res['error']);
    return $res['keys'];
}

# Write a file whole or stop; nothing here may be left half written
function setFileText(string $file, string $text): void {
    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0755, true) && !is_dir(dirname($file))) setFailExit('cannot create '.dirname($file));
    if (file_put_contents($file, $text, LOCK_EX) !== strlen($text)) setFailExit('cannot write '.$file);
}

# Sign the two manifests of a version, or sign them again on refresh: a new time, the same lifetime and the key id of the primary key in force
function setManifestSign(string $ver, bool $again, string $kfile): void {
    $dir = RELEASE_DIR.'/'.$ver;
    $names = ['manifest-beta.json', 'manifest-stable.json'];
    foreach ($names as $name) if (!is_file($dir.'/'.$name)) setFailExit('no '.$name.' in '.$dir.', run tools/release.php build v'.$ver);
    $done = is_file($dir.'/'.$names[0].'.sig') || is_file($dir.'/'.$names[1].'.sig');
    if ($done && !$again) setFailExit('the manifests of '.$ver.' are signed already; --refresh signs them again');
    if (!$done && $again) setFailExit('the manifests of '.$ver.' were never signed, so there is nothing to refresh');
    $data = json_decode((string)file_get_contents($dir.'/'.$names[1]), true);
    if (!is_array($data) || ($data['version'] ?? '') !== $ver) setFailExit('manifest-stable.json of '.$dir.' does not name '.$ver);
    $full = $dir.'/slaed-'.$ver.'.zip';
    if (is_file($full) && hash_file('sha256', $full) !== $data['full']['sha256']) setFailExit(basename($full).' is not the archive the manifest names');
    fwrite(STDOUT, ($again ? 'Refresh' : 'Sign').' release '.$ver."\n  commit   ".$data['commit']."\n  full     ".$data['full']['sha256'].' '.$data['full']['size']." bytes\n"
        .(is_array($data['delta']) ? '  delta    '.$data['delta']['sha256'].' from '.$data['delta']['from']."\n" : '')
        .'  min '.$data['min'].', php '.$data['php'].', security '.($data['security'] ? 'yes' : 'no')."\n");
    if (getInputLine('Is this the release you give your go for? [y/N] ') !== 'y') setFailExit('no go');
    $pair = getKeyPair($kfile, 'primary');
    if (base64_encode($pair['pub']) !== getKeyState()['primary']) setFailExit($kfile.' is not the primary key in force');
    $now = time();
    $life = 0;
    foreach ($names as $name) {
        $data = json_decode((string)file_get_contents($dir.'/'.$name), true);
        $life = intval($data['expires']) - intval($data['time']);
        $data = array_merge($data, ['time' => $now, 'expires' => $now + $life, 'kid' => substr(Update::getKeyPrint($pair['pub']), 0, 16)]);
        $body = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
        $sig = sodium_crypto_sign_detached(Update::MANIFEST.$body, $pair['sec']);
        if (!Update::isSignValid(Update::MANIFEST.$body, $sig, $pair['pub'])) setFailExit('the signature of '.$name.' does not verify');
        setFileText($dir.'/'.$name, $body);
        setFileText($dir.'/'.$name.'.sig', base64_encode($sig));
    }
    sodium_memzero($pair['sec']);
    fwrite(STDOUT, 'Signed until '.gmdate('Y-m-d', $now + $life).'; next: php tools/release.php publish v'.$ver."\n");
}

# Sign a note as it was written, under the primary key in force
function setNoteSign(string $id, string $kfile): void {
    $file = RELEASE_DIR.'/notes/'.$id.'.json';
    if (!ctype_digit($id) || !is_file($file)) setFailExit('no note '.$id.' in '.RELEASE_DIR.'/notes');
    $body = (string)file_get_contents($file);
    fwrite(STDOUT, $body);
    if (getInputLine('Sign this note? [y/N] ') !== 'y') setFailExit('no go');
    $pair = getKeyPair($kfile, 'primary');
    if (base64_encode($pair['pub']) !== getKeyState()['primary']) setFailExit($kfile.' is not the primary key in force');
    setFileText($file.'.sig', base64_encode(sodium_crypto_sign_detached(Update::NOTE.$body, $pair['sec'])));
    sodium_memzero($pair['sec']);
    fwrite(STDOUT, 'Signed; next: php tools/release.php publish --note='.$id."\n");
}

# Create a key file of one role under a password asked twice; a new recovery key is also shown once as its seed for paper
function addKeyFile(string $role, string $file, bool $paper): void {
    if (!in_array($role, ['primary', 'recovery'], true)) setFailExit('the role is primary or recovery');
    if ($file === '' || file_exists($file)) setFailExit('name a key file that does not exist yet');
    if ($paper) {
        $hex = (string)preg_replace('#\s+#', '', getInputLine('Seed from paper, 64 hex digits: ', true));
        if (preg_match('#^[0-9a-fA-F]{64}$#D', $hex) !== 1) setFailExit('the seed is 64 hex digits');
        $seed = (string)hex2bin($hex);
    } else {
        $seed = random_bytes(SODIUM_CRYPTO_SIGN_SEEDBYTES);
    }
    $pass = getInputLine('New password: ', true);
    if (strlen($pass) < 10) setFailExit('the password has at least 10 characters');
    if (getInputLine('Repeat it: ', true) !== $pass) setFailExit('the passwords differ');
    $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    [$ops, $mem] = [SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE];
    $kek = sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $pass, $salt, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    $pub = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair($seed));
    $data = ['type' => 'slaed-key', 'role' => $role, 'pub' => base64_encode($pub), 'salt' => base64_encode($salt), 'ops' => $ops, 'mem' => $mem,
        'nonce' => base64_encode($nonce), 'box' => base64_encode(sodium_crypto_secretbox($seed, $nonce, $kek))];
    setFileText($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    fwrite(STDOUT, ucfirst($role).' key '.$file."\n  public      ".$data['pub']."\n  fingerprint ".getPrintText($pub)."\n");
    if ($role === 'recovery' && !$paper) fwrite(STDOUT, "  seed for paper, shown once:\n  ".trim(chunk_split(bin2hex($seed), 8, ' '))."\n");
    sodium_memzero($seed);
    sodium_memzero($pass);
    sodium_memzero($kek);
}

# Write a key statement with the next kseq: signed by the recovery key in force, and by the primary in force as well when the recovery key is replaced
function addKeyChange(array $opt): void {
    $keys = getKeyState();
    $pubs = ['primary' => $keys['primary'], 'recovery' => $keys['recovery']];
    foreach (['primary', 'recovery'] as $role) {
        if (($opt[$role] ?? '') === '') continue;
        $data = json_decode((string)file_get_contents((string)$opt[$role]), true);
        if (!is_array($data) || ($data['role'] ?? '') !== $role || Update::getRawBytes($data['pub'] ?? '', 32) === '') setFailExit($opt[$role].' is no '.$role.' key file');
        $pubs[$role] = $data['pub'];
    }
    if ($pubs['primary'] === $keys['primary'] && $pubs['recovery'] === $keys['recovery']) setFailExit('name a new --primary or --recovery key file');
    $body = json_encode(['type' => 'keychange', 'kseq' => $keys['kseq'] + 1, 'primary' => $pubs['primary'], 'recovery' => $pubs['recovery'], 'time' => time()],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    fwrite(STDOUT, $body);
    if (getInputLine('Sign this key statement? [y/N] ') !== 'y') setFailExit('no go');
    $rec = getKeyPair((string)($opt['with'] ?? ''), 'recovery');
    if (base64_encode($rec['pub']) !== $keys['recovery']) setFailExit('--with is not the recovery key in force');
    $item = ['body' => $body, 'sig' => base64_encode(sodium_crypto_sign_detached(Update::KEYCHANGE.$body, $rec['sec'])), 'psig' => ''];
    sodium_memzero($rec['sec']);
    if ($pubs['recovery'] !== $keys['recovery']) {
        $pri = getKeyPair((string)($opt['key'] ?? getEnvValue('SLAED_KEY')), 'primary');
        if (base64_encode($pri['pub']) !== $keys['primary']) setFailExit('--key is not the primary key in force');
        $item['psig'] = base64_encode(sodium_crypto_sign_detached(Update::KEYCHANGE.$body, $pri['sec']));
        sodium_memzero($pri['sec']);
    }
    $res = Update::getKeyChain($keys, [$item]);
    if (!$res['ok']) setFailExit('the statement does not verify: '.$res['error']);
    $base = RELEASE_DIR.'/keys/'.$res['keys']['kseq'];
    setFileText($base.'.json', $body);
    setFileText($base.'.sig', $item['sig']);
    if ($item['psig'] !== '') setFileText($base.'.psig', $item['psig']);
    fwrite(STDOUT, 'Key statement '.$res['keys']['kseq'].' written; next: php tools/release.php publish --keys='.$res['keys']['kseq']."\n");
}

if (!extension_loaded('sodium')) setFailExit('signing needs the extension sodium, see .rules/global.md "Toolchain"');
$args = getArgs($argv, ['refresh', 'seed']);
$opt = $args['opt'];
$kfile = (string)($opt['key'] ?? getEnvValue('SLAED_KEY'));
if (isset($opt['keys'])) {
    if ($opt['keys'] === 'new') addKeyFile((string)($args['pos'][0] ?? ''), (string)($args['pos'][1] ?? ''), isset($opt['seed']));
    elseif ($opt['keys'] === 'change') addKeyChange($opt);
    else setFailExit('--keys takes new or change');
} elseif (isset($opt['note'])) {
    setNoteSign((string)$opt['note'], $kfile);
} elseif (Update::isVersion($args['pos'][0] ?? null)) {
    setManifestSign((string)$args['pos'][0], isset($opt['refresh']), $kfile);
} else {
    fwrite(STDERR, "Usage: php tools/sign.php <X.Y.Z> | --refresh <X.Y.Z> | --note <id> | --keys new <role> <file> | --keys change ...\n");
    exit(1);
}
