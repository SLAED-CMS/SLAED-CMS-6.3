<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Compares the IPv6 prefix list of core/classes/upload.php against the IANA global unicast registry and reports what the two disagree about
# The class only visits addresses inside a prefix IANA has delegated to a regional registry, so a prefix delegated after this installation was built is refused until it is added
# That is deliberate - an unknown prefix is refused rather than trusted - but it has to be visible, which is what this tool is for
#
# Usage: php tools/ipv6-registry-check.php [report|json]
# Exit code: 0 when the list matches the registry, 1 when it does not, 2 when the registry could not be read
# No credentials, no arguments, nothing written: one HTTPS GET and a comparison
#
# Run it by hand after a release, or from the scheduler; a difference is one line to add to Upload::ALLOWSIX, and the registry moves about once every five years

if (PHP_SAPI !== 'cli') die('Illegal file access');

define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
define('FUNC_FILE', true);
define('SOURCE', 'https://www.iana.org/assignments/ipv6-unicast-address-assignments/ipv6-unicast-address-assignments.csv');
define('KEEPERS', ['APNIC', 'ARIN', 'RIPE NCC', 'LACNIC', 'AFRINIC']);

require_once BASE_DIR.'/core/classes/upload.php';

# Fetch the registry over HTTPS with verification kept, because a list that decides where the server may connect must not be read over a connection nobody checked
function getRegistryBody(): string {
    if (!function_exists('curl_init')) return '';
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => SOURCE,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_PROTOCOLS_STR => 'https',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'SLAED ipv6 registry check',
    ]);
    $body = curl_exec($curl);
    $code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    return ($body !== false && $code === 200) ? (string)$body : '';
}

# Return every prefix the registry hands to a regional registry, which is exactly the set the class is allowed to hold
# Anything designated to IANA itself, to documentation or to 6to4 is left out: those are assigned but are never a legitimate fetch target
function getRegistryNets(string $body): array {
    $out = [];
    foreach (preg_split('/\R/', $body) ?: [] as $line) {
        $cell = str_getcsv(trim($line), ',', '"', '\\');
        if (count($cell) < 2) continue;
        $net = trim((string)$cell[0]);
        $who = trim((string)$cell[1]);
        if ($net === '' || !str_contains($net, '/')) continue;
        if (!in_array(strtoupper($who), array_map('strtoupper', KEEPERS), true)) continue;
        $out[] = $net;
    }
    sort($out);
    return $out;
}

# Normalize one prefix so two spellings of the same block compare equal, because the registry and a hand written constant need not agree on zero compression
function getNetKey(string $net): string {
    [$base, $bits] = array_pad(explode('/', $net, 2), 2, '');
    $pack = inet_pton(trim($base));
    if ($pack === false) return strtolower(trim($net));
    return strtolower((string)inet_ntop($pack)).'/'.(int)$bits;
}

# Compare the two lists by normalized prefix and report both directions of the difference
function getListDiff(array $have, array $want): array {
    $hkey = [];
    $wkey = [];
    foreach ($have as $one) $hkey[getNetKey($one)] = $one;
    foreach ($want as $one) $wkey[getNetKey($one)] = $one;
    return [
        'missing' => array_values(array_diff_key($wkey, $hkey)),
        'extra' => array_values(array_diff_key($hkey, $wkey)),
    ];
}

$mode = strtolower((string)($argv[1] ?? 'report'));
$body = getRegistryBody();
if ($body === '') {
    fwrite(STDERR, 'the IANA registry could not be read from '.SOURCE."\n");
    exit(2);
}
$want = getRegistryNets($body);
if ($want === []) {
    fwrite(STDERR, "the IANA registry was read but held no registry allocation, so its format has changed\n");
    exit(2);
}
$have = Upload::getPublicNets();
$diff = getListDiff($have, $want);
$same = $diff['missing'] === [] && $diff['extra'] === [];

if ($mode === 'json') {
    echo json_encode(['ok' => $same, 'shipped' => count($have), 'registry' => count($want)] + $diff, JSON_UNESCAPED_SLASHES), "\n";
    exit($same ? 0 : 1);
}

echo 'registry : ', SOURCE, "\n";
echo 'shipped  : ', count($have), " prefixes in Upload::ALLOWSIX\n";
echo 'registry : ', count($want), " prefixes delegated to a regional registry\n\n";
if ($same) {
    echo "the shipped list matches the registry\n";
    exit(0);
}
foreach ($diff['missing'] as $one) echo '  MISSING  ', $one, " - delegated by IANA and refused by this build; add it to Upload::ALLOWSIX\n";
foreach ($diff['extra'] as $one) echo '  EXTRA    ', $one, " - held by this build and no longer a registry allocation; remove it from Upload::ALLOWSIX\n";
echo "\n", count($diff['missing']), ' missing, ', count($diff['extra']), " extra\n";
exit(1);
