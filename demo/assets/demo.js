/* Demo rig for the presentation page. One manifest, one page chrome, one control rail, and the four behaviours
   the variants share. Every variant file carries the same content in the same order and differs only in how that
   content is composed and painted, so comparing two variants is comparing two designs and nothing else. */

const DEMO_VARIANTS = [
  {
    file: '01-bento.html',
    title: 'Бенто',
    note: 'Асимметричная сетка плиток разного размера: каждое преимущество получает свой размер по важности, а внутри плитки живёт собственная микро-иллюстрация — мини-терминал, орбита модулей, полоса нагрузки.',
    tags: ['сетка', 'плитки', 'магнит курсора'],
  },
  {
    file: '02-terminal.html',
    title: 'Терминал',
    note: 'Страница для разработчика: моноширинный тон, установка печатается в живом терминале, преимущества читаются как вывод команды, разделы подписаны как секции конфига.',
    tags: ['для разработчика', 'печать', 'тёмный тон'],
  },
  {
    file: '03-orbit.html',
    title: 'Орбита',
    note: 'Радиальная композиция: ядро системы в центре, модули идут по трём орбитам вокруг него. Дальше страница разворачивается кольцами, а не колонками.',
    tags: ['радиальная', 'модули', 'анимация'],
  },
  {
    file: '04-marquee.html',
    title: 'Ленты',
    note: 'Движение как главный приём: три ряда внедрений едут навстречу друг другу с разной скоростью, технологии идут отдельной лентой, а по краям стоят мягкие маски.',
    tags: ['движение', 'витрина', 'без JS'],
  },
  {
    file: '05-metrics.html',
    title: 'Цифры',
    note: 'Страница-доказательство: крупные счётчики набегают при появлении в кадре, под ними столбики сравнения нагрузки и таблица фактов вместо обещаний.',
    tags: ['счётчики', 'графики', 'доказательства'],
  },
  {
    file: '06-beam.html',
    title: 'Схема',
    note: 'Главный экран — живая схема запроса: браузер, кэш, ядро, база. По связям бегут импульсы, каждый узел подписан своей цифрой. Ниже — те же четыре опоры как слои схемы.',
    tags: ['схема', 'импульсы', 'инженерно'],
  },
  {
    file: '07-spotlight.html',
    title: 'Магические карты',
    note: 'Тёмный премиальный тон: за курсором по карточке идёт пятно света, у главной кнопки по кромке бежит луч, заголовок собран из градиентного текста.',
    tags: ['указатель', 'свечение', 'премиум'],
  },
  {
    file: '08-timeline.html',
    title: 'Хронология',
    note: 'История проекта с 2005 года как вертикальная лента версий: линия прорисовывается по мере прокрутки, у каждой вехи своя карточка, четыре опоры встроены в ленту как поворотные точки.',
    tags: ['история', 'прокрутка', 'повествование'],
  },
  {
    file: '09-sticky.html',
    title: 'Разворот',
    note: 'Левая колонка прилипает и держит заголовок и кнопку, правая прокручивает четыре опоры с крупными иллюстрациями. Читается как разворот книги, а не как список.',
    tags: ['sticky', 'две колонки', 'спокойно'],
  },
  {
    file: '10-tabs.html',
    title: 'Витрина',
    note: 'Продуктовая витрина: вкладки переключают большую панель со скриншотом, слева — список того, что внутри. Одна крупная картинка вместо четырёх мелких.',
    tags: ['вкладки', 'скриншот', 'продукт'],
  },
  {
    file: '11-grid.html',
    title: 'Ретро-сетка',
    note: 'Уходящая в перспективу сетка под первым экраном, точечный узор в разделах, неоновые акценты и рамки-хайлайты. Техничный тон без единой фотографии.',
    tags: ['без фото', 'неон', 'перспектива'],
  },
  {
    file: '12-kinetic.html',
    title: 'Кинетика',
    note: 'Типографика делает всю работу: огромный заголовок, слова проявляются по одному при прокрутке, четыре опоры набраны как манифест. Минимум элементов, максимум воздуха.',
    tags: ['типографика', 'прокрутка', 'минимализм'],
  },
  {
    file: '13-glass.html',
    title: 'Стекло',
    note: 'Сезонная полоса ровно своей высоты — 440 точек и ни пикселем больше, — а стеклянная панель наезжает на её нижний край и выходит на страницу. Сезон панель читает через backdrop-filter, а не вторым набором цветов.',
    tags: ['стекло', 'сезоны', 'фото без растяжения'],
  },
  {
    file: '14-editorial.html',
    title: 'Журнал',
    note: 'Журнальная вёрстка: узкая колонка ведущего текста, буквица, тонкие линейки, номера разделов на полях. Новости и файлы поданы как полоса издания.',
    tags: ['редакторская', 'линейки', 'без анимации'],
  },
  {
    file: '15-meteors.html',
    title: 'Метеоры',
    note: 'Тёмное небо первого экрана с метеорным дождём, карточки в неоновых градиентных рамках, кнопка с мерцающей кромкой. Самый громкий вариант стенда.',
    tags: ['тёмный', 'анимация', 'громко'],
  },
  {
    file: '16-flow.html',
    title: 'Поток',
    note: 'Путь пользователя как пронумерованные шаги: скачал, поставил, настроил, работает. Между шагами тянется рельс, который заполняется по мере прокрутки.',
    tags: ['шаги', 'рельс', 'сценарий'],
  },
  {
    file: '17-mosaic.html',
    title: 'Мозаика',
    note: 'Первый экран отдан внедрениям: стена сайтов во всю ширину, заголовок лежит поверх неё, под курсором плитка выходит вперёд и показывает подпись.',
    tags: ['витрина', 'во всю ширину', 'указатель'],
  },
  {
    file: '18-console.html',
    title: 'Пульт',
    note: 'Страница прикидывается панелью управления: виджеты, круговые индикаторы, живые строки журнала и таблица модулей. Продукт показывает себя собой.',
    tags: ['дашборд', 'виджеты', 'самопрезентация'],
  },
  {
    file: '19-liquid.html',
    title: 'Жидкое стекло',
    note: 'Развитие 13-го туда, куда в 2026-м ушла сама платформа: вместо одной матовой пластины — прогрессивное размытие в три слоя, и фотография не обрывается краем, а растворяется в странице. Управление плавает капсулами.',
    tags: ['стекло', 'прогрессивный блюр', 'капсулы'],
  },
  {
    file: '20-product.html',
    title: 'Витрина клиента',
    note: 'То же стекло, но на нём стоит продукт, а не обещание: на первом экране настоящий сайт клиента в рамке браузера, а рельс рядом переключает четыре из них. Единственный довод, который CMS не может подделать.',
    tags: ['стекло', 'реальный сайт', 'доказательство'],
  },
  {
    file: '21-layers.html',
    title: 'Слои',
    note: 'Стекло с глубиной: сцена прилипает, а четыре опоры приходят одна за другой пластинами в перспективе — ближняя резкая, дальние размыты. Ни одного слушателя прокрутки, всё на scroll-driven анимациях.',
    tags: ['стекло', 'глубина', 'прокрутка'],
  },
  {
    file: '22-anti.html',
    title: 'Антисетка',
    note: 'Контрход бенто: ни тени, ни блюра, ни скругления, ни градиента. Жёсткая линейка, моноширинная подпись и блок, который намеренно не совпадает с соседним. Голос, который может себе позволить открытый проект с двадцатью годами за спиной.',
    tags: ['брутализм', 'без ассетов', 'характер'],
  },
  {
    file: '23-wall.html',
    title: 'Стена работ',
    note: 'Не страница продукта, а страница портфолио: двенадцать клиентских сайтов выложены масонри-стеной, и каждый снимок сохраняет свою настоящую высоту — короткая главная остаётся короткой, длинная длинной.',
    tags: ['масонри', 'портфолио', 'без обрезки'],
  },
];

/* The implementations, taken from the live catalogue of the site: real screenshots of real client sites, with
   the names and ratings they carry there. They live at one address so every variant shows the same twelve, and
   so no variant can quietly substitute a stock banner for the proof this page exists to give.

   Every thumbnail is 260 wide and between 166 and 455 tall - a screenshot of a page, not a cropped photograph.
   That is the natural size and nothing may draw them larger than it. */
const DEMO_SITES = [
  { file: 'hostmind.ru.png', name: 'Hostmind', rate: 3361, height: 195 },
  { file: 'mylove-you.ru.png', name: 'Mylove-you', rate: 2459, height: 195 },
  { file: 'molodrk.ru.jpg', name: 'Molodrk', rate: 1304, height: 455 },
  { file: 'feldsher.ru.gif', name: 'Feldsher', rate: 1013, height: 166 },
  { file: 'hayfilm.info.gif', name: 'Hayfilm', rate: 1010, height: 166 },
  { file: 'start-drive.com.ua.gif', name: 'Start-drive', rate: 1002, height: 166 },
  { file: 'livetver.ru.gif', name: 'Livetver', rate: 995, height: 166 },
  { file: 'stepashka.com.gif', name: 'Stepashka', rate: 956, height: 166 },
  { file: 'most-konsalt.ru.gif', name: 'Most-konsalt', rate: 935, height: 166 },
  { file: 'mayolica.ru.gif', name: 'Mayolica', rate: 933, height: 166 },
  { file: 'mediacms.net.jpg', name: 'Mediacms', rate: 905, height: 330 },
  { file: 'keyelement.ru.gif', name: 'Keyelement', rate: 842, height: 166 },
];

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

const T = '../templates/lite';

const CHROME_TOP = `
  <div id="topbar">
    <div class="sl-wrp">
      <ul class="sl-top-contact"><li class="sl-head-marquee"><a href="#" title="Как и где можно установить свою тему оформления?"><i class="bi bi-stars" aria-hidden="true"></i>&nbsp;Как и где можно установить свою тему оформления?</a></li></ul>
      <div class="sl-top-right">
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
          <a href="#" title="PHP"><img src="${T}/images/tmp/php.png" alt="PHP" width="74" height="74"></a>
          <a href="#" title="MySQL"><img src="${T}/images/tmp/mysql.png" alt="MySQL" width="74" height="74"></a>
          <a href="#" title="HTML 5"><img src="${T}/images/tmp/html5.png" alt="HTML 5" width="74" height="74"></a>
          <a href="#" title="CSS 3"><img src="${T}/images/tmp/css3.png" alt="CSS 3" width="74" height="74"></a>
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

/* A variant designed for one mode opens in it, and a mode the visitor picked afterwards outranks that default:
   the second half of the comparison is whether the design still holds when the page turns over */
const demoState = {
  mode: localStorage.getItem('demo.mode') || document.documentElement.dataset.theme || 'auto',
  season: localStorage.getItem('demo.season') || 'sl-summer',
  motion: localStorage.getItem('demo.motion') || 'on',
};

function getDemoPanel(file) {
  const idx = DEMO_VARIANTS.findIndex((v) => v.file === file);
  const safe = idx < 0 ? 0 : idx;
  const prev = DEMO_VARIANTS[(safe - 1 + DEMO_VARIANTS.length) % DEMO_VARIANTS.length];
  const next = DEMO_VARIANTS[(safe + 1) % DEMO_VARIANTS.length];
  const seg = (name, items, active) => items.map(([val, label]) =>
    `<button type="button" data-demo-set="${name}" data-demo-value="${val}" aria-pressed="${val === active}">${label}</button>`).join('');
  const el = document.createElement('div');
  el.className = 'demo-panel';
  el.innerHTML = `
    <div class="demo-panel-head">
      <span class="demo-panel-title">${idx >= 0 ? String(idx + 1).padStart(2, '0') + ' &middot; ' + DEMO_VARIANTS[idx].title : 'Стенд'}</span>
      <button type="button" class="demo-panel-toggle" title="Свернуть">&ndash;</button>
    </div>
    <div class="demo-panel-row"><span>Тема</span><div class="demo-seg">${seg('mode', DEMO_MODES, demoState.mode)}</div></div>
    <div class="demo-panel-row"><span>Сезон</span><div class="demo-seg">${seg('season', DEMO_SEASONS, demoState.season)}</div></div>
    <div class="demo-panel-row"><span>Движение</span><div class="demo-seg">${seg('motion', [['on', 'Вкл'], ['off', 'Выкл']], demoState.motion)}</div></div>
    <div class="demo-panel-nav">
      <a href="${prev.file}" title="${prev.title}">&larr; ${prev.file.slice(0, 2)}</a>
      <a href="index.html" title="Все варианты">Все варианты</a>
      <a href="${next.file}" title="${next.title}">${next.file.slice(0, 2)} &rarr;</a>
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
}

/* The note card every variant closes with, built from the manifest so the text lives at one address */
function getDemoNote(note) {
  if (!note) return '';
  return `<div class="sl-wrp" style="padding-bottom: var(--sl-space-11)">
    <div class="demo-note">
      <h2>${String(DEMO_VARIANTS.indexOf(note) + 1).padStart(2, '0')} &middot; ${note.title}</h2>
      <p>${note.note}</p>
    </div>
  </div>`;
}

/* Pointer tracking for the variants that answer the hand: the position is eased on a rAF loop rather than
   written on every pointermove, because a raw write lands two or three times per frame and reads as a stutter */
function setDemoPointer() {
  const nodes = document.querySelectorAll('[data-demo-spot]');
  if (!nodes.length) return;
  const state = new Map();
  let pointer = null;
  let running = false;

  function frame() {
    let moving = false;
    nodes.forEach((node) => {
      const box = node.getBoundingClientRect();
      const cur = state.get(node) || { x: box.width / 2, y: box.height / 2 };
      const tx = pointer ? pointer.x - box.left : box.width / 2;
      const ty = pointer ? pointer.y - box.top : box.height / 2;
      cur.x += (tx - cur.x) * 0.18;
      cur.y += (ty - cur.y) * 0.18;
      state.set(node, cur);
      node.style.setProperty('--dx', cur.x.toFixed(1) + 'px');
      node.style.setProperty('--dy', cur.y.toFixed(1) + 'px');
      node.style.setProperty('--dnx', ((cur.x / box.width) * 2 - 1).toFixed(3));
      node.style.setProperty('--dny', ((cur.y / box.height) * 2 - 1).toFixed(3));
      if (Math.abs(tx - cur.x) > 0.4 || Math.abs(ty - cur.y) > 0.4) moving = true;
    });
    running = moving;
    if (running) requestAnimationFrame(frame);
  }

  function wake() {
    if (running) return;
    running = true;
    requestAnimationFrame(frame);
  }

  document.addEventListener('pointermove', (e) => {
    pointer = { x: e.clientX, y: e.clientY };
    wake();
  });
  document.addEventListener('pointerleave', () => {
    pointer = null;
    wake();
  });
}

/* Counters that run up once, when the figure first enters the viewport */
function setDemoTicker() {
  const nodes = document.querySelectorAll('[data-demo-num]');
  if (!nodes.length) return;
  const io = new IntersectionObserver((rows) => {
    rows.forEach((row) => {
      if (!row.isIntersecting) return;
      const node = row.target;
      io.unobserve(node);
      const goal = Number(node.dataset.demoNum);
      const digits = (node.dataset.demoNum.split('.')[1] || '').length;
      const start = performance.now();
      const step = (now) => {
        const part = Math.min(1, (now - start) / 1400);
        const ease = 1 - Math.pow(1 - part, 3);
        node.textContent = (goal * ease).toFixed(digits);
        if (part < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    });
  }, { threshold: 0.4 });
  nodes.forEach((node) => io.observe(node));
}

/* Tabs: one panel visible at a time, the strip driven by aria-selected so the theme paints it */
function setDemoTabs() {
  document.querySelectorAll('[data-demo-tabs]').forEach((box) => {
    box.addEventListener('click', (e) => {
      const tab = e.target.closest('[data-demo-tab]');
      if (!tab) return;
      e.preventDefault();
      const key = tab.dataset.demoTab;
      box.querySelectorAll('[data-demo-tab]').forEach((b) => b.setAttribute('aria-selected', String(b === tab)));
      box.querySelectorAll('[data-demo-panel]').forEach((p) => p.toggleAttribute('hidden', p.dataset.demoPanel !== key));
    });
  });
}

/* The implementation cards, written from the one list above into every box that asks for them. A variant says
   how many it wants and paints them with its own CSS; it never spells the list out, so the twelve cannot drift
   apart between eighteen files. `natural` asks for the screenshot at its own height instead of a cropped row. */
function setDemoSites() {
  document.querySelectorAll('[data-demo-sites]').forEach((box) => {
    const skip = Number(box.dataset.demoSitesSkip) || 0;
    const many = Number(box.dataset.demoSites) || DEMO_SITES.length;
    const own = box.dataset.demoSitesNatural !== undefined;
    box.innerHTML = DEMO_SITES.slice(skip, skip + many).map((site) => {
      const alt = 'Сайт: ' + site.name;
      return '<a class="d-site" href="../uploads/screens/' + site.file + '" title="' + alt + ', Рейтинг: ' + site.rate + '">'
        + '<span class="d-site-shot' + (own ? ' d-site-shot-own' : '') + '">'
        + '<img src="../uploads/screens/thumb/' + site.file + '" alt="' + alt + '" width="260" height="' + site.height + '" loading="lazy">'
        + '</span>'
        + '<span class="d-site-cap"><b>' + alt + '</b>'
        + '<span class="d-site-rate"><i class="bi bi-hand-thumbs-up" aria-hidden="true"></i>' + site.rate + '</span></span></a>';
    }).join('');
  });
}

/* A marquee needs its row twice to loop without a seam, and the copy is written here so the markup stays honest */
function setDemoMarquee() {
  document.querySelectorAll('[data-demo-marquee]').forEach((row) => {
    row.append(...[...row.children].map((node) => {
      const copy = node.cloneNode(true);
      copy.setAttribute('aria-hidden', 'true');
      return copy;
    }));
  });
}

function initDemoPage() {
  const params = new URLSearchParams(location.search);
  const bare = params.has('bare');
  const file = location.pathname.split('/').pop() || '';
  const note = DEMO_VARIANTS.find((v) => v.file === file);
  if (params.get('season')) demoState.season = params.get('season');
  if (params.get('mode')) demoState.mode = params.get('mode');

  const page = document.querySelector('[data-demo-page]');
  if (page && !bare) {
    page.insertAdjacentHTML('beforebegin', CHROME_TOP);
    page.insertAdjacentHTML('afterend', getDemoNote(note) + CHROME_FOOT);
  }
  if (!bare) document.body.appendChild(getDemoPanel(file));
  setDemoState();
  setDemoSites();
  setDemoMarquee();
  setDemoPointer();
  setDemoTicker();
  setDemoTabs();

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
