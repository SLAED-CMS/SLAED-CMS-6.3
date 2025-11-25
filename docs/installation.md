# Установка и настройка SLAED CMS

## Системные требования

### Минимальные требования

---

*© 2005-2026 Eduard Laas. All rights reserved.*
- **PHP:** 8.0 или выше
- **MySQL/MariaDB:** 5.7+ / 10.3+
- **Веб-сервер:** Apache 2.4+ или Nginx 1.14+
- **Память:** 128MB RAM для PHP
- **Дисковое пространство:** 50MB

### Рекомендуемые требования
- **PHP:** 8.1+ с OPcache
- **MySQL/MariaDB:** 8.0+ / 10.6+
- **Память:** 256MB+ RAM для PHP
- **Дисковое пространство:** 200MB+

### Необходимые PHP расширения
- `mysqli` - для работы с базой данных
- `gd` - для обработки изображений
- `zip` - для работы с архивами
- `mbstring` - для работы с многобайтными строками
- `json` - для работы с JSON
- `curl` - для внешних запросов

## Процесс установки

### Шаг 1: Подготовка файлов

1. **Скачайте архив** с SLAED CMS
2. **Распакуйте файлы** в корневую папку веб-сервера
3. **Установите права доступа:**

```bash
# Для Linux/Unix систем
chmod 755 /path/to/slaed/
chmod -R 777 /path/to/slaed/config/
chmod -R 777 /path/to/slaed/uploads/
chmod -R 777 /path/to/slaed/storage/
```

### Шаг 2: Создание базы данных

1. **Создайте базу данных MySQL/MariaDB:**

```sql
CREATE DATABASE slaed_cms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'slaed_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON slaed_cms.* TO 'slaed_user'@'localhost';
FLUSH PRIVILEGES;
```

### Шаг 3: Конфигурация

1. **Настройте файл конфигурации** `config/000config.php`:

```php
<?php
if (!defined('FUNC_FILE')) die('Illegal file access');

$confdb = array();
$confdb['host'] = "localhost";          // Хост базы данных
$confdb['uname'] = "slaed_user";        // Имя пользователя БД
$confdb['pass'] = "your_password";       // Пароль БД
$confdb['name'] = "slaed_cms";          // Имя базы данных
$confdb['type'] = "mysqli";             // Тип БД
$confdb['engine'] = "InnoDB";           // Движок БД
$confdb['charset'] = "utf8mb4";         // Кодировка
$confdb['collate'] = "utf8mb4_unicode_ci"; // Сравнение
$confdb['prefix'] = "slaed";            // Префикс таблиц
$confdb['sync'] = "0";                  // Синхронизация времени
$confdb['mode'] = "0";                  // Строгий режим MySQL

$prefix = "slaed";
$admin_file = "admin";
?>
```

### Шаг 4: Запуск установщика

1. **Откройте в браузере:** `http://yoursite.com/setup.php`
2. **Следуйте инструкциям установщика:**
   - Проверка системных требований
   - Настройка базы данных
   - Создание администратора
   - Базовая конфигурация

### Шаг 5: Первоначальная настройка

1. **Войдите в административную панель:** `http://yoursite.com/admin.php`
2. **Настройте основные параметры:**
   - Название сайта
   - Описание и ключевые слова
   - Настройки безопасности
   - Активация модулей

## Детальная конфигурация

### Настройки безопасности

**Файл:** `config/config_security.php`

```php
$confs = array(
    'protection' => '1',        // Общая защита
    'flood_time' => '30',       // Защита от флуда (сек)
    'login_attempts' => '5',    // Попытки входа
    'ban_time' => '3600',      // Время бана (сек)
    'captcha_enable' => '1',    // Включить капчу
    'csrf_protection' => '1',   // CSRF защита
    'xss_filter' => '1',       // XSS фильтр
);
```

### Настройки кэширования

**Файл:** `config/config_core.php`

```php
$conf = array(
    'cache_b' => '1',          // Кэширование страниц
    'cache_t' => '3600',       // Время жизни кэша (сек)
    'cache_css' => '1',        // Кэширование CSS
    'cache_script' => '1',     // Кэширование JavaScript
    'css_c' => '1',           // Сжатие CSS
    'script_c' => '1',        // Сжатие JavaScript
    'html_compress' => '1',    // Сжатие HTML
);
```

### Настройки загрузки файлов

**Файл:** `config/config_uploads.php`

```php
$confup = array(
    'news' => '2048|jpg,png,gif|1|0|1',      // Новости: размер|типы|превью|папки|активно
    'pages' => '5120|jpg,png,gif,pdf|1|0|1', // Страницы
    'avatars' => '512|jpg,png,gif|1|0|1',    // Аватары
    'files' => '10240|zip,rar,pdf,doc|0|1|1', // Файлы
);
```

## Настройка веб-сервера

### Apache

**Создайте файл `.htaccess`:**

```apache
# Безопасность
Options -Indexes
Options -ExecCGI

# Защита конфигурационных файлов
<Files "*.php">
    <RequireAll>
        Require all denied
        Require local
    </RequireAll>
</Files>

# Перенаправления для ЧПУ (если включены)
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?go=$1 [QSA,L]

# Кэширование статических файлов
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
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
```

### Nginx

**Пример конфигурации:**

```nginx
server {
    listen 80;
    server_name yoursite.com;
    root /path/to/slaed;
    index index.php index.html;

    # Безопасность
    location ~ /\. {
        deny all;
    }
    
    location ~* \.(conf|txt|log)$ {
        deny all;
    }

    # PHP обработка
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Статические файлы
    location ~* \.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1M;
        add_header Cache-Control "public, immutable";
    }

    # ЧПУ
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

## Проверка установки

### Тестирование функциональности

1. **Откройте главную страницу** сайта
2. **Проверьте доступ к админке** `admin.php`
3. **Протестируйте основные модули:**
   - Создание новости
   - Добавление пользователя
   - Загрузка файла

### Проверка системной информации

Используйте встроенные инструменты:
- `systeminfo.php` - информация о системе
- `check_compat.php` - проверка совместимости
- `test_write.php` - проверка прав записи

## Безопасность после установки

### Обязательные шаги:

1. **Удалите установщик:**
```bash
rm setup.php
```

2. **Измените права доступа:**
```bash
chmod 644 config/*.php
```

3. **Настройте бэкапы базы данных**

4. **Включите SSL/HTTPS**

5. **Настройте мониторинг логов**

### Рекомендуемые меры:

- Регулярно обновляйте PHP и MySQL
- Используйте сильные пароли
- Настройте файрвол
- Мониторьте подозрительную активность

## Оптимизация производительности

### PHP настройки

**В `php.ini`:**

```ini
; Память
memory_limit = 256M

; Загрузка файлов
upload_max_filesize = 32M
post_max_size = 32M

; Время выполнения
max_execution_time = 300
max_input_time = 300

; OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 4000
opcache.revalidate_freq = 60
```

### MySQL настройки

**В `my.cnf`:**

```ini
[mysqld]
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
query_cache_size = 32M
query_cache_type = 1
```

## Устранение проблем

### Типичные ошибки:

1. **"Illegal file access"**
   - Проверьте права доступа к файлам
   - Убедитесь в корректности путей

2. **Ошибки базы данных**
   - Проверьте настройки подключения
   - Убедитесь в правильности кодировки

3. **Проблемы с загрузкой файлов**
   - Проверьте права на папку uploads/
   - Увеличьте лимиты PHP

### Логи для диагностики:

- `config/logs/error.log` - ошибки системы
- `config/logs/security.log` - события безопасности
- Логи веб-сервера и PHP

Следуя этой инструкции, вы успешно установите и настроите SLAED CMS для эффективной и безопасной работы.