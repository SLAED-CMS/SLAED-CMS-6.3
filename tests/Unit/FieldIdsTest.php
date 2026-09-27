<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

# The getFieldIds() helper is the one owner of the three ids a form row needs, held on its own because every IDREF downstream derives from it
class FieldIdsTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!function_exists('getFieldIds')) require_once dirname(__DIR__, 2).'/core/helpers.php';
    }

    #[Test]
    public function testAnswerCarriesTheThreeKeys(): void
    {
        $ids = getFieldIds('f-title');
        $this->assertSame(['input', 'label', 'hint'], array_keys($ids));
    }

    # An id that already exists passes through untouched
    #[Test]
    public function testExistingIdPassesThroughUntouched(): void
    {
        foreach (['f-dump-skip', 'f-cap-provider', 'f-field-0', '1', 'form12_text'] as $id) {
            $this->assertSame($id, getFieldIds($id)['input'], 'An id that already exists was rewritten');
        }
    }

    # The caption of a radio group, the name of an editor and the hint of a control all derive from the id, so a wrong id breaks every IDREF quietly
    # An aria-labelledby that resolves to nothing computes an empty name while the attribute stays visibly in place
    #[Test]
    public function testCompanionsAreDerivedFromTheId(): void
    {
        $ids = getFieldIds('f-dump-skip');
        $this->assertSame('f-dump-skip-label', $ids['label']);
        $this->assertSame('f-dump-skip-hint', $ids['hint']);
    }

    #[Test]
    public function testDuplicateIdIsNotDeduplicated(): void
    {
        $this->assertSame(getFieldIds('f-url'), getFieldIds('f-url'), 'A hand-written duplicate was silently made unique');
    }

    # Only a row with no control id of its own is handed a minted one
    #[Test]
    public function testMintIsUsedOnlyWhenThereIsNoId(): void
    {
        $this->assertSame('f-title', getFieldIds('f-title', 'clickable')['input'], 'A seed overrode an id that already exists');
    }

    #[Test]
    public function testMintedIdCarriesTheSeedAndIsUnique(): void
    {
        $one = getFieldIds('', 'clickable')['input'];
        $two = getFieldIds('', 'clickable')['input'];
        $this->assertStringStartsWith('f-clickable-', $one);
        $this->assertNotSame($one, $two, 'Two rows of the same shape were handed the same minted id');
    }

    #[Test]
    public function testMintedCompanionsFollowTheMintedId(): void
    {
        $ids = getFieldIds('', 'cache_l');
        $this->assertStringStartsWith('f-cache-l-', $ids['input'], 'An underscore reached the id instead of the hyphen this tree writes');
        $this->assertSame($ids['input'].'-label', $ids['label']);
        $this->assertSame($ids['input'].'-hint', $ids['hint']);
    }

    #[Test]
    public function testMintFallsBackWhenNoSeedIsNamed(): void
    {
        $this->assertMatchesRegularExpression('#^f-field-\d+$#', getFieldIds('')['input']);
    }

    #[Test]
    public function testEveryMintedIdIsAValidHtmlName(): void
    {
        foreach (['clickable', 'cache_l', 'field[]', 'asum[]', 'Mail Verify', ''] as $mint) {
            $id = getFieldIds('', $mint)['input'];
            $this->assertMatchesRegularExpression('#^[a-z][a-z0-9-]*$#', $id, 'A minted id is not reachable by a CSS selector: '.$id);
        }
    }
}
