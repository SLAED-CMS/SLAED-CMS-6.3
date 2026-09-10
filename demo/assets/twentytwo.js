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
        setRadials();
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
        if (params.has('bare')) { document.body.dataset.leaderBare = ''; root.dataset.shell = 'bare'; }
        try {
            const url = new URL('../index.php?name=presentation', location.href);
            const reply = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
            if (!reply.ok) throw new Error('CMS response: ' + reply.status);
            const doc = new DOMParser().parseFromString(await reply.text(), 'text/html');
            const modal = doc.querySelector('dialog[data-sl-shot="view"]');
            if (!modal) throw new Error('CMS image viewer is missing');
            modal.id = 'sl-presentation-gallery';
            modal.append(getOne('#sl-gallery-actions').content.cloneNode(true));
            document.body.append(modal);
            document.dispatchEvent(new CustomEvent('sl:gallery-ready', {detail: modal}));
            if (params.has('bare')) return;
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
    /* One renderer owns every radial scale, including multi-track rings and cockpit tick marks */
    function setRadial(el) {
        const config = JSON.parse(el.dataset.radial);
        const icon = el.querySelector(':scope > i');
        if (icon) icon.style.color = `var(${config.arcs[0].tone})`;
        let canvas = el.querySelector(':scope > .sl-radial-canvas');
        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.className = 'sl-radial-canvas';
            canvas.setAttribute('aria-hidden', 'true');
            el.prepend(canvas);
        }
        const width = el.clientWidth, height = el.clientHeight;
        if (!width || !height) return;
        const dpr = Math.min(devicePixelRatio || 1, 2), pad = 16;
        canvas.width = Math.round((width + pad * 2) * dpr);
        canvas.height = Math.round((height + pad * 2) * dpr);
        const draw = canvas.getContext('2d');
        draw.setTransform(dpr, 0, 0, dpr, pad * dpr, pad * dpr);
        const start = (config.start ?? -90) * Math.PI / 180;
        const sweep = (config.sweep ?? 360) * Math.PI / 180;
        const radius = config.ticks ? Math.min(width / 2, height / 1.25) - 8 : Math.min(width, height) / 2;
        const cx = width / 2, cy = height / 2 + radius * (config.offset ?? 0);
        draw.lineCap = 'round';
        config.arcs.forEach(arc => {
            const thick = arc.width ?? 5;
            const rad = radius - (arc.inset ?? (config.ticks ? 0 : thick / 2));
            const value = Math.max(0, Math.min(1, arc.value / (arc.max ?? 100)));
            draw.lineWidth = thick;
            draw.strokeStyle = getChartTone('--line');
            draw.beginPath();
            draw.arc(cx, cy, rad, start, start + sweep);
            draw.stroke();
            if (!value) return;
            draw.save();
            draw.strokeStyle = getChartTone(arc.tone);
            draw.shadowColor = getChartTone(arc.tone, .5);
            draw.shadowBlur = 10 * dpr;
            draw.beginPath();
            draw.arc(cx, cy, rad, start, start + sweep * value);
            draw.stroke();
            draw.restore();
        });
        if (!config.ticks) return;
        draw.strokeStyle = getChartTone('--muted');
        draw.lineWidth = 1;
        for (let i = 0; i <= config.ticks; i++) {
            const angle = start + sweep * i / config.ticks;
            const outer = radius - config.arcs[0].width;
            const inner = outer - (i % 3 === 0 ? 7 : 4);
            draw.beginPath();
            draw.moveTo(cx + Math.cos(angle) * outer, cy + Math.sin(angle) * outer);
            draw.lineTo(cx + Math.cos(angle) * inner, cy + Math.sin(angle) * inner);
            draw.stroke();
        }
    }

    /* The same redraw path handles theme changes and explicit repaint requests */
    function setRadials() { getAll('[data-radial]').forEach(setRadial); }


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
    function getChartTone(name, alpha = 1) {
        const probe = document.createElement('span');
        probe.hidden = true;
        probe.style.color = `color-mix(in srgb, var(${name}) ${alpha * 100}%, transparent)`;
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
        const colors = ['--sl-primary-strong', '--sl-accent', '--sl-success'].map(tone => getChartTone(tone));
        const muted = getChartTone('--sl-text-muted');
        const border = getChartTone('--sl-border');
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
    setRadials();
    const rings = new ResizeObserver(entries => entries.forEach(entry => setRadial(entry.target)));
    getAll('[data-radial]').forEach(el => rings.observe(el));
    setRailFollow();
    setPointerLight();
    setLabControls();
    setLabChart();
    addEventListener('resize', () => { setRadials(); setLabChart(); }, {passive: true});
    getShell();
})();

/* Collections share one CMS viewer, its download action and the same navigation state. */
(() => {
    'use strict';
    const host = document.querySelector('.sl-leader');
    let modal = null, items = [], current = 0, touch = null, select = null, origin = null;

    /* Every step updates the original, its caption, download and the featured archive material */
    function setGalleryView(index) {
        current = (index + items.length) % items.length;
        const item = items[current];
        const image = modal.querySelector('[data-sl-shot-img]');
        const title = modal.querySelector('[data-sl-shot-name]');
        const down = modal.querySelector('[data-sl-shot-down]');
        image.src = item.href;
        image.alt = item.dataset.title;
        title.textContent = item.dataset.title;
        title.title = item.dataset.title;
        modal.querySelector('[data-sl-shot-num]').textContent = `${current + 1} / ${items.length}`;
        down.href = item.href;
        down.download = decodeURIComponent(new URL(item.href).pathname.split('/').pop());
        if (select) select(current, false);
    }

    document.addEventListener('sl:gallery-ready', event => {
        modal = event.detail;
        const stage = modal.querySelector('.sl-shot-stage');
        modal.querySelector('[data-sl-shot-img]').draggable = false;
        modal.querySelectorAll('[data-sl-shot-step]').forEach(el => {
            const step = Number(el.dataset.slShotStep);
            const label = step < 0 ? 'Предыдущее изображение' : 'Следующее изображение';
            el.title = label;
            el.setAttribute('aria-label', label);
            el.addEventListener('click', () => setGalleryView(current + step));
        });
        modal.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : current + (event.key === 'ArrowRight' ? 1 : -1);
            setGalleryView(next);
        });
        modal.addEventListener('close', () => {
            modal.querySelector('[data-sl-shot-img]').removeAttribute('src');
            if (origin && !origin.checkVisibility()) items[current].focus({preventScroll: true});
        });
        stage.addEventListener('pointerdown', event => {
            if (event.pointerType !== 'mouse') touch = event.clientX;
        });
        stage.addEventListener('pointerup', event => {
            if (touch !== null && Math.abs(event.clientX - touch) > 50) setGalleryView(current + (event.clientX < touch ? 1 : -1));
            touch = null;
        });
        stage.addEventListener('pointercancel', () => { touch = null; });
    }, {once: true});

    host.querySelectorAll('[data-gallery]').forEach(gallery => {
        const track = gallery.querySelector('.sl-gallery-track');
        const cards = [...track.children];
        const links = [...track.querySelectorAll('.sl-gallery-open')];
        const range = gallery.querySelector('input');
        const output = gallery.querySelector('output');
        const brand = gallery.dataset.gallery === 'brand';
        let active = 0, visible = 1, limit = 0;

        /* Site cards come in a fresh order on every load, and the position labels follow that order */
        if (gallery.dataset.gallery === 'sites') {
            for (let i = cards.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [cards[i], cards[j]] = [cards[j], cards[i]];
            }
            track.append(...cards);
            track.scrollTo({left: 0, behavior: 'instant'});
            cards.forEach((card, index) => card.setAttribute('aria-label', `${index + 1} из ${cards.length}`));
        }

        /* The archive shows a feature with four neighbours; site cards retain their scroll-snap strip */
        function updateGallery() {
            if (brand) {
                visible = 1;
                limit = cards.length - 1;
                cards.forEach((card, index) => {
                    const slot = (index - active + cards.length) % cards.length;
                    card.hidden = slot > 4;
                    card.style.order = slot;
                    card.toggleAttribute('data-gallery-feature', slot === 0);
                });
            } else {
                const width = cards[0].getBoundingClientRect().width;
                const gap = parseFloat(getComputedStyle(track).columnGap);
                visible = Math.max(1, Math.round((track.clientWidth + gap) / (width + gap)));
                limit = Math.max(0, cards.length - visible);
                active = Math.min(limit, Math.max(0, Math.round(track.scrollLeft / (width + gap))));
            }
            range.max = limit;
            range.value = active;
            range.setAttribute('aria-valuetext', `${active + 1} из ${cards.length}`);
            output.textContent = `${active + 1}${visible > 1 ? '–' + Math.min(cards.length, active + visible) : ''} / ${cards.length}`;
        }

        /* One position control drives arrows, keyboard and range input */
        function setGallerySlide(index, smooth = true) {
            const next = Math.min(limit, Math.max(0, index));
            if (brand) {
                active = next;
                updateGallery();
            } else {
                const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
                const motion = smooth && host.dataset.motion !== 'off' && !reduce;
                track.scrollTo({left: cards[next].offsetLeft, behavior: motion ? 'smooth' : 'instant'});
            }
        }

        gallery.querySelectorAll('[data-gallery-step]').forEach(el => {
            el.addEventListener('click', () => {
                const step = Number(el.dataset.galleryStep);
                setGallerySlide(step > 0 && active === limit ? 0 : step < 0 && active === 0 ? limit : active + step * visible);
            });
        });
        range.addEventListener('input', () => setGallerySlide(Number(range.value), false));
        if (!brand) track.addEventListener('scroll', updateGallery, {passive: true});
        track.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            setGallerySlide(event.key === 'Home' ? 0 : event.key === 'End' ? limit : active + (event.key === 'ArrowRight' ? 1 : -1));
            if (brand) track.focus({preventScroll: true});
        });
        links.forEach(link => link.closest('.sl-gallery-card').querySelector('.sl-gallery-expand')?.addEventListener('click', () => link.click()));
        links.forEach((link, index) => link.addEventListener('click', event => {
            if (!modal || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            event.preventDefault();
            items = links;
            select = brand ? setGallerySlide : null;
            origin = link;
            setGalleryView(index);
            window.setWindowOpen(modal);
        }));
        new ResizeObserver(updateGallery).observe(track);
        updateGallery();

        /* The site strip turns by itself while it is on screen and nobody hovers or focuses it; motion off holds it */
        if (gallery.dataset.gallery === 'sites') {
            let held = false, shown = false, timer = 0;
            function setAutoplay() {
                window.clearInterval(timer);
                timer = shown && !held ? window.setInterval(() => {
                    if (host.dataset.motion === 'off' || document.hidden || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
                    setGallerySlide(active >= limit ? 0 : active + 1);
                }, 4000) : 0;
            }
            gallery.addEventListener('pointerenter', () => { held = true; setAutoplay(); });
            gallery.addEventListener('pointerleave', () => { held = false; setAutoplay(); });
            gallery.addEventListener('focusin', () => { held = true; setAutoplay(); });
            gallery.addEventListener('focusout', event => {
                if (gallery.contains(event.relatedTarget)) return;
                held = false;
                setAutoplay();
            });
            new IntersectionObserver(([entry]) => { shown = entry.isIntersecting; setAutoplay(); }, {threshold: .25}).observe(gallery);
        }
    });
})();
