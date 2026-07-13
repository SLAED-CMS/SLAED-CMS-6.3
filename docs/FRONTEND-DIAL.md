# Speed dial (sl-dial) — референс механизма

Единое меню действий для фронтенда (тема lite) и админки: тогл-чип, веер иконок-чипов,
подтверждение удаления. Темы независимы — у каждой свои копии `fragments/dial.html`,
`fragments/link.html` и CSS; контракт ключей общий, потому что PHP один.

## Контракт данных

Фрагмент `dial.html` принимает:

| Ключ | Значение |
|---|---|
| `dial` | массив элементов веера (без него фрагмент не рендерится) |
| `dial_title` | title тогла (`_EDITOR`, `_FUNCTIONS`, `_USER`) |
| `is_user_menu` | lite: тогл three-dots вместо шестерёнки (admin-тема всегда three-dots) |

Элемент `dial[]`:

| Ключ | Значение |
|---|---|
| `href` | URL действия |
| `icon_name` | bootstrap-иконка без префикса `bi-` (pencil, trash, eye, power, ...) |
| `title` | подпись (tooltip) |
| `confirm_text` | текст confirm ЧИСТЫМ текстом с обычными кавычками: `_DELETE.' "'.$title.'"?'` |
| `is_htmx`, `is_post`, `hx_target`, `hx_swap` | htmx-действие (lite): hx-get/hx-post на `href` |
| `is_blank` | target="_blank" + rel |
| `link_attr`, `onclick_attr` | сырые атрибуты для остального (data-sl-toggle, history.go и т.п.) |

## Продюсеры

- `getTplEditMenu($edit, $del, $title)` (`core/helpers.php`) — стандартная пара
  pencil+trash(confirm) с ключами `is_moder`/`dial_title`/`dial`; модули добавляют её
  в данные строки (`$row += ...`) или рендерят напрямую `getHtmlFrag('dial', ...)`.
- `getActionMenu(array $items, bool $user = false)` (`core/system.php`) — произвольный
  набор структурированных элементов; `$user = true` даёт user-menu (три точки).
- Никогда не собирать в PHP HTML/OnClick-строки для меню: экранирование делает шаблон.

## Подтверждение удаления (data-sl-confirm)

PHP передаёт `confirm_text` без сущностей и без addslashes/htmlspecialchars.
Шаблон рендерит `data-sl-confirm="{{ confirm_text }}"` (экранирование однократное).
Делегированный обработчик в `plugins/system/slaed.js` (`setDialToggle`) вызывает
`confirm()` и гасит переход при отказе. Для htmx-элементов этот механизм не подходит
(их обработчики срабатывают раньше документного) — при необходимости использовать `hx-confirm`.

## CSS (lite)

- `.sl-dial` — инлайновый чип в потоке; элементы веера абсолютные, раскрываются влево
  оффсетами `right: calc(...)` по nth-child (до 6 элементов).
- Пилюля-подложка `.sl-dial::before` — ширина от `--sl-dial-count`, который выставляют
  чистые CSS-правила `:has(.sl-dial-item:nth-child(N):last-child)`.
- В открытом состоянии пилюля получает `pointer-events: auto` — она мостит зазоры между
  чипами, иначе `:hover` слетает и веер схлопывается до клика.
- `.sl-dial-toggle` — `position: relative`, иначе пилюля рисуется поверх тогла.
- В admin-теме своя схема: абсолютный контейнер в выделенной ячейке `td.sl-col-actions`.

## JS

- Клик-фиксация веера (`.sl-open`) и confirm — `plugins/system/slaed.js`, грузится и на
  фронте и в админке (`script_f`); в `admin-ui.js` dial-кода нет.
- Ajax-гейт `go=N` (`index.php`) требует `token` в URL или заголовок `X-CSRF-TOKEN` —
  все htmx-элементы веера и inline-формы (`getTplAjaxTextarea`) обязаны передавать
  `&token='.getSiteToken()`.
