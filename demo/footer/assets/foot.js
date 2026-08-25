/* Footer rig: the lower menu of the site, eight treatments of one block. The markup is the live one, lifted from
   templates/lite/partials/site-footer.html, and the CSS underneath is the real theme - so a variant is one <style>
   block and nothing else. A page renders the variant inside an <iframe> of a chosen width, because the theme reacts
   to the viewport and a narrowed <div> would keep serving it the desktop rules. */

const FOOT_VARIANTS = [
  {
    file: '01-two-columns.html',
    title: 'Две колонки',
    note: 'Восемь пунктов ложатся в две ровные колонки, и разделители не нужны вовсе. Строка занимает половину ширины, промахнуться пальцем некуда.',
    tags: ['сетка', 'крупный тап', 'без разделителей'],
  },
  {
    file: '02-rows.html',
    title: 'Список строками',
    note: 'Каждый пункт — своя строка с тонкой линией под ней, как список настроек в приложении. Самый привычный жест на телефоне: палец идёт сверху вниз.',
    tags: ['столбец', 'линии', 'привычно'],
  },
  {
    file: '03-chips.html',
    title: 'Чипы',
    note: 'Пункты — таблетки с обводкой, и перенос между ними честный: у чипа есть своя граница, висящему разделителю взяться неоткуда. Реклама — заполненный чип.',
    tags: ['pill', 'акцент', 'перенос'],
  },
  {
    file: '04-scroller.html',
    title: 'Лента со скроллом',
    note: 'Одна строка, которая уезжает вбок с прилипанием, и подвал перестаёт расти в высоту. Реклама сидит под лентой отдельной строкой.',
    tags: ['одна строка', 'scroll-snap', 'низкий'],
  },
  {
    file: '05-icon-grid.html',
    title: 'Сетка с иконками',
    note: 'Один приём на всех ширинах: у каждого пункта своя иконка, и палки-разделители не нужны — иконка сама показывает, где кончается один пункт. Выше 1200 это строка, как сейчас; от 768 до 1200 — четыре колонки в два ряда; на телефоне — два столбца по четыре, реклама на своей строке.',
    tags: ['иконки', 'все ширины', '1 ряд → 4×2 → 2×4'],
  },
  {
    file: '06-centered.html',
    title: 'Центрированный столбец',
    note: 'Всё по центру и крупнее обычного, реклама отделена чертой. Подвал становится финалом страницы, а не служебной строкой.',
    tags: ['центр', 'крупно', 'финал'],
  },
  {
    file: '07-compact.html',
    title: 'Три колонки компактно',
    note: 'Мелкий шрифт и три колонки: весь блок занимает три строки вместо восьми. Для подвала, который не должен тянуть на себя внимание.',
    tags: ['плотно', 'мелко', 'экономный'],
  },
  {
    file: '08-dots.html',
    title: 'Точки вместо палок',
    note: 'Самая малая правка: разделитель переезжает внутрь строки как точка после текста, поэтому при переносе он не остаётся висеть слева. Вид тот же, что сейчас.',
    tags: ['минимальная правка', 'та же строка', 'дёшево'],
  },

  /* Below: the advertising line has no place of its own, and the copyright sits at the top of the footer under the
     wordmark instead of at its foot. These six move the whole lower zone rather than the menu alone. */
  {
    file: '09-service-row.html',
    title: 'Служебная строка',
    note: 'Под меню появляется своя служебная строка: слева копирайт, справа реклама. Реклама перестаёт быть чужой в навигации, потому что попадает в ряд к таким же служебным ссылкам.',
    tags: ['вся зона', 'копирайт вниз', 'реклама на месте'],
  },
  {
    file: '10-cta.html',
    title: 'Реклама — кнопка',
    note: 'Реклама признаётся тем, что она есть: обведённая кнопка во всю ширину под меню, копирайт мелко под ней. Ни с чем не путается именно потому, что выглядит иначе.',
    tags: ['вся зона', 'CTA', 'честный статус'],
  },
  {
    file: '11-ninth-item.html',
    title: 'Девятым пунктом',
    note: 'Обратное решение: реклама становится обычным пунктом меню и встаёт в общую сетку. Выбивалась она из-за особого положения — не станет особой, не будет и выбиваться.',
    tags: ['вся зона', 'в сетку', 'ничего лишнего'],
  },
  {
    file: '12-contact-card.html',
    title: 'Карточка контакта',
    note: 'Реклама уезжает из навигации в блок с мегафоном и подписью — маленькая карточка рядом с меню, как приглашение написать, а не как пункт списка.',
    tags: ['вся зона', 'иконка', 'приглашение'],
  },
  {
    file: '13-column-finale.html',
    title: 'Финал одной колонной',
    note: 'Меню, черта, реклама, копирайт — всё по центру одной колонной. Подвал читается как конец страницы, и у каждой строки своё место в порядке важности.',
    tags: ['вся зона', 'центр', 'финал'],
  },
  {
    file: '14-slim.html',
    title: 'Сжатый подвал',
    note: 'Самое большое вмешательство: колонки на телефоне сворачиваются до логотипа и контактов, технологии прячутся, меню идёт лентой. Подвал перестаёт быть третью страницы.',
    tags: ['вся зона', 'минус высота', 'колонки тоже'],
  },
];

const FOOT_MODES = [
  ['light', 'Светлая'],
  ['auto', 'Системная'],
  ['dark', 'Тёмная'],
];

const FOOT_WIDTHS = [
  ['360', '360'],
  ['390', '390'],
  ['560', '560'],
  ['768', '768'],
  ['full', 'Полная'],
];

/* The live footer, trimmed to what a variant can move: the wordmark column with the copyright, the technology
   column, the contacts column, and the lower row with the menu and the advertising line. The forum column is left
   out because it is a live query, not a layout decision. */
const FOOT_MARKUP = [
  '<footer id="footbox">',
  '  <div class="sl-wrp sl-grid-1-4">',
  '    <section class="sl-grid" aria-label="SLAED CMS">',
  '      <a href="#" class="sl-upper-wordmark" title="SLAED CMS">',
  '        <img class="sl-upper-wordmark-img" src="../../templates/lite/images/logos/slaed-logo-wordmark-outline-blue.svg" alt="SLAED CMS" width="355" height="110">',
  '        <span class="sl-upper-tagline sl-font">Все великое просто</span>',
  '      </a>',
  '      <p class="sl-thd sl-foot-copy">SLAED CMS © 2005–2026 Eduard Laas. Released under MIT License.</p>',
  '      <a class="sl-thd sl-madein sl-madein-brand" href="#" title="Официальный патент на бренд SLAED в Германии">',
  '        <img src="../../templates/lite/images/flags/de.svg" alt="Германия" width="60" height="40">',
  '        <span class="sl-madein-label sl-font">Made<br>in<br>Germany</span>',
  '      </a>',
  '    </section>',
  '    <section class="sl-grid" aria-label="Технологии">',
  '      <div class="sl-font sl-f-title">Технологии</div>',
  '      <div class="sl-partners">',
  '        <a href="#" title="PHP"><img src="../../templates/lite/images/tmp/php.png" alt="PHP" width="74" height="74"></a>',
  '        <a href="#" title="MySQL"><img src="../../templates/lite/images/tmp/mysql.png" alt="MySQL" width="74" height="74"></a>',
  '        <a href="#" title="HTML 5"><img src="../../templates/lite/images/tmp/html5.png" alt="HTML 5" width="74" height="74"></a>',
  '        <a href="#" title="CSS 3"><img src="../../templates/lite/images/tmp/css3.png" alt="CSS 3" width="74" height="74"></a>',
  '        <a href="#" title="jQuery"><img src="../../templates/lite/images/tmp/jquery.png" alt="jQuery" width="74" height="74"></a>',
  '        <a href="#" title="jQuery UI"><img src="../../templates/lite/images/tmp/jqueryui.png" alt="jQuery UI" width="74" height="74"></a>',
  '      </div>',
  '    </section>',
  '    <section class="sl-grid" aria-label="Контакты">',
  '      <div class="sl-font sl-f-title">Контакты</div>',
  '      <address>',
  '        <ul class="sl-block-contact">',
  '          <li><i class="bi bi-geo-alt sl-contact-icon" aria-hidden="true"></i>D-49179, Deutschland<br>Ostercappeln, Im Siek 6</li>',
  '          <li><i class="bi bi-telephone sl-contact-icon" aria-hidden="true"></i>+49 176 61966679</li>',
  '          <li><i class="bi bi-envelope sl-contact-icon" aria-hidden="true"></i><a href="#">support@slaed.net</a></li>',
  '          <li><i class="bi bi-globe sl-contact-icon" aria-hidden="true"></i><a href="#">https://slaed.net</a></li>',
  '        </ul>',
  '      </address>',
  '    </section>',
  '  </div>',
  '  <div class="sl-wrp">',
  '    <nav class="sl-fmenu" aria-label="Нижнее меню">',
  '      <ul>',
  '        <li><a href="#" title="Главная">Главная</a></li>',
  '        <li><a href="#" title="Платные услуги проекта">Услуги</a></li>',
  '        <li><a href="#" title="Новости">Новости</a></li>',
  '        <li><a href="#" title="Каталог файлов">Файлы</a></li>',
  '        <li><a href="#" title="Вопросы и ответы">Вопросы и ответы</a></li>',
  '        <li><a href="#" title="Центр документации">Документация</a></li>',
  '        <li><a href="#" title="Презентационная страница системы">Портфолио</a></li>',
  '        <li><a href="#" title="Карта сайта">Карта сайта</a></li>',
  '      </ul>',
  '      <a class="sl-pull-right" href="#" title="Размещение рекламы на проекте">По вопросам рекламы</a>',
  '    </nav>',
  '  </div>',
  '</footer>',
].join('\n');

const footState = {
  mode: localStorage.getItem('foot.mode') || 'dark',
  width: localStorage.getItem('foot.width') || '390',
  motion: localStorage.getItem('foot.motion') || 'on',
};

function buildFootPanel(current) {
  const idx = FOOT_VARIANTS.findIndex((v) => v.file === current);
  const safe = idx < 0 ? 0 : idx;
  const prev = FOOT_VARIANTS[(safe - 1 + FOOT_VARIANTS.length) % FOOT_VARIANTS.length];
  const next = FOOT_VARIANTS[(safe + 1) % FOOT_VARIANTS.length];
  const seg = (name, items, active) => items.map(([val, label]) =>
    `<button type="button" data-foot-set="${name}" data-foot-value="${val}" aria-pressed="${val === active}">${label}</button>`).join('');
  const el = document.createElement('div');
  el.className = 'demo-panel';
  el.innerHTML = `
    <div class="demo-panel-head">
      <span class="demo-panel-title">${idx >= 0 ? String(idx + 1).padStart(2, '0') + ' · ' + FOOT_VARIANTS[idx].title : 'Стенд подвала'}</span>
      <button type="button" class="demo-panel-toggle" title="Свернуть">–</button>
    </div>
    <div class="demo-panel-row"><span>Тема</span><div class="demo-seg">${seg('mode', FOOT_MODES, footState.mode)}</div></div>
    <div class="demo-panel-row"><span>Ширина окна, px</span><div class="demo-seg">${seg('width', FOOT_WIDTHS, footState.width)}</div></div>
    <div class="demo-panel-row"><span>Движение</span><div class="demo-seg">${seg('motion', [['on', 'Вкл'], ['off', 'Выкл']], footState.motion)}</div></div>
    <div class="demo-panel-nav">
      <a href="${prev.file}" title="${prev.title}">← ${prev.file.slice(0, 2)}</a>
      <a href="index.html" title="Все варианты">Все варианты</a>
      <a href="${next.file}" title="${next.title}">${next.file.slice(0, 2)} →</a>
    </div>`;
  return el;
}

function applyFootState() {
  document.documentElement.dataset.theme = footState.mode;
  document.documentElement.dataset.demoMotion = footState.motion;
  const frame = document.querySelector('.foot-frame');
  if (frame) {
    frame.style.width = footState.width === 'full' ? '100%' : footState.width + 'px';
    const url = new URL(frame.getAttribute('src'), location.href);
    url.searchParams.set('mode', footState.mode);
    if (frame.src !== url.href) frame.src = url.href;
  }
  document.querySelectorAll('[data-foot-set]').forEach((b) => {
    b.setAttribute('aria-pressed', String(footState[b.dataset.footSet] === b.dataset.footValue));
  });
  localStorage.setItem('foot.mode', footState.mode);
  localStorage.setItem('foot.width', footState.width);
  localStorage.setItem('foot.motion', footState.motion);
}

function initFootPage() {
  const params = new URLSearchParams(location.search);
  const bare = params.has('bare');
  const file = location.pathname.split('/').pop() || '';
  const note = FOOT_VARIANTS.find((v) => v.file === file);

  if (params.get('mode')) footState.mode = params.get('mode');

  /* Bare is what the frame loads: the block alone, at the frame's own width */
  if (bare) {
    document.body.innerHTML = '<div class="foot-bare-filler"><p>Ниже — нижнее меню сайта, тот же CSS, что на живой странице.</p></div>' + FOOT_MARKUP;
    document.documentElement.dataset.theme = footState.mode;
    return;
  }

  document.body.innerHTML = `
    <div class="foot-stage">
      <h1 class="sl-title">${note ? note.title : 'Стенд подвала'}</h1>
      <div class="demo-note"><h2>Что здесь сделано</h2><p>${note ? note.note : ''}</p></div>
      <p class="foot-hint">Вариант живёт в рамке ниже, на выбранной ширине окна: медиазапросы темы отвечают на ширину
      окна, а не на ширину блока, поэтому суженный <code>&lt;div&gt;</code> продолжал бы получать правила для монитора.</p>
      <div class="foot-frame-wrap"><iframe class="foot-frame" title="${note ? note.title : 'Вариант'}" src="${file}?bare=1"></iframe></div>
    </div>`;
  document.body.appendChild(buildFootPanel(file));
  applyFootState();

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-foot-set]');
    if (btn) {
      footState[btn.dataset.footSet] = btn.dataset.footValue;
      applyFootState();
      return;
    }
    const tgl = e.target.closest('.demo-panel-toggle');
    if (tgl) {
      const panel = tgl.closest('.demo-panel');
      const on = panel.dataset.collapsed === '1';
      panel.dataset.collapsed = on ? '0' : '1';
      tgl.textContent = on ? '–' : '+';
    }
  });
}

function bootFoot() {
  if (document.body.dataset.footGallery === undefined) initFootPage();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootFoot);
} else {
  bootFoot();
}
