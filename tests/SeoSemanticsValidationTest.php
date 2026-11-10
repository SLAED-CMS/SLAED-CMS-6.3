<?php

use PHPUnit\Framework\TestCase;

final class SeoSemanticsValidationTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $this->base = dirname(__DIR__);
    }

    public function testHeadUsesContextSafeSerializationAndNoLegacyMeta(): void
    {
        $source = file_get_contents($this->base.'/core/system.php');
        $this->assertStringContainsString("getHtmlFrag('head-title'", $source);
        $this->assertStringContainsString("'is_property' => true", $source);
        $this->assertStringContainsString("'is_jsonld' => true", $source);
        $this->assertStringContainsString('JSON_HEX_TAG', $source);
        $this->assertStringNotContainsString('<meta name="revisit-after"', $source);
        $this->assertStringNotContainsString('<meta name="rating"', $source);
        $this->assertStringNotContainsString('<meta name="generator"', $source);
    }

    public function testHeadFragmentsEscapeTextAndAttributeContexts(): void
    {
        if (!class_exists('Template', false)) require_once $this->base.'/core/classes/template.php';
        $tpl = new Template('aurora');
        $payload = '"><script>alert(1)</script>';
        $title = $tpl->getHtmlFrag('head-title', ['title' => $payload]);
        $meta = $tpl->getHtmlFrag('head-meta', ['name' => 'description', 'content' => $payload]);
        $prop = $tpl->getHtmlFrag('head-meta', ['is_property' => true, 'property' => 'og:title', 'content' => $payload]);
        $json = $tpl->getHtmlFrag('head-script-inline', ['is_jsonld' => true, 'json_html' => '{"@type":"WebPage"}']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $title.$meta);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $title);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $meta);
        $this->assertStringContainsString('property="og:title"', $prop);
        $this->assertStringNotContainsString('name=""', $prop);
        $this->assertSame('<script type="application/ld+json">{"@type":"WebPage"}</script>', trim($json));
    }

    public function testDefaultSchemaDoesNotDeclareEveryPageAsArticle(): void
    {
        $config = file_get_contents($this->base.'/config/global.php');
        $this->assertStringContainsString('"@type": "Organization"', $config);
        $this->assertStringNotContainsString('"@type": "Article"', $config);
        $this->assertStringNotContainsString('plus.google.com', $config);
    }

    public function testSemanticComponentsRequireExplicitBooleanContexts(): void
    {
        $card = file_get_contents($this->base.'/public/templates/aurora/fragments/node/card.html');
        $vote = file_get_contents($this->base.'/public/templates/aurora/partials/voting-widget.html');
        $pop = file_get_contents($this->base.'/public/templates/aurora/fragments/popover.html');
        $layout = file_get_contents($this->base.'/public/templates/aurora/layouts/app.html');

        $this->assertSame(1, substr_count($card, '<h2 class="sl-title">'));
        $this->assertStringNotContainsString('<h1', $card);
        $this->assertStringNotContainsString('<h3', $card);
        $this->assertStringNotContainsString('card-title.html', $card);
        $this->assertMatchesRegularExpression('#\{% if is_page %\}\s*<h1#', $vote);
        $this->assertMatchesRegularExpression('#\{% elseif is_section %\}\s*<h2#', $vote);
        $this->assertMatchesRegularExpression('#\{% elseif is_widget %\}\s*<h3#', $vote);
        $this->assertStringNotContainsString('<nav', $pop);
        $this->assertSame(1, substr_count($layout, '<main'));
    }

    public function testCardHeadingRendersWithoutDedicatedTitleFragment(): void
    {
        if (!class_exists('Template', false)) require_once $this->base.'/core/classes/template.php';
        $tpl = new Template('aurora');
        $card = $tpl->getHtmlFrag('node/card', ['id' => '1', 'href' => '/item', 'title' => 'Card']);
        $this->assertStringContainsString('<h2 class="sl-title">', $card);
        $this->assertSame(1, substr_count($card, '>Card</a>'));
    }

    public function testLocaleIdentifiersUseRealLanguageRegionPairs(): void
    {
        $en = file_get_contents($this->base.'/lang/en.php');
        $uk = file_get_contents($this->base.'/lang/uk.php');
        $this->assertStringContainsString("define('_LOCALE','en_GB')", $en);
        $this->assertStringContainsString("define('_LOCALE','uk_UA')", $uk);
    }
}
