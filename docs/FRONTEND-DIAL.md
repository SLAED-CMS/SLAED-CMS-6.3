# Аудит и план: веер sl-dial для модераторских меню фронтенда

Статус: аудит завершён, план утверждается. Дата: 2026-07-13.

## Проблема

На фронтенде (тема lite) модераторское меню «Редактор» (шестерёнка) в списках
`index.php?name=*&op=liste`, карточках и на страницах материалов открывается
наведением вниз тёмной панелью `sl-float-panel` и накрывает 1-2 соседние строки
(замер на `news&op=liste`: панель 189×77px поверх 2 строк). Колонка «№» узкая —
те же перехлёсты, что были в админке до перехода на веер `sl-dial`.

## Аудит: где живёт меню

Данные меню строит один хелпер — `getTplEditMenu()` (`core/helpers.php`):
ключи `is_moder`, `editor_label`, `edit_href`, `delete_href`, `delete_ask`.
Рендерит их lite-фрагмент `popover.html` (фрагмент `edit-actions.html` — его алиас).

**Шаблоны lite, выводящие меню (4):**

| Шаблон | Где виден |
|---|---|
| `fragments/table-row.html:105` | табличные списки `op=liste` (news, pages, files, links, media) |
| `fragments/card.html:37` | карточки списков/категорий (news, pages, faq, files, links, media, jokes, auto_links, content) |
| `partials/view.html:8` | страница материала (`op=view`) |
| `partials/voting-home.html:5` | таблица опросов |

**PHP-вызовы (22 места, меню как данные):** `$row += getTplEditMenu(...)` /
`...getTplEditMenu(...)` в modules: auto_links, content (2), faq (2), files (3),
links (3), media (3), news (3), pages (3), jokes, voting.

**PHP-вызовы мимо хелпер-ключей (3):** `modules/shop/index.php:97,261` —
`getHtmlFrag('edit-actions', ...)` с `edit_link`/`delete_link`;
`modules/content/index.php:38` — `getHtmlFrag('popover', getTplEditMenu(...))`.

**Чего нет в lite:** стилей `sl-dial` (есть только в admin theme.css),
фрагмента `dial.html`, клик-механики веера (живёт в `admin-ui.js`, который
на фронт не грузится; общий `slaed.js` грузится и там и там).

## План

1. **PHP — один источник данных.** Переписать `getTplEditMenu()`: возвращать
   dial-ключи `['is_moder' => true, 'dial_title' => _EDITOR, 'dial' => [pencil-edit, trash-delete(confirm)]]`.
   Все 22 `$row += ...`-вызова получают новые данные без правок.
2. **Шаблоны lite.** Скопировать `fragments/dial.html` из admin-темы (темы
   независимые — копия, не share). В 4 шаблонах заменить
   `{% include 'fragments/popover.html' %}` (модераторская ветка) на
   `{% include 'fragments/dial.html' %}`.
3. **Спец-вызовы.** shop (2): собрать dial-массив на месте (power нет — только
   edit/delete как сейчас); content:38: `popover` → `dial`. Фрагмент
   `edit-actions.html` удалить, ключи `edit_link/delete_link/edit_href/...`
   вычистить из lite `popover.html` (останется только инфо-«i» и user-menu).
4. **CSS lite.** Перенести блок «Speed dial» из admin theme.css в lite
   theme.css на токенах lite (~60 строк: `.sl-dial`, `.sl-dial-toggle`,
   `.sl-dial-item`, каскадные задержки). Якорь в таблицах:
   `td:last-child`/ячейка меню — `position: relative` (проверить перехлёст
   вправо у колонки «№»: веер раскрывается влево — совместимо).
5. **JS.** Перенести клик-toggle веера из `admin-ui.js` в общий
   `plugins/system/slaed.js` (делегированный обработчик, ~10 строк) — один код
   для фронта и админки; из admin-ui.js удалить.
6. **Не трогаем:** инфо-«i» (`sl-tip`), меню пользователя (`is_user_menu`),
   навигацию шапки.

## Верификация

- `php -l`, `phpstan`, `phpunit`.
- Playwright под админом: свип `op=liste` всех модулей (news, pages, files,
  links, media, faq, jokes, auto_links, content) + `op=view` + опросы + shop:
  веера присутствуют, gear-попапов в модераторских местах 0, JS-ошибок нет.
- Проверить гостем: модераторские веера отсутствуют, вёрстка списков не
  изменилась.
- Клик по «удалить» — confirm срабатывает; редактирование ведёт в админку.

## Риски

- Веер в колонке «№» раскрывается влево поверх ячеек своей строки — на узких
  экранах может выйти за левый край таблицы: проверить на 360px, при
  необходимости добавить сжатие ячеек-иконок в мобильном брейкпоинте.
- Страница `op=view`: веер в мета-строке (не таблица) — якорь `li.sl-meta-actions`,
  проверить наложение на заголовок.
