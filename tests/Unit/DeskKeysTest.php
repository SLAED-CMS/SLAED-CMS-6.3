<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 8 of docs/FILE-MANAGER-CONCEPT-2026.md: the desk conveniences of both file screens.
 * A modifier and a key mark what the pointer marks, the arrows walk the list the pointer walks,
 * the fan of an object serves as its context menu, a dragged file lands in the directory it was
 * let go over, and the focus goes where §33 sends it. Nothing here writes to the site; every
 * claim is read off the shipped source, the two themes and the two skins.
 */
final class DeskKeysTest extends TestCase
{
    private const THEMES = ['admin', 'lite'];
    private const ADMINJS = 'templates/admin/assets/js/admin-ui.js';
    private const EDITJS = 'plugins/editors/toastui/assets/editor-upload.js';
    private const SITEJS = 'plugins/system/slaed.js';

    private static array $files = [];

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Return a slice of a file from a landmark, so a claim is made about one handler and not about the file around it
    private function getPart(string $path, string $mark, int $len): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, $mark);
        $this->assertNotFalse($from, $mark.' is gone from '.$path);
        return substr($code, $from, $len);
    }

    # The context menu is the fan the object already carries: a second list of actions would be a second place to keep the rights of that object in step with
    # It lives in the shared component file, so every fan of the project answers the right button and the two file screens get it without a line of their own
    #[Test]
    public function theContextMenuIsTheFanOfTheObjectAndLivesWithTheComponent(): void
    {
        $js = str_replace('document.', 'doc.', $this->getFile('plugins/system/slaed.js'));
        $note = 'The shared component takes no right button, so a context menu would have to be built once per screen';
        $this->assertStringContainsString("doc.addEventListener('contextmenu'", $js, $note);
        $part = $this->getPart('plugins/system/slaed.js', 'function getDialOwn(', 600);
        $note = 'The menu is not found through the fan of the object, so every screen would have to name its own rows';
        $this->assertStringContainsString("querySelectorAll('.sl-dial')", $part, $note);
        # A box around several fans is the list and not one of its objects: the empty ground between tiles must keep the menu of the browser
        $note = 'A box holding several fans is taken for an object, so the empty ground of a list opens the menu of some object standing in it';
        $this->assertStringContainsString('if (list.length > 1) return null;', $part, $note);
        $point = $this->getPart('plugins/system/slaed.js', 'function setDialPoint(', 900);
        $note = 'The fan is not opened at the pointer, which is the whole of what a context menu adds';
        $this->assertStringContainsString("classList.add('sl-dial-point', 'sl-open')", $point, $note);
        $note = 'The placement is not measured against the box the fan stands in, so a scrolled work area would cut it away';
        $this->assertStringContainsString('offsetParent', $point, $note);
        $note = 'A screen places the context menu itself, beside the component that owns the fan';
        foreach ([self::ADMINJS, self::EDITJS] as $own) {
            $this->assertStringNotContainsString('sl-dial-point', $this->getFile($own), $note);
        }
    }

    # A component missing in one theme is added to the other under the same name (§32), and the window of the editor overrules both anchors of its own fan
    #[Test]
    public function bothThemesAndBothSkinsCarryTheMenuState(): void
    {
        foreach (self::THEMES as $name) {
            $css = $this->getFile('templates/'.$name.'/assets/css/theme.css');
            $note = 'Theme '.$name.' has no placement for the fan opened at the pointer';
            $this->assertStringContainsString('.sl-dial.sl-dial-point {', $css, $note);
            $skin = $this->getFile('templates/'.$name.'/assets/editors/toastui/skin.css');
            $note = 'The window of theme '.$name.' keeps its own anchor, so the menu would stand in the corner of the row';
            $this->assertStringContainsString('.sl-toastui-upload .sl-dial.sl-dial-point {', $skin, $note);
            $note = 'The catalogue of theme '.$name.' says nothing while a file is dragged over it';
            $this->assertStringContainsString('.sl-toastui-upload .sl-fm-pane.sl-drag-over {', $skin, $note);
        }
        $css = $this->getFile('templates/admin/assets/css/theme.css');
        foreach (['.sl-fm-drop.sl-drag-over {', '.sl-fm-node.sl-drag-over,', '.sl-fm-cell[aria-selected="true"] .sl-fm-tile {'] as $rule) {
            $note = 'The administrative theme is missing '.$rule.', so a target of a drag or the current tile is drawn nowhere';
            $this->assertStringContainsString($rule, $css, $note);
        }
    }

    # A modifier means the mark and not the address: the press is taken before the object gets it, because the address of a row carries a request of its own
    #[Test]
    public function aModifierMarksInsteadOfOpening(): void
    {
        $part = $this->getPart(self::ADMINJS, 'var node = (event.shiftKey || event.ctrlKey || event.metaKey)', 900);
        $note = 'The press reaches the address of the object, so a marking click would also leave the directory';
        $this->assertStringContainsString('event.stopPropagation();', $part, $note);
        $note = 'The shift marks one object instead of the range from the object the range started at';
        $this->assertStringContainsString('setFileSpan(fmfrom, at)', $part, $note);
        $note = 'The press is taken in the bubbling phase, where the request of the address has already left';
        $this->assertStringContainsString('}, true);', $part, $note);
        # A mark is not the current object (§9.1): moving the current one here would show the properties of one object beside the name of another
        $note = 'A marking press moves the current object without asking for its properties, so the panel names another object than the list does';
        $this->assertStringNotContainsString('setFilePick(', $part, $note);
        $edit = $this->getPart(self::EDITJS, 'var el = (ev.shiftKey || ev.ctrlKey || ev.metaKey)', 700);
        $note = 'The window marks one object on a shift instead of the range';
        $this->assertStringContainsString('setPickSpan(id, room.anch, num)', $edit, $note);
        $note = 'The window takes the press after the object, where the current object has already changed';
        $this->assertStringContainsString('}, true);', $edit, $note);
        $list = $this->getPart(self::EDITJS, 'function setList(id)', 1400);
        $note = 'A redrawn catalogue keeps the object a range would start from, and the files stand in other places now';
        $this->assertStringContainsString('room.anch = -1;', $list, $note);
    }

    # The mark of one object is set through the one handler keeping the row, the tile and the panel of marks in step, so a set marked by keys is the set the panel acts on
    #[Test]
    public function theKeysMarkThroughTheOneSetOfMarks(): void
    {
        $part = $this->getPart(self::ADMINJS, 'function setFileMark(item, on)', 400);
        $note = 'A mark set by a key never reaches the panel of marks, so the two would disagree';
        $this->assertStringContainsString('setFileMarks(box);', $part, $note);
        $edit = $this->getPart(self::EDITJS, 'function setPickOne(id, num, on)', 500);
        $note = 'The window marks a drawing of a file instead of its path, so the row and the tile would disagree';
        $this->assertStringContainsString('room.pick', $edit, $note);
        $note = 'A mark set by a key never reaches the panel of marks of the window';
        $this->assertStringContainsString('setPicks(id);', $edit, $note);
    }

    # The keys walk what the eye sees and nothing else: a fan item, a page of the pager and the field of an operation stand in the same body and keep the keys they came with
    #[Test]
    public function theArrowsWalkOnlyTheObjectsOfTheViewThatIsOn(): void
    {
        $walk = $this->getPart(self::ADMINJS, 'function getFileWalk()', 300);
        $note = 'The walk takes the objects of both views at once, so an arrow would step into a drawing nobody sees';
        $this->assertStringContainsString(':not([hidden])', $walk, $note);
        $keys = $this->getPart(self::ADMINJS, "var keys = ['ArrowUp'", 1200);
        $note = 'Every focused thing of the body answers the arrows, so a fan item would lose its own keys';
        $this->assertStringContainsString("node.matches('[data-sl-fm-pick],[data-sl-fm-mark]')", $keys, $note);
        $note = 'A space on the box of a mark is handled twice, which sets the mark and takes it straight back off';
        $this->assertStringContainsString("event.key === ' ' && node.hasAttribute('data-sl-fm-pick')", $keys, $note);
        $note = 'The open gallery loses its own arrows to the list standing behind it';
        $this->assertStringContainsString('if (box && box.open) return;', $keys, $note);
        $side = $this->getPart(self::ADMINJS, 'function setFileSide(item)', 500);
        $note = 'A held arrow asks the server for the properties of every object it steps over';
        $this->assertStringContainsString('window.setTimeout', $side, $note);
        $lib = $this->getPart(self::EDITJS, '// Only the catalogue answers to these keys', 500);
        $note = 'The rail and the fields of the window answer the arrows of the catalogue';
        $this->assertStringContainsString("matches('input, textarea, select, button, a')", $lib, $note);
    }

    # The window opens on its content and gives the focus back to what opened it, and the key that closes a window lets an open fan win first (§33)
    # The rule is kept once, in the window canon of the shared component, because every window of the project now answers the same mechanism
    #[Test]
    public function theFocusAndTheEscapeFollowTheKeyboardRule(): void
    {
        $part = $this->getPart(self::SITEJS, 'function setWindowOpen(box)', 700);
        $note = 'The window forgets what opened it, so the keyboard is left on the page when it closes';
        $this->assertStringContainsString('box.backnode = document.activeElement', $part, $note);
        $free = $this->getPart(self::SITEJS, 'function setWindowRelease(box)', 500);
        $note = 'The focus is handed back to an element the answer of the server has already replaced';
        $this->assertStringContainsString('box.backnode.isConnected', $free, $note);
        $note = 'A window beside the page never gets its focus back, because only a modal one is served by the browser';
        $this->assertStringContainsString('!isWindowModal(box) && box.backnode', $free, $note);
        $first = $this->getPart(self::SITEJS, 'function setFirstFocus(box)', 500);
        $note = 'The window opens on nothing, so the first key press goes to the page behind it';
        $this->assertStringContainsString('[data-sl-focus]', $first, $note);
        $note = 'The window opens on an action of its own head, which reads as a window asking to be shut';
        $this->assertStringContainsString("one.closest('.sl-modal-title')", $first, $note);
        $esc = $this->getPart(self::SITEJS, 'A window beside the page is not closed by the browser on this key', 600);
        $note = 'The key closes the window under the open fan instead of the fan standing on it';
        $this->assertStringContainsString('.sl-dial.sl-open', $esc, $note);
        $note = 'The key closes the window opened last instead of the one standing in front';
        $this->assertStringContainsString('winstack[winstack.length - 1]', $esc, $note);
        $note = 'The editor keeps a second answer to the key, so two handlers race for one press';
        $this->assertStringNotContainsString("ev.key === 'Escape'", $this->getFile(self::EDITJS), $note);
        foreach (self::THEMES as $name) {
            $tpl = $this->getFile('templates/'.$name.'/partials/editor-toastui-templates.html');
            $note = 'A row of theme '.$name.' cannot take the focus, so the arrows never reach the catalogue';
            $this->assertStringContainsString('<div class="sl-fm-row" tabindex="-1">', $tpl, $note);
            $note = 'A tile of theme '.$name.' cannot take the focus, so the arrows never reach the catalogue';
            $this->assertStringContainsString('<div class="sl-fm-cell" tabindex="-1">', $tpl, $note);
        }
    }

    # A file dropped anywhere over the browser belongs to the directory the browser shows, and it goes up the one way an upload goes: the form of the module and its queue
    #[Test]
    public function aDroppedFileGoesUpTheOneWayAnUploadGoes(): void
    {
        $part = $this->getPart(self::ADMINJS, 'function setFileZone(on)', 600);
        $note = 'The target of the drag says nothing while a file stands over it';
        $this->assertStringContainsString('sl-drag-over', $part, $note);
        $note = 'A panel opened by the drag stays open after it, or one opened by the administrator is closed by it';
        $this->assertStringContainsString('fmshown', $part, $note);
        $drop = $this->getPart(self::ADMINJS, "closest('#slfmbody') : null;\n        var form", 700);
        $note = 'A dropped file never reaches the field of the form, so nothing is sent';
        $this->assertStringContainsString('pick.files = event.dataTransfer.files;', $drop, $note);
        $note = 'The drop sends a request of its own beside the form carrying the token and the directory';
        $this->assertStringContainsString('form.requestSubmit', $drop, $note);
        $kind = $this->getPart(self::ADMINJS, 'function checkFileDrag(event)', 300);
        $note = 'A dragged row of a sortable table opens the upload panel of the browser';
        $this->assertStringContainsString('Files', $kind, $note);
        $lib = $this->getPart(self::EDITJS, 'function getDropZone(ev)', 500);
        $note = 'The catalogue of the window takes no drop, so a file dragged onto the storage lands nowhere';
        $this->assertStringContainsString('data-sl-pane="lib"', $lib, $note);
        $note = 'The catalogue takes a drop from a visitor the settings deny the upload to';
        $this->assertStringContainsString('canupload', $lib, $note);
        $turn = $this->getPart(self::EDITJS, "if (!zone.classList.contains('js-slaed-upload-drop'))", 200);
        $note = 'A file dropped onto the catalogue goes up with its queue out of sight (§31)';
        $this->assertStringContainsString("setPane(id, 'up')", $turn, $note);
        # The refused file of a queue carries the mark of the failed list and stands above the body: unscoped, the state of the list hides the reason of the file
        $wait = $this->getPart(self::ADMINJS, 'function setFileWait(mode)', 500);
        $note = 'The state of the list is looked for in the whole page, so a refused file of the queue is taken for it';
        foreach (['#slfmbody .sl-skel', '#slfmbody [data-sl-fm-fail]', '#slfmbody [data-sl-fm-real]'] as $one) {
            $this->assertStringContainsString($one, $wait, $note);
        }
    }

    # An object dragged onto a directory is moved there, and the move opens the same form the panel of marks opens, because a moved file leaves its published address behind (§8)
    #[Test]
    public function aDraggedObjectIsMovedThroughTheFormOfTheOperation(): void
    {
        $js = $this->getFile(self::ADMINJS);
        $part = $this->getPart(self::ADMINJS, '[data-sl-fm-act="fmmove"][data-sl-fm-many]\');', 500);
        $note = 'The drop writes a request of its own instead of the form every operation of the browser goes out in';
        $this->assertStringContainsString("setFileSet('fmmove'", $part, $note);
        $note = 'The move runs behind the administrator, who never saw the directory his set is about to land in';
        $this->assertStringContainsString('setFileWord(form)', $part, $note);
        $note = 'The drop submits the move by itself, and a set moved by a slip of the pointer has no way back';
        $this->assertStringNotContainsString('requestSubmit', $part, $note);
        $note = 'A marked set is written into the form in more than one place, so the two can drift apart';
        $this->assertSame(1, substr_count($js, "'mark[]'"), $note);
        $this->assertStringContainsString('function setFileSet(', $js, $note);
        $dir = $this->getPart(self::ADMINJS, 'function getFileDir(node)', 400);
        $note = 'A directory takes a drop of itself, which is the one move that has nowhere to go';
        $this->assertStringContainsString('fmdrag.indexOf(', $dir, $note);
        $note = 'The root of the context is refused as a target, because the field demands a word the root has none of';
        $this->assertStringContainsString("arg.required = val !== '';", $js, $note);
    }

    # What may be dragged is what the descriptor of that object allows, so no drawing of the list works a permission out beside the file layer (§14)
    #[Test]
    public function whatMayBeDraggedComesFromTheDescriptor(): void
    {
        $php = $this->getFile('core/admin.php');
        $note = 'The list decides on its own what may be dragged instead of reading the capability of the object';
        $this->assertStringContainsString("'is_move' => !empty(\$row['capabilities']['move'])", $php, $note);
        foreach (['file-browser-row', 'file-browser-tile'] as $name) {
            $tpl = $this->getFile('templates/admin/fragments/'.$name.'.html');
            $note = 'The '.$name.' fragment is draggable whatever the object allows';
            $this->assertStringContainsString('{% if is_move %} draggable="true"{% endif %}', $tpl, $note);
            $note = 'The '.$name.' fragment carries no path, so a dragged object cannot name itself';
            $this->assertStringContainsString('data-sl-fm-file="{{ pick_value }}"', $tpl, $note);
            $note = 'The '.$name.' fragment offers a file as a target of a move';
            $this->assertStringContainsString('{% if is_dir %} data-sl-fm-dir="{{ pick_value }}"{% endif %}', $tpl, $note);
        }
        $tree = $this->getFile('templates/admin/partials/file-browser-tree.html');
        $note = 'The tree takes no drop, and the one place every directory of the context is listed is the tree';
        $this->assertStringContainsString('data-sl-fm-dir="{{ node.path }}"', $tree, $note);
        $note = 'The module hands a class name to the template, and the markup of this screen belongs to the template';
        $this->assertStringNotContainsString('class="sl-fm', $php, $note);
    }
}
