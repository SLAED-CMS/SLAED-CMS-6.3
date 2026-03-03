<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Safety net tests for setTemplateIf() conditional logic.
 * Captures behavior before eval() removal in Phase 1.3.
 */
final class TemplateIfTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('setTemplateIf')) {
            require_once BASE_DIR.'/core/template.php';
        }
    }

    #[Test]
    public function ifTrueShowsContent(): void
    {
        $html = 'before{% if logged %}Hello User{% endif %}after';
        $result = setTemplateIf($html, ['logged' => true]);

        $this->assertSame('beforeHello Userafter', $result);
    }

    #[Test]
    public function ifFalseHidesContent(): void
    {
        $html = 'before{% if logged %}Hello User{% endif %}after';
        $result = setTemplateIf($html, ['logged' => false]);

        $this->assertSame('beforeafter', $result);
    }

    #[Test]
    public function ifElseTrueBranch(): void
    {
        $html = '{% if logged %}Welcome{% else %}Please login{% endif %}';
        $result = setTemplateIf($html, ['logged' => true]);

        $this->assertSame('Welcome', $result);
    }

    #[Test]
    public function ifElseFalseBranch(): void
    {
        $html = '{% if logged %}Welcome{% else %}Please login{% endif %}';
        $result = setTemplateIf($html, ['logged' => false]);

        $this->assertSame('Please login', $result);
    }

    #[Test]
    public function nestedIfConditions(): void
    {
        $html = '{% if a %}A{% if b %}B{% endif %}{% endif %}';
        $result = setTemplateIf($html, ['a' => true, 'b' => true]);
        $this->assertSame('AB', $result);

        $result = setTemplateIf($html, ['a' => true, 'b' => false]);
        $this->assertSame('A', $result);

        $result = setTemplateIf($html, ['a' => false, 'b' => true]);
        $this->assertSame('', $result);
    }

    #[Test]
    public function stringTrueIsTruthy(): void
    {
        $html = '{% if flag %}yes{% endif %}';
        $result = setTemplateIf($html, ['flag' => 'true']);

        $this->assertSame('yes', $result);
    }

    #[Test]
    public function stringFalseIsFalsy(): void
    {
        $html = '{% if flag %}yes{% endif %}';
        $result = setTemplateIf($html, ['flag' => 'false']);

        $this->assertSame('', $result);
    }

    #[Test]
    public function undefinedFlagIsFalsy(): void
    {
        $html = '{% if undefined_flag %}yes{% else %}no{% endif %}';
        $result = setTemplateIf($html, []);

        $this->assertSame('no', $result);
    }

    #[Test]
    public function noConditionsReturnsOriginal(): void
    {
        $html = 'plain text without conditions';
        $result = setTemplateIf($html, ['flag' => true]);

        $this->assertSame($html, $result);
    }

    #[Test]
    public function emptyStringReturnsEmpty(): void
    {
        $result = setTemplateIf('', ['flag' => true]);
        $this->assertSame('', $result);
    }

    #[Test]
    public function multipleIndependentIfs(): void
    {
        $html = '{% if a %}A{% endif %}-{% if b %}B{% endif %}';
        $result = setTemplateIf($html, ['a' => true, 'b' => false]);

        $this->assertSame('A-', $result);
    }

    #[Test]
    public function ifWithSpacesInTag(): void
    {
        $html = '{%  if  flag  %}yes{%  endif  %}';
        $result = setTemplateIf($html, ['flag' => true]);

        $this->assertSame('yes', $result);
    }
}
