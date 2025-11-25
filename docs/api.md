# API для разработчиков SLAED CMS

SLAED CMS предоставляет обширный API для разработки модулей, плагинов и интеграции с внешними системами.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Основные компоненты API

### 1. Ядро системы (Core API)

#### Константы и определения

```php
// Основные константы
define('MODULE_FILE', true);    // Файл модуля
define('ADMIN_FILE', true);     // Административный файл
define('BLOCK_FILE', true);     // Файл блока
define('FUNC_FILE', true);      // Функциональный файл

// Директории
define('BASE_DIR', str_replace('\\', '/', dirname(__DIR__)));
define('CONFIG_DIR', BASE_DIR.'/config');
define('UPLOADS_DIR', BASE_DIR.'/uploads');
define('CACHE_DIR', BASE_DIR.'/storage/cache');
define('LOGS_DIR', BASE_DIR.'/storage/logs');
```

#### Основные функции ядра

```php
// Получение переменных с фильтрацией
getVar($method, $name, $type, $default = '')

// Параметры:
// $method - 'get', 'post', 'req', 'cookie', 'session'
// $type - 'var', 'num', 'text', 'html', 'email', 'url'
// $default - значение по умолчанию

// Примеры использования:
$id = getVar('get', 'id', 'num');                    // Число из GET
$title = getVar('post', 'title', 'text');           // Текст из POST
$content = getVar('post', 'content', 'html');       // HTML из POST
$email = getVar('post', 'email', 'email');          // Email из POST
```

### 2. База данных (Database API)

#### Подключение к БД

```php
global $db, $prefix;

// Объект базы данных автоматически доступен во всех модулях
// $prefix - префикс таблиц из конфигурации
```

#### Основные методы работы с БД

```php
// Выполнение запроса
$result = $db->sql_query("SELECT * FROM {$prefix}_table WHERE id = ?", array($id));

// Получение строки результата
$row = $db->sql_fetchrow($result);
list($id, $title, $content) = $db->sql_fetchrow($result);

// Получение количества строк
$count = $db->sql_numrows($result);

// Получение ID последней вставленной записи
$insert_id = $db->sql_insertid();

// Экранирование строк (устаревший метод, используйте подготовленные запросы)
$safe_string = $db->sql_escape_string($string);
```

#### Подготовленные запросы (рекомендуется)

```php
// INSERT
$stmt = $db->prepare("INSERT INTO {$prefix}_news (title, content, author_id) VALUES (?, ?, ?)");
$stmt->bind_param("ssi", $title, $content, $author_id);
$stmt->execute();
$new_id = $stmt->insert_id;

// SELECT
$stmt = $db->prepare("SELECT id, title, content FROM {$prefix}_news WHERE category = ? LIMIT ?");
$stmt->bind_param("ii", $category_id, $limit);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    // Обработка строк
}

// UPDATE
$stmt = $db->prepare("UPDATE {$prefix}_news SET title = ?, content = ? WHERE id = ?");
$stmt->bind_param("ssi", $title, $content, $id);
$stmt->execute();

// DELETE
$stmt = $db->prepare("DELETE FROM {$prefix}_news WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
```

### 3. Пользователи и права доступа (User API)

#### Проверка состояния пользователя

```php
// Проверка авторизации
if (is_user()) {
    // Пользователь авторизован
}

// Проверка администратора
if (is_admin()) {
    // Пользователь - администратор
}

// Проверка супер-администратора
if (is_admin_god()) {
    // Пользователь - супер-администратор
}

// Проверка модератора модуля
if (is_moder($module_name)) {
    // Пользователь - модератор модуля
}

// Проверка бота
if (is_bot()) {
    // Запрос от поискового бота
}
```

#### Получение информации о пользователе

```php
global $user;

// Массив данных текущего пользователя
// $user[0] - ID пользователя
// $user[1] - Имя пользователя
// $user[2] - Email
// $user[3] - Группа пользователя
// $user[4] - Аватар
// $user[5] - Подпись

// Получение ID текущего пользователя
$user_id = intval($user[0]);

// Получение информации о пользователе по ID
function get_user_info($user_id) {
    global $db, $prefix;
    $stmt = $db->prepare("SELECT user_id, user_name, user_email, user_group FROM {$prefix}_users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
```

#### Управление сессиями

```php
// Установка cookie
setCookies($name, $value, $time = 0);

// Получение cookie
$value = getCookies($name);

// Получение IP адреса
$ip = getIp();

// Получение User Agent
$user_agent = getUserAgent();
```

### 4. Шаблоны (Template API)

#### Основные шаблонные функции

```php
// Подключение шаблона темы
function setThemeInclude() {
    global $theme;
    $theme = get_theme();
    include_once('templates/'.$theme.'/index.php');
    include_once('core/template.php');
}

// Основные шаблоны
function setTemplateBasic($type, $values = array()) {
    // $type - тип шаблона: 'title', 'content', 'pagination' и др.
    // $values - массив замен для плейсхолдеров
}

// Шаблон предупреждения
function setTemplateWarning($type, $values = array()) {
    // $type - 'info', 'warn', 'error', 'success'
}

// Шаблон блока
function setTemplateBlock($title, $content, $style = '') {
    return '<div class="sl_block '.$style.'"><h3>'.$title.'</h3><div>'.$content.'</div></div>';
}
```

#### Использование шаблонов

```php
// Заголовок страницы
echo setTemplateBasic('title', array('{%title%}' => 'Заголовок страницы'));

// Контент с заменами
$content = setTemplateBasic('content', array(
    '{%title%}' => $title,
    '{%content%}' => $content,
    '{%author%}' => $author,
    '{%date%}' => $date
));

// Сообщение об ошибке
echo setTemplateWarning('error', array(
    'text' => 'Произошла ошибка',
    'url' => 'index.php',
    'time' => '5'
));
```

### 5. Файлы и загрузки (File API)

#### Загрузка файлов

```php
// Функция загрузки файла
function upload($type, $dir, $max_size, $allowed_types, $module, $thumb = 0, $folder = 0, $user_id = 0) {
    // $type - тип загрузки (1-изображение, 2-файл)
    // $dir - директория для сохранения
    // $max_size - максимальный размер в KB
    // $allowed_types - разрешенные типы файлов (через |)
    // $module - имя модуля
    // $thumb - создавать превью (0/1)
    // $folder - использовать папки пользователей (0/1)
    // $user_id - ID пользователя
}

// Проверка размера файла
function files_size($size) {
    if ($size >= 1073741824) {
        return round($size / 1073741824, 2) . ' GB';
    } elseif ($size >= 1048576) {
        return round($size / 1048576, 2) . ' MB';
    } elseif ($size >= 1024) {
        return round($size / 1024, 2) . ' KB';
    } else {
        return $size . ' B';
    }
}

// Поиск изображения
function img_find($img, $default = '') {
    $paths = array(
        'uploads/'.$img,
        'templates/'.get_theme().'/images/'.$img,
        'templates/default/images/'.$img
    );
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return $default;
}
```

#### Работа с архивами

```php
// Создание архива
function addCompress($dir, $src, $name, $mode = 'auto', $del = false) {
    // $dir - директория для сохранения
    // $src - источник (файл или папка)
    // $name - имя архива
    // $mode - тип сжатия ('zip', 'gz', 'bz2', 'auto')
    // $del - удалить исходник после сжатия
}

// Проверка доступных методов сжатия
function checkCompress() {
    return array(
        'zip' => class_exists('ZipArchive'),
        'gz'  => function_exists('gzopen'), 
        'bz2' => function_exists('bzopen')
    );
}
```

### 6. Безопасность (Security API)

#### Проверка доступа

```php
// Проверка прав доступа
function is_acess($level) {
    // $level - уровень доступа (строка вида "1,2,3")
    // Возвращает true, если у пользователя есть доступ
}

// Анализ строки на безопасность
function analyze($string) {
    // Очистка строки от опасных символов
    return preg_replace('#[^a-zA-Z0-9_-]#', '', $string);
}

// Фильтрация HTML
function filter_html($html, $allowed_tags = '') {
    return strip_tags($html, $allowed_tags);
}

// Защита от XSS
function xss_clean($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
```

#### CSRF защита

```php
// Генерация CSRF токена
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Проверка CSRF токена
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Вставка скрытого поля с токеном
function csrf_token_field() {
    return '<input type="hidden" name="csrf_token" value="'.generate_csrf_token().'">';
}
```

#### Капча

```php
// Получение капчи
function getCaptcha($id) {
    global $conf;
    if ($conf['gfx_chk'] >= '1' && ($id == 2 || ($id == 1 && !is_user()))) {
        // reCAPTCHA v3
        return '<script src="https://www.google.com/recaptcha/api.js?render='.$conf['capkey'].'"></script>';
    }
    return '';
}

// Проверка капчи
function checkCaptcha($id) {
    global $conf;
    if ($conf['gfx_chk'] >= '1') {
        $response = getVar('post', 'recaptcha', 'text');
        // Проверка через Google API
        return verify_recaptcha($response);
    }
    return true;
}
```

### 7. Кэширование (Cache API)

#### Управление кэшем

```php
// Установка заголовков кэширования
function setCache($id = '') {
    if ($id === "1") {
        global $conf;
        $cached = (int) ($conf['cache_d'] ?? 7);
        $max = $cached * 86400;
        header('Cache-Control: public, max-age='.$max);
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + $max).' GMT');
    } else {
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() - 3600).' GMT');
    }
}

// Работа с файловым кэшем
function cache_get($key) {
    $file = CACHE_DIR.'/'.$key.'.cache';
    if (file_exists($file) && (time() - filemtime($file)) < 3600) {
        return unserialize(file_get_contents($file));
    }
    return false;
}

function cache_set($key, $data, $ttl = 3600) {
    $file = CACHE_DIR.'/'.$key.'.cache';
    file_put_contents($file, serialize($data), LOCK_EX);
    touch($file, time() + $ttl);
}

function cache_delete($key) {
    $file = CACHE_DIR.'/'.$key.'.cache';
    if (file_exists($file)) {
        unlink($file);
    }
}
```

### 8. Утилиты (Utility API)

#### Работа с датами

```php
// Форматирование времени
function format_time($timestamp, $format = 'd.m.Y H:i') {
    return date($format, $timestamp);
}

// Проверка валидности даты
function isDate($str) {
    return is_numeric(strtotime($str));
}

// Относительное время
function time_ago($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) return $diff . ' сек. назад';
    if ($diff < 3600) return floor($diff/60) . ' мин. назад';
    if ($diff < 86400) return floor($diff/3600) . ' час. назад';
    return floor($diff/86400) . ' дн. назад';
}
```

#### Работа со строками

```php
// Обрезка строки
function cutstr($str, $length, $suffix = '...') {
    if (mb_strlen($str) > $length) {
        return mb_substr($str, 0, $length) . $suffix;
    }
    return $str;
}

// Транслитерация
function getTranslit($str, $lower = true) {
    $translit = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'yo', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        // ... полная таблица транслитерации
    );
    
    $str = strtr($str, $translit);
    if ($lower) $str = mb_strtolower($str);
    return preg_replace('#[^a-zA-Z0-9]#', '', $str);
}

// Генерация случайной строки
function generate_random_string($length = 10) {
    return substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/strlen($x)) )),1,$length);
}
```

#### Пагинация

```php
// Генерация номеров страниц
function setPageNumbers($current, $module, $total_items, $total_pages, $per_page, $param = '', $max_links = 8, $anchor = '') {
    if ($total_pages <= 1) return '';
    
    $pagination = '';
    
    // Ссылка "Назад"
    if ($current > 1) {
        $prev = $current - 1;
        $pagination .= '<a href="index.php?name='.$module.'&'.$param.'num='.$prev.$anchor.'" class="sl_num">'._BACK.'</a>';
    }
    
    // Номера страниц
    for ($i = 1; $i <= $total_pages; $i++) {
        if ($i == $current) {
            $pagination .= '<span class="sl_num_current">'.$i.'</span>';
        } elseif ($i <= $max_links || $i > $total_pages - $max_links || abs($i - $current) <= 2) {
            $pagination .= '<a href="index.php?name='.$module.'&'.$param.'num='.$i.$anchor.'" class="sl_num">'.$i.'</a>';
        } elseif ($pagination[strlen($pagination)-1] != '…') {
            $pagination .= '<span class="sl_num_dots">…</span>';
        }
    }
    
    // Ссылка "Вперед"
    if ($current < $total_pages) {
        $next = $current + 1;
        $pagination .= '<a href="index.php?name='.$module.'&'.$param.'num='.$next.$anchor.'" class="sl_num">'._NEXT.'</a>';
    }
    
    return $pagination;
}
```

### 9. SEO и URL (SEO API)

#### ЧПУ (Человекопонятные URL)

```php
// Генерация SEO URL
function getHref($params) {
    global $conf;
    
    if ($conf['rewrite']) {
        include('config/config_rewrite.php');
        // Применение правил перезаписи URL
        return apply_rewrite_rules($params);
    } else {
        return 'index.php?' . http_build_query($params);
    }
}

// Генерация мета-тегов
function generate_meta_tags($title, $description, $keywords, $image = '') {
    $meta = '<title>'.htmlspecialchars($title).'</title>'."\n";
    $meta .= '<meta name="description" content="'.htmlspecialchars($description).'">'."\n";
    $meta .= '<meta name="keywords" content="'.htmlspecialchars($keywords).'">'."\n";
    
    if ($image) {
        $meta .= '<meta property="og:image" content="'.$image.'">'."\n";
    }
    
    return $meta;
}
```

### 10. События и хуки (Events API)

#### Система событий

```php
// Глобальный массив для хранения хуков
global $hooks;

// Регистрация хука
function add_hook($event, $function, $priority = 10) {
    global $hooks;
    $hooks[$event][$priority][] = $function;
}

// Выполнение хуков
function trigger_event($event, $data = array()) {
    global $hooks;
    
    if (isset($hooks[$event])) {
        ksort($hooks[$event]);
        foreach ($hooks[$event] as $priority => $functions) {
            foreach ($functions as $function) {
                if (is_callable($function)) {
                    $data = call_user_func($function, $data);
                }
            }
        }
    }
    
    return $data;
}

// Примеры событий
trigger_event('module_init', array('module' => $module_name));
trigger_event('user_login', array('user_id' => $user_id));
trigger_event('content_save', array('id' => $id, 'type' => 'news'));
```

## Примеры использования API

### Создание простого модуля

```php
<?php
if (!defined('MODULE_FILE')) die('Illegal file access');

// Подключение языка и конфигурации
get_lang('my_module');
include('config/config_my_module.php');

// Получение параметров
$op = getVar('req', 'op', 'var');
$id = getVar('req', 'id', 'num');

// Роутинг
switch($op) {
    case 'add':
        if (is_user()) add_item();
        else redirect_to_login();
        break;
    case 'view':
        view_item($id);
        break;
    default:
        show_list();
        break;
}

function show_list() {
    global $db, $prefix, $conf_my_module;
    
    $page = getVar('get', 'page', 'num', 1);
    $per_page = $conf_my_module['per_page'];
    $offset = ($page - 1) * $per_page;
    
    // Получение данных
    $stmt = $db->prepare("SELECT id, title, content FROM {$prefix}_my_items LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = '';
    while ($row = $result->fetch_assoc()) {
        $items .= setTemplateBasic('item', array(
            '{%id%}' => $row['id'],
            '{%title%}' => htmlspecialchars($row['title']),
            '{%content%}' => $row['content']
        ));
    }
    
    // Пагинация
    $total = $db->query("SELECT COUNT(*) FROM {$prefix}_my_items")->fetch_row()[0];
    $pagination = setPageNumbers($page, 'my_module', $total, ceil($total/$per_page), $per_page);
    
    // Вывод
    head();
    echo setTemplateBasic('list', array(
        '{%items%}' => $items,
        '{%pagination%}' => $pagination
    ));
    foot();
}
?>
```

Этот API обеспечивает полный контроль над всеми аспектами SLAED CMS и позволяет создавать мощные и безопасные модули и расширения.