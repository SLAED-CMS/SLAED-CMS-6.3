/* Demo rig: one manifest, one page chrome, one control rail. Every variant page is nothing but its own
   <style> block, so comparing two variants is comparing two blocks of CSS and nothing else. */

const DEMO_VARIANTS = [
  {
    file: '01-duotone.html',
    title: 'Дуотон и вуаль',
    note: 'Фото уходит в один тон бренда через background-blend-mode, поверх — вуаль. Ноль анимации, ноль JS. В светлой теме не делает ничего: тон становится белым, вуаль прозрачной.',
    tags: ['статика', 'один блок CSS', 'дёшево'],
  },
  {
    file: '02-aurora.html',
    title: 'Аврора',
    note: 'Две занавеси северного сияния дрейфуют над фото, 24 и 31 секунда на круг. Свечение живёт своей силой и ползунка не читает, поэтому работает и на нуле, и в светлой теме.',
    tags: ['анимация', 'свет', 'обе темы'],
  },
  {
    file: '03-parallax.html',
    title: 'Параллакс на скролле',
    note: 'Фото плывёт медленнее страницы на CSS scroll-timeline — без единой строки JS, без scroll-слушателя, на композиторе.',
    tags: ['скролл', 'без JS', 'scroll-timeline'],
  },
  {
    file: '04-spotlight.html',
    title: 'Прожектор за курсором',
    note: 'Тёмная штора с дырой под курсором: в тёмной теме за дырой проступает фото, в светлой остаётся только тёплое пятно света без затемнения.',
    tags: ['указатель', 'интерактив', 'мало JS'],
  },
  {
    file: '05-mesh.html',
    title: 'Мешь-градиент и зерно',
    note: 'Фотографии нет вовсе: живой градиент из четырёх пятен бренда плюс плёночное зерно. Минус четыре JPEG из загрузки страницы.',
    tags: ['без фото', 'анимация', 'минус вес'],
  },
  {
    file: '06-stars.html',
    title: 'Ночное небо',
    note: 'Звёздная пыль в три слоя разной скорости и падающая звезда раз в двенадцать секунд. В тёмной теме ночь, в светлой те же искры над нетронутым фото.',
    tags: ['анимация', 'ночь', 'три слоя'],
  },
  {
    file: '07-grid.html',
    title: 'Неоновая сетка',
    note: 'Уходящая в перспективу сетка и скан-луч поверх неё — техничный тон, который читается как консоль, а не как открытка.',
    tags: ['анимация', 'HUD', 'перспектива'],
  },
  {
    file: '08-kenburns.html',
    title: 'Кен Бёрнс',
    note: 'Сезон остаётся на месте: то же фото, но с медленным наездом на сорок секунд. Оживает то, что уже есть, и в обеих темах одинаково.',
    tags: ['фото', 'медленно', 'сезоны'],
  },
  {
    file: '09-glass.html',
    title: 'Матовое стекло',
    note: 'Фото уходит в расфокус за стеклянной панелью: backdrop-filter, кромка света сверху и блик, ползущий по стеклу.',
    tags: ['стекло', 'blur', 'блик'],
  },
  {
    file: '10-beam.html',
    title: 'Световой луч',
    note: 'Полоса спокойна, но раз в семь секунд по ней проходит блик, а по нижней кромке горит градиентная нить бренда.',
    tags: ['анимация', 'акцент', 'дёшево'],
  },
  {
    file: '11-burns-spot.html',
    title: 'Кен Бёрнс и прожектор',
    note: 'Два выбранных приёма в одной полосе: сорокасекундный наезд на сезонное фото и тёплая линза, идущая за курсором. Полоса живёт сама и отвечает на руку.',
    tags: ['гибрид 08+04', 'фото', 'указатель'],
  },
  {
    file: '12-mouse-parallax.html',
    title: 'Параллакс за курсором',
    note: 'Фото едет за указателем на десяток пикселей, заголовок — втрое меньше, и между ними появляется глубина. Ни одного затемняющего слоя.',
    tags: ['указатель', 'глубина', 'обе темы'],
  },
  {
    file: '13-lens.html',
    title: 'Цветная линза',
    note: 'Полоса обесцвечена, и цвет сезона возвращается только в круге под курсором. Работает через backdrop-filter и маску, яркость не трогает вовсе.',
    tags: ['указатель', 'маска', 'цвет'],
  },
  {
    file: '14-particles.html',
    title: 'Сезонные частицы',
    note: 'Над фото летит то, что положено сезону: снег зимой, пыльца летом, листья осенью, искры под Новый год. Чистое добавление, обе темы одинаково.',
    tags: ['сезоны', 'анимация', 'без затемнения'],
  },
  {
    file: '15-tilt.html',
    title: 'Наклон к курсору',
    note: 'Полоса ведёт себя как плоскость на шарнире: фото уходит в 3D-наклон вслед за указателем, блик скользит по стороне, к которой она повернулась.',
    tags: ['указатель', '3D', 'тактильность'],
  },
  {
    file: '16-kinetic.html',
    title: 'Кинетическая типографика',
    note: 'Фото не трогаем совсем — оживает сам заголовок: по буквам идёт волна света, под ними просыпается нить бренда. Самый дешёвый способ сделать полосу живой.',
    tags: ['текст', 'дёшево', 'без фото-слоёв'],
  },
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
      <ul class="sl-top-contact"><li class="sl-head-marquee"><a href="#" title="Как сохранить или восстановить базу данных?"><i class="bi bi-stars" aria-hidden="true"></i>&nbsp;Как сохранить или восстановить базу данных?</a></li></ul>
      <div class="sl-top-right">
        <div class="sl-top-social">
          <a class="sl-thd sl-circle-action sl-cat-tone-0" href="#" title="Мы в GitHub" aria-label="Мы в GitHub"><i class="bi bi-github" aria-hidden="true"></i>Мы в GitHub</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-1" href="#" title="Мы в YouTube" aria-label="Мы в YouTube"><i class="bi bi-youtube" aria-hidden="true"></i>Мы в YouTube</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-2" href="#" title="Мы в X" aria-label="Мы в X"><i class="bi bi-twitter-x" aria-hidden="true"></i>Мы в X</a>
          <a class="sl-thd sl-circle-action sl-cat-tone-3" href="#" title="Мы вКонтакте" aria-label="Мы вКонтакте"><i class="bi bi-chat-square-text" aria-hidden="true"></i>Мы вКонтакте</a>
        </div>
      </div>
    </div>
  </div>
  <header id="header">
    <div class="sl-wrp">
      <a href="#" class="sl-thd sl-logo" title="Название сайта">
        <img src="${T}/images/logos/slaed-logo-wordmark-gradient-blue.svg" alt="Название сайта" width="8833" height="2699">
      </a>
      <p class="sl-font sl-slogan">Все великое<br>просто</p>
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
              <li><a href="#" title="Новая установка системы на хостинг">Новая установка системы</a></li>
            </ul>
          </li>
          <li><a href="#" title="Услуги">Услуги</a></li>
          <li><a href="#" title="Загрузки">Загрузки</a></li>
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

const CHROME_HEADBAND = `
  <div id="head-content">
    <div class="sl-wrp"><p class="sl-font">Новости</p></div>
  </div>`;

const CHROME_DEMOLINE = `
  <section id="demo-line" aria-label="Хотите опробовать SLAED CMS в действии?">
    <div class="sl-wrp">
      <div class="sl-font sl-demo-line-title">Хотите опробовать SLAED CMS в действии?</div>
      <ul class="sl-d-pane">
        <li class="sl-d-info sl-d-version sl-font">Aктуальная версия</li>
        <li class="sl-d-num sl-font">6.2</li>
        <li class="sl-d-btns"><a class="sl-but" href="#" title="Скачать бесплатно актуальную версию системы">Скачать систему</a></li>
      </ul>
    </div>
  </section>`;

const CHROME_FOOT = `
  <footer id="footbox">
    <div class="sl-wrp">
      <p class="sl-thd">2005 &ndash; 2026 SLAED &middot; демонстрационный стенд, в поставку не входит</p>
    </div>
  </footer>`;

const DEMO_TEXT = `
    <p>Полоса выше — <code>#head-content</code>, полоса ниже — <code>#demo-line</code>. Обе несут сезонное фото
    и белый текст поверх него. В светлой теме фото читается как открытка, в тёмной — как включённая лампа
    в тёмной комнате: страница уходит в <code>#111827</code>, а полосы остаются на прежней яркости.</p>
    <p>Прокрутите страницу до нижней полосы, переключите тему и сезон на панели справа и сравните вариант
    с соседними. Ползунок правит только одно — <code>--demo-dim</code>, силу затемнения фото, и только в тёмной
    теме: на нуле полоса ровно такая, какая идёт в поставке сейчас. Сам эффект варианта ползунка не читает
    и живёт при любом его положении, в обеих темах. В светлой теме затемнения нет ни на одном делении.</p>
    <p>Кнопка «Движение» гасит все анимации стенда разом — тем же выключателем, каким это делает
    <code>prefers-reduced-motion</code> у пользователя, так что вариант можно посмотреть и в покое.</p>`;

function getDemoFiller(note) {
  return `
  <div class="sl-wrp">
    <div class="demo-filler">
      <h1 class="sl-title">${note ? note.title : 'Вариант'}</h1>
      <div class="demo-note">
        <h2>Что здесь сделано</h2>
        <p>${note ? note.note : ''}</p>
      </div>
      ${DEMO_TEXT}
      ${DEMO_TEXT}
    </div>
  </div>`;
}

const demoState = {
  mode: localStorage.getItem('demo.mode') || 'dark',
  season: localStorage.getItem('demo.season') || 'sl-summer',
  dim: Number(localStorage.getItem('demo.dim.v3') ?? 15),
  motion: localStorage.getItem('demo.motion') || 'on',
};

function buildDemoPanel(current) {
  const idx = DEMO_VARIANTS.findIndex((v) => v.file === current);
  const safe = idx < 0 ? 0 : idx;
  const prev = DEMO_VARIANTS[(safe - 1 + DEMO_VARIANTS.length) % DEMO_VARIANTS.length];
  const next = DEMO_VARIANTS[(safe + 1) % DEMO_VARIANTS.length];
  const seg = (name, items, active) => items.map(([val, label]) =>
    `<button type="button" data-demo-set="${name}" data-demo-value="${val}" aria-pressed="${val === active}">${label}</button>`).join('');
  const el = document.createElement('div');
  el.className = 'demo-panel';
  el.innerHTML = `
    <div class="demo-panel-head">
      <span class="demo-panel-title">${idx >= 0 ? String(idx + 1).padStart(2, '0') + ' · ' + DEMO_VARIANTS[idx].title : 'Стенд'}</span>
      <button type="button" class="demo-panel-toggle" title="Свернуть">–</button>
    </div>
    <div class="demo-panel-row"><span>Тема</span><div class="demo-seg">${seg('mode', DEMO_MODES, demoState.mode)}</div></div>
    <div class="demo-panel-row"><span>Сезон</span><div class="demo-seg">${seg('season', DEMO_SEASONS, demoState.season)}</div></div>
    <div class="demo-panel-row"><span>Затемнение фото, тёмная тема: <b data-demo-dim-out>${demoState.dim}</b>%</span>
      <input type="range" min="0" max="100" step="2" value="${demoState.dim}" data-demo-dim></div>
    <div class="demo-panel-row"><span>Движение</span><div class="demo-seg">${seg('motion', [['on', 'Вкл'], ['off', 'Выкл']], demoState.motion)}</div></div>
    <div class="demo-panel-nav">
      <a href="${prev.file}" title="${prev.title}">← ${prev.file.slice(0, 2)}</a>
      <a href="index.html" title="Все варианты">Все варианты</a>
      <a href="${next.file}" title="${next.title}">${next.file.slice(0, 2)} →</a>
    </div>`;
  return el;
}

function applyDemoState() {
  document.documentElement.dataset.theme = demoState.mode;
  document.documentElement.dataset.demoMotion = demoState.motion;
  document.documentElement.style.setProperty('--demo-dim', String(demoState.dim / 100));
  DEMO_SEASONS.forEach(([cls]) => document.body.classList.toggle(cls, cls === demoState.season));
  localStorage.setItem('demo.mode', demoState.mode);
  localStorage.setItem('demo.season', demoState.season);
  localStorage.setItem('demo.dim.v3', String(demoState.dim));
  localStorage.setItem('demo.motion', demoState.motion);
  document.querySelectorAll('[data-demo-set]').forEach((b) => {
    b.setAttribute('aria-pressed', String(demoState[b.dataset.demoSet] === b.dataset.demoValue));
  });
}

function initDemoPage() {
  const params = new URLSearchParams(location.search);
  const bare = params.has('bare');
  const file = location.pathname.split('/').pop() || '';
  const note = DEMO_VARIANTS.find((v) => v.file === file);

  if (params.get('season')) demoState.season = params.get('season');
  if (params.get('mode')) demoState.mode = params.get('mode');

  document.body.innerHTML = bare
    ? CHROME_HEADBAND + '<div class="sl-wrp"><div class="demo-filler"><p>Тот же CSS, что и на живой странице: полоса заголовка сверху, полоса демо снизу.</p></div></div>' + CHROME_DEMOLINE
    : CHROME_TOP + CHROME_HEADBAND + getDemoFiller(note) + CHROME_DEMOLINE + CHROME_FOOT;

  if (!bare) document.body.appendChild(buildDemoPanel(file));
  applyDemoState();

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-demo-set]');
    if (btn) {
      demoState[btn.dataset.demoSet] = btn.dataset.demoValue;
      applyDemoState();
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

  document.addEventListener('input', (e) => {
    const range = e.target.closest('[data-demo-dim]');
    if (!range) return;
    demoState.dim = Number(range.value);
    const out = document.querySelector('[data-demo-dim-out]');
    if (out) out.textContent = String(demoState.dim);
    applyDemoState();
  });
}


/* Pointer tracking shared by the variants that answer the hand. Two jobs, and the second one is why this is
   twenty lines and not five: it writes the position on a rAF loop, easing the value toward the pointer instead of
   slamming it there on every pointermove. A raw pointermove write lands two or three times per frame and the eye
   reads that as a stutter; a CSS transition on the same property restarts on every event and reads worse still.

   Written on both bands: --demo-x / --demo-y in pixels from the top-left of the band, --demo-nx / --demo-ny
   normalised to -1..1 around its centre. Registered with @property in demo.css. */
function demoTrackPointer() {
  const state = new Map();
  let pointer = null;
  let running = false;

  const bands = () => document.querySelectorAll('#head-content, #demo-line');

  function frame() {
    let moving = false;
    bands().forEach((band) => {
      const box = band.getBoundingClientRect();
      const cur = state.get(band) || { x: box.width / 2, y: box.height / 2 };
      const tx = pointer ? pointer.x - box.left : box.width / 2;
      const ty = pointer ? pointer.y - box.top : box.height / 2;
      cur.x += (tx - cur.x) * 0.16;
      cur.y += (ty - cur.y) * 0.16;
      state.set(band, cur);
      band.style.setProperty('--demo-x', cur.x.toFixed(1) + 'px');
      band.style.setProperty('--demo-y', cur.y.toFixed(1) + 'px');
      band.style.setProperty('--demo-nx', ((cur.x / box.width) * 2 - 1).toFixed(3));
      band.style.setProperty('--demo-ny', ((cur.y / box.height) * 2 - 1).toFixed(3));
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

  /* Off the window the light goes home to the middle of the band rather than freezing where it was left */
  document.addEventListener('pointerleave', () => {
    pointer = null;
    wake();
  });
}

/* The gallery draws its own body from the same manifest, so it opts out of the chrome */
function bootDemo() {
  if (document.body.dataset.demoGallery === undefined) initDemoPage();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', bootDemo);
} else {
  bootDemo();
}
