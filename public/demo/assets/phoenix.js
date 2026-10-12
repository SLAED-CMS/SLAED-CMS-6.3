/* Phoenix stand: ten faces of the 8.0 return, played over the live etalon in a frame */
(function () {
  'use strict';

  const frame = document.getElementById('phx-frame');
  const dock = document.getElementById('phx-dock');
  const LOGO = '../../templates/admin/images/logos/';
  const TAU = Math.PI * 2;
  let sheet = '';
  let cur = 1;
  let token = 0;
  let live = null;
  let played = 0;
  let idle = '30000';
  try {
    idle = localStorage.getItem('phx.idle') || idle;
  } catch (e) { /* storage may be off */ }

  const getClamp = (v, a = 0, b = 1) => Math.min(b, Math.max(a, v));
  const getLerp = (a, b, t) => a + (b - a) * t;
  const getEaseInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2);
  const getEaseOut = (t) => 1 - (1 - t) ** 3;
  const getEaseBack = (t) => 1 + 2.2 * (t - 1) ** 3 + 1.2 * (t - 1) ** 2;
  const getQuad = (a, c, b, t) => (1 - t) * (1 - t) * a + 2 * (1 - t) * t * c + t * t * b;
  const getCubic = (a, b, c, d, t) => (1 - t) ** 3 * a + 3 * (1 - t) ** 2 * t * b + 3 * (1 - t) * t * t * c + t ** 3 * d;
  const getRand = (a, b) => a + Math.random() * (b - a);

  // Tools a face gets: the frame document, its logo, the colours of the theme, layers, particles and time
  function getKit(win) {
    const doc = win.document;
    const root = doc.documentElement;
    const brand = doc.querySelector('.sl-brand');
    const logo = brand.querySelector('img');
    const head = doc.querySelector('.sl-admin-header');
    const probe = doc.createElement('i');
    probe.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
    doc.body.append(probe);
    const nodes = [probe];
    const kit = {
      win, doc, root, brand, logo, head,
      alive: true,
      still: win.matchMedia('(prefers-reduced-motion: reduce)').matches || root.hasAttribute('data-sl-still'),
      rgb(name) {
        probe.style.color = 'var(' + name + ')';
        return win.getComputedStyle(probe).color.match(/[\d.]+/g).slice(0, 3).map(Number);
      },
      tone(name) {
        return 'rgb(' + kit.rgb(name).join(', ') + ')';
      },
      // The fire of Phoenix: a warm heart in the blue of the project
      pal() {
        return {
          hot: kit.tone(kit.dark ? '--sl-warning-strong' : '--sl-warning'),
          mid: kit.tone('--sl-warning'),
          deep: kit.tone(kit.dark ? '--sl-warning' : '--sl-warning-strong'),
          cool: kit.tone('--sl-primary'),
          sky: kit.tone('--sl-primary-strong'),
        };
      },
      box() {
        return logo.getBoundingClientRect();
      },
      // The A of the wordmark: its middle and its top, where every flame of the faces starts
      peak() {
        const r = logo.getBoundingClientRect();
        return { x: r.x + r.width * 0.495, y: r.y + r.height * 0.5, top: r.y + r.height * 0.04, r };
      },
      wait(ms) {
        return new Promise((ok) => win.setTimeout(() => kit.alive && ok(), ms));
      },
      tween(ms, fn, ease = (t) => t) {
        return new Promise((ok) => {
          const t0 = win.performance.now();
          let last = t0;
          const step = (now) => {
            if (!kit.alive) return;
            const t = getClamp((now - t0) / ms);
            fn(ease(t), Math.min(0.05, (now - last) / 1000), t);
            last = now;
            if (t < 1) win.requestAnimationFrame(step);
            else ok();
          };
          win.requestAnimationFrame(step);
        });
      },
      loop(fn) {
        let on = true;
        let last = win.performance.now();
        const step = (now) => {
          if (!on || !kit.alive) return;
          if (fn(Math.min(0.05, (now - last) / 1000), now / 1000) === false) return;
          last = now;
          win.requestAnimationFrame(step);
        };
        win.requestAnimationFrame(step);
        return () => { on = false; };
      },
      el(tag, cls, host, html) {
        const n = doc.createElement(tag);
        if (cls) n.className = cls;
        if (html) n.innerHTML = html;
        (host || doc.body).append(n);
        nodes.push(n);
        return n;
      },
      // Undo the run: its layers and copies go, its classes and values leave the page, its loops stop
      reset() {
        kit.alive = false;
        nodes.forEach((n) => n.remove());
        [...root.classList].filter((n) => n.startsWith('phx-') && n !== 'phx-dark').forEach((n) => root.classList.remove(n));
        [...root.style].filter((n) => n.startsWith('--phx-')).forEach((n) => root.style.removeProperty(n));
        const badge = brand.querySelector('.phx-badge');
        badge.classList.remove('phx-badge-old');
        badge.innerHTML = '<b>8.0</b> Phoenix';
      },
      // The logo answers: one soft beat with a blue glow
      beat() {
        root.classList.remove('phx-beat');
        void root.offsetWidth;
        root.classList.add('phx-beat');
      },
      image(name) {
        const img = new win.Image();
        img.src = LOGO + name;
        return img.decode().then(() => img);
      },
      // The quiet after the event: now and then a few sparks leave the top of the A
      sparks() {
        const L = kit.layer();
        const F = kit.field(L);
        const c = kit.pal();
        let next = 1.5;
        let dirty = false;
        kit.loop((dt) => {
          next -= dt;
          if (next <= 0) {
            next = getRand(2.5, 5);
            const P = kit.peak();
            const n = 3 + ((Math.random() * 3) | 0);
            for (let i = 0; i < n; i++) F.add({ x: P.x + getRand(-4, 4), y: P.top + 4, vx: getRand(-18, 18), vy: getRand(-70, -30), grav: -10, life: getRand(0.8, 1.5), size: getRand(0.8, 1.6), color: i % 2 ? c.sky : c.mid });
          }
          if (F.list.length || dirty) {
            L.g.clearRect(0, 0, L.w, L.h);
            F.step(dt);
            dirty = F.list.length > 0;
          }
          return true;
        });
      },
      // A canvas over the whole window, or the ground of a host behind its content
      layer(host) {
        const cv = kit.el('canvas', host ? 'phx-layer phx-layer-in' : 'phx-layer', host);
        cv.setAttribute('aria-hidden', 'true');
        const g = cv.getContext('2d');
        const L = { cv, g, w: 0, h: 0 };
        L.fit = () => {
          const w = host ? host.clientWidth : win.innerWidth;
          const h = host ? host.clientHeight : win.innerHeight;
          const d = win.devicePixelRatio || 1;
          cv.width = Math.round(w * d);
          cv.height = Math.round(h * d);
          if (host) Object.assign(cv.style, { left: 0, top: 0, width: w + 'px', height: h + 'px' });
          g.setTransform(d, 0, 0, d, 0, 0);
          L.w = w;
          L.h = h;
        };
        L.fit();
        win.addEventListener('resize', L.fit);
        return L;
      },
      // The logo as dots: where each opaque pixel lies on the window and what colour it has
      sample(step) {
        const r = logo.getBoundingClientRect();
        return kit.dots(logo, r.x, r.y, r.width, r.height, step);
      },
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
            if (d[i + 3] > 140) out.push({ tx: x0 + x, ty: y0 + y, u: x / c.width, v: y / c.height, rgb: [d[i], d[i + 1], d[i + 2]], c: 'rgb(' + d[i] + ', ' + d[i + 1] + ', ' + d[i + 2] + ')' });
          }
        }
        return out;
      },
      // Sparks: a pool that moves, fades and draws itself on a layer
      field(L) {
        const list = [];
        return {
          list,
          add(p) {
            list.push(Object.assign({ vx: 0, vy: 0, age: 0, life: 1, size: 2, drag: 0.97, grav: 0 }, p));
          },
          step(dt) {
            const g = L.g;
            g.globalCompositeOperation = kit.blend;
            for (let i = list.length - 1; i >= 0; i--) {
              const p = list[i];
              p.age += dt;
              if (p.age >= p.life) {
                list.splice(i, 1);
                continue;
              }
              const k = p.drag ** (dt * 60);
              p.vx *= k;
              p.vy = p.vy * k + p.grav * dt;
              p.x += p.vx * dt;
              p.y += p.vy * dt;
              const a = 1 - p.age / p.life;
              g.fillStyle = p.color;
              if (kit.dark) {
                g.globalAlpha = a * 0.22;
                g.beginPath();
                g.arc(p.x, p.y, p.size * 2.6, 0, TAU);
                g.fill();
              }
              g.globalAlpha = a;
              g.beginPath();
              g.arc(p.x, p.y, p.size * (0.4 + 0.6 * a), 0, TAU);
              g.fill();
            }
            g.globalAlpha = 1;
            g.globalCompositeOperation = 'source-over';
          },
        };
      },
      svg(defs) {
        const n = kit.el('div', '', doc.body, '<svg width="0" height="0" style="position:absolute" aria-hidden="true"><defs>' + defs + '</defs></svg>');
        return n;
      },
      // The heat of the faces: noise that bends what it filters, breathing while the loop runs
      heat(scale) {
        kit.svg('<filter id="phx-heat" x="-20%" y="-40%" width="140%" height="180%"><feTurbulence type="fractalNoise" baseFrequency="0.02 0.09" numOctaves="2" seed="3" result="n"/>'
          + '<feDisplacementMap in="SourceGraphic" in2="n" scale="' + scale + '" xChannelSelector="R" yChannelSelector="G"/></filter>');
        const turb = doc.querySelector('#phx-heat feTurbulence');
        const map = doc.querySelector('#phx-heat feDisplacementMap');
        const stop = kit.loop((dt, t) => {
          turb.setAttribute('baseFrequency', (0.02 + Math.sin(t * 1.7) * 0.004).toFixed(4) + ' ' + (0.09 + Math.cos(t * 2.3) * 0.02).toFixed(4));
        });
        return { set: (s) => map.setAttribute('scale', s), stop };
      },
      // The page ground: it plays the moment over the page, then settles under the content, between blocks and at the edges
      ground() {
        const mid = doc.querySelector('.sl-wrapmid');
        mid.classList.add('phx-ground-host');
        return kit.el('div', 'phx-ground phx-over', mid);
      },
      async settle(gr) {
        gr.classList.add('phx-fade');
        await kit.wait(400);
        gr.classList.remove('phx-over');
        gr.classList.remove('phx-fade');
      },
      // Fire of the faces in WebGL: blue tongues with a warm heart rising from the foot of a host; reach stretches it
      fire(host, reach) {
        const cv = kit.el('canvas', 'phx-layer phx-layer-in', host);
        const gl = cv.getContext('webgl', { premultipliedAlpha: false });
        if (!gl) return null;
        const fs = `precision mediump float;
uniform vec2 res;uniform vec2 peak;uniform float time;uniform float power;uniform float reach;uniform vec3 c1;uniform vec3 c2;uniform vec3 c3;
float hash(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453);}
float noise(vec2 p){vec2 i=floor(p),f=fract(p);vec2 u=f*f*(3.-2.*f);
return mix(mix(hash(i),hash(i+vec2(1.,0.)),u.x),mix(hash(i+vec2(0.,1.)),hash(i+vec2(1.,1.)),u.x),u.y);}
float fbm(vec2 p){float v=0.,a=.5;for(int i=0;i<5;i++){v+=a*noise(p);p*=2.03;a*=.5;}return v;}
void main(){
  float s=min(res.y,180.*reach);vec2 uv=gl_FragCoord.xy/s;float h=gl_FragCoord.y/res.y*reach;
  vec2 q=vec2(uv.x*4.2,uv.y*1.5-time*1.1);
  float n=fbm(q+vec2(fbm(q*1.6+time*.3)*.9,0.));
  float d=distance(gl_FragCoord.xy,peak)/res.y;
  float flame=n*1.15-h*(1.75-power*1.25)+max(0.,.6-d)*.4*power-.12;
  float f=smoothstep(.0,.42,flame);
  vec3 col=mix(c1,c2,smoothstep(.5,.85,f));col=mix(col,c3,smoothstep(.88,1.,f));
  vec2 g=vec2(uv.x*26.,uv.y*5.-time*1.2);vec2 id=floor(g);vec2 fr=fract(g)-.5;float r=hash(id);
  float sp=step(.9,r)*smoothstep(.09,.0,length(fr+vec2(sin(time*2.+r*6.)*.25,0.)))*(1.-min(1.,h*.6));
  float a=clamp(f*(.3+.5*power)+sp*.9,0.,.85);
  gl_FragColor=vec4(mix(col,mix(c1,c2,r),sp),a);
}`;
        const make = (type, src) => {
          const sh = gl.createShader(type);
          gl.shaderSource(sh, src);
          gl.compileShader(sh);
          return sh;
        };
        const prog = gl.createProgram();
        gl.attachShader(prog, make(gl.VERTEX_SHADER, 'attribute vec2 p;void main(){gl_Position=vec4(p,0.,1.);}'));
        gl.attachShader(prog, make(gl.FRAGMENT_SHADER, fs));
        gl.linkProgram(prog);
        gl.useProgram(prog);
        gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
        gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
        const at = gl.getAttribLocation(prog, 'p');
        gl.enableVertexAttribArray(at);
        gl.vertexAttribPointer(at, 2, gl.FLOAT, false, 0, 0);
        const u = (n) => gl.getUniformLocation(prog, n);
        const vec = (n) => kit.rgb(n).map((v) => v / 255);
        // Blue at the edges, warm toward the heart, the heart in whichever warm token is the brighter one on this ground
        gl.uniform3fv(u('c1'), vec('--sl-primary'));
        gl.uniform3fv(u('c2'), vec('--sl-warning'));
        gl.uniform3fv(u('c3'), vec(kit.dark ? '--sl-warning-strong' : '--sl-warning'));
        gl.uniform1f(u('reach'), reach);
        let power = 0;
        const t0 = win.performance.now();
        const fit = () => {
          const d = win.devicePixelRatio || 1;
          const w = host.clientWidth;
          const h = host.clientHeight;
          cv.width = Math.round(w * d);
          cv.height = Math.round(h * d);
          Object.assign(cv.style, { left: 0, top: 0, width: w + 'px', height: h + 'px' });
          gl.viewport(0, 0, cv.width, cv.height);
          gl.uniform2f(u('res'), cv.width, cv.height);
          const P = kit.peak();
          const hb = host.getBoundingClientRect();
          gl.uniform2f(u('peak'), (P.x - hb.x) * d, (hb.bottom - P.y) * d);
        };
        fit();
        win.addEventListener('resize', fit);
        const stop = kit.loop(() => {
          if (doc.hidden) return true;
          gl.uniform1f(u('time'), (win.performance.now() - t0) / 1000);
          gl.uniform1f(u('power'), power);
          gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
          return true;
        });
        return { set: (v) => { power = v; }, stop };
      },
      lit() {
        root.classList.add('phx-lit');
      },
      crown() {
        root.classList.add('phx-crown');
      },
      // Where the A lies, for the CSS of a face: inside the header and on the window
      mark() {
        const p = kit.peak();
        const h = head.getBoundingClientRect();
        root.style.setProperty('--phx-ax', (p.x - h.x) + 'px');
        root.style.setProperty('--phx-vx', p.x + 'px');
        root.style.setProperty('--phx-vy', p.y + 'px');
        return p;
      },
    };
    const bg = kit.rgb('--sl-bg');
    kit.dark = bg[0] * 0.3 + bg[1] * 0.59 + bg[2] * 0.11 < 128;
    kit.blend = kit.dark ? 'lighter' : 'source-over';
    root.classList.toggle('phx-dark', kit.dark);
    return kit;
  }

  const FACES = [
    {
      name: 'Из пепла',
      note: 'Пепел лежит под шапкой, разгорается и слетается в буквы, каждая искра занимает свой пиксель логотипа; когда логотип собран, остатки улетают искрами вверх. Canvas, ~2 000 частиц, 3,5 с, без библиотек.',
      async run(k) {
        const L = k.layer();
        const g = L.g;
        const ash = k.tone('--sl-text-subtle');
        const c = k.pal();
        const red = c.mid;
        const orange = c.sky;
        const hot = c.cool;
        const r = k.box();
        const ax = k.peak().x;
        const F = k.field(L);
        const pts = k.sample(3).map((p) => {
          const x = r.x - 60 + Math.random() * (r.width + 120);
          const y = r.bottom + 40 + Math.random() * 180;
          return Object.assign(p, { x, y, d: Math.abs(p.tx - ax) / r.width * 0.5 + Math.random() * 0.2, cx: (x + p.tx) / 2 + getRand(-90, 90), cy: Math.min(y, p.ty) - getRand(30, 120) });
        });
        await k.tween(2800, (t, dt) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          for (const p of pts) {
            const s = getClamp((t - p.d) / 0.55);
            const e = getEaseInOut(s);
            const x = getQuad(p.x, p.cx, p.tx, e) + (s < 1 ? Math.sin(t * 20 + p.tx) * 1.5 : 0);
            const y = getQuad(p.y - t * 20, p.cy, p.ty, e);
            g.fillStyle = s <= 0 ? ash : s < 0.45 ? red : s < 0.85 ? orange : s < 1 ? hot : p.c;
            g.globalAlpha = s <= 0 ? 0.55 : 1;
            g.fillRect(x - 1.3, y - 1.3, 2.6, 2.6);
            if (s > 0.1 && s < 0.9 && Math.random() < 0.012) F.add({ x, y, vx: getRand(-20, 20), vy: getRand(-90, -30), life: 0.7, size: 1.4, color: c.mid });
          }
          g.globalAlpha = 1;
          F.step(dt);
        });
        k.lit();
        await k.tween(900, (t, dt) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalAlpha = 1 - t;
          for (const p of pts) {
            g.fillStyle = p.c;
            g.fillRect(p.tx - 1.3, p.ty - 1.3, 2.6, 2.6);
          }
          for (let i = 0; i < 5; i++) {
            const p = pts[(Math.random() * pts.length) | 0];
            F.add({ x: p.tx, y: p.ty, vx: getRand(-25, 25), vy: getRand(-160, -60), life: getRand(0.6, 1.3), size: getRand(1, 2.2), color: Math.random() < 0.5 ? c.sky : c.mid });
          }
          g.globalAlpha = 1;
          F.step(dt);
        });
        k.crown();
        k.loop((dt) => {
          g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
          if (!F.list.length) L.cv.remove();
          return F.list.length > 0;
        });
      },
    },
    {
      name: 'Возгорание',
      note: 'Холодный серый логотип загорается снизу: пламя поднимается по буквам, края дрожат от жара, искры отрываются вверх; потом огонь остывает в родной синий. CSS-маска по форме логотипа + SVG-фильтр жара, 4 с.',
      async run(k) {
        const L = k.layer();
        const F = k.field(L);
        const pts = k.sample(4);
        const r = k.box();
        const heat = k.heat(14);
        const burn = k.el('span', 'phx-burn', k.brand);
        k.el('span', 'phx-shape phx-fire', burn);
        const c = k.pal();
        const tones = [c.mid, c.sky, c.cool];
        const sparks = k.loop((dt) => {
          L.g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
        });
        await k.tween(1700, (t) => {
          k.root.style.setProperty('--phx-burn', t.toFixed(3));
          const line = r.bottom - r.height * t;
          for (let i = 0; i < 4; i++) {
            const p = pts[(Math.random() * pts.length) | 0];
            if (p.ty < line) continue;
            F.add({ x: p.tx, y: p.ty, vx: getRand(-20, 20), vy: getRand(-150, -50), life: getRand(0.5, 1.1), size: getRand(1, 2), color: tones[i % 3] });
          }
        }, getEaseInOut);
        await k.wait(500);
        k.lit();
        k.root.classList.add('phx-cool');
        await k.tween(1200, (t) => heat.set((14 * (1 - t)).toFixed(1)));
        k.crown();
        await k.wait(1400);
        heat.stop();
        sparks();
        burn.remove();
        L.cv.remove();
      },
    },
    {
      name: 'Восход',
      note: 'Фон шапки: из-под шапки поднимается солнце, за логотипом медленно вращаются лучи, по странице проходит тёплый рассвет, логотип всплывает из дымки, по шапке пробегает блик. Потом солнце остаётся неярким тёплым фоном шапки. Только CSS, 3 с.',
      async run(k) {
        k.mark();
        const sky = k.el('span', 'phx-sky', k.head);
        k.el('span', 'phx-rays', sky);
        k.el('span', 'phx-sun', sky);
        k.el('span', 'phx-flare', sky);
        k.el('div', 'phx-dawn');
        await k.wait(60);
        k.root.classList.add('phx-go');
        await k.wait(1300);
        k.lit();
        await k.wait(700);
        k.root.classList.add('phx-flared');
        await k.wait(500);
        k.crown();
        await k.wait(1400);
        k.root.classList.add('phx-calm');
      },
    },
    {
      name: 'Крылья',
      note: 'Буква A — тело птицы: она вспыхивает, из неё раскрываются огненные крылья из перьев, делают взмах и рассыпаются искрами; логотип наливается цветом. Canvas, перья рисуются кодом, 4 с.',
      async run(k) {
        const L = k.layer();
        const g = L.g;
        const F = k.field(L);
        const P = k.peak();
        const root = { x: P.x, y: P.r.y + P.r.height * 0.42 };
        const c = k.pal();
        const size = getClamp(P.r.width / 256, 0.55, 1.2);
        const tips = [];
        const deg = Math.PI / 180;
        // One feather: a flame tongue from its base along an angle, curling at the tip, warm at the root and red at the end
        const drawFeather = (bx, by, a, len, s, alpha) => {
          const tx = bx + s * Math.cos(a) * len;
          const ty = by + Math.sin(a) * len;
          const nx = -Math.sin(a) * s;
          const ny = Math.cos(a);
          const w = len * 0.24;
          const grad = g.createLinearGradient(bx, by, tx, ty);
          grad.addColorStop(0, c.hot);
          grad.addColorStop(0.3, c.mid);
          grad.addColorStop(0.62, c.sky);
          grad.addColorStop(1, c.cool);
          g.globalAlpha = alpha;
          g.fillStyle = grad;
          g.beginPath();
          g.moveTo(bx - nx * w * 0.3, by - ny * w * 0.3);
          g.bezierCurveTo(bx + (tx - bx) * 0.35 + nx * w, by + (ty - by) * 0.35 + ny * w, bx + (tx - bx) * 0.8 + nx * w * 0.6, by + (ty - by) * 0.8 + ny * w * 0.6, tx + nx * w * 0.35, ty + ny * w * 0.35);
          g.bezierCurveTo(bx + (tx - bx) * 0.75 - nx * w * 0.1, by + (ty - by) * 0.75 - ny * w * 0.1, bx + (tx - bx) * 0.3 - nx * w * 0.5, by + (ty - by) * 0.3 - ny * w * 0.5, bx - nx * w * 0.3, by - ny * w * 0.3);
          g.fill();
          tips.push([tx, ty]);
        };
        // A wing growing out of the A: the bone rises, primaries fan from the wrist, secondaries hang from the bone
        const drawWing = (s, p, alpha) => {
          const grow = 0.25 + 0.75 * Math.min(1, p);
          const a1 = getLerp(-80, -38, p) * deg;
          const a2 = getLerp(-85, -14, p) * deg;
          const ex = root.x + s * Math.cos(a1) * 58 * size * grow;
          const ey = root.y + Math.sin(a1) * 58 * size * grow;
          const wx = ex + s * Math.cos(a2) * 70 * size * grow;
          const wy = ey + Math.sin(a2) * 70 * size * grow;
          for (let i = 0; i < 9; i++) {
            const t = i / 8;
            drawFeather(getLerp(root.x, wx, t), getLerp(root.y, wy, t), a2 + getLerp(80, 48, t) * deg * p, getLerp(46, 62, t) * size * grow, s, alpha * 0.8);
          }
          for (let i = 0; i < 9; i++) {
            const t = i / 8;
            drawFeather(wx, wy, a2 + getLerp(-30, 44, t) * deg * p, getLerp(108, 72, t) * size * grow, s, alpha);
          }
          for (let i = 0; i < 7; i++) {
            const t = i / 6;
            drawFeather(getLerp(root.x, wx, t), getLerp(root.y, wy, t), a2 + 26 * deg, 30 * size * grow, s, alpha);
          }
        };
        const paint = (p, alpha, rad) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          g.shadowColor = c.mid;
          g.shadowBlur = 16;
          tips.length = 0;
          g.globalAlpha = alpha;
          g.fillStyle = c.hot;
          g.beginPath();
          g.arc(root.x, root.y, rad * 0.18, 0, TAU);
          g.fill();
          drawWing(-1, p, alpha);
          drawWing(1, p, alpha);
          g.shadowBlur = 0;
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
        };
        await k.tween(500, (t) => paint(0, t, 10 + 40 * t), getEaseOut);
        k.lit();
        await k.tween(1200, (t) => paint(t, 1, 50), getEaseBack);
        await k.tween(1000, (t) => paint(1 - 0.16 * Math.sin(t * TAU) ** 2, 1, 50));
        k.crown();
        await k.tween(1300, (t, dt) => {
          paint(1 + t * 0.06, 1 - t, 50 * (1 - t));
          for (let i = 0; i < 6; i++) {
            const tip = tips[(Math.random() * tips.length) | 0];
            if (tip) F.add({ x: tip[0], y: tip[1], vx: getRand(-30, 30), vy: getRand(-120, -40), life: getRand(0.6, 1.2), size: getRand(1, 2.2), color: Math.random() < 0.5 ? c.mid : c.sky });
          }
          F.step(dt);
        });
        k.loop((dt) => {
          g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
          if (!F.list.length) L.cv.remove();
          return F.list.length > 0;
        });
      },
    },
    {
      name: 'Десять лет',
      note: 'Сначала логотип старый и пыльный, на значке бегут годы от рождения системы в 2005-м; потом ветер сдувает пыль слева направо, она уносится прахом, под ней свежий логотип, по нему пробегает блик, значок становится «8.0 Phoenix». CSS-маска + canvas, 4 с.',
      async run(k) {
        const aged = k.el('img', 'phx-aged', k.brand);
        aged.src = k.logo.currentSrc || k.logo.src;
        aged.alt = '';
        k.el('span', 'phx-shape phx-sheen', k.brand);
        const badge = k.brand.querySelector('.phx-badge');
        const text = badge.innerHTML;
        badge.classList.add('phx-badge-old');
        const L = k.layer();
        const F = k.field(L);
        const r = k.box();
        const pts = k.sample(2).map((p) => {
          const v = Math.round(p.rgb[0] * 0.3 + p.rgb[1] * 0.59 + p.rgb[2] * 0.11);
          return Object.assign(p, { gone: false, c: 'rgb(' + Math.min(255, v + 70) + ', ' + Math.min(255, v + 62) + ', ' + Math.min(255, v + 50) + ')' });
        });
        const now = new Date().getFullYear();
        const from = 2005;
        await k.tween(1900, (t) => { badge.textContent = String(Math.round(getLerp(from, now, t))); });
        await k.wait(250);
        const stop = k.loop((dt) => {
          L.g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
        });
        await k.tween(1700, (t) => {
          k.root.style.setProperty('--phx-sweep', t.toFixed(3));
          const front = r.x + (t * 1.3 - 0.15) * r.width;
          for (const p of pts) {
            if (p.gone || p.tx > front) continue;
            p.gone = true;
            if (Math.random() < 0.55) F.add({ x: p.tx, y: p.ty, vx: getRand(60, 260), vy: getRand(-90, 10), grav: -20, drag: 0.985, life: getRand(0.7, 1.5), size: getRand(0.7, 1.4), color: p.c });
          }
        }, getEaseInOut);
        aged.remove();
        badge.classList.remove('phx-badge-old');
        badge.innerHTML = text;
        k.lit();
        k.crown();
        await k.wait(1800);
        stop();
        L.cv.remove();
      },
    },
    {
      name: 'Перезапуск',
      note: 'В шапке включается старый экран: полоса развёртки, строки загрузки «6.2 … 10 лет … 8.0 Phoenix», шкала пробуждения; экран рвётся помехой, логотип проходит глитч с цветным сдвигом и встаёт. Только CSS + печать текста, 3,5 с.',
      async run(k) {
        const crt = k.el('div', 'phx-crt', k.head);
        const lines = [
          '> SLAED CMS 6.2 ................ спит',
          '> без обновлений: 10+ лет',
          '> пробуждение: ',
          '> SLAED CMS <b>8.0 Phoenix</b> .... готов',
        ];
        await k.wait(450);
        let out = '';
        for (let n = 0; n < lines.length; n++) {
          const line = lines[n];
          if (n === 2) {
            for (let i = 0; i <= 16; i++) {
              crt.innerHTML = out + line + '█'.repeat(i) + '░'.repeat(16 - i) + ' ' + Math.round(i / 16 * 100) + '%<i>▌</i>';
              await k.wait(28);
            }
            out += line + '█'.repeat(16) + ' 100%\n';
            continue;
          }
          const plain = line.replace(/<[^>]+>/g, '');
          for (let i = 0; i <= plain.length; i += 2) {
            crt.innerHTML = out + plain.slice(0, i) + '<i>▌</i>';
            await k.wait(14);
          }
          out += line + '\n';
          crt.innerHTML = out + '<i>▌</i>';
          await k.wait(120);
        }
        await k.wait(500);
        crt.classList.add('phx-crt-off');
        k.root.classList.add('phx-glitch');
        await k.wait(560);
        crt.remove();
        await k.wait(200);
        k.crown();
      },
    },
    {
      name: 'Сверхновая',
      note: 'Логотип сжимается, A набирает жар и взрывается: вспышка, ударная волна идёт по всей странице, карточки и блоки вздрагивают в тот миг, когда волна их задевает, искры летят во все стороны. Canvas + Web Animations, 2,5 с.',
      async run(k) {
        const P = k.mark();
        const L = k.layer();
        const g = L.g;
        const F = k.field(L);
        const c = k.pal();
        await k.tween(800, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          const rad = 6 + 26 * t + Math.sin(t * 60) * 3 * t;
          const gr = g.createRadialGradient(P.x, P.y, 0, P.x, P.y, rad);
          gr.addColorStop(0, c.hot);
          gr.addColorStop(0.5, c.mid);
          gr.addColorStop(1, c.deep);
          g.globalAlpha = 0.25 + 0.6 * t;
          g.fillStyle = gr;
          g.beginPath();
          g.arc(P.x, P.y, rad, 0, TAU);
          g.fill();
          g.globalAlpha = 1;
        }, (t) => t * t);
        k.lit();
        k.el('div', 'phx-flash');
        for (let i = 0; i < 180; i++) {
          const a = Math.random() * TAU;
          const v = getRand(150, 750);
          F.add({ x: P.x, y: P.y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, drag: 0.95, life: getRand(0.6, 1.4), size: getRand(1, 2.6), color: [c.mid, c.sky, c.cool, c.hot][i % 4] });
        }
        const far = Math.hypot(Math.max(P.x, L.w - P.x), Math.max(P.y, L.h - P.y));
        const ms = 1500;
        k.doc.querySelectorAll('.sl-mcard, .sl-block-sidebar, .sl-alert, .sl-dashboard-panel > h2, .sl-toolbar li, .sl-find').forEach((n) => {
          const b = n.getBoundingClientRect();
          if (!b.width || b.top > L.h) return;
          const dx = b.x + b.width / 2 - P.x;
          const dy = b.y + b.height / 2 - P.y;
          const d = Math.hypot(dx, dy) || 1;
          const at = (1 - Math.cbrt(1 - getClamp(d / far))) * ms;
          n.animate([{ transform: 'none' }, { transform: 'translate(' + (dx / d * 7).toFixed(1) + 'px, ' + (dy / d * 7).toFixed(1) + 'px) scale(1.015)' }, { transform: 'none' }], { duration: 480, delay: at, easing: 'ease-out' });
        });
        k.wait(350).then(k.crown);
        await k.tween(ms, (t, dt) => {
          g.clearRect(0, 0, L.w, L.h);
          const rad = far * getEaseOut(t);
          g.globalCompositeOperation = k.blend;
          g.lineWidth = 3 + 26 * (1 - t);
          g.globalAlpha = 0.75 * (1 - t);
          g.strokeStyle = c.sky;
          g.beginPath();
          g.arc(P.x, P.y, rad, 0, TAU);
          g.stroke();
          g.lineWidth = 2 + 8 * (1 - t);
          g.strokeStyle = c.mid;
          g.beginPath();
          g.arc(P.x, P.y, Math.max(0, rad - 22), 0, TAU);
          g.stroke();
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
          F.step(dt);
        });
        k.loop((dt) => {
          g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
          if (!F.list.length) L.cv.remove();
          return F.list.length > 0;
        });
      },
    },
    {
      name: 'Тлеющий фон',
      kind: 'logo',
      note: 'Фон шапки — живой очаг: синее пламя с тёплым сердцем поднимается снизу и заполняет шапку, логотип проступает сквозь жар, потом огонь оседает в тихое тление у нижней кромки с редкими искрами и остаётся фоном. WebGL-шейдер ~40 строк, без библиотек; пауза на скрытой вкладке.',
      async run(k) {
        const fire = k.fire(k.head, 1);
        if (!fire) {
          k.lit();
          k.crown();
          return;
        }
        const heat = k.heat(10);
        await k.tween(900, (t) => fire.set(t), getEaseOut);
        k.lit();
        await k.wait(900);
        await k.tween(2200, (t) => {
          fire.set(getLerp(1, 0.2, t));
          heat.set((10 * (1 - t)).toFixed(1));
        }, getEaseInOut);
        k.crown();
        heat.stop();
      },
    },
    {
      name: 'Полёт',
      note: 'Огненная птица влетает снизу справа через всю страницу, машет крыльями, тянет за собой хвост искр, делает дугу над шапкой и ныряет в букву A; логотип вспыхивает, вверх летит сноп искр. Canvas, 3 с.',
      async run(k) {
        const L = k.layer();
        const g = L.g;
        const F = k.field(L);
        const P = k.peak();
        const c = k.pal();
        const p0 = [L.w * 0.92, L.h + 40];
        const p1 = [L.w * 0.55, L.h * 0.05];
        const p2 = [P.x + 380, P.top - 170];
        const p3 = [P.x, P.top + 6];
        let px = p0[0];
        let py = p0[1];
        // The bird: a glowing head and two wings beating across the line of flight
        const drawBird = (x, y, ang, t) => {
          g.save();
          g.translate(x, y);
          g.rotate(ang);
          g.globalCompositeOperation = k.blend;
          const gr = g.createRadialGradient(0, 0, 0, 0, 0, 22);
          gr.addColorStop(0, c.hot);
          gr.addColorStop(0.35, c.mid);
          gr.addColorStop(1, c.mid);
          g.globalAlpha = 0.35;
          g.fillStyle = gr;
          g.beginPath();
          g.arc(0, 0, 22, 0, TAU);
          g.fill();
          g.globalAlpha = 1;
          const beat = Math.sin(t * 34) * 0.9;
          g.strokeStyle = c.sky;
          g.lineWidth = 3;
          g.lineCap = 'round';
          for (const s of [-1, 1]) {
            g.beginPath();
            g.moveTo(2, 0);
            g.quadraticCurveTo(-6, s * (10 + 8 * beat), -16, s * (20 * (0.5 + 0.5 * Math.abs(beat))));
            g.stroke();
          }
          g.fillStyle = c.hot;
          g.beginPath();
          g.arc(3, 0, 3.5, 0, TAU);
          g.fill();
          g.restore();
        };
        await k.tween(2100, (t, dt, raw) => {
          g.clearRect(0, 0, L.w, L.h);
          const x = getCubic(p0[0], p1[0], p2[0], p3[0], t);
          const y = getCubic(p0[1], p1[1], p2[1], p3[1], t);
          const ang = Math.atan2(y - py, x - px);
          for (let i = 0; i < 7; i++) {
            F.add({ x: x + getRand(-4, 4), y: y + getRand(-4, 4), vx: -Math.cos(ang) * getRand(10, 60) + getRand(-15, 15), vy: -Math.sin(ang) * getRand(10, 60) + getRand(-25, 5), life: getRand(0.4, 1), size: getRand(1, 3), color: [c.mid, c.sky, c.cool][i % 3] });
          }
          F.step(dt);
          drawBird(x, y, ang, raw * 2.1);
          px = x;
          py = y;
        }, (t) => 1 - Math.cos(t * Math.PI / 2) ** 1.4);
        k.lit();
        for (let i = 0; i < 90; i++) {
          const a = getRand(-Math.PI * 0.95, -Math.PI * 0.05);
          const v = getRand(80, 420);
          F.add({ x: P.x, y: P.top + 10, vx: Math.cos(a) * v, vy: Math.sin(a) * v, grav: 160, drag: 0.96, life: getRand(0.6, 1.4), size: getRand(1, 2.4), color: [c.mid, c.deep, c.cool, c.hot][i % 4] });
        }
        k.wait(400).then(k.crown);
        k.loop((dt) => {
          g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
          if (!F.list.length) L.cv.remove();
          return F.list.length > 0;
        });
      },
    },
    {
      name: 'Перо',
      note: 'Тихий вариант: сверху, покачиваясь, падает одно огненное перо и касается буквы A; от неё логотип загорается в обе стороны, сначала тёплым, потом остывает в синий, перо рассыпается искрами. Canvas + CSS, 4,5 с.',
      async run(k) {
        const dim = k.el('img', 'phx-dim', k.brand);
        dim.src = k.logo.currentSrc || k.logo.src;
        dim.alt = '';
        k.brand.prepend(dim);
        const glow = k.el('span', 'phx-shape phx-fire phx-glow-open', k.brand);
        const L = k.layer();
        const g = L.g;
        const F = k.field(L);
        const P = k.peak();
        const c = k.pal();
        // The feather: a shaft with barbs that shorten toward both ends, glowing warm
        const drawFeather = (x, y, rot, alpha) => {
          g.save();
          g.translate(x, y);
          g.rotate(rot);
          g.globalAlpha = alpha;
          g.globalCompositeOperation = k.blend;
          g.lineCap = 'round';
          const len = 84;
          for (let i = 2; i <= 30; i++) {
            const t = i / 30;
            const w = Math.sin(t * Math.PI) ** 0.7 * 18;
            g.strokeStyle = t < 0.35 ? c.hot : t < 0.7 ? c.mid : c.cool;
            g.lineWidth = 1.8;
            for (const s of [-1, 1]) {
              g.beginPath();
              g.moveTo(0, -len / 2 + t * len);
              g.quadraticCurveTo(s * w * 0.6, -len / 2 + t * len - 4, s * w, -len / 2 + t * len - 9);
              g.stroke();
            }
          }
          g.strokeStyle = c.deep;
          g.lineWidth = 2;
          g.beginPath();
          g.moveTo(0, -len / 2 - 8);
          g.lineTo(0, len / 2);
          g.stroke();
          g.restore();
        };
        let fx = P.x;
        let fy = P.top;
        await k.tween(2700, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          fx = P.x + Math.sin(t * TAU * 1.25) * 90 * (1 - t);
          fy = getLerp(-90, P.top - 34, t);
          drawFeather(fx, fy, Math.cos(t * TAU * 1.25) * 0.9 * (1 - t) + 2.3 + 0.5 * t, 1);
        }, (t) => Math.sin(t * Math.PI / 2));
        for (let i = 0; i < 70; i++) {
          F.add({ x: fx + getRand(-10, 10), y: fy + getRand(-28, 28), vx: getRand(-90, 90), vy: getRand(-140, -20), grav: 60, life: getRand(0.6, 1.3), size: getRand(1, 2.2), color: [c.mid, c.deep, c.cool, c.hot][i % 4] });
        }
        const sparks = k.loop((dt) => {
          g.clearRect(0, 0, L.w, L.h);
          F.step(dt);
        });
        await k.tween(1100, (t) => {
          k.root.style.setProperty('--phx-glow', getClamp(t * 1.25).toFixed(3));
          k.root.style.setProperty('--phx-open', getClamp(t * 1.25 - 0.25).toFixed(3));
        }, getEaseInOut);
        k.root.classList.add('phx-cool');
        k.crown();
        await k.wait(1600);
        sparks();
        dim.remove();
        glow.remove();
        L.cv.remove();
      },
    },
    {
      name: 'Исполин',
      kind: 'ground',
      note: 'Снизу через всю страницу поднимается рой искр и складывается в огромную букву A — эмблему логотипа; она вспыхивает, сжимается и влетает в свою A в логотипе, и тот загорается. Потом исполинская A остаётся еле видным дышащим водяным знаком под страницей. Canvas, ~3 000 точек на вход, в покое одна картинка.',
      async run(k) {
        const ground = k.ground();
        const L = k.layer(ground);
        const g = L.g;
        const c = k.pal();
        const r = k.box();
        const img = await k.image('slaed-logo-mark-gradient-blue.svg');
        const H = Math.min(L.h * 0.72, 600);
        const W = H * img.naturalWidth / img.naturalHeight;
        const pts = k.dots(img, (L.w - W) / 2, (L.h - H) / 2, W, H, 6).map((p) => Object.assign(p, {
          x: Math.random() * L.w,
          y: L.h + getRand(10, 160),
          d: (1 - p.v) * 0.35 + Math.random() * 0.15,
          sx: r.x + r.width * (0.378 + p.u * 0.235),
          sy: r.y + p.v * r.height,
        }));
        const dot = (x, y, s, color, a) => {
          g.globalAlpha = a;
          g.fillStyle = color;
          g.fillRect(x - s / 2, y - s / 2, s, s);
        };
        await k.tween(1500, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          for (const p of pts) {
            const s = getClamp((t - p.d) / 0.5);
            const e = getEaseOut(s);
            dot(getLerp(p.x, p.tx, e), getLerp(p.y, p.ty, e), 3, s < 0.5 ? c.mid : s < 1 ? c.sky : p.c, 0.35 + 0.65 * s);
          }
        });
        await k.tween(500, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          const flash = Math.sin(t * Math.PI);
          for (const p of pts) dot(p.tx + getRand(-1, 1), p.ty + getRand(-1, 1), 3 + flash, Math.random() < 0.05 + 0.25 * flash ? c.mid : p.c, 1);
        });
        let lit = false;
        await k.tween(1000, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          for (const p of pts) {
            const e = getEaseInOut(getClamp(t * 1.25 - p.v * 0.25));
            dot(getQuad(p.tx, (p.tx + p.sx) / 2, p.sx, e), getQuad(p.ty, Math.min(p.ty, p.sy) - 120, p.sy, e), getLerp(3, 1.2, e), e > 0.6 ? c.sky : p.c, 1 - e * 0.3);
          }
          if (t > 0.85 && !lit) {
            lit = true;
            k.lit();
            k.beat();
          }
        });
        k.crown();
        await k.tween(400, (t) => {
          g.clearRect(0, 0, L.w, L.h);
          for (const p of pts) dot(p.sx, p.sy, 1.2, c.sky, 0.7 * (1 - t));
        });
        await k.settle(ground);
        g.clearRect(0, 0, L.w, L.h);
        g.globalCompositeOperation = 'source-over';
        for (const p of pts) dot(p.tx, p.ty, 2.4, p.c, k.dark ? 0.16 : 0.1);
        L.cv.classList.add('phx-breath');
      },
    },
    {
      name: 'Лучи',
      kind: 'ground',
      note: 'Буква A вспыхивает, и из неё веером раскрываются лучи света — синие и тёплые — через всю страницу, качаются как прожектор, логотип загорается. Потом лучи уходят под страницу и остаются тихим светом из-за логотипа, медленно покачиваясь. Canvas.',
      async run(k) {
        const ground = k.ground();
        const L = k.layer(ground);
        const g = L.g;
        const c = k.pal();
        const beams = Array.from({ length: 16 }, (_, i) => ({ a: getLerp(-0.12, Math.PI * 0.62, i / 15) + getRand(-0.05, 0.05), w: getRand(0.012, 0.05), warm: i % 4 === 1, ph: Math.random() * TAU }));
        const draw = (power, spread, swing, time) => {
          const P = k.peak();
          const far = Math.hypot(L.w, L.h);
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = 'source-over';
          for (const b of beams) {
            const a = getLerp(Math.PI * 0.3, b.a, spread) + Math.sin(time * 0.35 + b.ph) * 0.05 + swing;
            g.globalAlpha = power * (b.warm ? 0.5 : 0.34) * (0.75 + 0.25 * Math.sin(time * 1.3 + b.ph));
            g.fillStyle = b.warm ? c.mid : c.sky;
            g.beginPath();
            g.moveTo(P.x, P.y);
            g.lineTo(P.x + Math.cos(a - b.w) * far, P.y + Math.sin(a - b.w) * far);
            g.lineTo(P.x + Math.cos(a + b.w) * far, P.y + Math.sin(a + b.w) * far);
            g.fill();
          }
          // The light dies away from its source: only the alpha of this gradient counts
          const fade = g.createRadialGradient(P.x, P.y, 0, P.x, P.y, far * 0.75);
          fade.addColorStop(0, c.sky);
          fade.addColorStop(1, 'transparent');
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'destination-in';
          g.fillStyle = fade;
          g.fillRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          g.globalAlpha = power;
          g.fillStyle = c.hot;
          g.shadowColor = c.mid;
          g.shadowBlur = 30;
          g.beginPath();
          g.arc(P.x, P.y, 6 + 6 * power, 0, TAU);
          g.fill();
          g.shadowBlur = 0;
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
        };
        const t0 = k.win.performance.now();
        const now = () => (k.win.performance.now() - t0) / 1000;
        await k.tween(700, (t) => draw(t, t, 0, now()), getEaseOut);
        k.lit();
        k.beat();
        await k.tween(1700, (t) => draw(1, 1, Math.sin(t * Math.PI * 1.5) * 0.18, now()), getEaseInOut);
        k.crown();
        await k.tween(500, (t) => draw(getLerp(1, 0.5, t), 1, 0, now()));
        await k.settle(ground);
        k.loop(() => {
          draw(0.5, 1, Math.sin(now() * 0.12) * 0.06, now());
          return true;
        });
      },
    },
    {
      name: 'Угли',
      kind: 'ground',
      note: 'Буква A выбрасывает фонтан углей, и одновременно снизу взлетает рой синих и тёплых углей через всю страницу, крупные расплываются как боке. Потом рой редеет, угли тихо поднимаются по фону, а из A время от времени вылетает пара искр — фон и логотип горят одним огнём. Canvas, ~90 частиц в покое.',
      async run(k) {
        const ground = k.ground();
        const L = k.layer(ground);
        const g = L.g;
        const c = k.pal();
        const F = k.field(L);
        const tones = [c.cool, c.sky, c.mid, c.hot, c.sky];
        const list = [];
        const add = (fast) => {
          const big = Math.random() < 0.18;
          list.push({
            x: Math.random() * L.w,
            y: L.h + getRand(0, fast ? 200 : 40),
            r: big ? getRand(8, 22) : getRand(1.6, 3.6),
            vy: (fast ? getRand(120, 340) : getRand(18, 55)) * (big ? 0.6 : 1),
            sway: getRand(10, 40),
            ph: Math.random() * TAU,
            a: big ? getRand(0.12, 0.26) : getRand(0.6, 1),
            color: tones[(Math.random() * tones.length) | 0],
          });
        };
        const fountain = (n, force) => {
          const P = k.peak();
          for (let i = 0; i < n; i++) {
            const a = getRand(-Math.PI * 0.85, -Math.PI * 0.15);
            const v = getRand(0.3, 1) * force;
            F.add({ x: P.x + getRand(-5, 5), y: P.top + 4, vx: Math.cos(a) * v, vy: Math.sin(a) * v, grav: force * 0.6, drag: 0.985, life: getRand(0.8, 1.8), size: getRand(1.2, 3), color: tones[i % tones.length] });
          }
        };
        for (let i = 0; i < 320; i++) add(true);
        k.lit();
        k.beat();
        fountain(140, 420);
        k.wait(900).then(k.crown);
        k.wait(2300).then(() => k.settle(ground));
        let next = 5;
        k.loop((dt, time) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          for (let i = list.length - 1; i >= 0; i--) {
            const p = list[i];
            p.y -= p.vy * dt;
            p.vy = Math.max(p.vy * (1 - dt * 0.6), p.r > 5 ? 14 : 22);
            if (p.y < -30) {
              list.splice(i, 1);
              continue;
            }
            const x = p.x + Math.sin(time * 0.8 + p.ph) * p.sway;
            g.globalAlpha = p.a * (p.r > 5 ? 1 : 0.6 + 0.4 * Math.sin(time * 6 + p.ph));
            g.fillStyle = p.color;
            g.beginPath();
            g.arc(x, p.y, p.r, 0, TAU);
            g.fill();
          }
          if (list.length < 90 && Math.random() < 0.5) add(false);
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
          F.step(dt);
          next -= dt;
          if (next <= 0) {
            next = getRand(4, 7);
            fountain(4, 120);
          }
          return true;
        });
      },
    },
    {
      name: 'Орбиты',
      kind: 'ground',
      note: 'Пунктирные орбиты эмблемы с сайта: логотип бьётся как сердце, и с каждым ударом из буквы A расходится кольцо, кольца встают орбитами на весь фон, одно с делениями, как шкалы кокпита; тёплые кометы вылетают из A на свои орбиты. В покое кольца медленно вращаются, а раз в восемь секунд логотип тихо ударяет и пускает по ним волну. Canvas.',
      async run(k) {
        const ground = k.ground();
        const L = k.layer(ground);
        const g = L.g;
        const c = k.pal();
        const P0 = k.peak();
        const far = Math.hypot(Math.max(P0.x, L.w - P0.x), Math.max(P0.y, L.h - P0.y));
        const rings = [];
        for (let r = 70, i = 0; r < far; r *= 1.42, i++) {
          rings.push({ r, i, dash: i % 3 === 1 ? [1, 7] : i % 3 === 2 ? [14, 10] : [4, 6], dir: i % 2 ? -1 : 1, speed: getRand(6, 16) / (1 + i * 0.4), ticks: i === 2 });
        }
        const comets = [1, 2, 4].filter((i) => rings[i]).map((i) => ({ ring: rings[i], a: Math.random() * TAU, v: (rings[i].dir * getRand(0.5, 0.9)) / (1 + i * 0.3) }));
        const waves = [];
        const t0 = k.win.performance.now();
        const pulse = (strength) => {
          waves.push({ at: (k.win.performance.now() - t0) / 1000, strength });
          k.beat();
        };
        k.lit();
        pulse(1);
        k.wait(260).then(() => pulse(1));
        k.wait(520).then(() => pulse(1));
        k.wait(1200).then(k.crown);
        k.wait(1800).then(() => k.settle(ground));
        let next = 8;
        k.loop((dt) => {
          const t = (k.win.performance.now() - t0) / 1000;
          const P = k.peak();
          g.clearRect(0, 0, L.w, L.h);
          g.lineWidth = 1.5;
          g.strokeStyle = c.sky;
          for (const o of rings) {
            const grow = getEaseOut(getClamp((t - o.i * 0.14) / 1.1));
            if (grow <= 0) continue;
            const r = o.r * grow;
            g.globalAlpha = Math.max(0.16, 0.5 - o.i * 0.04) * (1 + (1 - getClamp(t - 1)) * 0.6);
            g.setLineDash(o.dash);
            g.lineDashOffset = o.dir * t * o.speed;
            g.beginPath();
            g.arc(P.x, P.y, r, 0, TAU);
            g.stroke();
            if (o.ticks) {
              g.setLineDash([]);
              g.beginPath();
              for (let n = 0; n < 72; n++) {
                const a = n / 72 * TAU + t * 0.05 * o.dir;
                const len = n % 6 ? 4 : 10;
                g.moveTo(P.x + Math.cos(a) * r, P.y + Math.sin(a) * r);
                g.lineTo(P.x + Math.cos(a) * (r - len), P.y + Math.sin(a) * (r - len));
              }
              g.stroke();
            }
          }
          g.setLineDash([]);
          for (let i = waves.length - 1; i >= 0; i--) {
            const w = waves[i];
            const s = (t - w.at) / 2.2;
            if (s >= 1) {
              waves.splice(i, 1);
              continue;
            }
            g.globalAlpha = (1 - s) * 0.7 * w.strength;
            g.lineWidth = 3 * (1 - s) + 1;
            g.strokeStyle = c.mid;
            g.beginPath();
            g.arc(P.x, P.y, far * getEaseOut(s), 0, TAU);
            g.stroke();
          }
          g.globalCompositeOperation = k.blend;
          for (const m of comets) {
            const start = 0.6 + m.ring.i * 0.14;
            if (t < start) continue;
            const out = getEaseOut(getClamp((t - start) / 0.9));
            m.a += m.v * dt;
            for (let n = 0; n < 16; n++) {
              const a = m.a - Math.sign(m.v) * n * 0.022;
              g.globalAlpha = 1 - n / 16;
              g.fillStyle = n < 3 ? c.deep : c.mid;
              g.beginPath();
              g.arc(P.x + Math.cos(a) * m.ring.r * out, P.y + Math.sin(a) * m.ring.r * out, Math.max(0.6, 3 - n * 0.15), 0, TAU);
              g.fill();
            }
          }
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
          next -= dt;
          if (next <= 0) {
            next = 8;
            pulse(0.45);
          }
          return true;
        });
      },
    },
    {
      name: 'Фитиль',
      kind: 'ground',
      note: 'Из буквы A падает искра на рамку страницы, и огонь бежит по рамке в обе стороны, как по бикфордову шнуру, оставляя светящийся контур; встретившись внизу, огни взрываются, вся рамка вспыхивает, логотип загорается. Потом рамка еле светится, а раз в девять секунд по ней пробегает одна тихая комета и, вернувшись к A, будит логотип. Canvas.',
      async run(k) {
        const ground = k.ground();
        const L = k.layer(ground);
        const g = L.g;
        const c = k.pal();
        const F = k.field(L);
        const wrap = k.doc.querySelector('.sl-admin-shell');
        const rad = parseFloat(k.win.getComputedStyle(wrap).borderTopLeftRadius) || 0;
        const P0 = k.peak();
        let pts = [];
        let N = 0;
        let i0 = 0;
        // The frame of the page as points two pixels apart, in page coordinates, clockwise from the top left
        const build = () => {
          const b = wrap.getBoundingClientRect();
          const sy = k.win.scrollY;
          const x0 = b.x + 6;
          const y0 = b.y + sy + 6;
          const x1 = b.right - 6;
          const y1 = b.bottom + sy - 6;
          const out = [];
          const line = (ax, ay, bx, by) => {
            const n = Math.max(1, Math.round(Math.hypot(bx - ax, by - ay) / 2));
            for (let i = 0; i < n; i++) out.push([getLerp(ax, bx, i / n), getLerp(ay, by, i / n)]);
          };
          const arc = (cx, cy, a0) => {
            const n = Math.max(1, Math.round(rad * Math.PI / 4));
            for (let i = 0; i < n; i++) out.push([cx + Math.cos(a0 + i / n * Math.PI / 2) * rad, cy + Math.sin(a0 + i / n * Math.PI / 2) * rad]);
          };
          line(x0 + rad, y0, x1 - rad, y0);
          arc(x1 - rad, y0 + rad, -Math.PI / 2);
          line(x1, y0 + rad, x1, y1 - rad);
          arc(x1 - rad, y1 - rad, 0);
          line(x1 - rad, y1, x0 + rad, y1);
          arc(x0 + rad, y1 - rad, Math.PI / 2);
          line(x0, y1 - rad, x0, y0 + rad);
          arc(x0 + rad, y0 + rad, Math.PI);
          pts = out;
          N = out.length;
          i0 = Math.round(getClamp((P0.x - x0 - rad) / Math.max(1, x1 - x0 - 2 * rad)) * (x1 - x0 - 2 * rad) / 2);
        };
        build();
        new k.win.ResizeObserver(build).observe(wrap);
        const at = (i) => {
          const p = pts[((Math.round(i) % N) + N) % N];
          return [p[0], p[1] - k.win.scrollY];
        };
        // The lit fuse: a blue glow with a warm thread inside it
        const trace = (from, to, alpha, width) => {
          g.beginPath();
          for (let i = from; i <= to; i += 2) {
            const p = at(i);
            if (i === from) g.moveTo(p[0], p[1]);
            else g.lineTo(p[0], p[1]);
          }
          g.globalAlpha = alpha;
          g.lineWidth = width + 1.5;
          g.strokeStyle = c.sky;
          g.shadowColor = c.sky;
          g.shadowBlur = 10;
          g.stroke();
          g.shadowBlur = 0;
          g.lineWidth = Math.max(1, width * 0.45);
          g.strokeStyle = c.mid;
          g.stroke();
        };
        const head = (i, size) => {
          const p = at(i);
          g.globalAlpha = 1;
          g.fillStyle = c.hot;
          g.shadowColor = c.mid;
          g.shadowBlur = 18;
          g.beginPath();
          g.arc(p[0], p[1], size, 0, TAU);
          g.fill();
          g.shadowBlur = 0;
          return p;
        };
        // A spark falls from the top of the A onto the frame
        await k.tween(450, (t) => {
          const P = k.peak();
          const top = at(i0);
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          g.globalAlpha = 1;
          g.fillStyle = c.hot;
          g.shadowColor = c.mid;
          g.shadowBlur = 20;
          g.beginPath();
          g.arc(P.x, getLerp(P.top, top[1], t), 3 + 3 * t, 0, TAU);
          g.fill();
          g.shadowBlur = 0;
        }, (t) => t * t);
        await k.tween(2300, (t, dt) => {
          const d = t * N / 2;
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          trace(i0 - d, i0 + d, 1, 3);
          for (const p of [head(i0 + d, 3.5), head(i0 - d, 3.5)]) {
            F.add({ x: p[0], y: p[1], vx: getRand(-40, 40), vy: getRand(-60, 20), grav: 60, life: getRand(0.4, 0.9), size: getRand(0.8, 2), color: Math.random() < 0.5 ? c.mid : c.sky });
          }
          F.step(dt);
        }, getEaseInOut);
        const meet = at(i0 + N / 2);
        for (let n = 0; n < 110; n++) {
          const a = Math.random() * TAU;
          const v = getRand(80, 420);
          F.add({ x: meet[0], y: meet[1], vx: Math.cos(a) * v, vy: Math.sin(a) * v, grav: 120, drag: 0.96, life: getRand(0.6, 1.4), size: getRand(1, 2.6), color: [c.mid, c.sky, c.hot][n % 3] });
        }
        k.lit();
        k.beat();
        k.wait(300).then(k.crown);
        await k.tween(1200, (t, dt) => {
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          trace(0, N, getLerp(1, 0.35, t), getLerp(8, 2, t));
          F.step(dt);
        }, getEaseOut);
        await k.settle(ground);
        let lap = -1;
        const t0 = k.win.performance.now();
        k.loop((dt) => {
          const t = (k.win.performance.now() - t0) / 1000;
          g.clearRect(0, 0, L.w, L.h);
          g.globalCompositeOperation = k.blend;
          trace(0, N, k.dark ? 0.3 : 0.35, 2);
          const s = (t % 9) / 4.5;
          if (s < 1) {
            const i = i0 + getEaseInOut(s) * N;
            for (let n = 0; n < 40; n++) {
              const p = at(i - n * 3);
              g.globalAlpha = (1 - n / 40) * 0.9;
              g.fillStyle = n < 4 ? c.hot : c.sky;
              g.fillRect(p[0] - 1.5, p[1] - 1.5, 3, 3);
            }
          }
          if (Math.floor(t / 9) !== lap && s >= 1) {
            lap = Math.floor(t / 9);
            k.beat();
          }
          F.step(dt);
          g.globalAlpha = 1;
          g.globalCompositeOperation = 'source-over';
          return true;
        });
      },
    },
  ];

  const pad = (n) => String(n).padStart(2, '0');

  // Lay the sheet and the badge into the frame as soon as its document exists, then play the face once it is loaded
  function setWatch(t, old) {
    const tick = () => {
      if (t !== token) return;
      const doc = frame.contentDocument;
      if (doc && doc !== old && doc.documentElement && doc.URL.includes('/admin/index.html')) {
        const root = doc.documentElement;
        if (!root.dataset.phx && doc.head) {
          root.dataset.phx = pad(cur);
          root.dataset.phxKind = FACES[cur - 1].kind || 'logo';
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

  async function setRun(win, t) {
    const doc = win.document;
    doc.documentElement.removeAttribute('data-demo-marks');
    const brand = doc.querySelector('.sl-brand');
    if (!brand) return;
    brand.insertAdjacentHTML('beforeend', '<span class="phx-badge" aria-hidden="true"><b>8.0</b> Phoenix</span>');
    const img = brand.querySelector('img');
    await img.decode().catch(() => {});
    if (t !== token) return;
    setPlay(win);
    setIdle(win, t);
  }

  // One play of the face: the event, then the quiet; a play before it is undone first
  async function setPlay(win) {
    if (live) live.reset();
    const kit = getKit(win);
    live = kit;
    played = Date.now();
    if (kit.still) {
      kit.lit();
      kit.crown();
      dock.querySelector('[data-phx-note]').textContent = 'Движение выключено (система или «Опции → Движение»): эффект пропущен, сразу конечный вид.';
      return;
    }
    const face = FACES[cur - 1];
    await face.run(kit);
    if (kit.alive && face.kind !== 'ground') kit.sparks();
  }

  // The hand has rested longer than the chosen time: the event plays again
  function setIdle(win, t) {
    let last = Date.now();
    const touch = () => { last = Date.now(); };
    ['pointermove', 'pointerdown', 'keydown', 'wheel'].forEach((n) => {
      win.document.addEventListener(n, touch, { passive: true });
      document.addEventListener(n, touch, { passive: true });
    });
    win.addEventListener('scroll', touch, { passive: true });
    const tick = win.setInterval(() => {
      if (t !== token) {
        win.clearInterval(tick);
        return;
      }
      const wait = Number(idle);
      if (wait && Date.now() - last > wait && Date.now() - played > wait) {
        last = Date.now();
        setPlay(win);
      }
    }, 500);
  }

  function setFace(n) {
    cur = ((n - 1 + FACES.length) % FACES.length) + 1;
    token++;
    history.replaceState(null, '', '#' + pad(cur));
    dock.querySelectorAll('[data-phx-face]').forEach((b) => b.setAttribute('aria-pressed', String(Number(b.dataset.phxFace) === cur)));
    dock.querySelector('[data-phx-name]').textContent = pad(cur) + ' · ' + FACES[cur - 1].name;
    dock.querySelector('[data-phx-note]').textContent = FACES[cur - 1].note;
    live = null;
    const old = frame.contentDocument;
    frame.src = 'admin/index.html?phx=' + token;
    setWatch(token, old);
  }

  // The desk lists the faces in two groups, the logo and the ground of the page
  dock.querySelector('[data-phx-list]').innerHTML = FACES.map((f, i) => (i === 0 ? '<span class="phx-dock-group">Логотип</span>' : f.kind === 'ground' && FACES[i - 1].kind !== 'ground' ? '<span class="phx-dock-group">Фон</span>' : '')
    + '<button type="button" data-phx-face="' + (i + 1) + '" title="' + f.name + '" aria-pressed="false">' + pad(i + 1) + '</button>').join('');

  const IDLE = [['10000', '10 с'], ['30000', '30 с'], ['120000', '2 мин'], ['0', 'Выкл']];
  const setIdleList = () => {
    dock.querySelector('[data-phx-idle]').innerHTML = '<span class="phx-dock-group">Повтор без действий</span>'
      + IDLE.map(([v, n]) => '<button type="button" data-phx-idle-v="' + v + '" aria-pressed="' + (v === idle) + '">' + n + '</button>').join('');
  };
  setIdleList();

  dock.addEventListener('click', (e) => {
    const rest = e.target.closest('[data-phx-idle-v]');
    if (rest) {
      idle = rest.dataset.phxIdleV;
      try {
        localStorage.setItem('phx.idle', idle);
      } catch (err) { /* storage may be off */ }
      setIdleList();
    }
    const face = e.target.closest('[data-phx-face]');
    if (face) setFace(Number(face.dataset.phxFace));
    if (e.target.closest('[data-phx-again]')) setFace(cur);
    const fold = e.target.closest('[data-phx-fold]');
    if (fold) {
      const on = dock.toggleAttribute('data-fold');
      fold.setAttribute('aria-expanded', String(!on));
    }
  });

  // Keys of the stand work from the dock and from inside the frame alike
  const setKeys = (e) => {
    if (e.target.closest && e.target.closest('input, textarea, select, [contenteditable]')) return;
    if (e.key === 'ArrowRight') setFace(cur + 1);
    else if (e.key === 'ArrowLeft') setFace(cur - 1);
    else if (e.key === 'r' || e.key === 'R' || e.key === 'к' || e.key === 'К') setFace(cur);
  };
  document.addEventListener('keydown', setKeys);
  frame.addEventListener('load', () => frame.contentDocument.addEventListener('keydown', setKeys));

  fetch('assets/phoenix-fx.css?v=' + Date.now()).then((r) => r.text()).then((css) => {
    sheet = css;
    setFace(Number(location.hash.slice(1)) || 1);
  });
})();
