(function () {
    'use strict';
    var phxTau = Math.PI * 2;
    var phxStep = 1 / 60;
    var phxExit = 0.8;
    var phxGlint = 6;
    // Д11 Lines of the system itself: the rain of the first act is made of them, the name of the release closes it
    var phxCode = [
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
        'foreach ($new as $location => $md5) {',
        'if (!$refs) return $out;',
        'function getThemeHookVars(string $hook): array {',
        'function getSchedulerSchedule(array|string $job): string {'
    ];
    // The timeline in seconds: every act of the scene starts here
    var phxAt = { dim: 0, code: 1.5, clear: 4.5, rise: 9, hold: 16.5, fall: 18.5, form: 22, big: 28, melt: 30.5, fly: 34, small: 39, dust: 41, land: 43.5, calm: 47 };
    var phxEnd = 60;
    var rnd = Math.random;
    // Chance comes from a seed, so every round of the scene is the same
    function getPhxSeed(seed) {
        return function () {
            seed = (seed + 0x6d2b79f5) | 0;
            var t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
            t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
            return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
        };
    }
    // A number held between a and b, by default between 0 and 1
    function getPhxClamp(v, a, b) {
        return Math.min(b === undefined ? 1 : b, Math.max(a || 0, v));
    }
    // The point a share t of the way from a to b
    function getPhxLerp(a, b, t) {
        return a + (b - a) * t;
    }
    // A cubic curve that starts and ends slowly
    function getPhxInOut(t) {
        return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
    }
    // A cubic curve that ends slowly
    function getPhxOut(t) {
        return 1 - Math.pow(1 - t, 3);
    }
    // A point on the curve from a through the pull of c to b
    function getPhxQuad(a, c, b, t) {
        return (1 - t) * (1 - t) * a + 2 * (1 - t) * t * c + t * t * b;
    }
    // A number between a and b from the chance of the round
    function getPhxRand(a, b) {
        return a + rnd() * (b - a);
    }
    // The list in an order of the chance of the round
    function getPhxShuffle(list) {
        for (var i = list.length - 1; i > 0; i--) {
            var j = (rnd() * (i + 1)) | 0;
            var one = list[i];
            list[i] = list[j];
            list[j] = one;
        }
        return list;
    }
    // A smooth ramp through the given tones, ready for the canvas: a dot takes its colour by progress, never by a jump
    function getPhxRamp(stops) {
        var out = [];
        for (var i = 0; i < 32; i++) {
            var t = i / 31 * (stops.length - 1);
            var j = Math.min(stops.length - 2, Math.floor(t));
            var tone = [];
            for (var m = 0; m < 3; m++) tone.push(Math.round(getPhxLerp(stops[j][m], stops[j + 1][m], t - j)));
            out.push('rgb(' + tone.join(', ') + ')');
        }
        return out;
    }
    // The tone of a ramp at progress t
    function getPhxTone(ramp, t) {
        return ramp[Math.round(getPhxClamp(t) * (ramp.length - 1))];
    }
    // Д11 Tools of the scene: the logo of the page, the colours of the theme, its layers and the frame loop; null on a page without a logo
    function getPhoenixKit() {
        var doc = document;
        var root = doc.documentElement;
        var brand = doc.querySelector('.sl-brand');
        var logo = brand ? brand.querySelector('img') : null;
        var mid = doc.querySelector('.sl-wrapmid');
        var badge = brand ? brand.querySelector('.sl-phx-badge') : null;
        var probe, nodes, offs, kit, bg;
        if (!logo || !mid) return null;
        probe = doc.createElement('i');
        probe.className = 'sl-visually-hidden';
        doc.body.appendChild(probe);
        nodes = [probe];
        offs = [];
        kit = {
            doc: doc,
            root: root,
            alive: true,
            release: 'SLAED CMS ' + (badge ? badge.textContent.replace(/\s+/g, ' ').trim() : ''),
            rgb: function (name) {
                probe.style.color = 'var(' + name + ')';
                return window.getComputedStyle(probe).color.match(/[\d.]+/g).slice(0, 3).map(Number);
            },
            // The fire of Phoenix: a warm heart in the blue of the project and the grey of its ash, each tone also as numbers for the ramps
            pal: function () {
                var names = {
                    hot: kit.dark ? '--sl-warning-strong' : '--sl-warning',
                    mid: '--sl-warning',
                    deep: kit.dark ? '--sl-warning' : '--sl-warning-strong',
                    sky: '--sl-primary-strong',
                    ash: '--sl-text-subtle'
                };
                var out = { rgb: {} };
                Object.keys(names).forEach(function (key) {
                    out.rgb[key] = kit.rgb(names[key]);
                    out[key] = 'rgb(' + out.rgb[key].join(', ') + ')';
                });
                return out;
            },
            box: function () {
                return logo.getBoundingClientRect();
            },
            // The A of the wordmark: its middle and its top, where the embers leave
            peak: function () {
                var r = logo.getBoundingClientRect();
                return { x: r.x + r.width * 0.495, top: r.y + r.height * 0.04 };
            },
            loop: function (fn) {
                var last = window.performance.now();
                // One frame: the time since the last goes to the play until the scene is undone
                function setTick(now) {
                    if (!kit.alive) return;
                    fn(Math.min(0.05, (now - last) / 1000));
                    last = now;
                    window.requestAnimationFrame(setTick);
                }
                window.requestAnimationFrame(setTick);
            },
            el: function (tag, cls, host, before) {
                var one = doc.createElement(tag);
                if (cls) one.className = cls;
                if (before) (host || doc.body).insertBefore(one, before);
                else (host || doc.body).appendChild(one);
                nodes.push(one);
                return one;
            },
            // A listener of the scene, taken off with the scene
            on: function (host, name, fn, opts) {
                host.addEventListener(name, fn, opts);
                offs.push(function () { host.removeEventListener(name, fn, opts); });
            },
            // Undo the scene: its layers go, its listeners and classes leave the page, its loop stops
            reset: function () {
                kit.alive = false;
                nodes.forEach(function (one) { one.remove(); });
                offs.forEach(function (off) { off(); });
                Array.prototype.slice.call(root.classList).forEach(function (name) {
                    if (name.indexOf('sl-phx-') === 0) root.classList.remove(name);
                });
            },
            // The logo the page shows, whatever file the panel was given
            image: function () {
                var img = new window.Image();
                img.src = logo.currentSrc || logo.src;
                return img.decode().then(function () { return img; });
            },
            // A canvas over the ground of a host, or over the whole window without one
            layer: function (host) {
                var cv = kit.el('canvas', host ? 'sl-phx-layer' : 'sl-phx-layer sl-phx-top', host);
                var g = cv.getContext('2d');
                var lay = { cv: cv, g: g, w: 0, h: 0 };
                cv.setAttribute('aria-hidden', 'true');
                lay.fit = function () {
                    var w = host ? host.clientWidth : window.innerWidth;
                    var h = host ? host.clientHeight : window.innerHeight;
                    var d = window.devicePixelRatio || 1;
                    cv.width = Math.round(w * d);
                    cv.height = Math.round(h * d);
                    cv.style.width = w + 'px';
                    cv.style.height = h + 'px';
                    g.setTransform(d, 0, 0, d, 0, 0);
                    lay.w = w;
                    lay.h = h;
                };
                lay.fit();
                kit.on(window, 'resize', lay.fit);
                return lay;
            },
            // An image as dots: where each opaque pixel lies on the window and what colour it has
            dots: function (img, x0, y0, w, h, step) {
                var c = doc.createElement('canvas');
                var g, d, out, x, y, i;
                c.width = Math.round(w);
                c.height = Math.round(h);
                g = c.getContext('2d', { willReadFrequently: true });
                g.drawImage(img, 0, 0, c.width, c.height);
                d = g.getImageData(0, 0, c.width, c.height).data;
                out = [];
                for (y = 0; y < c.height; y += step) {
                    for (x = 0; x < c.width; x += step) {
                        i = (y * c.width + x) * 4;
                        if (d[i + 3] > 140) out.push({ tx: x0 + x, ty: y0 + y, u: x / c.width, v: y / c.height, c: 'rgb(' + d[i] + ', ' + d[i + 1] + ', ' + d[i + 2] + ')' });
                    }
                }
                return out;
            },
            // The ground of the page: it plays over the page, then settles under the content; the dimming lies just under it
            ground: function () {
                var one;
                mid.classList.add('sl-phx-host');
                offs.push(function () { mid.classList.remove('sl-phx-host'); });
                one = kit.el('div', 'sl-phx-ground sl-phx-over', mid);
                kit.veil = kit.el('div', 'sl-phx-veil', mid, one);
                return one;
            }
        };
        bg = kit.rgb('--sl-bg');
        kit.dark = bg[0] * 0.3 + bg[1] * 0.59 + bg[2] * 0.11 < 128;
        return kit;
    }
    // Д11 The scene on its own clock: the acts are drawn from the time, the ash moves in fixed steps from a seed; resolves to its control
    function getPhoenixScene(kit) {
        var ground = kit.ground();
        var veil = kit.veil;
        var code = kit.layer(ground);
        var lay = kit.layer(ground);
        var top = kit.layer(null);
        var text = phxCode.concat(kit.release).join('    ') + '    ';
        return kit.image().then(function (word) {
            return setPhxPlay(kit, ground, veil, code, lay, top, text, word);
        });
    }
    // The acts, the ash and the frame loop of one scene over its layers
    function setPhxPlay(kit, ground, veil, code, lay, top, text, word) {
        var g = lay.g;
        var c = kit.pal();
        var dpr = window.devicePixelRatio || 1;
        var rise = getPhxRamp([c.rgb.sky, c.rgb.mid, c.rgb.mid, c.rgb.sky]);
        var fire = getPhxRamp([c.rgb.ash, c.rgb.deep, c.rgb.mid, c.rgb.hot]);
        var beam = window.getComputedStyle(kit.root).getPropertyValue('--sl-beam-bg').trim();
        var lean = Math.tan(-18 * Math.PI / 180);
        var shine = kit.doc.createElement('canvas');
        var sg = shine.getContext('2d');
        var cell = 16;
        var trail = 18;
        var rows = Math.ceil(code.h / cell);
        var span = rows + trail + 12;
        var font = '600 ' + (cell - 2) + 'px ' + window.getComputedStyle(kit.root).getPropertyValue('--sl-face-mono').trim();
        var hs = 1;
        var ww = 0;
        var px = [];
        var ax = 0;
        var base = [];
        var letters = [];
        var hp = [];
        var hx = 0;
        var hy = 0;
        var now = 0;
        var cols = [];
        var pts = [];
        var own = [];
        var rest = [];
        var uniq = [];
        var ash = [];
        var acc = 0;
        var exit = 1;
        var leaving = false;
        var ctl;
        // Whole pixels of the screen: a dot that straddles them smears into its neighbours
        function setDot(x, y, s, color, a, ctx) {
            ctx = ctx || g;
            ctx.globalAlpha = a;
            ctx.fillStyle = color;
            s = Math.max(1, Math.round(s * dpr)) / dpr;
            ctx.fillRect(Math.round((x - s / 2) * dpr) / dpr, Math.round((y - s / 2) * dpr) / dpr, s, s);
        }
        // A dot that changes from one colour to another: the first fades out under the second
        function setDotTwo(x, y, s, from, to, mix, a) {
            if (mix < 1) setDot(x, y, s, from, (1 - mix) * a);
            if (mix > 0) setDot(x, y, s, to, mix * a);
        }
        // The measures of a round, taken when it starts: the big logo fills the page and its own A is the giant A
        function setShape() {
            var wh, wx, wy, filled, mid, lo, hi, r;
            ww = Math.min(lay.w * 0.9, lay.h * 0.62 * word.naturalWidth / word.naturalHeight);
            wh = ww * word.naturalHeight / word.naturalWidth;
            wx = Math.round((lay.w - ww) / 2);
            wy = Math.round((lay.h - wh) / 2);
            px = kit.dots(word, wx, wy, ww, wh, 4);
            ax = wx + ww * 0.495;
            filled = {};
            px.forEach(function (p) { filled[p.tx] = true; });
            mid = wx + Math.round(ww * 0.495 / 4) * 4;
            lo = mid;
            hi = mid;
            while (filled[lo - 4]) lo -= 4;
            while (filled[hi + 4]) hi += 4;
            if (!filled[mid] || hi - lo > ww * 0.3) {
                lo = wx + ww * 0.378;
                hi = wx + ww * 0.613;
            }
            base = px.filter(function (p) { return p.tx >= lo && p.tx <= hi; });
            letters = px.filter(function (p) { return p.tx < lo || p.tx > hi; });
            r = kit.box();
            hp = kit.dots(word, r.x, r.y, r.width, r.height, 2);
            hx = r.x + r.width / 2;
            hy = r.y + r.height / 2;
        }
        // The one particle of the scene: a square of smouldering ash that takes light (dh > 0) or burns out (dh < 0)
        function addAsh(p) {
            ash.push(Object.assign({
                vx: 0, vy: 0, grav: 0, drag: 1, cap: 80, sway: getPhxRand(4, 14), ph: rnd() * phxTau, s: 2.4, age: 0, life: Infinity, h: 0, dh: 0.27, top: 0.68, up: false
            }, p));
        }
        // How far the clock has gone from a to b, held between 0 and 1
        function getPart(a, b) {
            return getPhxClamp((now - a) / (b - a));
        }
        // How far a column of the rain has run at a time, in rows
        function getPos(o, at) {
            return at < o.from ? 0 : trail + o.v * (at - o.from);
        }
        // The beams of the quiet: one every six seconds from a second after the logo is home, two seconds across
        function getGlint(t) {
            return t < phxAt.calm + 1 ? -1 : ((t - phxAt.calm - 1) % phxGlint) / 2;
        }
        // A fresh start: the same seed gives the same columns, the same sparks and the same ash
        function setBuild() {
            var glow, n, i, j, a, q;
            setShape();
            rnd = getPhxSeed(2005);
            now = 0;
            ash = [];
            cols = [];
            for (i = 0; i < Math.ceil(code.w / cell); i++) {
                cols.push({ x: i * cell + cell / 2, from: phxAt.code + getPhxRand(0, 2), v: getPhxRand(5, 11), off: (rnd() * text.length) | 0 });
            }
            glow = getPhxShuffle(letters.slice());
            n = Math.round(px.length * 1.1);
            pts = [];
            for (i = 0; i < n; i++) {
                a = i < base.length ? base[i] : base[(rnd() * base.length) | 0];
                q = i < base.length ? a : glow[i - base.length] || null;
                pts.push({ tx: a.tx, ty: a.ty, v: a.v, c: a.c, q: q });
            }
            getPhxShuffle(pts);
            j = 0;
            pts.forEach(function (p) {
                var o = cols[(rnd() * cols.length) | 0];
                var head = Math.floor(getPos(o, phxAt.rise) % span) - trail;
                var y = getPhxClamp((head - getPhxRand(0, 16)) * cell, 0, lay.h);
                var h, ang, far;
                Object.assign(p, {
                    x: o.x,
                    y: y,
                    cx: getPhxLerp(o.x, p.tx, 0.2) + getPhxRand(-60, 60),
                    cy: getPhxLerp(y, p.ty, 0.8),
                    d: (1 - p.v) * 0.35 + rnd() * 0.2,
                    fd: p.v * 0.4 + rnd() * 0.15,
                    a1x: p.tx + getPhxRand(-30, 30),
                    a1y: p.ty + getPhxRand(20, 80),
                    ph: rnd() * phxTau
                });
                if (!p.q) return;
                h = hp[j % hp.length];
                ang = Math.atan2(h.ty - hy, h.tx - hx) + getPhxRand(-0.5, 0.5);
                far = getPhxRand(25, 65);
                Object.assign(p, {
                    fe: Math.abs(p.q.tx - ax) / ww * 0.8 + rnd() * 0.12,
                    bx: (p.a1x + p.q.tx) / 2,
                    by: Math.min(p.a1y, p.q.ty) - getPhxRand(10, 50),
                    md: p.q.v * 0.4 + rnd() * 0.15,
                    a2x: p.q.tx + getPhxRand(-25, 25),
                    a2y: p.q.ty + getPhxRand(15, 60),
                    h: h,
                    dup: j >= hp.length,
                    yd: rnd() * 0.35,
                    fx: (p.q.tx + h.tx) / 2 + getPhxRand(-80, 80),
                    fy: Math.min(p.q.ty, h.ty) - getPhxRand(40, 140),
                    a3x: h.tx + Math.cos(ang) * far,
                    a3y: Math.max(4, h.ty + Math.sin(ang) * far * 0.8),
                    sd: rnd() * 0.3,
                    ld: Math.abs(h.u - 0.495) * 0.8 + rnd() * 0.12
                });
                j++;
            });
            own = pts.filter(function (p) { return p.q; });
            rest = pts.filter(function (p) { return !p.q; });
            uniq = own.filter(function (p) { return !p.dup; });
        }
        // One fixed step of the clock: the ash is born and moves
        function setStep(dt) {
            var was = now;
            var low, a, b, peak, i, ang, v, p, drag;
            now += dt;
            if (was < phxAt.form && now >= phxAt.form) {
                rest.forEach(function (one) { addAsh({ x: one.a1x, y: one.a1y, vy: -getPhxRand(15, 40), grav: -5, ph: one.ph }); });
            }
            if (now > phxAt.calm) {
                low = 0;
                ash.forEach(function (one) { if (!one.up) low++; });
                if (low < 140 && rnd() < 0.6 * getPart(phxAt.calm, phxAt.calm + 8)) {
                    addAsh({ x: rnd() * lay.w, y: lay.h + getPhxRand(0, 20), vy: -getPhxRand(15, 35), grav: -4, cap: 45, h: getPhxRand(0.3, 0.6) });
                }
                a = getGlint(was);
                b = getGlint(now);
                if (a >= 0 && a < 0.45 && b >= 0.45 && b > a) {
                    peak = kit.peak();
                    for (i = 0; i < 14; i++) {
                        ang = getPhxRand(-Math.PI * 0.75, -Math.PI * 0.25);
                        v = getPhxRand(60, 170);
                        addAsh({
                            x: peak.x + getPhxRand(-4, 4), y: peak.top + 3, vx: Math.cos(ang) * v, vy: Math.sin(ang) * v, grav: 70, drag: 0.98, cap: 1000, sway: 0,
                            life: getPhxRand(1.2, 2.2), h: 0.95, dh: -0.4, top: 1, s: 2, up: true
                        });
                    }
                }
            }
            for (i = ash.length - 1; i >= 0; i--) {
                p = ash[i];
                p.age += dt;
                drag = Math.pow(p.drag, dt * 60);
                p.vx *= drag;
                p.vy = Math.max(p.vy * drag + p.grav * dt, -p.cap);
                p.x += p.vx * dt;
                p.y += p.vy * dt;
                if (p.age >= p.life || p.y < -10 || p.y > lay.h + 300) {
                    ash[i] = ash[ash.length - 1];
                    ash.pop();
                }
            }
        }
        // The rain: every column runs down the code of the system, a warm head over a blue trail
        function setRain() {
            var cg = code.g;
            var out, pos, cyc, head, row, ch, i;
            cg.clearRect(0, 0, code.w, code.h);
            if (now < phxAt.code || now >= phxAt.hold) return;
            out = Math.max(1 - getPhxInOut(getPart(phxAt.code, phxAt.clear)), getPart(phxAt.rise + 2.2, phxAt.hold));
            cg.font = font;
            cg.textAlign = 'center';
            cg.textBaseline = 'middle';
            cols.forEach(function (o) {
                pos = getPos(o, now);
                if (pos <= 0) return;
                cyc = Math.floor(pos / span);
                if (now > phxAt.rise && cyc > Math.floor(getPos(o, phxAt.rise) / span)) return;
                head = Math.floor(pos % span) - trail;
                for (i = 0; i <= trail; i++) {
                    row = head - i;
                    if (row < 0 || row >= rows) continue;
                    ch = text[(row + cyc * 37 + o.off) % text.length];
                    if (ch === ' ') continue;
                    cg.globalAlpha = (i ? Math.pow(1 - i / (trail + 1), 1.5) * 0.9 : 1) * (1 - out);
                    cg.fillStyle = i ? c.sky : c.hot;
                    cg.fillText(ch, o.x, row * cell + cell / 2);
                }
            });
            cg.globalAlpha = 1;
        }
        // The ash of the scene, each grain in the tone of its burning and swaying a little
        function setAsh() {
            ash.forEach(function (p) {
                var x = p.x + Math.sin(now * 0.9 + p.ph) * p.sway * Math.min(1, p.age / 2);
                var fade = (p.up ? 1 : getPhxClamp(p.y / (lay.h * 0.3))) * (1 - Math.pow(p.age / p.life, 2)) * (p.old ? 1 - getPart(phxAt.dim, phxAt.clear) : 1);
                setDot(x, p.y, p.s, getPhxTone(fire, getPhxClamp(p.h + p.dh * p.age, 0, p.top)), fade * (0.7 + 0.3 * Math.sin(now * 2.5 + p.ph)), p.up ? top.g : g);
            });
        }
        // Ash that lies: grey, a little shimmer
        function getLie(p) {
            return 0.6 + 0.1 * Math.sin(now * 2 + p.ph);
        }
        // Ash in the air over the header: brighter, so it is seen against the header
        function getAir(p) {
            return 0.85 + 0.1 * Math.sin(now * 2 + p.ph);
        }
        // The sway of ash hanging in the air
        function getSway(p) {
            return Math.sin(now * 1.3 + p.ph) * 2;
        }
        // The beam crosses the logo where it is now, leaning back and cut to the shape of the letters; a logo out of the window gets none
        function setGlint(t) {
            var r = kit.box();
            var w, h, bw, x, gr;
            if (r.bottom <= 0 || r.top >= top.h || !r.width) return;
            w = Math.round(r.width * dpr);
            h = Math.round(r.height * dpr);
            if (shine.width !== w || shine.height !== h) {
                shine.width = w;
                shine.height = h;
            }
            bw = r.width * 0.34;
            x = getPhxLerp(-1.6, 4.2, getPhxInOut(t)) * bw;
            sg.setTransform(dpr, 0, 0, dpr, 0, 0);
            sg.globalCompositeOperation = 'source-over';
            sg.clearRect(0, 0, r.width, r.height);
            sg.save();
            sg.translate(x + bw / 2, r.height / 2);
            sg.transform(1, 0, lean, 1, 0, 0);
            gr = sg.createLinearGradient(-bw / 2, 0, bw / 2, 0);
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
        }
        // The acts of the scene that move dots, one picture at the time of the clock
        function setActs() {
            var t, show, fade;
            if (now >= phxAt.rise && now < phxAt.hold) {
                t = getPart(phxAt.rise, phxAt.hold);
                show = getPart(phxAt.rise, phxAt.rise + 1);
                pts.forEach(function (p) {
                    var s = getPhxClamp((t - p.d) / 0.45);
                    var e = getPhxInOut(s);
                    setDotTwo(getPhxQuad(p.x, p.cx, p.tx, e), getPhxQuad(p.y, p.cy, p.ty, e), getPhxLerp(2, 3, s), getPhxTone(rise, s), p.c, getPhxClamp((s - 0.7) / 0.3),
                        (0.35 + 0.65 * getPhxOut(s)) * show);
                });
            } else if (now >= phxAt.hold && now < phxAt.fall) {
                pts.forEach(function (p) { setDot(p.tx, p.ty, 3, p.c, 1); });
            } else if (now >= phxAt.fall && now < phxAt.form) {
                t = getPart(phxAt.fall, phxAt.form);
                pts.forEach(function (p) {
                    var s = getPhxClamp((t - p.fd) / 0.45);
                    var e = getPhxOut(s);
                    setDotTwo(getPhxLerp(p.tx, p.a1x, e), getPhxLerp(p.ty, p.a1y, e), 3, p.c, c.ash, s, getPhxLerp(1, getLie(p), s));
                });
            } else if (now >= phxAt.form && now < phxAt.big) {
                t = getPart(phxAt.form, phxAt.big);
                own.forEach(function (p) {
                    var s = getPhxClamp((t - p.fe) / 0.45);
                    var e = getPhxInOut(s);
                    var x = getPhxQuad(p.a1x, p.bx, p.q.tx, e);
                    var y = getPhxQuad(p.a1y, p.by, p.q.ty, e);
                    if (s <= 0) setDot(x, y, 3, c.ash, getLie(p));
                    else setDotTwo(x, y, 3, getPhxTone(fire, s / 0.7), p.q.c, getPhxClamp((s - 0.7) / 0.3), getPhxLerp(getLie(p), 1, getPhxClamp(s * 3)));
                });
            } else if (now >= phxAt.big && now < phxAt.melt) {
                own.forEach(function (p) { setDot(p.q.tx, p.q.ty, 3, p.q.c, 1); });
            } else if (now >= phxAt.melt && now < phxAt.fly) {
                t = getPart(phxAt.melt, phxAt.fly);
                own.forEach(function (p) {
                    var s = getPhxClamp((t - p.md) / 0.45);
                    var e = getPhxOut(s);
                    setDotTwo(getPhxLerp(p.q.tx, p.a2x, e), getPhxLerp(p.q.ty, p.a2y, e), 3, p.q.c, c.ash, s, getPhxLerp(1, getLie(p), s));
                });
            } else if (now >= phxAt.fly && now < phxAt.small) {
                t = getPart(phxAt.fly, phxAt.small);
                own.forEach(function (p) {
                    var s = getPhxClamp((t - p.yd) / 0.6);
                    var e = getPhxInOut(s);
                    var gone = p.dup ? 1 - getPhxClamp((s - 0.75) / 0.25) : 1;
                    if (gone <= 0) return;
                    setDotTwo(getPhxQuad(p.a2x, p.fx, p.h.tx, e), getPhxQuad(p.a2y, p.fy, p.h.ty, e), getPhxLerp(3, hs, e), c.ash, p.h.c, getPhxClamp((s - 0.6) / 0.4),
                        getPhxLerp(getLie(p), 1, s) * gone);
                });
            } else if (now >= phxAt.small && now < phxAt.dust) {
                uniq.forEach(function (p) { setDot(p.h.tx, p.h.ty, hs, p.h.c, 1); });
            } else if (now >= phxAt.dust && now < phxAt.land) {
                t = getPart(phxAt.dust, phxAt.land);
                uniq.forEach(function (p) {
                    var s = getPhxClamp((t - p.sd) / 0.6);
                    var e = getPhxOut(s);
                    setDotTwo(getPhxLerp(p.h.tx, p.a3x, e) + getSway(p) * e, getPhxLerp(p.h.ty, p.a3y, e), hs, p.h.c, c.ash, s, getPhxLerp(1, getAir(p), s));
                });
            } else if (now >= phxAt.land && now < phxAt.calm) {
                t = getPart(phxAt.land, phxAt.calm - 0.6);
                fade = 1 - getPart(phxAt.calm - 0.8, phxAt.calm);
                uniq.forEach(function (p) {
                    var s = getPhxClamp((t - p.ld) / 0.5);
                    var e = getPhxInOut(s);
                    var x = getPhxLerp(p.a3x, p.h.tx, e) + getSway(p) * (1 - e);
                    var y = getPhxLerp(p.a3y, p.h.ty, e);
                    if (s <= 0) setDot(x, y, hs, c.ash, getAir(p) * fade);
                    else setDotTwo(x, y, hs, getPhxTone(fire, s / 0.7), p.h.c, getPhxClamp((s - 0.7) / 0.3), getPhxLerp(getAir(p), 1, getPhxClamp(s * 3)) * fade);
                });
            }
        }
        // One picture of the scene; the page follows the clock: it dims to three quarters and clears as the logo comes home, a soft exit fades it all
        function setFrame() {
            var gl = getGlint(now);
            var root = kit.root;
            var dim = now < phxAt.land ? getPhxInOut(getPart(phxAt.dim, phxAt.clear)) : 1 - getPhxInOut(getPart(phxAt.land, phxAt.land + 2));
            setRain();
            g.clearRect(0, 0, lay.w, lay.h);
            top.g.clearRect(0, 0, top.w, top.h);
            setActs();
            if (gl >= 0 && gl < 1) setGlint(gl);
            setAsh();
            g.globalAlpha = 1;
            top.g.globalAlpha = 1;
            veil.style.opacity = (0.75 * exit * dim).toFixed(3);
            ground.style.opacity = exit < 1 ? exit.toFixed(3) : '';
            top.cv.style.opacity = exit < 1 ? exit.toFixed(3) : '';
            root.classList.toggle('sl-phx-lit', exit < 1 || now < phxAt.clear || now >= phxAt.land + 1.2);
            root.classList.toggle('sl-phx-crown', exit < 1 || now < phxAt.clear || now >= phxAt.calm);
            root.classList.toggle('sl-phx-glint', gl >= 0.25 && gl < 1);
            ground.classList.toggle('sl-phx-over', now >= phxAt.code && now < phxAt.calm);
            ground.classList.toggle('sl-phx-fade', now >= phxAt.calm - 0.4 && now < phxAt.calm);
        }
        // The quiet: the logo is home and the ash of the quiet rises under the page
        function setQuiet() {
            setBuild();
            now = phxAt.calm;
        }
        // A round starts over from the quiet or from the end of the last one; the ash still in the air burns out while the screen dims
        function setRound() {
            var keep = ash;
            setBuild();
            ash = keep.filter(function (p) { return !p.up; });
            ash.forEach(function (p) { p.old = true; });
        }
        setQuiet();
        ctl = {
            get busy() { return now < phxAt.calm || leaving; },
            get ended() { return now >= phxEnd; },
            start: function () {
                leaving = false;
                exit = 1;
                setRound();
            },
            wake: function () {
                if (now < phxAt.calm) leaving = true;
            }
        };
        kit.loop(function (dt) {
            if (leaving) {
                exit -= dt / phxExit;
                if (exit <= 0) {
                    leaving = false;
                    exit = 1;
                    setQuiet();
                }
            } else {
                acc = Math.min(acc + dt, 0.25);
                while (acc >= phxStep) {
                    setStep(phxStep);
                    acc -= phxStep;
                }
            }
            setFrame();
        });
        return ctl;
    }
    window.getPhoenixKit = getPhoenixKit;
    window.getPhoenixScene = getPhoenixScene;
})();
