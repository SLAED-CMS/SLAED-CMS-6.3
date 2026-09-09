/* The controls of the lab face: the shell it borrows from the CMS, the settings panel, the workbench cards and the
   command dialog. The scenes of the page are driven by the inline script of the page itself and are not touched here. */
(() => {
    'use strict';
    const host = document.querySelector('.sl-lab');
    if (!host) return;
    const root = document.documentElement;
    const params = new URLSearchParams(location.search);
    const getAll = (query, from) => [...(from ?? document).querySelectorAll(query)];
    const getOne = (query, from) => (from ?? document).querySelector(query);
    const media = matchMedia('(prefers-color-scheme: dark)');
    const seasons = ['winter', 'spring', 'summer', 'autumn', 'newyear'];
    const layouts = ['overview', 'studio', 'monitor'];
    const months = ['янв', 'фев', 'мар', 'апр', 'мая', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    const builds = {
        portal: ['News', 'Files', 'Users', 'Security', 'Search', 'SEO', 'Pages'],
        media: ['News', 'Users', 'Security', 'Search', 'SEO', 'Media'],
        knowledge: ['Files', 'Users', 'Security', 'Search', 'SEO', 'Pages']
    };
    const spans = {day: {size: 24, base: 980, hour: true}, week: {size: 7, base: 21400}, month: {size: 30, base: 25600}};
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('slaed.lab') ?? '{}'); } catch {}
    const theme = params.get('mode') ?? saved.theme ?? 'auto';
    const season = (params.get('season') ?? saved.season ?? 'autumn').replace(/^sl-/, '');
    const state = {
        theme: ['light', 'dark', 'auto'].includes(theme) ? theme : 'auto',
        season: seasons.includes(season) ? season : 'autumn',
        layout: layouts.includes(saved.layout) ? saved.layout : 'overview',
        motion: saved.motion ?? !matchMedia('(prefers-reduced-motion: reduce)').matches,
        span: 'month',
        kind: 'area',
        desc: false
    };

    /* Keep the choices of the settings panel across visits, and never let a full storage break the page */
    function setStore() {
        try { localStorage.setItem('slaed.lab', JSON.stringify(state)); } catch {}
    }

    /* Say back what a control just changed, in one line that fades on its own */
    let timer = 0;
    function setToastText(text) {
        const note = getOne('.sl-lab-toast');
        if (!note) return;
        note.textContent = text;
        note.dataset.open = '';
        clearTimeout(timer);
        timer = setTimeout(() => delete note.dataset.open, 2200);
    }

    /* The page mode: the root carries it, and the mode rail of the borrowed head follows it */
    function setTheme(value) {
        state.theme = value;
        root.dataset.theme = value;
        getAll('[data-lab-theme]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.labTheme === value)));
        getAll('[data-lab-head] .sl-mode button').forEach(el => {
            el.classList.toggle('sl-is-active', el.value === value);
            el.setAttribute('aria-pressed', String(el.value === value));
        });
        setStore();
        setChartView();
    }

    /* The season skin of the theme, carried by the body the same way the CMS carries it */
    function setSeason(value) {
        state.season = value;
        seasons.forEach(name => document.body.classList.toggle('sl-' + name, name === value));
        const pick = getOne('#labSeason');
        if (pick) pick.value = value;
        setStore();
    }

    /* The composition of the workbench, shared by the presets above it and the select in the settings panel */
    function setLayout(value) {
        state.layout = value;
        host.dataset.layout = value;
        getAll('[data-lab-layout]').forEach(el => el.setAttribute('aria-pressed', String(el.dataset.labLayout === value)));
        const pick = getOne('#labLayout');
        if (pick) pick.value = value;
        setStore();
        setChartView();
    }

    /* Motion is a switch over the whole face: the CSS pauses what it can, and the SVG scenes are paused by hand */
    function setMotion(value) {
        state.motion = value;
        host.dataset.motion = value ? 'on' : 'off';
        const knob = getOne('.sl-lab-motion');
        if (knob) {
            knob.setAttribute('aria-pressed', String(value));
            getOne('span', knob).textContent = 'Анимация ' + (value ? 'включена' : 'выключена');
            getOne('i', knob).className = 'bi bi-' + (value ? 'pause-circle' : 'play-circle');
        }
        host.querySelectorAll('svg').forEach(el => value ? el.unpauseAnimations() : el.pauseAnimations());
        setStore();
    }

    /* Read one theme colour by its token name. A token holds a light-dark() pair that the canvas cannot parse, so the
       value is resolved through a probe standing inside the face and read back as the colour the current mode picked */
    const probe = document.createElement('span');
    probe.hidden = true;
    host.append(probe);
    function getToneValue(name) {
        probe.style.color = 'var(' + name + ')';
        return getComputedStyle(probe).color || '#888888';
    }

    /* One repeatable series per span, so the demo chart shows the same shape on every visit instead of new noise */
    function getSeriesData(span) {
        const plan = spans[span];
        const rows = {mark: [], reqs: [], cach: [], base: []};
        const now = new Date();
        let seed = plan.size * 7919;
        const getNext = () => { seed = (seed * 9301 + 49297) % 233280; return seed / 233280; };
        for (let i = 0; i < plan.size; i++) {
            const wave = Math.sin((i / plan.size) * Math.PI * 2) * 0.28 + Math.sin((i / plan.size) * Math.PI * 6) * 0.08;
            const reqs = Math.round(plan.base * (1 + wave + getNext() * 0.22));
            rows.reqs.push(reqs);
            rows.cach.push(Math.round(reqs * (0.62 + getNext() * 0.16)));
            rows.base.push(Math.round(reqs * (0.14 + getNext() * 0.08)));
            if (plan.hour) {
                rows.mark.push(String(i).padStart(2, '0') + ':00');
            } else {
                const when = new Date(now.getTime() - (plan.size - 1 - i) * 86400000);
                rows.mark.push(when.getDate() + ' ' + months[when.getMonth()]);
            }
        }
        return rows;
    }

    /* The figure above the plot: how much the span carries, and how its second half stands against its first */
    function updateChartInfo(rows) {
        const total = rows.reqs.reduce((sum, item) => sum + item, 0);
        const half = Math.floor(rows.reqs.length / 2);
        const head = rows.reqs.slice(0, half).reduce((sum, item) => sum + item, 0) || 1;
        const tail = rows.reqs.slice(half).reduce((sum, item) => sum + item, 0);
        const grow = ((tail - head) / head) * 100;
        const size = getOne('[data-lab-volume]');
        const move = getOne('[data-lab-change]');
        if (size) size.textContent = total.toLocaleString('ru-RU').replace(/\s/g, ' ');
        if (move) {
            move.textContent = (grow >= 0 ? '+' : '−') + Math.abs(grow).toFixed(1).replace('.', ',') + '%';
            move.style.color = grow >= 0 ? getToneValue('--sl-success') : getToneValue('--sl-danger');
        }
    }

    /* Paint the plot for the current span and shape, in the colours the mode resolves to right now */
    let rows = null;
    function setChartView() {
        const face = getOne('#labChart');
        if (!face) return;
        const plot = face.closest('.sl-lab-plot');
        const step = window.devicePixelRatio || 1;
        const wide = Math.max(plot.clientWidth, 120);
        const high = Math.max(plot.clientHeight, 80);
        face.width = Math.round(wide * step);
        face.height = Math.round(high * step);
        rows = getSeriesData(state.span);
        updateChartInfo(rows);
        const draw = face.getContext('2d');
        draw.setTransform(step, 0, 0, step, 0, 0);
        draw.clearRect(0, 0, wide, high);
        const pad = 18;
        const top = getToneValue('--sl-primary');
        const good = getToneValue('--sl-success');
        const mark = getToneValue('--sl-accent');
        const line = getToneValue('--sl-border');
        const peak = Math.max(...rows.reqs) * 1.15;
        const getX = i => pad + (i / Math.max(rows.reqs.length - 1, 1)) * (wide - pad * 2);
        const getY = value => high - pad - (value / peak) * (high - pad * 2);
        draw.strokeStyle = line;
        draw.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = pad + (i / 4) * (high - pad * 2);
            draw.beginPath();
            draw.moveTo(pad, y);
            draw.lineTo(wide - pad, y);
            draw.stroke();
        }
        if (state.kind === 'bar') {
            const size = Math.max((wide - pad * 2) / rows.reqs.length - 4, 2);
            draw.fillStyle = top;
            rows.reqs.forEach((value, i) => draw.fillRect(getX(i) - size / 2, getY(value), size, high - pad - getY(value)));
        } else {
            const wash = draw.createLinearGradient(0, pad, 0, high - pad);
            wash.addColorStop(0, top);
            wash.addColorStop(1, 'transparent');
            draw.beginPath();
            rows.reqs.forEach((value, i) => i ? draw.lineTo(getX(i), getY(value)) : draw.moveTo(getX(i), getY(value)));
            draw.lineTo(getX(rows.reqs.length - 1), high - pad);
            draw.lineTo(getX(0), high - pad);
            draw.closePath();
            draw.globalAlpha = 0.22;
            draw.fillStyle = wash;
            draw.fill();
            draw.globalAlpha = 1;
            draw.beginPath();
            rows.reqs.forEach((value, i) => i ? draw.lineTo(getX(i), getY(value)) : draw.moveTo(getX(i), getY(value)));
            draw.strokeStyle = top;
            draw.lineWidth = 2;
            draw.stroke();
        }
        [[rows.cach, good], [rows.base, mark]].forEach(([list, tone]) => {
            draw.beginPath();
            list.forEach((value, i) => i ? draw.lineTo(getX(i), getY(value)) : draw.moveTo(getX(i), getY(value)));
            draw.strokeStyle = tone;
            draw.lineWidth = 1.5;
            draw.stroke();
        });
    }

    /* The reading under the pointer: the nearest point of the span, named and counted */
    function updateChartTip(event) {
        const face = getOne('#labChart');
        const note = getOne('.sl-lab-tooltip');
        if (!face || !note || !rows) return;
        const box = face.getBoundingClientRect();
        const pad = 18;
        const part = (event.clientX - box.left - pad) / Math.max(box.width - pad * 2, 1);
        const spot = Math.min(Math.max(Math.round(part * (rows.reqs.length - 1)), 0), rows.reqs.length - 1);
        note.hidden = false;
        note.textContent = rows.mark[spot] + ' · ' + rows.reqs[spot].toLocaleString('ru-RU') + ' запросов';
        note.style.left = Math.min(Math.max(event.clientX - box.left - 60, 0), Math.max(box.width - 150, 0)) + 'px';
        note.style.top = '0px';
    }

    /* The registry follows the map of the hero: the same eight modules, the same state, counted in three places */
    function updateRegistry() {
        const mods = getAll('.module-map .mod[data-mod]');
        const live = mods.filter(el => el.dataset.on === '1').map(el => el.dataset.mod);
        getAll('[data-lab-module]').forEach(el => {
            const on = live.includes(el.dataset.labModule);
            const cell = el.closest('tr')?.querySelector('.sl-lab-status');
            el.setAttribute('aria-pressed', String(on));
            getOne('i', el).className = 'bi bi-toggle-' + (on ? 'on' : 'off');
            if (cell) {
                cell.dataset.off = String(!on);
                cell.textContent = on ? 'Включён' : 'Выключен';
            }
        });
        getAll('[data-lab-enabled]').forEach(el => el.textContent = live.length + ' ACTIVE');
        getAll('[data-lab-count]').forEach(el => el.textContent = live.length + ' / ' + mods.length);
        getAll('[data-lab-build]').forEach(el => {
            const names = builds[el.dataset.labBuild] ?? [];
            el.setAttribute('aria-pressed', String(mods.every(item => (item.dataset.on === '1') === names.includes(item.dataset.mod))));
        });
    }

    /* Hide the rows the filter does not name, matching the module and what the row says it does */
    function filterRows(query) {
        const text = query.toLowerCase().trim();
        getAll('#labRows tr').forEach(el => el.hidden = !el.textContent.toLowerCase().includes(text));
    }

    /* Order the registry by module name, the way the arrow in its header points */
    function setRowsOrder() {
        const body = getOne('#labRows');
        if (!body) return;
        const list = getAll('tr', body);
        const sign = state.desc ? -1 : 1;
        list.sort((one, two) => sign * one.cells[0].textContent.trim().localeCompare(two.cells[0].textContent.trim(), 'ru'));
        list.forEach(el => body.append(el));
        const knob = getOne('[data-lab-sort] i');
        if (knob) knob.className = 'bi bi-sort-alpha-' + (state.desc ? 'up' : 'down');
    }

    /* The command dialog lists the sections the top rail names, so what is typed matches the words the page shows.
       Each entry also carries the heading of its section, which is how an English title is reached by its Russian name */
    function setResultList(query) {
        const list = getOne('.sl-lab-results');
        if (!list) return;
        const text = query.toLowerCase().trim();
        const rest = getAll('.sl-lab-nav-links a[href^="#"]').map(el => {
            const part = getOne(el.getAttribute('href') + ' > .section-head');
            return {
                name: el.textContent.trim(),
                note: getOne('h2', part)?.textContent.trim() ?? '',
                addr: el.getAttribute('href').slice(1)
            };
        }).filter(item => (item.name + ' ' + item.note + ' ' + item.addr).toLowerCase().includes(text));
        list.innerHTML = rest.length
            ? rest.map((item, i) => `<a href="#${item.addr}"${i ? '' : ' aria-selected="true"'}>${item.name}<small>${item.note || item.addr}</small></a>`).join('')
            : '<p>Ничего не найдено</p>';
    }

    /* Move the selection of the dialog, or open the one it stands on. A search field eats the escape key to clear
       itself, so the dialog is closed here by hand instead of leaving it to the native handling of the element */
    function checkDialogKey(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.currentTarget.closest('dialog')?.close();
            return;
        }
        const list = getAll('.sl-lab-results a');
        if (!list.length) return;
        const spot = Math.max(list.findIndex(el => el.getAttribute('aria-selected') === 'true'), 0);
        if (event.key === 'Enter') {
            event.preventDefault();
            list[spot].click();
            return;
        }
        if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
        event.preventDefault();
        const next = (spot + (event.key === 'ArrowDown' ? 1 : list.length - 1)) % list.length;
        list.forEach((el, i) => el.setAttribute('aria-selected', String(i === next)));
        list[next].scrollIntoView({block: 'nearest'});
    }

    /* Borrow the live head and foot of the CMS, so the face is framed by the system it presents. The markup baked
       into the page stays in place when the request fails, which is what a page opened from the file system shows */
    async function getShell() {
        if (params.has('bare')) {
            document.body.dataset.labBare = '';
            root.dataset.shell = 'bare';
            return;
        }
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
            for (const name of ['demo-line', 'footbox']) {
                const part = doc.getElementById(name);
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
            getOne('[data-lab-head]').replaceChildren(head);
            getOne('[data-lab-foot]').replaceChildren(foot);
            getAll('[data-lab-head] .sl-mode button').forEach(el => {
                el.type = 'button';
                el.addEventListener('click', () => setTheme(el.value));
            });
            setTheme(state.theme);
            root.dataset.shell = 'ready';
        } catch (error) {
            root.dataset.shell = 'kept';
            console.error(error);
        }
    }

    getAll('[data-lab-theme]').forEach(el => el.addEventListener('click', () => setTheme(el.dataset.labTheme)));
    getAll('[data-lab-layout]').forEach(el => el.addEventListener('click', () => setLayout(el.dataset.labLayout)));
    getOne('#labLayout')?.addEventListener('change', event => setLayout(event.target.value));
    getOne('#labSeason')?.addEventListener('change', event => setSeason(event.target.value));
    getOne('.sl-lab-motion')?.addEventListener('click', () => setMotion(!state.motion));
    getOne('.sl-lab-tools-toggle')?.addEventListener('click', event => {
        const open = event.currentTarget.getAttribute('aria-expanded') !== 'true';
        event.currentTarget.setAttribute('aria-expanded', String(open));
        getOne('#labSettings').hidden = !open;
    });
    getAll('[data-lab-period]').forEach(el => el.addEventListener('click', () => {
        state.span = el.dataset.labPeriod;
        getAll('[data-lab-period]').forEach(item => item.setAttribute('aria-pressed', String(item === el)));
        setChartView();
        setToastText('Период графика: ' + el.textContent.trim());
    }));
    getAll('[data-lab-chart]').forEach(el => el.addEventListener('click', () => {
        state.kind = el.dataset.labChart;
        getAll('[data-lab-chart]').forEach(item => item.setAttribute('aria-pressed', String(item === el)));
        setChartView();
    }));
    getOne('#labChart')?.addEventListener('pointermove', updateChartTip);
    getAll('[data-lab-module]').forEach(el => el.addEventListener('click', () => {
        getOne(`.module-map .mod[data-mod="${el.dataset.labModule}"]`)?.click();
        setToastText('Модуль ' + el.dataset.labModule + ': ' + (el.getAttribute('aria-pressed') === 'true' ? 'выключен' : 'включён'));
    }));
    getAll('[data-lab-build]').forEach(el => el.addEventListener('click', () => {
        const names = builds[el.dataset.labBuild] ?? [];
        getAll('.module-map .mod[data-mod]').forEach(item => {
            if ((item.dataset.on === '1') !== names.includes(item.dataset.mod)) item.click();
        });
        setToastText('Сборка: ' + el.textContent.trim());
    }));
    getOne('#labFilter')?.addEventListener('input', event => filterRows(event.target.value));
    getOne('[data-lab-sort]')?.addEventListener('click', () => {
        state.desc = !state.desc;
        setRowsOrder();
    });
    getAll('.sl-lab-card').forEach(el => el.addEventListener('pointermove', event => {
        const box = el.getBoundingClientRect();
        el.style.setProperty('--sl-d-x', (event.clientX - box.left) + 'px');
        el.style.setProperty('--sl-d-y', (event.clientY - box.top) + 'px');
    }));
    const talk = getOne('.sl-lab-dialog');
    getAll('[data-lab-command]').forEach(el => el.addEventListener('click', () => {
        setResultList('');
        talk?.showModal();
        getOne('#labCommand')?.focus();
    }));
    getOne('[data-lab-close]')?.addEventListener('click', () => talk?.close());
    getOne('#labCommand')?.addEventListener('input', event => setResultList(event.target.value));
    getOne('#labCommand')?.addEventListener('keydown', checkDialogKey);
    talk?.addEventListener('click', event => { if (event.target === talk) talk.close(); });
    talk?.addEventListener('close', () => getOne('#labCommand') && (getOne('#labCommand').value = ''));
    getOne('.sl-lab-results')?.addEventListener('click', () => talk?.close());
    addEventListener('keydown', event => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            setResultList('');
            talk?.showModal();
            getOne('#labCommand')?.focus();
        }
    });
    const map = getOne('.module-map');
    if (map) new MutationObserver(updateRegistry).observe(map, {subtree: true, attributes: true, attributeFilter: ['data-on']});
    const marks = getAll('.sl-lab-nav-links a[href^="#"]');
    const watch = new IntersectionObserver(rest => rest.forEach(item => {
        if (!item.isIntersecting) return;
        marks.forEach(el => el.getAttribute('href') === '#' + item.target.id ? el.setAttribute('aria-current', 'location') : el.removeAttribute('aria-current'));
    }), {rootMargin: '-30% 0px -62% 0px'});
    getAll('.sl-lab section[id]').forEach(el => watch.observe(el));
    media.addEventListener('change', () => setTheme(state.theme));
    addEventListener('resize', () => setChartView());

    setTheme(state.theme);
    setSeason(state.season);
    setLayout(state.layout);
    setMotion(state.motion);
    updateRegistry();
    setChartView();
    setResultList('');
    getShell();
})();
