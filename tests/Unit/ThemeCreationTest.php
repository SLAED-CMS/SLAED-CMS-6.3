<?php
declare(strict_types=1);

namespace {
    require_once __DIR__.'/../Support/ViewTestBootstrap.php';
    require_once dirname(__DIR__, 2).'/tools/ui-audit.php';
}

namespace Tests\Unit {
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;
    use RuntimeException;

    /**
     * The goal of docs/THEME-ETALON-2026.md, expressed as a test: a new theme is made by copying an
     * etalon and editing one block. It copies an etalon under a name nothing else uses, repaints
     * only the API block of its base.css, and then asks the whole contract of the copy - every audit
     * count at zero, every contrast the etalon held still held, the runtime file list satisfied, and
     * every template of the package compiling through Template with no undefined token.
     *
     * This is the static half. Rendering a real page needs HTTP, and that half rides with the
     * screenshot runner, which walks tools/ui-shots.json once more against the same scratch theme.
     * A manual look proves nothing about the day after the freeze, and after the freeze the names
     * can no longer be corrected.
     */
    final class ThemeCreationTest extends TestCase
    {
        private const ETALON = 'lite';

        private static string $root = '';
        private static string $name = '';
        private static string $path = '';

        public static function setUpBeforeClass(): void
        {
            self::$root = dirname(__DIR__, 2);
            self::$name = 'scratch-'.substr(sha1((string)getmypid().self::ETALON), 0, 8);
            self::$path = self::$root.'/templates/'.self::$name;
            if (is_dir(self::$path)) self::setTreeGone(self::$path);
            self::setTreeCopy(self::$root.'/templates/'.self::ETALON, self::$path);
            self::setPalettePaint(self::$path.'/assets/css/base.css');
        }

        public static function tearDownAfterClass(): void
        {
            # Only ever the directory this harness built, never a path it guessed
            if (self::$name !== '' && str_starts_with(basename(self::$path), 'scratch-')) self::setTreeGone(self::$path);
        }

        # Copy one directory tree whole, which is step one of creating a theme
        private static function setTreeCopy(string $from, string $to): void
        {
            if (!is_dir($from)) throw new RuntimeException('No etalon at '.$from);
            mkdir($to, 0777, true);
            foreach (scandir($from) ?: [] as $item) {
                if ($item === '.' || $item === '..') continue;
                $one = $from.'/'.$item;
                $two = $to.'/'.$item;
                if (is_dir($one)) self::setTreeCopy($one, $two);
                else copy($one, $two);
            }
        }

        # Remove the scratch tree, refusing any path outside templates/scratch-*
        private static function setTreeGone(string $path): void
        {
            $safe = str_replace('\\', '/', $path);
            if (!preg_match('#/templates/scratch-[0-9a-f]{8}$#', $safe) && !str_contains($safe, '/templates/scratch-')) return;
            foreach (scandir($path) ?: [] as $item) {
                if ($item === '.' || $item === '..') continue;
                $one = $path.'/'.$item;
                if (is_dir($one)) self::setTreeGone($one);
                else unlink($one);
            }
            rmdir($path);
        }

        # Repaint every colour of the API block by turning its hue half way round and then pulling it back to the
        # relative luminance it had. Hue alone does not hold a contrast ratio: the three channels carry different
        # weight, so the same HSL lightness in orange is brighter than in blue and #111827 turned to #272011 gains
        # half again as much luminance. Luminance is what a ratio is made of, so a repaint that holds it holds every
        # pair the etalon measured, in both halves of light-dark() and without a browser to ask
        private static function setPalettePaint(string $file): void
        {
            $text = str_replace("\r\n", "\n", (string)file_get_contents($file));
            $mark = strpos($text, getContract()['marker']);
            if ($mark === false) throw new RuntimeException('The etalon has no marker, so it has no API block to repaint');
            $head = substr($text, 0, $mark);
            $tail = substr($text, $mark);
            $head = (string)preg_replace_callback('/#([0-9a-fA-F]{6})\b/', static fn($m) => '#'.self::getHueTurn($m[1]), $head);
            file_put_contents($file, $head.$tail);
        }

        # One hex colour turned half way round the wheel, then searched back onto the relative luminance it started with
        private static function getHueTurn(string $hex): string
        {
            $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
            [$deg, $pct, $val] = getHslValues($rgb);
            if ($pct <= 0.0) return $hex;
            $want = self::getLuminance($hex);
            $hue = fmod($deg + 180.0, 360.0) / 360.0;
            $sat = $pct / 100.0;
            $low = 0.0;
            $high = 1.0;
            $out = self::getHslHex($hue, $sat, $val / 100.0);
            for ($i = 0; $i < 40; $i++) {
                $mid = ($low + $high) / 2;
                $out = self::getHslHex($hue, $sat, $mid);
                if (self::getLuminance($out) < $want) $low = $mid;
                else $high = $mid;
            }
            return $out;
        }

        # One HSL triple written back as a hex colour
        private static function getHslHex(float $hue, float $sat, float $lum): string
        {
            $two = $lum < 0.5 ? $lum * (1.0 + $sat) : $lum + $sat - $lum * $sat;
            $one = 2.0 * $lum - $two;
            $part = static function (float $at) use ($one, $two): int {
                if ($at < 0.0) $at += 1.0;
                if ($at > 1.0) $at -= 1.0;
                if ($at < 1 / 6) return (int)round(255 * ($one + ($two - $one) * 6.0 * $at));
                if ($at < 1 / 2) return (int)round(255 * $two);
                if ($at < 2 / 3) return (int)round(255 * ($one + ($two - $one) * (2 / 3 - $at) * 6.0));
                return (int)round(255 * $one);
            };
            return sprintf('%02x%02x%02x', $part($hue + 1 / 3), $part($hue), $part($hue - 1 / 3));
        }

        # The three CSS files of the copy, read as one model the way the audit reads a declared theme
        private function getModel(): array
        {
            $list = [];
            foreach (['assets/css/base.css', 'assets/css/theme.css', 'assets/editors/toastui/skin.css'] as $one) {
                $full = self::$path.'/'.$one;
                if (is_file($full)) $list['templates/'.self::$name.'/'.$one] = str_replace("\r\n", "\n", (string)file_get_contents($full));
            }
            return getTextModel($list, 'templates/'.self::$name.'/assets/css/base.css');
        }

        #[Test]
        public function theCopyIsTheEtalonWithOneBlockRepainted(): void
        {
            $etalon = self::$root.'/templates/'.self::ETALON;
            $mark = getContract()['marker'];
            foreach (['assets/css/theme.css', 'assets/editors/toastui/skin.css'] as $one) {
                $this->assertFileEquals($etalon.'/'.$one, self::$path.'/'.$one, $one.' changed, so the copy is not "edit one block" any more');
            }
            $was = str_replace("\r\n", "\n", (string)file_get_contents($etalon.'/assets/css/base.css'));
            $now = str_replace("\r\n", "\n", (string)file_get_contents(self::$path.'/assets/css/base.css'));
            $why = 'the repaint reached below the marker, where a theme author has no business editing';
            $this->assertSame(substr($was, strpos($was, $mark)), substr($now, strpos($now, $mark)), $why);
            $this->assertNotSame(substr($was, 0, strpos($was, $mark)), substr($now, 0, strpos($now, $mark)), 'the palette did not actually change, so this test proves nothing');
        }

        #[Test]
        public function everyAuditCountOfTheNewThemeIsAtZero(): void
        {
            $model = $this->getModel();
            $this->assertTrue($model['marker'], 'the copy has no marker, so its API block has no end');
            $this->assertCount(0, checkThemeCount($model)['sites'], 'the copy holds an untokenised visual decision');
            $this->assertCount(0, checkBareValues($model)['sites'], 'the copy holds a bare number in one of the four properties');
            $this->assertCount(0, checkNameGrammar($model), 'the copy holds a token name the grammar refuses');
            $use = checkTokenUse($model);
            foreach (['dead', 'alias', 'unsat'] as $key) {
                $this->assertCount(0, $use[$key], 'the copy holds a '.$key.' token');
            }
            $this->assertCount(0, checkUnmetNames($model), 'the copy reads a name it declares nowhere, which CSS answers by dropping the declaration');
            $this->assertCount(0, $model['clash'], 'the copy declares one name twice, where the second silently wins');
        }

        # Every pair the walk recorded for the etalon, re-measured after the same repaint the API block took. The registry
        # stores the colour it resolved rather than the name that gave it, so a repainted theme cannot be looked up in it -
        # but the repaint is a function of a colour, so putting each recorded colour through it answers the same question
        # the walk would: does this palette still clear AA on the pages and states that really carry these two colours
        #[Test]
        public function theRepaintHoldsEveryContrastTheEtalonHeld(): void
        {
            $pairs = array_filter(getContract()['contrast']['pairs'], static fn($v) => ($v['theme'] ?? '') === self::ETALON);
            $this->assertGreaterThan(100, count($pairs), 'the pair registry holds almost nothing for the etalon, so passing it proves nothing');
            $bad = [];
            foreach ($pairs as $pair) {
                $fore = self::getPaintedRgb($pair['fg']);
                $back = self::getPaintedRgb($pair['bg']);
                if ($fore === null || $back === null) continue;
                $rate = getContrastRatio($fore, $back);
                $want = self::getWantedRatio($pair);
                if ($rate + 0.005 < $want) $bad[] = $pair['page'].' ['.$pair['mode'].'] '.$pair['sel'].': '.round($rate, 2).':1, needs '.$want;
            }
            $this->assertSame([], $bad, "the repainted palette drops a pair below AA on a page the walk really visited:
".implode("
", $bad));
        }

        # The ratio one recorded pair has to clear, which large text is allowed to clear at a lower bar
        private static function getWantedRatio(array $pair): float
        {
            $conf = getContract()['contrast'];
            $size = (float)($pair['size'] ?? 0);
            $bold = (int)($pair['weight'] ?? 400) >= 700;
            $big = $size >= $conf['large']['size'] || ($bold && $size >= $conf['large']['boldsize']);
            return $big ? $conf['aalarge'] : $conf['aa'];
        }

        # One recorded `rgb(...)` colour put through the same repaint the API block took
        private static function getPaintedRgb(string $val): ?array
        {
            if (!preg_match('/rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/', $val, $hit)) return null;
            $hex = sprintf('%02x%02x%02x', (int)$hit[1], (int)$hit[2], (int)$hit[3]);
            $now = self::getHueTurn($hex);
            return [hexdec(substr($now, 0, 2)), hexdec(substr($now, 2, 2)), hexdec(substr($now, 4, 2))];
        }

        #[Test]
        public function theRuntimeAcceptsTheNewThemeAsAPackage(): void
        {
            $said = $this->getProbeVerdict([self::$name, 'scratch-no-such-theme']);
            $this->assertTrue($said[self::$name], 'checkThemeAssets() refuses the copy, so the etalon is missing a file the runtime demands of every theme');
            $this->assertFalse($said['scratch-no-such-theme'], 'checkThemeAssets() accepts a theme that is not there, so it proves nothing about the one that is');
        }

        # Ask the real core, in its own process, what it makes of these theme packages
        private function getProbeVerdict(array $list): array
        {
            $work = str_replace(DIRECTORY_SEPARATOR, '/', sys_get_temp_dir()).'/slaed_theme_'.bin2hex(random_bytes(6));
            $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg(dirname(__DIR__).'/Support/theme_probe.php').' assets '.escapeshellarg($work);
            foreach ($list as $one) $cmd .= ' '.escapeshellarg((string)$one);
            $out = (string)shell_exec($cmd.' 2>&1');
            $said = json_decode($out, true);
            $this->assertIsArray($said, 'the theme probe did not answer JSON: '.$out);
            return $said;
        }

        # The skeleton is the union of two lists that are not the same list, so it is checked against both of them rather
        # than against itself: an entry nobody demands is one nobody dares delete, and a demand nobody listed is one a
        # new theme discovers at runtime
        #[Test]
        public function theSkeletonNamesExactlyWhatTheGatesDemand(): void
        {
            $skel = getContract()['skeleton'];
            $said = getFileText('core/system.php');
            $body = substr($said, (int)strpos($said, 'function checkThemeAssets('));
            $body = substr($body, 0, (int)strpos($body, "
}"));
            foreach (array_keys($skel['any']) as $one) {
                $want = rtrim($one, '/');
                $this->assertStringContainsString("'".$want."'", $body, $want.' is in the skeleton and checkThemeAssets() never looks for it');
            }
            $rule = getFileText('tests/TemplateValidationTest.php');
            foreach (array_keys($skel['frontend']) as $one) {
                $this->assertStringContainsString("'".$one."'", $rule, $one.' is in the skeleton and TemplateValidationTest never looks for it');
            }
            foreach (glob(dirname(__DIR__, 2).'/plugins/editors/*/manifest.json') ?: [] as $file) {
                $man = json_decode((string)file_get_contents($file), true);
                $dec = (array)($man['theme'] ?? []);
                if ($dec === []) continue;
                foreach (array_keys(getContract()['themes']) as $theme) {
                    $root = dirname(__DIR__, 2).'/templates/'.$theme.'/';
                    if (!empty($dec['skin'])) $this->assertFileExists($root.'assets/editors/'.$man['id'].'/skin.css', $theme.' declares no skin for the '.$man['id'].' editor');
                    foreach ((array)($dec['partials'] ?? []) as $part) {
                        $this->assertFileExists($root.'partials/'.$part.'.html', $theme.' is missing the '.$part.' partial the '.$man['id'].' editor declares');
                    }
                }
            }
        }

        #[Test]
        public function bothEtalonsCarryTheWholeSkeleton(): void
        {
            $skel = getContract()['skeleton'];
            foreach (getContract()['themes'] as $theme => $conf) {
                $need = $skel['any'];
                if (($conf['kind'] ?? '') === 'frontend') $need += $skel['frontend'];
                foreach (array_keys($need) as $one) {
                    $full = dirname(__DIR__, 2).'/'.$conf['root'].'/'.$one;
                    $why = $theme.' is missing '.$one.', which the skeleton names as required';
                    str_ends_with($one, '/') ? $this->assertDirectoryExists(rtrim($full, '/'), $why) : $this->assertFileExists($full, $why);
                }
            }
        }

        #[Test]
        public function everyTemplateOfTheNewThemeCompiles(): void
        {
            $GLOBALS['__test_theme'] = self::$name;
            $GLOBALS['conf']['theme'] = self::$name;
            $tpl = new \Template(self::$name);
            $bad = [];
            foreach (['fragments' => 'getHtmlFrag', 'partials' => 'getHtmlPart'] as $dir => $call) {
                foreach (glob(self::$path.'/'.$dir.'/*.html') ?: [] as $file) {
                    $name = basename($file, '.html');
                    try {
                        $html = $tpl->$call($name, []);
                    } catch (\Throwable $e) {
                        $bad[] = $dir.'/'.$name.': '.$e->getMessage();
                        continue;
                    }
                    if (preg_match('/\{[%{]\s*[a-z_]/i', $html)) $bad[] = $dir.'/'.$name.': an unresolved placeholder survived the render';
                }
            }
            $this->assertSame([], $bad, "a template of the copied theme does not compile:\n".implode("\n", $bad));
        }

        #[Test]
        public function everyTokenTheTemplatesWriteIsRegisteredAsData(): void
        {
            $data = array_keys(getContract()['data']);
            $bad = [];
            foreach (['fragments', 'partials', 'layouts', 'pages'] as $dir) {
                foreach (glob(self::$path.'/'.$dir.'/*.html') ?: [] as $file) {
                    if (!preg_match_all('/(--sl-[a-z0-9-]+)\s*:/', (string)file_get_contents($file), $hit)) continue;
                    foreach ($hit[1] as $name) {
                        if (in_array($name, $data, true)) continue;
                        $bad[$dir.'/'.basename($file).' writes '.$name] = true;
                    }
                }
            }
            $why = 'a template writes a custom property that is not registered under `data` in tools/ui-contract.php,'
                .' so a dead-token scan would delete what feeds it';
            $this->assertSame([], array_keys($bad), $why);
        }

        # WCAG relative luminance, the quantity a contrast ratio is built from
        private static function getLuminance(string $hex): float
        {
            $sum = 0.0;
            $part = [0.2126, 0.7152, 0.0722];
            foreach ([0, 2, 4] as $i => $at) {
                $one = hexdec(substr($hex, $at, 2)) / 255;
                $sum += $part[$i] * ($one <= 0.03928 ? $one / 12.92 : (($one + 0.055) / 1.055) ** 2.4);
            }
            return $sum;
        }
    }
}
