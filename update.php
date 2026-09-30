<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

define('MODULE_FILE', true);
$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', __DIR__));
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/filemanager.php';
Cache::setHeaders(false);
if (!isAdmin(true)) setExit(_ACCESSDENIED);

# The removed modules the migration carries into Node: target type, an extension key replacing the one of the profile, the columns of rating, home, poll and pin
function getMigrateMap(): array {
    return [
        'news' => ['type' => 'news', 'ext' => null, 'rate' => 'ratings', 'home' => true, 'poll' => true, 'pin' => true],
        'pages' => ['type' => 'docs', 'ext' => null, 'rate' => 'ratings', 'home' => true, 'poll' => false, 'pin' => false],
        'faq' => ['type' => 'faq', 'ext' => null, 'rate' => 'ratings', 'home' => true, 'poll' => false, 'pin' => false],
        'help' => ['type' => 'help', 'ext' => null, 'rate' => 'ratings', 'home' => false, 'poll' => false, 'pin' => false],
        'links' => ['type' => 'links', 'ext' => null, 'rate' => 'votes', 'home' => true, 'poll' => false, 'pin' => false],
        'files' => ['type' => 'files', 'ext' => null, 'rate' => 'votes', 'home' => true, 'poll' => false, 'pin' => false],
        'content' => ['type' => 'content', 'ext' => '', 'rate' => '', 'home' => false, 'poll' => false, 'pin' => false],
    ];
}

# The working directory of the migration under the backup root, which the web server never serves: the manifest and the files of the closed module directories
function getMigrateDir(): string {
    return BACKUP_DIR.'/update/node';
}

# Read the manifest of the migration, or the empty one of a first run
function getMigrateState(): array {
    $file = getMigrateDir().'/manifest.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $base = ['version' => 1, 'stash' => [], 'types' => [], 'data' => [], 'files' => [], 'active' => [], 'notes' => [], 'counter' => 0];
    return is_array($data) ? $data + $base : $base;
}

# Write the manifest through a temporary file and a rename, so a broken run never leaves half a manifest behind
function setMigrateState(array $state): void {
    $dir = getMigrateDir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('The migration directory cannot be created');
    $tmp = $dir.'/manifest.'.getmypid().'.tmp';
    $text = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($text === false || file_put_contents($tmp, $text) !== strlen($text) || !rename($tmp, $dir.'/manifest.json')) {
        throw new RuntimeException('The migration manifest cannot be written');
    }
}

# Keep one note of a module for the report; the manifest holds at most two hundred per module, so a large damaged table cannot swell it
function addMigrateNote(array &$state, string $mod, string $text): void {
    $state['notes'][$mod] ??= [];
    if (count($state['notes'][$mod]) < 200) $state['notes'][$mod][] = $text;
}

# Run one statement of the migration; a statement the server refused stops the run with the driver error
function getMigrateQuery(string $sql, array $pars = []): PDOStatement {
    global $db;
    $res = $db->getSqlQuery($sql, $pars);
    if ($res === false) throw new RuntimeException('A migration statement failed: '.substr($sql, 0, 120).' '.($db->laste?->getMessage() ?? ''));
    return $res;
}

# Count rows with one statement
function getMigrateCount(string $sql, array $pars = []): int {
    return intval(getMigrateQuery($sql, $pars)->fetchColumn());
}

# Whether a table of the site exists in the current database
function checkMigrateTable(string $name): bool {
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :name';
    return getMigrateCount($sql, ['name' => PREFIX_DB.'_'.$name]) === 1;
}

# Read one type through a reader of its own, which takes up a configuration this request has just written
function getMigrateType(string $name): ?NodeType {
    global $db, $fld;
    return (new NodeQuery($db, getNodeContext(), $fld))->getNodeType($name);
}

# The features a standard type must have switched on to show what a module left behind; a type of an extension keeps the fixed features of its extension
function getMigrateNeed(string $mod, array $item, array $one): array {
    $tab = PREFIX_DB.'_'.$mod.($mod === 'help' ? ' WHERE pid = 0' : '');
    $need = ['categories' => $one['categories'] > 0, 'comments' => $one['comments'] > 0, 'favorites' => $one['favorites'] > 0];
    $need['rating'] = $item['rate'] !== '' && getMigrateCount('SELECT COUNT(*) FROM '.$tab.($mod === 'help' ? ' AND ' : ' WHERE ').$item['rate'].' > 0') > 0;
    $need['home'] = $item['home'] && getMigrateCount('SELECT COUNT(*) FROM '.$tab.' WHERE ihome <> 0') > 0;
    $need['poll'] = $item['poll'] && getMigrateCount('SELECT COUNT(*) FROM '.$tab.' WHERE vote > 0') > 0;
    $need['pinned'] = $item['pin'] && getMigrateCount('SELECT COUNT(*) FROM '.$tab.' WHERE fix <> 0') > 0;
    return array_keys(array_filter($need));
}

# The first role of a type whose display mode is the given one, or an empty string
function getMigrateRole(?NodeType $type, string $mode): string {
    foreach ($type?->settings['assets'] ?? [] as $role => $def) if ($def['mode'] === $mode && $def['active']) return $role;
    return '';
}

# The plan of the migration: every module whose table remains with its target type, what it left behind, what the type must switch on and what blocks it
# A module whose data step is done is never blocked again, because its type now legitimately holds the migrated materials
function getMigratePlan(array $state): array {
    $out = [];
    foreach (getMigrateMap() as $mod => $item) {
        if (!checkMigrateTable($mod)) continue;
        $name = $item['type'];
        $type = getMigrateType($name);
        $pars = ['mod' => $mod, 'key' => '~'.$mod];
        $one = ['type' => $name, 'block' => '', 'rows' => getMigrateCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_'.$mod.($mod === 'help' ? ' WHERE pid = 0' : ''))];
        foreach (['comments' => '_comment', 'favorites' => '_favorites', 'categories' => '_categories'] as $key => $tab) {
            $one[$key] = getMigrateCount('SELECT COUNT(*) FROM '.PREFIX_DB.$tab.' WHERE modul IN (:mod, :key)', $pars);
        }
        $one['need'] = getMigrateNeed($mod, $item, $one);
        $nodes = $type ? getMigrateCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE tid = :id', ['id' => $type->id]) : 0;
        $done = ($state['data'][$mod]['state'] ?? '') === 'done';
        $role = ['links' => 'link', 'files' => 'download'][$mod] ?? '';
        if (is_dir(BASE_DIR.'/modules/'.$mod)) {
            $one['block'] = 'the module '.$mod.' is still installed';
        } elseif (!$done && $type && $name === $mod && $nodes > 0 && empty($state['stash'][$mod])) {
            $one['block'] = 'the type '.$name.' already holds materials, so the rows the old module keeps under the same key cannot be told apart';
        } elseif (!$done && $type && $type->ext !== '' && $item['ext'] === '' && $nodes > 0) {
            $one['block'] = 'the type '.$name.' keeps the extension '.$type->ext.' while it holds materials';
        } elseif (!$done && $type && $role !== '' && getMigrateRole($type, $role) === '') {
            $one['block'] = 'the type '.$name.' has no active role of the mode '.$role;
        }
        $out[$mod] = $one;
    }
    return $out;
}

# List every file below a directory as relative paths with forward slashes
function getMigrateList(string $dir, string $pre = ''): array {
    $out = [];
    foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $one) {
        if ($one === '.' || $one === '..') continue;
        $path = $dir.'/'.$one;
        if (is_dir($path) && !is_link($path)) $out = array_merge($out, getMigrateList($path, $pre.$one.'/'));
        elseif (is_file($path)) $out[] = $pre.$one;
    }
    return $out;
}

# Move every entry of one directory into another, merging directories; a name of the rename map applies to the files of the root and of thumb/
# An entry whose target already holds the same bytes is dropped, one whose target differs stays where it is and is answered, and emptied directories are removed
function setMigrateMove(string $from, string $into, array $skip = [], array $names = [], string $rel = ''): array {
    $left = [];
    foreach (is_dir($from) ? (scandir($from) ?: []) : [] as $one) {
        if ($one === '.' || $one === '..' || ($rel === '' && in_array($one, $skip, true))) continue;
        $src = $from.'/'.$one;
        $dst = $into.'/'.(($rel === '' || $rel === 'thumb/') ? ($names[$one] ?? $one) : $one);
        if (is_dir($src) && !is_link($src)) {
            if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
                $left[] = $rel.$one;
                continue;
            }
            $left = array_merge($left, setMigrateMove($src, $dst, [], $names, $rel.$one.'/'));
            if (count(scandir($src) ?: []) === 2) rmdir($src);
        } elseif (is_file($dst) && sha1_file($src) === sha1_file($dst)) {
            unlink($src);
        } elseif (file_exists($dst) || !rename($src, $dst)) {
            $left[] = $rel.$one;
        }
    }
    return $left;
}

# Take what every module left under its own key out of the way of the name check of a new type: its categories, comments and favorites move to the key ~<module>
# The files of its upload directory move into the working directory, the guard files of the directory stay; a repeat finds nothing left to move
function setMigrateStash(array $plan, array &$state): void {
    global $db;
    $todo = array_keys(array_filter($plan, fn(array $v): bool => $v['block'] === ''));
    $todo = array_values(array_filter($todo, fn(string $v): bool => empty($state['stash'][$v])));
    if (!$todo) return;
    $guard = Cache::getWriteGuard();
    if ($guard === false || !$db->setSqlBegin()) throw new RuntimeException('The transaction of the stash step cannot be started');
    try {
        foreach ($todo as $mod) {
            foreach (['_categories', '_comment', '_favorites'] as $tab) {
                getMigrateQuery('UPDATE '.PREFIX_DB.$tab.' SET modul = :key WHERE modul = :mod', ['key' => '~'.$mod, 'mod' => $mod]);
            }
        }
        if (!$db->setSqlCommit()) throw new RuntimeException('The commit of the stash step failed');
    } catch (Throwable $err) {
        $db->setSqlRollback();
        Cache::deleteWriteGuard($guard);
        throw $err;
    }
    Cache::addEpoch(true);
    Cache::deleteWriteGuard($guard);
    $skip = array_keys(FileManager::getGuardFiles());
    foreach ($todo as $mod) {
        $dir = getMigrateDir().'/files/'.$mod;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('The stash directory of '.$mod.' cannot be created');
        $left = setMigrateMove(UPLOADS_DIR.'/'.$mod, $dir, $skip);
        if ($left) throw new RuntimeException('Files of uploads/'.$mod.' could not be moved aside: '.implode(', ', array_slice($left, 0, 10)));
        $state['stash'][$mod] = true;
        setMigrateState($state);
    }
}

# Create or adjust the target type of one module: a missing type comes from its shipped profile, an existing one only gains the features the data needs
# A new type takes over the upload rule the old module left under its name, which addNodeType() does for the nine replaced names
# The extension key of the map replaces the one of the profile or of an empty type; a type that must be changed while active is switched off first and on again at the end
function setMigrateType(string $mod, array $one, array &$state): void {
    $item = getMigrateMap()[$mod];
    $name = $item['type'];
    $type = getMigrateType($name);
    $svc = getNodeWriter();
    if ($type === null) {
        $file = BASE_DIR.'/modules/node/profiles/'.$name.'.json';
        $prof = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (!is_array($prof['type'] ?? null)) throw new RuntimeException('The profile of the type '.$name.' cannot be read');
        $def = $prof['type'];
        $ext = $item['ext'] ?? $def['ext'];
        $set = $def['settings'];
        if ($ext === '') foreach ($one['need'] as $key) $set['features'][$key] = true;
        $svc->addNodeType($name, new NodeTypeInput($def['title'], $def['intro'], $ext, $def['sort'], $set, $def['fields'], $def['uploads'], $def['rating']));
        $state['active'][$name] = true;
    } else {
        $ext = $item['ext'] ?? $type->ext;
        $set = $type->settings;
        if ($ext === '') foreach ($one['need'] as $key) $set['features'][$key] = true;
        if ($ext !== $type->ext) $set['ext'] = [];
        if ($set !== $type->settings || $ext !== $type->ext) {
            if ($ext !== $type->ext && $type->active) {
                $type = $svc->updateNodeTypeStatus($name, false, $type->version);
                $state['active'][$name] = true;
                setMigrateState($state);
            }
            $svc->updateNodeType($name, new NodeTypeInput($type->title, $type->intro, $ext, $type->sort, $set, $type->fields, $type->uploads, $type->rating), $type->version);
        }
    }
    $state['types'][$mod] = true;
    setMigrateState($state);
}

# Rewrite the file references of one text: attachment names the migration renames, and direct addresses of a closed module directory, which now point into the public archive
# Only a file the working directory holds is touched and collected by module into the archive list; every other reference stays exactly as it was
function getMigrateText(string $text, array $names, array $files, array &$arch): string {
    if ($names && stripos($text, '[attach=') !== false) {
        $text = preg_replace_callback('/\[attach=([a-zA-Z0-9_\-\. ]+) align=/', fn(array $m): string => '[attach='.($names[$m[1]] ?? $m[1]).' align=', $text) ?? $text;
    }
    if (!$files || stripos($text, 'uploads/') === false) return $text;
    $mods = implode('|', array_map(fn(string $v): string => preg_quote($v, '#'), array_keys($files)));
    return preg_replace_callback('#(?<![A-Za-z0-9_.-])uploads/('.$mods.')/([A-Za-z0-9_./%-]+)#', function (array $mat) use ($files, &$arch): string {
        $cut = rtrim($mat[2], '.');
        $rel = rawurldecode($cut);
        if (!isset($files[$mat[1]][$rel])) return $mat[0];
        $arch[$mat[1]][$rel] = true;
        return 'uploads/archive/'.$mat[1].'/'.$mat[2];
    }, $text) ?? $text;
}

# Wrap every block of raw HTML the conversion does not know - tables, divisions, frames, objects, forms, scripts and styles - into a [usehtml] block on its own lines
# A block is taken up to its balanced closing tag, an unclosed one up to the end of the text; each wrapped block leaves a marker the caller restores last
function getMigrateBlocks(string $text, array &$keep): string {
    $pos = 0;
    while (preg_match('#<(table|div|iframe|object|form|script|style)\b[^>]*>#i', $text, $hit, PREG_OFFSET_CAPTURE, $pos)) {
        $tag = strtolower($hit[1][0]);
        $start = $hit[0][1];
        $end = strlen($text);
        $depth = 0;
        preg_match_all('#<(/?)'.$tag.'\b[^>]*>#i', $text, $mm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE, $start);
        foreach ($mm as $one) {
            $depth += ($one[1][0] === '/') ? -1 : 1;
            if ($depth > 0) continue;
            $end = $one[0][1] + strlen($one[0][0]);
            break;
        }
        $keep[] = "\n\n[usehtml]\n".trim(substr($text, $start, $end - $start))."\n[/usehtml]\n\n";
        $mark = "\x01".(count($keep) - 1)."\x01";
        $text = substr($text, 0, $start).$mark.substr($text, $end);
        $pos = $start + strlen($mark);
    }
    return $text;
}

# Convert the raw HTML of a legacy text into the BB and Markdown a Node text renders: breaks, emphasis, colour, links, images, code, lists, headings, paragraphs and rules
# Code, [usehtml] and [usephp] regions stay exactly as written; unknown blocks become [usehtml] blocks in a trusted text and plain text in a comment
# Entities are decoded last, so the text shows the characters the old trusted rendering showed and a decoded tag stays visible text
function getMigrateHtml(string $text, bool $trust): string {
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $keep = [];
    $text = preg_replace_callback('#\[(code|php|usehtml|usephp)\b[^\]]*\].*?\[/\1\]#si', function (array $mat) use (&$keep): string {
        $raw = in_array(strtolower($mat[1]), ['usehtml', 'usephp'], true);
        $keep[] = $raw ? $mat[0] : (preg_replace('#<br\s*/?>[ \t]*\n?#i', "\n", $mat[0]) ?? $mat[0]);
        return "\x01".(count($keep) - 1)."\x01";
    }, $text) ?? $text;
    $text = preg_replace('/^[ \t]+/m', '', $text) ?? $text;
    if ($trust) $text = getMigrateBlocks($text, $keep);
    $text = preg_replace(['#<!--.*?-->#s', '#<br\s*/?>[ \t]*\n?#i'], ['', "  \n"], $text) ?? $text;
    do {
        $text = preg_replace_callback('#<font\b([^>]*)>((?:(?!<font\b).)*?)</font>#si', function (array $mat): string {
            $color = preg_match('/color\s*=\s*["\']?(#?[a-z0-9]+)/i', $mat[1], $hit) ? $hit[1] : '';
            return ($color !== '') ? '[color='.$color.']'.$mat[2].'[/color]' : $mat[2];
        }, $text, -1, $num) ?? $text;
    } while ($num > 0);
    $text = preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#si', function (array $mat): string {
        $href = preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $mat[1], $hit) ? trim(html_entity_decode($hit[1] ?: ($hit[2] ?? '') ?: ($hit[3] ?? ''))) : '';
        $label = trim($mat[2]);
        if ($href === '' || preg_match('/^(?:javascript|vbscript|data):/i', $href)) return $label;
        return ($label === '' || $label === $href) ? '[url]'.$href.'[/url]' : '[url='.$href.']'.$label.'[/url]';
    }, $text) ?? $text;
    $text = preg_replace_callback('#<img\b([^>]*)>#i', function (array $mat): string {
        $src = preg_match('/src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $mat[1], $hit) ? trim($hit[1] ?: ($hit[2] ?? '') ?: ($hit[3] ?? '')) : '';
        return ($src !== '') ? '[img]'.html_entity_decode($src).'[/img]' : '';
    }, $text) ?? $text;
    $text = preg_replace_callback('#<(code|tt|kbd)\b[^>]*>(.*?)</\1>#si', fn(array $m): string => preg_match('/[`\n]/', $m[2]) ? $m[2] : '`'.$m[2].'`', $text) ?? $text;
    $pairs = ['b|strong' => 'b', 'i|em' => 'i', 'u|ins' => 'u', 's|strike|del' => 's'];
    foreach ($pairs as $tags => $bb) $text = preg_replace(['#<(?:'.$tags.')\b[^>]*>#i', '#</(?:'.$tags.')>#i'], ['['.$bb.']', '[/'.$bb.']'], $text) ?? $text;
    do {
        $text = preg_replace_callback('#<(ul|ol)\b[^>]*>((?:(?!<(?:ul|ol)\b).)*?)</\1>#si', function (array $mat): string {
            $items = preg_split('#<li\b[^>]*>#i', $mat[2]) ?: [];
            array_shift($items);
            $out = [];
            foreach ($items as $i => $one) {
                $lines = array_map('ltrim', explode("\n", trim(str_ireplace('</li>', '', $one))));
                $out[] = ((strtolower($mat[1]) === 'ol') ? ($i + 1).'. ' : '- ').implode("\n   ", $lines);
            }
            return "\n\n".implode("\n", $out)."\n\n";
        }, $text, -1, $num) ?? $text;
    } while ($num > 0);
    $text = preg_replace_callback('#<h([1-6])\b[^>]*>(.*?)</h\1>#si',
        fn(array $m): string => "\n\n".str_repeat('#', intval($m[1])).' '.trim(preg_replace('/\s+/', ' ', $m[2]) ?? '')."\n\n", $text) ?? $text;
    $text = preg_replace_callback('#<p\b[^>]*align\s*=\s*["\']?center[^>]*>(.*?)</p>#si', fn(array $m): string => "\n\n[center]".trim($m[1])."[/center]\n\n", $text) ?? $text;
    $from = ['#<center\b[^>]*>#i', '#</center>#i', '#<blockquote\b[^>]*>#i', '#</blockquote>#i', '#<hr\b[^>]*>#i', '#</?p\b[^>]*>#i', '#</?[a-z][a-z0-9]*\b[^>]*>#i'];
    $text = preg_replace($from, ['[center]', '[/center]', "\n[quote]", "[/quote]\n", "\n[hr]\n", "\n\n", ''], $text) ?? $text;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace(['/\n[ \t]+\n/', '/\n{3,}/', '/^(?=[ \t]*([-=*_])(?:[ \t]*\1){2,}[ \t]*$)/m'], ["\n\n", "\n\n", '\\\\'], $text) ?? $text;
    $text = preg_replace_callback("/\x01([0-9]+)\x01/", fn(array $m): string => $keep[intval($m[1])], $text) ?? $text;
    return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
}

# The new managed names of the attachments of one module whose stored names the file layer does not manage, so the controlled attach route can deliver them
# The readable stem is kept, the salt of the upload service is added; a name in use in the working directory or the type root is drawn again
function getMigrateNames(string $name, array $texts, array $files): array {
    $out = [];
    foreach ($texts as $text) {
        if (!preg_match_all('/\[attach=([a-zA-Z0-9_\-\. ]+) align=/', $text, $mm)) continue;
        foreach ($mm[1] as $one) {
            if (isset($out[$one]) || !isset($files[$one]) || FileManager::checkFileName($one)) continue;
            $stem = trim(preg_replace('/[^A-Za-z0-9_]+/', '_', pathinfo($one, PATHINFO_FILENAME)) ?? '', '_') ?: $name;
            $ext = preg_replace('/[^A-Za-z0-9]/', '', pathinfo($one, PATHINFO_EXTENSION)) ?: 'bin';
            do {
                $new = $stem.'-'.getRandomString(FileManager::SALTLEN).'.'.$ext;
            } while (isset($files[$new]) || in_array($new, $out, true) || file_exists(UPLOADS_DIR.'/'.$name.'/'.$new));
            $out[$one] = $new;
        }
    }
    return $out;
}

# The material a row of a removed module becomes, in the columns of the node table, plus the extra data the module carried in its own columns
# The status of help tells an open request from a closed one, which the queue row carries, so every request is published; elsewhere 0 is a submission not yet approved
function getMigrateNode(string $mod, array $row): array {
    $votes = in_array($mod, ['links', 'files'], true);
    $uid = intval($row['uid'] ?? 0);
    return [
        'id' => intval($row['id']), 'cid' => intval($row['cid'] ?? 0), 'uid' => $uid, 'aname' => $uid > 0 ? '' : mb_substr((string)($row['name'] ?? ''), 0, 25),
        'ip' => getIpNorm((string)($row['ip'] ?? '')) ?: '', 'title' => mb_substr(trim((string)($row['title'] ?? '')), 0, 100), 'intro' => (string)($row['intro'] ?? ''),
        'body' => (string)($row['body'] ?? ''), 'field' => (string)($row['field'] ?? ''), 'time' => (string)($row['time'] ?? ''), 'views' => intval($row['counter'] ?? 0),
        'home' => intval($row['ihome'] ?? 0), 'comon' => ($mod === 'help') ? CommentMode::Open->value : intval($row['acomm'] ?? 0), 'pinned' => intval($row['fix'] ?? 0),
        'poll' => intval($row['vote'] ?? 0), 'score' => intval($votes ? $row['tvotes'] : ($row['score'] ?? 0)),
        'ratings' => intval($votes ? $row['votes'] : ($row['ratings'] ?? 0)),
        'status' => ($mod === 'help') ? 1 : intval($row['status'] ?? 1), 'assoc' => (string)($row['assoc'] ?? ''), 'url' => trim((string)($row['url'] ?? '')),
        'size' => intval($row['filesize'] ?? 0),
        'release' => mb_substr(trim((string)($row['version'] ?? '')), 0, 100), 'site' => trim((string)($row['website'] ?? '')), 'hits' => intval($row['hits'] ?? 0),
    ];
}

# The resource a file or link row brings: an external address or a relative path below the type root, the facts of a local file read once from the working directory
# A local path the Node grammar refuses gets a safe spelling and its file the same one on the files step; an address Node refuses brings nothing and is noted
function getMigrateAsset(string $mod, array $node, string $role, array $files, array &$moves, array &$miss, array &$state, array &$srcs): ?array {
    $src = $node['url'];
    $old = $src;
    if ($src === '' || $role === '') return null;
    $link = (bool)preg_match('#^https?://#i', $src);
    if ($link) {
        $part = parse_url($src);
        if (preg_match('/[\x00-\x20\x7F]/', $src) || !is_array($part) || ($part['host'] ?? '') === '' || isset($part['user']) || isset($part['pass']) || strlen($src) > 2048) {
            addMigrateNote($state, $mod, '#'.$node['id'].': the address '.$src.' is refused by Node and was not carried');
            return null;
        }
        if ($mod === 'links' && isset($srcs[$src])) {
            addMigrateNote($state, $mod, '#'.$node['id'].': the address '.$src.' repeats #'.$srcs[$src].' and was not carried, a link role keeps each address once');
            return null;
        }
        $srcs[$src] = $node['id'];
    } else {
        $old = preg_replace('#^/?uploads/'.preg_quote($mod, '#').'/#', '', $src, 1, $hit) ?? '';
        $src = $moves[$old] ?? implode('/', array_map(fn(string $v): string => trim(preg_replace('/[^A-Za-z0-9_.-]+/', '_', $v) ?? '', '_'), explode('/', $old)));
        $good = $hit && preg_match('#^[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#D', $src) && !preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $src) && !str_starts_with($src, 'thumb/');
        if (!$good) {
            addMigrateNote($state, $mod, '#'.$node['id'].': the file '.$node['url'].' is outside uploads/'.$mod.' and was not carried');
            return null;
        }
        if ($src !== $old && !isset($moves[$old])) {
            $ext = pathinfo($src, PATHINFO_EXTENSION);
            $tail = ($ext !== '') ? '.'.$ext : '';
            $stem = substr($src, 0, strlen($src) - strlen($tail));
            while (isset($files[$src]) || in_array($src, $moves, true)) $src = $stem.'-'.getRandomString(4).$tail;
            if (isset($files[$old])) $moves[$old] = $src;
        }
    }
    $ext = strtolower(pathinfo((string)parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
    $kind = match (true) {
        $mod === 'links' => 'file',
        in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'], true) => 'image',
        in_array($ext, ['mp3', 'wav', 'ogg', 'oga', 'flac', 'm4a', 'aac', 'opus'], true) => 'audio',
        in_array($ext, ['mp4', 'webm', 'ogv', 'mov', 'm4v'], true) => 'video',
        default => 'file',
    };
    $full = $link ? '' : getMigrateDir().'/files/'.$mod.'/'.$old;
    $size = ($full !== '' && is_file($full)) ? filesize($full) : false;
    $mime = null;
    if ($size !== false && function_exists('finfo_open')) {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $info ? (finfo_file($info, $full) ?: null) : null;
    }
    $dims = ($size !== false && $kind === 'image') ? getimagesize($full) : false;
    if (!$link && $size === false) $miss[] = $node['url'];
    return [
        'kind' => $kind, 'role' => $role, 'src' => $src, 'name' => $link ? '' : mb_substr(basename($src), 0, 255), 'mime' => $mime,
        'size' => ($size !== false) ? $size : ($node['size'] > 0 ? $node['size'] : null), 'width' => $dims ? $dims[0] : null, 'height' => $dims ? $dims[1] : null,
        'hits' => $node['hits'], 'reported' => $node['status'] === 2,
    ];
}

# The extra field values a material keeps: the version and the site of a file row where its type defines them, each value checked by the shared field system on its own
function getMigrateFields(NodeType $type, array $node): array {
    global $fld;
    $out = [];
    foreach (['release' => $node['release'], 'site' => $node['site']] as $key => $val) {
        if ($val === '' || !isset($type->fields[$key])) continue;
        try {
            $out += $fld->filterFieldValues([$key => $type->fields[$key]], [$key => $val]);
        } catch (InvalidArgumentException) {
            continue;
        }
    }
    ksort($out);
    return $out;
}

# Insert the materials of one module and everything that belongs to each: the legacy address, extra categories, the resource, the queue row and the starting rating
# The texts are rewritten before the insert; the answer is the map of old ids to new ones and, by reference, the archive list and the notes
# Only a material the old module published gets a legacy address: an unapproved submission had no public page, and its first approval earns the award it never got
function addMigrateNodes(string $mod, NodeType $type, array $rows, array $names, array $files, array &$arch, array &$moves, array &$state): array {
    global $db;
    $now = (string)getMigrateQuery('SELECT NOW()')->fetchColumn();
    $cats = array_map('intval', getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = :name', ['name' => $type->name])->fetchAll(PDO::FETCH_COLUMN));
    $cats = array_flip($cats);
    $polls = array_flip(array_map('intval', getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_voting')->fetchAll(PDO::FETCH_COLUMN)));
    $feat = $type->settings['features'];
    $role = ['files' => getMigrateRole($type, 'download'), 'links' => getMigrateRole($type, 'link')][$mod] ?? '';
    $srcs = [];
    $sql = 'SELECT a.src FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE n.tid = :tid';
    foreach (getMigrateQuery($sql, ['tid' => $type->id])->fetchAll(PDO::FETCH_COLUMN) as $src) $srcs[$src] = 0;
    $ins = 'INSERT INTO '.PREFIX_DB.'_nodes (tid, cid, uid, aname, ip, title, intro, body, field, poll, home, comon, pinned, comnum, views, score, ratings, status, version,'
        .' created, updated, published, expires) VALUES (:tid, :cid, :uid, :aname, :ip, :title, :intro, :body, :field, :poll, :home, :comon, :pinned, 0, :views, :score,'
        .' :ratings, :status, 1, :created, :updated, :published, NULL)';
    $put = 'INSERT INTO '.PREFIX_DB.'_node_assets (nid, kind, role, src, name, title, intro, mime, size, width, height, duration, hits, reported, ruid, sort, created, updated)'
        .' VALUES (:nid, :kind, :role, :src, :name, \'\', \'\', :mime, :size, :width, :height, NULL, :hits, :rep, 0, 0, :created, :updated)';
    $rate = 'INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (:scope, :mid, :base, :votes, UNIX_TIMESTAMP())';
    $back = 'INSERT INTO '.PREFIX_DB.'_node_legacy (modul, oid, nid) VALUES (:modul, :oid, :nid)';
    $map = [];
    $miss = [];
    foreach ($rows as $row) {
        $node = getMigrateNode($mod, $row);
        $id = $node['id'];
        $time = (preg_match('/^[1-9]\d{3}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $node['time'])) ? $node['time'] : $now;
        if ($node['title'] === '') {
            $node['title'] = '#'.$id;
            addMigrateNote($state, $mod, '#'.$id.': the title was empty and became #'.$id);
        }
        if ($node['cid'] > 0 && (!$feat['categories'] || !isset($cats[$node['cid']]))) {
            addMigrateNote($state, $mod, '#'.$id.': the category '.$node['cid'].' is not a category of '.$type->name.', the material has none');
            $node['cid'] = 0;
        }
        if ($node['poll'] > 0 && (!$feat['poll'] || !isset($polls[$node['poll']]))) {
            addMigrateNote($state, $mod, '#'.$id.': the poll '.$node['poll'].' is gone or the type has no polls, the link was dropped');
            $node['poll'] = 0;
        }
        $vals = array_values(array_filter(explode('|', $node['field']), fn(string $v): bool => trim($v) !== '' && $v !== '0'));
        $body = getMigrateHtml(getMigrateText($node['body'], $names, $files, $arch), true).($vals ? "\n\n".implode(' | ', $vals) : '');
        $intro = getMigrateHtml(getMigrateText($node['intro'], $names, $files, $arch), true);
        if (strlen($intro) > 65535) {
            $intro = $node['intro'];
            addMigrateNote($state, $mod, '#'.$id.': the rewritten summary would not fit its column, it keeps the old addresses');
        }
        $fields = getMigrateFields($type, $node);
        $score = $node['score'];
        $num = $node['ratings'];
        if ($num > 0 && (!$feat['rating'] || $score < $num || $score > 5 * $num)) {
            addMigrateNote($state, $mod, '#'.$id.': the rating '.$score.'/'.$num.' is not carried');
            $score = $num = 0;
        } elseif ($num === 0) {
            $score = 0;
        }
        $pars = [
            'tid' => $type->id, 'cid' => $node['cid'], 'uid' => $node['uid'], 'aname' => $node['aname'], 'ip' => $node['ip'], 'title' => $node['title'], 'intro' => $intro,
            'body' => $body, 'field' => $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : '{}', 'poll' => $node['poll'],
            'home' => ($feat['home'] && $node['home']) ? 1 : 0, 'comon' => $node['comon'], 'pinned' => ($feat['pinned'] && $node['pinned']) ? 1 : 0, 'views' => $node['views'],
            'score' => $score, 'ratings' => $num, 'status' => ($node['status'] === 0 ? NodeStatus::Pending : NodeStatus::Published)->value, 'created' => $time,
            'updated' => $time, 'published' => $time,
        ];
        getMigrateQuery($ins, $pars);
        $nid = intval($db->getSqlLastId());
        if ($nid < 1) throw new RuntimeException('The new id of '.$mod.' #'.$id.' cannot be read');
        $map[$id] = $nid;
        if ($node['status'] !== 0) getMigrateQuery($back, ['modul' => $mod, 'oid' => $id, 'nid' => $nid]);
        $more =array_diff(array_unique(array_map('intval', explode(',', $node['assoc']))), [0, $node['cid']]);
        foreach ($feat['categories'] ? $more : [] as $cid) {
            if (isset($cats[$cid])) getMigrateQuery('INSERT INTO '.PREFIX_DB.'_node_categories (nid, cid) VALUES (:nid, :cid)', ['nid' => $nid, 'cid' => $cid]);
        }
        $one = getMigrateAsset($mod, $node, $role, $files[$mod] ?? [], $moves, $miss, $state, $srcs);
        if ($one !== null) {
            getMigrateQuery($put, ['nid' => $nid, 'kind' => $one['kind'], 'role' => $one['role'], 'src' => $one['src'], 'name' => $one['name'], 'mime' => $one['mime'],
                'size' => $one['size'], 'width' => $one['width'], 'height' => $one['height'], 'hits' => $one['hits'], 'rep' => $one['reported'] ? $now : null,
                'created' => $time, 'updated' => $time]);
        }
        if ($num > 0) getMigrateQuery($rate, ['scope' => 'node.'.$type->name, 'mid' => $nid, 'base' => $score, 'votes' => $num]);
        if ($mod === 'content' && $node['url'] !== '') addMigrateNote($state, $mod, '#'.$id.': the feed address '.$node['url'].' is not carried, the type has no sync extension');
    }
    if ($miss) addMigrateNote($state, $mod, count($miss).' files the rows name do not exist, their resources keep the address: '.implode(', ', array_slice($miss, 0, 10)));
    return $map;
}

# Carry the replies of the requests of the old help module into comments of their new materials, then fill the queue row of each request from its history
# A reply keeps its author, address and time; a closed request stays closed, an open one waits for the side that did not write last
function addMigrateReplies(NodeType $type, array $roots, array $map, array $names, array $files, array &$arch): void {
    global $conf;
    $maps = $conf['node']['support'];
    $rows = getMigrateQuery('SELECT id, pid, aid, title, time, body, ip FROM '.PREFIX_DB.'_help WHERE pid > 0 ORDER BY pid, time, id')->fetchAll(PDO::FETCH_ASSOC);
    $uids = array_values(array_unique(array_filter(array_map(fn(array $v): int => intval($v['aid']), $rows))));
    $users = [];
    foreach (array_chunk($uids, 500) as $part) {
        $pars = [];
        foreach ($part as $i => $uid) $pars['u'.$i] = $uid;
        $sql = 'SELECT id, name FROM '.PREFIX_DB.'_users WHERE id IN (:'.implode(', :', array_keys($pars)).')';
        foreach (getMigrateQuery($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $one) $users[intval($one['id'])] = $one['name'];
    }
    $last = [];
    $sql = 'INSERT INTO '.PREFIX_DB.'_comment (pid, cid, modul, time, uid, name, ip, body, status, shown)'
        .' VALUES (0, :cid, :modul, :time, :uid, :name, :ip, :body, :status, :shown)';
    foreach ($rows as $row) {
        $nid = $map[intval($row['pid'])] ?? 0;
        if ($nid < 1) continue;
        $aid = intval($row['aid']);
        $time = $row['time'] ?: date('Y-m-d H:i:s');
        $title = trim((string)$row['title']);
        $body = getMigrateHtml(getMigrateText(($title !== '' ? '[b]'.$title."[/b]\n\n" : '').$row['body'], $names, $files, $arch), false);
        getMigrateQuery($sql, ['cid' => $nid, 'modul' => $type->name, 'time' => $time, 'uid' => $aid, 'name' => mb_substr((string)($users[$aid] ?? ''), 0, 25),
            'ip' => getIpNorm((string)$row['ip']) ?: '', 'body' => $body, 'status' => CommentStatus::Published->value, 'shown' => $time]);
        $last[$nid] = ['aid' => $aid, 'time' => $time];
    }
    $sql = 'INSERT INTO '.PREFIX_DB.'_node_support (nid, aid, state, prio, version, activity) VALUES (:nid, 0, :state, :prio, 1, :activity)';
    foreach ($roots as $row) {
        $nid = $map[intval($row['id'])] ?? 0;
        if ($nid < 1) continue;
        $end = $last[$nid] ?? null;
        $stat = (intval($row['status']) === 1) ? $maps['state']['closed'] : (($end && $end['aid'] !== intval($row['uid'])) ? $maps['state']['author'] : $maps['state']['staff']);
        getMigrateQuery($sql, ['nid' => $nid, 'state' => $stat, 'prio' => $maps['prio']['normal'], 'activity' => $end['time'] ?? ($row['time'] ?: date('Y-m-d H:i:s'))]);
    }
}

# Bind the comments and favorites of the old module to the new materials; rows of a gone material take the key old<module>, which the Node remains list cleans up
# Comment bodies that point into a closed module directory are rewritten like the materials, and the terms of the old rating journal become the last votes of the new targets
function setMigrateLinks(string $mod, NodeType $type, array $map, array $files, array &$arch): void {
    $key = '~'.$mod;
    $sql = 'SELECT id, body FROM '.PREFIX_DB.'_comment WHERE modul = :key AND body LIKE :like';
    foreach (getMigrateQuery($sql, ['key' => $key, 'like' => '%uploads/%'])->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $body = getMigrateText($row['body'], [], $files, $arch);
        if ($body !== $row['body']) getMigrateQuery('UPDATE '.PREFIX_DB.'_comment SET body = :body WHERE id = :id', ['body' => $body, 'id' => $row['id']]);
    }
    foreach (['_comment' => 'cid', '_favorites' => 'fid'] as $tab => $col) {
        $sql = 'SELECT DISTINCT '.$col.' FROM '.PREFIX_DB.$tab.' WHERE modul = :key';
        foreach (getMigrateQuery($sql, ['key' => $key])->fetchAll(PDO::FETCH_COLUMN) as $old) {
            if (!isset($map[intval($old)])) continue;
            $sql = 'UPDATE '.PREFIX_DB.$tab.' SET modul = :name, '.$col.' = :nid WHERE modul = :key AND '.$col.' = :old';
            getMigrateQuery($sql, ['name' => $type->name, 'nid' => $map[intval($old)], 'key' => $key, 'old' => $old]);
        }
        getMigrateQuery('UPDATE '.PREFIX_DB.$tab.' SET modul = :name WHERE modul = :key', ['name' => 'old'.$mod, 'key' => $key]);
    }
    $terms = [];
    foreach (getMigrateQuery('SELECT mid, uid, ip, time FROM '.PREFIX_DB.'_rating WHERE modul = :mod', ['mod' => $mod])->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $nid = $map[intval($row['mid'])] ?? 0;
        $ip = getIpNorm((string)$row['ip']);
        $actor = (intval($row['uid']) > 0) ? 'u:'.intval($row['uid']) : (($ip && $ip !== '0.0.0.0' && $ip !== '::') ? 'g:'.$ip : '');
        if ($nid < 1 || $actor === '' || !preg_match('/^[0-9]{1,14}$/D', (string)$row['time'])) continue;
        $terms[$nid.' '.$actor] = max($terms[$nid.' '.$actor] ?? 0, intval($row['time']));
    }
    $sql = 'INSERT INTO '.PREFIX_DB.'_rating_actors (scope, mid, actor, last) VALUES (:scope, :mid, :actor, LEAST(:last, UNIX_TIMESTAMP()))'
        .' ON DUPLICATE KEY UPDATE last = GREATEST(last, VALUES(last))';
    foreach ($terms as $pair => $time) {
        [$nid, $actor] = explode(' ', $pair, 2);
        getMigrateQuery($sql, ['scope' => 'node.'.$type->name, 'mid' => intval($nid), 'actor' => $actor, 'last' => $time]);
    }
    if ($map) {
        $sql = 'UPDATE '.PREFIX_DB.'_nodes AS n SET n.comnum = (SELECT COUNT(*) FROM '.PREFIX_DB.'_comment AS c WHERE c.modul = :name AND c.cid = n.id AND c.status = :stat'
            .' AND c.deleted IS NULL) WHERE n.tid = :tid AND n.id BETWEEN :low AND :high';
        getMigrateQuery($sql, ['name' => $type->name, 'stat' => CommentStatus::Published->value, 'tid' => $type->id, 'low' => min($map), 'high' => max($map)]);
    }
    foreach (getMigrateQuery('SELECT id, modules FROM '.PREFIX_DB.'_admins')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $mods = getAdminModuleNames((string)$row['modules']);
        if (!in_array($mod, $mods, true)) continue;
        $keep = array_values(array_unique(array_merge(array_diff($mods, [$mod]), ['node-'.$type->name])));
        getMigrateQuery('UPDATE '.PREFIX_DB.'_admins SET modules = :mods WHERE id = :id', ['mods' => implode(',', $keep), 'id' => $row['id']]);
    }
}

# Carry the data of one module in one transaction under the lock of its type row and the page cache guard; the map is written to the manifest before the commit
# A repeat after a commit whose manifest was not finished recognizes the first new material, or with no material the emptied ~<module> key, and only finishes the manifest
function setMigrateData(string $mod, array $one, array &$state): void {
    global $db;
    $item = getMigrateMap()[$mod];
    $type = getMigrateType($item['type']) ?? throw new RuntimeException('The type '.$item['type'].' cannot be read');
    $was = $state['data'][$mod] ?? [];
    if (($was['state'] ?? '') === 'done') return;
    if (($was['state'] ?? '') === 'committing') {
        $left = 0;
        foreach (['_categories', '_comment', '_favorites'] as $tab) $left += getMigrateCount('SELECT COUNT(*) FROM '.PREFIX_DB.$tab.' WHERE modul = :key', ['key' => '~'.$mod]);
        $sql = 'SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE id = :id AND tid = :tid';
        if ($was['map'] ? getMigrateCount($sql, ['id' => reset($was['map']), 'tid' => $type->id]) === 1 : $left === 0) {
            $state['data'][$mod]['state'] = 'done';
            setMigrateState($state);
            return;
        }
    }
    $files = [];
    foreach (array_keys(getMigrateMap()) as $key) $files[$key] = array_fill_keys(getMigrateList(getMigrateDir().'/files/'.$key), true);
    $rows = getMigrateQuery('SELECT * FROM '.PREFIX_DB.'_'.$mod.($mod === 'help' ? ' WHERE pid = 0' : '').' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $texts = array_merge(array_column($rows, 'intro'), array_column($rows, 'body'));
    if ($mod === 'help') $texts = array_merge($texts, getMigrateQuery('SELECT body FROM '.PREFIX_DB.'_help WHERE pid > 0')->fetchAll(PDO::FETCH_COLUMN));
    $names = getMigrateNames($type->name, array_map('strval', $texts), $files[$mod]);
    $arch = [];
    $guard = Cache::getWriteGuard();
    if ($guard === false || !$db->setSqlBegin()) throw new RuntimeException('The transaction of '.$mod.' cannot be started');
    try {
        getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE', ['id' => $type->id]);
        getMigrateQuery('UPDATE '.PREFIX_DB.'_categories SET modul = :name WHERE modul = :key', ['name' => $type->name, 'key' => '~'.$mod]);
        $state['notes'][$mod] = [];
        $moves = [];
        $map = addMigrateNodes($mod, $type, $rows, $names, $files, $arch, $moves, $state);
        if ($mod === 'help') addMigrateReplies($type, $rows, $map, $names, $files, $arch);
        setMigrateLinks($mod, $type, $map, $files, $arch);
        $state['data'][$mod] = ['state' => 'committing', 'type' => $type->name, 'map' => $map, 'names' => $names, 'moves' => $moves, 'archive' => array_map('array_keys', $arch),
            'count' => array_intersect_key($one, array_flip(['rows', 'comments', 'favorites', 'categories']))];
        setMigrateState($state);
        if (!$db->setSqlCommit()) throw new RuntimeException('The commit of '.$mod.' failed');
    } catch (Throwable $err) {
        $db->setSqlRollback();
        Cache::deleteWriteGuard($guard);
        $state['data'][$mod] = [];
        setMigrateState($state);
        throw $err;
    }
    Cache::addEpoch(true);
    Cache::deleteWriteGuard($guard);
    $state['data'][$mod]['state'] = 'done';
    setMigrateState($state);
}

# Put the files of every module where the new texts expect them: directly addressed files are copied into the public archive, everything else returns to the type root
# Renamed attachments and their thumbnails take their managed names; a file whose place is taken by other bytes stays in the working directory and is noted
function setMigrateFiles(array &$state): void {
    $arch = [];
    foreach ($state['data'] as $one) foreach ($one['archive'] ?? [] as $mod => $list) $arch[$mod] = array_merge($arch[$mod] ?? [], $list);
    $page = is_file(UPLOADS_DIR.'/index.html') ? (string)file_get_contents(UPLOADS_DIR.'/index.html') : '';
    foreach ($state['data'] as $mod => $one) {
        if (($one['state'] ?? '') !== 'done' || !empty($state['files'][$mod])) continue;
        $dir = getMigrateDir().'/files/'.$mod;
        foreach (array_unique($arch[$mod] ?? []) as $rel) {
            $dst = UPLOADS_DIR.'/archive/'.$mod.'/'.$rel;
            if (!is_file($dir.'/'.$rel) || is_file($dst)) continue;
            $sub = dirname($dst);
            if (!is_dir($sub) && !mkdir($sub, 0755, true) && !is_dir($sub)) throw new RuntimeException('The archive directory of '.$mod.' cannot be created');
            if (!copy($dir.'/'.$rel, $dst)) throw new RuntimeException('The file '.$rel.' cannot be copied into the archive');
        }
        foreach (['', '/'.$mod] as $sub) {
            $path = UPLOADS_DIR.'/archive'.$sub.'/index.html';
            if (is_dir(dirname($path)) && !is_file($path) && $page !== '') file_put_contents($path, $page);
        }
        $into = UPLOADS_DIR.'/'.$one['type'];
        foreach (setMigrateMove($dir, $into, [], $one['names'] ?? []) as $rel) addMigrateNote($state, $mod, 'the file '.$rel.' stays in '.$dir.', its place in '.$into.' is taken');
        foreach ($one['moves'] ?? [] as $old => $new) {
            $dst = $into.'/'.$new;
            if (!is_file($into.'/'.$old) || file_exists($dst)) continue;
            $sub = dirname($dst);
            if ((!is_dir($sub) && !mkdir($sub, 0755, true)) || !rename($into.'/'.$old, $dst)) addMigrateNote($state, $mod, 'the file '.$old.' could not become '.$new);
        }
        if (is_dir($dir) && count(scandir($dir) ?: []) === 2) rmdir($dir);
        $state['files'][$mod] = true;
        setMigrateState($state);
    }
}

# Switch on every type the migration created or switched off; a refusal, for example a web server that serves the upload directory, is noted and leaves the type off
function setMigrateActive(array &$state): void {
    foreach (array_keys($state['active']) as $name) {
        $type = getMigrateType($name);
        if ($type === null || $type->active) {
            unset($state['active'][$name]);
            continue;
        }
        try {
            getNodeWriter()->updateNodeTypeStatus($name, true, $type->version);
            unset($state['active'][$name]);
        } catch (NodeException $err) {
            addMigrateNote($state, $name, 'the type stays switched off: '.$err->getMessage());
        }
        setMigrateState($state);
    }
}

# Raise the id counter of the materials above the highest id any removed module used, so an old address never names a new material of the same type
# Such an address then meets the 404 of its type, where the legacy map sends it on; InnoDB keeps a counter that is already higher and ignores a lower one
function setMigrateCounter(array $plan, array &$state): void {
    if (!empty($state['counter'])) return;
    $top = 0;
    foreach (array_keys($plan) as $mod) $top = max($top, getMigrateCount('SELECT COALESCE(MAX(id), 0) FROM '.PREFIX_DB.'_'.$mod));
    getMigrateQuery('ALTER TABLE '.PREFIX_DB.'_nodes AUTO_INCREMENT = '.($top + 1));
    $state['counter'] = $top + 1;
    setMigrateState($state);
}

# Run every step that is not done yet in the fixed order - stash, types, id counter, data, files, activation - and answer the text that stopped the run, or an empty string
# The map of the old addresses needs its table, which the 6.3 update of setup.php creates, so a schema without it stops the run before the first write
function setMigrateRun(): string {
    $state = getMigrateState();
    try {
        set_time_limit(0);
        if (!checkMigrateTable('node_legacy')) throw new RuntimeException('The table '.PREFIX_DB.'_node_legacy is missing: run the 6.3 update of setup.php first');
        $plan = getMigratePlan($state);
        setMigrateStash($plan, $state);
        $todo = array_filter($plan, fn(array $v): bool => $v['block'] === '');
        foreach ($todo as $mod => $one) if (empty($state['types'][$mod])) setMigrateType($mod, $one, $state);
        setMigrateCounter($plan, $state);
        foreach ($todo as $mod => $one) setMigrateData($mod, $one, $state);
        setMigrateFiles($state);
        setMigrateActive($state);
    } catch (Throwable $err) {
        Logger::addSite('error', 'Node: the migration of the old modules stopped', ['error' => $err->getMessage()]);
        return $err->getMessage();
    }
    Logger::addSite('info', 'Node: the migration of the old modules ran', ['aid' => getNodeContext()->aid]);
    return '';
}

# Render the page of the migration: every module with its target and its state, the notes of the runs and the button that starts or continues the run
function setMigratePage(string $fail): void {
    global $tpl;
    $state = getMigrateState();
    $plan = getMigratePlan($state);
    $rows = '';
    $open = false;
    foreach ($plan as $mod => $one) {
        $done = !empty($state['files'][$mod]);
        $open = $open || (!$done && $one['block'] === '');
        $one = ($state['data'][$mod]['count'] ?? []) + $one;
        $cells = [$mod.' → '.$one['type'], $one['rows'], $one['comments'], $one['favorites'], $one['categories'], $one['block'] ?: ($done ? _NODE_MIGDONE : _NODE_MIGWAIT)];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells' => array_map(fn(mixed $v): array => ['text' => (string)$v], $cells)]);
    }
    $head = array_map(fn(string $v): array => ['text' => $v], [_TYPE, _NODE_MIGROWS, _COMMENTS, _FAVORITES, _CATEGORIES, _STATUS]);
    $cont = $tpl->getHtmlFrag('alert', ['text' => _NODE_MIGINFO]);
    if ($fail !== '') $cont .= $tpl->getHtmlFrag('alert', ['type' => 'error', 'messages' => [_NODE_MIGSTOP, $fail]]);
    elseif (!$open && $plan) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'success', 'text' => _NODE_MIGDONE]);
    $cont .= $tpl->getHtmlFrag('table', ['headers' => $head, 'rows_html' => $rows]);
    foreach ($state['notes'] as $mod => $list) {
        if ($list) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => array_map(fn(string $v): string => $mod.': '.$v, $list)]);
    }
    if ($open || !empty($state['active'])) {
        $hide = '';
        foreach (['op' => 'run', 'token' => getSiteToken('update')] as $key => $val) {
            $hide .= $tpl->getHtmlFrag('hidden', ['name_attr' => $key, 'value_attr' => $val, 'input_attr' => '']);
        }
        $cont .= $tpl->getHtmlFrag('post-button', ['action' => 'update.php', 'hidden' => $hide, 'icon_name' => 'play-circle', 'title' => _NODE_MIGRUN, 'label' => _NODE_MIGRUN]);
    }
    setHead(['title' => _NODE_MIGRATE]);
    echo $tpl->getHtmlFrag('title', ['title' => _NODE_MIGRATE, 'is_level_one' => true, 'content' => $cont]);
    setFoot();
}

$conf['name'] = '';
$fail = '';
if (getVar('post', 'op', 'var') === 'run') $fail = checkSiteToken(getVar('post', 'token', 'var'), 'update') ? setMigrateRun() : _ACCESSDENIED;
setMigratePage($fail);
