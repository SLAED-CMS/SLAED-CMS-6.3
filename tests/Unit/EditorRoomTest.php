<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 6 of docs/EDITOR-UPLOADS-2026.md, the half a running stand is not needed for. The room table
 * is held against the shipped schema in both directions, every call site is held to a storage the
 * table carries, and the write guard is driven through tests/Support/editor_probe.php against the
 * real core so the bytes it measures are the bytes a request would produce. The client half is read
 * off the two files that own it: the cap and the type list reach the editor from PHP, and all three
 * routes into the embed path pass the same guard. Nothing here writes to the site.
 */
final class EditorRoomTest extends TestCase
{
    private const BODIES = [
        'comment.body', 'content.body', 'faq.body', 'files.body', 'forum.body', 'help.body', 'jokes.body',
        'links.body', 'media.note', 'message.body', 'money.note', 'news.body', 'newsletter.body',
        'order.note', 'pages.body', 'privat.body', 'products.body',
    ];

    private const SUMMARIES = [
        'auto_links.intro', 'files.intro', 'links.intro', 'media.intro', 'money.intro', 'news.intro',
        'order.info', 'pages.intro', 'products.intro', 'users.block', 'users.sig',
    ];

    private static array $files = [];
    private static array $probe = [];

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Run the room probe in a fresh process and memoize its report, handing it the store names this run scanned out of the tree
    # The list travels as a file and not as an argument, because a Windows shell strips the quotes out of an argument and would hand the probe a broken document
    private function getProbe(): array
    {
        if (self::$probe !== []) return self::$probe;
        $script = dirname(__DIR__).'/Support/editor_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_editor_room';
        if (!is_dir($work)) mkdir($work, 0777, true);
        $list = $work.'/stores.json';
        file_put_contents($list, (string)json_encode(array_values(array_unique(array_merge(array_keys($this->getTable()), array_keys($this->getCalls()['named']))))));
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($list).' '.escapeshellarg($work).' 2>&1';
        $out = (string)shell_exec($cmd);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The room probe did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('The room probe failed: '.$data['error']);
        return self::$probe = $data;
    }

    # The room table as the shipped function carries it, read off the one literal that defines it so the test and the runtime cannot hold two different tables
    private function getTable(): array
    {
        $code = $this->getFile('core/helpers.php');
        $from = strpos($code, 'function getEditorRoomData(');
        $this->assertNotFalse($from, 'The room table is gone from core/helpers.php');
        $stop = strpos($code, '$byte = [', $from);
        $this->assertNotFalse($stop, 'The room table no longer ends at the type converter');
        preg_match_all("#'([a-z_]+(?:\.[a-z_]+)?)' => '([a-z]+)'#", substr($code, $from, $stop - $from), $hits, PREG_SET_ORDER);
        $out = [];
        foreach ($hits as $hit) $out[$hit[1]] = $hit[2];
        $this->assertNotEmpty($out, 'The room table holds no entry at all');
        return $out;
    }

    # Every column the fresh schema declares, as table.column => type, which is what a room entry has to agree with
    private function getSchema(): array
    {
        preg_match_all('#CREATE\s+TABLE\s+`\{prefix\}_([a-z0-9_]+)`\s*\((.*?)\n\)\s*ENGINE#is', $this->getFile('setup/sql/table.sql'), $blocks, PREG_SET_ORDER);
        $out = [];
        foreach ($blocks as $block) {
            foreach (explode("\n", $block[2]) as $line) {
                if (!preg_match('#^\s*`([a-z0-9_]+)`\s+([A-Za-z]+)#', $line, $col)) continue;
                $out[$block[1].'.'.strtolower($col[1])] = strtolower($col[2]);
            }
        }
        $this->assertNotEmpty($out, 'setup/sql/table.sql parsed to no column at all');
        return $out;
    }

    # Every PHP file of the shipped tree that can hold a call site, which is every one of them outside the development directories
    private function getSources(): array
    {
        $base = dirname(__DIR__, 2);
        $out = [];
        $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));
        foreach ($walk as $item) {
            $path = str_replace('\\', '/', (string)$item);
            $rel = substr($path, strlen($base) + 1);
            if (!str_ends_with($rel, '.php')) continue;
            if (preg_match('#^(vendor|node_modules|storage|tests|setup|\.git)/#', $rel)) continue;
            $out[$rel] = $path;
        }
        return $out;
    }

    # Every storage one call site declares, as store => the call sites that declare it, plus the call sites that declare none
    # The argument list is walked with balanced parentheses and not cut at a fixed length, because these arrays carry nested calls and a window would read into the next statement
    private function getCalls(): array
    {
        $out = ['named' => [], 'bare' => []];
        foreach ($this->getSources() as $rel => $path) {
            $code = (string)file_get_contents($path);
            $from = 0;
            while (preg_match('#getTpl(?:Ajax)?Textarea\(#', $code, $hit, PREG_OFFSET_CAPTURE, $from)) {
                $open = (int)$hit[0][1] + strlen($hit[0][0]) - 1;
                $arg = $this->getArgument($code, $open);
                $from = $open + max(1, strlen($arg));
                if ($rel === 'core/helpers.php') continue;
                $line = substr_count(substr($code, 0, $open), "\n") + 1;
                $spot = $rel.':'.$line;
                if (preg_match("#'store'\s*=>\s*'([^']*)'#", $arg, $key)) $out['named'][$key[1]][] = $spot;
                else $out['bare'][] = $spot;
            }
        }
        return $out;
    }

    # The argument text of one call, from its opening parenthesis to the one that closes it, with quoted strings skipped so a bracket in a label never unbalances the walk
    private function getArgument(string $code, int $open): string
    {
        $deep = 0;
        $len = strlen($code);
        for ($i = $open; $i < $len; $i++) {
            $chr = $code[$i];
            if ($chr === "'" || $chr === '"') {
                $i = $this->getStringEnd($code, $i);
                continue;
            }
            if ($chr === '(') $deep++;
            if ($chr === ')' && --$deep === 0) return substr($code, $open, $i - $open + 1);
        }
        return substr($code, $open);
    }

    # The body of the one entry point every editor is registered through, so a claim about what runs for every visitor is made about that body and not about the file around it
    private function getEntry(string $js): string
    {
        $from = strpos($js, 'api.addUpload = function(id, ed, opt) {');
        $this->assertNotFalse($from, 'The editor entry point is gone, so nothing registers the hook or the icon at all');
        $stop = strpos($js, "\n    };", $from);
        $this->assertNotFalse($stop, 'The editor entry point has no closing brace at its own indentation');
        return substr($js, $from, $stop - $from);
    }

    # How many open template conditions stand over the first appearance of one marker, so a claim about what a visitor is shown is made about nesting and not about source order
    # A textual test cannot tell a block that precedes the marker from one that encloses it, and the whole point of the address field is that no condition encloses it at all
    private function getDepth(string $tpl, string $mark): int
    {
        $at = strpos($tpl, $mark);
        $this->assertNotFalse($at, $mark.' is gone from the window markup');
        $head = substr($tpl, 0, $at);
        return preg_match_all('#\{%\s*if\s#', $head) - preg_match_all('#\{%\s*endif\s*%\}#', $head);
    }

    # The offset of the quote that closes the string starting at the given quote, escapes honoured
    private function getStringEnd(string $code, int $from): int
    {
        $quote = $code[$from];
        $len = strlen($code);
        for ($i = $from + 1; $i < $len; $i++) {
            if ($code[$i] === '\\') {
                $i++;
                continue;
            }
            if ($code[$i] === $quote) return $i;
        }
        return $len - 1;
    }

    # No entry of the room table misstates the schema: a store names a real column and the type beside it is the type the fresh install creates
    #[Test]
    public function noRoomEntryMisstatesTheShippedSchema(): void
    {
        $schema = $this->getSchema();
        foreach ($this->getTable() as $store => $kind) {
            if ($kind === 'config') continue;
            $this->assertArrayHasKey($store, $schema, 'The room table carries '.$store.', which the fresh schema does not declare at all');
            $this->assertSame($schema[$store], $kind, 'The room table calls '.$store.' a '.$kind.' and setup/sql/table.sql creates it as a '.$schema[$store]);
        }
    }

    # No store resolves to a third column type: a VARCHAR behind a rich editor is a defect, and this is the test that finds the next one without anybody reading the schema by hand
    #[Test]
    public function noStoreResolvesToAThirdColumnType(): void
    {
        foreach ($this->getTable() as $store => $kind) {
            $this->assertContains($kind, ['text', 'mediumtext', 'config'], 'The room table gives '.$store.' the kind '.$kind.', which is neither type the editor may write into');
        }
        $schema = $this->getSchema();
        foreach (array_keys($this->getTable()) as $store) {
            if (!isset($schema[$store])) continue;
            $this->assertContains($schema[$store], ['text', 'mediumtext'], 'The editor writes into '.$store.', which the schema creates as a '.$schema[$store]);
        }
    }

    # The two classes of the contract are the table itself, so a column moved quietly from one class to the other fails here rather than in a lost post
    #[Test]
    public function bothClassesOfTheContractKeepTheirMembers(): void
    {
        $table = $this->getTable();
        foreach (self::BODIES as $store) {
            $this->assertSame('mediumtext', $table[$store] ?? '', $store.' is no longer a body field, so an embed at the cap would not fit the column');
        }
        foreach (self::SUMMARIES as $store) {
            $this->assertSame('text', $table[$store] ?? '', $store.' is no longer a summary field, so a data URI drawn on every row of a list page would be accepted');
        }
        $wide = array_keys(array_filter($table, static fn(string $kind): bool => $kind === 'mediumtext'));
        $this->assertSame(count(self::BODIES), count($wide), 'The wide class gained or lost a member: '.implode(', ', array_diff($wide, self::BODIES)));
    }

    # Every call site names a storage the table carries, and a call site that names none is allowed and answered with the narrowest field there is
    #[Test]
    public function everyCallSiteNamesAStorageTheTableCarries(): void
    {
        $calls = $this->getCalls();
        $table = $this->getTable();
        $this->assertNotEmpty($calls['named'], 'No call site of the editor declares a storage at all');
        $this->assertSame([], $calls['bare'], 'These call sites name no storage and take the strict default instead: '.implode(', ', $calls['bare']));
        foreach ($calls['named'] as $store => $spots) {
            $this->assertArrayHasKey($store, $table, 'The call site '.$spots[0].' declares the storage '.$store.', which the room table does not carry');
        }
        $rooms = $this->getProbe()['rooms'];
        $this->assertSame('text', (string)($rooms['']['kind'] ?? ''), 'A call site with no storage no longer takes the room of TEXT');
        $this->assertFalse((bool)($rooms['']['embed'] ?? true), 'A call site with no storage offers the embed mode, so a forgotten one costs a post');
    }

    # Every store resolves at runtime to the type the literal states and the byte count that follows from it, and may embed only when a whole data URI of the cap fits
    #[Test]
    public function everyRoomResolvesToTheBytesItsTypeGives(): void
    {
        $data = $this->getProbe();
        $wide = intdiv((int)$data['embedmax'] + 2, 3) * 4 + 32;
        foreach ($this->getTable() as $store => $kind) {
            $room = $data['rooms'][$store] ?? [];
            $this->assertSame($kind, (string)($room['kind'] ?? ''), 'getEditorRoomData() answers a kind for '.$store.' that its own table does not state');
            $this->assertSame($kind === 'mediumtext' ? 16777215 : 65535, (int)($room['bytes'] ?? 0), 'The room of '.$store.' is not the byte count its type gives');
            $this->assertSame((int)($room['bytes'] ?? 0) >= $wide, (bool)($room['embed'] ?? false), 'Whether '.$store.' may embed is not derived from the room it has');
        }
    }

    # A body one byte past its column is refused with a message and never reaches the database, and the same holds where the column runs out at half the character count
    # The Cyrillic pair decides whether the guard works at all on the sites this project serves: cyrover is 32768 characters and 65536 bytes, so a character count passes it
    #[Test]
    public function theGuardMeasuresBytesAndRefusesBeforeTheQuery(): void
    {
        $data = $this->getProbe();
        $says = $data['says'];
        $guard = $data['guard'];
        $this->assertSame('', $guard['fit'], 'A text of exactly the column length was refused');
        $this->assertSame($says['long'], $guard['over'], 'A text one byte past its column was not refused with the length message');
        $this->assertSame('', $guard['cyrfit'], 'A Cyrillic text inside its column was refused');
        $this->assertSame($says['long'], $guard['cyrover'], 'A Cyrillic text past its column was measured as characters, so ERROR 1406 answers instead of the guard');
        $this->assertSame('', $guard['wide'], 'A body column was bounded by the length of a summary column');
        $this->assertSame($says['long'], $guard['cfgover'], 'A value written into a configuration file is unbounded, though it is loaded on every request that reads that file');
    }

    # The embed contract is size and type together, and a field that may not embed refuses a data URI at any size while a linked image into the same field is accepted
    #[Test]
    public function theGuardHoldsTheEmbedContractBySizeAndByType(): void
    {
        $guard = $this->getProbe()['guard'];
        $says = $this->getProbe()['says'];
        $this->assertSame('', $guard['edge'], 'An embed of exactly the cap was refused by the field the widening exists for');
        $this->assertSame($says['size'], $guard['big'], 'An embed one byte past the cap was not refused');
        $this->assertSame('', $guard['png'], 'An embed of an allowed type well inside the cap was refused');
        $this->assertSame($says['noembed'], $guard['small'], 'A summary field accepted a small data URI, which is then drawn onto every row of a list page');
        $this->assertSame('', $guard['link'], 'An image inserted by its address was refused by a summary field, so the restriction is about graphics rather than about delivery');
        $this->assertSame('', $guard['prose'], 'Plain prose inside its column was refused');
        $this->assertSame($says['file'], $guard['pdf'], 'A document data URI was stored, counted against the column and then silently not rendered');
        $this->assertSame($says['file'], $guard['svg'], 'An SVG data URI was accepted by a field the parser will not draw it in');
        $this->assertSame($says['file'], $guard['odd'], 'A raster type the parser does not admit was accepted');
        $this->assertSame($says['file'], $guard['tail'], 'Only the first data URI of a text is measured, so a second one walks past the guard');
        $this->assertSame($says['noembed'], $guard['none'], 'An unknown storage was answered permissively instead of with the narrowest field there is');
    }

    # The cap and the list of embeddable types reach the editor from PHP, so each has one definition and the JavaScript carries no copy of either
    #[Test]
    public function theEditorReadsTheCapAndTheTypeListFromPhp(): void
    {
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringContainsString("'embedmax' => Parser::EMBEDMAX,", $drv, 'The editor no longer receives the cap from the one constant that defines it');
        $this->assertStringContainsString("'embedimg' => Parser::EMBEDIMG,", $drv, 'The editor no longer receives the type list from the one array that defines it');
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
        $this->assertStringContainsString('getOpt(id).embedimg', $js, 'The editor no longer reads the type list it was handed');
        $this->assertStringContainsString('opt.embedmax', $js, 'The editor no longer reads the cap it was handed');
        $this->assertSame(0, preg_match('#65536|87384#', $js), 'The editor restates the cap as a number of its own, which is a second definition that can drift');
        foreach ($this->getProbe()['embedimg'] as $type) {
            $this->assertSame(0, preg_match("#'".$type."'#", $js), 'The editor spells the type '.$type.' into its own source instead of reading the one list');
        }
    }

    # All three routes into the embed path pass the same guard, because a picked, a dropped and a pasted file are one entry point and not three
    # The blob hook is registered unconditionally, which is what stops the vendor default from base64 encoding a file into the body with no bound at all when uploading is denied
    #[Test]
    public function everyRouteIntoTheEmbedPathPassesTheSameGuard(): void
    {
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
        $note = 'The blob hook is not registered as a statement of its own, so a condition can stand in front of it';
        $this->assertMatchesRegularExpression('#\n\s*addHook\(id, ed\);#', $this->getEntry($js), $note);
        $hook = (int)strpos($js, "ed.addHook('addImageBlobHook'");
        $this->assertGreaterThan(0, $hook, 'The blob hook is gone, so a dropped and a pasted image reach the vendor default and are embedded with no bound');
        $note = 'The registration itself became conditional, so a denied visitor is handed to the vendor default and embeds with no bound';
        $this->assertMatchesRegularExpression('#\n\s*ed\.addHook\(.addImageBlobHook.#', $js, $note);
        $body = substr($js, (int)strpos($js, 'function addEmbed(id, file, done)'), 900);
        foreach (['opt.canembed', 'checkEmbedType(id, file)', 'file.size > max'] as $part) {
            $this->assertStringContainsString($part, $body, 'The embed path no longer checks '.$part.', so one half of the contract is unenforced in the client');
        }
        $this->assertMatchesRegularExpression('#addFileList\(zone\.getAttribute\(.data-editor.\), ev\.dataTransfer#', $js, 'A dropped file misses the one file entry point');
        $this->assertMatchesRegularExpression('#addFileList\(el\.getAttribute\(.data-editor.\), el\.files,#', $js, 'A picked file misses the one file entry point');
        $this->assertStringContainsString('addEmbed(id, files[0]);', $js, 'The embed mode no longer routes through the guarded embed path');
    }

    # A column of this contract written by a path that never rendered an editor is guarded too, and a refusal there keeps the stored body rather than losing it
    #[Test]
    public function theFeedWriterAsksTheGuardAndKeepsTheStoredBody(): void
    {
        $code = $this->getFile('modules/content/index.php');
        $from = (int)strpos($code, 'rss_read(');
        $this->assertGreaterThan(0, $from, 'The feed writer is gone from the content module');
        $body = substr($code, $from, 500);
        $gate = strpos($body, "checkEditorTextRoom(\$rss, 'content.body')");
        $call = strpos($body, 'UPDATE ');
        $this->assertNotFalse($gate, 'The feed writer no longer measures what the far end served against the column it writes into');
        $this->assertNotFalse($call, 'The feed writer no longer stores anything');
        $this->assertLessThan($call, $gate, 'The feed is stored before it is measured, so ERROR 1406 reaches a page a visitor asked for');
        $this->assertStringContainsString('Logger::addSite(', $body, 'A refused feed leaves no line in the log, so a stale item looks like a working one');
    }

    # One window behind one icon, present for every visitor, and no reduced second editor in either theme
    #[Test]
    public function oneWindowAndOneIconReplaceTheTwoDialogs(): void
    {
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
        foreach (['setImageChrome', 'setPopupChrome'] as $name) {
            $this->assertStringNotContainsString($name, $js, $name.'() is back, so code measures and rewrites a popup it does not own');
        }
        $this->assertStringContainsString("ed.removeToolbarItem('image')", $js, 'The vendor image item is back beside our own, so there are two windows again');
        $note = 'The toolbar icon is conditional again, so a visitor who may only insert an address finds none';
        $this->assertMatchesRegularExpression('#\n\s*addBtn\(id, ed\);#', $this->getEntry($js), $note);
        $lite = $this->getFile('templates/lite/partials/editor-toastui-files.html');
        $this->assertSame($lite, $this->getFile('templates/admin/partials/editor-toastui-files.html'), 'The two themes no longer carry the same window markup');
        $this->assertStringContainsString('js-slaed-image-url', $lite, 'The address field is gone from the window, which is the one thing every visitor may use');
        $note = 'The address field sits inside a condition of the window, though it stores nothing and belongs to every visitor';
        $this->assertSame(0, $this->getDepth($lite, 'js-slaed-image-url'), $note);
        foreach (['lite', 'admin'] as $theme) {
            $path = dirname(__DIR__, 2).'/templates/'.$theme.'/partials/editor-toastui-dialogs.html';
            $this->assertFileDoesNotExist($path, 'A second window markup survives in the '.$theme.' theme');
        }
    }
}
