# Реализация по отдельным окнам

Исполнитель: Claude Fable 5.1. Одно окно — один этап S01–S18. Первые этапы: **Point как класс, затем Rating как класс**. Реализация сейчас не выполняется.

## Вход в каждое новое окно

1. Прочитать CLAUDE.md/AGENTS.md, затем этот раздел и строку этапа в [PROGRESS.md](PROGRESS.md).
2. Проверить фактические файлы и результаты зависимостей; история чата не является источником состояния. Прочитать только перечисленные ниже разделы контрактов и подходящие проектные навыки.
3. Проверить git status и найденные вызовы затрагиваемого API. Сохранить чужие изменения. Выполнить один этап без заглушек и перехода к следующему.
4. Проверить результат, обновить строку PROGRESS.md и краткую передачу следующему окну: файлы, проверки/результаты, незавершённое, изменения контракта. Галочка только при выполненной приёмке.

Незавершённый этап продолжается в новом окне под тем же ID. Не начинать зависимый этап по одному заявлению предыдущего агента: проверить код и артефакты. Если код расходится с контрактом, исправить код либо явно согласовать изменение контракта; молчаливой новой архитектуры не вводить. review.md и 15-decisions.md — архив/история, не обязательное чтение каждого окна. TODO.md учитывает проектирование, PROGRESS.md — реализацию.

## Карточки этапов

### S01 — Point — класс

- Зависит: нет.
- Читать: points.md целиком; 02 — правила имён.
- Файлы: core/classes/point.php; config/points.php; setup/sql/table.sql (_points); tests/Unit/PointTest.php.
- Готово: Полный API, строгая конфигурация, SQL/баланс/компенсации, SAVEPOINT и потеря внешней транзакции. Изолированная MariaDB. Живые обработчики и старые балансы ещё не переключать.

### S02 — Rating — класс

- Зависит: S01 как порядок работ; Point не используется.
- Читать: ratings.md до HTTP; 11 — протокол HTML-кеша.
- Файлы: core/classes/rating.php; core/classes/cache.php (guard/addEpoch); setup/sql/table.sql (три таблицы Rating); tests/Unit/RatingTest.php и проверки Cache.
- Готово: Полный API на доверенных тестовых адаптерах, срок/nonce/аннулирование/конкуренция, canvote, отказы SQL/COMMIT и восстановление guard. Node и HTTP не требуются.

### S03 — Конфигурация и файловые блокировки

- Зависит: S01, S02.
- Читать: 06 — настройки/восстановление; 11 — порядок lock.
- Файлы: core/system.php (setConfigFile/getConfig); core/classes/filemanager.php; писатели admin/modules/fields.php, uploads.php, ratings.php; тесты конфигурации.
- Готово: Общий lock чтения/сборки/публикации, журнал и OPcache/local.php, повтор после сбоя. В этом этапе общий механизм без вызовов ещё отсутствующего NodeService; Node-proof подключается на S10.

### S04 — Point — подключение

- Зависит: S01, S03.
- Читать: points.md целиком; 12 — граница данных; 09 — опросы.
- Файлы: core/system.php, core/user.php; admin/modules/groups.php; modules/account/admin/index.php; modules/auto_links/index.php; найденные остающиеся владельцы баллов; штатное обновление.
- Готово: Баланс сохранён, все остающиеся источники переведены, старые числовые helpers удалены без обёрток. В старом getRatingView убрать награду сразу, не дожидаясь S05. HTTP adjust/сброс/auto_links, журнал и БД.

### S05 — Rating — подключение

- Зависит: S02, S03, S04.
- Читать: ratings.md целиком; 12 — потребители _rating.
- Файлы: core/system.php, core/helpers.php, index.php; admin/modules/ratings.php; config/ratings.php; plugins/system/slaed.js; rating-фрагменты поставляемых тем; штатное обновление.
- Готово: getRatingService с account/forum/shop, POST/CSRF и nonce; preflight/manifest остатков и сроков, административная отмена. Реальные HTTP на трёх целях, БД/логи, Point и voting неизменны.

### S06 — Field — класс и остающиеся потребители

- Зависит: S03.
- Читать: 05 — общий Field, реестр/значения; 06 — поля; 12 — преобразование Field; 13 — проверки Field.
- Файлы: core/classes/field.php; core/helpers.php; admin/modules/fields.php; config/fields.php; формы/запись/вывод account/forum/order; setup/sql/table.sql и штатное обновление; тесты Field.
- Готово: Однозначный preflight всех строк до записи, устойчивые ключи, TEXT→MEDIUMTEXT, партии и повтор. Все остающиеся формы используют JSON, без двойного runtime-формата.

### S07 — Feed — класс и RSS-потребители

- Зависит: S03.
- Читать: 05 — Feed/транспорт; 11 — SSRF; 13 — RSS/Atom.
- Файлы: core/classes/feed.php, core/classes/parser.php; core/system.php; config/rss.php; admin/modules/rss.php; остающиеся rss_read-вызовы/фрагменты; tests/Unit/FeedTest.php.
- Готово: Канонический Markdown, безопасные литералы и полный Parser, resolve/get без внешней сети, 304/лимиты/redirect/DNS. rss_read удалён одновременно с переводом вызывающего кода.

### S08 — Node — схема, DTO и загрузка

- Зависит: S06.
- Читать: 01; 02; 03 кроме ссылок на общие классы; 04; 05 — DTO/контекст/ошибки.
- Файлы: setup/sql/table.sql и штатное обновление; core/classes/node/{entity,type,typeinput,input,relation,asset,target,context,status,exception,extension,load}.php; core/classes/node/ext/load.php; core/system.php; NodeModelTest.php, tests/Support/node_probe.php.
- Готово: Схема чистой установки/обновления, _admins.modules TEXT, readonly-модели и загрузка без Composer. Фабрика расширений закрыта; классы support/sync появляются на S14/S15, пустых файлов не создавать.

### S09 — NodeQuery — чтение

- Зависит: S08.
- Читать: 03 — индексы; 05 — чтение/списки/типы/выборки; 11 — SQL-бюджеты; 13 — чтение.
- Файлы: core/classes/node/query.php; core/system.php (getNodeContext); tests/Unit/NodeQueryTest.php.
- Готово: Одиночные/смешанные цели, Field, ACL, поиск/главная/дерево/sitemap/deadline. Предикаты count/list одинаковы; 3/7 SQL и отсутствие N+1. Расширения проверяются через интерфейс тестовыми реализациями, не production-заглушками.

### S10 — NodeService — типы и настройки

- Зависит: S03, S05, S06, S09.
- Читать: 05 — запись типов; 06 целиком; 11 — конфигурация/файлы; 13 — типы.
- Файлы: core/classes/node/service.php; config/node.php, fields.php, uploads.php, ratings.php; общие admin/modules/fields.php, uploads.php, ratings.php; NodeConfigTest.php.
- Готово: Четыре операции типа, восемь свойств ввода, версия и согласованный пакет. Создание/клонирование/экспорт/импорт, восстановление сбоя и запрет удаления занятого типа через сервис. HTTP Node ещё не заявлять готовым.

### S11 — NodeService — материалы и ресурсы

- Зависит: S04, S09, S10.
- Читать: 03 — состояния/связи/ресурсы; 05 — запись/счётчики/ресурсы; 06 — workflow; 11 — locks; 13 — запись.
- Файлы: core/classes/node/service.php; admin/modules/categories.php; tests/Unit/NodeServiceTest.php.
- Готово: Создание/preview/изменение/состояния/удаление, категории, дерево, assets/link, жалоба, counters и Point. Версии, общий порядок lock, конкурентный цикл, откат. Проверяется сервис на изолированной БД.

### S12 — Файлы и кеш Node

- Зависит: S02, S10, S11.
- Читать: 07 — asset/attach/preview; 09 — файловый менеджер/выдача; 11 — кеш/время/locks; 13 — файлы.
- Файлы: core/system.php (getFileStream и штатные upload helpers); core/classes/filemanager.php, cache.php; интеграция NodeQuery deadline; тесты файлов/кеша.
- Готово: Защита всех uploads/<type>, владелец/модератор node-<name>, GET/HEAD/Range/304, приватный preview без строки материала. SQL-бюджет построения кеша 4/8, попадание 0. Полные маршруты проверяются на S13.

### S13 — Node — HTTP и представление

- Зависит: S08–S12.
- Читать: 04 — дерево модуля; 05 — NodeView; 07; 08; 13 — HTTP/браузер.
- Файлы: index.php, admin.php, core/system.php, core/helpers.php; core/classes/node/view.php; modules/node/index.php, admin/index.php, lang/, admin/lang/; node-фрагменты тем и slaed.js; NodeRouteTest.php.
- Готово: Обычный/home/admin/AJAX bootstrap, язык/layout, формы редактора/файлов, POST/CSRF/409 и права. Реальные создание типа/материала/preview/файлы; БД, конфиги и логи.

### S14 — Комментарии и NodeSupport

- Зависит: S04, S11, S13.
- Читать: 05 — NodeExtension/NodeSupport; 09 — комментарии; 10 — поддержка; 13 — support.
- Файлы: общий владелец комментариев core/classes/comment.php и его HTTP; core/classes/node/ext/support.php; NodeService/Node-маршруты/фрагменты; тесты поддержки.
- Готово: Цель Node в комментариях, comnum и Point; приватное обращение/ответ, состояние ожидания, права автора/модератора, version и откат. Эта интеграция готова до испытания NodeSupport.

### S15 — NodeSync

- Зависит: S07, S11, S13.
- Читать: 05 — NodeSync; 10 — внешняя синхронизация; 13 — sync.
- Файлы: core/classes/node/ext/sync.php; core/system.php (addNodeSyncTask); существующий реестр планировщика/admin/modules/scheduler.php; Node-маршрут sync; тесты.
- Готово: Один источник, ручной и плановый запуск nodesync по 10, сеть до транзакции, повторная версия/URL, последнее тело при ошибке, отсутствие сети в просмотре.

### S16 — Остальные интеграции Node

- Зависит: S04, S05, S09, S13–S15.
- Читать: 05 — цели/счётчики/опрос; 09 целиком; 11 — locks; 13 — интеграции.
- Файлы: core/system.php, core/helpers.php, core/user.php, index.php; владельцы рейтинга/избранного/опроса/поиска/блоков/главной/RSS/sitemap/SEO; тесты.
- Готово: Node подключён к общему Rating без Point; остальные владельцы используют Query/Service и расширение каждого типа. Проверить удаление poll, смешанные типы, видимость/кеш и реальные HTTP. Комментарии уже подключены S14.

### S17 — Все десять профилей

- Зависит: S10–S16.
- Читать: 06 — профили; 08 — режимы; 13 — состав выпуска.
- Файлы: config/node.php, fields.php, uploads.php, ratings.php; установочная инициализация; node-фрагменты; тесты профилей.
- Готово: Общий валидатор и HTTP: news/docs/files первыми как эталоны, затем pages/faq/jokes/links/media/help/content. Все десять входят в выпуск. links/media без расширений; help= support, content=sync.

### S18 — Целевая поставка и итоговая приёмка

- Зависит: S01–S17.
- Читать: 01; 11 — бюджеты; 12 — граница данных; 13 целиком.
- Файлы: девять заменённых modules/{news,pages,faq,help,jokes,content,links,files,media}/ и их регистрации; setup; tools/node-profile.php; документация выпуска.
- Готово: Без старых модулей/таблиц/fallback в исполнении Node, без импорта или удаления данных стенда. Чистая установка/обновление, 100000 материалов, EXPLAIN, полная HTTP/гейты и логи.

## Общая приёмка

Каждый этап с кодом: php -l затронутых PHP, php vendor/bin/phpunit, php vendor/bin/phpstan, php vendor/bin/php-cs-fixer check и npm run ui:gates по правилам проекта. Перед первым визуальным изменением — npm run ui:before, после — npm run ui:after. Использовать execute-test-suite; для SQL — secure-database-access, форм/файлов — secure-inputs-and-forms, шаблонов — manage-slaed-templates, реального браузера — browser-debugging.

Для HTTP-записи подтвердить интерфейс и постоянные данные; после административной записи прочитать storage/logs/error_php.log, error_sql.log, error_site.log. Сервисный тест не выдаётся за HTTP. Обычные тесты работают на изолированной MariaDB/временном файловом корне; большой профиль запускается отдельно на S18. Никаких пустых тестовых файлов заранее. Канонический DDL — setup/sql/table.sql, обновление — штатный файл версии проекта; новый номер версии не угадывать.

Под «владельцами» в карточке понимаются реальные найденные вызовы заменяемого API в целевом составе. Перед изменением зафиксировать точные пути в передаче этапа. Исторические файлы могут быть gitignored: проверять физические пути, а не только rg --files. Например, форма пользователей — modules/account/admin/index.php.

Накопленная промежуточная реализация до S18 не является готовой поставкой: старые девять модулей не адаптируются и не получают runtime-совместимость. Сохранение данных при обновлении Point/Rating/Field не означает импорт старого контента в Node. Текущая работа изменяет только docs/node; проверки будущего PHP/БД не заявляются выполненными.
