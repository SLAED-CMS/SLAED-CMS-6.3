# Оптимизация производительности SLAED CMS

SLAED CMS разработана с акцентом на высокую производительность и эффективное использование ресурсов сервера. Однако для достижения максимальной скорости работы сайта необходимо правильно настроить систему и серверное окружение.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Анализ производительности

### Встроенные инструменты

SLAED CMS предоставляет встроенные инструменты для анализа производительности:

1. **Информация о системе** - [systeminfo.php](file:///c:/OSPanel/home/slaed.loc/public/systeminfo.php)
   - Системные характеристики сервера
   - Версии PHP, MySQL, веб-сервера
   - Загрузка ресурсов

2. **Проверка совместимости** - [check_compat.php](file:///c:/OSPanel/home/slaed.loc/public/check_compat.php)
   - Проверка требований к системе
   - Анализ конфигурации PHP

3. **Тест записи файлов** - [test_write.php](file:///c:/OSPanel/home/slaed.loc/public/test_write.php)
   - Проверка прав доступа к файлам
   - Тест скорости записи

### Внешние инструменты

Для более детального анализа можно использовать:

- **Google PageSpeed Insights** - анализ производительности веб-страниц
- **GTmetrix** - комплексный анализ скорости загрузки
- **Pingdom Tools** - тест времени загрузки сайта
- **WebPageTest** - детальный анализ производительности

## Оптимизация PHP

### Настройки php.ini

Для оптимальной работы SLAED CMS рекомендуется следующая конфигурация PHP:

```ini
; Память
memory_limit = 256M

; Время выполнения
max_execution_time = 300
max_input_time = 300

; Загрузка файлов
upload_max_filesize = 32M
post_max_size = 32M
max_file_uploads = 20

; OPcache (обязательно для производительности)
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
opcache.save_comments = 0
opcache.fast_shutdown = 1

; Сессии
session.gc_maxlifetime = 86400
session.cookie_lifetime = 0
session.cookie_secure = 1
session.cookie_httponly = 1
session.cookie_samesite = "Strict"

; Безопасность
expose_php = Off
display_errors = Off
log_errors = On
```

### Расширения PHP

Обязательные расширения для производительности:
- **OPcache** - кэширование байткода PHP
- **mysqli** - оптимизированный доступ к MySQL
- **gd** - обработка изображений
- **zip** - работа с архивами

Рекомендуемые расширения:
- **APCu** - пользовательский кэш в памяти
- **Redis** - распределенный кэш
- **Memcached** - высокопроизводительный кэш

## Оптимизация базы данных

### Настройки MySQL/MariaDB

Рекомендуемые настройки для my.cnf:

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

# Оптимизация
skip-name-resolve
table_open_cache = 2000
table_definition_cache = 2000
```

### Оптимизация таблиц

Регулярная оптимизация таблиц:

```sql
-- Оптимизация всех таблиц SLAED CMS
OPTIMIZE TABLE sl_users, sl_modules, sl_config, sl_blocks, sl_categories;
```

### Индексы

Проверка и оптимизация индексов:

```sql
-- Анализ использования индексов
SHOW INDEX FROM sl_users;
ANALYZE TABLE sl_users;
```

## Кэширование

### Встроенное кэширование SLAED CMS

SLAED CMS предоставляет многоуровневую систему кэширования:

1. **Кэширование страниц**
   - Полное кэширование HTML страниц
   - Настройка времени жизни кэша
   - Исключения для динамического контента

2. **Кэширование CSS и JavaScript**
   - Объединение файлов
   - Минификация кода
   - Сжатие ресурсов

3. **Кэширование блоков**
   - Кэширование отдельных блоков
   - Раздельное время жизни для разных блоков

### Настройка кэширования

В файле [config/config_core.php](file:///c:/OSPanel/home/slaed.loc/public/config/config_core.php):

```php
$conf = array(
    // Кэширование блоков
    'cache_b' => '1',           // Включить кэширование блоков
    'cache_d' => '7',           // Дни кэширования
    'cache_t' => '3600',        // Время жизни кэша (секунды)
    
    // Кэширование CSS/JS
    'cache_css' => '1',         // Кэширование CSS
    'cache_script' => '1',      // Кэширование JavaScript
    
    // Сжатие
    'css_c' => '1',             // Сжатие CSS
    'script_c' => '1',          // Сжатие JavaScript
    'html_compress' => '1',     // Сжатие HTML
);
```

### Внешние системы кэширования

#### Redis

Для интеграции с Redis:

```php
// Подключение к Redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

// Кэширование данных
function cache_get_redis($key) {
    global $redis;
    return $redis->get($key);
}

function cache_set_redis($key, $data, $ttl = 3600) {
    global $redis;
    return $redis->setex($key, $ttl, $data);
}
```

#### Memcached

Для интеграции с Memcached:

```php
// Подключение к Memcached
$memcached = new Memcached();
$memcached->addServer('localhost', 11211);

// Кэширование данных
function cache_get_memcached($key) {
    global $memcached;
    return $memcached->get($key);
}

function cache_set_memcached($key, $data, $ttl = 3600) {
    global $memcached;
    return $memcached->set($key, $data, $ttl);
}
```

## Оптимизация веб-сервера

### Apache

Оптимизация .htaccess:

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

# Сжатие
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# ETags
<IfModule mod_headers.c>
    Header unset ETag
    FileETag None
</IfModule>
```

### Nginx

Оптимизация конфигурации Nginx:

```nginx
server {
    # Кэширование статических файлов
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
        add_header Vary "Accept-Encoding";
    }
    
    # Сжатие
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/javascript
        application/xml+rss
        application/json;
}
```

## Оптимизация изображений

### Форматы изображений

Рекомендуемые форматы:
- **WebP** - современный формат с лучшим сжатием
- **AVIF** - следующее поколение форматов изображений
- **JPEG** - для фотографий
- **PNG** - для изображений с прозрачностью

### Автоматическая оптимизация

SLAED CMS поддерживает автоматическую оптимизацию изображений:

```php
// Оптимизация изображений при загрузке
function optimize_image($source, $destination, $quality = 85) {
    $info = getimagesize($source);
    
    switch ($info['mime']) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            imagejpeg($image, $destination, $quality);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            imagepng($image, $destination, floor($quality/10)-1);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            imagewebp($image, $destination, $quality);
            break;
    }
    
    imagedestroy($image);
}
```

## CDN (Content Delivery Network)

### Интеграция с CDN

Для использования CDN настройте следующие параметры:

1. **Статические файлы**
   ```php
   // В конфигурации
   $conf['cdn_url'] = 'https://cdn.yoursite.com';
   ```

2. **Подключение ресурсов**
   ```html
   <link rel="stylesheet" href="https://cdn.yoursite.com/templates/default/theme.css">
   <script src="https://cdn.yoursite.com/plugins/jquery/jquery.min.js"></script>
   ```

## Ленивая загрузка

### Lazy Loading для изображений

Реализация ленивой загрузки:

```html
<!-- Изображения с ленивой загрузкой -->
<img data-src="image.jpg" alt="Описание" class="lazy" loading="lazy">
```

```javascript
// JavaScript для ленивой загрузки
document.addEventListener("DOMContentLoaded", function() {
    const lazyImages = [].slice.call(document.querySelectorAll("img.lazy"));
    
    if ("IntersectionObserver" in window) {
        let lazyImageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    let lazyImage = entry.target;
                    lazyImage.src = lazyImage.dataset.src;
                    lazyImage.classList.remove("lazy");
                    lazyImageObserver.unobserve(lazyImage);
                }
            });
        });
        
        lazyImages.forEach(function(lazyImage) {
            lazyImageObserver.observe(lazyImage);
        });
    }
});
```

## Мониторинг производительности

### Логирование производительности

Включение логирования времени выполнения:

```php
// В конфигурации
$conf['log_performance'] = 1;
$conf['performance_threshold'] = 1000; // мс

// Логирование медленных запросов
function log_slow_query($query, $time) {
    global $conf;
    if ($time > $conf['performance_threshold']) {
        error_log("Slow query: {$query} ({$time}ms)", 3, LOGS_DIR.'/performance.log');
    }
}
```

### Алертинг

Настройка уведомлений о проблемах производительности:

```php
// Отправка алертов
function send_performance_alert($issue, $details) {
    $subject = "Performance Alert: {$issue}";
    $message = "Performance issue detected:\n\n{$details}";
    mail(ADMIN_EMAIL, $subject, $message);
}
```

## Масштабирование

### Горизонтальное масштабирование

Для высоконагруженных сайтов:

1. **Балансировка нагрузки**
   ```nginx
   upstream slaed_backend {
       server 192.168.1.10:8080;
       server 192.168.1.11:8080;
       server 192.168.1.12:8080;
   }
   ```

2. **Разделение БД**
   - Master-slave репликация
   - Шардинг таблиц

### Вертикальное масштабирование

Увеличение ресурсов сервера:
- Более мощный процессор
- Больше оперативной памяти
- SSD накопители
- Выделенный сервер

## Рекомендации по оптимизации

### Контрольный список

- [ ] Включить и настроить OPcache
- [ ] Оптимизировать настройки PHP
- [ ] Настроить кэширование MySQL
- [ ] Включить кэширование SLAED CMS
- [ ] Оптимизировать изображения
- [ ] Использовать CDN для статических файлов
- [ ] Включить сжатие Gzip
- [ ] Настроить кэширование браузера
- [ ] Реализовать ленивую загрузку
- [ ] Провести нагрузочное тестирование

### Регулярное обслуживание

1. **Еженедельно**
   - Оптимизация таблиц БД
   - Очистка логов
   - Проверка кэша

2. **Ежемесячно**
   - Анализ производительности
   - Обновление PHP и MySQL
   - Резервное копирование

3. **Ежеквартально**
   - Аудит кода
   - Проверка безопасности
   - Обновление расширений

Следуя этим рекомендациям, вы сможете достичь максимальной производительности вашего сайта на SLAED CMS и обеспечить отличный пользовательский опыт.