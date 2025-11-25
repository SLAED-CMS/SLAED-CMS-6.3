<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confse = array();

# Разделитель заголовков old $conf['defis']
$confse['tsep'] = "-";

# Ключевые слова по умолчанию
$confse['keys'] = "cms,php,html5,css3,jquery,ajax,mysql,web,сайт,бесплатно,просто,быстро,функционально,эффективно,безопасно";

# Исключить следующие слова из ключевых
$confse['dkeys'] = "quot,amp,вам,вас,все,всех,где,даже,для,его,ей,если,есть,еще,её,и,или,их,как,когда,кого,кто,лишь,мне,мы,на,надо,нас,нет,но,он,она,они,оно,при,раз,так,уже,чем,что,эта,этих,это";

# Количество ключвых слов
$confse['kwords'] = "15";

# Количество знаков в ключевом слове
$confse['kletter'] = "3";

# Количество знаков в описании
$confse['dletter'] = "160";

# Автоматическая генерация ключевых слов
$confse['akeys'] = "1";

# Перемешивать ключевые слова
$confse['kmix'] = "0";

# Разделять ключевые слова через запятую
$confse['ksep'] = "0";

# Длинные заголовки
$confse['ltitle'] = "1";

# Автоматическая генерация описания
$confse['adesc'] = "1";

#Активировать ЧПУ
$confse['rewrite'] = "1";

# Разделитель ЧПУ
$confse['sep'] = "-";





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
		"https://www.facebook.com/slaedsystem",
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

### На удаление!
# $conf['keywords']
# $conf['keywords_s']
# $conf['kletter']
# $conf['kwords']
# $conf['key_shuffle']
###
?>