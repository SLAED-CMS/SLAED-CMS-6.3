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
        $this->assertStringContainsString('getEditorFileArea(', $body, 'The listing route builds no file context, so it walks the directory beside the one layer that may');
        $this->assertStringContainsString("\$area->getFileList('')", $body, 'The listing route scans the directory itself instead of asking the file layer for it');
        $this->assertStringNotContainsString('scandir(', $body, 'The listing route reads the filesystem directly again beside the layer that owns it');
        $row = $this->getBody('core/system.php', 'getEditorFileData');
        foreach (['$one[\'capabilities\']', '$one[\'kind\']', '$one[\'mtime\']', '$one[\'thumbnail\']'] as $key) {
            $this->assertStringContainsString($key, $row, 'The row of the window is built without '.$key.', so one field of the descriptor is invented beside it');
        }
        $this->assertStringNotContainsString('realpath', $row, 'The row of the window carries the absolute server path, which no editor context may ever receive');
        $area = $this->getBody('core/system.php', 'getEditorFileArea');
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
        $lite = $this->getFile('templates/lite/partials/editor-toastui-files.html');
        $this->assertSame($lite, $this->getFile('templates/admin/partials/editor-toastui-files.html'), 'The two themes no longer carry the same window markup');
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
        $drv = $this->getFile('plugins/editors/toastui/driver.php');
        $this->assertStringContainsString("'can_zip' => \$mdr", $drv, 'Packing in the window follows something other than the one role rule of this area');
        $this->assertStringContainsString("'can_delete' => \$mdr", $drv, 'Deletion in the window follows something other than the one role rule of this area');
        $this->assertStringContainsString('$mdr = $upl && is_moder($mod);', $drv, 'The moderator of the module is decided by something other than is_moder()');
    }

    # The window keeps its styles under its own root, because the administrative theme owns the same component names for the browser of the catalogue
    #[Test]
    public function theWindowKeepsItsStylesUnderItsOwnRoot(): void
    {
        $lite = $this->getFile('templates/lite/assets/editors/toastui/skin.css');
        $this->assertSame($lite, $this->getFile('templates/admin/assets/editors/toastui/skin.css'), 'The two themes no longer carry the same window skin');
        $loose = preg_match_all('#(?m)^\.sl-fm-#', $lite);
        $this->assertSame(0, $loose, 'The window declares a file manager class at the top level, where it meets the same name of the administrative browser');
        foreach (['sl-fm-rail', 'sl-fm-pane', 'sl-fm-tile', 'sl-fm-queue', 'sl-fm-props'] as $part) {
            $this->assertStringContainsString('.sl-toastui-upload .'.$part, $lite, 'The window carries no rule for '.$part.', so one part of the mock-up has no shape at all');
        }
        # The skeleton is a shared component of the theme, so it stands in both of them under one name rather than being redefined beside one
        foreach (['lite', 'admin'] as $theme) {
            $css = $this->getFile('templates/'.$theme.'/assets/css/theme.css');
            $this->assertStringContainsString('.sl-skel {', $css, 'The skeleton of a list is missing from the '.$theme.' theme, so one of the two would have to invent it');
            $this->assertStringContainsString('prefers-reduced-motion', $css, 'The skeleton of the '.$theme.' theme pulses even where motion is asked to stop');
        }
    }

    # The fan of an object is built from the capabilities of that object, and the width of its plate is written down rather than counted off a child index
    #[Test]
    public function theFanFollowsTheCapabilitiesAndNamesItsOwnWidth(): void
    {
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
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
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
        $this->assertStringContainsString('function setCurrent(id, num)', $js, 'Nothing makes one object the current one, so the properties would follow a mark');
        $this->assertStringContainsString('function setPicks(id)', $js, 'Nothing keeps the marks, so a tile and a row of one file could show two states');
        $this->assertMatchesRegularExpression('#room\.pick = \[\];#', $js, 'The marks are never dropped, so an action would travel to a file of another listing');
        $lite = $this->getFile('templates/lite/partials/editor-toastui-files.html');
        $this->assertStringContainsString('sl-bulk-bar', $lite, 'The panel of the actions over several files is gone from the window');
        $this->assertStringContainsString('data-sl-slot="pickall"', $lite, 'The head of the list carries no mark for all of them');
    }

    # The catalogue has five states and the settled one is only one of them; an empty catalogue and an empty filter carry different words on purpose
    #[Test]
    public function theCatalogueCarriesEveryStateOfTheProcess(): void
    {
        $lite = $this->getFile('templates/lite/partials/editor-toastui-files.html');
        foreach (['compact', 'full', 'skel', 'empty'] as $view) {
            $this->assertStringContainsString('data-sl-view="'.$view.'"', $lite, 'The catalogue has no view '.$view.', so one state of the process has nowhere to be shown');
        }
        $this->assertStringContainsString('sl-skel-tile', $lite, 'The list on its way shows nothing in the place of the tiles it is about to fill');
        $js = $this->getFile('plugins/editors/toastui/assets/editor-upload.js');
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
}
