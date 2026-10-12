(function () {
    'use strict';
    const root = document.documentElement;
    const stage = document.querySelector('.sl-phx-stage');
    const canvas = document.querySelector('.sl-phx-world');
    const ctx = canvas.getContext('2d');
    const cover = document.querySelector('.sl-phx-cover');
    const history = document.querySelector('.sl-phx-history');
    const silence = document.querySelector('.sl-phx-silence');
    const rewrite = document.querySelector('.sl-phx-rewrite');
    const editor = document.querySelector('.sl-phx-editor');
    const works = [...document.querySelectorAll('.sl-phx-work')];
    const labs = [...document.querySelectorAll('.sl-phx-lab > article')];
    const dna = document.querySelector('.sl-phx-dna');
    const panels = [...document.querySelectorAll('.sl-phx-principle')];
    const tabs = [...document.querySelectorAll('.sl-phx-dna-index > span')];
    const year = document.querySelector('[data-sl-phx-year]');
    const era = document.querySelector('[data-sl-phx-era]');
    const yearbar = document.querySelector('.sl-phx-years > div i');
    const idea = document.querySelector('.sl-phx-idea');
    const wires = document.querySelector('.sl-phx-wires g');
    const radar = document.querySelector('.sl-phx-radar i');
    const motto = document.querySelector('.sl-phx-motto strong');
    const code = document.querySelector('[data-sl-phx-code]');
    const file = document.querySelector('[data-sl-phx-file]');
    const build = document.querySelector('[data-sl-phx-build]');
    const percent = document.querySelector('[data-sl-phx-percent]');
    const buildbar = document.querySelector('.sl-phx-build i');
    const lines = document.querySelector('.sl-phx-line-numbers');
    const assemble = document.querySelector('.sl-phx-assembly');
    const bird = document.querySelector('.sl-phx-phoenix');
    const reborn = document.querySelector('.sl-phx-reborn');
    const finale = document.querySelector('.sl-phx-finale');
    const logo = document.querySelector('.sl-phx-final-logo');
    const portal = document.querySelector('.sl-phx-portal');
    const flash = document.querySelector('.sl-phx-flash');
    const scan = document.querySelector('.sl-phx-scanlines');
    const bar = document.querySelector('.sl-phx-progress > i');
    const timer = document.querySelector('[data-sl-phx-time]');
    const chapter = document.querySelector('[data-sl-phx-chapter]');
    const status = document.querySelector('[data-sl-phx-status]');
    const loader = document.querySelector('[data-sl-phx-load]');
    const panel = document.querySelector('.sl-phx-panel');
    const control = document.querySelector('.sl-phx-controls');
    const pause = document.querySelector('[data-sl-phx-pause]');
    const replay = document.querySelector('[data-sl-phx-replay]');
    const soundbtn = document.querySelector('[data-sl-phx-sound]');
    const track = document.querySelector('[data-sl-phx-track]');
    const params = new URLSearchParams(location.search);
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const still = motion.matches || params.get('still') === '1';
    const length = 45;
    const beat = 60 / 128;
    const score = new window.PhoenixScore();
    const sources = [
        {
            file: 'core/system.php', label: 'Переписываем ядро',
            code: [
                '<?php', '', "$name = getVar('get', 'name', 'text', '');", "$mode = getCookies('mode');", '',
                '$view = $tpl->getHtmlPart(', "    'module-head', $data", ');', '', '# A new beginning, line by line'
            ].join('\n')
        },
        {
            file: 'core/classes/pdo.php', label: 'Новая работа с данными',
            code: [
                '<?php', '', "$sql = 'SELECT * FROM '.PREFIX_DB.'_users';", "$sql .= ' WHERE id = :id';", '',
                '$rows = $db->getSqlQuery($sql, [', "    'id' => $uid", ']);', '', '# Prepared queries', '# Named parameters', '# MariaDB'
            ].join('\n')
        },
        {
            file: 'core/security.php', label: 'Безопасность с первого шага',
            code: [
                '<?php', '', "$token = getSiteToken('admin');", '', "if (!checkSiteToken($token, 'admin')) {", '    return;', '}', '',
                "$title = getVar('post', 'title', 'text', '');", '', '# Validate input', '# Escape output', '# Protect every action'
            ].join('\n')
        },
        {
            file: 'templates/admin/layouts/admin.html', label: 'Новый интерфейс. Обе темы.',
            code: [
                '<html data-theme="{{ mode }}">', '', '<main class="sl-admin-content">', '    {% block content %}',
                '    {% endblock %}', '</main>', '', '--sl-primary: light-dark(#0877b1, #38a9de);',
                '--sl-surface: light-dark(#f7fbfd, #151d2b);', '', '# One system. Two modes.'
            ].join('\n')
        }
    ];
    const glyphs = 'SLAED01PHP84{}[]<>/=$:;MariaDBtplcore';
    const stars = Array.from({length: 160}, (_, index) => ({xx: getSeed(index), yy: getSeed(index + 618), size: getSeed(index + 271)}));
    let palette = [];
    let rgb = [];
    let atlas = null;
    let face = 'monospace';
    let wide = innerWidth;
    let high = innerHeight;
    let ratio = 1;
    let cloud = null;
    let ready = false;
    let loaded = false;
    let running = false;
    let started = false;
    let pending = false;
    let sound = false;
    let ended = false;
    let spent = 0;
    let start = 0;
    let frame = 0;
    let coverat = performance.now();
    let pointer = [0, 0];
    let birdwide = 0;
    let birdhigh = 0;
    let logobox = [0, 0, 0, 0];
    let lastfile = -1;
    let lasttext = -1;
    let download = '';

    /* Clamp timeline values before interpolating scene states */
    function getClamp(num) { return Math.max(0, Math.min(1, num)); }

    /* Give a segment a smooth start and finish */
    function getEase(num) { const val = getClamp(num); return val * val * (3 - 2 * val); }

    /* Fade a chapter inside a specified timeline window */
    function getWindow(time, from, unto, span = 0.7) {
        return getEase((time - from) / span) * (1 - getEase((time - unto) / span));
    }

    /* Preserve identical archive, stars and code streams across repeated plays */
    function getSeed(seed) { const val = Math.sin(seed * 127.1 + 311.7) * 43758.5453; return val - Math.floor(val); }

    /* Fetch and decode each local image before starting the synchronized film */
    function getSceneImage(url) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.onload = () => resolve(image);
            image.onerror = () => reject(new Error('Cannot load presentation image: ' + url));
            image.src = url;
        });
    }

    /* Resolve the real admin theme tokens into CSS colors and GPU channels */
    function setPalette() {
        const probe = document.createElement('span');
        document.body.append(probe);
        const names = ['--sl-pres-link-text', '--sl-pres-text', '--sl-pres-warn-text', '--sl-primary', '--sl-primary-muted', '--sl-pres-good-text'];
        palette = names.map((name) => {
            probe.style.color = 'var(' + name + ')';
            return getComputedStyle(probe).color;
        });
        rgb = palette.map((tone) => tone.match(/[\d.]+/g).slice(0, 3).map(Number));
        probe.remove();
        face = getComputedStyle(root).getPropertyValue('--sl-face-mono').trim();
        atlas = document.createElement('canvas');
        atlas.width = glyphs.length * 36;
        atlas.height = 120;
        const brush = atlas.getContext('2d');
        brush.scale(2, 2);
        brush.font = '12px ' + face;
        for (let row = 0; row < 3; row++) {
            brush.fillStyle = palette[row === 0 ? 1 : row === 1 ? 0 : 4];
            for (let i = 0; i < glyphs.length; i++) brush.fillText(glyphs[i], i * 18 + 1, row * 20 + 16);
        }
        if (cloud) cloud.setTheme(rgb, root.dataset.theme === 'dark');
    }

    /* Resize all rendering surfaces and measure the true SVG wordmark once */
    function setSceneSize() {
        wide = innerWidth;
        high = innerHeight;
        ratio = Math.min(devicePixelRatio || 1, 1.5);
        canvas.width = Math.round(wide * ratio);
        canvas.height = Math.round(high * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        birdwide = bird.clientWidth;
        birdhigh = birdwide * 2 / 3;
        const rect = logo.getBoundingClientRect();
        logobox = [rect.left + rect.width / 2, rect.top + rect.height / 2, rect.width, rect.height];
        if (cloud) cloud.setResize(wide, high);
    }

    /* Paint a broad energy halo without per-particle canvas shadows */
    function addWorldGlow(xx, yy, radius, tone, alpha) {
        const grad = ctx.createRadialGradient(xx, yy, 0, xx, yy, radius);
        grad.addColorStop(0, tone);
        grad.addColorStop(1, 'transparent');
        ctx.globalAlpha = alpha;
        ctx.fillStyle = grad;
        ctx.fillRect(xx - radius, yy - radius, radius * 2, radius * 2);
    }

    /* Draw blue Matrix streams, traveling code, a perspective grid and musical shockwaves */
    function setWorldFrame(time, idle = false) {
        ctx.clearRect(0, 0, wide, high);
        const dark = root.dataset.theme === 'dark';
        const matrix = idle ? 0.13 : 0.04 + getWindow(time, 0.2, 7, 0.9) * 0.18 + getWindow(time, 23.5, 32.7, 1.5) * 0.68;
        const space = matrix < 0.2 ? 40 : 24;
        const count = Math.ceil(wide / space);
        const warp = idle ? 0 : getWindow(time, 0.1, 7.1, 1.1) * 0.3 + getWindow(time, 27.5, 34.3, 1.1);
        const power = idle ? 0.09 : getWindow(time, 30.3, 42.5, 1.8);
        const pulse = Math.exp(-(time % beat) / 0.09) * power;
        const center = wide / 2;
        const horizon = high * 0.56;
        ctx.globalCompositeOperation = dark ? 'lighter' : 'source-over';
        addWorldGlow(center, high * 0.45, Math.min(wide, high) * 0.58, palette[0], 0.07 + power * 0.08 + pulse * 0.025);
        addWorldGlow(center, high * 0.71, Math.min(wide, high) * 0.5, palette[4], 0.04 + warp * 0.06);
        stars.forEach((star, index) => {
            const yy = (star.yy * high + time * (1 + star.size * 2)) % high;
            ctx.globalAlpha = (0.1 + star.size * 0.32) * (idle ? 0.6 : 1 - warp * 0.5);
            ctx.fillStyle = palette[index % 4 === 0 ? 1 : 0];
            ctx.fillRect(star.xx * wide, yy, 0.6 + star.size, 0.6 + star.size);
        });
        for (let i = 0; i < count; i++) {
            const seed = getSeed(i + 175);
            const xx = i * space + 4;
            const head = (seed * high + time * (44 + seed * 95) * (1 + warp)) % (high + 230);
            const trail = matrix < 0.2 ? 9 : 16;
            for (let j = 0; j < trail; j++) {
                const yy = head - j * 17;
                if (yy < 70 || yy > high - 55) continue;
                ctx.globalAlpha = matrix * (1 - j / trail) * (0.22 + seed * 0.6);
                const row = j === 0 ? 0 : i % 7 === 0 ? 2 : 1;
                const at = Math.floor(time * 7 + i * 71 + j * 13) % glyphs.length;
                ctx.drawImage(atlas, at * 36, row * 40, 36, 40, xx, yy - 16, 18, 20);
            }
        }
        ctx.globalAlpha = 0.07 + warp * 0.16;
        ctx.strokeStyle = palette[0];
        ctx.lineWidth = 0.65;
        ctx.beginPath();
        for (let i = -10; i <= 10; i++) {
            ctx.moveTo(center + i * 8, horizon);
            ctx.lineTo(center + i * wide * 0.15, high);
        }
        ctx.stroke();
        ctx.globalAlpha = 0.06 + warp * 0.1;
        ctx.beginPath();
        for (let i = 0; i < 15; i++) {
            const depth = (i / 15 + time * 0.1) % 1;
            const yy = horizon + depth ** 3 * (high - horizon);
            ctx.moveTo(0, yy);
            ctx.lineTo(wide, yy);
        }
        ctx.stroke();
        if (warp > 0.01) {
            for (let i = 0; i < 15; i++) {
                const depth = (i / 15 + time * 0.16) % 1;
                const scale = 0.12 + depth ** 2 * 2.1;
                const ww = wide * 0.49 * scale;
                const hh = high * 0.42 * scale;
                ctx.globalAlpha = warp * (1 - depth) * 0.2;
                ctx.strokeRect(center - ww / 2, high * 0.45 - hh / 2, ww, hh);
            }
            for (let i = 0; i < 18; i++) {
                const seed = getSeed(i + 51);
                const depth = (seed + time * 0.13) % 1;
                const scale = 0.4 + depth * 1.8;
                const xx = center + Math.sin(i * 3.7) * wide * 0.47 * scale;
                const yy = high * 0.46 + Math.cos(i * 1.91) * high * 0.37 * scale;
                ctx.globalAlpha = warp * (1 - depth) * 0.4;
                ctx.fillStyle = palette[0];
                ctx.font = Math.round(9 + scale * 5) + 'px ' + face;
                ctx.fillText(['core/system.php', 'getVar()', 'PDOStatement', '{{ mode }}', 'getSiteToken()', 'Template'][i % 6], xx, yy);
            }
        }
        if (!idle && time < 7.5) {
            const travel = getWindow(time, 0.2, 6.7, 0.7);
            ctx.textAlign = 'center';
            for (let i = 0; i < 8; i++) {
                const depth = (i / 8 + time * 0.18) % 1;
                const scale = 0.14 + depth ** 2 * 1.8;
                const side = i % 2 ? 1 : -1;
                const xx = center + side * wide * 0.42 * scale;
                const yy = high * (0.4 + Math.sin(i * 2.9) * 0.33 * scale);
                ctx.globalAlpha = travel * (1 - depth) * 0.14;
                ctx.fillStyle = palette[0];
                ctx.font = Math.round(22 + scale * 24) + 'px ' + face;
                ctx.fillText(String(2005 + i * 3), xx, yy);
            }
            ctx.textAlign = 'start';
        }
        if (!idle && time > 33.75 && time < 37.5) {
            const age = time - 33.75;
            for (let i = 0; i < 3; i++) {
                const radius = Math.max(0, age - i * 0.16) * wide * 0.6;
                if (!radius) continue;
                ctx.globalAlpha = Math.exp(-age * 1.4) * (0.7 - i * 0.15);
                ctx.strokeStyle = palette[i % 2];
                ctx.lineWidth = 0.8;
                ctx.beginPath();
                ctx.ellipse(center, high * 0.44, radius, radius * 0.5, 0, 0, Math.PI * 2);
                ctx.stroke();
            }
        }
        if (power > 0.01) {
            const lens = ctx.createLinearGradient(0, horizon, wide, horizon);
            lens.addColorStop(0, 'transparent');
            lens.addColorStop(0.4, palette[0]);
            lens.addColorStop(0.5, palette[1]);
            lens.addColorStop(0.6, palette[0]);
            lens.addColorStop(1, 'transparent');
            ctx.globalAlpha = power * (0.08 + pulse * 0.08);
            ctx.fillStyle = lens;
            ctx.fillRect(0, high * 0.56, wide, 0.7);
        }
        ctx.globalAlpha = 1;
        ctx.globalCompositeOperation = 'source-over';
    }

    /* Type representative project code as each subsystem joins the new architecture */
    function setCodeFrame(time) {
        const age = Math.max(0, time - 24.4);
        const index = Math.min(3, Math.floor(age / 1.2));
        const text = sources[index].code;
        const count = Math.min(text.length, Math.floor((age - index * 1.2) / 0.94 * text.length));
        if (index !== lastfile) {
            file.textContent = sources[index].file;
            build.textContent = sources[index].label;
            lastfile = index;
            lasttext = -1;
        }
        if (count !== lasttext) {
            code.textContent = text.slice(0, count);
            const total = code.textContent.split('\n').length;
            lines.textContent = Array.from({length: total}, (_, num) => String(num + 1).padStart(2, '0')).join('\n');
            lasttext = count;
        }
        const progress = getClamp(age / 4.8);
        percent.textContent = Math.round(progress * 100) + '%';
        buildbar.style.transform = 'scaleX(' + progress + ')';
        labs.forEach((node, num) => {
            const lit = getEase((age - 0.4 - num * 0.55) / 0.75);
            node.style.opacity = 0.12 + lit * 0.88;
            node.style.borderColor = lit > 0.9 ? 'var(--sl-primary-muted)' : 'var(--sl-pres-frame-border)';
            node.style.transform = 'translateY(' + (1 - lit) * 30 + 'px) scale(' + (0.94 + lit * 0.06) + ')';
        });
        radar.style.transform = 'rotate(' + time * 145 + 'deg)';
        document.querySelectorAll('.sl-phx-trace span').forEach((node, num) => {
            node.style.opacity = 0.3 + getEase((age - 1.4 - num * 0.45) / 0.5) * 0.7;
        });
    }

    /* Assemble code, servers, interface and solved challenges around the unmodified brand mark */
    function setHistoryFrame(time) {
        const progress = getClamp(time / 6.4);
        const value = 2005 + Math.floor(progress * 21);
        if (year.textContent !== String(value)) year.textContent = String(value);
        const words = ['Первая строка кода.', 'Идеи становятся возможностями.', 'Проблемы. Поиск. Решения.', 'Система растёт вместе с нами.', 'Время двигаться дальше.'];
        era.textContent = words[Math.min(4, Math.floor(time / 1.4))];
        yearbar.style.transform = 'scaleX(' + progress + ')';
        works.forEach((node, index) => {
            const enter = getEase((time - 0.5 - index * 0.23) / 1.1);
            const side = index % 2 ? 1 : -1;
            const float = still ? 0 : Math.sin(time * 1.1 + index) * 5;
            const depth = 0.91 + enter * 0.09;
            node.style.opacity = enter;
            const move = (1 - enter) * side * 110;
            node.style.transform = 'translate(' + move + 'px,' + float + 'px) perspective(1000px) rotateY(' + side * -5 + 'deg) scale(' + depth + ')';
        });
        const solve = getEase((time - 3.8) / 1.1);
        document.querySelectorAll('.sl-phx-task').forEach((node, index) => {
            const done = getEase((time - 3.6 - index * 0.4) / 0.7);
            node.querySelector('b').style.opacity = done;
            node.querySelector('i').style.opacity = 0.15 + done * 0.85;
            node.querySelector('span').style.opacity = 1 - done * 0.65;
        });
        document.querySelector('[data-sl-phx-taskhead]').textContent = solve > 0.8 ? 'solution' : 'challenge';
        wires.style.strokeDashoffset = -time * 42;
        idea.style.transform = 'translate(-50%, -50%) scale(' + (0.9 + getEase(time / 2) * 0.1) + ')';
        document.querySelector('.sl-phx-orbits').style.transform = 'rotate(' + time * 8 + 'deg)';
        document.querySelectorAll('.sl-phx-ui-bars i').forEach((node, index) => {
            node.style.transform = 'scaleY(' + (0.4 + (Math.sin(time * 2 + index * 0.6) * 0.5 + 0.5) * 0.6) + ')';
        });
    }

    /* Sweep through the four real product principles as large cinematic image planes */
    function setDnaFrame(time) {
        const age = time - 7.5;
        const index = Math.max(0, Math.min(3, Math.floor(age / 3.75)));
        panels.forEach((node, num) => {
            const local = age - num * 3.75;
            const enter = getEase(local / 0.45);
            const exit = getEase((local - 3.75) / 0.45);
            node.style.opacity = enter * (1 - exit);
            node.querySelector('.sl-phx-dna-copy').style.transform = 'translateX(' + ((1 - enter) * -35 - exit * 30) + 'px)';
            const scale = 0.94 + getEase(local / 4) * 0.09;
            node.querySelector('img').style.transform = 'perspective(1400px) rotateY(' + ((1 - enter) * -12 + exit * 10) + 'deg) scale(' + scale + ')';
            tabs[num].dataset.active = String(num === index);
        });
        document.querySelector('.sl-phx-dna-pulse').style.transform = 'rotate(' + time * 8 + 'deg)';
    }

    /* Choreograph the development narrative on the same twenty-bar musical clock */
    function setSceneFrame(time) {
        spent = Math.min(length, Math.max(0, time));
        const phase = time - 15;
        const dark = root.dataset.theme === 'dark';
        const rise = getEase((phase - 17.8) / 1.7);
        const native = getEase((phase - 19.6) / 1.25) * (1 - getEase((phase - 23.2) / 1.05));
        const settle = getEase((phase - 25.8) / 1.4);
        const reveal = getEase((time - 43.95) / 1.05);
        const origin = getWindow(time, 0.1, 6.9, 0.6);
        history.style.opacity = origin;
        history.style.transform = 'scale(' + (0.97 + getEase(time / 7.5) * 0.04) + ')';
        if (origin > 0.005) setHistoryFrame(time);
        const principle = getWindow(time, 7.5, 22.5, 0.45);
        dna.style.opacity = principle;
        if (principle > 0.005) setDnaFrame(time);
        silence.style.opacity = getWindow(time, 22.65, 24.15, 0.4);
        silence.style.transform = 'scale(' + (0.96 + getEase((time - 22.5) / 1.8) * 0.09) + ')';
        const writing = getWindow(time, 24.4, 29.8, 0.65);
        rewrite.style.opacity = writing;
        rewrite.style.transform = 'translateY(' + (1 - getEase((time - 24.4) / 0.8)) * 24 + 'px)';
        editor.style.transform = 'perspective(1200px) rotateY(' + (1 - getEase((time - 24.4) / 1.2)) * -13 + 'deg)';
        if (writing > 0.005) setCodeFrame(time);
        assemble.style.opacity = getWindow(phase, 15, 16.6, 0.65);
        assemble.style.transform = 'translateY(' + (1 - getEase((phase - 15) / 0.9)) * 30 + 'px)';
        const scale = 0.81 + rise * 0.19;
        const drift = still ? 0 : Math.sin(time * 1.7) * 3;
        const xx = wide / 2 + pointer[0] * 7;
        const yy = high * 0.41 + drift;
        bird.style.opacity = still ? 0 : native;
        bird.style.transform = 'translate(calc(-50% + ' + pointer[0] * 7 + 'px), calc(-50% + ' + drift + 'px)) scale(' + scale + ')';
        bird.style.clipPath = 'inset(0 ' + (1 - getEase((phase - 19.6) / 1.1)) * 43 + '%)';
        reborn.style.opacity = getWindow(phase, 20.1, 23.4, 0.7);
        finale.style.opacity = settle;
        const accent = getEase((time - 42.15) / 0.65);
        motto.style.opacity = accent;
        motto.style.transform = 'translateY(' + (1 - accent) * 18 + 'px) scale(' + (0.94 + accent * 0.06) + ')';
        document.querySelector('.sl-phx-cloud').style.opacity = 1 - settle;
        const opacity = (dark ? 0.18 : 0.07) * getWindow(time, 28.3, 32.9, 1.5);
        portal.style.opacity = opacity;
        portal.style.transform = 'scale(' + (1 + getEase((time - 27.5) / 6) * 0.55) + ')';
        scan.style.opacity = still ? 0 : getWindow(time, 22.5, 29.8, 0.5) * 0.12;
        const impact = still ? 0 : Math.exp(-(((time - 33.75) / 0.13) ** 2)) * 0.62;
        flash.style.opacity = impact;
        stage.style.opacity = 1 - reveal;
        stage.style.transform = 'scale(' + (1 + reveal * 0.07) + ')';
        bar.style.transform = 'scaleX(' + getClamp(time / length) + ')';
        timer.textContent = time.toFixed(1).padStart(4, '0') + ' / ' + length.toFixed(1);
        chapter.textContent = time < 7.5 ? '01 / ДВИЖЕНИЕ ВПЕРЁД' : time < 22.5 ? '02 / DNA ПРОДУКТА' : time < 24.4 ? '03 / ТИШИНА'
            : time < 30 ? '04 / ПЕРЕПИСАН С НУЛЯ' : time < 33.75 ? '05 / СБОРКА' : time < 39.5 ? '06 / ВОЗРОЖДЕНИЕ' : '07 / PHOENIX 8.0';
        if (!still) setWorldFrame(time);
        else setWorldFrame(27.8, true);
        if (cloud && !still) {
            const markbox = [wide / 2, high * 0.45, Math.min(wide * 0.23, high * 0.37), Math.min(wide * 0.23, high * 0.37) * 1.32];
            cloud.setFrame(phase, [xx, yy, birdwide * scale, birdhigh * scale], logobox, markbox);
        }
    }

    /* Reflect playback, sound and completion in accessible controls */
    function setControlState() {
        pause.disabled = !started || ended;
        pause.title = running ? 'Пауза' : 'Продолжить';
        pause.setAttribute('aria-label', pause.title);
        pause.querySelector('i').className = running ? 'bi bi-pause-fill' : 'bi bi-play-fill';
        soundbtn.setAttribute('aria-pressed', String(sound));
        soundbtn.querySelector('i').className = sound ? 'bi bi-volume-up' : 'bi bi-volume-mute';
        soundbtn.title = sound ? 'Выключить звук' : 'Включить звук';
    }

    /* Return to the independent admin preview after the final SVG title */
    function setSceneEnd() {
        if (ended) return;
        ended = true;
        running = false;
        cancelAnimationFrame(frame);
        score.setStop();
        stage.hidden = true;
        panel.removeAttribute('inert');
        setControlState();
        status.textContent = 'Презентация завершена. Открыт макет панели администратора.';
        replay.focus({preventScroll: true});
    }

    /* Advance visuals from the audio clock whenever the anthem is playing */
    function setNextFrame(now) {
        if (!running) return;
        const clock = sound ? score.getClock() : null;
        const time = clock === null ? (now - start) / 1000 : clock;
        setSceneFrame(Math.min(time, length));
        if (time >= length) setSceneEnd();
        else frame = requestAnimationFrame(setNextFrame);
    }

    /* Show a brief static final composition when the system requests less motion */
    function setStillFrame(now) {
        if (!running) return;
        if (now - start > 2200) setSceneEnd();
        else frame = requestAnimationFrame(setStillFrame);
    }

    /* Start or seek the film using a user-approved sound choice */
    async function setScenePlay(audible, offset = 0) {
        if (!ready || pending) return;
        pending = true;
        cancelAnimationFrame(frame);
        score.setStop();
        sound = Boolean(audible && loaded && !still);
        if (sound) sound = await score.setPlay(offset);
        started = true;
        ended = false;
        running = true;
        spent = offset;
        lastfile = -1;
        lasttext = -1;
        cover.hidden = true;
        stage.hidden = false;
        panel.setAttribute('inert', '');
        start = performance.now() - offset * 1000;
        setSceneFrame(still ? 43.3 : offset);
        setControlState();
        pending = false;
        document.querySelector('[data-sl-phx-skip]').focus({preventScroll: true});
        status.textContent = 'Началась история SLAED CMS.';
        frame = requestAnimationFrame(still ? setStillFrame : setNextFrame);
    }

    /* Pause and resume without dropping the musical or visual timeline position */
    async function setScenePause() {
        if (!started || ended || pending) return;
        if (running) {
            cancelAnimationFrame(frame);
            const clock = score.getClock();
            if (clock !== null) spent = clock;
            running = false;
            score.setStop();
            setControlState();
            status.textContent = 'Пауза.';
        } else await setScenePlay(sound, spent);
    }

    /* Change actual system theme tokens without restarting the narrative */
    function setSceneMode(mode) {
        root.dataset.theme = mode;
        document.querySelectorAll('[data-sl-phx-mode]').forEach((node) => {
            node.setAttribute('aria-pressed', String(node.dataset.slPhxMode === mode));
        });
        if (panel.contentDocument && panel.contentDocument.documentElement) panel.contentDocument.documentElement.dataset.theme = mode;
        setPalette();
        if (started && !ended) setSceneFrame(spent);
        else if (!started) setWorldFrame(0, true);
    }

    /* Enable sound at the current musical position after a fresh gesture */
    async function setSoundMode() {
        if (!loaded || pending) return;
        sound = !sound;
        if (!sound) {
            const clock = score.getClock();
            if (clock !== null) spent = clock;
            start = performance.now() - spent * 1000;
            score.setStop();
        } else if (running) {
            pending = true;
            await score.setPlay(spent);
            start = performance.now() - spent * 1000;
            pending = false;
        }
        setControlState();
    }

    /* Keep the cover gently alive before the user starts the film */
    function setCoverFrame(now) {
        if (started) return;
        setWorldFrame((now - coverat) / 1000 * 0.25, true);
        if (!still) frame = requestAnimationFrame(setCoverFrame);
    }

    /* Download the same original composition used by the presentation */
    function getTrackDownload() {
        const wave = score.getWave();
        if (!wave) return;
        if (download) URL.revokeObjectURL(download);
        download = URL.createObjectURL(new Blob([wave], {type: 'audio/wav'}));
        const anchor = document.createElement('a');
        anchor.href = download;
        anchor.download = 'SLAED-Phoenix-Anthem.wav';
        anchor.click();
    }

    const mode = params.get('theme');
    if (mode === 'light' || mode === 'dark') root.dataset.theme = mode;
    panel.addEventListener('load', () => {
        if (panel.contentDocument) panel.contentDocument.documentElement.dataset.theme = root.dataset.theme;
    });
    setPalette();
    setSceneSize();
    portal.style.opacity = root.dataset.theme === 'dark' ? 0.32 : 0.12;
    portal.style.transform = 'translateX(24%) scale(1.15)';
    setWorldFrame(0, true);
    setControlState();
    frame = requestAnimationFrame(setCoverFrame);
    const assets = ['assets/phoenix-codex/phoenix-digital.png', 'assets/phoenix-codex/wordmark.svg', 'assets/phoenix-codex/mark.svg'];
    const photos = [...document.querySelectorAll('.sl-phx-principle > img')].map((node) => node.getAttribute('src'));
    Promise.all([Promise.all(assets.concat(photos).map(getSceneImage)), score.ready]).then(([images, audio]) => {
        cloud = new window.PhoenixCloud(document.querySelector('.sl-phx-cloud'), {bird: images[0], logo: images[1], mark: images[2]});
        loaded = audio;
        ready = true;
        setPalette();
        setSceneSize();
        document.querySelectorAll('[data-sl-phx-start]').forEach((node) => { node.disabled = false; });
        track.disabled = !audio;
        soundbtn.disabled = !audio;
        loader.textContent = still ? 'Движение уменьшено согласно настройкам устройства.' : '45 секунд. Включите звук. Почувствуйте возвращение.';
        const at = Number(params.get('at'));
        if (params.has('at') && Number.isFinite(at)) {
            cancelAnimationFrame(frame);
            cover.hidden = true;
            started = true;
            setSceneFrame(Math.max(0, Math.min(43.8, at)));
            setControlState();
        } else if (params.get('autoplay') === '1') setScenePlay(false);
    }).catch((error) => {
        loader.textContent = 'Не удалось подготовить сцену. Обновите страницу.';
        status.textContent = loader.textContent;
        console.error(error);
    });
    document.querySelectorAll('[data-sl-phx-start]').forEach((node) => {
        node.addEventListener('click', () => setScenePlay(node.dataset.slPhxStart === 'sound'));
    });
    document.querySelectorAll('[data-sl-phx-mode]').forEach((node) => {
        node.addEventListener('click', () => setSceneMode(node.dataset.slPhxMode));
    });
    replay.addEventListener('click', () => setScenePlay(sound));
    pause.addEventListener('click', setScenePause);
    soundbtn.addEventListener('click', setSoundMode);
    track.addEventListener('click', getTrackDownload);
    document.querySelector('[data-sl-phx-volume]').addEventListener('input', (event) => score.setVolume(Number(event.target.value)));
    document.querySelector('[data-sl-phx-skip]').addEventListener('click', setSceneEnd);
    document.addEventListener('pointermove', (event) => { if (!still) pointer = [(event.clientX / wide - 0.5) * 2, (event.clientY / high - 0.5) * 2]; }, {passive: true});
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSceneEnd();
        if (event.target.closest('input')) return;
        if (!event.ctrlKey && !event.metaKey && ['r', 'R', 'к', 'К'].includes(event.key)) {
            event.preventDefault();
            setScenePlay(sound);
            return;
        }
        if (event.target.closest('button, a')) return;
        if (event.code === 'Space') {
            event.preventDefault();
            if (!started) setScenePlay(true);
            else setScenePause();
        }
    });
    window.addEventListener('resize', () => { setSceneSize(); if (started && !ended) setSceneFrame(spent); }, {passive: true});
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && running) setScenePause();
    });
    window.addEventListener('pagehide', () => { cancelAnimationFrame(frame); running = false; score.setStop(); if (download) URL.revokeObjectURL(download); });
    window.addEventListener('pageshow', (event) => { if (event.persisted) { running = false; setControlState(); } });
})();
