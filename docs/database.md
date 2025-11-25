# База данных SLAED CMS

SLAED CMS использует реляционную базу данных MySQL/MariaDB для хранения всей информации системы. Структура базы данных спроектирована с учетом требований производительности, безопасности и масштабируемости.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Общая архитектура

### Префикс таблиц

Все таблицы в базе данных используют общий префикс, который задается при установке системы. По умолчанию префикс - `sl_`.

### Основные таблицы системы

```
sl_modules              # Модули системы
sl_config               # Конфигурация
sl_users                # Пользователи
sl_groups               # Группы пользователей
sl_blocks               # Блоки интерфейса
sl_categories           # Категории контента
sl_comments             # Комментарии
sl_ratings              # Рейтинги
sl_sessions             # Сессии пользователей
sl_logs                 # Логи системы
```

## Структура таблиц

### sl_modules - Модули системы

Хранит информацию о всех модулях системы.

```sql
CREATE TABLE `sl_modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `view` tinyint(1) NOT NULL DEFAULT '0',
  `blocks` tinyint(1) NOT NULL DEFAULT '1',
  `blocks_c` tinyint(1) NOT NULL DEFAULT '1',
  `mod_group` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `id` - Уникальный идентификатор модуля
- `title` - Название модуля (уникальное)
- `description` - Описание модуля
- `active` - Статус активности (0/1)
- `view` - Видимость модуля
- `blocks` - Использование блоков
- `blocks_c` - Использование центральных блоков
- `mod_group` - Группа модуля

### sl_config - Конфигурация системы

Хранит все настройки системы.

```sql
CREATE TABLE `sl_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `value` text NOT NULL,
  `module` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name_module` (`name`,`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `id` - Уникальный идентификатор настройки
- `name` - Название параметра
- `value` - Значение параметра
- `module` - Модуль, к которому относится параметр

### sl_users - Пользователи системы

Хранит информацию о зарегистрированных пользователях.

```sql
CREATE TABLE `sl_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(25) NOT NULL DEFAULT '',
  `user_email` varchar(100) NOT NULL DEFAULT '',
  `user_password` varchar(255) NOT NULL DEFAULT '',
  `user_group` tinyint(1) NOT NULL DEFAULT '1',
  `user_avatar` varchar(100) NOT NULL DEFAULT 'default/00.gif',
  `user_regdate` int(11) NOT NULL DEFAULT '0',
  `user_lastvisit` int(11) NOT NULL DEFAULT '0',
  `user_active` tinyint(1) NOT NULL DEFAULT '0',
  `user_level` tinyint(1) NOT NULL DEFAULT '1',
  `user_sig` text NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_name` (`user_name`),
  UNIQUE KEY `user_email` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `user_id` - Уникальный идентификатор пользователя
- `user_name` - Имя пользователя (уникальное)
- `user_email` - Email пользователя (уникальный)
- `user_password` - Хэшированный пароль
- `user_group` - Группа пользователя
- `user_avatar` - Аватар пользователя
- `user_regdate` - Дата регистрации (timestamp)
- `user_lastvisit` - Дата последнего визита (timestamp)
- `user_active` - Статус активности (0/1)
- `user_level` - Уровень пользователя
- `user_sig` - Подпись пользователя

### sl_groups - Группы пользователей

Определяет группы пользователей с разными правами доступа.

```sql
CREATE TABLE `sl_groups` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(50) NOT NULL DEFAULT '',
  `group_description` text NOT NULL,
  `group_level` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `group_id` - Уникальный идентификатор группы
- `group_name` - Название группы
- `group_description` - Описание группы
- `group_level` - Уровень доступа группы

### sl_blocks - Блоки интерфейса

Хранит информацию о блоках, отображаемых на сайте.

```sql
CREATE TABLE `sl_blocks` (
  `bid` int(11) NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) NOT NULL DEFAULT '',
  `title` varchar(50) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `url` text NOT NULL,
  `bposition` char(1) NOT NULL DEFAULT 'l',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `blockfile` varchar(50) NOT NULL DEFAULT '',
  `view` tinyint(1) NOT NULL DEFAULT '0',
  `expire` int(11) NOT NULL DEFAULT '0',
  `action` tinyint(1) NOT NULL DEFAULT '0',
  `subscription` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`bid`),
  KEY `bposition` (`bposition`),
  KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `bid` - Уникальный идентификатор блока
- `bkey` - Ключ блока
- `title` - Заголовок блока
- `content` - Содержимое блока
- `url` - URL для отображения блока
- `bposition` - Позиция блока (l/r/c/d/s/o)
- `active` - Статус активности (0/1)
- `blockfile` - Файл блока
- `view` - Видимость блока
- `expire` - Дата истечения (timestamp)
- `action` - Действие блока
- `subscription` - Подписка на блок

### sl_categories - Категории контента

Общая таблица категорий для всех модулей.

```sql
CREATE TABLE `sl_categories` (
  `cat_id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL DEFAULT '',
  `cat_title` varchar(100) NOT NULL DEFAULT '',
  `cat_description` text NOT NULL,
  `cat_image` varchar(100) NOT NULL DEFAULT '',
  `cat_order` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cat_id`),
  KEY `module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Поля:**
- `cat_id` - Уникальный идентификатор категории
- `module` - Модуль, к которому относится категория
- `cat_title` - Название категории
- `cat_description` - Описание категории
- `cat_image` - Изображение категории
- `cat_order` - Порядок отображения

## Индексация и оптимизация

### Основные индексы

Для обеспечения высокой производительности используются следующие индексы:
- Первичные ключи (PRIMARY KEY) для всех таблиц
- Уникальные индексы для полей с уникальными значениями
- Составные индексы для часто используемых комбинаций полей

### Рекомендации по оптимизации

1. **Использование правильных типов данных**
   - Используйте минимально необходимые размеры полей
   - Применяйте ENUM для полей с ограниченным набором значений
   - Используйте TINYINT для булевых значений

2. **Индексы**
   - Создавайте индексы для полей, используемых в WHERE, ORDER BY, JOIN
   - Избегайте избыточных индексов
   - Регулярно анализируйте использование индексов

3. **Нормализация**
   - Следуйте принципам нормализации БД
   - Избегайте дублирования данных
   - Используйте внешние ключи для обеспечения целостности

## Безопасность базы данных

### Защита от SQL-инъекций

SLAED CMS использует подготовленные запросы для защиты от SQL-инъекций:

```php
// Правильно - подготовленный запрос
$stmt = $db->prepare("SELECT * FROM sl_users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Неправильно - прямая подстановка (уязвимо)
$query = "SELECT * FROM sl_users WHERE user_id = " . $user_id;
```

### Права доступа к БД

Рекомендуется создать отдельного пользователя БД с ограниченными правами:

```sql
-- Создание пользователя с минимально необходимыми правами
CREATE USER 'slaed_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON slaed_cms.* TO 'slaed_user'@'localhost';
FLUSH PRIVILEGES;
```

## Резервное копирование

### Автоматическое резервное копирование

SLAED CMS поддерживает автоматическое резервное копирование структуры и данных:

```sql
-- Резервное копирование структуры таблиц
SHOW CREATE TABLE sl_users;

-- Резервное копирование данных
SELECT * FROM sl_users INTO OUTFILE '/backup/users_backup.sql';
```

### Восстановление из резервной копии

```sql
-- Восстановление структуры
SOURCE /backup/users_structure.sql;

-- Восстановление данных
LOAD DATA INFILE '/backup/users_backup.sql' INTO TABLE sl_users;
```

## Миграции базы данных

### Версионирование схемы

Для управления изменениями структуры БД используется система миграций:

```sql
-- Пример миграции для добавления нового поля
ALTER TABLE sl_users ADD COLUMN user_phone VARCHAR(20) DEFAULT '' AFTER user_email;
```

### Совместимость версий

При обновлении системы автоматически применяются необходимые миграции для обеспечения совместимости структуры БД.

## Мониторинг и обслуживание

### Анализ производительности

Регулярный анализ запросов помогает выявить узкие места:

```sql
-- Анализ медленных запросов
SHOW PROCESSLIST;

-- Статистика использования индексов
SHOW INDEX FROM sl_users;
```

### Оптимизация таблиц

Регулярная оптимизация таблиц улучшает производительность:

```sql
-- Оптимизация таблиц
OPTIMIZE TABLE sl_users, sl_modules, sl_config;
```

### Статистика использования

Мониторинг использования БД позволяет планировать масштабирование:

```sql
-- Размер таблиц
SELECT 
    table_name AS `Table`,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) `Size in MB`
FROM information_schema.TABLES
WHERE table_schema = 'slaed_cms'
ORDER BY (data_length + index_length) DESC;
```

## Резервное копирование и восстановление

### Политика резервного копирования

Рекомендуется следующая политика резервного копирования:
- Ежедневные инкрементальные копии
- Еженедельные полные копии
- Ежемесячные архивные копии с долгосрочным хранением

### Скрипты резервного копирования

Пример скрипта для автоматического резервного копирования:

```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u username -p password slaed_cms > /backup/slaed_cms_$DATE.sql
gzip /backup/slaed_cms_$DATE.sql
```

Структура базы данных SLAED CMS спроектирована для обеспечения высокой производительности, безопасности и надежности при работе с контентом и пользователями системы.