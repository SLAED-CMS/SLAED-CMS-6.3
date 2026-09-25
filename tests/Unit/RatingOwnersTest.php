<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Stage S05 of docs/node: the shared rating is wired to the Rating class alone. The old shared table is left to the
 * polls, the vote reads nothing from the address, the rules are the four keys of docs/node/ratings.md behind the mark
 * of the 6.3 data update, the mass reset of votes is gone, and every text of the wiring exists in all six locales.
 */
final class RatingOwnersTest extends TestCase
{
    private const SITE = ['_RATINGS_FORM', '_RATINGS_DENY', '_RATINGS_GONE', '_RATINGS_TWICE', '_RATINGS_WAIT', '_RATINGS_FAIL'];

    private const ADMIN = [
        '_RATINGS_GUESTS', '_RATINGS_NOMARK', '_RATINGS_BADDAYS', '_RATINGS_VOTES', '_RATINGS_ANNUL', '_RATINGS_REASON', '_RATINGS_DONE', '_RATINGS_ACTOR', '_RATINGS_TARGET',
    ];

    # The root of the tree
    private function getRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    # One shipped file as text
    private function getCode(string $path): string
    {
        return (string)file_get_contents($this->getRoot().'/'.$path);
    }

    # The body of one function of core/system.php
    private function getBody(string $name): string
    {
        $code = $this->getCode('core/system.php');
        $from = strpos($code, 'function '.$name.'(');
        $this->assertNotFalse($from, $name.'() is gone from core/system.php');
        return substr($code, $from, strpos($code, "\n}\n", $from) - $from);
    }

    # Every statement of the running system that names the old shared table speaks of polls; ratings write to their own three tables through the class
    #[Test]
    public function theOldTableIsLeftToThePolls(): void
    {
        foreach (['admin', 'blocks', 'core', 'modules', 'plugins'] as $dir) {
            $walk = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->getRoot().'/'.$dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($walk as $file) {
                if ($file->getExtension() !== 'php') continue;
                foreach (file($file->getPathname()) as $num => $line) {
                    if (!preg_match('/_rating(?![_a-z])/', $line) || !str_contains($line, 'PREFIX_DB')) continue;
                    $this->assertStringContainsString("'voting'", $line, $file->getPathname().':'.($num + 1).' reaches the old rating table for something that is no poll');
                }
            }
        }
        $this->assertSame(1, preg_match_all('/new Rating\(/', $this->getCode('core/system.php')), 'The rating subsystem is built in more than one place');
    }

    # The vote is a POST body and nothing else: no query parameter, no cookie of its own, no points, and the method is refused before the token is asked
    #[Test]
    public function theVoteReadsNothingFromTheAddress(): void
    {
        $body = $this->getBody('getRatingView');
        foreach (["getVar('get'", "getVar('req'", '$_GET', '$_REQUEST', '$_COOKIE', 'setcookie', 'pnt'] as $name) {
            $this->assertStringNotContainsString($name, $body, 'getRatingView() still uses '.$name);
        }
        $this->assertLessThan(strpos($body, 'checkSiteToken('), strpos($body, "header('Allow: POST')"), 'The method is not refused before the token');
        $this->assertStringContainsString("'getRatingView'], true))", $this->getCode('index.php'), 'The dispatcher asks for a token before the handler can refuse the method');
        foreach (['rating-bar', 'rating-like'] as $name) {
            $frag = $this->getCode('templates/lite/fragments/'.$name.'.html');
            $this->assertStringContainsString('hx-post="index.php?go=1&amp;op=getRatingView"', $frag, $name);
            $this->assertStringNotContainsString('rate=', $frag, $name.' still carries a voting address with parameters');
        }
    }

    # The shipped rules are exactly four string keys per fixed scope, the readers ask for the keys, and the subsystem opens only behind the mark;
    # the rule node.<name> of a registered Node type belongs to that type and is held by NodeConfigTest
    #[Test]
    public function theRulesAreFourKeysBehindTheMark(): void
    {
        $rules = (require $this->getRoot().'/config/ratings.php')['ratings'];
        $types = array_keys((require $this->getRoot().'/config/node.php')['node']['types'] ?? []);
        $rules = array_diff_key($rules, array_flip(array_map(fn($v) => 'node.'.$v, $types)));
        $this->assertSame(['account', 'forum', 'shop'], array_keys($rules));
        foreach ($rules as $name => $rule) {
            $this->assertSame(['active', 'period', 'detail', 'guests'], array_keys($rule), $name);
            $this->assertSame([true, true, true, true], array_map('is_string', array_values($rule)), $name);
        }
        foreach (['core/system.php', 'core/helpers.php', 'core/user.php', 'modules/account/index.php', 'admin/modules/ratings.php'] as $path) {
            $this->assertDoesNotMatchRegularExpression('/explode\(\'\|\',[^;]*conf\[\'ratings\'\]/', $this->getCode($path), $path.' still reads the positional rule');
        }
        $mark = "(\$conf['update']['ratings'] ?? '') === '6.3.0'";
        $this->assertStringContainsString($mark, $this->getBody('getRatingService'));
        $this->assertStringContainsString($mark, $this->getCode('core/helpers.php'), 'The widget is live without the mark');
    }

    # The account form no longer zeroes the votes of every account around the class, and the only annulment is the one of the main administrator
    #[Test]
    public function theMassResetOfVotesIsGone(): void
    {
        $code = $this->getCode('modules/account/admin/index.php');
        $this->assertDoesNotMatchRegularExpression('/SET\s+votes|tvotes\s*=/', $code);
        $this->assertStringNotContainsString("'name' => 'votes'", $code);
        $admin = $this->getCode('admin/modules/ratings.php');
        $this->assertStringContainsString("case 'votes': votes(); break;", $admin);
        $this->assertStringContainsString("case 'annul': annul(); break;", $admin);
        $this->assertStringContainsString("checkAdminPost('ratings')", $admin);
    }

    # Every text of the wiring is defined exactly once in each of the six locales of its scope
    #[Test]
    public function everyTextExistsInSixLocales(): void
    {
        foreach (['lang' => self::SITE, 'admin/lang' => self::ADMIN] as $dir => $names) {
            foreach (['de', 'en', 'fr', 'pl', 'ru', 'uk'] as $lang) {
                $code = $this->getCode($dir.'/'.$lang.'.php');
                foreach ($names as $name) $this->assertSame(1, substr_count($code, "define('".$name."',"), $dir.'/'.$lang.'.php: '.$name);
            }
        }
    }
}
