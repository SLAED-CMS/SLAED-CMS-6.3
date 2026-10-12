(function () {
    'use strict';
    if (window.SlaedPhoenix) return;
    const base = new URL('./', document.currentScript.src);
    const root = document.documentElement;
    let active = null;
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = new URL('embed.css', base).href;
    const styles = new Promise((done) => {
        link.addEventListener('load', () => done(true), {once: true});
        link.addEventListener('error', () => done(false), {once: true});
    });
    document.head.append(link);

    /* Resolve the current page theme without changing its settings */
    function getFilmTheme(mode) {
        const value = mode || root.dataset.theme;
        if (value === 'dark' || value === 'light') return value;
        return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    /* Load a named preset only from this package */
    async function getFilmPreset(name, signal) {
        if (!/^[a-z][a-z0-9-]{0,31}$/.test(name)) throw new Error('Invalid Phoenix preset');
        const url = new URL('presets/' + name + '.json', base);
        const reply = await fetch(url, {signal});
        if (!reply.ok) throw new Error('Phoenix preset is unavailable');
        return reply.json();
    }

    /* Expose lifecycle events to any page that integrates the presentation */
    function setFilmEvent(name, data = {}) {
        document.dispatchEvent(new CustomEvent('slaed:phoenix:' + name, {detail: data}));
    }

    /* Release the modal and audio context and restore the host page */
    function setFilmDone(reason) {
        const live = active;
        if (!live) return;
        active = null;
        clearTimeout(live.timer);
        clearTimeout(live.tail);
        live.abort.abort();
        if (root.style.overflow === 'hidden') root.style.overflow = live.scroll;
        if (live.view?.open) live.view.close();
        live.view?.remove();
        if (live.audio && live.audio.state !== 'closed') live.audio.close().catch(() => {});
        const focus = document.activeElement;
        if (live.focus?.isConnected && (live.phase !== 'revealed' || focus === document.body || live.view?.contains(focus))) {
            live.focus.focus({preventScroll: true});
        }
        const result = {reason, preset: live.preset};
        setFilmEvent(reason === 'error' ? 'error' : 'end', result);
        live.done(result);
    }

    /* Close a running presentation while stopping its musical tail */
    function setFilmStop() {
        const live = active;
        if (!live) return;
        if (live.frame?.contentWindow) {
            live.frame.contentWindow.postMessage({channel: 'slaed-phoenix', action: 'stop'}, base.origin);
            clearTimeout(live.tail);
            live.tail = setTimeout(() => {if (active === live) setFilmDone('close');}, 250);
        } else setFilmDone('close');
    }

    /* Create one isolated player on the current page using a movable package URL */
    async function setFilmView(live, setup, cfg) {
        if (active !== live) return;
        if (base.origin !== location.origin) throw new Error('Phoenix must be served from the same origin as its host page');
        const theme = getFilmTheme(setup.theme || cfg.theme);
        const sound = typeof setup.sound === 'boolean' ? setup.sound : cfg.sound === true;
        const level = Number(setup.volume ?? cfg.volume ?? 0.7);
        const auto = typeof setup.autoplay === 'boolean' ? setup.autoplay : cfg.autoplay === true;
        const url = new URL('index.html', base);
        url.searchParams.set('embed', '1');
        url.searchParams.set('theme', theme);
        url.searchParams.set('volume', String(Number.isFinite(level) ? Math.max(0, Math.min(1, level)) : 0.7));
        url.searchParams.set('sound', sound ? '1' : '0');
        url.searchParams.set('autoplay', auto && (!sound || live.audio?.state === 'running') ? '1' : '0');
        if (typeof setup.at === 'number' && Number.isFinite(setup.at)) url.searchParams.set('at', String(setup.at));
        const view = document.createElement('dialog');
        const frame = document.createElement('iframe');
        view.className = 'sl-phx-host';
        view.setAttribute('aria-label', 'Презентация SLAED Phoenix');
        frame.title = 'Презентация SLAED Phoenix';
        frame.allow = 'autoplay';
        frame.referrerPolicy = 'same-origin';
        frame.src = url.href;
        view.append(frame);
        document.body.append(view);
        live.view = view;
        live.frame = frame;
        live.phase = 'loading';
        root.style.overflow = 'hidden';
        view.addEventListener('cancel', (event) => {event.preventDefault(); setFilmStop();});
        view.addEventListener('close', () => {if (active === live && live.phase !== 'revealed') setFilmDone('close');});
        view.showModal();
        frame.focus({preventScroll: true});
    }

    /* Start from a button gesture or show the existing sound choice for automatic launches */
    function setFilmPlay(options = {}) {
        if (active) return active.wait;
        const setup = options && typeof options === 'object' ? options : {};
        let done;
        const wait = new Promise((reply) => {done = reply;});
        const live = {wait, done, preset: setup.preset || getFilmTheme(setup.theme), abort: new AbortController(),
            audio: null, view: null, frame: null, phase: 'preparing', focus: document.activeElement, scroll: root.style.overflow};
        active = live;
        live.timer = setTimeout(() => {if (active === live) setFilmDone('error');}, 30000);
        let resumed = Promise.resolve();
        const audioctx = window.AudioContext || window.webkitAudioContext;
        if (audioctx && navigator.userActivation?.isActive && setup.sound !== false) {
            live.audio = new audioctx();
            resumed = live.audio.resume().catch(() => {});
        }
        Promise.all([getFilmPreset(live.preset, live.abort.signal), styles, resumed]).then(([cfg, styled]) => {
            if (!styled) throw new Error('Phoenix host stylesheet is unavailable');
            return setFilmView(live, setup, cfg);
        }).catch(() => {if (active === live) setFilmDone('error');});
        return wait;
    }

    /* Accept lifecycle notifications only from the active player and its origin */
    function setFilmMessage(event) {
        const live = active;
        const data = event.data;
        if (!live?.frame || event.source !== live.frame.contentWindow || event.origin !== base.origin || data?.channel !== 'slaed-phoenix') return;
        if (data.event === 'ready') {
            clearTimeout(live.timer);
            live.phase = 'ready';
            setFilmEvent('ready', {preset: live.preset, theme: data.theme});
        } else if (data.event === 'reveal') {
            live.phase = 'revealed';
            if (live.view.open) live.view.close();
            if (root.style.overflow === 'hidden') root.style.overflow = live.scroll;
            setFilmEvent('reveal', {preset: live.preset});
            live.tail = setTimeout(() => {if (active === live) setFilmDone('complete');}, Math.max(0, Number(data.tail) || 0) * 1000 + 500);
        } else if (data.event === 'end') setFilmDone(data.reason || 'complete');
        else if (data.event === 'error') setFilmDone('error');
    }

    /* Connect declarative launch buttons without altering the host form or router */
    function setFilmTrigger(event) {
        const node = event.target.closest('[data-sl-phoenix]');
        if (!node) return;
        event.preventDefault();
        const setup = {};
        if (node.dataset.slPhoenixPreset) setup.preset = node.dataset.slPhoenixPreset;
        if (node.dataset.slPhoenixSound !== undefined) setup.sound = node.dataset.slPhoenixSound === '1';
        if (node.dataset.slPhoenixAutoplay !== undefined) setup.autoplay = node.dataset.slPhoenixAutoplay === '1';
        if (node.dataset.slPhoenixVolume !== undefined) setup.volume = Number(node.dataset.slPhoenixVolume);
        setFilmPlay(setup);
    }

    window.SlaedPhoenix = Object.freeze({setPlay: setFilmPlay, setStop: setFilmStop,
        isPlaying: () => Boolean(active), getAudioContext: () => active?.audio || null});
    window.addEventListener('message', setFilmMessage);
    window.addEventListener('pagehide', () => setFilmDone('pagehide'));
    document.addEventListener('click', setFilmTrigger);
})();
