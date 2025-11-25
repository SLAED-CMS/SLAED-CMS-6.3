# Конфигурация SLAED CMS

SLAED CMS использует модульную систему конфигурации, где каждый аспект системы настраивается через отдельные файлы конфигурации.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Структура конфигурации

```
config/
├── 000config.php          # Основная конфигурация БД
├── 000config_global.php   # Глобальные настройки
├── config_core.php        # Настройки ядра
├── config_security.php    # Параметры безопасности
├── config_*.php           # Конфигурации модулей
├── cache/                 # Кэшированные файлы
└── logs/                  # Файлы логов
```

## Основная конфигурация

### База данных (`000config.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confdb = array();
$confdb['host'] = "localhost";              // Хост базы данных
$confdb['uname'] = "username";              // Имя пользователя
$confdb['pass'] = "password";               // Пароль
$confdb['name'] = "database";               // Имя базы данных
$confdb['type'] = "mysqli";                 // Тип БД (mysqli)
$confdb['engine'] = "InnoDB";               // Движок БД
$confdb['charset'] = "utf8mb4";             // Кодировка
$confdb['collate'] = "utf8mb4_unicode_ci";  // Сравнение
$confdb['prefix'] = "slaed";                // Префикс таблиц
$confdb['sync'] = "0";                      // Синхронизация времени
$confdb['mode'] = "0";                      // Строгий режим MySQL

$prefix = "slaed";                          // Префикс (дублирование)
$admin_file = "admin";                      // Имя админ-файла
?>
```

### Глобальные настройки (`000config_global.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$conf = array(
    // Основные настройки сайта
    'sitename' => 'SLAED CMS',              // Название сайта
    'slogan' => 'Быстрая и эффективная CMS', // Слоган
    'homeurl' => 'http://localhost',         // URL сайта
    'email' => 'admin@example.com',          // Email администратора
    
    // Языки и локализация
    'language' => 'russian',                 // Язык по умолчанию
    'multilingual' => '1',                   // Многоязычность
    'alang' => '1',                         // Автоопределение языка
    'charset' => 'UTF-8',                   // Кодировка
    'timezone' => 'Europe/Moscow',          // Часовой пояс
    
    // Модули и отображение
    'module' => 'news,pages',               // Модули главной страницы
    'theme' => 'default',                   // Тема по умолчанию
    'close' => '0',                         // Закрыть сайт (техобслуживание)
    
    // SEO настройки
    'meta_keywords' => 'cms,slaed,website', // Мета-ключевые слова
    'meta_description' => 'SLAED CMS',      // Мета-описание
    'rewrite' => '1',                       // ЧПУ (mod_rewrite)
);
?>
```

## Настройки ядра (`config_core.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$conf = array(
    // Кэширование
    'cache_b' => '1',                       // Кэширование блоков
    'cache_d' => '7',                       // Дни кэширования
    'cache_t' => '3600',                    // Время кэширования (секунды)
    'cache_css' => '1',                     // Кэширование CSS
    'cache_script' => '1',                  // Кэширование JavaScript
    
    // Сжатие
    'css_c' => '1',                         // Сжатие CSS
    'css_h' => '0',                         // CSS в header (inline)
    'css_e' => '0',                         // CSS с base64 изображениями
    'script_c' => '1',                      // Сжатие JavaScript
    'script_h' => '0',                      // JavaScript в header
    'script_a' => '1',                      // Асинхронная загрузка JS
    'html_compress' => '1',                 // Сжатие HTML
    
    // Файлы для загрузки
    'css_f' => 'templates/[theme]/css/,plugins/system/',  // CSS файлы
    'script_f' => 'plugins/jquery/jquery.min.js,plugins/system/core.js', // JS файлы
    
    // Дополнительные настройки
    'variables' => '0,1,0',                 // Отображение переменных (дебаг)
    'foot_time' => '1',                     // Время генерации в футере
    'foot_queries' => '1',                  // Количество запросов в футере
);
?>
```

## Настройки безопасности (`config_security.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confs = array(
    // Общая защита
    'protection' => '1',                    // Включить защиту
    'flood_enable' => '1',                  // Защита от флуда
    'flood_time' => '30',                   // Интервал между действиями (сек)
    'flood_attempts' => '5',                // Количество попыток
    
    // Авторизация
    'login_attempts' => '5',                // Попытки входа
    'login_ban_time' => '3600',            // Время бана (секунды)
    'session_lifetime' => '86400',          // Время жизни сессии
    'remember_time' => '2592000',           // Время "запомнить меня"
    
    // Капча
    'captcha_enable' => '1',                // Включить капчу
    'captcha_type' => 'recaptcha3',         // Тип капчи
    'recaptcha_site_key' => '',             // Ключ сайта reCAPTCHA
    'recaptcha_secret_key' => '',           // Секретный ключ reCAPTCHA
    'recaptcha_score' => '0.5',            // Минимальный score для v3
    
    // Защита от атак
    'csrf_protection' => '1',               // CSRF защита
    'xss_filter' => '1',                   // XSS фильтр
    'sql_injection_filter' => '1',         // Фильтр SQL инъекций
    'file_upload_check' => '1',            // Проверка загружаемых файлов
    
    // IP фильтрация
    'ip_ban_enable' => '1',                // Бан по IP
    'ip_whitelist' => '',                  // Белый список IP
    'ip_blacklist' => '',                  // Черный список IP
    
    // Логирование
    'log_failed_logins' => '1',            // Логировать неудачные входы
    'log_admin_actions' => '1',            // Логировать действия админов
    'log_file_uploads' => '1',             // Логировать загрузки файлов
    
    // Дополнительно
    'password_min_length' => '6',          // Минимальная длина пароля
    'password_require_special' => '0',      // Требовать спецсимволы
    'password_require_numbers' => '1',      // Требовать цифры
    'max_upload_size' => '10240',          // Макс размер загрузки (KB)
);
?>
```

## Настройки загрузки файлов (`config_uploads.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

// Формат: 'модуль' => 'размер_KB|типы_файлов|превью|папки_пользователей|активно'
$confup = array(
    // Модули контента
    'news' => '2048|jpg,jpeg,png,gif|1|0|1',           // Новости
    'pages' => '5120|jpg,jpeg,png,gif,pdf|1|0|1',      // Страницы
    'forum' => '1024|jpg,jpeg,png,gif|1|1|1',          // Форум
    'shop' => '5120|jpg,jpeg,png,gif|1|1|1',           // Магазин
    
    // Медиа контент
    'media' => '10240|jpg,jpeg,png,gif,mp4,avi|1|1|1', // Медиа
    'files' => '51200|zip,rar,pdf,doc,docx,xls,xlsx|0|1|1', // Файлы
    'links' => '512|jpg,jpeg,png,gif|1|0|1',           // Ссылки
    
    // Пользователи
    'avatars' => '512|jpg,jpeg,png,gif|1|1|1',         // Аватары
    'account' => '2048|jpg,jpeg,png,gif,pdf|1|1|1',    // Аккаунты
    
    // Системные
    'content' => '5120|jpg,jpeg,png,gif,pdf|1|0|1',    // Контент
    'voting' => '1024|jpg,jpeg,png,gif|1|0|1',         // Голосования
);

// Настройки обработки изображений
$conf_images = array(
    'quality' => '85',                      // Качество JPEG (1-100)
    'thumb_width' => '150',                 // Ширина превью
    'thumb_height' => '150',                // Высота превью
    'thumb_crop' => '1',                    // Обрезать до размера
    'watermark_enable' => '0',              // Водяные знаки
    'watermark_file' => 'watermark.png',   // Файл водяного знака
    'watermark_position' => 'bottom-right', // Позиция водяного знака
);
?>
```

## Настройки модулей

### Новости (`config_news.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confn = array(
    'storyhome' => '5',                     // Новостей на главной
    'storypage' => '10',                    // Новостей на странице
    'readmore' => '500',                    // Символов для "читать далее"
    'anonymous' => '1',                     // Анонимные комментарии
    'moderation' => '0',                    // Премодерация комментариев
    'rating' => '1',                        // Рейтинг новостей
    'views' => '1',                         // Счетчик просмотров
    'social' => '1',                        // Социальные кнопки
    'rss' => '1',                          // RSS лента
    'sitemap' => '1',                      // Включить в карту сайта
);
?>
```

### Пользователи (`config_users.php`)

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confu = array(
    'registration' => '1',                  // Разрешить регистрацию
    'activation' => '1',                    // Активация по email
    'moderation' => '0',                    // Модерация регистраций
    'avatar_enable' => '1',                 // Разрешить аватары
    'avatar_max_size' => '512',            // Макс размер аватара (KB)
    'signature_enable' => '1',              // Разрешить подписи
    'signature_max_length' => '255',        // Макс длина подписи
    'profile_fields' => '1',               // Дополнительные поля
    'private_messages' => '1',              // Личные сообщения
    'online_users' => '1',                  // Показывать онлайн
    'last_visit' => '1',                   // Показывать последний визит
);
?>
```

## Настройка кэширования

### Файловое кэширование

```php
// В config_core.php
$conf = array(
    'cache_enable' => '1',                  // Включить кэширование
    'cache_lifetime' => '3600',             // Время жизни (секунды)
    'cache_compress' => '1',                // Сжимать кэш
    'cache_prefix' => 'slaed_',            // Префикс кэша
);

// Директории кэша
define('CACHE_DIR', BASE_DIR.'/config/cache');
define('CACHE_BLOCKS_DIR', CACHE_DIR.'/blocks');
define('CACHE_PAGES_DIR', CACHE_DIR.'/pages');
define('CACHE_QUERIES_DIR', CACHE_DIR.'/queries');
```

### Настройка веб-сервера для кэширования

**Apache (.htaccess):**

```apache
# Кэширование статических файлов
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# ETags
<IfModule mod_headers.c>
    Header unset ETag
    FileETag None
</IfModule>
```

**Nginx:**

```nginx
# Кэширование статических файлов
location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
    expires 1M;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept-Encoding";
}
```

## Производительность

### Оптимизация PHP

**php.ini настройки:**

```ini
; Память
memory_limit = 256M
max_execution_time = 300

; OPcache (рекомендуется)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.save_comments = 0
opcache.fast_shutdown = 1

; Загрузка файлов
upload_max_filesize = 32M
post_max_size = 32M
max_file_uploads = 20

; Сессии
session.gc_maxlifetime = 86400
session.cookie_lifetime = 0
```

### Оптимизация MySQL

**my.cnf настройки:**

```ini
[mysqld]
# Буферы
innodb_buffer_pool_size = 256M
innodb_log_buffer_size = 16M
key_buffer_size = 64M

# Логи
innodb_log_file_size = 64M
innodb_flush_log_at_trx_commit = 2

# Кэш запросов
query_cache_type = 1
query_cache_size = 32M
query_cache_limit = 2M

# Соединения
max_connections = 100
max_connect_errors = 10
```

## Среды разработки

### Разработка (`config_dev.php`)

```php
<?php
$conf_dev = array(
    'debug_mode' => '1',                    // Режим отладки
    'error_reporting' => 'E_ALL',          // Уровень ошибок
    'log_queries' => '1',                   // Логировать SQL запросы
    'show_variables' => '1',                // Показывать переменные
    'cache_disable' => '1',                 // Отключить кэш
    'minify_disable' => '1',               // Отключить минификацию
);
?>
```

### Продакшн (`config_prod.php`)

```php
<?php
$conf_prod = array(
    'debug_mode' => '0',                    // Отключить отладку
    'error_reporting' => '0',               // Скрыть ошибки
    'log_errors' => '1',                    // Логировать ошибки
    'cache_enable' => '1',                  // Включить кэш
    'minify_enable' => '1',                // Включить минификацию
    'security_strict' => '1',               // Строгая безопасность
);
?>
```

## Мониторинг и логирование

### Настройки логов (`config_logs.php`)

```php
<?php
$conf_logs = array(
    'log_level' => 'INFO',                  // DEBUG, INFO, WARN, ERROR
    'log_file' => 'system.log',            // Основной лог файл
    'log_rotation' => '1',                  // Ротация логов
    'log_max_size' => '10240',             // Макс размер лога (KB)
    'log_keep_days' => '30',               // Дни хранения логов
    'log_compress' => '1',                  // Сжимать старые логи
);

// Типы логов
$log_types = array(
    'system' => 'system.log',               // Системные события
    'security' => 'security.log',           // События безопасности
    'error' => 'error.log',                 // Ошибки
    'access' => 'access.log',               // Доступ к модулям
    'upload' => 'upload.log',               // Загрузки файлов
);
?>
```

Эта система конфигурации обеспечивает гибкую настройку всех аспектов SLAED CMS при сохранении простоты управления и высокой производительности.