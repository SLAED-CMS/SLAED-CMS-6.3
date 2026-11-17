/* The page of the module users (modules/users) as one hybrid built from markup the theme aurora already draws
   somewhere else. The rating wears the head of a Node list (partials/node/head.html), and the switch of the look in
   that head (fragments/mode-switch.html) turns the same list into one of three looks:
   - podium — the first three as the rail of a profile (partials/account-profile.html) in one window, the rest as the
     rows of the cabinet (sl-cab-row) in a second one
   - cards — a window per member in the grid of the settings (sl-opt-grid), the facts as the rows of a profile
   - steps — a folding window per group (fragments/pane.html, is_fold), its members as the rows of the cabinet
   The one empty .us-root carries the set:
   - data-us-page="new|now" — the hybrid, or the page as the module printed it before the hybrid was taken
   - data-us-nav="wins|links|tabs" — the way to the rules and to the statistics: two pills of the head that open a window
     of the site under it each (fragments/pane.html); two pills that lead to a page each; or the tabs of the theme
     (partials/tabs.html) round all three rooms
   - data-us-view — the look the page opens in; the switch of the page itself writes it
   - data-us-room="top|rules|stats" — the room that is open; the tabs and the pills that lead to a page write it
   - data-us-lists="rows|tiles|table" — the form of the rules and of the groups: rows of the cabinet, the tiles of a Node
     head (fragments/category-row.html), the tables of today
   The hybrid has ten additions, each an axis of its own with the values on and off:
   - data-us-find — the search of the head filters the members by name
   - data-us-group — a pill of the head opens the window of the groups, a tile narrows the list to its group
   - data-us-sort — a pill of the head opens the orders, and a link beside it turns the direction
   - data-us-me — a window over the list tells the member his own place and the way to the next step
   - data-us-more — the button beside the pager adds the next page under the list (sl-node-more)
   - data-us-self — the reader finds himself in the list by a chip beside his name
   - data-us-fresh — a member who came in the last week wears the mark of a fresh material (fragments/fresh.html)
   - data-us-lead — the first three places wear the lit state of the place button
   - data-us-jump — a group of the statistics leads to the rating of that group, the window of the own place to the rules
   - data-us-plain — a rule tells its limit and its period in words, as one chip
   This file fills the root and puts the desk over it: a button data-us-set="<axis>:<value>" writes the attribute and the
   page is drawn again, data-us-reset returns the set of the face, data-us-say names the set. The groups follow the
   stand, the members and the figures are invented */

const US_LOOKS = [
  ['podium', 'Пьедестал', 'trophy'],
  ['cards', 'Карточки', 'person-vcard'],
  ['steps', 'Ступени', 'bar-chart-steps'],
];

const US_PAGES = [
  ['new', 'Гибрид', 'рейтинг носит шапку списка Node, вид переключает бегунок в ней'],
  ['now', 'Как было', 'ряд кнопок, заголовок и таблица без окна, как страница печатала их до переноса'],
];

const US_NAVS = [
  ['wins', 'Окна в шапке', 'кнопки «Правила» и «Статистика» в шапке списка открывают свои окна под ней'],
  ['links', 'Страницы', 'кнопки «Правила» и «Статистика» в шапке списка ведут на свои страницы'],
  ['tabs', 'Вкладки', 'три раздела стоят вкладками'],
];

const US_LISTS = [
  ['rows', 'Строки', 'строками кабинета'],
  ['tiles', 'Плитки', 'плитками шапки Node'],
  ['table', 'Таблица', 'таблицами прежней страницы'],
];

const US_ADDS = [
  ['find', 'Поиск участника'],
  ['group', 'Фильтр по группе'],
  ['sort', 'Сортировка'],
  ['me', 'Ваше место'],
  ['more', 'Показать ещё'],
  ['self', 'Метка «Вы»'],
  ['fresh', 'Был недавно'],
  ['lead', 'Первые трое'],
  ['jump', 'Переходы'],
  ['plain', 'Период словами'],
];

const US_ROOMS = [
  ['top', 'Рейтинг', 'trophy'],
  ['rules', 'Правила', 'stars'],
  ['stats', 'Статистика', 'people'],
];

const US_SORTS = [
  ['points', 'Пункты', 'stars'],
  ['since', 'Регистрация', 'calendar3'],
  ['last', 'Последний визит', 'clock-history'],
  ['nick', 'Имя', 'sort-alpha-down'],
];

/* The groups in the order the page of the statistics lists them: the special ones first, then the steps by their threshold */
const US_GROUPS = [
  { key: 'client', name: 'Клиент', text: 'Клиенты системы', from: 0, num: 91, spec: true, mark: ['record-circle-fill', 5, '#629abf'], ring: '#629abf' },
  { key: 'modcms', name: 'Модераторы «Система построения сайтов»', text: 'Модераторы форума «Система построения сайтов»', from: 0, num: 2, spec: true, mark: ['star-fill', 5, '#b0904d'], ring: '#b0904d' },
  { key: 'partner', name: 'Партнёры', text: 'Официальные партнёры проекта', from: 0, num: 0, spec: true, mark: ['sun-fill', 5, '#c68649'], ring: '#c68649' },
  { key: 'modweb', name: 'Модераторы «Вебмастер и вебдизайн»', text: 'Модераторы форума «Вебмастер и вебдизайн»', from: 0, num: 1, spec: true, mark: ['star-fill', 5, '#b0904d'], ring: '#b0904d' },
  { key: 'super', name: 'Супермодераторы', text: 'Модераторы всех форумов', from: 0, num: 1, spec: true, mark: ['star-fill', 5, '#c68649'], ring: '#c68649' },
  { key: 'new', name: 'Новички', text: 'Новые посетители портала, проявляющие низкую активность', from: 25, num: 865, spec: false, mark: ['star-fill', 0, '#8297a2'], ring: '#8297a2' },
  { key: 'guest', name: 'Посетители', text: 'Посетители портала, не проявляющие большой активности', from: 250, num: 82, spec: false, mark: ['star-fill', 1, '#8297a2'], ring: '#8297a2' },
  { key: 'user', name: 'Пользователи', text: 'Пользователи портала, проявляющие небольшую активность', from: 1000, num: 25, spec: false, mark: ['star-fill', 2, '#8297a2'], ring: '#35b56a' },
  { key: 'local', name: 'Местные', text: 'Пользователи портала, которые активно посещают сайт и высказывают своё мнение', from: 5000, num: 2, spec: false, mark: ['star-fill', 3, '#8297a2'], ring: '#2e8fff' },
  { key: 'active', name: 'Активисты', text: 'Постоянные пользователи портала, которые проявляют особую активность на страницах сайта', from: 10000, num: 2, spec: false, mark: ['star-fill', 4, '#8297a2'], ring: '#b06fdc' },
  { key: 'expert', name: 'Опытные', text: 'Пользователи портала, которые приняли самое активное участие в работе сайта', from: 15000, num: 4, spec: false, mark: ['star-fill', 5, '#8297a2'], ring: '#e08a00' },
];

/* A member: the name, the preset of the face, the points, the registration, the last visit, the origin, the site, the gender.
   The first fifteen are the first page, the rest come with the button of the next page */
const US_USERS = [
  ['SLAED CMS', '001', 40391, '17.06.2005', '17.11.2026 12:19', 'Germany', 'https://slaed.net', 'Мужчина'],
  ['Aquilon', '007', 32679, '06.05.2011', '24.10.2026 15:26', 'Ковров', '', 'Мужчина'],
  ['Marta', '011', 29827, '27.09.2006', '16.11.2026 21:40', 'Екатеринбург', '', 'Женщина'],
  ['Nordwind', '014', 20840, '23.08.2007', '02.11.2026 08:12', 'Зеленогорск', '', 'Мужчина'],
  ['gizmo', '022', 10975, '15.10.2009', '11.11.2026 19:03', 'Москва', '', 'Мужчина'],
  ['polaris', '005', 10766, '28.10.2005', '30.09.2026 10:47', 'Москва', '', 'Мужчина'],
  ['kestrel', '031', 6248, '19.07.2005', '14.11.2026 23:15', 'Таллин', '', 'Мужчина'],
  ['Tessa', '017', 5816, '06.07.2007', '08.11.2026 13:30', '', '', 'Женщина'],
  ['veter', '003', 4475, '25.02.2008', '17.11.2026 09:02', '', '', 'Мужчина'],
  ['harbor', '026', 3849, '21.07.2006', '21.10.2026 17:55', 'Санкт-Петербург', '', 'Мужчина'],
  ['lumen', '009', 2641, '09.03.2014', '05.11.2026 06:20', '', '', 'Нет информации'],
  ['Quartz', '035', 2513, '12.10.2013', '12.11.2026 22:08', '', '', 'Нет информации'],
  ['a-line', '020', 2219, '09.10.2006', '29.10.2026 12:00', '', '', 'Мужчина'],
  ['mirella', '038', 2204, '06.09.2006', '15.11.2026 18:44', 'Саратов', '', 'Женщина'],
  ['kedr', '019', 2176, '23.02.2009', '03.11.2026 20:31', '', '', 'Мужчина'],
  ['Onyx', '002', 2104, '14.01.2010', '10.11.2026 07:48', 'Казань', '', 'Мужчина'],
  ['briza', '013', 2057, '30.05.2012', '01.11.2026 16:12', '', '', 'Женщина'],
  ['Falke', '024', 1988, '11.11.2008', '13.11.2026 11:36', 'Wien', '', 'Мужчина'],
  ['tundra', '033', 1931, '02.04.2015', '27.10.2026 21:09', 'Мурманск', '', 'Нет информации'],
  ['Selena', '016', 1876, '19.08.2011', '16.11.2026 08:25', 'Минск', '', 'Женщина'],
  ['pixel', '028', 1812, '07.12.2006', '09.11.2026 19:50', '', '', 'Мужчина'],
  ['Corvus', '006', 1764, '25.06.2013', '04.11.2026 14:02', 'Рига', '', 'Мужчина'],
  ['ivushka', '037', 1701, '03.03.2009', '15.11.2026 10:17', 'Тверь', '', 'Женщина'],
  ['Borealis', '021', 1655, '16.09.2016', '18.10.2026 23:41', '', '', 'Мужчина'],
  ['mango', '010', 1598, '28.02.2012', '12.11.2026 06:33', 'Сочи', '', 'Нет информации'],
  ['Zenit', '030', 1540, '09.07.2007', '31.10.2026 18:27', 'Самара', '', 'Мужчина'],
  ['ladoga', '015', 1486, '21.10.2010', '07.11.2026 13:05', 'Выборг', '', 'Женщина'],
  ['Grafit', '039', 1433, '05.05.2014', '14.11.2026 20:58', '', '', 'Мужчина'],
  ['sova', '004', 1379, '12.12.2008', '02.11.2026 09:44', 'Пермь', '', 'Женщина'],
  ['Vesper', '027', 1322, '27.01.2017', '11.11.2026 22:19', '', '', 'Мужчина'],
].map(([nick, face, points, since, last, from, site, gender], i) => ({ place: i + 1, nick, face, points, since, last, from, site, gender }));

/* The rules of config/points.php: the action, its icon, the points, the period in seconds and the limit of a period */
const US_RULES = [
  ['Публикация материала или темы', 'pencil-square', 10, 86400, 10],
  ['Комментарий или ответ', 'chat-text', 5, 86400, 30],
  ['Засчитанный просмотр', 'eye', 0, 86400, 50],
  ['Засчитанное скачивание', 'download', 3, 86400, 20],
  ['Засчитанный переход по внешней ссылке', 'box-arrow-up-right', 0, 86400, 20],
  ['Голос в опросе', 'bar-chart', 3, 86400, 10],
  ['Добавление в избранное', 'bookmark', 0, 86400, 20],
  ['Сообщение или обращение', 'envelope', 0, 86400, 20],
  ['Рекомендация сайта', 'megaphone', 0, 86400, 5],
  ['Регистрация', 'person-plus', 0, 0, 0],
  ['Засчитанный вход', 'box-arrow-in-right', 0, 86400, 1],
  ['Подтверждённая полезная жалоба', 'flag', 3, 86400, 5],
  ['Завершённое действие модератора', 'shield-check', 0, 86400, 100],
];

const US_TOTAL = 11931;
const US_LAST = 796;
const US_STEP = 15;

/* The member who reads the page, for the window of his own place, and the day he reads it on */
const US_ME = 'veter';
const US_TODAY = '17.11.2026';

/* The set the page is drawn with; the pieces of a member read their additions from it */
let usOn = {};

/* What the visitor chose in the head of the list; the desk and the tabs leave it alone */
const usPick = { find: '', group: '', sort: 'points', asc: false, shown: US_STEP };

function getUsIcon(name) {
  return `<i class="bi bi-${name}" aria-hidden="true"></i>`;
}

function getUsNum(num) {
  return String(num).replace(/\B(?=(\d{3})+$)/g, ' ');
}

/* The step of a member is the highest threshold his points have passed */
function getUsGroup(user) {
  return US_GROUPS.filter((one) => !one.spec && one.from <= user.points).pop();
}

/* The rank of a group, as fragments/rank.html prints it */
function getUsRank(grp) {
  const [icon, lit, color] = grp.mark;
  const marks = [1, 2, 3, 4, 5].map((n) => `<i class="bi bi-${icon}${n <= lit ? ' sl-is-active' : ''}" aria-hidden="true"></i>`).join('');
  return `<span class="sl-rank" role="img" aria-label="Группа: ${grp.name}" title="Группа: ${grp.name}" style="--sl-d-rank: ${color}; --sl-rank-color: ${color}">${marks}</span>`;
}

function getUsFace(user) {
  return `<img src="../templates/aurora/images/avatars/presets/${user.face}.svg" alt="${user.nick}" title="${user.nick}">`;
}

function getUsLink(user) {
  return `<a href="#" title="Персональная информация">${user.nick}</a>`;
}

/* The tip of a row, as getTplTitleTip() prints it: the registration, the last visit and the site */
function getUsTip(user) {
  return '<div class="sl-float sl-tip"><button type="button" aria-label="Подробности"><span class="sl-tip-icon">' + getUsIcon('info-circle-fill') + '</span></button>' +
    `<div class="sl-float-panel" role="tooltip"><dl><dt>Регистрация</dt><dd>${user.since}</dd><dt>Последний визит</dt><dd>${user.last}</dd>` +
    (user.site ? `<dt>Сайт</dt><dd>${user.site}</dd>` : '') + '</dl></div></div>';
}

/* The chip of the reader himself, where the addition is on */
function getUsSelf(user, wrap) {
  if (!usOn.self || user.nick !== US_ME) return '';
  return `<span class="sl-chip sl-chip-accent" title="Это вы">${getUsIcon('person-check')}${wrap ? '<span>Вы</span>' : 'Вы'}</span>`;
}

/* The mark of a fresh visit, as fragments/fresh.html prints it: today, yesterday, the last three days, the last week */
function getUsFresh(user) {
  if (!usOn.fresh) return '';
  const day = (text) => { const [d, m, y] = text.slice(0, 10).split('.'); return Date.UTC(y, m - 1, d) / 86400000; };
  const ago = day(US_TODAY) - day(user.last);
  const hit = (ago < 1) ? ['now', 'fire'] : (ago < 2) ? ['day', 'stopwatch'] : (ago <= 3) ? ['days', 'calendar-event'] : (ago <= 7) ? ['week', 'clock-history'] : null;
  if (!hit) return '';
  return `<span class="sl-tip sl-float sl-fresh sl-fresh-${hit[0]}" tabindex="0">${getUsIcon(hit[1])}` +
    `<div class="sl-float-panel"><dl><dt>Последний визит</dt><dd><time>${user.last}</time></dd></dl></div></span>`;
}

/* The place button of the first three is lit, where the addition is on */
function getUsLit(place) {
  return (usOn.lead && place <= 3) ? ' sl-is-active' : '';
}

/* The limit of a rule and its period in words */
function getUsLimit(period, limit) {
  if (!period || !limit) return 'Без лимита';
  return 'До ' + limit + ' ' + ({ 3600: 'в час', 86400: 'в сутки', 604800: 'в неделю' }[period] || 'за ' + getUsNum(period) + ' с');
}

/* The chips of a member wear what the chips of a news card wear (fragments/node/card.html): a count is the plain chip of the reads,
   a date the blue chip of a date, a group the green link of a category */
function getUsPoints(user) {
  return `<span class="sl-chip us-pts" title="Пункты">${getUsIcon('stars')}${getUsNum(user.points)}</span>`;
}

function getUsGeo(user) {
  return user.from ? `<span class="sl-chip us-geo" title="Откуда">${getUsIcon('geo-alt')}${user.from}</span>` : '';
}

/* In a row the date is one of the chips that leave a narrow window first */
function getUsDate(user, row) {
  return `<time title="Регистрация" class="sl-chip sl-chip-info${row ? ' us-geo' : ''}">${getUsIcon('calendar3')}${user.since}</time>`;
}

/* The chip of a group leads to the rating of that group, where the filter is on */
function getUsClan(grp) {
  return `<a href="#" title="Группа: ${grp.name}" class="sl-chip"${usOn.group ? ` data-us-grp="${grp.key}"` : ''}>${getUsIcon('award')}${grp.name}</a>`;
}

/* The head of a window, as fragments/pane-cap.html prints it: the icon, the name, the chips, then the plus of a folding window */
function getUsCap(icon, title, chips, fold) {
  const plus = fold ? `<span class="sl-plus sl-pane-fold sl-is-open" title="Свернуть или развернуть" aria-label="Свернуть или развернуть" ` +
    `data-sl-toggle-control="${fold}-rows" data-sl-toggle-effect="slide" data-sl-toggle-duration="500"></span>` : '';
  return `<div class="sl-pane-cap"${fold ? ' data-sl-toggle-head' : ''}>${getUsIcon(icon)}<h2 class="sl-pane-title">${title}</h2>` +
    (chips ? `<span class="sl-pane-chips">${chips}</span>` : '') + plus + '</div>';
}

function getUsPane(icon, title, chips, body, tone) {
  return `<section class="sl-pane sl-cat-tone-${tone}">${getUsCap(icon, title, chips)}<div class="sl-pane-body">${body}</div></section>`;
}

/* The pager of the theme, as getTplPager() prints it over the rows the page shows of the whole list */
function getUsPager(shown) {
  const cells = [2, 3, 4, 5].map((n) => `<a href="#" title="${n}" class="sl-seg-cell">${n}</a>`).join('') +
    `<span class="sl-seg-cell sl-is-muted" aria-hidden="true">…</span><a href="#" title="${US_LAST}" class="sl-seg-cell">${US_LAST}</a>`;
  return '<nav class="sl-pager" aria-label="Страницы"><div class="sl-pager-main"><div class="sl-pager-nav">' +
    `<span title="Назад" class="sl-but-mini sl-is-muted">${getUsIcon('chevron-left')}</span><span class="sl-seg sl-seg-pager"><span class="sl-seg-run sl-pager-pages">` +
    `<span title="1" aria-current="page" class="sl-seg-cell sl-is-active">1</span>${cells}</span></span>` +
    `<a href="#" title="Вперед" aria-label="Вперед" class="sl-but-mini">${getUsIcon('chevron-right')}</a></div>` +
    '<div class="sl-pager-side"><span class="sl-pager-sep" aria-hidden="true"></span>' +
    `<a href="#top" title="Наверх" aria-label="Наверх" class="sl-but-mini">${getUsIcon('arrow-up')}</a></div></div>` +
    `<p class="sl-pager-info"><b>1 из ${US_LAST}</b><span class="sl-pager-range"> · Показаны 1–${shown}</span> · <b>${US_TOTAL}</b></p></nav>`;
}

/* A table of the theme, as fragments/table.html and table-row.html print it; a head given as [text] is a column of numbers */
function getUsTable(heads, rows) {
  const head = heads.map((one) => Array.isArray(one) ? `<th class="sl-table-col-num">${one[0]}</th>` : `<th>${one}</th>`).join('');
  const body = rows.map((cells) => '<tr class="sl-table-row">' + cells.map((one) => Array.isArray(one) ? `<td class="sl-table-cell sl-table-col-num">${one[0]}</td>`
    : `<td class="sl-table-cell">${one}</td>`).join('') + '</tr>').join('');
  return `<div class="sl-table-shell"><table class="sl-table-list" data-sl-table-sort><thead class="sl-table-head"><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
}

function getUsPlace(num) {
  return `<a href="#${num}" title="${num}" class="sl-but-mini${getUsLit(num)}">${num}</a>`;
}

/* A member as a row of the cabinet: the place, the face, the name with its marks, then the origin, the registration, the tip, the rank and the points, each in a column of its window */
function getUsRow(user, rank) {
  return `<div class="sl-cab-row sl-profile-entry"><span class="sl-but-mini${getUsLit(user.place)}" title="Место">${user.place}</span><span class="sl-cab-msg-ava">${getUsFace(user)}</span>` +
    `<span class="us-who">${getUsLink(user)}${getUsSelf(user)}${getUsFresh(user)}</span><span class="sl-profile-hub-total">${getUsGeo(user) || '<span class="us-geo"></span>'}` +
    `${getUsDate(user, true)}${getUsTip(user)}${rank ? getUsRank(getUsGroup(user)) : ''}${getUsPoints(user)}</span></div>`;
}

/* The three looks of the rating: each one draws the members it is handed, in the order it is handed them */
const US_DRAW = {
  /* The podium: the first three wear the pieces of the rail of a profile, the first of them in the middle */
  podium: (list) => {
    const lead = list.slice(0, 3).map((user, n) => {
      const grp = getUsGroup(user);
      return `<div class="us-place us-place-${n + 1}"><div class="sl-profile-avatar" style="--sl-d-ring:${grp.ring}">${getUsFace(user)}</div>` +
        `<h3 class="sl-font sl-profile-name">${getUsLink(user)}</h3><div class="sl-profile-rank">${grp.name}</div>${getUsRank(grp)}` +
        `<div class="sl-profile-chips"><span class="sl-chip sl-chip-${user.place === 1 ? 'warn' : 'neutral'}" title="Место">${getUsIcon('trophy-fill')}<span>${user.place}</span></span>` +
        `<span class="sl-chip" title="Пункты">${getUsIcon('stars')}<span>${getUsNum(user.points)}</span></span>` + getUsSelf(user, true) + getUsFresh(user) + '</div></div>';
    }).join('');
    const rest = list.slice(3).map((user) => getUsRow(user, true)).join('');
    return getUsPane('trophy', 'Лидеры', `<span class="sl-chip sl-chip-neutral" title="Всего участников">${getUsIcon('people')}${getUsNum(US_TOTAL)}</span>`, `<div class="us-podium">${lead}</div>`, 4) +
      (rest ? getUsPane('list-ol', 'Рейтинг', `<span class="sl-chip sl-chip-neutral" title="На странице">${getUsIcon('people')}${list.length - 3}</span>`,
        `<div class="us-cols us-cols-who" style="--us-n:5">${rest}</div>`, 1) : '');
  },
  /* The cards: a window per member built as a card of the news is: the place at the right of the head, the chips of the meta row, the face with the facts, the button under them */
  cards: (list) => '<div class="sl-opt-grid">' + list.map((user) => {
    const grp = getUsGroup(user);
    const fact = (icon, name, text) => `<div class="sl-profile-info-row">${getUsIcon(icon)}<span>${name}</span><b title="${text}">${text || '—'}</b></div>`;
    const self = getUsSelf(user);
    return `<article class="sl-pane sl-opt-w2"><div class="sl-pane-cap">${getUsIcon('person')}<h2 class="sl-pane-title">${getUsLink(user)}</h2>${getUsFresh(user)}` +
      `${self ? `<span class="sl-pane-chips">${self}</span>` : ''}<a href="#${user.place}" title="Место" class="sl-but-mini${getUsLit(user.place)}">${user.place}</a></div>` +
      `<div class="sl-pane-body"><div class="sl-meta"><ul class="sl-pull-right"><li>${getUsPoints(user)}</li></ul><ul><li>${getUsDate(user)}</li></ul></div>` +
      `<div class="us-face"><div class="sl-com-ava" style="--sl-d-ring:${grp.ring}">` +
      `<img src="../templates/aurora/images/avatars/presets/${user.face}.svg" alt="${user.nick}" title="${user.nick}" class="sl-avatar"></div>${getUsClan(grp)}</div>` +
      `<div class="sl-profile-info">${fact('clock-history', 'Последний визит', user.last.slice(0, 10))}${fact('geo-alt', 'Откуда', user.from)}</div>` +
      `<div class="sl-meta-foot"><span class="sl-pull-right">${getUsRank(grp)}</span><a href="#" title="Персональная информация" class="sl-but">${getUsIcon('arrow-right')} Профиль</a></div></div></article>`;
  }).join('') + '</div>',
  /* The steps: a folding window per group from the highest threshold down, each over the members of the page who stand on it */
  steps: (list) => US_GROUPS.filter((grp) => !grp.spec).reverse().map((grp, n) => {
    const rows = list.filter((user) => getUsGroup(user) === grp).map((user) => getUsRow(user, false)).join('');
    if (!rows) return '';
    const chips = getUsRank(grp) + `<span class="sl-chip sl-chip-neutral us-step-from" title="Порог, пунктов">${getUsIcon('stars')}От ${getUsNum(grp.from)}</span>` +
      `<span class="sl-chip sl-chip-neutral us-step-num" title="Участники">${getUsIcon('people')}${getUsNum(grp.num)}</span>`;
    return `<section class="sl-pane sl-cat-tone-${n % 6}">${getUsCap('award', grp.name, chips, 'us-' + grp.key)}` +
      `<div id="us-${grp.key}-rows" class="sl-collapsible" data-sl-toggle="us-${grp.key}-rows" data-sl-toggle-default="open"><div class="sl-pane-body">` +
      `<div class="us-cols us-cols-who" style="--us-n:4">${rows}</div></div></div></section>`;
  }).join(''),
};

/* A tile of a Node head, as fragments/category-row.html prints it */
function getUsTile(tone, icon, name, chip, text, subs, pick) {
  return `<div class="sl-cat-tile sl-cat-tone-${tone % 6}"><a class="sl-cat-main" href="#" title="${name}"${pick || ''}><span class="sl-cat-ico">${getUsIcon(icon)}</span>` +
    `<span class="sl-cat-info"><span class="sl-cat-line"><span class="sl-cat-name">${name}</span>${chip}</span>` +
    (text ? `<span class="sl-cat-desc">${text}</span>` : '') + '</span></a>' + (subs ? `<span class="sl-cat-subs">${subs}</span>` : '') + '</div>';
}

/* The rules of the points in the three forms of the axis; the rows share the tracks of their box, so their chips stand in columns */
const US_RULE = {
  table: () => getUsTable(['Тип', ['Пункты'], ['Период'], ['Лимит']], US_RULES.map(([name, , points, period, limit]) => [name, [points], [period], [limit]])),
  rows: () => {
    const rows = US_RULES.map(([name, icon, points, period, limit]) => `<div class="sl-cab-row">${getUsIcon(icon)}<b>${name}</b><span class="sl-profile-hub-total">` +
      (usOn.plain ? `<span class="sl-chip us-geo" title="Лимит за период">${getUsIcon('arrow-repeat')}${getUsLimit(period, limit)}</span>`
        : `<span class="sl-chip us-geo" title="Период, секунд">${getUsIcon('clock-history')}${getUsNum(period)}</span>` +
          `<span class="sl-chip us-geo" title="Лимит за период">${getUsIcon('arrow-repeat')}${limit}</span>`) +
      `<span class="sl-chip${points ? ' sl-chip-success' : ''}" title="Пункты">${getUsIcon('stars')}${points ? '+' + points : '0'}</span></span></div>`).join('');
    return `<div class="us-cols" style="--us-n:${usOn.plain ? 2 : 3}">${rows}</div>`;
  },
  tiles: () => '<div class="sl-cat-grid">' + US_RULES.map(([name, icon, points, period, limit], i) => getUsTile(i, icon, name,
    `<span title="Пункты" class="sl-chip sl-chip-${points ? 'success' : 'info'}">${points ? '+' + points : '0'}</span>`, '',
    usOn.plain ? `<span class="sl-chip sl-chip-neutral" title="Лимит за период">${getUsIcon('arrow-repeat')}${getUsLimit(period, limit)}</span>`
      : `<span class="sl-chip sl-chip-neutral" title="Период, секунд">${getUsIcon('clock-history')}${getUsNum(period)}</span>` +
        `<span class="sl-chip sl-chip-neutral" title="Лимит за период">${getUsIcon('arrow-repeat')}${limit}</span>`)).join('') + '</div>',
};

/* The way from a step of the statistics to the rating of that step, where both the addition and the filter are on */
function getUsOpen(grp) {
  return (usOn.jump && usOn.group && !grp.spec) ? ` data-us-open="${grp.key}"` : '';
}

/* The groups in the same three forms */
const US_STAT = {
  table: () => getUsTable(['Ранг', 'Описание', 'Пункты', 'Члены', 'Специальная'], US_GROUPS.map((grp) => [getUsRank(grp), `<span>${grp.name}</span><br>${grp.text}`,
    String(grp.from), String(grp.num), grp.spec ? 'Да' : 'Нет'])),
  rows: () => {
    const rows = US_GROUPS.map((grp) => (getUsOpen(grp) ? `<a class="sl-cab-row" href="#" title="Рейтинг группы «${grp.name}»"${getUsOpen(grp)}>` : '<div class="sl-cab-row">') +
      `${getUsIcon(grp.spec ? 'patch-check' : 'people')}<b title="${grp.text}">${grp.name} · ${grp.text}</b><span class="sl-profile-hub-total">${getUsRank(grp)}` +
      (grp.spec ? `<span class="sl-chip sl-chip-accent us-geo" title="Специальная группа">${getUsIcon('patch-check')}Специальная</span>`
        : `<span class="sl-chip us-geo" title="Порог, пунктов">${getUsIcon('stars')}От ${getUsNum(grp.from)}</span>`) +
      `<span class="sl-chip" title="Участники">${getUsIcon('people')}${getUsNum(grp.num)}</span></span>${getUsOpen(grp) ? '</a>' : '</div>'}`).join('');
    return `<div class="us-cols">${rows}</div>`;
  },
  tiles: () => '<div class="sl-cat-grid">' + US_GROUPS.map((grp, i) => getUsTile(i, grp.spec ? 'patch-check' : 'people', grp.name,
    `<span title="Участники" class="sl-chip sl-chip-info">${getUsNum(grp.num)}</span>`, grp.text,
    `<span class="sl-chip sl-chip-neutral" title="Ранг">${getUsRank(grp)}</span>` +
    (grp.spec ? `<span class="sl-chip sl-chip-accent" title="Специальная группа">${getUsIcon('patch-check')}Специальная</span>`
      : `<span class="sl-chip sl-chip-neutral" title="Порог, пунктов">${getUsIcon('stars')}От ${getUsNum(grp.from)}</span>`), getUsOpen(grp))).join('') + '</div>',
};

/* A room of the rules or of the groups in the form of the axis; rows of the cabinet stand in a window of their own, unless a window of the head holds them already */
function getUsRoom(room, lists, bare) {
  const body = (room === 'rules' ? US_RULE : US_STAT)[lists]();
  if (bare || lists !== 'rows') return body;
  return (room === 'rules') ? getUsPane('stars', 'Правила начисления', `<span class="sl-chip sl-chip-neutral" title="Действия">${getUsIcon('list-check')}${US_RULES.length}</span>`, body, 2)
    : getUsPane('people', 'Группы', `<span class="sl-chip sl-chip-neutral" title="Группы">${getUsIcon('collection')}${US_GROUPS.length}</span>`, body, 0);
}

/* The page as it stands today: the row of buttons over the title, a bare table, and on an inner room the row of ways back under it */
function getUsNow(room) {
  const link = (key, icon, text) => `<li><a href="#" title="${text}" class="sl-but" data-us-go="${key}">${getUsIcon(icon)} ${text}</a></li>`;
  const lower = (room === 'top') ? '' : `<nav class="sl-navi-lower"><button type="button" class="sl-but" title="Назад" data-us-go="top">${getUsIcon('arrow-left')} Назад</button>` +
    `<a href="#" title="Начало" class="sl-but" data-us-go="top">${getUsIcon('house-door')} Начало</a><a href="#top" title="Наверх" class="sl-but">${getUsIcon('arrow-up')} Наверх</a></nav>`;
  const body = (room === 'rules') ? US_RULE.table() : (room === 'stats') ? US_STAT.table()
    : getUsTable([['№'], 'Ник', 'Регистрация', 'Пол', 'Откуда', 'Пункты'], US_USERS.slice(0, US_STEP).map((user) => [[getUsPlace(user.place)],
      getUsTip(user) + `<a href="#" title="Персональная информация" class="sl-author">${getUsIcon('person')}${user.nick}</a>`, user.since, user.gender, user.from, String(user.points)])) + getUsPager(US_STEP);
  return `<nav aria-label="Топ пользователей"><ul class="sl-navi-menu">${link('top', 'house-door', 'Главная')}${link('rules', 'trophy', 'Правила')}${link('stats', 'fire', 'Статистика')}</ul></nav>` +
    `<h1 class="sl-title">Топ пользователей</h1>${body}${lower}`;
}

/* The set of the root: the page, the way to the rooms, the look, the room, the form of the two lists and the additions that are on; with windows in the head there is one room */
function getUsSet(root) {
  const pick = (list, val) => list.some(([key]) => key === val) ? val : list[0][0];
  const set = { page: pick(US_PAGES, root.dataset.usPage), nav: pick(US_NAVS, root.dataset.usNav), view: pick(US_LOOKS, root.dataset.usView), room: pick(US_ROOMS, root.dataset.usRoom),
    lists: pick(US_LISTS, root.dataset.usLists) };
  if (set.page === 'new' && set.nav === 'wins') set.room = 'top';
  US_ADDS.forEach(([key]) => { set[key] = root.getAttribute('data-us-' + key) === 'on'; });
  usOn = set;
  return set;
}

/* A date of the list as a text that sorts: the year, the month, the day and the time if there is one */
function getUsStamp(text) {
  const [day, time] = text.split(' ');
  return day.split('.').reverse().join('') + (time || '');
}

/* The members the list shows: the loaded pages, narrowed by the search and the group, in the chosen order; an addition that is off does not act */
function getUsList(set) {
  const ask = set.find ? usPick.find.trim().toLowerCase() : '';
  const group = set.group ? usPick.group : '';
  const list = US_USERS.slice(0, set.more ? usPick.shown : US_STEP).filter((user) => (!ask || user.nick.toLowerCase().includes(ask)) && (!group || getUsGroup(user).key === group));
  if (!set.sort) return list;
  const val = { points: (user) => -user.points, since: (user) => getUsStamp(user.since), last: (user) => getUsStamp(user.last), nick: (user) => user.nick.toLowerCase() }[usPick.sort];
  const turn = (usPick.asc === (usPick.sort === 'points')) ? -1 : 1;
  return list.sort((a, b) => (val(a) > val(b) ? 1 : val(a) < val(b) ? -1 : 0) * turn);
}

/* Whether the visitor moved the list off its first state, which is what the chip of the reset stands for */
function getUsMoved(set) {
  return (set.group && usPick.group !== '') || (set.sort && (usPick.sort !== 'points' || usPick.asc)) || (set.find && usPick.find !== '');
}

/* The window of the own place: the place and the points as chips of its head, the way to the next step as the meter of a profile */
function getUsMe() {
  const user = US_USERS.find((one) => one.nick === US_ME);
  const grp = getUsGroup(user);
  const next = US_GROUPS.filter((one) => !one.spec && one.from > user.points)[0];
  const part = next ? Math.round((user.points - grp.from) / (next.from - grp.from) * 100) : 100;
  const note = next ? `До ступени «${next.name}» осталось ${getUsNum(next.from - user.points)} пунктов` : 'Высшая ступень';
  return getUsPane('person-check', 'Ваше место', `<span class="sl-chip sl-chip-neutral" title="Место">${getUsIcon('hash')}${user.place} из ${getUsNum(US_TOTAL)}</span>` +
    `<span class="sl-chip sl-chip-neutral" title="Пункты">${getUsIcon('stars')}${getUsNum(user.points)}</span>` +
    (usOn.jump ? `<a class="sl-chip" href="#" title="Правила начисления" data-us-go="rules">${getUsIcon('question-circle')}Как набрать</a>` : ''),
  `<div class="sl-profile-meter"><div class="sl-profile-meter-head"><span>${note}</span><b>${part}%</b></div><div class="sl-profile-meter-track"><span style="width:${part}%"></span></div></div>`, 3);
}

/* The head of a Node list for the rating: the count, the switch of the look, the search and the way up, then the pills of the filters with their windows.
   Without tabs it carries the title of the page and the two pills of the rules and the statistics; on a page of theirs it keeps the title and the ways alone */
function getUsHead(set) {
  const solo = set.nav !== 'tabs';
  const inner = set.room !== 'top';
  const cells = US_LOOKS.map(([key, name, icon]) => `<button type="submit" class="sl-seg-cell${key === set.view ? ' sl-is-active' : ''}" data-us-look="${key}" title="${name}" ` +
    `aria-label="${name}" aria-pressed="${key === set.view}">${getUsIcon(icon)}</button>`).join('');
  const look = `<form action="#" method="post" class="sl-mode"><span class="sl-seg sl-seg-view" data-sl-knob="us-look" role="group" aria-label="Вид списка">${cells}` +
    '<span class="sl-seg-knob" data-sl-knob-mark aria-hidden="true"></span></span></form>';
  const find = set.find ? '<form class="sl-search-form" role="search" action="#" method="post">' +
    `<input type="text" value="${usPick.find}" name="word" maxlength="100" placeholder="Найти участника" aria-label="Найти участника" class="sl-field" id="us-find" autocomplete="off">` +
    `<button type="submit" class="sl-search-btn sl-circle-action" aria-label="Поиск" title="Поиск">${getUsIcon('search')}</button></form>` : '';
  const lens = set.find ? '<button type="button" class="sl-but-mini" data-sl-lens="us-find" aria-controls="us-find" aria-expanded="false" title="Найти участника" aria-label="Найти участника">' +
    `${getUsIcon('search')}${getUsIcon('x-lg')}</button>` : '';
  const pill = (id, icon, name, on) => `<span class="sl-node-head-pill${on ? ' sl-is-active' : ''}"><button type="button" class="sl-but" data-sl-toggle-control="${id}" ` +
    `data-sl-toggle-scope="none" data-sl-toggle-effect="slide" data-sl-toggle-duration="250">${getUsIcon(icon)}${name}${getUsIcon('chevron-down')}</button></span>`;
  const way = ([key, name, icon]) => `<span class="sl-node-head-pill${key === set.room ? ' sl-is-active' : ''}"><a class="sl-but" href="#" title="${name}" data-us-go="${key}"` +
    `${key === set.room ? ' aria-current="true"' : ''}>${getUsIcon(icon)}<span>${name}</span></a></span>`;
  const grp = US_GROUPS.find((one) => one.key === usPick.group);
  const sort = US_SORTS.find(([key]) => key === usPick.sort);
  const turn = usPick.asc ? 'По возрастанию' : 'По убыванию';
  const sift = (set.group ? pill('us-win-group', 'people', grp ? grp.name : 'Все группы', !!grp) : '') +
    (set.sort ? pill('us-win-sort', 'filter', sort[1], usPick.sort !== 'points') + `<span class="sl-node-head-pill"><a class="sl-but" href="#" data-us-turn ` +
      `title="${turn}" aria-label="${turn}">${getUsIcon(usPick.asc ? 'sort-up' : 'sort-down')}<span>${turn}</span></a></span>` : '');
  const rooms = (set.nav === 'wins') ? pill('us-win-rules', 'stars', 'Правила', false) + pill('us-win-stats', 'people', 'Статистика', false)
    : (set.nav === 'links') ? US_ROOMS.slice(inner ? 0 : 1).map(way).join('') : '';
  const drop = `<a class="sl-chip sl-chip-info" href="#" title="Сбросить" data-us-drop${getUsMoved(set) ? '' : ' hidden'}>${getUsIcon('arrow-counterclockwise')}Сбросить</a>`;
  const win = (id, name, body) => `<div id="${id}" class="sl-node-head-win" data-sl-toggle="${id}" data-sl-toggle-group="us-head" data-sl-toggle-scope="none" data-sl-toggle-default="closed" hidden>` +
    `<p class="sl-node-head-cap">${name}</p>${body}</div>`;
  const pane = (id, icon, name, mark, num, tone, body) => `<section id="${id}" class="sl-pane sl-cat-tone-${tone}" data-sl-toggle="${id}" data-sl-toggle-group="us-head" ` +
    `data-sl-toggle-scope="none" data-sl-toggle-default="closed" hidden>${getUsCap(icon, name, `<span class="sl-chip sl-chip-neutral">${getUsIcon(mark)}${num}</span>`)}` +
    `<div class="sl-pane-body">${body}</div></section>`;
  const tiles = getUsTile(0, 'people', 'Все группы', `<span title="Участники" class="sl-chip sl-chip-info">${getUsNum(US_TOTAL)}</span>`, '', '', ` data-us-grp=""${grp ? '' : ' aria-current="true"'}`) +
    US_GROUPS.filter((one) => !one.spec).reverse().map((one, i) => getUsTile(i + 1, 'award', one.name, `<span title="Участники" class="sl-chip sl-chip-info">${getUsNum(one.num)}</span>`,
      'От ' + getUsNum(one.from) + ' пунктов', '', ` data-us-grp="${one.key}"${one.key === usPick.group ? ' aria-current="true"' : ''}`)).join('');
  const sorts = US_SORTS.map(([key, name, icon]) => `<a class="sl-chip sl-chip-neutral" href="#" title="${name}" data-us-by="${key}"${key === usPick.sort ? ' aria-current="true"' : ''}>` +
    `${getUsIcon(icon)}${name}</a>`).join('');
  const top = `<a class="sl-but-mini" href="#top" title="Наверх" aria-label="Наверх">${getUsIcon('arrow-up')}</a>`;
  return '<header class="sl-node-head" data-sl-sticky><div class="sl-node-head-row">' +
    `<div class="sl-node-head-title">${solo ? '<h1 class="sl-title">Топ пользователей</h1>' : ''}<span title="Всего участников" class="sl-chip sl-chip-neutral">${getUsNum(US_TOTAL)} участник</span></div>` +
    `<div class="sl-node-head-acts">${inner ? top : look + find + lens + top}</div>` +
    `<div class="sl-node-head-filters" role="toolbar" aria-label="Фильтры">${inner ? rooms : sift + rooms + drop}</div></div>` +
    (set.group && !inner ? win('us-win-group', 'Группа', `<div class="sl-cat-grid">${tiles}</div>`) : '') +
    (set.sort && !inner ? win('us-win-sort', 'Сортировка', `<div class="sl-node-head-sorts">${sorts}</div>`) : '') +
    (set.nav === 'wins' ? pane('us-win-rules', 'stars', 'Правила', 'list-check', US_RULES.length, 2, getUsRoom('rules', set.lists, true)) +
      pane('us-win-stats', 'people', 'Статистика', 'collection', US_GROUPS.length, 0, getUsRoom('stats', set.lists, true)) : '') + '</header>';
}

/* The list under the head: the chosen look over the members, or the empty state of a search, then the pager with the button of the next page */
function getUsBody(set) {
  const list = getUsList(set);
  const shown = set.more ? usPick.shown : US_STEP;
  const more = (set.more && shown < US_USERS.length) ? `<p class="sl-node-more"><a href="#" title="Показать ещё" class="sl-but" data-us-next>${getUsIcon('arrow-down')}Показать ещё</a></p>` : '';
  const body = list.length ? US_DRAW[set.view](list) : `<div class="sl-empty sl-empty-small">${getUsIcon('search')}<b>Ничего не найдено</b></div>`;
  return `${body}<div class="sl-node-foot">${getUsPager(shown)}${more}</div>`;
}

/* The hybrid: the rating under its head, with the rules and the statistics in windows of that head or on pages of their own; or the title, the tabs of the three rooms and the open room in their frame */
function getUsPage(set) {
  const room = (set.room === 'top') ? (set.me ? getUsMe() : '') + `<div data-us-list>${getUsBody(set)}</div>` : getUsRoom(set.room, set.lists);
  if (set.nav !== 'tabs') return getUsHead(set) + room;
  const tabs = US_ROOMS.map(([key, name, icon]) => `<li class="sl-tabs-item"><a href="#" title="${name}" class="sl-tabs-link${key === set.room ? ' sl-is-active' : ''}" ` +
    `data-us-go="${key}">${getUsIcon(icon)} ${name}</a></li>`).join('');
  return `<h1 class="sl-title">Топ пользователей</h1><div class="sl-tabs"><ul class="sl-tabs-nav">${tabs}</ul><div class="sl-tabs-content">${set.room === 'top' ? getUsHead(set) : ''}${room}</div></div>`;
}

function getUsDesk() {
  const seg = (name, cells) => `<span class="fr-axis"><span class="fr-axis-cap">${name}</span><span class="sl-seg sl-seg-view" role="group" aria-label="${name}">${cells}</span></span>`;
  const cell = (key, val, text) => `<button type="button" class="sl-seg-cell" data-us-set="${key}:${val}" aria-pressed="false">${text}</button>`;
  const adds = US_ADDS.map(([key, name]) => seg(name, cell(key, 'off', 'Нет') + cell(key, 'on', 'Есть')));
  return '<div class="fr-desk" role="group" aria-label="Пульт стенда"><div class="fr-desk-row"><span class="fr-desk-cap">Пульт стенда</span>' +
    seg('Страница', US_PAGES.map(([key, name]) => cell('page', key, name)).join('')) +
    seg('Разделы', US_NAVS.map(([key, name]) => cell('nav', key, name)).join('')) + '</div>' +
    '<div class="fr-desk-row">' + seg('Правила и статистика', US_LISTS.map(([key, name]) => cell('lists', key, name)).join('')) + '</div>' +
    `<div class="fr-desk-row"><span class="fr-desk-cap">Дополнения</span>${adds.slice(0, 3).join('')}</div><div class="fr-desk-row">${adds.slice(3, 6).join('')}</div>` +
    `<div class="fr-desk-row">${adds.slice(6).join('')}</div>` +
    '<div class="fr-desk-row"><p class="fr-desk-set" data-us-say></p>' +
    `<button type="button" class="sl-but fr-desk-reset" data-us-reset>${getUsIcon('arrow-counterclockwise')} Набор варианта</button></div></div>`;
}

/* The line under the desk names the set in words: the page, the way to the rooms, the look it opens in, the form of the two lists and the additions */
function setUsSay(set) {
  const page = US_PAGES.find(([key]) => key === set.page);
  const adds = US_ADDS.filter(([key]) => set[key]).map(([, name]) => name.toLowerCase());
  document.querySelector('[data-us-say]').textContent = 'Страница: ' + page[1].toLowerCase() + ' — ' + page[2] + '.' + (set.page === 'now' ? ''
    : ' Разделы: ' + US_NAVS.find(([key]) => key === set.nav)[2] + '. Вид при открытии: ' + US_LOOKS.find(([key]) => key === set.view)[1].toLowerCase() + '. Правила и статистика стоят ' + US_LISTS.find(([key]) => key === set.lists)[2] +
    '. Дополнения: ' + (adds.length ? adds.join(' · ') : 'нет') + '.');
}

/* The page follows the attributes of the root, so a set written by hand and a set pressed on the desk read the same; the page of today knows none of the axes of the hybrid */
function setUsView(root) {
  const set = getUsSet(root);
  const old = set.page === 'now';
  document.querySelectorAll('[data-us-set]').forEach((cell) => {
    const [key, val] = cell.dataset.usSet.split(':');
    const mute = old && key !== 'page';
    const on = !mute && ((typeof set[key] === 'boolean') ? set[key] === (val === 'on') : set[key] === val);
    cell.classList.toggle('sl-is-active', on);
    cell.classList.toggle('sl-is-muted', mute);
    cell.setAttribute('aria-pressed', String(on));
  });
  setUsSay(set);
  root.innerHTML = old ? getUsNow(set.room) : getUsPage(set);
  root.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
}

/* A new look or a typed name redraws the list alone: the head stays, so the plate of the switch travels and the field keeps its caret */
function setUsList(root) {
  const set = getUsSet(root);
  const box = root.querySelector('[data-us-list]');
  if (!box) return;
  root.querySelectorAll('[data-us-look]').forEach((cell) => {
    const on = cell.dataset.usLook === set.view;
    cell.classList.toggle('sl-is-active', on);
    cell.setAttribute('aria-pressed', String(on));
  });
  setUsSay(set);
  root.querySelector('[data-us-drop]').hidden = !getUsMoved(set);
  box.innerHTML = getUsBody(set);
  box.dispatchEvent(new CustomEvent('htmx:afterSwap', { bubbles: true, detail: {} }));
}

function initUs() {
  const root = document.querySelector('[data-us-root]');
  if (!root) return;
  const face = [...root.attributes].filter((one) => one.name.startsWith('data-us-') && one.name !== 'data-us-root').map((one) => [one.name, one.value]);
  const ask = new URLSearchParams(location.search);
  ['page', 'nav', 'view', 'room', 'lists'].forEach((key) => { if (ask.get(key)) root.setAttribute('data-us-' + key, ask.get(key)); });
  root.insertAdjacentHTML('beforebegin', getUsDesk());
  setUsView(root);
  document.addEventListener('input', (e) => {
    if (!e.target.matches('#us-find')) return;
    usPick.find = e.target.value;
    setUsList(root);
  });
  document.addEventListener('submit', (e) => { if (e.target.closest('[data-us-root]')) e.preventDefault(); });
  document.addEventListener('click', (e) => {
    const hit = (sel) => e.target.closest(sel);
    const cell = hit('[data-us-set]');
    if (cell) {
      const [key, val] = cell.dataset.usSet.split(':');
      root.setAttribute('data-us-' + key, val);
      setUsView(root);
      return;
    }
    if (hit('[data-us-reset]')) {
      face.forEach(([name, val]) => root.setAttribute(name, val));
      Object.assign(usPick, { find: '', group: '', sort: 'points', asc: false, shown: US_STEP });
      setUsView(root);
      return;
    }
    if (!hit('[data-us-root]')) return;
    const look = hit('[data-us-look]');
    if (look) {
      root.dataset.usView = look.dataset.usLook;
      setUsList(root);
      return;
    }
    const go = hit('[data-us-go]');
    const group = hit('[data-us-grp]');
    const sort = hit('[data-us-by]');
    const open = hit('[data-us-open]');
    const pill = (go && usOn.page === 'new' && usOn.nav === 'wins') ? root.querySelector(`[data-sl-toggle-control="us-win-${go.dataset.usGo}"]`) : null;
    if (pill) {
      /* With windows in the head a way to a room opens its window and brings the head into sight */
      e.preventDefault();
      if (!pill.classList.contains('sl-is-open')) pill.click();
      pill.scrollIntoView({ block: 'center' });
      return;
    }
    if (go) root.dataset.usRoom = go.dataset.usGo;
    else if (open) {
      usPick.group = open.dataset.usOpen;
      root.dataset.usRoom = 'top';
    }
    else if (group) usPick.group = group.dataset.usGrp;
    else if (sort) usPick.sort = sort.dataset.usBy;
    else if (hit('[data-us-turn]')) usPick.asc = !usPick.asc;
    else if (hit('[data-us-drop]')) Object.assign(usPick, { find: '', group: '', sort: 'points', asc: false });
    else if (hit('[data-us-next]')) usPick.shown += US_STEP;
    else {
      if (hit('a[href="#"]')) e.preventDefault();
      return;
    }
    e.preventDefault();
    setUsView(root);
  });
}

initUs();
