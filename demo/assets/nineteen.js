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
    const builds = {
        portal: ['News', 'Files', 'Users', 'Security', 'Search', 'SEO', 'Pages'],
        media: ['News', 'Users', 'Security', 'Search', 'SEO', 'Media'],
        knowledge: ['Files', 'Users', 'Security', 'Search', 'SEO', 'Pages']
    };
    let saved = {};
    try { saved = JSON.parse(localStorage.getItem('slaed.nineteen.restored') ?? '{}'); } catch {}
    const theme = params.get('mode') ?? saved.theme ?? 'dark';
    const season = (params.get('season') ?? saved.season ?? 'autumn').replace(/^sl-/, '');
    const state = {
        theme: ['light', 'dark', 'auto'].includes(theme) ? theme : 'auto',
        season: seasons.includes(season) ? season : 'autumn',
        layout: layouts.includes(saved.layout) ? saved.layout : 'overview',
        motion: saved.motion ?? !matchMedia('(prefers-reduced-motion: reduce)').matches,
        span: 'day',
        kind: 'area',
        desc: false
    };

    /* Keep the choices of the settings panel across visits, and never let a full storage break the page */
    function setStore() {
        try { localStorage.setItem('slaed.nineteen.restored', JSON.stringify(state)); } catch {}
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
        if (state.span === 'day') return String(index).padStart(2, '0') + ':00';
        if (state.span === 'week') return ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'][Math.min(6, Math.floor(index / 4))];
        return String(index + 1) + ' авг';
    }
    function setChartView() {
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
        const colors = ['--sl-primary-strong', '--sl-accent', '--sl-success'].map(getToneValue);
        const muted = getToneValue('--sl-text-muted');
        const border = getToneValue('--sl-border');
        const series = getSeriesData(state.span);
        getOne('[data-lab-volume]').textContent = sets[state.span][0].toLocaleString('ru-RU');
        getOne('[data-lab-change]').textContent = '+' + sets[state.span][1] + '%';
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
            if (state.kind === 'bar') {
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
    function updateChartTip(event) {
        const face = getOne('#labChart'), tip = getOne('.sl-lab-tooltip');
        const rect = face.getBoundingClientRect();
        hover = Math.max(0, Math.min(sets[state.span][2] - 1,
            Math.round((event.clientX - rect.left - 34) / (rect.width - 44) * (sets[state.span][2] - 1))));
        const series = getSeriesData(state.span);
        tip.textContent = getChartLabel(hover) + ' · ' + Math.round(series[0][hover] * 1000) + ' запросов';
        tip.hidden = false;
        setChartView();
    }
    getOne('#labChart').addEventListener('pointerleave', () => {
        hover = -1;
        getOne('.sl-lab-tooltip').hidden = true;
        setChartView();
    });
    new ResizeObserver(setChartView).observe(getOne('.sl-lab-plot'));

    /* The registry follows the map of the hero: the same eight modules, the same state, counted in three places */
    function updateRegistry() {
        const mods = getAll('.module-map .mod[data-mod]');
        const live = mods.filter(el => el.dataset.on === '1').map(el => el.dataset.mod);
        mods.forEach(el => el.setAttribute('aria-pressed', String(el.dataset.on === '1')));
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

    /* Borrow the live head and foot of the CMS, retaining the standalone presentation if the request fails */
    async function getShell() {
        if (params.has('bare')) {
            document.body.dataset.labBare = '';
            root.dataset.shell = 'bare';
            return;
        }
        try {
            const url = new URL('../index.php?name=presentation', location.href);
            const reply = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!reply.ok) throw new Error('CMS response: ' + reply.status);
            const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
            const wrap = doc.querySelector('body > .sl-wrp');
            if (!wrap) throw new Error('CMS content boundary is missing');
            const head = document.createDocumentFragment();
            let node = doc.body.firstElementChild;
            while (node && node !== wrap) {
                const next = node.nextElementSibling;
                if (node.id !== 'head-content') head.append(node);
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
    getAll('.module-map .mod[data-mod]').forEach(el => {
        el.tabIndex = 0;
        el.setAttribute('role', 'button');
        el.addEventListener('keydown', event => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            el.click();
        });
    });
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
