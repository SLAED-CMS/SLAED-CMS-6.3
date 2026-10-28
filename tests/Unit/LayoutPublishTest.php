<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The publication of the site builder over route_probe.php publish: the layout, the addresses and the blocks in one transaction, two editors, the history and an unsure commit
final class LayoutPublishTest extends TestCase
{
    private static array $probe = [];

    # Run the probe once in its publish mode and memoize the run; no request may write a PHP or SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_publish_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' publish 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The requests wrote PHP or SQL errors');
            self::$probe = $data['runs']['publish'];
        }
        return self::$probe;
    }

    # The screen carries its editor, the revision of the draft and the checksum of the site, and every act of the run is answered
    #[Test]
    public function theScreenCarriesWhatItPostsBack(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, true, true, true], $run['marks'], 'The token, the editor, the revision or the checksum is missing from the screen');
        $this->assertSame(array_fill(0, 6, 200), $run['acts']);
    }

    # A write of another editor over a draft it did not see is refused by the name and the time of the last writer, and the draft keeps its pages
    #[Test]
    public function anotherEditorIsRefusedInsteadOfOverwriting(): void
    {
        [$text, $kept] = $this->getRun()['other'];
        $this->assertStringContainsString('Черновик изменён в другом окне: root, ', $text);
        $this->assertTrue($kept, 'The refused write changed the draft');
    }

    # A site changed after the screen opened and a block changed in its own screen since the draft touched it refuse the publication, and nothing is written
    #[Test]
    public function aRefusedPublicationWritesNothing(): void
    {
        $run = $this->getRun();
        $this->assertStringContainsString('Сайт изменён после открытия экрана', $run['stale'][0]);
        $this->assertTrue($run['stale'][1], 'A stale publication wrote the file, an address, a block or the history');
        $this->assertSame('Блок «Elsewhere» изменён в разделе «Блоки и баннеры» после начала правки. Публикация остановлена, сайт прежний.', $run['moved'][0]);
        $this->assertSame([true, true], array_slice($run['moved'], 1), 'A refused publication wrote something, or the draft lost its blocks');
    }

    # One publication writes the layout, the addresses and the blocks, keeps itself in the history with its author and leaves a draft equal to the site
    #[Test]
    public function aPublicationWritesTheLayoutTheAddressesAndTheBlocks(): void
    {
        $done = $this->getRun()['done'];
        $this->assertSame('Опубликовано. Прежняя версия сохранена в истории.', $done['text']);
        $want = ['10:about-team:1', '10:about-us:0', '11:members:1', '12:hidden:2', '13:docs-page:1', '14:unpublished:1', '60:novaya-stranitsa:1'];
        $this->assertSame($want, $done['trace']['routes'], 'A moved page is not former, a deleted one not gone, or a new one has no address');
        $this->assertSame(['92:Left one:l:1:1', '90:Right now:r:1:1', '93:Новый блок:r:2:0'], $done['trace']['blocks'], 'The changed, deleted or new block is not in its zone');
        $this->assertSame([['pub-1.json'], false], [$done['trace']['story'], $done['trace']['marker']]);
        $this->assertSame([[1, 2, 10, 11, 13, 14, 60], ['about-team', 'novaya-stranitsa'], false], [$done['pages'], $done['addr'], $done['shipped']]);
        $this->assertSame([[], [], []], $done['draft'], 'The draft kept blocks, first rows or steps of what is now the site');
        $this->assertSame([1, 'root', 3], $done['story'], 'The history does not name its number, its author or the blocks it published');
        $this->assertTrue($done['live'], 'The answer does not show the published state');
    }

    # The addresses answer as the registry says: the former path moves to the new one, the deleted page is gone, the new page answers at its own
    #[Test]
    public function theAddressesFollowThePublication(): void
    {
        $this->assertSame(['301 ~/about-team', '200 ', '410 ', '200 '], $this->getRun()['visit']);
    }

    # The history lists the publication as the one on the site, and bringing it back makes the draft equal to the site again as one step with Undo
    #[Test]
    public function aVersionOfTheHistoryComesBackIntoTheDraft(): void
    {
        $run = $this->getRun();
        $this->assertSame([1, true, true], $run['list']);
        $this->assertSame([true, true, 2], $run['back'], 'The version did not come back as the site, or not as a step of its own');
    }

    # A commit the database does not confirm keeps the marker with the witness row and holds no Node type; the restore takes the side that row shows
    #[Test]
    public function anUnsurePublicationIsFinishedByItsWitness(): void
    {
        $unsure = $this->getRun()['unsure'];
        $mark = ['state' => 'uncertain', 'why' => 'proof', 'types' => [], 'marker' => true, 'proof' => ['layout', 'blocks', 90]];
        $this->assertSame([$mark, ['done' => true, 'page' => 'About', 'block' => 'Right now', 'marker' => false]], $unsure['drop'], 'A rolled back commit was not taken back');
        $this->assertSame([$mark, ['done' => true, 'page' => 'About lost', 'block' => 'Unsure', 'marker' => false]], $unsure['lost'], 'A kept commit was not finished');
    }
}
