<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 2 of docs/UPLOAD-2026.md: the image pipeline behind uploads.
 * tests/Support/image_probe.php boots the real core with its writable directories in scratch, writes
 * every fixture with GD itself and calls getImageThumb() directly, because the admin panel cannot reach
 * the helper until batch 8 migrates local upload. The removed WBMP branch is asserted at source level,
 * which is stated in the test that does so; the runtime image lists moved to UploadFormatTest with batch 9,
 * where they are compared against the canonical set instead of against the intermediate state of batch 2.
 */
final class ImageThumbTest extends TestCase
{
    private static array $probe = [];

    # Run one probe scenario in a fresh process and memoize its report
    private function getProbe(string $mode): array
    {
        if (isset(self::$probe[$mode])) return self::$probe[$mode];
        $file = dirname(__DIR__).'/Support/image_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_img_probe_'.$mode;
        $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($file).' '.escapeshellarg($mode).' '.escapeshellarg($work).' 2>&1');
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        if (!empty($data['error'])) $this->markTestSkipped('Probe '.$mode.': '.$data['error']);
        return self::$probe[$mode] = $data;
    }

    # Read one source file of the project
    private function getFile(string $name): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/'.$name);
    }

    # Read the body of one top-level function, which is how the removed and the newly guarded branches are asserted
    private function getSource(string $name): string
    {
        $code = $this->getFile('core/system.php');
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from core/system.php');
        $to = strpos($code, "\n}\n", $from);
        return substr($code, $from, $to === false ? null : $to - $from);
    }

    # Every format the helper has a branch for produces a real thumbnail that keeps its own container and is scaled to the requested width
    #[Test]
    public function everyBranchedFormatProducesAScaledThumbnail(): void
    {
        $data = $this->getProbe('thumb');
        foreach (['gif' => IMAGETYPE_GIF, 'jpg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG] as $ext => $type) {
            $run = $data['runs'][$ext];
            $this->assertTrue($run['made'], 'The '.$ext.' fixture could not be written, so this format proves nothing');
            $this->assertTrue($run['same'], 'getImageThumb() refused the '.$ext.' fixture and returned the source path');
            $this->assertTrue($run['thumb']['exists'], 'No '.$ext.' thumbnail was written');
            $this->assertSame($type, $run['thumb']['type'], 'The '.$ext.' thumbnail changed its container');
            $this->assertSame(32, $run['thumb']['width']);
            $this->assertSame(24, $run['thumb']['height'], 'The '.$ext.' thumbnail lost the aspect ratio of its 64x48 source');
        }
    }

    # The two formats batch 9 enables: without these branches every webp and avif attachment would silently render at full size
    #[Test]
    public function webpAndAvifFixturesProduceThumbnails(): void
    {
        $data = $this->getProbe('thumb');
        foreach (['webp' => IMAGETYPE_WEBP, 'avif' => IMAGETYPE_AVIF] as $ext => $type) {
            if (empty($data['caps'][$ext])) {
                $this->markTestSkipped('This GD build cannot both decode and encode '.$ext);
            }
            $run = $data['runs'][$ext];
            $this->assertTrue($run['made'], 'The '.$ext.' fixture could not be written, so this format proves nothing');
            $this->assertSame($type, $run['source']['type'], 'The '.$ext.' fixture is not stored in the container it claims');
            $this->assertTrue($run['same'], 'getImageThumb() returned the source path for '.$ext.', so no thumbnail was produced');
            $this->assertSame($type, $run['thumb']['type'], 'The '.$ext.' thumbnail changed its container');
            $this->assertSame(32, $run['thumb']['width']);
            $this->assertSame(24, $run['thumb']['height']);
        }
    }

    # Anything without a branch degrades to the full image: the source path comes back and nothing is left at the destination
    #[Test]
    public function anUnsupportedSourceDegradesToTheFullImage(): void
    {
        $runs = $this->getProbe('skip')['runs'];
        $this->assertTrue($runs['bmp']['made'], 'The bmp fixture could not be written, so this case proves nothing');
        $this->assertTrue($runs['bmp']['same'], 'bmp has no decode branch, so the source path must come back');
        $this->assertFalse($runs['bmp']['written'], 'A bmp source left a file at the thumbnail destination');
        $this->assertTrue($runs['plain']['same'], 'A file that is not an image must return the source path');
        $this->assertFalse($runs['plain']['written']);
        $this->assertTrue($runs['missing']['same'], 'A missing source must return the source path');
        $this->assertFalse($runs['missing']['written']);
        $this->assertTrue($runs['zero']['same'], 'A width of zero must return the source path instead of a division by zero');
        $this->assertFalse($runs['zero']['written']);
    }

    # Source level: the renamed helper carries no WBMP path, so IMAGETYPE_SWF is no longer decoded as a wireless bitmap
    #[Test]
    public function theHelperCarriesNoWbmpBranch(): void
    {
        $code = $this->getSource('getImageThumb');
        foreach (['IMG_WBMP', 'imagewbmp', 'imagecreatefromwbmp', 'IMAGETYPE_SWF'] as $mark) {
            $this->assertStringNotContainsString($mark, $code, 'getImageThumb() still carries the '.$mark.' branch');
        }
        $this->assertStringNotContainsString('create_img_gd', $this->getFile('core/system.php'), 'The old create_img_gd() name survived the rename');
        $this->assertStringNotContainsString('create_img_gd', $this->getFile('core/classes/parser.php'), 'The parser still calls the old thumbnail helper');
        $this->assertStringContainsString('getImageThumb($path, $tmp, $twd)', $this->getFile('core/classes/parser.php'), 'The parser no longer calls the renamed helper');
    }

    # Source level: both new branches are guarded on each side, so a GD build without webp or avif degrades instead of fataling
    #[Test]
    public function theNewBranchesAreGuardedOnBothSides(): void
    {
        $code = $this->getSource('getImageThumb');
        foreach (['imagecreatefromwebp', 'imagecreatefromavif', 'imagewebp', 'imageavif'] as $call) {
            $this->assertStringContainsString("function_exists('".$call."')", $code, $call.' is called without a function_exists() guard');
        }
    }

    # The styling hook the batch 9 render templates depend on has to exist in both themes before those templates ship
    #[Test]
    public function bothThemesCarryTheAttachmentStyling(): void
    {
        foreach (['lite', 'admin'] as $name) {
            $css = $this->getFile('templates/'.$name.'/assets/css/base.css');
            foreach (['.sl-attach {', '.sl-attach-left {', '.sl-attach-right {', '.sl-attach-center {'] as $rule) {
                $this->assertStringContainsString($rule, $css, 'Theme '.$name.' is missing the '.trim($rule, ' {').' rule');
            }
        }
    }
}
