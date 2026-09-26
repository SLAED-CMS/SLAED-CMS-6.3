# Реализация по отдельным окнам

Исполнитель: Claude Fable 5.1. Одно окно — один этап S00–S18, включая вставной S03A, затем подэтапы S19.1–S19.8 исправлений по аудиту реализации и S20.1–S20.8 по второму аудиту. Окно запускается одной командой «Работай по плану docs/node/PROGRESS.md»; текущий этап и протокол окна определяет [PROGRESS.md](PROGRESS.md). Порядок: **S00 — правка плана по аудиту готовности, затем Point как класс, затем Rating как класс**; девять старых модулей удаляются этапом S03A до первого подключения (NOD-224). S00–S19.8 выполнены (1334a08d); S20.1–S20.8 — исправления по второму аудиту реализации — выполнены 2026-09-25…26, план выполнен.

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

Аудит 2026-09-24 проверил S00–S18 (7166b365..70224f65) в семи срезах против 01–13, points.md, ratings.md и .rules/*; затем каждую находку повторно сверили с кодом и документами: ошибочные сняты, неточные исправлены, пропущенные добавлены. Строки указаны по 70224f65 (f9ecac63 менял только docs); окно ищет место по имени функции и перед правкой убеждается, что находка жива, — неподтверждённая записывается в передачу, а не исправляется. «Развилка» — контракт молчит или правка меняет хранение либо контракт: вопрос пользователю через AskUserQuestion до первой строки кода подэтапа, ответ — в документ-владелец. «Документ» — расходятся сами документы: код не трогается, правится документ-владелец. Буквы (а)–(я) — открытые находки окон S01–S18 (определены в PROGRESS.md коммитов 5d717167 и acb58916), разнесены по карточкам. Одно окно — один подэтап S19.1–S19.8. Проверки на момент аудита: phpstan без ошибок, php-cs-fixer 0, ui:gates 232 теста, полный phpunit 1455 тестов, один давний провал CommentIsolationTest.

### S19.1 — Безопасность входа и вывода

- Зависит: S18.
- Читать: .rules/global.md — Security baseline, Template boundary; 05 — заметки S06 (около стр. 222), политика доверенных тегов (около 1037); 08 — контракт NodeView и критерии; 09 — Поиск; points.md — Жизненный цикл и карта владельцев; 11 — Безопасность.
- Находки:
  - Развилка, первая по порядку: установщик открыт после установки (дефект старше плана, блокирует выпуск). setup.php в корне не закрыт .htaccess (правило `^setup/` его не касается), блокировки нет. `setup.php?op=config` выводит пользователя и пароль БД в `value` поля (setup/index.php:286–309); `op=save` переписывает config/db.php на любой сервер и переименовывает файл админки (744–776), `setup=update5_0` перехеширует пароли. README велит удалить setup.php, UPGRADING:107 требует его для обновления. Способ запирания (маркер установки, вход главного администратора, одноразовый ключ в файле) выбирает пользователь.
  - Хранимый XSS: modules/search/index.php:255 отдаёт `label_html => filterTextHighlight($row['title'], ...)`, фрагмент link выводит его сырым; заголовок Node читается `raw` (modules/node/index.php:190) и хранится необработанным; `filterTextHighlight()` (core/system.php:2194) сохраняет теги. Экранировать только строки Node в `getSearchNode()` (около 195): заголовки прежних владельцев уже проходят `filterHtml` фильтра `title` (core/security.php:820), общее экранирование дало бы двойное.
  - Там же, modules/search/index.php:235: фрагмент материала рендерится с `safe=false` — доверенный `[usephp]` главного администратора исполняется на странице поиска, `[block=]` рисуется. Режим — по правилу `NodeView::getTextHtml()`.
  - admin/modules/fields.php `save()` (184–231): `checkSiteToken()` принимает токен из адреса (core/security.php:694–697), каждая область строится только из POST. GET с токеном сохраняет account, forum, order пустыми (типы Node спасает отказ CONFLICT без `ver[]`); обрезка по `max_input_vars` стирает типы Node — скрытые `ver[]` идут первыми, области `node.*` последними. Перейти на `checkAdminPost()`; область записывается, только если форма подтвердила её целиком (маркер конца области или формы); неполная — отказ.
  - admin/modules/fields.php (93–94, 104): имя и ключи вариантов сохранённого поля защищены только `readonly`; тип закреплён сервером (202). Переименованное имя молча удаляет старое поле без флага drop. Сервер отклоняет смену имени и удаление или переименование ключа варианта (05, заметки S06).
  - core/classes/field.php:174 `checkFieldText()`: заголовок-константа проверяется `defined()` в текущем окружении; админка загружает admin/lang, поэтому admin-only константа проходит сохранение, а на сайте `getFieldRules()` (core/helpers.php:35) отвергает весь набор — область молча теряет все поля. Проверять по словарю сайта.
  - admin/modules/fields.php:25 `getFieldInput()`: целое ограничено 18 цифрами — допустимое 19-значное int64 в default, min, max отклоняется как `type`.
  - modules/node/admin/index.php `delete()` (536–549): id не сверяется с присланным типом, в отличие от `status()` (526). Прав не повышает (`deleteNode()` проверяет право на реальном типе), но `deleteTarget($type->name, [$id])` стирает комментарии другого материала A:id, а комментарии удалённого B:id остаются сиротами.
  - (щ) core/user.php:1325–1330 `addFavorite()` фиксированных модулей: любой `mod` и `id` без проверки цели и имени модуля, предел `favorites.favorites` не соблюдается; начисляется `favorite` (по умолчанию limit 20 за период, points.md:73). Закрытый список модулей и проверка цели.
  - admin/modules/categories.php `addsave()` (382–416): категория типа Node создаётся сырым INSERT — без прав `node-<type>`, без блокировки строки типа и guard, родитель не сверяется с `modul`; родитель не сверяется и в `NodeService::updateNodeCategory()` (service.php около 1469). Создание — через сервис.
  - (ф) сохранение и добавление категории сбрасывают `pview`…`pmod` к умолчаниям: `getVar('post', 'ppost[]', 'var', [])` теряет значения с `|` — касается и типов Node.
  - Изменение состояния по GET с токеном в адресе: shop `clientset` (205–208), `clientdel` (423–426), `partnerset` (865–868), `partnerdel` (1001–1004), `productops` (720–726); order `activate` (179–183) и `delete()` (159–162) — часть начисляет или снимает баллы. POST с `checkAdminPost()`.
  - modules/order/admin/index.php:181–188: `act` не проверяется, награда срабатывает на 0→N вместо 0→1 (points.md:236).
  - `NodeService::setTypeWrite()` (service.php:376–404): изменения типов и схемы не журналируются, журнал конфигурации удаляется по завершении (core/system.php:2709–2714); 11:48 «Административные изменения типов и схемы журналируются».
  - Документ: `NodeService::deleteNodePoll()` (service.php:1286) проверяет только `aid >= 1`; 05:948 требует права удаления опросов, 05:1141 — «администратора». Согласовать 05; в коде проверить право опросов (единственный вызов уже за правом voting — защита в глубину).
- Файлы: setup.php, setup/index.php (запирание); modules/search/index.php; admin/modules/fields.php; core/classes/field.php; modules/node/admin/index.php; core/user.php; admin/modules/categories.php; core/classes/node/service.php; modules/shop/admin/index.php; modules/order/admin/index.php; фрагменты admin-шаблонов этих форм.
- Готово: на установленном сайте `setup.php?op=config` и POST `op=save` отказаны без вывода секретов и без записи. HTTP: поиск по материалу с `<img onerror>` в заголовке выводит текст; GET `name=fields&op=save` с токеном отказан; POST с обрезанной областью отказан, config/fields.php и `_node_types.fields` байт в байт; смена имени и ключа варианта отклонены; admin-only константа в заголовке отклонена; delete с чужим типом — отказ без удаления; избранное с выдуманным `mod` — без строки и без баллов; категория Node без права типа — редирект с `_NODE_BAD` без строки; права категории с `|` сохраняются; GET каждого перечисленного действия shop и order отказан; order 0→2 без награды; операция типа оставляет запись журнала. Тест на каждый пункт; журналы ошибок после админ-записей.

### S19.2 — Обновление 6.3

- Зависит: S19.1.
- Читать: 12 — «Штатное обновление данных 6.3» (около 148–153); 03 — Критерии готовности; update-разделы points.md, ratings.md, 06 — Поля; UPGRADING.md.
- Находки:
  - Сайт не закрыт на время обновления: `close=1` пишется в global.php (setup/index.php:886), config/local.php удаляется только в 1074; `getConfig()` доверяет local.php по версии (core/system.php:42–50), index.php:12 читает close оттуда. Ручное удаление local.php (UPGRADING:155) не помогает — следующий запрос соберёт его с close=0. Контракт уже требует (12:151): удалять local.php после каждой публикации конфигурации установщиком.
  - Ошибка DDL не останавливает ветку (1045–1048; `getSqlFile()` только собирает вывод, 112–131). Без таблицы `getSqlRow(false)` молча отдаёт false, блок идёт дальше и пишет manifest и отметку поверх сломанной схемы. Любая ошибка DDL — стоп с отчётом до первого блока данных.
  - Рассылка: `mails` читается в память (958–964) до DROP колонки (table_update6_3.sql:1701), INSERT в `_mail` — в конце (1058–1069) и не идемпотентен; сбой между ними теряет получателей. Переносить до DDL или через снимок с отметкой.
  - Preflight пускает MariaDB от 10.2.1 (323), а `RENAME COLUMN` и `RENAME INDEX` (table_update6_3.sql:73, 378) требуют 10.5.2. Порог в коде, 12-migration.md:152, UPGRADING.md:40 и 113.
  - Список InnoDB preflight (326) без `_categories` (NodeService блокирует `FOR UPDATE`, service.php:1477, 1497) и `_voting` (удаление опроса — транзакция NodeService, modules/voting/admin/index.php:303–306).
  - Конфигурация и db.php пишутся, файл админки переименовывается (758–776) до preflight (884): отказ preflight оставляет их изменёнными. Preflight — до первой записи.
  - config/newsletter.php перезаписывается значениями по умолчанию при каждом прогоне (1043).
  - (р) при `xsync = 1` ветка удаляет запись presentation из config/modules.php и переписывает отступ `maildrain` в config/scheduler.php — сверить с кодом после S18.
  - (ю) `setup new` поверх дерева с типами в config/node.php.
  - UPGRADING.md:92–103 велит `mysql < setup/sql/table.sql` (с плейсхолдерами `{prefix}`) и ручной `table_update*.sql` с оставшимся TODO — противоречит 12:148 и NOD-225 (единственный вход — ветка установщика).
  - Развилка: формат конфигурации 6.2 Pro. Установщик читает только `return [...]` (setup/index.php:11–12, 348, 368, 369, 423, 646, 945). global.php хранил `$conf = array(...)` до fe03155e (2026-02-18), а до 03803ad6 файлы назывались `config/config_*.php`, поля — позиционные строки `$conffi[...]`; тегов релизов в git нет. Старый формат ломает ветку уже в 11–12 (`require` отдаёт 1, `array_merge` падает). Сначала установить формат реального архива 6.2 Pro, затем пользователь выбирает: преобразователь в ветке или ручной шаг в UPGRADING. users.php (368) и points.php (369) читать с `is_file()`.
  - Развилка: снимки storage/backup/update/* (terms.json содержит `g:<ip>`) не удаляются и защищены только .htaccess `deny from all`; правила nginx для storage/ в UPGRADING нет (есть только для uploads, 207–210). Срок хранения решает пользователь; правило nginx добавить в любом случае.
- Файлы: setup/index.php; setup/sql/table_update6_3.sql; UPGRADING.md; docs/node/12-migration.md; tests/Support/update_probe.php и update-тесты.
- Готово: прогон update6_3 на копии стенда — гость получает 503 от закрытия после preflight до конца ветки; искусственная ошибка DDL останавливает ветку до блоков данных, manifest и отметок нет; обрыв после DDL не теряет рассылку, повтор не дублирует; повторный прогон не меняет снимки; отказ preflight не меняет ни одного файла; newsletter.php сохраняет настройки. Порог версий — тестом preflight с подставленным `VERSION()` (10.4 — отказ, 10.5.2 — проход); прогон на MySQL 8 — на реальном сервере через `SLAED_PROBE_DB`, результат в передаче.

### S19.3 — Целостность данных Node, Point и Rating

- Зависит: S19.2.
- Читать: points.md — Жизненный цикл, Отложенная публикация, карта владельцев (около 247); ratings.md — Результаты (около 53); 05 — удаление (около 1106, 1141); 06 — Дополнение восстановления (около 940); 09 — Комментарии (около 207); 11 (около 42, 211); 12 — Решение; NOD-104, NOD-200.
- Находки:
  - Старые строки с `modul` удалённых модулей: `_comment` (комментарии Node — `modul` равен имени типа, comment.php:69) и `_favorites` (service.php:1223) остаются в базе, а `NodeService::checkNewName()` (service.php:154–165) блокирует имя только по категориям и uploads. Новый тип `news` получает у материала id 1 комментарии старой новости id 1. `_rating` и `_voting` не затронуты (голоса Node — область `node.<name>`, опросы связаны по id). Контракт уже задан (11:211): исторические записи с тем же `modul` очищаются отдельной операцией до повторной регистрации — `checkNewName()` отказывает, пока такие строки есть; операция очистки — в админке типов.
  - Развилка: старые адреса `name=<type>&op=view&id=N` после повторной регистрации типа открывают чужой материал (NOD-104 — без карты ID). Варианты — принять, смещение AUTO_INCREMENT, 410 для id ниже порога.
  - `Comment::deleteTarget()` (comment.php:572–585) удаляет комментарии без компенсации `comment`; вызовы — modules/node/admin/index.php:548, modules/shop/admin/index.php:756, modules/voting/admin/index.php:293, 319. Исключение points.md:247 — только темы форума; points.md:103, 212, 231 требуют компенсации. `getEventId()` требует открытой транзакции, поэтому удаление и компенсация идут в транзакции владельца, а не после COMMIT `deleteNode()` (modules/node/admin/index.php:544–548). Документ: 05:1106 и 05:1141 ставят удаление после COMMIT, 11:42 и 09:207 — в транзакции; согласовать.
  - Аннулирование голоса Node: чтение адаптера идёт через `NodeQuery::getNodeTarget()` с чтением `target` — только опубликованные даже для super (query.php:97, 614–623), поэтому голос отключённого материала — `unavailable`; адаптер записи отвечает `false` при выключенном рейтинге типа (core/system.php:6047), неактивный тип — `null` (6018); аннулирование вызывает `updateNodeAction(..., 'rate')` (6049). ratings.md:53: аннулирование в контексте super, включая отключённый материал; `unavailable` — только физическое отсутствие.
  - Та же причина: `NodeService::setAssetAction()` (service.php:1297) через чтение `target` отказывает модератору в действиях с ресурсами неопубликованного материала — только у типов с расширением.
  - Отложенная публикация: при невалидной конфигурации Point, включая намеренно закрытую область `[]` (point.php:235, points.md:198), или отказе `filterEvent` `addEvent()` отвечает `false`, задача переносится каждые 60 с бесконечно (service.php:1410–1411). При `active = 0` уже верно — `true` (point.php:239). Отказ по конфигурации — задача обработана с записью в журнал.
  - core/system.php:2822: сбой записи `phase=committed` после COMMIT операции Node вызывает `setConfigRestore('old')` без учёта доказательства в базе — база и конфигурация расходятся, маркер снят (06:940). С доказательством — довести `new` или оставить маркер.
  - modules/account/admin/index.php: `adjust:<random>` (546) — повторная отправка формы применяет поправку дважды; `strip_tags` по `pnote` (496) молча вместо отказа; длинный `pnote` отклоняется Point (546) после сохранения профиля (529–540). Проверить поправку до записи профиля; стабильный источник — ключ формы.
  - modules/forum/index.php:849: новое сообщение ищется как «последнее этого uid в категории», а не `getSqlLastId()`; неверный id уходит в источник `post:<id>` (868).
  - `NodeService::updateNodeStatus()` (service.php:1192) не сверяет `expires` с новым временем публикации, в отличие от create и update (898).
  - `NodeService::getAssetData()` (service.php:674–718) не отклоняет повтор адреса внутри одного ввода, `checkNodeRefs()` (938–941) сверяет только с другими материалами; после записи каждое `updateNodeType()` падает в `checkTypeAssets()` (316–328).
  - admin/modules/blocks.php `editsave()`: `param` обновляется отдельным запросом (754), остальная запись — цепочка UPDATE (775, 777, 789 и далее); 09 «Критерии готовности» требует одной транзакции для составной операции — вся запись в `setSqlBegin`.
  - query.php:529 `checkRowCat()` сравнивает `cmod` строго, SQL — без учёта регистра; практически недостижимо (имена типов строчные), выровнять по пути.
- Файлы: core/classes/node/service.php; core/classes/node/query.php; core/classes/comment.php; core/system.php; modules/node/admin/index.php; modules/account/admin/index.php; modules/forum/index.php; admin/modules/blocks.php; docs/node/05, 12.
- Готово: тесты класса — отказ регистрации имени при старых `_comment`/`_favorites` и проход после очистки; компенсация при удалении цели в транзакции; аннулирование на выключенном рейтинге, неактивном типе и отключённом материале; задача при закрытой области `[]` обработана; сбой журнала после COMMIT не откатывает к `old`; дубль адреса ссылки отклонён; публикация с истёкшим `expires` отклонена; сбой второй части `editsave()` откатывает `param`. HTTP — повтор формы adjust без двойной поправки, длинный `pnote` не сохраняет профиль; постоянные данные сверены SQL.

### S19.4 — Кеш, блокировки и коды ответа

- Зависит: S19.3.
- Читать: 11 — «Единый порядок блокировок» (около 207), «Завершённый протокол HTML-кеша» (около 199); 05 — Инвалидация кеша; 07 (около 35); ratings.md — заметки S02.
- Находки:
  - Запись комментариев на цели Node без `Cache::getWriteGuard()` до BEGIN (comment.php — BEGIN в 394, 459, 481, 531; `addEpoch` в 433, 468, 521, 566, 583). В админ-запросе ранний bump (pdo.php:151) до COMMIT подавляет финальный — страница со старым `comnum` публикуется под новым поколением (`setStatus`, `deleteComment`); у `deleteTarget` (autocommit) и публичных `addComment`/`updateComment` нет только guard. Без guard также `updateCountDrift` (184) и `deleteUser` (594).
  - (ъ) `Comment` для цели Node блокирует материал после обычного чтения (`getLiveCount()` до `updateNodeComments()`): при `innodb_snapshot_isolation` гонка двух комментариев одного материала.
  - Файловые блокировки: FileManager берёт только `dirname(file)` (filemanager.php:169, 224, 242, 273, 405), Upload — только каталог назначения (upload.php:183, 1174), без корня типа; 11:207 требует корень до подкаталога — загрузка в подпапку идёт во время `deleteNodeType()` (проверка файлов service.php:483). Ключ `getPathLock()` (filemanager.php:314–315) без realpath и регистра — на стандартной установке совпадает, расходится при junction или другом регистре; канонизировать.
  - `Cache::addEpoch()` (cache.php:196–198) превращает нечисловое содержимое в `intval+1`, поколение идёт назад (ratings.md, заметки S02).
  - `NodeQuery` (query.php:378–380): `is_file()` и `file_get_contents()` marker.json без защиты — удаление между ними даёт `['*']`, на запрос удержаны все типы (404).
  - `setConfigFile()`: `$busy` и счётчик блокировки остаются при исключении вне try (core/system.php:2739, 2781, 2817; `getConfig()` 72) — до конца запроса; сбой чтения снимка даёт `''` и удаляет живой источник (2697); нечитаемый источник журналируется как «не существовал» (2748). Развилка: при журнале `journal`/`backup` без local.php конфигурация собирается из полузаменённых источников (58) — правила в контракте нет.
  - Порядок блокировок: shop (modules/shop/admin/index.php:406–408) компенсирует `$ouid`, затем награждает `$uid` без сортировки; Point раньше строк расширения в `addNode()` (service.php:1131→1133), `updateNode()` (1165→1167), `updateNodeStatus()` (1196→1198), `deleteNode()` (1217→1222) вопреки 11.
  - Кеш главной: `getCacheRouteVars()` (core/system.php около 1653) даёт `name=''` всем домашним типам — при нескольких модулях главной первый случайный тип (index.php:44–60, `mt_rand`) закрепляется в кеше.
  - Коды ответа: modules/node/index.php:806–813 — проверки op и метода до 503 маркера, для `op=support` `getNodeRoute()` собирает карту типов до маркера (07:35); modules/node/admin/index.php:1089 `config()` отвечает 422 на сбой хранения и ожидающий журнал (сохранение типа — 500 через `getNodeStatus()`).
  - Необязательно: регулярные выражения без `D` (core/system.php:2640, 2651, 2731) — входы серверные; fsync каталога после rename (2606–2631) — только POSIX.
- Файлы: core/classes/comment.php; core/classes/filemanager.php; core/classes/upload.php; core/classes/cache.php; core/classes/node/query.php; core/classes/node/service.php; core/system.php; index.php; modules/node/index.php; modules/node/admin/index.php; modules/shop/admin/index.php.
- Готово: PageCacheContractTest — одобрение комментария админом не публикует старый `comnum`; FileManagerLockTest — загрузка в подкаталог ждёт блокировку корня; ConfigFileTest — исключение в источнике не оставляет `$busy`, сбой чтения снимка не удаляет источник; тест поколения на испорченном журнале; тест гонки marker.json; порядок блокировок shop и Point — тестом или чтением с записью в передачу; `config()` на сбое хранения — 500, при журнале — 409.

### S19.5 — Рендер и представление

- Зависит: S19.4.
- Читать: 08 целиком; 05 — политика «тег и есть право» (около 1037), единый адрес (около 897); 07 — SEO и canonical; 10 (около 71).
- Находки:
  - Развилка: `NodeView::getTextHtml()` (view.php:46) включает доверенный рендер всего текста по наличию `[usehtml]`/`[usephp]`. Не-super теги не сохраняет (service.php:742, 748), но главный администратор, правя чужой текст, делает доверенным и пользовательский HTML в нём — он исполняется. Это действующая политика 05:1037; исправление (отметка доверенного автора) меняет хранение и контракт — решает пользователь. `getPlainText()` (52) переносит текст `<script>` в `intro` (качество meta).
  - og:image внешней обложки — `homeurl/https://...` (modules/node/index.php:627; источник view.php:79).
  - blocks/node.php:49 всегда `node/block`, не используя поиск `getNodeTplName()` (modules/node/index.php:36–43) по 08:79–93.
  - Одобрение ответа поддержки (comment.php:510) вызывает расширение в контексте модератора: `NodeSupport::updateNodeAction()` (support.php:201) решает сторону по `ctx->uid` — владелец получает уведомление о собственном ответе; скрытие и повторное одобрение шлют его снова. 10:71 — по автору ответа.
  - Живой виджет рейтинга: `$res['average']` отбрасывается (core/system.php:6094), `number_format` с float (core/helpers.php:1107).
  - Постер (modules/node/index.php:411) — первая роль с mode `none` и kinds `['image']`, а не роль `poster`; ошибается только при пользовательской роли той же формы раньше.
  - (п) при смешанных формах `[attach]` в одном тексте `filterAttach()` выводит одну форму, `getAttachList()` перечисляет все три.
  - Комментарий `op=report` (modules/node/index.php:759) обещает возврат на страницу жалобы, код (778) ведёт к списку типа.
  - Публичные адреса собраны вручную в обход `getSeoUrl()`: modules/node/admin/index.php:263, 340, 596, 1131; адрес preview — view.php:82.
  - Мёртвый код: `$conf['style'] = 'sl_mod_'.$name` (index.php:57, прежняя ветка 67) нигде не читается — удалить.
  - plugins/system/slaed.js:1909–1910: тело ошибки через `innerHTML` отсоединённого div (`<img onerror>` срабатывает) — DOMParser.
- Файлы: core/classes/node/view.php; modules/node/index.php; modules/node/admin/index.php; blocks/node.php; core/classes/comment.php; core/classes/node/ext/support.php; core/classes/parser.php; core/helpers.php; core/system.php; index.php; plugins/system/slaed.js; фрагменты lite.
- Готово: og:image внешней обложки — адрес источника; блок использует `fragments/node/<mode>/block.html`, если он есть; одобрение ответа владельца не шлёт уведомление ему, повторное одобрение — без второго; виджет показывает среднее класса; смешанные `[attach]` выводятся все; ответ `op=report` соответствует комментарию; NodeRouteTest, ui:gates; ui:before/ui:after при правке фрагментов или CSS.

### S19.6 — Производительность и Feed

- Зависит: S19.5.
- Читать: 03 — Индексы материала (около 91, 108), Дополнительные категории (около 271, 300), Критерии утверждения; 05 — Общий API RSS и Atom (около 257, 277); 11 — Производительность (около 109, 118); 13 (около 262).
- Находки:
  - `setNodeCategory()` (query.php:575, счётчик 926): `OR EXISTS` — EXPLAIN на стенде: `_nodes` по `pub (tid, status)`, `_node_categories` как DEPENDENT SUBQUERY; ни `_nodes.cat`, ни `_node_categories.cat` не используются, список и COUNT сканируют все опубликованные строки типа. 03:300 требует объединить два индекса с однократным материалом — например UNION двух ветвей.
  - `getNodeDeadline()` (query.php:940–942) без временного условия агрегирует MIN по всем опубликованным строкам типа; `expires` нет в индексе `pub`.
  - Журнал баллов: `COUNT(*)` по всей таблице без фильтра и `LIMIT :offset` (admin/modules/groups.php:295–297) — договор не нарушен, стоимость растёт с журналом; курсор или оценка.
  - Индексы при `pinned`: сортировки `title` и `updated` с `pin DESC` — filesort (query.php:656–671); 03:108 откладывает дополнительные индексы до профилирования — решение по профилю. Индекс `admin` (03:99) не служит списку админки по умолчанию (modules/node/admin/index.php:333 не сортирует по `updated`).
  - Feed: `getFeedFail('redirect')` без последнего кода — `code = 0` (feed.php:93; 05:257); разрешение имени вне бюджета `rss.timeout` (57–60), `gethostbynamel()` вызывается и после успешного `dns_get_record()` (249–250); (м) фильтр `url` в `getVar()` (`filterWebUrl()`) переводит весь адрес в нижний регистр — лента с прописными буквами в пути запрашивается искажённой; (о) `getRssView()` (core/system.php:5142–5153) и модуль rss загружают адрес посетителя без кеша и ограничения частоты (SSRF закрыт, но нагрузку дёшево усилить).
  - Документ: бюджет категорийного списка — 11:118 и 13:262 говорят 4, по 05 (заметки S09, `cids` при `features.categories`) выходит 5; 06:291 приписывает предзагрузке `pview`, хотя по 06:285 он управляет только видимостью самой категории.
- Файлы: core/classes/node/query.php; core/classes/feed.php; core/security.php (`filterWebUrl`); core/system.php; admin/modules/groups.php; tools/node-profile.php; setup/sql/table.sql и table_update6_3.sql при смене индексов; docs/node/03, 06, 11, 13.
- Готово: tools/node-profile.php на 100000 материалов — категорийный список использует `cat` и `_node_categories.cat`, просмотренных строк порядка размера страницы (критерий инструмента ужесточён: сейчас он ловит только `type=ALL` при числе строк больше 1000, стр. 460); бюджеты 11 соблюдены; FeedTest — код последнего редиректа, общий бюджет времени, прописные буквы адреса; NodeQueryTest; /schema совпадает с table.sql.

### S19.7 — Остатки девяти модулей и потерянные функции

- Зависит: S19.6.
- Читать: 09 целиком; 12 (около 125); 15 (около 1133); .rules/constants.md.
- Находки:
  - (ы) профиль: `getProfileModules()` (core/user.php:1208–1213) знает только comm и forum (до плана — ещё семь модулей); вкладки и счётчики профиля (modules/account/index.php:330 и далее) без Node — 09 «Пользователи и уведомления» держит «пользовательские публикации». Ссылка `help` кабинета (core/user.php:341 и далее) снята без замены на тип поддержки (15:1133 сохраняет адрес `name=help`).
  - Админ-панель: блок «Новое» (core/admin.php:354 и далее) потерял строки девяти модулей без счётчиков Node; `$tabs` в `getAdminCategoryList()` (391) — только forum и shop, категории Node показывают 0 материалов.
  - templates/lite/partials/menu.html:13–80 и site-footer.html:7, 28–33: адреса списков совпадают с типами (12:125), но ссылки на конкретные `id` и `cat` старых данных (`name=content&op=view&id=13`, `name=files&cat=4` и другие) на чистой установке и после обновления ведут в 404. Ссылки на материалы и категории — убрать или заменить списками типов.
  - Сиротские константы `_MDIRECTOR`, `_MROLES`, `_MTITLE`, `_MYEAR` (modules/search/lang/*.php:7–10).
  - modules/voting/admin/index.php:32 предлагает только shop; сохранение (246, 274) опроса с `modul='news'` обнуляет `modul`, опрос уходит в публичный список (96) и sitemap (core/system.php:3023). На стенде — опросы 27, 28, 33.
  - RSS: modules/rss/index.php:15 по умолчанию `shop` (было news); элементы Node без `<dc:creator>` (core/user.php:1515–1527). Выдача `_products` без `is_active('shop')` (1494) — дефект старше плана, по пути.
  - modules/search/admin/index.php:69 показывает `node` сломанным (`sport_node` не существует).
  - (н) правка RSS-блока со сменой позиции не сохраняет новый URL и не сдвигает `time` (`editsave()` admin/modules/blocks.php).
  - (ч) config/scheduler.php: `filescan` и `maildrain` с одинаковым приоритетом 2, а `save()` admin/modules/scheduler.php отклоняет занятый приоритет — ни одно из двух не сохранить.
- Файлы: core/user.php; modules/account/index.php; core/admin.php; templates/lite/partials/menu.html, site-footer.html; modules/search/lang/*, modules/search/admin/index.php; modules/voting/admin/index.php; modules/rss/index.php; admin/modules/blocks.php; config/scheduler.php; admin/modules/scheduler.php.
- Готово: HTTP — ссылки меню и подвала на модули и установленные типы отвечают 200 (contact, recommend, whois отвечают 403 по настройке стенда — вне задачи); профиль автора показывает его материалы Node; опрос с `modul='news'` после сохранения сохраняет `modul`; оба задания планировщика сохраняются; LanguageValidationTest и поиск сирот чисты; ui:before/ui:after при правке partials.

### S19.8 — Правила кода и прежние находки

- Зависит: S19.7.
- Читать: .rules/global.md — Naming, Comments, PHP and code style; .rules/constants.md (длина языковых констант — 2–18).
- Находки:
  - admin/index.php:147–151 `addNodeProfiles()`: русский заголовок и текст стартового материала в PHP — в константы шести локалей или данные установки.
  - Строки длиннее 180, добавленные планом: 73 строки в 22 файлах по `git diff d41badf4 70224f65`, среди них core/classes/node/context.php:30, core/classes/filemanager.php:312, core/system.php:2058, modules/account/admin/index.php:176, 531–542, modules/account/index.php:211, 1295, admin/modules/groups.php:215, 217, modules/order/admin/index.php:136, 140, modules/search/index.php:300, modules/users/index.php:15, 87, 127, modules/presentation/index.php:341, demo/assets/demo.js, около 40 в tests.
  - Комментарии в теле функций, добавленные планом: core/helpers.php:1240, setup/index.php:984, 999; plugins/system/filemanager.js:1128.
  - Лишние приведения, добавленные планом: admin/modules/ratings.php:92, admin/modules/config.php:1059, core/classes/node/ext/support.php:106, 122 и приведения `(тип)$row` в node/query.php, service.php, support.php, sync.php — там, где PHP приводит сам или приведение тут же отменяется.
  - (я) `getUserInfo()` без объявленного возврата; (ь) `define('SITEMAP_DIR')` и остальные каталоги в core/system.php объявлены без `defined()` — route_web.php, объявляющий их раньше, получает предупреждение о повторном определении.
  - (к) `filterFields()` (core/security.php) остался только как ветка массива в `filterText()` и цель InputFilterTest; (л) `getTplRefreshTimeSelect()` и ещё три функции ядра неиспользуемы по UnusedCodeAuditTest — удалить с проверкой зависимостей.
  - (х) пять тестов StatsContractTest падают в 00:00–02:00 местного времени — пояс CLI против пояса сайта; (ш) длинные строки tests/Support/route_probe.php S15 — сверить (в acb58916 отмечены перенесёнными); CommentIsolationTest на демо-строке `_comments` в modules/presentation/index.php.
  - Прежние находки вне Node — сверить с кодом, живые вынести пользователю отдельной задачей: (а) формы добавления auto_links не сохраняют ссылку — поле `name` затеняет параметр модуля; (б) admin.php?name=shop&op=clients — SQL `SELECT COUNT(id) FROM sport`; (в) касса читает cookie `shop`, корзина пишется в `<user_c>-shop`; (г) новая тема форума около часа даёт 404 (`time <= NOW()`), адаптер Rating форума повторяет условие; (д) холодный старт с пустым storage/cache/templates и двумя запросами — `include(): Failed opening`, errno=13; (е) риг снимков теряет один из четырёх входов; (ж) front и presentation нельзя сравнивать разнесённой парой; (з) обязательное демо-поле аккаунта не даёт сохранить профиль; (и) заказы в админке по `time DESC` при отставании часов БД; (ц) отладочный вывод сессии на стенде — настройка стенда. Закрыты прежде: (с), (т), (у), (э).
- Файлы: по списку находок.
- Готово: php -l, phpstan, php-cs-fixer check, полный phpunit без провалов, ui:gates; среди строк, добавленных планом (`git diff d41badf4 70224f65`), нет строк длиннее 180 и комментариев в теле функций.

### S20 — Исправления по второму аудиту реализации

Аудит 2026-09-25 проверил S00–S19.8 (HEAD 1334a08d) в шести срезах — безопасность, целостность данных, установщик и схема, рендер и маршруты, соответствие плану, правила кода — против 01–13, points.md, ratings.md и .rules/*; каждую находку сверили с кодом, неподтверждённые сняты. Строки указаны по 1334a08d; окно ищет место по имени функции и перед правкой убеждается, что находка жива, — неподтверждённая записывается в передачу, а не исправляется. «Развилка» и «Документ» — как в S19. Открытые записи блока передачи PROGRESS.md (прежние находки S19.1–S19.7 и вне Node) в S20 не входят. Решение пользователя 2026-09-25: обещанное планом, но не реализованное (дерево документов, действие `moderate`, режимы представления), — реализовать; развилки внутри этих подэтапов задаются до первой строки кода. Одно окно — один подэтап S20.1–S20.8. Проверки на момент аудита: `php -l` всех PHP в git — без ошибок, phpstan без ошибок, php-cs-fixer 0, ui:gates 234 теста, полный phpunit 1501 тест, 22529 утверждений, 7 пропусков, без провалов.

### S20.1 — Безопасность публичной формы Node

- Зависит: S19.8.
- Читать: .rules/global.md — Security baseline; 05 — ресурсы материала и загрузка (заметки S11, S12); 07 — коды ответа, SEO и canonical; 11 — Безопасность.
- Находки:
  - modules/node/index.php:104–137 `getNodeAssetPost()`: `$_FILES['afile<idx>']` сохраняется `addUploadedFile()` без `checkEditorUploadAccess($type->name, $rule)` (core/system.php:4734) — в Node проверки нет вовсе. `userupload`/`guestupload` правила `<type>.attach` не действуют; `maxfiles` (core/classes/upload.php:141, только в `addUploadedFiles()`) не соблюдается; роль с `active = 0` или режима `link` принимает файл (фильтр только `$def === null`, 115–116); файл пишется и для `action=preview`, и до отказа писателя `assets.<role>.max` — остаётся в квоте. Право — до цикла; неактивные и link-роли пропускаются; число строк с файлом ограничено `max` роли и `maxfiles`.
  - Развилка: публичная форма `op=add` (`setNodeForm()`, 633–686) без капчи и без ограничения частоты. Прежний modules/news проверял `getPageCaptcha('comment')`/`checkCaptcha('comment')` (7166b365^:modules/news/index.php:457, 485); в docs/node решения об отказе нет. При `access = all` бот создаёт pending-материалы и письма `notify.pending` (658–659). Ключ капчи (свой `node` или `comment`) и предел частоты решает пользователь.
  - modules/node/index.php:487–488: категория сверяется только с `getCategoryMap()` без прав чтения; `NodeQuery::addFilterSql()` (query.php:590) даёт `1 = 0` — закрытая категория (группы `pread`, другой язык) отвечает 200 с заголовком категории в `<title>`, индексируемо и с canonical. Просмотр материала такой категории — 404, sitemap её пропускает (core/system.php:3048–3050). Недоступная категория — 404.
  - modules/search/index.php:197 `getSearchNode()`: заголовок Node экранируется в PHP, фрагмент `link` экранирует `title` снова — подсказка `Q&amp;A`. Экранировать только для `label_html` (257).
- Файлы: modules/node/index.php; core/classes/node/query.php, если право категории выносится в чтение; modules/search/index.php; partials формы lite и lang шести локалей при капче.
- Готово: HTTP — гость с `afile0` при `guestupload = 0` отказан, в uploads/<type>/ нового файла нет; строка сверх `max` роли и сверх `maxfiles` файла не сохраняет; неактивная роль игнорируется; форма без капчи или с неверной — отказ без строки `_nodes` и без письма; закрытая категория — 404, открытая — 200; поиск по заголовку с `&` — одно экранирование. NodeRouteTest; журналы ошибок после записей.

### S20.2 — Установщик и обновление 6.3

- Зависит: S20.1.
- Читать: 12 — «Штатное обновление данных 6.3», реестр модулей (около 191), заметки S19.2; 05 — заметки S19.1 (запирание установщика); UPGRADING.md.
- Находки:
  - setup/index.php:1147–1149 ветка `update6_3`: `active`, `view`, `inmenu`, `mod_group`, `blocks`, `blocks_c` из `_modules` сайта 6.2 (1110–1121) перекрываются `array_merge($cont, $existing)` поставочным config/modules.php (файл в git) — выключенный или закрытый группой модуль после обновления снова активен и публичен; 12:191 — «сохраняет запись сайта». Запись `_modules` сайта побеждает поставочную; сайт без `_modules` — как сейчас.
  - setup/index.php:978–992 ветка `new` (выбрана по умолчанию, 328): без проверки пустой базы снимает типы Node из config/node.php, fields, uploads и ratings без снимка, затем `table.sql` падает на существующих таблицах, а 992 всё равно пишет отметки update.php — последующий `update6_3` отказывает блокам без manifest (385, 456, 679). Отказ `new` при таблицах префикса — до первой записи; отметки — только при успехе DDL, как в `update6_3` после S19.2.
  - Развилка: окно `config/setup.unlock`. Отказ preflight (952, `setExit`) оставляет файл-ключ; форма `config()` (340) выводит пароль БД в `value`; `xafile` (945) без фильтра уходит в `rename()` (964). Варианты: не выводить пароль (пустое поле — прежний), снимать ключ при любом завершении, фильтровать `xafile`.
  - setup/index.php:943: префикс таблиц без проверки конкатенируется в SQL (1004, 1105, 1267, 1283) и в `PREFIX_DB`; `site-1` устанавливается (table.sql в обратных кавычках), рантайм падает 1064. Проверка по грамматике имени до первой записи.
  - setup/index.php:361–366 `checkUpdateBase()`: префикс, не совпадающий ни с одной таблицей, проходит preflight — ветка закрывает сайт, переносит config_*.php, DDL создаёт таблицы чужого префикса. Preflight требует таблиц `_users` и `_admins` префикса.
  - core/classes/pdo.php:29 `Logger::addSite()`: установщик не подключает core/classes/logger.php — неверные учётные данные дают fatal `Class "Logger" not found` вместо сообщения.
  - Проверка версии сервера только для `update6_3` (950); чистая установка на MySQL ниже 8.0.16 молча теряет CHECK (`chk_node_*`, `chk_points_uid`, `chk_rating_votes_value`; table.sql:5).
  - Вне Node, пользователю отдельной задачей: ветки `update4_1`…`update6_2` (1002–1086) без проверки версии источника, `update5_0` перехеширует пароли.
- Файлы: setup/index.php; setup/lang/*.php при новых сообщениях; UPGRADING.md; docs/node/12; tests/Support/install_probe.php, update_probe.php; SchemaUpdateValidationTest.
- Готово: update_probe — сайт 6.2 с выключенным модулем и `mod_group` сохраняет их после `update6_3`; `new` на базе с таблицами префикса отказан, config/*.php байт в байт, update.php без отметок; ошибка DDL `new` — без отметок; неверный префикс и префикс без таблиц — отказ до записи; неверный пароль БД — сообщение без fatal; форма не содержит пароля; журналы ошибок.

### S20.3 — Целостность комментариев, баллов и категорий

- Зависит: S20.2.
- Читать: points.md — Жизненный цикл, карта владельцев (около 247); 05 — удаление (около 1189), заметки S19.3 и S19.4; 11 — «Единый порядок блокировок».
- Находки:
  - core/classes/comment.php:121–129 `setNodeLock()`: NOTFOUND под блокировкой отвечает `null`, `addComment()` (431 → INSERT 436) сохраняет комментарий удалённого материала и начисляет `comment` (457); режим (корзина, `comon = Disabled`) под блокировкой не перечитывается; pending-комментарий не блокирует материал (`$kind = null`, 426), одобрение `setStatus()` (509, 534) повторяет то же. Rating и избранное перечитывают цель после блокировки (`getLockedTarget()`). NOTFOUND — отказ, режим проверяется под блокировкой.
  - core/classes/node/service.php:1261–1262 `deleteNode()` и comment.php:661–662 `updateTargetPoints()`: `false` из `getEventId()` принимается за «награды не было», `false` обратного `addEvent()` отбрасывается — удаление коммитится без сторно (05:1189; points.md:34 — false есть ошибка, а не отсутствие). Любой `false` откатывает владельца; отказ по конфигурации (закрытая область, `valid = false`) отличить от ошибки чтения — сверить с заметками S19.3.
  - comment.php:457, 534, 570: `updateTargetPoints()` вне `try` — `RuntimeException` Point уходит с открытой транзакцией и удержанным guard в 500 без локального отката и записи журнала.
  - core/classes/node/ext/sync.php:250–258 `setSourceResult()`: результат `setSqlRollback()` не проверяется, guard снимается; `NodeService::setNodeWrite()` (service.php:891) в том же случае держит `unknown`.
  - admin/modules/categories.php:406, 442, 472, 498 (создание, сохранение, смена состояния, удаление): Node или прежняя категория решается по `getNodeTypeMap()`, который пропускает тип с повреждённой или несовпадающей конфигурацией (query.php:443, 452, 486) и пуст при сбое чтения (core/system.php:5923–5925) — категория такого типа идёт сырым DELETE/UPDATE без `category.used` и блокировки типа. Решать по реестру `_node_types`.
  - service.php:1557–1562 `updateNodeCategory()`: перенос категории в другой модуль проверяет только её материалы; прямые подкатегории остаются со старым `modul` и чужим `parent`. Отказ переноса категории с подкатегориями — как с материалами.
- Файлы: core/classes/comment.php; core/classes/node/service.php; core/classes/node/ext/sync.php; admin/modules/categories.php; tests — NodeIntegrityTest, тесты Comment, tests/Support/node_probe.php.
- Готово: тесты — комментарий к материалу, удалённому между проверкой и блокировкой, отказан без строки и без баллов; одобрение pending-комментария удалённого материала — отказ; отказ Point при удалении откатывает удаление (строка `_nodes` на месте); исключение Point в `addComment()` — откат и запись журнала; сбой отката sync держит guard; категория типа с повреждённой конфигурацией не удаляется сырым путём; перенос категории с подкатегориями отказан. `sport_points` сверены SQL.

### S20.4 — Дерево документов

- Зависит: S20.3.
- Читать: 10 (около 15); 03 — связи (около 335–345); 05 — `getNodeTree` (около 599), заметки S09; 06 — `features.tree`, профиль docs; 08 целиком; 11 — бюджеты запросов; .rules/theme.md.
- Находки:
  - `NodeQuery::getNodeTree()` (query.php:1096) не вызывается вне тестов (tests/Support/node_probe.php:1044); `setNodeView()` (modules/node/index.php:587–592) выводит только связи `related`, `parent` читает лишь форма (167–168, 317). Профиль docs включает `tree: true`, но оглавления, родителя, предыдущего и следующего документа нет — 10:15, 03:340, 05:599.
  - Развилка до кода: где выводится оглавление (вид материала, список типа или оба), глубина, порядок соседей (по `sort` внутри родителя или обход дерева), сборка оглавления больше 500 документов партиями `getNodeTree`, новые ключи шаблона и фрагменты. Новые функции, шаблоны и классы — с согласия пользователя (.rules/global.md).
- Файлы: modules/node/index.php; core/classes/node/query.php, если соседи — отдельный запрос; core/classes/node/view.php; templates/lite/partials/node/**, fragments/node/**; theme.css lite; lang шести локалей; docs/node/05, 08, 10.
- Готово: HTTP — документ docs с родителем и детьми показывает оглавление, путь к родителю и соседей; недоступный родитель не раскрывается (05:599); тип без `features.tree` — без блока; число SQL в бюджете 11 (NodeRouteTest, tools/node-profile.php); ui:before/ui:after; ui:gates.

### S20.5 — Действие `moderate` и мелкий рендер

- Зависит: S20.4.
- Читать: points.md — таблица действий (около 53), карта владельцев (около 247–252); 08 — контракт NodeView; 06 — настройки типа (около 207); 09 — Поиск, sitemap (около 21, 85).
- Находки:
  - `moderate` (core/classes/point.php:19, config/points.php:24) без владельца: `addEvent('moderate')` нет нигде, а points.md:252 отдаёт его Node (S11, S12, S14), экран правил предлагает награду. Развилка: какие завершённые действия модератора награждаются (одобрение материала, решение по жалобе `deleteNodeAssetReport()`, ответ поддержки, одобрение комментария), источник события и компенсация.
  - templates/lite/partials/node/support/view.html (24 строки) не выводит `poll_html`, `rating_html`, `fav_html`, `assets_html`, `rels_html`, которые передаёт `getNodeViewHtml()` (modules/node/index.php:421–437) — включённые у help функции не видны.
  - Связанные карточки (modules/node/index.php:591) строятся из `NodeTarget` с `views = null` (core/classes/node/view.php:149) при `is_views = true` — пустой чип просмотров (templates/lite/fragments/node/card.html:5) у всех профилей с `related`.
  - templates/lite/fragments/node/search.html не рендерится (modules/search строит строки сам), режимы `block` и `search` в `NodeView::LIGHT` (view.php:16) без вызывающих — поиск через `getNodeTplName()` по 08 или удаление из 08 и кода.
  - Документ: query.php:329 принимает в секциях `form` и `admin` только `[]`, 06:207 — «управляют своими участками»; записать правило или определить содержание.
  - Документ и код: партии sitemap (core/system.php:3108) и дерева (query.php:1096, 1116) — константа 500 при настраиваемом `limits.syncbatch`; 09:85 обещает `syncbatch`, 09:21 и 13:258 — 500. Согласовать.
- Файлы: core/classes/node/service.php; core/classes/comment.php; core/classes/node/ext/support.php; core/classes/node/view.php; modules/node/index.php; modules/search/index.php; core/system.php; templates/lite/partials/node/support/view.html, fragments/node/**; docs/node/06, 08, 09, points.md.
- Готово: тест Point — `moderate` начисляется выбранным действием один раз и компенсируется по правилу; HTTP — тип без расширения с `view.mode = support` и включённым рейтингом показывает виджет (help рейтинг включить не может: расширение support его запрещает, решение пользователя 2026-09-26); связанные карточки без пустого чипа; ui:before/ui:after при правке фрагментов; ui:gates; NodeRouteTest.

### S20.6 — Режимы представления

- Зависит: S20.5.
- Читать: 08 — «Выбор представления типом» (около 79–93); 06 — `view.mode` (около 207) и профили; 13 — браузерный сценарий 1 (около 333); .rules/theme.md.
- Находки:
  - Режимы `article`, `docs`, `faq`, `files`, `media` без собственных файлов: в templates/lite/partials/node/ — только list.html, view.html и support/view.html, в fragments/node/ — только support/; `getNodeTplName()` (core/system.php:5962) всегда берёт базовый. 08:81, 06:207 и 13:333 («разные представления») обещают отличия.
  - Развилка до кода: состав отличий каждого режима (список и вид), показ вариантов пользователю до переноса; новые partials и классы `sl-*` — с согласия пользователя; тема admin не затрагивается.
- Файлы: templates/lite/partials/node/<mode>/**, fragments/node/<mode>/**; templates/lite/assets/css/theme.css; при новых ключах — core/classes/node/view.php, modules/node/index.php; docs/node/08.
- Готово: ui:before до правки; HTTP — news, docs, faq, files, media показывают свои режимы на 375 и 1280 px без ошибок консоли и горизонтальной прокрутки; `php tools/ui-audit.php --theme=lite` без роста счётчиков; ui:after; ui:gates.

### S20.7 — Правила кода и консолидация

- Зависит: S20.6.
- Читать: .rules/global.md — Naming, PHP and code style, Core workflow (новые функции и консолидация).
- Находки:
  - `list()` вместо `[...]`: core/system.php `addSitemapTask()` 3031, 3034, 3037, 3041, 3092 (функция переписана планом); `getBlocks()` 686, 707, 785.
  - `render_blocks()` (core/system.php:5828): сигнатура менялась планом (`$param`), имя без глагола и с `_`, `mixed $bid`; переменные `$qlang_params` (685), `$where_mas` (691, 707, 785), `$querylang` (684), `$blocktitle` (5828). Переименование с переносом вызовов по всему дереву и темам.
  - Лишние приведения: `(int)getVar(..., 'num', ...)` при `filterNum(): int` (core/security.php:921) — modules/node/index.php:167, 173, 177, 184, 793; modules/node/admin/index.php:239, 305, 402, 428, 439, 495, 521, 528, 540, 545, 561, 884, 965, 979, 1152, 1159. `(string)$conf['homeurl']` и `mtemp` — modules/node/admin/index.php:99, 101; modules/node/index.php:615; ext/support.php:135, 150; core/system.php:3057. `(string)_BLOCKPROBLEM` — blocks/node.php:20; `(string)$own` — service.php:1528; строки PDO NOT NULL — service.php:526, 947, 1103, 1555, 1577, ext/sync.php:224 (сверить столбцы).
  - Развилка (консолидация, новые и объединённые функции — с согласия пользователя): `getNodeLabel()` (modules/node/index.php:36), `getConst()` (core/system.php:4025) и `Field::getFieldText()` (field.php:164); `NodeQuery::checkLabelText()` (query.php:195) и `Field::checkFieldText()` (field.php:171); письмо `setNodeResultMail()` (modules/node/admin/index.php:93–104) и `NodeSupport::addSupportMail()` (support.php:131–152), абсолютный адрес ещё в modules/node/index.php:615; фабрики `getNodeReader()`/`getNodeWriter()` (modules/node/index.php:22–33) при примерно двадцати встроенных `new NodeQuery(...)`/`new NodeService(...)` (core/system.php:5922, 5946, 6065; core/user.php:1224, 1284, 1352, 1533; core/admin.php:436, 1130; blocks/node.php:33; search, presentation, voting, monitor, categories) — перенос в core/system.php рядом с `getNodeContext()`; ответ 405 с `Allow` двумя путями — `checkNodeMethod()` (modules/node/admin/index.php:50) и modules/node/index.php:804–808.
  - Развилка: 69 стрелочных функций кода плана без типа возврата и 81 параметр без типа (например query.php:486–487, service.php:1143–1144, modules/node/admin/index.php:774–861); правило «type hints and return types» замыкания не исключает. Типизировать все или записать исключение в .rules — решает пользователь.
- Файлы: по списку находок.
- Готово: php -l, phpstan, php-cs-fixer check, полный phpunit, ui:gates; поиск `list(` и `(int)getVar(` по файлам плана пуст; вызовы переименованных функций найдены по всему дереву, включая темы.

### S20.8 — Документы плана

- Зависит: S20.7.
- Читать: README.md; 04; 05 — блоки кода NodeContext и NodeQuery; 06 — обложка; 13 — список тестов.
- Находки (все — «Документ», код не трогается):
  - README.md:3 и строка статуса в начале 01–11 и 13 («реализация и её проверки выполняются…»); 13:390 («требования к будущей реализации»).
  - 13:272–289 «Создаются только утверждённые файлы» без tests/Unit/NodeGuardTest.php, NodeIntegTest.php, NodeIntegrityTest.php и тестов S20.
  - 05:293–306 блок NodeContext без `bool $polls = false` (context.php:29; 05:1175); 05:418–419 `getNodeTarget()`/`getNodeTargetList()` без `bool $any = false` (query.php:1054, 1091; 05:1195).
  - 06:391, 703 — метка обложки `'_COVER'`, в профилях и lang — `_NODE_COVER`.
  - 04:97–110 — дерево модуля без modules/node/profiles/ (06:569, S17).
- Файлы: docs/node/README.md, 01–11, 13, 04, 05, 06.
- Готово: контроль документации (UTF-8, один корневой заголовок, относительные ссылки живы); каждый пункт сверен с кодом HEAD окна.

## Общая приёмка

Каждый этап с кодом: php -l затронутых PHP, php vendor/bin/phpunit, php vendor/bin/phpstan, php vendor/bin/php-cs-fixer check и npm run ui:gates по правилам проекта. Перед первым визуальным изменением — npm run ui:before, после — npm run ui:after. Использовать execute-test-suite; для SQL — secure-database-access, форм/файлов — secure-inputs-and-forms, шаблонов — manage-slaed-templates, реального браузера — browser-debugging.

Для HTTP-записи подтвердить интерфейс и постоянные данные; после административной записи прочитать storage/logs/error_php.log, error_sql.log, error_site.log. Сервисный тест не выдаётся за HTTP. Обычные тесты работают на изолированной MariaDB/временном файловом корне; большой профиль запускается отдельно на S18. Никаких пустых тестовых файлов заранее. Канонический DDL — setup/sql/table.sql. Штатное обновление — setup/sql/table_update6_3.sql и ветка update6_3 в setup/index.php, версия 6.3.0 (NOD-225); протокол отметок, manifest и возобновления — в 12-migration.md.

Опоры, подтверждённые сверкой с деревом 2026-09-19: getSqlbatch() в core/admin.php делит SQL-файл по `;` вне кавычек; InsertValidationTest пропускает строки CONSTRAINT; Backup отключает foreign_key_checks на время восстановления; автозагрузчика нет — классы подключаются списком require_once в core/system.php; UnusedCodeAuditTest только считает; образцы изолированной БД — tests/Support/privat_class_probe.php и backup_probe.php; стенд — MariaDB 11.7.2, все таблицы InnoDB. DDL плана на сервере ещё не исполнялся: каждый этап исполняет свой DDL на изолированной БД.

Под «владельцами» в карточке понимаются реальные найденные вызовы заменяемого API в целевом составе. Перед изменением зафиксировать точные пути в передаче этапа. Исторические файлы могут быть gitignored: проверять физические пути, а не только rg --files. Например, форма пользователей — modules/account/admin/index.php.

Накопленная промежуточная реализация до S18 не является готовой поставкой: старые девять модулей удаляются на S03A, не адаптируются и не получают runtime-совместимость; между S03A и S13 стенд работает без контентных разделов. Сохранение данных при обновлении Point/Rating/Field не означает импорт старого контента в Node.
