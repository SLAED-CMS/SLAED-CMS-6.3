# План интеграции эталона главной страницы в модуль presentation

Составлен 2026-09-10 от коммита `ba6d2859` на `master`. Эталон — `demo/22-dashboard-layout-etalon.html`
с `demo/assets/twentytwo.css` и `demo/assets/twentytwo.js`; модуль-заготовка — `modules/presentation/index.php`.
Пять батчей, каждый со своими файлами, шагами и критерием приёмки. **Все проверки один раз по окончании
всех батчей**, по ходу только `php -l`, `phpunit --filter` одного набора и `node tools/ui-shots.mjs --after
--only=<page>` одной страницы; `npm run ui:before` один раз до первой правки CSS темы.

Статус: закрыто A, следующий батч B. Двенадцать решений раздела 8 приняты владельцем 2026-09-10 и 2026-09-11 и вшиты в
батчи; заново их не открывать. Запуск — раздел 11: одна команда на сессию, батч сессия берёт из этой строки.
Строку обновляет сессия перед коммитом своего батча (раздел 11, «Команда на коммит»); после E здесь «закрыто A-E, следующего нет».

Номера строк в документе относятся к дереву на `ba6d2859`; после первого батча искать по имени функции,
селектора или атрибута, а не по номеру.

## 0. Что даёт основание

| Наблюдение | Факт на 2026-09-10 |
|---|---|
| Модуль | `modules/presentation/index.php:12-18` — только `setHead()` с русским заголовком и `setFoot()`; данных нет |
| Реестр | `config/modules.php:262-272` — `active 1`, `menu 1`, `side 3` (без боковых блоков), `top 3`, иконка `house-door` |
| Стартовый модуль | `config/local.php:255` и умолчание `config/global.php:71` — `'module' => 'news'`; presentation открывается только как `index.php?name=presentation` |
| Цепочка рендера | `index.php:44-58` (именованный модуль, `$blocks` из `side`) → `setHead()` `core/system.php:1647` → буфер → `setFoot()` `:1977` → `getHtmlPage($page, …, 'home'|'app')` `:2036-2037` → `pages/home.html` только при `$home = 1` (`index.php:94`), иначе `pages/module.html` → `layouts/app.html` |
| Автобандл CSS | `getThemeAssets()` `core/system.php:2549-2565`, строка `:2560` — `assets/css/*.css` по алфавиту; `presentation.css` встанет между `base.css` и `theme.css` без конфига |
| Аудит CSS | `tools/ui-audit.php:404` читает список `tools/ui-contract.php:32`, не glob; файл вне списка бандлится, но не проверяется |
| Реестр скриптов | `config/global.php:99` `script_f` — четыре записи; каталог раскрывается на один уровень `getAssetFiles()` `core/system.php:2568-2583`; условная подгрузка есть у капчи `core/classes/captcha.php:42-44` |
| Эталон | HTML 2626 строк: инлайн `<style>` 7-985 под `@scope (.sl-leader)`, инлайн `<script>` 1966-2624 (~660 строк), плюс `twentytwo.css` 1073 и `twentytwo.js` 536; `demo.js` и `demo.css` этой страницей **не загружаются**, `DEMO_SITES` до неё не доходит |
| Секции | 12 секций в нужном порядке уже в эталоне: `#workbench` 1039, `#system` 1046, `#security` 1114, `#runtime` 1231, `#development` 1370, `#live-project` 1405, `#pipeline` 1431, `#dna` 1505, `#archive` 1534, `#showcase` 1676, `#voices` 1897, `#pulse` 1931; герой 988-1019, рельс 1021-1036 |
| Материалы | `uploads/presentation/sites` 71 снимок 1024×768 + `sites/thumb` 71 превью 260 в ширину; `brand` 34 PNG 1920×1200, 20,5 МБ, `brand/thumb` пустой; `dna` 4 webp 1920×440, `dna/thumb` пустой |
| Рейтинг | `filesize(thumb)/20`: `hostmind.ru.png` — 67222 Б → 3361, ровно `rate` в `DEMO_SITES` `demo/assets/demo.js:334`; база — превью, как в старом `main` (`slaed-old…/modules/main/index.php:103`) |
| Страница рельса | `tools/ui-shots.json:57` — `front` снимает `/`, то есть сегодня news |

## 1. Правила игры

- Правила `.rules/*` и `CLAUDE.md` старше плана; для темы решает `tools/ui-contract.php`.
- Архитектурные решения владельца переносятся как есть: свой `templates/lite/assets/css/presentation.css`
  с записью в `themes.lite.css` контракта и токенами только из `base.css`; разметка только в теме —
  `partials/presentation-<блок>.html` на секцию и `fragments/presentation-<карточка>.html` на повторяющийся
  элемент, PHP отдаёт данные и семантические флаги; поведение — плагин `plugins/presentation/`; данные —
  только из системы, без зашитых чисел; отзывы — отдельный массив или таблица без захардкоженного HTML.
- Решения по виду 1-6 из задания переносятся дословно (сайты без адреса и без модалки, лента 4 с,
  cover/contain, единый фильтр снимков, порядок секций, рельс без подписи и поиска, стандарт 11/12/8/4/34/18/16).
- Никаких `class` из PHP; `--markup` держит ноль (`tools/ui-contract.php:794`, `tools/ui-audit.php:993-1005`
  сканирует `admin, core, modules, plugins`), значит SVG-проводка модульной карты, пути конвейера и любой
  тег живут в `.html`.
- `TemplateValidationTest`: `style="…"` только с `{{ }}` внутри (`tests/TemplateValidationTest.php:415-421`),
  никакого `<style>` (`:412`), баланс `{% if %}` (`:111-112`), каждое имя в `getHtmlPart()`/`getHtmlFrag()`
  должно существовать файлом (`:297-302`).
- Движок шаблонов без сравнений (`docs/TEMPLATES.md`, «Engine Limitations»): все ветвления — флаги
  `is_*`/`has_*`, вычисленные в PHP; цикл `{% for item in items %}` и `{% include '…' with item %}` есть
  (`core/classes/template.php:425-444`).
- Новые токены — только в API-блок `base.css` над маркером `:562`; имя компонента — из объявленных в
  контракте `:187-210` (`rail`, `veil`, `quote`, `site`, `site-img`, `ring`, `brand`, `marquee`, `spark`,
  `pulse`, `meter`, `progress` уже есть) или новое имя одной вставкой в список.
- Ни одна цифра аудита не растёт; `--store` только в конце батча B и в конце плана.
- Один коммит на батч по команде пользователя; между батчами дерево проходит `php -l` и одну страницу рельса.

**Стоп-условия.** Схема БД, адрес или контракт API должны измениться сверх принятого в разделе 8 (запись
`monitor` в `config/scheduler.php` и правка ветки `$home` в `index.php` уже решены); причина не сводится к file:line; батч расползается за пределы
presentation, `core/monitor.php` и перечисленных выносов. Вопрос — формой `AskUserQuestion`, не прозой.

## 2. Findings

### 2.1 Блокеры контракта и архитектуры

**F1. `--sl-font-micro` 11 px вне лестницы.** `demo/assets/twentytwo.css:9` переопределяет
`--sl-font-micro: 11px` на `.sl-leader`; в теме `base.css:99` — `10px`, лестница `font-size` в контракте —
`10 / 12 / 14 …`, шага 11 нет. Скоуп-переопределение семантического токена в `theme.css`/`presentation.css`
запрещено («custom property, that could be mistaken for API»), off-step значение красит аудит. Нужна
развилка: поднять `--sl-font-micro` глобально до 11 (тронет всё, что читает micro: `theme.css`, admin-тема
отдельно), либо снять micro до 12 на странице, либо добавить шаг в лестницу (контракт). → решение 6.

**F2. Три визуальных литерала без оси.** Фильтр снимков `twentytwo.css:842-846`
(`grayscale(.35) saturate(.75) contrast(1.02) brightness(.85)`), градиент под подписью `:936` (стопы 28 % /
58 %, доли панели 10 % / 43 %), глиф кавычки `demo/22-dashboard-layout-etalon.html:437` (`80px Georgia`,
`right: 12px; top: -14px`). У компонентного токена закрытый список свойств (`tools/ui-contract.php:181-184`),
`filter` и `font` в нём нет; в `allowlist.properties` `:104-140` `filter` не числится; у оси `grad` роли
`line/gloss/stripe/progress-*`, роли «caption» нет; у `--sl-size-*` роли для глифа нет. Значит: либо записи в
allowlist с причиной, либо две новые роли (`--sl-grad-caption`, размер глифа) — обе контрактные. Вуаль
«--blue 10 %» — это ровно `--sl-tint` `base.css:247` (`primary 10%`), новый токен не нужен; тон кавычки —
`--sl-quote-mix: 18%` (компонент `quote` объявлен, `mix` — разрешённое свойство, образец `--sl-tile-mix`
`base.css:409` и `color-mix(... var(--sl-cat-tone) …)` `theme.css:7251`). → решение 6.

**F3. Нет `--sl-z-sticky` и `--sl-face-quote` в lite.** Слои lite `base.css:288-294` — `base, raised,
overlay, dropdown, popover, modal, toast`; `sticky` есть только в admin (`templates/admin/assets/css/base.css:147`).
Ось `face` в lite не объявлена вовсе. Рельс липкий, кавычка на `Georgia` — оба токена добавить в API-блок
lite первыми (по решению владельца), имена уже в контракте (ось `face` `:53`, ось `layer`).

**F4. Новый CSS-файл виден рантайму, но не гейтам.** `tools/ui-contract.php:32` (список `css` lite),
`tests/Unit/ThemeCreationTest.php:55` и `:67` (литеральные списки трёх файлов, копируемых в новую тему) —
без записи `presentation.css` во все три места аудит его не откроет, а `ThemeCreationTest` не проверит.
Базовая линия per-theme, per-file записи нет — новых ключей в `tools/ui-audit-baseline.json` не нужно.

**F5. Префикс языковых констант не помещается.** `.rules/constants.md`: префикс равен имени модуля, длина
константы 2-18. `_PRESENTATION_` — 14 символов, на имя остаётся 4 (`_PRESENTATION_DNA` ещё влезает,
`_PRESENTATION_SITES` — нет). Эталон несёт сотни строк русского текста (герой 996, принципы 1508-1531,
конвейер 1431-1503, копия карточек). → решение 2.

**F6. Стартовая страница не готова к presentation.** `index.php:94-101` в ветке `$home = 1` не берёт
`$blocks`/`$blocks_c` из конфига модуля (это делает только именованная ветка `:52-53`), поэтому как
стартовый модуль presentation получит левую и правую колонки вопреки `side => '3'`; `layouts/home.html:8`
печатает `<h1 class="sl-title">{{ sitename }}</h1>` перед контентом — при собственном `<h1>` героя (эталон
`:996`) на странице будет два `h1`. Обе правки — вне модуля (`index.php`, layout). → решение 1.

**F7. Кеш страниц.** `checkPageCache()` `core/system.php:1607-1620`: allowlist маршрутов `:1618` —
только news, ветка `cache == 2 && !$home` `:1612` касается только стартовой. Сегодня presentation не
кешируется никогда — живые цифры безопасны; при переводе в стартовые (`cache = 2`) страница попадёт в
`cache == 2` ветку, но не пройдёт `getCacheRouteVars()` `:1587-1597` (regex `name = news`). Поведение
предсказуемо: кеша нет. Если владелец захочет кешировать, live-блоки нужно оборачивать в
`[[sldyn:` регионы (`:1547-1586`) — вне плана.

### 2.2 Данные: источники, зависимости, стоимость

**F8. Помощники монитора закрыты админкой и дороги.** `admin/modules/monitor.php:7` — `ADMIN_FILE` +
`isAdmin(true)`. Чистые функции без admin-зависимостей: `getCpuLoad` `:10`, `getMemoryInfo` `:136`
(+ `:156`, `:211`, `:253`), `getCpuCores` `:258`, `getNetworkStats` `:299` (+ `:307`, `:325`, `:354`),
`getMetricStorePath/getMetricStore/setMetricStore/addHistory` `:376-417`, `getDiskIoTotals/getDiskIoMetrics`
`:647-705`, `getUptimeInfo/getUptimeText` `:706-734`, `getDbHealth` `:837` (нужны гранты `SHOW GLOBAL STATUS`),
`getErrorLogCountHours` `:940`, `getFileTailChunk/getTailLines/getLogLineTimestamp` `:960-999`,
`getFailedLoginCountHours` `:1007`, `getSecurityEventHours` `:1046`, `getMonitorDiskSnapshot` `:1109`,
`isPathAllowed/getFileSafe/getServerValue/getCookieValues` `:92-135`. Привязаны к админке (не выносить):
`getStatusHtml` `:826`, `getMonitorServerStats` `:1293` (`$tpl`, попаверы; чистая часть — разбор
`SERVER_SOFTWARE` в `servname/servver` уходит в `getServerSoftware()`, см. 4.3), `getMonitorTemplateVars` `:1459`, `setMonitorPage` `:1572`,
`getMonitorChartSvg` `:514`, бэкапы `:894-939`. `getCpuLoad` читает `_NO_INFO` и `_PLOAD1` — обе есть во
фронтовых `lang/en.php:400,432`. Стоимость: на Windows каждая — `exec()` PowerShell/`wmic` 50-300 мс;
история пишется только пока открыт админ-монитор (`getMonitorPanelSnapshot` `:1123-1137`, APCu 3 с;
записи `monitor` в `config/scheduler.php` нет). Публичная страница либо платит `exec()` на каждый
запрос, либо читает устаревший `monitor.json`. → решение 4.

**F9. Журнал охраны — публичная утечка.** `getSecurityEventHours` `admin/modules/monitor.php:1046-1075`
возвращает строку последнего события из `warn.log, hack.log, error_site.log, error_php.log, log.log`
с текстом запроса; в эталоне журнал `:1223` пуст и засевается шестью строками JS (`:2453-2461`) из
выдуманного `TRAFFIC` `:2440-2451`. Реальный хвост несёт IP и payload атак. → решение 5.

**F10. Итоговое время генерации известно только в подвале.** `getLoadStats()` `core/system.php:3195`
меряет до момента вызова; финальную подстановку делает `getTimedHtml()` `:3553` по `GEN_MARK` и только при
`$conf['db_t'] == '1'`, причём фразой, а не числом. Допущение плана: герой и секция «Скорость» показывают
значения на момент конца рендера модуля (все SQL секций уже выполнены) — без правки ядра.

**F11. Знаменатели датчиков уже есть в ядре.** `getDebugSystemInfo()` `core/system.php:3218-3227` держит
локальный `$max` (лимит памяти, 2.0 с, 50 запросов, 0.010 с) — те самые «12/50» эталона `:993`. Вынести
в `getLoadLimits()` и читать из обеих точек; тон — только `getPercentTone()` `:3210`.

**F12. Счётчики без сырого читателя.** Онлайн — `getUserSessionInfo()` `core/system.php:3854-3857` считает
`COUNT` по `_session` (участники / боты / все) и сразу рендерит `session-summary`; сырых чисел нет.
Визиты — `statistic.log` (одна строка, 17 полей, писатель `updateStatsTrack()` `:1259-1285`: 1 хосты,
2 визиты, 3 всего, 6 стартовая, 13 почасовка, 14 новые/вернувшиеся, 15 глубина) и `days.log` (строка на
день) — читают напрямую `core/user.php:1603-1611` (PNG-счётчик), `getStatistic()` `core/admin.php:10-60`,
`admin/modules/statistic.php:50-66`; `getCounterField()` `:939` разбирает карты. Нужны два читателя в
ядре с миграцией существующих вызовов (реальная консолидация).

**F13. Счёт строк заперт в админке.** `getAdminCountRow()` `core/admin.php:340-342` — `COUNT(id)` плюс
рендер; фронт повторяет тот же запрос в девяти модулях (`modules/users/index.php:137`,
`modules/news/index.php:342`, `modules/files/index.php:324`, `modules/pages/index.php:315` …). Вынести
`getTableCount(string $table, string $where = '', array $bind = []): int` в `core/system.php`, перевести
`getAdminCountRow()` на него; фронтовые модули — вне плана, но точка консолидации названа.

**F14. Changelog пригоден, но сетевой.** `chlogLoadCommits()` `modules/changelog/common.php:118` без
admin-guard, `require_once` из другого модуля работает; источник `github` (`config/changelog.php:8-19`,
файл в `.gitignore:23`, токен наружу не уходит), кеш `CACHE_DIR/changelog/*.json` (`:355-360`), TTL `CHLOG_DEFAULT_CACHE_TTL` 900 с (`:9`) при `cachettl` 3600 в `config/changelog.php:9` — какой из двух действует, проверить в A3.
На холодном кеше публичный запрос ждёт GitHub API. Ветка `master` в данных нет — брать из `$conf`
(если ключа нет — не показывать). Имена `chlog*` нарушают именование, но вне задачи.

**F15. Имена и категории сайтов не выводятся из файлов.** В эталоне 71 карточка с именем и категорией
(`:1679-1892`, 33 разных категории, свободный текст); `DEMO_SITES` `demo/assets/demo.js:333-346` — 12
записей, из них лишь 3 совпадают с эталоном — два несвязанных набора. Подписи бренда («Простота» для
`wallpaper_ease.png`, группа «Кампании SLAED») из имени файла не получить; `contain` выводится по префиксу
`logotype-*`/`partner_*` (эталон `:1589-1645`). Нужен один источник данных для трёх списков. → решение 3.

**F16. Бренд грузит оригиналы.** Плитки `:1537-1673` ставят в `src` те же 1920×1200 PNG, что и в `href`
(20,5 МБ на секцию); `brand/thumb` пуст. Превью генерировать через `getImageThumb()` `core/system.php:5779`
один раз (uploads — пользовательский контент, допустимо), просмотрщик открывает оригинал. Сайты: эталон
кладёт в `src` полный снимок (`:1680`), README стенда и старый модуль — превью 260; модуль показывает превью.

**F17. Ветка просмотрщика.** `plugins/system/slaed.js:276` открывает `dialog[data-sl-shot="view"]` по
клику на `a.sl-attach, a.site-link`; `a.site-link` — класс старого `main`, мёртв с `ba6d2859`; `a.sl-attach`
стилизован как вложение `base.css:951-974`. Подпись берётся из `title` (`:283`), `data-caption` нет.
Сайтовый экземпляр создаётся без `acts` (`core/helpers.php:1067`) — кнопки «Скачать» в нём нет (есть только
в админском `fragments/window-foot-shot.html:2`), стрелки `[data-sl-shot-step]` при `can_walk` рендерятся,
но бинд есть только в `filemanager.js:1518` — в сайтовом просмотрщике они инертны (вне задачи, зафиксировать).
План: переименовать мёртвый `a.site-link` в хук `a[data-sl-shot-open]` на месте (без обёртки), плитка
бренда несёт `data-sl-shot-open` и `title`; «Скачать» — отдельный `<a download>` на плитке (решение 2).

**F18. Scroll-spy уже есть.** `setSpyRail()` `plugins/system/slaed.js:1168-1215`: `[data-sl-spy]` /
`[data-sl-spy-mark]`, `IntersectionObserver` с `rootMargin -20%/-70%`, дорисовка последнего пункта у низа
документа, клик → `scrollIntoView` с учётом reduced-motion, публикует `--sl-d-spy` в процентах
(`theme.css:7441`); секции настроек берут `scroll-margin-top` от `--sl-opt-rail-height` (`theme.css:7533`).
Решение 5 требует: заливка в px по `offsetLeft` активной пилюли, автоподвод пилюли в видимую область,
публикацию высоты рельса как offset. Либо расширить `setSpyRail()` (режим px + follow + offset), либо
плагин пишет своё. → решение 8.

**F19. Датчики-кольца.** Эталон рисует `[data-radial]` на canvas (`twentytwo.js:103-161`, 14 экземпляров).
В теме уже есть CSS-кольцо: `account-home.html:3` пишет `--sl-d-level`/`--sl-d-ring`, компонент `cab-ring`.
Кольца презентации — тем же приёмом (conic-gradient от `--sl-d-*`), canvas не переносится.

### 2.3 Эталон: дубли, конфликты, мёртвые хуки

**F20. Дубли поведения.** Подвод рельса — `twentytwo.js:164-182` и инлайн `:2018-2021`; свет за курсором
— `twentytwo.js:184-198` и инлайн `:1982`; `.sl-lab-*` объявлены дважды внутри `@scope` (`twentytwo.css`
~430-650 и ~653-784, например `.sl-lab-overline` `:430` `inline` против `:653` `block`). Переносить по одному
разу. `.sl-leader.sl-leader { overflow-x: clip }` `:219` несущий — `hidden` убьёт липкий рельс.

**F21. Демо-обвязка.** `getShell()` `twentytwo.js:51-101` (fetch шапки/подвала и просмотрщика с живого
сайта), панель `aside.sl-leader-tools` (`:1958-1964`), `?mode=/season=/bare=`, `localStorage['slaed.leader']`,
`host.dataset.motion/scheme` — не переносятся; на живой странице шапка, подвал и `dialog[data-sl-shot="view"]`
уже в DOM (`site-footer.html:73`).

**F22. Цифры без источника и конфликты.** Генерация `0.027` против `0.842` `:1327`; запросов `12` против
`5` `:1328`; файлов `685+` (`:1017`, `:1046`, `:1518`) против `702` `:1938`; кольцо CPU `15` против `6/12`
`:1942`; `#gaugeOnline` `:994` никто не пишет; `#rpsMini` (инлайн `:2186`) — элемента нет; `.snav a` `:1988`
— разметки нет; рандомизация `onlineMetric` `:2066`, `guardRate` `:2430`, `liveSync` `:2362`, строк событий
`:2192`, журнала ядра `:2045-2061`, случайные ряды `smoothCanvasSeries` `:2326-2327`. Всё заменяется одним
источником или уходит (раздел 6).

**F23. Инлайн-стили и лестницы.** Статичные `style` в эталоне: узлы блоков `--bx/--by` `:1085-1088`,
задержки точек и дорожек `:1131-1141`, `margin-top: 7px` `:1224`, столбики `--h` `:1284`, `--x` `:1246-1249`,
`--vc` на карточках отзывов `:1907/1913/1919`, `--pc` на пульсе `:1935-1938`. Тест пропустит только
`style` с `{{ }}`; динамические доли идут через `--sl-d-*` (реестр `tools/ui-contract.php:213-231`, файл —
в `places` `:238-250`), статичные — в CSS. Точки останова `twentytwo.css` — 1100/1024/900/800/768/560/480
против лестницы 560/768/900/1200; off-step px `:20, 21, 27, 92, 262, 264, 302, 877, 927, 993, 1011, 1018`
и `0px` `:11, 92, 143, 170, 177, 179` — снять на шаги или токены компонентов.

**F24. Алиасы `@scope`.** `--bg, --bg2, --panel, --panel2, --line, --ink, --muted, --blue, --blue2, --ice,
--good, --warn, --danger, --mx, --my, --scroll, --max` (эталон `:10-15`, `twentytwo.css:58-70`) — в теме
их нет; карта переноса: `--panel → --sl-surface`, `--line → --sl-border`, `--ink → --sl-text`,
`--muted → --sl-text-muted`, `--blue → --sl-primary`, `--blue2 → --sl-primary-strong`, `--good → --sl-success`,
`--warn → --sl-warning`, `--danger → --sl-danger`, `--mx/--my → --sl-d-*` (если свет за курсором остаётся).

### 2.4 Мелкое и вне задачи

- `blocks/img.php:13-21` — `opendir/readdir` по тому же `sites/thumb`; правило предпочитает `scandir()`. Не в плане, но
  после появления `getPresentationSites()` блок — кандидат на тот же читатель.
- `getUserSessionInfo()` и `getUserSessionAdminInfo()` `core/system.php:3854/3930` дублируют `COUNT` — закрывается F12.
- `$conf['uploads']['presentation']` в `config/uploads.php` нет; `FileManager` не нужен — списки читает `scandir()`.

## 3. Раскладка эталона по partials и fragments

Корень: `partials/presentation.html` — обёртка `.sl-pres` (`overflow-x: clip`), `{% include %}` героя,
рельса и двенадцати секций `with` под-массивами; модуль делает **один** `getHtmlPart('presentation', $data)`.
Общие фрагменты: `fragments/presentation-head.html` (номер `01`, иконка секции 18 px, заголовок, лид —
12 повторов, образец `fragments/panel-head.html`), `fragments/presentation-stat.html` (плитка: подпись,
значение, единица, примечание, `tone`, `--sl-d-part` — герой ×6, статистика ×5, скорость ×4, пульс ×4),
`fragments/presentation-ring.html` (кольцо: `--sl-d-level`, `--sl-d-ring`, число, подпись — 14 повторов).

| # | Секция эталона (строки) | Partial | Fragment повторов | Ключи данных (PHP → шаблон) | Флаги |
|---|---|---|---|---|---|
| — | герой 988-1019 | `presentation-hero.html` | `-ring` ×4, `-stat` ×6 | `version`, `gen`, `qnum`, `qmax`, `online`, `php`, `facts[]`, `stack[]`, `flow[]` (тексты — константы) | `is_ok` по `getPercentTone()` |
| — | рельс 1021-1036 | `presentation-rail.html` | цикл `{% for %}` inline | `items[] {id, label, num}`, `total` | `is_current` первому |
| 01 | Ритм `#workbench` 1039-1044 | `presentation-rhythm.html` | `-ring` ×1 | `series` (JSON в `data-sl-pres-series`: day/week/month), `volume`, `change`, `gen`, `qnum`, `hours[]` | `has_history` |
| 02 | Модули `#system` 1046-1110 | `presentation-modules.html` | `-module` ×8, узлы блоков inline | `modules[] {icon, label, href}`, `on`, `total`, `blocks[] {title, pos}`, `bcount` | `is_on` |
| 03 | Защита `#security` 1114-1229 | `presentation-guard.html` | строки журнала inline | `rate`, `allowed`, `blocked`, `failed`, `log[] {label, result}` (шесть статичных строк из констант) | `is_deny` |
| 04 | Скорость `#runtime` 1231-1368 | `presentation-runtime.html` | `-stat` ×4 | `gen`, `qnum`, `sql`, `avg`, `mem`, `memmax`, `online {all, users, bots}`, `driver`, `native`, `errmode` | `is_native` |
| 05 | Разработка `#development` 1370-1403 | `presentation-dev.html` | `-commit` ×5 | `version`, `commits[] {sha, short, iso, date, title, href}`, `last` | `has_commits` |
| 06 | Статистика `#live-project` 1405-1429 | `presentation-stats.html` | `-stat` ×5, `-ring` ×3, столбики inline | `visits`, `hosts`, `total`, `home`, `back`, `fresh`, `depth`, `human`, `hours[] {h, part}`, `since` | `has_today` |
| 07 | Архитектура `#pipeline` 1431-1503 | `presentation-pipeline.html` | шаги inline | `cache {mode, ttl, days, epoch}`, `states[]` | `is_cache_on`, `is_home_only` |
| 08 | Принципы `#dna` 1505-1532 | `presentation-dna.html` | принципы inline ×4 | `items[] {src, w, h, title, text, href}` | — |
| 09 | История `#archive` 1534-1675 | `presentation-archive.html` | `-brand` ×34 | `items[] {src, href, w, h, title, group, num}`, `total` | `is_contain`, `is_first` |
| 10 | Примеры `#showcase` 1676-1895 | `presentation-sites.html` | `-site` ×71 | `items[] {src, w, h, name, cat, rate, num}`, `total` | `has_cat` |
| 11 | Отзывы `#voices` 1897-1929 | `presentation-voices.html` | `-voice` ×N | `items[] {text, name, initials, site, role, since, tone, marks[] {icon, label}}` | — |
| 12 | Сообщество `#pulse` 1931-1949 | `presentation-pulse.html` | `-stat` ×4, `-ring` ×3 | `news {title, date, href}`, `topic {…}`, `pages {n, cats}`, `files {n, cats}`, `cpu`, `ram`, `disk`, `soft[]` | `has_monitor` |

Плагин получает хуки: `data-sl-pres="rail|sites|brand|chart"` на корнях, `data-sl-pres-step`, `data-sl-pres-range`,
`data-sl-shot-open`, `data-sl-spy-mark` (если рельс идёт через `setSpyRail`). Классы — только `sl-pres-*`,
`sl-gallery-*` из эталона переименовать в `sl-pres-*` или оставить как компонент `site`/`brand` темы — решить в B.

Запрещённые ключи данных: `file`, `path`, `data`, `real`, `lev`, `iscode`, `sourceType`, `sourceName` —
`{% include … with %}` теряет их в `getScope()` (`core/classes/template.php:669-675`, память
`template-extract-key-collision`). Для сайта и бренда брать `src`, `href`, `name`, `title`; `file` остаётся
ключом только внутри `config/presentation.php`, в шаблон не уходит.

## 4. Данные: что считает PHP и откуда

### 4.1 Источник каждой цифры

| Цифра | Источник | Функция (существует / новая) |
|---|---|---|
| Версия, ветка | `$conf['version']` (`config/global.php:116`); ветки в конфиге нет | — |
| Генерация, запросы, SQL-время, среднее, память | `getLoadStats()` `core/system.php:3195`; лимит `getMemoryLimitBytes()` `:3181` | есть |
| Знаменатели датчиков | `$max` из `getDebugSystemInfo()` `:3222-3227` | **новая** `getLoadLimits()`, читают обе точки |
| Тон | `getPercentTone()` `:3210` | есть |
| Онлайн: все / участники / боты | `COUNT` по `_session` `:3857` | **новая** `getSessionCounts()`; `getUserSessionInfo()` `:3854` и `:3931` переводятся на неё |
| Визиты, хосты, всего, стартовая, почасовка, новые/вернувшиеся, глубина | `COUNTER_DIR/statistic.log` поля 1/2/3/6/13/14/15 (`:1259-1285`) | **новая** `getStatsToday()`; `core/user.php:1600` переводится на неё |
| Ряды 7/30 дней | `COUNTER_DIR/days.log`, поле 2 по строкам | **новая** `getStatsDays(int $days)`; кандидат для `getStatistic()` `core/admin.php:10-60` |
| Запросов в минуту | хиты сегодня (поле 3 `statistic.log`, растёт на каждый запрос `:1268`), делённые на минуты с полуночи | считает модуль |
| Новости, файлы, страницы, пользователи, блоки, категории | `COUNT(id)` по таблицам `setup/sql/table.sql:457, 206, 535, 693, 42, 65` | **новая** `getTableCount()`; `getAdminCountRow()` `core/admin.php:342` переводится |
| Модули: включено / всего | `$conf['modules']`, флаг `active` (`is_active()` `:5417`) | считает модуль |
| Последняя новость, последняя тема | `_news` `ORDER BY time DESC LIMIT 1`; `getForumTopics()` `core/system.php:4156` | есть / один запрос |
| Коммиты | `chlogLoadCommits($conf, [], '')` `modules/changelog/common.php:118`, срез 5 | есть, `require_once` |
| Кеш: режим, TTL, дни, эпоха | `$conf['cache']`, `cache_t`, `cache_b`, `Cache::getEpoch()` `core/classes/cache.php:154` | есть |
| PHP, СУБД, веб-сервер | `PHP_VERSION`, `PDO::ATTR_SERVER_VERSION`, `SERVER_SOFTWARE` — разбор в `servname`/`servver` сидит внутри `getMonitorServerStats()` `:1295-1301` рядом с `$tpl` | **новая** `getServerSoftware(): array` в `core/monitor.php`; `getMonitorServerStats()` переводится на неё, сама остаётся в админке |
| CPU / RAM / диск / аптайм | `getCpuLoad`, `getCpuCores`, `getMemoryInfo`, `getMonitorDiskSnapshot`, `getUptimeInfo` | вынос в `core/monitor.php`; читается из `monitor.json` сэмплера (решение 4) |
| Ошибки 24 ч, неудачные входы, события охраны | `getErrorLogCountHours` `:940`, `getFailedLoginCountHours` `:1007`, `getSecurityEventHours` `:1046` | вынос; счёт событий — новая `getSecurityEventCount()` (решение 5) |
| Сайты: файл, превью, размер, рейтинг | `scandir(UPLOADS_DIR.'/presentation/sites/thumb')`, `getImageBox()` `:2991`, `round(filesize/20)` | **новая** `getPresentationSites()` в модуле; `shuffle()` в PHP |
| Сайты: имя, категория; бренд: подпись, группа; принципы: ссылки | `config/presentation.php` (решение 3) | читает модуль через `$conf['presentation']` |
| Бренд: файл, превью, размер | `scandir(…/brand)`, `brand/thumb` после генерации `getImageThumb()` `:5779` | **новая** `getPresentationBrand()` |
| Принципы | `scandir(…/dna)`, `getImageBox()` | **новая** `getPresentationDna()` |
| Отзывы | `config/presentation.php` `voices[]` (решение 3) | **новая** `getPresentationVoices()` |

### 4.2 Что выносится из admin/modules/monitor.php в общий слой

Новый файл `core/monitor.php` (одно слово, каталог даёт контекст) подключается **одним** `require_once
BASE_DIR.'/core/monitor.php'` из `core/system.php` в блоке `:124-142` рядом с `security.php`: потребителей
три — `admin/modules/monitor.php`, модуль и `addMonitorSample()` в самом ядре, поэтому ленивое подключение из
потребителей не рассматривается. Переносятся **перемещением, не копией**: `getCpuLoad`,
`getMemoryInfo` + `getMemoryInfoWindows` + `getMemoryInfoLinux` + `getMemorySafeLimit`, `getCpuCores`,
`getNetworkStats` + оба варианта + `getNetDevStats`, `getMetricStorePath`, `getMetricStore`, `setMetricStore`,
`addHistory`, `getDiskIoTotals`, `getDiskIoMetrics`, `getRealtimePanelMetrics` `:608-646` (единственный писатель истории `monitor.json`: читает хранилище, добавляет 30-точечные ряды, пишет), `getUptimeInfo`, `getUptimeText`, `getMonitorDiskSnapshot`,
`getErrorLogCountHours`, `getFileTailChunk`, `getTailLines`, `getLogLineTimestamp`, `getFailedLoginCountHours`,
`getSecurityEventHours`, `isPathAllowed`, `getFileSafe`, `getServerValue`, `getCookieValues`, `getDbHealth`.
`admin/modules/monitor.php` после переноса — только страница: снапшот, графики, шаблонные переменные,
маршруты. Комментарии над функциями обязательны в `core/*` (в `admin/modules/*` их не было — дописать).

### 4.3 Новые функции ядра и модуля (список — это и есть запрос на разрешение)

`core/system.php`: `getTableCount()`, `getSessionCounts()`, `getStatsToday()`, `getStatsDays()`, `getLoadLimits()`.
`core/monitor.php`: перечисленное в 4.2 плюс `getSecurityEventCount()` (решение 5) и `getServerSoftware(): array` — разбор `SERVER_SOFTWARE` в `['name', 'version']`, вынутый из `getMonitorServerStats()` `admin/modules/monitor.php:1295-1301`, которая остаётся в админке из-за `$tpl` и вызывает новую функцию; `addMonitorSample()` в `core/system.php` (решение 4).
`modules/presentation/index.php`: `getPresentationSites()`, `getPresentationBrand()`, `getPresentationDna()`,
`getPresentationVoices()`, `getPresentationData()` (собирает массив для `getHtmlPart`), `presentation()`.
Все имена 6-24 символа, глагольный префикс, без цифр; переменные 2-8 букв.

## 5. Что уходит в плагин plugins/presentation/

Файл `plugins/presentation/presentation.js`, одно IIFE в стиле `slaed.js` (ES5, `data-sl-*`, флаг
`data-sl-…-ready="1"`, реинициализация не нужна — htmx на странице нет). Подключение — по образцу капчи
`core/classes/captcha.php:42-44`: PHP отдаёт `script_src => 'plugins/presentation/presentation.js'`,
партиал печатает его через `fragments/head-script-src.html`; в глобальный `script_f` не добавлять
(иначе каждый лист сайта грузит скрипт одной страницы). Если владелец предпочтёт бандл — запись каталога
`plugins/presentation` в `script_f` через админ-настройки и умолчание `config/global.php:99`.

Переносится:
- **Лента сайтов** (`twentytwo.js:431-535`): шаг 4000 мс `:520`, пропуск тика при `document.hidden` и
  `prefers-reduced-motion` `:521`, пауза на `pointerenter/focusin` `:525-527`, работа только в кадре
  (`IntersectionObserver` `.25` `:533`), ползунок `range` ↔ `scrollLeft` `:448-468`, кнопки шага с кольцом
  `:486-495`, клавиатура на дорожке. Перемешивание — **в PHP**, JS-shuffle `:438-446` не переносится.
  Проверка `host.dataset.motion` заменяется на `prefers-reduced-motion` (пользовательской настройки анимации
  в системе нет).
- **Галерея бренда**: слот-фича и порядок `:448-468`, ползунок, «Открыть» → `setWindowOpen()` с
  `[data-sl-shot-img]`/`[data-sl-shot-name]` (`slaed.js:267-285`, после переименования хука F17),
  стрелки/клавиши внутри окна `:400-429` — только если владелец хочет листание (стрелки сайтового
  просмотрщика сегодня инертны, F17).
- **Рельс**: подвод активной пилюли `:164-182`, заливка в px по `offsetLeft`, публикация высоты рельса
  (`--sl-d-rail`), `overflow-anchor: none` — в CSS. Живёт в `setSpyRail()` `slaed.js`, не в плагине (решение 8).
- **График ритма** (`:200-311`): ряды из `data-sl-pres-series`, периоды, тултип, `ResizeObserver`;
  токены читаются `getComputedStyle` → имена в `tools/ui-contract.php:233-236` (`js`).
- **Появление секций** (`.reveal`) — `IntersectionObserver` или CSS `animation-timeline`; выбрать в D.

Не переносится: `getShell()`, панель инструментов, `localStorage`, параметры URL, свет за курсором
(если не оставлен как CSS), radial-canvas (F19), фальшивые интервалы (F22), пресеты модульной карты
`:326-347` с зашитыми списками, `MutationObserver` карты.

## 6. Демо-декор, который не переносится

Числа без источника заменяются данными из 4.1 или уходят вместе с элементом:
- герой: журнал ядра `:1005-1008` и `CORE` `:2045-2061`, `cache hit 88 %` `:991` (счётчика попаданий у
  кеша страниц нет — кольцо становится «состояние кеша»), знаменатель `120` онлайна `:994`;
- ритм: подписи оси `1000/750/…` `:240`, «N запросов» в тултипе `:301`, второе кольцо `80` `:1042`;
- модули: «685» `:1046/:2077` (это счёт файлов архива, не модулей), координаты узлов `:1085-1088`,
  проводка SVG остаётся статичной геометрией в шаблоне;
- защита: `guardRate` рандом `:2430`, дорожки/точки `:1131-1141`, ловушки `:1170`, выдуманный `TRAFFIC`
  `:2440-2451` — счётчики реальные, дорожки статичная CSS-схема (решения 5 и 7);
- скорость: PDO-консоль `:1266-1283`, столбики `:1284`, трасса `:1301-1303`, строки ошибок `:1337-1340`,
  карточки переменных запроса `:1347-1349`, SQL-консоль `:1356-1360`, события `:1365` — статичные схемы без цифр (решение 7);
- разработка: «915 tests · 193 gates», «23 designs», «16 band treatments» `:1383-1386`, `DEV_COMMITS`
  `:2331-2339` (дубль данных), прогресс конвейера;
- статистика: `liveSync` `:2362`, задержки `--d` `:1417-1418`;
- архитектура: живая строка запроса `:1445` и сценарии `:2094-2098`, анимированные точки на путях `:1449-1457`;
- пульс: `6 / 12 cores` при кольце `15` `:1942` — оба от монитора;
- отзывы: три демо-карточки `:1907-1923` с пометкой «DEMO TEXT» `:1905` — только структура.

## 7. Порядок батчей

| # | Батч | Зависит от | Скилл | Объём | Риск |
|---|---|---|---|---|---|
| A | Данные и общий слой: `core/monitor.php`, задача планировщика, пять функций ядра, `config/presentation.php`, константы `_PRES_*`, функции модуля | — | `/refactor-slaed-module`, `/secure-database-access`, `/modernize-php-code` (константы) | средний, только PHP | средний: правки `core/admin.php`, `core/user.php`, `admin/modules/monitor.php`, `config/scheduler.php` |
| B | Тема: `--sl-font-micro` 11 в обеих темах, токены `base.css`, контракт, `presentation.css` | `ui:before` до первой правки | `/manage-theme-tokens` | большой: ~2000 строк CSS эталона → один файл | средний: аудит обеих тем, дупликатор |
| C | Разметка: партиалы, фрагменты, сборка `getPresentationData()` | A, B | `/manage-slaed-templates` | большой: 15 партиалов, 7 фрагментов | средний: TemplateValidation, `--markup` |
| D | Плагин `plugins/presentation/presentation.js`, расширение `setSpyRail()` и хук просмотрщика в `slaed.js` | C | `/browser-debugging` | средний | низкий |
| E | Стартовая ветка `index.php`, флаг заголовка в `layouts/home.html`, рельс скриншотов, уборка, финальные проверки | A-D | `/execute-test-suite` | малый | средний: правка `index.php` |

A и B независимы и могут идти параллельно; C только после обоих.

### A. Данные и общий слой

**A1. `core/monitor.php` и сэмплер.** Перенести функции из 4.2, подключить одним `require_once` из
`core/system.php:124-142` (решено в 4.2, у сессии развилки нет); дописать комментарии над функциями;
`admin/modules/monitor.php` теряет перенесённое. Задача планировщика: запись `monitor` в
`config/scheduler.php` (`type system`, `system monitor`, `schedule * * * * *`, `manual 1`, `priority 9` — больше любого существующего, максимум сейчас 5: тик берёт одну задачу по возрастанию приоритета `core/system.php:303, 577`, иначе ежеминутная задача отбирает тики у `maildrain` и `newsletter`) и ветка
`'monitor' => addMonitorSample()` в `addSchedulerSystemJob()` `core/system.php:593-602`; функция вызывает `getRealtimePanelMetrics()` и `getDiskIoMetrics()` — единственных писателей `monitor.json` — и дописывает `sampled_at`; своей записи истории у сэмплера нет. Псевдо-cron тикает одним `fetch` после загрузки страницы (`core/system.php:1872`), поэтому без реального cron первый визитёр после тишины дольше пяти минут получит `has_monitor = false` — это ожидаемо.
Контракт `monitor.json` для модуля: оба писателя кладут только ряды и предыдущие счётчики
(`sys_hist_cpu`, `sys_hist_ram`, `net_hist_down`, `net_hist_up`, `net_prev_*`, `disk_prev_*`, `disk_hist_*`
`admin/modules/monitor.php:626-633, 689-693`), текущие CPU и RAM — последняя точка своего ряда, а диска,
аптайма, ядер и ПО в хранилище нет. Поэтому `addMonitorSample()` после двух вызовов дописывает снимок
теми же `getMetricStore()`/`setMetricStore()`: `disk_pct`, `disk_used`, `disk_total` (из
`getMonitorDiskSnapshot()` `:1109-1121`), `uptime` (`getUptimeInfo()`), `cores` (`getCpuCores()`), `soft`
(`getServerSoftware()` из 4.3 для имени и версии веб-сервера, плюс `PHP_VERSION` и
`PDO::ATTR_SERVER_VERSION`), `sampled_at`. Именно эти ключи читает секция «Сообщество» (`cpu`, `ram`,
`disk`, `soft[]` в разделе 3). Модуль читает только `getMetricStore()`;
`has_monitor = false`, если хранилища нет или `sampled_at` старше пяти минут. Приёмка: `php -l`; `phpunit
--filter "AdminPageRenderFlow|SchedulerLock"`; ручной запуск задачи из `admin.php?name=scheduler` пишет
`monitor.json`; `admin.php?name=monitor` и вкладки `traffic/status/sync` — HTML без изменений (сравнить
размер ответа до/после); `storage/logs/error_php.log` без новых записей.

**A2. Пять функций ядра.** `getTableCount()` (перевести `getAdminCountRow()` `core/admin.php:342`),
`getSessionCounts()` (перевести `:3857` и `:3930`), `getStatsToday()`/`getStatsDays()` (перевести
`core/user.php:1603-1611`; `getStatistic()` `core/admin.php:10-60` — если сводится без потери формата графика),
`getLoadLimits()` (перевести `getDebugSystemInfo()`). SQL — именованные плейсхолдеры, таблица — из
закрытого списка, не из ввода. Приёмка: `phpstan` по `core`; `phpunit --filter "StatsContract|AdminPageRender|
SecurityValidation"`; `index.php?stat=1&img=1` отдаёт тот же PNG; сводка сессий на сайте прежняя.

**A3. Данные модуля.** Первым шагом после создания `config/presentation.php` удалить `config/local.php` — кэш `getConfig()` `core/system.php:38-75` держит список `config/*.php`, без сброса `$conf['presentation']` пуст (см. память `stand-verification-gotchas` про OPcache). `config/presentation.php`: `sites[file] => [name, cat]` из эталона `:1679-1892`
(71 запись — извлечь скриптом из HTML, не руками; `name` — одноязычное имя собственное, `cat` — имя константы `_PRES_CAT_*`), `brand[file] => [title, group]` (оба — имена констант), `dna[file] =>
[title, text, href]` (константы), `voices[]` (текст цитаты и имя автора — одноязычные, роль и метки — константы). Правило: словарные поля — имена констант, имена собственные и цитаты — сырой текст (решение 11). Константы
`modules/presentation/lang/*.php` для шести локалей с префиксом `_PRES_` (исключение к правилу «префикс
равен имени модуля» записать в `.rules/constants.md` в том же батче), `en.php` первым; все тексты эталона —
константами, включая прозу героя, принципов и конвейера. Функции 4.3 в модуле, возвращающие массивы;
`shuffle()` в `getPresentationSites()`; рейтинг `(int)round(filesize($thumb) / 20)`; `getImageBox()` для
`width/height`; пропуск `index.html`, каталогов и файлов без превью. Приёмка: `php -l`, `phpstan modules`,
`phpunit --filter "LanguageValidation|StructureTest"`; временный `var_export` в scratchpad показывает 71 сайт,
34 материала, 4 принципа с непустыми полями.

**A4. Превью бренда.** Один прогон `getImageThumb()` по `uploads/presentation/brand/*.png` в `brand/thumb`
(ширина 480 — обсудить в A3 замером высоты плитки; `dna/thumb` не нужен, полоса показывается целиком).
Превью коммитятся в батч A вместе с кодом (решение 12): `uploads/presentation` трекается, 188 файлов, untracked-превью сломали бы чистое дерево между батчами. Приёмка: 34 файла в `brand/thumb`, суммарно менее 2 МБ, все в индексе.

### B. Тема

**B0.** `npm run ui:before` до первой правки; каталог `%LOCALAPPDATA%\Temp\slaed-ui-guard` очищен.

**B1. Токены и контракт.** Шаг лестницы `font-size` `10 → 11` в `tools/ui-contract.php` и
`--sl-font-micro: 11px` в `templates/lite/assets/css/base.css:99` и в admin-теме (одно решение на обе темы;
рельс `ui:after` покажет, что сдвинулось в admin и на страницах lite, читающих micro). В API-блок lite над
`:562`: `--sl-z-sticky` (между `dropdown` 30 и `popover` 1000, тот же `40`, что в admin `:147`),
`--sl-face-quote`, роль `--sl-grad-caption` (ось `grad` в контракте расширяется на одну роль, стопы
28 % / 58 % и доли 10 % / 43 % внутри значения); компонентные `--sl-rail-*`, `--sl-veil-*`, `--sl-quote-mix: 18%`,
`--sl-site-*`; allowlist `properties`: `filter` с причиной (единый фильтр снимков — одна формула на четыре
секции, не цвет и не размер) и размер глифа кавычки (`80px` — оптика знака, не типографский шаг); `--sl-d-rail`
и другие `--sl-d-*` в `data` контракта `:213-231`, файлы партиалов — в `places` `:238-250`, читаемые JS
токены — в `js` `:233-236`, `presentation.css` — в `themes.lite.css` `:32` и в `ThemeCreationTest.php:55,67`.
Приёмка: `php tools/ui-audit.php --theme=lite` и `--theme=admin` без роста; `phpunit --filter
"ThemeContract|ThemeCreation|UiAudit"`.

**B2. `presentation.css`.** Перенос `twentytwo.css` и инлайн-стиля эталона `:7-985` в один файл под
корнем `.sl-pres`: алиасы по карте F24, дубли F20 один раз, точки останова на лестницу, off-step px на
шаги/токены, `0px` → `0`, `@scope` не нужен (корень — класс). Вуаль `--sl-tint`, фильтр литералом под
записью allowlist, градиент `--sl-grad-caption`, `object-fit: contain` по `[data-sl-pres-fit="contain"]`.
Каскад: `presentation.css` бандлится **до** `theme.css`, поэтому переопределение общих компонентов (кольцо `cab-ring`, чипы, `panel-head`) при равной специфичности проигрывает — каждое такое правило пишется под предком `.sl-pres`. После создания файла удалить `config/local.php`: `getConfig()` `core/system.php:38-75` кэширует список CSS темы в `derived.assets`, без сброса `presentation.css` не попадёт в бандл. Секции-иллюстрации (PDO-консоль, трасса, дорожки, конвейер) — только CSS-анимация с `prefers-reduced-motion`,
без JS. Радиусы 12/8/4/pill/circle,
отступы `--sl-space-*`, боксы `--sl-size-control`, иконки `--sl-size-icon-sm`/`icon-xs`, `--sl-font-body`
в рельсе. `prefers-reduced-motion` в CSS гасит автоанимации. Приёмка: хук `lint-edit.php` молчит на
каждом сохранении; `ui-audit --theme=lite` — счётчики не выше; `php tools/ui-audit.php --store` в конце батча.

### C. Разметка

**C1. Каркас.** `partials/presentation.html`, `-hero`, `-rail`, `fragments/presentation-head/-stat/-ring`,
секции 01, 02, 05, 06, 07, 08, 12; `getPresentationData()` и `presentation()` в модуле; `setHead()` с
заголовком из константы. Иконки — имена через `getIconName()`. Приёмка: `php -l`; `phpunit --filter
TemplateValidation`; `php tools/ui-audit.php --markup` = 0; `index.php?name=presentation` открывается без
ошибок в `storage/logs/error_php.log`; Playwright-обход временным скриптом в корне репозитория (удалить после):
12 якорей рельса ведут к секциям, ни один элемент не шире родителя, консоль пуста.

**C2. Галереи, отзывы, охрана, скорость.** Секции 09, 10, 11, 03, 04 и фрагменты `-brand`, `-site`,
`-voice`, `-commit`; плитка бренда несёт `data-sl-shot-open`, `title`, `<a download>`; сайты без ссылки.
Журнал охраны: счётчики за 24 ч (события охраны, неудачные входы, визиты) из 4.1 и ровно шесть статичных
строк-легенд из констант без времени и без данных запросов — высота секции стабильна. PDO-консоль, трасса,
строки ошибок, карточки переменных, живой запрос конвейера, дорожки атак — статичные схемы с подписями из
констант, без времён, миллисекунд и счётчиков. Приёмка: как C1 плюс `node tools/ui-shots.mjs --after
--only=front` (или страница `presentation` манифеста из E); перед проверкой секции «Сообщество» вручную прогнать задачу `monitor` из `admin.php?name=scheduler`, иначе `has_monitor = false`; измерение зазора иконок в чипсах 4 px; ни одного
текста ниже 11 px скриптом `getComputedStyle` по всем узлам `.sl-pres`.

### D. Плагин

`plugins/presentation/presentation.js` по разделу 5 без рельса: лента сайтов, галерея бренда, график
ритма, появление секций. Рельс — расширение `setSpyRail()` `plugins/system/slaed.js:1168-1215`: режим по
атрибуту `data-sl-spy="px"` (заливка `--sl-d-spy` в px по `offsetLeft + width / 2` активной пилюли вместо
процентов), подвод активной пилюли в видимую область рельса (`rail.scrollTo`, `twentytwo.js:164-182`) и
публикация высоты рельса как `--sl-d-rail` на `documentElement`; рельс настроек (`data-sl-spy` без
значения) не меняет поведения. В `slaed.js:276` `a.site-link` → `a[data-sl-shot-open]`; при чтении токенов —
запись в `js` контракта. Приёмка Playwright (перед ней ручной прогон задачи `monitor`): лента делает шаг
через 4 с, замирает под курсором и при фокусе, не двигается при `emulateMedia({reducedMotion: 'reduce'})`;
ползунок и `scrollLeft` совпадают в обе стороны; рельс: заливка кончается внутри активной пилюли
(`offsetLeft + width/2` с допуском 1 px), активная пилюля в видимой области после прокрутки к `#pulse`;
`#archive` открывает `dialog[data-sl-shot="view"]` с подписью и закрывается; консоль без ошибок;
`storage/logs/error_site.log` без новых записей.

### E. Стартовая страница и финиш

Модуль работает и как стартовый, и по адресу. Ветка `$home` в `index.php:94-101` начинает читать
`$blocks`/`$blocks_c` из `$conf['modules'][$name]['side'|'top']` так же, как именованная ветка `:52-53` —
блоки стартовой страницы управляются стандартно через `admin.php?name=modules`. Заголовок: модуль кладёт в
`$sitevars` флаг `has_own_title` **после** `setHead()` через `global $sitevars` (`setHead()` пересобирает массив с нуля `core/system.php:1917-1967`, `setFoot()` читает его `:1997`); `layouts/home.html:8` печатает `<h1 class="sl-title">{{ sitename }}</h1>`
только без него; `layouts/app.html` `h1` не печатает, дубль возможен только на стартовой; герой остаётся `h1`. Переключение `$conf['module']` — через админ-настройки, не кодом, и
по желанию владельца. В `tools/ui-shots.json` добавляется страница `{ "name": "presentation", "url":
"/index.php?name=presentation" }`, чтобы рельс снимал модуль независимо от стартового. Уборка: мёртвые хуки,
временные скрипты; `demo/22-*` остаётся стендом. Приёмка: `index.php` с `module = presentation` и с
`module = news` — блоки по настройкам модуля, один `h1` на странице; затем раздел 9.

**Breaking change (решение 10).** Сегодня ветка `$home` не задаёт `$blocks`, а `setFoot()` трактует пустое
значение как «обе колонки» (`core/system.php:2003-2012`); news со `side => '2'` (`config/modules.php:335`)
показывает на стартовой обе. После E стартовая честно слушает `side/top` модуля, и левая колонка news
исчезает. Настройка news не трогается; отчёт и коммит E заявляют это явно, кто хочет обе колонки — ставит
`side = 0` в `admin.php?name=modules`.

## 8. Принятые решения (владелец, формой: 1-9 от 2026-09-10, 10-12 от аудита 2026-09-11)

1. **Стартовая (E).** Модуль работает и как стартовый, и по адресу; блоки стартовой управляются
   стандартно через настройки модуля (`admin.php?name=modules`), для чего ветка `$home` в `index.php:94-101`
   начинает читать `side/top`.
2. **Тексты (A).** Всё константами в шести локалях, префикс `_PRES_`; исключение к «префикс равен имени
   модуля» записать в `.rules/constants.md`.
3. **Данные (A).** `config/presentation.php`: массивы сайтов, бренда, принципов и отзывов; админ-редактор
   позже отдельной задачей.
4. **Метрики (A).** Сэмплер в планировщике (`config/scheduler.php` + `addSchedulerSystemJob()`) пишет
   `monitor.json` раз в минуту; модуль только читает; пустое или старое хранилище — `has_monitor = false`.
5. **Журнал охраны (A/C).** Реальные счётчики за 24 ч плюс шесть статичных строк-легенд из констант; хвост
   логов на публичную страницу не выходит.
6. **Контракт (B).** `--sl-font-micro` 11 px глобально в обеих темах и шаг лестницы 10 → 11; `filter` и
   размер глифа кавычки — записи allowlist с причиной; градиент подписи — новая роль `--sl-grad-caption`.
7. **Декор (B/C).** Секции без источника остаются статичными схемами с подписями из констант, без
   времён, миллисекунд и счётчиков; анимация только CSS.
8. **Рельс (D).** Расширить `setSpyRail()` в `plugins/system/slaed.js` (px-режим, подвод пилюли,
   `--sl-d-rail`); один scroll-spy на систему, рельс настроек не меняется.
9. **Заголовок (E).** Флаг модуля `has_own_title` прячет `h1` sitename в `layouts/home.html:8`; герой
   остаётся `h1`.
10. **Колонки стартовой (E, аудит 2026-09-11).** Настройка news не меняется; исчезновение левой колонки на
    стартовой news после правки ветки `$home` заявляется в отчёте и коммите E как breaking change.
11. **Язык списков (A3, аудит 2026-09-11).** Словарные поля `config/presentation.php` (категории, подписи и
    группы бренда, роли и метки отзывов) — имена констант `_PRES_*`; имена сайтов, имена авторов и текст
    цитат — одноязычный сырой текст.
12. **Превью бренда (A4, аудит 2026-09-11).** 34 превью `brand/thumb` коммитятся в батч A.

## 9. Проверки

**По ходу** (не чаще раза на батч): `php -l` тронутых файлов; `phpunit --filter` набора батча;
`node tools/ui-shots.mjs --after --only=front` одной страницы; Playwright-обход временным скриптом в корне
репозитория (ESM ищет playwright от каталога скрипта), скрипт удаляется сразу после прогона.

**Один раз в конце**, последовательно, без параллели рельса и `phpunit`:

```
php -l …                       # все тронутые PHP
php vendor/bin/phpstan analyse --no-progress core modules
php vendor/bin/php-cs-fixer check --config=.php-cs-fixer.dist.php <тронутые php>
npm run ui:gates
php vendor/phpunit/phpunit/phpunit
set SLAED_UI_USER / SLAED_UI_PASS  и  npm run ui:after
php tools/ui-audit.php --store   # только если все счётчики упали или не изменились
```

После прогона прочитать `storage/logs/error_php.log`, `error_sql.log`, `error_site.log`. `phpstan` не видит
`admin/` и `plugins/` — `admin/modules/monitor.php` после A1 проверяется только `php -l` и открытием страницы.

**Критерий готовности.** Двенадцать секций в порядке решения 4 на `index.php?name=presentation` (или `/`);
ни одной цифры без источника из 4.1; `presentation.css` в контракте и `ThemeCreationTest`; `--markup` = 0;
счётчики `ui-audit` не выше базовой линии; лента, рельс и просмотрщик ведут себя как описано в D;
`admin.php?name=monitor` отдаёт прежний HTML.

## 10. Источники

- Память проекта: `feedback-checks-once-at-end`, `ui-rig-session-pages`, `ui-rig-git-conflict`,
  `chip-icon-gap-standard`, `template-extract-key-collision`, `project_global_ampersand_href_escaping`,
  `stand-verification-gotchas`, `presentation-deck-engine`, `feedback-ask-questions-forcibly`.
- Правила: `.rules/global.md`, `.rules/theme.md`, `.rules/constants.md`, `.rules/git.md`; контракт
  `tools/ui-contract.php`; `docs/TEMPLATES.md`, `docs/PLUGINS.md`, `docs/PAGE-CACHE-ROUTES-2026.md`.
- Старая логика: `E:\OSPanel\home\slaed-old.loc\public\modules\main\index.php:93-113` (shuffle, рейтинг).
- Скиллы: `/analyze-system-architecture`, `/audit-and-plan-refactor`, `/refactor-slaed-module`,
  `/manage-slaed-templates`, `/manage-theme-tokens`, `/secure-database-access`, `/browser-debugging`,
  `/execute-test-suite`.

## 11. Запуск: одна сессия на батч, одна фраза на сессию

Порядок строгий: A → B → C → D → E. Каждая сессия открывается на чистом дереве (`git status -sb` пуст,
предыдущий батч закоммичен) и заканчивается отчётом и ожиданием команды на коммит. Следующая сессия не
начинается, пока прошлая не закоммичена. Параллельно батчи не запускать: одно дерево, один рельс скриншотов.

Перед стартом любой сессии проверить три вещи: дерево чистое; прошлый батч в `git log -1`; для B и E —
пункты ниже. Если сессия задаёт вопрос, уже решённый в разделе 8, ответ один: «по плану, раздел 8».

### Универсальная команда

```
Работай по плану docs/PRESENTATION-INTEGRATION-2026.md
```

Получив её, сессия: читает строку «Статус» в шапке и берёт из неё следующий батч; сверяет с `git log -1`
(сообщение последнего коммита называет закрытый батч) и с `git status -sb` (дерево чистое) — при
расхождении останавливается формой `AskUserQuestion`, ничего не начиная; выполняет **ровно один** батч по
его фразе ниже, как если бы фраза была вставлена целиком, включая её преамбулу в скобках; после E
выполняет раздел 9. Если строка статуса говорит «следующего нет», сессия сообщает об этом и ничего не делает.
Фразы ниже остаются для ручного запуска и как текст, который универсальная команда разворачивает.

### Фразы для копирования

**A** (первая сессия заодно кладёт план в историю):

```
Выполни батч A из docs/PRESENTATION-INTEGRATION-2026.md. Первым действием закоммить сам план как Docs по .rules/git.md. Читать разделы 1, 4, 7 (A1-A4), 8, 9; решения раздела 8 не пересматривать; вопросы только через AskUserQuestion. По ходу только php -l и phpunit --filter из приёмки батча. В конце отчёт по .rules/report.md и стоп: коммит батча только по моей команде.
```

**B** (снимок до первой правки CSS обязателен):

```
Выполни батч B из docs/PRESENTATION-INTEGRATION-2026.md. До любой правки CSS очисти %LOCALAPPDATA%\Temp\slaed-ui-guard и выполни npm run ui:before. Читать разделы 1, 2.1, 7 (B0-B2), 8, 9; решения не пересматривать; вопросы только через AskUserQuestion. По ходу только php tools/ui-audit.php --theme=lite и --theme=admin, phpunit --filter из приёмки; php tools/ui-audit.php --store один раз в конце батча, если ни один счётчик не вырос. В конце отчёт по .rules/report.md и стоп: коммит только по моей команде.
```

**C**:

```
Выполни батч C из docs/PRESENTATION-INTEGRATION-2026.md (C1, затем C2). Читать разделы 1, 3, 4, 6, 7, 8, 9; решения не пересматривать; вопросы только через AskUserQuestion. Разметка только в partials/fragments, PHP отдаёт данные и флаги, --markup остаётся нулём. По ходу только php -l, phpunit --filter TemplateValidation, php tools/ui-audit.php --markup и один Playwright-обход временным скриптом из корня, который удалить после прогона. В конце отчёт по .rules/report.md и стоп: коммит только по моей команде.
```

**D**:

```
Выполни батч D из docs/PRESENTATION-INTEGRATION-2026.md. Читать разделы 5, 7 (D), 8 (решения 7 и 8), 9; рельс — расширение setSpyRail() в plugins/system/slaed.js, остальное в plugins/presentation/presentation.js; вопросы только через AskUserQuestion. Приёмка Playwright из батча D, включая emulateMedia reducedMotion. В конце отчёт по .rules/report.md и стоп: коммит только по моей команде.
```

**E** (нужны учётные данные стенда, иначе рельс молча пропустит страницы с сессией; отчёт заявляет breaking change по колонкам стартовой):

```
Выполни батч E из docs/PRESENTATION-INTEGRATION-2026.md, затем весь раздел 9 «Один раз в конце». Перед npm run ui:after задай SLAED_UI_USER и SLAED_UI_PASS из скилла /browser-debugging, раздел Logging in. Проверь index.php и с module = presentation, и с module = news. После зелёных проверок обнови строку статуса в шапке плана и выполни php tools/ui-audit.php --store, если счётчики не выросли. В конце отчёт по .rules/report.md и стоп: коммит только по моей команде.
```

### Команда на коммит

После отчёта, если он устраивает: `закоммить батч` (буква не нужна — сессия знает свой батч). Перед
коммитом сессия обновляет строку «Статус» в шапке плана: «закрыто A, следующий батч B» и так далее, после E —
«закрыто A-E, следующего нет»; план коммитится вместе с батчем, а тело сообщения коммита называет закрытый
батч буквой. Сессия сама соберёт сообщение из `.gitmessage`, тип `Refactor` для A, `Feature` для B-E, `Docs`
для плана. Если отчёт показывает красную проверку — `почини и перепроверь`, коммита нет, пока не зелено.

### Что сделать один раз перед A

Ничего. Стенд, правила, скиллы и контракт в нужном состоянии на `ba6d2859`; `npm install` уже ставит хуки.

### Если сессия остановилась вопросом

Отвечать в форме. Все двенадцать развилок уже закрыты разделом 8; новая развилка означает, что план чего-то не
предусмотрел — ответ записывается в раздел 8 той же сессией, чтобы следующая его видела.
