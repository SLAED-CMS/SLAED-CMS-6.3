# Безопасность SLAED CMS

SLAED CMS разработана с акцентом на безопасность и включает многоуровневую систему защиты от основных видов атак и угроз.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Архитектура безопасности

### Принципы безопасности

1. **Defense in Depth** - многоуровневая защита
2. **Principle of Least Privilege** - минимальные необходимые права
3. **Input Validation** - проверка всех входных данных
4. **Output Encoding** - кодирование всех выходных данных
5. **Secure by Default** - безопасные настройки по умолчанию

### Основные компоненты защиты

```
┌─────────────────────────────────────────┐
│           SECURITY LAYERS               │
├─────────────────────────────────────────┤
│ 1. Web Server Security (Apache/Nginx)  │ 
│ 2. Application Firewall                 │
│ 3. Input Validation & Sanitization     │
│ 4. Authentication & Authorization       │
│ 5. Session Management                   │
│ 6. CSRF Protection                      │
│ 7. XSS Prevention                       │
│ 8. SQL Injection Prevention            │
│ 9. File Upload Security                 │
│ 10. Logging & Monitoring                │
└─────────────────────────────────────────┘
```

## Защита от основных атак

### 1. SQL Injection Protection

**Использование подготовленных запросов:**

```php
// БЕЗОПАСНО - Подготовленные запросы
$stmt = $db->prepare("SELECT * FROM {$prefix}_users WHERE user_id = ? AND active = ?");
$stmt->bind_param("ii", $user_id, $active);
$stmt->execute();
$result = $stmt->get_result();

// НЕБЕЗОПАСНО - Прямая подстановка (НЕ ИСПОЛЬЗУЙТЕ!)
$query = "SELECT * FROM {$prefix}_users WHERE user_id = " . $user_id;
```

**Автоматическое экранирование в функциях ядра:**

```php
// Функция getVar автоматически фильтрует входные данные
$id = getVar('get', 'id', 'num');        // Только числа
$email = getVar('post', 'email', 'email'); // Валидация email
$text = getVar('post', 'text', 'text');   // Безопасный текст
```

### 2. XSS (Cross-Site Scripting) Protection

**Фильтрация входных данных:**

```php
// Автоматическая фильтрация в getVar
function getVar($method, $name, $type, $default = '') {
    switch($type) {
        case 'text':
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        case 'html':
            return filter_html($value);
        case 'var':
            return preg_replace('#[^a-zA-Z0-9_-]#', '', $value);
    }
}

// Дополнительная защита при выводе
function xss_clean($str) {
    $str = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    $str = preg_replace('#javascript:#i', '', $str);
    $str = preg_replace('#vbscript:#i', '', $str);
    $str = preg_replace('#onload#i', '', $str);
    return $str;
}
```

**Content Security Policy (CSP):**

```php
// В файлах шаблонов
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://www.google.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
```

### 3. CSRF (Cross-Site Request Forgery) Protection

**Генерация и проверка токенов:**

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
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Использование в формах
echo '<input type="hidden" name="csrf_token" value="'.generate_csrf_token().'">';

// Проверка при обработке формы
if (!verify_csrf_token(getVar('post', 'csrf_token', 'text'))) {
    die('CSRF token mismatch');
}
```

### 4. Защита от File Upload атак

**Строгая проверка загружаемых файлов:**

```php
function secure_file_upload($file, $allowed_types, $max_size, $upload_dir) {
    // 1. Проверка типа файла по расширению
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        return false;
    }
    
    // 2. Проверка MIME типа
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowed_mimes = array(
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf'
    );
    
    if (!isset($allowed_mimes[$ext]) || $allowed_mimes[$ext] !== $mime) {
        return false;
    }
    
    // 3. Проверка размера
    if ($file['size'] > $max_size) {
        return false;
    }
    
    // 4. Проверка на исполняемые файлы
    $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'pl', 'py', 'jsp', 'asp', 'sh', 'cgi');
    if (in_array($ext, $dangerous_extensions)) {
        return false;
    }
    
    // 5. Генерация безопасного имени файла
    $safe_name = uniqid().'_'.preg_replace('#[^a-zA-Z0-9.-]#', '_', basename($file['name']));
    
    // 6. Перемещение файла
    if (move_uploaded_file($file['tmp_name'], $upload_dir.'/'.$safe_name)) {
        return $safe_name;
    }
    
    return false;
}
```

**Дополнительные меры защиты:**

```apache
# .htaccess в папке uploads
<Files "*.php">
    Deny from all
</Files>
<Files "*.phtml">
    Deny from all
</Files>
<Files "*.php3">
    Deny from all
</Files>
```

## Аутентификация и авторизация

### Система ролей и прав доступа

```php
// Иерархия пользователей
define('USER_GUEST', 0);        // Гость
define('USER_MEMBER', 1);       // Пользователь
define('USER_MODERATOR', 2);    // Модератор
define('USER_ADMIN', 3);        // Администратор
define('USER_SUPERADMIN', 4);   // Супер-администратор

// Проверка прав доступа
function check_access_level($required_level, $user_level = null) {
    global $user;
    
    if ($user_level === null) {
        $user_level = isset($user[3]) ? intval($user[3]) : USER_GUEST;
    }
    
    return $user_level >= $required_level;
}

// Проверка прав на модуль
function is_module_admin($module) {
    global $db, $prefix, $user;
    
    if (!is_user()) return false;
    if (is_admin()) return true;
    
    $user_id = intval($user[0]);
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_module_admins WHERE user_id = ? AND module = ?");
    $stmt->bind_param("is", $user_id, $module);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_row()[0];
    
    return $count > 0;
}
```

### Безопасная аутентификация

```php
// Хеширование паролей
function hash_password($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,  // 64MB
        'time_cost' => 4,        // 4 итерации
        'threads' => 3,          // 3 потока
    ]);
}

// Проверка пароля
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Проверка силы пароля
function check_password_strength($password) {
    $strength = 0;
    
    if (strlen($password) >= 8) $strength++;
    if (preg_match('/[a-z]/', $password)) $strength++;
    if (preg_match('/[A-Z]/', $password)) $strength++;
    if (preg_match('/[0-9]/', $password)) $strength++;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
    
    return $strength; // 0-5
}
```

### Управление сессиями

```php
// Безопасные настройки сессии
function init_secure_session() {
    // Настройки cookie сессии
    $cookie_params = array(
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    );
    
    session_set_cookie_params($cookie_params);
    
    // Регенерация ID сессии
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
    }
    
    // Проверка IP адреса и User Agent
    if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== getIp()) {
        session_destroy();
        return false;
    }
    
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== getUserAgent()) {
        session_destroy();
        return false;
    }
    
    $_SESSION['user_ip'] = getIp();
    $_SESSION['user_agent'] = getUserAgent();
    
    return true;
}

// Безопасное завершение сессии
function secure_logout() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}
```

## Защита от автоматизированных атак

### Rate Limiting / Flood Protection

```php
// Защита от флуда
function check_flood_protection($action, $ip = null, $user_id = null) {
    global $db, $prefix, $confs;
    
    if (!$confs['flood_enable']) return true;
    
    $ip = $ip ?: getIp();
    $user_id = $user_id ?: (is_user() ? intval($user[0]) : 0);
    $time_limit = time() - intval($confs['flood_time']);
    
    // Подсчет попыток
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_flood_log WHERE action = ? AND (ip = ? OR user_id = ?) AND timestamp > ?");
    $stmt->bind_param("ssii", $action, $ip, $user_id, $time_limit);
    $stmt->execute();
    $attempts = $stmt->get_result()->fetch_row()[0];
    
    if ($attempts >= intval($confs['flood_attempts'])) {
        // Логирование попытки
        log_security_event('FLOOD_DETECTED', array(
            'action' => $action,
            'ip' => $ip,
            'user_id' => $user_id,
            'attempts' => $attempts
        ));
        
        return false;
    }
    
    // Запись попытки
    $stmt = $db->prepare("INSERT INTO {$prefix}_flood_log (action, ip, user_id, timestamp) VALUES (?, ?, ?, ?)");
    $current_time = time();
    $stmt->bind_param("ssii", $action, $ip, $user_id, $current_time);
    $stmt->execute();
    
    return true;
}
```

### CAPTCHA Integration

```php
// Google reCAPTCHA v3
function verify_recaptcha_v3($response, $action = 'homepage') {
    global $confs;
    
    if (!$confs['captcha_enable']) return true;
    
    $secret_key = $confs['recaptcha_secret_key'];
    $verify_url = 'https://www.google.com/recaptcha/api/siteverify';
    
    $data = array(
        'secret' => $secret_key,
        'response' => $response,
        'remoteip' => getIp()
    );
    
    $options = array(
        'http' => array(
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data)
        )
    );
    
    $context = stream_context_create($options);
    $result = file_get_contents($verify_url, false, $context);
    $response_data = json_decode($result, true);
    
    if ($response_data['success'] && 
        $response_data['action'] === $action && 
        $response_data['score'] >= floatval($confs['recaptcha_score'])) {
        return true;
    }
    
    log_security_event('CAPTCHA_FAILED', array(
        'ip' => getIp(),
        'score' => $response_data['score'] ?? 0,
        'action' => $action
    ));
    
    return false;
}
```

## Мониторинг и логирование безопасности

### Система логирования

```php
// Логирование событий безопасности
function log_security_event($event_type, $data = array()) {
    global $user;
    
    $log_entry = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'event_type' => $event_type,
        'ip' => getIp(),
        'user_agent' => getUserAgent(),
        'user_id' => is_user() ? intval($user[0]) : 0,
        'data' => $data
    );
    
    $log_line = json_encode($log_entry) . "\n";
    file_put_contents(LOGS_DIR.'/security.log', $log_line, FILE_APPEND | LOCK_EX);
    
    // Критические события
    if (in_array($event_type, array('ADMIN_LOGIN_FAILED', 'SQL_INJECTION_ATTEMPT', 'XSS_ATTEMPT'))) {
        send_security_alert($event_type, $log_entry);
    }
}

// Отправка уведомлений о критических событиях
function send_security_alert($event_type, $log_entry) {
    global $conf;
    
    $subject = 'Security Alert: ' . $event_type;
    $message = "Security event detected:\n\n";
    $message .= "Type: " . $event_type . "\n";
    $message .= "Time: " . $log_entry['timestamp'] . "\n";
    $message .= "IP: " . $log_entry['ip'] . "\n";
    $message .= "User Agent: " . $log_entry['user_agent'] . "\n";
    $message .= "Details: " . json_encode($log_entry['data'], JSON_PRETTY_PRINT);
    
    mail($conf['admin_email'], $subject, $message);
}
```

### Мониторинг подозрительной активности

```php
// Анализ подозрительных паттернов
function analyze_suspicious_activity($ip) {
    global $db, $prefix;
    
    $suspicious_score = 0;
    $last_hour = time() - 3600;
    
    // Частые неудачные попытки входа
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_login_attempts WHERE ip = ? AND success = 0 AND timestamp > ?");
    $stmt->bind_param("si", $ip, $last_hour);
    $stmt->execute();
    $failed_logins = $stmt->get_result()->fetch_row()[0];
    
    if ($failed_logins > 10) $suspicious_score += 3;
    elseif ($failed_logins > 5) $suspicious_score += 2;
    elseif ($failed_logins > 3) $suspicious_score += 1;
    
    // Частые запросы к несуществующим страницам
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_404_log WHERE ip = ? AND timestamp > ?");
    $stmt->bind_param("si", $ip, $last_hour);
    $stmt->execute();
    $not_found_requests = $stmt->get_result()->fetch_row()[0];
    
    if ($not_found_requests > 20) $suspicious_score += 2;
    elseif ($not_found_requests > 10) $suspicious_score += 1;
    
    // Попытки SQL инъекций
    $stmt = $db->prepare("SELECT COUNT(*) FROM {$prefix}_security_log WHERE ip = ? AND event_type = 'SQL_INJECTION_ATTEMPT' AND timestamp > ?");
    $stmt->bind_param("si", $ip, $last_hour);
    $stmt->execute();
    $sql_attempts = $stmt->get_result()->fetch_row()[0];
    
    if ($sql_attempts > 0) $suspicious_score += 5;
    
    // Автоматическая блокировка при высоком подозрительном счете
    if ($suspicious_score >= 5) {
        block_ip($ip, 'Suspicious activity detected');
        log_security_event('IP_AUTO_BLOCKED', array('ip' => $ip, 'score' => $suspicious_score));
    }
    
    return $suspicious_score;
}
```

## Безопасность конфигурации

### Защита конфигурационных файлов

```apache
# .htaccess для защиты папки config
<Files "*.php">
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
</Files>

<Files "*.txt">
    Order Deny,Allow
    Deny from all
</Files>

<Files "*.log">
    Order Deny,Allow
    Deny from all
</Files>
```

### Безопасные права доступа к файлам

```bash
# Установка правильных прав доступа
find /path/to/slaed/ -type f -exec chmod 644 {} \;
find /path/to/slaed/ -type d -exec chmod 755 {} \;

# Особые права для критических папок
chmod 700 /path/to/slaed/config/
chmod 777 /path/to/slaed/uploads/
chmod 777 /path/to/slaed/storage/

# Запрет выполнения в папке uploads
chmod -x /path/to/slaed/uploads/*
```

## Рекомендации по безопасности

### Регулярные проверки безопасности

1. **Аудит логов** - ежедневный анализ логов безопасности
2. **Обновления** - своевременное обновление PHP, MySQL, веб-сервера
3. **Мониторинг** - контроль подозрительной активности
4. **Бэкапы** - регулярное резервное копирование
5. **Тестирование** - периодическое тестирование на проникновение

### Контрольный список безопасности

- [ ] Обновлен PHP до последней версии
- [ ] Установлены последние обновления безопасности
- [ ] Настроен файрвол веб-сервера
- [ ] Включена HTTPS/SSL
- [ ] Настроены безопасные заголовки HTTP
- [ ] Отключены неиспользуемые модули и функции PHP
- [ ] Настроено логирование безопасности
- [ ] Установлены мониторинговые решения
- [ ] Проведено тестирование на уязвимости
- [ ] Настроены автоматические бэкапы

Следуя этим рекомендациям и используя встроенные механизмы защиты SLAED CMS, вы обеспечите высокий уровень безопасности вашего веб-сайта.