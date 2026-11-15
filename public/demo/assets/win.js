/* Window series: the window of the right side of the panel on the engine of the statistic series (st.js draws the panel
   around the face). A face is one empty .wn-root carrying its set of values:
   - data-wn-frame="soft|strong" — the tone of the frame
   - data-wn-radius="1|2|3" — the step of the radius ladder
   - data-wn-ground="flat|grad|cap|sunken" — the surface: the page, the gradient of the monitor down the whole window, the
     gradient under the head alone, the sunken block with white rows
   - data-wn-head="lamps|caps|link|badge" — the head: mono capitals with the three lamps or with the icon, the plain
     text of the settings block, the icon on a plate with a semibold name of the cabinet
   - data-wn-rule="on|off" — the line under the head
   - data-wn-inset="tight|mid|wide" — the inset of the head and the body
   - data-wn-shade="none|xs" — the shadow of the list table
   - data-wn-fold="start|end" — the side of the fold control
   - data-wn-tabs="folder|line|pills" — the form of a page tab: the folder of today, a word on the line of the strip with
     a brand line under the open one, a pill with the open one filled; the strip of the page head stands outside the root
     and follows the tab axes too
   - data-wn-tabline="strong|pane" — the line of the strip and the frame of an open folder: the strong border or the line
     of the window; a strip of pills has no line
   - data-wn-tabfill="own|pane|none" — the ground of an idle folder: its own gradient, the gradient of the window, none
   - data-wn-rows="strong|soft" — the dashed rule between the rows of a list table
   - data-wn-listcap="own|pane|caps" — the head of a list: its own plate, the ground of the window down the whole list,
     the same with the titles in the capitals of a window head
   - data-wn-rowhover="own|tint" — the row under the pointer: the surface of today or the tint of a row in a window
   - data-wn-picks="mixed|pills|rects|seg" — the tabs of the emoji window and of the icon window: each in the form it
     wore before, both as pills, both as rectangles, both as the segmented switch
   The theme carries the set chosen on the desk (the folders, the line of the window, the own ground, the soft rule, the
   ground and the capitals of a window, the tint, the segments), so win.css writes only the other values of these axes
   - data-wn-helpwin="none|one|parts" — the help page in the window of the theme: bare as today, one window round the
     whole text, a window round every section with the name of the section in its head
   - data-wn-helptoc="none|snake|time|line|rows|steps" — the rail of the sections of a help page, which stands over the help
     and marks the section being read: none; the snake - a card for a section on one line that fills the width, turns
     at the end of a row and runs back, for any number of cards; the timeline - the names over the dots of one line
     that fills up to the section being read and slides between two arrows; the segmented switch with the names in
     one line that slides between the same two arrows, with the names in rows, or with the numbers of the pager
   - data-wn-helpstick="on|off" — the rail stays in view while the text scrolls, or leaves with it
   - data-wn-helphead="on|off" — the first heading of a help page, which says the name of the page again: shown or not
   - data-wn-seg="now|edge|plate|ring" — the segmented switch of the whole page: as it was, without a line; a line round
     the plate and round the track in both schemes, the colours kept; a lighter plate in the dark scheme, where the
     plate is the ground of the page on a track one step lighter; the lighter plate with both lines
   The theme carries the help chosen on the desk as well (one window, the names in one line that stay in view, the first
   heading kept, the line round the switch): that set is the set of the face, and the room draws it with the window of
   the theme
   - data-wn-ask="<axis> <axis> …" — the axes the face asks about: the desk draws only those, and a room stands only when
     it serves one of them; without the attribute the face shows every axis and every room
   This file fills the root with the rooms, the same in every face, and puts the desk over it: a button
   data-wn-set="<axis>:<value>" writes the attribute, data-wn-reset returns the set of the face, data-wn-say names the
   set in words. The rows, lists and meters are the markup the theme prints on the live pages */

const WN_AXES = [
  ['frame', 'Рамка', [['soft', 'Тонкая'], ['strong', 'Сильная']]],
  ['radius', 'Радиус', [['1', '4'], ['2', '8'], ['3', '12']]],
  ['ground', 'Поверхность', [['flat', 'Плоская'], ['grad', 'Градиент'], ['cap', 'Градиент шапки'], ['sunken', 'Утопленная']]],
  ['head', 'Шапка', [['lamps', 'Капс и лампы'], ['caps', 'Капс и иконка'], ['link', 'Текст'], ['badge', 'Плашка']]],
  ['rule', 'Черта', [['on', 'Есть'], ['off', 'Нет']]],
  ['inset', 'Поля', [['tight', '8'], ['mid', '12'], ['wide', '16']]],
  ['shade', 'Тень', [['none', 'Нет'], ['xs', 'Есть']]],
  ['fold', 'Сворачивание', [['start', 'Слева'], ['end', 'Справа']]],
  ['tabs', 'Вкладки', [['folder', 'Папки'], ['line', 'Черта'], ['pills', 'Пилюли']]],
  ['tabline', 'Линия вкладок', [['strong', 'Тёмная'], ['pane', 'Как рамка окна']]],
  ['tabfill', 'Фон вкладки', [['own', 'Свой'], ['pane', 'Как у окна'], ['none', 'Без фона']]],
  ['rows', 'Строки списка', [['strong', 'Тёмные'], ['soft', 'Как в окне']]],
  ['listcap', 'Шапка списка', [['own', 'Своя'], ['pane', 'Фон окна'], ['caps', 'Фон и капс окна']]],
  ['rowhover', 'Строка под указателем', [['own', 'Своя'], ['tint', 'Как в окне']]],
  ['picks', 'Окна выбора', [['mixed', 'Как было'], ['pills', 'Пилюли'], ['rects', 'Прямоугольники'], ['seg', 'Сегменты']]],
  ['helpwin', 'Окно справки', [['none', 'Нет'], ['one', 'Одно'], ['parts', 'По разделам']]],
  ['helptoc', 'Рельс разделов', [['none', 'Нет'], ['snake', 'Змейка'], ['time', 'Лента'], ['line', 'В строку'], ['rows', 'В ряды'], ['steps', 'Номера']]],
  ['helpstick', 'Рельс при прокрутке', [['on', 'Остаётся'], ['off', 'Уходит']]],
  ['helphead', 'Заголовок справки', [['on', 'Есть'], ['off', 'Скрыт']]],
  ['seg', 'Переключатель', [['now', 'Как было'], ['edge', 'Кант'], ['plate', 'Плашка светлее'], ['ring', 'Плашка и кант']]],
];

/* The axes of the window itself: the rooms that show nothing but windows serve these */
const WN_WIN = ['frame', 'radius', 'ground', 'head', 'rule', 'inset', 'shade', 'fold'];

function getWnKv(rows, soft) {
  return `<dl class="sl-kv-list">${rows.map(([name, text, kind]) => {
    const val = kind === 'good' ? `<span class="sl-kv-good">${text}</span>` : kind === 'on' ? `<span class="sl-kv-flag sl-kv-on">${text}</span>` : text;
    const icon = soft ? `<i class="bi bi-${kind}" aria-hidden="true"></i>` : '';
    return `<div class="sl-kv${soft ? ' sl-kv-soft' : ''}"><dt>${icon}${name}</dt><dd>${val}</dd></div>`;
  }).join('')}</dl>`;
}

function getWnRow(id, label, field, hint) {
  const note = hint ? `<span class="sl-small" id="${id}-hint">${hint}</span>` : '';
  return `<div class="sl-div-item"><div class="sl-div-label"><label for="${id}">${label}</label>${note}</div><div class="sl-div-field">${field}</div></div>`;
}

function getWnSwitch(id, label, on) {
  return `<div class="sl-div-item"><div class="sl-div-label"><span id="${id}-label">${label}</span></div><div class="sl-div-field">` +
    `<div class="sl-radio-switch sl-radio-group" role="group" aria-labelledby="${id}-label"><label class="sl-radio"> <input type="radio" name="${id}" value="1"${on ? ' checked' : ''}> Да</label>` +
    `<label class="sl-radio"> <input type="radio" name="${id}" value="0"${on ? '' : ' checked'}> Нет</label></div></div></div>`;
}

function getWnInput(id, label, value, type, hint) {
  return getWnRow(id, label, `<input type="${type || 'text'}" value="${value}" class="sl-select-config" id="${id}"${hint ? ` aria-describedby="${id}-hint"` : ''}>`, hint);
}

function getWnSelect(id, label, options) {
  return getWnRow(id, label, `<select class="sl-select" id="${id}">${options.map((one, i) => `<option${i ? '' : ' selected'}>${one}</option>`).join('')}</select>`);
}

/* One window: the fold control, the mark with its icon and its lamps, the name, the tail of the head, the body */
function getWnWin(one) {
  const fold = one.fold ? `<span class="sl-plus wn-fold" title="Свёртывание / Развёртывание" aria-label="Свёртывание / Развёртывание" data-sl-toggle-control="${one.fold}" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"></span>` : '';
  const meta = one.meta ? `<span class="wn-meta">${one.meta}</span>` : '';
  const chip = one.chip ? `<span class="sl-chip sl-chip-${one.chip[0]}">${one.chip[1]}</span>` : '';
  const tail = meta || chip ? `<span class="wn-tail">${meta}${chip}</span>` : '';
  const body = `<div class="wn-body">${one.body}</div>`;
  const hold = one.fold ? `<div id="${one.fold}" class="sl-collapsible" data-sl-toggle="${one.fold}" data-sl-toggle-default="${one.shut ? 'closed' : 'open'}"${one.shut ? ' hidden' : ''}>${body}</div>` : body;
  return `<section class="wn-win"><div class="wn-cap">${fold}<span class="wn-mark" aria-hidden="true"><i class="bi bi-${one.icon}"></i><span class="wn-lamps"><i></i><i></i><i></i></span></span>` +
    `<h2 class="wn-title">${one.title}</h2>${tail}</div>${hold}</section>`;
}

/* open(axis) says whether the face asks about the axis; a room names the axes it serves and stands when one of them is open */
function getWnRooms(open) {
  const feed = (n, shut, chip) => getWnWin({
    icon: 'rss', title: `RSS-канал ${n}`, fold: `wn-feed-${n}`, shut, chip,
    body: `<div class="sl-div-grid">${getWnInput(`wn-feed-${n}-name`, 'Название', n === 1 ? 'Новости SLAED' : '')}${getWnInput(`wn-feed-${n}-url`, 'Адрес', n === 1 ? 'https://slaed.net/rss' : '')}` +
      `${getWnSelect(`wn-feed-${n}-use`, 'Использование', ['Сторонний канал', 'Стандартный канал'])}</div>`,
  });
  const meter = '<div class="sl-meter"><div class="sl-meter-head"><span>Занято и в резерве</span><b>31%</b></div>' +
    '<span class="sl-meter-bar sl-knob-ok" role="meter" aria-label="Занято и в резерве" aria-valuemin="0" aria-valuemax="100" aria-valuenow="31"><i style="--sl-d-part: 31%"></i></span>' +
    '<span class="sl-meter-note">73,36 GB из 238,35 GB</span></div>';
  const room = (axes, name, note, html) => (axes.some(open) ? `<div class="wn-room"><p class="wn-room-cap"><b>${name}</b><span>${note}</span></p>${html}</div>` : '');
  const strip = `<ul class="sl-tabs-nav">${['Главная', 'Система', 'Тема', 'Шаблоны', 'Конфигурации', 'Справка'].map((name, i) =>
    `<li class="sl-tabs-item"><a href="#" title="${name}" class="sl-tabs-link${i ? '' : ' sl-is-active'}"${i ? '' : ' aria-current="page"'}>${name}</a></li>`).join('')}</ul>`;
  const files = '<div class="sl-box"><table class="sl-table-list-sort"><thead><tr><th>Название</th><th>Тип</th><th>Размер</th><th class="sl-col-date">Дата</th></tr></thead><tbody>' +
    [['thumb', 'Директория', '—'], ['.htaccess', 'HTACCESS', '13 Bytes'], ['check.gif', 'GIF', '1,35 KB'], ['check.png', 'PNG', '1,76 KB'], ['index.html', 'HTML', '162 Bytes']]
      .map(([name, type, size], i) => `<tr${i === 1 ? ' class="wn-hot"' : ''}><td>${name}</td><td>${type}</td><td>${size}</td><td class="sl-col-date">25.10.2026 18:29</td></tr>`).join('') + '</tbody></table></div>';
  const emoji = `<div class="sl-editor-emoji-tabs sl-seg">${['Недавние', 'Смайлы', 'Реакции', 'Заметки', 'Символы'].map((name, i) =>
    `<button type="button" class="sl-editor-emoji-tab sl-seg-cell${i === 1 ? ' sl-is-active' : ''}">${name}</button>`).join('')}</div>`;
  const icons = '<nav class="sl-seg"><button type="button" class="sl-seg-cell sl-is-active">Bootstrap Icons</button><button type="button" class="sl-seg-cell">Свой набор</button></nav>';
  return room(['tabs', 'tabline', 'tabfill', 'rows', 'listcap', 'rowhover'], 'Вкладки, окно и список',
    'Как «Файловый менеджер». Полоса в шапке страницы меняется вместе с этой. Линия вкладок действует на папки и черту, фон — на папки. Вторая строка списка стоит как под указателем',
    `${strip}<div class="wn-duo">${
    getWnWin({ icon: 'folder2-open', title: 'Каталог файлов', body: getWnKv([['Директория', 'uploads/node/news'], ['Файлов', '18'], ['Квота', '52,38 MB из 1 GB'], ['Загрузка', 'Включена', 'on']]) })}${files}</div>`) +
  room(WN_WIN, 'Приборы','Как «Мониторинг»: три окна в ряд', `<div class="wn-trio">${
    getWnWin({ icon: 'window-stack', title: 'Программы', body: getWnKv([['SLAED CMS', '8.0.0 Phoenix', 'window-stack'], ['PHP', '8.5.12', 'filetype-php'], ['MariaDB', '11.8.3', 'database'], ['Nginx', '1.29.2', 'hdd-rack']], true) })}${
    getWnWin({ icon: 'shield-check', title: 'Безопасность', meta: 'За сутки', body: getWnKv([['Неудачные входы', '0'], ['Ошибки базы', 'Нет', 'good'], ['Журнал ошибок', '3'], ['HTTPS', 'Включено', 'on']]) })}${
    getWnWin({ icon: 'hdd', title: 'Диск', body: meter + getWnKv([['Свободно', '164,99 GB'], ['Состояние', 'Норма', 'good']]) })}</div>`) +
  room(WN_WIN, 'Настройки группами','Как «Пользователи → Конфигурации»: каждая группа в окне', `<div class="wn-stack">${
    getWnWin({ icon: 'person-bounding-box', title: 'Аватар', body: `<div class="sl-div-grid">${getWnSwitch('wn-aupload', 'Разрешить загрузку аватаров на сервер?', true)}${
      getWnInput('wn-adir', 'Папка аватаров внутри uploads/', 'avatars')}${getWnInput('wn-asize', 'Размер одного файла, MB', '5', 'number')}${
      getWnInput('wn-aheight', 'Максимально допустимая высота изображения, в пикселях', '256', 'number', 'Изображение шире или выше этих границ уменьшается до них при загрузке, с сохранением пропорций.')}</div>` })}${
    getWnWin({ icon: 'person-check', title: 'Регистрация, вход и профиль', body: `<div class="sl-div-grid">${getWnSelect('wn-pass', 'Минимальная длина пароля', ['8', '10', '12'])}${
      getWnSwitch('wn-nomail', 'Регистрация без подтверждения на E-Mail?', false)}${getWnInput('wn-stop', 'Запрещённые имена', 'alter,drop,insert,outfile,select,truncate,union')}</div>` })}</div>`) +
  room(WN_WIN, 'Сворачиваемые группы','Как «RSS-каналы» и «Дополнительные поля»: одно раскрыто, два свёрнуты', `<div class="wn-stack">${
    feed(1, false, ['success', 'Включён'])}${feed(2, true, ['neutral', 'Пусто'])}${feed(3, true, ['neutral', 'Пусто'])}</div>`) +
  room(WN_WIN, 'Короткая форма и таблица','Короткая форма стоит в одном окне «Основные», кнопка под ним; таблица в окно не ставится', `<div class="sl-box"><div class="sl-div-form">${
    getWnWin({ icon: 'sliders', title: 'Основные', body: `<div class="sl-div-grid">${
    getWnInput('wn-cnum', 'Записей на странице сайта', '15', 'number', 'Пусто — как в общих настройках: 15.')}${getWnSelect('wn-csort', 'Сортировка', ['В порядке убывания', 'В порядке возрастания'])}${
    getWnSwitch('wn-calert', 'Уведомлять администраторов о новых поступлениях?', true)}</div>` })}` +
    '<div class="sl-div-actions"><button type="button" class="sl-but-blue"><i class="bi bi-check2" aria-hidden="true"></i> Сохранить изменения</button></div></div></div>' +
    '<div class="sl-box"><table class="sl-table-list-sort"><thead><tr><th class="sl-col-id">№</th><th>Категория</th><th>Материалы</th><th class="sl-col-status">Статус</th></tr></thead><tbody>' +
    [[31, 'Устройство', 12], [62, 'Темы', 14], [64, 'Модули', 2], [63, 'Блоки', 2]].map(([id, name, sum]) => `<tr><td class="sl-col-id">${id}</td><td>${name}</td><td>${sum}</td><td class="sl-col-status"><span class="sl-status-active"></span></td></tr>`).join('') +
    '</tbody></table></div>') +
  room(['picks'], 'Окна выбора', 'Группы в окне эмодзи и наборы в окне иконок; второй набор иконок здесь для вида, в системе он один', `<div class="wn-duo wn-halves">${
    getWnWin({ icon: 'emoji-smile', title: 'Эмодзи', body: emoji })}${getWnWin({ icon: 'grid-3x3-gap', title: 'Иконки', body: icons })}</div>`) +
  room(['helpwin', 'helptoc', 'helpstick', 'helphead', 'seg'], 'Справка',
    'Справка «Комментариев» и редактор под ней, как их печатает тема. Рельс собран из заголовков разделов и стоит над справкой. ' +
    'Первый заголовок повторяет название страницы над вкладками',
    '<div class="sl-box wn-help" data-wn-help><div class="sl-markdown"></div></div><div class="sl-box" data-wn-help-form inert></div>');
}

/* The cards of the snake stand in as many columns as the rail holds, and every second row runs back, so the line between them turns at the end of a row */
function setWnSnake(run) {
  if (!run.offsetParent) return;
  const css = getComputedStyle(run);
  const gap = parseFloat(css.columnGap) || 0;
  const room = run.clientWidth - parseFloat(css.paddingLeft) - parseFloat(css.paddingRight);
  const cols = Math.max(1, Math.floor((room + gap) / ((parseFloat(css.getPropertyValue('--wn-stop-min')) || 180) + gap)));
  const stops = [...run.children];
  run.style.gridTemplateColumns = `repeat(${cols}, minmax(0, 1fr))`;
  stops.forEach((stop, i) => {
    const row = Math.floor(i / cols);
    const back = row % 2 === 1;
    stop.style.gridRow = row + 1;
    stop.style.gridColumn = back ? cols - (i % cols) : (i % cols) + 1;
    stop.classList.toggle('wn-is-back', back);
    stop.classList.toggle('wn-is-turn', i % cols === cols - 1 && i < stops.length - 1);
    stop.classList.toggle('wn-is-first', i === 0);
    stop.classList.toggle('wn-is-last', i === stops.length - 1);
  });
}

/* The help room: the text and the editor under it are snapshots of a rendered help page. The text is wrapped once - the whole of it, its opening part, and
   every h2 with what follows it - and setWnHelpLook() dresses the wrappers as windows of the theme or leaves them bare. The rails are built from the h2 */
function setWnHelp(root) {
  const box = root.querySelector('[data-wn-help]');
  if (!box) return;
  Promise.all(['assets/help-sample.html', 'assets/help-editor.html'].map((url) => fetch(url).then((res) => res.text()))).then(([html, form]) => {
    const text = box.querySelector('.sl-markdown');
    const cap = (name) => `<div data-wn-cap><i class="bi bi-info-circle" aria-hidden="true" hidden></i>${name}</div><div data-wn-body></div>`;
    root.querySelector('[data-wn-help-form]').innerHTML = form;
    text.innerHTML = `<section data-wn-all>${cap('<h2 class="sl-pane-title">Справка</h2>')}</section>`;
    const body = text.querySelector('[data-wn-body]');
    body.innerHTML = html;
    const heads = [...body.querySelectorAll('h2[id]')];
    const intro = document.createElement('section');
    intro.setAttribute('data-wn-part', 'intro');
    intro.innerHTML = cap('<h2 class="sl-pane-title">Справка</h2>');
    body.prepend(intro);
    while (intro.nextSibling && !(intro.nextSibling.nodeType === 1 && /^(H2|HR)$/.test(intro.nextSibling.tagName))) intro.lastChild.appendChild(intro.nextSibling);
    for (const head of heads) {
      const part = document.createElement('section');
      part.setAttribute('data-wn-part', '');
      part.innerHTML = cap('');
      head.before(part);
      part.firstChild.appendChild(head);
      while (part.nextSibling && !(part.nextSibling.nodeType === 1 && /^(H2|HR)$/.test(part.nextSibling.tagName))) part.lastChild.appendChild(part.nextSibling);
    }
    const name = (head) => head.textContent.replace(/^\d+\.\s*/, '');
    const cells = (short) => heads.map((head, i) =>
      `<a href="#${head.id}" class="sl-seg-cell${i ? '' : ' sl-is-active'}" data-wn-to="${head.id}" title="${head.textContent}">${short ? i + 1 : head.textContent}</a>`).join('');
    const stops = heads.map((head, i) =>
      `<a href="#${head.id}" class="wn-stop${i ? '' : ' sl-is-active'}" data-wn-to="${head.id}" title="${name(head)}"><b>${i + 1}</b><span>${name(head)}</span></a>`).join('');
    const dots = heads.map((head, i) => `<a href="#${head.id}" class="wn-time-stop${i ? '' : ' sl-is-active'}" data-wn-to="${head.id}">${name(head)}</a>`).join('');
    const go = (way, icon, text) => `<button type="button" class="sl-but-mini" data-wn-time="${way}" title="${text}" aria-label="${text}"><i class="bi bi-chevron-${icon}" aria-hidden="true"></i></button>`;
    const knob = '<span class="sl-seg-knob" data-sl-knob-mark aria-hidden="true"></span>';
    const rail = document.createElement('nav');
    rail.className = 'wn-rail';
    rail.setAttribute('aria-label', 'Оглавление');
    rail.innerHTML = `<div class="wn-rail-run wn-snake" data-wn-rail="snake">${stops}</div>` +
      `<div class="wn-rail-run wn-time" data-wn-rail="time">${go(-1, 'left', 'Назад')}<div class="wn-rail-view"><div class="wn-time-way"><span class="wn-time-fill"></span>${dots}</div></div>` +
      `${go(1, 'right', 'Вперёд')}</div>` +
      `<div class="wn-rail-run" data-wn-rail="line">${go(-1, 'left', 'Назад')}<div class="wn-rail-view"><span class="sl-seg" data-sl-knob="help-line">${knob}${cells(false)}</span></div>` +
      `${go(1, 'right', 'Вперёд')}</div>` +
      `<div class="wn-rail-run" data-wn-rail="rows"><span class="sl-seg">${cells(false)}</span></div>` +
      `<div class="wn-rail-run" data-wn-rail="steps"><span class="sl-seg sl-seg-pager" data-sl-knob="help-steps">${knob}${cells(true)}</span>` +
      `<b class="wn-rail-now">${heads[0].textContent}</b></div>`;
    text.prepend(rail);
    const snake = rail.querySelector('.wn-snake');
    const line = rail.querySelector('[data-wn-rail="line"] > .wn-rail-view');
    const view = rail.querySelector('.wn-time > .wn-rail-view');
    const follow = (pane) => {
      const cell = pane.querySelector('.sl-is-active');
      if (pane.offsetParent) pane.scrollTo({ left: cell.offsetLeft - (pane.clientWidth - cell.offsetWidth) / 2, behavior: 'smooth' });
    };
    const fill = () => {
      const cell = view.querySelector('.sl-is-active');
      view.firstChild.style.setProperty('--wn-time-fill', (cell.offsetLeft + cell.offsetWidth / 2) + 'px');
    };
    const ends = () => [line, view].forEach((pane) => {
      pane.parentElement.classList.toggle('wn-is-fit', pane.scrollWidth <= pane.clientWidth + 1);
      pane.previousSibling.classList.toggle('sl-is-muted', pane.scrollLeft < 1);
      pane.nextSibling.classList.toggle('sl-is-muted', pane.scrollLeft + pane.clientWidth > pane.scrollWidth - 2);
    });
    const stuck = () => getComputedStyle(rail).position === 'sticky';
    let now = heads[0];
    let pin = false;
    const mark = (head) => {
      if (head === now) return;
      now = head;
      const at = heads.indexOf(head);
      rail.querySelectorAll('.wn-rail-run').forEach((run) => run.querySelectorAll('[data-wn-to]').forEach((cell, i) => {
        cell.classList.toggle('sl-is-active', i === at);
        cell.classList.toggle('wn-is-done', i < at);
      }));
      rail.querySelector('.wn-rail-now').textContent = head.textContent;
      follow(line);
      follow(view);
      fill();
    };
    const read = () => {
      if (pin) return;
      const edge = (stuck() ? rail.getBoundingClientRect().bottom : 0) + 24;
      let hit = heads[0];
      for (const head of heads) if (head.getBoundingClientRect().top <= edge) hit = head;
      mark(hit);
    };
    const size = () => {
      setWnSnake(snake);
      fill();
      ends();
      box.style.setProperty('--wn-rail-h', (stuck() ? rail.offsetHeight : 0) + 'px');
    };
    rail.addEventListener('click', (e) => {
      const cell = e.target.closest('[data-wn-to]');
      const step = e.target.closest('[data-wn-time]');
      const pane = step && step.parentElement.querySelector('.wn-rail-view');
      if (pane) pane.scrollBy({ left: step.dataset.wnTime * pane.clientWidth * 0.7, behavior: 'smooth' });
      if (!cell) return;
      pin = true;
      mark(heads.find((head) => head.id === cell.dataset.wnTo));
    });
    for (const type of ['wheel', 'touchmove', 'keydown']) window.addEventListener(type, () => { pin = false; }, { passive: true });
    window.addEventListener('scroll', () => requestAnimationFrame(read), { passive: true });
    for (const pane of [line, view]) pane.addEventListener('scroll', () => requestAnimationFrame(ends), { passive: true });
    new ResizeObserver(size).observe(rail);
    new MutationObserver(size).observe(root, { attributes: true });
    setWnHelpLook(root);
    size();
    box.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
  });
}

/* The wrappers of the help take the classes of the window of the theme by the axis: one window round the whole text, a window round its opening and round
   every section, or none. A head with no name of its own - the whole text, the opening - shows only as the head of a window */
function setWnHelpLook(root) {
  const text = root.querySelector('[data-wn-help] .sl-markdown');
  if (!text || !text.firstChild) return;
  const win = root.getAttribute('data-wn-helpwin');
  const dress = (node, on, bare) => {
    const [cap, body] = node.children;
    node.className = on ? 'sl-pane' : '';
    cap.className = on ? 'sl-pane-cap' : '';
    body.className = on ? 'sl-pane-body' : '';
    cap.hidden = bare && !on;
    cap.querySelector('.bi').hidden = !on;
  };
  dress(text.querySelector('[data-wn-all]'), win === 'one', true);
  text.querySelectorAll('[data-wn-part]').forEach((part) => {
    dress(part, win === 'parts', part.getAttribute('data-wn-part') === 'intro');
    part.querySelector('h2').className = win === 'parts' || part.getAttribute('data-wn-part') === 'intro' ? 'sl-pane-title' : '';
  });
}

/* The desk lays the open axes four to a row */
function getWnDesk(axes) {
  const axis = ([key, name, values]) => `<span class="wn-axis"><span class="wn-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${
    values.map(([val, text]) => `<button type="button" class="sl-seg-cell" data-wn-set="${key}:${val}" aria-pressed="false">${text}</button>`).join('')}</span></span>`;
  const reset = '<button type="button" class="sl-but wn-desk-reset" data-wn-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Набор варианта</button>';
  const rows = [];
  for (let i = 0; i < axes.length; i += 4) rows.push(axes.slice(i, i + 4));
  return `<div class="sl-box wn-desk" role="group" aria-label="Пульт стенда">${rows.map((row, i) =>
    `<div class="wn-desk-row">${i ? '' : '<span class="wn-desk-cap">Пульт стенда</span>'}${row.map(axis).join('')}${i === rows.length - 1 ? reset : ''}</div>`).join('')}` +
    '<p class="wn-desk-set" data-wn-say></p></div>';
}

/* The desk follows the attributes of the root, so a set written by hand and a set pressed on the desk read the same */
function setWnDesk(root, axes) {
  const said = [];
  for (const [key, name, values] of axes) {
    const now = root.getAttribute('data-wn-' + key);
    document.querySelectorAll(`[data-wn-set^="${key}:"]`).forEach((cell) => {
      const on = cell.dataset.wnSet === key + ':' + now;
      cell.classList.toggle('sl-is-active', on);
      cell.setAttribute('aria-pressed', String(on));
    });
    const hit = values.find(([val]) => val === now);
    said.push(name.toLowerCase() + ' — ' + (hit ? hit[1].toLowerCase() : now));
  }
  document.querySelector('[data-wn-say]').textContent = 'Набор: ' + said.join(' · ');
}

/* A press anywhere on the head of a folding window goes to its fold control, which stays the one button of the head */
function initWn() {
  const root = document.querySelector('[data-wn-root]');
  if (!root) return;
  const face = Object.fromEntries(WN_AXES.map(([key]) => [key, root.getAttribute('data-wn-' + key)]));
  const asks = (root.getAttribute('data-wn-ask') || '').split(' ').filter(Boolean);
  const open = (key) => !asks.length || asks.includes(key);
  const axes = WN_AXES.filter(([key]) => open(key));
  root.innerHTML = getWnRooms(open);
  const sync = () => {
    setWnDesk(root, axes);
    setWnHelpLook(root);
  };
  root.insertAdjacentHTML('beforebegin', getWnDesk(axes));
  sync();
  setWnHelp(root);
  if (asks.length && asks.every((key) => key.startsWith('help') || key === 'seg')) {
    document.querySelectorAll('.sl-tabs-nav-main .sl-tabs-link').forEach((tab) => tab.classList.toggle('sl-is-active', tab.textContent === 'Справка'));
  }
  root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
  document.addEventListener('click', (e) => {
    const cell = e.target.closest('[data-wn-set]');
    if (cell) {
      const [key, val] = cell.dataset.wnSet.split(':');
      root.setAttribute('data-wn-' + key, val);
      sync();
      return;
    }
    if (e.target.closest('[data-wn-reset]')) {
      for (const [key, val] of Object.entries(face)) root.setAttribute('data-wn-' + key, val);
      sync();
      return;
    }
    if (!e.target.closest('a, button, input, select, textarea, label, [data-sl-toggle-control]')) e.target.closest('.wn-cap')?.querySelector('.wn-fold')?.click();
  });
}

initWn();
