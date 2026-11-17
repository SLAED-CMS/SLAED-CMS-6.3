/* The manifests of the stand series and the shell a face of a series stands in: the header and the footer of aurora, the
   panel of the stand and the travelling knob. index.html draws its wall from DEMO_SERIES */

/* The window of the panel: four rooms on the theme admin (assets/win.js), the desk mixes the axes in place */
const DEMO_WIN = [
  {
    file: 'win-01-panel.html',
    title: 'Окно панели',
    note: 'Окно панели администратора решено и стоит в теме. Вариант показывает справку раздела в одном окне, с рельсом разделов в строку и кантом у переключателя. ' +
      'Пульт оставляет для сравнения остальные виды: окно на раздел, змейку из карточек, ленту с точками, переключатель без канта. Исходный набор — панель как сейчас.',
    tags: ['рамка тонкая', 'радиус 8', 'градиент', 'капс и иконка', 'черта', 'поля 8', 'тень'],
  },
];

/* The window of the site: the page of one forum topic on the theme aurora with the blocks of the side column
   (assets/frm.js); the desk mixes the axes of the window and of the page in place */
const DEMO_FORUM = [
  {
    file: 'frm-01-topic.html',
    title: 'Окно сайта',
    note: 'Окно сайта решено и стоит в теме. Вариант показывает его на странице темы форума, с блоками боковой колонки. ' +
      'Пульт оставляет для сравнения остальные значения: рамку, радиус, поверхность, шапку, размер названия и иконки, поля, низ окна и тень, ' +
      'а у страницы — окно темы, разделитель сообщений, форму ответа и кнопки. Исходный набор — как на сайте сейчас.',
    tags: ['рамка тонкая', 'радиус 8', 'градиент', 'название 18', 'иконка 18', 'поля 12', 'низ 12', 'тень'],
  },
];

/* The page of the module sitemap in four looks built from markup the theme already draws (assets/map.js); the desk
   switches the look in place */
const DEMO_MAP = [
  {
    file: 'map-01-sitemap.html',
    title: 'Карта сайта',
    note: 'Страница модуля «Карта сайта» в четырёх видах на готовых механиках темы. Пульт переключает вид на месте: окна с раскрываемыми категориями, ' +
      'рельс разделов, плитки категорий, сворачиваемые окна с нумерованным деревом. Наполнение во всех видах одно. У выбранного вида «Окна и ветки» семь дополнений, каждое со своим выключателем. Набор со всеми дополнениями принят и стоит на сайте, кнопка «Как на сайте» возвращает его.',
    tags: ['окна и ветки', 'рельс', 'плитки', 'дерево'],
  },
];

/* The settings of a section: the page "Поиск → Конфигурации" on the theme admin (assets/cfg.js); the desk picks one of
   four sets and mixes the three axes they are made of */
const DEMO_CFG = [
  {
    file: 'cfg-01-search.html',
    title: 'Настройки поиска',
    note: 'Страница «Поиск → Конфигурации» в четырёх наборах. Пульт выбирает набор и меняет три оси, из которых он собран. ' +
      'Первая ставит источники поиска на страницу, на отдельную вкладку или рядом с настройками. Вторая меняет форму списка, третья показывает или сворачивает несовместимые модули. ' +
      'Наполнение во всех наборах одно. Кнопка «Как сейчас» возвращает страницу в нынешнем виде.',
    tags: ['две вкладки', 'окна', 'два столбца', 'плитки'],
  },
];

const DEMO_SERIES = [
  { key: 'windows', title: 'Окно панели', addr: 'admin.php', items: DEMO_WIN },
  { key: 'config', title: 'Настройки раздела', addr: 'admin.php?name=search&op=config', items: DEMO_CFG },
  { key: 'forum', title: 'Окно сайта', addr: 'index.php?name=forum&op=view', items: DEMO_FORUM },
  { key: 'sitemap', title: 'Карта сайта', addr: 'index.php?name=sitemap', items: DEMO_MAP },
];

/* Which series a file belongs to, and where it stands in it. An unknown file gets the first series at index -1,
   which is what the panel already treats as "the stand itself". */
function getDemoPlace(file) {
  for (const series of DEMO_SERIES) {
    const idx = series.items.findIndex((v) => v.file === file);
    if (idx >= 0) return { series: series, idx: idx, item: series.items[idx] };
  }
  return { series: DEMO_SERIES[0], idx: -1, item: null };
}

const DEMO_SEASONS = [
  ['sl-winter', 'Зима'],
  ['sl-spring', 'Весна'],
  ['sl-summer', 'Лето'],
  ['sl-autumn', 'Осень'],
  ['sl-newyear', 'Новый год'],
];

const DEMO_MODES = [
  ['light', 'Светлая'],
  ['auto', 'Системная'],
  ['dark', 'Тёмная'],
];

const T = '../templates/aurora';

const CHROME_TOP = `
  <div id="topbar">
    <div class="sl-wrp">
      <ul class="sl-top-contact"><li class="sl-head-marquee"><a href="#" title="Как и где можно установить свою тему оформления?"><i class="bi bi-stars" aria-hidden="true"></i>&nbsp;Как и где можно установить свою тему оформления?</a></li></ul>
      <div class="sl-top-right">
        <form action="" method="get" data-demo-mode>
          <span class="sl-seg sl-seg-mode" role="group" aria-label="Тема оформления" data-sl-knob="mode">
            <button type="submit" class="sl-seg-cell" name="mode" value="light" title="Светлая" aria-label="Светлая"><i class="bi bi-sun" aria-hidden="true"></i></button><button type="submit" class="sl-seg-cell" name="mode" value="auto" title="Системная" aria-label="Системная"><i class="bi bi-circle-half" aria-hidden="true"></i></button><button type="submit" class="sl-seg-cell" name="mode" value="dark" title="Тёмная" aria-label="Тёмная"><i class="bi bi-moon" aria-hidden="true"></i></button>
            <span class="sl-seg-knob" data-sl-knob-mark aria-hidden="true"></span>
          </span>
        </form>
        <div class="sl-top-social">
          <a class="sl-thd sl-circle-action sl-cat-tone-0" href="#" title="Мы в GitHub" aria-label="Мы в GitHub"><i class="bi bi-github" aria-hidden="true"></i>Мы в GitHub</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-1" href="#" title="Мы в YouTube" aria-label="Мы в YouTube"><i class="bi bi-youtube" aria-hidden="true"></i>Мы в YouTube</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-2" href="#" title="Мы в X" aria-label="Мы в X"><i class="bi bi-twitter-x" aria-hidden="true"></i>Мы в X</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-3" href="#" title="Мы вКонтакте" aria-label="Мы вКонтакте"><i class="bi bi-chat-square-text" aria-hidden="true"></i>Мы вКонтакте</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-4" href="#" title="Документация на немецком" aria-label="Документация на немецком"><i class="bi bi-book-half" aria-hidden="true"></i>Документация на немецком</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-5" href="#" title="Документация на английском" aria-label="Документация на английском"><i class="bi bi-book" aria-hidden="true"></i>Документация на английском</a>
        </div>
      </div>
    </div>
  </div>
  <header id="header">
    <div class="sl-wrp">
      <a href="#" class="sl-thd sl-logo" title="SLAED CMS">
        <img src="${T}/images/logos/slaed-logo-wordmark-gradient-blue.svg" alt="SLAED CMS" width="8833" height="2699">
      </a>
      <p class="sl-font sl-slogan">Все великое<br>просто</p>
      <ul class="sl-login-top sl-login-top--head">
        <li class="sl-login-dropdown sl-float">
          <span class="sl-login-toggle" role="button" tabindex="0" title="Войти"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i><b>Войти</b></span>
        </li>
        <li><a href="#" title="Регистрация" class="sl-login-link sl-login-link-top sl-login-link-register">Регистрация</a></li>
      </ul>
      <ul class="sl-d-pane">
        <li class="sl-d-info sl-d-version sl-font">Aктуальная версия</li>
        <li class="sl-d-num sl-font">6.2</li>
        <li class="sl-d-btns"><a class="sl-but" href="#" title="Скачать бесплатно актуальную версию системы">Скачать систему</a></li>
      </ul>
    </div>
  </header>
  <nav id="hmenu" aria-label="Главное меню">
    <div class="sl-wrp">
      <div id="topmenu">
        <ul>
          <li><a href="#" class="sl-home-link sl-circle-action" title="Главная"><i class="bi bi-house-door-fill" aria-hidden="true"></i><span class="sl-hidden">Главная</span></a></li>
          <li><a href="#" title="Продукты">Продукты</a>
            <ul>
              <li><a href="#" title="Все продукты проекта">Все продукты проекта</a></li>
              <li><a href="#" title="Новая установка системы на хостинг">Новая установка системы на хостинг</a></li>
              <li><a href="#" title="Обновление системы до актуальной версии">Обновление системы</a></li>
            </ul>
          </li>
          <li><a href="#" title="Услуги">Услуги</a>
            <ul>
              <li><a href="#" title="Создание и модификация">Создание и модификация</a></li>
              <li><a href="#" title="Темы и шаблоны">Темы и шаблоны</a></li>
            </ul>
          </li>
          <li><a href="#" title="Партнерам">Партнерам</a></li>
          <li><a href="#" title="Поддержка">Поддержка</a>
            <ul>
              <li><a href="#" title="Вопросы и ответы">Вопросы и ответы</a></li>
              <li><a href="#" title="Центр документации">Центр документации</a></li>
              <li><a href="#" title="Общий форум проекта">Общий форум проекта</a></li>
            </ul>
          </li>
          <li><a href="#" title="Каталог файлов">Каталог файлов</a></li>
          <li><a href="#" title="Новости">Новости</a></li>
          <li><a href="#" title="Компания">Компания</a></li>
        </ul>
      </div>
      <form class="sl-search-form" role="search" action="#" method="post" onsubmit="return false">
        <input type="text" value="" name="word" maxlength="100" placeholder="Поиск" aria-label="Поиск" class="sl-field">
        <button type="submit" class="sl-search-btn sl-circle-action" aria-label="Поиск" title="Поиск"><i class="bi bi-search" aria-hidden="true"></i></button>
      </form>
    </div>
  </nav>`;

/* The tiles of the footer, as partials/site-footer.html draws a mark of the theme: the mono drawing and the coloured one over it */
const CHROME_MARKS = [['php', 'PHP'], ['mysql', 'MySQL / MariaDB'], ['htmx', 'htmx'], ['html5', 'HTML5'], ['css', 'CSS3'], ['javascript', 'JavaScript']];

const CHROME_FOOT = `
  <section id="demo-line" aria-label="Хотите опробовать SLAED CMS в действии?">
    <div class="sl-wrp">
      <div class="sl-font sl-demo-line-title">Хотите опробовать SLAED CMS в действии?</div>
      <ul class="sl-d-pane">
        <li class="sl-d-info sl-d-version sl-font">Aктуальная версия</li>
        <li class="sl-d-num sl-font">6.2</li>
        <li class="sl-d-btns"><a class="sl-but" href="#" title="Скачать бесплатно актуальную версию системы">Скачать систему</a></li>
      </ul>
    </div>
  </section>
  <footer id="footbox">
    <div class="sl-wrp sl-grid-1-4">
      <section class="sl-grid" aria-label="SLAED CMS">
        <a href="#" class="sl-upper-wordmark" title="SLAED CMS">
          <img class="sl-upper-wordmark-img" src="${T}/images/logos/slaed-logo-wordmark-outline-blue.svg" alt="SLAED CMS" width="355" height="110">
          <span class="sl-upper-tagline sl-font">Все великое просто</span>
        </a>
        <div class="sl-license"><a href="#" title="SLAED CMS">SLAED CMS</a> &copy; 2005-2026 Eduard Laas. Released under MIT License.</div>
        <div class="sl-generates">Демонстрационный стенд: в поставку не входит</div>
        <a class="sl-thd sl-madein sl-madein-brand" href="#" title="Официальный патент на бренд SLAED в Германии">
          <img src="${T}/images/flags/de.svg" alt="Made in Germany" width="60" height="40">
          <span class="sl-madein-label sl-font">Made<br>in<br>Germany</span>
        </a>
      </section>
      <section class="sl-grid" aria-label="Форум">
        <p title="Форум" class="sl-font sl-f-title">Форум</p>
        <ul class="sl-list-item">
          <li><a href="#" title="Пожелания к версии SLAED CMS 6.3">Пожелания к версии SLAED CMS 6.3</a></li>
          <li><a href="#" title="Дата выхода новой версии SLAED CMS 6.3">Дата выхода новой версии SLAED CMS 6.3</a></li>
          <li><a href="#" title="Тестируем релиз Pre-Alpha версии 6.3 Pro">Тестируем релиз Pre-Alpha версии 6.3 Pro</a></li>
        </ul>
      </section>
      <section class="sl-grid" aria-label="Технологии">
        <div class="sl-font sl-f-title">Технологии</div>
        <div class="sl-partners">
          ${CHROME_MARKS.map(([logo, name]) => `<a href="#" title="${name}"><img src="${T}/images/tech/${logo}.svg" alt="${name}" width="74" height="74">` +
            `<img src="${T}/images/tech/${logo}-color.svg" alt="" width="74" height="74"></a>`).join('')}
        </div>
      </section>
      <section class="sl-grid" aria-label="Контакты">
        <div class="sl-font sl-f-title">Контакты</div>
        <address>
          <ul class="sl-block-contact">
            <li><i class="bi bi-geo-alt sl-contact-icon" aria-hidden="true"></i>D-49179, Deutschland<br>Ostercappeln, Im Siek 6</li>
            <li><i class="bi bi-telephone sl-contact-icon" aria-hidden="true"></i>+49 176 61966679</li>
            <li><i class="bi bi-envelope sl-contact-icon" aria-hidden="true"></i><a href="#">support@slaed.net</a></li>
            <li><i class="bi bi-globe sl-contact-icon" aria-hidden="true"></i><a href="#">https://slaed.net</a></li>
          </ul>
        </address>
      </section>
    </div>
    <div class="sl-wrp">
      <nav class="sl-fmenu" aria-label="Нижнее меню">
        <ul>
          <li><a href="#" title="Главная"><i class="bi bi-house-door" aria-hidden="true"></i>Главная</a></li>
          <li><a href="#" title="Платные услуги проекта"><i class="bi bi-briefcase" aria-hidden="true"></i>Услуги</a></li>
          <li><a href="#" title="Новости"><i class="bi bi-newspaper" aria-hidden="true"></i>Новости</a></li>
          <li><a href="#" title="Каталог файлов"><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i>Файлы</a></li>
          <li><a href="#" title="Вопросы и ответы"><i class="bi bi-question-circle" aria-hidden="true"></i>Вопросы и ответы</a></li>
          <li><a href="#" title="Центр документации"><i class="bi bi-book" aria-hidden="true"></i>Документация</a></li>
          <li><a href="#" title="Презентационная страница системы"><i class="bi bi-easel" aria-hidden="true"></i>Портфолио</a></li>
          <li><a href="#" title="Карта сайта"><i class="bi bi-diagram-3" aria-hidden="true"></i>Карта сайта</a></li>
        </ul>
        <a class="sl-pull-right" href="#" title="Размещение рекламы на проекте"><i class="bi bi-megaphone" aria-hidden="true"></i>По вопросам рекламы</a>
      </nav>
    </div>
  </footer>`;

/* The inner shell. A module page does not live in the full 1320: it runs the season band, then `#container`
   splitting into `main#content` at 896 and `aside#sidebar` at 400. A variant judged at 1320 without the band
   and without the column beside it is judged in a room it will never get, so a variant that names its band with
   `data-demo-band` is wrapped in the real one and the sidebar carries real blocks. */
function getChromeBand(caption) {
  return `
  <div id="head-content">
    <div class="sl-wrp"><p class="sl-font">${caption}</p></div>
  </div>`;
}

const CHROME_SIDE = `
  <aside id="sidebar">
    <section class="sl-block">
      <div class="sl-pane-cap"><i class="bi bi-bar-chart" aria-hidden="true"></i><h2 class="sl-pane-title">Опрос</h2></div>
      <div class="sl-block-content">
        <div class="sl-vote">
          <h3 class="sl-vote-title">Какой текстовый редактор кода вы используете?</h3>
          <ul class="sl-vote-list">
            <li class="sl-lead">
              <div class="sl-progress-info">
                <span title="Notepad++"><i class="bi bi-trophy-fill" aria-hidden="true"></i>Notepad++</span>
                <span class="sl-pull-right" title="Notepad++ — 38.88%"><b class="sl-vote-pct">38.88%</b> <b>(Голосов: <span class="sl-vote-num">208</span>)</b></span>
              </div>
              <div class="sl-progress-line sl-progress-5" title="Notepad++ — 38.88%"><div style="width: 38.88%">38.88%</div></div>
            </li>
            <li>
              <div class="sl-progress-info">
                <span title="Visual Studio Code"><i class="bi bi-trophy-fill" aria-hidden="true"></i>Visual Studio Code</span>
                <span class="sl-pull-right" title="Visual Studio Code — 24.11%"><b class="sl-vote-pct">24.11%</b> <b>(Голосов: <span class="sl-vote-num">129</span>)</b></span>
              </div>
              <div class="sl-progress-line sl-progress-1" title="Visual Studio Code — 24.11%"><div style="width: 24.11%">24.11%</div></div>
            </li>
            <li>
              <div class="sl-progress-info">
                <span title="Sublime Text"><i class="bi bi-trophy-fill" aria-hidden="true"></i>Sublime Text</span>
                <span class="sl-pull-right" title="Sublime Text — 10.28%"><b class="sl-vote-pct">10.28%</b> <b>(Голосов: <span class="sl-vote-num">55</span>)</b></span>
              </div>
              <div class="sl-progress-line sl-progress-2" title="Sublime Text — 10.28%"><div style="width: 10.28%">10.28%</div></div>
            </li>
            <li>
              <div class="sl-progress-info">
                <span title="PhpStorm/WebStorm"><i class="bi bi-trophy-fill" aria-hidden="true"></i>PhpStorm/WebStorm</span>
                <span class="sl-pull-right" title="PhpStorm/WebStorm — 8.79%"><b class="sl-vote-pct">8.79%</b> <b>(Голосов: <span class="sl-vote-num">47</span>)</b></span>
              </div>
              <div class="sl-progress-line sl-progress-3" title="PhpStorm/WebStorm — 8.79%"><div style="width: 8.79%">8.79%</div></div>
            </li>
            <li>
              <div class="sl-progress-info">
                <span title="Atom"><i class="bi bi-trophy-fill" aria-hidden="true"></i>Atom</span>
                <span class="sl-pull-right" title="Atom — 4.49%"><b class="sl-vote-pct">4.49%</b> <b>(Голосов: <span class="sl-vote-num">24</span>)</b></span>
              </div>
              <div class="sl-progress-line sl-progress-4" title="Atom — 4.49%"><div style="width: 4.49%">4.49%</div></div>
            </li>
          </ul>
          <div class="sl-vote-links">
            <span class="sl-chip sl-chip-neutral"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i> Голосов: 535</span>
            <span class="sl-chip sl-chip-info"><i class="bi bi-chat-square-text" aria-hidden="true"></i> Комментарии: 3</span>
          </div>
        </div>
      </div>
    </section>
    <section class="sl-block">
      <div class="sl-pane-cap"><i class="bi bi-people" aria-hidden="true"></i><h2 class="sl-pane-title">Пользователи</h2></div>
      <div class="sl-block-content">
        <div class="sl-session-lines">
          <div class="sl-session-line"><i class="bi bi-person-fill" aria-hidden="true"></i><span>Пользователей</span><b>1</b></div>
          <div class="sl-session-line"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Гостей</span><b>0</b></div>
          <div class="sl-session-line sl-session-total"><i class="bi bi-people" aria-hidden="true"></i><span>Всего</span><b>1</b></div>
        </div>
      </div>
    </section>
  </aside>`;

/* Move the variant into main#content beside the sidebar and return the wrap the page chrome is inserted around */
function setCabinetShell(page) {
  const wrap = document.createElement('div');
  wrap.className = 'sl-wrp';
  wrap.innerHTML = '<div id="container" class="sl-has-right"><main id="content"></main>' + CHROME_SIDE + '</div>';
  page.parentNode.insertBefore(wrap, page);
  wrap.querySelector('#content').appendChild(page);
  return wrap;
}

const demoState = {
  mode: localStorage.getItem('demo.mode') || document.documentElement.dataset.theme || 'auto',
  season: localStorage.getItem('demo.season') || 'sl-summer',
  motion: localStorage.getItem('demo.motion') || 'on',
};

/* The panel of the stand: the mode, the season, the motion, the way to the gallery and, in a series of several, the neighbours */
function getDemoPanel(file) {
  const place = getDemoPlace(file);
  const list = place.series.items;
  const safe = place.idx < 0 ? 0 : place.idx;
  const prev = list[(safe - 1 + list.length) % list.length];
  const next = list[(safe + 1) % list.length];
  const solo = list.length < 2;
  const no = (v) => v.file.match(/^(\d{2})-/)?.[1] || String(list.indexOf(v) + 1).padStart(2, '0');
  const seg = (name, items, active) => items.map(([val, label]) =>
    `<button type="button" data-demo-set="${name}" data-demo-value="${val}" aria-pressed="${val === active}">${label}</button>`).join('');
  const el = document.createElement('div');
  el.className = 'demo-panel';
  el.innerHTML = `
    <div class="demo-panel-head">
      <span class="demo-panel-title">${place.item ? no(place.item) + ' &middot; ' + place.item.title : 'Стенд'}</span>
      <button type="button" class="demo-panel-toggle" title="Свернуть">&ndash;</button>
    </div>
    <div class="demo-panel-row"><span>Тема</span><div class="demo-seg">${seg('mode', DEMO_MODES, demoState.mode)}</div></div>
    <div class="demo-panel-row"><span>Сезон</span><div class="demo-seg">${seg('season', DEMO_SEASONS, demoState.season)}</div></div>
    <div class="demo-panel-row"><span>Движение</span><div class="demo-seg">${seg('motion', [['on', 'Вкл'], ['off', 'Выкл']], demoState.motion)}</div></div>
    <div class="demo-panel-nav">
      ${solo ? '' : `<a href="${prev.file}" title="${prev.title}">&larr; ${no(prev)}</a>`}
      <a href="index.html" title="Галерея">Галерея</a>
      ${solo ? '' : `<a href="${next.file}" title="${next.title}">${no(next)} &rarr;</a>`}
    </div>`;
  return el;
}

function setDemoState() {
  document.documentElement.dataset.theme = demoState.mode;
  document.documentElement.dataset.demoMotion = demoState.motion;
  DEMO_SEASONS.forEach(([cls]) => document.body.classList.toggle(cls, cls === demoState.season));
  localStorage.setItem('demo.mode', demoState.mode);
  localStorage.setItem('demo.season', demoState.season);
  localStorage.setItem('demo.motion', demoState.motion);
  document.querySelectorAll('[data-demo-set]').forEach((b) => {
    b.setAttribute('aria-pressed', String(demoState[b.dataset.demoSet] === b.dataset.demoValue));
  });
  // The header switch shows the mode in force: a pick in the panel moves its knob in place, a press reloads the page with it
  document.querySelectorAll('[data-demo-mode] .sl-seg-cell').forEach((cell) => {
    cell.classList.toggle('sl-is-active', cell.value === demoState.mode);
    cell.setAttribute('aria-pressed', String(cell.value === demoState.mode));
  });
}

/* The note card every variant closes with, built from the manifest so the text lives at one address */
function getDemoNote(place) {
  if (!place || !place.item) return '';
  const no = String(place.idx + 1).padStart(2, '0');
  return `<div class="sl-wrp" style="padding-bottom: var(--sl-space-11)">
    <div class="demo-note">
      <h2>${no} &middot; ${place.item.title}</h2>
      <p>${place.item.note}</p>
    </div>
  </div>`;
}

/* Write the manifest card of the face into the box the engine of the window series leaves for it */
function setDemoAbout(place) {
  if (!place.item) return;
  const no = String(place.idx + 1).padStart(2, '0');
  const tags = (place.item.tags || []).map((t) => `<span class="sl-chip">${t}</span>`).join(' ');
  document.querySelectorAll('[data-demo-about]').forEach((node) => {
    node.innerHTML = `<h2 class="demo-about-title">${no} &middot; ${place.item.title}</h2>` +
      `<p class="demo-about-note">${place.item.note}</p><p class="demo-about-tags">${tags}</p>`;
  });
}

/* The knob: the travelling plate under the chosen cell of the colour mode rail in the header. The rail names itself in
   data-sl-knob, the plate carries data-sl-knob-mark, the chosen cell wears sl-is-active; the script measures that cell
   and writes --sl-d-knob-x and --sl-d-knob-w on the rail and the sheet moves the plate. It follows any change of the chosen cell, whoever makes it, and a page that is left
   hands the last place of every rail to the next one through sessionStorage, so after a reload or a page change the
   plate travels from where it stood instead of appearing at once */
const KNOB_KEY = 'demo-knob';

function setKnobPlace(rail) {
  const cell = [...rail.querySelectorAll('.sl-is-active')].find((node) => !node.closest('[data-sl-knob-mark]') && node.offsetParent);
  if (!cell) return;
  const box = rail.getBoundingClientRect();
  const rect = cell.getBoundingClientRect();
  rail.style.setProperty('--sl-d-knob-x', (rect.left - box.left - rail.clientLeft) + 'px');
  rail.style.setProperty('--sl-d-knob-w', rect.width + 'px');
}

function setDemoKnob() {
  const rails = document.querySelectorAll('[data-sl-knob]');
  if (!rails.length) return;
  let from = {};
  try {
    from = JSON.parse(sessionStorage.getItem(KNOB_KEY) || '{}');
    sessionStorage.removeItem(KNOB_KEY);
  } catch (err) { /* storage is a convenience */ }
  const fresh = from.path === location.pathname && Date.now() - (from.time || 0) < 5000;
  rails.forEach((rail) => {
    const was = fresh && from.rails ? from.rails[rail.dataset.slKnob] : null;
    rail.setAttribute('data-sl-knob-still', '');
    if (was) {
      rail.style.setProperty('--sl-d-knob-x', was.x);
      rail.style.setProperty('--sl-d-knob-w', was.w);
    } else {
      setKnobPlace(rail);
    }
    rail.setAttribute('data-sl-knob-ready', '');
    requestAnimationFrame(() => requestAnimationFrame(() => {
      rail.removeAttribute('data-sl-knob-still');
      setKnobPlace(rail);
    }));
    // A resize observer answers at once when it starts: while the plate stands on the place of the page before it is left there
    const follow = () => { if (!rail.hasAttribute('data-sl-knob-still')) setKnobPlace(rail); };
    new ResizeObserver(follow).observe(rail);
    new MutationObserver(follow).observe(rail, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'] });
  });
  addEventListener('pagehide', () => {
    const keep = {};
    rails.forEach((rail) => {
      const name = rail.dataset.slKnob;
      if (keep[name] || !rail.style.getPropertyValue('--sl-d-knob-w')) return;
      keep[name] = { x: rail.style.getPropertyValue('--sl-d-knob-x'), w: rail.style.getPropertyValue('--sl-d-knob-w') };
    });
    try { sessionStorage.setItem(KNOB_KEY, JSON.stringify({ path: location.pathname, time: Date.now(), rails: keep })); } catch (err) { /* storage is a convenience */ }
  });
}

/* Stand the face in its shell and start the panel; ?bare=1 leaves the face alone for a frame of the wall */
function initDemoPage() {
  const params = new URLSearchParams(location.search);
  const bare = params.has('bare');
  const file = location.pathname.split('/').pop() || '';
  const place = getDemoPlace(file);
  if (params.get('season')) demoState.season = params.get('season');
  if (params.get('mode')) demoState.mode = params.get('mode');

  const page = document.querySelector('[data-demo-page]');
  const band = page ? page.dataset.demoBand : undefined;
  if (page) {
    const box = (band === undefined) ? page : setCabinetShell(page);
    if (!bare) {
      box.insertAdjacentHTML('beforebegin', CHROME_TOP + (band === undefined ? '' : getChromeBand(band)));
      box.insertAdjacentHTML('afterend', getDemoNote(place) + CHROME_FOOT);
    }
  }
  if (!bare) document.body.appendChild(getDemoPanel(file));
  setDemoState();
  setDemoAbout(place);
  setDemoKnob();
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-demo-set]');
    if (btn) {
      demoState[btn.dataset.demoSet] = btn.dataset.demoValue;
      setDemoState();
      return;
    }
    const tgl = e.target.closest('.demo-panel-toggle');
    if (tgl) {
      const panel = tgl.closest('.demo-panel');
      const on = panel.dataset.collapsed === '1';
      panel.dataset.collapsed = on ? '0' : '1';
      tgl.innerHTML = on ? '&ndash;' : '+';
    }
  });
}

function bootDemo() {
  if (document.body.dataset.demoGallery === undefined) initDemoPage();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootDemo);
} else {
  bootDemo();
}
