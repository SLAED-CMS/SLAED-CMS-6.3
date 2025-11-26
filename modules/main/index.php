<?php
# Author: Eduard Laas
# Copyright © 2005 - 2017 SLAED
# License: GNU GPL 3
# Website: http://www.slaed.net

if (!defined("MODULE_FILE")) {
	header("Location: ../../index.php");
	exit;
}

function main() {
	global $prefix, $db, $conf;
	head($conf['defis']." Простота Функциональность Эффективность Безопасность");
	$cont = '<div class="slider-head">
		<ul id="slider-head">
			<li>
				<div class="slide-cont">
					<h1 class="font slide-title">SLAED CMS</h1>
					<p style="text-align: justify;">Это система управления содержимым сайта, простая в использовании и настройке, имеющая при этом высокий уровень безопасности, высокую скорость работы, а также практически неограниченный потенциал в решении вопроса расширения функциональности. На основе SLAED любой желающий, даже, не обладающий большими знаниями, может построить себе не только качественный сайт, но и мощный портал.</p>
					<a class="sl_but_blue" href="index.php?name=faq&amp;cat=38#18" title="Система управления содержимым сайта SLAED CMS"><b>Подробнее</b></a>
				</div>
				<div class="slide-img"><span class="slide-season"></span></div>
			</li>
			<li>
				<div class="slide-cont">
					<h2 class="font slide-title">Простота</h2>
					<p style="text-align: justify;">Интуитивно понятный интерфейс, позволяет управлять сайтом так же просто, как работать с документами или почтой. При наличие хотя бы небольшого опыта и знания HTML, сможете существенно изменить не только внешний вид, но и саму структуру сайта. Модульное наращивание позволяет инсталлировать на ваш сайт разного рода модули, блоки, темы оформления и разного рода дополнения.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=14" title="Управлять сайтом также просто, как работать с документами на компьютере" class="sl_but_blue"><b>Подробнее</b></a>
				</div>
				<div class="slide-img"><img src="templates/lite/images/slide/slaed_1.jpg" alt="Управлять сайтом также просто, как работать с документами на компьютере"></div>
			</li>
			<li>
				<div class="slide-cont">
					<h2 class="font slide-title">Функциональность</h2>
					<p style="text-align: justify;">К дополнению и без того широкого функционала системы, существует масса дополнительных модулей, блоков, расширений и тем оформления, которые дополнят систему практически любым функционалом. Файловый архив проекта содержит более 685 дополнений от сторонних разработчиков. Система управления поставляется с открытым исходным кодом, что даёт неограниченный возможности модификации опытным пользователям.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=15" title="Скорей всего, у нас предусмотрены функции, которые нужны Вам" class="sl_but_blue"><b>Подробнее</b></a>
				</div>
				<div class="slide-img"><img src="templates/lite/images/slide/slaed_2.jpg" alt="Скорей всего, у нас предусмотрены функции, которые нужны Вам"></div>
			</li>
			<li>
				<div class="slide-cont">
					<h2 class="font slide-title">Эффективность</h2>
					<p style="text-align: justify;">Система существенно отличается от аналогов, низкой потребляемостью серверных ресурсов, что позволяет получить высокую скорость работы системы. Техническая поддержка клиентов и встроенная система «Документация/Справка» по использованию и применению тех или иных модулей непосредственно в панели управления системой, помогут найти ответ на любой возникший вопрос.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=16" title="Мы действительно уделяем ей большое внимание" class="sl_but_blue"><b>Подробнее</b></a>
				</div>
				<div class="slide-img"><img src="templates/lite/images/slide/slaed_3.jpg" alt="Мы действительно уделяем ей большое внимание"></div>
			</li>
			<li>
				<div class="slide-cont">
					<h2 class="font slide-title">Безопасность</h2>
					<p style="text-align: justify;">Мощная система безопасности, имеющая гибкие настройки, позволяет логирование всех действий посетителей сайта. Существуют возможности автоматического отслеживания вредоносных запросов, инъекций, попыток внедрений и прочих несанкционированных действий. В любом случае система предупреждений и отражений надёжно предупредит и защитит сайт от всех внешних угроз.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=17" title="Создавайте сайты, надёжно закрытые от злоумышленников" class="sl_but_blue"><b>Подробнее</b></a>
				</div>
				<div class="slide-img"><img src="templates/lite/images/slide/slaed_4.jpg" alt="Создавайте сайты, надёжно закрытые от злоумышленников"></div>
			</li>
		</ul>
	</div>
	<div class="advantage">
		<div class="wrp clrfix">
			<ul class="big-icons">
				<li>
					<b class="b_icon b_i_1"></b>
					<h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=14" title="Управлять сайтом также просто, как работать с документами на компьютере">Простота</a></h4>
					<p>Интуитивно понятный интерфейс, простое управление сайтом, лёгкая модификация.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=14" title="Управлять сайтом также просто, как работать с документами на компьютере" class="sl_but_read">Узнать больше</a>
				</li>
				<li>
					<b class="b_icon b_i_2"></b>
					<h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=15" title="Скорей всего, у нас предусмотрены функции, которые нужны Вам">Функциональность</a></h4>
					<p>Неограниченные возможности, масса дополнений, расширение любыми функциями.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=15" title="Скорей всего, у нас предусмотрены функции, которые нужны Вам" class="sl_but_read">Узнать больше</a>
				</li>
				<li>
					<b class="b_icon b_i_3"></b>
					<h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=16" title="Мы действительно уделяем ей большое внимание">Эффективность</a></h4>
					<p>Низкое потребление серверных ресурсов, высокая скорость работы системы.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=16" title="Мы действительно уделяем ей большое внимание" class="sl_but_read">Узнать больше</a>
				</li>
				<li>
					<b class="b_icon b_i_4"></b>
					<h4 class="font"><a href="index.php?name=content&amp;op=view&amp;id=17" title="Создавайте сайты, надёжно закрытые от злоумышленников">Безопасность</a></h4>
					<p>Мощная система безопасности, гибкие настройки, логирование всех действий.</p>
					<a href="index.php?name=content&amp;op=view&amp;id=17" title="Создавайте сайты, надёжно закрытые от злоумышленников" class="sl_but_read">Узнать больше</a>
				</li>
			</ul>
		</div>
	</div>';
	$cont .= '<div id="site-carousel">
		<div class="wrp"><h3 class="font heading-1">Примеры внедрений системы</h3></div>
		<div class="carousel">
			<ul id="slaed_sites">';
			$path = "uploads/screens";
			$path2 = "uploads/screens/thumb";
			$dir = opendir($path2);
			while (false !== ($file = readdir($dir))) {
				if ($file != "." && $file != ".." && $file != "index.html" && !is_dir($path2."/".$file)) $screens[] = $file;
			}
			closedir($dir);
			shuffle($screens);
			foreach ($screens as $val) {
				$sname = ucfirst(str_ireplace(array(".gif", ".jpg", ".png", ".com", ".net", ".ru", ".ua", ".biz", ".info", ".su", ".in", ".org"), "", $val));
				$srat = round(filesize($path2.'/'.$val) / 20);
				$cont .= '<li>
					<div class="site-item">
						<a href="'.$path.'/'.$val.'" rel="alternate" title="'._SITE.': '.$sname.', '._RATING.': '.$srat.'" class="site-link">
							<div class="site-img ico"><img src="'.$path2.'/'.$val.'" alt="Сайт: '.$sname.'" title="Сайт: '.$sname.'"></div>
							<p class="site-title">'._SITE.': '.$sname.'</p>
						</a>
						<b class="ico rate_sites" title="'._RATING.'">'.$srat.'</b>
					</div>
				</li>';
			}
			$cont .= '</ul>
			<span id="carousel-left"></span>
			<span id="carousel-right"></span>
		</div>
	</div>';
	$cont .= '<div class="wrp grid_1_2">
		<div class="grid col-block">
			<h3 title="'._NEWS.'" class="font heading-1">Новости</h3>
			<ul class="ms-list">';
			$result = $db->sql_query("SELECT s.sid, s.catid, s.title, s.time, s.hometext, c.title, c.description FROM ".$prefix."_news AS s LEFT JOIN ".$prefix."_categories AS c ON (s.catid=c.id) WHERE time <= now() AND status!='0' ORDER BY time DESC LIMIT 3");
			while(list($sid, $catid, $title, $time, $hometext, $ctitle, $cdesc) = $db->sql_fetchrow($result)) {
				$linkstrip = cutstr($title, 45);
				if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
					$img = "uploads/news/thumb/".trim($match[1]);
				} else {
					preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
					$img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : "");
				}
				$img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
				$ntext = cutstr(htmlspecialchars(trim(strip_tags(bb_decode($hometext, "news"))), ENT_QUOTES), 60);
				$cont .= '<li><a href="index.php?name=news&amp;op=view&amp;id='.$sid.'" title="'.$title.'" class="ms-img" style="background-image: url('.$img.');"></a>
					<b><a href="index.php?name=news&amp;op=view&amp;id='.$sid.'" title="'.$title.'">'.$linkstrip.'</a></b>
					<p>'.$ntext .'</p>
					<ul class="grey">
						<li><a href="index.php?name=news&amp;cat='.$catid.'" title="'.$cdesc.'" class="sl_cat"><b>'.$ctitle.'</b></a></li>
						<li title="'._DATE.'" class="ico i_date">'.format_time($time, _TIMESTRING).'</li>
					</ul>
				</li>';
			}
			$cont .= '</ul>
			<a href="index.php?name=news" title="Все новости" class="sl_but_read">Все новости</a>
		</div>
		<div class="grid col-block">
			<h3 title="'._FILES.'" class="font heading-1">Файлы</h3>
			<ul class="ms-list">';
			$result = $db->sql_query("SELECT s.lid, s.cid, s.title, s.description, s.date, c.title, c.description FROM ".$prefix."_files AS s LEFT JOIN ".$prefix."_categories AS c ON (s.cid=c.id) WHERE date <= now() AND status!='0' ORDER BY date DESC LIMIT 3");
			while(list($sid, $catid, $title, $hometext, $time, $ctitle, $cdesc) = $db->sql_fetchrow($result)) {
				$linkstrip = cutstr($title, 45);
				if (preg_match("#\[attach=(.*?)\s(.*?)\]#si", $hometext, $match)) {
					$img = "uploads/files/thumb/".trim($match[1]);
				} else {
					preg_match("#\[img=(.*?)\](.*)\[/img\]#si", $hometext, $match);
					$img = isset($match[2]) ? trim($match[2]) : (isset($match[1]) ? trim($match[1]) : "");
				}
				$img = ($img) ? (file_exists($img) ? $img : img_find('logos/slaed_logo_60x60.png')) : img_find('logos/slaed_logo_60x60.png');
				$ntext = cutstr(htmlspecialchars(trim(strip_tags(bb_decode($hometext, "files"))), ENT_QUOTES), 60);
				$cont .= '<li><a href="index.php?name=files&amp;op=view&amp;id='.$sid.'" title="'.$title.'" class="ms-img" style="background-image: url('.$img.');"></a>
					<b><a href="index.php?name=files&amp;op=view&amp;id='.$sid.'" title="'.$title.'">'.$linkstrip.'</a></b>
					<p>'.$ntext .'</p>
					<ul class="grey">
						<li><a href="index.php?name=files&amp;cat='.$catid.'" title="'.$cdesc.'" class="sl_cat"><b>'.$ctitle.'</b></a></li>
						<li title="'._DATE.'" class="ico i_date">'.format_time($time, _TIMESTRING).'</li>
					</ul>
				</li>';
			}
			$cont .= '</ul>
			<a href="index.php?name=files" title="Все файлы" class="sl_but_read">Все файлы</a>
		</div>
	</div>';
	echo $cont;
	foot();
}

switch($op) {
	default:
	main();
	break;
}
?>