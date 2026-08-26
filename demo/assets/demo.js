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
/* The second series of the stand: the settings department of the account, `index.php?name=account&op=edithome`.
   Same law as the first - every variant carries the same fields in the same words and differs only in how they
   are composed, so comparing two variants is comparing two designs. */
const DEMO_SETTINGS = [
  {
    file: 'set-01-deck.html',
    title: 'Палуба',
    note: 'Настройки как продолжение кабинета: слева прилипший рельс с аватаром, кольцом заполненности и оглавлением, справа все разделы одной лентой карточек. Ни одной вкладки — ничего не спрятано за кликом.',
    tags: ['кабинет', 'sticky', 'одна лента'],
  },
  {
    file: 'set-02-stack.html',
    title: 'Отсеки',
    note: 'Шесть свёрнутых отсеков, и каждый показывает сводку своих значений прямо в заголовке — видно, что внутри, не открывая. Открыт ровно один, вся страница помещается на экран.',
    tags: ['аккордеон', 'сводки', 'без JS'],
  },
  {
    file: 'set-03-rows.html',
    title: 'Строки',
    note: 'Настройки как список строк системного вида: подпись слева, объяснение под ней, контрол справа по месту. Плотно, спокойно, читается сверху вниз без единой рамки.',
    tags: ['список', 'плотно', 'системный вид'],
  },
  {
    file: 'set-04-bento.html',
    title: 'Бенто',
    note: 'Плиточная сетка: аватар и тема оформления берут крупные плитки, переключатели — мелкие. Каждая настройка живёт в своей плитке и занимает столько места, сколько заслуживает.',
    tags: ['плитки', 'сетка', 'тумблеры'],
  },
  {
    file: 'set-05-wizard.html',
    title: 'Мастер',
    note: 'Пять шагов на рельсе и кольцо заполненности профиля рядом. Одна панель за раз, вперёд и назад по рельсу — форма из тридцати полей превращается в пять коротких экранов.',
    tags: ['шаги', 'прогресс', 'по одному'],
  },
  {
    file: 'set-06-palette.html',
    title: 'Палитра',
    note: 'Поиск по настройкам вместо навигации: строка сверху фильтрует все поля вживую, как командная палитра редактора. Нужное находится за два символа, а не за три вкладки.',
    tags: ['поиск', 'фильтр', 'клавиатура'],
  },
  {
    file: 'set-07-mirror.html',
    title: 'Зеркало',
    note: 'Слева форма, справа прилипшая карточка профиля — ровно та, какой её видят другие. Меняешь поле, и карточка меняется в тот же миг: настройка показывает свой результат, а не обещает его.',
    tags: ['живое превью', 'две колонки', 'sticky'],
  },
  {
    file: 'set-08-console.html',
    title: 'Пульт',
    note: 'Настройки прикидываются приборной панелью: индикаторы безопасности и приватности сверху, разделы как виджеты, журнал последних изменений сбоку. Состояние аккаунта читается за секунду.',
    tags: ['дашборд', 'индикаторы', 'статусы'],
  },
  {
    file: 'set-09-paper.html',
    title: 'Разворот',
    note: 'Журнальная вёрстка настроек: узкая колонка, крупная типографика, тонкие линейки и номера разделов на полях. Ни одной тени и ни одного скругления — только ритм и воздух.',
    tags: ['типографика', 'линейки', 'спокойно'],
  },
  {
    file: 'set-10-bridge.html',
    title: 'Рубка',
    note: 'Сборка из лидеров: четыре лампы состояния из «Пульта» сверху, рельс разделов из «Мастера» под ними — но без мастера, ничего не спрятано, — плитки «Бенто» вместо колонки формы, журнал изменений «Пульта» рядом с паролем и панель сохранения «Палубы» снизу. Собрано под настоящую колонку кабинета в 896 точек, поэтому рельс лёг горизонтально и левый борт «Палубы» не понадобился. Единственный вариант с полным набором полей живой страницы, включая дополнительные поля, которые объявляет администратор.',
    tags: ['слияние', 'лампы', 'рельс', 'все поля'],
  },
];

/* The third series: adding a file. The mechanism is already built and it is good — the file manager window of
   the editor, `sl-toastui-upload` with its `sl-fm-*` parts. Its geometry tokens already live in the theme's own
   `base.css`; only the rules sit in `assets/editors/toastui/skin.css`, scoped under the window. So everywhere
   outside an editor — the avatar, the file of the catalogue — falls back to a bare `<input type="file">` with a
   class that has no CSS at all. These variants ask one question: in what shape does that mechanism come out of
   the window, so that one gesture serves the whole system. */
const DEMO_UPLOAD = [
  {
    file: 'up-01-drop.html',
    title: 'Полоса',
    note: 'Самый прямой перенос: зона `sl-fm-drop` выходит из окна прямо в строку формы. Пунктирное поле во всю ширину, облако, под ним строка лимитов — написанная из правила модуля, а не набранная рядом с ним. Пачка файлов, очередь по одному, отказ каждого своими словами. Аватар получает ту же полосу, только низкую и с превью слева.',
    tags: ['перетаскивание', 'готовое как есть', 'лимиты из правила'],
  },
  {
    file: 'up-02-window.html',
    title: 'Одна дверь',
    note: 'Поля нет вовсе: в строке стоит кнопка, открывающая тот самый файловый менеджер, а выбранное возвращается в форму чипом. Один механизм на всю систему — и хранилище, и ссылка, и загрузка ведут себя одинаково, где бы файл ни понадобился.',
    tags: ['окно', 'один механизм', 'хранилище'],
  },
  {
    file: 'up-03-switch.html',
    title: 'Две двери',
    note: 'Форма каталога сейчас держит «Загрузить файл» и «Ссылка» двумя строками подряд, и заполняют по ошибке обе. Сегмент «С компьютера / По ссылке» делает из них один выбор с одним ответом.',
    tags: ['сегмент', 'один ответ', 'исправляет форму'],
  },
  {
    file: 'up-04-queue.html',
    title: 'Очередь',
    note: 'Очередь менеджера целиком: зона принимает пачку, под ней карточки `sl-fm-job` — по одной на файл, со своей полосой и своей отменой. Файлы идут по одному, отказ одного не останавливает остальные и называет причину словами `getUploadFailText()`. Счётчик «Загрузка N из M», кнопка «остановить», полоса квоты движется на принятые байты.',
    tags: ['пачка', 'очередь', 'отказы', 'квота'],
  },
  {
    file: 'up-05-tile.html',
    title: 'Плитка',
    note: 'Зона загрузки встроена первой ячейкой сетки: у аватара — перед галереей пресетов темы, у каталога — перед уже загруженными файлами. Выбор своего и выбор готового становятся одним жестом в одном ряду. Пачка, очередь и отказы те же, что у остальных: сетка меняет композицию, а не механизм.',
    tags: ['сетка', 'галерея', 'пачка'],
  },
  {
    file: 'up-06-inline.html',
    title: 'Строка',
    note: 'Самый скромный: кнопка «Обзор», за ней имя, вес и крестик — ровно высота поля, ни точкой больше. Для мест, где дропзона в полтораста точек забирает больше внимания, чем сама настройка заслуживает.',
    tags: ['компактно', 'в строку', 'без зоны'],
  },
];

/* The stand carries three series now, so a file finds its own neighbours and its own gallery section rather
   than the first list that happens to be declared. */
const DEMO_SERIES = [
  { key: 'main', title: 'Презентационная страница', addr: 'index.php?name=main', items: DEMO_VARIANTS },
  { key: 'settings', title: 'Настройки аккаунта', addr: 'index.php?name=account&op=edithome', items: DEMO_SETTINGS },
  { key: 'upload', title: 'Добавление файла', addr: 'index.php?name=files&op=add', items: DEMO_UPLOAD },
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
      <h2 class="sl-title">Опрос</h2>
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
      <h2 class="sl-title">Пользователи</h2>
      <div class="sl-block-content">
        <div class="sl-session-lines">
          <div class="sl-session-line"><i class="bi bi-person-fill" aria-hidden="true"></i><span>Пользователей</span><b>1</b></div>
          <div class="sl-session-line"><i class="bi bi-people-fill" aria-hidden="true"></i><span>Гостей</span><b>0</b></div>
          <div class="sl-session-line sl-session-total"><span>Всего</span><b>1</b></div>
        </div>
      </div>
    </section>
  </aside>`;

/* Move the variant into `main#content` and stand the sidebar beside it. Returns the node the page chrome is
   inserted around, which from here on is the wrap and no longer the variant itself. */
function setCabinetShell(page) {
  const wrap = document.createElement('div');
  wrap.className = 'sl-wrp';
  wrap.innerHTML = '<div id="container"><main id="content"></main>' + CHROME_SIDE + '</div>';
  page.parentNode.insertBefore(wrap, page);
  wrap.querySelector('#content').appendChild(page);
  return wrap;
}

const demoState = {
  mode: localStorage.getItem('demo.mode') || document.documentElement.dataset.theme || 'auto',
  season: localStorage.getItem('demo.season') || 'sl-summer',
  motion: localStorage.getItem('demo.motion') || 'on',
};

function getDemoPanel(file) {
  const place = getDemoPlace(file);
  const list = place.series.items;
  const safe = place.idx < 0 ? 0 : place.idx;
  const prev = list[(safe - 1 + list.length) % list.length];
  const next = list[(safe + 1) % list.length];
  const no = (v) => String(list.indexOf(v) + 1).padStart(2, '0');
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
      <a href="${prev.file}" title="${prev.title}">&larr; ${no(prev)}</a>
      <a href="index.html" title="Все варианты">Все варианты</a>
      <a href="${next.file}" title="${next.title}">${no(next)} &rarr;</a>
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

/* --- The settings series -------------------------------------------------------------------------------------
   Six behaviours the settings variants share. They live here for the same reason the four above do: a variant is
   a composition and a stylesheet, never a script, so two variants cannot answer the same gesture differently. */

/* The preset gallery: the theme ships 128 avatars and a variant says how many of them it wants to show. Picking
   one writes it into every live preview on the page, so the choice lands where the profile is actually drawn. */
function setDemoPresets() {
  document.querySelectorAll('[data-demo-presets]').forEach((box) => {
    const many = Number(box.dataset.demoPresets) || 12;
    const skip = Number(box.dataset.demoPresetsSkip) || 0;
    const pick = Number(box.dataset.demoPresetsPick) || 1;
    let out = '';
    for (let i = 1; i <= many; i++) {
      const no = String(skip + i).padStart(3, '0');
      const alt = 'Аватар ' + no;
      out += '<button type="button" class="d-ava" data-demo-pick="' + no + '" aria-pressed="' + (i === pick)
        + '" title="' + alt + '"><img src="' + T + '/images/avatars/presets/' + no + '.svg" alt="' + alt
        + '" width="80" height="80" loading="lazy"></button>';
    }
    box.innerHTML = out;
  });

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-demo-pick]');
    if (!btn) return;
    e.preventDefault();
    const box = btn.closest('[data-demo-presets]');
    box.querySelectorAll('[data-demo-pick]').forEach((b) => b.setAttribute('aria-pressed', String(b === btn)));
    const src = btn.querySelector('img').getAttribute('src');
    document.querySelectorAll('[data-demo-face]').forEach((img) => img.setAttribute('src', src));
  });
}

/* Search over the settings themselves. A searchable row carries `data-demo-find` with the words it answers to,
   a group folds away when none of its rows survive, and the tally is written where the variant asked for it. */
function setDemoFilter() {
  const field = document.querySelector('[data-demo-filter]');
  if (!field) return;
  const rows = [...document.querySelectorAll('[data-demo-find]')];
  const groups = [...document.querySelectorAll('[data-demo-group]')];
  const tally = document.querySelector('[data-demo-tally]');
  const empty = document.querySelector('[data-demo-empty]');

  function apply() {
    const q = field.value.trim().toLowerCase();
    let live = 0;
    rows.forEach((row) => {
      const hay = (row.dataset.demoFind + ' ' + row.textContent).toLowerCase();
      const on = !q || hay.includes(q);
      row.toggleAttribute('hidden', !on);
      if (on) live++;
    });
    groups.forEach((g) => {
      g.toggleAttribute('hidden', ![...g.querySelectorAll('[data-demo-find]')].some((r) => !r.hasAttribute('hidden')));
    });
    if (tally) tally.textContent = String(live);
    if (empty) empty.toggleAttribute('hidden', live > 0);
  }

  field.addEventListener('input', apply);
  document.addEventListener('click', (e) => {
    const chip = e.target.closest('[data-demo-seek]');
    if (!chip) return;
    e.preventDefault();
    field.value = chip.dataset.demoSeek;
    field.focus();
    apply();
  });
  apply();
}

/* The mirror: a field writes what it holds into every node that asked for that key, so the preview is the form
   itself and not a second copy of it. A select hands over the label of its option, never the value behind it. */
function setDemoLive() {
  const srcs = [...document.querySelectorAll('[data-demo-live-src]')];
  if (!srcs.length) return;

  function write(node) {
    const key = node.dataset.demoLiveSrc;
    let val = (node.tagName === 'SELECT') ? node.options[node.selectedIndex].text : node.value.trim();
    /* A date field holds ISO and the profile shows the date the way the site writes it everywhere else */
    if (node.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(val)) val = val.split('-').reverse().join('.');
    document.querySelectorAll('[data-demo-live="' + key + '"]').forEach((out) => {
      out.textContent = val || (out.dataset.demoLiveEmpty || '');
      out.toggleAttribute('data-demo-off', !val);
    });
  }

  srcs.forEach((node) => {
    node.addEventListener('input', () => write(node));
    node.addEventListener('change', () => write(node));
    write(node);
  });
}

/* How full the profile is, counted over the fields the variant marked as counting. The figure goes out as a custom
   property as well as text, because the ring that draws it is an SVG stroke and reads a number, not a sentence. */
function setDemoMeter() {
  const meters = [...document.querySelectorAll('[data-demo-meter]')];
  if (!meters.length) return;
  const fields = [...document.querySelectorAll('[data-demo-fill]')];
  const rests = [...document.querySelectorAll('[data-demo-meter-left]')];

  function apply() {
    const full = fields.filter((f) => f.value.trim() !== '').length;
    const pct = fields.length ? Math.round((full / fields.length) * 100) : 0;
    meters.forEach((m) => {
      m.style.setProperty('--d-meter', String(pct));
      (m.querySelector('[data-demo-meter-num]') || m).textContent = pct + '%';
      /* A ring with nothing left to fill takes the theme's solid state, so a full circle shows no join */
      const knob = m.classList.contains('sl-knob') ? m : m.querySelector('.sl-knob');
      if (knob) knob.classList.toggle('sl-knob-full', pct >= 100);
    });
    rests.forEach((n) => { n.textContent = String(fields.length - full); });
  }

  fields.forEach((f) => {
    f.addEventListener('input', apply);
    f.addEventListener('change', apply);
  });
  apply();
}

/* Unsaved changes. The scope marks itself dirty on the first keystroke and the variant decides what that looks
   like — a bar rising from the bottom, a chip beside the title, a button waking up. */
function setDemoDirty() {
  document.querySelectorAll('[data-demo-dirty]').forEach((scope) => {
    const wake = () => { scope.dataset.dirty = '1'; };
    scope.addEventListener('input', wake);
    scope.addEventListener('change', wake);
    scope.addEventListener('click', (e) => {
      if (!e.target.closest('[data-demo-clean]')) return;
      e.preventDefault();
      scope.dataset.dirty = '0';
    });
  });
}

/* The file manager is a `<dialog>` in the system too, and what opens it lives in `plugins/system/slaed.js`,
   which a stand page does not load. These few lines stand in for that one gesture and nothing else. */
function setDemoWindow() {
  document.addEventListener('click', (e) => {
    const open = e.target.closest('[data-demo-open]');
    if (open) {
      e.preventDefault();
      const dlg = document.getElementById(open.dataset.demoOpen);
      if (dlg && typeof dlg.showModal === 'function') dlg.showModal();
      return;
    }
    const shut = e.target.closest('[data-demo-close]');
    if (shut) {
      e.preventDefault();
      const dlg = shut.closest('dialog');
      if (dlg) dlg.close();
    }
  });
}

/* One upload gesture, wherever it stands, and it is the one the file manager already runs.

   The window of the editor does four things this rig now does too, in the same order and with the same words:

   1. It knows the rule of the destination. `getUploadRuleData()` reads twelve fields out of one pipe string in
      `$conf['uploads'][<module>]` — extensions, quota, bytes per file, width, height, files at once, and the
      rest — and every screen that offers an upload is handed the same twelve. Here a zone carries them as data
      attributes and the line of limits under it is written *from* them, so the sentence and the check cannot
      drift apart.
   2. It refuses a batch before it starts. `addFileList()` compares the count against `maxfiles` and raises one
      warning for the whole handful, exactly as here.
   3. It runs the files one at a time. `addQueue()` builds one `sl-fm-job` card per file, `setQueueStep()` walks
      them, each card carries its own bar and its own cancel, and a refusal never stops the rest.
   4. It names the reason. `getUploadFailText()` maps a code to one sentence, and that mapping lives in one place
      precisely so the editor window and the administrative catalogue explain a refusal identically. The same
      sentences are quoted below — a stand that invented its own wording would be showing a mechanism nobody has.

   What the stand cannot do is send anything, so it does not pretend to: the bar measures a real read of the real
   file in the browser, which is work that actually happens and takes the time the file's size deserves. */

/* The refusals, quoted from `getUploadFailText()` in `core/system.php` */
const DEMO_FAIL = {
  extension: 'Недопустимый формат файла!',
  size: 'Загружаемый файл слишком большой!',
  dimensions: 'Размер загружаемого графического элемента превышает допустимый!',
  quota: 'Максимальный размер загруженных файлов модуля',
  count: 'Количество одновременно загружаемых файлов',
  exists: 'Файл с таким названием уже существует на сервере!',
};

/* Weights are written the way `filterSize()` writes them, so the stand does not spell a second dialect */
function getDemoSize(n) {
  const unit = ['Bytes', 'KB', 'MB', 'GB'];
  let num = Number(n) || 0;
  let at = 0;
  while (num >= 1024 && at < unit.length - 1) {
    num /= 1024;
    at++;
  }
  return (at === 0 ? num : num.toFixed(1)) + ' ' + unit[at];
}

function setDemoDrop() {
  const zones = [...document.querySelectorAll('[data-demo-drop]')];
  if (!zones.length) return;

  function getRule(zone) {
    const d = zone.dataset;
    return {
      ext: (d.demoExt || '').split(',').map((s) => s.trim().toLowerCase()).filter(Boolean),
      maxbytes: Number(d.demoMaxbytes) || 0,
      maxfiles: Number(d.demoMaxfiles) || 0,
      maxquota: Number(d.demoMaxquota) || 0,
      maxwidth: Number(d.demoMaxwidth) || 0,
      maxheight: Number(d.demoMaxheight) || 0,
      used: Number(d.demoUsed) || 0,
    };
  }

  /* The line under the zone is written from the rule and never typed by hand beside it */
  function setLimits(zone, scope, rule) {
    /* The line usually stands inside the zone; a variant that prints it beside the zone keeps it in the same scope */
    const out = zone.querySelector('[data-demo-limits]') || scope.querySelector('[data-demo-limits]');
    if (!out) return;
    const part = [];
    if (rule.ext.length) part.push(rule.ext.join(', '));
    if (rule.maxbytes) part.push('не более ' + getDemoSize(rule.maxbytes));
    if (rule.maxwidth && rule.maxheight) part.push(rule.maxwidth + ' × ' + rule.maxheight + ' точек');
    if (rule.maxfiles > 1) part.push('до ' + rule.maxfiles + ' файлов за раз');
    out.textContent = part.join(' · ');
  }

  /* The quota bar of the window, moved by what the queue has actually accepted */
  function setQuota(scope, rule, used) {
    scope.querySelectorAll('[data-demo-quota]').forEach((box) => {
      const fill = box.querySelector('.sl-fm-quota-fill');
      const num = box.querySelector('[data-demo-quota-num]');
      const part = rule.maxquota ? Math.min(100, (used / rule.maxquota) * 100) : 0;
      if (fill) fill.style.width = part.toFixed(1) + '%';
      if (num) num.textContent = getDemoSize(used) + ' из ' + getDemoSize(rule.maxquota);
    });
  }

  /* One warning for the whole handful, in the alert the theme already paints */
  function setMsg(scope, text) {
    const box = scope.querySelector('[data-demo-msg]');
    if (!box) return;
    box.innerHTML = text
      ? '<div class="sl-alert sl-alert-warn"><div class="sl-alert-body"><p class="sl-alert-text">' + text + '</p></div></div>'
      : '';
  }

  /* The card of `templates/lite/partials/editor-toastui-templates.html`, verbatim */
  function getJob(file) {
    const box = document.createElement('div');
    box.className = 'sl-fm-job';
    box.innerHTML = '<i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>'
      + '<div><span class="sl-fm-job-name"><span></span><small></small></span>'
      + '<div class="sl-progress-line sl-progress-2"><div></div></div></div>'
      + '<button type="button" class="sl-but-mini sl-is-muted" data-demo-jobstop title="Отменить" aria-label="Отменить"><i class="bi bi-x-lg" aria-hidden="true"></i></button>';
    box.querySelector('.sl-fm-job-name > span').textContent = file.name || '';
    box.querySelector('.sl-fm-job-name > small').textContent = getDemoSize(file.size || 0);
    return box;
  }

  function setStep(box, at) {
    const line = box.querySelector('.sl-progress-line div');
    if (!line) return;
    line.style.width = at + '%';
    line.textContent = at + '%';
  }

  function setDone(box, why) {
    const stop = box.querySelector('[data-demo-jobstop]');
    const line = box.querySelector('.sl-progress-line');
    if (stop) stop.remove();
    box.classList.add(why ? 'sl-is-fail' : 'sl-is-done');
    box.querySelector('.bi').className = 'bi bi-' + (why ? 'exclamation-octagon-fill' : 'check-circle-fill');
    if (why) {
      if (line) {
        const note = document.createElement('p');
        note.className = 'sl-fm-job-why';
        note.textContent = why;
        line.replaceWith(note);
      }
      return;
    }
    const pick = document.createElement('label');
    pick.className = 'sl-fm-pick';
    pick.innerHTML = '<input type="checkbox" checked>';
    box.appendChild(pick);
  }

  /* The checks the server would run, in the order it runs them, so a file refused here is refused there */
  function getWhy(file, rule, used) {
    const name = String(file.name || '').toLowerCase();
    const ext = name.indexOf('.') > 0 ? name.split('.').pop() : '';
    if (rule.ext.length && rule.ext.indexOf(ext) < 0) return DEMO_FAIL.extension;
    if (rule.maxbytes && file.size > rule.maxbytes) return DEMO_FAIL.size;
    if (rule.maxquota && used + file.size > rule.maxquota) return DEMO_FAIL.quota + ': ' + getDemoSize(rule.maxquota);
    return '';
  }

  /* An image is measured before it is accepted, because `dimensions` is a refusal of its own */
  function checkSize(file, rule, done) {
    if (!rule.maxwidth || !rule.maxheight || !String(file.type || '').startsWith('image/')) return done('');
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      const bad = img.naturalWidth > rule.maxwidth || img.naturalHeight > rule.maxheight;
      URL.revokeObjectURL(url);
      done(bad ? DEMO_FAIL.dimensions : '');
    };
    img.onerror = () => { URL.revokeObjectURL(url); done(''); };
    img.src = url;
  }

  /* The bar of one card. Nothing leaves the browser: what is measured is a real read of the real file, which is
     the same shape the transfer will have and takes the time the size of the file deserves. */
  function runJob(file, box, done) {
    const rd = new FileReader();
    rd.onprogress = (ev) => {
      if (ev.lengthComputable) setStep(box, Math.round((ev.loaded / ev.total) * 100));
    };
    rd.onload = () => { setStep(box, 100); done(); };
    rd.onerror = () => done();
    rd.onabort = () => done(true);
    rd.readAsArrayBuffer(file);
    return rd;
  }

  zones.forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    const key = zone.dataset.demoDrop;
    const out = document.querySelector('[data-demo-picked="' + key + '"]');
    const scope = zone.closest('.sl-toastui-upload') || document;
    const rule = getRule(zone);
    if (!input) return;
    let used = rule.used;
    let job = null;
    let dead = false;

    setLimits(zone, scope, rule);
    setQuota(scope, rule, used);

    function setCap(text) {
      scope.querySelectorAll('[data-demo-queue-cap]').forEach((n) => { n.textContent = text; });
    }

    function walk(list, at, ok) {
      /* `deleteQueue()` in the window drops the whole run, not the file in hand; the rest are marked and it ends */
      if (dead) {
        for (let i = at; i < list.length; i++) {
          const rest = out.querySelector('[data-demo-job="' + i + '"]');
          if (rest && !rest.classList.contains('sl-is-done') && !rest.classList.contains('sl-is-fail')) setDone(rest, 'Отменено.');
        }
        setCap('Остановлено: ' + ok + ' из ' + list.length);
        scope.querySelectorAll('[data-demo-stopall]').forEach((n) => { n.hidden = true; });
        job = null;
        return;
      }
      if (at >= list.length) {
        setCap('Готово: ' + ok + ' из ' + list.length);
        scope.querySelectorAll('[data-demo-stopall]').forEach((n) => { n.hidden = true; });
        job = null;
        return;
      }
      const file = list[at];
      const box = out.querySelector('[data-demo-job="' + at + '"]');
      /* A card cancelled before its turn is simply not there any more; the queue steps over it and goes on */
      if (!box) {
        walk(list, at + 1, ok);
        return;
      }
      setCap('Загрузка ' + (at + 1) + ' из ' + list.length);
      const why = getWhy(file, rule, used);
      if (why) {
        setDone(box, why);
        walk(list, at + 1, ok);
        return;
      }
      checkSize(file, rule, (bad) => {
        if (bad) {
          setDone(box, bad);
          walk(list, at + 1, ok);
          return;
        }
        job = runJob(file, box, (stopped) => {
          if (stopped) {
            setDone(box, 'Отменено.');
          } else {
            setDone(box, '');
            used += file.size;
            setQuota(scope, rule, used);
            if (zone.dataset.demoDropFace !== undefined && String(file.type || '').startsWith('image/')) {
              const src = URL.createObjectURL(file);
              document.querySelectorAll('[data-demo-face]').forEach((n) => n.setAttribute('src', src));
            }
            ok++;
          }
          walk(list, at + 1, ok);
        });
      });
    }

    function start(files) {
      const list = [...files];
      if (!out || !list.length) return;
      setMsg(scope, '');
      dead = false;
      /* The whole handful is refused before it starts, the way `addFileList()` refuses it */
      if (rule.maxfiles && list.length > rule.maxfiles) {
        setMsg(scope, DEMO_FAIL.count + ': ' + rule.maxfiles);
        input.value = '';
        return;
      }
      const none = out.querySelector('.d-pick-none');
      out.querySelectorAll('.sl-fm-job').forEach((n) => n.remove());
      if (none) none.setAttribute('hidden', '');
      list.forEach((f, i) => {
        const box = getJob(f);
        box.setAttribute('data-demo-job', String(i));
        out.appendChild(box);
      });
      scope.querySelectorAll('[data-demo-queue]').forEach((n) => { n.hidden = false; });
      scope.querySelectorAll('[data-demo-stopall]').forEach((n) => { n.hidden = false; });
      walk(list, 0, 0);
    }

    input.addEventListener('change', () => start(input.files));
    zone.addEventListener('click', (e) => {
      if (e.target === input || e.target.closest('button, a, label')) return;
      input.click();
    });
    zone.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); }
    });
    ['dragenter', 'dragover'].forEach((ev) => zone.addEventListener(ev, (e) => {
      e.preventDefault();
      zone.classList.add('sl-drag-over');
    }));
    ['dragleave', 'drop'].forEach((ev) => zone.addEventListener(ev, (e) => {
      e.preventDefault();
      zone.classList.remove('sl-drag-over');
    }));
    zone.addEventListener('drop', (e) => {
      if (e.dataTransfer && e.dataTransfer.files.length) {
        input.files = e.dataTransfer.files;
        start(input.files);
      }
    });

    scope.addEventListener('click', (e) => {
      if (!e.target.closest('[data-demo-stopall]')) return;
      e.preventDefault();
      dead = true;
      if (job) job.abort();
    });
  });

  /* One card cancelled leaves the queue; the rest keep going, which is the law of the window too */
  document.addEventListener('click', (e) => {
    const kill = e.target.closest('[data-demo-jobstop]');
    if (!kill) return;
    e.preventDefault();
    const box = kill.closest('[data-demo-picked]');
    kill.closest('.sl-fm-job').remove();
    if (box && !box.querySelector('.sl-fm-job')) {
      const none = box.querySelector('.d-pick-none');
      if (none) none.removeAttribute('hidden');
    }
  });
}

/* The same rail without the wizard behind it: nothing is hidden, the marks only say which section the reader is
   standing in and how much of the road is behind. The section in view is found by observer rather than by a
   scroll listener, so the rail costs nothing while the page is still. */
function setDemoSpy() {
  document.querySelectorAll('[data-demo-spy]').forEach((rail) => {
    const marks = [...rail.querySelectorAll('[data-demo-spy-mark]')];
    const secs = marks.map((m) => document.getElementById(m.dataset.demoSpyMark));
    if (secs.some((s) => !s)) return;
    let at = 0;

    function draw() {
      marks.forEach((m, i) => {
        m.setAttribute('aria-current', String(i === at));
        m.dataset.done = String(i < at);
      });
      rail.style.setProperty('--d-step', String(at + 1));
    }

    /* The band is the upper fifth of the window: a section counts as the one being read when its top has passed
       the fold but its body still fills the screen, which is where the eye actually is */
    const io = new IntersectionObserver((rows) => {
      rows.forEach((row) => {
        if (!row.isIntersecting) return;
        at = secs.indexOf(row.target);
        draw();
      });
    }, { rootMargin: '-20% 0px -70% 0px' });
    secs.forEach((s) => io.observe(s));

    rail.addEventListener('click', (e) => {
      const mark = e.target.closest('[data-demo-spy-mark]');
      if (!mark) return;
      e.preventDefault();
      document.getElementById(mark.dataset.demoSpyMark).scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
    draw();
  });
}

/* The wizard rail: one panel at a time, the rail marking where the reader stands and how far the road still goes */
function setDemoSteps() {
  document.querySelectorAll('[data-demo-steps]').forEach((box) => {
    const panels = [...box.querySelectorAll('[data-demo-step]')];
    const rails = [...box.querySelectorAll('[data-demo-rail]')];
    let at = 0;

    function draw() {
      at = Math.max(0, Math.min(panels.length - 1, at));
      panels.forEach((p, i) => p.toggleAttribute('hidden', i !== at));
      rails.forEach((r, i) => {
        r.setAttribute('aria-current', String(i === at));
        r.dataset.done = String(i < at);
      });
      box.style.setProperty('--d-step', String(at + 1));
      box.querySelectorAll('[data-demo-go="prev"]').forEach((b) => { b.disabled = at === 0; });
      box.querySelectorAll('[data-demo-go="next"]').forEach((b) => { b.disabled = at === panels.length - 1; });
      box.querySelectorAll('[data-demo-step-num]').forEach((n) => { n.textContent = String(at + 1); });
    }

    box.addEventListener('click', (e) => {
      const go = e.target.closest('[data-demo-go]');
      if (!go) return;
      e.preventDefault();
      const dir = go.dataset.demoGo;
      at = (dir === 'next') ? at + 1 : (dir === 'prev') ? at - 1 : Number(dir);
      draw();
      /* A short step after a long one leaves the reader below the whole panel: bring the rail back only when it
         has actually scrolled off the top, so a step change from the top of the page never jumps */
      if (box.getBoundingClientRect().top < 0) box.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
    draw();
  });
}

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
  setDemoSites();
  setDemoMarquee();
  setDemoPointer();
  setDemoTicker();
  setDemoTabs();
  setDemoPresets();
  setDemoFilter();
  setDemoLive();
  setDemoMeter();
  setDemoDirty();
  setDemoSteps();
  setDemoSpy();
  setDemoDrop();
  setDemoWindow();

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
