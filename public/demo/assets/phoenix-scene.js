/* The Phoenix scene of the 8.0 return and its screensaver timer: the page gives it a kit of its window and a player may drive its clock */
(function () {
  'use strict';

  const LOGO = '../../templates/admin/images/logos/';
  const TAU = Math.PI * 2;

  // Lines of the system itself: the rain of the first act is made of them
  const CODE = [
    'function getConfig(bool $fresh = false): array {',
    'if (is_array($data)) $conf = array_merge($conf, $data);',
    'if (!function_exists($hook)) return [];',
    '$state = getSchedulerFields();',
    'return $next > 0 && $next <= time();',
    'function addSelfCheckTask(): array {',
    '$state = updateSchedulerCrash($name, $state);',
    '$url = substr(urlencode($request), 0, 2048);',
    'function getStatsDays(int $days): array {',
    '$isnew = ($lst < $now - 1800);',
    'function setSeoMove(array $seo = []): void {',
    'if (isset($maps[$key])) return $maps[$key];',
    'function addCacheGcTask(): array {',
    'foreach ($items as $item) {',
    '$path = $info->getRealPath();',
    'function checkCaptcha(string $act): bool {',
    'if (rename($temp, $file)) break;',
    'if (is_array($val)) return array_map($norm, $val);',
    'foreach ($types as $name => $type) {',
    'function getMemoryLimitBytes(bool $safe = false): int {',
    'function getDebugErrors(): string {',
    'if (strlen($text) <= 80) return $text;',
    'function setThemeMode(string $mode): void {',
    'function updateNewsletter(): array {',
    'function getLangName(string $con): string {',
    '$key ??= getEditorKey();',
    'if ($want === $had[$id]) continue;',
    'function getEditorFileJson(): void {',
    '$items = array_values(array_filter($items));',
    'function getRssBody(string $url): ?string {',
    'function is_user_id(string $name): int {',
    'foreach ($new as $location => $md5) {',
    'if (!$refs) return $out;',
    'function getThemeHookVars(string $hook): array {',
    'function getSchedulerSchedule(array|string $job): string {',
    'SLAED CMS 8.0 Phoenix',
  ];
  const TEXT = CODE.join('    ') + '    ';

  // The timeline in seconds: every act starts here and the desk lists them as chapters
  const AT = { dim: 0, code: 1.5, clear: 4.5, rise: 9, hold: 16.5, fall: 18.5, form: 22, big: 28, melt: 30.5, fly: 34, small: 39, dust: 41, land: 43.5, calm: 47 };
  const ACTS = [
    ['dim', 'Затемнение'], ['clear', 'Код'], ['rise', 'Искры в A'], ['hold', 'A'], ['fall', 'A в пепел'], ['form', 'Пепел в логотип'], ['big', 'Большой логотип'],
    ['melt', 'Логотип в пепел'], ['fly', 'Полёт в угол'], ['small', 'Пиксельный логотип'], ['dust', 'Снова пепел'], ['land', 'Настоящий логотип'], ['calm', 'Покой'],
  ];
  const END = 60;
  const DT = 1 / 60;

  // Chance comes from a seed: the scene is the same at every second, however it was reached
  let rnd = Math.random;
  const getSeed = (n) => () => {
    n = (n + 0x6d2b79f5) | 0;
    let t = Math.imul(n ^ (n >>> 15), 1 | n);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
  const getClamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const getLerp = (a, b, t) => a + (b - a) * t;
  const getEaseInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2);
  const getEaseOut = (t) => 1 - (1 - t) ** 3;
  const getQuad = (a, c, b, t) => (1 - t) * (1 - t) * a + 2 * (1 - t) * t * c + t * t * b;
  const getRand = (a, b) => a + rnd() * (b - a);
  const getShuffle = (list) => {
    for (let i = list.length - 1; i > 0; i--) {
      const j = (rnd() * (i + 1)) | 0;
      [list[i], list[j]] = [list[j], list[i]];
    }
    return list;
  };
  // A smooth ramp through the given tones, ready for the canvas; a dot takes its colour from it by progress, never by a jump
  const getRamp = (stops, n = 32) => Array.from({ length: n }, (_, i) => {
    const t = i / (n - 1) * (stops.length - 1);
    const j = Math.min(stops.length - 2, Math.floor(t));
    return 'rgb(' + stops[j].map((v, m) => Math.round(getLerp(v, stops[j + 1][m], t - j))).join(', ') + ')';
  });
  const getTone = (ramp, t) => ramp[Math.round(getClamp(t) * (ramp.length - 1))];

  // Tools of the play: the page document, its logo, the colours of the theme, layers and the frame loop
  function getKit(win) {
    const doc = win.document;
    const root = doc.documentElement;
    const brand = doc.querySelector('.sl-brand');
    const logo = brand.querySelector('img');
    const probe = doc.createElement('i');
    probe.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
    doc.body.append(probe);
    const nodes = [probe];
    const offs = [];
    const kit = {
      win, doc, root, logo,
      alive: true,
      still: win.matchMedia('(prefers-reduced-motion: reduce)').matches || root.hasAttribute('data-sl-still'),
      rgb(name) {
        probe.style.color = 'var(' + name + ')';
        return win.getComputedStyle(probe).color.match(/[\d.]+/g).slice(0, 3).map(Number);
      },
      // The fire of Phoenix: a warm heart in the blue of the project, and the grey of its ash; each tone also as numbers for the ramps
      pal() {
        const names = {
          hot: kit.dark ? '--sl-warning-strong' : '--sl-warning',
          mid: '--sl-warning',
          deep: kit.dark ? '--sl-warning' : '--sl-warning-strong',
          cool: '--sl-primary',
          sky: '--sl-primary-strong',
          ash: '--sl-text-subtle',
        };
        const out = { rgb: {} };
        Object.entries(names).forEach(([key, name]) => {
          out.rgb[key] = kit.rgb(name);
          out[key] = 'rgb(' + out.rgb[key].join(', ') + ')';
        });
        return out;
      },
      box() {
        return logo.getBoundingClientRect();
      },
      // The A of the wordmark: its middle and its top, where the embers leave
      peak() {
        const r = logo.getBoundingClientRect();
        return { x: r.x + r.width * 0.495, top: r.y + r.height * 0.04 };
      },
      loop(fn) {
        let last = win.performance.now();
        const step = (now) => {
          if (!kit.alive) return;
          fn(Math.min(0.05, (now - last) / 1000));
          last = now;
          win.requestAnimationFrame(step);
        };
        win.requestAnimationFrame(step);
      },
      el(tag, cls, host) {
        const n = doc.createElement(tag);
        if (cls) n.className = cls;
        (host || doc.body).append(n);
        nodes.push(n);
        return n;
      },
      // A listener of the play, taken off with the play
      on(host, name, fn, opts) {
        host.addEventListener(name, fn, opts);
        offs.push(() => host.removeEventListener(name, fn, opts));
      },
      // Undo the play: its layers go, its listeners and classes leave the page, its loop stops
      reset() {
        kit.alive = false;
        nodes.forEach((n) => n.remove());
        offs.forEach((off) => off());
        [...root.classList].filter((n) => n.startsWith('phx-')).forEach((n) => root.classList.remove(n));
      },
      image(name) {
        const img = new win.Image();
        img.src = LOGO + name;
        return img.decode().then(() => img);
      },
      // A canvas over the ground of a host, or over the whole window without one
      layer(host) {
        const cv = kit.el('canvas', host ? 'phx-layer' : 'phx-layer phx-top', host);
        cv.setAttribute('aria-hidden', 'true');
        const g = cv.getContext('2d');
        const L = { cv, g, w: 0, h: 0 };
        L.fit = () => {
          const w = host ? host.clientWidth : win.innerWidth;
          const h = host ? host.clientHeight : win.innerHeight;
          const d = win.devicePixelRatio || 1;
          cv.width = Math.round(w * d);
          cv.height = Math.round(h * d);
          Object.assign(cv.style, { left: 0, top: 0, width: w + 'px', height: h + 'px' });
          g.setTransform(d, 0, 0, d, 0, 0);
          L.w = w;
          L.h = h;
        };
        L.fit();
        kit.on(win, 'resize', L.fit);
        return L;
      },
      // An image as dots: where each opaque pixel lies on the window and what colour it has
      dots(img, x0, y0, w, h, step) {
        const c = doc.createElement('canvas');
        c.width = Math.round(w);
        c.height = Math.round(h);
        const g = c.getContext('2d', { willReadFrequently: true });
        g.drawImage(img, 0, 0, c.width, c.height);
        const d = g.getImageData(0, 0, c.width, c.height).data;
        const out = [];
        for (let y = 0; y < c.height; y += step) {
          for (let x = 0; x < c.width; x += step) {
            const i = (y * c.width + x) * 4;
            if (d[i + 3] > 140) out.push({ tx: x0 + x, ty: y0 + y, u: x / c.width, v: y / c.height, c: 'rgb(' + d[i] + ', ' + d[i + 1] + ', ' + d[i + 2] + ')' });
          }
        }
        return out;
      },
      // The page ground: it plays over the page, then settles under the content
      ground() {
        const mid = doc.querySelector('.sl-wrapmid');
        mid.classList.add('phx-ground-host');
        offs.push(() => mid.classList.remove('phx-ground-host'));
        return kit.el('div', 'phx-ground phx-over', mid);
      },
    };
    const bg = kit.rgb('--sl-bg');
    kit.dark = bg[0] * 0.3 + bg[1] * 0.59 + bg[2] * 0.11 < 128;
    return kit;
  }

  // The scene on its own clock T: the acts are drawn from T, the ash is moved in fixed steps from a seed, so any second can be reached
  async function getScene(k) {
    const ground = k.ground();
    // The dimming shares the stacking of the ground and lies just under it: every effect plays over it, the page lies under it
    const veil = k.el('div', 'phx-veil', ground.parentNode);
    const code = k.layer(ground);
    const L = k.layer(ground);
    const top = k.layer(null);
    const g = L.g;
    const c = k.pal();
    const word = await k.image('slaed-logo-wordmark-gradient-blue.svg');
    const dpr = k.win.devicePixelRatio || 1;
    const dot = (x, y, s, color, a, ctx = g) => {
      ctx.globalAlpha = a;
      ctx.fillStyle = color;
      // Whole pixels of the screen: a dot that straddles them smears into its neighbours and the gaps between the pixels close
      s = Math.max(1, Math.round(s * dpr)) / dpr;
      ctx.fillRect(Math.round((x - s / 2) * dpr) / dpr, Math.round((y - s / 2) * dpr) / dpr, s, s);
    };
    // Colours change by ramps: warm in flight to the A, fire as the ash takes light
    const rise = getRamp([c.rgb.sky, c.rgb.mid, c.rgb.mid, c.rgb.sky]);
    const fire = getRamp([c.rgb.ash, c.rgb.deep, c.rgb.mid, c.rgb.hot]);

    // The measures of a round are taken when it starts: the window may have changed and the page may have been scrolled since
    let WW = 0;
    let px = [];
    let ax = 0;
    let base = [];
    let letters = [];
    let hp = [];
    let hx = 0;
    let hy = 0;
    const hs = 1;
    const setShape = () => {
      // The big logo fills the page and its own A is the giant A, so later the letters grow around it; one pixel for both, a 3 px dot on a 4 px grid
      WW = Math.min(L.w * 0.9, L.h * 0.62 * word.naturalWidth / word.naturalHeight);
      const WH = WW * word.naturalHeight / word.naturalWidth;
      const wx = Math.round((L.w - WW) / 2);
      const wy = Math.round((L.h - WH) / 2);
      px = k.dots(word, wx, wy, WW, WH, 4);
      ax = wx + WW * 0.495;
      // The A of the logo: the run of filled columns around its middle
      const filled = new Set(px.map((p) => p.tx));
      const mid = wx + Math.round(WW * 0.495 / 4) * 4;
      let lo = mid;
      let hi = mid;
      while (filled.has(lo - 4)) lo -= 4;
      while (filled.has(hi + 4)) hi += 4;
      if (!filled.has(mid) || hi - lo > WW * 0.3) {
        lo = wx + WW * 0.378;
        hi = wx + WW * 0.613;
      }
      base = px.filter((p) => p.tx >= lo && p.tx <= hi);
      letters = px.filter((p) => p.tx < lo || p.tx > hi);
      // The small pixel logo in the header: a 1 px dot on a 2 px grid over the real logo, where it is now
      const r = k.box();
      hp = k.dots(word, r.x, r.y, r.width, r.height, 2);
      hx = r.x + r.width / 2;
      hy = r.y + r.height / 2;
    };
    // The beam of the installer: a white band leaning back, the token of the theme
    const beam = k.win.getComputedStyle(k.root).getPropertyValue('--sl-beam-bg').trim() || 'rgba(255, 255, 255, 0.42)';
    const lean = Math.tan(-18 * Math.PI / 180);
    // The beam is drawn on a canvas of its own and cut to the shape of the letters, so it never touches the header around them
    const shine = k.doc.createElement('canvas');
    const sg = shine.getContext('2d');

    // The rain: every column runs down the code of the system, a warm head over a blue trail; a function of T alone
    const cell = 16;
    const trail = 18;
    const rows = Math.ceil(code.h / cell);
    const span = rows + trail + 12;
    const font = '600 ' + (cell - 2) + 'px ' + (k.win.getComputedStyle(k.root).getPropertyValue('--sl-face-mono').trim() || 'monospace');

    let T = 0;
    let cols = [];
    let pts = [];
    let own = [];
    let rest = [];
    let uniq = [];
    let cinders = [];

    // The one particle of the scene: a square of smouldering ash that shimmers as it burns; it takes light (dh > 0) or burns out (dh < 0)
    const addAsh = (p) => cinders.push(Object.assign({
      vx: 0, vy: 0, grav: 0, drag: 1, cap: 80, sway: getRand(4, 14), ph: rnd() * TAU, s: 2.4, age: 0, life: Infinity, h: 0, dh: 0.27, top: 0.68, up: false,
    }, p));

    // A fresh start: the same seed gives the same columns, the same sparks and the same ash
    const setBuild = () => {
      setShape();
      rnd = getSeed(2005);
      T = 0;
      cinders = [];
      // The columns enter from the top one by one over the first seconds of the code
      cols = Array.from({ length: Math.ceil(code.w / cell) }, (_, i) => ({
        x: i * cell + cell / 2, from: AT.code + getRand(0, 2), v: getRand(5, 11), off: (rnd() * TEXT.length) | 0,
      }));
      // Every pixel of the big logo gets a spark; those of the A stand on their own pixel from the start, those of the letters wait inside the A
      // for their turn, and a few more than the logo needs leave as ash
      const glow = getShuffle(letters.slice());
      const n = Math.round(px.length * 1.1);
      pts = Array.from({ length: n }, (_, i) => {
        const a = i < base.length ? base[i] : base[(rnd() * base.length) | 0];
        const q = i < base.length ? a : glow[i - base.length] || null;
        return { tx: a.tx, ty: a.ty, v: a.v, c: a.c, q };
      });
      getShuffle(pts);
      let j = 0;
      pts.forEach((p) => {
        const o = cols[(rnd() * cols.length) | 0];
        const head = Math.floor(getPos(o, AT.rise) % span) - trail;
        const x = o.x;
        const y = getClamp((head - getRand(0, 16)) * cell, 0, L.h);
        Object.assign(p, {
          x, y,
          cx: getLerp(x, p.tx, 0.2) + getRand(-60, 60),
          cy: getLerp(y, p.ty, 0.8),
          d: (1 - p.v) * 0.35 + rnd() * 0.2,
          fd: p.v * 0.4 + rnd() * 0.15,
          a1x: p.tx + getRand(-30, 30),
          a1y: p.ty + getRand(20, 80),
          ph: rnd() * TAU,
        });
        if (!p.q) return;
        const q = p.q;
        const h = hp[j % hp.length];
        // The small logo breaks evenly in every direction from its middle; the ash stays in the air until it gathers again
        const ang = Math.atan2(h.ty - hy, h.tx - hx) + getRand(-0.5, 0.5);
        const far = getRand(25, 65);
        Object.assign(p, {
          fe: Math.abs(q.tx - ax) / WW * 0.8 + rnd() * 0.12,
          bx: (p.a1x + q.tx) / 2,
          by: Math.min(p.a1y, q.ty) - getRand(10, 50),
          md: q.v * 0.4 + rnd() * 0.15,
          a2x: q.tx + getRand(-25, 25),
          a2y: q.ty + getRand(15, 60),
          h,
          dup: j >= hp.length,
          yd: rnd() * 0.35,
          fx: (q.tx + h.tx) / 2 + getRand(-80, 80),
          fy: Math.min(q.ty, h.ty) - getRand(40, 140),
          a3x: h.tx + Math.cos(ang) * far,
          a3y: Math.max(4, h.ty + Math.sin(ang) * far * 0.8),
          sd: rnd() * 0.3,
          ld: Math.abs(h.u - 0.495) * 0.8 + rnd() * 0.12,
        });
        j++;
      });
      own = pts.filter((p) => p.q);
      rest = pts.filter((p) => !p.q);
      uniq = own.filter((p) => !p.dup);
    };
    const getPart = (a, b) => getClamp((T - a) / (b - a));
    // How far a column of the rain has run at a time, in rows; its head shows on the top row the moment it starts
    const getPos = (o, at) => (at < o.from ? 0 : trail + o.v * (at - o.from));
    // The beams of the quiet: one every six seconds from a second after the logo is home, two seconds across, as on the installer card
    const GLINT = 6;
    const getGlint = (t) => (t < AT.calm + 1 ? -1 : ((t - AT.calm - 1) % GLINT) / 2);

    // One fixed step of the clock: the ash is born and moves
    const setStep = (dt) => {
      const was = T;
      T += dt;
      // The sparks of the A beyond the pixels of the logo leave as ash while the rest takes fire
      if (was < AT.form && T >= AT.form) {
        for (const p of rest) addAsh({ x: p.a1x, y: p.a1y, vy: -getRand(15, 40), grav: -5, ph: p.ph });
      }
      if (T > AT.calm) {
        // Ash rises from the foot of the page, a little more with every second of the quiet
        let low = 0;
        for (const p of cinders) if (!p.up) low++;
        if (low < 140 && rnd() < 0.6 * getPart(AT.calm, AT.calm + 8)) {
          addAsh({ x: rnd() * L.w, y: L.h + getRand(0, 20), vy: -getRand(15, 35), grav: -4, cap: 45, h: getRand(0.3, 0.6) });
        }
        // When the beam reaches the A, sparks leave its top
        const a = getGlint(was);
        const b = getGlint(T);
        if (a >= 0 && a < 0.45 && b >= 0.45 && b > a) {
          const P = k.peak();
          for (let i = 0; i < 14; i++) {
            const ang = getRand(-Math.PI * 0.75, -Math.PI * 0.25);
            const v = getRand(60, 170);
            addAsh({
              x: P.x + getRand(-4, 4), y: P.top + 3, vx: Math.cos(ang) * v, vy: Math.sin(ang) * v, grav: 70, drag: 0.98, cap: 1000, sway: 0,
              life: getRand(1.2, 2.2), h: 0.95, dh: -0.4, top: 1, s: 2, up: true,
            });
          }
        }
      }
      for (let i = cinders.length - 1; i >= 0; i--) {
        const p = cinders[i];
        p.age += dt;
        const drag = p.drag ** (dt * 60);
        p.vx *= drag;
        p.vy = Math.max(p.vy * drag + p.grav * dt, -p.cap);
        p.x += p.vx * dt;
        p.y += p.vy * dt;
        if (p.age >= p.life || p.y < -10 || p.y > L.h + 300) {
          cinders[i] = cinders[cinders.length - 1];
          cinders.pop();
        }
      }
    };

    const setRain = () => {
      const cg = code.g;
      cg.clearRect(0, 0, code.w, code.h);
      if (T < AT.code || T >= AT.hold) return;
      // The code comes out of half-light together with the dimming and is clear when the screen is
      const out = Math.max(1 - getEaseInOut(getPart(AT.code, AT.clear)), getPart(AT.rise + 2.2, AT.hold));
      cg.font = font;
      cg.textAlign = 'center';
      cg.textBaseline = 'middle';
      for (const o of cols) {
        const pos = getPos(o, T);
        if (pos <= 0) continue;
        const cyc = Math.floor(pos / span);
        if (T > AT.rise && cyc > Math.floor(getPos(o, AT.rise) / span)) continue;
        const head = Math.floor(pos % span) - trail;
        for (let i = 0; i <= trail; i++) {
          const row = head - i;
          if (row < 0 || row >= rows) continue;
          const ch = TEXT[(row + cyc * 37 + o.off) % TEXT.length];
          if (ch === ' ') continue;
          cg.globalAlpha = (i ? (1 - i / (trail + 1)) ** 1.5 * 0.9 : 1) * (1 - out);
          cg.fillStyle = i ? c.sky : c.hot;
          cg.fillText(ch, o.x, row * cell + cell / 2);
        }
      }
      cg.globalAlpha = 1;
    };
    const setAsh = () => {
      for (const p of cinders) {
        const x = p.x + Math.sin(T * 0.9 + p.ph) * p.sway * Math.min(1, p.age / 2);
        const fade = (p.up ? 1 : getClamp(p.y / (L.h * 0.3))) * (1 - (p.age / p.life) ** 2) * (p.old ? 1 - getPart(AT.dim, AT.clear) : 1);
        dot(x, p.y, p.s, getTone(fire, getClamp(p.h + p.dh * p.age, 0, p.top)), fade * (0.7 + 0.3 * Math.sin(T * 2.5 + p.ph)), p.up ? top.g : g);
      }
    };
    // Ash that lies: grey, a little shimmer
    const getAsh = (p) => 0.6 + 0.1 * Math.sin(T * 2 + p.ph);
    // Ash in the air over the header: brighter, so it is seen against the header, and swaying a little
    const getAir = (p) => 0.85 + 0.1 * Math.sin(T * 2 + p.ph);
    const getSway = (p) => Math.sin(T * 1.3 + p.ph) * 2;
    // A dot that changes from one colour to another: the first fades out under the second
    const dotTwo = (x, y, s, from, to, mix, a) => {
      if (mix < 1) dot(x, y, s, from, (1 - mix) * a);
      if (mix > 0) dot(x, y, s, to, mix * a);
    };
    // The beam crosses the logo box from left to right, leaning back, white at its heart
    // It follows the logo where it is now, and a logo scrolled out of the window gets no beam
    const setGlint = (t) => {
      const r = k.box();
      if (r.bottom <= 0 || r.top >= top.h || !r.width) return;
      const w = Math.round(r.width * dpr);
      const h = Math.round(r.height * dpr);
      if (shine.width !== w || shine.height !== h) {
        shine.width = w;
        shine.height = h;
      }
      const bw = r.width * 0.34;
      const x = getLerp(-1.6, 4.2, getEaseInOut(t)) * bw;
      sg.setTransform(dpr, 0, 0, dpr, 0, 0);
      sg.globalCompositeOperation = 'source-over';
      sg.clearRect(0, 0, r.width, r.height);
      sg.save();
      sg.translate(x + bw / 2, r.height / 2);
      sg.transform(1, 0, lean, 1, 0, 0);
      const gr = sg.createLinearGradient(-bw / 2, 0, bw / 2, 0);
      gr.addColorStop(0, 'rgba(255, 255, 255, 0)');
      gr.addColorStop(0.5, beam);
      gr.addColorStop(1, 'rgba(255, 255, 255, 0)');
      sg.fillStyle = gr;
      sg.fillRect(-bw / 2, -r.height, bw, r.height * 2);
      sg.restore();
      sg.globalCompositeOperation = 'destination-in';
      sg.drawImage(word, 0, 0, r.width, r.height);
      top.g.globalAlpha = 1;
      top.g.drawImage(shine, r.x, r.y, r.width, r.height);
    };

    // One picture of the scene at T
    const setFrame = () => {
      setRain();
      g.clearRect(0, 0, L.w, L.h);
      top.g.clearRect(0, 0, top.w, top.h);
      if (T >= AT.rise && T < AT.hold) {
        // Sparks leave the code from every side and gather slowly into the giant A while the code dies away
        const t = getPart(AT.rise, AT.hold);
        const show = getPart(AT.rise, AT.rise + 1);
        for (const p of pts) {
          const s = getClamp((t - p.d) / 0.45);
          const e = getEaseInOut(s);
          dotTwo(getQuad(p.x, p.cx, p.tx, e), getQuad(p.y, p.cy, p.ty, e), getLerp(2, 3, s), getTone(rise, s), p.c, getClamp((s - 0.7) / 0.3),
            (0.35 + 0.65 * getEaseOut(s)) * show);
        }
      } else if (T >= AT.hold && T < AT.fall) {
        for (const p of pts) dot(p.tx, p.ty, 3, p.c, 1);
      } else if (T >= AT.fall && T < AT.form) {
        // The A falls into ash from its top down
        const t = getPart(AT.fall, AT.form);
        for (const p of pts) {
          const s = getClamp((t - p.fd) / 0.45);
          const e = getEaseOut(s);
          dotTwo(getLerp(p.tx, p.a1x, e), getLerp(p.ty, p.a1y, e), 3, p.c, c.ash, s, getLerp(1, getAsh(p), s));
        }
      } else if (T >= AT.form && T < AT.big) {
        // The ash takes fire from the A outward and gathers into the big logo, every grain to its own pixel
        const t = getPart(AT.form, AT.big);
        for (const p of own) {
          const s = getClamp((t - p.fe) / 0.45);
          const e = getEaseInOut(s);
          const x = getQuad(p.a1x, p.bx, p.q.tx, e);
          const y = getQuad(p.a1y, p.by, p.q.ty, e);
          if (s <= 0) dot(x, y, 3, c.ash, getAsh(p));
          else dotTwo(x, y, 3, getTone(fire, s / 0.7), p.q.c, getClamp((s - 0.7) / 0.3), getLerp(getAsh(p), 1, getClamp(s * 3)));
        }
      } else if (T >= AT.big && T < AT.melt) {
        for (const p of own) dot(p.q.tx, p.q.ty, 3, p.q.c, 1);
      } else if (T >= AT.melt && T < AT.fly) {
        // The big logo falls into ash from its top down
        const t = getPart(AT.melt, AT.fly);
        for (const p of own) {
          const s = getClamp((t - p.md) / 0.45);
          const e = getEaseOut(s);
          dotTwo(getLerp(p.q.tx, p.a2x, e), getLerp(p.q.ty, p.a2y, e), 3, p.q.c, c.ash, s, getLerp(1, getAsh(p), s));
        }
      } else if (T >= AT.fly && T < AT.small) {
        // The ash flies into the top left corner and gathers into the small pixel logo; the grains beyond its pixels melt into it
        const t = getPart(AT.fly, AT.small);
        for (const p of own) {
          const s = getClamp((t - p.yd) / 0.6);
          const e = getEaseInOut(s);
          const gone = p.dup ? 1 - getClamp((s - 0.75) / 0.25) : 1;
          if (gone <= 0) continue;
          dotTwo(getQuad(p.a2x, p.fx, p.h.tx, e), getQuad(p.a2y, p.fy, p.h.ty, e), getLerp(3, hs, e), c.ash, p.h.c, getClamp((s - 0.6) / 0.4), getLerp(getAsh(p), 1, s) * gone);
        }
      } else if (T >= AT.small && T < AT.dust) {
        for (const p of uniq) dot(p.h.tx, p.h.ty, hs, p.h.c, 1);
      } else if (T >= AT.dust && T < AT.land) {
        // The small logo breaks into ash once more: the grains fly out evenly and hang in the air
        const t = getPart(AT.dust, AT.land);
        for (const p of uniq) {
          const s = getClamp((t - p.sd) / 0.6);
          const e = getEaseOut(s);
          dotTwo(getLerp(p.h.tx, p.a3x, e) + getSway(p) * e, getLerp(p.h.ty, p.a3y, e), hs, p.h.c, c.ash, s, getLerp(1, getAir(p), s));
        }
      } else if (T >= AT.land && T < AT.calm) {
        // The ash takes fire from the A outward and becomes the real logo, which shows through it
        const t = getPart(AT.land, AT.calm - 0.6);
        const fade = 1 - getPart(AT.calm - 0.8, AT.calm);
        for (const p of uniq) {
          const s = getClamp((t - p.ld) / 0.5);
          const e = getEaseInOut(s);
          const x = getLerp(p.a3x, p.h.tx, e) + getSway(p) * (1 - e);
          const y = getLerp(p.a3y, p.h.ty, e);
          if (s <= 0) dot(x, y, hs, c.ash, getAir(p) * fade);
          else dotTwo(x, y, hs, getTone(fire, s / 0.7), p.h.c, getClamp((s - 0.7) / 0.3), getLerp(getAir(p), 1, getClamp(s * 3)) * fade);
        }
      }
      const gl = getGlint(T);
      if (gl >= 0 && gl < 1) setGlint(gl);
      setAsh();
      g.globalAlpha = 1;
      top.g.globalAlpha = 1;
      // The page follows the clock. The screen dims slowly to three quarters and clears as the logo comes home;
      // the logo and its version stay lit until the dimming has covered them, so the circle closes without a jump.
      // When the hand comes back mid-scene everything fades out together and the real logo is there at once
      veil.style.opacity = (0.75 * exit * (T < AT.land ? getEaseInOut(getPart(AT.dim, AT.clear)) : 1 - getEaseInOut(getPart(AT.land, AT.land + 2)))).toFixed(3);
      ground.style.opacity = exit < 1 ? exit.toFixed(3) : '';
      top.cv.style.opacity = exit < 1 ? exit.toFixed(3) : '';
      k.root.classList.toggle('phx-lit', exit < 1 || T < AT.clear || T >= AT.land + 1.2);
      k.root.classList.toggle('phx-crown', exit < 1 || T < AT.clear || T >= AT.calm);
      k.root.classList.toggle('phx-glint', gl >= 0.25 && gl < 1);
      k.root.classList.toggle('phx-busy', T < AT.calm || leaving);
      // The ground rises over the page only once the ash of the last round has burnt out under it
      ground.classList.toggle('phx-over', T >= AT.code && T < AT.calm);
      ground.classList.toggle('phx-fade', T >= AT.calm - 0.4 && T < AT.calm);
    };

    let playing = true;
    let acc = 0;
    // The soft exit: 1 while the scene shows, down to 0 over EXIT seconds when the hand comes back
    const EXIT = 0.8;
    let exit = 1;
    let leaving = false;
    // The page opens quiet: the logo is home and the ash of the quiet rises under the page
    const setQuiet = () => {
      setBuild();
      T = AT.calm;
    };
    setQuiet();
    // A round starts over from the quiet or from the end of the last one; the ash still in the air burns out while the screen dims
    const setRound = () => {
      const keep = cinders;
      setBuild();
      cinders = keep.filter((p) => !p.up);
      cinders.forEach((p) => { p.old = true; });
    };
    const ctl = {
      speed: 1,
      onframe: null,
      get time() { return T; },
      get playing() { return playing; },
      // The scene is on the screen: from the dimming until the logo is home
      get busy() { return T < AT.calm || leaving; },
      get ended() { return T >= END; },
      play() { playing = true; },
      pause() { playing = false; },
      start() {
        leaving = false;
        exit = 1;
        setRound();
      },
      // The hand is back mid-scene: fade it all out and go on with the quiet
      wake() {
        if (T < AT.calm) leaving = true;
      },
      // Forward is stepped on from now, backward starts over from the seed
      seek(to) {
        to = getClamp(to, 0, END - DT);
        if (to < T) setBuild();
        while (T + DT / 2 < to) setStep(DT);
        acc = 0;
        setFrame();
      },
    };
    k.loop((dt) => {
      if (leaving) {
        exit -= dt / EXIT;
        if (exit <= 0) {
          leaving = false;
          exit = 1;
          setQuiet();
        }
      } else if (playing) {
        acc = Math.min(acc + dt * ctl.speed, 0.25);
        while (acc >= DT) {
          setStep(DT);
          acc -= DT;
        }
      }
      setFrame();
      if (ctl.onframe) ctl.onframe();
    });
    return ctl;
  }

  // The screensaver: after the rest of the hand it starts and runs in a circle while the hand rests; a move, a key, a wheel or a scroll
  // fades a running scene out and the quiet stays as the ground; a paused scene and a hidden tab are left alone
  function setIdle(k, ctl, getWait) {
    const saver = { last: Date.now(), asleep: false, grace: 0 };
    const touch = () => {
      saver.last = Date.now();
      if (!ctl.playing || Date.now() <= saver.grace) return;
      saver.asleep = false;
      ctl.wake();
    };
    ['pointermove', 'pointerdown', 'keydown', 'wheel'].forEach((n) => k.on(k.doc, n, touch, { passive: true }));
    k.on(k.win, 'scroll', touch, { passive: true });
    saver.sleep = () => {
      saver.asleep = true;
      saver.grace = Date.now() + 1000;
      ctl.start();
    };
    saver.left = () => {
      const wait = getWait();
      return wait ? Math.max(0, wait - (Date.now() - saver.last)) : -1;
    };
    const tick = k.win.setInterval(() => {
      if (!k.alive) {
        k.win.clearInterval(tick);
        return;
      }
      const wait = getWait();
      if (!wait || !ctl.playing || ctl.busy || k.doc.hidden) return;
      if (saver.asleep ? ctl.ended : Date.now() - saver.last >= wait) saver.sleep();
    }, 250);
    return saver;
  }

  window.slPhoenix = { AT, ACTS, END, getKit, getScene, setIdle };
})();
