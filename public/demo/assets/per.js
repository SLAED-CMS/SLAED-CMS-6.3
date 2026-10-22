/* Rows-per-page series (per-NN): one personal number of rows for every list of the panel, chosen on the list itself.
   The screen is the one of assets/tbl.js in the theme of today (without .tb-skin); this engine adds what the number
   needs — a corpus as long as the filter, two pagers that count their pages from the number and keep the reader on the
   same row when it changes, the toast of the theme with Undo, and the stand desk with the module default, the size of
   the filter and the reset.
   A variant brings only its control in <template data-per-face> and its own <style>. The choice is kept in
   localStorage under sl.demo.rows, so every variant of the series and every reload sees it, as the live cookie would.
   Attributes of a face:
     data-per-slot="info|range|side|nav|view|foot"  the page line / in place of its range / the side group of the pager /
                                                   after its arrows / before the view switch / under the pager
     data-per-where="top|bottom|both"               which pager carries it; both by default, the view slot is top only
     {n}                                            in the template, replaced by the number of the copy, for ids
     data-per-set="N|all"        a press sets N, or every row on one page: .sl-is-active, and aria-checked on a menuitemradio,
                                 aria-pressed else
     data-per-reset              back to the default of the module; disabled while no own number is set
     data-per-select             a <select> of numbers; its option 0 is the reset, labels are written by the engine
     data-per-input              a number field, applied on Enter or change, held to 5…1000; ="custom" shows only a number
                                 off the ladder, is applied only by Enter or its [data-per-apply] and forgets what was typed
                                 once the focus leaves it
     data-per-custom             lit (.sl-is-active) while the number in force is an own one off the ladder
     data-per-step="-1|1"        one step down or up the ladder; disabled at its ends
     data-per-scale              an <input type=range> over the index of the ladder
     data-per-cur / -base / -range / -pos / -total / -sum / -all-hint   text the engine writes: the number, the module default,
                                 "1–25", "1 из 337", "8 411", "8411", and the count or the cap beside "all"
     data-per-menu > [data-per-menu-toggle] + [data-per-menu-panel]   a menu on click; Esc, a pick or outside close it
     data-per-float on a .sl-float  the system float of slaed.js, open on hover and focus; arrows walk its buttons
     data-per-pages-of="N"       text the engine writes: how many pages N rows a page make, or "= все" when N holds the
                                 whole filter, which also marks its [data-per-set] with data-per-same
   A ladder cell equal to the module default carries data-per-is-def. The host [data-tb-shell] carries data-per-limit,
   and data-per-own while an own number is set */
const PER_LADDER = [10, 25, 50, 100, 250];
/* The most rows one page may hold: the cap of an own number and of "all" */
const PER_MAX = 1000;
const PER_ALL = -1;
const PER_KEY = 'sl.demo.rows';
const PER_MODS = [[25, 'Комментарии'], [50, 'Фавориты, Переходы, Личные сообщения']];
const PER_COUNTS = [8411, 640, 40];
const perState = { def: 25, own: 0, page: 1, total: PER_COUNTS[0] };
let perShell = null;

function getPerRead() {
  try {
    return parseInt(window.localStorage.getItem(PER_KEY) || '0', 10) || 0;
  } catch (err) {
    return 0;
  }
}

function setPerWrite(v) {
  try {
    if (v) window.localStorage.setItem(PER_KEY, String(v));
    else window.localStorage.removeItem(PER_KEY);
  } catch (err) {
    /* A private window keeps the choice for the page only */
  }
}

function getPerLimit() {
  if (perState.own === PER_ALL) return getPerAll() ? perState.total : perState.def;
  return perState.own || perState.def;
}

/* "All" holds only while the filter fits under the cap; above it the list falls back to the default of the module */
function getPerAll() {
  return perState.total <= PER_MAX;
}

function getPerPages() {
  return Math.ceil(perState.total / getPerLimit());
}

function getPerWide(n) {
  return n.toLocaleString('ru-RU');
}

/* Row i of the whole filter: the 32 rows of the corpus repeated, each lap 112 days older, numbers counting down */
function getPerRow(i) {
  const base = TBL_ROWS[i % TBL_ROWS.length];
  const lap = Math.floor(i / TBL_ROWS.length);
  return { ...base, id: 20702 - i, date: new Date(new Date(base.date).getTime() - lap * 112 * 86400000).toISOString() };
}

/* The run of page cells as getTplPagerView() prints it: a window of eight around the page, the first and the last beside */
function getPerRun() {
  const page = perState.page;
  const pages = getPerPages();
  const start = Math.max(1, Math.min(page - 3, pages - 7));
  const end = Math.min(pages, start + 7);
  const cell = (n) => (n === page
    ? `<span title="${n}" aria-current="page" class="sl-seg-cell sl-is-active">${n}</span>`
    : `<a href="#" data-per-page="${n}" title="${n}" class="sl-seg-cell ${Math.abs(n - page) === 1 ? 'sl-pager-near' : 'sl-pager-far'}">${n}</a>`);
  const gap = '<span class="sl-seg-cell sl-is-muted" aria-hidden="true">…</span>';
  let run = '';
  if (start > 1) run += cell(1) + (start > 2 ? gap : '');
  for (let n = start; n <= end; n++) run += cell(n);
  if (end < pages) run += (end < pages - 1 ? gap : '') + cell(pages);
  return run;
}

function getPerStep(dir) {
  const page = perState.page + dir;
  const name = dir < 0 ? 'Назад' : 'Вперед';
  const icon = dir < 0 ? 'chevron-left' : 'chevron-right';
  const mark = `<i class="bi bi-${icon}" aria-hidden="true"></i>`;
  if (page < 1 || page > getPerPages()) return `<span title="${name}" class="sl-but-mini sl-is-muted" data-per-arrow="${dir}">${mark}</span>`;
  return `<a href="#" data-per-page="${page}" data-per-arrow="${dir}" title="${name}" aria-label="${name}" rel="${dir > 0 ? 'next' : 'prev'}"` +
    ` class="sl-but-mini">${mark}</a>`;
}

/* The pager of the theme (fragments/pager.html) with its key tip; the side, top or bottom, is kept for the faces */
function getPerPager(side) {
  const jump = side === 'top'
    ? '<a href="#sl-list-end" title="Вниз" aria-label="Вниз" class="sl-but-mini"><i class="bi bi-arrow-down" aria-hidden="true"></i></a>'
    : '<a href="#top" title="Наверх" aria-label="Наверх" class="sl-but-mini"><i class="bi bi-arrow-up" aria-hidden="true"></i></a>';
  const key = (glyph, said) => `<kbd><i class="bi bi-${glyph}" role="img" aria-label="${said}"></i></kbd>`;
  const list = [[key('arrow-left', 'Стрелка влево'), 'Предыдущая страница'], [key('arrow-right', 'Стрелка вправо'), 'Следующая страница'],
    ['<kbd>J</kbd><kbd>K</kbd>', 'Строка ниже и выше'], ['<kbd>X</kbd>', 'Отметить строку'], ['<kbd>E</kbd>', 'Править строку'], ['<kbd>/</kbd>', 'Поиск'],
    ['<kbd>Ctrl</kbd><kbd>K</kbd>', 'Быстрый переход'], ['<kbd>[</kbd>', 'Свернуть меню']].map(([keys, text]) => `<li>${keys}${text}</li>`).join('');
  const tip = '<div class="sl-tip sl-float sl-pager-keys" tabindex="0" role="button" aria-label="Листайте с клавиатуры">' +
    '<span class="sl-but-mini" aria-hidden="true"><i class="bi bi-info-lg"></i></span><div class="sl-float-panel sl-pager-tip" role="tooltip">' +
    `<p class="sl-pager-tip-head">Листайте с клавиатуры</p><ul class="sl-pager-tip-keys">${list}</ul>` +
    '<p class="sl-pager-tip-note">Пока курсор не стоит в поле ввода</p></div></div>';
  const rail = '<span class="sl-seg sl-seg-pager" data-sl-knob="pager"><span class="sl-seg-knob" data-sl-knob-mark="" aria-hidden="true"></span>' +
    `<span class="sl-seg-run sl-pager-pages">${getPerRun()}</span></span>`;
  return `<nav class="sl-pager" aria-label="Страницы" data-per-side="${side}"><div class="sl-pager-main">
    <div class="sl-pager-nav">${getPerStep(-1)}${rail}${getPerStep(1)}</div>
    <div class="sl-pager-side"><span class="sl-pager-sep" aria-hidden="true"></span>${jump}${tip}</div></div>
  <p class="sl-pager-info">Страница <b data-per-pos></b><span class="sl-pager-range"> · Показаны <span data-per-range></span></span> · ` +
    'Всего <b data-per-sum></b></p></nav>';
}

/* The face of the variant, copied into every place its slot names */
function setPerFaces() {
  let copy = 0;
  document.querySelectorAll('template[data-per-face]').forEach((tpl) => {
    const slot = tpl.dataset.perSlot;
    const where = tpl.dataset.perWhere || (slot === 'view' ? 'top' : 'both');
    const html = () => tpl.innerHTML.replaceAll('{n}', String(++copy));
    if (slot === 'view') {
      perShell.querySelector('.sl-list-view').insertAdjacentHTML('afterbegin', html());
      return;
    }
    perShell.querySelectorAll('.sl-pager').forEach((pg) => {
      if (where !== 'both' && pg.dataset.perSide !== where) return;
      if (slot === 'info') pg.querySelector('.sl-pager-info').insertAdjacentHTML('beforeend', html());
      if (slot === 'range') pg.querySelector('.sl-pager-range').outerHTML = html();
      if (slot === 'side') pg.querySelector('.sl-pager-side').insertAdjacentHTML('afterbegin', html());
      if (slot === 'nav') pg.querySelector('.sl-pager-nav').insertAdjacentHTML('beforeend', html());
      if (slot === 'foot') pg.insertAdjacentHTML('afterend', html());
    });
  });
}

/* Everything that follows the number and the page: rows, page runs, arrows, texts, the marks of every control, the desk */
function setPerDraw() {
  const limit = getPerLimit();
  const from = (perState.page - 1) * limit;
  const upto = Math.min(from + limit, perState.total);
  const rows = [];
  for (let i = from; i < upto; i++) rows.push(getTblRow(getPerRow(i)));
  perShell.querySelector('tbody').innerHTML = rows.join('');
  setTblPicked();
  perShell.querySelectorAll('.sl-pager').forEach((pg) => {
    pg.querySelector('.sl-pager-pages').innerHTML = getPerRun();
    pg.querySelectorAll('[data-per-arrow]').forEach((one) => { one.outerHTML = getPerStep(Number(one.dataset.perArrow)); });
  });
  const said = {
    perCur: String(limit),
    perBase: String(perState.def),
    perRange: perState.own === PER_ALL && getPerAll() ? 'все' : `${getPerWide(from + 1)}–${getPerWide(upto)}`,
    perPos: `${perState.page} из ${getPerPages()}`,
    perTotal: getPerWide(perState.total),
    perSum: String(perState.total),
    perAllHint: getPerAll() ? getPerWide(perState.total) : `только до ${getPerWide(PER_MAX)}`,
  };
  Object.keys(said).forEach((key) => {
    const attr = 'data-' + key.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
    document.querySelectorAll(`[${attr}]`).forEach((one) => {
      if (one.matches('input, select')) return;
      one.textContent = said[key];
    });
  });
  document.querySelectorAll('[data-per-set]').forEach((one) => {
    const n = one.dataset.perSet === 'all' ? PER_ALL : Number(one.dataset.perSet);
    const on = n === PER_ALL ? perState.own === PER_ALL && getPerAll() : !(perState.own === PER_ALL && getPerAll()) && n === limit;
    if (n === PER_ALL) one.disabled = !getPerAll();
    one.classList.toggle('sl-is-active', on);
    one.setAttribute(one.getAttribute('role') === 'menuitemradio' ? 'aria-checked' : 'aria-pressed', String(on));
    one.toggleAttribute('data-per-is-def', n === perState.def);
  });
  document.querySelectorAll('[data-per-pages-of]').forEach((one) => {
    const n = Number(one.dataset.perPagesOf);
    const same = n >= perState.total;
    one.textContent = same ? '= все' : `${getPerWide(Math.ceil(perState.total / n))} стр.`;
    one.closest('[data-per-set]')?.toggleAttribute('data-per-same', same);
  });
  document.querySelectorAll('[data-per-reset]').forEach((one) => { one.disabled = !perState.own; });
  document.querySelectorAll('select[data-per-select]').forEach((sel) => {
    if (!sel.querySelector(`option[value="${limit}"]`)) sel.insertAdjacentHTML('beforeend', `<option value="${limit}" data-per-extra>${limit}</option>`);
    sel.querySelectorAll('option').forEach((opt) => {
      const n = Number(opt.value);
      if (n === 0) {
        opt.textContent = `Сбросить (${perState.def})`;
        opt.disabled = !perState.own;
      } else {
        opt.textContent = n === perState.def ? `${n} · умолч.` : String(n);
      }
    });
    sel.value = String(limit);
  });
  const custom = perState.own > 0 && !PER_LADDER.includes(perState.own);
  document.querySelectorAll('[data-per-custom]').forEach((one) => one.classList.toggle('sl-is-active', custom));
  document.querySelectorAll('input[data-per-input]').forEach((one) => { if (one !== document.activeElement) setPerField(one); });
  document.querySelectorAll('input[data-per-scale]').forEach((one) => {
    one.max = String(PER_LADDER.length - 1);
    one.value = String(getPerNear(limit));
    one.setAttribute('aria-valuetext', `${limit} строк`);
  });
  document.querySelectorAll('[data-per-step]').forEach((one) => {
    one.disabled = Number(one.dataset.perStep) < 0 ? limit <= PER_LADDER[0] : limit >= PER_LADDER[PER_LADDER.length - 1];
  });
  perShell.dataset.perLimit = String(limit);
  perShell.toggleAttribute('data-per-own', !!perState.own);
  document.querySelectorAll('[data-per-mod]').forEach((one) => one.setAttribute('aria-pressed', String(Number(one.dataset.perMod) === perState.def)));
  document.querySelectorAll('[data-per-count]').forEach((one) => one.setAttribute('aria-pressed', String(Number(one.dataset.perCount) === perState.total)));
  // A new range changes the width of a float trigger; the system refits its open panels on resize
  if (document.querySelector('[data-per-float].sl-is-open')) window.dispatchEvent(new Event('resize'));
  const own = document.querySelector('[data-per-own-text]');
  if (!own) return;
  own.textContent = perState.own
    ? `${perState.own === PER_ALL ? 'все строки' : perState.own} — для всех списков` : `нет — действует умолчание модуля (${perState.def})`;
}

/* "по N строк" in the case the number asks for: по 1 строке, по 2 строки, по 5 строк */
function getPerWord(n) {
  const one = n % 10;
  const ten = n % 100;
  if (one === 1 && ten !== 11) return 'строке';
  return one >= 2 && one <= 4 && (ten < 12 || ten > 14) ? 'строки' : 'строк';
}

/* The field shows the number in force; the field of an own number stays empty while a number of the ladder holds */
function setPerField(one) {
  const custom = perState.own > 0 && !PER_LADDER.includes(perState.own);
  one.value = one.dataset.perInput !== 'custom' || custom ? String(getPerLimit()) : '';
}

/* What the field holds becomes the number, then the field shows what was taken: 3000 comes back as 1000 */
function setPerOwn(one) {
  if (one.value !== '') setPerLimit(Number(one.value));
  setPerField(one);
}

function getPerNear(v) {
  let at = 0;
  PER_LADDER.forEach((n, i) => { if (Math.abs(n - v) < Math.abs(PER_LADDER[at] - v)) at = i; });
  return at;
}

/* The row the reader is on: the first one whose bottom is below the sticky head of the table, with its place in the window */
function getPerAnchor() {
  const head = Math.max(perShell.querySelector('thead').getBoundingClientRect().bottom, 0);
  const row = [...perShell.querySelectorAll('tbody tr')].find((tr) => tr.getBoundingClientRect().bottom > head);
  return row ? { idx: 20702 - Number(row.cells[0].textContent), top: row.getBoundingClientRect().top } : null;
}

/* A new number keeps the reader on the same row: its page is floor(row / number) + 1, and the row stays where it stood in the window */
function setPerLimit(v, quiet) {
  const was = perState.own;
  const at = getPerAnchor();
  const first = at ? at.idx : (perState.page - 1) * getPerLimit();
  perState.own = v === PER_ALL ? PER_ALL : v ? Math.max(5, Math.min(PER_MAX, Math.round(v))) : 0;
  if (perState.own === was) return;
  setPerWrite(perState.own);
  perState.page = Math.floor(first / getPerLimit()) + 1;
  setPerDraw();
  const row = at ? perShell.querySelector(`tbody tr[data-sl-row="Комментарий ${20702 - at.idx}"]`) : null;
  if (row) window.scrollBy({ top: row.getBoundingClientRect().top - at.top, behavior: 'instant' });
  if (quiet || !window.setToast) return;
  const text = perState.own === PER_ALL ? `Теперь все строки на одной странице во всех списках до ${getPerWide(PER_MAX)} строк`
    : perState.own ? `Теперь по ${getPerWide(perState.own)} ${getPerWord(perState.own)} во всех списках панели`
      : `Снова по умолчанию модуля: по ${perState.def} ${getPerWord(perState.def)}`;
  window.setToast(text, { undo: () => setPerLimit(was, true) });
}

function setPerMenu(menu, open) {
  menu.classList.toggle('sl-is-open', open);
  menu.querySelector('[data-per-menu-toggle]').setAttribute('aria-expanded', String(open));
  menu.querySelector('[data-per-menu-panel]').hidden = !open;
  if (open) (menu.querySelector('[data-per-menu-panel] .sl-is-active') || menu.querySelector('[data-per-menu-panel] button:not(:disabled)')).focus();
}

function setPerDesk() {
  const desk = document.querySelector('.tb-desk');
  const mods = PER_MODS.map(([n, name]) => `<button type="button" class="sl-but" data-per-mod="${n}" aria-pressed="false">${n} · ${name}</button>`).join('');
  const counts = PER_COUNTS.map((n) => `<button type="button" class="sl-but" data-per-count="${n}" aria-pressed="false">${getPerWide(n)}</button>`).join('');
  desk.innerHTML = `<span class="tb-desk-cap">Пульт стенда</span>
    <span class="pp-desk-row">Умолчание списка из «Конфигураций»: ${mods}</span>
    <span class="pp-desk-row">Строк в фильтре: ${counts}</span>
    <span class="pp-desk-row">Личный выбор: <b data-per-own-text></b> <button type="button" class="sl-but" data-per-reset>Сбросить</button></span>
    <span class="tb-desk-hint">Один личный выбор на все списки панели. На стенде он живёт в localStorage и общий для всех вариантов
      серии; на живой панели — cookie через setAdminOption(), как вид списка.</span>`;
}

function initPer() {
  perShell = document.querySelector('[data-tb-shell]');
  perShell.classList.remove('tb-skin');
  perState.own = getPerRead();
  setPerDesk();
  perShell.querySelector('.sl-list-head-pager').innerHTML = getPerPager('top');
  perShell.querySelector('.sl-list-bottom-pager').innerHTML = getPerPager('bottom');
  // The toast of the theme, fragments/toast.html, so setToast() of slaed.js shows its own notice with Undo
  document.body.insertAdjacentHTML('beforeend', '<div class="sl-toast-host" aria-live="polite"><template data-sl-toast>' +
    '<div class="sl-alert sl-alert-success sl-alert-flash sl-toast"><div class="sl-alert-flash-bar"><svg aria-hidden="true" focusable="false">' +
    '<circle cx="50%" cy="50%" r="50%" pathLength="100"></circle></svg></div><div class="sl-alert-body"><span data-sl-toast-text></span>' +
    '<button type="button" class="sl-but" data-sl-undo hidden>Отменить</button><button type="button" class="sl-but-mini" data-sl-toast-close' +
    ' title="Закрыть" aria-label="Закрыть"><i class="bi bi-x-lg" aria-hidden="true"></i></button></div></div></template></div>');
  setPerFaces();
  setPerDraw();

  document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-per-menu-toggle]');
    document.querySelectorAll('[data-per-menu].sl-is-open').forEach((menu) => { if (!menu.contains(e.target)) setPerMenu(menu, false); });
    if (toggle) {
      const menu = toggle.closest('[data-per-menu]');
      setPerMenu(menu, !menu.classList.contains('sl-is-open'));
      return;
    }
    const apply = e.target.closest('[data-per-apply]');
    if (apply) {
      setPerOwn(apply.closest('[data-per-custom]').querySelector('input[data-per-input]'));
      return;
    }
    const page = e.target.closest('[data-per-page]');
    const set = e.target.closest('[data-per-set], [data-per-reset], [data-per-step], [data-per-mod], [data-per-count]');
    if (page) {
      e.preventDefault();
      const low = !!page.closest('[data-per-side="bottom"]');
      perState.page = Number(page.dataset.perPage);
      setPerDraw();
      if (low) perShell.scrollIntoView({ block: 'start' });
      return;
    }
    if (!set || set.disabled) return;
    const menu = set.closest('[data-per-menu]');
    if (set.hasAttribute('data-per-count')) {
      perState.total = Number(set.dataset.perCount);
      perState.page = 1;
      setPerDraw();
    } else if (set.hasAttribute('data-per-mod')) {
      perState.def = Number(set.dataset.perMod);
      perState.page = 1;
      setPerDraw();
    } else if (set.hasAttribute('data-per-reset')) {
      setPerLimit(0);
    } else if (set.hasAttribute('data-per-step')) {
      const limit = getPerLimit();
      const dir = Number(set.dataset.perStep);
      const next = dir > 0 ? PER_LADDER.find((n) => n > limit) : [...PER_LADDER].reverse().find((n) => n < limit);
      if (next) setPerLimit(next);
    } else {
      setPerLimit(set.dataset.perSet === 'all' ? PER_ALL : Number(set.dataset.perSet));
    }
    if (menu) {
      setPerMenu(menu, false);
      menu.querySelector('[data-per-menu-toggle]').focus();
    }
    // A pick by pointer leaves the float to the pointer, so it closes once the pointer leaves; a pick by key keeps the focus inside
    if (e.detail > 0 && set.closest('[data-per-float]')) set.blur();
  });

  document.addEventListener('change', (e) => {
    if (e.target.matches('select[data-per-select]')) setPerLimit(Number(e.target.value));
    if (e.target.matches('input[data-per-input]:not([data-per-input="custom"])') && e.target.value !== '') setPerLimit(Number(e.target.value));
    if (e.target.matches('input[data-per-scale]')) setPerLimit(PER_LADDER[Number(e.target.value)]);
  });

  // The tick applies without taking the focus from the field, so the field does not forget the number on its way to the tick
  document.addEventListener('mousedown', (e) => { if (e.target.closest('[data-per-apply]')) e.preventDefault(); });
  document.addEventListener('focusout', (e) => { if (e.target.matches('input[data-per-input="custom"]')) setPerField(e.target); });

  document.addEventListener('keydown', (e) => {
    const float = e.target.closest ? e.target.closest('[data-per-float]') : null;
    const menu = float || (e.target.closest ? e.target.closest('[data-per-menu].sl-is-open') : null);
    if (e.key === 'Enter' && e.target.matches('input[data-per-input]')) {
      e.preventDefault();
      setPerOwn(e.target);
      return;
    }
    if (!menu) return;
    // Escape of a float is the system's: it closes the panel and hands the focus back to the trigger
    if (e.key === 'Escape' && float) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      setPerMenu(menu, false);
      menu.querySelector('[data-per-menu-toggle]').focus();
      return;
    }
    if (!['ArrowDown', 'ArrowUp', 'ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
    // In a field left, right, Home and End move the caret; only up and down leave it for the next row
    if (e.target.matches('input') && !['ArrowDown', 'ArrowUp'].includes(e.key)) return;
    const list = [...menu.querySelectorAll('[data-per-menu-panel] button:not(:disabled), .sl-float-panel :is(button:not(:disabled), input[data-per-input])')];
    const at = list.indexOf(document.activeElement);
    // On the trigger of a float only up and down enter the panel; left and right stay the keys of the pager
    if (at < 0 && float && !['ArrowDown', 'ArrowUp'].includes(e.key)) return;
    // A float shut with Escape opens again the way the pointer opens it, so the focus has a visible row to go to
    if (at < 0 && float && float.hasAttribute('data-sl-float-shut')) float.dispatchEvent(new MouseEvent('mouseenter'));
    const step = ['ArrowDown', 'ArrowRight'].includes(e.key) ? 1 : -1;
    const here = Math.max(0, list.findIndex((one) => one.classList.contains('sl-is-active')));
    const next = at < 0 ? here : e.key === 'Home' ? 0 : e.key === 'End' ? list.length - 1 : (at + step + list.length) % list.length;
    e.preventDefault();
    list[next]?.focus();
  }, true);
}

initPer();
