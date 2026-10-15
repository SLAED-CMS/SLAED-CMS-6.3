/* The sound engine of the system: voices in the key of the Phoenix theme (D minor), the rules of when they sound, the choice of this browser */
(function () {
  'use strict';

  const SONG = new URL('../../phoenix/audio/theme.mp3', document.currentScript.src).href;
  const GAP = 2.5;
  // The notes of D minor the voices are made of, in Hz
  const NOTE = { lo: 293.66, re: 587.33, fa: 698.46, la: 880, si: 1046.5, hi: 1174.66, mi: 1318.51, top: 1760 };
  const ZONES = { msgIn: 'msg', msgOut: 'msg', ment: 'ment', alErr: 'alert', alWarn: 'alert', alOk: 'alert', alInfo: 'alert', alNews: 'alert', win: 'win', mod: 'mod' };
  const DEFAULT = {
    on: { msg: true, ment: true, alert: true, win: true, mod: false },
    pick: { msgIn: 'a', msgOut: 'a', ment: 'a', alErr: 'a', alWarn: 'a', alOk: 'a', alInfo: 'none', alNews: 'a', win: 'a', mod: 'a' },
    vol: 0.6,
    mute: false,
  };
  const conf = JSON.parse(JSON.stringify(DEFAULT));
  const api = { GAP, ZONES, conf, onload: null };

  // The master switch lives in its own key next to sl.motion, as 1 or 0; the zones, the variants and the volume in another
  const setLoad = () => {
    try {
      const kept = JSON.parse(localStorage.getItem('sl.sound.set') || '{}');
      conf.on = Object.assign({}, DEFAULT.on, kept.on);
      conf.pick = Object.assign({}, DEFAULT.pick, kept.pick);
      conf.vol = typeof kept.vol === 'number' ? kept.vol : DEFAULT.vol;
      conf.mute = localStorage.getItem('sl.sound') === '0';
    } catch (e) { /* storage may be off */ }
  };
  const setSave = () => {
    try {
      localStorage.setItem('sl.sound', conf.mute ? '0' : '1');
      localStorage.setItem('sl.sound.set', JSON.stringify({ on: conf.on, pick: conf.pick, vol: conf.vol }));
    } catch (e) { /* storage may be off */ }
  };

  let ctx = null;
  let main = null;
  let song = null;
  const last = {};

  // The engine wakes on the first touch of the page; where the browser can, it obeys the silent switch of the phone
  const getCtx = () => {
    if (ctx) return ctx;
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (navigator.audioSession) navigator.audioSession.type = 'ambient';
    ctx = new Ctx();
    main = ctx.createGain();
    main.connect(ctx.destination);
    setVolume();
    return ctx;
  };
  // Whether the browser has given the sound, asked without waking the engine before a touch
  const getState = () => (ctx ? ctx.state : 'asleep');
  const setVolume = () => {
    if (main) main.gain.value = conf.mute ? 0 : conf.vol;
  };
  const setWake = () => {
    const ac = getCtx();
    if (ac) ac.resume().catch(() => {});
  };

  // One voice: a tone with a quick attack and an exponential fall, optionally gliding
  const addTone = (at, freq, len, vol, kind = 'sine', glide = 0) => {
    const os = ctx.createOscillator();
    const amp = ctx.createGain();
    os.type = kind;
    os.frequency.setValueAtTime(freq, at);
    if (glide) os.frequency.exponentialRampToValueAtTime(glide, at + len * 0.8);
    amp.gain.setValueAtTime(0.0001, at);
    amp.gain.exponentialRampToValueAtTime(vol, at + 0.008);
    amp.gain.exponentialRampToValueAtTime(0.0001, at + len);
    os.connect(amp).connect(main);
    os.start(at);
    os.stop(at + len + 0.02);
  };
  // A breath of noise through a moving band: the swoosh
  const addAir = (at, len, from, to, vol, kind = 'bandpass') => {
    const n = Math.round(ctx.sampleRate * len);
    const buf = ctx.createBuffer(1, n, ctx.sampleRate);
    const d = buf.getChannelData(0);
    for (let i = 0; i < n; i++) d[i] = Math.random() * 2 - 1;
    const src = ctx.createBufferSource();
    src.buffer = buf;
    const flt = ctx.createBiquadFilter();
    flt.type = kind;
    flt.Q.value = 1.2;
    flt.frequency.setValueAtTime(from, at);
    flt.frequency.exponentialRampToValueAtTime(to, at + len);
    const amp = ctx.createGain();
    amp.gain.setValueAtTime(0.0001, at);
    amp.gain.exponentialRampToValueAtTime(vol, at + len * 0.35);
    amp.gain.exponentialRampToValueAtTime(0.0001, at + len);
    src.connect(flt).connect(amp).connect(main);
    src.start(at);
  };
  // A piece of the Phoenix song, faded in and out
  const addCut = async (from, len) => {
    if (!song) song = fetch(SONG).then((r) => r.arrayBuffer()).then((b) => ctx.decodeAudioData(b));
    const buf = await song;
    const at = ctx.currentTime + 0.02;
    const src = ctx.createBufferSource();
    src.buffer = buf;
    const amp = ctx.createGain();
    amp.gain.setValueAtTime(0.0001, at);
    amp.gain.exponentialRampToValueAtTime(0.9, at + 0.03);
    amp.gain.setValueAtTime(0.9, at + len - 0.45);
    amp.gain.exponentialRampToValueAtTime(0.0001, at + len);
    src.connect(amp).connect(main);
    src.start(at, from, len + 0.05);
  };

  // Every sound with its variants; the second argument quiets a voice for an open tab
  const SILENT = { name: 'Без звука', play: () => {} };
  const SOUNDS = {
    msgIn: {
      a: { name: 'Тук-тук', play: (t, q) => { addTone(t, NOTE.la, 0.16, 0.35 * q, 'triangle'); if (q === 1) addTone(t + 0.09, NOTE.hi, 0.22, 0.3, 'triangle'); } },
      b: { name: 'Стекло', play: (t, q) => { addTone(t, NOTE.hi, 0.45, 0.28 * q); addTone(t, NOTE.hi * 2.01, 0.25, 0.06 * q); } },
      c: { name: 'Капля', play: (t, q) => { addTone(t, NOTE.top, 0.12, 0.3 * q, 'sine', NOTE.la); if (q === 1) addTone(t + 0.1, NOTE.hi, 0.18, 0.18, 'sine'); } },
    },
    msgOut: {
      a: { name: 'Свуш', play: (t) => addAir(t, 0.22, 500, 3500, 0.35) },
      b: { name: 'Вжух вверх', play: (t) => addTone(t, NOTE.re, 0.14, 0.22, 'sine', NOTE.hi) },
      c: { name: 'Щелчок', play: (t) => { addAir(t, 0.03, 3000, 5000, 0.25, 'highpass'); addTone(t, NOTE.mi, 0.05, 0.12); } },
    },
    ment: {
      a: { name: 'Три ступени', play: (t, q) => {
        addTone(t, NOTE.re, 0.18, 0.25 * q, 'triangle');
        addTone(t + 0.07, NOTE.fa, 0.18, 0.25 * q, 'triangle');
        addTone(t + 0.14, NOTE.la, 0.3, 0.28 * q, 'triangle');
      } },
      b: { name: 'Колокол', play: (t, q) => { addTone(t, NOTE.mi, 0.7, 0.25 * q); addTone(t, NOTE.mi * 2.76, 0.4, 0.05 * q); addTone(t, NOTE.mi * 5.4, 0.2, 0.02 * q); } },
      c: { name: 'Вниз', play: (t, q) => { addTone(t, NOTE.hi, 0.16, 0.28 * q, 'triangle'); addTone(t + 0.1, NOTE.la, 0.28, 0.26 * q, 'triangle'); } },
    },
    win: {
      a: { name: 'Арпеджио ре минор', play: (t) => {
        [NOTE.re, NOTE.fa, NOTE.la, NOTE.hi].forEach((f, i) => addTone(t + i * 0.08, f, 0.5, 0.2, 'triangle'));
        [NOTE.re, NOTE.la, NOTE.hi].forEach((f) => { addTone(t + 0.34, f, 1.1, 0.12, 'sawtooth'); addTone(t + 0.34, f * 1.004, 1.1, 0.08, 'sine'); });
      } },
      b: { name: 'Песня 72,3 с — кульминация', play: () => addCut(72.3, 1.6) },
      c: { name: 'Песня 44,8 с', play: () => addCut(44.8, 1.5) },
      d: { name: 'Песня 10,6 с', play: () => addCut(10.6, 1.5) },
    },
    alErr: {
      a: { name: 'Мягкое «нет»', play: (t) => { addTone(t, NOTE.la / 2, 0.16, 0.32, 'triangle'); addTone(t + 0.13, NOTE.fa / 2, 0.26, 0.32, 'triangle'); } },
      b: { name: 'Глухой стук', play: (t) => { addTone(t, NOTE.lo / 2, 0.18, 0.45, 'sine', NOTE.lo / 2.6); addAir(t, 0.06, 900, 400, 0.18, 'lowpass'); } },
      c: { name: 'Двойной низкий', play: (t) => { addTone(t, NOTE.lo, 0.1, 0.3, 'triangle'); addTone(t + 0.14, NOTE.lo, 0.14, 0.3, 'triangle'); } },
      none: SILENT,
    },
    alWarn: {
      a: { name: 'Ла-ла', play: (t) => { addTone(t, NOTE.la / 2, 0.12, 0.26, 'triangle'); addTone(t + 0.15, NOTE.la / 2, 0.18, 0.24, 'triangle'); } },
      b: { name: 'Вопрос вверх', play: (t) => { addTone(t, NOTE.re / 2, 0.14, 0.24, 'triangle'); addTone(t + 0.12, NOTE.la / 2, 0.24, 0.24, 'triangle'); } },
      c: { name: 'Один тон', play: (t) => addTone(t, NOTE.fa / 2, 0.3, 0.26, 'triangle') },
      none: SILENT,
    },
    alOk: {
      a: { name: 'Тинь', play: (t) => { addTone(t, NOTE.re, 0.12, 0.16); addTone(t + 0.07, NOTE.la, 0.22, 0.16); } },
      b: { name: 'Галочка', play: (t) => { addTone(t, NOTE.fa, 0.08, 0.14, 'triangle'); addTone(t + 0.06, NOTE.hi, 0.2, 0.16, 'triangle'); } },
      c: { name: 'Капелька', play: (t) => addTone(t, NOTE.hi, 0.14, 0.14, 'sine', NOTE.top) },
      none: SILENT,
    },
    alInfo: {
      a: { name: 'Лёгкий тон', play: (t) => addTone(t, NOTE.la, 0.2, 0.12) },
      b: { name: 'Пузырёк', play: (t) => addTone(t, NOTE.re, 0.12, 0.14, 'sine', NOTE.fa) },
      none: SILENT,
    },
    alNews: {
      a: { name: 'Дин-дон', play: (t) => {
        addTone(t, NOTE.la, 0.6, 0.2);
        addTone(t, NOTE.la * 2.76, 0.3, 0.03);
        addTone(t + 0.28, NOTE.fa, 0.8, 0.2);
        addTone(t + 0.28, NOTE.fa * 2.76, 0.4, 0.03);
      } },
      b: { name: 'Три ступени вверх', play: (t) => [NOTE.re, NOTE.fa, NOTE.la].forEach((f, i) => addTone(t + i * 0.11, f, 0.35, 0.16, 'triangle')) },
      none: SILENT,
    },
    mod: {
      a: { name: 'Низкий тум', play: (t) => { addTone(t, NOTE.lo, 0.22, 0.3); addTone(t + 0.08, NOTE.la / 2, 0.2, 0.15); } },
      b: { name: 'Дерево', play: (t) => { addTone(t, 220, 0.06, 0.3, 'triangle'); addTone(t + 0.12, 220, 0.06, 0.24, 'triangle'); } },
      c: { name: 'Мягкий ре', play: (t) => addTone(t, NOTE.re, 0.3, 0.2) },
    },
  };

  // One event for the ear: zone and master switch, the browser, one sound of a category per gap; only what others send is quiet on an open tab
  const setPlay = (id) => new Promise((done) => {
    const zone = ZONES[id];
    if (!conf.on[zone]) return done({ state: 'zone' });
    if (conf.mute) return done({ state: 'mute' });
    const ac = getCtx();
    if (!ac) return done({ state: 'none' });
    const go = () => {
      const now = ac.currentTime;
      const key = zone === 'alert' ? id : zone;
      if (last[key] && now - last[key] < GAP) return done({ state: 'gap' });
      last[key] = now;
      const hidden = document.hidden || !document.hasFocus();
      const quiet = ['msgIn', 'ment', 'mod'].includes(id) && !hidden ? 0.4 : 1;
      const pick = SOUNDS[id][conf.pick[id]] || SOUNDS[id].a;
      if (pick === SILENT) return done({ state: 'silent' });
      pick.play(now + 0.01, quiet);
      return done({ state: 'ok', name: pick.name, quiet, hidden });
    };
    if (ac.state === 'running') return go();
    ac.resume().then(() => (ac.state === 'running' ? go() : done({ state: 'asleep' })), () => done({ state: 'asleep' }));
    return undefined;
  });

  setLoad();
  ['pointerdown', 'keydown'].forEach((n) => document.addEventListener(n, setWake, { passive: true }));
  // A choice made in another tab, the lab for one, reaches this page without a reload
  window.addEventListener('storage', (e) => {
    if (e.key !== null && !e.key.startsWith('sl.sound')) return;
    setLoad();
    setVolume();
    if (api.onload) api.onload();
  });
  window.slSound = Object.assign(api, { SOUNDS, getCtx, getState, setPlay, setSave, setVolume });
})();
