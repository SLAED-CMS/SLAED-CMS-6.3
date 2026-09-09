/* Extend the leader's controls without replacing its scenes. */
(() => {
    'use strict';
    const host = document.querySelector('.sl-leader');
    const root = document.documentElement;
    const params = new URLSearchParams(location.search);
    const getAll = query => [...document.querySelectorAll(query)];
    const getOne = query => document.querySelector(query);
    const media = matchMedia('(prefers-color-scheme: dark)');
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('slaed.leader') || '{}'); } catch {}
    const theme = params.get('mode') || saved.theme || 'dark';
    const season = (params.get('season') || saved.season || 'autumn').replace(/^sl-/, '');
    const state = {
        theme: ['light', 'dark', 'auto'].includes(theme) ? theme : 'dark',
        season: ['winter', 'spring', 'summer', 'autumn', 'newyear'].includes(season) ? season : 'autumn',
        motion: saved.motion ?? !matchMedia('(prefers-reduced-motion: reduce)').matches
    };
    function setStore() {
        try { localStorage.setItem('slaed.leader', JSON.stringify(state)); } catch {}
    }
    function setTheme(value) {
        state.theme = value;
        root.dataset.theme = value;
        host.dataset.scheme = value === 'auto' ? (media.matches ? 'dark' : 'light') : value;
        getAll('[data-leader-theme]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.leaderTheme === value)));
        getAll('[data-leader-head] .sl-mode button').forEach(el => {
            el.classList.toggle('sl-is-active', el.value === value);
            el.setAttribute('aria-pressed', String(el.value === value));
        });
        setGauges();
        setLabChart();
        setStore();
    }
    function setSeason(value) {
        state.season = value;
        ['winter', 'spring', 'summer', 'autumn', 'newyear'].forEach(key => document.body.classList.toggle('sl-' + key, key === value));
        getOne('#leaderSeason').value = value;
        setStore();
    }
    function setMotion(value) {
        state.motion = value;
        host.dataset.motion = value ? 'on' : 'off';
        const button = getOne('.sl-leader-motion');
        button.setAttribute('aria-pressed', String(value));
        button.querySelector('span').textContent = 'Анимация ' + (value ? 'включена' : 'выключена');
        button.querySelector('i').className = 'bi bi-' + (value ? 'pause-circle' : 'play-circle');
        host.querySelectorAll('svg').forEach(el => { if (value) el.unpauseAnimations(); else el.pauseAnimations(); });
        setStore();
    }
    async function getShell() {
        if (params.has('bare')) { document.body.dataset.leaderBare = ''; root.dataset.shell = 'bare'; return; }
        try {
            const url = new URL('../index.php?name=main', location.href);
            const reply = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!reply.ok) throw new Error('CMS response: ' + reply.status);
            const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
            const wrap = doc.querySelector('body > .sl-wrp');
            if (!wrap) throw new Error('CMS content boundary is missing');
            const head = document.createDocumentFragment();
            let node = doc.body.firstElementChild;
            while (node && node !== wrap) {
                const next = node.nextElementSibling;
                head.append(node);
                node = next;
            }
            const foot = document.createDocumentFragment();
            for (const id of ['demo-line', 'footbox']) {
                const part = doc.getElementById(id);
                if (!part) throw new Error('CMS footer is incomplete');
                foot.append(part);
            }
            for (const part of [head, foot]) {
                part.querySelectorAll('script').forEach(el => el.remove());
                part.querySelectorAll('[href],[src],[action]').forEach(el => {
                    for (const attr of ['href', 'src', 'action']) {
                        const path = el.getAttribute(attr);
                        if (path && !path.startsWith('#')) el.setAttribute(attr, new URL(path, url).href);
                    }
                });
            }
            getOne('[data-leader-head]').replaceChildren(head);
            getOne('[data-leader-foot]').replaceChildren(foot);
            getAll('[data-leader-head] .sl-mode button').forEach(el => {
                el.type = 'button';
                el.addEventListener('click', () => setTheme(el.value));
            });
            setTheme(state.theme);
            root.dataset.shell = 'ready';
        } catch (error) {
            root.dataset.shell = 'error';
            getOne('[data-leader-head]').textContent = 'Не удалось загрузить шапку SLAED. Обновите страницу.';
            console.error(error);
        }
    }
    /* The cockpit gauges: a 240-degree arc per value, drawn on a canvas at device resolution.
       Colours are read off the element, so the same call repaints the row after a scheme change. */
    function getGaugeColor(el, name) {
        const probe = document.createElement('i');
        probe.style.color = 'var(--' + name + ')';
        probe.style.display = 'none';
        el.append(probe);
        const out = getComputedStyle(probe).color;
        probe.remove();
        return out || '#888';
    }
    function setGauge(el) {
        let cv = el.querySelector('canvas');
        if (!cv) { cv = document.createElement('canvas'); el.append(cv); }
        const dpr = Math.min(devicePixelRatio || 1, 2);
        const w = el.clientWidth;
        const h = el.clientHeight;
        if (!w || !h) return;
        const pad = 16;
        cv.width = Math.round((w + pad * 2) * dpr);
        cv.height = Math.round((h + pad * 2) * dpr);
        const ctx = cv.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, pad * dpr, pad * dpr);
        ctx.clearRect(-pad, -pad, w + pad * 2, h + pad * 2);
        const val = Number(el.dataset.value || 0);
        const max = Number(el.dataset.max || 100) || 100;
        const rad = Math.min(w / 2, h / 1.25) - 8;
        const cx = w / 2;
        const cy = h / 2 + rad * 0.2;
        const a0 = Math.PI * 0.75;
        const a1 = Math.PI * 2.25;
        const bar = 12;
        ctx.lineCap = 'round';
        ctx.lineWidth = bar;
        ctx.beginPath();
        ctx.arc(cx, cy, rad, a0, a1);
        ctx.strokeStyle = getGaugeColor(el, 'line');
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(cx, cy, rad, a0, a0 + (a1 - a0) * Math.max(0, Math.min(1, val / max)));
        ctx.strokeStyle = getGaugeColor(el, el.dataset.color || 'blue');
        ctx.save();
        ctx.shadowColor = ctx.strokeStyle;
        ctx.shadowBlur = 10 * dpr;
        ctx.stroke();
        ctx.restore();
        ctx.strokeStyle = getGaugeColor(el, 'muted');
        ctx.lineWidth = 1;
        for (let i = 0; i <= 12; i++) {
            const ang = a0 + (a1 - a0) * (i / 12);
            const out = rad - bar;
            const len = out - (i % 3 === 0 ? 7 : 4);
            ctx.beginPath();
            ctx.moveTo(cx + Math.cos(ang) * out, cy + Math.sin(ang) * out);
            ctx.lineTo(cx + Math.cos(ang) * len, cy + Math.sin(ang) * len);
            ctx.stroke();
        }
    }
    function setGauges() { getAll('[data-gauge]').forEach(setGauge); }

    /* Trace only the active CSS arc with SVG so its shadow can extend beyond the ring without blurring its label */
    function addRingGlow() {
        const ns = 'http://www.w3.org/2000/svg';
        getAll('.monitor-ring, .live-ring .r').forEach(el => {
            const css = getComputedStyle(el);
            const size = el.clientWidth;
            const inset = parseFloat(getComputedStyle(el, '::after').top);
            const key = el.matches('.monitor-ring') ? '--m' : '--p';
            const value = Math.max(0, Math.min(100, parseFloat(css.getPropertyValue(key)) || 0));
            const svg = document.createElementNS(ns, 'svg');
            const circle = document.createElementNS(ns, 'circle');
            svg.setAttribute('class', 'sl-ring-glow');
            svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
            svg.setAttribute('aria-hidden', 'true');
            circle.setAttribute('cx', size / 2);
            circle.setAttribute('cy', size / 2);
            circle.setAttribute('r', (size - inset) / 2);
            circle.setAttribute('stroke-width', inset);
            circle.setAttribute('pathLength', '100');
            circle.setAttribute('stroke-dasharray', `${value} ${100 - value}`);
            circle.setAttribute('transform', `rotate(-90 ${size / 2} ${size / 2})`);
            svg.append(circle);
            el.prepend(svg);
        });
    }

    /* The rail scrolls sideways when the labels outrun the width, so the department the page is on
       is pulled into view instead of being left off the edge. */
    function setRailFollow() {
        const rail = getOne('.road-rail .outline-road');
        if (!rail) return;
        let last = null;
        const follow = () => {
            const cur = rail.querySelector('a.current');
            if (!cur || cur === last) return;
            last = cur;
            const box = rail.getBoundingClientRect();
            const item = cur.getBoundingClientRect();
            if (item.left < box.left || item.right > box.right) {
                rail.scrollTo({left: cur.offsetLeft - rail.clientWidth / 2 + cur.offsetWidth / 2, behavior: 'smooth'});
            }
        };
        addEventListener('scroll', follow, {passive: true});
        follow();
    }

    /* One viewport halo provides the same light over cards, section gaps and the page background */
    function setPointerLight() {
        const light = document.createElement('div');
        light.className = 'sl-etalon-light';
        light.setAttribute('aria-hidden', 'true');
        document.body.append(light);
        document.addEventListener('pointermove', event => {
            if (event.pointerType === 'touch') return;
            light.style.setProperty('--mx', event.clientX + 'px');
            light.style.setProperty('--my', event.clientY + 'px');
            light.hidden = false;
        }, {passive: true});
        document.documentElement.addEventListener('pointerleave', () => { light.hidden = true; });
    }


    const lab = {span: 'day', kind: 'area'};
    function getLabTone(name) {
        const probe = document.createElement('span');
        probe.hidden = true;
        probe.style.color = 'var(' + name + ')';
        host.append(probe);
        const out = getComputedStyle(probe).color;
        probe.remove();
        return out || '#888888';
    }
    /* Restore the original deterministic illustration and its three series. */
    const sets = {day: [24862, '18,4', 24, 0], week: [186420, '12,8', 28, 2], month: [782641, '24,1', 30, 5]};
    let hover = -1;
    function getSeriesData(span) {
        const conf = sets[span];
        return [0, 1, 2].map(line => Array.from({length: conf[2]}, (_, i) => {
            const wave = Math.sin(i * .48 + conf[3]) * .15 + Math.cos(i * 1.15 + line) * .065;
            return Math.max(.07, .42 - line * .115 + wave + i / conf[2] * .27);
        }));
    }
    function getChartLabel(index) {
        if (lab.span === 'day') return String(index).padStart(2, '0') + ':00';
        if (lab.span === 'week') return ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'][Math.min(6, Math.floor(index / 4))];
        return String(index + 1) + ' авг';
    }
    function setLabChart() {
        const face = getOne('#labChart');
        if (!face) return;
        const rect = face.parentElement.getBoundingClientRect();
        if (!rect.width || !rect.height) return;
        const dpr = Math.min(devicePixelRatio || 1, 2);
        face.width = Math.round(rect.width * dpr);
        face.height = Math.round(rect.height * dpr);
        const draw = face.getContext('2d');
        draw.setTransform(dpr, 0, 0, dpr, 0, 0);
        const width = rect.width, height = rect.height, left = 34, right = width - 10, top = 12, bottom = height - 30;
        const colors = ['--sl-primary-strong', '--sl-accent', '--sl-success'].map(getLabTone);
        const muted = getLabTone('--sl-text-muted');
        const border = getLabTone('--sl-border');
        const series = getSeriesData(lab.span);
        getOne('[data-lab-volume]').textContent = sets[lab.span][0].toLocaleString('ru-RU');
        getOne('[data-lab-change]').textContent = '+' + sets[lab.span][1] + '%';
        draw.font = getComputedStyle(root).getPropertyValue('--sl-font-micro').trim() + ' Arial';
        draw.textBaseline = 'middle';
        for (let i = 0; i <= 4; i++) {
            const ypos = top + (bottom - top) * i / 4;
            draw.strokeStyle = border;
            draw.setLineDash([3, 5]);
            draw.beginPath(); draw.moveTo(left, ypos); draw.lineTo(right, ypos); draw.stroke();
            draw.fillStyle = muted;
            draw.fillText(String((4 - i) * 250), 0, ypos);
        }
        draw.setLineDash([]);
        series.forEach((data, line) => {
            const points = data.map((value, i) => [left + i / (data.length - 1) * (right - left), bottom - value * (bottom - top)]);
            draw.strokeStyle = colors[line];
            draw.fillStyle = colors[line];
            draw.lineWidth = line ? 1.5 : 2.5;
            if (lab.kind === 'bar') {
                const span = (right - left) / data.length;
                draw.globalAlpha = line ? .6 : .9;
                points.forEach(([xx, yy]) => draw.fillRect(xx + (line - 1) * span * .22, yy, Math.max(1, span * .2), bottom - yy));
                draw.globalAlpha = 1;
                return;
            }
            draw.beginPath(); draw.moveTo(...points[0]);
            for (let i = 1; i < points.length; i++) {
                const prev = points[i - 1], curr = points[i], mid = (prev[0] + curr[0]) / 2;
                draw.bezierCurveTo(mid, prev[1], mid, curr[1], curr[0], curr[1]);
            }
            draw.stroke();
            if (!line) {
                draw.lineTo(right, bottom); draw.lineTo(left, bottom); draw.closePath();
                const grad = draw.createLinearGradient(0, top, 0, bottom);
                grad.addColorStop(0, colors[line]); grad.addColorStop(1, 'transparent');
                draw.fillStyle = grad; draw.globalAlpha = .17; draw.fill(); draw.globalAlpha = 1;
            }
        });
        draw.fillStyle = muted;
        draw.textAlign = 'center';
        for (let i = 0; i < 5; i++) {
            const index = Math.round((series[0].length - 1) * i / 4);
            draw.fillText(getChartLabel(index), left + 10 + i / 4 * (right - left - 25), height - 10);
        }
        if (hover >= 0) {
            const index = Math.min(series[0].length - 1, hover);
            const xpos = left + index / (series[0].length - 1) * (right - left);
            draw.strokeStyle = muted; draw.setLineDash([3, 3]);
            draw.beginPath(); draw.moveTo(xpos, top); draw.lineTo(xpos, bottom); draw.stroke(); draw.setLineDash([]);
            series.forEach((data, line) => {
                draw.fillStyle = colors[line]; draw.beginPath();
                draw.arc(xpos, bottom - data[index] * (bottom - top), 4, 0, Math.PI * 2); draw.fill();
            });
        }
    }
    function setLabTip(event) {
        const face = getOne('#labChart'), tip = getOne('.sl-lab-tooltip');
        const rect = face.getBoundingClientRect();
        hover = Math.max(0, Math.min(sets[lab.span][2] - 1,
            Math.round((event.clientX - rect.left - 34) / (rect.width - 44) * (sets[lab.span][2] - 1))));
        const series = getSeriesData(lab.span);
        tip.textContent = getChartLabel(hover) + ' · ' + Math.round(series[0][hover] * 1000) + ' запросов';
        tip.hidden = false;
        setLabChart();
    }
    getOne('#labChart').addEventListener('pointerleave', () => {
        hover = -1;
        getOne('.sl-lab-tooltip').hidden = true;
        setLabChart();
    });
    new ResizeObserver(setLabChart).observe(getOne('.sl-lab-plot'));

    function setLabControls() {
        getAll('[data-lab-period]').forEach(el => el.addEventListener('click', () => {
            lab.span = el.dataset.labPeriod;
            getAll('[data-lab-period]').forEach(item => item.setAttribute('aria-pressed', String(item === el)));
            setLabChart();
        }));
        getAll('[data-lab-chart]').forEach(el => el.addEventListener('click', () => {
            lab.kind = el.dataset.labChart;
            getAll('[data-lab-chart]').forEach(item => item.setAttribute('aria-pressed', String(item === el)));
            setLabChart();
        }));
        getOne('#labChart')?.addEventListener('pointermove', setLabTip);
    }

    const mods = getAll('.sl-leader .mod[data-mod]');
    const builds = {
        all: mods.map(el => el.dataset.mod),
        portal: ['News', 'Files', 'Users', 'Security', 'Search', 'SEO', 'Pages'],
        media: ['News', 'Users', 'Security', 'Search', 'SEO', 'Media'],
        knowledge: ['Files', 'Users', 'Security', 'Search', 'SEO', 'Pages']
    };
    function updateModules() {
        mods.forEach(el => el.setAttribute('aria-pressed', String(el.dataset.on === '1')));
        getAll('[data-leader-build]').forEach(el => {
            const names = builds[el.dataset.leaderBuild];
            const active = mods.every(el => (el.dataset.on === '1') === names.includes(el.dataset.mod));
            el.setAttribute('aria-pressed', String(active));
        });
    }
    getAll('[data-leader-build]').forEach(el => el.addEventListener('click', () => {
        const names = builds[el.dataset.leaderBuild];
        mods.forEach(el => { if ((el.dataset.on === '1') !== names.includes(el.dataset.mod)) el.click(); });
    }));
    new MutationObserver(updateModules).observe(getOne('.module-map'), {subtree: true, attributes: true, attributeFilter: ['data-on']});
    getAll('.mod[data-mod], #coreMenu [data-core], .pdo-node, .commit-row').forEach(el => {
        el.setAttribute('role', 'button');
        el.tabIndex = 0;
        el.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); el.click(); }
        });
    });
    getOne('#leaderSearch').addEventListener('input', event => {
        const query = event.target.value.toLowerCase().trim();
        getAll('#pageRoad a').forEach(el => el.hidden = !el.textContent.toLowerCase().includes(query));
    });
    getAll('[data-leader-theme]').forEach(el => el.addEventListener('click', () => setTheme(el.dataset.leaderTheme)));
    getOne('#leaderSeason').addEventListener('change', event => setSeason(event.target.value));
    getOne('.sl-leader-motion').addEventListener('click', () => setMotion(!state.motion));
    getOne('.sl-leader-toggle').addEventListener('click', event => {
        const open = event.currentTarget.getAttribute('aria-expanded') !== 'true';
        event.currentTarget.setAttribute('aria-expanded', String(open));
        getOne('#leaderSettings').hidden = !open;
    });
    media.addEventListener('change', () => setTheme(state.theme));
    setTheme(state.theme);
    setSeason(state.season);
    setMotion(state.motion);
    updateModules();
    setGauges();
    addRingGlow();
    setRailFollow();
    setPointerLight();
    setLabControls();
    setLabChart();
    addEventListener('resize', () => { setGauges(); setLabChart(); }, {passive: true});
    getShell();
})();
