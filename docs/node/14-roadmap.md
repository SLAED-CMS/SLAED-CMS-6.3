# Реализация по отдельным окнам

Исполнитель: Claude Fable 5.1. Одно окно — один этап S00–S18, включая вставной S03A. Окно запускается одной командой «Работай по плану docs/node/PROGRESS.md»; текущий этап и протокол окна определяет [PROGRESS.md](PROGRESS.md). Порядок: **S00 — правка плана по аудиту готовности, затем Point как класс, затем Rating как класс**; девять старых модулей удаляются этапом S03A до первого подключения (NOD-224). Реализация сейчас не выполняется.

## Вход в каждое новое окно

1. Прочитать CLAUDE.md/AGENTS.md, протокол окна и блок передачи в [PROGRESS.md](PROGRESS.md), затем этот раздел и карточку этапа.
2. Проверить фактические файлы и результаты зависимостей; история чата не является источником состояния. Прочитать только перечисленные ниже разделы контрактов и подходящие проектные навыки.
3. Проверить git status и найденные вызовы затрагиваемого API. Сохранить чужие изменения. Выполнить один этап без заглушек и перехода к следующему.
4. Проверить результат, обновить строку PROGRESS.md и краткую передачу следующему окну: файлы, проверки/результаты, незавершённое, изменения контракта. Галочка только при выполненной приёмке.

Незавершённый этап продолжается в новом окне под тем же ID. Не начинать зависимый этап по одному заявлению предыдущего агента: проверить код и артефакты. Если код расходится с контрактом, исправить код либо явно согласовать изменение контракта; молчаливой новой архитектуры не вводить. review.md и 15-decisions.md — архив/история, не обязательное чтение каждого окна. TODO.md учитывает проектирование, PROGRESS.md — реализацию.

## Карточки этапов

### S00 — Правка плана по аудиту готовности

- Зависит: нет.
- Читать: рабочий документ сверки AUDIT.md целиком (удалён при закрытии этапа); документы-владельцы — по ссылкам из находок; код — только для подтверждения находки перед правкой контракта.
- Файлы: только docs/node. Код, схема, конфиги и тесты не изменяются.
- Готово: каждая находка AUDIT.md перенесена в документ-владелец и в карточку своего этапа либо закрыта ответом пользователя; решения R1–R3 применены — этап раннего удаления девяти модулей получает собственный ID, карточку и строку в PROGRESS.md перед S04, штатное обновление данных описано для setup/index.php ветки update6_3 с сохраняемой отметкой преобразования; устаревшие утверждения 09/11/13 исправлены; продуктовые развилки заданы одним пакетом и их ответы внесены; зависимости и списки «Читать»/«Файлы» карточек S01–S18 сверены заново; README.md и TODO.md отражают состояние; AUDIT.md удалён. Контроль документации из TODO.md (UTF-8, ссылки, один корневой заголовок, git diff --check по docs/node) пройден.

### S01 — Point — класс

- Зависит: нет.
- Читать: points.md целиком, включая «Заметки реализации S01»; 02 — правила имён.
- Файлы: core/classes/point.php; config/points.php; setup/sql/table.sql (_points и строка совместимости в заголовке); tests/Unit/PointTest.php; tests/Support/point_probe.php — изолированный процесс и одноразовая схема MariaDB.
- Готово: Полный API, строгая конфигурация, SQL/баланс/компенсации, SAVEPOINT и потеря внешней транзакции. Окно лимита считается только часами БД; getSqlError() читается только после false. Изолированная MariaDB. Живые обработчики и старые балансы ещё не переключать; стенд расходится с table.sql по _points до S04 — это ожидаемо.

### S02 — Rating — класс

- Зависит: S01 как порядок работ; Point не используется.
- Читать: ratings.md до HTTP, включая исполняемый DDL; 11 — «Завершённый протокол HTML-кеша» и «Физические файлы guard».
- Файлы: core/classes/rating.php; core/classes/cache.php (guard/addEpoch, deleteAll сохраняет guards); core/system.php (checkPageCache и заполнение HTML-кеша: маркеры guard и поколение); setup/sql/table.sql (три таблицы Rating); tests/Unit/RatingTest.php и проверки Cache; tests/Support/rating_probe.php — изолированный процесс, одноразовая схема MariaDB и scratch-каталог кеша.
- Готово: Полный API на доверенных тестовых адаптерах, срок/nonce/аннулирование/конкуренция, canvote, отказы SQL/COMMIT и восстановление guard. Чтение и заполнение HTML-кеша учитывают маркеры и поколение; общая очистка кеша не трогает guards/ и guards.lock. Правка checkPageCache ограничена этим протоколом: docs/PAGE-CACHE-ROUTES-2026.md заморожен до конца S18 (NOD-231). Node и HTTP не требуются.

### S03 — Конфигурация и файловые блокировки

- Зависит: S01, S02.
- Читать: 06 — настройки/восстановление, включая «Дополнение восстановления общих источников»; 11 — порядок lock.
- Файлы: core/system.php (setConfigFile/getConfig); core/classes/filemanager.php (владение lock и счётчик вложенности); admin/modules/config.php (op=restore) и шесть admin/lang; писатели admin/modules/fields.php, uploads.php, ratings.php; tests/Unit/ConfigFileTest.php и tests/Support/config_probe.php — изолированный процесс и scratch-копия config/; дополнение tests/Unit/FileManagerLockTest.php.
- Готово: Единственная новая форма setConfigFile — Closure, строковый вызов сохранён, возврат bool; общий lock чтения/сборки/публикации, журнал и OPcache/local.php, повтор после сбоя. getPathLock()/deletePathLock() повторно входимы в пределах запроса: без этого удержание корня типа самоблокирует Upload и операции FileManager. Вход восстановления — admin.php?name=config&op=restore для главного администратора; отсутствие local.php без маркера журнала остаётся штатной пересборкой getConfig(). В этом этапе общий механизм без вызовов ещё отсутствующего NodeService; Node-proof подключается на S10.

### S03A — Удаление девяти старых модулей

- Зависит: S03 как порядок работ.
- Читать: 12 — «Раннее удаление девяти модулей» целиком; 01 — состав выпуска.
- Файлы: modules/{news,pages,faq,help,jokes,content,links,files,media}/; их config/*.php и записи config/modules.php; блоки и сид setup/sql/insert.sql; девять таблиц в setup/sql/table.sql и их операторы в table_update6_3.sql; потребители старых таблиц, тесты и инструменты — по закрытому перечню 12.
- Готово: В дереве нет кода, конфигурации, блоков, сидов, DDL и тестов девяти модулей; общие helpers, потерявшие последнего вызывающего, удалены, остальные сохранены без веток старых модулей. Comment::MODULES содержит только account, shop и voting; несущая цель comment-тестов и contract_probe — shop. Данные и файлы стенда не импортируются и не удаляются: таблицы и uploads/<name> остаются нетронутыми. Главная стенда переведена на остающийся модуль. Полная общая приёмка, включая phpstan по modules и ui:gates; пара ui:before/ui:after вокруг удаления.

### S04 — Point — подключение

- Зависит: S01, S03, S03A.
- Читать: points.md целиком, включая «Карта остающихся владельцев»; 12 — граница данных и «Штатное обновление данных 6.3»; 09 — опросы.
- Файлы: core/system.php (setHead, updateVotingResult, getRatingView, updatePoints, addPointsAction), core/user.php (личные сообщения, избранное, показ баланса), core/helpers.php, core/classes/comment.php; blocks/user_info.php; admin/modules/groups.php; modules/account/index.php и admin/index.php; modules/users/index.php и константы правил в шести lang; index.php модулей forum, contact, recommend, auto_links; modules/order/index.php и admin/index.php (_order.uid); modules/shop/index.php и admin/index.php; config/users.php (users.point и users.points удаляются), config/update.php; setup/index.php (ветка update6_3 и чистая установка), setup/sql/table.sql и table_update6_3.sql (_points, _order.uid); tests/Support/contract_probe.php, tests/SchemaUpdateValidationTest.php (имя после CONSTRAINT не считается таблицей) и тесты баллов.
- Готово: Баланс сохранён снимком, все остающиеся источники переведены по карте points.md, старые числовые helpers удалены без обёрток. Начисление за просмотр страницы удалено без замены (NOD-227); order начисляется при подтверждении администратором (NOD-234); показом баланса и страницы правил управляет points.active (NOD-232). Preflight обновления проверяет версию сервера БД и InnoDB у таблиц, участвующих в транзакциях Point. В старом getRatingView убрать награду сразу, не дожидаясь S05. HTTP adjust/сброс/auto_links, журнал и БД.

### S05 — Rating — подключение

- Зависит: S02, S03, S04.
- Читать: ratings.md целиком; 12 — потребители _rating.
- Файлы: core/system.php, core/helpers.php, index.php; admin/modules/ratings.php; modules/account/admin/index.php (массовый сброс оценок удаляется, NOD-228); config/ratings.php, config/update.php; plugins/system/slaed.js; rating-фрагменты поставляемых тем; setup/index.php (ветка update6_3) и setup/sql/table_update6_3.sql.
- Готово: getRatingService с account/forum/shop, POST/CSRF и nonce; preflight/manifest остатков и сроков, административная отмена. Отметка завершения — update.ratings по 12; до неё новые обработчики не пишут. Реальные HTTP на трёх целях, БД/логи, Point и voting неизменны.

### S06 — Field — класс и остающиеся потребители

- Зависит: S03, S03A.
- Читать: 05 — общий Field, реестр/значения; 06 — поля; 12 — преобразование Field и «Штатное обновление данных 6.3»; 13 — проверки Field.
- Файлы: core/classes/field.php; core/helpers.php; admin/modules/fields.php и шесть admin/lang (_FIELDS_BOOL, _FIELDS_INT, _FIELDS_DECIMAL); config/fields.php, config/update.php; формы/запись/вывод account/forum/order; setup/index.php (ветка update6_3), setup/sql/table.sql и table_update6_3.sql; тесты Field.
- Готово: Однозначный preflight всех строк до записи, устойчивые ключи, явная карта четырёх legacy-слотов, TEXT→MEDIUMTEXT, партии и повтор по manifest в storage/backup/update/fields. Определения публикует писатель setup, отметка — update.fields. Все остающиеся формы используют JSON, без двойного runtime-формата.

### S07 — Feed — класс и RSS-потребители

- Зависит: S03, S03A.
- Читать: 05 — Feed/транспорт; 09 — контракт Parser::getAttachList(); 11 — SSRF; 13 — RSS/Atom.
- Файлы: core/classes/feed.php, core/classes/parser.php (литералы и getAttachList); core/system.php (rss_read, rss_load); config/rss.php (bytes/timeout/redirects добавляются, temp удаляется); modules/rss/admin/index.php (поле шаблона удаляется) и modules/rss/index.php; admin/modules/blocks.php (два вызова); modules/account/index.php; setup/index.php и table_update6_3.sql (очистка сохранённого HTML RSS-блоков); tests/Unit/FeedTest.php.
- Готово: Канонический Markdown, безопасные литералы и полный Parser, resolve/get без внешней сети, 304/лимиты/redirect/DNS. Parser::getAttachList() создаётся здесь. rss_read удалён одновременно с переводом всех пяти остающихся вызовов; _blocks.content RSS-блоков очищается обновлением и заполняется Markdown при следующем refresh.

### S08 — Node — схема, DTO и загрузка

- Зависит: S06.
- Читать: 01; 02; 03 кроме ссылок на общие классы; 04; 05 — DTO/контекст/ошибки.
- Файлы: setup/sql/table.sql (включая _node_publish) и setup/sql/table_update6_3.sql; core/classes/node/{entity,type,typeinput,input,relation,asset,target,context,status,exception,extension,load}.php; core/classes/node/ext/load.php; core/system.php; NodeModelTest.php, tests/Support/node_probe.php.
- Готово: Схема чистой установки/обновления по исполняемому DDL 03 с именованными индексами и ограничениями; родительские таблицы объявлены раньше дочерних, без отключения FOREIGN_KEY_CHECKS. _admins.modules TEXT получается правкой существующего MODIFY в table_update6_3.sql, второй MODIFY той же колонки не добавляется. Readonly-модели и загрузка без Composer. Карта load.php перечисляет только классы, чьи файлы уже существуют; каждый следующий этап добавляет строку вместе со своим файлом. Фабрика расширений закрыта: до S14/S15 её карта пуста, пустой ключ даёт null, любой иной — отказ; классы support/sync появляются на S14/S15, пустых файлов не создавать.

### S09 — NodeQuery — чтение

- Зависит: S08.
- Читать: 03 — индексы; 05 — чтение/списки/типы/выборки; 06 — «Права чтения»; 07 — «Полный bootstrap виртуального типа»; 11 — SQL-бюджеты; 13 — чтение.
- Файлы: core/classes/node/query.php и строка карты load.php; core/system.php (getNodeContext); tests/Unit/NodeQueryTest.php.
- Готово: Одиночные/смешанные цели, Field, ACL, поиск/главная/дерево/sitemap/deadline. Право чтения категорий вычисляется в PHP по одной предвыборке категорий типа на экземпляр NodeQuery, без is_acess() и глобальных переменных; SQL наполнения NodeContext.groups принадлежит bootstrap и в бюджет Node не входит. Предикаты count/list одинаковы; 3/7 SQL без категорий, 4/7 с категориями, и отсутствие N+1. Расширения проверяются через интерфейс тестовыми реализациями, не production-заглушками.

### S10 — NodeService — типы и настройки

- Зависит: S03, S05, S06, S09.
- Читать: 05 — запись типов; 06 целиком; 11 — конфигурация/файлы; 13 — типы.
- Файлы: core/classes/node/service.php, core/classes/node/query.php (getNodeTypeExport) и строки карты load.php; config/node.php, fields.php, uploads.php, ratings.php; общие admin/modules/fields.php, uploads.php, ratings.php; admin/modules/admins.php (filterAdminmods и редактор прав принимают node и node-<name> зарегистрированных типов); admin/modules/config.php и setConfigRestore() без аргумента в core/system.php (Node-proof во входе восстановления S03: независимая проверка БД вместо отказа why = proof); NodeConfigTest.php.
- Готово: Четыре операции типа, восемь свойств ввода, версия и согласованный пакет. Экспорт — NodeQuery::getNodeTypeExport(), импорт — NodeService::addNodeTypeImport(); клонирование и создание из профиля являются их композицией, JSON-кодек и валидатор формата живут в этих двух методах. Восстановление сбоя и запрет удаления занятого типа через сервис. Сохранение прав администратора не стирает ключи node-<name>. HTTP Node ещё не заявлять готовым.

### S11 — NodeService — материалы и ресурсы

- Зависит: S04, S09, S10.
- Читать: 03 — состояния/связи/ресурсы; 05 — запись/счётчики/ресурсы; 06 — workflow; 11 — locks; 13 — запись.
- Файлы: core/classes/node/service.php; admin/modules/categories.php; core/system.php (addNodePublishTask, getSchedulerJob, addSchedulerSystemJob; штатные upload helpers — право node-<name> вместо is_moder старого модуля), config/scheduler.php и дополнение заданий в setup/index.php; tests/Unit/NodeServiceTest.php.
- Готово: Создание/preview/изменение/состояния/удаление, категории, дерево, assets/link, жалоба, counters и Point. Версии, общий порядок lock, конкурентный цикл, откат; _node_publish и обработка наступившей публикации без ранних/двойных баллов. Задание регистрируется во всех четырёх местах планировщика; результат использует его статусы success|failed. Модератор node-<name> проходит getUploadTakenFile() и остальные upload helpers. Проверяется сервис на изолированной БД и штатный запуск задания.

### S12 — Файлы и кеш Node

- Зависит: S02, S10, S11.
- Читать: 07 — asset/attach/preview; 09 — файловый менеджер/выдача; 11 — кеш/время/locks; 13 — файлы.
- Файлы: core/system.php (getFileStream; upload helpers уже переведены на S11); core/classes/filemanager.php, cache.php; интеграция NodeQuery deadline; тесты файлов/кеша.
- Готово: Защита всех uploads/<type>, владелец/модератор node-<name>, GET/HEAD/Range/304, приватный preview без строки материала. Прямого режима отдачи нет ни у одного типа (NOD-206). SQL-бюджет построения кеша 4/8 без категорий и 5/8 с категориями, попадание 0. Полные маршруты проверяются на S13.

### S13 — Node — HTTP и представление

- Зависит: S08–S12.
- Читать: 04 — дерево модуля; 05 — NodeView; 07; 08; 13 — HTTP/браузер.
- Файлы: index.php, admin/index.php (ворота и меню для node и node-<name>, ссылка «на сайт»), core/system.php (getModuleName; проверка маркера конфигурации до HTML-кеша — типы из marker.types публично недоступны до восстановления, 06 → «Заметки реализации S03»), core/helpers.php (getModuleNavi, getTplModuleSelect); blocks/modules.php; core/classes/template.php (checkTemplateFile для fallback режима); core/classes/node/view.php и строка карты load.php; modules/node/index.php, admin/index.php, lang/, admin/lang/; node-partials и восемь node-фрагментов тем; общий компонент повторяемых строк в plugins/system/slaed.js; NodeRouteTest.php.
- Готово: Обычный/home/admin/AJAX bootstrap, язык/layout, формы редактора/файлов, POST/CSRF/409 и права. Полный закрытый набор admin-ops из 07, включая typestatus, typedelete, clone, export, import и report; восстановление конфигурации остаётся входом S03. Модератор только с node-<name> проходит ворота админки и видит только свой тип. Навигация, выбор главной и имя модуля знают типы Node без списка имён в общем коде. NodeTypeInput имеет восемь свойств. Реальные создание типа/материала/preview/файлы; БД, конфиги и логи.

### S14 — Комментарии и NodeSupport

- Зависит: S04, S11, S13.
- Читать: 05 — NodeExtension/NodeSupport; 09 — комментарии; 10 — поддержка; 13 — support.
- Файлы: общий владелец комментариев core/classes/comment.php и его HTTP; core/user.php (лента комментариев профиля); admin/modules/comments.php; core/classes/node/ext/support.php и строка карты ext/load.php; NodeService/Node-маршруты/фрагменты; тесты поддержки.
- Готово: Цель Node в комментариях, comnum и Point; приватное обращение/ответ, состояние ожидания, права автора/модератора, version и откат. Comment создаётся как сейчас и лениво собирает NodeQuery/NodeService из своего Database и общего getNodeContext() только для цели node.<name>. getUserList(), getAdminList() и лента профиля проверяют видимость цели текущему зрителю: приватные ответы поддержки не попадают в чужие списки и отрывки. Эта интеграция готова до испытания NodeSupport.

### S15 — NodeSync

- Зависит: S07, S11, S13.
- Читать: 05 — NodeSync; 10 — внешняя синхронизация; 13 — sync.
- Файлы: core/classes/node/ext/sync.php и строка карты ext/load.php; core/system.php (addNodeSyncTask, getSchedulerJob, addSchedulerSystemJob), config/scheduler.php и дополнение заданий в setup/index.php; admin/modules/scheduler.php (настройка limit системного задания); Node-маршрут sync; тесты.
- Готово: Один источник, ручной и плановый запуск nodesync по 10, сеть до транзакции, повторная версия/URL, последнее тело при ошибке, отсутствие сети в просмотре.

### S16 — Остальные интеграции Node

- Зависит: S04, S05, S09, S13–S15.
- Читать: 05 — цели/счётчики/опрос; 09 целиком; 11 — locks; 13 — интеграции.
- Файлы: core/system.php, core/helpers.php, core/user.php, index.php; владельцы рейтинга/избранного/опроса/поиска/главной/RSS/sitemap/SEO, включая выбор типов в modules/sitemap/admin/index.php и modules/search/admin/index.php; блоки: blocks/node.php, admin/modules/blocks.php, колонка _blocks.param в setup/sql/table.sql и table_update6_3.sql; тесты.
- Готово: Node подключён к общему Rating без Point; остальные владельцы используют Query/Service и расширение каждого типа. Единственный файловый блок blocks/node.php получает тип, режим и лимит из параметров экземпляра _blocks.param (NOD-229); главная — список типа, выбранного штатным домашним модулем, отдельного home-контракта нет. Проверить удаление poll, смешанные типы, видимость/кеш и реальные HTTP. Комментарии уже подключены S14.

### S17 — Все десять профилей

- Зависит: S10–S16.
- Читать: 06 — профили; 08 — режимы; 13 — состав выпуска.
- Файлы: modules/node/profiles/<name>.json — десять профилей в формате экспорта; setup/index.php и setup/sql/insert.sql (чистая установка создаёт десять активных типов и стартовый материал, NOD-230); config/node.php, fields.php, uploads.php, ratings.php как результат установки; node-фрагменты; тесты профилей.
- Готово: Общий валидатор и HTTP: news/docs/files первыми как эталоны, затем pages/faq/jokes/links/media/help/content. Все десять входят в выпуск. links/media без расширений; help= support, content=sync. Чистая установка проводит каждый профиль через NodeService::addNodeTypeImport() и включает тип; обновление 6.3 типов не создаёт (NOD-200). Приёмка десяти штатных имён идёт на чистой установке: на стенде каталоги uploads/<name> удалённых модулей с пользовательскими файлами блокируют одноимённый тип по NOD-200 до отдельной явной очистки, которую этот этап не выполняет.

### S18 — Целевая поставка и итоговая приёмка

- Зависит: S01–S17.
- Читать: 01; 11 — бюджеты; 12 — граница данных; 13 целиком.
- Файлы: setup; tools/node-profile.php; tools/ui-shots.json и остальные инструменты под маршруты Node; документация выпуска.
- Готово: Контроль, что после S03A в дереве не осталось кода, DDL и регистраций девяти модулей; без fallback в исполнении Node, без импорта или удаления данных стенда. Чистая установка/обновление, 100000 материалов, EXPLAIN, полная HTTP/гейты и логи.

## Общая приёмка

Каждый этап с кодом: php -l затронутых PHP, php vendor/bin/phpunit, php vendor/bin/phpstan, php vendor/bin/php-cs-fixer check и npm run ui:gates по правилам проекта. Перед первым визуальным изменением — npm run ui:before, после — npm run ui:after. Использовать execute-test-suite; для SQL — secure-database-access, форм/файлов — secure-inputs-and-forms, шаблонов — manage-slaed-templates, реального браузера — browser-debugging.

Для HTTP-записи подтвердить интерфейс и постоянные данные; после административной записи прочитать storage/logs/error_php.log, error_sql.log, error_site.log. Сервисный тест не выдаётся за HTTP. Обычные тесты работают на изолированной MariaDB/временном файловом корне; большой профиль запускается отдельно на S18. Никаких пустых тестовых файлов заранее. Канонический DDL — setup/sql/table.sql. Штатное обновление — setup/sql/table_update6_3.sql и ветка update6_3 в setup/index.php, версия 6.3.0 (NOD-225); протокол отметок, manifest и возобновления — в 12-migration.md.

Опоры, подтверждённые сверкой с деревом 2026-09-19: getSqlbatch() в core/admin.php делит SQL-файл по `;` вне кавычек; InsertValidationTest пропускает строки CONSTRAINT; Backup отключает foreign_key_checks на время восстановления; автозагрузчика нет — классы подключаются списком require_once в core/system.php; UnusedCodeAuditTest только считает; образцы изолированной БД — tests/Support/privat_class_probe.php и backup_probe.php; стенд — MariaDB 11.7.2, все таблицы InnoDB. DDL плана на сервере ещё не исполнялся: каждый этап исполняет свой DDL на изолированной БД.

Под «владельцами» в карточке понимаются реальные найденные вызовы заменяемого API в целевом составе. Перед изменением зафиксировать точные пути в передаче этапа. Исторические файлы могут быть gitignored: проверять физические пути, а не только rg --files. Например, форма пользователей — modules/account/admin/index.php.

Накопленная промежуточная реализация до S18 не является готовой поставкой: старые девять модулей удаляются на S03A, не адаптируются и не получают runtime-совместимость; между S03A и S13 стенд работает без контентных разделов. Сохранение данных при обновлении Point/Rating/Field не означает импорт старого контента в Node.
