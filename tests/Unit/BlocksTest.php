<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The blocks over real requests, driven by tests/Support/route_probe.php blocks: the zones of a type and of a page, the defects D1 and D3-D7, the order the panel writes
final class BlocksTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its blocks mode and memoize the run; the requests may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_blocks_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' blocks 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame([], $data['logs']['error_php.log'], 'The requests wrote PHP errors');
            $this->assertSame([], $data['logs']['error_sql.log'], 'The requests wrote SQL errors');
            self::$probe = $data['runs']['blocks'];
        }
        return self::$probe;
    }

    # A Node type draws the columns of its own settings, docs none at the side, and a page of the builder draws its own and the blocks that name page-<key>
    #[Test]
    public function aTypeAndAPageKeepTheirColumns(): void
    {
        $run = $this->getRun();
        [$right, $after, , , $page] = $run['news'];
        $this->assertSame([true, true, false], [$right, $after, $page], 'News lost the right or the centre column, or drew a block of a page');
        $this->assertSame([false, true], $run['docs'][1], 'A material of docs drew the side columns its type switched off, or lost the centre');
        $this->assertSame([false, false, true], $run['docs'][0], 'The list of docs did not take the columns of the page holding its slot');
        $this->assertSame([true, true, true], $run['about'], 'The page About lost a column or the block that names page-10');
    }

    # D3 and D4: an expired block switches off and the next of its column still shows; a block whose file is gone is hidden from a guest and named to an administrator
    #[Test]
    public function aBrokenBlockCutsNothing(): void
    {
        $run = $this->getRun();
        $this->assertFalse($run['news'][2], 'An expired block was shown');
        $this->assertSame([0, null], $run['gone'], 'An expired block stayed on or kept its expiry');
        $this->assertFalse($run['news'][3], 'A guest saw a block whose file is gone');
        $this->assertSame([true], $run['root'], 'An administrator was not shown the block whose file is gone');
        $this->assertTrue($run['news'][5], 'A block that names a gone place beside news was not shown on news');
    }

    # D7: a trusted text prints a free block and a fixed one only where its places allow, code keeps the tag; a writer who is no super administrator loses the tag
    #[Test]
    public function aFreeBlockPrintsInATrustedText(): void
    {
        $run = $this->getRun();
        $this->assertSame([false, false], array_slice($run['news'], 6), 'A free block was drawn in a column');
        $this->assertSame([true, true, true], $run['free'][0], 'A free block, a fixed one on its page or a tag in code went wrong in a material of news');
        $this->assertSame([true, false], $run['free'][1], 'A fixed free block showed outside its places');
        $this->assertSame('x keep', $run['sig'], 'A user kept a trusted tag in a signature');
    }

    # D1 and D5: the panel offers four positions, marks a lost file and a gone place and keeps both checked until the administrator changes them
    #[Test]
    public function thePanelMarksWhatNoLongerAnswers(): void
    {
        [$nofile, $nowhere, $stray, $news, $alert, $lost, $page, $opts, $banner] = $this->getRun()['panel'];
        $this->assertSame([true, true], [$nofile, $nowhere], 'The list did not mark the lost file or the gone place');
        $this->assertSame([true, true, true], [$stray, $news, $alert], 'The form dropped a gone place or did not warn of it');
        $this->assertTrue($lost, 'The form did not keep the lost file chosen');
        $this->assertTrue($page, 'The form did not offer the page of the builder as checked');
        $this->assertSame([4, false], [$opts, $banner], 'The form offered a banner position');
    }

    # The one writer of the order: a new block at the end, a move into another position at its number with the old one closed up, a deletion, a drag and the fix
    #[Test]
    public function theOrderStaysNumberedFromOne(): void
    {
        $run = $this->getRun();
        $this->assertSame(['90:1,94:2,95:3,96:4,97:5,98:6,99:7', 0, 303], $run['add'], 'A new block did not stand last, or a banner position was taken');
        $this->assertSame(['90:1', '94:1,95:2,96:3,97:4,98:5,99:6'], $run['move'], 'A move did not close the position it left');
        $this->assertSame('94:1,96:2,97:3,98:4,99:5', $run['delete'], 'A deletion left a gap');
        $this->assertSame('99:1,98:2,97:3,96:4,94:5', $run['drag'], 'A drag did not take the order');
        $this->assertSame('99:1,98:2,97:3,96:4,94:5', $run['fix'], 'The fix did not number the position from one');
    }
}
