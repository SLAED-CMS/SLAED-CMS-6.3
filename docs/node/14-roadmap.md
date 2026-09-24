# Реализация по отдельным окнам

Исполнитель: Claude Fable 5.1. Одно окно — один этап S00–S18, включая вставной S03A, затем подэтапы S19.1–S19.8 исправлений по аудиту реализации. Окно запускается одной командой «Работай по плану docs/node/PROGRESS.md»; текущий этап и протокол окна определяет [PROGRESS.md](PROGRESS.md). Порядок: **S00 — правка плана по аудиту готовности, затем Point как класс, затем Rating как класс**; девять старых модулей удаляются этапом S03A до первого подключения (NOD-224). Реализация сейчас не выполняется.

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

### S19 — Исправления по аудиту реализации

Аудит 2026-09-24 проверил S00–S18 (коммиты 7166b365..70224f65) в семи срезах против документов 01–13, points.md, ratings.md и .rules/*. Находки ниже — дефекты кода относительно действующих контрактов, кроме отмеченных «развилка»: там контракт молчит, и окно задаёт вопрос пользователю через AskUserQuestion до первой правки. Строки указаны по 70224f65; окно ищет место по имени функции и сверяет находку с кодом перед правкой — неподтверждённая находка записывается в передачу, а не исправляется. Одно окно — один подэтап S19.1–S19.8. Проверки на момент аудита: phpstan без ошибок, php-cs-fixer 0, ui:gates 232 теста, полный phpunit 1455 тестов с одним давним провалом CommentIsolationTest.

### S19.1 — Безопасность входа и вывода

- Зависит: S18.
- Читать: .rules/global.md — Security baseline и Template boundary; 08 — raw-границы; 09 — Поиск; points.md — Жизненный цикл; 11 — Безопасность.
- Находки:
  - Хранимый XSS: modules/search/index.php:255 отдаёт `label_html => filterTextHighlight($row['title'], ...)`, заголовок Node хранится необработанным (modules/node/index.php:190 читает `raw`, `NodeService::checkText()` теги не снимает), `filterTextHighlight()` (core/system.php:2194) теги сохраняет. Экранировать заголовок до подсветки, как `getFavoriteList()` в core/user.php.
  - admin/modules/fields.php `save()`: `checkSiteToken()` принимает GET и токен из адреса; каждая область строится только из POST, отсутствующая область сохраняется пустой — GET с токеном или обрезка по `max_input_vars` стирает определения account, forum, order и типов Node. Перевести на `checkAdminPost()` и записывать только области, присутствие которых подтверждено формой (скрытый маркер области); отсутствующая область — отказ, не пустой набор.
  - admin/modules/fields.php `save()` (стр. 199–207, 93–94): неизменность имени, типа и ключей вариантов сохранённого поля держится только атрибутом `readonly`. Сервер должен отклонять смену имени и удаление или переименование ключа варианта у сохранённого поля (05 — заметки S06).
  - modules/node/admin/index.php `delete()` (540–548): id не связан с присланным типом, в отличие от `status()`; добавить ту же предварительную проверку `getNodeContent($id, $type)`.
  - core/user.php:1325–1329: ветка избранного не для Node принимает любой `mod` и `id` без проверки существования цели и начисляет `favorite`; ограничить `mod` закрытым списком владельцев и проверять цель.
  - admin/modules/categories.php `addsave()` (382–414): категория типа Node создаётся сырым INSERT в обход NodeService — без прав `node-<type>`, без блокировки строки типа, без guard, родитель не проверяется на тот же `modul`. Создание категории Node — через сервис, как save/change/delete.
  - Состояние по GET с токеном в адресе: modules/shop/admin/index.php `clientset` (207–208), modules/order/admin/index.php (181–183) меняют статус и баллы. Перевести на POST с `checkAdminPost()`.
  - modules/order/admin/index.php:181–189: награда срабатывает на 0→N вместо 0→1 по карте владельцев points.md.
- Файлы: modules/search/index.php; admin/modules/fields.php; modules/node/admin/index.php; core/user.php; admin/modules/categories.php; core/classes/node/service.php (создание категории); modules/shop/admin/index.php; modules/order/admin/index.php; соответствующие фрагменты admin-шаблонов.
- Готово: HTTP — поиск по материалу с `<img onerror>` в заголовке выводит текст; GET на `name=fields&op=save` отказан без записи; POST без одной области отказан, config/fields.php байт в байт; подмена ключа варианта отклонена; delete с чужим типом — 404 без удаления; избранное с выдуманным `mod` — без строки и без баллов; категория Node без права типа — 403. Тесты на каждый пункт; журналы ошибок после админ-записей.

### S19.2 — Обновление 6.3

- Зависит: S19.1.
- Читать: 12 — «Штатное обновление данных 6.3»; 03 — Критерии готовности; update-разделы points.md, ratings.md, 06 — Поля; UPGRADING.md.
- Находки:
  - Сайт не закрыт на время обновления: setup/index.php:886 пишет `close=1` в global.php, а config/local.php удаляется только в строке 1074; `getConfig()` доверяет local.php по версии (core/system.php:42–50), поэтому DDL и блоки Point/Rating/Field идут на открытом сайте. Удалять local.php сразу после каждой публикации конфигурации установщиком (писатель `setConfigFile()` в setup/index.php) и проверить закрытие до DDL.
  - Ошибка DDL не останавливает ветку (setup/index.php:1045–1048): блоки данных идут на половинчатой схеме, `getSqlRow(false)` (351) падает TypeError. Любая ошибка `getSqlFile()` — стоп с отчётом до первого блока данных.
  - Потеря получателей рассылки (setup/index.php:958–964, 1057–1069; table_update6_3.sql:1700): `mails` держится в памяти между DROP колонки и INSERT в `_mail`, вставка не идемпотентна. Переносить до DDL или через снимок с отметкой.
  - Preflight пускает MariaDB от 10.2.1 (setup/index.php:323), а `RENAME COLUMN` и `RENAME INDEX` (table_update6_3.sql:73, 378) требуют 10.5.2; поправить порог в коде, 12-migration.md и UPGRADING.md.
  - Preflight InnoDB (setup/index.php:326) не включает `_categories`, которую NodeService блокирует `FOR UPDATE`.
  - Конфигурация и db.php пишутся и файл админки переименовывается (759–776) до preflight (884): отказ preflight оставляет их изменёнными. Preflight — до первой записи.
  - config/newsletter.php перезаписывается значениями по умолчанию при каждом прогоне (1043).
  - UPGRADING.md:92–103 велит `mysql < setup/sql/table.sql` и ручной `table_update*.sql` с оставшимся TODO — противоречит NOD-225 (единственный вход — ветка установщика).
  - Развилка: формат конфигурации 6.2 Pro. Установщик читает только файлы `return [...]` (setup/index.php:11–12, 348, 369, 423, 646, 945), а дерево до 03803ad6 поставлялось с `$conf = array(...)`; с перенесённым config/ сайта ветка падает или сбрасывает модули и поля. Сначала подтвердить формат реального релиза 6.2 Pro; затем пользователь выбирает — преобразователь формата в ветке или документированный ручной шаг. Отсутствующий config/points.php (369) читать с проверкой `is_file()`.
  - Снимки storage/backup/update/* с IP гостей не очищаются и защищены только .htaccess; UPGRADING без правила nginx для storage/.
- Файлы: setup/index.php; setup/sql/table_update6_3.sql; UPGRADING.md; docs/node/12-migration.md; tests/Support/update_probe.php и update-тесты.
- Готово: прогон update6_3 на копии стенда — гость получает 503 с первого шага до конца ветки; искусственная ошибка DDL останавливает ветку до блоков данных; обрыв после DDL не теряет рассылку; повтор без изменений снимков; preflight на 10.4 отказывает без единой записи. Прогон на MySQL 8 и зафиксированный результат.

### S19.3 — Целостность данных Node, Point и Rating

- Зависит: S19.2.
- Читать: points.md — Жизненный цикл, Отложенная публикация; ratings.md — Результаты; 06 — Дополнение восстановления; 09 — Комментарии; 12 — Решение; NOD-200.
- Находки:
  - Развилка: строки `_comment`, `_favorites`, `_rating`, `_voting` с `modul` старых модулей остаются в базе, а `NodeService::checkNewName()` (service.php:154–165) блокирует имя только по категориям и uploads/<name>. Новый тип `news` получает у материала id 1 комментарии старой новости id 1, старые адреса `name=news&op=view&id=N` открывают чужой материал. Варианты для пользователя: запрет имени при наличии старых строк, чистка старых строк при создании типа, смещение AUTO_INCREMENT. Решение вносится в 12-migration.md до кода.
  - `Comment::deleteTarget()` (comment.php:572–584) физически удаляет комментарии без компенсации награды `comment`; вызовы — modules/node/admin/index.php:548, modules/shop/admin/index.php:756, modules/voting/admin/index.php:293, 319.
  - Удаление комментариев материала идёт после COMMIT `deleteNode()` вне транзакции (modules/node/admin/index.php:544–548); сбой оставляет сирот.
  - Аннулирование голоса Node: адаптер записи отвечает `false` при выключенном рейтинге типа (core/system.php:6047), неактивный тип даёт `unavailable` (6018); аннулирование вызывает `updateNodeAction(..., 'rate')`. По ratings.md оно работает в контексте super, включая отключённый материал; только физическое отсутствие — `unavailable`.
  - Отложенная публикация при выключенном Point: `addEvent()` отвечает `false`, задача переносится каждые 60 с бесконечно (service.php:1410–1411). Выключенная награда — задача обработана.
  - core/system.php:2818–2823: сбой записи `phase=committed` после COMMIT операции Node восстанавливает `old` и снимает маркер; база и конфигурация расходятся навсегда. С доказательством в базе — довести `new` или оставить маркер. Там же (2824): `uncertain` без доказательства должен оставлять маркер.
  - modules/account/admin/index.php:546: `adjust:<random>` — повторная отправка формы применяет поправку дважды; 496 молча снимает теги с `pnote` вместо отказа; длинный `pnote` отклоняется Point уже после сохранения профиля. 700: ключ сессии сброса без срока — давно брошенный сброс продолжается со старого курсора.
  - modules/forum/index.php:849: новое сообщение ищется как «последнее этого uid в категории», а не через `getSqlLastId()`.
  - `updateNodeStatus()` (service.php:1192) не сверяет `expires` с новым временем публикации, в отличие от create и update (898).
  - Дубли адреса ссылки внутри одного материала (service.php:677–718, 938–941) после записи блокируют каждое `updateNodeType()` через `checkTypeAssets()` (321–327).
- Файлы: core/classes/node/service.php; core/classes/comment.php; core/system.php; modules/node/admin/index.php; modules/account/admin/index.php; modules/forum/index.php; docs/node/12-migration.md.
- Готово: тесты класса на каждый сценарий (компенсация при удалении цели, аннулирование при выключенном рейтинге и неактивном типе, задача при выключенном Point, сбой журнала после COMMIT); HTTP — повтор формы adjust без двойной поправки; постоянные данные сверены SQL-запросом.

### S19.4 — Кеш и блокировки

- Зависит: S19.3.
- Читать: 11 — «Единый порядок блокировок», «Завершённый протокол HTML-кеша»; 05 — Инвалидация кеша; ratings.md — заметки S02.
- Находки:
  - Запись комментариев на цели Node без `Cache::getWriteGuard()` до BEGIN (comment.php:433 addComment, 468 updateComment, 521 setStatus, 566 deleteComment, 583 deleteTarget); `comnum` меняется, ранний bump в админ-запросе (pdo.php:151) подавляет финальный — страница со старым `comnum` публикуется под новым поколением.
  - Ключ блокировки не канонический (filemanager.php:314–315: без realpath и нормализации регистра), операции FileManager берут только `dirname(file)` (169, 224, 242, 273, 405) без корня типа; NodeService блокирует `UPLOADS_DIR.'/'.$name` (service.php:383, 838). Загрузка в подпапку идёт во время `deleteNodeType()`.
  - `Cache::addEpoch()` (cache.php:196–198) превращает нечисловое содержимое журнала в `intval+1`, поколение идёт назад.
  - `NodeQuery` (query.php:378–380) читает marker.json через `is_file()` и `file_get_contents()` без защиты от удаления между вызовами — кратковременный 404 всех типов.
  - `setConfigFile()`: `$busy` и счётчик блокировки остаются при исключении вне try (system.php:2739, 2781, 2817, 72); `file_get_contents()` снимка с ошибкой даёт `''` и удаляет живой источник (2697); нечитаемый источник журналируется как «не существовал» (2748); при журнале `journal`/`backup` без local.php строится смешанный снимок (58).
  - Сдвиг порядка блокировок пользователей в shop (modules/shop/admin/index.php:406–408): компенсация `$ouid`, затем награда `$uid` без сортировки.
  - Point блокируется раньше строк расширения в `addNode()` (service.php:1131–1133) и `deleteNode()` (1216–1223) вопреки порядку 11.
- Файлы: core/classes/comment.php; core/classes/filemanager.php; core/classes/cache.php; core/classes/node/query.php; core/classes/node/service.php; core/system.php; modules/shop/admin/index.php.
- Готово: CacheTest, FileManagerLockTest, ConfigFileTest дополнены сценариями гонки и исключения; блокировка корня исключает загрузку в подпапку во время удаления типа; поколение не уменьшается на испорченном журнале.

### S19.5 — Рендер и представление

- Зависит: S19.4.
- Читать: 08 целиком; 07 — SEO и canonical; 09 — Комментарии, Пользователи; .rules/global.md — Template boundary.
- Находки:
  - `NodeView::getTextHtml()` (view.php:46) включает доверенный рендер всего текста по наличию `[usehtml]`/`[usephp]`: модератор добавляет тег в чужой текст — пользовательский HTML того же текста исполняется. Доверие должно следовать автору текста, а не содержимому; `getPlainText()` (52) переносит текст `<script>` в `intro`.
  - og:image внешней обложки — `homeurl/https://...` (modules/node/index.php:627; источник view.php:79).
  - blocks/node.php:49 всегда рисует `node/block`, пропуская `fragments/node/<mode>/block.html` из 08 «Выбор представления типом».
  - Постер берётся из первой скрытой роли только с изображениями, а не из роли `poster` (modules/node/index.php:411).
  - Одобрение ответа поддержки вызывает расширение в контексте модератора и повторно после скрытия (comment.php:510; support.php:201) — владелец получает письмо о собственном ответе.
  - Живой виджет рейтинга читает сырые `$conf['ratings']` и не учитывает `guests` и собственную цель (core/helpers.php:1106–1118); `getRating()`/`canvote` не вызывается; `$res['average']` отбрасывается (core/system.php:6094).
  - Шаблонная граница: `sl_mod_` строится в PHP (index.php:57); `input_attr` строкой атрибутов (modules/node/index.php:315).
  - plugins/system/slaed.js:1884–1888 — запасной ключ доставки на `Math.random()`; 1909–1910 — тело ошибки через `innerHTML`.
- Файлы: core/classes/node/view.php; core/classes/node/service.php (отметка доверенного автора); modules/node/index.php; blocks/node.php; core/classes/comment.php; core/classes/node/ext/support.php; core/helpers.php; index.php; plugins/system/slaed.js; фрагменты lite.
- Готово: NodeRouteTest и тест view на смешанный текст модератора и пользователя; og:image с внешней обложкой; блок с переопределённым шаблоном режима; ui:gates; пара ui:before/ui:after, если менялись фрагменты или CSS.

### S19.6 — Производительность чтения

- Зависит: S19.5.
- Читать: 03 — Индексы материала, Дополнительные категории, Критерии утверждения; 11 — Производительность.
- Находки:
  - `setNodeCategory()` (query.php:575, счётчик 926) — `OR EXISTS` исключает оба индекса категорий; нужен UNION двух индексных ветвей с дедупликацией по id.
  - Сортировки `title` и `updated` при `pinned` всегда filesort: индексы `title` и `admin` без `pinned` (query.php:656–671).
  - Предзагрузка категорий не читает `pview` (query.php:510); сравнение `modul` в SQL без учёта регистра против строгого в `checkRowCat()` (529).
  - `getNodeDeadline()` (query.php:940–942) сканирует все опубликованные строки типа.
  - Журнал баллов — `COUNT(*)` по всей таблице и `LIMIT :offset` (admin/modules/groups.php:295–297).
  - Бюджет SQL категорийного списка в 11 (4) расходится с фактом и заметкой S09 (5).
- Файлы: core/classes/node/query.php; setup/sql/table.sql и table_update6_3.sql при изменении индексов; admin/modules/groups.php; docs/node/03, 11.
- Готово: tools/node-profile.php на 100000 материалов — EXPLAIN категорийного списка без полного скана, бюджеты 11 соблюдены; NodeQueryTest; /schema совпадает с table.sql.

### S19.7 — Остатки девяти модулей и потерянные функции

- Зависит: S19.6.
- Читать: 09 целиком; .rules/constants.md.
- Находки:
  - Профиль потерял публикации пользователя: `getProfileModules()` — только comm и forum (core/user.php:1208–1213), вкладки и счётчики профиля (modules/account/index.php:330 и далее) без Node; ссылка `help` кабинета снята без замены на тип поддержки (core/user.php:341 и далее).
  - templates/lite/partials/menu.html:13–80 и site-footer.html:7 ссылаются на `name=content/files/news/faq`.
  - Сиротские константы `_MDIRECTOR`, `_MROLES`, `_MTITLE`, `_MYEAR` в modules/search/lang/*.php:7–10.
  - modules/voting/admin/index.php:32 предлагает только shop; сохранение опроса с `modul='news'` обнуляет `modul` и публикует его в списке и sitemap.
  - modules/rss/index.php:15 по умолчанию `shop` даже при выключенном магазине; `getRssChannel()` (core/user.php:1494) отдаёт `_products` без `is_active('shop')`; элементы Node без `<dc:creator>` (1515–1526).
  - modules/search/admin/index.php:69 показывает `node` сломанным модулем.
  - Админ-панель: счётчики материалов категорий Node (core/admin.php:391) и ожидающих модерации (354 и далее) не заменены на Node.
  - core/user.php:505 — имя расширения `support` в общем коде вместо признака расширения.
- Файлы: core/user.php; modules/account/index.php; templates/lite/partials/menu.html, site-footer.html; modules/search/lang/*, modules/search/admin/index.php; modules/voting/admin/index.php; modules/rss/index.php; core/admin.php.
- Готово: HTTP — все ссылки меню и подвала 200; профиль показывает материалы Node автора; LanguageValidationTest и поиск сирот чисты; ui:before/ui:after при правке partials.

### S19.8 — Правила кода

- Зависит: S19.7.
- Читать: .rules/global.md — Naming, Comments, PHP and code style; .rules/constants.md.
- Находки:
  - 47 констант `_NODE_*` длиннее 12 символов (modules/node/lang/*, modules/node/admin/lang/*) — переименовать с переносом всех вызовов.
  - admin/index.php:147–151: русский стартовый текст материала в PHP — в константы шести локалей или данные установки.
  - Строки длиннее 180: core/classes/node/context.php:30, core/classes/filemanager.php:312; длинные SQL modules/account/admin/index.php:531, 535, 541 и modules/account/index.php:211.
  - Комментарии в теле функций: core/helpers.php:1240, plugins/system/filemanager.js:1128.
  - Лишние приведения: admin/modules/config.php:1058, admin/modules/ratings.php:92, service.php:338, 438, 1031–1050, support.php:106, 122; магическое 4294967295 в support.php:295 при существующей `MAXINT`.
  - Комментарии над константами и свойствами внутри классов point.php:16–50, rating.php:15–29, cache.php:14–16; двойная пустая строка admin/modules/ratings.php:8–9.
  - Неатомарное сохранение блока: admin/modules/blocks.php:754 обновляет `param` отдельно до основного UPDATE.
  - Нерешённые находки прежних окон из передачи S18 — (а)–(я), label-audit-baseline — сверить с кодом; живые закрыть здесь или вынести пользователю.
- Файлы: по списку находок.
- Готово: php -l, phpstan, php-cs-fixer check, полный phpunit, ui:gates; поиск по дереву — ни одной константы длиннее 12 и ни одного старого имени.

## Общая приёмка

Каждый этап с кодом: php -l затронутых PHP, php vendor/bin/phpunit, php vendor/bin/phpstan, php vendor/bin/php-cs-fixer check и npm run ui:gates по правилам проекта. Перед первым визуальным изменением — npm run ui:before, после — npm run ui:after. Использовать execute-test-suite; для SQL — secure-database-access, форм/файлов — secure-inputs-and-forms, шаблонов — manage-slaed-templates, реального браузера — browser-debugging.

Для HTTP-записи подтвердить интерфейс и постоянные данные; после административной записи прочитать storage/logs/error_php.log, error_sql.log, error_site.log. Сервисный тест не выдаётся за HTTP. Обычные тесты работают на изолированной MariaDB/временном файловом корне; большой профиль запускается отдельно на S18. Никаких пустых тестовых файлов заранее. Канонический DDL — setup/sql/table.sql. Штатное обновление — setup/sql/table_update6_3.sql и ветка update6_3 в setup/index.php, версия 6.3.0 (NOD-225); протокол отметок, manifest и возобновления — в 12-migration.md.

Опоры, подтверждённые сверкой с деревом 2026-09-19: getSqlbatch() в core/admin.php делит SQL-файл по `;` вне кавычек; InsertValidationTest пропускает строки CONSTRAINT; Backup отключает foreign_key_checks на время восстановления; автозагрузчика нет — классы подключаются списком require_once в core/system.php; UnusedCodeAuditTest только считает; образцы изолированной БД — tests/Support/privat_class_probe.php и backup_probe.php; стенд — MariaDB 11.7.2, все таблицы InnoDB. DDL плана на сервере ещё не исполнялся: каждый этап исполняет свой DDL на изолированной БД.

Под «владельцами» в карточке понимаются реальные найденные вызовы заменяемого API в целевом составе. Перед изменением зафиксировать точные пути в передаче этапа. Исторические файлы могут быть gitignored: проверять физические пути, а не только rg --files. Например, форма пользователей — modules/account/admin/index.php.

Накопленная промежуточная реализация до S18 не является готовой поставкой: старые девять модулей удаляются на S03A, не адаптируются и не получают runtime-совместимость; между S03A и S13 стенд работает без контентных разделов. Сохранение данных при обновлении Point/Rating/Field не означает импорт старого контента в Node.
