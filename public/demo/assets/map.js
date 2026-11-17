/* The page of the module sitemap (modules/sitemap) in four looks, each built from markup the theme aurora already
   draws somewhere else. The one empty .mp-root carries the look:
   - data-mp-view="panes" — a window per section (fragments/pane.html), a category opens as a question of the FAQ list
     (fragments/node/faq/card.html) onto the rows of the cabinet
   - data-mp-view="rail" — the rail of a Node list (partials/node/list.html): the sections on the left as tabs, the chosen
     one on the right under the heads of a list by date (fragments/date-head.html)
   - data-mp-view="tiles" — a window per section with the category tiles of a Node head (fragments/category-row.html),
     the pages as the chips of a tile
   - data-mp-view="tree" — a folding window per section (fragments/pane.html, is_fold) round the numbered tree the page
     prints now (fragments/list.html)
   The look "panes" with its seven additions on is the one the site carries (partials/sitemap.html of the theme); it has seven additions, each an axis of its own with the values on and off:
   - data-mp-jump — a row of chips under the title, a chip leads to the window of its section
   - data-mp-all — one button beside the title opens or shuts every branch
   - data-mp-find — a field over the map hides the rows that do not match and opens the branches that do
   - data-mp-fold — the plus of a folding window in every head (fragments/pane.html, is_fold)
   - data-mp-sum — chips beside the title: the sections, the categories, the pages and the time the map was built
   - data-mp-fresh — the mark of a fresh page (fragments/fresh.html) in the row of a page added in the last days
   - data-mp-tone — the icon of a head and the chip of a section in the tone a category tile wears (sl-cat-tone-N)
   The content is the same in every look: the sections, their categories with the count of pages, the first pages of a
   category and the way to the rest. This file fills the root and puts the desk over it: a button
   data-mp-set="<axis>:<value>" writes the attribute and the page is drawn again, data-mp-reset returns the set of the
   site, data-mp-say names the set. The sections follow the stand, the pages and the figures are invented */

const MP_VIEWS = [
  ['panes', 'Окна и ветки', 'окно на раздел, категория раскрывается как вопрос списка FAQ, страницы стоят строками кабинета'],
  ['rail', 'Рельс', 'рельс списка Node: разделы слева вкладками, справа выбранный раздел под шапками категорий со счётчиком'],
  ['tiles', 'Плитки', 'окно на раздел, категории стоят плитками шапки Node, страницы — чипами плитки'],
  ['tree', 'Дерево', 'сворачиваемое окно на раздел, внутри нумерованное дерево, как страница печатает его сейчас'],
];

const MP_ADDS = [
  ['jump', 'Переход по разделам'],
  ['all', 'Раскрыть всё'],
  ['find', 'Фильтр'],
  ['fold', 'Сворачивание окна'],
  ['sum', 'Сводка'],
  ['fresh', 'Метка свежего'],
  ['tone', 'Тон раздела'],
];

const MP_ROWS = [['jump', 'all', 'find', 'fold'], ['sum', 'fresh', 'tone']];

/* The pages added in the last days: the tier of the mark, its glyph and the time its tip tells */
const MP_FRESH = {
  'Перенос сайта на другой хостинг': ['now', 'fire', 'Сегодня, 09:14'],
  'Фотогалерея с альбомами': ['day', 'stopwatch', 'Вчера, 18:40'],
  'Тёмная схема для своей темы': ['days', 'calendar-event', '14.11.2026'],
  'Поддержка новой версии MariaDB': ['now', 'fire', 'Сегодня, 07:02'],
  'Как настроить отправку почты?': ['days', 'calendar-event', '13.11.2026'],
  'Команда': ['week', 'clock-history', '10.11.2026'],
  'Руководство по htmx': ['day', 'stopwatch', 'Вчера, 11:25'],
  'Календарь событий 1.0': ['days', 'calendar-event', '15.11.2026'],
  'Шаблоны и фрагменты': ['week', 'clock-history', '11.11.2026'],
};

const MP_TREE = [
  {
    key: 'forum', icon: 'chat-left-text', name: 'Форум', cats: [
      ['Установка, настройка и использование', 614, ['Установка и настройка веб-сервера с нуля', 'Вопрос про lite и pro', 'Перенос сайта на другой хостинг', 'Не приходят письма после регистрации']],
      ['Разное', 213, ['Бесплатные версии системы', 'Как изменять данные', 'Сколько может выдержать база данных MySQL', 'Функция предварительного просмотра']],
      ['Сторонние модули', 198, ['Доска объявлений', 'Модуль онлайн просмотра', 'RSS Grabber', 'Фотогалерея с альбомами']],
      ['Заказы и предложения, купля и продажа', 176, ['Ищу разработчика модуля', 'Продаётся новостная система обмена трафиком', 'Сделаю тему оформления на заказ', 'Нужен перенос сайта с другой системы']],
      ['Блоки', 143, ['Хиты и хосты', 'Украшение блока', 'Блок последних сообщений форума', 'Блок случайной статьи']],
      ['Темы оформления', 107, ['Как исправить тему оформления', 'Как изменить размер под логотип', 'Адаптация темы под телефон', 'Тёмная схема для своей темы']],
    ],
  },
  {
    key: 'news', icon: 'newspaper', name: 'Новости', cats: [
      ['Наши новости', 42, ['Производительность SLAED CMS относительно новых версий PHP', 'Бесплатная система управления содержимым SLAED CMS 6.2 Pro', 'Новая система статистики сайта']],
      ['Обновления', 27, ['Вышло обновление безопасности', 'Обновление редактора и файлового менеджера', 'Поддержка новой версии MariaDB']],
      ['События', 9, ['Проекту двадцать лет', 'Итоги конкурса тем оформления', 'Открыт раздел документации']],
    ],
  },
  {
    key: 'faq', icon: 'question-circle', name: 'Вопросы и ответы', cats: [
      ['Установка', 18, ['Какие требования у системы к хостингу?', 'Как установить систему на локальный сервер?', 'Что делать, если установщик не видит базу?']],
      ['Настройка', 31, ['Как и где можно установить свою тему оформления?', 'Как включить читаемые адреса?', 'Как настроить отправку почты?']],
      ['Безопасность', 12, ['Как сменить пароль администратора?', 'Как закрыть сайт на время работ?', 'Что такое блокировка по числу попыток входа?']],
    ],
  },
  {
    key: 'content', icon: 'file-text', name: 'Содержание', cats: [
      ['', 6, ['О проекте', 'Лицензия', 'Команда', 'Контакты']],
    ],
  },
  {
    key: 'links', icon: 'link-45deg', name: 'Каталог сайтов', cats: [
      ['Сайты на SLAED CMS', 58, ['Музыкальный портал', 'Городской справочник', 'Сайт школы программирования']],
      ['Хостинг и домены', 23, ['Хостинг с поддержкой новых версий PHP', 'Регистратор доменов', 'Облачные серверы для небольших проектов']],
      ['Веб-разработка', 37, ['Справочник по HTML и CSS', 'Руководство по htmx', 'Инструменты проверки доступности']],
    ],
  },
  {
    key: 'files', icon: 'download', name: 'Каталог файлов', cats: [
      ['SLAED CMS', 3, ['SLAED CMS 6.2 Pro', 'Open SLAED 1.3.1', 'SLAED Lite 2.6']],
      ['Модули', 212, ['Доска объявлений 2.1', 'Фотогалерея 1.4', 'Календарь событий 1.0']],
      ['Блоки', 148, ['Блок погоды', 'Блок курсов валют', 'Блок случайной цитаты']],
      ['Темы оформления', 96, ['Celestial', 'Тёмная тема Graphite', 'Тема для сайта школы']],
    ],
  },
  {
    key: 'docs', icon: 'book', name: 'Документация', cats: [
      ['Введение', 7, ['Что такое SLAED CMS', 'Как устроена система', 'Словарь терминов']],
      ['Администрирование', 34, ['Панель администратора', 'Резервные копии', 'Группы и права']],
      ['Разработка модулей', 26, ['Структура модуля', 'Работа с базой данных', 'Шаблоны и фрагменты']],
    ],
  },
];

function getMpIcon(name) {
  return `<i class="bi bi-${name}" aria-hidden="true"></i>`;
}

function getMpNum(num) {
  return String(num).replace(/\B(?=(\d{3})+$)/g, ' ');
}

function getMpSum(sec) {
  return sec.cats.reduce((all, one) => all + one[1], 0);
}

function getMpLink(text) {
  return `<a href="#" title="${text}">${text}</a>`;
}

/* The head of a window, as fragments/pane-cap.html prints it: the icon, the linked name, the chips, then the plus of a folding window */
function getMpCap(sec, fold, open) {
  const named = sec.cats.filter((one) => one[0] !== '').length;
  const chips = (named ? `<span class="sl-chip sl-chip-neutral" title="Категории">${getMpIcon('folder')}${named}</span>` : '') +
    `<span class="sl-chip sl-chip-neutral" title="Страницы">${getMpIcon('file-earmark-text')}${getMpNum(getMpSum(sec))}</span>`;
  const plus = fold ? `<span class="sl-plus sl-pane-fold${open ? ' sl-is-open' : ''}" title="Свернуть или развернуть" aria-label="Свернуть или развернуть" ` +
    `data-sl-toggle-control="mp-${sec.key}-rows" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"></span>` : '';
  return `<div class="sl-pane-cap"${fold ? ' data-sl-toggle-head' : ''}>${getMpIcon(sec.icon)}<h2 class="sl-pane-title">${getMpLink(sec.name)}</h2>` +
    `<span class="sl-pane-chips">${chips}</span>${plus}</div>`;
}

/* The mark of a fresh page, as fragments/fresh.html prints it: the glyph of its tier and the time in its tip */
function getMpFresh(text) {
  const hit = MP_FRESH[text];
  if (!hit) return '';
  return `<span class="sl-tip sl-float sl-fresh sl-fresh-${hit[0]}" tabindex="0">${getMpIcon(hit[1])}` +
    `<div class="sl-float-panel"><dl><dt>Добавлено</dt><dd><time>${hit[2]}</time></dd></dl></div></span>`;
}

/* The pages of one category as rows of the cabinet, closed by the way to the rest of them */
function getMpRows([name, num, pages], fresh) {
  const rest = num - pages.length;
  return pages.map((text) => `<div class="sl-cab-row" data-mp-row>${getMpIcon('file-earmark-text')}${getMpLink(text)}${fresh ? getMpFresh(text) : ''}</div>`).join('') +
    (rest > 0 ? `<div class="sl-cab-row" data-mp-rest>${getMpIcon('three-dots')}${getMpLink('Ещё ' + getMpNum(rest))}</div>` : '');
}

/* The row of the title: the summary and the button of all branches stand at its right, the field and the chips of the sections under it */
function getMpHead(set) {
  const cats = MP_TREE.reduce((all, sec) => all + sec.cats.filter((one) => one[0] !== '').length, 0);
  const pages = MP_TREE.reduce((all, sec) => all + getMpSum(sec), 0);
  const sum = (set.sum === 'on') ? `<span class="sl-chip sl-chip-neutral" title="Разделы">${getMpIcon('grid')}${MP_TREE.length}</span>` +
    `<span class="sl-chip sl-chip-neutral" title="Категории">${getMpIcon('folder')}${cats}</span>` +
    `<span class="sl-chip sl-chip-neutral" title="Страницы">${getMpIcon('file-earmark-text')}${getMpNum(pages)}</span>` +
    `<span class="sl-chip sl-chip-info" title="Карта собрана">${getMpIcon('clock-history')}17.11.2026 04:00</span>` : '';
  const all = (set.all === 'on') ? `<button type="button" class="sl-but" data-mp-act="all" aria-pressed="false">${getMpIcon('arrows-expand')} Раскрыть всё</button>` : '';
  const find = (set.find === 'on') ? '<div class="mp-find"><input type="search" class="sl-field" data-mp-ask placeholder="Найти раздел, категорию или страницу" ' +
    'aria-label="Найти раздел, категорию или страницу"></div>' : '';
  const jump = (set.jump === 'on') ? '<nav class="mp-jump" aria-label="Разделы">' + MP_TREE.map((sec, n) =>
    `<a class="sl-chip ${set.tone === 'on' ? 'sl-chip-tone sl-cat-tone-' + (n % 6) : 'sl-chip-neutral'}" href="#mp-${sec.key}" title="${sec.name}">${getMpIcon(sec.icon)}${sec.name}</a>`).join('') + '</nav>' : '';
  return `<div class="mp-head"><h1 class="sl-title">Карта сайта</h1>${sum || all ? `<span class="mp-tools">${sum}${all}</span>` : ''}</div>${find}${jump}`;
}

function getMpPanes(set) {
  const fold = set.fold === 'on';
  const fresh = set.fresh === 'on';
  const none = '<div class="sl-empty sl-empty-small" data-mp-none hidden>' + getMpIcon('search') + '<b>Ничего не найдено</b></div>';
  return getMpHead(set) + MP_TREE.map((sec, n) => {
    const body = sec.cats.map((one) => (one[0] === '') ? getMpRows(one, fresh) :
      `<details class="sl-node-faq" data-mp-cat><summary>${getMpIcon('chevron-right')}<h3 class="sl-title">${one[0]}</h3>` +
      `<span class="sl-chip sl-chip-neutral">${getMpNum(one[1])}</span></summary><div class="sl-node-faq-body">${getMpRows(one, fresh)}</div></details>`).join('');
    const rows = fold ? `<div id="mp-${sec.key}-rows" class="sl-collapsible" data-sl-toggle="mp-${sec.key}-rows" data-sl-toggle-default="open"><div class="sl-pane-body">${body}</div></div>`
      : `<div class="sl-pane-body">${body}</div>`;
    return `<section id="mp-${sec.key}" class="sl-pane${set.tone === 'on' ? ' sl-cat-tone-' + (n % 6) : ''}" data-mp-sec>${getMpCap(sec, fold, true)}${rows}</section>`;
  }).join('') + none;
}

function getMpRail() {
  const tabs = MP_TREE.map((sec, i) => `<li class="sl-node-toc" role="presentation"><a href="#" title="${sec.name}"${i ? '' : ' class="sl-is-active"'} ` +
    `data-sl-tab-link data-sl-tab-target="mp-${sec.key}">${sec.name}</a><span class="sl-chip sl-chip-neutral">${getMpNum(getMpSum(sec))}</span></li>`).join('');
  const rooms = MP_TREE.map((sec, i) => {
    const body = sec.cats.map((one) => ((one[0] === '') ? '' :
      `<p class="sl-date-head">${getMpIcon('folder')} ${one[0]} <span class="sl-chip sl-chip-neutral">${getMpNum(one[1])}</span></p>`) +
      `<div class="mp-rows">${getMpRows(one)}</div>`).join('');
    return `<article id="mp-${sec.key}" data-sl-tab-panel="mp-${sec.key}"${i ? ' hidden' : ''}><h2 class="sl-title">${getMpLink(sec.name)}</h2>${body}</article>`;
  }).join('');
  return `<div class="sl-duo sl-node-rail" data-sl-tabs-init="mp-rail"><aside class="sl-duo-aside"><ul role="tablist" aria-orientation="vertical" aria-label="Карта сайта">${tabs}</ul></aside>` +
    `<div class="sl-duo-main">${rooms}</div></div>`;
}

function getMpTiles() {
  return MP_TREE.map((sec) => {
    const body = sec.cats.map(([name, num, pages], i) => {
      const rest = num - pages.length;
      const subs = pages.map((text) => `<a class="sl-chip sl-chip-neutral" href="#" title="${text}">${getMpIcon('file-earmark-text')}<span class="mp-cut">${text}</span></a>`).join('') +
        (rest > 0 ? `<a class="sl-chip sl-chip-neutral" href="#" title="Все страницы">${getMpIcon('three-dots')}Ещё ${getMpNum(rest)}</a>` : '');
      return `<div class="sl-cat-tile sl-cat-tone-${i % 6}"><a class="sl-cat-main" href="#" title="${name || sec.name}">` +
        `<span class="sl-cat-ico">${getMpIcon(name ? 'folder' : sec.icon)}</span><span class="sl-cat-info"><span class="sl-cat-line">` +
        `<span class="sl-cat-name">${name || sec.name}</span><span title="Страницы" class="sl-chip sl-chip-info">${getMpNum(num)}</span></span></span></a>` +
        `<span class="sl-cat-subs">${subs}</span></div>`;
    }).join('');
    return `<section class="sl-pane">${getMpCap(sec)}<div class="sl-pane-body"><div class="sl-cat-grid">${body}</div></div></section>`;
  }).join('');
}

/* The numbered tree of fragments/list.html: a category with its pages under it, a section without categories lists its pages at once */
function getMpList(sec) {
  const leaf = ([name, num, pages]) => pages.map((text) => `<li>${getMpLink(text)}</li>`).join('') +
    (num > pages.length ? `<li>${getMpLink('Ещё ' + getMpNum(num - pages.length))}</li>` : '');
  return sec.cats.map((one) => (one[0] === '') ? leaf(one) : `<li>${getMpLink(one[0])}<ol class="sl-sublist">${leaf(one)}</ol></li>`).join('');
}

function getMpFold() {
  return MP_TREE.map((sec, i) => `<section id="mp-${sec.key}" class="sl-pane">${getMpCap(sec, true, !i)}` +
    `<div id="mp-${sec.key}-rows" class="sl-collapsible" data-sl-toggle="mp-${sec.key}-rows" data-sl-toggle-default="${i ? 'closed' : 'open'}"${i ? ' hidden' : ''}>` +
    `<div class="sl-pane-body"><ol class="sl-list">${getMpList(sec)}</ol></div></div></section>`).join('');
}

function getMpDesk() {
  const seg = (name, cells) => `<span class="fr-axis"><span class="fr-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${cells}</span></span>`;
  const cell = (key, val, text) => `<button type="button" class="sl-seg-cell" data-mp-set="${key}:${val}" aria-pressed="false">${text}</button>`;
  const adds = MP_ROWS.map((keys, n) => `<div class="fr-desk-row">${n ? '' : '<span class="fr-desk-cap">Дополнения</span>'}` +
    keys.map((key) => seg(MP_ADDS.find((one) => one[0] === key)[1], cell(key, 'off', 'Нет') + cell(key, 'on', 'Есть'))).join('') + '</div>').join('');
  return '<div class="fr-desk" role="group" aria-label="Пульт стенда"><div class="fr-desk-row"><span class="fr-desk-cap">Пульт стенда</span>' +
    seg('Вид', MP_VIEWS.map(([key, name]) => cell('view', key, name)).join('')) + '</div>' + adds +
    '<div class="fr-desk-row"><p class="fr-desk-set" data-mp-say></p>' +
    `<button type="button" class="sl-but fr-desk-reset" data-mp-reset>${getMpIcon('arrow-counterclockwise')} Как на сайте</button></div></div>`;
}

const MP_DRAW = { panes: getMpPanes, rail: getMpRail, tiles: getMpTiles, tree: getMpFold };

/* The page follows the attributes of the root, so a set written by hand and a set pressed on the desk read the same; the additions belong to the chosen look alone */
function setMpView(root) {
  const set = Object.fromEntries(MP_ADDS.map(([key]) => [key, root.getAttribute('data-mp-' + key) === 'on' ? 'on' : 'off']));
  const view = MP_DRAW[root.dataset.mpView] ? root.dataset.mpView : 'panes';
  const hit = MP_VIEWS.find(([key]) => key === view);
  const idle = view !== 'panes';
  document.querySelectorAll('[data-mp-set]').forEach((cell) => {
    const [key, val] = cell.dataset.mpSet.split(':');
    const mute = key !== 'view' && idle;
    const on = !mute && ((key === 'view') ? val === view : set[key] === val);
    cell.classList.toggle('sl-is-active', on);
    cell.classList.toggle('sl-is-muted', mute);
    cell.setAttribute('aria-pressed', String(on));
  });
  const adds = MP_ADDS.filter(([key]) => set[key] === 'on').map(([key, name]) => name.toLowerCase());
  document.querySelector('[data-mp-say]').textContent = 'Вид: ' + hit[1].toLowerCase() + ' — ' + hit[2] + '.' +
    (idle ? '' : ' Дополнения: ' + (adds.length ? adds.join(' · ') : 'нет') + '.');
  root.innerHTML = (view === 'panes') ? getMpPanes(set) : '<h1 class="sl-title">Карта сайта</h1>' + MP_DRAW[view]();
  root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
}

/* The button of all branches names what a press will do, so it reads the branches as they stand */
function setMpAll(root) {
  const btn = root.querySelector('[data-mp-act="all"]');
  if (!btn) return;
  const cats = [...root.querySelectorAll('[data-mp-cat]')].filter((one) => !one.hidden);
  const open = cats.length > 0 && cats.every((one) => one.open);
  btn.innerHTML = getMpIcon(open ? 'arrows-collapse' : 'arrows-expand') + (open ? ' Свернуть всё' : ' Раскрыть всё');
  btn.setAttribute('aria-pressed', String(open));
}

/* The filter keeps a section whose name matches whole, a category whose name matches with all its pages, and otherwise the pages that match with their branch open */
function setMpFind(root, text) {
  const ask = text.trim().toLowerCase();
  let seen = 0;
  root.querySelectorAll('[data-mp-sec]').forEach((sec) => {
    const whole = ask === '' || sec.querySelector('.sl-pane-title').textContent.toLowerCase().includes(ask);
    let kept = 0;
    const walk = (box, all) => {
      let rows = 0;
      box.querySelectorAll(':scope > [data-mp-row]').forEach((row) => {
        const on = all || row.textContent.toLowerCase().includes(ask);
        row.hidden = !on;
        rows += on ? 1 : 0;
      });
      box.querySelectorAll(':scope > [data-mp-rest]').forEach((row) => { row.hidden = !all; });
      return rows;
    };
    kept += walk(sec.querySelector('.sl-pane-body'), whole);
    sec.querySelectorAll('[data-mp-cat]').forEach((cat) => {
      const named = whole || cat.querySelector('.sl-title').textContent.toLowerCase().includes(ask);
      const rows = walk(cat.querySelector('.sl-node-faq-body'), named);
      cat.hidden = !named && rows === 0;
      cat.open = ask !== '' && !whole && rows > 0;
      kept += cat.hidden ? 0 : 1;
    });
    sec.hidden = !whole && kept === 0;
    seen += sec.hidden ? 0 : 1;
  });
  root.querySelector('[data-mp-none]').hidden = seen > 0;
  setMpAll(root);
}

function initMp() {
  const root = document.querySelector('[data-mp-root]');
  if (!root) return;
  const ask = new URLSearchParams(location.search).get('view');
  if (ask && MP_DRAW[ask]) root.dataset.mpView = ask;
  root.insertAdjacentHTML('beforebegin', getMpDesk());
  setMpView(root);
  document.addEventListener('input', (e) => { if (e.target.matches('[data-mp-ask]')) setMpFind(root, e.target.value); });
  document.addEventListener('toggle', (e) => { if (e.target.matches && e.target.matches('[data-mp-cat]')) setMpAll(root); }, true);
  document.addEventListener('click', (e) => {
    const cell = e.target.closest('[data-mp-set]');
    if (cell) {
      const [key, val] = cell.dataset.mpSet.split(':');
      root.setAttribute('data-mp-' + key, val);
      setMpView(root);
      return;
    }
    if (e.target.closest('[data-mp-reset]')) {
      root.dataset.mpView = 'panes';
      MP_ADDS.forEach(([key]) => root.setAttribute('data-mp-' + key, 'on'));
      setMpView(root);
      return;
    }
    const all = e.target.closest('[data-mp-act="all"]');
    if (all) {
      const open = all.getAttribute('aria-pressed') !== 'true';
      root.querySelectorAll('[data-mp-cat]').forEach((one) => { if (!one.hidden) one.open = open; });
      setMpAll(root);
      return;
    }
    if (e.target.closest('[data-mp-root] a[href="#"]')) e.preventDefault();
  });
}

initMp();
