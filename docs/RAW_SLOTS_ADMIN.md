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

## admin-contact-config-rows

- `info_html`: raw textarea helper output for the contact info block
- `admins_html`: raw radio control output from `radio_form()`

## action-menu-item

- `item_html`: prepared action link markup assembled by shared helpers before wrapping into the stable menu list shell

## admin-article-list-head

- `checkall_html`: prepared optional trailing `<th>` cell for bulk-select article lists like `news`; empty string for plain list tables

## admin-article-list-row

- `title_html`: prepared title cell markup with title-tip helper output and truncated visible label
- `post_html`: prepared poster cell markup from user helper output or anonymous fallback
- `status_html`: prepared status indicator markup from `ad_status()`
- `actions_html`: prepared action menu markup
- `checkbox_html`: prepared optional trailing checkbox `<td>` cell for bulk-action rows; empty string for plain rows

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

## admin-panel-grid-item

- (no raw slots — all values are escaped by template)

## admin-panel-list-item

- (no raw slots — all values are escaped by template)

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

## admin-search-drop-form

- (no raw slots — all values are escaped by template)

## admin-categories-search-form

- `select_html`: raw output from `cat_modul()` — the module filter select, assembled by the core helper

## admin-category-img-preview

- (no raw slots — all values are escaped by template)

## admin-search-box

- `sort_html`: prepared search sort `<select>` from `getAdminSelect()`
- `order_html`: prepared search order `<select>` from `getAdminSelect()`
- `modul_html`: prepared module filter `<select>` from `getAdminSelect()`
- `hidden_html`: prepared route hidden inputs for `name=search` and optional `op=toplist`

## admin-search-list-row

- `word_html`: prepared search word cell markup, including title tip or module-local link markup
- `modul_html`: prepared highlighted module label markup
- `actions_html`: prepared action menu markup

## admin-search-word-link

- `label_html`: prepared highlighted word markup for the toplist word link

## admin-search-config-rows

- `modules_label_html`: prepared label markup with helper note block
- `modules_html`: raw output from `modul()` selector helper
- `searchletinfo_label_html`: prepared label markup with helper note block
- `searchlimitinfo_label_html`: prepared label markup with helper note block
- `radio_html`: raw output from `radio_form()`

## admin-search-ready-rows

- `searchauto_label_html`: prepared label markup with helper note block

## admin-search-edit-rows

- `modul_html`: prepared module select markup from `getAdminSelect()`
- `time_html`: raw output from `datetime()`

## admin-search-delete-rows

- `mode_html`: prepared delete-mode select markup from `getAdminSelect()`
- `modul_html`: prepared module select markup from `getAdminSelect()`

## admin-shop-search-box

- `select_html`: prepared search-field select markup from `getAdminSelect()`
- `input_html`: raw output from `get_user_search()`
- `hidden_html`: prepared hidden route fields for `name=shop` and `op=clients`

## admin-shop-clients-row

- `product_html`: prepared title-tip markup with truncated product note
- `site_html`: prepared highlighted site/domain markup
- `nickname_html`: prepared user info markup
- `status_html`: prepared status control markup
- `actions_html`: prepared action menu markup

## admin-shop-products-row

- `title_html`: prepared title-tip markup with truncated product title
- `status_html`: prepared status control markup
- `actions_html`: prepared action menu markup

## admin-shop-partners-row

- `nickname_html`: prepared title-tip plus user info markup
- `actions_html`: prepared action menu markup

## admin-shop-partnerinfo-row

- `nickname_html`: prepared user info markup

## admin-shop-client-partner-rows

- `nickname_html`: prepared partner user info markup

## admin-shop-clientadd-rows

- `product_html`: prepared product select markup
- `cregdate_html`: raw output from `datetime()`
- `cenddate_html`: raw output from `datetime()`
- `activate_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-shop-partneradd-main-rows

- `uid_html`: prepared uid field markup, either input or hidden+value
- `paregdate_html`: raw output from `datetime()`

## admin-shop-partneradd-submit

- `activate_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-shop-export-rows

- `database_html`: prepared export source select markup
- `hidden_html`: prepared route fields for export submit

## admin-shop-import-rows

- `file_html`: prepared import file select markup
- `hidden_html`: prepared route fields for import submit

## admin-shop-export-tabs

- `export_html`: prepared export tab content markup
- `import_html`: prepared import tab content markup

## admin-shop-config-rows

## admin-order-list-row

- `email_html`: prepared title-tip plus anti-spam email markup
- `ip_html`: prepared geo-ip helper markup
- `status_html`: prepared status toggle markup
- `actions_html`: prepared action menu markup

## admin-order-add-rows

- `fields_html`: raw output from `fields_in()` for the order module
- `date_html`: raw output from `datetime()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-article-list-head

- `checkall_html`: optional prepared trailing `<th>` cell for module-specific bulk checkbox controls

## admin-article-list-row

- `title_html`: prepared title-tip markup with truncated module title
- `post_html`: prepared user info markup or fallback text
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup
- `checkbox_html`: optional prepared trailing `<td>` cell for module-specific bulk checkbox controls

## admin-news-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `associated_label_html`: prepared label markup with helper note block
- `associated_html`: prepared associated-topic checkbox table markup
- `hometext_html`: raw output from `textarea()`
- `body_html`: raw output from `textarea()`
- `field_html`: raw output from `fields_in()`
- `time_html`: raw output from `datetime()`
- `vote_html`: raw output from `add_voting()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `fix_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-pages-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `hometext_html`: raw output from `textarea()`
- `body_html`: raw output from `textarea()`
- `time_html`: raw output from `datetime()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-jokes-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `joke_html`: raw output from `textarea()`
- `date_html`: raw output from `datetime()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-media-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `year_html`: prepared year select markup from `getAdminSelect()`
- `description_html`: raw output from `textarea()`
- `lang_html`: prepared language select markup from `getAdminSelect()`
- `note_html`: raw output from `textarea()`
- `format_html`: prepared format select markup from `getAdminSelect()`
- `quality_html`: prepared quality select markup from `getAdminSelect()`
- `links_html`: prepared repeated link-row markup assembled from `admin-media-link-row`
- `date_html`: raw output from `datetime()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-files-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `description_html`: raw output from `textarea()`
- `bodytext_html`: raw output from `textarea()`
- `path_html`: optional prepared directory select markup from `getAdminSelect()`
- `date_html`: raw output from `datetime()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-clients-list-row

- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-clients-add-rows

- `body_html`: raw output from `textarea()`
- `status_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-help-list-row

- `title_html`: prepared title-tip markup with truncated help title
- `post_html`: prepared user info markup or anonymous fallback
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-help-addview-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `hometext_html`: raw output from `textarea()`
- `status_html`: raw output from `radio_form()`
- `umail_html`: raw output from `radio_form()`

## admin-help-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `hometext_html`: raw output from `textarea()`
- `field_html`: raw output from `fields_in()`
- `time_html`: raw output from `datetime()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-money-list-row

- `email_html`: prepared title-tip plus anti-spam email markup
- `ip_html`: prepared geo-ip helper markup
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-money-add-rows

- `intro_html`: prepared dynamic intro field rows assembled from `getAdminFormRow()`
- `time_html`: raw output from `datetime()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-statistic-search-form

- `select_html`: prepared file select markup from `getAdminSelect()`

## admin-referers-search-form

- `sort_html`: prepared sort select markup from `getAdminSelect()`
- `order_html`: prepared order select markup from `getAdminSelect()`

## admin-referers-list-row

- `ip_html`: prepared title-tip plus raw IP text markup

## admin-uploads-search-form

- `select_html`: prepared directory select markup from `getAdminSelect()`

## admin-uploads-tplconfig-block

- `editor_html`: raw output from `textarea_code()`

## admin-uploads-config-general-tab

- `dir_html`: prepared directory select markup from `getAdminSelect()`

## admin-uploads-config-module-block

- `upload_html`: raw output from `radio_form()`
- `upguest_html`: raw output from `radio_form()`

## admin-uploads-tabs-content

- `tab_one_html`: prepared content for upload tab
- `tab_two_html`: prepared content for generated-files tab
- `tab_three_html`: prepared content for thumbs tab

## admin-uploads-config-tabs

- `tab_one_html`: prepared content for general uploads config tab
- `tab_two_html`: prepared content for per-module uploads config tab

## admin-faq-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `answer_html`: raw output from `textarea()`
- `time_html`: raw output from `datetime()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-messages-list-row

- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-messages-add-rows

- `body_html`: raw output from `textarea()`
- `lang_html`: prepared language select markup from `getAdminSelect()`
- `expire_label_html`: prepared label markup with helper note block
- `expire_html`: prepared expiration control markup, either hidden+duration text or numeric input
- `view_html`: prepared visibility select markup from `getAdminSelect()`
- `active_html`: raw output from `radio_form()`

- radio slots (`homcat_html`, `viewcat_html`, `catdesc_html`, `subcat_html`, `mailuser_html`, `date_html`, `read_html`, `rate_html`, `letter_html`, `assoc_html`, `mailsend_html`, `part_html`): raw output from `radio_form()`
- textarea slots (`sende_html`, `userinfo_html`, `partinfo_html`, `partinfoextra_html`, `shopinfo_html`): raw output from `textarea()`
- `partlink_label_html`: prepared label markup with helper note block

## admin-order-list-row

- `email_html`: prepared title-tip plus anti-spam email markup
- `ip_html`: prepared geo/ip markup
- `status_html`: prepared status markup
- `actions_html`: prepared action menu markup

## admin-order-add-rows

- `fields_html`: raw output from `fields_in()`
- `date_html`: raw output from `datetime()`
- `save_html`: prepared save action markup from `ad_save()`

## admin-links-list-row

- `title_html`: prepared title-tip markup with truncated link title
- `site_html`: prepared domain label markup from `domain()`
- `postedby_html`: prepared user info markup
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-links-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `cat_html`: prepared category select markup from `getcat()`
- `description_html`: raw output from `textarea()`
- `bodytext_html`: raw output from `textarea()`
- `date_html`: raw output from `datetime()`
- `acomm_html`: raw output from `com_access()`
- `ihome_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-account-list-row

- `id_html`: prepared highlighted user id markup
- `nickname_html`: prepared title-tip plus highlighted user info markup
- `ip_html`: prepared highlighted geo/ip markup
- `email_html`: prepared highlighted email markup
- `actions_html`: prepared action menu markup

## admin-account-newuser-row

- `actions_html`: prepared action menu markup

## admin-account-add-basic-rows

- `avatar_html`: optional prepared avatar row markup
- `reg_html`: raw output from `datetime()`
- `signature_html`: raw output from `textarea()`
- `allowusers_html`: raw output from `radio_form()`

## admin-content-list-row

- `title_html`: prepared title-tip markup with content view and RSS URLs
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-content-add-rows

- `refresh_label_html`: prepared label markup with helper hint
- `refresh_html`: prepared refresh select markup from `getAdminSelect()`
- `body_html`: raw output from `textarea()`
- `fields_html`: raw output from `fields_in()`
- `date_html`: raw output from `datetime()`
- `save_html`: prepared save action markup from `ad_save()`

## admin-whois-list-row

- `postedby_html`: prepared title-tip plus user label markup
- `domain_html`: prepared domain helper markup
- `domain_status_html`: prepared status badge markup
- `host_html`: prepared domain helper or fallback text
- `host_status_html`: prepared status badge markup
- `dc_html`: prepared domain helper or fallback text
- `dc_status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-whois-add-rows

- `postname_html`: prepared user-search control from `get_user_search()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-ratings-module-block

- `show_hr`: bool flag for separator rendering between module blocks
- `in_html`: raw output from `radio_form()`
- `view_html`: raw output from `radio_form()`

## admin-groups-list-row

- `group_html`: prepared title-tip plus colored group name markup
- `actions_html`: prepared action menu markup

## admin-groups-add-rows

- `rank_html`: prepared rank select markup from `getAdminSelect()`
- `check_attr`: prepared checked attribute for legacy extra-group checkbox

## admin-groups-points-row

- `points_value`: escaped numeric input value

## admin-comments-edit-rows

- `comment_html`: raw output from `textarea()`

## admin-account-add-menu-rows

- `activatepersonal_html`: raw output from `radio_form()`
- `menuconf_html`: raw output from `textarea()`
- `menuconf_label_html`: prepared label markup with helper note

## admin-account-add-tail-rows

- `fields_html`: raw output from `fields_in()`
- `mailblock_html`: prepared nested admin form markup for mail text
- `check_attr`: prepared checkbox checked attribute string
- `save_html`: prepared hidden route fields and submit markup

## admin-account-theme-row

- `options_html`: prepared `<option>` markup assembled in PHP

## admin-account-lang-row

- `lang_html`: raw output from `language()`

## admin-account-warn-row

- `is_hidden`: bool flag controlling the legacy `sl_none` class for collapsed rows

## admin-account-pointreset-rows

- `points_html`: raw output from `radio_form()`
- `ratings_html`: raw output from `radio_form()`
- `uwarns_html`: raw output from `radio_form()`
- `signature_html`: raw output from `radio_form()`

## admin-categories-add-rows

- `lang_html`: optional prepared language row markup
- `modul_html`: raw output from `cat_modul()`
- `img_html`: prepared category image select markup
- `preview_html`: prepared category preview markup
- `activate_html`: raw output from `radio_form()`

## admin-categories-subadd-rows

- `lang_html`: optional prepared language row markup
- `modul_html`: raw output from `cat_modul()`
- `category_html`: raw output from `getcat()`
- `img_html`: prepared category image select markup
- `preview_html`: prepared category preview markup
- `activate_html`: raw output from `radio_form()`

## admin-categories-editpick-rows

- `category_html`: raw output from `getcat()`

## admin-categories-edit-rows

- `modul_html`: raw output from `cat_modul()`
- `lang_html`: optional prepared language row markup
- `category_html`: optional prepared parent-category row markup
- `hidden_parent_html`: prepared hidden parent input for root categories
- `img_html`: prepared category image select markup
- `preview_html`: prepared category preview markup
- `activate_html`: raw output from `radio_form()`

## admin-blocks-add-rows

- `bfile_html`: prepared file select markup
- `content_html`: raw output from `textarea()`
- `position_html`: prepared block position select markup
- `blockview_html`: prepared block visibility grid markup
- `language_html`: optional prepared language row markup
- `activate_html`: raw output from `radio_form()`
- `action_html`: prepared after-expiration select markup
- `viewpriv_html`: prepared view-privilege select markup

## admin-blocks-fileedit-rows

- `bf_html`: prepared file select markup

## admin-blocks-edit-rows

- `activate_html`: raw output from `radio_form()`
- `expiration_html`: prepared expiration field markup, either hidden+purchased text or numeric input
- `action_html`: prepared after-expiration select markup
- `viewpriv_html`: prepared view-privilege select markup

## admin-blocks-back-box

- (no raw slots — all values are escaped by template)

## admin-blocks-filecode-rows

- `code_html`: raw output from `textarea_code()`

## admin-rss-source-block

- `uses_html`: prepared source-target select markup from `getAdminSelect()`

## admin-rss-config-form

- `rss_sources_html`: prepared repeated source block markup
- `act_html`: raw output from `radio_form()`
- `use_html`: raw output from `radio_form()`

## admin-template-search-form

- `select_html`: prepared theme select markup from `getAdminSelect()`

## admin-template-editor-block

- `editor_html`: raw output from `textarea_code()`

## admin-security-list-row

- `title_html`: prepared title-tip log label markup
- `actions_html`: prepared action menu markup

## admin-security-logview-box

- `code_html`: raw output from `textarea_code()`

## admin-security-pass-rows

- `login_row_html`: prepared credential rows fragment when login/password bootstrap fields are required
- `pass_row_html`: reserved empty raw slot for backward-compatible row assembly

## admin-security-pass-credential-rows

- (no raw slots — all values are escaped by template)

## admin-sitemap-editor-rows

- `code_html`: raw output from `textarea_code()` for XML editors

## admin-replace-field-block

- `display_attr`: prepared attribute string like ` class="sl_none"` for hidden rows
- `hr_html`: prepared separator HTML, empty string or `<hr>`

## admin-replace-tab-content

- `items_html`: prepared sequence of replace field blocks for one tab

## admin-fields-field-block

- `display_attr`: prepared attribute string like ` class="sl_none"` for hidden rows
- `hr_html`: prepared separator HTML, empty string or `<hr>`
- `next_block_id`: target id for the collapsible legacy row
- `field_label`: escaped expand-link label
- `content_label`: escaped field-content label
- `name_label`: escaped field-name label
- `content_placeholder`: escaped placeholder text
- `name_placeholder`: escaped placeholder text
- `name_value`: prepared field-name value
- `content_value`: prepared field-content value
- `field_html`: prepared field-type select markup from `getAdminSelect()`
- `field2_html`: prepared visibility select markup from `getAdminSelect()`
- `title_attr`: escaped `title` attribute for the legacy expand link
- `xid`: escaped field group index suffix

## admin-fields-tab-content

- `items_html`: prepared sequence of field blocks for one tab

## admin-fields-tabs-script

- `group_id`: escaped ddtabcontent group id

## admin-security-ban-ip-row

- `ip_html`: prepared title-tip plus geo-ip markup
- `actions_html`: prepared action menu markup

## admin-security-ban-user-row

- `name_html`: prepared user info markup
- `actions_html`: prepared action menu markup

## admin-security-ban-user-form

- `name_html`: prepared user-search control from `get_user_search()`
- `check_attr`: prepared checked-attribute fragment for legacy checkbox state
- `mailtext_html`: raw output from `textarea()`

## admin-security-ban-tabs

- `tab_one_html`: prepared banned-ip tab content
- `tab_two_html`: prepared banned-user tab content

## admin-security-config-form

- `flood_html`: prepared flood select markup from `getAdminSelect()`
- `error_html`: prepared error-view select markup from `getAdminSelect()`
- `log_b_html`: raw output from `radio_form()`
- `error_java_html`: raw output from `radio_form()`
- `error_log_html`: raw output from `radio_form()`
- `url_get_html`: raw output from `radio_form()`
- `url_post_html`: raw output from `radio_form()`
- `ref_post_html`: raw output from `radio_form()`
- `mail_html`: raw output from `radio_form()`
- `mail_w_html`: raw output from `radio_form()`
- `mail_d_html`: raw output from `radio_form()`
- `write_h_html`: raw output from `radio_form()`
- `write_w_html`: raw output from `radio_form()`
- `log_html`: raw output from `radio_form()`
- `log_d_html`: raw output from `radio_form()`
- `log_a_html`: raw output from `radio_form()`
- `log_u_html`: raw output from `radio_form()`
- `block_html`: raw output from `radio_form()`

## admin-auto-links-list-row

- `sitename_html`: prepared title-tip plus short-name markup
- `actions_html`: prepared action menu markup

## admin-auto-links-stats-search

- `sort_html`: prepared sort select markup from `getAdminSelect()`
- `order_html`: prepared order select markup from `getAdminSelect()`

## admin-auto-links-stats-row

- `nickname_html`: prepared title-tip plus user label markup
- `ip_html`: prepared geo-ip helper markup

## admin-auto-links-add-rows

- `desc_html`: raw output from `textarea()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-auto-links-config-rows

- `img_html`: prepared image select markup from `getAdminSelect()`
- `preview_html`: prepared preview image markup
- `addmail_html`: raw output from `radio_form()`

## admin-chlog-config-rows

- `source_html`: prepared source select markup
- `grpdate_html`: raw output from `radio_form()`
- `showfile_html`: raw output from `radio_form()`
- `showstat_html`: raw output from `radio_form()`
- `exporten_html`: raw output from `radio_form()`

## admin-newsletter-list-head

- no raw slots

## admin-newsletter-list-row

- `title_html`: prepared title-tip markup for newsletter timing
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-newsletter-add-rows

- `title_value`: prepared title value from PHP caller
- `body_html`: raw output from `textarea()`
- `mails_html`: prepared select markup from `getAdminSelect()`
- `text_label`: escaped label for newsletter body row

## admin-voting-list-row

- `title_html`: prepared title-tip markup with truncated poll title
- `status_html`: prepared status badge markup
- `actions_html`: prepared action menu markup

## admin-voting-preview-box

- `voting_html`: raw output from `getVoting()`

## admin-voting-answer-row

- `hidden`: bool flag controlling the legacy `sl_none` class

## admin-voting-add-rows

- `modul_html`: prepared module select markup from `getAdminSelect()`
- `answers_html`: prepared repeated answer-row markup assembled from `admin-voting-answer-row`
- `date_html`: raw output from `datetime()`
- `enddate_html`: raw output from `datetime()`
- `status_html`: prepared status select markup from `getAdminSelect()`
- `type_html`: prepared type select markup from `getAdminSelect()`
- `lang_html`: prepared language select markup from `getAdminSelect()`
- `acomm_html`: raw output from `com_access()`
- `multi_html`: raw output from `radio_form()`
- `save_html`: prepared hidden/save action markup from `ad_save()`

## admin-hidden-input

- (no raw slots — all values are escaped by template)

## admin-text-input

- `input_attr`: prepared extra attribute string (e.g. `placeholder="..." maxlength="255"`) — output raw, trusted PHP-assembled literals only

## admin-number-input

- `input_attr`: prepared extra attribute string (e.g. `placeholder="..." required`) — output raw, trusted PHP-assembled literals only

## Rules

- origin must stay obvious in the PHP caller
- escaping must happen before fragment rendering
- new raw slots require an explicit reason and a registry entry in this file
