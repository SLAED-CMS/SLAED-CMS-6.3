/* Settings series: the page "Поиск → Конфигурации" (admin.php?name=search&op=config) laid out again on the engine of the
   statistic series (st.js draws the panel around the face). A face is one empty .cf-root carrying its set of values:
   - data-cf-place="page|tab|side" — where the sources of the search stand: under the settings on the same page, on a tab
     of their own beside "Конфигурации", or in a column beside the settings
   - data-cf-list="now|table|rows|tiles|marks" — the form of the list of sources: the check lists inside the settings and
     the three tables with their two buttons as the page prints them now; one table of every source with a filter over it;
     a window per kind with a row and a switch per source (the form row of the theme); the module cards of the panel home
     under its folding heads; one window with the check lists of the theme
   - data-cf-bad="open|fold" — the sources that cannot be searched: shown, or kept in a folded window; the form "now" has
     no such window and leaves the axis idle
   The content is the same in every set: the five settings, the seven types of Node, the two modules that can be
   searched and the seven that cannot, each with its table, its fields and its reason. The three ways the page has now
   to add a module - a check in the table, the check "all" and the button under the tables - are one switch per source and
   one button in every new form. This file fills the root and puts the desk over it: a button data-cf-set="<axis>:<value>"
   writes the attribute, data-cf-view picks one of the four sets, data-cf-reset returns the page as it is now, data-cf-say
   names the set in words. The modules follow the stand, the tables and fields are of that shape */

const CF_AXES = [
  ['place', 'Источники', [['page', 'На странице'], ['tab', 'Отдельная вкладка'], ['side', 'Рядом']]],
  ['list', 'Список', [['now', 'Как сейчас'], ['table', 'Одна таблица'], ['rows', 'Строки в окнах'], ['tiles', 'Плитки'], ['marks', 'Отметки']]],
  ['bad', 'Несовместимые', [['open', 'Видны'], ['fold', 'Свёрнуты']]],
];

const CF_VIEWS = [
  ['1', 'Две вкладки', { place: 'tab', list: 'table', bad: 'fold' }],
  ['2', 'Окна', { place: 'page', list: 'rows', bad: 'fold' }],
  ['3', 'Два столбца', { place: 'side', list: 'marks', bad: 'fold' }],
  ['4', 'Плитки', { place: 'page', list: 'tiles', bad: 'open' }],
];

const CF_NOW = { place: 'page', list: 'now', bad: 'open' };

const CF_KINDS = { node: ['Материалы', 'info'], special: ['Специальный', 'accent'], auto: ['Автосовместимый', 'success'], bad: ['Несовместим', 'neutral'] };

/* key, name, icon, kind, table, fields, route, in the search, reason */
const CF_SRC = [
  ['news', 'Новости', 'newspaper', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['faq', 'Вопросы и ответы', 'question-circle', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['help', 'Помощь', 'life-preserver', 'node', 'sport_nodes', 'title, intro, body', 'node_add', false, ''],
  ['content', 'Содержание', 'file-text', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['links', 'Каталог сайтов', 'link-45deg', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['files', 'Каталог файлов', 'file-earmark-arrow-down', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['docs', 'Документация', 'book', 'node', 'sport_nodes', 'title, intro, body', 'node_add', true, ''],
  ['forum', 'Форум', 'chat-square-text', 'special', 'sport_forum', 'title, body', 'forum_add', true, ''],
  ['voting', 'Опросы', 'bar-chart', 'auto', 'sport_voting', 'title, body', 'voting_add', false, ''],
  ['account', 'Профиль', 'people', 'bad', 'sport_account', '', '', false, 'Таблица не найдена'],
  ['changelog', 'Журнал изменений', 'journal-text', 'bad', 'sport_changelog', '', '', false, 'Таблица не найдена'],
  ['contact', 'Обратная связь', 'envelope-open', 'bad', 'sport_contact', '', '', false, 'Таблица не найдена'],
  ['presentation', 'Презентация', 'easel', 'bad', 'sport_presentation', '', '', false, 'Таблица не найдена'],
  ['rss', 'RSS-каналы', 'rss', 'bad', 'sport_rss', '', '', false, 'Таблица не найдена'],
  ['sitemap', 'Карта сайта', 'diagram-3', 'bad', 'sport_sitemap', '', '', false, 'Таблица не найдена'],
  ['users', 'Топ пользователей', 'trophy', 'bad', 'sport_users', '', '', false, 'Нужны поля: title'],
].map(([key, name, icon, kind, table, fields, route, on, why]) => ({ key, name, icon, kind, table, fields, route, on, why }));

const cf = { tab: 'src', pick: 'all' };

const getCfIcon = (name) => `<i class="bi bi-${name}" aria-hidden="true"></i>`;
const getCfChip = (tone, text) => `<span class="sl-chip sl-chip-${tone}">${text}</span>`;
const getCfOf = (kinds) => CF_SRC.filter((one) => kinds.includes(one.kind));
const getCfSum = (kinds) => `${getCfOf(kinds).filter((one) => one.on).length} из ${getCfOf(kinds).length}`;

/* One window of the theme (fragments/pane.html): the head with its icon, its chips and its tail, the body, and the fold of a folding one */
function getCfPane(one) {
  const chips = one.chips ? `<span class="sl-pane-chips">${one.chips}</span>` : '';
  const meta = one.meta ? `<span class="sl-pane-meta">${one.meta}</span>` : '';
  const fold = one.fold ? `<span class="sl-plus sl-pane-fold" title="Свёртывание / Развёртывание" aria-label="Свёртывание / Развёртывание" data-sl-toggle-control="${one.fold}-rows" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"></span>` : '';
  const body = `<div class="sl-pane-body">${one.body}</div>`;
  const hold = one.fold ? `<div id="${one.fold}-rows" class="sl-collapsible" data-sl-toggle="${one.fold}-rows" data-sl-toggle-default="closed" hidden>${body}</div>` : body;
  return `<section class="sl-pane"><div class="sl-pane-cap"${one.fold ? ' data-sl-toggle-head' : ''}>${getCfIcon(one.icon)}<h2 class="sl-pane-title">${one.title}</h2>${chips}${meta}${fold}</div>${hold}</section>`;
}

function getCfRow(id, label, field, hint) {
  const note = hint ? `<span class="sl-small" id="${id}-hint">${hint}</span>` : '';
  return `<div class="sl-div-item"><div class="sl-div-label"><label for="${id}">${label}</label>${note}</div><div class="sl-div-field">${field}</div></div>`;
}

function getCfNum(id, label, value, hint, hold) {
  return getCfRow(id, label, `<input type="number" value="${value}" id="${id}"${hold ? ` class="sl-select-config" placeholder="${hold}"` : ''} aria-describedby="${id}-hint">`, hint);
}

function getCfSwitch(id, label, on, hint, flip) {
  const cell = (val, text) => `<label class="sl-radio"> <input type="radio" name="${id}" value="${val}"${on === !!val ? ' checked' : ''}${flip ? ` data-cf-on="${flip}:${val}"` : ''}> ${text}</label>`;
  return `<div class="sl-div-item"><div class="sl-div-label"><span id="${id}-label">${label}</span>${hint ? `<span class="sl-small">${hint}</span>` : ''}</div><div class="sl-div-field">` +
    `<div class="sl-radio-switch sl-radio-group" role="group" aria-labelledby="${id}-label">${cell(1, 'Да')}${cell(0, 'Нет')}</div></div></div>`;
}

function getCfChecks(id, label, kinds, flip) {
  const item = (one) => `<div><label class="sl-radio"><input type="checkbox" value="${one.key}"${one.on ? ' checked' : ''}${flip ? ` data-cf-on="${one.key}"` : ''}> ${one.name}</label></div>`;
  return `<div class="sl-div-item sl-div-item-full"><div class="sl-div-label"><span id="${id}-label">${label}</span></div><div class="sl-div-field">` +
    `<div class="sl-check-list" role="group" aria-labelledby="${id}-label">${getCfOf(kinds).map(item).join('')}</div></div></div>`;
}

/* The window "Основные": the five settings, and in the form of today the two check lists the page keeps among them */
function getCfMain(now) {
  const lists = now ? getCfChecks('cf-mods', 'Использовать поиск в модулях', ['special']) + getCfChecks('cf-types', 'Материалы', ['node']) : '';
  return getCfPane({ icon: 'sliders', title: 'Основные', body: `<div class="sl-div-grid">${
    getCfSwitch('cf-asearch', 'Активировать анализатор поисковых запросов?', false)}${lists}${
    getCfNum('cf-slet', 'Минимально допустимое количество символов в поисковом запросе', '3', 'Активно для модуля поиска, а также в функции подсказки при поиске пользователя в модуле личных сообщений и панели администратора.')}${
    getCfNum('cf-slimit', 'Максимальный объём текста для поиска (символов)', '500', 'Ограничивает количество символов, передаваемых в поисковый запрос, для защиты от перегрузки базы данных.')}${
    getCfNum('cf-rows', 'Записей на странице сайта', '', 'Пусто — как в общих настройках: 15.', '15')}${
    getCfNum('cf-span', 'Номеров страниц в пагинаторе сайта', '', 'Пусто — как в общих настройках: 8.', '8')}</div>` });
}

function getCfActs(all) {
  const free = getCfOf(['auto']).some((one) => !one.on);
  const add = all ? `<button type="button" class="sl-but" data-cf-all${free ? '' : ' disabled'} title="Добавляет в поиск все активные модули с совместимой структурой таблицы.">${getCfIcon('lightning-charge')} Подключить все совместимые</button>` : '';
  return `<div class="sl-div-actions">${add}<button type="button" class="sl-but-blue">${getCfIcon('check-lg')} Сохранить</button></div>`;
}

const getCfForm = (html) => `<div class="sl-box"><div class="sl-div-form">${html}</div></div>`;

/* A table of the list canon (fragments/table.html), as the page prints its three */
function getCfTable(head, rows) {
  return `<table class="sl-table-list-sort sl-table-fixed"><thead><tr>${head.map(([name, cls]) => `<th${cls ? ` class="${cls}"` : ''}>${name}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table>`;
}

const getCfCell = (text, cls) => `<td class="sl-cell-ellipsis${cls ? ' ' + cls : ''}" title="${text}">${text}</td>`;

/* The second part of the page as it is now: three tables of seven columns, the check "all" with its button, and one more button that does the same */
function getCfNowRest() {
  const head = [['Модуль', 'sl-cell-ellipsis'], ['ID', 'sl-cell-ellipsis'], ['Тип'], ['Таблица', 'sl-cell-ellipsis'], ['Поля', 'sl-cell-ellipsis'], ['Маршрут', 'sl-cell-ellipsis'], ['Причина', 'sl-cell-ellipsis']];
  const row = (one, check) => `<tr>${getCfCell(one.name)}${getCfCell(one.key)}<td>${one.kind === 'bad' ? 'Несовместимы' : CF_KINDS[one.kind][0]}</td>${getCfCell(one.table)}${getCfCell(one.fields || 'Нет')}` +
    `${getCfCell(one.route || 'Нет')}${getCfCell(one.why || 'Нет')}${check ? `<td class="sl-col-check"><input type="checkbox" aria-label="${one.key}"></td>` : ''}</tr>`;
  const mods = getCfOf(['special', 'auto']);
  const box = (title, html) => `<div class="sl-box"><h2>${title}</h2>${html}</div>`;
  const pick = '<div class="sl-div-actions"><label class="sl-radio sl-right"><input type="checkbox"> Добавить все совместимые</label><span class="sl-small">Добавляет в поиск все активные модули с совместимой структурой таблицы.</span>' +
    `<button type="button" class="sl-but-blue">${getCfIcon('plus-lg')} Добавить выбранные</button></div>`;
  return box('Уже подключены', getCfTable(head, mods.filter((one) => one.on).map((one) => row(one)))) +
    box('Доступны для подключения', `<div class="sl-div-form">${getCfTable([...head, ['Добавить', 'sl-col-check']], mods.filter((one) => !one.on).map((one) => row(one, true)))}${pick}</div>`) +
    box('Несовместимы', getCfTable(head, getCfOf(['bad']).map((one) => row(one)))) +
    `<div class="sl-box"><div class="sl-div-form"><div class="sl-div-actions"><button type="button" class="sl-but-blue">${getCfIcon('lightning-charge')} Автодобавление совместимых модулей</button></div></div></div>`;
}

/* The window of the sources that cannot be searched: the name of a module against its reason, as a list of pairs of the theme (fragments/kv-list.html) */
function getCfBad(fold) {
  const list = getCfOf(['bad']);
  return getCfPane({ icon: 'slash-circle', title: 'Несовместимы', chips: getCfChip('neutral', list.length), meta: 'Нет таблицы или нужных полей', fold: fold ? 'cf-bad' : '',
    body: `<dl class="sl-kv-list">${list.map((one) => `<div class="sl-kv sl-kv-soft"><dt>${getCfIcon(one.icon)}${one.name}</dt><dd><span class="sl-kv-mute">${one.why}</span></dd></div>`).join('')}</dl>` });
}

/* One table of every source: the filter of the theme switch over it, the state as a chip, the check of the row in the last column */
function getCfListTable(bad) {
  const open = bad === 'open';
  const all = getCfOf(open ? ['node', 'special', 'auto', 'bad'] : ['node', 'special', 'auto']);
  const sets = { all, on: all.filter((one) => one.on), off: all.filter((one) => !one.on && one.kind !== 'bad'), bad: all.filter((one) => one.kind === 'bad') };
  if (!sets[cf.pick] || (cf.pick === 'bad' && !open)) cf.pick = 'all';
  const cells = [['all', 'Все'], ['on', 'В поиске'], ['off', 'Выключены'], ...(open ? [['bad', 'Несовместимы']] : [])].map(([key, name]) =>
    `<button type="button" class="sl-seg-cell${cf.pick === key ? ' sl-is-active' : ''}" data-cf-pick="${key}" aria-pressed="${cf.pick === key}">${name} <b>${sets[key].length}</b></button>`).join('');
  const state = (one) => (one.kind === 'bad' ? getCfChip('warn', one.why) : one.on ? getCfChip('success', 'В поиске') : getCfChip('neutral', 'Выключен'));
  const rows = sets[cf.pick].map((one) => `<tr${one.kind === 'bad' ? ' class="cf-is-bad"' : ''}><td class="sl-cell-ellipsis" title="${one.name}"><span class="cf-name">${getCfIcon(one.icon)}${one.name}</span></td>${getCfCell(one.key)}` +
    `<td>${getCfChip(CF_KINDS[one.kind][1], CF_KINDS[one.kind][0])}</td>${getCfCell(one.table)}${getCfCell(one.fields || 'Нет')}<td>${state(one)}</td>` +
    `<td class="sl-col-check">${one.kind === 'bad' ? '' : `<input type="checkbox" id="cf-on-${one.key}" aria-label="${one.name}"${one.on ? ' checked' : ''} data-cf-on="${one.key}">`}</td></tr>`);
  const head = [['Источник', 'sl-cell-ellipsis'], ['ID', 'sl-cell-ellipsis'], ['Модуль'], ['Таблица', 'sl-cell-ellipsis'], ['Поля', 'sl-cell-ellipsis'], ['Состояние'], [getCfIcon('search') + '<span class="sl-visually-hidden">В поиске</span>', 'sl-col-check" title="В поиске']];
  return `<div class="cf-stack"><div class="cf-bar"><span class="sl-seg cf-pick" role="group" aria-label="Состояние">${cells}</span></div>${getCfTable(head, rows)}${open ? '' : getCfBad(true)}</div>`;
}

/* A window per kind, a source is the form row of the theme: the name with its icon on the left, the switch on the right */
function getCfListRows(bad) {
  const row = (one, note) => getCfSwitch('cf-on-' + one.key, `<span class="cf-name">${getCfIcon(one.icon)}${one.name}</span>${one.kind === 'node' ? '' : ' ' + getCfChip(CF_KINDS[one.kind][1], CF_KINDS[one.kind][0])}`,
    one.on, note ? `${one.table} · ${one.fields}` : '', one.key);
  return `<div class="cf-stack">${
    getCfPane({ icon: 'collection', title: 'Материалы', chips: `<span class="sl-chip sl-chip-info" data-cf-n="node">${getCfSum(['node'])}</span>`, meta: 'sport_nodes · title, intro, body',
      body: `<div class="sl-div-grid">${getCfOf(['node']).map((one) => row(one)).join('')}</div>` })}${
    getCfPane({ icon: 'puzzle', title: 'Модули', chips: `<span class="sl-chip sl-chip-info" data-cf-n="mod">${getCfSum(['special', 'auto'])}</span>`,
      body: `<div class="sl-div-grid">${getCfOf(['special', 'auto']).map((one) => row(one, true)).join('')}</div>` })}${
    getCfBad(bad === 'fold')}</div>`;
}

/* The module cards of the panel home (fragments/menu-grid-item.html) under its folding heads: a press on a card moves it between the first two groups */
function getCfListTiles(bad) {
  const card = (one) => {
    const dead = one.kind === 'bad';
    const text = `<span class="sl-ico">${getCfIcon(one.icon)}<i class="sl-led ${one.on ? 'sl-led-ok' : 'sl-led-off'}" title="${one.on ? 'В поиске' : dead ? 'Несовместим' : 'Выключен'}"></i></span>` +
      `<span class="sl-mbody"><strong><span class="sl-menu-name">${one.name}</span></strong><span>${dead ? one.why : one.key + ' · ' + CF_KINDS[one.kind][0]}</span></span>`;
    return `<article class="sl-menu-grid-item sl-mcard${dead ? ' sl-mcard-off' : ''}">${dead ? `<span class="sl-mcard-link">${text}</span>`
      : `<a class="sl-mcard-link" href="#" role="button" id="cf-on-${one.key}" aria-pressed="${one.on}" title="${one.on ? 'Убрать из поиска' : 'Добавить в поиск'}" data-cf-flip="${one.key}">${text}</a>`}</article>`;
  };
  const group = (id, icon, title, list, shut) => `<div class="sl-dashboard-panel"><h2 class="sl-dashboard-panel-head"><button type="button" class="sl-menu-head" title="${title}" data-sl-toggle-control="${id}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500">` +
    `<b>${getCfIcon(icon)}${title} · ${list.length}</b><i class="bi bi-arrows-collapse sl-panel-toggle sl-panel-toggle-open" aria-hidden="true"></i><i class="bi bi-arrows-expand sl-panel-toggle sl-panel-toggle-closed" aria-hidden="true"></i></button></h2>` +
    `<div id="${id}" class="sl-dashboard-panel-content" data-sl-toggle="${id}"${shut ? ' data-sl-toggle-default="closed" hidden' : ''}>${list.length ? `<div class="sl-menu-grid">${list.map(card).join('')}</div>`
      : `<div class="sl-empty sl-empty-small">${getCfIcon('inbox')}<b>Нет информации</b></div>`}</div></div>`;
  const live = getCfOf(['node', 'special', 'auto']);
  return `<div class="cf-stack">${group('cf-g-on', 'search', 'В поиске', live.filter((one) => one.on))}${group('cf-g-off', 'plus-circle', 'Можно подключить', live.filter((one) => !one.on))}${
    group('cf-g-bad', 'slash-circle', 'Несовместимы', getCfOf(['bad']), bad === 'fold')}</div>`;
}

/* One window with the check lists of the theme: the form the page has for the types of Node today, given to the modules too */
function getCfListMarks(bad) {
  return `<div class="cf-stack">${getCfPane({ icon: 'funnel', title: 'Где искать', chips: `<span class="sl-chip sl-chip-info" data-cf-n="all">${getCfSum(['node', 'special', 'auto'])}</span>`,
    body: `<div class="sl-div-grid">${getCfChecks('cf-m-types', 'Материалы', ['node'], true)}${getCfChecks('cf-m-mods', 'Модули', ['special', 'auto'], true)}</div>` })}${getCfBad(bad === 'fold')}</div>`;
}

function getCfFace(root) {
  const [place, list, bad] = ['place', 'list', 'bad'].map((key) => root.getAttribute('data-cf-' + key));
  const split = (one, two) => `<div class="cf-split"><div class="cf-stack">${one}</div><div class="cf-stack">${two}</div></div>`;
  if (list === 'now') {
    const main = getCfForm(getCfMain(true) + getCfActs(false));
    const rest = getCfNowRest();
    return place === 'tab' ? (cf.tab === 'src' ? rest : main) : place === 'side' ? split(main, rest) : main + rest;
  }
  const main = getCfMain(false);
  const src = { table: getCfListTable, rows: getCfListRows, tiles: getCfListTiles, marks: getCfListMarks }[list](bad);
  if (place === 'tab') return getCfForm(cf.tab === 'src' ? src + getCfActs(true) : main + getCfActs(false));
  return getCfForm((place === 'side' ? split(main, src) : main + src) + getCfActs(true));
}

/* The desk: the four sets in the first row, the three axes they are made of in the second */
function getCfDesk() {
  const cell = (attr, val, text) => `<button type="button" class="sl-seg-cell" ${attr}="${val}" aria-pressed="false">${text}</button>`;
  const axis = (name, cells) => `<span class="wn-axis"><span class="wn-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${cells}</span></span>`;
  return `<div class="sl-box wn-desk" role="group" aria-label="Пульт стенда"><div class="wn-desk-row"><span class="wn-desk-cap">Пульт стенда</span>${
    axis('Вариант', CF_VIEWS.map(([key, name]) => cell('data-cf-view', key, key + ' · ' + name)).join(''))}` +
    `<button type="button" class="sl-but wn-desk-reset" data-cf-reset>${getCfIcon('arrow-counterclockwise')} Как сейчас</button></div>` +
    `<div class="wn-desk-row">${CF_AXES.map(([key, name, values]) => axis(name, values.map(([val, text]) => cell('data-cf-set', key + ':' + val, text)).join(''))).join('')}</div>` +
    '<p class="wn-desk-set" data-cf-say></p></div>';
}

function setCfDesk(root) {
  const now = Object.fromEntries(CF_AXES.map(([key]) => [key, root.getAttribute('data-cf-' + key)]));
  const press = (cell, on) => {
    cell.classList.toggle('sl-is-active', on);
    cell.setAttribute('aria-pressed', String(on));
  };
  document.querySelectorAll('[data-cf-set]').forEach((cell) => {
    const [key, val] = cell.dataset.cfSet.split(':');
    press(cell, now[key] === val);
    cell.disabled = key === 'bad' && now.list === 'now';
  });
  document.querySelectorAll('[data-cf-view]').forEach((cell) => {
    const set = CF_VIEWS.find(([key]) => key === cell.dataset.cfView)[2];
    press(cell, Object.keys(set).every((key) => set[key] === now[key]));
  });
  document.querySelector('[data-cf-say]').textContent = 'Набор: ' + CF_AXES.filter(([key]) => key !== 'bad' || now.list !== 'now').map(([key, name, values]) =>
    name.toLowerCase() + ' — ' + values.find(([val]) => val === now[key])[1].toLowerCase()).join(' · ');
}

/* The strip of the page head: "Конфигурации" is the open tab, and a set with a tab of its own puts "Источники" beside it */
function setCfTabs(root) {
  const nav = document.querySelector('.sl-tabs-nav-main');
  const own = root.getAttribute('data-cf-place') === 'tab';
  let src = nav.querySelector('[data-cf-tab="src"]');
  const conf = [...nav.querySelectorAll('.sl-tabs-link')].find((tab) => tab.textContent === 'Конфигурации');
  conf.setAttribute('data-cf-tab', 'conf');
  if (own && !src) {
    conf.parentElement.insertAdjacentHTML('afterend', '<li class="sl-tabs-item"><a href="#" title="Источники" class="sl-tabs-link" data-cf-tab="src">Источники</a></li>');
    src = nav.querySelector('[data-cf-tab="src"]');
  }
  if (!own && src) src.parentElement.remove();
  nav.querySelectorAll('.sl-tabs-link').forEach((tab) => {
    const on = tab.dataset.cfTab === (own ? cf.tab : 'conf');
    tab.classList.toggle('sl-is-active', on);
    if (on) tab.setAttribute('aria-current', 'page');
    else tab.removeAttribute('aria-current');
  });
}

function setCfCounts() {
  const sums = { node: ['node'], mod: ['special', 'auto'], all: ['node', 'special', 'auto'] };
  document.querySelectorAll('[data-cf-n]').forEach((node) => { node.textContent = getCfSum(sums[node.dataset.cfN]); });
  document.querySelectorAll('[data-cf-all]').forEach((btn) => { btn.disabled = !getCfOf(['auto']).some((one) => !one.on); });
}

function initCf() {
  const root = document.querySelector('[data-cf-root]');
  if (!root) return;
  const set = (vals) => Object.entries(vals).forEach(([key, val]) => root.setAttribute('data-cf-' + key, val));
  const draw = (focus) => {
    root.innerHTML = getCfFace(root);
    setCfTabs(root);
    setCfDesk(root);
    root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
    if (focus) document.getElementById(focus)?.focus({ preventScroll: true });
  };
  const view = CF_VIEWS.find(([key]) => key === new URLSearchParams(location.search).get('view'));
  if (view) set(view[2]);
  root.insertAdjacentHTML('beforebegin', getCfDesk());
  draw();
  const flip = (key, on) => {
    const one = CF_SRC.find((src) => src.key === key);
    one.on = on === undefined ? !one.on : on;
  };
  document.addEventListener('click', (e) => {
    const cell = e.target.closest('[data-cf-set]');
    const pick = e.target.closest('[data-cf-view]');
    const tab = e.target.closest('[data-cf-tab]');
    const card = e.target.closest('[data-cf-flip]');
    const some = e.target.closest('[data-cf-pick]');
    if (cell || pick || e.target.closest('[data-cf-reset]')) {
      const [key, val] = cell ? cell.dataset.cfSet.split(':') : [];
      set(cell ? { [key]: val } : pick ? CF_VIEWS.find(([one]) => one === pick.dataset.cfView)[2] : CF_NOW);
      cf.tab = 'src';
      draw();
    } else if (tab) {
      e.preventDefault();
      cf.tab = tab.dataset.cfTab === 'src' ? 'src' : 'conf';
      draw();
    } else if (card) {
      e.preventDefault();
      flip(card.dataset.cfFlip);
      draw(card.id);
    } else if (some) {
      cf.pick = some.dataset.cfPick;
      draw();
    } else if (e.target.closest('[data-cf-all]')) {
      getCfOf(['auto']).forEach((one) => { one.on = true; });
      draw();
    }
  });
  document.addEventListener('change', (e) => {
    const box = e.target.closest('[data-cf-on]');
    if (!box) return;
    const [key, val] = box.dataset.cfOn.split(':');
    flip(key, val === undefined ? box.checked : val === '1');
    if (root.getAttribute('data-cf-list') === 'table') draw(box.id);
    else setCfCounts();
  });
}

initCf();
