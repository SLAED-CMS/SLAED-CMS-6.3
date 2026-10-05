<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

$sgtime = microtime(true);
define('BASE_DIR', str_replace('\\', '/', __DIR__));

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
    $base = ['version' => 1, 'stash' => [], 'types' => [], 'data' => [], 'outer' => [], 'files' => [], 'active' => [], 'notes' => [], 'counter' => 0];
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
# A root file named in $skip stays only with the bytes $skip gives it: an older placeholder under a guard name belongs to the module and moves with it
function setMigrateMove(string $from, string $into, array $skip = [], array $names = [], string $rel = ''): array {
    $left = [];
    foreach (is_dir($from) ? (scandir($from) ?: []) : [] as $one) {
        if ($one === '.' || $one === '..') continue;
        if ($rel === '' && isset($skip[$one]) && is_file($from.'/'.$one) && file_get_contents($from.'/'.$one) === $skip[$one]) continue;
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
# The files of its upload directory move into the working directory, the guard files of the release stay; a repeat finds nothing left to move
function setMigrateStash(array $plan, array &$state): void {
    global $db;
    $todo = array_keys(array_filter($plan, fn(array $v): bool => $v['block'] === ''));
    $todo = array_values(array_filter($todo, fn(string $v): bool => empty($state['stash'][$v])));
    if (!$todo) return;
    if (!$db->setSqlBegin()) throw new RuntimeException('The transaction of the stash step cannot be started');
    try {
        foreach ($todo as $mod) {
            foreach (['_categories', '_comment', '_favorites'] as $tab) {
                getMigrateQuery('UPDATE '.PREFIX_DB.$tab.' SET modul = :key WHERE modul = :mod', ['key' => '~'.$mod, 'mod' => $mod]);
            }
        }
        if (!$db->setSqlCommit()) throw new RuntimeException('The commit of the stash step failed');
    } catch (Throwable $err) {
        $db->setSqlRollback();
        throw $err;
    }
    foreach ($todo as $mod) deleteCategoryMap($mod);
    $skip = FileManager::getGuardFiles();
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
# A rewritten address reads ./uploads/archive/..., the local form the safe parser accepts, unless a / before it makes it absolute or rooted already
# Only a file the working directory holds is touched and collected by module into the archive list; every other reference stays exactly as it was
function getMigrateText(string $text, array $names, array $files, array &$arch): string {
    if ($names && stripos($text, '[attach=') !== false) {
        $text = preg_replace_callback('/\[attach=([a-zA-Z0-9_\-\. ]+) align=/', fn(array $m): string => '[attach='.($names[$m[1]] ?? $m[1]).' align=', $text) ?? $text;
    }
    if (!$files || stripos($text, 'uploads/') === false) return $text;
    $mods = implode('|', array_map(fn(string $v): string => preg_quote($v, '#'), array_keys($files)));
    return preg_replace_callback('#(^|[^A-Za-z0-9_.-])uploads/('.$mods.')/([A-Za-z0-9_./%-]+)#', function (array $mat) use ($files, &$arch): string {
        $cut = rtrim($mat[3], '.');
        $rel = rawurldecode($cut);
        if (!isset($files[$mat[2]][$rel])) return $mat[0];
        $arch[$mat[2]][$rel] = true;
        return $mat[1].(($mat[1] === '/') ? '' : './').'uploads/archive/'.$mat[2].'/'.$mat[3];
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
    $text = preg_replace_callback('#<(?:a|img)\b[^>]*>#i', fn(array $m): string => str_ireplace(['&#034;', '&#34;', '&quot;'], '"', $m[0]), $text) ?? $text;
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
    $text = getMigrateLocal($text);
    $text = preg_replace_callback("/\x01([0-9]+)\x01/", fn(array $m): string => $keep[intval($m[1])], $text) ?? $text;
    return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
}

# Make the local targets of a converted text explicit: a bare relative address of [img], [img=...], [img alt=...], [url] and [url=...] gains ./ in front
# The old modules rendered trusted HTML, where uploads/x.png resolved against the site; the safe parser of Node accepts a local address only with a leading / or ./
# The ./ form keeps resolving against the site whether it runs in the root or in a directory; an address with a scheme or a www. prefix stays as it is
function getMigrateLocal(string $text): string {
    return preg_replace_callback('#(\[img(?:[= ][^\]]*)?\]|\[url=|\[url\])([A-Za-z0-9_][^\s\[\]]*)#i', function (array $mat): string {
        if (preg_match('#^(?:[a-z][a-z0-9+.\-]*:|www\.)#i', $mat[2])) return $mat[0];
        return $mat[1].'./'.$mat[2];
    }, $text) ?? $text;
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

# The files of one module the new materials take from the type root: every attachment their texts and their comments name with its thumbnail, and every local resource
# A comment of a material shows its attachments through the attach route of the type as the material does, so its files stay in the closed type root as well
# The list holds the names of the working directory, so an attachment the migration renames is listed under the old name it is stored under there
function getMigrateKeep(NodeType $type, array $texts, array $files, array $moves): array {
    $out = [];
    foreach ($texts as $text) {
        if (!preg_match_all('/\[attach=([a-zA-Z0-9_\-\. ]+) align=/', $text, $mm)) continue;
        foreach ($mm[1] as $one) foreach ([$one, 'thumb/'.$one] as $rel) if (isset($files[$rel])) $out[$rel] = true;
    }
    $back = array_flip($moves);
    $sql = 'SELECT a.src FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE n.tid = :tid';
    foreach (getMigrateQuery($sql, ['tid' => $type->id])->fetchAll(PDO::FETCH_COLUMN) as $src) {
        $rel = $back[$src] ?? $src;
        if (isset($files[$rel])) $out[$rel] = true;
    }
    return array_keys($out);
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
# Comment bodies are rewritten like the materials - renamed attachments, direct addresses into the archive - and the old rating terms become the last votes of the targets
function setMigrateLinks(string $mod, NodeType $type, array $map, array $names, array $files, array &$arch): void {
    $key = '~'.$mod;
    $sql = 'SELECT id, body FROM '.PREFIX_DB.'_comment WHERE modul = :key AND (body LIKE :like OR body LIKE :tag)';
    foreach (getMigrateQuery($sql, ['key' => $key, 'like' => '%uploads/%', 'tag' => '%[attach=%'])->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $body = getMigrateText($row['body'], $names, $files, $arch);
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

# Carry the data of one module in one transaction under the lock of its type row; the map is written to the manifest before the commit
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
    $sql = 'SELECT body FROM '.PREFIX_DB.'_comment WHERE modul = :key AND body LIKE :tag';
    $texts = array_merge($texts, getMigrateQuery($sql, ['key' => '~'.$mod, 'tag' => '%[attach=%'])->fetchAll(PDO::FETCH_COLUMN));
    $names = getMigrateNames($type->name, array_map('strval', $texts), $files[$mod]);
    $arch = [];
    if (!$db->setSqlBegin()) throw new RuntimeException('The transaction of '.$mod.' cannot be started');
    try {
        getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE', ['id' => $type->id]);
        getMigrateQuery('UPDATE '.PREFIX_DB.'_categories SET modul = :name WHERE modul = :key', ['name' => $type->name, 'key' => '~'.$mod]);
        $state['notes'][$mod] = [];
        $moves = [];
        $map = addMigrateNodes($mod, $type, $rows, $names, $files, $arch, $moves, $state);
        if ($mod === 'help') addMigrateReplies($type, $rows, $map, $names, $files, $arch);
        setMigrateLinks($mod, $type, $map, $names, $files, $arch);
        $keep = getMigrateKeep($type, array_map('strval', $texts), $files[$mod], $moves);
        $state['data'][$mod] = ['state' => 'committing', 'type' => $type->name, 'map' => $map, 'names' => $names, 'moves' => $moves, 'archive' => array_map('array_keys', $arch),
            'keep' => $keep, 'count' => array_intersect_key($one, array_flip(['rows', 'comments', 'favorites', 'categories']))];
        setMigrateState($state);
        if (!$db->setSqlCommit()) throw new RuntimeException('The commit of '.$mod.' failed');
    } catch (Throwable $err) {
        $db->setSqlRollback();
        $state['data'][$mod] = [];
        setMigrateState($state);
        throw $err;
    }
    deleteCategoryMap($type->name);
    $state['data'][$mod]['state'] = 'done';
    setMigrateState($state);
}

# The text columns outside the removed modules that may address their files directly, as table => text columns; a table or column a site lacks is passed over
# The mail queue is left out: a queued letter is sent as it was written, and a sent one is history
function getMigrateOuter(): array {
    return ['forum' => ['body'], 'comment' => ['body'], 'privat' => ['body'], 'message' => ['body'], 'newsletter' => ['body'], 'blocks' => ['content'], 'users' => ['sig'],
        'voting' => ['body']];
}

# Point the direct addresses the rest of the site keeps into the closed module directories at the public archive, as the materials were pointed in their data step
# It runs once, after the data of every module and before any file leaves the working directory, because only a file still held there is rewritten and archived
function setMigrateOuter(array &$state): void {
    global $db;
    if (!empty($state['outer'])) return;
    $files = [];
    foreach (array_keys(getMigrateMap()) as $key) $files[$key] = array_fill_keys(getMigrateList(getMigrateDir().'/files/'.$key), true);
    $files = array_filter($files);
    $arch = [];
    $rows = 0;
    if (!$db->setSqlBegin()) throw new RuntimeException('The transaction of the outer addresses cannot be started');
    try {
        $sql = 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tab';
        foreach ($files ? getMigrateOuter() : [] as $tab => $cols) {
            $have = getMigrateQuery($sql, ['tab' => PREFIX_DB.'_'.$tab])->fetchAll(PDO::FETCH_COLUMN);
            $cols = array_values(array_intersect($cols, $have));
            if (!$cols || !in_array('id', $have, true)) continue;
            $like = implode(' OR ', array_map(fn(string $v): string => '`'.$v.'` LIKE \'%uploads/%\'', $cols));
            foreach (getMigrateQuery('SELECT id, `'.implode('`, `', $cols).'` FROM '.PREFIX_DB.'_'.$tab.' WHERE '.$like)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sets = [];
                $pars = ['id' => $row['id']];
                foreach ($cols as $i => $col) {
                    $text = getMigrateText((string)$row[$col], [], $files, $arch);
                    if ($text === (string)$row[$col]) continue;
                    $sets[] = '`'.$col.'` = :v'.$i;
                    $pars['v'.$i] = $text;
                }
                if (!$sets) continue;
                getMigrateQuery('UPDATE '.PREFIX_DB.'_'.$tab.' SET '.implode(', ', $sets).' WHERE id = :id', $pars);
                $rows++;
            }
        }
        $state['outer'] = ['rows' => $rows, 'archive' => array_map('array_keys', $arch)];
        setMigrateState($state);
        if (!$db->setSqlCommit()) throw new RuntimeException('The commit of the outer addresses failed');
    } catch (Throwable $err) {
        $db->setSqlRollback();
        unset($state['outer']);
        setMigrateState($state);
        throw $err;
    }
}

# Remove the directories below and at one path that hold no file any more, deepest first; a directory that still holds something stays
function deleteMigrateTree(string $dir): void {
    if (!is_dir($dir) || is_link($dir)) return;
    foreach (scandir($dir) ?: [] as $one) {
        if ($one !== '.' && $one !== '..' && is_dir($dir.'/'.$one)) deleteMigrateTree($dir.'/'.$one);
    }
    if (count(scandir($dir) ?: []) === 2) rmdir($dir);
}

# Put one file of the working directory in its place by a move, or by a copy when the working directory still needs it; a place holding the same bytes counts as done
# A place taken by other bytes answers false and leaves the file where it is
function setMigrateFile(string $src, string $dst, bool $copy): bool {
    if (is_file($dst)) {
        if (sha1_file($src) !== sha1_file($dst)) return false;
        return $copy || unlink($src);
    }
    $sub = dirname($dst);
    if (!is_dir($sub) && !mkdir($sub, 0755, true) && !is_dir($sub)) throw new RuntimeException('The directory '.$sub.' cannot be created');
    if (!($copy ? copy($src, $dst) : rename($src, $dst))) throw new RuntimeException('The file '.$src.' cannot be placed at '.$dst);
    return true;
}

# Put the files of every module where the new texts expect them: a file the site addresses directly moves into the public archive
# The files the materials use return to the type root under their managed names, and a file that is both is copied into the archive first
# The files nothing uses stay in the working directory and are noted
function setMigrateFiles(array &$state): void {
    $arch = [];
    foreach ($state['data'] as $one) foreach ($one['archive'] ?? [] as $mod => $list) $arch[$mod] = array_merge($arch[$mod] ?? [], $list);
    foreach ($state['outer']['archive'] ?? [] as $mod => $list) $arch[$mod] = array_merge($arch[$mod] ?? [], $list);
    $page = is_file(UPLOADS_DIR.'/index.html') ? (string)file_get_contents(UPLOADS_DIR.'/index.html') : '';
    foreach ($state['data'] as $mod => $one) {
        if (($one['state'] ?? '') !== 'done' || !empty($state['files'][$mod])) continue;
        $dir = getMigrateDir().'/files/'.$mod;
        $keep = array_flip($one['keep'] ?? []);
        foreach (array_unique($arch[$mod] ?? []) as $rel) {
            if (!is_file($dir.'/'.$rel)) continue;
            if (!setMigrateFile($dir.'/'.$rel, UPLOADS_DIR.'/archive/'.$mod.'/'.$rel, isset($keep[$rel]))) {
                addMigrateNote($state, $mod, 'the file '.$rel.' stays in '.$dir.', its place in the archive is taken');
            }
        }
        foreach (['', '/'.$mod] as $sub) {
            $path = UPLOADS_DIR.'/archive'.$sub.'/index.html';
            if (is_dir(dirname($path)) && !is_file($path) && $page !== '') file_put_contents($path, $page);
        }
        $into = UPLOADS_DIR.'/'.$one['type'];
        $names = $one['names'] ?? [];
        $moves = $one['moves'] ?? [];
        foreach (array_keys($keep) as $rel) {
            $base = basename($rel);
            $root = in_array(dirname($rel), ['.', 'thumb'], true);
            $new = $moves[$rel] ?? (($root && isset($names[$base])) ? substr($rel, 0, -strlen($base)).$names[$base] : $rel);
            if (is_file($dir.'/'.$rel) && !setMigrateFile($dir.'/'.$rel, $into.'/'.$new, false)) {
                addMigrateNote($state, $mod, 'the file '.$rel.' stays in '.$dir.', its place in '.$into.' is taken');
            }
        }
        $left = array_filter(getMigrateList($dir), fn(string $v): bool => !isset(FileManager::getGuardFiles()[basename($v)]));
        if ($left) addMigrateNote($state, $mod, count($left).' files nothing on the site uses stay in '.$dir.' and are not published');
        deleteMigrateTree($dir);
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

# Run every step that is not done yet in the fixed order - stash, types, id counter, data, outer addresses, files, activation - and answer the text that stopped the run
# The map of the old addresses needs its table, which the first stage of this file creates, so a schema without it stops the run before the first write
function setMigrateRun(): string {
    $state = getMigrateState();
    try {
        set_time_limit(0);
        $text = 'The table '.PREFIX_DB.'_node_legacy is missing: run the first stage of update.php?op=update';
        if (!checkMigrateTable('node_legacy')) throw new RuntimeException($text);
        $plan = getMigratePlan($state);
        setMigrateStash($plan, $state);
        $todo = array_filter($plan, fn(array $v): bool => $v['block'] === '');
        foreach ($todo as $mod => $one) if (empty($state['types'][$mod])) setMigrateType($mod, $one, $state);
        setMigrateCounter($plan, $state);
        foreach ($todo as $mod => $one) setMigrateData($mod, $one, $state);
        setMigrateOuter($state);
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

# Saving configurations to a file; every scalar is stored as a string unless $raw keeps the native types the definitions of the extra fields are made of
# The answer says whether the whole file was written: a file or a config/ that is not writable is left as it was and answers false, which every caller reports
function setUpdateFile(string $fp, array $arr, array $act = [], bool $raw = false): bool {
    $fp = BASE_DIR.'/config/'.$fp;
    if (!empty($act)) $arr = array_replace_recursive($arr, $act);
    ksort($arr);
    $norm = function ($val) use (&$norm) {
        if (is_array($val)) {
            foreach ($val as $kk => $vv) $val[$kk] = $norm($vv);
            return $val;
        }
        if (is_bool($val)) return (string)(int)$val;
        if (is_int($val)) return (string)$val;
        if (is_float($val)) return (string)$val;
        if (is_null($val)) return '';
        return (string)$val;
    };
    if (!$raw) $arr = $norm($arr);
    $key = pathinfo(basename($fp), PATHINFO_FILENAME);
    $data = ($key === 'global') ? $arr : [$key => $arr];
    $exp = function (array $arr, int $dep = 0) use (&$exp): string {
        $pad = str_repeat('    ', $dep);
        $ind = $pad.'    ';
        $out = '['."\n";
        foreach ($arr as $key => $val) {
            $body = is_array($val) ? $exp($val, $dep + 1) : var_export($val, true);
            $out .= $ind.var_export($key, true).' => '.$body.','."\n";
        }
        return $out.$pad.']';
    };
    $cnt = '<?php'."\n"
    .'# Author: Eduard Laas'."\n"
    .'# 2005 - '.date('Y').' SLAED'."\n"
    .'# License: MIT'."\n"
    .'# Website: slaed.net'."\n\n"
    .'return '.$exp($data).';'."\n";
    $lock = FileManager::getPathLock(CONFIG_DIR);
    $done = is_writable(is_file($fp) ? $fp : CONFIG_DIR) && file_put_contents($fp, $cnt, LOCK_EX) === strlen($cnt);
    if (function_exists('opcache_invalidate')) opcache_invalidate($fp, true);
    if (is_file(CONFIG_DIR.'/local.php')) unlink(CONFIG_DIR.'/local.php');
    FileManager::deletePathLock($lock);
    return $done;
}

# Include one configuration source in a scope of its own with its output swallowed and answer its values
# The answer is the array a 6.3 source returns, the first array a 6.2 source assigns to a variable, or an empty array for a missing file or a source without settings
function getUpdateSource(string $file): array {
    if (!is_file($file)) return [];
    ob_start();
    $data = (static function (string $path): array {
        $back = include $path;
        if (is_array($back)) return $back;
        unset($path, $back);
        foreach (get_defined_vars() as $val) if (is_array($val)) return $val;
        return [];
    })($file);
    ob_end_clean();
    return $data;
}

# The connection settings of the site from config/db.php, written by 6.3 as the db area or by 6.2 as the variable $confdb, empty while the file does not exist
function getUpdateCred(): array {
    $data = getUpdateSource(CONFIG_DIR.'/db.php');
    return is_array($data['db'] ?? null) ? $data['db'] : $data;
}

# Check what the 6.3 data update needs before anything is changed and answer the refusal, or an empty string when the run may start
# The server has to enforce CHECK constraints and to know RENAME COLUMN and RENAME INDEX of the schema file, which MariaDB has from 10.5.2 on
# The update needs the users and admins tables of the prefix config/db.php names
# Every table of a points, ratings, fields, Node, private message or newsletter transaction has to be InnoDB; nothing is converted, and the run closes the site itself
function checkUpdateBase(Database $db, string $prefix): string {
    [$ver] = $db->getSqlRow($db->getSqlQuery('SELECT VERSION()'));
    $min = (stripos((string)$ver, 'mariadb') !== false) ? '10.5.2' : '8.0.16';
    if (version_compare(preg_replace('/[^0-9.].*$/', '', (string)$ver), $min, '<')) return 'The database server '.$ver.' is older than '.$min.'.';
    $sql = 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND ';
    [$num] = $db->getSqlRow($db->getSqlQuery($sql.'table_name IN (:users, :admins)', ['users' => $prefix.'_users', 'admins' => $prefix.'_admins']));
    if ($num < 2) return 'The tables '.$prefix.'_users and '.$prefix.'_admins are not both in the database, check the table prefix of the site.';
    $list = [];
    $tabs = ['users', 'admins', 'comment', 'forum', 'favorites', 'user_oauth', 'points', 'rating_targets', 'rating_actors', 'rating_votes', 'categories', 'voting',
        'newsletter', 'privat'];
    foreach ($tabs as $key => $name) $list['t'.$key] = $prefix.'_'.$name;
    $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN (:'.implode(', :', array_keys($list)).')'
        .' AND engine IS NOT NULL AND engine != \'InnoDB\'';
    $res = $db->getSqlQuery($sql, $list);
    $fix = [];
    while ($res && ([$name] = $db->getSqlRow($res))) $fix[] = 'ALTER TABLE `'.$name.'` ENGINE=InnoDB;';
    return $fix ? 'These tables are not InnoDB, convert them and start the update again: '.implode(' ', $fix) : '';
}

# Write one file of the update backup through a temporary file and a rename, so a reader never meets a half-written snapshot or manifest
function setUpdateBackup(string $path, string $text): bool {
    $temp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
    return file_put_contents($temp, $text, LOCK_EX) === strlen($text) && rename($temp, $path);
}

# The points unit of the 6.3 data update: keep the starting balances as a hashed snapshot, carry users.point into points.active and leave the mark that opens the subsystem
# The unit resumes from its manifest: verified is skipped, applying and prepared continue
# Journal rows without a manifest stop it, because a current balance is never taken for a starting one
function setUpdatePoints(Database $db, string $prefix): array {
    $dir = BASE_DIR.'/storage/backup/update/points';
    $file = $dir.'/manifest.json';
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($info)) {
        [$rows] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$prefix.'_points`'));
        if ($rows > 0 || isset($mark['points'])) return getUpdateRow('points: the journal already has rows and no manifest exists, the unit is stopped', false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('points: '.$dir.' could not be created', false);
        $list = [];
        $done = $db->setSqlBegin();
        $res = $done ? $db->getSqlQuery('SELECT id, points FROM `'.$prefix.'_users` ORDER BY id ASC') : false;
        while ($res && ([$uid, $sum] = $db->getSqlRow($res))) $list[(string)$uid] = intval($sum);
        if ($done) $db->setSqlCommit();
        $text = (string)json_encode($list);
        if (!$res || !setUpdateBackup($dir.'/balances.json', $text)) return getUpdateRow('points: the snapshot of the balances could not be written', false);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => 0, 'count' => count($list), 'source' => ['balances.json' => hash('sha256', $text)], 'target' => []];
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('points: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        $same = is_file($dir.'/balances.json') && hash_file('sha256', $dir.'/balances.json') === ($info['source']['balances.json'] ?? '');
        if (!$same) return getUpdateRow('points: the snapshot does not match its manifest', false);
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('points: the manifest could not be written', false);
        $users = getUpdateSource(CONFIG_DIR.'/users.php')['users'] ?? [];
        $point = getUpdateSource(CONFIG_DIR.'/points.php')['points'] ?? [];
        $flag = isset($users['point']) ? ($users['point'] ? '1' : '0') : ($point['active'] ?? '');
        $moved = $flag !== ($point['active'] ?? '');
        $stale = isset($users['point']) || isset($users['points']);
        $point['active'] = $flag;
        unset($users['point'], $users['points']);
        $text = 'points: config/points.php is not a valid points scope';
        if (count($point['actions'] ?? []) !== 14 || !in_array($point['active'], ['0', '1'], true)) return getUpdateRow($text, false);
        if ($moved && !setUpdateFile('points.php', $point)) return getUpdateRow('points: config/points.php could not be written, run the update again', false);
        if ($stale && !setUpdateFile('users.php', $users)) return getUpdateRow('points: config/users.php could not be written, run the update again', false);
        $info['target'] = ['points.php' => hash_file('sha256', CONFIG_DIR.'/points.php'), 'users.php' => hash_file('sha256', CONFIG_DIR.'/users.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('points: the manifest could not be written', false);
    }
    if (!setUpdateFile('update.php', ['points' => '6.3.0'] + $mark)) return getUpdateRow('points: config/update.php could not be written, the subsystem stays closed', false);
    return getUpdateRow('points: starting balances kept ('.intval($info['count']).' accounts), the subsystem is open', true);
}

# The ratings unit of the 6.3 data update: keep the aggregate of every remaining target as its starting balance, carry the last participation over and publish the four-key rules
# Nothing is written before the whole preflight passed: a broken aggregate, a broken time or address of a kept row and a broken rule stop the unit with the table and the id
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a row that is already stored has to equal its snapshot
# Rows of the new tables without a manifest stop it, because a current aggregate is never taken for a starting one; rows of polls and of other events are counted and left alone
function setUpdateRatings(Database $db, string $prefix): array {
    $dir = BASE_DIR.'/storage/backup/update/ratings';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'votes', 'tvotes', ''], 'forum' => ['forum', 'ratings', 'score', ' WHERE pid = 0']];
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $bad = [];
    $owners = function () use ($db, $prefix, $maps, &$bad): array|false {
        $list = [];
        foreach ($maps as $scope => [$tab, $cnt, $sum, $cond]) {
            $res = $db->getSqlQuery('SELECT id, '.$cnt.', '.$sum.' FROM `'.$prefix.'_'.$tab.'`'.$cond.' ORDER BY id ASC');
            if (!$res) return false;
            while ([$mid, $num, $tot] = $db->getSqlRow($res)) {
                [$mid, $num, $tot] = [intval($mid), intval($num), intval($tot)];
                if ($num ? ($tot < $num || $tot > 5 * $num) : $tot > 0) $bad[] = $prefix.'_'.$tab.' '.$mid;
                $list[] = [$scope, $mid, $tot, $num];
            }
        }
        return $list;
    };
    $count = function (string $sql) use ($db): int {
        $res = $db->getSqlQuery($sql);
        return $res ? intval($db->getSqlRow($res)[0] ?? -1) : -1;
    };
    $polls = 'SELECT COUNT(*) FROM `'.$prefix.'_rating` WHERE modul = \'voting\'';
    if (!is_array($info)) {
        $rows = 0;
        foreach (['targets', 'actors', 'votes'] as $name) {
            $num = $count('SELECT COUNT(*) FROM `'.$prefix.'_rating_'.$name.'`');
            if ($num < 0) return getUpdateRow('ratings: the table '.$prefix.'_rating_'.$name.' could not be read, the unit is stopped', false);
            $rows += $num;
        }
        $text = 'ratings: the new tables already have rows or the mark is set and no manifest exists, the unit is stopped';
        if ($rows > 0 || isset($mark['ratings'])) return getUpdateRow($text, false);
        $old = is_file(CONFIG_DIR.'/ratings.php') ? ((require CONFIG_DIR.'/ratings.php')['ratings'] ?? []) : [];
        $rules = [];
        $stat = ['voting' => 0, 'foreign' => 0, 'orphan' => 0, 'dropped' => 0];
        foreach ($old as $name => $rule) {
            if (!isset($maps[$name]) && !preg_match('/^node\.[a-z][a-z0-9]{0,19}$/D', $name)) {
                $stat['dropped']++;
                continue;
            }
            $part = is_string($rule) ? explode('|', $rule) : [];
            if (count($part) === 3) $rule = ['active' => $part[1], 'period' => $part[0], 'detail' => $part[2], 'guests' => '1'];
            $good = is_array($rule) && count($rule) === 4 && is_string($rule['period'] ?? null) && preg_match('/^(?:0|[1-9][0-9]{0,17})$/D', $rule['period']);
            $good = $good && intval($rule['period']) % 86400 === 0;
            foreach (['active', 'detail', 'guests'] as $key) $good = $good && in_array($rule[$key] ?? null, ['0', '1'], true);
            if ($good) $rules[$name] = ['active' => $rule['active'], 'period' => $rule['period'], 'detail' => $rule['detail'], 'guests' => $rule['guests']];
            else $bad[] = 'config/ratings.php '.$name;
        }
        foreach (array_diff(array_keys($maps), array_keys($old)) as $name) $bad[] = 'config/ratings.php '.$name.' (missing)';
        $done = $db->setSqlBegin();
        $now = $done ? $count('SELECT UNIX_TIMESTAMP()') : -1;
        $list = $done ? $owners() : false;
        $seen = [];
        foreach ($list ?: [] as $row) $seen[$row[0].':'.$row[1]] = true;
        $last = [];
        $res = $done ? $db->getSqlQuery('SELECT id, mid, modul, time, uid, ip FROM `'.$prefix.'_rating` ORDER BY id ASC') : false;
        while ($res && ([$rid, $mid, $mod, $time, $uid, $ip] = $db->getSqlRow($res))) {
            if (!isset($maps[$mod]) || !isset($seen[$mod.':'.$mid])) {
                $stat[$mod === 'voting' ? 'voting' : (isset($maps[$mod]) ? 'orphan' : 'foreign')]++;
                continue;
            }
            $pack = (!intval($uid) && filter_var($ip, FILTER_VALIDATE_IP)) ? inet_pton($ip) : false;
            $norm = $pack === false ? false : inet_ntop($pack);
            $actor = intval($uid) ? 'u:'.intval($uid) : (in_array($norm, [false, '0.0.0.0', '::'], true) ? '' : 'g:'.$norm);
            if ($actor === '' || !preg_match('/^[1-9][0-9]{0,13}$/D', $time) || intval($time) > $now) {
                $bad[] = $prefix.'_rating '.intval($rid);
                continue;
            }
            $last[$mod][intval($mid)][$actor] = max($last[$mod][intval($mid)][$actor] ?? 0, intval($time));
        }
        if ($done) $db->setSqlCommit();
        if (!$done || $now < 1 || $list === false || !$res) return getUpdateRow('ratings: the aggregates and the kept terms could not be read', false);
        if ($bad) return getUpdateRow('ratings: the preflight found broken data, nothing was written ('.count($bad).'): '.implode(', ', array_slice($bad, 0, 50)), false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('ratings: '.$dir.' could not be created', false);
        $terms = [];
        foreach (array_intersect_key($maps, $last) as $scope => $void) {
            ksort($last[$scope]);
            foreach ($last[$scope] as $mid => $acts) {
                ksort($acts, SORT_STRING);
                foreach ($acts as $actor => $time) $terms[] = [$scope, $mid, $actor, $time];
            }
        }
        $text = ['targets.json' => (string)json_encode($list), 'terms.json' => (string)json_encode($terms)];
        $text['rules.json'] = (string)json_encode(['source' => $old, 'rules' => $rules]);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => ['targets' => 0, 'terms' => 0], 'moment' => $now, 'source' => [], 'target' => []];
        $info['count'] = ['targets' => count($list), 'terms' => count($terms)] + $stat;
        foreach ($text as $name => $body) {
            if (!setUpdateBackup($dir.'/'.$name, $body)) return getUpdateRow('ratings: the snapshot '.$name.' could not be written', false);
            $info['source'][$name] = hash('sha256', $body);
        }
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('ratings: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        foreach (['targets.json', 'terms.json', 'rules.json'] as $name) {
            $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === ($info['source'][$name] ?? '');
            if (!$same) return getUpdateRow('ratings: the snapshot '.$name.' does not match its manifest', false);
        }
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('ratings: the manifest could not be written', false);
        $sets = ['targets' => ['targets.json', 'rating_targets', ['scope', 'mid', 'base', 'votes'], 2]];
        $sets['terms'] = ['terms.json', 'rating_actors', ['scope', 'mid', 'actor', 'last'], 3];
        foreach ($sets as $kind => [$snap, $tab, $cols, $knum]) {
            $list = json_decode((string)file_get_contents($dir.'/'.$snap), true);
            $keys = array_slice($cols, 0, $knum);
            $cond = implode(' AND ', array_map(fn(string $v): string => $v.' = :'.$v, $keys));
            $more = $kind === 'targets' ? ['created' => intval($info['moment'])] : [];
            $into = array_merge($cols, array_keys($more));
            $sql = 'INSERT INTO `'.$prefix.'_'.$tab.'` ('.implode(', ', $into).') VALUES (:'.implode(', :', $into).')';
            $find = 'SELECT '.implode(', ', $cols).' FROM `'.$prefix.'_'.$tab.'` WHERE '.$cond.' FOR UPDATE';
            for ($pos = intval($info['cursor'][$kind]); $pos < count($list); $pos += 500) {
                $good = $db->setSqlBegin();
                foreach (array_slice($list, $pos, 500) as $row) {
                    $pars = array_combine($cols, $row);
                    $res = $good ? $db->getSqlQuery($find, array_intersect_key($pars, array_flip($keys))) : false;
                    $cur = $res ? $db->getSqlRow($res) : false;
                    if ($cur) $good = array_map('strval', $pars) === array_map('strval', array_intersect_key($cur, $pars));
                    else $good = $res && $db->getSqlQuery($sql, $pars + $more) !== false;
                    if (!$good) break;
                }
                if (!$good || !$db->setSqlCommit()) {
                    $db->setSqlRollback();
                    return getUpdateRow('ratings: a batch of '.$kind.' was refused at row '.$pos.' - a stored row differs from the snapshot or could not be written', false);
                }
                $info['cursor'][$kind] = min($pos + 500, count($list));
                if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('ratings: the manifest could not be written', false);
            }
        }
        $real = [];
        foreach ($sets as $kind => [$snap, $tab, $cols]) {
            $list = [];
            $res = $db->getSqlQuery('SELECT '.implode(', ', $cols).' FROM `'.$prefix.'_'.$tab.'` ORDER BY scope ASC, mid ASC'.($kind === 'terms' ? ', actor ASC' : ''));
            while ($res && ($row = $db->getSqlRow($res))) {
                $list[] = [$row['scope'], intval($row['mid']), $kind === 'terms' ? $row['actor'] : intval($row['base']), intval($row[$cols[3]])];
            }
            $real[$snap] = hash('sha256', (string)json_encode($list));
        }
        $list = $owners();
        $same = $real === array_intersect_key($info['source'], $real) && $list !== false && !$bad && hash('sha256', (string)json_encode($list)) === $info['source']['targets.json'];
        $same = $same && $count('SELECT COUNT(*) FROM `'.$prefix.'_rating_votes`') === 0 && $count($polls) === intval($info['count']['voting']);
        if (!$same) return getUpdateRow('ratings: the stored targets, terms, owner aggregates or poll rows do not match the manifest, the rules are not published', false);
        $rules = json_decode((string)file_get_contents($dir.'/rules.json'), true)['rules'] ?? [];
        $same = ((require CONFIG_DIR.'/ratings.php')['ratings'] ?? null) === $rules;
        if (!$same && !setUpdateFile('ratings.php', $rules)) return getUpdateRow('ratings: config/ratings.php could not be written, the rules are not published', false);
        $info['target'] = $real + ['ratings.php' => hash_file('sha256', CONFIG_DIR.'/ratings.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('ratings: the manifest could not be written', false);
    }
    if (!setUpdateFile('update.php', ['ratings' => '6.3.0'] + $mark)) return getUpdateRow('ratings: config/update.php could not be written, the subsystem stays closed', false);
    $stat = $info['count'];
    $text = 'ratings: starting aggregates kept ('.intval($stat['targets']).' targets, '.intval($stat['terms']).' terms), left alone in the old table: '
        .intval($stat['voting']).' poll rows, '
        .intval($stat['foreign']).' rows of other events, '.intval($stat['orphan']).' rows of missing targets; rules dropped: '.intval($stat['dropped']).'; the subsystem is open';
    return getUpdateRow($text, true);
}

# Read the positional 6.2 definitions of one area and answer [named definitions, slot map by position, number of positions]; every refusal goes to $bad and nothing is guessed
# The four slots are caption, content, type and duty: caption 0 switches a position off, content is the default of a text, the comma list of a select or the default of a date
# Keys are field1, field2 and so on by the original position without closing gaps, options are option1, option2 in their original order, and only an exact 1 makes a field required
# Slots and option captions are trimmed; a switched off position with a known type stays in the slot map as off, with the inactive definition it becomes once a row holds data there
function getUpdateRules(string $area, mixed $text, Field $fld, array &$bad): array {
    if (!is_string($text)) {
        $bad[] = 'config/fields.php '.$area.' (not a 6.2 definition string)';
        return [[], [], 0];
    }
    $types = ['1' => 'text', '2' => 'textarea', '3' => 'select', '4' => 'datetime', '5' => 'date'];
    $list = explode('||', $text);
    $rules = [];
    $slots = [];
    foreach ($list as $pos => $item) {
        $part = array_map('trim', explode('|', $item));
        $type = $types[$part[2] ?? ''] ?? '';
        $on = $item !== '' && $part[0] !== '0';
        if (!$on && (count($part) !== 4 || $type === '')) continue;
        if (count($part) !== 4 || $type === '') {
            $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (four slots and a type from 1 to 5 are expected)';
            continue;
        }
        $name = 'field'.($pos + 1);
        $rule = ['title' => $on ? $part[0] : $name, 'intro' => '', 'type' => $type, 'default' => '', 'options' => [], 'req' => $on && $part[3] === '1', 'multi' => false];
        $rule += ['active' => $on, 'sort' => ($pos + 1) * 10];
        $items = [];
        foreach ($type === 'select' ? explode(',', $part[1]) : [] as $label) {
            $label = trim($label);
            if ($label === '' || (!$on && ($label === '0' || isset($items[$label])))) continue;
            if (isset($items[$label])) $bad[] = 'config/fields.php '.$area.' position '.($pos + 1).' (an option caption repeats)';
            $items[$label] = 'option'.(count($items) + 1);
            $rule['options']['items'][$items[$label]] = ['title' => $label, 'active' => true, 'sort' => count($items) * 10];
        }
        if ($on && $type !== 'select' && $part[1] !== '' && $part[1] !== '0') $rule['default'] = ($type === 'datetime') ? str_replace(' ', 'T', $part[1]) : $part[1];
        if ($on) $rules[$name] = $rule;
        $slots[$pos] = ['name' => $name, 'type' => $type, 'items' => $items, 'on' => $on, 'rule' => $rule];
    }
    try {
        $rules = $fld->filterFieldList($rules);
    } catch (InvalidArgumentException $err) {
        $bad[] = 'config/fields.php '.$area.' '.$err->getMessage().' (the shared check of definitions refused it)';
    }
    return [$rules, $slots, count($list)];
}

# Turn one positional 6.2 value row into the canonical JSON of its area and answer ['json' => text, 'plan' => layout, 'grow' => needs], or ['why' => reason] without stored data
# The old view indexed every position while a posted form could leave the switched off ones out, so both layouts are tried and one confirmed result is accepted
# A layout that fits the definitions as they are wins; only when none does, one that fits once they grow: a caption without an option, data at a switched off position
# The needs are field name => [caption => true] and the JSON of a growing row is provisional; $plan runs only that layout again once every need of the area is granted
# An empty part is absence; 0 is a value of a text and the placeholder of an empty choice in a select without such an option, in a date and in a switched off position
function getUpdateValue(array $rules, array $slots, int $size, string $text, Field $fld, string $plan = ''): array {
    $part = explode('|', $text);
    $plans = ['full' => range(0, max($size, count($part)) - 1), 'short' => array_keys(array_filter($slots, fn(array $v): bool => $v['on']))];
    if ($plan !== '') $plans = array_intersect_key($plans, [$plan => true]);
    $found = ['fit' => [], 'grow' => []];
    $why = '';
    foreach ($plans as $name => $order) {
        $vals = [];
        $grow = [];
        $test = $rules;
        $fail = '';
        foreach ($part as $num => $val) {
            $slot = isset($order[$num]) ? ($slots[$order[$num]] ?? null) : null;
            $spot = 'value '.($num + 1);
            $data = $val !== '' && $val !== '0';
            if ($slot === null) {
                if ($data) $fail = $spot.' holds data and has no definition';
            } elseif (!$slot['on'] && !$data) {
                continue;
            } elseif ($slot['type'] === 'select') {
                $cap = trim($val);
                if (isset($slot['items'][$cap])) $vals[$slot['name']] = $slot['items'][$cap];
                elseif ($data && $cap !== '') $grow[$slot['name']][$cap] = true;
            } elseif ($slot['type'] === 'date' || $slot['type'] === 'datetime') {
                if ($data) $vals[$slot['name']] = ($slot['type'] === 'datetime') ? str_replace(' ', 'T', $val) : $val;
            } elseif ($val !== '') {
                $vals[$slot['name']] = $val;
            }
            if ($slot !== null && $data && !isset($rules[$slot['name']])) $grow[$slot['name']] ??= [];
            if ($fail !== '') break;
        }
        foreach ($fail === '' ? $grow : [] as $key => $caps) {
            $slot = $slots[intval(substr($key, 5)) - 1];
            $test[$key] ??= $slot['rule'];
            foreach (array_keys($caps) as $cap) {
                $next = count($test[$key]['options']['items'] ?? []) + 1;
                $pick = 'option'.$next;
                $test[$key]['options']['items'][$pick] = ['title' => (string)$cap, 'active' => true, 'sort' => $next * 10];
                $vals[$key] = $pick;
            }
        }
        foreach ($test as $key => $rule) {
            $test[$key]['active'] = true;
            foreach (array_keys($rule['options']['items'] ?? []) as $pick) $test[$key]['options']['items'][$pick]['active'] = true;
        }
        $out = [];
        try {
            $errs = ($fail === '') ? $fld->checkFieldValues($test, $vals, false) : [];
            foreach ($errs as $key => $code) $fail = $key.' is refused by the shared check: '.$code;
            $out = ($fail === '') ? $fld->filterFieldValues($test, $vals) : [];
        } catch (InvalidArgumentException $err) {
            $fail = 'the grown definition '.$err->getMessage().' is refused by the shared check';
        }
        if ($fail === '') {
            $json = $out ? (string)json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '';
            $found[$grow ? 'grow' : 'fit'][$json.'|'.json_encode($grow)] ??= ['json' => $json, 'plan' => $name, 'grow' => array_map('array_keys', $grow)];
        } elseif ($name === 'full' || $plan !== '') {
            $why = $fail;
        }
    }
    $list = $found['fit'] ?: $found['grow'];
    if (count($list) === 1) return reset($list);
    return ['why' => $list ? 'the full and the short layout both fit and differ' : $why];
}

# The fields unit of the 6.3 data update: name the positional definitions of account and forum and turn every stored value row into one canonical JSON object
# Nothing is written before the whole preflight passed: a definition or a row that cannot be mapped without guessing stops the unit with the table, the id and the reason
# A caption without an option and data at a switched off position grow the definitions by a disabled option or an inactive field in row order, and the report counts them
# The unit resumes from its manifest: verified is skipped, applying and prepared continue by cursor, and a stored row has to equal its source or its target
# Definitions that are already named while positional rows exist and no manifest does stop it, because the old definitions are the only key to those rows
# Named definitions of the order area are not carried, because the release no longer ships the module that owns them
function setUpdateFields(Database $db, string $prefix): array {
    $dir = BASE_DIR.'/storage/backup/update/fields';
    $file = $dir.'/manifest.json';
    $maps = ['account' => ['users', 'field'], 'forum' => ['forum', 'field']];
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $flag = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (!class_exists('Field', false)) require_once BASE_DIR.'/core/classes/field.php';
    $fld = new Field();
    $stored = function (string $area) use ($db, $prefix, $maps): array|false {
        [$tab, $col] = $maps[$area];
        $res = $db->getSqlQuery('SELECT id, '.$col.' FROM `'.$prefix.'_'.$tab.'` WHERE '.$col.' != \'\' ORDER BY id ASC');
        $list = [];
        while ($res && ([$mid, $text] = $db->getSqlRow($res))) $list[] = [intval($mid), $text];
        return $res ? $list : false;
    };
    if (!is_array($info)) {
        if (isset($mark['fields'])) return getUpdateRow('fields: the mark is set and no manifest exists, the unit is stopped', false);
        $old = is_file(CONFIG_DIR.'/fields.php') ? ((require CONFIG_DIR.'/fields.php')['fields'] ?? []) : [];
        $named = count(array_filter(array_intersect_key($old, $maps), 'is_array'));
        $bad = [];
        $rules = [];
        $snaps = [];
        $read = [];
        $grown = ['options' => 0, 'fields' => 0];
        $done = $db->setSqlBegin();
        foreach (array_keys($maps) as $area) $read[$area] = $done ? $stored($area) : false;
        if ($done) $db->setSqlCommit();
        foreach ($maps as $area => [$tab]) {
            $base = count($bad);
            [$rules[$area], $slots, $size] = $named ? [$old[$area] ?? null, [], 0] : getUpdateRules($area, $old[$area] ?? '', $fld, $bad);
            $rows = $read[$area];
            if ($rows === false) return getUpdateRow('fields: the values of '.$prefix.'_'.$tab.' could not be read, the unit is stopped', false);
            if ($named && ($rows || !is_array($rules[$area]))) {
                $text = 'fields: config/fields.php is already in the 6.3 format while '.$prefix.'_'.$tab.' still holds positional rows and no manifest exists';
                return getUpdateRow($text.' - put the 6.2 config/fields.php back and start the update again', false);
            }
            $memo = [];
            $keep = [];
            $snaps[$area] = [];
            foreach (count($bad) > $base ? [] : $rows as [$mid, $text]) {
                $memo[$text] ??= getUpdateValue($rules[$area], $slots, $size, $text, $fld);
                if (isset($memo[$text]['why'])) $bad[] = $prefix.'_'.$tab.' '.$mid.' ('.$memo[$text]['why'].')';
                else $keep[] = [$mid, $text];
            }
            foreach ($keep as [$mid, $text]) {
                foreach ($memo[$text]['grow'] as $name => $caps) {
                    $pos = intval(substr($name, 5)) - 1;
                    if (!isset($rules[$area][$name])) $grown['fields']++;
                    $rules[$area][$name] ??= $slots[$pos]['rule'];
                    foreach ($caps as $cap) {
                        if (isset($slots[$pos]['items'][$cap])) continue;
                        $next = count($slots[$pos]['items']) + 1;
                        $slots[$pos]['items'][$cap] = 'option'.$next;
                        $rules[$area][$name]['options']['items']['option'.$next] = ['title' => (string)$cap, 'active' => false, 'sort' => $next * 10];
                        $grown['options']++;
                    }
                }
            }
            foreach ($keep as [$mid, $text]) {
                if ($memo[$text]['grow'] ?? []) $memo[$text] = getUpdateValue($rules[$area], $slots, $size, $text, $fld, $memo[$text]['plan']);
                if (isset($memo[$text]['why']) || $memo[$text]['grow']) $bad[] = $prefix.'_'.$tab.' '.$mid.' ('.($memo[$text]['why'] ?? 'the grown definitions do not fit it').')';
                else $snaps[$area][] = [$mid, $text, $memo[$text]['json']];
            }
        }
        if ($bad) return getUpdateRow('fields: the preflight found data it will not guess, nothing was written ('.count($bad).'): '.implode(', ', array_slice($bad, 0, 50)), false);
        try {
            foreach ($rules as $area => $set) $rules[$area] = $fld->filterFieldList($set);
            if ($named) $rules += array_diff_key($old, ['order' => true]);
            ksort($rules);
        } catch (InvalidArgumentException $err) {
            return getUpdateRow('fields: config/fields.php '.$area.' '.$err->getMessage().' is refused by the shared check of definitions, nothing was written', false);
        }
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('fields: '.$dir.' could not be created', false);
        $text = ['definitions.json' => json_encode(['source' => $old, 'rules' => $rules], $flag)];
        foreach ($snaps as $area => $list) $text[$area.'.json'] = json_encode($list, $flag);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'cursor' => array_fill_keys(array_keys($maps), 0), 'source' => [], 'target' => []];
        $info['count'] = array_map('count', $snaps);
        $info['grown'] = $grown;
        foreach ($text as $name => $body) {
            if (!is_string($body) || !setUpdateBackup($dir.'/'.$name, $body)) return getUpdateRow('fields: the snapshot '.$name.' could not be written', false);
            $info['source'][$name] = hash('sha256', $body);
        }
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('fields: the manifest could not be written', false);
    }
    if ($info['state'] !== 'verified') {
        foreach (array_keys($info['source']) as $name) {
            $same = is_file($dir.'/'.$name) && hash_file('sha256', $dir.'/'.$name) === $info['source'][$name];
            if (!$same) return getUpdateRow('fields: the snapshot '.$name.' does not match its manifest', false);
        }
        $info['state'] = 'applying';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('fields: the manifest could not be written', false);
        $real = [];
        foreach ($maps as $area => [$tab, $col]) {
            $list = json_decode((string)file_get_contents($dir.'/'.$area.'.json'), true);
            for ($pos = intval($info['cursor'][$area]); $pos < count($list); $pos += 500) {
                $pack = array_slice($list, $pos, 500);
                $ids = [];
                foreach ($pack as $key => $row) $ids['i'.$key] = $row[0];
                $good = $db->setSqlBegin();
                $res = $good ? $db->getSqlQuery('SELECT id, '.$col.' FROM `'.$prefix.'_'.$tab.'` WHERE id IN (:'.implode(', :', array_keys($ids)).') FOR UPDATE', $ids) : false;
                $have = [];
                while ($res && ([$mid, $text] = $db->getSqlRow($res))) $have[intval($mid)] = $text;
                foreach ($res ? $pack : [] as [$mid, $from, $into]) {
                    $cur = $have[$mid] ?? null;
                    $sql = 'UPDATE `'.$prefix.'_'.$tab.'` SET '.$col.' = :val WHERE id = :id';
                    if ($cur !== $into) $good = $cur === $from && $db->getSqlQuery($sql, ['val' => $into, 'id' => $mid]) !== false;
                    if (!$good) break;
                }
                if (!$res || !$good || !$db->setSqlCommit()) {
                    $db->setSqlRollback();
                    $text = 'fields: a batch of '.$prefix.'_'.$tab.' was refused at row '.$pos;
                    return getUpdateRow($text.' - a stored row equals neither its source nor its target or could not be written', false);
                }
                $info['cursor'][$area] = min($pos + 500, count($list));
                if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('fields: the manifest could not be written', false);
            }
            $want = [];
            foreach ($list as [$mid, $from, $into]) {
                if ($into !== '') $want[] = [$mid, $into];
            }
            $rows = $stored($area);
            if ($rows !== $want) return getUpdateRow('fields: the stored values of '.$prefix.'_'.$tab.' do not match the manifest, the definitions are not published', false);
            $real[$area.'.json'] = hash('sha256', (string)json_encode($rows, $flag));
        }
        $rules = json_decode((string)file_get_contents($dir.'/definitions.json'), true)['rules'] ?? [];
        if (((require CONFIG_DIR.'/fields.php')['fields'] ?? null) !== $rules) setUpdateFile('fields.php', $rules, [], true);
        if (((require CONFIG_DIR.'/fields.php')['fields'] ?? null) !== $rules) return getUpdateRow('fields: config/fields.php could not be published', false);
        $info['target'] = $real + ['fields.php' => hash_file('sha256', CONFIG_DIR.'/fields.php')];
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('fields: the manifest could not be written', false);
    }
    if (!setUpdateFile('update.php', ['fields' => '6.3.0'] + $mark)) return getUpdateRow('fields: config/update.php could not be written, the subsystem stays closed', false);
    $stat = $info['count'];
    $text = 'fields: named definitions published, value rows carried over: '.intval($stat['account']).' accounts, '.intval($stat['forum']).' forum posts; kept as switched off: ';
    $text .= intval($info['grown']['options'] ?? 0).' options, '.intval($info['grown']['fields'] ?? 0).' fields';
    return getUpdateRow($text.'; the subsystem is open', true);
}

# The configuration step of the 6.3 update for a 6.2 site, whose settings live in config/config_<name>.php as a variable of their own
# The site values go over the shipped source of the same name, stat into statistic and seo over global; an unshipped key stays for the data units and the next form save
# The version, the asset lists and the closed site belong to the release and the update, and a language name becomes its code
# A start module, a theme or a site logo that is not in the tree falls back to the shipped value
# Two positional formats changed after 6.2: an upload rule loses its retired eighth field adminlist and gains the guest file limit at the user one
# The upload rule takes the short form the runtime reads, and an address ban turns ip and octet count into one CIDR
# Every source ends in storage/backup/update/config once its target is written and read back, the ones without a successor as well, so the runtime never includes one again
function setUpdateConfig(): array {
    $list = glob(CONFIG_DIR.'/config_*.php') ?: [];
    if (!$list) return [];
    $dir = BASE_DIR.'/storage/backup/update/config';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('config: '.$dir.' could not be created', false);
    $maps = ['stat' => 'statistic', 'seo' => 'global'];
    $skip = ['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media', 'templ', 'header', 'chmod', 'core', 'rewrite', 'rules', 'db'];
    $langs = ['english' => 'en', 'french' => 'fr', 'german' => 'de', 'polish' => 'pl', 'russian' => 'ru', 'ukrainian' => 'uk'];
    $mods = array_map('basename', glob(BASE_DIR.'/modules/*', GLOB_ONLYDIR) ?: []);
    $plan = [];
    $left = [];
    foreach ($list as $file) {
        $name = substr(basename($file, '.php'), 7);
        $into = $maps[$name] ?? $name;
        if (in_array($name, $skip, true) || !is_file(CONFIG_DIR.'/'.$into.'.php')) $left[$name] = $file;
        else $plan[$into][$name] = $file;
    }
    $done = [];
    $bad = [];
    $note = [];
    foreach ($plan as $into => $files) {
        $base = getUpdateSource(CONFIG_DIR.'/'.$into.'.php');
        $base = ($into === 'global') ? $base : ($base[$into] ?? []);
        $site = [];
        foreach ($files as $file) $site = array_replace_recursive($site, getUpdateSource($file));
        if (!$site) {
            $left += $files;
            continue;
        }
        if ($into === 'global') {
            $site['close'] = '1';
            if (isset($site['language'])) $site['language'] = $langs[$site['language']] ?? $site['language'];
            $keep = implode(',', array_intersect(array_map('trim', explode(',', (string)($site['module'] ?? ''))), $mods));
            if ($keep === '') unset($site['module']);
            else $site['module'] = $keep;
            unset($site['version'], $site['css_f'], $site['script_f'], $site['amod']);
            if (isset($site['theme']) && !is_dir(BASE_DIR.'/templates/'.basename((string)$site['theme']))) unset($site['theme']);
            $look = BASE_DIR.'/templates/'.basename((string)($site['theme'] ?? $base['theme'] ?? '')).'/images/logos/';
            if (isset($site['site_logo']) && !is_file($look.basename((string)$site['site_logo']))) unset($site['site_logo']);
        }
        if ($into === 'lang' && isset($site['lang'])) $site['lang'] = $langs[$site['lang']] ?? $site['lang'];
        foreach ($into === 'uploads' ? $site : [] as $key => $val) {
            $part = is_string($val) ? explode('|', $val) : [];
            if (count($part) !== 12) continue;
            unset($part[7]);
            $part = array_values($part);
            $site[$key] = implode('|', $part).'|'.$part[8];
        }
        if ($into === 'security' && isset($site['blocker_ip'])) {
            $list = [];
            foreach (explode('||', (string)$site['blocker_ip']) as $item) {
                $part = explode('|', $item, 5);
                $mask = intval($part[1] ?? 0);
                if ($item === '') continue;
                if (count($part) !== 5 || !filter_var($part[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || $mask < 1 || $mask > 4) {
                    $note[] = 'the ban entry '.$part[0].' is not a 6.2 address ban and was dropped';
                    continue;
                }
                $net = implode('.', array_pad(array_slice(explode('.', $part[0]), 0, $mask), 4, '0')).'/'.($mask * 8);
                $list[] = $net.'|'.$part[2].'|'.$part[3].'|'.$part[4].'||';
            }
            $site['blocker_ip'] = implode('', $list);
        }
        $data = ($into === 'fields') ? $site : array_replace_recursive($base, $site);
        setUpdateFile($into.'.php', $data, [], true);
        $read = getUpdateSource(CONFIG_DIR.'/'.$into.'.php');
        if ((($into === 'global') ? $read : ($read[$into] ?? null)) != $data) {
            $bad[] = $into;
            continue;
        }
        foreach ($files as $file) if (!rename($file, $dir.'/'.basename($file))) $bad[] = basename($file);
        $done[] = $into;
    }
    foreach ($left as $file) if (!rename($file, $dir.'/'.basename($file))) $bad[] = basename($file);
    $text = 'config: 6.2 settings carried into '.($done ? implode(', ', $done) : 'no file').'; not carried: '.($left ? implode(', ', array_keys($left)) : 'none')
        .($note ? '; '.implode('; ', $note) : '').'; the old sources are in storage/backup/update/config';
    $out = getUpdateRow($text, true);
    $text = 'config: '.implode(', ', $bad).' could not be written or moved, the old sources stay in config/ and the update stops before the schema';
    if ($bad) $out = array_merge($out, getUpdateRow($text, false));
    return $out;
}

# The module registry of the 6.3 update, reconciled with the tree as the modules screen does it; the _modules table of a 6.2 site wins for its six switches
# A module of the tree keeps the record of config/modules.php or gets the default, node gets the record of a clean installation, a record without a module is dropped
# The panel rights of the administrators name the modules instead of the numbers of that table, and the answer names the dropped records
# Only the first run reads that table: a repeat after the mark modules keeps the switches the owner set in between, and the answer says so
function setUpdateModules(Database $db, string $prefix, bool $first = true): array {
    $mods = [];
    foreach (scandir(BASE_DIR.'/admin/modules') ?: [] as $file) if (preg_match('/^([a-z_]+)\.php$/i', $file, $matches)) $mods[$matches[1]] = 0;
    foreach (scandir(BASE_DIR.'/modules') ?: [] as $file) {
        if (!str_contains($file, '.') && (file_exists(BASE_DIR.'/modules/'.$file.'/index.php') || file_exists(BASE_DIR.'/modules/'.$file.'/admin/index.php'))) $mods[$file] = 1;
    }
    $cont = [];
    foreach ($mods as $module => $type) {
        $cont[$module] = ['lang' => '_'.strtoupper($module), 'icon' => 'puzzle', 'active' => $type ? 0 : 1, 'view' => 0, 'menu' => 1, 'group' => 0, 'side' => 0,
            'top' => 0, 'type' => $type];
    }
    if (isset($cont['node'])) {
        $cont['node'] = ['lang' => '_NODE', 'icon' => 'collection', 'active' => 1, 'view' => 0, 'menu' => 0, 'group' => 0, 'side' => 2, 'top' => 0, 'type' => 1];
    }
    $exfile = CONFIG_DIR.'/modules.php';
    $existing = file_exists($exfile) ? ((require $exfile)['modules'] ?? []) : [];
    $cont = array_merge($cont, $existing);
    $sql = 'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl';
    $tblres = $first ? $db->getSqlQuery($sql, ['tbl' => $prefix.'_modules']) : false;
    $fail = ($first && !$tblres) ? ['information_schema'] : [];
    if ($tblres && $db->getSqlRowCount($tblres) > 0) {
        $map = [];
        $result = $db->getSqlQuery('SELECT mid, title, active, view, inmenu, mod_group, blocks, blocks_c FROM `'.$prefix.'_modules`');
        if (!$result) $fail[] = $prefix.'_modules';
        while ($result && ($row = $db->getSqlRow($result))) {
            $name = $row['title'];
            $map[(string)$row['mid']] = $name;
            if (!isset($mods[$name])) continue;
            $site = ['active' => $row['active'], 'view' => $row['view'], 'menu' => $row['inmenu'], 'group' => $row['mod_group'], 'side' => $row['blocks'],
                'top' => $row['blocks_c']];
            $cont[$name] = array_replace($cont[$name], $site);
        }
        if (!empty($map)) {
            $result = $db->getSqlQuery('SELECT id, modules FROM `'.$prefix.'_admins`');
            if (!$result) $fail[] = $prefix.'_admins';
            $sql = 'UPDATE `'.$prefix.'_admins` SET modules = :modules WHERE id = :id';
            while ($result && ($row = $db->getSqlRow($result))) {
                $modules = $row['modules'] ?? '';
                $list = array_filter(array_map('trim', explode(',', $modules)), 'strlen');
                $names = [];
                foreach ($list as $val) {
                    if (ctype_digit($val) && isset($map[$val])) {
                        $names[] = $map[$val];
                    } elseif (!ctype_digit($val)) {
                        $names[] = $val;
                    }
                }
                $newmod = implode(',', array_values(array_unique($names)));
                if ($newmod !== $modules && !$db->getSqlQuery($sql, ['modules' => $newmod, 'id' => $row['id']])) $fail[] = $prefix.'_admins '.$row['id'];
            }
        }
    }
    $gone = array_keys(array_diff_key($existing, $mods));
    if (!$fail && !setUpdateFile('modules.php', array_intersect_key($cont, $mods))) $fail[] = 'config/modules.php';
    if ($fail) return getUpdateRow('module registry: '.implode(', ', $fail).' could not be read or written, the update stops before the schema', false);
    $out = $first ? [] : getUpdateRow('config/modules.php switches of the 6.2 table '.$prefix.'_modules were carried by the first run, the current ones stay', true);
    return array_merge($out, $gone ? getUpdateRow('config/modules.php records of removed modules dropped: '.implode(', ', $gone), true) : []);
}

# Take the types of config/node.php out of the four areas of their package - node.types, fields.node, uploads and ratings node.<name> - as the panel removes a type
# Publish the four files and answer the names taken out; the update keeps the names in $keep that its type table registers
# A file that cannot be written answers false; node.php goes last, so a repeat still finds the types it has to take out of the other three
function deleteUpdateTypes(array $keep): array|false {
    $ntypes = array_values(array_diff(array_keys(getUpdateSource(CONFIG_DIR.'/node.php')['node']['types'] ?? []), $keep));
    if (!$ntypes) return [];
    $pack = [];
    foreach (['fields', 'uploads', 'ratings', 'node'] as $name) $pack[$name] = getUpdateSource(CONFIG_DIR.'/'.$name.'.php')[$name] ?? [];
    foreach ($ntypes as $name) unset($pack['node']['types'][$name], $pack['fields']['node'][$name], $pack['uploads'][$name], $pack['ratings']['node.'.$name]);
    if (($pack['fields']['node'] ?? null) === []) unset($pack['fields']['node']);
    foreach ($pack as $name => $data) if (!setUpdateFile($name.'.php', $data, [], true)) return false;
    return $ntypes;
}

# The newsletter step of the 6.3 update: before the schema file drops the mails column the pending recipients of every campaign are kept in storage/backup/update/newsletter
# After the drop they move into the mail queue; an address already queued for its campaign is not written twice, so a break and a repeat neither lose nor double a recipient
# The unit resumes from its manifest like the data units: without the column and without a manifest there is nothing pending, verified is skipped
function setUpdateMails(Database $db, string $prefix, string $from, bool $move): array {
    $dir = BASE_DIR.'/storage/backup/update/newsletter';
    $file = $dir.'/manifest.json';
    $tab = '`'.$prefix.'_newsletter`';
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$move) {
        if (is_array($info)) return [];
        $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tab AND column_name = \'mails\'';
        $res = $db->getSqlQuery($sql, ['tab' => $prefix.'_newsletter']);
        if (!$res) return getUpdateRow('newsletter: '.$prefix.'_newsletter could not be read, the update stops before the schema', false);
        if (!$db->getSqlRowCount($res)) return [];
        $list = [];
        $res = $db->getSqlQuery('SELECT id, title, mails FROM '.$tab.' WHERE mails IS NOT NULL AND mails != \'\' ORDER BY id ASC');
        while ($res && ([$nid, $name, $text] = $db->getSqlRow($res))) {
            $mails = array_filter(array_map('trim', explode(',', (string)$text)), fn(string $v): bool => filter_var($v, FILTER_VALIDATE_EMAIL) !== false);
            $list[] = [intval($nid), (string)$name, array_values(array_unique($mails))];
        }
        if (!$res) return getUpdateRow('newsletter: the pending recipients could not be read, the update stops before the schema', false);
        if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('newsletter: '.$dir.' could not be created, the update stops before the schema', false);
        $text = (string)json_encode($list, JSON_UNESCAPED_UNICODE);
        $info = ['version' => '6.3.0', 'state' => 'prepared',
            'count' => ['campaigns' => count($list), 'recipients' => array_sum(array_map(fn(array $v): int => count($v[2]), $list))]];
        $info['source'] = ['recipients.json' => hash('sha256', $text)];
        if (!setUpdateBackup($dir.'/recipients.json', $text) || !setUpdateBackup($file, (string)json_encode($info))) {
            return getUpdateRow('newsletter: the snapshot of the recipients could not be written, the update stops before the schema', false);
        }
        return getUpdateRow('newsletter: '.$info['count']['recipients'].' pending recipients of '.$info['count']['campaigns'].' campaigns kept before the schema change', true);
    }
    if (!is_array($info)) return [];
    if ($info['state'] !== 'verified') {
        $same = is_file($dir.'/recipients.json') && hash_file('sha256', $dir.'/recipients.json') === ($info['source']['recipients.json'] ?? '');
        if (!$same) return getUpdateRow('newsletter: the snapshot of the recipients does not match its manifest', false);
        $find = 'SELECT id FROM `'.$prefix.'_mail` WHERE kind = \'newsletter\' AND ref = :ref AND email = :mail LIMIT 1';
        $sql = 'INSERT INTO `'.$prefix.'_mail` (kind, sender, email, title, body, ref, prio, time, ntime)'
            .' VALUES (\'newsletter\', :from, :mail, :title, \'\', :ref, 3, NOW(), NOW())';
        $mark = 'UPDATE '.$tab.' SET status = 5, audit = \'list\', expect = :num, total = :sum WHERE id = :id';
        $info['sent'] = 0;
        foreach (json_decode((string)file_get_contents($dir.'/recipients.json'), true) as [$nid, $name, $mails]) {
            $good = $db->setSqlBegin();
            foreach ($good ? $mails : [] as $mail) {
                $res = $db->getSqlQuery($find, ['ref' => $nid, 'mail' => $mail]);
                $good = $res !== false;
                if (!$good) break;
                if ($db->getSqlRow($res)) continue;
                $good = $db->getSqlQuery($sql, ['from' => $from, 'mail' => $mail, 'title' => mb_substr($name, 0, 255), 'ref' => $nid]) !== false;
                if (!$good) break;
                $info['sent']++;
            }
            $good = $good && $db->getSqlQuery($mark, ['num' => count($mails), 'sum' => count($mails), 'id' => $nid]) !== false;
            if (!$good || !$db->setSqlCommit()) {
                $db->setSqlRollback();
                return getUpdateRow('newsletter: the recipients of campaign '.$nid.' could not be queued, run the update again', false);
            }
        }
        $info['state'] = 'verified';
        if (!setUpdateBackup($file, (string)json_encode($info))) return getUpdateRow('newsletter: the manifest could not be written', false);
    }
    return getUpdateRow($prefix.'_newsletter pending recipients moved into the mail queue (rows written: '.intval($info['sent'] ?? 0).')', true);
}

# One row of the update report as data, a list so a unit answers no row, one or several and the run joins them in their order
function getUpdateRow(string $text, bool $done): array {
    return [['text' => $text, 'done' => $done]];
}

# Whether a row of the report failed; a failed row stops the run before its next step
function checkUpdateFail(array $rows): bool {
    return in_array(false, array_column($rows, 'done'), true);
}

# Whether PHP may write a configuration file the run writes, or config/ for one it creates; permissions are never changed, a file opened to everyone hands out the password
function checkUpdateWrite(string $file): bool {
    return is_writable(is_file(CONFIG_DIR.'/'.$file) ? CONFIG_DIR.'/'.$file : CONFIG_DIR);
}

# Run one SQL file of storage/update/sql with its placeholders filled and answer a row per table statement and per failed one; a DELETE names its removed rows
function setUpdateSql(Database $db, string $file, string $prefix): array {
    $path = BASE_DIR.'/storage/update/sql/'.$file;
    if (!is_file($path)) return getUpdateRow('storage/update/sql/'.$file.' is missing', false);
    $list = getSqlbatch((string)file_get_contents($path));
    if ($list['error'] !== '') return getUpdateRow($file.': '.$list['error'], false);
    $rows = [];
    foreach ($list['statements'] as $sql) {
        $sql = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$prefix, 'InnoDB', 'utf8mb4', 'utf8mb4_unicode_ci'], $sql);
        $res = $db->getSqlQuery($sql);
        $info = getSqlinfo($sql);
        $gone = ($res && $info['type'] === 'DELETE') ? ' (rows removed: '.intval($db->getSqlRowCount($res)).')' : '';
        if ($info['table'] !== '') $rows = array_merge($rows, getUpdateRow($info['table'].$gone, $res !== false));
        elseif (!$res) $rows = array_merge($rows, getUpdateRow($info['type'], false));
    }
    return $rows;
}

# Run the 6.3 update of the site config/db.php names and answer its report rows; a refusal before the preflight passed answers one failed row and changes nothing
# Then the site is closed, the 6.2 settings go over the release, the shipped admin.php takes the name of the panel file of the site and replaces its 6.2 loader
# The registry, the Node types, the upload rules, the scheduler and the newsletter snapshot follow; a failed row stops the run before the schema file
# Before the schema file a negative point balance of 6.2 becomes 0, since the schema makes the column unsigned, and the report counts those accounts
# The scheduler gains the system jobs nodepublish and nodesync it has not carried yet, maildrain moves off a priority another job holds and commentsync is dropped
# The dbbackup job gains only the missing keys of its scope, compression and retention settings, so a configured value is never overwritten
# After the schema file the data units, the feed blocks, the blocks of removed modules, the newsletter queue and the id counter of Node run
function setUpdateRun(): array {
    global $conf, $spanel;
    foreach (['db.php', 'global.php', 'security.php'] as $name) {
        if (!checkUpdateWrite($name)) return getUpdateRow('config/'.$name.' is not writable by PHP, nothing was changed', false);
    }
    $text = 'An unfinished configuration operation of the site waits in storage/backup/config; finish it on the restore screen of the panel, nothing was changed';
    if (is_file(BACKUP_DIR.'/config/marker.json')) return getUpdateRow($text, false);
    $cred = getUpdateCred();
    $pref = (string)($cred['prefix'] ?? '');
    $text = 'config/db.php names no database or no valid prefix, nothing was changed';
    if (($cred['name'] ?? '') === '' || !preg_match('/^[A-Za-z0-9_]{1,32}$/D', $pref)) return getUpdateRow($text, false);
    try {
        $db = new Database((string)($cred['host'] ?? ''), (string)($cred['uname'] ?? ''), (string)($cred['pass'] ?? ''), (string)$cred['name']);
    } catch (RuntimeException $err) {
        return getUpdateRow($err->getMessage(), false);
    }
    $stop = checkUpdateBase($db, $pref);
    if ($stop !== '') return getUpdateRow($stop, false);
    if (!setUpdateFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), ['close' => '1'])) return getUpdateRow('config/global.php could not be written', false);
    $rows = getUpdateRow('the site is closed for the data update (close = 1), open it in the settings after the result is checked', true);
    $rows = array_merge($rows, setUpdateConfig());
    $conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
    $from = is_file(BASE_DIR.'/admin.php') ? 'admin' : $spanel;
    $afile = $spanel;
    if ($afile !== $from && (!is_file(BASE_DIR.'/'.$from.'.php') || !rename(BASE_DIR.'/'.$from.'.php', BASE_DIR.'/'.$afile.'.php'))) $afile = $from;
    $left = BASE_DIR.'/'.$spanel.'.php';
    if (!in_array(strtolower($spanel), ['admin', 'index', 'setup', 'update', strtolower($afile)], true) && is_file($left)) unlink($left);
    if (!setUpdateFile('security.php', $conf['security'], ['afile' => $afile])) return array_merge($rows, getUpdateRow('config/security.php could not be written', false));
    $conf = array_merge($conf, require CONFIG_DIR.'/security.php');
    $base = ['engine' => 'InnoDB', 'charset' => 'utf8mb4', 'collate' => 'utf8mb4_unicode_ci', 'sync' => '1'];
    if (!setUpdateFile('db.php', $cred, $base)) return array_merge($rows, getUpdateRow('config/db.php could not be written', false));
    $mark = getUpdateSource(CONFIG_DIR.'/update.php')['update'] ?? [];
    $first = !isset($mark['modules']);
    $rows = array_merge($rows, setUpdateModules($db, $pref, $first));
    $keep = [];
    $res = $db->getSqlQuery('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl', ['tbl' => $pref.'_node_types']);
    $good = $res !== false;
    if ($good && $db->getSqlRowCount($res) > 0) {
        $res = $db->getSqlQuery('SELECT name FROM `'.$pref.'_node_types`');
        $good = $res !== false;
        while ($good && ([$name] = $db->getSqlRow($res))) $keep[] = $name;
    }
    $tgone = $good ? deleteUpdateTypes($keep) : [];
    $text = 'config/node.php types without a row in '.$pref.'_node_types removed with their fields, upload and rating rules: '.implode(', ', $tgone ?: []);
    if (!$good) $text = 'config/node.php: '.$pref.'_node_types could not be read, the update stops before the schema';
    if ($tgone === false) $text = 'config/node.php: the types without a row in '.$pref.'_node_types could not be taken out, the update stops before the schema';
    $good = $good && $tgone !== false;
    if (!$good || $tgone) $rows = array_merge($rows, getUpdateRow($text, $good));
    $udata = getUpdateSource(CONFIG_DIR.'/uploads.php')['uploads'] ?? [];
    $ntypes = getUpdateSource(CONFIG_DIR.'/node.php')['node']['types'] ?? [];
    $ugone = array_diff_key(array_intersect_key($udata, array_flip(['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media'])), $ntypes);
    if ($ugone && !setUpdateFile('uploads.php', array_diff_key($udata, $ugone))) {
        $rows = array_merge($rows, getUpdateRow('config/uploads.php could not be written, the update stops before the schema', false));
    } elseif ($ugone) {
        $rows = array_merge($rows, getUpdateRow('config/uploads.php rules of removed modules dropped: '.implode(', ', array_keys($ugone)), true));
    }
    $sched = getUpdateSource(CONFIG_DIR.'/scheduler.php')['scheduler'] ?? null;
    $sdone = false;
    if (is_array($sched) && !isset($sched['jobs']['maildrain'])) {
        $sched['jobs']['maildrain'] = [
            'title' => 'Mail delivery',
            'type' => 'system',
            'active' => '1',
            'system' => 'maildrain',
            'schedule' => '*/5 * * * *',
            'priority' => '8',
            'lock_timeout' => '900',
            'manual' => '1',
            'settings' => [],
        ];
        $sdone = true;
    }
    if (is_array($sched) && !isset($sched['jobs']['nodepublish'])) {
        $sched['jobs']['nodepublish'] = [
            'title' => 'Node publication',
            'type' => 'system',
            'active' => '1',
            'system' => 'nodepublish',
            'schedule' => '* * * * *',
            'priority' => '6',
            'lock_timeout' => '180',
            'manual' => '1',
            'settings' => ['limit' => '50'],
        ];
        $sdone = true;
    }
    if (is_array($sched) && !isset($sched['jobs']['nodesync'])) {
        $sched['jobs']['nodesync'] = [
            'title' => 'Node sync',
            'type' => 'system',
            'active' => '1',
            'system' => 'nodesync',
            'schedule' => '*/5 * * * *',
            'priority' => '7',
            'lock_timeout' => '180',
            'manual' => '1',
            'settings' => ['limit' => '10'],
        ];
        $sdone = true;
    }
    if (is_array($sched) && isset($sched['jobs']['commentsync'])) {
        unset($sched['jobs']['commentsync']);
        $sdone = true;
    }
    if ($first && is_array($sched) && isset($sched['jobs']['newsletter']) && is_array($sched['jobs']['newsletter'])) {
        $sched['jobs']['newsletter']['active'] = '1';
        $sched['jobs']['newsletter']['schedule'] = '*/5 * * * *';
        $sdone = true;
    }
    if (is_array($sched) && isset($sched['jobs']['dbbackup']) && is_array($sched['jobs']['dbbackup'])) {
        $bset = [
            'include' => '*',
            'exclude' => 'ipb_*',
            'schemaonly' => 'MRG_MyISAM,MERGE,HEAP,MEMORY',
            'compress' => 'auto',
            'keep' => '0',
            'allow_incomplete' => '0',
        ];
        if (!isset($sched['jobs']['dbbackup']['settings']) || !is_array($sched['jobs']['dbbackup']['settings'])) $sched['jobs']['dbbackup']['settings'] = [];
        foreach ($bset as $key => $val) {
            if (isset($sched['jobs']['dbbackup']['settings'][$key])) continue;
            $sched['jobs']['dbbackup']['settings'][$key] = $val;
            $sdone = true;
        }
    }
    if (is_array($sched) && isset($sched['jobs']['maildrain']) && is_array($sched['jobs']['maildrain'])) {
        $held = [];
        foreach ($sched['jobs'] as $jkey => $jval) if ($jkey !== 'maildrain' && is_array($jval)) $held[] = (int)($jval['priority'] ?? 100);
        if (in_array((int)($sched['jobs']['maildrain']['priority'] ?? 100), $held, true)) {
            $prio = 1;
            while (in_array($prio, $held, true)) $prio++;
            $sched['jobs']['maildrain']['priority'] = (string)$prio;
            $sdone = true;
        }
    }
    $text = ' could not be written, the update stops before the schema';
    if ($sdone && !setUpdateFile('scheduler.php', $sched)) $rows = array_merge($rows, getUpdateRow('config/scheduler.php'.$text, false));
    $done = !$first || checkUpdateFail($rows) || setUpdateFile('update.php', ['modules' => '6.3.0'] + $mark);
    if (!$done) $rows = array_merge($rows, getUpdateRow('config/update.php'.$text, false));
    $ndata = getUpdateSource(CONFIG_DIR.'/newsletter.php')['newsletter'] ?? [];
    $nset = $ndata + ['abort' => '10', 'bouncemax' => '2', 'breakwin' => '100', 'canary' => '100', 'canarymin' => '500'];
    if ($nset !== $ndata && !setUpdateFile('newsletter.php', $nset)) $rows = array_merge($rows, getUpdateRow('config/newsletter.php'.$text, false));
    $ndata = getUpdateSource(CONFIG_DIR.'/node.php')['node'] ?? [];
    $nedit = is_array($ndata['limits'] ?? null) && !array_key_exists('edit', $ndata['limits']);
    if ($nedit) $ndata['limits']['edit'] = 600;
    if ($nedit && !setUpdateFile('node.php', $ndata, [], true)) $rows = array_merge($rows, getUpdateRow('config/node.php'.$text, false));
    $rows = array_merge($rows, setUpdateMails($db, $pref, (string)$conf['adminmail'], false));
    $sql = 'SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl AND column_name IN (\'points\', \'user_points\')';
    $res = $db->getSqlQuery($sql, ['tbl' => $pref.'_users']);
    $pcol = $res ? (string)($db->getSqlRow($res)[0] ?? '') : '';
    $res = ($res && $pcol !== '') ? $db->getSqlQuery('UPDATE `'.$pref.'_users` SET `'.$pcol.'` = 0 WHERE `'.$pcol.'` < 0') : $res;
    $text = $pref.'_users negative point balances set to 0 before the schema makes the column unsigned (accounts: ';
    $rows = array_merge($rows, getUpdateRow($text.(($res && $pcol !== '') ? intval($db->getSqlRowCount($res)) : 0).')', $res !== false));
    $text = 'the update stopped before the schema file: neither the schema file nor a data unit ran, correct the refusal above and run the update again';
    if (checkUpdateFail($rows)) return array_merge($rows, getUpdateRow($text, false));
    $ddl = setUpdateSql($db, 'table_update6_3.sql', $pref);
    $rows = array_merge($rows, $ddl);
    $text = 'the update stopped at the schema file: no data unit ran and no mark was written, correct the failed statement and run the update again';
    if ($ddl === [] || checkUpdateFail($ddl)) return array_merge($rows, getUpdateRow($text, false));
    $rows = array_merge($rows, setUpdatePoints($db, $pref), setUpdateRatings($db, $pref), setUpdateFields($db, $pref));
    $rdata = getUpdateSource(CONFIG_DIR.'/rss.php')['rss'] ?? [];
    if ($rdata !== [] && (isset($rdata['temp']) || !isset($rdata['bytes'], $rdata['redirects'], $rdata['timeout']))) {
        unset($rdata['temp']);
        $rdata += ['bytes' => '2097152', 'redirects' => '3', 'timeout' => '10'];
        if (!setUpdateFile('rss.php', $rdata)) $rows = array_merge($rows, getUpdateRow('config/rss.php could not be written', false));
    }
    $rnum = $db->getSqlQuery('UPDATE `'.$pref.'_blocks` SET content = \'\', time = \'0\' WHERE url != \'\'');
    $rows = array_merge($rows, getUpdateRow($pref.'_blocks RSS bodies cleared for Markdown (rows: '.($rnum ? $db->getSqlRowCount($rnum) : 0).')', $rnum !== false));
    $bpars = [];
    foreach (['news', 'pages', 'faq', 'files', 'jokes', 'jokes_random', 'links', 'center', 'center_media', 'center_plus'] as $i => $one) $bpars['b'.$i] = $one.'.php';
    $bsql = ' WHERE status = 1 AND bfile IN (:'.implode(', :', array_keys($bpars)).')';
    $res = $db->getSqlQuery('SELECT DISTINCT bfile FROM `'.$pref.'_blocks`'.$bsql.' ORDER BY bfile', $bpars);
    $boff = $res ? array_column($db->getSqlRows($res) ?: [], 0) : [];
    $good = $res !== false && ($boff === [] || $db->getSqlQuery('UPDATE `'.$pref.'_blocks` SET status = 0'.$bsql, $bpars) !== false);
    $rows = array_merge($rows, getUpdateRow($pref.'_blocks of removed modules switched off: '.($boff ? implode(', ', $boff) : 'none'), $good));
    $rows = array_merge($rows, setUpdateMails($db, $pref, (string)$conf['adminmail'], true));
    [$acount] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$pref.'_users` WHERE `avatar` LIKE \'default/%\''));
    $rows = array_merge($rows, getUpdateRow($pref.'_users avatar migration (legacy rows left: '.(int)$acount.')', (int)$acount === 0));
    $pars = [];
    foreach (['news', 'pages', 'faq', 'help', 'jokes', 'content', 'links', 'files', 'media'] as $i => $one) $pars['t'.$i] = $pref.'_'.$one;
    $sql = 'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND EXTRA LIKE \'%auto_increment%\''
        .' AND TABLE_NAME IN (:'.implode(', :', array_keys($pars)).')';
    $res = $db->getSqlQuery($sql, $pars);
    $good = $res !== false;
    $top = 0;
    foreach (($good ? $db->getSqlRows($res) : false) ?: [] as $row) {
        $got = $db->getSqlQuery('SELECT COALESCE(MAX(`'.$row[1].'`), 0) FROM `'.$row[0].'`');
        $good = $good && $got !== false;
        $top = max($top, $got ? intval($db->getSqlRow($got)[0] ?? 0) : 0);
    }
    $got = $good ? $db->getSqlQuery('SHOW CREATE TABLE `'.$pref.'_nodes`') : false;
    $next = ($got && preg_match('/\bAUTO_INCREMENT=(\d+)/', $db->getSqlRow($got)[1] ?? '', $hit)) ? intval($hit[1]) : 1;
    $good = $got !== false && ($top < $next || $db->getSqlQuery('ALTER TABLE `'.$pref.'_nodes` AUTO_INCREMENT = '.($top + 1)) !== false);
    $rows = array_merge($rows, getUpdateRow($pref.'_nodes new ids start above the highest id of the removed sections ('.$top.')', $good));
    if (checkUpdateFail($rows)) return $rows;
    $gone = 0;
    foreach (glob(BACKUP_DIR.'/update/*/*') ?: [] as $file) if (basename($file) !== 'manifest.json' && unlink($file)) $gone++;
    $rows = array_merge($rows, getUpdateRow('the snapshots of the update are deleted ('.$gone.' files), the manifests stay in storage/backup/update', true));
    return $rows;
}

# The link tags of the admin theme gathered as the core gathers them for the same card: the icon, the stylesheets of its vendor packages and its own
function getUpdateLinks(Template $tpl): string {
    $base = 'templates/admin/';
    $out = [$tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => $base.'images/favicon.svg', 'type' => 'image/svg+xml', 'title' => ''])];
    $list = glob($base.'*.css') ?: [];
    foreach (glob($base.'assets/vendor/*/', GLOB_ONLYDIR) ?: [] as $sub) $list = array_merge($list, glob($sub.'*.css') ?: [], glob($sub.'*/*.css') ?: []);
    foreach (array_merge($list, glob($base.'assets/css/*.css') ?: []) as $file) {
        $out[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => $file, 'type' => '', 'title' => '']);
    }
    return implode("\n", $out);
}

# Render the first stage on the login card of the admin theme: what the run does, the report of a run with its verdict, and the button that starts it
function setUpdatePage(array $rows): void {
    global $conf;
    $tpl = new Template('admin');
    $text = 'This page brings a SLAED CMS 6.2 site to 6.3 and runs without a login at the risk of the owner. It closes the site, carries the configuration over,'
        .' runs the schema file and the data units. Then update.php carries the content of the removed modules into Node behind the panel login.';
    $cont = $tpl->getHtmlFrag('alert', ['text' => $text]);
    if ($rows) {
        $list = '';
        foreach ($rows as $row) {
            $mark = $tpl->getHtmlFrag('inline-badge', ['label' => $row['done'] ? 'OK' : 'Error', 'is_success' => $row['done'], 'is_danger' => !$row['done']]);
            $cells = [['has_content_text' => true, 'content_text' => $row['text']], ['content_html' => $mark, 'is_col_status' => true]];
            $list .= $tpl->getHtmlFrag('table-row', ['cells_html' => $tpl->getHtmlFrag('table-cells', ['cells' => $cells])]);
        }
        $cont .= $tpl->getHtmlFrag('table', ['head' => [['content' => 'Step'], ['content' => 'Result', 'is_col_status' => true]], 'rows_html' => $list, 'disable_sort' => true]);
        $fail = 'The update stopped. Correct the failed row and run it again; every step resumes where it stopped.';
        $done = 'The update finished. Open update.php again to carry the content of the removed modules into Node.';
        $cont .= $tpl->getHtmlFrag('alert', checkUpdateFail($rows) ? ['type' => 'error', 'text' => $fail] : ['type' => 'success', 'text' => $done]);
    }
    $hide = $tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'update', 'input_attr' => '']);
    $text = 'Run the update';
    $cont .= $tpl->getHtmlFrag('post-button', ['action' => 'update.php', 'hidden' => $hide, 'icon_name' => 'play-circle', 'title' => $text, 'label' => $text]);
    $meta = $tpl->getHtmlFrag('head-title', ['title' => 'SLAED CMS 6.3 update'])."\n".$tpl->getHtmlFrag('head-meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
    $logo = 'templates/admin/images/logos/'.basename((string)($conf['admin_logo'] ?? ''));
    if (!is_file(BASE_DIR.'/'.$logo)) $logo = 'templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg';
    $link = $tpl->getHtmlFrag('link', ['href' => 'https://slaed.net', 'title' => 'SLAED CMS', 'label' => 'SLAED CMS', 'is_blank' => true]);
    header('Content-Type: text/html; charset=utf-8');
    echo $tpl->getHtmlPage('login', ['lang' => 'en', 'mode' => 'auto', 'meta' => $meta, 'links' => getUpdateLinks($tpl), 'scripts' => '', 'adlogo' => $logo,
        'adalt' => 'SLAED CMS', 'adtitle' => 'SLAED CMS', 'license' => $link.' © 2005-'.date('Y').' Eduard Laas. Released under MIT License.', 'content' => $cont]);
}

# The first stage runs before the core and without a login, at the risk of the owner: a 6.2 site cannot boot the core, whose configuration loader reads only arrays
# It opens while the units points, ratings and fields have not all left their marks in config/update.php, and always for op=update, which a repeat uses
# Otherwise the core boots and the panel login guards the migration of the removed modules into Node
$umark = is_file(BASE_DIR.'/config/update.php') ? ((include BASE_DIR.'/config/update.php')['update'] ?? []) : [];
if (($_REQUEST['op'] ?? '') === 'update' || !isset($umark['points'], $umark['ratings'], $umark['fields'])) {
    define('SETUP_FILE', true);
    define('FUNC_FILE', true);
    define('CONFIG_DIR', BASE_DIR.'/config');
    define('BACKUP_DIR', BASE_DIR.'/storage/backup');
    define('LOGS_DIR', BASE_DIR.'/storage/logs');
    $conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
    require_once BASE_DIR.'/core/admin.php';
    require_once BASE_DIR.'/core/classes/filemanager.php';
    require_once BASE_DIR.'/core/classes/logger.php';
    require_once BASE_DIR.'/core/classes/pdo.php';
    require_once BASE_DIR.'/core/classes/template.php';
    require_once BASE_DIR.'/lang/en.php';
    ini_set('display_errors', $conf['security']['error'] > 0 ? '1' : '0');
    error_reporting([0, E_ALL ^ E_NOTICE, E_ALL][intval($conf['security']['error'])] ?? 0);
    set_time_limit(0);
    $spanel = (string)(getUpdateSource(CONFIG_DIR.'/config_security.php')['afile'] ?? $conf['security']['afile'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $spanel) || in_array(strtolower($spanel), ['index', 'setup', 'update'], true)) $spanel = 'admin';
    setUpdatePage((($_POST['op'] ?? '') === 'update') ? setUpdateRun() : []);
    exit;
}
define('MODULE_FILE', true);
require_once BASE_DIR.'/core/system.php';
require_once BASE_DIR.'/core/classes/filemanager.php';
Cache::setHeaders();
if (!isAdmin(true)) setExit(_ACCESSDENIED);
$conf['name'] = '';
$fail = '';
if (getVar('post', 'op', 'var') === 'run') $fail = checkSiteToken(getVar('post', 'token', 'var'), 'update') ? setMigrateRun() : _ACCESSDENIED;
setMigratePage($fail);
