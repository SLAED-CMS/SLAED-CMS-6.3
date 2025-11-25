# Шаблоны SLAED CMS

SLAED CMS использует гибкую систему шаблонов, которая позволяет полностью изменить внешний вид сайта без вмешательства в код ядра системы.

---

*© 2005-2026 Eduard Laas. All rights reserved.*

## Структура тем

Каждая тема оформления находится в отдельной папке в директории [templates](file:///c:/OSPanel/home/slaed.loc/public/templates)/ и имеет следующую структуру:

```
templates/
├── default/                 # Тема по умолчанию
│   ├── index.html           # Основной шаблон страницы
│   ├── basic.html           # Базовый шаблон для контента
│   ├── title.html           # Шаблон заголовка
│   ├── pagenum.html         # Шаблон пагинации
│   ├── block-*.html         # Шаблоны блоков
│   ├── *.html               # Специализированные шаблоны
│   ├── theme.css            # CSS темы
│   ├── system.css           # Системные стили
│   ├── blocks.css           # Стили блоков
│   └── images/              # Изображения темы
└── custom_theme/            # Пользовательская тема
    ├── index.html
    └── ...                  # Остальные файлы темы
```

## Основные шаблоны

### index.html - Главный шаблон

Главный шаблон определяет общую структуру страницы сайта. Он должен содержать следующие обязательные элементы:

```html
<!DOCTYPE html>
<html lang="{%lang%}">
<head>
    <meta charset="UTF-8">
    <title>{%title%}</title>
    <link rel="stylesheet" href="templates/{%theme%}/theme.css">
</head>
<body>
    <header>
        <h1>{%sitename%}</h1>
        <p>{%slogan%}</p>
    </header>
    
    <nav>
        <!-- Навигация -->
    </nav>
    
    <main>
        {%content%}
    </main>
    
    <aside>
        {%blocks%}
    </aside>
    
    <footer>
        <p>&copy; 2026 {%sitename%}</p>
    </footer>
</body>
</html>
```

### Плейсхолдеры главного шаблона

- `{%lang%}` - Язык сайта (ru, en, de и т.д.)
- `{%title%}` - Заголовок страницы
- `{%sitename%}` - Название сайта
- `{%slogan%}` - Слоган сайта
- `{%content%}` - Основной контент страницы
- `{%blocks%}` - Блоки сайта
- `{%theme%}` - Название текущей темы

## Система блоков

### Типы блоков

SLAED CMS поддерживает следующие позиции блоков:
- **l** - Левая колонка
- **r** - Правая колонка
- **c** - Центральная часть
- **d** - Нижняя часть страницы
- **s** - Плавающие блоки
- **o** - Плавающие блоки (альтернативные)

### Шаблоны блоков

Для каждого блока система ищет специфичный шаблон по следующему приоритету:
1. `block-{block_id}.html` - Индивидуальный шаблон блока
2. `block-{module}.html` - Шаблон для модуля
3. `block-{position}.html` - Шаблон для позиции (left, right, center, down)
4. `block-all.html` - Общий шаблон для всех блоков

Пример шаблона блока:
```html
<div class="block block-{%position%}">
    <h3>{%title%}</h3>
    <div class="block-content">
        {%content%}
    </div>
</div>
```

## API шаблонизатора

### setTemplateBasic($tpl, $values)

Основная функция для работы с шаблонами. Загружает шаблон из текущей темы и заменяет в нем плейсхолдеры.

```php
$content = setTemplateBasic('basic', array(
    '{%title%}' => 'Заголовок страницы',
    '{%content%}' => 'Содержимое страницы'
));
```

### setTemplateBlock($tpl, $values)

Функция для работы с блоками. Использует специфичные шаблоны блоков.

```php
$block = setTemplateBlock('block-center', array(
    '{%title%}' => 'Название блока',
    '{%content%}' => 'Содержимое блока'
));
```

### setTemplateWarning($tpl, $settings)

Функция для отображения системных сообщений и предупреждений.

```php
$message = setTemplateWarning('warn', array(
    'text' => 'Текст предупреждения',
    'url' => '?name=module',
    'time' => '5',
    'id' => 'warning-id'
));
```

## Создание собственной темы

### 1. Подготовка структуры

Создайте новую папку в [templates](file:///c:/OSPanel/home/slaed.loc/public/templates)/ с названием вашей темы:
```bash
mkdir templates/my_theme
```

### 2. Основные файлы темы

Минимальный набор файлов для новой темы:
- `index.html` - Главный шаблон
- `basic.html` - Базовый шаблон контента
- `block-all.html` - Шаблон блоков по умолчанию
- `theme.css` - Стили темы

### 3. Адаптация существующей темы

Для создания темы на основе существующей:
1. Скопируйте папку темы по умолчанию:
   ```bash
   cp -r templates/default templates/my_theme
   ```
2. Измените название папки на желаемое
3. Адаптируйте HTML и CSS под свои нужды

## Работа с CSS

### Системные стили

SLAED CMS предоставляет базовые CSS классы для быстрого оформления:
- `.sl_block` - Базовый класс блока
- `.sl_content` - Контентная область
- `.sl_form` - Формы
- `.sl_button` - Кнопки
- `.sl_table` - Таблицы

### Собственные стили

Для добавления собственных стилей:
1. Создайте файл `theme.css` в папке темы
2. Подключите его в `index.html`:
   ```html
   <link rel="stylesheet" href="templates/{%theme%}/theme.css">
   ```

## Продвинутые возможности

### Условные шаблоны

Шаблоны могут содержать условные конструкции:
```html
<!-- IF {%user_logged%} -->
<div class="user-panel">
    Добро пожаловать, {%username%}!
</div>
<!-- ELSE -->
<div class="login-form">
    <a href="/login">Войти</a>
</div>
<!-- ENDIF -->
```

### Циклы в шаблонах

Для отображения списков элементов:
```html
<!-- BEGIN item -->
<div class="item">
    <h4>{%item.title%}</h4>
    <p>{%item.description%}</p>
</div>
<!-- END item -->
```

## Оптимизация шаблонов

### Кэширование

SLAED CMS автоматически кэширует шаблоны для повышения производительности:
- Кэш хранится в [config/cache/](file:///c:/OSPanel/home/slaed.loc/public/config/cache/)
- Автоматическая очистка при изменении файлов
- Возможность принудительной очистки кэша

### Минификация

Система поддерживает автоматическую минификацию CSS и JavaScript:
- Сжатие CSS файлов
- Объединение нескольких CSS в один файл
- Минификация JavaScript кода

## Отладка шаблонов

### Режим разработки

Для отладки шаблонов включите режим разработки в конфигурации:
```php
// config/config_core.php
$conf['debug_mode'] = 1;
```

### Инструменты отладки

В режиме разработки доступны:
- Отображение всех плейсхолдеров
- Проверка существования шаблонов
- Логирование ошибок шаблонизатора

## Совместимость тем

### Поддержка разных браузеров

Темы SLAED CMS поддерживают:
- Современные браузеры (Chrome, Firefox, Safari, Edge)
- Мобильные устройства (адаптивный дизайн)
- Старые браузеры (ограниченно)

### Адаптивный дизайн

Для создания адаптивных тем используйте:
```css
/* Мобильные устройства */
@media (max-width: 768px) {
    .container {
        width: 100%;
        padding: 10px;
    }
}

/* Планшеты */
@media (min-width: 769px) and (max-width: 1024px) {
    .container {
        width: 90%;
    }
}

/* Десктопы */
@media (min-width: 1025px) {
    .container {
        width: 80%;
        max-width: 1200px;
    }
}
```

## Примеры шаблонов

### Шаблон новости

Файл: `news-view.html`
```html
<article class="news-item">
    <header>
        <h1>{%title%}</h1>
        <div class="meta">
            <span class="date">{%date%}</span>
            <span class="author">{%author%}</span>
        </div>
    </header>
    <div class="content">
        {%content%}
    </div>
    <footer>
        <div class="tags">{%tags%}</div>
        <div class="rating">{%rating%}</div>
    </footer>
</article>
```

### Шаблон формы

Файл: `form-basic.html`
```html
<form method="post" action="{%action%}" class="sl_form">
    <div class="form-group">
        <label for="title">{%title_label%}</label>
        <input type="text" id="title" name="title" value="{%title_value%}" required>
    </div>
    <div class="form-group">
        <label for="content">{%content_label%}</label>
        <textarea id="content" name="content" required>{%content_value%}</textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="sl_button">{%submit_text%}</button>
        <a href="{%cancel_url%}" class="sl_button secondary">{%cancel_text%}</a>
    </div>
</form>
```

Эта система шаблонов обеспечивает гибкость и мощность при создании уникального дизайна для вашего сайта на SLAED CMS.