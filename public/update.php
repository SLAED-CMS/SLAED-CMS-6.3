<?php
# Author: Eduard Laas
# 2005 - 2026 SLAED
# License: MIT
# Website: slaed.net

$sgtime = microtime(true);
# The update in the document root: the project is the folder above it; a test or a tool may name both folders first
if (!defined('BASE_DIR')) define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
if (!defined('PUBLIC_DIR')) define('PUBLIC_DIR', str_replace('\\', '/', __DIR__));

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
        'jokes' => ['type' => 'jokes', 'ext' => null, 'rate' => 'ratetot', 'home' => false, 'poll' => false, 'pin' => false],
        'media' => ['type' => 'media', 'ext' => null, 'rate' => 'votes', 'home' => true, 'poll' => false, 'pin' => false],
    ];
}

# The working directory of the migration under the backup root, which the web server never serves: it holds the manifest
function getMigrateDir(): string {
    return BACKUP_DIR.'/update/node';
}

# Read the manifest of the migration, or the empty one of a first run
function getMigrateState(): array {
    $file = getMigrateDir().'/manifest.json';
    $data = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    $base = ['version' => 1, 'stash' => [], 'types' => [], 'data' => [], 'active' => [], 'notes' => [], 'counter' => 0, 'routes' => []];
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
        $role = ['links' => 'link', 'files' => 'download', 'media' => 'download'][$mod] ?? '';
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

# The path of one file a module left, in the folder of its type the operator moved it into or still in the 6.2 folder of the module, or an empty string
function getMigratePath(string $mod, string $rel): string {
    if ($rel === '' || str_contains($rel, "\0") || preg_match('#(?:^|/)\.{0,2}(?:/|$)#', $rel)) return '';
    foreach ([getUploadFolder(getMigrateMap()[$mod]['type'], true), $mod] as $dir) if (is_file(UPLOADS_DIR.'/'.$dir.'/'.$rel)) return UPLOADS_DIR.'/'.$dir.'/'.$rel;
    return '';
}

# Move the categories, comments and favorites of every module to the key ~<module>, out of the way of the name check of a new type; no file moves
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
    foreach ($todo as $mod) {
        deleteCategoryMap($mod);
        $state['stash'][$mod] = true;
    }
    setMigrateState($state);
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

# Rewrite direct addresses of files in uploads/$dir, BB and Markdown, into attachments, names unchanged; inside [usehtml] a source becomes the file address of material $nid
function getMigrateAttach(string $text, string $dir, int $nid, Closure $have): string {
    global $conf;
    if (stripos($text, 'uploads/'.$dir.'/') === false) return $text;
    $host = preg_quote((string)preg_replace('#^www\.#i', '', (string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST)), '#');
    $addr = '(?:\./|/|https?://(?:www\.)?'.$host.'/)?uploads/'.preg_quote($dir, '#').'/([A-Za-z0-9_./%-]+)';
    $name = function (string $rel) use ($have): string {
        $one = rawurldecode($rel);
        $good = preg_match('/^[A-Za-z0-9_\-. ]+$/D', $one) && trim($one, '. ') !== '' && $have($one);
        return $good ? $one : '';
    };
    $tag = function (string $file, string $align, string $title, bool $full): string {
        $title = (string)preg_replace('/\\\\([!-\/:-@\[-`{-~])/', '$1', $title);
        $title = trim((string)preg_replace('/\s+/u', ' ', (string)preg_replace('/[^\pL0-9_\-. "]+/u', ' ', $title)));
        return '[attach='.$file.' align='.($align !== '' ? strtolower($align) : 'none').' title='.($title !== '' ? $title : 'title').($full ? ' size=full' : '').']';
    };
    $keep = [];
    $hide = function (string $src, string $find, ?Closure $when = null) use (&$keep): string {
        return preg_replace_callback($find, function (array $m) use (&$keep, $when): string {
            if ($when && !$when($m[0])) return $m[0];
            $keep[] = $m[0];
            return "\x01".(count($keep) - 1)."\x01";
        }, $src) ?? $src;
    };
    $text = $hide($text, '#\[(code|php|usehtml|usephp)\b[^\]]*\].*?\[/\1\]#si');
    $text = $hide($text, '/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\1[`~]*[ \t]*$|\z)/ms');
    $text = $hide($text, '/(?<![`\\\\])(`+)(?!`)(?:[^\n]|\n(?![ \t]*\n))+?(?<!`)\1(?!`)/');
    $img = '\[img(?:=([a-z]+))?(?: alt=([^\]]*))?\]';
    $text = preg_replace_callback('#\[url='.$addr.'\]\s*'.$img.$addr.'\[/img\]\s*\[/url\]#i', function (array $m) use ($name, $tag): string {
        $file = ($m[4] === 'thumb/'.$m[1]) ? $name($m[1]) : '';
        if ($file !== '') return $tag($file, $m[2], $m[3], false);
        $file = $name($m[4]);
        return ($file !== '') ? $tag($file, $m[2], $m[3], true) : $m[0];
    }, $text) ?? $text;
    $text = preg_replace_callback('#\[!\[([^\]\n]*)\]\('.$addr.'\)\]\('.$addr.'\)#i', function (array $m) use ($name, $tag): string {
        $file = ($m[2] === 'thumb/'.$m[3]) ? $name($m[3]) : '';
        if ($file !== '') return $tag($file, '', $m[1], false);
        $file = $name($m[2]);
        return ($file !== '') ? $tag($file, '', $m[1], true) : $m[0];
    }, $text) ?? $text;
    $text = $hide($text, '#\[url(?:=[^\]]*)?\](?:(?!\[/url\]).)*?\[/url\]#si', fn(string $v): bool => stripos($v, '[img') !== false);
    $text = $hide($text, '#\[(?:[^\[\]\n]|\n(?![ \t]*\n))*?!\[[^\]\n]*\]\([^)\s]*\)(?:[^\[\]\n]|\n(?![ \t]*\n))*\]\([^)\s]*\)#');
    $text = preg_replace_callback('#'.$img.$addr.'\[/img\]#i', function (array $m) use ($name, $tag): string {
        $thumb = str_starts_with($m[3], 'thumb/');
        $file = $name($thumb ? substr($m[3], 6) : $m[3]);
        return ($file !== '') ? $tag($file, $m[1], $m[2], !$thumb) : $m[0];
    }, $text) ?? $text;
    $text = preg_replace_callback('#!\[([^\]\n]*)\]\('.$addr.'\)#i', function (array $m) use ($name, $tag): string {
        $thumb = str_starts_with($m[2], 'thumb/');
        $file = $name($thumb ? substr($m[2], 6) : $m[2]);
        return ($file !== '') ? $tag($file, '', $m[1], !$thumb) : $m[0];
    }, $text) ?? $text;
    $link = fn(array $m): string => ($name($m[1]) !== '') ? $tag($name($m[1]), '', (stripos($m[2] ?? '', 'uploads/') === false) ? ($m[2] ?? '') : '', false) : $m[0];
    $text = preg_replace_callback('#\[url='.$addr.'\]([^\[]*)\[/url\]#i', $link, $text) ?? $text;
    $text = preg_replace_callback('#\[url\]'.$addr.'\[/url\]#i', $link, $text) ?? $text;
    $text = preg_replace_callback('#(?<![!\\\\])\[((?:\\\\.|[^\]\[\\\\\n*_~`])*)\]\('.$addr.'\)#i', fn(array $m): string => $link([$m[0], $m[2], $m[1]]), $text) ?? $text;
    $text = preg_replace_callback('#<(https?://(?:www\.)?'.$host.'/uploads/'.preg_quote($dir, '#').'/([A-Za-z0-9_./%-]+))>#i', fn(array $m): string => $link([$m[0], $m[2]]), $text)
        ?? $text;
    for ($i = count($keep) - 1; $i >= 0; $i--) $text = str_replace("\x01".$i."\x01", $keep[$i], $text);
    if ($nid < 1) return $text;
    return preg_replace_callback('#\[usehtml\].*?\[/usehtml\]#si', function (array $blk) use ($addr, $name, $nid): string {
        return preg_replace_callback('#((?:src|href)\s*=\s*(?:"|\'|&\#0?34;|&quot;)?)'.$addr.'#i', function (array $m) use ($name, $nid): string {
            $thumb = str_starts_with($m[2], 'thumb/');
            $file = $name($thumb ? substr($m[2], 6) : $m[2]);
            return ($file !== '') ? $m[1].'index.php?go=file&own=node&id='.$nid.'&key='.rawurlencode($file).($thumb ? '&thumb=1' : '') : $m[0];
        }, $blk[0]) ?? $blk[0];
    }, $text) ?? $text;
}

# Point direct addresses into uploads/$dir of another owner at the file address of owner $own and target $find; quoted code and a name answered 0 keep the address (410)
function getMigrateForeign(string $text, string $dir, string $own, Closure $find): string {
    global $conf;
    if (stripos($text, 'uploads/'.$dir.'/') === false) return $text;
    $host = preg_quote((string)preg_replace('#^www\.#i', '', (string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST)), '#');
    $addr = '(?<![A-Za-z0-9_./:%-])(?:\./|/|https?://(?:www\.)?'.$host.'/)?uploads/'.preg_quote($dir, '#').'/([A-Za-z0-9_./%-]+)';
    $keep = [];
    $code = ['#\[(code|php)\b[^\]]*\].*?\[/\1\]#si', '/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\1[`~]*[ \t]*$|\z)/ms',
        '/(?<![`\\\\])(`+)(?!`)(?:[^\n]|\n(?![ \t]*\n))+?(?<!`)\1(?!`)/'];
    $text = preg_replace_callback($code, function (array $m) use (&$keep): string {
        $keep[] = $m[0];
        return "\x01".(count($keep) - 1)."\x01";
    }, $text) ?? $text;
    $text = preg_replace_callback('#'.$addr.'#i', function (array $m) use ($own, $find): string {
        $rel = rawurldecode($m[1]);
        $thumb = str_starts_with($rel, 'thumb/');
        $name = $thumb ? substr($rel, 6) : $rel;
        $nid = (preg_match('/^[A-Za-z0-9_\-. ]+$/D', $name) && trim($name, '. ') !== '') ? $find($name) : 0;
        return ($nid > 0) ? './index.php?go=file&own='.$own.'&id='.$nid.'&key='.rawurlencode($name).($thumb ? '&thumb=1' : '') : $m[0];
    }, $text) ?? $text;
    for ($i = count($keep) - 1; $i >= 0; $i--) $text = str_replace("\x01".$i."\x01", $keep[$i], $text);
    return $text;
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


# Convert the raw HTML of a 6.2 text into Markdown: HTML becomes the BB of getMigrateMarkdown() or a finished Markdown piece behind a \x03 or \x04 marker it passes through
function getMigrateHtml(string $text, bool $trust, array &$seen = []): string {
    $text = str_replace(["\r\n", "\r", "\x03", "\x04"], ["\n", "\n", '', ''], $text);
    $keep = [];
    $mark = [];
    $put = function (string $val, string $kind) use (&$mark): string {
        $mark[] = $val;
        return $kind.(count($mark) - 1).$kind;
    };
    $text = preg_replace_callback('#\[(code|php|usehtml|usephp)\b[^\]]*\].*?\[/\1\]#si', function (array $mat) use (&$keep): string {
        $keep[] = $mat[0];
        return "\x01".(count($keep) - 1)."\x01";
    }, $text) ?? $text;
    $text = preg_replace('/^[ \t]+/m', '', $text) ?? $text;
    if ($trust) $text = getMigrateBlocks($text, $keep);
    $text = preg_replace('#<!--.*?-->#s', '', $text) ?? $text;
    do {
        $text = preg_replace_callback('#<font\b([^>]*)>((?:(?!<font\b).)*?)</font>#si', function (array $mat): string {
            $color = preg_match('/color\s*=\s*["\']?(#?[a-z0-9]+)/i', $mat[1], $hit) ? $hit[1] : '';
            return ($color !== '') ? '[color='.$color.']'.$mat[2].'[/color]' : $mat[2];
        }, $text, -1, $num) ?? $text;
    } while ($num > 0);
    $text = preg_replace_callback('#<(?:a|img)\b[^>]*>#i', fn(array $m): string => str_ireplace(['&#034;', '&#34;', '&quot;'], '"', $m[0]), $text) ?? $text;
    $text = preg_replace_callback('#<a\b([^>]*)>(.*?)</a>#si', function (array $mat): string {
        $href = preg_match('/href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $mat[1], $hit) ? trim($hit[1] ?: ($hit[2] ?? '') ?: ($hit[3] ?? '')) : '';
        $href = strtr($href, ['[' => '%5B', ']' => '%5D']);
        $label = trim($mat[2]);
        if ($href === '' || preg_match('/^(?:javascript|vbscript|data):/i', trim(html_entity_decode($href)))) return $label;
        return ($label === '' || $label === $href) ? '[url]'.$href.'[/url]' : '[url='.$href.']'.$label.'[/url]';
    }, $text) ?? $text;
    $text = preg_replace_callback('#<img\b([^>]*)>#i', function (array $mat): string {
        $src = preg_match('/src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $mat[1], $hit) ? trim($hit[1] ?: ($hit[2] ?? '') ?: ($hit[3] ?? '')) : '';
        return ($src !== '') ? '[img]'.strtr($src, ['[' => '%5B', ']' => '%5D']).'[/img]' : '';
    }, $text) ?? $text;
    $text = preg_replace_callback('#<(code|tt|kbd)\b[^>]*>(.*?)</\1>#si', function (array $mat) use ($put): string {
        $body = html_entity_decode(strip_tags($mat[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($body) === '' || str_contains($body, "\n")) return $mat[2];
        $tick = str_repeat('`', max(array_map('strlen', preg_split('/[^`]+/', $body) ?: [''])) + 1);
        $pad = (str_starts_with($body, '`') || str_ends_with($body, '`') || (str_starts_with($body, ' ') && str_ends_with($body, ' '))) ? ' ' : '';
        return $put($tick.$pad.$body.$pad.$tick, "\x04");
    }, $text) ?? $text;
    $pairs = ['b|strong' => 'b', 'i|em' => 'i', 'u|ins' => 'u', 's|strike|del' => 's'];
    foreach ($pairs as $tags => $bb) $text = preg_replace(['#<(?:'.$tags.')\b[^>]*>#i', '#</(?:'.$tags.')>#i'], ['['.$bb.']', '[/'.$bb.']'], $text) ?? $text;
    do {
        $text = preg_replace_callback('#<(ul|ol)\b[^>]*>((?:(?!<(?:ul|ol)\b).)*?)</\1>#si', function (array $mat) use ($put): string {
            $items = preg_split('#<li\b[^>]*>#i', $mat[2]) ?: [];
            array_shift($items);
            $out = [];
            foreach ($items as $i => $one) {
                $head = (strtolower($mat[1]) === 'ol') ? ($i + 1).'. ' : '- ';
                $lines = array_map('ltrim', explode("\n", trim(str_ireplace('</li>', '', $one))));
                $out[] = $put($head, "\x03").implode("\n".$put(str_repeat(' ', strlen($head)), "\x03"), $lines);
            }
            return "\n\n".implode("\n", $out)."\n\n";
        }, $text, -1, $num) ?? $text;
    } while ($num > 0);
    $text = preg_replace_callback('#<h([1-6])\b[^>]*>(.*?)</h\1>#si', fn(array $m): string => "\n\n".$put(str_repeat('#', intval($m[1])).' ', "\x03")
        .trim(preg_replace('/\s+/', ' ', $m[2]) ?? '')."\n\n", $text) ?? $text;
    $text = preg_replace_callback('#<p\b[^>]*align\s*=\s*["\']?center[^>]*>(.*?)</p>#si', fn(array $m): string => "\n\n[center]".trim($m[1])."[/center]\n\n", $text) ?? $text;
    $from = ['#<center\b[^>]*>#i', '#</center>#i', '#<blockquote\b[^>]*>#i', '#</blockquote>#i', '#<hr\b[^>]*>#i', '#</?p\b[^>]*>#i', '#</?(?!br\b)[a-z][a-z0-9]*\b[^>]*>#i'];
    $text = preg_replace($from, ['[center]', '[/center]', "\n[quote]", "[/quote]\n", "\n[hr]\n", "\n\n", ''], $text) ?? $text;
    $text = preg_replace(['/\n[ \t]+\n/', '/\n{3,}/'], ["\n\n", "\n\n"], $text) ?? $text;
    $text = getMigrateLocal($text);
    $text = preg_replace_callback("/\x01([0-9]+)\x01/", fn(array $m): string => $keep[intval($m[1])], $text) ?? $text;
    $text = getMigrateMarkdown($text, true, $seen);
    $text = preg_replace_callback("/([\x03\x04])([0-9]+)\\1/", fn(array $m): string => $mark[intval($m[2])], $text) ?? $text;
    return trim($text);
}

# Convert stored BB into Markdown by docs/PARSER.md, "Texts of the BB era": a BB era text escapes its text runs, a Markdown one keeps them; $seen counts the forms met
function getMigrateMarkdown(string $text, bool $esc, array &$seen = []): string {
    $orig = $text;
    $text = str_replace(["\r\n", "\r", "\x01", "\x02", "\x05"], ["\n", "\n", '', '', ''], $text);
    $flat = $text;
    $keep = [];
    $wide = [];
    $put = function (string $val, bool $block = false) use (&$keep, &$wide): string {
        $keep[] = $val;
        if ($block) $wide[count($keep) - 1] = true;
        return $block ? "\x05\x01".(count($keep) - 1)."\x01\x05" : "\x01".(count($keep) - 1)."\x01";
    };
    $see = function (string $key, int $num = 1) use (&$seen): void {
        $seen[$key] = ($seen[$key] ?? 0) + $num;
    };
    $text = preg_replace_callback('#\[(usehtml|usephp)\].*?\[/\1\]#si', function (array $m) use ($put, $see): string {
        $see(strtolower($m[1]));
        return $put($m[0]);
    }, $text) ?? $text;
    $src = $text;
    $text = preg_replace_callback('#\[(code|php)(=[^\]\n]*)?\](.*?)\[/\1\]#si', function (array $m) use ($src, $put, $see): string {
        [$all, $at] = $m[0];
        $name = strtolower($m[1][0]);
        $attr = ($m[2][1] ?? -1) >= 0 ? substr($m[2][0], 1) : null;
        if ($attr !== null && $name === 'php') return $all;
        $see($name);
        $body = html_entity_decode((string)preg_replace('#<br\s*/?>[ \t]*\n?#i', "\n", $m[3][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $from = strrpos(substr($src, 0, $at), "\n");
        $head = substr($src, ($from === false) ? 0 : $from + 1, $at - (($from === false) ? 0 : $from + 1));
        $stop = strpos($src, "\n", $at + strlen($all));
        $tail = substr($src, $at + strlen($all), (($stop === false) ? strlen($src) : $stop) - $at - strlen($all));
        $run = max(array_map('strlen', preg_split('/[^`]+/', $body) ?: ['']));
        $alone = preg_match('#^(?:\s|<br\s*/?>)*$#i', $head) && preg_match('#^(?:\s|<br\s*/?>)*$#i', $tail);
        if (trim($body) === '') return '';
        if (!str_contains($body, "\n") && !$alone) {
            $tick = str_repeat('`', $run + 1);
            $pad = (str_starts_with($body, '`') || str_ends_with($body, '`') || (str_starts_with($body, ' ') && str_ends_with($body, ' '))) ? ' ' : '';
            return $put($tick.$pad.$body.$pad.$tick);
        }
        $body = rtrim((string)preg_replace('/^(?:[ \t]*\n)+/', '', $body));
        $lang = ($name === 'php') ? 'php' : (string)preg_replace('/[^A-Za-z0-9_+#.-]+/', '', (string)$attr);
        $tick = str_repeat('`', max(3, $run + 1));
        return $put($tick.$lang."\n".$body."\n".$tick, true);
    }, $text, -1, $num, PREG_OFFSET_CAPTURE) ?? $text;
    if (!$esc) {
        $text = preg_replace_callback('/^ {0,3}(`{3,}|~{3,})[^\n]*\n.*?(?:^ {0,3}\1[`~]*[ \t]*$|\z)/ms', fn(array $m): string => $put($m[0]), $text) ?? $text;
        $text = preg_replace_callback('/(?<!`)(`+)(?!`)(?:[^\n]|\n(?![ \t]*\n))+?(?<!`)\1(?!`)/', fn(array $m): string => $put($m[0]), $text) ?? $text;
    }
    $val = '[^\]\s](?:[^\]\n]*[^\]\s])?';
    $word = '[\pL0-9_\-.\"](?:[\pL0-9_\-.\" ]*[\pL0-9_\-.\"])?';
    $pairs = ['b' => '', 'i' => '', 's' => '', 'u' => '', 'quote' => '', 'hide' => '', 'left' => '', 'center' => '', 'right' => '', 'justify' => '',
        'url' => '(?:=[ \t]*('.$val.')[ \t]*)?', 'mail' => '(?:[ \t]*=[ \t]*('.$val.')[ \t]*)?', 'img' => '(?:=([a-zA-Z]+))?(?: alt=([\pL0-9_\-.\" \t]+))?',
        'color' => '=[ \t]*('.$val.')[ \t]*', 'size' => '=([0-9]{1,2})', 'family' => '=([A-Za-z](?:[A-Za-z ]*[A-Za-z])?) *', 'tabs' => '=('.$val.')', 'tab' => '=('.$word.')'];
    $ones = ['hr' => '', 'li' => '', 'attach' => '='.$val, 'block' => '=[0-9]{1,10}'];
    preg_match_all('#\[(/?)([a-zA-Z]+)([= ][^\]\n]*)?\]#', $text, $hits, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $list = [];
    $pos = 0;
    foreach ($hits as $hit) {
        $name = strtolower($hit[2][0]);
        $rest = $hit[3][0] ?? '';
        $args = [];
        if ($hit[1][0] === '/') {
            $kind = (isset($pairs[$name]) && $rest === '') ? 'close' : '';
        } elseif (isset($pairs[$name]) && preg_match('#^'.$pairs[$name].'$#u', $rest, $args)) {
            $kind = 'open';
        } else {
            $kind = (isset($ones[$name]) && preg_match('#^'.$ones[$name].'$#u', $rest)) ? 'one' : '';
        }
        if ($kind === '') continue;
        if ($hit[0][1] > $pos) $list[] = ['t' => 'text', 'v' => substr($text, $pos, $hit[0][1] - $pos)];
        $list[] = ['t' => $kind, 'n' => $name, 'v' => $hit[0][0], 'a' => array_slice($args, 1)];
        $pos = $hit[0][1] + strlen($hit[0][0]);
    }
    if ($pos < strlen($text)) $list[] = ['t' => 'text', 'v' => substr($text, $pos)];
    $pair = [];
    $open = [];
    foreach ($list as $i => $one) {
        if ($one['t'] === 'open') $open[] = $i;
        if ($one['t'] !== 'close') continue;
        $deep = array_keys(array_filter($open, fn(int $v): bool => $list[$v]['n'] === $one['n']));
        if (!$deep) continue;
        $at = end($deep);
        $pair[$open[$at]] = $i;
        $open = array_slice($open, 0, $at);
    }
    $emo = ["\u{1F615}", "\u{1F61B}", "\u{1F60F}", "\u{1F642}", "\u{1F603}", "\u{1F604}", "\u{1F60E}", "\u{1F913}", "\u{1F644}", "\u{1F62E}", "\u{1F641}", "\u{1F633}",
        "\u{1F609}", "\u{1F60C}", "\u{1F620}", "\u{1F622}", "\u{1F633}", "\u{1F631}"];
    $line = function (string $part, bool $head, bool $more): string {
        if ($head) $part = ltrim($part, " \t");
        if ($more) $part = rtrim($part, " \t");
        $segs = preg_split('/(\x01\d+\x01|\x03\d+\x03|\x04\d+\x04)/', $part, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($segs as $k => $seg) {
            if ($k % 2 === 1 || $seg === '') {
                if ($seg !== '' && $seg[0] !== "\x03") $head = false;
                continue;
            }
            $bits = preg_split('#((?:https?|ftp)://[^\s<>\[\]]+|www\.[^\s<>\[\]]+|[A-Za-z0-9._+-]+@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)+)#i', $seg, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            foreach ($bits as $j => $bit) {
                if ($j % 2 === 1) continue;
                $bit = (string)preg_replace(['/[\\\\`*\[\]<~]/', '/(?<![\pL\pN])_|_(?![\pL\pN])/u', '/&(?=#?[A-Za-z0-9]+;)/', '/=(?==)|(?<==)=/'],
                    ['\\\\$0', '\\\\_', '\\\\&', '\\\\='], $bit);
                if (preg_match('/^[\s:|\\\\-]*-[\s:|\\\\-]*$/', $bit) && str_contains($bit, '|')) $bit = str_replace('|', '\|', $bit);
                $bits[$j] = $bit;
            }
            $seg = implode('', $bits);
            if ($head) {
                $seg = ltrim($seg, " \t");
                $seg = (string)preg_replace(['/^([#>+=-])/', '/^(\d{1,9})([.)])(?=[ \t]|$)/'], ['\\\\$1', '$1\\\\$2'], $seg);
            }
            $head = false;
            $segs[$k] = $seg;
        }
        return implode('', $segs);
    };
    $plain = function (string $run, bool $bol) use ($esc, $see, $emo, $line): string {
        $run = preg_replace_callback('/(?<!\*)\*(0[1-9]|1[0-8])(?!\d)/', function (array $m) use ($see, $emo): string {
            $see('smiley');
            return $emo[intval($m[1]) - 1];
        }, $run) ?? $run;
        $run = preg_replace('#<br\s*/?>[ \t]*\n?#i', "\x02", $run, -1, $num) ?? $run;
        if ($num) $see('br', $num);
        if (!$esc) return $run;
        if (preg_match('/&(?:[A-Za-z][A-Za-z0-9]*|#[0-9]+|#x[0-9A-Fa-f]+);/', $run)) $see('entity');
        $run = html_entity_decode($run, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $rows = preg_split('/(\n|\x02)/', $run, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($rows as $k => $row) if ($k % 2 === 0) $rows[$k] = $line($row, ($k === 0) ? $bol : true, $k < count($rows) - 1);
        return implode('', $rows);
    };
    $addr = fn(string $url): string => (string)preg_replace('/^www\./i', 'https://www.', trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    $dest = fn(string $url): string => strtr($url, [' ' => '%20', '<' => '%3C', '>' => '%3E', '(' => '%28', ')' => '%29', '\\' => '%5C', "\n" => '%0A', "\t" => '%09']);
    $raw = function (int $from, int $to) use ($list): string {
        $out = '';
        for ($i = $from; $i < $to; $i++) $out .= $list[$i]['v'];
        return $out;
    };
    $doc = function (string $s) use (&$keep, $esc): string {
        $s = (string)preg_replace(['/^[\s\x02]*\x05[\s\x05\x02]*/', '/[\s\x02]*\x05[\s\x05\x02]*$/', '/[\s\x02]*\x05[\s\x05\x02]*/'], ['', '', "\n\n"], $s);
        $bits = explode("\x02", $s);
        $s = array_shift($bits);
        foreach ($bits as $rest) {
            $last = substr($s, (int)strrpos("\n".$s, "\n"));
            $s .= (trim($last) === '' || preg_match('/^[ \t]*(?:\n|$)/', $rest)) ? "\n" : "\\\n";
            $s .= $rest;
        }
        if ($esc) $s = (string)preg_replace('/\n[ \t]*\n(?:[ \t]*\n)+/', "\n\n", $s);
        while (preg_match('/\x01\d+\x01/', $s)) $s = preg_replace_callback('/\x01(\d+)\x01/', fn(array $m): string => $keep[intval($m[1])], $s) ?? $s;
        return $s;
    };
    $emph = function (string $mark, string $in, string $before, string $after) use (&$wide): string {
        $parts = preg_split('/(\x05+|\n[ \t]*\n)/', $in, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $isp = fn(string $c): bool => $c !== '' && (bool)preg_match('/^[\p{P}\p{S}]$/u', $c);
        $isw = fn(string $c): bool => $c === '' || (bool)preg_match('/^[\s\x02\x05]$/u', $c);
        $unit = function (string $s, bool $end): string {
            if ($end) {
                if (preg_match('/(?:\x01\d+\x01|\\\\?.)$/su', $s, $m)) return $m[0];
                return '';
            }
            return preg_match('/^(?:\x01\d+\x01|\\\\.|.)/su', $s, $m) ? $m[0] : '';
        };
        $last = count($parts) - 1;
        foreach ($parts as $k => $one) {
            if ($k % 2 === 1 || trim($one, " \t\n\x02") === '') continue;
            if (preg_match('/^\s*\x01(\d+)\x01\s*$/', $one, $h) && isset($wide[intval($h[1])])) continue;
            preg_match('/^([\s\x02]*)(.*?)([\s\x02]*)$/su', $one, $p);
            $core = $p[2];
            $prev = ($p[1] !== '') ? ' ' : (($k === 0) ? mb_substr($before, -1) : "\n");
            $next = ($p[3] !== '') ? ' ' : (($k === $last) ? mb_substr($after, 0, 1) : "\n");
            $pre = '';
            $post = '';
            while (!$isw($prev) && !$isp($prev) && ($u = $unit($core, false)) !== '' && !in_array($u[0], ['*', '~'], true)
                && $isp(($u[0] === "\x01") ? '`' : mb_substr($u, -1))) {
                $pre .= $u;
                $core = substr($core, strlen($u));
            }
            while ($core !== '' && !$isw($next) && !$isp($next) && ($u = $unit($core, true)) !== '' && !in_array($u[strlen($u) - 1], ['*', '~'], true)
                && $isp(($u[0] === "\x01") ? '`' : mb_substr($u, -1))) {
                $post = $u.$post;
                $core = substr($core, 0, -strlen($u));
            }
            $parts[$k] = $p[1].$pre.(($core === '') ? '' : $mark.$core.$mark).$post.$p[3];
        }
        return implode('', $parts);
    };
    $marks = ['b' => '**', 'i' => '*', 's' => '~~'];
    $render = function (int $from, int $to, bool $bol, array $act) use (&$render, $list, $pair, $marks, $esc, $see, $put, $plain, $addr, $dest, $raw, $doc, $emph): string {
        $parts = [];
        $buf = '';
        $tail = function () use (&$parts, $bol): string {
            if (!$parts) return $bol ? "\n" : '';
            $one = end($parts);
            return isset($one['m']) ? $one['m'] : substr($one['s'], -1);
        };
        $flush = function () use (&$buf, &$parts, $plain, $tail): void {
            if ($buf === '') return;
            $parts[] = ['s' => $plain($buf, in_array($tail(), ["\n", "\x02", "\x05"], true))];
            $buf = '';
        };
        $lit = fn(string $s, bool $head = false): string => $esc ? $plain($s, $head) : $s;
        for ($i = $from; $i < $to; $i++) {
            $one = $list[$i];
            if ($one['t'] === 'text' || $one['t'] === 'close' || ($one['t'] === 'open' && !isset($pair[$i]))) {
                if ($one['t'] !== 'text') $see('unpaired');
                $buf .= $one['v'];
                continue;
            }
            $flush();
            $name = $one['n'];
            $bnow = in_array($tail(), ["\n", "\x02", "\x05"], true);
            if ($one['t'] === 'one') {
                $see($name);
                $parts[] = ['s' => ['hr' => $put('***', true), 'li' => "\u{2022} "][$name] ?? $one['v']];
                continue;
            }
            $end = $pair[$i];
            $args = $one['a'];
            $see($name);
            if (isset($marks[$name])) {
                $in = $render($i + 1, $end, false, $act + [$name => true]);
                $parts[] = isset($act[$name]) ? ['s' => $in] : ['m' => $marks[$name], 'in' => $in];
            } elseif ($name === 'quote') {
                $body = trim($doc($render($i + 1, $end, true, [])), "\n");
                $body = implode("\n", array_map(fn(string $v): string => ($v === '') ? '>' : '> '.$v, explode("\n", $body)));
                $parts[] = ['s' => (trim($body, "> \n") === '') ? '' : $put($body, true)];
            } elseif (in_array($name, ['hide', 'tabs', 'tab'], true)) {
                $parts[] = ['s' => $one['v'].$render($i + 1, $end, true, []).$list[$end]['v']];
            } elseif ($name === 'url' || $name === 'mail') {
                $kid = $raw($i + 1, $end);
                $link = $addr((string)($args[0] ?? $kid));
                $bare = !isset($args[0]) || trim($kid) === '' || trim($kid) === trim((string)$args[0]);
                $label = $bare ? '' : $render($i + 1, $end, false, $act);
                $abs = (bool)preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]{1,31}:#', $link);
                if ($name === 'mail') {
                    if (!preg_match('/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i', $link)) {
                        $see('mail-text');
                        $parts[] = ['s' => $bare ? $lit($kid) : $label];
                    } elseif (!$bare) {
                        $parts[] = ['s' => '['.$label.'](mailto:'.$dest($link).')'];
                    } else {
                        $host = '[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?';
                        $auto = preg_match('/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@'.$host.'(?:\.'.$host.')*$/D', $link);
                        $parts[] = ['s' => $auto ? '<'.$link.'>' : '['.$lit($link).'](mailto:'.$dest($link).')'];
                    }
                } elseif ($link === '' || preg_match('#^(?:javascript|vbscript|data|ed2k):#i', (string)preg_replace('#[\s\x00-\x1f]+#', '', $link))) {
                    $see(preg_match('#^ed2k:#i', $link) ? 'ed2k' : 'url-text');
                    $parts[] = ['s' => $bare ? $lit($kid) : $label];
                } elseif ($bare && $abs) {
                    $parts[] = ['s' => '<'.strtr($link, [' ' => '%20', '<' => '%3C', '>' => '%3E', "\n" => '%0A', "\t" => '%09']).'>'];
                } else {
                    $parts[] = ['s' => '['.($bare ? $lit($link) : $label).']('.$dest($link).')'];
                }
            } elseif ($name === 'img') {
                $link = $addr($raw($i + 1, $end));
                if (($args[0] ?? '') !== '') $see('align');
                $alt = $lit(trim((string)($args[1] ?? '')));
                if (!$esc) $alt = strtr($alt, ['[' => '\[', ']' => '\]']);
                if ($link === '') {
                    $parts[] = ['s' => ''];
                } elseif (preg_match('#^(?:javascript|vbscript|ed2k):#i', (string)preg_replace('#[\s\x00-\x1f]+#', '', $link))) {
                    $parts[] = ['s' => $alt];
                } else {
                    $parts[] = ['s' => '!['.$alt.']('.$dest($link).')'];
                }
            } else {
                $parts[] = ['s' => $render($i + 1, $end, $bnow, $act)];
            }
            $i = $end;
        }
        $flush();
        $out = '';
        foreach ($parts as $k => $one) {
            if (!isset($one['m'])) {
                $out .= $one['s'];
                continue;
            }
            $next = '';
            for ($j = $k + 1; $j < count($parts) && $next === ''; $j++) $next = $parts[$j]['m'] ?? $parts[$j]['s'];
            $out .= $emph($one['m'], $one['in'], $out, $next);
        }
        return $out;
    };
    $text = $doc($render(0, count($list), true, []));
    return ($text === $flat) ? $orig : $text;
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

# The material a row of a removed module becomes, in the columns of the node table, plus the extra data the module carried in its own columns
# The own columns of files and media land in the extra fields of their profiles under the field names; the links of media are a comma list of addresses
# The status of help tells an open request from a closed one, which the queue row carries, so every request is published; elsewhere 0 is a submission not yet approved
function getMigrateNode(string $mod, array $row): array {
    $votes = in_array($mod, ['links', 'files', 'media'], true);
    $uid = intval($row['uid'] ?? 0);
    $cols = ['files' => ['release' => 'version', 'site' => 'website'], 'media' => ['subtitle' => 'subtitle', 'year' => 'year', 'director' => 'director', 'cast' => 'roles',
        'creator' => 'author', 'runtime' => 'duration', 'language' => 'lang', 'notes' => 'note', 'format' => 'format', 'quality' => 'quality', 'filesize' => 'size',
        'release' => 'released']][$mod] ?? [];
    $extra = array_map(fn(string $v): string => trim((string)($row[$v] ?? '')), $cols);
    if (isset($extra['release'])) $extra['release'] = mb_substr($extra['release'], 0, 100);
    return [
        'id' => intval($row['id']), 'cid' => intval($row['cid'] ?? 0), 'uid' => $uid, 'aname' => $uid > 0 ? '' : filterCutText((string)($row['name'] ?? ''), 25),
        'ip' => getIpNorm((string)($row['ip'] ?? '')) ?: '', 'title' => filterCutText(trim((string)($row['title'] ?? '')), 100), 'intro' => (string)($row['intro'] ?? ''),
        'body' => (string)($row['body'] ?? ''), 'field' => (string)($row['field'] ?? ''), 'time' => (string)($row['time'] ?? ''), 'views' => intval($row['counter'] ?? 0),
        'home' => intval($row['ihome'] ?? 0), 'comon' => ($mod === 'help') ? CommentMode::Open->value : intval($row['acomm'] ?? 0), 'pinned' => intval($row['fix'] ?? 0),
        'poll' => intval($row['vote'] ?? 0), 'score' => intval($votes ? $row['tvotes'] : ($row['score'] ?? $row['rating'] ?? 0)),
        'ratings' => intval($votes ? $row['votes'] : ($row['ratings'] ?? $row['ratetot'] ?? 0)),
        'status' => ($mod === 'help') ? 1 : intval($row['status'] ?? 1), 'assoc' => (string)($row['assoc'] ?? ''), 'url' => trim((string)($row['url'] ?? '')),
        'links' => array_values(array_filter(array_map('trim', explode(',', (string)($row['links'] ?? ''))), 'strlen')), 'size' => intval($row['filesize'] ?? 0),
        'extra' => $extra, 'hits' => intval($row['hits'] ?? 0),
    ];
}

# One resource of a file, link or media row; another scheme is refused, a local path the Node grammar refuses gets a safe spelling and a note to rename its file
function getMigrateAsset(string $mod, array $node, string $role, array &$moves, array &$miss, array &$state, array &$srcs): ?array {
    $src = $node['url'];
    $old = $src;
    if ($src === '' || $role === '') return null;
    $link = (bool)preg_match('#^https?://#i', $src);
    if (!$link && preg_match('#^[a-z][a-z0-9+.\-]*:#i', $src)) {
        addMigrateNote($state, $mod, '#'.$node['id'].': the address '.$src.' is refused by Node and was not carried');
        return null;
    }
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
            while (getMigratePath($mod, $src) !== '' || in_array($src, $moves, true)) $src = $stem.'-'.getRandomString(4).$tail;
            $moves[$old] = $src;
            if (getMigratePath($mod, $old) !== '') {
                addMigrateNote($state, $mod, '#'.$node['id'].': rename the file '.$old.' to '.$src.' in uploads/'.getUploadFolder(getMigrateMap()[$mod]['type'], true));
            }
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
    $full = $link ? '' : getMigratePath($mod, $old);
    $size = ($full !== '') ? filesize($full) : false;
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

# The extra field values a material keeps where its type defines them, each value checked by the shared field system on its own; a refused value is left out
function getMigrateFields(NodeType $type, array $node): array {
    global $fld;
    $out = [];
    foreach ($node['extra'] as $key => $val) {
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

# Insert the materials of one module with their legacy address, categories, resource and rating, and answer the map of old ids to new ones
function addMigrateNodes(string $mod, NodeType $type, array $rows, Closure $have, array &$state): array {
    global $db;
    $moves = [];
    $now = (string)getMigrateQuery('SELECT NOW()')->fetchColumn();
    $cats = array_map('intval', getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_categories WHERE modul = :name', ['name' => $type->name])->fetchAll(PDO::FETCH_COLUMN));
    $cats = array_flip($cats);
    $polls = array_flip(array_map('intval', getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_voting')->fetchAll(PDO::FETCH_COLUMN)));
    $feat = $type->settings['features'];
    $role = ['files' => getMigrateRole($type, 'download'), 'links' => getMigrateRole($type, 'link'), 'media' => getMigrateRole($type, 'download')][$mod] ?? '';
    $most = intval($type->settings['assets'][$role]['max'] ?? 1);
    $srcs = [];
    $sql = 'SELECT a.src FROM '.PREFIX_DB.'_node_assets AS a INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = a.nid WHERE n.tid = :tid';
    foreach (getMigrateQuery($sql, ['tid' => $type->id])->fetchAll(PDO::FETCH_COLUMN) as $src) $srcs[$src] = 0;
    $ins = 'INSERT INTO '.PREFIX_DB.'_nodes (tid, cid, uid, aname, ip, title, intro, body, field, poll, home, comon, pinned, comnum, views, score, ratings, status, version,'
        .' created, updated, published, expires) VALUES (:tid, :cid, :uid, :aname, :ip, :title, :intro, :body, :field, :poll, :home, :comon, :pinned, 0, :views, :score,'
        .' :ratings, :status, 1, :created, :updated, :published, NULL)';
    $put = 'INSERT INTO '.PREFIX_DB.'_node_assets (nid, kind, role, src, name, title, intro, mime, size, width, height, duration, hits, reported, ruid, sort, created, updated)'
        .' VALUES (:nid, :kind, :role, :src, :name, \'\', \'\', :mime, :size, :width, :height, NULL, :hits, :rep, 0, :sort, :created, :updated)';
    $rate = 'INSERT INTO '.PREFIX_DB.'_rating_targets (scope, mid, base, votes, created) VALUES (:scope, :mid, :base, :votes, NOW())';
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
        $body = getMigrateAttach(getMigrateHtml($node['body'], true), $mod, 0, $have).($vals ? "\n\n".getMigrateMarkdown(implode(' | ', $vals), true) : '');
        $intro = getMigrateAttach(getMigrateHtml($node['intro'], true), $mod, 0, $have);
        if (strlen($intro) > 65535) {
            $intro = $node['intro'];
            addMigrateNote($state, $mod, '#'.$id.': the converted summary would not fit its column, it keeps the text of the old module');
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
        $done = array_map(fn(string $v): string => getMigrateAttach($v, $mod, $nid, $have), ['intro' => $intro, 'body' => $body]);
        if (strlen($done['intro']) > 65535) $done['intro'] = $intro;
        if ($done !== ['intro' => $intro, 'body' => $body]) getMigrateQuery('UPDATE '.PREFIX_DB.'_nodes SET intro = :intro, body = :body WHERE id = :id', $done + ['id' => $nid]);
        if ($node['status'] !== 0) getMigrateQuery($back, ['modul' => $mod, 'oid' => $id, 'nid' => $nid]);
        $more =array_diff(array_unique(array_map('intval', explode(',', $node['assoc']))), [0, $node['cid']]);
        foreach ($feat['categories'] ? $more : [] as $cid) {
            if (isset($cats[$cid])) getMigrateQuery('INSERT INTO '.PREFIX_DB.'_node_categories (nid, cid) VALUES (:nid, :cid)', ['nid' => $nid, 'cid' => $cid]);
        }
        $sort = 0;
        foreach (($mod === 'media') ? $node['links'] : [$node['url']] as $src) {
            if ($sort >= $most) {
                addMigrateNote($state, $mod, '#'.$id.': the addresses after the first '.$most.' are not carried, the role keeps no more');
                break;
            }
            $one = getMigrateAsset($mod, ['url' => $src, 'hits' => ($sort > 0) ? 0 : $node['hits']] + $node, $role, $moves, $miss, $state, $srcs);
            if ($one === null) continue;
            getMigrateQuery($put, ['nid' => $nid, 'kind' => $one['kind'], 'role' => $one['role'], 'src' => $one['src'], 'name' => $one['name'], 'mime' => $one['mime'],
                'size' => $one['size'], 'width' => $one['width'], 'height' => $one['height'], 'hits' => $one['hits'], 'rep' => $one['reported'] ? $now : null,
                'sort' => $sort++, 'created' => $time, 'updated' => $time]);
        }
        if ($num > 0) getMigrateQuery($rate, ['scope' => 'node.'.$type->name, 'mid' => $nid, 'base' => $score, 'votes' => $num]);
        if ($mod === 'content' && $node['url'] !== '') addMigrateNote($state, $mod, '#'.$id.': the feed address '.$node['url'].' is not carried, the type has no sync extension');
    }
    if ($miss) addMigrateNote($state, $mod, count($miss).' files the rows name do not exist, their resources keep the address: '.implode(', ', array_slice($miss, 0, 10)));
    return $map;
}

# Carry the replies of the requests of the old help module into comments of their new materials, then fill the queue row of each request from its history
# A reply keeps its author, address and time; a closed request stays closed, an open one waits for the side that did not write last
function addMigrateReplies(NodeType $type, array $roots, array $map, Closure $have): void {
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
        $body = getMigrateAttach(getMigrateHtml(($title !== '' ? '[b]'.$title."[/b]\n\n" : '').$row['body'], false), 'help', 0, $have);
        getMigrateQuery($sql, ['cid' => $nid, 'modul' => $type->name, 'time' => $time, 'uid' => $aid, 'name' => filterCutText((string)($users[$aid] ?? ''), 25),
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

# Bind comments, favorites and rating terms of the old module to the new materials, rows of a gone material under old<module>, comment addresses as attachments
function setMigrateLinks(string $mod, NodeType $type, array $map, Closure $have): void {
    $key = '~'.$mod;
    $sql = 'SELECT id, body FROM '.PREFIX_DB.'_comment WHERE modul = :key AND body LIKE :like';
    foreach (getMigrateQuery($sql, ['key' => $key, 'like' => '%uploads/'.$mod.'/%'])->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $body = getMigrateAttach($row['body'], $mod, 0, $have);
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
    $rows = getMigrateQuery('SELECT * FROM '.PREFIX_DB.'_'.$mod.($mod === 'help' ? ' WHERE pid = 0' : '').' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $exts = $type->uploads ? explode(',', $type->uploads['extensions']) : [];
    $have = fn(string $rel): bool => in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), $exts, true) && getMigratePath($mod, $rel) !== '';
    if (!$db->setSqlBegin()) throw new RuntimeException('The transaction of '.$mod.' cannot be started');
    try {
        getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_node_types WHERE id = :id FOR UPDATE', ['id' => $type->id]);
        getMigrateQuery('UPDATE '.PREFIX_DB.'_categories SET modul = :name WHERE modul = :key', ['name' => $type->name, 'key' => '~'.$mod]);
        $state['notes'][$mod] = [];
        $map = addMigrateNodes($mod, $type, $rows, $have, $state);
        if ($mod === 'help') addMigrateReplies($type, $rows, $map, $have);
        setMigrateLinks($mod, $type, $map, $have);
        $state['data'][$mod] = ['state' => 'committing', 'type' => $type->name, 'map' => $map,
            'count' => array_intersect_key($one, array_flip(['rows', 'comments', 'favorites', 'categories']))];
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

# Drop the table of every module whose data step is done, once the database agrees with the map of its manifest: every row of the table is a key, every material is there
# A manifest of another database, left in the tree, never drops a table whose rows were not carried into this one: the table stays with a note
function deleteMigrateTables(array &$state): void {
    foreach (getMigrateMap() as $mod => $item) {
        if (($state['data'][$mod]['state'] ?? '') !== 'done' || !checkMigrateTable($mod)) continue;
        $map = array_map('intval', $state['data'][$mod]['map'] ?? []);
        $ids = array_map('intval', getMigrateQuery('SELECT id FROM '.PREFIX_DB.'_'.$mod.($mod === 'help' ? ' WHERE pid = 0' : ''))->fetchAll(PDO::FETCH_COLUMN));
        $type = getMigrateType($item['type']);
        $have = 0;
        foreach (array_chunk(array_values(array_unique($map)), 500) as $part) {
            $pars = ['tid' => $type?->id ?? 0];
            foreach ($part as $i => $nid) $pars['n'.$i] = $nid;
            $have += getMigrateCount('SELECT COUNT(*) FROM '.PREFIX_DB.'_nodes WHERE tid = :tid AND id IN (:'.implode(', :', array_slice(array_keys($pars), 1)).')', $pars);
        }
        if (!array_diff($ids, array_keys($map)) && $have === count(array_unique($map))) {
            getMigrateQuery('DROP TABLE '.PREFIX_DB.'_'.$mod);
            continue;
        }
        $text = 'the table stays: its rows or the materials of the type differ from the map of the manifest';
        if (in_array($text, $state['notes'][$mod] ?? [], true)) continue;
        addMigrateNote($state, $mod, $text);
        setMigrateState($state);
    }
}

# Point the direct addresses a text keeps into the 6.2 folder of a type, or into uploads/forum outside the forum, at a published material or post that carries the name
# It runs on every run after the data step, so a type that was blocked before is served once its materials exist; a converted address no longer matches
function setMigrateForeign(array &$state): void {
    $prs = new Parser();
    $cols = ['nodes' => ['intro', 'body'], 'comment' => ['body'], 'forum' => ['body'], 'privat' => ['body'], 'message' => ['body'], 'newsletter' => ['body'],
        'blocks' => ['content'], 'users' => ['sig', 'block'], 'voting' => ['body']];
    $live = NodeStatus::Published->value;
    foreach (array_map(fn(array $v): string => $v['type'], getMigrateMap()) + ['forum' => ''] as $mod => $kind) {
        $type = ($kind !== '') ? getMigrateType($kind) : null;
        if ($kind !== '' && $type === null) continue;
        $memo = [];
        $find = ($type === null) ? function (string $name) use ($prs, &$memo): int {
            if (isset($memo[$name])) return $memo[$name];
            $sql = 'SELECT f.id, f.body FROM '.PREFIX_DB.'_forum AS f LEFT JOIN '.PREFIX_DB.'_forum AS h ON h.id = f.pid WHERE f.status <> 0 AND f.time <= NOW()'
                .' AND COALESCE(h.status, f.status) > 1 AND COALESCE(h.time, f.time) <= NOW() AND f.body LIKE :like ORDER BY f.id';
            foreach (getMigrateQuery($sql, ['like' => '%'.$name.'%'])->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (in_array($name, $prs->getAttachList($row['body']), true)) return $memo[$name] = intval($row['id']);
            }
            return $memo[$name] = 0;
        } : function (string $name) use ($type, $prs, $live, &$memo): int {
            if (isset($memo[$name])) return $memo[$name];
            $like = '%'.$name.'%';
            $sql = 'SELECT id, intro, body FROM '.PREFIX_DB.'_nodes WHERE tid = :tid AND status = :live AND (intro LIKE :one OR body LIKE :two) ORDER BY id';
            foreach (getMigrateQuery($sql, ['tid' => $type->id, 'live' => $live, 'one' => $like, 'two' => $like])->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $text = $row['intro']."\n".$row['body'];
                $mark = '#index\.php\?go=file&(?:amp;)?own=node&(?:amp;)?id='.$row['id'].'&(?:amp;)?key='.preg_quote(rawurlencode($name), '#').'(?![A-Za-z0-9_.%-])#';
                $html = preg_match_all('#\[usehtml\](.*?)\[/usehtml\]#si', $text, $blks) ? implode("\n", $blks[1]) : '';
                if (in_array($name, $prs->getAttachList($text), true) || preg_match($mark, $html)) return $memo[$name] = intval($row['id']);
            }
            $sql = 'SELECT c.cid, c.body FROM '.PREFIX_DB.'_comment AS c INNER JOIN '.PREFIX_DB.'_nodes AS n ON n.id = c.cid AND n.tid = :tid AND n.status = :live'
                .' WHERE c.modul = :mod AND c.status = :stat AND c.deleted IS NULL AND c.body LIKE :like ORDER BY c.id';
            $pars = ['tid' => $type->id, 'live' => $live, 'mod' => $type->name, 'stat' => CommentStatus::Published->value, 'like' => $like];
            foreach (getMigrateQuery($sql, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (in_array($name, $prs->getAttachList($row['body']), true)) return $memo[$name] = intval($row['cid']);
            }
            return $memo[$name] = 0;
        };
        $num = 0;
        $self = ($type === null) ? [] : ['nodes' => ['tid', $type->id], 'comment' => ['modul', $type->name]];
        $own = ($type === null) ? 'forum' : 'node';
        foreach ($cols as $tab => $list) {
            if ($tab === $mod) continue;
            $pars = [];
            foreach (array_keys($list) as $i) $pars['l'.$i] = '%uploads/'.$mod.'/%';
            $where = '('.implode(' OR ', array_map(fn(string $v, string $k): string => '`'.$v.'` LIKE :'.$k, $list, array_keys($pars))).')';
            if (isset($self[$tab])) {
                $where .= ' AND `'.$self[$tab][0].'` <> :own';
                $pars['own'] = $self[$tab][1];
            }
            foreach (getMigrateQuery('SELECT id, `'.implode('`, `', $list).'` FROM '.PREFIX_DB.'_'.$tab.' WHERE '.$where, $pars)->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $sets = [];
                $vals = ['id' => $row['id']];
                foreach ($list as $i => $col) {
                    $text = getMigrateForeign((string)$row[$col], $mod, $own, $find);
                    if ($text === (string)$row[$col]) continue;
                    if (strlen($text) > getEditorRoomData($tab.'.'.$col)['bytes']) {
                        addMigrateNote($state, $mod, $tab.' #'.$row['id'].': the rewritten '.$col.' would not fit its column, it keeps the old addresses');
                        continue;
                    }
                    $sets[] = '`'.$col.'` = :v'.$i;
                    $vals['v'.$i] = $text;
                }
                if (!$sets) continue;
                getMigrateQuery('UPDATE '.PREFIX_DB.'_'.$tab.' SET '.implode(', ', $sets).' WHERE id = :id', $vals);
                $num++;
            }
        }
        if ($num > 0) addMigrateNote($state, $mod, $num.' texts reach a file of uploads/'.$mod.' through the '.(($type === null) ? 'post' : 'material').' that carries it');
    }
    setMigrateState($state);
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

# Give every entity of the site its first address once the materials exist; it runs on every run, so the materials of a module carried later get theirs as well
function setMigrateRoutes(array &$state): void {
    $state['routes'] = addRouteBacklog();
    setMigrateState($state);
}

# Run every step that is not done yet in the fixed order - stash, types, id counter, data, the drop of the carried tables, foreign addresses, addresses, activation -
# and answer the text that stopped the run
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
        deleteMigrateTables($state);
        setMigrateForeign($state);
        setMigrateRoutes($state);
        setMigrateActive($state);
    } catch (Throwable $err) {
        Logger::addSite('error', 'Node: the migration of the old modules stopped', ['error' => $err->getMessage()]);
        return $err->getMessage();
    }
    Logger::addSite('info', 'Node: the migration of the old modules ran', ['aid' => getNodeContext()->aid, 'routes' => array_sum($state['routes'])]);
    return '';
}

# Render the page of the migration: every module with its target and its state, the notes of the runs and the button that starts or continues the run
# A module whose table the run dropped after its data step is shown from the counts its manifest keeps
function setMigratePage(string $fail): void {
    global $tpl;
    $state = getMigrateState();
    $plan = getMigratePlan($state);
    $list = [];
    foreach (getMigrateMap() as $mod => $item) {
        if (isset($plan[$mod]) || ($state['data'][$mod]['state'] ?? '') === 'done') $list[$mod] = $plan[$mod] ?? ['type' => $item['type'], 'block' => ''];
    }
    $rows = '';
    $open = false;
    foreach ($list as $mod => $one) {
        $done = ($state['data'][$mod]['state'] ?? '') === 'done';
        $open = $open || (!$done && $one['block'] === '');
        $one = ($state['data'][$mod]['count'] ?? []) + $one;
        $cells = [$mod.' → '.$one['type'], $one['rows'], $one['comments'], $one['favorites'], $one['categories'], $one['block'] ?: ($done ? _NODE_MIGDONE : _NODE_MIGWAIT)];
        $rows .= $tpl->getHtmlFrag('table-row', ['cells' => array_map(fn(mixed $v): array => ['text' => (string)$v], $cells)]);
    }
    $head = array_map(fn(string $v): array => ['text' => $v], [_TYPE, _NODE_MIGROWS, _COMMENTS, _FAVORITES, _CATEGORIES, _STATUS]);
    $cont = $tpl->getHtmlFrag('alert', ['text' => _NODE_MIGINFO]);
    if ($fail !== '') $cont .= $tpl->getHtmlFrag('alert', ['type' => 'error', 'messages' => [_NODE_MIGSTOP, $fail]]);
    elseif (!$open && $state['routes']) $cont .= $tpl->getHtmlFrag('alert', ['type' => 'success', 'text' => _NODE_MIGDONE]);
    $cont .= $tpl->getHtmlFrag('table', ['headers' => $head, 'rows_html' => $rows]);
    foreach ($state['notes'] as $mod => $list) {
        if ($list) $cont .= $tpl->getHtmlFrag('alert', ['is_warn' => true, 'messages' => array_map(fn(string $v): string => $mod.': '.$v, $list)]);
    }
    if ($open || !empty($state['active']) || !$state['routes']) {
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

# The connection settings of config/db.php, the db area of 6.3 or the $confdb of 6.2, both with the collation of the release the schema file converts into
function getUpdateCred(): array {
    $data = getUpdateSource(CONFIG_DIR.'/db.php');
    $data = is_array($data['db'] ?? null) ? $data['db'] : $data;
    return $data ? array_replace($data, ['collate' => 'utf8mb4_unicode_520_ci']) : [];
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
# Journal rows without a manifest stop it, because a current balance is never taken for a starting one; a mark without a manifest is a unit that ran on the site before
function setUpdatePoints(Database $db, string $prefix): array {
    $dir = BASE_DIR.'/storage/backup/update/points';
    $file = $dir.'/manifest.json';
    $mark = is_file(CONFIG_DIR.'/update.php') ? ((require CONFIG_DIR.'/update.php')['update'] ?? []) : [];
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!is_array($info)) {
        if (isset($mark['points'])) return getUpdateRow('points: the mark is set and no manifest exists, the unit ran on this site before', true);
        [$rows] = $db->getSqlRow($db->getSqlQuery('SELECT COUNT(*) FROM `'.$prefix.'_points`'));
        if ($rows > 0) return getUpdateRow('points: the journal already has rows and no manifest exists, the unit is stopped', false);
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
# A mark without a manifest is a unit that ran on the site before
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
        if (isset($mark['ratings'])) return getUpdateRow('ratings: the mark is set and no manifest exists, the unit ran on this site before', true);
        $rows = 0;
        foreach (['targets', 'actors', 'votes'] as $name) {
            $num = $count('SELECT COUNT(*) FROM `'.$prefix.'_rating_'.$name.'`');
            if ($num < 0) return getUpdateRow('ratings: the table '.$prefix.'_rating_'.$name.' could not be read, the unit is stopped', false);
            $rows += $num;
        }
        if ($rows > 0) return getUpdateRow('ratings: the new tables already have rows and no manifest exists, the unit is stopped', false);
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
            $more = $kind === 'targets' ? ['created' => date('Y-m-d H:i:s', intval($info['moment']))] : [];
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
# A mark without a manifest is a unit that ran on the site before
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
        if (isset($mark['fields'])) return getUpdateRow('fields: the mark is set and no manifest exists, the unit ran on this site before', true);
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

# The attachment unit of the 6.3 data update for one table of texts: a direct address of a file its folder holds becomes an attachment of its name
# The forum posts address uploads/forum and the private messages uploads/account; both folders are closed, so a direct address would answer 410; a stopped run is run again
function setUpdateAttach(Database $db, string $prefix, string $tab, string $dir): array {
    $res = $db->getSqlQuery('SELECT id, body FROM `'.$prefix.'_'.$tab.'` WHERE body LIKE :addr', ['addr' => '%uploads/'.$dir.'/%']);
    if ($res === false) return getUpdateRow($tab.': the texts could not be read, no address was rewritten', false);
    $have = fn(string $name): bool => is_file(UPLOADS_DIR.'/'.$dir.'/'.$name);
    $done = 0;
    $good = true;
    foreach ($db->getSqlRows($res) ?: [] as $row) {
        $body = getMigrateAttach((string)$row[1], $dir, 0, $have);
        if ($body === (string)$row[1]) continue;
        $ok = $db->getSqlQuery('UPDATE `'.$prefix.'_'.$tab.'` SET body = :body WHERE id = :id', ['body' => $body, 'id' => intval($row[0])]) !== false;
        $good = $good && $ok;
        if ($ok) $done++;
    }
    return getUpdateRow($tab.': direct addresses of uploads/'.$dir.' turned into attachments (texts: '.$done.')', $good);
}

# The catch-up of a 6.3 site: archive and old folder addresses of Node texts and their comments become attachments, an archive address elsewhere reaches its material
function setUpdateArchive(Database $db, string $prefix, bool $dry): array {
    global $conf;
    require_once BASE_DIR.'/core/classes/upload.php';
    require_once BASE_DIR.'/core/classes/comment.php';
    require_once BASE_DIR.'/core/classes/node/status.php';
    $dir = BACKUP_DIR.'/update/archive';
    $file = $dir.'/manifest.json';
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$dry && ($info['state'] ?? '') === 'verified') return getUpdateRow('archive: the file addresses of the texts were caught up by an earlier run', true);
    $res = $db->getSqlQuery('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tab', ['tab' => $prefix.'_node_types']);
    if ($res === false) return getUpdateRow('archive: the tables could not be read, no address was rewritten', false);
    if (intval($db->getSqlRow($res)[0] ?? 0) === 0) return getUpdateRow('archive: the site has no Node tables, nothing to catch up', true);
    $res = $db->getSqlQuery('SELECT id, name FROM `'.$prefix.'_node_types`');
    if ($res === false) return getUpdateRow('archive: the types could not be read, no address was rewritten', false);
    $kinds = [];
    $folds = [];
    foreach ($db->getSqlRows($res) ?: [] as $row) $kinds[intval($row['id'])] = (string)$row['name'];
    foreach ($kinds as $name) $folds[$name] = array_values(array_unique([$name, ...array_keys(array_filter(getMigrateMap(), fn(array $v): bool => $v['type'] === $name))]));
    $exts = Upload::getSupportedTypes();
    $room = fn(string $name): Closure => function (string $rel) use ($name, $folds, $exts): bool {
        if (!in_array(strtolower(pathinfo($rel, PATHINFO_EXTENSION)), $exts, true)) return false;
        foreach ($folds[$name] as $one) if (is_file(UPLOADS_DIR.'/archive/'.$one.'/'.$rel) || is_file(UPLOADS_DIR.'/'.$one.'/'.$rel)) return true;
        return is_file(UPLOADS_DIR.'/node/'.$name.'/'.$rel);
    };
    $memo = [];
    $raw = true;
    $live = NodeStatus::Published->value;
    $seek = function (int $tid, string $one) use ($db, $prefix, $live, &$memo, &$raw): Closure {
        return function (string $key) use ($db, $prefix, $tid, $one, $live, &$memo, &$raw): int {
            $at = $tid.' '.$one;
            if (isset($memo[$at][$key])) return $memo[$at][$key];
            $like = '%'.strtr($key, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';
            $mark = '#\[attach='.preg_quote($key, '#').' align=#';
            $sql = 'SELECT id, intro, body FROM `'.$prefix.'_nodes` WHERE tid = :tid AND status = :live AND (intro LIKE :one ESCAPE \'!\' OR body LIKE :two ESCAPE \'!\')'
                .' ORDER BY id';
            $res = $db->getSqlQuery($sql, ['tid' => $tid, 'live' => $live, 'one' => $like, 'two' => $like]);
            foreach (($res ? $db->getSqlRows($res) : false) ?: [] as $row) {
                $text = $row['intro']."\n".$row['body'];
                if (preg_match($mark, $text) || ($raw && str_contains($text, 'archive/'.$one.'/'.$key))) return $memo[$at][$key] = intval($row['id']);
            }
            $sql = 'SELECT c.cid, c.body FROM `'.$prefix.'_comment` AS c INNER JOIN `'.$prefix.'_nodes` AS n ON n.id = c.cid AND n.tid = :tid AND n.status = :live'
                .' INNER JOIN `'.$prefix.'_node_types` AS t ON t.id = n.tid AND t.name = c.modul WHERE c.status = :stat AND c.deleted IS NULL'
                .' AND c.body LIKE :like ESCAPE \'!\' ORDER BY c.id';
            $res = $db->getSqlQuery($sql, ['tid' => $tid, 'live' => $live, 'stat' => CommentStatus::Published->value, 'like' => $like]);
            foreach (($res ? $db->getSqlRows($res) : false) ?: [] as $row) {
                if (preg_match($mark, $row['body']) || ($raw && str_contains($row['body'], 'archive/'.$one.'/'.$key))) return $memo[$at][$key] = intval($row['cid']);
            }
            return $memo[$at][$key] = 0;
        };
    };
    $fix = function (string $text, string $name, int $nid) use ($kinds, $folds, $room, $seek): string {
        $have = ($name !== '') ? $room($name) : null;
        foreach ($have ? $folds[$name] : [] as $one) $text = getMigrateAttach(getMigrateAttach($text, 'archive/'.$one, $nid, $have), $one, $nid, $have);
        if ($have) return $text;
        foreach ($kinds as $tid => $kind) foreach ($folds[$kind] as $one) $text = getMigrateForeign($text, 'archive/'.$one, 'node', $seek($tid, $one));
        return $text;
    };
    $host = preg_quote((string)preg_replace('#^www\.#i', '', (string)parse_url((string)($conf['homeurl'] ?? ''), PHP_URL_HOST)), '#');
    $left = fn(string $text, array $dirs): int => (int)preg_match_all('#(?<![A-Za-z0-9_./:%-])(?:\./|/|https?://(?:www\.)?'.$host.'/)?uploads/(?:'
        .implode('|', array_map(fn(string $v): string => preg_quote($v, '#'), $dirs)).'|archive/[a-z]+)/[A-Za-z0-9_%-]#i', $text);
    $jobs = ['nodes' => ['intro', 'body'], 'comment' => ['body'], 'forum' => ['body'], 'privat' => ['body'], 'message' => ['body'], 'newsletter' => ['body'],
        'blocks' => ['content'], 'users' => ['sig', 'block'], 'voting' => ['body']];
    $save = function () use ($file, &$info): bool {
        return setUpdateBackup($file, (string)json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    };
    $walk = function (bool $write) use ($db, $prefix, $jobs, $kinds, $folds, $fix, $left, $save, &$info): ?array {
        $sum = ['change' => [], 'left' => 0, 'long' => []];
        foreach ($jobs as $tab => $cols) {
            $res = $db->getSqlQuery('SELECT column_name, character_octet_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tab',
                ['tab' => $prefix.'_'.$tab]);
            $size = [];
            foreach (($res ? $db->getSqlRows($res) : false) ?: [] as $row) $size[strtolower((string)$row[0])] = intval($row[1]);
            if (array_diff($cols, array_keys($size))) continue;
            $pars = ['cur' => $write ? intval($info['cursor'][$tab] ?? 0) : 0];
            foreach ($cols as $i => $col) $pars['l'.$i] = '%uploads/%';
            $where = implode(' OR ', array_map(fn(string $v, int $i): string => '`'.$v.'` LIKE :l'.$i, $cols, array_keys($cols)));
            $more = ['nodes' => ', tid', 'comment' => ', modul'][$tab] ?? '';
            $res = $db->getSqlQuery('SELECT id, `'.implode('`, `', $cols).'`'.$more.' FROM `'.$prefix.'_'.$tab.'` WHERE id > :cur AND ('.$where.') ORDER BY id', $pars);
            if ($res === false) return null;
            $num = 0;
            foreach ($db->getSqlRows($res) ?: [] as $row) {
                $name = ($tab === 'comment' && in_array((string)$row['modul'], $kinds, true)) ? (string)$row['modul'] : '';
                if ($tab === 'nodes') $name = $kinds[intval($row['tid'])] ?? '';
                $sets = [];
                $vals = ['id' => intval($row['id'])];
                foreach ($cols as $i => $col) {
                    $text = ($tab === 'nodes' && $name === '') ? (string)$row[$col] : $fix((string)$row[$col], $name, ($tab === 'nodes') ? intval($row['id']) : 0);
                    $sum['left'] += $left($text, ($name !== '') ? $folds[$name] : []);
                    if ($text === (string)$row[$col]) continue;
                    $sum['change'][$tab.'.'.$col] = ($sum['change'][$tab.'.'.$col] ?? 0) + 1;
                    if (strlen($text) > $size[$col]) {
                        $sum['long'][] = $tab.'.'.$col.' #'.$row['id'];
                        continue;
                    }
                    $sets[] = '`'.$col.'` = :v'.$i;
                    $vals['v'.$i] = $text;
                    if ($write) $info['done'][$tab.'.'.$col] = ($info['done'][$tab.'.'.$col] ?? 0) + 1;
                }
                if ($write && $sets && $db->getSqlQuery('UPDATE `'.$prefix.'_'.$tab.'` SET '.implode(', ', $sets).' WHERE id = :id', $vals) === false) return null;
                if (!$write) continue;
                $info['cursor'][$tab] = intval($row['id']);
                if (++$num % 100 === 0 && !$save()) return null;
            }
            if ($write && !$save()) return null;
        }
        return $sum;
    };
    $list = fn(array $nums): string => $nums ? implode(', ', array_map(fn(string $k, int $v): string => $k.' '.$v, array_keys($nums), $nums)) : 'none';
    if ($dry) {
        $sum = $walk(false);
        if ($sum === null) return getUpdateRow('archive, dry run: the texts could not be read', false);
        $text = 'archive, dry run: texts that change ('.$list($sum['change']).'), addresses that stay: '.$sum['left'];
        return getUpdateRow($text.($sum['long'] ? '; too long for their column: '.implode(', ', $sum['long']) : '').'; nothing was written', true);
    }
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('archive: '.$dir.' could not be created, no address was rewritten', false);
    if (!is_array($info)) {
        $sum = $walk(false);
        if ($sum === null) return getUpdateRow('archive: the texts could not be read, no address was rewritten', false);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'count' => $sum['change'], 'left' => $sum['left'], 'cursor' => [], 'done' => []];
    }
    $raw = false;
    $memo = [];
    $info['state'] = 'applying';
    if (!$save() || $walk(true) === null) return getUpdateRow('archive: the rewrite stopped, run the update again to continue after the last written text', false);
    $sum = $walk(false);
    $miss = ($sum === null) ? -1 : array_sum($sum['change']) - count($sum['long']);
    $text = 'archive: '.(($miss < 0) ? 'the texts could not be read again' : $miss.' texts still change').' after the rewrite, run the update again';
    if ($miss !== 0) {
        $info['cursor'] = [];
        return getUpdateRow($save() ? $text : 'archive: the manifest could not be written', false);
    }
    $info['state'] = 'verified';
    $info['left'] = $sum['left'];
    if (!$save()) return getUpdateRow('archive: the manifest could not be written', false);
    $text = 'archive: file addresses of the October migration rewritten ('.$list($info['done'] ?? []).'), addresses left that name no file or material: '.$sum['left'];
    return getUpdateRow($text.($sum['long'] ? '; kept because too long for their column: '.implode(', ', $sum['long']) : ''), true);
}

# Turn every stored text the parser renders from BB into Markdown once: one rendered trusted takes getMigrateHtml(), one Markdown by origin (owner, 2026-10-25) its tags
function setUpdateTexts(Database $db, string $prefix, bool $dry, bool $six): array {
    $dir = BACKUP_DIR.'/update/texts';
    $file = $dir.'/manifest.json';
    $info = is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
    if (!$dry && ($info['state'] ?? '') === 'verified') return getUpdateRow('texts: the stored texts were turned into Markdown by an earlier run', true);
    $defs = (array)(getUpdateSource(CONFIG_DIR.'/fields.php')['fields'] ?? []);
    $area = fn(mixed $set): array => is_array($set) ? array_keys(array_filter($set, fn(mixed $v): bool => is_array($v) && ($v['type'] ?? '') === 'textarea')) : [];
    $kinds = [];
    $res = $db->getSqlQuery('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tab', ['tab' => $prefix.'_node_types']);
    $res = ($res && intval($db->getSqlRow($res)[0] ?? 0) > 0) ? $db->getSqlQuery('SELECT id, name FROM `'.$prefix.'_node_types`') : false;
    foreach (($res ? $db->getSqlRows($res) : false) ?: [] as $row) $kinds[intval($row['id'])] = $area($defs['node'][(string)$row['name']] ?? null);
    $late = $six ? '0' : 'time >= :from';
    $jobs = ['nodes' => [['intro', 'body'], '1', '', false], 'comment' => [['body'], $six ? '0' : '(modul = \'help\' OR time >= :from)', '', false],
        'forum' => [['body'], $late, '', true], 'privat' => [['body'], $late, '', false], 'message' => [['body'], '0', '', true], 'newsletter' => [['body'], '0', '', true],
        'blocks' => [['content'], '0', 'url = \'\'', true], 'users' => [['sig', 'block'], '0', '', true]];
    $names = array_filter(['nodes' => array_filter($kinds) ? fn(array $row): array => $kinds[intval($row['tid'])] ?? [] : null,
        'forum' => $area($defs['forum'] ?? null) ? fn(): array => $area($defs['forum']) : null,
        'users' => $area($defs['account'] ?? null) ? fn(): array => $area($defs['account']) : null]);
    $field = function (string $json, array $list, bool $esc, array &$seen): string {
        $data = ($list && $json !== '') ? json_decode($json, true) : null;
        if (!is_array($data)) return $json;
        $was = $data;
        foreach ($list as $one) if (is_string($data[$one] ?? null)) $data[$one] = getMigrateMarkdown($data[$one], $esc, $seen);
        return ($data === $was) ? $json : (string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    };
    $save = function () use ($file, &$info): bool {
        return setUpdateBackup($file, (string)json_encode($info, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    };
    $walk = function (bool $write) use ($db, $prefix, $jobs, $names, $field, $save, &$info): ?array {
        $sum = ['change' => [], 'seen' => [], 'long' => []];
        foreach ($jobs as $tab => [$cols, $mark, $only, $html]) {
            $res = $db->getSqlQuery('SELECT column_name, character_octet_length FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tab',
                ['tab' => $prefix.'_'.$tab]);
            $size = [];
            foreach (($res ? $db->getSqlRows($res) : false) ?: [] as $row) $size[strtolower((string)$row[0])] = intval($row[1]);
            if (array_diff($cols, array_keys($size)) || ($mark !== '0' && $mark !== '1' && !isset($size['time']))) continue;
            $test = isset($names[$tab], $size['field']) ? $names[$tab] : null;
            if ($test) $cols[] = 'field';
            $cur = $write ? intval($info['cursor'][$tab] ?? 0) : 0;
            $pend = ($write && ($info['batch']['tab'] ?? '') === $tab) ? $info['batch'] : null;
            if ($pend) {
                $pars = [];
                foreach (array_keys($pend['rows']) as $i => $id) $pars['i'.$i] = intval($id);
                $res = $db->getSqlQuery('SELECT * FROM `'.$prefix.'_'.$tab.'` WHERE id IN (:'.implode(', :', array_keys($pars)).')', $pars);
                if ($res === false) return null;
                $same = 0;
                foreach ($db->getSqlRows($res) ?: [] as $row) {
                    $one = $pend['rows'][$row['id']];
                    $same += (sha1((string)json_encode(array_map(fn(string $c): string => (string)$row[$c], $one['c']))) === $one['h']) ? 1 : 0;
                }
                if ($same !== 0 && $same !== count($pend['rows'])) return null;
                if ($same > 0) {
                    $cur = intval($pend['last']);
                    foreach ($pend['count'] as $key => $num) $info['done'][$key] = ($info['done'][$key] ?? 0) + $num;
                    $info['cursor'][$tab] = $cur;
                }
                unset($info['batch']);
                if (!$save()) return null;
            }
            $where = implode(' OR ', array_map(fn(string $v): string => '`'.$v.'` <> \'\'', $cols));
            $sql = 'SELECT id, `'.implode('`, `', $cols).'`'.(($tab === 'nodes') ? ', tid' : '').', ('.$mark.') AS md FROM `'.$prefix.'_'.$tab.'` WHERE id > :cur'
                .(($only !== '') ? ' AND '.$only : '').' AND ('.$where.') ORDER BY id LIMIT 200';
            while (true) {
                $res = $db->getSqlQuery($sql, ['cur' => $cur] + (str_contains($mark, ':from') ? ['from' => '2026-07-13 00:00:00'] : []));
                if ($res === false) return null;
                $rows = $db->getSqlRows($res) ?: [];
                if (!$rows) break;
                $sets = [];
                $count = [];
                foreach ($rows as $row) {
                    $id = intval($row['id']);
                    foreach ($cols as $col) {
                        $key = $tab.'.'.$col;
                        $seen = [];
                        $old = (string)$row[$col];
                        $new = match (true) {
                            $col === 'field' => $field($old, $test($row), !$row['md'], $seen),
                            $html && !$row['md'] => getMigrateHtml($old, true, $seen),
                            default => getMigrateMarkdown($old, !$row['md'], $seen),
                        };
                        foreach ($seen as $form => $num) $sum['seen'][$form] = ($sum['seen'][$form] ?? 0) + $num;
                        if ($new === $old) continue;
                        $sum['change'][$key] = ($sum['change'][$key] ?? 0) + 1;
                        if (strlen($new) > $size[$col]) {
                            $sum['long'][] = $key.' #'.$id;
                            continue;
                        }
                        $sets[$id][$col] = $new;
                        $count[$key] = ($count[$key] ?? 0) + 1;
                    }
                    $cur = $id;
                }
                if (!$write) continue;
                if ($sets) {
                    $info['batch'] = ['tab' => $tab, 'last' => $cur, 'count' => $count, 'rows' => array_map(fn(array $v): array => ['c' => array_keys($v),
                        'h' => sha1((string)json_encode(array_values($v)))], $sets)];
                    if (!$save() || !$db->setSqlBegin()) return null;
                    foreach ($sets as $id => $vals) {
                        $upd = 'UPDATE `'.$prefix.'_'.$tab.'` SET '.implode(', ', array_map(fn(string $v): string => '`'.$v.'` = :'.$v, array_keys($vals))).' WHERE id = :id';
                        if ($db->getSqlQuery($upd, $vals + ['id' => $id]) !== false) continue;
                        $db->setSqlRollback();
                        return null;
                    }
                    if (!$db->setSqlCommit()) return null;
                    foreach ($count as $key => $num) $info['done'][$key] = ($info['done'][$key] ?? 0) + $num;
                    unset($info['batch']);
                }
                $info['cursor'][$tab] = $cur;
                if (!$save()) return null;
            }
        }
        return $sum;
    };
    $cont = getUpdateSource(CONFIG_DIR.'/contact.php')['contact'] ?? null;
    $form = (is_array($cont) && is_string($cont['info'] ?? null)) ? getMigrateHtml($cont['info'], true) : null;
    $form = ($form !== null && $form !== $cont['info']) ? $form : null;
    $list = fn(array $nums): string => $nums ? implode(', ', array_map(fn(string $k, int $v): string => $k.' '.$v, array_keys($nums), $nums)) : 'none';
    if ($dry && is_array($info)) return getUpdateRow('texts, dry run: the unit already ran (state '.($info['state'] ?? '').'), its converted texts are not counted again', true);
    if ($dry) {
        $sum = $walk(false);
        if ($sum === null) return getUpdateRow('texts, dry run: the texts could not be read', false);
        arsort($sum['seen']);
        $text = 'texts, dry run: texts that turn into Markdown ('.$list($sum['change']).'), config/contact.php info '.(($form !== null) ? 'changes' : 'stays');
        $text .= '; forms met: '.$list($sum['seen']).($sum['long'] ? '; too long for their column, they stay BB: '.implode(', ', $sum['long']) : '');
        return getUpdateRow($text.'; nothing was written', true);
    }
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) return getUpdateRow('texts: '.$dir.' could not be created, no text was converted', false);
    if (!is_array($info)) {
        $sum = $walk(false);
        if ($sum === null) return getUpdateRow('texts: the texts could not be read, no text was converted', false);
        $info = ['version' => '6.3.0', 'state' => 'prepared', 'count' => $sum['change'], 'long' => $sum['long'], 'cursor' => [], 'done' => []];
    }
    $info['state'] = 'applying';
    if (!$save() || $walk(true) === null) return getUpdateRow('texts: the conversion stopped, run the update again to continue after the last written batch', false);
    $same = isset($info['config']) && sha1((string)($cont['info'] ?? '')) === $info['config'];
    if ($form !== null && !$same) {
        $info['config'] = sha1($form);
        $text = 'texts: config/contact.php could not be written, run the update again';
        if (!$save() || !setUpdateFile('contact.php', ['info' => $form] + $cont)) return getUpdateRow($text, false);
    }
    $info['state'] = 'verified';
    if (!$save()) return getUpdateRow('texts: the manifest could not be written', false);
    $miss = array_sum($info['count']) - count($info['long']) - array_sum($info['done'] ?? []);
    $text = 'texts: BB of the stored texts turned into Markdown ('.$list($info['done'] ?? []).')'.($info['long'] ? '; kept as BB because too long for their column: '
        .implode(', ', $info['long']) : '');
    return getUpdateRow($text.(($miss !== 0) ? '; '.$miss.' texts the count of the first pass named were not written' : ''), $miss === 0);
}

# Drop the tables of the modules gone for good and every source table of Node whose rows all are materials now, an empty one at once
# A published row is carried when _node_legacy names it; a pending row has no address there and is carried when its type holds a material of its title and creation time
# A table with a row matched by neither stays and the report names its ids: on a 6.2 site the second stage carries those rows and drops the table after them
function setUpdateTables(Database $db, string $prefix): array {
    $map = getMigrateMap();
    $names = array_merge(['auto_links', 'clients', 'clients_down', 'money', 'order', 'partners', 'products', 'whois'], array_keys($map));
    $pars = [];
    foreach ($names as $i => $one) $pars['t'.$i] = $prefix.'_'.$one;
    $res = $db->getSqlQuery('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (:'.implode(', :', array_keys($pars)).')', $pars);
    if ($res === false) return getUpdateRow('tables of the removed modules could not be read, none was dropped', false);
    $have = array_map(fn(string $v): string => substr($v, strlen($prefix) + 1), array_column($db->getSqlRows($res) ?: [], 0));
    $gone = array_values(array_diff(array_intersect($names, $have), array_keys($map)));
    $rows = [];
    $drop = [];
    $keep = [];
    foreach (array_intersect(array_keys($map), $have) as $mod) {
        $tab = '`'.$prefix.'_'.$mod.'`';
        $res = $db->getSqlQuery('SHOW COLUMNS FROM '.$tab.' LIKE \'status\'');
        $pend = $res !== false && $mod !== 'help' && $db->getSqlRowCount($res) > 0;
        $sql = 'SELECT s.id FROM '.$tab.' AS s WHERE '.(($mod === 'help') ? 's.pid = 0 AND ' : '')
            .'NOT EXISTS (SELECT 1 FROM `'.$prefix.'_node_legacy` AS l WHERE l.modul = :mod AND l.oid = s.id)';
        if ($pend) {
            $sql .= ' AND NOT (s.status = 0 AND EXISTS (SELECT 1 FROM `'.$prefix.'_nodes` AS n INNER JOIN `'.$prefix.'_node_types` AS t ON t.id = n.tid WHERE t.name = :type'
                .' AND n.created = s.time AND n.title COLLATE utf8mb4_bin = CONVERT(TRIM(s.title) USING utf8mb4) COLLATE utf8mb4_bin))';
        }
        $left = ($res === false) ? false : $db->getSqlQuery($sql.' ORDER BY s.id', ['mod' => $mod] + ($pend ? ['type' => $map[$mod]['type']] : []));
        if ($left === false) {
            $rows = array_merge($rows, getUpdateRow($prefix.'_'.$mod.': the rows could not be compared with Node, the table stays', false));
            continue;
        }
        $ids = array_map('intval', array_column($db->getSqlRows($left) ?: [], 0));
        if ($ids) $keep[] = $prefix.'_'.$mod.' '.count($ids).' (ids: '.implode(', ', array_slice($ids, 0, 10)).(count($ids) > 10 ? ', …' : '').')';
        else $drop[] = $mod;
    }
    foreach (['tables of the removed modules dropped: ' => $gone, 'source tables of Node dropped, every row is a material: ' => $drop] as $text => $list) {
        if (!$list) continue;
        $bad = array_values(array_filter($list, fn(string $v): bool => $db->getSqlQuery('DROP TABLE `'.$prefix.'_'.$v.'`') === false));
        $rows = array_merge($rows, getUpdateRow($prefix.' '.$text.implode(', ', $list).($bad ? '; could not be dropped: '.implode(', ', $bad) : ''), !$bad));
    }
    return array_merge($rows, $keep ? getUpdateRow('source tables of Node kept, rows not in Node yet: '.implode('; ', $keep), true) : []);
}

# The language names 6.2 stored in its settings and rows, each with the code of its lang/ file
function getUpdateLangs(): array {
    return ['english' => 'en', 'french' => 'fr', 'german' => 'de', 'polish' => 'pl', 'russian' => 'ru', 'ukrainian' => 'uk'];
}

# The language unit of the 6.3 data update: a 6.2 name in a language column becomes its code, and an account keeping a language no lang/ file serves takes the site one
function setUpdateLangs(Database $db, string $prefix): array {
    $good = true;
    $done = 0;
    foreach (['admins', 'blocks', 'categories', 'message', 'users', 'voting'] as $tab) {
        foreach (getUpdateLangs() as $name => $code) {
            $res = $db->getSqlQuery('UPDATE `'.$prefix.'_'.$tab.'` SET lang = :code WHERE LOWER(lang) = :name', ['code' => $code, 'name' => $name]);
            $good = $good && $res !== false;
            $done += $res ? intval($db->getSqlRowCount($res)) : 0;
        }
    }
    $pars = [];
    foreach (glob(BASE_DIR.'/lang/*.php') ?: [] as $i => $file) $pars['l'.$i] = basename($file, '.php');
    $res = $pars ? $db->getSqlQuery('UPDATE `'.$prefix.'_users` SET lang = \'\' WHERE lang != \'\' AND lang NOT IN (:'.implode(', :', array_keys($pars)).')', $pars) : false;
    $good = $good && $res !== false;
    $text = 'languages: the names of 6.2 turned into codes (rows: '.$done.'), accounts without a served language back on the site one (';
    return getUpdateRow($text.($res ? intval($db->getSqlRowCount($res)) : 0).')', $good);
}

# Answer a failed row per address held twice in one account table, compared in the collation of the unique key the schema file builds; a clean check answers none
function checkUpdateEmails(Database $db, string $prefix): array {
    $coll = (string)(getUpdateCred()['collate'] ?? '');
    if (!preg_match('/^[a-z0-9_]{1,64}$/D', $coll)) return getUpdateRow('config/db.php names no valid collation, the addresses cannot be compared', false);
    $rows = [];
    foreach (['admins', 'users', 'users_temp'] as $tab) {
        $res = $db->getSqlQuery('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl', ['tbl' => $prefix.'_'.$tab]);
        $cols = $res ? array_column($db->getSqlRows($res) ?: [], 0) : [];
        if (!$cols) continue;
        [$id, $name, $mail] = array_map(fn($v) => in_array('user_'.$v, $cols, true) ? 'user_'.$v : $v, ['id', 'name', 'email']);
        $sql = 'SELECT MIN(`'.$mail.'`), GROUP_CONCAT(CONCAT(`'.$id.'`, \' (\', `'.$name.'`, \')\') ORDER BY `'.$id.'` SEPARATOR \', \') FROM `'.$prefix.'_'.$tab.'`'
            .' WHERE `'.$mail.'` != \'\' GROUP BY CONVERT(`'.$mail.'` USING utf8mb4) COLLATE '.$coll.' HAVING COUNT(*) > 1 ORDER BY 1';
        $res = $db->getSqlQuery($sql);
        if ($res === false) $rows = array_merge($rows, getUpdateRow($prefix.'_'.$tab.': the addresses could not be compared', false));
        foreach (($res ? $db->getSqlRows($res) : []) ?: [] as [$addr, $list]) {
            $rows = array_merge($rows, getUpdateRow($prefix.'_'.$tab.': the address '.$addr.' is held by the accounts '.$list.', leave it on one of them by hand', false));
        }
    }
    return $rows;
}

# The configuration step of the 6.3 update for a 6.2 site, whose settings live in config/config_<name>.php as a variable of their own
# The site values go over the shipped source of the same name, stat into statistic and seo over global; an unshipped key stays for the data units and the next form save
# The version, the asset lists and the closed site belong to the release and the update, and a language name becomes its code
# A start module, a theme or a site logo that is not in the tree falls back to the shipped value; a global key naming a configuration area would shadow it and is dropped
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
    $langs = getUpdateLangs();
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
            unset($site['version'], $site['css_f'], $site['script_f'], $site['script_a'], $site['script_b'], $site['amod']);
            unset($site['rewrite'], $site['sep'], $site['tsep'], $site['title'], $site['ctitle'], $site['clickable']);
            foreach (glob(CONFIG_DIR.'/*.php') ?: [] as $one) unset($site[basename($one, '.php')]);
            if (isset($site['theme']) && !is_dir(PUBLIC_DIR.'/templates/'.basename((string)$site['theme']))) unset($site['theme']);
            $look = PUBLIC_DIR.'/templates/'.basename((string)($site['theme'] ?? $base['theme'] ?? '')).'/images/logos/';
            if (isset($site['site_logo']) && !is_file($look.basename((string)$site['site_logo']))) unset($site['site_logo']);
        }
        if ($into === 'lang' && isset($site['lang'])) $site['lang'] = $langs[$site['lang']] ?? $site['lang'];
        if ($into === 'comments') unset($site['alink']);
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

# The settings step of a 6.3 site: the keys no code reads leave, then every option of the shipped config/ the copy keeps in storage/backup/release the site lacks is added
function setUpdateMerge(): array {
    require_once BASE_DIR.'/core/classes/update.php';
    $pack = BACKUP_DIR.'/release/config';
    if (!is_dir($pack)) return getUpdateRow('config: the shipped settings are not in storage/backup/release/config, put the config/ of the release there and run again', false);
    $gone = ['global' => '/^(rewrite|sep|tsep|title|ctitle|clickable|dbsync|version|cache_.+|css_(?!f$).+|script_(?!f$).+)$/D',
        'users' => '/^(anonym|network|network_c|point|points)$/D', 'newsletter' => '/^(active|count)$/D', 'voting' => '/^bar$/D', 'comments' => '/^alink$/D'];
    $cut = [];
    foreach ($gone as $name => $find) {
        $data = getUpdateSource(CONFIG_DIR.'/'.$name.'.php');
        $area = ($name === 'global') ? $data : ($data[$name] ?? []);
        $keep = array_filter($area, fn(int|string $k): bool => !preg_match($find, (string)$k), ARRAY_FILTER_USE_KEY);
        if ($keep === $area) continue;
        if (!setUpdateFile($name.'.php', $keep)) return getUpdateRow('config/'.$name.'.php could not be written, the update stops before the schema', false);
        $cut[] = $name.' '.implode(' ', array_keys(array_diff_key($area, $keep)));
    }
    $packs = [];
    $sites = [];
    foreach (glob($pack.'/*.php') ?: [] as $file) {
        $name = basename($file, '.php');
        if (!Update::isSiteFile('config/'.$name.'.php')) continue;
        $one = getUpdateSource($file);
        $text = 'config: the shipped '.$name.'.php holds no '.$name.' area, the update stops before the schema';
        if ($name !== 'global' && !is_array($one[$name] ?? null)) return getUpdateRow($text, false);
        $site = getUpdateSource(CONFIG_DIR.'/'.$name.'.php');
        $packs[$name] = ($name === 'global') ? $one : $one[$name];
        $sites[$name] = ($name === 'global') ? $site : ($site[$name] ?? []);
    }
    $types = array_values(array_unique(array_merge(array_keys((array)($packs['node']['types'] ?? [])), array_keys((array)($sites['node']['types'] ?? [])))));
    $adds = [];
    foreach ($packs as $name => $one) {
        $res = Update::getConfigMerge($sites[$name], $one, $types, ($name === 'global') ? '' : $name);
        if (!$res['adds']) continue;
        if (!setUpdateFile($name.'.php', $res['data'], [], in_array($name, ['node', 'fields', 'uploads', 'ratings'], true))) {
            return getUpdateRow('config/'.$name.'.php could not be written, the update stops before the schema', false);
        }
        array_push($adds, ...$res['adds']);
    }
    $text = 'config: the keys no code reads taken out ('.($cut ? implode('; ', $cut) : 'none').'), the shipped options the site lacked added (';
    return getUpdateRow($text.($adds ? implode(', ', $adds) : 'none').')', true);
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
            .' VALUES (\'newsletter\', :from, :mail, :title, \'\', :ref, 3, NOW(), UNIX_TIMESTAMP())';
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
    $coll = (string)(getUpdateCred()['collate'] ?? '');
    foreach ($list['statements'] as $sql) {
        $sql = str_replace(['{prefix}', '{engine}', '{charset}', '{collate}'], [$prefix, 'InnoDB', 'utf8mb4', $coll], $sql);
        $res = $db->getSqlQuery($sql);
        $info = getSqlinfo($sql);
        $gone = ($res && $info['type'] === 'DELETE') ? ' (rows removed: '.intval($db->getSqlRowCount($res)).')' : '';
        if ($info['table'] !== '') $rows = array_merge($rows, getUpdateRow($info['table'].$gone, $res !== false));
        elseif (!$res) $rows = array_merge($rows, getUpdateRow($info['type'], false));
    }
    return $rows;
}

# Run the 6.3 update of the site config/db.php names and answer its report rows; a refusal before the preflight changes nothing, a dry run only counts addresses and texts
function setUpdateRun(bool $dry = false): array {
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
    $base = ['engine' => 'InnoDB', 'charset' => 'utf8mb4'];
    try {
        $db = new Database((string)($cred['host'] ?? ''), (string)($cred['uname'] ?? ''), (string)($cred['pass'] ?? ''), (string)$cred['name'], (string)$cred['collate']);
    } catch (RuntimeException $err) {
        return getUpdateRow($err->getMessage(), false);
    }
    $stop = checkUpdateBase($db, $pref);
    if ($stop !== '') return getUpdateRow($stop, false);
    $six = glob(CONFIG_DIR.'/config_*.php') || is_dir(BACKUP_DIR.'/update/config');
    if ($dry) return array_merge(setUpdateArchive($db, $pref, true), setUpdateTexts($db, $pref, true, $six));
    $rows = checkUpdateEmails($db, $pref);
    $text = 'the update stopped before it changed anything: leave each address above on one account and run it again';
    if (checkUpdateFail($rows)) return array_merge($rows, getUpdateRow($text, false));
    if (!setUpdateFile('global.php', array_diff_key($conf, ['security' => '', 'db' => '']), ['close' => '1'])) return getUpdateRow('config/global.php could not be written', false);
    $rows = getUpdateRow('the site is closed for the data update (close = 1), open it in the settings after the result is checked', true);
    $rows = array_merge($rows, setUpdateConfig(), $six ? [] : setUpdateMerge());
    $conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
    $from = is_file(PUBLIC_DIR.'/admin.php') ? 'admin' : $spanel;
    $afile = $spanel;
    if ($afile !== $from && (!is_file(PUBLIC_DIR.'/'.$from.'.php') || !rename(PUBLIC_DIR.'/'.$from.'.php', PUBLIC_DIR.'/'.$afile.'.php'))) $afile = $from;
    $left = PUBLIC_DIR.'/'.$spanel.'.php';
    if (!in_array(strtolower($spanel), ['admin', 'index', 'setup', 'update', strtolower($afile)], true) && is_file($left)) unlink($left);
    if (!setUpdateFile('security.php', $conf['security'], ['afile' => $afile])) return array_merge($rows, getUpdateRow('config/security.php could not be written', false));
    $conf = array_merge($conf, require CONFIG_DIR.'/security.php');
    $dbc = array_diff_key($cred, array_flip(['mode', 'sync', 'type']));
    if (!setUpdateFile('db.php', $dbc, $base)) return array_merge($rows, getUpdateRow('config/db.php could not be written', false));
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
    if (is_array($sched) && !isset($sched['jobs']['selfcheck'])) {
        $sched['jobs']['selfcheck'] = [
            'title' => 'Self-check',
            'type' => 'system',
            'active' => '1',
            'system' => 'selfcheck',
            'schedule' => '20 * * * *',
            'priority' => '10',
            'lock_timeout' => '300',
            'manual' => '1',
            'settings' => [],
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
    $smark = getUpdateSource(CONFIG_DIR.'/update.php')['update'] ?? [];
    if (!isset($smark['steps']) && !setUpdateFile('update.php', $smark + ['steps' => [SL_VERSION => date('Y-m-d H:i:s')]])) {
        $rows = array_merge($rows, getUpdateRow('config/update.php: the step mark of the code could not be written, the site stays closed', false));
    }
    $rows = array_merge($rows, setUpdatePoints($db, $pref), setUpdateRatings($db, $pref), setUpdateFields($db, $pref));
    $rows = array_merge($rows, setUpdateAttach($db, $pref, 'forum', 'forum'), setUpdateAttach($db, $pref, 'privat', 'account'), setUpdateLangs($db, $pref),
        setUpdateArchive($db, $pref, false), setUpdateTexts($db, $pref, false, $six));
    $rdata = getUpdateSource(CONFIG_DIR.'/rss.php')['rss'] ?? [];
    if ($rdata !== [] && (isset($rdata['temp']) || !isset($rdata['bytes'], $rdata['redirects'], $rdata['timeout']))) {
        unset($rdata['temp']);
        $rdata += ['bytes' => '2097152', 'redirects' => '3', 'timeout' => '10'];
        if (!setUpdateFile('rss.php', $rdata)) $rows = array_merge($rows, getUpdateRow('config/rss.php could not be written', false));
    }
    $rnum = $db->getSqlQuery('UPDATE `'.$pref.'_blocks` SET content = \'\', time = 0 WHERE url != \'\'');
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
    $rows = array_merge($rows, setUpdateTables($db, $pref));
    if (checkUpdateFail($rows)) return $rows;
    $gone = 0;
    foreach (glob(BACKUP_DIR.'/update/*/*') ?: [] as $file) if (basename($file) !== 'manifest.json' && unlink($file)) $gone++;
    $rows = array_merge($rows, getUpdateRow('the snapshots of the update are deleted ('.$gone.' files), the manifests stay in storage/backup/update', true));
    return $rows;
}

# The link tags of the admin theme gathered as the core gathers them for the same card: the icon, the stylesheets of its vendor packages and its own
function getUpdateLinks(Template $tpl): string {
    $base = PUBLIC_DIR.'/templates/admin/';
    $out = [$tpl->getHtmlFrag('head-link', ['rel' => 'shortcut icon', 'href' => 'templates/admin/images/favicon.svg', 'type' => 'image/svg+xml', 'title' => ''])];
    $list = glob($base.'*.css') ?: [];
    foreach (glob($base.'assets/vendor/*/', GLOB_ONLYDIR) ?: [] as $sub) $list = array_merge($list, glob($sub.'*.css') ?: [], glob($sub.'*/*.css') ?: []);
    foreach (array_merge($list, glob($base.'assets/css/*.css') ?: []) as $file) {
        $out[] = $tpl->getHtmlFrag('head-link', ['rel' => 'stylesheet', 'href' => Template::getAssetUrl(substr($file, strlen(PUBLIC_DIR) + 1)), 'type' => '', 'title' => '']);
    }
    return implode("\n", $out);
}

# Render the first stage on the login card of the admin theme: what the run does, the report of a run or a dry run with its verdict, and the buttons that start them
function setUpdatePage(array $rows, bool $dry = false): void {
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
        if ($dry) $done = 'The dry run finished, nothing was changed.';
        $cont .= $tpl->getHtmlFrag('alert', checkUpdateFail($rows) ? ['type' => 'error', 'text' => $fail] : ['type' => 'success', 'text' => $done]);
    }
    $hide = $tpl->getHtmlFrag('hidden', ['name_attr' => 'op', 'value_attr' => 'update', 'input_attr' => '']);
    $text = 'Run the update';
    $cont .= $tpl->getHtmlFrag('post-button', ['action' => 'update.php', 'hidden' => $hide, 'icon_name' => 'play-circle', 'title' => $text, 'label' => $text]);
    $hide .= $tpl->getHtmlFrag('hidden', ['name_attr' => 'dry', 'value_attr' => '1', 'input_attr' => '']);
    $text = 'Count the changes';
    $cont .= $tpl->getHtmlFrag('post-button', ['action' => 'update.php', 'hidden' => $hide, 'icon_name' => 'search', 'title' => $text, 'label' => $text]);
    $meta = $tpl->getHtmlFrag('head-title', ['title' => 'SLAED CMS 6.3 update'])."\n".$tpl->getHtmlFrag('head-meta', ['name' => 'robots', 'content' => 'noindex, nofollow']);
    $logo = 'templates/admin/images/logos/'.basename((string)($conf['admin_logo'] ?? ''));
    if (!is_file(PUBLIC_DIR.'/'.$logo)) $logo = 'templates/admin/images/logos/slaed-logo-wordmark-gradient-blue.svg';
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
    define('CACHE_DIR', BASE_DIR.'/storage/cache');
    define('LOGS_DIR', BASE_DIR.'/storage/logs');
    define('UPLOADS_DIR', BASE_DIR.'/uploads');
    define('HOME_PATH', rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/').'/');
    $conf = array_merge(require CONFIG_DIR.'/global.php', require CONFIG_DIR.'/security.php');
    require_once BASE_DIR.'/core/admin.php';
    require_once BASE_DIR.'/core/classes/filemanager.php';
    require_once BASE_DIR.'/core/classes/logger.php';
    require_once BASE_DIR.'/core/classes/pdo.php';
    require_once BASE_DIR.'/core/classes/template.php';
    require_once BASE_DIR.'/lang/en.php';
    require_once BASE_DIR.'/core/version.php';
    ini_set('display_errors', $conf['security']['error'] > 0 ? '1' : '0');
    error_reporting([0, E_ALL ^ E_NOTICE, E_ALL][intval($conf['security']['error'])] ?? 0);
    set_time_limit(0);
    date_default_timezone_set((string)(getUpdateSource(CONFIG_DIR.'/config_global.php')['gtime'] ?? $conf['gtime']));
    $spanel = (string)(getUpdateSource(CONFIG_DIR.'/config_security.php')['afile'] ?? $conf['security']['afile'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $spanel) || in_array(strtolower($spanel), ['index', 'setup', 'update'], true)) $spanel = 'admin';
    $udry = ($_POST['dry'] ?? '') === '1';
    setUpdatePage((($_POST['op'] ?? '') === 'update') ? setUpdateRun($udry) : [], $udry);
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
