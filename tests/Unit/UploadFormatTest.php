<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 9 of docs/UPLOAD-2026.md: the 2026 format set as it is actually shipped.
 * The configuration files are read as they lie in the repository, the type policy is read from the class through
 * reflection, and the render half runs through tests/Support/format_probe.php, which boots the real core and drives
 * Parser::filterAttach() over the shipped config/filetype.php. Nothing here restates the policy from memory: the
 * canonical set below is the one table of Format policy for 2026 browsers, and everything else is compared against it.
 */
final class UploadFormatTest extends TestCase
{
    private const IMAGES = ['avif', 'gif', 'jpeg', 'jpg', 'png', 'webp'];
    private const AUDIO = ['flac', 'm4a', 'mp3', 'oga', 'ogg', 'opus', 'wav'];
    private const VIDEO = ['mp4', 'webm'];
    private const DOCS = ['pdf'];
    private const ARCHIVE = ['7z', 'gz', 'rar', 'tar', 'zip'];
    private const GONE = ['bmp', 'gzip', 'm4b', 'm4p', 'm4r', 'm4v', 'ogv', 'ogx', 'spx', 'swf', 'wave', '7zip'];
    private const VISITOR = 'gif,jpg,jpeg,png,webp,avif,zip,rar';

    # Every extension of the canonical set, sorted, which is what a configured list may draw from
    private function getCanonical(): array
    {
        $all = array_merge(self::IMAGES, self::AUDIO, self::VIDEO, self::DOCS, self::ARCHIVE);
        sort($all);
        return $all;
    }

    # Read one configuration file the way the core does, as the plain array it returns
    private function getConfig(string $name): array
    {
        return require dirname(__DIR__, 2).'/config/'.$name.'.php';
    }

    # Load the production class the settings page validates against; core/classes has no runtime autoload, so every test that reads the policy comes through here
    private function getPolicy(): array
    {
        require_once dirname(__DIR__, 2).'/core/classes/upload.php';
        return (new \ReflectionClass(\Upload::class))->getConstants();
    }

    # Every extension the type policy knows, sorted, read from the class rather than restated
    private function getTypes(): array
    {
        $this->getPolicy();
        $known = \Upload::getSupportedTypes();
        sort($known);
        return $known;
    }

    # Read one source file of the project
    private function getFile(string $name): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$name);
    }

    # Pull the quoted entries of the one array literal that follows a marker, which is how the runtime image lists are compared
    private function getList(string $name, string $mark): array
    {
        $code = $this->getFile($name);
        $from = strpos($code, $mark);
        $this->assertNotFalse($from, $mark.' is gone from '.$name);
        $this->assertSame(1, preg_match('#\[([^\]]*)\]#', $code, $hit, 0, $from), 'No array literal follows '.$mark.' in '.$name);
        preg_match_all("#'([a-z0-9]+)'#", $hit[1], $all);
        $list = $all[1];
        sort($list);
        return $list;
    }

    # Run the render probe in a fresh process and memoize its report
    private function getProbe(): array
    {
        static $data = null;
        if ($data !== null) return $data;
        $file = dirname(__DIR__).'/Support/format_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_fmt_probe';
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' render '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'The render probe did not return JSON: '.$out);
        $this->assertTrue($data['ok'], 'The render probe could not write its image fixture');
        $this->assertFalse($data['left'], 'The render probe left its fixture directory below uploads/');
        return $data;
    }

    # The type policy is the canonical set of the plan, no more and no less, and it is the single source the settings page validates against
    #[Test]
    public function theTypePolicyIsTheCanonicalSet(): void
    {
        $known = $this->getTypes();
        $this->assertSame($this->getCanonical(), $known, 'Upload::getSupportedTypes() no longer matches the canonical 21 formats');
        foreach (self::GONE as $ext) {
            $this->assertNotContains($ext, $known, 'The withdrawn format '.$ext.' still has a type policy');
        }
    }

    # No configured allowlist may name a format the class cannot validate, and none may still name a withdrawn one
    #[Test]
    public function everyConfiguredExtensionHasATypePolicy(): void
    {
        $known = $this->getTypes();
        $upl = $this->getConfig('uploads')['uploads'];
        $lists = ['uploads.typ' => $upl['typ']];
        foreach ($upl as $mod => $val) {
            if (is_string($val) && str_contains($val, '|')) $lists['uploads.'.$mod] = explode('|', $val)[0];
        }
        $lists['files.typefile'] = $this->getConfig('files')['files']['typefile'];
        $lists['users.atypefile'] = $this->getConfig('users')['users']['atypefile'];
        foreach ($lists as $name => $list) {
            foreach (explode(',', $list) as $ext) {
                $this->assertContains($ext, $known, $name.' enables '.$ext.', which has no type policy and could never be uploaded');
                $this->assertNotContains($ext, self::GONE, $name.' still carries the withdrawn format '.$ext);
            }
        }
    }

    # The full canonical set reaches only the global list and the operator-only all record; every visitor-facing list gained webp and avif and nothing else
    #[Test]
    public function theVisitorAllowlistsWereNotWidened(): void
    {
        $upl = $this->getConfig('uploads')['uploads'];
        $full = implode(',', ['avif,gif,jpeg,jpg,png,webp', 'flac,m4a,mp3,oga,ogg,opus,wav', 'mp4,webm', 'pdf', '7z,gz,rar,tar,zip']);
        $this->assertSame($full, $upl['typ'], 'The global format list is not the canonical set in family order');
        $this->assertSame($full, explode('|', $upl['all'])[0], 'The all record does not carry the canonical set');
        foreach ($upl as $mod => $val) {
            if (!is_string($val) || !str_contains($val, '|') || $mod === 'all') continue;
            $this->assertSame(self::VISITOR, explode('|', $val)[0], 'Module record '.$mod.' does not carry exactly the visitor allowlist');
        }
        $this->assertArrayNotHasKey('album', $upl, 'The album record has no module and must be gone');
        $this->assertArrayNotHasKey('info', $upl, 'The info record has no module and must be gone');
        $this->assertSame('zip,gz,7z,rar,tar', $this->getConfig('files')['files']['typefile'], 'The file module list still uses the gzip spelling');
        $this->assertSame('jpg,jpeg,gif,png', $this->getConfig('users')['users']['atypefile'], 'The avatar allowlist was widened, which this migration does not do');
    }

    # The render map has exactly one entry per canonical extension, each rendered through the element of its own family, and the dead screens class is gone
    #[Test]
    public function theRenderMapMatchesEveryMedium(): void
    {
        $map = $this->getConfig('filetype')['filetype'];
        $keys = array_keys($map);
        sort($keys);
        $this->assertSame($this->getCanonical(), $keys, 'config/filetype.php does not hold exactly one entry per canonical extension');
        foreach ($map as $ext => $tpl) {
            $this->assertStringNotContainsString('screens', $tpl, 'The '.$ext.' template still carries the dead screens class');
            if (in_array($ext, self::IMAGES, true)) $this->assertStringContainsString('<img src="[tsrc]"', $tpl, 'The '.$ext.' template does not render an image');
            if (in_array($ext, self::AUDIO, true)) {
                $this->assertStringContainsString('<audio', $tpl, 'The '.$ext.' template does not render audio through <audio>');
                $this->assertStringNotContainsString('<video', $tpl, 'The '.$ext.' template still renders audio through <video>');
            }
            if (in_array($ext, self::VIDEO, true)) $this->assertStringContainsString('<video', $tpl, 'The '.$ext.' template does not render video through <video>');
            if (in_array($ext, self::DOCS, true)) $this->assertStringContainsString('<object data="[src]"', $tpl, 'The '.$ext.' template has no <object> with a fallback');
            if (in_array($ext, self::ARCHIVE, true)) $this->assertStringContainsString('<a href="[src]"', $tpl, 'The '.$ext.' template does not render as a plain link');
        }
    }

    # The lists that decide whether a file is decoded, dimension-checked, thumbnailed or previewed at all now agree with the type policy on the image set
    #[Test]
    public function everyImageListAgreesOnTheCanonicalImages(): void
    {
        $want = self::IMAGES;
        sort($want);
        $this->assertSame($want, $this->getList('core/system.php', 'if (!in_array($ext, ['), 'getImageBox() disagrees on the image set');
        $this->assertSame($want, $this->getList('core/system.php', '$img = in_array($ext, ['), 'getEditorImageData() disagrees on the image set');
        $this->assertSame($want, $this->getList('core/classes/parser.php', '$img = ['), 'filterAttach() disagrees on the image set');
        $this->assertSame($want, $this->getList('core/admin.php', '$ftype = ['), 'The admin file listing disagrees on the image set');
        $pars = $this->getFile('core/classes/parser.php');
        $this->assertSame(1, preg_match('#data:image/\(\?:([a-z?|]+)\);base64#', $pars, $hit), 'The data-URI allowlist is no longer readable as one alternation');
        $this->assertSame('png|jpe?g|gif|webp|avif', $hit[1], 'The data-URI allowlist disagrees on the image set');
        $pol = $this->getPolicy();
        $imgs = $pol['IMAGES'];
        sort($imgs);
        $this->assertSame($want, $imgs, 'The class image list disagrees on the image set');
        $mime = [];
        foreach ($pol['TYPES'] as $ext => $list) {
            foreach ($list as $one) {
                if (str_starts_with($one, 'image/')) $mime[$ext] = true;
            }
        }
        $mime = array_keys($mime);
        sort($mime);
        $this->assertSame($want, $mime, 'The image entries of the MIME map disagree on the image set');
    }

    # The lightbox follows the styling hook the new image template emits, because the class it used to hook onto no longer exists
    #[Test]
    public function theLightboxFollowsTheAttachmentClass(): void
    {
        $code = $this->getFile('plugins/system/slaed.js');
        $this->assertStringContainsString("closest('a.sl-attach, a.site-link')", $code, 'The lightbox no longer triggers on the attachment class');
        $this->assertStringNotContainsString('a.screens', $code, 'The lightbox still triggers on the dead screens class');
        $this->assertStringContainsString('/\\.(?:avif|gif|jpe?g|png|webp|svg)(?:[?#].*)?$/i', $code, 'The lightbox extension test disagrees on the image set');
    }

    # One attachment per family through the real parser: every shipped template resolves each token it uses and invents none of its own
    #[Test]
    public function everyRenderFamilyResolvesAllItsTokens(): void
    {
        $runs = $this->getProbe()['runs'];
        $this->assertSame(['image', 'audio', 'video', 'document', 'archive'], array_keys($runs), 'The probe did not render one attachment per family');
        foreach ($runs as $name => $html) {
            $this->assertNotSame('', $html, 'The '.$name.' family rendered nothing');
            $this->assertDoesNotMatchRegularExpression('#\[[a-z]+\]#', $html, 'The '.$name.' family left an unresolved token in '.$html);
            $this->assertStringNotContainsString('<mime>', $html, 'The '.$name.' family emitted a literal placeholder');
            $this->assertStringNotContainsString('screens', $html, 'The '.$name.' family rendered the dead screens class');
        }
        $this->assertStringContainsString('class="sl-attach sl-attach-left"', $runs['image'], 'The image family lost its styling hook or its alignment');
        $this->assertStringContainsString('style="max-width:500px"', $runs['image'], 'The image family did not receive the configured thumbnail width');
        $this->assertStringContainsString('<audio controls preload="metadata"', $runs['audio'], 'The audio family is not rendered through <audio>');
        $this->assertStringContainsString('width="100" height="80"', $runs['video'], 'The video family did not receive the dimensions of the tag');
        $this->assertStringContainsString('<a href="uploads/slaedrender/render.pdf">Render</a></object>', $runs['document'], 'The document family lost its link fallback');
        $this->assertStringContainsString('rel="noopener"', $runs['archive'], 'The archive family lost its link hardening');
    }
}
