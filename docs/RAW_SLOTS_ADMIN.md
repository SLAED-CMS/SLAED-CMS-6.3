# Admin Raw Slots

## admin-table

- `head_html`: prepared `<th>` cells for modules that need stable header labels with per-column metadata such as sorter flags
- `rows_html`: prepared `<tr>` markup assembled in PHP after escaping and branch handling

## admin-table-row

- `cells_html`: prepared `<td>` cells for modules whose stable list wrapper is shared but whose column sets still differ

## admin-form

- `hidden_html`: prepared hidden fields that stay route-specific and must remain explicit in the PHP caller
- `rows_html`: prepared `<tr>` markup assembled in PHP for mixed admin forms that share one stable shell
- `form_attr`: prepared form-level attributes for rare cases like multipart uploads when the shell stays the same

## admin-form-row

- `label_html`: prepared label cell markup for rows that may contain helper notes or inline hints
- `field_html`: prepared field cell markup for rows that render complex controls from PHP helpers

## admin-form-wide

- `content_html`: prepared full-width row content for collapsible panels, grouped controls, or submit areas

## admin-box

- `content_html`: prepared inner admin panel markup for legacy config and info screens that now share one stable wrapper without `open` and `close`

## admin-admins-table-cells

- `name_html`: prepared admin name cell content with tooltip helper markup
- `email_html`: prepared mail helper markup
- `actions_html`: prepared action menu markup

## admin-admins-form-rows

- `nickname_html`: prepared user-search control from PHP helper
- `password_hint_html`: prepared optional password hint markup
- `smail_html`: prepared radio control markup
- `mail_panel_html`: prepared nested mail panel markup
- `editor_html`: prepared editor selector markup
- `lang_html`: prepared language selector markup when multilingual mode is enabled
- `permissions_html`: prepared permissions grid markup

## admin-admins-mail-panel

- `textarea_html`: prepared mail textarea helper markup

## admin-admins-permissions

- `cells_html`: prepared permission grid cells assembled in PHP for stable 3-column layout

## admin-info-box

- `info_html`: prepared info content returned by legacy helper screens

## admin-info-row

- `count_html`: prepared count badge markup returned by shared helper

## admin-info-table

- `rows_html`: prepared info row markup assembled in PHP for shared admin info blocks

## admin-placeholder-box

- `content_html`: prepared optional ajax/bootstrap content for stable placeholder containers

## admin-select

- `options_html`: prepared option markup assembled in PHP for shared admin select shells

## admin-flag-box

- `css_class`: CSS class name — either `sl_green` or `sl_red`, determined by PHP before rendering
- `label_text`: visible text label — either the yes-label or the no-label, determined by PHP

## admin-note-label

- `title_attr`: plain-text tooltip value for the HTML `title` attribute
- `label_text`: visible abbreviated text, typically a cutstr result

## admin-title-tip

- `content_html`: raw HTML for the hover tooltip popup — may contain `<br>` and markup assembled from other helpers

## admin-danger-text

- `text`: plain text value for files flagged as potentially dangerous (PHP, JS, etc.)

## admin-move-controls

- `target`: ajax target container id — simple alphanumeric string, HTML-escaped by template
- `up_query`: pre-assembled query string for the move-up action — output raw, already contains `&amp;` entities
- `down_query`: pre-assembled query string for the move-down action — output raw, already contains `&amp;` entities
- `up_title`: label for the up-arrow title attribute
- `down_title`: label for the down-arrow title attribute

## admin-edit-list

- `categories_html`: raw output from `getcat()` — the move-to optgroup content
- `name_attr`: select name attribute
- `class`: optional CSS class for the select element
- all label slots (`ops_label`, `comments_label`, `moveto_label`, `activate_label`, etc.): language constant values passed from PHP

## admin-editor-form

- `editor_html`: raw output from `redaktor()` — the editor toggle control
- `action_url`: form action URL — HTML-escaped by template

## admin-info-form

- `textarea_html`: raw HTML from the `textarea()` helper — output raw
- `submit_onclick`: JS onclick handler string — HTML-escaped by template (same behavior as previous explicit htmlspecialchars)
- `submit_label`: submit button value text
- `submit_title`: submit button title attribute text

## admin-account-search-form

- `select_html`: prepared `<select>` markup from `getAdminSelect()` with pre-selected option
- `input_html`: prepared search input markup from `get_user_search()`

## admin-list-form

- `table_html`: prepared table markup from `getAdminTable()` — bulk-action list wrapped in a named form
- `bottom_html`: prepared pager+actions row from `list-bottom` fragment or equivalent
- `hide_html`: prepared hidden field markup for route (name/op/refer) — empty string when hidden fields are already inside `bottom_html`

## admin-conf-save

- `content_html`: prepared tab content assembled in PHP (div.tabcont blocks with tables, textareas and other controls) for ddtabcontent conf-save forms that share one stable form+submit shell

## admin-cat-form

- `tabs_html`: prepared tab content assembled from getCatTab() + getCatTabScript() + getCatSubmitRow() calls for the categories add/subadd/edit forms

## Rules

- origin must stay obvious in the PHP caller
- escaping must happen before fragment rendering
- new raw slots require an explicit reason and a registry entry in this file
