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
   This file fills the root with the four rooms, the same in every face, and puts the desk over it: a button
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
];

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

function getWnRooms() {
  const feed = (n, shut, chip) => getWnWin({
    icon: 'rss', title: `RSS-канал ${n}`, fold: `wn-feed-${n}`, shut, chip,
    body: `<div class="sl-div-grid">${getWnInput(`wn-feed-${n}-name`, 'Название', n === 1 ? 'Новости SLAED' : '')}${getWnInput(`wn-feed-${n}-url`, 'Адрес', n === 1 ? 'https://slaed.net/rss' : '')}` +
      `${getWnSelect(`wn-feed-${n}-use`, 'Использование', ['Сторонний канал', 'Стандартный канал'])}</div>`,
  });
  const meter = '<div class="sl-meter"><div class="sl-meter-head"><span>Занято и в резерве</span><b>31%</b></div>' +
    '<span class="sl-meter-bar sl-knob-ok" role="meter" aria-label="Занято и в резерве" aria-valuemin="0" aria-valuemax="100" aria-valuenow="31"><i style="--sl-d-part: 31%"></i></span>' +
    '<span class="sl-meter-note">73,36 GB из 238,35 GB</span></div>';
  const room = (name, note, html) => `<div class="wn-room"><p class="wn-room-cap"><b>${name}</b><span>${note}</span></p>${html}</div>`;
  return room('Приборы', 'Как «Мониторинг»: три окна в ряд', `<div class="wn-trio">${
    getWnWin({ icon: 'window-stack', title: 'Программы', body: getWnKv([['SLAED CMS', '8.0.0 Phoenix', 'window-stack'], ['PHP', '8.5.12', 'filetype-php'], ['MariaDB', '11.8.3', 'database'], ['Nginx', '1.29.2', 'hdd-rack']], true) })}${
    getWnWin({ icon: 'shield-check', title: 'Безопасность', meta: 'За сутки', body: getWnKv([['Неудачные входы', '0'], ['Ошибки базы', 'Нет', 'good'], ['Журнал ошибок', '3'], ['HTTPS', 'Включено', 'on']]) })}${
    getWnWin({ icon: 'hdd', title: 'Диск', body: meter + getWnKv([['Свободно', '164,99 GB'], ['Состояние', 'Норма', 'good']]) })}</div>`) +
  room('Настройки группами', 'Как «Пользователи → Конфигурации»: каждая группа в окне', `<div class="wn-stack">${
    getWnWin({ icon: 'person-bounding-box', title: 'Аватар', body: `<div class="sl-div-grid">${getWnSwitch('wn-aupload', 'Разрешить загрузку аватаров на сервер?', true)}${
      getWnInput('wn-adir', 'Папка аватаров внутри uploads/', 'avatars')}${getWnInput('wn-asize', 'Размер одного файла, MB', '5', 'number')}${
      getWnInput('wn-aheight', 'Максимально допустимая высота изображения, в пикселях', '256', 'number', 'Изображение шире или выше этих границ уменьшается до них при загрузке, с сохранением пропорций.')}</div>` })}${
    getWnWin({ icon: 'person-check', title: 'Регистрация, вход и профиль', body: `<div class="sl-div-grid">${getWnSelect('wn-pass', 'Минимальная длина пароля', ['8', '10', '12'])}${
      getWnSwitch('wn-nomail', 'Регистрация без подтверждения на E-Mail?', false)}${getWnInput('wn-stop', 'Запрещённые имена', 'alter,drop,insert,outfile,select,truncate,union')}</div>` })}</div>`) +
  room('Сворачиваемые группы', 'Как «RSS-каналы» и «Дополнительные поля»: одно раскрыто, два свёрнуты', `<div class="wn-stack">${
    feed(1, false, ['success', 'Включён'])}${feed(2, true, ['neutral', 'Пусто'])}${feed(3, true, ['neutral', 'Пусто'])}</div>`) +
  room('Короткая форма и таблица', 'Короткая форма стоит в одном окне «Основные», кнопка под ним; таблица в окно не ставится', `<div class="sl-box"><div class="sl-div-form">${
    getWnWin({ icon: 'sliders', title: 'Основные', body: `<div class="sl-div-grid">${
    getWnInput('wn-cnum', 'Записей на странице сайта', '15', 'number', 'Пусто — как в общих настройках: 15.')}${getWnSelect('wn-csort', 'Сортировка', ['В порядке убывания', 'В порядке возрастания'])}${
    getWnSwitch('wn-calert', 'Уведомлять администраторов о новых поступлениях?', true)}</div>` })}` +
    '<div class="sl-div-actions"><button type="button" class="sl-but-blue"><i class="bi bi-check2" aria-hidden="true"></i> Сохранить изменения</button></div></div></div>' +
    '<div class="sl-box"><table class="sl-table-list-sort"><thead><tr><th class="sl-col-id">№</th><th>Категория</th><th>Материалы</th><th class="sl-col-status">Статус</th></tr></thead><tbody>' +
    [[31, 'Устройство', 12], [62, 'Темы', 14], [64, 'Модули', 2], [63, 'Блоки', 2]].map(([id, name, sum]) => `<tr><td class="sl-col-id">${id}</td><td>${name}</td><td>${sum}</td><td class="sl-col-status"><span class="sl-status-active"></span></td></tr>`).join('') +
    '</tbody></table></div>');
}

function getWnDesk() {
  const axis = ([key, name, values]) => `<span class="wn-axis"><span class="wn-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${
    values.map(([val, text]) => `<button type="button" class="sl-seg-cell" data-wn-set="${key}:${val}" aria-pressed="false">${text}</button>`).join('')}</span></span>`;
  return `<div class="sl-box wn-desk" role="group" aria-label="Пульт стенда"><div class="wn-desk-row"><span class="wn-desk-cap">Пульт стенда</span>${WN_AXES.slice(0, 4).map(axis).join('')}</div>` +
    `<div class="wn-desk-row">${WN_AXES.slice(4).map(axis).join('')}<button type="button" class="sl-but wn-desk-reset" data-wn-reset><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Набор варианта</button></div>` +
    '<p class="wn-desk-set" data-wn-say></p></div>';
}

/* The desk follows the attributes of the root, so a set written by hand and a set pressed on the desk read the same */
function setWnDesk(root) {
  const said = [];
  for (const [key, name, values] of WN_AXES) {
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
  root.innerHTML = getWnRooms();
  root.insertAdjacentHTML('beforebegin', getWnDesk());
  setWnDesk(root);
  root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
  document.addEventListener('click', (e) => {
    const cell = e.target.closest('[data-wn-set]');
    if (cell) {
      const [key, val] = cell.dataset.wnSet.split(':');
      root.setAttribute('data-wn-' + key, val);
      setWnDesk(root);
      return;
    }
    if (e.target.closest('[data-wn-reset]')) {
      for (const [key, val] of Object.entries(face)) root.setAttribute('data-wn-' + key, val);
      setWnDesk(root);
      return;
    }
    if (!e.target.closest('a, button, input, select, textarea, label, [data-sl-toggle-control]')) e.target.closest('.wn-cap')?.querySelector('.wn-fold')?.click();
  });
}

initWn();
