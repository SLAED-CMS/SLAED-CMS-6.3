<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Batch 1 of docs/FORM-FIELDS-2026.md. getFieldIds() is the one owner of the three ids a form row
 * needs, and everything the batches above it wire - the caption a radio group points at, the name an
 * editor carries, the hint a control describes - is derived from what it answers. If it is wrong,
 * every IDREF downstream is wrong quietly: an aria-labelledby that resolves to nothing computes an
 * empty name with the attribute visibly in place. So it is held here on its own, before a call site
 * uses it: an id that already exists passes through untouched, the companions come from it, and only
 * a row with no control id of its own is handed a minted one.
 */
final class FieldIdsTest extends TestCase
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

    #[Test]
    public function testExistingIdPassesThroughUntouched(): void
    {
        foreach (['f-dump-skip', 'f-cap-provider', 'f-field-0', '1', 'form12_text'] as $id) {
            $this->assertSame($id, getFieldIds($id)['input'], 'An id that already exists was rewritten');
        }
    }

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
