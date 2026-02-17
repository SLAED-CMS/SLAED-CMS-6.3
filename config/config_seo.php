<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confse = array();

#Активировать ЧПУ // true = SEO-Link, false = klassischer Link
# DELETE
# $confse['rewrite'] = false;

# Разделитель ЧПУ // Separator for base segments (name, op, id)
# DELETE
# $confse['sep'] = "/";

# Разделитель заголовков old $conf['defis'] // Separator only for Title / CTitle
# DELETE
# $confse['tsep'] = "";

# insert title or not
# DELETE
# $confse['title'] = true;

# insert ctitle or not
# DELETE
# $confse['ctitle'] = true;

# Длинные заголовки
# DELETE
# $confse['ltitle'] = "1";

# Автоматическая генерация описания
# DELETE
# $confse['adesc'] = "1";

# Количество знаков в описании
# DELETE
# $confse['dletter'] = "160";

# Активировать Open Graph
$confse['agraph'] = "1";

# Open Graph
$confse['graph'] = <<<HTML
<meta property="og:site_name" content="[site]">
<meta property="og:locale" content="[loc]">
<meta property="og:title" content="[title]">
<meta property="og:description" content="[desc]">
<meta property="og:image" content="[img]">
<meta property="og:type" content="[type]">
<meta property="og:url" content="[url]">
HTML;

# Активировать Schema
$confse['aschema'] = "1";

# Schema
$confse['schema'] = <<<HTML
<script type="application/ld+json">
{
	"@context": "http://schema.org",
	"@type": "Organization",
	"name": "[site]",
	"url": "[homeurl]",
	"image": "[logo]",
	"sameAs": [
		"https://vk.com/slaed_cms",
		"https://www.facebook.com/SLAED-CMS-577310846059054",
		"https://twitter.com/slaed_cms",
		"https://plus.google.com/112343714768886483056"
	]
}
</script>
<script type="application/ld+json">
{
	"@context": "http://schema.org",
	"@type": "Article",
	"name": "[title]",
	"description": "[desc]",
	"articleSection": "[ctitle]",
	"datePublished": "[time]",
	"dateModified": "[mtime]",
	"image": "[img]",
	"url": "[url]",
	"headline": "0",
	"author": {
		"@type": "Person",
		"name": "[site]"
	},
	"publisher": {
		"@type": "Organization",
		"name": "[site]",
		"url": "[homeurl]",
		"logo": {
			"@type": "ImageObject",
			"name": "[site]",
			"url": "[logo]"
		}
	},
	"mainEntityOfPage": {
		"@type": "WebPage",
		"name": "[site]",
		"url": "[homeurl]"
	}
}
</script>
HTML;

?>