# SLAED CMS 6.3 — File Manager / File Browser
## Концепт и план интеграции

**Статус:** архитектурный план  
**Целевая версия:** SLAED CMS 6.3 Phoenix  
**Принцип:** развить существующую подсистему `uploads`, не внедряя сторонний файловый менеджер.

---

## 1. Цель

Создать единый файловый слой SLAED, который используется в трёх сценариях:

1. **Администратор → Загрузки → Каталог файлов** — полноценный файловый менеджер для `uploads/*`.
2. **ToastUI → Файловый менеджер** — компактный браузер разрешённых файлов модуля для загрузки, выбора и вставки в материал.
3. **Администратор → Загрузки → Системные файлы** — отдельная вкладка для просмотра и редактирования файлов SLAED внутри `BASE_DIR`.

Главная схема:

```text
                         FileManager
                             │
              ┌──────────────┼──────────────┐
              │              │              │
              ▼              ▼              ▼
        Uploads Admin     ToastUI       System Files
          FULL MODE      COMPACT MODE      FULL MODE
              │              │              │
         uploads/*       uploads/mod      BASE_DIR
```

---

## 2. Что уже есть в SLAED 6.3

### 2.1. `admin/modules/uploads.php`

Модуль уже является естественным владельцем будущего File Manager:

- выбор upload-директории;
- загрузка файла с компьютера;
- загрузка файла по URL;
- правила расширений;
- лимиты размеров и квот;
- ограничения изображений;
- количество файлов;
- основная директория;
- дополнительная директория `thumb`;
- файловые списки через HTMX;
- настройка шаблонов отображения файлов.

Поэтому новый отдельный `admin/modules/filemanager.php` **не создаётся**.

### 2.2. `core/classes/upload.php`

Класс `Upload` уже отвечает за безопасную публикацию новых файлов:

- проверка extension;
- проверка MIME;
- проверка изображений;
- quota;
- maxbytes;
- maxwidth/maxheight;
- collision-free naming;
- временный `.part`;
- lock;
- atomic publication;
- remote URL security;
- удаление файлов, созданных Upload service.

`Upload` остаётся специализированным сервисом загрузки и **не превращается в File Manager**.

### 2.3. ToastUI

У ToastUI уже реализована существенная часть интеграции:

```text
editorUpload
editorFiles
```

Редактор уже получает:

- module upload rule;
- CSRF token;
- extensions;
- maxquota;
- maxbytes;
- maxwidth;
- maxheight;
- maxfiles;
- список сохранённых файлов;
- drag & drop;
- upload;
- attachment/embed modes;
- собственное окно «Файловый менеджер».

Это сохраняется и развивается.

### 2.4. `Editor::getCode()`

Для системных текстовых файлов уже существует CodeMirror 6 через:

```php
Editor::getCode(...)
```

Отдельный code editor для File Manager не нужен.

---

## 3. Основные архитектурные решения

### Решение 1. Хозяин File Manager — `uploads`

UI интегрируется в:

```text
admin/modules/uploads.php
```

Главные вкладки:

```text
[ Каталог файлов ]
[ Системные файлы ]
[ Шаблоны ]
[ Конфигурация ]
[ Справка ]
```

### Решение 2. «Системные файлы» — отдельная вкладка

Системная файловая область **не добавляется** в selector:

```text
Директория: uploads/all
```

Upload files и system files имеют разные права, разные риски и разные политики.

### Решение 3. Один filesystem engine

Создаётся общий backend:

```text
FileManager
```

Он работает с разными контекстами:

```text
uploads
system
editor
```

### Решение 4. `Upload` и `FileManager` разделены

```text
Upload
└── принимает и безопасно публикует новые upload-файлы

FileManager
└── читает и управляет уже существующим filesystem
```

`FileManager` вызывает `Upload`, когда операция действительно является upload.

### Решение 5. Общий backend, но не один универсальный HTTP endpoint

Админка использует HTMX/HTML. ToastUI использует JSON.

Объединяется **domain layer и модель файлов**, а не транспорт:

```text
                     FileManager
                         │
            ┌────────────┴────────────┐
            │                         │
        Admin adapter            Editor adapter
        HTMX / HTML                 JSON
```

### Решение 6. Никаких сторонних SPA/file-manager frameworks

Не добавлять:

- Vue;
- React;
- CodeIgniter;
- jQuery;
- jQuery UI;
- elFinder backend;
- eXtplorer backend;
- Tiny File Manager runtime.

UI:

```text
Bootstrap 5
Bootstrap Icons
HTMX
Vanilla JS
```

---

## 4. Компоненты

Рекомендуемая структура:

```text
core/classes/filemanager.php
admin/modules/uploads.php

templates/admin/partials/
├── file-browser.html
├── file-browser-toolbar.html
├── file-browser-tree.html
├── file-browser-list.html
├── file-browser-editor.html
├── file-browser-preview.html
└── file-browser-dialogs.html

templates/admin/fragments/
├── file-browser-row.html
├── file-browser-tree-node.html
└── file-browser-breadcrumb.html

templates/admin/assets/js/
└── file-browser.js

templates/admin/assets/css/
└── file-browser.css
```

ToastUI сохраняет собственный wrapper:

```text
templates/<theme>/partials/editor-toastui-files.html
```

но получает данные через тот же FileManager domain layer.

---

## 5. Ответственность `FileManager`

Минимальный публичный контракт:

```text
list()
stat()
read()
write()
createFile()
createDirectory()
delete()
rename()
copy()
move()
```

Дополнительно:

```text
getDescriptor()
getCapabilities()
isEditable()
getEditorLanguage()
```

Upload выполняется через:

```text
Upload::addUploadedFile()
Upload::addUploadedFiles()
Upload::addRemoteFile()
```

а не собственной реализацией FileManager.

---

## 6. File context

Каждый запрос FileManager выполняется внутри заранее созданного контекста.

### Upload context

```text
mode     = uploads
root     = UPLOADS_DIR/<module>
access   = upload rules / admin
editor   = false
```

### ToastUI context

```text
mode     = editor
root     = UPLOADS_DIR/<module>
access   = module upload rules + owner
editor   = false
compact  = true
```

### System context

```text
mode     = system
root     = BASE_DIR
access   = isAdmin(true)
editor   = true
```

Клиент никогда не задаёт физический root. Он передаёт только относительный путь внутри уже выбранного context.

---

## 7. Нормализованная модель файла

FileManager должен возвращать единый descriptor:

```php
[
    'name'         => 'picture.webp',
    'path'         => 'news/picture.webp',
    'kind'         => 'image',
    'extension'    => 'webp',
    'size'         => 245760,
    'mtime'        => 1786082400,
    'url'          => 'uploads/news/picture.webp',
    'thumbnail'    => '',
    'width'        => 1280,
    'height'       => 720,
    'managed'      => true,
    'editable'     => false,
    'previewable'  => true,
    'capabilities' => [...],
]
```

Абсолютный серверный путь в browser/JSON **никогда не отдаётся**.

---

## 8. UI — «Каталог файлов»

Текущий экран сохраняет существующую структуру:

```text
Загрузки
[ Каталог файлов ] [ Системные файлы ] [ Шаблоны ] [ Конфигурация ] [ Справка ]

Директория: [ uploads/all ▼ ]

[ Файловый менеджер ] [ Основная директория ] [ Дополнительная директория ]
```

На первом этапе старые под-вкладки не удаляются.

### Новый «Файловый менеджер»

```text
┌───────────────────────────────────────────────────────────────┐
│ ← ↑ ⟳ │ uploads / all              🔍 Поиск   ⬆ Загрузить   │
├────────────────────┬──────────────────────────────────────────┤
│ 📁 uploads         │ Имя              Размер      Изменён     │
│ ├─ 📁 all          │ 📁 thumb                                 │
│ ├─ 📁 news         │ 🖼 image.webp     245 KB      Сегодня    │
│ ├─ 📁 files        │ 📄 manual.pdf     1.2 MB      Вчера      │
│ └─ 📁 forum        │ 📦 archive.zip    4.7 MB      05.08.26   │
└────────────────────┴──────────────────────────────────────────┘
```

Основные функции:

- breadcrumb;
- refresh;
- search/filter;
- list view;
- grid view позже;
- сортировка;
- preview;
- download;
- upload;
- delete;
- file information;
- pagination/limit.

### Важное ограничение для managed uploads

Автоматически созданные Upload service имена содержат служебную информацию/ownership.

Поэтому в первой версии для managed upload-файлов **не разрешать произвольные**:

- rename;
- move;
- изменение имени owner suffix.

Кроме безопасности, rename/move ломает уже сохранённые ссылки в материалах.

Поддерживаемые операции v1:

```text
browse
preview
upload
download
delete
compress
```

Rename/move для uploads можно добавить только отдельным проектом с учётом ссылок и ownership.

---

## 9. UI — ToastUI Compact Mode

Текущее окно сохраняется.

Обычный режим:

```text
┌─────────────────────────────────────────────────────┐
│ Файловый менеджер                           ↗   ×   │
├─────────────────────────────────────────────────────┤
│ Ограничения                                         │
├─────────────────────────────────────────────────────┤
│ Выбрать файл                                        │
│ [ Перетащите файл сюда...                        ]  │
│                                         [Обновить]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│                  ФАЙЛЫ МОДУЛЯ                      │
│                                                     │
└─────────────────────────────────────────────────────┘
```

В нижней части вместо «Нет информации» выводится File Browser catalogue.

### Compact view

Показывать:

- preview/icon;
- filename;
- size;
- select/insert;
- refresh;
- последние N файлов.

Не показывать:

- дерево всего `uploads`;
- system directories;
- rename;
- move;
- delete;
- raw filesystem paths.

### Expanded view

Существующая кнопка expand используется для расширенного браузера:

- search;
- больше файлов;
- list/grid;
- preview;
- pagination;
- file metadata.

ToastUI всё равно остаётся внутри разрешённого module root.

---

## 10. UI — «Системные файлы»

Новая отдельная главная вкладка:

```text
[ Каталог файлов ] [ Системные файлы ] [ Шаблоны ] [ Конфигурация ] [ Справка ]
```

Доступ:

```php
isAdmin(true)
```

Root:

```text
BASE_DIR
```

Пример:

```text
┌───────────────────┬────────────────────────────────────────────┐
│ 📁 SLAED          │ Имя               Размер       Изменён     │
│ ├─ 📁 admin       │ 📁 admin                                   │
│ ├─ 📁 blocks      │ 📁 config                                  │
│ ├─ 📁 config      │ 📁 core                                    │
│ ├─ 📁 core        │ 📁 modules                                 │
│ ├─ 📁 modules     │ 📁 plugins                                 │
│ ├─ 📁 plugins     │ 📁 templates                               │
│ └─ 📁 templates   │ 📄 index.php       19 KB       Сегодня     │
└───────────────────┴────────────────────────────────────────────┘
```

### Открытие текстового файла

Правая часть переключается из списка в editor view:

```text
templates / lite / style.css

← Назад                     style.css                 Сохранить
───────────────────────────────────────────────────────────────
                         CodeMirror 6
───────────────────────────────────────────────────────────────
```

Используется только:

```php
Editor::getCode(...)
```

---

## 11. Определение типа системного файла

Resolver:

```text
.php                 → php
.html .htm .tpl      → html
.css                 → css
.js                  → js
.json                → json
.sql                 → sql
.xml                 → xml
.txt .md .ini .log   → text
```

Изображения:

```text
png jpg jpeg gif webp avif svg
```

→ preview.

PDF → preview/download.

Неизвестный binary → info/download, без editor.

Markdown в System Files по умолчанию открывается как source через CodeMirror `text`.
ToastUI для raw `.md` автоматически не используется.

---

## 12. System Files security boundary

Это отдельная политика от Upload.

### Обязательные правила

1. Root всегда определяется сервером.
2. Browser передаёт только относительный path.
3. Все пути нормализуются.
4. `..` не может выйти за root.
5. Запрещены absolute paths.
6. Запрещены stream wrappers: `php://`, `file://`, `data://` и т.п.
7. Проверяется symlink escape.
8. Mutating operations — только POST.
9. Все mutating requests — CSRF protected.
10. Никакого shell execution.
11. Никакого chmod/chown в v1.
12. Никакого произвольного archive extraction в v1.

### Hidden/protected paths

По умолчанию скрыть или отдельно защищать:

```text
.git/
.env
storage/sessions/
lock/temp/part files
```

Точный список должен быть централизован в system policy.

### Critical files

Критические файлы можно редактировать super-admin, но delete/rename для них запрещён или требует отдельной policy:

```text
index.php
admin.php
setup.php
.htaccess
config/*
core/*
```

Не размазывать эти проверки по controller.

---

## 13. Безопасное сохранение исходников

Обычный:

```php
file_put_contents($file, $text)
```

для System File Editor недостаточен.

При `open` сервер отдаёт version/hash. При `save` browser возвращает version.

Алгоритм:

```text
open
 ↓
read file
 ↓
calculate version
 ↓
edit
 ↓
save(version)
 ↓
compare current version
 ├─ same    → write
 └─ changed → HTTP 409 / conflict
```

Это предотвращает тихое перетирание файла, изменённого другим процессом.

Сохранение:

1. validate path;
2. validate writable;
3. create temp in same directory;
4. write complete content;
5. flush/close;
6. preserve permissions where applicable;
7. atomic rename/replace;
8. return new version.

Пустой файл должен сохраняться корректно.

---

## 14. Capabilities

UI не должен самостоятельно вычислять права по роли.

FileManager/context возвращает capabilities.

### Admin uploads

```text
browse       yes
preview      yes
upload       yes
download     yes
delete       yes
rename       no for managed files
move         no for managed files
edit         no
```

### ToastUI

```text
browse       according to module rule
upload       according to module rule
insert       yes when file visible
embed        according to editor contract
delete       no
rename       no
move         no
edit         no
```

### System

```text
browse       super-admin
preview      yes
edit         text/code files
create       policy
rename       policy
copy         policy
move         policy
delete       policy
download     yes
```

---

## 15. Existing editor upload rules remain authoritative

Настройки upload-модуля остаются единственной точкой принятия решений для content editor.

File Manager не создаёт второй набор:

```text
editor_allow_upload
editor_allow_images
filemanager_allow_user
...
```

Используются существующие:

```text
extensions
maxquota
maxbytes
maxwidth
maxheight
maxfiles
thumbwidth
adminlist
moderfiles
userfiles
userupload
guestupload
guestfiles
```

`guestfiles` реализуется согласно существующему плану `docs/EDITOR-UPLOADS-2026.md`.

---

## 16. Ownership upload-файлов

До подключения общего catalogue к ToastUI необходимо завершить текущую реформу ownership.

Текущая проблема:

```text
guest owner = 0
```

поэтому исторический список guest-файлов нельзя безопасно показывать.

Целевая модель:

```text
authenticated user → user id owner
guest              → per-session opaque owner token
moderator/admin    → explicit privilege, не null как скрытый сигнал
```

FileManager не должен самостоятельно разбирать имя файла в каждом клиенте.
Разбор managed filename/owner должен существовать в одном месте.

---

## 17. HTTP / routing

### ToastUI

Сохраняем существующий contract:

```text
index.php?go=4&op=editorUpload&mod=...
index.php?go=4&op=editorFiles&mod=...
```

Они становятся adapters над общим FileManager/Upload layer.

### Admin read operations

Можно сохранить `go=5` для HTMX fragments:

```text
getAdminFileBrowser
getAdminFileList
getAdminFileTree
getAdminFilePreview
```

### Admin write operations

Не использовать GET.

Выполнять POST через `admin.php?name=uploads`:

```text
op=fmupload
op=fmdelete
op=fmmkdir
op=fmrename
op=fmcopy
op=fmmove
op=fmsave
```

Все через `checkAdminPost(...)` или эквивалентный scoped CSRF contract.

### Важная миграция

Текущее удаление в `getAdminUploadFiles()` выполняется через GET/HTMX GET.
При переходе на File Manager delete/compress должны стать POST-actions.

---

## 18. HTMX / JavaScript

HTMX используется для:

- directory navigation;
- tree lazy-load;
- list refresh;
- pagination;
- preview panel;
- editor open/close;
- save response;
- dialogs where удобно.

Vanilla JS используется для:

- Ctrl/Shift multi-select;
- keyboard navigation;
- context menu;
- drag & drop upload;
- list/grid switch;
- local UI state;
- unsaved editor guard.

Не строить SPA router.
URL страницы остаётся обычным SLAED admin URL.

---

## 19. Search

Первая версия:

```text
filter current directory
```

без рекурсивного обхода всего сайта.

Позже:

```text
recursive search
```

только по явному запросу и с ограничением количества результатов/времени.

---

## 20. Preview

### Upload mode

Preview:

- image;
- audio;
- video;
- PDF;
- text metadata;
- archive metadata.

Не исполнять HTML/JS.
SVG отображать безопасно, без выполнения произвольного script в admin origin.

### System mode

Preview для media. Text/code сразу открывается CodeMirror.

---

## 21. Работа с архивами

V1:

```text
download
compress existing upload file
```

Существующую функцию compress можно сохранить через новый POST action.

Не делать в первой версии:

```text
extract arbitrary ZIP/TAR into filesystem
```

Если extraction добавляется позже:

- canonical destination;
- запрет `../`;
- запрет absolute entry names;
- symlink policy;
- file count limit;
- total extracted bytes limit;
- overwrite policy.

---

## 22. Что делать с текущими под-вкладками uploads

Сейчас:

```text
[ Файловый менеджер ]
[ Основная директория ]
[ Дополнительная директория ]
```

На первом этапе оставить без изменений.

### Phase A

Новый File Browser появляется в «Файловый менеджер».
Старые «Основная директория» и «Дополнительная директория» продолжают работать.

### Phase B

После стабилизации File Browser сравнить функциональность.
Если всё покрыто, legacy lists можно убрать или превратить в быстрые views.

---

## 23. Что делать с `admin/modules/editor.php`

Не переносить File Manager туда.

`editor.php` остаётся специализированным quick editor системных файлов:

```text
system.php
header.php
.htaccess
robots.txt
```

Новая вкладка «Системные файлы» даёт общий filesystem browser.

В будущем quick editor может:

- остаться как быстрый инструмент;
- либо открыть соответствующий файл в `Uploads → System Files`.

Это не является блокером File Manager.

---

## 24. Logging

Для system mutating operations желательно писать audit entry:

```text
admin
operation
relative path
target path
timestamp
result
```

Особенно:

```text
save
delete
rename
move
upload
```

Содержимое файла в log не писать.

---

## 25. Производительность

### Directory listing

Не сканировать рекурсивно всё дерево.

Использовать:

```text
lazy tree
current-directory listing
pagination
```

### Metadata

Не выполнять дорогой `getimagesize()` для каждого неизвестного файла без проверки extension/type.

### Hash

Полный hash нужен для редактируемых text/code файлов при open/save.
Для обычного directory list hash не вычисляется.

---

## 26. Этапы реализации

### Phase 0 — завершить Editor Uploads contract

Закрыть `docs/EDITOR-UPLOADS-2026.md`:

- `guestfiles`;
- guest session ownership;
- explicit moderator privilege;
- unified ToastUI image/file window;
- unconditional blob hook;
- embed limits/write limits.

**Результат:** существующая editor upload модель становится стабильной основой.

### Phase 1 — FileManager core, read-only

Создать `core/classes/filemanager.php`.

Реализовать:

- contexts;
- root guard;
- canonical path;
- descriptor;
- list;
- stat;
- preview metadata;
- capabilities.

Без delete/write.

**Результат:** безопасный общий filesystem read layer.

### Phase 2 — System Files read-only UI

Добавить вкладку:

```text
Системные файлы
```

Реализовать:

- tree;
- breadcrumb;
- list;
- file info;
- preview.

**Результат:** проверка архитектуры без риска изменения файлов.

### Phase 3 — System Code Editor

Добавить:

- text detection;
- CodeMirror mapping;
- open;
- version/hash;
- atomic save;
- conflict detection;
- unsaved guard.

**Результат:** полноценный web code editor SLAED.

### Phase 4 — Uploads File Browser

Подключить тот же domain layer к:

```text
Каталог файлов → Файловый менеджер
```

Реализовать:

- list;
- preview;
- upload;
- download;
- delete;
- compress;
- search;
- pagination.

Существующие main/thumb lists пока оставить.

### Phase 5 — ToastUI catalogue adapter

Перевести `editorFiles` на FileManager descriptor.

В существующем окне ToastUI:

- compact catalogue;
- expanded catalogue;
- search;
- preview;
- select/insert.

Upload продолжает идти через `Upload`.

### Phase 6 — Desktop UX

После стабилизации backend:

- multi-select;
- keyboard;
- context menu;
- grid view;
- thumbnails;
- drag & drop;
- bulk operations where policy permits.

### Phase 7 — Cleanup

После проверки:

- убрать дублирующие list implementations;
- решить судьбу old main/thumb tabs;
- удалить устаревшие GET mutations;
- сократить duplicated filesystem helpers.

---

## 27. Тестирование

### FileManager unit tests

Обязательно:

- normal relative path;
- `../`;
- nested `../../`;
- Windows `..\\`;
- absolute Unix path;
- Windows drive path;
- NUL;
- stream wrapper;
- symlink inside root;
- symlink outside root;
- non-existing parent;
- UTF-8 filename;
- hidden file;
- empty file;
- unreadable file;
- unwritable directory.

### System editor tests

- PHP open/save;
- CSS/JS/JSON/text;
- empty save;
- binary refused;
- file changed after open → conflict;
- atomic replacement;
- permission preservation;
- CSRF denied;
- non-super-admin denied.

### Upload catalogue tests

- module root isolation;
- invalid module;
- owner filtering;
- user file filtering;
- guest session isolation;
- moderator list;
- admin list;
- quota;
- max file count;
- image dimensions;
- unsupported extension;
- delete managed file;
- thumb handling.

### Routing tests

- no mutating GET;
- correct CSRF scope;
- `go=4` editor contract unchanged;
- `go=5` admin reads super-admin only;
- admin writes POST only.

---

## 28. Acceptance criteria

File Manager считается готовым, когда:

- один domain layer используется Admin и ToastUI;
- Upload service не дублирован;
- System Files имеет отдельную вкладку;
- System Files доступен только super-admin;
- browser никогда не получает absolute server path;
- path traversal и symlink escape закрыты;
- system edit использует существующий `Editor::getCode()`;
- save имеет conflict detection;
- save выполняется безопасно;
- mutating actions не работают через GET;
- ToastUI не видит чужие upload roots/files;
- текущие module upload rules остаются authoritative;
- существующая загрузка файлов не ломается;
- текущая ToastUI интеграция не заменяется новым editor subsystem;
- нет jQuery/Vue/React/стороннего File Manager runtime.

---

## 29. Out of scope для первой версии

Не реализовывать сразу:

- FTP;
- SFTP;
- S3;
- WebDAV;
- Git client;
- shell/terminal;
- chmod/chown UI;
- recursive full-site search;
- automatic references update after rename;
- arbitrary archive extraction;
- file version history;
- recycle bin;
- remote filesystem mounts.

Это можно добавлять отдельно после стабильного core.

---

## 30. Итоговая архитектура

```text
                              SLAED CMS
                                  │
                    ┌─────────────┴─────────────┐
                    │                           │
             admin/modules/uploads.php      Content Editor
                    │                           │
      ┌─────────────┴─────────────┐          ToastUI
      │                           │             │
Каталог файлов              Системные файлы    │
      │                           │             │
      └─────────────┬─────────────┴─────────────┘
                    │
                    ▼
               FileManager
          filesystem/domain layer
                    │
       ┌────────────┴─────────────┐
       │                          │
    Upload                     Editor
 upload/publish             code/content
       │                          │
 core/classes/upload.php     Editor::getCode()
                                  │
                             CodeMirror 6
```

### Финальное правило

```text
Upload       = безопасно принять новый файл
FileManager  = безопасно работать с filesystem
FileBrowser  = показать filesystem пользователю
ToastUI      = выбрать/вставить разрешённый upload-файл
CodeMirror   = редактировать разрешённый system text/code файл
```

Ни один компонент не дублирует ответственность другого.
