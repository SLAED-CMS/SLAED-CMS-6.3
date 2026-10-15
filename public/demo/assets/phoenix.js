/* Phoenix stand: the screensaver of the 8.0 return, played over the live etalon in a frame like a clip in a player */
(function () {
  'use strict';

  const frame = document.getElementById('phx-frame');
  const dock = document.getElementById('phx-dock');
  const { AT, ACTS, END, getKit, getScene, setIdle } = window.slPhoenix;
  let sheet = '';
  let token = 0;
  let live = null;
  let player = null;
  let saver = null;
  let speed = 1;
  // The rest of the hand after which the screensaver starts, in ms; 0 keeps it off; one choice with Options of the panel
  let idle = '60000';
  try {
    idle = localStorage.getItem('sl.saver') || idle;
  } catch (e) { /* storage may be off */ }

  // Lay the sheet and the badge into the frame as soon as its document exists, then play once it is loaded
  function setWatch(t, old) {
    const tick = () => {
      if (t !== token) return;
      const doc = frame.contentDocument;
      if (doc && doc !== old && doc.documentElement && doc.URL.includes('/admin/index.html')) {
        const root = doc.documentElement;
        if (!root.dataset.phx && doc.head) {
          root.dataset.phx = 'on';
          const st = doc.createElement('style');
          st.textContent = sheet;
          doc.head.append(st);
        }
        if (doc.readyState === 'complete' && root.dataset.phx) {
          setRun(frame.contentWindow, t);
          return;
        }
      }
      requestAnimationFrame(tick);
    };
    tick();
  }

  // The etalon in the frame keeps its own screensaver silent: the address carries phx, and the stand plays the scene
  async function setRun(win, t) {
    const doc = win.document;
    doc.documentElement.removeAttribute('data-demo-marks');
    const brand = doc.querySelector('.sl-brand');
    if (!brand) return;
    brand.insertAdjacentHTML('beforeend', '<span class="phx-badge" aria-hidden="true"><b>8.0</b> Phoenix</span>');
    const img = brand.querySelector('img');
    await img.decode().catch(() => {});
    if (t !== token) return;
    const kit = getKit(win);
    live = kit;
    if (kit.still) {
      kit.root.classList.add('phx-lit', 'phx-crown');
      dock.querySelector('[data-phx-note]').textContent = 'Движение выключено (система или «Опции → Движение»): заставка пропущена, сразу конечный вид.';
      return;
    }
    const ctl = await getScene(kit);
    if (t !== token) return;
    player = ctl;
    player.speed = speed;
    player.onframe = setDock;
    saver = setIdle(kit, ctl, () => Number(idle));
    if (now) saver.sleep();
  }

  // How long until the screensaver starts, for the desk
  const getWait = () => {
    if (player && player.busy) return 'Заставка идёт';
    if (player && !player.playing) return 'Заставка на паузе';
    const left = saver ? saver.left() : -1;
    if (left < 0) return 'Заставка выключена';
    return 'Заставка через ' + Math.ceil(left / 1000) + ' с';
  };

  // A fresh frame: the etalon comes back clean; with go the scene starts at once, otherwise the page opens quiet
  let now = false;
  function setStart(go) {
    now = Boolean(go);
    token++;
    if (live) live.reset();
    live = null;
    player = null;
    saver = null;
    const old = frame.contentDocument;
    frame.src = 'admin/index.html?phx=' + token;
    setWatch(token, old);
  }

  // The desk follows the clock: the stopwatch, the scrubber, the act that plays now
  const ui = {
    play: dock.querySelector('[data-phx-play]'),
    time: dock.querySelector('[data-phx-time]'),
    seek: dock.querySelector('[data-phx-seek]'),
    act: dock.querySelector('[data-phx-act]'),
    acts: dock.querySelector('[data-phx-acts]'),
    wait: dock.querySelector('[data-phx-wait]'),
  };
  let drag = false;
  const getSec = (t) => t.toFixed(1).replace('.', ',');
  function setDock() {
    if (!player) return;
    const rest = getWait();
    if (ui.wait.textContent !== rest) ui.wait.textContent = rest;
    const t = player.time;
    const time = getSec(Math.min(t, END)) + ' / ' + getSec(END) + ' с';
    if (ui.time.textContent !== time) ui.time.textContent = time;
    if (!drag) ui.seek.value = String(Math.min(t, END));
    let name = '';
    let key = '';
    ACTS.forEach(([id, label]) => {
      if (t >= AT[id] - 0.01) {
        name = label;
        key = id;
      }
    });
    if (ui.act.textContent !== name) {
      ui.act.textContent = name;
      ui.acts.querySelectorAll('[data-phx-at]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.phxKey === key)));
    }
    const on = String(player.playing);
    if (ui.play.getAttribute('aria-pressed') !== on) {
      ui.play.setAttribute('aria-pressed', on);
      ui.play.innerHTML = '<i class="bi ' + (player.playing ? 'bi-pause-fill' : 'bi-play-fill') + '" aria-hidden="true"></i>';
    }
  }
  const setToggle = () => {
    if (!player) return;
    if (player.playing) player.pause();
    else player.play();
  };
  // Start the screensaver from the desk over the page as it is; before the scene is ready the frame is loaded with it
  const setLaunch = () => {
    if (!player) {
      setStart(true);
      return;
    }
    player.play();
    saver.sleep();
  };
  const setShift = (s) => {
    if (player) player.seek(player.time + s);
  };

  ui.seek.max = String(END);
  ui.acts.innerHTML = ACTS.map(([key, name]) => '<button type="button" data-phx-at="' + AT[key] + '" data-phx-key="' + key + '" aria-pressed="false">'
    + getSec(AT[key]) + ' · ' + name + '</button>').join('');
  const SPEED = [['0.25', '×¼'], ['0.5', '×½'], ['1', '×1']];
  const setSpeedList = () => {
    dock.querySelector('[data-phx-speed]').innerHTML = '<span class="phx-dock-group">Скорость</span>'
      + SPEED.map(([v, n]) => '<button type="button" data-phx-speed-v="' + v + '" aria-pressed="' + (Number(v) === speed) + '">' + n + '</button>').join('');
  };
  setSpeedList();
  const IDLE = [['60000', '1 мин'], ['300000', '5 мин'], ['900000', '15 мин'], ['0', 'Выкл']];
  const setIdleList = () => {
    dock.querySelector('[data-phx-idle]').innerHTML = '<span class="phx-dock-group">Заставка после простоя</span>'
      + IDLE.map(([v, n]) => '<button type="button" data-phx-idle-v="' + v + '" aria-pressed="' + (v === idle) + '">' + n + '</button>').join('');
  };
  setIdleList();

  ui.seek.addEventListener('pointerdown', () => { drag = true; });
  ui.seek.addEventListener('pointerup', () => { drag = false; });
  ui.seek.addEventListener('input', () => {
    if (player) player.seek(Number(ui.seek.value));
  });
  dock.addEventListener('click', (e) => {
    const rest = e.target.closest('[data-phx-idle-v]');
    if (rest) {
      idle = rest.dataset.phxIdleV;
      if (saver) saver.last = Date.now();
      try {
        localStorage.setItem('sl.saver', idle);
      } catch (err) { /* storage may be off */ }
      setIdleList();
    }
    const pace = e.target.closest('[data-phx-speed-v]');
    if (pace) {
      speed = Number(pace.dataset.phxSpeedV);
      if (player) player.speed = speed;
      setSpeedList();
    }
    const act = e.target.closest('[data-phx-at]');
    if (act && player) player.seek(Number(act.dataset.phxAt));
    if (e.target.closest('[data-phx-play]')) setToggle();
    if (e.target.closest('[data-phx-again]')) setLaunch();
    const fold = e.target.closest('[data-phx-fold]');
    if (fold) {
      const on = dock.toggleAttribute('data-fold');
      fold.setAttribute('aria-expanded', String(!on));
    }
  });

  // The keys of the player belong to the desk; keys on the page are the hand coming back
  function setKeys(e) {
    if (e.target.closest && e.target.closest('input:not([type="range"]), textarea, select, [contenteditable]')) return;
    if (e.key === ' ') {
      e.preventDefault();
      setToggle();
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
      e.preventDefault();
      setShift((e.key === 'ArrowLeft' ? -1 : 1) * (e.shiftKey ? 5 : 1));
    } else if (e.key === 'r' || e.key === 'R' || e.key === 'к' || e.key === 'К') setLaunch();
  }
  document.addEventListener('keydown', setKeys);
  // A choice made in Options of the panel, in the frame or in another tab, reaches the desk
  window.addEventListener('storage', (e) => {
    if (e.key !== 'sl.saver') return;
    idle = e.newValue || '60000';
    if (saver) saver.last = Date.now();
    setIdleList();
  });

  fetch('assets/phoenix-fx.css?v=' + Date.now()).then((r) => r.text()).then((css) => {
    sheet = css;
    setStart();
  });
})();
