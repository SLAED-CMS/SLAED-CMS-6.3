/* The comments reference: the panel part rides on the theme machinery and moves into admin-ui.js, the stand part stays */
(function () {
  'use strict';

  const doc = document;
  const root = doc.documentElement;
  const TOTAL = 8414;
  const wide = window.matchMedia('(width > 900px)');
  const state = { mod: '', field: 'text', q: '', view: 'all', allPages: false, fail: false, down: false, cursor: -1, restoring: false, unseen: 0 };
  let rows = [];
  let conf = null;

  // Storage for per-viewer choices; a browser that refuses it keeps the defaults
  function getKept(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
  }

  // Remember a per-viewer choice
  function setKept(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* storage may be off */ }
  }

  // A number the way the page writes it
  function getNum(n) {
    return Number(n).toLocaleString('ru-RU');
  }

  // Escape a text for markup built here
  function getEsc(text) {
    return String(text).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  // Open or close a theme window, with the native dialog as the fallback
  function setWindow(box, open) {
    if (!box) return;
    if (open) (window.setWindowOpen || ((d) => d.showModal()))(box);
    else (window.setWindowClose || ((d) => d.close()))(box);
  }

  // Say a result to a screen reader through the live line of the list
  function setStatus(text) {
    doc.querySelectorAll('[data-sl-status]').forEach((n) => { n.textContent = text; });
  }

  /* ---------- Shell ---------- */

  // Э02 Д8 The browser tab names the section, the open view of it, the panel and the site; queue arrivals not yet seen go first
  function setTitle() {
    const tab = doc.querySelector('[data-sl-view="' + state.view + '"]');
    const parts = [doc.body.dataset.slSection, tab ? tab.dataset.slViewName : '', 'Админ', doc.body.dataset.slSite];
    doc.title = (state.unseen ? '(' + state.unseen + ') ' : '') + parts.filter(Boolean).join(' — ');
  }

  // Д4 One engine for every sound of the system; a page without it stays silent
  function setSound(id) {
    if (window.slSound) window.slSound.setPlay(id);
  }

  // Д4 The sound switch of Options, kept next to the motion; turning it on answers with the success tone
  function setSoundOn(on, keep = true) {
    const snd = window.slSound;
    if (!snd) return;
    if (keep) {
      snd.conf.mute = !on;
      snd.setSave();
      snd.setVolume();
      if (on) setSound('alOk');
    }
    doc.querySelectorAll('#sl-o-sound').forEach((s) => { s.value = snd.conf.mute ? '0' : '1'; });
    if (setSoundOn.onset) setSoundOn.onset(!snd.conf.mute);
  }

  // В08.8 Д7 The question before a change is lost comes with the warning tone
  function setAsk(text, run) {
    setSound('alWarn');
    window.setConfirmTask(text, run);
  }

  // Д8 The tab is seen again: the number of new arrivals leaves its title
  function setSeen() {
    if (doc.hidden || !state.unseen) return;
    state.unseen = 0;
    setTitle();
  }

  // Д8 A comment for the queue arrives: the row enters, the bell counts and stirs, the title counts while unseen, the ear gets a low tone
  function setQueueIn(row) {
    if (row) {
      const held = doc.querySelector('[data-sl-row][data-sl-cursor]');
      const errs = [...doc.querySelectorAll('[data-sl-error]')].filter((n) => !n.hidden);
      rows = [...doc.querySelectorAll('[data-sl-row]')];
      setList(true);
      errs.forEach((n) => { n.hidden = false; });
      if (held && !held.hidden) {
        state.cursor = getShown().indexOf(held);
        held.setAttribute('data-sl-cursor', '');
      }
      if (!row.hidden) {
        row.style.setProperty('--sl-d-i', '0');
        row.setAttribute('data-sl-enter', '');
      }
    } else {
      doc.querySelectorAll('[data-sl-bell-own]').forEach((n) => { n.textContent = String((Number(n.textContent) || 0) + 1); });
      setBell();
    }
    doc.querySelectorAll('[data-sl-bell]').forEach((b) => {
      b.removeAttribute('data-sl-ping');
      void b.offsetWidth;
      b.setAttribute('data-sl-ping', '');
    });
    setStatus('Новый комментарий ждёт проверки.');
    if (doc.hidden || !doc.hasFocus()) {
      state.unseen += 1;
      setTitle();
    }
    setSound('mod');
  }

  // Э12 The drawer of a phone: opened by the menu button, closed by its cross, the scrim or Escape; the focus comes back to the button
  function setDrawer(open) {
    const btn = doc.querySelector('[data-sl-menu-open]');
    const menu = doc.querySelector('[data-sl-menu]');
    doc.body.toggleAttribute('data-sl-drawer', open);
    if (btn) btn.setAttribute('aria-expanded', String(open));
    if (open) (menu.querySelector('[data-sl-menu-find]') || menu.querySelector('a, button')).focus();
    else if (btn && menu.contains(doc.activeElement)) btn.focus();
  }

  // Э07 В09.3 Every block shows its sections while a search narrows the menu or the rail is folded; after, each takes back its own state
  function setGroupsShown(force, sel) {
    doc.querySelectorAll(sel).forEach((box) => {
      if (force && !box.hasAttribute('data-sl-keep')) {
        box.setAttribute('data-sl-keep', JSON.stringify([box.hidden, box.style.display]));
        box.hidden = false;
        box.style.display = '';
      } else if (!force && box.hasAttribute('data-sl-keep')) {
        const [hid, disp] = JSON.parse(box.getAttribute('data-sl-keep'));
        box.removeAttribute('data-sl-keep');
        box.hidden = hid;
        box.style.display = disp;
      }
    });
  }

  // Э07 Г2 The find field narrows the sections by name and module word where they are listed: the menu of a page, the cards of the home
  function setMenuFind() {
    const find = doc.querySelector('[data-sl-menu-find]');
    const term = find ? find.value.trim().toLowerCase() : '';
    doc.querySelectorAll('[data-sl-menu], .sl-admin-content').forEach((scope) => {
      const groups = [...scope.querySelectorAll('[data-sl-find-group]')];
      let seen = 0;
      groups.forEach((group) => {
        let hits = 0;
        group.querySelectorAll('[data-sl-menu-item]').forEach((item) => {
          const hit = !term || (item.textContent + ' ' + item.dataset.slMenuItem).toLowerCase().includes(term);
          item.hidden = !hit;
          if (hit) hits += 1;
        });
        group.hidden = term !== '' && hits === 0;
        seen += hits;
      });
      if (groups.length && scope.matches('[data-sl-menu]')) scope.querySelectorAll('.sl-block-sidebar:not([data-sl-find-group])').forEach((n) => { n.hidden = term !== ''; });
      scope.querySelectorAll('[data-sl-menu-empty]').forEach((n) => { n.hidden = !groups.length || seen > 0; });
    });
    setGroupsShown(term !== '' || (root.hasAttribute('data-sl-rail') && wide.matches), '[data-sl-menu] .sl-block-sidebar-content[data-sl-toggle]');
    setGroupsShown(term !== '', '.sl-dashboard-panel-content[data-sl-toggle]');
  }

  // В09.1 Fold the menu into the rail and back; the width moves only under a hand, the choice is kept
  function setRail(on, keep) {
    if (keep) {
      root.setAttribute('data-sl-rail-anim', '');
      setTimeout(() => root.removeAttribute('data-sl-rail-anim'), 400);
      setKept('sl.rail', on ? '1' : '0');
    }
    root.toggleAttribute('data-sl-rail', on);
    doc.querySelectorAll('[data-sl-rail-toggle]').forEach((b) => {
      const name = on ? 'Развернуть меню' : 'Свернуть меню';
      b.setAttribute('aria-expanded', String(!on));
      b.setAttribute('aria-label', name);
      b.title = name + ' ( [ )';
      b.querySelector('.bi').className = 'bi ' + (on ? 'bi-layout-sidebar' : 'bi-layout-sidebar-inset');
    });
    setMenuFind();
  }

  // В09.5 Arrows walk the icons of the rail as in a menu; Tab still passes through them in order
  function setRailKeys(e) {
    if (!root.hasAttribute('data-sl-rail') || !wide.matches || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp')) return;
    const parts = ['.sl-menu-tool', '[data-sl-menu-item] > a', '.sl-block-sidebar-count-label a', '.sl-menu-watch .sl-menu-head'];
    const its = [...doc.querySelectorAll(parts.map((x) => '[data-sl-menu] ' + x).join(', '))]
      .filter((n) => n.getClientRects().length);
    const at = its.indexOf(doc.activeElement);
    if (at < 0) return;
    e.preventDefault();
    its[(at + (e.key === 'ArrowDown' ? 1 : -1) + its.length) % its.length].focus();
  }

  // The comments of the list that wait for a check
  function getWaitCount() {
    return rows.filter((r) => r.dataset.on === '0' && !r.hasAttribute('data-sl-gone')).length;
  }

  // Э04 Г8 The bell sums every queue of the panel; the comments count is live, the others as the dashboard gives them
  function setBell() {
    const given = doc.querySelector('[data-sl-bell-own]');
    const own = rows.length ? getWaitCount() : Number(given ? given.textContent : 0) || 0;
    let sum = own;
    doc.querySelectorAll('[data-sl-bell-own]').forEach((n) => { n.textContent = String(own); n.closest('a').hidden = own === 0; });
    doc.querySelectorAll('[data-sl-bell-n]').forEach((n) => { sum += Number(n.textContent) || 0; });
    doc.querySelectorAll('[data-sl-bell]').forEach((b) => b.setAttribute('aria-label', 'Уведомления: ' + sum));
    doc.querySelectorAll('[data-sl-bell-sum]').forEach((n) => { n.textContent = String(sum); n.hidden = sum === 0; });
    doc.querySelectorAll('[data-sl-count-own]').forEach((n) => {
      n.textContent = String(own);
      n.classList.toggle('sl-chip-warn', own > 0);
      n.classList.toggle('sl-chip-success', own === 0);
    });
    doc.querySelectorAll('[data-sl-queue]').forEach((c) => {
      c.hidden = own === 0;
      c.querySelectorAll('[data-sl-queue-n]').forEach((x) => { x.textContent = String(own); });
    });
  }

  // В07.5 Still the page as the reduced-motion setting would, keep the choice and show it in Options; the motion switch of the stand calls the same
  function setMotion(on, keep = true) {
    root.toggleAttribute('data-sl-still', !on);
    if (keep) setKept('sl.motion', on ? '1' : '0');
    doc.querySelectorAll('#sl-o-motion').forEach((s) => { s.value = on ? '1' : '0'; });
    if (setMotion.onset) setMotion.onset(on);
    if (keep) setSaver();
  }

  // Д12 The rest of the hand before the screensaver, in ms; 0 keeps it off, one minute unless chosen
  function getSaverWait() {
    const wait = getKept('sl.saver');
    return Number(wait === null ? '60000' : wait);
  }

  // Д11 The Phoenix screensaver: while it is on, the badge and the quiet ground, the scene after the rest of the hand; off, still or framed — none
  function setSaver() {
    const phx = window.slPhoenix;
    const brand = doc.querySelector('.sl-brand');
    const framed = new URLSearchParams(location.search).has('phx');
    const token = setSaver.token = (setSaver.token || 0) + 1;
    if (setSaver.kit) setSaver.kit.reset();
    if (setSaver.busy && setSaver.onset) setSaver.onset(false);
    Object.assign(setSaver, { kit: null, ctl: null, idle: null, busy: false });
    if (framed || !phx || !brand) return;
    doc.querySelectorAll('.phx-badge').forEach((n) => n.remove());
    root.classList.remove('phx-lit', 'phx-crown');
    if (!getSaverWait()) {
      delete root.dataset.phx;
      return;
    }
    root.dataset.phx = 'panel';
    root.classList.add('phx-lit', 'phx-crown');
    brand.insertAdjacentHTML('beforeend', '<span class="phx-badge" aria-hidden="true"><b>8.0</b> Phoenix</span>');
    const kit = phx.getKit(window);
    setSaver.kit = kit;
    if (kit.still) return;
    phx.getScene(kit).then((ctl) => {
      if (token !== setSaver.token) return;
      setSaver.ctl = ctl;
      setSaver.idle = phx.setIdle(kit, ctl, () => (doc.querySelector('dialog:modal') ? 0 : getSaverWait()));
      ctl.onframe = () => {
        if (ctl.busy === setSaver.busy) return;
        setSaver.busy = ctl.busy;
        if (setSaver.onset) setSaver.onset(ctl.busy);
      };
    }, () => kit.reset());
  }

  // Д11 Start the screensaver now, or say why it cannot start
  function setSaverNow() {
    if (setSaver.idle) { setSaver.idle.sleep(); return; }
    if (!getSaverWait()) setToast('Заставка выключена: выберите время в «Опциях» → «Заставка».', null);
    else if (setSaver.kit && setSaver.kit.still) setToast('Движение выключено: заставки нет, только значок «8.0 Phoenix».', null);
  }

  // Э06 Д4 Д12 Options apply on change without a reload; the colour mode, the motion, the sound and the screensaver take effect at once
  function setOption(sel) {
    if (sel.name === 'mode') {
      root.dataset.theme = sel.value;
      setKept('sl.mode', sel.value);
      setSaver();
    }
    if (sel.name === 'saver') {
      setKept('sl.saver', sel.value);
      setSaver();
    }
    if (sel.name === 'motion') setMotion(sel.value === '1');
    if (sel.name === 'sound') setSoundOn(sel.value === '1');
    // М10 The floating menu: in the panel at once; the row of the site is kept for the site, which this stand does not draw
    if (sel.name === 'dock') setDockOn(sel.value === '1');
    if (sel.name === 'dock_site') setKept('sl.dock.site', sel.value);
    if (sel.name === 'dock_side') setDockSide(sel.value);
  }

  // Э14 Д7 The end of a session: a countdown before the work is lost, a way to keep it, the warning tone
  function setSessionEnd() {
    const box = doc.getElementById('sl-session');
    let left = 120;
    const draw = () => box.querySelectorAll('[data-sl-sess-left]').forEach((n) => { n.textContent = Math.floor(left / 60) + ':' + String(left % 60).padStart(2, '0'); });
    draw();
    clearInterval(setSessionEnd.tick);
    setSessionEnd.tick = setInterval(() => { left -= 1; draw(); if (left <= 0) clearInterval(setSessionEnd.tick); }, 1000);
    setWindow(box, true);
    setSound('alWarn');
  }

  /* ---------- Э08 Ctrl+K: sections, actions of the screen and settings in one palette of the theme ---------- */

  const cmds = [];

  // Fill the palette from the menu, the screen and the form, one group each
  function setPalette() {
    const list = doc.querySelector('[data-sl-pal-list]');
    if (!list) return;
    cmds.length = 0;
    const add = (group, icon, label, run, keys) => cmds.push({ group, icon, label, run, keys: keys || '' });
    const onList = (tab, run) => () => (doc.querySelector('[data-sl-list]') ? run() : location.assign('?name=comments' + (tab ? '&tab=' + tab : '')));
    add('Действия', 'list-ul', 'Все комментарии', onList('', () => setView('all')));
    add('Действия', 'hourglass-split', 'Ждут проверки', onList('wait', () => setView('wait')));
    add('Действия', 'gear', 'Конфигурации', onList('config', () => setView('config')));
    add('Действия', 'funnel', 'Сбросить фильтры', () => { state.mod = ''; state.q = ''; doc.querySelectorAll('[data-sl-q]').forEach((q) => { q.value = ''; }); setList(); });
    add('Действия', 'check2-square', 'Отметить все показанные', onList('', setShownPicked));
    add('Действия', 'layout-sidebar', 'Свернуть или развернуть меню', () => setRail(!root.hasAttribute('data-sl-rail'), true), '<kbd>[</kbd>');
    add('Действия', 'moon', 'Тёмная тема', () => setModeTo('dark'));
    add('Действия', 'sun', 'Светлая тема', () => setModeTo('light'));
    add('Действия', 'sliders', 'Опции', () => setWindow(doc.getElementById('sl-settings'), true));
    doc.querySelectorAll('[data-sl-menu-item] > a').forEach((a) => add('Разделы', a.querySelector('.bi').className.replace('bi bi-', ''),
      a.querySelector('.sl-menu-name').textContent, () => { location.href = a.href; }));
    doc.querySelectorAll('[data-sl-conf-item]').forEach((it) => add('Настройки', 'gear', getConfLabel(it), () => setConfJump(getConfField(it).id)));
    add('Стенд', 'wifi-off', 'Смоделировать сбой сети', () => { state.fail = true; setStatus('Следующий запрос списка не дойдёт до сервера.'); });
    add('Стенд', 'hourglass-bottom', 'Смоделировать конец сессии', () => setSessionEnd());
    add('Стенд', 'inbox', 'Смоделировать новый комментарий', () => setDemoArrive());
    add('Стенд', 'fire', 'Запустить заставку', () => setSaverNow());
    const groups = [...new Set(cmds.map((c) => c.group))];
    const row = (c, i) => '<button type="button" role="option" tabindex="-1" aria-selected="false" class="sl-editor-pal-row" id="sl-pal-' + i + '" data-cmd="' + i + '">'
      + '<i class="bi bi-' + c.icon + '" aria-hidden="true"></i><span class="sl-editor-pal-name">' + getEsc(c.label) + '</span>'
      + '<span class="sl-editor-pal-keys">' + c.keys + '</span></button>';
    list.innerHTML = groups.map((g, gi) => '<div class="sl-editor-pal-group" role="group" aria-labelledby="sl-pal-g' + gi + '">'
      + '<div class="sl-editor-pal-cap" id="sl-pal-g' + gi + '">' + g + '</div>' + cmds.map((c, i) => (c.group === g ? row(c, i) : '')).join('') + '</div>').join('');
  }

  // Э24 Check every row on screen, as the head box does
  function setShownPicked() {
    setView('all', true);
    getShown().forEach((r) => { r.querySelector('[data-sl-check-item]').checked = true; });
    setPicked();
  }

  // The colour mode from the palette goes through the same list as Options, so both say the same
  function setModeTo(mode) {
    const sel = doc.getElementById('sl-o-mode');
    sel.value = mode;
    setOption(sel);
  }

  // The rows the palette search leaves
  function getPalShown() {
    return [...doc.querySelectorAll('[data-sl-pal-list] [role="option"]')].filter((o) => !o.hidden);
  }

  // One option is chosen among the shown ones; the focus stays in the search above it
  function setPalAt(i) {
    const list = getPalShown();
    const q = doc.querySelector('[data-sl-pal-q]');
    doc.querySelectorAll('[data-sl-pal-list] [role="option"]').forEach((o) => o.setAttribute('aria-selected', 'false'));
    if (!list.length) { q.removeAttribute('aria-activedescendant'); return; }
    setPalAt.at = (i + list.length) % list.length;
    list[setPalAt.at].setAttribute('aria-selected', 'true');
    q.setAttribute('aria-activedescendant', list[setPalAt.at].id);
    list[setPalAt.at].scrollIntoView({ block: 'nearest' });
  }

  // The palette search narrows the rows and drops the groups it empties
  function setPalFind() {
    const term = doc.querySelector('[data-sl-pal-q]').value.trim().toLowerCase();
    doc.querySelectorAll('[data-sl-pal-list] [role="option"]').forEach((o) => { o.hidden = term !== '' && !o.textContent.toLowerCase().includes(term); });
    doc.querySelectorAll('[data-sl-pal-list] .sl-editor-pal-group').forEach((g) => { g.hidden = !g.querySelector('[role="option"]:not([hidden])'); });
    doc.querySelectorAll('[data-sl-pal-empty]').forEach((n) => { n.hidden = getPalShown().length > 0; });
    setPalAt(0);
  }

  // Run a command of the palette after the palette has gone
  function setPalRun(opt) {
    setWindow(doc.getElementById('sl-palette'), false);
    cmds[Number(opt.dataset.cmd)].run();
  }

  // Open the palette on an empty search
  function setPalOpen() {
    const q = doc.querySelector('[data-sl-pal-q]');
    q.value = '';
    setPalFind();
    setWindow(doc.getElementById('sl-palette'), true);
    q.focus();
  }

  /* ---------- The list ---------- */

  // The rows a filter leaves on screen, in the order the sort put them
  function getShown() {
    return [...doc.querySelectorAll('[data-sl-row]')].filter((r) => !r.hidden);
  }

  // The rows the open view holds before any filter: later pages show only where the queue brings them
  function getInView() {
    return rows.filter((r) => !r.hasAttribute('data-sl-gone') && (state.view === 'wait' ? r.dataset.on === '0' : !r.hasAttribute('data-sl-far')));
  }

  // The name of a module as the filter lists it
  function getModName(key) {
    const opt = [...doc.querySelectorAll('[data-sl-mod] option')].find((o) => o.value === key);
    return opt ? opt.text : key;
  }

  // Э24 Э25 Э26 The pick: the head box, the count, the OK and, once the whole page is checked, the offer of the whole list
  function setPicked() {
    const shown = getShown();
    rows.filter((r) => r.hidden).forEach((r) => { r.querySelector('[data-sl-check-item]').checked = false; });
    const on = shown.filter((r) => r.querySelector('[data-sl-check-item]').checked).length;
    const all = doc.querySelector('[data-sl-check-all] input');
    if (all) {
      all.checked = on > 0 && on === shown.length;
      all.indeterminate = on > 0 && on < shown.length;
    }
    const pages = state.view === 'all' && !state.mod && !state.q && shown.length > 0 && on === shown.length;
    if (!pages) state.allPages = false;
    doc.querySelectorAll('[data-sl-allpages]').forEach((b) => {
      b.hidden = !pages;
      b.querySelector('[data-sl-allpages-text]').textContent = state.allPages ? 'Отмечены все ' + getNum(TOTAL) + ' комментариев.' : 'Отмечены все ' + on + ' на этой странице.';
      b.querySelector('[data-sl-allpages-go]').hidden = state.allPages;
      b.querySelector('[data-sl-allpages-off]').hidden = !state.allPages;
    });
    doc.body.toggleAttribute('data-sl-picking', on > 0);
    doc.querySelectorAll('[data-sl-picked]').forEach((n) => { n.textContent = 'Отмечено: ' + (state.allPages ? getNum(TOTAL) : on); });
    doc.querySelectorAll('[data-sl-act-go]').forEach((b) => { b.disabled = on === 0; });
    rows.forEach((r) => r.toggleAttribute('data-sl-checked', r.querySelector('[data-sl-check-item]').checked));
  }

  // Э15 Э17 Э28 В07.1 Д6 Draw the list for the filter and the view; rows a filter brings enter, rows an action touched stay put
  function setList(quiet, stay) {
    const table = doc.querySelector('[data-sl-list]');
    if (!table) return;
    doc.querySelectorAll('[data-sl-error]').forEach((n) => { n.hidden = true; });
    if (!quiet && (state.fail || state.down)) {
      state.fail = false;
      doc.querySelectorAll('[data-sl-error]').forEach((n) => { n.hidden = false; n.focus(); });
      setSound('alErr');
      return;
    }
    if (!quiet) {
      table.setAttribute('aria-busy', 'true');
      doc.querySelectorAll('[data-sl-busy]').forEach((n) => { n.hidden = false; });
      clearTimeout(setList.busy);
      setList.busy = setTimeout(() => {
        table.setAttribute('aria-busy', 'false');
        doc.querySelectorAll('[data-sl-busy]').forEach((n) => { n.hidden = true; });
      }, 320);
    }
    const base = getInView();
    let shown = 0;
    rows.forEach((r) => {
      const hay = { id: r.dataset.id, by: r.dataset.by, module: r.dataset.modname, ip: r.dataset.ip }[state.field] || r.dataset.text;
      const ok = base.includes(r) && (!state.mod || r.dataset.mod === state.mod) && (!state.q || hay.toLowerCase().includes(state.q.toLowerCase()));
      r.hidden = !ok;
      if (ok) shown += 1;
    });
    if (!quiet && !stay) setRowsEnter(table);
    setFound();
    doc.querySelectorAll('[data-sl-mod]').forEach((s) => { s.value = state.mod; });
    const calm = state.view === 'wait' && !state.mod && !state.q;
    doc.querySelectorAll('[data-sl-none]').forEach((n) => {
      n.hidden = shown > 0;
      n.cells[0].toggleAttribute('data-calm', calm);
      n.cells[0].textContent = calm ? 'Всё проверено: комментариев, ждущих проверки, нет.' : 'Комментариев не найдено. Измените модуль или текст поиска.';
    });
    const bare = state.view === 'wait' || Boolean(state.mod || state.q);
    doc.querySelectorAll('[data-sl-pager]').forEach((p) => {
      p.querySelectorAll('.sl-pager-nav, .sl-pager-info, .sl-pager-sep, .sl-pager-side > a').forEach((n) => { n.hidden = bare; });
    });
    if (!quiet) {
      const mod = state.mod ? ' модуля «' + getModName(state.mod) + '»' : '';
      setStatus('Показаны ' + shown + ' из ' + base.length + mod + (state.view === 'wait' ? ', ждущих проверки' : '') + (state.q ? ', найдено по «' + state.q + '»' : '') + '.');
    }
    state.cursor = -1;
    rows.forEach((r) => r.removeAttribute('data-sl-cursor'));
    setPicked();
    setBell();
    setUrl();
  }

  // Д2 Mark what the search found in the number, the text, the author and the module, as filterTextHighlight() does
  function setFound() {
    const words = state.q.split(/\s+/).filter(Boolean).sort((a, b) => b.length - a.length).map((w) => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
    const re = words.length ? new RegExp('(' + words.join('|') + ')', 'iu') : null;
    doc.querySelectorAll('[data-sl-row] [data-sl-hl]').forEach((n) => {
      if (n.dataset.slRaw === undefined) n.dataset.slRaw = n.textContent;
      const raw = n.dataset.slRaw;
      if (!re) { n.textContent = raw; return; }
      n.innerHTML = raw.split(new RegExp(re.source, 'giu')).map((p, i) => (i % 2 ? '<span class="sl-highlight">' + getEsc(p) + '</span>' : getEsc(p))).join('');
    });
  }

  // В07.1 Mark the rows on screen to enter, each with its place in the order for the cascade
  function setRowsEnter(table) {
    const list = getShown();
    list.forEach((r) => r.removeAttribute('data-sl-enter'));
    void table.offsetWidth;
    list.forEach((r, i) => {
      r.style.setProperty('--sl-d-i', String(i));
      r.setAttribute('data-sl-enter', '');
    });
  }

  // The pager over the list is the one under it; its way back to the top turns into the way down to the end of the list
  function setPagerCopy() {
    const from = doc.querySelector('.sl-list-bottom-pager .sl-pager');
    const box = doc.querySelector('[data-sl-pager-copy]');
    if (!from || !box) return;
    const copy = from.cloneNode(true);
    copy.querySelectorAll('a[href="#top"]').forEach((a) => {
      a.href = '#sl-list-end';
      a.title = 'Вниз';
      a.setAttribute('aria-label', 'Вниз');
      a.querySelector('.bi').className = 'bi bi-arrow-down';
    });
    copy.querySelectorAll('[data-mark]').forEach((n) => n.removeAttribute('data-mark'));
    copy.setAttribute('aria-label', 'Страницы, над списком');
    box.append(copy);
  }

  // Э16 The filter and the view live in the address: Back returns them, the address can be sent, an edit comes back to it
  function setUrl() {
    const url = new URL(location.href);
    const pairs = [['mod', state.mod], ['q', state.q], ['in', state.field === 'text' ? '' : state.field], ['tab', state.view === 'all' ? '' : state.view]];
    pairs.forEach(([k, v]) => { if (v) url.searchParams.set(k, v); else url.searchParams.delete(k); });
    if (!state.restoring && url.href !== location.href) history.pushState(null, '', url);
    const keep = new URLSearchParams(url.search);
    keep.delete('name');
    const back = encodeURIComponent('admin.php?name=comments' + (keep.toString() ? '&' + keep : ''));
    doc.querySelectorAll('[data-sl-edit]').forEach((a) => { a.href = '../../admin.php?name=comments&op=edit&id=' + a.closest('[data-sl-row]').dataset.id + '&back=' + back; });
  }

  // В08.8 Back or Forward away from a changed form asks first; a refusal puts the address of the form back
  function setBackGuard() {
    const to = new URL(location.href).searchParams.get('tab');
    if (state.view !== 'config' || to === 'config' || !conf || !conf.isDirty()) { setFromUrl(); return; }
    const away = location.href;
    setUrl();
    setAsk('Изменения конфигурации не сохранены. Уйти без сохранения?', () => {
      conf.setClean(true);
      history.pushState(null, '', away);
      setFromUrl();
    });
  }

  // Read the filter and the view back from the address: on load and on Back or Forward
  function setFromUrl() {
    const p = new URL(location.href).searchParams;
    state.mod = p.get('mod') || '';
    state.q = p.get('q') || '';
    state.field = ['id', 'by', 'module', 'ip'].includes(p.get('in')) ? p.get('in') : 'text';
    doc.querySelectorAll('[data-sl-field]').forEach((f) => { f.value = state.field; });
    doc.querySelectorAll('[data-sl-q]').forEach((f) => { f.value = state.q; });
    state.restoring = true;
    setView(['wait', 'config'].includes(p.get('tab')) ? p.get('tab') : 'all', true);
    state.restoring = false;
  }

  // Э17 В08.8 Switch the view in place; leaving a changed form asks first
  function setView(view, force) {
    if (!force && state.view === 'config' && view !== 'config' && conf && conf.isDirty()) {
      setAsk('Изменения конфигурации не сохранены. Уйти без сохранения?', () => { conf.setClean(true); setView(view, true); });
      return;
    }
    if (view !== state.view && !state.restoring) {
      doc.querySelectorAll('.sl-module-head-icon .bi').forEach((i) => i.getAnimations().forEach((a) => { a.cancel(); a.play(); }));
    }
    state.view = view;
    doc.querySelectorAll('[data-sl-view]').forEach((t) => {
      const on = t.dataset.slView === view;
      t.classList.toggle('sl-is-active', on);
      if (on) t.setAttribute('aria-current', 'page');
      else t.removeAttribute('aria-current');
    });
    doc.querySelectorAll('[data-sl-for-list]').forEach((n) => { n.hidden = view === 'config'; });
    doc.querySelectorAll('[data-sl-for-config]').forEach((n) => { n.hidden = view !== 'config'; });
    setTitle();
    if (view === 'config') setUrl();
    else setList(state.restoring);
  }

  // Э22 The status of a row as its button tells it
  function setRowOn(r, on) {
    const b = r.querySelector('[data-sl-stat]');
    r.dataset.on = on ? '1' : '0';
    b.setAttribute('aria-pressed', String(on));
    b.setAttribute('aria-label', 'Комментарий ' + r.dataset.id + ': ' + (on ? 'активирован, нажмите, чтобы деактивировать' : 'ждёт проверки, нажмите, чтобы активировать'));
    b.title = on ? 'Активирован — деактивировать' : 'Ждёт проверки — активировать';
    b.firstElementChild.className = on ? 'sl-status-active' : 'sl-status-inactive';
  }

  // Э23 A notice in the theme's flash alert, its ring counting the ten seconds an Undo stays offered
  function setToast(text, undo) {
    const host = doc.querySelector('[data-sl-toast-host]');
    const tpl = doc.getElementById('sl-toast-tpl');
    if (!host || !tpl) return;
    host.replaceChildren(tpl.content.cloneNode(true));
    const box = host.firstElementChild;
    box.querySelector('[data-sl-toast-text]').textContent = text;
    box.querySelector('[data-sl-undo]').hidden = !undo;
    const drop = () => {
      box.classList.add('sl-is-hiding');
      setTimeout(() => box.remove(), 400);
    };
    box.querySelector('[data-sl-undo]').addEventListener('click', () => { drop(); undo(); });
    box.querySelector('[data-sl-toast-close]').addEventListener('click', drop);
    setTimeout(drop, 10000);
  }

  // Э22 Э23 Э29 Д5 Д9 Act on rows at once, without a question: the focus moves on, the change can be undone, an emptied queue sounds a win
  function setAct(list, act) {
    if (!list.length) return;
    const all = state.allPages;
    const held = doc.activeElement && doc.activeElement.closest ? doc.activeElement.closest('[data-sl-row]') : null;
    const was = list.map((r) => [r, r.dataset.on, r.hasAttribute('data-sl-gone')]);
    const waits = getWaitCount();
    list.forEach((r) => {
      if (act === 'del') r.setAttribute('data-sl-gone', '');
      else setRowOn(r, act === 'on');
      r.querySelector('[data-sl-check-item]').checked = false;
    });
    state.allPages = false;
    setList(false, true);
    if (held && held.hidden) {
      const left = getShown();
      const next = left.find((r) => held.compareDocumentPosition(r) & Node.DOCUMENT_POSITION_FOLLOWING) || left[left.length - 1];
      if (next) next.querySelector('[data-sl-check-item]').focus();
    }
    const word = { del: 'Удалено', on: 'Активировано', off: 'Деактивировано' }[act];
    const what = all ? getNum(TOTAL) + ' (все страницы)' : (list.length === 1 ? '№ ' + list[0].dataset.id : String(list.length));
    setToast((list.length === 1 && !all ? 'Комментарий ' : 'Комментариев: ') + what + ' — ' + word.toLowerCase() + '.', () => {
      was.forEach(([r, on, gone]) => { setRowOn(r, on === '1'); r.toggleAttribute('data-sl-gone', gone); });
      setList(false, true);
    });
    setSound(waits > 0 && getWaitCount() === 0 ? 'win' : 'alOk');
  }

  // Н6 Д5 A section is switched off or on at once, without a question, and can be switched back for ten seconds
  function setSectionOn(btn) {
    const item = btn.closest('[data-sl-menu-item]');
    const off = !item.hasAttribute('data-sl-off');
    const name = item.querySelector('.sl-menu-name').textContent;
    const draw = (now) => {
      item.toggleAttribute('data-sl-off', now);
      item.classList.toggle('sl-mcard-off', now && item.classList.contains('sl-mcard'));
      item.querySelectorAll('.sl-led').forEach((l) => {
        l.classList.toggle('sl-led-ok', !now);
        l.classList.toggle('sl-led-off', now);
        l.title = now ? 'Деактивирован' : 'Активирован';
      });
      btn.title = now ? 'Активировать' : 'Деактивировать';
    };
    draw(off);
    setToast('Раздел «' + name + '» ' + (off ? 'деактивирован' : 'активирован') + '.', () => draw(!off));
    setSound('alOk');
  }

  // Н4 Refresh the counters of the watch in place; the stand has no live sessions, so the figures stay
  function setWatchFresh() {
    const box = doc.querySelector('[data-sl-sess-box]');
    box.setAttribute('aria-busy', 'true');
    setTimeout(() => box.setAttribute('aria-busy', 'false'), 320);
  }

  // Э27 J and K walk the rows: the row in hand is outlined and takes the focus
  function setCursor(i) {
    const list = getShown();
    if (!list.length) return;
    state.cursor = Math.max(0, Math.min(list.length - 1, i));
    rows.forEach((r) => r.removeAttribute('data-sl-cursor'));
    const r = list[state.cursor];
    r.setAttribute('data-sl-cursor', '');
    r.tabIndex = -1;
    r.focus();
  }

  // В02.1 The view of the list: the theme's segmented switch, the knob follows the chosen cell, the choice is kept
  function setDensity(mode, keep) {
    const btns = [...doc.querySelectorAll('[data-sl-density]')];
    const table = doc.querySelector('[data-sl-list]');
    if (!table) return;
    if (!btns.some((b) => b.dataset.slDensity === mode)) mode = 'standard';
    btns.forEach((b) => {
      const on = b.dataset.slDensity === mode;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-pressed', String(on));
      b.tabIndex = on ? 0 : -1;
    });
    table.dataset.density = mode;
    if (keep) setKept('sl.density', mode);
  }

  // Э19 The sort the theme's Tablesort made is kept and said; on load it is put back
  function setSortKept() {
    const table = doc.querySelector('[data-sl-list]');
    if (!table) return;
    let keep = null;
    try { keep = JSON.parse(getKept('sl.sort') || 'null'); } catch (e) { keep = null; }
    const was = keep && table.tHead.rows[0].cells[keep.i];
    if (was && was.dataset.sortMethod !== 'none') {
      was.click();
      if (was.getAttribute('aria-sort') !== keep.dir) was.click();
    }
    table.addEventListener('afterSort', () => {
      const th = table.tHead.querySelector('th[aria-sort]');
      if (!th) return;
      setKept('sl.sort', JSON.stringify({ i: th.cellIndex, dir: th.getAttribute('aria-sort') }));
      setStatus('Сортировка: ' + th.textContent.trim() + ', ' + (th.getAttribute('aria-sort') === 'ascending' ? 'по возрастанию' : 'по убыванию') + '.');
    });
  }

  // Э20 A relative time on the screen, the exact moment in the title and the datetime
  function setAgo(scope = doc) {
    const rtf = new Intl.RelativeTimeFormat('ru', { numeric: 'auto' });
    const steps = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
    scope.querySelectorAll('time[data-sl-ago]').forEach((t) => {
      const sec = (Date.parse(t.dateTime) - Date.now()) / 1000;
      const [unit, size] = steps.find(([, s]) => Math.abs(sec) >= s) || steps[steps.length - 1];
      t.title = t.textContent;
      t.textContent = rtf.format(Math.round(sec / size), unit);
    });
  }

  /* ---------- В08 The config form ---------- */

  // The label of a setting, its own text without hints
  function getConfLabel(item) {
    return (item.querySelector('.sl-div-label label, .sl-div-label span[id]') || item).textContent.trim();
  }

  // The first control of a setting
  function getConfField(item) {
    return item.querySelector('input, select, textarea');
  }

  // В08.7 Open the form, light the setting and put the focus in it
  function setConfJump(id) {
    const field = doc.getElementById(id);
    if (!field) return;
    if (state.view !== 'config') setView('config');
    if (state.view !== 'config') return;
    const item = field.closest('[data-sl-conf-item]');
    item.removeAttribute('data-sl-found');
    void item.offsetWidth;
    item.setAttribute('data-sl-found', '');
    field.focus();
  }

  // В08.5 В08.6 В08.7 В08.8 Д5 Д6 Changes against the saved values, the dot of the tab, judgement after the first save, search, leave guard
  function setConfForm() {
    const form = doc.querySelector('[data-sl-conf]');
    if (!form) return null;
    const ctrls = [...form.elements].filter((c) => c.name);
    const snap = (c) => (c.type === 'radio' || c.type === 'checkbox' ? String(c.checked) : c.value);
    const saved = new Map(ctrls.map((c) => [c, snap(c)]));
    let judged = false;
    const isDirty = () => ctrls.some((c) => snap(c) !== saved.get(c));
    const setDot = () => doc.querySelectorAll('[data-sl-dirty-dot]').forEach((d) => { d.hidden = !isDirty(); });
    const getRule = (c) => {
      const v = c.value.trim();
      const name = getConfLabel(c.closest('[data-sl-conf-item]'));
      if (c.required && v === '') return 'Заполните поле «' + name + '».';
      if (c.type === 'number' && v !== '' && (!/^\d+$/.test(v) || Number(v) < Number(c.min || 0))) return 'Укажите целое число не меньше ' + (c.min || 0) + '.';
      return '';
    };
    const setErr = (c, msg) => {
      const p = doc.getElementById(c.id + '-err');
      if (!p) return;
      p.textContent = msg;
      p.hidden = !msg;
      c.toggleAttribute('aria-invalid', Boolean(msg));
      const ids = (c.getAttribute('aria-describedby') || '').split(' ').filter((x) => x && x !== p.id);
      if (msg) ids.push(p.id);
      if (ids.length) c.setAttribute('aria-describedby', ids.join(' '));
      else c.removeAttribute('aria-describedby');
    };
    const sum = doc.querySelector('[data-sl-conf-sum]');
    const setClean = (back) => {
      ctrls.forEach((c) => {
        if (back && (c.type === 'radio' || c.type === 'checkbox')) c.checked = saved.get(c) === 'true';
        else if (back) c.value = saved.get(c);
        else saved.set(c, snap(c));
        setErr(c, '');
      });
      sum.hidden = true;
      judged = false;
      setDot();
    };
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      judged = true;
      const bad = ctrls.filter((c) => c.type !== 'radio' && c.tagName !== 'SELECT').map((c) => [c, getRule(c)]).filter(([c, m]) => { setErr(c, m); return m; });
      if (bad.length) {
        sum.querySelector('[data-sl-conf-sum-cap]').textContent = 'Не сохранено, исправьте: ' + bad.length;
        sum.querySelector('[data-sl-conf-sum-list]').innerHTML = bad.map(([c, m]) => '<li><a href="#' + c.id + '" data-sl-conf-jump="' + c.id + '">'
          + getEsc(m) + '</a></li>').join('');
        sum.hidden = false;
        sum.focus();
        setSound('alErr');
        return;
      }
      setClean(false);
      setToast('Конфигурация сохранена.', null);
      setSound('alOk');
    });
    form.addEventListener('focusout', (e) => { if (judged && e.target.name && e.target.type !== 'radio') setErr(e.target, getRule(e.target)); });
    form.addEventListener('input', setDot);
    form.addEventListener('change', setDot);
    doc.addEventListener('click', (e) => {
      const j = e.target.closest('[data-sl-conf-jump]');
      if (j) { e.preventDefault(); setConfJump(j.dataset.slConfJump); }
    });
    window.addEventListener('beforeunload', (e) => {
      if (!isDirty()) return;
      e.preventDefault();
      e.returnValue = '';
    });
    doc.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a || a.getAttribute('href').startsWith('#') || a.target === '_blank' || a.hasAttribute('data-sl-view') || !isDirty()) return;
      e.preventDefault();
      setAsk('Изменения конфигурации не сохранены. Уйти без сохранения?', () => { setClean(true); location.href = a.href; });
    }, true);
    setConfFind();
    return { isDirty, setClean };
  }

  // В08.7 The settings search: hits by the name and the hint of a setting, walked by the arrows
  function setConfFind() {
    const find = doc.querySelector('[data-sl-conf-find]');
    const list = doc.querySelector('[data-sl-conf-hits]');
    if (!find || !list) return;
    const items = [...doc.querySelectorAll('[data-sl-conf-item]')];
    let at = -1;
    const draw = () => {
      const term = find.value.trim().toLowerCase();
      const hits = term.length < 2 ? [] : items.filter((it) => it.textContent.toLowerCase().includes(term)).slice(0, 8);
      list.innerHTML = hits.map((it, n) => '<li id="sl-conf-hit-' + n + '" role="option" aria-selected="false" data-sl-conf-jump="' + getConfField(it).id + '">'
        + getEsc(getConfLabel(it)) + '</li>').join('')
        || (term.length < 2 ? '' : '<li class="sl-conf-none">Настройка не найдена.</li>');
      list.hidden = list.innerHTML === '';
      find.setAttribute('aria-expanded', String(!list.hidden));
      find.removeAttribute('aria-activedescendant');
      at = -1;
    };
    find.addEventListener('input', draw);
    find.addEventListener('keydown', (e) => {
      const opts = [...list.querySelectorAll('[role="option"]')];
      if (e.key === 'Escape') { find.value = ''; draw(); return; }
      if (!opts.length) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        at = (at + (e.key === 'ArrowDown' ? 1 : -1) + opts.length) % opts.length;
        opts.forEach((o, n) => o.setAttribute('aria-selected', String(n === at)));
        find.setAttribute('aria-activedescendant', opts[at].id);
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        const id = opts[Math.max(0, at)].dataset.slConfJump;
        find.value = '';
        draw();
        setConfJump(id);
      }
    });
    list.addEventListener('click', () => { find.value = ''; setTimeout(draw, 0); });
  }

  /* ---------- Wiring ---------- */

  // Clicks of the whole screen, one listener
  function setClicks(e) {
    const t = e.target;
    if (!t.closest) return;
    if (t.closest('[data-sl-menu-open]')) { setDrawer(!doc.body.hasAttribute('data-sl-drawer')); return; }
    if (t.closest('[data-sl-menu-close]')) { setDrawer(false); return; }
    if (t.closest('[data-sl-rail-toggle]')) { setRail(!root.hasAttribute('data-sl-rail'), true); return; }
    if (t.closest('[data-sl-pal-open]')) { setPalOpen(); return; }
    const opt = t.closest('[data-sl-pal-list] [role="option"]');
    if (opt) { setPalRun(opt); return; }
    const view = t.closest('[data-sl-view]');
    if (view) { e.preventDefault(); setView(view.dataset.slView); return; }
    const go = t.closest('[data-sl-tab-go]');
    if (go && doc.querySelector('[data-sl-list]')) {
      e.preventDefault();
      const tip = go.closest('.sl-float');
      if (tip) {
        tip.classList.remove('sl-is-open');
        go.blur();
      }
      setView(go.dataset.slTabGo);
      return;
    }
    const stat = t.closest('[data-sl-stat]');
    if (stat) { setAct([stat.closest('[data-sl-row]')], stat.getAttribute('aria-pressed') === 'true' ? 'off' : 'on'); return; }
    const del = t.closest('[data-sl-del]');
    if (del) { setAct([del.closest('[data-sl-row]')], 'del'); return; }
    if (t.closest('[data-sl-act-go]')) { setAct(getShown().filter((r) => r.querySelector('[data-sl-check-item]').checked), doc.querySelector('[data-sl-act]').value); return; }
    if (t.closest('[data-sl-allpages-go]')) { state.allPages = true; setPicked(); return; }
    if (t.closest('[data-sl-allpages-off]')) { rows.forEach((r) => { r.querySelector('[data-sl-check-item]').checked = false; }); setPicked(); return; }
    if (t.closest('[data-sl-retry]')) { setList(); return; }
    const find = t.closest('[data-sl-find]');
    if (find) {
      const row = find.closest('[data-sl-row]');
      state.field = find.dataset.slFind;
      state.q = row.dataset[find.dataset.slFind];
      doc.querySelectorAll('[data-sl-field]').forEach((f) => { f.value = state.field; });
      doc.querySelectorAll('[data-sl-q]').forEach((f) => { f.value = state.q; });
      setList();
      return;
    }
    const sec = t.closest('[data-sl-section-off]');
    if (sec) { setSectionOn(sec); return; }
    if (t.closest('[data-sl-sess-refresh]')) { setWatchFresh(); return; }
    const dense = t.closest('[data-sl-density]');
    if (dense) { setDensity(dense.dataset.slDensity, true); return; }
    if (t.closest('[data-sl-sess-keep]')) {
      clearInterval(setSessionEnd.tick);
      setWindow(doc.getElementById('sl-session'), false);
      setToast('Сессия продлена на 30 минут.', null);
      setSound('alOk');
    }
  }

  // Keys of the whole screen: Ctrl+K, [ , the list keys and Escape for the drawer
  function setKeys(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); setPalOpen(); return; }
    if (e.key === 'Escape' && doc.body.hasAttribute('data-sl-drawer')) { setDrawer(false); return; }
    const t = e.target;
    if (e.ctrlKey || e.metaKey || e.altKey || (t.closest && t.closest('input, select, textarea, dialog, [contenteditable]'))) return;
    if (e.code === 'BracketLeft' && wide.matches) { e.preventDefault(); setRail(!root.hasAttribute('data-sl-rail'), true); return; }
    if (!doc.querySelector('[data-sl-list]')) {
      if (e.key === '/') { e.preventDefault(); doc.querySelector('[data-sl-menu-find]').focus(); }
      return;
    }
    if (state.view === 'config') {
      if (e.key === '/') { e.preventDefault(); doc.querySelector('[data-sl-conf-find]').focus(); }
      return;
    }
    const k = e.key.toLowerCase();
    const row = state.cursor >= 0 ? getShown()[state.cursor] : null;
    if (k === '/') { e.preventDefault(); doc.querySelector('[data-sl-q]').focus(); }
    else if (k === 'j' || k === 'о') { e.preventDefault(); setCursor(state.cursor + 1); }
    else if (k === 'k' || k === 'л') { e.preventDefault(); setCursor(state.cursor - 1); }
    else if ((k === 'x' || k === 'ч') && row) { e.preventDefault(); row.querySelector('[data-sl-check-item]').click(); }
    else if ((k === 'e' || k === 'у') && row) { e.preventDefault(); row.querySelector('[data-sl-edit]').click(); }
  }

  // В09.3 While the rail is folded its block heads only stand for their icons and never fold a block away
  function setRailGuard(e) {
    if (!root.hasAttribute('data-sl-rail') || !wide.matches || !e.target.closest || !e.target.closest('[data-sl-menu] [data-sl-toggle-control]')) return;
    if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
    e.preventDefault();
    e.stopPropagation();
  }

  // Bind the screen once the theme has bound its own parts
  function setPanel() {
    doc.addEventListener('click', setRailGuard, true);
    doc.addEventListener('keydown', setRailGuard, true);
    doc.addEventListener('click', setClicks);
    doc.addEventListener('click', setDockClicks);
    doc.addEventListener('keydown', setKeys);
    doc.addEventListener('keydown', setDockKeys);
    doc.addEventListener('keydown', setRailKeys);
    doc.querySelectorAll('[data-sl-menu-find]').forEach((f) => f.addEventListener('input', setMenuFind));
    doc.querySelectorAll('[data-sl-option]').forEach((s) => s.addEventListener('change', () => setOption(s)));
    doc.querySelectorAll('[data-sl-mod]').forEach((s) => s.addEventListener('change', () => { state.mod = s.value; setList(); }));
    doc.querySelectorAll('[data-sl-field]').forEach((s) => s.addEventListener('change', () => { state.field = s.value; if (state.q) setList(); }));
    doc.querySelectorAll('[data-sl-search]').forEach((f) => f.addEventListener('submit', (e) => {
      e.preventDefault();
      state.q = f.querySelector('[data-sl-q]').value.trim();
      setList();
    }));
    doc.addEventListener('change', (e) => { if (e.target.type === 'checkbox' && e.target.closest('[data-sl-list]')) setPicked(); });
    doc.querySelectorAll('[data-sl-density-group]').forEach((g) => g.addEventListener('keydown', (e) => {
      const step = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 }[e.key];
      if (!step) return;
      e.preventDefault();
      const btns = [...g.querySelectorAll('[data-sl-density]')];
      const next = btns[(btns.indexOf(doc.activeElement) + step + btns.length) % btns.length];
      setDensity(next.dataset.slDensity, true);
      next.focus();
    }));
    const pq = doc.querySelector('[data-sl-pal-q]');
    pq.addEventListener('input', setPalFind);
    pq.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); setPalAt((setPalAt.at || 0) + (e.key === 'ArrowDown' ? 1 : -1)); }
      if (e.key === 'Enter') { e.preventDefault(); const o = getPalShown()[setPalAt.at || 0]; if (o) setPalRun(o); }
      if (e.key === 'Escape') { e.preventDefault(); setWindow(doc.getElementById('sl-palette'), false); }
    });
    window.addEventListener('popstate', setBackGuard);
    doc.addEventListener('visibilitychange', setSeen);
    window.addEventListener('focus', setSeen);
    if (window.slSound) window.slSound.onload = () => setSoundOn(false, false);
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => { if ((root.dataset.theme || 'auto') === 'auto') setSaver(); });
    window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', setSaver);
    window.addEventListener('storage', (e) => {
      if (e.key !== 'sl.saver') return;
      doc.querySelectorAll('#sl-o-saver').forEach((s) => { s.value = String(getSaverWait()); });
      setSaver();
    });
    wide.addEventListener('change', setMenuFind);
    const mode = doc.getElementById('sl-o-mode');
    if (mode) mode.value = root.dataset.theme || 'auto';
    setMotion(!root.hasAttribute('data-sl-still'), false);
    setSoundOn(false, false);
    doc.querySelectorAll('#sl-o-saver').forEach((s) => { s.value = String(getSaverWait()); });
    conf = setConfForm();
    setPalette();
    setAgo();
    setDensity(getKept('sl.density'), false);
    setRail(root.hasAttribute('data-sl-rail'), false);
    setSortKept();
    setFromUrl();
    setList(true);
    setBell();
    setSaver();
    setDock();
    setDockOn(!root.hasAttribute('data-sl-dock-off'), false);
    doc.querySelectorAll('#sl-o-dock-site').forEach((s) => { s.value = getKept('sl.dock.site') === '0' ? '0' : '1'; });
    setDockSideMark();
    setDockPinMarks();
    setStats();
  }

  /* ---------- М1–М14 The floating menu (variant 09 of the admin block series) ---------- */

  const DOCK_PINS = ['blocks', 'comments', 'config', 'database', 'node', 'account'];
  const DOCK_MAX = 8;
  const DOCK_EDGE = 12;
  // М2 The sections take the semantic tones of the theme in turn; red stays for danger and the logout
  const DOCK_TONES = ['var(--sl-primary)', 'var(--sl-success)', 'var(--sl-accent)', 'var(--sl-warning)'];
  const DOCK_ICO = (name) => '<i class="bi bi-' + name + '" aria-hidden="true"></i>';

  function getDockPins() {
    try {
      const p = JSON.parse(getKept('sl.dock.pins') || 'null');
      return Array.isArray(p) ? p : DOCK_PINS.slice();
    } catch (e) { return DOCK_PINS.slice(); }
  }

  function getDockAt() {
    try {
      const a = JSON.parse(getKept('sl.dock.at') || 'null');
      return a && typeof a.x === 'number' ? a : null;
    } catch (e) { return null; }
  }

  // М10 The edge chosen in Options; the bottom is the first state
  function getDockSide() {
    const side = getKept('sl.dock.side');
    return ['top', 'right', 'left'].includes(side) ? side : 'bottom';
  }

  // Every section of the menu data by its key, with its href as the menu builds it and its tone by its place
  function getDockSections() {
    const menu = JSON.parse(doc.getElementById('demo-menu').textContent);
    const map = {};
    menu.admin.concat(menu.modules).forEach(([key, name, icon], i) => {
      map[key] = { key, name, icon, tone: DOCK_TONES[i % DOCK_TONES.length], href: key === 'comments' ? '?name=comments' : key === 'layout' ? '../sb-01-builder.html' : '../../admin.php?name=' + key };
    });
    return map;
  }

  // М2 М9 М11 М13 The inside of the pill: the mark, the pins, the quick jump, the watch, the owner's links, the admin, the logout
  function getDockNav() {
    const map = getDockSections();
    const page = root.dataset.demoPage;
    const app = (cls, href, tip, inner, extra = '') => '<a class="sl-dock-app ' + cls + '" href="' + href + '" data-tip="' + getEsc(tip) + '" aria-label="' + getEsc(tip) + '"'
      + extra + '>' + inner + '</a>';
    const btn = (cls, tip, inner, extra = '') => '<button type="button" class="sl-dock-app ' + cls + '" data-tip="' + getEsc(tip) + '" aria-label="' + getEsc(tip) + '"' + extra
      + '>' + inner + '</button>';
    const pins = getDockPins().filter((k) => map[k]).map((k) => {
      const s = map[k];
      const here = page === 'comments' && k === 'comments';
      return app('sl-dock-pin' + (here ? ' sl-dock-here' : ''), s.href, s.name + (here ? ' · вы здесь' : ''), DOCK_ICO(s.icon), ' style="--sl-dock-c: ' + s.tone + '"' + (here
        ? ' aria-current="page"' : ''));
    }).join('');
    const home = page === 'home';
    return app('sl-dock-plain' + (home ? ' sl-dock-here' : ''), 'index.html', 'Главная страница панели' + (home ? ' · вы здесь' : ''),
      '<img src="../../templates/lite/images/logos/slaed-logo-mark-gradient-blue.svg" alt="">', home ? ' aria-current="page"' : '')
      + '<div class="sl-dock-body" id="sl-dock-body" data-sl-dock-body' + (getKept('sl.dock.fold') === '1' ? ' hidden style="display: none"' : '') + '>'
      + (pins ? '<span class="sl-dock-sep sl-dock-pin"></span><span class="sl-dock-pins" data-mark="М9">' + pins + '</span>' : '')
      + '<span class="sl-dock-sep"></span>'
      + btn('sl-dock-launch', 'Быстрый переход по разделам · Ctrl+K', DOCK_ICO('grid-3x3-gap-fill'), ' data-sl-pal-open data-mark="М11"')
      + btn('sl-dock-watch', 'Кто на сайте: 2 — подробная статистика', '<span>2</span>',
      ' data-sl-open="sl-stats" style="--sl-d-adm: 50%; --sl-d-people: 50%; --sl-d-bots: 50%" data-mark="М13"')
      + btn('sl-dock-stack', 'Свои ссылки: 5', DOCK_ICO('folder2-open') + '<b class="sl-dock-badge" title="Одна ссылка не работает">!</b>',
      ' data-sl-dock-pop="stack" aria-controls="sl-dock-pop-stack" aria-expanded="false"')
      + btn('sl-dock-me', 'SLAED CMS', '<img src="../../templates/lite/images/avatars/presets/038.svg" alt="">',
      ' data-sl-dock-pop="me" aria-controls="sl-dock-pop-me" aria-expanded="false" data-mark="М14"')
      + '<span class="sl-dock-sep"></span>'
      + app('sl-dock-plain sl-dock-out', '../../admin.php?op=logout', 'Выход', DOCK_ICO('box-arrow-right'))
      + '</div>'
      + '<span class="sl-dock-sep sl-dock-sep-end"></span>'
      + '<span class="sl-modal-acts sl-dock-tools" data-mark="М5 М6 М7 М8">'
      + '<button type="button" class="sl-modal-act sl-dock-tool sl-dock-turn" data-sl-dock-turn title="Повернуть меню: горизонтально или вертикально" aria-label="Повернуть меню">'
      + DOCK_ICO('distribute-vertical sl-dock-ax-row') + DOCK_ICO('distribute-horizontal sl-dock-ax-col') + '</button>'
      + '<button type="button" class="sl-modal-act sl-dock-tool sl-dock-fold" data-sl-dock-fold aria-controls="sl-dock-body" aria-expanded="' + (getKept('sl.dock.fold') !== '1')
      + '" title="Свернуть или развернуть меню">'
      + DOCK_ICO('arrows-collapse-vertical sl-dock-ax-row sl-dock-open') + DOCK_ICO('arrows-expand-vertical sl-dock-ax-row sl-dock-shut')
      + DOCK_ICO('arrows-collapse sl-dock-ax-col sl-dock-open') + DOCK_ICO('arrows-expand sl-dock-ax-col sl-dock-shut') + '</button>'
      + '<button type="button" class="sl-modal-act sl-modal-act-move sl-dock-tool sl-dock-grip" data-sl-dock-grip title="Перетащить меню" aria-label="Переместить меню: '
      + 'перетащите мышью или двигайте стрелками; Home — вернуть на место">' + DOCK_ICO('grip-vertical sl-dock-ax-row') + DOCK_ICO('grip-horizontal sl-dock-ax-col') + '</button>'
      + '</span>';
  }

  // М1 Build the layer once; it stands on its own point over the page and is put back there on every load
  function setDock() {
    const dir = getKept('sl.dock.dir') === 'col' ? 'col' : 'row';
    const box = doc.createElement('div');
    box.className = 'sl-dock';
    box.setAttribute('data-sl-dock', '');
    box.dataset.dir = dir;
    box.dataset.to = 'up';
    box.toggleAttribute('data-min', getKept('sl.dock.fold') === '1');
    const mag = getKept('sl.dock.mag') !== '0';
    box.innerHTML = '<div class="sl-dock-pop" id="sl-dock-pop-stack" data-sl-dock-pop-id="stack" hidden><p class="sl-dock-cap">' + DOCK_ICO('folder2-open')
      + ' Свои ссылки<small>Добавляются в настройках блока «Администрация»</small></p>'
      + '<div class="sl-dock-grid">'
      + '<a href="../../plugins/sxd/index.php" target="_blank" rel="noopener noreferrer" title="Не работает: файла plugins/sxd/index.php нет на сервере"><span '
      + 'style="--sl-dock-c: var(--sl-primary)">' + DOCK_ICO('database-down') + '</span><span>DB Backup - Sypex Dumper</span><i class="sl-dock-dead" title="Нет файла"></i></a>'
      + '<a href="https://github.com/SLAED-CMS/SLAED-CMS-6.3" target="_blank" rel="noopener noreferrer" title="SLAED CMS на GitHub"><span style="--sl-dock-c: var(--sl-accent)">'
      + DOCK_ICO('github') + '</span><span>SLAED CMS на GitHub</span></a>'
      + '<a href="https://pagespeed.web.dev/" target="_blank" rel="noopener noreferrer" title="PageSpeed Insights — проверка скорости сайта"><span style="--sl-dock-c: '
      + 'var(--sl-success)">' + DOCK_ICO('speedometer') + '</span><span>PageSpeed Insights</span></a>'
      + '<a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer" title="Google Search Console"><span style="--sl-dock-c: var(--sl-primary)">'
      + DOCK_ICO('google') + '</span><span>Google Search Console</span></a>'
      + '<a href="https://webmaster.yandex.ru/" target="_blank" rel="noopener noreferrer" title="Яндекс Вебмастер"><span style="--sl-dock-c: var(--sl-warning)">'
      + DOCK_ICO('bar-chart-line') + '</span><span>Яндекс Вебмастер</span></a></div></div>'
      + '<div class="sl-dock-pop" id="sl-dock-pop-me" data-sl-dock-pop-id="me" hidden><div class="sl-dock-me-head"><img '
      + 'src="../../templates/lite/images/avatars/presets/038.svg" alt=""><b>SLAED CMS</b><small>Суперадминистратор</small></div>'
      + '<dl class="sl-dock-facts"><dt>Вошёл</dt><dd>сегодня, 20:31</dd><dt>Прошлый вход</dt><dd>вчера, 21:14</dd><dt>IP-адрес</dt><dd>127.0.0.1</dd><dt>До конца сеанса</dt>'
      + '<dd>47 мин</dd></dl>'
      + '<div class="sl-dock-row">Положение меню <span class="sl-dock-pair"><button type="button" data-sl-dock-dir="row" aria-pressed="' + (dir === 'row')
      + '" title="Горизонтально" aria-label="Горизонтально">' + DOCK_ICO('three-dots') + '</button><button type="button" data-sl-dock-dir="col" aria-pressed="' + (dir === 'col')
      + '" title="Вертикально" aria-label="Вертикально">' + DOCK_ICO('three-dots-vertical') + '</button></span></div>'
      + '<button type="button" class="sl-dock-row" data-sl-dock-home>Вернуть на место ' + DOCK_ICO('arrow-counterclockwise') + '</button>'
      + '<label class="sl-dock-row">Увеличивать иконки под курсором <input type="checkbox" class="sl-check" data-sl-dock-mag' + (mag ? ' checked' : '') + '></label>'
      + '<button type="button" class="sl-dock-row" data-sl-dock-fold>Свернуть меню ' + DOCK_ICO('arrows-collapse-vertical') + '</button>'
      + '<p class="sl-dock-note">Меню можно перетащить за ручку в любое место экрана — оно запомнит позицию. С клавиатуры: стрелки двигают меню, Home возвращает его на '
      + 'место. Позиция и отключение меню — в «Опциях» справа вверху.</p></div>'
      + '<nav class="sl-dock-bar" aria-label="Плавающее меню администратора" data-mark="М1 М2 М3 М4">' + getDockNav() + '</nav>';
    doc.body.append(box);
    setDockPlace();
    doc.addEventListener('pointermove', setDockGrow);
    setDockDrag(box);
    window.addEventListener('resize', setDockPlace);
  }

  // М5 М6 Stand the layer on its point, keep it whole in the window, turn it to the room; upright on a short screen the
  // tiles give way just enough for the pill to stand whole, never under 32 px
  function setDockPlace() {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    const phone = window.matchMedia('(max-width: 560px)').matches;
    box.dataset.dir = phone ? 'row' : (getKept('sl.dock.dir') === 'col' ? 'col' : 'row');
    const bar = box.querySelector('.sl-dock-bar');
    bar.style.removeProperty('--sl-dock-tile');
    if (box.dataset.dir === 'col') {
      const room = window.innerHeight - DOCK_EDGE * 2;
      for (let tile = 44; tile >= 32 && bar.getBoundingClientRect().height > room; tile -= 2) bar.style.setProperty('--sl-dock-tile', (tile - 2) + 'px');
    }
    // М10 With no point of its own the pill stands on the edge from Options; upright on the top or the bottom edge it
    // stands at the middle of the right one, not across the content
    const side = getDockSide();
    const col = box.dataset.dir === 'col';
    const edge = { top: { x: 0.5, y: 0 }, right: { x: 1, y: 0.5 }, left: { x: 0, y: 0.5 } }[col && (side === 'top' || side === 'bottom') ? 'right' : side];
    const at = getDockAt() || edge || null;
    if (at && !phone && !box.hasAttribute('data-drag')) {
      box.setAttribute('data-placed', '');
      const r = box.getBoundingClientRect();
      box.style.setProperty('--sl-dock-x', Math.min(Math.max(at.x * window.innerWidth, r.width / 2 + DOCK_EDGE), window.innerWidth - r.width / 2 - DOCK_EDGE) + 'px');
      box.style.setProperty('--sl-dock-y', Math.min(Math.max(at.y * window.innerHeight, r.height / 2 + DOCK_EDGE), window.innerHeight - r.height / 2 - DOCK_EDGE) + 'px');
    } else if (!box.hasAttribute('data-drag')) {
      box.removeAttribute('data-placed');
      box.style.removeProperty('--sl-dock-x');
      box.style.removeProperty('--sl-dock-y');
    }
    setDockTurn(box);
    setDockLift(box);
  }

  // М4 Tips, pops and the growth of the icons turn to where the room is
  function setDockTurn(box) {
    const r = box.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    box.dataset.to = box.dataset.dir === 'col' ? (cx < window.innerWidth / 2 ? 'right' : 'left') : (cy > window.innerHeight * 0.45 ? 'up' : 'down');
  }

  // М12 The notice with Undo keeps its home at the bottom centre and steps over the pill lying there; a pill standing
  // tall through the lower part is passed beside, never climbed to the top of the screen
  function setDockLift(box) {
    const r = box.getBoundingClientRect();
    const hit = !root.hasAttribute('data-sl-dock-off') && r.bottom > window.innerHeight - 110 && r.top > window.innerHeight * 0.6 && r.right > window.innerWidth / 2 - 320
      && r.left < window.innerWidth / 2 + 320;
    if (hit) root.style.setProperty('--sl-dock-lift', (window.innerHeight - r.top + 14) + 'px');
    else root.style.removeProperty('--sl-dock-lift');
  }

  // М3 Only the icon under the hand grows; the reading comes from the cell, which never moves
  function setDockGrow(e) {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box || box.hasAttribute('data-drag') || root.hasAttribute('data-sl-dock-off')) return;
    const bar = box.querySelector('.sl-dock-bar');
    const r = bar.getBoundingClientRect();
    const col = box.dataset.dir === 'col';
    const near = e.clientY > r.top - 50 && e.clientY < r.bottom + 50 && e.clientX > r.left - 50 && e.clientX < r.right + 50;
    const flat = getKept('sl.dock.mag') === '0' || root.hasAttribute('data-sl-still') || e.pointerType === 'touch';
    bar.querySelectorAll('.sl-dock-app').forEach((a) => {
      if (!near || flat) { a.style.setProperty('--sl-dock-s', 1); a.style.removeProperty('--sl-dock-z'); return; }
      // The scale grows from the middle of the axis, so the centre of the icon on it stays where the layout put it
      const ar = a.getBoundingClientRect();
      const c = col ? ar.top + ar.height / 2 : ar.left + ar.width / 2;
      const grow = Math.max(0, Math.cos(Math.min(Math.abs((col ? e.clientY : e.clientX) - c), 70) / 70 * Math.PI / 2));
      a.style.setProperty('--sl-dock-s', (1 + 0.5 * grow).toFixed(3));
      a.style.setProperty('--sl-dock-z', Math.round(grow * 10));
    });
  }

  // М5 Dragging by the grip: anywhere, and remembered
  function setDockDrag(box) {
    let drag = null;
    box.addEventListener('pointerdown', (e) => {
      const grip = e.target.closest('[data-sl-dock-grip]');
      if (!grip || e.button !== 0) return;
      const r = box.getBoundingClientRect();
      drag = { x: e.clientX, y: e.clientY, dx: e.clientX - (r.left + r.width / 2), dy: e.clientY - (r.top + r.height / 2), moved: false, id: e.pointerId };
      // The page would start its own drag and cancel the pointer after the first step; the grip still takes the focus
      e.preventDefault();
      grip.focus({ preventScroll: true });
      grip.setPointerCapture(e.pointerId);
    });
    box.addEventListener('pointermove', (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      if (!drag.moved && Math.hypot(e.clientX - drag.x, e.clientY - drag.y) < 5) return;
      if (!drag.moved) {
        drag.moved = true;
        setDockClose();
        box.setAttribute('data-drag', '');
        box.setAttribute('data-placed', '');
      }
      const r = box.getBoundingClientRect();
      const x = Math.min(Math.max(e.clientX - drag.dx, r.width / 2 + DOCK_EDGE), window.innerWidth - r.width / 2 - DOCK_EDGE);
      const y = Math.min(Math.max(e.clientY - drag.dy, r.height / 2 + DOCK_EDGE), window.innerHeight - r.height / 2 - DOCK_EDGE);
      box.style.setProperty('--sl-dock-x', x + 'px');
      box.style.setProperty('--sl-dock-y', y + 'px');
      drag.at = [x, y];
      setDockTurn(box);
    });
    // A cancelled pointer ends the drag where it stands rather than leaving the pill held
    const setDrop = (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      const done = drag;
      drag = null;
      if (!done.moved) return;
      box.removeAttribute('data-drag');
      setDockAt(done.at[0], done.at[1]);
    };
    box.addEventListener('pointerup', setDrop);
    box.addEventListener('pointercancel', setDrop);
  }

  // М5 Keep the point as shares of the window; a point set by the grip is the own place for Options, the one the fold
  // holds is not
  function setDockAt(x, y, own = true) {
    const at = { x: +(x / window.innerWidth).toFixed(4), y: +(y / window.innerHeight).toFixed(4) };
    const was = getDockAt();
    if (!own && !(was && was.own !== false)) at.own = false;
    setKept('sl.dock.at', JSON.stringify(at));
    setDockSideMark();
    setDockPlace();
  }

  // М10 An edge from Options drops the own point and lays the pill along the edge: across on the top and the bottom,
  // upright on the sides; Home and "Вернуть на место" come back here
  function setDockSide(side) {
    if (!['top', 'right', 'bottom', 'left'].includes(side)) return;
    const dir = side === 'right' || side === 'left' ? 'col' : 'row';
    setKept('sl.dock.side', side);
    setKept('sl.dock.dir', dir);
    try { localStorage.removeItem('sl.dock.at'); } catch (e) { /* storage may be off */ }
    doc.querySelectorAll('[data-sl-dock-dir]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.slDockDir === dir)));
    setDockSideMark();
    setDockClose();
    setDockPlace();
  }

  // The select shows the chosen edge, or "Произвольная" once the grip has set a point of its own
  function setDockSideMark() {
    const at = getDockAt();
    doc.querySelectorAll('#sl-o-dock-side').forEach((s) => { s.value = at && at.own !== false ? '' : getDockSide(); });
  }

  function setDockClose() {
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    box.removeAttribute('data-open');
    box.querySelectorAll('.sl-dock-pop').forEach((p) => { p.hidden = true; });
    box.querySelectorAll('[data-sl-dock-pop]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
  }

  // The pop over (beside) its icon, held inside the window
  function setDockPop(btn) {
    const box = doc.querySelector('[data-sl-dock]');
    const pop = box.querySelector('[data-sl-dock-pop-id="' + btn.dataset.slDockPop + '"]');
    const open = pop.hidden;
    setDockClose();
    if (!open) return;
    const wb = box.getBoundingClientRect();
    const bb = btn.getBoundingClientRect();
    pop.hidden = false;
    if (box.dataset.to === 'up' || box.dataset.to === 'down') {
      const half = pop.offsetWidth / 2;
      pop.style.setProperty('--sl-dock-px', (Math.min(Math.max(bb.left + bb.width / 2, half + 16), window.innerWidth - half - 16) - wb.left) + 'px');
    } else {
      const half = pop.offsetHeight / 2;
      pop.style.setProperty('--sl-dock-py', (Math.min(Math.max(bb.top + bb.height / 2, half + 16), window.innerHeight - half - 16) - wb.top) + 'px');
    }
    box.setAttribute('data-open', '');
    btn.setAttribute('aria-expanded', 'true');
  }

  // М7 The motion of the column blocks (setSlideMotion, slaed.js): cancel what runs, clip, run the size from what it is
  // to its end in 500 ms linear, clean up; here along the axis of the pill, with the far end held under the hand
  function setDockFold(shut) {
    const box = doc.querySelector('[data-sl-dock]');
    const body = box.querySelector('[data-sl-dock-body]');
    const col = box.dataset.dir === 'col';
    const prop = col ? 'height' : 'width';
    const size = () => (col ? body.offsetHeight : body.offsetWidth);
    setDockClose();
    box.toggleAttribute('data-min', shut);
    setKept('sl.dock.fold', shut ? '1' : '0');
    box.querySelector('.sl-dock-bar [data-sl-dock-fold]').setAttribute('aria-expanded', String(!shut));
    body.getAnimations().forEach((a) => a.cancel());
    const r0 = box.getBoundingClientRect();
    const end0 = col ? r0.bottom : r0.right;
    box.setAttribute('data-placed', '');
    box.style.setProperty('--sl-dock-x', (r0.left + r0.width / 2) + 'px');
    box.style.setProperty('--sl-dock-y', (r0.top + r0.height / 2) + 'px');
    if (!shut) {
      body.hidden = false;
      body.style.display = 'flex';
      body.style[prop] = '0px';
      body.style.overflow = 'hidden';
      void body.offsetWidth;
    } else {
      body.style.overflow = 'hidden';
      body.style[prop] = size() + 'px';
    }
    const from = shut ? size() : 0;
    const to = shut ? 0 : (col ? body.scrollHeight : body.scrollWidth);
    let raf = 0;
    const hold = () => {
      const r = box.getBoundingClientRect();
      if (col) box.style.setProperty('--sl-dock-y', (end0 - r.height / 2) + 'px');
      else box.style.setProperty('--sl-dock-x', (end0 - r.width / 2) + 'px');
      raf = requestAnimationFrame(hold);
    };
    const run = body.animate([{ [prop]: from + 'px' }, { [prop]: to + 'px' }], { duration: root.hasAttribute('data-sl-still') ? 1 : 500, easing: 'linear' });
    hold();
    run.onfinish = () => {
      cancelAnimationFrame(raf);
      body.style.overflow = '';
      body.style[prop] = '';
      if (shut) {
        body.style.display = 'none';
        body.hidden = true;
      }
      const r = box.getBoundingClientRect();
      setDockAt(col ? r.left + r.width / 2 : end0 - r.width / 2, col ? end0 - r.height / 2 : r.top + r.height / 2, false);
    };
  }

  // М6 Across by default, upright on a press, and back; the point the pill stands on stays
  function setDockDir(dir) {
    setKept('sl.dock.dir', dir);
    doc.querySelectorAll('[data-sl-dock-dir]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.slDockDir === dir)));
    setDockClose();
    setDockPlace();
  }

  // М10 А1 Options switch the menu of the panel on or off; off, the block "Администрация" stands in the column of the home
  function setDockOn(on, keep = true) {
    root.toggleAttribute('data-sl-dock-off', !on);
    if (keep) setKept('sl.dock', on ? '1' : '0');
    doc.querySelectorAll('#sl-o-dock').forEach((s) => { s.value = on ? '1' : '0'; });
    setDockClose();
    setDockPlace();
  }

  // М9 Pin or unpin a section from the fan of its menu row or card; the change can be undone for ten seconds (Э23)
  function setDockPin(btn) {
    const item = btn.closest('[data-sl-menu-item]');
    const key = item.dataset.slMenuItem;
    const name = item.querySelector('.sl-menu-name').textContent;
    const was = getDockPins();
    let pins = was.slice();
    if (pins.includes(key)) pins = pins.filter((k) => k !== key);
    else if (pins.length < DOCK_MAX) pins.push(key);
    else { setToast('В плавающем меню уже ' + DOCK_MAX + ' разделов. Чтобы добавить новый, открепите один из них.', null); return; }
    const draw = (list) => {
      setKept('sl.dock.pins', JSON.stringify(list));
      setDockPinMarks();
      const bar = doc.querySelector('[data-sl-dock] .sl-dock-bar');
      if (bar) {
        bar.innerHTML = getDockNav();
        setDockPlace();
      }
    };
    draw(pins);
    setToast('Раздел «' + name + '» ' + (pins.includes(key) ? 'закреплён в плавающем меню' : 'откреплён от плавающего меню') + '.', () => draw(was));
    setSound('alOk');
  }

  // М9 The pin item of every fan says what a press will do
  function setDockPinMarks() {
    const pins = getDockPins();
    doc.querySelectorAll('[data-sl-pin]').forEach((b) => {
      const on = pins.includes(b.closest('[data-sl-menu-item]').dataset.slMenuItem);
      b.title = on ? 'Открепить от плавающего меню' : 'Закрепить в плавающем меню';
      b.setAttribute('aria-pressed', String(on));
      b.firstElementChild.className = 'bi ' + (on ? 'bi-pin-angle-fill' : 'bi-pin-angle');
    });
  }

  // А3 The window of the statistics: the filter by kind, the pause of the live refresh, the age of the last refresh
  function setStats() {
    const win = doc.getElementById('sl-stats');
    if (!win) return;
    let stamp = Date.now();
    let paused = false;
    const ago = () => {
      const el = win.querySelector('[data-sl-stats-ago]');
      const sec = Math.round((Date.now() - stamp) / 1000);
      el.textContent = paused ? 'на паузе' : sec < 2 ? 'только что' : sec + ' сек назад';
    };
    setInterval(() => {
      if (!paused && !doc.hidden && Date.now() - stamp >= 10000) stamp = Date.now();
      ago();
    }, 1000);
    win.addEventListener('click', (e) => {
      const kind = e.target.closest('[data-sl-stats-kind]');
      if (kind) {
        const k = kind.dataset.slStatsKind;
        win.querySelectorAll('[data-sl-stats-kind]').forEach((b) => b.setAttribute('aria-pressed', String(b === kind)));
        let seen = 0;
        win.querySelectorAll('[data-sl-stats-rows] > li').forEach((li) => {
          li.hidden = !(k === 'all' || li.dataset.kind === k);
          if (!li.hidden) seen += 1;
        });
        win.querySelector('[data-sl-stats-empty]').hidden = seen > 0;
        return;
      }
      const pause = e.target.closest('[data-sl-stats-pause]');
      if (pause) {
        paused = !paused;
        stamp = Date.now();
        pause.setAttribute('aria-pressed', String(paused));
        pause.querySelector('[data-sl-stats-state]').textContent = paused ? 'пауза' : 'в эфире';
        ago();
      }
    });
  }

  // The clicks of the menu, the options row and the pins: one listener beside the screen's own
  function setDockClicks(e) {
    const t = e.target;
    if (!t.closest) return;
    const pin = t.closest('[data-sl-pin]');
    if (pin) { setDockPin(pin); return; }
    if (t.closest('[data-sl-dock-home]')) { setDockSide(getDockSide()); return; }
    const box = doc.querySelector('[data-sl-dock]');
    if (!box) return;
    if (t.closest('[data-sl-dock-turn]')) { setDockDir(box.dataset.dir === 'col' ? 'row' : 'col'); box.querySelector('[data-sl-dock-turn]').focus(); return; }
    if (t.closest('[data-sl-dock-fold]')) { setDockFold(!box.hasAttribute('data-min')); box.querySelector('.sl-dock-bar [data-sl-dock-fold]').focus(); return; }
    const dir = t.closest('[data-sl-dock-dir]');
    if (dir) { setDockDir(dir.dataset.slDockDir); return; }
    const mag = t.closest('[data-sl-dock-mag]');
    if (mag) { setKept('sl.dock.mag', mag.checked ? '1' : '0'); return; }
    const popBtn = t.closest('[data-sl-dock-pop]');
    if (popBtn) { setDockPop(popBtn); return; }
    if (!t.closest('.sl-dock-pop')) setDockClose();
  }

  // М5 The grip moves the layer with the arrows (Shift for the long step), Home sends it back; Escape shuts a pop
  function setDockKeys(e) {
    if (e.key === 'Escape') { setDockClose(); return; }
    if (!e.target.matches || !e.target.matches('[data-sl-dock-grip]')) return;
    const step = e.shiftKey ? 96 : 24;
    const mv = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
    if (e.key === 'Home') { e.preventDefault(); setDockSide(getDockSide()); e.target.focus(); return; }
    if (!mv) return;
    e.preventDefault();
    const r = doc.querySelector('[data-sl-dock]').getBoundingClientRect();
    setDockAt(r.left + r.width / 2 + mv[0], r.top + r.height / 2 + mv[1]);
    e.target.focus();
  }

  /* ---------- Stand: data and marks, not part of the panel ---------- */

  // The signals the lamp sums, each read in the panel from a source the code already has: the self-check of the
  // scheduler, the setup file, the error and security logs of the monitor, its disk and runtime figures, the backups.
  // A state of the stand gives the ones it breaks a level and the fact to show
  const SIGNALS = [
    { key: 'dirs', name: 'Защита каталогов', href: '../../admin.php?name=security' },
    { key: 'setup', name: 'Файл установки', href: '#sl-alert-setup' },
    { key: 'phperr', name: 'Ошибки PHP', href: '../../admin.php?name=monitor' },
    { key: 'dberr', name: 'Ошибки базы данных', href: '../../admin.php?name=database' },
    { key: 'disk', name: 'Место на диске', href: '../../admin.php?name=monitor' },
    { key: 'backup', name: 'Резервная копия', href: '../../plugins/sxd/index.php' },
    { key: 'login', name: 'Неудачные входы', href: '../../admin.php?name=security' },
    { key: 'attack', name: 'События безопасности', href: '../../admin.php?name=security' },
    { key: 'cron', name: 'Планировщик', href: '../../admin.php?name=scheduler' },
    { key: 'https', name: 'HTTPS', href: '../../admin.php?name=config' },
    { key: 'opcache', name: 'OPcache', href: '../../admin.php?name=monitor' },
    { key: 'php', name: 'Версия PHP', href: '../../admin.php?name=monitor' }
  ];
  const STATES = {
    ok: { word: 'Все системы в норме', icon: 'check-circle-fill', off: {} },
    warn: {
      word: 'Требует внимания', icon: 'exclamation-triangle-fill',
      off: { setup: ['warn', 'в корне сайта'], phperr: ['warn', '29 за сутки'] }
    },
    danger: {
      word: 'Сбой', icon: 'x-octagon-fill',
      off: {
        dirs: ['fail', 'каталог открыт'],
        dberr: ['fail', '12 за сутки'],
        login: ['warn', '214 за сутки'],
        phperr: ['warn', '37 за сутки'],
        setup: ['warn', 'в корне сайта']
      }
    }
  };
  const LEVELS = { fail: ['sl-knob-danger', 'x-octagon-fill'], warn: ['sl-knob-warn', 'exclamation-triangle-fill'] };

  // Put the cockpit in one state: one ring tone class on the panel colours the lamp and the line; the bell panel of the
  // lamp lists only the signals that broke, the failures first, and a calm system says in its caption that all passed
  function setCockpitState(tone) {
    const box = doc.querySelector('[data-sl-cockpit]');
    const now = STATES[tone];
    if (!box || !now) return;
    const rows = SIGNALS.map((s) => ({ ...s, level: (now.off[s.key] || [])[0] || '', note: (now.off[s.key] || [])[1] || '' }));
    const loud = rows.filter((r) => r.level).sort((x, y) => (x.level === y.level ? 0 : x.level === 'fail' ? -1 : 1));
    const tip = (r) => '<a class="' + LEVELS[r.level][0] + '" href="' + getEsc(r.href) + '"' + (r.href.includes('/plugins/') ? ' target="_blank" rel="noopener noreferrer"' : '') + '><i class="bi bi-' + LEVELS[r.level][1] + '" aria-hidden="true"></i> ' + getEsc(r.name) + ' <span class="sl-bell-num">' + getEsc(r.note) + '</span></a>';
    box.querySelector('[data-sl-signals]').innerHTML = loud.map(tip).join('');
    box.querySelector('[data-sl-signals-cap]').textContent = loud.length ? 'Требуют внимания' : 'Все ' + SIGNALS.length + ' проверок пройдены';
    const text = loud.length ? now.word + ' · ' + loud.length : now.word;
    box.classList.remove('sl-knob-ok', 'sl-knob-warn', 'sl-knob-danger');
    box.classList.add('sl-knob-' + tone);
    const line = box.querySelector('[data-sl-cockpit-state]');
    line.firstElementChild.className = 'bi bi-' + now.icon;
    line.lastElementChild.textContent = text;
    box.querySelector('[data-sl-cockpit-lamp]').setAttribute('aria-label', 'Состояние системы: ' + text + '. Сигналы');
  }

  // The cockpit of the home: the stand has no server behind it, so each gauge and each figure of the strip drifts round its own value as a live panel would
  function setCockpit() {
    const box = doc.querySelector('[data-sl-cockpit]');
    if (!box) return;
    setCockpitState('warn');
    const rings = [...box.querySelectorAll('[data-sl-gauge]')];
    const base = rings.map((r) => Number(r.querySelector('[data-sl-gauge-val]').textContent));
    setInterval(() => {
      if (doc.hidden || root.hasAttribute('data-sl-still') || box.hasAttribute('data-sl-live-paused')) return;
      box.setAttribute('data-sl-live-stamp', Date.now());
      rings.forEach((r, i) => {
        const max = Number(r.dataset.slGaugeMax);
        const val = Math.max(0, Math.min(max, Math.round(base[i] * (0.9 + Math.random() * 0.2))));
        const part = Math.round(val / max * 100);
        r.querySelector('[data-sl-gauge-val]').textContent = val;
        r.querySelector('.sl-knob-val')?.setAttribute('stroke-dasharray', part + ' 100');
        if (r.getAttribute('role') === 'meter') {
          r.setAttribute('aria-valuenow', val);
          r.setAttribute('aria-valuetext', val + '%');
        }
        // The server gauges take their tone from the share, by the thresholds of getPercentTone() in core/system.php
        if (r.hasAttribute('data-sl-gauge-tone')) {
          const tone = part > 95 ? 'danger' : part > 75 ? 'warn' : part > 50 ? 'info' : 'ok';
          r.classList.remove('sl-knob-ok', 'sl-knob-info', 'sl-knob-warn', 'sl-knob-danger');
          r.classList.add('sl-knob-' + tone);
        }
      });
    }, 10000);
  }

  // Draw the stand page from its data: the other page of the file leaves the document, then menu, cards and rows fill their templates
  function setDemoRender() {
    const page = root.dataset.demoPage;
    doc.querySelectorAll('[data-demo-page]').forEach((n) => { if (n !== root && n.dataset.demoPage !== page) n.remove(); });
    if (page === 'home') doc.body.dataset.slSection = 'Главная';
    doc.querySelectorAll('#demo-panel .sl-modal-sub').forEach((n) => { n.textContent = 'Эталон · ' + doc.body.dataset.slSection; });
    const menu = JSON.parse(doc.getElementById('demo-menu').textContent);
    const mtpl = doc.getElementById('sl-menu-tpl').content.firstElementChild;
    const ctpl = doc.getElementById('sl-card-tpl').content.firstElementChild;
    ['admin', 'modules'].forEach((block) => {
      const grid = doc.querySelector('[data-sl-cards="' + block + '"]');
      if (grid) menu[block].forEach(([key, name, icon]) => {
        const card = ctpl.cloneNode(true);
        const a = card.querySelector('a');
        card.dataset.slMenuItem = key;
        a.href = key === 'comments' ? '?name=comments' : key === 'layout' ? '../sb-01-builder.html' : '../../admin.php?name=' + key;
        if (key === 'layout') card.dataset.mark = 'Д13';
        a.title = name;
        a.querySelector('.bi').classList.add('bi-' + icon);
        a.querySelector('.sl-menu-name').textContent = name;
        a.querySelector('[data-m="sub"]').textContent = key + ' · ' + (menu.access[key] || 'Все посетители');
        card.querySelector('.sl-dial-toggle').setAttribute('aria-label', 'Опции раздела «' + name + '»');
        card.querySelector('[data-m="edit"]').href = '../../admin.php?name=modules&op=edit&mod=' + key;
        card.querySelector('[data-m="info"]').href = '../../admin.php?name=' + key + '&op=info';
        const cfg = card.querySelector('[data-m="conf"]');
        if (!menu.conf.includes(key)) cfg.remove();
        else cfg.href = key === 'comments' ? '?name=comments&tab=config' : '../../admin.php?name=' + key + '&op=config';
        const chip = '<span class="sl-queue" data-sl-queue><span data-sl-queue-n></span><span class="sl-sr"> ждут проверки</span></span>';
        if (key === 'comments') a.querySelector('strong').insertAdjacentHTML('beforeend', ' ' + chip);
        grid.append(card);
      });
      const box = doc.querySelector('[data-sl-menu-list="' + block + '"]');
      if (box) menu[block].forEach(([key, name, icon]) => {
        const it = mtpl.cloneNode(true);
        const a = it.querySelector('a');
        it.dataset.slMenuItem = key;
        // Д13 The site builder is a section of the panel like any other; on the stand it opens the series sb-01
        a.href = key === 'comments' ? '?name=comments' : key === 'layout' ? '../sb-01-builder.html' : '../../admin.php?name=' + key;
        if (key === 'layout') it.dataset.mark = 'Д13';
        a.title = name;
        a.querySelector('.bi').classList.add('bi-' + icon);
        a.querySelector('.sl-menu-name').textContent = name;
        it.querySelector('.sl-dial-toggle').setAttribute('aria-label', 'Опции раздела «' + name + '»');
        it.querySelector('[data-m="edit"]').href = '../../admin.php?name=modules&op=edit&mod=' + key;
        it.querySelector('[data-m="info"]').href = '../../admin.php?name=' + key + '&op=info';
        const cfg = it.querySelector('[data-m="conf"]');
        if (!menu.conf.includes(key)) cfg.remove();
        else if (key === 'comments') Object.assign(cfg, { href: '?tab=config' }).setAttribute('data-sl-tab-go', 'config');
        else cfg.href = '../../admin.php?name=' + key + '&op=config';
        if (key === 'comments') {
          a.setAttribute('aria-current', 'page');
          a.insertAdjacentHTML('beforeend', '<span class="sl-queue" data-sl-queue><span data-sl-queue-n></span><span class="sl-sr"> ждут проверки</span></span>');
        }
        box.append(it);
      });
    });
    const data = JSON.parse(doc.getElementById('demo-rows').textContent);
    const body = doc.querySelector('[data-sl-rows]');
    if (!body) return;
    const none = body.querySelector('[data-sl-none]');
    data.rows.forEach((row) => addDemoRow(data, row, none));
    rows = [...body.querySelectorAll('[data-sl-row]')];
  }

  // Fill one row of the list from the stand data and put it before the given one
  function addDemoRow(data, [id, mod, by, text, date, ip, cid, on, far, cc, land], before) {
    const r = doc.getElementById('sl-row-tpl').content.firstElementChild.cloneNode(true);
    const iso = date.replace(/^(\d\d)\.(\d\d)\.(\d{4}) (.+)$/, '$3-$2-$1T$4');
    const vals = { id, modname: data.mods[mod] + ' - ' + mod, cid, date, ip, text, by };
    Object.assign(r.dataset, { id, mod, by, text, ip, modname: vals.modname, on: String(on) });
    if (far) r.setAttribute('data-sl-far', '');
    r.querySelectorAll('[data-r]').forEach((n) => { n.textContent = vals[n.dataset.r]; });
    r.cells[1].title = text;
    r.cells[2].title = vals.modname;
    r.cells[4].dataset.sort = iso.replace(/\D/g, '');
    r.querySelector('time').dateTime = iso;
    r.querySelector('[data-r-tip]').setAttribute('aria-label', 'Подробности комментария ' + id);
    r.querySelector('[data-r-dial]').setAttribute('aria-label', 'Опции комментария ' + id);
    r.querySelector('[data-r-view]').href = '../../index.php?name=' + mod + '&op=view&id=' + cid + '#' + id;
    r.querySelector('[data-r-reply]').href = '../../index.php?name=' + mod + '&op=view&id=' + cid + '#reply-' + id;
    r.querySelectorAll('[data-sl-find]').forEach((b) => {
      const name = b.dataset.slFind === 'by' ? 'Все комментарии автора «' + by + '»' : 'Все комментарии с IP ' + ip;
      b.title = name;
      b.setAttribute('aria-label', name);
    });
    const flag = r.querySelector('.sl-geo-flag');
    flag.title = land;
    Object.assign(flag.firstElementChild, { src: '../../templates/admin/images/flags/' + cc + '.svg', alt: land, title: land });
    Object.assign(r.querySelector('[data-r="ip"]'), { href: 'https://whatismyipaddress.com/ip/' + ip, title: 'IP Адрес: ' + ip });
    r.querySelector('a[data-r="by"]').href = '../../index.php?name=account&op=view&uname=' + encodeURIComponent(by);
    const check = r.querySelector('[data-sl-check-item]');
    check.value = id;
    check.setAttribute('aria-label', 'Выбрать комментарий ' + id);
    setRowOn(r, on === 1);
    before.parentNode.insertBefore(r, before);
    return r;
  }

  // Д8 The stand: a comment for the queue arrives as the server would send it, in turn from the pool of the stand data
  function setDemoArrive() {
    const body = doc.querySelector('[data-sl-rows]');
    let row = null;
    if (body) {
      const data = JSON.parse(doc.getElementById('demo-rows').textContent);
      const turn = setDemoArrive.turn = (setDemoArrive.turn || 0) + 1;
      const [mod, by, text, ip, cid, cc, land] = data.incoming[(turn - 1) % data.incoming.length];
      const id = Math.max(...rows.map((r) => Number(r.dataset.id))) + 1;
      const now = new Date();
      const two = (n) => String(n).padStart(2, '0');
      const date = two(now.getDate()) + '.' + two(now.getMonth() + 1) + '.' + now.getFullYear() + ' ' + [now.getHours(), now.getMinutes(), now.getSeconds()].map(two).join(':');
      row = addDemoRow(data, [id, mod, by, text, date, ip, cid, 0, 0, cc, land], body.querySelector('[data-sl-row]') || body.querySelector('[data-sl-none]'));
      setAgo(row);
    }
    setQueueIn(row);
  }

  // The stand: marks, their description and the helper window; dialog.show() would take the focus, so the window opens inert
  function setDemoStand() {
    const layer = doc.querySelector('[data-demo-layer]');
    const legend = doc.querySelector('[data-demo-legend]');
    const panel = doc.getElementById('demo-panel');
    if (!layer || !legend || !panel) return;
    const off = new Set(JSON.parse(getKept('demo.marks.off') || '[]'));
    const items = [...legend.querySelectorAll('li[data-code]')];
    const tones = { 'В': 'sl-chip-warn', 'Н': 'sl-chip-success', 'П': 'sl-chip-neutral', 'Д': 'sl-chip-danger' };
    items.forEach((li) => {
      const code = li.dataset.code;
      const show = li.dataset.show ? ' <button type="button" class="sl-linkbtn" data-demo-show="' + li.dataset.show + '">Показать</button>' : '';
      li.innerHTML = '<input type="checkbox" class="sl-check" data-demo-code="' + code + '" aria-label="Пометка ' + code + '"' + (off.has(code) ? '' : ' checked') + '>'
        + '<span class="sl-chip ' + (tones[code[0]] || 'sl-chip-accent') + ' demo-legend-code">' + code + '</span>'
        + '<span class="demo-legend-text">' + li.innerHTML + show + '</span>';
    });
    let wait = 0;
    const draw = () => {
      wait = 0;
      if (!root.hasAttribute('data-demo-marks')) return;
      let html = '';
      const tags = [];
      doc.querySelectorAll('[data-mark]').forEach((el) => {
        const codes = el.dataset.mark.split(' ').filter((c) => !off.has(c));
        const r = el.getBoundingClientRect();
        const head = el.closest('thead');
        if (!codes.length || r.width < 4 || r.height < 4 || el.closest('dialog, [popover], [hidden]') || (head && head.getBoundingClientRect().height < 2)) return;
        const kinds = new Map();
        codes.forEach((c) => {
          const k = { 'В': 'v', 'Н': 'n', 'П': 'p', 'Д': 'd' }[c[0]] || 'e';
          kinds.set(k, (kinds.get(k) || []).concat(c));
        });
        html += '<span class="demo-mark-box" data-kind="' + (kinds.has('p') ? 'p' : kinds.keys().next().value) + '" data-codes="' + codes.join(' ') + '" style="left:'
          + Math.round(r.left) + 'px;top:' + Math.round(r.top) + 'px;width:' + Math.round(r.width) + 'px;height:' + Math.round(r.height) + 'px"></span>';
        kinds.forEach((list, kind) => {
          const text = list.join(' · ');
          const tag = { x: Math.min(Math.round(r.left), window.innerWidth - text.length * 7 - 16), y: Math.round(r.top) - 17, w: text.length * 7 + 12 };
          if (tag.y < 0) tag.y = Math.round(r.top) + 1;
          let hit = null;
          let tries = 0;
          while (tries++ < 24 && (hit = tags.find((o) => tag.x < o.x + o.w && o.x < tag.x + tag.w && Math.abs(o.y - tag.y) < 18))) {
            tag.x = hit.x + hit.w + 4;
            if (tag.x + tag.w > window.innerWidth - 4) Object.assign(tag, { x: window.innerWidth - tag.w - 4, y: tag.y > 18 ? tag.y - 18 : tag.y + 36 });
          }
          tags.push(tag);
          html += '<button type="button" class="demo-mark-tag" data-kind="' + kind + '" data-demo-tag="' + list[0] + '" style="left:' + tag.x + 'px;top:' + tag.y
            + 'px" tabindex="-1">' + text + '</button>';
        });
      });
      layer.innerHTML = html;
    };
    const later = () => { if (!wait) wait = requestAnimationFrame(draw); };
    const setSeg = (key, val) => panel.querySelectorAll('[data-demo-seg="' + key + '"] [data-demo-val]').forEach((b) => {
      const on = b.dataset.demoVal === val;
      b.classList.toggle('sl-is-active', on);
      b.setAttribute('aria-pressed', String(on));
    });
    const setOn = (on) => {
      root.toggleAttribute('data-demo-marks', on);
      setKept('demo.marks', on ? '1' : '0');
      setSeg('marks', on ? '1' : '0');
      later();
    };
    setMotion.onset = (on) => setSeg('motion', on ? '1' : '0');
    const sbox = doc.querySelector('[data-demo-sounds]');
    const snd = window.slSound;
    const SNDS = [
      ['alOk', 'success', 'check-circle', 'Успех', 'Д5', 'Статус строки (Э22), «Удалить» (Э23), «OK» отмеченных (Э25), веер раздела (Н6, Г3), '
        + '«Сохранить изменения» (В08), «Продлить сессию» (Э14), включение звука (Д4)', 'success'],
      ['alErr', 'error', 'x-octagon', 'Ошибка', 'Д6', 'Сбой загрузки списка (Э28), сводка ошибок формы (В08.5)', 'fail'],
      ['alWarn', 'warn', 'exclamation-triangle', 'Внимание', 'Д7', 'Конец сессии (Э14), «Уйти без сохранения?» (В08.8)', 'session'],
      ['mod', 'info', 'inbox', 'Очередь модерации', 'Д8', 'Новый комментарий на проверку: колокольчик (Э04), строка очереди, чипы (Э10, Г4), «Ждут внимания» (Г8)', 'queue'],
      ['win', 'accent', 'trophy', 'Победа', 'Д9', 'Очередь опустела действием: статус (Э22), «OK» (Э25) или удаление', 'win']
    ];
    const setSndDraw = () => {
      if (!sbox || !snd) return;
      sbox.querySelectorAll('[data-demo-snd-var]').forEach((b) => b.setAttribute('aria-pressed', String(snd.conf.pick[b.dataset.demoSndId] === b.dataset.demoSndVar)));
      sbox.querySelectorAll('[data-demo-snd-row]').forEach((r) => r.toggleAttribute('data-off', !snd.conf.on[snd.ZONES[r.dataset.demoSndRow]] || snd.conf.mute));
      sbox.querySelectorAll('[data-demo-snd-zone]').forEach((c) => { c.checked = snd.conf.on[c.dataset.demoSndZone]; });
      sbox.querySelectorAll('[data-demo-snd-master]').forEach((c) => { c.checked = !snd.conf.mute; });
      sbox.querySelectorAll('[data-demo-snd-vol]').forEach((v) => { v.value = String(snd.conf.vol); });
      sbox.querySelectorAll('[data-demo-snd-asleep]').forEach((p) => { p.hidden = snd.getState() === 'running'; });
    };
    if (sbox && snd) {
      sbox.querySelector('[data-demo-snd-list]').innerHTML = SNDS.map(([id, tone, icon, name, code, where, show]) => '<section class="demo-snd-row" data-tone="' + tone
        + '" data-demo-snd-row="' + id + '"><h3><i class="bi bi-' + icon + '" aria-hidden="true"></i> ' + name + ' <span class="sl-chip ' + tones['Д'] + '">' + code + '</span>'
        + '<button type="button" class="sl-linkbtn" data-demo-show="' + show + '">Показать</button></h3><p class="demo-snd-where">' + where + '</p>'
        + '<div class="demo-snd-pick" role="group" aria-label="Вариант: ' + name + '">' + Object.entries(snd.SOUNDS[id]).map(([key, v]) => '<button type="button" class="sl-but"'
        + ' data-demo-snd-id="' + id + '" data-demo-snd-var="' + key + '" aria-pressed="false">' + getEsc(v.name) + '</button>').join('') + '</div></section>').join('');
      sbox.addEventListener('click', (e) => {
        const b = e.target.closest('[data-demo-snd-var]');
        if (!b) return;
        const id = b.dataset.demoSndId;
        snd.conf.pick[id] = b.dataset.demoSndVar;
        snd.setSave();
        setSndDraw();
        const ac = snd.getCtx();
        if (ac && !snd.conf.mute) ac.resume().then(() => { snd.SOUNDS[id][b.dataset.demoSndVar].play(ac.currentTime + 0.01, 1); setSndDraw(); });
      });
      sbox.addEventListener('change', (e) => {
        const c = e.target;
        if (c.matches('[data-demo-snd-master]')) setSoundOn(c.checked);
        if (!c.matches('[data-demo-snd-zone]')) return;
        snd.conf.on[c.dataset.demoSndZone] = c.checked;
        snd.setSave();
        setSndDraw();
      });
      sbox.querySelector('[data-demo-snd-vol]').addEventListener('input', (e) => {
        snd.conf.vol = Number(e.target.value);
        snd.setVolume();
        snd.setSave();
      });
    }
    setSoundOn.onset = (on) => { setSeg('sound', on ? '1' : '0'); setSndDraw(); };
    setSaver.onset = (busy) => setSeg('saver', busy ? '1' : '0');
    const setStill = (still) => setMotion(!still);
    const setLegend = (open) => setWindow(legend, open);
    const sess = doc.getElementById('sl-session');
    const setWinSegs = () => {
      setSeg('mode', root.dataset.theme || 'auto');
      setSeg('legend', legend.open ? '1' : '0');
      setSeg('session', sess.open ? '1' : '0');
      if (sbox) setSeg('sounds', sbox.open ? '1' : '0');
    };
    let flow = 0;
    const segs = {
      mode: (v) => setModeTo(v),
      marks: (v) => setOn(v === '1'),
      motion: (v) => setStill(v === '0'),
      legend: (v) => setLegend(v === '1'),
      net: (v) => {
        state.down = v === '1';
        setSeg('net', v);
        setStatus(state.down ? 'Сеть стенда отключена: запросы списка не доходят до сервера.' : 'Сеть стенда снова работает.');
      },
      state: (v) => {
        setCockpitState(v);
        setSeg('state', v);
      },
      session: (v) => (v === '1' ? setSessionEnd() : setWindow(sess, false)),
      sound: (v) => setSoundOn(v === '1'),
      sounds: (v) => { setSndDraw(); setWindow(sbox, v === '1'); },
      saver: (v) => {
        if (v === '1') setSaverNow();
        else if (setSaver.ctl) setSaver.ctl.wake();
      },
      flow: (v) => {
        clearInterval(flow);
        setSeg('flow', v);
        if (v !== '1') return;
        setDemoArrive();
        flow = setInterval(setDemoArrive, 10000);
        if (window.slSound && !window.slSound.conf.on.mod) setToast('Звук очереди выключен: включите зону «Очередь модерации» в панели «Звуки» (строка «Выбор звуков»).', null);
      }
    };
    const fold = panel.querySelector('[data-demo-fold]');
    const phone = window.matchMedia('(max-width: 560px)');
    const setFold = (shut, keep) => {
      const was = panel.offsetHeight;
      panel.querySelectorAll('.sl-modal-body, .sl-modal-foot').forEach((n) => { n.hidden = shut; });
      fold.title = shut ? 'Развернуть' : 'Свернуть';
      fold.setAttribute('aria-label', fold.title);
      fold.setAttribute('aria-expanded', String(!shut));
      fold.firstElementChild.className = 'bi ' + (shut ? 'bi-window' : 'bi-dash-lg');
      if (panel.style.top) panel.style.top = Math.max(0, panel.offsetTop + was - panel.offsetHeight) + 'px';
      if (keep) setKept('demo.panel.fold', shut ? '1' : '0');
    };
    const setPanelPlace = () => {
      if (phone.matches) return;
      let at = null;
      try { at = JSON.parse(getKept('demo.panel.at') || 'null'); } catch (e) { at = null; }
      const left = at ? at[0] : window.innerWidth - panel.offsetWidth - 16;
      const top = at ? at[1] : window.innerHeight - panel.offsetHeight - 16;
      panel.style.left = Math.max(0, Math.min(left, window.innerWidth - panel.offsetWidth)) + 'px';
      panel.style.top = Math.max(0, Math.min(top, window.innerHeight - panel.offsetHeight)) + 'px';
    };
    const setAllBox = () => {
      const all = legend.querySelector('[data-demo-all]');
      all.checked = off.size === 0;
      all.indeterminate = off.size > 0 && off.size < items.length;
    };
    const setFlash = (code) => {
      const el = [...doc.querySelectorAll('[data-mark]')].find((n) => n.dataset.mark.split(' ').includes(code) && n.getClientRects().length && !n.closest('[hidden]'));
      if (!el) return false;
      el.scrollIntoView({ block: 'center', behavior: 'smooth' });
      setTimeout(() => {
        draw();
        layer.querySelectorAll('.demo-mark-box').forEach((b) => b.toggleAttribute('data-flash', b.dataset.codes.split(' ').includes(code)));
        setTimeout(() => layer.querySelectorAll('[data-flash]').forEach((b) => b.removeAttribute('data-flash')), 1600);
      }, 400);
      return true;
    };
    const shows = {
      title: () => setToast('Заголовок вкладки: «' + doc.title + '»', null),
      skip: () => { setLegend(false); setTimeout(() => doc.querySelector('.sl-skip').focus(), 450); },
      palette: () => setPalOpen(),
      session: () => setSessionEnd(),
      phone: () => setToast('Сузьте окно до 900 px и меньше (телефон — до 560 px), чтобы увидеть это.', null),
      fail: () => (doc.querySelector('[data-sl-list]') ? (setView('all', true), state.fail = true, setList()) : location.assign('?name=comments')),
      allpages: () => (doc.querySelector('[data-sl-list]') ? setShownPicked() : location.assign('?name=comments')),
      config: () => (doc.querySelector('[data-sl-list]') ? setView('config') : location.assign('?name=comments&tab=config')),
      rail: () => { if (!wide.matches) setToast('Балка есть только шире 900 px.', null); else setRail(true, true); },
      options: () => { setLegend(false); setWindow(doc.getElementById('sl-settings'), true); },
      queue: () => setDemoArrive(),
      // А1 Н5 The block of the home stands either way: the stand brings it into view and lights it
      adm: () => {
        if (root.dataset.demoPage !== 'home') { location.assign('index.html'); return; }
        setLegend(false);
        doc.querySelector('.sl-adm').scrollIntoView({ block: 'center', behavior: 'instant' });
        setTimeout(() => setFlash('А1'), 200);
      },
      stats: () => { setLegend(false); setWindow(doc.getElementById('sl-stats'), true); },
      dockmove: () => {
        setLegend(false);
        if (root.hasAttribute('data-sl-dock-off')) setDockOn(true);
        setTimeout(() => doc.querySelector('[data-sl-dock-grip]').focus(), 300);
        setToast('Ручка меню выбрана: стрелки двигают меню, с Shift — быстрее, Home возвращает его на место.', null);
      },
      dockturn: () => {
        setLegend(false);
        if (root.hasAttribute('data-sl-dock-off')) setDockOn(true);
        const box = doc.querySelector('[data-sl-dock]');
        setDockDir(box.dataset.dir === 'col' ? 'row' : 'col');
      },
      dockfold: () => {
        setLegend(false);
        if (root.hasAttribute('data-sl-dock-off')) setDockOn(true);
        const box = doc.querySelector('[data-sl-dock]');
        setDockFold(!box.hasAttribute('data-min'));
      },
      cockpit: () => {
        if (!doc.querySelector('[data-sl-cockpit]')) { location.assign('index.html'); return; }
        setLegend(false);
        segs.state('danger');
        doc.querySelector('[data-sl-cockpit]').scrollIntoView({ block: 'center', behavior: 'instant' });
      },
      signals: () => {
        if (!doc.querySelector('[data-sl-cockpit]')) { location.assign('index.html'); return; }
        setLegend(false);
        setTimeout(() => doc.querySelector('[data-sl-cockpit-lamp]').focus(), 450);
      },
      saver: () => { setLegend(false); setTimeout(setSaverNow, 300); },
      sounds: () => { setLegend(false); setSndDraw(); setWindow(sbox, true); },
      success: () => {
        if (!doc.querySelector('[data-sl-list]')) { location.assign('?name=comments'); return; }
        setLegend(false);
        setView('all', true);
        const r = getShown()[0];
        if (r) setAct([r], r.dataset.on === '1' ? 'off' : 'on');
        else setToast('В списке нет строк: сбросьте фильтр.', null);
      },
      win: () => {
        if (!doc.querySelector('[data-sl-list]')) { location.assign('?name=comments&tab=wait'); return; }
        setLegend(false);
        setView('wait', true);
        const left = getShown().filter((r) => r.dataset.on === '0');
        if (left.length) setAct(left, 'on');
        else setToast('Очередь уже пуста: дождитесь заявки («Поток заявок») или отмените последнее действие.', null);
      }
    };
    legend.addEventListener('change', (e) => {
      const c = e.target;
      if (c.matches('[data-demo-all]')) {
        items.forEach((li) => {
          li.querySelector('[data-demo-code]').checked = c.checked;
          if (c.checked) off.delete(li.dataset.code);
          else off.add(li.dataset.code);
        });
      } else if (c.matches('[data-demo-code]')) {
        if (c.checked) off.delete(c.dataset.demoCode); else off.add(c.dataset.demoCode);
      }
      setKept('demo.marks.off', JSON.stringify([...off]));
      setAllBox();
      later();
    });
    doc.addEventListener('click', (e) => {
      const t = e.target;
      if (!t.closest) return;
      const cell = t.closest('[data-demo-seg] [data-demo-val]');
      if (cell) segs[cell.closest('[data-demo-seg]').dataset.demoSeg](cell.dataset.demoVal);
      else if (t.closest('[data-demo-fold]')) setFold(fold.getAttribute('aria-expanded') === 'true', true);
      else if (t.closest('[data-demo-reset]')) {
        ['sl.mode', 'sl.rail', 'sl.density', 'sl.sort', 'demo.marks', 'demo.marks.off', 'sl.motion', 'sl.sound', 'sl.saver', 'demo.panel.at', 'demo.panel.fold',
          'sl.dock', 'sl.dock.site', 'sl.dock.side', 'sl.dock.at', 'sl.dock.dir', 'sl.dock.fold', 'sl.dock.mag', 'sl.dock.pins']
          .forEach((k) => { try { localStorage.removeItem(k); } catch (er) { /* storage may be off */ } });
        location.href = location.pathname + (root.dataset.demoPage === 'comments' ? '?name=comments' : '');
      } else if (t.closest('[data-demo-show]')) shows[t.closest('[data-demo-show]').dataset.demoShow]();
      else if (t.closest('.demo-legend-code')) {
        const li = t.closest('li');
        const other = root.dataset.demoPage === 'home' ? 'странице «Комментарии» (index.html?name=comments)' : 'главной (index.html)';
        if (!setFlash(li.dataset.code)) {
          if (li.dataset.show) shows[li.dataset.show]();
          else setToast('Пометка ' + li.dataset.code + ' — на ' + other + '.', null);
        }
      }
      else if (t.closest('[data-demo-tag]')) {
        const li = legend.querySelector('li[data-code="' + t.closest('[data-demo-tag]').dataset.demoTag + '"]');
        setLegend(true);
        li.scrollIntoView({ block: 'center' });
        li.setAttribute('data-flash', '');
        setTimeout(() => li.removeAttribute('data-flash'), 1600);
      }
    });
    const setTagsLive = (e) => root.toggleAttribute('data-demo-tags-live', e.altKey === true);
    doc.addEventListener('keydown', setTagsLive);
    doc.addEventListener('keyup', setTagsLive);
    window.addEventListener('blur', () => root.removeAttribute('data-demo-tags-live'));
    window.addEventListener('scroll', later, { passive: true, capture: true });
    window.addEventListener('resize', later);
    new MutationObserver((list) => { if (list.some((m) => !layer.contains(m.target))) later(); })
      .observe(doc.body, { subtree: true, childList: true, attributes: true, attributeFilter: ['hidden', 'class', 'style', 'data-density', 'open'] });
    new ResizeObserver(later).observe(doc.body);
    root.addEventListener('transitionend', later);
    doc.addEventListener('pointerup', () => { if (panel.hasAttribute('data-moving')) setKept('demo.panel.at', JSON.stringify([panel.offsetLeft, panel.offsetTop])); }, true);
    const watch = new MutationObserver(setWinSegs);
    watch.observe(root, { attributes: true, attributeFilter: ['data-theme'] });
    [legend, sess, sbox].filter(Boolean).forEach((d) => watch.observe(d, { attributes: true, attributeFilter: ['open'] }));
    setAllBox();
    setOn(root.hasAttribute('data-demo-marks'));
    setMotion(!root.hasAttribute('data-sl-still'), false);
    setWinSegs();
    setSeg('net', '0');
    setSeg('flow', '0');
    setSeg('state', 'warn');
    setSeg('saver', '0');
    setSoundOn(false, false);
    panel.inert = true;
    panel.show();
    panel.inert = false;
    if (window.setWindowFront) window.setWindowFront(panel);
    setFold(getKept('demo.panel.fold') === '1' || phone.matches, false);
    setPanelPlace();
  }

  setDemoRender();
  setCockpit();
  setPagerCopy();
  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', () => { setPanel(); setDemoStand(); });
  else { setPanel(); setDemoStand(); }
})();
