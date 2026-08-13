# SLAED CMS: fallback загрузок без PHP Fileinfo

## Задача для Opus 5

Исправить загрузку всех 21 разрешённых типов на PHP без Fileinfo. Решение должно работать в чистой поставке SLAED без Composer и `vendor`, не ослаблять проверку содержимого и не менять маршруты, JSON, result shape или БД.

Проверяется окружение, в котором отсутствует только Fileinfo; существующие обязательные возможности конкретного потока сохраняются: GD для изображений и cURL для remote upload.

## Причина

Production PHP 8.4.22 FPM/FastCGI собран с `--disable-fileinfo`.

- `core/classes/upload.php:216` возвращает `unsupported` до проверки входа, размера и расширения.
- `core/classes/upload.php:124` по той же причине блокирует remote upload.
- `core/classes/upload.php:255-263` возвращает `null`, если `finfo` отсутствует или не создаётся.
- `core/system.php:4270` показывает `unsupported` как ошибку формата.

## Важный контракт имени файла

Локальный файл проверяется до переименования:

1. расширение берётся из исходного `$_FILES['name']` в `core/classes/upload.php:90`;
2. временный файл проверяется по пути `tmp_name`;
3. только после успешной проверки файл перемещается в `.part`;
4. итоговое имя формируется как безопасные base, salt, owner и уже подтверждённое расширение в `core/classes/upload.php:564-589`.

Fallback не должен определять тип по новому имени. Он принимает временный `$path` и исходный `$ext`, проверяет их соответствие и возвращает canonical MIME. Для remote upload `$ext` берётся из path конечного URL после redirects до публикации.

## Решение

### 1. Исправить порядок проверок

В `checkUploadInput()` оставить:

1. наличие разрешённых типов;
2. структуру `$_FILES`;
3. PHP upload error;
4. временный файл и `is_uploaded_file()`;
5. фактический размер.

После этого проверить исходное расширение через `checkTypePolicy()`, затем содержимое. Отсутствие Fileinfo не должно маскировать `missing`, `size`, `transfer` или `extension`.

### 2. Сделать единый content validator

Изменить `getFileMime()` так, чтобы он принимал `$path` и проверенный `$ext`:

1. если `finfo` доступен, использовать текущий MIME reader;
2. если `finfo` недоступен или не смог определить MIME, вызвать внутренний fallback для конкретного `$ext`;
3. вернуть canonical MIME только после успешной проверки структуры;
4. вернуть пустую строку при несоответствии или повреждении.

Один и тот же validator использовать в local и remote upload. `$_FILES['type']` и HTTP `Content-Type` не использовать.

Fallback должен читать файл через `fopen()`/`fread()`/`fseek()`, проверять размеры и offsets и не загружать весь файл в память.

### 3. Проверить все 21 формата

| Типы | Обязательная fallback-проверка | Canonical MIME |
|---|---|---|
| `gif`, `jpg`, `jpeg`, `png`, `webp`, `avif` | `getimagesize()`, точное соответствие `IMAGETYPE_*`, limits и успешный `imagecreatefrom*()` | соответствующий `image/*` |
| `mp3` | корректный ID3v2 header с допустимым synchsafe size либо MPEG frame header; проверить минимум два последовательных совместимых frame headers | `audio/mpeg` |
| `wav` | `RIFF` + объявленный размер + `WAVE`; пройти chunks и подтвердить корректные `fmt ` и `data` в границах файла | `audio/wav` |
| `flac` | `fLaC`; пройти metadata blocks, проверить обязательный `STREAMINFO` длиной 34 и границы последнего block | `audio/flac` |
| `ogg`, `oga` | пройти минимум первую Ogg page: `OggS`, version, segment table, page bounds и checksum; подтвердить audio codec Vorbis, Opus или FLAC | `audio/ogg` |
| `opus` | та же Ogg-проверка и обязательный `OpusHead` в первом packet | `audio/ogg` |
| `m4a` | пройти ISO BMFF boxes; валидный `ftyp`, допустимый brand и audio handler `soun` | `audio/mp4` |
| `mp4` | пройти ISO BMFF boxes; валидный `ftyp`, допустимый MP4 brand и непротиворечивые box sizes | `video/mp4` |
| `webm` | разобрать EBML header и подтвердить `DocType=webm`; проверить размеры EBML elements до `Segment`/`Tracks` | `video/webm` |
| `pdf` | `%PDF-1.x`/`%PDF-2.x` в допустимой header-зоне, корректная версия и `%%EOF` в ограниченной tail-зоне | `application/pdf` |
| `zip` | проверить EOCD, central directory, record counts, offsets и local-header references; если доступен `ZipArchive`, дополнительно требовать успешный `open()` | `application/zip` |
| `rar` | распознать RAR4/RAR5 marker и пройти block headers с проверкой размеров в границах файла | `application/vnd.rar` |
| `gz` | проверить RFC 1952 header, flags, optional fields и trailer; при наличии zlib потоково проверить compressed stream и CRC/ISIZE | `application/gzip` |
| `7z` | проверить signature header, version, Start Header CRC, Next Header offset/size в границах файла и Next Header CRC | `application/x-7z-compressed` |
| `tar` | пройти 512-byte headers, octal sizes, header checksums и data padding; потребовать два завершающих zero blocks | `application/x-tar` |

Это format-specific validators, а не универсальная таблица первых байтов. Одной сигнатуры недостаточно. Если обязательную структуру формата проверить нельзя, файл получает `mime` и не публикуется.

Не запускать shell-команды `file`, `ffprobe`, `unzip` и подобные. Не добавлять Composer-пакеты или новые PHP extensions как обязательное условие fallback.

### 4. Поведение при Fileinfo

Fileinfo остаётся первым способом определения MIME. Fallback используется:

- когда класс `finfo` отсутствует;
- когда `new finfo(FILEINFO_MIME_TYPE)` завершается ошибкой;
- когда `finfo->file()` вернул пустое или неизвестное значение.

Если Fileinfo вернул MIME, не соответствующий `$ext`, файл отклоняется. Не использовать fallback для обхода явного конфликта Fileinfo с расширением.

### 5. Remote upload

В `addRemoteFile()` убрать Fileinfo из раннего условия, но сохранить отдельную проверку cURL.

После скачивания:

1. получить `$ext` из path конечного URL;
2. проверить его через `checkTypePolicy()`;
3. передать `.part` и `$ext` в общий content validator;
4. при любом отказе удалить `.part`;
5. публиковать под новым именем только после успеха.

Сохранить текущие SSRF, DNS, redirect, TLS, destination и byte-limit checks.

### 6. Тестовый seam

Изменить visibility существующего `getTypeReader()` с `private` на `protected`, сохранив `?finfo`. Test subclass должен уметь вернуть `null` при работающих GD, cURL, zlib и других доступных native extensions.

Не использовать `php -n`, глобальный flag или production bypass.

### 7. Ошибка и лог

`unsupported` оставить для отсутствующей серверной capability, например cURL или нужного image decoder. Несоответствие либо повреждение файла возвращает `mime` или существующий `image`.

Для `unsupported` добавить отдельный нейтральный пользовательский текст. Обновить mapping в:

- `core/system.php::getUploadFailText()`;
- `modules/files/index.php`;
- `modules/files/admin/index.php`;
- `modules/account/index.php`.

Если нужна новая global constant, добавить её во все шесть `lang/{de,en,fr,pl,ru,uk}.php`.

В журнале различать `fileinfo_missing`, `fileinfo_init_failed`, `decoder_missing` и `curl_missing`. Result shape и JSON не расширять.

## Обязательные тесты

### Автоматические

- валидный fixture каждого из 21 расширения проходит без Fileinfo;
- каждый fixture публикуется под новым именем с подтверждённым расширением;
- одно и то же содержимое под каждым несовместимым разрешённым расширением отклоняется;
- truncated header, неверный size/offset/checksum и отсутствующий обязательный block каждого container отклоняются;
- polyglot с допустимой первой сигнатурой, но неверной дальнейшей структурой отклоняется;
- `size`, `extension`, `missing` и `transfer` имеют приоритет над MIME capability;
- Fileinfo-path сохраняет текущее поведение;
- remote использует тот же validator и удаляет `.part` после каждого отказа;
- восьмиключевой result shape не меняется.

Заменить синтетические минимальные bodies в `tests/Support/upload_probe.php:156-170` реальными структурно корректными fixtures там, где новые validators требуют полную структуру.

### HTTP

| Поток | Проверка |
|---|---|
| Editor `files` и `forum` | Изображение проходит без Fileinfo, JSON и новое имя корректны |
| Frontend/admin `files` | `zip`, `gz`, `7z`, `rar`, `tar` проходят без Fileinfo; файл и строка БД корректны |
| Admin `fmupload` local | По одному формату каждой группы проходит без Fileinfo |
| Admin `fmupload` remote | По одному формату каждой группы проходит, `.part` отсутствует |
| Подмена расширения | Отказ, final file, `.part` и новая строка БД отсутствуют |

HTTP-проверки без Fileinfo выполнять в отдельном Web SAPI окружении. Test subclass не считается HTTP-проверкой.

## Проверка

1. `php -l` для изменённых PHP-файлов.
2. `UploadContractTest`, `UploadFormatTest`, `UploadIntegrationTest` и новые fallback tests.
3. PHPStan изменённой области.
4. `php-cs-fixer --dry-run` для изменённых PHP-файлов.
5. HTTP-сценарии из таблицы.
6. Проверить `error_file.log`, `error_php.log`, `error_site.log`, `error_sql.log`.

## Критерии приёмки

- Все 21 типа проходят без Fileinfo только при соответствии содержимого исходному расширению.
- Проверка завершается до перемещения и переименования local file.
- Итоговое безопасное имя содержит только подтверждённое расширение.
- Fileinfo остаётся основным reader, fallback не скрывает явный MIME conflict.
- Повреждённые, подменённые и неполные файлы не публикуются.
- Local и remote используют один validator и не оставляют `.part` после отказа.
- Решение не зависит от Composer, `vendor`, shell utilities и новых обязательных extensions.
- Маршруты, JSON, result shape и БД не изменены.
- Все автоматические и HTTP-проверки проходят.
