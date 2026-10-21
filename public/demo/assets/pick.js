/* The series "pick all pages": the offer a canon list makes once its whole page is picked - "Select all 8 411" - and
   what follows it, the whole list taken and the portions of op sweep running behind it. Every face keeps the same
   list and the same states and changes only where the offer stands and how it looks; this file is the one engine.

   The host [data-pk] carries data-pk-state - none, some, page, all, run, hold, done - and the numbers --pk-run (the
   share of the sweep done, 0 to 1) and --pk-share (the share of the list picked). --pk-head is the height of the
   table head, for a face that lays something over it.

   [data-pk-when="page all"]  shown only in the named states
   [data-pk-n]                picked, page, total, todo, seen, left, pct, act - a number as the page writes it
   [data-pk-do]               page, all, off, run, hold, go, stop, hush; one with aria-pressed is pressed in its state
   [data-pk-box] [data-pk-head]  the boxes of the rows and the box of the head
   [data-pk-act]              the action of the bulk form; its option carries data-pk-noun, the name of the work
   [data-pk-said]             the live region every change of state is told to
   [data-pk-set]              a button of the stand's desk: forces a state
   [data-pk-pop]              a popover menu placed under the button that opens it */
(function () {
  const host = document.querySelector('[data-pk]');
  if (!host) return;
  const total = Number(host.dataset.pkTotal) || 8411;
  const step = 100;
  const boxes = Array.from(host.querySelectorAll('[data-pk-box]'));
  const heads = Array.from(document.querySelectorAll('[data-pk-head]'));
  const fmt = (num) => Number(num).toLocaleString('ru-RU');
  const st = { state: 'none', todo: 0, seen: 0, timer: 0, rest: 0 };
  const busy = () => st.state === 'run' || st.state === 'hold';

  function getPicked() {
    return boxes.filter((box) => box.checked).length;
  }

  function getNum() {
    if (st.state === 'all') return total;
    if (busy() || st.state === 'done') return st.todo;
    return getPicked();
  }

  function getNoun() {
    const sel = document.querySelector('[data-pk-act]');
    const opt = sel ? sel.options[sel.selectedIndex] : null;
    return opt ? (opt.dataset.pkNoun || opt.textContent) : 'Обработка';
  }

  function setSaid(text) {
    document.querySelectorAll('[data-pk-said]').forEach((node) => { node.textContent = text; });
  }

  function setView() {
    const picked = getPicked();
    const num = getNum();
    const nums = {
      picked: fmt(num),
      page: fmt(boxes.length),
      total: fmt(total),
      todo: fmt(st.todo),
      seen: fmt(st.seen),
      left: fmt(Math.max(0, st.todo - st.seen)),
      pct: Math.floor(st.todo ? st.seen / st.todo * 100 : 0) + '%',
      act: getNoun(),
    };
    host.dataset.pkState = st.state;
    host.style.setProperty('--pk-run', String(st.todo ? st.seen / st.todo : 0));
    host.style.setProperty('--pk-share', String(Math.min(1, num / total)));
    document.querySelectorAll('[data-pk-when]').forEach((node) => {
      node.hidden = !node.dataset.pkWhen.split(' ').includes(st.state);
    });
    document.querySelectorAll('[data-pk-n]').forEach((node) => {
      const val = nums[node.dataset.pkN];
      if (val !== undefined && node.textContent !== val) node.textContent = val;
    });
    document.querySelectorAll('[data-pk-do][aria-pressed]').forEach((btn) => {
      btn.setAttribute('aria-pressed', String(btn.dataset.pkDo === st.state));
    });
    document.querySelectorAll('[data-pk-do="run"]').forEach((btn) => { btn.disabled = num === 0 || busy(); });
    document.querySelectorAll('[data-pk-set]').forEach((btn) => {
      btn.setAttribute('aria-pressed', String(btn.dataset.pkSet === st.state));
    });
    heads.forEach((head) => {
      head.checked = picked > 0 && picked === boxes.length;
      head.indeterminate = picked > 0 && picked < boxes.length;
      head.disabled = busy();
    });
    boxes.forEach((box) => {
      box.disabled = busy();
      box.closest('tr').toggleAttribute('data-pk-on', box.checked);
    });
  }

  function setState(next) {
    if (next !== st.state) delete host.dataset.pkHush;
    const was = st.state;
    st.state = next;
    setView();
    if (next === was) return;
    if (next === 'page') setSaid(`Отмечены все ${fmt(boxes.length)} на этой странице.`);
    if (next === 'all') setSaid(`Отмечены все ${fmt(total)}.`);
    if (next === 'some') setSaid(`Отмечено: ${fmt(getPicked())}`);
    if (next === 'none') setSaid('Отметка снята.');
    if (next === 'run' && was !== 'hold') setSaid(`${getNoun()}: ${fmt(st.todo)}, порциями по ${step}.`);
    if (next === 'hold') setSaid(`Пауза: обработано ${fmt(st.seen)} из ${fmt(st.todo)}.`);
    if (next === 'done') setSaid(`Готово: обработано ${fmt(st.todo)}.`);
  }

  // The state the boxes stand for, once a box is changed by hand
  function setFromBoxes() {
    const picked = getPicked();
    if (busy()) return;
    if (!picked) setState('none');
    else if (picked < boxes.length) setState('some');
    else setState(st.state === 'all' ? 'all' : 'page');
  }

  function setBoxes(on) {
    boxes.forEach((box) => { box.checked = on; });
  }

  function setTick() {
    clearTimeout(st.timer);
    if (st.state !== 'run') return;
    st.seen = Math.min(st.todo, st.seen + Math.min(step, Math.ceil(st.todo / 12)));
    if (st.seen >= st.todo) {
      setBoxes(false);
      setState('done');
      clearTimeout(st.rest);
      st.rest = setTimeout(() => { if (st.state === 'done') setState('none'); }, 4200);
      return;
    }
    setView();
    st.timer = setTimeout(setTick, 110);
  }

  function setRun() {
    const num = getNum();
    if (!num || busy()) return;
    st.todo = num;
    st.seen = 0;
    setState('run');
    st.timer = setTimeout(setTick, 160);
  }

  const acts = {
    page() { setBoxes(true); setState('page'); },
    all() { setBoxes(true); setState('all'); },
    off() { clearTimeout(st.timer); setBoxes(false); setState('none'); },
    run: setRun,
    hold() { if (st.state === 'run') { clearTimeout(st.timer); setState('hold'); } },
    go() { if (st.state === 'hold') { setState('run'); setTick(); } },
    stop() { clearTimeout(st.timer); st.todo = st.seen; setBoxes(false); setState(st.seen ? 'done' : 'none'); },
    hush() { host.dataset.pkHush = '1'; },
  };

  // The desk of the stand reaches any state at once, so a face can be judged in each without playing the whole way
  const sets = {
    none: () => acts.off(),
    some: () => { acts.off(); boxes.slice(0, 7).forEach((box) => { box.checked = true; }); setFromBoxes(); },
    page: () => { acts.off(); acts.page(); },
    all: () => { acts.off(); acts.all(); },
    run: () => { acts.off(); acts.all(); setRun(); },
    hold: () => {
      acts.off();
      acts.all();
      st.todo = total;
      st.seen = Math.round(total * 0.38 / step) * step;
      setState('hold');
    },
    done: () => { acts.off(); st.todo = total; st.seen = total; setState('done'); },
  };

  document.addEventListener('click', (e) => {
    const set = e.target.closest('[data-pk-set]');
    const btn = e.target.closest('[data-pk-do]');
    if (set && sets[set.dataset.pkSet]) {
      clearTimeout(st.rest);
      sets[set.dataset.pkSet]();
      return;
    }
    if (!btn || !acts[btn.dataset.pkDo]) return;
    acts[btn.dataset.pkDo]();
    const pop = btn.closest('[popover]');
    if (pop && pop.hidePopover) pop.hidePopover();
  });

  document.addEventListener('change', (e) => {
    if (e.target.matches('[data-pk-head]')) {
      setBoxes(e.target.checked);
      setFromBoxes();
    } else if (e.target.matches('[data-pk-box]')) {
      setFromBoxes();
    } else if (e.target.matches('[data-pk-act]')) {
      document.querySelectorAll('[data-pk-act]').forEach((sel) => { sel.value = e.target.value; });
      setView();
    }
  });

  // Esc gives the picks back, as long as no sweep is running and no window of the panel is open
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape' || busy() || st.state === 'none' || document.querySelector('[popover]:popover-open, dialog[open]')) return;
    if (e.target.closest && e.target.closest('input, select, textarea')) return;
    acts.off();
  });

  // A popover menu stands under the button that opened it, its right edges flush, inside the window
  document.querySelectorAll('[data-pk-pop]').forEach((pop) => {
    pop.addEventListener('toggle', (e) => {
      const at = document.querySelector(`[popovertarget="${pop.id}"]`);
      if (e.newState !== 'open' || !at) return;
      const box = at.getBoundingClientRect();
      const left = Math.max(8, Math.min(box.right - pop.offsetWidth, innerWidth - pop.offsetWidth - 8));
      pop.style.left = left + 'px';
      pop.style.top = (box.bottom + 6) + 'px';
    });
  });

  // The head cells measured against .pk-table: their height and top, the left edge of the first and, from the right,
  // the left edge and the middle of the box cell, so a face can lay a bar over the head or point at its box
  const wrap = host.querySelector('.pk-table');
  const ths = wrap ? wrap.querySelectorAll('thead th') : [];
  if (ths.length) {
    const setHead = () => {
      const box = wrap.getBoundingClientRect();
      const first = ths[0].getBoundingClientRect();
      const last = ths[ths.length - 1].getBoundingClientRect();
      host.style.setProperty('--pk-head', first.height + 'px');
      host.style.setProperty('--pk-head-y', (first.top - box.top) + 'px');
      host.style.setProperty('--pk-head-x', (first.left - box.left) + 'px');
      host.style.setProperty('--pk-check-r', (box.right - last.left) + 'px');
      host.style.setProperty('--pk-check-x', (box.right - last.left - last.width / 2) + 'px');
    };
    setHead();
    if (window.ResizeObserver) new ResizeObserver(setHead).observe(wrap);
  }

  (sets[host.dataset.pkStart || 'page'] || sets.page)();
})();
