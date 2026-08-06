<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

# Report and repair the comment counter of every comment target.
# The `comments` column of the eight target tables is denormalised: the frontend, the cards and the
# chips read it without ever touching the comment table, so nothing notices when the two drift apart.
# The write path keeps it in step from 6.3 on, but rows written under the earlier request-supplied
# module could move the counter of the wrong target, so an installation upgraded from before that
# fix carries historical drift. This finds it and writes the live number back.
#
#   php tools/comment-recount.php report                  # read-only, prints every target that disagrees
#   php tools/comment-recount.php fix                     # writes the live count back into those targets
#
# Options: --mod=news  --limit=50
#
# The live number is the public one: published, not deleted. It is recomputed inside the UPDATE rather
# than carried over from the report, so a comment written between the two cannot be lost.
# The same work runs unattended as the `commentsync` scheduler job; this tool is the manual way in.
#
# There is no --db= here: this tool boots the core so the module map,
# the visibility rules and the counter semantics have one home, and the core reads config/db.php. To
# rehearse against a restored copy, point that file at the copy.

if (PHP_SAPI !== 'cli') die('CLI only');

error_reporting(E_ALL);
ini_set('display_errors', '1');
define('MODULE_FILE', true);
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));

# Read one command line option, falling back to the given default
function getOption(array $args, string $name, string $def): string {
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--'.$name.'=')) return substr($arg, strlen($name) + 3);
    }
    return $def;
}

$mode = $argv[1] ?? '';
if (!in_array($mode, ['report', 'fix'], true)) {
    fwrite(STDERR, "Usage: php tools/comment-recount.php report|fix [--mod=news] [--limit=50]\n");
    exit(1);
}

$only = preg_replace('/[^a-z0-9_]/', '', strtolower(getOption($argv, 'mod', '')));
$show = max(1, (int)getOption($argv, 'limit', '50'));

# The core is booted rather than reimplemented, because the module-to-table map, the visibility rules
# and the counter semantics all live in the Comment class and must not exist a second time here
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
ob_start();
require BASE_DIR.'/core/system.php';
ob_end_clean();

if (!$com instanceof Comment) {
    fwrite(STDERR, "The comment subsystem is unavailable\n");
    exit(1);
}

$drift = $com->getCountDrift($only);
if (!$drift) {
    echo 'report: every comment counter agrees with its target', PHP_EOL;
    exit(0);
}

$mods = [];
foreach ($drift as $one) $mods[$one['modul']] = ($mods[$one['modul']] ?? 0) + 1;
ksort($mods);

echo str_pad('module', 10), str_pad('target', 10), str_pad('column', 10), 'live', PHP_EOL;
foreach (array_slice($drift, 0, $show) as $one) {
    echo str_pad($one['modul'], 10), str_pad((string)$one['cid'], 10), str_pad((string)$one['col'], 10), $one['live'], PHP_EOL;
}
if (count($drift) > $show) echo '... and ', count($drift) - $show, ' more', PHP_EOL;

$list = [];
foreach ($mods as $mod => $num) $list[] = $mod.' '.$num;
echo 'drifted: ', count($drift), ' targets (', implode(', ', $list), ')', PHP_EOL;

if ($mode === 'report') {
    echo 'report: nothing was written, run fix to repair', PHP_EOL;
    exit(0);
}

$done = $com->updateCountDrift($drift);
$left = $com->getCountDrift($only);
echo 'fix: ', $done, ' targets written, ', count($left), ' still disagree', PHP_EOL;
exit(count($left) === 0 ? 0 : 1);
