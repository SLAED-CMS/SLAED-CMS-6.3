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
    getShell();
})();
