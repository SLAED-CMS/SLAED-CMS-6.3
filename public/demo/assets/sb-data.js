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
const SB_PHOTOS = [1, 2, 3, 4, 5, 6, 7].map((n) => ['background-' + n, new URL('../uploads/presentation/brand/background-' + n + '.png', location.href).href, 'Фото ' + n]);
const SB_LOGOS = [
  ['slaed-logo-wordmark-gradient-blue.svg', 'Знак и имя, градиент'],
  ['slaed-logo-wordmark-solid-blue.svg', 'Знак и имя, синий'],
  ['slaed-logo-wordmark-solid-black.svg', 'Знак и имя, чёрный'],
  ['slaed-logo-wordmark-outline-blue.svg', 'Контур, синий'],
  ['slaed-logo-mark-gradient-blue.svg', 'Только знак, градиент'],
  ['slaed-logo-mark-duotone-blue.svg', 'Только знак, два тона'],
];
const SB_TONES = [['primary', 'Бренд'], ['success', 'Зелёный'], ['warning', 'Янтарный'], ['accent', 'Акцент'], ['danger', 'Красный']];
const SB_SEASONS = [['date', 'По дате'], ['sl-winter', 'Зима'], ['sl-spring', 'Весна'], ['sl-summer', 'Лето'], ['sl-autumn', 'Осень'], ['sl-newyear', 'Новый год']];
/* The files of blocks/ a file block runs: key, label, icon, file */
const SB_BLOCKS = [
  ['voting', 'Опрос', 'bar-chart', 'voting.php'], ['login', 'Вход на сайт', 'box-arrow-in-right', 'login.php'],
  ['stat', 'Статистика', 'graph-up', 'stat.php'], ['search', 'Поиск', 'search', 'search.php'],
  ['languages', 'Языки', 'translate', 'languages.php'], ['user_info', 'Кто на сайте', 'people', 'user_info.php'],
  ['forum', 'Форум', 'chat-dots', 'forum.php'], ['node', 'Материалы', 'newspaper', 'node.php'],
  ['img', 'Примеры внедрений', 'images', 'img.php'], ['banner_random', 'Случайный баннер', 'badge-ad', 'banner_random.php'],
];

/* The types of Node: what the feed reads through the engine of blocks/node.php, by the name their pages carry */
const SB_NODE_TYPES = [['news', 'Новости'], ['content', 'Содержание'], ['docs', 'Документация'], ['faq', 'Вопросы и ответы'],
  ['files', 'Каталог файлов'], ['links', 'Каталог сайтов'], ['help', 'Помощь']];

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
   builder is page:<key>; the home page is the home, as $home is for getBlocks() */
function getSbPageName(key, page) {
  const slot = (page.sections || []).find((s) => s.type === 'module');
  return slot ? slot.module : 'page:' + key;
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

/* A draft written before the model of the blocks takes it: file blocks, where to show, the columns of a page */
function setSbLayoutFit(layout) {
  (layout.blocks || []).forEach((b) => {
    if (!b.type) Object.assign(b, { type: 'file', content: '', url: '', status: true, days: 0, action: 'd', lang: '', which: ['all'] });
    if (b.who === undefined) b.who = b.view || '0';
  });
  Object.values(layout.pages || {}).forEach((pg) => {
    if (pg.side === undefined) {
      pg.side = pg.cols ? '0' : '3';
      pg.top = pg.cols ? '0' : '3';
    }
    pg.sections.forEach((s) => {
      if (s.who === undefined) s.who = '0';
      if (s.type === 'feed' && !['tile', 'list'].includes(s.view)) s.view = 'tile';
    });
  });
  return layout;
}

/* The modules of the stand: what the slot "Содержимое модуля" may hold and what "Пункты из модулей" offers the menu */
const SB_MODULES = [
  ['news', 'Новости', 'newspaper'], ['files', 'Файлы', 'file-earmark-arrow-down'], ['forum', 'Форум', 'chat-dots'],
  ['faq', 'Вопросы и ответы', 'question-circle'], ['docs', 'Документация', 'book'], ['voting', 'Опросы', 'bar-chart'],
  ['links', 'Каталог сайтов', 'link-45deg'], ['users', 'Пользователи', 'people'], ['contact', 'Обратная связь', 'envelope'],
];

/* Who sees a section and when: the same words and values as a block of the site (_blocks.view, expire), so one
   meaning holds for the whole site; then the anchor of the section and the devices it shows on */
const SB_SHOW = [
  { key: 'who', label: 'Кому', kind: 'select', opts: SB_WHO },
  { key: 'from', label: 'Показать с', kind: 'date' },
  { key: 'till', label: 'Показать до', kind: 'date' },
  { key: 'anchor', label: 'Якорь', kind: 'text' },
  { key: 'phone', label: 'На телефоне', kind: 'switch' },
  { key: 'desk', label: 'На компьютере', kind: 'switch' },
];
const getSbShow = () => ({ who: '0', from: '', till: '', anchor: '', phone: true, desk: true });

/* A field: key, label, kind, and what the kind needs. Kinds: text, area, select (opts), seg (opts), switch, range
   (min, max), icon, photo, logo, link, tone, code. tab: 'look' puts it on the second tab of the inspector */
const SB_TYPES = {
  module: {
    title: 'Содержимое модуля', icon: 'puzzle', group: 'Основа', one: true,
    note: 'Ответ модуля страницы: лента, каталог или форум, как сегодня',
    fields: [
      { key: 'module', label: 'Модуль', kind: 'select', opts: SB_MODULES.map(([k, t]) => [k, t]) },
    ],
    make: () => ({ module: 'news' }),
  },
  hero: {
    title: 'Обложка', icon: 'image', group: 'Основа',
    note: 'Фотография во всю ширину, заголовок, текст и две кнопки',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'text', label: 'Текст', kind: 'area' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
      { key: 'btn2', label: 'Вторая кнопка', kind: 'text' },
      { key: 'href2', label: 'Вторая ведёт на', kind: 'link' },
      { key: 'photo', label: 'Фото', kind: 'photo', tab: 'look' },
      { key: 'align', label: 'Выравнивание', kind: 'seg', opts: [['left', 'Слева', 'text-left'], ['center', 'По центру', 'text-center']], tab: 'look' },
      { key: 'size', label: 'Высота', kind: 'seg', opts: [['s', 'Низкая'], ['m', 'Средняя'], ['l', 'Высокая']], tab: 'look' },
      { key: 'veil', label: 'Затемнение', kind: 'switch', tab: 'look' },
    ],
    make: () => ({
      title: 'Всё великое просто', text: 'Сайт, который вы собираете сами: секции, меню и вид — без строчки кода.',
      btn: 'Скачать систему', href: 'files', btn2: 'Посмотреть возможности', href2: 'presentation',
      photo: 'background-1', align: 'left', size: 'm', veil: true,
    }),
  },
  features: {
    title: 'Преимущества', icon: 'grid-3x2-gap', group: 'Основа',
    note: 'Карточки с иконкой, заголовком и текстом в 2–4 колонки',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'intro', label: 'Вступление', kind: 'area' },
      { key: 'cols', label: 'Колонки', kind: 'seg', opts: [['2', '2'], ['3', '3'], ['4', '4']], tab: 'look' },
      { key: 'look', label: 'Карточки', kind: 'seg', opts: [['tile', 'Плитки'], ['line', 'Строки']], tab: 'look' },
      { key: 'tone', label: 'Тон иконок', kind: 'tone', tab: 'look' },
    ],
    items: {
      label: 'Карточки', one: 'карточку', max: 8, name: 'title',
      fields: [
        { key: 'icon', label: 'Иконка', kind: 'icon' },
        { key: 'title', label: 'Заголовок', kind: 'text' },
        { key: 'text', label: 'Текст', kind: 'area' },
      ],
      make: () => ({ icon: 'star', title: 'Новая карточка', text: 'Пара слов о том, чем это хорошо.' }),
    },
    make: () => ({
      title: 'Почему SLAED', intro: 'Двадцать лет одной системы: всё, что нужно сайту, уже в коробке.', cols: '4', look: 'tile', tone: 'primary',
      items: [
        { icon: 'shield-check', title: 'Безопасность', text: 'Подготовленные запросы, токены форм и журнал панели.' },
        { icon: 'lightning-charge', title: 'Скорость', text: 'Страница за сотые доли секунды и дюжину запросов.' },
        { icon: 'puzzle', title: 'Модули', text: 'Новости, форум, файлы, опросы и типы Node.' },
        { icon: 'palette', title: 'Темы', text: 'Тёмная схема, сезоны и токены вместо правки CSS.' },
      ],
    }),
  },
  feed: {
    title: 'Свежие материалы', icon: 'newspaper', group: 'Материалы сайта',
    note: 'Последние записи модуля, обновляются сами',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'source', label: 'Типы', kind: 'multi', opts: SB_NODE_TYPES },
      { key: 'mode', label: 'Какие', kind: 'seg', opts: [['last', 'Новые'], ['home', 'Для главной']] },
      { key: 'count', label: 'Сколько', kind: 'range', min: 2, max: 9 },
      { key: 'view', label: 'Вид', kind: 'seg', opts: [['tile', 'Плитки', 'grid'], ['list', 'Список', 'list-ul']], tab: 'look' },
      { key: 'date', label: 'Дата', kind: 'switch', tab: 'look' },
      { key: 'more', label: 'Ссылка «Все»', kind: 'switch', tab: 'look' },
    ],
    make: () => ({ title: 'Новости проекта', source: ['news'], mode: 'last', count: 3, view: 'tile', date: true, more: true }),
  },
  media: {
    title: 'Текст и картинка', icon: 'layout-text-sidebar', group: 'Основа',
    note: 'Абзац рядом с картинкой — слева или справа',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'text', label: 'Текст', kind: 'area' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
      { key: 'photo', label: 'Картинка', kind: 'photo', tab: 'look' },
      { key: 'side', label: 'Положение картинки', kind: 'seg', opts: [['left', 'Слева'], ['right', 'Справа']], tab: 'look' },
    ],
    make: () => ({
      title: 'Один модуль вместо шести', text: 'Типы Node собирают новости, статьи, страницы и каталоги из одного движка: поля, виды и права настраиваются в панели.',
      btn: 'Читать документацию', href: 'docs', photo: 'background-4', side: 'right',
    }),
  },
  stats: {
    title: 'Цифры', icon: '123', group: 'Доверие',
    note: 'Несколько крупных чисел с подписью',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'tone', label: 'Тон чисел', kind: 'tone', tab: 'look' },
    ],
    items: {
      label: 'Числа', one: 'число', max: 6, name: 'num',
      fields: [
        { key: 'num', label: 'Число', kind: 'text' },
        { key: 'label', label: 'Подпись', kind: 'text' },
      ],
      make: () => ({ num: '100', label: 'Подпись' }),
    },
    make: () => ({
      title: 'SLAED в цифрах', tone: 'primary',
      items: [
        { num: '2005', label: 'год первого выпуска' }, { num: '685+', label: 'дополнений в каталоге' },
        { num: '0,027 с', label: 'генерация страницы' }, { num: 'MIT', label: 'свободная лицензия' },
      ],
    }),
  },
  sites: {
    title: 'Внедрения', icon: 'images', group: 'Доверие',
    note: 'Снимки сайтов из каталога в собственном размере',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'count', label: 'Сколько', kind: 'range', min: 3, max: 12 },
    ],
    make: () => ({ title: 'Сайты на SLAED', count: 4 }),
  },
  faq: {
    title: 'Вопросы и ответы', icon: 'patch-question', group: 'Связь',
    note: 'Раскрывающиеся вопросы, как в модуле «Вопросы и ответы»',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'open', label: 'Первый вопрос открыт', kind: 'switch', tab: 'look' },
    ],
    items: {
      label: 'Вопросы', one: 'вопрос', max: 12, name: 'q',
      fields: [
        { key: 'q', label: 'Вопрос', kind: 'text' },
        { key: 'a', label: 'Ответ', kind: 'area' },
      ],
      make: () => ({ q: 'Новый вопрос?', a: 'Ответ на него.' }),
    },
    make: () => ({
      title: 'Частые вопросы', open: true,
      items: [
        { q: 'Нужна ли база данных для этой страницы?', a: 'Нет: секции, меню и вид сайта лежат в одном файле настроек, база отдаёт только материалы.' },
        { q: 'Можно ли вернуть прежний вид?', a: 'Да: у черновика есть отмена шагов, опубликованное остаётся прежним до нажатия «Опубликовать».' },
        { q: 'Что будет при смене темы?', a: 'Каждая тема объявляет свои секции; содержимое остаётся, вид даёт новая тема.' },
      ],
    }),
  },
  cta: {
    title: 'Призыв', icon: 'megaphone', group: 'Связь',
    note: 'Короткая фраза и кнопка с цветной полосой',
    fields: [
      { key: 'title', label: 'Заголовок', kind: 'text' },
      { key: 'text', label: 'Текст', kind: 'area' },
      { key: 'btn', label: 'Кнопка', kind: 'text' },
      { key: 'href', label: 'Ведёт на', kind: 'link' },
      { key: 'tone', label: 'Тон', kind: 'tone', tab: 'look' },
    ],
    make: () => ({ title: 'Остались вопросы?', text: 'Напишите нам — ответим в течение дня.', btn: 'Обратная связь', href: 'contact', tone: 'primary' }),
  },
  block: {
    title: 'Блок сайта', icon: 'bricks', group: 'Материалы сайта',
    note: 'Блок из «Блоков и баннеров» по номеру — как тег [block=N]',
    fields: [
      { key: 'block', label: 'Блок', kind: 'blockref' },
    ],
    make: () => ({ block: '' }),
  },
  html: {
    title: 'Свой HTML', icon: 'code-slash', group: 'Для мастеров',
    note: 'Разметка как есть — только для администраторов',
    fields: [
      { key: 'code', label: 'Разметка', kind: 'code' },
    ],
    make: () => ({ code: '<div class="sl-wrp"><p>Здесь может быть любая разметка.</p></div>' }),
  },
};

/* The parts of the theme around the content: each is a place on every page, set once for the whole site */
const SB_PARTS = {
  brand: {
    title: 'Логотип и слоган', icon: 'badge-tm', where: 'Шапка',
    fields: [
      { key: 'sitename', label: 'Название сайта', kind: 'text' },
      { key: 'slogan', label: 'Слоган', kind: 'area' },
      { key: 'logo', label: 'Логотип', kind: 'logo' },
    ],
  },
  pane: {
    title: 'Плашка версии', icon: 'tag', where: 'Шапка',
    fields: [
      { key: 'paneOn', label: 'Показывать', kind: 'switch' },
      { key: 'paneCap', label: 'Подпись', kind: 'text' },
      { key: 'paneNum', label: 'Версия', kind: 'text' },
      { key: 'paneBtn', label: 'Кнопка', kind: 'text' },
      { key: 'paneHref', label: 'Ведёт на', kind: 'link' },
    ],
  },
  social: {
    title: 'Соцсети', icon: 'share', where: 'Верхняя строка',
    fields: [],
    items: {
      key: 'social', label: 'Сети', one: 'сеть', max: 8, name: 'title',
      fields: [
        { key: 'icon', label: 'Иконка', kind: 'icon' },
        { key: 'title', label: 'Подпись', kind: 'text' },
        { key: 'url', label: 'Адрес', kind: 'text' },
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
      { key: 'tagline', label: 'Подпись под логотипом', kind: 'text' },
    ],
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
  { key: 'landing', title: 'Лендинг', icon: 'rocket-takeoff', note: 'Обложка, преимущества, цифры, внедрения, призыв', list: [['hero'], ['features'], ['stats'], ['sites'], ['cta']] },
  { key: 'company', title: 'О компании', icon: 'building', note: 'Обложка, текст с картинкой, цифры, вопросы', list: [['hero', { size: 's' }], ['media'], ['stats'], ['faq']] },
  { key: 'contacts', title: 'Контакты', icon: 'envelope', note: 'Обложка, форма обратной связи, призыв', list: [['hero', { size: 's', btn2: '' }], ['module', { module: 'contact' }], ['cta']] },
  {
    key: 'card', title: 'Визитка', icon: 'person-vcard', note: 'Сайт в одну страницу: секции с якорями для меню',
    list: [['hero', { anchor: 'nachalo' }], ['features', { anchor: 'uslugi' }], ['media', { anchor: 'o-nas' }], ['faq', { anchor: 'voprosy' }], ['cta', { anchor: 'kontakty' }]],
  },
];

const SB_MENUS = {
  main: { title: 'Главное меню', where: 'Под шапкой', depth: 2, icons: false },
  foot: { title: 'Нижнее меню', where: 'Подвал', depth: 1, icons: true },
};

/* The layout of a fresh site: the shipped header and footer, now values instead of HTML, and two pages */
function getSbStart() {
  let n = 0;
  const id = (p) => p + (++n);
  const sec = (type, over) => Object.assign({ id: id('s'), type: type, on: true }, SB_TYPES[type].make(), getSbShow(), over || {});
  const item = (title, href, over) => Object.assign({ id: id('m'), title: title, href: href, icon: '', blank: false, kids: [] }, over || {});
  // A block: the fields of a row of _blocks the builder edits
  const blk = (title, type, pos, over) => Object.assign({ id: id('b'), title: title, type: type, kind: 'voting', content: '', url: '', pos: pos,
    which: ['all'], status: true, days: 0, action: 'd', lang: '', who: '0' }, over || {});
  const free = blk('Опрос недели', 'file', 'r', { kind: 'voting', which: ['infly'] });
  return {
    theme: {
      sitename: 'Название сайта', slogan: 'Все великое\nпросто', logo: 'slaed-logo-wordmark-gradient-blue.svg',
      paneOn: true, paneCap: 'Актуальная версия', paneNum: '8.0', paneBtn: 'Скачать систему', paneHref: 'files',
      lineOn: true, lineTitle: 'Хотите опробовать SLAED CMS в действии?',
      address: 'D-49179, Deutschland\nOstercappeln, Im Siek 6', phone: '+49 176 61966679', email: 'support@slaed.net',
      web: 'https://slaed.net', tagline: 'Все великое просто', mode: 'auto', season: 'date',
      social: [
        { id: id('n'), icon: 'github', title: 'Мы в GitHub', url: 'https://github.com/SLAED-CMS' },
        { id: id('n'), icon: 'youtube', title: 'Мы в YouTube', url: 'https://www.youtube.com/@SLAED-CMS' },
        { id: id('n'), icon: 'twitter-x', title: 'Мы в X', url: 'https://x.com/slaed_cms' },
        { id: id('n'), icon: 'chat-square-text', title: 'Мы вКонтакте', url: 'https://vk.com/slaed_cms' },
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
        item('Документация', 'docs', { icon: 'book' }), item('Карта сайта', 'sitemap', { icon: 'diagram-3' }),
      ],
    },
    pages: {
      home: { title: 'Главная', addr: '/', side: '3', top: '3', sections: [sec('hero'), sec('features'), sec('feed'), sec('sites'), sec('cta')] },
      about: {
        title: 'О проекте', addr: 'about', side: '3', top: '3', sections: [
          sec('hero', { title: 'О проекте', text: 'SLAED CMS делают с 2005 года: открытый код, лицензия MIT, одна команда.', btn: 'Связаться', href: 'contact', btn2: '', photo: 'background-6', align: 'left', size: 's' }),
          sec('media'), sec('stats'), sec('faq'), sec('block', { block: free.id }),
        ],
      },
      // A page of a module: its content stays the module's, the sections stand around it
      news: {
        title: 'Новости', addr: 'news', side: '2', top: '0', sections: [sec('module', { module: 'news' }),
          sec('cta', { title: 'Пишете о SLAED?', text: 'Пришлите новость — опубликуем в ленте.', btn: 'Предложить новость', href: 'contact' })],
      },
    },
    next: n,
  };
}
