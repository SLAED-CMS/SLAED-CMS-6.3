<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;

# The public form of Node over route_probe.php secure: the upload right and limits, the write window, the captcha, the categories, the links, the notice and the import
final class NodeRouteFormTest extends NodeRouteCase
{
    # A file of the public form is stored only under the upload right of the type, for an active role that is no link, within the max of its role and maxfiles of the rule
    #[Test]
    public function theFormStoresAFileOnlyWithinItsRightAndLimits(): void
    {
        $run = $this->getMode('secure')['upload'];
        $this->assertSame([422, 0], $run['guest'], 'A guest stored a file although the rule of the type has no guest upload');
        $this->assertSame([200, 1], $run['user'], 'A member with the upload right could not store a cover');
        $this->assertSame([422, 1], $run['role'], 'A second file of a role with max 1 was stored');
        $this->assertSame([422, 2], $run['request'], 'A third file of a request under maxfiles 2 was stored');
        $this->assertSame([422, 0], $run['inactive'], 'An inactive role took a file');
        $this->assertSame([422, 0], $run['link'], 'A link role took a file');
    }

    # A visitor who does not moderate the type waits limits.send after the last material of the address; a moderator of the type does not
    #[Test]
    public function thePublicWriteWaitsForTheWindowOfTheAddress(): void
    {
        $this->assertSame([303, 429, 303, 2], $this->getMode('secure')['window'], 'The second submit inside the window was stored, or the window held the moderator');
    }

    # A guest passes the captcha of comments on every post: none or a forged one stores no material and sends no mail, a solved one submits; a member sees none
    #[Test]
    public function aGuestSubmitsOnlyThroughTheCaptcha(): void
    {
        $this->assertSame([true, false, 422, 422, 0, 0, 303, 1, true], $this->getMode('secure')['captcha'],
            'The captcha is missing for a guest or shown to a member, a post without a solved captcha stored a material or a mail, or a solved one was refused');
    }

    # The title of a material reaches the search result escaped once, in the link and in its tooltip
    #[Test]
    public function theSearchEscapesANodeTitleOnce(): void
    {
        $this->assertSame([true, false], $this->getMode('secure')['search'], 'The tooltip of a Node title in search is escaped twice');
    }

    # The form offers a category only when its view right shows it and its post right admits the writer: a guest sees Lobby alone, a member Secret and Open as well
    #[Test]
    public function theFormOffersOnlyCategoriesTheWriterSeesAndPostsTo(): void
    {
        $this->assertSame([true, false, false, true, true], $this->getMode('secure')['cats'],
            'The form offered a hidden category or one closed to the writer, or missed an open one');
        $this->assertSame([403, 200], $this->getMode('secure')['hidden'], 'A guest posted into a category hidden from guests, or a member could not');
    }

    # A related or parent link to a material the writer may not read - a closed category or a scheduled one - answers exactly as a link to a missing material
    #[Test]
    public function aLinkToAnUnreadableMaterialAnswersAsAMissingOne(): void
    {
        $run = $this->getMode('secure')['refs'];
        $this->assertSame([[422, 422], [422, 422], [422, 422], [200, 200]], $run['guest'],
            'A hidden or scheduled material answered unlike a missing one, or an open one was refused');
        $this->assertSame([200, 422], $run['user'], 'A member could not link a material of a members category, or linked a scheduled one');
    }

    # The pending notice to the moderators carries the raw title of a guest escaped in its HTML body
    #[Test]
    public function thePendingNoticeEscapesTheTitle(): void
    {
        $this->assertSame([303, true, true, false], $this->getMode('secure')['mail'],
            'The pending material or its notice is missing, or the notice carries live markup of the title');
    }

    # A profile is imported only from a .json file: the same export under another extension is refused and creates no type
    #[Test]
    public function theImportTakesOnlyAJsonFile(): void
    {
        $run = $this->getMode('secure')['import'];
        $this->assertTrue($run[0], 'The export of the probe type is no JSON');
        $this->assertSame([422, 0, 303, 1], array_slice($run, 1), 'A file of another extension was imported, or the .json file was refused');
    }

    # A refused import names its reason: a wrong extension, a file over 1 MiB and a definition the writer refuses each answer 422 with a text of their own
    #[Test]
    public function theImportNamesWhyAFileIsRefused(): void
    {
        [$ext, $big, $bad, $made] = $this->getMode('secure')['importtext'];
        $this->assertSame([422, 422, 422, 0], [$ext[0], $big[0], $bad[0], $made], 'A refused file was imported or answered another status');
        $texts = [$ext[1], $big[1], $bad[1]];
        $this->assertNotContains('', $texts, 'A refused import shows no alert');
        $this->assertSame($texts, array_values(array_unique($texts)), 'Two reasons of a refused import answer the same text');
    }
}
