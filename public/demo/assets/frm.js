/* Forum topic series: the page of one topic of the forum (modules/forum, op=view) in the column of the site, drawn by
   the markup the theme prints on the live page (fragments/forum-topic-view.html, forum-post.html, pane-cap.html).
   A face is one empty .fr-root carrying its set of values:
   - data-fr-wrap="none|posts|all" — the window: none, round the messages alone, round the messages, the pager and the form
   - data-fr-rule="off|dash" — the rule between two messages
   - data-fr-form="bare|pane" — the quick reply as it is, or in a window of its own (inside the one window it stays bare)
   - data-fr-acts="head|foot" — the buttons of the topic at the top and under the messages, or under the messages alone
   - data-fr-count="5|15" — the messages on the page, the one axis of the content: a short page and a long one
   - data-fr-type="small|h4|h3|h2" — the size of the name of a head: 13 or 16 semibold, 18 or 20 regular; 18 is the theme's
   - data-fr-isize="xs|sm|md|lg" — the size of the bare icon of a head, a step of --sl-size-icon-*: 16, 18, 24 or 32; the side
     column stands outside the root, so both values are mirrored onto <html>
   The frame and the head are the theme's
   This file fills the root from the attributes and puts the desk over it: a button data-fr-set="<axis>:<value>" writes
   the attribute and the page is drawn again, data-fr-reset returns the set of the face, data-fr-say names the set.
   The people, the texts and the figures are invented; nothing is sent anywhere */

const FR_AXES = [
  ['wrap', 'Окно', [['none', 'Нет'], ['posts', 'Сообщения'], ['all', 'Всё вместе']]],
  ['rule', 'Черта', [['off', 'Нет'], ['dash', 'Пунктир']]],
  ['form', 'Форма ответа', [['bare', 'Как есть'], ['pane', 'В окне']]],
  ['acts', 'Кнопки темы', [['head', 'Сверху и снизу'], ['foot', 'Только снизу']]],
  ['count', 'Сообщений', [['5', '5'], ['15', '15']]],
  ['type', 'Заголовок шапки', [['small', '13'], ['h4', '16'], ['h3', '18'], ['h2', '20']]],
  ['isize', 'Размер иконки', [['xs', '16'], ['sm', '18'], ['md', '24'], ['lg', '32']]],
];


const FR_TOPIC = { title: 'Дубли страниц в поиске и канонический адрес', total: 49 };

const FR_USERS = {
  veter: { nick: 'veter', face: 'presets/003.svg', group: 'Посетители', stars: 2, tone: '#8297a2', rate: '4.33', points: '1 289', since: '18.04.2017' },
  marta: {
    nick: 'Marta', face: 'presets/011.svg', group: 'Пользователи', stars: 3, tone: '#2e8fff', rate: '3.88', points: '4 120', since: '02.11.2014',
    sig: '<a href="#">Заметки о поиске и разметке</a><br><a href="#">Каталог полезных инструментов</a>',
  },
  admin: {
    nick: 'SLAED CMS', face: 'presets/001.svg', group: 'Администратор', stars: 5, tone: '#e08a00', rate: '3.24', points: '40 391', since: '17.06.2005',
    rank: 'Администратор', sig: '<em>Всё великое просто</em>',
  },
  kedr: { nick: 'kedr', face: 'presets/019.svg', group: 'Новички', stars: 1, tone: '#8297a2', rate: '0', points: '64', since: '30.07.2017' },
};

const FR_POSTS = [
  {
    by: 'veter', at: '05.08.2017 05:51:38', vote: true, fields: [['Версия системы', 'SLAED CMS 6.2 Pro'], ['Версия PHP', '7'], ['Сервер', 'Сервер в интернете']],
    text: '<p>Здравствуйте. Поисковик показывает одну и ту же страницу каталога под тремя адресами:</p>' +
      '<p>index.php?name=files&amp;op=view&amp;id=3857<br>/index.php?id=3857&amp;name=files&amp;op=view<br>/index.php?&amp;op=view&amp;id=3857&amp;name=files</p>' +
      '<p>Посещаемость за месяц упала вдвое. В кабинете вебмастера советуют прописать канонический адрес. Подскажите, как это сделать правильно?</p>',
  },
  {
    by: 'veter', at: '05.08.2017 05:53:58',
    text: '<p>И ещё вижу такое:</p><p>/index.php/index.php?id=3857&amp;name=files&amp;op=view</p>' +
      '<p>Как убрать повтор и отдать на него 404?</p>',
  },
  { by: 'veter', at: '05.08.2017 06:02:00', text: '<p>Помогите настроить robots.txt</p>' },
  {
    by: 'marta', at: '05.08.2017 09:59:18',
    text: '<p>Канонический адрес нужен давно, и дело не в одном поисковике: дубли не выпадают из индекса, они просто перестают показываться, ' +
      'а вес страницы делится между копиями. Запрет в robots.txt здесь не поможет: у всех трёх адресов общая часть, и правилу не за что зацепиться.<br>' +
      'Быстрые форматы выдачи и микроразметка упираются в то же самое: поисковику нужен один адрес страницы, а не три.</p>' +
      '<blockquote><p>Как убрать повтор и отдать на него 404?</p></blockquote>' +
      '<p>Здесь нужен редирект 301, а не 404. Пока канонического адреса нет, главной копией поисковик считает то одну, то другую, ' +
      'и после каждого обхода страница заново ищет своё место в выдаче. Канонический адрес закрепляет её, и робот перестаёт ходить по одному и тому же по сто раз.</p>',
  },
  { by: 'veter', at: '05.08.2017 10:12:41', text: '<p>Спасибо. А редирект ставить в .htaccess или это делает сама система?</p>' },
  {
    by: 'admin', at: '05.08.2017 10:40:03',
    text: '<p>В текущей ветке это решено в ядре: у каждой страницы один читаемый адрес, остальные формы отвечают редиректом 301 на него, ' +
      'а в шапке стоит <code>rel="canonical"</code>. Отдельных правил в .htaccess для этого не нужно.</p>' +
      '<p>Для версии 6.2 временное решение такое:</p><ol><li>Включить единый адрес сайта в «Конфигурациях».</li>' +
      '<li>Добавить в шаблон шапки ссылку на канонический адрес страницы.</li><li>Закрыть в robots.txt адреса с параметром <code>go</code>.</li></ol>',
  },
  { by: 'kedr', at: '05.08.2017 10:44:15', text: '<p>Подпишусь на тему, у меня то же самое на каталоге файлов.</p>' },
  { by: 'veter', at: '05.08.2017 10:51:09', text: '<p>Шаблон шапки — это какой файл в теме?</p>' },
  { by: 'admin', at: '05.08.2017 10:58:30', text: '<p>В теме по умолчанию это <code>partials/site-header.html</code>.</p>' },
  {
    by: 'veter', at: '05.08.2017 11:01:12',
    text: '<p>Сделал первые два пункта. Сайту восемь лет, на старые адреса стоят тысячи внешних ссылок, поэтому менять сами адреса я боюсь: ' +
      'всё, что накоплено, уйдёт в никуда.</p>',
  },
  {
    by: 'marta', at: '05.08.2017 11:04:47',
    text: '<blockquote><p>На старые адреса стоят тысячи внешних ссылок</p></blockquote>' +
      '<p>При редиректе 301 вес ссылок переходит на новый адрес, так что накопленное не пропадает. Просядет на время — да, недели на две-три, ' +
      'пока робот не обойдёт сайт заново. Карту сайта после включения единого адреса стоит пересобрать и отправить повторно, это заметно ускоряет обход.</p>',
  },
  { by: 'veter', at: '05.08.2017 11:06:32', text: '<p>Понял, спасибо. Тогда включаю и жду.</p>' },
  { by: 'kedr', at: '05.08.2017 11:13:35', text: '<p>А карта сайта пересобирается сама или её надо запускать руками?</p>' },
  {
    by: 'admin', at: '05.08.2017 11:14:16',
    text: '<p>Сама, по расписанию: раз в сутки, если в «Карте сайта» включена автоматическая генерация. Вручную — кнопкой на той же странице панели.</p>',
  },
  { by: 'veter', at: '05.08.2017 11:25:46', text: '<blockquote><p>Раз в сутки</p></blockquote><p>Отлично, значит завтра уже посмотрю, что изменилось в кабинете вебмастера.</p>' },
];

const FR_TOOLS = [
  [['sl-editor-icon sl-editor-icon-fullscreen', 'Развернуть редактор на весь экран'], ['heading', 'Заголовки'],
    ['bold', 'Жирный'], ['italic', 'Курсив'], ['strike', 'Зачеркнутый']],
  [['hrline', 'Линия'], ['quote', 'Цитата']],
  [['bullet-list', 'Неупорядоченный список'], ['ordered-list', 'Упорядоченный список'], ['task-list', 'Галочка'], ['indent', 'Увеличить отступ'], ['outdent', 'Уменьшить отступ']],
  [['table', 'Вставить таблицу'], ['sl-editor-icon sl-editor-icon-files', 'Вставить изображение'], ['link', 'Вставить ссылку']],
  [['code', 'Вставить код'], ['codeblock', 'Вставить блок кода']],
  [['sl-editor-icon sl-editor-icon-hide', 'Скрытый текст'], ['sl-editor-icon sl-editor-icon-tabs', 'Вкладки'], ['sl-editor-icon sl-editor-icon-emoji', 'Смайлы']],
];

const FR_FIELDS = [['Версия системы', ['Нет', 'SLAED CMS 6.2 Pro', 'SLAED CMS 6.3 Pro']], ['Версия PHP', ['Нет', '5', '7']],
  ['Сервер', ['Нет', 'Сервер в интернете', 'Локальный сервер']]];

function getFrIcon(name) {
  return `<i class="bi bi-${name}" aria-hidden="true"></i>`;
}

function getFrShare() {
  return '<span class="sl-dial"><button class="sl-dial-toggle sl-but-mini" type="button" title="Поделиться" aria-expanded="false">' + getFrIcon('share-fill') + '</button>' +
    '<button class="sl-dial-item sl-but-mini" type="button" title="QR-код">' + getFrIcon('qr-code') + '</button>' +
    '<button class="sl-dial-item sl-but-mini" type="button" title="Копировать ссылку">' + getFrIcon('link-45deg') + '</button>' +
    '<button class="sl-dial-item sl-but-mini" type="button" title="Ещё">' + getFrIcon('three-dots') + '</button></span>';
}

function getFrMenu() {
  return '<span class="sl-dial"><button class="sl-dial-toggle sl-but-mini" type="button" title="Пользователь" aria-expanded="false"><i class="bi bi-three-dots"></i></button>' +
    '<a class="sl-dial-item sl-but-mini" href="#" title="Ответить персонально"><i class="bi bi-reply"></i></a>' +
    '<a class="sl-dial-item sl-but-mini" href="#" title="Отправить личное сообщение"><i class="bi bi-envelope"></i></a>' +
    '<a class="sl-dial-item sl-but-mini" href="#" title="Персональная информация"><i class="bi bi-person"></i></a>' +
    '<a class="sl-dial-item sl-but-mini" href="#" title="Ответить с цитированием данного сообщения"><i class="bi bi-chat-quote"></i></a></span>';
}

function getFrRate(num, own) {
  return `<div class="sl-rate"><span class="sl-hidden">${num}</span><div class="sl-rate-like">` +
    `<p title="Плохо" class="sl-but-mini sl-rate-minus sl-out">${getFrIcon('hand-thumbs-down')}</p>` +
    `<span title="Рейтинг: ${num}" class="sl-rate-num${own ? ' sl-rate-is' : ''}">${num}</span>` +
    `<p title="Отлично" class="sl-but-mini sl-rate-plus sl-out">${getFrIcon('hand-thumbs-up')}</p></div></div>`;
}

function getFrActs() {
  return `<b><a href="#" title="Открыть новую тему" class="sl-but">${getFrIcon('plus-lg')} Открыть</a></b>` +
    `<b><a href="#" title="Ответить на тему" class="sl-but">${getFrIcon('reply')} Ответить</a></b>`;
}

function getFrFav() {
  return `<button type="button" class="sl-but-mini" title="Добавить в фавориты" aria-label="Добавить в фавориты">${getFrIcon('star')}</button>`;
}

/* One message, as fragments/forum-post.html prints it; the vote of a message belongs to the first one alone */
function getFrPost(one, n) {
  const who = FR_USERS[one.by];
  const stars = Array.from({ length: 5 }, (v, i) => `<i class="bi bi-star-fill${i < who.stars ? ' sl-is-active' : ''}" aria-hidden="true"></i>`).join('');
  const tip = '<div class="sl-float sl-tip"><button type="button" aria-label="Подробности"><span class="sl-tip-icon">' + getFrIcon('info-circle-fill') + '</span></button>' +
    `<div class="sl-float-panel" role="tooltip"><dl><dt>Группа</dt><dd>${who.group}</dd><dt>Пункты</dt><dd>${who.points}</dd>` +
    `<dt>Регистрация</dt><dd>${who.since}</dd></dl></div></div>`;
  const rows = (one.fields || []).map(([name, text]) =>
    `<div class="sl-value-row"><span class="sl-value-label">${name}</span><div class="sl-value-text">${text}</div></div>`).join('');
  return `<div id="fr-p${n}" class="fr-post"><div class="sl-forum-post"><div class="sl-forum-post-in"><div class="sl-fp-right"><div class="sl-fp-meta">` +
    `<div class="sl-pull-right sl-meta-actions"><div class="sl-min-rate"><div class="sl-rate-like-box">${one.vote ? getFrRate('0', false) : ''}</div></div>` +
    `${getFrShare()}${getFrMenu()}` +
    `<a href="#fr-p${n}" title="Сообщение: ${n}" class="sl-but-mini">${n}</a></div>` +
    `<span class="sl-grey"><span title="Добавлено" class="sl-chip sl-chip-info sl-topic-post">${getFrIcon('clock-history')} ${one.at}</span></span></div>` +
    `<div class="sl-entry-content"><div>${one.text}</div>${rows}<div class="sl-sig">${who.sig ? '<hr><p>' + who.sig + '</p>' : ''}</div></div></div>` +
    `<div class="sl-fp-left"><div class="sl-fp-nickname">${tip}<b><a href="#" title="Персональная информация">${who.nick}</a></b></div>` +
    `<div class="sl-com-ava"><img src="../templates/aurora/images/avatars/${who.face}" alt="${who.nick}" title="${who.nick}" class="sl-avatar"></div>` +
    `<div class="sl-min-rate"><div class="sl-rate-like-box">${getFrRate(who.rate, true)}</div></div><ul class="sl-fp-user-info"><li>${who.rank || ''}</li>` +
    `<li><span class="sl-rank" role="img" aria-label="Группа: ${who.group}" title="Группа: ${who.group}" style="--sl-d-rank: ${who.tone}; --sl-rank-color: ${who.tone}">` +
    `${stars}</span></li></ul></div></div></div></div>`;
}

/* The pager of the theme without its knob: the page is drawn again on every press of the desk, and a cell without the script paints itself */
function getFrPager(count) {
  const pages = Math.ceil(FR_TOPIC.total / count);
  const cells = [2, 3].filter((n) => n < pages).map((n) => `<a href="#" title="${n}" class="sl-seg-cell">${n}</a>`).join('') +
    (pages > 4 ? '<span class="sl-seg-cell sl-is-muted" aria-hidden="true">…</span>' : '') + `<a href="#" title="${pages}" class="sl-seg-cell">${pages}</a>`;
  return '<nav class="sl-pager" aria-label="Страницы"><div class="sl-pager-main"><div class="sl-pager-nav">' +
    `<span title="Назад" class="sl-but-mini sl-is-muted">${getFrIcon('chevron-left')}</span><span class="sl-seg sl-seg-pager"><span class="sl-seg-run sl-pager-pages">` +
    `<span title="1" aria-current="page" class="sl-seg-cell sl-is-active">1</span>${cells}</span></span>` +
    `<a href="#" title="Вперед" aria-label="Вперед" class="sl-but-mini">${getFrIcon('chevron-right')}</a></div>` +
    `<div class="sl-pager-side"><span class="sl-pager-sep" aria-hidden="true"></span>` +
    `<a href="#top" title="Наверх" aria-label="Наверх" class="sl-but-mini">${getFrIcon('arrow-up')}</a></div></div>` +
    `<p class="sl-pager-info"><b>1 из ${pages}</b><span class="sl-pager-range"> · Показаны 1–${count}</span> · <b>${FR_TOPIC.total}</b></p></nav>`;
}

/* The editor is a still of the one the live form mounts: its toolbar, an empty sheet and the switch of modes */
function getFrEditor() {
  const tool = ([cls, name]) => `<button type="button" class="${cls} toastui-editor-toolbar-icons" aria-label="${name}" title="${name}"></button>`;
  const bar = FR_TOOLS.map((set) => `<div class="toastui-editor-toolbar-group">${set.map(tool).join('')}<div class="toastui-editor-toolbar-divider"></div></div>`).join('');
  return '<div class="fr-editor"><div class="toastui-editor-defaultUI"><div class="toastui-editor-toolbar"><div class="toastui-editor-defaultUI-toolbar">' + bar + '</div></div>' +
    '<div class="toastui-editor-main toastui-editor-md-mode"><div class="toastui-editor-main-container"><div class="toastui-editor-md-container toastui-editor-md-tab-style">' +
    '<div class="toastui-editor md-mode active"><div translate="no" class="ProseMirror" role="textbox" aria-multiline="true" aria-labelledby="fr-text-label">' +
    '<div><span class="placeholder ProseMirror-widget">Текст</span><br></div></div></div></div></div></div>' +
    '<div class="toastui-editor-mode-switch" style="display: block"><div class="toastui-editor-md-tab-container" style="display: block">' +
    '<div class="toastui-editor-tabs" role="tablist">' +
    '<div class="tab-item active" role="tab" aria-selected="true">Редактор</div><div class="tab-item" role="tab" aria-selected="false">Просмотр</div></div></div>' +
    '<div class="tab-item active">Markdown</div><div class="tab-item">Визуальный</div></div></div></div>';
}

/* In a window the head names the form, so the name of the editor stays for a screen reader alone */
function getFrForm(named) {
  const label = named ? 'sl-visually-hidden' : 'sl-form-label';
  const rows = FR_FIELDS.map(([name, list], i) => `<div class="sl-form-row"><label class="sl-form-label" for="fr-field-${i}">${name}</label><div class="sl-form-field">` +
    `<select id="fr-field-${i}" class="sl-field sl-field--auto">${list.map((text) => `<option>${text}</option>`).join('')}</select></div></div>`).join('');
  return '<form action="#" method="post" class="sl-forum-reply-form"><div class="sl-form"><div class="sl-form-row">' +
    `<span id="fr-text-label" class="${label}">Текст</span>` +
    `<div class="sl-form-field">${getFrEditor()}</div></div>${rows}` +
    `<div class="sl-form-submit"><button type="submit" class="sl-but">${getFrIcon('send')} Отправить</button></div></div></form>`;
}

/* The head of a window, as fragments/pane-cap.html prints it: the icon, the name, the chips, then the tools */
function getFrCap(icon, title, level, chip, tools) {
  return `<div class="sl-pane-cap">${getFrIcon(icon)}<${level} class="sl-pane-title" title="${title}">${title}</${level}>` +
    (chip ? `<span class="sl-pane-chips"><span class="sl-chip sl-chip-neutral">${chip}</span></span>` : '') + (tools ? `<span class="fr-tools">${tools}</span>` : '') + '</div>';
}

function getFrPage(set) {
  const count = Number(set.count);
  const posts = FR_POSTS.slice(0, count).map((one, i) => getFrPost(one, i + 1)).join('');
  const acts = set.acts === 'head' ? getFrActs() : '';
  const info = '<div class="sl-block"><div class="sl-pane-cap">' + getFrIcon('grid-3x3-gap') + '<div class="sl-pane-title">Публикации на форуме</div></div><div class="sl-block-content"><div class="ico sl_info">Чтобы создавать темы и отвечать на ' +
    'сообщения форума, участник должен состоять в группе не ниже «Новички», для которой нужно набрать не менее 25 пунктов.</div></div></div>';
  const top = `<div class="sl-forum-top"><span class="sl-pull-right"><span class="sl-meta-actions">${getFrShare()}</span>${acts}</span>` +
    `<h1 class="sl-heading" title="${FR_TOPIC.title}"><span class="sl-fav">${getFrFav()}</span> ${FR_TOPIC.title}</h1></div>`;
  const foot = `<div class="sl-forum-foot">${getFrPager(count)}<span class="sl-pull-right">${getFrActs()}</span></div>`;
  const cap = getFrCap('chat-left-text', FR_TOPIC.title, 'h1', getFrIcon('chat') + FR_TOPIC.total, getFrFav() + getFrShare() + acts);
  const reply = set.form === 'pane'
    ? `<section class="sl-pane fr-pane fr-reply">${getFrCap('reply', 'Форма быстрого ответа', 'h3', '', '')}<div class="sl-pane-body">${getFrForm(true)}</div></section>`
    : `<h2>Форма быстрого ответа</h2>${getFrForm()}`;
  if (set.wrap === 'all') {
    return `${info}<section class="sl-pane fr-pane fr-topic">${cap}<div class="sl-pane-body">${posts}<div class="fr-part">${foot}</div>` +
      `<div class="fr-part"><h2>Форма быстрого ответа</h2>${getFrForm()}</div></div></section>`;
  }
  if (set.wrap === 'posts') return `${info}<section class="sl-pane fr-pane fr-topic">${cap}<div class="sl-pane-body">${posts}</div></section>${foot}${reply}`;
  return `${info}${top}<div class="fr-list">${posts}</div>${foot}${reply}`;
}

function getFrDesk() {
  const axis = ([key, name, values]) => `<span class="fr-axis"><span class="fr-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${
    values.map(([val, text]) => `<button type="button" class="sl-seg-cell" data-fr-set="${key}:${val}" aria-pressed="false">${text}</button>`).join('')}</span></span>`;
  return `<div class="fr-desk" role="group" aria-label="Пульт стенда"><div class="fr-desk-row"><span class="fr-desk-cap">Пульт стенда</span>` +
    `${FR_AXES.slice(0, 2).map(axis).join('')}</div>` +
    `<div class="fr-desk-row">${FR_AXES.slice(2, 5).map(axis).join('')}</div><div class="fr-desk-row">${FR_AXES.slice(5).map(axis).join('')}</div>` +
    `<div class="fr-desk-row"><p class="fr-desk-set" data-fr-say></p>` +
    `<button type="button" class="sl-but fr-desk-reset" data-fr-reset>${getFrIcon('arrow-counterclockwise')} Набор варианта</button></div></div>`;
}

/* The page follows the attributes of the root, so a set written by hand and a set pressed on the desk read the same */
function setFrView(root) {
  const set = Object.fromEntries(FR_AXES.map(([key]) => [key, root.getAttribute('data-fr-' + key)]));
  const said = [];
  for (const [key, name, values] of FR_AXES) {
    document.querySelectorAll(`[data-fr-set^="${key}:"]`).forEach((cell) => {
      const on = cell.dataset.frSet === key + ':' + set[key];
      const idle = key === 'form' && set.wrap === 'all';
      cell.classList.toggle('sl-is-active', on && !idle);
      cell.classList.toggle('sl-is-muted', idle);
      cell.setAttribute('aria-pressed', String(on && !idle));
    });
    const hit = values.find(([val]) => val === set[key]);
    if (key !== 'form' || set.wrap !== 'all') said.push(name.toLowerCase() + ' — ' + (hit ? hit[1].toLowerCase() : set[key]));
  }
  document.querySelector('[data-fr-say]').textContent = 'Набор: ' + said.join(' · ');
  root.innerHTML = getFrPage(set);
  document.documentElement.setAttribute('data-fr-type', set.type);
  document.documentElement.setAttribute('data-fr-isize', set.isize);
  root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
}

function initFr() {
  const root = document.querySelector('[data-fr-root]');
  if (!root) return;
  const face = Object.fromEntries(FR_AXES.map(([key]) => [key, root.getAttribute('data-fr-' + key)]));
  root.insertAdjacentHTML('beforebegin', getFrDesk());
  setFrView(root);
  document.addEventListener('submit', (e) => { if (e.target.closest('[data-fr-root]')) e.preventDefault(); });
  document.addEventListener('click', (e) => {
    const cell = e.target.closest('[data-fr-set]');
    if (cell) {
      const [key, val] = cell.dataset.frSet.split(':');
      root.setAttribute('data-fr-' + key, val);
      setFrView(root);
      return;
    }
    if (e.target.closest('[data-fr-reset]')) {
      for (const [key, val] of Object.entries(face)) root.setAttribute('data-fr-' + key, val);
      setFrView(root);
      return;
    }
    if (e.target.closest('[data-fr-root] a[href="#"]')) e.preventDefault();
  });
}

initFr();
