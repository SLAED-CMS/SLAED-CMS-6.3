# SLAED CMS Performance Audit, GPT-5.5

Дата анализа: 2026-05-18  
Среда: Windows, OSPanel, PHP-FCGI, MariaDB, локальный HTTPS `https://slaed.loc`  
Статус: анализ и замеры, без постоянных изменений кода

## 1. Цель

Найти реальные узкие места SLAED CMS, которые дают резкое замедление первого холодного запроса и постоянный overhead на frontend/admin страницах.

Проверялись:

- `https://slaed.loc/`
- `https://slaed.loc/index.php?name=news`
- `https://slaed.loc/admin.php`
- ядро bootstrap/render pipeline
- шаблонизатор
- admin dashboard
- security/session/log/stat/cron/backup/filescan
- changelog GitHub/cache flow

## 2. Ключевой вывод

В системе есть несколько узких мест, но они разного масштаба.

Подтвержденная критичная причина секундного cold slowdown на главной странице:

- frontend home module = `changelog`;
- при cache miss модуль синхронно делает GitHub API запросы до 500 commits;
- DB почти не участвует;
- после появления cache генерация падает до нормального уровня.

Для `admin.php` 4-секундный slowdown в headless Playwright не воспроизвелся. Подтвержденный admin overhead есть, но в замерах он был порядка `0.10-0.13 сек` PHP generation, а не секунды.

## 3. Замеры

### 3.1 Главная `/`, changelog cache miss

Контроль: cache-файл `storage/cache/dd692313c91ee47b159850c98fa490d790bd4a75.json` временно убирался и возвращался обратно.

| URL | состояние | total browser time | PHP generation | SQL |
|---|---:|---:|---:|---:|
| `/` | cache miss | `4023.7 ms` | `3.251 сек` | `3 запроса / 0.002 сек` |
| `/` | cache hit сразу после | `652.2 ms` | `0.063 сек` | `3 запроса / 0.004 сек` |

Профиль cache miss:

- `setHead:end -> setFoot:start`: `+3.1236 сек`
- это зона выполнения модуля перед footer render;
- DB в этот момент не является причиной.

### 3.2 News page

| URL | состояние | total browser time | PHP generation | SQL |
|---|---:|---:|---:|---:|
| `/index.php?name=news` | первый после PHP-FCGI reset | `798.6 ms` | `0.155 сек` | `8 запросов / 0.006 сек` |
| `/index.php?name=news` | warm | `669.9 ms` | `0.101 сек` | `8 запросов / 0.006 сек` |
| `/index.php?name=news` | warm HTTP check | `125.8 ms` | `0.102 сек` | `8 запросов / 0.006 сек` |

Вывод: модуль `news` не дает 3-4 секунды в проверенной среде.

### 3.3 Admin panel

Залогиненный admin через Playwright. Учетные данные в отчет не сохранялись.

| URL | состояние | total browser time | PHP generation | SQL |
|---|---:|---:|---:|---:|
| `/admin.php` | login submit -> panel | `1203.9 ms` | `0.321 сек` | `17 запросов / 0.013 сек` |
| `/admin.php` | panel warm | `143.3 ms` | `0.106 сек` | `17 запросов / 0.008 сек` |
| `/admin.php` | after PHP-FCGI reset | `646.3 ms` | `0.105 сек` | `17 запросов / 0.005 сек` |
| `/admin.php` | after empty template cache | `653.4 ms` | `0.113 сек` | `17 запросов / 0.006 сек` |

Вывод: в headless тесте `admin.php` не воспроизвел 4 секунды. Если 4 секунды видны в ручном браузере сразу после полного рестарта OSPanel, вероятная причина вне SQL и вне changelog: cold PHP/OPcache/Windows FS/browser TLS или первый старт PHP-FCGI worker.

## 4. Главные узкие места

| Приоритет | Место | Файл/строки | Доказательство | Оценка влияния |
|---:|---|---|---|---:|
| P0 | Changelog GitHub fetch на frontend home | `config/global.php:75`, `config/changelog.php:9`, `modules/changelog/common.php:331-372` | cache miss дал `3.251 сек`, DB `0.002 сек` | секунды |
| P1 | Шаблонизатор: много мелких вызовов и FS checks | `core/classes/template.php:106-123`, `297-301` | `news`: около 230 template calls; admin: около 233 calls | десятки ms, усиливает cold start |
| P1 | Admin dashboard render | `admin/index.php:13-149`, `core/admin.php:257-400` | admin panel: 17 SQL, много modules/lang/icon/template операций | десятки ms |
| P1 | Config bootstrap + fingerprint | `core/system.php:26-55`, `73-80` | каждый request читает configs и считает `sha1_file()` | 7-10 ms warm, больше на cold FS |
| P2 | Frontend blocks/layout сборка | `core/system.php:1688-1744` | `setFoot()` собирает footer/left/right/center/down blocks | десятки ms |
| P2 | Asset discovery через glob | `core/system.php:2337-2378` | `doCss()/doScript()` идут через glob/is_file | единицы ms warm, больше на cold FS |
| P3 | Security/session checks | `core/security.php:20-106`, `108-150` | warm overhead малый, лог выключен | низкое |
| P3 | Scheduler/filescan/backup | `core/system.php:379-397`, `4361-4411` | тяжелые задачи не исполняются в обычном `admin.php`; frontend только вставляет async trigger | низкое для обычного request |

## 5. Детальные находки

### 5.1 Changelog

Факты:

- `config/global.php:75`: домашний модуль установлен в `changelog`.
- `config/changelog.php:9`: cache TTL `900` секунд.
- `config/changelog.php:14`: лимит `500`.
- `config/changelog.php:18`: source `github`.
- `modules/changelog/common.php:331`: `chlogGhFetch()`.
- `modules/changelog/common.php:332`: cache key зависит от `owner/repo/limit/filters`.
- `modules/changelog/common.php:340-346`: цикл страниц GitHub API до набора лимита.
- `modules/changelog/common.php:352-372`: `chlogGhPage()` делает HTTP через curl.
- `modules/changelog/common.php:368`: timeout одного GitHub request = `CHLOG_GH_API_TIMEOUT`.
- `modules/changelog/common.php:372`: `curl_exec()`.
- `modules/changelog/common.php:294-320`: запись JSON cache.

Почему это критично:

- GitHub API выполняется синхронно в пользовательском frontend request.
- При cache miss пользователь ждет сеть, curl, JSON decode/normalize/render.
- Пагинация changelog после прогрева общего cache дешевая; новые filters создают новый cache key и могут снова вызвать GitHub fetch.
- DB время остается минимальным, поэтому SQL-оптимизация здесь ничего не даст.

Рекомендуемые фиксы:

1. Не обновлять GitHub changelog синхронно в frontend request.
2. Использовать stale-while-revalidate: если cache истек, отдать старый cache и поставить refresh flag.
3. Обновлять cache через scheduler/admin manual action.
4. Увеличить `cachettl` или сделать отдельный long TTL для GitHub source.
5. Уменьшить `limit` на frontend home; 500 commits держать только для admin/export.
6. Добавить защиту: если GitHub cache отсутствует, показывать lightweight fallback, а не ждать GitHub.

Риск фиксов:

- Низкий для TTL/stale cache.
- Средний для async refresh/scheduler, так как нужен lock и корректная диагностика ошибок.

### 5.2 Template engine

Факты:

- `core/classes/template.php:106-123`: каждый template call проверяет source/cache через `is_file()`, `filemtime()`, `filemtime(__FILE__)`.
- `core/classes/template.php:117`: если `template.php` свежее cache-файла, шаблон перекомпилируется.
- `core/classes/template.php:121`: compiled cache пишется через `file_put_contents()`.
- `core/classes/template.php:297-301`: cache key включает source path и source `filemtime`.

Замеры:

- `news`: около 230 template calls.
- `admin.php` panel: около 233 template calls.
- Полностью пустой template cache добавил примерно `0.03-0.05 сек`, не секунды.

Вывод:

- Шаблонизатор не является причиной `3-4 сек` сам по себе.
- Он является постоянным overhead и усиливает cold start на Windows FS.

Рекомендуемые фиксы:

1. Ввести per-request static cache для `getFile()`, `getCache()`, `checkFile()`, `filemtime()`.
2. Не проверять `filemtime(__FILE__)` на каждый template call в production.
3. Хранить build/version stamp шаблонизатора один раз на request.
4. Для production добавить precompile/warmup template cache.
5. Снизить количество fragment calls в admin dashboard и частых блоках.

Риск:

- Средний: шаблонизатор центральный, нужны regression checks по frontend/admin темам.

### 5.3 Admin dashboard

Факты:

- `admin/index.php:13-59`: `getAdminMenu()` на каждый пункт ищет иконку и рендерит template fragment.
- `admin/index.php:62-99`: `getAdminPanelBlocks()` перебирает `$conf['modules']`, проверяет `modules/<name>/admin/index.php`, грузит lang.
- `admin/index.php:104-149`: `getAdminPanel()` повторно строит dashboard module menu.
- `core/admin.php:257-267`: `getAdminLayoutVars()` для admin вызывает `getAdminPanelBlocks().admininfo().adminblock()`.
- `core/admin.php:270-400`: `admininfo()` делает серию count-запросов и template rows.

Замеры:

- Admin panel: `17` SQL.
- DB time: `0.005-0.013 сек`.
- PHP generation warm: `0.104-0.127 сек`.

Вывод:

- Admin dashboard не показал 4 секунды в тесте.
- Но он делает много повторяемой работы на каждый admin request.

Рекомендуемые фиксы:

1. Кешировать список admin modules и наличие admin entrypoint.
2. Кешировать resolved admin icon paths.
3. Кешировать lang loading state по модулю внутри request.
4. Свести dashboard counters к агрегированным `COUNT(*)`, не `SELECT id`.
5. Опционально кешировать admin counter block на 10-30 секунд.
6. Не строить sidebar blocks, если layout/route их не показывает.

Риск:

- Низкий для static in-request caches.
- Средний для cache counters, так как возможна задержка отображения новых items.

### 5.4 Config bootstrap

Факты:

- `core/system.php:26-55`: `getConfig()` на каждый request делает `glob(CONFIG_DIR.'/*.php')`, сортировку, require всех конфигов, merge local overrides.
- `core/system.php:73-80`: `getConfigFingerprint()` делает `sha1_file()` по config files.
- `config/global.php` содержит `dev_mode => '1'`.

Замеры:

- Warm fingerprint/merge обычно `~0.007-0.010 сек`.
- На cold Windows FS может быть выше.

Вывод:

- Это не главная 4-секундная причина.
- Это стабильный overhead на каждый request.

Рекомендуемые фиксы:

1. В production не считать fingerprint на каждый request.
2. Делать fingerprint только в admin config save/dev mode/manual check.
3. Скомпилировать merged config в один cache-файл.
4. Разделить immutable default config и local runtime overrides.

Риск:

- Средний: config bootstrap центральный, нужна аккуратная invalidation strategy.

### 5.5 Frontend layout/blocks

Факты:

- `core/system.php:1435-1684`: `setHead()` делает session/referer/stat tracking, SEO, CSS/JS, login block.
- `core/system.php:1688-1744`: `setFoot()` собирает footer, left/right/center/down blocks и финальную страницу.
- На главной с changelog cache miss задержка была в зоне выполнения модуля перед `setFoot:start`, а не в самих blocks.

Вывод:

- Blocks/layout дают регулярный overhead.
- Не являются доказанной причиной `3.251 сек` на `/`.

Рекомендуемые фиксы:

1. Ввести in-request cache для активных блоков и resolved assets.
2. Не собирать невидимые block zones.
3. Кешировать статичные block fragments.
4. Разнести tracking/stat и render path.

Риск:

- Средний: блоки могут иметь динамическое поведение.

### 5.6 Security/session/logs

Факты:

- `core/security.php:49`: `session_start()`.
- `core/security.php:80-106`: `addLog()`, но `config/security.php` показывает `log => 0`.
- `core/security.php:108-150`: blocker cookie/ip/user checks.
- `storage/logs/dump_log.log` был `0 bytes`.

Вывод:

- Security не является подтвержденной причиной секундного slowdown.
- `dump_log.log` не читается на обычном `/`, `/news`, `/admin.php`.

Рекомендуемые фиксы:

1. Оставить security path без больших изменений.
2. Если blocker lists растут, вынести parsed blocker rules в cache.
3. Не включать request logging на frontend без ротации и async strategy.

Риск:

- Высокий для любых security refactors.

### 5.7 Scheduler/filescan/backup

Факты:

- `core/system.php:379-397`: `addSchedulerTrigger()` формирует async frontend fetch.
- `core/system.php:1591-1594`: frontend вставляет `fetch()` для pseudo scheduler.
- `core/system.php:4361-4411`: `addFilescanTask()` тяжелый, сканирует файлы и пишет dump logs.
- `config/security.php`: `log_d => 0`, `log_b => 0`.
- `storage/logs/scheduler` в момент проверки не содержал активных json-state файлов.

Вывод:

- Filescan/backup не выполняются в обычном request.
- Они потенциально тяжелые, но не доказанная причина текущего `admin.php`/`news`.

Рекомендуемые фиксы:

1. Scheduler jobs выполнять только отдельным cron/CLI worker.
2. Не запускать тяжелые jobs через пользовательский frontend request.
3. Для filescan добавить lock, batch scan, progress state.

Риск:

- Средний.

## 6. SQL и БД

Проверенные страницы:

- `/`: `3` SQL, DB `0.002-0.004 сек`.
- `/index.php?name=news`: `8` SQL, DB `0.006 сек`.
- `/admin.php`: `17` SQL, DB `0.005-0.013 сек`.

Вывод:

- БД не является причиной найденного секундного slowdown.
- В admin dashboard SQL можно улучшать, но это не даст главный эффект.

Рекомендации:

1. В admin counters заменить `SELECT id FROM ...` на `SELECT COUNT(*)`.
2. Сгруппировать counters по таблицам, где возможно.
3. Кешировать counters на короткий TTL.
4. Логировать slow SQL отдельно от общего page generation.

## 7. Приоритет оптимизации

| Приоритет | Что оптимизировать | Ожидаемый эффект | Риск | Архитектура |
|---:|---|---:|---:|---|
| P0 | Changelog GitHub cache miss | убрать `3-4 сек` на главной | низкий/средний | нужна async/stale cache политика |
| P1 | Template in-request FS cache | `20-50 ms` на heavy pages | средний | нет |
| P1 | Config compiled cache/fingerprint policy | `7-20+ ms`, меньше cold FS | средний | частично |
| P1 | Admin modules/icons/lang cache | `10-40 ms` на admin | низкий/средний | нет |
| P2 | Admin counters aggregation/cache | меньше SQL и PHP overhead | низкий | нет |
| P2 | Asset discovery cache | `2-10+ ms` | низкий | нет |
| P2 | Blocks cache | зависит от блоков | средний | частично |
| P3 | Security blocker parsed cache | только при больших lists | средний | нет |

## 8. Что не трогать первым

- SQL как главную причину: DB time низкий.
- `dump_log.log`: не подтвержден.
- Security refactor: риск выше пользы.
- Filescan/backup: не запускаются на проверенных ordinary requests.
- Глубокий рефактор шаблонизатора до исправления changelog: эффект меньше.

## 9. Рекомендуемый план исправлений

### Быстрые безопасные фиксы

1. Увеличить `changelog.cachettl`.
2. Уменьшить frontend `changelog.limit`.
3. Для главной использовать отдельный lightweight limit, например 20-50 commits.
4. Добавить fallback: при GitHub error/cache miss не блокировать страницу дольше короткого timeout.

### Средние фиксы

1. Реализовать stale-while-revalidate для changelog.
2. Перенести GitHub cache refresh в scheduler/admin action.
3. Добавить lock на changelog refresh, чтобы несколько пользователей не запускали параллельные GitHub fetches.
4. Добавить in-request caches в Template для `filemtime`, `realpath`, `is_file`.
5. Кешировать admin module discovery.

### Рискованные фиксы

1. Полный compiled config cache.
2. Изменение lifecycle scheduler/jobs.
3. Глубокая переработка template engine.
4. Перенос всех counters/blocks на persistent cache.

## 10. Итог

Наиболее вероятная и подтвержденная причина большого cold slowdown:

- `changelog` на главной делает синхронный GitHub API fetch при cache miss.

Вторичные причины:

- холодный PHP-FCGI/OPcache/Windows FS после рестарта;
- высокий template call count;
- config fingerprint на каждый request;
- admin dashboard module/counter/layout сборка.

Что проверять первым в задаче на исправление:

1. `modules/changelog/common.php`: cache miss strategy.
2. `config/changelog.php`: frontend limit/cache TTL.
3. `core/classes/template.php`: in-request FS metadata cache.
4. `core/system.php:getConfig()`: fingerprint policy.
5. `admin/index.php` + `core/admin.php`: module/menu/counter cache.

## 11. Самопроверка и ограничения

Покрыто:

- frontend `/`;
- frontend `/index.php?name=news`;
- залогиненный `/admin.php`;
- changelog cache miss/cache hit;
- template cache miss/cache hit;
- PHP-FCGI reset;
- SQL count/time;
- security/logs/filescan/scheduler/backup по коду и состоянию файлов;
- отсутствие постоянных профилировочных правок после анализа.

Не подтверждено:

- 4-секундный slowdown именно на `admin.php` в headless Playwright не воспроизвелся.
- Полный cold start всей OSPanel-группы процессов не был профилирован до первого ручного открытия страницы.
- Live Edge/Chrome tab не был подключен через CDP: порт `127.0.0.1:9222` был закрыт.

Ограничение вывода:

- Для `/` причина доказана замером cache miss.
- Для `/admin.php` доказаны только постоянные узкие места и вероятный cold-start класс проблемы; точная 4-секундная причина требует профиля первого ручного запуска с уже открытым browser session/CDP или отдельного low-level OSPanel/PHP-FCGI профиля.
