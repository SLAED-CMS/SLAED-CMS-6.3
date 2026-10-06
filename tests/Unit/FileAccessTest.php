<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The delivery decision of every uploaded file: one adapter per owner, the address of each form and a refusal for every name a target does not carry (docs/1-FILES-2026.md, batch 4)
final class FileAccessTest extends TestCase
{
    private const ROOM = 'zzfileaccess';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';

    # A closed owner whose texts carry names by target id, a Node owner that grants nothing and the public owner, all over one scratch folder
    private static function getAccess(): \FileAccess
    {
        require_once BASE_DIR.'/core/classes/access.php';
        $texts = [5 => ['plain.png', 'a-abcdefghij-1.png', 'doc.pdf', 'note.txt', 'evil.php', 'gone.png', '.png', 'b.png.'], 6 => ['other.png']];
        $exts = ['png', 'pdf', 'php'];
        $grant = fn(string $mod, int $id, string $key): bool => in_array($key, $texts[$id] ?? [], true)
            && in_array(strtolower(pathinfo($key, PATHINFO_EXTENSION)), $exts, true);
        return new \FileAccess([
            'forum' => ['folder' => fn(string $mod, int $id): string => self::ROOM, 'grant' => $grant],
            'node' => ['folder' => fn(string $mod, int $id): string => 'node/'.$mod, 'grant' => fn(string $mod, int $id, string $key): bool => false],
            'public' => ['folder' => fn(string $mod, int $id): string => $mod],
            'mystery' => ['folder' => fn(string $mod, int $id): string => self::ROOM, 'grant' => fn(string $mod, int $id, string $key): bool => true],
        ]);
    }

    protected function setUp(): void
    {
        $dir = UPLOADS_DIR.'/'.self::ROOM;
        if (!is_dir($dir.'/thumb')) mkdir($dir.'/thumb', 0777, true);
        foreach (['plain.png', 'a-abcdefghij-1.png', 'thumb/plain.png', 'other.png', 'doc.pdf', 'note.txt', 'evil.php', 'b.png'] as $one) {
            file_put_contents($dir.'/'.$one, base64_decode(self::PNG));
        }
    }

    protected function tearDown(): void
    {
        $dir = UPLOADS_DIR.'/'.self::ROOM;
        foreach ((array)glob($dir.'/thumb/*') as $one) unlink((string)$one);
        foreach ((array)glob($dir.'/*.*') as $one) unlink((string)$one);
        if (is_dir($dir.'/thumb')) rmdir($dir.'/thumb');
        if (is_dir($dir)) rmdir($dir);
    }

    # The owner is one of a closed set: a value outside it has no folder, no address and no file, even with an adapter that grants everything
    #[Test]
    public function anOwnerOutsideTheClosedSetIsRefused(): void
    {
        $fac = self::getAccess();
        $this->assertSame(['node', 'forum', 'privat', 'comment', 'public'], \FileAccess::OWNERS);
        foreach (['mystery', 'Forum', 'FORUM', '', 'forum ', 'privat'] as $own) {
            $this->assertSame('', $fac->getFileFolder($own, 'forum', 5), 'A folder of '.$own);
            $this->assertSame('', $fac->getFileUrl($own, 'forum', 5, 'plain.png'), 'An address of '.$own);
            $this->assertSame('', $fac->getFilePath($own, 'forum', 5, 'plain.png'), 'A file of '.$own);
        }
    }

    # Every closed owner answers the file route, Node also with the preview of its type, a public folder its direct link, and an owner without a target nothing
    #[Test]
    public function everyOwnerAnswersItsOwnAddress(): void
    {
        $fac = self::getAccess();
        $this->assertSame('index.php?go=file&own=node&id=7&key=my%20file.pdf', $fac->getFileUrl('node', 'news', 7, 'my file.pdf'));
        $this->assertSame('index.php?go=file&own=node&id=7&key=a.png&thumb=1', $fac->getFileUrl('node', 'news', 7, 'a.png', true));
        $this->assertSame('index.php?go=file&own=node&name=news&key=a.png&preview=1', $fac->getFileUrl('node', 'news', 0, 'a.png'));
        $this->assertSame('index.php?go=file&own=node&name=news&key=a.png&preview=1&thumb=1', $fac->getFileUrl('node', 'news', 0, 'a.png', true));
        $this->assertSame('index.php?go=file&own=forum&id=5&key=my%20file.pdf', $fac->getFileUrl('forum', 'forum', 5, 'my file.pdf'));
        $this->assertSame('index.php?go=file&own=forum&id=5&key=a.png&thumb=1', $fac->getFileUrl('forum', '', 5, 'a.png', true));
        $this->assertSame('uploads/all/a.png', $fac->getFileUrl('public', 'all', 0, 'a.png'));
        $this->assertSame('uploads/all/thumb/a.png', $fac->getFileUrl('public', 'all', 0, 'a.png', true));
        $this->assertSame('', $fac->getFileUrl('public', self::ROOM, 0, 'a.png'), 'A folder off the public list got a direct address');
        $this->assertSame('', $fac->getFileUrl('forum', 'forum', 0, 'a.png'), 'A closed owner without a target got an address');
        $this->assertSame('', $fac->getFileUrl('node', '', 0, 'a.png'), 'A Node preview without its type got an address');
        $this->assertSame('node/news', $fac->getFileFolder('node', 'news'));
        $this->assertSame(self::ROOM, $fac->getFileFolder('forum', '', 5));
    }

    # A name the text of the target carries is served whatever its form, a thumb only when its copy exists, and the public owner has no route at all
    #[Test]
    public function aCarriedNameIsServedWhateverItsForm(): void
    {
        $fac = self::getAccess();
        $dir = str_replace('\\', '/', (string)realpath(UPLOADS_DIR.'/'.self::ROOM));
        $this->assertSame($dir.'/plain.png', $fac->getFilePath('forum', '', 5, 'plain.png'), 'A name without a random part was refused');
        $this->assertSame($dir.'/a-abcdefghij-1.png', $fac->getFilePath('forum', '', 5, 'a-abcdefghij-1.png'));
        $this->assertSame($dir.'/doc.pdf', $fac->getFilePath('forum', '', 5, 'doc.pdf'));
        $this->assertSame($dir.'/thumb/plain.png', $fac->getFilePath('forum', '', 5, 'plain.png', true));
        $this->assertSame('', $fac->getFilePath('forum', '', 5, 'doc.pdf', true), 'A thumb that does not exist was served');
        $this->assertSame('', $fac->getFilePath('forum', '', 5, 'gone.png'), 'A name of a missing file was served');
        $this->assertSame('', $fac->getFilePath('public', self::ROOM, 5, 'plain.png'), 'The public owner answered a route');
        $this->assertSame('', $fac->getFilePath('node', 'news', 5, 'plain.png'), 'A refused Node grant was served');
    }

    # A name of another target, a name the target does not carry, a negative target and every name that is not bare or of a type the owner allows are refused alike
    #[Test]
    public function everyOtherNameIsRefused(): void
    {
        $fac = self::getAccess();
        $this->assertNotSame('', $fac->getFilePath('forum', '', 6, 'other.png'));
        $this->assertSame('', $fac->getFilePath('forum', '', 5, 'other.png'), 'A name of another target was served');
        $this->assertSame('', $fac->getFilePath('forum', '', 6, 'plain.png'), 'A name of another target was served');
        $this->assertSame('', $fac->getFilePath('forum', '', 7, 'plain.png'), 'A target without a text was served');
        $this->assertSame('', $fac->getFilePath('forum', '', -5, 'plain.png'), 'A negative target was served');
        $this->assertSame('', $fac->getFilePath('forum', '', 5, 'note.txt'), 'An extension the owner does not allow was served');
        $this->assertSame('', $fac->getFilePath('forum', '', 5, 'evil.php'), 'An extension no upload accepts was served');
        $bad = ['', '.png', 'b.png.', '../'.self::ROOM.'/plain.png', self::ROOM.'/plain.png', 'thumb/plain.png', 'plain.png%00', "plain.png\0", 'plain.png/', 'PLAIN.png',
            str_repeat('a', 252).'.png', 'pl@in.png'];
        foreach ($bad as $key) $this->assertSame('', $fac->getFilePath('forum', '', 5, $key), 'A name that is no bare carried name was served: '.$key);
    }
}
