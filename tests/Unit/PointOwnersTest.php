<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S04 of docs/node: every remaining owner of a points rule speaks to the Point class with the action, the
 * scope and the source the owner map of docs/node/points.md gives it, the positional helpers and settings are gone
 * together with their callers, a rating never awards, and the label of every action exists in all six locales.
 * The labels are read through constant('_POINTS_'.strtoupper($name)), so this file is the one place a search by
 * name finds them: a dictionary cleanup that misses it takes a label of a live screen for an unused one.
 */
final class PointOwnersTest extends TestCase
{
    private const OWNERS = [
        'core/classes/comment.php' => [
            "addEvent('comment', \$mod, 'comment:'.\$id,", "getEventId('comment', \$mod, 'comment:'.\$id,", "addEvent('comment', \$mod, 'reverse:'.\$rid,",
        ],
        'core/system.php' => ["addEvent('poll', 'voting', 'poll:'.\$id,"],
        'core/user.php' => ["addEvent('message', 'privat', 'privat:'.\$new['id'],", "addEvent('favorite', 'favorites', \$mod.':'.\$id,"],
        'modules/account/admin/index.php' => [
            "addEvent('adjust', 'account', 'adjust:'.bin2hex(random_bytes(16)),",
            "addEvent('adjust', 'account', 'reset:'.\$_SESSION[\$skey]['id'].':'.\$uid.':'.\$part,",
        ],
        'modules/account/index.php' => ["addEvent('register', 'account', 'user:'.\$nuid,", "addEvent('login', 'account', 'day:'.gmdate('Ymd'),"],
        'modules/auto_links/index.php' => ["addEvent('visit', 'auto-links', 'link:'.\$id,"],
        'modules/contact/index.php' => ["addEvent('message', 'contact', 'req:'.bin2hex(random_bytes(16)),"],
        'modules/forum/index.php' => [
            "addEvent('comment', 'forum.topic', 'post:'.\$lpid,", "addEvent('publish', 'forum.topic', 'topic:'.\$lpid,",
            "getEventId(\$act, 'forum.topic', (\$pid ? 'post:' : 'topic:').\$id,", "getEventId('comment', 'forum.topic', 'post:'.\$id,",
            "addEvent(\$act, 'forum.topic', 'reverse:'.\$rid,",
        ],
        'modules/order/admin/index.php' => [
            "addEvent('order', 'order', 'order:'.\$id,", "getEventId('order', 'order', 'order:'.\$id,", "addEvent('order', 'order', 'reverse:'.\$rid,",
        ],
        'modules/recommend/index.php' => ["addEvent('recommend', 'recommend', 'req:'.bin2hex(random_bytes(16)),"],
        'modules/shop/admin/index.php' => [
            "addEvent('order', 'shop', 'client:'.\$id,", "getEventId('order', 'shop', 'client:'.\$id,", "addEvent('order', 'shop', 'reverse:'.\$rid,",
        ],
    ];

    private const LABELS = [
        '_POINTS_PUBLISH', '_POINTS_COMMENT', '_POINTS_VIEW', '_POINTS_DOWNLOAD', '_POINTS_VISIT', '_POINTS_POLL', '_POINTS_ORDER', '_POINTS_FAVORITE',
        '_POINTS_MESSAGE', '_POINTS_RECOMMEND', '_POINTS_REGISTER', '_POINTS_LOGIN', '_POINTS_REPORT', '_POINTS_MODERATE', '_POINTS_ADJUST',
    ];

    private const GONE = [
        'updatePoints(', 'addPointsAction(', "['users']['point']", "['users']['points']",
        '_POINTS0', '_POINTS1', '_POINTS2', '_POINTS3', '_POINTS4', '_DESC0', '_DESC1', '_DESC2', '_DESC3', '_DESC4',
    ];

    # The root of the tree
    private function getRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    # Every shipped PHP file of the application, keyed by its path from the root
    private function getTree(): array
    {
        $out = [];
        foreach (['admin', 'blocks', 'core', 'lang', 'modules', 'plugins', 'setup', 'templates'] as $dir) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getRoot().'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $file) {
                if ($file->getExtension() !== 'php') continue;
                $out[str_replace('\\', '/', substr($file->getPathname(), strlen($this->getRoot()) + 1))] = (string)file_get_contents($file->getPathname());
            }
        }
        foreach (['index.php', 'admin.php'] as $name) $out[$name] = (string)file_get_contents($this->getRoot().'/'.$name);
        return $out;
    }

    # The files that call the class are exactly the owners of the map, and each of them carries every key the map gives it
    #[Test]
    public function everyOwnerSpeaksTheMap(): void
    {
        $calls = array_filter($this->getTree(), fn($code) => preg_match('/pnt->(addEvent|getEventId)\(/', $code) === 1);
        $this->assertSame(array_keys(self::OWNERS), array_keys($calls), 'The set of files that call Point is not the owner map of docs/node/points.md');
        foreach (self::OWNERS as $path => $keys) {
            foreach ($keys as $key) $this->assertStringContainsString($key, $calls[$path], $path.' lost the event key '.$key);
        }
    }

    # The positional helpers, settings and rule constants left the tree together with their callers
    #[Test]
    public function thePositionalRulesAreGone(): void
    {
        $tree = $this->getTree() + ['config/users.php' => (string)file_get_contents($this->getRoot().'/config/users.php')];
        foreach ($tree as $path => $code) {
            foreach (self::GONE as $name) $this->assertStringNotContainsString($name, $code, $path.' still carries '.$name);
        }
        $users = (require $this->getRoot().'/config/users.php')['users'];
        $this->assertArrayNotHasKey('point', $users);
        $this->assertArrayNotHasKey('points', $users);
    }

    # A page view and a rating never reach the class: both rewards left without a replacement
    #[Test]
    public function aViewAndARatingNeverAward(): void
    {
        $code = (string)file_get_contents($this->getRoot().'/core/system.php');
        foreach (['setHead', 'getRatingView'] as $name) {
            $from = strpos($code, 'function '.$name.'(');
            $this->assertNotFalse($from, $name.'() is gone from core/system.php');
            $body = substr($code, $from, strpos($code, "\n}\n", $from) - $from);
            $this->assertStringNotContainsString('pnt', $body, $name.'() still reaches the points class');
        }
        $this->assertStringNotContainsString('Point', (string)file_get_contents($this->getRoot().'/core/classes/rating.php'));
    }

    # The actions of the shipped scope and the labels of this file are one list, and every locale defines every label exactly once
    #[Test]
    public function everyActionHasItsLabelInSixLocales(): void
    {
        $names = array_keys((require $this->getRoot().'/config/points.php')['points']['actions']);
        $this->assertSame(self::LABELS, array_map(fn($v) => '_POINTS_'.strtoupper($v), $names), 'The label list of this test drifted from config/points.php');
        foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
            $code = (string)file_get_contents($this->getRoot().'/lang/'.$lang.'.php');
            foreach (self::LABELS as $label) $this->assertSame(1, substr_count($code, "define('".$label."',"), 'lang/'.$lang.'.php: '.$label);
        }
    }

    # The two screens that print the labels build the constant name from the action, which is why no search by name finds a reader
    #[Test]
    public function theLabelsAreReadByActionName(): void
    {
        foreach (['admin/modules/groups.php', 'modules/users/index.php'] as $path) {
            $this->assertStringContainsString("constant('_POINTS_'.strtoupper(\$name))", (string)file_get_contents($this->getRoot().'/'.$path), $path);
        }
    }
}
