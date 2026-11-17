<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The page of the module users as its source and its templates state it: the query of the list, the search term, the addresses of the old pages
final class UsersListTest extends TestCase
{
    private function getCode(): string
    {
        return (string)file_get_contents(dirname(__DIR__, 2).'/modules/users/index.php');
    }

    # getSqlRow() answers a row under names and numbers at once, so a column named by a bare number would overwrite the column standing at that number
    #[Test]
    public function everyComputedColumnCarriesName(): void
    {
        $code = $this->getCode();
        $this->assertStringContainsString("r.path, '.\$rank\n        .' AS spot FROM '", $code, 'The place of a member is a column without a name of its own');
        $this->assertSame(0, preg_match('/SELECT[^;]*,\s*[0-9]+\s+FROM/i', $code), 'A query of the module selects a bare number as a column');
    }

    # The name a visitor searches is bound and its wildcards are escaped, and the order of the list comes from a closed map of columns
    #[Test]
    public function searchTermIsBoundAndEscaped(): void
    {
        $code = $this->getCode();
        $this->assertStringContainsString("'u.name LIKE :find ESCAPE \\'!\\''", $code);
        $this->assertStringContainsString("str_replace(['!', '%', '_'], ['!!', '!%', '!_'], \$find)", $code);
        $this->assertStringContainsString("\$cols = ['points' => 'u.points', 'reg' => 'u.regdate', 'visit' => 'u.lastvis', 'name' => 'u.name'];", $code);
        $this->assertStringContainsString("if ((\$sort !== '' && !isset(\$ways[\$sort])) || !in_array(\$dir, ['', 'asc', 'desc'], true)) setError(404);", $code);
    }

    # A member stands on one step, so the count of a step leaves out those who reached the next one: the filter, the window of a step and its count then name the same members
    #[Test]
    public function stepCountsItsOwnMembers(): void
    {
        $code = $this->getCode();
        $this->assertStringContainsString("foreach (array_keys(\$out[0]) as \$i) \$out[0][\$i]['num'] -= \$out[0][\$i + 1]['num'] ?? 0;", $code);
        $this->assertStringContainsString("if (isset(\$pick['till'])) [\$part[], \$bind['hi']] = ['u.points < :hi', \$pick['till']];", $code);
        $this->assertStringContainsString(": [\$pick ? \$pick['num'] : \$total];", $code, 'The list of a step counts its rows apart from the count of the step');
    }

    # The pedestal holds the best by points alone: a later page and a list in another order stand as rows, or places 16 to 18 would be named the leaders
    #[Test]
    public function pedestalHoldsTheBestByPoints(): void
    {
        $code = $this->getCode();
        $this->assertStringContainsString("\$top = (\$look === 'podium' && \$head === 1 && \$key === 'points' && !\$asc) ? 3 : 0;", $code);
        $this->assertStringContainsString("'lead' => array_slice(\$rows, 0, \$top),", $code);
        $this->assertStringContainsString("'is_podium' => \$top > 0,", $code);
    }

    # The rules and the statistics moved into windows of the list, and their old addresses open the list with that window open
    #[Test]
    public function oldPagesOpenTheirWindows(): void
    {
        $code = $this->getCode();
        $this->assertStringContainsString("case 'rules': users('rules'); break;", $code);
        $this->assertStringContainsString("case 'stats': users('stats'); break;", $code);
        $route = (string)file_get_contents(dirname(__DIR__, 2).'/core/classes/route.php');
        $this->assertStringContainsString("'users' => ['rules', 'stats'],", $route, 'The readable addresses of the two old pages are gone');
    }

    # The page walks its rows in the theme: the module hands arrays over, and the head of a Node list takes the search and the windows it is given
    #[Test]
    public function themeDrawsRowsFromData(): void
    {
        $base = dirname(__DIR__, 2).'/public/templates/aurora/partials/';
        $page = (string)file_get_contents($base.'users.html');
        $head = (string)file_get_contents($base.'node/head.html');
        $this->assertStringContainsString("{% include 'partials/node/head.html' with head %}", $page);
        $this->assertStringContainsString('{% for row in tier.rows %}', $page);
        $this->assertStringContainsString('{% for win in wins %}', $head);
        $this->assertStringContainsString('{% if seek_href %}', $head);
        $this->assertStringNotContainsString('getModuleNavi', $this->getCode(), 'The module prints the old row of buttons again');
    }
}
