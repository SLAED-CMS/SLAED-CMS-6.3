<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The group form of the panel: what save() writes and refuses, and the next step of the ladder a new group is offered
final class GroupFormTest extends TestCase
{
    # A group the form posts: a point group at a free threshold with the rank of five stars, three lit, in steel
    private const POST = [
        'gid' => 0, 'grname' => 'Probe', 'description' => '', 'points' => '23000', 'grextra' => '', 'color' => '#888888',
        'rank_icon' => 'star-fill', 'rank_lit' => '3', 'rank_total' => '5', 'rank_color' => '#8297a2', 'rank_sync' => '0',
    ];

    # Run one scenario of tests/Support/group_probe.php, which lifts the shipped functions over stubs, and answer its report
    private function getProbe(string $mode, array $case): array
    {
        $script = dirname(__DIR__).'/Support/group_probe.php';
        $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_group_'.$mode.'_'.md5((string)json_encode($case)).'_'.getmypid();
        $cmd = escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($mode).' '.escapeshellarg($work)
            .' '.escapeshellarg(base64_encode((string)json_encode($case)));
        $out = (string)shell_exec($cmd.' 2>&1');
        foreach (glob($work.'/*') ?: [] as $file) unlink($file);
        if (is_dir($work)) rmdir($work);
        $data = json_decode($out, true);
        $this->assertIsArray($data, 'Probe '.$mode.' did not return JSON: '.$out);
        $this->assertSame('', $data['error'], 'Probe '.$mode.' failed');
        return $data['data'];
    }

    # A point group posted on a threshold another point group holds is handed back to its form with the name of that group, and nothing is written
    #[Test]
    public function aTakenThresholdIsRefused(): void
    {
        $data = $this->getProbe('save', ['post' => ['points' => '1000'] + self::POST, 'row' => ['Пользователи', '1000']]);
        $this->assertArrayNotHasKey('write', $data);
        $this->assertSame('taken Пользователи 1 000', $data['stop']);
        $this->assertSame(['points' => 1000, 'id' => 0], $data['read'][0]);
    }

    # A special group has no threshold, so a taken one does not stop it and it is stored at zero
    #[Test]
    public function aSpecialGroupIgnoresTheThreshold(): void
    {
        $data = $this->getProbe('save', ['post' => ['points' => '1000', 'grextra' => '1'] + self::POST, 'row' => ['Пользователи', '1000']]);
        $this->assertSame(0, $data['write']['points']);
        $this->assertSame(1, $data['write']['extra']);
        $this->assertFalse($data['warn']);
    }

    # The counts are clamped: at most ten marks, at least one, and never more lit than there are
    #[Test]
    public function theCountsAreClamped(): void
    {
        $rank = fn(array $post): string => $this->getProbe('save', ['post' => $post + self::POST])['write']['rank'];
        $this->assertSame('star-fill:10:10:#8297a2', $rank(['rank_total' => '15', 'rank_lit' => '12']));
        $this->assertSame('star-fill:1:1:#8297a2', $rank(['rank_total' => '0', 'rank_lit' => '3']));
        $this->assertSame('star-fill:0:5:#8297a2', $rank(['rank_lit' => 'x']));
        $this->assertSame('star-fill:3:5:#8297a2', $rank([]));
    }

    # A rank bound to the group takes the group colour, whatever the own colour field still holds
    #[Test]
    public function aBoundRankTakesTheGroupColour(): void
    {
        $data = $this->getProbe('save', ['post' => ['rank_sync' => '1', 'color' => '#2E8FFF'] + self::POST]);
        $this->assertSame('star-fill:3:5:#2e8fff', $data['write']['rank']);
        $this->assertSame('#2e8fff', $data['write']['color']);
    }

    # An icon or a colour outside the rules of getRankData() is refused, and so is a group colour that is no #rrggbb
    #[Test]
    public function aBadIconOrColourIsRefused(): void
    {
        $this->assertSame('_groups_badrank', $this->getProbe('save', ['post' => ['rank_icon' => 'Star Fill"'] + self::POST])['stop']);
        $this->assertSame('_groups_badrank', $this->getProbe('save', ['post' => ['rank_color' => 'red'] + self::POST])['stop']);
        $data = $this->getProbe('save', ['post' => ['color' => 'red;x'] + self::POST]);
        $this->assertArrayNotHasKey('write', $data);
        $this->assertStringContainsString('_groups_badcolor', $data['stop']);
    }

    # The stand's top step, 15 000 with five of five stars: half again above rounded to a thousand, a sixth mark lit, the steel kept, the first swatch
    #[Test]
    public function theNextStepFollowsTheStandLadder(): void
    {
        $next = $this->getProbe('next', ['row' => ['Опытные', '15000', 'star-fill:5:5:#8297a2', '#003f87']])['next'];
        $this->assertSame(['points' => 23000, 'rank' => 'star-fill:6:6:#8297a2', 'color' => '#888888', 'after' => 'Опытные'], $next);
    }

    # Below a thousand the threshold rounds to 25, a rank with marks to spare only lights one more, and the colour after the top one in the palette follows
    #[Test]
    public function theNextStepOfALowLadder(): void
    {
        $next = $this->getProbe('next', ['row' => ['Посетители', '250', 'star-fill:1:5:#8297a2', '#8c08ff']])['next'];
        $this->assertSame(['points' => 375, 'rank' => 'star-fill:2:5:#8297a2', 'color' => '#888888', 'after' => 'Посетители'], $next);
        $next = $this->getProbe('next', ['row' => ['Новички', '25', 'rank_1.png', '#1eab1f']])['next'];
        $this->assertSame(['points' => 50, 'rank' => 'star-fill:2:5:#2e8fff', 'color' => '#059600', 'after' => 'Новички'], $next);
    }

    # A site without point groups starts its ladder at zero with one lit star of five in blue and no step to name
    #[Test]
    public function anEmptyLadderStartsAtZero(): void
    {
        $next = $this->getProbe('next', ['row' => []])['next'];
        $this->assertSame(['points' => 0, 'rank' => 'star-fill:1:5:#2e8fff', 'color' => '#2e8fff', 'after' => ''], $next);
    }
}
