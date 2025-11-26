<?php
# Author: Eduard Laas
# Copyright © 2005 - 2026 SLAED
# License: GNU GPL 3
# Website: slaed.net

if (!defined('FUNC_FILE')) die('Illegal file access');

function setTemplateSeason() {
	# <body> - Обычное оформление
	# <body class="winter"> - Зимнее оформление
	# <body class="autumn"> - Осенеее оформление
	# <body class="summer"> - Летнее оформление
	# <body class="spring"> - Весеннее оформление
	# <body class="newyear"> - Новогоднее оформление
	$zdate = date('z');
	if ($zdate > 355 || $zdate < 5) {
		$cont = 'newyear';
	} else {
		$seas = array(0 => 'winter', 1 => 'spring', 2 => 'summer', 3 => 'autumn');
		$cont = $seas[floor(date('n') / 3) % 4];
	}
	return $cont;
}

function setTemplateMenu() {
	$cont = '<nav id="topmenu">
				<ul>
					<li><a href="/" class="ico i_home" title="'._HOME.'"><span class="thide">'._HOME.'</span></a></li>
					<li>
					<a href="#" title="Продукты">Продукты</a>
						<ul>
							<li><a href="index.php?name=shop" title="Все продукты проекта">Все продукты проекта</a></li>
							<li><a href="index.php?name=shop&amp;op=view&amp;id=6" title="Новая установка системы на хостинг">Новая установка системы на хостинг</a></li>
							<li><a href="index.php?name=shop&amp;op=view&amp;id=24" title="Обновление системы до актуальной версии">Обновление системы до актуальной версии</a></li>
							<li><a href="index.php?name=shop&amp;op=view&amp;id=27" title="Регистрация и продление доменов">Регистрация и продление доменов</a></li>
							<li><a href="index.php?name=shop&amp;op=view&amp;id=25" title="Виртуальный хостинг - Тарифный план Pro">Виртуальный хостинг - Тарифный план Pro</a></li>
							<li><a href="index.php?name=shop&amp;op=view&amp;id=26" title="Виртуальный хостинг - Тарифный план Deluxe">Виртуальный хостинг - Тарифный план Deluxe</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=13" title="Правила и условия использования хостинга">Правила и условия использования хостинга</a></li>
						</ul>
					</li>
					<li>
					<a href="#" title="Услуги">Услуги</a>
						<ul>
							<li><a href="index.php?name=content&amp;op=view&amp;id=11" title="Услуги по изготовлению, модификации и оптимизации">Создание и модификация</a></li>
							<li><a href="index.php?name=money" title="Банковский перевод денежных средств в WebMoney из Германии">Обмен WebMoney</a></li>
							<li><a href="http://www.slaed.in" target="_blank" title="Модификация и интеграция тем оформления">Темы и шаблоны</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=1" title="Размещение рекламы на проекте">Реклама на проекте</a></li>
							<li><a href="index.php?name=whois" title="Проверка лицензии и доменного имени">Проверка домена</a></li>
						</ul>
					</li>
					<li>
						<a href="#" title="Партнерам">Партнерам</a>
						<ul>
							<li><a href="index.php?name=content&amp;op=view&amp;id=24" title="Информация для будущих партнеров">Партнерская программа</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=10" title="Информация о получении сертификата">Получить сертификат</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=25" title="Рекламные материалы">Рекламные материалы</a></li>
						</ul>
					</li>
					<li>
					<a href="#" title="Поддержка">Поддержка</a>
						<ul>
							<li><a href="index.php?name=faq" title="Вопросы и ответы">Вопросы и ответы</a></li>
							<li><a href="index.php?name=pages" title="Центр документации">Центр документации</a></li>
							<li><a href="index.php?name=forum" title="Общий форум проекта">Общий форум проекта</a></li>
							<li><a href="http://www.slaed.info" target="_blank" title="Руководство по PHP">Руководство по PHP</a></li>
							<li><a href="index.php?name=help" title="Техническая поддержка клиентов">Техническая поддержка</a></li>
							<li><a href="index.php?name=contact" title="Обратная связь и контактная информация">Обратная связь</a></li>
						</ul>
					</li>
					<li>
					<a href="#" title="Каталог файлов">Каталог файлов</a>
						<ul>
							<li><a href="index.php?name=files" title="Каталог файлов">Каталог файлов</a></li>
							<li><a href="index.php?name=files&amp;cat=4" title="Дополнительные модули">Модули системы</a></li>
							<li><a href="index.php?name=files&amp;cat=5" title="Дополнительные блоки">Блоки системы</a></li>
							<li><a href="index.php?name=files&amp;cat=6" title="Темы оформления и графические элементы">Темы оформления</a></li>
							<li><a href="index.php?name=files&amp;cat=12" title="Файлы и скрипты для системы">Файлы и скрипты</a></li>
							<li><a href="index.php?name=files&amp;cat=10" title="Языковые файлы для системы">Языковые файлы</a></li>
							<li><a href="index.php?name=files&amp;cat=9" title="Документация, учебники и инструкции">Учебники и инструкции</a></li>
							<li><a href="index.php?name=files&amp;cat=8" title="Полезные скрипты для вебмастера">Полезные скрипты</a></li>
							<li><a href="index.php?name=files&amp;cat=7" title="Полезные программы для вебмастера">Полезные программы</a></li>
						</ul>
					</li>
					<li>
						<a href="#" title="Новости">Новости</a>
						<ul>
							<li><a href="index.php?name=news" title="Все новости проекта">Все новости</a></li>
							<li><a href="index.php?name=news&amp;cat=2" title="Новости проекта">Наши новости</a></li>
							<li><a href="index.php?name=news&amp;cat=1" title="Мир интернета">Интернет</a></li>
							<li><a href="index.php?name=news&amp;cat=3" title="Компьютерный мир">Программы</a></li>
						</ul>
					</li>
					<li>
						<a href="#" title="Компания">Компания</a>
						<ul>
							<li><a href="index.php?name=contact" title="Контактная информация">Контактные данные</a></li>
							<li><a href="index.php?name=main" title="Презентационная страница системы">Презентационная страница</a></li>
							<li><a href="index.php?name=faq&amp;op=view&amp;id=20" title="История развития системы">История развития системы</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=8" title="Официальная регистрация фирмы SLAED в Германии">Официальная регистрация</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=7" title="Официальный патент на бренд SLAED в Германии">Официальный патент</a></li>
							<li><a href="index.php?name=recommend" title="Рекомендовать наш сайт">Рекомендовать наш сайт</a></li>
							<li><a href="index.php?name=links" title="Каталог сайтов системы">Каталог сайтов системы</a></li>
							<li><a href="index.php?name=users" title="Tоп пользователи проекта">Tоп пользователи проекта</a></li>
							<li><a href="index.php?name=voting" title="Опросы нашего проекта">Опросы нашего проекта</a></li>
							<li><a href="index.php?name=content&amp;op=view&amp;id=2" title="Общие правила проекта">Общие правила проекта</a></li>
						</ul>
					</li>
				</ul>
			</nav>';
	return $cont;
}

function setTemplateForum() {
	global $prefix, $db;
	$blimit = "3";
	$bclos = "97, 98, 99, 100, 101";
	$bwhere = ($bclos) ? "catid NOT IN (".$bclos.") AND" : "";
	$ordern = (is_moder("forum")) ? "" : "AND time <= now() AND status > '1'";
	$buffer = "";
	$result = $db->sql_query("SELECT id, title, l_time, l_uid, l_name, l_id, l_time, status FROM ".$prefix."_forum WHERE ".$bwhere." pid = '0' ".$ordern." ORDER BY l_time DESC LIMIT 0, ".$blimit);
	while (list($id, $title, $time, $l_uid, $l_name, $l_id, $l_time, $status) = $db->sql_fetchrow($result)) {
		$lposter = ($l_uid) ? user_info($l_name) : $l_name;
		$class = ($status <= 1 || $time > date("Y-m-d H:i:s")) ? " class=\"sl_hidden\"" : "";
		$buffer .= "<li".$class."><a href=\"index.php?name=forum&amp;op=view&amp;id=".$id."&amp;last#".$l_id."\" title=\"".$title."\">".cutstr($title, 50)."</a><ul><li title=\""._POSTEDBY."\" class=\"sl_post\">".$lposter."</li><li title=\""._DATE.": ".format_time($l_time, _TIMESTRING)."\" class=\"ico i_date\">".format_time($l_time)."</li></ul></li>";
	}
	$cont = "<div class=\"grid\"><p title=\""._FORUM."\" class=\"font f_title\">"._FORUM."</p><ul class=\"list-item\">".$buffer."</ul></div>";
	return $cont;
}

function setTemplateHead($sub, $val = '') {
	global $theme, $user, $conf, $confu, $conff, $prefix, $db;
	if (is_user()) {
		$uname = htmlspecialchars(substr($user[1], 0, 25));
		$userinfo = getusrinfo();
		$user_avatar = (file_exists($confu['adirectory'].'/'.$userinfo['user_avatar'])) ? $userinfo['user_avatar'] : 'default/00.gif';
		$cont = tpl_eval('login-logged', _ACCOUNT, $confu['adirectory'].'/'.$user_avatar, $uname, _LOGOUT);
	} else {
		if ($confu['enter'] == 1) {
			$captcha = ($conf['gfx_chk'] == 2 || $conf['gfx_chk'] == 4 || $conf['gfx_chk'] == 5 || $conf['gfx_chk'] == 7) ? getCaptcha(2) : '';
			$cont = tpl_eval('login', _LOGIN, _NICKNAME, _PASSWORD, $captcha, _LOGIN, _PASSFOR, _REG);
		} else {
			$cont = tpl_eval('login-without', _BREG);
		}
	}
	$mname = ($conf['name']) ? deflmconst($conf['name']) : '';
	$fcat = (isset($_GET['cat'])) ? intval($_GET['cat']) : 0;
	$cname = ($fcat) ? catlink($conf['name'], $fcat, $conff['defis'], $mname) : '';
	list($count) = $db->sql_fetchrow($db->sql_query("SELECT Count(fid) FROM ".$prefix."_faq WHERE time <= now() AND status != '0'"));
	$random = mt_rand(0, $count);
	$result = $db->sql_query("SELECT fid, title FROM ".$prefix."_faq ORDER BY fid DESC LIMIT ".$random.", 1");
	list($fid, $title) = $db->sql_fetchrow($result);
	$faq = '<a class="ico i_fav" href="index.php?name=faq&amp;op=view&amp;id='.$fid.'" title="'.$title.'">'.$title.'</a>';
	$value = array('{%login%}' => $cont, '{%theme%}' => $theme, '{%lang%}' => substr(_LOCALE, 0, 2), '{%sitename%}' => $conf['sitename'], '{%logo%}' => $conf['site_logo'], '{%homeurl%}' => $conf['homeurl'], '{%slogan%}' => $conf['slogan'], '{%home%}' => _HOME, '{%account%}' => _ACCOUNT, '{%album%}' => _ALBUM, '{%alinks%}' => _A_LINKS, '{%feedback%}' => _FEEDBACK, '{%content%}' => _CONTENT, '{%faq%}' => _FAQ, '{%files%}' => _FILES, '{%forum%}' => _FORUM, '{%help%}' => _HELP, '{%radio%}' => _RADIO, '{%jokes%}' => _JOKES, '{%links%}' => _LINKS, '{%media%}' => _MEDIA, '{%users%}' => _USERS, '{%news%}' => _NEWS, '{%order%}' => _ORDER, '{%pages%}' => _PAGES, '{%recommend%}' => _RECOMMEND, '{%rss%}' => _RSS, '{%search%}' => _SEARCH, '{%shop%}' => _SHOP, '{%topusers%}' => _TOPUSERS, '{%voting%}' => _VOTING, '{%favorites%}' => _S_FAVORITEN, '{%homepage%}' => _S_STARTSEITE, '{%season%}' => setTemplateSeason(), '{%modul%}' => $conf['name'], '{%menu%}' => setTemplateMenu(), '{%modulname%}' => $mname, '{%catname%}' => $cname, '{%faqtitle%}' => $faq);
	$value = is_array($val) ? array_merge($value, $val) : $value;
	return str_replace(array_keys($value), array_values($value), $sub);
}

function setTemplateFoot($sub, $val = '') {
	global $theme, $user, $conf, $confu;
	$cont = '';
	$contactblock = '<div id="block-feedback" class="dropdown">
		<a OnClick="HideShow(\'f-form\', \'slide\', \'right\', 500);" title="'._FEEDBACK.'" class="btn-feedback"><b class="font">'._FEEDBACK.'</b></a>
		<form id="f-form" class="dropdown-form" action="index.php?name=contact" method="post">
			<ul class="block-feedback-form clrfix">
				<li class="bff-col1">
					<textarea name="message" class="sl_field" rows="5" cols="65" placeholder="'._MESSAGE.'" required></textarea>
				</li>
				<li class="bff-col2">
					<input type="text" name="sname" class="sl_field" placeholder="'._YOURNAME.'" required>
					<input type="email" name="semail" class="sl_field" placeholder="'._YOUREMAIL.'" required>
					'.getCaptcha(1).'
					<input type="hidden" name="op" value="contact">
					<input type="hidden" name="send" value="1">
					<button type="submit" class="sl_but_blue">'._SEND.'</button>
				</li>
			</ul>
		</form>
	</div>';
	$value = array('{%login%}' => $cont, '{%theme%}' => $theme, '{%sitename%}' => $conf['sitename'], '{%logo%}' => $conf['site_logo'], '{%homeurl%}' => $conf['homeurl'], '{%slogan%}' => $conf['slogan'], '{%home%}' => _HOME, '{%account%}' => _ACCOUNT, '{%album%}' => _ALBUM, '{%alinks%}' => _A_LINKS, '{%feedback%}' => _FEEDBACK, '{%content%}' => _CONTENT, '{%faq%}' => _FAQ, '{%files%}' => _FILES, '{%forum%}' => _FORUM, '{%help%}' => _HELP, '{%radio%}' => _RADIO, '{%jokes%}' => _JOKES, '{%links%}' => _LINKS, '{%media%}' => _MEDIA, '{%users%}' => _USERS, '{%news%}' => _NEWS, '{%order%}' => _ORDER, '{%pages%}' => _PAGES, '{%recommend%}' => _RECOMMEND, '{%rss%}' => _RSS, '{%search%}' => _SEARCH, '{%shop%}' => _SHOP, '{%topusers%}' => _TOPUSERS, '{%voting%}' => _VOTING, '{%favorites%}' => _S_FAVORITEN, '{%homepage%}' => _S_STARTSEITE, '{%forumblock%}' => setTemplateForum(), '{%contactblock%}' => $contactblock);
	$value = is_array($val) ? array_merge($value, $val) : $value;
	return str_replace(array_keys($value), array_values($value), $sub);
}
?>