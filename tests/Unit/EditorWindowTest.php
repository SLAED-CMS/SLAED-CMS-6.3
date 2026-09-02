<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage 7 of docs/FILE-MANAGER-CONCEPT-2026.md: the window of the editor rebuilt on the file layer.
 * The listing route answers descriptors and capabilities instead of a row it assembled itself, the
 * two changing routes belong to a module moderator alone, and the rail states what a visitor may do
 * rather than hiding what he may not. Nothing here writes to the site; every claim is read off the
 * shipped source, the two themes and the six locales.
 */
final class EditorWindowTest extends TestCase
{
    private const LOCALES = ['de', 'en', 'fr', 'pl', 'ru', 'uk'];
    private const PANES = ['up', 'url', 'emb', 'lib'];

    private static array $files = [];

    # Read one repository file once per run
    private function getFile(string $path): string
    {
        if (isset(self::$files[$path])) return self::$files[$path];
        $full = dirname(__DIR__, 2).'/'.$path;
        $this->assertFileExists($full);
        return self::$files[$path] = (string)file_get_contents($full);
    }

    # Return the body of one function, so a claim is made about the handler and not about the file around it
    private function getBody(string $path, string $name): string
    {
        $code = $this->getFile($path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        $stop = strpos($code, "\n}", $from);
        $this->assertNotFalse($stop, $name.'() has no closing brace at its own indentation');
        return substr($code, $from, $stop - $from);
    }

    # The listing route hands out the descriptor of the file layer, so nothing about a stored file is worked out a second time beside it
    # The row carries the capability set of its own object, because the fan of that object is drawn from it and never from a role the window derives again
    #[Test]
    public function theListingAnswersTheDescriptorOfTheFileLayer(): void
    {
        $body = $this->getBody('core/system.php', 'getEditorFileJson');
        $this->assertStringContainsString('getUploadFileArea(', $body, 'The listing route builds no file context, so it walks the directory beside the one layer that may');
        $this->assertStringContainsString("\$area->getFileList('')", $body, 'The listing route scans the directory itself instead of asking the file layer for it');
        $this->assertStringNotContainsString('scandir(', $body, 'The listing route reads the filesystem directly again beside the layer that owns it');
        $row = $this->getBody('core/system.php', 'getEditorFileData');
        foreach (['$one[\'capabilities\']', '$one[\'kind\']', '$one[\'mtime\']', '$one[\'thumbnail\']'] as $key) {
            $this->assertStringContainsString($key, $row, 'The row of the window is built without '.$key.', so one field of the descriptor is invented beside it');
        }
        $this->assertStringNotContainsString('realpath', $row, 'The row of the window carries the absolute server path, which no editor context may ever receive');
        $area = $this->getBody('core/system.php', 'getUploadFileArea');
        $this->assertStringContainsString("new FileManager('editor'", $area, 'The window is handed a context that is not the editor one and holds the rights of another screen');
        # What narrows the list is the owner and nothing else: a name the upload service did not draw is still a file of the module, and an archive packed in the window carries one
        $this->assertStringNotContainsString("\$one['managed']", $body, 'The listing drops what the naming does not know, so an archive packed here would vanish from the catalogue');
        $this->assertStringContainsString("['index.html', '.htaccess']", $body, 'The listing shows the sentinels of the upload directory as if they were files of the module');
    }

    # The quota of the module is measured on the way through the listing and never by a second walk of the same directory
    #[Test]
    public function theQuotaIsCountedWhileTheListIsRead(): void
    {
        $body = $this->getBody('core/system.php', 'getEditorFileJson');
        $this->assertMatchesRegularExpression('#\$used \+= \$one\[.size.\];#', $body, 'The window is told nothing about the room the module has left');
        foreach (['used', 'quota', 'usedtext', 'quotatext'] as $key) {
            $this->assertStringContainsString("'".$key."' =>", $body, 'The answer of the listing carries no '.$key.', so the bar of the window has nothing to draw');
        }
        $this->assertStringContainsString("'able' => \$area->getCapabilities()", $body, 'The window is not told what the context allows and would derive it from a role');
    }

    # Deletion and packing in the window belong to a module moderator alone, and what says so is the capability of the object rather than a role the route reads again
    # Every path of a marked set is asked and journalled on its own, because a set is the same action over several names and never a second operation
    #[Test]
    public function theChangingRoutesAskTheDescriptorAndTheJournal(): void
    {
        $body = $this->getBody('core/system.php', 'setEditorFileRun');
        $this->assertStringContainsString('getEditorRouteRule(', $body, 'A changing route of the window passes no guard at all');
        $this->assertStringContainsString("empty(\$one['capabilities'][\$need])", $body, 'The route decides the operation itself instead of reading the capability of the object');
        $this->assertStringContainsString("getVar('post', 'mark[]', 'array'", $body, 'The marked set does not arrive in the body, so a changing action would travel in an address');
        $this->assertStringContainsString('Logger::addFile(', $body, 'A changing action of the window leaves no entry, so nobody can be asked who removed a file');
        $this->assertStringContainsString("'total' => count(\$mark)", $body, 'The answer never names how many of how many ran, so a partly refused set reads as a full success');
        $this->assertSame(0, substr_count($body, 'is_moder('), 'The route decides the role a second time beside the context that already answered it');
    }

    # A changing route of the window is reached by POST alone: its token is read out of the body, which an address cannot carry
    #[Test]
    public function theChangingRoutesReadTheirTokenFromTheBody(): void
    {
        $rule = $this->getBody('core/system.php', 'getEditorRouteRule');
        $this->assertStringContainsString("getVar(\$src, 'token', 'raw', '')", $rule, 'The guard reads its token from a fixed place, so one route cannot demand the body');
        $code = $this->getFile('index.php');
        foreach (['editorDelete', 'editorArchive'] as $op) {
            $this->assertStringContainsString("case '".$op."':", $code, 'The route '.$op.' is not wired at all');
        }
        $this->assertStringContainsString("setEditorFileRun('editorDelete')", $code, 'The deletion of the window runs something other than the one changing handler');
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringNotContainsString("&token='.rawurlencode(\$atk)", $drv, 'A token travels in an address again, where history, logs and the referer carry it along');
    }

    # The window is rendered for every visitor and the sections state what the settings decided; a closed one stays in place, gains the lock and says why
    #[Test]
    public function everySectionOfTheRailIsRenderedAndStatesItsRight(): void
    {
        $lite = $this->getFile('templates/lite/partials/file-manager.html');
        $this->assertSame($lite, $this->getFile('templates/admin/partials/file-manager.html'), 'The two themes no longer carry the same window markup');
        foreach (self::PANES as $pane) {
            $this->assertStringContainsString('data-sl-pane="'.$pane.'"', $lite, 'The rail carries no section '.$pane.', so one way of inserting a file has nowhere to live');
        }
        foreach (['can_upload', 'can_embed', 'can_list'] as $flag) {
            $this->assertStringContainsString('{% if not '.$flag.' %} disabled{% endif %}', $lite, 'The section of '.$flag.' disappears instead of being closed and explained');
            $this->assertStringContainsString('{% if not '.$flag.' %}<i class="bi bi-lock-fill', $lite, 'A section closed by '.$flag.' carries no lock, so the boundary of the rights is invisible');
        }
        foreach (['up_why', 'embed_why', 'files_why'] as $why) {
            $this->assertStringContainsString('{{ '.$why.' }}', $lite, 'A closed section never prints '.$why.', so it says nothing about why it is closed');
        }
        $win = $this->getBody('core/helpers.php', 'getFileManagerWindow');
        $this->assertStringContainsString("'can_zip' => \$mdr && in_array('editorArchive'", $win, 'Packing follows something other than the role and the routes of the place');
        $this->assertStringContainsString("'can_delete' => \$mdr && in_array('editorDelete'", $win, 'Deletion follows something other than the role and the routes of the place');
        $this->assertStringContainsString("\$mdr = \$see['moder'];", $win, 'The window works out the role beside the resolver that already answers it for the field as well');
        $view = $this->getBody('core/helpers.php', 'getUploadPlaceView');
        $this->assertStringContainsString("'moder' => \$upl && is_moder(\$mod),", $view, 'The moderator of the module is decided by something other than is_moder()');
        $this->assertStringContainsString("\$upl = \$mod !== '' && checkEditorUploadAccess(\$mod, \$rule);", $view, 'The upload right is decided beside the one gate that answers it for every other place');
    }

    # The window keeps its styles under its own root, because the administrative theme owns the same component names for the browser of the catalogue
    #[Test]
    public function theWindowKeepsItsStylesUnderItsOwnRoot(): void
    {
        $lite = $this->getFile('templates/lite/assets/editors/toastui/skin.css');
        $this->assertSame($lite, $this->getFile('templates/admin/assets/editors/toastui/skin.css'), 'The two themes no longer carry the same window skin');
        # The window is dressed by the theme and not by the skin: a page carrying no editor receives no skin at all, and the same window opens there
        $rules = (string)preg_replace('#/\*.*?\*/#s', '', $lite);
        $this->assertStringNotContainsString('.sl-fm-', $rules, 'The skin still dresses the file manager, which leaves the window bare on every page without an editor');
        # The skeleton is a shared component of the theme, so it stands in both of them under one name rather than being redefined beside one
        foreach (['lite', 'admin'] as $theme) {
            $css = $this->getFile('templates/'.$theme.'/assets/css/theme.css');
            foreach (['sl-fm-rail', 'sl-fm-pane', 'sl-fm-tile', 'sl-fm-queue', 'sl-fm-props'] as $part) {
                $this->assertStringContainsString('.sl-fm-win .'.$part, $css, 'The window of theme '.$theme.' carries no rule for '.$part.', so one part of the mock-up has no shape at all');
            }
            $this->assertStringContainsString('.sl-skel {', $css, 'The skeleton of a list is missing from the '.$theme.' theme, so one of the two would have to invent it');
            $this->assertStringContainsString('prefers-reduced-motion', $css, 'The skeleton of the '.$theme.' theme pulses even where motion is asked to stop');
        }
        # Lite owns no file browser, so every file manager rule standing at its top level would be the window losing its own root
        $loose = preg_match_all('#(?m)^\.sl-fm-(?!win)#', $this->getFile('templates/lite/assets/css/theme.css'));
        $this->assertSame(0, $loose, 'The window declares a file manager class at the top level, where it meets the same name of the administrative browser');
        # Every box carrying a file manager part has to stand under that root, and two do: the window itself and the small
        # dialog of insertion options, whose caption, radio row and text field are the window's own parts. The second is
        # invisible on every walked page, so nothing but this line would report it losing its shape
        foreach (['lite', 'admin'] as $theme) {
            $insert = $this->getFile('templates/'.$theme.'/fragments/window-body-insert.html');
            foreach (['sl-fm-label', 'sl-fm-as', 'sl-fm-field'] as $part) {
                $this->assertStringContainsString($part, $insert, 'The insertion options of theme '.$theme.' stopped using '.$part.', so this guard now proves nothing');
            }
        }
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringContainsString("'win_class' => 'sl-fm-win", $drv, 'The window of the insertion options carries no file manager root, so its caption, its radio row and its field are drawn bare');
    }

    # The runtime of the window left the editor plugin, and the cut has to be a cut: an alias would keep the coupling alive and break it one batch later instead
    # The borrowed names went local over a map of the runtime, so a window opened beside a form finds no editor and disables the four editor-only paths by itself
    # The draw templates travel with the script, because a runtime that cannot find them draws no tile, no row, no queue card and no message, and every one of them silently
    #[Test]
    public function theRuntimeOfTheWindowLeftThePluginWithItsTemplates(): void
    {
        $js = $this->getFile('plugins/system/filemanager.js');
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/plugins/editors/toastui/assets/editor-upload.js', 'The runtime is still delivered from the editor plugin, so a page carrying no editor gets a window with no behaviour');
        $this->assertStringContainsString('win.SlaedFileManager = api;', $js, 'The runtime publishes itself under no namespace of its own, so nothing outside the editor can reach it');
        $this->assertStringNotContainsString('SlaedToastUi', $js, 'The runtime still names the namespace of the editor plugin, which is the coupling this move was made to cut');
        foreach (['api.getEditor', 'api.insertText'] as $name) {
            $this->assertStringNotContainsString($name, $js, 'The runtime still borrows '.$name.' from the editor plugin, so it draws nothing on a page that plugin never loaded');
        }
        $this->assertStringContainsString('function getEditor(id) {', $js, 'The runtime keeps no editor of its own, so the four editor-only paths have nothing to ask');
        $this->assertStringContainsString('edits.set(String(id), ed);', $js, 'The map of the editors is never written, so an insert reaches no editor at all');
        $tags = $this->getFile('plugins/editors/toastui/assets/editor-tags.js');
        $reach = preg_match_all('#^.*SlaedFileManager.*$#m', $tags);
        $this->assertSame(1, $reach, 'The editor reaches the runtime from more than the one explicit call, so an alias hides the coupling again');
        $this->assertStringContainsString('if (win.SlaedFileManager) win.SlaedFileManager.addUpload(id, ed, opt || {});', $tags, 'The editor still asks its own namespace for the upload, which is false now and loses the file button with no error');
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringNotContainsString('editor-upload.js', $drv, 'The driver still delivers the runtime, so the move is only half done');
        foreach (['editor-tags.js', 'editor-emoji.js'] as $own) {
            $this->assertStringContainsString($own, $drv, 'The driver stopped delivering '.$own.', which belongs to the editor and not to the window');
        }
        $win = $this->getBody('core/helpers.php', 'getFileManagerWindow');
        $this->assertStringContainsString('static $done = false;', $win, 'The window delivers its runtime again for every editor of a page instead of once');
        $this->assertStringContainsString("'src' => 'plugins/system/filemanager.js'", $win, 'The window carries no runtime, so it opens beside a form and answers nothing');
        $this->assertStringContainsString("getHtmlPart('file-manager-templates'", $win, 'The window carries no draw templates, so it opens and then draws no tile, no row and no message');
        foreach (['fm-act', 'fm-busy', 'fm-dial', 'fm-job', 'fm-pick', 'fm-prop', 'fm-row', 'fm-tile', 'fm-why', 'msg-info', 'msg-warn'] as $name) {
            $this->assertStringContainsString('data-tpl="'.$name.'"', $this->getFile('templates/lite/partials/file-manager-templates.html'), 'The template '.$name.' stayed with the editor, so the runtime finds null where it expects a node');
        }
        foreach (['lite', 'admin'] as $theme) {
            $part = $this->getFile('templates/'.$theme.'/partials/file-manager-templates.html');
            $this->assertSame(11, substr_count($part, '<template'), 'The draw templates of theme '.$theme.' are not the eleven the runtime needs');
            $emoji = $this->getFile('templates/'.$theme.'/partials/editor-toastui-templates.html');
            $this->assertSame(4, substr_count($emoji, '<template'), 'The editor partial of theme '.$theme.' holds something besides the four emoji templates it keeps');
            $this->assertStringNotContainsString('data-tpl="fm-', $emoji, 'A file manager template of theme '.$theme.' stayed behind, so one node is looked for in two places');
        }
        $this->assertSame($this->getFile('templates/lite/partials/file-manager-templates.html'), $this->getFile('templates/admin/partials/file-manager-templates.html'), 'The two themes no longer carry the same draw templates');
    }

    # The fan of an object is built from the capabilities of that object, and the width of its plate is written down rather than counted off a child index
    #[Test]
    public function theFanFollowsTheCapabilitiesAndNamesItsOwnWidth(): void
    {
        $js = $this->getFile('plugins/system/filemanager.js');
        foreach (['able.insert', 'able.download', 'able.compress', 'able.delete'] as $key) {
            $this->assertStringContainsString($key, $js, 'The fan never asks '.$key.', so it offers an action the context withholds');
        }
        $note = 'The fan does not set its own count, so a right withheld leaves a gap in the plate the theme draws behind it';
        $this->assertStringContainsString("setProperty('--sl-d-count'", $js, $note);
        $this->assertStringContainsString('data-sl-ask', $js, 'A deletion in the window asks nothing before it runs');
        $this->assertStringContainsString('win.setConfirmTask', $js, 'The window asks with a dialogue of its own instead of the shared confirmation of the project');
        $this->assertStringNotContainsString('window.confirm(text)', substr($js, 0, (int)strpos($js, 'function setAsk')), 'A browser prompt stands before the shared confirmation');
    }

    # A mark and the current object are different things: the current one shows its properties, the marked ones carry the actions over several files
    #[Test]
    public function theMarksAndTheCurrentObjectAreKeptApart(): void
    {
        $js = $this->getFile('plugins/system/filemanager.js');
        $this->assertStringContainsString('function setCurrent(id, num)', $js, 'Nothing makes one object the current one, so the properties would follow a mark');
        $this->assertStringContainsString('function setPicks(id)', $js, 'Nothing keeps the marks, so a tile and a row of one file could show two states');
        $this->assertMatchesRegularExpression('#room\.pick = \[\];#', $js, 'The marks are never dropped, so an action would travel to a file of another listing');
        $lite = $this->getFile('templates/lite/partials/file-manager.html');
        $this->assertStringContainsString('sl-bulk-bar', $lite, 'The panel of the actions over several files is gone from the window');
        $this->assertStringContainsString('data-sl-slot="pickall"', $lite, 'The head of the list carries no mark for all of them');
    }

    # The catalogue has five states and the settled one is only one of them; an empty catalogue and an empty filter carry different words on purpose
    #[Test]
    public function theCatalogueCarriesEveryStateOfTheProcess(): void
    {
        $lite = $this->getFile('templates/lite/partials/file-manager.html');
        foreach (['compact', 'full', 'skel', 'empty'] as $view) {
            $this->assertStringContainsString('data-sl-view="'.$view.'"', $lite, 'The catalogue has no view '.$view.', so one state of the process has nowhere to be shown');
        }
        $this->assertStringContainsString('sl-skel-tile', $lite, 'The list on its way shows nothing in the place of the tiles it is about to fill');
        $js = $this->getFile('plugins/system/filemanager.js');
        $rows = substr($js, (int)strpos($js, 'function setState(id, name)'), 800);
        foreach (['empty:', 'filter:', 'fail:'] as $state) {
            $this->assertStringContainsString($state, $rows, 'The state '.$state.' speaks with the words of another');
        }
        $this->assertStringContainsString('xhr.upload.onprogress', $js, 'An upload shows no progress, so a large file looks like a stopped window');
        $this->assertStringContainsString('function setQueueStep(id)', $js, 'The files are not uploaded one at a time, so one refusal takes the rest with it');
    }

    # Every word the window says is a language constant of the six locales, and a concept the whole project shares is global rather than scoped to one screen
    #[Test]
    public function everyWordOfTheWindowStandsInSixLocales(): void
    {
        $en = $this->getFile('lang/en.php');
        $names = [];
        preg_match_all("#^define\('(_EDITOR_[A-Z]+)'#m", $en, $hits);
        $names = $hits[1];
        $this->assertGreaterThan(30, count($names), 'The window of the editor carries almost no words of its own, so most of it speaks English to everyone');
        foreach (self::LOCALES as $loc) {
            $code = $this->getFile('lang/'.$loc.'.php');
            foreach ($names as $name) {
                $this->assertStringContainsString("define('".$name."'", $code, $name.' is missing from '.$loc.', so the window falls apart in that language');
            }
            $this->assertStringContainsString("define('_RETRY'", $code, '_RETRY is missing from '.$loc.', so the way back to a list that failed has no words');
        }
        $adm = $this->getFile('admin/lang/en.php');
        $this->assertStringNotContainsString("define('_UPLOADS_RETRY'", $adm, 'The scoped copy of _RETRY is back, and one concept stands in two areas again');
        $this->assertStringNotContainsString("define('_FM_", $en, 'A prefix of its own appeared for the file manager, which no namespace of the project answers');
    }

    # A form row asks for a file through the same window, so the door beside the button is what the runtime is registered on and the three outcomes each get a carrier of their own
    # The chip states what the form is carrying and nothing else, which is why its cross clears all three at once and no carrier can survive the answer of another
    # A place refusing an address is handed no address field at all, so nothing can be posted into a store that keeps a file name and could never resolve an external one
    #[Test]
    public function theFormRowOpensTheSameWindowThroughOneDoor(): void
    {
        foreach (['lite', 'admin'] as $theme) {
            $frag = $this->getFile('templates/'.$theme.'/fragments/file-field.html');
            foreach (['file', 'path', 'url', 'chip', 'name'] as $part) {
                $this->assertStringContainsString('data-sl-field="'.$part.'"', $frag, 'The door of theme '.$theme.' carries no '.$part.', so one of the three outcomes reaches the handler through nothing');
            }
            foreach (['open', 'clear'] as $act) {
                $this->assertStringContainsString('data-sl-act="'.$act.'"', $frag, 'The door of theme '.$theme.' carries no '.$act.', so the runtime finds nothing to bind by delegation');
            }
            $this->assertStringContainsString('{% if url_name %}', $frag, 'The door of theme '.$theme.' prints an address field whatever the place says, so a store of file names can be posted a link');
            $this->assertStringContainsString('.sl-file-door', $this->getFile('templates/'.$theme.'/assets/css/theme.css'), 'The door of theme '.$theme.' has no shape, so the button, the chip and the limits stack');
        }
        $this->assertSame($this->getFile('templates/lite/fragments/file-field.html'), $this->getFile('templates/admin/fragments/file-field.html'), 'The two themes no longer carry the same door');
        $fld = $this->getBody('core/helpers.php', 'getFileManagerField');
        $this->assertStringContainsString("'is_field' => true", $fld, 'The door opens the editor window, which draws a queue for an upload the form is the one carrying');
        $this->assertStringContainsString('SlaedFileManager.addField(', $fld, 'The door registers through the editor entry, which would install bindings for an editor that is not there');
        $this->assertStringContainsString("'url_name' => \$link ?", $fld, 'The address field is printed without asking the place, so canlink decides nothing');
        $this->assertStringContainsString('getFieldIds(', $fld, 'The row of the door mints its ids beside the one helper that owns them');
        # The words of the window belong to the window and not to one editor, or a page carrying no editor opens a window speaking nothing
        $txt = $this->getBody('core/helpers.php', 'getFileManagerText');
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertSame(1, substr_count($drv, 'getFileManagerText('), 'The driver reads the shared words more than once per widget, or has stopped reading them at all');
        $this->assertStringContainsString("'labels' => \$txt['labels'] + [", $drv, 'The driver builds the words of the window again, so the door and the editor drift apart at the first edit');
        $this->assertStringContainsString("'panes' => \$txt['panes'] + [", $drv, 'The driver names the sections of the window again beside the one place that does');
        foreach (['badtype', 'big', 'toobig', 'quota', 'mynote'] as $key) {
            $this->assertStringContainsString("'".$key."' => ", $txt, 'The shared words carry no '.$key.', so the runtime falls back to English for it');
            $this->assertStringNotContainsString("'".$key."' =>", $drv, 'The driver keeps its own '.$key.' beside the shared one, and the two can disagree');
        }
    }
}
