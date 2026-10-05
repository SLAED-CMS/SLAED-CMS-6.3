<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# Every owner of a points rule speaks to the Point class with the action, the scope and the source the owner map of docs/POINTS.md gives it
final class PointOwnersTest extends TestCase
{
    private const OWNERS = [
        'core/classes/comment.php' => [
            "addEvent('comment', \$scope, 'comment:'.\$id,", "getEventId('comment', \$scope, 'comment:'.\$id,", "addEvent('comment', \$scope, 'reverse:'.\$rid,",
        ],
        'core/system.php' => ["addEvent('poll', 'voting', 'poll:'.\$id,"],
        'core/user.php' => ["addEvent('message', 'privat', 'privat:'.\$new['id'],", "addEvent('favorite', 'favorites', \$mod.':'.\$id,"],
        'modules/account/admin/index.php' => [
            "addEvent('adjust', 'account', 'adjust:'.\$pkey,",
            "addEvent('adjust', 'account', 'reset:'.\$_SESSION[\$skey]['id'].':'.\$uid.':'.\$part,",
        ],
        'modules/account/index.php' => ["addEvent('register', 'account', 'user:'.\$nuid,", "addEvent('login', 'account', 'day:'.gmdate('Ymd'),"],
        'modules/contact/index.php' => ["addEvent('message', 'contact', 'req:'.bin2hex(random_bytes(16)),"],
        'modules/forum/index.php' => [
            "addEvent('comment', 'forum.topic', 'post:'.\$lpid,", "addEvent('publish', 'forum.topic', 'topic:'.\$lpid,",
            "getEventId(\$act, 'forum.topic', (\$pid ? 'post:' : 'topic:').\$id,", "getEventId('comment', 'forum.topic', 'post:'.\$id,",
            "addEvent(\$act, 'forum.topic', 'reverse:'.\$rid,",
        ],
        'modules/recommend/index.php' => ["addEvent('recommend', 'recommend', 'req:'.bin2hex(random_bytes(16)),"],
    ];

    private const LABELS = [
        '_POINTS_PUBLISH', '_POINTS_COMMENT', '_POINTS_VIEW', '_POINTS_DOWNLOAD', '_POINTS_VISIT', '_POINTS_POLL', '_POINTS_FAVORITE',
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
        foreach (['admin', 'blocks', 'core', 'lang', 'modules', 'public/plugins', 'public/templates'] as $dir) {
            $walk = getTreeFiles($this->getRoot().'/'.$dir);
            foreach ($walk as $file) {
                if ($file->getExtension() !== 'php') continue;
                $out[str_replace('\\', '/', substr($file->getPathname(), strlen($this->getRoot()) + 1))] = (string)file_get_contents($file->getPathname());
            }
        }
        foreach (['public/index.php', 'public/admin.php'] as $name) $out[$name] = (string)file_get_contents($this->getRoot().'/'.$name);
        return $out;
    }

    # The files that call the class are exactly the owners of the map, and each of them carries every key the map gives it
    #[Test]
    public function everyOwnerSpeaksTheMap(): void
    {
        $calls = array_filter($this->getTree(), fn($code) => preg_match('/pnt->(addEvent|getEventId)\(/', $code) === 1);
        $this->assertSame(array_keys(self::OWNERS), array_keys($calls), 'The set of files that call Point is not the owner map of docs/POINTS.md');
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

    # Cut one function or method out of a file of the tree, from its signature to the closing brace of its own indentation
    private function getBody(string $path, string $name): string
    {
        $code = (string)file_get_contents($this->getRoot().'/'.$path);
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from '.$path);
        $top = ($code[$from - 1] ?? "\n") === "\n";
        $end = strpos($code, $top ? "\n}\n" : "\n    }\n", $from);
        return substr($code, $from, ($end === false ? strlen($code) : $end) - $from);
    }

    # Assert that the marks appear in one body in the order given, each of them once at least
    private function checkOrder(string $body, array $marks, string $note): void
    {
        $last = -1;
        foreach ($marks as $mark) {
            $at = strpos($body, $mark);
            $this->assertNotFalse($at, $note.': '.$mark.' is gone');
            $this->assertGreaterThan($last, $at, $note.': '.$mark.' comes too early');
            $last = $at;
        }
    }

    # The lock order of docs/NODE.md (The single lock order): the rows of an extension and of a discussion come before the accounts of Point
    # An operation that moves several accounts locks all of them by ascending id before its first event, so two operations never take them crosswise
    #[Test]
    public function theAccountsAreLockedAfterTheRowsAndBeforeTheFirstEvent(): void
    {
        $node = 'core/classes/node/service.php';
        $this->checkOrder($this->getBody($node, 'addNode'), ['->addNodeData(', '$this->setPublishJob('], 'addNode');
        $this->checkOrder($this->getBody($node, 'updateNode'), ["->updateNodeData(\$before, \$after, \$data['ext'])", '$this->setPublishJob('], 'updateNode');
        $this->checkOrder($this->getBody($node, 'updateNodeStatus'), ['->updateNodeData($before, $after, null)', '$this->setPublishJob('], 'updateNodeStatus');
        $this->checkOrder($this->getBody($node, 'deleteNode'), ['->deleteNodeData(', '$com->deleteTarget($type->name, [$id], [$uid])', '$point->getEventId('], 'deleteNode');
        $com = 'core/classes/comment.php';
        $this->checkOrder($this->getBody($com, 'deleteTarget'), ['FOR UPDATE',
            "\$this->pnt->setUserLocks(array_merge(array_column(\$rows, 'uid'), \$uids))", '$this->updateTargetPoints('], 'deleteTarget');
        $this->checkOrder($this->getBody($com, 'addComment'), ['$this->setNodeCount(', '$this->updateNodeAction(', '$this->updateTargetPoints('], 'addComment');
        $this->checkOrder($this->getBody($com, 'setStatus'), ['$this->setNodeCount(', '$this->updateNodeAction(', '$this->updateTargetPoints('], 'setStatus');
        $this->checkOrder($this->getBody($com, 'deleteComment'), ['$this->setNodeCount(', '$this->updateTargetPoints('], 'deleteComment');
    }

    # The two screens that print the labels build the constant name from the action, which is why no search by name finds a reader
    # The labels are read through constant('_POINTS_'.strtoupper($name)), so a dictionary cleanup that misses this file takes a live label for an unused one
    #[Test]
    public function theLabelsAreReadByActionName(): void
    {
        foreach (['admin/modules/groups.php', 'modules/users/index.php'] as $path) {
            $this->assertStringContainsString("constant('_POINTS_'.strtoupper(\$name))", (string)file_get_contents($this->getRoot().'/'.$path), $path);
        }
    }

    # The journal screen costs the same on a journal of any length: the count stops at 100 pages of 50 rows and no page deeper is read (docs/POINTS.md)
    #[Test]
    public function theJournalCountStopsAtOneHundredPages(): void
    {
        $body = $this->getBody('admin/modules/groups.php', 'getPointsJournal');
        $this->assertStringContainsString("\$num = min(100, max(1, getVar('get', 'num', 'num', 1)));", $body);
        $this->assertStringContainsString("'SELECT COUNT(*) FROM (SELECT 1 FROM '.PREFIX_DB.'_points AS p'.\$cond.' LIMIT 5000) AS q'", $body);
        $this->assertStringNotContainsString("'SELECT COUNT(*) FROM '.PREFIX_DB.'_points", $body, 'No count over the whole journal remains');
        $this->assertStringContainsString("'offset' => (\$num - 1) * 50, 'limit' => 50", $body);
    }

    # The interface of points follows the open class, never the stored switch alone: before the data update left its mark the class stays closed and so does the interface
    #[Test]
    public function theInterfaceFollowsTheOpenClass(): void
    {
        $code = (string)file_get_contents($this->getRoot().'/core/classes/point.php');
        $this->assertStringContainsString('public private(set) bool $active;', $code, 'The class does not tell its owners whether it rewards');
        foreach ($this->getTree() as $path => $code) {
            if ($path === 'admin/modules/groups.php') continue;
            $this->assertStringNotContainsString("\$conf['points']['active']", $code, $path.' shows points by the stored switch');
        }
        foreach (['core/helpers.php', 'core/user.php', 'modules/account/index.php', 'modules/users/index.php', 'blocks/user_info.php'] as $path) {
            $this->assertStringContainsString('$pnt->active', (string)file_get_contents($this->getRoot().'/'.$path), $path);
        }
    }
}
