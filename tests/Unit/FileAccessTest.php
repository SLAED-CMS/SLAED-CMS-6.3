<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The delivery decision of every file: one adapter per owner, each address form, a refusal of every name a target does not carry, the closed owners on route_probe.php files
final class FileAccessTest extends TestCase
{
    private const ROOM = 'zzfileaccess';
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';
    private static array $probe = [];

    # Run tests/Support/route_probe.php files once and memoize it; the routes may write no PHP and no SQL error
    private function getRun(): array
    {
        if (self::$probe === []) {
            $script = dirname(__DIR__).'/Support/route_probe.php';
            $work = str_replace('\\', '/', sys_get_temp_dir()).'/slaed_files_route';
            $out = (string)shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($script).' '.escapeshellarg($work).' files 2>&1');
            $data = json_decode($out, true);
            $this->assertIsArray($data, 'The probe did not return JSON: '.substr($out, 0, 600));
            $this->assertSame('', $data['error'], 'The probe failed');
            $this->assertTrue($data['clean'], 'The probe left a disposable database on the server');
            $this->assertSame(['error_php.log' => [], 'error_sql.log' => []], $data['logs'], 'The routes wrote PHP or SQL errors');
            self::$probe = $data['runs']['files'];
        }
        return self::$probe;
    }

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
            'comment' => ['folder' => fn(string $mod, int $id): string => self::ROOM, 'grant' => $grant],
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
        $this->assertSame(['node', 'forum', 'privat', 'comment', 'profile', 'public'], \FileAccess::OWNERS);
        $this->assertSame(['node', 'forum', 'privat', 'comment', 'profile'], \FileAccess::PREVIEW);
        foreach (['mystery', 'Forum', 'FORUM', '', 'forum ', 'privat'] as $own) {
            $this->assertSame('', $fac->getFileFolder($own, 'forum', 5), 'A folder of '.$own);
            $this->assertSame('', $fac->getFileUrl($own, 'forum', 5, 'plain.png'), 'An address of '.$own);
            $this->assertSame('', $fac->getFilePath($own, 'forum', 5, 'plain.png'), 'A file of '.$own);
        }
    }

    # Every closed owner answers the file route, an owner of PREVIEW also its preview with a name, a public folder its direct link, and no owner without a target and a name
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
        $this->assertSame('index.php?go=file&own=forum&name=forum&key=a.png&preview=1', $fac->getFileUrl('forum', 'forum', 0, 'a.png'));
        $this->assertSame('index.php?go=file&own=comment&id=5&key=a.png', $fac->getFileUrl('comment', 'voting', 5, 'a.png'));
        $this->assertSame('index.php?go=file&own=comment&name=voting&key=a.png&preview=1', $fac->getFileUrl('comment', 'voting', 0, 'a.png'));
        $this->assertSame('', $fac->getFileUrl('comment', '', 0, 'a.png'), 'A comment preview without its name got an address');
        $this->assertSame('', $fac->getFileUrl('forum', '', 0, 'a.png'), 'A forum preview without its name got an address');
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

    # A post of the forum serves the names its text carries to a reader of its category while it and its topic are published; the folder has no direct address
    #[Test]
    public function aForumPostServesItsReader(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 'image/png', 'private, no-cache, must-revalidate, no-transform'], $run['file']);
        $this->assertSame([200, 200, 404, 404, 404, 404, 404, 404], $run['reader'],
            'A guest got an unpublished reply, a hidden topic, a closed category, a name of another post, a name no post carries or a missing thumb');
        $this->assertSame([200, 404, 404], $run['club'], 'The club category refused its member, served a stranger, or a hidden topic served a member');
        $this->assertSame([200, 200, 200, 404], $run['moder'], 'The moderator of the forum was refused, or a moderator of a type read the forum');
        $this->assertSame([410, 410], $run['direct'], 'The light path served the closed forum folder');
        $this->assertSame([true, true, false], $run['page'], 'The topic page does not print the file route or still prints the folder');
    }

    # The preview of the forum serves the visitor's own upload and any to a moderator of the forum, never a foreign one, a name of a post or another name
    #[Test]
    public function aForumPreviewServesTheOwnUpload(): void
    {
        $this->assertSame([200, 404, 404, 200, 404, 404, 404], $this->getRun()['preview']);
        $this->assertSame([true, 2], $this->getRun()['draft'], 'The preview of an unsaved post does not take the preview address or stored the post');
    }

    # A writer of a post binds a new name only to an own upload or to a name the topic already carries: a foreign file keeps the form open with its refusal
    #[Test]
    public function aForumWriterBindsOnlyItsOwnOrAQuotedName(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, 200, true, 0], $run['send'], 'A reply naming a foreign file was stored or did not name the refused file');
        $this->assertSame([303, 1], $run['quote'], 'A quote of a name the topic carries was refused');
        $this->assertSame([303, 2], $run['own'], 'A reply naming an own upload was refused');
    }

    # A private message serves its names to the sides that still hold it and to a moderator of account, whatever extensions the rule takes; the folder has no direct address
    #[Test]
    public function aPrivateMessageServesItsTwoSides(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 200, 200, 404, 404, 404, 200, 404, 200, 404], $run['privat'],
            'A side was refused, a third account, a guest, a deleted side, a name the message does not carry or a moderator of a type was served, or root refused');
        $this->assertSame([200, 404, 404], $run['rule'], 'A stored PDF the rule of account does not take was refused to its side, or served to a stranger or as a preview');
        $this->assertSame([410, 410], $run['shut'], 'The light path served the folder of the private messages or of the account texts');
        $this->assertSame([true, true, false], $run['mpage'], 'The opened message does not print the file route of its body and its signature, or still prints the folder');
    }

    # The preview of a private message serves the own upload of account and any to a moderator, never another member's or under another name
    #[Test]
    public function aPrivatePreviewServesTheOwnUpload(): void
    {
        $this->assertSame([200, 404, 404, 200, 404], $this->getRun()['pview']);
    }

    # A writer of a message binds an own upload or a name a message they still read carries: a forward goes through, a foreign file and a deleted copy do not
    #[Test]
    public function aMessageWriterBindsItsOwnOrAReadName(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, true, 0], $run['msend'], 'A message naming a foreign file was stored or did not name the refused file');
        $this->assertSame([true, 0], $run['mgone'], 'A message naming a file of a copy the writer deleted was stored');
        $this->assertSame([2, 200, 404], $run['mfwd'], 'A forward or an own upload was refused, its recipient cannot read it, or the first sender reads the forward');
    }

    # A signature serves its names to every reader of profiles on every page that shows it, and nothing when profiles are closed to guests
    #[Test]
    public function aSignatureServesEveryReaderOfProfiles(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 200, 404, 404], $run['sign'], 'A guest or a member was refused, or another account or a name the signature does not carry was served');
        $this->assertSame([404, 200], $run['closed'], 'Closed profiles served a guest or refused a member');
        $this->assertSame([true, false], $run['spage'], 'The topic page does not print the file route of the signature or still prints the account folder');
        $this->assertTrue($run['sprof'], 'The profile page does not print the file route of the signature');
    }

    # The account texts upload into profile, its preview serves the own upload, and a signature binds an own file and refuses a foreign one
    #[Test]
    public function aSignatureBindsOnlyAnOwnFile(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 404, 404], $run['sview'], 'The preview of profile served a foreign upload or refused the own one');
        $this->assertSame([true, true, ''], $run['ssave'], 'A signature naming a foreign file was stored or did not name the refused file');
        $this->assertSame(['Anna [attach=own-aaaaaaaaaa-2.png align=left title=m]', 200, true], $run['sown'],
            'An own file was refused, is not served through the signature, or the editor does not upload into profile');
    }

    # A comment on a poll serves its names while it is published and its poll is shown, every one to a moderator of voting; the folder of the poll comments has no direct address
    #[Test]
    public function aPollCommentServesTheReaderOfItsPoll(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 200, 404, 404, 200, 404, 404, 404], $run['poll'],
            'A published comment or its thumb was refused, or a pending one served a guest or its author, a hidden poll, a foreign name or a moderator of a type');
        $this->assertSame([410, 410], $run['vshut'], 'The light path served the folder of the poll comments');
        $this->assertSame([200, 404, 404, 200, 404], $run['vview'], 'The preview of voting served a foreign upload, refused the own one, or served the folder of profile');
        $this->assertSame([true, false, false], $run['vpage'], 'The poll page does not print the file route, still prints the folder, or shows a pending comment');
    }

    # A comment on a profile serves its names to whoever may open the profile while it is published, and the own block serves its names to its owner alone
    #[Test]
    public function aProfileCommentServesTheReaderOfTheProfile(): void
    {
        $run = $this->getRun();
        $this->assertSame([200, 404, 404, 200, 404], $run['prof'],
            'A published comment was refused, a pending one served a guest or its author, root refused, or the file of a comment served as an account text');
        $this->assertSame([404, 200], $run['pshut'], 'Closed profiles served a comment to a guest or refused a member');
        $this->assertSame([true, false], $run['ppage'], 'The profile page does not print the file route of a comment or shows a pending one');
        $this->assertSame([200, 404, 404, 404], $run['block'], 'The own block served another account, a guest or an administrator, or refused its owner');
    }

    # A writer of a comment binds an own upload or a name the same target already serves, for a poll, a profile and a Node material alike, and never a foreign file
    #[Test]
    public function aCommentWriterBindsOnlyItsOwnOrAServedName(): void
    {
        $run = $this->getRun();
        $this->assertSame([true, true, true, 2], $run['pwrite'], 'A poll comment naming a foreign file or one of another poll was stored, or a quote or an own upload refused');
        $this->assertSame([true, true, true], $run['pedit'], 'The author edit stored a foreign file or did not name it, or refused a name the poll serves');
        $this->assertSame([true, true], $run['medit'], 'The moderation form of the main administrator refused a file of the folder');
        $this->assertSame([true, 1], $run['awrite'], 'A profile comment naming a file of the own block was stored, or an own upload refused');
        $this->assertSame([true, 1], $run['nwrite'], 'A Node comment naming a foreign file of the type was stored, or a name of its material refused');
    }
}
