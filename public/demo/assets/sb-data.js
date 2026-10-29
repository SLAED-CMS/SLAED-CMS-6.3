/* The series "site builder": one editor for everything a site owner changes without HTML - the sections of a page,
   the menus, the parts of the theme around the content - and the same editor laid over the page itself, where a
   click on any part opens its settings and a text is typed in place. This file is what the editor (sb.js) and the
   canvas (sb-site.js) share: the catalogue of the theme's sections with the schema of their settings, the parts of
   the theme a site sets, the menus and the layout a fresh site starts from.

   The layout is one tree, written as one file of the site - config/layout.php, read by one include that OPcache
   keeps in memory - so a page costs no query: theme parts, menus and pages side by side, every section a type of
   the theme with its settings. Nothing here is a database row */

/* The addresses a link may take: the routes the shipped menu already uses, in the words of the menu */
const SB_LINKS = [
  ['/', 'Главная'],
  ['news', 'Новости'],
  ['files', 'Каталог файлов'],
  ['forum', 'Форум'],
  ['docs', 'Центр документации'],
  ['faq', 'Вопросы и ответы'],
  ['help', 'Техническая поддержка'],
  ['contact', 'Обратная связь'],
  ['presentation', 'Презентационная страница'],
  ['links', 'Каталог сайтов системы'],
  ['users', 'Пользователи'],
  ['voting', 'Опросы'],
  ['recommend', 'Рекомендовать сайт'],
  ['sitemap', 'Карта сайта'],
  ['about', 'О проекте'],
  ['account/newuser', 'Регистрация'],
];

/* The icons a picker offers: Bootstrap Icons, the icon contract every theme ships */
const SB_ICONS = [
  'shield-check', 'lightning-charge', 'puzzle', 'palette', 'speedometer2', 'people', 'chat-dots', 'newspaper',
  'file-earmark-arrow-down', 'question-circle', 'book', 'easel', 'diagram-3', 'house-door', 'globe', 'envelope',
  'telephone', 'geo-alt', 'star', 'heart', 'gem', 'rocket-takeoff', 'trophy', 'award', 'gear', 'code-slash',
  'phone', 'cloud-check', 'lock', 'graph-up-arrow', 'box-seam', 'brush', 'camera', 'cart', 'calendar-event', 'cpu',
  'github', 'youtube', 'twitter-x', 'chat-square-text', 'telegram', 'facebook', 'instagram', 'stars',
];

/* The pictures a section may stand on: the brand photographs of the presentation (2560 x 1440) and the logos of the
   theme. A photograph is laid with cover and never upscaled: no box of a section is larger than the photo */
/* A picture is a file of the site, its value the path from the root as a file field of the panel keeps it; these are the
   files the stand's file manager shows: path, address, name */
const getSbFile = (path) => [path, new URL('../' + path, location.href).href, path.split('/').pop()];
const SB_PHOTOS = [1, 2, 3, 4, 5, 6, 7].map((n) => 'uploads/presentation/brand/background-' + n + '.png')
  .concat(['simplicity', 'functionality', 'efficiency', 'security'].map((k) => 'uploads/presentation/dna/' + k + '.webp')).map(getSbFile);
/* The pictures of the marks in the footer: the files of uploads/presentation/brand/tech/ the theme lite shows, with the caption each carries */
const SB_MARKS = [['php.png', 'PHP'], ['mysql.png', 'MySQL'], ['html5.png', 'HTML 5'], ['css3.png', 'CSS 3'], ['jquery.png', 'jQuery'], ['jqueryui.png', 'jQuery UI']]
  .map(([file, title]) => getSbFile('uploads/presentation/brand/tech/' + file).concat(title));
const SB_LOGOS = [
  ['slaed-logo-wordmark-gradient-blue.svg', 'Знак и имя, градиент'],
  ['slaed-logo-wordmark-solid-blue.svg', 'Знак и имя, синий'],
  ['slaed-logo-wordmark-solid-black.svg', 'Знак и имя, чёрный'],
  ['slaed-logo-wordmark-outline-blue.svg', 'Контур, синий'],
  ['slaed-logo-mark-gradient-blue.svg', 'Только знак, градиент'],
  ['slaed-logo-mark-duotone-blue.svg', 'Только знак, два тона'],
];
const SB_TONES = [['text', 'Как текст'], ['primary', 'Бренд'], ['success', 'Зелёный'], ['warning', 'Янтарный'], ['accent', 'Акцент'], ['danger', 'Красный']];
const SB_SEASONS = [['date', 'По дате'], ['sl-winter', 'Зима'], ['sl-spring', 'Весна'], ['sl-summer', 'Лето'], ['sl-autumn', 'Осень'], ['sl-newyear', 'Новый год']];
/* The files of blocks/ a file block runs: key, label, icon, file */
const SB_BLOCKS = [
  ['voting', 'Опрос', 'bar-chart', 'voting.php'], ['login', 'Вход на сайт', 'box-arrow-in-right', 'login.php'],
  ['stat', 'Статистика', 'graph-up', 'stat.php'], ['search', 'Поиск', 'search', 'search.php'],
  ['languages', 'Языки', 'translate', 'languages.php'], ['user_info', 'Кто на сайте', 'people', 'user_info.php'],
  ['forum', 'Форум', 'chat-dots', 'forum.php'], ['node', 'Материалы', 'newspaper', 'node.php'],
  ['img', 'Примеры внедрений', 'images', 'img.php'], ['banner_random', 'Случайный баннер', 'badge-ad', 'banner_random.php'],
];
// What a block is, by its type and the file it runs: key, label, icon, file - an HTML and an RSS block have no file
const getSbBlockKind = (b) => (b.type === 'html' ? ['', 'HTML', 'code-square'] : b.type === 'rss' ? ['', 'RSS', 'rss'] : (SB_BLOCKS.find((x) => x[0] === b.kind) || SB_BLOCKS[0]));

/* The types of Node: what the feed reads through the engine of blocks/node.php, by the name their pages carry; the icon
   and the tone are those of their card in "Пульс проекта" */
const SB_NODE_TYPES = [['news', 'Новости', 'newspaper', 'primary'], ['forum', 'Форум', 'chat-dots', 'accent'], ['docs', 'Документация', 'book', 'success'],
  ['files', 'Каталог файлов', 'file-earmark-arrow-down', 'warning'], ['content', 'Содержание', 'file-earmark-text', 'primary'],
  ['faq', 'Вопросы и ответы', 'question-circle', 'success'], ['links', 'Каталог сайтов', 'link-45deg', 'accent'], ['help', 'Помощь', 'life-preserver', 'warning']];

/* The records of Node the section "Материал" offers: number, type, address and title, real records of the stand. In the
   system the choice is a search over the titles through the reader of Node, and the section keeps the number alone */
const SB_NODE_ITEMS = [
  [5085, 'content', 'content/partnerskaya-programma', 'Партнерская программа'],
  [5086, 'content', 'content/reklamnye-materialy', 'Рекламные материалы'],
  [5079, 'content', 'content/polnaya-podderzhka-html5-v-slaed-cms', 'Полная поддержка HTML5 в SLAED CMS'],
  [3939, 'docs', 'docs/bezopasnost', 'Безопасность'],
  [3923, 'docs', 'docs/avtory', 'Авторы'],
  [3913, 'news', 'news/mozilla-firefox-prekrashchaet-podderzhku-plaginov', 'Mozilla Firefox прекращает поддержку плагинов'],
  [3915, 'news', 'news/besplatnaya-sistema-upravleniya-soderzhimym-slaed-cms-6-2-pro', 'Бесплатная система управления содержимым SLAED CMS 6.2 Pro'],
];
const getSbItem = (id) => SB_NODE_ITEMS.find((r) => String(r[0]) === String(id));

/* The four zones of _blocks.bpos the layouts of the theme print; b and f print nothing today and stay out */
const SB_ZONES = [['l', 'Левая колонка', 'layout-sidebar'], ['r', 'Правая колонка', 'layout-sidebar-reverse'],
  ['c', 'Над содержимым', 'arrow-bar-up'], ['d', 'Под содержимым', 'arrow-bar-down']];

/* The columns of a page, the two settings a module carries in config/modules.php: side for the side zones, top for the centre */
const SB_SIDE = [['0', 'Левая и правая'], ['1', 'Только левая'], ['2', 'Только правая'], ['3', 'Без боковых']];
const SB_TOP = [['0', 'Сверху и снизу'], ['1', 'Только сверху'], ['2', 'Только снизу'], ['3', 'Без центральных']];

/* Where a block shows: the marks of _blocks.which after the pages, in the words of the panel */
const SB_WHERE = [['ihome', 'Главная'], ['home', 'Только на главной'], ['all', 'Во всех модулях'], ['otricanie', 'Отрицание отмеченных'],
  ['infly', 'Свободный блок'], ['flyfix', 'Свободный фиксированный']];
const SB_LANGS = [['', 'Все'], ['ru', 'Русский'], ['en', 'English'], ['de', 'Deutsch']];
const SB_WHO = [['0', 'Всем'], ['1', 'Пользователям'], ['2', 'Модераторам'], ['3', 'Гостям']];

/* The name a page answers to in which: a page holding the slot of a module is that module, any other page of the
   builder is page-<key> - the hyphen passes filterVar() of the panel, and no module or type name carries one; the home
   page is the home, as $home is for getBlocks() */
function getSbPageName(key, page) {
  const slot = (page.sections || []).find((s) => s.type === 'module');
  return slot ? slot.module : 'page-' + key;
}

/* Whether a block shows on a page: getBlocks() of core/system.php read as it is, the fall-through of ihome included */
function getSbWhereShow(which, key, page) {
  const w = (which && which.length) ? which : [''];
  const home = key === 'home';
  const name = getSbPageName(key, page);
  let show = false;
  switch (w[0]) {
    case 'all':
    case '':
      show = true;
      break;
    case 'infly':
      show = false;
      break;
    case 'home':
      show = home;
      break;
    case 'ihome':
      if (home) show = true;
      // falls through, as in getBlocks()
    default:
      if (!home && w.includes(name)) show = true;
  }
  if (w.includes('otricanie')) show = !show;
  return show;
}

/* The checked boxes as editsave() of admin/modules/blocks.php writes them: all and home push the list out, the denial and the
   fixed mark follow them, infly leads (and stays alone unless fixed), ihome leads any list but home */
function getSbWhich(list) {
  let which = '';
  if (list.includes('all')) which = 'all';
  if (list.includes('home')) which = 'home';
  if (which === '') {
    which = list.join(',');
  } else {
    if (list.includes('otricanie')) which += ',otricanie';
    if (list.includes('flyfix')) which += ',flyfix';
  }
  if (list.includes('infly')) which = list.includes('flyfix') ? 'infly,' + which.replace('infly,', '') : 'infly,';
  if (list.includes('ihome') && which !== 'home') which = 'ihome,' + which.replace(',ihome', '');
  return [...new Set(which.split(',').filter(Boolean))];
}

/* Markdown for the stand: headings, paragraphs, lists with their nesting, emphasis, code, links and pictures, tables,
   rules, quotes and the GFM alerts ([!NOTE], [!TIP], [!IMPORTANT], [!WARNING], [!CAUTION]) in the markup the parser
   gives them (fragments/blockquote.html: sl-alert of the tone), escaped first. In the system a Markdown text is
   rendered by Parser::filterDoc() as every text of the site; this stub only lets the stand show one - on the canvas,
   in the preview of the editor window and in the help */
function getSbMd(src) {
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const href = (v) => /^(https?:|mailto:|#|\/)/.test(v) ? v : '/' + v;
  const inline = (v) => esc(v)
    .replace(/`([^`]+)`/g, '<code>$1</code>')
    .replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, (m, alt, url) => `<img src="${href(url)}" alt="${alt}" loading="lazy">`)
    .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, (m, words, url) => `<a href="${href(url)}">${words}</a>`)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\*([^*]+)\*/g, '<em>$1</em>');
  const TONE = { note: 'info', tip: 'success', important: 'accent', warning: 'warn', caution: 'error' };
  const lines = String(src || '').replace(/\r/g, '').split('\n');
  const out = [];
  const lists = [];
  let para = [];
  const flush = () => {
    if (para.length) out.push('<p>' + para.map(inline).join(' ') + '</p>');
    para = [];
  };
  const closeLists = () => {
    while (lists.length) out.push('</li></' + lists.pop().kind + '>');
  };
  const isRow = (line) => /^\|.*\|\s*$/.test(line || '');
  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];
    // A quote, or an alert when its first line names the kind
    if (/^>/.test(line)) {
      flush();
      closeLists();
      const body = [];
      for (; i < lines.length && /^>/.test(lines[i]); i++) body.push(lines[i].replace(/^>\s?/, ''));
      i--;
      const kind = (body[0] || '').match(/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*$/i);
      if (kind) body.shift();
      const html = getSbMd(body.join('\n'));
      out.push(kind ? `<div class="sl-alert sl-alert-${TONE[kind[1].toLowerCase()]}"><div class="sl-alert-body">${html}</div></div>` : `<blockquote>${html}</blockquote>`);
      continue;
    }
    // A table: the row of its head, the rule under it, its rows
    if (isRow(line) && /^\|[\s:|-]+\|\s*$/.test(lines[i + 1] || '')) {
      flush();
      closeLists();
      const cells = (row) => row.trim().replace(/^\||\|$/g, '').split('|').map((c) => inline(c.trim()));
      const head = cells(line);
      const rows = [];
      for (i += 2; i < lines.length && isRow(lines[i]); i++) rows.push(cells(lines[i]));
      i--;
      out.push('<table><thead><tr>' + head.map((c) => `<th>${c}</th>`).join('') + '</tr></thead><tbody>'
        + rows.map((r) => '<tr>' + r.map((c) => `<td>${c}</td>`).join('') + '</tr>').join('') + '</tbody></table>');
      continue;
    }
    if (/^ {0,3}(-{3,}|\*{3,})\s*$/.test(line)) {
      flush();
      closeLists();
      out.push('<hr>');
      continue;
    }
    const head = line.match(/^(#{1,6})\s+(.+)/);
    if (head) {
      flush();
      closeLists();
      out.push(`<h${head[1].length}>${inline(head[2])}</h${head[1].length}>`);
      continue;
    }
    // An item opens its list, or a list inside the item above when it stands deeper
    const item = line.match(/^(\s*)(?:[-*]|(\d+)\.)\s+(.+)/);
    if (item) {
      flush();
      const indent = item[1].length;
      const kind = item[2] ? 'ol' : 'ul';
      const top = lists[lists.length - 1];
      if (!top || indent > top.indent) {
        out.push('<' + kind + '>');
        lists.push({ kind: kind, indent: indent });
      } else {
        while (lists.length > 1 && indent < lists[lists.length - 1].indent) out.push('</li></' + lists.pop().kind + '>');
        out.push('</li>');
      }
      out.push('<li>' + inline(item[3]));
      continue;
    }
    if (!line.trim()) {
      flush();
      continue;
    }
    // A line under an item continues it; any other line is text, and text ends the lists
    if (lists.length && /^\s+/.test(line)) {
      out.push(' ' + inline(line.trim()));
      continue;
    }
    closeLists();
    para.push(line);
  }
  flush();
  closeLists();
  return out.join('');
}


/* The shape of the layout the stand writes: a draft, a publication or a file of another shape is not read but replaced
   by the delivery, as the system never reads a layout it did not write */
const SB_VERSION = 4;
const isSbLayout = (v) => !!v && v.v === SB_VERSION && !!v.theme && !!v.menus && !!v.pages && !!v.blocks;

/* The modules of the stand: what the slot "Содержимое модуля" may hold and what "Пункты из модулей" offers the menu */
const SB_MODULES = [
  ['presentation', 'Презентация', 'easel'], ['news', 'Новости', 'newspaper'], ['files', 'Файлы', 'file-earmark-arrow-down'], ['forum', 'Форум', 'chat-dots'],
  ['faq', 'Вопросы и ответы', 'question-circle'], ['docs', 'Документация', 'book'], ['voting', 'Опросы', 'bar-chart'],
  ['links', 'Каталог сайтов', 'link-45deg'], ['users', 'Пользователи', 'people'], ['contact', 'Обратная связь', 'envelope'],
];

/* Who sees a section and when: the same words and values as a block of the site (_blocks.view, expire), so one
   meaning holds for the whole site; then the anchor of the section and the devices it shows on */
const SB_SHOW = [
  { key: 'who', label: 'Кому', kind: 'select', opts: SB_WHO },
  { key: 'lang', label: 'Язык', kind: 'select', opts: SB_LANGS },
  { key: 'from', label: 'Показать с', kind: 'date' },
  { key: 'till', label: 'Показать до', kind: 'date' },
  { key: 'anchor', label: 'Якорь', kind: 'text' },
  { key: 'phone', label: 'На телефоне', kind: 'switch' },
  { key: 'desk', label: 'На компьютере', kind: 'switch' },
];
const getSbShow = () => ({ who: '0', lang: '', from: '', till: '', anchor: '', phone: true, desk: true });

/* A field: key, label, kind, and what the kind needs. Kinds: text, area, markdown (edited in the editor window,
   rendered by the parser), select (opts), seg (opts), switch, range (min, max), icon, file (pics), focus (the point of a
   picture kept in its crop), logo, link, tone, code, multi (opts), blockref, noderef (a record of Node by its number).
   tab: 'look' puts it on the second tab */

/* The head every framed section carries, as the presentation draws it (fragments/presentation-head.html): its number
   on the page, an icon, the title and a line beside it */
const SB_HEAD = [
  { key: 'title', label: 'Заголовок', kind: 'text' },
  { key: 'lead', label: 'Подпись справа', kind: 'text' },
  { key: 'icon', label: 'Иконка', kind: 'icon', tab: 'look' },
];

/* The groups of the library in their order: each holds a full row of three cards, the first two rows */
const SB_GROUPS = ['Основа', 'Медиа', 'Материалы сайта', 'Доверие', 'Связь', 'Для мастеров'];

/* The sections of the theme lite. Each is drawn on the classes the site already has (presentation.css of the theme,
   loaded on every page): the frame of a presentation section, its hero, stat tiles, DNA and technology cards, pulse
   cards and commit rows, the showcase, the voices, the claim, and the questions of the faq mode. In the system each type
   is a description found in a root - the theme or a module, whose provider gives the data (the feed is Node's) */
const SB_TYPES = {
  module: {
    title: 'Содержимое модуля', icon: 'puzzle', group: 'Материалы сайта', one: true,
    note: 'Ответ модуля страницы, как его выводит сам модуль: презентация, лента, каталог или форум',
    fields: [
      { key: 'module', label: 'Модуль', kind: 'select', opts: SB_MODULES.map(([k, t]) => [k, t]) },
    ],
    make: () => ({ module: 'news' }),
  },
  hero: {
    title: 'Обложка', icon: 'image', group: 'Основа',
    note: 'Надстрочник, крупный заголовок с выделенным словом, текст, две кнопки и картинка справа',
    fields: [
      { key: 'over', label: 'Надстрочник', kind: 'text' },
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'accent', label: 'Выделено цветом', kind: 'text' },
      { key: 'title2', label: 'Вторая строка', kind: 'text' },
      { key: 'text', label: 'Текст', kind: 'area' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
      { key: 'btn2', label: 'Вторая кнопка', kind: 'text' },
      { key: 'href2', label: 'Вторая ведёт на', kind: 'link' },
      { key: 'pic', label: 'Картинка справа', kind: 'switch', tab: 'look' },
      { key: 'photo', label: 'Картинка', kind: 'file', pics: 'photos', tab: 'look' },
      { key: 'alt', label: 'Описание картинки', kind: 'text', tab: 'look' },
      { key: 'focus', label: 'Точка фокуса', kind: 'focus', tab: 'look' },
    ],
    make: () => ({
      over: 'Система управления содержимым · открытый код', title: 'Всё великое', accent: 'просто.', title2: 'Но не примитивно.',
      text: 'SLAED — модульная CMS с открытым исходным кодом: управление контентом, пользователями, файлами, поиском и расширениями в одной системе.',
      btn: 'Скачать систему', href: 'files', btn2: 'Посмотреть возможности', href2: 'presentation', pic: true, photo: 'uploads/presentation/brand/background-1.png', alt: 'Знак SLAED',
    }),
  },
  features: {
    title: 'Преимущества', icon: 'grid-3x2-gap', group: 'Основа',
    note: 'Карточки с полосой-картинкой, иконкой и текстом — или строки, как технологии презентации',
    fields: SB_HEAD.concat([
      { key: 'look', label: 'Карточки', kind: 'seg', opts: [['tile', 'С картинкой'], ['line', 'Строки']], tab: 'look' },
      { key: 'cols', label: 'Колонки', kind: 'seg', opts: [['2', '2'], ['3', '3'], ['4', '4']], tab: 'look' },
      { key: 'tone', label: 'Тон иконок', kind: 'tone', tab: 'look' },
    ]),
    items: {
      label: 'Карточки', one: 'карточку', max: 8, name: 'title',
      fields: [
        { key: 'icon', label: 'Иконка', kind: 'icon' },
        { key: 'title', label: 'Заголовок', kind: 'text' },
        { key: 'text', label: 'Текст', kind: 'area' },
        { key: 'photo', label: 'Полоса', kind: 'file', pics: 'photos' },
        { key: 'focus', label: 'Точка фокуса', kind: 'focus' },
      ],
      make: () => ({ icon: 'star', title: 'Новая карточка', text: 'Пара слов о том, чем это хорошо.', photo: 'uploads/presentation/dna/simplicity.webp' }),
    },
    make: () => ({
      title: 'DNA продукта', lead: 'Четыре основополагающих принципа SLAED CMS.', icon: 'fingerprint', look: 'tile', cols: '2', tone: 'primary',
      items: [
        { icon: 'magic', title: 'Простота', text: 'Интуитивно понятный интерфейс позволяет управлять сайтом так же просто, как работать с документами или почтой.', photo: 'uploads/presentation/dna/simplicity.webp' },
        { icon: 'gear', title: 'Функциональность', text: 'Модули, блоки, расширения и темы оформления дополняют систему практически любым функционалом.', photo: 'uploads/presentation/dna/functionality.webp' },
        { icon: 'speedometer2', title: 'Эффективность', text: 'Низкая потребляемость серверных ресурсов даёт высокую скорость работы системы.', photo: 'uploads/presentation/dna/efficiency.webp' },
        { icon: 'shield-check', title: 'Безопасность', text: 'Гибкая система защиты ведёт журнал действий посетителей и отражает вредоносные запросы.', photo: 'uploads/presentation/dna/security.webp' },
      ],
    }),
  },
  feed: {
    title: 'Свежие материалы', icon: 'rss', group: 'Материалы сайта',
    note: 'Последние записи одного или нескольких типов — карточками «Пульса» или строками',
    fields: SB_HEAD.concat([
      { key: 'source', label: 'Типы', kind: 'multi', opts: SB_NODE_TYPES },
      { key: 'mode', label: 'Какие', kind: 'seg', opts: [['last', 'Новые'], ['home', 'Для главной']] },
      { key: 'count', label: 'Сколько', kind: 'range', min: 2, max: 8 },
      { key: 'view', label: 'Вид', kind: 'seg', opts: [['tile', 'Карточки', 'grid'], ['list', 'Строки', 'list-ul']], tab: 'look' },
      { key: 'date', label: 'Дата', kind: 'switch', tab: 'look' },
      { key: 'more', label: 'Ссылка «Все»', kind: 'switch', tab: 'look' },
    ]),
    make: () => ({ title: 'Пульс проекта', lead: 'Разработка, контент, сообщество и ресурсы.', icon: 'rss', source: ['news', 'forum', 'docs', 'files'], mode: 'last', count: 4, view: 'tile', date: true, more: true }),
  },
  // A long text lives in Node and is written in its module: the search, the comments, the translations and the revisions
  // stay with the record; the section only shows it on the page
  item: {
    title: 'Материал', icon: 'file-earmark-richtext', group: 'Материалы сайта',
    note: 'Одна запись Node по номеру — статья, страница или документ; текст правится в её модуле',
    fields: SB_HEAD.concat([
      { key: 'item', label: 'Материал', kind: 'noderef' },
      { key: 'part', label: 'Показать', kind: 'seg', opts: [['all', 'Весь текст'], ['intro', 'Вступление']] },
    ]),
    // An empty title gives the head the title of the record
    make: () => ({ title: '', lead: '', icon: 'file-earmark-richtext', item: '', part: 'all' }),
  },
  media: {
    title: 'Текст и картинка', icon: 'layout-text-sidebar', group: 'Основа',
    note: 'Картинка рядом с текстом и кнопкой — слева или справа',
    fields: SB_HEAD.concat([
      { key: 'text', label: 'Текст', kind: 'markdown' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
      { key: 'photo', label: 'Картинка', kind: 'file', pics: 'photos', tab: 'look' },
      { key: 'alt', label: 'Описание картинки', kind: 'text', tab: 'look' },
      { key: 'focus', label: 'Точка фокуса', kind: 'focus', tab: 'look' },
      { key: 'side', label: 'Положение картинки', kind: 'seg', opts: [['left', 'Слева'], ['right', 'Справа']], tab: 'look' },
    ]),
    make: () => ({
      title: 'Один модуль вместо шести', lead: 'Новости, статьи, страницы и каталоги — один движок.', icon: 'diagram-3',
      text: 'Типы Node собирают новости, статьи, страницы и каталоги из одного движка.\n\nПоля, виды и права настраиваются в панели — без строчки кода.',
      btn: 'Читать документацию', href: 'docs', photo: 'uploads/presentation/brand/background-4.png', alt: 'Знак SLAED на ноутбуке, планшете и автомобиле', side: 'left',
    }),
  },
  text: {
    title: 'Текст', icon: 'file-text', group: 'Основа',
    note: 'Свободный текст в Markdown: подзаголовки, списки, ссылки, картинки и таблицы',
    fields: SB_HEAD.concat([
      { key: 'body', label: 'Текст', kind: 'markdown' },
    ]),
    make: () => ({ title: 'О проекте', lead: '', icon: 'file-text', body: '' }),
  },
  stats: {
    title: 'Цифры', icon: '123', group: 'Доверие',
    note: 'Плитки с крупным числом и подписью капителью, как под заголовком презентации',
    fields: SB_HEAD.concat([
      { key: 'tone', label: 'Тон чисел', kind: 'tone', tab: 'look' },
    ]),
    items: {
      label: 'Числа', one: 'число', max: 6, name: 'num',
      fields: [
        { key: 'num', label: 'Число', kind: 'text' },
        { key: 'unit', label: 'Единица', kind: 'text' },
        { key: 'label', label: 'Подпись', kind: 'text' },
      ],
      make: () => ({ num: '100', unit: '', label: 'Подпись' }),
    },
    make: () => ({
      title: 'SLAED в цифрах', lead: 'Двадцать лет одной системы.', icon: 'bar-chart', tone: 'text',
      items: [
        { num: '6.3.0', unit: '', label: 'Phoenix · актуальная версия' }, { num: '701', unit: '', label: 'дополнений в архиве' },
        { num: '21', unit: 'год', label: 'развития · с 2005' }, { num: 'MIT', unit: '', label: 'открытый код' },
        { num: '0.038', unit: 'сек', label: 'генерация страницы' }, { num: '31', unit: '', label: 'запросов к базе данных' },
      ],
    }),
  },
  sites: {
    title: 'Внедрения', icon: 'images', group: 'Медиа',
    note: 'Витрина сайтов из каталога: снимок, оценка, раздел и имя',
    fields: SB_HEAD.concat([
      { key: 'over', label: 'Надпись над витриной', kind: 'text' },
      { key: 'count', label: 'Сколько', kind: 'range', min: 3, max: 12 },
    ]),
    make: () => ({ title: 'Сайты на SLAED', lead: 'Один движок — разные сайты и визуальные языки.', icon: 'globe', over: 'Реальные внедрения', count: 8 }),
  },
  voices: {
    title: 'Отзывы', icon: 'chat-quote', group: 'Доверие',
    note: 'Короткие истории владельцев: слова, имя, сайт и год начала',
    fields: SB_HEAD.concat([
      { key: 'over', label: 'Надпись над отзывами', kind: 'text' },
    ]),
    items: {
      label: 'Отзывы', one: 'отзыв', max: 6, name: 'name',
      fields: [
        { key: 'text', label: 'Слова', kind: 'area' },
        { key: 'name', label: 'Имя', kind: 'text' },
        { key: 'site', label: 'Сайт', kind: 'text' },
        { key: 'role', label: 'Чем занят', kind: 'text' },
        { key: 'since', label: 'С какого года', kind: 'text' },
      ],
      make: () => ({ text: 'Пара фраз о том, как сайт живёт на SLAED.', name: 'Имя Фамилия', site: 'site.ru', role: 'сообщество', since: '2026' }),
    },
    make: () => ({
      title: 'Голоса владельцев', lead: 'Не рекламные слоганы — короткие истории эксплуатации.', icon: 'chat-quote', over: 'Сигналы владельцев / долгий опыт',
      items: [
        { text: 'Перенесли форум за один вечер: восемь лет тем, вложения, права доступа. Утром никто из пользователей не заметил переезда.', name: 'Андрей Кузьмин', site: 'avto-club.ru', role: 'сообщество', since: '2011' },
        { text: 'Держим сорок разделов на обычном хостинге за 200 рублей. Кеш включён, страница собирается быстрее, чем приходит ответ от платёжного шлюза.', name: 'Марина Соловьёва', site: 'gorod-news.ru', role: 'медиа', since: '2016' },
        { text: 'Свой модуль записи к мастеру написали за выходные, ядро трогать не пришлось. Обновления с тех пор ставим без правок.', name: 'Игорь Демченко', site: 'studio-remont.ru', role: 'сервис', since: '2019' },
      ],
    }),
  },
  faq: {
    title: 'Вопросы и ответы', icon: 'patch-question', group: 'Связь',
    note: 'Раскрывающиеся вопросы, как в модуле «Вопросы и ответы»',
    fields: SB_HEAD.concat([
      { key: 'open', label: 'Первый вопрос открыт', kind: 'switch', tab: 'look' },
    ]),
    items: {
      label: 'Вопросы', one: 'вопрос', max: 12, name: 'q',
      fields: [
        { key: 'q', label: 'Вопрос', kind: 'text' },
        { key: 'a', label: 'Ответ', kind: 'markdown' },
      ],
      make: () => ({ q: 'Новый вопрос?', a: 'Ответ на него.' }),
    },
    make: () => ({
      title: 'Частые вопросы', lead: 'Коротко о главном.', icon: 'patch-question', open: true,
      items: [
        { q: 'Нужна ли база данных для этой страницы?', a: 'Нет: секции, меню и вид сайта лежат в одном файле настроек, база отдаёт только материалы.' },
        { q: 'Можно ли вернуть прежний вид?', a: 'Да: у черновика есть отмена шагов, опубликованное остаётся прежним до нажатия «Опубликовать».' },
        { q: 'Что будет при смене темы?', a: 'Каждая тема объявляет свои секции; содержимое остаётся, вид даёт новая тема.' },
      ],
    }),
  },
  gallery: {
    title: 'Галерея', icon: 'images', group: 'Медиа',
    note: 'Картинки с подписями — лентой, как витрина внедрений',
    fields: SB_HEAD.concat([
      { key: 'over', label: 'Надпись над галереей', kind: 'text' },
    ]),
    items: {
      label: 'Картинки', one: 'картинку', max: 24, name: 'caption',
      fields: [
        { key: 'photo', label: 'Картинка', kind: 'file', pics: 'photos' },
        { key: 'alt', label: 'Описание картинки', kind: 'text' },
        { key: 'caption', label: 'Подпись', kind: 'text' },
        { key: 'focus', label: 'Точка фокуса', kind: 'focus' },
      ],
      make: () => ({ photo: 'uploads/presentation/brand/background-1.png', alt: '', caption: 'Подпись', focus: 'center' }),
    },
    make: () => ({ title: 'Галерея', lead: '', icon: 'images', over: '', items: [1, 2, 3, 4].map((n) => ({
      photo: 'uploads/presentation/brand/background-' + n + '.png', alt: '', caption: 'Подпись ' + n, focus: 'center' })) }),
  },
  video: {
    title: 'Видео', icon: 'play-btn', group: 'Медиа',
    note: 'Ролик с YouTube или файл видео из загрузок, во всю ширину секции',
    fields: SB_HEAD.concat([
      { key: 'url', label: 'Ссылка или файл', kind: 'text' },
      { key: 'caption', label: 'Подпись', kind: 'text' },
    ]),
    make: () => ({ title: 'Видео', lead: '', icon: 'play-btn', url: '', caption: '' }),
  },
  steps: {
    title: 'Шаги', icon: 'list-ol', group: 'Основа',
    note: 'Пронумерованные шаги: как заказать, подключиться или начать',
    fields: SB_HEAD,
    items: {
      label: 'Шаги', one: 'шаг', max: 12, name: 'title',
      fields: [
        { key: 'title', label: 'Шаг', kind: 'text' },
        { key: 'text', label: 'Пояснение', kind: 'text' },
      ],
      make: () => ({ title: 'Новый шаг', text: 'Что здесь происходит.' }),
    },
    make: () => ({ title: 'Как это работает', lead: '', icon: 'list-ol', items: [
      { title: 'Заявка', text: 'Вы оставляете заявку на сайте.' }, { title: 'Обсуждение', text: 'Мы уточняем задачу и сроки.' },
      { title: 'Работа', text: 'Делаем и показываем результат.' }] }),
  },
  team: {
    title: 'Команда', icon: 'people', group: 'Доверие',
    note: 'Люди проекта: фото, имя, роль и пара слов, карточками принципов',
    fields: SB_HEAD.concat([
      { key: 'cols', label: 'Колонки', kind: 'seg', opts: [['2', '2'], ['3', '3'], ['4', '4']], tab: 'look' },
    ]),
    items: {
      label: 'Люди', one: 'человека', max: 12, name: 'name',
      fields: [
        { key: 'photo', label: 'Фото', kind: 'file', pics: 'photos' },
        { key: 'focus', label: 'Точка фокуса', kind: 'focus' },
        { key: 'name', label: 'Имя', kind: 'text' },
        { key: 'role', label: 'Роль', kind: 'text' },
        { key: 'text', label: 'О себе', kind: 'area' },
      ],
      make: () => ({ photo: 'uploads/presentation/brand/background-1.png', focus: 'center', name: 'Имя Фамилия', role: 'Роль', text: 'Пара слов о человеке.' }),
    },
    make: () => ({ title: 'Команда', lead: '', icon: 'people', cols: '3', items: [1, 2, 3].map(() => ({
      photo: 'uploads/presentation/brand/background-1.png', focus: 'center', name: 'Имя Фамилия', role: 'Роль', text: 'Пара слов о человеке.' })) }),
  },
  prices: {
    title: 'Тарифы', icon: 'tags', group: 'Связь',
    note: 'Карточки тарифов: название, цена, что входит и кнопка',
    fields: SB_HEAD,
    items: {
      label: 'Тарифы', one: 'тариф', max: 6, name: 'name',
      fields: [
        { key: 'name', label: 'Название', kind: 'text' },
        { key: 'price', label: 'Цена', kind: 'text' },
        { key: 'unit', label: 'За что', kind: 'text' },
        { key: 'text', label: 'Что входит', kind: 'area' },
        { key: 'btn', label: 'Кнопка', kind: 'text' },
        { key: 'href', label: 'Ведёт на', kind: 'link' },
        { key: 'hot', label: 'Выделить', kind: 'switch' },
      ],
      make: () => ({ name: 'Тариф', price: '0 ₽', unit: 'в месяц', text: 'Первое\nВторое', btn: 'Выбрать', href: 'contact', hot: false }),
    },
    make: () => ({ title: 'Тарифы', lead: '', icon: 'tags', items: [
      { name: 'Старт', price: '0 ₽', unit: 'в месяц', text: 'Сайт на поддомене\nДо 10 страниц', btn: 'Начать', href: 'contact', hot: false },
      { name: 'Бизнес', price: '990 ₽', unit: 'в месяц', text: 'Свой адрес\nБез ограничений\nПоддержка', btn: 'Выбрать', href: 'contact', hot: true },
      { name: 'Под ключ', price: 'по запросу', unit: '', text: 'Дизайн и настройка\nПеренос сайта', btn: 'Связаться', href: 'contact', hot: false }] }),
  },
  map: {
    title: 'Карта и контакты', icon: 'geo-alt', group: 'Связь',
    note: 'Карта OpenStreetMap рядом с адресом, телефоном и почтой из части «Контакты»',
    fields: SB_HEAD.concat([
      { key: 'lat', label: 'Широта', kind: 'text', tab: 'look' },
      { key: 'lon', label: 'Долгота', kind: 'text', tab: 'look' },
      { key: 'side', label: 'Положение карты', kind: 'seg', opts: [['left', 'Слева'], ['right', 'Справа']], tab: 'look' },
    ]),
    make: () => ({ title: 'Как нас найти', lead: '', icon: 'geo-alt', lat: '52.3507', lon: '8.2271', side: 'left' }),
  },

  cta: {
    title: 'Призыв', icon: 'megaphone', group: 'Основа',
    note: 'Надстрочник, короткая фраза и кнопка на карточке с кольцами',
    fields: [
      { key: 'over', label: 'Надстрочник', kind: 'text' },
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'text', label: 'Текст', kind: 'area' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
    ],
    make: () => ({ over: 'Обратная связь', title: 'Остались вопросы?', text: 'Напишите нам — ответим в течение дня.', btn: 'Обратная связь', href: 'contact' }),
  },
  block: {
    title: 'Блок сайта', icon: 'bricks', group: 'Для мастеров',
    note: 'Блок из «Блоков и баннеров» по номеру — как тег [block=N]',
    fields: [
      { key: 'block', label: 'Блок', kind: 'blockref' },
    ],
    make: () => ({ block: '' }),
  },
  embed: {
    title: 'Встраивание', icon: 'window-stack', group: 'Для мастеров',
    note: 'Страница другого сервиса в рамке по адресу: форма, календарь, опрос',
    fields: SB_HEAD.concat([
      { key: 'url', label: 'Адрес', kind: 'text' },
      { key: 'height', label: 'Высота', kind: 'range', min: 200, max: 900 },
    ]),
    make: () => ({ title: 'Встраивание', lead: '', icon: 'window-stack', url: '', height: 420 }),
  },
  html: {
    title: 'Свой HTML', icon: 'code-slash', group: 'Для мастеров',
    note: 'Разметка как есть — только для администраторов',
    fields: [
      { key: 'code', label: 'Разметка', kind: 'code' },
    ],
    make: () => ({ code: '<div class="sl-pres-note-row"><i class="bi bi-code-slash"></i>Здесь может быть любая разметка.</div>' }),
  },
};

/* The defaults of a new section are neutral placeholders, as the theme ships them in sections/<type>.json; the texts of
   slaed.net written above become the stand's layout (stand, read by getSbStart) and are never a default */
const SB_NEUTRAL = {
  hero: () => ({ over: 'Надстрочник', title: 'Заголовок', accent: 'страницы', title2: '', text: 'Пара предложений о том, что посетитель найдёт на этой странице.',
    btn: 'Подробнее', href: '', btn2: '', href2: '', pic: true, photo: 'uploads/presentation/brand/background-1.png', alt: '', focus: 'center' }),
  features: () => ({ title: 'Преимущества', lead: '', icon: 'grid-3x2-gap', look: 'tile', cols: '3', tone: 'primary', items: [1, 2, 3].map((n) => ({
    icon: 'star', title: 'Преимущество ' + n, text: 'Коротко о том, чем это полезно.', photo: 'uploads/presentation/dna/simplicity.webp', focus: 'center' })) }),
  feed: () => ({ title: 'Новости', lead: '', icon: 'rss', source: ['news'], mode: 'last', count: 3, view: 'tile', date: true, more: true }),
  media: () => ({ title: 'О нас', lead: '', icon: 'info-circle', text: 'Расскажите, **чем вы занимаетесь** и почему вам можно доверять.\n\nВторой абзац — подробности.',
    btn: 'Подробнее', href: '', photo: 'uploads/presentation/brand/background-4.png', alt: '', focus: 'center', side: 'left' }),
  text: () => ({ title: 'Заголовок раздела', lead: '', icon: 'file-text', body: '### Подзаголовок\n\nТекст в **Markdown**: абзацы, [ссылки](contact) и списки.\n\n- первый пункт\n- второй пункт' }),
  stats: () => ({ title: 'В цифрах', lead: '', icon: 'bar-chart', tone: 'text', items: [
    { num: '10', unit: 'лет', label: 'на рынке' }, { num: '500', unit: '', label: 'клиентов' }, { num: '24/7', unit: '', label: 'поддержка' }] }),
  sites: () => ({ title: 'Каталог сайтов', lead: '', icon: 'globe', over: '', count: 4 }),
  voices: () => ({ title: 'Отзывы', lead: '', icon: 'chat-quote', over: '', items: [1, 2].map(() => ({
    text: 'Отзыв клиента в двух-трёх предложениях.', name: 'Имя Фамилия', site: 'site.ru', role: '', since: '' })) }),
  faq: () => ({ title: 'Вопросы и ответы', lead: '', icon: 'patch-question', open: true, items: [
    { q: 'Первый вопрос?', a: 'Ответ на него.' }, { q: 'Второй вопрос?', a: 'Ответ на него.' }] }),
  cta: () => ({ over: '', title: 'Остались вопросы?', text: 'Напишите нам — ответим.', btn: 'Связаться', href: 'contact' }),
};
Object.entries(SB_NEUTRAL).forEach(([k, make]) => { SB_TYPES[k].stand = SB_TYPES[k].make; SB_TYPES[k].make = make; });

/* The parts of the theme around the content: each is a place on every page, set once for the whole site. hint stands
   under the fields; items.key names the list of the part in the theme values. In the system this list is the
   description lite carries of its places; another theme declares its own, and the values stay by key */
const SB_PARTS = {
  brand: {
    title: 'Логотип и строка', icon: 'badge-tm', where: 'Шапка',
    hint: 'Название и логотип — ключи sitename и site_logo из «Конфигураций»: правка здесь пишет их же. Строка под логотипом стоит в шапке и в подвале; описание сайта для поисковиков остаётся в «Конфигурациях».',
    fields: [
      { key: 'sitename', label: 'Название сайта', kind: 'text' },
      { key: 'tagline', label: 'Строка под логотипом', kind: 'area' },
      { key: 'logo', label: 'Логотип', kind: 'logo' },
    ],
  },
  pane: {
    title: 'Плашка версии', icon: 'tag', where: 'Шапка',
    hint: 'Плашка стоит в шапке и в полосе перед подвалом.',
    fields: [
      { key: 'paneOn', label: 'Показывать', kind: 'switch' },
      { key: 'paneCap', label: 'Подпись', kind: 'text' },
      { key: 'paneNum', label: 'Версия', kind: 'text' },
      { key: 'paneBtn', label: 'Кнопка', kind: 'text' },
      { key: 'paneHref', label: 'Ведёт на', kind: 'link' },
    ],
  },
  social: {
    title: 'Ссылки верхней строки', icon: 'share', where: 'Верхняя строка',
    fields: [],
    items: {
      key: 'social', label: 'Ссылки', one: 'ссылку', max: 10, name: 'title',
      fields: [
        { key: 'icon', label: 'Иконка', kind: 'icon' },
        { key: 'title', label: 'Подпись', kind: 'text' },
        { key: 'url', label: 'Ведёт на', kind: 'link' },
      ],
      make: () => ({ icon: 'globe', title: 'Мы в сети', url: 'https://' }),
    },
  },
  demoline: {
    title: 'Полоса перед подвалом', icon: 'distribute-vertical', where: 'Подвал',
    fields: [
      { key: 'lineOn', label: 'Показывать', kind: 'switch' },
      { key: 'lineTitle', label: 'Заголовок', kind: 'text' },
    ],
  },
  contacts: {
    title: 'Контакты', icon: 'person-lines-fill', where: 'Подвал',
    fields: [
      { key: 'address', label: 'Адрес', kind: 'area' },
      { key: 'phone', label: 'Телефон', kind: 'text' },
      { key: 'email', label: 'Почта', kind: 'text' },
      { key: 'web', label: 'Сайт', kind: 'text' },
    ],
  },
  marks: {
    title: 'Знаки в подвале', icon: 'award', where: 'Подвал',
    fields: [
      { key: 'techOn', label: 'Колонка знаков', kind: 'switch' },
      { key: 'techTitle', label: 'Заголовок колонки', kind: 'text' },
      { key: 'madeOn', label: 'Знак «Made in Germany»', kind: 'switch' },
    ],
    items: {
      key: 'tech', label: 'Знаки', one: 'знак', max: 6, name: 'title',
      fields: [
        { key: 'img', label: 'Картинка', kind: 'file', pics: 'marks' },
        { key: 'title', label: 'Подпись', kind: 'text' },
      ],
      make: () => ({ img: SB_MARKS[0][0], title: SB_MARKS[0][3] }),
    },
  },
  tabs: {
    title: 'Боковые ярлыки', icon: 'bookmark', where: 'Весь сайт',
    hint: 'Ярлыки стоят у левого края окна на каждой странице; тема рисует два — зелёный и синий.',
    fields: [
      { key: 'tabsOn', label: 'Показывать', kind: 'switch' },
    ],
    items: {
      key: 'tabs', label: 'Ярлыки', one: 'ярлык', max: 2, name: 'title',
      fields: [
        { key: 'title', label: 'Подпись', kind: 'text' },
        { key: 'href', label: 'Ведёт на', kind: 'link' },
      ],
      make: () => ({ title: 'Обратная связь', href: 'contact' }),
    },
  },
  look: {
    title: 'Цвет и сезон', icon: 'circle-half', where: 'Весь сайт',
    fields: [
      { key: 'mode', label: 'Схема', kind: 'seg', opts: [['light', 'Светлая', 'sun'], ['auto', 'Системная', 'circle-half'], ['dark', 'Тёмная', 'moon']] },
      { key: 'season', label: 'Сезон', kind: 'select', opts: SB_SEASONS },
    ],
  },
};

/* Ready pages: a new page starts from one of them instead of from nothing; the sections are the theme's own */
const SB_PRESETS = [
  { key: 'empty', title: 'Пустая', icon: 'file-earmark', note: 'Без секций — собрать с нуля', list: [] },
  { key: 'landing', title: 'Лендинг', icon: 'rocket-takeoff', note: 'Обложка, преимущества, цифры, внедрения, отзывы, призыв', list: [['hero'], ['features'], ['stats'], ['sites'], ['voices'], ['cta']] },
  { key: 'company', title: 'О компании', icon: 'building', note: 'Обложка, текст с картинкой, команда, цифры, вопросы', list: [['hero', { pic: false }], ['media'], ['team'], ['stats'], ['faq']] },
  { key: 'services', title: 'Услуги', icon: 'briefcase', note: 'Обложка, услуги строками, шаги, тарифы, призыв', list: [['hero'], ['features', { look: 'line' }], ['steps'], ['prices'], ['cta']] },
  { key: 'portfolio', title: 'Портфолио', icon: 'images', note: 'Обложка, галерея работ, отзывы, призыв', list: [['hero', { pic: false }], ['gallery'], ['voices'], ['cta']] },
  { key: 'contacts', title: 'Контакты', icon: 'envelope', note: 'Обложка, карта с контактами, форма обратной связи', list: [['hero', { btn2: '', pic: false }], ['map'], ['module', { module: 'contact' }]] },
  { key: 'faqpage', title: 'Вопросы и ответы', icon: 'patch-question', note: 'Обложка, вопросы, призыв', list: [['hero', { pic: false, btn: '', btn2: '' }], ['faq'], ['cta']] },
  { key: 'news', title: 'Новости и статьи', icon: 'newspaper', note: 'Обложка, свежие материалы, материал дня', list: [['hero', { pic: false, btn: '', btn2: '' }], ['feed'], ['item']] },
  { key: 'article', title: 'Статья', icon: 'file-earmark-richtext', note: 'Обложка и материал раздела целиком', list: [['hero', { pic: false, btn: '', btn2: '' }], ['item']] },
  { key: 'jobs', title: 'Вакансии', icon: 'person-workspace', note: 'Обложка, текст, шаги отбора, призыв', list: [['hero', { pic: false }], ['text'], ['steps', { title: 'Как к нам попасть' }], ['cta']] },
  { key: 'video', title: 'Видеостраница', icon: 'play-btn', note: 'Обложка, видео, текст, призыв', list: [['hero', { pic: false }], ['video'], ['text'], ['cta']] },
  { key: 'soon', title: 'Скоро открытие', icon: 'hourglass-split', note: 'Обложка «Мы скоро откроемся» и призыв написать', list: [['hero', { over: 'Скоро', title: 'Мы скоро', accent: 'откроемся', title2: '', btn2: '', pic: false }], ['cta', { title: 'Узнать первыми', btn: 'Написать нам' }]] },
  { key: 'event', title: 'Мероприятие', icon: 'calendar-event', note: 'Обложка, программа шагами, карта, призыв записаться', list: [['hero', { pic: false }], ['steps', { title: 'Программа' }], ['map'], ['cta', { title: 'Записаться', btn: 'Записаться' }]] },
  {
    key: 'card', title: 'Визитка', icon: 'person-vcard', note: 'Сайт в одну страницу: секции с якорями для меню',
    list: [['hero', { anchor: 'nachalo' }], ['features', { anchor: 'uslugi' }], ['media', { anchor: 'o-nas' }], ['faq', { anchor: 'voprosy' }], ['cta', { anchor: 'kontakty' }]],
  },
];


const SB_MENUS = {
  main: { title: 'Главное меню', where: 'Под шапкой', depth: 2, icons: false },
  foot: { title: 'Нижнее меню', where: 'Подвал', depth: 1, icons: true },
};

/* A page: its settings beside its sections, the one shape every way of making a page gives; the audience is "0" all,
   "1" users only */
const getSbPage = (title, addr, over, sections) => Object.assign({ title: title, addr: addr, desc: '', band: true, side: '3', top: '3', og: '', noindex: false, who: '0' }, over, { sections: sections });

/* The layout of the stand: the header and the footer of slaed.net as values instead of HTML, the home page holding the
   slot of the first module of $conf['module'] as today, a page of sections and a page of a module. The delivery of the
   system ships the same shape neutral: its own menu, empty contacts, no links, no marks and no tabs */
function getSbStart() {
  let n = 0;
  const id = (p) => p + (++n);
  const sec = (type, over) => Object.assign({ id: id('s'), type: type, on: true }, (SB_TYPES[type].stand || SB_TYPES[type].make)(), getSbShow(), over || {});
  const item = (title, href, over) => Object.assign({ id: id('m'), title: title, href: href, icon: '', blank: false, who: '0', kids: [] }, over || {});
  const page = getSbPage;
  const link = (icon, title, url) => ({ id: id('n'), icon: icon, title: title, url: url });
  // A block: the fields of a row of _blocks the builder edits
  const blk = (title, type, pos, over) => Object.assign({ id: id('b'), title: title, type: type, kind: 'voting', content: '', url: '', pos: pos,
    which: ['all'], status: true, days: 0, action: 'd', lang: '', who: '0' }, over || {});
  const free = blk('Опрос недели', 'file', 'r', { kind: 'voting', which: ['infly'] });
  return {
    v: SB_VERSION,
    // The layout of the delivery, untouched: the editor offers the ready pages until the first publication takes the mark away
    shipped: true,
    theme: {
      sitename: 'Название сайта', tagline: 'Все великое\nпросто', logo: 'slaed-logo-wordmark-gradient-blue.svg',
      paneOn: true, paneCap: 'Актуальная версия', paneNum: '6.2', paneBtn: 'Скачать систему', paneHref: 'files',
      lineOn: true, lineTitle: 'Хотите опробовать SLAED CMS в действии?',
      address: 'D-49179, Deutschland\nOstercappeln, Im Siek 6', phone: '+49 176 61966679', email: 'support@slaed.net',
      web: 'https://slaed.net', mode: 'auto', season: 'date',
      techOn: true, techTitle: 'Технологии', madeOn: true,
      tech: SB_MARKS.map(([img, , , title]) => ({ id: id('n'), img: img, title: title })),
      tabsOn: true,
      tabs: [{ id: id('n'), title: 'Идеи и предложения', href: 'forum/idei-i-predlozheniya-dlya-razrabotki' }, { id: id('n'), title: 'Обратная связь', href: 'contact' }],
      social: [
        link('github', 'Мы в GitHub', 'https://github.com/SLAED-CMS/SLAED-CMS-6.3'), link('youtube', 'Мы в YouTube', 'https://www.youtube.com/@SLAED-CMS'),
        link('twitter-x', 'Мы в X', 'https://x.com/slaed_cms'), link('chat-square-text', 'Мы во ВКонтакте', 'https://vk.com/slaed_cms'),
        link('book-half', 'Документация на немецком', 'https://slaed.de'), link('book', 'Документация на английском', 'https://slaed.info'),
        link('star', 'Рекомендовать сайт', 'recommend'),
      ],
    },
    // The blocks of the site: in the system these are the rows of _blocks, their position bpos and their order weight
    blocks: [
      blk('Вход на сайт', 'file', 'r', { kind: 'login', who: '3' }),
      blk('Опрос', 'file', 'r', { kind: 'voting' }),
      blk('Кто на сайте', 'file', 'r', { kind: 'user_info', which: ['news'] }),
      blk('Набор в команду модераторов', 'html', 'c', { content: '<p>Ищем модераторов форума — напишите нам.</p>', which: ['news', 'forum'] }),
      blk('Новости партнёров', 'rss', 'l', { url: 'https://slaed.net/rss', status: false }),
      blk('Статистика', 'file', 'd', { kind: 'stat' }),
      free,
    ],
    menus: {
      main: [
        item('Услуги', '', { kids: [item('Темы и шаблоны', 'https://slaed.de', { blank: true })] }),
        item('Поддержка', '', { kids: [item('Вопросы и ответы', 'faq'), item('Центр документации', 'docs'), item('Общий форум проекта', 'forum'), item('Техническая поддержка', 'help'), item('Обратная связь', 'contact')] }),
        item('Каталог файлов', 'files'),
        item('Новости', 'news'),
        item('Компания', '', { kids: [item('Контактные данные', 'contact'), item('О проекте', 'about'), item('Каталог сайтов системы', 'links'), item('Опросы нашего проекта', 'voting')] }),
      ],
      foot: [
        item('Главная', '/', { icon: 'house-door' }), item('Новости', 'news', { icon: 'newspaper' }),
        item('Файлы', 'files', { icon: 'file-earmark-arrow-down' }), item('Вопросы и ответы', 'faq', { icon: 'question-circle' }),
        item('Документация', 'docs', { icon: 'book' }), item('Портфолио', 'presentation', { icon: 'easel' }),
        item('Карта сайта', 'sitemap', { icon: 'diagram-3' }),
      ],
    },
    pages: {
      // The home of the stand: the slot of presentation, the first module of $conf['module'], as the site answers today
      home: page('Главная', '/', {}, [sec('module', { module: 'presentation' })]),
      about: page('О проекте', 'about', { desc: 'SLAED CMS делают с 2005 года: открытый код, лицензия MIT, одна команда.', band: false }, [
        sec('hero', { over: 'О проекте · с 2005 года', title: 'Двадцать лет', accent: 'одной системы.', title2: '', text: 'Открытый код, лицензия MIT и одна команда: SLAED растёт вместе с сайтами, которые на нём работают.', btn: 'Связаться', href: 'contact', btn2: 'Скачать систему', href2: 'files', photo: 'uploads/presentation/brand/background-6.png', alt: 'Логотип SLAED на чёрном фоне' }),
        sec('features'), sec('media'),
        sec('text', { title: 'История проекта', lead: 'Коротко о главном', icon: 'clock-history', body: '### С 2005 года\n\nSLAED появилась как система для небольших порталов и выросла в **модульную CMS**.\n\n- открытый код под лицензией MIT;\n- обновления без правки ядра;\n- [документация](docs) и живой форум.' }),
        sec('stats'), sec('feed'), sec('sites'), sec('voices'), sec('faq'), sec('cta'), sec('block', { block: free.id }),
      ]),
      // A page of a module: its content stays the module's, the sections stand around it
      news: page('Новости', 'news', { side: '2', top: '0' }, [sec('module', { module: 'news' }),
        sec('cta', { over: 'Для авторов', title: 'Пишете о SLAED?', text: 'Пришлите новость — опубликуем в ленте.', btn: 'Предложить новость', href: 'contact' })]),
      // The page a missing address gets, its code 404 kept; it has no address of its own and is never indexed
      notfound: page('Страница не найдена', '', { band: false, noindex: true }, [
        sec('hero', { over: 'Ошибка 404', title: 'Такой страницы', accent: 'нет.', title2: '', text: 'Возможно, адрес изменился или страницу удалили. Начните с главной или загляните в свежие материалы.', btn: 'На главную', href: '/', btn2: 'Поиск по сайту', href2: 'search', pic: false, alt: '' }),
        sec('feed', { title: 'Свежие материалы', lead: '', count: 4 }),
      ]),
    },
    next: n,
  };
}
