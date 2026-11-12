# Versions

## 2026-11-12

### The presentation stands in the window

- **A frame with a head is `.sl-pane` on the presentation too** — the cards of the guard, of the runtime, of the
  module groups and of the delivery, the vitals, the panel of the block layout, the hours of the statistics, the focus
  of the commits, the cache tree and the claim; the focus, the tree and the claim come down from radius 12 to 8.
  `.sl-pres-card` and `.sl-pres-panel-head` are gone. A theme that copied them takes `fragments/pane-cap.html`.
- **One head draws every head of the page** — of a window, of the model of the hero, which stays a drawing, and of
  the four groups that draw no frame. On the presentation it is the head of the panel: mono capitals, a bare icon of
  16 px, a rule, 43 px high where the strip was 34.
- **A head of `aurora` carries a value** — `meta` with `meta_tone` and `meta_icon`, printed as `.sl-pane-meta` at the
  right end, the name the panel uses; the route of the hero, the focus of the commits, the mode of the cache and the
  poll of the statistics are written into it by the plugin.
- **A rule between rows inside a window is dashed** — the rows of the traffic types, of the last decisions, of the
  cache tree and of the block rules; the frame and the rule under the head stay solid.
- **Three heads changed their mark** — the commits carry an icon where the live lamp stood, the count of the active
  blocks takes the tone of a state as the groups above it, and "Опрос" runs its dots in the tone of the value.

### An address with a plus or a long zone registers, and a refused captcha says to send the form again

- **`checkemail()` takes the address as it was typed** — a plus in the name and a zone of up to 63 letters pass
  (`ivan+news@gmail.com`, `name@site.photography`). The URL decoding that turned the plus into a space is gone from
  the check, from the registration form shown again after a refusal and from the row the registration writes, and the
  links of the password recovery carry the address encoded, since PHP reads a bare plus of a query as a space. An
  address without a zone, with a space or a tag is refused as before; a percent code is no longer decoded and is
  refused too. Held by `EmailCheckTest`.
- **`_SECCODEINCOR` tells the visitor to send the form once more** — a form that stood open past the life of its
  captcha (`ttl`, 600 s by default) is refused once and passes on the next send, so the text no longer sends the
  visitor to the administration first; the six locales.

### The upload of an archive follows a found release, and the channels speak to a site owner

- **The window "Загрузить архив" of "Обновления" stands only while the master offers a release** — the page goes
  "Искать обновления", then "Скачать" or the upload, then "Установить"; with no release found or a check that failed
  there is nothing an upload could be accepted against, and the form is not shown.
- **The channels say whom they suit** — "Stable — для рабочего сайта" and "Beta — для тестовой копии" in the switch
  and in "Состояние", and the row of the switch carries a hint (`_UPDATE_CHANHINT`) that tells the two apart in plain
  words, in the six locales.

### The cabinet of the site stands in the window

- **One window on the site and in the panel** — the panels of the cabinet and of the profile, the tiles of the
  settings, the shelves of the favourites and the focus of the private messages are `.sl-pane`, drawn by
  `fragments/pane.html` and `fragments/pane-cap.html` of `aurora`; `.sl-profile-panel`, `.sl-opt-tile`,
  `.sl-profile-hub-head` and `fragments/panel-head.html` are gone. A theme that copied them takes the new fragments.
- **Seven tokens hold the window** — `--sl-pane-border`, `--sl-pane-radius`, `--sl-pane-bg`, `--sl-pane-cap-bg`,
  `--sl-pane-pad-x`, `--sl-pane-pad-y`, `--sl-pane-shadow` in the API block of both themes; the panel reads them where
  it wrote the values.
- **The head is a chip and the inset twice high** — 50 px on the site, the icon on a plate in the tone of its
  category, no rule; the interests and the groups of a profile are two windows, and the text fields of a profile
  stand under the head "Дополнительные поля".
- **A window of the site folds as one of the panel** — a press anywhere on the head of a shelf or of the focus, the
  plus in place of the chevrons; the state is kept under a new key, so a shelf folded before opens once.
- **The inner list of a window is rounded 4** and a slot of the focus 8, so nothing inside a window is rounded more
  than it; the answer of an RSS feed appears inside the window "RSS-каналы".

### The long forms of the panel are parted into windows

- **A long form stands in windows named by what they set** — "Конфигурации" in fourteen over its five tabs ("Сайт",
  "Оформление", "Сессии и Cookies", "Списки и живые блоки", "Панель", "Отладка"; "Заголовки и описание",
  "Перенаправления", "Разметка"; "Язык", "GeoIP"; "Цензура"; "Кеш", "Стили и скрипты"), the transport of "Почта" in
  five ("Письмо", "Транспорт", "Возвраты", "Очередь", "Проверка адресов"), "Карта сайта" in five, the settings of
  "Безопасность" and of "Форум" in three each, a user of "Пользователи" in five with the nick as the fact of an edit.
  The rows are regrouped by meaning; no field name, address or handler changed.
- **The form of a group is three windows** — "Готовый ранг", "Свой ранг" with the proof at its right and "Группа" with
  the name of the record as the fact of an edit; an icon stands where the number of a part stood, the buttons under
  the last window, and the frame of the console is the window.
- **Every constant of a language file is a window** — "Языковая константа N" with the name of the constant as the fact
  of its head, the pager under the windows.
- **The search of settings leads into a window** — it stays a list of hits, opens the tab and puts the cursor in the
  field, and hides neither a row nor a window.
- **Twenty-three names of windows in the six locales** — `_CONFIG_*`, `_ACCOUNT_NOTICE`, `_ACCOUNT_POINTS`,
  `_MAIL_LETTER`, `_MAIL_RETURNS`, `_MAIL_CHECK`, `_MAP_*`, `_SEC_ATTACKS` (`admin/lang/*.php`) and `_FORUM_*`
  (`modules/forum/admin/lang/*.php`).

### A form of one record and a form of one action stand in the window

- **The window of a record form is named by the record** — "Администратор", "Блок", "Файловый блок", "Комментарий",
  "Модуль", "Рассылка", "Задание" (`_SCHEDULER_JOB`), "Запрос", "Опрос" (`_VOTING_POLL`), "Материал", "Выпуск"
  (`_MASTER_RELEASE`), "IP-адрес" and "Пользователь" of a ban. An edit shows the stored name of the record as the fact
  of the head and an addition shows none; the kind of a block is a chip of the head and its line of facts over the
  form is gone.
- **The window of an action form is named by its tab** — "Обслуживание" of "База данных", "Сброс" of "Пользователи",
  "Импорт" of "Материалы", "Удалить" of "Поиск", "Код" and "Редактировать" over the choice of a block file and of a
  category.
- **A form with tabs of its own puts one window under each tab** — "Категория", "Доступ" and "Форум" of a category,
  the five tabs of rows of a Node type; the first carries the fact of an edit.
- **The answers of a poll stand under the window "Опрос"** — a chain of windows of the form with the caption at the
  left, no longer windows in the field of a row; the rule of `.sl-div-field` for them is gone.
- **The head of a window that does not fold keeps one line** — the name is as wide as its word, the fact takes the
  line that is left and ends in an ellipsis.
- **A form that is one editor stands without a window** — the code of a block, the file and the XSL of the sitemap,
  the query of "База данных", a file of "Файлы", the help of a section, the templates of the uploads.

### The sections of a page and the short forms of settings stand in the window

- **A titled section over a form or over facts draws `fragments/pane.html`** — "Загрузить архив", "Установить", the
  rescue code and "Пробная установка" of "Обновления", the annulment of a vote in "Рейтинги", the source of a
  material, the clone of a type and the working card with the text of a request in "Материалы". Each names an icon,
  and a form of its own stands in the window with its button. A title over a table stays a heading of
  `partials/box.html`: the lists of "Поиск", the comments of a request, the reports of a file, the remains of a type.
- **The first block of "Обновления" is the window "Состояние"** (`_UPDATE_STATE`) — the facts, the notes and the row
  of buttons; the page is windows 10 pixels apart.
- **Every short form of settings stands in one window** — the tab "Конфигурации" of "Комментарии", "Фавориты",
  "Редактор языков", "Журналы", "Почта", "Личные сообщения", "Статистика", "Обновления", "Журнал изменений", "Обратная
  связь", "Мастер", "Материалы", "Поиск" and "Опросы" names it "Основные" (`_GENPREF`); "Доступ" and "OAuth" of
  "Безопасность" name it by the tab. The submit stands under the window. "Пункты" of "Группы" puts its switch in a
  window of that name and keeps the table of actions under it.
- **A `kv-list` in a window of a page keeps the columns of its form rows** — dashed rules out to the frame; the narrow
  list with the value at the right now belongs to a window of a board alone (a child of `.sl-ground`,
  `.sl-pane-split`, `.sl-pane-trio` or `.sl-pane-side`).
- **The key of a cloned type is a fact of the head** — the name of the window is capitals, the key keeps its case.

### The settings blocks move onto the window, and a window folds under its head

- **`fragments/pane.html` of `admin` folds under the flag `is_fold`** — the body waits under the plus at the right end
  of the head, and a press anywhere on the head goes to that plus: the head carries `data-sl-toggle-head`, which
  `public/plugins/system/slaed.js` serves for any head. A press on a control, or one that ends a selection in the head,
  folds nothing; the name of a folding head is not selectable. A folded window is its head alone, and the browser keeps
  the choice under the ids the blocks had.
- **The head takes `facts`, `chips` and `tools_html`** — the facts stand in columns of a fixed share of the width in
  their own case, the chips close the head before the plus, the tools are the buttons of the caller. At 1200 pixels and
  narrower the facts and the chips take a second line.
- **With `next_id` the plus adds** — it shows and hides the next window of a chain, as in "Замены", the feeds of `rss`
  and the answers of a poll; the head of such a window is not pressed.
- **`partials/toggle-form-block.html` and the rules of `.sl-toggle-form-block` are gone** — "Замены", "Дополнительные
  поля", "Рейтинги", the rules and the templates of the file manager, the settings of "Пользователи", the file roles
  of a Node type, the feeds of `rss`, the answers of a poll and the items of the site builder draw the window, each
  with an icon. Inside a window the rules between form rows are dashed and the rows reach the frame; windows in a
  stack stand 10 pixels apart; in the inspector of the builder a window has neither shadow nor gradient. The line of
  sections in the history of publications wears the plus of the window.

### The window of the panel takes one standard, and a form row is as tall as its field

- **`fragments/pane.html` of `admin` is the one window of the panel** — a thin frame of radius 8 with the shadow of a
  list, a gradient that ends with the head, a rule under the head and an inset of 8 by 10. The head is 43 pixels high
  and its icon 16. "Мониторинг" and "Статистика" wear it.
- **Every window names its icon** — the three lamps of a head and their animation are gone, and `pane-cap.html` prints
  the icon alone; the sixteen windows that showed the lamps got an icon of their own.
- **A form row of the panel carries no minimum height** — `.sl-div-item` is as tall as its field and its own inset, so
  the field stands as far from the rule over it as from the one under. A row with one field is 45 pixels high where it
  was 55.

### The two windows of the statistic that move with the day carry the live chip, and the figure of the rhythm runs

- **"Статистика / трафик сайта" and "Хиты по часам" carry the live chip** of the running month, as the window of
  the traffic does in "Мониторинг": a chip pauses its own window and names its own last reading, the chip of the
  cockpit holds the cockpit and every window without a chip, and the poll runs while one of the three does. A day picked
  or a switch of "Сейчас / Вчера" redraws the whole page under a pause too.
- **The figure over the chart of "Хиты по часам" runs to its new value** by `setFigureRun()`, as the figure of a tile
  does; a period of the rhythm carries `num` beside `figure`.
- **A split bar ends round whatever its shares** — a share of zero keeps its key and draws no part, where its empty
  part took the rounding of the end and left the next one square. In a window that takes the whole row ("Боты") the
  keys of the bar stand in one line from one end of the bar to the other; a side window keeps its two columns.

## 2026-11-10

### The avatar ships the limits of today, and its settings stand in a block of their own

- **The shipped avatar rule** — 256 by 256 pixels, 5 MB a file and a quota of 512 MB for the folder, where it was
  100 by 100 pixels and 51200 bytes. A photo of a phone passes the weight and is scaled into the bounds. The formats
  are as they were.
- **`aquota` of `config/users.php` is the quota of the avatar folder** — `getUploadPlaceRule('users.avatar')` answers
  it as `maxquota`, the class refuses an upload past it with `quota`, and the file window of the avatar prints the
  bytes in use against it.
- **The settings of the users section stand in two blocks** — "Avatar" holds the upload switch, the folder, the
  formats, the weight and the quota in megabytes and the two bounds with their hint; the rest is "Registration,
  sign-in and profile". The weight is still stored in bytes.
- **The file window names its module by its title** — "Module: Profile" where it printed the key `account`; the limit
  sentence of the window names the formats and the weight.
- **Breaking:** the key `user_t` of `config/users.php` and its row "Rating interval" are gone: nothing read the value.
  The constant `_ASIZE` is gone with the byte field it labelled.

### An image over the bounds of its upload rule is scaled down, not refused

- **`Upload` fits an image into `maxwidth` and `maxheight` when it is uploaded** — in proportion, in its own format,
  turned the way a browser shows it, through the editor window, the catalogue, a remote link and the avatar alike.
  JPEG and WebP are written at quality 85, AVIF at 60, PNG without loss. An image inside the bounds is stored byte for
  byte, as before. `docs/ARCHITECTURE.md`, "Image Fit Boundary", holds the contract and what was measured.
- **The orientation is read by the class itself** — the Exif segment of a JPEG, the `eXIf` chunk of a PNG, `irot` and
  `imir` of an AVIF; the extension `exif` is not needed. The bounds are compared with the size the image is shown at.
- **A scaled file carries no metadata but the colour profile of a JPEG** — EXIF with its GPS position leaves it; the
  ICC segments of a JPEG are carried over, so a photo with Display P3 keeps its colours. `maxbytes` stays the limit of
  the transfer and is compared before the scale; a scaled file heavier than it is stored.
- **`dimensions` is still answered** for an animation over the bounds, for an image whose decode does not fit into
  `memory_limit`, and for a type the build reads but cannot write (`encoder_missing` in `error_file.log`). The decode
  of every image, inside the bounds too, is budgeted from the header, so a picture too large for the memory is refused
  instead of ending the request.
- **The pick of a form row checks no bounds** — `checkFieldFile()` refuses on extension and weight; the options of
  the window carry no `maxwidth` and `maxheight`, and its limit sentence names the formats and the weight alone. The
  settings of the file manager say under the two bounds what happens to a larger image, and so do the avatar text of
  the profile and the help of the panel.

### The settings blocks fold into one series, and the upload rules ship the limits of today

- **`partials/toggle-form-block.html` of `admin` folds under the flag `is_fold`** — the cap carries the name, the
  list `facts` as columns of a fixed share of the width and the list `chips` at the right edge; the rows wait under
  the "+" of the cap, and the browser keeps the choice. The upload rules and the attachment templates of the file
  manager, the ratings, the extra fields and the file roles of a Node type draw their blocks this way.
- **The upload rules are edited by meaning** — the formats are checkboxes by family, the size of a file and the quota
  are megabytes, the access switches come first, and a label is a statement that stands as a chip in the cap. A folder
  is named by what it holds (`account` the private messages, `profile` the profile, `layout` the site builder), and
  every path reads from `uploads/`.
- **The shipped rules** — the record `all`, every Node type and the builder take 25 MB a file, 4096 pixels a side and
  a quota of 1 GB; the visitor folders 5 MB, 4096 pixels, 512 MB and five files a send. A thumbnail is 320 pixels wide,
  the default box of an attachment is 640 by 360, and a guest sees 20 files in the editor window. The formats of the
  visitor folders are as they were.
- **The neutral chip of `admin` takes its tint from its own colour**, as every other tone does, so it is seen in the
  dark scheme.

### The options of a select arrive as data, and the template walks them

- **`fragments/select.html` of both themes draws its options from the array `options`** — an item is `value_attr`,
  `label_text`, `is_selected` and `is_hidden`, and an item with `is_group` is a group with its own `options`. PHP no
  longer glues the list out of calls of `select-option`: 154 calls in 28 files and every sender of `options_html` pass
  the array. The HTML of the selects is the same up to whitespace on 111 routes of the panel and the site.
- **`fragments/select-options.html` is the one place of that loop** — the select includes it, and `find()` of the site
  builder answers the search of a record with it, the options without their select. `docs/TEMPLATES.md`, "Screen
  From Data", is the standard, and `testSelectOptionsArriveAsData()` holds it.
- **Breaking:** the key `options_html` of `fragments/select.html` is gone, and so are `fragments/select-optgroup.html`
  of `admin` and `fragments/forum-optgroup.html` of `aurora`; `partials/account-home.html` reads `rss_options` instead
  of `rss_options_html`. `getTplLanguageOptions()` and `rss_select()` return the array of options,
  `getTplCategorySelect()` takes `$empty` as that array and returns it when `$raw` is set. A theme of its own adds
  `fragments/select-options.html` and includes it from its select; a module of its own passes `options`.

### The audit holds raw output under a name that does not say markup

- **The count `raw` of `tools/ui-audit.php` is every `{{{ key }}}` of a theme whose key does not end in `_html`** — 154
  in `aurora` and 67 in `admin`, stored in `tools/ui-audit-baseline.json` and ratcheted: a template that adds one fails
  `ThemeContractTest`, and with it `npm run ui:gates` and the commit hook. `php tools/ui-audit.php --theme=<theme> --raw`
  prints the keys and the places. No key is renamed and the engine is as it was.

### A theme carries a manifest, and the core names the reason it refuses one

- **`theme.json` in the root of a theme names the contract it meets** — two strings, `api` (the release whose theme
  contract the package follows, `8.0`) and `version` (the theme's own). The core accepts the versions `THEME_API` of
  `core/system.php` lists, and a release that leaves the contract alone leaves the list alone. `admin` and `aurora`
  ship theirs; the name of a theme stays the name of its folder, so a copy of an etalon is a theme at once.
- **`getThemeFaults()` answers why a folder is not a theme** — the manifest, its fields, the contract version, then
  every missing file by name; `checkThemeAssets()` is that list being empty. The theme area of the file manager
  prints the reasons above the files of the chosen theme, and the line `Active theme incomplete` of the site log
  carries them as `faults`.
- **Breaking:** a theme without `theme.json`, with one that is not JSON, without `api` or `version`, or with an `api`
  the release does not list is refused: it leaves the theme lists of the panel and of the profile, and a member who
  chose it sees the theme of the site. A theme of its own adds the file `{"api": "8.0", "version": "<its own>"}`.

### One gallery partial draws both galleries of the presentation, and a row written once stands in its parent

- **`partials/presentation-gallery.html` of `aurora` draws the brand archive and the showcase of sites** — the flag
  `is_brand` of the archive chooses the section `archive` with its picture tiles and the walk of the `view` window,
  and without it the partial is the section `showcase` over `fragments/presentation-site.html`. The commit, the
  module and the voice of the presentation are written in `partials/presentation-dev.html`,
  `presentation-modules.html` and `presentation-voices.html`, and `partials/forum-teaser.html` walks its topics
  itself. The pages answer the same markup as before.
- **Breaking:** seven templates of `aurora` are gone — `partials/presentation-archive.html` and
  `presentation-sites.html`, `fragments/presentation-brand.html`, `presentation-commit.html`,
  `presentation-module.html`, `presentation-voice.html` and `forum-teaser-item.html`. Code of its own that renders one
  of them by name gets nothing and a line in the log. `partials/forum-teaser.html` takes `items` as a list of rows
  (`hidden`, `href`, `title`, `poster`, `when`) with `by` and `when_label` beside `label`, no longer as a string of
  markup.

### The base view and card of aurora draw the Node modes that carried a copy of them

- **The view and the card of a material read the flag of its display mode** — `is_docs`, `is_faq`, `is_files`,
  `is_media`, `is_support` and `is_event` of `getNodeViewVars()`, the one of `view.mode` true and none for `default`
  and `article`. `partials/node/view.html` of `aurora` draws `docs`, `faq`, `files` and `media` from it,
  `fragments/node/card.html` draws `files`, `support` and `event`; the pages answer the same markup as before.
- **Breaking:** seven templates of `aurora` are gone — `partials/node/docs/view.html`, `faq/view.html`,
  `files/view.html` and `media/view.html`, `fragments/node/files/card.html`, `support/card.html` and
  `event/card.html`. Code of its own that renders one of them by name gets nothing and a line in the log. The modes
  keep `fragments/node/docs/card.html`, `faq/card.html` and `media/card.html`, `partials/node/event/view.html` and
  `support/view.html`, and a theme of its own may carry any `node/<mode>/<file>` as before.

### The themes drop the templates nothing renders and the keys nothing sends

- **Seventeen templates no caller rendered are gone** — twelve of `aurora` (`fragments/block-center-link`, `card`,
  `grid`, `image-preview` and `related-item`, `partials/fieldset-panel`, `related` and `view`, `pages/admin`, `error`
  and `login`, `layouts/admin`) and five of `admin` (`fragments/edit-actions`, `label`, `lines`, `meta-refresh` and
  `save-action`). The single record a frontend theme has to ship is `partials/node/view.html`, in the place of
  `partials/view.html`. The pages of the site and the panel answer the same markup as before.
- **A key no producer sends has no branch** in the fragments of both themes, and the rules and tokens only those
  branches and templates read left the stylesheets with them.
- **Breaking:** a module of its own that renders a removed template gets nothing and a line in the log, and these keys
  are no longer read. `aurora`: `is_level_three`, `is_level_four`, `title_class` of `title`; `is_faq`, `is_amount`,
  `col_id`, `col_title`, `col_cat`, `col_poster`, `body_class`, `head_class`, `title_class` of `table`; `row_class`,
  the `heading_html` of a cell and the row without `cells` of `table-row`; `select` with its labels of `form-submit`;
  `enctype`, `extrafields`, `is_calculator`, `no_action` of `form-add`; `has_back_button`, `back_title`,
  `is_card_post`, `is_form_value` of `span`; `is_download`, `is_hits`, `badge_attr` of `inline-badge`; `button_class`,
  `button_id`, `reload_query` of `button`; `form_class`, `is_inline` of `form-wrap`; `is_private_message`,
  `meta_title` of `forum-post`; `is_calculator` of `input`; `is_related_date` of `date-badge`; `is_mini` of
  `post-button`; `before_html` of `content-list`; `module_link` of `session-row`; `label_class` of `checkbox` and
  `label`; `login` of `layouts/bare`. `admin`: `is_searchbox`, `is_rate_box` of `div`; `actions`, `form_class` of
  `form`; `is_save_action`, `is_search_filter`, `is_search_order`, `is_search_sort` of `select`; `is_search_filter` of
  `input`; `is_chlog_primary`, `is_chlog_secondary`, `button_class`, `button_id` of `button`; `is_note`,
  `is_post_icon`, `is_red`, `badge_attr` of `inline-badge`; `show_toggle`, `show_fallback` with the keys they read
  of `image-preview`; `is_catflex_box`, `is_catflex_cont` of `block-content`; `has_back_button`, `back_title` of
  `span`; `is_mini` of `link` and `post-button`; `row_class` of `div-row` and `table-row`. Both: `details_html`,
  `heading_html` of `message-block`; `act_icon`, `act_text` of `empty`; `class_attr`, `init_attr` of `tabs`;
  `row_class` of `code-row`; `content_id` of `block-sidebar`.
- **Breaking:** the classes `.sl-card-aside`, `.sl-related-item`, `.sl-related-list`, `.sl-related-meta`,
  `.sl-fieldset-center`, `.sl-fieldset-legend-info`, `.sl-fieldset-form-legend-success`,
  `.sl-fieldset-form-legend-danger`, `.sl-view-error` with its four parts, `.sl-table-faq`, `.sl-table-amount`,
  `.sl-calculator-field` and `.sl-copyright` of `aurora`, `.sl-associated-option`, `.sl-red`, `.sl-post-icon`,
  `.sl-save-action-select`, `.sl-search-sort-select`, `.sl-search-order-select` and `.sl-search-filter-field` of
  `admin`, and `.sl-image-preview-toggle`, `-popup`, `-mini` and `-thumb` of both are gone. So are the tokens
  `--sl-aside-width`, `--sl-calc-width`, `--sl-col-amount-width`, `--sl-preview-width`, `--sl-preview-height`,
  `--sl-preview-max-width` and `--sl-preview-max-height` of `aurora` and `--sl-thumb-height`,
  `--sl-search-sort-width`, `--sl-search-order-width` and `--sl-search-filter-width` of `admin`; a theme of its own
  that overrides them is no longer read.

## 2026-11-09

### The site theme is aurora

- **The theme `lite` is `aurora`** — it outgrew the name of the light theme of 6.2 and takes the dawn of the release
  Phoenix: the folder `public/templates/aurora`, the setting `theme` of `config/global.php`, the themes an update
  replaces whole, the warning of «Тема оформления» in six languages, the help, the tests, the tools and the demo. Its own
  fragment `lite-head-banner` is `aurora-head-banner`. The look and the markup are unchanged.
- **Breaking:** a path under `templates/lite/` in a theme or module of its own has to follow; a member whose saved
  theme is `lite` sees the theme of the site, as with any theme that is not there.

## 2026-11-08

### A tab of the panel is one word, and the note of «Обновления» is one paragraph

- **A tab names its job in one word** — the section already names the subject: «Добавить», «Создать» and «Код» of
  «Блоки и баннеры», «Новые» and «Сброс» of «Пользователи», «Запрос», «Доступ», «OAuth», «Входы», «Локализация»,
  «Замены», «Восстановление», «Модерация», «Отказы», «Оценки», «Система», «Тема», «Шаблоны», «Импорт», and «Доступ»
  and «Форум» in the form of a category, «Отправка» and «Ресурсы» in the form of a Node type, «Файл» and «Ссылки» in
  the settings of «Конструктор сайта». The first tab of «RSS-каналы» and «Конструктор сайта» is «Главная», as
  elsewhere. «Новый тип», «Экспорт TXT», «Экспорт Markdown» and «Топ-запросы» keep two parts, one word would lose them.
- **The note of the settings of «Обновления»** says what a check sends in one paragraph, like every other note; the
  channels read «Stable» and «Beta», the extension «Sodium».
- **Breaking:** the language constants `_ADDCATEGORY`, `_ADDSUBCATEGORY`, `_ACESS`, `_ACESSF`, `_BANNED_USERS`,
  `_SEC_PASS`, `_UPLOADS_SYSTEM`, `_LAYOUT_FILETAB`, `_LAYOUT_WHERE`, `_GEOLOCATION` and `_UPDATE_S_ID`,
  `_UPDATE_S_VER`, `_UPDATE_S_ENV`, `_UPDATE_S_ERR`, `_UPDATE_S_HOST`, `_UPDATE_S_FAIL` are gone; `_MULTILINGUAL` is
  `_LOCALIZATION`, `_LAYOUT_THEME` is `_THEME_TAB`; `_COMMENTS_MOD` and `_CONFIG_RECOVER` are new. A module of its own
  that reads a removed constant fails.

### Tables and section heads are one series on the site and in the panel

- **The head of a section** carries one or two filter fields beside the title once its column is 960px wide, and any
  filter of three fields or more in a bar under the tabs, its fields in aligned columns of a label and a control.
- **An action of a whole section** is a tab that posts its token: the synchronisation of «Форум» is the first.
- **A column title is one word** in every list of the site and the panel; the cut forms («Ик.», «Сод.», «Сообщ.») are
  gone. Status and error columns show an icon in the head and one of four tones in the cell — the green check, the
  clock, the grey dashed circle of off, the red cross — with its word in the tip, and sort by the tone; the options
  column shows a gear. A centred title stands over the middle of its values and clear of its sort mark, a title too
  long for its column ends in an ellipsis with the whole title in its tip.
- **A record names its module honestly:** a Node type is «Материалы - Новости», another module its name alone, never
  its key (`getModuleTitle()`).
- **A row ends in one order:** error, status, options; a list without options ends in its status, and a cell of an
  error or a status is never empty — without a value it shows the grey circle. The result of a run of «Планировщик» and
  the rights of «Администраторы» show their icon in the head too, and the shield of a super administrator is the accent
  colour, not the red of an error. A relative date of the panel begins with a capital letter («Через 5 часов»).
- **A special group shows at the nick** in the list of «Пользователи» as a chip, and the filter picks the group from a
  list.
- **The support queue keeps the filter of the list:** the type and the status of publication stand beside the title as
  in «Материалы», the request («Ожидает», «Отвечено», «Закрыто») follows them, and a request out of publication is found
  again; priority and assignment are sorted in their columns, the query no longer reads `aid` and `prio`. Three choices
  without a box to type in stand beside the title like two. Breaking: `NodeSupport::getNodeSupportCount()` and
  `getNodeSupportList()` drop `$aid` and `$prio` and take the status of publication after the state.
- **A failed check of «Обновления» says why in words:** the reason code (`master`, `sign` and the rest) becomes a
  sentence of what happened and what to do, the code shows only for a reason the panel does not know.
- **No section repeats its tab:** the titles «Пределы» and «Импорт» of «Материалы» and «Конфигурации» of «Поиск» are
  gone; `_NODE_LIMITS` is removed.
- **Breaking:** `partials/module-head.html` draws the filter after the tabs, not inside the title; a theme of its own
  that overrides the partial keeps the old place. The language constants `_ADIR`, `_VIEW` and `_POINTS_SCOPE` are gone,
  and the values of `_SWORD`, `_SEARCHFIELDS`, `_FIELDS_KEY`, `_POINTS_LIMIT`, `_POINTS_PERIOD` and `_TVIEWS` are one
  word. The tokens `--sl-col-check-width`, `--sl-col-actions-width`, `--sl-col-date-width`, `--sl-col-count-width` and
  `--sl-col-status-width` of `admin` hold new values; `.sl-status-inactive` is the grey dashed circle, not the red
  cross, which is `.sl-status-fail` now. The user list filters a special group by `grp=<id>`; the old address
  `search=6&chng=<id>` shows the whole list.

### No loader of three dots is left in either theme

- **A list of the panel** that loads dims its rows and says `aria-busy`, with no mark over the table; the tabs,
  filters and pages of a canon list wait the same way.
- **A file** of the file manager under an action is covered by its dimmed `.sl-fm-busy` alone.
- **A button of the site** whose htmx request is on its way keeps its label and takes no repeat click.
- **Breaking:** the class `.sl-loading`, the keyframes `sl-loading-dots` and the tokens `--sl-loading-dur`,
  `--sl-loading-width`, `--sl-loading-height`, `--sl-loading-dot-width`, `--sl-loading-dot-height` and
  `--sl-loading-dot-gap` of `admin` and `lite` are gone; a theme of its own that overrides them is no longer read.

## 2026-11-07

### Rows per page and page numbers come from one place: the general settings, with an own number of a module where it needs one

`config/global.php` takes the section `pager`: `rows` 15 and `span` 8 for the lists of the site, `arows` 50 and `aspan`
10 for the panel, four fields of «Конфигурации». Every list of the panel takes `arows` through `getListLimit()` and the
rows chip, and `aspan` through `getPageSpan()`; the keys `anum` and `anump` of comments, privat, search, voting, users
and favorites, the `base` and `maxpg` of `getTplAdminList()` and the numbers written into groups, logs, ratings,
master, newsletter and the Node lists leave. A list of the site takes `getPageRows()`: the own `rows` of its module,
else the general one; comments, privat, search, voting, forum and changelog keep the two fields `rows` and `span` in
their settings, empty for the general number (`getTplPagerFields()`, `getPagerPost()`), and ship empty but the five
numbers of the private messages and the 50 topics of the forum (`listnum`). `num`, `nump`, `snum`, `snump`, `pnum` and
`perpage` leave; `favorites.num` and `favorites.nump`, read by nothing, leave with them. The members list, the logins
and «Мои материалы» of the cabinet take the general numbers in place of 50 and 20. The own number of a visitor
(«Количество публикаций на страницу», 3–20) leaves with its switch `users.news`, the column `storynum` of `_users` and
its field of the login cookie, which now holds id, name, token, block and theme: a list of the site takes only the number
of its module or the general one, and a Node type keeps its own `list.limit`. The pager of the language editor shows the general window in place of every
page.

### Two more Node profiles ship: events and vacancies, and an ad leaves the site after thirty days

`modules/node/profiles/` ships sixteen profiles, and a clean installation creates and activates every one. `events`
takes the extension `event` and its display mode: a cover and a gallery of up to 20 images, the start, the end and the
place of each event, the upcoming list with the archive beside it, categories, comments, favorites, the home page,
pinning and moderated submissions. `jobs` carries salary, city, employment (a select of full-time, part-time and remote),
e-mail and address, a cover for the logo and the switches of `board`. `board` and `jobs` take `workflow.lifetime` 30: an
ad or a vacancy an author publishes leaves the list after thirty days and comes back with «Продлить» in «Мои
материалы». The titles and the labels take seven constants in the six languages of `lang/`; `NodeProfileTest` and the
installation probe check the sixteen through a real installation, an event written through the panel included.

### A Node type can let an author's publication leave the site after a set time, and the author renews it

A type takes the key `workflow.lifetime`, days from 0 to 3650, with the field «Срок показа, дней» in the form of the
type; `0` keeps the old behaviour. With a lifetime, `NodeService` sets the end of a material when an author who is no
moderator publishes it directly or when a moderator approves it from «На проверке», each only while no end is set; a
moderator's own material keeps the end the moderator gives, and an edit of a type without scheduling leaves the stored
end alone. The cabinet gets the screen «Мои материалы» (`op=materials`) while an active type has a lifetime: the
author's materials of such types, shown and expired, with their end and the button «Продлить» (`op=renew`, POST with
the token), which sets the end one lifetime after the press through `NodeService::updateNodeTerm()`. A profile file
without the key imports with `0` and stays format version 1. `NodeServiceTest` checks the publication, the approval,
the moderator's own material and the renewal, another author's material and a repeated press included.

### A Node type can be a listing of events

The extension `event` (`NodeEvent`) gives each material a start, an optional end and a place, stored in the new table
`_node_event`; the panel form and the public form show the three fields. The list of such a type shows the upcoming
events, the nearest first, an event staying upcoming until its end, or without one until the end of the day it starts;
the switch «Архив» in the header replaces the sort and the direction and shows the past ones, the newest first. For
this the interface `NodeExtension` gains `getNodeOrder()`, and `getNodeScope()` and `getNodeOrder()` receive the list
arguments set by `NodeQuery::setNodeArgs()`; `support` and `sync` keep the order of the type. The display mode `event`
of the lite theme draws the start as a calendar leaf and belongs to this extension alone. `NodeEventTest` checks the
extension, the writes and reads through `NodeService` and `NodeQuery`, the routes and the statement budget of the list.

### The Node types stand in the panel as a group of their own

The modules news, faq, files and the others are Node types since 8.0, and the home of the panel showed only the card
"Материалы": a newcomer did not find "Новости". `getAdminSectionList()` now lists every type an administrator
moderates after the site modules, keyed by its name and opening `name=node&type=<name>`, so the home, the menu, the
pins, Ctrl+K and "Все разделы" of the site menu show them at once. They stand apart from the modules, a type being no
module: the home has the panel "Типы материалов сайта, созданные в модуле «Материалы»" after the site modules, the menu
the block "Типы материалов", "Все разделы" the third group. A card carries the key of the type, its queue (materials
waiting, for support the requests waiting) and the dial of the type: edit, categories, site, help, pin; a type is
turned on and off in the types of Node only. `getModuleLabel()` answers the title of a type, so a pin no longer shows
the raw key, and the order of the menu takes as many keys as there are modules and types.

### Four new Node profiles ship: a gallery, a portfolio, classifieds and reviews

An audit of the shipped types against the modules sites use most (uCoz, WordPress, Drupal, sites of small firms) found
four that a profile alone can make. `modules/node/profiles/` ships fourteen profiles, and a clean installation
creates and activates every one: `album` (a cover and a gallery of up to 100 images, tiles), `portfolio` (client,
year, address and services of a project, no public form), `board` (price, city, phone and e-mail of an ad, moderated)
and `reviews` (a required score of 1 to 5, a guest may post, moderated). The score is the field `stars`, since `score`
is a column of `_nodes`. The titles and the labels of the fields take nine constants in the six languages of `lang/`;
`NodeProfileTest` and the installation probe check the fourteen through a real installation. Events, vacancies and a
lifetime of a classified ad need the core and wait in their own plan.

### The load profile of Node runs again

`php tools/node-profile.php` stopped at `Invalid node input: name`: it copies the committed `config/`, whose `node.php`
names the types of the stand, and the profiles met their names taken. It now drops those types with their areas first,
as `deleteSetupTypes()` of `setup.php` does. Two of its limits had fallen behind the code: a delete costs six Node
statements (the head and the type are two reads), and a row of a list page passes six reads since the two paths of
`NodeQuery::getPathSql()` joined the page; the budget of `delete` and the bound of a page take them, here and in
`docs/NODE.md`. The full run of 100000 materials in fourteen types passes without a failure.

## 2026-11-06

### Every form of the panel and the site is used once in a real browser

Before 8.0 every form, setting and function of the panel and the site was used once through the real HTTP flow on a
copy of the production site: the 31 sections of the panel, the 13 modules of the site, the requests `go=1`, `go=3`,
`go=4` and `go=5`, the blocks, by a guest, a user, a delegated administrator with and without the right and the super
administrator, at 1366 px and at 390 px for the site. Every valid save was read back from the database or the config,
every wrong input had to be refused writing nothing, and one POST without the token went against every handler that
changes state. An irreversible action (the steps of `update`, `optimize`, `repair`, `dump` and `delete` of `database`,
a release of a newsletter, the publication of the builder) was opened up to its confirmation only. What the run
found and fixed:

- **The panel shell.** The name and the address of the administrator form, an unknown editor of `op=option` refused,
  the admin block of a delegated administrator refreshed.
- **The site.** A saved profile says so, its private rows and admin menu open to the right `account` alone, an empty
  feed address of the cabinet is refused. A comment a moderator sends through the site stays open, so a support
  request of a moderator is no longer refused.
- **The forum.** The page titles are built once by `getHeadTitle()`, a new topic no longer counts as a post of its
  section, the final delete of a topic takes back the points of its replies too.
- **The content of the panel.** An empty category title and an empty comment body of a moderator are refused; the
  `http://` of a stored site no longer holds the form of a user in the panel and the cabinet; a type with `support` is
  not offered by "Добавить" and its `op=add` answers 404; a bad filter or limit of Node is answered with
  `_NODE_BADFILTER` or `_NODE_LIMITBAD`; the edit of a gone category returns to the list instead of 500; a ban without a
  term lasts 30 days instead of ending at once.
- **The site in the panel.** An explicit `tab=0` wins over the tab the session remembered; a value emptied in the
  language editor is named instead of a false success, and a save of the French files no longer writes `\$` into 13
  placeholders `%1$s`; an HTML block or a block file without content is refused; an import of the builder no longer
  shows a phantom change of the page settings.
- **Files, jobs and mail.** "Файловый менеджер" opens at the folder of its settings again, a bad name is refused with
  `_UPLOADS_BADNAME`; the form of a job of "Планировщик" names every refusal and keeps what was typed, "Добавить" no
  longer overwrites a job of the same key, a relative address of a job is stored as typed instead of `http:///…`, a
  removed job takes its state file along; the text of a mailing starts empty and is framed by the mail template through
  `getMailFrame()`; a compiled template the job `cachegc` removed during a request is compiled again under its lock.
- **The system.** A clean or delete of `database` on a table that is not there answers `_ERROR` and writes no SQL error;
  an empty query of "Запрос" and an empty XSL of `sitemap` are refused; "Очистка" of `search` asks its confirmation; the
  section of a module whose own tables are not installed shows `_DB_NOTABLES` instead of reading a missing table.
- **Shipped files.** The language files, `config/lang.php`, `config/forum.php`, `config/replace.php` and
  `config/points.php` ship in the shape the panel writes, so an unchanged save leaves them as they are
  (`ConfigFileTest`).

### One thing is done one way

The run listed 28 places where two mechanisms did one thing; each now has one standard.

- **A config write.** No caller of `setConfigFile()` drops its result: a refused save of the panel says
  `_CONFIG_PENDING` or `_ERROR_UP`, and the writers of a part write the changed keys on the file as it stands.
- **The answers of the panel.** A missing token is the flash `_TOKENMISS` back to the form or the list, a record that is
  not there the flash `_NONE_FOUND` back to its list (the editor window of the builder keeps it inside), the refusals of
  a form one list with what was typed kept, a count after a POST one flash by `sprintf()`.
- **The site.** A closed profile opens to `is_moder('account')` wherever it is read; `getRssView()` refuses an empty
  feed itself; an empty comment is refused by the server alone; `checkSitePost()` of `core/security.php` guards every
  write of `forum`, `contact`, `recommend`, `account` and `node` (`checkForumPost()` is gone); the captcha of
  `recommend` follows the switch of `contact`.
- **People and safety.** `Oauth::deleteLink()` is the one unlink, the panel removing the last login method by `force`;
  `getBanList()` adds a ban of an address, by the panel and by the guard alike; `addFormMail()` of `core/admin.php`
  writes the ban mail and the login mail of `admins` and `account`, the latter only with a new password;
  `checkPassRule()` applies `minpass` to the registration, the password change of the cabinet, the forms of `admins`
  and `account` and the first administrator of `admin.php`; every credential field is of the type `password` or
  `email`, where the login and the registration of the site showed the password; the date filters of the journals
  read `getVar(..., 'date', '')`.
- **The blocks.** `setBlockRow()` of `core/admin.php` writes a row of `_blocks` for "Блоки" and for the publication of
  the builder, on the one spec `getBlockSpec()`; a system block is neither offered for deletion nor deleted; the columns
  of a page have one owner, the module or the Node type for its page, the builder for a page without a module, which on
  the page of a module only shows them.
- **The site in the panel.** `filterSiteBrand()` checks the site name and logo for the settings and the builder, the
  name kept as typed; the language files are written through `setConfigSource()` with a failure per locale; a `|` in a
  rule of "Замена слов" is refused, and `Parser::replaceText()` splits a rule as the form does.
- **The system and the modules.** `updateNodeTypeParts()` of `core/admin.php` is the one answer of a section that owns a
  part of every Node type; `clear()` of `search` deletes nothing for 0 days, no module or an unknown mode; a poll is
  deleted from the list only; every irreversible action asks its confirmation; the parts of the super administrator are
  hidden from a delegated one and refused by a flash.

### The look is one system

- **Sizes.** A one-line field of the panel is `--sl-size-control` high wherever it stands; the chips of the private
  messages take `--sl-size-chip` and their bulk bar the sizes of the other forms of the site.
- **Small classes.** The copy button of a code head is `.sl-code-copy` of both themes and no longer borrows
  `.sl-modal-act` of a window; "Сейчас" and "Вчера" of "Статистика" are a plain `sl-seg` like "24ч", "7д" and "30д".
- **A file of an ordinary form.** "Загрузить архив" of `update` (a pane of its own) and the import of Node take the
  canon `fragments/file-field.html` in its form mode through `getFormFileField()`: a button opens the picker, the
  chip names the file and its weight, the cross empties it.
- **Lists.** The list of `changelog` in the panel is a table of `getTplAdminList()`, one row per commit, the body, the
  e-mail and the files in the "i" of the row; "Группировать по дате" acts on the site alone. The list of forums takes
  the canon with the view switch and the key tip. A block of rows of a form (`partials/toggle-form-block.html`) spans
  its box with the name in a cap.
- **The menu of the site on a phone.** Below 900 px the menu of `lite` is a drawer behind the button `sl-menu-btn` of
  the head, with a scrim, Esc and the focus back on the button; above 900 px the bar is unchanged. `setDrawer()` moved
  from `admin-ui.js` into `plugins/system/slaed.js`, one drawer for both themes.
- **Breaking:** the tokens `--sl-pmf-filter-height` of `lite` and `--sl-changelog-width`, `--sl-changelog-body-border`,
  `--sl-changelog-files-height` and `--sl-changelog-stat-width` of `admin`, the class `.sl-cockpit-set`,
  `partials/changelog.html`, `partials/changelog-body.html` and the three `changelog-*` fragments of `admin` are gone; a
  theme of its own that overrides them is no longer read there.

### The forum of no category answers 404

- **The form.** `add()` of the forum answers 404 for no category, a missing one, a missing post and a post whose
  category is gone, instead of 500.
- **The writes.** `send()`, `move()` and `delete()` take a category that is not there, or one of another module, as none
  and go back to the forum writing nothing, where a guest got the form of a new topic in a category of `news` and its
  post ended in an SQL error (`FileAccessTest`).

### Every button carries its icon and bursts when pressed, and a material takes its categories from one tree

- **The icon.** `fragments/button.html` of the panel is always a `<button>`, never an `<input>`, as on the site, so a
  glyph of the icon font stands before its words: the icon its caller names for an action that means more than to
  confirm, else the arrow of a way back, the turn of a reset or the check (`docs/TEMPLATES.md`).
- **The burst.** `setPressBurst()` of `plugins/system/slaed.js` gives every `.sl-but*` and `.sl-but-mini` the burst of
  the favourite star on a press, drawn by the `--sl-fav-*` tokens, which the panel now has too.
- **Two submits.** The forms of the forum and of a material send "Предпросмотр" and "Отправить" as two named buttons of
  `fragments/form-submit.html` instead of a select of the action beside one button.
- **One tree of categories.** The main and the additional categories of a material are one tree of checkboxes, on the
  site and in the panel alike (`getNodeFormRows()`): the starred row is the main one, `setCategoryMain()` keeps the star
  on a checked row, and the server takes the first checked row when none is starred.
- **Breaking:** the main category still posts as `cid`, now from the star of the tree instead of a select; the poll
  edit of the panel no longer posts `posttype` and deletes nothing, as a poll is deleted from its list.

## 2026-11-05

### The file screens explain their keys, their properties fold away, and the panel has one journal

- **The keys.** The "i" of the lists also ends the bar of "Файловый менеджер" and of the file window of the editor:
  `getFileKeysView()` lists the arrows, Home and End, Space or Ctrl with a click, Shift for a range, Enter (open in the
  manager, insert in the window), the right button and, in the window, Esc, in six locales. A list of the panel shows
  the tip once after its view switch; a touch screen hides it in both themes.
- **The properties.** A button of the bar of both file screens («Свойства» in the manager, «Свойства файла» in the
  window) opens and folds the properties column; it starts closed, the browser remembers the choice, and a closed
  column gives its width to the list. A row of the manager keeps one line, ends a long name in an ellipsis and shows
  its date to the minute with the full time in its title.
- **One journal.** A section of the panel no longer ends its tabs with «Журнал действий»: the actions of one section
  are read in "Журналы" by the filter «Модуль».
- **The Russian texts.** The Russian language files of the core and the modules read clean: commas, dashes, hyphenated
  compounds, spelling and the full stop at the end of a sentence.

### The monitor measures the disk of the project

- **The disk.** "Мониторинг" measures the disk that holds `BASE_DIR` instead of the working directory, and its scale
  reads «Занято и в резерве» (`_ADM_DISKUSED`), as the free space leaves out the blocks the system keeps for root.

## 2026-11-04

### The code is SLAED CMS 8.0.0

- **One number.** `SL_VERSION` of `core/version.php` is `8.0.0`, and the marks of the data update in
  `config/update.php` (`fields`, `points`, `ratings`, `modules`) are `8.0.0` too: `setup.php` and `update.php` write them,
  the core opens extra fields, points and ratings only behind them. The texts of the product name 8.0; the early 6.3
  builds and `table_update6_3.sql` keep their names as history.
- **A site moves by itself.** A site whose code is newer than its last step answers 503 until the light mode of the panel
  applies "Применить шаги базы"; a site of the development tree also needs its data marks at `8.0.0`.

### The admin menu counts its queues at once, and the lamp of a list is a mark

- **The queues.** `getAdminQueues()` counts the waiting materials of every Node type but `support` in one statement,
  `NodeQuery::getNodeCountList()`, and the queue of a support type by `NodeSupport::getNodeSupportCount()` without
  reading a page; `getSessionCounts()` is read once per request.
- **The lamp.** The status of a row of a canon list (`getTplAdminList()`) is a mark that only shows the state; its
  switch is an item of the dial of the row, and a press turns the mark, its words and the title of the item at once.

### A figure runs one way, and a light runs round the frame under the hand

- **The figure.** `setFigureRun()` of `plugins/system/slaed.js` is the one run of a figure in both themes: it climbs to
  its new value for `--sl-figure-dur`, swollen and in `--sl-figure-text`, and ends on the text the server wrote. The
  live figures of the panel, the results of a poll and the presentation take it; a still page or a hidden tab shows the
  text at once.
- **The light.** Under the hand a turning cone runs round the frame of every framed block of both themes, drawn by
  `--sl-aurora-width`, `--sl-aurora-mix`, `--sl-aurora-border`, `--sl-aurora-ring` and `--sl-aurora-dur`, which at
  `0s` turns it off.
- **Less motion.** The panel drops its entry animations (the tiles of the home, the parts of a ground, the charts, the
  rows of a list) and the four-dot loader of the live chart.
- **Breaking:** the tokens `--sl-drift-dur`, `--sl-drift-width`, `--sl-drift-height`, `--sl-drift-dot-width`,
  `--sl-drift-dot-height`, `--sl-drift-gap` and `--sl-tile-dur` of `admin` are gone, and `--sl-aurora-width` of `admin`
  is the line of the light instead of a band; a theme of its own that overrides them is no longer read there.

### The presentation reads as one series, and the footer forum lines up its dates

- **One series.** The presentation of `lite` takes one chip shape (a one-line chip is a pill), one head per inner block
  and two frames at most; its texts in six locales are in sentence case with case and grammar corrected, the map of the
  modules names Node instead of Master, the request path is seven equal stations on a ring.
- **The gallery.** The thumbs of the gallery of sites are WebP of 640 px at 82, as `getImageThumb()` takes the format
  and the quality, instead of small JPEG and GIF.
- **The footer forum.** The rows of the forum block of the footer share two columns, so every date starts on one line
  after the longest author; in a block narrower than 280 px the date stands under the author.

### The shipped layout is the layout of slaed.net

- **The layout.** `config/layout.php` ships as slaed.net runs it: the home and the page 404, the menu `main` of five
  items and `foot` of seven, and the parts of `lite` filled (the tagline, the version pane with its download button,
  seven social links, the contact line, six marks of the technologies, two tabs of the footer). Its `shipped` is false,
  so the builder labels its entry of a new page as for a site's own layout.
- **An existing site.** An update puts a settings file of `config/` only when it is absent, so a site keeps its own
  layout; only a new installation receives this one.

## 2026-11-03

### No guard pages in the document root

- **One standard.** The 23 `index.html` of `templates/` and `plugins/` that only sent a visitor to slaed.net are gone:
  the server closes the listing, `Options -Indexes` on Apache and `autoindex off` on nginx, as outside `public/`.
  `PublicTreeTest` now refuses a guard page inside `public/` too; a real page, as the two of `phoenix/`, stays.

### The marks of the footer are the theme's own logos and lead somewhere

- **Six marks of the theme.** Lite carries the logos of its stack in `images/tech/`: PHP, MySQL / MariaDB, HTML5, CSS3,
  JavaScript and htmx, each an SVG in mono and in its brand colour. A mark of the builder picks one by "Знак темы";
  the colour lies over the mono picture and shows on hover and keyboard focus. An uploaded picture still serves a mark
  without one.
- **A mark is a link.** The field "Адрес" turns a mark into a link with its caption as the text of the picture; a mark
  without it stays a picture. The pictures load lazily, as the footer is below the fold.
- **A mark without a caption is named.** A picture field of a description may name the field that describes it (`alt`),
  so the check before a publication now names a mark of the footer whose caption is empty, once per mark, the theme's
  logo and the uploaded picture alike.
- **Out of the uploads.** The six PNG of `uploads/presentation/brand/tech/`, jQuery and jQuery UI among them, are gone:
  the pictures of a theme belong to the theme, and an SVG below `uploads/` is never shown, as the stream of the uploads
  sends it as `application/octet-stream`.

### The lamp of the home names every error journal and opens it

- **Sixteen checks.** "Ошибки PHP" counts `error_php.log` alone; "Ошибки сайта" counts `error_site.log` and "Ошибки
  файлов" `error_file.log`, each warning from one entry a day and failing from a hundred. Each of the four error checks,
  the database among them, opens its own journal in "Журналы" instead of the monitor or the database tool.
- **Misses are no failures.** An answer of the site under 500 (404, 403) no longer counts as an error, on the lamp
  and in the count of the monitor; the bots that probe for files kept the lamp red on a site that had no fault.
- **The connection.** A refused database connection is written to `error_sql.log`, where the database errors are,
  and counts in the SQL errors the update check reports.

### The hero on top of a page loads its picture at once

- **Eager.** The picture of a hero with no section, no module output and no band above it, shown on the phone and the
  desk, carries `fetchpriority="high"` instead of `loading="lazy"`, as it is the largest paint of the page; every
  other hero stays lazy.

## 2026-11-02

### The admin block reads Markdown links and names this site

- **Markdown.** The own links of the block "Администрация" are read from the Markdown the editor writes,
  `[name](address "hint")`, as well as from an `<a>` tag; a list item per link, the hint optional.
- **This site.** `[homeurl]`, `[host]` and `[admin]` in an address or a hint become the address of the site, its host
  and its panel file, so a link to an outside check or to a section of the panel holds on any installation.
- **Without the floating menu.** While the menu is off in "Опции", the block stands on every page of the panel, not
  only on the home, so the own links and the watch stay at hand.

### Motion costs less, and the picture stays the same

The home of the panel and the presentation kept a core busy while nobody touched them, and a moving pointer dropped
them to 17–33 frames a second. The laws are `docs/TEMPLATES.md`, "The Cost Of A Frame"; the rig and every number are
`docs/PERFORMANCE.md`, "Frame Cost".

- **The rig.** `tools/frame-cost.mjs` with `npm run perf:frame` measures in interleaved pairs against a commit or a
  patch, names the animations the compositor refuses and compares the pixels of a part on a frozen clock.
- **The pointer.** The pointer light of both themes writes non-inherited coordinates, so a move restyles two elements
  instead of every one: 120 frames a second under a moving pointer on both pages.
- **Phoenix.** The scene draws only after a step and draws its still acts once, with every layer equal.
- **The presentation.** The pulse sleeps off screen and reads no layout per frame; a part off screen pauses its
  animations and its SVG. The gate of the guard moves by `transform` in whole device pixels, its parts as one, where
  the layout snapped each on its own; the guard screen falls from 85 to 60 % of a core.
- **The panel.** The LED and the ring of the cockpit pulse on `transform` and `opacity`.
- **Kept.** The scans, the dots, the packets, the dashes and the bars that move by an offset stay on it, since on
  `transform` a thin line spreads over two pixels; the alarm, the throb and the live icon of the cockpit stay as they
  were.

### The counter picture and the random banner are gone

- **Removed.** The blocks `blocks/stat.php` and `blocks/banner_random.php`, the counter picture `index.php?stat=1&img=N`
  of `core/user.php`, the folder `templates/lite/images/banners/` with its badges, and the leftover folders
  `templates/lite/images/account/` and `templates/admin/images/ranks/`. A clean install no longer seeds the blocks
  «Счетчик посещений» and «Реклама».
- **The update.** `update.php` switches off a block that runs `stat.php` or `banner_random.php`, as it does with the
  blocks of the removed modules.
- **Breaking:** an outside page that embeds the counter picture gets the start page instead of a PNG.

## 2026-11-01

### The panel is audited again, under axe and under a live screen reader

- **The audit.** Axe 4.14 (WCAG 2.2 AA and best-practice) over 249 screens of the panel, 517 addresses with the variants of a search term and of a pick in the builder, each at 1440 and 375 px in the light and the dark scheme, then 70 states the walk does not open: every dialog shown, the floats, a picked row, an empty result, the refusals, a form with an empty required field. NVDA 2026.3 read the home, "Комментарии" and "Конфигурации" by keys; the speech came from its log.
- **Landmarks.** The footer of the panel is a `footer`, the debug panel an `aside` named `_DEBUGPANEL`, the stage of the builder a `section`, so a page has one `main`. The login of the panel and the page of `setExit()` in the admin theme have their `main`, and the latter its `title`. The upper pager is named apart from the lower one, a code block is a `group`, as a help page holds many of them, and a section of the debug panel takes the Tab, as it scrolls.
- **Names.** The lamp of the home takes its name from what it shows, the text of the lamp of "Мониторинг" stands in its label word for word, and the zone of the file window drops the `aria-label` that hid its limits. `editor-tags.js` turns the `aria-role` of Toast UI into `role` and names its button of the folded toolbar; the editable body takes `role="textbox"`, which its name needs. The fields of the form "Пункты" of "Группы" are named by their rule and column. A label and a figure in a row of the bell no longer run together for a reader (`.sl-bell-num::before`).
- **Keys and state.** Space sorts by a head cell as Enter does (`setTableSortKey()`), the glyph of the sort left the name of the cell, and a strip of links marks its page with `aria-current`.
- **Targets.** A checkbox of the panel keeps 4 px on every side and an option under another 24 px of height, as WCAG 2.5.8 asks of a target under 24. The strip of the statistics archive stays narrower by the decision of 2026-10-29: the month list and ‹ › are its equal means.
- **Contrast.** `--sl-success-strong` and `--sl-danger-strong` of the admin theme go darker in the light scheme, so a chip holds 4.5:1 on the side block. The found words, the button of a warning, a link of a picked row, a row of the builder that is off, the captions of the file window in both themes, the mood of a lamp, the hours of the dial and a figure in the warning tone come up to the norm. The skin of Toast UI, one file in both themes, paints the Markdown source for both schemes, and a link inside text is underlined.
- **The file manager.** A long name ends in its ellipsis instead of pushing the table under the properties, a phone scrolls the table in its frame, and the toolbar wraps.
- **A dead section.** An address of the panel that names no section, or a section the administrator may not open, answers 404 instead of an empty page with 200 (`AdminLogTest`), and "Где ещё настраивается" of the builder leads to "Администраторы", where the editor of an administrator is chosen, not to the removed "Редактор".
- **The label crawl.** `tools/label-audit.mjs` opens the screens of the panel it passed by (`sysfiles`, `fmedit`, `tplconfig`, `passwd`, `logview`, `logins`, `queue`, `dead`, `types`, `type`, `import`, `support`, `votes`, `points`, `toplist`, `xsledit`, `theme`), which a GET only shows, and only in the panel, as the same words on the site are other handlers. A search term is no part of a route, so no query of a visitor reaches the tracked baseline, which also drops the removed routes.

### The journals are a section of their own, and the settings of two jobs stand on their forms

- **The section.** `admin/modules/logs.php`, `name=logs`, holds the list of the journals, `op=logview`, `op=logins`, `op=download` and `op=delete`, moved from "Безопасность" under the token and the journal of `logs`; its caption `_LOGS` replaces `_MONITOR_LOGS`. The first tab of "Безопасность" is the ban list with the verdict of the self-check, and the addresses `name=security&op=logview` and `name=security&op=logins` are gone. The merge of an update adds the section to a site, as it adds any shipped module.
- **The settings.** `log`, `error_log`, `write_h`, `write_w`, `log_a`, `log_u` and `log_size` are saved by `op=config` of "Журналы"; `log_b` and `dump_age` by the form of the job `dbbackup`; `log_d`, `dump_skip` and `mail_d` by the form of `filescan`, whose warnings in the list lead there. The keys stay in `config/security.php`.
- **Three writers, one file.** Every form passes `$conf['security']` as the third argument of `setConfigFile()` and writes its own fields; `configsave()` of "Безопасность" no longer loses `proxy` on a save (`SecurityFormTest`).
- **Gone.** `sess_d`, `sess_b`, their fields and `_SEC_LOG_DS`, `_SEC_LOG_DB`: no code read them, a job takes its time from `schedule`. `setUpdateConfig()` and `setUpdateMerge()` drop them from a site.

## 2026-10-31

### The request reading becomes a sensor, and the panel and the logins close

- **The reading.** `checkRequestData()` replaces `checkGet()`, `checkPost()`, `checkCookie()` and `checkFiles()`. Only `../`, `<script` and a stream wrapper in a GET value stop a request; every other match writes a `notice` of `hack.log` through `addWatchReport()` and the request goes on. A report names the field and never quotes the value; a pattern that breaks on a long value counts as found.
- **The files.** The check of `$_FILES` is gone: it read two field names, and `Upload` holds the closed list of types.
- **The ban and the mail.** A ban takes the address alone, drops expired rows and keeps at most 1000 automatic ones; a request another page sent bans nobody. `getReportSkip()` lets one security mail out per hour.
- **The info page.** A post that fails its token renders nothing, and only a super administrator edits a doc, as a doc runs `[usephp]`.
- **The trusted tags.** `filterTrustedTags()` runs last in `filterText()`, `filterHtml()` and `filterSourceText()`, as `stripslashes()` made `[usephp]` out of `[use\php]`.
- **The session.** The cookie is `HttpOnly` and `SameSite=Lax`, `Secure` on https, with strict mode on; every login regenerates the id.
- **The logins.** `Captcha::isLoginLimited()` closes an address after 5 misses on a name or 30 on all, and the installer turns the captcha on. The member cookie carries `getUserToken()` instead of the stored hash, so every member logs in once more after the update.
- **The passwords.** Login, registration and the profile read the password as typed, and `getPassMatch()` still opens a hash stored from an escaped form and rehashes it. A recovery code is keyed, 80 bits and ends within two hours, a generated password has at least 12 characters, and a sent mail of the kinds `account` and `admins` keeps no body.
- **The address.** `filterWebUrl()` empties any scheme but `http` and `https`.
- **The way out.** A logout is a POST with a token: a link carries `data-sl-post`, `slaed.js` submits it, and a GET of the same address logs nobody out.
- **The forum.** `checkForumPost()` takes the token of `move`, `send` and `delete` from the body of a POST alone.
- **The mail of a profile.** A member changes the address only with the current password, `mailpass`; an account of OAuth alone has none and is not asked. A miss counts toward the login limit, as a wrong current password does in `savepass()`.
- **The defaults.** `minpass` ships as 8; the forum search and the name search escape `%` and `_` under `ESCAPE '!'`.
- **The token.** No address carries a token any more: a page holds it in `<meta name="sl-token">`, `window.getPageToken()` and the `htmx:configRequest` hook of `slaed.js` send it by header, and neither `getRequestToken()` nor the gate of `public/index.php` reads the address. A scope takes its own token alone, so the ajax token of a poll opens no handler of the panel. The file window sends `upload`, the order of blocks and categories sends its own scope, and the gate leaves out `go=4` and those order handlers, as each checks for itself; a preview and a download go without a token, as a browser opens them by itself, and each answers for its rights.
- **The address of a visitor.** `getIp()` trusts `REMOTE_ADDR` alone unless it is a proxy of the new `security.proxy`, and then reads the rightmost address of `X-Forwarded-For` no listed proxy added, as the login limit and the ban hit everybody behind a proxy otherwise.
- **The settings that go.** `flood`, `flood_t` and `ref_post` leave the code, the form and the config: a new session evaded the first, and the second read an empty upload and a substring of the Referer.
- **The mail that failed.** A failed row of `account` or `admins` loses its body after the time a sent one is kept, and such a row can no longer be retried; the row stays for its error.

### The site messages are blocks

- **The section.** "Сообщения" is gone: `admin/modules/messages.php` and its help, the row of `config/modules.php`, `setMessageShow()` and its call in `setFoot()`, the setting `message` with its field in "Конфигурации" and `_MESSAGE_BOX`, `is_message_box` and `.sl-message-box`, and the table `_message`. The update drops the table without carrying its rows, takes the setting out of `config/global.php` and drops the record as a record without a module.
- **The block in its place.** A block of the zone `c` marked `home` stands where a message stood and goes through the same `filterContent()` call; its term, language and audience are those of a block. Unlike a message it obeys `top` of the home, whose shipped value `3` switches the zone off, and it carries no line for the moderator.
- **The field.** The content of a block is edited in `getTplTextarea()` with the store `blocks.content`, a `TEXT` of 64 KB without embeds, and `addsave()` and `editsave()` refuse what does not fit with a page that goes back to the form.

### The word replacement is a tab of the settings

- **The section.** "Замена слов" leaves the menu for a link in the strip of "Конфигурации", beside "Цензура": `op=replace` shows the rules, `op=replacesave` stores them. The address `name=replace`, `admin/modules/replace.php` and its help are gone, the record of the section drops on the update as a record without a module does, and the help is section 5 of the help of "Конфигурации".
- **Every type.** The types come from `getNodeTypeMap()` instead of `content` and `news`, in a second strip of links. Each type is a form of its own that writes its own key: a form of all types holds a hundred fields per type and can run into `max_input_vars` (1000 by the default of PHP, so from ten types on), which cuts off the rules of the last ones without a word.
- **The file.** A save drops the empty pairs instead of padding the value to fifty, so a type without rules holds an empty string and the parser returns at once. The key stays, because the merge of an update brings a missing key back with the shipped rules.
- **Around it.** The two lists of the blocks in the form of a module say that the columns of a builder page apply instead (`_BLOCKS_PAGEHINT`). The help of "Дополнительные поля" is written anew from the code. The dead `case 'mailtest'` of `admin/modules/config.php`, which ended in a fatal error since the mail moved, is gone.

### One file manager

- **The section.** "Загрузки" is "Файловый менеджер": the row `uploads` of `config/modules.php` takes `_EUPLOAD`, `setUpdateModules()` renames a stored `_UPLOADS` on the update, and the key `name=uploads` does not change. `_UPLOADS` stays for the monitor and the builder. The tab "Шаблоны" is "Шаблоны вложений" (`_UPLOADS_TPLS`).
- **"Редактор" is gone.** Its three files are direct links over the tree of `op=sysfiles` (`getAdminFileLinks()`, `_UPLOADS_QUICK`), and the button of the standard robots rules stands in the foot of that file's editor. `.htaccess` and `xsl` open in the source editor (`apache`, `xml`). `config/header.php` opens whole: the wrapper is no longer stripped and added back. `admin/modules/editor.php`, its help and the constants `_E*` of the screen left; `_EROBSTD` and `_EINFOPHP` stay.
- **"Шаблон" is gone.** The tab "Тема оформления" (`op=theme`) is the file browser over one theme, the context `theme` of `FileManager` with viewing and editing alone, in place of one editor per file on one page. `admin/modules/template.php`, its help, `_TEMPLATES` and `_STYLES` left. The engine is in `docs/TEMPLATES.md`, "Compile And Render" and "Editing A Theme From The Panel".
- **One writer.** `filecodesave()` of "Блоки" and `xslsave()` of "Карта сайта" stay on their screens and write through `FileManager::setFileBody()`, a new file first through `addFileEntry()`: both now refuse a stale save with 409 and keep the typed text.
- **The stub.** `config/system.php`, which nothing included, is deleted with its place in the loader, `setConfigFile()` and `Update::isSiteFile()`. `setUpdateModules()` moves the file of an old site to `config/system.php.old`, because the loader would now require it. The records of the two sections drop on the update, as a record without a module does.

### Mail is one section

- **The section.** "Рассылка" is "Почта": `_NEWSLETTER` carries the new word in the six locales, so a site gets it with its language
  files and its `config/modules.php` stays as it is. The key `name=newsletter` does not change. `_MAILOPT` is gone, and `_NEWSLETTER_JOB` keeps
  the word of the campaigns on the flag of their job in "Мониторинг".
- **The transport.** The mail tab left "Конфигурации" for the tab "Транспорт" of the section: `op=mailconf` shows it with its settings
  find, `op=mailsave` stores it, `op=mailtest` queues the test letter under the token of the section. `tab=5` of `name=config` is no more.
- **Two files.** `mtemp` stays in `config/global.php`, where its readers take it. The form of "Конфигурации" writes the whole file, so it
  keeps the key it no longer shows; `op=mailsave` writes that key alone. `MailConfigTest` holds both.
- **A draft and a launch.** "Сохранить" of `op=add` stores a draft and queues nothing. `op=launch` hands it to the producer with the audience
  counted anew: from "Запустить" of the form, which saves first, and from the fan of the row, both under a question. A finished or stopped
  campaign with an empty queue is edited in place, which returns it to a draft with its counters at zero, and is launched again from its fan
  ("Перезапустить"); one in work is only copied (`&copy=1`). `_NLUSER` is gone: the column of the sent shows the number alone.
- **The unsubscribe is a switch.** `unsub` of `config/newsletter.php`, "Добавлять ссылку отписки" of the tab "Конфигурации", shipped on. Off, a
  mailing leaves without the link and without the list headers.
- **The rate window.** The cap of messages per minute lives in `storage/logs/scheduler/mailrate.json`. It stood in the state of `maildrain`,
  where the end of every scheduler run dropped it, so each run opened a window of its own.
- **A late refusal counts.** A delivery report believed for an address turns its last accepted mailing letter into a refused row (`dsn` and the
  status code) and moves it from the sent to the failed of its campaign, once. The registry of "Отказы" names the reason in words by the code
  (`getDeadReason()`), and the mark before the address opens the reason, the code, the date, the count and the text of the server, kept in
  the new column `note` of `_maildead` (`table.sql`, and `addcol` in the tool for 6.2 data).
- **Who leaves a mailing.** A code that names the mailbox itself - `5.1.1`, `5.1.2`, `5.1.10`, `5.2.1` - excludes the address at once; every
  other refusal still counts up to `bouncemax`. The breaker of a campaign reads the results since its launch only, so a campaign launched
  again is not judged by its earlier run. `MailingPanelTest` holds the form and the launch over real requests.
- **Around it.** A status that is only a mark is no item of the dial of its row, in every list of `getTplAdminList()`. A submit button that
  asks first travels as the submitter of its form, so its name and value reach the handler. The bar of a meter stands `--sl-space-2` under its
  figure.

### The detector leaves a word inside a file name, a zero limit stays zero, and the presentation counts who is online

- **Detector.** `checkGet()` and `checkCookie()` take `ALTER`, `DROP`, `INSERT`, `OUTFILE`, `SELECT`, `TRUNCATE` and `UNION` only
  where no letter touches them, so a file name such as `Eyedropper-….gif` in `key=` no longer writes to `hack.log`, queues a mail or,
  with `$conf['security']['block']`, bans the visitor. The bound is letters and not `\b`: `/*!50000UNION*/` has a digit before the word.
  A word with a digit, a separator or nothing beside it still counts, `drop_down.gif` too, and so does the name of a table with the prefix.
- **Upload limits.** `configsave()` of "Загрузки" keeps a 0 in the three limits of files, as the hint of the form and
  `getEditorFileJson()` read it: no limit. A save without an edit no longer turns the `userfiles` and `guestfiles` 0 of `layout` into 100.
- **Presentation.** "Онлайн" shows `getSessionCounts()` alone; the floor `48 + visits % 37` is gone. The cache chart stays staged.
- **Muted zeros.** "База данных" writes a zero of "Расходы" with `sl-muted` and an InnoDB value that is not zero in the plain tone;
  `sl-dimmed` of the panel is the menu row that is off and nothing else, and `is_dimmed` leaves the admin `inline-badge.html`.
- **Warning tone.** The light `--sl-warning-strong` of the admin theme goes from `#c2410c` to `#b23b0b`: `sl-chip-warn` on its own tint
  over the sidebar reads 4.53:1, where it read 3.98:1.
- **Names of the site.** The fields of search, registration, the profile, the feed box of the cabinet, private messages, the form of a
  material and the forum carry a name of their own, and `control-unnamed` of `tools/label-audit.mjs` counts an image with its alt
  in a label as the text of that label.
- **Self-submitting list of search.** The list of search applies by its button, so an empty required word is held by the form;
  `PanelPartsTest` now holds the site modules too, but for RSS, whose section rebuilds the categories of the form on the server.
- **CodeMirror.** `getAreaAttr()` gives the editable area `tabindex="0"`: it was a Tab stop already, and axe now counts its scroller as reachable.
- **Tests.** `UpdateConfigTest` holds that the version of the old site is not carried, in place of a comparison of `null` with `null`;
  `InputFilterTest` holds the bounds of the detector and `UploadIntegrationTest` the zero of the save.

### Every field of the panel has a name, and a strip of tabs is a list of tabs

- **Self-submitting lists.** The filters of the forms of "Модули" and "Категории" and of the statistics of "Мастер" move onto
  `fragments/list-filter.html` with its button and `is_now`, so a change still opens the list and a page without the script works;
  `PanelPartsTest` holds that no screen writes `OnChange="submit()"` again. The filter of the list of "Модули" takes `is_now` too.
- **Tabs.** `initTabGroup()` in `slaed.js` finds the strip through its first tab, as on a module head the strip is the group
  itself and `role="tablist"` never landed there. Its `li` takes `role="presentation"`, a link of the strip that opens a page
  `role="tab"`, a tab its `aria-controls`. Arrows move the focus with the choice, so the second press goes on, and Home and End work.
- **Names.** Every field of the panel is reached by its own label: `label_for` and an id in the form rows,
  `aria_label` of `fragments/select.html` and `fragments/input.html` where no label stands, `label` of `getTplAddDateTime()`, and the
  hints of the site builder are tied to their fields. `tools/label-audit.mjs` gains the kind `control-unnamed`, which takes neither a
  `title` nor a placeholder for a name; its baseline is stored anew on the stand after the production move, 492 routes, with no
  entry of the panel and the fields of the site still listed.
- **Two lists under one label.** `_BLOCKS_MOD` goes: the side and the centre blocks of "Модули", of a Node type and of the
  site builder read `_BLOCKS_SIDE` and `_BLOCKS_TOP`, which replace `_LAYOUT_SIDE` and `_LAYOUT_TOP`.

### The secrets of the panel are for the super administrator alone

- **OAuth keys.** The switches and the keys of Google and Microsoft move from the settings of "Пользователи", a section that
  can be delegated, to the tab `name=security&op=oauthkeys`; `op=save` of the account module no longer writes
  `config/oauth.php`. The list of links `op=oauthlist` stays in "Пользователи".
- **GitHub access.** The fields `source`, `ghowner`, `ghrepo` and `ghtoken` of "Журнал изменений" are drawn and written for
  `isAdmin(true)` alone, as the site calls the named repository with that token; a save by another administrator keeps the
  stored values and changes the other settings.
- **Tests.** `AdminSecretTest` holds both over real requests, and that no journal carries a secret.

### Small repairs found by the audit of the panel sections

- **Site message.** The edit link of a message on the home page opens `name=messages&op=add`, where it led nowhere before,
  and an expired message is switched off without being shown once more.
- **"Редактор".** `save()` takes the path of the file from its tab; a path sent in the request is ignored.
- **"Блоки".** A bad file name in `filecode()` returns to `op=fileedit` instead of an operation that does not exist.
- **"Конструктор сайта".** A new block is created with `refresh` 3600, as the form of "Блоки" offers it: at 0 an RSS block
  fetched its feed on every view.
- **Help.** "Рейтинги" is rewritten by the code; "Категории", "Конфигурации", "Опросы", "Рассылка", "Группы", "Загрузки" and
  "Языки" no longer name modules that are gone, and "Сообщения" says where a message shows.

### The panel takes the parts of its series: "Статистика" and "Мониторинг" are cockpits, the other screens take the window

The engine of the series lives in `admin-ui.js`, and the two screens are built on it by their data. The references are
`docs/TEMPLATES.md`, "Parts Of A Screen" and "The Engine Of The Parts", `docs/ARCHITECTURE.md`, "Admin Shell Boundary", and
`docs/PERFORMANCE.md`, "Admin Runtime"; the help of each screen is in `admin/info/` and `modules/*/admin/info/`.

- **"Статистика".** `getStatData()` gives the cockpit: the dial of the day, the archive strip kept in
  `storage/counter/archive.json`, the windows, the log of days and `op=live`. The GD chart and the settings `bet` and `shi`
  are gone.
- **"Мониторинг".** The pulse and thirteen signals of `getServerSignalList()`, the tiles over the live chart and the
  findings of the audit; `op=live` takes the place of `op=status`, `op=traffic` and `op=sync`.
- **The screens of the panel.** "Рассылка", "База данных", "Безопасность", "Конфигурации", "Обновления", "Загрузки",
  "Поиск", "Карта сайта" and "Мастер" take the window, the tiles and the meters on top. The key `pane` of
  `getTplAdminList()` puts a list inside a window.
- **The mark "i".** `getTplLongCell()` opens a value of `CELL_LONG` (16) characters or more whole behind the mark, a shorter
  one keeps its title. A cut cell that ends a row stays inside the table, and a status wraps instead of covering its
  neighbour.
- **The meters.** `getTplMeter()` draws a share of a real limit: a campaign with nothing expected shows its figures alone.

### Visits are counted in one place, the files of the statistics, and robots are named by type

The counter of the statistics is the only record of visits; the page `name=referers` and the table `_referer` are gone.
The help of the panel is `admin/info/statistic/ru.md`, the upgrade notes are in `UPGRADING.md`.

- **The fields.** A line of `statistic.log` gains the hosts, the landing pages, the names and the types of robots. A full
  field lets a frequent key in by evicting the rarest, so a burst of spam no longer holds the top.
- **The sources.** A source counts the start of a session only, the new source `ai` is checked first, and the own site is
  told by its domain, so a search with the name of the site in its query is a search.
- **The robots.** `getBotInfo()` reads the masks of five types, and `getVisitBot()` adds a visit without `Accept-Language`
  as a robot; `is_bot()`, `from_bot()`, `engines_word()` and `updateRefererTrack()` are gone. A robot leaves "Аудитория"
  once and no longer writes search words.
- **The settings.** The lists and the masks moved to the settings of the statistics, the tab "Краулеры" of the
  configuration is gone and its tabs after it moved up by one.
- **The cache.** `Cache::setBody()` retries a `rename` on a busy file, as the writer of the configuration does.

## 2026-10-30

### The panel moves by one set of rules and behaviours, and a still panel stops all of it

The effects settled on the demo series "Статистика" became the motion of the admin theme. The reference is
`docs/TEMPLATES.md`, "Panel Shell", "Motion"; the help of the panel is `admin/info/panel/ru.md`.

- **The light.** A light runs round the frame of the cockpit, a home tile and a card of "Мониторинг" under the hand; the
  scan and the comet of the cockpit moved into the block "Motion over the page", and the circles of the cockpit glow at
  the share of the gauges of the presentation.
- **The figures.** The rings, the strip and the queues run to a new value and light once; the arcs of the rings fill
  after them.
- **The home.** The tiles rise in their turn when the grid enters the view and lean to the hand under a glare; a press on
  a tile, a row of the menu or of its counters and a control of the cockpit spreads from the pointer.
- **The charts.** A chart of "Мониторинг" is drawn left to right once when it enters the view, and a poll does not
  draw it again.

## 2026-10-29

### The site builder edits the pages, the menus and the parts of the theme on the page itself, and publishes them with their blocks in one step

The start page was one module of the list in `config/global.php`, and the header, the menus and the footer of lite were
partials that carried the data of slaed.net into every site. A site now has a layout, `config/layout.php`: pages of
sections at addresses of their own, two menus and the parts of the theme, edited by "Конструктор сайта" of the panel
in a draft and published with the rows of `_blocks` they touch. The references are `docs/ARCHITECTURE.md`, "Layout
Boundary", "Address Boundary" and "Block Boundary", `docs/TEMPLATES.md`, "Layout Sections", and `docs/UPDATE.md`,
"Settings, Languages and Themes"; the help of the panel is `admin/info/layout/ru.md`.

- **Pages.** The page `home` of the layout is the start page, its module slot holding the module of the home route
  (the first entry of the list, no longer one drawn at random). A page of sections answers at the root as the kind
  `page` of `_routes`, with 301 from a former address and 410 once deleted, its own title, description, picture and
  `robots`, an entry in the sitemap and the audience "users only"; the page 404 of the layout answers a missing address
  with the code kept. `which` of a block takes `page-<number>`.
- **Sections.** 21 types in six groups: the module slot of the core, 17 of lite, "Свежие материалы" and "Материал" of
  the module Node, "Внедрения" of the module presentation. A type is a description, a fragment and, in a module, a provider; Markdown renders
  through the parser, a video, a map and an outside page load only on a click.
- **The chrome of lite.** The header, both menus, the contacts, the band before the footer, the marks of the footer
  and the side tabs come from the layout by `places.json`; the delivered layout is neutral, and slaed.net takes its own
  by the import of the builder. The marks moved to `uploads/presentation/brand/tech/`, `blocks/menu.php` prints the main
  menu.
- **Blocks.** `setBlockOrder()` is the one writer of the zone and the order; the positions b and f leave, their rows
  moving to d by a statement of `table_update6_3.sql`; an expired block no longer cuts its column, a block without its
  file is hidden from visitors, a Node type carries columns of its own (`view.side`, `view.top`), and `[block=N]` renders
  in a trusted rendering.
- **The screen.** The structure, the page in a frame and the inspector; 50 steps of Undo kept in the draft; the checks
  of links, anchors, descriptions and weights before a publication, with its note; a history of ten with one section
  back; the guest view, a signed link of the draft, ready pages, own sections, the copy of a page and a page from a
  text; the export and the import of the layout as JSON. One draft serves every administrator, and a write that saw
  another revision is refused.
- **Beside its demo.** The screen was set beside the demo series "Конструктор сайта" and built of the canon of the
  panel where the two differed. The page draws at the width of its device, 1366, 820 or 390 px, scaled to the stage;
  the library sets its cards under the heading of their group by the key `order` of a type, each card a schema of its
  type; a zone of blocks carries its icon from `places.json`, the blocks their order in the zone, a menu item the name
  of its page or module; Alt+↑/↓ and Delete move and remove the picked section; the choice of a page lists the modules
  without a page and ends in "+ Новая страница". A button of lite is closed on both sides: no default text without its
  address, and the check names a filled text whose address is empty.
- **The publication.** The file, the addresses and the rows of `_blocks` go in one transaction inside the shared writer
  of `config/`, its proof a witness row; a row changed meanwhile in "Блоки и баннеры" stops it.
- **On the page.** "Конструктор сайта" of the floating menu, one entry in the panel and on the site, opens the builder
  on the page where the administrator stands; in the frame `plugins/system/builder.js` rings a part, types a text in
  place, drags a section and shows its toolbar.
- **One drag of the panel.** Rows of a table or a list drag by their handle; "Блоки и баннеры" post the order, and each
  administrator keeps an order of the panel menu of their own in the cookie `menu`.
- **Tests.** `LayoutTest`, `LayoutDraftTest`, `LayoutPublishTest`, `BlocksTest` and the route tests.

## 2026-10-26

### Every stored text is Markdown by CommonMark and GFM, and BB is left with the six SLAED tags

The parser read Markdown by its own habits and BB beside it. It now reads CommonMark 0.31.2 with the GFM extensions,
every example of both specifications passing in the trusted and in the safe mode; the update turns the BB of every
stored text into Markdown once, and the bracket layer reads `[hide]`, `[tabs]`, `[attach]`, `[usehtml]`, `[usephp]` and
`[block]` alone. The references are `docs/PARSER.md`, "The standard", "SLAED tags" and "Texts of the BB era", and
`docs/EDITORS.md`, "Stored Text on Mount and Save"; the help of the panel is `admin/info/config/ru.md`.

- **What goes.** The smilies `*01`–`*18` become emoji, with `images/smilies/` of both themes and `_SMILIE`; `[u]`,
  `[color]`, `[size]`, `[family]`, the alignment tags, the alignment of an image and ed2k keep their text and lose the
  formatting; the Toast UI quote button and `_EQUOTE` go. `filterClickable()` goes with the settings `clickable` and
  `alink`: a bare address is a link at render.
- **One storage, one rendering.** Every Markdown text is stored as typed through `filterSourceText()`, line ends as LF,
  and mounted in the editor unchanged, so a save without a change keeps every byte; the forum, the signatures, the own
  blocks, the blocks, the admin messages, the newsletters and the contact text render safe with trust, as a Node
  material does.
- **Safe addresses.** A safe rendering keeps an address without a scheme and one of `http`, `https` or `mailto`;
  `data:`, `javascript:` and `vbscript:` die in every mode, but a Markdown image of `data:` passes as an embedded raster.
- **Tests.** `ParserFixturesTest` runs the examples of `tests/Fixtures/spec/`, `UpdateTextTest` the conversion,
  `EditorBreakTest` the storage of every writer.

### The update brings a site of early October forward, and the tables of the gone and the carried modules leave

`slaed.net` runs a development build of early October with its content already in Node, so it is moved by bringing its
own database forward, not by a new installation. The first stage of `update.php` now does that for any such site, and
the whole move was rehearsed on the production dump of 2026-10-25. The references are `docs/NODE.md`, "A site of an
early 6.3 build", and `docs/UPDATE.md`, "Moving slaed.net to 8.0".

- **The catch-up.** The addresses of `uploads/archive/<module>/` and of the old module folders in the Node texts, the
  comments and the other texts become attachments or `go=file` addresses; the options of the release a site lacks are
  merged from `storage/backup/release/config/` and the retired ones leave; eight time columns take their forms with
  every moment kept; a 6.3 `db.php` is written in the collation of the release, so the schema file converts the tables.
- **Tables.** The tables of `auto_links`, `clients`, `clients_down`, `money`, `order`, `partners`, `products` and
  `whois` are dropped with no archive, and the source table of a carried module as soon as every row of it is a
  material, in the first stage for a site already in Node and right after the data step in the second. `jokes` and `media` join the
  6.2 path, the media columns going to the extra fields of its profile and its web links to `download` resources.
- **A unit that ran before.** Points, ratings and fields with their mark set and their manifest missing answer done; the
  units used to stop the whole run on a site brought from its dump, and the tables stayed.
- **The rehearsal.** On a copy of the dump, both stages and a repeat that changed nothing: 36 tables equal to
  `table.sql`, 15385 addresses, 121877 rows in a dump of the core restored and compared table by table, the folders of
  `uploads/` laid out and checked file by file, and the site and the panel checked live in two passes.

## 2026-10-25

### Two writers of one address no longer end a request in a 500 on MariaDB 11.6.2 and newer

With `innodb_snapshot_isolation`, the MariaDB default from 11.6.2, two writers that moved one entity at once, and on
some versions two that took one free path, left the second with error 1020 "Record has changed since last read". The
server rolls the whole transaction back on 1020, and `Route` answered it with `RuntimeException`, a 500 for a form
that only needed its next suffix. A write of `Route` in its own transaction now runs whole again with a fresh snapshot,
at most three times; a write inside the transaction of its writer still throws, as `Point` does, because only the
writer can repeat its operation. The reference is `docs/ARCHITECTURE.md`, "Address Boundary"; `RouteRegistryTest`
runs both races on MariaDB 11.8 and MySQL 8.4.

## 2026-10-24

### The panel gets one shell: the options in place, Ctrl+K, a cockpit, a floating menu, voices and a list canon

The etalons of the panel and of the site under an administrator moved into the themes `admin` and `lite`, so the first
8.0 release brings them to every site. The references are `docs/ARCHITECTURE.md`, "Admin Shell Boundary",
`docs/TEMPLATES.md`, "Panel Shell", "Admin List Contract", "Admin Form Contract" and "Alert Action Slot", and
`docs/WINDOW.md`, "Where the windows are"; the help of the panel is `admin/info/panel/ru.md`, opened from "Опции" and
from Ctrl+K.

- **The shell.** One `h1` on a page, a skip link, the tab title by section, the menu as a drawer on a phone, a filter
  and the current section in the menu, the dial "…" one Tab stop. "Опции" apply in place through op `option` — the
  colour mode, "Движение", "Звук", "Заставка", the floating menu in the panel and on the site, its position, the
  editor; only the language reloads. Ctrl+K opens the sections, the actions and the settings of the page; `[` folds the
  menu into a rail.
- **Queues and the session.** One source of the queues feeds the bell, "Ждут внимания", the chips of the menu and the
  cards and the floating menu, polled once a minute. The panel session ends after the key `idle` of
  `config/security.php` (60 minutes) without an action; a window warns before the end and keeps the session, and no
  poll counts as an action.
- **The home.** The cockpit of a super administrator: four rings, a lamp of fourteen signals with the age of a backup
  against `dump_age`, the strip of the request and a live chip; the warnings about `setup.php`, the self-check and the
  PHP version are signals of the lamp.
- **The block and the menu.** One block "Администрация" in both themes — in the panel on the home, and on every page
  while the floating menu is off, on the site first in its column; the window "Наблюдение" with the present of the visitors; a floating menu in the panel and on
  the site with the pins, the queue, "Все разделы", "Эта страница в панели", the card of the administrator, the own
  links and the window tools.
- **Lists and forms.** Every list of the panel is drawn by one canon: the filter bar, the address with the page, four
  views, a kept sort, relative dates, cards on a phone, deferred actions with Undo, the keys J, K, X, E and "/", and a
  personal number of rows per page; every POST form asks before its changes are left behind, and "Конфигурации" check
  their fields with a summary and find a setting. An alert carries its action in one slot.
- **Voices and the screensaver.** Short voices in `sound/` of each theme answer actions and events after the first
  touch and are switched in "Опции" and by zones in the profile; the screensaver "Возрождение" starts after the chosen
  idle minutes.

Breaking for code outside the tree: `getThemeImageUrl()` and `getThemeImagePath()` became `getThemeFileUrl()` and
`getThemeFilePath()` from the root of the theme (`images/…` in the argument); `isThemeStill()` became
`isThemeFlag($key)`; `setToast(text, warn)` became `setToast(text, opts)` with `{warn, undo, time}`;
`getTplPostButton()` takes the look as its fifth parameter; `Rating::getRatingList($scope, $id, $page, $limit)` pages
by number; `getSelfCheckAlert()` takes no argument; the ops `mode` and `changeeditor` of the panel, the function
`toplist()` of "Поиск" (its op stays on `search(true)`), `getAdminFavoriteList()`, `getAdminPrivateList()`, `getAdminCountRow()`, `_EDITOR_UNDO`
(now `_UNDO`), `_GROUPS_LEAVE` (now `_ADM_LEAVE`) and `_PHPSETUP` are gone; `fragments/admin-block-links.html` is
`fragments/admin-block.html`; "Оптимизация" and "Ремонт" of "База данных" run only by POST, the ban lists are
`op=banlist&kind=ip|user`; `public/sound/` is gone and its address answers 404.

### Every panel list does what "Комментарии" do, and takes it from the canon instead of its module

Until now only "Комментарии" had a filter bar, a relative date, the "i", the key E, row picks with a bulk foot and
"Отметить все N"; every other list glued its date and its tip into its cells and deleted one row at a time. The list
canon `getTplAdminList()` now builds all of it from the meaning a module hands it. The reference is `docs/TEMPLATES.md`,
"Admin List Contract"; the help of each section in `admin/info/` and `modules/*/admin/info/` says what its list does.

- **The row by meaning.** A row hands `name`, a cell `moment`, `details`, the dial items with `is_edit` and `is_find`,
  `drop` and `stat`; the canon draws `<time datetime>` with its relative reading, the "i" in the first cell past the id,
  `data-sl-edit` for the key E and the dial with the delete last. No list function writes a `popover`, a `stamp`, a
  `data-sl-edit` or an `hx-boost`. "Избранное" date by `_favorites.time`, "Голосования" by `enddate`; the bans read as
  the moment they end.
- **Picks and the bulk foot.** Seventeen lists with a deferred delete carry a box per row, "Отмечено: N", the choice of
  the action and OK, with Undo for ten seconds; the lamp of "Сообщения" turns in place, "Вернуть" of the undelivered is
  the action `move`. A delete under a question, "Аннулировать" of a rating vote and a campaign with mail in its queue
  stay single.
- **"Отметить все N".** The twelve paged lists of the foot act on the whole list under its filter: the foot handler is
  posted with `upto` and walks the list in portions of 100 by `setListPortion()`, with a progress line and a pause;
  rows added after the page was drawn are not touched.
- **Filters.** "Избранное", the private messages, "Сообщения", "Голосования", the campaigns and the undeliverable
  addresses of "Рассылка" got the filter bar; the list, its count, the pager, the foot and "Отметить все N" read one condition. A
  language picks its own rows and the rows for every language.
- **Empty states.** An empty list under its filter says "ничего не найдено", without one "нет информации"; the four
  queues of the bell say "всё проверено" in the calm tone. The OAuth provider column is `_OAUTH_PROVIDER`, no longer
  `_MODUL`.

Breaking for code outside the tree: a delete or status handler of the panel lists takes `id[]` (one id is a list of
one), and the delete of a user from the profile and the poll menu of the site post `id[]` too; `getTplEditMenu()`
writes a list of its address back as `key[]`; a ban removal names its list by `kind` (`ip`, `user`) instead of `id` 1
and 3; op `actions` and op `sweep` of "Комментарии", `Comment::setAdminPortion()` and `_COMMENTS_PICK` are gone;
`Privat::getAdminList()` and `Mail::getDeadList()` take the filter.

## 2026-10-18

### A site updates itself from the panel under the signature of the SLAED key, and every failure rolls it back

A site learns of an update only by code it already has, so the updater enters 8.0: without it every 8.0 site would stay
blind to the next security fix. The reference is `docs/UPDATE.md`; the help of the panel is `admin/info/update/ru.md`,
that of the master `modules/master/admin/info/ru.md`.

- **The check.** The scheduler job `updatecheck` asks `https://slaed.net/api/v1/master/check` once a day at a minute of
  its own, HTTPS only and without redirects. The site sends its identifier, channel, version, PHP and database versions,
  three extension bits, two error counts and its domain unless switched off; no text and no path. A banner names the
  release, red for a security fix; notes of the master arrive signed, as plain text.
- **The signature.** A manifest, a note and a key statement verify under Ed25519 keys signed offline on the owner's
  machine; the factory keys `Update::PRIMARY` and `Update::RECOVERY` ship in the core and their fingerprints in
  `README.md`. Without `sodium` the class `Signature` verifies, without `zip` `PharData` unpacks under the site's own
  reading of the central directory.
- **The installation.** "Обновления" downloads with resume or takes an archive by hand only under the manifest the master
  serves now, unpacks into `stage/`, shows what "Проверить установку" would change and installs in steps, each its own
  request, while `apply.lock` closes all three entries: the database in portions of the site's own tables, the files by
  renaming through the executor of the new package, the database steps in the light mode `UPDATE_FILE`, the merge of
  `config/`, the self-check. A failure rolls back files, `config/` and database with the caches; a rollback code and the
  file `rescue.now` reach the installed `core/rescue.php` when the core no longer boots; "Вернуть предыдущую версию"
  restores the last installation. The format of `apply.lock`, the backup and the portion dump is frozen as contract
  version 1.
- **Settings, languages and themes.** An update adds the options a site lacks at any depth and keeps every saved value;
  the language files and the themes `admin` and `lite` are replaced whole, other themes are not touched.
- **The manual path.** While `SL_VERSION` is above the last database step done, every entry answers 503 and only the
  light mode of the panel opens, with "Применить шаги базы" after a backup of the site's tables; after the steps it
  clears `config/local.php`, the caches of pages and assets and OPcache, as an installation does.
- **The release archive.** It carries only what a site runs. The modules `master` and `changelog` of slaed.net with the
  changelog templates, `public/update.php`, `table_update6_3.sql`, `tests/`, `tools/`, `docs/`, the stands
  `public/demo/`, `public/phoenix/` and `public/sound/`, `composer.json` and the other files of development and documents
  of the root stay in the repository; the build cuts the rows of the modules it leaves out from `config/modules.php`.
  Such a module keeps its name in its own languages, read by `getModuleLabel()`, and declares its scheduler jobs in its
  `scheduler.php`. The GitHub release goes to the repository `Update::PAGE` names, with the two signed archives.
- **The master.** The module `master` of slaed.net accepts releases and notes from `inbox/`, serves them by channel,
  share and pause, keeps a minimal statistic without an IP, pauses a release by itself on failed installations and
  mails the owner. It is in the repository and never in a release archive.
- **The tools.** `tools/release.php` builds the full archive and the delta from a tag and publishes, `tools/sign.php`
  signs and runs the key ceremony, `tools/rehearse.php` installs the previous release clean and updates it by both
  archives; `publish` refuses without that green rehearsal and with test factory keys. Before the first release the
  whole chain went through a real master on two OSPanel projects, a key change included.
- **The API.** The branch `/api/` of `public/index.php` is the one entry: the light core, the guards of `.rules/api.md`,
  the envelope, the reply mode `file`, `system/ping` and `system/schema`.

Breaking for code outside the tree: `$conf['version']` is built from `SL_VERSION` and `SL_CODENAME` of
`core/version.php` and is no key of `config/global.php`; every entry that loads the core calls `getSiteGate()` of
`core/gate.php` first, before the core; the language files of the shipped modules and the themes `admin` and `lite` are
overwritten by an update; `public/sitemap.xml` left git, `public/robots.txt` is a site file; `_CHANGELOG` and `_MASTER`
left `lang/*.php` for the languages of their modules; `addMasterTask()` is gone, a module job runs through
`addSchedulerModuleJob()`; `getModuleName()` answers the name of a site module its map lacks, `node` included, instead
of its key. The shipped `config/` carries no value of the stand: no test ban, no row `master`, error level 0.

## 2026-10-14

### Every page has one readable address without an id, and every other form of it answers one 301

The links of 6.2 were `index.php?name=files&op=view&id=5061`, and its switch of readable addresses could not work:
switched on, it printed `faq%2Fview%2F4048`, which Apache refuses before PHP, and nothing parsed a path. The addresses
change once, before the release, so the index of a search engine moves from the 6.2 query addresses straight to the
final form. The reference is `docs/ARCHITECTURE.md`, "Address Boundary"; the addresses of a type are in
`docs/NODE.md`, "Addresses", the links of templates in `docs/TEMPLATES.md`, "Links In Templates".

- **The addresses.** `/files`, `/files/adaptivnyi-shablon-pure`, `/files/moduli`, `/files/moduli?page=2`,
  `/forum/modul-poiska`, `/users/eduard`, `/voting/kak-vam-novyi-dizain`, `/files/add`, `/files/asset/2`. The prefix
  of the type is part of the address, the separator a fixed `/`; materials and categories of a type share one
  namespace; a private type keeps `/<type>/view/<id>`. AJAX, feeds, the panel and `/uploads/` keep their addresses.
- **One registry.** `_routes` maps a path to its entity — the current path and every former one; one class, `Route`,
  owns it. An address is never reused: a former path answers 301 to the current one, the path of a deleted entity
  410, and both stay taken. `filterSlug()` transliterates the letters of all six locales and cuts to 80 characters.
  Every writer of a material, type, category, topic, account and poll gives, moves and marks gone its paths inside
  its own transaction; the address follows the title until the first publication and then changes only by the field
  "Адрес". The name of a Node type is permanent, and the name of a deleted type is never given again.
- **Migration.** `addRouteBacklog()` gives every entity due an address its first path, categories before materials
  and the oldest first, from the second stage of `update.php` and from `setup.php`. No 6.2 path form is parsed: 6.2
  printed query addresses, and those answer one 301.
- **Parsing.** `core/security.php` cuts a path of up to five words before the database, `public/index.php` resolves
  it by one grammar against the configuration and the registry; `getVar()` reads scalars from `$_GET` and `$_POST`,
  so the keys of a path reach every reader. One indexed query per page view.
- **Building.** `getSeoUrl()` builds the address from the path the reader joined, operations and a numeric `id` in the
  path, the rest of the query sorted; every address the site prints starts at the root of the site, `HOME_PATH`, and
  templates take `{{ root }}`. The query counts of every list page stayed as they were.
- **One redirect.** `setSeoMove()` answers a former path, upper case, a trailing slash, `/index.php/`, a query address
  of 6.2 or 8.0, `?num=`, `page=1` and the wrong scheme or host in one absolute 301, after the entity is read and its
  access checked, for `GET` and `HEAD` only. `robots.txt` names the sitemap absolutely, written by the sitemap task,
  and lost the `Disallow` lines of folders no longer under `public/`.
- **The servers.** Checked live on Apache with the root on `public/` and on the project and on nginx: the same answer
  for every address of the route matrix and every link the pages print. The `.htaccess` of the project turns off the
  slash redirect of `mod_dir`, which sent `/docs` to the folder `docs/` of the project and back in a loop;
  `public/.htaccess` and `nginx.conf.example` refuse every dot file but `.well-known/`, which nginx served.
- **Pages.** A public list counts its pages by `page`; the panel keeps `num`. A profile of a user that does not exist
  answers 404, a profile closed to guests 403.

Breaking for code outside the tree: `rewrite`, `sep`, `tsep`, `title` and `ctitle` are no settings and no keys of
`getSeoUrl()`; `getSeoUrl()`, `Template::getAssetUrl()`, `getUploadUrl()` and `FileAccess::getFileUrl()` answer
addresses from the root; an entity link passes its `route` (`null` for none), and a link of `op=view` without the key
is logged; `getVar()` no longer reads through `filter_input()`; `getPublicUrl()` takes an address as a string too and
`setRedirect()` roots a relative target; `filterSlug()` lost its separator; `user_info()` takes the path of the user
as its second argument; `getPageNumbers()` writes `page` on the site and takes the keys of its page as an array;
`NodeService::addNodeCategory()` and `updateNodeCategory()` take the field "Адрес", `Node` and `NodeInput` end in a
path; a page link in a template or a script starts at `{{ root }}` or `HOME_PATH`. The server must send every path
that is no file to `index.php`, as `public/.htaccess` and `nginx.conf.example` do.

## 2026-10-13

### The schema takes one set of column domains, one connection contract and keys that serve the queries that run

Every change of a type, a key or a collation after 8.0 would be a migration step on every installed site, so the whole
schema was brought to one set of conventions before the release. The reference is `docs/NODE.md`, "Schema
conventions"; `tests/SchemaConventionTest.php` holds them for every later table.

- **The connection.** `Database::__construct()` takes the collation and sets it with `utf8mb4`, the time zone of PHP
  (by name where the server refuses the offset) and one strict `sql_mode` with `ONLY_FULL_GROUP_BY` on every path —
  the site, the panel, `setup.php`, `update.php`, the tools and the probes that load the code — so `NOW()` and
  `date()` write the same moment and MariaDB refuses what MySQL would. The zone line of `core/system.php`,
  `$conf['db']['sync']` and the panel switch `dbsync` are gone; the referer list groups by its newest row.
- **Text.** `utf8mb4_unicode_520_ci`, the newest collation both MySQL and MariaDB know, comes from `{collate}` of
  `config/db.php` alone. `ip`, `modul`, `scope` and `lang` are binary ASCII of one width each, a `lang` column holds a
  code. `filterCutText()` cuts a stored, escaped value to its column without splitting an entity, and every writer of
  an outside value uses it; a fixed choice is checked against its values.
- **Time.** A moment is `DATETIME`, a machine timer `BIGINT` unix seconds, and no column holds a time as text:
  `_mail.ntime` and `locked`, `_node_publish.due`, `_blocks.time` and `_rating.time` are timers, `_blocks.expire`,
  `_message.expire`, the stamps of `_rating_*` and `_user_oauth` are moments, `_users.lastvis` is NULL for never.
  `_users_temp.time` leaves. The forum marks a topic new only when it changed after the last visit, not every topic.
- **NULL.** No flag, counter or text is NULL. `_users.viewmail` is 0 where it was NULL, so an address stays hidden as
  the profile showed it, and saving the settings untouched no longer publishes it. An empty birthday is NULL; the
  false ones a date filter wrote into empty fields are gone.
- **Keys.** `_users.email`, `_admins.email` and `_users_temp.email` are unique with NULL for "no address";
  `Database::getSqlTwin()` names the key a duplicate hit, and every writer answers it as "address taken". Covered keys
  and `(191)` prefixes leave, unused keys leave, the lists of the forum, comments, blocks, categories, search and
  support get the keys they sort by, and `_node_categories` keys on `(nid, cid)`.
- **The 6.2 path.** `table_update6_3.sql` converts column by column, reaches every form through `runiftype()` and sets
  the keys through `setidx()`, both doing nothing on a second run; `update.php` stops before any change while two
  accounts share an address, and maps language names to codes. Any clean 6.2 site migrates: the rehearsal ran on
  MariaDB 11.8, MariaDB 10.11 and MySQL 8.4. `setup.php` starts its session only when none is open.

Breaking for code outside the tree: `Database::__construct()` requires the collation; `$conf['db']['sync']`, `dbsync`
and `_users_temp.time` no longer exist; the time columns named above changed type; an address column is written NULL,
never `''`; a `GROUP BY` must name every column it selects. The recommended servers are MySQL 8.4 and MariaDB 10.11 or
newer; the floor stays MariaDB 10.5.2 and MySQL 8.0.16.

## 2026-10-12

### Every journal of the site takes one format, the panel records each action, and every login lands in a table

No change made in the panel was recorded anywhere. A login was a block of text lines in `admin.log` or `user.log`, an
OAuth login was written three times in two formats, `oauth.log` and `database.log` kept forms of their own, and a user
could see none of their logins. The reference is `docs/ARCHITECTURE.md`, "Journal Boundary"; the help of the screen is
`admin/info/security/ru.md`.

- **One format.** `Logger` gains the channels `admin`, `user`, `oauth`, `database` and `filescan` with a method each,
  and `addRequest()` for the request journal; `addEntry()` is protected. The five carry the address, the method, the
  URL and the browser of the request, never its body. `Oauth::setLog()`, `addDblog()` of the database tool and
  `write_log()` of the file scan are gone; every journal rotates through `addCompress()`, `oauth.log` and
  `database.log` included.
- **The actions of the panel.** `checkAdminPost()` notes a passed token check through `setAdminLog()`, `setRedirect()`
  gives it its result, and `addAdminLog()` writes one line of `admin.log` when the request ends: the administrator,
  the module, the operation, the object and the number of a bulk action, `done`, `refused` or `open`. Every module is
  covered by that one point, one written later too; the drag-and-drop order of categories and blocks moves onto
  `checkAdminPost()`. The switch `log_a` covers actions and logins of the panel.
- **Logins.** `addLoginReport()` becomes `addLoginLog(int $uid, bool $adm, string $res, string $login, string $way)`,
  the one writer of a login: a row of the new table `_login_log` always, an event of `admin.log` or `user.log` while its
  switch is on. `oauth.log` keeps the events of the protocol and no longer a second record of the login. The monitor
  counts the refused logins of the panel from the table, and `cachegc` removes rows older than 90 days.
- **Reading.** A user reads their own logins in the profile, «Журнал входов»; the panel reads them all on the tab of
  the same name of «Безопасность» with filters. Every journal opens there as a table with filters of level, period,
  address and, for `admin.log`, event, module and result, and the pager; `filescan_tree.log` keeps the raw view. Every
  module ends its tab strip with «Журнал действий», that table filtered by the module, for an administrator who may
  open «Безопасность».

Breaking for code outside the tree: `addLoginReport()`, `Oauth::setLog()` and `write_log()` no longer exist, and
`Logger::addEntry()` is not public. The 6.2 update creates `_login_log`; the old text blocks of `admin.log`,
`user.log` and `oauth.log` stay in their files and the table view skips them.

### The rank of a group is a row of icons in a colour of its own, and the group list becomes a ladder with a rank composer

A rank was one of fourteen 93×17 pictures in `templates/lite/images/ranks/`, which seven readers found each their own
way, and the panel listed the groups as one table and picked a rank from a `select` of file names. The rank is now drawn
with Bootstrap Icons from one stored string, and the groups screen wears the face of the stand page
`public/demo/grp-10-unified.html`. The reference is `docs/TEMPLATES.md`, "Rank" and "Group Ladder And Rank Composer";
the help of the screen is `admin/info/groups/ru.md`.

- **The rank as icons.** `_groups.rank` holds `icon:lit:total:#rrggbb`: M marks of one icon, the first N lit in the
  colour of the rank, the rest in a muted tone. `getRankData()` and `getRankView()` in `core/system.php` parse and draw
  it through `fragments/rank.html`, the same in both themes; the comment, the forum post, the profile, the user block,
  the users statistics and the panel list pass the raw column. The schema does not change.
- **The migration.** `table_update6_3.sql` maps the fourteen pictures onto icon strings in tones darkened to hold 3:1 on
  the light and the dark surface — steel `#8297a2`, gold `#b0904d`, bronze `#c68649`, sky `#629abf`; a site's own
  picture becomes no lit star of five in its group colour. Only values ending in a picture extension change, so a second
  run changes nothing.
- **The ladder.** «Главная» shows «Лестница пунктов», the point groups by threshold, and «Специальные группы», by
  members, as steps on a rail: the threshold or the mark «Специальная», the rank and the name in the group colour, the
  description, «Участников: N» as a link to the user list over a bar of the share, the dial. Each list reverses in place
  by its sort button. One query counts the members of every group.
- **The composer.** «Добавить» holds «Готовый ранг» (eighteen presets), «Свой ранг» (forty icons and the icon window,
  the counts, the rank colour as swatches, an own colour or the group colour) beside a preview at three sizes and two
  contrast tiles for the light and the dark theme, and «Группа», where the threshold says where the group will stand. A
  new group opens filled as the next step of the ladder. The rows of choices are toolbars with one Tab stop, and leaving
  a changed form asks first.
- **A painted name stays readable.** The panel clamps the lightness of a group colour in both themes, so any stored
  colour reads at 4.5:1; the stored value is untouched.
- **`save()`** writes the joined string, clamps the counts, and refuses a bad icon, a bad colour and a threshold
  another point group has.
- **Strings.** The captions of both screens, the eighteen preset names and the colour names in six locales;
  `_POINTSNEEDED` reads «Порог пунктов»; `_GROUPSI` and `_COLOR` are gone.
- **Breaking:** an old picture name in `_groups.rank` is not drawn until `update.php` ran, and the folder
  `templates/lite/images/ranks/` is gone. The templates take `rank_html` in place of `rank_link`, `rank_src`, `rank_url`
  and `has_rank_image`, so a theme with its own copy of the comment, the forum post, the profile or the user block shows
  no rank. `.sl-profile-rank-img` and the site token `--sl-rank-height` are gone.

## 2026-10-11

### One segmented switch with a travelling plate, and one pager for both themes that pages with the arrow keys

The colour mode of the site header was a rail with a plate that copied the chosen icon and stepped by an `nth-child`
ladder, the site pager a band of mini buttons with its line above it, the panel pager a row of `sl-pnum` cells with
drawn arrows, and "Наверх" a script in the panel and a hidden link on the site. The switch and the pager now wear the
faces of the stand pages `public/demo/pg-09-final.html` and `pg-14-admin.html`, chosen by the owner. The reference is
`docs/TEMPLATES.md`, "Segmented Switch" and "Pager".

- **One segmented switch.** `sl-seg` with `sl-seg-cell`, the empty plate `sl-seg-knob` and `sl-seg-run`, in both
  themes, with the modifiers `sl-seg-mode` (site header only), `sl-seg-view` (the view of a Node list) and
  `sl-seg-pager`; eight `--sl-seg-*` tokens in the API block of each `base.css`.
- **The plate travels.** A rail names itself in `data-sl-knob`; `slaed.js` measures the chosen cell and the sheet moves
  the plate. A link, the mode form and an htmx swap hand the place to the next page through `sessionStorage`, so the
  plate moves from where it stood; nothing moves under `prefers-reduced-motion`, and without the script the chosen cell
  wears the plate.
- **One pager.** The same `pager.html` and `pager-link.html` in both themes: the arrows, the numbers on the rail with
  the current page in the brand colour, "Наверх" and an "i" beside them, and the line "Страница 3 из 10 · Показаны
  21–30 · Всего 97" under the row on the site and at the right of the row in the panel. The pager narrows by its own
  column — far numbers and the range go under 520px, the neighbours under 320px — and the panel list foot puts the bulk
  action under it on a phone.
- **The arrow keys page the list.** ← and → follow the back and forward link of the first pager on show, by a click so
  an htmx pager swaps its part; a field, an editable, a tab, an open window and the file desk keep their arrows. The "i"
  explains the keys in the system tip and is hidden on a touch screen.
- **One "Наверх".** Every link to the top points at `#top` and the page scrolls smoothly by CSS, switched off under
  `prefers-reduced-motion`; `Upper()` of `slaed.js` and the inline handler of the panel footer are gone.
- **Strings.** `_PAGENAV`, `_PAGERANGE`, `_PAGEKEYS`, `_PAGEPREV`, `_PAGENEXT`, `_PAGEKEYNOTE`, `_KEYLEFT`, `_KEYRIGHT`
  in six locales; "Всего" in the page line carries no colon; the French `_PAGETOP` reads "Haut de page".
- **Breaking:** a theme with its own `pager.html` or `pager-link.html` keeps its old face and loses the marks, the tip
  and the keys; the fragments take `is_far`, `is_near`, `is_dots`, `rel`, `key_name` and `range_*`, and
  `.sl-pager-nextprev`, `.sl-pager-prev`, `.sl-pager-next`, `.sl-pnum-nav`, `.sl-pnum-cur`, `.sl-pnum-dots`,
  `sl-mode-rail`, `sl-mode-cell` and `sl-mode-knob` are gone, as is the `is_pager_dots` branch of `inline-badge.html`.
  The panel API loses `--sl-pnum-width`, `--sl-pnum-height`, `--sl-pnum-border`, `--sl-pnum-arrow-width`,
  `--sl-pnum-arrow-height` and `--sl-card-shadow`. `getFootControls()` takes no `$label` and `$debug` any more, the
  `debug_html` branch of `foot-controls.html` and the flag `is_upper` of `link.html` with them, and a list of keys
  of `link.html`, `span.html` and `toggle-form-block.html` no caller passed is gone.

### A Node list gets a header of its own, offers the views of its type, lives in place and hints its search

The list of a Node type had the shared navigation of the modules: a title, three fixed links and a category toggle,
no count, no direction, no search and no feed link, while the RSS channel named the engine and was never cached. The
list now wears the face of the stand page `public/demo/nh-13-calm.html`, chosen by the owner among thirteen. The
reference is `docs/NODE.md`, "List header", "Views of a list", "Live list", "Search" and "RSS", and
`docs/TEMPLATES.md`, "Node List Contract".

- **The header.** `partials/node/head.html` from `getNodeHead()`: the title with the count "19 из 97 материалов" in the
  plural form of the locale (`getPluralForm()`), the view switch, the search field, the RSS button; the windows of the
  categories and the letters with their counts, the sort window with "С прошлого визита" and "В избранном" for a
  signed-in visitor, the direction pill, the reset, "Добавить". The bar sticks while the list scrolls; on a narrow bar
  a lens opens the search over it. Every control is a link or a form the server answers; a sort, the direction and a personal filter keep the other
  filters.
- **Counts that cost nothing on a warm request.** `getNodeCountMap()` keeps one file per type, an entry per set of
  readable categories, until a write, a new type version or the next scheduled publication or expiry.
- **Views by type.** `list.views` offers cards, compact, list, accordion, tiles and a rail; the shipped `news` offers
  cards, compact and tiles, `docs` list and rail, `faq` accordion and list. The visitor's choice is a cookie
  per type set by a POST with a token; a list by publication is headed month by month.
- **A live list.** The links of the header and the pager swap the part through htmx and push the address, an
  `HX-Request` gets the part alone with its title, back and forward ask the part again; "Показать ещё" appends the next
  page and the address takes its `num`.
- **Search hints.** Up to five titles of the type under the field after 300 ms, each a link to the material, from the
  light route `go=1&op=getNodeHint` under the read rights of the visitor; the arrows, Enter and Esc work the list.
- **The feed.** The channel names the site, the type and the category, links the list it mirrors, dates itself by the
  newest item, names the site in the copyright, answers 404 for a closed category and 304 to a reader that holds its
  tag, cached private with revalidation instead of no-store.
- **Breaking:** `config/node.php` needs `node.defaults.list.views` (`[]`), without it every type is refused at `list`.
  A theme's `partials/node/list.html` receives `head_html` instead of `navi_html`, `groups` and `rail` instead of
  `items_html`, and the categories and letters inside the header; `getNodeNavi()`, `partials/categories.html`,
  `partials/node/media/list.html` and the category toggle of `fragments/link.html` are gone. A POST to a Node list
  answers 303 instead of 405. `sl-pmf-day` is `sl-date-head` (the token keeps the name `--sl-pmf-day-bg`),
  `sl-pmf-split`, `sl-pmf-aside`, `sl-pmf-view` are `sl-duo`, `sl-duo-aside`, `sl-duo-main`. `_PRSORT` and the `_SORT`
  of `admin/lang/` are one `_SORT` of `lang/`, `_ASC` and `_DESC` moved there too, `_CATVORH` is gone. The title of a
  site page is `getHeadTitle()` in place of the code inside `setHead()`.

## 2026-10-09

### Plain Plus and CodeMirror share one editor shell, and CodeMirror is also a text editor to choose

CodeMirror served the code screens of the panel as one 240 KB bundle with One Dark painted over the theme, and Plain
Plus was a bare textarea. Both now stand in one shell with one runtime, and CodeMirror is a fifth text editor beside
Plain Plus, Toast UI, TinyMCE and CKEditor. The reference is `docs/EDITORS.md`; the fragments are in
`docs/TEMPLATES.md`, "Editor Fragments".

- **CodeMirror in two roles.** Its manifest declares `type` `["content", "code"]`, which a manifest may now give as one
  string or a list; it stores Markdown as a text, as Toast UI does, so a site moves between the two without touching a
  stored text. It is offered to members and administrators and given to nobody who did not choose it.
- **Painted by the theme.** `classHighlighter` writes `tok-*` classes that both `theme.css` colour, so code follows the
  colour scheme of the page; One Dark is gone. Code everywhere, the code blocks of the parser included, reads
  `--sl-face-mono` and the token tones; a `[code]`, `[php]` or fenced block carries its language, a copy button and a
  fold above 25 lines.
- **Loaded by need.** The build writes `core.js`, one `lang-*.js` per language and shared chunks; the runtime imports the
  core and one grammar when a frame comes near the screen: 133 KB gzip for the core, 134 to 226 KB with a language,
  against 240 KB for every code screen before.
- **One shell.** `editor-frame.html` and `editor-kit.html`, byte-identical in both themes, and `plugins/system/editor.js`:
  the format tab, the capsule, the status line with the caret, the size and what the column has left, the dirty mark,
  full screen, wrap, copy, reset, undo, a draft of the tab in `sessionStorage`, and a palette on Ctrl+K.
- **Text abilities.** The file window and the emoji panel serve Plain Plus and CodeMirror as text through an adapter;
  a pasted or dropped image goes to the upload; Markdown commands and lists continued by Enter on a Markdown field.
- **What the page tells the editor.** `vars` turns on the variable list, the hint and the marks; `lint` the check of a
  template with its badge, list and fixes, and "тег на строку" and "в одну строку"; `preview` a sandboxed preview of a
  template with bundled samples or of a text through the new route `getEditorPreview` (`go=1` and `go=5`, POST with a
  token). The upload templates of `tplconfig` pass all three.
- **Comparison.** "изменено" opens the loaded text against the present one, together or side by side, and "Вернуть было";
  a long text is aligned by lines first, so a text of eighty kilobytes with edits in three places opens in about a
  tenth of a second.
- **Breaking:** `cm6.bundle.js`, `build/entry.js` and the global `CM6` with `CM6.editors` and `CM6.oneDark` are gone; a
  script of a screen reads and writes an editor through `window.SlaedEditor`. The mount class `sl-code-editor` is
  `sl-editor`. `isValidEditor()` of `admin/index.php` is gone, its callers use `Editor::isValidEditor()`. The emoji panel
  left the Toast UI plugin: `SlaedEmoji` in `plugins/system/emoji.js`, its words in `plugins/system/emoji/<locale>.js`,
  the partial `emoji-panel`; the plugin's `assets/i18n/emoji-*.js` are gone. `_TPINFO` left the language files for ten
  `_UPLOADS_*` constants. `plugins/highlightjs/slaed-theme.css` and its `css_f` default are gone; the theme styles
  highlighted code.

## 2026-10-07

### A saved text keeps its line breaks in every editor format

`getTplTextarea()` deleted every `<br>` of a stored value before an editor saw it, for every format but `html`. The
`plain` save put them back through `nl2br()`; the `markdown` save of Toast UI, the editor of the site, did not. Every
member who saved anything on the account settings lost every line break of the signature and the custom menu, which
both show on every page: one save took the 22 breaks of a 1475-byte menu.

- **The mount.** A stored `<br>`, with the line end after it, mounts as a line end for `plain` and as a Markdown hard
  break, two spaces and a line end, for `markdown`; `html` mounts the value as it is. Measured in the browser through
  one save of the account settings: the menu keeps its 22 breaks as hard breaks and a second save changes no byte.
- **Stored form.** A value saved in `markdown` holds two spaces where it held `<br>`, two bytes less per break; every
  render of the parser, the escaped one of comments and the forum included, draws them as `<br>`, where an escaped
  render used to print a stored `<br>` as text. A line of nothing but a `<br>` becomes an empty line, so the lines
  around it render as two paragraphs.
- **Test.** `EditorBreakTest` drives stored values through the shipped mount, the browser submit and the save of both
  formats. The reference is `docs/EDITORS.md`, "Stored Text on Mount and Save".

### The private part of an installation is out of web reach, proves it every hour, and no journal holds a secret

The whole project sat in the document root and kept its private part with 95 `.htaccess` files of `deny from all`,
which nginx never reads; the login journal held the first 25 characters of every refused password and the request
journal the whole request with cookies and session. One server misconfiguration was a credential leak, and the
installation had no way to notice. The current state is in `docs/ARCHITECTURE.md`, "Private Data Boundary".

- **No secret in a journal.** `addLoginReport()` records who tried, under which login, from where and when, and
  rotates before it opens the file. The request journal is the channel `request` of `Logger` with its masking and
  only the names of cookies and session keys; `addLog()` and `getVariablesInfo()` are gone.
- **The document root is `public/`.** The entries, `.htaccess`, `robots.txt`, `favicon.ico`, `error.html`, the
  sitemap, `templates/`, `plugins/`, `sound/` and `demo/` are in it; the code, `config/`, `storage/` and the one
  upload root `uploads/` stay at the project level. Every entry defines `BASE_DIR` and `PUBLIC_DIR`, and no path relies
  on the working directory. A request under `/uploads/` reaches the light path of `core/stream.php`, which serves a
  public folder before the core boots and answers 410 for everything else; `getFileStream()` moved there with it and
  learned the public mode.
- **Two modes.** A root on `public/`, or on the project with the `.htaccess` of the project rewriting into `public/`
  and refusing everything without `mod_rewrite`; the installer records `webroot`. `nginx.conf.example` is the server
  block of the first mode, and `NginxConfigTest` keeps it refusing what `public/.htaccess` refuses. The help of the
  security section names the variants of shared hosting.
- **Guards gone.** Every `.htaccess` outside `public/` but the one of the project and every guard `index.html` outside
  it left the tree with their writers (`NodeService::setTypeGuards()`, `FileManager::getGuardFiles()`, the guard
  writer of `CaptchaStore`); the upload service creates a missing folder of its owner on the first write.
  `setup_old/` left the tree.
- **The self-check.** A marker `check.txt` in the project, `storage/`, `config/`, `uploads/` and `admin/info/`, asked
  over the site address every hour by the job `selfcheck` and judged by the body: `open`, `closed` or `unknown`. The
  home of the panel warns about a problem or a missing verdict, the security section shows the all-clear too, and a
  Node type is switched on only while `uploads/` is closed.
- **Journal names.** `error_*.log` is what failed, `<meaning>.log` what happened: `admin.log`, `user.log`,
  `oauth.log`, `request.log`, `filescan.log`, `filescan_tree.log`, `filescan.json`, and the new `file.log` for file
  operations below `error`. `Logger` and `addCompress()` name an archive `<name>_<date>.log.<ext>`; the locks moved into
  `storage/cache/locks/`. The dashboard error counter reads the structured lines and counts the problem levels, so it
  no longer reports zero whatever the journals hold.
- **Breaking:** the document root moves to `public/`; `addLoginReport()` lost its password parameter; `addLog()`,
  `getVariablesInfo()`, `FileManager::getGuardFiles()` and `NodeService::setTypeGuards()` are gone; the journals carry
  the names above and the label key `log` of the security section is `request`; `$conf['users']['adirectory']` is
  the folder name `avatars`. The journals of an existing installation are not renamed: 8.0 installs a new site.

### Every asset address carries the version of its content, the browser keeps it a year, and the head scripts run deferred

No static file carried a version, so a long browser lifetime would have kept an old file after a release. nginx sent
no `Cache-Control` at all and the `mod_expires` block of `public/.htaccess` never applied on the stand; a browser
guessed a tenth of a file's age, so right after a release every returning visitor asked for every file again, and a
file untouched for half a year stayed about eighteen days after it changed.

- **Versions.** Every printer of a stylesheet or script address goes through `Template::getAssetUrl()`:
  `theme.css?v=0a5af6e4ae`, ten hex characters of the SHA-1 of the file, from the map
  `$conf['derived']['version']` that `Template::getAssetVersions()` builds of `templates/` and `plugins/` with
  `config/local.php` (`cache_version` 5). A request stats no asset file; `dev_mode`, `setup.php` and `update.php` hash
  per request. The highlight versions are part of the parser cache key. The robots screen lost its second tag of
  `editor-robots.js`.
- **Lifetimes.** A style or script with `v=` in the query is kept `public, max-age=31536000, immutable`, any other
  static file `public, max-age=604800`, revalidated by `ETag`; pages stay `no-store`, the public uploads keep the day
  of the light path. `public/.htaccess` sets this through `mod_headers`, `nginx.conf.example` through a server-level
  `$asset_cache` and turns `gzip` on for the types the `.htaccess` deflates.
- **Defer.** `doScript()` prints every head script `defer` in the order of `getAssetList()`.
  `Editor::getInitScript()` waits for `DOMContentLoaded` while `window.SlaedEditors` is missing, so an editor of a
  page load registers its teardown.
- **Measured** on the stand, median of three runs, guest, start page / list / view against the baseline of the same
  day: a warm page costs one network request (the page) as before, now for a stated year instead of a guess; cold
  376 576 / 353 477 / 643 884 → 377 584 / 354 380 / 645 434 bytes (the `?v=` of every address), warm 30 449 /
  16 483 / 21 047 → 30 593 / 16 536 / 21 309 bytes, first paint warm 536 / 300 / 364 → 540 / 352 / 348 ms, within
  the spread of the runs. The current table is in `docs/PERFORMANCE.md`.
- **Breaking:** the settings `script_a` (async scripts) and `script_b` (scripts at the end of the page) left
  `config/global.php` and the settings screen, and `update.php` drops both from a carried 6.2 configuration. The
  `mod_expires` block left `public/.htaccess`; an Apache without `mod_headers` sends no lifetime. A theme or plugin
  file replaced by hand needs `config/local.php` rebuilt to reach the browsers.

### Retired addresses answer 404, 410 or 301, and the statistics append only what they are given

Two findings of the production logs of 2026-08-18 to 2026-08-21, neither of them the cause of that day's outage
(PHP-FPM had stopped, nginx answered 502).

- **A path that is no address answers 404.** The router read only the query, so `/news.html` of the long-retired
  `.html` scheme rendered the start page with 200 — 7058 requests in 2.9 days, almost all of them machines. Any path
  that reaches `index.php` other than the folder and its scripts now gets the standard 404 page; a real `.html` file
  is served by the server first, and a status an error document brings in `?error=` is kept. No table of the old
  scheme is built.
- **A path after a script answers 301.** `/index.php/index.php?name=sitemap` (1049 requests, from links Google
  keeps), `/index.php/` and `/admin.php/x` answer 301 to the script with the same query, or to the folder for a bare
  `index.php`. The target is built from the script name, which closes the open redirect of the old rule
  (`//other.host/index.php` sent `Location: //other.host/`); `nginx.conf.example` sends such a path to the script.
- **A retired file answers 410.** A missing file under `uploads/` and the direct address of a file of a closed owner.
- **`addFile()` writes data only.** It took its second argument as a source file or as the data and told them apart
  with `is_file()`. The visit statistics append an address or a user name followed by a comma, so every new visitor
  probed a path, which `open_basedir` logged as a warning (`File(141.148.184.29,) is not within the allowed path(s)`),
  and data that named an existing file would have appended the content of that file. `addFile(string $file, string
  $data, string $mode = 'w'): bool` writes or appends exactly the data; the compression branch had no caller and went
  with `$comp`, `$del` and `$max`.
- **Breaking:** `addFile()` returns `bool` instead of the codes `0`–`3` and drops its parameters `$comp`, `$del` and
  `$max`; a caller that copied a file reads it first.

### Every uploaded file leaves through the owner of the text that names it

Node closed its upload folders and served a file to the reader of its material; every other module linked its files
directly in open folders, so an attachment of a private message or of a post in a closed forum category reached
anyone who had its address. Every closed owner now has one route and one rights check (`docs/ARCHITECTURE.md`, "File
Delivery Boundary").

- **One route for every owner.** `index.php?go=file&own=<owner>&id=<target>&key=<name>` serves Node, the forum, the
  private messages, the comments on polls and profiles and the texts of an account; `FileAccess` asks the adapter of
  the owner whether the stored text carries the name and the reader may read the text. Every refusal is 404.
- **Closed folders.** `uploads/forum/`, `uploads/account/` (private messages alone), `uploads/voting/` and the new
  `uploads/profile/` (signature, own block, comments on a profile) answer 410 at their direct addresses, as
  `uploads/archive/` does; `uploads/all/`, `uploads/avatars/` and `uploads/presentation/` stay public.
- **Node under one root.** A type keeps its files in `uploads/node/<type>/` (`NODE_DIR`, `getUploadFolder()`), so a
  type can meet only another type there.
- **A signature renders the same everywhere,** in the file context of its account, on the forum, the profile, the
  private message and the comments; the comments printed its stored source unparsed until now.
- **No foreign file through a new text.** A writer binds a new `[attach]` name only when it is an own upload, the
  writer moderates the folder, or the same target already serves the name; a refusal names the file.
- **The rule governs the upload.** The extensions of an upload rule decide what may be uploaded, previewed and newly
  bound; a name a stored text carries is served whatever the rule says today.
- **Full-size attachments.** `[attach … size=full]` shows an image at its own size through the template `full` of
  `config/filetype.php`; the insert window of the editor offers "Thumbnail" or "Full size".
- **Unused files.** The file browser of the uploads screen filters a Node type, the forum, the private messages, the
  profile texts, the poll comments and the avatars to the files no stored row names, older than a day; the marking
  and the deletion of the browser remove them.
- **The update of `slaed.net`.** `update.php` rewrites stored texts and moves, copies and renames no file: a direct
  address of the own folder becomes an `[attach]`, a source inside `[usehtml]` the `go=file` address of its material,
  an address into the folder of another type or of the forum the `go=file` address of a published material or post
  that names the file. On a copy of the production dump of 2026-09-30, of the 721 file addresses of the stored texts
  657 became attachments, 24 `go=file` addresses, 9 point into public folders, and 31 answer 410, 21 because
  production does not hold the file and 10 by decision; the 1049 file references of the 231 pages that show them all
  answered 200. The upload folders move after the run; the steps are in `docs/NODE.md`, "The 6.3 update".
  `UPGRADING.md` keeps saying that the release installs a new site only.
- **Breaking:** `op=attach` of Node is gone; `uploads/forum/`, `uploads/account/`, `uploads/voting/`,
  `uploads/profile/` and `uploads/archive/` answer 410; `Parser::filterContent()` takes the file context `array $own`
  in place of `int $nid`; `uploads/jokes/`, `uploads/media/`, `uploads/pages/` and `uploads/node/files/temp/` left the
  shipped tree.

## 2026-10-05

### Every page is rendered live, and the ready-page cache leaves the system

A load test on the stand (nginx in front of eight PHP 8.4 FastCGI workers with opcache, 1000 guest requests per
page) showed the ready-page cache paying only on the lists of a type: 142 against 60 requests per second at a
concurrency of 16, 100 against 37 at 64. The material view (40 req/s) and the forum (47 req/s) were never stored,
a stored hit still cost about 56 ms of worker time, and a block printing a live token kept a page out of the cache
at random. The template, parser and data caches carry the expensive part of a page.

- **Gone with it:** the stored pages and their route contract, the signed dynamic markers of tokens, captcha and
  polls, the write guard around every content write, the cache generation moved by every database write, the
  deadline of a Node list and the single-flight rebuild.
- **Settings:** `cache` is an on/off switch of the parser cache; `cache_b`, `cache_l` and `cache_t` are gone; the
  `cachegc` job, now titled "Cache cleanup", removes files not rewritten for a day (`Cache::KEEP`).
- **Styles and scripts are linked one file at a time.** Joining them into bundles (`cache_css`, `cache_script`),
  inlining them into the page (`css_h`, `script_h`), compressing CSS (`css_c`) and embedding images into it as data
  URIs (`css_e`) are gone with the route `go=asset`: a browser keeps every file on its own, and one changed file no
  longer invalidates the rest.
- **Folders:** the caches live in `storage/cache/data/`; `storage/cache/pages/`, the guard journal and
  `storage/counter/cache.log` are no longer written. The changelog entries and the OAuth key sets moved from
  `storage/cache/changelog/` and `storage/cache/jwks_*.json` into the same data cache, so the cleanup job reaches
  them too.
- **One cache API:** `Cache::getFile()` names a stored entry, `Cache::deleteStale()` sweeps a directory, and
  `Cache::setHeaders()` takes the mode of the answer, `none`, `private` or `public`; the duplicate header
  `X-Powered-CMS` is gone. The query allowlist of an editor attachment lives in Node and refuses a tracking
  parameter like any other unknown key. One `getAssetList()` builds the linked styles and scripts of a theme.
- **The category map** is dropped explicitly by every writer of a category title, parent, order or icon.
- **Presentation:** the request path shows the parser cache, the compiled templates, the category map and the page
  built live.
- **Breaking:** `NodeException::BLOCKED`, the quick-edit result `blocked`, `getPageToken()`, `getPageCaptcha()`,
  `NodeQuery::getNodeDeadline()` and `setHead()` with a closure are gone; `Cache::getPath()`, `Cache::getHash()`,
  `Cache::getQueryVars()`, `Cache::setPrivateHeaders()` and `getAssetFiles()` are replaced as above.

## 2026-10-04

### One quick edit for comments, forum posts and Node materials

The quick edit of a comment and of a forum post were two separate paths over one helper, and Node had none. They
are one protocol now (`QuickEdit`, `getQuickService()`, the routes `getQuickEdit` and `updateQuickEdit`; see
`docs/ARCHITECTURE.md`, "Quick edit").

- **A refusal keeps the typed text.** Every refusal answers its own status and is told on the warning toast; the
  editor stays. An expired session answers 403 instead of an alert swapped over the text.
- **No silent overwrite.** A save carries the stamp the editor was opened with; a text changed meanwhile answers a
  conflict and asks once whether to overwrite. The repetition of a save whose answer was lost is saved again
  without a second write.
- **Cancel needs no request** and asks before it drops unsaved changes; Escape cancels, Ctrl+Enter saves.
- **The edited mark follows the save** for comments and forum posts.
- **The forum** writes under the write guard, with the topic and the right read again under the lock, so a topic
  closed or a post moved after the editor opened refuses the author; the page cache moves only after a write. The
  route `op=updatePost`, the route `op=updateComment` and `getTplAjaxTextarea()` are gone.
- **Node** offers the quick edit of the intro and the body on the public page of a type without an extension: to
  its moderator before the full editor, and to the signed-in author of a pending or published material for
  `limits.edit` seconds after creation (new key, default `600`, `0` off, on the limits tab; `update.php` adds it to
  an existing `config/node.php`). An author edit of a published material sends it back to moderation in the same
  write, unless the author publishes directly; the matrix of states gains `Published → Pending`.
- **Editor assets load once per page.** A fragment no longer carries the engine of an editor again: the client
  loader adds only what the page lacks, the init waits for the engine, and an editor swapped away is destroyed. On a
  page without an editor of its own the quick editor used to stay hidden.
- **Breaking:** `Comment::updateComment()` takes the stamp and answers a result code; `NodeException` gains
  `BLOCKED` (7, HTTP 503) for a closed write guard, which `setNodeWrite()` used to report as `STORAGE`.

## 2026-10-01

### One file installs a new site and deletes itself, and the 6.2 update leaves the release

The installer is one file, `setup.php`, and installs a new site only. The `setup/` directory, its key
`config/setup.unlock` and its update branches are gone; the update files of 4.1 to 6.2 are dropped.

- **Seven stops on the login card of the admin theme:** language, server checks, database with a probe, site,
  administrator, the run and the closing stop. Markup, styles and motion belong to the admin theme, the texts to the
  `_SETUP_*` constants of `admin/lang/*.php`.
- **The run in parts.** Configuration, the tables in groups, every seed statement and the administrator are separate
  requests, so the progress line shows how far the server really got. A part that broke its request off fails the
  run instead of running twice.
- **The first administrator** is created by the installer itself, with the ten Node types and the starter news.
  The form of `admin.php` for an empty admins table stays as the way back into the panel.
- **CSRF** is a random token in the session of the browser that runs the installation, carried to the end.
- **An installed site** — its database holds an administrator or does not answer — is refused without a write or a
  delete. The installer deletes itself only at the end of its own installation; a file that stays keeps the warning
  of the panel.
- **The language** chosen on the first stop reaches the panel: the installer sets the core cookie
  `{user_c}-language`, not the old `{user_c}-lang`.
- **The schema** moves to `storage/update/sql/`: `table.sql`, `insert.sql` and `table_update6_3.sql`.
- **The 6.2 update** moves into `update.php`, an internal tool of the maintainer that the release does not ship. A
  6.2 site is not updated by the release.
- **An administrator password** is hashed as typed in the installer, the recovery form and the admins module; the
  login still opens with a hash made from the old cut and escaped form. The recovery form carries a token.

### The migration of the old modules leaves one copy of every file, and every migrated image shows

A rehearsal on a copy of the production site found four faults of `update.php`, each fixed:

- **A stopped run.** The empty `index.html` placeholders of the old upload folders stayed in place, and Node took them
  for user files and refused the new type. Only the guard files of the release stay now.
- **Images replaced by `#`.** The converter wrote images and links as bare relative addresses like `uploads/x.png`,
  which the safe parser of Node replaces with `#`, so no migrated image showed. They now gain `./`. The
  entity-quoted attributes of old HTML are decoded first.
- **Duplicate files.** Every file went back into the closed type folder, and directly linked ones were copied into
  `uploads/archive/` as well, which left 270 duplicates. A file now has one place: the type folder holds what the
  materials use, the archive what the site links to directly. A file nothing uses stays outside the site in the
  working directory.
- **Broken outside links.** The forum, comments, private messages, newsletters, blocks, signatures and polls kept
  links into the folders Node closes. They are pointed at the archive as well, written as `./uploads/archive/...`.

### A comment of a Node material shows its attachments

A file uploaded into a comment of a material landed in the closed folder of the type and was linked there
directly, so it answered 403. A comment of a Node type now renders with the id of its material, and
`op=attach` grants the names its published comments carry, every comment not deleted for a moderator, with the
rights of the material. The preview of a fresh upload is open to whoever may upload into the type. The migration
keeps the attachments of comments and help replies in the type folder for the same route, so a private help
request keeps its files private.

The menu and footer of the lite theme link to `docs`, not to the removed `pages`.

## 2026-09-30

### The content of the removed modules moves into Node, and their old addresses follow it

`update.php` in the root carries `news`, `pages`, `faq`, `help`, `links`, `files` and `content` of a site that
ran the 6.3 update into Node types - `pages` into `docs`, `content` into a type without extension - with
categories, comments, favorites, rating balances, resources and files; the old tables stay. Only the main
administrator runs it, a stopped run continues from its manifest, and texts are converted from the trusted HTML
the old modules rendered into BB and Markdown. The new table `_node_legacy` maps every old address to its
material; the counter of `_nodes` continues above every old id, so an old address never names a new material,
and it answers 301 to the migrated material or list where it would otherwise meet a 404. A migrated material earns
no second publication award when a moderator publishes it again, since its module rewarded it once already.

The views and cards of Node show what the old modules showed: the share, author and moderator speed dials and the
anchor of the material, the chips of comments, author and downloads, favorites, rating, the category icon and the
fresh mark. The moderator dial of the site and the one of the panel list come from one function.

A new type of one of the nine replaced names now takes over the upload rule its old module left in
`config/uploads.php`, where it used to be refused as a taken name.

The category tiles of a Node list show the description of every category, as the old modules did;
`setCategories()` lost the two switches its one caller always set the same way.

### A forum post is acted on under the rights of the category it is stored in

The quick edit, the full edit, the reply, the deletion and the moderator actions of the forum took the rights of
the category the request named, so the moderator of one category could edit, delete, close or move the posts of
every other one. `getForumPlace()` now reads the category, the topic and the author of a named post from its rows,
and `checkForumRight()` is the one right over it that the handlers and the buttons of the view share: the
moderator of its category, or its signed-in author with the right of the category while the topic is open. A
reply always answers the topic of the post it names and needs the reply right, a guest no longer matches a post
written without an account, and a refused quick edit shows its alert instead of an empty body.

### A thumb vote bursts the thumb it pressed

The thumbs of the shared rating - a forum post, a profile, the rating under an avatar in the forum and the
comments, the user list - answer a counted vote with the burst of the favourite star, in the green or red their
hover promised.

### Code blocks are escaped in every highlighter mode

With `syntax = 2`, the shipped default, `[php]` and `[code=language]` handed their content to the page as
markup, so any author of a comment, a forum post or a material could store a script. The renderer moved into
the parser as `Parser::getCodeHtml()`; it decodes the entities of stored legacy text once and escapes in every
mode, drops only the editor line breaks before a line end instead of everything between the first `<br` and the
last `>`, and the highlighter mode joined the fingerprint of the parser cache.

## 2026-09-29

### The shop, order and service modules leave the package

`shop`, `clients`, `order`, `money`, `auto_links` and `whois` are no longer shipped: their module directories,
the block `auto_links`, their configuration files, upload directories, templates, language constants and tables
in `setup/sql/table.sql` are gone, and so is every hook the core kept for them — the cart route `go=2`, the
shop scope of comments, ratings and favorites, the points action `order`, the extra-field area `order`, the shop
feed, sitemap section and search source, the newsletter audiences of the three client lists, and the
administration counters of new clients, partners and whois requests. The referer log no longer links a visitor to
a link of `auto_links`, so `_referer.lid` is dropped, and the unread setting `amod` is gone from the global
configuration.

The `update6_3` branch leaves the tables and upload directories of these modules on an updated site untouched:
`table_update6_3.sql` no longer normalizes them, the ratings unit counts 6.2 shop rules as dropped, and the fields
unit converts account and forum fields only.

## 2026-09-24

### Node replaces nine content modules, and the 6.3 update carries a real 6.2 site over

`news`, `pages`, `faq`, `help`, `jokes`, `content`, `links`, `files` and `media` left the tree; one module,
Node, serves typed content in their place. A type is a row of `_node_types` plus its settings in
`config/node.php`, its extra fields in `fields.node`, its upload rule and its rating rule; `NodeQuery`
reads with the category rights of the visitor, `NodeService` writes in one transaction with a version
check, and two extensions carry the special cases: `support` for private requests of `help`, `sync` for
the external sources of `content`. Files of a type are served only through its controlled route, so a
type is switched on only when the web server refuses its upload directory. Ten shipped profiles in
`modules/node/profiles/` — the nine replacements and the new hierarchical `docs` — become active types
when the first administrator of a clean installation is created. Points, ratings and extra fields became
the shared classes `Point`, `Rating` and `Field`, and `Feed` fetches RSS and Atom for blocks and `sync`.

The `update6_3` branch of the installer closes the site, checks server version and table engines, runs
`table_update6_3.sql` and the resumable units points, ratings and fields with manifests under
`storage/backup/update/`, and leaves the marks in `config/update.php`. It now also reconciles
`config/modules.php` the way the modules screen does — the records of the removed modules go, `node`
gets the record of a clean installation — and drops the upload rules of the removed modules, which used
to refuse a Node type of the same name. It creates no types and touches no table of the removed modules.
Run on a dump of the stand taken before the data units, the fields preflight stopped on one order row it
could not map, wrote nothing, and finished after that row was corrected; the upgraded schema matches a
clean installation table for table.

The installer no longer needs `config/db.php` to exist, since the file is not shipped. MySQL 8.0 runs the
release: the columns `_users.rank` and `_groups.rank` are quoted wherever a query names them bare, because
`rank` is reserved there. The administrative material list of Node reads without the category,
relation and resource sets it never showed (`NodeQuery::setNodeSets(false)`), three statements a page.

`php tools/node-profile.php` builds a disposable database of 100000 materials in ten types with 200
categories, relations and resources, measures every route budget against `docs/NODE.md` (Performance), the p50 and p95
time and the plans of the main statements, and fails on a budget overrun or a full scan.

## 2026-09-11

### The presentation module renders the etalon home page from live figures, and the start page listens to its module

The etalon `demo/22-dashboard-layout-etalon.html` became `modules/presentation`: twelve
sections in a fixed order, each a partial of the `lite` theme with its repeated card as a
fragment, dressed by one stylesheet `assets/css/presentation.css`, moved by one plugin
`plugins/presentation/presentation.js`, and fed by PHP that hands over data and `is_*`/`has_*`
flags only. Every figure on the page has a source in the system: the request timing and
the memory come from `getLoadStats()` against the limits of the new `getLoadLimits()`, the
online figures from `getSessionCounts()`, today's visits and the day rows from
`getStatsToday()` and `getStatsDays()`, the table counts from `getTableCount()`, the commits
from the changelog module, the cache state from the cache config. Sites, brand materials,
principles and testimonials come from `config/presentation.php`, whose dictionary fields
name `_PRES_*` constants in six locales while proper names and quotes stay raw text.
The seventy-one site cards carry no address and open no window; the brand tiles open the
site lightbox through `a[data-sl-shot-open]`, the hook that replaced the dead `a.site-link`.

The server metrics left the admin monitor for `core/monitor.php`, and the scheduler samples
them once a minute through the `monitor` job at the lowest priority, so the public page
never runs `exec()` on a visit: it reads `storage/logs/monitor.json` and hides the section
while the sample is missing or older than five minutes. The guard section shows counts of
the last twenty-four hours and six static legend lines; the tail of the security logs stays
in the admin panel.

The start page now reads the `side` and `top` positions of its module like every named
route, so the block columns of the home page are set in `admin.php?name=modules`. This is a
breaking change for a site whose start module is `news` with `side = 2`: the empty value
used to mean both columns, and the left one disappears until the setting says `0`. A module
that prints its own `h1` raises `has_own_title` and `layouts/home.html` drops the site name
heading, so the page keeps one `h1` on either route.

The micro font step reads 11 px in both themes and the contract ladder follows: the
monospace labels of the page were unreadable at 10, and every page reading `--sl-font-micro`
grew by the same pixel. `setSpyRail()` gained the pixel mode of the presentation rail
(`data-sl-spy="px"`, `--sl-d-rail`), and the screenshot rig captures the presentation page
on its own, masks the shuffled site strip, bounds its wait for lazy images and pauses SMIL
animations before a shot.

## 2026-08-07

### One window and one icon insert an image, and a denied upload no longer means an unbounded one

The image dialog and the file catalogue are one window behind one toolbar icon. The
vendor image popup is removed from the toolbar, and what replaces it is our own markup:
the image address with its description, the file picker with its drag target, the three
insert modes, the stored-file list and the limits block, all siblings in one window
instead of two windows held together by measured geometry. Nothing in the editor reaches
into a popup it does not own any more, and the link dialog and the emoji window are
untouched.

The icon is there for every visitor. Inserting an image by its address touches no file
and no server, so it is available to everyone, always — and a visitor allowed nothing
beyond that opens the same window and finds the address field in it, which is an honest
answer where a missing icon would not be one.

A visitor who may not upload no longer gets a *worse* path than one who may. Dragging an
image onto the editor, pasting one from the clipboard and picking one in the window all
go through one bounded path from this release on: at most `Parser::EMBEDMAX` and only a
type the parser will draw. Until now the editor left that event to the vendor default
whenever uploading was denied, and that default base64-encoded any picked file straight
into the body — no extension check, no size check, no dimension check — so a
three-megabyte photo became four megabytes of base64 in the row and then did not display
at all.

### Whether a field may hold an embedded image is a property of the field

Every editor call site now names where its text is stored, and one table turns that name
into the room the storage has. A field wide enough for a whole embedded image offers the
embed mode; a summary, a signature or a link description does not, and offers the
address field instead. That is not a restriction on graphics: a linked image is accepted
in all of them, and it is the better delivery there anyway, because a list page draws the
summary of twenty rows and the browser fetches a linked image once instead of re-sending
a base64 copy twenty times.

A call site that names no storage renders a working editor with the room of `TEXT` and no
embed mode, so a forgotten one costs its author a button and never costs them a post.

The five image types the parser will draw have one definition, `Parser::EMBEDIMG`. The
render bound, the upload adapter and the editor read it from there, and a test fails if
they disagree.

### No stored text is longer than the column that holds it

One guard measures a finished text against the room its field declares, before the query
runs, and refuses with a message instead of letting the database answer `ERROR 1406` and
the author lose the post. It measures bytes and not characters, because bytes are what a
column bounds — in utf8mb4 a Cyrillic letter costs two of them, so a `TEXT` column runs
out at about 32700 Russian characters rather than 65535. That holds for plain prose as
much as for an embedded image: 70 KB of text in a news article was refused by the
database before this release and is refused with a message now.

The same guard measures what a text embeds, so the field rule is enforced and not merely
offered. A field that may not hold a data URI refuses one at any size; a field that may
refuses one over `Parser::EMBEDMAX` or of a type the parser will not draw. The editor
still refuses first, so an author is told at the moment they act, and a request that
never rendered an editor is told the same thing.

The one writer into a guarded column that has no author to tell is the content module: an
item with a feed address rewrites its body from whatever the far end served. A feed that
does not fit leaves the stored body untouched and writes the reason to the site log, since
a stale item is a better answer to a visitor than a lost one.

### The editor file panel is answered by the settings, not by the role

A guest allowed to upload now sees a file list. Until this release the editor listing
route refused every visitor who was not logged in, whatever
`admin.php?name=uploads&op=config` said, so a guest could upload a file under
`guestupload` and never see it again — not even their own, not even once. That rule is
gone: whether a list is answered at all is decided by the module upload settings and by
the module moderator, which is the one role standing above them.

A guest is shown the files of their own session and no other guest's, which rests on the
owner token below. The next session is a new session, and the files of the old one are no
longer listed.

The listing limit is chosen from three values instead of two. A moderator is bounded by
`moderfiles`, a member by `userfiles` and a guest by `guestfiles` — the field the release
below adds, which this one gives something to bound. Which of the three applies is the
only role question left on the route; what each of them is worth is a setting.

### A stored file belongs to a token, so one guest is no longer every guest

The owner segment of a stored upload name — the `-42` in `news-a1b2c3d4e5-42.png` — is an
alphanumeric token instead of a number. A member still owns their files by their user id
and a privileged upload still carries no owner segment at all, but a guest now owns theirs
by a token derived from the session rather than by the shared `0` every guest carried. Two
guests are therefore two owners, which is what a per-guest file list has to rest on.

The token is derived from the session and is never the session id itself, because the
segment ends up in a public file name and must authenticate nothing when it is read off a
URL. It lasts as long as the session: the next session is a new one, and the files of the
old one are no longer listed.

Files stored under the old pattern keep resolving, because digits are still a valid token,
and nothing already in `uploads/` is renamed or moved.

The per-guest file list above rests on this token: without it every guest would carry the
same owner and would see the uploads of every other guest.

### A link description is no longer one sentence, and the guest file list gets its own limit

`{prefix}_auto_links.intro` moves from `VARCHAR(255)` to `TEXT`. Those 255 bytes are
about 127 Cyrillic characters in utf8mb4 — one sentence for a field the site asks a
visitor to describe a whole site in. It joins the summary class rather than the body
class and stays `TEXT`: a link description is drawn once per row of a link list, which
is exactly the shape that must not carry an embedded image. It is the last column of
the schema work below that had not moved yet, and with it every column a rich editor
writes into is `TEXT` or `MEDIUMTEXT` and no `VARCHAR` sits behind an editor.

**Deployment:** a 6.2 upgrade and a fresh install carry the column already. No data is
lost or rewritten — the column only gets wider, and no stored description changes.

The upload rule of a module gains a thirteenth field, `guestfiles`, with its own input
on `admin.php?name=uploads&op=config` beside the two upload switches. A rule stored
before this release is one field short and keeps working untouched: the missing position
answers the user limit rather than zero, because zero means *no limit* to the reader and
would hand an unbounded list to the one role that never had one. The first save
normalises the rule to the full field order without changing a value it already carried,
and every save after that reproduces it.

The limit bounds the guest file list described above.

## 2026-08-05

### Private messages store source, and both fields are rendered safe

A private message is the source its author wrote from this release on. The body is
rendered by the parser with `safe = true`, the title is plain text escaped where a
template prints it, and no stored value is trusted HTML any more. **This release follows the state-model release below it and
must not be deployed before that one is live**, because it reads the columns that
release adds.

> **Superseded on 2026-08-06 by the content contract.** This entry originally
> shipped a `format` column on `{prefix}_privat` and a mandatory
> `tools/privat-migrate.php` run that rewrote stored bodies in place. Neither
> exists any more: the column is gone from all three SQL channels, the tool is
> deleted, and no text migration is part of any release. What survives is the part
> below — a message is stored as source and rendered safe — and it needs no
> conversion pass to be true.

A body is read through one contract. `plain` and the editors are input interfaces,
not storage formats, and nothing in the rendering branches on the editor an author
happened to type in. That is why no column names a syntax and why there is nothing
to classify: the same stored bytes render the same way whichever editor wrote them.

No deployment step is needed for this beyond the runtime code itself. The schema
work belongs to the state-model entry below, which stays exactly as it was.

What changes for the people using the site:

- A message is stored as it was written and escaped when it is read. Markup a
  sender types is text on the recipient's screen instead of live HTML, while
  Markdown, the bracket tags and the smilies still render.
- The editor an author writes in is an input interface and nothing more. An HTML
  editor is not a trust grant either: its markup is stored as source and escaped
  like any other, and the reading side never asks which editor produced a message.
- The subject line is plain text now. It carries no markup, it is stored decoded,
  and the template escapes it where it prints it — in the mailbox list, in its
  attribute and in the administrator panel alike. The panel used to decode it back
  on read to compensate for a writer that no longer exists.
- An administrator reads a message body through the same safe renderer its
  recipient does. Access to private-message contents in the `privat` section is a
  deliberate system policy, super-administrator only as before, and no raw stored
  body reaches a template on that path.
- One limitation the state model cannot repair either: a message
  either side deleted while both sides still shared one row is gone from the
  database and cannot be reconstructed for the other participant. That is recorded
  rather than papered over with a placeholder message.

### Private messages: four independent states instead of one shared column

The private-message subsystem is now one class, `Privat`, and one message carries
four state columns instead of the single `status` it shared between both
participants. **The schema section and the runtime code of this release are
deployed together.** An installation that applies the section while still running
code that reads `status` answers an SQL error on every private-message page until
the code follows it, and code deployed before the section does the same in the
other direction.

Which file an installation needs depends on where it comes from, and it is
exactly one of the two:

| Coming from | File | How |
|---|---|---|
| a new installation | `setup/sql/table.sql` | the installer, nothing to do |
| 6.2 | `setup/sql/table_update6_3.sql` | the installer, per the section below |

What the upgrade does to `{prefix}_privat`: it adds `saved`, `delin` and `delout`,
carries the saved messages over from `status` before that column is renamed to
`viewed`, forces `viewed` onto `TINYINT UNSIGNED NOT NULL DEFAULT 0` — the old
declaration was `BOOLEAN` while the code stored `2` — and replaces the three
single-column keys `uidin`, `uidout` and `status` with the composites
`in_box`, `in_new`, `out_box`, `out_new` and `flood`. It deletes no row and no
message, and the row count it starts from is the row count it ends on. It is safe
to run twice, and safe to run again after a crash: a re-run reads the shape the
table is really in and finishes only what is missing. Both files end on the same
table definition, byte for byte.

Building five indexes rewrites the table, and InnoDB holds the rows while it
does. On a large private-message table that is a maintenance window rather than a
page reload — the same rule the comment upgrade below already carries.

One documented consequence of the conversion: a message that was saved under the
old model becomes `viewed = 1`, because `status = 2` carried no read bit of its
own. Saving required opening the message, so read is the correct assumption.

What changes for the people using the site:

- A recipient deleting a message no longer removes it from the sender's outbox,
  and the reverse. Until now one delete destroyed the single shared row.
- A message the recipient saved stays visible in the sender's outbox, where it
  used to vanish the moment it was saved.
- A sender may delete an outgoing message the recipient has already read. It used
  to stay in the outbox forever.
- Saving no longer discards the read state, and a saved message no longer becomes
  undeletable for the sender.
- Read and unread are two actions now, not one transition, and inbox, saved and
  outbox carry bulk read, unread, save and delete.
- A send is refused when the saved folder of the recipient is full and not only
  when their inbox is, so the mailbox a message cannot fit into is the whole
  mailbox. Both quotas and the send interval are rechecked inside the write
  transaction, under a lock on both accounts, so two simultaneous sends can no
  longer both take the last free place.
- The notification mail reads the `psmail` preference of the recipient, which is
  the setting the account page has always offered and which had no effect until
  now — the code read the forum preference `fsmail` instead. Its link carries the
  id of the message that was really stored.
- Deleting an account now cleans its mailboxes in the same transaction that
  deletes the account, on both paths that delete a user row. The counterpart keeps
  a readable copy with the gone account rendered as an anonymous sender.
- The second administrator delete route, a GET carrying its token in the address,
  is gone. One POST route remains, super-administrator only as before.

## 2026-07-29

### Upgrade 6.2 → 6.3 must go through the installer, not through the SQL page

`setup/sql/table_update6_3.sql` is **not the whole upgrade**. Five steps of the 6.2 → 6.3
migration live in PHP, in the `update6_3` branch of `setup/index.php`, and pasting the
SQL file into **Database → Inquiry** performs none of them. The schema will look
correct — an upgrade of a real 6.2 database was compared against a fresh
`setup/sql/table.sql` install, table by table: 38 tables, 494 columns, 171 indexes,
engine and collation, zero differences, and the file is idempotent — but the data
around it will not be.

What the SQL file alone does **not** do:

1. **Administrator module permissions stay numeric and stop matching.** In 6.2 the
   `modules` column of `{prefix}_admins` held numeric ids from the `{prefix}_modules`
   table; 6.3 stores module names. `getAdminModuleNames()` (`core/system.php:998`)
   splits the column and does not translate ids, so every administrator who is not a
   super administrator silently loses **all** module permissions, comment moderation
   included. Only the installer rewrites the column.
2. **`config/modules.php` is not rebuilt.** 6.3 moves the module registry out of the
   `{prefix}_modules` table into that config file. The installer scans
   `admin/modules/*.php` and `modules/*`, folds in the old table and the existing
   file, and writes it.
3. **Pending newsletter recipients are lost.** The upgrade drops
   `{prefix}_newsletter.mails` (line 1696 of the SQL file), and it is the installer
   that reads those addresses first and writes them into the new mail queue
   afterwards. The SQL file does not carry them anywhere. Check before a manual run:
   `SELECT id, title, mails FROM {prefix}_newsletter WHERE mails IS NOT NULL AND mails != ''`
   — if that is empty, nothing is lost.
4. **The mail queue is never drained.** 6.3 stores outgoing mail instead of sending it
   inside the request, and the `maildrain` scheduler job is what delivers it. The
   installer seeds that job into `config/scheduler.php`; without it **all outgoing
   mail stops**.
5. **`config/newsletter.php` is not created**, so the campaign limits fall back to
   nothing.

The upgrade runs through the installer and nowhere else. The SQL page parses and
executes what it is given, but it performs none of the five PHP steps above, so a
schema pasted into it leaves the data around it wrong.

### Upgrade notes for the comment subsystem

Read this before running `setup/sql/table_update6_3.sql` on an installation with a
large comment table. **Take a dump of `{prefix}_comment` first and rehearse the
restore.**

- The upgrade adds five columns to `{prefix}_comment` (`pid`, `shown`, `edited`,
  `deleted`, `reqkey`), places `pid` directly behind `id` and `shown` behind
  `status`, fills `shown` from `time` for every published comment, makes `time` required, stores `ip`
  under a binary ascii collation, creates the index set the real list, count and
  thread predicates are read through, and drops the keys those supersede (`cid`,
  `modul_status`). No column named `format`, `iphash` or `path` is created, no
  comment rate table is built, and no idempotency key is minted for an existing
  row: a comment written before this release was never replayed and needs none.
- Two guards stop the run instead of guessing, and both stop it before anything
  else has changed: a `reqkey` found as hex text, which means the table was carried
  through a transitional release, and a `NULL` in `time`.
- **This is not instant on a large table.** Every index build rewrites the table,
  and InnoDB holds the rows while it does. It took under a second against 7358
  rows; on a table with orders of magnitude more it is a maintenance window, not a
  page reload. Close the site for it rather than letting a visitor discover it
  during a lock.
- **No text migration is needed and none is shipped.** A comment body is the
  source its author wrote and is rendered through one contract; nothing branches on
  an editor and no column names a storage format.
- The upgrade is idempotent: a second run changes neither schema nor data.
- Deleting a user no longer orphans their comments. The rows stay and lose only
  the reference to the account, so discussions and reply branches survive.
- **Repair the comment counters after the upgrade.** The `comments` column of the
  eight target tables is denormalised, and until 6.3 the counter could be moved for
  the wrong target by a request-supplied module name. The write path is fixed, the
  residue is not: on the reference installation 23 of 885 targets disagreed with
  their live count. Two ways in, both writing the same numbers:
  - `php tools/comment-recount.php report` reads only and prints every target that
    disagrees, `fix` writes the live count back;
  - the first tab of the comments section, which reports what is left and repairs it
    on a click.
  Both are safe to repeat: only rows that disagree are written, so a second
  run reports zero affected rows. None of them touches the comment table, and no
  user points are recalculated. Beyond them the counter maintains itself: every
  comment write recomputes the target it touched instead of nudging it by one.
- New setting `comments.reps` (default 5): how many replies a page shows under one
  comment before it offers to load the rest. It bounds what one long discussion can
  put in front of a reader; the remaining replies stay reachable both through the
  control and, without JavaScript, through the `&all=` link it carries.

### Fixed in the upgrade file itself

- `{prefix}_users`: the file modified `network` and then dropped it further down,
  so a re-run failed and discarded the type normalisation of thirteen other
  columns with it. The upgrade is idempotent for that table again.
- `{prefix}_admins.editor` was declared `BOOLEAN` while `setup/sql/table.sql`
  defines `VARCHAR(32) NOT NULL DEFAULT 'plain'`. It failed on any installation
  whose administrators carry an editor name, and would have destroyed those names
  had it passed.
- `{prefix}_users.points` was declared twice with two different definitions, and
  the second one undid the first. `table.sql` now agrees at `NOT NULL DEFAULT 0`.
- `SchemaUpdateValidationTest` compares column definitions between the fresh
  schema and the upgrade, and rejects a column declared twice with two
  definitions, so this class of defect fails a test instead of an installation.

## 2026-07-10
- Added the route-aware SEO head contract with safe HTML/JSON serialization, canonical and robots policy, Open Graph, and typed JSON-LD.
- Normalized frontend `H1-H3` ownership, card and voting contexts, comments, related content, tables, landmarks, and category breadcrumbs.
- Added scoped Markdown heading offsets for article, card, comment, block, and forum contexts.
- Added `SeoSemanticsValidationTest` and the executable `tools/seo-audit.php` HTTP contract audit.
- Corrected the English and Ukrainian Open Graph locale identifiers. `hreflang` remains disabled until languages have stable public URLs.

## 2026-05-20
- Responsive baseline closed for `lite` and `admin`.
- `lite` mobile/tablet layout fixed without changing the desktop visual style.
- Top menu, dropdowns, table wrappers, forum posts, comments, cards, media, and admin forms were verified after the CSS updates.
- Browser verification was completed with Playwright and Chromium.
- Authenticated admin pages were verified after login.
- Remaining component-specific backlog items are non-blocking: CodeMirror editor surface on the admin template page, statistic chart fixed-width images, and monitor widget internal widths.
