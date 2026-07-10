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
        $tpl = new Template('lite');
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
        $card = file_get_contents($this->base.'/templates/lite/fragments/card.html');
        $vote = file_get_contents($this->base.'/templates/lite/partials/voting-widget.html');
        $pop = file_get_contents($this->base.'/templates/lite/fragments/popover.html');
        $layout = file_get_contents($this->base.'/templates/lite/layouts/app.html');

        $this->assertStringContainsString('{% if is_detail %}<h1', $card);
        $this->assertStringContainsString('{% elseif is_nested %}<h3', $card);
        $this->assertStringContainsString('{% else %}<h2', $card);
        $this->assertStringNotContainsString('card-title.html', $card);
        $this->assertStringContainsString('{% if is_page %}<h1', $vote);
        $this->assertStringContainsString('{% elseif is_section %}<h2', $vote);
        $this->assertStringContainsString('{% elseif is_widget %}<h3', $vote);
        $this->assertStringNotContainsString('<nav', $pop);
        $this->assertSame(1, substr_count($layout, '<main'));
    }

    public function testCardHeadingContextsRenderWithoutDedicatedTitleFragment(): void
    {
        if (!class_exists('Template', false)) require_once $this->base.'/core/classes/template.php';
        $tpl = new Template('lite');
        $data = ['id' => '1', 'title_href' => '/item', 'title_attr' => 'Card', 'title_text' => 'Card'];
        $detail = $tpl->getHtmlFrag('card', $data + ['is_detail' => true]);
        $nested = $tpl->getHtmlFrag('card', $data + ['is_nested' => true]);
        $list = $tpl->getHtmlFrag('card', $data);
        $this->assertStringContainsString('<h1 class="sl-title">', $detail);
        $this->assertStringContainsString('<h3 class="sl-title">', $nested);
        $this->assertStringContainsString('<h2 class="sl-title">', $list);
        $this->assertSame(1, substr_count($detail, '>Card</a>'));
        $this->assertSame(1, substr_count($nested, '>Card</a>'));
        $this->assertSame(1, substr_count($list, '>Card</a>'));
    }

    public function testLocaleIdentifiersUseRealLanguageRegionPairs(): void
    {
        $en = file_get_contents($this->base.'/lang/en.php');
        $uk = file_get_contents($this->base.'/lang/uk.php');
        $this->assertStringContainsString("define('_LOCALE','en_GB')", $en);
        $this->assertStringContainsString("define('_LOCALE','uk_UA')", $uk);
    }
}
